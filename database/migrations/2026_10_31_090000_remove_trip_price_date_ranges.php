<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_prices', function (Blueprint $table): void {
            // The old composite index is currently also MySQL's supporting index for trip_id.
            // Keep that foreign key valid before the date-range index is removed.
            $table->index('trip_id');
            $table->dropIndex(['trip_id', 'vehicle_id', 'valid_from', 'valid_to']);
            $table->dropColumn(['valid_from', 'valid_to']);
            $table->unique(['trip_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trip_prices', function (Blueprint $table): void {
            $table->dropUnique(['trip_id', 'vehicle_id']);
            $table->date('valid_from')->nullable()->after('price_minor');
            $table->date('valid_to')->nullable()->after('valid_from');
            $table->index(['trip_id', 'vehicle_id', 'valid_from', 'valid_to']);
        });
    }
};
