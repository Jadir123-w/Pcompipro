-- ============================================================================
-- Comparativa de proveedores por ítem de pre-cotización (quotation_items_supplier)
-- ============================================================================
-- Equivalente a la migración tenant 2026_10_22_000001_create_quotation_items_supplier_table.php
-- (en el VPS la aplica `php artisan tenancy:migrate`). Idempotente: no toca la tabla si ya existe.
--
-- Cada fila es la cotización de un proveedor para un ítem; la seleccionada define el
-- costo base del ítem.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `quotation_items_supplier` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_items_id`   BIGINT UNSIGNED NOT NULL COMMENT 'FK a quotation_prequote_items.id (cascade delete)',
    `supplier_id`          BIGINT UNSIGNED NULL COMMENT 'persons.id del proveedor (type=suppliers). NULL = por definir',
    `supplier_name`        VARCHAR(191) NOT NULL DEFAULT 'Proveedor por definir' COMMENT 'Nombre desnormalizado del proveedor para mostrar en pantalla',
    `origen`               VARCHAR(20) NOT NULL DEFAULT 'manual',
    `product_reference`    VARCHAR(255) NULL COMMENT 'Referencia cotizada por el proveedor (texto libre)',
    `costo_base`           DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Costo base cotizado por este proveedor',
    `tiempo_estimado_dias` VARCHAR(150) NULL COMMENT 'Tiempo de entrega, texto libre (ej. 15 días útiles)',
    `observaciones`        TEXT NULL,
    `seleccionado`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = proveedor elegido (define el costo base del ítem); 0 = respaldo',
    `user_id`              BIGINT UNSIGNED NULL COMMENT 'Usuario que creó el registro',
    `created_at`           TIMESTAMP NULL,
    `updated_at`           TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `idx_qis_item` (`quotation_items_id`),
    KEY `idx_qis_supplier` (`supplier_id`),
    CONSTRAINT `fk_qis_item` FOREIGN KEY (`quotation_items_id`)
        REFERENCES `quotation_prequote_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
