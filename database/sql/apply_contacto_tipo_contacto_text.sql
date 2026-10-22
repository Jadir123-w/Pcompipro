-- ============================================================================
-- "Detalle de contacto" (contacto.tipo_contacto) a TEXT
-- ============================================================================
-- Equivalente a la migración tenant 2026_10_21_000001_change_tipo_contacto_to_text_on_contacto.php
-- (en el VPS la aplica `php artisan tenancy:migrate`). Idempotente: conserva el contenido.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- ============================================================================

ALTER TABLE `contacto` MODIFY `tipo_contacto` TEXT NULL;
