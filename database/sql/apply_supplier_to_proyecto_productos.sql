-- ============================================================================
-- Agrega columnas de proveedor a proyecto_productos
-- ============================================================================
-- Snapshot del proveedor heredado de la pre-cotización al convertir un
-- requerimiento en proyecto. Idempotente (MariaDB soporta IF NOT EXISTS).
--
-- Aplicar en cada BD tenant (tenancy_*). En el VPS lo aplica la migración
-- tenant 2026_06_08_000001_add_supplier_to_proyecto_productos.php.
-- ============================================================================

ALTER TABLE `proyecto_productos`
    ADD COLUMN IF NOT EXISTS `supplier_id` BIGINT UNSIGNED NULL AFTER `item_id`,
    ADD COLUMN IF NOT EXISTS `supplier_name` VARCHAR(255) NULL AFTER `supplier_id`;
