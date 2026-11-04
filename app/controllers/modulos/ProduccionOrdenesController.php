<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\models\Empresa;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\ProduccionOrdenRules;
use App\Services\modulos\ProduccionAsientoService;
use App\Services\modulos\ProduccionFormulaService;
use App\Services\modulos\ProduccionOrdenService;

/**
 * Órdenes de producción (modulos/produccion-ordenes), con el botón Producción rápida.
 * Permisos: ver; crear = nueva orden y producción rápida; actualizar = consumir, cerrar y
 * generar asiento; eliminar = anular y eliminar. Sin acceso total, cada usuario ve solo
 * sus órdenes. Sin lógica de negocio: delega en ProduccionOrdenService.
 */
class ProduccionOrdenesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/produccion-ordenes';

    private ProduccionOrdenService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = ProduccionOrdenService::crear();
    }

    private function sesion(): array
    {
        return [(int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], (int) ($_SESSION['nivel'] ?? 1)];
    }

    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function fallo(\Throwable $e, string $accion): never
    {
        if ($e instanceof \DomainException) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
        $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    private function filtros(): array
    {
        $estado = trim((string) ($_GET['estado'] ?? ''));
        $tipo   = trim((string) ($_GET['tipo'] ?? ''));
        return [
            'desde'  => trim((string) ($_GET['desde'] ?? '')),
            'hasta'  => trim((string) ($_GET['hasta'] ?? '')),
            'estado' => isset(ProduccionOrdenRules::ESTADOS[$estado]) ? $estado : '',
            'tipo'   => isset(ProduccionOrdenRules::TIPOS[$tipo]) ? $tipo : '',
        ];
    }

    private function bodegasDelUsuario(int $idEmpresa): array
    {
        [, $idUsuario, $nivel] = $this->sesion();
        $permitidas = [];
        foreach ((new \App\repositories\modulos\BodegaRepository())->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel) as $b) {
            $permitidas[(int) $b['id']] = true;
        }
        return array_values(array_filter(
            $this->service->config()->repo()->getBodegas($idEmpresa),
            fn($b) => isset($permitidas[(int) $b['id']])
        ));
    }

    /** Lectura de un registro por id con la regla de registros propios. */
    private function ordenPropia(int $id): array
    {
        $o = $this->service->repo()->getPorId($id, (int) $_SESSION['id_empresa']);
        if (!$o) {
            $this->json(['ok' => false, 'mensaje' => 'La orden no existe.']);
        }
        $this->requireRegistroPropio($o);
        return $o;
    }

    // ────────────────────────────────────────────────────────────────
    // LISTADO
    // ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $prefs     = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden     = OrdenListado::leer($prefs, 'numero', 'DESC');
        $perPage   = $this->porPagina();
        $buscar    = trim((string) ($_GET['b'] ?? ''));
        $filtros   = $this->filtros();
        $config    = $this->service->config();
        $instalado = $config->instalado();
        $cfg       = $instalado ? $config->get($idEmpresa) : null;

        $res = $cfg ? $this->service->getListado($idEmpresa, $buscar, 1, $perPage, $orden, $this->idUsuarioFiltro(), $filtros) : ['total' => 0, 'rows' => []];
        $resumen = $cfg ? $this->service->getResumen($idEmpresa, $this->idUsuarioFiltro(), $filtros, $buscar) : [];

        $this->viewWithLayout('layouts.main', 'modulos/produccion_ordenes/index', [
            'titulo'      => 'Órdenes de producción',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => self::RUTA_MODULO,
            'vistaConfig' => $prefs,
            'instalado'   => $instalado,
            'config'      => $cfg,
            'filasHtml'   => $this->renderFilas($res['rows']),
            'total'       => (int) $res['total'],
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'filtros'     => $filtros,
            'resumen'     => $resumen,
            'bodegas'     => $cfg ? $this->bodegasDelUsuario($idEmpresa) : [],
            'estados'     => ProduccionOrdenRules::ESTADOS,
            'tipos'       => ProduccionOrdenRules::TIPOS,
            'ordenJson'   => OrdenListado::aJson($orden),
            'ordenParam'  => OrdenListado::aCadena($orden),
            'base'        => BASE_URL,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $prefs   = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden   = OrdenListado::leer($prefs, 'numero', 'DESC');
        $perPage = $this->porPagina();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $buscar  = trim((string) ($_GET['b'] ?? ''));
        $filtros = $this->filtros();

        if (!$this->service->config()->get($idEmpresa)) {
            $this->json(['ok' => true, 'rows' => $this->renderFilas([]), 'page' => 1, 'pages' => 1, 'info' => '0-0/0', 'resumen' => []]);
        }
        $res   = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $this->idUsuarioFiltro(), $filtros);
        $total = (int) $res['total'];
        $from  = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to    = $total > 0 ? min($page * $perPage, $total) : 0;
        $q     = http_build_query(['b' => $buscar] + $filtros + ['orden' => OrdenListado::aCadena($orden)]);

        $this->json([
            'ok'        => true,
            'rows'      => $this->renderFilas($res['rows']),
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / max(1, $perPage))),
            'info'      => "{$from}-{$to}/{$total}",
            'resumen'   => $this->service->getResumen($idEmpresa, $this->idUsuarioFiltro(), $filtros, $buscar),
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?' . $q,
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?' . $q,
        ]);
    }

    public static function badgeEstado(string $estado): string
    {
        [$color, $txt] = match ($estado) {
            'liberada'   => ['info', 'Planificada'],
            'en_proceso' => ['warning', 'En proceso'],
            'cerrada'    => ['success', 'Cerrada'],
            'anulada'    => ['danger', 'Anulada'],
            default      => ['secondary', ucfirst($estado)],
        };
        return '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color . ' border border-' . $color . ' border-opacity-25">' . $txt . '</span>';
    }

    private static function cant(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.');
    }

    private function renderFilas(array $rows): string
    {
        if ($rows === []) {
            return '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-gear-wide-connected fs-3 d-block mb-2"></i>No hay órdenes de producción que coincidan.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $rapida = $r['tipo'] === 'rapida' ? ' <i class="bi bi-lightning-charge text-warning" title="Producción rápida"></i>' : '';
            $aviso  = !empty($r['asiento_aviso']) ? ' <i class="bi bi-exclamation-triangle text-danger" title="' . htmlspecialchars((string) $r['asiento_aviso'], ENT_QUOTES) . '"></i>' : '';
            $html .= '<tr class="por-row" role="button" onclick="window.POR_abrir(' . (int) $r['id'] . ')">'
                . '<td class="ps-3" data-col="numero"><code>' . htmlspecialchars((string) $r['numero']) . '</code>' . $rapida . $aviso . '</td>'
                . '<td data-col="fecha_inicio">' . date('d-m-Y', strtotime((string) $r['fecha_inicio'])) . '</td>'
                . '<td data-col="producto">' . htmlspecialchars(trim($r['producto_codigo'] . ' - ' . $r['producto_nombre'], ' -')) . '</td>'
                . '<td data-col="lote">' . htmlspecialchars((string) ($r['lote'] ?? '')) . '</td>'
                . '<td class="text-end" data-col="cantidad_planificada">' . self::cant((float) $r['cantidad_planificada']) . '</td>'
                . '<td class="text-end" data-col="cantidad_producida">' . ($r['estado'] === 'cerrada' ? self::cant((float) $r['cantidad_producida']) : '—')
                    . ' <span class="text-muted small">' . htmlspecialchars((string) ($r['medida_nombre'] ?? '')) . '</span></td>'
                . '<td class="text-end" data-col="costo_total">' . ($r['estado'] === 'cerrada' ? '$' . number_format((float) $r['costo_total'], 2) : '—') . '</td>'
                . '<td data-col="bodegas" class="small">' . htmlspecialchars((string) ($r['bodega_insumos_nombre'] ?? '')) . ' <i class="bi bi-arrow-right text-muted"></i> ' . htmlspecialchars((string) ($r['bodega_destino_nombre'] ?? '')) . '</td>'
                . '<td data-col="fecha_fin">' . (!empty($r['fecha_fin']) ? date('d-m-Y', strtotime((string) $r['fecha_fin'])) : '') . '</td>'
                . '<td class="text-center pe-3" data-col="estado">' . self::badgeEstado((string) $r['estado']) . '</td>'
                . '</tr>';
        }
        return $html;
    }

    // ────────────────────────────────────────────────────────────────
    // FICHA
    // ────────────────────────────────────────────────────────────────

    public function getOrdenAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $this->ordenPropia((int) ($_GET['id'] ?? 0));
        $o = $this->service->getCompleta((int) $_GET['id'], $idEmpresa);
        $o['asientos'] = (new ProduccionAsientoService())->getAsientos($o, $idEmpresa);
        $o['estado_nombre'] = ProduccionOrdenRules::ESTADOS[$o['estado']] ?? $o['estado'];
        $o['badge_estado'] = self::badgeEstado((string) $o['estado']);
        $this->json(['ok' => true, 'data' => $o]);
    }

    public function formulasAjax(): void
    {
        $this->requireLeer();
        $this->json(['ok' => true, 'data' => ProduccionFormulaService::crear()->repo()->getVigentes((int) $_SESSION['id_empresa'], trim((string) ($_GET['q'] ?? '')))]);
    }

    public function vistaPreviaAjax(): void
    {
        $this->requireLeer();
        try {
            $this->json(['ok' => true, 'data' => $this->service->vistaPrevia(
                (int) ($_GET['id_formula'] ?? 0),
                (float) str_replace(',', '.', (string) ($_GET['cantidad'] ?? 0)),
                (int) ($_GET['id_bodega_insumos'] ?? 0),
                (int) $_SESSION['id_empresa']
            )]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function lotesAjax(): void
    {
        $this->requireLeer();
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
        if ($idProducto <= 0 || $idBodega <= 0) {
            $this->json(['ok' => true, 'lotes' => [], 'stock' => 0]);
        }
        $inv = new InventarioRepository();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->json([
            'ok'    => true,
            'stock' => $inv->getStockActual($idProducto, $idBodega, $idEmpresa),
            'lotes' => array_values(array_filter(
                $inv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa),
                fn($l) => !in_array(strtoupper((string) $l['numero_lote']), ['SIN_LOTE', 'SIN LOTE', ''], true)
            )),
        ]);
    }

    public function buscarInsumosAjax(): void
    {
        $this->requireLeer();
        $this->json(['ok' => true, 'data' => ProduccionFormulaService::crear()->repo()->buscarProductos((int) $_SESSION['id_empresa'], trim((string) ($_GET['q'] ?? '')))]);
    }

    // ────────────────────────────────────────────────────────────────
    // ESCRITURA
    // ────────────────────────────────────────────────────────────────

    public function crearAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa, $idUsuario, $nivel] = $this->sesion();
        try {
            $id = $this->service->crearOrden($_POST, $idEmpresa, $idUsuario, $nivel);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => $this->service->fueRepetido()
                ? 'Esta orden ya estaba registrada; no se creó otra.'
                : 'Orden de producción creada. Registre los consumos y, al terminar, ciérrela.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function rapidaAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa, $idUsuario, $nivel] = $this->sesion();
        try {
            $salidas = json_decode((string) ($_POST['salidas'] ?? '[]'), true) ?: [];
            $id = $this->service->produccionRapida($_POST, $salidas, (float) str_replace(',', '.', (string) ($_POST['merma'] ?? 0)), $idEmpresa, $idUsuario, $nivel);
            $o = $this->service->repo()->getPorId($id, $idEmpresa);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => $this->service->fueRepetido()
                ? 'Esta producción ya estaba registrada; no se registró otra.'
                : 'Producción registrada: se consumieron los insumos y entró ' . self::cant((float) ($o['cantidad_producida'] ?? 0)) . ' al inventario.'
                    . (!empty($o['asiento_aviso']) ? ' Aviso contable: ' . $o['asiento_aviso'] : '')]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function consumirAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario, $nivel] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->ordenPropia($id);
            $items = json_decode((string) ($_POST['items'] ?? '[]'), true) ?: [];
            $this->service->consumir($id, $items, trim((string) ($_POST['fecha'] ?? '')) ?: date('Y-m-d'),
                (string) ($_POST['token_guardado'] ?? ''), $idEmpresa, $idUsuario, $nivel);
            $o = $this->service->repo()->getPorId($id, $idEmpresa);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => $this->service->fueRepetido()
                ? 'Este consumo ya estaba registrado; no se descontó otra vez.'
                : 'Consumo registrado.' . (!empty($o['asiento_aviso']) ? ' Aviso contable: ' . $o['asiento_aviso'] : '')]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function cerrarAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario, $nivel] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->ordenPropia($id);
            $salidas  = json_decode((string) ($_POST['salidas'] ?? '[]'), true) ?: [];
            $recursos = isset($_POST['recursos']) ? (json_decode((string) $_POST['recursos'], true) ?: []) : null;
            $this->service->cerrar($id, $salidas, (float) str_replace(',', '.', (string) ($_POST['merma'] ?? 0)), $recursos,
                ($_POST['consumir_pendiente'] ?? '') === '1', trim((string) ($_POST['fecha'] ?? '')) ?: date('Y-m-d'),
                (string) ($_POST['token_guardado'] ?? ''), $idEmpresa, $idUsuario, $nivel);
            $o = $this->service->repo()->getPorId($id, $idEmpresa);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => $this->service->fueRepetido()
                ? 'Esta orden ya estaba cerrada; no se registró otra entrada.'
                : 'Orden cerrada: lo producido entró al inventario con su costo real.' . (!empty($o['asiento_aviso']) ? ' Aviso contable: ' . $o['asiento_aviso'] : '')]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function anularAjax(): void
    {
        $this->requireEliminar();
        [$idEmpresa, $idUsuario, $nivel] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->ordenPropia($id);
            $this->service->anular($id, $idEmpresa, $idUsuario, $nivel);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Orden anulada: se revirtieron sus movimientos de inventario y sus asientos.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->ordenPropia($id);
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Orden eliminada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function generarAsientoAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->ordenPropia($id);
            (new ProduccionAsientoService())->contabilizar($id, $idEmpresa, $idUsuario);
            $this->service->repo()->actualizarCabecera($id, ['asiento_aviso' => null], $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Asientos generados.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ────────────────────────────────────────────────────────────────
    // PDF / EXPORTACIONES
    // ────────────────────────────────────────────────────────────────

    /** Orden de producción / registro de lote (PDF de un documento: CMG_pdfDocumento). */
    public function pdfDocumento(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $o = $this->service->repo()->getPorId((int) ($_GET['id'] ?? 0), $idEmpresa);
        if (!$o) {
            $this->json(['ok' => false, 'mensaje' => 'La orden no existe.'], 404);
        }
        $this->requireRegistroPropio($o);
        try {
            (new \App\Services\modulos\ProduccionOrdenPdfService())->generar($this->service->getCompleta((int) $o['id'], $idEmpresa), $idEmpresa, 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'No se pudo generar el PDF: ' . $e->getMessage()]);
        }
        exit;
    }

    private function filasExport(): array
    {
        [$idEmpresa] = $this->sesion();
        if (!$this->service->config()->get($idEmpresa)) {
            return [];
        }
        $orden = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), 'numero', 'DESC');
        return $this->service->getListado($idEmpresa, trim((string) ($_GET['b'] ?? '')), 1, 10000, $orden, $this->idUsuarioFiltro(), $this->filtros())['rows'];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->filasExport();
        try {
            $empresa = (new Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];
            $data = [];
            foreach ($rows as $r) {
                $data[] = [
                    (string) $r['numero'], ProduccionOrdenRules::TIPOS[$r['tipo']] ?? $r['tipo'],
                    date('d-m-Y', strtotime((string) $r['fecha_inicio'])),
                    trim($r['producto_codigo'] . ' - ' . $r['producto_nombre'], ' -'), (string) ($r['lote'] ?? ''),
                    (float) $r['cantidad_planificada'], (float) $r['cantidad_producida'], (float) $r['cantidad_merma'],
                    (float) $r['costo_materiales'], (float) $r['costo_mano_obra'], (float) $r['costo_cif'],
                    (float) $r['valor_subproductos'], (float) $r['costo_merma'], (float) $r['costo_total'],
                    (string) ($r['bodega_insumos_nombre'] ?? ''), (string) ($r['bodega_destino_nombre'] ?? ''),
                    !empty($r['fecha_fin']) ? date('d-m-Y', strtotime((string) $r['fecha_fin'])) : '',
                    ProduccionOrdenRules::ESTADOS[$r['estado']] ?? $r['estado'],
                ];
            }
            (new \App\Services\ReportService())->exportToExcel(
                'Órdenes',
                ['Número', 'Tipo', 'Fecha', 'Producto', 'Lote', 'Planificado', 'Producido', 'Merma anormal', 'Materiales',
                 'Mano de obra', 'CIF', 'Subproductos', 'Costo merma', 'Costo producido', 'Bodega insumos', 'Bodega destino', 'Cierre', 'Estado'],
                $data,
                'Ordenes_Produccion',
                (string) ($empresa['nombre'] ?? '')
            );
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasExport();
        try {
            $empresa = (new Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];
            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7.5pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 3px; text-align: left; }
                td { border: 1px solid #ccc; padding: 3px; }
                .r { text-align: right; }
                h1 { margin: 0; font-size: 14pt; text-align: center; } h2 { margin: 3px 0 10px; font-size: 10pt; text-align: center; color: #666; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm" orientation="landscape">
                <h1><?= htmlspecialchars((string) ($empresa['nombre'] ?? '')) ?></h1>
                <h2>ÓRDENES DE PRODUCCIÓN</h2>
                <table>
                    <thead><tr>
                        <th style="width:9%">Número</th><th style="width:8%">Fecha</th><th style="width:27%">Producto</th>
                        <th style="width:10%">Lote</th><th style="width:9%" class="r">Planificado</th><th style="width:9%" class="r">Producido</th>
                        <th style="width:10%" class="r">Costo</th><th style="width:8%">Cierre</th><th style="width:10%">Estado</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="width:9%"><?= htmlspecialchars((string) $r['numero']) ?></td>
                            <td style="width:8%"><?= date('d-m-Y', strtotime((string) $r['fecha_inicio'])) ?></td>
                            <td style="width:27%"><?= htmlspecialchars(trim($r['producto_codigo'] . ' - ' . $r['producto_nombre'], ' -')) ?></td>
                            <td style="width:10%"><?= htmlspecialchars((string) ($r['lote'] ?? '')) ?></td>
                            <td style="width:9%" class="r"><?= number_format((float) $r['cantidad_planificada'], 2) ?></td>
                            <td style="width:9%" class="r"><?= $r['estado'] === 'cerrada' ? number_format((float) $r['cantidad_producida'], 2) : '-' ?></td>
                            <td style="width:10%" class="r"><?= $r['estado'] === 'cerrada' ? number_format((float) $r['costo_total'], 2) : '-' ?></td>
                            <td style="width:8%"><?= !empty($r['fecha_fin']) ? date('d-m-Y', strtotime((string) $r['fecha_fin'])) : '' ?></td>
                            <td style="width:10%"><?= htmlspecialchars(ProduccionOrdenRules::ESTADOS[$r['estado']] ?? $r['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $html = ob_get_clean();
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Ordenes_Produccion_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}
