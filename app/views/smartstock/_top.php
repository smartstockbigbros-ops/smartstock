<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

if (!function_exists('ss_icon')) {
    function ss_icon($name, $size = 18)
    {
        $p = [
            'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'power'  => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
            'alert'  => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'bell'   => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'thermo' => '<path d="M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0z"/>',
            'drop'   => '<path d="M12 2.7s7 7.4 7 12.3a7 7 0 0 1-14 0c0-4.9 7-12.3 7-12.3z"/>',
            'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'wifi'   => '<path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><path d="M12 19.5h.01"/>',
            'box'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
            'check'  => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
            'xcircle'=> '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
            'play'   => '<path d="m8 5 11 7-11 7z" fill="currentColor"/>',
            'chev'   => '<path d="m6 9 6 6 6-6"/>',
        ];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
    }

    /** Smooth-ish area/line chart as inline SVG. */
    function ss_chart($values, $id)
    {
        $w = 600; $h = 150; $pad = 8;
        $n = count($values);
        if ($n < 2) return '';
        $min = min($values); $max = max($values);
        $span = max(0.5, $max - $min);
        $min -= $span * 0.35; $max += $span * 0.15; $span = $max - $min;
        $pts = [];
        foreach ($values as $i => $v) {
            $x = $i * ($w / ($n - 1));
            $y = $h - $pad - (($v - $min) / $span) * ($h - 2 * $pad);
            $pts[] = [round($x, 1), round($y, 1)];
        }
        $line = 'M' . implode(' L', array_map(function ($p) { return $p[0] . ',' . $p[1]; }, $pts));
        $area = $line . " L{$w},{$h} L0,{$h} Z";
        $grid = '';
        foreach ([0.25, 0.5, 0.75] as $g) {
            $gy = round($h * $g, 1);
            $grid .= "<line x1=\"0\" y1=\"{$gy}\" x2=\"{$w}\" y2=\"{$gy}\" class=\"grid\"/>";
        }
        return "<svg class=\"chart\" viewBox=\"0 0 {$w} {$h}\" preserveAspectRatio=\"none\" role=\"img\" aria-label=\"{$id} trend\">"
            . "<defs><linearGradient id=\"g-{$id}\" x1=\"0\" y1=\"0\" x2=\"0\" y2=\"1\"><stop offset=\"0\" stop-color=\"#17a9b8\" stop-opacity=\".28\"/><stop offset=\"1\" stop-color=\"#17a9b8\" stop-opacity=\"0\"/></linearGradient></defs>"
            . $grid . "<path d=\"{$area}\" fill=\"url(#g-{$id})\"/><path d=\"{$line}\" class=\"line\"/></svg>";
    }
}

$nav = [
    'dashboard'  => ['Dashboard', 'grid', 'dashboard'],
    'monitoring' => ['Environment Monitoring', 'power', 'monitoring'],
    'corrosion'  => ['Corrosion Risk', 'alert', 'corrosion'],
    'alerts'     => ['Alerts', 'bell', 'alerts'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> · SmartStock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/smartstock.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="<?= site_url('dashboard') ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#17a9b8" stroke-width="2.2" stroke-linecap="round"><path d="M3 7h9"/><path d="M3 12h13"/><path d="M3 17h9"/><path d="M17 6.5a4 4 0 0 1 4 0M18.3 8.3a2 2 0 0 1 1.4 0" stroke-width="1.8"/></svg>
            <span>SmartStock</span>
        </a>
        <nav>
            <?php foreach ($nav as $key => $n): ?>
                <a href="<?= site_url($n[2]) ?>" class="nav-item <?= $page === $key ? 'active' : '' ?>">
                    <?= ss_icon($n[1], 17) ?><span><?= $n[0] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="title-group">
                <h1><?= htmlspecialchars($title) ?></h1>
                <?php if ($page === 'monitoring'): ?>
                    <button class="btn btn-dark btn-sm" data-start-sensor><?= ss_icon('check', 15) ?> Start Sensors Manually</button>
                <?php endif; ?>
            </div>
            <div class="top-actions">
                <a class="icon-btn" href="<?= site_url('alerts') ?>" aria-label="Notifications">
                    <?= ss_icon('bell', 17) ?>
                    <?php if (!empty($unread)): ?><span class="dot"></span><?php endif; ?>
                </a>
                <div class="user-chip"><span class="avatar">A</span><span>ADMIN</span><?= ss_icon('chev', 14) ?></div>
            </div>
        </header>
