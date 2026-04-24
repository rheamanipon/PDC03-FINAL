<?php

namespace Database\Seeders;

use App\Models\Concert;
use App\Models\TicketPrice;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class ConcertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure venue exists (Madison Square Garden from VenueSeeder)
        $venueId = 1;
        $venue = Venue::find($venueId);

        $concerts = [
            [
                'title' => 'Born Pink World Tour Manila',
                'description' => 'Epic comeback concert by BLACKPINK featuring hits from Born Pink album.',
                'artist' => 'BLACKPINK',
                'date' => '2026-12-01',
                'time' => '19:00:00',
            ],
            [
                'title' => 'Permission to Dance On Stage Manila',
                'description' => 'BTS brings their stadium-filling Permission to Dance tour to Manila!',
                'artist' => 'BTS',
                'date' => '2026-12-08',
                'time' => '19:00:00',
            ],
            [
                'title' => 'BINIverse Concert',
                'description' => 'P-pop sensation BINI takes over the stage in their universe-themed concert.',
                'artist' => 'BINI',
                'date' => '2026-12-15',
                'time' => '20:00:00',
            ],
            [
                'title' => 'Pagtatag! World Tour Manila',
                'description' => 'SB19 returns home for their powerful Pagtatag! World Tour performance.',
                'artist' => 'SB19',
                'date' => '2026-12-22',
                'time' => '19:00:00',
            ],
            [
                'title' => 'Follow Tour Manila',
                'description' => 'SEVENTEEN\'s dynamic 13-member group performs their Follow Tour in Manila.',
                'artist' => 'SEVENTEEN',
                'date' => '2027-01-05',
                'time' => '19:00:00',
            ],
        ];

        foreach ($concerts as $concertData) {
            $concert = Concert::create([
                'title' => $concertData['title'],
                'description' => $concertData['description'],
                'artist' => $concertData['artist'],
                'venue_id' => $venueId,
                'date' => $concertData['date'],
                'time' => $concertData['time'],
                'poster_url' => null,
            ]);

            // Create default ticket prices for new types
            $ticketTypes = ['VIP Standing', 'Lower Box B (LBB)', 'Upper Box B (UBB)', 'General Admission (Gen Ad)'];
            $defaultPrices = [250.00, 150.00, 100.00, 75.00];

            foreach ($ticketTypes as $index => $type) {
                TicketPrice::create([
                    'concert_id' => $concert->id,
                    'section' => $type,
                    'price' => $defaultPrices[$index],
                ]);
            }
        }
    }
}

