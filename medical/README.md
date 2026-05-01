# SafeSense — Hospital Management System

**IoT-Based Rain and Flood Hazard Monitoring with Multi-Channel Smart Emergency Alert System**

> Arduino · IoT · GSM · WiFi Shield · PHP · MySQL

---

## What is SafeSense?

SafeSense is an Arduino-powered IoT system designed to monitor rain and flood conditions in real time and automatically alert emergency stations — including hospitals, police, and fire stations — when hazardous events are detected.

This **Hospital Management System** is the web-based dashboard that runs on a hospital's local server. It receives emergency alerts sent over the internet by the SafeSense Arduino device, displays them to staff in real time, and also manages day-to-day hospital operations such as patients, doctors, and appointments.

---

## How It Relates to the Arduino Project

The SafeSense Arduino device is deployed in the field (e.g., on a road or near a flood-prone area). It is equipped with:

- A **rain sensor** to detect rainfall and its intensity
- An **ultrasonic sensor** to measure water level (distance from sensor to water surface)
- A **vibration sensor** to detect road accidents or impact during rain events
- **Red and yellow LEDs** as adaptive hazard lights (blink rate changes by severity)
- A **GSM module** for SMS alerts (original channel)
- A **WiFi Shield** (ESP8266, ESP32, or Arduino WiFi Shield R2) for internet-based alerts (new IoT channel)

When the Arduino detects a critical condition — rising floodwater, heavy rain, or a road accident — it does two things simultaneously:

1. **Sends an SMS** via the GSM module to predefined contacts (original feature)
2. **POSTs a JSON alert** over WiFi to this Hospital Management System at the `/api/alert` endpoint (new IoT feature)

Within 5 seconds of receiving that POST, the hospital dashboard shows a **live alert popup** to the staff on duty, with full details: location, water level, event type, time, date, and a Google Maps link.

---

## System Features

### Hospital Management (Core)
- **Patient Management** — Add, view, edit, and delete patient records
- **Doctor Management** — Manage doctor profiles and specializations
- **Appointment Scheduling** — Book, confirm, and track appointments between patients and doctors
- **User Authentication** — Secure login with hashed passwords stored in the database

### SafeSense IoT Alert System (New)
- **Arduino API Endpoint** — `POST /api/alert` receives sensor data from the WiFi Shield
- **Real-Time Notification Bell** — Navbar bell badge updates every 5 seconds via JavaScript polling
- **Notification Drawer** — Slide-in panel listing all recent alerts with level, location, and time
- **Alert Modal Popup** — For DANGER and CRITICAL events, a full-screen modal appears automatically with an audio alarm tone, showing all sensor data and a Google Maps link to the exact location
- **Toast Notifications** — Non-blocking pop-up toasts appear in the corner for every new alert
- **Alert Log Page** — Full history of all alerts with filter by level (Critical / Danger / Warning), dismiss, and map view
- **Dashboard Widget** — The main dashboard shows the unread alert count and the 5 most recent alerts
- **Dual-Channel Alerting** — The Arduino uses both SMS (GSM) and HTTP (WiFi) simultaneously; if internet is unavailable, SMS still goes through

### Alert Levels
| Level | Trigger | Dashboard Behavior |
|---|---|---|
| 🟡 Warning | Rain detected, water level rising | Toast + drawer item |
| 🟠 Danger | High water level or accident detected | Toast + drawer + modal popup |
| 🔴 Critical | Flood threshold exceeded | Toast + drawer + modal + audio alarm |

---

## Arduino → System Connection

The Arduino WiFi Shield sends a JSON POST request to this system when a critical event is detected:

```
POST http://YOUR_SERVER_IP/SafeSense/medical/public/api/alert
Content-Type: application/json
```

```json
{
  "api_key":       "SAFESENSE_SECRET_KEY",
  "device_id":     "SAFESENSE-001",
  "station_type":  "hospital",
  "alert_level":   "critical",
  "event_type":    "flood",
  "rain_status":   "heavy",
  "water_level":   45.2,
  "vibration":     0,
  "message":       "CRITICAL: Flood detected. Water level at 45.2 cm.",
  "latitude":      8.1574,
  "longitude":     124.9282,
  "location_name": "Brgy. Casisang, Malaybalay City"
}
```

The API key in the request must match `SAFESENSE_API_KEY` defined in `app/Config/config.php`. This prevents unauthorized devices from sending fake alerts.

---

## Project File Structure

```
SafeSense/
├── arduino/
│   └── SafeSense_IoT.ino              ← Arduino sketch for WiFi Shield alert sending
│
└── medical/                           ← This PHP web application
    ├── README.md                      ← This file
    ├── composer.json
    ├── init.php
    │
    ├── app/
    │   ├── Config/
    │   │   ├── config.php             ← App constants, DB credentials, SAFESENSE_API_KEY
    │   │   └── database.php           ← PDO database connection class
    │   │
    │   ├── Controllers/
    │   │   ├── BaseController.php     ← Shared render(), redirect(), jsonResponse()
    │   │   ├── AuthController.php     ← Login, logout, dashboard with live data
    │   │   ├── AlertController.php    ← IoT API endpoint + alert log + polling
    │   │   ├── PatientController.php  ← Patient CRUD
    │   │   ├── DoctorController.php   ← Doctor CRUD
    │   │   ├── AppointmentController.php ← Appointment CRUD
    │   │   └── ErrorController.php    ← 404 handler
    │   │
    │   ├── Models/
    │   │   ├── Alert.php              ← safesense_alerts table — create, read, poll, dismiss
    │   │   ├── Patient.php            ← patients table CRUD
    │   │   ├── Doctor.php             ← doctors table CRUD
    │   │   └── Appointment.php        ← appointments table CRUD
    │   │
    │   ├── Core/
    │   │   ├── App.php                ← Router initialization and all route definitions
    │   │   └── Router.php             ← URL matching and dispatch
    │   │
    │   └── Views/
    │       ├── layouts/
    │       │   └── main.php           ← Master layout: navbar, bell, drawer, modal, toasts, JS engine
    │       ├── dashboard.php          ← Dashboard: stats cards, recent alerts widget, appointments
    │       ├── alerts/
    │       │   └── index.php          ← Full alert log with filters, map links, Arduino guide
    │       ├── auth/
    │       │   └── login.php          ← Login page
    │       ├── patients/
    │       │   ├── index.php          ← Patient list table
    │       │   ├── create.php         ← Add patient form
    │       │   └── edit.php           ← Edit patient form
    │       ├── doctors/
    │       │   ├── index.php          ← Doctor list table
    │       │   ├── create.php         ← Add doctor form
    │       │   └── edit.php           ← Edit doctor form
    │       ├── appointments/
    │       │   ├── index.php          ← Appointment list table
    │       │   ├── create.php         ← Schedule appointment form
    │       │   └── edit.php           ← Edit appointment form
    │       └── errors/
    │           └── 404.php            ← Not found page
    │
    ├── public/
    │   ├── index.php                  ← Entry point: session, autoload, runs App
    │   └── css/
    │       └── style.css              ← All styles including SafeSense alert components
    │
    └── database/
        ├── migrations/
        │   ├── 000_create_users_table.php           ← Staff login accounts
        │   ├── 001_create_patients_table.php        ← Patient records
        │   ├── 002_create_doctors_table.php         ← Doctor records
        │   ├── 003_create_appointments_table.php    ← Appointment scheduling
        │   └── 004_create_safesense_alerts_table.php ← IoT alert storage + demo seeds
        └── seeds/
            └── seed_patients.php                   ← Sample patient data
```

---

## Installation & Setup

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- A web server (Apache/Nginx) or PHP built-in server
- Arduino IDE (for uploading the sketch to your board)

### Step 1 — Configure the database

Open `app/Config/config.php` and set your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 2 — Run migrations (in this exact order)

```bash
cd database/migrations
php 000_create_users_table.php
php 001_create_patients_table.php
php 002_create_doctors_table.php
php 003_create_appointments_table.php
php 004_create_safesense_alerts_table.php
```

### Step 3 — Start the server

```bash
# Use XAMPP (Apache on port 80) — start Apache and MySQL from the XAMPP Control Panel
# Then open http://localhost/SafeSense/medical/public in your browser
```

Then open `http://localhost/SafeSense/medical/public` in your browser.

### Step 4 — Log in

| Field | Value |
|---|---|
| Email | admin@example.com |
| Password | password |

### Step 5 — Connect the Arduino

1. Find your computer's local IP address (`ipconfig` on Windows, `ifconfig` on Mac/Linux)
2. Open `arduino/SafeSense_IoT.ino` and set:
   ```cpp
   const char* WIFI_SSID     = "YourWiFiName";
   const char* WIFI_PASSWORD = "YourWiFiPassword";
   const char* SERVER_HOST   = "http://192.168.1.100"; // your PC's LAN IP (XAMPP on port 80)
   const char* API_KEY       = "SAFESENSE_SECRET_KEY";
   ```
3. Upload to your Arduino board via Arduino IDE
4. Open Serial Monitor at 115200 baud to see connection status

---

## API Routes Reference

| Method | Route | Description |
|---|---|---|
| GET | `/dashboard` | Main dashboard |
| GET | `/patients` | Patient list |
| GET | `/doctors` | Doctor list |
| GET | `/appointments` | Appointment list |
| GET | `/alerts` | Full SafeSense alert log |
| **POST** | **`/api/alert`** | **Arduino posts sensor data here** |
| GET | `/api/alerts/poll` | JS polls this every 5s for new alerts |
| POST | `/api/alerts/read` | Mark alert(s) as read |
| POST | `/api/alerts/dismiss` | Dismiss an alert |
| GET | `/login` | Login page |
| POST | `/login/authenticate` | Process login |
| POST | `/logout` | Logout |

---

## Security Notes

- Change `SAFESENSE_API_KEY` in `config.php` to a strong random string before deploying
- The same key must be set in the Arduino sketch (`API_KEY` constant)
- Remove the demo credentials fallback in `AuthController.php` before going to production
- All patient/doctor data inputs are sanitized with `htmlspecialchars()` and `strip_tags()`

---

*SafeSense — Protecting communities through intelligent flood and hazard monitoring.*
