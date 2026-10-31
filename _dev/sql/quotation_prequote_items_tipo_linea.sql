-- ============================================================
-- COLUMNA: quotation_prequote_items.tipo_linea
-- Proposito: Identificar si la linea es producto, servicio o comision.
--
-- Ejecutar manualmente en cada tenant donde se use pre-cotizacion.
-- Las filas existentes quedan como 'product' por el valor DEFAULT.
-- Seguro para re-ejecutar en MySQL 8.x.
-- ============================================================

SET @tipo_linea_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quotation_prequote_items'
      AND COLUMN_NAME = 'tipo_linea'
);

SET @tipo_linea_sql = IF(
    @tipo_linea_exists = 0,
    'ALTER TABLE `quotation_prequote_items`
       ADD COLUMN `tipo_linea` VARCHAR(20) NOT NULL DEFAULT ''product''
       COMMENT ''Tipo de linea: product, service o comision''
       AFTER `item_order`',
    'SELECT ''La columna tipo_linea ya existe; no se realizaron cambios'' AS mensaje'
);

PREPARE tipo_linea_stmt FROM @tipo_linea_sql;
EXECUTE tipo_linea_stmt;
DEALLOCATE PREPARE tipo_linea_stmt;

-- Verificacion
-- SHOW COLUMNS FROM `quotation_prequote_items` LIKE 'tipo_linea';
