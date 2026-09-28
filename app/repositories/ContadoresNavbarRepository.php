<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Contadores de los badges del navbar (facturas/pedidos/... en borrador o pendientes).
 *
 * Todo el conteo por empresa se resuelve en UNA sola consulta (subconsultas
 * escalares), para no pegarle a la base 10 veces por cada refresco del navbar.
 * El conteo de tareas es global por usuario y vive en TareaRepository (se reutiliza).
 */
class ContadoresNavbarRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Todos los contadores por empresa en una sola consulta.
     *
     * Claves devueltas: facturas_borrador, liquidaciones_borrador,
     * retenciones_compras_borrador, notas_credito_borrador, guias_remision_borrador,
     * ordenes_compra_borrador, pedidos_pendientes, factura_express_pendientes,
     * whatsapp_unread.
     *
     * @return array<string,int>
     */
    public function getConteosEmpresa(int $idEmpresa): array
    {
        // CTE 'amb': el tipo_ambiente actual de la empresa (mismo criterio que los
        // endpoints originales). Las tablas con comprobante electrónico filtran por él;
        // factura_express y whatsapp no lo usan.
        $sql = "
            WITH amb AS (
                SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e
            )
            SELECT
              (SELECT COUNT(*) FROM ventas_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS facturas_borrador,
              (SELECT COUNT(*) FROM liquidaciones_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS liquidaciones_borrador,
              (SELECT COUNT(*) FROM retencion_compra_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS retenciones_compras_borrador,
              (SELECT COUNT(*) FROM notas_credito_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS notas_credito_borrador,
              (SELECT COUNT(*) FROM guias_remision_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS guias_remision_borrador,
              (SELECT COUNT(*) FROM ordenes_compra x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS ordenes_compra_borrador,
              (SELECT COUNT(*) FROM pedidos_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'Pendiente' AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS pedidos_pendientes,
              (SELECT COUNT(*) FROM factura_express_solicitudes x
                 WHERE x.id_empresa = :e AND x.estado = 'pendiente' AND x.eliminado = false)                             AS factura_express_pendientes,
              (SELECT COUNT(*) FROM whatsapp_chats x
                 WHERE x.id_empresa = :e AND x.mensajes_sin_leer > 0)                                                    AS whatsapp_unread
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($row as $k => $v) {
            $row[$k] = (int) $v;
        }

        return $row;
    }

    /**
     * Documentos con NOVEDAD del SRI (devueltos / no autorizados / con error) por tipo.
     *
     * Fuente de verdad: la tabla `sri_envio_log` (la misma que alimenta el seguimiento
     * de la pestaña SRI). Se toma el ÚLTIMO `accion` por comprobante (en el ambiente
     * actual de la empresa); si ese estado es de fallo y el documento sigue vigente
     * (no eliminado), cuenta. Así, un documento corregido y reautorizado deja de contar.
     *
     * Los valores de tipo_comprobante y tabla son constantes internas (no entrada de
     * usuario), por lo que interpolarlos en el SQL es seguro.
     *
     * @return array<string,int>  claves: facturas, liquidaciones, retenciones_compras, notas_credito, guias_remision
     */
    public function getNovedadesSri(int $idEmpresa): array
    {
        $tipos = [
            'facturas'            => ['tipo' => 'factura_venta',      'tabla' => 'ventas_cabecera'],
            'liquidaciones'       => ['tipo' => 'liquidacion_compra', 'tabla' => 'liquidaciones_cabecera'],
            'retenciones_compras' => ['tipo' => 'retencion_compra',   'tabla' => 'retencion_compra_cabecera'],
            'notas_credito'       => ['tipo' => 'nota_credito',       'tabla' => 'notas_credito_cabecera'],
            'guias_remision'      => ['tipo' => 'guia_remision',      'tabla' => 'guias_remision_cabecera'],
            // Futuro (cuando exista el módulo): 'notas_debito' => ['tipo' => 'nota_debito', 'tabla' => 'notas_debito_cabecera'],
        ];

        $selects = [];
        foreach ($tipos as $key => $cfg) {
            $tipo  = $cfg['tipo'];   // constante interna
            $tabla = $cfg['tabla'];  // constante interna
            $selects[] = "
              (SELECT COUNT(*) FROM (
                  SELECT DISTINCT ON (l.id_comprobante) l.id_comprobante, l.accion
                  FROM sri_envio_log l
                  WHERE l.id_empresa = :e AND l.tipo_comprobante = '$tipo' AND l.tipo_ambiente = (SELECT t FROM amb)
                  ORDER BY l.id_comprobante, l.id DESC
               ) u
               JOIN $tabla d ON d.id = u.id_comprobante AND d.eliminado = false
               WHERE u.accion IN ('devuelta','no_autorizado','no_autorizada','error')) AS $key";
        }

        $sql = "WITH amb AS (SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e)
                SELECT " . implode(",\n", $selects);

        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($row as $k => $v) {
            $row[$k] = (int) $v;
        }

        return $row;
    }

    /**
     * Días restantes de vigencia de la suscripción del sistema de la empresa ACTIVA.
     *
     * Mismo criterio que la tarjeta "Suscripción y Vigencia" de modulos/empresa:
     * usa `proximo_cobro` de la suscripción vinculada (cruce por RUC contra la empresa
     * controladora); si no hay suscripción vinculada, cae a `periodo_vigencia_hasta`.
     *
     * @return array{dias:int,meses:?int}|null  Días restantes (negativo = vencida) y los
     *         meses de periodicidad de la suscripción (null = fallback manual, sin periodicidad).
     *         null si no hay dato/columnas.
     */
    public function getDiasVigenciaSuscripcion(int $idEmpresa): ?array
    {
        // Datos de la empresa (defensivo: las columnas pueden no existir si falta la migración).
        try {
            $st = $this->db->prepare(
                "SELECT ruc, id_empresa_suscripciones, periodo_vigencia_hasta
                 FROM empresas WHERE id = :e"
            );
            $st->execute([':e' => $idEmpresa]);
            $emp = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$emp) {
            return null;
        }

        $fechaObjetivo = null;
        $meses         = null;

        // 1) Suscripción vinculada: la de próximo cobro más cercano (activa) en la controladora,
        //    con su periodicidad para poder escalar el umbral del aviso.
        $idCtrl = (int) ($emp['id_empresa_suscripciones'] ?? 0);
        $ruc    = trim((string) ($emp['ruc'] ?? ''));
        if ($idCtrl > 0 && $ruc !== '') {
            try {
                $s = $this->db->prepare(
                    "SELECT s.proximo_cobro, per.meses AS meses
                     FROM suscripciones s
                     JOIN clientes c ON c.id = s.id_cliente
                     LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                     WHERE s.id_empresa = :ctrl AND s.eliminado = false AND c.eliminado = false
                       AND c.identificacion = :ruc AND s.estado = 'activo' AND s.proximo_cobro IS NOT NULL
                     ORDER BY s.proximo_cobro ASC
                     LIMIT 1"
                );
                $s->execute([':ctrl' => $idCtrl, ':ruc' => $ruc]);
                $row = $s->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['proximo_cobro'])) {
                    $fechaObjetivo = (string) $row['proximo_cobro'];
                    $meses = isset($row['meses']) && $row['meses'] !== null ? (int) $row['meses'] : null;
                }
            } catch (\Throwable $e) {
                // Módulo de suscripciones no disponible: se intenta el fallback.
            }
        }

        // 2) Fallback manual: periodo_vigencia_hasta de la empresa (sin periodicidad).
        if ($fechaObjetivo === null && !empty($emp['periodo_vigencia_hasta'])) {
            $fechaObjetivo = (string) $emp['periodo_vigencia_hasta'];
            $meses = null;
        }

        if ($fechaObjetivo === null) {
            return null;
        }

        $t2 = strtotime($fechaObjetivo);
        if ($t2 === false) {
            return null;
        }

        return [
            // Mismo cálculo que la vista (ceil de la diferencia contra "ahora").
            'dias'  => (int) ceil(($t2 - time()) / 86400),
            'meses' => $meses,
        ];
    }

    /**
     * Alerta de SUSCRIPCIONES para la empresa ADMINISTRADORA (la que vende/gestiona las
     * suscripciones de sus clientes: `suscripciones.id_empresa` = empresa activa).
     *
     * Cuenta, sobre las suscripciones ACTIVAS, cuántas están vencidas (próximo cobro ya
     * pasó) y cuántas por vencer (próximo cobro dentro de $dias). Si la empresa activa no
     * gestiona suscripciones, ambos contadores dan 0 y no se muestra aviso.
     *
     * @return array{vencidas:int,por_vencer:int}
     */
    public function getAlertaSuscripciones(int $idEmpresa, int $dias): array
    {
        $sql = "SELECT
                  COUNT(*) FILTER (WHERE s.proximo_cobro <  CURRENT_DATE) AS vencidas,
                  COUNT(*) FILTER (WHERE s.proximo_cobro >= CURRENT_DATE
                                     AND s.proximo_cobro <= CURRENT_DATE + CAST(:dias AS INTEGER)) AS por_vencer
                FROM suscripciones s
                WHERE s.id_empresa = :e
                  AND s.eliminado = false
                  AND s.estado = 'activo'
                  AND s.proximo_cobro IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':dias' => $dias]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'vencidas'   => (int) ($row['vencidas'] ?? 0),
            'por_vencer' => (int) ($row['por_vencer'] ?? 0),
        ];
    }

    /**
     * Cheques POSFECHADOS pendientes de cobro, recibidos (ingresos) y emitidos (egresos).
     *
     * Por grupo devuelve dos cortes:
     *   - listos:     la fecha del cheque ya llegó (sin importar hace cuánto)
     *                 y todavía no tiene Fecha Banco → hay que depositarlo / lo pueden cobrar.
     *   - por_vencer: la fecha cae entre mañana y hoy + $diasAdelante.
     *
     * Mismos criterios que el modal "Cheques Posfechados" de Control Bancario
     * (ControlBancarioRepository::getChequesPosfechados con no cobrados), pero leídos
     * directo de los cobros/pagos: aquella consulta recorre los asientos y es demasiado
     * pesada para el sondeo del navbar. "Posfechado" = fecha del cheque posterior a la
     * del documento (un cheque al día no cuenta); solo cuentas bancarias (id_banco).
     * Los ingresos/egresos MIGRADOS del sistema anterior (fila en migracion_mysql_map) no
     * cuentan: el aviso es solo para los cheques registrados en este sistema — muchos
     * migrados nunca se conciliaron y dejarían el aviso encendido sin motivo.
     * "Cobrado" = tiene Fecha Banco en control_bancario_movimientos por cualquiera de sus
     * dos anclajes: el cobro/pago (cuentas sin contabilidad) o la línea del asiento.
     *
     * @return array{recibidos:array{listos:int,monto_listos:float,por_vencer:int,monto_por_vencer:float},
     *               emitidos:array{listos:int,monto_listos:float,por_vencer:int,monto_por_vencer:float}}
     */
    public function getChequesPosfechados(int $idEmpresa, int $diasAdelante): array
    {
        $sql = "WITH amb AS (
                    SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e
                ),
                ch AS (
                    SELECT 'recibidos' AS grupo, ip.monto, COALESCE(cbm.fecha_cheque, ip.fecha_cobro) AS fecha
                    FROM ingresos_cabecera ic
                    INNER JOIN ingresos_pagos ip ON ip.id_ingreso = ic.id
                    INNER JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
                        AND fp.eliminado = FALSE AND fp.id_banco IS NOT NULL
                    LEFT JOIN control_bancario_movimientos cbm
                        ON cbm.origen_tipo = 'ingreso' AND cbm.origen_id = ip.id AND cbm.eliminado = FALSE
                    WHERE ic.id_empresa = :e
                      AND ic.eliminado = FALSE
                      AND COALESCE(ic.estado, 'registrado') <> 'anulado'
                      AND ic.tipo_ambiente = (SELECT t FROM amb)
                      AND ip.fecha_cobro IS NOT NULL
                      AND COALESCE(cbm.fecha_cheque, ip.fecha_cobro)
                          <= CURRENT_DATE + CAST(:adelante AS INTEGER)
                      AND COALESCE(cbm.fecha_cheque, ip.fecha_cobro) > ic.fecha_emision
                      AND NOT EXISTS (
                            SELECT 1 FROM migracion_mysql_map mm
                            WHERE mm.id_empresa = :e AND mm.entidad = 'ingresos' AND mm.id_destino = ic.id
                      )
                      AND COALESCE(cbm.tipo_transaccion, UPPER(NULLIF(ip.tipo_operacion_bancaria, '')),
                                   CASE fp.tipo WHEN 'CHEQUE' THEN 'CHEQUE' END) = 'CHEQUE'
                      AND cbm.fecha_banco IS NULL
                      AND NOT EXISTS (
                            SELECT 1
                            FROM asientos_contables_cabecera acc
                            JOIN asientos_contables_detalle acd ON acd.id_asiento = acc.id AND acd.eliminado = FALSE
                            JOIN control_bancario_movimientos cba ON cba.id_asiento_detalle = acd.id AND cba.eliminado = FALSE
                            WHERE acc.id_empresa = :e
                              AND UPPER(acc.tipo_comprobante) = 'INGRESOS'
                              AND acc.id_referencia_origen = ic.id
                              AND acc.eliminado = FALSE
                              AND acd.id_cuenta_contable = fp.id_cuenta_contable
                              AND cba.fecha_banco IS NOT NULL
                      )

                    UNION ALL

                    SELECT 'emitidos', ep.monto, COALESCE(cbm.fecha_cheque, ep.fecha_cobro)
                    FROM egresos_cabecera ec
                    INNER JOIN egresos_pagos ep ON ep.id_egreso = ec.id
                    INNER JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
                        AND fp.eliminado = FALSE AND fp.id_banco IS NOT NULL
                    LEFT JOIN control_bancario_movimientos cbm
                        ON cbm.origen_tipo = 'egreso' AND cbm.origen_id = ep.id AND cbm.eliminado = FALSE
                    WHERE ec.id_empresa = :e
                      AND ec.eliminado = FALSE
                      AND COALESCE(ep.eliminado, FALSE) = FALSE
                      AND COALESCE(ec.estado, 'registrado') <> 'anulado'
                      AND COALESCE(ep.estado_cheque, 'vigente') <> 'anulado'
                      AND ec.tipo_ambiente = (SELECT t FROM amb)
                      AND ep.fecha_cobro IS NOT NULL
                      AND COALESCE(cbm.fecha_cheque, ep.fecha_cobro)
                          <= CURRENT_DATE + CAST(:adelante AS INTEGER)
                      AND COALESCE(cbm.fecha_cheque, ep.fecha_cobro) > ec.fecha_emision
                      AND NOT EXISTS (
                            SELECT 1 FROM migracion_mysql_map mm
                            WHERE mm.id_empresa = :e AND mm.entidad = 'egresos' AND mm.id_destino = ec.id
                      )
                      AND COALESCE(cbm.tipo_transaccion, UPPER(NULLIF(ep.tipo_operacion_bancaria, '')),
                                   CASE fp.tipo WHEN 'CHEQUE' THEN 'CHEQUE' END) = 'CHEQUE'
                      AND cbm.fecha_banco IS NULL
                      AND NOT EXISTS (
                            SELECT 1
                            FROM asientos_contables_cabecera acc
                            JOIN asientos_contables_detalle acd ON acd.id_asiento = acc.id AND acd.eliminado = FALSE
                            JOIN control_bancario_movimientos cba ON cba.id_asiento_detalle = acd.id AND cba.eliminado = FALSE
                            WHERE acc.id_empresa = :e
                              AND UPPER(acc.tipo_comprobante) = 'EGRESOS'
                              AND acc.id_referencia_origen = ec.id
                              AND acc.eliminado = FALSE
                              AND acd.id_cuenta_contable = fp.id_cuenta_contable
                              AND cba.fecha_banco IS NOT NULL
                      )
                )
                SELECT grupo,
                       COUNT(*) FILTER (WHERE fecha <= CURRENT_DATE)                    AS listos,
                       COALESCE(SUM(monto) FILTER (WHERE fecha <= CURRENT_DATE), 0)    AS monto_listos,
                       COUNT(*) FILTER (WHERE fecha >  CURRENT_DATE)                    AS por_vencer,
                       COALESCE(SUM(monto) FILTER (WHERE fecha >  CURRENT_DATE), 0)    AS monto_por_vencer
                FROM ch
                GROUP BY grupo";

        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':adelante' => $diasAdelante]);

        $vacio = ['listos' => 0, 'monto_listos' => 0.0, 'por_vencer' => 0, 'monto_por_vencer' => 0.0];
        $out = ['recibidos' => $vacio, 'emitidos' => $vacio];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[$r['grupo']] = [
                'listos'           => (int) $r['listos'],
                'monto_listos'     => round((float) $r['monto_listos'], 2),
                'por_vencer'       => (int) $r['por_vencer'],
                'monto_por_vencer' => round((float) $r['monto_por_vencer'], 2),
            ];
        }
        return $out;
    }

    /**
     * Estado de la FIRMA ELECTRÓNICA vigente (es_activo) de la empresa activa.
     *
     * Usa la misma firma que el sistema emplea para firmar (empresa_firma con
     * es_activo = true, la de expiración más lejana si hubiera varias).
     *
     * @return array{sin_firma:bool}|array{dias:int}|null
     *   - ['sin_firma' => true]  → no hay ninguna firma vigente instalada.
     *   - ['dias' => int]        → hay firma vigente; días restantes (negativo = caducada).
     *   - null                   → firma vigente sin fecha de expiración, o tabla no disponible
     *                              (no se puede/ no procede avisar).
     */
    public function getEstadoFirma(int $idEmpresa): ?array
    {
        try {
            $st = $this->db->prepare(
                "SELECT fecha_expiracion
                 FROM empresa_firma
                 WHERE id_empresa = :e AND es_activo = TRUE AND eliminado = FALSE
                 ORDER BY fecha_expiracion DESC NULLS LAST, created_at DESC
                 LIMIT 1"
            );
            $st->execute([':e' => $idEmpresa]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null; // tabla/columnas no disponibles → no avisar
        }

        // Sin firma vigente instalada (ninguna es_activo).
        if ($row === false) {
            return ['sin_firma' => true];
        }

        $fecha = $row['fecha_expiracion'] ?? null;
        if (empty($fecha)) {
            return null; // firma activa sin fecha → no se puede calcular
        }
        $t2 = strtotime((string) $fecha);
        if ($t2 === false) {
            return null;
        }

        return ['dias' => (int) ceil(($t2 - time()) / 86400)];
    }
}
