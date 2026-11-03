<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
include __DIR__ . '/_top.php';
$v   = function ($k) use ($item) { return html_escape((string) ($item[$k] ?? '')); };
$err = function ($k) use ($errors) { return isset($errors[$k]) ? '<span class="field-err">' . html_escape($errors[$k]) . '</span>' : ''; };
?>
<div class="card">
    <form class="inv-form" method="post" action="<?= site_url($action) ?>" novalidate>
        <div class="grid2">
            <label>SKU / item code *<input name="sku" value="<?= $v('sku') ?>" maxlength="40" required placeholder="FAS-001"><?= $err('sku') ?></label>
            <label>Item name *<input name="item_name" value="<?= $v('item_name') ?>" maxlength="150" required placeholder="Hex Bolt M8 x 40mm"><?= $err('item_name') ?></label>
            <label>Category<input name="category" value="<?= $v('category') ?>" maxlength="60" list="cats"><?= $err('category') ?>
                <datalist id="cats"><?php foreach ($categories as $c): ?><option value="<?= html_escape($c) ?>"><?php endforeach; ?></datalist></label>
            <label>Material<input name="material" value="<?= $v('material') ?>" maxlength="60" placeholder="Galvanized Steel"><?= $err('material') ?></label>
            <label>Quantity on hand<input type="number" min="0" step="1" name="quantity" value="<?= $v('quantity') ?>"><?= $err('quantity') ?></label>
            <label>Unit<input name="unit" value="<?= $v('unit') ?>" maxlength="20" placeholder="pcs, box, kg, m"><?= $err('unit') ?></label>
            <label>Reorder level<input type="number" min="0" step="1" name="reorder_level" value="<?= $v('reorder_level') ?>"><span class="hint">Flagged “Low stock” at or below this number.</span><?= $err('reorder_level') ?></label>
            <label>Unit price (₱)<input type="number" min="0" step="0.01" name="unit_price" value="<?= $v('unit_price') ?>"><?= $err('unit_price') ?></label>
            <label>Location<input name="location" value="<?= $v('location') ?>" maxlength="80" placeholder="Rack A-2"><?= $err('location') ?></label>
            <label>Supplier<input name="supplier" value="<?= $v('supplier') ?>" maxlength="120"><?= $err('supplier') ?></label>
        </div>
        <label class="check"><input type="checkbox" name="corrosion_sensitive" value="1" <?= !empty($item['corrosion_sensitive']) ? 'checked' : '' ?>> Corrosion-sensitive (metal that rusts in high heat / humidity)</label>
        <label>Notes<textarea name="notes" rows="3"><?= $v('notes') ?></textarea></label>
        <div class="form-actions">
            <button class="btn btn-teal" type="submit">Save item</button>
            <a class="btn btn-light" href="<?= site_url('inventory') ?>">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/_bottom.php'; ?>
