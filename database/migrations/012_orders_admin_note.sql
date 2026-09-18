-- Vita Pictura: order handling notes (admin cancel reason etc.).

ALTER TABLE vp_orders
  ADD COLUMN admin_note VARCHAR(500) NULL AFTER status;

INSERT IGNORE INTO vp_schema_migrations (migration, applied_at) VALUES ('012_orders_admin_note.sql', NOW());
