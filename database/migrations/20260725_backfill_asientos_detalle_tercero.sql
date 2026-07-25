-- ============================================================
-- Asientos contables › completar Tercero y Documento en las líneas
--
-- Las líneas de asiento (asientos_contables_detalle) guardan el tercero
-- (tipo_entidad + id_entidad) y el número del documento (documento_referencia),
-- pero no todos los orígenes los llenan: los asientos generados por el
-- builder/sincronizador y los migrados desde el sistema viejo quedaron con esas
-- columnas en NULL. Por eso, en el Mayor (y en el Excel del Mayor), las columnas
-- "Tercero" y "Documento Ref." salían vacías aunque el dato sí exista en el
-- documento origen.
--
-- Este script rellena esas columnas leyendo el documento que originó el asiento,
-- por dos caminos:
--   1. modulo_origen + id_referencia_origen  → asientos de módulo (por clave primaria).
--   2. documento.id_asiento_contable = asiento.id → enlace inverso; es la única vía
--      para los asientos migrados, cuyo modulo_origen es 'migracion' y no dice a qué
--      tabla apunta id_referencia_origen.
--
-- El código ya resuelve lo mismo al vuelo (App\Helpers\DocumentoOrigenAsiento), así
-- que el Mayor se ve completo sin ejecutar esto. Correrlo deja el dato guardado, que
-- es lo que usan el resto de reportes y consultas.
--
-- NO destructivo: solo escribe donde el valor está en NULL/vacío, nunca pisa un dato
-- existente. Idempotente: se puede volver a ejecutar (la segunda vez no toca nada).
-- Salta en silencio las tablas de módulos que aún no estén desplegados.
-- ============================================================

BEGIN;

-- ------------------------------------------------------------
-- 1) Índices del enlace documento → asiento.
--    Sin ellos, buscar el documento de un asiento migrado recorre la tabla entera
--    (lo usan tanto este script como el Mayor).
-- ------------------------------------------------------------
DO $$
DECLARE
    d RECORD;
BEGIN
    FOR d IN
        SELECT * FROM (VALUES
            ('ventas_cabecera',           'id_asiento_contable'),
            ('compras_cabecera',          'id_asiento_contable'),
            ('ingresos_cabecera',         'id_asiento_contable'),
            ('egresos_cabecera',          'id_asiento_contable'),
            ('recibos_venta_cabecera',    'id_asiento_contable'),
            ('notas_credito_cabecera',    'id_asiento_contable'),
            ('retencion_compra_cabecera', 'id_asiento_contable'),
            ('retencion_venta_cabecera',  'id_asiento_contable'),
            ('liquidaciones_cabecera',    'id_asiento_contable'),
            ('importaciones_cabecera',    'id_asiento_contable'),
            ('consignaciones_ventas',     'id_asiento_contable'),
            ('retornos_cv',               'id_asiento_contable'),
            ('cambios_producto_cv',       'id_asiento_contable'),
            ('consignaciones_facturas',   'id_asiento_reingreso')
        ) AS x(tabla, col)
    LOOP
        CONTINUE WHEN to_regclass('public.' || d.tabla) IS NULL;
        CONTINUE WHEN NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = d.tabla AND column_name = d.col
        );

        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS %I ON %I (%I) WHERE %I IS NOT NULL',
            'idx_' || d.tabla || '_asiento', d.tabla, d.col, d.col
        );
    END LOOP;
END $$;

-- ------------------------------------------------------------
-- 2) Relleno de tipo_entidad / id_entidad / documento_referencia.
--    Las expresiones de tercero y número replican el mapa de
--    App\Helpers\DocumentoOrigenAsiento: si se agrega un módulo allá, agregarlo aquí.
-- ------------------------------------------------------------
DO $$
DECLARE
    d RECORD;
    n BIGINT;
    total BIGINT := 0;
    num_sri CONSTANT TEXT := 'concat_ws(''-'', t.establecimiento, t.punto_emision, t.secuencial)';
BEGIN
    FOR d IN
        SELECT * FROM (VALUES
            ('factura_venta',      'ventas_cabecera',           '''cliente''',   't.id_cliente',   NULL,                    'id_asiento_contable'),
            ('compra',             'compras_cabecera',          '''proveedor''', 't.id_proveedor', 'concat_ws(''-'', t.establecimiento_prov, t.punto_emision_prov, t.secuencial_prov)', 'id_asiento_contable'),
            ('ingreso',            'ingresos_cabecera',         '''cliente''',   't.id_cliente',
                'COALESCE(NULLIF(t.numero_ingreso, ''''), concat_ws(''-'', t.establecimiento, t.punto_emision, t.secuencial))', 'id_asiento_contable'),
            ('egreso',             'egresos_cabecera',
                'CASE WHEN t.id_proveedor IS NOT NULL THEN ''proveedor'' WHEN t.id_empleado IS NOT NULL THEN ''empleado'' END',
                'COALESCE(t.id_proveedor, t.id_empleado)',
                'COALESCE(NULLIF(t.numero_egreso, ''''), concat_ws(''-'', t.establecimiento, t.punto_emision, t.secuencial))', 'id_asiento_contable'),
            ('recibo_venta',       'recibos_venta_cabecera',    '''cliente''',   't.id_cliente',
                'COALESCE(NULLIF(t.recibo_numero, ''''), concat_ws(''-'', t.establecimiento, t.punto_emision, t.secuencial))', 'id_asiento_contable'),
            ('nota_credito',       'notas_credito_cabecera',    '''cliente''',   't.id_cliente',   NULL,                    'id_asiento_contable'),
            ('retencion_compra',   'retencion_compra_cabecera', '''proveedor''', 't.id_proveedor', NULL,                    'id_asiento_contable'),
            ('retencion_venta',    'retencion_venta_cabecera',  '''cliente''',   't.id_cliente',   NULL,                    'id_asiento_contable'),
            ('liquidacion_compra', 'liquidaciones_cabecera',    '''proveedor''', 't.id_proveedor', NULL,                    'id_asiento_contable'),
            ('importacion',        'importaciones_cabecera',    '''proveedor''', 't.id_proveedor',
                'COALESCE(NULLIF(t.numero_importacion, ''''), concat_ws(''-'', t.establecimiento, t.punto_emision, t.secuencial))', 'id_asiento_contable'),
            ('consignacion_venta', 'consignaciones_ventas',     '''cliente''',   't.id_cliente',   NULL,                    'id_asiento_contable'),
            ('retorno_cv',         'retornos_cv',               '''cliente''',   't.id_cliente',   NULL,                    'id_asiento_contable'),
            ('cambio_producto_cv', 'cambios_producto_cv',       '''cliente''',   't.id_cliente',   NULL,                    'id_asiento_contable'),
            ('FACTURACION_CV',     'consignaciones_facturas',   '''cliente''',   't.id_cliente',
                'COALESCE(NULLIF(t.numero_factura, ''''), concat_ws(''-'', t.establecimiento, t.punto_emision, t.secuencial))', 'id_asiento_reingreso')
        ) AS x(modulo, tabla, tipo, entidad, numero, col_asiento)
    LOOP
        CONTINUE WHEN to_regclass('public.' || d.tabla) IS NULL;

        EXECUTE format(
            'UPDATE asientos_contables_detalle ad
                SET tipo_entidad          = COALESCE(ad.tipo_entidad, (%s)::varchar),
                    id_entidad            = COALESCE(ad.id_entidad, (%s)::bigint),
                    documento_referencia  = COALESCE(NULLIF(ad.documento_referencia, ''''), NULLIF(%s, '''')),
                    updated_at            = CURRENT_TIMESTAMP
               FROM asientos_contables_cabecera ac, %I t
              WHERE ad.id_asiento = ac.id
                AND ad.eliminado = false
                AND ac.eliminado = false
                AND t.id_empresa = ac.id_empresa
                AND ((ac.modulo_origen = %L AND t.id = ac.id_referencia_origen)
                     OR (ac.modulo_origen = ''migracion'' AND t.%I = ac.id))
                AND (ad.tipo_entidad IS NULL OR ad.id_entidad IS NULL OR NULLIF(ad.documento_referencia, '''') IS NULL)',
            d.tipo, d.entidad, COALESCE(d.numero, num_sri), d.tabla, d.modulo, d.col_asiento
        );

        GET DIAGNOSTICS n = ROW_COUNT;
        total := total + n;
        IF n > 0 THEN
            RAISE NOTICE '% → % línea(s) completada(s)', rpad(d.modulo, 20), n;
        END IF;
    END LOOP;

    RAISE NOTICE '--------------------------------------------';
    RAISE NOTICE 'Total de líneas de asiento completadas: %', total;
END $$;

COMMIT;

-- ------------------------------------------------------------
-- Verificación: líneas que siguen sin tercero, por origen.
-- Es normal que queden las de asientos manuales, nómina, traspasos, activos fijos y
-- declaraciones (no tienen un tercero único), y las migradas cuyo documento no llegó
-- a migrarse (no hay de dónde sacarlo).
-- ------------------------------------------------------------
-- SELECT ac.modulo_origen,
--        count(*) AS lineas,
--        count(*) FILTER (WHERE ad.id_entidad IS NULL) AS sin_tercero,
--        count(*) FILTER (WHERE COALESCE(ad.documento_referencia, '') = '') AS sin_documento
--   FROM asientos_contables_detalle ad
--   JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
--  WHERE ad.eliminado = false AND ac.eliminado = false AND ac.id_empresa = 8
--  GROUP BY 1 ORDER BY 2 DESC;
