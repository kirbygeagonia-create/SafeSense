# SafeSense — Tech Stack

SafeSense is a dual-component system combining an **Arduino-powered IoT hardware device** for field monitoring with a **PHP/MySQL web application** for real-time dashboard alerts and hospital management. 

Below is the comprehensive technology stack used to build both sides of the system.

---

## 1. Hardware & IoT Stack (The Field Device)

The physical device deployed in flood-prone areas or roadsides to monitor conditions.

*   **Microcontroller:** Arduino (Uno/Mega) or ESP8266/ESP32 NodeMCU.
*   **Connectivity:**
    *   **WiFi Shield / ESP Module:** Used to send HTTP POST requests with JSON payloads over the internet to the web dashboard.
    *   **GSM Module:** Used as a fallback and secondary channel to send traditional SMS alerts to emergency contacts.
*   **Sensors:**
    *   **Rain Sensor:** Utilizes both digital (binary rain detection) and analog (rain intensity) outputs.
    *   **Ultrasonic Sensor (HC-SR04):** Measures distance to the water surface to calculate the current water level in centimeters.
    *   **Vibration Sensor:** Detects sudden impacts or road accidents during severe weather events.
*   **Actuators (Local Feedback):**
    *   Red & Yellow LEDs for visual hazard warnings.
    *   Piezo Buzzer for local audio alarms.
*   **Programming Language:** C++ (Arduino Core).
*   **Libraries:** `ArduinoJson` (for building the API payload), `ESP8266WiFi`, `ESP8266HTTPClient`.

---

## 2. Backend Web Application Stack (The Dashboard)

The central server application that receives IoT data and manages hospital operations.

*   **Language:** PHP (7.4+)
*   **Architecture:** Custom lightweight MVC (Model-View-Controller) pattern. No bloated frameworks, ensuring fast execution and easy deployment.
*   **Routing:** Custom URL routing engine to handle both web pages and API endpoints cleanly.
*   **Database:** MySQL / MariaDB (served via XAMPP).
*   **Database Access:** PHP Data Objects (PDO) with prepared statements to prevent SQL injection.
*   **API Integration:** Receives incoming IoT data via a dedicated `POST /api/alert` endpoint. Validates requests using a shared secret API key.

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
