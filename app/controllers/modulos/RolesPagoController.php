<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\RolPagoRepository;
use App\Rules\modulos\RolPagoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\RolPagoService;
use App\models\CatalogoRol;
use App\models\CatalogoNovedades;
use App\models\Empresa;
use App\Services\modulos\RolPagoPdfService;
use App\Services\EnvioDocumentosSRIService;

class RolesPagoController extends BaseModuloController
{
    private RolPagoService $service;
    private const RUTA_MODULO = 'modulos/roles-pago';

    public function __construct()
    {
        parent::__construct();
        $this->service = new RolPagoService(new RolPagoRepository(), new RolPagoRules(), new LogSistemaService());
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
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, (int) $_SESSION['id_usuario']);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.roles_pago.index', [
            'titulo'     => 'Roles de Pago',
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
            'tipos'      => CatalogoRol::tipos(),
            'estados'    => CatalogoRol::estados(),
            'meses'      => CatalogoNovedades::MESES,
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

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, (int) $_SESSION['id_usuario']);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $from = $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted">No hay corridas de rol registradas.</td></tr>';
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
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $mes = CatalogoNovedades::MESES[(int) $r['periodo_mes']] ?? $r['periodo_mes'];
        $num = (int) $r['numero_periodo'] > 0 ? ' #' . (int) $r['numero_periodo'] : '';
        $periodo = $h($mes . ' ' . $r['periodo_anio'] . $num);
        $fpago = $r['fecha_pago'] ? date('d-m-Y', strtotime((string) $r['fecha_pago'])) : '—';

        $colores = ['borrador' => 'secondary', 'generado' => 'info', 'pagado' => 'success', 'contabilizado' => 'primary', 'anulado' => 'danger'];
        $c = $colores[$r['estado']] ?? 'secondary';
        $estado = '<span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . ' border-opacity-25">' . $h(CatalogoRol::nombreEstado((string) $r['estado'])) . '</span>';

        return '<tr class="rol-row" role="button" data-row=\'' . $dataJson . '\' onclick="abrirModalVer(this)">'
            . '<td class="ps-3 fw-medium" data-col="tipo">' . $h(CatalogoRol::nombreTipo((string) $r['tipo_rol'])) . '</td>'
            . '<td data-col="periodo">' . $periodo . '</td>'
            . '<td class="text-center" data-col="empleados">' . (int) ($r['num_empleados'] ?? 0) . '</td>'
            . '<td class="text-end fw-bold" data-col="neto">$' . number_format((float) $r['total_neto'], 2) . '</td>'
            . '<td class="text-center" data-col="estado">' . $estado . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">'
            . '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            . '</td></tr>';
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $data = $this->recogerCabecera();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Corrida creada. Ahora puede generarla.', 'id' => $id]);
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
        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->recogerCabecera();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->actualizar($id, (int) $_SESSION['id_empresa'], $data);
            echo json_encode(['ok' => true, 'msg' => 'Corrida actualizada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function generar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $data = $this->service->generar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Rol generado correctamente.', 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id = (int) ($_GET['id'] ?? 0);
        $data = $this->service->getDetalle($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
        echo json_encode($data ? ['ok' => true, 'data' => $data] : ['ok' => false, 'error' => 'No encontrado']);
        exit;
    }

    /** Datos para el modal de "Generar egresos de nómina": formas de pago, puntos y concepto. */
    public function datosEgresoLoteAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $fpRepo = new \App\repositories\modulos\FormaPagoRepository();
        $formas = $fpRepo->getFormasFiltradas($idEmpresa, 'EGRESO');

        $emp    = new Empresa();
        $ests   = $emp->getEstablecimientos($idEmpresa);
        $puntos = !empty($ests) ? $emp->getPuntosEmision((int) $ests[0]['id']) : [];

        $db = \App\core\Database::getConnection();
        $stC = $db->prepare("SELECT COUNT(*) FROM empresa_opciones_ingreso_egreso
                             WHERE id_empresa = :e AND comportamiento = 'ROL' AND aplica_egresos = true
                               AND eliminado = false AND UPPER(estado) = 'ACTIVO'");
        $stC->execute([':e' => $idEmpresa]);
        $tieneConcepto = ((int) $stC->fetchColumn()) > 0;

        // Empleados del rol que aún tienen saldo pendiente: el usuario elige a
        // cuáles generarles el egreso (los ya pagados no se listan). Se refresca antes
        // de listar (si la corrida sigue sin pagos) para no mostrar montos desactualizados.
        $idRol = (int) ($_GET['id_rol'] ?? 0);
        $empleados = [];
        if ($idRol > 0) {
            $this->service->refrescarSiCorresponde($idRol, $idEmpresa, (int) $_SESSION['id_usuario']);
            foreach ((new RolPagoRepository())->getDetalleCompleto($idRol, $idEmpresa) as $d) {
                if (round((float) ($d['saldo'] ?? 0), 2) <= 0.01) {
                    continue; // ya pagado
                }
                $empleados[] = [
                    'id_detalle'        => (int) $d['id'],
                    'nombres_apellidos' => (string) ($d['nombres_apellidos'] ?? ''),
                    'identificacion'    => (string) ($d['identificacion'] ?? ''),
                    'neto'              => round((float) $d['neto'], 2),
                    'pagado'            => round((float) $d['pagado'], 2),
                    'saldo'             => round((float) $d['saldo'], 2),
                    'estado_pago'       => (string) $d['estado_pago'],
                ];
            }
        }

        echo json_encode([
            'ok' => true,
            'formas' => $formas,
            'puntos' => $puntos,
            'tiene_concepto' => $tieneConcepto,
            'pendientes' => count($empleados),
            'empleados' => $empleados,
        ]);
        exit;
    }

    /** Genera en lote un egreso por empleado del rol (solo los que tienen saldo pendiente). */
    public function generarEgresosLoteAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idRol = (int) ($_POST['id_rol'] ?? 0);
            if ($idRol <= 0) throw new \Exception('Rol no válido.');
            $opts = [
                'fecha'                    => trim($_POST['fecha'] ?? ''),
                'id_forma_pago'            => (int) ($_POST['id_forma_pago'] ?? 0),
                'id_punto_emision'         => (int) ($_POST['id_punto_emision'] ?? 0),
                'tipo_operacion_bancaria'  => trim($_POST['tipo_operacion_bancaria'] ?? ''),
                'numero_cheque_inicial'    => (int) ($_POST['numero_cheque_inicial'] ?? 0),
                // Fecha de cobro del cheque, aplica a todo el lote (control de posfechados)
                'fecha_cobro'              => trim($_POST['fecha_cobro'] ?? ''),
                // Empleados marcados por el usuario (líneas del rol). Vacío = todos los pendientes.
                'ids_detalle'              => array_map('intval', (array) ($_POST['ids_detalle'] ?? [])),
            ];
            $svc = new \App\Services\modulos\RolEgresoLoteService(new LogSistemaService());
            $res = $svc->generar($idRol, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $opts);
            echo json_encode(array_merge(['ok' => true], $res));
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function contabilizar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $res = $this->service->contabilizar((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Rol contabilizado. Asiento generado.', 'data' => $res]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstado(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $estado = trim($_POST['estado'] ?? '');
        try {
            $this->service->cambiarEstado($id, (int) $_SESSION['id_empresa'], $estado, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        $id = (int) ($_POST['id_eliminar'] ?? 0);
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Corrida eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** PDF del rol general (planilla horizontal, con logo, datos de empresa y firmas). */
    public function pdf(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $dest = ($_GET['view'] ?? '') === '1' ? 'I' : 'D'; // descarga por defecto, igual que pdfEmpleado()
        try {
            $rol = $this->service->getDetalle($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            if (!$rol) { http_response_code(404); echo 'Corrida no encontrada'; exit; }
            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            (new RolPagoPdfService())->generarGeneral($rol, $empresa, $dest);
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Excel general del rol: una fila por empleado con su desglose de totales. */
    public function excelGeneral(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $rol = $this->service->getDetalle($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            if (!$rol) { http_response_code(404); echo 'Corrida no encontrada'; exit; }

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            $mes = CatalogoNovedades::MESES[(int) $rol['periodo_mes']] ?? $rol['periodo_mes'];
            $num = (int) ($rol['numero_periodo'] ?? 0) > 0 ? ' #' . (int) $rol['numero_periodo'] : '';
            $empNom = (string) ($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? '');
            $empRuc = (string) ($empresa['ruc'] ?? '');
            $titulo = trim($empNom . ' (RUC ' . $empRuc . ') — ' . CatalogoRol::nombreTipo((string) $rol['tipo_rol']) . ' - ' . $mes . ' ' . $rol['periodo_anio'] . $num);

            // Hoja 1: resumen, un empleado por fila con el mayor detalle posible.
            $headers = ['Empleado', 'Identificación', 'Cargo', 'Días', 'Sueldo Base', 'Total Ingresos', 'Aporte IESS', 'IR', 'Otros Egresos', 'Total Egresos', 'Neto'];
            $data = [];
            $novedades = [];
            $otros = [];
            foreach ($rol['detalle'] as $d) {
                $iess = (float) ($d['aporte_iess'] ?? 0);
                $ir   = (float) ($d['retencion_renta'] ?? 0);
                $egr  = (float) ($d['total_egresos'] ?? 0);
                $data[] = [
                    $d['nombres_apellidos'],
                    $d['identificacion'],
                    $d['cargo'] ?? '—',
                    (float) ($d['dias_trabajados'] ?? 0),
                    (float) ($d['sueldo_base'] ?? 0),
                    (float) ($d['total_ingresos'] ?? 0),
                    $iess,
                    $ir,
                    max(0.0, $egr - $iess - $ir),
                    $egr,
                    (float) ($d['neto'] ?? 0),
                ];

                // Hoja 2 (Novedades) y Hoja 3 (Otros Detalles): todos los rubros de cada empleado.
                foreach (($d['rubros'] ?? []) as $r) {
                    $fila = [$d['nombres_apellidos'], $d['identificacion'], $r['concepto'] ?? '', ($r['tipo'] ?? '') === 'ingreso' ? 'Ingreso' : 'Egreso', (float) ($r['valor'] ?? 0)];
                    if (($r['origen'] ?? '') === 'novedad') {
                        $novedades[] = $fila;
                    } elseif (($r['origen'] ?? '') !== 'sueldo') {
                        array_splice($fila, 3, 0, [(string) ($r['origen'] ?? '')]);
                        $otros[] = $fila;
                    }
                }
            }

            $reportSvc = new \App\Services\ReportService();
            $spreadsheet = $reportSvc->construirSpreadsheet($headers, $data, 'Resumen', $titulo);
            $reportSvc->agregarHoja($spreadsheet, ['Empleado', 'Identificación', 'Concepto', 'Tipo', 'Valor'], $novedades, 'Novedades');
            $reportSvc->agregarHoja($spreadsheet, ['Empleado', 'Identificación', 'Concepto', 'Origen', 'Tipo', 'Valor'], $otros, 'Otros Detalles');
            $reportSvc->descargarSpreadsheet($spreadsheet, 'Rol_' . $id);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    /** Detalle de un empleado del rol (general + provisiones + asiento). */
    public function getEmpleadoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idDetalle = (int) ($_GET['det'] ?? 0);
        $data = $this->service->getEmpleadoCompleto($idDetalle, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
        echo json_encode($data ? ['ok' => true, 'data' => $data] : ['ok' => false, 'error' => 'No encontrado']);
        exit;
    }

    /** PDF individual (recibo) de un empleado dentro del rol. */
    public function pdfEmpleado(): void
    {
        $this->requireLeer();
        $idDetalle = (int) ($_GET['det'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $dest = ($_GET['view'] ?? '') === '1' ? 'I' : 'D'; // descarga por defecto
        try {
            $lin = $this->service->getLineaEmpleado($idDetalle, $idEmpresa, (int) $_SESSION['id_usuario']);
            if (!$lin) { http_response_code(404); echo 'Línea no encontrada'; exit; }
            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            (new RolPagoPdfService())->generarEmpleado($lin, $empresa, $dest);
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Excel individual (ficha) de un empleado dentro del rol: cabecera del rol +
     * tabla Concepto/Ingreso/Egreso (igual al desglose del PDF) + neto a recibir.
     */
    public function excelEmpleado(): void
    {
        $this->requireLeer();
        $idDetalle = (int) ($_GET['det'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if ($idDetalle <= 0) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $lin = $this->service->getEmpleadoCompleto($idDetalle, $idEmpresa, (int) $_SESSION['id_usuario']);
            if (!$lin) { http_response_code(404); echo 'Línea no encontrada'; exit; }
            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);

            $cab = $lin['cabecera'] ?? [];
            $mes = CatalogoNovedades::MESES[(int) ($cab['periodo_mes'] ?? 0)] ?? ($cab['periodo_mes'] ?? '');
            $periodo = trim($mes . ' ' . ($cab['periodo_anio'] ?? ''));
            $tipo = CatalogoRol::nombreTipo((string) ($cab['tipo_rol'] ?? ''));

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Rol de Pago');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];

            $empNom = (string) ($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? '');
            $empRuc = (string) ($empresa['ruc'] ?? '');
            $empDir = (string) ($empresa['direccion'] ?? '');
            $empTel = (string) ($empresa['telefono'] ?? '');
            $sheet->setCellValue('A1', strtoupper($empNom));
            $sheet->mergeCells('A1:C1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $filaInfoEmp = 2;
            $sheet->setCellValueExplicit('A' . $filaInfoEmp, 'RUC: ' . $empRuc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->mergeCells('A' . $filaInfoEmp . ':C' . $filaInfoEmp);
            $filaInfoEmp++;
            if ($empDir !== '') {
                $sheet->setCellValueExplicit('A' . $filaInfoEmp, $empDir, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->mergeCells('A' . $filaInfoEmp . ':C' . $filaInfoEmp);
                $filaInfoEmp++;
            }
            if ($empTel !== '') {
                $sheet->setCellValueExplicit('A' . $filaInfoEmp, 'Tel: ' . $empTel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->mergeCells('A' . $filaInfoEmp . ':C' . $filaInfoEmp);
                $filaInfoEmp++;
            }

            $filaInfoEmp++;
            $sheet->setCellValue('A' . $filaInfoEmp, 'ROL DE PAGO — ' . strtoupper(trim($tipo . ' ' . $periodo)));
            $sheet->mergeCells('A' . $filaInfoEmp . ':C' . $filaInfoEmp);
            $sheet->getStyle('A' . $filaInfoEmp)->getFont()->setBold(true);
            $filaInfoEmp += 2;

            $filaDatos = [
                'Empleado:'        => (string) ($lin['nombres_apellidos'] ?? ''),
                'Cédula:'          => (string) ($lin['identificacion'] ?? ''),
                'Cargo:'           => (string) ($lin['cargo'] ?? '—'),
                'Días trabajados:' => (string) ($lin['dias_trabajados'] ?? 0),
                'Sueldo base:'     => number_format((float) ($lin['sueldo_base'] ?? 0), 2),
                'Fecha de pago:'   => !empty($cab['fecha_pago']) ? date('d-m-Y', strtotime((string) $cab['fecha_pago'])) : '—',
            ];
            foreach ($filaDatos as $etiqueta => $valor) {
                $sheet->setCellValue('A' . $filaInfoEmp, $etiqueta);
                $sheet->getStyle('A' . $filaInfoEmp)->getFont()->setBold(true);
                $sheet->setCellValueExplicit('B' . $filaInfoEmp, $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $filaInfoEmp++;
            }

            $headerRow = $filaInfoEmp + 1;
            $headers = ['Concepto', 'Ingreso', 'Egreso'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $headerRow, $h);
                $col++;
            }
            $sheet->getStyle('A' . $headerRow . ':C' . $headerRow)->applyFromArray($headerStyle);

            $rubros = $lin['rubros'] ?? [];
            $row = $headerRow + 1;
            foreach ($rubros as $r) {
                $sheet->setCellValueExplicit('A' . $row, (string) ($r['concepto'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                if (($r['tipo'] ?? '') === 'ingreso') {
                    $sheet->setCellValue('B' . $row, (float) ($r['valor'] ?? 0));
                } else {
                    $sheet->setCellValue('C' . $row, (float) ($r['valor'] ?? 0));
                }
                $row++;
            }
            if ($row > $headerRow + 1) {
                $sheet->getStyle('B' . ($headerRow + 1) . ':C' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $sheet->setCellValue('A' . $row, 'TOTALES');
            $sheet->setCellValue('B' . $row, (float) ($lin['total_ingresos'] ?? 0));
            $sheet->setCellValue('C' . $row, (float) ($lin['total_egresos'] ?? 0));
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F3F5']]]);
            $sheet->getStyle('B' . $row . ':C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row += 2;

            $sheet->setCellValue('A' . $row, 'NETO A RECIBIR');
            $sheet->setCellValue('B' . $row, (float) ($lin['neto'] ?? 0));
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            foreach (['A', 'B', 'C'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            // Hoja 2 (Novedades) y Hoja 3 (Otros Detalles): mismo criterio que excelGeneral().
            $novedades = [];
            $otros = [];
            foreach ($rubros as $r) {
                $tipoRubro = ($r['tipo'] ?? '') === 'ingreso' ? 'Ingreso' : 'Egreso';
                if (($r['origen'] ?? '') === 'novedad') {
                    $novedades[] = [$r['concepto'] ?? '', $tipoRubro, (float) ($r['valor'] ?? 0)];
                } elseif (($r['origen'] ?? '') !== 'sueldo') {
                    $otros[] = [$r['concepto'] ?? '', (string) ($r['origen'] ?? ''), $tipoRubro, (float) ($r['valor'] ?? 0)];
                }
            }
            $reportSvc = new \App\Services\ReportService();
            $reportSvc->agregarHoja($spreadsheet, ['Concepto', 'Tipo', 'Valor'], $novedades, 'Novedades');
            $reportSvc->agregarHoja($spreadsheet, ['Concepto', 'Origen', 'Tipo', 'Valor'], $otros, 'Otros Detalles');

            // Hoja 4: Provisiones (beneficios sociales que la empresa acumula este mes).
            // Solo se listan las que realmente se provisionaron (mismo criterio que la
            // pestaña "Provisiones" del modal): sin valor o si el rol no es mensual, un
            // único mensaje en vez de una tabla vacía.
            $provFilas = [];
            foreach (($lin['provisiones'] ?? []) as $p) {
                if (!empty($p['incluir']) && (float) ($p['valor'] ?? 0) > 0) {
                    $provFilas[] = [(string) ($p['concepto'] ?? ''), (float) $p['valor']];
                }
            }
            if (empty($provFilas)) {
                $msg = (($cab['tipo_rol'] ?? '') === 'MENSUAL')
                    ? 'No hay provisiones para este empleado este mes.'
                    : 'Las provisiones solo aplican al rol mensual.';
                $provFilas[] = [$msg, ''];
            }
            $reportSvc->agregarHoja($spreadsheet, ['Concepto', 'Valor Mensual'], $provFilas, 'Provisiones');

            // Hoja 5: Asiento contable (cuentas reales resueltas: override por empleado > general).
            $asiento = $lin['asiento'] ?? null;
            $asientoFilas = [];
            if ($asiento) {
                foreach (($asiento['debe'] ?? []) as $x) {
                    $asientoFilas[] = [(string) ($x['cuenta_codigo'] ?? ''), (string) ($x['cuenta_nombre'] ?? ''), (string) ($x['concepto'] ?? ''), (float) $x['valor'], 0];
                }
                foreach (($asiento['haber'] ?? []) as $x) {
                    $asientoFilas[] = [(string) ($x['cuenta_codigo'] ?? ''), (string) ($x['cuenta_nombre'] ?? ''), (string) ($x['concepto'] ?? ''), 0, (float) $x['valor']];
                }
                $asientoFilas[] = ['', '', 'TOTALES', (float) ($asiento['total_debe'] ?? 0), (float) ($asiento['total_haber'] ?? 0)];
            } else {
                $asientoFilas[] = ['El asiento contable solo aplica al rol mensual.', '', '', '', ''];
            }
            $reportSvc->agregarHoja($spreadsheet, ['Cuenta', 'Nombre', 'Concepto', 'Debe', 'Haber'], $asientoFilas, 'Asiento Contable');

            // Hoja 6: Asistencia (jornadas del mes del rol: faltas, atrasos, horas extra).
            $asistFilas = [];
            $cap = fn($s) => $s !== '' ? mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1) : '';
            foreach (($lin['asistencia']['dias'] ?? []) as $x) {
                $fecha = substr((string) ($x['fecha'] ?? ''), 0, 10);
                $asistFilas[] = [
                    $fecha !== '' ? date('d-m-Y', strtotime($fecha)) : '',
                    $x['primera_entrada'] ? substr((string) $x['primera_entrada'], 11, 5) : '—',
                    $x['ultima_salida'] ? substr((string) $x['ultima_salida'], 11, 5) : '—',
                    (float) ($x['horas_trabajadas'] ?? 0),
                    (int) ($x['atraso_min'] ?? 0),
                    (int) ($x['extra_min'] ?? 0),
                    $cap((string) ($x['estado'] ?? '')),
                ];
            }
            if (empty($asistFilas)) {
                $asistFilas[] = ['Sin marcaciones/jornadas registradas para este empleado en el período.', '', '', '', '', '', ''];
            }
            $reportSvc->agregarHoja($spreadsheet, ['Fecha', 'Entrada', 'Salida', 'Horas Trabajadas', 'Atraso (min)', 'Extra (min)', 'Estado'], $asistFilas, 'Asistencia');

            $nombreArch = 'Rol_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) ($lin['identificacion'] ?? 'empleado'));
            $reportSvc->descargarSpreadsheet($spreadsheet, $nombreArch);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /** Envía por correo el rol individual del empleado. */
    public function enviarCorreoEmpleado(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idDetalle = (int) ($_POST['det'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $lin = $this->service->getLineaEmpleado($idDetalle, $idEmpresa, (int) $_SESSION['id_usuario']);
            if (!$lin) throw new \Exception('Línea no encontrada.');

            $email = trim((string) ($lin['email'] ?? ''));
            if ($email === '') throw new \Exception('El empleado no tiene correo registrado en su ficha.');

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            $pdfString = (new RolPagoPdfService())->generarEmpleado($lin, $empresa, 'S');

            $cab = $lin['cabecera'] ?? [];
            $mes = CatalogoNovedades::MESES[(int) ($cab['periodo_mes'] ?? 0)] ?? '';
            $periodo = trim($mes . ' ' . ($cab['periodo_anio'] ?? ''));
            $empNom = (string) ($empresa['razon_social'] ?? $empresa['nombre_comercial'] ?? '');
            $asunto = 'Rol de Pago - ' . $periodo;
            $cuerpo = '<p>Estimado(a) ' . htmlspecialchars((string) $lin['nombres_apellidos']) . ',</p>'
                . '<p>Adjunto encontrará su rol de pago correspondiente a <b>' . htmlspecialchars($periodo) . '</b>.</p>'
                . '<p>Neto a recibir: <b>$' . number_format((float) $lin['neto'], 2) . '</b></p>'
                . '<p>' . htmlspecialchars($empNom) . '</p>';
            $baseName = 'Rol_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) $lin['identificacion']);

            $ok = (new EnvioDocumentosSRIService())->enviarPdfSimple($idEmpresa, $email, (string) $lin['nombres_apellidos'], $asunto, $cuerpo, $pdfString, $baseName, $empNom);
            if ($ok) {
                echo json_encode(['ok' => true, 'msg' => 'Correo enviado a ' . $email]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo. Verifique la configuración de correo de la empresa.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    private function recogerCabecera(): array
    {
        $tipo = trim($_POST['tipo_rol'] ?? 'MENSUAL');
        $anio = (int) ($_POST['periodo_anio'] ?? 0);
        $mes  = (int) ($_POST['periodo_mes'] ?? 0);

        // El usuario solo elige tipo/mes/año; el resto se completa automáticamente.
        $numero = (int) ($_POST['numero_periodo'] ?? 0);
        if ($tipo !== 'MENSUAL' && $numero < 1) $numero = 1;

        $mesNombre = CatalogoNovedades::MESES[$mes] ?? (string) $mes;
        $descripcion = trim($_POST['descripcion'] ?? '');
        if ($descripcion === '') {
            $descripcion = CatalogoRol::nombreTipo($tipo) . ' - ' . $mesNombre . ' ' . $anio;
        }

        return [
            'tipo_rol'       => $tipo,
            'periodo_anio'   => $anio,
            'periodo_mes'    => $mes,
            'numero_periodo' => $numero,
            'fecha_desde'    => trim($_POST['fecha_desde'] ?? ''),
            'fecha_hasta'    => trim($_POST['fecha_hasta'] ?? ''),
            'fecha_pago'     => trim($_POST['fecha_pago'] ?? ''),
            'descripcion'    => $descripcion,
        ];
    }
}
