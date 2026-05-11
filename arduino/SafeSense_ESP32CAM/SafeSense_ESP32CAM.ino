/*
 * ============================================================
 *  SafeSense IoT — ESP32-S3 AI CAM WiFi Alert Gateway + Camera
 *  Board  : ESP32-S3 AI CAM (NOT AI-Thinker ESP32-CAM)
 *
 *  This sketch runs on the ESP32-S3 AI CAM module in the
 *  dual-MCU SafeSense architecture:
 *
 *    Arduino Uno  ──Serial──►  ESP32-S3 AI CAM
 *    (sensors,                  (WiFi HTTP,
 *     LEDs,                      JSON POST,
 *     GSM SMS)                   camera capture,
 *                                heartbeat)
 *
 *  The ESP32-S3 receives sensor data and alert commands
 *  from the Arduino Uno over Serial (UART1 on GPIO43/44),
 *  connects to WiFi, POSTs JSON payloads to the SafeSense
 *  dashboard, AND captures camera images on alerts.
 *
 *  ── HARDWARE CONNECTIONS ──
 *
 *  Serial from Arduino Uno (UART1):
 *    ESP32-S3 GPIO44 (RX) ← Arduino TX (D1)
 *    ESP32-S3 GPIO43 (TX) → Arduino RX (D0)
 *    GND ────────────────── GND (common ground required)
 *    3.3V ───────────────── 3V3 (logic level reference)
 *
 *  Power:
 *    ESP32-S3 VCC → 3.3V or 5V depending on board variant
 *    ESP32-S3 GND → Common GND
 *
 *  Camera:
 *    Camera module is built into the ESP32-S3 AI CAM board.
 *    No additional wiring needed — camera is onboard.
 *
 *  Status LED:
 *    GPIO2 = onboard LED (active HIGH on most ESP32-S3 AI CAM boards)
 *
 *  ── IMPORTANT NOTES ──
 *
 *  • ESP32-S3 uses UART1 (Serial1) on GPIO43/GPIO44 for
 *    communication with Arduino. GPIO43 = TX, GPIO44 = RX.
 *    This is different from the old AI-Thinker which used
 *    GPIO1 (TX) and GPIO3 (RX) on UART0.
 *
 *  • The camera pin definitions below are for the ESP32-S3
 *    AI CAM board. Do NOT use AI-Thinker pin values here.
 *
 *  • The camera uses significant RAM. DynamicJsonDocument
 *    sizes are kept conservative.
 *
 *  Required Libraries:
 *    - ArduinoJson by Benoit Blanchon (v6.x or v7.x)
 *    - WiFi.h       (built into ESP32-S3 Arduino Core)
 *    - HTTPClient.h (built into ESP32-S3 Arduino Core)
 *    - esp_camera.h (built into ESP32-S3 Arduino Core)
 *
 *  Board Setup in Arduino IDE:
 *    1. Add ESP32 board URL: https://dl.espressif.com/dl/package_esp32_index.json
 *    2. Install "esp32 by Espressif Systems" v2.0.9+ from Board Manager
 *    3. Select Board: "ESP32S3 Dev Module"
 *    4. Partition Scheme: "Huge APP (3MB No OTA/1MB SPIFFS)"
 *    5. Upload Speed: 115200
 *    6. USB CDC On Boot: "Enabled" (allows Serial monitor over USB)
 *    7. Flash Size: 4MB or 8MB (match your board)
 *
 * ============================================================
 */
// Add alongside the other #include lines at the top:
#include <Wire.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "esp_camera.h"

// ══════════════════════════════════════════════════════════════
//  ESP32-S3 AI CAM — CAMERA PIN DEFINITIONS
//  (Do NOT change these — they are fixed by the board design)
// ══════════════════════════════════════════════════════════════

#define PWDN_GPIO_NUM     -1
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM     15
#define SIOD_GPIO_NUM      4
#define SIOC_GPIO_NUM      5
#define Y9_GPIO_NUM       16
#define Y8_GPIO_NUM       17
#define Y7_GPIO_NUM       18
#define Y6_GPIO_NUM       12
#define Y5_GPIO_NUM       10
#define Y4_GPIO_NUM        8
#define Y3_GPIO_NUM        9
#define Y2_GPIO_NUM       11
#define VSYNC_GPIO_NUM     6
#define HREF_GPIO_NUM      7
#define PCLK_GPIO_NUM     13


// ══════════════════════════════════════════════════════════════
//  CONFIGURATION — Edit these values for your deployment
// ══════════════════════════════════════════════════════════════

// ── WiFi Credentials ─────────────────────────────────────────
const char* WIFI_SSID     = "Fracks";
const char* WIFI_PASSWORD = "686L[w36";

// ── Server Configuration ─────────────────────────────────────
// Your Hospital Management System server address.
// Use your PC's local LAN IP (find with 'ipconfig' on Windows).
// Include the port if not 80 (e.g., "http://192.168.1.100:8080")
const char* SERVER_URL = "https://safesense-tksy.onrender.com";


// ── API Endpoints ────────────────────────────────────────────
const char* ALERT_ENDPOINT     = "/api/alert";
const char* IMAGE_ENDPOINT     = "/api/alert/image";   // Camera image upload
const char* HEARTBEAT_ENDPOINT = "/api/heartbeat";

// ── API Key ──────────────────────────────────────────────────
// Must match SAFESENSE_API_KEY in the server's .env file
const char* API_KEY = "safesense-live-key-928374823901";

// ── Device Identity ──────────────────────────────────────────
const char* DEVICE_ID     = "SAFESENSE-001";
const char* STATION_TYPE  = "hospital";   // hospital | police | fire

// ── Location ─────────────────────────────────────────────────
const float  LATITUDE      = 8.1574;
const float  LONGITUDE     = 124.9282;
const char*  LOCATION_NAME = "Brgy. Casisang, Malaybalay City";

// ── Camera Settings ──────────────────────────────────────────
const bool   CAMERA_ENABLED     = true;    // Set false to disable camera
const bool   USE_FLASH          = true;    // Flash LED during capture (helps in dark/rain)
const int    CAPTURE_RETRIES    = 2;       // Retry capture if first frame is bad

// ── Timing ───────────────────────────────────────────────────
const unsigned long WIFI_RECONNECT_INTERVAL = 30000;
const unsigned long HEARTBEAT_INTERVAL      = 300000;
const int           MAX_RETRIES             = 3;
const unsigned long RETRY_DELAY_BASE        = 2000;


// ══════════════════════════════════════════════════════════════
//  PIN DEFINITIONS
// ══════════════════════════════════════════════════════════════

const int PIN_LED_STATUS = 2;    // Onboard LED on ESP32-S3 AI CAM (active HIGH)
const int PIN_LED_FLASH  = 48;   // Flash LED on ESP32-S3 AI CAM (active HIGH)
// Note: GPIO2 and GPIO48 are common onboard LED pins for ESP32-S3 AI CAM boards.
// If your specific board uses different pins, adjust these two lines only.


// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

// Serial receive buffer
String serialBuffer = "";
const int SERIAL_BUFFER_MAX = 512;

// Timing
unsigned long lastWiFiAttempt   = 0;
unsigned long lastHeartbeatSent = 0;
unsigned long lastDataReceived  = 0;

// Latest sensor state (received from Arduino)
int  lastRain       = 0;
int  lastWaterLevel = 0;
int  lastVibCount   = 0;
int  lastAlertLevel = 0;
bool deviceOnline   = false;

// WiFi
int wifiFailCount = 0;

// Camera
bool cameraReady = false;


// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  // USB CDC debug Serial (UART0 via USB — for Serial Monitor)
  Serial.begin(115200);
  delay(500);  // Brief delay so Serial Monitor can connect before first prints
  Serial.println("========================================");
  Serial.println(" SafeSense ESP32-S3 — Booting...");
  Serial.println("========================================");
  Serial.printf("[Boot] Free heap  : %d bytes\n", ESP.getFreeHeap());
  Serial.printf("[Boot] PSRAM found: %s\n", psramFound() ? "YES" : "NO");
  Serial.printf("[Boot] Chip model : %s rev%d\n",
                ESP.getChipModel(), ESP.getChipRevision());

  // Hardware Serial1 for communication with Arduino Uno (UART1)
  // GPIO43 = TX (connects to Arduino D0/RX)
  // GPIO44 = RX (connects to Arduino D1/TX)
  Serial1.begin(9600, SERIAL_8N1, 44, 43);
  Serial.println("[Boot] Serial1 (Arduino bridge) ready on GPIO43/44.");

  // LED setup — ESP32-S3 AI CAM onboard LED is active HIGH
  pinMode(PIN_LED_STATUS, OUTPUT);
  pinMode(PIN_LED_FLASH,  OUTPUT);
  digitalWrite(PIN_LED_STATUS, LOW);   // OFF (active high — LOW = off)
  digitalWrite(PIN_LED_FLASH,  LOW);   // OFF

  // Boot indication — flash LED twice
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_LED_STATUS, HIGH);  // ON
    delay(200);
    digitalWrite(PIN_LED_STATUS, LOW);   // OFF
    delay(200);
  }

  // Initialize camera
  if (CAMERA_ENABLED) {
    cameraReady = initCamera();
    if (cameraReady) {
      Serial.println("[Boot] Camera ready.");
    } else {
      Serial.println("[Boot] Camera NOT ready — running without camera.");
    }
  } else {
    Serial.println("[Boot] Camera disabled in config.");
  }

  // Connect to WiFi
  Serial.println("[Boot] Connecting to WiFi...");
  connectWiFi();
}


// ══════════════════════════════════════════════════════════════
//  CAMERA INITIALIZATION
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
  config.xclk_freq_hz = 16000000;
  config.pixel_format = PIXFORMAT_JPEG;

  // Use SVGA (800x600) for a good balance of quality and size
  // ESP32-CAM has 4MB PSRAM, so we can use larger frames
  if (psramFound()) {
    config.frame_size   = FRAMESIZE_VGA;    // 640x480 — safer for OV3660 init
    config.jpeg_quality = 10;
    config.fb_count     = 2;
  } else {
    // No PSRAM — use smaller frame
    config.frame_size   = FRAMESIZE_VGA;    // 640x480
    config.jpeg_quality = 15;
    config.fb_count     = 1;
  }
  
  Serial.println("[Camera] Initializing...");
  // Add this block right before: esp_err_t err = esp_camera_init(&config);
  Serial.println("[Camera] Scanning I2C for camera sensor...");
  Wire.begin(SIOD_GPIO_NUM, SIOC_GPIO_NUM);
  for (byte addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
      Serial.printf("[Camera] Found I2C device at 0x%02X\n", addr);
    }
  }
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("[Camera] Init FAILED — error 0x%x (%s)\n",
                  err, esp_err_to_name(err));
    Serial.println("[Camera] Check: board target, PSRAM setting, camera pin definitions.");
    return false;
  }
  Serial.println("[Camera] Init OK.");

  // Adjust camera settings for outdoor use
  sensor_t * s = esp_camera_sensor_get();
  if (s) {
    Serial.printf("[Camera] Sensor detected: PID 0x%x\n", s->id.PID);
    s->set_brightness(s, 1);     // Slightly brighter
    s->set_contrast(s, 1);       // Slightly more contrast
    s->set_saturation(s, 0);     // Normal saturation
    s->set_whitebal(s, 1);       // Auto white balance ON
    s->set_awb_gain(s, 1);       // AWB gain ON
    s->set_wb_mode(s, 0);        // Auto WB mode
    s->set_exposure_ctrl(s, 1);  // Auto exposure ON
    s->set_aec2(s, 1);           // AEC DSP ON
    s->set_gain_ctrl(s, 1);      // Auto gain ON
    s->set_agc_gain(s, 0);       // AGC gain 0
    s->set_gainceiling(s, (gainceiling_t)6);  // Gain ceiling 64x
    s->set_bpc(s, 1);            // Black pixel correction
    s->set_wpc(s, 1);            // White pixel correction
    s->set_raw_gma(s, 1);        // Gamma correction
    s->set_lenc(s, 1);           // Lens correction
  }

  return true;
}


// ══════════════════════════════════════════════════════════════
//  CAMERA CAPTURE
// ══════════════════════════════════════════════════════════════

/*
 * Captures a JPEG image from the OV2640 camera.
 * Returns a camera frame buffer pointer.
 * IMPORTANT: Caller must call esp_camera_fb_return(fb) after use!
 */
camera_fb_t* captureImage() {
  if (!cameraReady) return NULL;

  // Turn on flash LED for better image in rain/dark conditions
  if (USE_FLASH) {
    digitalWrite(PIN_LED_FLASH, HIGH);
    delay(100);  // Brief delay for flash to stabilize
  }

  camera_fb_t* fb = NULL;

  // Discard first frame (often contains artifacts from sensor startup)
  fb = esp_camera_fb_get();
  if (fb) {
    esp_camera_fb_return(fb);
    fb = NULL;
  }
  delay(50);

  // Capture the actual image (with retries)
  for (int i = 0; i < CAPTURE_RETRIES; i++) {
    fb = esp_camera_fb_get();
    if (fb && fb->len > 0) {
      break;  // Good capture
    }
    if (fb) {
      esp_camera_fb_return(fb);
      fb = NULL;
    }
    delay(100);
  }

  // Turn off flash
  if (USE_FLASH) {
    digitalWrite(PIN_LED_FLASH, LOW);
  }

  return fb;
}


// ══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ══════════════════════════════════════════════════════════════

void loop() {
  unsigned long now = millis();

  // ── Check WiFi connection ──────────────────────────────
  // ESP32-S3 LED is active HIGH (HIGH = ON, LOW = OFF)
  if (WiFi.status() != WL_CONNECTED) {
    // Blink fast while disconnected
    digitalWrite(PIN_LED_STATUS, (millis() / 200) % 2 == 0 ? HIGH : LOW);
    if (timeSince(now, lastWiFiAttempt) >= WIFI_RECONNECT_INTERVAL) {
      connectWiFi();
      lastWiFiAttempt = now;
    }
  } else {
    digitalWrite(PIN_LED_STATUS, HIGH);  // Solid ON = WiFi connected
  }

  // ── Read incoming Serial1 data from Arduino (GPIO44 RX) ────
  while (Serial1.available()) {
    char c = Serial1.read();
    if (c == '\n') {
      serialBuffer.trim();
      if (serialBuffer.length() > 0) {
        processSerialData(serialBuffer);
        lastDataReceived = now;
        deviceOnline = true;
      }
      serialBuffer = "";
    } else if (serialBuffer.length() < SERIAL_BUFFER_MAX) {
      serialBuffer += c;
    }
  }

  // ── Send heartbeat to server periodically ──────────────
  if (timeSince(now, lastHeartbeatSent) >= HEARTBEAT_INTERVAL) {
    lastHeartbeatSent = now;
    sendHeartbeat();
  }

  // ── Check if Arduino has gone silent (> 30s no data) ───
  if (deviceOnline && timeSince(now, lastDataReceived) > 30000) {
    deviceOnline = false;
  }

  delay(10);
}


// ══════════════════════════════════════════════════════════════
//  SERIAL DATA PROCESSING
// ══════════════════════════════════════════════════════════════

/*
 * Protocol from Arduino Uno:
 *
 *   $SAFE,DATA,<rain>,<waterRaw>,<vibCount>,<alertLevel>
 *   $SAFE,ALERT,<level>,<eventType>,<waterPct>,<vibration>|<message>
 *   $SAFE,HEARTBEAT,0,0,0,0
 *   $SAFE,BOOT,0,0,0,0
 */

void processSerialData(String line) {
  if (!line.startsWith("$SAFE,")) return;
  line = line.substring(6);

  if (line.startsWith("DATA,")) {
    processDataPacket(line.substring(5));
  }
  else if (line.startsWith("ALERT,")) {
    processAlertPacket(line.substring(6));
  }
  else if (line.startsWith("HEARTBEAT")) {
    // Arduino alive — timestamp already updated
  }
  else if (line.startsWith("BOOT")) {
    sendHeartbeat();
  }
}

void processDataPacket(String data) {
  int idx1 = data.indexOf(',');
  int idx2 = data.indexOf(',', idx1 + 1);
  int idx3 = data.indexOf(',', idx2 + 1);

  if (idx1 < 0 || idx2 < 0 || idx3 < 0) return;

  lastRain       = data.substring(0, idx1).toInt();
  lastWaterLevel = data.substring(idx1 + 1, idx2).toInt();
  lastVibCount   = data.substring(idx2 + 1, idx3).toInt();
  lastAlertLevel = data.substring(idx3 + 1).toInt();
}

void processAlertPacket(String data) {
  int pipeIdx = data.indexOf('|');
  if (pipeIdx < 0) return;

  String params  = data.substring(0, pipeIdx);
  String message = data.substring(pipeIdx + 1);

  int idx1 = params.indexOf(',');
  int idx2 = params.indexOf(',', idx1 + 1);
  int idx3 = params.indexOf(',', idx2 + 1);

  if (idx1 < 0 || idx2 < 0 || idx3 < 0) return;

  String level     = params.substring(0, idx1);
  String eventType = params.substring(idx1 + 1, idx2);
  float  waterPct  = params.substring(idx2 + 1, idx3).toFloat();
  int    vibration = params.substring(idx3 + 1).toInt();

  String rainStatus = lastRain ? "detected" : "none";

  // ── Step 1: Send the JSON alert ────────────────────────
  bool alertSuccess = sendAlertWithRetry(level, eventType, rainStatus,
                                         waterPct, vibration, message);

  // ── Step 2: Capture and send camera image ──────────────
  // Only capture for DANGER and CRITICAL alerts
  if (CAMERA_ENABLED && cameraReady &&
      (level == "danger" || level == "critical")) {

    camera_fb_t* fb = captureImage();
    if (fb && fb->len > 0) {
      sendCameraImage(fb, level, eventType);
      esp_camera_fb_return(fb);
    }
  }

  // Visual feedback — ESP32-S3 LED is active HIGH
  if (!alertSuccess) {
    // Rapid 5-flash = alert POST failed
    for (int i = 0; i < 5; i++) {
      digitalWrite(PIN_LED_STATUS, HIGH);
      delay(100);
      digitalWrite(PIN_LED_STATUS, LOW);
      delay(100);
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  WIFI CONNECTION
// ══════════════════════════════════════════════════════════════

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  // ESP32-S3 LED is active HIGH
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    digitalWrite(PIN_LED_STATUS, attempts % 2 == 0 ? HIGH : LOW);
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    digitalWrite(PIN_LED_STATUS, HIGH);  // ON = connected
    Serial.printf("[WiFi] Connected. IP: %s  RSSI: %d dBm\n",
                  WiFi.localIP().toString().c_str(), WiFi.RSSI());
  } else {
    wifiFailCount++;
    digitalWrite(PIN_LED_STATUS, LOW);   // OFF = failed
    Serial.printf("[WiFi] Connection FAILED (attempt %d).\n", wifiFailCount);
    if (wifiFailCount >= 10) {
      Serial.println("[WiFi] Too many failures — restarting.");
      ESP.restart();
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  HTTP ALERT SENDING (JSON)
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

  // FIX BUG-E1: http.begin(url) removed in ESP32 Core 2.x — silent fail.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);

  int httpCode = http.POST(jsonBody);
  bool success = (httpCode == 201);

  http.end();
  return success;
}

bool sendAlertWithRetry(String level, String eventType, String rainStatus,
                        float waterLevel, int vibration, String message) {
  for (int attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    if (sendAlert(level, eventType, rainStatus, waterLevel, vibration, message)) {
      return true;
    }
    if (attempt < MAX_RETRIES) {
      unsigned long delayMs = RETRY_DELAY_BASE * (1 << (attempt - 1));
      delay(delayMs);
      if (WiFi.status() != WL_CONNECTED) connectWiFi();
    }
  }
  return false;
}


// ══════════════════════════════════════════════════════════════
//  CAMERA IMAGE UPLOAD (multipart/form-data)
// ══════════════════════════════════════════════════════════════

/*
 * Sends a captured JPEG image to the server as a multipart
 * form-data POST request. The server stores the image and
 * associates it with the most recent alert from this device.
 *
 * The image is sent alongside metadata fields:
 *   - api_key, device_id, alert_level, event_type
 *
 * This is a separate HTTP request from the JSON alert because
 * the alert should go through fast (small JSON), while the
 * image upload is larger and can take more time.
 */
bool sendCameraImage(camera_fb_t* fb, String alertLevel, String eventType) {
  if (WiFi.status() != WL_CONNECTED || !fb || fb->len == 0) return false;

  String url = String(SERVER_URL) + String(IMAGE_ENDPOINT);

  // Build multipart/form-data boundary
  String boundary = "----SafeSenseBoundary" + String(millis());

  // Build the multipart body parts (text fields)
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

  // Image file part header
  bodyStart += "--" + boundary + "\r\n";
  bodyStart += "Content-Disposition: form-data; name=\"image\"; filename=\"safesense_capture.jpg\"\r\n";
  bodyStart += "Content-Type: image/jpeg\r\n\r\n";

  // End boundary
  String bodyEnd = "\r\n--" + boundary + "--\r\n";

  // Calculate total content length
  int totalLength = bodyStart.length() + fb->len + bodyEnd.length();

  // Send via WiFiClient for streaming large payloads
  WiFiClient client;
  HTTPClient http;
  http.begin(client, url);
  http.addHeader("Content-Type", "multipart/form-data; boundary=" + boundary);
  http.addHeader("Content-Length", String(totalLength));
  http.setTimeout(30000);  // 30s timeout for image upload

  // We need to send the data in chunks using the WiFiClient directly
  // HTTPClient doesn't support streaming multipart easily,
  // so we'll build the complete payload in memory for small images.
  // For SVGA (800x600) at quality 12, JPEG is typically 30-80KB.

  // Allocate buffer for the complete request body
  uint8_t* fullBody = (uint8_t*)malloc(totalLength);
  if (!fullBody) {
    http.end();
    return false;
  }

  // Copy parts into buffer
  int offset = 0;
  memcpy(fullBody + offset, bodyStart.c_str(), bodyStart.length());
  offset += bodyStart.length();
  memcpy(fullBody + offset, fb->buf, fb->len);
  offset += fb->len;
  memcpy(fullBody + offset, bodyEnd.c_str(), bodyEnd.length());

  // Send
  int httpCode = http.POST(fullBody, totalLength);
  bool success = (httpCode == 200 || httpCode == 201);

  free(fullBody);
  http.end();
  return success;
}


// ══════════════════════════════════════════════════════════════
//  HEARTBEAT
// ══════════════════════════════════════════════════════════════

void sendHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;

  DynamicJsonDocument doc(512);

  doc["api_key"]       = API_KEY;
  doc["device_id"]     = DEVICE_ID;
  doc["station_type"]  = STATION_TYPE;
  doc["status"]        = deviceOnline ? "online" : "arduino_disconnected";
  doc["wifi_rssi"]     = WiFi.RSSI();
  doc["uptime_ms"]     = millis();
  doc["free_heap"]     = ESP.getFreeHeap();
  doc["camera_ready"]  = cameraReady;
  doc["psram"]         = psramFound();

  String jsonBody;
  serializeJson(doc, jsonBody);

  String url = String(SERVER_URL) + String(HEARTBEAT_ENDPOINT);

  // FIX BUG-E2: same WiFiClient fix as BUG-E1.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  http.POST(jsonBody);
  http.end();
}


// ══════════════════════════════════════════════════════════════
//  UTILITY FUNCTIONS
// ══════════════════════════════════════════════════════════════

unsigned long timeSince(unsigned long now, unsigned long start) {
  return now - start;
}
