<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
include __DIR__ . '/_top.php';
?>
<div class="card">
    <div class="card-title">Import inventory from CSV</div>
    <p class="stat-sub">
        The file must have a header row matching <a class="link" href="<?= site_url('inventory/export') ?>">Export CSV</a>'s output exactly:
        <code><?= html_escape(implode(', ', $columns)) ?></code>.
        A row whose SKU already exists updates that item (and logs the quantity change in its history); a new SKU creates a new item.
    </p>

    <?php if ($result && !empty($result['error'])): ?>
        <div class="notice" style="background:#fdecec;border-color:#f3b4b4;color:#92201e"><?= html_escape($result['error']) ?></div>
    <?php endif; ?>
    <?php if ($result && empty($result['error']) && $result['skipped']): ?>
        <div class="notice" style="background:#fdecec;border-color:#f3b4b4;color:#92201e">Skipped <?= count($result['skipped']) ?> row(s):
            <ul><?php foreach ($result['skipped'] as [$row_num, $reason]): ?><li>Row <?= $row_num ?>: <?= html_escape($reason) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('inventory/import') ?>" enctype="multipart/form-data">
        <label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
        <div class="form-actions">
            <button class="btn btn-teal" type="submit">Import</button>
            <a class="btn btn-light" href="<?= site_url('inventory') ?>">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
