<?php

namespace Database\Seeders;

use App\Models\ChatbotMessage;
use App\Models\Dht11Reading;
use App\Models\SoilMoistureReading;
use App\Models\SprayHistory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (SoilMoistureReading::count() === 0) {
            SoilMoistureReading::create([
                'moisture_percent' => 48.5,
                'adc_value' => 2882,
                'status' => 'Optimal',
                'recommendation' => 'Kelembapan tanah ideal untuk pertumbuhan tanaman cabai (30%–70%).',
                'source' => 'esp32',
                'sent_to_web' => true,
                'sent_to_chatbot' => true,
            ]);
        }

        if (Dht11Reading::count() === 0) {
            Dht11Reading::create([
                'temperature' => 28.4,
                'humidity' => 66.0,
                'heat_index' => 29.2,
                'status' => 'Optimal',
                'recommendation' => 'Suhu (24–32°C) dan kelembapan udara mendukung pertumbuhan tanaman cabai.',
                'source' => 'esp32',
                'sent_to_web' => true,
                'sent_to_chatbot' => true,
            ]);
        }

        if (SprayHistory::where('type', 'water')->count() === 0) {
            SprayHistory::create([
                'type' => 'water',
                'trigger_mode' => 'auto',
                'triggered_by' => 'esp32',
                'duration_seconds' => 5,
                'status' => 'completed',
                'leaf_condition' => 'Daun Sehat',
                'soil_moisture' => 29.0,
                'notes' => 'Penyiraman otomatis terakhir saat kelembapan tanah menyentuh ambang bawah.',
                'sprayed_at' => now('Asia/Jakarta')->subMinutes(35),
            ]);
        }

        if (SprayHistory::where('type', 'pesticide')->count() === 0) {
            SprayHistory::create([
                'type' => 'pesticide',
                'trigger_mode' => 'manual',
                'triggered_by' => 'web',
                'duration_seconds' => 5,
                'status' => 'completed',
                'leaf_condition' => 'Penyakit Daun Keriting',
                'soil_moisture' => 48.5,
                'notes' => 'Penyemprotan pestisida terjadwal untuk pencegahan vektor hama.',
                'sprayed_at' => now('Asia/Jakarta')->subHours(2),
            ]);
        }

        if (ChatbotMessage::count() === 0) {
            ChatbotMessage::create([
                'sender' => 'bot',
                'message' => "Halo! Saya **ChiliVision Smart Bot** 🤖🌿.\nSistem terhubung ke **Laravel + MySQL (`chilivision_db`)**.\n\n• Data **Sensor Soil Moisture** & **DHT11** mengalir otomatis: `Web > Chatbot > Database`.\n• Gunakan tombol atau ketik command **`semprot air`** / **`semprot pestisida`** di sini untuk menyemprot secara manual!",
                'intent' => 'welcome',
            ]);
        }
    }
}
