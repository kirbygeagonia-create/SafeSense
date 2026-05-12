/*
 * ============================================================
 *  SafeSense IoT — Arduino Uno Sensor & Alert Controller
 *  Board  : Arduino Uno (ATmega328P)
 *
 *  ALERT BEHAVIOR:
 *    GREEN  (solid)  → System ON, all clear
 *    YELLOW (solid)  → Rain or water level detected (flood warning)
 *    RED    (blink)  → Vibration/accident detected → SMS + IoT + camera
 *
 *  SENSOR ROLES:
 *    Rain Sensor (D2)      → Flood warning → YELLOW
 *    Water Level (A0)      → Flood warning → YELLOW
 *    Vibration Sensor (D3) → Accident/crash → RED + SMS alert
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
  "639363195187",  // Emergency Contact 1

};
const int SMS_COUNT = sizeof(SMS_NUMBERS) / sizeof(SMS_NUMBERS[0]);

const char* DEVICE_ID     = "SAFESENSE-001";
const char* LOCATION_NAME = "Brgy. Crossing Palkan, Tupi";

// ── Water Level Thresholds (analog 0–1023) ───────────────────
// Your sensor reads 64-66 in dry air (noise floor).
// Set WATER_LEVEL_WARNING above the noise floor.
// Submerge 1 cm and note the reading, then set WARNING ~10 below that.
const int WATER_LEVEL_SAFE    = 70;   // Below → dry/safe (above noise floor of 64-66)
const int WATER_LEVEL_WARNING = 80;   // At or above → YELLOW (adjust after testing with water)

// ── Vibration (accident detection) ───────────────────────────
// VIBRATION_TRIGGER hits within VIBRATION_WINDOW → RED + SMS
const int            VIBRATION_TRIGGER = 2;     // 2 confirmed hits = accident
const unsigned long  VIBRATION_WINDOW  = 5000;  // within 5 seconds

// ── RED hold after accident ───────────────────────────────────
// Keeps RED on long enough for camera to capture the scene
const unsigned long ALERT_HOLD_MS = 10000;  // 10 seconds

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
//  1 = FLOOD    → YELLOW solid  (rain or water level)
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

const int PIN_LED_L1_GREEN  = 4;
const int PIN_LED_L1_YELLOW = 5;
const int PIN_LED_L1_RED    = 6;
const int PIN_LED_L2_GREEN  = 7;
const int PIN_LED_L2_YELLOW = 8;
const int PIN_LED_L2_RED    = 9;

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
//    STATE_ACCIDENT — vibration threshold met → RED + SMS
//    STATE_FLOOD    — rain OR water level     → YELLOW
//    STATE_SAFE     — nothing detected        → GREEN
//
//  ACCIDENT holds for ALERT_HOLD_MS so camera has time to capture
// ══════════════════════════════════════════════════════════════

void evaluateState(unsigned long now) {
  noInterrupts();
  int vc = vibCount;
  interrupts();

  // Accident check — highest priority
  if (vc >= VIBRATION_TRIGGER) {
    alertState      = STATE_ACCIDENT;
    accidentSetTime = now;
    resetVibration();
    return;
  }

  // Hold RED during camera capture window
  if (alertState == STATE_ACCIDENT) {
    if (timeSince(now, accidentSetTime) < ALERT_HOLD_MS) return;
    // Hold expired — fall through to re-evaluate
  }

  // Flood check
  if (isRaining || waterLevelRaw >= WATER_LEVEL_WARNING) {
    alertState = STATE_FLOOD;
  } else {
    alertState = STATE_SAFE;
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
      setAllLEDs(HIGH, LOW, LOW, HIGH, LOW, LOW);
      ledBlinkOn = false;
      break;

    case STATE_FLOOD:
      setAllLEDs(LOW, HIGH, LOW, LOW, HIGH, LOW);
      ledBlinkOn = false;
      break;

    case STATE_ACCIDENT:
      // All off first — guarantees green and yellow never bleed through
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
  String message = "ACCIDENT DETECTED: Possible vehicle crash. ";
  message += "Water: " + String(waterLevelPct, 1) + "%. ";
  message += "Rain: " + String(isRaining ? "Yes" : "No") + ". ";
  message += "Camera capturing for verification. ";
  message += "Location: " + String(LOCATION_NAME);

  // Notify ESP32 → IoT dashboard + triggers camera capture
  Serial.print("$SAFE,ALERT,danger,accident,");
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
  String message = "FLOOD WARNING: ";
  if (isRaining && waterLevelRaw >= WATER_LEVEL_WARNING) {
    message += "Rain and rising water detected.";
  } else if (isRaining) {
    message += "Rain detected.";
  } else {
    message += "Rising water level detected.";
  }
  message += " Water: " + String(waterLevelPct, 1) + "%.";
  message += " Location: " + String(LOCATION_NAME);

  // Notify ESP32 → IoT dashboard only (no SMS for flood warning)
  Serial.print("$SAFE,ALERT,warning,flood,");
  Serial.print(waterLevelPct, 1);
  Serial.print(",0|");
  Serial.println(message);
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
    gsmSerial.print("AT+CMGS=\"");
    gsmSerial.print(SMS_NUMBERS[i]);
    gsmSerial.println("\"");
    delay(1000);
    if (gsmReadResponse().indexOf(">") >= 0) {
      gsmSerial.print(smsBody);
      gsmSerial.write(26);
      delay(5000);
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
