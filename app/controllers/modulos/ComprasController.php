<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ComprasService;
use App\repositories\modulos\ComprasRepository;
use App\models\Empresa;

class ComprasController extends BaseModuloController
{
    private ComprasService    $service;
    private ComprasRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/compras';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ComprasRepository();
        $rules            = new \App\Rules\modulos\ComprasRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new ComprasService($this->repository, $rules, $logService);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = !empty($establecimientos) ? $empresaModel->getPuntosEmision((int)$establecimientos[0]['id']) : [];

        $this->viewWithLayout('layouts.main', 'modulos/compras/index', [
            'titulo'             => 'Compras',
            'perm'               => $perm,
            'rows'               => $result['rows'],
            'total'              => $total,
            'page'               => $page,
            'totalPages'         => $totalPages,
            'perPage'            => $perPage,
            'from'               => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'                 => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'             => $buscar,
            'ordenCol'           => $ordenCol,
            'ordenDir'           => $ordenDir,
            'vistaConfig'        => $prefsVista,
            'base'               => BASE_URL,
            'rutaModulo'         => $this->getRutaModulo(),
            'empresa'            => $empresaData,
            'formasPago'         => $this->repository->getFormasPago(),
            'tarifasIva'         => $this->repository->getTarifasIva(),
            'sustentos'          => $this->repository->getSustentosTributarios(),
            'puntos'             => $puntos,
            'establecimientos'   => $establecimientos,
            'sucursal_principal' => !empty($establecimientos) ? $establecimientos[0] : null,
            'tiposComprobante'   => (new \App\models\ComprobanteAutorizado())->getAll(),
            'unidadesMedida'     => (new \App\repositories\modulos\UnidadesMedidaRepository())->getActive($idEmpresa),
            'bodegas'            => (new \App\repositories\modulos\BodegaRepository())->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']),
            'fullWidth'          => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEARCH AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-cart fs-3 d-block mb-2"></i>No se encontraron compras.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero      = htmlspecialchars(($r['establecimiento_prov'] ?? '') . '-' . ($r['punto_emision_prov'] ?? '') . '-' . ($r['secuencial_prov'] ?? ''));
                $fechaEmision = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                
                $importeTotal = (float)($r['importe_total'] ?? 0);
                $pagado       = (float)($r['total_pagado'] ?? 0);
                $nc           = (float)($r['total_nc'] ?? 0);
                $retencion    = (float)($r['total_retencion'] ?? 0);
                $saldo        = max(0, $importeTotal - $pagado - $nc - $retencion);

                if ((string)($r['tipo_comprobante'] ?? '') === '04') {
                    // Las notas de crédito de compra son un crédito a favor: no se pagan → Pagada.
                    $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagada</span>';
                } elseif ($saldo <= 0.01) {
                    $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagada</span>';
                } elseif (($pagado + $nc + $retencion) > 0) {
                    $estadoPagoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Abonada</span>';
                } else {
                    $estadoPagoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Pendiente</span>';
                }

                $estado      = $r['estado'] ?? 'borrador';
                $estadoClass = match ($estado) {
                    'registrado'             => 'bg-success bg-opacity-10 text-success border-success',
                    'anulado'                => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default                  => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                echo '<tr class="compra-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalCompra(this)">
                        <td class="ps-3" data-col="secuencial_prov"><code class="text-secondary">' . $numero . '</code></td>
                        <td data-col="fecha_emision">' . $fechaEmision . '</td>
                        <td class="fw-medium text-truncate" style="max-width:220px" data-col="proveedor_nombre">' . htmlspecialchars($r['proveedor_nombre'] ?? '—') . '</td>
                        <td data-col="proveedor_ruc"><small class="text-muted">' . htmlspecialchars($r['proveedor_ruc'] ?? '—') . '</small></td>
                        <td data-col="tipo_comprobante"><small>' . htmlspecialchars($r['tipo_comprobante_nombre'] ?? $r['tipo_comprobante'] ?? '—') . '</small></td>
                        <td data-col="sustento_nombre" class="text-truncate" style="max-width:160px"><small class="text-muted">' . htmlspecialchars($r['sustento_nombre'] ?? '—') . '</small></td>
                        <td class="text-end" data-col="total_sin_impuestos">' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                        <td class="text-end" data-col="monto_iva">$' . number_format((float)($r['monto_iva'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="estado_pago">' . $estadoPagoBadge . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'totalPages' => $totalPages,
            'pdf_url'    => $urlBase . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET COMPRA AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getCompraAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            if (!$id) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
                exit;
            }

            $compra = $this->service->getPorId($id, $idEmpresa);
            if (!$compra) {
                echo json_encode(['ok' => false, 'mensaje' => 'Compra no encontrada']);
                exit;
            }

            echo json_encode(['ok' => true, 'data' => $compra]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
        exit;
    }

    /**
     * Vista previa del asiento contable de una compra (pestaña "Asiento contable" del modal).
     * Si la compra ya tiene asiento guardado, devuelve sus líneas; si no, devuelve la sugerencia
     * del builder ('adquisiciones_compras'). Para una compra nueva (id = 0) devuelve vacío.
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idCompra  = (int) ($_GET['id'] ?? $_GET['id_compra'] ?? 0);

        try {
            if ($idCompra <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $compra = $this->service->getPorId($idCompra, $idEmpresa);
            if (!$compra) {
                echo json_encode(['ok' => true, 'detalles' => []]);
                exit;
            }

            // 1. Si ya existe asiento guardado, devolver sus líneas.
            $idAsiento = (int) ($compra['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
                $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
                $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, new \App\Services\LogSistemaService());
                $cab = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);

                $detalles = [];
                foreach (($cab['detalles'] ?? []) as $det) {
                    $detalles[] = [
                        'id_cuenta_contable'   => (int) $det['id_cuenta_contable'],
                        'cuenta_codigo'        => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                        'cuenta_nombre'        => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                        'debe'                 => (float) $det['debe'],
                        'haber'                => (float) $det['haber'],
                        'referencia_detalle'   => $det['referencia_detalle'] ?? '',
                        'documento_referencia' => $det['documento_referencia'] ?? '',
                    ];
                }
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                exit;
            }

            // 2. Sin asiento guardado: sugerencia del builder (lee detalles/impuestos de la compra).
            $builder = new \App\Services\modulos\AsientoBuilderService();
            $detalles = $builder->generarAsientoSugerido($idEmpresa, 'adquisiciones_compras', [
                'id_compra'    => $idCompra,
                'id_empresa'   => $idEmpresa,
                'id_proveedor' => (int)($compra['id_proveedor'] ?? 0),
            ]);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESCARGAR XML AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function descargarXmlAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            http_response_code(400);
            echo 'ID requerido';
            exit;
        }

        $db  = \App\core\Database::getConnection();
        $st  = $db->prepare(
            "SELECT detalle_xml, establecimiento_prov, punto_emision_prov, secuencial_prov
               FROM compras_cabecera
              WHERE id = ? AND id_empresa = ? AND eliminado = false
              LIMIT 1"
        );
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['detalle_xml'])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'mensaje' => 'Este documento no tiene XML almacenado.']);
            exit;
        }

        $numero   = ($row['establecimiento_prov'] ?? '000') . '-'
                  . ($row['punto_emision_prov']   ?? '000') . '-'
                  . ($row['secuencial_prov']       ?? $id);
        $filename = 'compra_' . $numero . '.xml';

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Content-Length: ' . strlen($row['detalle_xml']));

        echo $row['detalle_xml'];
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORTAR PDF
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera el PDF de la compra a partir del XML del comprobante electrónico
     * (mismo formato del RIDE de Facturas de Venta). Solo aplica a compras con
     * XML almacenado; las compras físicas/manuales no tienen XML.
     */
    public function exportarPdfAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $compra = $this->service->getPorId($id, $idEmpresa);
            if (!$compra) { http_response_code(404); echo 'Compra no encontrada'; exit; }

            $xmlComprobante = trim((string) ($compra['detalle_xml'] ?? ''));
            if ($xmlComprobante === '') {
                http_response_code(422);
                echo 'Esta compra no tiene XML almacenado. El PDF solo está disponible para comprobantes electrónicos.';
                exit;
            }

            // Datos del documento tomados EXCLUSIVAMENTE del XML.
            $parsed = $this->service->parsearComprobanteXml($xmlComprobante);

            $cabecera = $parsed['cabecera'];
            // Metadatos internos de la compra (no forman parte del documento del proveedor).
            $cabecera['observaciones']  = $compra['observaciones'] ?? '';
            $cabecera['deducible']      = $compra['deducible'] ?? '';
            $cabecera['fecha_registro'] = $compra['fecha_registro'] ?? '';

            // Adquirente (comprador) = datos del receptor tomados del XML.
            // Los decimales de presentación se toman de la config de la empresa.
            $empresaModel = new \App\models\Empresa();
            $empresaCfg   = $empresaModel->getPorId($idEmpresa) ?? [];
            $empresa = [
                'decimales_cantidad' => $empresaCfg['decimales_cantidad'] ?? 2,
                'decimales_precio'   => $empresaCfg['decimales_precio']   ?? 2,
                'nombre'             => $parsed['comprador']['nombre'],
                'ruc'                => $parsed['comprador']['ruc'],
                'direccion'          => $parsed['comprador']['direccion'],
                'tipo_ambiente'      => $cabecera['tipo_ambiente'] ?? '1',
            ];

            $pdfService = new \App\Services\modulos\ComprasPdfService();
            // 'D' = forzar descarga del archivo PDF.
            $pdfService->generar($cabecera, $parsed['detalles'], $parsed['pagos'], $parsed['infoAdicional'], $empresa, 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }


    public function getEgresoDependenciesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db = \App\core\Database::getConnection();
        
        try {
            $repoFP = new \App\repositories\modulos\FormaPagoRepository();
            $formas = $repoFP->getFormasFiltradas($idEmpresa, 'EGRESO');
            
            $sqlC = "SELECT id, nombre, comportamiento FROM empresa_opciones_ingreso_egreso 
                     WHERE id_empresa = ? AND aplica_egresos = TRUE AND eliminado = FALSE ORDER BY nombre ASC";
            $stC = $db->prepare($sqlC);
            $stC->execute([$idEmpresa]);
            $conceptos = $stC->fetchAll(\PDO::FETCH_ASSOC);
            
            // Puntos de emisión activos para reservar secuencial correlativo del egreso
            $stPto = $db->prepare("
                SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
                WHERE es.id_empresa = ? AND pe.eliminado = FALSE AND es.eliminado = FALSE
                  AND LOWER(pe.estado) = 'activo'
                ORDER BY es.codigo ASC, pe.codigo_punto ASC
            ");
            $stPto->execute([$idEmpresa]);
            $puntos = $stPto->fetchAll(\PDO::FETCH_ASSOC);
            
            // Obtener también bancos de ecuador si alguna forma de pago requiere banco
            $stBancos = $db->query("SELECT id, nombre_banco FROM bancos_ecuador WHERE status = 1 ORDER BY nombre_banco ASC");
            $bancos = $stBancos->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'ok' => true,
                'data' => [
                    'formas_pago' => $formas,
                    'conceptos' => $conceptos,
                    'puntos' => $puntos,
                    'bancos' => $bancos
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function registrarEgresoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        
        try {
            $post = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $idCompra = (int) ($post['id_compra'] ?? 0);
            $montoPagar = (float) ($post['monto_pagar'] ?? 0);
            
            if ($idCompra <= 0) throw new \Exception("ID de compra inválido.");
            if ($montoPagar <= 0) throw new \Exception("El monto a pagar debe ser mayor a cero.");
            
            // Obtener los datos de la compra para pre-armar el egreso
            $compra = $this->repository->getPorId($idCompra, $idEmpresa);
            if (!$compra) throw new \Exception("Compra no encontrada.");
            
            // Armar el payload para el EgresoService
            // Necesitamos secuencial correlativo
            $idPunto = (int)($post['id_punto_emision'] ?? 0);
            if ($idPunto <= 0) throw new \Exception("Debe seleccionar un Punto de emisión.");
            
            $db = \App\core\Database::getConnection();
            $stPto = $db->prepare("
                SELECT pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
                WHERE pe.id = ? AND es.id_empresa = ? AND pe.eliminado = FALSE
            ");
            $stPto->execute([$idPunto, $idEmpresa]);
            $pto = $stPto->fetch(\PDO::FETCH_ASSOC);
            if (!$pto) throw new \Exception("El punto de emisión no existe o está inactivo.");
            
            $secuencialService = new \App\Services\SecuencialService();
            $rSec = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
            $secuencial = (string) ($rSec['formateado'] ?? '');
            
            if (empty($secuencial)) throw new \Exception("Error al reservar correlativo para el Egreso.");
            
            $est = str_pad((string)($pto['estab'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $ptoCod = str_pad((string)($pto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $numEgreso = "{$est}-{$ptoCod}-{$secuencial}";
            
            $fechaEgreso = !empty($post['fecha_emision']) ? $post['fecha_emision'] : date('Y-m-d');
            $idConcepto = (int) ($post['id_egreso_concepto'] ?? 0);
            $idFormaPago = (int) ($post['id_forma_pago'] ?? 0);
            $tipoOp = !empty($post['tipo_operacion_bancaria']) ? trim($post['tipo_operacion_bancaria']) : null;
            $numDoc = "{$compra['establecimiento_prov']}-{$compra['punto_emision_prov']}-{$compra['secuencial_prov']}";
            
            // Validar saldo anterior
            $saldoAnterior = (float)($post['saldo_actual'] ?? $compra['importe_total']);
            
            $dataEgreso = [
                'id_empresa'         => $idEmpresa,
                'usuario_id'         => $idUsuario,
                'fecha_emision'      => $fechaEgreso,
                'establecimiento'    => $est,
                'punto_emision'      => $ptoCod,
                'secuencial'         => $secuencial,
                'numero_egreso'      => $numEgreso,
                'id_punto_emision'   => $idPunto,
                'id_establecimiento' => (int)$pto['id_estab'],
                'tipo_egreso'        => 'COMPRA',
                'tipo_sujeto'        => 'PROVEEDOR',
                'id_proveedor'       => (int)$compra['id_proveedor'],
                'id_egreso_concepto' => $idConcepto,
                'monto_total'        => $montoPagar,
                'observaciones'      => !empty($post['observaciones']) ? trim($post['observaciones']) : "Pago de Compra #{$numDoc}",
                'estado'             => 'registrado',
                'detalles'           => [
                    [
                        'tipo_documento'           => 'COMPRA',
                        'id_referencia_documento'  => $idCompra,
                        'numero_documento'         => $numDoc,
                        'descripcion'              => "Liquidación de Compra #{$numDoc}",
                        'monto_documento'          => (float)$compra['importe_total'],
                        'saldo_anterior'           => $saldoAnterior,
                        'monto_pagado'             => $montoPagar,
                        'saldo_actual'             => max(0.0, $saldoAnterior - $montoPagar)
                    ]
                ],
                'pagos' => [
                    [
                        'id_forma_pago'             => $idFormaPago,
                        'monto'                     => $montoPagar,
                        'fecha_cobro'               => ($tipoOp === 'CHEQUE' && !empty($post['fecha_cobro'])) ? $post['fecha_cobro'] : $fechaEgreso,
                        'tipo_operacion_bancaria'   => $tipoOp,
                        'numero_cheque'             => ($tipoOp === 'CHEQUE') ? (!empty($post['numero_operacion']) ? trim($post['numero_operacion']) : null) : null,
                        'referencia'                => !empty($post['numero_operacion']) ? trim($post['numero_operacion']) : (!empty($post['observaciones']) ? trim($post['observaciones']) : null),
                    ]
                ]
            ];
            
            $egresoRepo = new \App\repositories\modulos\EgresoRepository();
            $egresoRules = new \App\Rules\modulos\EgresoRules();
            $logService = new \App\Services\LogSistemaService();
            $egresoService = new \App\Services\modulos\EgresoService($egresoRepo, $egresoRules, $logService);
            
            $idEgreso = $egresoService->registrar($dataEgreso);
            
            echo json_encode([
                'ok' => true,
                'msg' => "Pago registrado y Egreso #{$numEgreso} generado con éxito.",
                'id_egreso' => $idEgreso
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GUARDAR AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true) ?? [];
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $idExistente = !empty($data['id']) ? (int)$data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id      = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Compra actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id      = $this->service->crear($data);
                $mensaje = 'Compra registrada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            $db = \App\core\Database::getConnection();
            if ($db->inTransaction()) $db->rollBack();
            error_log("ComprasController::guardarAjax: " . $e->getMessage());
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }



    // ─────────────────────────────────────────────────────────────────────────
    // ELIMINAR AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $db = \App\core\Database::getConnection();
            $db->beginTransaction();

            // 1. Obtener detalles para revertir inventario
            $detalles = $this->repository->getDetalles($id);

            $invRepo = new \App\repositories\modulos\InventarioRepository();
            $invSrv  = new \App\Services\modulos\InventarioService($invRepo, new \App\Services\LogSistemaService());

            foreach ($detalles as $det) {
                // Revertir movimientos asociados a cada ítem (mismo referencia_tipo usado al insertar en procesarInventarioAjax)
                $invSrv->revertirMovimientosPorReferencia('compra', (int)$det['id'], $idEmpresa, $idUsuario);
            }

            // 2. Eliminar la compra (lógico)
            $this->service->eliminar($id, $idUsuario, $idEmpresa);

            $db->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Compra eliminada correctamente.']);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROVEEDORES AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $db  = \App\core\Database::getConnection();
        $sql = "SELECT p.id, p.razon_social AS nombre, p.identificacion,
                       p.tipo_id_proveedor AS tipo_id, p.email, p.plazo,
                       p.relacionado, p.unidad_tiempo,
                       COALESCE(icv.nombre, '') AS tipo_id_nombre
                FROM proveedores p
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
                WHERE p.id_empresa = ? AND p.eliminado = false
                  AND (p.razon_social ILIKE ? OR p.identificacion ILIKE ?)
                ORDER BY p.razon_social ASC
                LIMIT 20";

        $st = $db->prepare($sql);
        $st->execute([$idEmpresa, "%$buscar%", "%$buscar%"]);
        echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRODUCTOS AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ProductoRepository();
        // Filtrar para mostrar solo productos que permiten 'compra'
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'compra', true);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RETENCIONES AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getRetencionesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $tipoImpuesto = strtoupper(trim($_GET['tipo'] ?? ''));
        $buscar       = trim($_GET['q'] ?? '');

        $data = $this->repository->getRetencionesDisponibles($tipoImpuesto, $buscar);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUSTENTOS AJAX
    // ─────────────────────────────────────────────────────────────────────────
    public function getSustentosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $tipoComprobante = trim($_GET['tipo'] ?? '');
        $sustentoModel = new \App\models\SustentoTributario();

        if ($tipoComprobante === '') {
            $data = $sustentoModel->getAll();
        } else {
            $data = $sustentoModel->getPorTipoComprobante($tipoComprobante);
        }

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CUENTAS PLAN AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getCuentasPlanAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $data = $this->repository->getCuentasPlan($idEmpresa, $buscar);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO AUTOMÁTICO AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getAsientoAutomaticoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $data = json_decode($_POST['data'] ?? '{}', true) ?? [];
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $asiento = $this->service->generarAsientoAutomatico(0, $data);
            echo json_encode(['ok' => true, 'asiento' => $asiento]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECUENCIAL RETENCIÓN AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getSecuencialRetencionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Retenciones de compras');

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESTABLECIMEINTOS / PUNTOS AJAX
    // ─────────────────────────────────────────────────────────────────────────

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel     = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int)$_SESSION['id_empresa']);
        echo json_encode(['ok' => true, 'data' => $establecimientos]);
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEst        = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new Empresa();
        $puntos       = $empresaModel->getPuntosEmision($idEst);
        echo json_encode(['ok' => true, 'data' => $puntos]);
        exit;
    }

    public function procesarInventarioAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $data = json_decode($_POST['data'] ?? '{}', true) ?? [];
            $idCompra = (int)($data['id_compra'] ?? 0);
            $items    = $data['items'] ?? [];
            $idEmpresa = (int)$_SESSION['id_empresa'];
            $idUsuario = (int)$_SESSION['id_usuario'];

            if (!$idCompra || empty($items)) {
                throw new \Exception('Datos insuficientes para procesar inventario.');
            }

            // Obtener datos de cabecera para las observaciones
            $db = \App\core\Database::getConnection();
            $sqlCab = "SELECT c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov, p.razon_social AS proveedor_nombre 
                       FROM compras_cabecera c 
                       JOIN proveedores p ON c.id_proveedor = p.id 
                       WHERE c.id = ? AND c.id_empresa = ?";
            $stCab = $db->prepare($sqlCab);
            $stCab->execute([$idCompra, $idEmpresa]);
            $cab = $stCab->fetch();

            $numeroDoc = ($cab['establecimiento_prov'] ?? '000') . '-' . ($cab['punto_emision_prov'] ?? '000') . '-' . ($cab['secuencial_prov'] ?? $idCompra);
            $obsBase = "Ingreso por Compra #" . $numeroDoc . " - " . ($cab['proveedor_nombre'] ?? '');

            $invRepo = new \App\repositories\modulos\InventarioRepository();
            $invSrv  = new \App\Services\modulos\InventarioService($invRepo, new \App\Services\LogSistemaService());

            // Obtener reglas de facturación/inventario de la empresa (primer establecimiento)
            $empRepo = new \App\repositories\modulos\EmpresaRepository();
            $idEst   = $empRepo->getPrimerEstablecimientoId($idEmpresa);
            $config  = $empRepo->getEstablecimientoConfig($idEst) ?? [];

            $db->beginTransaction();
            error_log("INICIO PROCESAR INVENTARIO: Empresa $idEmpresa, Usuario $idUsuario, Compra $idCompra");
            $processedCount = 0;
            foreach ($items as $item) {
                $idDetalle = (int)($item['id_detalle'] ?? 0);
                if ($idDetalle <= 0) {
                    throw new \Exception("ID de detalle de compra inválido.");
                }

                // 1. Obtener datos del detalle de compra
                $sqlDet = "SELECT cd.cantidad, cd.descripcion AS descripcion_original, 
                                  cd.codigo_principal, c.id_proveedor
                           FROM compras_detalle cd
                           JOIN compras_cabecera c ON cd.id_compra = c.id
                           WHERE cd.id = ? AND cd.id_compra = ?";
                $stDet = $db->prepare($sqlDet);
                $stDet->execute([$idDetalle, $idCompra]);
                $det = $stDet->fetch();

                if (!$det) throw new \Exception("Detalle de compra #{$idDetalle} no encontrado.");

                $cantidadComprada = (float)$det['cantidad'];
                $idProducto = (int)$item['id_producto'];

                if ($idProducto <= 0) {
                    throw new \Exception("El ítem '{$det['descripcion_original']}' debe estar vinculado a un producto del catálogo.");
                }

                // Obtener nombre para mensajes
                $sqlProd = "SELECT nombre FROM productos WHERE id = ? AND id_empresa = ?";
                $stProd = $db->prepare($sqlProd);
                $stProd->execute([$idProducto, $idEmpresa]);
                $nombreAMostrar = $stProd->fetchColumn() ?: $det['descripcion_original'];

                // 2. Validaciones básicas y de configuración
                $cantEnviar = (float)$item['cantidad'];
                if ($cantEnviar <= 0) throw new \Exception("La cantidad para '{$nombreAMostrar}' debe ser mayor a 0.");

                if (empty($item['id_medida'])) throw new \Exception("Debe seleccionar una medida para '{$nombreAMostrar}'.");
                if (empty($item['id_bodega'])) throw new \Exception("Debe seleccionar una bodega para '{$nombreAMostrar}'.");

                // Reglas obligatorias por configuración de empresa
                if (($config['obligatorio_lotes'] ?? 'false') === 'true' && empty($item['lote'])) {
                    throw new \Exception("El Lote es OBLIGATORIO para '{$nombreAMostrar}' según la configuración.");
                }
                if (($config['obligatorio_caducidad'] ?? 'false') === 'true' && empty($item['caducidad'])) {
                    throw new \Exception("La Fecha de Caducidad es OBLIGATORIA para '{$nombreAMostrar}' según la configuración.");
                }
                if (($config['obligatorio_nup'] ?? 'false') === 'true' && empty($item['nup'])) {
                    throw new \Exception("El NUP (Serial) es OBLIGATORIO para '{$nombreAMostrar}' según la configuración.");
                }

                // 3. Validar saldo pendiente (No exceder lo comprado)
                $sqlSum = "SELECT ROUND(COALESCE(SUM(cantidad), 0), 2) FROM inventario_kardex 
                           WHERE referencia_tipo = 'compra' AND referencia_id = ? AND eliminado = false";
                $stSum = $db->prepare($sqlSum);
                $stSum->execute([$idDetalle]);
                $yaProcesado = (float)$stSum->fetchColumn();

                if (($yaProcesado + $cantEnviar) > ($cantidadComprada + 0.0001)) {
                    $disponible = max(0, $cantidadComprada - $yaProcesado);
                    throw new \Exception("Excede lo comprado para '{$nombreAMostrar}'. Comprado: {$cantidadComprada}, Procesado: {$yaProcesado}, Disponible: {$disponible}.");
                }

                // 4. Calcular stock actual para trazabilidad en Kardex
                $sqlStock = "SELECT ROUND(COALESCE(SUM(cantidad), 0), 2) FROM inventario_kardex 
                             WHERE id_empresa = ? AND id_producto = ? AND id_bodega = ? AND eliminado = false";
                $stStock = $db->prepare($sqlStock);
                $stStock->execute([$idEmpresa, $idProducto, (int)$item['id_bodega']]);
                $stockAnt = (float)$stStock->fetchColumn();
                $stockPost = $stockAnt + $cantEnviar;

                // 5. Registrar movimiento en Kardex DIRECTO
                $sqlInsK = "INSERT INTO inventario_kardex (
                                id_empresa, id_producto, id_bodega, id_medida, tipo_movimiento,
                                referencia_tipo, referencia_id, fecha_movimiento, cantidad, costo_unitario, costo_total,
                                stock_anterior, stock_posterior, numero_lote, fecha_caducidad, nup,
                                observaciones, created_by, updated_by, eliminado
                            ) VALUES (
                                ?, ?, ?, ?, 'entrada',
                                'compra', ?, CURRENT_TIMESTAMP, ?, ?, ?,
                                ?, ?, ?, ?, ?,
                                ?, ?, ?, false
                            ) RETURNING id";

                $loteVal = !empty($item['lote']) ? $item['lote'] : null;
                $cadVal  = !empty($item['caducidad']) ? $item['caducidad'] : null;
                $nupVal  = !empty($item['nup']) ? $item['nup'] : null;
                $medVal  = !empty($item['id_medida']) ? (int)$item['id_medida'] : null;

                $stInsK = $db->prepare($sqlInsK);
                $stInsK->execute([
                    $idEmpresa,
                    $idProducto,
                    (int)$item['id_bodega'],
                    $medVal,
                    $idDetalle,
                    $cantEnviar,
                    (float)$item['costo'],
                    round($cantEnviar * (float)$item['costo'], 2),
                    $stockAnt,
                    $stockPost,
                    $loteVal,
                    $cadVal,
                    $nupVal,
                    $obsBase . " (Item: " . $idDetalle . ")",
                    $idUsuario,
                    $idUsuario
                ]);
                $idKardex = (int)$stInsK->fetchColumn();
                error_log("KARDEX INSERTADO: ID $idKardex, Producto $idProducto, Cantidad $cantEnviar");

                // 6. Actualizar stock en productos_bodegas (Caché)
                $sqlPB = "INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_actual, created_by, updated_by, eliminado)
                          VALUES (?, ?, ?, ?, ?, ?, false)
                          ON CONFLICT (id_producto, id_bodega) 
                          DO UPDATE SET 
                             stock_actual = EXCLUDED.stock_actual,
                             updated_by = EXCLUDED.updated_by,
                             updated_at = CURRENT_TIMESTAMP,
                             eliminado = false";
                $stPB = $db->prepare($sqlPB);
                $stPB->execute([$idEmpresa, $idProducto, (int)$item['id_bodega'], $stockPost, $idUsuario, $idUsuario]);

                // 7. Vincular permanentemente el producto en el detalle de la compra
                $sqlUpd = "UPDATE compras_detalle SET id_producto = ? WHERE id = ?";
                $stUpd = $db->prepare($sqlUpd);
                $stUpd->execute([$idProducto, $idDetalle]);

                // 8. Registro de Auditoría (Log)
                $sqlLog = "INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_nuevos, created_at, ip_usuario)
                           VALUES (?, ?, ?, 'inventario_kardex', ?, ?, CURRENT_TIMESTAMP, ?)";
                $stLog = $db->prepare($sqlLog);
                $datosLog = json_encode([
                    'id_producto' => $idProducto,
                    'cantidad'    => $cantEnviar,
                    'bodega'      => (int)$item['id_bodega'],
                    'referencia'  => "Compra #{$idCompra} - Item {$idDetalle}"
                ]);
                $ipUser = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $stLog->execute([$idUsuario, $idEmpresa, 'INGRESO_INVENTARIO_COMPRA', $idKardex, $datosLog, $ipUser]);

                // Homologación automática
                if (!empty($det['codigo_principal']) && $idProducto > 0) {
                    $homologModel = new \App\models\ProductoHomologacion();
                    $homologModel->guardarVinculacion($idEmpresa, (int)$det['id_proveedor'], (string)$det['codigo_principal'], $idProducto, $idUsuario, (string)$det['descripcion_original']);
                }
                $processedCount++;
            }

            $db->commit();
            error_log("COMMIT REALIZADO: Procesados $processedCount");
            
            // Verificar persistencia real sin usar caché de consulta
            if ($processedCount > 0 && isset($idKardex)) {
                $stCheck = $db->prepare("SELECT id, id_empresa, referencia_tipo, referencia_id FROM inventario_kardex WHERE id = ?");
                $stCheck->execute([$idKardex]);
                $check = $stCheck->fetch(\PDO::FETCH_ASSOC);
                error_log("VERIFICACION POST-COMMIT (ID $idKardex): " . json_encode($check));
            }

            if ($processedCount === 0) {
                throw new \Exception("No se seleccionó ningún ítem válido para procesar o todos ya estaban ingresados.");
            }

            echo json_encode([
                'ok' => true,
                'mensaje' => "Inventario procesado con éxito. Se han registrado {$processedCount} movimientos."
            ]);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarMovimientoInventarioAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $idMov = (int)($_POST['id'] ?? 0);
            $idEmpresa = (int)$_SESSION['id_empresa'];
            $idUsuario = (int)$_SESSION['id_usuario'];

            if (!$idMov) throw new \Exception('ID de movimiento requerido.');

            $invRepo = new \App\repositories\modulos\InventarioRepository();
            $invSrv = new \App\Services\modulos\InventarioService($invRepo, new \App\Services\LogSistemaService());

            // Eliminamos ignorando la restricción manual porque es una acción controlada desde el módulo de origen
            $invSrv->eliminarMovimiento($idMov, $idEmpresa, $idUsuario, true);

            echo json_encode(['ok' => true, 'mensaje' => 'Movimiento eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getInventarioStatusAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idCompra = (int)($_GET['id_compra'] ?? 0);
            $idEmpresa = (int)$_SESSION['id_empresa'];

            if (!$idCompra) {
                throw new \Exception('ID de compra requerido.');
            }

            $sql = "SELECT k.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, 
                           b.nombre AS bodega_nombre, um.abreviatura AS medida_abreviatura
                    FROM inventario_kardex k
                    LEFT JOIN productos p ON k.id_producto = p.id
                    LEFT JOIN bodegas b ON k.id_bodega = b.id
                    LEFT JOIN unidades_medida um ON k.id_medida = um.id
                    JOIN compras_detalle d ON k.referencia_id = d.id AND k.referencia_tipo = 'compra'
                    WHERE d.id_compra = ? AND k.id_empresa = ? AND k.eliminado = false
                    ORDER BY k.id ASC";
            $db = \App\core\Database::getConnection();
            $st = $db->prepare($sql);
            $st->execute([$idCompra, $idEmpresa]);
            $movs = $st->fetchAll(\PDO::FETCH_ASSOC);


            echo json_encode(['ok' => true, 'data' => $movs]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getHomologacionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa  = (int) ($_SESSION['id_empresa'] ?? 0);
            $idProv     = (int) ($_GET['id_proveedor'] ?? 0);
            $codigoProv = trim($_GET['codigo_proveedor'] ?? '');

            if ($idProv <= 0 || $codigoProv === '') {
                echo json_encode(['ok' => true, 'data' => null]);
                exit;
            }

            $homModel = new \App\models\ProductoHomologacion();
            $idProducto = $homModel->getVinculacion($idEmpresa, $idProv, $codigoProv);

            if ($idProducto) {
                // Obtener datos del producto para el frontend
                $db = \App\core\Database::getConnection();
                $sql = "SELECT p.id, p.nombre, p.codigo, p.costo_producto as costo, p.id_medida, um.id_tipo AS id_tipo_medida 
                        FROM productos p 
                        LEFT JOIN unidades_medida um ON um.id = p.id_medida
                        WHERE p.id = ? AND p.eliminado = false";
                $st = $db->prepare($sql);
                $st->execute([$idProducto]);
                $producto = $st->fetch();
                echo json_encode(['ok' => true, 'data' => $producto]);
            } else {
                echo json_encode(['ok' => true, 'data' => null]);
            }
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
    public function guardarVinculacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int)$_SESSION['id_empresa'];
            $idUsuario = (int)$_SESSION['id_usuario'];
            $idProv    = (int)($_POST['id_proveedor'] ?? 0);
            $codigoProv = trim($_POST['codigo_proveedor'] ?? '');
            $idProd    = (int)($_POST['id_producto'] ?? 0);
            $desc      = trim($_POST['descripcion'] ?? '');

            if (!$idProv || $codigoProv === '' || !$idProd) {
                throw new \Exception('Datos insuficientes para guardar vinculación.');
            }

            $homologModel = new \App\models\ProductoHomologacion();
            $homologModel->guardarVinculacion($idEmpresa, $idProv, $codigoProv, $idProd, $idUsuario, $desc);

            echo json_encode(['ok' => true, 'mensaje' => 'Vinculación guardada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
