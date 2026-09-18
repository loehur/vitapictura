-- Vita Pictura: key/value application settings (editable from the admin panel).

CREATE TABLE IF NOT EXISTS vp_settings (
  name VARCHAR(80) NOT NULL,
  value TEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vp_settings (name, value, updated_at) VALUES
  ('store_name', 'Vita Pictura', NOW()),
  ('wa_number', '6285210692884', NOW()),
  ('origin_name', 'VITA PICTURA / ASIA BARU FOTO', NOW()),
  ('origin_contact_phone', '08117677494', NOW()),
  ('origin_address', 'Jl. Jend. Sudirman No. 331, Pekanbaru', NOW()),
  ('postal_code', '28111', NOW()),
  ('couriers', 'gojek,grab,jne,jnt,pos,anteraja,sicepat', NOW());

INSERT IGNORE INTO vp_schema_migrations (migration, applied_at) VALUES ('010_settings.sql', NOW());
