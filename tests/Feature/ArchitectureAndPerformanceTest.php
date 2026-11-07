<?php

namespace Tests\Feature;

use App\Http\Controllers\ChiliVisionApiController;
use App\Models\LeafDetection;
use App\Services\GeminiChatService;
use App\Services\LeafInferenceService;
use App\Services\PesticideScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchitectureAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngUpload(string $filename = 'leaf_sample.png'): UploadedFile
    {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($filename, $pngBytes);
    }

    public function test_services_and_controller_resolve_cleanly_from_container(): void
    {
        $inference = app(LeafInferenceService::class);
        $schedule = app(PesticideScheduleService::class);
        $gemini = app(GeminiChatService::class);
        $controller = app(ChiliVisionApiController::class);

        $this->assertInstanceOf(LeafInferenceService::class, $inference);
        $this->assertInstanceOf(PesticideScheduleService::class, $schedule);
        $this->assertInstanceOf(GeminiChatService::class, $gemini);
        $this->assertInstanceOf(ChiliVisionApiController::class, $controller);
    }

    public function test_health_and_dashboard_report_fastapi_warm_when_worker_is_online(): void
    {
        Cache::forget('fastapi_inference_offline');
        config([
            'services.inference.use_fastapi' => true,
            'services.inference.url' => 'http://127.0.0.1:8001',
        ]);

        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response(['status' => 'ok'], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('system_services.laravel', 'ok')
            ->assertJsonPath('system_services.inference_engine.mode', 'fastapi_warm')
            ->assertJsonPath('system_services.inference_engine.fastapi_online', true)
            ->assertJsonPath('system_services.scheduler.command', 'chilivision:check-schedule');

        $this->getJson('/api/dashboard/state')
            ->assertOk()
            ->assertJsonPath('system_services.inference_engine.mode', 'fastapi_warm')
            ->assertJsonPath('system_services.inference_engine.fastapi_online', true);
    }

    public function test_health_switches_to_cli_fallback_and_sets_circuit_breaker_when_fastapi_fails(): void
    {
        Cache::forget('fastapi_inference_offline');
        config([
            'services.inference.use_fastapi' => true,
            'services.inference.url' => 'http://127.0.0.1:8001',
        ]);

        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response(['status' => 'error'], 500),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('system_services.inference_engine.mode', 'cli_fallback')
            ->assertJsonPath('system_services.inference_engine.fastapi_online', false);

        $this->assertTrue((bool) Cache::get('fastapi_inference_offline', false));
    }

    public function test_leaf_image_uses_fastapi_warm_inference_when_available(): void
    {
        Storage::fake('public');
        Cache::forget('fastapi_inference_offline');
        config([
            'services.inference.use_fastapi' => true,
            'services.inference.url' => 'http://127.0.0.1:8001',
            'services.telegram.bot_token' => '',
            'services.telegram.chat_id' => '',
        ]);

        Http::fake([
            'http://127.0.0.1:8001/predict*' => Http::response([
                'model_id' => 'yolov8n-chili',
                'model' => 'yolov8n_chili.onnx',
                'image_width' => 640,
                'image_height' => 480,
                'count' => 1,
                'primary_detection' => [
                    'class_id' => 0,
                    'class_name' => 'Sehat',
                    'confidence' => 0.91,
                    'bbox_xyxy' => [10, 10, 100, 100],
                ],
                'detections' => [
                    [
                        'class_id' => 0,
                        'class_name' => 'Sehat',
                        'confidence' => 0.91,
                        'bbox_xyxy' => [10, 10, 100, 100],
                    ],
                ],
            ], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->tinyPngUpload('healthy_leaf.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('inference_backend', 'fastapi')
            ->assertJsonPath('primary_detection.class_name', 'Sehat')
            ->assertJsonPath('latest_detection.class_name', 'Sehat')
            ->assertJsonPath('saved_to_database', true);

        $this->assertSame(1, LeafDetection::where('class_name', 'Sehat')->count());
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'http://127.0.0.1:8001/predict'));
    }

    public function test_api_docs_page_and_json_spec_reflect_services_and_new_endpoints(): void
    {
        Cache::forget('fastapi_inference_offline');
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response(['status' => 'ok'], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $this->get('/api')
            ->assertOk()
            ->assertSee('LeafInferenceService')
            ->assertSee('PesticideScheduleService')
            ->assertSee('GeminiChatService')
            ->assertSee('/api/telegram/model')
            ->assertSee('/api/hardware/esp32-status')
            ->assertSee('/api/reports/export/{type}')
            ->assertSee('FastAPI Warm (8001)');

        $this->getJson('/api?format=json')
            ->assertOk()
            ->assertJsonPath('total_groups', 9)
            ->assertJsonPath('architecture.services.0', 'LeafInferenceService')
            ->assertJsonPath('architecture.services.1', 'PesticideScheduleService')
            ->assertJsonPath('architecture.services.2', 'GeminiChatService')
            ->assertJsonPath('system_services.inference_engine.mode', 'fastapi_warm')
            ->assertJsonPath('endpoints.7_chatbot_esp32_telegram.esp32_status', 'GET /api/hardware/esp32-status')
            ->assertJsonPath('endpoints.8_laporan_ekspor_csv.export_csv', 'GET /api/reports/export/{type} (soil | dht11 | leaf | spray)');
    }
}
