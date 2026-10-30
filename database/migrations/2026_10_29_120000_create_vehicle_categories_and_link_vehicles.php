<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
        });

        $now = now();
        DB::table('vehicle_categories')->insert([
            ['name' => 'Executive', 'slug' => 'executive', 'description' => 'Comfortable premium business travel.', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'First Class', 'slug' => 'first-class', 'description' => 'Flagship luxury travel.', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Van', 'slug' => 'van', 'description' => 'Spacious travel for groups and luggage.', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('slug')->constrained('vehicle_categories')->restrictOnDelete();
        });

        $categories = DB::table('vehicle_categories')->pluck('id', 'slug');
        DB::table('vehicles')->orderBy('id')->each(function (object $vehicle) use ($categories): void {
            $slug = match ($vehicle->category) {
                'first_class' => 'first-class',
                default => $vehicle->category,
            };
            DB::table('vehicles')->where('id', $vehicle->id)->update(['category_id' => $categories[$slug] ?? $categories['executive']]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->index()->after('slug');
        });

        DB::table('vehicles')->orderBy('id')->each(function (object $vehicle): void {
            $category = DB::table('vehicle_categories')->where('id', $vehicle->category_id)->value('slug');
            DB::table('vehicles')->where('id', $vehicle->id)->update(['category' => $category === 'first-class' ? 'first_class' : ($category ?: 'executive')]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('vehicle_categories');
    }
};
