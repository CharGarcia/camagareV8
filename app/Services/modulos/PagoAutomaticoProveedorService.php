<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\NumeroCheque;
use App\Services\LogSistemaService;
use PDO;

/**
 * Generación automática del egreso (pago) de una compra a partir de la
 * configuración de la pestaña Pagos del proveedor.
 *
 * Se usa desde dos flujos:
 *  1. En caliente, al registrar una compra descargada del SRI
 *     (DocumentoAutomatedRegisterService).
 *  2. En lote y hacia atrás, desde el botón "Generar pagos pendientes" del
 *     modal de proveedores, para las compras que quedaron sin pago.
 *
 * Diferencia intencional entre ambos: el flujo del SRI paga el total del
 * documento recién registrado y se omite si el proveedor tiene retenciones
 * configuradas (aún no existe la retención, así que pagar el total sería
 * incorrecto). El lote retroactivo paga el SALDO real de cada compra —el mismo
 * que muestra Cuentas por Pagar: total − retenido − NC + ND—, por lo que sí
 * funciona con proveedores que retienen.
 */
class PagoAutomaticoProveedorService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONFIGURACIÓN
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resuelve y valida la configuración de pago automático del proveedor.
     *
     * @param bool $bloquearPorRetenciones true en el flujo del SRI (paga el total).
     * @return array{ok:bool, motivo:?string, config:?array}
     */
    public function getConfiguracion(int $idProveedor, int $idEmpresa, bool $bloquearPorRetenciones = false): array
    {
        $stProv = $this->db->prepare("
            SELECT id_forma_pago_predeterminada, tipo_operacion_bancaria_predeterminada,
                   monto_minimo_auto_pago, monto_maximo_auto_pago,
                   plazo, unidad_tiempo,
                   id_retencion_renta, id_retencion_iva, id_egreso_concepto_predeterminado
            FROM proveedores
            WHERE id = ? AND id_empresa = ? AND eliminado = false
            LIMIT 1
        ");
        $stProv->execute([$idProveedor, $idEmpresa]);
        $prov = $stProv->fetch(PDO::FETCH_ASSOC);

        if (!$prov || empty($prov['id_forma_pago_predeterminada'])) {
            return ['ok' => false, 'motivo' => null, 'config' => null]; // Sin configurar: se omite en silencio
        }

        // La forma de pago debe seguir activa: el flujo manual solo ofrece formas
        // activas (FormaPagoRepository::getFormasFiltradas) y el automático debe
        // respetar el mismo criterio.
        $stFp = $this->db->prepare("
            SELECT 1 FROM empresa_formas_pago
            WHERE id = ? AND id_empresa = ? AND activo = TRUE AND eliminado = FALSE
            LIMIT 1
        ");
        $stFp->execute([(int) $prov['id_forma_pago_predeterminada'], $idEmpresa]);
        if (!$stFp->fetchColumn()) {
            return ['ok' => false, 'motivo' => 'La forma de pago predeterminada del proveedor está inactiva o fue eliminada.', 'config' => null];
        }

        if ($bloquearPorRetenciones && (!empty($prov['id_retencion_renta']) || !empty($prov['id_retencion_iva']))) {
            return ['ok' => false, 'motivo' => 'El proveedor posee retenciones configuradas.', 'config' => null];
        }

        // Concepto de egreso predeterminado, o el de comportamiento COMPRA como respaldo
        $idEgresoConcepto = !empty($prov['id_egreso_concepto_predeterminado']) ? (int) $prov['id_egreso_concepto_predeterminado'] : null;
        if (!$idEgresoConcepto) {
            $stC = $this->db->prepare("
                SELECT id FROM empresa_opciones_ingreso_egreso
                WHERE id_empresa = ? AND aplica_egresos = TRUE AND comportamiento = 'COMPRA' AND eliminado = FALSE
                ORDER BY id ASC LIMIT 1
            ");
            $stC->execute([$idEmpresa]);
            $idEgresoConcepto = ($row = $stC->fetch(PDO::FETCH_ASSOC)) ? (int) $row['id'] : null;
        }
        if (!$idEgresoConcepto) {
            return ['ok' => false, 'motivo' => 'Se requiere un concepto de egreso para COMPRA.', 'config' => null];
        }

        return [
            'ok'     => true,
            'motivo' => null,
            'config' => [
                'id_forma_pago'      => (int) $prov['id_forma_pago_predeterminada'],
                'tipo_operacion'     => !empty($prov['tipo_operacion_bancaria_predeterminada']) ? (string) $prov['tipo_operacion_bancaria_predeterminada'] : null,
                'monto_minimo'       => !empty($prov['monto_minimo_auto_pago']) ? (float) $prov['monto_minimo_auto_pago'] : null,
                'monto_maximo'       => !empty($prov['monto_maximo_auto_pago']) ? (float) $prov['monto_maximo_auto_pago'] : null,
                'plazo'              => (int) ($prov['plazo'] ?? 0),
                'unidad_tiempo'      => (string) ($prov['unidad_tiempo'] ?? 'DIAS'),
                'id_egreso_concepto' => $idEgresoConcepto,
            ],
        ];
    }

    /**
     * Comprueba el rango de monto configurado. Devuelve null si pasa, o el
     * motivo del rechazo.
     */
    public function validarRango(array $config, float $monto): ?string
    {
        $min = $config['monto_minimo'];
        $max = $config['monto_maximo'];

        if ($min !== null && $min > 0.001 && $monto < ($min - 0.001)) {
            return '$' . number_format($monto, 2) . ' no alcanza el mínimo de $' . number_format($min, 2);
        }
        if ($max !== null && $max > 0.001 && $monto > ($max + 0.001)) {
            return '$' . number_format($monto, 2) . ' supera el límite de $' . number_format($max, 2);
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // GENERACIÓN DE UN EGRESO
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra el egreso que liquida una compra. Lanza excepción si falla.
     *
     * @return array{id_egreso:int, numero_egreso:string, numero_cheque:?string, fecha_cobro:string}
     */
    public function generarParaCompra(
        array $config,
        int $idCompra,
        int $idProveedor,
        int $idEmpresa,
        int $idUsuario,
        float $montoTotalDocumento,
        float $montoAPagar,
        string $numDocCompleto,
        string $fechaEmisionDoc,
        string $observaciones
    ): array {
        // Punto de emisión y establecimiento activos
        $stPto = $this->db->prepare("
            SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
            FROM empresa_punto_emision pe
            JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
            WHERE es.id_empresa = ? AND pe.eliminado = FALSE AND es.eliminado = FALSE
            ORDER BY es.codigo ASC, pe.codigo_punto ASC LIMIT 1
        ");
        $stPto->execute([$idEmpresa]);
        $pto = $stPto->fetch(PDO::FETCH_ASSOC);
        if (!$pto) {
            throw new \Exception('No se localizó punto de emisión activo.');
        }

        $idPunto  = (int) $pto['id_punto'];
        $idEstab  = (int) $pto['id_estab'];
        $codEstab = (string) $pto['estab'];
        $codPunto = (string) $pto['punto'];

        // Secuencial correlativo del sistema. Se abre la transacción ANTES de calcularlo y se
        // mantiene hasta el INSERT final (EgresoService::registrar()): el lock de
        // obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $managedTransaction = !$this->db->inTransaction();
        if ($managedTransaction) {
            $this->db->beginTransaction();
        }

        try {
        $secService = new \App\Services\SecuencialService();
        $resSec = $secService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
        if (empty($resSec['secuencial'])) {
            throw new \Exception('Error al reservar secuencial correlativo.');
        }
        $secuencial   = $resSec['formateado'] ?? str_pad((string) $resSec['secuencial'], 9, '0', STR_PAD_LEFT);
        $numeroEgreso = "{$codEstab}-{$codPunto}-{$secuencial}";

        $egresoRepo    = new \App\repositories\modulos\EgresoRepository();
        $egresoRules   = new \App\Rules\modulos\EgresoRules();
        $logService    = new LogSistemaService();
        $egresoService = new EgresoService($egresoRepo, $egresoRules, $logService);

        // Cheque: numeración correlativa y fecha de cobro diferida por los días de crédito
        $numeroCheque = null;
        $fechaCobro   = $fechaEmisionDoc;

        if (($config['tipo_operacion'] ?? null) === 'CHEQUE') {
            $numeroCheque = NumeroCheque::siguiente($egresoRepo->getUltimoNumeroCheque($config['id_forma_pago']));
            if ($numeroCheque === '') {
                throw new \Exception('No hay un número de cheque previo correlativo para la forma de pago; regístrelo manualmente.');
            }
            $fechaCobro = $this->calcularFechaCobroCredito(
                $fechaEmisionDoc,
                (int) ($config['plazo'] ?? 0),
                (string) ($config['unidad_tiempo'] ?? 'DIAS')
            );
        }

        $dataEgreso = [
            'id_empresa'         => $idEmpresa,
            'usuario_id'         => $idUsuario,
            'fecha_emision'      => $fechaEmisionDoc,
            'establecimiento'    => $codEstab,
            'punto_emision'      => $codPunto,
            'secuencial'         => $secuencial,
            'numero_egreso'      => $numeroEgreso,
            'id_punto_emision'   => $idPunto,
            'id_establecimiento' => $idEstab,
            'tipo_egreso'        => 'COMPRA',
            'tipo_sujeto'        => 'PROVEEDOR',
            'id_proveedor'       => $idProveedor,
            'id_egreso_concepto' => $config['id_egreso_concepto'],
            'monto_total'        => $montoAPagar,
            'observaciones'      => $observaciones,
            'estado'             => 'registrado',
            'detalles'           => [
                [
                    'tipo_documento'          => 'COMPRA',
                    'id_referencia_documento' => $idCompra,
                    'numero_documento'        => $numDocCompleto,
                    'descripcion'             => 'Liquidación de Compra #' . $numDocCompleto,
                    'monto_documento'         => $montoTotalDocumento,
                    'saldo_anterior'          => $montoAPagar,
                    'monto_pagado'            => $montoAPagar,
                    'saldo_actual'            => 0.0,
                ],
            ],
            'pagos' => [
                [
                    'id_forma_pago'           => $config['id_forma_pago'],
                    'monto'                   => $montoAPagar,
                    'tipo_operacion_bancaria' => $config['tipo_operacion'],
                    'numero_cheque'           => $numeroCheque,
                    'fecha_cobro'             => $fechaCobro,
                ],
            ],
        ];

        $idEgreso = $egresoService->registrar($dataEgreso);

        if ($managedTransaction) {
            $this->db->commit();
        }

        return [
            'id_egreso'     => $idEgreso,
            'numero_egreso' => $numeroEgreso,
            'numero_cheque' => $numeroCheque,
            'fecha_cobro'   => $fechaCobro,
        ];
        } catch (\Throwable $e) {
            if ($managedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // LOTE RETROACTIVO
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Facturas de compra del proveedor, emitidas hasta $fechaHasta, que aún no
     * tienen ningún egreso asociado. Incluye el saldo real de cada una.
     */
    public function getComprasPendientes(int $idProveedor, int $idEmpresa, string $fechaHasta): array
    {
        $params = [
            ':id_proveedor' => $idProveedor,
            ':id_empresa'   => $idEmpresa,
            ':fecha_hasta'  => $fechaHasta,
        ];

        // Solo el ambiente activo de la empresa, igual que el resto de módulos
        $stA = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR) FROM empresas WHERE id = :id_empresa LIMIT 1");
        $stA->execute([':id_empresa' => $idEmpresa]);
        $amb = $stA->fetchColumn();
        $filtroAmb = '';
        if ($amb !== false && $amb !== null) {
            $filtroAmb = " AND CAST(c.tipo_ambiente AS VARCHAR) = :amb";
            $params[':amb'] = (string) $amb;
        }

        $numDocExpr = "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)";

        $esCargo = \App\Helpers\TiposComprobanteCompra::sqlEsCargo('c.tipo_comprobante'); // todo comprobante con deuda, no solo '01' (igual que CxP)

        $compraVigente = \App\Helpers\TiposComprobanteCompra::sqlCompraVigente('c.estado'); // anuladas/rechazadas no son deuda
        $sql = "
            WITH pagado AS (
                SELECT ed.id_referencia_documento AS id_doc
                FROM egresos_detalle ed
                INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                WHERE ed.tipo_documento = 'COMPRA'
                  AND ec.estado   != 'anulado'
                  AND ec.eliminado = false
                  AND ed.eliminado = false
                GROUP BY ed.id_referencia_documento
            ),
            nc_nd AS (
                SELECT nc.documento_modificado,
                       SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                       SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
                FROM compras_cabecera nc
                WHERE nc.tipo_comprobante IN ('04','05')
                  AND nc.eliminado    = false
                  AND nc.id_empresa   = :id_empresa
                  AND nc.id_proveedor = :id_proveedor
                GROUP BY nc.documento_modificado
            ),
            ret AS (
                SELECT r.id_compra, SUM(r.total_retenido) AS total_retenido
                FROM retencion_compra_cabecera r
                WHERE r.eliminado = false
                  AND r.id_compra IS NOT NULL
                  AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE')
                GROUP BY r.id_compra
            )
            SELECT c.id,
                   c.fecha_emision,
                   c.importe_total,
                   {$numDocExpr}                        AS numero_documento,
                   COALESCE(rt.total_retenido, 0)       AS total_retenido,
                   COALESCE(nn.total_nc, 0)             AS total_nc,
                   COALESCE(nn.total_nd, 0)             AS total_nd,
                   c.importe_total
                     - COALESCE(rt.total_retenido, 0)
                     - COALESCE(nn.total_nc, 0)
                     + COALESCE(nn.total_nd, 0)         AS saldo
            FROM compras_cabecera c
            LEFT JOIN pagado pg ON pg.id_doc = c.id
            LEFT JOIN nc_nd  nn ON nn.documento_modificado = {$numDocExpr}
            LEFT JOIN ret    rt ON rt.id_compra = c.id
            WHERE c.id_empresa       = :id_empresa
              AND c.id_proveedor     = :id_proveedor
              AND c.eliminado        = false
              AND {$esCargo} AND {$compraVigente}
              AND c.fecha_emision   <= :fecha_hasta
              AND pg.id_doc IS NULL
              {$filtroAmb}
            ORDER BY c.fecha_emision ASC, c.id ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clasifica las compras pendientes en las que se pagarían y las que quedan
     * fuera (saldo cero o monto fuera del rango configurado).
     *
     * @return array{ok:bool, motivo:?string, elegibles:array, excluidas:array, total:float}
     */
    public function previsualizarPendientes(int $idProveedor, int $idEmpresa, string $fechaHasta): array
    {
        $cfg = $this->getConfiguracion($idProveedor, $idEmpresa);
        if (!$cfg['ok']) {
            return [
                'ok'        => false,
                'motivo'    => $cfg['motivo'] ?? 'El proveedor no tiene una forma de pago predeterminada configurada.',
                'elegibles' => [],
                'excluidas' => [],
                'total'     => 0.0,
            ];
        }

        $elegibles = [];
        $excluidas = [];
        $total     = 0.0;

        foreach ($this->getComprasPendientes($idProveedor, $idEmpresa, $fechaHasta) as $c) {
            $saldo = round((float) $c['saldo'], 2);

            if ($saldo <= 0.001) {
                $c['motivo'] = 'Sin saldo por pagar (cubierta por retenciones o notas de crédito).';
                $excluidas[] = $c;
                continue;
            }

            $motivoRango = $this->validarRango($cfg['config'], $saldo);
            if ($motivoRango !== null) {
                $c['motivo'] = $motivoRango . '.';
                $excluidas[] = $c;
                continue;
            }

            $c['monto_a_pagar'] = $saldo;
            $elegibles[] = $c;
            $total += $saldo;
        }

        return [
            'ok'        => true,
            'motivo'    => null,
            'elegibles' => $elegibles,
            'excluidas' => $excluidas,
            'total'     => round($total, 2),
            'config'    => $cfg['config'],
        ];
    }

    /**
     * Genera los egresos pendientes del proveedor hasta la fecha indicada.
     *
     * Cada egreso se registra por separado (EgresoService maneja su propia
     * transacción y genera el asiento contable), de modo que un documento que
     * falle no impide los demás.
     *
     * @return array{ok:bool, motivo:?string, generados:int, fallidos:array, omitidas:int, total:float}
     */
    public function generarPendientes(int $idProveedor, int $idEmpresa, int $idUsuario, string $fechaHasta): array
    {
        $prev = $this->previsualizarPendientes($idProveedor, $idEmpresa, $fechaHasta);
        if (!$prev['ok']) {
            return ['ok' => false, 'motivo' => $prev['motivo'], 'generados' => 0, 'fallidos' => [], 'omitidas' => 0, 'total' => 0.0];
        }

        $config    = $prev['config'];
        $generados = 0;
        $total     = 0.0;
        $fallidos  = [];

        foreach ($prev['elegibles'] as $c) {
            $numDoc = (string) $c['numero_documento'];
            try {
                $this->generarParaCompra(
                    $config,
                    (int) $c['id'],
                    $idProveedor,
                    $idEmpresa,
                    $idUsuario,
                    (float) $c['importe_total'],
                    (float) $c['monto_a_pagar'],
                    $numDoc,
                    (string) $c['fecha_emision'],
                    'Pago generado desde la configuración de pagos del proveedor para la Compra #' . $numDoc . '.'
                );
                $generados++;
                $total += (float) $c['monto_a_pagar'];
            } catch (\Throwable $e) {
                $fallidos[] = ['numero_documento' => $numDoc, 'error' => $e->getMessage()];
            }
        }

        return [
            'ok'        => true,
            'motivo'    => null,
            'generados' => $generados,
            'fallidos'  => $fallidos,
            'omitidas'  => count($prev['excluidas']),
            'total'     => round($total, 2),
        ];
    }

    /**
     * Fecha de cobro del cheque = fecha de emisión del documento + días de
     * crédito del proveedor. Respeta la unidad de tiempo configurada.
     */
    public function calcularFechaCobroCredito(string $fechaEmision, int $plazo, string $unidadTiempo): string
    {
        if ($plazo <= 0) {
            return $fechaEmision;
        }

        $intervalo = match (strtoupper(trim($unidadTiempo))) {
            'MESES', 'MES'          => "+{$plazo} months",
            'ANOS', 'AÑOS', 'ANIOS' => "+{$plazo} years",
            default                 => "+{$plazo} days",
        };

        $ts = strtotime($intervalo, strtotime($fechaEmision));
        return $ts !== false ? date('Y-m-d', $ts) : $fechaEmision;
    }
}
