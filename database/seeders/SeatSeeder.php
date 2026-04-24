<?php

namespace Database\Seeders;

use App\Models\Seat;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::first(); // Madison Square Garden

        if (!$venue) {
            return;
        }

        // Create seats for different sections
        $sections = [
            'VIP Standing' => ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10'],
            'Lower Box B (LBB)' => range(1, 50), // 50 seats
            'Upper Box B (UBB)' => range(1, 100), // 100 seats
            'General Admission (Gen Ad)' => range(1, 140), // 140 seats
        ];

        foreach ($sections as $section => $seats) {
            foreach ($seats as $seatNumber) {
                Seat::create([
                    'venue_id' => $venue->id,
                    'seat_number' => (string) $seatNumber,
                    'section' => $section,
                ]);
            }
        }
    }
}