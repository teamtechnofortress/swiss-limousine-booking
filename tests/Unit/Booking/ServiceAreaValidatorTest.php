<?php

namespace Tests\Unit\Booking;

use App\Services\Geo\ServiceAreaValidator;
use App\Services\Settings\SettingsService;
use Tests\TestCase;

final class ServiceAreaValidatorTest extends TestCase
{
    public function test_distance_between_zurich_and_winterthur_is_about_twenty_kilometres(): void
    {
        $validator = new ServiceAreaValidator(new SettingsService());

        $metres = $validator->distanceMeters(47.3769, 8.5417, 47.5000, 8.7240);

        $this->assertGreaterThan(19000, $metres);
        $this->assertLessThan(20000, $metres);
    }

    public function test_distance_to_the_same_point_is_zero(): void
    {
        $validator = new ServiceAreaValidator(new SettingsService());

        $this->assertEqualsWithDelta(0.0, $validator->distanceMeters(47.0, 8.0, 47.0, 8.0), 0.001);
    }
}
