#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <DHT.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include "secrets.h"

// ================= PIN ESP32 =================
#define PIN_SOIL 35
#define RELAY1 27
#define RELAY2 26

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
const unsigned long WIFI_RETRY_MS = 15000;
const unsigned long SENSOR_SEND_MS = 10000;
const unsigned long POLL_MS = 1000;
const unsigned long AUTO_WATER_COOLDOWN_MS = 60000;
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
bool kirimTahapDhtNext = false;
bool wifiSudahTampil = false;
bool apiTerhubung = false;
int apiGagalBeruntun = 0;
unsigned long waktuCekApi = 0;
const unsigned long CEK_API_MS = 10000;

WiFiClientSecure pollSecureClient;
HTTPClient pollHttp;

unsigned long stopRelay1Ms = 0;
unsigned long stopRelay2Ms = 0;

// ================= KALIBRASI SOIL =================
const int ADC_KERING = 3000;
const int ADC_BASAH = 1200;

// ================= VARIABEL =================
int nilaiSoil = 0;
int kelembapanTanah = 0;

float suhu = 0;
float kelembapanUdara = 0;

bool statusRelay1 = false;
bool statusRelay2 = false;

unsigned long waktuBaca = 0;
unsigned long waktuLCD = 0;

int tampilan = 0;

// ================= PROTOTIPE FUNGSI =================
int bacaSoil();
int hitungKelembapan(int adc);
String kondisiTanah();
void bacaSensor();
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
void nyalakanRelay(int nomor, int detik, const char* sumber);
void updateTimerRelay();
String ambilNilai(const String& json, const String& key);
void cekPerintahWeb();

// ================= SETUP =================
void setup() {
  Serial.begin(115200);

  pinMode(RELAY1, OUTPUT);
  pinMode(RELAY2, OUTPUT);

  digitalWrite(RELAY1, RELAY_OFF);
  digitalWrite(RELAY2, RELAY_OFF);

  pinMode(PIN_SOIL, INPUT);
  analogReadResolution(12);
  analogSetPinAttenuation(PIN_SOIL, ADC_11db);

  Wire.begin(SDA_LCD, SCL_LCD);
  lcd.init();
  lcd.backlight();

  lcd.setCursor(0, 0);
  lcd.print("MONITOR CABAI");
  lcd.setCursor(0, 1);
  lcd.print("Memulai...");

  dht.begin();
  mulaiWifi();

  delay(2000);
  lcd.clear();

  Serial.println("================================");
  Serial.println(" MONITORING TANAMAN CABAI");
  Serial.println("================================");
  Serial.println("1 = Relay 1 / Pompa Air ON/OFF");
  Serial.println("2 = Relay 2 / Pompa Pestisida ON/OFF");
  Serial.println("0 = Matikan semua relay");
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

  Serial.print("Relay 1 (Pompa Air): ");
  Serial.println(statusRelay1 ? "ON" : "OFF");

  Serial.print("Relay 2 (Pompa Pestisida): ");
  Serial.println(statusRelay2 ? "ON" : "OFF");

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
    latestLeafIsDisease ? " [PENYAKIT TERDETEKSI!]" : ""
  );

  Serial.println("=================================");
}

// ================= TAMPIL LCD =================
void tampilLCD() {
  lcd.clear();

  if (tampilan == 0) {
    lcd.setCursor(0, 0);
    lcd.print("Tanah: ");
    lcd.print(kondisiTanah());

    lcd.setCursor(0, 1);
    lcd.print("Kelembapan:");
    lcd.print(kelembapanTanah);
    lcd.print("%");
  } else {
    lcd.setCursor(0, 0);
    lcd.print("Suhu: ");

    if (isnan(suhu)) {
      lcd.print("--");
    } else {
      lcd.print(suhu, 1);
      lcd.print((char)223);
      lcd.print("C");
    }

    lcd.setCursor(0, 1);
    lcd.print("Udara: ");

    if (isnan(kelembapanUdara)) {
      lcd.print("--");
    } else {
      lcd.print(kelembapanUdara, 1);
      lcd.print("%");
    }
  }

  tampilan = 1 - tampilan;
}

// ================= KONTROL RELAY =================
unsigned long mulaiRelay1Ms = 0;
unsigned long mulaiRelay2Ms = 0;

void kirimHistoriManual(bool pestisida, unsigned long mulaiMs) {
  int detik = (millis() - mulaiMs) / 1000UL;
  if (detik < 1) detik = 1;
  if (detik > 300) detik = 300;

  String payload =
    "{\"trigger_mode\":\"manual\","
    "\"triggered_by\":\"esp32_serial\","
    "\"duration_seconds\":" + String(detik) +
    ",\"notes\":\"Penyemprotan manual Serial Monitor ESP32\"}";

  kirimPost(
    pestisida ? "/spray/pesticide/history" : "/spray/water/history",
    payload
  );
}

void kontrolRelay(char perintah) {
  if (perintah == '1') {
    statusRelay1 = !statusRelay1;
    stopRelay1Ms = 0;
    digitalWrite(RELAY1, statusRelay1 ? RELAY_ON : RELAY_OFF);

    Serial.println("\n>>> [UPDATE SEMPROT AIR] <<<");
    Serial.println(
      statusRelay1
        ? "Pompa Air (Relay 1) STATUS: ON | Manual Serial"
        : "Pompa Air (Relay 1) STATUS: OFF | Manual Serial"
    );

    if (statusRelay1) {
      mulaiRelay1Ms = millis();
    } else {
      kirimHistoriManual(false, mulaiRelay1Ms);
    }
  } else if (perintah == '2') {
    statusRelay2 = !statusRelay2;
    stopRelay2Ms = 0;
    digitalWrite(RELAY2, statusRelay2 ? RELAY_ON : RELAY_OFF);

    Serial.println("\n>>> [UPDATE SEMPROT PESTISIDA] <<<");
    Serial.println(
      statusRelay2
        ? "Pompa Pestisida (Relay 2) STATUS: ON | Manual Serial"
        : "Pompa Pestisida (Relay 2) STATUS: OFF | Manual Serial"
    );

    if (statusRelay2) {
      mulaiRelay2Ms = millis();
    } else {
      kirimHistoriManual(true, mulaiRelay2Ms);
    }
  } else if (perintah == '0') {
    bool relay1Nyala = statusRelay1;
    bool relay2Nyala = statusRelay2;
    statusRelay1 = false;
    statusRelay2 = false;
    stopRelay1Ms = 0;
    stopRelay2Ms = 0;

    digitalWrite(RELAY1, RELAY_OFF);
    digitalWrite(RELAY2, RELAY_OFF);

    Serial.println("\n>>> [UPDATE SEMPROT AIR & PESTISIDA] <<<");
    Serial.println(
      "Semua Pompa (Relay 1 & 2) STATUS: OFF | Manual Serial"
    );

    if (relay1Nyala) kirimHistoriManual(false, mulaiRelay1Ms);
    if (relay2Nyala) kirimHistoriManual(true, mulaiRelay2Ms);
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
    }

    if (!apiTerhubung &&
        (waktuCekApi == 0 || millis() - waktuCekApi >= CEK_API_MS)) {
      waktuCekApi = millis();
      cekApi();
      updateTimerRelay();
    }
    return;
  }

  if (wifiSudahTampil) {
    Serial.println(
      "\n>>> [KONEKSI] Wi-Fi: TERPUTUS | API: TIDAK TERHUBUNG <<<\n"
    );
  }
  wifiSudahTampil = false;
  apiTerhubung = false;
  pollHttp.setReuse(false);
  pollHttp.end();
  pollSecureClient.stop();

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
  updateTimerRelay();
}

void nyalakanRelay(int nomor, int detik, const char* sumber) {
  unsigned long sekarang = millis();
  if (nomor == 1) {
    unsigned long sisaMs = (statusRelay1 && stopRelay1Ms > sekarang)
      ? (stopRelay1Ms - sekarang)
      : 0;
    statusRelay1 = true;
    digitalWrite(RELAY1, RELAY_ON);
    stopRelay1Ms = sekarang + sisaMs + detik * 1000UL;
    Serial.println("\n>>> [UPDATE SEMPROT AIR] <<<");
    Serial.printf(
      "Pompa Air (Relay 1) STATUS: ON %d detik | Sumber: %s\n\n",
      detik,
      sumber
    );
  } else {
    unsigned long sisaMs = (statusRelay2 && stopRelay2Ms > sekarang)
      ? (stopRelay2Ms - sekarang)
      : 0;
    statusRelay2 = true;
    digitalWrite(RELAY2, RELAY_ON);
    stopRelay2Ms = sekarang + sisaMs + detik * 1000UL;
    Serial.println("\n>>> [UPDATE SEMPROT PESTISIDA] <<<");
    Serial.printf(
      "Pompa Pestisida (Relay 2) STATUS: ON %d detik | Sumber: %s\n\n",
      detik,
      sumber
    );
  }
}

void updateTimerRelay() {
  if (stopRelay1Ms != 0 && millis() >= stopRelay1Ms) {
    stopRelay1Ms = 0;
    statusRelay1 = false;
    digitalWrite(RELAY1, RELAY_OFF);
    Serial.println("\n>>> [UPDATE SEMPROT AIR] <<<");
    Serial.println("Pompa Air (Relay 1) STATUS: OFF (selesai)\n");
  }
  if (stopRelay2Ms != 0 && millis() >= stopRelay2Ms) {
    stopRelay2Ms = 0;
    statusRelay2 = false;
    digitalWrite(RELAY2, RELAY_OFF);
    Serial.println("\n>>> [UPDATE SEMPROT PESTISIDA] <<<");
    Serial.println("Pompa Pestisida (Relay 2) STATUS: OFF (selesai)\n");
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
  pollHttp.setReuse(true);
  pollHttp.setConnectTimeout(3000);
  pollHttp.setTimeout(4000);

  bool began = false;
  if (url.startsWith("https://")) {
    pollSecureClient.setInsecure();
    began = pollHttp.begin(pollSecureClient, url);
  } else {
    began = pollHttp.begin(url);
  }

  if (!began) {
    pollHttp.setReuse(false);
    pollHttp.end();
    pollSecureClient.stop();
    setStatusApi(false, -1, false);
    return;
  }
  pollHttp.addHeader("Accept", "application/json");

  int kode = pollHttp.GET();
  updateTimerRelay();

  if (kode == 200) {
    setStatusApi(true, kode);
    String res = pollHttp.getString();

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
          ? "TERDETEKSI PENYAKIT (Perlu Penanganan)"
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

    if (ambilNilai(res, "spray_water") == "true") {
      int d = ambilNilai(res, "water_duration_seconds").toInt();
      nyalakanRelay(
        1,
        d > 0 ? d : 5,
        "Web / Telegram / Chatbot"
      );
    }
    if (ambilNilai(res, "spray_pesticide") == "true") {
      int d = ambilNilai(res, "pesticide_duration_seconds").toInt();
      nyalakanRelay(
        2,
        d > 0 ? d : 5,
        "Web / Telegram / Chatbot"
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
  updateTimerRelay();

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
    updateTimerRelay();
  }

  if (millis() - waktuBaca >= 2000) {
    waktuBaca = millis();
    bacaSensor();
    updateTimerRelay();

    bool cooldownSelesai = (
      waktuSiramAuto == 0 ||
      millis() - waktuSiramAuto >= AUTO_WATER_COOLDOWN_MS
    );
    if (kelembapanTanah < autoWaterThreshold &&
        !statusRelay1 &&
        cooldownSelesai) {
      waktuSiramAuto = millis();
      nyalakanRelay(1, 5, "Otomatis (Tanah Kering)");
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

  if (millis() - waktuLCD >= 3000) {
    waktuLCD = millis();
    tampilLCD();
  }
}
