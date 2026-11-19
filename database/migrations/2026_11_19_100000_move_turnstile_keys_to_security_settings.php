<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')
            ->whereIn('key', ['turnstile_site_key', 'turnstile_secret_key'])
            ->update(['group' => 'security']);

        Cache::forget('settings:public-values');
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->whereIn('key', ['turnstile_site_key', 'turnstile_secret_key'])
            ->update(['group' => 'integrations']);

        Cache::forget('settings:public-values');
    }
};
