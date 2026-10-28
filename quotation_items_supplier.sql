-- Tabla de cotizaciones de proveedores por ítem de pre-cotización.
-- Ejecutar en la base de datos del tenant.
CREATE TABLE IF NOT EXISTS `quotation_items_supplier` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` BIGINT UNSIGNED NULL COMMENT 'persons.id (type=suppliers)',
  `supplier_name` VARCHAR(191) NULL,
  `origen` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `product_reference` TEXT NULL,
  `costo_base` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tiempo_estimado_dias` VARCHAR(100) NULL,
  `observaciones` TEXT NULL,
  `seleccionado` TINYINT(1) NOT NULL DEFAULT 0,
  `user_id` INT UNSIGNED NULL,
  `quotation_items_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qis_quotation_item` (`quotation_items_id`),
  KEY `idx_qis_supplier` (`supplier_id`),
  KEY `idx_qis_user` (`user_id`),
  CONSTRAINT `fk_qis_quotation_item`
    FOREIGN KEY (`quotation_items_id`)
    REFERENCES `quotation_prequote_items` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
