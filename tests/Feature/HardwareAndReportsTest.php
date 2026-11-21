<?php

namespace Tests\Feature;

use App\Models\ChatbotMessage;
use App\Models\Dht11Reading;
use App\Models\LeafDetection;
use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardwareAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_esp32_heartbeat_transitions_from_never_connected_to_online_and_offline(): void
    {
        Http::fake();
        Cache::forget('esp32_last_seen_at');
        Cache::forget('esp32_last_activity');
        Cache::forget('esp32_offline_alert_sent');

        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 0, 0, 'Asia/Jakarta'));

        $this->getJson('/api/hardware/esp32-status')
            ->assertOk()
            ->assertJsonPath('esp32_status.state', 'never_connected')
            ->assertJsonPath('esp32_status.is_online', false);

        $this->getJson('/api/esp32/poll')->assertOk();

        $this->getJson('/api/hardware/esp32-status')
            ->assertOk()
            ->assertJsonPath('esp32_status.state', 'online')
            ->assertJsonPath('esp32_status.is_online', true)
            ->assertJsonPath('esp32_status.last_activity', 'poll')
            ->assertJsonPath('esp32_status.offline_threshold_seconds', 45);

        // Lewat > 15 detik masuk Koneksi Lambat
        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 0, 20, 'Asia/Jakarta'));

        $this->getJson('/api/hardware/esp32-status')
            ->assertOk()
            ->assertJsonPath('esp32_status.state', 'warning')
            ->assertJsonPath('esp32_status.is_online', true);

        // Lewat > 45 detik dianggap offline
        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 0, 46, 'Asia/Jakarta'));

        $this->getJson('/api/hardware/esp32-status')
            ->assertOk()
            ->assertJsonPath('esp32_status.state', 'offline')
            ->assertJsonPath('esp32_status.is_online', false);

        Carbon::setTestNow();
    }

    public function test_browser_preview_poll_and_non_esp32_sensor_posts_do_not_fake_esp32_online(): void
    {
        Http::fake();
        Cache::forget('esp32_last_seen_at');
        Cache::forget('esp32_last_activity');
        Cache::forget('esp32_offline_alert_sent');

        SprayHistory::create([
            'type' => 'water',
            'trigger_mode' => 'manual',
            'triggered_by' => 'web',
            'duration_seconds' => 5,
            'status' => 'queued',
            'sprayed_at' => now('Asia/Jakarta'),
        ]);

        // Preview dari web / link Cek Poll maupun request browser dari /api-docs tidak boleh mencatat heartbeat atau menghabiskan antrean pompa
        $this->getJson('/api/esp32/poll?preview=1')
            ->assertOk()
            ->assertJsonPath('preview_mode', true)
            ->assertJsonPath('heartbeat_recorded', false)
            ->assertJsonPath('spray_water', true);

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Sec-Fetch-Mode' => 'cors',
            'X-CSRF-TOKEN' => 'test-token',
        ])->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('preview_mode', true)
            ->assertJsonPath('heartbeat_recorded', false)
            ->assertJsonPath('spray_water', true);

        // Kiriman sensor tanpa source default ke 'api' sehingga tidak menandai ESP32 online
        $this->postJson('/api/sensor/soil-moisture', ['moisture_percent' => 55])
            ->assertCreated()
            ->assertJsonPath('data.source', 'api');

        $this->postJson('/api/sensor/dht11', ['temperature' => 29.0, 'humidity' => 65.0])
            ->assertCreated()
            ->assertJsonPath('data.source', 'api');

        $this->getJson('/api/hardware/esp32-status')
            ->assertOk()
            ->assertJsonPath('esp32_status.state', 'never_connected')
            ->assertJsonPath('esp32_status.is_online', false)
            ->assertJsonPath('esp32_status.pending_water_count', 1);
    }

    public function test_scheduler_command_triggers_due_pesticide_and_sends_single_offline_alert(): void
    {
        Http::fake();
        Cache::forget('esp32_last_seen_at');
        Cache::forget('esp32_offline_alert_sent');
        Cache::forget('pesticide_schedule_target_at');

        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 0, 0, 'Asia/Jakarta'));
        $this->getJson('/api/esp32/poll')->assertOk();

        // Set jadwal target 2 menit lagi dan majukan waktu 4 menit (target tercapai + ESP32 offline > 3 menit)
        Cache::forever('pesticide_schedule_target_at', Carbon::create(2026, 11, 13, 14, 2, 0, 'Asia/Jakarta')->toIso8601String());
        Cache::forever('pesticide_schedule_target_duration', 7);

        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 4, 0, 'Asia/Jakarta'));

        $this->artisan('chilivision:check-schedule')->assertExitCode(0);

        $this->assertSame(1, SprayHistory::where('type', 'pesticide')->where('trigger_mode', 'schedule_target_datetime')->count());
        $this->assertSame(1, ChatbotMessage::where('intent', 'esp32_offline_alert')->count());

        // Jalankan lagi tidak mengirim alert offline duplikat
        $this->artisan('chilivision:check-schedule')->assertExitCode(0);
        $this->assertSame(1, ChatbotMessage::where('intent', 'esp32_offline_alert')->count());

        Carbon::setTestNow();
    }

    public function test_dashboard_analytics_and_csv_export_endpoints(): void
    {
        SoilMoistureReading::create([
            'adc_value' => 2350,
            'moisture_percent' => 48.2,
            'status' => 'Optimal',
            'recommendation' => 'Tanah optimal.',
            'source' => 'esp32',
        ]);
        Dht11Reading::create([
            'temperature' => 30.5,
            'humidity' => 65.0,
            'heat_index' => 31.2,
            'status' => 'Optimal',
            'recommendation' => 'Suhu ideal.',
            'source' => 'esp32',
        ]);
        LeafDetection::create([
            'model_id' => 'yolov8n-chili',
            'class_name' => 'Sehat',
            'confidence' => 0.92,
            'recommendation' => 'Daun sehat.',
            'image_hash' => hash('sha256', 'sample-leaf'),
        ]);
        SprayHistory::create([
            'type' => 'water',
            'trigger_mode' => 'manual',
            'triggered_by' => 'web',
            'duration_seconds' => 5,
            'status' => 'completed',
            'sprayed_at' => now('Asia/Jakarta'),
        ]);

        $this->getJson('/api/dashboard/state')
            ->assertOk()
            ->assertJsonPath('analytics.soil_series.0.value', 48.2)
            ->assertJsonPath('analytics.soil_series.0.adc_value', 2350)
            ->assertJsonPath('analytics.soil_series.0.status', 'Optimal')
            ->assertJsonPath('analytics.dht_series.0.temperature', 30.5)
            ->assertJsonPath('analytics.dht_series.0.heat_index', 31.2)
            ->assertJsonPath('analytics.dht_series.0.status', 'Optimal')
            ->assertJsonPath('analytics.leaf_summary.0.class_name', 'Sehat')
            ->assertJsonPath('analytics.spray_summary.water_total', 1)
            ->assertJsonPath('esp32_status.state', 'never_connected');

        $this->get('/')
            ->assertOk()
            ->assertSee('soilChartTooltip', false)
            ->assertSee('dhtChartTooltip', false)
            ->assertSee('soilChartInspector', false)
            ->assertSee('dhtChartInspector', false);

        foreach (['soil', 'dht11', 'leaf', 'spray'] as $type) {
            $res = $this->get("/api/reports/export/{$type}");
            $res->assertOk();
            $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));
            $content = $res->streamedContent();
            $this->assertNotEmpty($content);
        }

        $this->getJson('/api/reports/export/unknown')->assertNotFound();
    }

    public function test_dry_soil_auto_spray_respects_cooldown_and_throttles_routine_telegram(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);
        config([
            'services.telegram.bot_token' => 'fake-token',
            'services.telegram.chat_id' => '123456',
        ]);
        Cache::forget('auto_water_dry_soil_cooldown');
        Cache::forget('telegram_soil_notify_cooldown');
        Cache::forget('telegram_dht11_notify_cooldown');
        Cache::forget('telegram_dht11_alert_cooldown');
        Cache::forget('telegram_unreachable');

        $this->postJson('/api/sensor/soil-moisture', [
            'moisture_percent' => 5,
            'adc_value' => 2950,
            'source' => 'esp32',
        ])->assertCreated()->assertJsonPath('auto_water_spray_triggered', true);

        // Kiriman kedua dalam 60 detik tetap tersimpan ke DB, namun tidak mengantrekan semprot ulang
        $this->postJson('/api/sensor/soil-moisture', [
            'moisture_percent' => 4,
            'adc_value' => 2960,
            'source' => 'esp32',
        ])->assertCreated()->assertJsonPath('auto_water_spray_triggered', false);

        $this->assertSame(2, SoilMoistureReading::count());
        $this->assertSame(1, SprayHistory::where('trigger_mode', 'auto_dry_soil')->count());

        // Kiriman DHT11 rutin dua kali berturut-turut hanya mengirim Telegram 1 kali dalam jendela 5 menit
        $this->postJson('/api/sensor/dht11', [
            'temperature' => 29.5,
            'humidity' => 65.0,
            'source' => 'esp32',
        ])->assertCreated();

        $this->postJson('/api/sensor/dht11', [
            'temperature' => 29.6,
            'humidity' => 65.2,
            'source' => 'esp32',
        ])->assertCreated();

        $this->assertSame(2, Dht11Reading::count());
        Http::assertSentCount(2);
    }

    public function test_auto_water_threshold_can_be_changed_via_chatbot_and_synced_to_esp32(): void
    {
        Http::fake();
        Cache::forget('auto_water_moisture_threshold');
        Cache::forget('auto_water_dry_soil_cooldown');

        // Default awal 30%
        $this->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('auto_water_threshold', 30);

        // Ubah menjadi 45% lewat chatbot
        $this->postJson('/api/chatbot/message', [
            'message' => 'ubah semprot air dibawah 45%',
            'source' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_auto_water_threshold')
            ->assertJsonPath('spray_action.auto_water_threshold', 45);

        $this->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('auto_water_threshold', 45);

        $this->getJson('/api/dashboard/state')
            ->assertOk()
            ->assertJsonPath('auto_water_threshold', 45);

        // Kelembapan 40% sekarang memicu penyiraman otomatis karena < 45%
        $this->postJson('/api/sensor/soil-moisture', [
            'moisture_percent' => 40,
            'adc_value' => 2280,
            'source' => 'esp32',
        ])
            ->assertCreated()
            ->assertJsonPath('auto_water_spray_triggered', true)
            ->assertJsonPath('data.status', 'Kering');

        // Ubah ke 25% lalu kirim 35%: tidak memicu semprot otomatis
        Cache::forget('auto_water_dry_soil_cooldown');
        $this->postJson('/api/chatbot/message', [
            'message' => 'ganti batas semprot air jadi 25%',
            'source' => 'telegram',
        ])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_auto_water_threshold')
            ->assertJsonPath('spray_action.auto_water_threshold', 25);

        $this->postJson('/api/sensor/soil-moisture', [
            'moisture_percent' => 35,
            'adc_value' => 2370,
            'source' => 'esp32',
        ])
            ->assertCreated()
            ->assertJsonPath('auto_water_spray_triggered', false)
            ->assertJsonPath('data.status', 'Optimal');

        // Cek nilai saat ini tanpa angka & validasi batas tidak wajar
        $this->postJson('/api/chatbot/message', [
            'message' => 'cek batas semprot air',
            'source' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_auto_water_threshold_prompt')
            ->assertJsonPath('spray_action.auto_water_threshold', 25);

        $this->postJson('/api/chatbot/message', [
            'message' => 'ubah batas semprot air 120%',
            'source' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_auto_water_threshold_invalid');
    }
}
