<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Concert;
use App\Models\ConcertTicketType;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\Ticket;
use App\Services\ConcertSeatAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingController extends Controller
{
    private const GEN_AD_SECTION = 'General Admission (Gen Ad)';

    public function __construct(
        private readonly ConcertSeatAvailabilityService $seatAvailability,
    ) {
    }

    public function index()
    {
        $bookings = Auth::user()->bookings()
            ->with('concert.venue', 'tickets')
            ->orderBy('id', 'desc') 
            ->get();

        return view('bookings.index', compact('bookings'));
    }

    public function create(Concert $concert)
    {
        $concert->load(['venue', 'concertTicketTypes.ticketType', 'bookings.tickets']);
        $totalSold = $concert->bookings->sum(fn ($b) => $b->tickets->count());
        $eventTicketTotal = $concert->totalTicketAllocation();
        $remainingEventTickets = max(0, $eventTicketTotal - $totalSold);

        $ticketAvailability = $concert->concertTicketTypes->mapWithKeys(function (ConcertTicketType $ctt) use ($concert) {
            return [$ctt->id => $this->seatAvailability->remainingForTicketType($concert, $ctt)];
        })->all();

        return view('bookings.create', compact('concert', 'remainingEventTickets', 'eventTicketTotal', 'ticketAvailability'));
    }

    public function getSeats(Request $request, Concert $concert)
    {
        $ticketTypeId = $request->query('concert_ticket_type_id');

        if (!$ticketTypeId) {
            return response()->json([]);
        }

        $concertTicketType = $concert->concertTicketTypes()->with('ticketType')->find($ticketTypeId);
        if (!$concertTicketType) {
            return response()->json([]);
        }

        return response()->json(
            $this->seatAvailability->seatsPayloadForTicketType($concert, $concertTicketType)
        );
    }

    public function store(Request $request, Concert $concert)
    {
        $request->validate([
            'cart_items' => 'required|json',
        ]);

        $cartItems = json_decode($request->cart_items, true);
        if (!is_array($cartItems) || empty($cartItems)) {
            return back()->withErrors(['cart_items' => 'Please add at least one ticket.']);
        }

        $inventoryError = $this->validateCartAgainstInventory($concert, $cartItems);
        if ($inventoryError !== null) {
            return back()->withErrors(['cart_items' => $inventoryError]);
        }

        // Store cart items in session for review
        session(['booking_cart' => [
            'concert_id' => $concert->id,
            'cart_items' => $cartItems
        ]]);

        return redirect()->route('bookings.review', $concert);
    }

    public function review(Concert $concert)
    {
        $cartData = session('booking_cart');
        if (!$cartData || $cartData['concert_id'] != $concert->id) {
            return redirect()->route('bookings.create', $concert)->withErrors(['general' => 'Session expired. Please select tickets again.']);
        }

        $cartItems = $cartData['cart_items'];
        $cartTotals = $this->calculateCartTotals($concert, $cartItems);
        $totalQuantity = $cartTotals['totalQuantity'];
        $totalPrice = $cartTotals['totalPrice'];
        $priceRecords = $cartTotals['priceRecords'];

        $inventoryError = $this->validateCartAgainstInventory($concert, $cartItems);
        if ($inventoryError !== null) {
            return redirect()->route('bookings.create', $concert)->withErrors(['cart_items' => $inventoryError]);
        }

        $selectedSeats = [];

        foreach ($cartItems as $item) {
            $ticketTypeId = $item['concert_ticket_type_id'] ?? null;
            if (!$ticketTypeId || !isset($priceRecords[$ticketTypeId])) {
                return redirect()->route('bookings.create', $concert)->withErrors(['cart_items' => 'Invalid ticket type selected.']);
            }

            if (isset($item['seat_id'])) {
                $selectedSeats[] = $item;
            }
        }

        $concert->load('venue', 'concertTicketTypes');
        $eventTicketTotal = $concert->totalTicketAllocation();

        return view('bookings.review', compact('concert', 'cartItems', 'totalPrice', 'totalQuantity', 'selectedSeats', 'priceRecords', 'eventTicketTotal'));
    }

    public function checkout(Concert $concert)
    {
        $cartData = session('booking_cart');
        if (!$cartData || $cartData['concert_id'] != $concert->id) {
            return redirect()->route('bookings.create', $concert)->withErrors(['general' => 'Session expired. Please select tickets again.']);
        }

        $cartItems = $cartData['cart_items'];
        $cartTotals = $this->calculateCartTotals($concert, $cartItems);
        $totalQuantity = $cartTotals['totalQuantity'];
        $totalPrice = $cartTotals['totalPrice'];
        $priceRecords = $cartTotals['priceRecords'];

        $inventoryError = $this->validateCartAgainstInventory($concert, $cartItems);
        if ($inventoryError !== null) {
            return redirect()->route('bookings.create', $concert)->withErrors(['cart_items' => $inventoryError]);
        }

        foreach ($cartItems as $item) {
            $ticketTypeId = $item['concert_ticket_type_id'] ?? null;
            if (!$ticketTypeId || !isset($priceRecords[$ticketTypeId])) {
                return redirect()->route('bookings.create', $concert)->withErrors(['cart_items' => 'Invalid ticket type selected.']);
            }
        }

        $concert->load('venue');
        return view('bookings.checkout', compact('concert', 'cartItems', 'totalPrice', 'totalQuantity', 'priceRecords'));
    }

    public function confirmPayment(Request $request, Concert $concert)
    {
        $request->validate([
            'card_number' => ['required', 'string', 'regex:/^[0-9 ]{13,19}$/'],
            'expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cvv' => ['required', 'digits_between:3,4'],
            'cardholder_name' => ['required', 'string', 'max:100'],
            'terms' => ['accepted'],
        ]);

        $cartData = session('booking_cart');
        if (!$cartData || $cartData['concert_id'] != $concert->id) {
            return redirect()->route('bookings.create', $concert)->withErrors(['general' => 'Session expired. Please select tickets again.']);
        }

        $cartItems = $cartData['cart_items'];
        $concert->loadMissing('concertTicketTypes.ticketType');
        $ticketTypes = $concert->concertTicketTypes->keyBy('id');
        $priceRecords = $ticketTypes->mapWithKeys(fn($type) => [$type->id => $type->price])->all();
        $seatRequiredTypes = ['VIP Seated', 'LBB', 'UBB', 'LBA', 'UBA'];

        $inventoryError = $this->validateCartAgainstInventory($concert, $cartItems);
        if ($inventoryError !== null) {
            return back()->withErrors(['cart_items' => $inventoryError]);
        }

        $totalQuantity = 0;
        $seatItems = [];
        $autoAssignItems = [];

        foreach ($cartItems as $item) {
            $ticketTypeId = $item['concert_ticket_type_id'] ?? null;
            $concertTicketType = $ticketTypes[$ticketTypeId];
            $ticketTypeSlug = $concertTicketType->ticketType->name ?? '';
            $ticketTypeLabel = $concertTicketType->custom_name ?: ($concertTicketType->ticketType->description ? $concertTicketType->ticketType->description . ' (' . $ticketTypeSlug . ')' : $ticketTypeSlug);
            $requiresSeat = in_array($ticketTypeSlug, $seatRequiredTypes, true);

            if ($requiresSeat) {
                if (empty($item['seat_id'])) {
                    return back()->withErrors(['cart_items' => 'Please select a seat for each reserved ticket.']);
                }

                $seatItems[] = [
                    'concert_ticket_type_id' => $ticketTypeId,
                    'ticket_type' => $ticketTypeLabel,
                    'seat_id' => $item['seat_id'],
                ];
                $totalQuantity += 1;
            } else {
                if (!isset($item['quantity']) || !is_numeric($item['quantity'])) {
                    return back()->withErrors(['cart_items' => 'Invalid ticket quantity.']);
                }

                $quantity = (int) $item['quantity'];
                if ($quantity < 1 || $quantity > 5) {
                    return back()->withErrors(['cart_items' => 'Quantity must be between 1 and 5.']);
                }

                $autoAssignItems[] = [
                    'concert_ticket_type_id' => $ticketTypeId,
                    'ticket_type' => $ticketTypeLabel,
                    'quantity' => $quantity,
                ];
                $totalQuantity += $quantity;
            }
        }

        if ($totalQuantity < 1) {
            return back()->withErrors(['cart_items' => 'Please add at least one ticket.']);
        }

        if ($totalQuantity > 5) {
            return back()->withErrors(['cart_items' => 'Maximum 5 tickets per booking.']);
        }

        // Validate each seat selection for availability
        foreach ($seatItems as $item) {
            $ticketTypeId = $item['concert_ticket_type_id'];
            $seatId = $item['seat_id'];
            $concertTicketType = $ticketTypes[$ticketTypeId];

            // Get seat details
            $seat = Seat::find($seatId);
            if (!$seat || $seat->venue_id !== $concert->venue_id) {
                return back()->withErrors(['cart_items' => 'Invalid seat selection.']);
            }

            if (! $this->seatAvailability->isSeatEligibleForTicketType($seat, $concert, $concertTicketType)) {
                return back()->withErrors(['cart_items' => 'Selected seat is not in the allowed set for this ticket type. Please choose another seat.']);
            }

            // Verify seat hasn't been sold since user selected it (race condition prevention)
            $alreadyBookedForThisSeat = Ticket::where('seat_id', $seatId)
                ->where('concert_ticket_type_id', $ticketTypeId)
                ->exists();

            if ($alreadyBookedForThisSeat) {
                return back()->withErrors(['cart_items' => 'Seat ' . $seat->seat_number . ' has already been booked by another customer. Please select a different seat.']);
            }
        }

        $totalPrice = 0;
        foreach ($seatItems as $item) {
            $totalPrice += $priceRecords[$item['concert_ticket_type_id']];
        }
        foreach ($autoAssignItems as $item) {
            $totalPrice += $priceRecords[$item['concert_ticket_type_id']] * $item['quantity'];
        }

        $paymentMethod = trim((string) $request->input('card_number'));

        try {
            $booking = DB::transaction(function () use ($concert, $seatItems, $autoAssignItems, $totalPrice, $totalQuantity, $priceRecords, $ticketTypes, $paymentMethod) {
                $booking = Booking::create([
                    'user_id' => Auth::id(),
                    'concert_id' => $concert->id,
                    'total_price' => $totalPrice,
                    'status' => 'confirmed',
                ]);

                foreach ($seatItems as $item) {
                    $concertTicketType = $ticketTypes[$item['concert_ticket_type_id']];
                    $ticketTypeSlug = $concertTicketType->ticketType->name ?? '';
                    $ticketTypeLabel = $concertTicketType->custom_name ?: ($concertTicketType->ticketType->description ? $concertTicketType->ticketType->description . ' (' . $ticketTypeSlug . ')' : $ticketTypeSlug);

                    $seat = Seat::find($item['seat_id']);
                    if (! $seat || $seat->venue_id !== $concert->venue_id
                        || ! $this->seatAvailability->isSeatEligibleForTicketType($seat, $concert, $concertTicketType)) {
                        throw new RuntimeException('Invalid seat selection.');
                    }

                    Ticket::create([
                        'booking_id' => $booking->id,
                        'concert_ticket_type_id' => $item['concert_ticket_type_id'],
                        'seat_id' => $item['seat_id'],
                        'ticket_type' => $ticketTypeLabel,
                        'price_at_purchase' => $priceRecords[$item['concert_ticket_type_id']],
                        'qr_code' => uniqid(),
                    ]);
                }

                foreach ($autoAssignItems as $item) {
                    $concertTicketType = $ticketTypes[$item['concert_ticket_type_id']];
                    $ticketTypeSlug = $concertTicketType->ticketType->name ?? '';
                    $ticketTypeLabel = $concertTicketType->custom_name ?: ($concertTicketType->ticketType->description ? $concertTicketType->ticketType->description . ' (' . $ticketTypeSlug . ')' : $ticketTypeSlug);
                    $assignedSeatIds = $this->allocateGenAdSeatIds($concert, $concertTicketType, (int) $item['quantity']);

                    for ($i = 0; $i < $item['quantity']; $i++) {
                        Ticket::create([
                            'booking_id' => $booking->id,
                            'concert_ticket_type_id' => $item['concert_ticket_type_id'],
                            'seat_id' => $assignedSeatIds[$i] ?? null,
                            'ticket_type' => $ticketTypeLabel,
                            'price_at_purchase' => $priceRecords[$item['concert_ticket_type_id']],
                            'qr_code' => uniqid(),
                        ]);
                    }
                }

                Payment::create([
                    'booking_id' => $booking->id,
                    'amount' => $totalPrice,
                    'payment_method' => $paymentMethod,
                    'status' => 'paid',
                ]);

                // Log the booking activity
                ActivityLog::record([
                    'user_id' => Auth::id(),
                    'action' => 'create',
                    'entity_type' => 'booking',
                    'entity_id' => $booking->id,
                    'description' => 'Booked tickets for concert: ' . $concert->title . ' (' . $totalQuantity . ' tickets, ₱' . number_format($totalPrice, 2) . ')',
                ]);

                return $booking;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart_items' => $e->getMessage()]);
        }

        // Clear session
        session()->forget('booking_cart');

        if (! $booking) {
            return redirect()
                ->route('bookings.create', $concert)
                ->withErrors(['general' => 'Payment succeeded but booking confirmation could not be loaded. Please check your bookings.']);
        }

        return redirect()->route('bookings.tickets', ['booking' => $booking->id]);
    }

    public function tickets(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        $booking->load('concert.venue', 'tickets.seat', 'payment');
        return view('bookings.tickets', compact('booking'));
    }

    public function show(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        $booking->load('concert.venue', 'payment');
        $tickets = $booking->tickets()->with('seat')->paginate(1);
        return view('bookings.show', compact('booking', 'tickets'));
    }

    private function calculateCartTotals(Concert $concert, array $cartItems): array
    {
        $ticketTypes = $concert->concertTicketTypes->keyBy('id');
        $priceRecords = $ticketTypes->mapWithKeys(fn($type) => [$type->id => $type->price])->all();

        $totalQuantity = 0;
        $totalPrice = 0;

        foreach ($cartItems as $item) {
            $ticketTypeId = (int) ($item['concert_ticket_type_id'] ?? 0);
            $quantity = isset($item['seat_id']) ? 1 : max(1, (int) ($item['quantity'] ?? 1));
            $totalQuantity += $quantity;
            $totalPrice += $priceRecords[$ticketTypeId] * $quantity;
        }

        return [
            'totalQuantity' => $totalQuantity,
            'totalPrice' => $totalPrice,
            'priceRecords' => $priceRecords,
        ];
    }

    /**
     * Validates aggregated quantities per ticket option against remaining inventory (handles split cart lines).
     */
    private function validateCartAgainstInventory(Concert $concert, array $cartItems): ?string
    {
        $concert->loadMissing('concertTicketTypes.ticketType');
        $ticketTypes = $concert->concertTicketTypes->keyBy(fn ($t) => (int) $t->id);

        $requestedByType = [];
        foreach ($cartItems as $item) {
            $ticketTypeId = (int) ($item['concert_ticket_type_id'] ?? 0);
            $concertTicketType = $ticketTypes->get($ticketTypeId);
            if (!$ticketTypeId || !$concertTicketType) {
                return 'Invalid ticket type selected.';
            }

            $qty = isset($item['seat_id']) ? 1 : max(1, (int) ($item['quantity'] ?? 1));
            $requestedByType[$ticketTypeId] = ($requestedByType[$ticketTypeId] ?? 0) + $qty;
        }

        foreach ($requestedByType as $ticketTypeId => $requestedQty) {
            $concertTicketType = $ticketTypes->get((int) $ticketTypeId);
            $remaining = $this->seatAvailability->remainingForTicketType($concert, $concertTicketType);

            if ($requestedQty > $remaining) {
                return 'Not enough tickets available for '.$concertTicketType->section.'.';
            }
        }

        return null;
    }

    /**
     * Gen Ad-only allocator: random single seat, or random-start consecutive block.
     *
     * @return list<int|null>
     */
    private function allocateGenAdSeatIds(Concert $concert, ConcertTicketType $concertTicketType, int $quantity): array
    {
        $slug = $concertTicketType->ticketType->name ?? '';
        $section = $this->seatAvailability->sectionForTicketTypeSlug($slug);
        if ($section !== self::GEN_AD_SECTION) {
            return array_fill(0, $quantity, null);
        }

        if ($quantity < 1) {
            throw new RuntimeException('Invalid ticket quantity.');
        }

        $ticketLimit = max(0, (int) $concertTicketType->quantity);
        if ($ticketLimit < $quantity) {
            throw new RuntimeException('Not enough General Admission tickets are available.');
        }

        $eligibleSeats = Seat::query()
            ->where('venue_id', $concert->venue_id)
            ->where('section', self::GEN_AD_SECTION)
            ->where('status', 'available')
            ->orderByRaw('CAST(seat_number AS UNSIGNED) ASC')
            ->limit($ticketLimit)
            ->lockForUpdate()
            ->get(['id', 'seat_number']);

        if ($eligibleSeats->count() < $quantity) {
            throw new RuntimeException('Not enough General Admission seats are currently available.');
        }

        $eligibleIds = $eligibleSeats->pluck('id')->all();
        $soldSeatIds = Ticket::query()
            ->where('concert_ticket_type_id', $concertTicketType->id)
            ->whereIn('seat_id', $eligibleIds)
            ->lockForUpdate()
            ->pluck('seat_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $availableSeats = $eligibleSeats
            ->reject(fn ($seat) => in_array((int) $seat->id, $soldSeatIds, true))
            ->values();

        if ($availableSeats->count() < $quantity) {
            throw new RuntimeException('Not enough General Admission seats are currently available.');
        }

        if ($quantity === 1) {
            $chosen = $availableSeats->random();

            return [(int) $chosen->id];
        }

        $seats = $availableSeats
            ->map(fn ($seat) => ['id' => (int) $seat->id, 'num' => (int) $seat->seat_number])
            ->sortBy('num')
            ->values()
            ->all();

        $maxStart = count($seats) - $quantity;
        if ($maxStart < 0) {
            throw new RuntimeException('Not enough General Admission seats are currently available.');
        }

        $startIndexes = range(0, $maxStart);
        shuffle($startIndexes);

        foreach ($startIndexes as $start) {
            $candidate = array_slice($seats, $start, $quantity);
            if (count($candidate) !== $quantity) {
                continue;
            }

            $consecutive = true;
            for ($i = 1; $i < $quantity; $i++) {
                if ($candidate[$i]['num'] !== $candidate[$i - 1]['num'] + 1) {
                    $consecutive = false;
                    break;
                }
            }

            if ($consecutive) {
                return array_map(fn ($seat) => $seat['id'], $candidate);
            }
        }

        throw new RuntimeException('Unable to allocate consecutive General Admission seats. Please try again.');
    }

}
