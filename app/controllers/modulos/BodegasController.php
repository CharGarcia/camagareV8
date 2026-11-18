<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\BodegaRepository;
use App\Rules\modulos\BodegaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\BodegaService;

class BodegasController extends BaseModuloController
{
    private BodegaService $service;
    private const RUTA_MODULO = 'modulos/bodegas';

    public function __construct()
    {
        parent::__construct();
        $repository = new BodegaRepository();
        $rules = new BodegaRules();
        $logService = new LogSistemaService();
        $this->service = new BodegaService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /** Columnas del listado (dropdown de columnas, PDF y Excel). */
    private const COLUMNAS = [
        'nombre'          => 'Nombre',
        'establecimiento' => 'Establecimiento',
        'productos'       => 'Productos',
        'unidades'        => 'Unidades',
        'valor_costo'     => 'Saldo al costo',
        'valor_venta'     => 'Saldo a precio de venta',
        'status'          => 'Estado',
    ];

    /** Lee búsqueda, página y orden (una o varias columnas) de la petición. */
    private function leerParametrosListado(): array
    {
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = \App\Helpers\OrdenListado::leer($prefsVista, 'nombre');
        return [
            'prefsVista' => $prefsVista,
            'buscar'     => trim((string) ($_GET['b'] ?? $_POST['b'] ?? '')),
            'page'       => max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1)),
            'orden'      => $orden,
            'ordenCol'   => \App\Helpers\OrdenListado::primeraCol($orden, 'nombre'),
            'ordenDir'   => \App\Helpers\OrdenListado::primeraDir($orden),
        ];
    }

    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $p = $this->leerParametrosListado();
        $perPage = $this->porPagina();

        $result = $this->service->getListado($idEmpresa, $p['buscar'], $p['page'], $perPage, $p['ordenCol'], $p['ordenDir'], $this->idUsuarioFiltro(), $p['orden']);
        $total = $result['total'];
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

        // Establecimientos de la empresa: cada bodega puede indicar en cuál está
        // (lo consumen las Transferencias de Inventario).
        $establecimientos = (new \App\models\Empresa())->getEstablecimientos($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.bodegas.index', [
            'titulo'           => 'Bodegas',
            'establecimientos' => $establecimientos,
            'fullWidth'        => true,
            'perm'             => $perm,
            'rutaModulo'       => self::RUTA_MODULO,
            'rowsHtml'         => $this->renderFilas($result['rows']),
            'pieHtml'          => $this->renderPie($result['totales'], $total),
            'total'            => $total,
            'page'             => $p['page'],
            'totalPages'       => $totalPages,
            'perPage'          => $perPage,
            'buscar'           => $p['buscar'],
            'ordenCol'         => $p['ordenCol'],
            'ordenDir'         => $p['ordenDir'],
            'ordenJson'        => \App\Helpers\OrdenListado::aJson($p['orden']),
            'ordenParam'       => \App\Helpers\OrdenListado::aCadena($p['orden']),
            'columnasTabla'    => self::COLUMNAS,
            'opcionesFiltro'   => $this->service->getOpcionesFiltroListado($idEmpresa),
            'vistaConfig'      => $p['prefsVista'],
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $p = $this->leerParametrosListado();
        $perPage = $this->porPagina();

        try {
            $result = $this->service->getListado($idEmpresa, $p['buscar'], $p['page'], $perPage, $p['ordenCol'], $p['ordenDir'], $this->idUsuarioFiltro(), $p['orden']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => 'No se pudo cargar el listado de bodegas.']);
            exit;
        }
        $total = $result['total'];
        $page = $p['page'];
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
                        . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';

        $qs = 'b=' . urlencode($p['buscar']) . '&orden=' . urlencode(\App\Helpers\OrdenListado::aCadena($p['orden']));
        echo json_encode([
            'ok'         => true,
            'rows'       => $this->renderFilas($result['rows']),
            'pie'        => $this->renderPie($result['totales'], $total),
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?' . $qs,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?' . $qs,
        ]);
        exit;
    }

    private static function num(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }

    private static function esActiva(array $r): bool
    {
        $s = $r['status'] ?? true;
        return !($s === false || $s === 0 || $s === '0' || $s === 'f' || $s === 'false');
    }

    /** Filas del listado (las usan index() y searchAjax(), así no se pintan dos veces distinto). */
    private function renderFilas(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>No se encontraron bodegas.</td></tr>';
        }
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $neg = fn($v) => (float) $v < 0 ? ' text-danger' : '';
        $html = '';
        foreach ($rows as $r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));

            $statusBadge = self::esActiva($r)
                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>';

            $est = self::valorCelda($r, 'establecimiento');

            $html .= '<tr class="bodega-row" role="button" tabindex="0" data-row="' . $h(json_encode($r)) . '" onclick="abrirModalBodegaEditar(this)">'
                . '<td class="fw-medium ps-3" data-col="nombre">' . $h($r['nombre'] ?? '') . '</td>'
                . '<td data-col="establecimiento" class="text-truncate" style="max-width:240px">' . ($est !== 'Sin asignar' ? $h($est) : '<span class="text-muted">Sin asignar</span>') . '</td>'
                . '<td class="text-end" data-col="productos">' . number_format((int) $r['productos']) . '</td>'
                . '<td class="text-end' . $neg($r['unidades']) . '" data-col="unidades">' . self::num((float) $r['unidades']) . '</td>'
                . '<td class="text-end' . $neg($r['valor_costo']) . '" data-col="valor_costo">' . self::num((float) $r['valor_costo']) . '</td>'
                . '<td class="text-end' . $neg($r['valor_venta']) . '" data-col="valor_venta">' . self::num((float) $r['valor_venta']) . '</td>'
                . '<td class="text-center pe-3" data-col="status">' . $statusBadge . '</td>'
                . '</tr>';
        }
        return $html;
    }

    /** Fila de totales (de todas las bodegas filtradas, no solo de la página). */
    private function renderPie(array $t, int $total): string
    {
        if ($total <= 0) return '';
        return '<tr class="fw-bold">'
            . '<td class="ps-3" data-col="nombre">Total (' . $total . ' ' . ($total === 1 ? 'bodega' : 'bodegas') . ')</td>'
            . '<td data-col="establecimiento"></td>'
            . '<td data-col="productos"></td>'
            . '<td class="text-end" data-col="unidades">' . self::num((float) $t['unidades']) . '</td>'
            . '<td class="text-end" data-col="valor_costo">' . self::num((float) $t['valor_costo']) . '</td>'
            . '<td class="text-end" data-col="valor_venta">' . self::num((float) $t['valor_venta']) . '</td>'
            . '<td data-col="status"></td>'
            . '</tr>';
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido');

            $repo = new BodegaRepository();
            $bodega = $repo->getDetalleCompleto($id, $idEmpresa);
            if (!$bodega) throw new \Exception('Bodega no encontrada');

            echo json_encode([
                'ok' => true,
                'data' => ['saldo' => $this->service->getSaldoBodega($id, $idEmpresa)],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Filas completas (sin paginar) para PDF y Excel, con el mismo filtro y orden de la pantalla. */
    private function datosExportacion(): array
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $p = $this->leerParametrosListado();
        $data = $this->service->getListado($idEmpresa, $p['buscar'], 1, 0, $p['ordenCol'], $p['ordenDir'], $this->idUsuarioFiltro(), $p['orden']);

        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Columnas visibles según las preferencias del usuario (las ocultas no se exportan).
        $ocultas = (array) ($p['prefsVista']['__columnas_ocultas__'] ?? []);
        $cols = array_filter(self::COLUMNAS, fn($k) => $k === 'nombre' || !in_array($k, $ocultas, true), ARRAY_FILTER_USE_KEY);

        return [$data, $empresa['nombre'] ?? '', $cols];
    }

    private static function valorCelda(array $r, string $col): string
    {
        return match ($col) {
            'nombre'          => (string) ($r['nombre'] ?? ''),
            'establecimiento' => trim(($r['establecimiento_codigo'] ?? '') . ' - ' . ($r['establecimiento_nombre'] ?? ''), ' -') ?: 'Sin asignar',
            'productos'       => (string) (int) ($r['productos'] ?? 0),
            'unidades', 'valor_costo', 'valor_venta' => self::num((float) ($r[$col] ?? 0)),
            'status'          => self::esActiva($r) ? 'Activo' : 'Inactivo',
            default           => '',
        };
    }

    public function exportPdf(): void
    {
        [$data, $nombreEmpresa, $cols] = $this->datosExportacion();
        $rows = $data['rows'];
        $t = $data['totales'];
        $numericas = ['productos', 'unidades', 'valor_costo', 'valor_venta'];
        $pesos = ['nombre' => 26, 'establecimiento' => 20, 'productos' => 9, 'unidades' => 11, 'valor_costo' => 12, 'valor_venta' => 12, 'status' => 10];
        $sumaPesos = array_sum(array_intersect_key($pesos, $cols));
        $ancho = fn($k) => round($pesos[$k] * 100 / max(1, $sumaPesos), 2) . '%';

        try {
            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; }
                .r { text-align: right; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Bodegas y saldos</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($cols as $k => $label): ?>
                                <th style="width: <?= $ancho($k) ?>" class="<?= in_array($k, $numericas, true) ? 'r' : '' ?>"><?= htmlspecialchars($label) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <?php foreach ($cols as $k => $label): ?>
                                    <td style="width: <?= $ancho($k) ?>" class="<?= in_array($k, $numericas, true) ? 'r' : '' ?>"><?= htmlspecialchars(self::valorCelda($r, $k)) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows): ?>
                            <tr>
                                <?php foreach ($cols as $k => $label): ?>
                                    <td style="width: <?= $ancho($k) ?>; font-weight: bold" class="<?= in_array($k, $numericas, true) ? 'r' : '' ?>"><?= $k === 'nombre' ? 'TOTAL' : (isset($t[$k]) ? self::num((float) $t[$k]) : '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf(count($cols) > 5 ? 'L' : 'P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Bodegas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    public function exportExcel(): void
    {
        [$data, $nombreEmpresa, $cols] = $this->datosExportacion();
        $t = $data['totales'];
        $numericas = ['productos', 'unidades', 'valor_costo', 'valor_venta'];

        try {
            $exportData = [];
            foreach ($data['rows'] as $r) {
                $fila = [];
                foreach (array_keys($cols) as $k) {
                    $fila[] = in_array($k, $numericas, true) ? round((float) ($r[$k] ?? 0), 2) : self::valorCelda($r, $k);
                }
                $exportData[] = $fila;
            }
            if ($exportData) {
                $fila = [];
                foreach (array_keys($cols) as $k) {
                    $fila[] = $k === 'nombre' ? 'TOTAL' : (isset($t[$k]) ? round((float) $t[$k], 2) : '');
                }
                $exportData[] = $fila;
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Bodegas', array_values($cols), $exportData, 'Bodegas y saldos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . htmlspecialchars($e->getMessage());
            exit;
        }
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
            echo json_encode(['ok' => true, 'msg' => 'Bodega creada correctamente.', 'id' => $id]);
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
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID de bodega no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Bodega actualizada correctamente.']);
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

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID de bodega no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Bodega eliminada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Obtiene los usuarios de la empresa y su estado de acceso a la bodega.
     */
    public function getUsuariosAccesoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idBodega = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($idBodega <= 0) throw new \Exception('ID de bodega no válido.');
            $usuarios = $this->service->getUsuariosAcceso($idBodega, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $usuarios]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Guarda la configuración de accesos de usuarios.
     */
    public function guardarAccesosAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idBodega = (int) ($_POST['id_bodega'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuarioLogueado = (int) $_SESSION['id_usuario'];
        
        // El frontend enviará un JSON en la variable 'accesos'
        $accesosJson = $_POST['accesos'] ?? '[]';
        $accesos = json_decode($accesosJson, true) ?: [];

        try {
            if ($idBodega <= 0) throw new \Exception('ID de bodega no válido.');
            $this->service->guardarAccesos($idBodega, $idEmpresa, $accesos, $idUsuarioLogueado);
            echo json_encode(['ok' => true, 'msg' => 'Accesos actualizados correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Obtiene las bodegas permitidas para el usuario logueado en la empresa actual.
     */
    public function getBodegasPermitidasAjax(): void
    {
        // No usamos requireLeer estricto aquí porque se puede llamar desde diversos módulos
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        try {
            if ($idEmpresa <= 0 || $idUsuario <= 0) throw new \Exception('Sesión no válida.');
            $bodegas = $this->service->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
            echo json_encode(['ok' => true, 'data' => $bodegas]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre'             => trim($_POST['nombre'] ?? ''),
            'status'             => (int) ($_POST['status'] ?? 1),
            // Establecimiento al que pertenece la bodega: lo usan las
            // Transferencias de Inventario para saber cuándo el traslado cruza
            // de un local a otro del mismo RUC. Vacío = sin asignar.
            'id_establecimiento' => (int) ($_POST['id_establecimiento'] ?? 0) ?: null,
        ];
    }
}
