<?php

namespace Database\Seeders;

use App\Models\Concert;
use App\Models\ConcertSeat;
use App\Models\Seat;
use Illuminate\Database\Seeder;

class ConcertSeatSeeder extends Seeder
{
    public function run(): void
    {
        $concerts = Concert::all();

        foreach ($concerts as $concert) {
            $seats = Seat::where('venue_id', $concert->venue_id)->get();

            foreach ($seats as $seat) {
                ConcertSeat::create([
                    'concert_id' => $concert->id,
                    'seat_id' => $seat->id,
                    'status' => 'available',
                ]);
            }
        }
    }
}