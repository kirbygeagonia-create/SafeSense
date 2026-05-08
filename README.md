# SafeSense — Tech Stack

SafeSense is a dual-component system combining an **Arduino-powered IoT hardware device** for field monitoring with a **PHP/MySQL web application** for real-time dashboard alerts and hospital management. 

Below is the comprehensive technology stack used to build both sides of the system.

---

## 1. Hardware & IoT Stack (The Field Device)

The physical device deployed in flood-prone areas or roadsides to monitor conditions.

### Dual-MCU Architecture

SafeSense uses two microcontrollers working together:

| Board | Role | Key Responsibilities |
|-------|------|---------------------|
| **Arduino Uno** | Sensor Controller | Reads all sensors, controls LEDs, sends SMS via SIM900A GSM, forwards data to ESP32-CAM via Serial |
| **ESP32-CAM** | WiFi Gateway | Receives sensor data from Arduino, connects to WiFi, POSTs JSON alerts to web dashboard, sends heartbeat pings |

*Alternative: A single ESP32 DevKit can handle all functions — see `SafeSense_ESP32_Standalone.ino`.*

### Components

*   **Microcontrollers:** Arduino Uno (ATmega328P) + AI-Thinker ESP32-CAM
*   **Connectivity:**
    *   **ESP32-CAM Built-in WiFi:** Sends HTTP POST requests with JSON payloads over the internet to the web dashboard. No separate WiFi shield needed.
    *   **SIM900A GSM Module:** Sends SMS alerts to emergency contacts via AT commands. Powered by a separate 4V regulator (critical — max 4.2V input).
*   **Sensors:**
    *   **Resistive Water Level Sensor (Analog, A0):** Outputs analog value (0–1023) proportional to water depth.
    *   **Rain Sensor with LM393 (Digital, D3):** Binary rain detection (LOW = rain detected).
    *   **SW-420 Vibration Sensor (Digital, D2):** Detects impact/vibration events. Requires 3 confirmations within 10 seconds to trigger.
*   **Actuators (Local Feedback):**
    *   Green, Yellow, and Red LEDs (D10, D11, D12) for visual hazard warnings with non-blocking blink patterns.
*   **Power:** LM2596 buck converter(s) — 5V output for Arduino + ESP32, separate ~4V output for SIM900A. All grounds connected.
*   **Programming Language:** C++ (Arduino Core).
*   **Libraries:** `ArduinoJson` (JSON payloads), `SoftwareSerial` (GSM), `WiFi.h` + `HTTPClient.h` (ESP32 WiFi).

### Sketch Files

| File | Board | Purpose |
|------|-------|---------|
| `arduino/SafeSense_Arduino.ino` | Arduino Uno | Sensors, LEDs, GSM SMS, Serial bridge to ESP32 |
| `arduino/SafeSense_ESP32CAM.ino` | ESP32-CAM | WiFi HTTP POST, heartbeat, alert forwarding |
| `arduino/SafeSense_ESP32_Standalone.ino` | ESP32 DevKit | All-in-one alternative (single board) |
| `arduino/SafeSense_IoT.ino` | ⚠️ Deprecated | Original single-board sketch (reference only) |

> 📖 **Full hardware setup guide:** See [SafeSense_Hardware_Guide.md](SafeSense_Hardware_Guide.md) for complete wiring diagrams, pin connections, power distribution, upload instructions, calibration, and troubleshooting.

---

## 2. Backend Web Application Stack (The Dashboard)

The central server application that receives IoT data and manages hospital operations.

*   **Language:** PHP (7.4+)
*   **Architecture:** Custom lightweight MVC (Model-View-Controller) pattern. No bloated frameworks, ensuring fast execution and easy deployment.
*   **Routing:** Custom URL routing engine to handle both web pages and API endpoints cleanly.
*   **Database:** MySQL / MariaDB (served via XAMPP).
*   **Database Access:** PHP Data Objects (PDO) with prepared statements to prevent SQL injection.
*   **API Integration:** 
    *   `POST /api/alert` — Receives incoming IoT sensor data and stores alerts.
    *   `POST /api/heartbeat` — Receives periodic device health pings (WiFi RSSI, uptime, free memory).
    *   Validates requests using a shared secret API key defined in `.env`.

---

## 3. Frontend UI/UX Stack

The user interface for the hospital staff to view alerts and manage data.

*   **Markup & Styling:** HTML5 and CSS3 (Vanilla).
*   **CSS Framework:** Bootstrap 5 (via CDN) for rapid, responsive layout structuring and grid management.
*   **Icons:** FontAwesome 6 for scalable vector icons.
*   **Typography:** Google Fonts (IBM Plex Sans & IBM Plex Mono) for a modern, clean, and legible dashboard look.
*   **JavaScript:** Vanilla JS (`ES6+`) used for:
    *   **Long-polling:** Fetching new alerts from the server every 5 seconds (`fetch` API) without refreshing the page.
    *   **DOM Manipulation:** Dynamically rendering notification toasts, alert drawers, and full-screen critical modals.
    *   **Audio Alerts:** Utilizing the browser's native **Web Audio API** (`AudioContext`, `OscillatorNode`) to generate a loud, synthetic alarm tone for CRITICAL alerts directly in the browser, without needing MP3 files.

---

## 4. Development & Deployment Tools

*   **Local Server Environment:** XAMPP (Apache web server & MySQL database).
*   **Package Management:** Composer (for PHP dependencies, though the core is mostly dependency-free).
*   **Version Control:** Git.
*   **Arduino IDE:** Version 2.x with ESP32 board package for uploading sketches.
