-- ============================================================================
-- Índice para el cálculo de notas de crédito de Compras (buscador y saldo)
-- Fecha: 2026-09-16
--
-- CONTEXTO
--   El saldo de cada compra resta las notas de crédito (tipo '04') que la
--   modifican. Esa subconsulta busca las NC por PROVEEDOR y luego compara el
--   número del documento modificado fila por fila: sin índice, el costo crece
--   con el CUADRADO de las compras por proveedor. Medido en local con 4.740
--   compras de una empresa (transacción revertida):
--     - buscar un monto con decimales en Compras: 5,5 s  →  81 ms con el índice
--     - versión anterior del listado (antes de la optimización del 16-09):
--       6 a 11 s por búsqueda  →  ~190 ms con el índice
--   Lo usan el listado de Compras (saldo, filtro "pago:", búsqueda de montos),
--   Cuentas por Pagar y cualquier cálculo de saldo de compras.
--
--   Es el MISMO índice (mismo nombre y definición) que ya estaba en
--   database/migrations/20260910_indices_cuentas_por_pagar.sql y en
--   database/compras_indices_listado.sql. Si alguno de esos ya se ejecutó en
--   esta base, este archivo no hace nada (IF NOT EXISTS).
--
-- QUÉ HACE
--   Crea UN índice parcial, pequeño (solo notas de crédito vivas). NO modifica
--   ni borra datos. Idempotente: se puede ejecutar varias veces.
--
-- REVERSIBLE, sin pérdida de datos:
--   DROP INDEX IF EXISTS idx_compras_nc_documento_modificado;
--
-- CÓMO EJECUTARLO (pgAdmin)
--   Pegar todo en el Query Tool y ejecutar (F5). El CREATE INDEX normal bloquea
--   las ESCRITURAS de compras_cabecera mientras se construye (las lecturas no);
--   como es un índice parcial de pocas filas tarda muy poco, pero conviene
--   hacerlo en horario de baja actividad. Variante CONCURRENTLY al final.
-- ============================================================================


-- 0) (Opcional, solo lectura) ¿Ya existe un índice equivalente con OTRO nombre?
--    IF NOT EXISTS solo compara el nombre. Si esta consulta devuelve un índice
--    sobre compras_cabecera que ya incluye documento_modificado con
--    tipo_comprobante = '04', NO hace falta crear el de abajo.
-- SELECT indexname, indexdef
-- FROM pg_indexes
-- WHERE tablename = 'compras_cabecera'
--   AND indexdef ILIKE '%documento_modificado%';


-- 1) Índice parcial para cruzar compras con sus notas de crédito -------------
CREATE INDEX IF NOT EXISTS idx_compras_nc_documento_modificado
    ON compras_cabecera (id_empresa, id_proveedor, documento_modificado)
    WHERE tipo_comprobante = '04' AND eliminado = false;

-- 2) Refrescar estadísticas para que el planificador lo use -----------------
ANALYZE compras_cabecera;


-- COMPROBACIÓN (ejecutar aparte): debe salir 1 fila con indisvalid = true
-- SELECT c.relname AS indice, i.indisvalid
-- FROM pg_index i
-- JOIN pg_class c ON c.oid = i.indexrelid
-- WHERE c.relname = 'idx_compras_nc_documento_modificado';


-- VARIANTE OPCIONAL SIN BLOQUEAR ESCRITURAS (no usar junto con el paso 1)
--   CONCURRENTLY no puede ir en una transacción: en pgAdmin hay que ejecutar
--   UNA sola sentencia seleccionada a la vez (o usar psql). Si falla a medio
--   camino deja el índice inválido: DROP INDEX IF EXISTS … y volver a lanzar.
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_compras_nc_documento_modificado
--     ON compras_cabecera (id_empresa, id_proveedor, documento_modificado)
--     WHERE tipo_comprobante = '04' AND eliminado = false;
