#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Adafruit_GFX.h>
#include <Adafruit_ST7735.h>
#include <SPI.h>

// ==================== KONFIGURASI WIFI & LARAVEL ====================
#define WIFI_SSID       "SANKEN_TAMU"
#define WIFI_PASSWORD   "SankenPluit"

const char* SERVER_ORIGIN = "http://192.168.6.113:8000";

// Identifier unik ESP32 (Harus cocok dengan kolom `id_esp` di database)
const char* idEsp         = "ESP32_01"; 
// =====================================================================

// Pin LCD ST7735S
#define TFT_CS     5
#define TFT_RST    4
#define TFT_DC     2

// Pin 3 Push Button
#define BTN_UP     13
#define BTN_DOWN   12   
#define BTN_SELECT 14

Adafruit_ST7735 tft = Adafruit_ST7735(TFT_CS, TFT_DC, TFT_RST);

// State Sistem & Menu Status
int  selectedMenu = 0;              // 0 = RUNNING, 1 = MOULD, 2 = SETTER, 3 = Refresh
String namaMesinDinamis = "AC";      // Default nama mesin dari database
String statusAktif = "OFF";          // Status mesin saat ini dari database
bool serverOnline      = false;
bool terhubungBackend  = false;

// Timer durasi status — diambil dari backend (esp.timer_sec), bukan dihitung lokal,
// supaya LCD selalu sama dengan dashboard & database.
int timerBackend = 0;

// Nama yang terakhir digambar di LCD, untuk tahu kapan judul harus digambar ulang
// (nama diambil dari tabel mesin lewat backend, jadi bisa berubah kapan saja).
String namaTerakhirDigambar = "";

// Status yang terakhir digambar di menu, untuk tahu kapan kotak [AKTIF] harus
// digambar ulang — updateMenuUI() tidak dipanggil saat polling, hanya saat tombol.
String statusTerakhirDigambar = "";

// List Pilihan Status
const char* listStatus[3] = {"RUNNING", "MOULD", "SETTER"};

// Debounce Push Button
const int PIN_BTN[3] = {BTN_UP, BTN_DOWN, BTN_SELECT};
bool lastBtn[3] = {HIGH, HIGH, HIGH};
unsigned long lastBtnChange[3] = {0, 0, 0};
const unsigned long DEBOUNCE = 50;

// Timing
unsigned long lastPoll = 0;
unsigned long lastPing = 0;
unsigned long pesanSampai = 0;
const unsigned long INTERVAL_PING = 5000;

// ---------- Deklarasi Fungsi ----------
void connectWiFi();
void drawStaticUI();
void updateMenuUI();
void updateStatusBar();
void tampilPesan(const char* teks, uint16_t warna, unsigned long durasiMs);
bool ambilStatus();
int kirimPost(const char* path, const String& jsonBody, String* responseOut = nullptr);
void kirimPing();
bool setStatusMesin(const char* statusBaru);
bool parseEsp(const String& json);

void setup() {
  Serial.begin(115200);

  for (int i = 0; i < 3; i++) pinMode(PIN_BTN[i], INPUT_PULLUP);

  tft.initR(INITR_BLACKTAB);   // Gunakan INITR_REDTAB jika warna terbalik
  tft.setRotation(1);          // Landscape Mode
  
  connectWiFi();
  kirimPing();                 // Panggil API Ping
  ambilStatus();               // Ambil nama_mesin, status & timer awal dari database
  drawStaticUI();
  updateMenuUI();
}

void loop() {
  connectWiFi();

  // 1. Pembacaan Tombol Physical
  for (int i = 0; i < 3; i++) {
    bool baca = digitalRead(PIN_BTN[i]);
    if (baca != lastBtn[i] && millis() - lastBtnChange[i] > DEBOUNCE) {
      lastBtnChange[i] = millis();
      lastBtn[i] = baca;
      if (baca != LOW) continue;   // Eksekusi hanya saat ditekan (LOW)

      if (PIN_BTN[i] == BTN_UP) {
        selectedMenu = (selectedMenu + 3) % 4; // Navigasi Up 0..3
        updateMenuUI();
      } else if (PIN_BTN[i] == BTN_DOWN) {
        selectedMenu = (selectedMenu + 1) % 4; // Navigasi Down 0..3
        updateMenuUI();
      } else if (PIN_BTN[i] == BTN_SELECT) {
        if (selectedMenu == 3) {
          // ================= REFRESH DATA & LCD UI =================
          tampilPesan("Refreshing Data...", ST7735_CYAN, 0);
          
          kirimPing();
          bool statusGet = ambilStatus();

          drawStaticUI(); 
          updateMenuUI();
          
          if (statusGet) {
            tampilPesan("Refreshed OK!", ST7735_GREEN, 1500);
          } else {
            tampilPesan("Refresh Failed", ST7735_RED, 2000);
          }
        } else {
          // ================= TOGGLE STATUS (ON / OFF) =================
          const char* targetStatus = listStatus[selectedMenu];
          
          // Jika menu yang dipilih SAMA dengan status aktif saat ini -> UBAH JADI "OFF"
          if (statusAktif.equalsIgnoreCase(targetStatus)) {
            targetStatus = "OFF";
          }

          tampilPesan("Updating Status...", ST7735_CYAN, 0);
          if (setStatusMesin(targetStatus)) {
            char buf[30];
            snprintf(buf, sizeof(buf), "Status: %s", targetStatus);
            tampilPesan(buf, ST7735_GREEN, 1500);
          } else {
            tampilPesan("Update Gagal!", ST7735_RED, 2000);
          }
          updateMenuUI();
        }
      }
    }
  }

  // 2. Periodic Ping Heartbeat
  if (millis() - lastPing >= INTERVAL_PING) {
    lastPing = millis();
    kirimPing();
  }

  // 3. Polling Sync Data Mesin & Refresh Timer LCD
  if (millis() - lastPoll >= 1000) {  // Dipercepat ke 1 detik agar detik timer di UI berjalan lancar
    lastPoll = millis();
    ambilStatus();

    // Gambar ulang hanya bila ada perubahan dari database:
    //  - nama   -> judul (hanya digambar oleh drawStaticUI)
    //  - status -> baris menu tempat kotak [AKTIF]
    // karena updateMenuUI() tidak dipanggil otomatis saat polling.
    if (namaTerakhirDigambar != namaMesinDinamis) {
      drawStaticUI();   // membersihkan layar -> menu wajib digambar ulang
      updateMenuUI();
    } else if (statusTerakhirDigambar != statusAktif) {
      updateMenuUI();
    }

    updateStatusBar();
  }
}

// ==================== KOMUNIKASI API LARAVEL ====================

// Baca field "esp" dari response backend (ping / status / state).
// Dipakai bersama oleh kirimPing(), setStatusMesin() dan ambilStatus() supaya
// nama, status, dan timer selalu datang dari server — bukan dari hitungan lokal.
bool parseEsp(const String& json) {
  JsonDocument doc;
  if (deserializeJson(doc, json)) return false;

  JsonObject esp = doc["esp"].as<JsonObject>();
  if (esp.isNull()) return false;

  if (!esp["nama_mesin"].isNull()) {
    namaMesinDinamis = esp["nama_mesin"].as<String>();
  }

  // status = null artinya belum pernah di-set -> tampilkan OFF
  statusAktif = esp["status"].isNull() ? "OFF" : esp["status"].as<String>();

  if (!esp["timer_sec"].isNull()) {
    timerBackend = esp["timer_sec"].as<int>();
  }

  return true;
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

  tampilPesan("Connecting WiFi...", ST7735_YELLOW, 0);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long mulai = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - mulai < 15000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    tampilPesan("WiFi Connected!", ST7735_GREEN, 1500);
  } else {
    tampilPesan("WiFi Failed!", ST7735_RED, 2000);
  }
}

int kirimPost(const char* path, const String& jsonBody, String* responseOut) {
  if (WiFi.status() != WL_CONNECTED) return -1;

  HTTPClient http;
  http.setTimeout(3000);
  http.begin(String(SERVER_ORIGIN) + path);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-ESP-ID", idEsp);

  int code = http.POST(jsonBody);
  if (responseOut != nullptr && code > 0) {
    *responseOut = http.getString();
  }
  http.end();

  return code;
}

void kirimPing() {
  String body = "";
  int kode = kirimPost("/api/esp/ping", "{}", &body);
  terhubungBackend = (kode == 200);

  // Response ping sudah memuat nama_mesin, status & timer_sec terkini
  if (kode == 200) parseEsp(body);
}

// Mengirimkan status baru (RUNNING / MOULD / SETTER / OFF) ke backend
bool setStatusMesin(const char* statusBaru) {
  JsonDocument doc;
  doc["status"] = statusBaru;

  String body;
  serializeJson(doc, body);

  String resBody = "";
  int kode = kirimPost("/api/esp/status", body, &resBody);

  Serial.printf("[POST /api/esp/status] Code: %d | Input: %s | Response: %s\n", 
                kode, body.c_str(), resBody.c_str());

  if (kode == 200) {
    statusAktif = String(statusBaru);   // cadangan bila parsing gagal
    parseEsp(resBody);                  // status & timer resmi dari server
    return true;
  }
  return false;
}

// GET status mesin dari backend (GET /api/esp/state)
bool ambilStatus() {
  if (WiFi.status() != WL_CONNECTED) { serverOnline = false; return false; }

  HTTPClient http;
  http.setTimeout(3000);
  http.begin(String(SERVER_ORIGIN) + "/api/esp/state?id_esp=" + String(idEsp));
  http.addHeader("Accept", "application/json");

  int code = http.GET();
  bool ok = false;

  if (code == 200) {
    ok = parseEsp(http.getString());
  }

  http.end();
  serverOnline = ok;
  return ok;
}

// ==================== DISPLAY & UI LCD ====================

void drawStaticUI() {
  tft.fillScreen(ST7735_BLACK);
  tft.setTextColor(ST7735_YELLOW);
  tft.setTextSize(2);
  tft.setCursor(5, 5);
  
  String title = "Mesin " + namaMesinDinamis;
  if (title.length() > 13) title = title.substring(0, 13);
  tft.print(title);

  // Catat nama yang sudah tampil supaya loop tahu bila ada perubahan dari database
  namaTerakhirDigambar = namaMesinDinamis;

  tft.drawFastHLine(0, 23, 160, ST7735_WHITE);
  tft.drawFastHLine(0, 112, 160, ST7735_WHITE);
}

void updateMenuUI() {
  int startY = 27;
  int spacing = 20;

  for (int i = 0; i < 3; i++) {
    int y = startY + (i * spacing);
    tft.fillRect(0, y, 160, 18, ST7735_BLACK);
    tft.setTextSize(1);

    if (i == selectedMenu) {
      tft.setTextColor(ST7735_CYAN);
      tft.setCursor(2, y + 4);
      tft.print(">");
    }

    tft.setTextColor(ST7735_WHITE);
    tft.setCursor(12, y + 4);
    tft.print(listStatus[i]);

    if (statusAktif.equalsIgnoreCase(listStatus[i])) {
      tft.drawRect(100, y + 1, 55, 15, ST7735_GREEN);
      tft.setCursor(105, y + 4);
      tft.setTextColor(ST7735_GREEN);
      tft.print("[AKTIF]");
    } else {
      if (i == selectedMenu) {
        tft.drawRect(100, y + 1, 55, 15, ST7735_CYAN);
      }
    }
  }

  int yRefresh = startY + (3 * spacing);
  tft.fillRect(0, yRefresh, 160, 18, ST7735_BLACK);
  tft.setTextSize(1);

  if (selectedMenu == 3) {
    tft.setTextColor(ST7735_CYAN);
    tft.setCursor(2, yRefresh + 2);
    tft.print(">");
    tft.setTextColor(ST7735_YELLOW);
  } else {
    tft.setTextColor(ST7735_MAGENTA);
  }
  
  tft.setCursor(12, yRefresh + 2);
  tft.print("[ Refresh Data ]");

  // Catat status yang sudah tampil supaya polling tahu bila perlu menggambar ulang
  statusTerakhirDigambar = statusAktif;

  updateStatusBar();
}

void tampilPesan(const char* teks, uint16_t warna, unsigned long durasiMs) {
  tft.fillRect(0, 115, 160, 13, ST7735_BLACK);
  tft.setTextSize(1);
  tft.setTextColor(warna);
  tft.setCursor(5, 117);
  tft.print(teks);
  pesanSampai = millis() + durasiMs;
}

void updateStatusBar() {
  if (millis() < pesanSampai) return;

  tft.fillRect(0, 115, 160, 13, ST7735_BLACK);
  tft.setTextSize(1);
  tft.setCursor(5, 117);

  if (!terhubungBackend || !serverOnline) {
    tft.setTextColor(ST7735_RED);
    tft.print("Server Offline");
    return;
  }

  if (statusAktif.equalsIgnoreCase("OFF")) {
    tft.setTextColor(ST7735_WHITE);
    tft.print("ST: OFF");
    return;
  }

  // Timer dari backend (esp.timer_sec), sama dengan yang tampil di dashboard
  long detikTotal = timerBackend < 0 ? 0 : timerBackend;
  unsigned int jam   = detikTotal / 3600;
  unsigned int menit = (detikTotal % 3600) / 60;
  unsigned int detik = detikTotal % 60;

  char timerBuf[20];
  snprintf(timerBuf, sizeof(timerBuf), "%02u:%02u:%02u", jam, menit, detik);


  tft.setTextColor(ST7735_GREEN);
 
  String stShort = statusAktif.substring(0, 3); 
  tft.printf("%s %s", stShort.c_str(), timerBuf);
}