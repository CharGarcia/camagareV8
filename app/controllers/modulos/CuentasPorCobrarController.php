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
     * saldos iniciales por cobrar, en una sola tabla. Cada fila lleva un campo
     * `origen` ('FACTURA' | 'SALDO_INICIAL') para distinguirla y enrutar acciones.
     * Los saldos iniciales se filtran por el mismo estado/cliente del listado.
     */
    private function getFilasUnificadas(int $idEmpresa, array $filtros): array
    {
        // Facturas
        $facturas = $this->repo->getListado($idEmpresa, $filtros);
        foreach ($facturas as &$f) { $f['origen'] = 'FACTURA'; }
        unset($f);

        // Saldos iniciales (todos; se filtran en PHP por el mismo estado del listado)
        $saldos = $this->repo->getSaldosInicialesCxc($idEmpresa, [
            'estado'      => 'TODOS',
            'id_cliente'  => $filtros['id_cliente'] ?? '',
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
        ]);

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
                'total_nc'          => 0,
                'saldo'             => $s['saldo_pendiente'],
                'dias_vencido'      => (int)($s['dias_vencido'] ?? 0),
            ];
        }

        $filas = array_merge($facturas, $filasSI);

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

        try {
            // Obtener siguiente secuencial mediante SecuencialService
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

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'        => "Cobro registrado correctamente. Ingreso: {$numDoc}",
                'id_ingreso'     => $idIngreso,
                'numero_ingreso' => $numDoc,
                'nuevo_saldo'    => number_format($nuevoSaldo, 2, '.', ''),
                'pagada'         => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
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

        if ($idVenta <= 0 || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Datos incompletos o email inválido.');
            return;
        }

        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        $asuntoFinal = $asunto ?: 'Recordatorio de pago — Factura ' . ($factura['numero_factura'] ?? '');
        $htmlBody    = $this->renderEmailBody($factura, $mensaje);

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
            'ventas_cabecera',
            $idVenta,
            null,
            ['email' => $emailDest]
        );

        $this->jsonSuccess(['mensaje' => 'Correo enviado correctamente a ' . $emailDest]);
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

        // 2. CÁLCULO PRECISO DEL SALDO PENDIENTE
        $totalFactura = (float)($factura['importe_total'] ?? 0);
        
        // Abonos
        $stmtAbonos = $this->repo->getDb()->prepare("
            SELECT COALESCE(SUM(id2.monto_cobrado), 0)
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
            WHERE id2.tipo_documento = 'FACTURA' 
              AND id2.id_referencia_documento = ?
              AND ic.estado != 'anulado'
              AND ic.eliminado = false
        ");
        $stmtAbonos->execute([$idVenta]);
        $totalAbonos = (float)$stmtAbonos->fetchColumn();

        // Notas de Crédito
        $stmtNC = $this->repo->getDb()->prepare("
            SELECT COALESCE(SUM(importe_total), 0)
            FROM notas_credito_cabecera 
            WHERE id_factura = ? AND id_empresa = ? AND estado = 'autorizado' AND eliminado = false
        ");
        $stmtNC->execute([$idVenta, $idEmpresa]);
        $totalNC = (float)$stmtNC->fetchColumn();

        // Retenciones
        $stmtRet = $this->repo->getDb()->prepare("
            SELECT COALESCE(SUM(importe_total), 0)
            FROM retencion_venta_cabecera 
            WHERE id_venta = ? AND id_empresa = ? AND estado = 'autorizado' AND eliminado = false
        ");
        $stmtRet->execute([$idVenta, $idEmpresa]);
        $totalRetenciones = (float)$stmtRet->fetchColumn();

        $saldoReal = $totalFactura - $totalAbonos - $totalNC - $totalRetenciones;
        $saldoReal = max(0, $saldoReal); // No permitir saldo negativo visualmente

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

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cuentas_por_cobrar_' . date('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $headers = ['Documento', 'Origen', 'Cliente', 'RUC/Cédula', 'F.Emisión', 'F.Vencimiento', 'Días Vencidos', 'Total', 'Cobrado', 'Saldo', 'Estado'];
        echo implode("\t", $headers) . "\n";

        foreach ($filas as $r) {
            $dias = (int)($r['dias_vencido'] ?? 0);
            $estadoCxC = $dias > 0 ? "VENCIDA ({$dias} días)" : ($dias <= 0 ? 'VIGENTE' : '');
            echo implode("\t", [
                $r['numero_factura'] ?? '',
                (($r['origen'] ?? 'FACTURA') === 'SALDO_INICIAL') ? 'Saldo inicial' : 'Factura',
                $r['cliente_nombre'] ?? '',
                $r['cliente_ruc']    ?? '',
                $r['fecha_emision']  ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                $r['fecha_vencimiento'] ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '',
                $dias > 0 ? $dias : 0,
                number_format((float)$r['total'], 2),
                number_format((float)$r['total_cobrado'], 2),
                number_format((float)$r['saldo'], 2),
                $estadoCxC,
            ]) . "\n";
        }
        exit;
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
                $color = $dias > 0 ? 'color:#dc3545;' : '';
                $badge = $dias > 0 ? "<small style='color:#dc3545;font-weight:bold;'> ({$dias}d vencida)</small>" : "<small style='color:#198754;'>Vigente</small>";
                $fVenc = !empty($r['fecha_vencimiento']) ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '—';
                $fEmis = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $origenTxt = (($r['origen'] ?? 'FACTURA') === 'SALDO_INICIAL') ? 'Saldo inicial' : 'Factura';
                $filaHtml .= "<tr style='{$color}'>
                    <td>" . htmlspecialchars($r['numero_factura'] ?? '') . "</td>
                    <td class='text-center'>{$origenTxt}</td>
                    <td>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "<br><small style='color:#6c757d;'>" . htmlspecialchars($r['cliente_ruc'] ?? '') . "</small></td>
                    <td class='text-center'>{$fEmis}</td>
                    <td class='text-center'>{$fVenc} {$badge}</td>
                    <td class='text-end'>\$" . number_format($ts, 2) . "</td>
                    <td class='text-end' style='color:#198754;'>\$" . number_format($tc, 2) . "</td>
                    <td class='text-end' style='{$color}font-weight:bold;'>\$" . number_format($tsal, 2) . "</td>
                </tr>";
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                th { background: #e9ecef; border: 1px solid #ccc; padding: 4px 5px; text-align: center; font-size: 8pt; }
                td { border: 1px solid #ddd; padding: 3px 5px; font-size: 7.5pt; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 12px; }
                .stats { display: table; width: 100%; margin-bottom: 10px; }
                .stat-box { display: table-cell; border: 1px solid #ccc; padding: 5px; text-align: center; }
                .stat-val { font-size: 11pt; font-weight: bold; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Cuentas por Cobrar</h3>
                <p>Generado: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <div class="stats">
                <div class="stat-box">
                    <div>Facturas</div>
                    <div class="stat-val"><?= $stats['total_facturas'] ?></div>
                </div>
                <div class="stat-box">
                    <div>Saldo Total</div>
                    <div class="stat-val">$<?= number_format($stats['total_saldo'], 2) ?></div>
                </div>
                <div class="stat-box" style="color:#dc3545;">
                    <div>Vencido</div>
                    <div class="stat-val">$<?= number_format($stats['total_vencido'], 2) ?></div>
                </div>
                <div class="stat-box" style="color:#198754;">
                    <div>Al Día</div>
                    <div class="stat-val">$<?= number_format($stats['total_al_dia'], 2) ?></div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Origen</th>
                        <th>Cliente / RUC</th>
                        <th>F. Emisión</th>
                        <th>F. Vencimiento</th>
                        <th>Total</th>
                        <th>Cobrado</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $filaHtml ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <th colspan="5" class="text-end">TOTALES:</th>
                        <th class="text-end">$<?= number_format($totalTotal, 2) ?></th>
                        <th class="text-end" style="color:#198754;">$<?= number_format($totalCobrado, 2) ?></th>
                        <th class="text-end" style="color:#dc3545;">$<?= number_format($totalSaldo, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
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
        return [
            'estado'      => $_REQUEST['estado']      ?? 'PENDIENTES',
            'fecha_desde' => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta' => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'  => $_REQUEST['id_cliente']  ?? '',
        ];
    }

    private function renderEmailBody(array $factura, string $mensajeExtra): string
    {
        $nombre   = htmlspecialchars($factura['cliente_nombre'] ?? '');
        $nroFact  = htmlspecialchars($factura['numero_factura'] ?? '');
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
  <div><div class="label">Factura</div><div class="value">{$nroFact}</div></div>
  <div style="margin-top:10px;"><div class="label">Total Factura</div><div class="value">{$total}</div></div>
  <div style="margin-top:10px;"><div class="label">Saldo Pendiente</div><div class="saldo">{$saldo}</div></div>
  <div style="margin-top:10px;"><div class="label">Fecha de Vencimiento</div><div class="value">{$vence}</div></div>
</div>
{$msg}
<p>Por favor, regularice su cuenta a la brevedad posible. Si ya realizó el pago, por favor ignorer este mensaje.</p>
<p class="footer">Este es un mensaje automático. Por favor no responda a este correo.</p>
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
