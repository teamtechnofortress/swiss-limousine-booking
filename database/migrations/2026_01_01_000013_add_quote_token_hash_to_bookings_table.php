<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable keeps this migration safe for existing/manual bookings.
            $table->char('quote_token_hash', 64)->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['quote_token_hash']);
            $table->dropColumn('quote_token_hash');
        });
    }
};
