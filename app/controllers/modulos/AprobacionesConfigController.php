<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\PreferenciasHelper;
use App\repositories\modulos\AprobacionesRepository;
use App\Services\modulos\AprobacionesService;

/**
 * Configuración centralizada del motor de Aprobaciones: por empresa, qué
 * procesos exigen aprobación y quiénes son los aprobadores.
 */
class AprobacionesConfigController extends BaseModuloController
{
    private AprobacionesService $service;
    private const RUTA_MODULO = 'modulos/aprobaciones-config';
    private const PER_PAGE    = 20;

    /** Nombre e ícono visibles por módulo dueño del checkpoint. */
    private const MODULOS_INFO = [
        'modulos/cargas-inventario' => ['Inventario', 'bi-box-seam',  'text-primary'],
        'modulos/importaciones'     => ['Inventario', 'bi-airplane',  'text-primary'],
        'modulos/transferencias'    => ['Tesorería',  'bi-bank',      'text-success'],
        'modulos/compras'           => ['Compras',    'bi-cart3',     'text-warning'],
        'modulos/roles-pago'        => ['Nómina',     'bi-people',    'text-info'],
        'modulos/factura-venta'     => ['Ventas',     'bi-receipt',   'text-danger'],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->service = new AprobacionesService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /** Nombre/ícono del módulo; si la ruta no está mapeada, se humaniza el slug. */
    public static function infoModulo(string $ruta): array
    {
        if (isset(self::MODULOS_INFO[$ruta])) {
            [$nombre, $icono, $color] = self::MODULOS_INFO[$ruta];
            return ['nombre' => $nombre, 'icono' => $icono, 'color' => $color];
        }
        $slug = preg_replace('#^modulos/#', '', $ruta);
        return [
            'nombre' => ucwords(str_replace(['-', '_'], ' ', $slug)),
            'icono'  => 'bi-diagram-3',
            'color'  => 'text-secondary',
        ];
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) ($_SESSION['id_empresa'] ?? 0);
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'modulo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));

        $result = $this->service->getListado($idEmpresa, $buscar, $page, self::PER_PAGE, $ordenCol, $ordenDir);
        $total  = $result['total'];

        $this->viewWithLayout('layouts.main', 'modulos.aprobaciones_config.index', [
            'titulo'      => 'Aprobaciones',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => (int) ceil($total / self::PER_PAGE) ?: 1,
            'perPage'     => self::PER_PAGE,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'disponibles' => $this->service->getTiposDisponibles($idEmpresa),
            'usuarios'    => $this->service->getUsuariosEmpresa($idEmpresa),
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) ($_SESSION['id_empresa'] ?? 0);
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_GET['q'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'modulo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, self::PER_PAGE, $ordenCol, $ordenDir);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = (int) ceil($total / self::PER_PAGE) ?: 1;

        $from = $total > 0 ? (($page - 1) * self::PER_PAGE) + 1 : 0;
        $to   = $total > 0 ? min($page * self::PER_PAGE, $total) : 0;

        $perm = $this->getPermisos();
        $puedeAbrir = !empty($perm['actualizar']) || !empty($perm['eliminar']);

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="5" class="text-center py-5 text-muted">'
                . '<i class="bi bi-check2-square fs-3 d-block mb-2"></i>No se encontraron aprobaciones.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo self::filaHtml($r, $puedeAbrir);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = $page <= 1 ? 'disabled' : '';
        $nextDisabled = $page >= $totalPages ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="APR_cambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="APR_cambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        $qs = '?b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'configuradas' => self::mapaConfiguradas($rows),
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf' . $qs,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel' . $qs,
        ]);
        exit;
    }

    /**
     * Crea o actualiza una aprobación. Es el mismo endpoint para ambos casos: el
     * UNIQUE(id_empresa,id_tipo) hace que un proceso tenga una sola config por
     * empresa, así que "crear" y "editar" son el mismo upsert.
     */
    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idTipo    = (int) ($_POST['id_tipo'] ?? 0);

        if (!$idTipo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Selecciona el proceso a aprobar.']);
            return;
        }

        // Si el proceso todavía no está configurado en la empresa, esto es un alta
        // (permiso de crear); si ya existe, es una edición (permiso de actualizar).
        $esNueva = !in_array($idTipo, $this->service->getTiposConfigurados($idEmpresa), true);
        if ($esNueva) {
            $this->requireCrear();
        } else {
            $this->requireActualizar();
        }

        try {
            $this->service->guardarConfig($idEmpresa, $idTipo, [
                'requiere_aprobacion'  => !empty($_POST['requiere_aprobacion']),
                'usuarios_aprobadores' => $_POST['usuarios_aprobadores'] ?? [],
                'umbral_monto'         => $_POST['umbral_monto'] ?? null,
            ], $idUsuario);
            echo json_encode([
                'ok'      => true,
                'mensaje' => $esNueva ? 'Aprobación creada.' : 'Aprobación actualizada.',
            ]);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar la aprobación.']);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idTipo    = (int) ($_POST['id_tipo'] ?? 0);

        if (!$idTipo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Aprobación no encontrada.']);
            return;
        }

        try {
            $this->service->eliminarConfig($idEmpresa, $idTipo, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Aprobación eliminada.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar la aprobación.']);
        }
    }

    // ─── Exportaciones ──────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $rows = $this->service->getListado(
            $idEmpresa,
            trim($_GET['b'] ?? ''),
            1,
            0,
            trim($_GET['sort'] ?? 'modulo'),
            strtoupper(trim($_GET['dir'] ?? 'asc'))
        )['rows'];

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Aprobaciones configuradas</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%">Módulo</th>
                            <th style="width: 25%">Proceso</th>
                            <th style="width: 34%">Aprobadores</th>
                            <th style="width: 14%">Monto mínimo</th>
                            <th style="width: 12%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): $info = self::infoModulo($r['modulo_ruta'] ?? ''); ?>
                            <tr>
                                <td><?= htmlspecialchars($info['nombre']) ?></td>
                                <td><?= htmlspecialchars((string) ($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(self::aprobadoresTexto($r)) ?></td>
                                <td><?= $r['umbral_monto'] !== null && $r['umbral_monto'] !== '' ? number_format((float) $r['umbral_monto'], 2) : 'Siempre' ?></td>
                                <td><?= !empty($r['requiere_aprobacion']) ? 'Activa' : 'Inactiva' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Aprobaciones_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $rows = $this->service->getListado(
            $idEmpresa,
            trim($_GET['b'] ?? ''),
            1,
            0,
            trim($_GET['sort'] ?? 'modulo'),
            strtoupper(trim($_GET['dir'] ?? 'asc'))
        )['rows'];

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Módulo', 'Proceso', 'Aprobadores', 'Monto mínimo', 'Estado'];
            $data = [];
            foreach ($rows as $r) {
                $info = self::infoModulo($r['modulo_ruta'] ?? '');
                $data[] = [
                    $info['nombre'],
                    (string) ($r['nombre'] ?? ''),
                    self::aprobadoresTexto($r),
                    $r['umbral_monto'] !== null && $r['umbral_monto'] !== '' ? number_format((float) $r['umbral_monto'], 2) : 'Siempre',
                    !empty($r['requiere_aprobacion']) ? 'Activa' : 'Inactiva',
                ];
            }

            (new \App\Services\ReportService())
                ->exportToExcel('Aprobaciones', $headers, $data, 'Aprobaciones configuradas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    // ─── Helpers de render ──────────────────────────────────────────────────────

    /** Lista de aprobadores como texto plano separado por comas (PDF / Excel). */
    private static function aprobadoresTexto(array $r): string
    {
        return str_replace(
            AprobacionesRepository::SEP_APROBADORES,
            ', ',
            (string) ($r['aprobadores_nombres'] ?? '')
        );
    }

    /** Lista de aprobadores como arreglo de nombres. */
    private static function aprobadoresLista(array $r): array
    {
        $txt = (string) ($r['aprobadores_nombres'] ?? '');
        if ($txt === '') return [];
        return explode(AprobacionesRepository::SEP_APROBADORES, $txt);
    }

    /** Una fila del listado. Se usa en la carga inicial y en searchAjax. */
    public static function filaHtml(array $r, bool $puedeAbrir): string
    {
        $idTipo = (int) $r['id_tipo'];
        $info   = self::infoModulo($r['modulo_ruta'] ?? '');
        $activa = !empty($r['requiere_aprobacion']);
        $esc    = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $nombres = self::aprobadoresLista($r);
        $chips = $nombres
            ? implode('', array_map(
                static fn(string $n): string => '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-medium">' . htmlspecialchars(trim($n), ENT_QUOTES, 'UTF-8') . '</span>',
                $nombres
            ))
            : '<span class="text-muted small">Sin aprobadores</span>';

        $umbral = ($r['umbral_monto'] !== null && $r['umbral_monto'] !== '')
            ? '<span class="fw-medium">$ ' . number_format((float) $r['umbral_monto'], 2) . '</span>'
            : '<span class="text-muted small">Siempre</span>';

        $badge = $activa
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activa</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactiva</span>';

        $desc = !empty($r['descripcion'])
            ? '<div class="text-muted" style="font-size:.72rem;">' . $esc($r['descripcion']) . '</div>'
            : '';

        $attrs = $puedeAbrir
            ? ' class="aprobacion-row" role="button" tabindex="0" onclick="APR_abrirModal(' . $idTipo . ')"'
            : '';

        return '<tr' . $attrs . ' data-tipo="' . $idTipo . '">'
            . '<td class="ps-3" data-col="modulo"><i class="bi ' . $info['icono'] . ' ' . $info['color'] . ' me-1"></i>' . $esc($info['nombre']) . '</td>'
            . '<td data-col="proceso" style="max-width:340px;"><div class="fw-medium">' . $esc($r['nombre']) . '</div>' . $desc . '</td>'
            . '<td data-col="aprobadores"><div class="d-flex flex-wrap gap-1">' . $chips . '</div></td>'
            . '<td class="text-end" data-col="umbral">' . $umbral . '</td>'
            . '<td class="text-center pe-3" data-col="estado">' . $badge . '</td>'
            . '</tr>';
    }

    /** Mapa id_tipo → datos del modal, para abrir en edición sin ir al servidor. */
    public static function mapaConfiguradas(array $rows): array
    {
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[(int) $r['id_tipo']] = [
                'id'          => (int) $r['id_tipo'],
                'nombre'      => $r['nombre'],
                'descripcion' => $r['descripcion'] ?? '',
                'aprobadores' => array_values(array_map('intval', $r['usuarios_aprobadores'])),
                'umbral'      => $r['umbral_monto'],
                'activa'      => !empty($r['requiere_aprobacion']),
            ];
        }
        return $mapa;
    }
}
