#include <SoftwareSerial.h>
#include <avr/wdt.h>

// ═════════════════════ PINS ═════════════════════

const int PIN_RAIN      = 2;
const int PIN_VIBRATION = 3;
const int PIN_WATER     = A0;

// LEDs
const int G1 = 8;
const int G2 = 7;
const int Y1 = 6;
const int Y2 = 5;
const int R1 = 9;
const int R2 = 4;

// GSM MODULE
SoftwareSerial gsm(10, 11);      // RX=10, TX=11

// ESP32 COMMUNICATION
SoftwareSerial espSerial(12, 13); // RX=12, TX=13

// ═════════════════════ SETTINGS ═════════════════════

const String PHONE_NUMBER    = "+639709126550";
const int    WATER_THRESHOLD = 80;        // dry ~65-70, wet 80+
const unsigned long BLINK_SPEED   = 300;  // ms per blink half-cycle
const unsigned long ACCIDENT_TIME = 5000; // ms accident stays active
const unsigned long VIB_DEBOUNCE = 200;    // ms vibration debounce
const unsigned long POST_ACCIDENT_LOCKOUT = 3000; // ms lockout after accident

// ═════════════════════ STATE ═════════════════════

bool accidentActive  = false;
bool smsSent         = false;
bool vibrationTrigger = false;
bool lastVibState    = HIGH;
unsigned long accidentStart = 0;
unsigned long lastVibTime = 0;
unsigned long accidentEndTime = 0;

bool blinkState   = false;
unsigned long lastBlink = 0;

// Dedup — only send to ESP32 when state actually changes
String lastSentState = "";

// ═════════════════════ SMS ═════════════════════

void sendSMS(String msg) {
  Serial.println("[SMS] Sending...");
  gsm.listen();                           // ensure gsm is active receiver
  gsm.println("AT");         wdt_reset(); delay(300);
  gsm.println("AT+CMGF=1"); wdt_reset(); delay(300);
  gsm.print("AT+CMGS=\"");
  gsm.print(PHONE_NUMBER);
  gsm.println("\"");         wdt_reset(); delay(500);
  gsm.print(msg);            wdt_reset(); delay(300);
  gsm.write(26);             wdt_reset(); delay(2000); // wait for +CMGS response
  wdt_reset();
  Serial.println("[SMS] Sent");
}

// ═════════════════════ VIBRATION ═════════════════════

void checkVibration() {
  bool cur = digitalRead(PIN_VIBRATION);
  if (cur == LOW && lastVibState == HIGH) {
    if (millis() - lastVibTime > VIB_DEBOUNCE) {
      lastVibTime = millis();
      Serial.println("[VIB] Vibration detected!");
      vibrationTrigger = true;
    }
  }
  lastVibState = cur;
}

// ═════════════════════ LED MODES ═════════════════════

void greenMode() {
  digitalWrite(G1, HIGH); digitalWrite(G2, HIGH);
  digitalWrite(Y1, LOW);  digitalWrite(Y2, LOW);
  digitalWrite(R1, LOW);  digitalWrite(R2, LOW);
}

void yellowMode() {
  digitalWrite(G1, LOW);  digitalWrite(G2, LOW);
  digitalWrite(Y1, HIGH); digitalWrite(Y2, HIGH);
  digitalWrite(R1, LOW);  digitalWrite(R2, LOW);
}

void redMode() {
  digitalWrite(G1, LOW);  digitalWrite(G2, LOW);
  digitalWrite(Y1, LOW);  digitalWrite(Y2, LOW);
  digitalWrite(R1, HIGH); digitalWrite(R2, HIGH);
}

// ═════════════════════ SEND TO ESP32 (deduped) ═════════════════════

void sendToESP(String state) {
  if (state == lastSentState) return;
  lastSentState = state;
  espSerial.listen();                   // ensure espSerial is TX-active
  espSerial.println(state);
  Serial.println("[ESP] Sent: " + state);
}

// ═════════════════════ SYSTEM LOGIC ═════════════════════

void updateSystem() {
  int  water = analogRead(PIN_WATER);
  bool rain  = (digitalRead(PIN_RAIN) == LOW);

  Serial.print("[DATA] water="); Serial.print(water);
  Serial.print(" rain=");        Serial.println(rain ? "YES" : "NO");

  // ── ACCIDENT (vibration) ──────────────────────────────────────────
  if (vibrationTrigger && !accidentActive) {
    vibrationTrigger = false;
    accidentActive   = true;
    accidentStart    = millis();
    blinkState       = true;
    lastBlink        = millis();
    smsSent          = false;
    Serial.println("[STATE] ACCIDENT started");
    sendToESP("ACCIDENT");
  }

  if (accidentActive) {
    // Blink RED — works whether coming from GREEN, YELLOW, or solid RED
    if (millis() - lastBlink >= BLINK_SPEED) {
      lastBlink  = millis();
      blinkState = !blinkState;
    }
    digitalWrite(G1, LOW);  digitalWrite(G2, LOW);
    digitalWrite(Y1, LOW);  digitalWrite(Y2, LOW);
    digitalWrite(R1, blinkState ? HIGH : LOW);
    digitalWrite(R2, blinkState ? HIGH : LOW);

    // SMS once after 1 s
    if (!smsSent && millis() - accidentStart > 1000) {
      sendSMS("ALERT: Accident/crash detected at Brgy. Crossing Palkan, Tupi!");
      smsSent = true;
    }

    // End accident mode after ACCIDENT_TIME
    if (millis() - accidentStart >= ACCIDENT_TIME) {
      accidentActive   = false;
      accidentEndTime  = millis();
      digitalWrite(R1, LOW); digitalWrite(R2, LOW);
      lastSentState = "";
      Serial.println("[STATE] ACCIDENT ended");
    }
    return;
  }

  // ── FLOOD: water level triggered → RED + flood alert ─────────────
  if (millis() - accidentEndTime < POST_ACCIDENT_LOCKOUT) return;  // lockout after accident
  if (water > WATER_THRESHOLD) {
    redMode();
    sendToESP("FLOOD");

  // ── WARNING: rain only → YELLOW, no alert ─────────────────────────
  } else if (rain) {
    yellowMode();
    sendToESP("CLEAR");

  // ── SAFE ──────────────────────────────────────────────────────────
  } else {
    greenMode();
    sendToESP("CLEAR");
  }
}

// ═════════════════════ SETUP ═════════════════════

void setup() {
  Serial.begin(9600);
  gsm.begin(9600);
  espSerial.begin(9600);

  pinMode(PIN_RAIN,      INPUT_PULLUP);
  pinMode(PIN_VIBRATION, INPUT_PULLUP);
  pinMode(PIN_WATER,     INPUT);

  pinMode(G1, OUTPUT); pinMode(G2, OUTPUT);
  pinMode(Y1, OUTPUT); pinMode(Y2, OUTPUT);
  pinMode(R1, OUTPUT); pinMode(R2, OUTPUT);

  greenMode();

  wdt_enable(WDTO_8S);
  Serial.println("[BOOT] SafeSense Arduino ready");
}

// ═════════════════════ LOOP ═════════════════════

void loop() {
  wdt_reset();
  checkVibration();
  updateSystem();
  delay(100);   // 100 ms — stable reads, no spam
}
