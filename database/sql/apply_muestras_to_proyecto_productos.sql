-- ============================================================================
-- Agrega columna muestras a proyecto_productos
-- ============================================================================
-- Cantidad de muestras del producto (entero). Default 0 (hay productos que
-- no llevan muestras). Dato propio del proyecto, se edita manualmente.
--
-- Aplicar en cada BD tenant (tenancy_*). En el VPS lo aplica la migracion
-- tenant 2026_06_08_000002_add_muestras_to_proyecto_productos.php.
-- ============================================================================

ALTER TABLE `proyecto_productos`
    ADD COLUMN IF NOT EXISTS `muestras` INT NOT NULL DEFAULT 0 AFTER `cantidad`;
