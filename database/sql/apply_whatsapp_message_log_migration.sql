-- Aplicar manualmente la migración 2026_05_21_000001 a la BD del tenant.
-- (tenancy:migrate puede fallar por migraciones previas rotas; este SQL es
-- el fallback. Idempotente: usa CREATE TABLE IF NOT EXISTS.)

USE tenancy_prodix;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `chatbot_whatsapp_message_log` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(64) NOT NULL COMMENT 'FK lógica a chatbot_sessions.session_id (formato wa_<numero>)',
  `wa_id` VARCHAR(30) NOT NULL COMMENT 'Número de WhatsApp del usuario (E.164 sin +)',
  `contact_name` VARCHAR(150) NULL,
  `direction` ENUM('inbound','outbound') NOT NULL COMMENT 'inbound = del usuario al bot; outbound = del bot al usuario (lo que la IA generó, antes de aprobación)',
  `message_text` TEXT NOT NULL,
  `ai_intent` VARCHAR(60) NULL COMMENT 'Intent detectado por el LLM (saludo, registrar_para_cotizacion, etc.)',
  `ai_provider` VARCHAR(30) NULL COMMENT 'claude | openai | gemini',
  `ai_model` VARCHAR(100) NULL,
  `meta` JSON NULL COMMENT 'Para inbound: {message_id de Meta, attachments}. Para outbound: {entities_extracted, actions, confidence, etc.}',
  `meta_message_id` VARCHAR(100) NULL COMMENT 'ID del mensaje de Meta (para inbound, el incoming_message_id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_created` (`session_id`, `created_at`),
  KEY `idx_wa_id` (`wa_id`),
  KEY `idx_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marcar migración como aplicada para que tenancy:migrate la salte
INSERT IGNORE INTO migrations (migration, batch)
SELECT '2026_05_21_000001_create_chatbot_whatsapp_message_log_table',
       COALESCE(MAX(batch), 0) + 1
FROM migrations;

-- Verificación
SHOW COLUMNS FROM chatbot_whatsapp_message_log;
