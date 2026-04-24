<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Venue;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index()
    {
        $venues = Venue::paginate(10);
        return view('admin.venues.index', compact('venues'));
    }

    public function create()
    {
        return view('admin.venues.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'seat_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['name', 'location', 'capacity']);

        if ($request->hasFile('seat_plan_image')) {
            $data['seat_plan_image'] = $request->file('seat_plan_image')->store('venue-plans', 'public');
        }

        $venue = Venue::create($data);

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'create',
            'entity_type' => 'venue',
            'entity_id' => $venue->id,
            'description' => 'Created venue: '.$venue->name,
        ]);

        return redirect()->route('admin.venues.index')->with('success', 'Venue created successfully.');
    }


    public function show(Venue $venue)
    {
        return view('admin.venues.show', compact('venue'));
    }

    public function edit(Venue $venue)
    {
        return view('admin.venues.edit', compact('venue'));
    }

    public function update(Request $request, Venue $venue)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'seat_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['name', 'location', 'capacity']);
        $request->request->remove('seat_plan_image');

        if ($request->hasFile('seat_plan_image')) {
            $data['seat_plan_image'] = $request->file('seat_plan_image')->store('venue-plans', 'public');
        }

        $venue->update($data);

        $description = 'Updated venue: '.$venue->name;
        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'update',
            'entity_type' => 'venue',
            'entity_id' => $venue->id,
            'description' => $description,
        ]);

        $successMsg = 'Venue updated successfully.';
        return redirect()->route('admin.venues.index')->with('success', $successMsg);
    }

    public function destroy(Venue $venue)
    {
        $name = $venue->name;
        $id = $venue->id;
        $venue->delete();
        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'entity_type' => 'venue',
            'entity_id' => $id,
            'description' => 'Deleted venue: '.$name,
        ]);
        return redirect()->route('admin.venues.index')->with('success', 'Venue deleted successfully.');
    }


}
