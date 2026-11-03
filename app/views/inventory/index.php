<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
include __DIR__ . '/_top.php';

$status_of = function ($i) {
    if ((int) $i['quantity'] === 0) return ['out', 'Out of stock'];
    if ((int) $i['quantity'] <= (int) $i['reorder_level']) return ['low', 'Low stock'];
    return ['normal', 'In stock'];
};
$filtered = ($q !== '' || $category !== '' || $status !== '');
?>
<?php if (!empty($flash)): ?><div class="notice"><?= html_escape($flash) ?></div><?php endif; ?>

<div class="stat-grid four">
    <div class="card stat"><div class="stat-label upper tiny">Item types</div><div class="stat-value"><?= number_format($stats['items']) ?></div></div>
    <div class="card stat"><div class="stat-label upper tiny">Units on hand</div><div class="stat-value teal"><?= number_format($stats['units']) ?></div></div>
    <div class="card stat"><div class="stat-label upper tiny">Low / out of stock</div><div class="stat-value <?= ($stats['low'] + $stats['out_of_stock']) ? 'orange' : '' ?>"><?= (int) $stats['low'] ?> <small>low</small> · <?= (int) $stats['out_of_stock'] ?> <small>out</small></div></div>
    <div class="card stat"><div class="stat-label upper tiny">Stock value</div><div class="stat-value">₱<?= number_format($stats['value'], 2) ?></div></div>
</div>

<div class="card">
    <form class="inv-toolbar" method="get" action="<?= site_url('inventory') ?>">
        <input type="search" name="q" value="<?= html_escape($q) ?>" placeholder="Search name, SKU, location, supplier…" aria-label="Search inventory">
        <select name="category" aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= html_escape($c) ?>" <?= $c === $category ? 'selected' : '' ?>><?= html_escape($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" aria-label="Stock status">
            <option value="">Any status</option>
            <option value="ok"  <?= $status === 'ok'  ? 'selected' : '' ?>>In stock</option>
            <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low stock</option>
            <option value="out" <?= $status === 'out' ? 'selected' : '' ?>>Out of stock</option>
        </select>
        <button class="btn btn-dark btn-sm" type="submit">Filter</button>
        <?php if ($filtered): ?><a class="link" href="<?= site_url('inventory') ?>">Clear</a><?php endif; ?>
        <a class="btn btn-light btn-sm" href="<?= site_url('inventory/reorder') ?>">Reorder list<?= ($stats['low'] + $stats['out_of_stock']) ? ' (' . (int) ($stats['low'] + $stats['out_of_stock']) . ')' : '' ?></a>
        <a class="btn btn-light btn-sm" href="<?= site_url('inventory/export') . ($q !== '' || $category !== '' || $status !== '' ? '?' . http_build_query(['q' => $q, 'category' => $category, 'status' => $status]) : '') ?>">Export CSV</a>
        <a class="btn btn-light btn-sm" href="<?= site_url('inventory/import') ?>">Import CSV</a>
        <a class="btn btn-teal btn-sm inv-add" href="<?= site_url('inventory/create') ?>"><?= $ic('plus', 15) ?> Add item</a>
    </form>

    <table class="table">
        <thead><tr>
            <th>SKU</th><th>Item</th><th>Category</th><th>Location</th><th>Qty</th><th>Status</th><th>Unit price</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($items as $i): [$cls, $label] = $status_of($i); ?>
            <tr>
                <td><b><?= html_escape($i['sku']) ?></b></td>
                <td>
                    <?= html_escape($i['item_name']) ?>
                    <?php if ($i['corrosion_sensitive']): ?><span class="tag" title="Tracked by the corrosion-risk monitor">corrosion-sensitive</span><?php endif; ?>
                    <?php if ($i['material']): ?><div class="stat-sub"><?= html_escape($i['material']) ?><?= $i['supplier'] ? ' · ' . html_escape($i['supplier']) : '' ?></div><?php endif; ?>
                </td>
                <td><?= html_escape($i['category']) ?></td>
                <td><?= html_escape($i['location'] ?? '—') ?></td>
                <td>
                    <form class="qty" method="post" action="<?= site_url('inventory/adjust/' . (int) $i['id']) ?>">
                        <input type="hidden" name="q" value="<?= html_escape($q) ?>">
                        <input type="hidden" name="category" value="<?= html_escape($category) ?>">
                        <input type="hidden" name="status" value="<?= html_escape($status) ?>">
                        <button name="delta" value="-1" aria-label="Remove one" <?= (int) $i['quantity'] === 0 ? 'disabled' : '' ?>>−</button>
                        <span><b><?= number_format($i['quantity']) ?></b> <small><?= html_escape($i['unit']) ?></small></span>
                        <button name="delta" value="1" aria-label="Add one">+</button>
                    </form>
                </td>
                <td><span class="pill <?= $cls ?>"><?= $label ?></span></td>
                <td>₱<?= number_format($i['unit_price'], 2) ?></td>
                <td class="row-actions">
                    <a class="link" href="<?= site_url('inventory/edit/' . (int) $i['id']) ?>">Edit</a>
                    <a class="link" href="<?= site_url('inventory/movements/' . (int) $i['id']) ?>">History</a>
                    <form method="post" action="<?= site_url('inventory/delete/' . (int) $i['id']) ?>" onsubmit="return confirm('Delete <?= html_escape(addslashes($i['item_name'])) ?>?')">
                        <button class="linkbtn danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="8" class="empty"><?= $filtered ? 'No items match those filters.' : 'No hardware in inventory yet. Add your first item.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
