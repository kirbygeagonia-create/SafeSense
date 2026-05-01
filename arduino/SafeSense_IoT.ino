/*
 * ============================================================
 *  SafeSense IoT — Arduino WiFi Alert Sender
 *  Board  : Arduino Uno/Mega + ESP8266 WiFi Shield
 *           (or ESP32 / NodeMCU — see notes)
 *
 *  This sketch reads sensor data and POSTs a JSON alert
 *  to your Hospital Management System when thresholds
 *  are exceeded.
 *
 *  Required Libraries (install via Arduino Library Manager):
 *    - ArduinoJson  by Benoit Blanchon  (v6.x)
 *    - ESP8266WiFi  (if using ESP8266 shield)
 *    - ESP8266HTTPClient
 * ============================================================
 */

#include <ArduinoJson.h>

// ── Choose your WiFi method ──────────────────────────────────
// Uncomment ONE block depending on your hardware:

// --- Option A: ESP8266 / NodeMCU (most common) ---
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>

// --- Option B: ESP32 ---
// #include <WiFi.h>
// #include <HTTPClient.h>

// --- Option C: Arduino WiFi Shield R2 ---
// #include <WiFiNINA.h>
// ────────────────────────────────────────────────────────────


// ══════════════════════════════════════════════════════════════
//  CONFIGURATION — Edit these values
// ══════════════════════════════════════════════════════════════

// WiFi credentials
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// Your Hospital Management System server
// Use your server's local IP or domain (no trailing slash)
const char* SERVER_HOST   = "http://192.168.1.100";  // e.g. your PC's IP on the LAN
const char* SERVER_PATH   = "/SafeSense/medical";    // Base path if installed in subdirectory
const char* API_ENDPOINT  = "/api/alert";            // Full path will be SERVER_HOST + SERVER_PATH + API_ENDPOINT
// Task 1 — API key now lives in medical/.env as SAFESENSE_API_KEY; mirror it here
const char* API_KEY       = "7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8";   // must match .env SAFESENSE_API_KEY

// Device identity
const char* DEVICE_ID     = "SAFESENSE-001";
const char* STATION_TYPE  = "hospital";  // hospital | police | fire

// Location (set to your actual deployment coordinates)
const float  LATITUDE      = 8.1574;
const float  LONGITUDE     = 124.9282;
const char*  LOCATION_NAME = "Brgy. Casisang, Malaybalay City";


// ══════════════════════════════════════════════════════════════
//  PIN DEFINITIONS
// ══════════════════════════════════════════════════════════════

const int PIN_RAIN_DIGITAL  = 2;   // Rain sensor DO pin (LOW = rain)
const int PIN_RAIN_ANALOG   = A0;  // Rain sensor AO pin (intensity)
const int PIN_TRIG          = 9;   // Ultrasonic sensor TRIG
const int PIN_ECHO          = 10;  // Ultrasonic sensor ECHO
const int PIN_VIBRATION     = 3;   // Vibration/impact sensor DO
const int PIN_LED_RED       = 5;   // Red hazard LED (danger)
const int PIN_LED_YELLOW    = 6;   // Yellow hazard LED (warning)
const int PIN_BUZZER        = 7;   // Buzzer


// ══════════════════════════════════════════════════════════════
//  SENSOR MOUNTING & THRESHOLDS
//  Adjust SENSOR_HEIGHT_CM to match your physical installation.
//  water_level = SENSOR_HEIGHT_CM - (ultrasonic distance reading)
// ══════════════════════════════════════════════════════════════

const float SENSOR_HEIGHT_CM = 100.0; // Height of ultrasonic sensor above ground (cm)
                                       // Measure from sensor face to dry ground surface

const float WATER_WARNING  = 20.0;  // cm — Yellow LED on
const float WATER_DANGER   = 35.0;  // cm — Red LED slow blink
const float WATER_CRITICAL = 50.0;  // cm — Red LED fast blink + alert


// ══════════════════════════════════════════════════════════════
//  ALERT COOLDOWN (prevent spam)
// ══════════════════════════════════════════════════════════════

const unsigned long ALERT_COOLDOWN_MS = 60000;  // 60 seconds between alerts

unsigned long lastAlertTime   = 0;
int           vibrationCount  = 0;
bool          prevRaining     = false;


// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  Serial.begin(115200);
  delay(100);

  // Pin modes
  pinMode(PIN_RAIN_DIGITAL, INPUT);
  pinMode(PIN_VIBRATION,    INPUT);
  pinMode(PIN_TRIG,         OUTPUT);
  pinMode(PIN_ECHO,         INPUT);
  pinMode(PIN_LED_RED,      OUTPUT);
  pinMode(PIN_LED_YELLOW,   OUTPUT);
  pinMode(PIN_BUZZER,       OUTPUT);

  Serial.println("\n[SafeSense] Booting...");

  // Connect to WiFi
  connectWiFi();

  Serial.println("[SafeSense] System ready.");
}


// ══════════════════════════════════════════════════════════════
//  LOOP
// ══════════════════════════════════════════════════════════════

void loop() {
  // Reconnect WiFi if dropped
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] Disconnected — reconnecting...");
    connectWiFi();
  }

  // ── Read sensors ──────────────────────────────────────
  bool  isRaining    = (digitalRead(PIN_RAIN_DIGITAL) == LOW);
  int   rainIntensity = analogRead(PIN_RAIN_ANALOG);   // 0–1023 (higher = more rain)
  float waterLevel    = measureWaterLevel();
  bool  vibDetected   = (digitalRead(PIN_VIBRATION) == HIGH);

  // ── Debug output ──────────────────────────────────────
  Serial.print("[Sensors] Rain="); Serial.print(isRaining);
  Serial.print(" Intensity="); Serial.print(rainIntensity);
  Serial.print(" WaterLevel="); Serial.print(waterLevel);
  Serial.print("cm Vib="); Serial.println(vibDetected);

  // ── LED hazard lighting ───────────────────────────────
  updateLEDs(isRaining, waterLevel);

  // ── Alert logic ───────────────────────────────────────
  unsigned long now = millis();
  bool cooldownOk = (now - lastAlertTime > ALERT_COOLDOWN_MS);

  if (cooldownOk) {

    String rainStatus = getRainStatus(rainIntensity, isRaining);

    // CRITICAL flood — Task 9: use sendWithRetry instead of sendAlert
    if (waterLevel >= WATER_CRITICAL && isRaining) {
      String msg = "CRITICAL: Flood detected. Water level at " + String(waterLevel, 1) +
                   " cm — DANGER threshold exceeded. Immediate response required!";
      sendWithRetry("critical", "flood", rainStatus, waterLevel, false, msg);
      lastAlertTime = now;
    }
    // DANGER — high water
    else if (waterLevel >= WATER_DANGER && isRaining) {
      String msg = "DANGER: Rising floodwater detected. Water level: " + String(waterLevel, 1) +
                   " cm. Road hazard likely.";
      sendWithRetry("danger", "flood", rainStatus, waterLevel, false, msg);
      lastAlertTime = now;
    }
    // WARNING — rain started
    else if (isRaining && !prevRaining) {
      String msg = "WARNING: Rain detected (" + rainStatus + "). Water level: " +
                   String(waterLevel, 1) + " cm. Monitoring conditions.";
      sendWithRetry("warning", "rain", rainStatus, waterLevel, false, msg);
      lastAlertTime = now;
    }
    // ACCIDENT — vibration during rain/flood
    else if (vibDetected && (isRaining || waterLevel >= WATER_WARNING)) {
      vibrationCount++;
      if (vibrationCount >= 3) {  // 3 consecutive detections = confirmed
        String msg = "DANGER: Possible road accident detected via vibration sensor during " +
                     String(isRaining ? "rain" : "flood") + " event. Water level: " +
                     String(waterLevel, 1) + " cm.";
        sendWithRetry("danger", "accident", rainStatus, waterLevel, true, msg);
        lastAlertTime  = now;
        vibrationCount = 0;
      }
    } else {
      vibrationCount = 0;
    }
  }

  prevRaining = isRaining;
  delay(2000);  // Check every 2 seconds
}


// ══════════════════════════════════════════════════════════════
//  FUNCTIONS
// ══════════════════════════════════════════════════════════════

void connectWiFi() {
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("[WiFi] Connecting to ");
  Serial.print(WIFI_SSID);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("\n[WiFi] Failed to connect. Will retry.");
  }
}


float measureWaterLevel() {
  // Send ultrasonic pulse
  digitalWrite(PIN_TRIG, LOW);  delayMicroseconds(2);
  digitalWrite(PIN_TRIG, HIGH); delayMicroseconds(10);
  digitalWrite(PIN_TRIG, LOW);

  long duration = pulseIn(PIN_ECHO, HIGH, 30000);  // timeout 30ms
  if (duration == 0) return 0.0;

  float distanceCm = duration * 0.034 / 2;
  // If sensor is mounted 100 cm above ground:
  // water_level = 100 - distance
  // Adjust the 100.0 to your actual sensor height
  float waterLevel = SENSOR_HEIGHT_CM - distanceCm;
  return max(0.0f, waterLevel);
}


void updateLEDs(bool isRaining, float waterLevel) {
  if (waterLevel >= WATER_CRITICAL) {
    // Fast blink — critical
    digitalWrite(PIN_LED_RED,    HIGH);
    digitalWrite(PIN_LED_YELLOW, LOW);
    delay(100);
    digitalWrite(PIN_LED_RED, LOW);
    delay(100);
  } else if (waterLevel >= WATER_DANGER) {
    // Slow blink — danger
    digitalWrite(PIN_LED_RED,    HIGH);
    digitalWrite(PIN_LED_YELLOW, LOW);
    delay(400);
    digitalWrite(PIN_LED_RED, LOW);
    delay(400);
  } else if (waterLevel >= WATER_WARNING || isRaining) {
    // Solid yellow — warning
    digitalWrite(PIN_LED_RED,    LOW);
    digitalWrite(PIN_LED_YELLOW, HIGH);
  } else {
    // All off — safe
    digitalWrite(PIN_LED_RED,    LOW);
    digitalWrite(PIN_LED_YELLOW, LOW);
  }
}


String getRainStatus(int intensity, bool isRaining) {
  if (!isRaining)        return "none";
  if (intensity > 800)   return "heavy";
  if (intensity > 500)   return "moderate";
  return "light";
}


// ── sendAlert: returns true on HTTP 201, false otherwise ──────
bool sendAlert(String level, String eventType, String rainStatus,
               float waterLevel, bool vibration, String message) {

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[Alert] No WiFi — skipping HTTP alert.");
    return false;
  }

  Serial.println("[Alert] Sending " + level + " alert...");

  // Build JSON payload
  StaticJsonDocument<512> doc;
  doc["api_key"]       = API_KEY;
  doc["device_id"]     = DEVICE_ID;
  doc["station_type"]  = STATION_TYPE;
  doc["alert_level"]   = level;
  doc["event_type"]    = eventType;
  doc["rain_status"]   = rainStatus;
  doc["water_level"]   = waterLevel;
  doc["vibration"]     = vibration ? 1 : 0;
  doc["message"]       = message;
  doc["latitude"]      = LATITUDE;
  doc["longitude"]     = LONGITUDE;
  doc["location_name"] = LOCATION_NAME;

  String jsonBody;
  serializeJson(doc, jsonBody);

  // HTTP POST
  WiFiClient client;
  HTTPClient http;
  String url = String(SERVER_HOST) + String(SERVER_PATH) + String(API_ENDPOINT);

  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(jsonBody);

  bool success = (httpCode == 201);
  if (success) {
    Serial.println("[Alert] ✓ Sent successfully (HTTP 201)");
    tone(PIN_BUZZER, 1000, 200);
    delay(300);
    tone(PIN_BUZZER, 1400, 200);
  } else {
    Serial.print("[Alert] ✗ Failed. HTTP code: ");
    Serial.println(httpCode);
    tone(PIN_BUZZER, 400, 500);
  }

  http.end();
  return success;
}


// ── Task 9: Retry wrapper — up to maxRetries attempts ────────
bool sendWithRetry(String level, String eventType, String rainStatus,
                   float waterLevel, bool vibration, String message,
                   int maxRetries = 3) {
  for (int attempt = 1; attempt <= maxRetries; attempt++) {
    Serial.print("[Alert] Attempt ");
    Serial.print(attempt);
    Serial.print("/");
    Serial.println(maxRetries);

    if (sendAlert(level, eventType, rainStatus, waterLevel, vibration, message)) {
      return true;  // success — stop retrying
    }

    if (attempt < maxRetries) {
      Serial.println("[Alert] Retrying in 2s...");
      delay(2000);
    }
  }
  Serial.println("[Alert] All retry attempts exhausted. Alert may not have been received.");
  // Visual indicator: alternate-blink both LEDs 5 times to signal failed transmission
  for (int i = 0; i < 5; i++) {
    digitalWrite(PIN_LED_RED,    HIGH);
    digitalWrite(PIN_LED_YELLOW, LOW);
    delay(200);
    digitalWrite(PIN_LED_RED,    LOW);
    digitalWrite(PIN_LED_YELLOW, HIGH);
    delay(200);
  }
  digitalWrite(PIN_LED_RED,    LOW);
  digitalWrite(PIN_LED_YELLOW, LOW);
  return false;
}
