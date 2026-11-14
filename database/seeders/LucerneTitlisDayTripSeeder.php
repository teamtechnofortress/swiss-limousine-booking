<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\TripContentSeeder;

/** Draft content based on the supplied Lucerne, Engelberg and Titlis tour artwork. */
final class LucerneTitlisDayTripSeeder extends TripContentSeeder
{
    public function run(): void
    {
        $content = [
            'overview' => 'A private 10-hour Alpine day from Zurich, combining Lucerne’s historic old town with the monastery village of Engelberg, the Titlis glacier experience and the turquoise Trübsee Lake.',
            'hero_image_path' => null,
            'hero_image_url' => 'https://placehold.co/1920x800/35176f/ffffff?text=Replace+Lucerne+Titlis+Tour+Hero+Image',
            'countries' => [
                ['name' => 'Switzerland', 'logo_url' => 'https://placehold.co/240x160/f8fafc/35176f?text=Switzerland'],
            ],
            'stops' => [
                ['city' => 'Lucerne', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/35176f/ffffff?text=Replace+Lucerne+Image'],
                ['city' => 'Engelberg', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/35176f/ffffff?text=Replace+Engelberg+Image'],
                ['city' => 'Titlis', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/35176f/ffffff?text=Replace+Titlis+Image'],
                ['city' => 'Trübsee Lake', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/35176f/ffffff?text=Replace+Trubsee+Lake+Image'],
                ['city' => 'Zurich', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/35176f/ffffff?text=Replace+Zurich+Return+Image'],
            ],
            'itinerary' => implode("\n\n", [
                '08:00 AM – Depart Zurich | Meet your private chauffeur and travel towards Lucerne.',
                '09:15 AM – Lucerne | Walk across Chapel Bridge and explore the charming Old Town.',
                '10:45 AM – Engelberg | Visit the peaceful monastery village beneath the mountains.',
                '12:00 PM – Titlis | Ride the Rotair cable car to the glacier and enjoy the Cliff Walk (weather and operating conditions permitting).',
                '02:30 PM – Trübsee Lake | Relax beside the scenic turquoise alpine lake before beginning the return journey.',
                '06:00 PM – Return to Zurich | Arrive back in Zurich after a memorable Alpine day.',
            ]),
            'comfort_car' => 'Mercedes-Benz V-Class 2026',
            'included_items' => implode("\n", ['Luxury Mercedes-Benz V-Class for up to 7 passengers', 'Professional private driver', 'Bottled water', 'Photo stops at the best locations', 'Flexible private experience']),
            'not_included_items' => implode("\n", ['Titlis cable-car tickets and other entrance fees', 'Meals and beverages beyond bottled water', 'Personal purchases and gratuities']),
        ];

        $this->seedTrip('lucerne-engelberg-titlis-day-trip', [
            'title' => 'Switzerland: 5 Incredible Stops – Lucerne, Engelberg & Titlis',
            'subtitle' => 'A private 10-hour Zurich round trip through Lucerne and the Swiss Alps.',
            'summary' => 'Discover Lucerne, Engelberg, Titlis and Trübsee Lake on a private full-day journey from Zurich, returning by 6:00 PM.',
            'region' => 'Central Switzerland',
            'status' => 'draft', 'pricing_mode' => 'enquiry', 'fixed_departures_only' => false, 'meeting_point_required' => false, 'pickup_locations_only' => false,
            'min_travellers' => 1, 'max_travellers' => 7, 'traveller_limits_enabled' => true, 'is_featured' => false, 'sort_order' => 2,
            'meta_title' => 'Lucerne, Engelberg & Titlis Private Day Trip from Zurich',
            'meta_description' => 'Explore Lucerne, Engelberg, Titlis and Trübsee Lake on a private 10-hour day tour from Zurich.',
        ], $content);
    }
}
