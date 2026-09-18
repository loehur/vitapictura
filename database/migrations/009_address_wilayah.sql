-- Vita Pictura: address hierarchy (wilayah Indonesia) for the customer address book.

ALTER TABLE vp_customer_addresses
  ADD COLUMN province_id VARCHAR(10) NULL AFTER area_name,
  ADD COLUMN province_name VARCHAR(150) NULL AFTER province_id,
  ADD COLUMN regency_id VARCHAR(10) NULL AFTER province_name,
  ADD COLUMN regency_name VARCHAR(150) NULL AFTER regency_id,
  ADD COLUMN district_id VARCHAR(10) NULL AFTER regency_name,
  ADD COLUMN district_name VARCHAR(150) NULL AFTER district_id,
  ADD COLUMN village_id VARCHAR(10) NULL AFTER district_name,
  ADD COLUMN village_name VARCHAR(150) NULL AFTER village_id;

INSERT IGNORE INTO vp_schema_migrations (migration, applied_at) VALUES ('009_address_wilayah.sql', NOW());
