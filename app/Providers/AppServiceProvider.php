<?php

namespace App\Providers;

use App\Models\EspMapping;
use App\Models\Mesin;
use App\Observers\EspMappingObserver;
use App\Observers\MesinObserver;
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
        // Menjaga tabel mesin (dipakai Flutter) dan esp_mapping (dipakai dashboard/ESP32)
        // tetap satu suara, apa pun arah perubahan statusnya.
        Mesin::observe(MesinObserver::class);
        EspMapping::observe(EspMappingObserver::class);
    }
}
