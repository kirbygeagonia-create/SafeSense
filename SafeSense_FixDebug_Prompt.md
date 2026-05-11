# SafeSense — Fix & Debug Prompt
# Covers: LED pin swap fix + sensor pull-up fix + camera debug output

---

## CONTEXT

Repository: https://github.com/kirbygeagonia-create/SafeSense.git

Two files need changes:
- `arduino/SafeSense_Arduino/SafeSense_Arduino.ino`
- `arduino/SafeSense_ESP32CAM/SafeSense_ESP32CAM.ino`

Three issues were found during hardware testing and must be fixed now.
Do not change anything else in either file.

---

## RULES

- Match every FIND block exactly as written — verbatim, same whitespace.
- Replace only what is shown. Do not touch surrounding code.
- Preserve all existing `// FIX BUG-*` comments.
- Do not modify `SafeSense_ESP32_Standalone.ino`.

---

---

# FILE 1 — `arduino/SafeSense_Arduino/SafeSense_Arduino.ino`

---

## FIX-1 — LED pin swap: Green and Yellow are reversed for both lanes

**Problem:** The Green and Yellow pin numbers are swapped vs the physical wiring diagram.
The wiring diagram shows D4 = Green, D5 = Yellow for Lane 1, and D7 = Green, D8 = Yellow
for Lane 2. The sketch currently has them reversed, so the Yellow LED lights up at boot
instead of the Green LED.

**FIND:**

```cpp
const int PIN_LED_L1_GREEN  = 5;   // Lane 1 Safe  / Power indicator
const int PIN_LED_L1_YELLOW = 4;   // Lane 1 Warning
const int PIN_LED_L1_RED    = 6;   // Lane 1 Danger / Critical

// ── LEDs — Left Lane (Lane 2) ────────────
// Drivers approaching from Left Lane see these three LEDs.
// Wire: D8/D7/D9 ──[220Ω]──► LED anode, LED cathode ── GND
const int PIN_LED_L2_GREEN  = 8;   // Lane 2 Safe  / Power indicator
const int PIN_LED_L2_YELLOW = 7;   // Lane 2 Warning
const int PIN_LED_L2_RED    = 9;   // Lane 2 Danger / Critical
```

**REPLACE WITH:**

```cpp
const int PIN_LED_L1_GREEN  = 4;   // Lane 1 Safe  / Power indicator — D4 matches diagram
const int PIN_LED_L1_YELLOW = 5;   // Lane 1 Warning               — D5 matches diagram
const int PIN_LED_L1_RED    = 6;   // Lane 1 Danger / Critical

// ── LEDs — Left Lane (Lane 2) ────────────
// Drivers approaching from Left Lane see these three LEDs.
// Wire: D7/D8/D9 ──[220Ω]──► LED anode, LED cathode ── GND
const int PIN_LED_L2_GREEN  = 7;   // Lane 2 Safe  / Power indicator — D7 matches diagram
const int PIN_LED_L2_YELLOW = 8;   // Lane 2 Warning               — D8 matches diagram
const int PIN_LED_L2_RED    = 9;   // Lane 2 Danger / Critical
```

---

## FIX-2 — Rain sensor floating: causes false rain detection and spurious yellow LED

**Problem:** `PIN_RAIN_DIGITAL (D2)` is set as plain `INPUT`. When the rain sensor is
not actively detecting rain, the pin floats and randomly reads LOW. The sketch treats
LOW as rain detected, which triggers alert level 1 (WARNING) and lights the yellow LED
even when conditions are safe. Adding INPUT_PULLUP holds the pin HIGH when no rain signal
is present, preventing false readings.

The vibration sensor stays as plain INPUT — it is an active-HIGH sensor and INPUT_PULLUP
would pull it HIGH permanently, causing false vibration readings.

**FIND:**

```cpp
  pinMode(PIN_RAIN_DIGITAL,   INPUT);
  pinMode(PIN_VIBRATION,      INPUT);
```

**REPLACE WITH:**

```cpp
  pinMode(PIN_RAIN_DIGITAL,   INPUT_PULLUP);  // Pull-up prevents floating → false rain detection
  pinMode(PIN_VIBRATION,      INPUT);         // Stays INPUT — active HIGH sensor, not pull-up
```

---

---

# FILE 2 — `arduino/SafeSense_ESP32CAM/SafeSense_ESP32CAM.ino`

---

## FIX-3 — Camera init has no debug output: impossible to diagnose failures

**Problem:** When `esp_camera_init()` fails, the sketch silently sets `cameraReady = false`
and moves on. There is no Serial output explaining what went wrong or what error code was
returned. This makes it impossible to diagnose camera issues from the Serial Monitor.

This fix adds:
- Boot status prints so you can confirm the sketch is running
- PSRAM detection output
- The exact error code and name when camera init fails
- A success confirmation when camera init succeeds

### FIX-3a — Add boot prints to setup()

**FIND** inside `setup()`:

```cpp
  // USB CDC debug Serial (UART0 via USB — for Serial Monitor)
  Serial.begin(115200);

  // Hardware Serial1 for communication with Arduino Uno (UART1)
  // GPIO43 = TX (connects to Arduino D0/RX)
  // GPIO44 = RX (connects to Arduino D1/TX)
  Serial1.begin(9600, SERIAL_8N1, 44, 43);
```

**REPLACE WITH:**

```cpp
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
```

---

### FIX-3b — Add camera init result output

**FIND** inside `initCamera()`:

```cpp
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    // Camera init failed
    return false;
  }

  // Adjust camera settings for outdoor use
  sensor_t * s = esp_camera_sensor_get();
```

**REPLACE WITH:**

```cpp
  Serial.println("[Camera] Initializing...");
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
  }
```

---

### FIX-3c — Add camera result print in setup()

**FIND** inside `setup()`:

```cpp
  // Initialize camera
  if (CAMERA_ENABLED) {
    cameraReady = initCamera();
  }

  // Connect to WiFi
  connectWiFi();
```

**REPLACE WITH:**

```cpp
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
```

---

### FIX-3d — Add WiFi connection result print in connectWiFi()

**FIND** inside `connectWiFi()`:

```cpp
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

**REPLACE WITH:**

```cpp
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
```

---

---

# VERIFICATION CHECKLIST

After applying all fixes, confirm the following before uploading.

## SafeSense_Arduino.ino

- [ ] `PIN_LED_L1_GREEN = 4` (was 5)
- [ ] `PIN_LED_L1_YELLOW = 5` (was 4)
- [ ] `PIN_LED_L2_GREEN = 7` (was 8)
- [ ] `PIN_LED_L2_YELLOW = 8` (was 7)
- [ ] `PIN_LED_L1_RED = 6` and `PIN_LED_L2_RED = 9` — unchanged
- [ ] `pinMode(PIN_RAIN_DIGITAL, INPUT_PULLUP)` — not plain INPUT
- [ ] `pinMode(PIN_VIBRATION, INPUT)` — unchanged, still plain INPUT
- [ ] No other lines changed

## SafeSense_ESP32CAM.ino

- [ ] `setup()` prints boot header, free heap, PSRAM status, and chip info after `Serial.begin(115200)`
- [ ] `setup()` prints `[Boot] Serial1 (Arduino bridge) ready on GPIO43/44.`
- [ ] `initCamera()` prints `[Camera] Initializing...` before `esp_camera_init()`
- [ ] `initCamera()` prints the error code and name when init fails
- [ ] `initCamera()` prints `[Camera] Init OK.` and the sensor PID when init succeeds
- [ ] `setup()` prints `[Boot] Camera ready.` or `[Boot] Camera NOT ready.` after init
- [ ] `connectWiFi()` prints the IP address and RSSI on success
- [ ] `connectWiFi()` prints the failure attempt number on failure
- [ ] All existing `// FIX BUG-E1` and `// FIX BUG-E2` comments are still present

---

## AFTER UPLOADING — WHAT TO LOOK FOR

### Arduino Serial Monitor (115200 baud, or 9600 if you use SoftwareSerial for debug)
- At boot, all 6 LEDs flash once, then **Green LEDs (Lane 1 D4, Lane 2 D7) stay ON**
- Yellow and Red LEDs go OFF after the startup flash
- If Yellow stays on — rain sensor is still reading LOW; check physical wiring of sensor to D2

### ESP32-S3 Serial Monitor (115200 baud, USB CDC)
- You should see the boot header immediately on reset
- `[Boot] PSRAM found: YES` — if NO, check Arduino IDE PSRAM setting (must be OPI PSRAM)
- `[Camera] Init OK.` and a sensor PID — confirms camera is working
- `[WiFi] Connected. IP: 192.168.x.x` — confirms WiFi is working
- If camera fails, the error code (e.g. `0x20003` = ESP_ERR_NOT_FOUND) tells you the exact cause

---

*SafeSense Fix & Debug Prompt — LED pin swap + rain sensor pull-up + camera diagnostics*
*Modifies: SafeSense_Arduino.ino and SafeSense_ESP32CAM.ino only*
