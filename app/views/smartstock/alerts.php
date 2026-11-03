<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); include __DIR__ . '/_top.php';
$icons = ['warning' => 'alert', 'danger' => 'xcircle', 'success' => 'check'];
?>
<section class="card alerts-card">
    <div class="alerts-head"><button class="btn btn-teal btn-sm" id="markAll" <?= $unread ? '' : 'disabled' ?>>Mark all as Read</button></div>
    <ul class="alert-list">
    <?php foreach ($alerts as $a): ?>
        <li class="alert-row">
            <span class="alert-ic <?= $a['type'] ?>"><?= ss_icon($icons[$a['type']], 18) ?></span>
            <div class="alert-body"><div class="alert-title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="alert-msg"><?= htmlspecialchars($a['message']) ?></div></div>
            <time><?= date('g:i A', $a['ts']) ?></time>
            <span class="read-pill <?= $a['read'] ? 'read' : 'unread' ?>"><?= $a['read'] ? 'Read' : 'Unread' ?></span>
        </li>
    <?php endforeach; ?>
    </ul>
    <div class="end-note">--- End of Notification ---</div>
</section>
<?php include __DIR__ . '/_bottom.php'; ?>
