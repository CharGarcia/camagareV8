<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\RetornoCvRepository;
use App\Rules\modulos\RetornoCvRules;
use App\Services\LogSistemaService;
use App\Services\modulos\RetornoCvService;
use Exception;

/**
 * Retornos de Consignaciones en Ventas.
 *
 * Registra la devolución (entrada de inventario) de mercadería que el cliente
 * recibió en una o varias consignaciones de venta.
 */
class RetornosCvController extends BaseModuloController
{
    private RetornoCvService $service;
    private const RUTA_MODULO = 'modulos/retornos-cv';
    private const TIPO_SECUENCIAL = 'Retornos consignaciones ventas';

    public function __construct()
    {
        parent::__construct();
        try {
            $db = \App\Core\Database::getConnection();
            $db->exec("ALTER TABLE retornos_cv ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL");
        } catch (\Throwable $e) {}

        $repository = new RetornoCvRepository();
        $rules      = new RetornoCvRules();
        $logService = new LogSistemaService();
        $this->service = new RetornoCvService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_retorno');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        foreach ($rows as &$r) {
            if (!empty($r['fecha_retorno'])) $r['fecha_retorno'] = date('d-m-Y', strtotime($r['fecha_retorno']));
        }
        unset($r);

        // Config empresa (para tipo_ambiente, facturacion_inventario, decimales, etc.)
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        $responsableRepo = new \App\repositories\modulos\ResponsableTrasladoRepository();
        $responsables = $responsableRepo->listarPorEmpresa($idEmpresa);

        // Serie unificada (establecimiento-punto): solo puntos con secuencial de retornos configurado.
        $empresaRepo    = new \App\repositories\modulos\EmpresaRepository();
        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($empresaRepo->getPuntosEmision($idEmpresa) as $p) {
            $cfg = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($cfg['id'])) {
                $puntos[] = $p;
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos.retornos_cv.index', [
            'titulo'       => 'Retornos de Consignaciones en Ventas',
            'perm'         => $perm,
            'rutaModulo'   => self::RUTA_MODULO,
            'empresa'      => $empresaData,
            'responsables' => $responsables,
            'puntos'       => $puntos,
            'rows'         => $rows,
            'total'        => $total,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'perPage'      => $perPage,
            'buscar'       => $buscar,
            'ordenCol'     => $ordenCol,
            'ordenDir'     => $ordenDir,
            'vistaConfig'  => $prefsVista,
            'fullWidth'    => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? $_GET['q'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_retorno');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-arrow-return-left fs-3 d-block mb-2"></i>No se encontraron retornos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['fecha_retorno'])) $r['fecha_retorno'] = date('d-m-Y', strtotime($r['fecha_retorno']));
                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

                $statusBadge = self::badgeEstado($r['estado'] ?? '');

                echo '<tr class="retorno-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="abrirModalRetornoVer(this)">
                        <td class="ps-3" data-col="fecha_retorno">' . htmlspecialchars($r['fecha_retorno'] ?? '') . '</td>
                        <td data-col="secuencial" class="fw-bold text-primary">' . htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) . '</td>
                        <td data-col="cliente" class="text-truncate" style="max-width:250px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                        <td data-col="motivo" class="text-truncate" style="max-width:220px">' . htmlspecialchars($r['motivo'] ?? '—') . '</td>
                        <td data-col="total" class="text-end pe-3">' . number_format((float)($r['total'] ?? 0), 2) . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Datos no recibidos.");
            }

            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];
            $input['empresa_config'] = $this->getEmpresaConfig($input['id_empresa']);

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Retorno actualizado correctamente.']);
            } else {
                $id = $this->service->crear($input);
                echo json_encode(['ok' => true, 'msg' => 'Retorno registrado correctamente. El inventario ha sido actualizado.', 'id' => $id]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID no válido.");

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Retorno eliminado. El inventario ha sido reversado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstadoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = trim($_POST['estado'] ?? '');
            if ($id <= 0) throw new Exception("ID no válido.");

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->cambiarEstado($id, $idEmpresa, $idUsuario, $estado, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado a ' . $estado . '.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Devuelve el asiento contable (inverso a la consignación) del retorno: el guardado si existe,
     * o la sugerencia (Debe Inventario / Haber Mercadería en Consignación, a costo).
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idRet     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            if ($idRet <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $cab = $this->service->getPorId($idRet, $idEmpresa) ?? [];
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);

            // Si aún no tiene asiento (y está Emitida), intentar generarlo ahora.
            if ($idAsiento <= 0 && !empty($cab)) {
                try {
                    $this->service->procesarAsientoContable($idRet, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);
                    $cab = $this->service->getPorId($idRet, $idEmpresa) ?? [];
                    $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
                } catch (\Throwable $e) {}
            }

            if ($idAsiento > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    new LogSistemaService()
                );
                $cabAsiento = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                $detalles = [];
                foreach (($cabAsiento['detalles'] ?? []) as $det) {
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

            $detalles = $this->service->obtenerAsientoSugerido($idEmpresa, $idRet);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Genera el PDF del retorno (modelo general, con hook de plantilla por empresa). */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $retorno = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$retorno) { http_response_code(404); echo 'Retorno no encontrado'; exit; }

            // Nombre del usuario que realizó el retorno (para la firma "Realizado por").
            try {
                $db = \App\Core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM usuarios WHERE id = :u LIMIT 1");
                $st->execute([':u' => (int) ($retorno['created_by'] ?? 0)]);
                $retorno['usuario_nombre'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $retorno['usuario_nombre'] = '';
            }

            $detalles = $retorno['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            // Fase 2 (personalización): renderer si hay plantilla activa 'retorno_cv'.
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'retorno_cv');
            if ($plantilla) {
                $renderer->generar($plantilla, $retorno, $detalles, [], [], $empresa, 'D');
            } else {
                (new \App\Services\modulos\RetornoCvPdfService())
                    ->generar($retorno, $detalles, $empresa, 'D');
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Envía por correo SOLO el PDF del retorno (mismo mecanismo que Facturas/Consignaciones). */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $retorno = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$retorno) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Retorno no encontrado.']); exit; }

            // Nombre del usuario que realizó (para la firma "Realizado por" del PDF).
            try {
                $db = \App\Core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM usuarios WHERE id = :u LIMIT 1");
                $st->execute([':u' => (int) ($retorno['created_by'] ?? 0)]);
                $retorno['usuario_nombre'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $retorno['usuario_nombre'] = '';
            }

            $detalles = $retorno['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            // PDF como string (mismo hook que la descarga).
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'retorno_cv');
            if ($plantilla) {
                $pdfString = $renderer->generar($plantilla, $retorno, $detalles, [], [], $empresa, 'S');
            } else {
                $pdfString = (new \App\Services\modulos\RetornoCvPdfService())->generar($retorno, $detalles, $empresa, 'S');
            }

            $numero = trim((string)($retorno['serie'] ?? '') . '-' . (string)($retorno['secuencial'] ?? ''), '-');

            // Destinatarios: el que venga del formulario o, en su defecto, el del cliente.
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string)($retorno['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string)($retorno['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string)($empresa['nombre'] ?? '');
            $asunto = 'Retorno de Consignación ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará el comprobante del retorno de consignación <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'Retorno_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
            );

            if (ob_get_level() > 0) ob_end_clean();
            if ($enviado) {
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo o el destinatario.']);
            }
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Datos de la empresa (con logo del establecimiento) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $data = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$data) throw new Exception("Retorno no encontrado.");
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Busca consignaciones con saldo pendiente por cliente o número de consignación.
     */
    public function buscarConsignacionesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');
        if (mb_strlen($buscar) < 2) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }

        $data = $this->service->buscarConsignacionesPendientes($idEmpresa, $buscar);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    /**
     * Devuelve las líneas de consignación pendientes de retornar de un cliente.
     */
    public function getLineasClienteAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            if ($idCliente <= 0) throw new Exception("Cliente no válido.");

            $rows = $this->service->getLineasPendientesPorCliente($idEmpresa, $idCliente);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Secuencial (mismo patrón que Consignaciones) ─────────────────────────

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int) $_SESSION['id_empresa']);
        echo json_encode(['ok' => true, 'data' => $establecimientos]);
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEst = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new \App\models\Empresa();
        $puntos = $empresaModel->getPuntosEmision($idEst);

        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntosFiltrados = [];
        foreach ($puntos as $p) {
            $config = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($config['id'])) {
                $puntosFiltrados[] = $p;
            }
        }
        echo json_encode(['ok' => true, 'data' => array_values($puntosFiltrados)]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);

        $repo = new \App\repositories\SecuencialRepository();
        $config = $repo->getConfigSecuencial($idPunto, self::TIPO_SECUENCIAL);

        if (empty($config['id'])) {
            echo json_encode([
                'ok'  => false,
                'msg' => 'No hay configuración de secuencial para "' . self::TIPO_SECUENCIAL . '" en este punto de emisión. Configúrelo en Empresa / Secuenciales.'
            ]);
            exit;
        }

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Badge HTML según el estado del retorno (Emitida | Borrador | Anulada). */
    public static function badgeEstado(string $estado): string
    {
        switch ($estado) {
            case 'Emitida':
                return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Emitida</span>';
            case 'Borrador':
                return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';
            case 'Anulada':
                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>';
            default:
                return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">' . htmlspecialchars($estado) . '</span>';
        }
    }

    private function getEmpresaConfig(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (\Throwable $e) {}
        }
        return $empresaData;
    }
}
