<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class InventoryController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->call->library('session');
        $this->call->model('InventoryModel');
    }

    public function index() {
        $q        = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $status   = (string) ($_GET['status'] ?? '');
        if (!in_array($status, ['ok', 'low', 'out'], true)) $status = '';

        $this->call->view('inventory/index', [
            'page'       => 'inventory',
            'title'      => 'Hardware Inventory',
            'items'      => $this->InventoryModel->search($q, $category, $status),
            'categories' => $this->InventoryModel->categories(),
            'stats'      => $this->InventoryModel->stats(),
            'q' => $q, 'category' => $category, 'status' => $status,
            'flash'      => $this->session->flashdata('inventory_msg'),
        ]);
    }

    public function create() {
        $item = $this->blank();
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $item = $this->collect();
            $errors = $this->validate($item, 0);
            if (!$errors) {
                $this->InventoryModel->create_item($item, $this->actor());
                $this->session->set_flashdata('inventory_msg', 'Added "' . $item['item_name'] . '" to inventory.');
                redirect('inventory');
            }
        }
        $this->form('Add Hardware Item', 'inventory/create', $item, $errors, $this->InventoryModel->categories());
    }

    public function edit($id) {
        $existing = $this->InventoryModel->get_item($id);
        if (!$existing) {
            $this->session->set_flashdata('inventory_msg', 'That item no longer exists.');
            redirect('inventory');
        }
        $item = $existing;
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $item = array_merge($existing, $this->collect());
            $errors = $this->validate($item, (int) $id);
            if (!$errors) {
                $this->InventoryModel->update_item($id, $this->collect(), $this->actor());
                $this->session->set_flashdata('inventory_msg', 'Updated "' . $item['item_name'] . '".');
                redirect('inventory');
            }
        }
        $this->form('Edit Hardware Item', 'inventory/edit/' . (int) $id, $item, $errors, $this->InventoryModel->categories());
    }

    /** POST only: quick +/- stock adjustment from the list. */
    public function adjust($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $delta = (int) $this->post('delta');
            if ($delta !== 0 && abs($delta) <= 100000) {
                $this->InventoryModel->adjust_quantity($id, $delta, 'manual_adjust', $this->actor());
            }
        }
        redirect('inventory' . $this->back_query());
    }

    /** POST only: deleting through a plain link would let crawlers/prefetch wipe stock. */
    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $item = $this->InventoryModel->get_item($id);
            if ($item) {
                $this->InventoryModel->delete_item($id, $this->actor());
                $this->session->set_flashdata('inventory_msg', 'Deleted "' . $item['item_name'] . '".');
            }
        }
        redirect('inventory');
    }

    /** Audit trail for a single item: every +/- adjustment, edit and the initial stock entry. */
    public function movements($id) {
        $item = $this->InventoryModel->get_item($id);
        if (!$item) {
            $this->session->set_flashdata('inventory_msg', 'That item no longer exists.');
            redirect('inventory');
        }
        $this->call->view('inventory/movements', [
            'page' => 'inventory', 'title' => 'Stock History · ' . $item['item_name'],
            'item' => $item, 'movements' => $this->InventoryModel->movements_for($id, 100),
        ]);
    }

    /** Items at/below their reorder level, grouped by supplier, with suggested order quantities. */
    public function reorder() {
        $this->call->view('inventory/reorder', [
            'page' => 'inventory', 'title' => 'Reorder List',
            'grouped' => $this->InventoryModel->reorder_suggestions(),
        ]);
    }

    /** Download the full inventory (or the current filtered list) as CSV. */
    public function export() {
        $q        = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $status   = (string) ($_GET['status'] ?? '');
        if (!in_array($status, ['ok', 'low', 'out'], true)) $status = '';
        $rows = ($q !== '' || $category !== '' || $status !== '')
            ? $this->InventoryModel->search($q, $category, $status)
            : null; // null -> export_csv() fetches everything itself

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        $this->InventoryModel->export_csv($out, $rows);
        fclose($out);
        exit;
    }

    /** Bulk update/create items from an uploaded CSV shaped like export()'s output. */
    public function import() {
        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $rows = $this->read_csv($_FILES['csv']['tmp_name']);
            if ($rows === null) {
                $result = ['error' => 'Could not read that file as CSV, or the header row did not match (expected: ' . implode(', ', $this->InventoryModel->csv_columns()) . ').'];
            } else {
                $result = $this->InventoryModel->import_rows($rows, $this->actor());
                $this->session->set_flashdata('inventory_msg',
                    "Import complete: {$result['created']} added, {$result['updated']} updated" .
                    (count($result['skipped']) ? ', ' . count($result['skipped']) . ' row(s) skipped.' : '.'));
            }
        }
        if ($result && empty($result['error'])) redirect('inventory');
        $this->call->view('inventory/import', [
            'page' => 'inventory', 'title' => 'Import Inventory (CSV)',
            'columns' => $this->InventoryModel->csv_columns(), 'result' => $result,
        ]);
    }

    /* ---------- helpers ---------- */

    /** Logged-in username for the audit trail, or null if nobody is signed in. */
    private function actor() {
        $u = $this->session->userdata('username');
        return is_string($u) && $u !== '' ? $u : null;
    }

    /** Parse an uploaded CSV into associative rows keyed by csv_columns(); null if the header doesn't match. */
    private function read_csv($path) {
        $expected = $this->InventoryModel->csv_columns();
        $fh = fopen($path, 'r');
        if (!$fh) return null;
        $header = fgetcsv($fh);
        if (!$header || array_map('strtolower', array_map('trim', $header)) !== $expected) {
            fclose($fh);
            return null;
        }
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if ($line === [null] || $line === false) continue;
            $rows[] = array_combine($expected, array_pad(array_slice($line, 0, count($expected)), count($expected), ''));
        }
        fclose($fh);
        return $rows;
    }

    /** Safe POST read: unchecked checkboxes and absent fields come back as ''. */
    private function post($key) {
        $v = $_POST[$key] ?? '';
        return is_scalar($v) ? (string) $v : '';
    }

    private function back_query() {
        $keep = array_filter([
            'q'        => trim($this->post('q')),
            'category' => trim($this->post('category')),
            'status'   => trim($this->post('status')),
        ], 'strlen');
        return $keep ? '?' . http_build_query($keep) : '';
    }

    private function form($title, $action, $item, $errors, $categories) {
        $this->call->view('inventory/form', [
            'page' => 'inventory', 'title' => $title, 'action' => $action,
            'item' => $item, 'errors' => $errors, 'categories' => $categories,
        ]);
    }

    private function blank() {
        return [
            'sku' => '', 'item_name' => '', 'category' => 'General', 'material' => '',
            'quantity' => 0, 'unit' => 'pcs', 'reorder_level' => 10, 'unit_price' => '0.00',
            'location' => '', 'supplier' => '', 'corrosion_sensitive' => 0, 'notes' => '',
        ];
    }

    /** Raw values; output is escaped in the views, queries are parameterised. */
    private function collect() {
        $t = function ($k) { return trim($this->post($k)); };
        return [
            'sku'                 => strtoupper($t('sku')),
            'item_name'           => $t('item_name'),
            'category'            => $t('category') !== '' ? $t('category') : 'General',
            'material'            => $t('material') !== '' ? $t('material') : null,
            'quantity'            => $t('quantity') === '' ? 0 : $t('quantity'),
            'unit'                => $t('unit') !== '' ? $t('unit') : 'pcs',
            'reorder_level'       => $t('reorder_level') === '' ? 0 : $t('reorder_level'),
            'unit_price'          => $t('unit_price') === '' ? 0 : $t('unit_price'),
            'location'            => $t('location') !== '' ? $t('location') : null,
            'supplier'            => $t('supplier') !== '' ? $t('supplier') : null,
            'corrosion_sensitive' => $this->post('corrosion_sensitive') !== '' ? 1 : 0,
            'notes'               => $t('notes') !== '' ? $t('notes') : null,
        ];
    }

    private function validate(&$item, $id) {
        $e = [];
        if ($item['sku'] === '' || strlen($item['sku']) > 40) $e['sku'] = 'SKU is required (max 40 characters).';
        elseif ($this->InventoryModel->sku_taken($item['sku'], $id)) $e['sku'] = 'That SKU is already used by another item.';
        if ($item['item_name'] === '' || strlen($item['item_name']) > 150) $e['item_name'] = 'Item name is required (max 150 characters).';
        foreach (['category' => 60, 'material' => 60, 'unit' => 20, 'location' => 80, 'supplier' => 120] as $f => $max) {
            if ($item[$f] !== null && strlen($item[$f]) > $max) $e[$f] = "Max $max characters.";
        }
        foreach (['quantity', 'reorder_level'] as $f) {
            if (!preg_match('/^\d{1,9}$/', (string) $item[$f])) $e[$f] = 'Enter a whole number, 0 or more.';
        }
        if (!is_numeric($item['unit_price']) || $item['unit_price'] < 0 || $item['unit_price'] > 99999999) $e['unit_price'] = 'Enter a price, 0 or more.';
        return $e;
    }
}
