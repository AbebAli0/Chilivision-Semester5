<?php

namespace Tests\Feature;

use App\Services\LeafInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramModelSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_selected_model_is_yolov8n(): void
    {
        Cache::forget('telegram_selected_model');

        $response = $this->getJson('/api/telegram/model')
            ->assertOk()
            ->assertJsonPath('selected_model_id', 'yolov8n-chili')
            ->assertJsonCount(2, 'models');

        $ids = array_column($response->json('models'), 'id');
        $this->assertSame(['yolov8n-chili', 'cnn-5ch-chili'], $ids);

        $names = array_column($response->json('models'), 'name');
        $this->assertSame([
            'Yolov8n (Deteksi Penyakit Daun Cabai)',
            'CNN (Klasifikasi Penyakit Daun Cabai)',
        ], $names);
    }

    public function test_user_can_select_available_model(): void
    {
        foreach (['cnn-5ch-chili', 'yolov8n-chili'] as $modelId) {
            $this->postJson('/api/telegram/model', ['model_id' => $modelId])
                ->assertOk()
                ->assertJsonPath('selected_model_id', $modelId);

            $this->getJson('/api/telegram/model')
                ->assertOk()
                ->assertJsonPath('selected_model_id', $modelId);
        }
    }

    public function test_unknown_model_is_rejected(): void
    {
        $this->postJson('/api/telegram/model', ['model_id' => 'tidak-ada'])
            ->assertNotFound()
            ->assertJsonPath('status', 'error');
    }

    public function test_model_id_is_required(): void
    {
        $this->postJson('/api/telegram/model', [])
            ->assertUnprocessable();
    }

    public function test_chatbot_model_command_without_slash_returns_model_menu(): void
    {
        Http::fake();
        Cache::forget('telegram_selected_model');

        $this->postJson('/api/chatbot/message', ['message' => 'model', 'source' => 'web'])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'select_model_menu')
            ->assertJsonCount(2, 'bot_reply.metadata_json.models');
    }

    public function test_chatbot_can_select_model_directly_by_name(): void
    {
        Http::fake();

        $this->postJson('/api/chatbot/message', ['message' => 'pilih cnn', 'source' => 'web'])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'model_selected')
            ->assertJsonPath('selected_model_id', 'cnn-5ch-chili');

        $this->getJson('/api/telegram/model')
            ->assertOk()
            ->assertJsonPath('selected_model_id', 'cnn-5ch-chili');

        $this->postJson('/api/chatbot/message', ['message' => 'model yolov8n', 'source' => 'telegram'])
            ->assertOk()
            ->assertJsonPath('bot_reply.intent', 'model_selected')
            ->assertJsonPath('selected_model_id', 'yolov8n-chili');

        $this->getJson('/api/telegram/model')
            ->assertOk()
            ->assertJsonPath('selected_model_id', 'yolov8n-chili');
    }

    public function test_bukan_daun_is_treated_as_no_object_detected(): void
    {
        $service = app(LeafInferenceService::class);

        $normalized = $service->normalizeInferencePayload([
            'model_id' => 'cnn-5ch-chili',
            'count' => 1,
            'classification' => [
                'class_id' => 6,
                'class_name' => 'bukan_daun',
                'confidence' => 0.98,
            ],
            'primary_detection' => [
                'class_id' => 6,
                'class_name' => 'Bukan Daun',
                'confidence' => 0.98,
            ],
            'detections' => [],
        ]);

        $this->assertNull($normalized['classification']);
        $this->assertNull($normalized['primary_detection']);
        $this->assertSame(0, $normalized['count']);
    }
}
