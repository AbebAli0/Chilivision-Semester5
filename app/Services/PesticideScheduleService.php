<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use App\Models\LeafDetection;
use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Mengelola kebijakan jadwal penyemprotan pestisida (interval dinamis dan target tanggal/jam spesifik).
 */
class PesticideScheduleService
{
    /**
     * Ambil interval jadwal penyemprotan pestisida aktif dalam jam (default: 120 jam / 5 hari).
     */
    public function getIntervalHours(): int
    {
        $hours = (int) Cache::get('pesticide_schedule_interval_hours', 120);

        return max(1, min(720, $hours));
    }

    /**
     * Ambil target tanggal/jam penyemprotan pestisida spesifik jika disetel pengguna.
     */
    public function getTargetAt(): ?Carbon
    {
        $raw = Cache::get('pesticide_schedule_target_at');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->timezone('Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ambil durasi pompa (detik) untuk jadwal target spesifik.
     */
    public function getTargetDuration(): int
    {
        $duration = (int) Cache::get('pesticide_schedule_target_duration', 5);

        return max(1, min(60, $duration));
    }

    /**
     * Hapus target tanggal/jam spesifik setelah tereksekusi atau diganti ke mode interval.
     */
    public function clearTarget(): void
    {
        Cache::forget('pesticide_schedule_target_at');
        Cache::forget('pesticide_schedule_target_duration');
    }

    /**
     * Format label interval jadwal agar mudah dibaca.
     */
    public function formatIntervalLabel(int $hours): string
    {
        if ($hours % 24 === 0) {
            $days = (int) ($hours / 24);

            return "{$days} Hari Sekali";
        }

        return "{$hours} Jam Sekali";
    }

    /**
     * Validasi dan simpan target tanggal/jam penyemprotan pestisida spesifik.
     *
     * @param  callable(SprayHistory|null): (array<string, mixed>|null)  $formatSprayFn
     * @param  callable(string): void  $notifyFn
     * @return array{ok: bool, error?: string, schedule?: array<string, mixed>}
     */
    public function applyTargetDatetime(string $datetimeInput, int $duration, callable $formatSprayFn, callable $notifyFn): array
    {
        $trimmed = trim($datetimeInput);
        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'Tanggal/jam target tidak boleh kosong.'];
        }

        try {
            $targetTime = Carbon::parse($trimmed, 'Asia/Jakarta')->timezone('Asia/Jakarta');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => "Format tanggal/jam target (`{$trimmed}`) tidak valid."];
        }

        $now = now('Asia/Jakarta');
        if ($targetTime->lessThanOrEqualTo($now)) {
            return [
                'ok' => false,
                'error' => "Waktu target **{$targetTime->format('d M Y, H:i')} WIB** sudah terlewat. Silakan pilih tanggal atau jam di masa mendatang.",
            ];
        }

        Cache::forever('pesticide_schedule_target_at', $targetTime->toIso8601String());
        Cache::forever('pesticide_schedule_target_duration', max(1, min(60, $duration)));

        return [
            'ok' => true,
            'schedule' => $this->evaluate($formatSprayFn, $notifyFn),
        ];
    }

    /**
     * Deteksi apakah teks menyebut tanggal atau jam kalender spesifik.
     */
    public function containsSpecificDatetimeCue(string $lower): bool
    {
        return preg_match('/\b(tanggal|tgl|besok|lusa|hari ini|pukul|januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b|\bjam\s+\d{1,2}|\d{4}-\d{2}-\d{2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{1,2}[:.]\d{2}/u', $lower) === 1;
    }

    /**
     * Ekstrak permintaan penjadwalan tanggal/jam spesifik pestisida secara deterministik.
     */
    public function parseTargetDatetimeRequest(string $lower): ?Carbon
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $lower) ?? '');
        $mentionsPesticideOrSchedule = str_contains($normalized, 'pestisida')
            || str_contains($normalized, 'jadwal')
            || str_contains($normalized, 'semprot');

        if (! $mentionsPesticideOrSchedule || ! $this->containsSpecificDatetimeCue($normalized)) {
            return null;
        }

        $now = now('Asia/Jakarta');
        $hour = 7;
        $minute = 0;

        if (preg_match('/(?:jam|pukul)?\s*(\d{1,2})[:.](\d{2})(?:\s*(pagi|siang|sore|malam))?/u', $normalized, $tm) === 1) {
            $hour = (int) $tm[1];
            $minute = (int) $tm[2];
            $meridiem = $tm[3] ?? '';
        } elseif (preg_match('/\b(?:jam|pukul)\s*(\d{1,2})(?:\s*(pagi|siang|sore|malam))?/u', $normalized, $tm) === 1) {
            $hour = (int) $tm[1];
            $minute = 0;
            $meridiem = $tm[2] ?? '';
        } else {
            $meridiem = '';
        }

        if ($hour >= 1 && $hour <= 11 && in_array($meridiem, ['sore', 'malam'], true)) {
            $hour += 12;
        } elseif ($hour === 12 && $meridiem === 'malam') {
            $hour = 0;
        }
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        $monthMap = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        ];

        $date = null;
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $normalized, $dm) === 1) {
            $date = Carbon::create((int) $dm[1], (int) $dm[2], (int) $dm[3], $hour, $minute, 0, 'Asia/Jakarta');
        } elseif (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})\b/', $normalized, $dm) === 1) {
            $date = Carbon::create((int) $dm[3], (int) $dm[2], (int) $dm[1], $hour, $minute, 0, 'Asia/Jakarta');
        } elseif (preg_match('/\b(?:tanggal|tgl)?\s*(\d{1,2})\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+(\d{4}))?\b/u', $normalized, $dm) === 1) {
            $year = isset($dm[3]) && $dm[3] !== '' ? (int) $dm[3] : $now->year;
            $month = $monthMap[$dm[2]];
            $date = Carbon::create($year, $month, (int) $dm[1], $hour, $minute, 0, 'Asia/Jakarta');
        } elseif (preg_match('/\b(?:tanggal|tgl)\s*(\d{1,2})\b/u', $normalized, $dm) === 1) {
            $day = (int) $dm[1];
            if ($day >= 1 && $day <= 31) {
                $date = $now->copy()->setDay(min($day, $now->daysInMonth))->setTime($hour, $minute, 0);
            }
        } elseif (str_contains($normalized, 'lusa')) {
            $date = $now->copy()->addDays(2)->setTime($hour, $minute, 0);
        } elseif (str_contains($normalized, 'besok')) {
            $date = $now->copy()->addDay()->setTime($hour, $minute, 0);
        } elseif (str_contains($normalized, 'hari ini')) {
            $date = $now->copy()->setTime($hour, $minute, 0);
        }

        return $date;
    }

    /**
     * Ekstrak permintaan perubahan interval jadwal pestisida dari teks chatbot bahasa Indonesia.
     *
     * @return array<string, mixed>|null
     */
    public function parseIntervalRequest(string $lower): ?array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $lower) ?? '');
        if ($this->containsSpecificDatetimeCue($normalized)) {
            return null;
        }

        $mentionsSchedule = str_contains($normalized, 'jadwal')
            || str_contains($normalized, 'rutin')
            || str_contains($normalized, 'interval')
            || (str_contains($normalized, 'pestisida') && (str_contains($normalized, 'hari sekali') || str_contains($normalized, 'jam sekali') || str_contains($normalized, 'setiap ') || str_contains($normalized, 'tiap ')));

        if (! $mentionsSchedule) {
            return null;
        }

        $mentionsChangeVerb = str_contains($normalized, 'ganti')
            || str_contains($normalized, 'ubah')
            || str_contains($normalized, 'atur')
            || str_contains($normalized, 'set ')
            || str_contains($normalized, 'setting')
            || str_contains($normalized, 'jadi')
            || str_contains($normalized, 'ke ')
            || str_contains($normalized, 'per ')
            || str_contains($normalized, 'tiap ')
            || str_contains($normalized, 'setiap ');

        $wordMap = [
            'sehari' => '1 hari',
            'seminggu' => '7 hari',
            'satu' => '1',
            'dua' => '2',
            'tiga' => '3',
            'empat' => '4',
            'lima' => '5',
            'enam' => '6',
            'tujuh' => '7',
            'delapan' => '8',
            'sembilan' => '9',
            'sepuluh' => '10',
        ];
        $forNumbers = str_replace(array_keys($wordMap), array_values($wordMap), $normalized);

        if (preg_match('/(\d+)\s*(hari|jam)\b/u', $forNumbers, $matches) === 1) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            $hours = $unit === 'hari' ? $value * 24 : $value;

            if ($hours < 1 || $hours > 720) {
                return [
                    'invalid' => true,
                    'error' => 'Interval jadwal pestisida harus antara 1 jam hingga 30 hari (720 jam).',
                ];
            }

            return ['hours' => $hours];
        }

        if ($mentionsChangeVerb && ! str_contains($normalized, 'kapan') && ! str_contains($normalized, 'cek') && ! str_contains($normalized, 'lihat')) {
            return ['needs_value' => true];
        }

        return null;
    }

    /**
     * Evaluasi apakah penyemprotan pestisida terjadwal sudah jatuh tempo saat ini.
     *
     * @param  callable(SprayHistory|null): (array<string, mixed>|null)  $formatSprayFn
     * @param  callable(string): void  $notifyFn
     * @return array<string, mixed>
     */
    public function evaluate(callable $formatSprayFn, callable $notifyFn): array
    {
        $intervalHours = $this->getIntervalHours();
        $intervalLabel = $this->formatIntervalLabel($intervalHours);
        $intervalDays = ($intervalHours % 24 === 0) ? (int) ($intervalHours / 24) : round($intervalHours / 24, 1);

        $lastPesticide = SprayHistory::where('type', 'pesticide')
            ->orderByDesc('sprayed_at')
            ->orderByDesc('id')
            ->first();

        $now = now('Asia/Jakarta');
        $autoTriggeredNow = false;
        $targetAt = $this->getTargetAt();
        $scheduleMode = 'interval';

        $lastTime = ($lastPesticide && $lastPesticide->sprayed_at)
            ? Carbon::parse($lastPesticide->sprayed_at)->timezone('Asia/Jakarta')
            : null;

        if ($targetAt !== null) {
            if ($now->greaterThanOrEqualTo($targetAt)) {
                $targetDuration = $this->getTargetDuration();
                $latestLeaf = LeafDetection::latest('id')->first();
                $latestSoil = SoilMoistureReading::latest('id')->first();

                $lastPesticide = SprayHistory::create([
                    'type' => 'pesticide',
                    'trigger_mode' => 'schedule_target_datetime',
                    'triggered_by' => 'jadwal_pestisida',
                    'duration_seconds' => $targetDuration,
                    'status' => 'queued',
                    'leaf_condition' => $latestLeaf->class_name ?? 'Jadwal Tanggal Target',
                    'soil_moisture' => $latestSoil->moisture_percent ?? null,
                    'notes' => "Penyemprotan pestisida terjadwal sesuai target tanggal {$targetAt->format('d M Y, H:i')} WIB.",
                    'sprayed_at' => $now,
                ]);
                $this->clearTarget();
                $autoTriggeredNow = true;
                $lastTime = $now;
                $nextSchedule = $lastTime->copy()->addHours($intervalHours);
                $targetAt = null;

                $msgText = "🗓️🧪 [Jadwal Pestisida Tanggal Target] Waktu target **{$now->format('d M Y, H:i:s')} WIB** telah tercapai. Pompa pestisida otomatis dijalankan selama **{$targetDuration} detik**, lalu jadwal kembali ke interval **{$intervalLabel}** (**".$nextSchedule->format('d M Y, H:i:s').' WIB**).';
                ChatbotMessage::create([
                    'sender' => 'bot',
                    'message' => $msgText,
                    'intent' => 'spray_pesticide_schedule',
                    'metadata_json' => $formatSprayFn($lastPesticide),
                ]);
                $notifyFn($msgText);
            } else {
                $scheduleMode = 'target_datetime';
                $nextSchedule = $targetAt;
            }
        } elseif ($lastTime !== null) {
            $nextSchedule = $lastTime->copy()->addHours($intervalHours);

            if ($now->greaterThanOrEqualTo($nextSchedule)) {
                $latestLeaf = LeafDetection::latest('id')->first();
                $latestSoil = SoilMoistureReading::latest('id')->first();

                $lastPesticide = SprayHistory::create([
                    'type' => 'pesticide',
                    'trigger_mode' => 'schedule_'.$intervalHours.'_hours',
                    'triggered_by' => 'jadwal_pestisida',
                    'duration_seconds' => 5,
                    'status' => 'queued',
                    'leaf_condition' => $latestLeaf->class_name ?? 'Jadwal Rutin '.$intervalLabel,
                    'soil_moisture' => $latestSoil->moisture_percent ?? null,
                    'notes' => "Penyemprotan pestisida terjadwal otomatis (kondisi tiap {$intervalLabel} / {$intervalHours} jam sekali).",
                    'sprayed_at' => $now,
                ]);
                $autoTriggeredNow = true;
                $lastTime = $now;
                $nextSchedule = $lastTime->copy()->addHours($intervalHours);

                $msgText = "🗓️🧪 [Jadwal Pestisida {$intervalLabel}] Waktu penyemprotan pestisida terakhir sudah mencapai **{$intervalLabel} ({$intervalHours} jam)**. Pompa pestisida otomatis dijalankan selama **5 detik** dan jadwal berikutnya diatur ke **".$nextSchedule->format('d M Y, H:i:s').' WIB**.';
                ChatbotMessage::create([
                    'sender' => 'bot',
                    'message' => $msgText,
                    'intent' => 'spray_pesticide_schedule',
                    'metadata_json' => $formatSprayFn($lastPesticide),
                ]);
                $notifyFn($msgText);
            }
        } else {
            $nextSchedule = $now->copy()->addHours($intervalHours);
        }

        $displayLabel = $scheduleMode === 'target_datetime' ? 'Tanggal Target Khusus' : $intervalLabel;

        return [
            'policy' => "Pestisida hanya disemprot melalui Tombol/Konfirmasi Manual (Web/Telegram), Target Tanggal Khusus, atau Kondisi Tiap {$intervalLabel} ({$intervalHours} Jam).",
            'schedule_mode' => $scheduleMode,
            'target_datetime' => $targetAt?->toIso8601String(),
            'interval_days' => $intervalDays,
            'interval_hours' => $intervalHours,
            'interval_label' => $displayLabel,
            'recurring_interval_label' => $intervalLabel,
            'auto_triggered_now' => $autoTriggeredNow,
            'last_sprayed_at_formatted' => $lastTime ? $lastTime->format('d M Y, H:i:s').' WIB' : 'Belum pernah',
            'next_spray_at' => $nextSchedule->toIso8601String(),
            'next_spray_formatted' => $nextSchedule->format('d M Y, H:i:s').' WIB',
            'time_until_next' => $nextSchedule->diffForHumans($now, ['parts' => 2]),
        ];
    }
}
