<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * SmartStock is local-first: sensor readings, alerts and sale/cart state live
 * in the browser (localStorage, see public/assets/js/smartstock.js), so every
 * page keeps working offline once loaded. Two things the server *does*
 * supply, via window.SS (see smartstock/shell.php):
 *  - hardwareItems: which hardware is corrosion-sensitive, pulled live from
 *    the real `inventory` table (InventoryModel::corrosion_sensitive_items()),
 *    so Dashboard / Corrosion Risk reflect whatever is actually in stock.
 *  - the signed-in user's name/avatar - real server session (AuthController /
 *    app/libraries/Auth.php), not the old localStorage-only demo login.
 * Every route here sits behind the 'auth' middleware (see routes.php).
 */
class SmartStockController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('session');
        $this->call->model('InventoryModel');
    }

    private function shell($page, $title)
    {
        $this->call->view('smartstock/shell', [
            'page' => $page, 'title' => $title,
            'hardware_items' => $this->InventoryModel->corrosion_sensitive_items(),
            'username' => (string) ($this->session->userdata('username') ?? ''),
            'avatar'   => $this->session->userdata('avatar'),
        ]);
    }

    public function dashboard()  { $this->shell('dashboard',  'Corrosion Risk Dashboard'); }
    public function monitoring() { $this->shell('monitoring', 'Environmental Monitoring'); }
    public function corrosion()  { $this->shell('corrosion',  'Corrosion Risk'); }
    public function alerts()     { $this->shell('alerts',     'Alerts & Notification'); }
    public function pos()        { $this->shell('pos',        'Point of Sale'); }
}
