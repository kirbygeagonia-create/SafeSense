/*
 * SafeSense IoT — ESP32-S3 WiFi Alert Gateway
 * Board : DFRobot FireBeetle 2 ESP32-S3
 *
 * Receives $SAFE packets from Arduino Uno via Serial1,
 * forwards alerts to the SafeSense server over HTTPS,
 * and sends a heartbeat every 5 minutes.
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

// ══════════════════════════════════════════════════════════════
//  CONFIGURATION
// ══════════════════════════════════════════════════════════════

const char* WIFI_SSID     = "Fracks";
const char* WIFI_PASSWORD = "686L[w36";

const char* SERVER_URL         = "https://safesense-tksy.onrender.com";
const char* ALERT_ENDPOINT     = "/api/alert";
const char* HEARTBEAT_ENDPOINT = "/api/heartbeat";

const char* API_KEY      = "safesense-live-key-928374823901";
const char* DEVICE_ID    = "SAFESENSE-001";
const char* STATION_TYPE = "hospital";
const char* LOCATION_NAME = "Brgy. Crossing Palkan, Tupi";
const float LATITUDE     = 8.1574;
const float LONGITUDE    = 124.9282;

// Serial1 pins — Arduino TX → ESP32 RX, Arduino RX ← ESP32 TX
// Match these to your actual wiring
const int SERIAL1_RX = 44;
const int SERIAL1_TX = 43;

const unsigned long WIFI_RECONNECT_MS = 30000;
const unsigned long HEARTBEAT_MS      = 300000;  // 5 min
const int           MAX_RETRIES       = 3;
const unsigned long RETRY_BASE_MS     = 2000;

// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

String        serialBuffer   = "";
const int     BUFFER_MAX     = 512;
unsigned long lastWiFiCheck  = 0;
unsigned long lastHeartbeat  = 0;
unsigned long lastDataRx     = 0;
bool          deviceOnline   = false;
int           wifiFailCount  = 0;

// Last known sensor values (updated from DATA packets)
int   lastRain       = 0;
int   lastWaterRaw   = 0;
int   lastVibCount   = 0;
int   lastAlertState = 0;

// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println("========================================");
  Serial.println(" SafeSense ESP32-S3 Gateway — Booting");
  Serial.println("========================================");
  Serial.printf("[Boot] Free heap : %d bytes\n", ESP.getFreeHeap());
  Serial.printf("[Boot] PSRAM     : %s\n", psramFound() ? "YES" : "NO");

  // UART1 from Arduino Uno
  Serial1.begin(9600, SERIAL_8N1, SERIAL1_RX, SERIAL1_TX);
  Serial.printf("[Boot] Serial1 ready — RX=GPIO%d TX=GPIO%d\n", SERIAL1_RX, SERIAL1_TX);

  connectWiFi();
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
        Serial.printf("[Arduino] %s\n", serialBuffer.c_str());
        processLine(serialBuffer);
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
    Serial.println("[Serial] Arduino silent 30s — offline");
  }

  // Heartbeat
  if (now - lastHeartbeat >= HEARTBEAT_MS) {
    lastHeartbeat = now;
    sendHeartbeat();
  }
}

// ══════════════════════════════════════════════════════════════
//  SERIAL PACKET PROCESSING
// ══════════════════════════════════════════════════════════════

void processLine(String line) {
  if (!line.startsWith("$SAFE,")) return;
  String body = line.substring(6);

  if (body.startsWith("DATA,")) {
    parseDataPacket(body.substring(5));
  } else if (body.startsWith("ALERT,")) {
    parseAlertPacket(body.substring(6));
  } else if (body.startsWith("BOOT")) {
    sendHeartbeat();
  }
}

void parseDataPacket(String data) {
  // Format: rain,waterRaw,vibCount,alertState
  int i1 = data.indexOf(',');
  int i2 = data.indexOf(',', i1 + 1);
  int i3 = data.indexOf(',', i2 + 1);
  if (i1 < 0 || i2 < 0 || i3 < 0) return;

  lastRain       = data.substring(0, i1).toInt();
  lastWaterRaw   = data.substring(i1 + 1, i2).toInt();
  lastVibCount   = data.substring(i2 + 1, i3).toInt();
  lastAlertState = data.substring(i3 + 1).toInt();
}

void parseAlertPacket(String data) {
  // Format: level,eventType,waterPct,vibration|message
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

  Serial.printf("[Alert] level=%s event=%s water=%.1f%% vib=%d\n",
                level.c_str(), eventType.c_str(), waterPct, vibration);

  bool ok = sendAlertWithRetry(level, eventType, rainStatus, waterPct, vibration, message);
  Serial.printf("[Alert] POST → %s\n", ok ? "OK" : "FAILED");

  if (!ok) {
    Serial.println("[Alert] Will retry on next trigger.");
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
    Serial.print(".");
    attempts++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    Serial.printf("[WiFi] Connected! IP=%s RSSI=%d dBm\n",
                  WiFi.localIP().toString().c_str(), WiFi.RSSI());
    Serial.printf("[WiFi] Server: %s\n", SERVER_URL);
  } else {
    wifiFailCount++;
    Serial.printf("[WiFi] FAILED (attempt %d)\n", wifiFailCount);
    if (wifiFailCount >= 10) {
      Serial.println("[WiFi] Too many failures — restarting.");
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
  doc["has_image"]     = 0;

  String body;
  serializeJson(doc, body);

  String url = String(SERVER_URL) + String(ALERT_ENDPOINT);

  WiFiClientSecure client;
  client.setInsecure();  // Skip cert verification (fine for capstone)
  HTTPClient http;
  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(45000);  // 45s — Render free tier cold start

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
  doc["camera_ready"] = false;
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
