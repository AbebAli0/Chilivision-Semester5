<?php

namespace Tests\Feature;

use App\Models\SprayHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PesticideScheduleChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_pesticide_schedule_to_two_days_via_chatbot(): void
    {
        Http::fake();
        Cache::forget('pesticide_schedule_interval_hours');

        $response = $this->postJson('/api/chatbot/message', [
            'message' => 'ganti jadwal semprot pestisida ke 2 hari sekali',
            'source' => 'web',
        ]);

        $response->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_pesticide_schedule')
            ->assertJsonPath('spray_action.interval_days', 2)
            ->assertJsonPath('spray_action.interval_hours', 48)
            ->assertJsonPath('spray_action.interval_label', '2 Hari Sekali');

        // Memastikan tidak salah memicu penyemprotan manual
        $this->assertSame(0, SprayHistory::where('trigger_mode', 'manual')->count());

        $this->getJson('/api/dashboard/state')
            ->assertOk()
            ->assertJsonPath('spray_history.pesticide_schedule_2_days.interval_days', 2)
            ->assertJsonPath('spray_history.pesticide_schedule_2_days.interval_hours', 48);

        $this->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('pesticide_schedule_mode', 'interval')
            ->assertJsonPath('pesticide_interval_hours', 48)
            ->assertJsonPath('pesticide_interval_label', '2 Hari Sekali');
    }

    public function test_user_can_change_pesticide_schedule_using_indonesian_words_or_hours(): void
    {
        Http::fake();

        $this->postJson('/api/chatbot/message', [
            'message' => 'ubah jadwal pestisida menjadi tiga hari sekali',
            'source' => 'telegram',
        ])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_pesticide_schedule')
            ->assertJsonPath('spray_action.interval_days', 3)
            ->assertJsonPath('spray_action.interval_hours', 72);

        $this->postJson('/api/chatbot/message', [
            'message' => 'atur jadwal pestisida tiap 12 jam sekali',
            'source' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_pesticide_schedule')
            ->assertJsonPath('spray_action.interval_hours', 12)
            ->assertJsonPath('spray_action.interval_label', '12 Jam Sekali');
    }

    public function test_specific_target_datetime_from_gemini_sprays_exactly_at_requested_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 0, 0, 'Asia/Jakarta'));
        config()->set('services.gemini.api_key', 'AQ.FakeTestKey');
        Cache::forget('pesticide_schedule_target_at');
        Cache::forget('pesticide_schedule_target_duration');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode([
                                    'reply' => 'Baik, jadwal penyemprotan pestisida saya atur untuk 15 November 2026 pukul 08:30 WIB.',
                                    'action' => 'set_pesticide_target_datetime',
                                    'target_datetime' => '2026-11-15 08:30',
                                    'duration_seconds' => 10,
                                ]),
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $res = $this->postJson('/api/chatbot/message', [
            'message' => 'tolong jadwalkan semprot pestisida tanggal 15 november jam setengah 9 pagi selama 10 detik',
            'source' => 'telegram',
        ]);

        $res->assertOk()
            ->assertJsonPath('bot_reply.intent', 'set_pesticide_target_datetime')
            ->assertJsonPath('spray_action.schedule_mode', 'target_datetime')
            ->assertJsonPath('spray_action.next_spray_formatted', '15 Nov 2026, 08:30:00 WIB');

        // Sebelum waktu target tiba, ESP32 poll tidak mengeksekusi semprot pestisida
        Carbon::setTestNow(Carbon::create(2026, 11, 15, 8, 29, 50, 'Asia/Jakarta'));
        $this->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('spray_pesticide', false)
            ->assertJsonPath('pesticide_duration_seconds', 0);

        // Saat waktu target tercapai, ESP32 poll otomatis memicu semprot pestisida 10 detik & hapus target sekali pakai
        Carbon::setTestNow(Carbon::create(2026, 11, 15, 8, 30, 5, 'Asia/Jakarta'));
        $this->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('spray_pesticide', true)
            ->assertJsonPath('pesticide_duration_seconds', 10);
        $this->assertNull(Cache::get('pesticide_schedule_target_at'));

        // Poll berikutnya tidak menyemprot ulang
        $this->getJson('/api/esp32/poll')
            ->assertOk()
            ->assertJsonPath('spray_pesticide', false)
            ->assertJsonPath('pesticide_duration_seconds', 0);

        Carbon::setTestNow();
    }

    public function test_specific_target_datetime_fallback_parser_and_past_validation(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 13, 14, 0, 0, 'Asia/Jakarta'));
        config()->set('services.gemini.api_key', '');
        Cache::forget('pesticide_schedule_target_at');
        Http::fake();

        $okRes = $this->postJson('/api/chatbot/message', [
            'message' => 'jadwalkan semprot pestisida besok jam 07:00',
            'source' => 'web',
        ]);

        $okRes->assertOk()
            ->assertJsonPath('bot_reply.intent', 'set_pesticide_target_datetime')
            ->assertJsonPath('spray_action.next_spray_formatted', '14 Nov 2026, 07:00:00 WIB');

        $pastRes = $this->postJson('/api/chatbot/message', [
            'message' => 'jadwalkan semprot pestisida tanggal 10 november 2026 jam 07:00',
            'source' => 'web',
        ]);

        $pastRes->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_pesticide_schedule_invalid');

        Carbon::setTestNow();
    }
}
