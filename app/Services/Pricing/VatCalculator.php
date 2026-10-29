<?php

namespace App\Services\Pricing;

use App\Data\VatBreakdown;
use App\Services\Settings\SettingsService;

/** All VAT maths lives here. Amounts are integer rappen. Rates are basis points (810 = 8.10 percent). */
final class VatCalculator
{
    public function __construct(private readonly SettingsService $settings) {}

    /** Uses the current VAT settings. Used when a quote is created. */
    public function split(int $amount): VatBreakdown
    {
        $rateBp = $this->settings->bool('vat_enabled') ? $this->settings->int('vat_rate_bp') : 0;

        return $this->calculate($amount, $rateBp, $this->settings->bool('vat_included'));
    }

    /** Pure calculation. Stored bookings, waiting charges and refunds keep their own rate. */
    public function calculate(int $amount, int $rateBp, bool $included): VatBreakdown
    {
        if ($rateBp === 0) {
            return new VatBreakdown($amount, 0, $amount, 0, $included);
        }

        if ($included) {
            $net = (int) round($amount * 10000 / (10000 + $rateBp));

            return new VatBreakdown($net, $amount - $net, $amount, $rateBp, true);
        }

        $vat = (int) round($amount * $rateBp / 10000);

        return new VatBreakdown($amount, $vat, $amount + $vat, $rateBp, false);
    }
}
