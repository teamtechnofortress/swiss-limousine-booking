<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->boolean('is_featured')->default(false)->after('longitude');
            $table->boolean('allowed_pickup')->default(false)->after('is_featured');
            $table->unsignedInteger('sort_order')->default(0)->after('allowed_pickup');
            $table->timestamp('details_fetched_at')->nullable()->after('is_active');
            $table->index(['is_active', 'allowed_pickup', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'allowed_pickup', 'sort_order']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'is_featured', 'allowed_pickup', 'sort_order', 'details_fetched_at']);
        });
    }
};
