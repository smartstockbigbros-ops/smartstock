<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
include __DIR__ . '/_top.php';

$reason_label = [
    'initial_stock' => 'Initial stock',
    'manual_adjust' => 'Manual +/-',
    'edit'          => 'Edited',
    'deleted'       => 'Item deleted',
    'sale'          => 'Sold (POS)',
];
?>
<div class="card">
    <div class="card-title">
        <?= html_escape($item['item_name']) ?>
        <span class="stat-sub">SKU <?= html_escape($item['sku']) ?> · currently <b><?= number_format($item['quantity']) ?></b> <?= html_escape($item['unit']) ?> on hand</span>
    </div>
    <a class="link" href="<?= site_url('inventory') ?>">&larr; Back to inventory</a>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>Date &amp; time</th><th>Change</th><th>Quantity after</th><th>Reason</th><th>Note</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($movements as $m): ?>
            <tr>
                <td><?= html_escape(date('M j, Y · g:i A', strtotime($m['created_at']))) ?></td>
                <td><span class="<?= $m['delta'] > 0 ? 'risk-text low' : ($m['delta'] < 0 ? 'risk-text high' : '') ?>"><?= $m['delta'] > 0 ? '+' : '' ?><?= (int) $m['delta'] ?></span></td>
                <td><?= number_format($m['quantity_after']) ?></td>
                <td><?= html_escape($reason_label[$m['reason']] ?? $m['reason']) ?></td>
                <td><?= html_escape($m['note'] ?? '') ?></td>
                <td><?= html_escape($m['actor'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$movements): ?>
            <tr><td colspan="6" class="empty">No stock movements recorded for this item yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
