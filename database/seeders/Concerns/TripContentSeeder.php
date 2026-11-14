<?php

namespace Database\Seeders\Concerns;

use App\Models\Trip;
use Illuminate\Database\Seeder;

abstract class TripContentSeeder extends Seeder
{
    /** @param array<string, mixed> $attributes @param array<string, mixed> $content */
    protected function seedTrip(string $slug, array $attributes, array $content): void
    {
        Trip::query()->firstOrCreate(
            ['slug' => $slug],
            [
                ...$attributes,
                'description' => $this->description((string) $attributes['title'], $content),
                'content_data' => $content,
            ],
        );
    }

    /** @param array<string, mixed> $content */
    private function description(string $title, array $content): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $countries = $content['countries'];
        $stops = $content['stops'];
        $stopLabel = count($stops) === 1 ? 'The 1 Incredible Stop' : 'The '.count($stops).' Incredible Stops';

        $html = '<section class="tour-hero-image-section"><img src="'.$escape($content['hero_image_url']).'" alt="'.$escape($title).'" class="tour-hero-image"></section>';
        $html .= '<section class="tour-overview-section"><h2 class="section-title">Tour Overview</h2><p class="section-text">'.$escape($content['overview']).'</p></section>';
        $html .= '<section class="tour-countries-section"><h2 class="section-title">Countries Covered</h2><ul class="country-flags-list">';
        foreach ($countries as $country) {
            $html .= '<li class="country-pill"><img src="'.$escape($country['logo_url']).'" alt="'.$escape($country['name']).' Flag" class="flag-icon"><span>'.$escape($country['name']).'</span></li>';
        }
        $html .= '</ul></section><section class="tour-stops-section"><h2 class="section-title">'.$stopLabel.'</h2><div class="stops-grid">';
        foreach ($stops as $index => $stop) {
            $html .= '<article class="stop-card"><span class="stop-number">'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'</span><img src="'.$escape($stop['image_url']).'" alt="'.$escape($stop['city']).'" class="stop-image"><h4 class="stop-city">'.$escape($stop['city']).'</h4><p class="stop-country">'.$escape($stop['country']).'</p></article>';
        }
        $html .= '</div></section><section class="tour-itinerary-section"><h2 class="section-title">Your Tour Itinerary</h2><ul class="itinerary-timeline">';
        foreach (preg_split('/\n\s*\n/', $content['itinerary']) ?: [] as $item) {
            [$itineraryTitle, $details] = array_pad(explode('|', $item, 2), 2, '');
            $html .= '<li class="itinerary-item"><strong class="itinerary-time">'.$escape(trim($itineraryTitle)).'</strong><p class="itinerary-desc">'.$escape(trim($details)).'</p></li>';
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
