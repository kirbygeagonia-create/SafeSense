# SafeSense IoT — Hardware-Verified Fix Prompt v2
### Target: Windsurf Kimi K2.5

---

## Context

This is a real deployed IoT system. Two `.ino` files need fixes. All previously reported bugs
(WDT resets, SoftwareSerial listen(), debounce, ps_malloc, camera retry, server wake, ACCIDENT
cooldown, post-accident lockout) are already correctly applied in the current code — do not
regress them.

**Repository:** `https://github.com/kirbygeagonia-create/SafeSense.git`

**Files to edit:**
```
arduino/SafeSense_Arduino/SafeSense_Arduino.ino
arduino/SafeSense_ESP32CAM/SafeSense_ESP32CAM.ino
```

---

## Board Identification — Critical

| Board | Product | Arduino IDE Board Selection |
|---|---|---|
| Arduino | Arduino Uno | Arduino Uno |
| ESP32 | DFRobot ESP32-S3 AI Camera V1.1 (DFR1154) | **ESP32S3 Dev Module** |

> The ESP32 is NOT a "DFRobot FireBeetle 2 ESP32-S3". It is the DFR1154 — a different product.
> The correct Arduino IDE board is "ESP32S3 Dev Module". This is NOT a code change — it is a
> board settings change the user must apply manually. You must update the board settings comment
> block at the top of SafeSense_ESP32CAM.ino to reflect the correct settings.

---

## Bug 1 — Arduino: False vibration trigger on boot → RED LED immediately [CRITICAL]

**File:** `SafeSense_Arduino.ino`

**Root cause:**
```cpp
unsigned long lastVibTime = 0;   // initialized to zero at compile time
```
The debounce check in `checkVibration()` is:
```cpp
if (millis() - lastVibTime > VIB_DEBOUNCE)  // VIB_DEBOUNCE = 200
```
By the time `setup()` finishes (three `.begin()` calls, eight `pinMode()` calls, `greenMode()`),
`millis()` is already 300–500ms. So the very first loop iteration passes the debounce check
unconditionally. If the SW-420 reads LOW at that moment — which it commonly does due to
mechanical vibration from USB plug-in — `vibrationTrigger = true` is set immediately, triggering
ACCIDENT state and RED blinking before the user does anything.

**Fix:** Add exactly two lines at the end of `setup()`, after `greenMode()` and before
`wdt_enable(WDTO_8S)`:

```cpp
  greenMode();

  lastVibTime = millis();  // reset debounce timer — ignores power-on vibration
  delay(500);              // sensor settle: let SW-420 spring stop oscillating

  wdt_enable(WDTO_8S);
  Serial.println("[BOOT] SafeSense Arduino ready");
}
```

**Why `delay(500)` is safe here:** `wdt_enable()` is called AFTER the delay, so the watchdog is
not yet running and cannot fire during this delay.

**Do not** add a `wdt_reset()` inside this delay — the watchdog is not enabled yet.

---

## Bug 2 — Arduino: FLOOD never sends an SMS [CRITICAL]

**File:** `SafeSense_Arduino.ino`

**Root cause:** When water level exceeds `WATER_THRESHOLD`, `sendToESP("FLOOD")` fires correctly
but `sendSMS()` is never called. The system spec requires one SMS per flood event.

**Fix:**

Add a new boolean in the globals block (near `smsSent`):
```cpp
bool floodSmsSent = false;
```

In `updateSystem()`, replace the FLOOD/WARNING/SAFE block with:
```cpp
  if (millis() - accidentEndTime < POST_ACCIDENT_LOCKOUT) return;

  if (water > WATER_THRESHOLD) {
    redMode();
    sendToESP("FLOOD");
    if (!floodSmsSent) {
      sendSMS("ALERT: Flood danger detected at Brgy. Crossing Palkan, Tupi! Take immediate action.");
      floodSmsSent = true;
    }

  } else if (rain) {
    yellowMode();
    sendToESP("CLEAR");
    floodSmsSent = false;   // reset: next flood event must re-send SMS

  } else {
    greenMode();
    sendToESP("CLEAR");
    floodSmsSent = false;   // reset: next flood event must re-send SMS
  }
```

**Why the guard is needed:** `updateSystem()` runs every 100ms. Without `floodSmsSent`, SMS
fires continuously while water is high, locking the MCU in AT command delays and triggering WDT.

---

## Bug 3 — ESP32: Wrong board settings comment block [LOW — comment only]

**File:** `SafeSense_ESP32CAM.ino`

**Root cause:** The comment block at the top of the file says:
```
 * Board settings in Arduino IDE:
 *   Board            : DFRobot FireBeetle 2 ESP32-S3
 *   Flash Size       : 8MB
```
The actual board is DFR1154 and requires different settings. Wrong documentation misleads
the user into wrong Arduino IDE configuration, which causes the sketch to not boot (only
`ESP-ROM:esp32s3-20210327` appears in serial monitor — no sketch output).

**Fix:** Update the board settings comment block to:
```cpp
 * Board settings in Arduino IDE:
 *   Board            : ESP32S3 Dev Module
 *   USB CDC On Boot  : Enabled            ← REQUIRED for Serial output to work
 *   PSRAM            : OPI PSRAM
 *   Flash Size       : 16MB               ← DFR1154 has 16MB flash, not 8MB
 *   Flash Mode       : QIO 80MHz
 *   Partition Scheme : Huge APP (3MB No OTA/1MB SPIFFS)
 *   Upload Speed     : 921600
 *   CPU Frequency    : 240MHz (WiFi)
 *
 * BEFORE uploading with new board settings:
 *   Tools → Erase Flash → All Flash Contents
 *   (removes old bootloader written by previous wrong board selection)
```

> This is a comment change only — no functional code changes to the ESP32 sketch.
> The camera pins are correct for DFR1154 V1.1 and must NOT be changed.

---

## Camera Pins — Do Not Touch

These `#define` values are correct for DFRobot ESP32-S3 AI Camera V1.1 (DFR1154).
DFRobot's own documentation confirms this board uses `CAMERA_MODEL_DFRobot_FireBeetle2_ESP32S3`
which maps to exactly these GPIOs. They are hardwired on the PCB.

```cpp
#define PWDN_GPIO_NUM   -1   // not wired — camera powers on immediately
#define RESET_GPIO_NUM  -1   // not wired — software reset only
#define XCLK_GPIO_NUM   45   // master clock to camera
#define SIOD_GPIO_NUM    1   // I2C SDA — camera config bus
#define SIOC_GPIO_NUM    2   // I2C SCL — camera config bus
#define Y9_GPIO_NUM     48   // pixel data bus (8-bit parallel)
#define Y8_GPIO_NUM     46
#define Y7_GPIO_NUM     14
#define Y6_GPIO_NUM     21
#define Y5_GPIO_NUM     47
#define Y4_GPIO_NUM     20
#define Y3_GPIO_NUM     19
#define Y2_GPIO_NUM     34
#define VSYNC_GPIO_NUM  36   // frame sync
#define HREF_GPIO_NUM   35   // line sync
#define PCLK_GPIO_NUM    0   // pixel clock
```

**Do not change any of these values.**

---

## Do Not Change — Full List

- Any camera `#define` pin values
- `SERIAL1_RX = 44`, `SERIAL1_TX = 43`
- `WIFI_SSID`, `WIFI_PASSWORD`, `SERVER_URL`, `API_KEY`, `DEVICE_ID`
- `PHONE_NUMBER`, `LOCATION_NAME`, `LATITUDE`, `LONGITUDE`
- `WATER_THRESHOLD` (80), `BLINK_SPEED` (300), `ACCIDENT_TIME` (5000)
- `VIB_DEBOUNCE` (200), `POST_ACCIDENT_LOCKOUT` (3000), `ACCIDENT_COOLDOWN` (10000)
- `RETRY_BASE_MS` (5000), `HEARTBEAT_MS` (300000)
- All `wdt_reset()` calls inside `sendSMS()`
- `gsm.listen()` inside `sendSMS()`, `espSerial.listen()` inside `sendToESP()`
- `ps_malloc()` / `psramFound()` logic in `sendCameraImage()`
- Camera retry block in `setup()`, server wake block in `setup()`
- ACCIDENT cooldown guard in `processCommand()`
- All JSON field names in `sendAlert()` — server API depends on exact names
- `xclk_freq_hz = 10000000` (10 MHz XCLK — tuned for OV3660 stability)
- `STATION_TYPE = "hospital"`

---

## Verification Checklist

### `SafeSense_Arduino.ino`
- [ ] `bool floodSmsSent = false;` declared in globals
- [ ] `lastVibTime = millis();` is the first line after `greenMode()` in `setup()`
- [ ] `delay(500);` is the second line after `greenMode()` in `setup()`
- [ ] Both lines appear BEFORE `wdt_enable(WDTO_8S)`
- [ ] `sendSMS(...)` is called in the FLOOD branch, guarded by `!floodSmsSent`
- [ ] `floodSmsSent = true;` follows the SMS call
- [ ] `floodSmsSent = false;` is present in both the RAIN and SAFE branches
- [ ] All existing `wdt_reset()` calls in `sendSMS()` still present
- [ ] `greenMode()` is still called in `setup()`

### `SafeSense_ESP32CAM.ino`
- [ ] Board settings comment block updated to show "ESP32S3 Dev Module" and 16MB flash
- [ ] "USB CDC On Boot: Enabled" note is present in comment block
- [ ] "Erase Flash before uploading" note is present
- [ ] All 16 camera `#define` values are unchanged from original
- [ ] No functional code was changed

---

## Output Format

Output both complete files in full — no truncation, no ellipsis, no "rest unchanged".
Label each with its path:

```
// arduino/SafeSense_Arduino/SafeSense_Arduino.ino
[full file]
```

```
// arduino/SafeSense_ESP32CAM/SafeSense_ESP32CAM.ino
[full file]
```
