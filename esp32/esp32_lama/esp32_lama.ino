#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include "secrets.h"

// ================= PIN ESP32 =================
#define PIN_SOIL 35

/*
 * PEMETAAN RELAY POMPA (Active-LOW: LOW = ON, HIGH = OFF)
 * -----------------------------------------------------------------
 * - RELAY 1 -> GPIO 27 : KHUSUS POMPA AIR
 *     Menyala otomatis saat tanah kering (< autoWaterThreshold),
 *     perintah Web/Telegram/Chatbot, atau tes manual Serial ('1').
 * - RELAY 2 -> GPIO 25 : KHUSUS POMPA PESTISIDA
 *     Menyala saat konfirmasi Website / Telegram, jadwal otomatis
 *     5 hari sekali (120 jam), atau tes manual Serial ('2').
 * -----------------------------------------------------------------
 * Catatan: Jika modul relay Anda Active-HIGH, tukar LOW/HIGH di bawah.
 */
#define RELAY_POMPA_AIR       27  // Relay 1 -> Pompa Air
#define RELAY_POMPA_PESTISIDA 25  // Relay 2 -> Pompa Pestisida

#define DHTPIN 4
#define DHTTYPE DHT22

#define SDA_LCD 21
#define SCL_LCD 22

// ================= KONFIGURASI =================
#define RELAY_ON LOW
#define RELAY_OFF HIGH

LiquidCrystal_I2C lcd(0x27, 16, 2);
DHT dht(DHTPIN, DHTTYPE);

// ================= KONFIGURASI WIFI & API =================
const unsigned long WIFI_RETRY_MS = 5000;
const unsigned long SENSOR_SEND_MS = 10000;
const unsigned long POLL_MS = 1000;
const unsigned long AUTO_WATER_COOLDOWN_MS = 60000;
const unsigned long LCD_INTERVAL_MS = 2500;
const unsigned long LCD_PUMP_INTERVAL_MS = 1000;
// Interval jadwal pestisida lokal: 5 hari (120 jam)
const unsigned long PESTICIDE_INTERVAL_5_HARI_MS =
  5UL * 24UL * 3600UL * 1000UL;
const int PESTICIDE_AUTO_DURATION_SEC = 5;

int autoWaterThreshold = 30;
bool configAwalSudahSync = false;
String pesticideScheduleMode = "";
int pesticideIntervalHours = 0;
String pesticideIntervalLabel = "";
String pesticideNextSpray = "";
String pesticideTargetDatetime = "";

// Tracking status deteksi penyakit daun dari Website
String latestLeafCondition = "Belum ada deteksi";
float latestLeafConfidence = 0.0;
String latestLeafModel = "-";
bool latestLeafIsDisease = false;
String latestLeafRecommendation = "-";
String latestLeafDetectedAt = "-";
String latestLeafEventKey = "";

unsigned long waktuWifiRetry = 0;
unsigned long waktuKirim = 0;
unsigned long waktuPoll = 0;
unsigned long waktuSiramAuto = 0;
unsigned long waktuTerakhirPestisidaMs = 0;
bool kirimTahapDhtNext = false;
bool wifiSudahTampil = false;
bool apiTerhubung = false;
int apiGagalBeruntun = 0;
unsigned long waktuCekApi = 0;
const unsigned long CEK_API_MS = 5000;

WiFiClientSecure pollSecureClient;
HTTPClient pollHttp;

// ================= KALIBRASI SOIL =================
const int ADC_KERING = 3000;
const int ADC_BASAH = 1200;

// ================= VARIABEL SENSOR & POMPA =================
int nilaiSoil = 0;
int kelembapanTanah = 0;

float suhu = 0;
float kelembapanUdara = 0;

// Status & timer terpisah untuk Pompa Air
bool statusPompaAir = false;
unsigned long mulaiTimerPompaAirMs = 0;
unsigned long durasiPompaAirMs = 0;
unsigned long mulaiManualPompaAirMs = 0;

// Status & timer terpisah untuk Pompa Pestisida
bool statusPompaPestisida = false;
unsigned long mulaiTimerPompaPestisidaMs = 0;
unsigned long durasiPompaPestisidaMs = 0;
unsigned long mulaiManualPompaPestisidaMs = 0;

unsigned long waktuBaca = 0;
unsigned long waktuLCD = 0;

int tampilan = 0;

// ================= PROTOTIPE FUNGSI =================
int bacaSoil();
int hitungKelembapan(int adc);
String kondisiTanah();
void bacaSensor();
void cetakBarisLCD(int baris, String teks);
String formatStatusPompaLCD(
  const char* label,
  bool aktif,
  unsigned long mulaiMs,
  unsigned long durasiMs
);
void tampilLCD();
void kirimHistoriManual(bool pestisida, unsigned long mulaiMs);
void kontrolRelay(char perintah);
void mulaiWifi();
String artiKodeHttp(int kode);
bool mulaiHttp(
  HTTPClient& http,
  WiFiClientSecure& secureClient,
  const String& url
);
void setStatusApi(bool status, int kode, bool paksa = false);
void cekApi();
void cekKoneksiWifiDanApi();
bool kirimPost(const String& path, const String& payload);
void kirimSensor();
void nyalakanPompaAir(int detik, const char* sumber);
void matikanPompaAir(const char* alasan);
void nyalakanPompaPestisida(int detik, const char* sumber);
void matikanPompaPestisida(const char* alasan);
void updateTimerPompa();
String ambilNilai(const String& json, const String& key);
void cekPerintahWeb();

// ================= SETUP =================
void setup() {
  Serial.begin(115200);

  // Relay 1 = Pompa Air (GPIO 27), Relay 2 = Pompa Pestisida (GPIO 25)
  pinMode(RELAY_POMPA_AIR, OUTPUT);
  pinMode(RELAY_POMPA_PESTISIDA, OUTPUT);

  // Pastikan kedua relay OFF saat ESP32 menyala
  digitalWrite(RELAY_POMPA_AIR, RELAY_OFF);
  digitalWrite(RELAY_POMPA_PESTISIDA, RELAY_OFF);

  pinMode(PIN_SOIL, INPUT);
  analogReadResolution(12);
  analogSetPinAttenuation(PIN_SOIL, ADC_11db);

  Wire.begin(SDA_LCD, SCL_LCD);
  lcd.init();
  lcd.backlight();

  cetakBarisLCD(0, "MONITOR CABAI");
  cetakBarisLCD(1, "Hubung Wi-Fi...");

  dht.begin();
  waktuTerakhirPestisidaMs = millis();
  mulaiWifi();

  delay(1500);
  bacaSensor();
  tampilan = 2; // Tampilkan status Wi-Fi & API terlebih dahulu
  tampilLCD();
  waktuLCD = millis();

  Serial.println("================================");
  Serial.println(" MONITORING TANAMAN CABAI");
  Serial.println("================================");
  Serial.printf("Relay Pompa Air       : GPIO %d\n", RELAY_POMPA_AIR);
  Serial.printf(
    "Relay Pompa Pestisida : GPIO %d\n",
    RELAY_POMPA_PESTISIDA
  );
  Serial.println("1 = Tes Pompa Air ON/OFF Manual");
  Serial.println("2 = Tes Pompa Pestisida ON/OFF Manual");
  Serial.println("0 = Matikan semua pompa (Emergency OFF)");
  Serial.println(
    "Otomatis pestisida hanya via Web/Telegram "
    "atau 5 hari sekali."
  );
}

// ================= BACA SOIL =================
int bacaSoil() {
  long total = 0;
  for (int i = 0; i < 10; i++) {
    total += analogRead(PIN_SOIL);
    delay(4);
  }
  return total / 10;
}

// ================= HITUNG KELEMBAPAN =================
int hitungKelembapan(int adc) {
  int persen = map(adc, ADC_KERING, ADC_BASAH, 0, 100);
  return constrain(persen, 0, 100);
}

// ================= KONDISI TANAH =================
String kondisiTanah() {
  if (kelembapanTanah < autoWaterThreshold) {
    return "KERING";
  } else if (kelembapanTanah <= 70) {
    return "LEMBAP";
  } else {
    return "BASAH";
  }
}

// ================= BACA SENSOR =================
void bacaSensor() {
  nilaiSoil = bacaSoil();
  kelembapanTanah = hitungKelembapan(nilaiSoil);

  suhu = dht.readTemperature();
  kelembapanUdara = dht.readHumidity();

  Serial.println("\n========== DATA SENSOR ==========");

  Serial.print("Kondisi Tanah: ");
  Serial.println(kondisiTanah());

  Serial.print("Kelembapan Tanah: ");
  Serial.print(kelembapanTanah);
  Serial.println(" %");

  Serial.print("Suhu: ");
  if (isnan(suhu)) {
    Serial.println("Gagal membaca");
  } else {
    Serial.print(suhu);
    Serial.println(" C");
  }

  Serial.print("Kelembapan Udara: ");
  if (isnan(kelembapanUdara)) {
    Serial.println("Gagal membaca");
  } else {
    Serial.print(kelembapanUdara);
    Serial.println(" %");
  }

  unsigned long sekarang = millis();
  if (statusPompaAir) {
    if (durasiPompaAirMs > 0) {
      unsigned long jalan = sekarang - mulaiTimerPompaAirMs;
      unsigned long sisaDtk = (jalan < durasiPompaAirMs)
        ? ((durasiPompaAirMs - jalan + 999UL) / 1000UL)
        : 0;
      Serial.printf(
        ">>> [POMPA AIR HIDUP / ON] GPIO %d (sisa %lu dtk) <<<\n",
        RELAY_POMPA_AIR,
        sisaDtk
      );
    } else {
      Serial.printf(
        ">>> [POMPA AIR HIDUP / ON] GPIO %d (Manual Serial) <<<\n",
        RELAY_POMPA_AIR
      );
    }
  } else {
    Serial.printf(
      "Relay Pompa Air (GPIO %d)       : MATI (OFF)\n",
      RELAY_POMPA_AIR
    );
  }

  if (statusPompaPestisida) {
    if (durasiPompaPestisidaMs > 0) {
      unsigned long jalan = sekarang - mulaiTimerPompaPestisidaMs;
      unsigned long sisaDtk = (jalan < durasiPompaPestisidaMs)
        ? ((durasiPompaPestisidaMs - jalan + 999UL) / 1000UL)
        : 0;
      Serial.printf(
        ">>> [POMPA PESTISIDA HIDUP / ON] GPIO %d (sisa %lu dtk) <<<\n",
        RELAY_POMPA_PESTISIDA,
        sisaDtk
      );
    } else {
      Serial.printf(
        ">>> [POMPA PESTISIDA HIDUP / ON] GPIO %d (Manual) <<<\n",
        RELAY_POMPA_PESTISIDA
      );
    }
  } else {
    Serial.printf(
      "Relay Pompa Pestisida (GPIO %d) : MATI (OFF)\n",
      RELAY_POMPA_PESTISIDA
    );
  }

  Serial.print("Status Wi-Fi: ");
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf(
      "TERHUBUNG (%s | IP: %s)\n",
      WIFI_SSID,
      WiFi.localIP().toString().c_str()
    );
  } else {
    Serial.printf(
      "TIDAK TERHUBUNG (status=%d)\n",
      WiFi.status()
    );
  }

  Serial.print("Status API: ");
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("TIDAK TERHUBUNG (menunggu Wi-Fi)");
  } else {
    Serial.println(
      apiTerhubung ? "TERHUBUNG" : "TIDAK TERHUBUNG"
    );
  }

  Serial.printf(
    "Batas Semprot Air Auto: < %d%%\n",
    autoWaterThreshold
  );
  if (pesticideIntervalLabel.length() > 0) {
    Serial.printf(
      "Jadwal Pestisida: %s | Berikutnya: %s\n",
      pesticideIntervalLabel.c_str(),
      pesticideNextSpray.c_str()
    );
  }
  Serial.printf(
    "Deteksi Daun Web: %s (%.1f%%)%s\n",
    latestLeafCondition.c_str(),
    latestLeafConfidence * 100.0f,
    latestLeafIsDisease
      ? " [PENYAKIT TERDETEKSI - MENUNGGU KONFIRMASI]"
      : ""
  );

  Serial.println("=================================");
}

// ================= TAMPIL LCD =================
void cetakBarisLCD(int baris, String teks) {
  if (teks.length() > 16) {
    teks = teks.substring(0, 16);
  }
  while (teks.length() < 16) {
    teks += ' ';
  }
  lcd.setCursor(0, baris);
  lcd.print(teks);
}

String formatStatusPompaLCD(
  const char* label,
  bool aktif,
  unsigned long mulaiMs,
  unsigned long durasiMs
) {
  if (!aktif) {
    return String(label) + ": OFF";
  }
  if (durasiMs > 0) {
    unsigned long sekarang = millis();
    unsigned long jalan = sekarang - mulaiMs;
    unsigned long sisaDtk = (jalan < durasiMs)
      ? ((durasiMs - jalan + 999UL) / 1000UL)
      : 0;
    return String(label) + ": ON (" + String(sisaDtk) + "d)";
  }
  return String(label) + ": ON MANUAL";
}

void tampilLCD() {
  // Prioritas: jika ada pompa aktif, langsung tampilkan countdown real-time
  if (statusPompaAir || statusPompaPestisida) {
    cetakBarisLCD(
      0,
      formatStatusPompaLCD(
        "Air ",
        statusPompaAir,
        mulaiTimerPompaAirMs,
        durasiPompaAirMs
      )
    );
    cetakBarisLCD(
      1,
      formatStatusPompaLCD(
        "Pest",
        statusPompaPestisida,
        mulaiTimerPompaPestisidaMs,
        durasiPompaPestisidaMs
      )
    );
    return;
  }

  if (tampilan == 0) {
    // Slide 1: Kondisi & Kelembapan Tanah + Batas Siram Otomatis
    cetakBarisLCD(0, "Tanah: " + kondisiTanah());
    cetakBarisLCD(
      1,
      "Lembap:" + String(kelembapanTanah) +
      "% <" + String(autoWaterThreshold) + "%"
    );
  } else if (tampilan == 1) {
    // Slide 2: Suhu & Kelembapan Udara (DHT22)
    String barisSuhu = "Suhu : ";
    if (isnan(suhu)) {
      barisSuhu += "--";
    } else {
      barisSuhu += String(suhu, 1);
      barisSuhu += (char)223;
      barisSuhu += "C";
    }

    String barisUdara = "Udara: ";
    if (isnan(kelembapanUdara)) {
      barisUdara += "--";
    } else {
      barisUdara += String(kelembapanUdara, 1);
      barisUdara += "%";
    }

    cetakBarisLCD(0, barisSuhu);
    cetakBarisLCD(1, barisUdara);
  } else if (tampilan == 2) {
    // Slide 3: Status Koneksi Wi-Fi (dengan sinyal dBm) & API Server
    if (WiFi.status() == WL_CONNECTED) {
      cetakBarisLCD(0, "WiFi:OK " + String(WiFi.RSSI()) + "dBm");
      cetakBarisLCD(
        1,
        apiTerhubung ? "API : TERHUBUNG" : "API : TERPUTUS"
      );
    } else {
      cetakBarisLCD(0, "WiFi: RECONNECT");
      cetakBarisLCD(1, "Mencoba ulang...");
    }
  } else if (tampilan == 3) {
    // Slide 4: Ringkasan Pompa & Alamat IP ESP32
    cetakBarisLCD(0, "Air:OFF Pest:OFF");
    if (WiFi.status() == WL_CONNECTED) {
      cetakBarisLCD(1, WiFi.localIP().toString());
    } else {
      cetakBarisLCD(1, "IP: Belum dapat");
    }
  } else {
    // Slide 5: Hasil Deteksi Penyakit Daun Terakhir dari Web AI
    if (latestLeafCondition == "Belum ada deteksi" ||
        latestLeafCondition.length() == 0) {
      cetakBarisLCD(0, "Daun: Belum ada");
      cetakBarisLCD(1, "deteksi di web");
    } else {
      cetakBarisLCD(
        0,
        latestLeafIsDisease ? "DAUN: PENYAKIT!" : "Daun: SEHAT"
      );
      int persenConf = (int)(latestLeafConfidence * 100.0f + 0.5f);
      String infoDaun = latestLeafCondition;
      if (persenConf > 0) {
        String sufiks = " " + String(persenConf) + "%";
        if (infoDaun.length() + sufiks.length() <= 16) {
          infoDaun += sufiks;
        }
      }
      cetakBarisLCD(1, infoDaun);
    }
  }

  tampilan = (tampilan + 1) % 5;
}

// ================= KONTROL RELAY MANUAL SERIAL =================
void kirimHistoriManual(bool pestisida, unsigned long mulaiMs) {
  int detik = (millis() - mulaiMs) / 1000UL;
  if (detik < 1) detik = 1;
  if (detik > 300) detik = 300;

  String label = pestisida ? "pestisida" : "air";
  String payload =
    "{\"trigger_mode\":\"manual\","
    "\"triggered_by\":\"esp32_serial\","
    "\"duration_seconds\":" + String(detik) +
    ",\"notes\":\"Tes semprot " + label + " manual Serial ESP32\"}";

  kirimPost(
    pestisida ? "/spray/pesticide/history" : "/spray/water/history",
    payload
  );
}

void kontrolRelay(char perintah) {
  if (perintah == '1') {
    if (!statusPompaAir) {
      statusPompaAir = true;
      durasiPompaAirMs = 0; // Mode manual sampai dimatikan manual
      mulaiManualPompaAirMs = millis();
      digitalWrite(RELAY_POMPA_AIR, RELAY_ON);
      tampilLCD();
      waktuLCD = millis();
      Serial.println("\n>>> [ALERT] POMPA AIR HIDUP (ON) <<<");
      Serial.printf(
        "Pompa Air (GPIO %d) STATUS: HIDUP | Manual Serial\n",
        RELAY_POMPA_AIR
      );
    } else {
      matikanPompaAir("Manual Serial");
      kirimHistoriManual(false, mulaiManualPompaAirMs);
    }
  } else if (perintah == '2') {
    if (!statusPompaPestisida) {
      statusPompaPestisida = true;
      durasiPompaPestisidaMs = 0; // Mode manual sampai dimatikan
      mulaiManualPompaPestisidaMs = millis();
      waktuTerakhirPestisidaMs = mulaiManualPompaPestisidaMs;
      digitalWrite(RELAY_POMPA_PESTISIDA, RELAY_ON);
      tampilLCD();
      waktuLCD = millis();
      Serial.println("\n>>> [ALERT] POMPA PESTISIDA HIDUP (ON) <<<");
      Serial.printf(
        "Pompa Pestisida (GPIO %d) STATUS: HIDUP | Manual Serial\n",
        RELAY_POMPA_PESTISIDA
      );
    } else {
      matikanPompaPestisida("Manual Serial");
      kirimHistoriManual(true, mulaiManualPompaPestisidaMs);
    }
  } else if (perintah == '0') {
    bool airNyalaManual =
      statusPompaAir && (durasiPompaAirMs == 0);
    bool pestisidaNyalaManual =
      statusPompaPestisida && (durasiPompaPestisidaMs == 0);
    matikanPompaAir("Emergency OFF Serial");
    matikanPompaPestisida("Emergency OFF Serial");

    if (airNyalaManual) {
      kirimHistoriManual(false, mulaiManualPompaAirMs);
    }
    if (pestisidaNyalaManual) {
      kirimHistoriManual(true, mulaiManualPompaPestisidaMs);
    }
  }
}

// ================= KONEKSI WIFI & API =================
void mulaiWifi() {
  waktuWifiRetry = millis();
  WiFi.persistent(false);
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.setSleep(false);
  WiFi.disconnect(false);
  delay(100);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.printf(
    "[KONEKSI] Menghubungkan ke Wi-Fi '%s' ...\n",
    WIFI_SSID
  );
}

String artiKodeHttp(int kode) {
  if (kode == HTTPC_ERROR_CONNECTION_REFUSED) {
    return "Koneksi ditolak (server mati / port salah)";
  }
  if (kode == HTTPC_ERROR_CONNECTION_LOST) {
    return "Koneksi terputus";
  }
  if (kode == HTTPC_ERROR_READ_TIMEOUT) {
    return "Timeout (IP salah / firewall / hotspot)";
  }
  if (kode < 0) {
    return "Tidak bisa menjangkau server (cek URL/Wi-Fi)";
  }
  if (kode == 404) {
    return "URL API tidak ditemukan";
  }
  if (kode >= 500) {
    return "Server error (cek Laravel / TiDB Cloud)";
  }
  return "HTTP " + String(kode);
}

bool mulaiHttp(
  HTTPClient& http,
  WiFiClientSecure& secureClient,
  const String& url
) {
  http.setReuse(false);
  http.setConnectTimeout(4000);
  http.setTimeout(6000);
  if (url.startsWith("https://")) {
    secureClient.setInsecure();
    return http.begin(secureClient, url);
  }
  return http.begin(url);
}

void setStatusApi(bool status, int kode, bool paksa) {
  if (status) {
    apiGagalBeruntun = 0;
    if (!apiTerhubung) {
      apiTerhubung = true;
      Serial.println(
        "\n>>> [KONEKSI] Wi-Fi: TERHUBUNG | API: TERHUBUNG <<<\n"
      );
      if (!statusPompaAir && !statusPompaPestisida) {
        tampilan = 2;
        tampilLCD();
        waktuLCD = millis();
      }
    }
    return;
  }

  apiGagalBeruntun++;
  if ((paksa || apiGagalBeruntun >= 2) && apiTerhubung) {
    apiTerhubung = false;
    pollHttp.setReuse(false);
    pollHttp.end();
    pollSecureClient.stop();
    const char* wf = (WiFi.status() == WL_CONNECTED)
      ? "TERHUBUNG"
      : "TIDAK TERHUBUNG";
    Serial.printf(
      "\n>>> [KONEKSI] Wi-Fi: %s | API: TIDAK TERHUBUNG (%d - %s) <<<\n\n",
      wf,
      kode,
      artiKodeHttp(kode).c_str()
    );
    if (!statusPompaAir && !statusPompaPestisida) {
      tampilan = 2;
      tampilLCD();
      waktuLCD = millis();
    }
  }
}

void cekApi() {
  if (WiFi.status() != WL_CONNECTED) {
    apiTerhubung = false;
    return;
  }

  HTTPClient http;
  WiFiClientSecure secureClient;
  if (!mulaiHttp(http, secureClient, API_BASE_URL + "/health")) {
    setStatusApi(false, -1, true);
    Serial.println(
      "[KONEKSI] Wi-Fi: TERHUBUNG | API: Gagal init HTTP"
    );
    return;
  }
  http.addHeader("Accept", "application/json");
  http.addHeader("Connection", "close");
  int kode = http.GET();
  http.end();

  if (kode == 200) {
    Serial.printf(
      "[KONEKSI] Cek %s/health -> OK (200)\n",
      API_BASE_URL.c_str()
    );
    setStatusApi(true, kode, true);
  } else {
    setStatusApi(false, kode, true);
    Serial.printf(
      "[KONEKSI] Wi-Fi: TERHUBUNG | API: GAGAL (%d: %s)\n",
      kode,
      artiKodeHttp(kode).c_str()
    );
  }
}

void cekKoneksiWifiDanApi() {
  if (WiFi.status() == WL_CONNECTED) {
    if (!wifiSudahTampil) {
      wifiSudahTampil = true;
      Serial.println("\n>>> [KONEKSI] Wi-Fi: TERHUBUNG <<<");
      Serial.printf("    SSID       : %s\n", WIFI_SSID);
      Serial.printf(
        "    IP ESP32   : %s\n",
        WiFi.localIP().toString().c_str()
      );
      Serial.printf("    Server API : %s\n", API_BASE_URL.c_str());
      waktuCekApi = 0;
      waktuPoll = millis();
      waktuKirim = millis() - (SENSOR_SEND_MS / 2) + 3000;
      if (!statusPompaAir && !statusPompaPestisida) {
        tampilan = 2;
        tampilLCD();
        waktuLCD = millis();
      }
    }

    if (!apiTerhubung &&
        (waktuCekApi == 0 || millis() - waktuCekApi >= CEK_API_MS)) {
      waktuCekApi = millis();
      cekApi();
      updateTimerPompa();
    }
    return;
  }

  bool baruTerputus = wifiSudahTampil;
  if (baruTerputus) {
    Serial.println(
      "\n>>> [KONEKSI] Wi-Fi: TERPUTUS | API: TIDAK TERHUBUNG <<<\n"
    );
  }
  wifiSudahTampil = false;
  apiTerhubung = false;
  pollHttp.setReuse(false);
  pollHttp.end();
  pollSecureClient.stop();
  if (baruTerputus && !statusPompaAir && !statusPompaPestisida) {
    tampilan = 2;
    tampilLCD();
    waktuLCD = millis();
  }

  if (millis() - waktuWifiRetry >= WIFI_RETRY_MS) {
    Serial.printf(
      "[KONEKSI] Wi-Fi belum terhubung (status=%d), mencoba ulang...\n",
      WiFi.status()
    );
    mulaiWifi();
  }
}

bool kirimPost(const String& path, const String& payload) {
  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient http;
  WiFiClientSecure secureClient;
  if (!mulaiHttp(http, secureClient, API_BASE_URL + path)) {
    setStatusApi(false, -1, false);
    return false;
  }
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Connection", "close");

  int kode = http.POST(payload);
  bool ok = kode >= 200 && kode < 300;
  String info = ok
    ? ("BERHASIL (" + String(kode) + ")")
    : ("GAGAL (" + String(kode) + "): " + artiKodeHttp(kode));
  Serial.printf("[API] POST %s -> %s\n", path.c_str(), info.c_str());
  http.end();
  setStatusApi(ok, kode, false);
  return ok;
}

void kirimSensor() {
  if (!kirimTahapDhtNext) {
    kirimTahapDhtNext = true;
    kirimPost(
      "/sensor/soil-moisture",
      "{\"moisture_percent\":" + String(kelembapanTanah) +
      ",\"adc_value\":" + String(nilaiSoil) +
      ",\"source\":\"esp32\"}"
    );
  } else {
    kirimTahapDhtNext = false;
    if (!isnan(suhu) && !isnan(kelembapanUdara)) {
      kirimPost(
        "/sensor/dht22",
        "{\"temperature\":" + String(suhu, 1) +
        ",\"humidity\":" + String(kelembapanUdara, 1) +
        ",\"source\":\"esp32\"}"
      );
    }
  }
  updateTimerPompa();
}

// ================= KONTROL POMPA TERPISAH =================
// --- RELAY 1 (GPIO 27): KONTROL KHUSUS POMPA AIR ---
void nyalakanPompaAir(int detik, const char* sumber) {
  if (detik < 1) detik = 1;
  unsigned long sekarang = millis();
  unsigned long tambahanMs = (unsigned long)detik * 1000UL;

  if (statusPompaAir && durasiPompaAirMs > 0) {
    unsigned long berjalanMs = sekarang - mulaiTimerPompaAirMs;
    unsigned long sisaMs = (berjalanMs < durasiPompaAirMs)
      ? (durasiPompaAirMs - berjalanMs)
      : 0;
    mulaiTimerPompaAirMs = sekarang;
    durasiPompaAirMs = sisaMs + tambahanMs;
  } else {
    mulaiTimerPompaAirMs = sekarang;
    durasiPompaAirMs = tambahanMs;
  }

  statusPompaAir = true;
  digitalWrite(RELAY_POMPA_AIR, RELAY_ON);
  tampilLCD();
  waktuLCD = millis();

  Serial.println("\n>>> [ALERT] POMPA AIR HIDUP (ON) <<<");
  Serial.printf(
    "Pompa Air (GPIO %d) HIDUP %d detik | Sumber: %s\n\n",
    RELAY_POMPA_AIR,
    detik,
    sumber
  );
}

void matikanPompaAir(const char* alasan) {
  bool sebelumnyaNyala = statusPompaAir;
  statusPompaAir = false;
  durasiPompaAirMs = 0;
  digitalWrite(RELAY_POMPA_AIR, RELAY_OFF);

  if (sebelumnyaNyala) {
    tampilLCD();
    waktuLCD = millis();
    Serial.println("\n>>> [INFO] POMPA AIR MATI (OFF) <<<");
    Serial.printf(
      "Pompa Air (GPIO %d) STATUS: MATI (%s)\n\n",
      RELAY_POMPA_AIR,
      alasan
    );
  }
}

// --- RELAY 2 (GPIO 25): KONTROL KHUSUS POMPA PESTISIDA ---
void nyalakanPompaPestisida(int detik, const char* sumber) {
  if (detik < 1) detik = 1;
  unsigned long sekarang = millis();
  unsigned long tambahanMs = (unsigned long)detik * 1000UL;

  if (statusPompaPestisida && durasiPompaPestisidaMs > 0) {
    unsigned long berjalanMs = sekarang - mulaiTimerPompaPestisidaMs;
    unsigned long sisaMs = (berjalanMs < durasiPompaPestisidaMs)
      ? (durasiPompaPestisidaMs - berjalanMs)
      : 0;
    mulaiTimerPompaPestisidaMs = sekarang;
    durasiPompaPestisidaMs = sisaMs + tambahanMs;
  } else {
    mulaiTimerPompaPestisidaMs = sekarang;
    durasiPompaPestisidaMs = tambahanMs;
  }

  statusPompaPestisida = true;
  // Reset hitungan jadwal 5 hari sekali
  waktuTerakhirPestisidaMs = sekarang;
  digitalWrite(RELAY_POMPA_PESTISIDA, RELAY_ON);
  tampilLCD();
  waktuLCD = millis();

  Serial.println("\n>>> [ALERT] POMPA PESTISIDA HIDUP (ON) <<<");
  Serial.printf(
    "Pompa Pestisida (GPIO %d) HIDUP %d detik | Sumber: %s\n\n",
    RELAY_POMPA_PESTISIDA,
    detik,
    sumber
  );
}

void matikanPompaPestisida(const char* alasan) {
  bool sebelumnyaNyala = statusPompaPestisida;
  statusPompaPestisida = false;
  durasiPompaPestisidaMs = 0;
  digitalWrite(RELAY_POMPA_PESTISIDA, RELAY_OFF);

  if (sebelumnyaNyala) {
    tampilLCD();
    waktuLCD = millis();
    Serial.println("\n>>> [INFO] POMPA PESTISIDA MATI (OFF) <<<");
    Serial.printf(
      "Pompa Pestisida (GPIO %d) STATUS: MATI (%s)\n\n",
      RELAY_POMPA_PESTISIDA,
      alasan
    );
  }
}

void updateTimerPompa() {
  unsigned long sekarang = millis();

  if (statusPompaAir &&
      durasiPompaAirMs > 0 &&
      (sekarang - mulaiTimerPompaAirMs >= durasiPompaAirMs)) {
    matikanPompaAir("selesai");
  }

  if (statusPompaPestisida &&
      durasiPompaPestisidaMs > 0 &&
      (sekarang - mulaiTimerPompaPestisidaMs >= durasiPompaPestisidaMs)) {
    matikanPompaPestisida("selesai");
  }
}

String ambilNilai(const String& json, const String& key) {
  int i = json.indexOf("\"" + key + "\"");
  if (i < 0) return "";
  i = json.indexOf(':', i);
  if (i < 0) return "";
  i++;
  while (i < (int)json.length() &&
         (json[i] == ' ' || json[i] == '\t')) {
    i++;
  }
  if (i >= (int)json.length()) return "";

  if (json[i] == '"') {
    i++;
    String out = "";
    while (i < (int)json.length()) {
      char c = json[i];
      if (c == '\\' && i + 1 < (int)json.length()) {
        i++;
        out += json[i];
      } else if (c == '"') {
        break;
      } else {
        out += c;
      }
      i++;
    }
    return out;
  }

  int j = i;
  while (j < (int)json.length() &&
         json[j] != ',' &&
         json[j] != '}') {
    j++;
  }
  String v = json.substring(i, j);
  v.trim();
  if (v == "null") return "";
  return v;
}

void cekPerintahWeb() {
  if (WiFi.status() != WL_CONNECTED) return;

  String url = API_BASE_URL + "/esp32/poll";
  pollHttp.setReuse(false);
  pollHttp.setConnectTimeout(4000);
  pollHttp.setTimeout(6000);

  bool began = false;
  if (url.startsWith("https://")) {
    pollSecureClient.setInsecure();
    began = pollHttp.begin(pollSecureClient, url);
  } else {
    began = pollHttp.begin(url);
  }

  if (!began) {
    pollHttp.end();
    pollSecureClient.stop();
    setStatusApi(false, -1, false);
    return;
  }
  pollHttp.addHeader("Accept", "application/json");
  pollHttp.addHeader("Connection", "close");
  pollHttp.addHeader("X-ESP32-Device", "1");

  int kode = pollHttp.GET();
  updateTimerPompa();

  if (kode == 200) {
    setStatusApi(true, kode);
    String res = pollHttp.getString();
    if (res.length() == 0) {
      Serial.println("[API] Poll 200 tapi body respons kosong!");
      pollHttp.end();
      return;
    }

    String thStr = ambilNilai(res, "auto_water_threshold");
    int th = thStr.length() > 0 ? thStr.toInt() : 0;

    String mode = ambilNilai(res, "pesticide_schedule_mode");
    int intHrs = ambilNilai(res, "pesticide_interval_hours").toInt();
    String label = ambilNilai(res, "pesticide_interval_label");
    String nextSpray = ambilNilai(
      res,
      "pesticide_next_spray_formatted"
    );
    String targetDt = ambilNilai(res, "pesticide_target_datetime");

    String leafCond = ambilNilai(res, "latest_leaf_condition");
    float leafConf = ambilNilai(
      res,
      "latest_leaf_confidence"
    ).toFloat();
    String leafModel = ambilNilai(res, "latest_leaf_model");
    bool leafDisease = (
      ambilNilai(res, "latest_leaf_is_disease") == "true"
    );
    String leafRec = ambilNilai(res, "latest_leaf_recommendation");
    String leafTime = ambilNilai(res, "latest_leaf_detected_at");
    String leafEventKey = ambilNilai(res, "latest_leaf_event_key");

    if (!configAwalSudahSync) {
      configAwalSudahSync = true;
      if (th >= 1 && th <= 95) {
        autoWaterThreshold = th;
      }
      if (mode.length() > 0) pesticideScheduleMode = mode;
      if (intHrs > 0) pesticideIntervalHours = intHrs;
      if (label.length() > 0) pesticideIntervalLabel = label;
      if (nextSpray.length() > 0) pesticideNextSpray = nextSpray;
      pesticideTargetDatetime = targetDt;

      if (leafCond.length() > 0) latestLeafCondition = leafCond;
      latestLeafConfidence = leafConf;
      if (leafModel.length() > 0) latestLeafModel = leafModel;
      latestLeafIsDisease = leafDisease;
      if (leafRec.length() > 0) latestLeafRecommendation = leafRec;
      if (leafTime.length() > 0) latestLeafDetectedAt = leafTime;
      latestLeafEventKey = leafEventKey;

      Serial.println(
        "\n[SYNC SERVER] Konfigurasi awal berhasil disinkronkan:"
      );
      Serial.printf(
        "  - Batas Semprot Air Auto : < %d%%\n",
        autoWaterThreshold
      );
      if (pesticideIntervalLabel.length() > 0) {
        Serial.printf(
          "  - Jadwal Pestisida       : %s (%d jam) | Next: %s\n",
          pesticideIntervalLabel.c_str(),
          pesticideIntervalHours,
          pesticideNextSpray.c_str()
        );
      }
      Serial.printf(
        "  - Deteksi Daun Terakhir  : %s (%.1f%%)%s\n\n",
        latestLeafCondition.c_str(),
        latestLeafConfidence * 100.0f,
        latestLeafIsDisease ? " [PENYAKIT TERDETEKSI!]" : ""
      );
    } else {
      bool eventBaru = (
        leafEventKey.length() > 0 &&
        leafEventKey != latestLeafEventKey &&
        leafEventKey != "none"
      );
      bool kondisiBaru = (
        leafCond.length() > 0 &&
        leafCond != latestLeafCondition
      );

      if (eventBaru || kondisiBaru) {
        if (leafCond.length() > 0) latestLeafCondition = leafCond;
        latestLeafConfidence = leafConf;
        if (leafModel.length() > 0) latestLeafModel = leafModel;
        latestLeafIsDisease = leafDisease;
        if (leafRec.length() > 0) latestLeafRecommendation = leafRec;
        if (leafTime.length() > 0) latestLeafDetectedAt = leafTime;
        latestLeafEventKey = leafEventKey;

        if (latestLeafIsDisease) {
          Serial.println(
            "\n>>> [PERINGATAN: PENYAKIT DAUN TERDETEKSI DI WEB!] <<<"
          );
          Serial.println(
            "Menunggu konfirmasi pengguna di Website atau Telegram "
            "sebelum menyemprot pestisida."
          );
        } else {
          Serial.println(
            "\n>>> [UPDATE DETEKSI DAUN WEBSITE] <<<"
          );
        }
        Serial.printf(
          "Kondisi / Penyakit : %s (%.1f%%)\n",
          latestLeafCondition.c_str(),
          latestLeafConfidence * 100.0f
        );
        const char* stTanaman = latestLeafIsDisease
          ? "TERDETEKSI PENYAKIT (Perlu Konfirmasi Web/Telegram)"
          : "Daun Sehat / Normal";
        Serial.printf("Status Tanaman     : %s\n", stTanaman);
        Serial.printf(
          "Model AI           : %s\n",
          latestLeafModel.c_str()
        );
        Serial.printf(
          "Waktu Deteksi      : %s\n",
          latestLeafDetectedAt.c_str()
        );
        Serial.printf(
          "Rekomendasi        : %s\n\n",
          latestLeafRecommendation.c_str()
        );
      }

      if (th >= 1 && th <= 95 && th != autoWaterThreshold) {
        int thresholdLama = autoWaterThreshold;
        autoWaterThreshold = th;
        Serial.println("\n>>> [UPDATE SEMPROT AIR] <<<");
        Serial.printf(
          "Batas siram air auto diubah: < %d%% (sebelumnya < %d%%)\n",
          autoWaterThreshold,
          thresholdLama
        );
        Serial.printf(
          "Kondisi tanah saat ini (%d%%): %s\n\n",
          kelembapanTanah,
          kondisiTanah().c_str()
        );
      }

      bool jadwalBerubah =
        (mode.length() > 0 && mode != pesticideScheduleMode) ||
        (intHrs > 0 && intHrs != pesticideIntervalHours) ||
        (label.length() > 0 && label != pesticideIntervalLabel) ||
        (targetDt != pesticideTargetDatetime) ||
        (nextSpray.length() > 0 && nextSpray != pesticideNextSpray);

      if (jadwalBerubah) {
        String labelLama = pesticideIntervalLabel;
        int jamLama = pesticideIntervalHours;
        if (mode.length() > 0) pesticideScheduleMode = mode;
        if (intHrs > 0) pesticideIntervalHours = intHrs;
        if (label.length() > 0) pesticideIntervalLabel = label;
        if (nextSpray.length() > 0) pesticideNextSpray = nextSpray;
        pesticideTargetDatetime = targetDt;

        Serial.println("\n>>> [UPDATE SEMPROT PESTISIDA] <<<");
        if (pesticideScheduleMode == "target_datetime") {
          Serial.printf(
            "Jadwal target khusus pestisida diubah: %s\n\n",
            pesticideNextSpray.c_str()
          );
        } else {
          const char* lblOld = labelLama.length() > 0
            ? labelLama.c_str()
            : "-";
          Serial.printf(
            "Jadwal rutin diubah: %s (%d jam) [lama: %s (%d jam)]\n",
            pesticideIntervalLabel.c_str(),
            pesticideIntervalHours,
            lblOld,
            jamLama
          );
          Serial.printf(
            "Jadwal berikutnya: %s\n\n",
            pesticideNextSpray.c_str()
          );
        }
      }
    }

    // Perintah Pompa Air dari Website / Telegram / Chatbot
    if (ambilNilai(res, "spray_water") == "true") {
      int d = ambilNilai(res, "water_duration_seconds").toInt();
      nyalakanPompaAir(
        d > 0 ? d : 5,
        "Website / Telegram / Chatbot"
      );
    }

    // Perintah Pompa Pestisida HANYA dari konfirmasi Website / Telegram
    // atau jadwal 5 hari sekali
    if (ambilNilai(res, "spray_pesticide") == "true") {
      int d = ambilNilai(res, "pesticide_duration_seconds").toInt();
      nyalakanPompaPestisida(
        d > 0 ? d : 5,
        "Konfirmasi Website / Telegram / Jadwal Server"
      );
    }
    pollHttp.end();
  } else {
    Serial.printf(
      "[API] Poll /esp32/poll gagal (%d): %s\n",
      kode,
      artiKodeHttp(kode).c_str()
    );
    pollHttp.setReuse(false);
    pollHttp.end();
    pollSecureClient.stop();
    if (kode < 0 || kode >= 500) {
      setStatusApi(false, kode, false);
    }
  }
}

// ================= LOOP =================
void loop() {
  cekKoneksiWifiDanApi();
  updateTimerPompa();

  if (Serial.available()) {
    char perintah = Serial.read();
    if (perintah != '\n' && perintah != '\r') {
      kontrolRelay(perintah);
    }
  }

  if (WiFi.status() == WL_CONNECTED &&
      apiTerhubung &&
      millis() - waktuPoll >= POLL_MS) {
    waktuPoll = millis();
    cekPerintahWeb();
    updateTimerPompa();
  }

  // Jadwal lokal 5 hari sekali (120 jam) untuk Pompa Pestisida
  // (fallback jika server offline / belum pernah semprot)
  unsigned long selangPestisidaMs =
    millis() - waktuTerakhirPestisidaMs;
  if (!statusPompaPestisida &&
      selangPestisidaMs >= PESTICIDE_INTERVAL_5_HARI_MS) {
    nyalakanPompaPestisida(
      PESTICIDE_AUTO_DURATION_SEC,
      "Jadwal Otomatis 5 Hari Sekali"
    );
    if (WiFi.status() == WL_CONNECTED && apiTerhubung) {
      kirimPost(
        "/spray/pesticide/history",
        "{\"trigger_mode\":\"schedule_5_days\","
        "\"triggered_by\":\"esp32_jadwal_5_hari\","
        "\"duration_seconds\":" + String(PESTICIDE_AUTO_DURATION_SEC) +
        ",\"notes\":\"Penyemprotan pestisida otomatis "
        "tiap 5 hari sekali (120 jam)\"}"
      );
    }
  }

  if (millis() - waktuBaca >= 2000) {
    waktuBaca = millis();
    bacaSensor();
    updateTimerPompa();

    // Otomatis menyiram AIR saat tanah kering
    // (Pompa Pestisida tidak ikut menyala)
    bool cooldownSelesai = (
      waktuSiramAuto == 0 ||
      millis() - waktuSiramAuto >= AUTO_WATER_COOLDOWN_MS
    );
    if (kelembapanTanah < autoWaterThreshold &&
        !statusPompaAir &&
        cooldownSelesai) {
      waktuSiramAuto = millis();
      nyalakanPompaAir(5, "Otomatis (Tanah Kering)");
      if (WiFi.status() == WL_CONNECTED && apiTerhubung) {
        String notes =
          "Penyiraman otomatis ESP32 (batas <" +
          String(autoWaterThreshold) + "%)";
        kirimPost(
          "/spray/water/history",
          "{\"trigger_mode\":\"auto\","
          "\"triggered_by\":\"esp32\","
          "\"duration_seconds\":5,"
          "\"soil_moisture\":" + String(kelembapanTanah) +
          ",\"notes\":\"" + notes + "\"}"
        );
      }
    }
  }

  if (WiFi.status() == WL_CONNECTED &&
      apiTerhubung &&
      millis() - waktuKirim >= (SENSOR_SEND_MS / 2)) {
    waktuKirim = millis();
    kirimSensor();
  }

  unsigned long intervalLcdAktif =
    (statusPompaAir || statusPompaPestisida)
      ? LCD_PUMP_INTERVAL_MS
      : LCD_INTERVAL_MS;
  if (millis() - waktuLCD >= intervalLcdAktif) {
    waktuLCD = millis();
    tampilLCD();
  }
}
