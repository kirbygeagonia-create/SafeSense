/*
 * ============================================================
 *  SafeSense IoT — Arduino Uno Sensor & Alert Controller
 *  Board  : Arduino Uno (ATmega328P)
 *
 *  This sketch runs on the Arduino Uno in the dual-MCU
 *  SafeSense architecture:
 *
 *    Arduino Uno  ←→  ESP32-CAM
 *    (sensors,        (WiFi HTTP,
 *     LEDs,            JSON POST)
 *     GSM SMS)
 *
 *  The Arduino reads all sensors, controls LEDs, sends SMS
 *  via SIM900A GSM, and forwards sensor data to the ESP32-CAM
 *  over Hardware Serial for WiFi transmission.
 *
 *  ── HARDWARE CONNECTIONS ──
 *
 *  Sensors:
 *    Water Level Sensor  → A0 (analog, resistive)
 *    Rain Sensor (DO)    → D3 (digital, LOW = rain)
 *    Vibration Sensor    → D2 (digital, HIGH = vibration)
 *
 *  Outputs:
 *    LED 1 (Green/Safe)  → D10 (via 220Ω resistor)
 *    LED 2 (Yellow/Warn) → D11 (via 220Ω resistor)
 *    LED 3 (Red/Danger)  → D12 (via 220Ω resistor)
 *
 *  SIM900A GSM Module:
 *    SIM900A TX → D7 (Arduino SoftSerial RX)
 *    SIM900A RX → D8 (Arduino SoftSerial TX)
 *    SIM900A VCC → 4V external regulator (LM2596)
 *    SIM900A GND → Common GND
 *
 *  ESP32-CAM Serial Bridge:
 *    Arduino TX (D1) → ESP32-CAM U0R (RX)
 *    Arduino RX (D0) → ESP32-CAM U0T (TX)
 *    (Uses Hardware Serial — avoid Serial.print debug
 *     when ESP32 is connected; use SoftSerial for debug)
 *
 *  Power:
 *    Battery → LM2596 Buck Converter
 *      Output 5V  → Arduino VIN + ESP32-CAM VCC
 *      Output ~4V → SIM900A VCC (separate regulator)
 *    ALL GROUNDS CONNECTED
 *
 *  Required Libraries:
 *    - SoftwareSerial (built-in)
 *    - avr/wdt.h (built-in, for watchdog timer)
 *
 * ============================================================
 */

#include <SoftwareSerial.h>
#include <avr/wdt.h>

// ══════════════════════════════════════════════════════════════
//  CONFIGURATION
// ══════════════════════════════════════════════════════════════

// ── SMS Recipients (add your numbers here) ───────────────────
// Use international format WITHOUT the '+' sign
// Example: "639171234567" for a Philippine number
const char* SMS_NUMBERS[] = {
  "639XXXXXXXXX",   // Emergency Contact 1
  "639XXXXXXXXX",   // Emergency Contact 2 (Police/Rescue)
};
const int SMS_COUNT = sizeof(SMS_NUMBERS) / sizeof(SMS_NUMBERS[0]);

// ── Device Identity ──────────────────────────────────────────
const char* DEVICE_ID     = "SAFESENSE-001";
const char* LOCATION_NAME = "Brgy. Casisang, Malaybalay City";

// ── Sensor Thresholds ────────────────────────────────────────
// Water level sensor (analog 0–1023)
// The resistive water level sensor outputs higher values when
// more of the sensor is submerged. Calibrate these for your
// specific sensor and mounting.
const int WATER_LEVEL_SAFE     = 200;   // Below this = dry/safe
const int WATER_LEVEL_WARNING  = 400;   // Yellow LED territory
const int WATER_LEVEL_DANGER   = 600;   // Red LED slow blink
const int WATER_LEVEL_CRITICAL = 800;   // Red LED fast blink + alert

// Vibration confirmation: how many detections within the
// confirmation window to count as a real event
const int  VIBRATION_CONFIRM_COUNT   = 3;
const unsigned long VIBRATION_WINDOW = 10000;  // 10 seconds

// ── Buzzer (optional) ────────────────────────────────────────
// Set to true if you have a piezo buzzer connected to D9
// If you don't have a buzzer yet, set to false — no errors
const bool BUZZER_ENABLED = false;   // Change to true when buzzer is wired

// ── Timing ───────────────────────────────────────────────────
const unsigned long SENSOR_READ_INTERVAL  = 2000;   // Read sensors every 2s
const unsigned long ALERT_COOLDOWN_MS     = 60000;  // 60s between alerts
const unsigned long ESP32_SEND_INTERVAL   = 3000;   // Send data to ESP32 every 3s
const unsigned long SMS_COOLDOWN_MS       = 300000;  // 5 min between SMS (expensive)
const unsigned long HEARTBEAT_INTERVAL    = 300000;  // 5 min heartbeat to ESP32
const unsigned long LED_FAST_BLINK_MS     = 150;
const unsigned long LED_SLOW_BLINK_MS     = 500;
const unsigned long BUZZER_BEEP_DURATION  = 200;     // ms per beep
const unsigned long BUZZER_BEEP_INTERVAL  = 500;     // ms between beeps


// ══════════════════════════════════════════════════════════════
//  PIN DEFINITIONS
// ══════════════════════════════════════════════════════════════

// Sensors
const int PIN_WATER_LEVEL   = A0;  // Analog — resistive water level sensor
const int PIN_VIBRATION     = 2;   // Digital — vibration sensor OUT
const int PIN_RAIN_DIGITAL  = 3;   // Digital — rain sensor DO (LOW = rain)

// LEDs (with 220Ω resistors to GND)
const int PIN_LED_GREEN     = 10;  // Safe / Power indicator
const int PIN_LED_YELLOW    = 11;  // Warning
const int PIN_LED_RED       = 12;  // Danger / Critical

// Buzzer (optional)
const int PIN_BUZZER        = 9;   // Piezo buzzer (via 100Ω resistor)

// SIM900A GSM (SoftwareSerial)
const int PIN_GSM_RX        = 7;   // Arduino receives FROM SIM900A TX
const int PIN_GSM_TX        = 8;   // Arduino sends TO SIM900A RX


// ══════════════════════════════════════════════════════════════
//  GLOBALS
// ══════════════════════════════════════════════════════════════

SoftwareSerial gsmSerial(PIN_GSM_RX, PIN_GSM_TX);

// Timing (all use millis() — overflow-safe comparisons)
unsigned long lastSensorRead   = 0;
unsigned long lastAlertTime    = 0;
// FIX BUG-NEW-1: unsigned underflow makes cooldown appear already expired
// at boot so the very first SMS is never skipped.
unsigned long lastSmsTime      = (unsigned long)(0UL - SMS_COOLDOWN_MS);
unsigned long lastEsp32Send    = 0;
unsigned long lastHeartbeat    = 0;
unsigned long lastLedToggle    = 0;

// Sensor state
int   waterLevelRaw    = 0;
float waterLevelPct    = 0.0;   // 0–100%
bool  isRaining        = false;
bool  prevRaining      = false;
bool  vibDetected      = false;

// Vibration confirmation buffer
int           vibrationCount     = 0;
unsigned long vibrationFirstTime = 0;

// LED state (non-blocking)
bool ledRedState    = false;
bool ledYellowState = false;

// Alert level tracking
// 0 = safe, 1 = warning, 2 = danger, 3 = critical
int currentAlertLevel = 0;
int prevAlertLevel    = 0;

// GSM ready flag
bool gsmReady = false;

// Buzzer state
unsigned long lastBuzzerToggle = 0;
bool buzzerOn = false;


// ══════════════════════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════════════════════

void setup() {
  // Enable watchdog timer — 8 second timeout
  wdt_enable(WDTO_8S);

  // Hardware Serial — used to communicate with ESP32-CAM
  // Baud rate must match ESP32-CAM sketch
  Serial.begin(9600);

  // SoftwareSerial for SIM900A GSM
  gsmSerial.begin(9600);

  // Pin modes
  pinMode(PIN_WATER_LEVEL,  INPUT);
  pinMode(PIN_VIBRATION,    INPUT);
  pinMode(PIN_RAIN_DIGITAL, INPUT);
  pinMode(PIN_LED_GREEN,    OUTPUT);
  pinMode(PIN_LED_YELLOW,   OUTPUT);
  pinMode(PIN_LED_RED,      OUTPUT);
  if (BUZZER_ENABLED) {
    pinMode(PIN_BUZZER, OUTPUT);
    // Short beep on boot to confirm buzzer works
    tone(PIN_BUZZER, 1000, 100);
  }

  // Startup LED sequence (all on briefly, then green only)
  digitalWrite(PIN_LED_GREEN,  HIGH);
  digitalWrite(PIN_LED_YELLOW, HIGH);
  digitalWrite(PIN_LED_RED,    HIGH);
  delay(500);
  digitalWrite(PIN_LED_YELLOW, LOW);
  digitalWrite(PIN_LED_RED,    LOW);
  // Green stays on = system powered

  // Initialize GSM module
  initGSM();

  // Send boot message to ESP32
  Serial.println("$SAFE,BOOT,0,0,0,0");

  wdt_reset();
}


// ══════════════════════════════════════════════════════════════
//  MAIN LOOP (non-blocking)
// ══════════════════════════════════════════════════════════════

void loop() {
  wdt_reset();  // Pet the watchdog

  unsigned long now = millis();

  // ── Read sensors at interval ───────────────────────────────
  if (timeSince(now, lastSensorRead) >= SENSOR_READ_INTERVAL) {
    lastSensorRead = now;
    readSensors();
    evaluateAlertLevel();
  }

  // ── Update LEDs and buzzer (non-blocking) ─────────────────
  updateLEDs(now);
  updateBuzzer(now);

  // ── Alert logic ────────────────────────────────────────────
  if (currentAlertLevel > 0 && currentAlertLevel > prevAlertLevel) {
    // Alert level escalated — trigger alerts

    if (timeSince(now, lastAlertTime) >= ALERT_COOLDOWN_MS) {
      triggerAlert(now);
      lastAlertTime = now;
    }
  }
  prevAlertLevel = currentAlertLevel;

  // ── Send data to ESP32-CAM periodically ────────────────────
  if (timeSince(now, lastEsp32Send) >= ESP32_SEND_INTERVAL) {
    lastEsp32Send = now;
    sendToESP32();
  }

  // ── Heartbeat to ESP32 ─────────────────────────────────────
  if (timeSince(now, lastHeartbeat) >= HEARTBEAT_INTERVAL) {
    lastHeartbeat = now;
    Serial.println("$SAFE,HEARTBEAT,0,0,0,0");
  }

  prevRaining = isRaining;
}


// ══════════════════════════════════════════════════════════════
//  SENSOR READING
// ══════════════════════════════════════════════════════════════

void readSensors() {
  // ── Water Level (Analog) ────────────────────────────────
  // Resistive water level sensor: higher analog value = more water
  // Take average of 5 readings for stability
  long sum = 0;
  for (int i = 0; i < 5; i++) {
    sum += analogRead(PIN_WATER_LEVEL);
    delay(2);  // Short delay between ADC reads for stability
  }
  waterLevelRaw = sum / 5;
  waterLevelPct = map(waterLevelRaw, 0, 1023, 0, 100);
  waterLevelPct = constrain(waterLevelPct, 0.0, 100.0);

  // ── Rain (Digital) ──────────────────────────────────────
  // Most rain sensor modules: LOW = rain detected, HIGH = dry
  isRaining = (digitalRead(PIN_RAIN_DIGITAL) == LOW);

  // ── Vibration (Digital) ─────────────────────────────────
  vibDetected = (digitalRead(PIN_VIBRATION) == HIGH);

  // Vibration confirmation logic — require multiple detections
  // within a time window to confirm a real event
  if (vibDetected) {
    unsigned long now = millis();
    if (vibrationCount == 0) {
      vibrationFirstTime = now;
    }
    // Check if still within confirmation window
    if (timeSince(now, vibrationFirstTime) <= VIBRATION_WINDOW) {
      vibrationCount++;
    } else {
      // Window expired — restart
      vibrationCount = 1;
      vibrationFirstTime = now;
    }
  } else {
    // FIX BUG-A2: reset counter when window expires with no vibration.
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
  // Determine the highest applicable alert level

  // Check for confirmed vibration event (accident detection)
  bool vibrationConfirmed = (vibrationCount >= VIBRATION_CONFIRM_COUNT);

  if (waterLevelRaw >= WATER_LEVEL_CRITICAL && isRaining) {
    currentAlertLevel = 3;  // CRITICAL — flood
  }
  else if (vibrationConfirmed && (isRaining || waterLevelRaw >= WATER_LEVEL_WARNING)) {
    currentAlertLevel = 3;  // CRITICAL — accident during hazardous conditions
    // Reset vibration counter after confirmation
    vibrationCount = 0;
  }
  else if (waterLevelRaw >= WATER_LEVEL_DANGER && isRaining) {
    currentAlertLevel = 2;  // DANGER — rising flood
  }
  else if (waterLevelRaw >= WATER_LEVEL_WARNING || isRaining) {
    currentAlertLevel = 1;  // WARNING — rain or rising water
  }
  else {
    currentAlertLevel = 0;  // SAFE
  }
}


// ══════════════════════════════════════════════════════════════
//  LED CONTROL (non-blocking)
// ══════════════════════════════════════════════════════════════

void updateLEDs(unsigned long now) {
  switch (currentAlertLevel) {
    case 0:  // SAFE — solid green
      digitalWrite(PIN_LED_GREEN,  HIGH);
      digitalWrite(PIN_LED_YELLOW, LOW);
      digitalWrite(PIN_LED_RED,    LOW);
      break;

    case 1:  // WARNING — solid yellow, green off
      digitalWrite(PIN_LED_GREEN,  LOW);
      digitalWrite(PIN_LED_YELLOW, HIGH);
      digitalWrite(PIN_LED_RED,    LOW);
      break;

    case 2:  // DANGER — slow blink red, yellow solid
      digitalWrite(PIN_LED_GREEN,  LOW);
      digitalWrite(PIN_LED_YELLOW, HIGH);
      if (timeSince(now, lastLedToggle) >= LED_SLOW_BLINK_MS) {
        lastLedToggle = now;
        ledRedState = !ledRedState;
        digitalWrite(PIN_LED_RED, ledRedState ? HIGH : LOW);
      }
      break;

    case 3:  // CRITICAL — fast blink red + yellow alternating
      digitalWrite(PIN_LED_GREEN, LOW);
      if (timeSince(now, lastLedToggle) >= LED_FAST_BLINK_MS) {
        lastLedToggle = now;
        ledRedState = !ledRedState;
        digitalWrite(PIN_LED_RED,    ledRedState    ? HIGH : LOW);
        digitalWrite(PIN_LED_YELLOW, (!ledRedState) ? HIGH : LOW);
      }
      break;
  }
}


// ══════════════════════════════════════════════════════════════
//  BUZZER CONTROL (non-blocking, optional)
// ══════════════════════════════════════════════════════════════

void updateBuzzer(unsigned long now) {
  if (!BUZZER_ENABLED) return;

  if (currentAlertLevel >= 2) {
    // Beep pattern: DANGER = slow beep, CRITICAL = rapid beep
    unsigned long interval = (currentAlertLevel == 3)
      ? BUZZER_BEEP_INTERVAL / 2   // Fast beeping for critical
      : BUZZER_BEEP_INTERVAL;      // Normal beeping for danger

    if (timeSince(now, lastBuzzerToggle) >= interval) {
      lastBuzzerToggle = now;
      buzzerOn = !buzzerOn;
      if (buzzerOn) {
        tone(PIN_BUZZER, currentAlertLevel == 3 ? 2000 : 1000, BUZZER_BEEP_DURATION);
      } else {
        noTone(PIN_BUZZER);
      }
    }
  } else {
    // No alert — ensure buzzer is off
    if (buzzerOn) {
      noTone(PIN_BUZZER);
      buzzerOn = false;
    }
  }
}


// ══════════════════════════════════════════════════════════════
//  ALERT TRIGGERING
// ══════════════════════════════════════════════════════════════

void triggerAlert(unsigned long now) {
  // Build alert message
  String levelStr;
  String eventType;
  String message;

  switch (currentAlertLevel) {
    case 3:
      levelStr = "critical";
      if (vibrationCount >= VIBRATION_CONFIRM_COUNT) {
        eventType = "accident";
        message = "CRITICAL: Possible road accident detected via vibration sensor during ";
        message += isRaining ? "rain" : "flood";
        message += " event. Water level: ";
        message += String(waterLevelPct, 1);
        message += "%.";
      } else {
        eventType = "flood";
        message = "CRITICAL: Flood detected. Water level at ";
        message += String(waterLevelPct, 1);
        message += "% — DANGER threshold exceeded. Immediate response required!";
      }
      break;

    case 2:
      levelStr = "danger";
      eventType = "flood";
      message = "DANGER: Rising floodwater detected. Water level: ";
      message += String(waterLevelPct, 1);
      message += "%. Road hazard likely.";
      break;

    case 1:
      levelStr = "warning";
      eventType = isRaining ? "rain" : "flood";
      message = "WARNING: ";
      message += isRaining ? "Rain detected." : "Water level rising.";
      message += " Water level: ";
      message += String(waterLevelPct, 1);
      message += "%. Monitoring conditions.";
      break;

    default:
      return;  // No alert needed
  }

  // ── Send to ESP32-CAM (for WiFi HTTP POST) ──────────────
  sendAlertToESP32(levelStr, eventType, message);

  // ── Send SMS via GSM (with separate cooldown) ───────────
  if (currentAlertLevel >= 2 && timeSince(now, lastSmsTime) >= SMS_COOLDOWN_MS) {
    sendSMS(message);
    lastSmsTime = now;
  }
}


// ══════════════════════════════════════════════════════════════
//  ESP32-CAM SERIAL COMMUNICATION
// ══════════════════════════════════════════════════════════════

/*
 * Data protocol (Arduino → ESP32-CAM):
 *
 * Periodic sensor data:
 *   $SAFE,DATA,<rain>,<waterRaw>,<vibCount>,<alertLevel>\n
 *
 * Alert trigger:
 *   $SAFE,ALERT,<level>,<eventType>,<waterPct>,<vibration>|<message>\n
 *
 * Heartbeat:
 *   $SAFE,HEARTBEAT,0,0,0,0\n
 *
 * Boot notification:
 *   $SAFE,BOOT,0,0,0,0\n
 */

void sendToESP32() {
  // Periodic sensor data packet
  Serial.print("$SAFE,DATA,");
  Serial.print(isRaining ? 1 : 0);
  Serial.print(",");
  Serial.print(waterLevelRaw);
  Serial.print(",");
  Serial.print(vibrationCount);
  Serial.print(",");
  Serial.println(currentAlertLevel);
}

void sendAlertToESP32(String level, String eventType, String message) {
  Serial.print("$SAFE,ALERT,");
  Serial.print(level);
  Serial.print(",");
  Serial.print(eventType);
  Serial.print(",");
  Serial.print(waterLevelPct, 1);
  Serial.print(",");
  Serial.print(vibrationCount >= VIBRATION_CONFIRM_COUNT ? 1 : 0);
  Serial.print("|");
  Serial.println(message);
}


// ══════════════════════════════════════════════════════════════
//  SIM900A GSM — SMS FUNCTIONS
// ══════════════════════════════════════════════════════════════

void initGSM() {
  // Allow SIM900A to boot (needs ~3-5 seconds after power on)
  delay(1000);

  // Test AT communication
  gsmSerial.println("AT");
  delay(1000);
  if (gsmReadResponse().indexOf("OK") >= 0) {
    gsmReady = true;
  } else {
    // Retry once
    gsmSerial.println("AT");
    delay(2000);
    if (gsmReadResponse().indexOf("OK") >= 0) {
      gsmReady = true;
    }
  }

  if (gsmReady) {
    // Set SMS text mode
    gsmSerial.println("AT+CMGF=1");
    delay(500);
    gsmReadResponse();

    // Set character set to GSM default
    gsmSerial.println("AT+CSCS=\"GSM\"");
    delay(500);
    gsmReadResponse();

    // Check SIM card status
    gsmSerial.println("AT+CPIN?");
    delay(500);
    String simStatus = gsmReadResponse();
    if (simStatus.indexOf("READY") >= 0) {
      // SIM is ready
    }

    // Check network registration
    gsmSerial.println("AT+CREG?");
    delay(500);
    gsmReadResponse();

    // Signal quality check
    gsmSerial.println("AT+CSQ");
    delay(500);
    gsmReadResponse();
  }
}

void sendSMS(String message) {
  if (!gsmReady) {
    // Try to re-initialize
    initGSM();
    if (!gsmReady) return;
  }

  // Prepend device info to SMS
  String smsBody = "[SafeSense " + String(DEVICE_ID) + "] ";
  smsBody += message;
  smsBody += " | Location: ";
  smsBody += LOCATION_NAME;

  // Truncate to 160 chars (single SMS limit)
  if (smsBody.length() > 160) {
    smsBody = smsBody.substring(0, 157) + "...";
  }

  for (int i = 0; i < SMS_COUNT; i++) {
    wdt_reset();  // Reset watchdog during potentially long SMS operation

    gsmSerial.print("AT+CMGS=\"");
    gsmSerial.print(SMS_NUMBERS[i]);
    gsmSerial.println("\"");
    delay(1000);

    // Wait for '>' prompt
    String prompt = gsmReadResponse();
    // FIX BUG-A3: only send body when > prompt is confirmed.
    if (prompt.indexOf(">") >= 0) {
      gsmSerial.print(smsBody);
      gsmSerial.write(26);  // Ctrl+Z to send
      delay(5000);          // Wait for SMS to be sent

      String result = gsmReadResponse();
      if (result.indexOf("OK") >= 0) {
        // SMS sent successfully
      }
    }

    delay(2000);  // Pause between multiple SMS sends
    wdt_reset();
  }
}

String gsmReadResponse() {
  // FIX BUG-A1: wdt_reset() added — early exit stops spinning the full 3 s.
  String response = "";
  unsigned long start = millis();
  while (timeSince(millis(), start) < 3000) {
    wdt_reset();
    if (gsmSerial.available()) {
      char c = gsmSerial.read();
      response += c;
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
//  UTILITY FUNCTIONS
// ══════════════════════════════════════════════════════════════

/*
 * Overflow-safe time comparison.
 * Returns the elapsed milliseconds from 'start' to 'now',
 * correctly handling the millis() rollover at ~49 days.
 *
 * Because unsigned subtraction wraps naturally on overflow,
 * (now - start) always gives the correct elapsed time even
 * when millis() has rolled over, as long as the actual
 * elapsed time is less than ~49 days.
 */
unsigned long timeSince(unsigned long now, unsigned long start) {
  return now - start;  // Unsigned subtraction handles overflow
}
