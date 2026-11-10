<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * Local development only. Sample values come from the competitor's public fleet page.
 * Replace them with your real vehicle catalogue through the admin panel before going live.
 */
final class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $vehicles = [
            ['slug' => 'mercedes-e-class', 'name' => 'Mercedes E-Class', 'model_name' => 'E 220d', 'category' => 'executive', 'passenger_capacity' => 3, 'luggage_capacity' => 3, 'price_per_km' => 350, 'minimum_fare' => 8000, 'amenities' => ['wifi', 'water'], 'sort_order' => 10],
            ['slug' => 'mercedes-s-class', 'name' => 'Mercedes S-Class', 'model_name' => 'S 350', 'category' => 'first_class', 'passenger_capacity' => 3, 'luggage_capacity' => 3, 'price_per_km' => 470, 'minimum_fare' => 10000, 'amenities' => ['wifi', 'water', 'champagne'], 'sort_order' => 20],
            ['slug' => 'mercedes-v-class', 'name' => 'Mercedes V-Class', 'model_name' => 'V 250', 'category' => 'van', 'passenger_capacity' => 7, 'luggage_capacity' => 7, 'price_per_km' => 380, 'minimum_fare' => 9000, 'amenities' => ['wifi', 'water'], 'sort_order' => 30],
            ['slug' => 'byd-seal-u', 'name' => 'BYD Seal U DM-i', 'model_name' => 'Seal U DM-i', 'category' => 'executive', 'passenger_capacity' => 4, 'luggage_capacity' => 4, 'price_per_km' => 300, 'minimum_fare' => 7000, 'amenities' => ['wifi'], 'sort_order' => 5],
            ['slug' => 'mercedes-sprinter', 'name' => 'Mercedes Sprinter', 'model_name' => 'Sprinter', 'category' => 'van', 'passenger_capacity' => 14, 'luggage_capacity' => 14, 'price_per_km' => null, 'minimum_fare' => null, 'amenities' => [], 'sort_order' => 40],
            ['slug' => 'mercedes-maybach', 'name' => 'Mercedes Maybach', 'model_name' => 'S 680', 'category' => 'first_class', 'passenger_capacity' => 2, 'luggage_capacity' => 2, 'price_per_km' => null, 'minimum_fare' => null, 'amenities' => [], 'sort_order' => 15],
        ];

        foreach ($vehicles as $data) {
            $category = VehicleCategory::query()->where('slug', $data['category'] === 'first_class' ? 'first-class' : $data['category'])->firstOrFail();
            unset($data['category']);

            Vehicle::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data + ['category_id' => $category->id, 'slot_check_enabled' => false, 'is_active' => true],
            );
        }
    }
}
