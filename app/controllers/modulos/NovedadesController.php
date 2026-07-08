<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\NovedadRepository;
use App\repositories\modulos\EmpleadoRepository;
use App\Rules\modulos\NovedadRules;
use App\Services\LogSistemaService;
use App\Services\modulos\NovedadService;
use App\Services\modulos\NovedadImportService;
use App\models\CatalogoNovedades;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NovedadesController extends BaseModuloController
{
    private NovedadService $service;
    private const RUTA_MODULO = 'modulos/novedades';

    public function __construct()
    {
        parent::__construct();
        $this->service = new NovedadService(new NovedadRepository(), new NovedadRules(), new LogSistemaService());
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

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.novedades.index', [
            'titulo'     => 'Novedades',
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
            'tipos'      => CatalogoNovedades::tipos(),
            'motivos'    => CatalogoNovedades::motivosSalida(),
            'meses'      => CatalogoNovedades::MESES,
            'aplicaEn'   => CatalogoNovedades::aplicaEn(),
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
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="10" class="text-center py-5 text-muted">No se encontraron novedades.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $this->renderFila($r);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
        exit;
    }

    private function renderFila(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

        $mes    = CatalogoNovedades::MESES[(int) $r['periodo_mes']] ?? $r['periodo_mes'];
        $periodo = $h($mes) . ' ' . $h($r['periodo_anio']);
        $valor  = CatalogoNovedades::formatValor((string) $r['tipo_codigo'], $r['valor']);
        $fecha  = $r['fecha'] ? date('d-m-Y', strtotime((string) $r['fecha'])) : '—';
        $estadoOk = ($r['estado'] ?? 'activo') === 'activo';
        $estadoBadge = $estadoOk
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Anulado</span>';

        return '<tr class="novedad-row" role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalEditar(this)">'
            . '<td class="ps-3 fw-medium" data-col="empleado">' . $h($r['empleado_nombre']) . '</td>'
            . '<td data-col="identificacion"><code class="text-secondary">' . $h($r['empleado_identificacion']) . '</code></td>'
            . '<td data-col="tipo">' . $h($r['tipo_nombre']) . '</td>'
            . '<td data-col="fecha">' . $h($fecha) . '</td>'
            . '<td data-col="periodo">' . $periodo . '</td>'
            . '<td class="text-end fw-bold" data-col="valor">' . $h($valor) . '</td>'
            . '<td data-col="aplica_en"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">' . $h(CatalogoNovedades::nombreAplicaEn((string) ($r['aplica_en'] ?? 'rol'))) . '</span></td>'
            . '<td data-col="motivo" class="small text-muted">' . $h($r['motivo_nombre'] ?? '—') . '</td>'
            . '<td class="text-center" data-col="estado">' . $estadoBadge . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">'
            . '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            . '</td></tr>';
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatos();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        $data['estado']     = 'activo'; // Toda novedad nace activa.

        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Novedad registrada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = $this->recogerDatos();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Novedad actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Novedad eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarEmpleadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        $result = (new EmpleadoRepository())->getListado($idEmpresa, $q, 1, 15, 'nombres_apellidos', 'ASC');
        $data = array_map(fn($r) => [
            'id'                => (int) $r['id'],
            'identificacion'    => $r['identificacion'],
            'nombres_apellidos' => $r['nombres_apellidos'],
        ], $result['rows']);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = $this->service->getDetalle($id, $idEmpresa);
        echo json_encode($data ? ['ok' => true, 'data' => $data] : ['ok' => false, 'error' => 'No encontrado']);
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, 'fecha', 'DESC', $idUsuarioFiltro);

        try {
            $headers = ['Empleado', 'Identificación', 'Tipo', 'Fecha', 'Mes', 'Año', 'Valor', 'Afecta a', 'Motivo', 'Estado'];
            $exportData = [];
            foreach ($data['rows'] as $r) {
                $exportData[] = [
                    $r['empleado_nombre'],
                    $r['empleado_identificacion'],
                    $r['tipo_nombre'],
                    $r['fecha'],
                    CatalogoNovedades::MESES[(int) $r['periodo_mes']] ?? $r['periodo_mes'],
                    $r['periodo_anio'],
                    CatalogoNovedades::formatValor((string) $r['tipo_codigo'], $r['valor']),
                    CatalogoNovedades::nombreAplicaEn((string) ($r['aplica_en'] ?? 'rol')),
                    $r['motivo_nombre'] ?? '',
                    $r['estado'],
                ];
            }
            (new \App\Services\ReportService())->exportToExcel('Novedades', $headers, $exportData, 'Listado de Novedades', 'Empresa ID ' . $idEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);

        try {
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->AddPage();

            $html = '<h3>Listado de Novedades</h3><table border="0.5" cellpadding="3">'
                . '<tr style="background-color:#e9ecef;font-weight:bold;font-size:8px;">'
                . '<th width="19%">Empleado</th><th width="11%">Identificación</th><th width="15%">Tipo</th>'
                . '<th width="9%">Fecha</th><th width="12%">Período</th><th width="9%">Valor</th>'
                . '<th width="10%">Afecta a</th><th width="10%">Motivo</th><th width="7%">Estado</th></tr>';
            foreach ($data['rows'] as $r) {
                $mes = CatalogoNovedades::MESES[(int) $r['periodo_mes']] ?? $r['periodo_mes'];
                $html .= '<tr style="font-size:7.5px;">'
                    . '<td width="19%">' . htmlspecialchars((string) $r['empleado_nombre']) . '</td>'
                    . '<td width="11%">' . htmlspecialchars((string) $r['empleado_identificacion']) . '</td>'
                    . '<td width="15%">' . htmlspecialchars((string) $r['tipo_nombre']) . '</td>'
                    . '<td width="9%">' . ($r['fecha'] ? date('d-m-Y', strtotime((string) $r['fecha'])) : '') . '</td>'
                    . '<td width="12%">' . htmlspecialchars($mes . ' ' . $r['periodo_anio']) . '</td>'
                    . '<td width="9%" align="right">' . htmlspecialchars(CatalogoNovedades::formatValor((string) $r['tipo_codigo'], $r['valor'])) . '</td>'
                    . '<td width="10%">' . htmlspecialchars(CatalogoNovedades::nombreAplicaEn((string) ($r['aplica_en'] ?? 'rol'))) . '</td>'
                    . '<td width="10%">' . htmlspecialchars((string) ($r['motivo_nombre'] ?? '')) . '</td>'
                    . '<td width="7%">' . htmlspecialchars((string) $r['estado']) . '</td></tr>';
            }
            $html .= '</table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('Novedades.pdf', 'I');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Descarga la plantilla Excel para cargar novedades. */
    public function plantillaExcel(): void
    {
        $this->requireCrear();
        $ss = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle('Novedades');
        $headers = ['IDENTIFICACION', 'TIPO', 'VALOR', 'MES', 'ANIO', 'AFECTA_A', 'FECHA', 'OBSERVACION', 'MOTIVO'];
        $hoja->fromArray($headers, null, 'A1');
        $hoja->getStyle('A1:I1')->getFont()->setBold(true);
        // Fila de ejemplo
        $hoja->fromArray([
            ['1717136574', 'Otros Ingresos', 50, (int) date('n'), (int) date('Y'), 'rol', date('Y-m-d'), 'Bono de productividad', ''],
        ], null, 'A2');
        foreach (range('A', 'I') as $col) $hoja->getColumnDimension($col)->setAutoSize(true);

        // Hoja de referencia
        $ref = $ss->createSheet();
        $ref->setTitle('Referencia');
        $ref->fromArray(['TIPOS (usar código o nombre)'], null, 'A1');
        $ref->getStyle('A1')->getFont()->setBold(true);
        $fila = 2;
        foreach (CatalogoNovedades::TIPOS as $t) {
            $ref->fromArray([$t['codigo'], $t['nombre']], null, 'A' . $fila++);
        }
        $fila++;
        $ref->fromArray(['AFECTA_A'], null, 'A' . $fila);
        $ref->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach (CatalogoNovedades::APLICA_EN as $k => $v) $ref->fromArray([$k, $v], null, 'A' . $fila++);
        $fila++;
        $ref->fromArray(['MOTIVOS DE SALIDA (solo para Aviso de salida)'], null, 'A' . $fila);
        $ref->getStyle('A' . $fila)->getFont()->setBold(true);
        $fila++;
        foreach (CatalogoNovedades::MOTIVOS_SALIDA as $m) $ref->fromArray([$m['codigo'], $m['nombre']], null, 'A' . $fila++);
        $ref->getColumnDimension('A')->setWidth(12);
        $ref->getColumnDimension('B')->setAutoSize(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_novedades.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** Procesa la carga masiva de novedades desde un Excel. */
    public function importarExcel(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            if (empty($_FILES['archivo']['tmp_name']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
                throw new \Exception('No se recibió ningún archivo.');
            }
            $ext = strtolower(pathinfo($_FILES['archivo']['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                throw new \Exception('El archivo debe ser Excel (.xlsx o .xls).');
            }
            $import = new NovedadImportService($this->service, new NovedadRepository());
            $res = $import->procesar($_FILES['archivo']['tmp_name'], (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true] + $res);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatos(): array
    {
        return [
            'id_empleado'   => (int) ($_POST['id_empleado'] ?? 0),
            'tipo_codigo'   => trim($_POST['tipo_codigo'] ?? ''),
            'fecha'         => trim($_POST['fecha'] ?? ''),
            'periodo_mes'   => (int) ($_POST['periodo_mes'] ?? 0),
            'periodo_anio'  => (int) ($_POST['periodo_anio'] ?? 0),
            'valor'         => (float) ($_POST['valor'] ?? 0),
            'aplica_en'     => trim($_POST['aplica_en'] ?? 'rol'),
            'motivo_codigo' => trim($_POST['motivo_codigo'] ?? ''),
            'observacion'   => trim($_POST['observacion'] ?? ''),
            'estado'        => trim($_POST['estado'] ?? 'activo'),
        ];
    }
}
