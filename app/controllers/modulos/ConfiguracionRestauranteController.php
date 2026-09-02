<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ConfiguracionRestauranteService;
use Exception;

/**
 * Configuración del restaurante: estaciones de preparación (cocina, barra,
 * parrilla…), su impresora y cuál recoge lo que no tiene estación propia.
 *
 * Estas estaciones se administraban en una pestaña del modal de Menú; se
 * movieron aquí para que la configuración del salón viva en un solo sitio y
 * para que un local que trabaja sin carta no tenga que entrar a Menú para
 * configurar su cocina.
 *
 * Ruta 'modulos/configuracion-restaurante' → ConfiguracionRestauranteController.
 */
class ConfiguracionRestauranteController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/configuracion-restaurante';
    private const PER_PAGE = 20;

    private ConfiguracionRestauranteService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ConfiguracionRestauranteService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));

        $result = $this->service->getListado($idEmpresa, $buscar, $page, self::PER_PAGE, $ordenCol, $ordenDir);

        // Con layout: la vista es un fragmento (sin <html> ni assets propios).
        // Las standalone del salón —tablero de mesas, comanda, KDS— sí usan
        // view() a secas porque arman su propia página.
        $this->viewWithLayout('layouts.main', 'modulos.configuracion_restaurante.index', [
            'titulo'      => 'Configuración Restaurante',
            'rutaModulo'  => self::RUTA_MODULO,
            'perm'        => $this->getPermisos(),
            'rows'        => $result['rows'],
            'filasHtml'   => $this->renderFilas($result['rows'], !empty($this->getPermisos()['actualizar'])),
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => (int) ceil($result['total'] / self::PER_PAGE) ?: 1,
            'perPage'     => self::PER_PAGE,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'anchoTirilla' => $this->service->getAnchoTirilla($idEmpresa),
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
        ]);
    }

    /** Refresco del listado sin recargar la página (buscador, orden y paginación). */
    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = max(1, (int) ($_GET['perPage'] ?? $_POST['perPage'] ?? self::PER_PAGE));

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        $perm = $this->getPermisos();

        $rowsHtml = $this->renderFilas($result['rows'], !empty($perm['actualizar']), true);

        $prevDisabled = $page <= 1 ? 'disabled' : '';
        $nextDisabled = $page >= $totalPages ? 'disabled' : '';
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
            'data_raw'   => $result['rows'],
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    /**
     * El tbody completo. Lo usan la carga inicial y el refresco por AJAX, así
     * que las dos rutas pintan exactamente lo mismo.
     *
     * @param bool $esBusqueda Cambia el mensaje del vacío: sin resultados no es
     *                         lo mismo que un módulo recién estrenado.
     */
    private function renderFilas(array $rows, bool $puedeActualizar, bool $esBusqueda = false): string
    {
        if (empty($rows)) {
            return $esBusqueda
                ? '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-printer fs-3 d-block mb-2"></i>No se encontraron estaciones.</td></tr>'
                : '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-printer fs-3 d-block mb-2"></i>Todavía no hay estaciones.'
                  . '<div class="small">Cree al menos una (por ejemplo "Cocina") para que los pedidos lleguen a preparación.</div></td></tr>';
        }

        $html = '';
        foreach ($rows as $r) {
            $html .= $this->filaHtml($r, $puedeActualizar);
        }
        return $html;
    }

    /**
     * Una fila del listado. Se arma en PHP y no en JS para que la carga inicial
     * y el refresco por AJAX pinten exactamente lo mismo (mismo criterio que
     * Proveedores y Categorías).
     */
    private function filaHtml(array $r, bool $puedeActualizar): string
    {
        $esVerdadero = static fn($v): bool => $v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1';

        $tipos = [
            'cocina' => ['Cocina', 'warning', 'bi-fire'],
            'barra'  => ['Barra', 'info', 'bi-cup-straw'],
            'otro'   => ['Otro', 'secondary', 'bi-egg-fried'],
        ];
        [$lblTipo, $colorTipo, $iconoTipo] = $tipos[$r['tipo'] ?? 'otro'] ?? $tipos['otro'];

        $imprime = $esVerdadero($r['imprime_ordenes'] ?? false);
        $activo  = $esVerdadero($r['activo'] ?? false);
        $pred    = $esVerdadero($r['es_predeterminada'] ?? false);
        $usos    = (int) ($r['usos'] ?? 0);

        $badgeImpresion = $imprime
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-printer"></i> '
              . ($esVerdadero($r['imprimir_auto'] ?? false) ? 'Automática' : 'A pedido') . '</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary">Solo pantalla</span>';

        $badgeEstado = $activo
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Activa</span>'
            : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10">Inactiva</span>';

        // La estrella marca la estación que recoge los ítems sin estación
        // propia. Va en la fila —no en un selector aparte— y no abre el modal:
        // el onclick corta la propagación.
        $tituloEstrella = $pred
            ? 'Es la estación predeterminada. Clic para quitarla.'
            : ($activo ? 'Marcar como estación predeterminada' : 'Una estación inactiva no puede ser la predeterminada');
        $estrella = '<button type="button" class="cr-estrella' . ($pred ? ' activa' : '') . '"'
            . ' data-id="' . (int) $r['id'] . '" data-pred="' . ($pred ? '1' : '0') . '"'
            . ' onclick="event.stopPropagation(); CR_togglePredeterminada(this)"'
            . ((!$puedeActualizar || !$activo) ? ' disabled' : '')
            . ' title="' . htmlspecialchars($tituloEstrella) . '">'
            . '<i class="bi bi-star' . ($pred ? '-fill' : '') . ' fs-6"></i></button>';

        $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

        return '<tr class="estacion-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="CR_abrirModalEditar(this)">'
            . '<td class="ps-3 fw-medium" data-col="nombre">' . htmlspecialchars((string) ($r['nombre'] ?? '')) . '</td>'
            . '<td class="text-center" data-col="tipo"><span class="badge bg-' . $colorTipo . ' bg-opacity-10 text-' . $colorTipo
                . ' border border-' . $colorTipo . ' border-opacity-25"><i class="bi ' . $iconoTipo . '"></i> ' . $lblTipo . '</span></td>'
            . '<td class="text-center" data-col="impresion">' . $badgeImpresion . '</td>'
            . '<td class="text-center text-muted" data-col="ancho_papel">' . ($imprime ? (int) $r['ancho_papel'] . ' mm' : '-') . '</td>'
            . '<td class="text-center text-muted" data-col="copias">' . ($imprime ? (int) $r['copias'] : '-') . '</td>'
            . '<td class="text-center" data-col="predeterminada">' . $estrella . '</td>'
            . '<td class="text-center" data-col="activo">' . $badgeEstado . '</td>'
            . '<td class="text-center pe-3 small text-muted" data-col="usos">' . ($usos > 0 ? $usos . ' ítem(s)' : '-') . '</td>'
            . '</tr>';
    }

    public function guardarEstacionAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $data = [
                'nombre'          => $_POST['nombre'] ?? '',
                'tipo'            => $_POST['tipo'] ?? 'cocina',
                'orden'           => (int) ($_POST['orden'] ?? 0),
                // Los checkbox solo llegan cuando están marcados.
                'activo'          => !empty($_POST['activo']),
                'imprime_ordenes' => !empty($_POST['imprime_ordenes']),
                'imprimir_auto'   => !empty($_POST['imprimir_auto']),
                'ancho_papel'     => (int) ($_POST['ancho_papel'] ?? 80),
                'copias'          => (int) ($_POST['copias'] ?? 1),
            ];

            if ($id > 0) {
                $this->service->actualizarEstacion($id, $idEmpresa, $idUsuario, $data);
                $this->json(['ok' => true, 'msg' => 'Estación actualizada.', 'id' => $id]);
                return;
            }

            $nuevo = $this->service->crearEstacion($idEmpresa, $idUsuario, $data);
            $this->json(['ok' => true, 'msg' => 'Estación creada.', 'id' => $nuevo]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function eliminarEstacionAjax(): void
    {
        $this->requireEliminar();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Estación no válida.');
            }
            $this->service->eliminarEstacion($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'msg' => 'Estación eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Marca (o desmarca, con id = 0) la estación que recoge los ítems sin
     * estación propia — los que vienen del stock general. Se acciona desde la
     * estrella de cada fila del listado.
     */
    public function fijarPredeterminadaAjax(): void
    {
        $this->requireActualizar();
        try {
            $idEstacion = (int) ($_POST['id_estacion'] ?? 0);
            $this->service->fijarPredeterminada($idEstacion, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            $this->json([
                'ok'  => true,
                'msg' => $idEstacion > 0 ? 'Estación predeterminada actualizada.' : 'Ya no hay estación predeterminada.',
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Ancho del papel de la tirilla de cuenta/factura (58 u 80 mm). */
    public function guardarAnchoTirillaAjax(): void
    {
        $this->requireActualizar();
        try {
            $this->service->guardarAnchoTirilla(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                (int) ($_POST['ancho_papel_tirilla'] ?? 80)
            );
            $this->json(['ok' => true, 'msg' => 'Ancho de la tirilla guardado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Exportaciones ────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'asc'));

        $rows = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir)['rows'];

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'ESTACIONES DE PREPARACIÓN';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $filas = array_map(fn(array $r): array => $this->filaExport($r), $rows);

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
                    <h2>Estaciones de preparación</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 26%">Nombre</th>
                            <th style="width: 12%">Tipo</th>
                            <th style="width: 16%">Impresión</th>
                            <th style="width: 10%">Papel</th>
                            <th style="width: 10%">Copias</th>
                            <th style="width: 14%">Predeterminada</th>
                            <th style="width: 12%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f): ?>
                            <tr>
                                <?php foreach ($f as $celda): ?>
                                    <td><?= htmlspecialchars((string) $celda) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Estaciones_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'asc'));

        $rows = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir)['rows'];

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Nombre', 'Tipo', 'Impresión', 'Papel', 'Copias', 'Predeterminada', 'Estado'];
            $data = array_map(fn(array $r): array => $this->filaExport($r), $rows);

            (new \App\Services\ReportService())->exportToExcel(
                'Estaciones',
                $headers,
                $data,
                'Estaciones de preparación',
                $empresa['nombre'] ?? ''
            );
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** Una fila para PDF y Excel: mismas columnas y mismo orden en los dos. */
    private function filaExport(array $r): array
    {
        $esVerdadero = static fn($v): bool => $v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1';
        $imprime = $esVerdadero($r['imprime_ordenes'] ?? false);

        return [
            (string) ($r['nombre'] ?? ''),
            ucfirst((string) ($r['tipo'] ?? '')),
            $imprime ? ($esVerdadero($r['imprimir_auto'] ?? false) ? 'Automática' : 'A pedido') : 'Solo pantalla',
            $imprime ? (int) $r['ancho_papel'] . ' mm' : '-',
            $imprime ? (string) (int) $r['copias'] : '-',
            $esVerdadero($r['es_predeterminada'] ?? false) ? 'Sí' : 'No',
            $esVerdadero($r['activo'] ?? false) ? 'Activa' : 'Inactiva',
        ];
    }
}
