<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\EgresoRepository;
use App\Rules\modulos\EgresoRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;

/**
 * Registro de pagos a proveedores (Cuentas por Pagar).
 *
 * Extraído de CuentasPorPagarController::registrarPagoAjax() (que antes hacía
 * toda la lógica de negocio inline) para respetar Controller → Service.
 */
class CuentasPorPagarService
{
    private CuentasPorPagarRepository $repo;
    private LogSistemaService $log;

    public function __construct(?CuentasPorPagarRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new CuentasPorPagarRepository();
        $this->log  = $log  ?? new LogSistemaService();
    }

    /**
     * Valida los datos de un pago (documento existe, saldo suficiente) y
     * devuelve el documento + punto de emisión ya resueltos. Lanza excepción
     * si algo no es válido — mismos mensajes que antes mostraba el controller.
     */
    public function validarPago(array $d): array
    {
        $idDoc       = (int) ($d['id_doc'] ?? 0);
        $tipoFuente  = trim((string) ($d['tipo_fuente'] ?? 'COMPRA'));
        $monto       = (float) ($d['monto'] ?? 0);
        $idPunto     = (int) ($d['id_punto_emision'] ?? 0);
        $idFormaPago = (int) ($d['id_forma_pago'] ?? 0);
        $idEmpresa   = (int) ($d['id_empresa'] ?? 0);

        if ($idDoc <= 0 || $monto <= 0 || $idFormaPago <= 0 || $idPunto <= 0) {
            throw new \InvalidArgumentException('Datos incompletos. Verifique serie, monto y forma de pago.');
        }

        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            throw new \InvalidArgumentException('Punto de emisión no válido.');
        }

        $doc = $this->repo->getDocumentoParaPago($idDoc, $tipoFuente, $idEmpresa);
        if (!$doc) {
            throw new \InvalidArgumentException('Documento no encontrado.');
        }

        $saldo = (float) $doc['saldo'];
        if ($saldo <= 0) {
            throw new \InvalidArgumentException('Este documento ya se encuentra pagado.');
        }
        if ($monto > $saldo + 0.001) {
            throw new \InvalidArgumentException("El monto ($monto) supera el saldo pendiente ($saldo).");
        }

        return ['doc' => $doc, 'punto' => $punto, 'saldo' => $saldo, 'tipoFuente' => $tipoFuente, 'monto' => $monto];
    }

    /**
     * Registra el pago real: revalida saldo, genera secuencial NUEVO y crea el
     * egreso. Se puede llamar de inmediato (pago sin aprobación) o más tarde
     * desde el callback de aprobación, con los mismos datos guardados en el
     * momento en que se solicitó — siempre revalida contra el estado actual.
     */
    public function registrarPago(array $d): array
    {
        $val = $this->validarPago($d);
        $doc = $val['doc'];
        $punto = $val['punto'];
        $saldo = $val['saldo'];
        $tipoFuente = $val['tipoFuente'];
        $monto = $val['monto'];

        $idEmpresa   = (int) $d['id_empresa'];
        $idUsuario   = (int) $d['id_usuario'];
        $idDoc       = (int) $d['id_doc'];
        $idFormaPago = (int) $d['id_forma_pago'];
        $idPunto     = (int) $d['id_punto_emision'];
        $idConcepto  = !empty($d['id_egreso_concepto']) ? (int) $d['id_egreso_concepto'] : null;
        $fechaPago   = trim((string) ($d['fecha_pago'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $observ      = trim((string) ($d['observaciones'] ?? ''));
        $tipoOp      = trim((string) ($d['tipo_operacion_bancaria'] ?? ''));
        $numOp       = trim((string) ($d['numero_operacion'] ?? ''));
        $fechaCobro  = !empty($d['fecha_cobro']) ? trim((string) $d['fecha_cobro']) : $fechaPago;

        $totalDoc  = (float) $doc['importe_total'];
        $tipoDocEg = $tipoFuente === 'LIQUIDACION' ? 'LIQUIDACION' : 'COMPRA';

        $secuencialService = new SecuencialService();
        $secRes = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
        $secuencial = $secRes['formateado'];

        $codEst = str_pad((string) ($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $codPto = str_pad((string) ($punto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $numEgr = "{$codEst}-{$codPto}-{$secuencial}";
        $numDoc = $doc['numero_documento'] ?? '';

        $payload = [
            'id_empresa'         => $idEmpresa,
            'id_punto_emision'   => $idPunto,
            'establecimiento'    => $codEst,
            'punto_emision'      => $codPto,
            'secuencial'         => $secuencial,
            'numero_egreso'      => $numEgr,
            'fecha_emision'      => $fechaPago,
            'tipo_egreso'        => $tipoFuente === 'LIQUIDACION' ? 'COMPRA_LIQUIDACION' : 'COMPRA_FACTURA',
            'tipo_sujeto'        => 'PROVEEDOR',
            'id_proveedor'       => (int) $doc['id_proveedor'],
            'id_empleado'        => null,
            'id_egreso_concepto' => $idConcepto,
            'monto_total'        => $monto,
            'observaciones'      => $observ ?: "Pago de {$tipoDocEg} {$numDoc}",
            'estado'             => 'registrado',
            'usuario_id'         => $idUsuario,
            'detalles' => [[
                'tipo_documento'          => $tipoDocEg,
                'id_referencia_documento' => $idDoc,
                'numero_documento'        => $numDoc,
                'descripcion'             => "Pago de {$tipoDocEg} {$numDoc}",
                'monto_documento'         => $totalDoc,
                'saldo_anterior'          => $saldo,
                'monto_pagado'            => $monto,
                'saldo_actual'            => max(0.0, $saldo - $monto),
            ]],
            'pagos' => [[
                'id_forma_pago'           => $idFormaPago,
                'monto'                   => $monto,
                'fecha_cobro'             => $fechaCobro,
                'referencia'              => $numOp ?: null,
                'tipo_operacion_bancaria' => $tipoOp ?: null,
                'numero_cheque'           => ($tipoOp === 'CHEQUE' ? $numOp : null) ?: null,
            ]],
        ];

        $egresoService = new EgresoService(new EgresoRepository(), new EgresoRules(), $this->log);
        $idEgreso = $egresoService->registrar($payload);

        $nuevoSaldo = $saldo - $monto;

        return [
            'mensaje'       => "Pago registrado correctamente. Egreso: {$numEgr}",
            'id_egreso'     => $idEgreso,
            'numero_egreso' => $numEgr,
            'nuevo_saldo'   => number_format($nuevoSaldo, 2, '.', ''),
            'pagado'        => $nuevoSaldo <= 0.001,
            'doc'           => $doc,
        ];
    }

}
