<?php
include 'includes/header.php';
require_role('farmer');
$farmer_id = get_farmer_id_by_user($conn, $_SESSION['user_id']);

$activeSeason = $conn->query("SELECT * FROM growing_seasons WHERE farmer_id=$farmer_id AND status='active' ORDER BY start_date DESC LIMIT 1")->fetch_assoc();
$latestScore = $conn->query("SELECT * FROM agri_credit_scores WHERE farmer_id=$farmer_id ORDER BY last_updated DESC LIMIT 1")->fetch_assoc();
$latestReading = $conn->query(
    "SELECT sr.* FROM sensor_readings sr JOIN growing_seasons gs ON gs.id=sr.season_id
     WHERE gs.farmer_id=$farmer_id ORDER BY sr.recorded_at DESC LIMIT 1"
)->fetch_assoc();
$latestAdvisory = $conn->query("SELECT * FROM advisories WHERE farmer_id=$farmer_id ORDER BY generated_at DESC LIMIT 1")->fetch_assoc();
$deviceCount = $conn->query("SELECT COUNT(*) c FROM iot_devices WHERE farmer_id=$farmer_id")->fetch_assoc()['c'];
$seasonCount = $conn->query("SELECT COUNT(*) c FROM growing_seasons WHERE farmer_id=$farmer_id")->fetch_assoc()['c'];
$readingCount = $conn->query("SELECT COUNT(*) c FROM sensor_readings sr JOIN growing_seasons gs ON gs.id=sr.season_id WHERE gs.farmer_id=$farmer_id")->fetch_assoc()['c'];
$scoreCount = $conn->query("SELECT COUNT(*) c FROM agri_credit_scores WHERE farmer_id=$farmer_id")->fetch_assoc()['c'];
$advisoryCount = $conn->query("SELECT COUNT(*) c FROM advisories WHERE farmer_id=$farmer_id")->fetch_assoc()['c'];

$crop = $activeSeason['crop_type'] ?? null;
$thr = get_thresholds($crop);
$latestCls = $latestReading ? classify_crop_health($latestReading['soil_moisture'], $latestReading['temperature'], $latestReading['humidity'], $crop) : null;

$steps = [
    ['Start a growing season', $activeSeason ? true : false, 'farmer_seasons.php', 'Go to Seasons', 'Tell the system which crop you are growing. Readings and scores are tied to a season.'],
    ['Register your sensor device', $deviceCount > 0, 'farmer_devices.php', 'Go to Devices', 'Enter the MAC address printed on or reported by your sensor node.'],
    ['Get your first sensor reading', $readingCount > 0, 'farmer_sensor_data.php', 'Go to Sensor Data', 'Readings arrive from your device. Check the healthy ranges first.'],
    ['Calculate your AgroCredit score', $scoreCount > 0, 'farmer_credit_score.php', 'Go to Credit Score', 'The score summarises your crop health history for lenders.'],
    ['Read your first advisory', $advisoryCount > 0, 'farmer_advisories.php', 'Go to Advisories', 'Advice is written when a reading needs attention.'],
];
$doneSteps = count(array_filter(array_column($steps, 1)));
?>
<?php
if (!function_exists('fd_ago')) {
    function fd_ago($ts) {
        $t = strtotime((string)$ts);
        if (!$t) return '';
        $d = time() - $t;
        if ($d < 60) return 'just now';
        if ($d < 3600) return floor($d / 60) . ' min ago';
        if ($d < 86400) return floor($d / 3600) . ' h ago';
        if ($d < 604800) return floor($d / 86400) . ' day' . (floor($d / 86400) > 1 ? 's' : '') . ' ago';
        return date('j M Y', $t);
    }
}
$firstName = explode(' ', trim($_SESSION['full_name'] ?? 'Farmer'))[0];
$metricIcons = ['soil_moisture' => 'fa-droplet', 'temperature' => 'fa-temperature-half', 'humidity' => 'fa-cloud'];
$statusClass = $latestCls ? ($latestCls['status'] === 'Healthy' ? 'ok' : ($latestCls['status'] === 'Monitor' ? 'warn' : 'bad')) : 'ok';
$tierKey = $latestScore ? strtolower($latestScore['tier']) : '';
if (!in_array($tierKey, ['gold', 'silver', 'bronze'], true)) $tierKey = 'bronze';
$ringC = 2 * M_PI * 70;
$advisoryStale = $latestAdvisory && $latestReading && strtotime($latestAdvisory['generated_at']) < strtotime($latestReading['recorded_at']) - 60;
?>
<link rel="stylesheet" href="assets/farmer.css">
<div class="fd">
  <div class="fd-wrap">

    <div class="fd-head">
        <div>
            <h1>Hello, <?php echo h($firstName); ?></h1>
            <div class="sub">
                <?php if ($latestReading): ?>Last sensor reading <?php echo h(fd_ago($latestReading['recorded_at'])); ?>
                <?php else: ?>Waiting for your first sensor reading<?php endif; ?>
            </div>
        </div>
        <span class="fd-season <?php echo $activeSeason ? '' : 'off'; ?>">
            <span class="dot"></span>
            <?php echo $activeSeason ? h($activeSeason['crop_type']) . ' season is active' : 'No active season'; ?>
        </span>
    </div>

    <?php render_flashes(); ?>

    <?php if ($doneSteps < count($steps)): ?>
    <div class="fd-card fd-steps mb-4">
        <div class="fd-card-head">
            <div>
                <h2 class="fd-card-title">Getting started</h2>
                <div class="fd-card-sub"><?php echo $doneSteps; ?> of <?php echo count($steps); ?> steps done</div>
            </div>
            <a href="health_guide.php" class="btn-fd-ghost text-decoration-none">Read the health guide</a>
        </div>
        <div class="progress mb-2"><div class="progress-bar" style="width:<?php echo round($doneSteps / count($steps) * 100); ?>%"></div></div>
        <?php $nextShown = false; foreach ($steps as $i => $st): $isNext = !$st[1] && !$nextShown; if ($isNext) $nextShown = true; ?>
            <div class="fd-step <?php echo $isNext ? 'next' : ''; ?>">
                <div class="mk <?php echo $st[1] ? 'done' : 'todo'; ?>"><i class="fa-<?php echo $st[1] ? 'solid fa-circle-check' : 'regular fa-circle'; ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="t"><?php echo h($st[0]); ?><?php if ($isNext): ?> <span class="fd-pill ok ms-1">Next</span><?php endif; ?></div>
                    <div class="d"><?php echo h($st[4]); ?></div>
                </div>
                <?php if (!$st[1]): ?><a href="<?php echo $st[2]; ?>" class="<?php echo $isNext ? 'btn-fd' : 'btn-fd-ghost'; ?> text-decoration-none"><?php echo h($st[3]); ?></a><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fd-grid">
        <!-- FIELD CONDITIONS -->
        <div>
            <div class="fd-card">
                <div class="fd-card-head">
                    <div>
                        <h2 class="fd-card-title">Field conditions<?php echo $crop ? ' · ' . h($crop) : ''; ?></h2>
                        <div class="fd-card-sub">Your latest reading against the healthy range for your crop</div>
                    </div>
                    <a href="health_guide.php" class="fd-link">How labels are decided</a>
                </div>

                <?php if ($latestReading && $latestCls): ?>
                <div class="fd-status <?php echo $statusClass; ?>">
                    <span class="fd-pill <?php echo $statusClass; ?>"><i class="fa-solid fa-<?php echo $statusClass === 'ok' ? 'circle-check' : 'triangle-exclamation'; ?>"></i><?php echo h($latestCls['status']); ?></span>
                    <span class="big num"><?php echo (int)$latestCls['score']; ?><span class="meta"> / 100 crop health</span></span>
                    <?php if ($latestCls['reasons']): ?>
                    <div class="w-100">
                        <div class="meta"><?php echo $latestCls['status'] === 'Healthy' ? 'Healthy overall, but worth watching:' : 'What needs attention:'; ?></div>
                        <ul><?php foreach ($latestCls['reasons'] as $why): ?><li><?php echo h($why); ?></li><?php endforeach; ?></ul>
                    </div>
                    <?php else: ?>
                    <span class="meta">All values are inside the healthy ranges.</span>
                    <?php endif; ?>
                </div>

                <?php foreach ($thr as $key => $bt):
                    $v = (float)$latestReading[$key];
                    $lvl = $latestCls['details'][$key]['level'] ?? 'ok';
                    $max = $bt['unit'] === 'C' ? 50 : 100;
                    $pos = max(0, min(100, $v / $max * 100));
                    $bandL = $bt['ok'][0] / $max * 100;
                    $bandW = ($bt['ok'][1] - $bt['ok'][0]) / $max * 100;
                    $unit = $bt['unit'] === 'C' ? '°C' : $bt['unit'];
                ?>
                <div class="fd-metric <?php echo $lvl; ?>">
                    <div class="ico"><i class="fa-solid <?php echo $metricIcons[$key] ?? 'fa-gauge'; ?>"></i></div>
                    <div>
                        <div class="name"><?php echo h($bt['label']); ?></div>
                        <div class="range">Healthy: <?php echo $bt['ok'][0]; ?> to <?php echo $bt['ok'][1]; ?> <?php echo h($unit); ?></div>
                    </div>
                    <div class="val num"><?php echo h($latestReading[$key]); ?><small><?php echo h($unit); ?></small></div>
                    <div class="bar-row">
                        <div class="fd-track" role="img" aria-label="<?php echo h($bt['label']); ?> is <?php echo h($latestReading[$key] . ' ' . $unit); ?>, healthy range <?php echo $bt['ok'][0]; ?> to <?php echo $bt['ok'][1]; ?>">
                            <div class="fd-band" style="left:<?php echo round($bandL, 1); ?>%;width:<?php echo round($bandW, 1); ?>%"></div>
                            <div class="fd-mark" style="left:<?php echo round($pos, 1); ?>%"></div>
                        </div>
                        <div class="fd-scale"><span>0</span><span><?php echo $max; ?></span></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php else: ?>
                <div class="fd-empty">
                    <i class="fa-solid fa-satellite-dish"></i>
                    No sensor data yet. You have <?php echo (int)$deviceCount; ?> device(s) registered.<br>
                    <a href="farmer_sensor_data.php" class="btn-fd text-decoration-none d-inline-block mt-3">Log a reading</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CREDIT SCORE -->
        <div>
            <div class="fd-card text-center">
                <div class="fd-card-head mb-2">
                    <div class="text-start">
                        <h2 class="fd-card-title">AgroCredit score</h2>
                        <div class="fd-card-sub">What lenders see</div>
                    </div>
                    <a href="farmer_credit_score.php" class="fd-link">Details</a>
                </div>
                <?php if ($latestScore): $sv = max(0, min(100, (int)$latestScore['score_value'])); ?>
                    <div class="fd-ring <?php echo $tierKey; ?>" role="img" aria-label="Score <?php echo $sv; ?> out of 100">
                        <svg viewBox="0 0 160 160" aria-hidden="true">
                            <circle class="trk" cx="80" cy="80" r="70" fill="none" stroke-width="12"/>
                            <circle class="arc" cx="80" cy="80" r="70" fill="none" stroke-width="12"
                                    stroke-dasharray="<?php echo round($ringC, 2); ?>" stroke-dashoffset="<?php echo round($ringC * (1 - $sv / 100), 2); ?>"/>
                        </svg>
                        <div class="center"><div class="score num"><?php echo $sv; ?></div><div class="of">out of 100</div></div>
                    </div>
                    <span class="fd-tier <?php echo $tierKey; ?>"><?php echo h($latestScore['tier']); ?> tier</span>
                    <div class="fd-tiers" aria-hidden="true">
                        <span class="bronze <?php echo $tierKey === 'bronze' ? 'on' : ''; ?>">Bronze</span>
                        <span class="silver <?php echo $tierKey === 'silver' ? 'on' : ''; ?>">Silver 50+</span>
                        <span class="gold <?php echo $tierKey === 'gold' ? 'on' : ''; ?>">Gold 75+</span>
                    </div>
                    <p class="fd-card-sub mt-3 mb-0">Built from your seasonal sensor history.</p>
                <?php else: ?>
                    <div class="fd-empty">
                        <i class="fa-solid fa-star"></i>
                        You have not been scored yet.<br>
                        <a href="farmer_credit_score.php" class="btn-fd text-decoration-none d-inline-block mt-3">Calculate my score</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ADVISORY -->
    <div class="fd-section">
        <div class="fd-card">
            <div class="fd-card-head">
                <div>
                    <h2 class="fd-card-title">Latest advisory</h2>
                    <?php if ($latestAdvisory): ?><div class="fd-card-sub">Written <?php echo h(fd_ago($latestAdvisory['generated_at'])); ?></div><?php endif; ?>
                </div>
                <a href="farmer_advisories.php" class="fd-link"><?php echo $latestAdvisory ? 'All advisories' : 'Open advisories'; ?></a>
            </div>
            <?php if ($latestAdvisory): ?>
                <div class="fd-advice"><?php echo h($latestAdvisory['advisory_text']); ?></div>
                <?php if ($advisoryStale): ?>
                    <div class="fd-stale"><i class="fa-solid fa-clock-rotate-left"></i>Based on an older reading. Generate a new one to match your latest data.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="fd-empty">
                    <i class="fa-solid fa-comment-dots"></i>
                    No advisories yet.<br>
                    <a href="farmer_advisories.php" class="btn-fd text-decoration-none d-inline-block mt-3">Generate an advisory</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="fd-section fd-links">
        <a class="fd-tile" href="farmer_seasons.php"><span class="ico"><i class="fa-solid fa-calendar"></i></span><span><div class="t">Seasons</div><div class="d">Start or close a crop season</div></span></a>
        <a class="fd-tile" href="farmer_devices.php"><span class="ico"><i class="fa-solid fa-microchip"></i></span><span><div class="t">Devices (<?php echo (int)$deviceCount; ?>)</div><div class="d">Your registered sensors</div></span></a>
        <a class="fd-tile" href="farmer_crop_health.php"><span class="ico"><i class="fa-solid fa-leaf"></i></span><span><div class="t">Crop health history</div><div class="d">Past readings and labels</div></span></a>
        <a class="fd-tile" href="farmer_profile.php"><span class="ico"><i class="fa-solid fa-user"></i></span><span><div class="t">Profile</div><div class="d">Update your details</div></span></a>
    </div>

  </div>
</div>

<script src="assets/autorefresh.js"></script>
<script>initAutoRefresh({seconds: 10});</script>
<?php include 'includes/footer.php'; ?>
