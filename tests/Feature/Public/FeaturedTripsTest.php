<?php

namespace Tests\Feature\Public;

use App\Models\Trip;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FeaturedTripsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_it_lists_only_featured_published_trips_with_card_image_urls(): void
    {
        $featured = $this->publishedTrip(['slug' => 'featured-trip', 'is_featured' => true]);
        $this->publishedTrip(['slug' => 'standard-trip']);
        $this->publishedTrip(['slug' => 'draft-featured-trip', 'is_featured' => true, 'status' => 'draft']);

        $this->getJson('/api/public/trips/featured')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $featured->slug)
            ->assertJsonPath('data.0.cover_url', null)
            ->assertJsonPath('data.0.thumbnail_url', null)
            ->assertJsonPath('data.0.original_cover_url', null);
    }

    /** @param array<string, mixed> $overrides */
    private function publishedTrip(array $overrides = []): Trip
    {
        return Trip::query()->create($overrides + [
            'slug' => 'trip-'.str()->random(8),
            'title' => 'Swiss Day Trip',
            'summary' => 'A private tour of Switzerland.',
            'region' => 'Central Switzerland',
            'status' => 'published',
            'pricing_mode' => 'enquiry',
            'min_travellers' => 1,
            'max_travellers' => 8,
            'published_at' => now(),
        ]);
    }
}
