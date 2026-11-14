<?php

namespace App\Services\Trips;

use App\Models\Trip;
use DOMDocument;
use DOMNode;
use DOMXPath;

final class TripContentData
{
    /** Reload structured values, including content saved by the previous HTML-only editor. */
    public static function forTrip(Trip $trip): array
    {
        if (is_array($trip->content_data)) {
            return $trip->content_data;
        }

        if (! $trip->description) {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$trip->description, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $nodes = static fn (string $class, ?DOMNode $context = null) => $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]', $context ?? $document);
        $text = static fn (string $class, ?DOMNode $context = null) => trim($nodes($class, $context)->item(0)?->textContent ?? '');

        $data = [
            'overview' => $text('section-text', $nodes('tour-overview-section')->item(0)),
            'hero_image_path' => null,
            'hero_image_url' => $xpath->query('.//img/@src', $nodes('tour-hero-image-section')->item(0))->item(0)?->nodeValue,
            'countries' => [], 'stops' => [], 'itinerary' => '',
            'comfort_car' => $text('comfort-car'),
            'included_items' => '', 'not_included_items' => '',
        ];
        foreach ($nodes('country-pill') as $country) {
            $data['countries'][] = ['name' => trim($country->textContent), 'logo_url' => $xpath->query('.//img/@src', $country)->item(0)?->nodeValue];
        }
        foreach ($nodes('stop-card') as $stop) {
            $image = $xpath->query('.//img/@src', $stop)->item(0)?->nodeValue;
            $data['stops'][] = ['city' => $text('stop-city', $stop), 'country' => $text('stop-country', $stop), 'image_url' => $image === '[INSERT_IMAGE_URL]' ? null : $image];
        }
        $lines = [];
        foreach ($nodes('itinerary-item') as $item) {
            $description = $text('itinerary-desc', $item);
            $lines[] = $text('itinerary-time', $item).($description !== '' ? ' | '.$description : '');
        }
        $data['itinerary'] = implode("\n", $lines);
        foreach (['included_items' => 'included-list', 'not_included_items' => 'not-included-list'] as $field => $class) {
            $lines = [];
            foreach ($nodes($class) as $list) {
                foreach ($xpath->query('.//li', $list) as $item) {
                    $lines[] = trim($item->textContent);
                }
            }
            $data[$field] = implode("\n", $lines);
        }

        return $data;
    }
}
