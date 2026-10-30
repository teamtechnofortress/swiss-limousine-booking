<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table) {
                $table->id();
                $table->morphs('model');
                $table->uuid()->nullable()->unique();
                $table->string('collection_name');
                $table->string('name');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->string('disk');
                $table->string('conversions_disk')->nullable();
                $table->unsignedBigInteger('size');
                $table->json('manipulations');
                $table->json('custom_properties');
                $table->json('generated_conversions');
                $table->json('responsive_images');
                $table->unsignedInteger('order_column')->nullable()->index();
                $table->nullableTimestamps();
            });
        }

        if (Schema::hasColumn('fleet_units', 'is_active')) {
            Schema::table('fleet_units', fn (Blueprint $table) => $table->dropColumn('is_active'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('media')) {
            Schema::drop('media');
        }

        if (! Schema::hasColumn('fleet_units', 'is_active')) {
            Schema::table('fleet_units', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->index()->after('plate'));
        }
    }
};
