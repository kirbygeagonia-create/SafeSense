/*
 * SafeSense IoT — ESP32-S3 WiFi Alert Gateway + Camera
 * Board : DFRobot FireBeetle 2 ESP32-S3
 *
 * Receives plain-text commands from Arduino via Serial1:
 *   "ACCIDENT" → POST critical/accident alert + capture image
 *   "FLOOD"    → POST critical/flood alert
 *   "CLEAR"    → no action (safe state)
 *
 * Arduino wiring:
 *   Arduino D13 (espSerial TX) → ESP32 GPIO44 (RX)
 *   Arduino D12 (espSerial RX) ← ESP32 GPIO43 (TX)
 *   GND ←→ GND  (common ground required)
 *
 * Board settings in Arduino IDE:
 *   Board            : DFRobot FireBeetle 2 ESP32-S3
 *   USB CDC On Boot  : Enabled
 *   PSRAM            : OPI PSRAM
 *   Partition Scheme : Huge APP (3MB No OTA/1MB SPIFFS)
 *   Flash Size       : 8MB
 *
 * Required libraries (Library Manager):
 *   ArduinoJson by Benoit Blanchon (v6.x)
 */

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "esp_camera.h"

// ══════════════════════════════════════════════════════════════
//  CAMERA PINS — DFRobot FireBeetle 2 ESP32-S3 (DFR0975)
//  OV3660 FPC connector on-board
// ══════════════════════════════════════════════════════════════

#define PWDN_GPIO_NUM   -1
#define RESET_GPIO_NUM  -1
#define XCLK_GPIO_NUM   45
#define SIOD_GPIO_NUM    1
#define SIOC_GPIO_NUM    2
#define Y9_GPIO_NUM     48
#define Y8_GPIO_NUM     46
#define Y7_GPIO_NUM     14
#define Y6_GPIO_NUM     21
#define Y5_GPIO_NUM     47
#define Y4_GPIO_NUM     20
#define Y3_GPIO_NUM     19
#define Y2_GPIO_NUM     34
#define VSYNC_GPIO_NUM  36
#define HREF_GPIO_NUM   35
#define PCLK_GPIO_NUM   0

// ══════════════════════════════════════════════════════════════
//  SERIAL1 PINS — must NOT conflict with camera pins above
//  GPIO44 = RX (from Arduino TX / D13)
//  GPIO43 = TX (to Arduino RX / D12)
// ══════════════════════════════════════════════════════════════

const int SERIAL1_RX = 44;
const int SERIAL1_TX = 43;

// ══════════════════════════════════════════════════════════════
//  CONFIGURATION
// ══════════════════════════════════════════════════════════════

const char* WIFI_SSID      = "Fracks";
const char* WIFI_PASSWORD  = "686L[w36";

const char* SERVER_URL         = "https://safesense-tksy.onrender.com";
const char* ALERT_ENDPOINT     = "/api/alert";
const char* IMAGE_ENDPOINT     = "/api/alert/image";
const char* HEARTBEAT_ENDPOINT = "/api/heartbeat";

const char* API_KEY       = "safesense-live-key-928374823901";
const char* DEVICE_ID     = "SAFESENSE-001";
const char* STATION_TYPE  = "hospital";
const char* LOCATION_NAME = "Brgy. Crossing Palkan, Tupi";
const float LATITUDE      = 8.1574;
const float LONGITUDE     = 124.9282;

const unsigned long WIFI_RECONNECT_MS = 30000;
const unsigned long HEARTBEAT_MS      = 300000;
const int           MAX_RETRIES       = 3;
const unsigned long RETRY_BASE_MS     = 5000;
const unsigned long ACCIDENT_COOLDOWN = 10000; // 10 seconds

// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

String        serialBuffer  = "";
const int     BUFFER_MAX    = 256;
unsigned long lastWiFiCheck = 0;
unsigned long lastHeartbeat = 0;
unsigned long lastDataRx    = 0;
bool          deviceOnline  = false;
int           wifiFailCount = 0;
bool          cameraReady   = false;
unsigned long lastAccidentMs = 0;

// ══════════════════════════════════════════════════════════════
//  FORWARD DECLARATIONS
// ══════════════════════════════════════════════════════════════

void connectWiFi();
bool initCamera();
void testCameraCapture();
camera_fb_t* captureImage();
bool sendCameraImage(camera_fb_t* fb, String alertLevel, String eventType);
bool sendAlert(String level, String eventType, String rainStatus,
               float waterLevel, int vibration, String message);
bool sendAlertWithRetry(String level, String eventType, String rainStatus,
                        float waterLevel, int vibration, String message);
void sendHeartbeat();
void processCommand(String cmd);

// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("========================================");
  Serial.println(" SafeSense ESP32-S3 — Booting");
  Serial.println("========================================");
  Serial.printf("[Boot] Free heap : %d bytes\n", ESP.getFreeHeap());
  Serial.printf("[Boot] PSRAM     : %s\n", psramFound() ? "YES" : "NO");
  Serial.printf("[Boot] Chip      : %s rev%d\n", ESP.getChipModel(), ESP.getChipRevision());

  // Serial1 — receives commands from Arduino
  Serial1.begin(9600, SERIAL_8N1, SERIAL1_RX, SERIAL1_TX);
  Serial.printf("[Boot] Serial1 ready — RX=GPIO%d TX=GPIO%d\n", SERIAL1_RX, SERIAL1_TX);

  // WiFi first
  connectWiFi();

  // Server wake request (cold-start prevention)
  Serial.println("[Boot] Waking server...");
  {
    WiFiClientSecure wc; wc.setInsecure();
    HTTPClient hh;
    hh.begin(wc, String(SERVER_URL) + "/api/heartbeat");
    hh.addHeader("Content-Type", "application/json");
    hh.setTimeout(60000);
    hh.POST("{\"api_key\":\"" + String(API_KEY) + "\",\"device_id\":\"" + String(DEVICE_ID) + "\",\"status\":\"boot\"}");
    hh.end();
    Serial.println("[Boot] Server wake sent");
  }

  // Camera init
  Serial.println("[Boot] Initializing camera...");
  cameraReady = initCamera();
  if (!cameraReady) {
    Serial.println("[Boot] Camera retry in 1s...");
    delay(1000);
    cameraReady = initCamera();
  }
  if (cameraReady) {
    Serial.println("[Boot] Camera READY ✓");
    testCameraCapture();
  } else {
    Serial.println("[Boot] Camera FAILED — alerts will send without image");
  }

  Serial.println("[Boot] System ready. Waiting for Arduino commands...");
}

// ══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ══════════════════════════════════════════════════════════════

void loop() {
  unsigned long now = millis();

  // WiFi watchdog
  if (WiFi.status() != WL_CONNECTED) {
    if (now - lastWiFiCheck >= WIFI_RECONNECT_MS) {
      lastWiFiCheck = now;
      connectWiFi();
    }
  }

  // Read Serial1 from Arduino
  while (Serial1.available()) {
    char c = Serial1.read();
    if (c == '\n') {
      serialBuffer.trim();
      if (serialBuffer.length() > 0) {
        Serial.printf("[Arduino] Received: \"%s\"\n", serialBuffer.c_str());
        processCommand(serialBuffer);
        lastDataRx   = now;
        deviceOnline = true;
      }
      serialBuffer = "";
    } else if (serialBuffer.length() < BUFFER_MAX) {
      serialBuffer += c;
    }
  }

  // Arduino watchdog
  if (deviceOnline && (now - lastDataRx > 30000)) {
    deviceOnline = false;
    Serial.println("[Serial] Arduino silent 30s — marking offline");
  }

  // Heartbeat
  if (now - lastHeartbeat >= HEARTBEAT_MS) {
    lastHeartbeat = now;
    sendHeartbeat();
  }
}

// ══════════════════════════════════════════════════════════════
//  COMMAND PROCESSING
// ══════════════════════════════════════════════════════════════

void processCommand(String cmd) {

  if (cmd == "ACCIDENT") {
    if (millis() - lastAccidentMs < ACCIDENT_COOLDOWN) {
      Serial.println("[CMD] ACCIDENT ignored — cooldown active");
      return;
    }
    lastAccidentMs = millis();
    Serial.println("[CMD] ACCIDENT — capturing image and sending alert");

    camera_fb_t* fb = NULL;
    if (cameraReady) {
      fb = captureImage();
      if (fb) Serial.printf("[Camera] Captured %d bytes\n", fb->len);
      else    Serial.println("[Camera] Capture failed — sending alert without image");
    }

    String msg = "CRITICAL: Accident/vibration detected. Camera capturing scene. Location: ";
    msg += String(LOCATION_NAME);
    bool ok = sendAlertWithRetry("critical", "accident", "none", 0.0, 1, msg);
    Serial.printf("[Alert] ACCIDENT POST → %s\n", ok ? "OK" : "FAILED");

    if (fb) {
      sendCameraImage(fb, "critical", "accident");
      esp_camera_fb_return(fb);
    }

  } else if (cmd == "FLOOD") {
    Serial.println("[CMD] FLOOD — sending alert");

    String msg = "CRITICAL FLOOD WARNING: Rain and rising water detected. Location: ";
    msg += String(LOCATION_NAME);
    bool ok = sendAlertWithRetry("critical", "flood", "detected", 0.0, 0, msg);
    Serial.printf("[Alert] FLOOD POST → %s\n", ok ? "OK" : "FAILED");

  } else if (cmd == "CLEAR") {
    Serial.println("[CMD] CLEAR — system safe");

  } else {
    Serial.printf("[CMD] Unknown: \"%s\"\n", cmd.c_str());
  }
}

// ══════════════════════════════════════════════════════════════
//  CAMERA INIT
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
  config.pin_sccb_sda = SIOD_GPIO_NUM;
  config.pin_sccb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn     = PWDN_GPIO_NUM;
  config.pin_reset    = RESET_GPIO_NUM;
  config.xclk_freq_hz = 10000000;   // 10 MHz — more stable for OV3660
  config.pixel_format = PIXFORMAT_JPEG;

  if (psramFound()) {
    config.frame_size   = FRAMESIZE_VGA;
    config.jpeg_quality = 10;
    config.fb_count     = 1;
    config.fb_location  = CAMERA_FB_IN_PSRAM;
  } else {
    config.frame_size   = FRAMESIZE_QVGA;
    config.jpeg_quality = 15;
    config.fb_count     = 1;
    config.fb_location  = CAMERA_FB_IN_DRAM;
  }

  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("[Camera] Init FAILED: 0x%x (%s)\n", err, esp_err_to_name(err));
    return false;
  }

  sensor_t* s = esp_camera_sensor_get();
  if (s) {
    Serial.printf("[Camera] Sensor PID: 0x%04x\n", s->id.PID);
    s->set_brightness(s, 1);
    s->set_contrast(s, 1);
    s->set_saturation(s, 0);
    s->set_whitebal(s, 1);
    s->set_awb_gain(s, 1);
    s->set_exposure_ctrl(s, 1);
    s->set_gain_ctrl(s, 1);
  }
  return true;
}

// ══════════════════════════════════════════════════════════════
//  CAMERA TEST
// ══════════════════════════════════════════════════════════════

void testCameraCapture() {
  Serial.println("[Camera] Running boot capture test...");
  camera_fb_t* fb = esp_camera_fb_get();
  if (fb) { esp_camera_fb_return(fb); fb = NULL; }
  delay(100);

  fb = esp_camera_fb_get();
  if (fb && fb->len > 0) {
    Serial.printf("[Camera] TEST PASSED — %d bytes (%dx%d)\n",
                  fb->len, fb->width, fb->height);
    esp_camera_fb_return(fb);
  } else {
    Serial.println("[Camera] TEST FAILED — no frame returned");
    if (fb) esp_camera_fb_return(fb);
  }
}

// ══════════════════════════════════════════════════════════════
//  CAMERA CAPTURE
// ══════════════════════════════════════════════════════════════

camera_fb_t* captureImage() {
  if (!cameraReady) return NULL;
  camera_fb_t* fb = esp_camera_fb_get();
  if (fb) { esp_camera_fb_return(fb); fb = NULL; }
  delay(50);

  for (int i = 0; i < 2; i++) {
    fb = esp_camera_fb_get();
    if (fb && fb->len > 0) return fb;
    if (fb) { esp_camera_fb_return(fb); fb = NULL; }
    delay(100);
  }
  return NULL;
}

// ══════════════════════════════════════════════════════════════
//  CAMERA IMAGE UPLOAD
// ══════════════════════════════════════════════════════════════

bool sendCameraImage(camera_fb_t* fb, String alertLevel, String eventType) {
  if (!fb || fb->len == 0 || WiFi.status() != WL_CONNECTED) return false;

  String url      = String(SERVER_URL) + String(IMAGE_ENDPOINT);
  String boundary = "----SafeSenseBoundary" + String(millis());

  String head = "";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"api_key\"\r\n\r\n" + String(API_KEY) + "\r\n";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"device_id\"\r\n\r\n" + String(DEVICE_ID) + "\r\n";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"alert_level\"\r\n\r\n" + alertLevel + "\r\n";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"event_type\"\r\n\r\n" + eventType + "\r\n";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"latitude\"\r\n\r\n" + String(LATITUDE, 4) + "\r\n";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"longitude\"\r\n\r\n" + String(LONGITUDE, 4) + "\r\n";
  head += "--" + boundary + "\r\nContent-Disposition: form-data; name=\"image\"; filename=\"capture.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n";
  String tail = "\r\n--" + boundary + "--\r\n";

  int total = head.length() + fb->len + tail.length();

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;
  http.begin(client, url);
  http.addHeader("Content-Type", "multipart/form-data; boundary=" + boundary);
  http.addHeader("Content-Length", String(total));
  http.setTimeout(30000);

  uint8_t* buf = psramFound()
                 ? (uint8_t*)ps_malloc(total)
                 : (uint8_t*)malloc(total);
  if (!buf) {
    Serial.printf("[Camera] Upload alloc failed — total=%d bytes, free heap=%d\n",
                  total, ESP.getFreeHeap());
    http.end();
    return false;
  }

  int off = 0;
  memcpy(buf + off, head.c_str(), head.length()); off += head.length();
  memcpy(buf + off, fb->buf, fb->len);            off += fb->len;
  memcpy(buf + off, tail.c_str(), tail.length());

  int code = http.POST(buf, total);
  bool ok  = (code == 200 || code == 201);
  Serial.printf("[Camera] Image upload HTTP %d — %s\n", code, ok ? "OK" : "FAILED");

  free(buf);
  http.end();
  return ok;
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
    Serial.print(".");
    attempts++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    Serial.printf("[WiFi] Connected! IP=%s RSSI=%d dBm\n",
                  WiFi.localIP().toString().c_str(), WiFi.RSSI());
  } else {
    wifiFailCount++;
    Serial.printf("[WiFi] FAILED (attempt %d)\n", wifiFailCount);
    if (wifiFailCount >= 10) {
      Serial.println("[WiFi] Restarting...");
      ESP.restart();
    }
  }
}

// ══════════════════════════════════════════════════════════════
//  HTTP ALERT POST
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
  doc["has_image"]     = cameraReady ? 1 : 0;

  String body;
  serializeJson(doc, body);

  String url = String(SERVER_URL) + String(ALERT_ENDPOINT);

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;
  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(45000);

  Serial.printf("[HTTP] POST %s\n", url.c_str());
  int code = http.POST(body);
  Serial.printf("[HTTP] Response: %d\n", code);

  bool ok = (code == 200 || code == 201);
  http.end();
  return ok;
}

bool sendAlertWithRetry(String level, String eventType, String rainStatus,
                        float waterLevel, int vibration, String message) {
  for (int i = 1; i <= MAX_RETRIES; i++) {
    if (sendAlert(level, eventType, rainStatus, waterLevel, vibration, message)) return true;
    if (i < MAX_RETRIES) {
      unsigned long wait = RETRY_BASE_MS * (1 << (i - 1));
      Serial.printf("[HTTP] Retry %d in %lums...\n", i + 1, wait);
      delay(wait);
      if (WiFi.status() != WL_CONNECTED) connectWiFi();
    }
  }
  return false;
}

// ══════════════════════════════════════════════════════════════
//  HEARTBEAT
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

  String body;
  serializeJson(doc, body);

  String url = String(SERVER_URL) + String(HEARTBEAT_ENDPOINT);

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;
  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(15000);

  int code = http.POST(body);
  Serial.printf("[Heartbeat] HTTP %d\n", code);
  http.end();
}
