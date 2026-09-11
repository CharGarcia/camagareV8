-- ============================================================================
-- Índices por egreso: egresos_pagos y egresos_detalle
-- Fecha: 2026-09-11
--
-- CONTEXTO
--   PostgreSQL NO crea índices para las claves foráneas. En ingresos ya existen
--   (idx_ingresos_pagos_cabecera, idx_ingresos_det_cabecera e
--   idx_ingresos_detalle_referencia), pero en egresos no: egresos_pagos y
--   egresos_detalle solo tienen su PRIMARY KEY. Cada consulta que baja a los
--   pagos o al detalle de un egreso recorre entonces la tabla entera, sin
--   importar cuántas filas pida. Medido en producción el 10-09-2026:
--   91.424 líneas en egresos_detalle.
--
--   Lo usan, entre otros: abrir un egreso (EgresoRepository::getDetalles y
--   getPagos), Cuentas por Pagar, el listado de Compras, Control Bancario,
--   Impresión de cheques, Roles de pago / Décimos / Novedades, Saldos iniciales
--   CxP, Transferencias, el Dashboard y el Reporte de Ingresos y Egresos.
--
-- QUÉ HACE
--   Crea 3 índices. NO modifica, NO borra y NO toca ninguna fila de datos.
--   Es idempotente: se puede volver a ejecutar sin error.
--
-- REVERSIBLE, sin pérdida de datos:
--   DROP INDEX IF EXISTS idx_egresos_pagos_cabecera;
--   DROP INDEX IF EXISTS idx_egresos_detalle_egreso;
--   DROP INDEX IF EXISTS idx_egresos_detalle_documento;
--
-- ----------------------------------------------------------------------------
-- CÓMO EJECUTARLO EN pgAdmin
-- ----------------------------------------------------------------------------
--   1) Ejecutar el PASO 1 (solo consulta, no cambia nada) y revisar qué hay ya
--      en ESA base: el esquema de producción puede diferir del local. Los dos
--      índices de egresos_detalle también están en
--      database/migrations/20260910_indices_cuentas_por_pagar.sql; aquí se
--      repiten con EL MISMO nombre y la MISMA definición, así que si ese archivo
--      ya se ejecutó, el IF NOT EXISTS los salta sin error y no se duplican.
--
--   2) Pegar el PASO 2 y ejecutar (F5). Se ejecuta de una sola vez, no hay que
--      ir sentencia por sentencia.
--
--      HACERLO EN UN MOMENTO DE BAJA ACTIVIDAD: mientras se construye cada
--      índice, esa tabla NO acepta escrituras (quien esté guardando un egreso en
--      ese instante espera a que termine). Las CONSULTAS siguen funcionando. Con
--      ~90.000 filas son segundos.
--
--   3) Ejecutar el PASO 3 para comprobar que quedaron los 3 y que están válidos.
--
--   (Al final está la variante CONCURRENTLY, que no bloquea escrituras pero
--   obliga a ejecutar UNA sentencia a la vez.)
-- ============================================================================


-- ============================================================================
-- PASO 1 — Qué índices existen hoy (no cambia nada)
-- ============================================================================

-- 1.a) Todos los índices de las tablas involucradas, con los de ingresos al lado
--      como referencia de lo que debería existir en egresos.
SELECT tablename, indexname, indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename IN ('egresos_pagos', 'egresos_detalle', 'ingresos_pagos', 'ingresos_detalle')
ORDER BY tablename, indexname;

-- 1.b) ¿Ya hay un índice equivalente con OTRO nombre?
--      Importante: IF NOT EXISTS compara solo el NOMBRE. Si en esta base ya
--      existe un índice sobre las mismas columnas pero con otro nombre, el PASO 2
--      crearía un duplicado (ocupa espacio y hace más lenta cada escritura).
--      Si la columna "ya_existe_equivalente" trae algo, saltarse ese CREATE INDEX.
WITH propuestos (tabla, primera_columna, indice) AS (
    VALUES ('egresos_pagos',   'id_egreso',      'idx_egresos_pagos_cabecera'),
           ('egresos_detalle', 'id_egreso',      'idx_egresos_detalle_egreso'),
           ('egresos_detalle', 'tipo_documento', 'idx_egresos_detalle_documento')
)
SELECT p.indice AS indice_propuesto,
       p.tabla,
       p.primera_columna,
       string_agg(x.indexname || ' -> ' || x.indexdef, ' | ') AS ya_existe_equivalente
FROM propuestos p
LEFT JOIN pg_indexes x
       ON x.schemaname = 'public'
      AND x.tablename  = p.tabla
      AND x.indexdef ~ ('\(' || p.primera_columna || '(\)|,| )')
GROUP BY p.indice, p.tabla, p.primera_columna
ORDER BY p.indice;


-- ============================================================================
-- PASO 2 — Crear los índices
-- ============================================================================

-- 1) Pagos de un egreso -------------------------------------------------------
--    Espejo de idx_ingresos_pagos_cabecera. Es el que falta de verdad: hoy
--    cualquier lectura de los pagos de un egreso recorre egresos_pagos entera.
--    Va SIN el filtro "eliminado = false" a propósito: hay consultas que leen los
--    pagos sin filtrar por esa columna (saldos iniciales, impresión de cheques,
--    la reejecución de la migración, la verificación de la clave foránea), y un
--    índice parcial no las cubriría.
CREATE INDEX IF NOT EXISTS idx_egresos_pagos_cabecera
    ON egresos_pagos (id_egreso);

-- 2) Detalle de un egreso -----------------------------------------------------
--    Mismo nombre y misma definición que en
--    database/migrations/20260910_indices_cuentas_por_pagar.sql, para que no se
--    dupliquen si se ejecutan los dos archivos. Es parcial porque todas las
--    consultas de lectura del detalle filtran "eliminado = false" (un índice
--    parcial solo se usa si la consulta repite esa condición).
CREATE INDEX IF NOT EXISTS idx_egresos_detalle_egreso
    ON egresos_detalle (id_egreso)
    WHERE eliminado = false;

-- 3) Detalle por el documento que se está pagando -----------------------------
--    Para las consultas "cuánto se ha pagado de este documento": Cuentas por
--    Pagar agrupa por (tipo_documento, id_referencia_documento), y Compras,
--    Liquidaciones, Roles, Anticipos, Préstamos, Décimos y Saldos iniciales CxP
--    cruzan por ese mismo par. Mismo nombre y definición que el archivo del
--    10-09-2026.
CREATE INDEX IF NOT EXISTS idx_egresos_detalle_documento
    ON egresos_detalle (tipo_documento, id_referencia_documento)
    WHERE eliminado = false;

-- 4) Refrescar estadísticas para que el planificador use los índices nuevos ----
ANALYZE egresos_pagos;
ANALYZE egresos_detalle;


-- ============================================================================
-- PASO 3 — Comprobar que quedaron creados
-- ============================================================================
--   Deben salir 3 filas, todas con valido = true.
--   Si alguna sale con valido = false, quedó a medio crear (solo pasa con la
--   variante CONCURRENTLY): borrarla y volver a crearla.
--     DROP INDEX IF EXISTS <nombre>;
SELECT t.relname  AS tabla,
       c.relname  AS indice,
       i.indisvalid AS valido,
       pg_size_pretty(pg_relation_size(c.oid)) AS tamano,
       pg_get_indexdef(c.oid) AS definicion
FROM pg_index i
JOIN pg_class c ON c.oid = i.indexrelid
JOIN pg_class t ON t.oid = i.indrelid
WHERE c.relname IN ('idx_egresos_pagos_cabecera',
                    'idx_egresos_detalle_egreso',
                    'idx_egresos_detalle_documento')
ORDER BY t.relname, c.relname;


-- ============================================================================
-- VARIANTE SIN BLOQUEAR ESCRITURAS (opcional)
-- ----------------------------------------------------------------------------
-- Solo hace falta si hay que crearlos en pleno horario de trabajo.
--
-- CREATE INDEX CONCURRENTLY no puede correr dentro de una transacción, y cuando
-- pgAdmin envía varias sentencias juntas PostgreSQL las agrupa en una sola
-- transacción implícita: falla con "CREATE INDEX CONCURRENTLY no puede ser
-- ejecutado dentro de un bloque de transacción". Comprobado el 11-09-2026 contra
-- PostgreSQL 18: poner "Auto commit ON" NO evita el error; lo único que funciona
-- es enviar UNA sentencia a la vez (seleccionarla en el Query Tool y F5) o usar
-- psql. Cada una por separado:
--
--   CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_egresos_pagos_cabecera ON egresos_pagos (id_egreso);
--
--   CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_egresos_detalle_egreso ON egresos_detalle (id_egreso) WHERE eliminado = false;
--
--   CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_egresos_detalle_documento ON egresos_detalle (tipo_documento, id_referencia_documento) WHERE eliminado = false;
--
--   ANALYZE egresos_pagos;
--   ANALYZE egresos_detalle;
--
-- Si un CONCURRENTLY falla a medio camino deja el índice en estado inválido y el
-- IF NOT EXISTS lo saltaría al reintentar: hay que borrarlo primero
-- (DROP INDEX IF EXISTS <nombre>;) y volver a lanzarlo. El PASO 3 lo detecta.
-- ============================================================================
