<?php

namespace Tests\Feature;

use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversational_question_calls_gemini_with_sensor_context(): void
    {
        config()->set('services.gemini.api_key', 'AQ.FakeTestKey');
        config()->set('services.gemini.model', 'gemini-3.5-flash-lite');

        SoilMoistureReading::create([
            'adc_value' => 2400,
            'moisture_percent' => 42.5,
            'status' => 'Normal',
            'recommendation' => 'Kelembapan tanah ideal.',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'status' => 'completed',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode([
                                    'reply' => 'Kondisi tanah 42.5% cukup ideal untuk tanaman cabai dan tidak perlu disiram sekarang.',
                                    'action' => 'none',
                                ]),
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->postJson('/api/chatbot/message', [
            'message' => 'kenapa daun cabai keriting dan apakah tanah sekarang aman?',
            'source' => 'telegram',
        ]);

        $response->assertOk()
            ->assertJsonPath('bot_reply.intent', 'ai_chat')
            ->assertJsonPath('bot_reply.metadata_json.ai_model', 'gemini-3.5-flash-lite');

        $this->assertStringContainsString('42.5%', (string) $response->json('bot_reply.message'));

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'generativelanguage.googleapis.com/v1beta/interactions')
                && $request->hasHeader('x-goog-api-key', 'AQ.FakeTestKey')
                && ($request['model'] ?? null) === 'gemini-3.5-flash-lite'
                && str_contains((string) ($request['input'] ?? ''), '42.5%');
        });
    }

    public function test_gemini_can_trigger_pesticide_schedule_and_water_spray_actions(): void
    {
        config()->set('services.gemini.api_key', 'AQ.FakeTestKey');
        config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
        Cache::forget('pesticide_schedule_interval_hours');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode([
                                        'reply' => 'Siap, jadwal penyemprotan pestisida sudah saya ubah ke 3 hari sekali.',
                                        'action' => 'update_pesticide_schedule',
                                        'interval_hours' => 72,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'steps' => [
                        [
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode([
                                        'reply' => 'Baik, saya aktifkan pompa air selama 8 detik ya.',
                                        'action' => 'spray_water',
                                        'duration_seconds' => 8,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $scheduleRes = $this->postJson('/api/chatbot/message', [
            'message' => 'nanti tolong atur penyemprotan obat hama biar tiga hari sekali aja ya',
            'source' => 'telegram',
        ]);

        $scheduleRes->assertOk()
            ->assertJsonPath('bot_reply.intent', 'update_pesticide_schedule')
            ->assertJsonPath('spray_action.interval_hours', 72);
        $this->assertSame(72, Cache::get('pesticide_schedule_interval_hours'));

        $sprayRes = $this->postJson('/api/chatbot/message', [
            'message' => 'tanah kayaknya kering nih, tolong siramin sebentar 8 detik',
            'source' => 'telegram',
        ]);

        $sprayRes->assertOk()
            ->assertJsonPath('bot_reply.intent', 'spray_water')
            ->assertJsonPath('spray_action.duration_seconds', 8);

        $this->assertSame(1, SprayHistory::where('type', 'water')->where('trigger_mode', 'manual')->count());
    }

    public function test_fast_deterministic_commands_do_not_call_gemini(): void
    {
        config()->set('services.gemini.api_key', 'AQ.FakeTestKey');
        Http::fake();

        $this->postJson('/api/chatbot/message', [
            'message' => '/status',
            'source' => 'telegram',
        ])->assertOk()->assertJsonPath('bot_reply.intent', 'status_help');

        $this->postJson('/api/chatbot/message', [
            'message' => 'model',
            'source' => 'telegram',
        ])->assertOk()->assertJsonPath('bot_reply.intent', 'select_model_menu');

        Http::assertNothingSent();
    }

    public function test_gemini_rate_limit_or_error_falls_back_gracefully_to_local_handler(): void
    {
        config()->set('services.gemini.api_key', 'AQ.FakeTestKey');
        config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
        config()->set('services.gemini.fallback_model', 'gemini-3.8-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->postJson('/api/chatbot/message', [
            'message' => 'halo asisten bagaimana kabar kebun hari ini?',
            'source' => 'telegram',
        ]);

        $response->assertOk()
            ->assertJsonPath('bot_reply.intent', 'status_help');
    }
}
