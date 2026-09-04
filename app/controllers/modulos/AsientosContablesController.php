<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AsientoContableService;
use App\models\CentroCosto;
use App\models\Proyecto;
use App\models\Empresa;

class AsientosContablesController extends BaseModuloController
{
    private AsientoContableService $service;
    private const RUTA_MODULO = 'modulos/asientos_contables';

    public function __construct()
    {
        parent::__construct();
        $repository = new AsientoContableRepository();
        $rules = new AsientoContableRules();
        $logService = new LogSistemaService();
        $this->service = new AsientoContableService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        // La generación de asientos pendientes NO se hace aquí (bloquearía la carga cuando hay
        // muchos por generar). La dispara la vista en segundo plano vía sincronizarAjax() y,
        // si se generan asientos nuevos, refresca el listado.
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_asiento');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.asientos_contables.index', [
            'titulo'     => 'Libro Diario / Asientos',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig'=> $prefsVista,
            'fullWidth'  => true,
        ]);
    }

    /**
     * Genera en segundo plano los asientos contables pendientes (documentos sin asiento).
     * Se invoca por AJAX desde la vista al cargar, para no bloquear la carga del listado
     * cuando hay muchos documentos pendientes. Devuelve los avisos y cuántos se generaron
     * (si es > 0, la vista refresca la tabla para mostrar los nuevos asientos).
     */
    public function sincronizarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            // Liberar el lock de sesión para no bloquear otras peticiones del usuario
            // mientras dura la generación, y ampliar el tiempo máximo de ejecución.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            @set_time_limit(300);

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            $sincronizador->sincronizar($idEmpresa, $idUsuario);

            echo json_encode([
                'success'   => true,
                'resumen'   => $sincronizador->getResumenMensaje(),
                'detalle'   => $sincronizador->getDetalle(),
                'warnings'  => $sincronizador->getWarnings(),
                'info'      => $sincronizador->getInfo(),
                'generados' => $sincronizador->getGenerados(),
            ]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Ejecuta UN paso de la sincronización (un módulo, o una de las verificaciones fijas del
     * final) y devuelve de inmediato — permite a la UI mostrar una barra de progreso real
     * (paso/totalPasos) e interrumpir el proceso entre pasos sin más que dejar de pedir el
     * siguiente. Ver SincronizadorAsientosService::ejecutarPaso().
     */
    public function sincronizarPasoAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $paso = (int) ($_GET['paso'] ?? $_POST['paso'] ?? 0);

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            @set_time_limit(120);

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            $resultado = $sincronizador->ejecutarPaso($idEmpresa, $idUsuario, $paso);

            echo json_encode(['ok' => true] + $resultado);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Un paso de la generación de asientos para DOCUMENTOS MIGRADOS que siguen sin asiento.
     * Acción explícita del usuario desde el aviso informativo, previa confirmación de que la
     * migración de contabilidad ya se corrió completa (ver
     * SincronizadorAsientosService::ejecutarPasoMigrados()). Misma respuesta que
     * sincronizarPasoAjax() para reutilizar la barra de progreso de la UI.
     */
    public function sincronizarMigradosPasoAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $paso = (int) ($_GET['paso'] ?? $_POST['paso'] ?? 0);

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            @set_time_limit(120);

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            $resultado = $sincronizador->ejecutarPasoMigrados($idEmpresa, $idUsuario, $paso);

            echo json_encode(['ok' => true] + $resultado);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Cuenta cuántos documentos operativos están pendientes de generar su asiento contable,
     * sin generar nada. La vista lo consulta al cargar para preguntar al usuario si desea
     * generarlos ahora o continuar sin generar.
     */
    public function contarPendientesAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            echo json_encode([
                'ok'                   => true,
                'pendientes'           => $sincronizador->contarPendientes($idEmpresa),
                'migrados_sin_asiento' => $sincronizador->contarMigradosSinAsiento($idEmpresa),
            ]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $th->getMessage()]);
        }
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_asiento');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage   = 20;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron asientos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $statusBadge = '';
                if ($r['estado'] === 'contabilizado') $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Contabilizado</span>';
                elseif ($r['estado'] === 'anulado') $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>';
                else $statusBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';

                echo '<tr class="asiento-row" role="button" onclick="ASIENTO_abrirModal(' . $r['id'] . ')">
                        <td class="ps-3 fw-bold" data-col="numero_comprobante">' . htmlspecialchars($r['numero_comprobante'] ?? '') . '</td>
                        <td data-col="fecha_asiento">' . htmlspecialchars($r['fecha_asiento']) . '</td>
                        <td data-col="tipo_comprobante" class="text-capitalize">' . htmlspecialchars($r['tipo_comprobante']) . '</td>
                        <td data-col="concepto" class="small text-truncate" style="max-width: 250px;">' . htmlspecialchars($r['concepto']) . '</td>
                        <td data-col="modulo_origen" class="text-capitalize small text-muted">' . str_replace('_', ' ', htmlspecialchars($r['modulo_origen'] ?? '')) . '</td>
                        <td data-col="total_debe" class="text-end fw-bold">$' . number_format((float)($r['total_debe'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="estado">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total"
        ]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        // Permite buscar por ID directo o por Origen (módulo + id_referencia)
        $id = (int)($_GET['id'] ?? 0);
        $modulo = trim($_GET['modulo'] ?? '');
        $idRef = (int)($_GET['id_ref'] ?? 0);

        if ($modulo !== '' && $idRef > 0) {
            $data = $this->service->getAsientoPorOrigen($modulo, $idRef, $idEmpresa);
        } else {
            $data = $this->service->getDetalleAsiento($id, $idEmpresa);
        }

        if (!$data) {
            // Sin asiento todavía (se abrió desde un documento que aún no se contabilizó): se
            // devuelve igual el importe del documento para que el modal pueda mostrar el cuadre
            // en vivo mientras el usuario arma las líneas a mano.
            echo json_encode([
                'ok' => false,
                'error' => 'No se encontró el asiento.',
                'cuadre_documento' => $modulo !== '' && $idRef > 0
                    ? $this->service->getContextoCuadreDocumento($modulo, $idRef, $idEmpresa)
                    : null,
            ]);
        } else {
            // Importe del documento origen: el modal lo muestra y avisa en vivo si el asiento
            // deja de reflejarlo mientras se edita.
            $data['cuadre_documento'] = $this->service->getContextoCuadreDocumento(
                $data['modulo_origen'] ?? null,
                (int) ($data['id_referencia_origen'] ?? 0),
                $idEmpresa
            );
            echo json_encode(['ok' => true, 'data' => $data]);
        }
        exit;
    }

    public function getSelectDataAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        $ccModel = new CentroCosto();
        $centros = $ccModel->getActivosPorEmpresa($idEmpresa);
        
        $pjModel = new Proyecto();
        $proyectos = $pjModel->getActivosPorEmpresa($idEmpresa);
        
        echo json_encode([
            'ok' => true, 
            'data' => [
                'centros_costo' => $centros,
                'proyectos' => $proyectos
            ]
        ]);
        exit;
    }

    public function getDocumentoOrigenAjax(): void
    {
        $this->requireLeer();
        try {
            $servicio = new \App\Services\modulos\DocumentoOrigenService();
            $datos = $servicio->getDetalle(
                trim($_GET['modulo'] ?? ''),
                (int) ($_GET['id'] ?? 0),
                (int) $_SESSION['id_empresa']
            );
            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $asiento = $this->service->getDetalleAsiento($id, $idEmpresa);
            if (!$asiento) {
                http_response_code(404); echo 'Asiento no encontrado'; exit;
            }

            $empresaModel = new Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa) ?? [];

            $pdfService = new \App\Services\modulos\AsientoContablePdfService();
            $pdfService->generar($asiento, $empresa);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function exportarExcelAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $asiento = $this->service->getDetalleAsiento($id, $idEmpresa);
            if (!$asiento) {
                http_response_code(404); echo 'Asiento no encontrado'; exit;
            }

            $empresaModel = new Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa) ?? [];

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Asiento');

            $sheet->setCellValue('A1', strtoupper((string)($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'ASIENTO CONTABLE N.° ' . ($asiento['numero_comprobante'] ?? ''));
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($asiento['fecha_asiento']) ? date('d-m-Y', strtotime((string)$asiento['fecha_asiento'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Tipo: ' . str_replace('_', ' ', (string)($asiento['tipo_comprobante'] ?? '')));
            $sheet->setCellValue('E3', 'Estado: ' . ucfirst((string)($asiento['estado'] ?? '')));

            $sheet->setCellValue('A4', 'Concepto: ' . (string)($asiento['concepto'] ?? ''));
            $sheet->mergeCells('A4:F4');

            $headerRow = 6;
            $headers = ['Cuenta Contable', 'Centro Costo', 'Proyecto', 'Documento/Ref', 'Debe', 'Haber'];
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
            $totDebe = 0.0;
            $totHaber = 0.0;
            foreach ($asiento['detalles'] ?? [] as $d) {
                $cuenta = trim((string)($d['codigo_cuenta'] ?? '') . ' - ' . (string)($d['nombre_cuenta'] ?? ''), ' -');
                $debe  = (float)($d['debe'] ?? 0);
                $haber = (float)($d['haber'] ?? 0);
                $totDebe  += $debe;
                $totHaber += $haber;

                $sheet->setCellValueExplicit('A' . $row, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string)($d['nombre_centro_costo'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, (string)($d['nombre_proyecto'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $row, (string)($d['documento_referencia'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('E' . $row, $debe > 0 ? $debe : null);
                $sheet->setCellValue('F' . $row, $haber > 0 ? $haber : null);
                $row++;
            }

            $sheet->setCellValue('D' . $row, 'TOTALES');
            $sheet->getStyle('D' . $row)->getFont()->setBold(true);
            $sheet->setCellValue('E' . $row, $totDebe);
            $sheet->setCellValue('F' . $row, $totHaber);
            $sheet->getStyle('E' . $row . ':F' . $row)->getFont()->setBold(true);

            $sheet->getStyle('E' . ($headerRow + 1) . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Asiento_' . ($asiento['numero_comprobante'] ?: 'comprobante') . '.xlsx';

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

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $cuadre = $this->service->evaluarCuadreDocumento($data['cabecera'], $data['detalles'], $idEmpresa);
            if ($this->cortarPorCuadreDocumento($cuadre)) {
                exit;
            }

            $id = $this->service->guardarAsiento($data['cabecera'], $data['detalles'], $idEmpresa, $idUsuario, true);
            if ($this->hayDescuadreDocumento($cuadre)) {
                $this->service->registrarDescuadreConfirmado($id, $cuadre, $idEmpresa, $idUsuario);
            }
            echo json_encode(['ok' => true, 'msg' => 'Asiento registrado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idAsiento = (int) ($data['cabecera']['id'] ?? 0);
            if ($idAsiento <= 0) {
                throw new \Exception('ID de asiento inválido.');
            }

            // Regla: un asiento anulado solo se puede modificar si es de tipo Diario.
            $existente = $this->service->getDetalleAsiento($idAsiento, $idEmpresa);
            if ($existente
                && ($existente['estado'] ?? '') === 'anulado'
                && strtolower(trim($existente['tipo_comprobante'] ?? '')) !== 'diario') {
                throw new \Exception('No se puede modificar un asiento anulado. Solo los de tipo Diario son editables.');
            }

            $cuadre = $this->service->evaluarCuadreDocumento($data['cabecera'], $data['detalles'], $idEmpresa);
            if ($this->cortarPorCuadreDocumento($cuadre)) {
                exit;
            }

            // edicionManual = true: lo guarda una persona, así que queda marcado y el módulo
            // dueño del documento ya no lo regenera desde el builder al reguardar el documento
            // (se revierte con restaurarAutomaticoAjax).
            $id = $this->service->guardarAsiento($data['cabecera'], $data['detalles'], $idEmpresa, $idUsuario, true);
            if ($this->hayDescuadreDocumento($cuadre)) {
                $this->service->registrarDescuadreConfirmado($id, $cuadre, $idEmpresa, $idUsuario);
            }
            echo json_encode(['ok' => true, 'msg' => 'Asiento actualizado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function restablecer(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        try {
            $this->service->restablecer($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asiento restablecido a contabilizado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function anular(): void
    {
        $this->requireActualizar(); // O anular si existe el permiso
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asiento anulado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * `modulo_origen` del asiento => clave del trabajo en SincronizadorAsientosService, para
     * poder pedirle al service dueño del documento que vuelva a armar el asiento.
     *
     * Los orígenes que NO están aquí (nota de débito, factura de reembolso, activos fijos,
     * traspasos, conciliación de tarjetas) no tienen trabajo en el sincronizador: se les quita
     * la marca igual y el asiento se regenera la próxima vez que se guarde el documento.
     */
    private const CLAVE_SINCRONIZADOR = [
        'factura_venta'      => 'facturas_venta',
        'recibo_venta'       => 'recibos_venta',
        'compra'             => 'compras',
        'liquidacion_compra' => 'liquidaciones_compra',
        'nota_credito'       => 'notas_credito',
        'retencion_venta'    => 'retenciones_venta',
        'retencion_compra'   => 'retenciones_compra',
        'ingreso'            => 'ingresos',
        'egreso'             => 'egresos',
        'consignacion_venta' => 'consignaciones',
        'retorno_cv'         => 'retornos_cv',
        'cambio_producto_cv' => 'cambios_producto_cv',
        'FACTURACION_CV'     => 'facturacion_cv',
        'importacion'        => 'importaciones',
    ];

    /**
     * Descarta la edición manual de un asiento de documento y lo vuelve a armar con las reglas
     * contables configuradas. Es la salida cuando alguien corrigió el asiento a mano y quiere
     * volver al automático.
     */
    public function restaurarAutomaticoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idAsiento = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $asiento = $this->service->getDetalleAsiento($idAsiento, $idEmpresa);
            if (!$asiento) {
                throw new \Exception('No se encontró el asiento.');
            }

            $modulo = (string) ($asiento['modulo_origen'] ?? 'manual');
            $idRef  = (int) ($asiento['id_referencia_origen'] ?? 0);
            if ($modulo === 'manual' || $idRef <= 0) {
                throw new \Exception('Este asiento no proviene de un documento: no hay reglas desde las cuales regenerarlo.');
            }

            $this->service->restaurarAsientoAutomatico($idAsiento, $idEmpresa, $idUsuario);

            $regenerado = $this->regenerarDesdeDocumento($modulo, $idRef, $idEmpresa);
            echo json_encode([
                'ok' => true,
                'regenerado' => $regenerado,
                'msg' => $regenerado
                    ? 'El asiento se volvió a generar desde las reglas contables.'
                    : 'Se quitó la edición manual. El asiento se regenerará al guardar nuevamente el documento.',
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Pide al service dueño del documento que vuelva a armar el asiento. Reutiliza las factories
     * de SincronizadorAsientosService para no repetir aquí cómo se construye cada service.
     *
     * @return bool true si el asiento se regeneró en el acto.
     */
    private function regenerarDesdeDocumento(string $moduloOrigen, int $idReferencia, int $idEmpresa): bool
    {
        $clave = self::CLAVE_SINCRONIZADOR[$moduloOrigen] ?? null;
        if ($clave === null) {
            return false;
        }

        $trabajo = (new \App\Services\modulos\SincronizadorAsientosService())
            ->getTrabajoPorClave($idEmpresa, $clave);
        if ($trabajo === null || empty($trabajo['factory'])) {
            return false;
        }

        $service = ($trabajo['factory'])();
        if (!method_exists($service, 'procesarAsientoContablePorSincronizacion')) {
            return false;
        }

        $service->procesarAsientoContablePorSincronizacion($idReferencia);
        return true;
    }

    /** ¿El asiento no refleja el importe de su documento origen? (null = no aplica la comprobación) */
    private function hayDescuadreDocumento(?array $cuadre): bool
    {
        return $cuadre !== null && (empty($cuadre['cuadra']) || !empty($cuadre['sin_linea_cartera']));
    }

    /**
     * Corta el guardado cuando el asiento no cuadra con su documento origen y devuelve true si
     * ya respondió. Dos casos distintos:
     *  - falta la línea de cartera (cuenta por cobrar/pagar): no se puede guardar así, porque sin
     *    ella no hay forma de comprobar que el asiento refleje el documento;
     *  - hay diferencia de importe: se avisa y el usuario decide — el front reenvía con
     *    `confirmar_descuadre` y el guardado queda registrado en la auditoría.
     */
    private function cortarPorCuadreDocumento(?array $cuadre): bool
    {
        if (!$this->hayDescuadreDocumento($cuadre)) {
            return false;
        }

        if (!empty($cuadre['sin_linea_cartera'])) {
            echo json_encode(['ok' => false, 'error' => $cuadre['mensaje']]);
            return true;
        }

        if (!empty($_POST['confirmar_descuadre'])) {
            return false;
        }

        echo json_encode([
            'ok' => false,
            'requiere_confirmacion' => true,
            'mensaje' => $cuadre['mensaje'],
            'cuadre' => $cuadre,
        ]);
        return true;
    }

    private function recogerDatosFormulario(): array
    {
        $cabecera = [
            'id' => (int)($_POST['id'] ?? 0),
            'fecha_asiento' => trim($_POST['fecha_asiento'] ?? ''),
            'tipo_comprobante' => trim($_POST['tipo_comprobante'] ?? 'diario'),
            'numero_comprobante' => trim($_POST['numero_comprobante'] ?? ''),
            'concepto' => trim($_POST['concepto'] ?? ''),
            'estado' => trim($_POST['estado'] ?? 'contabilizado'), // Por default lo guardamos como contabilizado si no envían borrador
            'observaciones' => trim($_POST['observaciones'] ?? ''),
            'modulo_origen' => trim($_POST['modulo_origen'] ?? 'manual'),
            'id_referencia_origen' => !empty($_POST['id_referencia_origen']) ? (int)$_POST['id_referencia_origen'] : null,
        ];

        $detalles = [];
        if (!empty($_POST['detalles_json'])) {
            $det = json_decode($_POST['detalles_json'], true);
            if (is_array($det)) {
                $detalles = $det;
            }
        }

        return [
            'cabecera' => $cabecera,
            'detalles' => $detalles
        ];
    }
}
