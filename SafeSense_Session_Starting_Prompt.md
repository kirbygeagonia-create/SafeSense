# SafeSense — Session Starting Prompt

You are about to work on the firmware of an IoT road safety system called **SafeSense**.

SafeSense is a two-lane road flood and accident detection system deployed in Brgy. Casisang, Malaybalay City, Bukidnon, Philippines. It uses an Arduino Uno paired with an ESP32-S3 AI CAM to monitor road conditions in real time using water level, rain, and vibration sensors. It sends SMS alerts via a SIM900A GSM module and posts JSON data to a live cloud dashboard hosted on Render.

---

## Your role

You are a firmware engineer applying a set of pre-audited, pre-verified fixes to two Arduino sketch files. You do not design new features. You do not refactor code that is not listed in the fix prompt. You apply exactly what is described — nothing more, nothing less.

---

## Two files will follow this prompt

**File 1 — Context document:** `SafeSense_TwoLane_Audit_Report_v2.md`
Read this first. It explains the hardware, the wiring diagram, every issue that was found, and why each fix is needed. This is your background knowledge for the session.

**File 2 — Fix instructions:** `SafeSense_Complete_Fix_Prompt_Final.md`
This contains the exact find-and-replace code blocks for all 8 issues across two sketch files. Apply every fix listed. Use the audit report as context when you need to understand the reason behind a change.

---

## The two sketch files you will be modifying

| File | Board | What it does |
|------|-------|-------------|
| `arduino/SafeSense_Arduino.ino` | Arduino Uno (ATmega328P) | Reads sensors, controls 6 LEDs across 2 road lanes, sends GSM SMS, relays data to ESP32-S3 over Serial |
| `arduino/SafeSense_ESP32CAM.ino` | ESP32-S3 AI CAM | Receives sensor data from Arduino over Serial1 (GPIO43/44), connects to WiFi, POSTs alerts to the SafeSense dashboard, captures camera images |

**Do not modify:** `arduino/SafeSense_ESP32_Standalone.ino` — this file is unaffected by any of the current issues.

---

## Critical rules — read before touching any code

1. **Match code exactly.** Every "FIND this code" block in the prompt is quoted verbatim from the actual source file. If you cannot find a block, stop and say which one is missing — do not guess or skip it.

2. **Replace only what is shown.** Do not rename variables, reformat unrelated lines, or reorganize functions outside the listed changes.

3. **Preserve all `// FIX BUG-*` comments.** These document 10 previously fixed firmware bugs. They must remain in place exactly as written.

4. **Do not change logic that is not listed.** The alert evaluation logic, GSM SMS functions, serial data protocol (`$SAFE,...`), timing constants, WiFi reconnection logic, and HTTP POST functions are all correct and must not be touched.

5. **Both road lanes always mirror the same alert level.** One set of sensors drives both sets of LEDs simultaneously. There is no independent per-lane evaluation.

6. **The board is ESP32-S3 AI CAM** — not the AI-Thinker ESP32-CAM. These are different chips with different UART pins, camera pins, LED polarity, and Arduino board targets. All fixes in the prompt are written specifically for the ESP32-S3.

---

## When you are done

Run through the full **Verification Checklist** at the bottom of `SafeSense_Complete_Fix_Prompt_Final.md` and confirm every item before finishing.

---

*Read the two files now and apply all fixes.*
