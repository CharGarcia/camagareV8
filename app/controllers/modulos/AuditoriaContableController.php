<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AuditoriaContableRepository;
use App\Services\modulos\AuditoriaContableService;
use App\Rules\modulos\AuditoriaContableRules;
use App\Services\LogSistemaService;

/**
 * Controller del módulo Auditoría Contable.
 * Sin lógica de negocio: valida permisos, recibe la solicitud, delega al Service
 * y responde (vista o JSON). Filtra por id_empresa (sesión) y registros propios.
 */
class AuditoriaContableController extends BaseModuloController
{
    private AuditoriaContableService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/auditoria_contable';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new AuditoriaContableService(
            new AuditoriaContableRepository(),
            new AuditoriaContableRules(),
            new LogSistemaService()
        );
    }

    // ==================================================================
    //  ETIQUETAS / BADGES
    // ==================================================================

    private const TIPO_LABEL = [
        'faltante'             => 'Falta asiento',
        'duplicado'            => 'Duplicado',
        'monto_no_coincide'    => 'Monto no coincide',
        'descuadrado'          => 'Descuadrado',
        'cab_vs_detalle'       => 'Cabecera ≠ detalle',
        'huerfano'             => 'Huérfano',
        'estado_incoherente'   => 'Estado incoherente',
        'ambiente_incoherente' => 'Ambiente incoherente',
    ];

    private const TIPO_CLASE = [
        'faltante'             => 'bg-danger bg-opacity-10 text-danger border-danger',
        'duplicado'            => 'bg-warning bg-opacity-10 text-warning border-warning',
        'monto_no_coincide'    => 'bg-warning bg-opacity-10 text-warning border-warning',
        'descuadrado'          => 'bg-danger bg-opacity-10 text-danger border-danger',
        'cab_vs_detalle'       => 'bg-danger bg-opacity-10 text-danger border-danger',
        'huerfano'             => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
        'estado_incoherente'   => 'bg-primary bg-opacity-10 text-primary border-primary',
        'ambiente_incoherente' => 'bg-info bg-opacity-10 text-info border-info',
    ];

    private const ORIGEN_LABEL = [
        'factura_venta'      => 'Factura de venta',
        'compra'             => 'Factura de compra',
        'liquidacion_compra' => 'Liquidación de compra',
        'nota_credito'       => 'Nota de crédito',
        'retencion_venta'    => 'Retención en venta',
        'ingreso'            => 'Ingreso',
        'egreso'             => 'Egreso',
    ];

    private const REVISION_LABEL = [
        'pendiente'   => 'Pendiente',
        'revisada'    => 'Revisada',
        'justificada' => 'Justificada',
        'resuelta'    => 'Resuelta',
    ];

    private const REVISION_CLASE = [
        'pendiente'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
        'revisada'    => 'bg-info bg-opacity-10 text-info border-info',
        'justificada' => 'bg-success bg-opacity-10 text-success border-success',
        'resuelta'    => 'bg-success bg-opacity-10 text-success border-success',
    ];

    // ==================================================================
    //  VISTA PRINCIPAL
    // ==================================================================

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'detectado_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil(($result['total'] ?: 0) / $perPage);

        $this->viewWithLayout('layouts.main', 'modulos/auditoria_contable/index', [
            'titulo'      => 'Auditoría Contable',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'base'        => BASE_URL,
            'rutaModulo'  => $this->getRutaModulo(),
            'resumen'     => $this->service->getResumen($idEmpresa),
            'origenes'    => $this->service->getOrigenes(),
            'origenLabels'=> self::ORIGEN_LABEL,
            'tipoLabels'  => self::TIPO_LABEL,
            'corridas'    => $this->service->getCorridas($idEmpresa, 20),
            'fullWidth'   => true,
        ]);
    }

    // ==================================================================
    //  LISTADO AJAX
    // ==================================================================

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'detectado_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $html = '';
        if (empty($result['rows'])) {
            $html = '<tr><td colspan="10" class="text-center py-5 text-muted">'
                  . '<i class="bi bi-clipboard-check fs-3 d-block mb-2"></i>Sin incidencias. Ejecute la auditoría para verificar.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                $html .= $this->construirFila($r, $perm);
            }
        }

        echo json_encode([
            'ok'         => true,
            'html'       => $html,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'from'       => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'         => $total > 0 ? min($page * $perPage, $total) : 0,
            'resumen'    => $this->service->getResumen($idEmpresa),
        ]);
        exit;
    }

    private function construirFila(array $r, array $perm): string
    {
        $tipo   = (string) $r['tipo_hallazgo'];
        $origen = (string) $r['modulo_origen'];
        $rev    = (string) ($r['estado_revision'] ?? 'pendiente');

        $tipoBadge = '<span class="badge ' . (self::TIPO_CLASE[$tipo] ?? 'bg-secondary')
            . ' border">' . htmlspecialchars(self::TIPO_LABEL[$tipo] ?? $tipo) . '</span>';
        $revBadge = '<span class="badge ' . (self::REVISION_CLASE[$rev] ?? 'bg-secondary')
            . ' border">' . htmlspecialchars(self::REVISION_LABEL[$rev] ?? $rev) . '</span>';
        $origenLabel = htmlspecialchars(self::ORIGEN_LABEL[$origen] ?? $origen);

        $numeroDoc = trim((string) ($r['documento_numero'] ?? ''));
        $doc = $numeroDoc !== ''
            ? htmlspecialchars($numeroDoc)
            : ($r['id_documento'] !== null ? '#' . (int) $r['id_documento'] : '—');
        $asiento = $r['id_asiento'] !== null ? '#' . (int) $r['id_asiento'] : '—';
        $mDoc    = $r['monto_documento'] !== null ? number_format((float) $r['monto_documento'], 2) : '—';
        $mAsi    = $r['monto_asiento'] !== null ? number_format((float) $r['monto_asiento'], 2) : '—';
        $dif     = $r['diferencia'] !== null ? number_format((float) $r['diferencia'], 2) : '—';
        $fecha   = !empty($r['fecha_documento']) ? date('d-m-Y', strtotime((string) $r['fecha_documento'])) : '—';
        $detalle = htmlspecialchars((string) ($r['detalle'] ?? ''));

        $acciones = $this->accionesFila($r, $perm);

        return '<tr data-id="' . (int) $r['id'] . '" data-tipo="' . htmlspecialchars($tipo) . '" data-origen="' . htmlspecialchars($origen) . '"'
            . ' data-doc="' . (int) ($r['id_documento'] ?? 0) . '" data-asiento="' . (int) ($r['id_asiento'] ?? 0) . '">'
            . '<td data-col="tipo">' . $tipoBadge . '</td>'
            . '<td data-col="origen">' . $origenLabel . '</td>'
            . '<td data-col="documento" class="text-center">' . $doc . '</td>'
            . '<td data-col="asiento" class="text-center">' . $asiento . '</td>'
            . '<td data-col="monto_documento" class="text-end">' . $mDoc . '</td>'
            . '<td data-col="monto_asiento" class="text-end">' . $mAsi . '</td>'
            . '<td data-col="diferencia" class="text-end">' . $dif . '</td>'
            . '<td data-col="fecha" class="text-center">' . $fecha . '</td>'
            . '<td data-col="revision">' . $revBadge . '<div class="small text-muted">' . $detalle . '</div></td>'
            . '<td data-col="acciones" class="text-nowrap">' . $acciones . '</td>'
            . '</tr>';
    }

    private function accionesFila(array $r, array $perm): string
    {
        $tipo = (string) $r['tipo_hallazgo'];
        $btns = [];

        if ($tipo === 'faltante' && !empty($perm['crear'])) {
            $btns[] = '<button class="btn btn-sm btn-outline-success js-aud-generar" title="Generar asiento"><i class="bi bi-magic"></i></button>';
        }
        if ($tipo === 'duplicado' && !empty($perm['eliminar'])) {
            $btns[] = '<button class="btn btn-sm btn-outline-warning js-aud-duplicado" title="Resolver duplicado"><i class="bi bi-files"></i></button>';
        }
        if ($tipo === 'ambiente_incoherente' && !empty($perm['actualizar'])) {
            $btns[] = '<button class="btn btn-sm btn-outline-info js-aud-ambiente" title="Corregir ambiente"><i class="bi bi-arrow-repeat"></i></button>';
        }
        if (!empty($perm['actualizar'])) {
            $btns[] = '<button class="btn btn-sm btn-outline-primary js-aud-revisar" title="Marcar revisión"><i class="bi bi-check2-square"></i></button>';
        }

        return implode(' ', $btns);
    }

    // ==================================================================
    //  EJECUTAR AUDITORÍA
    // ==================================================================

    public function ejecutarAuditoriaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $origen    = trim($_POST['origen'] ?? '') ?: null;

        try {
            $res = $this->service->ejecutarAuditoria($idEmpresa, $idUsuario, $origen);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Auditoría ejecutada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    //  ACCIONES DE CORRECCIÓN
    // ==================================================================

    public function marcarRevisionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id'] ?? 0);
        $estado    = trim($_POST['estado_revision'] ?? '');
        $nota      = trim($_POST['nota'] ?? '') ?: null;

        try {
            $this->service->marcarRevision($id, $idEmpresa, $idUsuario, $estado, $nota);
            echo json_encode(['ok' => true, 'mensaje' => 'Revisión actualizada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function generarFaltanteAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id'] ?? 0);

        try {
            $this->service->generarFaltante($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Asiento generado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function corregirAmbienteAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id'] ?? 0);

        try {
            $this->service->corregirAmbiente($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Ambiente corregido.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Lista los asientos de un documento para que el usuario elija cuál anular. */
    public function asientosDocumentoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $origen    = trim($_GET['origen'] ?? '');
        $idDoc     = (int) ($_GET['documento'] ?? 0);

        $rows = $this->service->getAsientosDeDocumento($idEmpresa, $origen, $idDoc);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function anularDuplicadoAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idAsiento = (int) ($_POST['id_asiento'] ?? 0);

        try {
            $this->service->anularDuplicado($idAsiento, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Asiento anulado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================================================================
    //  REGENERACIÓN MASIVA
    // ==================================================================

    public function regenerarMasivoAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idUsuario  = (int) $_SESSION['id_usuario'];
        $origen     = trim($_POST['origen'] ?? '');
        $fechaDesde = trim($_POST['fecha_desde'] ?? '') ?: null;
        $fechaHasta = trim($_POST['fecha_hasta'] ?? '') ?: null;

        try {
            $res = $this->service->regenerarMasivo($idEmpresa, $idUsuario, $origen, $fechaDesde, $fechaHasta);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => $res['mensaje']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
