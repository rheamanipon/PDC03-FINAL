<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Concert;
use App\Models\ConcertSeat;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
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
        $totalSold = $concert->bookings->sum(fn($b) => $b->tickets->count());
        $remaining = $concert->venue->capacity - $totalSold;
        $concert->load('venue', 'ticketPrices');
        return view('bookings.create', compact('concert', 'remaining'));
    }

    public function getSeats(Concert $concert)
    {
        $seats = \DB::table('seats')
            ->leftJoin('concert_seats', function($join) use ($concert) {
                $join->on('seats.id', '=', 'concert_seats.seat_id')
                     ->where('concert_seats.concert_id', '=', $concert->id);
            })
            ->where('seats.venue_id', $concert->venue_id)
            ->select('seats.id', 'seats.seat_number', 'seats.section', 
                    \DB::raw('COALESCE(concert_seats.status, "available") as status'))
            ->get();

        return response()->json($seats);
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
        $priceRecords = $concert->ticketPrices->pluck('price', 'section');

        // Calculate totals and validate
        $totalQuantity = 0;
        $totalPrice = 0;
        $selectedSeats = [];

        foreach ($cartItems as $item) {
            $quantity = $item['quantity'] ?? 1;
            $totalQuantity += $quantity;
            $totalPrice += $priceRecords[$item['ticket_type']] * $quantity;

            if (isset($item['seat_id'])) {
                $selectedSeats[] = $item;
            }
        }

        $concert->load('venue');
        return view('bookings.review', compact('concert', 'cartItems', 'totalPrice', 'totalQuantity', 'selectedSeats', 'priceRecords'));
    }

    public function checkout(Concert $concert)
    {
        $cartData = session('booking_cart');
        if (!$cartData || $cartData['concert_id'] != $concert->id) {
            return redirect()->route('bookings.create', $concert)->withErrors(['general' => 'Session expired. Please select tickets again.']);
        }

        $cartItems = $cartData['cart_items'];
        $priceRecords = $concert->ticketPrices->pluck('price', 'section');

        // Calculate totals
        $totalQuantity = 0;
        $totalPrice = 0;
        foreach ($cartItems as $item) {
            $quantity = $item['quantity'] ?? 1;
            $totalQuantity += $quantity;
            $totalPrice += $priceRecords[$item['ticket_type']] * $quantity;
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

        $validTypes = ['VIP Standing', 'General Admission (Gen Ad)', 'Lower Box B (LBB)', 'Upper Box B (UBB)'];
        $seatTypes = ['Lower Box B (LBB)', 'Upper Box B (UBB)'];
        $priceRecords = $concert->ticketPrices->pluck('price', 'section');

        $totalQuantity = 0;
        $seatItems = [];
        $autoAssignItems = [];

        foreach ($cartItems as $item) {
            if (!isset($item['ticket_type']) || !in_array($item['ticket_type'], $validTypes)) {
                return back()->withErrors(['cart_items' => 'Invalid ticket type.']);
            }

            $ticketType = $item['ticket_type'];

            if (in_array($ticketType, $seatTypes)) {
                if (empty($item['seat_id'])) {
                    return back()->withErrors(['cart_items' => 'Please select a seat for each reserved ticket.']);
                }

                $seatItems[] = [
                    'ticket_type' => $ticketType,
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
                    'ticket_type' => $ticketType,
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

        $totalSold = $concert->bookings->sum(fn($b) => $b->tickets->count());
        if ($totalSold + $totalQuantity > $concert->venue->capacity) {
            return back()->withErrors(['general' => 'Not enough remaining capacity.']);
        }

        $totalPrice = 0;
        foreach ($seatItems as $item) {
            $totalPrice += $priceRecords[$item['ticket_type']];
        }
        foreach ($autoAssignItems as $item) {
            $totalPrice += $priceRecords[$item['ticket_type']] * $item['quantity'];
        }

        DB::transaction(function () use ($concert, $seatItems, $autoAssignItems, $totalPrice, $priceRecords) {
            $booking = Booking::create([
                'user_id' => Auth::id(),
                'concert_id' => $concert->id,
                'total_price' => $totalPrice,
                'status' => 'confirmed',
            ]);

            foreach ($seatItems as $item) {
                $seat = Seat::find($item['seat_id']);
                if (!$seat || $seat->section !== $item['ticket_type']) {
                    throw new \Exception('Invalid seat selection.');
                }

                $concertSeat = ConcertSeat::where('concert_id', $concert->id)
                    ->where('seat_id', $item['seat_id'])
                    ->first();

                if (!$concertSeat || $concertSeat->status !== 'available') {
                    throw new \Exception('Seat no longer available');
                }

                $concertSeat->update(['status' => 'reserved']);

                Ticket::create([
                    'booking_id' => $booking->id,
                    'seat_id' => $item['seat_id'],
                    'ticket_type' => $item['ticket_type'],
                    'price_at_purchase' => $priceRecords[$item['ticket_type']],
                    'qr_code' => uniqid(),
                ]);
            }

            foreach ($autoAssignItems as $item) {
                $availableSeats = ConcertSeat::where('concert_id', $concert->id)
                    ->where('status', 'available')
                    ->inRandomOrder()
                    ->limit($item['quantity'])
                    ->get();

                if ($availableSeats->count() < $item['quantity']) {
                    throw new \Exception('Not enough seats available');
                }

                foreach ($availableSeats as $concertSeat) {
                    $concertSeat->update(['status' => 'reserved']);

                    Ticket::create([
                        'booking_id' => $booking->id,
                        'seat_id' => $concertSeat->seat_id,
                        'ticket_type' => $item['ticket_type'],
                        'price_at_purchase' => $priceRecords[$item['ticket_type']],
                        'qr_code' => uniqid(),
                    ]);
                }
            }

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $totalPrice,
                'payment_method' => 'credit_card',
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

        // Clear session
        session()->forget('booking_cart');

        // Get the latest booking for this user and concert
        $booking = Booking::where('user_id', Auth::id())
            ->where('concert_id', $concert->id)
            ->latest()
            ->first();

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
}
