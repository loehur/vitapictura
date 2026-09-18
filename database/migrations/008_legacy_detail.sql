-- Vita Pictura: legacy ABFLab product detail parity.
-- Adds nested variant support, per-variant image keys, gallery media keys,
-- description tabs, and file-delivery/template metadata.

ALTER TABLE vp_products
  ADD COLUMN legacy_img_detail VARCHAR(120) NULL AFTER legacy_product_id,
  ADD COLUMN perlu_file TINYINT(1) NOT NULL DEFAULT 0 AFTER short_description,
  ADD COLUMN mal_json JSON NULL AFTER perlu_file;

ALTER TABLE vp_product_option_groups
  ADD COLUMN legacy_vg_id BIGINT UNSIGNED NULL AFTER product_id,
  ADD COLUMN group_level TINYINT NOT NULL DEFAULT 1 AFTER name,
  ADD COLUMN parent_group_id BIGINT UNSIGNED NULL AFTER group_level,
  ADD KEY vp_option_groups_legacy_index (product_id, legacy_vg_id),
  ADD CONSTRAINT vp_option_groups_parent_fk FOREIGN KEY (parent_group_id) REFERENCES vp_product_option_groups (id) ON DELETE CASCADE;

ALTER TABLE vp_product_option_values
  ADD COLUMN legacy_varian_id BIGINT UNSIGNED NULL AFTER option_group_id,
  ADD COLUMN image_suffix VARCHAR(80) NULL AFTER name,
  ADD COLUMN parent_value_id BIGINT UNSIGNED NULL AFTER image_suffix,
  ADD KEY vp_option_values_legacy_index (legacy_varian_id),
  ADD CONSTRAINT vp_option_values_parent_fk FOREIGN KEY (parent_value_id) REFERENCES vp_product_option_values (id) ON DELETE CASCADE;

ALTER TABLE vp_product_media
  ADD COLUMN image_key VARCHAR(120) NULL AFTER url,
  ADD COLUMN image_suffix VARCHAR(80) NULL AFTER image_key,
  ADD KEY vp_product_media_key_index (product_id, image_key);

CREATE TABLE IF NOT EXISTS vp_product_detail_tabs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  content_key VARCHAR(120) NULL,
  content_html MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY vp_product_detail_tabs_listing (product_id, sort_order),
  CONSTRAINT vp_product_detail_tabs_product_fk FOREIGN KEY (product_id) REFERENCES vp_products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vp_schema_migrations (migration, applied_at) VALUES ('008_legacy_detail.sql', NOW());
