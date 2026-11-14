<?php

namespace Database\Seeders;

use App\Models\Trip;
use Illuminate\Database\Seeder;

/**
 * Draft content based on the supplied "4 Countries, 6 Incredible Stops" tour artwork.
 *
 * The image URLs are intentionally placeholders. Replace them in the Trip Content
 * editor when final photography is available; no media files are seeded here.
 */
final class FourCountriesDayTripSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Switzerland', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Switzerland'],
            ['name' => 'Germany', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Germany'],
            ['name' => 'Austria', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Austria'],
            ['name' => 'Liechtenstein', 'logo_url' => 'https://placehold.co/240x160/f8fafc/0f2740?text=Liechtenstein'],
        ];

        $stops = [
            [
                'city' => 'Zurich',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Zurich+Image',
                'description' => 'Begin your day in beautiful Zurich before setting out on an unforgettable cross-border journey.',
            ],
            [
                'city' => 'Lindau',
                'country' => 'Germany',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Lindau+Image',
                'description' => 'Visit the picturesque island town on Lake Constance, with its harbour, lighthouse and charming old town.',
            ],
            [
                'city' => 'Feldkirch',
                'country' => 'Austria',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Feldkirch+Image',
                'description' => 'Discover one of Austria’s oldest towns, its medieval lanes and the surrounding mountain scenery.',
            ],
            [
                'city' => 'Vaduz',
                'country' => 'Liechtenstein',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Vaduz+Image',
                'description' => 'Enjoy the capital of Liechtenstein, admire Vaduz Castle from outside and take in the peaceful Alpine atmosphere.',
            ],
            [
                'city' => 'Rapperswil',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Rapperswil+Image',
                'description' => 'Explore the City of Roses, its lakeside promenade, historic old town and castle views over Lake Zurich.',
            ],
            [
                'city' => 'Zurich',
                'country' => 'Switzerland',
                'image_url' => 'https://placehold.co/960x640/0f2740/ffffff?text=Replace+Zurich+Return+Image',
                'description' => 'Return to Zurich by 6:00 PM after a full day of scenic views, charming towns and four-country memories.',
            ],
        ];

        $itinerary = implode("\n\n", [
            '08:00 AM – Depart Zurich | Meet your professional chauffeur and begin your 10-hour private day tour.',
            '09:45 AM – Lindau, Germany | Enjoy time by Lake Constance, the harbour and the historic island town.',
            '11:30 AM – Feldkirch, Austria | Explore the medieval streets and admire the mountain backdrop.',
            '01:15 PM – Vaduz, Liechtenstein | See the principality’s capital and its iconic castle views.',
            '03:15 PM – Rapperswil, Switzerland | Stroll the lakeside promenade and discover the City of Roses.',
            '06:00 PM – Return to Zurich | Finish the day back in Zurich with lasting memories of four countries.',
        ]);

        $content = [
            'overview' => 'Experience four countries and six memorable stops in one remarkable day. This private Zurich round trip combines lakeside scenery, historic towns, Alpine views and the flexibility to enjoy each destination at your own pace.',
            'hero_image_path' => null,
            'hero_image_url' => 'https://placehold.co/1920x800/0f2740/ffffff?text=Replace+4+Countries+Tour+Hero+Image',
            'countries' => $countries,
            'stops' => collect($stops)->map(fn (array $stop) => collect($stop)->except('description')->all())->all(),
            'itinerary' => $itinerary,
            'comfort_car' => 'Mercedes-Benz V-Class 2026',
            'included_items' => implode("\n", [
                'Luxury Mercedes-Benz V-Class for up to 7 passengers',
                'Professional and friendly private chauffeur',
                'Comfortable, spacious, climate-controlled cabin',
                'Bottled water',
                'Photo stops at the best locations',
                'Flexible and personalised experience',
            ]),
            'not_included_items' => implode("\n", [
                'Meals and beverages beyond bottled water',
                'Entrance fees and optional activities',
                'Personal purchases and gratuities',
            ]),
        ];

        // firstOrCreate protects edits made later through the admin editor when db:seed is run again.
        Trip::query()->firstOrCreate(
            ['slug' => 'four-countries-six-incredible-stops'],
            [
                'title' => '4 Countries, 6 Incredible Stops: One Amazing Day',
                'subtitle' => 'A private 10-hour Zurich round trip through Switzerland, Germany, Austria and Liechtenstein.',
                'summary' => 'A full-day private journey from Zurich through four countries, with six scenic and historic stops before returning by 6:00 PM.',
                'description' => $this->description($content, $stops),
                'region' => 'Switzerland, Germany, Austria & Liechtenstein',
                'status' => 'draft',
                'pricing_mode' => 'enquiry',
                'fixed_departures_only' => false,
                'meeting_point_required' => false,
                'pickup_locations_only' => false,
                'min_travellers' => 1,
                'max_travellers' => 7,
                'traveller_limits_enabled' => true,
                'is_featured' => false,
                'sort_order' => 0,
                'meta_title' => '4 Countries, 6 Incredible Stops Private Day Tour from Zurich',
                'meta_description' => 'Explore Switzerland, Germany, Austria and Liechtenstein on a private 10-hour day trip from Zurich.',
                'content_data' => $content,
            ],
        );
    }

    /** @param array<string, mixed> $content @param list<array<string, string>> $stops */
    private function description(array $content, array $stops): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = '<section class="tour-hero-image-section"><img src="'.$escape($content['hero_image_url']).'" alt="4 Countries, 6 Incredible Stops: One Amazing Day" class="tour-hero-image"></section>';
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
