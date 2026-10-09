-- Aplicar manualmente la migración 2026_05_21_000001 (Fase A — motor de costeo
-- ampliado) cuando tenancy:migrate se traba por una migración previa.
--
-- Idempotente vía INSERT IGNORE en migrations + chequeo manual de columnas
-- (correr SHOW COLUMNS antes si se quiere verificar). Las columnas usan
-- ADD COLUMN — si ya existen, MySQL dará error 1060; en ese caso ya están
-- aplicadas y se puede saltar el ALTER.

USE tenancy_compipro;   -- ⚠️ cambiar al schema del tenant correcto en Pruebas
SET NAMES utf8mb4;

-- 1) Columnas de componentes de costo en quotation_prequote_items
ALTER TABLE quotation_prequote_items
  ADD COLUMN costo_muestra        decimal(12,2) NOT NULL DEFAULT 0 AFTER costos_adicionales,
  ADD COLUMN costo_movilidad      decimal(12,2) NOT NULL DEFAULT 0 AFTER costo_muestra,
  ADD COLUMN costo_impresion      decimal(12,2) NOT NULL DEFAULT 0 AFTER costo_movilidad,
  ADD COLUMN costo_empaque        decimal(12,2) NOT NULL DEFAULT 0 AFTER costo_impresion,
  ADD COLUMN impresion_por_unidad tinyint(1)    NOT NULL DEFAULT 0 AFTER costo_empaque,
  ADD COLUMN empaque_por_unidad   tinyint(1)    NOT NULL DEFAULT 0 AFTER impresion_por_unidad;

-- 2) Config del SCTR (1.5%) — idempotente
INSERT IGNORE INTO quotation_config (config_key, config_value, config_type, description, created_at, updated_at)
VALUES ('sctr_porcentaje', '1.5', 'decimal',
        'Porcentaje SCTR aplicado sobre (Total Producto + impresión + empaque). Default 1.5%.',
        NOW(), NOW());

-- 3) Marcar la migración como aplicada
INSERT IGNORE INTO migrations (migration, batch)
SELECT '2026_05_21_000001_add_cost_components_to_quotation_prequote_items',
       COALESCE(MAX(batch), 0) + 1
FROM migrations;

-- Verificación
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'quotation_prequote_items'
  AND COLUMN_NAME IN ('costo_muestra','costo_movilidad','costo_impresion','costo_empaque','impresion_por_unidad','empaque_por_unidad')
ORDER BY ORDINAL_POSITION;
