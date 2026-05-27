<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CitaAgendaRepository;
use App\Rules\modulos\CitaAgendaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CitaAgendaService;

class CitasAgendaController extends BaseModuloController
{
    private CitaAgendaService $service;
    private const RUTA_MODULO = 'modulos/citas-agenda';

    public function __construct()
    {
        parent::__construct();
        $this->service = new CitaAgendaService(
            new CitaAgendaRepository(),
            new CitaAgendaRules(),
            new LogSistemaService()
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm        = $this->getPermisos();
        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $catalogos   = $this->service->getCatalogos($idEmpresa);
        $prefsVista  = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $this->viewWithLayout('layouts.main', 'modulos/citas_agenda/index', [
            'titulo'      => 'Agenda de Citas',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'tipos'       => $catalogos['tipos'],
            'recursos'    => $catalogos['recursos'],
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
        ]);
    }

    // ─── AJAX: eventos para FullCalendar ─────────────────────────────────────

    public function eventosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $inicio    = trim($_GET['start'] ?? '');
        $fin       = trim($_GET['end']   ?? '');

        if (!$inicio || !$fin) {
            echo json_encode([]);
            exit;
        }

        $filtros = [
            'estado'       => trim($_GET['estado']       ?? ''),
            'id_recurso'   => (int) ($_GET['id_recurso']   ?? 0) ?: null,
            'id_tipo_cita' => (int) ($_GET['id_tipo_cita'] ?? 0) ?: null,
        ];

        $rows   = $this->service->getEventos($idEmpresa, $inicio, $fin, $filtros);
        $events = [];
        $colorEstado = [
            'pendiente'  => '#ffc107',
            'confirmada' => '#0d6efd',
            'en_curso'   => '#0dcaf0',
            'completada' => '#198754',
            'cancelada'  => '#6c757d',
            'no_asistio' => '#dc3545',
        ];

        foreach ($rows as $r) {
            $color  = $r['color'] ?: ($colorEstado[$r['estado']] ?? '#6c757d');
            $titulo = $r['nombre_cliente'] ?? $r['titulo'] ?? 'Sin título';
            if ($r['nombre_tipo']) $titulo = "[{$r['nombre_tipo']}] $titulo";

            $events[] = [
                'id'    => $r['id'],
                'title' => $titulo,
                'start' => $r['fecha_inicio'],
                'end'   => $r['fecha_fin'],
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'textColor'       => '#fff',
                'extendedProps'   => [
                    'estado'         => $r['estado'],
                    'nombre_tipo'    => $r['nombre_tipo'],
                    'nombre_recurso' => $r['nombre_recurso'],
                    'nombre_cliente' => $r['nombre_cliente'],
                    'notas'          => $r['notas'],
                    'origen'         => $r['origen'],
                ],
            ];
        }

        echo json_encode($events);
        exit;
    }

    // ─── AJAX: listado paginado ───────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $page      = max(1, (int) ($_GET['page']     ?? 1));
        $perPage   = max(5, min(100, (int) ($_GET['per_page'] ?? 20)));
        $ordenCol  = trim($_GET['sort']  ?? 'fecha_inicio');
        $ordenDir  = trim($_GET['dir']   ?? 'DESC');

        // Parsear string de búsqueda del componente FiltrosBusqueda
        $rawQ   = trim($_GET['q'] ?? '');
        $parsed = \App\Helpers\FiltrosBusqueda::parsear($rawQ);

        // Texto libre: combinar texto general + valores de campos de texto específicos
        $textos = array_filter([
            $parsed['texto_libre'],
            (string) ($parsed['filtros']['q']['valor']       ?? ''),
            (string) ($parsed['filtros']['cliente']['valor'] ?? ''),
            (string) ($parsed['filtros']['titulo']['valor']  ?? ''),
            (string) ($parsed['filtros']['tipo']['valor']    ?? ''),
            (string) ($parsed['filtros']['recurso']['valor'] ?? ''),
        ]);
        $buscar = implode(' ', $textos);

        // Filtros estándar (GET tiene prioridad; si no, usar los del componente)
        $estadoGet  = trim($_GET['estado'] ?? '');
        $origenGet  = trim($_GET['origen'] ?? '');
        $filtros   = [
            'estado'       => $estadoGet !== ''
                ? $estadoGet
                : (string) ($parsed['filtros']['estado']['valor'] ?? ''),
            'id_recurso'   => (int) ($_GET['id_recurso']   ?? 0) ?: null,
            'id_tipo_cita' => (int) ($_GET['id_tipo_cita'] ?? 0) ?: null,
            'fecha_desde'  => trim($_GET['fecha_desde']  ?? ''),
            'fecha_hasta'  => trim($_GET['fecha_hasta']  ?? ''),
        ];

        $result  = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $filtros);
        $rows    = $result['rows'];
        $total   = $result['total'];
        $from    = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to      = $total > 0 ? min($page * $perPage, $total) : 0;

        $params = http_build_query([
            'q'           => $buscar,
            'estado'      => $filtros['estado']       ?? '',
            'id_tipo_cita'=> $filtros['id_tipo_cita'] ?? '',
            'id_recurso'  => $filtros['id_recurso']   ?? '',
            'fecha_desde' => $filtros['fecha_desde']  ?? '',
            'fecha_hasta' => $filtros['fecha_hasta']  ?? '',
            'sort'        => $ordenCol,
            'dir'         => $ordenDir,
        ]);
        $baseExport = BASE_URL . '/' . self::RUTA_MODULO;

        echo json_encode([
            'ok'       => true,
            'data'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'info'     => "$from-$to/$total",
            'pdf_url'  => "$baseExport/export-pdf?$params",
            'excel_url'=> "$baseExport/export-excel?$params",
        ]);
        exit;
    }

    // ─── EXPORT PDF ──────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_inicio');
        $ordenDir  = trim($_GET['dir']  ?? 'DESC');
        $filtros   = [
            'estado'       => trim($_GET['estado']       ?? ''),
            'id_recurso'   => (int) ($_GET['id_recurso']   ?? 0) ?: null,
            'id_tipo_cita' => (int) ($_GET['id_tipo_cita'] ?? 0) ?: null,
            'fecha_desde'  => trim($_GET['fecha_desde']  ?? ''),
            'fecha_hasta'  => trim($_GET['fecha_hasta']  ?? ''),
        ];

        $result = $this->service->getListado($idEmpresa, $buscar, 1, 5000, $ordenCol, $ordenDir, $filtros);
        $rows   = $result['rows'];

        $estadoLabel = [
            'pendiente'  => 'Pendiente',
            'confirmada' => 'Confirmada',
            'en_curso'   => 'En curso',
            'completada' => 'Completada',
            'cancelada'  => 'Cancelada',
            'no_asistio' => 'No asistió',
        ];

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            body{font-family:Arial,sans-serif;font-size:11px;}
            h2{text-align:center;margin-bottom:4px;font-size:14px;}
            p.sub{text-align:center;color:#666;margin:0 0 12px;}
            table{width:100%;border-collapse:collapse;}
            th{background:#0d6efd;color:#fff;padding:5px 6px;text-align:left;font-size:10px;}
            td{padding:4px 6px;border-bottom:1px solid #e5e7eb;font-size:10px;}
            tr:nth-child(even) td{background:#f8f9fa;}
        </style></head><body>';
        $html .= '<h2>Agenda de Citas</h2>';
        $html .= '<p class="sub">Generado el ' . date('d-m-Y H:i:s') . '</p>';
        $html .= '<table><thead><tr>
            <th>Fecha inicio</th><th>Tipo</th><th>Cliente</th>
            <th>Recurso</th><th>Estado</th><th>Origen</th><th>Título</th>
        </tr></thead><tbody>';

        foreach ($rows as $r) {
            $fi    = $r['fecha_inicio'] ? date('d-m-Y H:i', strtotime($r['fecha_inicio'])) : '—';
            $est   = htmlspecialchars($estadoLabel[$r['estado']] ?? $r['estado']);
            $html .= '<tr>
                <td>' . htmlspecialchars($fi) . '</td>
                <td>' . htmlspecialchars($r['nombre_tipo'] ?? '—') . '</td>
                <td>' . htmlspecialchars($r['nombre_cliente'] ?? '—') . '</td>
                <td>' . htmlspecialchars($r['nombre_recurso'] ?? '—') . '</td>
                <td>' . $est . '</td>
                <td>' . htmlspecialchars(ucfirst($r['origen'] ?? '')) . '</td>
                <td>' . htmlspecialchars($r['titulo'] ?? '—') . '</td>
            </tr>';
        }

        $html .= '</tbody></table></body></html>';

        try {
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('Citas_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/plain');
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    // ─── EXPORT EXCEL ────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_inicio');
        $ordenDir  = trim($_GET['dir']  ?? 'DESC');
        $filtros   = [
            'estado'       => trim($_GET['estado']       ?? ''),
            'id_recurso'   => (int) ($_GET['id_recurso']   ?? 0) ?: null,
            'id_tipo_cita' => (int) ($_GET['id_tipo_cita'] ?? 0) ?: null,
            'fecha_desde'  => trim($_GET['fecha_desde']  ?? ''),
            'fecha_hasta'  => trim($_GET['fecha_hasta']  ?? ''),
        ];

        $result = $this->service->getListado($idEmpresa, $buscar, 1, 5000, $ordenCol, $ordenDir, $filtros);
        $rows   = $result['rows'];

        $estadoLabel = [
            'pendiente'  => 'Pendiente',
            'confirmada' => 'Confirmada',
            'en_curso'   => 'En curso',
            'completada' => 'Completada',
            'cancelada'  => 'Cancelada',
            'no_asistio' => 'No asistió',
        ];

        $headers    = ['Fecha inicio', 'Tipo', 'Cliente', 'Recurso', 'Estado', 'Origen', 'Título', 'Notas'];
        $exportData = [];

        foreach ($rows as $r) {
            $fi = $r['fecha_inicio'] ? date('d-m-Y H:i', strtotime($r['fecha_inicio'])) : '';
            $exportData[] = [
                $fi,
                $r['nombre_tipo']    ?? '',
                $r['nombre_cliente'] ?? '',
                $r['nombre_recurso'] ?? '',
                $estadoLabel[$r['estado']] ?? $r['estado'],
                ucfirst($r['origen'] ?? ''),
                $r['titulo']         ?? '',
                $r['notas']          ?? '',
            ];
        }

        try {
            $reportService  = new \App\Services\ReportService();
            $nombreEmpresa  = $_SESSION['nombre_empresa'] ?? 'Empresa';
            $reportService->exportToExcel('Citas', $headers, $exportData, 'Agenda de Citas', $nombreEmpresa);
        } catch (\Throwable $e) {
            header('Content-Type: text/plain');
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    // ─── AJAX: catálogos ─────────────────────────────────────────────────────

    public function catalogosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $catalogos = $this->service->getCatalogos((int) $_SESSION['id_empresa']);
        echo json_encode(['ok' => true, 'data' => $catalogos]);
        exit;
    }

    // ─── AJAX: buscar clientes ────────────────────────────────────────────────

    public function buscarClientes(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rows      = $this->service->buscarClientes($q, $idEmpresa);
        echo json_encode($rows);
        exit;
    }

    // ─── AJAX: obtener cita ───────────────────────────────────────────────────

    public function getAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $id   = (int) ($_GET['id'] ?? 0);
            $data = $this->service->getById($id, (int) $_SESSION['id_empresa']);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── GUARDAR ─────────────────────────────────────────────────────────────

    public function guardar(): void
    {
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $data = [
            'id'           => $id,
            'id_empresa'   => (int) $_SESSION['id_empresa'],
            'id_usuario'   => (int) $_SESSION['id_usuario'],
            'id_tipo_cita' => (int) ($_POST['id_tipo_cita'] ?? 0) ?: null,
            'id_recurso'   => (int) ($_POST['id_recurso']   ?? 0) ?: null,
            'id_cliente'   => (int) ($_POST['id_cliente']   ?? 0) ?: null,
            'titulo'       => trim($_POST['titulo']        ?? '') ?: null,
            'fecha_inicio' => trim($_POST['fecha_inicio']  ?? ''),
            'fecha_fin'    => trim($_POST['fecha_fin']     ?? ''),
            'estado'       => trim($_POST['estado']        ?? 'pendiente'),
            'notas'        => trim($_POST['notas']         ?? '') ?: null,
            'origen'       => 'interno',
        ];

        try {
            $newId = $this->service->guardar($data);
            $msg   = $id > 0 ? 'Cita actualizada correctamente.' : 'Cita creada correctamente.';
            echo json_encode(['ok' => true, 'mensaje' => $msg, 'id' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── ELIMINAR ─────────────────────────────────────────────────────────────

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Cita eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── CAMBIAR ESTADO ───────────────────────────────────────────────────────

    public function cambiarEstado(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $id     = (int) ($_POST['id']     ?? 0);
            $estado = trim($_POST['estado']   ?? '');
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->cambiarEstado($id, $estado, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Estado actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
