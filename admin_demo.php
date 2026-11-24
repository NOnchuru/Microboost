<?php
include 'includes/header.php';
require_role('admin');

const DEMO_FARMER_PHONE  = '0799000001';
const DEMO_OFFICER_PHONE = '0799000002';
const DEMO_PASSWORD      = 'demo1234';
const DEMO_MAC_PRIMARY   = 'AA:BB:CC:44:55:66';   // same MAC the simulator uses by default
const DEMO_MAC_FALLBACK  = 'DE:AD:BE:EF:00:01';   // used when the primary MAC is already registered

function demo_exists($conn, $phone) {
    $st = $conn->prepare("SELECT id FROM users WHERE phone_number=?");
    $st->bind_param("s", $phone);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

// ---------------- CLEAR READINGS ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_readings') {
    if (trim($_POST['confirm_text'] ?? '') !== 'CLEAR') {
        flash_set('error', 'Nothing was deleted. Type CLEAR in the box to confirm.');
    } else {
        try {
            foreach (['sms_delivery_logs', 'advisories', 'crop_health_classifications', 'sensor_readings', 'agri_credit_scores'] as $t) {
                $conn->query("DELETE FROM `$t`");
            }
            flash_set('success', 'All sensor readings, classifications, advisories, SMS logs and scores were cleared. Users, devices and seasons were kept.');
        } catch (Throwable $e) {
            flash_set('error', 'Could not clear the data: ' . $e->getMessage());
        }
    }
    header("Location: admin_demo.php"); exit();
}

// ---------------- SMS CONNECTION TEST ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_sms') {
    $r = africastalking_send($_POST['phone'] ?? '', 'MicroBoost test message: your SMS alerts are working.');
    if ($r['ok']) flash_set('success', 'Africa\'s Talking accepted the message: ' . $r['detail']);
    else flash_set('error', 'SMS was not sent. Reason: ' . $r['detail']);
    header("Location: admin_demo.php"); exit();
}

// ---------------- GEMINI CONNECTION TEST ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_ai') {
    $sample = gemini_advisory('Monitor', 'Maize', 20, 35, 60, ['Soil moisture 20% is too low', 'Temperature 35C is above the ideal range']);
    if ($sample !== null) {
        flash_set('success', 'Gemini is working. Sample advisory: ' . $sample);
    } else {
        flash_set('error', 'Gemini is not available, so templates will be used. Reason: ' . gemini_last_error());
    }
    header("Location: admin_demo.php"); exit();
}

// ---------------- LOAD ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'load') {
    if (demo_exists($conn, DEMO_FARMER_PHONE) || demo_exists($conn, DEMO_OFFICER_PHONE)) {
        flash_set('error', 'Demo data is already loaded. Reset it first if you want a fresh copy.');
        header("Location: admin_demo.php"); exit();
    }

    $conn->begin_transaction();
    try {
        $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

        // demo farmer
        $name = 'Demo Farmer'; $role = 'farmer'; $status = 'active';
        $st = $conn->prepare("INSERT INTO users (full_name, phone_number, password, role, account_status) VALUES (?,?,?,?,?)");
        $phone = DEMO_FARMER_PHONE;
        $st->bind_param("sssss", $name, $phone, $hash, $role, $status);
        if (!$st->execute()) throw new Exception('farmer user');
        $farmer_user = $conn->insert_id;

        $county = 'Kiambu'; $crop = 'Maize'; $acres = 2.5;
        $st = $conn->prepare("INSERT INTO farmers (user_id, county, primary_crop, farm_size_acres) VALUES (?,?,?,?)");
        $st->bind_param("issd", $farmer_user, $county, $crop, $acres);
        if (!$st->execute()) throw new Exception('farmer row');
        $farmer_id = $conn->insert_id;

        // demo loan officer
        $name2 = 'Demo Officer'; $role2 = 'loan_officer';
        $st = $conn->prepare("INSERT INTO users (full_name, phone_number, password, role, account_status) VALUES (?,?,?,?,?)");
        $phone2 = DEMO_OFFICER_PHONE;
        $st->bind_param("sssss", $name2, $phone2, $hash, $role2, $status);
        if (!$st->execute()) throw new Exception('officer user');
        $officer_user = $conn->insert_id;
        $sacco = 'Demo SACCO'; $emp = 'DEMO001';
        $st = $conn->prepare("INSERT INTO loan_officers (user_id, sacco_name, employee_no) VALUES (?,?,?)");
        $st->bind_param("iss", $officer_user, $sacco, $emp);
        if (!$st->execute()) throw new Exception('officer row');

        // season, started 8 days ago
        $st = $conn->prepare("INSERT INTO growing_seasons (farmer_id, crop_type, start_date, status) VALUES (?, 'Maize', DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'active')");
        $st->bind_param("i", $farmer_id);
        if (!$st->execute()) throw new Exception('season');
        $season_id = $conn->insert_id;

        // device: prefer the MAC the simulator already uses
        $mac = DEMO_MAC_PRIMARY;
        $taken = $conn->prepare("SELECT id FROM iot_devices WHERE mac_address=?");
        $taken->bind_param("s", $mac);
        $taken->execute();
        if ($taken->get_result()->fetch_assoc()) $mac = DEMO_MAC_FALLBACK;
        $st = $conn->prepare("INSERT INTO iot_devices (farmer_id, mac_address, installation_date, status) VALUES (?,?,DATE_SUB(CURDATE(), INTERVAL 8 DAY),'online')");
        $st->bind_param("is", $farmer_id, $mac);
        if (!$st->execute()) throw new Exception('device');
        $device_id = $conn->insert_id;

        // 7 days of readings, every 4 hours (42 readings); newest is 5 minutes old.
        // Story: healthy first, then a dry spell with a heat spike, then recovery.
        mt_srand(7);
        $counts = ['Healthy' => 0, 'Monitor' => 0, 'Critical' => 0];
        $firstNotify = null; $firstCritical = null;
        $n = 42;
        for ($i = 0; $i < $n; $i++) {
            $minutesAgo = ($n - 1 - $i) * 240 + 5;
            if ($i < 24)       { $m = 58 + 8 * sin($i / 4); $dry = 0; }
            elseif ($i < 34)   { $m = 40 - ($i - 24) * 5; $dry = 1; }
            else               { $m = 18 + ($i - 33) * 4.2; $dry = 0; }
            $t = 22 + 7 * sin(2 * M_PI * ($i % 6) / 6 - M_PI / 2) + ($dry ? 10 : 0);
            $h = 60 + 10 * sin($i / 3) - ($dry ? 25 : 0);
            $m = max(3, min(97, $m + mt_rand(-15, 15) / 10));
            $t = max(5, min(45, $t + mt_rand(-10, 10) / 10));
            $h = max(8, min(97, $h + mt_rand(-15, 15) / 10));
            $m = round($m, 2); $t = round($t, 2); $h = round($h, 2);

            $st = $conn->prepare("INSERT INTO sensor_readings (device_id, season_id, soil_moisture, temperature, humidity, recorded_at) VALUES (?,?,?,?,?, DATE_SUB(NOW(), INTERVAL ? MINUTE))");
            $st->bind_param("iidddi", $device_id, $season_id, $m, $t, $h, $minutesAgo);
            if (!$st->execute()) throw new Exception('reading');
            $rid = $conn->insert_id;

            $c = classify_crop_health($m, $t, $h, 'Maize');
            $counts[$c['status']]++;
            $st = $conn->prepare("INSERT INTO crop_health_classifications (reading_id, health_status, analyzed_at) VALUES (?,?, DATE_SUB(NOW(), INTERVAL ? MINUTE))");
            $st->bind_param("isi", $rid, $c['status'], $minutesAgo);
            if (!$st->execute()) throw new Exception('classification');

            if ($c['notify'] && $firstNotify === null)               $firstNotify = [$minutesAgo, $c, $m, $t];
            if ($c['status'] === 'Critical' && $firstCritical === null) $firstCritical = [$minutesAgo, $c, $m, $t];
        }

        // advisories (with SMS log) at the moments the system would have raised them
        $adv = [];
        if ($firstNotify) $adv[] = $firstNotify;
        if ($firstCritical && (!$firstNotify || $firstCritical[0] !== $firstNotify[0])) $adv[] = $firstCritical;
        foreach ($adv as [$minAgo, $c, $m, $t]) {
            $text = generate_advisory($c['status'], 'Maize', $m, $t, null, [], false)['text'];
            if ($c['reasons']) $text .= ' Details: ' . implode('; ', $c['reasons']) . '.';
            $st = $conn->prepare("INSERT INTO advisories (farmer_id, advisory_text, generated_at) VALUES (?,?, DATE_SUB(NOW(), INTERVAL ? MINUTE))");
            $st->bind_param("isi", $farmer_id, $text, $minAgo);
            if (!$st->execute()) throw new Exception('advisory');
            $aid = $conn->insert_id;
            $st = $conn->prepare("INSERT INTO sms_delivery_logs (advisory_id, phone_number, delivery_status, sent_at) VALUES (?,?, 'Delivered', DATE_SUB(NOW(), INTERVAL ? MINUTE))");
            $st->bind_param("isi", $aid, $phone, $minAgo);
            if (!$st->execute()) throw new Exception('sms log');
        }

        $score = update_credit_score($conn, $farmer_id, $season_id);
        $conn->commit();

        flash_set('success', "Demo data loaded: 42 readings (Healthy {$counts['Healthy']}, Monitor {$counts['Monitor']}, Critical {$counts['Critical']}), "
            . count($adv) . " advisories, score " . ($score ? $score['score'] . ' (' . $score['tier'] . ')' : 'n/a')
            . ". Device MAC: $mac");
    } catch (Exception $e) {
        $conn->rollback();
        flash_set('error', 'Could not load demo data (' . $e->getMessage() . ').');
    }
    header("Location: admin_demo.php"); exit();
}

// ---------------- RESET ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    $removed = 0;
    foreach ([DEMO_FARMER_PHONE, DEMO_OFFICER_PHONE] as $ph) {
        $u = demo_exists($conn, $ph);
        if (!$u) continue;
        $uid = (int)$u['id'];
        // delete report files of the demo farmer before the rows disappear
        $files = $conn->query("SELECT r.report_path FROM agri_credit_reports r JOIN farmers f ON f.id=r.farmer_id WHERE f.user_id=$uid");
        while ($f = $files->fetch_assoc()) {
            $path = __DIR__ . '/' . $f['report_path'];
            if (is_file($path)) @unlink($path);
        }
        $conn->query("DELETE FROM users WHERE id=$uid");   // cascades to every related table
        $removed++;
    }
    flash_set($removed ? 'success' : 'info', $removed ? 'Demo data removed.' : 'There was no demo data to remove.');
    header("Location: admin_demo.php"); exit();
}

$loaded = demo_exists($conn, DEMO_FARMER_PHONE) ? true : false;
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="fw-bold mb-3"><i class="fa-solid fa-flask me-2"></i>Demo Data</h2>
            <?php render_flashes(); ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <p>Loads a ready-made example so a presentation starts with a populated system, and removes it afterwards. It creates:</p>
                    <ul>
                        <li>A <strong>Demo Farmer</strong> (Maize, Kiambu) with one active season and one device.</li>
                        <li><strong>42 sensor readings</strong> over the last 7 days: healthy at first, then a dry spell with a heat spike that reaches Critical, then recovery. The newest reading is minutes old, so the device shows as online.</li>
                        <li>Crop health labels, two advisories with SMS records, and an AgroCredit score.</li>
                        <li>A <strong>Demo Officer</strong> so you can show the lender side.</li>
                    </ul>
                    <div class="alert alert-light border small mb-3">
                        <strong>Logins</strong> (password for both: <code><?php echo DEMO_PASSWORD; ?></code>)<br>
                        Farmer: <code><?php echo DEMO_FARMER_PHONE; ?></code> &nbsp; Loan officer: <code><?php echo DEMO_OFFICER_PHONE; ?></code>
                    </div>
                    <p class="small text-muted">The demo device uses the MAC <code><?php echo DEMO_MAC_PRIMARY; ?></code>, the same one the Python simulator sends by default, so running the simulator adds live readings to the demo farmer. If that MAC is already registered to someone else, a different one is used and shown after loading. This data is clearly separate from your real records, and Reset deletes only these two accounts and what belongs to them.</p>

                    <div class="d-flex gap-2">
                        <form method="POST"><input type="hidden" name="action" value="load">
                            <button class="btn btn-agri" <?php echo $loaded ? 'disabled' : ''; ?>><i class="fa-solid fa-download me-1"></i>Load demo data</button></form>
                        <form method="POST" onsubmit="return confirm('Remove the demo farmer, demo officer and everything that belongs to them?');">
                            <input type="hidden" name="action" value="reset">
                            <button class="btn btn-outline-danger" <?php echo $loaded ? '' : 'disabled'; ?>><i class="fa-solid fa-rotate-left me-1"></i>Reset (remove demo data)</button></form>
                    </div>
                    <p class="small mt-3 mb-0">Status: <?php echo $loaded ? '<span class="badge bg-success">Demo data is loaded</span>' : '<span class="badge bg-secondary">Not loaded</span>'; ?></p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Gemini advisories</h6>
                    <p class="small text-muted mb-3">Advisories are written by Gemini from the rule engine's result. If Gemini is not set up or cannot be reached, MicroBoost uses its built-in templates instead, so farmers always get a message. Set the key in <code>includes/config.php</code>.</p>
                    <p class="small mb-3">Status: <?php echo GEMINI_API_KEY !== '' ? '<span class="badge bg-success">Key set (' . h(GEMINI_MODEL) . ')</span>' : '<span class="badge bg-secondary">No key set: using templates</span>'; ?></p>
                    <form method="POST"><input type="hidden" name="action" value="test_ai">
                        <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-plug me-1"></i>Test Gemini connection</button></form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-comment-sms me-2"></i>SMS delivery (Africa's Talking)</h6>
                    <p class="small text-muted mb-3">Advisories are sent to the farmer's phone by SMS through Africa's Talking. Without credentials, delivery is simulated and only logged. Set them in <code>includes/config.php</code>.</p>
                    <p class="small mb-3">Status: <?php
                        if (!africastalking_configured()) echo '<span class="badge bg-secondary">Not set up: simulated</span>';
                        elseif (defined('AT_SANDBOX') && AT_SANDBOX) echo '<span class="badge bg-warning text-dark">Sandbox: goes to the simulator, not a phone</span>';
                        else echo '<span class="badge bg-success">Live: real phones</span>'; ?></p>
                    <form method="POST" class="d-flex gap-2 flex-wrap"><input type="hidden" name="action" value="test_sms">
                        <input type="text" name="phone" class="form-control form-control-sm" style="max-width:220px" placeholder="0712 345 678" required>
                        <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-paper-plane me-1"></i>Send test SMS</button></form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4 border-start border-danger border-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-broom me-2"></i>Clear readings before a presentation</h6>
                    <p class="small text-muted mb-3">Deletes all sensor readings, health classifications, advisories, SMS logs and credit scores for every farmer. Users, devices and growing seasons stay. This cannot be undone.</p>
                    <form method="POST" class="d-flex gap-2 flex-wrap" onsubmit="return confirm('Delete all readings and scores?');"><input type="hidden" name="action" value="clear_readings">
                        <input type="text" name="confirm_text" class="form-control form-control-sm" style="max-width:220px" placeholder="Type CLEAR" required>
                        <button class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash me-1"></i>Clear readings</button></form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
