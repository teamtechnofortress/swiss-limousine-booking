<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('summary', 300);
            $table->text('description')->nullable();
            $table->string('region');
            $table->unsignedInteger('duration_minutes');
            $table->string('status', 20)->default('draft')->index();
            $table->string('pricing_mode', 20)->default('fixed');
            $table->unsignedTinyInteger('min_travellers')->default(1);
            $table->unsignedTinyInteger('max_travellers');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trip_departures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->time('start_time');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['trip_id', 'start_time']);
        });

        Schema::create('trip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('time_label')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['trip_id', 'kind', 'sort_order']);
        });

        Schema::create('trip_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_minor');
            $table->unsignedTinyInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('trip_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('price_minor');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['trip_id', 'vehicle_id', 'valid_from', 'valid_to']);
        });

        Schema::create('trip_blackout_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'date']);
        });

        Schema::create('trip_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30);
            $table->date('travel_date')->nullable();
            $table->unsignedTinyInteger('travellers')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_enquiries');
        Schema::dropIfExists('trip_blackout_dates');
        Schema::dropIfExists('trip_prices');
        Schema::dropIfExists('trip_addons');
        Schema::dropIfExists('trip_items');
        Schema::dropIfExists('trip_departures');
        Schema::dropIfExists('trips');
    }
};
