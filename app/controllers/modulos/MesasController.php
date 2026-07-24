<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CajaSesionRepository;
use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Rules\modulos\ComandaRules;
use App\Rules\modulos\MesaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CajaSesionService;
use App\Services\modulos\ComandaService;
use App\Services\modulos\MesaService;
use App\Services\modulos\PosVentaService;

class MesasController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/mesas';
    private MesaService $service;
    private ComandaService $comandaService;
    private CajaSesionService $cajaService;

    public function __construct()
    {
        parent::__construct();
        $repo = new MesaRepository();
        $rules = new MesaRules();
        $logService = new LogSistemaService();
        $this->service = new MesaService($repo, $rules, $logService);
        $this->cajaService = new CajaSesionService(new CajaSesionRepository(), new CajaSesionRules(), $logService);
        $ventaService = new PosVentaService($this->cajaService, $logService);
        $this->comandaService = new ComandaService(new ComandaRepository(), new ComandaRules(), $repo, $logService, $ventaService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /**
     * Tablero operativo del modo restaurante (modulos/mesas/tablero): grid de
     * mesas con su comanda abierta, si tiene. Requiere el mismo turno de caja
     * que el mostrador (modulos/caja-pos) — el salón no vende sin turno
     * abierto, y abrir/cerrar turno sigue viviendo solo en Cajas para no
     * duplicar esa pantalla aquí.
     */
    public function tablero(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idPuntoEmision = (int) ($_SESSION['pos_id_punto_emision'] ?? 0);
        $sesion = $idPuntoEmision > 0 ? $this->cajaService->getSesionAbierta($idEmpresa, $idPuntoEmision) : null;

        if (!$sesion) {
            $this->view('modulos.caja_sesion.venta_placeholder', [
                'titulo'         => 'Punto de Venta — Restaurante',
                // Con ?volver=mesas: al abrir el turno en Cajas, "Continuar al
                // Punto de Venta" (standalone.php) regresa aquí (mesas/tablero)
                // en vez de caer al mostrador — cierra el círculo Punto de
                // Venta → POS Restaurante. Ver CajaPosController::index().
                'rutaModulo'     => 'modulos/caja-pos?volver=mesas',
                'idPuntoEmision' => $idPuntoEmision,
            ]);
            return;
        }

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];

        $this->view('modulos.mesas.tablero', [
            'titulo'         => 'Mesas',
            'rutaModulo'     => self::RUTA_MODULO,
            'perm'           => $this->getPermisos(),
            'idPuntoEmision' => $idPuntoEmision,
            'sesion'         => $sesion,
            'mesas'          => $this->comandaService->getTablero($idEmpresa),
            'empresaNombre'  => $empresa['nombre_comercial'] ?? $empresa['nombre'] ?? '',
        ]);
    }

    public function tableroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->json(['ok' => true, 'data' => $this->comandaService->getTablero($idEmpresa)]);
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
        }
        unset($r);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.mesas.index', [
            'titulo'     => 'Mesas',
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

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-grid-3x3 fs-3 d-block mb-2"></i>No se encontraron mesas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));

                $estadoBadge = ($r['estado'] === 'disponible')
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Disponible</span>'
                    : '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Ocupada</span>';

                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="mesa-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalMesaEditar(this)">
                        <td class="ps-3 fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td class="text-center" data-col="ubicacion">' . htmlspecialchars($r['ubicacion'] ?? '') . '</td>
                        <td class="text-center" data-col="estado">' . $estadoBadge . '</td>
                        <td class="text-center" data-col="created_at">' . htmlspecialchars($r['created_at'] ?? '') . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Mesa creada correctamente.', 'id' => $id]);
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

        $data = $this->recogerDatosFormulario();
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID de la mesa no es válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Mesa actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID de la mesa no es válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Mesa eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        $permiteFactura = isset($_POST['permite_factura']);
        $permiteRecibo  = isset($_POST['permite_recibo']);
        if (!$permiteFactura && !$permiteRecibo) {
            $permiteFactura = true; // nunca dejar la mesa sin ningún documento habilitado
        }
        return [
            'nombre'          => trim($_POST['nombre'] ?? ''),
            'estado'          => trim($_POST['estado'] ?? 'disponible'),
            'ubicacion'       => trim($_POST['ubicacion'] ?? ''),
            'permite_factura' => $permiteFactura,
            'permite_recibo'  => $permiteRecibo,
        ];
    }

    /** QR de la mesa (portal público de pedido) — lo genera la primera vez que se pide. */
    public function getQrAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_POST['id_mesa'] ?? $_GET['id_mesa'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('Mesa no válida.');
            $token = $this->service->getOrCrearQrToken($id, $idEmpresa);
            $url = $this->urlPublicaQr($token);
            echo json_encode(['ok' => true, 'token' => $token, 'url' => $url]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Invalida el QR impreso anterior (se filtró, hay que reimprimirlo, etc.) y genera uno nuevo. */
    public function regenerarQrAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_mesa'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('Mesa no válida.');
            $token = $this->service->regenerarQrToken($id, $idEmpresa, $idUsuario);
            $url = $this->urlPublicaQr($token);
            echo json_encode(['ok' => true, 'msg' => 'QR regenerado; el anterior ya no funciona.', 'token' => $token, 'url' => $url]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * URL pública completa del portal de pedido. Un QR necesita una URL
     * absoluta (con dominio) para poder escanearse desde cualquier celular —
     * usa APP_URL (dominio real de producción) si está configurado; si no
     * (entorno local sin config/local.php app_url), arma el absoluto con el
     * host de la petición actual, para poder probar el QR igual en local.
     */
    private function urlPublicaQr(string $token): string
    {
        $dominio = defined('APP_URL') && APP_URL !== '' ? rtrim(APP_URL, '/') : '';
        if ($dominio === '') {
            $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dominio = $esquema . '://' . $host;
        }
        $base = $dominio . rtrim(BASE_URL ?? '', '/');
        return $base . '/pedido/' . $token;
    }

    /** Arrastrar y soltar en el tablero (modulos/mesas/tablero): guarda la nueva posición. */
    public function actualizarPosicionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_POST['id'] ?? 0);
        $posX = (float) ($_POST['pos_x'] ?? -1);
        $posY = (float) ($_POST['pos_y'] ?? -1);

        try {
            if ($id <= 0) throw new \Exception('Mesa no válida.');
            $this->service->actualizarPosicion($id, $idEmpresa, $posX, $posY);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'desc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE MESAS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                    font-size: 8pt;
                    table-layout: fixed;
                }

                th {
                    background: #f2f2f2;
                    border: 1px solid #ccc;
                    padding: 4px;
                    text-align: left;
                }

                td {
                    border: 1px solid #ccc;
                    padding: 4px;
                    overflow: hidden;
                    word-wrap: break-word;
                }

                .header {
                    text-align: center;
                    margin-bottom: 15px;
                    width: 100%;
                }

                h1 {
                    margin: 0;
                    font-size: 14pt;
                    color: #333;
                }

                h2 {
                    margin: 3px 0 0 0;
                    color: #666;
                    font-size: 10pt;
                    text-transform: uppercase;
                }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Mesas</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45%">Nombre</th>
                            <th style="width: 30%">Ubicación</th>
                            <th style="width: 25%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['ubicacion'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Mesas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'desc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel = new \App\models\Empresa();
            $empresa = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Nombre', 'Ubicación', 'Estado', 'Fecha Registro'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string)($r['nombre'] ?? ''),
                    (string)($r['ubicacion'] ?? ''),
                    (string)($r['estado'] ?? ''),
                    (string)($r['created_at'] ?? '')
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Mesas', $headers, $exportData, 'Listado de Mesas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }
}
