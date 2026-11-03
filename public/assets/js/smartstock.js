/* SmartStock - local-first app logic.
 * All data (readings, alerts, sensor state) lives in localStorage on this device.
 * The server only delivers page shells; a service worker caches them for offline use. */
(function () {
  'use strict';
  var KEY = 'smartstock.v1', INTERVAL = 5 * 60 * 1000, TEMP_T = 30.0, HUM_T = 80;
  /* Prototype sensor feed: while the sensor is "Online", this script generates a new
   * temperature/humidity reading every AUTO_MS (no real hardware yet - Progress 3 / Capstone
   * swaps nextReading() for real device data). Only runs on pages that show live readings. */
  var AUTO_MS = 10 * 1000, LIVE_PAGES = { dashboard: 1, monitoring: 1, corrosion: 1, alerts: 1 };
  /* Corrosion-sensitive items come from the real `inventory` table (see
   * SmartStockController::shell() / InventoryModel::corrosion_sensitive_items()),
   * not a hardcoded list - mark an item "corrosion-sensitive" on the Inventory
   * page and it shows up here automatically. */
  var ITEMS = (window.SS && Array.isArray(SS.hardwareItems)) ? SS.hardwareItems : [];

  /* ---------- store ---------- */
  var state;
  function seedInventory() {
    function i(id, sku, name, category, material, qty, unit, reorder, price, location, supplier, corr) {
      return { id: id, sku: sku, name: name, category: category, material: material, qty: qty, unit: unit, reorder: reorder, price: price, location: location, supplier: supplier, corr: corr, notes: '' };
    }
    return [
      i(1, 'FAS-001', 'Hex Bolt M8 x 40mm', 'Fasteners', 'Galvanized Steel', 480, 'pcs', 100, 6.5, 'Rack A-1', 'MetroFix Supply', true),
      i(2, 'FAS-002', 'Common Wire Nails 3"', 'Fasteners', 'Steel', 35, 'kg', 40, 78, 'Rack A-2', 'MetroFix Supply', true),
      i(3, 'FAS-003', 'Self-Drilling Screws #8', 'Fasteners', 'Zinc-plated Steel', 1200, 'pcs', 300, 1.25, 'Rack A-1', 'MetroFix Supply', true),
      i(4, 'PLB-001', 'PVC Pipe 1/2" x 3m', 'Plumbing', 'PVC', 60, 'pcs', 20, 95, 'Rack B-1', 'AquaLine Trading', false),
      i(5, 'PLB-002', 'Brass Gate Valve 3/4"', 'Plumbing', 'Brass', 8, 'pcs', 10, 320, 'Rack B-3', 'AquaLine Trading', true),
      i(6, 'ELC-001', 'THHN Wire 2.0mm (150m)', 'Electrical', 'Copper', 14, 'roll', 5, 3250, 'Rack C-1', 'BrightWire Corp.', false),
      i(7, 'TLS-001', 'Claw Hammer 16oz', 'Tools', 'Carbon Steel', 0, 'pcs', 5, 285, 'Rack D-2', 'ToolHub', true),
      i(8, 'PNT-001', 'Red Oxide Primer 4L', 'Paint', '', 22, 'can', 10, 640, 'Rack E-1', 'ColorWorks', false)
    ];
  }
  function seed() {
    var now = Date.now(), n = 40, r = [], i, hum, temp, s = 42;
    function rnd() { s = (s * 16807) % 2147483647; return s / 2147483647; } // deterministic noise
    for (i = 0; i < n; i++) {
      hum = i < 6 ? 58 + Math.round(rnd() * 4)
        : Math.max(65, Math.min(70, Math.round(67 + 2.5 * Math.sin(i / 4 + 1) + (rnd() - .5) * 2)));
      temp = Math.max(27.4, Math.min(29.8, +(28.2 + 1.1 * Math.sin(i / 5) + (i / n) * .3 + (rnd() - .5) * .4).toFixed(1)));
      if (i === n - 1) { hum = 67; temp = 28.7; }
      r.push({ ts: now - (n - 1 - i) * INTERVAL, temp: temp, hum: hum });
    }
    return {
      online: true, readings: r, inventory: seedInventory(), sales: [],
      alerts: [
        { id: 1, type: 'success', title: 'Sensor Online', message: 'Sensor Successfully Turned On', ts: now - 900000, read: true },
        { id: 2, type: 'danger', title: 'Sensor Offline', message: 'Sensor Disconnected', ts: now - 600000, read: true },
        { id: 3, type: 'warning', title: 'High Temperature', message: 'Sensor Reached 35.4C (Threshold 30.0 C).', ts: now - 300000, read: false },
        { id: 4, type: 'warning', title: 'High Humidity', message: 'Sensor Reached 85% RH (Threshold 80%).', ts: now - 240000, read: false }
      ]
    };
  }
  function load() {
    try { var s = JSON.parse(localStorage.getItem(KEY)); if (s && s.readings && s.readings.length) { if (!s.inventory) s.inventory = seedInventory(); if (!s.sales) s.sales = []; return s; } } catch (e) {}
    return seed();
  }
  var memoryOnly = false;
  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { memoryOnly = true; }
  }
  state = load(); save();

  /* ---------- risk logic ---------- */
  function points(t, h) { return (h >= 80 ? 3 : h >= 65 ? 2 : h >= 50 ? 1 : 0) + (t >= 30 ? 2 : t >= 28 ? 1 : 0); }
  function level(p) { return p >= 5 ? 'Critical' : p >= 3 ? 'High' : p >= 2 ? 'Moderate' : 'Low'; }
  function latest() { return state.readings[state.readings.length - 1]; }
  function readingStatus(r) { return (r.temp >= TEMP_T || r.hum >= HUM_T) ? 'Critical' : points(r.temp, r.hum) >= 3 ? 'Elevated' : 'Normal'; }
  function unread() { return state.alerts.filter(function (a) { return !a.read; }).length; }
  function exposureMin() {
    var rs = state.readings, c = 0, i; for (i = rs.length - 1; i >= 0; i--) { if (points(rs[i].temp, rs[i].hum) < 2) break; c++; }
    if (!c) return 0;
    // elapsed time since the run began (+1 interval for the first reading) - correct for any reading spacing
    return Math.max(5, Math.round((rs[rs.length - 1].ts - rs[rs.length - c].ts) / 60000) + 5);
  }
  function dur(m) { return m < 60 ? m + 'm' : Math.floor(m / 60) + 'h ' + ('0' + (m % 60)).slice(-2) + 'm'; }
  function items() {
    var l = latest(), base = points(l.temp, l.hum);
    return ITEMS.map(function (it) { var p = Math.max(0, base + (it.offset || 0)); return { product: it.product, category: it.category, points: p, risk: level(p), status: it.status || 'On stock' }; })
      .sort(function (a, b) { return b.points - a.points; });
  }
  function counts() { var c = { Low: 0, Moderate: 0, High: 0, Critical: 0 }; items().forEach(function (i) { c[i.risk]++; }); return c; }
  function message() {
    var l = latest(), hot = l.temp >= 28, humid = l.hum >= 65;
    if (humid && hot) return 'High humidity and elevated temperature detected. Prolonged exposure may increase the risk of corrosion for metal hardware.';
    if (humid) return 'High humidity detected. Moisture in the air can speed up rust on unprotected metal hardware.';
    if (hot) return 'Elevated temperature detected. Heat combined with rising humidity can accelerate corrosion.';
    return 'Storage conditions are within the safe range. No corrosion risk action needed.';
  }

  /* ---------- actions ---------- */
  function pushAlert(type, title, msg, isRead) {
    state.alerts.push({ id: state.alerts.length + 1, type: type, title: title, message: msg, ts: Date.now(), read: !!isRead });
  }
  function rand(a, b) { return a + Math.floor(Math.random() * (b - a + 1)); }
  /* Script-generated reading: drifts toward a slowly oscillating target (so the dashboard moves through
   * Normal -> Elevated -> Critical over a few minutes) plus a little noise. */
  function nextReading(p) {
    var phase = Math.sin(Date.now() / 1000 / 90),
      tTarget = 28.5 + 2.2 * phase, hTarget = 69 + 14 * phase,
      temp = +Math.max(22, Math.min(38, p.temp + (tTarget - p.temp) * 0.2 + rand(-3, 3) / 10)).toFixed(1),
      hum = Math.round(Math.max(35, Math.min(95, p.hum + (hTarget - p.hum) * 0.2 + rand(-3, 3))));
    return { ts: Date.now(), temp: temp, hum: hum };
  }
  function addReading() {
    var p = latest(), r = nextReading(p);
    state.readings.push(r);
    state.readings = state.readings.slice(-200);
    // alert only when a threshold is newly crossed, so the feed doesn't spam
    if (r.hum >= HUM_T && p.hum < HUM_T) pushAlert('warning', 'High Humidity', 'Sensor Reached ' + r.hum + '% RH (Threshold ' + HUM_T + '%).');
    if (r.temp >= TEMP_T && p.temp < TEMP_T) pushAlert('warning', 'High Temperature', 'Sensor Reached ' + r.temp + 'C (Threshold ' + TEMP_T.toFixed(1) + ' C).');
    return r;
  }
  function startSensor() {
    if (!state.online) { state.online = true; pushAlert('success', 'Sensor Online', 'Sensor Successfully Turned On'); }
    var r = addReading();
    save(); render(); toast('Sensor started - new reading ' + r.temp + ' C / ' + r.hum + '% RH');
  }
  /* Automatic feed. Re-reads storage first and skips if another tab just wrote a reading, so
   * several open tabs don't multiply the readings. */
  function autoTick() {
    if (document.hidden || !LIVE_PAGES[page]) return;
    state = load();
    if (!state.online) return;
    if (Date.now() - latest().ts < AUTO_MS - 1000) return;
    addReading(); save(); render();
  }
  function stopSensor() {
    if (state.online) { state.online = false; pushAlert('danger', 'Sensor Offline', 'Sensor Disconnected', true); }
    save(); render(); toast('Sensor taken offline');
  }
  function markAll() { state.alerts.forEach(function (a) { a.read = true; }); save(); render(); toast('All notifications marked as read'); }
  function reset() { state = seed(); save(); render(); toast('Demo data reset'); }

  /* ---------- rendering ---------- */
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function ic(n, s) { return '<svg width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-' + n + '"/></svg>'; }
  function tm(ts) { return new Date(ts).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }); }
  function dt(ts) { return new Date(ts).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' · ' + tm(ts); }
  var RC = { Low: 'low', Moderate: 'mod', High: 'high', Critical: 'crit' };
  function stat(icon, label, value, extra, cls) {
    return '<div class="card stat"><div class="stat-label"><span class="chip-ic">' + ic(icon, 14) + '</span>' + label + '</div>' + value + (extra || '') + '</div>';
  }
  function val(v, unit, cls) { return '<div class="stat-value ' + (cls || '') + '">' + v + (unit ? ' <small>' + unit + '</small>' : '') + '</div>'; }

  function chart(values, id) {
    var w = 600, h = 150, pad = 8, n = values.length; if (n < 2) return '';
    var mn = Math.min.apply(null, values), mx = Math.max.apply(null, values), span = Math.max(.5, mx - mn);
    mn -= span * .35; mx += span * .15; span = mx - mn;
    var pts = values.map(function (v, i) { return [(i * w / (n - 1)).toFixed(1), (h - pad - ((v - mn) / span) * (h - 2 * pad)).toFixed(1)]; });
    var line = 'M' + pts.map(function (p) { return p.join(','); }).join(' L');
    var grid = [.25, .5, .75].map(function (g) { return '<line x1="0" y1="' + (h * g) + '" x2="' + w + '" y2="' + (h * g) + '" class="grid"/>'; }).join('');
    return '<svg class="chart" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" role="img" aria-label="' + id + ' trend"><defs><linearGradient id="g-' + id + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#17a9b8" stop-opacity=".28"/><stop offset="1" stop-color="#17a9b8" stop-opacity="0"/></linearGradient></defs>' +
      grid + '<path d="' + line + ' L' + w + ',' + h + ' L0,' + h + ' Z" fill="url(#g-' + id + ')"/><path d="' + line + '" class="line"/></svg>';
  }


  /* ---------- inventory ---------- */
  var inv = { q: '', cat: '', status: '', form: null };
  function invState(i) { return i.qty === 0 ? 'out' : i.qty <= i.reorder ? 'low' : 'ok'; }
  var INV_LABEL = { out: 'Out of stock', low: 'Low stock', ok: 'In stock' }, INV_PILL = { out: 'out', low: 'low', ok: 'normal' };
  function money(n) { return '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function invFiltered() {
    var q = inv.q.trim().toLowerCase();
    return state.inventory.filter(function (i) {
      if (inv.cat && i.category !== inv.cat) return false;
      if (inv.status && invState(i) !== inv.status) return false;
      return !q || [i.name, i.sku, i.location, i.supplier].join(' ').toLowerCase().indexOf(q) !== -1;
    }).sort(function (a, b) { return a.name.localeCompare(b.name); });
  }
  function invSave(i, d) {
    var errs = {}, n = function (v) { return /^\d{1,9}$/.test(v); };
    d.sku = d.sku.trim().toUpperCase(); d.name = d.name.trim();
    if (!d.sku) errs.sku = 'SKU is required.';
    else if (state.inventory.some(function (x) { return x.sku === d.sku && x.id !== i; })) errs.sku = 'That SKU is already used.';
    if (!d.name) errs.name = 'Item name is required.';
    if (!n(d.qty)) errs.qty = 'Whole number, 0 or more.';
    if (!n(d.reorder)) errs.reorder = 'Whole number, 0 or more.';
    if (!(parseFloat(d.price) >= 0)) errs.price = 'Price, 0 or more.';
    if (Object.keys(errs).length) return errs;
    var rec = { sku: d.sku, name: d.name, category: d.category.trim() || 'General', material: d.material.trim(), qty: +d.qty, unit: d.unit.trim() || 'pcs',
      reorder: +d.reorder, price: parseFloat(d.price), location: d.location.trim(), supplier: d.supplier.trim(), corr: !!d.corr, notes: d.notes.trim() };
    if (i) { var cur = state.inventory.filter(function (x) { return x.id === i; })[0]; Object.keys(rec).forEach(function (k) { cur[k] = rec[k]; }); }
    else { rec.id = state.inventory.reduce(function (m, x) { return Math.max(m, x.id); }, 0) + 1; state.inventory.push(rec); }
    return null;
  }
  function invField(label, name, f, opts) {
    opts = opts || {};
    return '<label>' + label + '<input name="' + name + '" value="' + esc(f.data[name]) + '"' + (opts.type ? ' type="' + opts.type + '" min="0"' + (opts.step ? ' step="' + opts.step + '"' : '') : '') +
      (opts.ph ? ' placeholder="' + opts.ph + '"' : '') + (opts.list ? ' list="invCats"' : '') + '>' + (f.errors[name] ? '<span class="field-err">' + esc(f.errors[name]) + '</span>' : '') + '</label>';
  }
  function invModal() {
    var f = inv.form; if (!f) return '';
    var cats = {}; state.inventory.forEach(function (i) { cats[i.category] = 1; });
    return '<div class="modal-back" data-inv-cancel-bg><form class="card modal inv-form" id="invForm" novalidate><h2>' + (f.id ? 'Edit hardware item' : 'Add hardware item') + '</h2><div class="grid2">' +
      invField('SKU / item code *', 'sku', f, { ph: 'FAS-001' }) + invField('Item name *', 'name', f, { ph: 'Hex Bolt M8 x 40mm' }) +
      invField('Category', 'category', f, { list: 1 }) + invField('Material', 'material', f, { ph: 'Galvanized Steel' }) +
      invField('Quantity on hand', 'qty', f, { type: 'number' }) + invField('Unit', 'unit', f, { ph: 'pcs, box, kg' }) +
      invField('Reorder level', 'reorder', f, { type: 'number' }) + invField('Unit price (₱)', 'price', f, { type: 'number', step: '0.01' }) +
      invField('Location', 'location', f, { ph: 'Rack A-2' }) + invField('Supplier', 'supplier', f) +
      '</div><datalist id="invCats">' + Object.keys(cats).map(function (c) { return '<option value="' + esc(c) + '">'; }).join('') + '</datalist>' +
      '<label class="check"><input type="checkbox" name="corr"' + (f.data.corr ? ' checked' : '') + '> Corrosion-sensitive (metal that rusts in high heat / humidity)</label>' +
      '<label>Notes<textarea name="notes" rows="2">' + esc(f.data.notes) + '</textarea></label>' +
      '<div class="form-actions"><button class="btn btn-teal" type="submit">Save item</button><button class="btn btn-light" type="button" data-inv-cancel>Cancel</button></div></form></div>';
  }
  function invView() {
    var all = state.inventory, rows = invFiltered(), low = 0, out = 0, units = 0, value = 0, cats = {};
    all.forEach(function (i) { var s = invState(i); if (s === 'low') low++; if (s === 'out') out++; units += i.qty; value += i.qty * i.price; cats[i.category] = 1; });
    var filtered = inv.q || inv.cat || inv.status;
    return '<section class="stat-grid four">' +
      stat('box', 'Item types', val(all.length)) + stat('grid', 'Units on hand', val(units.toLocaleString('en-US'), '', 'teal')) +
      stat('alert', 'Low / out of stock', val(low + ' <small>low</small> · ' + out + ' <small>out</small>', '', low + out ? 'orange' : '')) + stat('check', 'Stock value', val(money(value))) + '</section>' +
      '<section class="card"><div class="inv-toolbar"><input type="search" id="invQ" value="' + esc(inv.q) + '" placeholder="Search name, SKU, location, supplier…" aria-label="Search inventory">' +
      '<select id="invCat" aria-label="Category"><option value="">All categories</option>' + Object.keys(cats).sort().map(function (c) { return '<option' + (c === inv.cat ? ' selected' : '') + ' value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('') + '</select>' +
      '<select id="invStatus" aria-label="Stock status"><option value="">Any status</option>' + ['ok', 'low', 'out'].map(function (k) { return '<option value="' + k + '"' + (k === inv.status ? ' selected' : '') + '>' + INV_LABEL[k] + '</option>'; }).join('') + '</select>' +
      (filtered ? '<a href="#" class="link" data-inv-clear>Clear</a>' : '') +
      '<button class="btn btn-teal btn-sm inv-add" data-inv-add>' + ic('plus', 15) + ' Add item</button></div>' +
      '<table class="table"><thead><tr><th>SKU</th><th>Item</th><th>Category</th><th>Location</th><th>Qty</th><th>Status</th><th>Unit price</th><th></th></tr></thead><tbody>' +
      (rows.length ? rows.map(function (i) {
        var st = invState(i);
        return '<tr><td><b>' + esc(i.sku) + '</b></td><td>' + esc(i.name) + (i.corr ? '<span class="tag" title="Tracked by the corrosion-risk monitor">corrosion-sensitive</span>' : '') +
          (i.material || i.supplier ? '<div class="stat-sub">' + esc([i.material, i.supplier].filter(Boolean).join(' · ')) + '</div>' : '') + '</td><td>' + esc(i.category) + '</td><td>' + esc(i.location || '—') + '</td>' +
          '<td><span class="qty"><button data-inv-adj="' + i.id + '" data-delta="-1" aria-label="Remove one"' + (i.qty ? '' : ' disabled') + '>−</button><span><b>' + i.qty.toLocaleString('en-US') + '</b> <small>' + esc(i.unit) + '</small></span><button data-inv-adj="' + i.id + '" data-delta="1" aria-label="Add one">+</button></span></td>' +
          '<td><span class="pill ' + INV_PILL[st] + '">' + INV_LABEL[st] + '</span></td><td>' + money(i.price) + '</td>' +
          '<td class="row-actions"><a href="#" class="link" data-inv-edit="' + i.id + '">Edit</a><button class="linkbtn danger" data-inv-del="' + i.id + '">Delete</button></td></tr>';
      }).join('') : '<tr><td colspan="8" class="empty">' + (filtered ? 'No items match those filters.' : 'No hardware in inventory yet. Add your first item.') + '</td></tr>') +
      '</tbody></table></section>' + invModal();
  }


  /* ---------- point of sale ---------- */
  var pos = { q: '', cat: '', cart: {}, disc: '', cash: '', receipt: 0 };
  function itemById(id) { return state.inventory.filter(function (x) { return x.id === +id; })[0]; }
  function posLines() {
    return Object.keys(pos.cart).map(function (id) { var it = itemById(id); return it ? { item: it, qty: Math.min(pos.cart[id], it.qty) } : null; })
      .filter(function (l) { return l && l.qty > 0; });
  }
  function posTotals() {
    var sub = 0; posLines().forEach(function (l) { sub += l.item.price * l.qty; });
    var d = Math.min(100, Math.max(0, parseFloat(pos.disc) || 0)), off = Math.round(sub * d) / 100, total = Math.round((sub - off) * 100) / 100;
    var cash = parseFloat(pos.cash); cash = isNaN(cash) ? 0 : cash;
    return { sub: sub, d: d, off: off, total: total, cash: cash, change: Math.round((cash - total) * 100) / 100 };
  }
  function sameDay(a, b) { return new Date(a).toDateString() === new Date(b).toDateString(); }
  function posAdd(id, delta) {
    var it = itemById(id); if (!it) return;
    var q = Math.max(0, Math.min(it.qty, (pos.cart[id] || 0) + delta));
    if (q) pos.cart[id] = q; else delete pos.cart[id];
  }
  function posComplete() {
    var lines = posLines(), t = posTotals();
    if (!lines.length) return toast('Cart is empty');
    if (t.cash < t.total) return toast('Cash received is less than the total');
    var sale = { id: state.sales.reduce(function (m, x) { return Math.max(m, x.id); }, 0) + 1, ts: Date.now(), cashier: currentUser() || 'admin',
      items: lines.map(function (l) { return { sku: l.item.sku, name: l.item.name, unit: l.item.unit, qty: l.qty, price: l.item.price }; }),
      subtotal: t.sub, discount: t.d, total: t.total, cash: t.cash, change: t.change };
    lines.forEach(function (l) {
      var before = invState(l.item); l.item.qty -= l.qty; var after = invState(l.item);
      if (after !== before && after === 'out') pushAlert('danger', 'Out of Stock', l.item.name + ' (' + l.item.sku + ') is now out of stock.');
      else if (after !== before && after === 'low') pushAlert('warning', 'Low Stock', l.item.name + ' (' + l.item.sku + ') is down to ' + l.item.qty + ' ' + l.item.unit + ' (reorder level ' + l.item.reorder + ').');
    });
    state.sales.push(sale); save();
    pos.cart = {}; pos.disc = ''; pos.cash = ''; pos.receipt = sale.id;
    toast('Sale #' + pad(sale.id) + ' completed - ' + money(sale.total)); render();
  }
  function pad(n) { return ('0000' + n).slice(-4); }
  function receiptHtml(sale) {
    return '<div class="modal-back" data-pos-close-bg><div class="card modal receipt"><div class="receipt-head"><b>SmartStock</b><div>Sale receipt #' + pad(sale.id) + '</div><div class="stat-sub">' + dt(sale.ts) + ' · Cashier: ' + esc(sale.cashier) + '</div></div>' +
      '<table class="rcpt">' + sale.items.map(function (i) { return '<tr><td>' + esc(i.name) + '<div class="stat-sub">' + i.qty + ' ' + esc(i.unit) + ' × ' + money(i.price) + '</div></td><td class="r">' + money(i.qty * i.price) + '</td></tr>'; }).join('') +
      '</table><div class="rcpt-tot"><div><span>Subtotal</span><span>' + money(sale.subtotal) + '</span></div>' +
      (sale.discount ? '<div><span>Discount (' + sale.discount + '%)</span><span>−' + money(sale.subtotal - sale.total) + '</span></div>' : '') +
      '<div class="grand"><span>Total</span><span>' + money(sale.total) + '</span></div><div><span>Cash</span><span>' + money(sale.cash) + '</span></div><div><span>Change</span><span>' + money(sale.change) + '</span></div></div>' +
      '<div class="form-actions noprint"><button class="btn btn-teal" data-pos-print>Print</button><button class="btn btn-light" data-pos-close>Close</button></div></div></div>';
  }
  function posView() {
    var q = pos.q.trim().toLowerCase(), cats = {}, today = state.sales.filter(function (x) { return sameDay(x.ts, Date.now()); }), lines = posLines(), t = posTotals();
    state.inventory.forEach(function (i) { cats[i.category] = 1; });
    var shown = state.inventory.filter(function (i) { return (!pos.cat || i.category === pos.cat) && (!q || (i.name + ' ' + i.sku).toLowerCase().indexOf(q) !== -1); })
      .sort(function (a, b) { return a.name.localeCompare(b.name); });
    var revenue = today.reduce(function (a, x) { return a + x.total; }, 0), sold = today.reduce(function (a, x) { return a + x.items.reduce(function (b, i) { return b + i.qty; }, 0); }, 0);
    var sale = pos.receipt && state.sales.filter(function (x) { return x.id === pos.receipt; })[0];
    return '<section class="stat-grid three">' + stat('check', "Today's sales", val(money(revenue))) + stat('grid', 'Transactions today', val(today.length)) + stat('box', 'Items sold today', val(sold, '', 'teal')) + '</section>' +
      '<section class="pos-grid"><div class="card"><div class="inv-toolbar"><input type="search" id="posQ" value="' + esc(pos.q) + '" placeholder="Search item or SKU…" aria-label="Search items">' +
      '<select id="posCat" aria-label="Category"><option value="">All categories</option>' + Object.keys(cats).sort().map(function (c) { return '<option' + (c === pos.cat ? ' selected' : '') + ' value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('') + '</select></div>' +
      '<div class="pos-items">' + (shown.length ? shown.map(function (i) {
        var inCart = pos.cart[i.id] || 0, dis = i.qty - inCart <= 0, st = invState(i);
        return '<button class="pos-item' + (dis ? ' off' : '') + '" data-pos-add="' + i.id + '"' + (dis ? ' disabled' : '') + '><span class="pos-name">' + esc(i.name) + '</span><span class="stat-sub">' + esc(i.sku) + ' · ' + esc(i.category) + '</span>' +
          '<span class="pos-price">' + money(i.price) + ' <small>/ ' + esc(i.unit) + '</small></span><span class="pill ' + INV_PILL[st] + '">' + (i.qty ? i.qty + ' ' + esc(i.unit) + ' left' : 'Out of stock') + '</span>' + (inCart ? '<span class="pos-badge">' + inCart + '</span>' : '') + '</button>';
      }).join('') : '<div class="empty">No items match.</div>') + '</div></div>' +
      '<div class="card cart"><div class="card-title">Current sale' + (lines.length ? ' <a href="#" class="link cart-clear" data-pos-clear>Clear</a>' : '') + '</div>' +
      (lines.length ? '<ul class="cart-list">' + lines.map(function (l) {
        return '<li><div class="cart-name">' + esc(l.item.name) + '<div class="stat-sub">' + money(l.item.price) + ' each</div></div><span class="qty"><button data-pos-dec="' + l.item.id + '" aria-label="Remove one">−</button><b>' + l.qty + '</b><button data-pos-add="' + l.item.id + '" aria-label="Add one"' + (l.qty >= l.item.qty ? ' disabled' : '') + '>+</button></span>' +
          '<span class="cart-amt">' + money(l.qty * l.item.price) + '</span><button class="linkbtn danger" data-pos-rm="' + l.item.id + '" aria-label="Remove item">×</button></li>';
      }).join('') + '</ul>' : '<div class="empty">Tap an item to add it to the sale.</div>') +
      '<div class="cart-foot"><div class="row"><span>Subtotal</span><span>' + money(t.sub) + '</span></div>' +
      '<label class="row">Discount %<input id="posDisc" type="number" min="0" max="100" step="1" value="' + esc(pos.disc) + '" placeholder="0"></label>' +
      (t.off ? '<div class="row"><span>Discount</span><span>−' + money(t.off) + '</span></div>' : '') +
      '<div class="row grand"><span>Total</span><span>' + money(t.total) + '</span></div>' +
      '<label class="row">Cash received<input id="posCash" type="number" min="0" step="0.01" value="' + esc(pos.cash) + '" placeholder="0.00"></label>' +
      '<div class="row"><span>Change</span><span class="' + (lines.length && t.cash && t.change < 0 ? 'neg' : '') + '">' + (lines.length && t.cash ? (t.change < 0 ? 'Short ' + money(-t.change) : money(t.change)) : '—') + '</span></div>' +
      '<div class="cart-btns"><button class="btn btn-light btn-sm" data-pos-exact' + (lines.length ? '' : ' disabled') + '>Exact amount</button><button class="btn btn-teal pay" data-pos-pay' + (lines.length ? '' : ' disabled') + '>Complete sale</button></div></div></div></section>' +
      '<section class="card"><div class="card-title">Recent sales</div><table class="table"><thead><tr><th>Receipt</th><th>Date &amp; time</th><th>Items</th><th>Total</th><th>Cashier</th><th></th></tr></thead><tbody>' +
      (state.sales.length ? state.sales.slice(-8).reverse().map(function (x) {
        return '<tr><td><b>#' + pad(x.id) + '</b></td><td>' + dt(x.ts) + '</td><td>' + x.items.reduce(function (a, i) { return a + i.qty; }, 0) + '</td><td>' + money(x.total) + '</td><td>' + esc(x.cashier) + '</td><td class="row-actions"><a href="#" class="link" data-pos-receipt="' + x.id + '">View receipt</a></td></tr>';
      }).join('') : '<tr><td colspan="6" class="empty">No sales yet. Completed sales appear here.</td></tr>') + '</tbody></table></section>' + (sale ? receiptHtml(sale) : '');
  }

  var views = {
    inventory: invView,
    pos: posView,
    dashboard: function () {
      var l = latest(), c = counts(), it = items(), total = it.length, risk = level(points(l.temp, l.hum)),
        avg = Math.round(state.readings.slice(-12).reduce(function (a, r) { return a + r.hum; }, 0) / Math.min(12, state.readings.length)),
        colors = { Low: '#9fe3ea', Moderate: '#17a9b8', High: '#f59e0b', Critical: '#dc2626' };
      var R = 52, C = 2 * Math.PI * R, off = 0, arcs = '';
      Object.keys(c).forEach(function (k) {
        if (!c[k]) return; var len = C * c[k] / total;
        arcs += '<circle cx="70" cy="70" r="' + R + '" fill="none" stroke="' + colors[k] + '" stroke-width="12" stroke-dasharray="' + (len - 1.5).toFixed(2) + ' ' + (C - len + 1.5).toFixed(2) + '" stroke-dashoffset="' + (-off).toFixed(2) + '" transform="rotate(-90 70 70)"/>';
        off += len;
      });
      return '<section class="stat-grid four">' +
        stat('thermo', 'Temperature', val(l.temp.toFixed(1), 'C')) +
        stat('drop', 'Avg. Humidity', val(avg + '%', 'RH')) +
        stat('box', 'High Risk Items', val(c.High + c.Critical, '', 'orange'), '<div class="stat-sub">of ' + total + ' items</div>') +
        stat('bell', 'Active Alerts', val(unread(), '', 'red'), '<div class="stat-sub">needs attention</div>') +
        '</section><section class="dash-grid"><div class="col">' +
        '<div class="card"><table class="table compact"><thead><tr><th>Temperature</th><th>Humidity</th><th>Exposure Duration</th><th>Risk Level</th><th>Status</th></tr></thead><tbody><tr><td>' +
        l.temp.toFixed(1) + ' C</td><td>' + l.hum + '%</td><td>' + dur(exposureMin()) + '</td><td><strong class="risk-text ' + RC[risk] + '">' + risk + '</strong></td><td><strong>' + (state.online ? 'Online' : 'Offline') + '</strong></td></tr></tbody></table></div>' +
        '<div class="card grow"><div class="card-title">Top Affected Metal Hardware</div><table class="table"><thead><tr><th>Product</th><th>Category</th><th>Risk Level</th><th>Status</th></tr></thead><tbody>' +
        (it.length ? it.slice(0, 3).map(function (i) { return '<tr><td>' + esc(i.product) + '</td><td>' + i.category + '</td><td><span class="risk-text ' + RC[i.risk] + '">' + i.risk + '</span></td><td>' + i.status + '</td></tr>'; }).join('')
          : '<tr><td colspan="4" class="empty">No corrosion-sensitive items in inventory yet. <a class="link" href="' + SS.inventoryUrl + '">Mark some on the Inventory page.</a></td></tr>') +
        '</tbody></table></div></div><div class="col"><div class="card grow donut-card"><svg class="donut" viewBox="0 0 140 140" role="img" aria-label="Items by risk level"><circle cx="70" cy="70" r="' + R + '" fill="none" stroke="#eef2f5" stroke-width="12"/>' + arcs +
        '<text x="70" y="68" text-anchor="middle" class="donut-n">' + total + '</text><text x="70" y="84" text-anchor="middle" class="donut-l">items</text></svg><ul class="legend">' +
        Object.keys(c).map(function (k) { return '<li><i style="background:' + colors[k] + '"></i><span>' + k + '</span><b>' + c[k] + '</b></li>'; }).join('') +
        '</ul></div><div class="card last-update"><div class="stat-label tiny">Last Sensor Update</div><div class="update-time">' + ic('clock', 15) + ' ' + tm(l.ts) + ' · ' + new Date(l.ts).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + '</div></div></div></section>';
    },
    monitoring: function () {
      var l = latest(), s = state.readings.slice(-12), rec = state.readings.slice(-5).reverse(),
        pc = { Normal: 'normal', Elevated: 'elevated', Critical: 'critical' };
      return '<section class="stat-grid three">' +
        stat('thermo', 'Temperature', val(l.temp.toFixed(1), 'C')) +
        stat('drop', 'Humidity', val(l.hum + '%', 'RH')) +
        stat('wifi', 'Sensor Status', '<div class="status-line ' + (state.online ? 'on' : 'off') + '"><i></i>' + (state.online ? 'Online' : 'Offline') + '</div><div class="stat-sub">Last Update: ' + tm(l.ts).toLowerCase() + ' · <a href="#" class="link" data-' + (state.online ? 'stop' : 'start') + '-sensor>' + (state.online ? 'Simulate offline' : 'Bring online') + '</a></div>') +
        '</section><section class="chart-grid"><div class="card"><div class="card-title upper">Temperature (C)</div>' + chart(s.map(function (r) { return r.temp; }), 'temp') +
        '</div><div class="card"><div class="card-title upper">Humidity (% RH)</div>' + chart(s.map(function (r) { return r.hum; }), 'hum') + '</div></section>' +
        '<section class="card grow"><div class="card-title">Recent Readings</div><table class="table"><thead><tr><th>Date &amp; Time</th><th>Temperature</th><th>Humidity</th><th>Status</th></tr></thead><tbody>' +
        rec.map(function (r) { var st = readingStatus(r); return '<tr><td>' + dt(r.ts) + '</td><td>' + r.temp.toFixed(1) + ' C</td><td>' + r.hum + '% RH</td><td><span class="pill ' + pc[st] + '">' + st + '</span></td></tr>'; }).join('') +
        '</tbody></table></section>';
    },
    corrosion: function () {
      var l = latest(), c = counts(), risk = level(points(l.temp, l.hum)), hi = c.High + c.Critical, total = c.Low + c.Moderate + hi;
      return '<section class="risk-row"><div class="card risk-card"><div class="stat-label tiny upper">Corrosion Risk Level</div><div class="risk-badge ' + RC[risk] + '">' + ic('alert', 26) + '<span>' + risk.toUpperCase() + '</span></div></div>' +
        '<button class="card start-card" data-start-sensor><span class="play">' + ic('play', 14) + '</span><span>START SENSOR MANUALLY</span></button></section>' +
        '<section class="stat-grid four">' + stat('thermo', 'Temperature', val(l.temp.toFixed(1), 'C')) + stat('drop', 'Humidity', val(l.hum + '%', 'RH')) +
        stat('clock', 'Exposure Duration', val(dur(exposureMin()), '', 'orange'), '<div class="stat-sub">affecting ' + hi + ' of ' + total + ' items</div>') +
        stat('wifi', 'Sensor Status', val(state.online ? 'Online' : 'Offline', '', 'teal sm'), '<div class="stat-sub">' + (state.online ? 'needs attention' : 'no new readings') + '</div>') + '</section>' +
        '<section class="card"><div class="card-head">Environment Condition</div><div class="condition"><span class="warn-dot">!</span><p>' + esc(message()) + '</p></div>' +
        (total === 0 ? '<p class="stat-sub">No items are marked corrosion-sensitive in inventory yet, so nothing is being tracked here. <a class="link" href="' + SS.inventoryUrl + '">Mark some on the Inventory page.</a></p>' : '') +
        '</section>' +
        '<a class="btn btn-dark float-btn" href="' + SS.dashboardUrl + '">Go back to Dashboard</a>';
    },
    alerts: function () {
      var ico = { warning: 'alert', danger: 'xcircle', success: 'check' };
      return '<section class="card alerts-card"><div class="alerts-head"><button class="btn btn-teal btn-sm" id="markAll"' + (unread() ? '' : ' disabled') + '>Mark all as Read</button></div><ul class="alert-list">' +
        state.alerts.slice().reverse().map(function (a) {
          return '<li class="alert-row"><span class="alert-ic ' + a.type + '">' + ic(ico[a.type], 18) + '</span><div class="alert-body"><div class="alert-title">' + esc(a.title) + '</div><div class="alert-msg">' + esc(a.message) +
            '</div></div><time>' + tm(a.ts) + '</time><span class="read-pill ' + (a.read ? 'read' : 'unread') + '">' + (a.read ? 'Read' : 'Unread') + '</span></li>';
        }).join('') + '</ul><div class="end-note">--- End of Notification ---</div></section>';
    }
  };

  /* Signed-in user now comes from the real server session (window.SS.username,
   * set by SmartStockController -> AuthController/Auth.php), not localStorage. */
  function currentUser() { return (window.SS && SS.username) ? SS.username : null; }
  function logout() { location.href = SS.logoutUrl; }

  var view = document.getElementById('view'), page = view.dataset.page;
  function render() {
    view.innerHTML = views[page]();
    document.getElementById('bellDot').hidden = unread() === 0;
    var note = document.getElementById('netText');
    if (note) note.textContent = memoryOnly ? 'Storage blocked - not saved' : (navigator.onLine ? 'Saved on this device' : 'Offline - saved on this device');
    var who = currentUser();
    if (who) { document.getElementById('userName').textContent = who.toUpperCase(); document.getElementById('userFull').textContent = who; document.getElementById('userAvatar').textContent = who.charAt(0).toUpperCase(); }
    var nd = document.getElementById('netDot'); if (nd) nd.className = navigator.onLine ? '' : 'off';
  }

  var toastEl = document.getElementById('toast'), tt;
  function toast(msg) { toastEl.textContent = msg; toastEl.classList.add('show'); clearTimeout(tt); tt = setTimeout(function () { toastEl.classList.remove('show'); }, 2600); }

  /* one delegated listener - survives re-renders */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-start-sensor],[data-stop-sensor],#markAll,#resetData,[data-logout]');
    if (!t || t.disabled) return;
    e.preventDefault();
    if (t.hasAttribute('data-start-sensor')) startSensor();
    else if (t.hasAttribute('data-stop-sensor')) stopSensor();
    else if (t.id === 'markAll') markAll();
    else if (t.id === 'resetData') reset();
    else if (t.hasAttribute('data-logout')) logout();
  });

  /* user menu */
  function menu(open) { var m = document.getElementById('userMenu'), b = document.getElementById('userBtn'); m.hidden = !open; b.setAttribute('aria-expanded', open); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('#userBtn');
    if (b) menu(document.getElementById('userMenu').hidden); else if (!e.target.closest('#userMenu')) menu(false);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menu(false); });

  /* inventory events */
  function formData(f) {
    var d = {}; ['sku', 'name', 'category', 'material', 'qty', 'unit', 'reorder', 'price', 'location', 'supplier', 'notes'].forEach(function (k) { d[k] = f.elements[k].value; });
    d.corr = f.elements.corr.checked; return d;
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-inv-add],[data-inv-edit],[data-inv-del],[data-inv-adj],[data-inv-cancel],[data-inv-clear]');
    if (e.target.hasAttribute && e.target.hasAttribute('data-inv-cancel-bg')) { inv.form = null; render(); return; }
    if (!t || t.disabled) return;
    e.preventDefault();
    var id = +(t.getAttribute('data-inv-edit') || t.getAttribute('data-inv-del') || t.getAttribute('data-inv-adj')),
      item = state.inventory.filter(function (x) { return x.id === id; })[0];
    if (t.hasAttribute('data-inv-add')) inv.form = { id: 0, errors: {}, data: { sku: '', name: '', category: 'General', material: '', qty: '0', unit: 'pcs', reorder: '10', price: '0.00', location: '', supplier: '', notes: '', corr: false } };
    else if (t.hasAttribute('data-inv-edit') && item) inv.form = { id: id, errors: {}, data: { sku: item.sku, name: item.name, category: item.category, material: item.material, qty: String(item.qty), unit: item.unit, reorder: String(item.reorder), price: String(item.price), location: item.location, supplier: item.supplier, notes: item.notes, corr: item.corr } };
    else if (t.hasAttribute('data-inv-cancel')) inv.form = null;
    else if (t.hasAttribute('data-inv-clear')) { inv.q = inv.cat = inv.status = ''; }
    else if (t.hasAttribute('data-inv-adj') && item) { item.qty = Math.max(0, item.qty + (+t.getAttribute('data-delta'))); save(); }
    else if (t.hasAttribute('data-inv-del') && item) {
      if (!confirm('Delete ' + item.name + '?')) return;
      state.inventory = state.inventory.filter(function (x) { return x.id !== id; }); save(); toast('Deleted "' + item.name + '"');
    }
    render();
  });
  document.addEventListener('submit', function (e) {
    if (e.target.id !== 'invForm') return;
    e.preventDefault();
    var d = formData(e.target), errs = invSave(inv.form.id, d), wasNew = !inv.form.id;
    if (errs) { inv.form.data = d; inv.form.errors = errs; render(); return; }
    save(); toast(wasNew ? 'Added "' + d.name.trim() + '"' : 'Updated "' + d.name.trim() + '"'); inv.form = null; render();
  });
  document.addEventListener('input', function (e) {
    if (e.target.id !== 'invQ') return;
    inv.q = e.target.value; var pos = e.target.selectionStart; render();
    var q = document.getElementById('invQ'); q.focus(); q.setSelectionRange(pos, pos);
  });
  document.addEventListener('change', function (e) {
    if (e.target.id === 'invCat') { inv.cat = e.target.value; render(); }
    else if (e.target.id === 'invStatus') { inv.status = e.target.value; render(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && inv.form) { inv.form = null; render(); } });

  /* point of sale events */
  document.addEventListener('click', function (e) {
    if (e.target.hasAttribute && e.target.hasAttribute('data-pos-close-bg')) { pos.receipt = 0; render(); return; }
    var t = e.target.closest('[data-pos-add],[data-pos-dec],[data-pos-rm],[data-pos-clear],[data-pos-exact],[data-pos-pay],[data-pos-receipt],[data-pos-close],[data-pos-print]');
    if (!t || t.disabled) return;
    e.preventDefault();
    var g = function (k) { return t.getAttribute(k); };
    if (t.hasAttribute('data-pos-add')) posAdd(g('data-pos-add'), 1);
    else if (t.hasAttribute('data-pos-dec')) posAdd(g('data-pos-dec'), -1);
    else if (t.hasAttribute('data-pos-rm')) delete pos.cart[g('data-pos-rm')];
    else if (t.hasAttribute('data-pos-clear')) pos.cart = {};
    else if (t.hasAttribute('data-pos-exact')) pos.cash = posTotals().total.toFixed(2);
    else if (t.hasAttribute('data-pos-pay')) return posComplete();
    else if (t.hasAttribute('data-pos-receipt')) pos.receipt = +g('data-pos-receipt');
    else if (t.hasAttribute('data-pos-close')) pos.receipt = 0;
    else if (t.hasAttribute('data-pos-print')) return window.print();
    render();
  });
  document.addEventListener('input', function (e) {
    var map = { posQ: 'q', posDisc: 'disc', posCash: 'cash' }, k = map[e.target.id];
    if (!k) return;
    pos[k] = e.target.value; var id = e.target.id, pos0 = e.target.selectionStart; render();
    var el = document.getElementById(id); el.focus(); try { el.setSelectionRange(pos0, pos0); } catch (x) {}
  });
  document.addEventListener('change', function (e) { if (e.target.id === 'posCat') { pos.cat = e.target.value; render(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pos.receipt) { pos.receipt = 0; render(); } });

  /* keep tabs in sync, reflect connectivity */
  window.addEventListener('storage', function (e) { if (e.key === KEY) { state = load(); render(); } });
  window.addEventListener('online', render); window.addEventListener('offline', render);

  render();
  setInterval(autoTick, AUTO_MS);

  /* offline support */
  if ('serviceWorker' in navigator) {
    var q = '?u=' + encodeURIComponent(JSON.stringify(SS.pages.concat(SS.assets)));
    navigator.serviceWorker.register(SS.sw + q, { scope: SS.scope }).catch(function () {});
  }
})();
