-- ============================================================================
-- DIAGNÓSTICO (solo lectura): ventas del punto de venta que quedaron SIN su
-- Ingreso, es decir con la Cuenta por Cobrar abierta aunque el dinero se cobró.
--
-- No modifica nada. Ejecutar en producción y compartir el resultado.
--
-- Cambiar el 8 por el id de la empresa a revisar (o quitar la condición para
-- verlas todas).
-- ============================================================================

-- ── 1. FACTURAS del POS sin Ingreso ─────────────────────────────────────────
-- id_caja_sesion IS NOT NULL = se emitió desde un punto de venta (mostrador o
-- comandas); una factura hecha a mano desde el módulo no lleva turno de caja.
SELECT 'FACTURA' AS tipo,
       v.id                                   AS id_documento,
       v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero,
       v.fecha_emision,
       v.importe_total,
       v.estado,
       v.id_caja_sesion,
       g.id                                   AS grupo_comanda,
       g.origen                               AS origen_comanda,
       g.forma_pago                           AS codigo_sri_guardado,
       g.id_forma_pago_sugerida
  FROM ventas_cabecera v
  LEFT JOIN comanda_grupos_cobro g
         ON g.id_documento = v.id AND g.tipo_documento = 'FACTURA' AND g.eliminado = false
 WHERE v.id_empresa = 8
   AND v.eliminado = false
   AND v.id_caja_sesion IS NOT NULL
   AND NOT EXISTS (
         SELECT 1 FROM ingresos_detalle d
          WHERE d.id_referencia_documento = v.id AND d.tipo_documento = 'FACTURA'
       )
 ORDER BY v.fecha_emision DESC, v.id DESC;

-- ── 2. RECIBOS del POS sin Ingreso ──────────────────────────────────────────
SELECT 'RECIBO' AS tipo,
       r.id                AS id_documento,
       r.recibo_numero     AS numero,
       r.fecha_emision,
       r.importe_total,
       r.estado,
       r.id_caja_sesion,
       g.id                AS grupo_comanda,
       g.origen            AS origen_comanda,
       g.forma_pago        AS codigo_sri_guardado
  FROM recibos_venta_cabecera r
  LEFT JOIN comanda_grupos_cobro g
         ON g.id_documento = r.id AND g.tipo_documento = 'RECIBO' AND g.eliminado = false
 WHERE r.id_empresa = 8
   AND r.eliminado = false
   AND r.id_caja_sesion IS NOT NULL
   AND NOT EXISTS (
         SELECT 1 FROM ingresos_detalle d
          WHERE d.id_referencia_documento = r.id AND d.tipo_documento = 'RECIBO'
       )
 ORDER BY r.fecha_emision DESC, r.id DESC;

-- ── 3. Resumen: cuántos hay y desde cuándo ──────────────────────────────────
SELECT 'FACTURA' AS tipo, COUNT(*) AS sin_ingreso,
       MIN(v.fecha_emision) AS desde, MAX(v.fecha_emision) AS hasta,
       SUM(v.importe_total) AS monto_total
  FROM ventas_cabecera v
 WHERE v.id_empresa = 8 AND v.eliminado = false AND v.id_caja_sesion IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM ingresos_detalle d
                    WHERE d.id_referencia_documento = v.id AND d.tipo_documento = 'FACTURA')
UNION ALL
SELECT 'RECIBO', COUNT(*), MIN(r.fecha_emision), MAX(r.fecha_emision), SUM(r.importe_total)
  FROM recibos_venta_cabecera r
 WHERE r.id_empresa = 8 AND r.eliminado = false AND r.id_caja_sesion IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM ingresos_detalle d
                    WHERE d.id_referencia_documento = r.id AND d.tipo_documento = 'RECIBO');

-- ── 4. ¿Vienen de comandas o del mostrador? ─────────────────────────────────
-- Si 'grupo_comanda' viene con valor en las consultas 1/2, ese cobro salió del
-- salón (comandas) y encaja con el bug del id de forma de pago que se corrigió.
-- Si viene NULL, salió del POS mostrador y la causa es otra: hay que mirar el
-- error_log del servidor buscando '[PosVentaService]'.
