-- ============================================================================
-- Bajas con fecha en teléfonos de contactos
-- ============================================================================
-- Equivalente a la migración tenant 2026_10_20_000001_add_deleted_at_to_contacto_telefonos.php
-- (en el VPS la aplica `php artisan tenancy:migrate`). Idempotente.
--
-- - contacto_telefonos.deleted_at: fecha de baja. estado = 0 sin fecha = suspendido
--   por inactivar el contacto (se recupera al activarlo).
-- - El índice único (contacto_id, telefono, estado) pasa a índice normal.
-- - Bajas antiguas (estado = 0 sin fecha) toman su updated_at como fecha.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- Luego: generar y revisar el SQL de datos con `php artisan contactos:normalizacion-sql`.
-- ============================================================================

ALTER TABLE `contacto_telefonos`
    ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `estado`;

ALTER TABLE `contacto_telefonos` DROP INDEX IF EXISTS `unique_contacto_telefono_activo`;
ALTER TABLE `contacto_telefonos`
    ADD INDEX IF NOT EXISTS `idx_contacto_telefonos_contacto_telefono` (`contacto_id`, `telefono`);

UPDATE `contacto_telefonos` SET `deleted_at` = COALESCE(`updated_at`, NOW()) WHERE `estado` = 0 AND `deleted_at` IS NULL;
UPDATE `contacto_emails`    SET `deleted_at` = COALESCE(`updated_at`, NOW()) WHERE `estado` = 0 AND `deleted_at` IS NULL;
