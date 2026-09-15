<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\CargaInventarioService;
use App\Helpers\PreferenciasHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Módulo Cargas de Inventario (Documentos).
 * Importa cargas masivas (entrada/salida/ajuste) desde Excel/CSV; afectan el
 * kardex solo al ser aprobadas (si la config del establecimiento lo exige).
 */
class CargasInventarioController extends BaseModuloController
{
    private CargaInventarioService $service;
    private const RUTA_MODULO = 'modulos/cargas-inventario';
    private const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CargaInventarioService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) ($_SESSION['id_usuario'] ?? 0) : null;
    }

    /**
     * Query string de los enlaces de exportar, con SOLO los parámetros que
     * llevan valor: sin búsqueda ni orden explícito el enlace queda limpio
     * (`…/export-pdf`), no `…/export-pdf?b=&orden=`. Lo que falte lo resuelve
     * `filasParaExportar()` con la preferencia guardada del usuario.
     */
    private function exportQs(string $buscar, array $orden): string
    {
        $params = array_filter([
            'b'     => $buscar,
            'orden' => \App\Helpers\OrdenListado::aCadena($orden),
        ], static fn(string $v): bool => $v !== '');

        return $params === [] ? '' : '?' . http_build_query($params);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $orden    = \App\Helpers\OrdenListado::leer($prefsVista, 'numero', 'DESC');
        $ordenCol = \App\Helpers\OrdenListado::primeraCol($orden, 'numero');
        $ordenDir = \App\Helpers\OrdenListado::primeraDir($orden);
        $perPage  = self::PER_PAGE;

        $res = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $this->idUsuarioFiltro(), $orden);
        $total = $res['total'];

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $esAprobador = $this->service->esAprobador($idUsuario, $idEmpresa, $nivel);

        $this->viewWithLayout('layouts.main', 'modulos.cargas_inventario.index', [
            'titulo'      => 'Cargas de Inventario',
            'perm'        => $this->getPermisos(),
            'rows'        => $res['rows'],
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'ordenJson'   => \App\Helpers\OrdenListado::aJson($orden),
            'exportQs'    => $this->exportQs($buscar, $orden),
            'esAprobador' => $esAprobador,
            'esSuperAdmin' => $nivel >= 3,
            'idUsuarioActual' => $idUsuario,
            'aprobadoresNombres' => $this->service->getAprobadoresNombres($idEmpresa),
            'rutaModulo'  => self::RUTA_MODULO,
            'fullWidth'   => true,
        ]);
    }

    /**
     * Refresco del listado por AJAX (búsqueda, orden y paginación) al estilo del
     * resto de listados: devuelve las filas ya renderizadas, la paginación, el
     * contador y los enlaces de exportación con los filtros vigentes.
     */
    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) ($_SESSION['id_empresa'] ?? 0);
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $orden      = \App\Helpers\OrdenListado::leer($prefsVista, 'numero', 'DESC');
        $ordenCol   = \App\Helpers\OrdenListado::primeraCol($orden, 'numero');
        $ordenDir   = \App\Helpers\OrdenListado::primeraDir($orden);
        $perPage    = self::PER_PAGE;

        $res        = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $this->idUsuarioFiltro(), $orden);
        $rows       = $res['rows'];
        $total      = (int) $res['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted">'
               . '<i class="bi bi-box-seam fs-3 d-block mb-2"></i>No hay cargas de inventario registradas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $this->renderFila($r);
            }
        }
        $rowsHtml = (string) ob_get_clean();

        $prevDis = ($page <= 1) ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml =
            "<button type='button' class='btn btn-outline-secondary' {$prevDis} onclick='CI_buscar(" . ($page - 1) . ")'><i class='bi bi-chevron-left'></i></button>"
          . "<button type='button' class='btn btn-outline-secondary' {$nextDis} onclick='CI_buscar(" . ($page + 1) . ")'><i class='bi bi-chevron-right'></i></button>";

        $qs = $this->exportQs($buscar, $orden);
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf' . $qs,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel' . $qs,
        ]);
        exit;
    }

    /**
     * Una fila del listado. El HTML vive en un único partial que incluyen tanto
     * la carga inicial (index.php) como este refresco AJAX: escribirlo en dos
     * sitios deja una de las dos versiones desfasada al tocar una columna.
     */
    private function renderFila(array $r): string
    {
        ob_start();
        include MVC_APP . '/views/modulos/cargas_inventario/_fila.php';
        return (string) ob_get_clean();
    }

    public function importarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        $tipo = $_POST['tipo_movimiento'] ?? 'entrada';
        $obs  = trim($_POST['observacion'] ?? '');

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'mensaje' => 'Seleccione un archivo Excel válido.']);
            return;
        }
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Formato no soportado. Use Excel (.xlsx, .xls) o CSV.']);
            return;
        }

        try {
            $hoja = IOFactory::load($_FILES['archivo']['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);

            if (count($hoja) <= 1) {
                echo json_encode(['ok' => false, 'mensaje' => 'El archivo está vacío o solo contiene los encabezados.']);
                return;
            }

            // Primera fila = encabezados (se normalizan a minúsculas sin espacios).
            $header = array_map(static fn($h) => strtolower(trim((string) $h)), $hoja[0]);

            $filas = [];
            for ($i = 1; $i < count($hoja); $i++) {
                $row = $hoja[$i];
                if (empty(array_filter($row, static fn($v) => trim((string) $v) !== ''))) continue;
                $filas[] = @array_combine($header, $row) ?: [];
            }

            if (empty($filas)) {
                echo json_encode(['ok' => false, 'mensaje' => 'El archivo no contiene filas de datos.']);
                return;
            }

            $res = $this->service->crearDesdeImportacion($idEmpresa, $idUsuario, $tipo, $obs !== '' ? $obs : null, $filas);
            echo json_encode(['ok' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }
    }

    /** Descarga una plantilla Excel (.xlsx) de ejemplo para la carga de inventario. */
    public function descargarPlantilla(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $refs = $this->service->getReferenciasPlantilla($idEmpresa);

        $TEXT = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $FILL = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID;

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Datos');

        // Columnas: código de producto y nombre de bodega (no ids internos).
        $cols = [
            'codigo_producto'  => 22,
            'bodega'           => 22,
            'cantidad'         => 12,
            'costo_unitario'   => 14,
            'numero_lote'      => 16,
            'fecha_caducidad'  => 16,
            'nup'              => 20,
            'observacion'      => 28,
        ];
        $numericas = ['cantidad', 'costo_unitario'];

        $ci = 1;
        foreach ($cols as $col => $width) {
            $sheet->setCellValueExplicit([$ci, 1], $col, $TEXT);
            $sheet->getColumnDimensionByColumn($ci)->setWidth($width);
            $sheet->getStyle([$ci, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle([$ci, 1])->getFill()->setFillType($FILL)->getStartColor()->setARGB('FF4472C4');

            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci);
            $rango  = "{$letter}2:{$letter}1001";
            if (in_array($col, $numericas, true)) {
                $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            } else {
                // Texto: evita que Excel altere códigos como "004" o fechas.
                $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            }
            $ci++;
        }

        // Fila de ejemplo (referencial) usando el primer producto/bodega si existen.
        $codEj = $refs['productos'][0]['codigo'] ?? 'COD001';
        $bodEj = $refs['bodegas'][0]['nombre']  ?? 'Central';
        $ejemplo = [$codEj, $bodEj, '10', '5.50', 'L001', '2026-12-31', '', 'Fila de ejemplo — reemplácela'];
        $ce = 1;
        foreach ($ejemplo as $val) { $sheet->setCellValueExplicit([$ce++, 2], (string) $val, $TEXT); }
        $sheet->getStyle('A2:H2')->getFont()->setItalic(true)->getColor()->setARGB('FF888888');

        // Helper para hojas de referencia.
        $crearHojaRef = function (string $titulo, array $headers, array $filas, string $color) use ($ss, $TEXT, $FILL) {
            $sh = $ss->createSheet();
            $sh->setTitle($titulo);
            foreach ($headers as $i => $h) {
                $c = $i + 1;
                $sh->setCellValueExplicit([$c, 1], $h, $TEXT);
                $sh->getColumnDimensionByColumn($c)->setWidth(28);
                $sh->getStyle([$c, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sh->getStyle([$c, 1])->getFill()->setFillType($FILL)->getStartColor()->setARGB($color);
            }
            $r = 2;
            foreach ($filas as $fila) {
                $c = 1;
                foreach (array_values($fila) as $val) { $sh->setCellValueExplicit([$c++, $r], (string) $val, $TEXT); }
                $r++;
            }
        };

        $crearHojaRef('Productos', ['CODIGO (usar este valor)', 'NOMBRE'], $refs['productos'], 'FF70AD47');
        $crearHojaRef('Bodegas', ['NOMBRE_BODEGA (usar este valor)'], $refs['bodegas'], 'FFED7D31');

        $ss->setActiveSheetIndex(0);

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_carga_inventario.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $data = $this->service->getDetalleCompleto($id, $idEmpresa);
        if ($data) {
            echo json_encode(['ok' => true, 'data' => $data]);
        } else {
            echo json_encode(['ok' => false, 'mensaje' => 'Carga no encontrada.']);
        }
    }

    public function aprobarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        if (!$this->service->esAprobador($idUsuario, $idEmpresa, $nivel)) {
            echo json_encode(['ok' => false, 'mensaje' => 'No está autorizado para aprobar cargas de inventario.']);
            return;
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $res = $this->service->aprobar($id, $idEmpresa, $idUsuario, false, $nivel);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Carga aprobada y aplicada al inventario.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function rechazarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        if (!$this->service->esAprobador($idUsuario, $idEmpresa, $nivel)) {
            echo json_encode(['ok' => false, 'mensaje' => 'No está autorizado para rechazar cargas de inventario.']);
            return;
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            if ($motivo === '') {
                echo json_encode(['ok' => false, 'mensaje' => 'Indique el motivo del rechazo.']);
                return;
            }
            $res = $this->service->rechazar($id, $idEmpresa, $idUsuario, $motivo, $nivel);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Carga rechazada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Filas del listado sin paginar (para exportar), respetando búsqueda y registros propios. */
    private function filasParaExportar(): array
    {
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $buscar    = trim($_GET['b'] ?? '');
        // El enlace de exportar lleva el orden de pantalla en `orden=`; si se abre sin
        // parámetros, se respeta la preferencia guardada del usuario.
        $orden     = \App\Helpers\OrdenListado::leer(
            PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO),
            'numero',
            'DESC'
        );
        $ordenCol  = \App\Helpers\OrdenListado::primeraCol($orden, 'numero');
        $ordenDir  = \App\Helpers\OrdenListado::primeraDir($orden);
        $res = $this->service->getListado($idEmpresa, $buscar, 1, 10000, $ordenCol, $ordenDir, $this->idUsuarioFiltro(), $orden);
        return $res['rows'];
    }

    private function etiquetaEstado(string $estado): string
    {
        return ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'][$estado] ?? $estado;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExportar();
        $empresa = (new \App\models\Empresa())->getPorId((int) ($_SESSION['id_empresa'] ?? 0)) ?? [];
        $nombreEmpresa = $empresa['nombre'] ?? 'Cargas de Inventario';

        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $filas = '';
        foreach ($rows as $r) {
            $filas .= '<tr>'
                . '<td>#' . (int) $r['numero'] . '</td>'
                . '<td>' . ($r['fecha'] ? date('d-m-Y', strtotime($r['fecha'])) : '') . '</td>'
                . '<td>' . $e(ucfirst((string) $r['tipo_movimiento'])) . '</td>'
                . '<td align="center">' . (int) $r['total_lineas'] . '</td>'
                . '<td align="center">' . $e($this->etiquetaEstado($r['estado'] ?? '')) . '</td>'
                . '<td>' . $e($r['creado_por_nombre'] ?? '') . '</td>'
                . '<td>' . $e($r['aprobado_por_nombre'] ?? '') . '</td>'
                . '<td>' . $e(\App\Helpers\ObservacionCargaInventario::paraMostrar($r['observacion'] ?? null)) . '</td>'
                . '</tr>';
        }
        if ($filas === '') {
            $filas = '<tr><td colspan="8" align="center">Sin registros</td></tr>';
        }

        $html = '
            <div style="text-align:center;">
                <h2>' . $e($nombreEmpresa) . '</h2>
                <h3>Cargas de Inventario</h3>
                <p style="font-size:9px;">Fecha de reporte: ' . date('d-m-Y H:i:s') . '</p>
            </div>
            <table border="1" cellpadding="4" cellspacing="0" style="font-size:8px;">
                <thead>
                    <tr style="background-color:#eef2f7;font-weight:bold;">
                        <th>N°</th><th>Fecha</th><th>Tipo</th><th>Líneas</th><th>Estado</th><th>Creado por</th><th>Aprobado por</th><th>Observación</th>
                    </tr>
                </thead>
                <tbody>' . $filas . '</tbody>
            </table>';

        try {
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetMargins(12, 12, 12);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('Cargas_Inventario_' . date('Ymd') . '.pdf', 'I');
            exit;
        } catch (\Throwable $ex) {
            echo 'Error al generar PDF: ' . $ex->getMessage();
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExportar();
        $empresa = (new \App\models\Empresa())->getPorId((int) ($_SESSION['id_empresa'] ?? 0)) ?? [];
        $nombreEmpresa = $empresa['nombre'] ?? '';

        $headers = ['N°', 'Fecha', 'Tipo', 'Líneas', 'Estado', 'Creado por', 'Aprobado por', 'Observación'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (int) $r['numero'],
                $r['fecha'] ? date('d-m-Y', strtotime($r['fecha'])) : '',
                ucfirst((string) $r['tipo_movimiento']),
                (int) $r['total_lineas'],
                $this->etiquetaEstado($r['estado'] ?? ''),
                (string) ($r['creado_por_nombre'] ?? ''),
                (string) ($r['aprobado_por_nombre'] ?? ''),
                \App\Helpers\ObservacionCargaInventario::paraMostrar($r['observacion'] ?? null),
            ];
        }

        try {
            (new \App\Services\ReportService())->exportToExcel('Cargas de Inventario', $headers, $data, 'Cargas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Carga eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
