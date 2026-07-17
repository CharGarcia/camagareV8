<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ImportacionesService;
use App\repositories\modulos\ImportacionesRepository;
use App\models\Empresa;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ImportacionesController extends BaseModuloController
{
    private ImportacionesService    $service;
    private ImportacionesRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/importaciones';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportacionesRepository();
        $this->service     = new ImportacionesService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_nacionalizacion');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        $empresaModel     = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        // El punto de emisión por defecto debe salir de un establecimiento ACTIVO
        // (getEstablecimientos() no filtra por estado, solo por eliminado).
        $establecimientosActivos = array_values(array_filter(
            $establecimientos,
            fn($e) => strtolower((string) ($e['estado'] ?? 'activo')) === 'activo'
        ));
        $establecimientoDefault = $establecimientosActivos[0] ?? ($establecimientos[0] ?? null);
        $puntos = $establecimientoDefault ? $empresaModel->getPuntosEmision((int) $establecimientoDefault['id']) : [];

        $this->viewWithLayout('layouts.main', 'modulos/importaciones/index', [
            'titulo'             => 'Importaciones',
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
            'bodegas'            => (new \App\repositories\modulos\BodegaRepository())
                                        ->getBodegasPermitidas((int) $_SESSION['id_usuario'], $idEmpresa, (int) $_SESSION['nivel']),
            'establecimientos'   => $establecimientos,
            'puntos'             => $puntos,
            'sucursal_principal' => $establecimientoDefault,
        ]);
    }

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel     = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int) $_SESSION['id_empresa']);
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

    /**
     * Vista previa del siguiente secuencial (no lo reserva; solo informa). El
     * definitivo se asigna al guardar, vía ImportacionesService::asignarSecuencial().
     * Valida también que el punto de emisión (y su establecimiento) estén
     * ACTIVOS: un punto inactivo no debe ofrecerse para nuevos documentos,
     * aunque tenga secuencial configurado.
     */
    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            echo json_encode(['ok' => true, 'activo' => false, 'configurado' => false, 'detalle' => 'Debe seleccionar un punto de emisión.']);
            exit;
        }

        // El estado activo/inactivo se calcula APARTE y no condiciona la consulta
        // del secuencial: así un fallo o falso negativo en esa comprobación nunca
        // enmascara un secuencial que sí está configurado.
        $serie  = $this->repository->getDatosSerieConEstado($idPunto);
        $activo = $serie !== null && $serie['activo'];

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Importaciones');

        if (!$activo) {
            $res['detalle'] = $serie ? 'El punto de emisión (o su establecimiento) está inactivo.' : 'El punto de emisión no existe.';
        }

        echo json_encode(array_merge(['ok' => true, 'activo' => $activo], $res));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // LISTADO AJAX
    // ─────────────────────────────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_nacionalizacion');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-globe-americas fs-3 d-block mb-2"></i>No se encontraron importaciones.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $fechaNac = !empty($r['fecha_nacionalizacion']) ? date('d-m-Y', strtotime($r['fecha_nacionalizacion'])) : '—';

                $estado = $r['estado'] ?? 'borrador';
                $estadoClass = match ($estado) {
                    'nacionalizada' => 'bg-success bg-opacity-10 text-success border-success',
                    'cerrada'       => 'bg-primary bg-opacity-10 text-primary border-primary',
                    'anulada'       => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'en_transito'   => 'bg-warning bg-opacity-10 text-warning border-warning',
                    default         => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                };
                $estadoLabel = match ($estado) {
                    'nacionalizada' => 'Nacionalizada',
                    'cerrada'       => 'Cerrada',
                    'anulada'       => 'Anulada',
                    'en_transito'   => 'En tránsito',
                    default         => 'Borrador',
                };
                $estadoBadge = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . $estadoLabel . '</span>';

                echo '<tr class="importacion-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalImportacion(this)">
                        <td class="ps-3" data-col="numero_importacion"><code class="text-secondary">' . htmlspecialchars($r['numero_importacion'] ?? '—') . '</code></td>
                        <td data-col="referencia_dai">' . htmlspecialchars($r['referencia_dai'] ?? '—') . '</td>
                        <td class="fw-medium text-truncate" style="max-width:220px" data-col="proveedor_nombre">' . htmlspecialchars($r['proveedor_nombre'] ?? '—') . '</td>
                        <td data-col="incoterm"><small class="text-muted">' . htmlspecialchars($r['incoterm'] ?? '—') . '</small></td>
                        <td data-col="bodega_nombre"><small class="text-muted">' . htmlspecialchars($r['bodega_nombre'] ?? '—') . '</small></td>
                        <td data-col="fecha_nacionalizacion">' . $fechaNac . '</td>
                        <td class="text-end" data-col="subtotal_fob">$' . number_format((float) ($r['subtotal_fob'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold" data-col="costo_total_nacionalizado">$' . number_format((float) ($r['costo_total_nacionalizado'] ?? 0), 2) . '</td>
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

    // ─────────────────────────────────────────────────────────────────────
    // OBTENER / GUARDAR / ELIMINAR
    // ─────────────────────────────────────────────────────────────────────

    public function getImportacionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $importacion = $this->service->getPorId($id, $idEmpresa);
        if (!$importacion) {
            echo json_encode(['ok' => false, 'mensaje' => 'Importación no encontrada']);
            exit;
        }

        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);
        $importacion['puede_aprobar'] = $this->service->esAprobador($idUsuario, $idEmpresa, $nivel);
        if ($importacion['estado'] === 'pendiente_aprobacion') {
            $importacion['aprobadores_nombres'] = $this->service->getAprobadoresNombres($idEmpresa);
        }

        echo json_encode(['ok' => true, 'data' => $importacion]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = json_decode($_POST['data'] ?? '{}', true) ?? [];
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id      = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Importación actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id      = $this->service->crear($data);
                $mensaje = 'Importación registrada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            $db = \App\core\Database::getConnection();
            if ($db->inTransaction()) $db->rollBack();
            error_log('ImportacionesController::guardarAjax: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CARGA MASIVA DE LÍNEAS FOB DESDE EXCEL/CSV (pestaña Productos)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Recibe el Excel/CSV, lo parsea (mismo mecanismo que CargasInventarioController::
     * importarAjax) y devuelve las líneas resueltas contra el catálogo. No persiste
     * nada: el JS agrega cada línea a la tabla de la pestaña Productos, igual que al
     * elegir un producto en el buscador; solo se guardan cuando se guarda la importación.
     */
    public function importarProductosAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'mensaje' => 'Seleccione un archivo Excel válido.']);
            exit;
        }
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Formato no soportado. Use Excel (.xlsx, .xls) o CSV.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $hoja = IOFactory::load($_FILES['archivo']['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);

            if (count($hoja) <= 1) {
                echo json_encode(['ok' => false, 'mensaje' => 'El archivo está vacío o solo contiene los encabezados.']);
                exit;
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
                exit;
            }

            $lineas = $this->service->resolverLineasExcelProductos($filas, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $lineas]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Descarga una plantilla Excel (.xlsx) de ejemplo para cargar líneas de producto FOB. */
    public function descargarPlantillaProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $productos = $this->repository->getProductosParaPlantilla($idEmpresa);

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Datos');

        $cols = [
            'codigo_producto'     => 22,
            'descripcion'         => 32,
            'cantidad'            => 12,
            'precio_unitario_fob' => 16,
            'peso_kg'             => 12,
            'volumen_m3'          => 12,
            'numero_lote'         => 16,
            'fecha_caducidad'     => 16,
            'nup'                 => 18,
        ];
        $numericas = ['cantidad', 'precio_unitario_fob', 'peso_kg', 'volumen_m3'];

        $ci = 1;
        foreach ($cols as $col => $width) {
            $sheet->setCellValueExplicit([$ci, 1], $col, DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($ci)->setWidth($width);
            $sheet->getStyle([$ci, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle([$ci, 1])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');

            $letter = Coordinate::stringFromColumnIndex($ci);
            $rango  = "{$letter}2:{$letter}1001";
            if (in_array($col, $numericas, true)) {
                $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            } else {
                // Texto: evita que Excel altere códigos como "004" o fechas.
                $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            $ci++;
        }

        // Fila de ejemplo (referencial) usando el primer producto de la empresa si existe.
        $codEj = $productos[0]['codigo'] ?? 'COD001';
        $ejemplo = [$codEj, 'Descripción de ejemplo — reemplace esta fila', '10', '5.50', '0', '0', 'L001', '2026-12-31', ''];
        $ce = 1;
        foreach ($ejemplo as $val) { $sheet->setCellValueExplicit([$ce++, 2], (string) $val, DataType::TYPE_STRING); }
        $sheet->getStyle('A2:I2')->getFont()->setItalic(true)->getColor()->setARGB('FF888888');

        // Hoja de referencia con los códigos de producto válidos de la empresa.
        $shRef = $ss->createSheet();
        $shRef->setTitle('Productos');
        $refHeaders = ['CODIGO (usar este valor)', 'NOMBRE'];
        foreach ($refHeaders as $i => $h) {
            $c = $i + 1;
            $shRef->setCellValueExplicit([$c, 1], $h, DataType::TYPE_STRING);
            $shRef->getColumnDimensionByColumn($c)->setWidth(30);
            $shRef->getStyle([$c, 1])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $shRef->getStyle([$c, 1])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF70AD47');
        }
        $r = 2;
        foreach ($productos as $p) {
            $shRef->setCellValueExplicit([1, $r], (string) $p['codigo'], DataType::TYPE_STRING);
            $shRef->setCellValueExplicit([2, $r], (string) $p['nombre'], DataType::TYPE_STRING);
            $r++;
        }

        $ss->setActiveSheetIndex(0);

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_importaciones_productos.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
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
            $this->service->eliminar($id, $idUsuario, $idEmpresa);
            echo json_encode(['ok' => true, 'mensaje' => 'Importación eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRORRATEO (vista previa) Y PROCESAR INVENTARIO (nacionalización)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Vista previa del prorrateo (pestaña "Prorrateo / Resumen"): recalcula sobre
     * lo que hay guardado en BD sin postear al kardex ni cambiar el estado.
     */
    public function previsualizarProrrateoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if (!$id) throw new \Exception('ID requerido.');

            $importacion = $this->service->getPorId($id, $idEmpresa);
            if (!$importacion) throw new \Exception('Importación no encontrada.');

            $detalles = $importacion['detalles'];
            $gastos   = $importacion['gastos'];
            $facturas = $importacion['facturas_exterior'];

            $totalFacturaExterior = array_sum(array_map(fn($f) => (float) $f['monto_usd'], $facturas));
            $tg = $this->service->calcularTotalesGastos($gastos);

            $costoTotal = round($totalFacturaExterior + $tg['capitalizable_total'], 2);
            $detallesConCosto = $this->service->calcularProrrateo($detalles, $costoTotal, $importacion['criterio_prorrateo']);

            echo json_encode([
                'ok'      => true,
                'detalles' => $detallesConCosto,
                'totales' => array_merge($tg, [
                    'total_factura_exterior'    => round($totalFacturaExterior, 2),
                    'costo_total_nacionalizado' => $costoTotal,
                ]),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function procesarInventarioAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            if (!$id) throw new \Exception('ID de importación requerido.');

            $resultado = $this->service->procesarInventario($id, $idEmpresa, $idUsuario);

            if (!empty($resultado['pendiente_aprobacion'])) {
                $mensaje = 'Costo calculado: $' . number_format($resultado['costo_total_nacionalizado'], 2)
                    . '. Esta empresa exige aprobación para el inventario: la importación quedó pendiente de aprobación y no se afectó el kardex todavía.';
            } else {
                $mensaje = 'Importación nacionalizada. Costo total capitalizado: $' . number_format($resultado['costo_total_nacionalizado'], 2) . '.';
                if (!empty($resultado['asiento_warning'])) {
                    $mensaje .= ' Atención: el asiento contable no se pudo generar (' . $resultado['asiento_warning'] . '). Configúrelo en /config/asientos-contables y vuelva a intentar.';
                }
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'data' => $resultado]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function aprobarNacionalizacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) throw new \Exception('ID de importación requerido.');

            if (!$this->service->esAprobador($idUsuario, $idEmpresa, $nivel)) {
                throw new \Exception('No está autorizado para aprobar la nacionalización de importaciones.');
            }

            $resultado = $this->service->aprobarNacionalizacion($id, $idEmpresa, $idUsuario, $nivel);

            $mensaje = 'Importación aprobada y nacionalizada. Costo total capitalizado: $' . number_format($resultado['costo_total_nacionalizado'], 2) . '.';
            if (!empty($resultado['asiento_warning'])) {
                $mensaje .= ' Atención: el asiento contable no se pudo generar (' . $resultado['asiento_warning'] . '). Configúrelo en /config/asientos-contables.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'data' => $resultado]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function rechazarNacionalizacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        try {
            $id     = (int) ($_POST['id'] ?? 0);
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            if (!$id) throw new \Exception('ID de importación requerido.');
            if ($motivo === '') throw new \Exception('Indique el motivo del rechazo.');

            if (!$this->service->esAprobador($idUsuario, $idEmpresa, $nivel)) {
                throw new \Exception('No está autorizado para rechazar la nacionalización de importaciones.');
            }

            $resultado = $this->service->rechazarNacionalizacion($id, $idEmpresa, $idUsuario, $motivo, $nivel);
            echo json_encode(['ok' => true, 'mensaje' => 'Importación rechazada; vuelve a borrador para que la corrijan.', 'data' => $resultado]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa     = (int) $_SESSION['id_empresa'];
        $idImportacion = (int) ($_GET['id'] ?? 0);

        try {
            if ($idImportacion <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $importacion = $this->service->getPorId($idImportacion, $idEmpresa);
            if (!$importacion) {
                echo json_encode(['ok' => true, 'detalles' => []]);
                exit;
            }

            $idAsiento = (int) ($importacion['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
                $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
                $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, new \App\Services\LogSistemaService());
                $cab = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);

                $detalles = [];
                foreach (($cab['detalles'] ?? []) as $det) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int) $det['id_cuenta_contable'],
                        'cuenta_codigo'      => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                        'cuenta_nombre'      => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                        'debe'               => (float) $det['debe'],
                        'haber'              => (float) $det['haber'],
                        'referencia_detalle' => $det['referencia_detalle'] ?? '',
                    ];
                }
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                exit;
            }

            if (($importacion['estado'] ?? '') !== 'nacionalizada') {
                // Sin nacionalizar aún no hay totales definitivos que capitalizar.
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $builder  = new \App\Services\modulos\AsientoBuilderService();
            $detalles = $builder->generarAsientoImportacion($idEmpresa, $idImportacion);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CATÁLOGOS / TYPEAHEADS
    // ─────────────────────────────────────────────────────────────────────

    public function getProveedoresExteriorAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarProveedoresExterior($idEmpresa, $buscar)]);
        exit;
    }

    public function getAgentesAfianzadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarProveedoresLocales($idEmpresa, $buscar)]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'compra', true);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function buscarComprasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarComprasParaVincular($idEmpresa, $buscar)]);
        exit;
    }

    public function buscarLiquidacionesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarLiquidacionesParaVincular($idEmpresa, $buscar)]);
        exit;
    }
}
