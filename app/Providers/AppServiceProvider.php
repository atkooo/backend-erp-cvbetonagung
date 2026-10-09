<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        try {
            if (Schema::hasTable('settings')) {
                $tz = Setting::query()->where('key', 'company.timezone')->value('value');
                if ($tz && $tz !== 'auto' && in_array($tz, timezone_identifiers_list(), true)) {
                    config(['app.timezone' => $tz]);
                    date_default_timezone_set($tz);
                }
            }
        } catch (\Throwable) {
            // Ignore during migrations or initial bootstrap
        }
    }
}
