/*
 * ============================================================
 *  SafeSense IoT — ESP32-S3 AI CAM WiFi Alert Gateway + Camera
 *  Board  : DFRobot ESP32-S3 Camera V1.1 (OV3660 sensor)
 *
 *  FIXES IN THIS VERSION:
 *    FIX-E1  Removed #include <Wire.h> AND the Wire.begin() +
 *            I2C scanner block from initCamera(). Wire was
 *            grabbing the camera's SDA/SCL pins (GPIO4/5) before
 *            esp_camera_init(), corrupting the I2C bus. This is
 *            why the camera always failed with 0x106 even with
 *            Core 3.x and the correct sensor model.
 *
 *    FIX-E2  sendAlert() now uses WiFiClientSecure + setInsecure()
 *            instead of plain WiFiClient. The server URL is https://
 *            — plain WiFiClient cannot do TLS and was returning 0
 *            silently on every POST. This is why IoT/WiFi appeared
 *            completely broken.
 *
 *    FIX-E3  sendCameraImage() same fix as FIX-E2.
 *
 *    FIX-E4  sendHeartbeat() same fix as FIX-E2.
 *
 *    FIX-E5  sendAlert() timeout raised from 10 s to 45 s.
 *            Render's free tier sleeps after 15 min of inactivity.
 *            First request after wake-up takes 30–45 s to respond.
 *            10 s always timed out on cold start.
 *
 *  ── HARDWARE CONNECTIONS ──
 *
 *  Serial from Arduino Uno (UART1 on GPIO43/44):
 *    ESP32-S3 GPIO44 (RX) ← Arduino TX (D1)
 *    ESP32-S3 GPIO43 (TX) → Arduino RX (D0)
 *    GND ─────────────────── GND (common ground required)
 *
 *  Board Setup in Arduino IDE:
 *    Board            : ESP32S3 Dev Module
 *    USB CDC On Boot  : Enabled
 *    USB Mode         : Hardware CDC and JTAG
 *    PSRAM            : OPI PSRAM
 *    Partition Scheme : Huge APP (3MB No OTA/1MB SPIFFS)
 *    Flash Mode       : QIO 80 MHz
 *    Flash Size       : 8MB
 *
 *  Required Libraries (install in Library Manager):
 *    - ArduinoJson by Benoit Blanchon (v6.x)
 *    WiFi.h / WiFiClientSecure.h / HTTPClient.h / esp_camera.h
 *    are all built into ESP32 Arduino Core 3.x — no separate install.
 *
 *  ESP32 Arduino Core requirement:
 *    Must be 3.0.0 or higher for OV3660 support.
 *    Tools → Board → Boards Manager → esp32 by Espressif Systems
 *
 * ============================================================
 */

// FIX-E1: Wire.h REMOVED — it conflicted with esp_camera's I2C driver
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "esp_camera.h"

// DFRobot FireBeetle 2 ESP32-S3 V1.0 has an AXP313A power management chip
// that controls camera power. Without calling axp.enableCameraPower() the
// camera sensor gets no voltage and reports "unsupported" or 0x105.
// V1.1+ boards do NOT need this — the #ifdef makes it safe for both.
#ifdef ARDUINO_DFRobot_FireBeetle2_ESP32S3
  #include "DFRobot_AXP313A.h"
  DFRobot_AXP313A axp;
  #define NEEDS_AXP_POWER true
#else
  #define NEEDS_AXP_POWER false
#endif


// ══════════════════════════════════════════════════════════════
//  CAMERA PIN DEFINITIONS — DFRobot FireBeetle 2 ESP32-S3 (DFR0975)
//  Source: espressif/arduino-esp32 camera_pins.h (official)
//  CAMERA_MODEL_DFRobot_FireBeetle2_ESP32S3
// ══════════════════════════════════════════════════════════════

#define PWDN_GPIO_NUM   -1
#define RESET_GPIO_NUM  -1
#define XCLK_GPIO_NUM   45
#define SIOD_GPIO_NUM    1
#define SIOC_GPIO_NUM    2
#define Y9_GPIO_NUM     48
#define Y8_GPIO_NUM     46
#define Y7_GPIO_NUM      8
#define Y6_GPIO_NUM      7
#define Y5_GPIO_NUM      4
#define Y4_GPIO_NUM     41
#define Y3_GPIO_NUM     40
#define Y2_GPIO_NUM     39
#define VSYNC_GPIO_NUM   6
#define HREF_GPIO_NUM   42
#define PCLK_GPIO_NUM    5


// ══════════════════════════════════════════════════════════════
//  CONFIGURATION — Edit these for your deployment
// ══════════════════════════════════════════════════════════════

// ── WiFi ─────────────────────────────────────────────────────
const char* WIFI_SSID     = "Fracks";
const char* WIFI_PASSWORD = "686L[w36";

// ── Server ───────────────────────────────────────────────────
const char* SERVER_URL = "https://safesense-tksy.onrender.com";

// ── API Endpoints ────────────────────────────────────────────
const char* ALERT_ENDPOINT     = "/api/alert";
const char* IMAGE_ENDPOINT     = "/api/alert/image";
const char* HEARTBEAT_ENDPOINT = "/api/heartbeat";

// ── API Key (must match SAFESENSE_API_KEY in Render env vars) ─
const char* API_KEY = "safesense-live-key-928374823901";

// ── Device ───────────────────────────────────────────────────
const char* DEVICE_ID    = "SAFESENSE-001";
const char* STATION_TYPE = "hospital";

// ── Location ─────────────────────────────────────────────────
const float LATITUDE      = 8.1574;
const float LONGITUDE     = 124.9282;
const char* LOCATION_NAME = "Brgy. Crossing Palkan, Tupi";

// ── Camera ───────────────────────────────────────────────────
// Camera is re-enabled now that the Wire conflict is fixed.
// If it still fails after the fix, set to false temporarily.
const bool CAMERA_ENABLED  = true;
const bool USE_FLASH       = true;
const int  CAPTURE_RETRIES = 2;

// ── Timing ───────────────────────────────────────────────────
const unsigned long WIFI_RECONNECT_INTERVAL = 30000;
const unsigned long HEARTBEAT_INTERVAL      = 300000;
const int           MAX_RETRIES             = 3;
const unsigned long RETRY_DELAY_BASE        = 2000;


// ══════════════════════════════════════════════════════════════
//  PIN DEFINITIONS
// ══════════════════════════════════════════════════════════════

const int PIN_LED_STATUS = 2;    // Onboard LED — active HIGH
const int PIN_LED_FLASH  = 48;   // Flash LED — active HIGH


// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

String serialBuffer = "";
const int SERIAL_BUFFER_MAX = 512;

unsigned long lastWiFiAttempt   = 0;
unsigned long lastHeartbeatSent = 0;
unsigned long lastDataReceived  = 0;

int  lastRain       = 0;
int  lastWaterLevel = 0;
int  lastVibCount   = 0;
int  lastAlertLevel = 0;
bool deviceOnline   = false;

int  wifiFailCount = 0;
bool cameraReady   = false;


// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  // USB-CDC Serial — for Serial Monitor
  // The while(!Serial) wait ensures boot messages always appear
  // when you open Serial Monitor and press RST.
  Serial.begin(115200);
  unsigned long cdcWait = millis();
  while (!Serial && (millis() - cdcWait < 3000)) {}  // Wait up to 3 s for CDC

  Serial.println("========================================");
  Serial.println(" SafeSense ESP32-S3 -- Booting...");
  Serial.println("========================================");
  Serial.printf("[Boot] Free heap  : %d bytes\n", ESP.getFreeHeap());
  Serial.printf("[Boot] PSRAM found: %s\n", psramFound() ? "YES" : "NO");
  Serial.printf("[Boot] Chip model : %s rev%d\n",
                ESP.getChipModel(), ESP.getChipRevision());

  // UART1 — receives $SAFE packets from Arduino Uno
  // GPIO44 = RX (from Arduino TX), GPIO43 = TX (to Arduino RX)
  // NOTE: GPIO17/18 are now used by camera I2C (SIOD/SIOC) — Serial1 stays on 43/44
  // which are dedicated UART pins on the ESP32-S3 and do NOT conflict with camera.
  Serial1.begin(9600, SERIAL_8N1, 44, 43);
  Serial.println("[Boot] Serial1 (Arduino bridge) ready on GPIO44(RX)/GPIO43(TX).");

  pinMode(PIN_LED_STATUS, OUTPUT);
  pinMode(PIN_LED_FLASH,  OUTPUT);
  digitalWrite(PIN_LED_STATUS, LOW);
  digitalWrite(PIN_LED_FLASH,  LOW);

  // Boot blink — 2 quick flashes
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_LED_STATUS, HIGH); delay(200);
    digitalWrite(PIN_LED_STATUS, LOW);  delay(200);
  }

  // Connect WiFi FIRST — camera LEDC/XCLK init is more stable after WiFi stack is up
  Serial.printf("[Boot] Connecting to WiFi SSID: %s\n", WIFI_SSID);
  connectWiFi();

  // Camera init — after WiFi to avoid LEDC peripheral conflicts
  if (CAMERA_ENABLED) {
    Serial.println("[Boot] Initializing camera...");
    cameraReady = initCameraWithRetry();
    Serial.println(cameraReady ? "[Boot] Camera READY." : "[Boot] Camera FAILED — continuing without it.");
  } else {
    Serial.println("[Boot] Camera disabled in config.");
  }
}


// ══════════════════════════════════════════════════════════════
//  CAMERA RETRY WRAPPER
// ══════════════════════════════════════════════════════════════

bool initCameraWithRetry() {
  for (int attempt = 1; attempt <= 3; attempt++) {
    Serial.printf("[Camera] Init attempt %d/3...\n", attempt);
    if (initCamera()) return true;

    // De-init cleanly before retrying — leaves the I2C bus in a known state
    esp_camera_deinit();
    delay(500 * attempt);  // Back off: 500 ms, 1000 ms, 1500 ms
  }
  return false;
}


// ══════════════════════════════════════════════════════════════
//  CAMERA INITIALIZATION  (FIX-E1 applied here)
// ══════════════════════════════════════════════════════════════

bool initCamera() {
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer   = LEDC_TIMER_0;
  config.pin_d0       = Y2_GPIO_NUM;
  config.pin_d1       = Y3_GPIO_NUM;
  config.pin_d2       = Y4_GPIO_NUM;
  config.pin_d3       = Y5_GPIO_NUM;
  config.pin_d4       = Y6_GPIO_NUM;
  config.pin_d5       = Y7_GPIO_NUM;
  config.pin_d6       = Y8_GPIO_NUM;
  config.pin_d7       = Y9_GPIO_NUM;
  config.pin_xclk     = XCLK_GPIO_NUM;
  config.pin_pclk     = PCLK_GPIO_NUM;
  config.pin_vsync    = VSYNC_GPIO_NUM;
  config.pin_href     = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn     = PWDN_GPIO_NUM;
  config.pin_reset    = RESET_GPIO_NUM;

  // OV3660 initializes more reliably at 16 MHz (not 20 MHz)
  config.xclk_freq_hz = 16000000;
  config.pixel_format = PIXFORMAT_JPEG;

  if (psramFound()) {
    config.frame_size   = FRAMESIZE_VGA;   // 640x480 — safe starting point for OV3660
    config.jpeg_quality = 10;
    config.fb_count     = 1;               // 1 is more reliable on first boot; increase to 2 only if needed
    config.fb_location  = CAMERA_FB_IN_PSRAM;
  } else {
    config.frame_size   = FRAMESIZE_QVGA;  // 320x240 if no PSRAM
    config.jpeg_quality = 15;
    config.fb_count     = 1;
    config.fb_location  = CAMERA_FB_IN_DRAM;
  }

  // FIX-E1: The Wire.begin() + I2C scanner block that was here before
  // is now REMOVED. It was calling Wire.begin(GPIO4, GPIO5) — the same
  // pins the camera uses for I2C — before esp_camera_init(), which
  // corrupted the bus and caused every init to fail with 0x106.
  // esp_camera_init() manages I2C internally; do not touch Wire before it.

  Serial.println("[Camera] Calling esp_camera_init()...");

  // DFRobot FireBeetle 2 V1.0: enable camera power via AXP313A PMIC
  // V1.1+ skips this block automatically
#if NEEDS_AXP_POWER
  int axpRetry = 0;
  while (axp.begin() != 0 && axpRetry < 5) {
    Serial.println("[Camera] AXP313A init error — retrying...");
    delay(500);
    axpRetry++;
  }
  if (axpRetry < 5) {
    axp.enableCameraPower(axp.eOV2640);
    Serial.println("[Camera] AXP313A camera power enabled.");
    delay(100);
  } else {
    Serial.println("[Camera] AXP313A failed — camera may not have power.");
  }
#endif

  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("[Camera] Init FAILED — error 0x%x (%s)\n",
                  err, esp_err_to_name(err));
    Serial.println("[Camera] Check: Core 3.x installed? PSRAM enabled? Pin definitions correct?");
    return false;
  }
  Serial.println("[Camera] Init OK.");

  // Apply settings optimized for outdoor/rainy conditions
  sensor_t* s = esp_camera_sensor_get();
  if (s) {
    Serial.printf("[Camera] Sensor PID: 0x%x\n", s->id.PID);
    // OV3660 PID should be 0x3660
    s->set_brightness(s, 1);
    s->set_contrast(s, 1);
    s->set_saturation(s, 0);
    s->set_whitebal(s, 1);
    s->set_awb_gain(s, 1);
    s->set_wb_mode(s, 0);
    s->set_exposure_ctrl(s, 1);
    s->set_aec2(s, 1);
    s->set_gain_ctrl(s, 1);
    s->set_agc_gain(s, 0);
    s->set_gainceiling(s, (gainceiling_t)6);
    s->set_bpc(s, 1);
    s->set_wpc(s, 1);
    s->set_raw_gma(s, 1);
    s->set_lenc(s, 1);
  }
  return true;
}


// ══════════════════════════════════════════════════════════════
//  CAMERA CAPTURE
// ══════════════════════════════════════════════════════════════

camera_fb_t* captureImage() {
  if (!cameraReady) return NULL;

  if (USE_FLASH) {
    digitalWrite(PIN_LED_FLASH, HIGH);
    delay(100);
  }

  // Discard first frame (often artifact-filled on sensor wake)
  camera_fb_t* fb = esp_camera_fb_get();
  if (fb) { esp_camera_fb_return(fb); fb = NULL; }
  delay(50);

  for (int i = 0; i < CAPTURE_RETRIES; i++) {
    fb = esp_camera_fb_get();
    if (fb && fb->len > 0) break;
    if (fb) { esp_camera_fb_return(fb); fb = NULL; }
    delay(100);
  }

  if (USE_FLASH) digitalWrite(PIN_LED_FLASH, LOW);
  return fb;
}


// ══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ══════════════════════════════════════════════════════════════

void loop() {
  unsigned long now = millis();

  // WiFi watchdog
  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(PIN_LED_STATUS, (millis() / 200) % 2 == 0 ? HIGH : LOW);
    if (timeSince(now, lastWiFiAttempt) >= WIFI_RECONNECT_INTERVAL) {
      connectWiFi();
      lastWiFiAttempt = now;
    }
  } else {
    digitalWrite(PIN_LED_STATUS, HIGH);
  }

  // Read Serial1 data from Arduino Uno
  while (Serial1.available()) {
    char c = Serial1.read();
    if (c == '\n') {
      serialBuffer.trim();
      if (serialBuffer.length() > 0) {
        Serial.printf("[Arduino] Received: %s\n", serialBuffer.c_str());
        processSerialData(serialBuffer);
        lastDataReceived = now;
        deviceOnline = true;
      }
      serialBuffer = "";
    } else if (serialBuffer.length() < SERIAL_BUFFER_MAX) {
      serialBuffer += c;
    }
  }

  // Heartbeat
  if (timeSince(now, lastHeartbeatSent) >= HEARTBEAT_INTERVAL) {
    lastHeartbeatSent = now;
    sendHeartbeat();
  }

  // Arduino watchdog
  if (deviceOnline && timeSince(now, lastDataReceived) > 30000) {
    deviceOnline = false;
    Serial.println("[Serial] Arduino silent for 30 s — marking offline.");
  }

  delay(10);
}


// ══════════════════════════════════════════════════════════════
//  SERIAL DATA PROCESSING
// ══════════════════════════════════════════════════════════════

void processSerialData(String line) {
  if (!line.startsWith("$SAFE,")) return;
  line = line.substring(6);

  if      (line.startsWith("DATA,"))      processDataPacket(line.substring(5));
  else if (line.startsWith("ALERT,"))     processAlertPacket(line.substring(6));
  else if (line.startsWith("BOOT"))       sendHeartbeat();
  // HEARTBEAT: just confirms Arduino is alive (lastDataReceived already updated)
}

void processDataPacket(String data) {
  int i1 = data.indexOf(',');
  int i2 = data.indexOf(',', i1 + 1);
  int i3 = data.indexOf(',', i2 + 1);
  if (i1 < 0 || i2 < 0 || i3 < 0) return;
  lastRain       = data.substring(0, i1).toInt();
  lastWaterLevel = data.substring(i1 + 1, i2).toInt();
  lastVibCount   = data.substring(i2 + 1, i3).toInt();
  lastAlertLevel = data.substring(i3 + 1).toInt();
}

void processAlertPacket(String data) {
  int pipeIdx = data.indexOf('|');
  if (pipeIdx < 0) return;

  String params  = data.substring(0, pipeIdx);
  String message = data.substring(pipeIdx + 1);

  int i1 = params.indexOf(',');
  int i2 = params.indexOf(',', i1 + 1);
  int i3 = params.indexOf(',', i2 + 1);
  if (i1 < 0 || i2 < 0 || i3 < 0) return;

  String level     = params.substring(0, i1);
  String eventType = params.substring(i1 + 1, i2);
  float  waterPct  = params.substring(i2 + 1, i3).toFloat();
  int    vibration = params.substring(i3 + 1).toInt();
  String rainStatus = lastRain ? "detected" : "none";

  Serial.printf("[Alert] Level=%s  Event=%s  Water=%.1f%%  Vib=%d\n",
                level.c_str(), eventType.c_str(), waterPct, vibration);

  // ── Camera capture for accident events ───────────────────
  // Accident (vibration trigger) always captures — this is the
  // primary verification tool to confirm if living beings are involved.
  // Flood-only alerts also capture so the dashboard has visual context.
  camera_fb_t* fb = NULL;
  if (CAMERA_ENABLED && cameraReady) {
    Serial.println("[Camera] Capturing scene for alert verification...");
    fb = captureImage();
    if (fb && fb->len > 0) {
      Serial.printf("[Camera] Captured %d bytes.\n", fb->len);
    } else {
      Serial.println("[Camera] Capture failed — sending alert without image.");
      if (fb) { esp_camera_fb_return(fb); fb = NULL; }
    }
  }

  // POST alert to IoT server
  bool ok = sendAlertWithRetry(level, eventType, rainStatus,
                               waterPct, vibration, message);
  Serial.printf("[Alert] POST result: %s\n", ok ? "OK" : "FAILED");

  // Upload image separately if captured
  if (fb) {
    sendCameraImage(fb, level, eventType);
    esp_camera_fb_return(fb);
  }

  if (!ok) {
    for (int i = 0; i < 5; i++) {
      digitalWrite(PIN_LED_STATUS, HIGH); delay(100);
      digitalWrite(PIN_LED_STATUS, LOW);  delay(100);
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  WIFI
// ══════════════════════════════════════════════════════════════

void connectWiFi() {
  Serial.printf("[WiFi] Connecting to %s ...\n", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    digitalWrite(PIN_LED_STATUS, attempts % 2 == 0 ? HIGH : LOW);
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    digitalWrite(PIN_LED_STATUS, HIGH);
    Serial.printf("[WiFi] Connected!  IP: %s  RSSI: %d dBm\n",
                  WiFi.localIP().toString().c_str(), WiFi.RSSI());
    Serial.printf("[WiFi] Target server: %s\n", SERVER_URL);
  } else {
    wifiFailCount++;
    digitalWrite(PIN_LED_STATUS, LOW);
    Serial.printf("[WiFi] FAILED (attempt %d/10).\n", wifiFailCount);
    if (wifiFailCount >= 10) {
      Serial.println("[WiFi] Too many failures — restarting ESP32.");
      ESP.restart();
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  HTTP ALERT  (FIX-E2 + FIX-E5)
// ══════════════════════════════════════════════════════════════

bool sendAlert(String level, String eventType, String rainStatus,
               float waterLevel, int vibration, String message) {
  if (WiFi.status() != WL_CONNECTED) return false;

  DynamicJsonDocument doc(1024);
  doc["api_key"]       = API_KEY;
  doc["device_id"]     = DEVICE_ID;
  doc["station_type"]  = STATION_TYPE;
  doc["alert_level"]   = level;
  doc["event_type"]    = eventType;
  doc["rain_status"]   = rainStatus;
  doc["water_level"]   = waterLevel;
  doc["vibration"]     = vibration;
  doc["message"]       = message;
  doc["latitude"]      = LATITUDE;
  doc["longitude"]     = LONGITUDE;
  doc["location_name"] = LOCATION_NAME;
  doc["has_image"]     = (CAMERA_ENABLED && cameraReady) ? 1 : 0;

  String jsonBody;
  serializeJson(doc, jsonBody);

  String url = String(SERVER_URL) + String(ALERT_ENDPOINT);

  // FIX-E2: WiFiClientSecure (was WiFiClient — cannot handle https://)
  WiFiClientSecure wifiClient;
  wifiClient.setInsecure();  // Accept any certificate (fine for capstone/school project)
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  // FIX-E5: 45 s timeout (was 10 s — Render cold start takes 30–45 s)
  http.setTimeout(45000);

  Serial.printf("[HTTP] POST %s ...\n", url.c_str());
  int httpCode = http.POST(jsonBody);
  Serial.printf("[HTTP] Response code: %d\n", httpCode);

  bool success = (httpCode == 200 || httpCode == 201);
  http.end();
  return success;
}

bool sendAlertWithRetry(String level, String eventType, String rainStatus,
                        float waterLevel, int vibration, String message) {
  for (int attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    if (sendAlert(level, eventType, rainStatus, waterLevel, vibration, message)) return true;
    if (attempt < MAX_RETRIES) {
      unsigned long delayMs = RETRY_DELAY_BASE * (1 << (attempt - 1));
      Serial.printf("[HTTP] Retry %d in %lu ms...\n", attempt + 1, delayMs);
      delay(delayMs);
      if (WiFi.status() != WL_CONNECTED) connectWiFi();
    }
  }
  return false;
}


// ══════════════════════════════════════════════════════════════
//  CAMERA IMAGE UPLOAD  (FIX-E3)
// ══════════════════════════════════════════════════════════════

bool sendCameraImage(camera_fb_t* fb, String alertLevel, String eventType) {
  if (WiFi.status() != WL_CONNECTED || !fb || fb->len == 0) return false;

  String url      = String(SERVER_URL) + String(IMAGE_ENDPOINT);
  String boundary = "----SafeSenseBoundary" + String(millis());

  String bodyStart = "";
  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"api_key\"\r\n\r\n";
  bodyStart += String(API_KEY) + "\r\n";

  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"device_id\"\r\n\r\n";
  bodyStart += String(DEVICE_ID) + "\r\n";

  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"alert_level\"\r\n\r\n";
  bodyStart += alertLevel + "\r\n";

  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"event_type\"\r\n\r\n";
  bodyStart += eventType + "\r\n";

  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"latitude\"\r\n\r\n";
  bodyStart += String(LATITUDE, 4) + "\r\n";

  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"longitude\"\r\n\r\n";
  bodyStart += String(LONGITUDE, 4) + "\r\n";

  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"image\"; filename=\"safesense_capture.jpg\"\r\n";
  bodyStart += "Content-Type: image/jpeg\r\n\r\n";

  String bodyEnd = "\r\n--" + boundary + "--\r\n";

  int totalLength = bodyStart.length() + fb->len + bodyEnd.length();

  // FIX-E3: WiFiClientSecure for HTTPS (was WiFiClient)
  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;
  http.begin(client, url);
  http.addHeader("Content-Type", "multipart/form-data; boundary=" + boundary);
  http.addHeader("Content-Length", String(totalLength));
  http.setTimeout(30000);

  uint8_t* fullBody = (uint8_t*)malloc(totalLength);
  if (!fullBody) { http.end(); return false; }

  int offset = 0;
  memcpy(fullBody + offset, bodyStart.c_str(), bodyStart.length()); offset += bodyStart.length();
  memcpy(fullBody + offset, fb->buf,           fb->len);             offset += fb->len;
  memcpy(fullBody + offset, bodyEnd.c_str(),   bodyEnd.length());

  int httpCode = http.POST(fullBody, totalLength);
  bool success = (httpCode == 200 || httpCode == 201);
  Serial.printf("[Camera] Image upload: HTTP %d — %s\n", httpCode, success ? "OK" : "FAILED");

  free(fullBody);
  http.end();
  return success;
}


// ══════════════════════════════════════════════════════════════
//  HEARTBEAT  (FIX-E4)
// ══════════════════════════════════════════════════════════════

void sendHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;

  DynamicJsonDocument doc(512);
  doc["api_key"]      = API_KEY;
  doc["device_id"]    = DEVICE_ID;
  doc["station_type"] = STATION_TYPE;
  doc["status"]       = deviceOnline ? "online" : "arduino_disconnected";
  doc["wifi_rssi"]    = WiFi.RSSI();
  doc["uptime_ms"]    = millis();
  doc["free_heap"]    = ESP.getFreeHeap();
  doc["camera_ready"] = cameraReady;
  doc["psram"]        = psramFound();

  String jsonBody;
  serializeJson(doc, jsonBody);

  String url = String(SERVER_URL) + String(HEARTBEAT_ENDPOINT);

  // FIX-E4: WiFiClientSecure for HTTPS (was WiFiClient)
  WiFiClientSecure wifiClient;
  wifiClient.setInsecure();
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(15000);

  int code = http.POST(jsonBody);
  Serial.printf("[Heartbeat] HTTP %d\n", code);
  http.end();
}


// ══════════════════════════════════════════════════════════════
//  UTILITY
// ══════════════════════════════════════════════════════════════

unsigned long timeSince(unsigned long now, unsigned long start) {
  return now - start;
}
