<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use App\Models\Dht11Reading;
use App\Models\LeafDetection;
use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mengelola integrasi Gemini API (Interactions API + Structured Output) beserta konteks sensor IoT.
 */
class GeminiChatService
{
    public function __construct(
        private readonly LeafInferenceService $leafInference,
        private readonly PesticideScheduleService $pesticideSchedule,
    ) {}

    /**
     * Ambil batas kelembapan tanah (%) untuk penyemprotan air otomatis (default: 30%).
     */
    public function getAutoWaterThreshold(): int
    {
        $threshold = (int) Cache::get('auto_water_moisture_threshold', 30);

        return max(1, min(95, $threshold));
    }

    /**
     * Ekstrak permintaan ubah/cek batas kelembapan tanah untuk semprot air otomatis dari teks chat.
     *
     * @return array{threshold?: int, needs_value?: bool, invalid?: bool, error?: string}|null
     */
    public function parseAutoWaterThresholdRequest(string $lower): ?array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $lower) ?? '');
        if ($normalized === '' || str_contains($normalized, 'pestisida')) {
            return null;
        }

        if (preg_match('/\b(detik|sec|menit)\b/u', $normalized) === 1 && ! preg_match('/\b(batas|ambang|threshold|persen)\b|%/u', $normalized)) {
            return null;
        }

        $mentionsWaterOrSoil = str_contains($normalized, 'air')
            || str_contains($normalized, 'tanah')
            || str_contains($normalized, 'soil')
            || str_contains($normalized, 'kelembapan')
            || str_contains($normalized, 'moisture')
            || str_contains($normalized, 'siram')
            || str_contains($normalized, 'penyiraman');

        $mentionsThresholdCue = str_contains($normalized, 'batas')
            || str_contains($normalized, 'ambang')
            || str_contains($normalized, 'threshold')
            || str_contains($normalized, 'dibawah')
            || str_contains($normalized, 'di bawah')
            || str_contains($normalized, 'kurang dari')
            || str_contains($normalized, '<')
            || (str_contains($normalized, 'otomatis') && preg_match('/\b(ubah|ganti|atur|set|setting|rubah|cek)\b/u', $normalized) === 1);

        if (! $mentionsWaterOrSoil || ! $mentionsThresholdCue) {
            return null;
        }

        $hasImperative = preg_match('/\b(ubah|ganti|atur|set|setting|rubah|naikkan|turunkan|jadikan|buat|cek|berapa)\b/u', $normalized) === 1;
        $isPureQuestion = preg_match('/\b(kenapa|mengapa|apakah|bagaimana|gimana|jelaskan)\b/u', $normalized) === 1;
        if ($isPureQuestion && ! $hasImperative) {
            return null;
        }

        if (! $hasImperative && preg_match('/(?:dibawah|di bawah|kurang dari|<|batas|ambang|threshold)\s*-?\d+/u', $normalized) !== 1) {
            return null;
        }

        $wordMap = [
            'sembilan puluh lima' => '95',
            'sembilan puluh' => '90',
            'delapan puluh lima' => '85',
            'delapan puluh' => '80',
            'tujuh puluh lima' => '75',
            'tujuh puluh' => '70',
            'enam puluh lima' => '65',
            'enam puluh' => '60',
            'lima puluh lima' => '55',
            'lima puluh' => '50',
            'empat puluh lima' => '45',
            'empat puluh' => '40',
            'tiga puluh lima' => '35',
            'tiga puluh' => '30',
            'dua puluh lima' => '25',
            'dua puluh' => '20',
            'lima belas' => '15',
            'sepuluh' => '10',
        ];
        $withDigits = strtr($normalized, $wordMap);

        if (preg_match_all('/(-?\d{1,3})\s*(?:%|persen)?/u', $withDigits, $matches) > 0) {
            $lastRaw = end($matches[1]);
            $value = (int) $lastRaw;
            if ($value < 1 || $value > 95) {
                return [
                    'invalid' => true,
                    'error' => 'Batas kelembapan tanah untuk semprot air otomatis harus antara **1% sampai 95%** (contoh: `ubah batas semprot air 40%`).',
                ];
            }

            return ['threshold' => $value];
        }

        return ['needs_value' => true];
    }

    /**
     * Tentukan apakah pesan merupakan command cepat/deterministik yang tidak perlu memanggil Gemini.
     */
    public function isFastDeterministicCommand(string $lower): bool
    {
        if ($lower === '' || str_starts_with($lower, '/')) {
            return true;
        }

        $exactFastCommands = [
            'ya', 'iya', 'yes', 'setuju', 'konfirmasi', 'ya semprot', 'semprot sekarang',
            'tidak', 'nggak', 'no', 'batal', 'jangan', 'abaikan', 'jangan semprot',
            'model', 'pilih model', 'ganti model', 'cek model', 'daftar model', 'pakai model apa', 'model apa', 'ubah model',
            'status', 'menu', 'help', 'bantuan',
            'soil', 'tanah', 'moisture', 'cek tanah', 'cek soil',
            'dht', 'dht11', 'dht22', 'suhu', 'temperatur', 'cek suhu', 'cek dht', 'cek dht11', 'cek dht22',
            'daun', 'penyakit', 'deteksi', 'cek daun', 'cek penyakit',
            'histori', 'riwayat', 'jadwal', 'cek histori', 'cek riwayat', 'cek jadwal',
            'semprot air', 'siram air', 'siram tanaman', 'nyalakan air', 'pompa air', 'spray water',
            'semprot pestisida', 'siram pestisida', 'nyalakan pestisida', 'pompa pestisida', 'spray pesticide',
        ];

        if (in_array($lower, $exactFastCommands, true)) {
            return true;
        }

        if ($this->pesticideSchedule->parseIntervalRequest($lower) !== null) {
            return true;
        }

        if ($this->parseAutoWaterThresholdRequest($lower) !== null) {
            return true;
        }

        if ($this->leafInference->resolveModelFromChatText($lower) !== null) {
            return true;
        }

        if (preg_match('/^(semprot|siram)\s+(air|pestisida)(\s+\d+\s*(detik|s|sec))?$/i', $lower) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Panggil Gemini API dengan Structured Output lalu ekstrak keputusan aksi + balasan AI.
     *
     * @param  callable(SprayHistory|null): (array<string, mixed>|null)  $formatSprayFn
     * @param  callable(string): void  $notifyFn
     * @return array{reply: string, action: string, duration_seconds: int|null, interval_hours: int|null, auto_water_threshold: int|null, target_datetime: string|null, model_id: string|null, model_used: string}|null
     */
    public function askStructured(string $userText, ChatbotMessage $currentUserMsg, callable $formatSprayFn, callable $notifyFn): ?array
    {
        $apiKey = trim((string) config('services.gemini.api_key', ''));
        if ($apiKey === '') {
            return null;
        }

        $endpoint = (string) config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/interactions');
        $primaryModel = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $fallbackModel = (string) config('services.gemini.fallback_model', 'gemini-3.8-flash');
        $timeout = max(5, (int) config('services.gemini.timeout', 15));

        $modelsToTry = [$primaryModel];
        if ($fallbackModel !== '' && $fallbackModel !== $primaryModel) {
            $modelsToTry[] = $fallbackModel;
        }

        $systemInstruction = implode("\n", [
            'Kamu adalah ChiliVision Smart Assistant, asisten agronomi tanaman cabai dan pengontrol perangkat IoT kebun cabai berbahasa Indonesia.',
            'Jawab dengan ramah, ringkas, praktis, akurat berdasarkan DATA AKTUAL SISTEM CHILIVISION dan BASIS PENGETAHUAN JURNAL CHILIVISION yang diberikan.',
            'Saat menjelaskan gejala, penyebab, atau penanganan penyakit daun cabai, selalu gunakan rujukan dari BASIS PENGETAHUAN JURNAL CHILIVISION dan sebutkan sumber jurnalnya secara singkat.',
            'Jangan mengarang angka sensor; jika data belum ada, katakan belum ada.',
            'Kamu bisa mengeksekusi aksi sistem dengan mengisi field `action`:',
            '- `none`: untuk tanya jawab, sapaan, konsultasi penyakit daun cabai, langkah perawatan, atau saran penyiraman/pemupukan TANPA menyalakan pompa fisik.',
            '- `spray_water`: AKAN MENYALAKAN POMPA AIR FISIK ESP32 SEKARANG! Isi ini HANYA jika user secara eksplisit memerintahkan untuk menyiram/menyalakan pompa air sekarang (mis. "tolong siram air sekarang"). JANGAN gunakan untuk pertanyaan saran/tips. Isi `duration_seconds` (1-60, default 5).',
            '- `spray_pesticide`: AKAN MENYALAKAN POMPA PESTISIDA FISIK ESP32 SEKARANG! Isi ini HANYA jika user secara eksplisit memerintahkan penyemprotan pestisida sekarang. JANGAN gunakan untuk pertanyaan konsultasi. Isi `duration_seconds` (1-60, default 5).',
            '- `update_pesticide_schedule`: jika user meminta mengubah interval rutin jadwal pestisida (mis. "2 hari sekali", "12 jam sekali"). Isi `interval_hours` (1-720, mis. 2 hari = 48, 3 hari = 72, 1 minggu = 168).',
            '- `set_pesticide_target_datetime`: jika user meminta menjadwalkan penyemprotan pestisida pada TANGGAL atau JAM KALENDER SPESIFIK (mis. "besok jam 7 pagi", "tanggal 15 november jam 08:00", "2026-11-16 09:00"). Hitung berdasarkan Waktu Saat Ini (WIB) dan isi `target_datetime` dengan format `YYYY-MM-DD HH:MM` (zona waktu WIB). Opsional isi `duration_seconds` (1-60, default 5).',
            '- `update_auto_water_threshold`: jika user meminta mengubah ambang batas (%) kelembapan tanah untuk penyiraman air otomatis (default 30%). Isi `auto_water_threshold` (bilangan bulat 1-95).',
            '- `select_model`: jika user meminta mengganti model AI daun ke model spesifik. Isi `model_id` persis dari daftar model tersedia.',
            '- `show_model_menu`: jika user ingin melihat pilihan tombol model AI.',
            '- `check_soil`, `check_dht11`, `check_leaf`, `check_history`, `status`: jika user semata-mata meminta laporan data terkait.',
            'Gunakan markdown sederhana yang rapi (bold dengan **teks**) agar enak dibaca di Website dan Telegram.',
        ]);

        $contextSummary = $this->buildContextSummary($currentUserMsg, $formatSprayFn, $notifyFn);
        $prompt = "{$contextSummary}\n\nPESAN USER TERBARU:\n{$userText}";

        $schema = [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string'],
                'action' => [
                    'type' => 'string',
                    'enum' => [
                        'none',
                        'spray_water',
                        'spray_pesticide',
                        'update_pesticide_schedule',
                        'set_pesticide_target_datetime',
                        'update_auto_water_threshold',
                        'select_model',
                        'show_model_menu',
                        'check_soil',
                        'check_dht11',
                        'check_leaf',
                        'check_history',
                        'status',
                    ],
                ],
                'duration_seconds' => ['type' => 'integer'],
                'interval_hours' => ['type' => 'integer'],
                'auto_water_threshold' => ['type' => 'integer'],
                'target_datetime' => ['type' => 'string'],
                'model_id' => ['type' => 'string'],
            ],
            'required' => ['reply', 'action'],
        ];

        $hasCaBundle = trim((string) ini_get('curl.cainfo')) !== '' || trim((string) ini_get('openssl.cafile')) !== '';
        $disableSslVerify = (! $hasCaBundle && app()->environment(['local', 'testing']))
            || config('services.gemini.verify_ssl') === false;

        foreach ($modelsToTry as $idx => $modelName) {
            try {
                $httpClient = Http::timeout($timeout)->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Api-Revision' => '2026-05-20',
                ]);

                if ($disableSslVerify) {
                    $httpClient = $httpClient->withoutVerifying();
                }

                $response = $httpClient->post($endpoint, [
                    'model' => $modelName,
                    'system_instruction' => $systemInstruction,
                    'input' => $prompt,
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => $schema,
                    ],
                    'generation_config' => [
                        'max_output_tokens' => 700,
                    ],
                ]);

                if (! $response->successful()) {
                    Log::warning('Gemini API request failed', [
                        'model' => $modelName,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 300),
                    ]);

                    if (in_array($response->status(), [404, 429, 500, 502, 503], true) && $idx < count($modelsToTry) - 1) {
                        continue;
                    }

                    return null;
                }

                $payload = $response->json();
                if (! is_array($payload)) {
                    return null;
                }

                $rawText = $this->extractResponseText($payload);
                if ($rawText === null) {
                    return null;
                }

                $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $rawText) ?? $rawText;
                $decoded = json_decode(trim($cleanJson), true);
                if (! is_array($decoded) || empty($decoded['reply'])) {
                    return null;
                }

                return [
                    'reply' => trim((string) $decoded['reply']),
                    'action' => (string) ($decoded['action'] ?? 'none'),
                    'duration_seconds' => isset($decoded['duration_seconds']) ? (int) $decoded['duration_seconds'] : null,
                    'interval_hours' => isset($decoded['interval_hours']) ? (int) $decoded['interval_hours'] : null,
                    'auto_water_threshold' => isset($decoded['auto_water_threshold']) ? (int) $decoded['auto_water_threshold'] : null,
                    'target_datetime' => isset($decoded['target_datetime']) ? trim((string) $decoded['target_datetime']) : null,
                    'model_id' => isset($decoded['model_id']) ? trim((string) $decoded['model_id']) : null,
                    'model_used' => $modelName,
                ];
            } catch (\Throwable $e) {
                Log::warning('Gemini API exception: '.$e->getMessage(), ['model' => $modelName]);
                if ($idx < count($modelsToTry) - 1) {
                    continue;
                }

                return null;
            }
        }

        return null;
    }

    /**
     * Kumpulkan konteks sensor, daun, jadwal, model aktif, dan riwayat chat terbaru untuk Gemini.
     *
     * @param  callable(SprayHistory|null): (array<string, mixed>|null)  $formatSprayFn
     * @param  callable(string): void  $notifyFn
     */
    private function buildContextSummary(ChatbotMessage $currentUserMsg, callable $formatSprayFn, callable $notifyFn): string
    {
        $leaf = LeafDetection::latest('id')->first();
        $soil = SoilMoistureReading::latest('id')->first();
        $dht = Dht11Reading::latest('id')->first();
        $lastWater = $formatSprayFn(SprayHistory::where('type', 'water')->orderByDesc('sprayed_at')->orderByDesc('id')->first());
        $lastPest = $formatSprayFn(SprayHistory::where('type', 'pesticide')->orderByDesc('sprayed_at')->orderByDesc('id')->first());
        $scheduleInfo = $this->pesticideSchedule->evaluate($formatSprayFn, $notifyFn);
        $selectedModelId = $this->leafInference->getSelectedModelId();

        $modelsSummary = [];
        foreach ($this->leafInference->getModelDefinitions() as $def) {
            $avail = is_file($def['path']) ? 'tersedia' : 'tidak tersedia';
            $active = $def['id'] === $selectedModelId ? ', AKTIF' : '';
            $modelsSummary[] = "{$def['id']} ({$def['name']}: {$avail}{$active})";
        }

        $leafLine = $leaf
            ? $this->leafInference->formatClassName((string) $leaf->class_name).' (confidence '.round($leaf->confidence * 100, 1)."%, model {$leaf->model_id}, rekomendasi: {$leaf->recommendation})"
            : 'Belum ada data deteksi daun';
        $soilLine = $soil
            ? "{$soil->moisture_percent}% (ADC {$soil->adc_value}, status: {$soil->status})"
            : 'Belum ada data sensor tanah';
        $dhtLine = $dht
            ? "Suhu {$dht->temperature}C, Kelembapan {$dht->humidity}%, Heat Index {$dht->heat_index}C ({$dht->status})"
            : 'Belum ada data sensor DHT22';
        $waterLine = $lastWater ? "{$lastWater['sprayed_at_formatted']} ({$lastWater['time_ago']}, via {$lastWater['triggered_by']})" : 'Belum pernah';
        $pestLine = $lastPest ? "{$lastPest['sprayed_at_formatted']} ({$lastPest['time_ago']}, via {$lastPest['triggered_by']})" : 'Belum pernah';

        $recentMessages = ChatbotMessage::where('id', '<', $currentUserMsg->id)
            ->latest('id')
            ->take(6)
            ->get()
            ->reverse();

        $historyLines = [];
        foreach ($recentMessages as $msg) {
            $role = $msg->sender === 'user' ? 'User' : 'Assistant';
            $snippet = mb_substr(preg_replace('/\s+/', ' ', (string) $msg->message) ?? '', 0, 220);
            if ($snippet !== '') {
                $historyLines[] = "- {$role}: {$snippet}";
            }
        }
        $historyBlock = $historyLines === [] ? '- Belum ada percakapan sebelumnya.' : implode("\n", $historyLines);
        $nowWib = now('Asia/Jakarta');
        $autoWaterThreshold = $this->getAutoWaterThreshold();
        $knowledgeBlock = $this->buildJournalKnowledgeContext($leaf);

        return implode("\n", [
            'DATA AKTUAL SISTEM CHILIVISION (WIB):',
            '- Waktu Saat Ini: '.$nowWib->format('Y-m-d H:i:s').' WIB ('.$nowWib->translatedFormat('l, d F Y').')',
            "- Deteksi Daun Terakhir: {$leafLine}",
            "- Sensor Soil Moisture: {$soilLine} (otomatis siram air jika <{$autoWaterThreshold}%)",
            "- Sensor DHT22: {$dhtLine}",
            "- Terakhir Semprot Air: {$waterLine}",
            "- Terakhir Semprot Pestisida: {$pestLine}",
            "- Jadwal Pestisida Saat Ini: {$scheduleInfo['interval_label']} (mode {$scheduleInfo['schedule_mode']}, interval rutin {$scheduleInfo['recurring_interval_label']} / {$scheduleInfo['interval_hours']} jam), berikutnya {$scheduleInfo['next_spray_formatted']} ({$scheduleInfo['time_until_next']})",
            '- Model AI Daun: '.implode('; ', $modelsSummary),
            'RIWAYAT CHAT TERBARU:',
            $historyBlock,
            $knowledgeBlock,
        ]);
    }

    /**
     * Muat basis pengetahuan gejala, penyebab, dan penanganan dari jurnal terverifikasi.
     */
    private function buildJournalKnowledgeContext(?LeafDetection $leaf): string
    {
        $path = storage_path('app/chili_disease_knowledge.json');
        if (! is_file($path)) {
            return '';
        }

        $data = json_decode((string) file_get_contents($path), true);
        $diseases = $data['diseases'] ?? null;
        if (! is_array($diseases) || $diseases === []) {
            return '';
        }

        $detectedClass = $leaf ? strtolower(trim((string) $leaf->class_name)) : '';
        $lines = ['BASIS PENGETAHUAN JURNAL CHILIVISION (GEJALA, PENYEBAB, PENANGANAN):'];

        $env = $data['metadata']['optimal_environment'] ?? null;
        if (is_array($env)) {
            $lines[] = "- Syarat Tumbuh Optimal Cabai: Suhu {$env['temperature_c']}, Sinar matahari {$env['sunlight']} (Sumber: {$env['source']}).";
        }

        foreach ($diseases as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }
            $aliases = array_map('strtolower', (array) ($item['aliases'] ?? []));
            $isDetected = $detectedClass !== '' && (in_array($detectedClass, $aliases, true) || $detectedClass === strtolower((string) ($item['id'] ?? '')));
            $tag = $isDetected ? ' [TERDETEKSI SAAT INI]' : '';
            $lines[] = "* {$item['name']}{$tag} (Sumber: {$item['source']}):";
            $lines[] = "  - Penyebab: {$item['penyebab']}";
            $lines[] = "  - Gejala: {$item['gejala']}";
            $lines[] = "  - Penanganan: {$item['penanganan']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Ekstrak teks respons dari payload Gemini Interactions API.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractResponseText(array $payload): ?string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text']) && trim($payload['output_text']) !== '') {
            return trim($payload['output_text']);
        }

        foreach (['steps', 'outputs'] as $containerKey) {
            $items = $payload[$containerKey] ?? null;
            if (! is_array($items)) {
                continue;
            }

            foreach (array_reverse($items) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (isset($item['text']) && is_string($item['text']) && trim($item['text']) !== '') {
                    return trim($item['text']);
                }
                $contents = $item['content'] ?? null;
                if (! is_array($contents)) {
                    continue;
                }
                foreach ($contents as $part) {
                    if (is_array($part) && ($part['type'] ?? 'text') === 'text' && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                        return trim($part['text']);
                    }
                }
            }
        }

        return null;
    }
}
