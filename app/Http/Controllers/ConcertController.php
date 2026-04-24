<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Concert;
use App\Models\TicketPrice;
use App\Models\Venue;
use Illuminate\Http\Request;

class ConcertController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $concerts = Concert::with('venue')->paginate(10);
        return view('admin.concerts.index', compact('concerts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $venues = Venue::all();
        return view('admin.concerts.create', compact('venues'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'artist' => 'required|string|max:255',
            'venue_id' => 'required|exists:venues,id',
            'date' => 'required|date|after:today',
            'time' => 'required|date_format:H:i',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['title', 'description', 'artist', 'venue_id', 'date', 'time']);

        if ($request->hasFile('poster')) {
            $data['poster_url'] = $request->file('poster')->store('posters', 'public');
        }

        $data['seat_plan_image'] = $request->hasFile('seat_plan_image') ? $request->file('seat_plan_image')->store('seat-plans', 'public') : null;
        $concert = Concert::create($data);
        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'create',
            'entity_type' => 'concert',
            'entity_id' => $concert->id,
            'description' => 'Created concert: '.$concert->title,
        ]);

        // Create default ticket prices for new types
        $ticketTypes = ['VIP Standing', 'VIP Seated', 'Lower Box B (LBB)', 'Upper Box B (UBB)', 'General Admission (Gen Ad)'];
        $defaultPrices = [250.00, 200.00, 150.00, 100.00, 75.00];

        foreach ($ticketTypes as $index => $type) {
            TicketPrice::create([
                'concert_id' => $concert->id,
                'section' => $type,
                'price' => $defaultPrices[$index],
            ]);
        }

        return redirect()->route('admin.concerts.index')->with('success', 'Concert created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Concert $concert)
    {
        $concert->load(['venue', 'ticketPrices', 'bookings']);
        return view('admin.concerts.show', compact('concert'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Concert $concert)
    {
        $venues = Venue::all();
        return view('admin.concerts.edit', compact('concert', 'venues'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Concert $concert)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'artist' => 'required|string|max:255',
            'venue_id' => 'required|exists:venues,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'seat_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['title', 'description', 'artist', 'venue_id', 'date', 'time']);

        if ($request->hasFile('poster')) {
            $data['poster_url'] = $request->file('poster')->store('posters', 'public');
        }
        
        if ($request->hasFile('seat_plan_image')) {
            $data['seat_plan_image'] = $request->file('seat_plan_image')->store('seat-plans', 'public');
        }

        $oldVenueId = $concert->venue_id;
        $concert->update($data);

        $description = 'Updated concert: '.$concert->title;
        $venueChanged = $oldVenueId != $request->venue_id;
        if ($venueChanged) {
            $this->regenerateTicketPrices($concert);
            $description .= ' (venue changed, ticket prices regenerated)';
        }

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'entity_type' => 'concert',
            'entity_id' => $concert->id,
            'description' => $description,
        ]);

        $successMsg = 'Concert updated successfully.';
        if ($venueChanged) {
            $successMsg .= ' Seats and ticket prices regenerated for new venue.';
        }
        return redirect()->route('admin.concerts.index')->with('success', $successMsg);
    }

    /**
     * Regenerate concert seats for the current venue
     */
    private function regenerateConcertSeats(Concert $concert)
    {
        // Delete existing seats for this concert
        ConcertSeat::where('concert_id', $concert->id)->delete();

        $venue = $concert->venue;
        foreach ($venue->seats as $seat) {
            ConcertSeat::create([
                'concert_id' => $concert->id,
                'seat_id' => $seat->id,
                'status' => 'available',
            ]);
        }
    }

    /**
     * Regenerate default ticket prices
     */
    private function regenerateTicketPrices(Concert $concert)
    {
        // Delete existing ticket prices
        TicketPrice::where('concert_id', $concert->id)->delete();

        $sections = ['Floor', 'Lower Bowl', 'Upper Bowl', 'Balcony'];
        $defaultPrices = [150.00, 100.00, 75.00, 50.00];

        foreach ($sections as $index => $section) {
            TicketPrice::create([
                'concert_id' => $concert->id,
                'section' => $section,
                'price' => $defaultPrices[$index],
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Concert $concert)
    {
        $title = $concert->title;
        $id = $concert->id;
        $concert->delete();
        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'entity_type' => 'concert',
            'entity_id' => $id,
            'description' => 'Deleted concert: '.$title,
        ]);
        return redirect()->route('admin.concerts.index')->with('success', 'Concert deleted successfully.');
    }
}

