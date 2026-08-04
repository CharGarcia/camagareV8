-- ============================================================================
-- Compras invisibles en el listado por desfase de tipo_ambiente
-- ----------------------------------------------------------------------------
-- Causa: ComprasRepository::insertCabecera() grababa tipo_ambiente = 1 por
-- defecto cuando el llamador no lo enviaba explícitamente, y el formulario
-- manual de Compras (ComprasController::guardarAjax → ComprasService::crear)
-- nunca lo enviaba. El listado (getListado(), ComprasRepository.php ~línea 43)
-- filtra:
--   c.tipo_ambiente = (SELECT tipo_ambiente FROM empresas WHERE id = c.id_empresa)
-- Si el ambiente actual de la empresa no es '1', la compra creada a mano queda
-- invisible en el listado para siempre, sin importar permisos del usuario.
-- Mismo patrón de bug ya visto en Kardex y en Pedidos.
--
-- Ya corregido en código: insertCabecera() ahora toma tipo_ambiente en vivo de
-- `empresas` vía subconsulta (igual que ya hacía DocumentoAutomatedRegisterService
-- para las cargas del SRI), así que las compras NUEVAS no van a repetir esto.
--
-- Este script repara las que ya quedaron mal grabadas: iguala su tipo_ambiente
-- al de la empresa ACTUAL (que es justo lo que el listado espera).
--
-- NOTA: esto es distinto (más amplio) del reparo de tipo_ambiente ya aplicado
-- para documentos MIGRADOS (ver migracion-tipo-ambiente-visibilidad, acotado a
-- filas en migracion_mysql_map). Este cubre cualquier compra viva, migrada o
-- creada a mano, sin importar el origen — es seguro re-correrlo aunque ya
-- hayas aplicado aquel otro (mismo efecto, no duplica nada al ser UPDATE).
--
-- RIESGO CONOCIDO: el índice único uq_compras_numaut_activo es por
-- (id_empresa, numero_autorizacion) SIN incluir tipo_ambiente. Si dos compras
-- con el mismo numero_autorizacion quedaron conviviendo en ambientes distintos
-- ('1' y '2'), el UPDATE de la segunda puede fallar con 23505 al intentar
-- igualar su ambiente al de la primera. Por eso va envuelto en BEGIN/COMMIT: si
-- eso pasa, la transacción entera se aborta sola (no queda nada a medias) y el
-- mensaje de error señala cuál fila investigar a mano antes de reintentar.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- PASO 1 — DIAGNÓSTICO (solo lectura). Compras vivas cuyo tipo_ambiente no
-- coincide con el ambiente actual de su empresa → invisibles en el listado.
-- ----------------------------------------------------------------------------
SELECT c.id, c.id_empresa, c.tipo_comprobante, c.numero_autorizacion,
       c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov,
       c.importe_total, c.fecha_emision, c.created_by, c.created_at,
       c.tipo_ambiente        AS ambiente_compra,
       e.tipo_ambiente        AS ambiente_empresa_actual
FROM compras_cabecera c
JOIN empresas e ON e.id = c.id_empresa
WHERE c.eliminado = false
  AND c.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1))
ORDER BY c.id_empresa, c.id;

-- ----------------------------------------------------------------------------
-- PASO 2 — REPARACIÓN. Solo después de revisar el PASO 1.
-- No cambia ningún otro dato, no elimina nada.
-- ----------------------------------------------------------------------------
-- BEGIN;

UPDATE compras_cabecera c
SET tipo_ambiente = CAST(e.tipo_ambiente AS VARCHAR(1)),
    updated_at    = NOW()
FROM empresas e
WHERE e.id = c.id_empresa
  AND c.eliminado = false
  AND c.tipo_ambiente IS DISTINCT FROM CAST(e.tipo_ambiente AS VARCHAR(1));

-- Verificar que el PASO 1 ahora devuelve 0 filas; si todo bien: COMMIT;
-- si algo no cuadra: ROLLBACK;
