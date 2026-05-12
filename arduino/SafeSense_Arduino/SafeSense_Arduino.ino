/*
 * ============================================================
 *  SafeSense IoT — Arduino Uno Sensor & Alert Controller
 *  Board  : Arduino Uno (ATmega328P)
 *
 *  ALERT BEHAVIOR:
 *    GREEN  (solid)  → System ON, all clear
 *    YELLOW (solid)  → Rain OR water level detected only
 *    RED    (solid)  → BOTH rain AND water level detected
 *    RED    (blink)  → Vibration/accident detected → SMS + IoT + camera
 *
 *  SENSOR ROLES:
 *    Rain Sensor (D2)      → Flood warning → YELLOW (only rain) / RED (rain + water)
 *    Water Level (A0)      → Flood warning → YELLOW (only water) / RED (rain + water)
 *    Vibration Sensor (D3) → Accident/crash → RED blink + SMS alert
 *
 * ── HARDWARE CONNECTIONS ──
 *
 *  Sensors:
 *    Water Level Sensor → A0 (analog)
 *    Rain Sensor (DO)   → D2 (digital, LOW = rain, INPUT_PULLUP)
 *    Vibration Sensor   → D3 (digital, HIGH = hit, INT1)
 *
 *  LEDs — Two-Lane (3 LEDs x 2 lanes = 6 total):
 *    Lane 1 Green  → D4   Lane 2 Green  → D7
 *    Lane 1 Yellow → D5   Lane 2 Yellow → D8
 *    Lane 1 Red    → D6   Lane 2 Red    → D9
 *
 *  SIM900A GSM: TX→D10 (RX), RX→D11 (TX)
 *  Buzzer: D12
 *  ESP32-S3: Arduino TX(D1) → GPIO44, Arduino RX(D0) → GPIO43
 * ============================================================
 */

#include <SoftwareSerial.h>
#include <avr/wdt.h>

// ══════════════════════════════════════════════════════════════
//  CONFIGURATION
// ══════════════════════════════════════════════════════════════

const char* SMS_NUMBERS[] = {
  "639709126550",  // Emergency Contact 1

};
const int SMS_COUNT = sizeof(SMS_NUMBERS) / sizeof(SMS_NUMBERS[0]);

const char* DEVICE_ID     = "SAFESENSE-001";
const char* LOCATION_NAME = "Brgy. Crossing Palkan, Tupi";

// ── Water Level Thresholds (analog 0–1023) ───────────────────
// Sensor noise floor (dry air): ~64-66
// WATER_LEVEL_WARNING: reading must EXCEED this to trigger YELLOW
// WATER_LEVEL_SAFE: reading must DROP BELOW this to return to GREEN
// The gap between them (hysteresis) prevents flickering at the boundary.
const int WATER_LEVEL_SAFE    = 68;   // Drop below this → back to GREEN (just above dry noise of 64-66)
const int WATER_LEVEL_WARNING = 75;   // Exceed this → YELLOW (low threshold — easy to trigger)

// ── Vibration (accident detection) ───────────────────────────
// VIBRATION_TRIGGER hits within VIBRATION_WINDOW → RED + SMS
const int            VIBRATION_TRIGGER = 2;     // 2 confirmed hits = accident
const unsigned long  VIBRATION_WINDOW  = 5000;  // within 5 seconds

// ── Alert Hold Hysteresis ─────────────────────────────────────
// How long a state must be consistently detected before it changes.
// Prevents false triggers from brief sensor contact or noise.
const unsigned long STATE_CONFIRM_MS = 1500;  // must hold for 1.5s before state changes
const unsigned long ALERT_HOLD_MS    = 10000; // RED stays on 10s after accident clears

// ── Buzzer ────────────────────────────────────────────────────
const bool BUZZER_ENABLED = true;

// ── Timing ────────────────────────────────────────────────────
const unsigned long SENSOR_READ_INTERVAL = 200;    // 200 ms polling
const unsigned long SMS_COOLDOWN_MS      = 300000; // 5 min between SMS
const unsigned long ESP32_SEND_INTERVAL  = 2000;
const unsigned long HEARTBEAT_INTERVAL   = 300000;
const unsigned long LED_BLINK_MS         = 300;    // RED blink speed

// ── Rain sensor polarity ──────────────────────────────────────
// LM393 module: LOW = rain. Change to HIGH if yours is inverted.
const int RAIN_ACTIVE_LEVEL = LOW;


// ══════════════════════════════════════════════════════════════
//  ALERT STATES
//  0 = SAFE     → GREEN solid
//  1 = FLOOD    → YELLOW solid  (rain OR water level OR both)
//  2 = ACCIDENT → RED blinking  (vibration → SMS + IoT + camera)
// ══════════════════════════════════════════════════════════════

#define STATE_SAFE     0
#define STATE_FLOOD    1
#define STATE_ACCIDENT 2


// ══════════════════════════════════════════════════════════════
//  PIN DEFINITIONS
// ══════════════════════════════════════════════════════════════

const int PIN_WATER_LEVEL   = A0;
const int PIN_RAIN_DIGITAL  = 2;
const int PIN_VIBRATION     = 3;  // INT1

const int PIN_LED_L1_GREEN  = 4;   // D4 — Lane 1 Green
const int PIN_LED_L1_YELLOW = 5;   // D5 — Lane 1 Yellow
const int PIN_LED_L1_RED    = 6;   // D6 — Lane 1 Red
const int PIN_LED_L2_GREEN  = 7;   // D7 — Lane 2 Green
const int PIN_LED_L2_YELLOW = 8;   // D8 — Lane 2 Yellow
const int PIN_LED_L2_RED    = 9;   // D9 — Lane 2 Red

const int PIN_BUZZER = 12;
const int PIN_GSM_RX = 10;
const int PIN_GSM_TX = 11;


// ══════════════════════════════════════════════════════════════
//  VIBRATION INTERRUPT
// ══════════════════════════════════════════════════════════════

volatile int           vibCount      = 0;
volatile unsigned long vibFirstHitMs = 0;
volatile unsigned long vibLastHitMs  = 0;

void vibrationISR() {
  unsigned long now = millis();
  if (now - vibLastHitMs < 50) return;  // 50 ms debounce
  vibLastHitMs = now;

  if (vibCount == 0) vibFirstHitMs = now;

  if (now - vibFirstHitMs <= VIBRATION_WINDOW) {
    vibCount++;
  } else {
    vibCount      = 1;
    vibFirstHitMs = now;
  }
}

void resetVibration() {
  noInterrupts();
  vibCount      = 0;
  vibFirstHitMs = 0;
  interrupts();
}


// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

SoftwareSerial gsmSerial(PIN_GSM_RX, PIN_GSM_TX);

unsigned long lastSensorRead  = 0;
unsigned long lastEsp32Send   = 0;
unsigned long lastHeartbeat   = 0;
unsigned long lastSmsTime     = (unsigned long)(0UL - SMS_COOLDOWN_MS);
unsigned long lastLedToggle   = 0;
unsigned long accidentSetTime = 0;

int   waterLevelRaw = 0;
float waterLevelPct = 0.0;
bool  isRaining     = false;

int  alertState   = STATE_SAFE;
int  prevState    = STATE_SAFE;
bool ledBlinkOn   = false;
bool gsmReady     = false;

// State confirmation — a candidate state must hold for STATE_CONFIRM_MS before applying
int           pendingState       = STATE_SAFE;
unsigned long pendingStateStart  = 0;

unsigned long lastBuzzerToggle = 0;
bool buzzerOn = false;


// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  wdt_enable(WDTO_8S);

  Serial.begin(9600);
  gsmSerial.begin(9600);

  pinMode(PIN_WATER_LEVEL,   INPUT);
  pinMode(PIN_RAIN_DIGITAL,  INPUT_PULLUP);
  pinMode(PIN_VIBRATION,     INPUT);

  pinMode(PIN_LED_L1_GREEN,  OUTPUT);
  pinMode(PIN_LED_L1_YELLOW, OUTPUT);
  pinMode(PIN_LED_L1_RED,    OUTPUT);
  pinMode(PIN_LED_L2_GREEN,  OUTPUT);
  pinMode(PIN_LED_L2_YELLOW, OUTPUT);
  pinMode(PIN_LED_L2_RED,    OUTPUT);

  if (BUZZER_ENABLED) {
    pinMode(PIN_BUZZER, OUTPUT);
    tone(PIN_BUZZER, 1000, 150);
  }

  attachInterrupt(digitalPinToInterrupt(PIN_VIBRATION), vibrationISR, RISING);

  // Boot: flash all LEDs once, then GREEN only = system ready
  setAllLEDs(HIGH, HIGH, HIGH, HIGH, HIGH, HIGH);
  delay(400);
  setAllLEDs(LOW, LOW, LOW, LOW, LOW, LOW);
  delay(200);
  setAllLEDs(HIGH, LOW, LOW, HIGH, LOW, LOW);  // GREEN on

  initGSM();
  Serial.println("$SAFE,BOOT,0,0,0,0");
  wdt_reset();
}


// ══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ══════════════════════════════════════════════════════════════

void loop() {
  wdt_reset();
  unsigned long now = millis();

  if (timeSince(now, lastSensorRead) >= SENSOR_READ_INTERVAL) {
    lastSensorRead = now;
    readSensors();
    evaluateState(now);
  }

  updateLEDs(now);
  updateBuzzer(now);

  // Fire alert only on state change
  if (alertState != prevState) {
    if (alertState == STATE_ACCIDENT) triggerAccidentAlert(now);
    else if (alertState == STATE_FLOOD) triggerFloodAlert(now);
    prevState = alertState;
  }

  if (timeSince(now, lastEsp32Send) >= ESP32_SEND_INTERVAL) {
    lastEsp32Send = now;
    sendToESP32();
  }

  if (timeSince(now, lastHeartbeat) >= HEARTBEAT_INTERVAL) {
    lastHeartbeat = now;
    Serial.println("$SAFE,HEARTBEAT,0,0,0,0");
  }
}


// ══════════════════════════════════════════════════════════════
//  SENSOR READING
// ══════════════════════════════════════════════════════════════

void readSensors() {
  long sum = 0;
  for (int i = 0; i < 5; i++) { sum += analogRead(PIN_WATER_LEVEL); delay(2); }
  waterLevelRaw = sum / 5;
  waterLevelPct = constrain(map(waterLevelRaw, 0, 1023, 0, 100), 0, 100);

  isRaining = (digitalRead(PIN_RAIN_DIGITAL) == RAIN_ACTIVE_LEVEL);

  // Expire stale vibration window
  noInterrupts();
  unsigned long firstHit = vibFirstHitMs;
  int           vc       = vibCount;
  interrupts();
  if (vc > 0 && timeSince(millis(), firstHit) > VIBRATION_WINDOW * 2) {
    resetVibration();
  }
}


// ══════════════════════════════════════════════════════════════
//  STATE EVALUATION
//
//  Priority (highest wins):
//    STATE_ACCIDENT      — vibration threshold met → RED blink + SMS
//    STATE_CRITICAL_FLOOD— rain AND water level     → RED solid
//    STATE_FLOOD         — rain OR water level only → YELLOW
//    STATE_SAFE          — nothing detected          → GREEN
//
//  ACCIDENT holds for ALERT_HOLD_MS so camera has time to capture
// ══════════════════════════════════════════════════════════════

void evaluateState(unsigned long now) {
  noInterrupts();
  int vc = vibCount;
  interrupts();

  // ── Determine what the sensors are saying right now ──────
  int targetState;

  if (vc >= VIBRATION_TRIGGER) {
    targetState = STATE_ACCIDENT;
    resetVibration();
  } else if (alertState == STATE_ACCIDENT) {
    // Hold RED until ALERT_HOLD_MS expires, then re-evaluate flood/safe
    if (timeSince(now, accidentSetTime) < ALERT_HOLD_MS) return;
    // Fall through to flood/safe check
    bool floodNow = isRaining || (waterLevelRaw >= WATER_LEVEL_WARNING);
    targetState = floodNow ? STATE_FLOOD : STATE_SAFE;
  } else {
    // Hysteresis: once in FLOOD, need to drop below WATER_LEVEL_SAFE to clear
    bool floodNow;
    if (alertState == STATE_FLOOD) {
      floodNow = isRaining || (waterLevelRaw >= WATER_LEVEL_SAFE);
    } else {
      floodNow = isRaining || (waterLevelRaw >= WATER_LEVEL_WARNING);
    }
    targetState = floodNow ? STATE_FLOOD : STATE_SAFE;
  }

  // ── Confirmation debounce ─────────────────────────────────
  // Accident bypasses debounce — immediate response for safety
  if (targetState == STATE_ACCIDENT) {
    alertState      = STATE_ACCIDENT;
    accidentSetTime = now;
    pendingState    = STATE_ACCIDENT;
    pendingStateStart = now;
    return;
  }

  // For flood/safe: only change after holding consistently for STATE_CONFIRM_MS
  if (targetState != pendingState) {
    // New candidate — start the confirmation timer
    pendingState      = targetState;
    pendingStateStart = now;
  } else if (targetState != alertState) {
    // Same candidate — check if it's held long enough
    if (timeSince(now, pendingStateStart) >= STATE_CONFIRM_MS) {
      alertState = targetState;
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  LED CONTROL
// ══════════════════════════════════════════════════════════════

void setAllLEDs(int l1g, int l1y, int l1r,
                int l2g, int l2y, int l2r) {
  digitalWrite(PIN_LED_L1_GREEN,  l1g);
  digitalWrite(PIN_LED_L1_YELLOW, l1y);
  digitalWrite(PIN_LED_L1_RED,    l1r);
  digitalWrite(PIN_LED_L2_GREEN,  l2g);
  digitalWrite(PIN_LED_L2_YELLOW, l2y);
  digitalWrite(PIN_LED_L2_RED,    l2r);
}

void updateLEDs(unsigned long now) {
  switch (alertState) {

    case STATE_SAFE:
      // GREEN solid — all others off
      setAllLEDs(HIGH, LOW, LOW, HIGH, LOW, LOW);
      ledBlinkOn = false;
      break;

    case STATE_FLOOD:
      // YELLOW solid — all others off
      setAllLEDs(LOW, HIGH, LOW, LOW, HIGH, LOW);
      ledBlinkOn = false;
      break;

    case STATE_ACCIDENT:
      // RED blinking — all others always off
      setAllLEDs(LOW, LOW, LOW, LOW, LOW, LOW);
      if (timeSince(now, lastLedToggle) >= LED_BLINK_MS) {
        lastLedToggle = now;
        ledBlinkOn    = !ledBlinkOn;
      }
      digitalWrite(PIN_LED_L1_RED, ledBlinkOn ? HIGH : LOW);
      digitalWrite(PIN_LED_L2_RED, ledBlinkOn ? HIGH : LOW);
      break;
  }
}


// ══════════════════════════════════════════════════════════════
//  BUZZER
// ══════════════════════════════════════════════════════════════

void updateBuzzer(unsigned long now) {
  if (!BUZZER_ENABLED) return;
  if (alertState == STATE_ACCIDENT) {
    if (timeSince(now, lastBuzzerToggle) >= 400) {
      lastBuzzerToggle = now;
      buzzerOn = !buzzerOn;
      if (buzzerOn) tone(PIN_BUZZER, 2000, 200);
      else          noTone(PIN_BUZZER);
    }
  } else {
    if (buzzerOn) { noTone(PIN_BUZZER); buzzerOn = false; }
  }
}


// ══════════════════════════════════════════════════════════════
//  ALERT TRIGGERING
// ══════════════════════════════════════════════════════════════

void triggerAccidentAlert(unsigned long now) {
  String message = "CRITICAL: ACCIDENT DETECTED — Possible vehicle crash. ";
  message += "Water: " + String(waterLevelPct, 1) + "%. ";
  message += "Rain: " + String(isRaining ? "Yes" : "No") + ". ";
  message += "Camera capturing for verification. ";
  message += "Location: " + String(LOCATION_NAME);

  // Send as CRITICAL/accident so the IoT modal shows the correct alert type
  Serial.print("$SAFE,ALERT,critical,accident,");
  Serial.print(waterLevelPct, 1);
  Serial.print(",1|");
  Serial.println(message);

  // SMS to emergency contacts
  if (timeSince(now, lastSmsTime) >= SMS_COOLDOWN_MS) {
    sendSMS(message);
    lastSmsTime = now;
  }
}

void triggerFloodAlert(unsigned long now) {
  // Rain only → no alert, just LED (already handled by LED state)
  // Water level detected (with or without rain) → critical flood alert + SMS
  if (waterLevelRaw < WATER_LEVEL_WARNING && !isRaining) return;

  // Only send alert if water level is actually triggered
  // Rain alone does NOT send an alert — only lights up YELLOW
  if (waterLevelRaw < WATER_LEVEL_WARNING) return;

  String message = "CRITICAL FLOOD WARNING: ";
  if (isRaining && waterLevelRaw >= WATER_LEVEL_WARNING) {
    message += "Rain and rising water detected.";
  } else {
    message += "Rising water level detected.";
  }
  message += " Water: " + String(waterLevelPct, 1) + "%.";
  message += " Location: " + String(LOCATION_NAME);

  // critical level → RED in IoT modal
  Serial.print("$SAFE,ALERT,critical,flood,");
  Serial.print(waterLevelPct, 1);
  Serial.print(",0|");
  Serial.println(message);

  // SMS for flood when water level is triggered
  if (timeSince(now, lastSmsTime) >= SMS_COOLDOWN_MS) {
    sendSMS(message);
    lastSmsTime = now;
  }
}

// ══════════════════════════════════════════════════════════════
//  ESP32-S3 SERIAL COMMUNICATION
// ══════════════════════════════════════════════════════════════

void sendToESP32() {
  noInterrupts();
  int vc = vibCount;
  interrupts();

  Serial.print("$SAFE,DATA,");
  Serial.print(isRaining ? 1 : 0);
  Serial.print(",");
  Serial.print(waterLevelRaw);
  Serial.print(",");
  Serial.print(vc);
  Serial.print(",");
  Serial.println(alertState);
}


// ══════════════════════════════════════════════════════════════
//  SIM900A GSM
// ══════════════════════════════════════════════════════════════

void initGSM() {
  delay(1000);
  gsmSerial.println("AT");
  delay(1000);
  gsmReady = (gsmReadResponse().indexOf("OK") >= 0);
  if (!gsmReady) {
    gsmSerial.println("AT");
    delay(2000);
    gsmReady = (gsmReadResponse().indexOf("OK") >= 0);
  }
  if (gsmReady) {
    gsmSerial.println("AT+CMGF=1");       delay(500); gsmReadResponse();
    gsmSerial.println("AT+CSCS=\"GSM\""); delay(500); gsmReadResponse();
    gsmSerial.println("AT+CPIN?");        delay(500); gsmReadResponse();
    gsmSerial.println("AT+CREG?");        delay(500); gsmReadResponse();
    gsmSerial.println("AT+CSQ");          delay(500); gsmReadResponse();
  }
}

void sendSMS(String message) {
  if (!gsmReady) { initGSM(); if (!gsmReady) return; }

  String smsBody = "[SafeSense " + String(DEVICE_ID) + "] " + message;
  if (smsBody.length() > 160) smsBody = smsBody.substring(0, 157) + "...";

  for (int i = 0; i < SMS_COUNT; i++) {
    wdt_reset();
    // SIM900A requires international format with + prefix: "+639XXXXXXXXX"
    gsmSerial.print("AT+CMGS=\"+");
    gsmSerial.print(SMS_NUMBERS[i]);
    gsmSerial.println("\"");
    delay(1500);  // Give SIM900A time to show the > prompt
    String prompt = gsmReadResponse();
    if (prompt.indexOf(">") >= 0) {
      gsmSerial.print(smsBody);
      delay(100);
      gsmSerial.write(26);  // Ctrl+Z to send
      delay(6000);          // Wait for send confirmation
      gsmReadResponse();
    }
    delay(2000);
    wdt_reset();
  }
}

String gsmReadResponse() {
  String response = "";
  unsigned long start = millis();
  while (timeSince(millis(), start) < 3000) {
    wdt_reset();
    if (gsmSerial.available()) {
      char c = gsmSerial.read();
      response += c;
      if (response.indexOf("OK")    >= 0 || response.indexOf("ERROR") >= 0 ||
          response.indexOf(">")     >= 0 || response.indexOf("+CMGS:") >= 0) {
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
