<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\TripContentSeeder;

/** Draft content based on the supplied four-country, five-destination-stop tour artwork. */
final class FourCountriesFiveStopsDayTripSeeder extends TripContentSeeder
{
    public function run(): void
    {
        $content = [
            'overview' => 'Travel through Switzerland, Germany, Austria and Liechtenstein in one private day. This 10-hour Zurich round trip combines lakeside scenery, historic towns, Alpine views and five memorable destination stops.',
            'hero_image_path' => null,
            'hero_image_url' => 'https://placehold.co/1920x800/0f2740/ffffff?text=Replace+4+Countries+Tour+Hero+Image',
            'countries' => [
                ['name' => 'Switzerland', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Switzerland'],
                ['name' => 'Germany', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Germany'],
                ['name' => 'Austria', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Austria'],
                ['name' => 'Liechtenstein', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Liechtenstein'],
            ],
            'stops' => [
                ['city' => 'Zurich', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Zurich+Image'],
                ['city' => 'Lindau', 'country' => 'Germany', 'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Lindau+Image'],
                ['city' => 'Feldkirch', 'country' => 'Austria', 'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Feldkirch+Image'],
                ['city' => 'Vaduz', 'country' => 'Liechtenstein', 'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Vaduz+Image'],
                ['city' => 'Rapperswil', 'country' => 'Switzerland', 'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Rapperswil+Image'],
            ],
            'itinerary' => implode("\n\n", [
                '08:00 AM – Depart Zurich | Meet your professional chauffeur and begin your 10-hour private four-country journey.',
                '09:45 AM – Lindau, Germany | Enjoy Lake Constance, the harbour, lighthouse and charming island old town.',
                '11:30 AM – Feldkirch, Austria | Discover medieval streets and the surrounding mountain backdrop.',
                '01:15 PM – Vaduz, Liechtenstein | See the principality’s capital and enjoy views of the iconic castle.',
                '03:15 PM – Rapperswil, Switzerland | Explore the City of Roses, its lakeside promenade and old town.',
                '06:00 PM – Return to Zurich | Finish the day in Zurich with lasting four-country memories.',
            ]),
            'comfort_car' => 'Mercedes-Benz V-Class 2026',
            'included_items' => implode("\n", ['Luxury Mercedes-Benz V-Class for up to 7 passengers', 'Professional and friendly private chauffeur', 'Comfortable and spacious cabin', 'Bottled water', 'Photo stops at the best locations', 'Flexible and personalised experience']),
            'not_included_items' => implode("\n", ['Meals and beverages beyond bottled water', 'Entrance fees and optional activities', 'Personal purchases and gratuities']),
        ];

        $this->seedTrip('four-countries-five-incredible-stops', [
            'title' => '4 Countries: 5 Incredible Stops, One Amazing Day',
            'subtitle' => 'A private 10-hour Zurich round trip through Switzerland, Germany, Austria and Liechtenstein.',
            'summary' => 'Travel through four countries and five destination stops on a private full-day journey from Zurich, returning by 6:00 PM.',
            'region' => 'Switzerland, Germany, Austria & Liechtenstein',
            'status' => 'draft', 'pricing_mode' => 'enquiry', 'fixed_departures_only' => false, 'meeting_point_required' => false, 'pickup_locations_only' => false,
            'min_travellers' => 1, 'max_travellers' => 7, 'traveller_limits_enabled' => true, 'is_featured' => false, 'sort_order' => 4,
            'meta_title' => '4 Countries, 5 Stops Private Day Tour from Zurich',
            'meta_description' => 'Explore Switzerland, Germany, Austria and Liechtenstein on a private 10-hour day trip from Zurich.',
        ], $content);
    }
}
