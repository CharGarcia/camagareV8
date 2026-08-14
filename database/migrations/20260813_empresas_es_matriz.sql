-- =============================================================================
-- Empresa matriz del grupo RUC (`empresas.es_matriz`)
-- ----------------------------------------------------------------------------
-- Varias filas de `empresas` (tenants separados, cada uno con su propio plan de
-- cuentas) pueden compartir el mismo RUC — es el "grupo RUC" que ya consolidan
-- Declaración de IVA / Retenciones / Control Bancario / Dashboard
-- (EmpresaRepository::getIdsEmpresaMismoRuc). Hasta ahora cuál de ellas es la
-- "matriz" era solo una etiqueta libre en `empresas.establecimiento` /
-- `empresa_establecimiento.tipo`, sin validar ni usarse para nada — cualquier
-- proceso que dijera "se ejecuta contra la matriz" en realidad usaba, sin más,
-- la empresa activa en sesión.
--
-- Este script agrega el flag real `es_matriz` (único por grupo RUC, se valida
-- en código — ver EmpresaService::marcarEstablecimientoMatriz()) y hace un
-- backfill: por cada RUC, se marca UNA sola empresa como matriz, prefiriendo la
-- que ya tiene código de establecimiento '001' (convención ya usada al crear
-- empresas — ver Empresa::crear()); si ninguna tiene '001', la de menor id.
--
-- Aditivo y no destructivo. Idempotente.
-- =============================================================================

BEGIN;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS es_matriz BOOLEAN NOT NULL DEFAULT false;

WITH grupos AS (
    SELECT
        ruc,
        COALESCE(
            (SELECT id FROM empresas e2
             WHERE e2.ruc = e1.ruc AND e2.eliminado = false AND e2.establecimiento = '001'
             ORDER BY e2.id ASC LIMIT 1),
            MIN(e1.id)
        ) AS id_matriz
    FROM empresas e1
    WHERE e1.eliminado = false
    GROUP BY ruc
)
UPDATE empresas e
SET es_matriz = true
FROM grupos g
WHERE e.ruc = g.ruc AND e.eliminado = false AND e.id = g.id_matriz
  AND NOT EXISTS (
      SELECT 1 FROM empresas e3 WHERE e3.ruc = g.ruc AND e3.eliminado = false AND e3.es_matriz = true
  );

COMMIT;
