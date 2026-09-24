-- ============================================================================
-- Notas de crédito: guardar la bodega de reintegro en la cabecera.
--
-- Antes la bodega solo viajaba en la petición y se usaba para el kardex, sin
-- guardarse: al reabrir o editar un borrador, el reingreso iba a la bodega que
-- tuviera el combo en ese momento. El código funciona con o sin esta columna
-- (NotaCreditoRepository::columnaExiste), así que el orden de despliegue da igual.
--
-- Idempotente: se puede ejecutar más de una vez.
-- ============================================================================

BEGIN;

ALTER TABLE notas_credito_cabecera
    ADD COLUMN IF NOT EXISTS id_bodega integer;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_nc_bodega') THEN
        ALTER TABLE notas_credito_cabecera
            ADD CONSTRAINT fk_nc_bodega FOREIGN KEY (id_bodega) REFERENCES bodegas (id);
    END IF;
END $$;

-- Relleno de las NC existentes: la bodega a la que fue su último movimiento de kardex
-- (vigente primero; si todos se revirtieron —NC anulada—, la del último revertido).
-- Las NC que nunca movieron inventario quedan en NULL. Se ignoran movimientos cuya
-- bodega ya no existe en la tabla bodegas (romperían la llave foránea).
UPDATE notas_credito_cabecera nc
SET id_bodega = k.id_bodega
FROM (
    SELECT DISTINCT ON (ik.referencia_id) ik.referencia_id, ik.id_bodega
    FROM inventario_kardex ik
    JOIN bodegas b ON b.id = ik.id_bodega
    WHERE ik.referencia_tipo = 'nota_credito'
    ORDER BY ik.referencia_id, ik.eliminado ASC, ik.id DESC
) k
WHERE k.referencia_id = nc.id
  AND nc.id_bodega IS NULL;

COMMIT;

-- Verificación: cuántas NC quedaron con y sin bodega.
SELECT (id_bodega IS NOT NULL) AS con_bodega, count(*)
FROM notas_credito_cabecera
WHERE eliminado = false
GROUP BY 1;
