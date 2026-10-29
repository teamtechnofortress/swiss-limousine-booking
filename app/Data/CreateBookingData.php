<?php

namespace App\Data;

use App\Enums\PaymentMethod;

final readonly class CreateBookingData
{
    public function __construct(
        public string $quoteToken,
        public int $vehicleId,
        public PaymentMethod $paymentMethod,
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public ?string $flightNumber,
    ) {}
}
