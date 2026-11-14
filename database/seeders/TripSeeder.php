<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Seeds every editable draft trip supplied from the tour artwork. */
final class TripSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FourCountriesDayTripSeeder::class,
            SwitzerlandIncredibleStopsDayTripSeeder::class,
            LucerneTitlisDayTripSeeder::class,
            ChocolateWaterfallDayTripSeeder::class,
            FourCountriesFiveStopsDayTripSeeder::class,
        ]);
    }
}
