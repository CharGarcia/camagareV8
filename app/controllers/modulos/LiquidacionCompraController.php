<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Services\modulos\LiquidacionCompraService;
use App\repositories\modulos\LiquidacionCompraRepository;
use App\models\Empresa;

class LiquidacionCompraController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/liquidacion-compra';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new LiquidacionCompraRepository();
        $rules            = new \App\Rules\modulos\LiquidacionCompraRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new LiquidacionCompraService($this->repository, $rules, $logService);
    }

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

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $secRepo = new \App\repositories\SecuencialRepository();
            foreach ($empresaModel->getPuntosEmision((int) $establecimientos[0]['id']) as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Liquidación de compras o servicios');
                if (empty($config['id'])) {
                    continue;
                }
                $puntos[] = $p;
            }
        }

        $total = $result['total'];
        $this->viewWithLayout('layouts.main', 'modulos/liquidacion_compra/index', [
            'titulo'              => 'Liquidaciones de Compras y Servicios',
            'perm'                => $perm,
            'rows'                => $result['rows'],
            'total'               => $total,
            'page'                => $page,
            'totalPages'          => $totalPages,
            'perPage'             => $perPage,
            'from'                => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'                  => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'              => $buscar,
            'ordenCol'            => $ordenCol,
            'ordenDir'            => $ordenDir,
            'vistaConfig'         => $prefsVista,
            'base'                => BASE_URL,
            'rutaModulo'          => $this->getRutaModulo(),
            'empresa'             => $empresaData,
            'formasPago'          => $this->repository->getFormasPago(),
            'tarifasIva'          => $this->repository->getTarifasIva(),
            'sustentos'           => $this->repository->getSustentosTributarios(),
            'puntos'              => $puntos,
            'fullWidth'           => true,
            'sucursal_principal'  => !empty($establecimientos) ? $establecimientos[0] : null
        ]);
    }

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
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No se encontraron liquidaciones.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData      = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero       = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                $fecha        = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $estado       = $r['estado'] ?? 'borrador';
                $estadoClass  = match ($estado) {
                    'aprobado', 'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                    'anulado'                => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default                  => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge  = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                echo '<tr class="liquidacion-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalLiquidacionVer(this)">
                        <td class="ps-3" data-col="secuencial"><code class="text-secondary">' . $numero . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td class="fw-medium text-truncate" style="max-width:250px" data-col="proveedor_nombre">' . htmlspecialchars($r['proveedor_nombre'] ?? '—') . '</td>
                        <td data-col="proveedor_ruc"><small class="text-muted">' . htmlspecialchars($r['proveedor_ruc'] ?? '—') . '</small></td>
                        <td class="text-end" data-col="total_sin_impuestos">$' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                        <td class="text-end" data-col="total_descuento">$' . number_format((float)($r['total_descuento'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                        <td data-col="usuario_nombre">' . htmlspecialchars($r['usuario_nombre'] ?? '—') . '</td>
                        <td class="text-center" data-col="estado_correo">—</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'totalPages' => $totalPages
        ]);
        exit;
    }

    public function getLiquidacionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Liquidación no encontrada']);
            exit;
        }

        $detalles = $this->repository->getDetalles($id);
        // Impuestos EN LOTE: una sola consulta para todas las líneas, en vez
        // de una por línea. Con la base en un servidor remoto, un documento
        // largo pagaba un viaje de red por cada ítem.
        $impuestosPorDetalle = $this->repository->getImpuestosPorDetalles(array_column($detalles, 'id'));
        foreach ($detalles as &$d) {
            $d['impuestos'] = $impuestosPorDetalle[(int) $d['id']] ?? [];
        }
        unset($d);

        $egresosVinculados = $this->repository->getEgresosVinculados($id);
        
        // Obtener total de retenciones para el resumen de pagos
        $repoRet = new \App\repositories\modulos\RetencionCompraRepository();
        $retenciones = $repoRet->getPorCompra(0, $idEmpresa, $id);
        $totalRet = 0;
        foreach ($retenciones as $r) {
            if (strtolower($r['estado'] ?? '') !== 'anulada') {
                $totalRet += (float)($r['total_retenido'] ?? 0);
            }
        }

        echo json_encode([
            'ok'                 => true,
            'cabecera'           => $cabecera,
            'detalles'           => $detalles,
            'pagos'              => $this->repository->getPagos($id),
            'egresos_vinculados' => $egresosVinculados,
            'total_retenido'     => $totalRet,
            'info_adicional'     => $this->repository->getInfoAdicional($id),
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            if (!empty($_POST['id']) || (isset($_POST['data']) && !empty(json_decode($_POST['data'], true)['id']))) {
                $this->requireActualizar();
            } else {
                $this->requireCrear();
            }

            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            // Cargar configuración de la empresa para que el service pueda usarla.
            // Fusionar la config del establecimiento (decimales, etc.) para que el
            // XML use los mismos decimales que el sistema.
            $empresaModel = new \App\models\Empresa();
            $empresaData  = $empresaModel->getPorId($data['id_empresa']) ?? [];
            try {
                $establecimientos = $empresaModel->getEstablecimientos($data['id_empresa']);
                if (!empty($establecimientos)) {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $empresaData = array_merge($empresaData, $estConfig);
                    }
                }
            } catch (\Throwable $e) {}
            $data['empresa_config'] = $empresaData;

            // Procesar impuestos para cada detalle
            if (!empty($data['detalles'])) {
                $tarifasIva = $this->repository->getTarifasIva();
                $tarifasMap = [];
                foreach ($tarifasIva as $t) {
                    $tarifasMap[$t['id']] = $t;
                }

                foreach ($data['detalles'] as &$det) {
                    $det['id_empresa'] = $data['id_empresa'];
                    $idTarifa = (int) ($det['id_tarifa_iva'] ?? 0);
                    $neto = (float) $det['total'];
                    
                    if (isset($tarifasMap[$idTarifa])) {
                        $tarifa = $tarifasMap[$idTarifa];
                        $porcentaje = (float) $tarifa['porcentaje_iva'];
                        $ivaValor = $neto * ($porcentaje / 100);
                        
                        $det['impuestos'] = [[
                            'codigo_impuesto'   => '2', // IVA
                            'codigo_porcentaje' => (string) ($tarifa['codigo'] ?? '0'),
                            'tarifa'            => $porcentaje,
                            'base_imponible'    => $neto,
                            'valor'             => $ivaValor
                        ]];
                    } else {
                        $det['impuestos'] = [];
                    }

                    // Asegurar campos para el repositorio
                    $det['codigo_principal'] = trim((string)($det['codigo'] ?? ''));
                    $det['precio_total_sin_impuesto'] = $neto;
                    $det['info_adicional'] = $det['adicional'] ?? '';
                }
                unset($det);
            }

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;
            
            if ($idExistente > 0) {
                $id = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Liquidación actualizada exitosamente.';
            } else {
                $id = $this->service->crear($data);
                $mensaje = 'Liquidación guardada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            // Loguear error para depuración
            error_log("Error en LiquidacionCompraController::guardarAjax: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
        }
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        // Búsqueda filtrada: Solo Cédula (02) y Pasaporte (03) en este sistema
        $db = \App\core\Database::getConnection();
        $sql = "SELECT p.id, p.razon_social as nombre, p.identificacion, p.tipo_id_proveedor as tipo_id, p.email 
                FROM proveedores p
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor AND icv.tipo = 1
                WHERE p.id_empresa = ? AND p.eliminado = false 
                  AND (
                    p.tipo_id_proveedor IN ('05', '5', '06', '6') 
                    OR icv.nombre ILIKE '%CEDULA%' 
                    OR icv.nombre ILIKE '%CÉDULA%' 
                    OR icv.nombre ILIKE '%PASAPORTE%'
                  )
                  AND (p.razon_social ILIKE ? OR p.identificacion ILIKE ?)
                ORDER BY p.razon_social ASC LIMIT 15";

        $st = $db->prepare($sql);
        $st->execute([$idEmpresa, "%$buscar%", "%$buscar%"]);
        $result = $st->fetchAll();

        echo json_encode(['ok' => true, 'data' => $result]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, null, true);

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Liquidación anulada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

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
            $ok = $this->service->eliminar($id, $idEmpresa, $idUsuario);
            if ($ok) {
                echo json_encode(['ok' => true, 'mensaje' => 'Liquidación eliminada correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipoDoc = 'Liquidación de compras o servicios';

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, $tipoDoc);

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ── SRI ──────────────────────────────────────────────────────────────────

    public function enviarSriAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $envioService = new \App\Services\Sri\SriEnvioService();
            $resultado    = $envioService->enviarLiquidacionCompra($id, $idEmpresa, $idUsuario);

            echo json_encode([
                'ok'                  => $resultado['ok'],
                'estado'              => $resultado['estado'],
                'mensaje'             => $resultado['mensaje'],
                'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
                'fecha_autorizacion'  => $resultado['fecha_autorizacion']  ?? '',
                'errores'             => $resultado['errores'] ?? [],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            try {
                $cab = $this->repository->getPorId($id);
                if ($cab && (int)$cab['id_empresa'] === $idEmpresa) {
                    (new \App\models\SriEnvioLog())->registrar([
                        'id_empresa'       => $idEmpresa,
                        'tipo_comprobante' => 'liquidacion_compra',
                        'id_comprobante'   => $id,
                        'clave_acceso'     => $cab['clave_acceso'] ?? null,
                        'tipo_ambiente'    => $cab['tipo_ambiente'] ?? '1',
                        'accion'           => 'error',
                        'estado_sri'       => 'ERROR',
                        'mensaje'          => $e->getMessage(),
                        'created_by'       => $idUsuario,
                    ]);
                }
            } catch (\Throwable) {}
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { echo json_encode(['ok' => false, 'data' => []]); exit; }

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('liquidacion_compra', $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    public function eliminarLogSriAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idLog     = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$idLog) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        $model = new \App\models\SriEnvioLog();
        $log   = $model->getPorId($idLog, $idEmpresa);

        if (!$log) {
            echo json_encode(['ok' => false, 'mensaje' => 'Registro no encontrado.']);
            exit;
        }
        if ($log['tipo_ambiente'] !== '1') {
            echo json_encode(['ok' => false, 'mensaje' => 'Los registros de producción no se pueden eliminar.']);
            exit;
        }

        echo json_encode(['ok' => $model->eliminar($idLog, $idEmpresa)]);
        exit;
    }

    public function getEgresoDependenciesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db = \App\core\Database::getConnection();

        // Mismas fuentes/consultas que Compras (ComprasController::getEgresoDependenciesAjax): la
        // versión anterior usaba nombres de tabla/columna inexistentes (empresa_puntos_emision,
        // empresa_bancos, tipo='EGRESO', estado=true) → fatal → respuesta vacía → "Unexpected end
        // of JSON input" al abrir la pestaña Pagos.
        try {
            // Formas de pago internas (mismo repo/criterio que Compras)
            $repoFP = new \App\repositories\modulos\FormaPagoRepository();
            $formas = $repoFP->getFormasFiltradas($idEmpresa, 'EGRESO');

            // Conceptos de egreso
            $stC = $db->prepare("SELECT id, nombre, comportamiento FROM empresa_opciones_ingreso_egreso
                                 WHERE id_empresa = ? AND aplica_egresos = TRUE AND eliminado = FALSE ORDER BY nombre ASC");
            $stC->execute([$idEmpresa]);
            $conceptos = $stC->fetchAll(\PDO::FETCH_ASSOC);

            // Puntos de emisión activos (para reservar el secuencial del egreso)
            $stPto = $db->prepare("SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
                                   FROM empresa_punto_emision pe
                                   JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
                                   WHERE es.id_empresa = ? AND pe.eliminado = FALSE AND es.eliminado = FALSE
                                     AND LOWER(pe.estado) = 'activo'
                                   ORDER BY es.codigo ASC, pe.codigo_punto ASC");
            $stPto->execute([$idEmpresa]);
            $puntos = $stPto->fetchAll(\PDO::FETCH_ASSOC);

            // Bancos (catálogo global)
            $bancos = $db->query("SELECT id, nombre_banco FROM bancos_ecuador WHERE status = 1 ORDER BY nombre_banco ASC")->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => [
                'puntos'      => $puntos,
                'conceptos'   => $conceptos,
                'formas_pago' => $formas,
                'bancos'      => $bancos,
            ]]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function registrarEgresoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || empty($data['id_compra'])) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos o ID faltante.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $cab = $this->repository->getPorId((int)$data['id_compra']);
            if (!$cab) throw new \Exception('Liquidación no encontrada.');

            $egresoService = new \App\Services\modulos\EgresoService(
                new \App\repositories\modulos\EgresoRepository(),
                new \App\Rules\modulos\EgresoRules(),
                new \App\Services\LogSistemaService()
            );

            // Adaptar payload para el service de Egresos
            $payload = [
                'id_empresa'         => $idEmpresa,
                'id_punto_emision'   => (int)$data['id_punto_emision'],
                'id_egreso_concepto' => (int)$data['id_egreso_concepto'],
                'fecha_emision'      => $data['fecha_emision'],
                'monto_total'        => (float)$data['monto_pagar'],
                'tipo_egreso'        => 'PAGO',
                'tipo_sujeto'        => 'PROVEEDOR',
                'id_proveedor'       => $cab['id_proveedor'],
                'observaciones'      => $data['observaciones'] ?? '',
                'usuario_id'         => $idUsuario,
                'detalles'           => [
                    [
                        'tipo_documento'           => 'LIQUIDACION',
                        'id_referencia_documento'  => (int)$data['id_compra'],
                        'monto_pagado'             => (float)$data['monto_pagar'],
                        'monto_documento'          => (float)$cab['importe_total'],
                        'numero_documento'         => "{$cab['establecimiento']}-{$cab['punto_emision']}-{$cab['secuencial']}",
                        'descripcion'              => $data['observaciones'] ?? ''
                    ]
                ],
                'pagos' => [
                    [
                        'id_forma_pago'           => (int)$data['id_forma_pago'],
                        'monto'                   => (float)$data['monto_pagar'],
                        'referencia'              => $data['numero_operacion'] ?? '',
                        'tipo_operacion_bancaria' => $data['tipo_operacion_bancaria'] ?? null,
                        // Cheque: número y fecha en que se podrá cobrar (control de posfechados)
                        'numero_cheque'           => (($data['tipo_operacion_bancaria'] ?? '') === 'CHEQUE') ? ($data['numero_operacion'] ?? null) : null,
                        'fecha_cobro'             => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null,
                        'banco_id'                => !empty($data['banco_id']) ? (int)$data['banco_id'] : null
                    ]
                ]
            ];

            $idEgreso = $egresoService->registrar($payload);
            echo json_encode(['ok' => true, 'msg' => 'Pago registrado con éxito.', 'id_egreso' => $idEgreso]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        $cab = $this->repository->getPorId($id);
        if (!$cab || (int)($cab['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404); echo 'Liquidación no encontrada'; exit;
        }

        $numero   = ($cab['establecimiento'] ?? '001') . '-'
                  . ($cab['punto_emision']   ?? '001') . '-'
                  . str_pad((string)($cab['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'liq_' . $numero . '.xml';

        // Servir desde detalle_xml si existe
        if (!empty($cab['detalle_xml'])) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $cab['detalle_xml'];
            exit;
        }

        // Fallback: regenerar y persistir
        try {
            $detalles = $this->repository->getDetalles($id);
            // Impuestos EN LOTE: una sola consulta para todas las líneas, en vez
            // de una por línea. Con la base en un servidor remoto, un documento
            // largo pagaba un viaje de red por cada ítem.
            $impuestosPorDetalle = $this->repository->getImpuestosPorDetalles(array_column($detalles, 'id'));
            foreach ($detalles as &$d) {
                $d['impuestos'] = $impuestosPorDetalle[(int) $d['id']] ?? [];
            }
            unset($d);

            $pagos         = $this->repository->getPagos($id);
            $infoAdicional = $this->repository->getInfoAdicional($id);
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cab['id_establecimiento'])) {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$cab['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            }

            $xml = (new \App\Services\Xml\XmlLiquidacionCompraService())
                ->generar($cab, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

            $this->repository->updateDetalleXml($id, $xml);

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $xml;
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error generando XML: ' . $e->getMessage();
        }
        exit;
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM liquidaciones_cabecera
                    WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
            $st  = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $count = (int) $st->fetchColumn();
            echo json_encode(['ok' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exportación del LISTADO (respeta filtro y orden actuales)
    // ─────────────────────────────────────────────────────────────────────────

    /** Filas del listado con los mismos filtros/orden de la vista, sin paginar. */
    private function filasParaExport(): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? '');
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // perPage = 0 => sin LIMIT (todas las filas del filtro actual).
        $data = $this->repository->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'] ?? [];
    }

    /** Exporta el listado a PDF. */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

        try {
            $empresaModel  = new Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
            ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7.5pt; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:3px; text-align:left; }
                td { border:1px solid #ccc; padding:3px; }
                .r { text-align:right; }
                h2 { font-family:Arial,sans-serif; font-size:12pt; margin:0 0 2px 0; }
                .sub { font-family:Arial,sans-serif; font-size:8pt; color:#555; margin-bottom:6px; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="6mm" backright="6mm">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <div class="sub">Listado de Liquidaciones de Compra &mdash; <?= date('d-m-Y H:i:s') ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:11%">Nº Liquidación</th>
                            <th style="width:9%">Fecha</th>
                            <th style="width:26%">Proveedor</th>
                            <th style="width:11%">Identificación</th>
                            <th style="width:9%" class="r">Subtotal</th>
                            <th style="width:8%" class="r">Descuento</th>
                            <th style="width:9%" class="r">Total</th>
                            <th style="width:9%">Usuario</th>
                            <th style="width:8%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $numero = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($numero) ?></td>
                            <td><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                            <td><?= htmlspecialchars((string) ($r['proveedor_nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['proveedor_ruc'] ?? '')) ?></td>
                            <td class="r"><?= number_format((float) ($r['total_sin_impuestos'] ?? 0), 2) ?></td>
                            <td class="r"><?= number_format((float) ($r['total_descuento'] ?? 0), 2) ?></td>
                            <td class="r"><?= number_format((float) ($r['importe_total'] ?? 0), 2) ?></td>
                            <td><?= htmlspecialchars((string) ($r['usuario_nombre'] ?? '-')) ?></td>
                            <td><?= ucfirst((string) ($r['estado'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Liquidaciones_compra_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    /** Exporta el listado a Excel. */
    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

        try {
            $empresaModel  = new Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Nº Liquidación', 'Fecha', 'Proveedor', 'Identificación', 'Subtotal',
                        'Descuento', 'Total', 'Usuario', 'Observaciones', 'Estado'];

            $exportData = [];
            foreach ($rows as $r) {
                $numero = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
                $exportData[] = [
                    $numero,
                    !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-',
                    (string) ($r['proveedor_nombre'] ?? ''),
                    (string) ($r['proveedor_ruc'] ?? ''),
                    number_format((float) ($r['total_sin_impuestos'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['total_descuento'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['importe_total'] ?? 0), 2, '.', ''),
                    (string) ($r['usuario_nombre'] ?? '-'),
                    (string) ($r['observaciones'] ?? ''),
                    ucfirst((string) ($r['estado'] ?? '')),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Liquidaciones_de_Compra', $headers, $exportData, 'Liquidaciones de Compra', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF del documento
    // ─────────────────────────────────────────────────────────────────────────

    public function exportarPdfDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $datos = $this->cargarDatosDocumento($id, $idEmpresa);
            if (!$datos) { die('Liquidación no encontrada'); }

            $this->generarPdfLiquidacion($idEmpresa, $datos, 'D');
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Excel del documento
    // ─────────────────────────────────────────────────────────────────────────

    /** Etiqueta de la forma de pago SRI (igual mapa que LiquidacionCompraPdfService::formaPagoLabel). */
    private function formaPagoLabelExcel(string $cod): string
    {
        return match ($cod) {
            '01' => '01 - Sin utilización del sistema financiero',
            '15' => '15 - Compensación de deudas',
            '16' => '16 - Tarjeta de débito',
            '17' => '17 - Dinero electrónico',
            '18' => '18 - Tarjeta prepago',
            '19' => '19 - Tarjeta de crédito',
            '20' => '20 - Otros con utilización del sistema financiero',
            '21' => '21 - Endoso de títulos',
            default => $cod !== '' ? $cod : 'Sin especificar',
        };
    }

    public function exportarExcelDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $datos = $this->cargarDatosDocumento($id, $idEmpresa);
            if (!$datos) { http_response_code(404); echo 'Liquidación no encontrada'; exit; }

            $cabecera = $datos['cabecera'];
            $detalles = $datos['detalles'];
            $pagos    = $datos['pagos'];
            $empresa  = $datos['empresa'];

            $numero = str_pad((string)($cabecera['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string)($cabecera['punto_emision']   ?? '001'), 3, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string)($cabecera['secuencial']      ?? ''),   9, '0', STR_PAD_LEFT);

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Liquidación');

            $sheet->setCellValue('A1', strtoupper((string)($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'LIQUIDACIÓN DE COMPRA DE BIENES Y PRESTACIÓN DE SERVICIOS N.° ' . $numero);
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($cabecera['fecha_emision']) ? date('d-m-Y', strtotime((string)$cabecera['fecha_emision'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Proveedor: ' . (string)($cabecera['proveedor_nombre'] ?? ''));
            $sheet->setCellValue('A4', 'RUC/Identificación: ' . (string)($cabecera['proveedor_ruc'] ?? ''));
            $claveAcceso = trim((string)($cabecera['numero_autorizacion'] ?? '')) ?: trim((string)($cabecera['clave_acceso'] ?? ''));
            $sheet->setCellValue('C4', 'Autorización: ' . $claveAcceso);

            $headerRow = 6;
            $headers = ['Código', 'Descripción', 'Cantidad', 'P. Unitario', 'Descuento', 'Subtotal'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $headerRow, $h);
                $col++;
            }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];
            $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            $totalIva = 0.0;
            foreach ($detalles as $d) {
                $sheet->setCellValueExplicit('A' . $row, (string)($d['codigo_principal'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string)($d['descripcion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $row, (float)($d['cantidad'] ?? 0));
                $sheet->setCellValue('D' . $row, (float)($d['precio_unitario'] ?? 0));
                $sheet->setCellValue('E' . $row, (float)($d['descuento'] ?? 0));
                $sheet->setCellValue('F' . $row, (float)($d['precio_total_sin_impuesto'] ?? 0));
                $row++;

                foreach ($d['impuestos'] ?? [] as $imp) {
                    if ((int)($imp['codigo_impuesto'] ?? 0) === 2) {
                        $totalIva += (float)($imp['valor'] ?? 0);
                    }
                }
            }

            $sheet->getStyle('C' . ($headerRow + 1) . ':F' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');

            $subtotal   = (float)($cabecera['total_sin_impuestos'] ?? 0);
            $descuento  = (float)($cabecera['total_descuento'] ?? 0);
            $total      = (float)($cabecera['importe_total'] ?? 0);

            $row += 1;
            $totales = [
                'Subtotal sin impuestos' => $subtotal,
                'Descuento' => $descuento,
                'IVA' => $totalIva,
                'TOTAL' => $total,
            ];

            foreach ($totales as $label => $valor) {
                $sheet->setCellValue('E' . $row, $label);
                $sheet->getStyle('E' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('F' . $row, $valor);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            if (!empty($pagos)) {
                $row += 1;
                $sheet->setCellValue('A' . $row, 'Forma de pago');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                foreach ($pagos as $p) {
                    $sheet->setCellValueExplicit('A' . $row, $this->formaPagoLabelExcel((string)($p['forma_pago'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C' . $row, (float)($p['total'] ?? 0));
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Liquidacion_' . $numero . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombre . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Genera el PDF de una liquidación de compra usando la plantilla activa
     * (tipo 'liquidacion_compra') del módulo Plantillas de Documentos; si la
     * empresa no tiene una configurada, usa el diseño original hardcodeado.
     */
    private function generarPdfLiquidacion(int $idEmpresa, array $datos, string $outputDest)
    {
        $renderer  = new \App\Services\PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'liquidacion_compra');
        if ($plantilla) {
            return $renderer->generar(
                $plantilla, $datos['cabecera'], $datos['detalles'], $datos['pagos'], $datos['info_adicional'], $datos['empresa'], $outputDest
            );
        }
        $pdfService = new \App\Services\modulos\LiquidacionCompraPdfService();
        if ($outputDest === 'S') {
            return $pdfService->generarBytes(
                $datos['cabecera'], $datos['detalles'], $datos['pagos'], $datos['info_adicional'], $datos['empresa']
            );
        }
        return $pdfService->generar(
            $datos['cabecera'], $datos['detalles'], $datos['pagos'], $datos['info_adicional'], $datos['empresa']
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enviar / reenviar por correo (igual que factura de venta / retención)
    // ─────────────────────────────────────────────────────────────────────────

    public function reenviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            while (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $datos = $this->cargarDatosDocumento($id, $idEmpresa);
            if (!$datos) {
                while (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'Liquidación no encontrada.']);
                exit;
            }

            $cabecera = $datos['cabecera'];

            $pdfString = $this->generarPdfLiquidacion($idEmpresa, $datos, 'S');

            // XML autorizado ya persistido (o el generado como respaldo).
            $xmlString = (string)($cabecera['detalle_xml'] ?? '');

            $numAut = (string)($cabecera['numero_autorizacion'] ?? $cabecera['clave_acceso'] ?? '');

            $correosDestino = trim($_POST['correos'] ?? '');

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarSiAplica(
                $idEmpresa, 'liquidacion_compra', $cabecera, $xmlString, $pdfString, $numAut, true, $correosDestino
            );

            while (ob_get_level() > 0) ob_end_clean();
            if ($enviado) {
                $db = \App\core\Database::getConnection();
                $db->prepare("UPDATE liquidaciones_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = ? AND id_empresa = ?")
                   ->execute([$id, $idEmpresa]);
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración o el correo del destinatario.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            while (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Carga la cabecera + detalles (con impuestos) + pagos + info adicional + empresa
     * (enriquecida con logo/direcciones del establecimiento). Devuelve null si no existe
     * o no pertenece a la empresa activa.
     */
    private function cargarDatosDocumento(int $id, int $idEmpresa): ?array
    {
        if (!$id) return null;

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            return null;
        }

        $detalles = $this->repository->getDetalles($id);
        // Impuestos EN LOTE: una sola consulta para todas las líneas, en vez
        // de una por línea. Con la base en un servidor remoto, un documento
        // largo pagaba un viaje de red por cada ítem.
        $impuestosPorDetalle = $this->repository->getImpuestosPorDetalles(array_column($detalles, 'id'));
        foreach ($detalles as &$d) {
            $d['impuestos'] = $impuestosPorDetalle[(int) $d['id']] ?? [];
        }
        unset($d);

        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        // El logo y las direcciones viven en el establecimiento (igual que factura/retención).
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $est = null;
        if (!empty($cabecera['id_establecimiento'])) {
            foreach ($establecimientos as $e) {
                if ((int)$e['id'] === (int)$cabecera['id_establecimiento']) { $est = $e; break; }
            }
        }
        if ($est === null && !empty($establecimientos)) {
            $est = $establecimientos[0];
        }
        if ($est) {
            if (!empty($est['logo_ruta'])) {
                $empresa['logo_ruta'] = $est['logo_ruta'];
            }
            $empresa['direccion_matriz']          = $empresa['direccion'] ?? '';
            $empresa['direccion_establecimiento'] = $est['direccion'] ?? '';
        }

        return [
            'cabecera'       => $cabecera,
            'detalles'       => $detalles,
            'pagos'          => $this->repository->getPagos($id),
            'info_adicional' => $this->repository->getInfoAdicional($id),
            'empresa'        => $empresa,
        ];
    }
}

