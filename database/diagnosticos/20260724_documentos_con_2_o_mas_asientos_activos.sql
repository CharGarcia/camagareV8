-- Diagnóstico: documentos que tienen MÁS DE UN asiento contable activo.
-- Regla del sistema: un documento debe tener a lo sumo UN asiento "vivo"
-- (eliminado=false AND estado <> 'anulado'). Un asiento anulado no cuenta
-- como duplicado: es el rastro correcto de una reversión.
--
-- OJO con 'migracion': a diferencia de los módulos operativos (que usan un
-- modulo_origen propio por tabla: 'factura_venta', 'nota_credito',
-- 'retencion_venta', 'compra', etc., donde (modulo_origen, id_referencia_origen)
-- SÍ identifica un único documento), los documentos migrados comparten TODOS
-- el mismo modulo_origen='migracion', mientras que id_referencia_origen es el id
-- real de la fila en su tabla nueva de Postgres (que empieza en 1 en cada tabla:
-- notas_credito_cabecera, retencion_venta_cabecera, ingresos_cabecera, etc.).
-- Por eso, agrupar 'migracion' solo por id_referencia_origen produce falsos
-- positivos masivos (una NC con id=24 y una Retención con id=24 no son el mismo
-- documento). Para 'migracion' la clave real y única es numero_comprobante
-- (código legado con prefijo por tipo: NCV..., RETVEN..., VE..., etc.),
-- confirmado en MigracionMysqlService::migrarContabilidad().
--
-- Uso: ejecutar tal cual (solo lectura). Si ambos SELECT devuelven 0 filas,
-- no hay duplicados activos reales.

-- 1a) Duplicados en documentos NO migrados (clave: modulo_origen + id_referencia_origen)
SELECT
    ac.id_empresa,
    ac.modulo_origen,
    ac.id_referencia_origen,
    COUNT(*)                                                AS asientos_activos,
    STRING_AGG(ac.id::text, ', ' ORDER BY ac.id)             AS ids_asiento,
    STRING_AGG(ac.numero_comprobante, ', ' ORDER BY ac.id)   AS numeros_comprobante,
    SUM(ac.total_debe)                                       AS suma_total_debe
FROM asientos_contables_cabecera ac
WHERE ac.eliminado = false
  AND ac.estado <> 'anulado'
  AND ac.id_referencia_origen IS NOT NULL
  AND ac.modulo_origen <> 'migracion'
GROUP BY ac.id_empresa, ac.modulo_origen, ac.id_referencia_origen
HAVING COUNT(*) > 1
ORDER BY ac.id_empresa, ac.modulo_origen, ac.id_referencia_origen;

-- 1b) Duplicados en documentos MIGRADOS (clave: modulo_origen + numero_comprobante)
SELECT
    ac.id_empresa,
    ac.modulo_origen,
    ac.numero_comprobante,
    COUNT(*)                                       AS asientos_activos,
    STRING_AGG(ac.id::text, ', ' ORDER BY ac.id)    AS ids_asiento,
    SUM(ac.total_debe)                              AS suma_total_debe
FROM asientos_contables_cabecera ac
WHERE ac.eliminado = false
  AND ac.estado <> 'anulado'
  AND ac.modulo_origen = 'migracion'
GROUP BY ac.id_empresa, ac.modulo_origen, ac.numero_comprobante
HAVING COUNT(*) > 1
ORDER BY ac.id_empresa, ac.numero_comprobante;

-- 2) Detalle fila por fila de los documentos NO migrados encontrados en 1a
--    (para inspeccionar antes de decidir qué anular)
SELECT ac.*
FROM asientos_contables_cabecera ac
WHERE ac.eliminado = false
  AND ac.estado <> 'anulado'
  AND ac.id_referencia_origen IS NOT NULL
  AND ac.modulo_origen <> 'migracion'
  AND (ac.id_empresa, ac.modulo_origen, ac.id_referencia_origen) IN (
        SELECT id_empresa, modulo_origen, id_referencia_origen
        FROM asientos_contables_cabecera
        WHERE eliminado = false AND estado <> 'anulado'
          AND id_referencia_origen IS NOT NULL AND modulo_origen <> 'migracion'
        GROUP BY id_empresa, modulo_origen, id_referencia_origen
        HAVING COUNT(*) > 1
  )
ORDER BY ac.id_empresa, ac.modulo_origen, ac.id_referencia_origen, ac.id;

-- 3) Detalle fila por fila de los documentos MIGRADOS encontrados en 1b
SELECT ac.*
FROM asientos_contables_cabecera ac
WHERE ac.eliminado = false
  AND ac.estado <> 'anulado'
  AND ac.modulo_origen = 'migracion'
  AND (ac.id_empresa, ac.numero_comprobante) IN (
        SELECT id_empresa, numero_comprobante
        FROM asientos_contables_cabecera
        WHERE eliminado = false AND estado <> 'anulado' AND modulo_origen = 'migracion'
        GROUP BY id_empresa, numero_comprobante
        HAVING COUNT(*) > 1
  )
ORDER BY ac.id_empresa, ac.numero_comprobante, ac.id;
