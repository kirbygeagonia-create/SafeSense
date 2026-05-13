# SafeSense Arduino & ESP32 Setup Guide

## Overview
This guide documents the complete setup and configuration for the SafeSense IoT flood/accident detection system, including all hardware connections, software configurations, and recent code changes.

---

## System Architecture

### Hardware Components
1. **Arduino Uno** - Main sensor controller
2. **ESP32-S3 (DFRobot FireBeetle 2)** - WiFi gateway + camera
3. **Sensors:**
   - Rain sensor (digital, D2)
   - Vibration sensor SW-420 (digital, D3)
   - Water level sensor (analog, A0)
4. **LEDs:** 6 LEDs (2 green, 2 yellow, 2 red)
5. **GSM Module:** SIM900A (for SMS alerts)
6. **Camera:** OV3660 module (XDKJ-OV3660)

---

## Arduino Uno Configuration

### Board Selection
- **Board:** Arduino Uno
- **Port:** Select appropriate COM port

### Pin Assignments

#### Sensors
| Sensor | Pin | Mode | Trigger |
|--------|-----|------|---------|
| Rain sensor | D2 | INPUT_PULLUP | LOW = rain detected |
| Vibration sensor | D3 | INPUT_PULLUP | LOW = vibration detected |
| Water level | A0 | INPUT | >80 = water detected |

#### LEDs
| LED | Pin | State |
|-----|-----|-------|
| Green 1 | D8 | Normal/safe |
| Green 2 | D7 | Normal/safe |
| Yellow 1 | D6 | Rain warning |
| Yellow 2 | D5 | Rain warning |
| Red 1 | D9 | Flood/accident |
| Red 2 | D4 | Flood/accident |

#### Communication Modules
| Module | RX Pin | TX Pin | Baud Rate |
|--------|--------|--------|-----------|
| GSM (SIM900A) | D10 | D11 | 9600 |
| ESP32 Serial | D12 | D13 | 9600 |

### Wiring: Arduino → ESP32
- Arduino D13 (TX) → ESP32 GPIO44 (R)
- Arduino D12 (RX) ← ESP32 GPIO43 (T)
- GND ↔ GND (common ground required)

### Libraries Required
- `SoftwareSerial.h` (built-in)
- `avr/wdt.h` (built-in)

### Configuration Settings
```cpp
const String PHONE_NUMBER = "+639709126550";
const int WATER_THRESHOLD = 80;  // Analog reading threshold
const unsigned long BLINK_SPEED = 300;  // ms
const unsigned long ACCIDENT_TIME = 5000;  // ms
```

---

## ESP32-S3 Configuration

### Board Selection in Arduino IDE
- **Board:** DFRobot FireBeetle 2 ESP32-S3
- **USB CDC On Boot:** Enabled
- **PSRAM:** OPI PSRAM
- **Partition Scheme:** Huge APP (3MB No OTA/1MB SPIFFS)
- **Flash Size:** 8MB
- **Port:** Select appropriate COM port

### Libraries Required
Install via Arduino Library Manager:
- **ArduinoJson** by Benoit Blanchon (v6.x)
- **esp_camera** (included with ESP32 board package)

### WiFi Configuration
```cpp
const char* WIFI_SSID = "Fracks";
const char* WIFI_PASSWORD = "686L[w36";
```

### Server Configuration
```cpp
const char* SERVER_URL = "https://safesense-tksy.onrender.com";
const char* API_KEY = "safesense-live-key-928374823901";
const char* DEVICE_ID = "SAFESENSE-001";
const char* STATION_TYPE = "hospital";
const char* LOCATION_NAME = "Brgy. Crossing Palkan, Tupi";
const float LATITUDE = 8.1574;
const float LONGITUDE = 124.9282;
```

### Serial1 Pin Configuration
```cpp
const int SERIAL1_RX = 44;  // GPIO44 (labeled "R" on board)
const int SERIAL1_TX = 43;  // GPIO43 (labeled "T" on board)
```

### Camera Pin Configuration (DFRobot FireBeetle 2 ESP32-S3)
```cpp
#define PWDN_GPIO_NUM   -1
#define RESET_GPIO_NUM  -1
#define XCLK_GPIO_NUM   45
#define SIOD_GPIO_NUM    1
#define SIOC_GPIO_NUM    2
#define Y9_GPIO_NUM     48
#define Y8_GPIO_NUM     46
#define Y7_GPIO_NUM     14
#define Y6_GPIO_NUM     21
#define Y5_GPIO_NUM     47
#define Y4_GPIO_NUM     20
#define Y3_GPIO_NUM     19
#define Y2_GPIO_NUM     34
#define VSYNC_GPIO_NUM  36
#define HREF_GPIO_NUM   35
#define PCLK_GPIO_NUM   0
```

**Camera Settings:**
- XCLK Frequency: 10 MHz (stable for OV3660)
- Frame Size: VGA (640x480) with PSRAM
- JPEG Quality: 10 (lower = better quality)
- Frame Buffer: 1 buffer in PSRAM

---

## System Behavior & LED Logic

### LED States

| Condition | LED Color | Alert Sent | Description |
|-----------|-----------|------------|-------------|
| Normal | GREEN solid | None | All sensors safe |
| Rain detected | YELLOW solid | None | Rain sensor triggered only |
| Water level high | RED solid | FLOOD | Water level > threshold |
| Vibration/crash | RED blinking | ACCIDENT | Vibration sensor triggered |

### Alert Flow

#### Rain Warning (Yellow LED)
1. Rain sensor detects water (LOW signal)
2. Yellow LEDs turn on
3. No alert sent to system
4. Returns to green when rain stops

#### Flood Alert (Red LED)
1. Water level sensor reads > 80
2. Red LEDs turn on solid
3. Arduino sends "FLOOD" to ESP32
4. ESP32 posts critical flood alert to server
5. Returns to green when water level drops

#### Accident Alert (Red Blinking)
1. Vibration sensor detects impact (LOW edge)
2. Red LEDs blink for 5 seconds
3. Arduino sends "ACCIDENT" to ESP32
4. ESP32 captures camera image
5. ESP32 posts critical accident alert with image
6. SMS sent after 1 second: "ALERT: Accident/crash detected at Brgy. Crossing Palkan, Tupi!"
7. After 5 seconds, returns to previous state

### State Priority
- **ACCIDENT** (vibration) has highest priority - overrides all other states
- **FLOOD** (water level) has second priority
- **WARNING** (rain only) has third priority
- **SAFE** (green) is default state

---

## Communication Protocol

### Arduino → ESP32 Commands
The Arduino sends plain-text commands via Serial (9600 baud):

| Command | Trigger | ESP32 Action |
|---------|---------|--------------|
| `ACCIDENT` | Vibration detected | POST accident alert + capture image |
| `FLOOD` | Water level > threshold | POST flood alert |
| `CLEAR` | All sensors safe | No action (safe state) |

### Deduplication
- Commands are only sent when state **changes**
- Prevents spam to ESP32 and server
- Implemented via `lastSentState` tracking

---

## Recent Code Changes

### Session Summary

#### Arduino Changes
1. **LED Logic Fix:**
   - Rain sensor only → YELLOW LED (no alert)
   - Water level triggered → RED LED + FLOOD alert
   - Vibration → RED blinking + ACCIDENT alert

2. **Deduplication:**
   - Added `sendToESP()` function with state tracking
   - Only sends commands when state changes
   - Prevents FLOOD/CLEAR spam every 20ms

3. **Water Threshold:**
   - Changed from 500 to 80 (matches actual sensor readings)
   - Dry floor: ~65-70
   - Wet: 80+

4. **Loop Delay:**
   - Increased from 20ms to 100ms for stable sensor reads

#### ESP32 Changes
1. **Serial1 Pin Fix:**
   - Changed from GPIO12/13 (conflicted with camera)
   - Now uses GPIO43 (TX) / GPIO44 (RX)
   - Matches board labels "43 T" and "44 R"

2. **Camera Configuration:**
   - Using DFRobot FireBeetle 2 ESP32-S3 official pins
   - XCLK frequency: 10 MHz (more stable for OV3660)
   - Added boot capture test for verification

3. **Alert System:**
   - ACCIDENT: captures image + posts alert
   - FLOOD: posts alert only
   - CLEAR: no action

4. **WiFi & Server:**
   - HTTPS connection to Render server
   - Retry logic with exponential backoff
   - Heartbeat every 5 minutes

---

## Upload Instructions

### Arduino Uno
1. **DO NOT** disconnect TX/RX wires when uploading
2. Select: Tools → Board → Arduino Uno
3. Select correct COM port
4. Click Upload
5. Monitor Serial at 9600 baud

### ESP32-S3
1. **IMPORTANT:** Disconnect GPIO43/44 wires before uploading
2. Select: Tools → Board → DFRobot FireBeetle 2 ESP32-S3
3. Configure board settings (see ESP32 Configuration section)
4. Select correct COM port
5. Click Upload
6. Reconnect GPIO43/44 wires after upload
7. Monitor Serial at 115200 baud

**Why disconnect for ESP32?**
- GPIO43/44 are also used by USB-UART bridge during programming
- External signals on these pins can interfere with upload
- Always disconnect, upload, then reconnect

---

## Testing Checklist

### Arduino Tests
- [ ] Green LEDs on at boot
- [ ] Rain sensor → Yellow LEDs
- [ ] Water level → Red LEDs + "FLOOD" sent
- [ ] Vibration → Red blinking + "ACCIDENT" sent
- [ ] Serial monitor shows sensor readings
- [ ] No spam in Serial output

### ESP32 Tests
- [ ] WiFi connects successfully
- [ ] Camera initializes (check Serial for "Camera READY ✓")
- [ ] Receives commands from Arduino
- [ ] Posts alerts to server
- [ ] Captures and uploads images on ACCIDENT
- [ ] Heartbeat every 5 minutes

### Integration Tests
- [ ] Arduino → ESP32 communication working
- [ ] Alerts appear in web system
- [ ] Camera images attached to accident alerts
- [ ] SMS sent on vibration trigger
- [ ] LED states match sensor conditions

---

## Troubleshooting

### Arduino Issues
**LEDs not responding:**
- Check pin connections (G1=8, G2=7, Y1=6, Y2=5, R1=9, R2=4)
- Verify LED polarity (long leg = positive)

**Sensors not triggering:**
- Rain sensor: should read LOW when wet (INPUT_PULLUP)
- Vibration: should read LOW on impact (INPUT_PULLUP)
- Water level: check analog reading in Serial monitor

**ESP32 not receiving commands:**
- Verify wiring: Arduino D13→ESP32 GPIO44, D12←GPIO43
- Check common ground connection
- Monitor both Serial outputs simultaneously

### ESP32 Issues
**WiFi not connecting:**
- Verify SSID and password
- Check WiFi signal strength
- ESP32 will auto-restart after 10 failed attempts

**Camera init failed (0x106):**
- Error means sensor not recognized
- Check FPC cable connection and orientation
- Verify camera module is OV3660
- Try re-seating the camera cable

**Upload fails:**
- Disconnect GPIO43/44 wires before upload
- Press BOOT button if needed
- Select correct board and settings

**Alerts not reaching server:**
- Check WiFi connection
- Verify server URL and API key
- Monitor Serial for HTTP response codes
- Server may be sleeping (Render free tier)

---

## File Locations

```
SafeSense/
├── arduino/
│   ├── SafeSense_Arduino/
│   │   └── SafeSense_Arduino.ino
│   └── SafeSense_ESP32CAM/
│       └── SafeSense_ESP32CAM.ino
└── SafeSense_Arduino_ESP32_Setup_Guide.md (this file)
```

---

## Support & Documentation

- **GitHub Repository:** https://github.com/kirbygeagonia-create/SafeSense
- **Server:** https://safesense-tksy.onrender.com
- **Location:** Brgy. Crossing Palkan, Tupi, South Cotabato

---

**Last Updated:** May 13, 2026  
**Version:** 2.0 (LED logic fix + Serial1 GPIO43/44)
