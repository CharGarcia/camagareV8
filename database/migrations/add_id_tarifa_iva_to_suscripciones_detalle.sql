-- Migración: Agregar id_tarifa_iva a suscripciones_detalle
-- Fecha: 2026-06-16
-- Razón: La tabla solo almacenaba porcentaje_iva (número), lo que no permitía
--        distinguir conceptos SRI con el mismo porcentaje (ej. varias tarifas al 0%).
--        Se agrega FK a tarifa_iva para guardar el concepto exacto.

ALTER TABLE suscripciones_detalle
ADD COLUMN IF NOT EXISTS id_tarifa_iva INTEGER REFERENCES tarifa_iva(id);

-- Poblar id_tarifa_iva en registros existentes, buscando la primera tarifa
-- que coincida con el porcentaje almacenado.
UPDATE suscripciones_detalle sd
SET id_tarifa_iva = (
    SELECT ti.id
    FROM tarifa_iva ti
    WHERE ROUND(ti.porcentaje_iva::numeric, 2) = ROUND(sd.porcentaje_iva::numeric, 2)
      AND ti.status = 1
    ORDER BY ti.id ASC
    LIMIT 1
)
WHERE sd.id_tarifa_iva IS NULL;
