<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\DecimoTerceroRepository;
use App\Rules\modulos\DecimoTerceroRules;
use App\Services\LogSistemaService;
use App\Services\modulos\DecimoTerceroService;
use App\Services\modulos\DecimoTerceroExportService;

class DecimoTerceroController extends BaseModuloController
{
    private DecimoTerceroService $service;
    private const RUTA_MODULO = 'modulos/decimo-tercero';

    public function __construct()
    {
        parent::__construct();
        $this->service = new DecimoTerceroService(new DecimoTerceroRepository(), new DecimoTerceroRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.decimo_tercero.index', [
            'titulo'     => 'Décimo Tercero',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig' => $prefsVista,
            'idEmpresa'  => $idEmpresa,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $from = $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No hay declaraciones calculadas.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) echo $this->renderFila($r);
        }
        $rowsHtml = ob_get_clean();

        $prev = $page <= 1 ? 'disabled' : '';
        $next = $page >= $totalPages ? 'disabled' : '';
        $pag = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prev . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $next . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button></div>';

        echo json_encode(['ok' => true, 'rows' => $rowsHtml, 'pagination' => $pag, 'info' => "$from-$to/" . $result['total'], 'total' => $result['total']]);
        exit;
    }

    private function renderFila(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $limite = $r['fecha_limite_pago'] ? date('d-m-Y', strtotime((string) $r['fecha_limite_pago'])) : '—';
        $colores = ['borrador' => 'secondary', 'calculado' => 'info'];
        $c = $colores[$r['estado']] ?? 'secondary';
        $estado = '<span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . ' border-opacity-25">' . $h(ucfirst((string) $r['estado'])) . '</span>';
        $base = $r['base_calculo'] === 'todos' ? 'Todos los ingresos' : 'Solo IESS';

        return '<tr class="dt-row" role="button" data-row=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') . '\' onclick="abrirModalVer(this)">'
            . '<td class="ps-3 fw-medium" data-col="anio">' . (int) $r['anio'] . '</td>'
            . '<td data-col="base">' . $h($base) . '</td>'
            . '<td data-col="limite">' . $h($limite) . '</td>'
            . '<td class="text-center" data-col="empleados">' . (int) $r['total_empleados'] . '</td>'
            . '<td class="text-end fw-bold" data-col="total">$' . number_format((float) $r['total_valor'], 2) . '</td>'
            . '<td class="text-center" data-col="estado">' . $estado . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">'
            . '<button class="btn btn-outline-secondary btn-xs border-0 px-2" onclick="exportarCsv(' . (int) $r['id'] . ')" title="Exportar CSV"><i class="bi bi-file-earmark-spreadsheet"></i></button>'
            . '</td></tr>';
    }

    public function calcularAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $anio = (int) ($_POST['anio'] ?? 0);
            $baseCalculo = trim($_POST['base_calculo'] ?? '');
            $id = $this->service->calcular((int) $_SESSION['id_empresa'], $anio, $baseCalculo, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Décimo tercero calculado.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        $cab = $this->service->getCabecera($id, $idEmpresa);
        if (!$cab) { echo json_encode(['ok' => false, 'error' => 'No encontrado']); exit; }
        $detalle = $this->service->getDetalle($id, $idEmpresa);
        echo json_encode(['ok' => true, 'cabecera' => $cab, 'detalle' => $detalle]);
        exit;
    }

    public function actualizarDetalleAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idDetalle = (int) ($_POST['id'] ?? 0);
            $campos = [];
            foreach (['tipo_pago', 'discapacidad', 'valor_retencion', 'nombres', 'apellidos', 'total_ganado'] as $c) {
                if (array_key_exists($c, $_POST)) {
                    $campos[$c] = $c === 'discapacidad' ? !empty($_POST[$c]) : trim((string) $_POST[$c]);
                }
            }
            $this->service->actualizarDetalleEmpleado($idDetalle, (int) $_SESSION['id_empresa'], $campos, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Registro actualizado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportarCsv(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        try {
            $export = new DecimoTerceroExportService(new DecimoTerceroRepository(), new DecimoTerceroRules());
            $cab = $this->service->getCabecera($id, $idEmpresa);
            if (!$cab) throw new \Exception('Declaración no encontrada.');
            $contenido = $export->generar($id, $idEmpresa);
            $nombre = $export->nombreArchivo($cab);

            header('Content-Type: text/csv; charset=windows-1252');
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . strlen($contenido));
            echo $contenido;
        } catch (\Throwable $e) {
            http_response_code(400);
            echo 'No se pudo generar el archivo: ' . $e->getMessage();
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->anular($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Declaración anulada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
