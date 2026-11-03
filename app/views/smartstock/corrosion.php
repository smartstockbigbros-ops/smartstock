<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); include __DIR__ . '/_top.php';
$cls = ['Low' => 'low', 'Moderate' => 'mod', 'High' => 'high', 'Critical' => 'crit'];
?>
<section class="risk-row">
    <div class="card risk-card">
        <div class="stat-label tiny upper">Corrosion Risk Level</div>
        <div class="risk-badge <?= $cls[$risk] ?>"><?= ss_icon('alert', 26) ?><span><?= strtoupper($risk) ?></span></div>
    </div>
    <button class="card start-card" data-start-sensor>
        <span class="play"><?= ss_icon('play', 14) ?></span><span>START SENSOR MANUALLY</span>
    </button>
</section>

<section class="stat-grid four">
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('thermo', 14) ?></span>Temperature</div>
        <div class="stat-value"><?= number_format($latest['temp'], 1) ?> <small>C</small></div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('drop', 14) ?></span>Humidity</div>
        <div class="stat-value"><?= $latest['hum'] ?>% <small>RH</small></div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('clock', 14) ?></span>Exposure Duration</div>
        <div class="stat-value orange"><?= $exposure ?></div><div class="stat-sub">affecting <?= $high_items ?> of <?= $item_total ?> items</div></div>
    <div class="card stat"><div class="stat-label"><span class="chip-ic"><?= ss_icon('wifi', 14) ?></span>Sensor Status</div>
        <div class="stat-value teal sm"><?= $online ? 'Online' : 'Offline' ?></div><div class="stat-sub"><?= $online ? 'needs attention' : 'no new readings' ?></div></div>
</section>

<section class="card">
    <div class="card-head">Environment Condition</div>
    <div class="condition"><span class="warn-dot">!</span><p><?= htmlspecialchars($message) ?></p></div>
</section>

<a class="btn btn-dark float-btn" href="<?= site_url('dashboard') ?>">Go back to Dashboard</a>
<?php include __DIR__ . '/_bottom.php'; ?>
