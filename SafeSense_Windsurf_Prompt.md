# SafeSense IoT — Full System Fix Prompt

## Context for the AI

You are fixing a real deployed Arduino + ESP32-S3 IoT system called **SafeSense**. It detects road flooding and accidents and sends alerts to a web server. The repository is at `https://github.com/kirbygeagonia-create/SafeSense.git`.

Only edit the two files listed below. Do not rename, move, or restructure anything. Do not add features not listed. Keep every comment already present in the file.

---

## Files to Edit

```
arduino/SafeSense_Arduino/SafeSense_Arduino.ino
arduino/SafeSense_ESP32CAM/SafeSense_ESP32CAM.ino
```

---

## Hardware — Source of Truth (do not change pin values)

### Arduino Uno
| Component | Pin | Mode | Logic |
|---|---|---|---|
| Rain sensor DO | D2 | INPUT_PULLUP | LOW = rain |
| Vibration SW-420 | D3 | INPUT_PULLUP | LOW = vibration |
| Water level sensor | A0 | INPUT | analog, >80 = water |
| Green LED 1 | D8 | OUTPUT | |
| Green LED 2 | D7 | OUTPUT | |
| Yellow LED 1 | D6 | OUTPUT | |
| Yellow LED 2 | D5 | OUTPUT | |
| Red LED 1 | D9 | OUTPUT | |
| Red LED 2 | D4 | OUTPUT | |
| GSM SIM900A RX | D10 | SoftwareSerial | |
| GSM SIM900A TX | D11 | SoftwareSerial | |
| ESP32 Serial RX | D12 | SoftwareSerial | |
| ESP32 Serial TX | D13 | SoftwareSerial | |

### ESP32-S3 (DFRobot FireBeetle 2 ESP32-S3, DFR0975)
| Signal | GPIO |
|---|---|
| Serial1 RX (from Arduino D13) | GPIO44 |
| Serial1 TX (to Arduino D12) | GPIO43 |
| Camera XCLK | GPIO45 |
| Camera SIOD | GPIO1 |
| Camera SIOC | GPIO2 |
| Camera Y9..Y2 | 48,46,14,21,47,20,19,34 |
| Camera VSYNC | GPIO36 |
| Camera HREF | GPIO35 |
| Camera PCLK | GPIO0 |
| Camera PWDN | -1 |
| Camera RESET | -1 |
| Camera sensor | OV3660 (XDKJ-OV3660), XCLK = 10 MHz |

---

## Required System Behavior — Source of Truth

### LED States (Arduino)
| Condition | LED | Alert to ESP32 | SMS |
|---|---|---|---|
| All sensors idle | GREEN solid | CLEAR | No |
| Rain sensor LOW only | YELLOW solid | CLEAR | No |
| Water level > 80 | RED solid | FLOOD | No |
| Vibration LOW edge | RED blinking (300ms) for 5s | ACCIDENT | Yes, after 1s |

### State Priority (highest first)
1. ACCIDENT — overrides everything for 5 seconds
2. FLOOD — overrides WARNING and SAFE
3. WARNING (rain only) — overrides SAFE
4. SAFE

### Arduino → ESP32 Serial Commands (9600 baud, newline-terminated)
- `ACCIDENT` → ESP32 captures image, posts accident alert to server
- `FLOOD` → ESP32 posts flood alert to server (no image)
- `CLEAR` → ESP32 does nothing

Commands are deduplicated: only sent when state changes.

### ESP32 Alert Flow
- ACCIDENT: capture JPEG → POST /api/alert (critical, accident, has_image=1) → POST /api/alert/image (multipart JPEG)
- FLOOD: POST /api/alert (critical, flood, has_image=0)
- Heartbeat: POST /api/heartbeat every 5 minutes

---

## Bugs to Fix — Every Single One

### BUG 1 — Arduino: Watchdog resets during SMS (CRITICAL)
**File:** `SafeSense_Arduino.ino`
**Problem:** `wdt_enable(WDTO_8S)` is active. `sendSMS()` runs for ~4-6 seconds (multiple AT commands with delays totalling 2400ms minimum, sometimes longer with GSM response time). If GSM is slow, `sendSMS()` exceeds 8 seconds → watchdog fires → Arduino resets mid-SMS → SMS never sent.
**Fix:** Add `wdt_reset()` calls inside `sendSMS()` at every `delay()` point:
```cpp
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
```

### BUG 2 — Arduino: SoftwareSerial listener conflict (MEDIUM)
**File:** `SafeSense_Arduino.ino`
**Problem:** Both `gsm(10,11)` and `espSerial(12,13)` are SoftwareSerial instances. On Arduino Uno only one can be the active listener at a time. `espSerial` is the last to call `.begin()` so it holds the listener slot. When `sendSMS()` calls `gsm.println()`, TX works because TX does not require listen — but adding explicit `gsm.listen()` before GSM and `espSerial.listen()` before ESP makes behavior deterministic and prevents future breakage.
**Fix:**
- Add `gsm.listen();` at the top of `sendSMS()` (already shown in BUG 1 fix above).
- Add `espSerial.listen();` at the top of `sendToESP()`:
```cpp
void sendToESP(String state) {
  if (state == lastSentState) return;
  lastSentState = state;
  espSerial.listen();                   // ensure espSerial is TX-active
  espSerial.println(state);
  Serial.println("[ESP] Sent: " + state);
}
```

### BUG 3 — Arduino: Vibration debounce missing (MEDIUM)
**File:** `SafeSense_Arduino.ino`
**Problem:** `checkVibration()` uses `lastVibState` edge detection. SW-420 is a mechanical contact switch — it bounces on contact, producing multiple LOW→HIGH→LOW transitions within ~20ms. With `delay(100)` in loop, a bounce that resolves within 100ms reads as one clean edge. However, contact noise during an actual vibration can fire `vibrationTrigger` multiple times if the loop catches intermediate bounces. While `accidentActive` guard prevents duplicate ACCIDENT sends, the trigger can still be set incorrectly when sensor is noisy.
**Fix:** Add a software debounce of 200ms:
```cpp
unsigned long lastVibTime = 0;
const unsigned long VIB_DEBOUNCE = 200;

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
```

### BUG 4 — Arduino: FLOOD state re-sends CLEAR immediately after accident (LOW)
**File:** `SafeSense_Arduino.ino`
**Problem:** When `accidentActive` ends (after 5s), `lastSentState` is cleared to `""`. If the water level is still above threshold, the next `updateSystem()` call immediately sends `FLOOD`. If water then drops below threshold on the same loop iteration, it sends `CLEAR`. This is correct behavior — but if the loop reads a noisy water level that briefly dips below 80 and back above 80, it sends `CLEAR` then `FLOOD` in rapid succession. This spams the server.
**Fix:** Apply a 3-second lockout after accident ends before allowing new state sends:
```cpp
unsigned long accidentEndTime = 0;
const unsigned long POST_ACCIDENT_LOCKOUT = 3000;

// In the accident-end block inside updateSystem():
if (millis() - accidentStart >= ACCIDENT_TIME) {
  accidentActive   = false;
  accidentEndTime  = millis();             // ADD THIS LINE
  digitalWrite(R1, LOW); digitalWrite(R2, LOW);
  lastSentState = "";
  Serial.println("[STATE] ACCIDENT ended");
}

// At the top of FLOOD/WARNING/SAFE section in updateSystem():
if (millis() - accidentEndTime < POST_ACCIDENT_LOCKOUT) return;  // ADD THIS GUARD
```

### BUG 5 — ESP32: sendCameraImage uses malloc on internal heap — can fail for large JPEG (CRITICAL)
**File:** `SafeSense_ESP32CAM.ino`
**Problem:** Current code:
```cpp
uint8_t* buf = (uint8_t*)malloc(total);
```
`total` = multipart header (~400 bytes) + JPEG image (~50–150KB for VGA). `malloc()` allocates from internal DRAM (not PSRAM). Internal DRAM on ESP32-S3 is ~300KB total, and after WiFi/stack/HTTPClient overhead, free heap is ~80–120KB. A 100KB+ image malloc will fail → `sendCameraImage` returns false silently → no image attached to alert.
**Fix:** Use `ps_malloc()` (allocates from PSRAM) with a fallback:
```cpp
uint8_t* buf = psramFound()
               ? (uint8_t*)ps_malloc(total)
               : (uint8_t*)malloc(total);
if (!buf) {
  Serial.printf("[Camera] Upload alloc failed — total=%d bytes, free heap=%d\n",
                total, ESP.getFreeHeap());
  http.end();
  return false;
}
```

### BUG 6 — ESP32: Camera init does not retry on transient failure (MEDIUM)
**File:** `SafeSense_ESP32CAM.ino`
**Problem:** `initCamera()` tries once. OV3660 sometimes needs a power stabilization delay after boot before the I2C sensor is detectable. A single-shot init on a cold-started board commonly fails with `ESP_ERR_NOT_FOUND (0x105)` or `ESP_ERR_INVALID_STATE (0x103)` even with correct pins — a single retry after 1 second resolves it.
**Fix:** In `setup()`, replace:
```cpp
cameraReady = initCamera();
```
With:
```cpp
cameraReady = initCamera();
if (!cameraReady) {
  Serial.println("[Boot] Camera retry in 1s...");
  delay(1000);
  cameraReady = initCamera();
}
```

### BUG 7 — ESP32: Render cold-start timeout causes first alert to fail (MEDIUM)
**File:** `SafeSense_ESP32CAM.ino`
**Problem:** The server is hosted on Render free tier. After inactivity, Render spins the server down. First request after spin-down can take 30–60 seconds to respond. Current `http.setTimeout(45000)` (45 seconds) is marginally enough but `sendAlertWithRetry` waits only `2s + 4s` between retries (6s total wait for 3 retries). If all 3 attempts hit a cold-start spin-up, all fail.
**Fix:** Increase retry wait and send a "wake" request on boot:
```cpp
// In setup(), after connectWiFi() and before camera init:
Serial.println("[Boot] Waking server...");
{
  WiFiClientSecure wc; wc.setInsecure();
  HTTPClient hh;
  hh.begin(wc, String(SERVER_URL) + "/api/heartbeat");
  hh.addHeader("Content-Type", "application/json");
  hh.setTimeout(60000);   // 60s — allow full cold-start
  hh.POST("{\"api_key\":\"" + String(API_KEY) + "\",\"device_id\":\"" + String(DEVICE_ID) + "\",\"status\":\"boot\"}");
  hh.end();
  Serial.println("[Boot] Server wake sent");
}
```
Also increase `RETRY_BASE_MS` from 2000 to 5000:
```cpp
const unsigned long RETRY_BASE_MS = 5000;
```

### BUG 8 — ESP32: Duplicate ACCIDENT commands can trigger double image upload (LOW)
**File:** `SafeSense_ESP32CAM.ino`
**Problem:** If Arduino somehow sends `ACCIDENT` twice (e.g., if `lastSentState` is reset by the accident-end block and a second vibration fires immediately after), ESP32 will capture and upload two images and post two alerts within seconds.
**Fix:** Add a cooldown guard in `processCommand()`:
```cpp
unsigned long lastAccidentMs = 0;
const unsigned long ACCIDENT_COOLDOWN = 10000; // 10 seconds

// Inside processCommand(), at the top of the "ACCIDENT" block:
if (cmd == "ACCIDENT") {
  if (millis() - lastAccidentMs < ACCIDENT_COOLDOWN) {
    Serial.println("[CMD] ACCIDENT ignored — cooldown active");
    return;
  }
  lastAccidentMs = millis();
  // ... rest of accident handling
}
```

---

## Do Not Change

- Do not change any WiFi credentials, SERVER_URL, API_KEY, DEVICE_ID, PHONE_NUMBER, or location constants.
- Do not change any pin numbers.
- Do not change the camera pin #define values — they are correct for DFRobot FireBeetle 2 ESP32-S3 (DFR0975) with OV3660.
- Do not add new libraries.
- Do not change WATER_THRESHOLD (80), BLINK_SPEED (300), ACCIDENT_TIME (5000).
- Do not restructure the file or rename any functions.
- Do not change the `sendAlert()` JSON field names — server depends on exact field names.
- Do not change `Serial1.begin(9600, SERIAL_8N1, 44, 43)` — these are correct GPIO pins.
- Do not remove `wdt_enable(WDTO_8S)` from Arduino setup — keep watchdog active.

---

## Verification Checklist

After making all fixes, verify the following in your changes:

### Arduino
- [ ] `sendSMS()` has `wdt_reset()` after every `delay()` call
- [ ] `sendSMS()` calls `gsm.listen()` at the start
- [ ] `sendToESP()` calls `espSerial.listen()` at the start
- [ ] `checkVibration()` has 200ms debounce using `millis()`
- [ ] Post-accident 3-second lockout guard exists in `updateSystem()`
- [ ] `lastVibTime` and `accidentEndTime` variables are declared globally
- [ ] `VIB_DEBOUNCE` and `POST_ACCIDENT_LOCKOUT` constants are declared
- [ ] Watchdog is still enabled at the end of `setup()`

### ESP32
- [ ] `sendCameraImage()` uses `ps_malloc()` with psramFound() check
- [ ] `sendCameraImage()` logs alloc failure with byte count and free heap
- [ ] Camera init retries once after 1 second on failure
- [ ] Server wake POST is sent in `setup()` before camera init
- [ ] `RETRY_BASE_MS` is 5000
- [ ] `lastAccidentMs` variable and `ACCIDENT_COOLDOWN` constant are declared globally
- [ ] ACCIDENT cooldown check is at the top of the ACCIDENT block in `processCommand()`

---

## Output Format

Provide the two complete fixed files in full — no truncation, no ellipsis, no "rest stays the same". Output each file wrapped in a code block with the file path as the label:

```cpp
// arduino/SafeSense_Arduino/SafeSense_Arduino.ino
// ... full file content ...
```

```cpp
// arduino/SafeSense_ESP32CAM/SafeSense_ESP32CAM.ino
// ... full file content ...
```
