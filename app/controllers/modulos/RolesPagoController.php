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
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
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

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
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
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id = (int) ($_GET['id'] ?? 0);
        $data = $this->service->getDetalle($id, (int) $_SESSION['id_empresa']);
        echo json_encode($data ? ['ok' => true, 'data' => $data] : ['ok' => false, 'error' => 'No encontrado']);
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
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** PDF del rol general (planilla). */
    public function pdf(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $rol = $this->service->getDetalle($id, $idEmpresa);
            if (!$rol) { http_response_code(404); echo 'Corrida no encontrada'; exit; }

            $mes = CatalogoNovedades::MESES[(int) $rol['periodo_mes']] ?? $rol['periodo_mes'];
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(8, 8, 8);
            $pdf->AddPage();

            $html = '<h3>' . htmlspecialchars(CatalogoRol::nombreTipo((string) $rol['tipo_rol'])) . ' — ' . htmlspecialchars($mes . ' ' . $rol['periodo_anio']) . '</h3>';
            $html .= '<table border="0.5" cellpadding="3"><tr style="background-color:#e9ecef;font-weight:bold;font-size:8px;">'
                . '<th width="34%">Empleado</th><th width="13%">Identificación</th>'
                . '<th width="13%" align="right">Ingresos</th><th width="13%" align="right">Egresos</th>'
                . '<th width="13%" align="right">IESS</th><th width="14%" align="right">Neto</th></tr>';
            foreach ($rol['detalle'] as $d) {
                $html .= '<tr style="font-size:8px;">'
                    . '<td width="34%">' . htmlspecialchars((string) $d['nombres_apellidos']) . '</td>'
                    . '<td width="13%">' . htmlspecialchars((string) $d['identificacion']) . '</td>'
                    . '<td width="13%" align="right">' . number_format((float) $d['total_ingresos'], 2) . '</td>'
                    . '<td width="13%" align="right">' . number_format((float) $d['total_egresos'], 2) . '</td>'
                    . '<td width="13%" align="right">' . number_format((float) $d['aporte_iess'], 2) . '</td>'
                    . '<td width="14%" align="right"><b>' . number_format((float) $d['neto'], 2) . '</b></td></tr>';
            }
            $html .= '<tr style="font-size:8.5px;font-weight:bold;background-color:#f1f3f5;">'
                . '<td width="47%" colspan="2">TOTALES</td>'
                . '<td width="13%" align="right">' . number_format((float) $rol['total_ingresos'], 2) . '</td>'
                . '<td width="13%" align="right">' . number_format((float) $rol['total_egresos'], 2) . '</td>'
                . '<td width="13%" align="right"></td>'
                . '<td width="14%" align="right">' . number_format((float) $rol['total_neto'], 2) . '</td></tr>';
            $html .= '</table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('Rol_' . $id . '.pdf', 'I');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Detalle de un empleado del rol (general + provisiones + asiento). */
    public function getEmpleadoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idDetalle = (int) ($_GET['det'] ?? 0);
        $data = $this->service->getEmpleadoCompleto($idDetalle, (int) $_SESSION['id_empresa']);
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
            $lin = $this->service->getLineaEmpleado($idDetalle, $idEmpresa);
            if (!$lin) { http_response_code(404); echo 'Línea no encontrada'; exit; }
            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            (new RolPagoPdfService())->generarEmpleado($lin, $empresa, $dest);
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
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
            $lin = $this->service->getLineaEmpleado($idDetalle, $idEmpresa);
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
