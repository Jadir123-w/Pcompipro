-- Fase C — Precios por volumen (demo).
--
-- Pobla item_unit_types con escalones de ejemplo para 3 productos típicos
-- de COMPIPRO. Útil para validar la Fase C end-to-end en Pruebas o local.
--
-- Cómo se interpretan los escalones (lógica mayorista clásica del cotizador):
--   Q cotizada → último escalón cuyo quantity_unit <= Q.
--   Q debajo del menor escalón → fallback al purchase_unit_price del item.
--
-- IDEMPOTENTE: chequea si ya hay escalones para cada item antes de insertar.
-- Si el ítem ya tiene escalones, los saltea (no duplica).

USE tenancy_compipro;   -- ⚠️ cambiar al schema del tenant correcto en Pruebas
SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────────
-- 1) POLO CUELLO REDONDO PIQUE  (busca por nombre para evitar ID hardcoded)
-- ────────────────────────────────────────────────────────────────────────
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 50 polos',  id, 'NIU', 50,  30.00, 0, 0, 1 FROM items WHERE name = 'POLO CUELLO REDONDO PIQUE' AND id NOT IN (SELECT item_id FROM item_unit_types) LIMIT 1;
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 100 polos', id, 'NIU', 100, 27.00, 0, 0, 1 FROM items WHERE name = 'POLO CUELLO REDONDO PIQUE' AND id NOT IN (SELECT item_id FROM item_unit_types WHERE quantity_unit = 100) LIMIT 1;
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 500 polos', id, 'NIU', 500, 22.00, 0, 0, 1 FROM items WHERE name = 'POLO CUELLO REDONDO PIQUE' AND id NOT IN (SELECT item_id FROM item_unit_types WHERE quantity_unit = 500) LIMIT 1;

-- ────────────────────────────────────────────────────────────────────────
-- 2) MOCHILA ESCOLAR POLIESTER 600D
-- ────────────────────────────────────────────────────────────────────────
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 25 mochilas',  id, 'NIU', 25,  70.00, 0, 0, 1 FROM items WHERE name = 'MOCHILA ESCOLAR POLIESTER 600D' AND id NOT IN (SELECT item_id FROM item_unit_types) LIMIT 1;
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 100 mochilas', id, 'NIU', 100, 60.00, 0, 0, 1 FROM items WHERE name = 'MOCHILA ESCOLAR POLIESTER 600D' AND id NOT IN (SELECT item_id FROM item_unit_types WHERE quantity_unit = 100) LIMIT 1;

-- ────────────────────────────────────────────────────────────────────────
-- 3) POLO TIPO CAMISERO ALGODON
-- ────────────────────────────────────────────────────────────────────────
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 30 polos',  id, 'NIU', 30,  36.00, 0, 0, 1 FROM items WHERE name = 'POLO TIPO CAMISERO ALGODON' AND id NOT IN (SELECT item_id FROM item_unit_types) LIMIT 1;
INSERT INTO item_unit_types (description, item_id, unit_type_id, quantity_unit, price1, price2, price3, price_default)
SELECT 'Compra desde 200 polos', id, 'NIU', 200, 30.00, 0, 0, 1 FROM items WHERE name = 'POLO TIPO CAMISERO ALGODON' AND id NOT IN (SELECT item_id FROM item_unit_types WHERE quantity_unit = 200) LIMIT 1;

-- Verificación
SELECT iut.id, i.name AS producto, iut.description, iut.quantity_unit, iut.price1
FROM item_unit_types iut
JOIN items i ON iut.item_id = i.id
WHERE i.name IN ('POLO CUELLO REDONDO PIQUE', 'MOCHILA ESCOLAR POLIESTER 600D', 'POLO TIPO CAMISERO ALGODON')
ORDER BY i.name, iut.quantity_unit;
