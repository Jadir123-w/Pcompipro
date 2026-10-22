-- ============================================================
-- TABLAS: quotation_proposal_kits, quotation_proposal_kit_items
-- Proposito: Agrupar items de una pre-cotizacion en kits comerciales.
--
-- Ejecutar manualmente en cada tenant despues de crear las tablas base:
--   quotation_proposals
--   quotation_prequote_items
--
-- Seguro para re-ejecutar cuando las tablas ya existen.
-- ============================================================

CREATE TABLE IF NOT EXISTS `quotation_proposal_kits` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proposal_id`  BIGINT UNSIGNED NOT NULL
                   COMMENT 'FK a quotation_proposals (borrador 1:1 del prequote)',
    `nombre`       VARCHAR(191) NOT NULL,
    `descripcion`  TEXT NULL,
    `presentacion` VARCHAR(191) NULL
                   COMMENT 'Empaque / presentacion del kit',
    `imagen`       MEDIUMTEXT NULL
                   COMMENT 'Path relativo de la foto propia del kit',
    `cantidad`     INT NOT NULL DEFAULT 1
                   COMMENT 'Cantidad del kit (= la de todos sus componentes)',
    `precio_kit`   DECIMAL(12,2) NOT NULL DEFAULT 0
                   COMMENT 'Snapshot del consolidado al guardar',
    `created_at`   TIMESTAMP NULL,
    `updated_at`   TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pkit_proposal`
        FOREIGN KEY (`proposal_id`)
        REFERENCES `quotation_proposals` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_proposal_kit_items` (
    `kit_id`          BIGINT UNSIGNED NOT NULL,
    `prequote_item_id` BIGINT UNSIGNED NOT NULL,
    `cantidad`        DECIMAL(12,2) NOT NULL DEFAULT 1
                      COMMENT 'Siempre igual a la cantidad del kit y del item',
    `created_at`      TIMESTAMP NULL,
    `updated_at`      TIMESTAMP NULL,
    PRIMARY KEY (`kit_id`, `prequote_item_id`),
    CONSTRAINT `fk_pkititems_kit`
        FOREIGN KEY (`kit_id`)
        REFERENCES `quotation_proposal_kits` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_pkititems_item`
        FOREIGN KEY (`prequote_item_id`)
        REFERENCES `quotation_prequote_items` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificacion de las tablas
-- SELECT TABLE_NAME FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = DATABASE()
--    AND TABLE_NAME IN ('quotation_proposal_kits', 'quotation_proposal_kit_items');
