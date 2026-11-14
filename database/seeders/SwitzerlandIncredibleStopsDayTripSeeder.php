<?php

namespace Database\Seeders;

use App\Models\Trip;
use Illuminate\Database\Seeder;

/**
 * Draft content based on the supplied "Switzerland, 6 Incredible Stops" tour artwork.
 *
 * The image URLs are intentionally placeholders. Replace them in the Trip Content
 * editor when final photography is available; no media files are seeded here.
 */
final class SwitzerlandIncredibleStopsDayTripSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Switzerland', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Switzerland'],
        ];

        $stops = [
            [
                'city' => 'Lake Lungern',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Lake+Lungern+Image',
                'description' => 'Pause beside the turquoise alpine waters of Lake Lungern for photos and a peaceful walk surrounded by mountain scenery.',
            ],
            [
                'city' => 'Grindelwald',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Grindelwald+Image',
                'description' => 'Discover the charming alpine village of Grindelwald beneath the dramatic Eiger north face.',
            ],
            [
                'city' => 'Lauterbrunnen',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Lauterbrunnen+Image',
                'description' => 'Take in the Valley of 72 Waterfalls, a spectacular valley framed by steep cliffs and cascading falls.',
            ],
            [
                'city' => 'Interlaken',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Interlaken+Image',
                'description' => 'Enjoy free time in Interlaken, perfectly placed between Lake Thun and Lake Brienz.',
            ],
            [
                'city' => 'Iseltwald',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Iseltwald+Image',
                'description' => 'Visit the famous lakeside village on Lake Brienz, known for its clear water and postcard-perfect views.',
            ],
            [
                'city' => 'Zurich',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Zurich+Return+Image',
                'description' => 'Return to Zurich by 6:00 PM after a full day of unforgettable Swiss landscapes and village discoveries.',
            ],
        ];

        $itinerary = implode("\n\n", [
            '08:00 AM – Depart Zurich | Meet your professional chauffeur and set off for a 10-hour private Swiss discovery tour.',
            '09:30 AM – Lake Lungern | Photograph the turquoise lake and enjoy the Alpine panorama.',
            '11:00 AM – Grindelwald | Explore the village beneath the Eiger and take in the mountain views.',
            '12:30 PM – Lauterbrunnen | Visit the beautiful valley of waterfalls and its dramatic cliffs.',
            '02:30 PM – Interlaken | Enjoy free time between Lake Thun and Lake Brienz.',
            '04:00 PM – Iseltwald | Stop in the scenic lakeside village on Lake Brienz before returning to Zurich.',
            '06:00 PM – Return to Zurich | Finish the day in Zurich after an unforgettable Swiss journey.',
        ]);

        $content = [
            'overview' => 'Discover Switzerland’s most memorable Alpine landscapes in one private day from Zurich. From the turquoise waters of Lake Lungern to the waterfalls of Lauterbrunnen and the shores of Lake Brienz, this flexible 10-hour journey combines scenic photo stops, charming villages and free time to enjoy the views.',
            'hero_image_path' => null,
            'hero_image_url' => 'https://placehold.co/1920x800/0f2740/ffffff?text=Replace+Swiss+Alps+Tour+Hero+Image',
            'countries' => $countries,
            'stops' => collect($stops)->map(fn (array $stop) => collect($stop)->except('description')->all())->all(),
            'itinerary' => $itinerary,
            'comfort_car' => 'Mercedes-Benz V-Class 2026',
            'included_items' => implode("\n", [
                'Luxury Mercedes-Benz V-Class for up to 7 passengers',
                'Professional private driver',
                'Bottled water',
                'Photo stops at the best locations',
                'Flexible private experience',
            ]),
            'not_included_items' => implode("\n", [
                'Meals and beverages beyond bottled water',
                'Entrance fees and optional activities',
                'Personal purchases and gratuities',
            ]),
        ];

        // firstOrCreate protects edits made later through the admin editor when db:seed is run again.
        Trip::query()->firstOrCreate(
            ['slug' => 'switzerland-six-incredible-stops'],
            [
                'title' => 'Switzerland: 6 Incredible Stops, One Amazing Day',
                'subtitle' => 'A private 10-hour Zurich round trip through Switzerland’s most beautiful Alpine landscapes.',
                'summary' => 'A full-day private Swiss journey from Zurich to Lake Lungern, Grindelwald, Lauterbrunnen, Interlaken and Iseltwald, returning by 6:00 PM.',
                'description' => $this->description($content, $stops),
                'region' => 'Central Switzerland and Bernese Oberland',
                'status' => 'draft',
                'pricing_mode' => 'enquiry',
                'fixed_departures_only' => false,
                'meeting_point_required' => false,
                'pickup_locations_only' => false,
                'min_travellers' => 1,
                'max_travellers' => 7,
                'traveller_limits_enabled' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'meta_title' => 'Switzerland 6 Incredible Stops Private Day Tour from Zurich',
                'meta_description' => 'Visit Lake Lungern, Grindelwald, Lauterbrunnen, Interlaken and Iseltwald on a private day trip from Zurich.',
                'content_data' => $content,
            ],
        );
    }

    /** @param array<string, mixed> $content @param list<array<string, string>> $stops */
    private function description(array $content, array $stops): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = '<section class="tour-hero-image-section"><img src="'.$escape($content['hero_image_url']).'" alt="Switzerland: 6 Incredible Stops, One Amazing Day" class="tour-hero-image"></section>';
        $html .= '<section class="tour-overview-section"><h2 class="section-title">Tour Overview</h2><p class="section-text">'.$escape($content['overview']).'</p></section>';
        $html .= '<section class="tour-countries-section"><h2 class="section-title">Countries Covered</h2><ul class="country-flags-list">';
        foreach ($content['countries'] as $country) {
            $html .= '<li class="country-pill"><img src="'.$escape($country['logo_url']).'" alt="'.$escape($country['name']).' Flag" class="flag-icon"><span>'.$escape($country['name']).'</span></li>';
        }
        $html .= '</ul></section><section class="tour-stops-section"><h2 class="section-title">The 6 Incredible Stops</h2><div class="stops-grid">';
        foreach ($stops as $index => $stop) {
            $html .= '<article class="stop-card"><span class="stop-number">'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'</span><img src="'.$escape($stop['image_url']).'" alt="'.$escape($stop['city']).'" class="stop-image"><h4 class="stop-city">'.$escape($stop['city']).'</h4><p class="stop-country">'.$escape($stop['country']).'</p></article>';
        }
        $html .= '</div></section><section class="tour-itinerary-section"><h2 class="section-title">Your Tour Itinerary</h2><ul class="itinerary-timeline">';
        foreach (preg_split('/\n\s*\n/', $content['itinerary']) ?: [] as $item) {
            [$title, $details] = array_pad(explode('|', $item, 2), 2, '');
            $html .= '<li class="itinerary-item"><strong class="itinerary-time">'.$escape(trim($title)).'</strong><p class="itinerary-desc">'.$escape(trim($details)).'</p></li>';
        }
        $html .= '</ul></section><section class="tour-comfort-section"><h2 class="section-title">Your Travel Comfort</h2><h3 class="comfort-car">'.$escape($content['comfort_car']).'</h3><ul class="comfort-features"><li>Up to 7 Passengers</li><li>Spacious &amp; Luxurious Interior</li><li>Climate Control</li><li>Professional Chauffeur</li></ul></section>';
        $html .= '<section class="tour-inclusions-section"><article class="included-list"><h4 class="included-title">What\'s Included</h4><ul>'.$this->listItems($content['included_items'], $escape).'</ul></article><article class="not-included-list"><h4 class="not-included-title">Not Included</h4><ul>'.$this->listItems($content['not_included_items'], $escape).'</ul></article></section>';

        return $html;
    }

    /** @param callable(string): string $escape */
    private function listItems(string $items, callable $escape): string
    {
        return collect(explode("\n", $items))
            ->filter(fn (string $item) => trim($item) !== '')
            ->map(fn (string $item) => '<li>'.$escape(trim($item)).'</li>')
            ->implode('');
    }
}
