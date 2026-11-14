<?php

namespace Tests\Feature\Admin;

use App\Models\Trip;
use App\Models\User;
use App\Services\Rbac\RbacSynchronizer;
use App\Services\Trips\TripContentData;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\MigrationStarted;
use Tests\TestCase;

final class TripContentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // An older fleet migration drops this indexed column without dropping its
        // index. SQLite needs the index removed first; keep this test-only.
        Event::listen(MigrationStarted::class, function (MigrationStarted $event): void {
            if (DB::getDriverName() === 'sqlite' && str_ends_with((new \ReflectionClass($event->migration))->getFileName(), '2026_10_29_110000_create_media_table_and_remove_fleet_unit_status.php')) {
                DB::statement('DROP INDEX IF EXISTS fleet_units_is_active_index');
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(RbacSynchronizer::class)->sync();
        $this->seed(SettingsSeeder::class);
        $user = User::create(['name' => 'Content Manager', 'email' => 'content-manager@example.test', 'password' => bcrypt('test-password'), 'is_active' => true]);
        $user->assignRole('manager');
        $this->actingAs($user);
        Storage::fake('public');
    }

    private function trip(): Trip
    {
        return Trip::create(['slug' => 'content-test', 'title' => 'Content Test', 'summary' => 'A private tour.', 'region' => 'Zurich', 'status' => 'draft', 'pricing_mode' => 'distance', 'min_travellers' => 1, 'max_travellers' => 8]);
    }

    public function test_content_reloads_and_uploaded_images_survive_another_save(): void
    {
        $trip = $this->trip();
        $fields = [
            'editor_step' => 'content', 'overview' => 'Explore the mountains.',
            'countries' => ['Switzerland', 'Austria'],
            'stops' => [['city' => 'Zurich', 'country' => 'Switzerland'], ['city' => 'Innsbruck', 'country' => 'Austria']],
            'itinerary' => "08:00 - Pickup | Meet your driver\n10:00 - Arrival | Explore",
            'comfort_car' => 'Mercedes V-Class', 'included_items' => "Driver\nWater", 'not_included_items' => 'Meals',
        ];
        $uploadFields = $fields;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a/mkAAAAASUVORK5CYII=');
        $uploadFields['country_logos'][1] = UploadedFile::fake()->createWithContent('flag.png', $png);
        $uploadFields['stops'][1]['image'] = UploadedFile::fake()->createWithContent('stop.png', $png);
        $this->put(route('admin.trips.content', $trip), $uploadFields)->assertSessionHasNoErrors()->assertRedirect(route('admin.trips.edit', $trip).'#content');
        $data = $trip->fresh()->content_data;
        $this->assertSame($fields['overview'], $data['overview']);
        $this->assertSame($fields['itinerary'], $data['itinerary']);
        Storage::disk('public')->assertExists($data['countries'][1]['logo_path']);
        Storage::disk('public')->assertExists($data['stops'][1]['image_path']);
        $this->get(route('admin.trips.edit', $trip))->assertOk()
            ->assertSee('Explore the mountains.')->assertSee('value="Mercedes V-Class"', false)
            ->assertSee('value="Innsbruck"', false)->assertSee($data['stops'][1]['image_url'], false);

        $fields['country_existing'] = [0, 1];
        $fields['stops'][0]['existing_index'] = 0;
        $fields['stops'][1]['existing_index'] = 1;
        $this->put(route('admin.trips.content', $trip), $fields)->assertSessionHasNoErrors();
        $this->assertSame($data, $trip->fresh()->content_data);
        $this->assertStringContainsString($data['stops'][1]['image_url'], $trip->fresh()->description);

        // Removing the first row must keep the second row's image attached correctly.
        unset($fields['countries'][0], $fields['country_existing'][0], $fields['stops'][0]);
        $this->put(route('admin.trips.content', $trip), $fields)->assertSessionHasNoErrors();
        $remaining = $trip->fresh()->content_data;
        $this->assertCount(1, $remaining['stops']);
        $this->assertSame('Innsbruck', $remaining['stops'][0]['city']);
        $this->assertSame($data['stops'][1]['image_path'], $remaining['stops'][0]['image_path']);
        $this->assertSame($data['countries'][1]['logo_path'], $remaining['countries'][0]['logo_path']);
    }

    public function test_legacy_generated_html_can_be_reopened_without_losing_its_fields(): void
    {
        $trip = $this->trip();
        $trip->update(['description' => '<section class="tour-overview-section"><p class="section-text">A scenic tour.</p></section><li class="country-pill"><span>Switzerland</span></li><article class="stop-card"><img src="/storage/stops/old.jpg"><h4 class="stop-city">Lucerne</h4><p class="stop-country">Switzerland</p></article><li class="itinerary-item"><strong class="itinerary-time">09:00 - Depart</strong><p class="itinerary-desc">Meet driver</p></li><h3 class="comfort-car">V-Class</h3><article class="included-list"><ul><li>Water</li></ul></article>']);
        $data = TripContentData::forTrip($trip->fresh());
        $this->assertSame('A scenic tour.', $data['overview']);
        $this->assertSame('Switzerland', $data['countries'][0]['name']);
        $this->assertSame('/storage/stops/old.jpg', $data['stops'][0]['image_url']);
        $this->assertSame('09:00 - Depart | Meet driver', $data['itinerary']);
        $this->assertSame('V-Class', $data['comfort_car']);
        $this->assertSame('Water', $data['included_items']);
        $this->get(route('admin.trips.edit', $trip))->assertOk()->assertSee('value="Lucerne"', false);
    }

    public function test_invalid_upload_does_not_overwrite_saved_content(): void
    {
        $trip = $this->trip();
        $trip->update(['content_data' => ['overview' => 'Keep this'], 'description' => '<p>Keep this</p>']);
        $this->from(route('admin.trips.edit', $trip))->put(route('admin.trips.content', $trip), [
            'overview' => 'Changed', 'countries' => ['Switzerland'],
            'country_logos' => [UploadedFile::fake()->create('invalid.txt', 1, 'text/plain')],
        ])->assertSessionHasErrors('country_logos.0');
        $this->assertSame('Keep this', $trip->fresh()->content_data['overview']);
    }
}
