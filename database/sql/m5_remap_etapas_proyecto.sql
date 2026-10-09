-- ============================================================================
-- M5 — Remapea las etapas viejas de `proyecto` a la lista nueva.
-- ----------------------------------------------------------------------------
-- Correr en PRUEBAS sobre la BD del tenant (tenancy_prodix).
-- DBeaver: seleccionar TODO el script y ejecutar con ALT + X.
-- NOTA: el CI también lo aplica solo vía `tenancy:migrate` al desplegar; este
-- SQL es por si querés dejarlo listo de inmediato. Es idempotente.
-- ============================================================================

UPDATE proyecto SET etapa = 'Nuevo'                     WHERE etapa IN ('Inicio');
UPDATE proyecto SET etapa = 'En curso / Pre-producción' WHERE etapa IN ('Arte en proceso', 'Arte aprobado');
UPDATE proyecto SET etapa = 'En producción'             WHERE etapa IN ('En curso / Producción', 'En produccinnn');
UPDATE proyecto SET etapa = 'Final producción'          WHERE etapa IN ('Control de calidad', 'Listo para despacho');
UPDATE proyecto SET etapa = 'Entregado'                 WHERE etapa IN ('Despachado');
UPDATE proyecto SET etapa = 'Pendiente de pago'         WHERE etapa IN ('Facturado', 'Pendiente de Pago');
UPDATE proyecto SET etapa = 'Terminado'                 WHERE etapa IN ('Cobrado');

-- Catch-all: cualquier etapa fuera de la lista nueva → 'Nuevo'.
UPDATE proyecto SET etapa = 'Nuevo'
 WHERE etapa IS NOT NULL
   AND etapa NOT IN (
       'Nuevo', 'En curso / Pre-producción', 'En producción', 'Final producción',
       'En reproceso', 'Entregado', 'Pendiente de pago', 'Terminado'
   );
