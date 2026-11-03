<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$icons = [
    'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'power'   => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
    'alert'   => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'bell'    => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'thermo'  => '<path d="M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0z"/>',
    'drop'    => '<path d="M12 2.7s7 7.4 7 12.3a7 7 0 0 1-14 0c0-4.9 7-12.3 7-12.3z"/>',
    'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'wifi'    => '<path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><path d="M12 19.5h.01"/>',
    'box'     => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'check'   => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    'xcircle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
    'play'    => '<path d="m8 5 11 7-11 7z" fill="currentColor"/>',
    'cart'    => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/>',
    'plus'    => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    'chev'    => '<path d="m6 9 6 6 6-6"/>',
];
$ic = function ($n, $s = 17) { return '<svg width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-' . $n . '"/></svg>'; };
$nav = [
    'dashboard'  => ['Dashboard', 'grid'],
    'monitoring' => ['Environment Monitoring', 'power'],
    'corrosion'  => ['Corrosion Risk', 'alert'],
    'alerts'     => ['Alerts', 'bell'],
    'inventory'  => ['Inventory', 'box'],
    'pos'        => ['Point of Sale', 'cart'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1829">
    <!-- Access is now enforced server-side by AuthMiddleware (see routes.php) rather than
         this localStorage check, which would otherwise bounce every real login back to /login. -->
    <title><?= htmlspecialchars($title) ?> · SmartStock</title>
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
        <div class="local-note">
            <div class="local-row"><i id="netDot"></i><span id="netText">Saved on this device</span></div>
            <button class="local-reset" id="resetData" type="button">Reset demo data</button>
            <button class="logout-btn" type="button" data-logout><?= $ic('logout', 15) ?> Log out</button>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="title-group">
                <h1><?= htmlspecialchars($title) ?></h1>
                <?php if ($page === 'monitoring'): ?>
                    <button class="btn btn-dark btn-sm" data-start-sensor><?= $ic('check', 15) ?> Start Sensors Manually</button>
                <?php endif; ?>
            </div>
            <div class="top-actions">
                <a class="icon-btn" href="<?= site_url('alerts') ?>" aria-label="Notifications"><?= $ic('bell') ?><span class="dot" id="bellDot" hidden></span></a>
                <div class="user-menu">
                    <button class="user-chip" id="userBtn" type="button" aria-haspopup="true" aria-expanded="false"><span class="avatar" id="userAvatar">A</span><span id="userName">ADMIN</span><?= $ic('chev', 14) ?></button>
                    <div class="menu" id="userMenu" hidden>
                        <div class="menu-head">Signed in as <b id="userFull">admin</b></div>
                        <button type="button" class="menu-item danger" data-logout><?= $ic('logout', 15) ?> Log out</button>
                    </div>
                </div>
            </div>
        </header>
        <div id="view" class="view" data-page="<?= $page ?>"></div>
    </main>
</div>
<div class="toast" id="toast" role="status"></div>
<script>window.SS = {
    sw: "<?= base_url('sw.js') ?>",
    scope: "<?= rtrim(base_url(''), '/') ?>/",
    pages: <?= json_encode(array_map('site_url', array_keys($nav))) ?>,
    assets: <?= json_encode([base_url('assets/css/smartstock.css'), base_url('assets/js/smartstock.js')]) ?>,
    dashboardUrl: "<?= site_url('dashboard') ?>",
    loginUrl: "<?= site_url('login') ?>",
    logoutUrl: "<?= site_url('logout') ?>",
    inventoryUrl: "<?= site_url('inventory') ?>",
    hardwareItems: <?= json_encode($hardware_items ?? [], JSON_UNESCAPED_SLASHES) ?>,
    username: <?= json_encode($username ?? '') ?>,
    avatar: <?= json_encode($avatar ?? null) ?>
};</script>
<script src="<?= base_url('assets/js/smartstock.js') ?>"></script>
</body>
</html>
