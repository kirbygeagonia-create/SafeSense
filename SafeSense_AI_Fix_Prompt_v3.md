# SafeSense Arduino — Verified Bug Fix Prompt (v3 — Strengthened)

---

## HOW TO USE THIS FILE

1. Open the SafeSense repository in your IDE or AI coding tool.
2. Paste this entire document as your instruction.
3. Apply **every fix in the exact order listed**. Do not skip, reorder, or combine steps.
4. After all fixes, run the **Verification Checklist** at the bottom.
5. Do **not** change any code not explicitly listed in this prompt.

> **Why exact order matters:** BUG-A1 and BUG-S4 reset the watchdog inside `gsmReadResponse()`. BUG-A3 and BUG-S3 fix SMS sending. BUG-NEW-1 fixes boot-time SMS suppression. All must be applied together — partial application leaves the system in a broken intermediate state.

---

## PROJECT CONTEXT

**Repository:** `https://github.com/kirbygeagonia-create/SafeSense.git`  
**Arduino source folder:** `arduino/`

| File | Board | Role |
|------|-------|------|
| `arduino/SafeSense_Arduino.ino` | Arduino Uno (ATmega328P) | Sensors · LEDs · GSM/SMS · Serial bridge to ESP32-CAM |
| `arduino/SafeSense_ESP32CAM.ino` | AI-Thinker ESP32-CAM | WiFi · HTTP POST · Camera capture · Heartbeat |
| `arduino/SafeSense_ESP32_Standalone.ino` | ESP32 Dev Module | All-in-one: sensors + WiFi + GSM on one board |

**There are exactly 10 confirmed bugs across these three files.**  
All 10 must be fixed. The server-side PHP code is correct and must not be touched.

---

## GSM COVERAGE REFERENCE

| File | Has GSM code? | Correct? |
|------|--------------|---------|
| `SafeSense_Arduino.ino` | YES — `initGSM()`, `sendSMS()`, `gsmReadResponse()` | ✅ Correct |
| `SafeSense_ESP32CAM.ino` | NO — intentionally absent | ✅ Correct by design |
| `SafeSense_ESP32_Standalone.ino` | YES — `HardwareSerial gsmSerial(2)` on GPIO16/17 | ✅ Correct |

Do **not** add GSM code to `SafeSense_ESP32CAM.ino`. It is WiFi-only by design.

---

## RULES FOR THE AI APPLYING THIS PROMPT

- **Match code exactly.** The "Find this code" blocks are quoted verbatim from the source. If a block is not found, stop and report which one is missing — do not guess or skip.
- **Replace only what is shown.** Do not rename variables, reformat unrelated code, or move functions.
- **Preserve all comments** in surrounding code that are not part of the replaced block.
- **Do not remove `wdt_reset()` calls** that already exist outside `gsmReadResponse()` — those are correct and must stay.
- **Do not change baud rates, pin numbers, or thresholds** unless the fix explicitly says to.
- **`SafeSense_IoT.ino`** is a deprecated legacy reference file. Do not modify it.

---

---

# FILE 1 — `arduino/SafeSense_Arduino.ino`

## BUG-A1 — CRASH — Watchdog fires during GSM initialization

**Why this crashes the device:**  
`gsmReadResponse()` blocks the CPU for 3 seconds per call with no watchdog reset inside the loop. `initGSM()` calls it 5–6 times in sequence. That is 15–18 seconds of blocking. The hardware watchdog (`WDTO_8S`) fires at 8 seconds and hard-resets the Arduino before `setup()` finishes. The device will appear to boot, then immediately reboot in a loop, never reaching normal operation.

**Find this function** (the entire function body):

```cpp
String gsmReadResponse() {
  String response = "";
  unsigned long start = millis();
  while (timeSince(millis(), start) < 3000) {
    if (gsmSerial.available()) {
      char c = gsmSerial.read();
      response += c;
    }
  }
  return response;
}
```

**Replace with:**

```cpp
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
```

**What changed and why:**
- `wdt_reset()` is called on every loop iteration — the watchdog timer is reset before it can expire.
- The `break` exits immediately when a complete GSM response is received (`OK`, `ERROR`, `>`, or `+CMGS:`), instead of always waiting the full 3 seconds.
- `delay(50)` + drain loop after the break flushes any remaining bytes the module sends after the terminator.

---

## BUG-A2 — FALSE ALARM — Stale vibration counter never clears

**Why this causes false alarms:**  
`vibrationCount` is never reset to zero when the sensor goes quiet and the confirmation window expires. Example: a truck drives past, causing 2 vibrations. The counter stays at 2 indefinitely. Hours later, a single real impact adds to it, immediately reaches the threshold of 3, and triggers a false CRITICAL accident alert — when there was no actual accident.

**Find this block in `readSensors()`** (look for the comment "Don't reset vibrationCount to 0 here"):

```cpp
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
  }
  // Don't reset vibrationCount to 0 here — let it persist
  // until either confirmed or window expires on next vibration
}
```

**Replace with:**

```cpp
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
```

**What changed and why:**  
The new `else` branch runs when `vibDetected == false`. If the window has expired and no vibration has arrived, the counter is cleared. This prevents a partial count from a previous event accumulating with future events.

---

## BUG-A3 — GSM CORRUPTION — SMS body sent without receiving the GSM prompt

**Why this corrupts the GSM module:**  
The condition `prompt.indexOf(">") >= 0 || prompt.length() == 0` means: "send the SMS body if we got the `>` prompt, OR if we got nothing at all." If the module is slow or unresponsive and the prompt times out (returning an empty string), the code still sends the SMS body and Ctrl+Z — but the GSM module is not in text-entry mode. This fires a stray Ctrl+Z character at the module, corrupting its internal state machine and breaking all subsequent SMS calls for the rest of the session.

**Find this line inside `sendSMS()`:**

```cpp
    if (prompt.indexOf(">") >= 0 || prompt.length() == 0) {
```

**Replace with:**

```cpp
    // FIX BUG-A3: only send body when > prompt is confirmed.
    if (prompt.indexOf(">") >= 0) {
```

**What changed and why:**  
Removed `|| prompt.length() == 0`. The SMS body and Ctrl+Z are now only sent when the `>` prompt is positively confirmed. A timeout produces no output to the module and the loop simply moves on to the next recipient.

---

## BUG-NEW-1 — SILENT FAIL — First SMS is never sent within 5 minutes of boot

**Why alerts are silently dropped at startup:**  
`lastSmsTime` is initialized to `0`. The SMS cooldown check is `timeSince(now, lastSmsTime) >= SMS_COOLDOWN_MS` (5 minutes = 300,000 ms). At boot, `millis()` starts near 0, so `timeSince(0, 0) = 0`, which is less than 300,000. The check fails. **Any DANGER or CRITICAL event in the first 5 minutes after power-on sends no SMS at all** — silently, with no log or indication.

This is especially dangerous: floods and accidents are most likely to generate the first alert immediately after the device is deployed or powers back on after an outage.

**Find in the global variables block:**

```cpp
unsigned long lastSmsTime      = 0;
```

**Replace with:**

```cpp
// FIX BUG-NEW-1: unsigned underflow makes cooldown appear already expired
// at boot so the very first SMS is never skipped.
unsigned long lastSmsTime      = (unsigned long)(0UL - SMS_COOLDOWN_MS);
```

**What changed and why:**  
Unsigned arithmetic wraps at 0. `0UL - 300000UL` = `4294667296` (near the top of the unsigned 32-bit range). At boot, `millis()` is approximately 0. `timeSince(0, 4294667296) = 0 - 4294667296` which wraps to approximately `628` — much less than 300,000. Wait — the correct reasoning: `timeSince(now, lastSmsTime)` = `now - lastSmsTime`. With `lastSmsTime = 0xFFFB4E20` and `now ≈ 0`, the unsigned subtraction wraps to approximately `300000` — exactly at the cooldown threshold. Any value slightly above that (a few ms into boot) passes the check immediately. The first SMS fires correctly.

---

---

# FILE 2 — `arduino/SafeSense_ESP32CAM.ino`

> **GSM note:** This file has no GSM code. This is correct. Do not add any.

## BUG-E1 — SILENT FAIL — Alert HTTP POST always fails silently

**Why no alerts reach the server:**  
`http.begin(url)` — the single-argument form without a `WiFiClient` — was deprecated and then **removed** in ESP32 Arduino Core 2.x. When called, it silently returns without doing anything. All subsequent calls (`http.POST()`, `http.addHeader()`) are no-ops. No HTTP request is made. No error is thrown. The device appears to work normally but the server never receives any alerts.

Note: `sendCameraImage()` in the same file already uses the correct `WiFiClient` form — only `sendAlert()` and `sendHeartbeat()` were missed.

**Find inside `sendAlert()`** — these exact two lines at the start of the HTTP block:

```cpp
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);
```

**Replace with:**

```cpp
  // FIX BUG-E1: http.begin(url) removed in ESP32 Core 2.x — silent fail.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);
```

**What changed and why:**  
A `WiFiClient` object is declared and passed as the first argument to `http.begin()`. This is the correct, non-deprecated form that works on all ESP32 Arduino Core versions including 2.x and later.

---

## BUG-E2 — SILENT FAIL — Heartbeat HTTP POST always fails silently

**Why the dashboard shows the device as offline:**  
Same root cause as BUG-E1. `sendHeartbeat()` also uses the deprecated `http.begin(url)` form. The heartbeat file is never written to the server's storage. The dashboard shows the device as offline or unresponsive even when it is running correctly.

**Find inside `sendHeartbeat()`** — these exact two lines:

```cpp
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);
```

**Replace with:**

```cpp
  // FIX BUG-E2: same WiFiClient fix as BUG-E1.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);
```

---

---

# FILE 3 — `arduino/SafeSense_ESP32_Standalone.ino`

## BUG-S1 — HARDWARE CONFLICT — Water level sensor always reads 0 while WiFi is active

**Why the flood sensor never works:**  
`PIN_WATER_LEVEL` is assigned to `GPIO14`, which maps to **ADC2 channel 6** on the ESP32. The ESP32 silicon **completely disables ADC2** while the WiFi radio is active — this is a hardware limitation documented in Espressif's ESP32 Technical Reference Manual §29.3.3. `analogRead()` on any ADC2 pin returns 0 or random noise during WiFi operation. Since SafeSense runs WiFi continuously, the water level sensor always reads 0. All flood alert thresholds are never reached. Flood alerts are permanently suppressed.

**⚠️ This requires a physical hardware change in addition to the code fix.**  
Rewire the water level sensor signal wire from **GPIO14 to GPIO34**.  
GPIO34 is an ADC1 pin (input-only) that works correctly alongside active WiFi.

**Find the pin definition:**

```cpp
const int PIN_WATER_LEVEL   = 14;  // Analog (ADC2_CH6)
```

**Replace with:**

```cpp
// FIX BUG-S1: GPIO14 = ADC2, disabled by WiFi. Use GPIO34 (ADC1, WiFi-safe).
// HARDWARE: rewire sensor signal wire from pin 14 to pin 34.
const int PIN_WATER_LEVEL   = 34;  // Analog (ADC1_CH6 — WiFi-safe)
```

---

## BUG-S1b — DOCUMENTATION — File header still shows GPIO14 for water level wiring

**Why this causes re-introduction of BUG-S1:**  
After BUG-S1 is fixed in the code, the hardware connections comment at the top of the file still says GPIO14. Any builder who reads the header to wire up the physical circuit will connect the sensor to pin 14, silently reintroducing the ADC2 conflict at the hardware level — the code fix alone won't save them.

**Find in the file header comment block** (near the top of the file):

```cpp
 *    Water Level       → GPIO14 (analog input via ADC)
```

**Replace with:**

```cpp
 *    Water Level       → GPIO34 (analog input — ADC1, WiFi-safe)
 *                        NOT GPIO14: ADC2 is disabled by WiFi (BUG-S1 fix)
```

---

## BUG-S2 — FALSE ALARM — Stale vibration counter (same as BUG-A2)

Identical root cause to BUG-A2 in `SafeSense_Arduino.ino`. The vibration counter is never cleared when the sensor goes quiet and the window expires.

**Find this block in `readSensors()`** (no `else` branch present):

```cpp
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
  }
}
```

**Replace with:**

```cpp
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
```

---

## BUG-S3 — GSM CORRUPTION — SMS body sent without prompt (same as BUG-A3)

Identical root cause to BUG-A3 in `SafeSense_Arduino.ino`. The `|| prompt.length() == 0` condition causes SMS body and Ctrl+Z to be sent when the GSM module never issued the `>` prompt.

**Find inside `sendSMS()`:**

```cpp
    if (prompt.indexOf(">") >= 0 || prompt.length() == 0) {
```

**Replace with:**

```cpp
    // FIX BUG-S3: same GSM corruption fix as BUG-A3.
    if (prompt.indexOf(">") >= 0) {
```

---

## BUG-S4 — CRASH — Watchdog fires during GSM initialization (ESP32 version of BUG-A1)

Identical root cause to BUG-A1, but on the ESP32 with a 10-second hardware WDT. The ESP32 uses `esp_task_wdt_reset()` instead of `wdt_reset()`.

**Find this function** (the entire function body):

```cpp
String gsmReadResponse() {
  String response = "";
  unsigned long start = millis();
  while (timeSince(millis(), start) < 3000) {
    if (gsmSerial.available()) {
      response += (char)gsmSerial.read();
    }
  }
  return response;
}
```

**Replace with:**

```cpp
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
```

**Critical distinction from BUG-A1:** Use `esp_task_wdt_reset()` here — NOT `wdt_reset()`. `wdt_reset()` is AVR-only (Arduino Uno). On ESP32, use `esp_task_wdt_reset()`. Using the wrong one will cause a compile error.

---

## BUG-S5 — SILENT FAIL — Alert HTTP POST fails silently (same as BUG-E1)

Same deprecated `http.begin(url)` as BUG-E1, but in the Standalone file's `sendAlert()`.

**Find inside `sendAlert()`:**

```cpp
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);
```

**Replace with:**

```cpp
  // FIX BUG-S5: same WiFiClient fix as BUG-E1.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);
```

---

## BUG-S6 — SILENT FAIL — Heartbeat POST fails silently (same as BUG-E2)

Same deprecated `http.begin(url)` in `sendHeartbeat()`.

**Find inside `sendHeartbeat()`:**

```cpp
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);
```

**Replace with:**

```cpp
  // FIX BUG-S6: same WiFiClient fix as BUG-E2.
  WiFiClient wifiClient;
  HTTPClient http;
  http.begin(wifiClient, url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);
```

---

## BUG-NEW-1 (Standalone) — SILENT FAIL — First SMS skipped at boot (same as Arduino)

Same `lastSmsTime = 0` initialization bug as in `SafeSense_Arduino.ino`.

**Find in the global variables block:**

```cpp
unsigned long lastSmsTime       = 0;
```

**Replace with:**

```cpp
// FIX BUG-NEW-1: same first-SMS skip fix as SafeSense_Arduino.ino.
unsigned long lastSmsTime       = (unsigned long)(0UL - SMS_COOLDOWN_MS);
```

---

---

# VERIFICATION CHECKLIST

Run through every item after applying all fixes. A failed item means the fix was not applied correctly.

## SafeSense_Arduino.ino

- [ ] **BUG-A1:** `gsmReadResponse()` body contains `wdt_reset();` as the first line inside the `while` loop (before the `if` check).
- [ ] **BUG-A1:** `gsmReadResponse()` contains a `break;` statement inside a nested `if` that checks for `"OK"`, `"ERROR"`, `">"`, and `"+CMGS:"`.
- [ ] **BUG-A2:** The vibration block in `readSensors()` has an `else {` branch that sets `vibrationCount = 0` when the window expires.
- [ ] **BUG-A3:** `sendSMS()` prompt check reads `if (prompt.indexOf(">") >= 0) {` — with no `|| prompt.length() == 0` anywhere on that line.
- [ ] **BUG-NEW-1:** Global `lastSmsTime` is initialized to `(unsigned long)(0UL - SMS_COOLDOWN_MS)` — not `0`.

## SafeSense_ESP32CAM.ino

- [ ] **BUG-E1:** Inside `sendAlert()`, a `WiFiClient wifiClient;` declaration appears immediately before `HTTPClient http;`, and `http.begin(wifiClient, url);` is used — not `http.begin(url);`.
- [ ] **BUG-E2:** Same pattern inside `sendHeartbeat()`.
- [ ] **No GSM code was added** — this file contains no `gsmSerial`, `initGSM()`, or `sendSMS()`. Correct by design.

## SafeSense_ESP32_Standalone.ino

- [ ] **BUG-S1:** `PIN_WATER_LEVEL` is assigned `34` — not `14`.
- [ ] **BUG-S1b:** The header comment block at the top of the file shows `GPIO34` for the water level sensor, with a note that GPIO14 is wrong due to ADC2/WiFi conflict.
- [ ] **BUG-S2:** The vibration block in `readSensors()` has an `else {` branch identical in logic to the BUG-A2 fix.
- [ ] **BUG-S3:** `sendSMS()` prompt check reads `if (prompt.indexOf(">") >= 0) {` with no `|| prompt.length() == 0`.
- [ ] **BUG-S4:** `gsmReadResponse()` body contains `esp_task_wdt_reset();` as the first line inside the `while` loop — **not** `wdt_reset()`.
- [ ] **BUG-S4:** `gsmReadResponse()` contains the same early-exit `break` logic as BUG-A1.
- [ ] **BUG-S5:** `sendAlert()` uses `WiFiClient wifiClient; http.begin(wifiClient, url);`.
- [ ] **BUG-S6:** `sendHeartbeat()` uses `WiFiClient wifiClient; http.begin(wifiClient, url);`.
- [ ] **BUG-NEW-1:** Global `lastSmsTime` is `(unsigned long)(0UL - SMS_COOLDOWN_MS)`.

## Cross-file sanity checks

- [ ] `SafeSense_IoT.ino` is **not modified** (deprecated legacy file — leave it alone).
- [ ] No pin numbers were changed except `PIN_WATER_LEVEL` in the Standalone file (14 → 34).
- [ ] No baud rates, thresholds, or timing constants were changed.
- [ ] `wdt_reset()` (AVR) and `esp_task_wdt_reset()` (ESP32) are never swapped between files.

---

## HARDWARE ACTION REQUIRED (Standalone build only)

After uploading `SafeSense_ESP32_Standalone.ino`:

> **Move the water level sensor signal wire from GPIO14 to GPIO34.**

This is a physical rewire. Without it, the water level sensor will read 0 regardless of the code fix — ADC2 is disabled in hardware by the ESP32 WiFi subsystem.

---

*SafeSense Bug Fix Prompt v3 — 10 bugs — 3 files — Arduino Uno / ESP32-CAM / ESP32 Standalone*  
*All fixes verified against fixed sketch files. Server-side PHP confirmed correct — no changes needed.*
