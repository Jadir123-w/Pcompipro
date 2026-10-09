-- Aplicar manualmente la migración 2026_05_17_000001 a la BD del tenant
-- (tenancy:migrate falla por una migración previa rota — supplier_categories
-- already exists — sin relación con este cambio).
--
-- Idempotente: usa INSERT IGNORE y chequea la tabla `migrations`.

USE tenancy_prodix;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 1) email_imap_circuit_breaker_max_failures (configurable, default 3)
INSERT IGNORE INTO marketing_chatbot_config
  (config_key, config_value, value_type, `group`, display_label, description,
   secret_env_var, is_sensitive, is_encrypted, sort_order, created_at, updated_at)
VALUES
  ('email_imap_circuit_breaker_max_failures', '3', 'int', 'email',
   'Circuit Breaker: máx. fallos consecutivos',
   'Cuántos fallos consecutivos de login IMAP se toleran antes de pausar el bot automáticamente. Default: 3 (suficiente para detectar credenciales mal configuradas y evitar bans del hosting). Subir a 5-7 si el server IMAP es inestable.',
   NULL, 0, 0, 200, NOW(), NOW());

-- 2) email_imap_consecutive_failures (state, contador)
INSERT IGNORE INTO marketing_chatbot_config
  (config_key, config_value, value_type, `group`, display_label, description,
   secret_env_var, is_sensitive, is_encrypted, sort_order, created_at, updated_at)
VALUES
  ('email_imap_consecutive_failures', '0', 'int', 'email',
   '[Auto] Fallos consecutivos del breaker',
   'Contador automático. Se incrementa con cada fallo de login IMAP y se resetea con cada login exitoso. NO editar a mano (usar "php artisan marketing:email-triage:reset-breaker").',
   NULL, 0, 0, 210, NOW(), NOW());

-- 3) email_imap_last_failure_at (state, timestamp)
INSERT IGNORE INTO marketing_chatbot_config
  (config_key, config_value, value_type, `group`, display_label, description,
   secret_env_var, is_sensitive, is_encrypted, sort_order, created_at, updated_at)
VALUES
  ('email_imap_last_failure_at', NULL, 'string', 'email',
   '[Auto] Último fallo de IMAP (timestamp)',
   'Fecha y hora del último fallo de login IMAP. Automático.',
   NULL, 0, 0, 220, NOW(), NOW());

-- 4) email_imap_last_failure_reason (state, mensaje)
INSERT IGNORE INTO marketing_chatbot_config
  (config_key, config_value, value_type, `group`, display_label, description,
   secret_env_var, is_sensitive, is_encrypted, sort_order, created_at, updated_at)
VALUES
  ('email_imap_last_failure_reason', NULL, 'text', 'email',
   '[Auto] Último fallo de IMAP (motivo)',
   'Mensaje de error del último fallo de login IMAP. Útil para diagnosticar credenciales incorrectas, problemas de red, o bans del firewall del proveedor. Automático.',
   NULL, 0, 0, 230, NOW(), NOW());

-- Marcar la migración como aplicada para que tenancy:migrate la salte
INSERT IGNORE INTO migrations (migration, batch)
SELECT '2026_05_17_000001_add_email_imap_circuit_breaker_config',
       COALESCE(MAX(batch), 0) + 1
FROM migrations;

-- Verificación
SELECT config_key, value_type, `group`, sort_order, LEFT(display_label, 50) AS label
FROM marketing_chatbot_config
WHERE `group` = 'email'
  AND (config_key LIKE '%circuit_breaker%'
       OR config_key = 'email_imap_consecutive_failures'
       OR config_key LIKE 'email_imap_last_failure%')
ORDER BY sort_order;
