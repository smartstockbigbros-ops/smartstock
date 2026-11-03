<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); include __DIR__ . '/_top.php';
$cls = ['Low' => 'low', 'Moderate' => 'mod', 'High' => 'high', 'Critical' => 'crit'];
$total = max(1, array_sum($counts));
$colors = ['Low' => '#9fe3ea', 'Moderate' => '#17a9b8', 'High' => '#f59e0b', 'Critical' => '#dc2626'];
?>
<section class="stat-grid four">
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('thermo', 14) ?></span>Temperature</div>
        <div class="stat-value"><?= number_format($latest['temp'], 1) ?> <small>C</small></div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('drop', 14) ?></span>Avg. Humidity</div>
        <div class="stat-value"><?= $avg_humidity ?>% <small>RH</small></div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('box', 14) ?></span>High Risk Items</div>
        <div class="stat-value orange"><?= $high_items ?></div><div class="stat-sub">of <?= $item_total ?> items</div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('bell', 14) ?></span>Active Alerts</div>
        <div class="stat-value red"><?= $active_alerts ?></div><div class="stat-sub">needs attention</div></div>
</section>

<section class="dash-grid">
    <div class="col">
        <div class="card">
            <table class="table compact">
                <thead><tr><th>Temperature</th><th>Humidity</th><th>Exposure Duration</th><th>Risk Level</th><th>Status</th></tr></thead>
                <tbody><tr>
                    <td><?= number_format($latest['temp'], 1) ?> C</td>
                    <td><?= $latest['hum'] ?>%</td>
                    <td><?= $exposure ?></td>
                    <td><strong class="risk-text <?= $cls[$risk] ?>"><?= $risk ?></strong></td>
                    <td><strong><?= $online ? 'Online' : 'Offline' ?></strong></td>
                </tr></tbody>
            </table>
        </div>
        <div class="card grow">
            <div class="card-title">Top Affected Metal Hardware</div>
            <table class="table">
                <thead><tr><th>Product</th><th>Category</th><th>Risk Level</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($top_items as $it): ?>
                    <tr><td><?= htmlspecialchars($it['product']) ?></td><td><?= $it['category'] ?></td>
                        <td><span class="risk-text <?= $cls[$it['risk']] ?>"><?= $it['risk'] ?></span></td><td><?= $it['status'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col">
        <div class="card grow donut-card">
            <?php
            $r = 52; $c = 2 * M_PI * $r; $off = 0;
            ?>
            <svg class="donut" viewBox="0 0 140 140" role="img" aria-label="Items by risk level">
                <circle cx="70" cy="70" r="<?= $r ?>" fill="none" stroke="#eef2f5" stroke-width="12"/>
                <?php foreach ($counts as $k => $v): if (!$v) continue; $len = $c * $v / $total; ?>
                    <circle cx="70" cy="70" r="<?= $r ?>" fill="none" stroke="<?= $colors[$k] ?>" stroke-width="12"
                        stroke-dasharray="<?= round($len - 1.5, 2) ?> <?= round($c - $len + 1.5, 2) ?>" stroke-dashoffset="<?= round(-$off, 2) ?>"
                        transform="rotate(-90 70 70)"/>
                <?php $off += $len; endforeach; ?>
                <text x="70" y="68" text-anchor="middle" class="donut-n"><?= array_sum($counts) ?></text>
                <text x="70" y="84" text-anchor="middle" class="donut-l">items</text>
            </svg>
            <ul class="legend">
                <?php foreach ($counts as $k => $v): ?>
                    <li><i style="background:<?= $colors[$k] ?>"></i><span><?= $k ?></span><b><?= $v ?></b></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card last-update">
            <div class="stat-label tiny">Last Sensor Update</div>
            <div class="update-time"><?= ss_icon('clock', 15) ?> <?= date('g:i A · M j, Y', $latest['ts']) ?></div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/_bottom.php'; ?>
