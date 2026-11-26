<?php

namespace App\Http\Controllers;

use App\Models\ChatbotMessage;
use App\Models\Dht11Reading;
use App\Models\LeafDetection;
use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use App\Services\GeminiChatService;
use App\Services\LeafInferenceService;
use App\Services\PesticideScheduleService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChiliVisionApiController extends Controller
{
    public function __construct(
        private readonly LeafInferenceService $leafInference,
        private readonly PesticideScheduleService $pesticideSchedule,
        private readonly GeminiChatService $geminiChat,
    ) {}

    /**
     * Daftar definisi model Computer Vision yang tersedia di folder models/.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getModelDefinitions(): array
    {
        return $this->leafInference->getModelDefinitions();
    }

    /**
     * Rekomendasi penanganan berdasarkan label penyakit daun cabai.
     */
    private function getLeafRecommendation(string $className): string
    {
        return $this->leafInference->getLeafRecommendation($className);
    }

    /**
     * Normalisasi label penyakit pada model LeafDetection sebelum dikirim ke API/UI.
     */
    private function formatLeafDetectionRecord(?LeafDetection $leaf): ?LeafDetection
    {
        if (! $leaf) {
            return null;
        }

        $leaf->class_name = $this->leafInference->formatClassName((string) $leaf->class_name);
        if (is_array($leaf->detections_json)) {
            $detections = $leaf->detections_json;
            foreach ($detections as $idx => $det) {
                if (is_array($det) && isset($det['class_name'])) {
                    $detections[$idx]['class_name'] = $this->leafInference->formatClassName((string) $det['class_name']);
                }
            }
            $leaf->detections_json = $detections;
        }

        return $leaf;
    }

    /**
     * Cek apakah label hasil deteksi termasuk kondisi penyakit daun.
     */
    private function isDiseaseClass(string $className): bool
    {
        $trimmed = trim($className);
        if ($trimmed === '' || $trimmed === 'Tidak Terdeteksi' || $trimmed === 'Belum ada deteksi') {
            return false;
        }

        return ! $this->leafInference->isHealthyClassName($trimmed)
            && ! $this->leafInference->isNonLeafClassName($trimmed);
    }

    /**
     * Hitung 64-bit average perceptual hash (16 hex chars) untuk perbandingan kemiripan gambar daun.
     */
    private function computePerceptualHash(string $rawBytes): string
    {
        return $this->leafInference->computePerceptualHash($rawBytes);
    }

    /**
     * Hitung Hamming distance antara dua 16-char hex perceptual hash.
     */
    private function hammingDistance(string $hash1, string $hash2): int
    {
        return $this->leafInference->hammingDistance($hash1, $hash2);
    }

    /**
     * Format objek SprayHistory agar informatif
     */
    private function formatSprayHistory(?SprayHistory $spray): ?array
    {
        if (! $spray) {
            return null;
        }
        $sprayedAt = $spray->sprayed_at ? Carbon::parse($spray->sprayed_at)->timezone('Asia/Jakarta') : null;

        return [
            'id' => $spray->id,
            'type' => $spray->type,
            'type_label' => $spray->type === 'pesticide' ? 'Pestisida' : 'Air',
            'trigger_mode' => $spray->trigger_mode,
            'triggered_by' => $spray->triggered_by,
            'duration_seconds' => $spray->duration_seconds,
            'status' => $spray->status,
            'leaf_condition' => $spray->leaf_condition !== null
                ? $this->leafInference->formatClassName((string) $spray->leaf_condition)
                : null,
            'soil_moisture' => $spray->soil_moisture,
            'notes' => $spray->notes,
            'sprayed_at' => $sprayedAt ? $sprayedAt->toIso8601String() : null,
            'sprayed_at_formatted' => $sprayedAt ? $sprayedAt->format('d M Y, H:i:s').' WIB' : null,
            'time_ago' => $sprayedAt ? $sprayedAt->diffForHumans() : null,
        ];
    }

    private function getPesticideScheduleIntervalHours(): int
    {
        return $this->pesticideSchedule->getIntervalHours();
    }

    private function clearPesticideTargetSchedule(): void
    {
        $this->pesticideSchedule->clearTarget();
    }

    /**
     * @return array{ok: bool, error?: string, schedule?: array<string, mixed>}
     */
    private function applyPesticideTargetSchedule(string $datetimeInput, int $duration = 5): array
    {
        return $this->pesticideSchedule->applyTargetDatetime(
            $datetimeInput,
            $duration,
            fn (?SprayHistory $s) => $this->formatSprayHistory($s),
            fn (string $msg) => $this->notifyTelegramIfConfigured($msg),
        );
    }

    private function formatScheduleIntervalLabel(int $hours): string
    {
        return $this->pesticideSchedule->formatIntervalLabel($hours);
    }

    private function parseTargetScheduleDatetimeRequest(string $lower): ?Carbon
    {
        return $this->pesticideSchedule->parseTargetDatetimeRequest($lower);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseScheduleIntervalRequest(string $lower): ?array
    {
        return $this->pesticideSchedule->parseIntervalRequest($lower);
    }

    private function getAutoWaterThreshold(): int
    {
        return $this->geminiChat->getAutoWaterThreshold();
    }

    private function setAutoWaterThreshold(int $threshold): int
    {
        $clamped = max(1, min(95, $threshold));
        Cache::forever('auto_water_moisture_threshold', $clamped);

        return $clamped;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateTwoDayPesticideSchedule(): array
    {
        return $this->pesticideSchedule->evaluate(
            fn (?SprayHistory $s) => $this->formatSprayHistory($s),
            fn (string $msg) => $this->notifyTelegramIfConfigured($msg),
        );
    }

    /**
     * Ambil nilai terbaru dari .env (termasuk jika TELEGRAM_CHAT_ID baru ditulis oleh telegram_bot.py)
     */
    private function getTelegramConfig(): array
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        $envFile = base_path('.env');
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                if (str_starts_with($line, 'TELEGRAM_BOT_TOKEN=')) {
                    $val = trim(substr($line, strlen('TELEGRAM_BOT_TOKEN=')));
                    if ($val !== '') {
                        $botToken = $val;
                    }
                }
                if (str_starts_with($line, 'TELEGRAM_CHAT_ID=')) {
                    $val = trim(substr($line, strlen('TELEGRAM_CHAT_ID=')));
                    if ($val !== '') {
                        $chatId = $val;
                    }
                }
            }
        }

        return ['token' => $botToken, 'chat_id' => $chatId];
    }

    /**
     * Kirim pesan otomatis ke Telegram jika TELEGRAM_BOT_TOKEN & TELEGRAM_CHAT_ID diatur di .env
     */
    private function notifyTelegramIfConfigured(string $markdownText, ?string $overrideChatId = null, ?array $inlineKeyboard = null): void
    {
        $cfg = $this->getTelegramConfig();
        $botToken = $cfg['token'];
        $chatId = $overrideChatId ?: $cfg['chat_id'];
        if (! $botToken || ! $chatId) {
            return;
        }

        // Circuit breaker: jika Telegram baru saja timeout, lewati pengiriman agar request ESP32/web tidak tertahan
        if (Cache::has('telegram_unreachable')) {
            return;
        }

        try {
            $cleanText = str_replace('**', '*', $markdownText);
            if (mb_strlen($cleanText) > 4000) {
                $cleanText = mb_substr($cleanText, 0, 3997).'...';
            }
            $payload = [
                'chat_id' => $chatId,
                'text' => $cleanText,
                'parse_mode' => 'Markdown',
            ];
            if ($inlineKeyboard !== null) {
                $payload['reply_markup'] = [
                    'inline_keyboard' => $inlineKeyboard,
                ];
            }
            $telegramHttp = fn () => Http::withoutVerifying()
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->connectTimeout(3)
                ->timeout(6);

            $res = $telegramHttp()->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

            // Fallback tanpa parse_mode jika terdapat karakter underscore/simbol yang gagal di-parse Markdown Telegram
            if (! $res->successful()) {
                unset($payload['parse_mode']);
                $payload['text'] = str_replace(['*', '`', '_'], '', $cleanText);
                $fallbackRes = $telegramHttp()->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

                if (! $fallbackRes->successful()) {
                    Log::warning('Telegram sendMessage ditolak API Telegram.', [
                        'status' => $fallbackRes->status(),
                        'body' => mb_substr($fallbackRes->body(), 0, 300),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Cache::put('telegram_unreachable', true, now()->addMinutes(2));
            $safeMessage = preg_replace('/\/bot[^\/\s]+/', '/bot<TOKEN>', $e->getMessage()) ?? $e->getMessage();
            Log::warning('Gagal mengirim notifikasi Telegram (dilewati 2 menit): '.$safeMessage);
        }
    }

    /**
     * Format pesan peringatan deteksi penyakit daun yang seragam untuk Chatbot Website & Telegram
     */
    private function buildDiseaseConfirmationMessage(string $className, float $confidence, string $modelId, bool $savedToDatabase): string
    {
        $confPct = round($confidence * 100, 1);
        $dbNote = $savedToDatabase
            ? 'Disimpan ke database TiDB Cloud (`leaf_detections`)'
            : 'Kondisi daun sama dengan deteksi terakhir (gambar tidak diduplikasi ke database)';

        return "⚠️ **PERINGATAN DETEKSI PENYAKIT DAUN CABAI!**\n\n"
            ."• Penyakit Terdeteksi: **{$className}**\n"
            ."• Tingkat Keyakinan (Confidence): **{$confPct}%**\n"
            ."• Model AI: `{$modelId}`\n"
            ."• Status Database: {$dbNote}\n\n"
            ."🛡️ Pompa pestisida TIDAK dinyalakan otomatis.\n"
            ."❓ **Apakah Anda ingin menyemprotkan pestisida sekarang?**\n"
            .'Silakan tekan tombol konfirmasi di bawah atau balas **ya** / **tidak**:';
    }

    /**
     * Kirim notifikasi ke Telegram saat penyakit daun terdeteksi untuk meminta konfirmasi semprot pestisida
     */
    private function sendTelegramDiseaseConfirmationPrompt(string $className, float $confidence, string $modelId, bool $savedToDatabase = true): void
    {
        $text = $this->buildDiseaseConfirmationMessage($className, $confidence, $modelId, $savedToDatabase);

        $inlineKeyboard = [
            [
                ['text' => '✅ Ya, Semprot Pestisida (5s)', 'callback_data' => 'confirm_pesticide_yes'],
                ['text' => '❌ Tidak, Jangan Semprot', 'callback_data' => 'confirm_pesticide_no'],
            ],
        ];

        $this->notifyTelegramIfConfigured($text, null, $inlineKeyboard);
    }

    /**
     * GET /api/health & GET /health
     */
    public function health(): JsonResponse
    {
        $defs = $this->getModelDefinitions();
        $available = [];
        foreach ($defs as $key => $def) {
            if (is_file($def['path'])) {
                $available[] = $key;
            }
        }

        $dbLabel = str_contains((string) config('database.connections.mysql.host'), 'tidbcloud') ? 'TiDB Cloud' : 'mysql';

        return response()->json([
            'status' => 'ok',
            'framework' => 'Laravel '.app()->version(),
            'database' => $dbLabel.' ('.config('database.connections.mysql.database').')',
            'models' => count($defs),
            'available_models' => $available,
            'system_services' => $this->getSystemServicesStatus(),
            'timestamp' => now('Asia/Jakarta')->toIso8601String(),
        ]);
    }

    /**
     * GET /api/models & GET /models
     */
    public function models(): JsonResponse
    {
        $defs = $this->getModelDefinitions();
        $result = [];
        foreach ($defs as $def) {
            $result[] = [
                'id' => $def['id'],
                'name' => $def['name'],
                'filename' => $def['filename'],
                'default_threshold' => $def['default_threshold'],
                'available' => is_file($def['path']),
            ];
        }

        return response()->json($result);
    }

    private function getSelectedTelegramModelId(): string
    {
        return $this->leafInference->getSelectedModelId();
    }

    /**
     * GET /api/telegram/model
     * Daftar model beserta model yang sedang dipilih di Telegram Bot
     */
    public function getTelegramModel(): JsonResponse
    {
        $selectedId = $this->getSelectedTelegramModelId();
        $models = [];
        foreach ($this->getModelDefinitions() as $def) {
            $models[] = [
                'id' => $def['id'],
                'name' => $def['name'],
                'available' => is_file($def['path']),
                'selected' => $def['id'] === $selectedId,
            ];
        }

        return response()->json([
            'status' => 'success',
            'selected_model_id' => $selectedId,
            'models' => $models,
        ]);
    }

    /**
     * POST /api/telegram/model
     * Pilih model yang dipakai untuk analisis foto daun dari Telegram Bot
     */
    public function setTelegramModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_id' => 'required|string|max:100',
        ]);

        $defs = $this->getModelDefinitions();
        $modelId = $validated['model_id'];

        if (! isset($defs[$modelId])) {
            return response()->json([
                'status' => 'error',
                'message' => "Model '{$modelId}' tidak terdaftar.",
                'available_model_ids' => array_keys($defs),
            ], 404);
        }

        if (! is_file($defs[$modelId]['path'])) {
            return response()->json([
                'status' => 'error',
                'message' => "File model '{$defs[$modelId]['filename']}' belum tersedia di folder models/.",
            ], 422);
        }

        Cache::forever('telegram_selected_model', $modelId);
        Cache::forever('selected_model_version', ((int) Cache::get('selected_model_version', 0)) + 1);

        return response()->json([
            'status' => 'success',
            'message' => "Model Telegram Bot diganti ke {$defs[$modelId]['name']}.",
            'selected_model_id' => $modelId,
            'selected_model_name' => $defs[$modelId]['name'],
        ]);
    }

    private function resolveModelFromChatText(string $lower): ?string
    {
        return $this->leafInference->resolveModelFromChatText($lower);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChatModelOptions(string $selectedId): array
    {
        return $this->leafInference->buildChatModelOptions($selectedId);
    }

    /**
     * Bangun struktur inline keyboard Telegram untuk daftar pilihan model AI.
     *
     * @param  array<int, array<string, mixed>>  $models
     * @return array<int, array<int, array<string, string>>>|null
     */
    private function buildModelInlineKeyboard(array $models): ?array
    {
        $keyboard = [];
        foreach ($models as $opt) {
            if (! ($opt['available'] ?? false)) {
                continue;
            }
            $mark = ($opt['selected'] ?? false) ? '✅ ' : '';
            $keyboard[] = [[
                'text' => $mark.(string) $opt['name'],
                'callback_data' => 'select_model:'.(string) $opt['id'],
            ]];
        }

        return $keyboard ?: null;
    }

    /**
     * GET /api/cameras & GET /cameras
     */
    public function cameras(): JsonResponse
    {
        return response()->json([
            ['index' => 0, 'name' => 'Kamera 0 (Default Webcam)'],
        ]);
    }

    /**
     * Katalog terstruktur seluruh grup & endpoint REST API ChiliVision
     *
     * @return array<int, array<string, mixed>>
     */
    private function getApiCatalog(): array
    {
        return [
            [
                'group_number' => 0,
                'group_key' => '0_info_health_dashboard',
                'title' => '0. Info, Health & Ringkasan Dashboard',
                'icon' => '⚡',
                'note' => 'Endpoint utilitas untuk memeriksa status server, ketersediaan model AI ONNX, daftar kamera, serta ringkasan real-time seluruh state sistem.',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api',
                        'aliases' => ['/api?format=json'],
                        'handler' => 'ChiliVisionApiController@apiInfo',
                        'description' => 'Menampilkan halaman dokumentasi interaktif REST API (di browser) atau spesifikasi JSON lengkap (?format=json).',
                        'parameters' => 'Query opsional: format=json (untuk paksa output raw JSON).',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/health',
                        'aliases' => ['/health'],
                        'handler' => 'ChiliVisionApiController@health',
                        'description' => 'Status server Laravel, koneksi database TiDB Cloud, daftar model AI aktif, dan ringkasan system_services (FastAPI warm worker / CLI fallback, Gemini AI, Telegram Bot, Scheduler).',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/models',
                        'aliases' => ['/models'],
                        'handler' => 'ChiliVisionApiController@models (via LeafInferenceService)',
                        'description' => 'Daftar model AI Computer Vision (yolov8n-chili, cnn-5ch-chili) beserta status file model.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/telegram/model',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@getTelegramModel',
                        'description' => 'Mengambil model AI Computer Vision yang sedang dipilih secara persisten untuk Telegram Bot & Web.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/telegram/model',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@setTelegramModel',
                        'description' => 'Mengubah model AI aktif untuk deteksi foto daun (yolov8n-chili atau cnn-5ch-chili).',
                        'parameters' => "JSON / Form:\n• model_id (wajib): ID model dari /api/models",
                        'sample_payload' => [
                            'model_id' => 'yolov8n-chili',
                        ],
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/cameras',
                        'aliases' => ['/cameras'],
                        'handler' => 'ChiliVisionApiController@cameras',
                        'description' => 'Daftar sumber kamera default yang terdaftar.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/dashboard/state',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@dashboardState',
                        'description' => 'Mengambil seluruh state real-time sekaligus (deteksi daun, soil moisture, DHT11, histori semprot, jadwal pestisida, status ESP32, system_services, dan pesan chatbot).',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                ],
            ],
            [
                'group_number' => 1,
                'group_key' => '1_gambar_daun',
                'title' => '1. API Gambar Daun & Deteksi Penyakit (Computer Vision)',
                'icon' => '🍃',
                'note' => 'Inferensi berjalan melalui LeafInferenceService (FastAPI warm worker port 8001 dengan circuit breaker + fallback otomatis ke CLI Python). Jika visual daun tidak berubah, gambar tidak diduplikasi ke TiDB Cloud kecuali force_save=true.',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/leaf-image',
                        'aliases' => ['/api/gambar-daun'],
                        'handler' => 'ChiliVisionApiController@getLatestLeafImage',
                        'description' => 'Mengambil deteksi daun terakhir beserta 10 riwayat deteksi terbaru dari tabel leaf_detections.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/leaf-image',
                        'aliases' => ['/api/gambar-daun', '/api/predict', '/predict'],
                        'handler' => 'ChiliVisionApiController@predictAndStoreLeaf -> LeafInferenceService',
                        'description' => 'Menjalankan inferensi AI (mengembalikan field inference_backend: fastapi|cli) pada foto daun cabai & menyimpan ke database jika kondisi daun berubah.',
                        'parameters' => "multipart/form-data:\n• file atau image (wajib, file gambar JPG/PNG/WEBP)\n• model_id (opsional, default: model terpilih / 'yolov8n-chili')\n• threshold (opsional, 0.10–0.95; default: 0.25 untuk YOLOv8n, 0.50 untuk CNN)\n• force_save (opsional, boolean)",
                        'sample_payload' => null,
                    ],
                ],
            ],
            [
                'group_number' => 2,
                'group_key' => '2_sensor_soil_moisture',
                'title' => '2. API Sensor Soil Moisture (Kelembapan Tanah)',
                'icon' => '🌱',
                'note' => 'Alur: web > chatbot > database. Jika kelembapan tanah < 30% (Kering), sistem otomatis menjadwalkan penyemprotan air selama 5 detik.',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/sensor/soil-moisture',
                        'aliases' => ['/api/soil-moisture'],
                        'handler' => 'ChiliVisionApiController@getLatestSoilMoisture',
                        'description' => 'Mengambil pembacaan kelembapan tanah terakhir & 15 riwayat terbaru dari tabel soil_moisture_readings.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sensor/soil-moisture',
                        'aliases' => ['/api/soil-moisture'],
                        'handler' => 'ChiliVisionApiController@storeSoilMoisture',
                        'description' => 'Mengirim pembacaan kelembapan tanah baru ke Web, meneruskan notifikasi ke Chatbot/Telegram, dan menyimpan ke TiDB Cloud.',
                        'parameters' => "JSON / Form:\n• moisture_percent (atau moisture / value): 0–100 (wajib)\n• adc_value (opsional, integer)\n• source (opsional, default: 'api'; isi 'esp32' untuk mencatat heartbeat hardware)",
                        'sample_payload' => [
                            'moisture_percent' => 45.5,
                            'adc_value' => 2950,
                            'source' => 'api_docs_test',
                        ],
                    ],
                ],
            ],
            [
                'group_number' => 3,
                'group_key' => '3_sensor_dht11',
                'title' => '3. API Sensor DHT22 (Suhu & Kelembapan Udara)',
                'icon' => '🌡️',
                'note' => 'Alur: web > chatbot > database. Menghitung heat index otomatis dan memberikan rekomendasi kondisi mikroklimat cabai.',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/sensor/dht22',
                        'aliases' => ['/api/dht22', '/api/sensor/dht11', '/api/dht11'],
                        'handler' => 'ChiliVisionApiController@getLatestDht11',
                        'description' => 'Mengambil pembacaan suhu & kelembapan udara terakhir beserta 15 riwayat terbaru dari sensor DHT22.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sensor/dht22',
                        'aliases' => ['/api/dht22', '/api/sensor/dht11', '/api/dht11'],
                        'handler' => 'ChiliVisionApiController@storeDht11',
                        'description' => 'Mengirim pembacaan suhu & kelembapan udara DHT22 baru ke Web, meneruskan notifikasi ke Chatbot/Telegram, dan menyimpan ke TiDB Cloud.',
                        'parameters' => "JSON / Form:\n• temperature (atau suhu): -10 s/d 80 (wajib)\n• humidity (atau kelembapan_udara): 0–100 (wajib)\n• source (opsional, default: 'api'; isi 'esp32' untuk mencatat heartbeat hardware)",
                        'sample_payload' => [
                            'temperature' => 29.8,
                            'humidity' => 66.0,
                            'source' => 'api_docs_test',
                        ],
                    ],
                ],
            ],
            [
                'group_number' => 4,
                'group_key' => '4_histori_penyemprotan_pestisida',
                'title' => '4. API Histori & Jadwal Penyemprotan Pestisida',
                'icon' => '🧪',
                'note' => 'Dikelola oleh PesticideScheduleService: menyimpan histori semprot serta mengevaluasi interval fleksibel dan jadwal target tanggal/jam spesifik (dipicu otomatis oleh scheduler background).',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/spray/pesticide/history',
                        'aliases' => ['/api/histori-pestisida'],
                        'handler' => 'ChiliVisionApiController@getPesticideSprayHistory -> PesticideScheduleService',
                        'description' => 'Mengambil waktu terakhir pestisida disemprot, info interval/jadwal target tanggal spesifik, dan 20 riwayat terakhir.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/spray/pesticide/history',
                        'aliases' => ['/api/histori-pestisida'],
                        'handler' => 'ChiliVisionApiController@storePesticideSprayHistory',
                        'description' => 'Mencatat log histori waktu penyemprotan pestisida ke tabel spray_histories.',
                        'parameters' => "JSON / Form (opsional semua):\n• trigger_mode, triggered_by, duration_seconds, leaf_condition, notes, sprayed_at",
                        'sample_payload' => [
                            'trigger_mode' => 'manual',
                            'triggered_by' => 'api_docs_test',
                            'duration_seconds' => 5,
                            'notes' => 'Pencatatan histori pestisida dari halaman dokumentasi API.',
                        ],
                    ],
                ],
            ],
            [
                'group_number' => 5,
                'group_key' => '5_histori_penyemprotan_air',
                'title' => '5. API Histori Penyemprotan Air',
                'icon' => '💧',
                'note' => 'Menyimpan dan menampilkan waktu terakhir penyemprotan air dari tabel spray_histories.',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/spray/water/history',
                        'aliases' => ['/api/histori-air'],
                        'handler' => 'ChiliVisionApiController@getWaterSprayHistory',
                        'description' => 'Mengambil waktu terakhir air disemprot dan 20 riwayat penyemprotan air terakhir.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/spray/water/history',
                        'aliases' => ['/api/histori-air'],
                        'handler' => 'ChiliVisionApiController@storeWaterSprayHistory',
                        'description' => 'Mencatat log histori waktu penyemprotan air ke tabel spray_histories.',
                        'parameters' => "JSON / Form (opsional semua):\n• trigger_mode, triggered_by, duration_seconds, soil_moisture, notes, sprayed_at",
                        'sample_payload' => [
                            'trigger_mode' => 'manual',
                            'triggered_by' => 'api_docs_test',
                            'duration_seconds' => 5,
                            'soil_moisture' => 42.0,
                            'notes' => 'Pencatatan histori air dari halaman dokumentasi API.',
                        ],
                    ],
                ],
            ],
            [
                'group_number' => 6,
                'group_key' => '6_penyemprotan_manual',
                'title' => '6. API Penyemprotan Manual (Air & Pestisida)',
                'icon' => '💦',
                'note' => 'Digunakan oleh tombol di Web Dashboard maupun perintah manual untuk menyalakan pompa air/pestisida dan memasukkannya ke antrean ESP32.',
                'items' => [
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/spray/manual',
                        'aliases' => ['/api/penyemprotan-manual'],
                        'handler' => 'ChiliVisionApiController@triggerManualSpray',
                        'description' => 'Memicu penyemprotan manual air atau pestisida, mencatat ke histori, dan memberi tahu Chatbot/Telegram.',
                        'parameters' => "JSON / Form:\n• type (atau jenis): 'water' / 'air' atau 'pesticide' / 'pestisida'\n• duration_seconds (opsional, 1–60 detik, default: 5)\n• triggered_by / source (opsional, default: 'web')\n• notes (opsional)",
                        'sample_payload' => [
                            'type' => 'water',
                            'duration_seconds' => 5,
                            'triggered_by' => 'web',
                            'notes' => 'Uji semprot manual dari dokumentasi API',
                        ],
                    ],
                ],
            ],
            [
                'group_number' => 7,
                'group_key' => '7_chatbot_esp32_telegram',
                'title' => '7. API Chatbot AI (Gemini), Telegram Bot & Hardware ESP32',
                'icon' => '🤖',
                'note' => 'Sinkronisasi 2 arah antara Chatbot Website (GeminiChatService + fast-path command), Bot Telegram, monitoring heartbeat ESP32, dan antrean polling perintah pompa.',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/chatbot/messages',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@getChatbotMessages',
                        'description' => 'Mengambil 40 pesan percakapan chatbot terakhir dari tabel chatbot_messages.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/chatbot/message',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@sendChatbotMessage -> GeminiChatService',
                        'description' => 'Mengirim pesan atau perintah ke chatbot. Mendukung /status, /semprot_air, /semprot_pestisida, ubah jadwal interval atau tanggal/jam spesifik, ganti model AI, serta tanya jawab bebas via Gemini AI.',
                        'parameters' => "JSON / Form:\n• message (wajib, string maks 1000 karakter)\n• source (opsional, 'web' atau 'telegram')",
                        'sample_payload' => [
                            'message' => '/status',
                            'source' => 'web',
                        ],
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/esp32/poll',
                        'aliases' => ['/api/spray/pending'],
                        'handler' => 'ChiliVisionApiController@esp32PollCommands',
                        'description' => 'Di-polling oleh mikrokontroler ESP32 untuk mencatat heartbeat online, mengecek antrean perintah nyala pompa (spray_water, spray_pesticide beserta durasinya), dan menandainya completed.',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/hardware/esp32-status',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@getHardwareStatus',
                        'description' => 'Memeriksa status koneksi heartbeat ESP32 terbaru (never_connected, online, offline beserta selisih detik terakhir aktif).',
                        'parameters' => 'Tidak ada parameter.',
                        'sample_payload' => null,
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/telegram/webhook',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@telegramWebhook',
                        'description' => 'Endpoint webhook untuk menerima pesan masuk dari Bot Telegram dan membalasnya secara otomatis.',
                        'parameters' => "Payload JSON standar Telegram Update:\n• message.chat.id\n• message.text",
                        'sample_payload' => [
                            'message' => [
                                'chat' => ['id' => 123456789],
                                'text' => '/status',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'group_number' => 8,
                'group_key' => '8_laporan_ekspor_csv',
                'title' => '8. API Laporan & Ekspor Data CSV',
                'icon' => '📊',
                'note' => 'Menyediakan unduhan laporan data historis siap buka di Excel/Spreadsheet (UTF-8 BOM CSV).',
                'items' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/reports/export/{type}',
                        'aliases' => [],
                        'handler' => 'ChiliVisionApiController@exportCsvReport',
                        'description' => 'Mengekspor data historis ke format CSV berdasarkan tipe laporan.',
                        'parameters' => "Path parameter {type} wajib salah satu dari:\n• soil (kelembapan tanah)\n• dht11 (suhu & kelembapan udara)\n• leaf (riwayat deteksi daun)\n• spray (riwayat penyemprotan air & pestisida)",
                        'sample_payload' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * GET /api (Tampilan UI Dokumentasi Lengkap di Browser atau JSON API Spec)
     */
    public function apiInfo(Request $request): JsonResponse|View
    {
        $catalog = $this->getApiCatalog();
        $defs = $this->getModelDefinitions();
        $availableModels = [];
        foreach ($defs as $key => $def) {
            if (is_file($def['path'])) {
                $availableModels[] = $key;
            }
        }

        $totalEndpoints = 0;
        $totalRoutesWithAliases = 0;
        foreach ($catalog as $group) {
            foreach ($group['items'] as $item) {
                $totalEndpoints++;
                $totalRoutesWithAliases += 1 + count($item['aliases']);
            }
        }

        $systemServices = $this->getSystemServicesStatus();

        $payload = [
            'name' => 'ChiliVision Laravel Smart Crop & Computer Vision API',
            'version' => '3.1.0-laravel-modular',
            'framework' => 'Laravel '.app()->version(),
            'database' => config('database.connections.mysql.database'),
            'architecture' => [
                'controller' => 'ChiliVisionApiController',
                'services' => [
                    'LeafInferenceService',
                    'PesticideScheduleService',
                    'GeminiChatService',
                ],
                'scheduler_command' => 'chilivision:check-schedule',
            ],
            'system_services' => $systemServices,
            'base_url' => url('/api'),
            'total_groups' => count($catalog),
            'total_primary_endpoints' => $totalEndpoints,
            'total_routes_including_aliases' => $totalRoutesWithAliases,
            'available_models' => $availableModels,
            'timestamp' => now('Asia/Jakarta')->toIso8601String(),
            'groups' => $catalog,
            'endpoints' => [
                '0_info_health_dashboard' => [
                    'api_info' => 'GET /api (HTML UI) | GET /api?format=json (JSON)',
                    'health' => 'GET /api/health (alias: GET /health)',
                    'models' => 'GET /api/models (alias: GET /models)',
                    'telegram_model_get' => 'GET /api/telegram/model',
                    'telegram_model_set' => 'POST /api/telegram/model',
                    'cameras' => 'GET /api/cameras (alias: GET /cameras)',
                    'dashboard_state' => 'GET /api/dashboard/state',
                ],
                '1_gambar_daun' => [
                    'description' => 'Deteksi terakhir daun (LeafInferenceService: FastAPI warm + CLI fallback), jika daun tidak berubah gambar tidak dikirim ke database',
                    'get_latest' => 'GET /api/leaf-image (alias: GET /api/gambar-daun)',
                    'post_detect' => 'POST /api/leaf-image (alias: POST /api/gambar-daun, POST /api/predict, POST /predict)',
                ],
                '2_sensor_soil_moisture' => [
                    'description' => 'Mengirim Data Terakhir ke web > chatbot > database',
                    'get_latest' => 'GET /api/sensor/soil-moisture (alias: GET /api/soil-moisture)',
                    'post_data' => 'POST /api/sensor/soil-moisture (alias: POST /api/soil-moisture)',
                ],
                '3_sensor_dht11' => [
                    'description' => 'Mengirim Data Terakhir ke web > chatbot > database',
                    'get_latest' => 'GET /api/sensor/dht11 (alias: GET /api/dht11)',
                    'post_data' => 'POST /api/sensor/dht11 (alias: POST /api/dht11)',
                ],
                '4_histori_penyemprotan_pestisida' => [
                    'description' => 'Menyimpan waktu terakhir pestisida disemprot & jadwal target tanggal/interval (PesticideScheduleService)',
                    'get_history' => 'GET /api/spray/pesticide/history (alias: GET /api/histori-pestisida)',
                    'post_history' => 'POST /api/spray/pesticide/history (alias: POST /api/histori-pestisida)',
                ],
                '5_histori_penyemprotan_air' => [
                    'description' => 'Menyimpan waktu terakhir air disemprot',
                    'get_history' => 'GET /api/spray/water/history (alias: GET /api/histori-air)',
                    'post_history' => 'POST /api/spray/water/history (alias: POST /api/histori-air)',
                ],
                '6_penyemprotan_manual' => [
                    'description' => 'Implementasi Tombol di web dan command di chatbot untuk menyemprot air atau pestisida secara manual',
                    'post_manual_spray' => 'POST /api/spray/manual (alias: POST /api/penyemprotan-manual)',
                ],
                '7_chatbot_esp32_telegram' => [
                    'get_messages' => 'GET /api/chatbot/messages',
                    'post_message' => 'POST /api/chatbot/message (GeminiChatService)',
                    'esp32_poll' => 'GET /api/esp32/poll (alias: GET /api/spray/pending)',
                    'esp32_status' => 'GET /api/hardware/esp32-status',
                    'telegram_webhook' => 'POST /api/telegram/webhook',
                ],
                '8_laporan_ekspor_csv' => [
                    'export_csv' => 'GET /api/reports/export/{type} (soil | dht11 | leaf | spray)',
                ],
            ],
        ];

        if ($request->query('format') === 'json' || $request->expectsJson()) {
            return response()->json($payload);
        }

        return view('api-docs', $payload);
    }

    /**
     * =========================================================================
     * 1. API GAMBAR DAUN
     * Deteksi terakhir daun, jika daun tidak berubah gambar tidak dikirim ke database
     * =========================================================================
     */
    public function getLatestLeafImage(): JsonResponse
    {
        $latest = $this->formatLeafDetectionRecord(LeafDetection::latest('id')->first());
        $history = LeafDetection::latest('id')->take(10)->get()->map(fn (LeafDetection $item) => $this->formatLeafDetectionRecord($item));

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Gambar Daun',
            'rule' => 'Deteksi terakhir daun, jika daun tidak berubah gambar tidak dikirim ke database',
            'latest_detection' => $latest,
            'total_saved_in_database' => LeafDetection::count(),
            'recent_detections' => $history,
        ]);
    }

    /**
     * POST /api/leaf-image & POST /api/predict & POST /predict
     * Melakukan deteksi daun & mengecek apakah daun berubah sebelum menyimpan ke MySQL
     */
    public function predictAndStoreLeaf(Request $request): JsonResponse
    {
        $modelId = $request->query('model_id', $request->input('model_id', 'yolov8n-chili'));
        $forceSave = filter_var($request->query('force_save', $request->input('force_save', false)), FILTER_VALIDATE_BOOLEAN);

        $defs = $this->getModelDefinitions();
        if (! isset($defs[$modelId])) {
            return response()->json(['detail' => "Model '{$modelId}' tidak terdaftar."], 404);
        }
        $definition = $defs[$modelId];
        $defaultThreshold = (float) ($definition['default_threshold'] ?? 0.45);
        $rawThreshold = $request->query('threshold', $request->input('threshold'));
        $threshold = ($rawThreshold !== null && $rawThreshold !== '')
            ? max(0.10, min(0.95, (float) $rawThreshold))
            : $defaultThreshold;

        if (! $request->hasFile('file') && ! $request->hasFile('image')) {
            return response()->json(['detail' => 'File gambar (field: file atau image) wajib dikirim.'], 422);
        }

        $uploadedFile = $request->file('file') ?? $request->file('image');
        $rawBytes = file_get_contents($uploadedFile->getRealPath());
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            return response()->json(['detail' => 'File gambar kosong atau tidak valid.'], 400);
        }

        $imageHash = hash('sha256', $rawBytes);
        $perceptualHash = $this->computePerceptualHash($rawBytes);

        // Jalankan inferensi Computer Vision (via service Python lokal port 8001 atau CLI fallback)
        $inferenceResult = $this->runPythonInference($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalName(), $modelId, $threshold);
        if (isset($inferenceResult['error'])) {
            return response()->json(['detail' => $inferenceResult['error']], 503);
        }

        $detections = $inferenceResult['detections'] ?? [];
        $classification = $inferenceResult['classification'] ?? null;
        $primary = $inferenceResult['primary_detection'] ?? null;

        if ($classification && ! $primary) {
            $primary = $classification;
        }

        $detectedClassName = $this->leafInference->formatClassName((string) ($primary['class_name'] ?? ($request->input('class_name') ?? 'Tidak Terdeteksi')));
        $detectedClassId = $primary['class_id'] ?? null;
        $detectedConfidence = isset($primary['confidence']) ? (float) $primary['confidence'] : 0.0;
        $detectionCount = (int) ($inferenceResult['count'] ?? count($detections));
        $imageWidth = (int) ($inferenceResult['image_width'] ?? 640);
        $imageHeight = (int) ($inferenceResult['image_height'] ?? 480);

        // Ambil deteksi daun terakhir dari database MySQL
        $lastDetection = LeafDetection::latest('id')->first();

        // Logika: "jika daun tidak berubah gambar tidak dikirim ke database"
        $leafChanged = true;
        $unchangedReason = null;

        if ($detectedClassName === 'Tidak Terdeteksi' && $detectionCount === 0 && ! $classification) {
            $leafChanged = false;
            $unchangedReason = 'Tidak ada daun/objek yang terdeteksi di atas threshold.';
        } elseif (! $forceSave && $lastDetection !== null) {
            $sameExactFile = ($lastDetection->image_hash === $imageHash);
            $hamming = ($lastDetection->perceptual_hash && $perceptualHash)
                ? $this->hammingDistance($lastDetection->perceptual_hash, $perceptualHash)
                : 64;
            $sameVisualLeaf = ($hamming <= 10);

            $currentConditions = $this->leafInference->extractDetectedConditionsSignature($detections, $primary);
            $lastDetections = is_array($lastDetection->detections_json) ? $lastDetection->detections_json : [];
            $lastConditions = $this->leafInference->extractDetectedConditionsSignature(
                $lastDetections,
                ['class_name' => (string) $lastDetection->class_name]
            );
            $sameLeafCondition = ($currentConditions === $lastConditions);

            if ($sameExactFile) {
                $leafChanged = false;
                $unchangedReason = 'File gambar identik dengan deteksi terakhir di database (hash sama).';
            } elseif ($sameVisualLeaf && $sameLeafCondition) {
                $leafChanged = false;
                $unchangedReason = "Gambar visual daun dan kondisi '{$detectedClassName}' tidak berubah (perceptual distance: {$hamming}).";
            }
        }

        $savedRecord = null;
        $savedToDatabase = false;
        $confPct = round($detectedConfidence * 100, 1);
        $isDisease = ! str_contains(mb_strtolower($detectedClassName), 'sehat')
            && $detectedClassName !== 'Tidak Terdeteksi';
        $originalName = strtolower((string) $uploadedFile->getClientOriginalName());
        $isLiveWebcamFrame = ($originalName === 'webcam.jpg');
        $isTelegramPhoto = str_starts_with($originalName, 'telegram_');
        $photoSource = $isTelegramPhoto ? 'telegram' : (string) $request->query('source', $request->input('source', 'web'));
        $ext = $uploadedFile->getClientOriginalExtension() ?: 'jpg';
        $recommendation = $this->getLeafRecommendation($detectedClassName);
        $chatImageUrl = null;

        if ($leafChanged) {
            // Simpan file gambar ke storage/app/public/leaves
            $filename = 'leaf_'.now('Asia/Jakarta')->format('Ymd_His').'_'.Str::random(6).'.'.$ext;
            $relativePath = 'leaves/'.$filename;
            Storage::disk('public')->put($relativePath, $rawBytes);
            $chatImageUrl = '/storage/'.$relativePath;

            $savedRecord = LeafDetection::create([
                'model_id' => $modelId,
                'model_name' => $definition['filename'],
                'class_id' => $detectedClassId,
                'class_name' => $detectedClassName,
                'confidence' => $detectedConfidence,
                'detection_count' => $detectionCount,
                'detections_json' => $detections,
                'image_path' => $relativePath,
                'image_hash' => $imageHash,
                'perceptual_hash' => $perceptualHash,
                'image_width' => $imageWidth,
                'image_height' => $imageHeight,
                'recommendation' => $recommendation,
            ]);
            $savedToDatabase = true;
        } elseif (! $isLiveWebcamFrame) {
            // Simpan preview foto untuk tampilan chatbot tanpa menduplikasi tabel leaf_detections
            $chatFilename = 'chat_'.now('Asia/Jakarta')->format('Ymd_His').'_'.Str::random(6).'.'.$ext;
            $chatRelativePath = 'chat_photos/'.$chatFilename;
            Storage::disk('public')->put($chatRelativePath, $rawBytes);
            $chatImageUrl = '/storage/'.$chatRelativePath;
        }

        if (! $isLiveWebcamFrame && $chatImageUrl !== null) {
            ChatbotMessage::create([
                'sender' => 'user',
                'message' => $isTelegramPhoto
                    ? '📸 Mengirim foto daun dari Telegram (HP)'
                    : '📸 Mengirim foto daun',
                'intent' => 'user_photo',
                'metadata_json' => [
                    'source' => $photoSource,
                    'image_url' => $chatImageUrl,
                    'model_id' => $modelId,
                ],
            ]);
        }

        if ($leafChanged) {
            // Kirim notifikasi perubahan kondisi daun yang identik ke Chatbot Website & Telegram
            if ($isDisease) {
                $promptText = $this->buildDiseaseConfirmationMessage($detectedClassName, $detectedConfidence, $modelId, true);
                ChatbotMessage::create([
                    'sender' => 'bot',
                    'message' => $promptText,
                    'intent' => 'ask_pesticide_confirmation',
                    'metadata_json' => [
                        'leaf_detection_id' => $savedRecord->id,
                        'class_name' => $detectedClassName,
                        'confidence' => $detectedConfidence,
                        'requires_confirmation' => true,
                        'confirmation_handled' => false,
                        'saved_to_database' => true,
                        'image_url' => $isLiveWebcamFrame ? $chatImageUrl : null,
                    ],
                ]);

                $this->sendTelegramDiseaseConfirmationPrompt($detectedClassName, $detectedConfidence, $modelId, true);
            } else {
                $healthyMsg = "🌿 **Update Deteksi Daun Baru**\n"
                    ."• Kondisi Daun: **{$detectedClassName}** (**{$confPct}%**)\n"
                    ."• Model AI: `{$modelId}`\n"
                    ."• Status Database: Disimpan ke database TiDB Cloud (`leaf_detections`)\n"
                    ."• Rekomendasi: {$recommendation}";
                ChatbotMessage::create([
                    'sender' => 'bot',
                    'message' => $healthyMsg,
                    'intent' => 'leaf_update',
                    'metadata_json' => [
                        'leaf_detection_id' => $savedRecord->id,
                        'class_name' => $detectedClassName,
                        'confidence' => $detectedConfidence,
                        'saved_to_database' => true,
                        'image_url' => $isLiveWebcamFrame ? $chatImageUrl : null,
                    ],
                ]);
                $this->notifyTelegramIfConfigured($healthyMsg);
            }
        } elseif (! $isLiveWebcamFrame && $detectedClassName !== 'Tidak Terdeteksi') {
            // Jika pengguna melakukan Upload Foto / Ambil Foto di Website atau Telegram
            // namun kondisi daun sama dengan deteksi terakhir, gambar tidak diduplikasi ke DB,
            // tetapi foto & hasil analisis tetap tampil di Chatbot Website & Telegram.
            if ($isDisease) {
                $promptText = $this->buildDiseaseConfirmationMessage($detectedClassName, $detectedConfidence, $modelId, false);
                ChatbotMessage::create([
                    'sender' => 'bot',
                    'message' => $promptText,
                    'intent' => 'ask_pesticide_confirmation',
                    'metadata_json' => [
                        'leaf_detection_id' => $lastDetection?->id,
                        'class_name' => $detectedClassName,
                        'confidence' => $detectedConfidence,
                        'requires_confirmation' => true,
                        'confirmation_handled' => false,
                        'saved_to_database' => false,
                    ],
                ]);

                $this->sendTelegramDiseaseConfirmationPrompt($detectedClassName, $detectedConfidence, $modelId, false);
            } else {
                $healthyMsg = "🌿 **Hasil Analisis Gambar Daun**\n"
                    ."• Kondisi Daun: **{$detectedClassName}** (**{$confPct}%**)\n"
                    ."• Model AI: `{$modelId}`\n"
                    ."• Status Database: Kondisi daun tidak berubah (gambar tidak diduplikasi ke database)\n"
                    ."• Rekomendasi: {$recommendation}";
                ChatbotMessage::create([
                    'sender' => 'bot',
                    'message' => $healthyMsg,
                    'intent' => 'leaf_update',
                    'metadata_json' => [
                        'leaf_detection_id' => $lastDetection?->id,
                        'class_name' => $detectedClassName,
                        'confidence' => $detectedConfidence,
                        'saved_to_database' => false,
                    ],
                ]);
                $this->notifyTelegramIfConfigured($healthyMsg);
            }
        } elseif (! $isLiveWebcamFrame) {
            $notDetectedMsg = "🍃 **Hasil Analisis Foto Daun**\n"
                ."• Status: Tidak ada objek daun yang terdeteksi di atas threshold (`{$modelId}`).\n"
                .'• Saran: Dekatkan kamera ke daun cabai atau turunkan batas threshold lalu kirim ulang foto.';
            ChatbotMessage::create([
                'sender' => 'bot',
                'message' => $notDetectedMsg,
                'intent' => 'leaf_not_detected',
                'metadata_json' => [
                    'model_id' => $modelId,
                    'saved_to_database' => false,
                ],
            ]);
            if (! $isTelegramPhoto) {
                $this->notifyTelegramIfConfigured($notDetectedMsg);
            }
        }

        $activeDetection = $this->formatLeafDetectionRecord($savedRecord ?? $lastDetection);

        if ($leafChanged || (! $isLiveWebcamFrame && $detectedClassName !== 'Tidak Terdeteksi')) {
            Cache::put('esp32_latest_leaf_summary', [
                'class_name' => $detectedClassName,
                'confidence' => round((float) $detectedConfidence, 4),
                'model_id' => $modelId,
                'is_disease' => $isDisease,
                'recommendation' => $recommendation,
                'detected_at' => now('Asia/Jakarta')->format('d M Y, H:i:s').' WIB',
                'event_key' => $savedRecord
                    ? 'id_'.$savedRecord->id
                    : 'scan_'.now('Asia/Jakarta')->format('Ymd_His').'_'.substr($imageHash, 0, 6),
            ], now()->addMinutes(10));
        }

        return response()->json([
            'status' => 'success',
            'model_id' => $modelId,
            'model' => $definition['filename'],
            'inference_backend' => $inferenceResult['inference_backend'] ?? 'cli',
            'threshold' => $threshold,
            'image_width' => $imageWidth,
            'image_height' => $imageHeight,
            'image_url' => $chatImageUrl,
            'count' => $detectionCount,
            'classification' => $classification,
            'primary_detection' => $primary,
            'detections' => $detections,
            'leaf_changed' => $leafChanged,
            'saved_to_database' => $savedToDatabase,
            'database_message' => $savedToDatabase
                ? "Daun berubah menjadi '{$detectedClassName}'. Gambar dan hasil deteksi berhasil disimpan ke database TiDB Cloud."
                : ($unchangedReason ?? 'Daun tidak berubah, gambar tidak dikirim ke database.'),
            'latest_detection' => $activeDetection,
        ]);
    }

    /**
     * Jalankan inferensi Computer Vision melalui LeafInferenceService (FastAPI warm atau fallback CLI).
     *
     * @return array<string, mixed>
     */
    private function runPythonInference(string $filePath, string $originalName, string $modelId, float $threshold): array
    {
        return $this->leafInference->runInference($filePath, $originalName, $modelId, $threshold);
    }

    /**
     * =========================================================================
     * 2. API HASIL SENSOR SOIL MOISTURE
     * Mengirim Data Terakhir ke web > chatbot > database
     * =========================================================================
     */
    public function storeSoilMoisture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'moisture_percent' => 'nullable|numeric|min:0|max:100',
            'moisture' => 'nullable|numeric|min:0|max:100',
            'value' => 'nullable|numeric|min:0|max:100',
            'adc_value' => 'nullable|integer',
            'source' => 'nullable|string|max:50',
        ]);

        $moisture = $validated['moisture_percent']
            ?? $validated['moisture']
            ?? $validated['value']
            ?? null;

        if ($moisture === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter moisture_percent (0-100) wajib diisi.',
            ], 422);
        }

        $moisture = round((float) $moisture, 2);
        $adcValue = $validated['adc_value'] ?? (int) round(4095 - ($moisture / 100.0) * 2500);
        $source = $validated['source'] ?? 'api';
        $threshold = $this->getAutoWaterThreshold();
        $isDry = $moisture < (float) $threshold;

        if ($isDry) {
            $status = 'Kering';
            $recommendation = "Tanah terdeteksi kering (<{$threshold}%). Sistem otomatis menyemprotkan air selama 5 detik.";
        } elseif ($moisture <= 70.0) {
            $status = 'Optimal';
            $recommendation = "Kelembapan tanah ideal untuk pertumbuhan tanaman cabai ({$threshold}%–70%).";
        } else {
            $status = 'Basah';
            $recommendation = 'Tanah sudah basah (>70%). Hentikan penyiraman air untuk mencegah busuk akar.';
        }

        if ($source === 'esp32') {
            $this->recordEsp32Heartbeat('soil_moisture');
        }

        // Tahap 3 pada alur (web > chatbot > database): Simpan ke MySQL Database
        $reading = SoilMoistureReading::create([
            'moisture_percent' => $moisture,
            'adc_value' => $adcValue,
            'status' => $status,
            'recommendation' => $recommendation,
            'source' => $source,
            'sent_to_web' => true,
            'sent_to_chatbot' => true,
        ]);

        // Jika tanah kering (< threshold), jalankan penyemprotan air otomatis (dengan cooldown 60 detik agar pompa & server tidak banjir antrean)
        $autoWaterSprayRecord = null;
        $autoWaterTriggered = false;
        if ($isDry) {
            if (! Cache::has('auto_water_dry_soil_cooldown')) {
                Cache::put('auto_water_dry_soil_cooldown', true, now()->addSeconds(60));
                $autoWaterTriggered = true;
                $latestLeaf = $this->formatLeafDetectionRecord(LeafDetection::latest('id')->first());
                $autoWaterSprayRecord = SprayHistory::create([
                    'type' => 'water',
                    'trigger_mode' => 'auto_dry_soil',
                    'triggered_by' => 'sensor_tanah_kering',
                    'duration_seconds' => 5,
                    'status' => 'queued',
                    'leaf_condition' => $latestLeaf ? $this->leafInference->formatClassName((string) $latestLeaf->class_name) : 'Belum ada deteksi',
                    'soil_moisture' => $moisture,
                    'notes' => "Penyemprotan air otomatis karena tanah kering ({$moisture}% < {$threshold}%).",
                    'sprayed_at' => now('Asia/Jakarta'),
                ]);
                $formattedAutoSpray = $this->formatSprayHistory($autoWaterSprayRecord);
                $chatNotificationText = "🌱💧 **[Tanah Kering Terdeteksi — Semprot Air Otomatis!]**\n"
                    ."• Kelembapan Tanah: **{$moisture}%** (ADC: {$adcValue}) — Status: **Kering (<{$threshold}%)**\n"
                    ."• Tindakan Otomatis: **Pompa Air langsung menyemprot otomatis selama 5 detik!**\n"
                    ."• Waktu Disemprot: **{$formattedAutoSpray['sprayed_at_formatted']}**\n"
                    .'Data sensor & histori penyemprotan air terakhir telah disimpan ke database TiDB Cloud.';
            } else {
                $formattedAutoSpray = null;
                $chatNotificationText = "🌱 [Update Sensor Soil Moisture] Kelembapan Tanah: **{$moisture}%** (ADC: {$adcValue}) — Status: **{$status}** (batas <{$threshold}%, jeda cooldown 60 detik aktif).";
            }
        } else {
            $formattedAutoSpray = null;
            $chatNotificationText = "🌱 [Update Sensor Soil Moisture] Kelembapan Tanah: **{$moisture}%** (ADC: {$adcValue}) — Status: **{$status}**. {$recommendation}";
        }

        // Tahap 2 pada alur (web > chatbot > database): Teruskan ke Chatbot & Telegram (throttle notif rutin tiap 5 menit agar server tidak macet)
        $chatBotMsg = ChatbotMessage::create([
            'sender' => 'bot',
            'message' => $chatNotificationText,
            'intent' => $autoWaterTriggered ? 'auto_water_spray_dry_soil' : 'sensor_soil_update',
            'metadata_json' => [
                'pipeline' => 'web > chatbot > database',
                'soil_moisture_id' => $reading->id,
                'moisture_percent' => $moisture,
                'adc_value' => $adcValue,
                'status' => $status,
                'auto_water_spray_triggered' => $autoWaterTriggered,
                'auto_water_spray' => $formattedAutoSpray,
            ],
        ]);
        if ($autoWaterTriggered || ! Cache::has('telegram_soil_notify_cooldown')) {
            Cache::put('telegram_soil_notify_cooldown', true, now()->addMinutes(5));
            $this->notifyTelegramIfConfigured($chatBotMsg->message);
        }

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Hasil Sensor Soil Moisture',
            'pipeline_flow' => 'web > chatbot > database',
            'auto_water_spray_triggered' => $autoWaterTriggered,
            'auto_water_spray' => $formattedAutoSpray,
            'pipeline_steps' => [
                '1_web' => [
                    'delivered' => true,
                    'display_value' => "{$moisture}% ({$status})",
                ],
                '2_chatbot' => [
                    'delivered' => true,
                    'chatbot_message_id' => $chatBotMsg->id,
                    'notification' => $chatBotMsg->message,
                ],
                '3_database' => [
                    'saved' => true,
                    'table' => 'soil_moisture_readings',
                    'record_id' => $reading->id,
                ],
            ],
            'data' => $reading,
        ], 201);
    }

    public function getLatestSoilMoisture(): JsonResponse
    {
        $latest = SoilMoistureReading::latest('id')->first();
        $history = SoilMoistureReading::latest('id')->take(15)->get();

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Hasil Sensor Soil Moisture',
            'pipeline_flow' => 'web > chatbot > database',
            'latest' => $latest,
            'history' => $history,
        ]);
    }

    /**
     * =========================================================================
     * 3. API HASIL SENSOR DHT11
     * Mengirim Data Terakhir ke web > chatbot > database
     * =========================================================================
     */
    public function storeDht11(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'temperature' => 'nullable|numeric|min:-10|max:80',
            'suhu' => 'nullable|numeric|min:-10|max:80',
            'humidity' => 'nullable|numeric|min:0|max:100',
            'kelembapan_udara' => 'nullable|numeric|min:0|max:100',
            'source' => 'nullable|string|max:50',
        ]);

        $temperature = $validated['temperature'] ?? $validated['suhu'] ?? null;
        $humidity = $validated['humidity'] ?? $validated['kelembapan_udara'] ?? null;

        if ($temperature === null || $humidity === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter temperature (atau suhu) dan humidity (atau kelembapan_udara) wajib diisi.',
            ], 422);
        }

        $temperature = round((float) $temperature, 2);
        $humidity = round((float) $humidity, 2);
        $source = $validated['source'] ?? 'api';

        // Estimasi Heat Index sederhana (°C)
        $heatIndex = round($temperature + 0.05 * ($humidity - 50), 2);

        if ($temperature > 33.0 && $humidity < 50.0) {
            $status = 'Panas & Kering';
            $recommendation = 'Suhu udara tinggi dan kering. Periksa kelembapan tanah dan pertimbangkan penyemprotan air.';
        } elseif ($humidity > 85.0) {
            $status = 'Lembap Tinggi (Risiko Jamur)';
            $recommendation = 'Kelembapan udara sangat tinggi (>85%). Waspadai penyakit bercak daun & antraknosa.';
        } elseif ($temperature < 18.0) {
            $status = 'Dingin';
            $recommendation = 'Suhu udara rendah. Pantau pertumbuhan daun cabai.';
        } else {
            $status = 'Optimal';
            $recommendation = 'Suhu (24–32°C) dan kelembapan udara mendukung pertumbuhan tanaman cabai.';
        }

        if ($source === 'esp32') {
            $this->recordEsp32Heartbeat('dht11');
        }

        // Simpan ke MySQL Database
        $reading = Dht11Reading::create([
            'temperature' => $temperature,
            'humidity' => $humidity,
            'heat_index' => $heatIndex,
            'status' => $status,
            'recommendation' => $recommendation,
            'source' => $source,
            'sent_to_web' => true,
            'sent_to_chatbot' => true,
        ]);

        // Kirim ke Chatbot
        $chatBotMsg = ChatbotMessage::create([
            'sender' => 'bot',
            'message' => "🌡️ [Update Sensor DHT22] Suhu Udara: **{$temperature}°C** | Kelembapan Udara: **{$humidity}%** — Status: **{$status}**. {$recommendation}",
            'intent' => 'sensor_dht11_update',
            'metadata_json' => [
                'pipeline' => 'web > chatbot > database',
                'dht11_id' => $reading->id,
                'temperature' => $temperature,
                'humidity' => $humidity,
                'status' => $status,
            ],
        ]);
        $isAlertDht = $status !== 'Optimal';
        if (($isAlertDht && ! Cache::has('telegram_dht11_alert_cooldown')) || ! Cache::has('telegram_dht11_notify_cooldown')) {
            if ($isAlertDht) {
                Cache::put('telegram_dht11_alert_cooldown', true, now()->addMinutes(2));
            }
            Cache::put('telegram_dht11_notify_cooldown', true, now()->addMinutes(5));
            $this->notifyTelegramIfConfigured($chatBotMsg->message);
        }

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Hasil Sensor DHT11',
            'pipeline_flow' => 'web > chatbot > database',
            'pipeline_steps' => [
                '1_web' => [
                    'delivered' => true,
                    'display_value' => "{$temperature}°C / {$humidity}% ({$status})",
                ],
                '2_chatbot' => [
                    'delivered' => true,
                    'chatbot_message_id' => $chatBotMsg->id,
                    'notification' => $chatBotMsg->message,
                ],
                '3_database' => [
                    'saved' => true,
                    'table' => 'dht11_readings',
                    'record_id' => $reading->id,
                ],
            ],
            'data' => $reading,
        ], 201);
    }

    public function getLatestDht11(): JsonResponse
    {
        $latest = Dht11Reading::latest('id')->first();
        $history = Dht11Reading::latest('id')->take(15)->get();

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Hasil Sensor DHT11',
            'pipeline_flow' => 'web > chatbot > database',
            'latest' => $latest,
            'history' => $history,
        ]);
    }

    /**
     * =========================================================================
     * 4. API HISTORI PENYEMPROTAN TERAKHIR PESTISIDA
     * Menyimpan waktu terakhir pestisida disemprot
     * =========================================================================
     */
    public function getPesticideSprayHistory(): JsonResponse
    {
        $scheduleInfo = $this->evaluateTwoDayPesticideSchedule();

        $lastSpray = SprayHistory::where('type', 'pesticide')
            ->orderByDesc('sprayed_at')
            ->orderByDesc('id')
            ->first();

        $history = SprayHistory::where('type', 'pesticide')
            ->orderByDesc('sprayed_at')
            ->orderByDesc('id')
            ->take(20)
            ->get()
            ->map(fn (SprayHistory $item) => $this->formatSprayHistory($item));

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Histori Penyemprotan Terakhir Pestisida',
            'description' => 'Menyimpan waktu terakhir pestisida disemprot (Hanya Tombol Manual & Kondisi Tiap 2 Hari Sekali)',
            'schedule_2_days' => $scheduleInfo,
            'last_pesticide_spray' => $this->formatSprayHistory($lastSpray),
            'total_pesticide_sprays' => SprayHistory::where('type', 'pesticide')->count(),
            'history' => $history,
        ]);
    }

    public function storePesticideSprayHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trigger_mode' => 'nullable|string|max:30',
            'triggered_by' => 'nullable|string|max:50',
            'duration_seconds' => 'nullable|integer|min:1|max:300',
            'leaf_condition' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
            'sprayed_at' => 'nullable|date',
        ]);

        $latestLeaf = $this->formatLeafDetectionRecord(LeafDetection::latest('id')->first());
        $sprayedAt = isset($validated['sprayed_at'])
            ? Carbon::parse($validated['sprayed_at'])->timezone('Asia/Jakarta')
            : now('Asia/Jakarta');

        $record = SprayHistory::create([
            'type' => 'pesticide',
            'trigger_mode' => $validated['trigger_mode'] ?? 'auto',
            'triggered_by' => $validated['triggered_by'] ?? 'api',
            'duration_seconds' => $validated['duration_seconds'] ?? 5,
            'status' => 'completed',
            'leaf_condition' => $this->leafInference->formatClassName((string) ($validated['leaf_condition'] ?? ($latestLeaf->class_name ?? 'Tidak diketahui'))),
            'soil_moisture' => SoilMoistureReading::latest('id')->value('moisture_percent'),
            'notes' => $validated['notes'] ?? 'Waktu penyemprotan pestisida dicatat ke database.',
            'sprayed_at' => $sprayedAt,
        ]);

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Histori Penyemprotan Terakhir Pestisida',
            'message' => 'Waktu terakhir pestisida disemprot berhasil disimpan ke database TiDB Cloud.',
            'last_pesticide_spray' => $this->formatSprayHistory($record),
        ], 201);
    }

    /**
     * =========================================================================
     * 5. API HISTORI PENYEMPROTAN TERAKHIR AIR
     * Menyimpan waktu terakhir air disemprot
     * =========================================================================
     */
    public function getWaterSprayHistory(): JsonResponse
    {
        $lastSpray = SprayHistory::where('type', 'water')
            ->orderByDesc('sprayed_at')
            ->orderByDesc('id')
            ->first();

        $history = SprayHistory::where('type', 'water')
            ->orderByDesc('sprayed_at')
            ->orderByDesc('id')
            ->take(20)
            ->get()
            ->map(fn (SprayHistory $item) => $this->formatSprayHistory($item));

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Histori Penyemprotan Terakhir Air',
            'description' => 'Menyimpan waktu terakhir air disemprot',
            'last_water_spray' => $this->formatSprayHistory($lastSpray),
            'total_water_sprays' => SprayHistory::where('type', 'water')->count(),
            'history' => $history,
        ]);
    }

    public function storeWaterSprayHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trigger_mode' => 'nullable|string|max:30',
            'triggered_by' => 'nullable|string|max:50',
            'duration_seconds' => 'nullable|integer|min:1|max:300',
            'soil_moisture' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
            'sprayed_at' => 'nullable|date',
        ]);

        $latestSoil = SoilMoistureReading::latest('id')->first();
        $sprayedAt = isset($validated['sprayed_at'])
            ? Carbon::parse($validated['sprayed_at'])->timezone('Asia/Jakarta')
            : now('Asia/Jakarta');

        $record = SprayHistory::create([
            'type' => 'water',
            'trigger_mode' => $validated['trigger_mode'] ?? 'auto',
            'triggered_by' => $validated['triggered_by'] ?? 'api',
            'duration_seconds' => $validated['duration_seconds'] ?? 5,
            'status' => 'completed',
            'leaf_condition' => ($latestLeafName = LeafDetection::latest('id')->value('class_name')) !== null
                ? $this->leafInference->formatClassName((string) $latestLeafName)
                : null,
            'soil_moisture' => $validated['soil_moisture'] ?? ($latestSoil->moisture_percent ?? null),
            'notes' => $validated['notes'] ?? 'Waktu penyemprotan air dicatat ke database.',
            'sprayed_at' => $sprayedAt,
        ]);

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Histori Penyemprotan Terakhir Air',
            'message' => 'Waktu terakhir air disemprot berhasil disimpan ke database TiDB Cloud.',
            'last_water_spray' => $this->formatSprayHistory($record),
        ], 201);
    }

    /**
     * =========================================================================
     * 6. API PENYEMPROTAN MANUAL PESTISIDA DAN AIR
     * Implementasi Tombol di web dan command di chatbot untuk menyemprot air atau pestisida secara manual
     * =========================================================================
     */
    public function triggerManualSpray(Request $request): JsonResponse
    {
        $rawType = strtolower(trim((string) $request->input('type', $request->input('jenis', 'water'))));
        if (in_array($rawType, ['pestisida', 'pesticide'], true)) {
            $type = 'pesticide';
        } elseif (in_array($rawType, ['air', 'water'], true)) {
            $type = 'water';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => "Parameter 'type' harus bernilai 'water' (air) atau 'pesticide' (pestisida).",
            ], 422);
        }

        $triggeredBy = $request->input('triggered_by', $request->input('source', 'web'));
        $duration = (int) $request->input('duration_seconds', 5);
        $duration = max(1, min(60, $duration));

        $record = $this->executeManualSprayAction($type, $triggeredBy, $duration, $request->input('notes'));
        $formatted = $this->formatSprayHistory($record);

        return response()->json([
            'status' => 'success',
            'endpoint' => 'API Penyemprotan Manual Pestisida dan Air',
            'message' => 'Penyemprotan manual '.($type === 'pesticide' ? 'Pestisida' : 'Air')." berhasil dijalankan melalui {$triggeredBy} selama {$duration} detik.",
            'spray_action' => $formatted,
            'last_water_spray' => $this->formatSprayHistory(
                SprayHistory::where('type', 'water')->orderByDesc('sprayed_at')->orderByDesc('id')->first()
            ),
            'last_pesticide_spray' => $this->formatSprayHistory(
                SprayHistory::where('type', 'pesticide')->orderByDesc('sprayed_at')->orderByDesc('id')->first()
            ),
        ], 201);
    }

    /**
     * Helper internal untuk menjalankan penyemprotan manual (dipakai oleh Tombol Web & Command Chatbot)
     */
    private function executeManualSprayAction(string $type, string $triggeredBy, int $durationSeconds = 5, ?string $customNotes = null): SprayHistory
    {
        $latestLeaf = $this->formatLeafDetectionRecord(LeafDetection::latest('id')->first());
        $latestSoil = SoilMoistureReading::latest('id')->first();
        $label = $type === 'pesticide' ? 'Pestisida' : 'Air';

        $notes = $customNotes ?: "Penyemprotan {$label} manual dieksekusi dari {$triggeredBy} (durasi {$durationSeconds} detik).";

        // Simpan ke tabel spray_histories di MySQL (otomatis memperbarui histori penyemprotan terakhir!)
        $record = SprayHistory::create([
            'type' => $type,
            'trigger_mode' => 'manual',
            'triggered_by' => $triggeredBy,
            'duration_seconds' => $durationSeconds,
            'status' => 'queued',
            'leaf_condition' => $latestLeaf ? $this->leafInference->formatClassName((string) $latestLeaf->class_name) : 'Belum ada deteksi',
            'soil_moisture' => $latestSoil->moisture_percent ?? null,
            'notes' => $notes,
            'sprayed_at' => now('Asia/Jakarta'),
        ]);

        // Catat log aksi di Chatbot & kirim notifikasi ke Telegram jika dipicu dari Tombol Web atau API
        if ($triggeredBy !== 'chatbot') {
            $icon = $type === 'pesticide' ? '🧪' : '💧';
            $formattedTime = Carbon::parse($record->sprayed_at)->timezone('Asia/Jakarta')->format('d M Y, H:i:s').' WIB';
            $soilInfo = $record->soil_moisture !== null ? "{$record->soil_moisture}%" : 'Belum ada data';
            $webSprayMsg = "{$icon} **[Penyemprotan Manual dari Website]**\n"
                ."• Jenis Pompa: **{$label}**\n"
                ."• Durasi: **{$durationSeconds} detik**\n"
                ."• Waktu Disemprot: **{$formattedTime}**\n"
                ."• Kondisi Daun: **{$record->leaf_condition}**\n"
                ."• Kelembapan Tanah: **{$soilInfo}**\n"
                ."Waktu penyemprotan terakhir {$label} telah disimpan ke database TiDB Cloud.";

            ChatbotMessage::create([
                'sender' => 'bot',
                'message' => $webSprayMsg,
                'intent' => $type === 'pesticide' ? 'spray_pesticide' : 'spray_water',
                'metadata_json' => $this->formatSprayHistory($record),
            ]);

            // Kirim langsung ke Telegram Bot (@chili_vision_bot)
            $this->notifyTelegramIfConfigured($webSprayMsg);
        }

        return $record;
    }

    /**
     * Tandai pesan konfirmasi pestisida yang masih menunggu sebagai selesai (handled)
     * agar tombol konfirmasi di Chatbot Website & Telegram tetap sinkron.
     */
    private function markPendingConfirmationsHandled(string $decision): void
    {
        $pendingMsgs = ChatbotMessage::where('intent', 'ask_pesticide_confirmation')
            ->latest('id')
            ->take(10)
            ->get();

        foreach ($pendingMsgs as $msg) {
            $meta = is_array($msg->metadata_json) ? $msg->metadata_json : [];
            if (empty($meta['confirmation_handled'])) {
                $meta['confirmation_handled'] = true;
                $meta['decision'] = $decision;
                $msg->update(['metadata_json' => $meta]);
            }
        }
    }

    private function isFastDeterministicChatCommand(string $lower): bool
    {
        return $this->geminiChat->isFastDeterministicCommand($lower);
    }

    /**
     * @return array{reply: string, action: string, duration_seconds: int|null, interval_hours: int|null, auto_water_threshold: int|null, target_datetime: string|null, model_id: string|null, model_used: string}|null
     */
    private function callGeminiStructuredAssistant(string $userText, ChatbotMessage $currentUserMsg): ?array
    {
        return $this->geminiChat->askStructured(
            $userText,
            $currentUserMsg,
            fn (?SprayHistory $s) => $this->formatSprayHistory($s),
            fn (string $msg) => $this->notifyTelegramIfConfigured($msg),
        );
    }

    /**
     * Eksekusi hasil terstruktur dari Gemini ke aksi nyata Laravel/IoT.
     *
     * @return array{intent: string, reply: string, action_data: array<string, mixed>|null}|null
     */
    private function resolveGeminiInteraction(string $userText, int $defaultDuration, ChatbotMessage $currentUserMsg): ?array
    {
        $ai = $this->callGeminiStructuredAssistant($userText, $currentUserMsg);
        if ($ai === null) {
            return null;
        }

        $action = $ai['action'];
        $reply = $ai['reply'];
        $actionData = ['ai_model' => $ai['model_used'], 'ai_action' => $action];

        if ($action === 'spray_water') {
            $dur = max(1, min(60, $ai['duration_seconds'] ?? $defaultDuration));
            $sprayRecord = $this->executeManualSprayAction('water', 'chatbot', $dur, "Perintah AI Chatbot: \"{$userText}\"");
            $formatted = $this->formatSprayHistory($sprayRecord);
            $actionData = array_merge($formatted ?? [], $actionData);

            return [
                'intent' => 'spray_water',
                'reply' => $reply."\n\n💧 **Aksi Sistem**: Pompa air dinyalakan selama **{$dur} detik** ({$formatted['sprayed_at_formatted']}).",
                'action_data' => $actionData,
            ];
        }

        if ($action === 'spray_pesticide') {
            $dur = max(1, min(60, $ai['duration_seconds'] ?? $defaultDuration));
            $this->markPendingConfirmationsHandled('manual_pesticide');
            $sprayRecord = $this->executeManualSprayAction('pesticide', 'chatbot', $dur, "Perintah AI Chatbot: \"{$userText}\"");
            $formatted = $this->formatSprayHistory($sprayRecord);
            $actionData = array_merge($formatted ?? [], $actionData);

            return [
                'intent' => 'spray_pesticide',
                'reply' => $reply."\n\n🧪 **Aksi Sistem**: Pompa pestisida dinyalakan selama **{$dur} detik** ({$formatted['sprayed_at_formatted']}).",
                'action_data' => $actionData,
            ];
        }

        if ($action === 'update_pesticide_schedule') {
            $hours = (int) ($ai['interval_hours'] ?? 0);
            if ($hours >= 1 && $hours <= 720) {
                $this->clearPesticideTargetSchedule();
                Cache::forever('pesticide_schedule_interval_hours', $hours);
                $scheduleInfo = $this->evaluateTwoDayPesticideSchedule();
                $actionData = array_merge($scheduleInfo, $actionData);

                return [
                    'intent' => 'update_pesticide_schedule',
                    'reply' => $reply."\n\n🗓️ **Jadwal Diperbarui**: Tiap **{$scheduleInfo['interval_label']}** (`{$scheduleInfo['interval_hours']} jam`), jadwal berikutnya **{$scheduleInfo['next_spray_formatted']}**.",
                    'action_data' => $actionData,
                ];
            }
        }

        if ($action === 'set_pesticide_target_datetime' && ! empty($ai['target_datetime'])) {
            $dur = max(1, min(60, $ai['duration_seconds'] ?? $defaultDuration));
            $applied = $this->applyPesticideTargetSchedule($ai['target_datetime'], $dur);
            if (! $applied['ok']) {
                return [
                    'intent' => 'update_pesticide_schedule_invalid',
                    'reply' => '⚠️ '.$applied['error'],
                    'action_data' => $actionData,
                ];
            }

            $scheduleInfo = $applied['schedule'];
            $actionData = array_merge($scheduleInfo, $actionData, ['duration_seconds' => $dur]);

            return [
                'intent' => 'set_pesticide_target_datetime',
                'reply' => $reply."\n\n🗓️ **Target Jadwal Disimpan**: Pestisida akan disemprot otomatis selama **{$dur} detik** pada **{$scheduleInfo['next_spray_formatted']}** ({$scheduleInfo['time_until_next']}).",
                'action_data' => $actionData,
            ];
        }

        if ($action === 'update_auto_water_threshold' && isset($ai['auto_water_threshold'])) {
            $reqThreshold = (int) $ai['auto_water_threshold'];
            if ($reqThreshold >= 1 && $reqThreshold <= 95) {
                $saved = $this->setAutoWaterThreshold($reqThreshold);
                $actionData['auto_water_threshold'] = $saved;

                return [
                    'intent' => 'update_auto_water_threshold',
                    'reply' => $reply."\n\n💧 **Batas Semprot Air Otomatis Diperbarui**: Sekarang aktif saat kelembapan tanah **< {$saved}%** (tersinkron ke ESP32).",
                    'action_data' => $actionData,
                ];
            }
        }

        if ($action === 'select_model' && ! empty($ai['model_id'])) {
            $defs = $this->getModelDefinitions();
            $requestedId = $ai['model_id'];
            $matchedId = isset($defs[$requestedId]) ? $requestedId : $this->resolveModelFromChatText(mb_strtolower($requestedId));

            if ($matchedId !== null && is_file($defs[$matchedId]['path'])) {
                Cache::forever('telegram_selected_model', $matchedId);
                Cache::forever('selected_model_version', ((int) Cache::get('selected_model_version', 0)) + 1);
                $actionData['selected_model_id'] = $matchedId;
                $actionData['selected_model_name'] = $defs[$matchedId]['name'];

                return [
                    'intent' => 'model_selected',
                    'reply' => $reply."\n\n✅ **Model Aktif Sekarang**: **{$defs[$matchedId]['name']}** (`{$matchedId}`).",
                    'action_data' => $actionData,
                ];
            }
        }

        if ($action === 'show_model_menu') {
            $selectedId = $this->getSelectedTelegramModelId();
            $modelOptions = $this->buildChatModelOptions($selectedId);
            $actionData['selected_model_id'] = $selectedId;
            $actionData['models'] = $modelOptions;

            return [
                'intent' => 'select_model_menu',
                'reply' => $reply,
                'action_data' => $actionData,
            ];
        }

        $intentMap = [
            'check_soil' => 'check_soil',
            'check_dht11' => 'check_dht11',
            'check_leaf' => 'check_leaf',
            'check_history' => 'check_history',
            'status' => 'status_help',
        ];

        return [
            'intent' => $intentMap[$action] ?? 'ai_chat',
            'reply' => $reply,
            'action_data' => $actionData,
        ];
    }

    /**
     * =========================================================================
     * 7. CHATBOT INTERAKTIF (Sinkron Penuh Website & Telegram Bot)
     * =========================================================================
     */
    public function getChatbotMessages(): JsonResponse
    {
        $messages = ChatbotMessage::latest('id')->take(40)->get()->reverse()->values();

        return response()->json([
            'status' => 'success',
            'messages' => $messages,
        ]);
    }

    public function sendChatbotMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userText = trim($validated['message']);
        $lower = mb_strtolower($userText);
        $source = (string) $request->input('source', 'telegram');

        // Simpan pesan user ke database agar tampil di Website & Telegram
        $userMsg = ChatbotMessage::create([
            'sender' => 'user',
            'message' => $userText,
            'intent' => 'user_input',
            'metadata_json' => ['source' => $source],
        ]);

        $botReplyText = '';
        $intent = 'general';
        $actionData = null;

        // Deteksi durasi opsional di command, misal "semprot air 10 detik"
        $duration = 5;
        if (preg_match('/(\d+)\s*(detik|s|sec)/i', $userText, $matches)) {
            $duration = max(1, min(60, (int) $matches[1]));
        }

        // 0a. Gemini AI Assistant untuk pertanyaan natural, konsultasi agronomi, & perintah fleksibel
        if (
            ! $this->isFastDeterministicChatCommand($lower) &&
            ($geminiResult = $this->resolveGeminiInteraction($userText, $duration, $userMsg)) !== null
        ) {
            $intent = $geminiResult['intent'];
            $botReplyText = $geminiResult['reply'];
            $actionData = $geminiResult['action_data'];
        }
        // 0. Konfirmasi Ya / Tidak atas notifikasi deteksi penyakit daun untuk semprot pestisida
        elseif (in_array($lower, ['ya', 'iya', 'yes', 'setuju', 'konfirmasi', 'ya semprot', 'semprot sekarang', '/konfirmasi_pestisida'], true)) {
            $intent = 'confirm_pesticide_yes';
            $this->markPendingConfirmationsHandled('yes');
            $sprayRecord = $this->executeManualSprayAction('pesticide', 'chatbot', $duration, 'Dikonfirmasi pengguna (Ya) setelah notifikasi deteksi penyakit daun.');
            $actionData = $this->formatSprayHistory($sprayRecord);
            $botReplyText = "✅🧪 **Konfirmasi Diterima! Menyemprotkan Pestisida...**\n"
                ."• Status: Disetujui oleh pengguna\n"
                ."• Durasi Pompa: **{$duration} detik**\n"
                ."• Kondisi Daun: **{$actionData['leaf_condition']}**\n"
                ."• Waktu Disemprot: **{$actionData['sprayed_at_formatted']}**\n"
                .'Waktu penyemprotan pestisida terakhir telah disimpan ke database TiDB Cloud dan perintah dikirim ke ESP32.';
        } elseif (in_array($lower, ['tidak', 'nggak', 'no', 'batal', 'jangan', 'abaikan', 'jangan semprot', '/batal_pestisida'], true)) {
            $intent = 'confirm_pesticide_no';
            $this->markPendingConfirmationsHandled('no');
            $intervalLabel = strtolower($this->formatScheduleIntervalLabel($this->getPesticideScheduleIntervalHours()));
            $botReplyText = "❌🛡️ **Penyemprotan Pestisida Dibatalkan.**\n"
                ."Sesuai konfirmasi Anda, pompa pestisida **tidak dinyalakan** dan tetap mengikuti jadwal rutin tiap {$intervalLabel}.";
        }
        // 0b. Ubah Jadwal Penyemprotan Pestisida Dinamis (harus sebelum command semprot manual!)
        elseif (($scheduleReq = $this->parseScheduleIntervalRequest($lower)) !== null) {
            if ($scheduleReq['invalid'] ?? false) {
                $intent = 'update_pesticide_schedule_invalid';
                $botReplyText = '⚠️ '.$scheduleReq['error'];
            } elseif ($scheduleReq['needs_value'] ?? false) {
                $intent = 'update_pesticide_schedule_prompt';
                $currentLabel = $this->formatScheduleIntervalLabel($this->getPesticideScheduleIntervalHours());
                $botReplyText = "🗓️ Jadwal penyemprotan pestisida saat ini: **Tiap {$currentLabel}**.\n"
                    .'Silakan sebutkan interval barunya, misalnya: `ganti jadwal pestisida 2 hari sekali` atau `ubah jadwal pestisida 48 jam sekali`.';
            } else {
                $newHours = (int) $scheduleReq['hours'];
                $this->clearPesticideTargetSchedule();
                Cache::forever('pesticide_schedule_interval_hours', $newHours);
                $scheduleInfo = $this->evaluateTwoDayPesticideSchedule();
                $intent = 'update_pesticide_schedule';
                $actionData = $scheduleInfo;
                $botReplyText = "✅🗓️ **Jadwal Penyemprotan Pestisida Diperbarui!**\n"
                    ."• Interval Baru: **Tiap {$scheduleInfo['interval_label']}** (`{$scheduleInfo['interval_hours']} jam`)\n"
                    ."• Terakhir Disemprot: **{$scheduleInfo['last_sprayed_at_formatted']}**\n"
                    ."• Jadwal Berikutnya: **{$scheduleInfo['next_spray_formatted']}** ({$scheduleInfo['time_until_next']})";
            }
        }
        // 0c. Jadwal Tanggal & Jam Spesifik Pestisida (fallback deterministik saat tanpa AI / format langsung)
        elseif (($targetDatetime = $this->parseTargetScheduleDatetimeRequest($lower)) !== null) {
            $applied = $this->applyPesticideTargetSchedule($targetDatetime->toIso8601String(), $duration);
            if (! $applied['ok']) {
                $intent = 'update_pesticide_schedule_invalid';
                $botReplyText = '⚠️ '.$applied['error'];
            } else {
                $scheduleInfo = $applied['schedule'];
                $intent = 'set_pesticide_target_datetime';
                $actionData = array_merge($scheduleInfo, ['duration_seconds' => $duration]);
                $botReplyText = "✅🗓️ **Jadwal Tanggal Spesifik Pestisida Disimpan!**\n"
                    ."• Waktu Target Semprot: **{$scheduleInfo['next_spray_formatted']}** ({$scheduleInfo['time_until_next']})\n"
                    ."• Durasi Pompa: **{$duration} detik**\n"
                    ."• Setelah tereksekusi, jadwal kembali ke interval rutin **{$scheduleInfo['recurring_interval_label']}**.";
            }
        }
        // 0d. Ubah/Cek Batas Kelembapan Tanah Semprot Air Otomatis (default <30%)
        elseif (($waterThresholdReq = $this->geminiChat->parseAutoWaterThresholdRequest($lower)) !== null) {
            if ($waterThresholdReq['invalid'] ?? false) {
                $intent = 'update_auto_water_threshold_invalid';
                $botReplyText = '⚠️ '.$waterThresholdReq['error'];
            } elseif ($waterThresholdReq['needs_value'] ?? false) {
                $intent = 'update_auto_water_threshold_prompt';
                $currentThreshold = $this->getAutoWaterThreshold();
                $actionData = ['auto_water_threshold' => $currentThreshold];
                $botReplyText = "💧 Batas penyemprotan air otomatis saat ini: kelembapan tanah **< {$currentThreshold}%**.\n"
                    .'Untuk mengubahnya, ketik misalnya: `ubah batas semprot air 40%` atau `ubah semprot air dibawah 25%`.';
            } else {
                $savedThreshold = $this->setAutoWaterThreshold((int) $waterThresholdReq['threshold']);
                $intent = 'update_auto_water_threshold';
                $actionData = ['auto_water_threshold' => $savedThreshold];
                $botReplyText = "✅💧 **Batas Semprot Air Otomatis Diperbarui!**\n"
                    ."• Ambang Batas Baru: Kelembapan tanah **< {$savedThreshold}%**\n"
                    ."• Efek: Jika kelembapan tanah turun di bawah **{$savedThreshold}%**, sistem & ESP32 otomatis menyalakan pompa air.\n"
                    .'Perubahan ini otomatis dikirim ke ESP32 pada polling berikutnya.';
            }
        }
        // 1. Command Penyemprotan Manual Pestisida dari Chatbot
        elseif (
            ! str_contains($lower, 'jadwal') &&
            ! str_contains($lower, 'kapan') &&
            ! str_contains($lower, 'histori') &&
            ! str_contains($lower, 'riwayat') &&
            (
                str_contains($lower, 'semprot pestisida') ||
                str_contains($lower, 'siram pestisida') ||
                str_contains($lower, 'nyalakan pestisida') ||
                str_contains($lower, 'pompa pestisida') ||
                $lower === '/pestisida' ||
                $lower === '/semprot_pestisida' ||
                $lower === 'spray pesticide'
            )
        ) {
            $intent = 'spray_pesticide';
            $this->markPendingConfirmationsHandled('manual_pesticide');
            $sprayRecord = $this->executeManualSprayAction('pesticide', 'chatbot', $duration, "Command chatbot: \"{$userText}\"");
            $actionData = $this->formatSprayHistory($sprayRecord);
            $botReplyText = "🧪 **Penyemprotan Pestisida Manual Berhasil!**\n"
                ."• Sumber Perintah: Chatbot (`{$userText}`)\n"
                ."• Durasi Pompa: **{$duration} detik**\n"
                ."• Waktu Disemprot: **{$actionData['sprayed_at_formatted']}**\n"
                ."• Kondisi Daun Saat Ini: **{$actionData['leaf_condition']}**\n"
                .'Waktu terakhir penyemprotan pestisida sudah diperbarui di database TiDB Cloud.';
        }
        // 2. Command Penyemprotan Manual Air dari Chatbot
        elseif (
            ! str_contains($lower, 'jadwal') &&
            ! str_contains($lower, 'kapan') &&
            ! str_contains($lower, 'histori') &&
            ! str_contains($lower, 'riwayat') &&
            (
                str_contains($lower, 'semprot air') ||
                str_contains($lower, 'siram air') ||
                str_contains($lower, 'siram tanaman') ||
                str_contains($lower, 'nyalakan air') ||
                str_contains($lower, 'pompa air') ||
                $lower === '/air' ||
                $lower === '/water' ||
                $lower === '/semprot_air' ||
                $lower === 'spray water'
            )
        ) {
            $intent = 'spray_water';
            $sprayRecord = $this->executeManualSprayAction('water', 'chatbot', $duration, "Command chatbot: \"{$userText}\"");
            $actionData = $this->formatSprayHistory($sprayRecord);
            $soilText = $actionData['soil_moisture'] !== null ? "{$actionData['soil_moisture']}%" : 'Belum ada data';
            $botReplyText = "💧 **Penyemprotan Air Manual Berhasil!**\n"
                ."• Sumber Perintah: Chatbot (`{$userText}`)\n"
                ."• Durasi Pompa: **{$duration} detik**\n"
                ."• Waktu Disemprot: **{$actionData['sprayed_at_formatted']}**\n"
                ."• Kelembapan Tanah Saat Ini: **{$soilText}**\n"
                .'Waktu terakhir penyemprotan air sudah diperbarui di database TiDB Cloud.';
        }
        // 3. Command Cek Sensor Soil Moisture
        elseif (str_contains($lower, 'soil') || str_contains($lower, 'tanah') || str_contains($lower, 'moisture') || $lower === '/cek_tanah') {
            $intent = 'check_soil';
            $soil = SoilMoistureReading::latest('id')->first();
            if ($soil) {
                $time = Carbon::parse($soil->created_at)->timezone('Asia/Jakarta')->format('d M Y, H:i:s').' WIB';
                $botReplyText = "🌱 **Data Terakhir Sensor Soil Moisture**\n"
                    ."• Kelembapan Tanah: **{$soil->moisture_percent}%** (ADC: {$soil->adc_value})\n"
                    ."• Status: **{$soil->status}**\n"
                    ."• Rekomendasi: {$soil->recommendation}\n"
                    ."• Waktu Pembacaan: {$time}\n"
                    .'Ketik `/semprot_air` jika ingin menyiram tanaman secara manual.';
            } else {
                $botReplyText = '🌱 Belum ada data sensor Soil Moisture di database. Kirim data melalui `POST /api/sensor/soil-moisture` atau simulator di dashboard.';
            }
        }
        // 4. Command Cek Sensor DHT22
        elseif (str_contains($lower, 'dht') || str_contains($lower, 'suhu') || str_contains($lower, 'temperatur') || str_contains($lower, 'udara') || $lower === '/cek_suhu') {
            $intent = 'check_dht11';
            $dht = Dht11Reading::latest('id')->first();
            if ($dht) {
                $time = Carbon::parse($dht->created_at)->timezone('Asia/Jakarta')->format('d M Y, H:i:s').' WIB';
                $botReplyText = "🌡️ **Data Terakhir Sensor DHT22**\n"
                    ."• Suhu Udara: **{$dht->temperature}°C**\n"
                    ."• Kelembapan Udara: **{$dht->humidity}%**\n"
                    ."• Heat Index: **{$dht->heat_index}°C**\n"
                    ."• Status: **{$dht->status}**\n"
                    ."• Rekomendasi: {$dht->recommendation}\n"
                    ."• Waktu Pembacaan: {$time}";
            } else {
                $botReplyText = '🌡️ Belum ada data sensor DHT22 di database. Kirim data melalui `POST /api/sensor/dht22` atau simulator di dashboard.';
            }
        }
        // 5. Command Cek Deteksi Daun Terakhir
        elseif (str_contains($lower, 'daun') || str_contains($lower, 'penyakit') || str_contains($lower, 'deteksi') || $lower === '/cek_daun') {
            $intent = 'check_leaf';
            $leaf = LeafDetection::latest('id')->first();
            if ($leaf) {
                $leafName = $this->leafInference->formatClassName((string) $leaf->class_name);
                $conf = round($leaf->confidence * 100, 1);
                $time = Carbon::parse($leaf->created_at)->timezone('Asia/Jakarta')->format('d M Y, H:i:s').' WIB';
                $botReplyText = "🍃 **Deteksi Terakhir Daun Cabai**\n"
                    ."• Kondisi/Penyakit: **{$leafName}**\n"
                    ."• Confidence: **{$conf}%** (Model: `{$leaf->model_id}`)\n"
                    ."• Rekomendasi: {$leaf->recommendation}\n"
                    ."• Waktu Disimpan: {$time}\n"
                    .'*(Catatan: Jika daun tidak berubah, gambar tidak dikirim ulang ke database).*';
            } else {
                $botReplyText = '🍃 Belum ada deteksi daun di database. Silakan gunakan kamera atau upload foto daun cabai di sebelah kiri.';
            }
        }
        // 6. Command Cek Histori Penyemprotan
        elseif (str_contains($lower, 'histori') || str_contains($lower, 'riwayat') || str_contains($lower, 'kapan') || str_contains($lower, 'jadwal') || $lower === '/histori') {
            $intent = 'check_history';
            $scheduleInfo = $this->evaluateTwoDayPesticideSchedule();
            $lastWater = $this->formatSprayHistory(SprayHistory::where('type', 'water')->orderByDesc('sprayed_at')->orderByDesc('id')->first());
            $lastPesticide = $this->formatSprayHistory(SprayHistory::where('type', 'pesticide')->orderByDesc('sprayed_at')->orderByDesc('id')->first());

            $waterThreshold = $this->getAutoWaterThreshold();
            $waterStr = $lastWater ? "**{$lastWater['sprayed_at_formatted']}** ({$lastWater['time_ago']} via `{$lastWater['triggered_by']}`)" : 'Belum pernah disemprot';
            $pestStr = $lastPesticide ? "**{$lastPesticide['sprayed_at_formatted']}** ({$lastPesticide['time_ago']} via `{$lastPesticide['triggered_by']}`)" : 'Belum pernah disemprot';

            $botReplyText = "⏱️ **Histori & Jadwal Penyemprotan**\n"
                ."• 💧 **Air Terakhir Disemprot**: {$waterStr} *(Otomatis menyemprot saat tanah kering <{$waterThreshold}%)*\n"
                ."• 🧪 **Pestisida Terakhir Disemprot**: {$pestStr}\n"
                ."• 🗓️ **Jadwal Pestisida Berikutnya (Tiap {$scheduleInfo['interval_label']})**: **{$scheduleInfo['next_spray_formatted']}** ({$scheduleInfo['time_until_next']})\n"
                .'*(Tip: Ketik `ganti jadwal pestisida 2 hari sekali` untuk mengubah interval jadwal pestisida).*';
        }
        // 6b. Command Menu Pilih Model AI (tombol interaktif Website & Telegram)
        elseif (in_array($lower, ['model', '/model', 'pilih model', 'ganti model', 'cek model', 'daftar model', 'pakai model apa', 'model apa', 'ubah model'], true)) {
            $intent = 'select_model_menu';
            $selectedId = $this->getSelectedTelegramModelId();
            $modelOptions = $this->buildChatModelOptions($selectedId);
            $actionData = [
                'selected_model_id' => $selectedId,
                'models' => $modelOptions,
            ];

            $lines = ['🧠 **Pilih Model AI untuk Analisis Daun**'];
            foreach ($modelOptions as $opt) {
                $statusText = $opt['available'] ? 'Tersedia' : 'Belum tersedia';
                $mark = $opt['selected'] ? ' ✅ (Aktif)' : '';
                $lines[] = "• **{$opt['name']}** (`{$opt['id']}`) — {$statusText}{$mark}";
            }
            $lines[] = "\nKlik salah satu tombol model di bawah, atau ketik langsung misalnya `model yolov8n` / `model cnn`.";
            $botReplyText = implode("\n", $lines);
        }
        // 6c. Command Pilih Model Langsung via Teks (mis. "model yolov8n", "yolov8n", "pilih cnn")
        elseif (($matchedModelId = $this->resolveModelFromChatText($lower)) !== null) {
            $defs = $this->getModelDefinitions();
            $selectedDef = $defs[$matchedModelId];
            if (! is_file($selectedDef['path'])) {
                $intent = 'model_unavailable';
                $botReplyText = "⚠️ File model **{$selectedDef['name']}** (`{$selectedDef['filename']}`) belum tersedia di folder `models/`.";
            } else {
                Cache::forever('telegram_selected_model', $matchedModelId);
                Cache::forever('selected_model_version', ((int) Cache::get('selected_model_version', 0)) + 1);
                $intent = 'model_selected';
                $actionData = [
                    'selected_model_id' => $matchedModelId,
                    'selected_model_name' => $selectedDef['name'],
                ];
                $botReplyText = "✅ **Model AI Berhasil Dipilih!**\n"
                    ."• Model Aktif: **{$selectedDef['name']}** (`{$matchedModelId}`)\n"
                    .'Model ini sekarang dipakai untuk analisis foto daun di Website & Telegram Bot.';
            }
        }
        // 7. Command Status Lengkap / Bantuan
        else {
            $intent = 'status_help';
            $scheduleInfo = $this->evaluateTwoDayPesticideSchedule();
            $leaf = LeafDetection::latest('id')->first();
            $soil = SoilMoistureReading::latest('id')->first();
            $dht = Dht11Reading::latest('id')->first();
            $lastWater = $this->formatSprayHistory(SprayHistory::where('type', 'water')->orderByDesc('sprayed_at')->orderByDesc('id')->first());
            $lastPest = $this->formatSprayHistory(SprayHistory::where('type', 'pesticide')->orderByDesc('sprayed_at')->orderByDesc('id')->first());

            $waterThreshold = $this->getAutoWaterThreshold();
            $leafStr = $leaf ? $this->leafInference->formatClassName((string) $leaf->class_name).' ('.round($leaf->confidence * 100, 1).'%)' : 'Belum ada data';
            $soilStr = $soil ? "{$soil->moisture_percent}% ({$soil->status})" : 'Belum ada data';
            $dhtStr = $dht ? "{$dht->temperature}°C / {$dht->humidity}% ({$dht->status})" : 'Belum ada data';
            $waterStr = $lastWater ? $lastWater['sprayed_at_formatted'] : 'Belum ada';
            $pestStr = $lastPest ? $lastPest['sprayed_at_formatted'] : 'Belum ada';

            $botReplyText = "🤖 **ChiliVision Smart Assistant**\n"
                ."Berikut ringkasan kondisi terkini dari database TiDB Cloud:\n"
                ."• 🍃 **Daun Terakhir**: **{$leafStr}**\n"
                ."• 🌱 **Soil Moisture**: **{$soilStr}** *(Otomatis semprot air jika <{$waterThreshold}%)*\n"
                ."• 🌡️ **Sensor DHT22**: **{$dhtStr}**\n"
                ."• 💧 **Terakhir Semprot Air**: **{$waterStr}**\n"
                ."• 🧪 **Terakhir Semprot Pestisida**: **{$pestStr}**\n"
                ."• 🗓️ **Jadwal Pestisida ({$scheduleInfo['interval_label']})**: **{$scheduleInfo['next_spray_formatted']}**\n\n"
                ."**Menu Perintah Chatbot (Website & Telegram):**\n"
                ."• `/status` — Ringkasan kondisi daun, sensor, & histori semprot\n"
                ."• `model` / `/model` — Pilih model AI yang ingin digunakan\n"
                ."• `ganti jadwal pestisida 2 hari sekali` — Ubah interval jadwal pestisida\n"
                ."• `ubah batas semprot air 40%` — Ubah ambang batas kelembapan tanah semprot air otomatis\n"
                ."• `/semprot_air` — Semprot air secara manual (5 detik)\n"
                ."• `/semprot_pestisida` — Semprot pestisida secara manual (5 detik)\n"
                ."• `/cek_daun` — Lihat deteksi gambar daun terakhir di database\n"
                ."• `/cek_tanah` — Lihat data terakhir sensor Soil Moisture\n"
                ."• `/cek_suhu` — Lihat data terakhir sensor DHT22\n"
                .'• `/histori` — Lihat waktu terakhir air & pestisida disemprot';
        }

        $botMsg = ChatbotMessage::create([
            'sender' => 'bot',
            'message' => $botReplyText,
            'intent' => $intent,
            'metadata_json' => $actionData,
        ]);

        // Sinkronisasi 2 arah: setiap perintah yang dikirim dari Website (source = web)
        // langsung diteruskan juga ke Telegram Bot agar kondisi chat di Website & Telegram selalu sama.
        if ($source === 'web') {
            $inlineKeyboard = null;
            if ($intent === 'select_model_menu' && is_array($actionData['models'] ?? null)) {
                $inlineKeyboard = $this->buildModelInlineKeyboard($actionData['models']);
            }
            $this->notifyTelegramIfConfigured("💬 *[Perintah dari Website: {$userText}]*\n\n".$botReplyText, null, $inlineKeyboard);
        }

        return response()->json([
            'status' => 'success',
            'user_message' => $userMsg,
            'bot_reply' => $botMsg,
            'spray_action' => $actionData,
            'selected_model_id' => $actionData['selected_model_id'] ?? $this->getSelectedTelegramModelId(),
        ]);
    }

    /**
     * Tentukan apakah request ke /api/esp32/poll berasal dari perangkat ESP32 (bukan preview/browser).
     */
    private function isRealEsp32PollRequest(Request $request): bool
    {
        if ($request->boolean('preview')) {
            return false;
        }

        if ($request->hasHeader('X-ESP32-Device') || $request->query('device') === 'esp32') {
            return true;
        }

        if (
            $request->hasHeader('Sec-Fetch-Mode')
            || $request->hasHeader('Sec-Fetch-Dest')
            || $request->hasHeader('Sec-Fetch-Site')
            || $request->hasHeader('Upgrade-Insecure-Requests')
            || $request->hasHeader('X-CSRF-TOKEN')
            || $request->hasHeader('Referer')
            || $request->hasHeader('Origin')
        ) {
            return false;
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'text/html')) {
            return false;
        }

        $userAgent = strtolower((string) $request->userAgent());
        if ($userAgent !== '' && str_starts_with($userAgent, 'mozilla/')) {
            return false;
        }

        return true;
    }

    /**
     * Catat waktu terakhir ESP32 menghubungi server (via polling atau upload sensor).
     */
    private function recordEsp32Heartbeat(string $activity): void
    {
        Cache::forever('esp32_last_seen_at', now('Asia/Jakarta')->toIso8601String());
        Cache::forever('esp32_last_activity', $activity);
        Cache::forget('esp32_offline_alert_sent');
    }

    /**
     * Hitung status koneksi hardware ESP32 dan antrean pompa saat ini.
     *
     * @return array<string, mixed>
     */
    private function getEsp32ConnectionStatus(): array
    {
        $rawLastSeen = Cache::get('esp32_last_seen_at');
        $lastActivity = Cache::get('esp32_last_activity');
        $now = now('Asia/Jakarta');

        $pendingWaterCount = SprayHistory::where('type', 'water')->where('status', 'queued')->count();
        $pendingPesticideCount = SprayHistory::where('type', 'pesticide')->where('status', 'queued')->count();
        $offlineThresholdSeconds = 45;

        if (! is_string($rawLastSeen) || trim($rawLastSeen) === '') {
            return [
                'state' => 'never_connected',
                'is_online' => false,
                'label' => 'Menunggu Koneksi',
                'last_seen_at' => null,
                'last_seen_formatted' => 'Belum pernah terhubung',
                'last_seen_ago' => 'Belum ada sinyal',
                'seconds_since_seen' => null,
                'last_activity' => null,
                'pending_water_count' => $pendingWaterCount,
                'pending_pesticide_count' => $pendingPesticideCount,
                'offline_threshold_seconds' => $offlineThresholdSeconds,
            ];
        }

        $lastSeen = Carbon::parse($rawLastSeen)->timezone('Asia/Jakarta');
        $secondsSince = max(0, (int) $lastSeen->diffInSeconds($now));

        if ($secondsSince <= 15) {
            $state = 'online';
            $label = 'Online';
            $isOnline = true;
        } elseif ($secondsSince <= $offlineThresholdSeconds) {
            $state = 'warning';
            $label = 'Koneksi Lambat';
            $isOnline = true;
        } else {
            $state = 'offline';
            $label = 'Offline';
            $isOnline = false;
        }

        return [
            'state' => $state,
            'is_online' => $isOnline,
            'label' => $label,
            'last_seen_at' => $lastSeen->toIso8601String(),
            'last_seen_formatted' => $lastSeen->format('d M Y, H:i:s').' WIB',
            'last_seen_ago' => $lastSeen->diffForHumans($now, ['parts' => 2]),
            'seconds_since_seen' => $secondsSince,
            'last_activity' => is_string($lastActivity) ? $lastActivity : null,
            'pending_water_count' => $pendingWaterCount,
            'pending_pesticide_count' => $pendingPesticideCount,
            'offline_threshold_seconds' => $offlineThresholdSeconds,
        ];
    }

    /**
     * Jalankan pengecekan latar belakang (Laravel Scheduler): evaluasi jadwal pestisida & alert ESP32 offline.
     *
     * @return array{schedule: array<string, mixed>, esp32: array<string, mixed>, offline_alert_sent: bool}
     */
    public function runScheduledHealthAndSprayCheck(): array
    {
        $schedule = $this->evaluateTwoDayPesticideSchedule();
        $esp32 = $this->getEsp32ConnectionStatus();
        $offlineAlertSentNow = false;

        if ($esp32['state'] === 'offline' && ! Cache::get('esp32_offline_alert_sent', false)) {
            Cache::forever('esp32_offline_alert_sent', true);
            $offlineAlertSentNow = true;

            $msgText = "⚠️🔌 **[Peringatan Hardware ESP32 Offline]**\n"
                ."Perangkat ESP32 tidak mengirim sinyal selama **{$esp32['seconds_since_seen']} detik** "
                ."(terakhir aktif: **{$esp32['last_seen_formatted']}**).\n"
                .'Periksa koneksi Wi-Fi atau catu daya ESP32 agar penyemprotan otomatis tetap berjalan.';

            ChatbotMessage::create([
                'sender' => 'bot',
                'message' => $msgText,
                'intent' => 'esp32_offline_alert',
                'metadata_json' => $esp32,
            ]);
            $this->notifyTelegramIfConfigured($msgText);
        }

        return [
            'schedule' => $schedule,
            'esp32' => $esp32,
            'offline_alert_sent' => $offlineAlertSentNow,
        ];
    }

    /**
     * GET /api/hardware/esp32-status
     * Status koneksi hardware ESP32 dan antrean pompa
     */
    public function getHardwareStatus(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'esp32_status' => $this->getEsp32ConnectionStatus(),
            'pesticide_schedule' => $this->evaluateTwoDayPesticideSchedule(),
            'auto_water_threshold' => $this->getAutoWaterThreshold(),
        ]);
    }

    /**
     * Bangun data grafik tren sensor, ringkasan penyakit daun, & statistik penyemprotan untuk Dashboard.
     *
     * @param  Collection<int, SoilMoistureReading>|null  $soilRows
     * @param  Collection<int, Dht11Reading>|null  $dhtRows
     * @return array<string, mixed>
     */
    private function buildDashboardAnalytics($soilRows = null, $dhtRows = null): array
    {
        $soilRows ??= SoilMoistureReading::latest('id')->take(20)->get();
        $soilSeries = $soilRows
            ->reverse()
            ->values()
            ->map(function (SoilMoistureReading $row): array {
                $dt = Carbon::parse($row->created_at)->timezone('Asia/Jakarta');

                return [
                    'time' => $dt->format('H:i'),
                    'full_time' => $dt->format('d M Y, H:i:s').' WIB',
                    'value' => round((float) $row->moisture_percent, 1),
                    'adc_value' => $row->adc_value !== null ? (int) $row->adc_value : null,
                    'status' => $row->status,
                    'source' => $row->source ?? 'esp32',
                ];
            })
            ->all();

        $dhtRows ??= Dht11Reading::latest('id')->take(20)->get();
        $dhtSeries = $dhtRows
            ->reverse()
            ->values()
            ->map(function (Dht11Reading $row): array {
                $dt = Carbon::parse($row->created_at)->timezone('Asia/Jakarta');

                return [
                    'time' => $dt->format('H:i'),
                    'full_time' => $dt->format('d M Y, H:i:s').' WIB',
                    'temperature' => round((float) $row->temperature, 1),
                    'humidity' => round((float) $row->humidity, 1),
                    'heat_index' => $row->heat_index !== null ? round((float) $row->heat_index, 1) : null,
                    'status' => $row->status ?? 'Optimal',
                    'source' => $row->source ?? 'esp32',
                ];
            })
            ->all();

        $leafGrouped = [];
        $leafTotal = 0;
        foreach (LeafDetection::selectRaw('class_name, COUNT(*) as total')->groupBy('class_name')->get() as $row) {
            $count = (int) $row->total;
            $leafTotal += $count;
            $label = $this->leafInference->formatClassName((string) $row->class_name);
            $leafGrouped[$label] = ($leafGrouped[$label] ?? 0) + $count;
        }
        arsort($leafGrouped);
        $leafSummary = [];
        foreach (array_slice($leafGrouped, 0, 6, true) as $className => $total) {
            $leafSummary[] = [
                'class_name' => (string) $className,
                'total' => $total,
            ];
        }

        $sprayCounts = SprayHistory::selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $spraySummary = [
            'water_total' => (int) ($sprayCounts['water'] ?? 0),
            'pesticide_total' => (int) ($sprayCounts['pesticide'] ?? 0),
        ];

        return [
            'soil_series' => $soilSeries,
            'dht_series' => $dhtSeries,
            'leaf_summary' => $leafSummary,
            'leaf_total_saved' => $leafTotal,
            'spray_summary' => $spraySummary,
        ];
    }

    /**
     * GET /api/reports/export/{type}
     * Ekspor laporan CSV untuk data sensor, deteksi daun, dan histori penyemprotan.
     */
    public function exportCsvReport(string $type): StreamedResponse|JsonResponse
    {
        $normalized = strtolower(trim($type));
        if ($normalized === 'dht22') {
            $normalized = 'dht11';
        }
        $timestamp = now('Asia/Jakarta')->format('Ymd_His');

        $configs = [
            'soil' => [
                'filename' => "chilivision_soil_moisture_{$timestamp}.csv",
                'headers' => ['ID', 'Waktu (WIB)', 'Kelembapan (%)', 'ADC', 'Status', 'Sumber', 'Rekomendasi'],
                'rows' => fn () => SoilMoistureReading::latest('id')->take(500)->get()->map(fn (SoilMoistureReading $r) => [
                    $r->id,
                    Carbon::parse($r->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    $r->moisture_percent,
                    $r->adc_value,
                    $r->status,
                    $r->source,
                    $r->recommendation,
                ])->all(),
            ],
            'dht11' => [
                'filename' => "chilivision_dht22_{$timestamp}.csv",
                'headers' => ['ID', 'Waktu (WIB)', 'Suhu (C)', 'Kelembapan Udara (%)', 'Heat Index (C)', 'Status', 'Sumber', 'Rekomendasi'],
                'rows' => fn () => Dht11Reading::latest('id')->take(500)->get()->map(fn (Dht11Reading $r) => [
                    $r->id,
                    Carbon::parse($r->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    $r->temperature,
                    $r->humidity,
                    $r->heat_index,
                    $r->status,
                    $r->source,
                    $r->recommendation,
                ])->all(),
            ],
            'leaf' => [
                'filename' => "chilivision_leaf_detections_{$timestamp}.csv",
                'headers' => ['ID', 'Waktu (WIB)', 'Kelas/Penyakit', 'Confidence (%)', 'Model', 'Rekomendasi'],
                'rows' => fn () => LeafDetection::latest('id')->take(500)->get()->map(fn (LeafDetection $r) => [
                    $r->id,
                    Carbon::parse($r->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    $this->leafInference->formatClassName((string) $r->class_name),
                    round(((float) $r->confidence) * 100, 2),
                    $r->model_id,
                    $r->recommendation,
                ])->all(),
            ],
            'spray' => [
                'filename' => "chilivision_spray_history_{$timestamp}.csv",
                'headers' => ['ID', 'Waktu Semprot (WIB)', 'Tipe', 'Mode Pemicu', 'Dipicu Oleh', 'Durasi (detik)', 'Status', 'Kondisi Daun', 'Kelembapan Tanah (%)', 'Catatan'],
                'rows' => fn () => SprayHistory::orderByDesc('sprayed_at')->orderByDesc('id')->take(500)->get()->map(fn (SprayHistory $r) => [
                    $r->id,
                    Carbon::parse($r->sprayed_at ?? $r->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    $r->type,
                    $r->trigger_mode,
                    $r->triggered_by,
                    $r->duration_seconds,
                    $r->status,
                    $r->leaf_condition !== null ? $this->leafInference->formatClassName((string) $r->leaf_condition) : null,
                    $r->soil_moisture,
                    $r->notes,
                ])->all(),
            ],
        ];

        if (! isset($configs[$normalized])) {
            return response()->json([
                'status' => 'error',
                'message' => "Tipe laporan '{$type}' tidak valid. Gunakan: soil, dht11, leaf, atau spray.",
            ], 404);
        }

        $cfg = $configs[$normalized];

        return response()->streamDownload(function () use ($cfg): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM agar Excel langsung membaca karakter UTF-8 dengan benar
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $cfg['headers']);
            foreach (($cfg['rows'])() as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $cfg['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ringkasan status terpadu seluruh layanan backend (Inference, Gemini AI, Telegram, Scheduler).
     *
     * @return array<string, mixed>
     */
    private function getSystemServicesStatus(): array
    {
        $inference = $this->leafInference->getEngineStatus();
        $tgConfig = $this->getTelegramConfig();
        $geminiEnabled = trim((string) config('services.gemini.api_key', '')) !== '';

        return [
            'laravel' => 'ok',
            'inference_engine' => $inference,
            'gemini_ai' => [
                'enabled' => $geminiEnabled,
                'model' => (string) config('services.gemini.model', 'gemini-3.5-flash-lite'),
            ],
            'telegram_bot' => [
                'configured' => trim((string) ($tgConfig['token'] ?? '')) !== '' && trim((string) ($tgConfig['chat_id'] ?? '')) !== '',
                'unreachable' => (bool) Cache::get('telegram_unreachable', false),
            ],
            'scheduler' => [
                'command' => 'chilivision:check-schedule',
                'interval' => 'everyMinute',
            ],
        ];
    }

    /**
     * =========================================================================
     * 8. DASHBOARD STATE AGGREGATOR (Untuk Sinkronisasi Real-Time Web UI)
     * =========================================================================
     */
    public function dashboardState(): JsonResponse
    {
        $buildSnapshot = function (): array {
            $pesticideSchedule = $this->evaluateTwoDayPesticideSchedule();

            $soilRows = SoilMoistureReading::latest('id')->take(20)->get();
            $dhtRows = Dht11Reading::latest('id')->take(20)->get();
            $latestLeaf = $this->formatLeafDetectionRecord(LeafDetection::latest('id')->first());
            $latestSoil = $soilRows->first();
            $latestDht11 = $dhtRows->first();

            $recentSpraysCollection = SprayHistory::orderByDesc('sprayed_at')
                ->orderByDesc('id')
                ->take(10)
                ->get();

            $lastWaterModel = $recentSpraysCollection->firstWhere('type', 'water')
                ?? SprayHistory::where('type', 'water')->orderByDesc('sprayed_at')->orderByDesc('id')->first();

            $lastPesticide = $pesticideSchedule['last_spray'] ?? $this->formatSprayHistory(
                $recentSpraysCollection->firstWhere('type', 'pesticide')
                    ?? SprayHistory::where('type', 'pesticide')->orderByDesc('sprayed_at')->orderByDesc('id')->first()
            );

            $recentSprays = $recentSpraysCollection
                ->map(fn (SprayHistory $item) => $this->formatSprayHistory($item));

            $chatMessages = ChatbotMessage::latest('id')->take(30)->get()->reverse()->values();
            $analytics = $this->buildDashboardAnalytics($soilRows, $dhtRows);
            $totalLeafSaved = (int) ($analytics['leaf_total_saved'] ?? 0);
            unset($analytics['leaf_total_saved']);

            return [
                'latest_leaf' => $latestLeaf?->toArray(),
                'total_leaf_saved' => $totalLeafSaved,
                'soil_moisture' => $latestSoil?->toArray(),
                'dht11' => $latestDht11?->toArray(),
                'analytics' => $analytics,
                'last_water_spray' => $this->formatSprayHistory($lastWaterModel),
                'last_pesticide_spray' => $lastPesticide,
                'pesticide_schedule_2_days' => $pesticideSchedule,
                'recent_sprays' => $recentSprays->values()->all(),
                'chatbot_messages' => $chatMessages->map(fn (ChatbotMessage $msg) => $msg->toArray())->values()->all(),
            ];
        };

        $snapshot = app()->runningUnitTests()
            ? $buildSnapshot()
            : Cache::remember('dashboard_state_db_snapshot', now()->addSeconds(5), $buildSnapshot);

        return response()->json([
            'status' => 'success',
            'leaf_detection' => [
                'latest' => $snapshot['latest_leaf'],
                'total_saved' => $snapshot['total_leaf_saved'],
                'auto_pesticide_on_disease' => false,
            ],
            'soil_moisture' => $snapshot['soil_moisture'],
            'dht11' => $snapshot['dht11'],
            'esp32_status' => $this->getEsp32ConnectionStatus(),
            'system_services' => $this->getSystemServicesStatus(),
            'analytics' => $snapshot['analytics'],
            'spray_history' => [
                'last_water_spray' => $snapshot['last_water_spray'],
                'last_pesticide_spray' => $snapshot['last_pesticide_spray'],
                'pesticide_schedule_2_days' => $snapshot['pesticide_schedule_2_days'],
                'recent_sprays' => $snapshot['recent_sprays'],
            ],
            'chatbot_messages' => $snapshot['chatbot_messages'],
            'selected_model_id' => $this->getSelectedTelegramModelId(),
            'selected_model_version' => (int) Cache::get('selected_model_version', 0),
            'auto_water_threshold' => $this->getAutoWaterThreshold(),
            'server_time' => now('Asia/Jakarta')->format('d M Y, H:i:s').' WIB',
        ]);
    }

    /**
     * =========================================================================
     * 9. ENDPOINT POLLING KHUSUS ESP32 (ARDUINO IDE)
     * GET /api/esp32/poll
     * Mengambil perintah penyemprotan manual atau jadwal 2 hari sekali pestisida
     * =========================================================================
     */
    public function esp32PollCommands(Request $request): JsonResponse
    {
        $isRealEsp32 = $this->isRealEsp32PollRequest($request);
        if ($isRealEsp32) {
            $this->recordEsp32Heartbeat('poll');
        }

        // Evaluasi jadwal pestisida setiap kali ESP32 melakukan polling
        $pesticideSchedule = $this->evaluateTwoDayPesticideSchedule();

        $queuedSprays = SprayHistory::where('status', 'queued')
            ->whereIn('type', ['water', 'pesticide'])
            ->orderBy('id')
            ->get();

        $pendingWater = $queuedSprays->firstWhere('type', 'water');
        $pendingPesticide = $queuedSprays->firstWhere('type', 'pesticide');

        $sprayWater = false;
        $waterDuration = 0;
        if ($pendingWater) {
            $sprayWater = true;
            $waterDuration = (int) $pendingWater->duration_seconds;
            if ($isRealEsp32) {
                $pendingWater->update(['status' => 'completed']);
            }
        }

        $sprayPesticide = false;
        $pesticideDuration = 0;
        if ($pendingPesticide) {
            $sprayPesticide = true;
            $pesticideDuration = (int) $pendingPesticide->duration_seconds;
            if ($isRealEsp32) {
                $pendingPesticide->update(['status' => 'completed']);
            }
        }

        $fetchLatestLeaf = function (): array {
            $latestLeaf = $this->formatLeafDetectionRecord(LeafDetection::latest('id')->first());
            $className = $latestLeaf->class_name ?? 'Belum ada deteksi';

            return [
                'class_name' => $className,
                'confidence' => $latestLeaf->confidence ?? 0,
                'model_id' => $latestLeaf->model_id ?? '-',
                'is_disease' => $latestLeaf ? $this->isDiseaseClass((string) $className) : false,
                'recommendation' => $latestLeaf->recommendation ?? '-',
                'detected_at' => ($latestLeaf && $latestLeaf->created_at)
                    ? Carbon::parse($latestLeaf->created_at)->timezone('Asia/Jakarta')->format('d M Y, H:i:s').' WIB'
                    : '-',
                'event_key' => $latestLeaf ? 'id_'.$latestLeaf->id : 'none',
            ];
        };

        $leafSummary = app()->runningUnitTests()
            ? $fetchLatestLeaf()
            : Cache::remember('esp32_latest_leaf_summary', now()->addSeconds(30), $fetchLatestLeaf);

        $payload = [
            'status' => 'ok',
            'spray_water' => $sprayWater,
            'water_duration_seconds' => $waterDuration,
            'spray_pesticide' => $sprayPesticide,
            'pesticide_duration_seconds' => $pesticideDuration,
            'auto_water_threshold' => $this->getAutoWaterThreshold(),
            'pesticide_schedule_mode' => $pesticideSchedule['schedule_mode'],
            'pesticide_interval_hours' => $pesticideSchedule['interval_hours'],
            'pesticide_interval_label' => $pesticideSchedule['interval_label'],
            'pesticide_next_spray_formatted' => $pesticideSchedule['next_spray_formatted'],
            'pesticide_target_datetime' => $pesticideSchedule['target_datetime'],
            'latest_leaf_condition' => $leafSummary['class_name'],
            'latest_leaf_confidence' => $leafSummary['confidence'],
            'latest_leaf_model' => $leafSummary['model_id'] ?? '-',
            'latest_leaf_is_disease' => (bool) ($leafSummary['is_disease'] ?? false),
            'latest_leaf_recommendation' => $leafSummary['recommendation'] ?? '-',
            'latest_leaf_detected_at' => $leafSummary['detected_at'] ?? '-',
            'latest_leaf_event_key' => $leafSummary['event_key'] ?? 'none',
        ];

        if (! $isRealEsp32) {
            $payload['preview_mode'] = true;
            $payload['heartbeat_recorded'] = false;
        }

        return response()->json($payload);
    }

    /**
     * =========================================================================
     * 10. WEBHOOK TELEGRAM BOT
     * POST /api/telegram/webhook
     * Menerima pesan langsung dari Telegram Bot, memproses di Chatbot, & membalas ke Telegram
     * =========================================================================
     */
    public function telegramWebhook(Request $request): JsonResponse
    {
        $chatId = $request->input('message.chat.id');
        $text = $request->input('message.text');

        if (! $text) {
            return response()->json(['status' => 'ignored']);
        }

        $internalReq = new Request(['message' => $text]);
        $chatRes = $this->sendChatbotMessage($internalReq);
        $payload = $chatRes->getData(true);
        $botReply = $payload['bot_reply'] ?? [];
        $replyText = $botReply['message'] ?? 'Perintah diterima.';
        $inlineKeyboard = null;
        if (($botReply['intent'] ?? null) === 'select_model_menu' && is_array($botReply['metadata_json']['models'] ?? null)) {
            $inlineKeyboard = $this->buildModelInlineKeyboard($botReply['metadata_json']['models']);
        }

        if ($chatId) {
            $this->notifyTelegramIfConfigured($replyText, (string) $chatId, $inlineKeyboard);
        }

        return response()->json([
            'status' => 'ok',
            'telegram_chat_id' => $chatId,
            'reply' => $replyText,
        ]);
    }
}
