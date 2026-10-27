<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Gambar & Deteksi Daun Terakhir (leaf_detections)
        Schema::create('leaf_detections', function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->default('yolov8n-chili');
            $table->string('model_name')->nullable();
            $table->integer('class_id')->nullable();
            $table->string('class_name')->index();
            $table->double('confidence')->default(0);
            $table->integer('detection_count')->default(0);
            $table->json('detections_json')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_hash', 64)->index();
            $table->string('perceptual_hash', 32)->nullable()->index();
            $table->integer('image_width')->nullable();
            $table->integer('image_height')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });

        // 2. Tabel Sensor Soil Moisture (soil_moisture_readings)
        Schema::create('soil_moisture_readings', function (Blueprint $table) {
            $table->id();
            $table->double('moisture_percent');
            $table->integer('adc_value')->nullable();
            $table->string('status'); // Kering, Optimal, Basah
            $table->string('recommendation')->nullable();
            $table->string('source')->default('esp32'); // esp32, web, chatbot, api
            $table->boolean('sent_to_web')->default(true);
            $table->boolean('sent_to_chatbot')->default(true);
            $table->timestamps();
        });

        // 3. Tabel Sensor DHT11 Suhu & Kelembapan Udara (dht11_readings)
        Schema::create('dht11_readings', function (Blueprint $table) {
            $table->id();
            $table->double('temperature');
            $table->double('humidity');
            $table->double('heat_index')->nullable();
            $table->string('status'); // Normal, Panas, Lembap Tinggi, dll.
            $table->string('recommendation')->nullable();
            $table->string('source')->default('esp32'); // esp32, web, chatbot, api
            $table->boolean('sent_to_web')->default(true);
            $table->boolean('sent_to_chatbot')->default(true);
            $table->timestamps();
        });

        // 4. Tabel Histori Penyemprotan Air & Pestisida (spray_histories)
        Schema::create('spray_histories', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['water', 'pesticide'])->index();
            $table->string('trigger_mode')->default('manual'); // manual, auto
            $table->string('triggered_by')->default('web'); // web, chatbot, esp32, api
            $table->integer('duration_seconds')->default(5);
            $table->string('status')->default('completed'); // queued, executing, completed
            $table->string('leaf_condition')->nullable();
            $table->double('soil_moisture')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sprayed_at')->useCurrent()->index();
            $table->timestamps();
        });

        // 5. Tabel Pesan & Pipeline Chatbot (chatbot_messages)
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender'); // user, bot, system
            $table->text('message');
            $table->string('intent')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('spray_histories');
        Schema::dropIfExists('dht11_readings');
        Schema::dropIfExists('soil_moisture_readings');
        Schema::dropIfExists('leaf_detections');
    }
};
