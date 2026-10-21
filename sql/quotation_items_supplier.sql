-- ============================================================
-- TABLA: quotation_items_supplier
-- Fecha: 2026-10-20
-- Proposito: Nueva tabla de comparativa de proveedores por item
--            de pre-cotizacion. Solucion momentanea que reemplaza
--            quotation_prequote_item_suppliers (queda intacta).
--
-- Diferencias con la tabla anterior:
--   - origen siempre 'manual' (no hay match con catalogo de productos)
--   - La referencia del proveedor es texto libre (sin busqueda en catalogo)
--   - v_unit NO se almacena: se calcula en backend
--     (costo_base x (1 + margen_porcentaje / 100))
--
-- Relaciones:
--   quotation_items_id -> quotation_prequote_items.id (cascade)
--   supplier_id        -> persons.id (type='suppliers') [logico]
--
-- Ejecutar manualmente en cada tenant (ej: tenancy_prueba).
-- Seguro para re-ejecutar: usa IF NOT EXISTS.
-- ============================================================

CREATE TABLE IF NOT EXISTS `quotation_items_supplier` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_items_id`  BIGINT UNSIGNED NOT NULL
                          COMMENT 'FK a quotation_prequote_items.id (cascade delete)',
    `supplier_id`         BIGINT UNSIGNED NULL
                          COMMENT 'persons.id del proveedor (type=suppliers). NULL = por definir',
    `supplier_name`       VARCHAR(191) NOT NULL DEFAULT 'Proveedor por definir'
                          COMMENT 'Nombre desnormalizado del proveedor para mostrar en front',
    `origen`              VARCHAR(20)  NOT NULL DEFAULT 'manual'
                          COMMENT 'Siempre manual: no hay match con catalogo de productos',
    `product_reference`   VARCHAR(255) NULL
                          COMMENT 'Referencia cotizada por el proveedor (texto libre, sin busqueda en catalogo)',
    `costo_base`          DECIMAL(12,2) NOT NULL DEFAULT 0
                          COMMENT 'Costo base cotizado por este proveedor',
    `tiempo_estimado_dias` VARCHAR(150) NULL
                          COMMENT 'Tiempo de entrega del proveedor, texto libre, ej: 15 dias utiles',
    `observaciones`       TEXT NULL
                          COMMENT 'Observaciones sobre esta cotizacion del proveedor',
    `seleccionado`        TINYINT(1) NOT NULL DEFAULT 0
                          COMMENT '1 = proveedor elegido (define el costo base del item); 0 = respaldo',
    `user_id`             BIGINT UNSIGNED NULL
                          COMMENT 'Usuario que creo este registro',
    `created_at`          TIMESTAMP NULL,
    `updated_at`          TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `idx_qis_item` (`quotation_items_id`),
    KEY `idx_qis_supplier` (`supplier_id`),
    CONSTRAINT `fk_qis_item`
        FOREIGN KEY (`quotation_items_id`)
        REFERENCES `quotation_prequote_items` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comparativa de proveedores por item de precotizacion (solucion momentanea, origen manual)';

-- ============================================================
-- Verificacion: ejecutar para confirmar que la tabla existe
-- ============================================================
-- SELECT TABLE_NAME FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = DATABASE()
--    AND TABLE_NAME = 'quotation_items_supplier';
