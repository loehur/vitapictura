-- Vita Pictura: correlate Biteship webhooks with local deliveries.

ALTER TABLE vp_order_deliveries
  ADD COLUMN biteship_order_id VARCHAR(120) NULL AFTER order_id,
  ADD KEY vp_order_deliveries_biteship_index (biteship_order_id);

INSERT IGNORE INTO vp_schema_migrations (migration, applied_at) VALUES ('011_delivery_biteship.sql', NOW());
