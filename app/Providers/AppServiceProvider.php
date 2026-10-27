<?php

namespace App\Providers;

use App\Models\ChatbotMessage;
use App\Models\Dht11Reading;
use App\Models\LeafDetection;
use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use Illuminate\Support\Facades\Cache;
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
        $invalidateDashboard = static function (): void {
            Cache::forget('dashboard_state_db_snapshot');
        };

        foreach ([
            ChatbotMessage::class,
            Dht11Reading::class,
            LeafDetection::class,
            SoilMoistureReading::class,
            SprayHistory::class,
        ] as $modelClass) {
            $modelClass::saved($invalidateDashboard);
            $modelClass::deleted($invalidateDashboard);
        }

        LeafDetection::saved(static fn () => Cache::forget('esp32_latest_leaf_summary'));
        LeafDetection::deleted(static fn () => Cache::forget('esp32_latest_leaf_summary'));
    }
}
