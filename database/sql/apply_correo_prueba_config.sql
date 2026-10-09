-- Aplicar manualmente la migración 2026_05_26_000001 (seed correo_prueba_interno)
-- cuando tenancy:migrate se traba por una migración previa.
--
-- Idempotente: solo inserta la config si no existe. Marca la migración como
-- aplicada vía INSERT IGNORE.

USE tenancy_compipro;   -- ⚠️ cambiar al schema del tenant correcto en Pruebas
SET NAMES utf8mb4;

-- 1) Sembrar la clave de config (vacía — se configura desde la UI)
INSERT INTO quotation_config (config_key, config_value, config_type, description, created_at, updated_at)
SELECT 'correo_prueba_interno', '', 'string',
       'Dirección de correo interna a la que se envía la propuesta al pulsar "Enviar correo de prueba" en la Cotización Final. No afecta el envío al cliente.',
       NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM quotation_config WHERE config_key = 'correo_prueba_interno');

-- 2) Marcar la migración como aplicada
INSERT IGNORE INTO migrations (migration, batch)
SELECT '2026_05_26_000001_seed_correo_prueba_config', COALESCE(MAX(batch), 0) + 1
FROM migrations;

-- Verificación
SELECT config_key, config_value, config_type FROM quotation_config WHERE config_key = 'correo_prueba_interno';
