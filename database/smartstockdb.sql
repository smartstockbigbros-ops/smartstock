-- SmartStock hardware inventory
-- Run once in MySQL / MariaDB (phpMyAdmin "SQL" tab, MySQL Workbench, or the mysql CLI).

CREATE DATABASE IF NOT EXISTS smartstockdb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smartstockdb;

-- Accounts: local (username + password) or Google sign-in (google_id set, password NULL)
-- or both (a local account that later linked Google). See app/libraries/Auth.php.
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username    VARCHAR(100)  NOT NULL,
  email       VARCHAR(255)  NOT NULL,
  password    VARCHAR(255)  NULL,                 -- NULL for a Google-only account
  google_id   VARCHAR(64)   NULL,
  avatar      VARCHAR(255)  NULL,
  role        ENUM('admin','moderator','user') NOT NULL DEFAULT 'user',
  is_active   TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY username_unique (username),
  UNIQUE KEY email_unique (email),
  UNIQUE KEY google_id_unique (google_id),        -- MySQL allows many NULLs in a UNIQUE index
  KEY role_idx (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forgot-password tokens. Only the SHA-256 hash of the mailed token is stored,
-- so a leaked row can't be replayed. See Auth::request_password_reset()/reset_password().
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NOT NULL,
  token_hash  VARCHAR(64)   NOT NULL,
  expires_at  DATETIME      NOT NULL,
  used_at     DATETIME      NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY token_hash_unique (token_hash),
  KEY user_id_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  sku           VARCHAR(40)      NOT NULL,                      -- unique item code, e.g. FAS-001
  item_name     VARCHAR(150)     NOT NULL,                      -- e.g. "Hex Bolt M8 x 40mm"
  category      VARCHAR(60)      NOT NULL DEFAULT 'General',    -- Fasteners, Plumbing, Electrical, Tools ...
  material      VARCHAR(60)      NULL,                          -- Steel, Galvanized, Brass, Copper ...
  quantity      INT UNSIGNED     NOT NULL DEFAULT 0,            -- units on hand
  unit          VARCHAR(20)      NOT NULL DEFAULT 'pcs',        -- pcs, box, kg, m, roll ...
  reorder_level INT UNSIGNED     NOT NULL DEFAULT 10,           -- at or below this = "Low stock"
  unit_price    DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  location      VARCHAR(80)      NULL,                          -- shelf / bin, e.g. "Rack A-2"
  supplier      VARCHAR(120)     NULL,
  corrosion_sensitive TINYINT(1) NOT NULL DEFAULT 0,            -- 1 = tracked by the corrosion-risk monitor
  notes         TEXT             NULL,
  created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_sku (sku),
  KEY idx_inventory_category (category),
  KEY idx_inventory_name (item_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional starter data (remove if you want an empty table)
INSERT INTO inventory
  (sku, item_name, category, material, quantity, unit, reorder_level, unit_price, location, supplier, corrosion_sensitive)
VALUES
  ('FAS-001', 'Hex Bolt M8 x 40mm',        'Fasteners',  'Galvanized Steel', 480, 'pcs',  100,   6.50, 'Rack A-1', 'MetroFix Supply', 1),
  ('FAS-002', 'Common Wire Nails 3"',      'Fasteners',  'Steel',            35,  'kg',    40,  78.00, 'Rack A-2', 'MetroFix Supply', 1),
  ('FAS-003', 'Self-Drilling Screws #8',   'Fasteners',  'Zinc-plated Steel',1200,'pcs',  300,   1.25, 'Rack A-1', 'MetroFix Supply', 1),
  ('PLB-001', 'PVC Pipe 1/2" x 3m',        'Plumbing',   'PVC',              60,  'pcs',   20,  95.00, 'Rack B-1', 'AquaLine Trading', 0),
  ('PLB-002', 'Brass Gate Valve 3/4"',     'Plumbing',   'Brass',            8,   'pcs',   10, 320.00, 'Rack B-3', 'AquaLine Trading', 1),
  ('ELC-001', 'THHN Wire 2.0mm (150m)',    'Electrical', 'Copper',           14,  'roll',   5, 3250.00,'Rack C-1', 'BrightWire Corp.', 0),
  ('TLS-001', 'Claw Hammer 16oz',          'Tools',      'Carbon Steel',     0,   'pcs',    5, 285.00, 'Rack D-2', 'ToolHub', 1),
  ('PNT-001', 'Red Oxide Primer 4L',       'Paint',      NULL,               22,  'can',   10, 640.00, 'Rack E-1', 'ColorWorks', 0);

-- Audit trail: every +/-, edit, delete and initial-stock change to an inventory row.
-- Not a foreign key to `inventory` on purpose — a movement must survive the item
-- it refers to being deleted, so sku/item_name are copied at the time it happened.
CREATE TABLE IF NOT EXISTS inventory_movements (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  inventory_id    INT UNSIGNED  NOT NULL,
  sku             VARCHAR(40)   NOT NULL,
  item_name       VARCHAR(150)  NOT NULL,
  delta           INT           NOT NULL,              -- signed change, e.g. -3 or +50
  quantity_after  INT UNSIGNED  NOT NULL,
  reason          VARCHAR(40)   NOT NULL,               -- initial_stock | manual_adjust | edit | deleted | sale
  note            VARCHAR(255)  NULL,
  actor           VARCHAR(100)  NULL,                   -- username that made the change, if signed in
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movements_inventory_id (inventory_id),
  KEY idx_movements_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
