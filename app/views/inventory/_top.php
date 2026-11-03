<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/** Shared SmartStock shell (sidebar + topbar) for the server-rendered inventory pages. */
$nav = [
    'dashboard'  => ['Dashboard', 'grid'],
    'monitoring' => ['Environment Monitoring', 'power'],
    'corrosion'  => ['Corrosion Risk', 'alert'],
    'alerts'     => ['Alerts', 'bell'],
    'inventory'  => ['Inventory', 'box'],
];
$icons = [
    'grid'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'power' => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
    'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'bell'  => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'box'   => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'chev'  => '<path d="m6 9 6 6 6-6"/>',
    'plus'  => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'check' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    'xcircle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
];
$ic = function ($n, $s = 17) { return '<svg width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-' . $n . '"/></svg>'; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1829">
    <title><?= html_escape($title) ?> · SmartStock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/smartstock.css') ?>">
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<?php foreach ($icons as $n => $paths): ?><symbol id="i-<?= $n ?>" viewBox="0 0 24 24"><?= $paths ?></symbol>
<?php endforeach; ?></defs></svg>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="<?= site_url('dashboard') ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#17a9b8" stroke-width="2.2" stroke-linecap="round"><path d="M3 7h9"/><path d="M3 12h13"/><path d="M3 17h9"/><path d="M17 6.5a4 4 0 0 1 4 0M18.3 8.3a2 2 0 0 1 1.4 0" stroke-width="1.8"/></svg>
            <span>SmartStock</span>
        </a>
        <nav>
            <?php foreach ($nav as $key => $n): ?>
                <a href="<?= site_url($key) ?>" class="nav-item <?= $page === $key ? 'active' : '' ?>"><?= $ic($n[1]) ?><span><?= $n[0] ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="local-note"><a class="local-reset" href="<?= site_url('logout') ?>">Log out</a></div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div class="title-group"><h1><?= html_escape($title) ?></h1></div>
            <div class="top-actions">
                <div class="user-chip"><span class="avatar">A</span><span>ADMIN</span></div>
            </div>
        </header>
