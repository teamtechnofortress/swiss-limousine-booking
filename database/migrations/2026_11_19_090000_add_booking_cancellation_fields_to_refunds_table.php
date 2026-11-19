<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->boolean('cancel_booking_on_success')->default(false)->after('reason');
            $table->dateTime('booking_cancelled_at')->nullable()->after('cancel_booking_on_success');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropColumn(['cancel_booking_on_success', 'booking_cancelled_at']);
        });
    }
};
