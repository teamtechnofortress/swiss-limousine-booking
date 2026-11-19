<?php

namespace Tests\Feature\Payments;

use App\Data\CheckoutSession;
use App\Data\GatewayBalanceTransaction;
use App\Data\GatewayPaymentLedger;
use App\Data\GatewayRefund;
use App\Data\GatewayRefundLedger;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Gateways\Payments\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\Vehicle;
use App\Services\Payments\PaymentEventLogger;
use App\Services\Payments\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderLedgerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_the_actual_payment_fee_and_net_from_the_provider_balance_transaction(): void
    {
        $payment = $this->onlinePayment();
        $balanceTransaction = new GatewayBalanceTransaction(
            id: 'txn_charge_123', amount: 15000, currency: 'CHF', feeAmount: 485,
            feeDetails: [['amount' => 485, 'currency' => 'chf', 'type' => 'stripe_fee']],
            netAmount: 14515, exchangeRate: null,
        );

        $service = $this->service(new GatewayPaymentLedger('ch_123', $balanceTransaction), null);

        $this->assertTrue($service->syncOnlinePaymentLedger($payment->id));

        $payment->refresh();
        $this->assertSame('ch_123', $payment->provider_charge_reference);
        $this->assertSame('txn_charge_123', $payment->provider_balance_transaction_reference);
        $this->assertSame(15000, $payment->provider_balance_amount);
        $this->assertSame('CHF', $payment->provider_balance_currency);
        $this->assertSame(485, $payment->provider_fee_amount);
        $this->assertSame(14515, $payment->provider_net_amount);
        $this->assertSame([['amount' => 485, 'currency' => 'chf', 'type' => 'stripe_fee']], $payment->provider_fee_details);
        $this->assertNotNull($payment->provider_ledger_synced_at);
    }

    public function test_it_saves_refund_settlement_separately_from_the_customer_refund_amount(): void
    {
        $payment = $this->onlinePayment();
        $refund = Refund::query()->create([
            'payment_id' => $payment->id,
            'gross_amount' => 14515,
            'vat_amount' => 1075,
            'provider_reference' => 're_123',
            'status' => RefundStatus::Succeeded,
            'reason' => 'Cancellation policy',
        ]);
        $balanceTransaction = new GatewayBalanceTransaction(
            id: 'txn_refund_123', amount: -14515, currency: 'CHF', feeAmount: 0,
            feeDetails: [], netAmount: -14515, exchangeRate: null,
        );

        $service = $this->service(null, new GatewayRefundLedger($balanceTransaction));

        $this->assertTrue($service->syncOnlineRefundLedger($refund->id));

        $refund->refresh();
        $this->assertSame(14515, $refund->gross_amount);
        $this->assertSame('txn_refund_123', $refund->provider_balance_transaction_reference);
        $this->assertSame(-14515, $refund->provider_balance_amount);
        $this->assertSame(0, $refund->provider_fee_amount);
        $this->assertSame(-14515, $refund->provider_net_amount);
        $this->assertNotNull($refund->provider_ledger_synced_at);
    }

    private function service(?GatewayPaymentLedger $paymentLedger, ?GatewayRefundLedger $refundLedger): PaymentService
    {
        $gateway = new class($paymentLedger, $refundLedger) implements PaymentGateway
        {
            public function __construct(
                private readonly ?GatewayPaymentLedger $paymentLedger,
                private readonly ?GatewayRefundLedger $refundLedger,
            ) {}

            public function name(): string
            {
                return 'stripe';
            }

            public function createCheckout(Booking $booking, PaymentAttempt $attempt, CarbonImmutable $expiresAt): CheckoutSession
            {
                throw new \LogicException('Not used by this test.');
            }

            public function retrieveCheckout(PaymentAttempt $attempt): CheckoutSession
            {
                throw new \LogicException('Not used by this test.');
            }

            public function refund(Payment $payment, Refund $refund): GatewayRefund
            {
                throw new \LogicException('Not used by this test.');
            }

            public function retrievePaymentLedger(Payment $payment): ?GatewayPaymentLedger
            {
                return $this->paymentLedger;
            }

            public function retrieveRefundLedger(Refund $refund): ?GatewayRefundLedger
            {
                return $this->refundLedger;
            }

            public function parseWebhook(string $payload, string $signature): array
            {
                throw new \LogicException('Not used by this test.');
            }
        };

        return new PaymentService($gateway, app(PaymentEventLogger::class));
    }

    private function onlinePayment(): Payment
    {
        $vehicle = Vehicle::query()->create([
            'name' => 'Test Sedan', 'slug' => 'test-sedan', 'category_id' => 1,
            'passenger_capacity' => 3, 'luggage_capacity' => 2, 'price_per_km' => 100,
            'minimum_fare' => 1000, 'slot_check_enabled' => false, 'is_active' => true,
        ]);
        $booking = Booking::query()->create([
            'reference' => 'LX-LEDGR', 'type' => 'transfer', 'status' => BookingStatus::Confirmed,
            'payment_method' => PaymentMethod::Online, 'vehicle_id' => $vehicle->id,
            'customer_name' => 'Ledger Test', 'customer_email' => 'ledger@example.test', 'customer_phone' => '123',
            'passengers' => 1, 'luggage' => 1, 'currency' => 'CHF', 'taxable_amount' => 13915,
            'vat_rate_bp' => 770, 'vat_amount' => 1085, 'total_amount' => 15000, 'vat_included' => true,
            'price_breakdown' => [], 'access_token_hash' => str_repeat('a', 64),
        ]);

        return Payment::query()->create([
            'booking_id' => $booking->id, 'method' => PaymentMethod::Online, 'provider' => 'stripe',
            'provider_reference' => 'cs_123', 'provider_payment_intent' => 'pi_123', 'gross_amount' => 15000,
            'vat_amount' => 1085, 'currency' => 'CHF', 'status' => PaymentStatus::Paid,
        ]);
    }
}
