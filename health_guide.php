<?php
include 'includes/header.php';

$th = get_thresholds(null);
$cut = get_status_cutoffs();
$overrides = get_crop_overrides();
$axes = ['soil_moisture' => [0, 100], 'temperature' => [0, 50], 'humidity' => [0, 100]];
$what = [
    'soil_moisture' => ['icon' => 'droplet', 'color' => 'primary', 'text' => 'How much water is in the soil around the roots. Measured by the soil moisture sensor, shown as a percentage.'],
    'temperature'   => ['icon' => 'temperature-half', 'color' => 'danger', 'text' => 'Air temperature at the farm in degrees Celsius. Measured by the DHT22 sensor.'],
    'humidity'      => ['icon' => 'cloud', 'color' => 'info', 'text' => 'How much moisture is in the air, as a percentage. Measured by the DHT22 sensor.'],
];

// Colour-coded bar: red / amber / green / amber / red, with the boundary numbers underneath
function range_bar($t, $min, $max) {
    $span = $max - $min;
    $pts = [$min, max($min, $t['warn'][0]), $t['ok'][0], $t['ok'][1], min($max, $t['warn'][1]), $max];
    $cls = ['bg-danger', 'bg-warning', 'bg-success', 'bg-warning', 'bg-danger'];
    $html = '<div class="d-flex rounded overflow-hidden" style="height:22px">';
    for ($i = 0; $i < 5; $i++) {
        $w = max(0, ($pts[$i + 1] - $pts[$i]) / $span * 100);
        $html .= '<div class="' . $cls[$i] . '" style="width:' . round($w, 2) . '%"></div>';
    }
    $html .= '</div><div style="position:relative;height:20px" class="small text-muted">';
    foreach (array_unique($pts) as $p) {
        $left = ($p - $min) / $span * 100;
        $html .= '<span style="position:absolute;left:' . round($left, 2) . '%;transform:translateX(-50%)">' . $p . '</span>';
    }
    return $html . '</div>';
}

$examples = [
    ['Typical healthy day', 45, 24, 60],
    ['Soil a little dry', 20, 24, 60],
    ['Dry and hot', 20, 35, 60],
    ['Drought', 5, 40, 10],
];
?>
<div class="container my-5">
    <div class="mb-4">
        <h2 class="fw-bold"><i class="fa-solid fa-circle-info text-success me-2"></i>Crop Health Guide</h2>
        <p class="text-muted mb-0">What the system measures, which values count as healthy, and how each reading becomes <span class="badge bg-success">Healthy</span> <span class="badge bg-warning text-dark">Monitor</span> or <span class="badge bg-danger">Critical</span>. The numbers on this page come straight from the rules the system uses.</p>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">1. The three measurements and their healthy ranges</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 mb-4 small">
                <span><span class="badge bg-success">&nbsp;</span> Healthy range</span>
                <span><span class="badge bg-warning">&nbsp;</span> Watch: outside healthy, not extreme</span>
                <span><span class="badge bg-danger">&nbsp;</span> Danger: extreme</span>
            </div>
            <div class="row g-4">
            <?php foreach ($th as $key => $t): [$amin, $amax] = $axes[$key]; ?>
                <div class="col-lg-4">
                    <h5 class="fw-bold"><i class="fa-solid fa-<?php echo $what[$key]['icon']; ?> text-<?php echo $what[$key]['color']; ?> me-2"></i><?php echo h($t['label']); ?> (<?php echo h($t['unit']); ?>)</h5>
                    <p class="small text-muted"><?php echo h($what[$key]['text']); ?></p>
                    <?php echo range_bar($t, $amin, $amax); ?>
                    <ul class="small mt-2 mb-0 ps-3">
                        <li><strong>Healthy:</strong> <?php echo $t['ok'][0]; ?> to <?php echo $t['ok'][1]; ?> <?php echo h($t['unit']); ?></li>
                        <li><strong>Watch:</strong> <?php echo $t['warn'][0]; ?> to <?php echo $t['ok'][0]; ?> or <?php echo $t['ok'][1]; ?> to <?php echo $t['warn'][1]; ?> <?php echo h($t['unit']); ?> (loses <?php echo $t['pen_warn']; ?> points)</li>
                        <li><strong>Danger:</strong> below <?php echo $t['warn'][0]; ?> or above <?php echo $t['warn'][1]; ?> <?php echo h($t['unit']); ?> (loses <?php echo $t['pen_bad']; ?> points)</li>
                    </ul>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">2. How a reading becomes a label</div>
        <div class="card-body">
            <ol class="mb-3">
                <li>Every reading starts with <strong>100 points</strong>.</li>
                <li>Each measurement in the <em>Watch</em> zone loses a few points. In the <em>Danger</em> zone it loses many more (amounts are listed above).</li>
                <li>The points left decide the label:</li>
            </ol>
            <table class="table table-bordered w-auto mb-3">
                <thead class="table-light"><tr><th>Points left</th><th>Label</th><th>What to do</th></tr></thead>
                <tbody>
                    <tr><td><?php echo $cut['healthy']; ?> or more</td><td><span class="badge bg-success">Healthy</span></td><td>Carry on with your normal care.</td></tr>
                    <tr><td><?php echo $cut['monitor']; ?> to <?php echo $cut['healthy'] - 1; ?></td><td><span class="badge bg-warning text-dark">Monitor</span></td><td>Check the farm in the next day or two. Light watering or shade may help.</td></tr>
                    <tr><td>Below <?php echo $cut['monitor']; ?></td><td><span class="badge bg-danger">Critical</span></td><td>Act now. Irrigate or drain, protect the crop, and contact your extension officer if it continues.</td></tr>
                </tbody>
            </table>
            <p class="small text-muted mb-0">Soil moisture carries the biggest penalty because it affects the crop most directly. A <strong>Critical</strong> reading always sends an advisory. Other warnings send at most one advisory every 6 hours so the farmer is not flooded with messages. These labels feed the AgroCredit score that lenders see, so consistent, honest readings matter.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">3. Ranges for specific crops</div>
        <div class="card-body">
            <p class="small text-muted">The system uses the crop name from your growing season. Crops not listed use the standard ranges. These example ranges are placeholders to be confirmed with an agronomist.</p>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Crop</th><th>Soil moisture (healthy)</th><th>Temperature (healthy)</th><th>Humidity (healthy)</th></tr></thead>
                    <tbody>
                    <?php foreach (array_merge(['standard (all other crops)' => null], array_fill_keys(array_keys($overrides), 1)) as $name => $isCrop):
                        $t = get_thresholds($isCrop ? $name : null); ?>
                        <tr>
                            <td class="fw-bold"><?php echo h(ucfirst($name)); ?></td>
                            <?php foreach (['soil_moisture', 'temperature', 'humidity'] as $k): ?>
                                <td><?php echo $t[$k]['ok'][0]; ?> to <?php echo $t[$k]['ok'][1]; ?> <?php echo h($t[$k]['unit']); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">4. Worked examples</div>
        <div class="card-body p-0">
            <table class="table mb-0 align-middle">
                <thead class="table-light"><tr><th>Situation</th><th>Soil moisture</th><th>Temp</th><th>Humidity</th><th>Result</th><th>Why</th></tr></thead>
                <tbody>
                <?php foreach ($examples as [$name, $m, $t, $hu]): $r = classify_crop_health($m, $t, $hu); ?>
                    <tr>
                        <td><?php echo h($name); ?></td><td><?php echo $m; ?>%</td><td><?php echo $t; ?>C</td><td><?php echo $hu; ?>%</td>
                        <td><span class="badge <?php echo health_badge_class($r['status']); ?>"><?php echo h($r['status']); ?></span> <span class="text-muted small"><?php echo $r['score']; ?>/100</span></td>
                        <td class="small"><?php echo $r['reasons'] ? h(implode('; ', $r['reasons'])) : 'Everything is in the healthy range.'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">5. Try it yourself</div>
        <div class="card-body">
            <p class="small text-muted">Type values to see the label before logging a real reading. Nothing is saved here.</p>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Crop</label>
                    <select id="g_crop" class="form-select">
                        <option value="">Standard</option>
                        <?php foreach (array_keys($overrides) as $c): ?><option value="<?php echo h($c); ?>"><?php echo h(ucfirst($c)); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Soil moisture %</label><input type="number" step="0.1" class="form-control" id="g_m" value="45"><div class="form-text" id="g_mh"></div></div>
                <div class="col-md-3"><label class="form-label">Temperature C</label><input type="number" step="0.1" class="form-control" id="g_t" value="24"><div class="form-text" id="g_th"></div></div>
                <div class="col-md-3"><label class="form-label">Humidity %</label><input type="number" step="0.1" class="form-control" id="g_h" value="60"><div class="form-text" id="g_hh"></div></div>
                <div class="col-12" id="g_out"></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">6. Before you enter a reading</div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong>Don't guess.</strong> Values should come from your sensor. The manual Log Reading form is meant for testing or when a sensor is being repaired.</li>
                <li>Soil moisture and humidity must be between 0 and 100 %. Temperature must be between -20 and 60 C. Anything else is rejected as a mistake.</li>
                <li>A device must be registered to your account and you need an <strong>active</strong> growing season before readings are accepted.</li>
                <li>Real soil sensors need calibration: note the raw value in dry air and in water so the percentage is correct.</li>
            </ul>
        </div>
    </div>

    <?php if (($_SESSION['role'] ?? '') === 'farmer'): ?>
        <a href="farmer_sensor_data.php" class="btn btn-agri"><i class="fa-solid fa-droplet me-1"></i>Go to Sensor Data</a>
        <a href="farmer_dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
    <?php elseif (!isset($_SESSION['role'])): ?>
        <a href="register.php" class="btn btn-agri">Create an account</a>
        <a href="login.php" class="btn btn-outline-secondary">Login</a>
    <?php endif; ?>
</div>

<script src="assets/health_preview.js"></script>
<script>
initHealthPreview({
    inputs: { m: 'g_m', t: 'g_t', h: 'g_h' },
    hints: { soil_moisture: 'g_mh', temperature: 'g_th', humidity: 'g_hh' },
    output: 'g_out',
    cropEl: 'g_crop'
});
</script>
<?php include 'includes/footer.php'; ?>
