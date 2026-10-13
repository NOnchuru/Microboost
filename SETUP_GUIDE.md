# MicroBoost — Setup & Hardware Integration Guide

This covers getting the web app running today, plus simulating the
sensors/IoT hardware with free software instead of waiting for physical parts.

---

## 1. Get the web app running (XAMPP, ~10 minutes)

1. **Install XAMPP** (includes PHP + MySQL/MariaDB + phpMyAdmin):
   https://www.apachefriends.org/download.html
2. **Copy the project folder.** Extract this zip, and copy the `microboost`
   folder into:
   - Windows: `C:\xampp\htdocs\microboost`
   - Mac: `/Applications/XAMPP/htdocs/microboost`
   - Linux: `/opt/lampp/htdocs/microboost`
3. **Start XAMPP.** Open the XAMPP Control Panel and click **Start** next to
   Apache and MySQL.
4. **Create the database:**
   - Open http://localhost/phpmyadmin
   - Click **New**, name the database `microboost`, click **Create**
   - Click the `microboost` database, go to the **Import** tab, choose the
     `microboost.sql` file (in this zip), click **Go**
5. **Check `includes/db_connect.php`** matches your MySQL credentials. XAMPP
   defaults are usually fine as-is (user `root`, no password).
6. **Create your admin account:** visit
   http://localhost/microboost/setup_admin.php
   This creates one admin login (check the page for the generated phone
   number/password) and deletes itself from the login flow afterwards —
   it's safe to leave the file, but don't expose it on a public server.
7. **Open the app:** http://localhost/microboost/index.php
   - Register a farmer and a loan officer account from the Sign Up page
   - Log in as admin, go to **Approvals**, and approve the new farmer
     (farmer accounts start `pending` until approved; loan officers are
     active immediately)

You now have a fully working CRUD system for all three roles — no hardware
needed to use any of it manually.

---

## 2. The hardware this project calls for (if you do get parts later)

| Part | Role | Approx. cost (KES) |
|---|---|---|
| ESP32 dev board | WiFi microcontroller, runs the firmware | 800–1,200 |
| Capacitive soil moisture sensor v1.2 | Soil moisture % | 300–500 |
| DHT22 sensor | Air temperature + humidity | 400–700 |
| USB power bank or 5V solar + battery | Power in the field | 500–1,500 |
| Weatherproof enclosure + jumper wires | Protect the electronics | 300–600 |

Available at Nairobi electronics shops (Luthuli Ave / River Road) or
jumia.co.ke / aliexpress.com if you have more lead time.

---

## 3. Simulating the hardware today (no parts required)

You have **two** simulation options in the `simulator/` folder. Use the
Python one for your actual demo today — it's the most reliable.

### Option A — Python simulator (recommended for today)

This sends realistic, scenario-based readings straight to your running app.

**Steps:**

1. Make sure XAMPP is running and you've registered a farmer + started an
   **active growing season** + registered an **IoT device** from the Farmer
   Dashboard (Devices page). Note the exact MAC address you entered, e.g.
   `AA:BB:CC:11:22:33` — it must match exactly.
2. Install Python 3 if you don't have it: https://www.python.org/downloads/
3. Open a terminal in the `simulator` folder and install the one dependency:
   ```
   pip install -r requirements.txt
   ```
4. Open `simulate.py` and edit two things near the top:
   - `BASE_URL = "http://localhost/microboost"` (match your XAMPP URL)
   - The `DEVICES` list — put in the real MAC address(es) you registered,
     and pick a `scenario`: `"normal"`, `"drought"`, `"flood"`, or `"random"`
5. Run it:
   ```
   python simulate.py --interval 10
   ```
   This sends one reading every 10 seconds per device, forever (Ctrl+C to
   stop), or add `--count 20` to send a fixed number and stop.
6. Watch it work live:
   - Terminal prints each reading and the health status the backend assigned
   - Refresh **Sensor Data & Trends** in the farmer dashboard — new rows and
     the trend chart update
   - Use the `"drought"` scenario and watch soil moisture fall — once it
     crosses the Critical threshold, the backend **automatically generates
     and "sends" an SMS advisory** (check the Advisories page)
   - Go to **Credit Score** and click "Calculate" to see the AgroCreditScore
     react to the new data

This alone is enough to demo the full sensor → classification → score →
advisory pipeline without any physical hardware.

### Option B — Wokwi (for showing the actual firmware/circuit)

Wokwi is a free browser-based simulator for real ESP32 code and wiring —
use this if you want to show a grader an actual circuit diagram and the
exact firmware you'd flash onto real hardware.

**Steps:**

1. Go to https://wokwi.com/projects/new/esp32 (no signup needed to try it;
   sign up free to save your project)
2. Delete the default `sketch.ino` content and paste in the contents of
   `simulator/esp32_wokwi.ino` from this zip
3. In the Wokwi project, add these two parts from the parts panel:
   - **DHT22** → wire data pin to ESP32 **GPIO 15**, VCC to 3V3, GND to GND
   - **Potentiometer** (stands in for the soil moisture sensor) → wiper to
     **GPIO 34**, outer pins to 3V3 and GND
4. In the sketch, add a `libraries.txt` file (Wokwi auto-detects this) or
   just let Wokwi prompt you — add:
   ```
   DHT sensor library
   ArduinoJson
   ```
5. **Important limitation:** Wokwi's simulated WiFi (`Wokwi-GUEST`) can only
   reach the real public internet, not `localhost` on your laptop. So:
   - For a live demo reaching your real local app, use **Option A** instead
   - To actually use Wokwi end-to-end, deploy the PHP app to a public host
     (e.g. a free tier on Railway, Render, InfinityFree, or 000webhost) and
     point `SERVER_URL` in the sketch at that public URL
   - Otherwise, just run the Wokwi simulation to **show the serial monitor
     output** (readings + the HTTP response) as evidence of working
     firmware, even without a live round trip to your local server

### Option C — Manual entry / curl (fastest single test)

Your app also has a "Log Reading" form built in (Farmer → Sensor Data →
Log Reading) for one-off manual testing, and you can hit the API directly:

```bash
curl -X POST http://localhost/microboost/api/sensor_data.php \
  -H "Content-Type: application/json" \
  -d '{"mac_address":"AA:BB:CC:11:22:33","soil_moisture":42,"temperature":25,"humidity":55}'
```

---

## 4. What happens automatically when a reading comes in

Whether it comes from the manual form, the Python simulator, or real
hardware later, every reading sent to `api/sensor_data.php` triggers the
same pipeline (matching your sequence diagram):

1. Reading is stored against the farmer's **active season**
2. The device is marked `online`
3. A rule-based classifier (stand-in for the Flask ML service) sets
   **Healthy / Monitor / Critical**
4. If **Critical**, an advisory is generated and an SMS delivery is logged
   (stand-in for Gemini AI + Africa's Talking)
5. Credit scores update whenever you click "Calculate" on the farmer's
   Credit Score page, pulling in the newest readings and classifications

Everything is in `includes/functions.php` if you want to swap the
rule-based logic for a real Flask endpoint or the real Gemini/Africa's
Talking APIs later — each function is isolated so you only need to change
the internals, not any of the pages that call them.

---

## 5. Quick troubleshooting

- **"Unknown device MAC address"** — the MAC in the simulator/firmware must
  exactly match what you typed when registering the device in the app.
- **"Farmer has no active growing season"** — start/activate a season on the
  Seasons page first.
- **Nothing updates in the browser** — hard refresh (Ctrl+Shift+R); the
  dashboard pages aren't auto-polling, they load fresh data on each visit.
- **MySQL connection errors** — check `includes/db_connect.php` matches your
  local MySQL username/password/port.

---

## 6. CRUD coverage (who can do what)

| Data | Admin | Farmer | Loan Officer |
|---|---|---|---|
| Users (all roles) | Create / Read / Update / Delete, suspend, approve (`Users`, `Approvals`) | Update own profile | Update own profile |
| Farmer details (county, crop, acres) | Full CRUD (`Farmers/Officers`) | Read / Update own | Read |
| Loan officer details (SACCO, employee no.) | Full CRUD (`Farmers/Officers`) | - | Read / Update own |
| IoT devices | Full CRUD, assign to any farmer | Full CRUD on own | Read (count) |
| Growing seasons | Full CRUD (`Data > Growing Seasons`) | Full CRUD on own | Read |
| Sensor readings | Full CRUD (edit re-classifies health) | Create / Read / Update / Delete own | Read |
| Crop health classifications | Full CRUD (`Data > Crop Health`) | Read | Read |
| AgroCredit scores | Full CRUD + recalculate | Calculate / Read / Delete own | Read |
| Advisories | Full CRUD, optional SMS | Generate / Read / Delete own | Read |
| SMS delivery logs | Full CRUD (`Data > SMS Logs`) | Read (status on advisory) | - |
| AgroCredit reports | Read / Delete (`Data > Credit Reports`) | - | Generate / Read / Refresh (update) / Delete own |

Deleting a user cascades to everything linked to it (farmer row, devices,
seasons, readings, scores, advisories, SMS logs, reports), as defined by the
foreign keys in your schema.
