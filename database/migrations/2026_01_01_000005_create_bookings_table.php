<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->string('status', 30)->index();
            $table->string('source', 10)->default('website');
            $table->string('payment_method', 30);
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('fleet_unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();

            $table->string('customer_name', 120);
            $table->string('customer_email', 190)->index();
            $table->string('customer_phone', 30);
            $table->unsignedTinyInteger('passengers');
            $table->unsignedTinyInteger('luggage');
            $table->string('flight_number', 20)->nullable();

            $table->char('currency', 3);
            $table->unsignedInteger('taxable_amount');
            $table->unsignedInteger('vat_rate_bp');
            $table->unsignedInteger('vat_amount');
            $table->unsignedInteger('total_amount');
            $table->boolean('vat_included');
            $table->string('vat_registration_number', 30)->nullable();
            $table->json('price_breakdown');

            $table->char('access_token_hash', 64)->index();
            $table->char('verification_token_hash', 64)->nullable()->index();
            $table->dateTime('hold_expires_at')->nullable()->index();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('admin_seen_at')->nullable()->index();
            $table->dateTime('cancellation_requested_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
