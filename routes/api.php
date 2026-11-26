<?php

use App\Http\Controllers\ChiliVisionApiController;
use Illuminate\Support\Facades\Route;

// Info & Health
Route::get('/', [ChiliVisionApiController::class, 'apiInfo']);
Route::get('/health', [ChiliVisionApiController::class, 'health']);
Route::get('/models', [ChiliVisionApiController::class, 'models']);
Route::get('/cameras', [ChiliVisionApiController::class, 'cameras']);
Route::get('/telegram/model', [ChiliVisionApiController::class, 'getTelegramModel']);
Route::post('/telegram/model', [ChiliVisionApiController::class, 'setTelegramModel']);
Route::get('/dashboard/state', [ChiliVisionApiController::class, 'dashboardState']);

// 1. ENDPOINT API: Gambar Daun (Deteksi terakhir daun, jika daun tidak berubah gambar tidak dikirim ke database)
Route::get('/leaf-image', [ChiliVisionApiController::class, 'getLatestLeafImage']);
Route::post('/leaf-image', [ChiliVisionApiController::class, 'predictAndStoreLeaf']);
Route::get('/gambar-daun', [ChiliVisionApiController::class, 'getLatestLeafImage']);
Route::post('/gambar-daun', [ChiliVisionApiController::class, 'predictAndStoreLeaf']);
Route::post('/predict', [ChiliVisionApiController::class, 'predictAndStoreLeaf']);

// 2. ENDPOINT API: Hasil Sensor Soil Moisture (Mengirim Data Terakhir ke web > chatbot > database)
Route::get('/sensor/soil-moisture', [ChiliVisionApiController::class, 'getLatestSoilMoisture']);
Route::post('/sensor/soil-moisture', [ChiliVisionApiController::class, 'storeSoilMoisture']);
Route::get('/soil-moisture', [ChiliVisionApiController::class, 'getLatestSoilMoisture']);
Route::post('/soil-moisture', [ChiliVisionApiController::class, 'storeSoilMoisture']);

// 3. ENDPOINT API: Hasil Sensor DHT22 / DHT11 (Mengirim Data Terakhir ke web > chatbot > database)
Route::get('/sensor/dht22', [ChiliVisionApiController::class, 'getLatestDht11']);
Route::post('/sensor/dht22', [ChiliVisionApiController::class, 'storeDht11']);
Route::get('/dht22', [ChiliVisionApiController::class, 'getLatestDht11']);
Route::post('/dht22', [ChiliVisionApiController::class, 'storeDht11']);
Route::get('/sensor/dht11', [ChiliVisionApiController::class, 'getLatestDht11']);
Route::post('/sensor/dht11', [ChiliVisionApiController::class, 'storeDht11']);
Route::get('/dht11', [ChiliVisionApiController::class, 'getLatestDht11']);
Route::post('/dht11', [ChiliVisionApiController::class, 'storeDht11']);

// 4. ENDPOINT API: Histori Penyemprotan Terakhir Pestisida (Menyimpan waktu terakhir pestisida disemprot)
Route::get('/spray/pesticide/history', [ChiliVisionApiController::class, 'getPesticideSprayHistory']);
Route::post('/spray/pesticide/history', [ChiliVisionApiController::class, 'storePesticideSprayHistory']);
Route::get('/histori-pestisida', [ChiliVisionApiController::class, 'getPesticideSprayHistory']);
Route::post('/histori-pestisida', [ChiliVisionApiController::class, 'storePesticideSprayHistory']);

// 5. ENDPOINT API: Histori Penyemprotan Terakhir Air (Menyimpan waktu terakhir air disemprot)
Route::get('/spray/water/history', [ChiliVisionApiController::class, 'getWaterSprayHistory']);
Route::post('/spray/water/history', [ChiliVisionApiController::class, 'storeWaterSprayHistory']);
Route::get('/histori-air', [ChiliVisionApiController::class, 'getWaterSprayHistory']);
Route::post('/histori-air', [ChiliVisionApiController::class, 'storeWaterSprayHistory']);

// 6. ENDPOINT API: Penyemprotan Manual Pestisida dan Air (Tombol di web & command di chatbot)
Route::post('/spray/manual', [ChiliVisionApiController::class, 'triggerManualSpray']);
Route::post('/penyemprotan-manual', [ChiliVisionApiController::class, 'triggerManualSpray']);

// 7. ENDPOINT API: Chatbot Interaktif (Web > Chatbot > Database & Command Semprot Manual)
Route::get('/chatbot/messages', [ChiliVisionApiController::class, 'getChatbotMessages']);
Route::post('/chatbot/message', [ChiliVisionApiController::class, 'sendChatbotMessage']);

// 8. ENDPOINT POLLING & STATUS HARDWARE ESP32 (Arduino IDE)
Route::get('/esp32/poll', [ChiliVisionApiController::class, 'esp32PollCommands']);
Route::get('/spray/pending', [ChiliVisionApiController::class, 'esp32PollCommands']);
Route::get('/hardware/esp32-status', [ChiliVisionApiController::class, 'getHardwareStatus']);

// 8b. EKSPOR LAPORAN CSV (soil, dht11, leaf, spray)
Route::get('/reports/export/{type}', [ChiliVisionApiController::class, 'exportCsvReport']);

// 9. ENDPOINT WEBHOOK TELEGRAM BOT
Route::post('/telegram/webhook', [ChiliVisionApiController::class, 'telegramWebhook']);
