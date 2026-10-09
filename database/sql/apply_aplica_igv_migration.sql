-- Aplicar manualmente la migración 2026_06_04_000001 (campo aplica_igv en
-- quotation_proposals) cuando tenancy:migrate se traba por una migración previa.
--
-- aplica_igv define si la Cotización Final lleva IGV y AFECTA el total mostrado:
--   true  → Total = Subtotal + IGV (TOTAL FACTURADO)
--   false → Total = Subtotal       (TOTAL Sin IGV)
-- Default true para no alterar cotizaciones existentes.
--
-- Idempotente vía INSERT IGNORE en migrations. Si la columna ya existe, el
-- ALTER dará error 1060 (ya aplicada) y se puede saltar.

USE tenancy_compipro;   -- ⚠️ cambiar al schema del tenant correcto en Pruebas
SET NAMES utf8mb4;

-- 1) Agregar la columna aplica_igv (default 1 = con IGV)
ALTER TABLE quotation_proposals
  ADD COLUMN aplica_igv tinyint(1) NOT NULL DEFAULT 1 AFTER precios_incluyen_igv;

-- 2) Marcar la migración como aplicada
INSERT IGNORE INTO migrations (migration, batch)
SELECT '2026_06_04_000001_add_aplica_igv_to_quotation_proposals', COALESCE(MAX(batch), 0) + 1
FROM migrations;

-- Verificación
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'quotation_proposals'
  AND COLUMN_NAME = 'aplica_igv';
