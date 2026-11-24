<?php
include 'includes/header.php';
require_role('loan_officer');
$officer_id = get_loan_officer_id_by_user($conn, $_SESSION['user_id']);

// Builds the report HTML file for a farmer. Returns false if the farmer is not found.
// The file has its own Print / Save as PDF button and a clean print layout (no extra software needed).
function write_report($conn, $farmer_id, $path) {
    $farmer_id = (int)$farmer_id;
    $farmer = $conn->query("SELECT f.*, u.full_name, u.phone_number FROM farmers f JOIN users u ON u.id=f.user_id WHERE f.id=$farmer_id")->fetch_assoc();
    if (!$farmer) return false;

    $scores = $conn->query("SELECT acs.*, gs.crop_type, gs.start_date, gs.status season_status FROM agri_credit_scores acs JOIN growing_seasons gs ON gs.id=acs.season_id WHERE acs.farmer_id=$farmer_id ORDER BY acs.last_updated DESC");
    $scoreRows = []; while ($x = $scores->fetch_assoc()) $scoreRows[] = $x;

    $health = ['Healthy' => 0, 'Monitor' => 0, 'Critical' => 0];
    $hq = $conn->query("SELECT chc.health_status, COUNT(*) c FROM crop_health_classifications chc JOIN sensor_readings sr ON sr.id=chc.reading_id JOIN growing_seasons gs ON gs.id=sr.season_id WHERE gs.farmer_id=$farmer_id GROUP BY chc.health_status");
    while ($x = $hq->fetch_assoc()) $health[$x['health_status']] = (int)$x['c'];
    $totalReadings = array_sum($health);

    $span = $conn->query("SELECT MIN(sr.recorded_at) first_r, MAX(sr.recorded_at) last_r FROM sensor_readings sr JOIN growing_seasons gs ON gs.id=sr.season_id WHERE gs.farmer_id=$farmer_id")->fetch_assoc();
    $first = $conn->query("SELECT MIN(start_date) d FROM growing_seasons WHERE farmer_id=$farmer_id")->fetch_assoc();
    $periodStart = $first['d'] ?? date('Y-m-d');
    $periodEnd = date('Y-m-d');
    $devices = (int)$conn->query("SELECT COUNT(*) c FROM iot_devices WHERE farmer_id=$farmer_id")->fetch_assoc()['c'];

    $latestScore = $scoreRows[0]['score_value'] ?? 'N/A';
    $latestTier = $scoreRows[0]['tier'] ?? 'N/A';
    $healthyPct = $totalReadings ? round($health['Healthy'] / $totalReadings * 100) . '%' : 'n/a';

    $adv = $conn->query("SELECT advisory_text, generated_at FROM advisories WHERE farmer_id=$farmer_id ORDER BY generated_at DESC LIMIT 3");
    $advRows = []; while ($x = $adv->fetch_assoc()) $advRows[] = $x;

    $css = 'body{font-family:Arial,sans-serif;color:#222;max-width:820px;margin:30px auto;padding:0 20px;line-height:1.4}'
         . 'h1{color:#2E7D32;margin:0}h2{color:#2E7D32;border-bottom:2px solid #2E7D32;padding-bottom:4px;margin-top:28px;font-size:18px}'
         . '.sub{color:#666;margin:4px 0 18px}table{border-collapse:collapse;width:100%;margin:10px 0}'
         . 'th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:14px}th{background:#eef5ee}'
         . '.kpi{display:flex;gap:12px;margin:14px 0}.kpi div{flex:1;border:1px solid #ccc;border-radius:6px;padding:12px;text-align:center}'
         . '.kpi b{display:block;font-size:24px;color:#2E7D32}.kpi span{font-size:12px;color:#666}'
         . '.btn{background:#2E7D32;color:#fff;border:0;padding:10px 18px;border-radius:4px;cursor:pointer;font-size:14px}'
         . '.note{font-size:12px;color:#777;margin-top:30px;border-top:1px solid #ddd;padding-top:10px}'
         . '@page{margin:15mm}@media print{.noprint{display:none}body{margin:0;max-width:none}tr,.kpi div{page-break-inside:avoid}}';

    $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>AgroCredit Report - ' . h($farmer['full_name']) . '</title><style>' . $css . '</style></head><body>';
    $html .= '<div class="noprint" style="text-align:right"><button class="btn" onclick="window.print()">Print / Save as PDF</button></div>';
    $html .= '<h1>MicroBoost AgroCredit Report</h1><p class="sub">Generated ' . date('d M Y, H:i') . ' for loan officer review</p>';

    $html .= '<h2>Farmer</h2><table>'
          . '<tr><th style="width:30%">Name</th><td>' . h($farmer['full_name']) . '</td></tr>'
          . '<tr><th>Phone</th><td>' . h($farmer['phone_number']) . '</td></tr>'
          . '<tr><th>County</th><td>' . h($farmer['county']) . '</td></tr>'
          . '<tr><th>Primary crop</th><td>' . h($farmer['primary_crop']) . '</td></tr>'
          . '<tr><th>Farm size</th><td>' . h($farmer['farm_size_acres']) . ' acres</td></tr>'
          . '<tr><th>Registered devices</th><td>' . $devices . '</td></tr>'
          . '<tr><th>Report period</th><td>' . h($periodStart) . ' to ' . h($periodEnd) . '</td></tr></table>';

    $html .= '<h2>Summary</h2><div class="kpi">'
          . '<div><b>' . h($latestScore) . '</b><span>Latest AgroCredit score (' . h($latestTier) . ')</span></div>'
          . '<div><b>' . $totalReadings . '</b><span>Sensor readings analysed</span></div>'
          . '<div><b>' . $healthyPct . '</b><span>Readings classed Healthy</span></div></div>';
    if ($span['last_r']) $html .= '<p style="font-size:13px;color:#555">First reading: ' . h($span['first_r']) . ' &nbsp;|&nbsp; Latest reading: ' . h($span['last_r']) . '</p>';

    $html .= '<h2>Crop health record</h2><table><tr><th>Healthy</th><th>Monitor</th><th>Critical</th></tr>'
          . '<tr><td>' . $health['Healthy'] . '</td><td>' . $health['Monitor'] . '</td><td>' . $health['Critical'] . '</td></tr></table>';

    $html .= '<h2>AgroCredit score by season</h2>';
    if ($scoreRows) {
        $html .= '<table><tr><th>Crop</th><th>Season start</th><th>Season status</th><th>Score</th><th>Tier</th><th>Last updated</th></tr>';
        foreach ($scoreRows as $x) {
            $html .= '<tr><td>' . h($x['crop_type']) . '</td><td>' . h($x['start_date']) . '</td><td>' . h($x['season_status']) . '</td><td>' . h($x['score_value']) . '</td><td>' . h($x['tier']) . '</td><td>' . h($x['last_updated']) . '</td></tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<p>No scores yet. A score is created once the farmer has sensor readings.</p>';
    }

    if ($advRows) {
        $html .= '<h2>Recent advisories sent to the farmer</h2><table><tr><th style="width:25%">Date</th><th>Advisory</th></tr>';
        foreach ($advRows as $x) $html .= '<tr><td>' . h($x['generated_at']) . '</td><td>' . h($x['advisory_text']) . '</td></tr>';
        $html .= '</table>';
    }

    $html .= '<p class="note">How to read this report: crop health labels and the score are calculated from sensor readings using fixed, published thresholds (see the Guide page in MicroBoost). '
          . 'They are an indicator of farming practice and data consistency to support a lending decision, not a credit decision in themselves.</p>';
    $html .= '</body></html>';

    file_put_contents($path, $html);
    return true;
}

// ---- GENERATE (Create) ----
if (isset($_GET['generate'])) {
    $farmer_id = (int)$_GET['generate'];
    $filename = "agrocredit_report_farmer{$farmer_id}_" . time() . ".html";
    if (write_report($conn, $farmer_id, __DIR__ . "/reports/$filename")) {
        $relativePath = "reports/$filename";
        $stmt = $conn->prepare("INSERT INTO agri_credit_reports (loan_officer_id, farmer_id, report_path, generated_at) VALUES (?,?,?,NOW())");
        $stmt->bind_param("iis", $officer_id, $farmer_id, $relativePath);
        $stmt->execute();
        flash_set('success', 'AgroCredit report generated.');
    } else {
        flash_set('error', 'Farmer not found.');
    }
    header("Location: lender_reports.php");
    exit();
}

// ---- REGENERATE (Update) ----
if (isset($_GET['regenerate'])) {
    $id = (int)$_GET['regenerate'];
    $r = $conn->query("SELECT * FROM agri_credit_reports WHERE id=$id AND loan_officer_id=$officer_id")->fetch_assoc();
    if ($r && write_report($conn, (int)$r['farmer_id'], __DIR__ . "/" . $r['report_path'])) {
        $conn->query("UPDATE agri_credit_reports SET generated_at=NOW() WHERE id=$id");
        flash_set('success', 'Report refreshed with the latest scores.');
    } else {
        flash_set('error', 'Report not found.');
    }
    header("Location: lender_reports.php");
    exit();
}

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $r = $conn->query("SELECT * FROM agri_credit_reports WHERE id=$id AND loan_officer_id=$officer_id")->fetch_assoc();
    if ($r) {
        $filePath = __DIR__ . "/" . $r['report_path'];
        if (file_exists($filePath)) unlink($filePath);
        $conn->query("DELETE FROM agri_credit_reports WHERE id=$id");
        flash_set('success', 'Report deleted.');
    }
    header("Location: lender_reports.php");
    exit();
}

$reports = $conn->query(
    "SELECT r.*, u.full_name farmer_name FROM agri_credit_reports r
     JOIN farmers f ON f.id = r.farmer_id JOIN users u ON u.id = f.user_id
     WHERE r.loan_officer_id = $officer_id ORDER BY r.generated_at DESC"
);

$allFarmers = $conn->query(
    "SELECT f.id, u.full_name FROM farmers f JOIN users u ON u.id=f.user_id WHERE u.account_status='active' ORDER BY u.full_name"
);
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fa-solid fa-file-lines text-danger me-2"></i>AgroCredit Reports</h2>
        <div class="dropdown">
            <button class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown"><i class="fa-solid fa-plus me-1"></i>Generate Report</button>
            <ul class="dropdown-menu" style="max-height:300px;overflow:auto;">
                <?php while ($f = $allFarmers->fetch_assoc()): ?>
                    <li><a class="dropdown-item" href="lender_reports.php?generate=<?php echo $f['id']; ?>"><?php echo h($f['full_name']); ?></a></li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>
    <?php render_flashes(); ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Farmer</th><th>Generated</th><th>File</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if ($reports->num_rows === 0): ?><tr><td colspan="4" class="text-center text-muted py-4">No reports generated yet.</td></tr><?php endif; ?>
                    <?php while ($r = $reports->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold"><?php echo h($r['farmer_name']); ?></td>
                            <td><?php echo h($r['generated_at']); ?></td>
                            <td><code><?php echo h($r['report_path']); ?></code></td>
                            <td class="text-end">
                                <a href="<?php echo h($r['report_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye me-1"></i>View / Export</a>
                                <a href="lender_reports.php?regenerate=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-success" title="Refresh with latest scores"><i class="fa-solid fa-rotate"></i></a>
                                <a href="lender_reports.php?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this report?');"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
