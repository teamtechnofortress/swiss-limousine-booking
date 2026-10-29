<?php

namespace Tests\Unit\Booking;

use App\Services\Pricing\VatCalculator;
use App\Services\Settings\SettingsService;
use Tests\TestCase;

final class VatCalculatorTest extends TestCase
{
    public function test_vat_included_splits_gross_into_net_and_vat(): void
    {
        $split = (new VatCalculator(new SettingsService()))->calculate(39900, 810, true);

        $this->assertSame(36909, $split->net);
        $this->assertSame(2991, $split->vat);
        $this->assertSame(39900, $split->gross);
    }

    public function test_vat_added_on_top_when_prices_are_net(): void
    {
        $split = (new VatCalculator(new SettingsService()))->calculate(10000, 810, false);

        $this->assertSame(10000, $split->net);
        $this->assertSame(810, $split->vat);
        $this->assertSame(10810, $split->gross);
    }

    public function test_zero_rate_leaves_the_amount_unchanged(): void
    {
        $split = (new VatCalculator(new SettingsService()))->calculate(10000, 0, true);

        $this->assertSame(10000, $split->net);
        $this->assertSame(0, $split->vat);
        $this->assertSame(10000, $split->gross);
    }
}
