<?php

namespace Tests\Feature;

use App\Models\ChatbotMessage;
use App\Models\LeafDetection;
use App\Models\SprayHistory;
use App\Services\LeafInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiseaseNameNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngUpload(string $filename = 'leaf_sample.png'): UploadedFile
    {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($filename, $pngBytes);
    }

    public function test_leaf_inference_service_formats_disease_names_and_recommendations(): void
    {
        $service = app(LeafInferenceService::class);

        $this->assertSame('Bercak Daun', $service->formatClassName('bercak_daun'));
        $this->assertSame('Bercak Bakteri', $service->formatClassName('bercak_bakteri'));
        $this->assertSame('Bercak Cercospora', $service->formatClassName('bercak_cercospora'));
        $this->assertSame('Bercak Putih', $service->formatClassName('bercak_putih'));
        $this->assertSame('Daun Sehat', $service->formatClassName('daun_sehat'));
        $this->assertSame('Defisiensi Nutrisi', $service->formatClassName('defisiensi_nutrisi'));
        $this->assertSame('Virus Keriting', $service->formatClassName('virus_keriting'));

        $this->assertSame('Daun Sehat', $service->formatClassName('Daun Sehat'));
        $this->assertSame('Tidak Terdeteksi', $service->formatClassName('Tidak Terdeteksi'));
        $this->assertSame('Belum ada deteksi', $service->formatClassName('Belum ada deteksi'));

        $this->assertStringContainsString('bercak bakteri', $service->getLeafRecommendation('Bercak Bakteri'));
        $this->assertStringContainsString('bercak cercospora', $service->getLeafRecommendation('Bercak Cercospora'));
        $this->assertStringContainsString('bercak putih', $service->getLeafRecommendation('Bercak Putih'));
        $this->assertStringContainsString('kondisi sehat', $service->getLeafRecommendation('Daun Sehat'));
        $this->assertStringContainsString('defisiensi nutrisi', $service->getLeafRecommendation('Defisiensi Nutrisi'));
        $this->assertStringContainsString('keriting/virus kuning', $service->getLeafRecommendation('Virus Keriting'));
    }

    public function test_leaf_image_normalizes_snake_case_predictions_across_api_db_and_chatbot(): void
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
                'count' => 2,
                'primary_detection' => [
                    'class_id' => 0,
                    'class_name' => 'bercak_daun',
                    'confidence' => 0.88,
                    'bbox_xyxy' => [10, 10, 100, 100],
                ],
                'detections' => [
                    [
                        'class_id' => 0,
                        'class_name' => 'bercak_daun',
                        'confidence' => 0.88,
                        'bbox_xyxy' => [10, 10, 100, 100],
                    ],
                    [
                        'class_id' => 1,
                        'class_name' => 'bercak_bakteri',
                        'confidence' => 0.72,
                        'bbox_xyxy' => [120, 40, 220, 150],
                    ],
                ],
            ], 200),
            '*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->tinyPngUpload('diseased_leaf.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('primary_detection.class_name', 'Bercak Daun')
            ->assertJsonPath('detections.0.class_name', 'Bercak Daun')
            ->assertJsonPath('detections.1.class_name', 'Bercak Bakteri')
            ->assertJsonPath('latest_detection.class_name', 'Bercak Daun')
            ->assertJsonPath('saved_to_database', true);

        $this->assertSame(1, LeafDetection::where('class_name', 'Bercak Daun')->count());
        $this->assertSame(0, LeafDetection::where('class_name', 'bercak_daun')->count());

        $botMessage = ChatbotMessage::where('intent', 'ask_pesticide_confirmation')->latest('id')->first();
        $this->assertNotNull($botMessage);
        $this->assertStringContainsString('Bercak Daun', $botMessage->message);
        $this->assertStringNotContainsString('bercak_daun', $botMessage->message);
    }

    public function test_dashboard_state_and_csv_export_normalize_and_merge_disease_names(): void
    {
        LeafDetection::create([
            'model_id' => 'yolov8n-chili',
            'class_name' => 'daun_sehat',
            'confidence' => 0.90,
            'recommendation' => 'Daun sehat.',
            'image_hash' => hash('sha256', 'leaf-1'),
        ]);
        LeafDetection::create([
            'model_id' => 'yolov8n-chili',
            'class_name' => 'Daun Sehat',
            'confidence' => 0.94,
            'recommendation' => 'Daun sehat.',
            'image_hash' => hash('sha256', 'leaf-2'),
        ]);
        SprayHistory::create([
            'type' => 'pesticide',
            'trigger_mode' => 'manual',
            'triggered_by' => 'web',
            'duration_seconds' => 5,
            'status' => 'completed',
            'leaf_condition' => 'bercak_putih',
            'sprayed_at' => now('Asia/Jakarta'),
        ]);

        $this->getJson('/api/dashboard/state')
            ->assertOk()
            ->assertJsonPath('analytics.leaf_summary.0.class_name', 'Daun Sehat')
            ->assertJsonPath('analytics.leaf_summary.0.total', 2)
            ->assertJsonPath('spray_history.last_pesticide_spray.leaf_condition', 'Bercak Putih');

        $csvResponse = $this->get('/api/reports/export/leaf');
        $csvResponse->assertOk();
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString('Daun Sehat', $csv);
        $this->assertStringNotContainsString('daun_sehat', $csv);
    }
}
