<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CuentasPorCobrarRepository;
use App\services\WhatsappService;
use App\Services\LogSistemaService;
use PDO;

class CuentasPorCobrarController extends BaseModuloController
{
    private CuentasPorCobrarRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/cuentas_por_cobrar';
    }

    private LogSistemaService $log;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CuentasPorCobrarRepository();
        $this->log  = new LogSistemaService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS JSON
    // ─────────────────────────────────────────────────────────────────────

    private function jsonSuccess(array $data): never
    {
        $this->json(array_merge(['ok' => true], $data));
    }

    private function jsonError(string $mensaje, int $code = 200): never
    {
        $this->json(['ok' => false, 'error' => $mensaje], $code);
    }

    // ─────────────────────────────────────────────────────────────────────
    // VISTA PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $anios        = $this->repo->getAniosDisponibles($idEmpresa);
        $tieneWA      = $this->repo->tieneWhatsappConfigurado($idEmpresa);
        $prefsVista   = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $this->viewWithLayout('layouts.main', 'modulos/cuentas_por_cobrar/index', [
            'titulo'      => 'Cuentas por Cobrar',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'anios'       => $anios,
            'tieneWA'     => $tieneWA,
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – LISTADO PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function generarAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $filtros = $this->getFiltros();

        $filas      = $this->getFilasUnificadas($idEmpresa, $filtros);
        $stats      = $this->repo->getEstadisticas($idEmpresa, $filtros);
        $antiguedad = $this->repo->getAntiguedad($idEmpresa, $filtros);

        // Formateamos filas
        foreach ($filas as &$f) {
            $f['total']        = number_format((float)$f['total'],        2, '.', '');
            $f['total_cobrado']= number_format((float)$f['total_cobrado'],2, '.', '');
            $f['saldo']        = number_format((float)$f['saldo'],        2, '.', '');
            $f['dias_vencido'] = (int)($f['dias_vencido'] ?? 0);
        }
        unset($f);

        $this->jsonSuccess([
            'filas'      => $filas,
            'stats'      => $stats,
            'antiguedad' => $antiguedad,
        ]);
    }

    /**
     * Lista unificada de Cuentas por Cobrar: facturas (ventas_cabecera) +
     * recibos de venta (recibos_venta_cabecera) + saldos iniciales por cobrar,
     * en una sola tabla. Cada fila lleva un campo `origen`
     * ('FACTURA' | 'RECIBO' | 'SALDO_INICIAL') para distinguirla y enrutar acciones.
     * El filtro `tipo_doc` (TODOS | FACTURA | RECIBO | SALDO_INICIAL) decide
     * qué orígenes se incluyen. Los saldos iniciales se filtran por el mismo
     * estado/cliente del listado.
     */
    private function getFilasUnificadas(int $idEmpresa, array $filtros): array
    {
        $tipoDoc = $filtros['tipo_doc'] ?? 'TODOS';

        // Facturas
        $facturas = [];
        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            $facturas = $this->repo->getListado($idEmpresa, $filtros);
            foreach ($facturas as &$f) { $f['origen'] = 'FACTURA'; }
            unset($f);
        }

        // Recibos de venta (mismas columnas que el listado de facturas)
        $recibos = [];
        if (in_array($tipoDoc, ['TODOS', 'RECIBO'], true)) {
            $recibos = $this->repo->getListadoRecibos($idEmpresa, $filtros);
            foreach ($recibos as &$r) { $r['origen'] = 'RECIBO'; }
            unset($r);
        }

        // Saldos iniciales (todos; se filtran en PHP por el mismo estado del listado)
        if (!in_array($tipoDoc, ['TODOS', 'SALDO_INICIAL'], true)) {
            $saldos = [];
        } else {
            $saldos = $this->repo->getSaldosInicialesCxc($idEmpresa, [
                'estado'      => 'TODOS',
                'id_cliente'  => $filtros['id_cliente'] ?? '',
                'fecha_desde' => $filtros['fecha_desde'] ?? '',
                'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
            ]);
        }

        $estado = $filtros['estado'] ?? 'PENDIENTES';
        $filasSI = [];
        foreach ($saldos as $s) {
            $pend = (float)$s['saldo_pendiente'];
            $venc = ((int)($s['dias_vencido'] ?? 0)) > 0;
            $incluir = match ($estado) {
                'PENDIENTES' => $pend > 0,
                'VENCIDAS'   => $pend > 0 && $venc,
                'AL_DIA'     => $pend > 0 && !$venc,
                'PAGADAS'    => $pend <= 0,
                default      => true, // TODOS
            };
            if (!$incluir) continue;

            $filasSI[] = [
                'origen'            => 'SALDO_INICIAL',
                'id'                => (int)$s['id'],
                'numero_factura'    => $s['nro_documento'],
                'id_cliente'        => $s['id_cliente'] ?? null,
                'cliente_nombre'    => $s['nombre_cliente'],
                'cliente_ruc'       => $s['ruc_cliente'],
                'cliente_email'     => '',
                'cliente_telefono'  => '',
                'fecha_emision'     => $s['fecha_emision'],
                'fecha_vencimiento' => $s['fecha_vencimiento'],
                'total'             => $s['saldo_inicial'],
                'total_cobrado'     => $s['monto_cobrado'],
                'total_retenido'    => $s['monto_retenido'] ?? 0,
                'total_nc'          => $s['monto_nc'] ?? 0,
                'saldo'             => $s['saldo_pendiente'],
                'dias_vencido'      => (int)($s['dias_vencido'] ?? 0),
            ];
        }

        $filas = array_merge($facturas, $recibos, $filasSI);

        // Orden por vencimiento ascendente (igual que el listado de facturas)
        usort($filas, function ($a, $b) {
            $va = $a['fecha_vencimiento'] ?? '';
            $vb = $b['fecha_vencimiento'] ?? '';
            return strcmp((string)$va, (string)$vb);
        });

        return $filas;
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – REGISTRAR COBRO
    // ─────────────────────────────────────────────────────────────────────

    public function registrarCobroAjax(): void
    {
        $this->requireCrear();
        $idEmpresa    = (int) $_SESSION['id_empresa'];
        $idUsuario    = (int) $_SESSION['id_usuario'];

        $idVenta      = (int)($_POST['id_venta']          ?? 0);
        $monto        = (float)($_POST['monto']           ?? 0);
        $idFormaCobro = (int)($_POST['id_forma_cobro']    ?? 0);
        $idPunto      = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto   = !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null;
        $fechaCobro   = trim($_POST['fecha_cobro']        ?? date('Y-m-d'));
        $observ       = trim($_POST['observaciones']      ?? '');
        $tipoOp       = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp        = trim($_POST['numero_operacion']        ?? '');

        if ($idVenta <= 0 || $monto <= 0 || $idFormaCobro <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        // Validar punto de emisión
        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('Punto de emisión no válido.');
            return;
        }

        // Validar factura y saldo
        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }
        $saldo       = (float)$factura['saldo'];
        $totalFact   = (float)$factura['importe_total'];
        if ($saldo <= 0) {
            $this->jsonError('Esta factura ya se encuentra pagada.');
            return;
        }
        if ($monto > $saldo + 0.001) {
            $this->jsonError("El monto ($monto) supera el saldo pendiente ($saldo).");
            return;
        }

        $db = \App\core\Database::getConnection();
        try {
            // Obtener siguiente secuencial mediante SecuencialService. Se abre la transacción
            // ANTES de calcularlo y se mantiene hasta el INSERT final (IngresoService::crear()):
            // el lock de obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
            $db->beginTransaction();
            $secuencialService = new \App\Services\SecuencialService();
            $secRes    = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos');
            $secuencial = $secRes['formateado'];

            $codEst  = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $codPto  = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);
            $numDoc  = "{$codEst}-{$codPto}-{$secuencial}";
            $numFact = ($factura['establecimiento'] ?? '') . '-'
                     . ($factura['punto_emision']   ?? '') . '-'
                     . ($factura['secuencial']       ?? '');

            // Delegar al IngresoService (igual que FacturaVentaController)
            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => $idPunto,
                'id_cliente'          => (int)$factura['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $fechaCobro ?: date('Y-m-d'),
                'establecimiento'     => $codEst,
                'punto_emision'       => $codPto,
                'secuencial'          => $secuencial,
                'numero_ingreso'      => $numDoc,
                'tipo_ingreso'        => 'FACTURA_VENTA',
                'id_ingreso_concepto' => $idConcepto,
                'monto_total'         => $monto,
                'observaciones'       => $observ ?: "Cobro de factura {$numFact}",
                'recibo_de'           => $factura['cliente_nombre'] ?? '',
                'id_recibo_cliente'   => (int)$factura['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'FACTURA',
                    'id_referencia_documento' => $idVenta,
                    'numero_documento'        => $numFact,
                    'descripcion'             => "Cobro de factura {$numFact}",
                    'monto_documento'         => $totalFact,
                    'saldo_anterior'          => $saldo,
                    'monto_cobrado'           => $monto,
                    'saldo_actual'            => max(0.0, $saldo - $monto),
                ]],
                'pagos' => [[
                    'id_forma_cobro'          => $idFormaCobro,
                    'monto'                   => $monto,
                    'fecha_cobro'             => $fechaCobro,
                    'observaciones'           => $observ ?: null,
                    'tipo_operacion_bancaria' => $tipoOp ?: null,
                    'numero_cheque'           => $numOp  ?: null,
                    'referencia'              => $numOp  ?: null,
                ]],
            ];

            $ingresoService = new \App\Services\modulos\IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                new \App\Services\LogSistemaService()
            );

            $idIngreso = $ingresoService->crear($payload);
            $db->commit();

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'        => "Cobro registrado correctamente. Ingreso: {$numDoc}",
                'id_ingreso'     => $idIngreso,
                'numero_ingreso' => $numDoc,
                'nuevo_saldo'    => number_format($nuevoSaldo, 2, '.', ''),
                'pagada'         => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[CxC registrarCobro] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – DATOS EN TIEMPO REAL PARA EL MODAL DE COBRO
    // ─────────────────────────────────────────────────────────────────────

    public function getFacturaParaCobroInfoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idVenta   = (int) ($_GET['id_venta'] ?? 0);

        if ($idVenta <= 0) {
            $this->jsonError('ID inválido.');
            return;
        }

        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        $this->jsonSuccess(['factura' => $factura]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – HISTORIAL DE COBROS
    // ─────────────────────────────────────────────────────────────────────

    public function historialCobrosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idVenta   = (int)($_GET['id_venta'] ?? 0);

        if ($idVenta <= 0) {
            $this->jsonError('ID de venta inválido.');
            return;
        }

        $historial = $this->repo->getHistorialCobros($idVenta, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – RECIBOS DE VENTA (cobro, info y historial)
    // ─────────────────────────────────────────────────────────────────────

    public function getReciboParaCobroInfoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idRecibo  = (int) ($_GET['id_recibo'] ?? 0);

        if ($idRecibo <= 0) {
            $this->jsonError('ID inválido.');
            return;
        }

        $recibo = $this->repo->getReciboParaCobro($idRecibo, $idEmpresa);
        if (!$recibo) {
            $this->jsonError('Recibo no encontrado.');
            return;
        }

        $this->jsonSuccess(['factura' => $recibo]);
    }

    public function historialCobrosReciboAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idRecibo  = (int)($_GET['id_recibo'] ?? 0);

        if ($idRecibo <= 0) {
            $this->jsonError('ID de recibo inválido.');
            return;
        }

        $historial = $this->repo->getHistorialCobrosRecibo($idRecibo, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    /**
     * Cobro de un recibo de venta desde la tabla unificada de CxC.
     * Mismo flujo que registrarCobroAjax pero con tipo_ingreso RECIBO_VENTA
     * y detalle tipo_documento RECIBO (igual que el módulo de recibos).
     */
    public function registrarCobroReciboAjax(): void
    {
        $this->requireCrear();
        $idEmpresa    = (int) $_SESSION['id_empresa'];
        $idUsuario    = (int) $_SESSION['id_usuario'];

        $idRecibo     = (int)($_POST['id_recibo']         ?? 0);
        $monto        = (float)($_POST['monto']           ?? 0);
        $idFormaCobro = (int)($_POST['id_forma_cobro']    ?? 0);
        $idPunto      = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto   = !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null;
        $fechaCobro   = trim($_POST['fecha_cobro']        ?? date('Y-m-d'));
        $observ       = trim($_POST['observaciones']      ?? '');
        $tipoOp       = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp        = trim($_POST['numero_operacion']        ?? '');

        if ($idRecibo <= 0 || $monto <= 0 || $idFormaCobro <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        // Validar punto de emisión
        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('Punto de emisión no válido.');
            return;
        }

        // Validar recibo y saldo
        $recibo = $this->repo->getReciboParaCobro($idRecibo, $idEmpresa);
        if (!$recibo) {
            $this->jsonError('Recibo no encontrado.');
            return;
        }
        $saldo    = (float)$recibo['saldo'];
        $totalRec = (float)$recibo['importe_total'];
        if ($saldo <= 0) {
            $this->jsonError('Este recibo ya se encuentra pagado.');
            return;
        }
        if ($monto > $saldo + 0.001) {
            $this->jsonError("El monto ($monto) supera el saldo pendiente ($saldo).");
            return;
        }

        $db = \App\core\Database::getConnection();
        try {
            // Obtener siguiente secuencial mediante SecuencialService. Se abre la transacción
            // ANTES de calcularlo y se mantiene hasta el INSERT final (IngresoService::crear()):
            // el lock de obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
            $db->beginTransaction();
            $secuencialService = new \App\Services\SecuencialService();
            $secRes     = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos');
            $secuencial = $secRes['formateado'];

            $codEst = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $codPto = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);
            $numDoc = "{$codEst}-{$codPto}-{$secuencial}";
            $numRec = ($recibo['establecimiento'] ?? '') . '-'
                    . ($recibo['punto_emision']   ?? '') . '-'
                    . ($recibo['secuencial']       ?? '');

            // Delegar al IngresoService (igual que ReciboVentaController)
            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => $idPunto,
                'id_cliente'          => (int)$recibo['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $fechaCobro ?: date('Y-m-d'),
                'establecimiento'     => $codEst,
                'punto_emision'       => $codPto,
                'secuencial'          => $secuencial,
                'numero_ingreso'      => $numDoc,
                'tipo_ingreso'        => 'RECIBO_VENTA',
                'id_ingreso_concepto' => $idConcepto,
                'monto_total'         => $monto,
                'observaciones'       => $observ ?: "Cobro de recibo {$numRec}",
                'recibo_de'           => $recibo['cliente_nombre'] ?? '',
                'id_recibo_cliente'   => (int)$recibo['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'RECIBO',
                    'id_referencia_documento' => $idRecibo,
                    'numero_documento'        => $numRec,
                    'descripcion'             => "Cobro de recibo {$numRec}",
                    'monto_documento'         => $totalRec,
                    'saldo_anterior'          => $saldo,
                    'monto_cobrado'           => $monto,
                    'saldo_actual'            => max(0.0, $saldo - $monto),
                ]],
                'pagos' => [[
                    'id_forma_cobro'          => $idFormaCobro,
                    'monto'                   => $monto,
                    'fecha_cobro'             => $fechaCobro,
                    'observaciones'           => $observ ?: null,
                    'tipo_operacion_bancaria' => $tipoOp ?: null,
                    'numero_cheque'           => $numOp  ?: null,
                    'referencia'              => $numOp  ?: null,
                ]],
            ];

            $ingresoService = new \App\Services\modulos\IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                new \App\Services\LogSistemaService()
            );

            $idIngreso = $ingresoService->crear($payload);
            $db->commit();

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'        => "Cobro registrado correctamente. Ingreso: {$numDoc}",
                'id_ingreso'     => $idIngreso,
                'numero_ingreso' => $numDoc,
                'nuevo_saldo'    => number_format($nuevoSaldo, 2, '.', ''),
                'pagada'         => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[CxC registrarCobroRecibo] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – FORMAS DE COBRO
    // ─────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – CATÁLOGOS PARA EL MODAL COBRO (puntos, conceptos, formas)
    // ─────────────────────────────────────────────────────────────────────

    public function getCatalogosCobroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonSuccess([
            'puntos'    => $this->repo->getPuntosEmision($idEmpresa),
            'conceptos' => $this->repo->getConceptos($idEmpresa),
            'formas'    => $this->repo->getFormasCobro($idEmpresa),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – SIGUIENTE SECUENCIAL DE INGRESO PARA UN PUNTO DE EMISIÓN
    // ─────────────────────────────────────────────────────────────────────

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('ID de punto de emisión inválido.');
            return;
        }
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos');
        $this->jsonSuccess($res);
    }

    public function getFormasCobroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonSuccess(['formas' => $this->repo->getFormasCobro($idEmpresa)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – PLANTILLAS WHATSAPP
    // ─────────────────────────────────────────────────────────────────────

    public function getPlantillasWAAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $todasPlantillas = $this->repo->getPlantillasWA($idEmpresa);

        $todasLasRapidas = [
            'aviso_mensajes_pendientes', 'factura_por_cobrar', 'factura_venta',
            'cuenta_por_cobrar', 'renovacion_suscripcion', 'renovacion_firma_electronica',
            'retencion_compra', 'nota_credito', 'nota_debito', 'guia_remision',
            'rol_pagos', 'descuento_empleado'
        ];
        $rapidasPermitidas = ['factura_por_cobrar', 'cuenta_por_cobrar'];

$plantillasFiltradas = [];
        foreach ($todasPlantillas as $p) {
            if (in_array($p['nombre'], $todasLasRapidas)) {
                if (in_array($p['nombre'], $rapidasPermitidas)) {
                    $plantillasFiltradas[] = $p;
                }
            } else {
                // Es una plantilla libre
                $plantillasFiltradas[] = $p;
            }
        }

        $this->jsonSuccess(['plantillas' => $plantillasFiltradas]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVIAR EMAIL
    // ─────────────────────────────────────────────────────────────────────

    public function enviarEmailAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $idVenta   = (int)($_POST['id_venta'] ?? 0);
        $emailDest = trim($_POST['email'] ?? '');
        $asunto    = trim($_POST['asunto'] ?? '');
        $mensaje   = trim($_POST['mensaje'] ?? '');
        $origen    = strtoupper(trim($_POST['origen'] ?? 'FACTURA'));
        $esRecibo  = ($origen === 'RECIBO');

        if ($idVenta <= 0 || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Datos incompletos o email inválido.');
            return;
        }

        $factura = $esRecibo
            ? $this->repo->getReciboParaCobro($idVenta, $idEmpresa)
            : $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError($esRecibo ? 'Recibo no encontrado.' : 'Factura no encontrada.');
            return;
        }

        $docLabel    = $esRecibo ? 'Recibo' : 'Factura';
        $asuntoFinal = $asunto ?: "Recordatorio de pago — {$docLabel} " . ($factura['numero_factura'] ?? '');
        $htmlBody    = $this->renderEmailBody($factura, $mensaje, $docLabel);

        // Usar el mismo servicio de envío que el resto del sistema
        // (incluye _mail_resolve_ipv4_host y config SMTP por empresa)
        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $enviado  = $emailSvc->enviarAvisoSimple(
            $idEmpresa,
            $emailDest,
            $factura['cliente_nombre'] ?? '',
            $asuntoFinal,
            $htmlBody
        );

        if (!$enviado) {
            $detalle = $GLOBALS['LAST_EMAIL_ERROR'] ?? null;
            $this->jsonError('No se pudo enviar el correo. Verifica la configuración de correo de la empresa.'
                . ($detalle ? ' Detalle: ' . $detalle : ''));
            return;
        }

        $this->log->registrar(
            (int)$_SESSION['id_usuario'],
            $idEmpresa,
            'EMAIL_CXC',
            $esRecibo ? 'recibos_venta_cabecera' : 'ventas_cabecera',
            $idVenta,
            null,
            ['email' => $emailDest]
        );

        $this->jsonSuccess(['mensaje' => 'Correo enviado correctamente a ' . $emailDest]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVÍO MASIVO DE EMAIL (un correo por cliente con el resumen)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Envío masivo de recordatorios: recibe los documentos seleccionados
     * ([{origen: FACTURA|RECIBO, id}]), los agrupa por cliente y envía UN
     * correo por cliente con la tabla resumen de sus documentos pendientes
     * (facturas y recibos mezclados) y el total. El email destino se toma de
     * la ficha del cliente en BD; los documentos sin saldo se omiten.
     */
    public function enviarEmailMasivoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $docsRaw = json_decode($_POST['documentos'] ?? '[]', true);
        if (!is_array($docsRaw) || empty($docsRaw)) {
            $this->jsonError('No se recibieron documentos para enviar.');
            return;
        }
        if (count($docsRaw) > 300) {
            $this->jsonError('Máximo 300 documentos por envío. Aplique filtros y envíe por partes.');
            return;
        }

        // Correos revisados/editados en el modal: {id_cliente: "correo1, correo2"}.
        // Si la clave existe se usa tal cual (vacío = omitir al cliente); si no,
        // se usa el correo de la ficha del cliente. Solo afecta a este envío.
        $correosEdit = json_decode($_POST['correos'] ?? '{}', true);
        if (!is_array($correosEdit)) $correosEdit = [];

        // El envío SMTP secuencial puede tardar varios segundos por cliente
        @set_time_limit(300);

        // 1) Cargar cada documento desde BD (valida empresa/estado y trae el saldo real)
        $porCliente    = [];  // id_cliente => [nombre, email, docs[]]
        $sinSaldo      = 0;
        $noEncontrados = 0;
        $vistos        = [];  // dedup ORIGEN:id

        foreach ($docsRaw as $d) {
            $origen = strtoupper(trim((string)($d['origen'] ?? 'FACTURA')));
            $id     = (int)($d['id'] ?? 0);
            if ($id <= 0 || !in_array($origen, ['FACTURA', 'RECIBO'], true)) continue;
            $k = $origen . ':' . $id;
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;

            $doc = $origen === 'RECIBO'
                ? $this->repo->getReciboParaCobro($id, $idEmpresa)
                : $this->repo->getFacturaParaCobro($id, $idEmpresa);
            if (!$doc) { $noEncontrados++; continue; }

            $saldo = (float)($doc['saldo'] ?? 0);
            if ($saldo <= 0.001) { $sinSaldo++; continue; }

            // Fecha de vencimiento (el recibo ya la trae; en la factura se calcula)
            $fVenc = !empty($doc['fecha_vencimiento'])
                ? $doc['fecha_vencimiento']
                : date('Y-m-d', strtotime(($doc['fecha_emision'] ?? 'now') . ' +' . (int)($doc['dias_credito'] ?? 0) . ' days'));
            $diasVencido = (int)((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($fVenc)))) / 86400);

            $idCliente = (int)($doc['id_cliente'] ?? 0);
            if (!isset($porCliente[$idCliente])) {
                $porCliente[$idCliente] = [
                    'nombre' => $doc['cliente_nombre'] ?? '',
                    'email'  => trim((string)($doc['cliente_email'] ?? '')),
                    'docs'   => [],
                ];
            }
            $porCliente[$idCliente]['docs'][] = [
                'tipo'          => $origen === 'RECIBO' ? 'Recibo' : 'Factura',
                'numero'        => $doc['numero_factura'] ?? '',
                'fecha_emision' => $doc['fecha_emision'] ?? '',
                'fecha_venc'    => $fVenc,
                'dias_vencido'  => $diasVencido,
                'total'         => (float)($doc['importe_total'] ?? 0),
                'saldo'         => $saldo,
            ];
        }

        if (empty($porCliente)) {
            $this->jsonError('Ninguno de los documentos seleccionados tiene saldo pendiente.');
            return;
        }

        // 2) Un correo por cliente
        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $enviados = 0;
        $sinEmail = 0;
        $conError = 0;

        foreach ($porCliente as $idCliente => $cli) {
            // Correo editado en el modal (si vino) o el de la ficha del cliente
            $emailStr = array_key_exists($idCliente, $correosEdit)
                ? trim((string)$correosEdit[$idCliente])
                : trim((string)$cli['email']);

            // Direcciones válidas (mismo criterio de split que enviarAvisoSimple)
            $direcciones = [];
            foreach (preg_split('/[\s,;]+/', $emailStr) as $c) {
                $c = trim($c);
                if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) $direcciones[] = $c;
            }
            if (empty($direcciones)) {
                $sinEmail++;
                continue;
            }

            // Del más vencido al más reciente
            usort($cli['docs'], fn ($a, $b) => strcmp((string)$a['fecha_venc'], (string)$b['fecha_venc']));

            $n      = count($cli['docs']);
            $asunto = $n === 1
                ? 'Recordatorio de pago — ' . $cli['docs'][0]['tipo'] . ' ' . $cli['docs'][0]['numero']
                : "Recordatorio de pago — {$n} documentos pendientes";
            $html = $this->renderEmailBodyResumen($cli['nombre'], $cli['docs']);

            $ok = $emailSvc->enviarAvisoSimple($idEmpresa, implode(',', $direcciones), $cli['nombre'], $asunto, $html);
            if ($ok) {
                $enviados++;
                $this->log->registrar(
                    (int)$_SESSION['id_usuario'],
                    $idEmpresa,
                    'EMAIL_CXC_MASIVO',
                    'clientes',
                    $idCliente,
                    null,
                    [
                        'email'           => implode(', ', $direcciones),
                        'documentos'      => array_map(fn ($x) => $x['tipo'] . ' ' . $x['numero'], $cli['docs']),
                        'total_pendiente' => round(array_sum(array_column($cli['docs'], 'saldo')), 2),
                    ]
                );
            } else {
                $conError++;
            }
        }

        $partes = ["{$enviados} correo(s) enviado(s)."];
        if ($sinEmail)      $partes[] = "{$sinEmail} cliente(s) sin email registrado.";
        if ($conError)      $partes[] = "{$conError} correo(s) con error de envío.";
        if ($sinSaldo)      $partes[] = "{$sinSaldo} documento(s) sin saldo omitido(s).";
        if ($noEncontrados) $partes[] = "{$noEncontrados} documento(s) no disponible(s).";

        $this->jsonSuccess([
            'mensaje'        => implode(' ', $partes),
            'enviados'       => $enviados,
            'sin_email'      => $sinEmail,
            'con_error'      => $conError,
            'sin_saldo'      => $sinSaldo,
            'no_encontrados' => $noEncontrados,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVIAR WHATSAPP
    // ─────────────────────────────────────────────────────────────────────

    public function enviarWhatsappAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $idVenta      = (int)($_POST['id_venta'] ?? 0);
        $telefono     = preg_replace('/[^0-9]/', '', trim($_POST['telefono'] ?? ''));
        $nombrePlant  = trim($_POST['template_name'] ?? '');

        if ($idVenta <= 0 || strlen($telefono) < 7 || !$nombrePlant) {
            $this->jsonError('Datos incompletos.');
            return;
        }

        if (str_starts_with($telefono, '593') && strlen($telefono) !== 12) {
            $this->jsonError('El número de teléfono para Ecuador (593) debe tener exactamente 12 dígitos.');
            return;
        }

        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        // 1. OBTENER PLANTILLA Y VALIDARLA
        $stmt = $this->repo->getDb()->prepare("SELECT * FROM whatsapp_plantillas WHERE nombre = ? AND id_empresa = ? AND estado_meta = 'APPROVED'");
        $stmt->execute([$nombrePlant, $idEmpresa]);
        $plantillaMeta = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantillaMeta) {
            $this->jsonError('Plantilla no válida o no aprobada por Meta.');
            return;
        }

        $idioma = $plantillaMeta['idioma'];

        // 2. SALDO PENDIENTE (ya viene calculado por getFacturaParaCobro con las
        // mismas CTEs de cobrado/retenido/NC que usa el resto del módulo)
        $saldoReal = max(0, (float) ($factura['saldo'] ?? 0));

        // 3. GENERAR PDF SI ES NECESARIO
        $waService = new WhatsappService();
        $mediaId = null;

        if ($nombrePlant === 'factura_por_cobrar') {
            $ventasRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $detalles = $ventasRepo->getDetalles($idVenta);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $ventasRepo->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);
            $pagos = $ventasRepo->getPagos($idVenta);
            $infoAdicional = $ventasRepo->getInfoAdicional($idVenta);

            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa) ?? [];
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos)) {
                if (!empty($establecimientos[0]['logo_ruta'])) $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                if (!empty($establecimientos[0]['direccion'])) $empresa['direccion_establecimiento'] = $establecimientos[0]['direccion'];
            }

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');

            if ($plantillaPdf) {
                $pdfString = $renderer->generar($plantillaPdf, $factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            } else {
                $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                $pdfString = $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            }

            if (empty($pdfString)) {
                $this->jsonError('No se pudo generar el PDF de la factura.');
                return;
            }

            $tmpPdfPath = sys_get_temp_dir() . '/factura_' . $idVenta . '_' . time() . '.pdf';
            file_put_contents($tmpPdfPath, $pdfString);

            $uploadResult = $waService->uploadMessageMedia($idEmpresa, $tmpPdfPath, 'application/pdf');
            unlink($tmpPdfPath);

            if (!$uploadResult['success']) {
                $this->jsonError('Error subiendo PDF a Meta: ' . $uploadResult['message']);
                return;
            }
            $mediaId = $uploadResult['media_id'];
        }

        // 4. CONSTRUIR COMPONENTES (API COMPONENTS)
        $componentesDB = json_decode($plantillaMeta['componentes'], true) ?? [];
        $apiComponents = [];

        $numeroFactura = ($factura['establecimiento'] ?? '') . '-' . ($factura['punto_emision'] ?? '') . '-' . ($factura['secuencial'] ?? '');
        $nombreCliente = $factura['cliente_nombre'] ?? 'Cliente';
        $saldoFormateado = number_format($saldoReal, 2);

        foreach ($componentesDB as $comp) {
            $type = $comp['type'] ?? '';

            if ($type === 'HEADER' && ($comp['format'] ?? '') === 'DOCUMENT' && $mediaId) {
                $apiComponents[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'id' => $mediaId,
                                'filename' => 'Factura_' . $numeroFactura . '.pdf'
                            ]
                        ]
                    ]
                ];
            } elseif ($type === 'BODY') {
                $texto = $comp['text'] ?? '';
                if (preg_match_all('/{{(\d+)}}/', $texto, $matches)) {
                    $numVars = max($matches[1]);
                    $parameters = [];
                    for ($i = 1; $i <= $numVars; $i++) {
                        $val = '';
                        
                        if ($nombrePlant === 'factura_por_cobrar') {
                            // 1: Cliente, 2: Saldo, 3: Número
                            if ($i == 1) $val = $nombreCliente;
                            elseif ($i == 2) $val = '$' . $saldoFormateado;
                            elseif ($i == 3) $val = $numeroFactura;
                        } elseif ($nombrePlant === 'cuenta_por_cobrar') {
                            // 1: Cliente, 2: Saldo
                            if ($i == 1) $val = $nombreCliente;
                            elseif ($i == 2) $val = '$' . $saldoFormateado;
                        } else {
                            $val = ' ';
                        }

                        $parameters[] = [
                            'type' => 'text',
                            'text' => (string) $val
                        ];
                    }

                    $apiComponents[] = [
                        'type' => 'body',
                        'parameters' => $parameters
                    ];
                }
            }
        }

        // 5. ENVIAR MENSAJE A META
        $result = $waService->sendTemplateMessage($idEmpresa, $telefono, $nombrePlant, $idioma, $apiComponents);

        if (!($result['success'] ?? false)) {
            $this->jsonError('Error al enviar WhatsApp: ' . ($result['message'] ?? 'Desconocido'));
            return;
        }

        // --- Guardar en la Base de Datos para el Webhook ---
        try {
            $metaMessageId = $result['data']['messages'][0]['id'] ?? null;
            $repoMsj = new \App\repositories\modulos\WhatsappMensajeRepository();
            $nombreCliente = $factura['cliente_nombre'] ?? 'Cliente';
            $idChat = $repoMsj->getOrCreateChat($idEmpresa, $telefono, $nombreCliente, 'Recordatorio de cuenta por cobrar', false);

            $variablesGuardar = [];
            foreach ($apiComponents as $comp) {
                if (strtolower($comp['type'] ?? '') === 'body') {
                    foreach ($comp['parameters'] ?? [] as $p) {
                        $variablesGuardar[] = $p['text'] ?? '';
                    }
                    break;
                }
            }

            $templateTextGuardar = '';
            foreach ($componentesDB as $comp) {
                if (($comp['type'] ?? '') === 'BODY') {
                    $templateTextGuardar = $comp['text'] ?? '';
                    foreach ($variablesGuardar as $idx => $val) {
                        $templateTextGuardar = str_replace('{{' . ($idx + 1) . '}}', $val, $templateTextGuardar);
                    }
                    break;
                }
            }

            $repoMsj->saveMessage(
                $idEmpresa,
                $idChat,
                'OUT',
                $telefono,
                'template',
                [
                    'template'      => $nombrePlant,
                    'variables'     => $variablesGuardar,
                    'template_text' => $templateTextGuardar,
                ],
                $metaMessageId,
                'sent'
            );
        } catch (\Throwable $ex) {
            error_log("Error guardando mensaje en BD (CXC): " . $ex->getMessage());
        }

        $this->log->registrar(
            (int)$_SESSION['id_usuario'],
            $idEmpresa,
            'WHATSAPP_CXC',
            'ventas_cabecera',
            $idVenta,
            null,
            ['telefono' => $telefono, 'template' => $nombrePlant]
        );

        $this->jsonSuccess(['mensaje' => 'WhatsApp enviado correctamente.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – BÚSQUEDA DE CLIENTES
    // ─────────────────────────────────────────────────────────────────────

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->jsonSuccess(['clientes' => []]);
            return;
        }

        $sql = "SELECT id, nombre, identificacion
                FROM clientes
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND (LOWER(nombre) LIKE :q OR identificacion LIKE :q2)
                ORDER BY nombre LIMIT 15";

        $st = $this->repo->getDb()->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':q' => '%' . strtolower($q) . '%', ':q2' => '%' . $q . '%']);
        $this->jsonSuccess(['clientes' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN EXCEL
    // ─────────────────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();

        $filas = $this->getFilasUnificadas($idEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';

            $headers = ['Documento', 'Origen', 'Cliente', 'RUC/Cédula', 'F.Emisión', 'F.Vencimiento', 'Días Vencidos', 'Total', 'Cobrado', 'Saldo', 'Estado'];

            $exportData = [];
            foreach ($filas as $r) {
                $dias = (int)($r['dias_vencido'] ?? 0);
                $estadoCxC = $dias > 0 ? "VENCIDA ({$dias} días)" : 'VIGENTE';
                $exportData[] = [
                    (string)($r['numero_factura'] ?? ''),
                    $this->getOrigenLabel($r['origen'] ?? 'FACTURA'),
                    (string)($r['cliente_nombre'] ?? ''),
                    (string)($r['cliente_ruc'] ?? ''),
                    $r['fecha_emision'] ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                    $r['fecha_vencimiento'] ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '',
                    $dias > 0 ? $dias : 0,
                    number_format((float)$r['total'], 2),
                    number_format((float)$r['total_cobrado'], 2),
                    number_format((float)$r['saldo'], 2),
                    $estadoCxC,
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('cuentas_por_cobrar', $headers, $exportData, 'Cuentas por Cobrar', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                $_SESSION['cuentas_por_cobrar_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . $this->getRutaModulo());
            }
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();

        $filas = $this->getFilasUnificadas($idEmpresa, $filtros);
        $stats = $this->repo->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';

            $totalSaldo   = 0;
            $totalCobrado = 0;
            $totalTotal   = 0;
            $filaHtml = '';
            foreach ($filas as $r) {
                $dias = (int)($r['dias_vencido'] ?? 0);
                $ts   = (float)$r['total'];
                $tc   = (float)$r['total_cobrado'];
                $tsal = (float)$r['saldo'];
                $totalTotal   += $ts;
                $totalCobrado += $tc;
                $totalSaldo   += $tsal;
                $badge = $dias > 0 ? "<small style='font-weight:bold;'> ({$dias}d vencida)</small>" : "<small>Vigente</small>";
                $fVenc = !empty($r['fecha_vencimiento']) ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '—';
                $fEmis = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $origenTxt = $this->getOrigenLabel($r['origen'] ?? 'FACTURA');
                $filaHtml .= "<tr>
                    <td style='width:13%;'>" . htmlspecialchars($r['numero_factura'] ?? '') . "</td>
                    <td class='text-center' style='width:9%;'>{$origenTxt}</td>
                    <td style='width:24%;'>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "</td>
                    <td class='text-center' style='width:11%;'>{$fEmis}</td>
                    <td class='text-center' style='width:16%;'>{$fVenc} {$badge}</td>
                    <td class='text-end' style='width:9%;'>\$" . number_format($ts, 2) . "</td>
                    <td class='text-end' style='width:9%;'>\$" . number_format($tc, 2) . "</td>
                    <td class='text-end' style='width:9%;font-weight:bold;'>\$" . number_format($tsal, 2) . "</td>
                </tr>";
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
                th { background: #e9ecef; border: 1px solid #ccc; padding: 4px 5px; text-align: center; font-size: 8pt; color: #000; }
                td { border: 1px solid #ddd; padding: 3px 5px; font-size: 7.5pt; overflow: hidden; word-wrap: break-word; color: #000; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 10px; }
                .header h2 { margin: 0 0 2px 0; font-size: 13pt; }
                .header h3 { margin: 0 0 2px 0; font-size: 10pt; }
                .header p  { margin: 0; font-size: 7.5pt; }
                table.stats td.stats-box { text-align: center; vertical-align: middle; padding: 6px 4px; border: 1px solid #ccc; }
                .stat-lbl  { font-size: 7.5pt; }
                .stat-val  { font-size: 11pt; font-weight: bold; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Cuentas por Cobrar</h3>
                <p>Generado: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table class="stats">
                <tr>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Facturas</span><br/>
                        <span class="stat-val"><?= $stats['total_facturas'] ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Saldo Total</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_saldo'], 2) ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Vencido</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_vencido'], 2) ?></span>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <span class="stat-lbl">Al Día</span><br/>
                        <span class="stat-val">$<?= number_format($stats['total_al_dia'], 2) ?></span>
                    </td>
                </tr>
            </table>
            <table>
                <thead>
                    <tr>
                        <th style="width:13%;">Documento</th>
                        <th style="width:9%;">Origen</th>
                        <th style="width:24%;">Cliente</th>
                        <th style="width:11%;">F. Emisión</th>
                        <th style="width:16%;">F. Vencimiento</th>
                        <th style="width:9%;">Total</th>
                        <th style="width:9%;">Cobrado</th>
                        <th style="width:9%;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $filaHtml ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <td colspan="5" class="text-end" style="width:73%;">TOTALES:</td>
                        <td class="text-end" style="width:9%;">$<?= number_format($totalTotal, 2) ?></td>
                        <td class="text-end" style="width:9%;">$<?= number_format($totalCobrado, 2) ?></td>
                        <td class="text-end" style="width:9%;">$<?= number_format($totalSaldo, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            </page>
            <?php
            $html     = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorCobrar_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS AUXILIARES
    // ─────────────────────────────────────────────────────────────────────

    private function getFiltros(): array
    {
        $tipoDoc = strtoupper(trim((string)($_REQUEST['tipo_doc'] ?? 'TODOS')));
        if (!in_array($tipoDoc, ['TODOS', 'FACTURA', 'RECIBO', 'SALDO_INICIAL'], true)) {
            $tipoDoc = 'TODOS';
        }
        return [
            'estado'      => $_REQUEST['estado']      ?? 'PENDIENTES',
            'tipo_doc'    => $tipoDoc,
            'fecha_desde' => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta' => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'  => $_REQUEST['id_cliente']  ?? '',
        ];
    }

    /** Etiqueta legible del origen de una fila del listado unificado. */
    private function getOrigenLabel(string $origen): string
    {
        return match ($origen) {
            'SALDO_INICIAL' => 'Saldo inicial',
            'RECIBO'        => 'Recibo',
            default         => 'Factura',
        };
    }

    private function renderEmailBody(array $factura, string $mensajeExtra, string $docLabel = 'Factura'): string
    {
        $nombre   = htmlspecialchars($factura['cliente_nombre'] ?? '');
        $nroFact  = htmlspecialchars($factura['numero_factura'] ?? '');
        $docLabel = htmlspecialchars($docLabel);
        $total    = '$' . number_format((float)($factura['importe_total'] ?? 0), 2);
        $saldo    = '$' . number_format((float)($factura['saldo'] ?? 0), 2);
        $vence    = !empty($factura['fecha_vencimiento'])
                    ? date('d-m-Y', strtotime($factura['fecha_vencimiento']))
                    : '—';
        $msg      = nl2br(htmlspecialchars($mensajeExtra));

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#333;}
.card{background:#f8f9fa;border-left:4px solid #e63946;padding:16px 20px;margin:16px 0;border-radius:4px;}
.label{color:#6c757d;font-size:12px;text-transform:uppercase;margin-bottom:2px;}
.value{font-size:16px;font-weight:bold;}
.saldo{color:#e63946;font-size:22px;}
.footer{color:#aaa;font-size:11px;margin-top:20px;}
</style></head><body>
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>Le recordamos que tiene un saldo pendiente de pago correspondiente a:</p>
<div class="card">
  <div><div class="label">{$docLabel}</div><div class="value">{$nroFact}</div></div>
  <div style="margin-top:10px;"><div class="label">Total {$docLabel}</div><div class="value">{$total}</div></div>
  <div style="margin-top:10px;"><div class="label">Saldo Pendiente</div><div class="saldo">{$saldo}</div></div>
  <div style="margin-top:10px;"><div class="label">Fecha de Vencimiento</div><div class="value">{$vence}</div></div>
</div>
{$msg}
<p>Por favor, regularice su cuenta a la brevedad posible. Si ya realizó el pago, por favor ignorer este mensaje.</p>
<p class="footer">Este es un mensaje automático. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }

    /**
     * Cuerpo HTML del correo masivo: tabla resumen de los documentos
     * pendientes de un cliente (facturas y recibos) con el total. Estilos
     * inline para máxima compatibilidad con clientes de correo.
     */
    private function renderEmailBodyResumen(string $nombreCliente, array $docs): string
    {
        $nombre = htmlspecialchars($nombreCliente);
        $nDocs  = count($docs);
        $intro  = $nDocs === 1
            ? 'el siguiente documento'
            : "los siguientes <strong>{$nDocs} documentos</strong>";

        $tdBase  = 'padding:6px 10px;border-bottom:1px solid #e9ecef;font-size:13px;';
        $filas   = '';
        $totalPend = 0.0;
        foreach ($docs as $d) {
            $totalPend += (float)$d['saldo'];
            $numero   = htmlspecialchars(($d['tipo'] ?? 'Factura') . ' ' . ($d['numero'] ?? ''));
            $fEmis    = !empty($d['fecha_emision']) ? date('d-m-Y', strtotime($d['fecha_emision'])) : '—';
            $fVenc    = !empty($d['fecha_venc'])    ? date('d-m-Y', strtotime($d['fecha_venc']))    : '—';
            $dias     = (int)($d['dias_vencido'] ?? 0);
            $vencTxt  = $dias > 0
                ? "<div style=\"color:#e63946;font-size:11px;font-weight:bold;\">Vencido {$dias} día" . ($dias === 1 ? '' : 's') . "</div>"
                : '';
            $totalFmt = number_format((float)$d['total'], 2);
            $saldoFmt = number_format((float)$d['saldo'], 2);
            $filas .= "<tr>
                <td style=\"{$tdBase}\">{$numero}</td>
                <td style=\"{$tdBase}text-align:center;\">{$fEmis}</td>
                <td style=\"{$tdBase}text-align:center;\">{$fVenc}{$vencTxt}</td>
                <td style=\"{$tdBase}text-align:right;\">\${$totalFmt}</td>
                <td style=\"{$tdBase}text-align:right;font-weight:bold;color:#e63946;\">\${$saldoFmt}</td>
            </tr>";
        }
        $totalPendFmt = number_format($totalPend, 2);

        $thBase = 'padding:8px 10px;font-size:12px;';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;">
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>Le recordamos que mantiene un saldo pendiente de pago en {$intro}:</p>
<table style="border-collapse:collapse;width:100%;max-width:680px;background:#f8f9fa;" cellpadding="0" cellspacing="0">
  <thead>
    <tr style="background:#343a40;color:#ffffff;">
      <th style="{$thBase}text-align:left;">Documento</th>
      <th style="{$thBase}text-align:center;">Emisión</th>
      <th style="{$thBase}text-align:center;">Vencimiento</th>
      <th style="{$thBase}text-align:right;">Total</th>
      <th style="{$thBase}text-align:right;">Saldo</th>
    </tr>
  </thead>
  <tbody>{$filas}</tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="padding:10px;text-align:right;font-weight:bold;border-top:2px solid #343a40;">TOTAL PENDIENTE:</td>
      <td style="padding:10px;text-align:right;font-weight:bold;color:#e63946;font-size:18px;border-top:2px solid #343a40;white-space:nowrap;">\${$totalPendFmt}</td>
    </tr>
  </tfoot>
</table>
<p>Por favor, regularice su cuenta a la brevedad posible. Si ya realizó el pago de alguno de estos documentos, por favor ignore este mensaje.</p>
<p style="color:#aaa;font-size:11px;margin-top:20px;">Este es un mensaje automático. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }

    // ─── SALDOS INICIALES CXC (para mostrar en la vista de CXC) ─────────────

    public function getSaldosInicialesCxcAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = [
            'estado'     => $_GET['estado']     ?? 'TODOS',
            'id_cliente' => $_GET['id_cliente'] ?? '',
        ];
        $filas = $this->repo->getSaldosInicialesCxc($idEmpresa, $filtros);
        $this->jsonSuccess(['filas' => $filas]);
    }

    /**
     * Cobro de un saldo inicial CXC desde la tabla unificada de Cuentas por Cobrar.
     * Delega en SaldosInicialesService::registrarCobroCxc (mismo flujo de ingresos).
     */
    public function registrarCobroSaldoInicialAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idSaldo = (int)($_POST['id_saldo'] ?? 0);
        $idPunto = (int)($_POST['id_punto_emision'] ?? 0);
        $monto   = (float)($_POST['monto'] ?? 0);
        $idForma = (int)($_POST['id_forma_cobro'] ?? 0);

        if ($idSaldo <= 0 || $idPunto <= 0 || $monto <= 0 || $idForma <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('Punto de emisión no válido.');
            return;
        }

        try {
            $service = new \App\Services\modulos\SaldosInicialesService(
                new \App\repositories\modulos\SaldosInicialesRepository(),
                new \App\Rules\modulos\SaldosInicialesRules(),
                new \App\Services\LogSistemaService()
            );
            $result = $service->registrarCobroCxc($idSaldo, $idEmpresa, $idUsuario, [
                'id_punto_emision'       => $idPunto,
                'punto'                  => $punto,
                'monto'                  => $monto,
                'id_forma_cobro'         => $idForma,
                'id_ingreso_concepto'    => !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null,
                'fecha_cobro'            => $_POST['fecha_cobro'] ?? date('Y-m-d'),
                'observaciones'          => $_POST['observaciones'] ?? '',
                'tipo_operacion_bancaria'=> $_POST['tipo_operacion_bancaria'] ?? '',
                'numero_operacion'       => $_POST['numero_operacion'] ?? '',
            ]);
            $this->jsonSuccess(array_merge($result, [
                'mensaje'     => "Cobro registrado correctamente. Ingreso: {$result['numero_ingreso']}",
                'nuevo_saldo' => $result['nuevo_saldo'] ?? null,
                'pagada'      => $result['pagado'] ?? false,
            ]));
        } catch (\Throwable $e) {
            error_log('[CxC cobro saldo inicial] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    /**
     * Historial de cobros de un saldo inicial CXC (para la tabla unificada).
     */
    public function historialCobrosSaldoInicialAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idSaldo   = (int)($_GET['id_saldo'] ?? 0);
        if ($idSaldo <= 0) {
            $this->jsonError('ID de saldo inválido.');
            return;
        }
        $repo = new \App\repositories\modulos\SaldosInicialesRepository();
        $historial = $repo->getHistorialCobrosCxc($idSaldo, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }
}
