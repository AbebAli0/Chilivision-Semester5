<?php

use App\Services\LeafInferenceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $inference = app(LeafInferenceService::class);

        if (Schema::hasTable('leaf_detections')) {
            DB::table('leaf_detections')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($inference): void {
                    foreach ($rows as $row) {
                        $updates = [];
                        if (is_string($row->class_name) && str_contains($row->class_name, '_')) {
                            $updates['class_name'] = $inference->formatClassName($row->class_name);
                        }

                        if (is_string($row->detections_json) && $row->detections_json !== '') {
                            $decoded = json_decode($row->detections_json, true);
                            if (is_array($decoded)) {
                                $changed = false;
                                foreach ($decoded as $idx => $det) {
                                    if (is_array($det) && isset($det['class_name']) && is_string($det['class_name']) && str_contains($det['class_name'], '_')) {
                                        $decoded[$idx]['class_name'] = $inference->formatClassName($det['class_name']);
                                        $changed = true;
                                    }
                                }
                                if ($changed) {
                                    $updates['detections_json'] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                                }
                            }
                        }

                        if ($updates !== []) {
                            DB::table('leaf_detections')->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }

        if (Schema::hasTable('spray_histories')) {
            DB::table('spray_histories')
                ->where('leaf_condition', 'like', '%\\_%')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($inference): void {
                    foreach ($rows as $row) {
                        if (is_string($row->leaf_condition) && str_contains($row->leaf_condition, '_')) {
                            DB::table('spray_histories')
                                ->where('id', $row->id)
                                ->update(['leaf_condition' => $inference->formatClassName($row->leaf_condition)]);
                        }
                    }
                });
        }

        if (Schema::hasTable('chatbot_messages')) {
            $replacements = [
                'bercak_bakteri' => 'Bercak Bakteri',
                'bercak_cercospora' => 'Bercak Cercospora',
                'bercak_putih' => 'Bercak Putih',
                'bercak_daun' => 'Bercak Daun',
                'daun_sehat' => 'Daun Sehat',
                'defisiensi_nutrisi' => 'Defisiensi Nutrisi',
                'virus_keriting' => 'Virus Keriting',
            ];
            $search = array_keys($replacements);
            $replace = array_values($replacements);

            DB::table('chatbot_messages')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($search, $replace, $inference): void {
                    foreach ($rows as $row) {
                        $updates = [];

                        if (is_string($row->message)) {
                            $updatedMessage = str_ireplace($search, $replace, $row->message);
                            if ($updatedMessage !== $row->message) {
                                $updates['message'] = $updatedMessage;
                            }
                        }

                        if (is_string($row->metadata_json) && $row->metadata_json !== '') {
                            $meta = json_decode($row->metadata_json, true);
                            if (is_array($meta)) {
                                $metaChanged = false;
                                foreach (['class_name', 'leaf_condition'] as $key) {
                                    if (isset($meta[$key]) && is_string($meta[$key]) && str_contains($meta[$key], '_')) {
                                        $meta[$key] = $inference->formatClassName($meta[$key]);
                                        $metaChanged = true;
                                    }
                                }
                                if ($metaChanged) {
                                    $updates['metadata_json'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
                                }
                            }
                        }

                        if ($updates !== []) {
                            DB::table('chatbot_messages')->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Normalisasi tampilan nama penyakit tidak perlu dikembalikan ke format snake_case.
    }
};
