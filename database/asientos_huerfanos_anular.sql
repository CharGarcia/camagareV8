-- ============================================================================
-- Anular asientos contables HUÉRFANOS (su documento de origen ya no existe)
-- ----------------------------------------------------------------------------
-- Un asiento huérfano es el que quedó vivo y 'contabilizado' después de que su
-- documento fuera eliminado. Sigue sumando en el Balance de Comprobación y en los
-- Estados Financieros, así que infla gasto/ingreso, IVA y las cuentas por pagar o
-- cobrar. Los generó el hueco de ComprasService/RetencionVentaService/ActivoFijoService,
-- que eliminaban el documento sin anular su asiento (ya corregido en código).
--
-- NO borra nada: replica exactamente lo que hace la aplicación en
-- AuditoriaContableRepository::anularAsiento() —
--     eliminado = true, estado = 'anulado', deleted_at/by, updated_at/by
-- en la cabecera y en su detalle. El rastro del asiento se conserva.
--
-- ── ORDEN DE EJECUCIÓN ──────────────────────────────────────────────────────
--   PASO 0  configurar el usuario  (una sola línea que editar)
--   PASO 1  descubrir (solo lectura) → revisar la salida
--   PASO 2  anular
--   PASO 3  verificar
-- El PASO 2 va dentro de una transacción explícita: revisá el conteo antes de
-- escribir COMMIT.
--
-- SQL puro, sin metaórdenes de psql: sirve igual en psql, pgAdmin o DBeaver.
--   psql "<conexión>" -f asientos_huerfanos_anular.sql
--
-- IMPORTANTE en clientes gráficos: ejecutá el archivo COMPLETO (o cada paso
-- entero), no statement por statement. El bloque DO del PASO 1 lleva comillas
-- dolarizadas ($$ … $f$) y algunos clientes lo parten mal si se ejecuta suelto.
-- Todo depende de tablas temporales, así que los 3 pasos van en la MISMA sesión;
-- si cerrás la conexión entre pasos, hay que volver al PASO 1.
-- ============================================================================


-- ============================================================================
--  PASO 0 — Usuario al que se le atribuye la anulación en la auditoría
--           (producción: 2). Es lo único que hay que editar.
-- ============================================================================
DROP TABLE IF EXISTS tmp_config;
CREATE TEMP TABLE tmp_config (uid integer NOT NULL);
INSERT INTO tmp_config (uid) VALUES (2);   -- <<< poné acá tu id de usuario


-- ============================================================================
--  PASO 1 — DESCUBRIR (solo lectura)
-- ============================================================================

-- Mapa origen → tabla del documento. Verificado contra el esquema: las 22 tablas
-- existen y todas tienen columna `eliminado`.
--   • 'manual' y 'migracion' quedan FUERA a propósito: su id_referencia_origen no
--     apunta a una sola tabla (el id 5 puede ser una compra y una factura a la vez),
--     así que no se puede decidir si están huérfanos.
--   • 'nomina' SÍ entra: tiene varios asientos por rol, pero si el rol se eliminó,
--     todos son huérfanos igual.
DROP TABLE IF EXISTS tmp_origen_tabla;
CREATE TEMP TABLE tmp_origen_tabla (origen text PRIMARY KEY, tabla text NOT NULL);
INSERT INTO tmp_origen_tabla (origen, tabla) VALUES
    ('factura_venta',              'ventas_cabecera'),
    ('compra',                     'compras_cabecera'),
    ('liquidacion_compra',         'liquidaciones_cabecera'),
    ('nota_credito',               'notas_credito_cabecera'),
    ('nota_debito',                'nota_debito_cabecera'),
    ('recibo_venta',               'recibos_venta_cabecera'),
    ('retencion_venta',            'retencion_venta_cabecera'),
    ('retencion_compra',           'retencion_compra_cabecera'),
    ('ingreso',                    'ingresos_cabecera'),
    ('egreso',                     'egresos_cabecera'),
    ('traspaso',                   'traspasos_cabecera'),
    ('importacion',                'importaciones_cabecera'),
    ('factura_reembolso',          'factura_reembolso_cabecera'),
    ('consignacion_venta',         'consignaciones_ventas'),
    ('FACTURACION_CV',             'consignaciones_facturas'),
    ('retorno_cv',                 'retornos_cv'),
    ('cambio_producto_cv',         'cambios_producto_cv'),
    ('activos_fijos_alta',         'activos_fijos'),
    ('activos_fijos_depreciacion', 'activos_fijos_lotes'),
    ('declaracion_iva',            'declaracion_iva_cabecera'),
    ('declaracion_retenciones',    'declaracion_retenciones_cabecera'),
    ('nomina',                     'rol_cabecera');

-- Recolecta los huérfanos. El nombre de tabla no se puede parametrizar, así que se
-- arma con format(%I) —identificador citado, no interpolación de texto— y solo se
-- usa para LEER.
DROP TABLE IF EXISTS tmp_asientos_huerfanos;
CREATE TEMP TABLE tmp_asientos_huerfanos (
    id_asiento           integer PRIMARY KEY,
    id_empresa           integer,
    modulo_origen        text,
    id_referencia_origen integer,
    numero_comprobante   text,
    fecha_asiento         date,
    total_debe           numeric,
    tipo_ambiente        text,
    motivo               text
);

DO $$
DECLARE r record; n integer;
BEGIN
    FOR r IN SELECT origen, tabla FROM tmp_origen_tabla LOOP
        EXECUTE format($f$
            INSERT INTO tmp_asientos_huerfanos
            SELECT a.id, a.id_empresa, a.modulo_origen, a.id_referencia_origen,
                   a.numero_comprobante, a.fecha_asiento, a.total_debe, a.tipo_ambiente,
                   CASE WHEN d.id IS NULL THEN 'documento inexistente'
                        ELSE 'documento eliminado' END
            FROM asientos_contables_cabecera a
            LEFT JOIN %I d ON d.id = a.id_referencia_origen
            WHERE a.modulo_origen = %L
              AND a.eliminado = false
              AND a.estado <> 'anulado'
              AND a.id_referencia_origen IS NOT NULL
              AND (d.id IS NULL OR d.eliminado = true)
        $f$, r.tabla, r.origen);
        GET DIAGNOSTICS n = ROW_COUNT;
        IF n > 0 THEN
            RAISE NOTICE '% -> % huerfano(s)', r.origen, n;
        END IF;
    END LOOP;
END $$;

-- 1a. Resumen por empresa y origen: cuánto dinero está inflado.
SELECT id_empresa,
       modulo_origen,
       motivo,
       COUNT(*)          AS asientos,
       SUM(total_debe)   AS monto_inflado
FROM tmp_asientos_huerfanos
GROUP BY id_empresa, modulo_origen, motivo
ORDER BY id_empresa, modulo_origen;

-- 1b. Detalle, para reconocer casos concretos antes de tocar nada.
SELECT id_asiento, id_empresa, modulo_origen, id_referencia_origen AS id_documento,
       numero_comprobante, fecha_asiento, total_debe, tipo_ambiente, motivo
FROM tmp_asientos_huerfanos
ORDER BY id_empresa, modulo_origen, id_asiento;

-- 1c. Total general.
SELECT COUNT(*) AS asientos_a_anular, SUM(total_debe) AS monto_total
FROM tmp_asientos_huerfanos;


-- ============================================================================
--  PASO 2 — ANULAR   (revisá el PASO 1 antes de correr esto)
-- ============================================================================

BEGIN;

-- 2a. Cabecera — mismos campos que AuditoriaContableRepository::anularAsiento().
UPDATE asientos_contables_cabecera a
   SET eliminado  = true,
       estado     = 'anulado',
       deleted_at = CURRENT_TIMESTAMP, deleted_by = (SELECT uid FROM tmp_config),
       updated_at = CURRENT_TIMESTAMP, updated_by = (SELECT uid FROM tmp_config)
 WHERE a.id IN (SELECT id_asiento FROM tmp_asientos_huerfanos)
   AND a.eliminado = false;

-- 2b. Detalle del asiento.
UPDATE asientos_contables_detalle d
   SET eliminado  = true,
       deleted_at = CURRENT_TIMESTAMP, deleted_by = (SELECT uid FROM tmp_config),
       updated_at = CURRENT_TIMESTAMP, updated_by = (SELECT uid FROM tmp_config)
 WHERE d.id_asiento IN (SELECT id_asiento FROM tmp_asientos_huerfanos)
   AND d.eliminado = false;

-- 2c. Seguimiento del costeo de ventas. La app lo limpia aparte
--     (AuditoriaContableService::limpiarSeguimientoCosteo); sin esto quedarían filas
--     apuntando a un asiento ya anulado. Solo aplica a estos 3 orígenes.
UPDATE ventas_costeo_seguimiento s
   SET eliminado  = true,
       deleted_at = CURRENT_TIMESTAMP, deleted_by = (SELECT uid FROM tmp_config),
       updated_at = CURRENT_TIMESTAMP, updated_by = (SELECT uid FROM tmp_config)
 WHERE s.eliminado = false
   AND EXISTS (
        SELECT 1 FROM tmp_asientos_huerfanos h
        WHERE h.id_empresa = s.id_empresa
          AND h.id_referencia_origen = s.id_documento
          AND s.tipo_documento = CASE h.modulo_origen
                                     WHEN 'factura_venta' THEN 'factura_venta'
                                     WHEN 'recibo_venta'  THEN 'recibo_venta'
                                     WHEN 'nota_credito'  THEN 'nota_credito_venta'
                                 END
   );

-- Revisá los conteos de arriba y decidí:
COMMIT;
-- ROLLBACK;


-- ============================================================================
--  PASO 3 — VERIFICAR
-- ============================================================================

-- 3a. Debe devolver 0: ya no queda ningún huérfano de los detectados.
SELECT COUNT(*) AS huerfanos_restantes
FROM asientos_contables_cabecera a
WHERE a.id IN (SELECT id_asiento FROM tmp_asientos_huerfanos)
  AND a.eliminado = false;

-- 3b. Cómo quedaron (deben estar todos eliminado=t / estado=anulado).
SELECT a.estado, a.eliminado, COUNT(*) AS n
FROM asientos_contables_cabecera a
WHERE a.id IN (SELECT id_asiento FROM tmp_asientos_huerfanos)
GROUP BY a.estado, a.eliminado;

-- 3c. Ningún detalle vivo colgando de un asiento anulado.
SELECT COUNT(*) AS detalles_vivos_de_asientos_anulados
FROM asientos_contables_detalle d
JOIN asientos_contables_cabecera a ON a.id = d.id_asiento
WHERE a.eliminado = true AND d.eliminado = false;

-- 3d. Cuadre del Balance: recalcular después de esto.
--     Los reportes ya filtran estado = 'contabilizado', así que el efecto es inmediato.


-- ============================================================================
--  NOTAS
-- ============================================================================
-- • Este script NO filtra por tipo_ambiente, a diferencia de la pantalla de
--   Auditoría Contable, que solo ve el ambiente activo de cada empresa. Un asiento
--   huérfano de otro ambiente es basura igual, pero por eso el PASO 1 muestra la
--   columna tipo_ambiente: si preferís limitarte a lo que vería la pantalla, agregá
--   al WHERE del DO block:
--       AND CAST(a.tipo_ambiente AS VARCHAR(1)) =
--           (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = a.id_empresa)
-- • Los asientos 'manual' y 'migracion' nunca se tocan.
-- • Un asiento ya 'anulado' no se vuelve a tocar (el WHERE lo excluye), así que el
--   script es idempotente: correrlo dos veces no cambia nada la segunda vez.
-- • Las tablas temporales (tmp_config, tmp_origen_tabla, tmp_asientos_huerfanos) viven
--   solo mientras dure la conexión. Si el cliente la cierra o reconecta entre pasos,
--   hay que volver a correr desde el PASO 0.
