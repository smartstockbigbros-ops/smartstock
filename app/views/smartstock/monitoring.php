<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); include __DIR__ . '/_top.php';
$sc = ['Normal' => 'normal', 'Elevated' => 'elevated', 'Critical' => 'critical'];
?>
<section class="stat-grid three">
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('thermo', 14) ?></span>Temperature</div>
        <div class="stat-value"><?= number_format($latest['temp'], 1) ?> <small>C</small></div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('drop', 14) ?></span>Humidity</div>
        <div class="stat-value"><?= $latest['hum'] ?>% <small>RH</small></div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('wifi', 14) ?></span>Sensor Status</div>
        <div class="status-line <?= $online ? 'on' : 'off' ?>"><i></i><?= $online ? 'Online' : 'Offline' ?></div>
        <div class="stat-sub">Last Update: <?= date('g:i a', $latest['ts']) ?>
            · <a href="#" class="link" data-<?= $online ? 'stop' : 'start' ?>-sensor><?= $online ? 'Simulate offline' : 'Bring online' ?></a></div></div>
</section>

<section class="chart-grid">
    <div class="card"><div class="card-title upper">Temperature (C)</div><?= ss_chart(array_column($series, 'temp'), 'temp') ?></div>
    <div class="card"><div class="card-title upper">Humidity (% RH)</div><?= ss_chart(array_column($series, 'hum'), 'hum') ?></div>
</section>

<section class="card grow">
    <div class="card-title">Recent Readings</div>
    <table class="table">
        <thead><tr><th>Date &amp; Time</th><th>Temperature</th><th>Humidity</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr><td><?= date('M j, Y · g:i A', $r['ts']) ?></td><td><?= number_format($r['temp'], 1) ?> C</td><td><?= $r['hum'] ?>% RH</td>
                <td><span class="pill <?= $sc[$r['status']] ?>"><?= $r['status'] ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/_bottom.php'; ?>
