-- Aplicar manualmente la migración 2026_05_23_000001 (Fase B — margen mínimo
-- + alerta visual) cuando tenancy:migrate se traba por una migración previa.
--
-- Idempotente: usa INSERT IGNORE en migrations. La columna ADD COLUMN dará
-- error 1060 si ya existe; en ese caso la migración ya se aplicó y se puede
-- saltar el ALTER sin problema.

USE tenancy_compipro;   -- ⚠️ cambiar al schema del tenant correcto en Pruebas
SET NAMES utf8mb4;

-- 1) Agregar columna min_margin_percentage
ALTER TABLE quotation_margin_rules
  ADD COLUMN min_margin_percentage decimal(5,2) NULL DEFAULT NULL AFTER margin_percentage;

-- 2) Marcar la migración como aplicada
INSERT IGNORE INTO migrations (migration, batch)
SELECT '2026_05_23_000001_add_min_margin_to_quotation_margin_rules',
       COALESCE(MAX(batch), 0) + 1
FROM migrations;

-- Verificación
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'quotation_margin_rules'
  AND COLUMN_NAME = 'min_margin_percentage';
