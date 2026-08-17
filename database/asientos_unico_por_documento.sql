-- ============================================================================
-- Un solo asiento contable VIVO por documento — red de seguridad en la BD
-- ----------------------------------------------------------------------------
-- La generación de asientos es un "leer → decidir → escribir": el módulo pregunta
-- con getAsientoPorOrigen() si el documento ya tiene asiento y, si no, inserta.
-- Ese patrón ya está protegido en código con un candado transaccional
-- (AsientoContableRepository::lockAsientoOrigen(), tomado dentro de
-- AsientoContableService::guardarAsiento()), pero el candado solo cubre a quien
-- pasa por ese Service. Este índice es el respaldo de último recurso: si alguna
-- vía futura inserta directo, o alguien hace un INSERT a mano, la BD lo rechaza
-- con 23505 en vez de dejar el documento contabilizado dos veces.
--
-- Duplicar el asiento de un documento NO es cosmético: los dos suman en el Balance
-- de Comprobación y en los Estados Financieros, así que el gasto, el IVA y la
-- cuenta por pagar/cobrar del documento quedan al doble.
--
-- ── Qué queda FUERA del índice, y por qué ──────────────────────────────────────
--   • estado = 'anulado'  → anular un asiento y regenerarlo es un flujo válido y
--     esperado (AsientoContableService.php:322): el anulado queda como rastro y
--     conviven con el nuevo. Solo se exige UNO no anulado a la vez.
--   • eliminado = true    → eliminación lógica, ya no cuenta.
--   • modulo_origen 'nomina' → un rol se contabiliza con un asiento POR EMPLEADO,
--     así que varios por documento es lo correcto (RolAsientoService). Debe seguir
--     alineado con AsientoContableService::ORIGENES_MULTIASIENTO.
--   • modulo_origen 'migracion' → el id_referencia_origen de un asiento migrado no
--     identifica una tabla concreta (el id 5 puede ser una compra y una factura a la
--     vez), así que agruparlos daría choques falsos.
--   • modulo_origen 'manual' → asientos escritos a mano, sin documento de origen.
--   • tipo_ambiente va DENTRO de la clave: pruebas (1) y producción (2) son mundos
--     separados y getAsientoPorOrigen() filtra por el ambiente de la empresa, así que
--     el mismo documento puede tener un asiento en cada uno legítimamente.
-- ============================================================================


-- ── PASO 1 (OBLIGATORIO): ¿hay duplicados que impedirían crear el índice? ─────
-- Si esta consulta devuelve filas, el CREATE INDEX de abajo VA A FALLAR.
-- Resolvelos primero desde Auditoría Contable (hallazgo «duplicado» → anular el
-- que sobra, o «huérfano» si su documento fue eliminado).

SELECT id_empresa,
       modulo_origen,
       id_referencia_origen,
       tipo_ambiente,
       COUNT(*)                                                       AS n_asientos,
       string_agg(id::text || ' (' || numero_comprobante || ')', ', '
                  ORDER BY id)                                        AS asientos
FROM asientos_contables_cabecera
WHERE eliminado = false
  AND estado <> 'anulado'
  AND id_referencia_origen IS NOT NULL
  AND modulo_origen NOT IN ('manual', 'migracion', 'nomina')
GROUP BY id_empresa, modulo_origen, id_referencia_origen, tipo_ambiente
HAVING COUNT(*) > 1
ORDER BY id_empresa, modulo_origen, id_referencia_origen;


-- ── PASO 2: crear el índice ──────────────────────────────────────────────────
-- CONCURRENTLY no bloquea las escrituras de la tabla mientras se construye, pero
-- NO puede correr dentro de una transacción: ejecutá esta sentencia sola (si tu
-- cliente abre transacción automática, usá la variante sin CONCURRENTLY de abajo).

CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS uq_asientos_vivo_por_documento
    ON asientos_contables_cabecera (id_empresa, modulo_origen, id_referencia_origen, tipo_ambiente)
    WHERE eliminado = false
      AND estado <> 'anulado'
      AND id_referencia_origen IS NOT NULL
      AND modulo_origen NOT IN ('manual', 'migracion', 'nomina');

-- Variante para clientes que no permiten CONCURRENTLY (bloquea escrituras unos ms):
-- CREATE UNIQUE INDEX IF NOT EXISTS uq_asientos_vivo_por_documento
--     ON asientos_contables_cabecera (id_empresa, modulo_origen, id_referencia_origen, tipo_ambiente)
--     WHERE eliminado = false
--       AND estado <> 'anulado'
--       AND id_referencia_origen IS NOT NULL
--       AND modulo_origen NOT IN ('manual', 'migracion', 'nomina');


-- ── PASO 3: verificar que quedó válido ───────────────────────────────────────
-- Un CREATE INDEX CONCURRENTLY que falla deja el índice en estado INVÁLIDO y sigue
-- ocupando espacio sin servir. indisvalid debe salir 't'.

SELECT c.relname AS indice, i.indisvalid AS valido, i.indisunique AS es_unico
FROM pg_class c
JOIN pg_index i ON i.indexrelid = c.oid
WHERE c.relname = 'uq_asientos_vivo_por_documento';


-- ── ROLLBACK ─────────────────────────────────────────────────────────────────
-- DROP INDEX IF EXISTS uq_asientos_vivo_por_documento;
