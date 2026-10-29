<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->string('method', 30);
            $table->string('provider', 20)->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('provider_payment_intent')->nullable()->index();
            $table->unsignedInteger('gross_amount');
            $table->unsignedInteger('vat_amount');
            $table->char('currency', 3);
            $table->string('status', 20)->default('unpaid')->index();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
