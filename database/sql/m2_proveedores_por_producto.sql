-- ============================================================================
-- M2 — Gestión de proveedores por producto en el proyecto
-- M4 — Costo total del proyecto (Monto total proyecto)
-- ----------------------------------------------------------------------------
-- Correr en PRUEBAS sobre la BD del tenant (tenancy_prodix).
-- DBeaver: seleccionar TODO el script y ejecutar con ALT + X (script completo).
-- Es idempotente: se puede volver a correr sin romper nada.
-- ============================================================================

-- 1) Proveedores por producto ------------------------------------------------
CREATE TABLE IF NOT EXISTS proyecto_producto_proveedores (
  id                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  proyecto_producto_id       BIGINT UNSIGNED NOT NULL,
  supplier_id                BIGINT UNSIGNED NULL,
  supplier_name              VARCHAR(255) NULL,
  concepto                   VARCHAR(500) NULL,
  cantidad                   DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  precio_unitario_informado  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  condicion_igv              VARCHAR(20)  NOT NULL DEFAULT 'pending',  -- included|excluded|exempt|pending
  precio_unitario_neto       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  subtotal_neto              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  igv_monto                  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_pagar                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  activo                     TINYINT(1) NOT NULL DEFAULT 1,            -- 1=activo, 0=reemplazado (historial)
  reemplaza_a_id             BIGINT UNSIGNED NULL,
  motivo_reemplazo           VARCHAR(255) NULL,
  orden                      INT NOT NULL DEFAULT 0,
  created_at                 TIMESTAMP NULL DEFAULT NULL,
  updated_at                 TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_ppp_producto (proyecto_producto_id),
  KEY idx_ppp_supplier (supplier_id),
  CONSTRAINT fk_ppp_producto FOREIGN KEY (proyecto_producto_id)
    REFERENCES proyecto_productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Costos operativos por producto -----------------------------------------
ALTER TABLE proyecto_productos
  ADD COLUMN IF NOT EXISTS costo_empaque   DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER personalizacion,
  ADD COLUMN IF NOT EXISTS costo_movilidad DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER costo_empaque,
  ADD COLUMN IF NOT EXISTS costo_sctr      DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER costo_movilidad;

-- 3) Costo total del proyecto (M4) ------------------------------------------
ALTER TABLE proyecto
  ADD COLUMN IF NOT EXISTS costo_total_proyecto DECIMAL(12,2) NULL AFTER monto_costo;
