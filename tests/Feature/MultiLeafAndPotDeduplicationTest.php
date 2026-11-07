<?php

namespace Tests\Feature;

use App\Models\ChatbotMessage;
use App\Models\LeafDetection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiLeafAndPotDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private function makePatternPng(string $filename, string $pattern, int $qualityOrSalt = 0): UploadedFile
    {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(32, 32);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            $accent = imagecolorallocate($img, min(255, $qualityOrSalt), min(255, $qualityOrSalt), min(255, $qualityOrSalt));
            imagefill($img, 0, 0, $black);

            if ($pattern === 'left_white') {
                imagefilledrectangle($img, 0, 0, 15, 31, $white);
            } elseif ($pattern === 'top_white') {
                imagefilledrectangle($img, 0, 0, 31, 15, $white);
            }

            if ($qualityOrSalt > 0) {
                imagesetpixel($img, 31, 31, $accent);
            }

            ob_start();
            imagepng($img);
            $bytes = (string) ob_get_clean();
            imagedestroy($img);

            return UploadedFile::fake()->createWithContent($filename, $bytes);
        }

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($filename, $pngBytes.$pattern.$qualityOrSalt);
    }

    private function configureFakeInference(array $responses): void
    {
        Cache::forget('fastapi_inference_offline');
        config([
            'services.inference.use_fastapi' => true,
            'services.inference.url' => 'http://127.0.0.1:8001',
            'services.telegram.bot_token' => '',
            'services.telegram.chat_id' => '',
        ]);

        $queue = $responses;
        Http::fake([
            'http://127.0.0.1:8001/predict*' => function () use (&$queue) {
                $payload = array_shift($queue) ?? [];

                return Http::response($payload, 200);
            },
            '*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_frame_with_healthy_and_diseased_leaves_prioritizes_disease_and_saves_after_healthy(): void
    {
        Storage::fake('public');

        LeafDetection::create([
            'model_id' => 'yolov8n-chili',
            'class_name' => 'Daun Sehat',
            'confidence' => 0.92,
            'detection_count' => 1,
            'detections_json' => [
                ['class_id' => 3, 'class_name' => 'Daun Sehat', 'confidence' => 0.92],
            ],
            'image_hash' => hash('sha256', 'previous-healthy-frame'),
            'perceptual_hash' => 'ffff0000ffff0000',
            'recommendation' => 'Daun sehat.',
        ]);

        $this->configureFakeInference([
            [
                'model_id' => 'yolov8n-chili',
                'model' => 'yolov8n_chili.onnx',
                'image_width' => 640,
                'image_height' => 480,
                'count' => 2,
                'primary_detection' => [
                    'class_id' => 3,
                    'class_name' => 'daun_sehat',
                    'confidence' => 0.94,
                    'bbox_xyxy' => [10, 10, 150, 150],
                ],
                'detections' => [
                    [
                        'class_id' => 3,
                        'class_name' => 'daun_sehat',
                        'confidence' => 0.94,
                        'bbox_xyxy' => [10, 10, 150, 150],
                    ],
                    [
                        'class_id' => 0,
                        'class_name' => 'bercak_bakteri',
                        'confidence' => 0.71,
                        'bbox_xyxy' => [200, 30, 360, 200],
                    ],
                ],
            ],
        ]);

        $response = $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->makePatternPng('two_pots_one_frame.png', 'left_white'),
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('primary_detection.class_name', 'Bercak Bakteri')
            ->assertJsonPath('saved_to_database', true)
            ->assertJsonPath('leaf_changed', true)
            ->assertJsonPath('count', 2);

        $this->assertSame(2, LeafDetection::count());
        $latest = LeafDetection::latest('id')->first();
        $this->assertSame('Bercak Bakteri', $latest?->class_name);
        $this->assertCount(2, $latest?->detections_json ?? []);
        $this->assertNotNull(ChatbotMessage::where('intent', 'ask_pesticide_confirmation')->latest('id')->first());
    }

    public function test_two_visually_distinct_pots_with_same_disease_are_both_saved(): void
    {
        Storage::fake('public');

        $diseasePayload = [
            'model_id' => 'yolov8n-chili',
            'model' => 'yolov8n_chili.onnx',
            'image_width' => 640,
            'image_height' => 480,
            'count' => 1,
            'primary_detection' => [
                'class_id' => 0,
                'class_name' => 'bercak_bakteri',
                'confidence' => 0.85,
                'bbox_xyxy' => [15, 15, 180, 180],
            ],
            'detections' => [
                [
                    'class_id' => 0,
                    'class_name' => 'bercak_bakteri',
                    'confidence' => 0.85,
                    'bbox_xyxy' => [15, 15, 180, 180],
                ],
            ],
        ];

        $this->configureFakeInference([$diseasePayload, $diseasePayload]);

        $first = $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->makePatternPng('pot_a.png', 'left_white'),
        ]);
        $first->assertOk()->assertJsonPath('saved_to_database', true);

        $second = $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->makePatternPng('pot_b.png', 'top_white'),
        ]);
        $second->assertOk()
            ->assertJsonPath('saved_to_database', true)
            ->assertJsonPath('leaf_changed', true);

        $this->assertSame(2, LeafDetection::count());
    }

    public function test_visually_similar_webcam_frame_skips_duplicate_but_saves_when_second_condition_appears(): void
    {
        Storage::fake('public');

        $healthyPayload = [
            'model_id' => 'yolov8n-chili',
            'model' => 'yolov8n_chili.onnx',
            'image_width' => 640,
            'image_height' => 480,
            'count' => 1,
            'primary_detection' => [
                'class_id' => 3,
                'class_name' => 'daun_sehat',
                'confidence' => 0.91,
                'bbox_xyxy' => [10, 10, 120, 120],
            ],
            'detections' => [
                [
                    'class_id' => 3,
                    'class_name' => 'daun_sehat',
                    'confidence' => 0.91,
                    'bbox_xyxy' => [10, 10, 120, 120],
                ],
            ],
        ];

        $mixedPayload = [
            'model_id' => 'yolov8n-chili',
            'model' => 'yolov8n_chili.onnx',
            'image_width' => 640,
            'image_height' => 480,
            'count' => 2,
            'primary_detection' => [
                'class_id' => 3,
                'class_name' => 'daun_sehat',
                'confidence' => 0.93,
                'bbox_xyxy' => [10, 10, 120, 120],
            ],
            'detections' => [
                [
                    'class_id' => 3,
                    'class_name' => 'daun_sehat',
                    'confidence' => 0.93,
                    'bbox_xyxy' => [10, 10, 120, 120],
                ],
                [
                    'class_id' => 2,
                    'class_name' => 'bercak_putih',
                    'confidence' => 0.74,
                    'bbox_xyxy' => [160, 40, 260, 160],
                ],
            ],
        ];

        $this->configureFakeInference([$healthyPayload, $healthyPayload, $mixedPayload]);

        $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->makePatternPng('webcam.jpg', 'left_white', 0),
        ])->assertOk()->assertJsonPath('saved_to_database', true);

        $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->makePatternPng('webcam.jpg', 'left_white', 1),
        ])->assertOk()->assertJsonPath('saved_to_database', false);

        $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.25', [
            'file' => $this->makePatternPng('webcam.jpg', 'left_white', 2),
        ])->assertOk()
            ->assertJsonPath('saved_to_database', true)
            ->assertJsonPath('primary_detection.class_name', 'Bercak Putih');

        $this->assertSame(2, LeafDetection::count());
    }
}
