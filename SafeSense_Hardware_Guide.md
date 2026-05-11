# SafeSense IoT — Complete Hardware & Software Guide

**IoT-Based Rain and Flood Hazard Monitoring with Multi-Channel Smart Emergency Alert System**

> Arduino Uno · ESP32-CAM · SIM900A GSM · WiFi · Camera Capture · PHP Dashboard

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Hardware Components](#hardware-components)
4. [Pin Connection Reference](#pin-connection-reference)
5. [Power Distribution](#power-distribution)
6. [Circuit Wiring Guide](#circuit-wiring-guide)
7. [Software Setup](#software-setup)
8. [Sketch Upload Instructions](#sketch-upload-instructions)
9. [Web Dashboard Connection](#web-dashboard-connection)
10. [Sensor Calibration](#sensor-calibration)
11. [Troubleshooting](#troubleshooting)
12. [API Reference](#api-reference)

---

## System Overview

SafeSense is an IoT-powered field monitoring system designed to detect hazardous weather and road conditions in real time. It combines environmental sensors with dual-channel emergency alerting (WiFi + SMS) to notify hospitals, police stations, and emergency responders when dangerous conditions are detected.

### What It Does

- **Detects rain** using a rain sensor module (digital output)
- **Measures water levels** using a resistive water level sensor (analog output)
- **Detects road accidents** using a vibration/impact sensor (digital output)
- **Activates hazard LEDs** (green/yellow/red) based on threat severity
- **Sounds a buzzer** (optional) during DANGER and CRITICAL events
- **Sends SMS alerts** via SIM900A GSM module to predefined emergency contacts
- **Sends HTTP alerts** via WiFi to a web dashboard for real-time monitoring
- **Captures camera images** (JPEG) during DANGER/CRITICAL events as visual evidence for responders
- **Uploads images** to the web server where hospital staff can view them alongside alert details
- **Reports device health** via periodic heartbeat pings every 5 minutes

### Alert Levels

| Level | Trigger | LEDs | Buzzer | SMS | WiFi | Camera |
|-------|---------|------|--------|-----|------|--------|
| 🟢 **Safe** | No rain, low water, no vibration | Solid green | — | — | — | — |
| 🟡 **Warning** | Rain detected OR water level rising | Solid yellow | — | — | ✅ HTTP POST | — |
| 🟠 **Danger** | High water + rain, or rising flood | Yellow + red slow blink | 🔊 Slow beep | ✅ SMS | ✅ HTTP POST | 📸 Capture + Upload |
| 🔴 **Critical** | Flood threshold exceeded OR confirmed accident | Red + yellow fast alternating blink | 🔊 Rapid beep | ✅ SMS | ✅ HTTP POST | 📸 Capture + Upload |

---

## Architecture

SafeSense uses a **dual-microcontroller architecture** where each board handles what it does best:

```
┌─────────────────────────────────┐     ┌──────────────────────────────┐
│       ARDUINO UNO               │     │       ESP32-CAM              │
│                                 │     │                              │
│  Sensors:                       │     │  WiFi:                       │
│    Water Level (A0) ─────┐      │     │    Connects to local WiFi    │
│    Rain Sensor (D3) ─────┤      │     │    HTTP POST → Web Dashboard │
│    Vibration (D2) ───────┤      │     │    Heartbeat pings (5 min)   │
│                          │      │     │                              │
│  Outputs:                │      │ TX  │  Camera:                     │
│    LED Green (D10)       ├──────┼────►│    OV2640 (built-in)         │
│    LED Yellow (D11)      │      │ RX  │    Captures JPEG on alert    │
│    LED Red (D12)         ├◄─────┼─────│    Uploads to server         │
│    Buzzer (D9, optional) │      │     │                              │
│                          │      │     │  Serial:                     │
│  GSM:                    │      │     │    Receives sensor data      │
│    SIM900A (D7/D8)       │      │     │    from Arduino via UART     │
│    → SMS Alerts          │      │     │                              │
└─────────────────────────────────┘     └──────────────────────────────┘
         │                                        │
         │          COMMON GROUND                  │
         └────────────────────────────────────────-┘
                         │
              ┌──────────┴──────────┐
              │   LM2596 Buck       │
              │   Converter         │
              │                     │
              │   5V → Arduino+ESP  │
              │   4V → SIM900A      │
              └─────────────────────┘
                         │
                   [ BATTERY ]
```

### Why Two Boards?

| Concern | Arduino Uno | ESP32-CAM |
|---------|-------------|-----------|
| **Analog sensors** | ✅ Reliable 10-bit ADC | ⚠️ ADC2 conflicts with WiFi |
| **Real-time I/O** | ✅ Deterministic timing | ⚠️ WiFi stack can cause jitter |
| **WiFi** | ❌ No built-in WiFi | ✅ Built-in WiFi |
| **Camera** | ❌ No camera | ✅ Built-in OV2640 camera |
| **GSM via SoftwareSerial** | ✅ Works well | ⚠️ No SoftwareSerial on ESP32 |
| **Memory** | 2 KB SRAM (tight) | 520 KB SRAM + 4MB PSRAM |
| **Watchdog** | ✅ Hardware WDT | ✅ Hardware WDT |

### Alternative: ESP32 Standalone

For simpler deployments, a **single ESP32 board** can handle everything. See `SafeSense_ESP32_Standalone.ino`. Trade-offs:

- ✅ Simpler wiring (one board)
- ✅ Lower cost
- ⚠️ ADC2 pins (GPIO14 for water level) cannot be used while WiFi is active — **must use ADC1 pins** (GPIO32–39)
- ⚠️ No SoftwareSerial — uses HardwareSerial(2) for GSM

---

## Hardware Components

### Bill of Materials

| # | Component | Quantity | Purpose | Notes |
|---|-----------|----------|---------|-------|
| 1 | Arduino Uno R3 | 1 | Sensor controller + GSM | ATmega328P, 5V logic |
| 2 | ESP32-CAM (AI-Thinker) | 1 | WiFi gateway + camera | Built-in WiFi + OV2640 camera, FTDI needed for upload |
| 3 | SIM900A GSM Module | 1 | SMS alerts | **Requires 4V supply** (not 5V!) |
| 4 | Resistive Water Level Sensor | 1 | Measures water depth | Analog output, 0–1023 on Arduino |
| 5 | Rain Sensor Module (with LM393) | 1 | Detects rain | Digital output (DO), LOW = rain |
| 6 | SW-420 Vibration Sensor | 1 | Detects impact/accident | Digital output, HIGH = vibration |
| 7 | LEDs (5mm) | 3 | Status indicators | Green, Yellow, Red |
| 8 | 220Ω Resistors | 3 | LED current limiting | One per LED |
| 9 | LM2596 Buck Converter | 1–2 | Power regulation | One for 5V, one for 4V (or adjustable) |
| 10 | SIM Card (any carrier) | 1 | For GSM SMS | Must have SMS plan & credit |
| 11 | Jumper Wires | ~30 | Connections | Male-to-Male and Male-to-Female |
| 12 | Breadboard(s) | 1–2 | Prototyping | Full-size recommended |
| 13 | Battery Pack | 1 | Power source | 7–12V recommended (or USB power bank) |
| 14 | FTDI USB-to-Serial Adapter | 1 | ESP32-CAM programming | 3.3V logic, CP2102 or CH340 |

---

## Pin Connection Reference

### Arduino Uno Pin Map

| Arduino Pin | Direction | Connected To | Wire Color (Suggested) |
|-------------|-----------|-------------|----------------------|
| **A0** | INPUT | Water Level Sensor — Signal | Blue |
| **D2** | INPUT | Vibration Sensor — OUT | Orange |
| **D3** | INPUT | Rain Sensor — DO | Green |
| **D7** | INPUT (SoftSerial RX) | SIM900A — TX | Yellow |
| **D8** | OUTPUT (SoftSerial TX) | SIM900A — RX | Yellow |
| **D9** | OUTPUT (optional) | Buzzer — + pin | Purple |
| **D10** | OUTPUT | LED Green — Anode (+) | Green |
| **D11** | OUTPUT | LED Yellow — Anode (+) | Yellow |
| **D12** | OUTPUT | LED Red — Anode (+) | Red |
| **TX (D1)** | OUTPUT | ESP32-CAM — U0R (GPIO3/RX) | White |
| **RX (D0)** | INPUT | ESP32-CAM — U0T (GPIO1/TX) | Gray |
| **5V** | POWER | Water Level VCC, Rain VCC, Vibration VCC | Red |
| **GND** | GROUND | All sensor GNDs, LED cathodes | Black |

### ESP32-CAM Pin Map

| ESP32-CAM Pin | Direction | Connected To | Notes |
|---------------|-----------|-------------|-------|
| **U0R (GPIO3)** | INPUT | Arduino Uno — TX (D1) | Serial data from Arduino |
| **U0T (GPIO1)** | OUTPUT | Arduino Uno — RX (D0) | Serial data to Arduino |
| **5V / VCC** | POWER | 5V from LM2596 | **Not 3.3V!** Board has onboard regulator |
| **GND** | GROUND | Common GND | Must share ground with Arduino |
| **GPIO33** | OUTPUT | Onboard status LED | Active LOW (built into board) |
| **GPIO4** | OUTPUT | Onboard flash LED / camera flash | Active HIGH (used for camera flash during captures) |
| **Camera** | — | OV2640 camera module | Built into board — no wiring needed |

### SIM900A Pin Map

| SIM900A Pin | Connected To | Notes |
|-------------|-------------|-------|
| **VCC** | **4V** from separate LM2596 output | ⚠️ **CRITICAL: Max 4.2V! Never connect to 5V!** |
| **GND** | Common GND | Shared with Arduino + ESP32 |
| **TX** | Arduino D7 (SoftSerial RX) | SIM900A sends data to Arduino |
| **RX** | Arduino D8 (SoftSerial TX) | Arduino sends AT commands to SIM900A |

### Sensor Pin Maps

#### Water Level Sensor (Analog)
| Sensor Pin | Connected To |
|------------|-------------|
| VCC | Arduino 5V |
| GND | Arduino GND |
| Signal (S) | Arduino A0 |

#### Rain Sensor (Digital)
| Sensor Pin | Connected To |
|------------|-------------|
| VCC | Arduino 5V |
| GND | Arduino GND |
| DO (Digital Out) | Arduino D3 |

#### Vibration Sensor (SW-420)
| Sensor Pin | Connected To |
|------------|-------------|
| VCC | Arduino 5V |
| GND | Arduino GND |
| OUT | Arduino D2 |

### LED Wiring (All 3 LEDs)
```
Arduino Pin (D10/D11/D12) ──► LED Anode (+) ──► LED Cathode (-) ──► 220Ω Resistor ──► GND
```

### Buzzer Wiring (Optional)
```
Arduino Pin D9 ──► Buzzer (+) ──► Buzzer (-) ──► GND
```
Use an active buzzer (3.3V–5V type). If using a passive buzzer, add a 100Ω resistor.
Set `BUZZER_ENABLED = true` in `SafeSense_Arduino.ino` after wiring.

---

## Power Distribution

> [!CAUTION]
> **Do NOT skip this section.** Incorrect power distribution will cause random resets, GSM failures, or permanent damage to the SIM900A module.

### Power Architecture

```
[ BATTERY 7-12V ]
       │
       ▼
┌──────────────────┐
│  LM2596 Buck     │
│  Converter #1    │
│  Output: 5V      │──────► Arduino Uno VIN
│                  │──────► ESP32-CAM 5V/VCC
│                  │──────► Sensor VCCs (via Arduino 5V pin)
└──────────────────┘
       │
[ BATTERY 7-12V ]   (or same battery, separate converter)
       │
       ▼
┌──────────────────┐
│  LM2596 Buck     │
│  Converter #2    │
│  Output: ~4V     │──────► SIM900A VCC
│  (adjust to 4.0V)│
└──────────────────┘
```

### Why Separate Power for SIM900A?

The SIM900A module:
- Operates at **3.4V – 4.4V** (nominal 4.0V)
- Draws up to **2A peak** during transmission bursts
- **Will be destroyed** by 5V input
- Causes **voltage drops** that reset the Arduino if sharing the same 5V rail

### Grounding Rules

- **ALL GND pins must be connected together** (common ground)
- Arduino GND + ESP32-CAM GND + SIM900A GND + All sensor GNDs + LM2596 GND = **ONE common ground bus**
- Use thick wires for ground connections

---

## Circuit Wiring Guide

### Step-by-Step Wiring Order

1. **Power first** — Set up the LM2596 buck converters and verify output voltages with a multimeter before connecting anything
2. **Arduino + Sensors** — Connect the 3 sensors to the Arduino
3. **LEDs** — Wire all 3 LEDs with resistors to Arduino pins
4. **SIM900A** — Connect GSM module to Arduino D7/D8 with the 4V power supply
5. **ESP32-CAM** — Connect Serial (TX/RX) between Arduino and ESP32-CAM
6. **Common Ground** — Verify all grounds are connected
7. **Power on** — Connect battery, verify LEDs light up in startup sequence

### Wiring Checklist

- [ ] LM2596 #1 outputs exactly 5.0V (measured with multimeter)
- [ ] LM2596 #2 outputs exactly 4.0V (measured with multimeter)
- [ ] Water level sensor connected: VCC→5V, GND→GND, Signal→A0
- [ ] Rain sensor connected: VCC→5V, GND→GND, DO→D3
- [ ] Vibration sensor connected: VCC→5V, GND→GND, OUT→D2
- [ ] LED Green: D10→Anode, Cathode→220Ω→GND
- [ ] LED Yellow: D11→Anode, Cathode→220Ω→GND
- [ ] LED Red: D12→Anode, Cathode→220Ω→GND
- [ ] SIM900A: VCC→4V, GND→CommonGND, TX→ArduinoD7, RX→ArduinoD8
- [ ] SIM card inserted into SIM900A module
- [ ] ESP32-CAM: VCC→5V, GND→CommonGND, U0R→ArduinoTX, U0T→ArduinoRX
- [ ] All ground rails connected together

---

## Software Setup

### Arduino IDE Configuration

#### Step 1: Install Arduino IDE
Download from [arduino.cc/en/software](https://www.arduino.cc/en/software) (version 2.x recommended).

#### Step 2: Install ESP32 Board Package
1. Open Arduino IDE → File → Preferences
2. In "Additional Board Manager URLs", add:
   ```
   https://dl.espressif.com/dl/package_esp32_index.json
   ```
3. Go to Tools → Board → Board Manager
4. Search "esp32" and install **"esp32 by Espressif Systems"**

#### Step 3: Install Required Libraries
Go to Sketch → Include Library → Manage Libraries and install:
- **ArduinoJson** by Benoit Blanchon (v6.x or v7.x)

The following libraries are built-in and don't need installation:
- `SoftwareSerial` (Arduino Uno)
- `WiFi.h` (ESP32)
- `HTTPClient.h` (ESP32)
- `esp_camera.h` (ESP32, for OV2640 camera)

### Sketch Files

| Sketch File | Upload To | Purpose |
|------------|-----------|---------|
| `SafeSense_Arduino.ino` | Arduino Uno | Sensors, LEDs, buzzer, GSM SMS, Serial bridge |
| `SafeSense_ESP32CAM.ino` | ESP32-CAM | WiFi HTTP POST, camera capture & image upload, heartbeat |
| `SafeSense_ESP32_Standalone.ino` | ESP32 DevKit (alternative) | All-in-one single board |
| `SafeSense_IoT.ino` | ⚠️ DEPRECATED | Original sketch — do not use |

---

## Sketch Upload Instructions

### Uploading to Arduino Uno

1. Connect Arduino Uno to PC via USB cable
2. **Disconnect the ESP32-CAM Serial wires** (TX/RX on D0/D1) — these interfere with USB upload
3. In Arduino IDE:
   - Board: **"Arduino Uno"**
   - Port: Select the COM port (e.g., COM3)
4. Open `SafeSense_Arduino.ino`
5. Edit the configuration section at the top:
   ```cpp
   const char* SMS_NUMBERS[] = { "639XXXXXXXXX", "639XXXXXXXXX" };
   const char* DEVICE_ID     = "SAFESENSE-001";
   const char* LOCATION_NAME = "Your Location Name";
   ```
6. Click **Upload** (→ button)
7. After upload completes, **reconnect the ESP32-CAM Serial wires**

### Uploading to ESP32-CAM

The ESP32-CAM has **no USB port**. You need an FTDI USB-to-Serial adapter.

1. **Wiring for upload mode:**

   | FTDI Pin | ESP32-CAM Pin |
   |----------|---------------|
   | 5V | 5V |
   | GND | GND |
   | TX | U0R (GPIO3) |
   | RX | U0T (GPIO1) |

2. **Enable flash mode:** Connect **GPIO0 to GND** (jumper wire)
3. In Arduino IDE:
   - Board: **"AI Thinker ESP32-CAM"**
   - Partition Scheme: **"Huge APP (3MB No OTA/1MB SPIFFS)"** (needed for camera)
   - Upload Speed: **115200**
   - Port: Select the FTDI COM port
4. Open `SafeSense_ESP32CAM.ino`
5. Edit the configuration:
   ```cpp
   const char* WIFI_SSID     = "YourWiFiName";
   const char* WIFI_PASSWORD = "YourWiFiPassword";
   const char* SERVER_URL    = "http://192.168.1.100/SafeSense/medical/public";
   const char* API_KEY       = "your_api_key_from_env_file";
   ```
6. Click **Upload**
7. After upload completes:
   - **Remove the GPIO0-to-GND jumper**
   - Press the **RST button** on the ESP32-CAM to start normal operation

### Finding Your Server IP

On Windows:
```cmd
ipconfig
```
Look for "IPv4 Address" under your WiFi adapter (e.g., `192.168.1.100`).

---

## Web Dashboard Connection

### How It Works

```
ESP32-CAM ──HTTP POST──► http://YOUR_IP/SafeSense/medical/public/api/alert
                         ┌─────────────────────────────────────────────┐
                         │  AlertController::receive()                 │
                         │  1. Validates API key                       │
                         │  2. Validates required fields               │
                         │  3. Stores alert in safesense_alerts table  │
                         │  4. Returns HTTP 201 Created                │
                         └─────────────────────────────────────────────┘
                                          │
                                          ▼
                         Dashboard polls /api/alerts/poll every 5 seconds
                                          │
                                          ▼
                         Staff sees popup/toast/modal with alert details
```

### API Key Configuration

The API key must match on **both sides**:

**Server side** (`medical/.env`):
```
SAFESENSE_API_KEY=7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8
```

**Arduino side** (`SafeSense_ESP32CAM.ino`):
```cpp
const char* API_KEY = "7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8";
```

To generate a new key:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Testing Without Hardware

The web dashboard has a **Simulate Alert** button (admin only) that injects a test alert without needing the Arduino connected. Use this to verify the dashboard is working before connecting hardware.

---

## Sensor Calibration

### Water Level Sensor

The resistive water level sensor outputs an analog value (0–1023 on Arduino) based on how much of the sensor is submerged.

**Calibration steps:**
1. Upload the sketch and open Serial Monitor at 9600 baud
2. With sensor **completely dry**: note the reading (should be ~0–50)
3. Submerge sensor to **25% depth**: note reading
4. Submerge to **50% depth**: note reading
5. Submerge to **75% depth**: note reading
6. Submerge **fully**: note reading (should be ~900–1023)

Adjust these constants in `SafeSense_Arduino.ino`:
```cpp
const int WATER_LEVEL_SAFE     = 200;   // Below this = dry
const int WATER_LEVEL_WARNING  = 400;   // Yellow zone
const int WATER_LEVEL_DANGER   = 600;   // Red zone
const int WATER_LEVEL_CRITICAL = 800;   // Critical flood
```

### Rain Sensor

The rain sensor with LM393 comparator board has a **potentiometer** for adjusting sensitivity:
- Turn **clockwise** = less sensitive (needs more rain to trigger)
- Turn **counter-clockwise** = more sensitive (triggers with light moisture)

The digital output (DO) goes **LOW when rain is detected**.

### Vibration Sensor (SW-420)

The SW-420 also has a **sensitivity potentiometer**:
- Turn **clockwise** = less sensitive (ignores small vibrations)
- Turn **counter-clockwise** = more sensitive

The sketch requires **3 vibration detections within 10 seconds** to confirm a real event (avoids false positives from wind or passing vehicles).

---

## Troubleshooting

### Common Issues

| Symptom | Possible Cause | Solution |
|---------|---------------|----------|
| LEDs don't light up | No power, wrong pin | Check LM2596 output, verify wiring |
| "GSM not responding" | Wrong baud rate, no power | Verify 4V supply, check D7/D8 wires |
| SIM900A keeps resetting | Insufficient power | Use separate 4V regulator, thick wires |
| ESP32-CAM won't connect to WiFi | Wrong SSID/password, out of range | Verify credentials, check signal |
| HTTP POST returns 401 | API key mismatch | Match key in sketch with `.env` file |
| HTTP POST returns 404 | Wrong URL path | Verify `/public` is in the URL |
| No alerts on dashboard | Alert cooldown active | Wait 60 seconds, or adjust `ALERT_COOLDOWN_MS` |
| Water level reads 0 | Wrong pin, sensor disconnected | Check A0 connection |
| Arduino resets randomly | Power instability | Add capacitors, use separate power rails |
| Upload fails on ESP32-CAM | GPIO0 not grounded, wrong board | Connect GPIO0→GND, select "AI Thinker ESP32-CAM" |

### Serial Monitor Debug

For Arduino Uno, sensor data is sent via Hardware Serial to ESP32-CAM. To debug the Arduino, you need to temporarily disconnect the ESP32-CAM and monitor via USB:

1. Disconnect ESP32-CAM TX/RX wires from Arduino D0/D1
2. Upload sketch via USB
3. Open Serial Monitor at **9600 baud**
4. You'll see `$SAFE,DATA,...` packets every 3 seconds

For ESP32-CAM, connect an FTDI adapter and use Serial Monitor at **9600 baud**.

### LED Status Indicators

| LED Pattern | Meaning |
|-------------|---------|
| 🟢 Solid green | System safe, all sensors normal |
| 🟡 Solid yellow | Warning — rain or rising water |
| 🟡🔴 Yellow + red slow blink | Danger — high water level |
| 🟡🔴 Fast alternating blink | Critical — flood or accident |
| All 3 LEDs on briefly at boot | Startup self-test |

---

## API Reference

### POST `/api/alert` — Send Sensor Alert

Sent by ESP32-CAM when the Arduino detects a hazardous condition.

**Request:**
```json
{
  "api_key":       "your_api_key",
  "device_id":     "SAFESENSE-001",
  "station_type":  "hospital",
  "alert_level":   "critical",
  "event_type":    "flood",
  "rain_status":   "detected",
  "water_level":   75.5,
  "vibration":     0,
  "message":       "CRITICAL: Flood detected. Water level at 75.5%...",
  "latitude":      8.1574,
  "longitude":     124.9282,
  "location_name": "Brgy. Crossing Rubber, Tupi"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Alert received and stored."
}
```

### POST `/api/heartbeat` — Device Keep-Alive

Sent by ESP32-CAM every 5 minutes.

**Request:**
```json
{
  "api_key":      "your_api_key",
  "device_id":    "SAFESENSE-001",
  "station_type": "hospital",
  "status":       "online",
  "wifi_rssi":    -45,
  "uptime_ms":    300000,
  "free_heap":    120000,
  "camera_ready": true,
  "psram":        true
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Heartbeat received.",
  "server_time": "2026-05-07 12:00:00"
}
```

### POST `/api/alert/image` — Upload Camera Image

Sent by ESP32-CAM immediately after a DANGER or CRITICAL JSON alert.
The image is a JPEG captured by the onboard OV2640 camera (800×600, ~30–80 KB).

**Request:** `multipart/form-data`

| Field | Type | Description |
|-------|------|-------------|
| `api_key` | text | Shared API secret |
| `device_id` | text | e.g., "SAFESENSE-001" |
| `alert_level` | text | danger \| critical |
| `event_type` | text | flood \| accident \| rain |
| `latitude` | text | GPS latitude |
| `longitude` | text | GPS longitude |
| `image` | file | JPEG image (max 500 KB) |

**Response (201):**
```json
{
  "success": true,
  "message": "Image received and stored.",
  "filename": "safesense_SAFESENSE-001_2026-05-07_12-00-00.jpg"
}
```

### Valid Enum Values

| Field | Valid Values |
|-------|-------------|
| `alert_level` | `warning`, `danger`, `critical` |
| `event_type` | `rain`, `flood`, `accident`, `vibration`, `test` |
| `station_type` | `hospital`, `police`, `fire` |

---

## Serial Communication Protocol

### Arduino → ESP32-CAM Data Format

The Arduino sends structured text packets over Hardware Serial (9600 baud):

| Packet Type | Format | Example |
|------------|--------|---------|
| Sensor Data | `$SAFE,DATA,<rain>,<waterRaw>,<vibCount>,<alertLevel>` | `$SAFE,DATA,1,650,0,2` |
| Alert | `$SAFE,ALERT,<level>,<event>,<waterPct>,<vib>\|<message>` | `$SAFE,ALERT,critical,flood,63.5,0\|CRITICAL: Flood detected...` |
| Heartbeat | `$SAFE,HEARTBEAT,0,0,0,0` | `$SAFE,HEARTBEAT,0,0,0,0` |
| Boot | `$SAFE,BOOT,0,0,0,0` | `$SAFE,BOOT,0,0,0,0` |

All packets are newline-terminated (`\n`).

---

*SafeSense Hardware Guide — May 2026*
*For the latest version, see the [GitHub repository](https://github.com/kirbygeagonia-create/SafeSense)*
