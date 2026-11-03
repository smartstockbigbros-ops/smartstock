<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
include __DIR__ . '/_top.php';
$total_items = array_sum(array_map('count', $grouped));
?>
<div class="card">
    <div class="card-title">
        <?= $total_items ?> item(s) at or below their reorder level, grouped by supplier.
        <span class="stat-sub">Suggested quantity tops stock back up to roughly twice the reorder level.</span>
    </div>
    <a class="link" href="<?= site_url('inventory') ?>">&larr; Back to inventory</a>
</div>

<?php if (!$total_items): ?>
    <div class="card"><div class="empty">Nothing needs reordering right now — every item is above its reorder level.</div></div>
<?php endif; ?>

<?php foreach ($grouped as $supplier => $rows): ?>
    <div class="card">
        <div class="card-title upper"><?= html_escape($supplier) ?></div>
        <table class="table">
            <thead><tr><th>SKU</th><th>Item</th><th>On hand</th><th>Reorder level</th><th>Suggested order</th><th>Est. cost</th></tr></thead>
            <tbody>
            <?php $sub = 0; foreach ($rows as $r): $cost = $r['suggested_qty'] * $r['unit_price']; $sub += $cost; ?>
                <tr>
                    <td><b><?= html_escape($r['sku']) ?></b></td>
                    <td><?= html_escape($r['item_name']) ?></td>
                    <td><span class="pill <?= (int) $r['quantity'] === 0 ? 'out' : 'low' ?>"><?= number_format($r['quantity']) ?> <?= html_escape($r['unit']) ?></span></td>
                    <td><?= number_format($r['reorder_level']) ?></td>
                    <td><b><?= number_format($r['suggested_qty']) ?></b> <?= html_escape($r['unit']) ?></td>
                    <td>₱<?= number_format($cost, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="5" style="text-align:right"><b>Supplier subtotal</b></td><td><b>₱<?= number_format($sub, 2) ?></b></td></tr></tfoot>
        </table>
    </div>
<?php endforeach; ?>
<?php include __DIR__ . '/_bottom.php'; ?>
