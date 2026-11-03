<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('parent_key')->nullable()->after('editable_by');
            $table->boolean('is_master')->default(false)->after('parent_key');
        });
        $now = now();
        foreach ([
            ['restrict_pickups_enabled', '0', 'bool', 'locations', 'Master switch for restricted customer pickups.', 'locations.manage', null, true],
            ['restricted_pickup_message', 'Choose an available pickup location to continue.', 'string', 'locations', 'Message shown when a pickup is outside the allowed list.', 'locations.manage', 'restrict_pickups_enabled', false],
            ['trips_enabled', '1', 'bool', 'trips', 'Master switch for published day tours and trip bookings.', 'trips.manage', null, true],
            ['trip_min_notice_hours', '48', 'int', 'trips', 'Minimum notice before a customer can book a trip departure.', 'trips.manage', 'trips_enabled', false],
        ] as [$key, $value, $type, $group, $description, $editableBy, $parent, $master]) {
            DB::table('app_settings')->insertOrIgnore(['key' => $key, 'value' => $value, 'type' => $type, 'group' => $group, 'description' => $description, 'is_secret' => false, 'editable_by' => $editableBy, 'parent_key' => $parent, 'is_master' => $master, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', ['restrict_pickups_enabled', 'restricted_pickup_message', 'trips_enabled', 'trip_min_notice_hours'])->delete();
        Schema::table('app_settings', function (Blueprint $table): void { $table->dropColumn(['parent_key', 'is_master']); });
    }
};
