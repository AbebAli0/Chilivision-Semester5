<?php

namespace Tests\Feature;

use App\Models\ChatbotMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatbotPhotoDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngUpload(string $filename): UploadedFile
    {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($filename, $pngBytes);
    }

    public function test_photo_sent_from_telegram_or_phone_appears_in_chatbot_with_image_url(): void
    {
        Storage::fake('public');
        Http::fake();

        $file = $this->tinyPngUpload('telegram_leaf.png');

        $response = $this->post('/api/leaf-image?model_id=cnn-5ch-chili&threshold=0.10', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $imageUrl = $response->json('image_url');
        $this->assertNotEmpty($imageUrl);
        $this->assertStringStartsWith('/storage/', $imageUrl);

        $userPhotoMessage = ChatbotMessage::where('intent', 'user_photo')->latest('id')->first();
        $this->assertNotNull($userPhotoMessage);
        $this->assertSame($imageUrl, $userPhotoMessage->metadata_json['image_url'] ?? null);
        $this->assertSame('telegram', $userPhotoMessage->metadata_json['source'] ?? null);

        $relativePath = ltrim(str_replace('/storage/', '', $imageUrl), '/');
        Storage::disk('public')->assertExists($relativePath);

        $this->getJson('/api/dashboard/state')
            ->assertOk()
            ->assertJsonFragment(['intent' => 'user_photo']);
    }

    public function test_undetected_manual_photo_still_appears_in_chatbot_without_duplicating_leaf_db(): void
    {
        Storage::fake('public');
        Http::fake();

        $file = $this->tinyPngUpload('telegram_blank.png');

        $response = $this->post('/api/leaf-image?model_id=yolov8n-chili&threshold=0.95', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('saved_to_database', false);

        $imageUrl = $response->json('image_url');
        $this->assertNotEmpty($imageUrl);

        $userPhotoMessage = ChatbotMessage::where('intent', 'user_photo')->latest('id')->first();
        $this->assertNotNull($userPhotoMessage);
        $this->assertSame($imageUrl, $userPhotoMessage->metadata_json['image_url'] ?? null);

        $botMessage = ChatbotMessage::where('intent', 'leaf_not_detected')->latest('id')->first();
        $this->assertNotNull($botMessage);
    }
}
