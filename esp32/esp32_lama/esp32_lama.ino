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

