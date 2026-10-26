-- ============================================================================
-- Producto ya cotizado en otro requerimiento (quotation_prequote_items.cotizacion_anterior)
-- ============================================================================
-- Equivalente a la migración tenant 2026_10_23_000001_add_cotizacion_anterior_to_quotation_prequote_items.php
-- (en el VPS la aplica `php artisan tenancy:migrate`). Idempotente.
--
-- JSON con el requerimiento, la pre-cotización, la fecha y la similitud de la cotización
-- anterior que se usó como referencia. NULL = no se encontró.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- ============================================================================

ALTER TABLE `quotation_prequote_items`
    ADD COLUMN IF NOT EXISTS `cotizacion_anterior` TEXT NULL
    COMMENT 'JSON: cotización anterior del mismo producto usada como referencia';
