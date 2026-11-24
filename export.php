<?php
// export.php?type=...  Downloads a CSV file that opens in Excel.
// Farmers can only export their own data. Lenders and admins can export the farmer summary.
// Only admins can export all readings and the user list (no passwords are ever exported).
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$role = $_SESSION['role'];
$type = $_GET['type'] ?? '';

$rows = null; $header = []; $filename = 'export';

if ($type === 'my_readings' && $role === 'farmer') {
    $fid = get_farmer_id_by_user($conn, $_SESSION['user_id']);
    $header = ['Recorded at', 'Crop', 'Device MAC', 'Soil moisture %', 'Temperature C', 'Humidity %', 'Health'];
    $rows = $conn->query(
        "SELECT sr.recorded_at, gs.crop_type, d.mac_address, sr.soil_moisture, sr.temperature, sr.humidity, chc.health_status
         FROM sensor_readings sr
         JOIN growing_seasons gs ON gs.id = sr.season_id
         JOIN iot_devices d ON d.id = sr.device_id
         LEFT JOIN crop_health_classifications chc ON chc.reading_id = sr.id
         WHERE gs.farmer_id = " . (int)$fid . " ORDER BY sr.recorded_at DESC"
    );
    $filename = 'my_sensor_readings';

} elseif ($type === 'my_scores' && $role === 'farmer') {
    $fid = get_farmer_id_by_user($conn, $_SESSION['user_id']);
    $header = ['Crop', 'Season start', 'Score', 'Tier', 'Last updated'];
    $rows = $conn->query(
        "SELECT gs.crop_type, gs.start_date, acs.score_value, acs.tier, acs.last_updated
         FROM agri_credit_scores acs JOIN growing_seasons gs ON gs.id = acs.season_id
         WHERE acs.farmer_id = " . (int)$fid . " ORDER BY acs.last_updated DESC"
    );
    $filename = 'my_credit_scores';

} elseif ($type === 'farmers' && in_array($role, ['loan_officer', 'admin'], true)) {
    $header = ['Farmer ID', 'Name', 'Phone', 'County', 'Primary crop', 'Farm size (acres)', 'Latest score', 'Tier', 'Devices'];
    $rows = $conn->query(
        "SELECT f.id, u.full_name, u.phone_number, f.county, f.primary_crop, f.farm_size_acres,
            (SELECT score_value FROM agri_credit_scores WHERE farmer_id=f.id ORDER BY last_updated DESC LIMIT 1),
            (SELECT tier FROM agri_credit_scores WHERE farmer_id=f.id ORDER BY last_updated DESC LIMIT 1),
            (SELECT COUNT(*) FROM iot_devices WHERE farmer_id=f.id)
         FROM farmers f JOIN users u ON u.id = f.user_id
         WHERE u.account_status = 'active' ORDER BY u.full_name"
    );
    $filename = 'farmers_summary';

} elseif ($type === 'all_readings' && $role === 'admin') {
    $header = ['Reading ID', 'Recorded at', 'Farmer', 'Crop', 'Device MAC', 'Soil moisture %', 'Temperature C', 'Humidity %', 'Health'];
    $rows = $conn->query(
        "SELECT sr.id, sr.recorded_at, u.full_name, gs.crop_type, d.mac_address, sr.soil_moisture, sr.temperature, sr.humidity, chc.health_status
         FROM sensor_readings sr
         JOIN growing_seasons gs ON gs.id = sr.season_id
         JOIN farmers f ON f.id = gs.farmer_id JOIN users u ON u.id = f.user_id
         JOIN iot_devices d ON d.id = sr.device_id
         LEFT JOIN crop_health_classifications chc ON chc.reading_id = sr.id
         ORDER BY sr.recorded_at DESC"
    );
    $filename = 'all_sensor_readings';

} elseif ($type === 'all_users' && $role === 'admin') {
    $header = ['User ID', 'Name', 'Phone', 'Role', 'Status', 'Created'];
    $rows = $conn->query("SELECT id, full_name, phone_number, role, account_status, created_at FROM users ORDER BY id");
    $filename = 'users';
}

if ($rows === null) {
    http_response_code(403);
    echo 'Not allowed.';
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");               // UTF-8 BOM so Excel reads it correctly
fputcsv($out, $header);
while ($r = $rows->fetch_row()) {
    fputcsv($out, array_map('csv_safe', $r));
}
fclose($out);
