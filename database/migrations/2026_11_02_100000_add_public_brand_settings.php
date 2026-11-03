<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        foreach ([
            ['site_name', 'Swiss Limousine', 'string', 'Name shown in the public website header, browser title and customer-facing pages.'],
            ['site_logo_url', '', 'string', 'Public HTTPS URL or site-relative path for the logo shown in the customer website header. Leave blank to use the site name.'],
        ] as [$key, $value, $type, $description]) {
            DB::table('app_settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'group' => 'company',
                'description' => $description,
                'is_secret' => false,
                'editable_by' => 'company.manage',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('settings:public-values');
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', ['site_name', 'site_logo_url'])->delete();
        Cache::forget('settings:public-values');
    }
};
