<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mengelola definisi model Computer Vision, pemilihan model aktif, hashing kemiripan,
 * serta inferensi gambar daun melalui worker FastAPI (warm) dengan fallback ke inference_cli.py.
 */
class LeafInferenceService
{
    /**
     * Daftar definisi model Computer Vision yang tersedia di folder models/.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getModelDefinitions(): array
    {
        $modelsDir = base_path('models');

        return [
            'yolov8n-chili' => [
                'id' => 'yolov8n-chili',
                'name' => 'Yolov8n (Deteksi Penyakit Daun Cabai)',
                'filename' => 'yolov8n_chili.onnx',
                'path' => $modelsDir.DIRECTORY_SEPARATOR.'yolov8n_chili.onnx',
                'kind' => 'yolo',
                'default_threshold' => 0.25,
            ],
            'cnn-5ch-chili' => [
                'id' => 'cnn-5ch-chili',
                'name' => 'CNN (Klasifikasi Penyakit Daun Cabai)',
                'filename' => 'cnn_5ch_7kelas_hard_negative.onnx',
                'path' => $modelsDir.DIRECTORY_SEPARATOR.'cnn_5ch_7kelas_hard_negative.onnx',
                'kind' => 'classification',
                'default_threshold' => 0.25,
            ],
        ];
    }

    /**
     * Ambil ID model aktif (default: yolov8n-chili).
     */
    public function getSelectedModelId(): string
    {
        $selected = (string) Cache::get('telegram_selected_model', 'yolov8n-chili');

        return array_key_exists($selected, $this->getModelDefinitions()) ? $selected : 'yolov8n-chili';
    }

    /**
     * Cocokkan ID model dari perintah teks chatbot (mis. "model yolov8s", "pilih cnn").
     */
    public function resolveModelFromChatText(string $lower): ?string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $lower) ?? '');
        $patterns = [
            'yolov8n-chili' => ['yolov8n-chili', 'yolov8n', 'yolo v8n', 'yolo v8', 'yolov8', 'yolo8'],
            'cnn-5ch-chili' => ['cnn-5ch-chili', 'cnn-6ch-chili', 'cnn 5-channel', 'cnn 5 channel', 'cnn 5ch', 'cnn5ch', 'cnn 6-channel', 'cnn 6 channel', 'cnn 6ch', 'cnn6ch', 'cnn'],
        ];

        foreach ($patterns as $modelId => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $normalized) === 1) {
                    return $modelId;
                }
            }
        }

        return null;
    }

    /**
     * Bangun daftar pilihan model untuk tombol inline chatbot website & Telegram.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildChatModelOptions(string $selectedId): array
    {
        $options = [];
        foreach ($this->getModelDefinitions() as $def) {
            $options[] = [
                'id' => $def['id'],
                'name' => $def['name'],
                'available' => is_file($def['path']),
                'selected' => $def['id'] === $selectedId,
            ];
        }

        return $options;
    }

    /**
     * Format nama kelas penyakit/kondisi daun agar konsisten tanpa underscore (mis. "bercak_daun" -> "Bercak Daun").
     */
    public function formatClassName(string $className): string
    {
        $trimmed = trim($className);
        if ($trimmed === '') {
            return '';
        }

        $knownLower = [
            'bercak bakteri',
            'bercak cercospora',
            'bercak putih',
            'bercak daun',
            'daun sehat',
            'defisiensi nutrisi',
            'virus keriting',
        ];

        if (str_contains($trimmed, '_')) {
            $spaced = preg_replace('/\s+/', ' ', str_replace('_', ' ', $trimmed)) ?? $trimmed;

            return mb_convert_case(trim($spaced), MB_CASE_TITLE, 'UTF-8');
        }

        if (in_array(mb_strtolower($trimmed, 'UTF-8'), $knownLower, true)) {
            return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
        }

        return $trimmed;
    }

    /**
     * Cek apakah label termasuk kelas hard-negative non-daun yang harus dianggap tidak ada objek.
     */
    public function isNonLeafClassName(string $className): bool
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(str_replace('_', ' ', trim($className)), 'UTF-8')) ?? '';

        return in_array($normalized, ['bukan daun', 'non leaf', 'non-leaf'], true);
    }

    /**
     * Cek apakah label termasuk kelas daun sehat.
     */
    public function isHealthyClassName(string $className): bool
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(str_replace('_', ' ', trim($className)), 'UTF-8')) ?? '';

        return $normalized !== '' && (str_contains($normalized, 'sehat') || $normalized === 'healthy');
    }

    /**
     * Pilih primary_detection dengan memprioritaskan daun berpenyakit di atas daun sehat.
     *
     * @param  array<int, array<string, mixed>>  $detections
     * @param  array<string, mixed>|null  $fallbackPrimary
     * @return array<string, mixed>|null
     */
    public function selectPrimaryDetection(array $detections, ?array $fallbackPrimary = null): ?array
    {
        $validDetections = array_values(array_filter(
            $detections,
            fn (mixed $item): bool => is_array($item) && isset($item['class_name']) && ! $this->isNonLeafClassName((string) $item['class_name'])
        ));

        if ($validDetections === []) {
            return $fallbackPrimary;
        }

        $diseasedDetections = array_values(array_filter(
            $validDetections,
            fn (array $item): bool => ! $this->isHealthyClassName((string) $item['class_name'])
        ));

        $candidates = $diseasedDetections !== [] ? $diseasedDetections : $validDetections;
        usort(
            $candidates,
            fn (array $a, array $b): int => (float) ($b['confidence'] ?? 0.0) <=> (float) ($a['confidence'] ?? 0.0)
        );

        return $candidates[0];
    }

    /**
     * Bangun daftar unik kondisi daun yang terdeteksi (terurut) untuk membandingkan perubahan multi-objek.
     *
     * @param  array<int, array<string, mixed>>  $detections
     * @param  array<string, mixed>|null  $primary
     * @return array<int, string>
     */
    public function extractDetectedConditionsSignature(array $detections, ?array $primary = null): array
    {
        $conditions = [];

        foreach ($detections as $detection) {
            if (! is_array($detection) || ! isset($detection['class_name'])) {
                continue;
            }

            $formatted = $this->formatClassName((string) $detection['class_name']);
            $lower = mb_strtolower(trim($formatted), 'UTF-8');
            if ($lower === '' || $lower === 'tidak terdeteksi' || $this->isNonLeafClassName($formatted)) {
                continue;
            }

            $conditions[$lower] = true;
        }

        if ($conditions === [] && is_array($primary) && isset($primary['class_name'])) {
            $formatted = $this->formatClassName((string) $primary['class_name']);
            $lower = mb_strtolower(trim($formatted), 'UTF-8');
            if ($lower !== '' && $lower !== 'tidak terdeteksi' && ! $this->isNonLeafClassName($formatted)) {
                $conditions[$lower] = true;
            }
        }

        $keys = array_keys($conditions);
        sort($keys);

        return $keys;
    }

    /**
     * Normalisasi seluruh label penyakit/daun pada payload hasil inferensi Python.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeInferencePayload(array $payload): array
    {
        if (isset($payload['classification']) && is_array($payload['classification']) && isset($payload['classification']['class_name'])) {
            $formatted = $this->formatClassName((string) $payload['classification']['class_name']);
            if ($this->isNonLeafClassName($formatted)) {
                $payload['classification'] = null;
                $payload['count'] = 0;
            } else {
                $payload['classification']['class_name'] = $formatted;
            }
        }

        if (isset($payload['primary_detection']) && is_array($payload['primary_detection']) && isset($payload['primary_detection']['class_name'])) {
            $formatted = $this->formatClassName((string) $payload['primary_detection']['class_name']);
            if ($this->isNonLeafClassName($formatted)) {
                $payload['primary_detection'] = null;
            } else {
                $payload['primary_detection']['class_name'] = $formatted;
            }
        }

        if (isset($payload['detections']) && is_array($payload['detections'])) {
            $filteredDetections = [];
            foreach ($payload['detections'] as $detection) {
                if (! is_array($detection) || ! isset($detection['class_name'])) {
                    continue;
                }
                $formatted = $this->formatClassName((string) $detection['class_name']);
                if ($this->isNonLeafClassName($formatted)) {
                    continue;
                }
                $detection['class_name'] = $formatted;
                $filteredDetections[] = $detection;
            }
            $payload['detections'] = $filteredDetections;
            if (! isset($payload['classification'])) {
                $payload['count'] = count($filteredDetections);
                $fallbackPrimary = is_array($payload['primary_detection'] ?? null) ? $payload['primary_detection'] : null;
                $payload['primary_detection'] = $this->selectPrimaryDetection($filteredDetections, $fallbackPrimary);
            }
        }

        return $payload;
    }

    /**
     * Rekomendasi penanganan berdasarkan label penyakit daun cabai.
     */
    public function getLeafRecommendation(string $className): string
    {
        $lower = str_replace('_', ' ', strtolower($className));
        $policyNote = ' (Pestisida tidak disemprot otomatis saat deteksi; gunakan Tombol Manual atau Jadwal Rutin).';
        if (str_contains($lower, 'sehat')) {
            return 'Daun cabai dalam kondisi sehat. Pertahankan kelembapan tanah optimal (40–65%) dan jadwal perawatan rutin.';
        }
        if (str_contains($lower, 'bercak bakteri')) {
            return 'Terdeteksi gejala bercak bakteri pada daun.'.$policyNote;
        }
        if (str_contains($lower, 'bercak cercospora')) {
            return 'Terdeteksi gejala bercak cercospora pada daun.'.$policyNote;
        }
        if (str_contains($lower, 'bercak putih')) {
            return 'Terdeteksi gejala bercak putih pada daun.'.$policyNote;
        }
        if (str_contains($lower, 'defisiensi') || str_contains($lower, 'nutrisi')) {
            return 'Terdeteksi gejala defisiensi nutrisi pada daun. Periksa keseimbangan pemupukan dan kelembapan media tanam.';
        }
        if (str_contains($lower, 'bercak') || str_contains($lower, 'antraknosa')) {
            return 'Terdeteksi gejala bercak daun/antraknosa.'.$policyNote;
        }
        if (str_contains($lower, 'keriting') || str_contains($lower, 'kuning') || str_contains($lower, 'virus')) {
            return 'Terdeteksi gejala keriting/virus kuning.'.$policyNote;
        }

        return 'Pantau kondisi daun secara berkala.'.$policyNote;
    }

    /**
     * Hitung 64-bit average perceptual hash (16 hex chars) untuk perbandingan kemiripan gambar daun.
     */
    public function computePerceptualHash(string $rawBytes): string
    {
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($rawBytes);
            if ($img !== false) {
                $small = imagecreatetruecolor(8, 8);
                imagecopyresampled($small, $img, 0, 0, 0, 0, 8, 8, imagesx($img), imagesy($img));
                $pixels = [];
                $sum = 0;
                for ($y = 0; $y < 8; $y++) {
                    for ($x = 0; $x < 8; $x++) {
                        $rgb = imagecolorat($small, $x, $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $gray = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                        $pixels[] = $gray;
                        $sum += $gray;
                    }
                }
                imagedestroy($small);
                imagedestroy($img);
                $avg = $sum / 64.0;
                $bits = '';
                foreach ($pixels as $px) {
                    $bits .= ($px >= $avg) ? '1' : '0';
                }
                $hex = '';
                for ($i = 0; $i < 64; $i += 4) {
                    $hex .= dechex(bindec(substr($bits, $i, 4)));
                }

                return $hex;
            }
        }

        return substr(md5($rawBytes), 0, 16);
    }

    /**
     * Hitung Hamming distance antara dua 16-char hex perceptual hash.
     */
    public function hammingDistance(string $hash1, string $hash2): int
    {
        if (strlen($hash1) !== strlen($hash2)) {
            return 64;
        }
        $dist = 0;
        for ($i = 0; $i < strlen($hash1); $i++) {
            $xor = hexdec($hash1[$i]) ^ hexdec($hash2[$i]);
            while ($xor > 0) {
                $dist += ($xor & 1);
                $xor >>= 1;
            }
        }

        return $dist;
    }

    /**
     * Jalankan inferensi daun menggunakan worker FastAPI warm jika aktif, atau fallback ke inference_cli.py.
     *
     * @return array<string, mixed>
     */
    public function runInference(string $filePath, string $originalName, string $modelId, float $threshold): array
    {
        $useFastApi = (bool) config('services.inference.use_fastapi', true);
        if ($useFastApi && ! Cache::get('fastapi_inference_offline', false)) {
            $fastApiResult = $this->runViaFastApi($filePath, $originalName, $modelId, $threshold);
            if ($fastApiResult !== null) {
                return $this->normalizeInferencePayload($fastApiResult);
            }
        }

        return $this->normalizeInferencePayload($this->runViaCli($filePath, $modelId, $threshold));
    }

    /**
     * Cek status worker inferensi FastAPI saat ini.
     *
     * @return array{mode: string, fastapi_enabled: bool, fastapi_online: bool, url: string}
     */
    public function getEngineStatus(): array
    {
        $enabled = (bool) config('services.inference.use_fastapi', true);
        $url = rtrim((string) config('services.inference.url', 'http://127.0.0.1:8001'), '/');
        $online = false;

        if ($enabled && ! Cache::get('fastapi_inference_offline', false)) {
            try {
                $res = Http::connectTimeout(1)->timeout(2)->get($url.'/health');
                $online = $res->successful() && is_array($res->json()) && (($res->json('status') ?? '') !== '');
                if (! $online) {
                    Cache::put('fastapi_inference_offline', true, now()->addSeconds(30));
                }
            } catch (\Throwable) {
                Cache::put('fastapi_inference_offline', true, now()->addSeconds(30));
            }
        }

        return [
            'mode' => $online ? 'fastapi_warm' : 'cli_fallback',
            'fastapi_enabled' => $enabled,
            'fastapi_online' => $online,
            'url' => $url,
        ];
    }

    /**
     * Kirim frame ke server FastAPI lokal (model tetap warm di RAM tanpa cold-start).
     *
     * @return array<string, mixed>|null
     */
    private function runViaFastApi(string $filePath, string $originalName, string $modelId, float $threshold): ?array
    {
        $url = rtrim((string) config('services.inference.url', 'http://127.0.0.1:8001'), '/');
        $timeout = max(3, (int) config('services.inference.timeout', 15));

        $stream = @fopen($filePath, 'rb');
        if ($stream === false) {
            return null;
        }

        try {
            $response = Http::connectTimeout(1)
                ->timeout($timeout)
                ->attach('file', $stream, $originalName ?: 'leaf.jpg')
                ->post("{$url}/predict?".http_build_query([
                    'model_id' => $modelId,
                    'threshold' => $threshold,
                ]));

            if (! $response->successful()) {
                return null;
            }

            $decoded = $response->json();
            if (
                ! is_array($decoded) ||
                ! isset($decoded['model_id']) ||
                (! array_key_exists('detections', $decoded) && ! isset($decoded['classification']))
            ) {
                return null;
            }

            $decoded['inference_backend'] = 'fastapi';

            return $decoded;
        } catch (\Throwable $e) {
            Cache::put('fastapi_inference_offline', true, now()->addSeconds(30));
            Log::info('FastAPI inference tidak aktif, fallback ke inference_cli.py: '.$e->getMessage());

            return null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Fallback: jalankan inferensi ONNX langsung lewat proses CLI Python.
     *
     * @return array<string, mixed>
     */
    private function runViaCli(string $filePath, string $modelId, float $threshold): array
    {
        $scriptPath = base_path('src'.DIRECTORY_SEPARATOR.'inference_cli.py');
        $escapedScript = escapeshellarg($scriptPath);
        $escapedImage = escapeshellarg($filePath);
        $escapedModel = escapeshellarg($modelId);
        $escapedThreshold = escapeshellarg((string) $threshold);

        $cmd = "python {$escapedScript} --image {$escapedImage} --model-id {$escapedModel} --threshold {$escapedThreshold} 2>&1";
        $output = shell_exec($cmd);

        if ($output) {
            $lines = array_filter(array_map('trim', explode("\n", trim($output))));
            foreach (array_reverse($lines) as $line) {
                if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                    $decoded = json_decode($line, true);
                    if (is_array($decoded)) {
                        $decoded['inference_backend'] = 'cli';

                        return $decoded;
                    }
                }
            }
        }

        return ['error' => 'Gagal menjalankan inferensi model ONNX.'];
    }
}
