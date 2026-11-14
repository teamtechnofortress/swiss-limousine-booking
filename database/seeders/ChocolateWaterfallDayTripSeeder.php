<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\TripContentSeeder;

/** Draft content based on the supplied Lindt, Rhine Falls and Zurich tour artwork. */
final class ChocolateWaterfallDayTripSeeder extends TripContentSeeder
{
    public function run(): void
    {
        $content = [
            'overview' => 'Enjoy one sweet private day from Zurich, pairing the Lindt Home of Chocolate with the power of Rhine Falls and time to explore Zurich’s historic streets and lakeside promenade.',
            'hero_image_path' => null,
            'hero_image_url' => 'https://placehold.co/1920x800/552512/ffffff?text=Replace+Chocolate+Waterfall+Tour+Hero+Image',
            'countries' => [
                ['name' => 'Switzerland', 'logo_url' => 'https://placehold.co/240x160/f8fafc/552512?text=Switzerland'],
            ],
            'stops' => [
                ['city' => 'Lindt Home of Chocolate, Kilchberg', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/552512/ffffff?text=Replace+Lindt+Image'],
                ['city' => 'Rhine Falls, Schaffhausen', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/552512/ffffff?text=Replace+Rhine+Falls+Image'],
                ['city' => 'Zurich City', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/552512/ffffff?text=Replace+Zurich+City+Image'],
                ['city' => 'Zurich', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/552512/ffffff?text=Replace+Zurich+Return+Image'],
            ],
            'itinerary' => implode("\n\n", [
                '08:00 AM – Depart Zurich | Meet your private chauffeur for an eight-hour chocolate and waterfall discovery tour.',
                '08:30 AM – Lindt Home of Chocolate, Kilchberg | Explore the world-famous chocolate museum, tasting experience and giant chocolate fountain.',
                '11:30 AM – Rhine Falls, Schaffhausen | Experience Europe’s largest waterfall from the viewpoints; boat rides are optional and subject to availability.',
                '02:30 PM – Zurich City | Explore the Old Town, Bahnhofstrasse and the Lake Zurich promenade.',
                '04:00 PM – Return to Zurich | Finish the tour back in Zurich after a sweet day of Swiss highlights.',
            ]),
            'comfort_car' => 'Mercedes-Benz V-Class 2026',
            'included_items' => implode("\n", ['Luxury Mercedes-Benz V-Class for up to 7 passengers', 'Professional private driver', 'Bottled water', 'Photo stops at the best locations', 'Flexible private experience']),
            'not_included_items' => implode("\n", ['Lindt museum admission and tastings', 'Rhine Falls boat ride and entrance fees', 'Meals and beverages beyond bottled water', 'Personal purchases and gratuities']),
        ];

        $this->seedTrip('chocolate-waterfall-day-trip', [
            'title' => 'Switzerland: 4 Incredible Stops – Chocolate & Waterfall',
            'subtitle' => 'An eight-hour private Zurich day tour of chocolate, waterfalls and city highlights.',
            'summary' => 'Visit Lindt Home of Chocolate, Rhine Falls and Zurich City on a private eight-hour round trip from Zurich, returning by 4:00 PM.',
            'region' => 'Zurich and Schaffhausen',
            'status' => 'draft', 'pricing_mode' => 'enquiry', 'fixed_departures_only' => false, 'meeting_point_required' => false, 'pickup_locations_only' => false,
            'min_travellers' => 1, 'max_travellers' => 7, 'traveller_limits_enabled' => true, 'is_featured' => false, 'sort_order' => 3,
            'meta_title' => 'Chocolate & Rhine Falls Private Day Trip from Zurich',
            'meta_description' => 'Visit Lindt Home of Chocolate, Rhine Falls and Zurich City on a private eight-hour tour from Zurich.',
        ], $content);
    }
}
