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
6. **Log in as admin.** The database file already contains an admin account:
   phone `+254700000000`, password `admin123`. Change that password straight away
   (Profile, then Change password). The old `setup_admin.php` and `create_admin.php`
   pages have been removed for security. If you still have them in your
   `htdocs\microboost` folder from an earlier copy, **delete both files by hand**,
   because copying a new version over the old one does not remove them.
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

---

## 7. Health thresholds and notifications (updated)

`includes/functions.php` now has a configurable threshold table:

- `get_thresholds($crop)`: healthy range, warning range and penalty per measure, with optional per-crop overrides (maize and tea are examples only, not researched values).
- `get_status_cutoffs()`: score cutoffs (75+ Healthy, 45-74 Monitor, below 45 Critical).
- `classify_crop_health($moisture, $temp, $humidity, $crop)` returns status, score, confidence, reasons and a `notify` flag.

`api/sensor_data.php` creates an advisory (with the reasons) and logs an SMS when `notify` is set.
Critical readings always alert; other alerts are limited to one per farmer every 6 hours.
The API reply now also includes `score` and `reasons`.

---

## 8. On-screen guidance for new users (updated)

- **Guide page** (`health_guide.php`, "Guide" in every menu, open to visitors without login): colour-coded healthy / watch / danger ranges for each measurement, how the score becomes a label, per-crop ranges, worked examples, and a live "try it yourself" calculator.
- **Farmer dashboard:** a "Getting started" checklist (season, device, first reading, score, advisory) that highlights the next step, plus the healthy ranges for the active crop and the latest reading's status with the reasons.
- **Sensor Data page:** healthy ranges for the selected crop above the table, the reason under every non-healthy label, and a Log Reading form that shows the range under each box and previews the label as you type.
- **Validation:** impossible values (moisture or humidity outside 0-100 %, temperature outside -20 to 60 C) are rejected by the form and by the API (HTTP 422).
- All ranges come from `get_thresholds()` / `get_crop_overrides()` in `includes/functions.php`. Change them there and every page, the preview and the classifier update together.

## 9. Simulator defaults
`simulator/simulate.py` now points at `http://localhost/microboost` and the device `AA:BB:CC:44:55:66`. Register that MAC under your farmer's Devices page before running it from the `simulator` folder: `python simulate.py --interval 2`.


---

## 10. Latest additions (Batch 1)

No database change was needed, so do **not** re-import the SQL.

| Feature | Where |
|---|---|
| Credit score updates by itself when a reading arrives, is edited or deleted | automatic |
| Device status from real activity (online / offline / maintenance) and "last reading" time | Farmer Devices, Admin Devices, Admin dashboard |
| Auto-refresh switch (bottom right) | Sensor Data, farmer / lender / admin dashboards |
| Demo data loader and reset | Admin menu, **Demo** |
| Print / Save as PDF button inside every report, richer summary | Lender Reports (use the refresh arrow on older reports) |
| CSV export | Sensor Data, Credit Score, Lender dashboard, Admin Reports |
| Alert cards: Critical readings in 24 h, offline devices | Admin and Lender dashboards |
| Phone format check and password confirmation at sign-up | Sign Up |
| Change password (asks for the current password) | Profile pages |

**Device status rule:** a device is *online* if it sent a reading in the last
`DEVICE_OFFLINE_MINUTES` (60, set in `includes/functions.php`); otherwise *offline*.
*Maintenance* is a manual setting and is never overwritten by incoming readings.
For the simulator demo, 60 minutes is fine; for a real deployment sending every
15 minutes you may want a smaller value.

**Demo data:** Admin, **Demo**, **Load demo data** creates `Demo Farmer` (phone `0799000001`)
and `Demo Officer` (phone `0799000002`), password `demo1234`, with 42 readings over 7 days
(healthy, then a dry spell reaching Critical, then recovery), advisories and a score.
**Reset** removes only those two accounts and what belongs to them.
Because the demo farmer already has 42 readings, a short simulator drought barely moves
the *score* (it is an average over the season). The Critical readings, alerts and
advisories show the drought immediately. To watch a score fall, use a new farmer.

**Score note:** the score is recalculated from all readings in the season, so it moves
quickly for a new season and slowly for a long one. That is by design.

---

## 4. Gemini advisories (optional)

Advisories can be written by Google Gemini. The rule engine still decides the crop health
label and the score; Gemini only turns that result into a short, plain message. Only the
crop name, the three readings and the rule reasons are sent. No names or phone numbers are.

1. Create a free key at https://aistudio.google.com/apikey
2. Open `includes/config.php` and paste the key into `GEMINI_API_KEY`.
3. Check the model name in Google AI Studio and update `GEMINI_MODEL` if it has changed.
4. Log in as admin, open **Demo & Gemini** in the sidebar and press **Test Gemini connection**.

If there is no key, no internet, or Gemini is slow or returns an error, MicroBoost
automatically uses its built-in templates instead, so the system never stops producing
advisories. Failures are written to the Apache error log (`C:\xampp\apache\logs\error.log`).
Do not share `includes/config.php` once it holds your key.

## 5. Admin layout

All administrator pages now share one layout (`includes/header.php` and `includes/footer.php`):
a slim top bar, a grouped sidebar, and the styles in `assets/admin.css`. The sidebar becomes
a slide-out menu on narrow screens.
