<?php

namespace Tests\Feature\Database;

use App\Models\Trip;
use Database\Seeders\FourCountriesDayTripSeeder;
use Database\Seeders\SwitzerlandIncredibleStopsDayTripSeeder;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class FourCountriesDayTripSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // SQLite requires an indexed column's index to be removed before that column is dropped.
        Event::listen(MigrationStarted::class, function (MigrationStarted $event): void {
            $migration = new \ReflectionClass($event->migration);

            if (DB::getDriverName() === 'sqlite' && str_ends_with($migration->getFileName(), '2026_10_29_110000_create_media_table_and_remove_fleet_unit_status.php')) {
                DB::statement('DROP INDEX IF EXISTS fleet_units_is_active_index');
            }
        });
    }

    public function test_it_creates_the_draft_four_countries_day_trip_with_structured_content(): void
    {
        $this->seed(FourCountriesDayTripSeeder::class);

        $trip = Trip::query()->where('slug', 'four-countries-six-incredible-stops')->firstOrFail();

        $this->assertSame('draft', $trip->status);
        $this->assertSame('enquiry', $trip->pricing_mode);
        $this->assertSame(7, $trip->max_travellers);
        $this->assertCount(4, $trip->content_data['countries']);
        $this->assertCount(6, $trip->content_data['stops']);
        $this->assertSame('Zurich', $trip->content_data['stops'][0]['city']);
        $this->assertSame('Zurich', $trip->content_data['stops'][5]['city']);
        $this->assertStringContainsString('08:00 AM', $trip->content_data['itinerary']);
        $this->assertStringContainsString('https://placehold.co/', $trip->content_data['hero_image_url']);
        $this->assertStringContainsString('tour-stops-section', $trip->description);
    }

    public function test_reseeding_does_not_overwrite_later_admin_changes(): void
    {
        $this->seed(FourCountriesDayTripSeeder::class);

        $trip = Trip::query()->where('slug', 'four-countries-six-incredible-stops')->firstOrFail();
        $trip->update(['title' => 'Updated in the admin editor']);

        $this->seed(FourCountriesDayTripSeeder::class);

        $this->assertSame('Updated in the admin editor', $trip->fresh()->title);
        $this->assertSame(1, Trip::query()->where('slug', 'four-countries-six-incredible-stops')->count());
    }

    public function test_it_creates_the_draft_swiss_six_stops_trip_with_structured_content(): void
    {
        $this->seed(SwitzerlandIncredibleStopsDayTripSeeder::class);

        $trip = Trip::query()->where('slug', 'switzerland-six-incredible-stops')->firstOrFail();

        $this->assertSame('draft', $trip->status);
        $this->assertSame('enquiry', $trip->pricing_mode);
        $this->assertCount(1, $trip->content_data['countries']);
        $this->assertCount(6, $trip->content_data['stops']);
        $this->assertSame('Lake Lungern', $trip->content_data['stops'][0]['city']);
        $this->assertSame('Zurich', $trip->content_data['stops'][5]['city']);
        $this->assertStringContainsString('08:00 AM', $trip->content_data['itinerary']);
        $this->assertStringContainsString('tour-stops-section', $trip->description);
    }
}
