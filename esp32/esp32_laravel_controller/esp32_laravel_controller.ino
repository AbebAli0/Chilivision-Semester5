/*
 * ============================================================================
 * CHILIVISION ESP32 CONTROLLER — INTEGRASI PENUH DENGAN LARAVEL + MYSQL API
 * ============================================================================
 * Fitur Sketch ini:
 * 1. Membaca Sensor Soil Moisture (GPIO 34) -> Kirim ke POST /api/sensor/soil-moisture
 *    (Otomatis mengalir: Web > Chatbot > Database MySQL)
 * 2. Membaca Sensor DHT11 (GPIO 4) -> Kirim ke POST /api/sensor/dht11
 *    (Otomatis mengalir: Web > Chatbot > Database MySQL)
 * 3. Mengecek Perintah Penyemprotan Manual dari Tombol Web & Command Chatbot
 *    melalui GET /api/esp32/poll setiap 2 detik -> Menyalakan Relay Pompa Air (GPIO 26)
 *    atau Relay Pompa Pestisida (GPIO 27).
 * 4. Jika ESP32 melakukan penyiraman otomatis karena tanah kering (< 30%),
 *    ESP32 otomatis mencatat waktunya ke POST /api/spray/water/history.
 *
 * Library yang diperlukan di Arduino IDE (Sketch > Include Library > Manage Libraries):
 * - "DHT sensor library" by Adafruit
 * - "Adafruit Unified Sensor"
 * - "ArduinoJson" by Benoit Blanchon (versi 6 atau 7)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <DHT.h>

// ===================== 1. KONFIGURASI WIFI & SERVER LARAVEL =====================
const char* WIFI_SSID     = "NAMA_WIFI_ANDA";
const char* WIFI_PASSWORD = "PASSWORD_WIFI_ANDA";

// Ganti IP di bawah dengan IPv4 Laptop Anda (cek dengan perintah `ipconfig` di CMD/PowerShell)
// Jika memakai `php artisan serve --host=0.0.0.0 --port=8000`:
String API_BASE_URL = "http://192.168.1.10:8000/api";
// Atau jika memakai Apache XAMPP langsung tanpa port 8000:
// String API_BASE_URL = "http://192.168.1.10/chilivision/api";

// ===================== 2. KONFIGURASI PIN ESP32 =====================
#define PIN_SOIL_MOISTURE   34   // Pin Analog ADC1 untuk Soil Moisture
#define PIN_DHT11           4    // Pin Digital untuk Sensor DHT11
#define DHTTYPE             DHT11
#define PIN_RELAY_WATER     26   // Pin Relay Pompa Air
#define PIN_RELAY_PESTICIDE 27   // Pin Relay Pompa Pestisida

// Ubah ke LOW jika modul relay Anda bertipe Active-LOW
#define RELAY_ON            LOW
#define RELAY_OFF           HIGH

// Kalibrasi ADC Soil Moisture (ESP32 12-bit: 0 - 4095)
const int DRY_ADC = 3500; // Nilai ADC saat tanah kering
const int WET_ADC = 1400; // Nilai ADC saat tanah basah

// Ambang batas penyiraman air otomatis
const float AUTO_WATER_ON_THRESHOLD = 30.0; // Siram otomatis jika kelembapan < 30%

DHT dht(PIN_DHT11, DHTTYPE);

// Timer non-blocking
unsigned long lastSensorSendMs    = 0;
unsigned long lastPollMs          = 0;
unsigned long lastPesticideAutoMs = 0;
const unsigned long SENSOR_INTERVAL_MS    = 10000;                  // Kirim data sensor tiap 10 detik
const unsigned long POLL_INTERVAL_MS      = 2000;                   // Cek tombol manual Web/Chatbot tiap 2 detik
const unsigned long PESTICIDE_5_DAYS_MS   = 5UL * 24UL * 3600UL * 1000UL; // Kondisi tiap 5 hari sekali (120 jam) menyemprot pestisida

// Status pompa non-blocking
bool waterPumpActive = false;
unsigned long waterPumpStopMs = 0;

bool pesticidePumpActive = false;
unsigned long pesticidePumpStopMs = 0;

// ===================== 3. FUNGSI HELPER SENSOR & RELAY =====================
float readSoilMoisturePercent(int &rawAdcOut) {
  rawAdcOut = analogRead(PIN_SOIL_MOISTURE);
  float pct = map(rawAdcOut, DRY_ADC, WET_ADC, 0, 100);
  if (pct < 0.0) pct = 0.0;
  if (pct > 100.0) pct = 100.0;
  return pct;
}

void activateWaterPump(int durationSeconds) {
  digitalWrite(PIN_RELAY_WATER, RELAY_ON);
  waterPumpActive = true;
  waterPumpStopMs = millis() + (durationSeconds * 1000UL);
  Serial.printf("[POMPA AIR] MENYALA selama %d detik\n", durationSeconds);
}

void activatePesticidePump(int durationSeconds) {
  digitalWrite(PIN_RELAY_PESTICIDE, RELAY_ON);
  pesticidePumpActive = true;
  pesticidePumpStopMs = millis() + (durationSeconds * 1000UL);
  Serial.printf("[POMPA PESTISIDA] MENYALA selama %d detik\n", durationSeconds);
}

void updatePumpTimers() {
  unsigned long now = millis();
  if (waterPumpActive && now >= waterPumpStopMs) {
    digitalWrite(PIN_RELAY_WATER, RELAY_OFF);
    waterPumpActive = false;
    Serial.println("[POMPA AIR] MATI (Selesai)");
  }
  if (pesticidePumpActive && now >= pesticidePumpStopMs) {
    digitalWrite(PIN_RELAY_PESTICIDE, RELAY_OFF);
    pesticidePumpActive = false;
    Serial.println("[POMPA PESTISIDA] MATI (Selesai)");
  }
}

// ===================== 4. FUNGSI PENGIRIMAN KE API LARAVEL =====================

// A. Kirim Hasil Sensor Soil Moisture (Web > Chatbot > Database)
void sendSoilMoistureToApi(float moisturePercent, int adcValue) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = API_BASE_URL + "/sensor/soil-moisture";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  StaticJsonDocument<200> doc;
  doc["moisture_percent"] = moisturePercent;
  doc["adc_value"] = adcValue;
  doc["source"] = "esp32";

  String payload;
  serializeJson(doc, payload);

  int httpCode = http.POST(payload);
  Serial.printf("[API Soil Moisture] POST %s -> HTTP %d\n", url.c_str(), httpCode);
  http.end();
}

// B. Kirim Hasil Sensor DHT11 (Web > Chatbot > Database)
void sendDht11ToApi(float temperature, float humidity) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = API_BASE_URL + "/sensor/dht11";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  StaticJsonDocument<200> doc;
  doc["temperature"] = temperature;
  doc["humidity"] = humidity;
  doc["source"] = "esp32";

  String payload;
  serializeJson(doc, payload);

  int httpCode = http.POST(payload);
  Serial.printf("[API DHT11] POST %s -> HTTP %d\n", url.c_str(), httpCode);
  http.end();
}

// C. Catat Histori Penyemprotan Air Otomatis ke Database
void sendAutoWaterHistoryToApi(float currentMoisture, int durationSeconds) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = API_BASE_URL + "/spray/water/history";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  StaticJsonDocument<256> doc;
  doc["trigger_mode"] = "auto";
  doc["triggered_by"] = "esp32";
  doc["duration_seconds"] = durationSeconds;
  doc["soil_moisture"] = currentMoisture;
  doc["notes"] = "Penyiraman otomatis ESP32 (kelembapan tanah di bawah ambang batas)";

  String payload;
  serializeJson(doc, payload);

  int httpCode = http.POST(payload);
  Serial.printf("[API Histori Air] POST %s -> HTTP %d\n", url.c_str(), httpCode);
  http.end();
}

// C2. Catat Histori Penyemprotan Pestisida Terjadwal (Tiap 5 Hari Sekali) ke Database
void sendScheduledPesticideHistoryToApi(int durationSeconds) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = API_BASE_URL + "/spray/pesticide/history";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  StaticJsonDocument<256> doc;
  doc["trigger_mode"] = "schedule_5_days";
  doc["triggered_by"] = "esp32_jadwal_5_hari";
  doc["duration_seconds"] = durationSeconds;
  doc["notes"] = "Penyemprotan pestisida terjadwal otomatis (kondisi tiap 5 hari sekali / 120 jam)";

  String payload;
  serializeJson(doc, payload);

  int httpCode = http.POST(payload);
  Serial.printf("[API Histori Pestisida 5 Hari] POST %s -> HTTP %d\n", url.c_str(), httpCode);
  http.end();
}

// D. Polling Perintah Penyemprotan dari Tombol Web / Konfirmasi Telegram / Jadwal
void pollManualSprayCommands() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = API_BASE_URL + "/esp32/poll";
  http.begin(url);
  http.addHeader("Accept", "application/json");

  int httpCode = http.GET();
  if (httpCode == 200) {
    String response = http.getString();
    StaticJsonDocument<512> doc;
    DeserializationError err = deserializeJson(doc, response);
    if (!err) {
      bool sprayWater = doc["spray_water"] | false;
      int waterDur    = doc["water_duration_seconds"] | 5;

      bool sprayPest  = doc["spray_pesticide"] | false;
      int pestDur     = doc["pesticide_duration_seconds"] | 5;

      if (sprayWater) {
        Serial.println("[COMMAND DITERIMA] Perintah Semprot Air dari Web / Telegram / Otomatis Tanah Kering!");
        activateWaterPump(waterDur);
      }
      if (sprayPest) {
        Serial.println("[COMMAND DITERIMA] Perintah Semprot Pestisida (Tombol Web / Konfirmasi Telegram / Jadwal 5 Hari)!");
        activatePesticidePump(pestDur);
        lastPesticideAutoMs = millis(); // Reset timer lokal 5 hari
      }
    }
  }
  http.end();
}

// ===================== 5. SETUP & LOOP UTAMA =====================
void setup() {
  Serial.begin(115200);

  pinMode(PIN_RELAY_WATER, OUTPUT);
  pinMode(PIN_RELAY_PESTICIDE, OUTPUT);
  digitalWrite(PIN_RELAY_WATER, RELAY_OFF);
  digitalWrite(PIN_RELAY_PESTICIDE, RELAY_OFF);

  dht.begin();

  Serial.printf("Menghubungkan ke Wi-Fi %s ", WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWi-Fi Terhubung!");
  Serial.print("IP ESP32: ");
  Serial.println(WiFi.localIP());
  Serial.print("Target Server Laravel: ");
  Serial.println(API_BASE_URL);
}

void loop() {
  updatePumpTimers();

  unsigned long now = millis();

  // 1. Cek perintah Tombol Web & Jadwal 2 Hari dari Laravel setiap 2 detik
  //    (Catatan: Deteksi penyakit daun TIDAK menyemprot pestisida otomatis!)
  if (now - lastPollMs >= POLL_INTERVAL_MS) {
    lastPollMs = now;
    pollManualSprayCommands();
  }

  // 1b. Kondisi fallback lokal di ESP32: Tiap 5 hari sekali (120 jam) menyemprot pestisida
  if (now - lastPesticideAutoMs >= PESTICIDE_5_DAYS_MS && !pesticidePumpActive) {
    lastPesticideAutoMs = now;
    Serial.println("[JADWAL 5 HARI] Waktu 120 jam (5 hari) tercapai, menyemprot pestisida selama 5 detik...");
    activatePesticidePump(5);
    sendScheduledPesticideHistoryToApi(5);
  }

  // 2. Baca Sensor Soil Moisture & DHT11 lalu kirim ke Laravel setiap 10 detik
  if (now - lastSensorSendMs >= SENSOR_INTERVAL_MS) {
    lastSensorSendMs = now;

    // Baca & kirim Soil Moisture
    int rawAdc = 0;
    float moisturePct = readSoilMoisturePercent(rawAdc);
    Serial.printf("[Sensor] Soil Moisture: %.1f%% (ADC: %d)\n", moisturePct, rawAdc);
    sendSoilMoistureToApi(moisturePct, rawAdc);

    // Jika tanah kering (< 30%) dan pompa air sedang mati, siram otomatis & simpan histori ke API
    if (moisturePct < AUTO_WATER_ON_THRESHOLD && !waterPumpActive) {
      activateWaterPump(5);
      sendAutoWaterHistoryToApi(moisturePct, 5);
    }

    // Baca & kirim DHT11
    float temp = dht.readTemperature();
    float hum  = dht.readHumidity();
    if (!isnan(temp) && !isnan(hum)) {
      Serial.printf("[Sensor] DHT11 Suhu: %.1f C, Kelembapan Udara: %.1f%%\n", temp, hum);
      sendDht11ToApi(temp, hum);
    } else {
      Serial.println("[Sensor] Gagal membaca sensor DHT11, periksa kabel pin GPIO 4.");
    }
  }
}
