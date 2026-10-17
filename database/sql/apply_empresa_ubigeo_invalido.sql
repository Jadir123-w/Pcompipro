-- ============================================================================
-- Empresas con ubicación (ubigeo) que no existe en el catálogo
-- ============================================================================
-- La consulta del RUC a SUNAT devolvía "-" como departamento, provincia y distrito
-- cuando el contribuyente no tiene domicilio fiscal. Ese valor se guardaba en la empresa
-- y luego impedía crear su cliente (llave foránea de persons hacia departments).
--
-- Deja la ubicación vacía (NULL) en las empresas cuyo departamento, provincia o distrito
-- no existe en el catálogo, o que la tienen incompleta. Idempotente: una segunda ejecución
-- no cambia nada. No toca la dirección ni ningún otro dato.
--
-- Aplicar en cada BD tenant (tenancy_*), después de un backup.
-- ============================================================================

-- 1) Revisión previa (solo lectura): empresas que se van a limpiar
SELECT e.id, e.ruc, e.razon_social, e.department_id, e.province_id, e.district_id
FROM `empresa` e
LEFT JOIN `departments` d ON d.id = e.department_id
LEFT JOIN `provinces` p ON p.id = e.province_id
LEFT JOIN `districts` t ON t.id = e.district_id
WHERE (e.department_id IS NOT NULL OR e.province_id IS NOT NULL OR e.district_id IS NOT NULL)
  AND (d.id IS NULL OR p.id IS NULL OR t.id IS NULL);

-- 2) Limpieza
UPDATE `empresa` e
LEFT JOIN `departments` d ON d.id = e.department_id
LEFT JOIN `provinces` p ON p.id = e.province_id
LEFT JOIN `districts` t ON t.id = e.district_id
SET e.department_id = NULL, e.province_id = NULL, e.district_id = NULL
WHERE (e.department_id IS NOT NULL OR e.province_id IS NOT NULL OR e.district_id IS NOT NULL)
  AND (d.id IS NULL OR p.id IS NULL OR t.id IS NULL);

-- 3) Verificación: debe devolver 0
SELECT COUNT(*) AS empresas_con_ubigeo_invalido
FROM `empresa` e
LEFT JOIN `departments` d ON d.id = e.department_id
LEFT JOIN `provinces` p ON p.id = e.province_id
LEFT JOIN `districts` t ON t.id = e.district_id
WHERE (e.department_id IS NOT NULL OR e.province_id IS NOT NULL OR e.district_id IS NOT NULL)
  AND (d.id IS NULL OR p.id IS NULL OR t.id IS NULL);
