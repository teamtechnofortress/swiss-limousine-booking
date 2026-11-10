<?php

namespace Tests\Feature\Public;

use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_trip_enquiry_is_linked_using_the_server_side_trip_record(): void
    {
        $trip = Trip::query()->create([
            'slug' => 'zurich-lakeside',
            'title' => 'Zurich Lakeside Day Trip',
            'summary' => 'A private Zurich day trip.',
            'region' => 'Zurich',
            'status' => 'published',
            'pricing_mode' => 'enquiry',
            'max_travellers' => 8,
        ]);

        $response = $this->postJson('/api/public/enquiries', [
            'type' => 'trip',
            'trip_slug' => $trip->slug,
            'name' => 'Test Customer',
            'email' => 'customer@example.test',
            'passengers' => 4,
            'message' => 'Please send a quote.',
            // This is deliberately ignored. The backend uses the official trip title.
            'subject_title' => 'Changed in browser',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.message', 'Your enquiry has been received.');

        $this->assertDatabaseHas('enquiries', [
            'trip_id' => $trip->id,
            'type' => 'trip',
            'subject_title' => 'Zurich Lakeside Day Trip',
            'customer_email' => 'customer@example.test',
            'passengers' => 4,
        ]);
    }
}
