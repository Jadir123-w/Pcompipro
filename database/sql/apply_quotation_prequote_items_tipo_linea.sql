-- ============================================================================
-- Tipo de línea de los ítems de pre-cotización (quotation_prequote_items.tipo_linea)
-- ============================================================================
-- Equivalente a la migración tenant 2026_10_22_000002_add_tipo_linea_to_quotation_prequote_items.php
-- (en el VPS la aplica `php artisan tenancy:migrate`). Idempotente.
--
-- Valores: product, service o comision. Los ítems existentes quedan como 'product'.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- ============================================================================

ALTER TABLE `quotation_prequote_items`
    ADD COLUMN IF NOT EXISTS `tipo_linea` VARCHAR(20) NOT NULL DEFAULT 'product'
    COMMENT 'Tipo de línea: product, service o comision' AFTER `item_order`;
