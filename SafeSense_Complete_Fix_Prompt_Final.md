# SafeSense Arduino — Complete Fix Prompt (Two-Lane LEDs + ESP32-S3 Migration)
# Version: Final Combined

---

## HOW TO USE THIS PROMPT

1. Open the SafeSense repository.
2. Paste this entire document as your instruction.
3. Apply fixes to **both files** in the exact order listed.
4. Do NOT change `SafeSense_ESP32_Standalone.ino` — it is unaffected.
5. Run the Verification Checklist at the bottom before closing.

---

## WHAT THIS PROMPT FIXES

### File 1 — `arduino/SafeSense_Arduino.ino`
- **ISSUE-1:** Only 3 LEDs coded. Must be expanded to 6 LEDs (3 per lane × 2 lanes).
- **ISSUE-2:** LED pins are wrong (D10/D11/D12). Must match the wiring diagram (D4–D9).
- **ISSUE-3:** GSM SoftwareSerial on D7/D8 conflicts with Lane 2 LEDs. Must move to D10/D11.
- **ISSUE-4:** Buzzer on D9 conflicts with Lane 2 Red LED. Must move to D12.
- **ISSUE-5:** Rain and Vibration sensor pins are swapped vs the wiring diagram. Rain must be D2, Vibration must be D3.

### File 2 — `arduino/SafeSense_ESP32CAM.ino`
- **ISSUE-6:** The sketch is written for AI-Thinker ESP32-CAM (GPIO1/GPIO3 UART). The actual board is ESP32-S3 AI CAM which uses GPIO43 (TX) and GPIO44 (RX). The Serial communication between Arduino and ESP32-S3 will not work until this is fixed.
- **ISSUE-7:** Camera pin definitions are for the OV2640 on AI-Thinker. The ESP32-S3 AI CAM has a different camera pin mapping and uses a different board target. These must be updated.
- **ISSUE-8:** Board setup instructions in the header comment still describe AI-Thinker. Must be updated for ESP32-S3.

---

## RULES — READ BEFORE APPLYING ANY FIX

- Match every "FIND this code" block **exactly** as written. If a block is not found, stop and report which one is missing.
- Replace **only** what is shown in the REPLACE block. Do not modify any surrounding code.
- Preserve all existing `// FIX BUG-*` comments — they document previously fixed bugs and must stay.
- Do not change any timing constants, thresholds, SMS logic, GSM logic, WiFi logic, alert evaluation logic, or the serial data protocol (`$SAFE,...`).
- Do not modify `SafeSense_ESP32_Standalone.ino`.
- Both lanes always mirror the same alert level — there is no independent per-lane sensor evaluation. One set of sensors drives both sets of LEDs simultaneously.

---

---

# FILE 1 — `arduino/SafeSense_Arduino.ino`

---

## ISSUE-1, 2, 3, 4, 5 — Fix all pin definitions

**FIND this exact block:**

```cpp
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
```

**REPLACE WITH:**

```cpp
// Sensors (D2 = Rain, D3 = Vibration — matches wiring diagram)
const int PIN_WATER_LEVEL   = A0;  // Analog — resistive water level sensor
const int PIN_RAIN_DIGITAL  = 2;   // Digital — rain sensor DO (D2, LOW = rain)
const int PIN_VIBRATION     = 3;   // Digital — vibration sensor OUT (D3, HIGH = vibration)

// ── LEDs — Lane 1 (Direction A — e.g. Northbound) ────────────
// Drivers approaching from Direction A see these three LEDs.
// Wire: D4/D5/D6 ──[220Ω]──► LED anode, LED cathode ── GND
const int PIN_LED_L1_GREEN  = 4;   // Lane 1 Safe  / Power indicator
const int PIN_LED_L1_YELLOW = 5;   // Lane 1 Warning
const int PIN_LED_L1_RED    = 6;   // Lane 1 Danger / Critical

// ── LEDs — Lane 2 (Direction B — e.g. Southbound) ────────────
// Drivers approaching from Direction B see these three LEDs.
// Wire: D7/D8/D9 ──[220Ω]──► LED anode, LED cathode ── GND
const int PIN_LED_L2_GREEN  = 7;   // Lane 2 Safe  / Power indicator
const int PIN_LED_L2_YELLOW = 8;   // Lane 2 Warning
const int PIN_LED_L2_RED    = 9;   // Lane 2 Danger / Critical

// Buzzer (optional) — moved to D12 to free D9 for Lane 2 Red LED
const int PIN_BUZZER        = 12;  // Piezo buzzer (via 100Ω resistor)

// SIM900A GSM (SoftwareSerial) — moved to D10/D11 to free D7/D8 for Lane 2 LEDs
const int PIN_GSM_RX        = 10;  // Arduino receives FROM SIM900A TX
const int PIN_GSM_TX        = 11;  // Arduino sends TO SIM900A RX
```

---

## ISSUE-2, 3, 4 — Fix setup() pinMode calls

**FIND this exact block inside `setup()`:**

```cpp
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
```

**REPLACE WITH:**

```cpp
  pinMode(PIN_WATER_LEVEL,    INPUT);
  pinMode(PIN_RAIN_DIGITAL,   INPUT);
  pinMode(PIN_VIBRATION,      INPUT);
  // Lane 1 LEDs
  pinMode(PIN_LED_L1_GREEN,   OUTPUT);
  pinMode(PIN_LED_L1_YELLOW,  OUTPUT);
  pinMode(PIN_LED_L1_RED,     OUTPUT);
  // Lane 2 LEDs
  pinMode(PIN_LED_L2_GREEN,   OUTPUT);
  pinMode(PIN_LED_L2_YELLOW,  OUTPUT);
  pinMode(PIN_LED_L2_RED,     OUTPUT);
  if (BUZZER_ENABLED) {
    pinMode(PIN_BUZZER, OUTPUT);
    // Short beep on boot to confirm buzzer works
    tone(PIN_BUZZER, 1000, 100);
  }
```

---

## ISSUE-2, 3, 4 — Fix startup LED sequence in setup()

**FIND this exact block inside `setup()`:**

```cpp
  // Startup LED sequence (all on briefly, then green only)
  digitalWrite(PIN_LED_GREEN,  HIGH);
  digitalWrite(PIN_LED_YELLOW, HIGH);
  digitalWrite(PIN_LED_RED,    HIGH);
  delay(500);
  digitalWrite(PIN_LED_YELLOW, LOW);
  digitalWrite(PIN_LED_RED,    LOW);
  // Green stays on = system powered
```

**REPLACE WITH:**

```cpp
  // Startup LED sequence — all 6 LEDs on briefly, then both lane greens stay on
  digitalWrite(PIN_LED_L1_GREEN,  HIGH);
  digitalWrite(PIN_LED_L1_YELLOW, HIGH);
  digitalWrite(PIN_LED_L1_RED,    HIGH);
  digitalWrite(PIN_LED_L2_GREEN,  HIGH);
  digitalWrite(PIN_LED_L2_YELLOW, HIGH);
  digitalWrite(PIN_LED_L2_RED,    HIGH);
  delay(500);
  digitalWrite(PIN_LED_L1_YELLOW, LOW);
  digitalWrite(PIN_LED_L1_RED,    LOW);
  digitalWrite(PIN_LED_L2_YELLOW, LOW);
  digitalWrite(PIN_LED_L2_RED,    LOW);
  // Both lane green LEDs stay on = system powered, both lanes safe
```

---

## ISSUE-1 — Replace the entire updateLEDs() function

**FIND the entire function** (from `void updateLEDs` to the closing `}`):

```cpp
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
```

**REPLACE WITH:**

```cpp
void updateLEDs(unsigned long now) {
  // Both lanes always mirror the same alert level.
  // Lane 1 = Direction A (Northbound), Lane 2 = Direction B (Southbound).
  // One set of sensors drives both sets of LEDs — drivers from both
  // directions receive the same warning simultaneously.

  switch (currentAlertLevel) {

    case 0:  // SAFE — both lanes solid green
      digitalWrite(PIN_LED_L1_GREEN,  HIGH);
      digitalWrite(PIN_LED_L1_YELLOW, LOW);
      digitalWrite(PIN_LED_L1_RED,    LOW);
      digitalWrite(PIN_LED_L2_GREEN,  HIGH);
      digitalWrite(PIN_LED_L2_YELLOW, LOW);
      digitalWrite(PIN_LED_L2_RED,    LOW);
      break;

    case 1:  // WARNING — both lanes solid yellow, green off
      digitalWrite(PIN_LED_L1_GREEN,  LOW);
      digitalWrite(PIN_LED_L1_YELLOW, HIGH);
      digitalWrite(PIN_LED_L1_RED,    LOW);
      digitalWrite(PIN_LED_L2_GREEN,  LOW);
      digitalWrite(PIN_LED_L2_YELLOW, HIGH);
      digitalWrite(PIN_LED_L2_RED,    LOW);
      break;

    case 2:  // DANGER — both lanes: yellow solid + red slow blink
      digitalWrite(PIN_LED_L1_GREEN,  LOW);
      digitalWrite(PIN_LED_L1_YELLOW, HIGH);
      digitalWrite(PIN_LED_L2_GREEN,  LOW);
      digitalWrite(PIN_LED_L2_YELLOW, HIGH);
      if (timeSince(now, lastLedToggle) >= LED_SLOW_BLINK_MS) {
        lastLedToggle = now;
        ledRedState = !ledRedState;
        // Both lane reds blink in sync
        digitalWrite(PIN_LED_L1_RED, ledRedState ? HIGH : LOW);
        digitalWrite(PIN_LED_L2_RED, ledRedState ? HIGH : LOW);
      }
      break;

    case 3:  // CRITICAL — both lanes: red + yellow fast alternating blink
      digitalWrite(PIN_LED_L1_GREEN, LOW);
      digitalWrite(PIN_LED_L2_GREEN, LOW);
      if (timeSince(now, lastLedToggle) >= LED_FAST_BLINK_MS) {
        lastLedToggle = now;
        ledRedState = !ledRedState;
        // Both lanes blink red and yellow in opposite phase (alternating)
        digitalWrite(PIN_LED_L1_RED,    ledRedState    ? HIGH : LOW);
        digitalWrite(PIN_LED_L1_YELLOW, (!ledRedState) ? HIGH : LOW);
        digitalWrite(PIN_LED_L2_RED,    ledRedState    ? HIGH : LOW);
        digitalWrite(PIN_LED_L2_YELLOW, (!ledRedState) ? HIGH : LOW);
      }
      break;
  }
}
```

---

## ISSUE-2, 3, 4, 5 — Update the file header hardware connections comment

**FIND this block near the very top of the file (inside the `/* ... */` header comment):**

```
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
```

**REPLACE WITH:**

```
 *  Sensors:
 *    Water Level Sensor  → A0 (analog, resistive)
 *    Rain Sensor (DO)    → D2 (digital, LOW = rain)
 *    Vibration Sensor    → D3 (digital, HIGH = vibration)
 *
 *  Outputs — Two-Lane LED Array (3 LEDs × 2 lanes = 6 LEDs total):
 *    ── Lane 1 (Direction A — Northbound) ──
 *    Lane 1 Green  → D4  (via 220Ω to GND) — Safe
 *    Lane 1 Yellow → D5  (via 220Ω to GND) — Warning
 *    Lane 1 Red    → D6  (via 220Ω to GND) — Danger / Critical
 *    ── Lane 2 (Direction B — Southbound) ──
 *    Lane 2 Green  → D7  (via 220Ω to GND) — Safe
 *    Lane 2 Yellow → D8  (via 220Ω to GND) — Warning
 *    Lane 2 Red    → D9  (via 220Ω to GND) — Danger / Critical
 *
 *  SIM900A GSM Module (moved to D10/D11 to free D7/D8 for Lane 2 LEDs):
 *    SIM900A TX → D10 (Arduino SoftSerial RX)
 *    SIM900A RX → D11 (Arduino SoftSerial TX)
 *    SIM900A VCC → 4V external regulator (LM2596)
 *    SIM900A GND → Common GND
 *
 *  Buzzer (moved to D12 to free D9 for Lane 2 Red LED):
 *    Buzzer     → D12 (via 100Ω to GND)
 *
 *  ESP32-S3 AI CAM Serial Bridge:
 *    Arduino TX (D1) → ESP32-S3 GPIO44 (RX)
 *    Arduino RX (D0) → ESP32-S3 GPIO43 (TX)
 *    (Uses Hardware Serial — avoid Serial.print debug
 *     when ESP32-S3 is connected; use SoftSerial for debug)
```

---

## ISSUE-2, 3, 4 — Update the buzzer comment in CONFIGURATION section

**FIND:**

```cpp
// ── Buzzer (optional) ────────────────────────────────────────
// Set to true if you have a piezo buzzer connected to D9
// If you don't have a buzzer yet, set to false — no errors
const bool BUZZER_ENABLED = false;   // Change to true when buzzer is wired
```

**REPLACE WITH:**

```cpp
// ── Buzzer (optional) ────────────────────────────────────────
// Set to true if you have a piezo buzzer connected to D12
// If you don't have a buzzer yet, set to false — no errors
const bool BUZZER_ENABLED = false;   // Change to true when buzzer is wired
```

---

---

# FILE 2 — `arduino/SafeSense_ESP32CAM.ino`

> **Board being used: ESP32-S3 AI CAM**
> The current sketch targets the AI-Thinker ESP32-CAM.
> These are different chips. The fixes below migrate the sketch fully to ESP32-S3.

---

## ISSUE-8 — Update the file header comment block

**FIND the entire opening comment block:**

```cpp
/*
 * ============================================================
 *  SafeSense IoT — ESP32-CAM WiFi Alert Gateway + Camera
 *  Board  : AI-Thinker ESP32-CAM
 *
 *  This sketch runs on the ESP32-CAM module in the dual-MCU
 *  SafeSense architecture:
 *
 *    Arduino Uno  ──Serial──►  ESP32-CAM
 *    (sensors,                  (WiFi HTTP,
 *     LEDs,                      JSON POST,
 *     GSM SMS)                   camera capture,
 *                                heartbeat)
 *
 *  The ESP32-CAM receives sensor data and alert commands
 *  from the Arduino Uno over Serial, connects to WiFi,
 *  POSTs JSON payloads to the SafeSense Hospital Management
 *  System web dashboard, AND captures camera images when
 *  alerts are triggered (flood/accident evidence photos).
 *
 *  ── HARDWARE CONNECTIONS ──
 *
 *  Serial from Arduino Uno:
 *    ESP32-CAM U0R (GPIO3/RX) ← Arduino TX (D1)
 *    ESP32-CAM U0T (GPIO1/TX) → Arduino RX (D0)
 *
 *  Power:
 *    ESP32-CAM VCC → 5V from LM2596 buck converter
 *    ESP32-CAM GND → Common GND
 *
 *  Camera:
 *    OV2640 camera module (built into ESP32-CAM board)
 *    No additional wiring needed — camera is onboard
 *
 *  Status LED:
 *    GPIO33 = onboard red LED (active LOW)
 *    GPIO4  = onboard flash LED (active HIGH, also camera flash)
 *
 *  ── IMPORTANT NOTES ──
 *
 *  • GPIO4 (flash LED) is shared with the camera's SD card
 *    interface. If using SD card, the flash LED cannot be used.
 *    This sketch does NOT use SD card, so flash LED is available.
 *
 *  • The camera uses significant RAM (~120KB for SVGA JPEG).
 *    DynamicJsonDocument sizes are kept conservative.
 *
 *  Required Libraries (all built-in with ESP32 board package):
 *    - ArduinoJson by Benoit Blanchon (v6.x or v7.x)
 *    - WiFi.h
 *    - HTTPClient.h
 *    - esp_camera.h
 *
 *  Board Setup in Arduino IDE:
 *    1. Add ESP32 board URL: https://dl.espressif.com/dl/package_esp32_index.json
 *    2. Install "esp32 by Espressif Systems" from Board Manager
 *    3. Select Board: "AI Thinker ESP32-CAM"
 *    4. Partition Scheme: "Huge APP (3MB No OTA/1MB SPIFFS)"
 *    5. Upload Speed: 115200
 *    6. You need an FTDI programmer to upload (GPIO0→GND for flash mode)
 *
 * ============================================================
 */
```

**REPLACE WITH:**

```cpp
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
```

---

## ISSUE-7 — Replace the camera pin definitions

**FIND this entire block:**

```cpp
// ══════════════════════════════════════════════════════════════
//  AI-THINKER ESP32-CAM — CAMERA PIN DEFINITIONS
//  (Do NOT change these — they are fixed by the board design)
// ══════════════════════════════════════════════════════════════

#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22
```

**REPLACE WITH:**

```cpp
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
```

---

## ISSUE-6 — Update the PIN DEFINITIONS for status LED

**FIND:**

```cpp
const int PIN_LED_STATUS = 33;   // Onboard red LED (active LOW)
const int PIN_LED_FLASH  = 4;    // Onboard flash LED (active HIGH)
```

**REPLACE WITH:**

```cpp
const int PIN_LED_STATUS = 2;    // Onboard LED on ESP32-S3 AI CAM (active HIGH)
const int PIN_LED_FLASH  = 48;   // Flash LED on ESP32-S3 AI CAM (active HIGH)
// Note: GPIO2 and GPIO48 are common onboard LED pins for ESP32-S3 AI CAM boards.
// If your specific board uses different pins, adjust these two lines only.
```

---

## ISSUE-6 — Update setup() LED initialization

**FIND this block inside `setup()`:**

```cpp
  // LED setup
  pinMode(PIN_LED_STATUS, OUTPUT);
  pinMode(PIN_LED_FLASH,  OUTPUT);
  digitalWrite(PIN_LED_STATUS, HIGH);  // OFF (active low)
  digitalWrite(PIN_LED_FLASH,  LOW);   // OFF

  // Boot indication — flash LED twice
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_LED_STATUS, LOW);
    delay(200);
    digitalWrite(PIN_LED_STATUS, HIGH);
    delay(200);
  }
```

**REPLACE WITH:**

```cpp
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
```

---

## ISSUE-6 — Replace Hardware Serial with Serial1 (UART1) throughout

The ESP32-S3 must use `Serial1` on GPIO43/44 for Arduino communication.
`Serial` (UART0) on the ESP32-S3 is reserved for USB CDC debug output.

**FIND in `setup()`:**

```cpp
  // Hardware Serial for communication with Arduino Uno
  Serial.begin(9600);
```

**REPLACE WITH:**

```cpp
  // USB CDC debug Serial (UART0 via USB — for Serial Monitor)
  Serial.begin(115200);

  // Hardware Serial1 for communication with Arduino Uno (UART1)
  // GPIO43 = TX (connects to Arduino D0/RX)
  // GPIO44 = RX (connects to Arduino D1/TX)
  Serial1.begin(9600, SERIAL_8N1, 44, 43);
```

---

## ISSUE-6 — Update loop() Serial reads to Serial1

**FIND inside `loop()`:**

```cpp
  // ── Read incoming Serial data from Arduino ─────────────
  while (Serial.available()) {
    char c = Serial.read();
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
```

**REPLACE WITH:**

```cpp
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
```

---

## ISSUE-6 — Update LED status feedback in loop() WiFi check

**FIND in `loop()`:**

```cpp
  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(PIN_LED_STATUS, (millis() / 200) % 2 == 0 ? LOW : HIGH);
    if (timeSince(now, lastWiFiAttempt) >= WIFI_RECONNECT_INTERVAL) {
      connectWiFi();
      lastWiFiAttempt = now;
    }
  } else {
    digitalWrite(PIN_LED_STATUS, LOW);  // Solid ON = connected
  }
```

**REPLACE WITH:**

```cpp
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
```

---

## ISSUE-6 — Update LED feedback in connectWiFi()

**FIND in `connectWiFi()`:**

```cpp
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    digitalWrite(PIN_LED_STATUS, attempts % 2 == 0 ? LOW : HIGH);
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    digitalWrite(PIN_LED_STATUS, LOW);
  } else {
    wifiFailCount++;
    digitalWrite(PIN_LED_STATUS, HIGH);
    if (wifiFailCount >= 10) {
      ESP.restart();
    }
  }
```

**REPLACE WITH:**

```cpp
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
  } else {
    wifiFailCount++;
    digitalWrite(PIN_LED_STATUS, LOW);   // OFF = failed
    if (wifiFailCount >= 10) {
      ESP.restart();
    }
  }
```

---

## ISSUE-6 — Update LED feedback in processAlertPacket()

**FIND at the end of `processAlertPacket()`:**

```cpp
  // Visual feedback
  if (!alertSuccess) {
    for (int i = 0; i < 5; i++) {
      digitalWrite(PIN_LED_STATUS, HIGH);
      delay(100);
      digitalWrite(PIN_LED_STATUS, LOW);
      delay(100);
    }
  }
```

**REPLACE WITH:**

```cpp
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
```

---

---

# VERIFICATION CHECKLIST

Go through every item after applying all fixes. A failed item means the fix was not applied correctly.

## SafeSense_Arduino.ino

- [ ] `PIN_RAIN_DIGITAL = 2` and `PIN_VIBRATION = 3` (not swapped)
- [ ] `PIN_LED_L1_GREEN = 4`, `PIN_LED_L1_YELLOW = 5`, `PIN_LED_L1_RED = 6`
- [ ] `PIN_LED_L2_GREEN = 7`, `PIN_LED_L2_YELLOW = 8`, `PIN_LED_L2_RED = 9`
- [ ] `PIN_BUZZER = 12` (not 9)
- [ ] `PIN_GSM_RX = 10`, `PIN_GSM_TX = 11` (not 7/8)
- [ ] `SoftwareSerial gsmSerial(PIN_GSM_RX, PIN_GSM_TX)` — unchanged, still uses the same variables
- [ ] No remaining references to `PIN_LED_GREEN`, `PIN_LED_YELLOW`, or `PIN_LED_RED` (old single-lane names) anywhere
- [ ] `setup()` has exactly 6 LED `pinMode(OUTPUT)` calls — 3 for Lane 1, 3 for Lane 2
- [ ] Startup LED sequence turns on all 6 LEDs, then turns off yellow and red for both lanes
- [ ] `updateLEDs()` drives all 6 pins in every `case` (0, 1, 2, 3)
- [ ] All existing `// FIX BUG-A*` and `// FIX BUG-NEW-1` comments are still present and untouched
- [ ] Buzzer comment in CONFIGURATION section says D12 (not D9)
- [ ] Header comment shows D2=Rain, D3=Vibration, D4–D9 for 6 LEDs, D10/D11 for GSM, D12 for Buzzer

## SafeSense_ESP32CAM.ino

- [ ] Board comment at top says "ESP32-S3 AI CAM" (not "AI-Thinker ESP32-CAM")
- [ ] Camera `XCLK_GPIO_NUM = 15` (not 0) — confirms ESP32-S3 pin mapping is in place
- [ ] Camera `PWDN_GPIO_NUM = -1` (not 32) — confirms ESP32-S3 pin mapping
- [ ] `PIN_LED_STATUS = 2` (not 33)
- [ ] `PIN_LED_FLASH = 48` (not 4)
- [ ] `setup()` contains `Serial1.begin(9600, SERIAL_8N1, 44, 43)` — RX=44, TX=43
- [ ] `setup()` still contains `Serial.begin(115200)` for USB debug output
- [ ] `loop()` reads from `Serial1.available()` / `Serial1.read()` (not `Serial`)
- [ ] LED logic uses `HIGH = ON` pattern throughout (active HIGH for ESP32-S3)
- [ ] `sendAlert()` still uses `WiFiClient wifiClient; http.begin(wifiClient, url)` — `// FIX BUG-E1` comment present
- [ ] `sendHeartbeat()` still uses `WiFiClient wifiClient; http.begin(wifiClient, url)` — `// FIX BUG-E2` comment present
- [ ] Board setup instructions in header say `ESP32S3 Dev Module` and mention "USB CDC On Boot: Enabled"

## Cross-file sanity checks

- [ ] `SafeSense_ESP32_Standalone.ino` is **not modified**
- [ ] The `$SAFE,...` serial protocol is **identical** in both Arduino and ESP32-S3 sketches — no format changes
- [ ] Arduino baud rate `Serial.begin(9600)` matches `Serial1.begin(9600, ...)` on the ESP32-S3 side

---

## PHYSICAL WIRING REMINDER

After flashing both boards, confirm the physical wires match:

| Arduino Pin | Connects to | ESP32-S3 Pin |
|-------------|------------|--------------|
| D1 (TX)     | ──────────► | GPIO44 (RX)  |
| D0 (RX)     | ◄────────── | GPIO43 (TX)  |
| GND         | ──────────── | GND          |
| 3.3V        | ──────────── | 3V3          |

| Arduino Pin | Component | Notes |
|-------------|-----------|-------|
| D2          | Rain Sensor DO | LOW = rain |
| D3          | Vibration Sensor OUT | HIGH = vibration |
| A0          | Water Level Sensor OUT | Analog |
| D4          | Lane 1 Green LED | via 220Ω to GND |
| D5          | Lane 1 Yellow LED | via 220Ω to GND |
| D6          | Lane 1 Red LED | via 220Ω to GND |
| D7          | Lane 2 Green LED | via 220Ω to GND |
| D8          | Lane 2 Yellow LED | via 220Ω to GND |
| D9          | Lane 2 Red LED | via 220Ω to GND |
| D10         | SIM900A TX | GSM SoftSerial RX |
| D11         | SIM900A RX | GSM SoftSerial TX |
| D12         | Buzzer (optional) | via 100Ω to GND |

---

*SafeSense Complete Fix Prompt — Two-Lane LEDs + ESP32-S3 Migration*
*Covers: ISSUE-1 through ISSUE-8 — Both SafeSense_Arduino.ino and SafeSense_ESP32CAM.ino*
*Do not modify SafeSense_ESP32_Standalone.ino*
