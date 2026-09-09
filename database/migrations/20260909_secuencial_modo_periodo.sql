-- =====================================================================================
-- Numeración por fecha de emisión: modo y periodo de reinicio por tipo de secuencial.
--
-- QUÉ RESUELVE
--   Hasta ahora todo documento numeraba de una sola forma: un consecutivo corrido por
--   punto de emisión que nunca se reinicia (000000001, 000000002, ...). Este script
--   habilita una segunda forma, configurable en Empresa → Secuenciales para CADA tipo
--   de documento: que el correlativo arranque de cero en cada periodo (año o mes) según
--   la FECHA DE EMISIÓN del documento.
--
-- CÓMO SE VE EL NÚMERO
--   El secuencial sigue teniendo 9 dígitos: el periodo va como prefijo (año con 4 dígitos
--   y, en mensual, el mes con 2) y el correlativo ocupa lo que sobra:
--     anual    → AAAA   (4) + 5 dígitos  →  202600017  (documento 17 del año 2026)
--     mensual  → AAAAMM (6) + 3 dígitos  →  202609017  (documento 17 de septiembre de 2026)
--   Por eso NO hace falta tocar los índices únicos de secuencial ni el formato canónico
--   (App\Helpers\SecuencialFormato, 9 dígitos): el prefijo mantiene el número único
--   dentro de la serie.
--
--   El número se arma CONCATENANDO prefijo y correlativo, no sumando. Así, un periodo que
--   agote sus dígitos (el documento 1000 de un mes) crece hacia afuera —202609 + 1000 →
--   2026091000, 10 caracteres— en vez de caer encima de la numeración del periodo
--   siguiente. Por eso el paso 3 amplía las columnas `secuencial` que hoy son VARCHAR(9).
--
-- QUÉ NO CAMBIA AL EJECUTAR ESTE SCRIPT
--   Absolutamente nada: modo_numeracion nace en 'consecutivo' para TODAS las filas, que
--   es el comportamiento que el sistema tiene hoy. La numeración por fecha solo empieza
--   a aplicarse cuando alguien la elige a mano en Empresa → Secuenciales.
--
-- ALCANCE (se controla en el código, no aquí)
--   La opción solo se ofrece en los tipos de documento que NO se envían al SRI. Los
--   electrónicos (Facturas de venta, Facturas de reembolso, Nota de crédito, Nota de
--   débito, Guía de remisión, Liquidación de compras, Retenciones de compras) quedan
--   fuera: su secuencial forma parte de la clave de acceso y su numeración no admite
--   reinicios. La lista vive en SecuencialRepository::TIPOS_SIN_MODO_PERIODO.
--
-- IDEMPOTENTE: se puede volver a ejecutar sin efecto.
-- =====================================================================================

BEGIN;

-- -------------------------------------------------------------------------------------
-- 1. Columnas nuevas.
--    modo_numeracion  : 'consecutivo' (actual) | 'por_fecha' (reinicia por periodo)
--    periodo_reinicio : 'anual' | 'mensual'. Solo tiene sentido con modo 'por_fecha';
--                       en 'consecutivo' queda NULL.
-- -------------------------------------------------------------------------------------
ALTER TABLE empresa_secuencial
    ADD COLUMN IF NOT EXISTS modo_numeracion  VARCHAR(20) NOT NULL DEFAULT 'consecutivo',
    ADD COLUMN IF NOT EXISTS periodo_reinicio VARCHAR(10);

COMMENT ON COLUMN empresa_secuencial.modo_numeracion IS
    'Cómo se calcula el siguiente número: consecutivo (corrido, sin reinicio) o por_fecha (reinicia cada periodo según la fecha de emisión).';
COMMENT ON COLUMN empresa_secuencial.periodo_reinicio IS
    'Cada cuánto reinicia el correlativo en modo por_fecha: anual (prefijo AAAA) o mensual (prefijo AAAAMM). NULL en modo consecutivo.';

-- -------------------------------------------------------------------------------------
-- 2. Reglas que la base hace cumplir por sí misma.
--    Se crean con DO/EXCEPTION en vez de ADD CONSTRAINT IF NOT EXISTS porque esa
--    sintaxis no existe para constraints en PostgreSQL.
-- -------------------------------------------------------------------------------------
DO $$
BEGIN
    -- Valores admitidos en cada columna.
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_empresa_secuencial_modo') THEN
        ALTER TABLE empresa_secuencial
            ADD CONSTRAINT chk_empresa_secuencial_modo
            CHECK (modo_numeracion IN ('consecutivo', 'por_fecha'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_empresa_secuencial_periodo') THEN
        ALTER TABLE empresa_secuencial
            ADD CONSTRAINT chk_empresa_secuencial_periodo
            CHECK (periodo_reinicio IS NULL OR periodo_reinicio IN ('anual', 'mensual'));
    END IF;

    -- Coherencia entre ambas: 'por_fecha' exige periodo; 'consecutivo' no admite ninguno.
    -- Sin esto, una fila en modo por_fecha sin periodo dejaría al generador sin saber
    -- qué prefijo usar justo en el momento de emitir.
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_empresa_secuencial_modo_periodo') THEN
        ALTER TABLE empresa_secuencial
            ADD CONSTRAINT chk_empresa_secuencial_modo_periodo
            CHECK (
                (modo_numeracion = 'por_fecha'   AND periodo_reinicio IS NOT NULL)
             OR (modo_numeracion = 'consecutivo' AND periodo_reinicio IS NULL)
            );
    END IF;
END $$;

-- -------------------------------------------------------------------------------------
-- 3. Normalización defensiva: cualquier fila que hubiera quedado con un periodo suelto
--    en modo consecutivo (no debería existir en una instalación limpia) se deja coherente
--    ANTES de que el CHECK del paso 2 la evalúe en la próxima escritura.
-- -------------------------------------------------------------------------------------
UPDATE empresa_secuencial
   SET periodo_reinicio = NULL
 WHERE modo_numeracion = 'consecutivo'
   AND periodo_reinicio IS NOT NULL;

-- -------------------------------------------------------------------------------------
-- 4. Margen en la columna `secuencial` de los documentos que pueden numerar por fecha.
--
--    En modo mensual el correlativo tiene 3 dígitos (202609001 .. 202609999). Si un punto
--    de emisión llegara a emitir más de 999 documentos de un tipo en un mismo mes, el
--    número crece a 10 caracteres (2026091000) en vez de invadir octubre — y en una
--    columna VARCHAR(9) ese INSERT fallaría con "value too long".
--
--    Ampliar el largo de un varchar NO reescribe la tabla ni sus índices en PostgreSQL
--    (desde la 9.2): es un cambio de catálogo, instantáneo incluso en tablas grandes.
--    Los datos existentes quedan intactos y el formato canónico de 9 dígitos no cambia:
--    esto es solo margen para un desbordamiento que en la práctica casi nunca ocurre.
--
--    Solo se tocan las que hoy son VARCHAR(9); las que ya son VARCHAR(20) tienen sitio
--    de sobra. Los documentos electrónicos NO se tocan: no pueden numerar por fecha y su
--    secuencial ante el SRI es de exactamente 9 dígitos.
--
--    DOS OBSTÁCULOS DE POSTGRESQL, y cómo los sortea este script. En ambos casos se
--    guarda la definición del objeto, se elimina, se amplía la columna y se vuelve a crear
--    exactamente igual — todo dentro de la misma transacción, así que si algo falla no
--    queda nada a medias:
--
--    1) VISTAS. No deja cambiar el tipo de una columna de la que dependa una vista
--       ("no se puede alterar el tipo de una columna usada en una regla o vista"). En esta
--       base existe v_egresos_traspasos_secuencial (sobre egresos y traspasos), que no está
--       versionada en el repositorio; por eso se recupera su definición del catálogo en
--       vez de darla por conocida.
--
--    2) COLUMNAS GENERADAS. Tampoco deja hacerlo si otra columna se calcula a partir de
--       ella: ordenes_compra.numero_orden e importaciones_cabecera.numero_importacion son
--       `establecimiento-punto-secuencial`. Al recrearlas, PostgreSQL recalcula su valor
--       para todas las filas, así que no se pierde ningún dato. Sí cambian de posición
--       (pasan al final de la tabla): un INSERT sin lista de columnas se vería afectado,
--       pero en este sistema todos los INSERT nombran sus columnas.
--
--    El script ABORTA si alguna de esas columnas generadas tiene índices o constraints
--    propios: recrearla los perdería, y es preferible detenerse a hacerlo en silencio.
--    (Si alguna vista tuviera GRANTs propios, habría que reponerlos a mano; en esta
--    instalación todo corre con un único usuario de base de datos.)
-- -------------------------------------------------------------------------------------
DO $$
DECLARE
    t TEXT;
    v RECORD;
    g RECORD;
    con TEXT;
    tablas TEXT[] := ARRAY[
        'ingresos_cabecera',
        'egresos_cabecera',
        'traspasos_cabecera',
        'pedidos_cabecera',
        'proformas_cabecera',
        'recibos_venta_cabecera',
        'ordenes_compra',
        'importaciones_cabecera'
    ];
BEGIN
    -- 4.0 Si ninguna columna se queda corta, no hay nada que hacer: se sale sin tocar
    --     vistas ni columnas generadas. Así una segunda ejecución del script no las
    --     elimina y recrea para nada.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = ANY(tablas)
           AND column_name = 'secuencial'
           AND character_maximum_length IS NOT NULL
           AND character_maximum_length < 12
    ) THEN
        RAISE NOTICE 'La columna secuencial ya tiene el largo necesario en todas las tablas; nada que ampliar.';
        RETURN;
    END IF;

    -- 4.1 Guardar la definición de las vistas que dependen de esas columnas.
    CREATE TEMP TABLE _vistas_secuencial ON COMMIT DROP AS
    SELECT DISTINCT vista.relname::TEXT AS nombre,
           pg_get_viewdef(vista.oid, true) AS definicion
      FROM pg_depend d
      JOIN pg_rewrite r         ON r.oid = d.objid
      JOIN pg_class   vista     ON vista.oid = r.ev_class AND vista.relkind = 'v'
      JOIN pg_class   origen    ON origen.oid = d.refobjid
      JOIN pg_attribute a       ON a.attrelid = d.refobjid AND a.attnum = d.refobjsubid
      JOIN pg_namespace n       ON n.oid = origen.relnamespace AND n.nspname = 'public'
     WHERE origen.relname = ANY(tablas)
       AND a.attname = 'secuencial';

    -- 4.2 Guardar las columnas generadas a partir de `secuencial`, con su tipo y expresión.
    CREATE TEMP TABLE _generadas_secuencial ON COMMIT DROP AS
    SELECT c.relname::TEXT                          AS tabla,
           a.attname::TEXT                          AS columna,
           format_type(a.atttypid, a.atttypmod)     AS tipo,
           pg_get_expr(ad.adbin, ad.adrelid)        AS expresion
      FROM pg_class c
      JOIN pg_namespace n  ON n.oid = c.relnamespace AND n.nspname = 'public'
      JOIN pg_attribute a  ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
      JOIN pg_attrdef ad   ON ad.adrelid = c.oid AND ad.adnum = a.attnum
     WHERE c.relname = ANY(tablas)
       AND a.attgenerated = 's'
       AND pg_get_expr(ad.adbin, ad.adrelid) LIKE '%secuencial%';

    -- 4.3 Cortafuegos: no seguir si esas columnas generadas tienen índices o constraints.
    FOR g IN SELECT tabla, columna FROM _generadas_secuencial LOOP
        SELECT string_agg(i.relname, ', ') INTO con
          FROM pg_index x
          JOIN pg_class i ON i.oid = x.indexrelid
          JOIN pg_class c ON c.oid = x.indrelid
          JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(x.indkey)
         WHERE c.relname = g.tabla AND a.attname = g.columna;

        IF con IS NOT NULL THEN
            RAISE EXCEPTION
                'La columna generada %.% tiene índices (%): recrearla los perdería. Amplíe esa tabla a mano y vuelva a ejecutar.',
                g.tabla, g.columna, con;
        END IF;
    END LOOP;

    -- 4.4 Eliminar vistas y columnas generadas (sin CASCADE: si algo más dependiera de
    --     ellas, es preferible que el script se detenga con la transacción intacta).
    FOR v IN SELECT nombre FROM _vistas_secuencial LOOP
        EXECUTE format('DROP VIEW %I', v.nombre);
        RAISE NOTICE 'vista % eliminada temporalmente', v.nombre;
    END LOOP;

    FOR g IN SELECT tabla, columna FROM _generadas_secuencial LOOP
        EXECUTE format('ALTER TABLE %I DROP COLUMN %I', g.tabla, g.columna);
        RAISE NOTICE 'columna generada %.% eliminada temporalmente', g.tabla, g.columna;
    END LOOP;

    -- 4.5 Ampliar las columnas que se quedan cortas.
    FOREACH t IN ARRAY tablas LOOP
        IF EXISTS (
            SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = t
               AND column_name = 'secuencial'
               AND character_maximum_length IS NOT NULL
               AND character_maximum_length < 12
        ) THEN
            EXECUTE format('ALTER TABLE %I ALTER COLUMN secuencial TYPE VARCHAR(12)', t);
            RAISE NOTICE 'secuencial ampliado a VARCHAR(12) en %', t;
        END IF;
    END LOOP;

    -- 4.6 Volver a crear todo tal como estaba.
    FOR g IN SELECT tabla, columna, tipo, expresion FROM _generadas_secuencial LOOP
        EXECUTE format('ALTER TABLE %I ADD COLUMN %I %s GENERATED ALWAYS AS (%s) STORED',
                       g.tabla, g.columna, g.tipo, g.expresion);
        RAISE NOTICE 'columna generada %.% recreada', g.tabla, g.columna;
    END LOOP;

    FOR v IN SELECT nombre, definicion FROM _vistas_secuencial LOOP
        EXECUTE format('CREATE VIEW %I AS %s', v.nombre, v.definicion);
        RAISE NOTICE 'vista % recreada', v.nombre;
    END LOOP;
END $$;

COMMIT;

-- -------------------------------------------------------------------------------------
-- Verificación (opcional): cómo quedó configurado cada tipo por punto de emisión.
-- -------------------------------------------------------------------------------------
-- SELECT id_empresa, id_punto_emision, tipo_documento, secuencial_inicial,
--        modo_numeracion, periodo_reinicio
--   FROM empresa_secuencial
--  WHERE eliminado = false
--  ORDER BY id_empresa, id_punto_emision, tipo_documento;
