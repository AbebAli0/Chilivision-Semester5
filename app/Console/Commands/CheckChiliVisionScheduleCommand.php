<?php

namespace App\Console\Commands;

use App\Http\Controllers\ChiliVisionApiController;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chilivision:check-schedule')]
#[Description('Evaluasi jadwal penyemprotan pestisida dan pantau status koneksi ESP32 di background')]
class CheckChiliVisionScheduleCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ChiliVisionApiController $controller): int
    {
        $result = $controller->runScheduledHealthAndSprayCheck();

        $this->info('Jadwal Pestisida: '.$result['schedule']['next_spray_formatted']
            .' | ESP32: '.$result['esp32']['label']
            .($result['schedule']['auto_triggered_now'] ? ' [AUTO SPRAY QUEUED]' : '')
            .($result['offline_alert_sent'] ? ' [ESP32 OFFLINE ALERT SENT]' : ''));

        return self::SUCCESS;
    }
}
