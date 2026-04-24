<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;

class VenueApiController extends Controller
{
    public function index()
    {
        return response()->json(Venue::orderByDesc('id')->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
        ]);

        $venue = Venue::create($data);
        return response()->json($venue, 201);
    }

    public function show(Venue $venue)
    {
        return response()->json($venue->load('seats'));
    }

    public function update(Request $request, Venue $venue)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
        ]);
        $venue->update($data);
        return response()->json($venue);
    }

    public function destroy(Venue $venue)
    {
        $venue->delete();
        return response()->json(['message' => 'Venue deleted']);
    }
}
