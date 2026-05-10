# SafeSense IoT — Two-Lane Audit Report & Project Impact Documentation
**Date:** May 2026
**Repository:** https://github.com/kirbygeagonia-create/SafeSense.git
**Scope:** Wiring diagram analysis, two-lane LED architecture gap, ESP32-S3 vs ESP32-CAM mismatch, project relevance and scientific/social impact

> **How to use this document:**
> This is the context and background report. Read it to understand *why* changes are needed.
> The actual code fixes are in the companion file: **`SafeSense_Complete_Fix_Prompt_Final.md`**
> Feed both files to your AI — this one first as context, then the prompt file as the instruction.

---

## Part 1 — Wiring Diagram Analysis

### What the diagram shows (your plan)

```
ESP32-S3 AI CAM
  43/T  ──────────────► RX  (Arduino Pin 0 / D0)
  44/R  ◄────────────── TX  (Arduino Pin 1 / D1)
  GND   ──────────────── GND
  3V3   ──────────────── 3.3V

ARDUINO UNO
  5V  ──────────────────────────── (power rail)
  GND ──────────────────────────── (ground rail)

  A0  ◄── Water Level Sensor OUT
  D2  ◄── Rain Sensor DO
  D3  ◄── Vibration Sensor OUT

  D4  ──[220Ω]──► LED1 ── GND    (Lane 1 — Green)
  D5  ──[220Ω]──► LED2 ── GND    (Lane 1 — Yellow)
  D6  ──[220Ω]──► LED3 ── GND    (Lane 1 — Red)
  D7  ──[220Ω]──► LED4 ── GND    (Lane 2 — Green)
  D8  ──[220Ω]──► LED5 ── GND    (Lane 2 — Yellow)
  D9  ──[220Ω]──► LED6 ── GND    (Lane 2 — Red)
```

---

## Part 2 — Gap Analysis: Diagram vs Current Sketch

### 2.1 LED Count Mismatch — CRITICAL GAP ❌

| Item | Your Diagram (plan) | Current Sketch (code) | Status |
|------|--------------------|-----------------------|--------|
| LED count | **6 LEDs** (3 per lane × 2 lanes) | **3 LEDs** (1 lane only) | ❌ MISMATCH |
| Lane 1 Green | D4 | D10 | ❌ Wrong pin |
| Lane 1 Yellow | D5 | D11 | ❌ Wrong pin |
| Lane 1 Red | D6 | D12 | ❌ Wrong pin |
| Lane 2 Green | D7 | Not defined | ❌ Missing |
| Lane 2 Yellow | D8 | Not defined | ❌ Missing |
| Lane 2 Red | D9 | Not defined | ❌ Missing |

**Root cause:** The sketch was written for a single-lane prototype. The two-lane concept was not yet implemented when the firmware was written. The hardware plan (your diagram) is correct. The code needs to catch up.

---

### 2.2 ESP32-S3 AI CAM vs AI-Thinker ESP32-CAM — CRITICAL MISMATCH ❌

Your wiring diagram shows an **ESP32-S3 AI CAM**. The sketch (`SafeSense_ESP32CAM.ino`) is written for the **AI-Thinker ESP32-CAM** (which uses the original ESP32 chip, not ESP32-S3).

These are **two completely different chips** with different UART pin numbers, camera pin layouts, and Arduino board targets:

| Property | AI-Thinker ESP32-CAM (current sketch) | ESP32-S3 AI CAM (your diagram) |
|----------|---------------------------------------|-------------------------------|
| UART TX pin | GPIO1 (U0T) | GPIO43 |
| UART RX pin | GPIO3 (U0R) | GPIO44 |
| Camera XCLK pin | GPIO0 | GPIO15 |
| Camera PWDN pin | GPIO32 | Not used (-1) |
| Board target in Arduino IDE | `AI Thinker ESP32-CAM` | `ESP32S3 Dev Module` |
| Arduino Core | ESP32 | ESP32-S3 |
| Onboard LED | GPIO33 (active LOW) | GPIO2 (active HIGH) |

**Impact:** If you wire the ESP32-S3 according to your diagram (GPIO43/44) and upload the current sketch unchanged, the Serial communication between the Arduino and the ESP32-S3 will not work at all. The sketch configures `Serial` on GPIO1/GPIO3, which are the wrong pins for the ESP32-S3. No sensor data or alerts will be transmitted.

**Decision made:** The project uses the **ESP32-S3 AI CAM**. The sketch is being updated accordingly. See the companion prompt file for all code changes.

---

### 2.3 Pin Conflicts on Current Arduino Sketch — IMPORTANT ⚠️

The current sketch assigns D7 and D8 to the GSM SoftwareSerial:

```cpp
const int PIN_GSM_RX = 7;   // SIM900A TX → Arduino D7
const int PIN_GSM_TX = 8;   // SIM900A RX → Arduino D8
```

Your diagram assigns D7 and D8 to **LED4 and LED5** (Lane 2 Green and Yellow).

This is a **direct pin conflict** — D7 and D8 cannot serve both GSM and LEDs at the same time. The resolution is to move GSM to D10 and D11, which are freed up when the old single-lane LEDs (previously on D10/D11/D12) are reassigned to D4–D9.

Similarly, D9 was the buzzer pin. Your diagram assigns D9 to Lane 2 Red LED. The buzzer moves to D12.

---

### 2.4 Arduino Uno Pin Budget — Final Resolution

| Pin | Old Assignment | New Assignment | Notes |
|-----|---------------|----------------|-------|
| D0  | Hardware Serial RX (to ESP32) | Hardware Serial RX (to ESP32-S3) | ✅ Unchanged |
| D1  | Hardware Serial TX (to ESP32) | Hardware Serial TX (to ESP32-S3) | ✅ Unchanged |
| D2  | Vibration Sensor (sketch) | Rain Sensor DO (diagram — corrected) | ⚠️ Pin swap fixed |
| D3  | Rain Sensor (sketch) | Vibration Sensor OUT (diagram — corrected) | ⚠️ Pin swap fixed |
| D4  | (unused) | Lane 1 Green LED | ✅ New |
| D5  | (unused) | Lane 1 Yellow LED | ✅ New |
| D6  | (unused) | Lane 1 Red LED | ✅ New |
| D7  | GSM SoftSerial RX | Lane 2 Green LED | ✅ Conflict resolved — GSM moved |
| D8  | GSM SoftSerial TX | Lane 2 Yellow LED | ✅ Conflict resolved — GSM moved |
| D9  | Buzzer | Lane 2 Red LED | ✅ Conflict resolved — Buzzer moved |
| D10 | Lane 1 Green LED (old) | GSM SoftSerial RX (moved here) | ✅ Freed and reused |
| D11 | Lane 1 Yellow LED (old) | GSM SoftSerial TX (moved here) | ✅ Freed and reused |
| D12 | Lane 1 Red LED (old) | Buzzer (moved here) | ✅ Freed and reused |
| A0  | Water Level Sensor | Water Level Sensor | ✅ Unchanged |

Every pin is accounted for. No conflicts remain after the reassignment.

---

### 2.5 Rain/Vibration Sensor Pin Swap ⚠️

| Source | Rain Sensor Pin | Vibration Sensor Pin |
|--------|----------------|---------------------|
| Your diagram | D2 | D3 |
| Current sketch | D3 | D2 |

The two pins are swapped between the diagram and the sketch. The diagram is the physical wiring plan and is taken as the authority. The sketch is corrected to match: Rain → D2, Vibration → D3.

---

## Part 3 — Two-Lane Architecture Design

### What "two-lane" means for SafeSense

A two-lane road has traffic moving in **both directions**. Hazard conditions — flooding, accident, rain — affect the road segment regardless of which direction a vehicle is coming from. Each lane needs its own set of visual warnings so that drivers approaching from **either direction** receive the alert before they reach the hazard zone.

### LED layout per lane

```
LANE 1 (Direction A — e.g. Northbound)     LANE 2 (Direction B — e.g. Southbound)
────────────────────────────────────────   ────────────────────────────────────────
LED1 — Green  (D4) — Safe, proceed         LED4 — Green  (D7) — Safe, proceed
LED2 — Yellow (D5) — Warning, slow down    LED5 — Yellow (D8) — Warning, slow down
LED3 — Red    (D6) — Danger, stop          LED6 — Red    (D9) — Danger, stop
```

### How both lanes are driven

For the current single-sensor setup, both lanes share the same sensor data and always show the same alert level. This is intentional and correct — one sensor monitors the road segment, both lanes respond simultaneously. The value of having two independent LED sets is purely physical: **drivers from both directions see the warning no matter which way they are facing.**

> **Future upgrade path:** Adding a second water level sensor and second vibration sensor, one per lane, would allow independent per-lane alert levels. For example, one lane could be flooded while the other is passable. This is not implemented now but the two-lane LED architecture already supports it — only the sensor and evaluation logic would need to change.

---

## Part 4 — Summary of All Issues Found

### Issues in `SafeSense_Arduino.ino`

| ID | Severity | Issue | Resolution |
|----|----------|-------|-----------|
| ISSUE-1 | 🔴 Critical | Only 3 LEDs coded (1 lane). Diagram requires 6 (2 lanes). | Add Lane 2 LED pins D7/D8/D9, update `updateLEDs()` to drive all 6 |
| ISSUE-2 | 🔴 Critical | LED pins are D10/D11/D12. Diagram requires D4/D5/D6 for Lane 1. | Reassign all LED pins to D4–D9 |
| ISSUE-3 | 🔴 Critical | GSM on D7/D8 conflicts with Lane 2 LEDs on D7/D8. | Move GSM SoftSerial to D10/D11 |
| ISSUE-4 | 🟠 Important | Buzzer on D9 conflicts with Lane 2 Red LED on D9. | Move Buzzer to D12 |
| ISSUE-5 | 🟠 Important | Rain and Vibration pins swapped vs diagram (D2↔D3). | Correct sketch to match diagram: Rain=D2, Vibration=D3 |

### Issues in `SafeSense_ESP32CAM.ino`

| ID | Severity | Issue | Resolution |
|----|----------|-------|-----------|
| ISSUE-6 | 🔴 Critical | UART on GPIO1/GPIO3 (AI-Thinker). ESP32-S3 uses GPIO43/GPIO44. Serial comms will not work. | Switch to `Serial1.begin(9600, SERIAL_8N1, 44, 43)` throughout |
| ISSUE-7 | 🔴 Critical | Camera pin definitions are for AI-Thinker OV2640. ESP32-S3 has different pin mapping. | Replace all camera `#define` values with ESP32-S3 equivalents |
| ISSUE-8 | 🟡 Documentation | Header, board target, LED polarity, and setup instructions describe AI-Thinker. | Update header comment and LED logic for ESP32-S3 |

### Already correct — confirmed, do not change

| Item | Status |
|------|--------|
| All 10 previously audited firmware bugs | ✅ Confirmed fixed in repo |
| GSM `gsmReadResponse()` with `wdt_reset()` and early exit | ✅ Correct |
| `lastSmsTime` boot initialization (underflow trick) | ✅ Correct |
| Vibration counter reset in `else` branch | ✅ Correct |
| SMS `>` prompt check (no `|| prompt.length() == 0`) | ✅ Correct |
| `sendAlert()` and `sendHeartbeat()` using `WiFiClient` | ✅ Correct |
| Standalone `PIN_WATER_LEVEL = 34` (ADC1, WiFi-safe) | ✅ Correct |
| `evaluateAlertLevel()` logic and thresholds | ✅ Correct |
| SMS message content and 160-character truncation | ✅ Correct |
| `$SAFE,...` serial data protocol between boards | ✅ Correct |
| 5-sample averaging on water level ADC reads | ✅ Correct |
| Overflow-safe `timeSince()` function | ✅ Correct |

---

## Part 5 — ESP32-S3 Board Decision

The project uses the **ESP32-S3 AI CAM**. This is confirmed by the wiring diagram which shows GPIO43 and GPIO44 as the UART pins. All code changes in the companion prompt file are written for the ESP32-S3.

### Key differences that affect the code

**UART:** ESP32-S3 uses `Serial1` on GPIO43 (TX) and GPIO44 (RX) to communicate with the Arduino. `Serial` (UART0) on the ESP32-S3 is reserved for USB CDC debug output and remains available for the Serial Monitor.

**Camera pins:** The ESP32-S3 AI CAM uses a completely different set of GPIO numbers for the camera interface compared to the AI-Thinker board. The `XCLK`, `SIOD`, `SIOC`, `Y2–Y9`, `VSYNC`, `HREF`, and `PCLK` pin numbers are all different.

**LED polarity:** The onboard status LED on the ESP32-S3 AI CAM is **active HIGH** (HIGH = on, LOW = off), the opposite of the AI-Thinker (which is active LOW). All LED state logic in the sketch is updated accordingly.

**Board target in Arduino IDE:** Select `ESP32S3 Dev Module` instead of `AI Thinker ESP32-CAM`. Enable `USB CDC On Boot` so the Serial Monitor works over USB.

---

## Part 6 — Project Relevance and Impact

### Why does rain make roads dangerous? The science.

Yes — roads become measurably more dangerous when it rains. Here is why, with the specific mechanisms:

#### 6.1 Hydroplaning

When rain falls on a road, water fills the microscopic grooves in the pavement. At speeds above roughly 80–90 km/h (or lower on worn tyres), a thin film of water builds up under the tyre faster than the tyre can displace it. The tyre literally lifts off the road surface and rides on water — this is hydroplaning. The driver loses all steering and braking control instantly. It happens without warning. Even at 60 km/h on a degraded road, partial hydroplaning significantly increases stopping distance.

#### 6.2 Reduced friction coefficient

Dry asphalt has a friction coefficient (μ) of roughly 0.7–0.8. Wet asphalt drops to 0.4–0.5. This means on a wet road your braking distance is approximately **40–70% longer** than on a dry road at the same speed. For a vehicle travelling at 60 km/h, dry stopping distance is about 36 meters. Wet stopping distance is 55–65 meters. If a flooded section appears ahead, many drivers simply do not have enough road left to stop.

#### 6.3 First-rain effect — the most dangerous window

The **first 30 minutes of rainfall** after a dry period are statistically the most dangerous. During dry weather, vehicles deposit oil, rubber particles, and dust on the road surface. When the first rain hits, water mixes with this layer before washing it away, creating a slick film that can be more slippery than either dry or fully wet conditions. Research from the University of North Carolina shows crash risk is highest in this early-rain window — exactly when SafeSense's rain sensor fires its first warning.

#### 6.4 Flash flooding on Philippine roads

The Philippines, particularly Bukidnon and Northern Mindanao, receives significant rainfall from tropical systems. Roads in hilly or low-lying barangays can flood rapidly from upstream runoff — the road surface can go from dry to 20 cm of fast-moving water within 15–30 minutes. Drivers who encounter this unexpectedly can lose vehicle control. Fast-moving shallow water can overturn light vehicles or motorcycles. Motorcycles — the dominant transport mode in rural barangays — are especially vulnerable: as little as 15 cm of moving water can knock a motorcycle rider over.

#### 6.5 Reduced visibility

Rain reduces forward visibility through the windscreen and reduces the effective range of headlights. Combined with night conditions, this creates situations where a flooded road section is simply not visible until the driver is already in it. SafeSense's LED warnings are visible day and night, at distance, before the driver reaches the hazard.

---

### Why SafeSense matters

#### 6.6 The gap SafeSense fills

Current flood alert systems in the Philippines (PAGASA, LGU text alerts) operate at the **weather station level** — they cover entire municipalities or river basins. They do not tell a driver whether the specific 50-meter stretch of road they are about to cross is passable right now. SafeSense operates at the **road segment level**, giving real-time sensor data specific to one physical road location.

#### 6.7 Who benefits

| Stakeholder | How SafeSense helps |
|-------------|-------------------|
| Motorists (especially motorcycle riders) | Visual LED warning before entering a flooded or dangerous stretch |
| Barangay emergency response | SMS and dashboard alert within seconds of sensor threshold crossing |
| Hospital emergency department | Early warning gives staff time to prepare for potential casualties |
| Police / traffic enforcement | Alert allows deployment of personnel before accidents happen |
| MDRRMO (Municipal Disaster Risk Reduction) | Real-time data for road closure decisions |

#### 6.8 Vibration sensor — accident and hazard detection

Roads in Northern Mindanao pass through mountainous terrain where rockfalls and landslides triggered by heavy rain are real hazards. The vibration sensor detects:
- Ground impact from falling rocks or debris
- Structural impact from a vehicle losing control
- Repeated heavy impacts indicating road surface deterioration

When combined with rain detection, a confirmed vibration event during active rain is a strong indicator of a road incident requiring immediate response. This is the exact logic in `evaluateAlertLevel()` — vibration confirmed + rain active = CRITICAL alert.

#### 6.9 The two-lane LED design — why both directions matter

Single-sided warning signs only protect drivers approaching from one direction. A flood or hazard on a two-lane road affects traffic in both directions. By placing Lane 1 LEDs visible to northbound traffic and Lane 2 LEDs visible to southbound traffic, SafeSense ensures no driver approaches the hazard unwarned regardless of their direction of travel. This is the same principle used by professional Variable Message Signs (VMS) on highways — SafeSense brings a simplified, affordable version of this to barangay roads.

#### 6.10 Cost and accessibility

Commercial road flood sensors cost tens of thousands of pesos and require professional installation and maintenance contracts. SafeSense uses commodity components:

| Component | Approximate cost |
|-----------|----------------|
| Arduino Uno | ₱350–500 |
| ESP32-S3 AI CAM | ₱300–500 |
| Water level sensor | ₱50–150 |
| Rain sensor module | ₱50–100 |
| Vibration sensor | ₱50–80 |
| SIM900A GSM module | ₱300–500 |
| 6 LEDs + resistors | ₱30–50 |
| Power supply components | ₱300–500 |
| **Total per monitoring point** | **≈ ₱1,500–2,300** |

A single unit protects one road segment. Multiple units connected to the same SafeSense dashboard can give a barangay or municipality comprehensive real-time road safety monitoring at a cost that is realistic for LGU procurement and community funding.

#### 6.11 Academic and capstone relevance

SafeSense addresses all three dimensions that make a capstone project strong:

1. **Technical depth** — embedded systems (Arduino, ESP32-S3), custom serial communication protocol, IoT HTTP POST with API key authentication, GSM SMS, real-time web dashboard, multi-table relational database, camera integration, cloud deployment.

2. **Social relevance** — road safety and flood monitoring directly address one of the leading causes of injury and death in Philippine barangays. DOST and CHED both identify disaster risk reduction technology as a national priority research area.

3. **Deployability** — SafeSense is not theoretical. It runs on real hardware, sends real SMS alerts, posts to a live cloud server (Render + Aiven MySQL), and can be physically installed on an actual road segment in Brgy. Casisang or any comparable barangay.

---

## Part 7 — Complete Issue Register

### All issues — current status

| ID | File | Severity | Issue | Status |
|----|------|----------|-------|--------|
| ISSUE-1 | `SafeSense_Arduino.ino` | 🔴 Critical | Only 3 LEDs, needs 6 for two lanes | Fix in companion prompt |
| ISSUE-2 | `SafeSense_Arduino.ino` | 🔴 Critical | LED pins D10/D11/D12, need D4–D9 | Fix in companion prompt |
| ISSUE-3 | `SafeSense_Arduino.ino` | 🔴 Critical | GSM on D7/D8 conflicts with Lane 2 LEDs | Fix in companion prompt |
| ISSUE-4 | `SafeSense_Arduino.ino` | 🟠 Important | Buzzer on D9 conflicts with Lane 2 Red LED | Fix in companion prompt |
| ISSUE-5 | `SafeSense_Arduino.ino` | 🟠 Important | Rain/Vibration sensor pins swapped (D2↔D3) | Fix in companion prompt |
| ISSUE-6 | `SafeSense_ESP32CAM.ino` | 🔴 Critical | UART on GPIO1/3, ESP32-S3 needs GPIO43/44 | Fix in companion prompt |
| ISSUE-7 | `SafeSense_ESP32CAM.ino` | 🔴 Critical | Camera pins are AI-Thinker, need ESP32-S3 values | Fix in companion prompt |
| ISSUE-8 | `SafeSense_ESP32CAM.ino` | 🟡 Documentation | Header, LED polarity, board target describe wrong board | Fix in companion prompt |
| BUG-A1 through BUG-NEW-1 | All sketches | ✅ Previously fixed | 10 firmware bugs from prior audit sessions | Confirmed fixed in repo |

### Companion prompt file

All code changes for ISSUE-1 through ISSUE-8 are written out as exact find-and-replace blocks in:

**`SafeSense_Complete_Fix_Prompt_Final.md`**

That file is self-contained and can be fed directly to an AI coding tool. Feed this audit report first as context, then the prompt file as the instruction.

---

*SafeSense IoT — Two-Lane Audit Report v2 — May 2026*
*Context document — read alongside SafeSense_Complete_Fix_Prompt_Final.md*
*Repository: https://github.com/kirbygeagonia-create/SafeSense.git*
