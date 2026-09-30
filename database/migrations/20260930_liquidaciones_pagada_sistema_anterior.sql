-- ============================================================================
-- Liquidaciones de compra "pagadas en el sistema anterior"
-- ----------------------------------------------------------------------------
-- Contexto:
--   El sistema anterior no registraba pagos de liquidaciones de compra hasta
--   2020. Al migrarlas, las de esos años salían "pendientes de pago" en
--   Liquidaciones, Cuentas por pagar, Egresos (pendientes de pago), la ficha del
--   proveedor y el Reporte de cartera, aunque estaban pagadas.
--   Se agrega una marca en la liquidación: con ella, todo cálculo de saldo la
--   trata como saldo 0 (App\Helpers\LiquidacionPagoAnterior). NO crea egresos, no
--   mueve caja ni bancos y no toca asientos, declaraciones ni ATS.
--
-- Qué hace:
--   1. Agrega la columna liquidaciones_cabecera.pagada_sistema_anterior
--      (boolean, false por defecto). Idempotente.
--   2. Marca, en TODAS las empresas, las liquidaciones que cumplen:
--        - las insertó la migración (migracion_mysql_map, entidad 'liquidaciones',
--          vinculado = false) — nunca las creadas en el sistema nuevo ni las
--          vinculadas a una ya existente;
--        - fecha de emisión hasta el 31-12-2020;
--        - vigentes (no anuladas/rechazadas);
--        - con saldo pendiente, total o parcial (total − pagos de egresos no
--          anulados − retenciones no anuladas > 0).
--      Es lo mismo que hace la migración al ejecutar "Liquidaciones de compra";
--      este paso evita tener que volver a migrarlas. Idempotente (0 la 2.ª vez).
--
-- Toca datos: sí (solo la columna nueva, updated_at y updated_by).
-- Orden: ejecutar ANTES de desplegar el código (el código funciona sin la
-- columna, pero entonces no marca nada).
--
-- Reversible:
--   UPDATE liquidaciones_cabecera SET pagada_sistema_anterior = false
--    WHERE pagada_sistema_anterior = true;            -- quita la marca
--   ALTER TABLE liquidaciones_cabecera DROP COLUMN IF EXISTS pagada_sistema_anterior;
--   (el DROP solo si también se retira el código que la usa).
-- ============================================================================

ALTER TABLE liquidaciones_cabecera
    ADD COLUMN IF NOT EXISTS pagada_sistema_anterior BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN liquidaciones_cabecera.pagada_sistema_anterior IS
    'Liquidación migrada (hasta 2020) pagada en el sistema anterior, que no registraba sus pagos: todo cálculo de saldo la trata como saldo 0. Ver App\Helpers\LiquidacionPagoAnterior.';

UPDATE liquidaciones_cabecera l
   SET pagada_sistema_anterior = true,
       updated_at = now(),
       updated_by = 2                         -- << usuario que ejecuta (producción: 2)
 WHERE l.eliminado = false
   AND l.pagada_sistema_anterior = false
   AND l.fecha_emision <= DATE '2020-12-31'
   AND UPPER(TRIM(COALESCE(l.estado, ''))) IN ('AUTORIZADO', 'AUTORIZADA', 'APROBADO', 'APROBADA', 'CONTABILIZADO', 'CONTABILIZADA')  -- vigentes (TiposComprobanteCompra)
   AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                WHERE m.id_empresa = l.id_empresa AND m.entidad = 'liquidaciones'
                  AND m.id_destino = l.id AND m.vinculado = false)
   AND ROUND(l.importe_total
       - COALESCE((SELECT SUM(ed.monto_pagado) FROM egresos_detalle ed
                     JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento = 'LIQUIDACION' AND ed.id_referencia_documento = l.id
                      AND ed.eliminado = false AND ec.eliminado = false AND ec.estado <> 'anulado'), 0)
       - COALESCE((SELECT SUM(r.total_retenido) FROM retencion_compra_cabecera r
                    WHERE r.id_liquidacion = l.id AND r.id_empresa = l.id_empresa
                      AND r.eliminado = false AND r.estado <> 'anulada'), 0), 2) > 0;

-- Comprobación (ejecutar aparte): liquidaciones marcadas por empresa.
-- SELECT id_empresa, COUNT(*) AS marcadas, SUM(importe_total) AS total
--   FROM liquidaciones_cabecera
--  WHERE pagada_sistema_anterior = true AND eliminado = false
--  GROUP BY id_empresa ORDER BY id_empresa;
