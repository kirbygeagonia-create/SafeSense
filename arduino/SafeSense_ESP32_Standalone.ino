/*
 * ============================================================
 *  SafeSense IoT — ESP32 Standalone (All-in-One)
 *  Board  : ESP32 DevKit V1 / ESP32-WROOM-32
 *
 *  This is the SIMPLIFIED single-board version where one ESP32
 *  handles everything: sensor reading, LED control, GSM SMS,
 *  WiFi HTTP alerts, and heartbeat.
 *
 *  Use this sketch if you're using the simplified circuit
 *  design (no Arduino Uno — ESP32 does it all).
 *
 *  ── HARDWARE CONNECTIONS ──
 *
 *  Sensors:
 *    Vibration Sensor  → GPIO12 (digital input)
 *    Rain Sensor (DO)  → GPIO13 (digital input, LOW = rain)
 *    Water Level       → GPIO34 (analog input — ADC1, WiFi-safe)
 *                        NOT GPIO14: ADC2 is disabled by WiFi (BUG-S1 fix)
 *
 *  Outputs:
 *    LED 1 (Green)     → GPIO2  (via 220Ω resistor)
 *    LED 2 (Yellow)    → GPIO4  (via 220Ω resistor)
 *    LED 3 (Red)       → GPIO15 (via 220Ω resistor)
 *
 *  SIM900A GSM Module:
 *    SIM900A TX → GPIO16 (ESP32 HardwareSerial2 RX)
 *    SIM900A RX → GPIO17 (ESP32 HardwareSerial2 TX)
 *    SIM900A VCC → 4V external regulator (LM2596)
 *    SIM900A GND → Common GND
 *
 *  Power:
 *    Battery → LM2596 Buck Converter
 *      Output 5V  → ESP32 VIN
 *      Output ~4V → SIM900A VCC (SEPARATE regulator!)
 *    ALL GROUNDS CONNECTED
 *
 *  Required Libraries:
 *    - ArduinoJson by Benoit Blanchon (v6.x or v7.x)
 *    - WiFi.h (built-in)
 *    - HTTPClient.h (built-in)
 *
 *  Board Setup in Arduino IDE:
 *    1. Board: "ESP32 Dev Module"
 *    2. Upload Speed: 115200
 *    3. Flash Frequency: 80MHz
 *    4. Partition Scheme: "Default 4MB with spiffs"
 *
 * ============================================================
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <esp_task_wdt.h>    // ESP32 hardware watchdog

// ══════════════════════════════════════════════════════════════
//  CONFIGURATION — Edit these values for your deployment
// ══════════════════════════════════════════════════════════════

// ── WiFi Credentials ─────────────────────────────────────────
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// ── Server Configuration ─────────────────────────────────────
const char* SERVER_URL = "http://192.168.1.100/SafeSense/medical/public";

// ── API Endpoints ────────────────────────────────────────────
const char* ALERT_ENDPOINT     = "/api/alert";
const char* HEARTBEAT_ENDPOINT = "/api/heartbeat";

// ── API Key (must match server .env SAFESENSE_API_KEY) ───────
const char* API_KEY = "7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8";

// ── Device Identity ──────────────────────────────────────────
const char* DEVICE_ID     = "SAFESENSE-001";
const char* STATION_TYPE  = "hospital";

// ── Location ─────────────────────────────────────────────────
const float  LATITUDE      = 8.1574;
const float  LONGITUDE     = 124.9282;
const char*  LOCATION_NAME = "Brgy. Crossing Rubber, Tupi";

// ── SMS Recipients ───────────────────────────────────────────
const char* SMS_NUMBERS[] = {
  "639XXXXXXXXX",   // Emergency Contact 1
  "639XXXXXXXXX",   // Emergency Contact 2
};
const int SMS_COUNT = sizeof(SMS_NUMBERS) / sizeof(SMS_NUMBERS[0]);

// ── Sensor Thresholds ────────────────────────────────────────
// ESP32 ADC is 12-bit (0–4095) vs Arduino's 10-bit (0–1023)
const int WATER_LEVEL_SAFE     = 800;
const int WATER_LEVEL_WARNING  = 1600;
const int WATER_LEVEL_DANGER   = 2400;
const int WATER_LEVEL_CRITICAL = 3200;

// Vibration confirmation
const int  VIBRATION_CONFIRM_COUNT   = 3;
const unsigned long VIBRATION_WINDOW = 10000;

// ── Timing ───────────────────────────────────────────────────
const unsigned long SENSOR_READ_INTERVAL    = 2000;
const unsigned long ALERT_COOLDOWN_MS       = 60000;
const unsigned long SMS_COOLDOWN_MS         = 300000;
const unsigned long HEARTBEAT_INTERVAL      = 300000;
const unsigned long WIFI_RECONNECT_INTERVAL = 30000;
const unsigned long LED_FAST_BLINK_MS       = 150;
const unsigned long LED_SLOW_BLINK_MS       = 500;
const int           MAX_RETRIES             = 3;
const unsigned long RETRY_DELAY_BASE        = 2000;


// ══════════════════════════════════════════════════════════════
//  PIN DEFINITIONS
// ══════════════════════════════════════════════════════════════

// Sensors
const int PIN_VIBRATION     = 12;  // Digital
const int PIN_RAIN_DIGITAL  = 13;  // Digital (LOW = rain)
// FIX BUG-S1: GPIO14 = ADC2, disabled by WiFi. Use GPIO34 (ADC1, WiFi-safe).
// HARDWARE: rewire sensor signal wire from pin 14 to pin 34.
const int PIN_WATER_LEVEL   = 34;  // Analog (ADC1_CH6 — WiFi-safe)

// LEDs
const int PIN_LED_GREEN     = 2;   // Safe / Power
const int PIN_LED_YELLOW    = 4;   // Warning
const int PIN_LED_RED       = 15;  // Danger / Critical

// SIM900A GSM (HardwareSerial2)
const int PIN_GSM_RX        = 16;  // ESP32 receives FROM SIM900A
const int PIN_GSM_TX        = 17;  // ESP32 sends TO SIM900A


// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

// HardwareSerial for GSM
HardwareSerial gsmSerial(2);  // UART2

// Timing
unsigned long lastSensorRead    = 0;
unsigned long lastAlertTime     = 0;
// FIX BUG-NEW-1: same first-SMS skip fix as SafeSense_Arduino.ino.
unsigned long lastSmsTime       = (unsigned long)(0UL - SMS_COOLDOWN_MS);
unsigned long lastHeartbeat     = 0;
unsigned long lastWiFiAttempt   = 0;
unsigned long lastLedToggle     = 0;

// Sensor state
int   waterLevelRaw   = 0;
float waterLevelPct   = 0.0;
bool  isRaining       = false;
bool  prevRaining     = false;
bool  vibDetected     = false;

// Vibration confirmation
int           vibrationCount     = 0;
unsigned long vibrationFirstTime = 0;

// LED state
bool ledRedState = false;

// Alert level: 0=safe, 1=warning, 2=danger, 3=critical
int currentAlertLevel = 0;
int prevAlertLevel    = 0;

// GSM
bool gsmReady = false;

// WiFi
int wifiFailCount = 0;


// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  Serial.begin(115200);  // USB debug output
  Serial.println("\n[SafeSense] ESP32 Standalone — Booting...");

  // Enable hardware watchdog (10 second timeout)
  esp_task_wdt_init(10, true);
  esp_task_wdt_add(NULL);

  // GSM Serial
  gsmSerial.begin(9600, SERIAL_8N1, PIN_GSM_RX, PIN_GSM_TX);

  // Pin modes
  pinMode(PIN_VIBRATION,    INPUT);
  pinMode(PIN_RAIN_DIGITAL, INPUT);
  // Note: analogRead on ESP32 doesn't need pinMode for ADC pins

  pinMode(PIN_LED_GREEN,  OUTPUT);
  pinMode(PIN_LED_YELLOW, OUTPUT);
  pinMode(PIN_LED_RED,    OUTPUT);

  // Startup LED sequence
  digitalWrite(PIN_LED_GREEN,  HIGH);
  digitalWrite(PIN_LED_YELLOW, HIGH);
  digitalWrite(PIN_LED_RED,    HIGH);
  delay(500);
  digitalWrite(PIN_LED_YELLOW, LOW);
  digitalWrite(PIN_LED_RED,    LOW);

  // Initialize GSM
  Serial.println("[GSM] Initializing SIM900A...");
  initGSM();

  // Connect WiFi
  Serial.println("[WiFi] Connecting...");
  connectWiFi();

  Serial.println("[SafeSense] System ready.");
}


// ══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ══════════════════════════════════════════════════════════════

void loop() {
  esp_task_wdt_reset();  // Pet the watchdog

  unsigned long now = millis();

  // ── Maintain WiFi connection ───────────────────────────
  if (WiFi.status() != WL_CONNECTED) {
    if (timeSince(now, lastWiFiAttempt) >= WIFI_RECONNECT_INTERVAL) {
      Serial.println("[WiFi] Reconnecting...");
      connectWiFi();
      lastWiFiAttempt = now;
    }
  }

  // ── Read sensors ───────────────────────────────────────
  if (timeSince(now, lastSensorRead) >= SENSOR_READ_INTERVAL) {
    lastSensorRead = now;
    readSensors();
    evaluateAlertLevel();

    // Debug output
    Serial.printf("[Sensors] Rain=%d Water=%d (%.1f%%) Vib=%d Level=%d\n",
                  isRaining, waterLevelRaw, waterLevelPct, vibrationCount, currentAlertLevel);
  }

  // ── Update LEDs ────────────────────────────────────────
  updateLEDs(now);

  // ── Alert logic ────────────────────────────────────────
  if (currentAlertLevel > 0 && currentAlertLevel > prevAlertLevel) {
    if (timeSince(now, lastAlertTime) >= ALERT_COOLDOWN_MS) {
      triggerAlert(now);
      lastAlertTime = now;
    }
  }
  prevAlertLevel = currentAlertLevel;
  prevRaining    = isRaining;

  // ── Heartbeat ──────────────────────────────────────────
  if (timeSince(now, lastHeartbeat) >= HEARTBEAT_INTERVAL) {
    lastHeartbeat = now;
    sendHeartbeat();
  }

  delay(10);  // Small yield for WiFi stack
}


// ══════════════════════════════════════════════════════════════
//  SENSOR READING
// ══════════════════════════════════════════════════════════════

void readSensors() {
  // ── Water Level (Analog) ────────────────────────────────
  // Note: ESP32 ADC2 pins (including GPIO14) CANNOT be used
  // while WiFi is active! If you experience issues, move the
  // water level sensor to an ADC1 pin: GPIO32, GPIO33, GPIO34,
  // GPIO35, GPIO36, or GPIO39.
  //
  // Workaround: Take readings before WiFi, or use ADC1 pins.
  // For production, we recommend GPIO34 or GPIO35 (ADC1, input-only).
  long sum = 0;
  for (int i = 0; i < 5; i++) {
    sum += analogRead(PIN_WATER_LEVEL);
    delay(2);
  }
  waterLevelRaw = sum / 5;
  waterLevelPct = map(waterLevelRaw, 0, 4095, 0, 100);
  waterLevelPct = constrain(waterLevelPct, 0.0f, 100.0f);

  // ── Rain (Digital) ──────────────────────────────────────
  isRaining = (digitalRead(PIN_RAIN_DIGITAL) == LOW);

  // ── Vibration (Digital) ─────────────────────────────────
  vibDetected = (digitalRead(PIN_VIBRATION) == HIGH);

  if (vibDetected) {
    unsigned long now = millis();
    if (vibrationCount == 0) {
      vibrationFirstTime = now;
    }
    if (timeSince(now, vibrationFirstTime) <= VIBRATION_WINDOW) {
      vibrationCount++;
    } else {
      vibrationCount = 1;
      vibrationFirstTime = now;
    }
  } else {
    // FIX BUG-S2: same stale counter fix as BUG-A2.
    unsigned long now = millis();
    if (vibrationCount > 0 && timeSince(now, vibrationFirstTime) > VIBRATION_WINDOW) {
      vibrationCount = 0;
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  ALERT LEVEL EVALUATION
// ══════════════════════════════════════════════════════════════

void evaluateAlertLevel() {
  bool vibrationConfirmed = (vibrationCount >= VIBRATION_CONFIRM_COUNT);

  if (waterLevelRaw >= WATER_LEVEL_CRITICAL && isRaining) {
    currentAlertLevel = 3;
  }
  else if (vibrationConfirmed && (isRaining || waterLevelRaw >= WATER_LEVEL_WARNING)) {
    currentAlertLevel = 3;
    vibrationCount = 0;
  }
  else if (waterLevelRaw >= WATER_LEVEL_DANGER && isRaining) {
    currentAlertLevel = 2;
  }
  else if (waterLevelRaw >= WATER_LEVEL_WARNING || isRaining) {
    currentAlertLevel = 1;
  }
  else {
    currentAlertLevel = 0;
  }
}


// ══════════════════════════════════════════════════════════════
//  LED CONTROL (non-blocking)
// ══════════════════════════════════════════════════════════════

void updateLEDs(unsigned long now) {
  switch (currentAlertLevel) {
    case 0:
      digitalWrite(PIN_LED_GREEN,  HIGH);
      digitalWrite(PIN_LED_YELLOW, LOW);
      digitalWrite(PIN_LED_RED,    LOW);
      break;
    case 1:
      digitalWrite(PIN_LED_GREEN,  LOW);
      digitalWrite(PIN_LED_YELLOW, HIGH);
      digitalWrite(PIN_LED_RED,    LOW);
      break;
    case 2:
      digitalWrite(PIN_LED_GREEN,  LOW);
      digitalWrite(PIN_LED_YELLOW, HIGH);
      if (timeSince(now, lastLedToggle) >= LED_SLOW_BLINK_MS) {
        lastLedToggle = now;
        ledRedState = !ledRedState;
        digitalWrite(PIN_LED_RED, ledRedState);
      }
      break;
    case 3:
      digitalWrite(PIN_LED_GREEN, LOW);
      if (timeSince(now, lastLedToggle) >= LED_FAST_BLINK_MS) {
        lastLedToggle = now;
        ledRedState = !ledRedState;
        digitalWrite(PIN_LED_RED,    ledRedState);
        digitalWrite(PIN_LED_YELLOW, !ledRedState);
      }
      break;
  }
}


// ══════════════════════════════════════════════════════════════
//  ALERT TRIGGERING (WiFi + SMS)
// ══════════════════════════════════════════════════════════════

void triggerAlert(unsigned long now) {
  String levelStr, eventType, message;

  switch (currentAlertLevel) {
    case 3:
      levelStr = "critical";
      if (vibrationCount >= VIBRATION_CONFIRM_COUNT) {
        eventType = "accident";
        message = "CRITICAL: Possible road accident detected via vibration sensor during ";
        message += isRaining ? "rain" : "flood";
        message += " event. Water level: " + String(waterLevelPct, 1) + "%.";
      } else {
        eventType = "flood";
        message = "CRITICAL: Flood detected. Water level at " + String(waterLevelPct, 1);
        message += "% — DANGER threshold exceeded. Immediate response required!";
      }
      break;
    case 2:
      levelStr = "danger";
      eventType = "flood";
      message = "DANGER: Rising floodwater detected. Water level: ";
      message += String(waterLevelPct, 1) + "%. Road hazard likely.";
      break;
    case 1:
      levelStr = "warning";
      eventType = isRaining ? "rain" : "flood";
      message = "WARNING: ";
      message += isRaining ? "Rain detected." : "Water level rising.";
      message += " Water level: " + String(waterLevelPct, 1) + "%. Monitoring conditions.";
      break;
    default:
      return;
  }

  // Determine rain status
  String rainStatus = "none";
  if (isRaining) rainStatus = "detected";

  // ── Send via WiFi (HTTP POST) ──────────────────────────
  Serial.println("[Alert] Sending " + levelStr + " alert via WiFi...");
  bool wifiSuccess = sendAlertWithRetry(levelStr, eventType, rainStatus,
                                         waterLevelPct, vibrationCount > 0 ? 1 : 0, message);
  Serial.println(wifiSuccess ? "[Alert] WiFi: OK" : "[Alert] WiFi: FAILED");

  // ── Send via SMS (separate cooldown, only for danger+) ──
  if (currentAlertLevel >= 2 && timeSince(now, lastSmsTime) >= SMS_COOLDOWN_MS) {
    Serial.println("[Alert] Sending SMS...");
    sendSMS(message);
    lastSmsTime = now;
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

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
    Serial.println("[WiFi] RSSI: " + String(WiFi.RSSI()) + " dBm");
  } else {
    wifiFailCount++;
    Serial.println("\n[WiFi] Failed to connect.");
    if (wifiFailCount >= 10) {
      Serial.println("[WiFi] Too many failures — restarting ESP32...");
      ESP.restart();
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  HTTP ALERT SENDING
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

  String jsonBody;
  serializeJson(doc, jsonBody);

  String url = String(SERVER_URL) + String(ALERT_ENDPOINT);

  // FIX BUG-S5: same WiFiClient fix as BUG-E1.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);

  int httpCode = http.POST(jsonBody);
  bool success = (httpCode == 201);

  Serial.printf("[HTTP] POST %s → %d\n", url.c_str(), httpCode);

  http.end();
  return success;
}

bool sendAlertWithRetry(String level, String eventType, String rainStatus,
                        float waterLevel, int vibration, String message) {
  for (int attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    Serial.printf("[Alert] Attempt %d/%d\n", attempt, MAX_RETRIES);
    if (sendAlert(level, eventType, rainStatus, waterLevel, vibration, message)) {
      return true;
    }
    if (attempt < MAX_RETRIES) {
      unsigned long delayMs = RETRY_DELAY_BASE * (1 << (attempt - 1));
      delay(delayMs);
      if (WiFi.status() != WL_CONNECTED) connectWiFi();
    }
  }
  Serial.println("[Alert] All retry attempts exhausted.");
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
  doc["status"]       = "online";
  doc["wifi_rssi"]    = WiFi.RSSI();
  doc["uptime_ms"]    = millis();
  doc["free_heap"]    = ESP.getFreeHeap();

  String jsonBody;
  serializeJson(doc, jsonBody);

  String url = String(SERVER_URL) + String(HEARTBEAT_ENDPOINT);

  // FIX BUG-S6: same WiFiClient fix as BUG-E2.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int code = http.POST(jsonBody);
  Serial.printf("[Heartbeat] → %d\n", code);

  http.end();
}


// ══════════════════════════════════════════════════════════════
//  SIM900A GSM — SMS FUNCTIONS
// ══════════════════════════════════════════════════════════════

void initGSM() {
  delay(1000);

  gsmSerial.println("AT");
  delay(1000);
  if (gsmReadResponse().indexOf("OK") >= 0) {
    gsmReady = true;
  } else {
    gsmSerial.println("AT");
    delay(2000);
    if (gsmReadResponse().indexOf("OK") >= 0) {
      gsmReady = true;
    }
  }

  if (gsmReady) {
    gsmSerial.println("AT+CMGF=1");
    delay(500);
    gsmReadResponse();

    gsmSerial.println("AT+CSCS=\"GSM\"");
    delay(500);
    gsmReadResponse();

    gsmSerial.println("AT+CPIN?");
    delay(500);
    String simStatus = gsmReadResponse();
    Serial.println("[GSM] SIM: " + simStatus);

    gsmSerial.println("AT+CSQ");
    delay(500);
    Serial.println("[GSM] Signal: " + gsmReadResponse());

    Serial.println("[GSM] Ready.");
  } else {
    Serial.println("[GSM] SIM900A not responding.");
  }
}

void sendSMS(String message) {
  if (!gsmReady) {
    initGSM();
    if (!gsmReady) return;
  }

  String smsBody = "[SafeSense " + String(DEVICE_ID) + "] " + message +
                   " | Location: " + String(LOCATION_NAME);

  if (smsBody.length() > 160) {
    smsBody = smsBody.substring(0, 157) + "...";
  }

  for (int i = 0; i < SMS_COUNT; i++) {
    esp_task_wdt_reset();

    gsmSerial.print("AT+CMGS=\"");
    gsmSerial.print(SMS_NUMBERS[i]);
    gsmSerial.println("\"");
    delay(1000);

    String prompt = gsmReadResponse();
    // FIX BUG-S3: same GSM corruption fix as BUG-A3.
    if (prompt.indexOf(">") >= 0) {
      gsmSerial.print(smsBody);
      gsmSerial.write(26);  // Ctrl+Z
      delay(5000);

      String result = gsmReadResponse();
      Serial.println("[SMS] Sent to " + String(SMS_NUMBERS[i]) + ": " +
                     (result.indexOf("OK") >= 0 ? "OK" : "FAIL"));
    }

    delay(2000);
    esp_task_wdt_reset();
  }
}

String gsmReadResponse() {
  // FIX BUG-S4: esp_task_wdt_reset() added — early exit prevents WDT trip.
  String response = "";
  unsigned long start = millis();
  while (timeSince(millis(), start) < 3000) {
    esp_task_wdt_reset();
    if (gsmSerial.available()) {
      response += (char)gsmSerial.read();
      if (response.indexOf("OK") >= 0    ||
          response.indexOf("ERROR") >= 0 ||
          response.indexOf(">") >= 0     ||
          response.indexOf("+CMGS:") >= 0) {
        delay(50);
        while (gsmSerial.available()) response += (char)gsmSerial.read();
        break;
      }
    }
  }
  return response;
}


// ══════════════════════════════════════════════════════════════
//  UTILITY
// ══════════════════════════════════════════════════════════════

unsigned long timeSince(unsigned long now, unsigned long start) {
  return now - start;
}
