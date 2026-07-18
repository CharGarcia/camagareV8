<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\TransferenciaLoteService;
use App\repositories\modulos\FormaPagoRepository;

/**
 * Módulo Cargar Transferencias (modulos/transferencias).
 * Arma lotes de pagos (proveedores y/o nómina) ya registrados en Egresos con
 * tipo_operacion_bancaria='TRANSFERENCIA', los aprueba y genera el archivo
 * (Excel/TXT) para subir al banco. No genera egresos nuevos.
 */
class TransferenciasController extends BaseModuloController
{
    private TransferenciaLoteService $service;
    private const RUTA_MODULO = 'modulos/transferencias';

    public function __construct()
    {
        parent::__construct();
        $this->service = new TransferenciaLoteService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) ($_SESSION['id_usuario'] ?? 0) : null;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? 'numero');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage  = 20;

        $res   = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $this->idUsuarioFiltro());
        $total = $res['total'];

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $esAprobador = $this->service->esAprobador($idUsuario, $idEmpresa, $nivel);

        $formaPagoRepo = new FormaPagoRepository();
        // Solo cuentas bancarias de la empresa (tipo='BANCO'): una transferencia
        // no puede salir de efectivo, tarjeta o un anticipo.
        $formasBancoOrigen = array_values(array_filter(
            $formaPagoRepo->getFormasFiltradas($idEmpresa, 'EGRESO'),
            static fn($fp) => ($fp['tipo'] ?? '') === 'BANCO'
        ));

        $this->viewWithLayout('layouts.main', 'modulos.transferencias.index', [
            'titulo'       => 'Carga de Pagos al Banco',
            'perm'         => $this->getPermisos(),
            'rows'         => $res['rows'],
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            'buscar'       => $buscar,
            'ordenCol'     => $ordenCol,
            'ordenDir'     => $ordenDir,
            'esAprobador'  => $esAprobador,
            'esSuperAdmin' => $nivel >= 3,
            'idUsuarioActual'    => $idUsuario,
            'aprobadoresNombres' => $this->service->getAprobadoresNombres($idEmpresa),
            'formasPagoOrigen'   => $formasBancoOrigen,
            'bancosDisponibles'  => $formaPagoRepo->getBancosDisponibles(),
            'rutaModulo'   => self::RUTA_MODULO,
            'fullWidth'    => true,
        ]);
    }

    /**
     * Config de aprobación vigente (se consulta cada vez que se abre el modal,
     * en lugar de confiar en los valores cargados al momento del index()).
     */
    public function getConfigAprobacionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        $cfg = $this->service->getConfigAprobacion($idEmpresa);
        echo json_encode([
            'ok' => true,
            'data' => [
                'requiere'     => $cfg['requiere'],
                'esAprobador'  => $this->service->esAprobador($idUsuario, $idEmpresa, $nivel),
                'aprobadores'  => $this->service->getAprobadoresNombres($idEmpresa),
            ],
        ]);
    }

    /** Pagos de egresos (proveedores/nómina) disponibles para armar un lote nuevo. */
    public function getPagosDisponiblesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $tipo   = strtoupper(trim($_GET['tipo'] ?? 'AMBOS'));
        $buscar = trim($_GET['b'] ?? '');
        if (!in_array($tipo, ['PROVEEDORES', 'NOMINA', 'AMBOS'], true)) $tipo = 'AMBOS';

        $data = $this->service->getPagosDisponibles($idEmpresa, $tipo, $buscar);
        echo json_encode(['ok' => true, 'data' => $data]);
    }

    public function crearAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            $idLote = $this->service->crearLote($idEmpresa, $idUsuario, [
                'tipo_lote'            => strtoupper(trim($_POST['tipo_lote'] ?? '')),
                'id_forma_pago_origen' => (int) ($_POST['id_forma_pago_origen'] ?? 0),
                'id_banco_formato'     => (int) ($_POST['id_banco_formato'] ?? 0),
                'fecha_pago'           => trim($_POST['fecha_pago'] ?? ''),
                'observaciones'        => trim($_POST['observaciones'] ?? ''),
            ]);
            echo json_encode(['ok' => true, 'data' => ['id' => $idLote]]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function actualizarCabeceraAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $this->service->actualizarCabecera($id, $idEmpresa, $idUsuario, [
                'tipo_lote'            => strtoupper(trim($_POST['tipo_lote'] ?? '')),
                'id_forma_pago_origen' => (int) ($_POST['id_forma_pago_origen'] ?? 0),
                'id_banco_formato'     => (int) ($_POST['id_banco_formato'] ?? 0),
                'fecha_pago'           => trim($_POST['fecha_pago'] ?? ''),
                'observaciones'        => trim($_POST['observaciones'] ?? ''),
            ]);
            echo json_encode(['ok' => true, 'mensaje' => 'Lote actualizado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function agregarLineasAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idLote = (int) ($_POST['id_lote'] ?? 0);
        $ids = array_map('intval', (array) ($_POST['ids_egreso_pago'] ?? []));

        try {
            $data = $this->service->agregarLineas($idLote, $idEmpresa, $idUsuario, $ids);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function quitarLineaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idLote    = (int) ($_POST['id_lote'] ?? 0);
        $idDetalle = (int) ($_POST['id_detalle'] ?? 0);

        try {
            $data = $this->service->quitarLinea($idLote, $idDetalle, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
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
            echo json_encode(['ok' => false, 'mensaje' => 'Lote no encontrado.']);
        }
    }

    public function enviarAprobacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $res = $this->service->enviarAprobacion($id, $idEmpresa, $idUsuario);
            $msg = $res['estado'] === 'APROBADO' ? 'Lote auto-aprobado (la empresa no exige aprobación).' : 'Lote enviado a aprobación.';
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => $msg]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
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
            echo json_encode(['ok' => false, 'mensaje' => 'No está autorizado para aprobar lotes de pago bancario.']);
            return;
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $res = $this->service->aprobar($id, $idEmpresa, $idUsuario, false, $nivel);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Lote aprobado.']);
        } catch (\Throwable $e) {
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
            echo json_encode(['ok' => false, 'mensaje' => 'No está autorizado para rechazar lotes de pago bancario.']);
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
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Lote rechazado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function generarArchivoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $res = $this->service->generarArchivo($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Archivo generado. Ya puede descargarlo.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function descargarArchivo(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        $ruta = $this->service->getRutaArchivo($id, $idEmpresa);
        if (!$ruta) {
            http_response_code(404);
            echo 'Archivo no encontrado. Genere el archivo primero.';
            return;
        }

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    public function confirmarEnvioAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $res = $this->service->confirmarEnvio($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Envío confirmado. Los pagos incluidos ya no se podrán volver a transferir.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function anularAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            echo json_encode(['ok' => false, 'mensaje' => 'Indique el motivo de la anulación.']);
            return;
        }

        try {
            $res = $this->service->anular($id, $idEmpresa, $idUsuario, $motivo);
            echo json_encode(['ok' => true, 'data' => $res, 'mensaje' => 'Lote anulado. Los pagos quedaron disponibles nuevamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
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
            echo json_encode(['ok' => true, 'mensaje' => 'Lote eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Filas del listado sin paginar (para exportar), respetando búsqueda y registros propios. */
    private function filasParaExportar(): array
    {
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'numero');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $res = $this->service->getListado($idEmpresa, $buscar, 1, 10000, $ordenCol, $ordenDir, $this->idUsuarioFiltro());
        return $res['rows'];
    }

    private function etiquetaEstado(string $estado): string
    {
        return [
            'BORRADOR' => 'Borrador', 'PENDIENTE_APROBACION' => 'Pendiente de aprobación',
            'APROBADO' => 'Aprobado', 'RECHAZADO' => 'Rechazado', 'GENERADO' => 'Generado',
            'CONFIRMADO' => 'Confirmado', 'ANULADO' => 'Anulado',
        ][$estado] ?? $estado;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExportar();
        $empresa = (new \App\models\Empresa())->getPorId((int) ($_SESSION['id_empresa'] ?? 0)) ?? [];
        $nombreEmpresa = $empresa['nombre'] ?? 'Carga de Pagos al Banco';

        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $filas = '';
        foreach ($rows as $r) {
            $filas .= '<tr>'
                . '<td>#' . (int) $r['numero'] . '</td>'
                . '<td>' . ($r['fecha_pago'] ? date('d-m-Y', strtotime($r['fecha_pago'])) : '') . '</td>'
                . '<td>' . $e(ucfirst(strtolower((string) $r['tipo_lote']))) . '</td>'
                . '<td align="right">$ ' . number_format((float) $r['monto_total'], 2) . '</td>'
                . '<td align="center">' . (int) $r['cantidad_pagos'] . '</td>'
                . '<td align="center">' . $e($this->etiquetaEstado($r['estado'] ?? '')) . '</td>'
                . '<td>' . $e($r['creado_por_nombre'] ?? '') . '</td>'
                . '</tr>';
        }
        if ($filas === '') {
            $filas = '<tr><td colspan="7" align="center">Sin registros</td></tr>';
        }

        $html = '
            <div style="text-align:center;">
                <h2>' . $e($nombreEmpresa) . '</h2>
                <h3>Carga de Pagos al Banco</h3>
                <p style="font-size:9px;">Fecha de reporte: ' . date('d-m-Y H:i:s') . '</p>
            </div>
            <table border="1" cellpadding="4" cellspacing="0" style="font-size:8px;">
                <thead>
                    <tr style="background-color:#eef2f7;font-weight:bold;">
                        <th>N°</th><th>Fecha pago</th><th>Tipo</th><th>Monto total</th><th>Pagos</th><th>Estado</th><th>Creado por</th>
                    </tr>
                </thead>
                <tbody>' . $filas . '</tbody>
            </table>';

        try {
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetMargins(12, 12, 12);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('Carga_Pagos_Banco_' . date('Ymd') . '.pdf', 'I');
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

        $headers = ['N°', 'Fecha pago', 'Tipo', 'Monto total', 'Pagos', 'Estado', 'Creado por'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (int) $r['numero'],
                $r['fecha_pago'] ? date('d-m-Y', strtotime($r['fecha_pago'])) : '',
                ucfirst(strtolower((string) $r['tipo_lote'])),
                (float) $r['monto_total'],
                (int) $r['cantidad_pagos'],
                $this->etiquetaEstado($r['estado'] ?? ''),
                (string) ($r['creado_por_nombre'] ?? ''),
            ];
        }

        try {
            (new \App\Services\ReportService())->exportToExcel('Carga de Pagos al Banco', $headers, $data, 'Pagos Banco', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }
}
