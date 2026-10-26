-- ============================================================================
-- Proveedores de respaldo del producto del proyecto (proyecto_producto_proveedores)
-- ============================================================================
-- Equivalente a la migración tenant 2026_10_24_000001_add_respaldo_to_proyecto_producto_proveedores.php
-- (en el VPS la aplica `php artisan tenancy:migrate`). Idempotente.
--
-- Los demás proveedores cotizados en la comparativa pasan al proyecto como respaldo:
-- no suman al costo y se pueden usar en lugar del proveedor que respaldan.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- ============================================================================

ALTER TABLE `proyecto_producto_proveedores`
    ADD COLUMN IF NOT EXISTS `es_respaldo` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = proveedor de respaldo de la comparativa (no suma al costo)' AFTER `activo`,
    ADD COLUMN IF NOT EXISTS `respalda_a_id` BIGINT UNSIGNED NULL
        COMMENT 'Proveedor del producto al que reemplazaría este respaldo' AFTER `es_respaldo`,
    ADD COLUMN IF NOT EXISTS `cotizado_el` DATE NULL
        COMMENT 'Fecha en que el proveedor cotizó (comparativa)' AFTER `respalda_a_id`;
