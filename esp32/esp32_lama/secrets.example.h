#pragma once

// Salin file ini menjadi `secrets.h` di folder yang sama sebelum compile di Arduino IDE.
const char* WIFI_SSID     = "NAMA_WIFI_ANDA";
const char* WIFI_PASSWORD = "PASSWORD_WIFI_ANDA";

// Ganti ke domain HTTPS Cloudflare Tunnel Anda (mis. "https://chilivision.me/api") atau IP lokal laptop.
String API_BASE_URL = "https://chilivision.me/api";
