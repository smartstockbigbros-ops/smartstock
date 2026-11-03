<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * InventoryModel - hardware stock kept in the `inventory` table (smartstockdb).
 * Schema: database/smartstockdb.sql
 */
class InventoryModel extends Model
{
    protected $table = 'inventory';
    protected $primary_key = 'id';
    protected $fillable = [
        'sku', 'item_name', 'category', 'material', 'quantity', 'unit',
        'reorder_level', 'unit_price', 'location', 'supplier',
        'corrosion_sensitive', 'notes', 'updated_at',
    ];
    protected $timestamps = false; // created_at defaults via MySQL; updated_at is set explicitly below
    protected $has_soft_delete = false;

    public function __construct() {
        parent::__construct();
        $this->call->database();
    }

    /**
     * Corrosion-sensitive items, shaped for the SmartStock dashboard/corrosion-risk
     * JS pages (public/assets/js/smartstock.js): {product, category, offset, status}.
     * "offset" is a rough corrosion-resistance nudge derived from the material name
     * (see material_offset()) so e.g. stainless items show lower risk than plain
     * steel for the same temperature/humidity reading — it's a display heuristic,
     * not a real engineering figure.
     */
    public function corrosion_sensitive_items() {
        $rows = $this->db->raw(
            'SELECT item_name, category, material, quantity, reorder_level
             FROM inventory WHERE corrosion_sensitive = 1 ORDER BY item_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            $qty = (int) $r['quantity'];
            $status = $qty === 0 ? 'Out of stock' : ($qty <= (int) $r['reorder_level'] ? 'Low stock' : 'On stock');
            return [
                'product'  => $r['item_name'],
                'category' => $r['category'],
                'offset'   => $this->material_offset($r['material']),
                'status'   => $status,
            ];
        }, $rows);
    }

    private function material_offset($material) {
        $m = strtolower((string) $material);
        if ($m === '') return 0;
        $has = function (...$needles) use ($m) {
            foreach ($needles as $n) if (strpos($m, $n) !== false) return true;
            return false;
        };
        if ($has('stainless', 'brass', 'copper')) return -2;
        if ($has('galvaniz', 'zinc', 'chrome')) return -1;
        if ($has('iron', 'steel')) return 1;
        return 0;
    }

    /** Items matching optional search text, category and stock status (ok|low|out). */
    public function search($q = '', $category = '', $status = '') {
        $sql = 'SELECT * FROM inventory WHERE 1=1';
        $args = [];
        if ($q !== '') {
            $sql .= ' AND (item_name LIKE ? OR sku LIKE ? OR location LIKE ? OR supplier LIKE ?)';
            $like = '%' . $q . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if ($category !== '') {
            $sql .= ' AND category = ?';
            $args[] = $category;
        }
        if ($status === 'out') {
            $sql .= ' AND quantity = 0';
        } elseif ($status === 'low') {
            $sql .= ' AND quantity > 0 AND quantity <= reorder_level';
        } elseif ($status === 'ok') {
            $sql .= ' AND quantity > reorder_level';
        }
        $sql .= ' ORDER BY item_name ASC';
        return $this->db->raw($sql, $args)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories() {
        return $this->db->raw('SELECT DISTINCT category FROM inventory ORDER BY category')
                        ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function stats() {
        $row = $this->db->raw(
            'SELECT COUNT(*) AS items,
                    COALESCE(SUM(quantity), 0) AS units,
                    COALESCE(SUM(quantity > 0 AND quantity <= reorder_level), 0) AS low,
                    COALESCE(SUM(quantity = 0), 0) AS out_of_stock,
                    COALESCE(SUM(quantity * unit_price), 0) AS value
             FROM inventory'
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['items' => 0, 'units' => 0, 'low' => 0, 'out_of_stock' => 0, 'value' => 0];
    }

    public function get_item($id) {
        return $this->find($id);
    }

    public function sku_taken($sku, $except_id = 0) {
        $row = $this->db->raw('SELECT id FROM inventory WHERE sku = ? AND id <> ? LIMIT 1', [$sku, (int) $except_id])->fetch();
        return (bool) $row;
    }

    public function create_item($data, $actor = null) {
        $id = $this->insert($data);
        if ($id && (int) ($data['quantity'] ?? 0) > 0) {
            $this->log_movement($id, $data['sku'], $data['item_name'], (int) $data['quantity'], (int) $data['quantity'], 'initial_stock', null, $actor);
        }
        return $id;
    }

    public function update_item($id, $data, $actor = null) {
        $before = $this->find($id);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $this->update($id, $data);
        if ($before && array_key_exists('quantity', $data)) {
            $delta = (int) $data['quantity'] - (int) $before['quantity'];
            if ($delta !== 0) {
                $this->log_movement($id, $data['sku'] ?? $before['sku'], $data['item_name'] ?? $before['item_name'], $delta, (int) $data['quantity'], 'edit', null, $actor);
            }
        }
        return $result;
    }

    public function delete_item($id, $actor = null) {
        $item = $this->find($id);
        if ($item) {
            $this->log_movement($id, $item['sku'], $item['item_name'], -(int) $item['quantity'], 0, 'deleted', 'Item removed from inventory', $actor);
        }
        return $this->delete($id);
    }

    /** Add (or subtract, with a negative number) stock without going below zero. Logs the change. */
    public function adjust_quantity($id, $delta, $reason = 'manual_adjust', $actor = null) {
        $before = $this->find($id);
        if (!$before) return false;
        $this->db->raw(
            'UPDATE inventory SET quantity = GREATEST(0, CAST(quantity AS SIGNED) + ?), updated_at = NOW() WHERE id = ?',
            [(int) $delta, (int) $id]
        );
        $after = $this->find($id);
        $applied = (int) $after['quantity'] - (int) $before['quantity'];
        if ($applied !== 0) {
            $this->log_movement($id, $before['sku'], $before['item_name'], $applied, (int) $after['quantity'], $reason, null, $actor);
        }
        return true;
    }

    /* ---------- audit trail (inventory_movements) ---------- */

    public function log_movement($inventory_id, $sku, $item_name, $delta, $quantity_after, $reason, $note = null, $actor = null) {
        return $this->db->raw(
            'INSERT INTO inventory_movements (inventory_id, sku, item_name, delta, quantity_after, reason, note, actor, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [(int) $inventory_id, (string) $sku, (string) $item_name, (int) $delta, (int) $quantity_after, (string) $reason, $note, $actor]
        );
    }

    /** Most recent movements for one item, newest first. */
    public function movements_for($inventory_id, $limit = 50) {
        return $this->db->raw(
            'SELECT * FROM inventory_movements WHERE inventory_id = ? ORDER BY created_at DESC, id DESC LIMIT ?',
            [(int) $inventory_id, (int) $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Most recent movements across all items, newest first (for a store-wide activity feed). */
    public function recent_movements($limit = 50) {
        return $this->db->raw(
            'SELECT * FROM inventory_movements ORDER BY created_at DESC, id DESC LIMIT ?',
            [(int) $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ---------- reorder / purchasing ---------- */

    /** Items at or below their reorder level, grouped by supplier, with a suggested order quantity. */
    public function reorder_suggestions() {
        $rows = $this->db->raw(
            'SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY supplier IS NULL, supplier ASC, item_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            // Top up to twice the reorder level (never less than the reorder level itself).
            $target = max((int) $row['reorder_level'] * 2, (int) $row['reorder_level'] + 1);
            $row['suggested_qty'] = max($target - (int) $row['quantity'], (int) $row['reorder_level']);
            $supplier = $row['supplier'] !== null && $row['supplier'] !== '' ? $row['supplier'] : 'No supplier on file';
            $grouped[$supplier][] = $row;
        }
        return $grouped;
    }

    /* ---------- CSV import / export ---------- */

    /** CSV header used by both export() and import() so a round trip just works. */
    public function csv_columns() {
        return ['sku', 'item_name', 'category', 'material', 'quantity', 'unit', 'reorder_level', 'unit_price', 'location', 'supplier', 'corrosion_sensitive', 'notes'];
    }

    /** Write the given rows (or everything, if none given) as CSV to a file handle. */
    public function export_csv($handle, $rows = null) {
        $cols = $this->csv_columns();
        fputcsv($handle, $cols);
        foreach (($rows ?? $this->search()) as $row) {
            fputcsv($handle, array_map(fn ($c) => $row[$c] ?? '', $cols));
        }
    }

    /**
     * Import rows shaped like csv_columns(). Upserts by SKU: an existing SKU is
     * updated (and the quantity delta logged), a new one is created.
     * Returns ['created' => n, 'updated' => n, 'skipped' => [[row, reason], ...]].
     */
    public function import_rows($rows, $actor = null) {
        $created = 0; $updated = 0; $skipped = [];
        foreach ($rows as $i => $r) {
            $sku = strtoupper(trim((string) ($r['sku'] ?? '')));
            $name = trim((string) ($r['item_name'] ?? ''));
            if ($sku === '' || $name === '') {
                $skipped[] = [$i + 1, 'Missing SKU or item name.'];
                continue;
            }
            $data = [
                'sku'                 => $sku,
                'item_name'           => $name,
                'category'            => trim((string) ($r['category'] ?? '')) !== '' ? trim($r['category']) : 'General',
                'material'            => trim((string) ($r['material'] ?? '')) !== '' ? trim($r['material']) : null,
                'quantity'            => max(0, (int) ($r['quantity'] ?? 0)),
                'unit'                => trim((string) ($r['unit'] ?? '')) !== '' ? trim($r['unit']) : 'pcs',
                'reorder_level'       => max(0, (int) ($r['reorder_level'] ?? 0)),
                'unit_price'          => max(0, (float) ($r['unit_price'] ?? 0)),
                'location'            => trim((string) ($r['location'] ?? '')) !== '' ? trim($r['location']) : null,
                'supplier'            => trim((string) ($r['supplier'] ?? '')) !== '' ? trim($r['supplier']) : null,
                'corrosion_sensitive' => !empty($r['corrosion_sensitive']) && $r['corrosion_sensitive'] !== '0' ? 1 : 0,
                'notes'               => trim((string) ($r['notes'] ?? '')) !== '' ? trim($r['notes']) : null,
            ];
            $existing_id = $this->db->raw('SELECT id FROM inventory WHERE sku = ? LIMIT 1', [$sku])->fetchColumn();
            if ($existing_id) {
                $this->update_item($existing_id, $data, $actor);
                $updated++;
            } else {
                $this->create_item($data, $actor);
                $created++;
            }
        }
        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
