<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\models\Empresa;
use App\Rules\modulos\ProduccionFormulaRules;
use App\Services\modulos\ProduccionConfiguracionService;
use App\Services\modulos\ProduccionFormulaService;

/**
 * Fórmulas de producción (modulos/produccion-formulas): lista de materiales / receta /
 * plantilla con entradas, salidas y costos de MO/CIF. Permisos: ver, crear, actualizar
 * (editar, activar, inactivar, nueva versión) y eliminar. Sin acceso total, cada usuario
 * ve solo las fórmulas que creó. Sin lógica de negocio: delega en ProduccionFormulaService.
 */
class ProduccionFormulasController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/produccion-formulas';

    private ProduccionFormulaService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = ProduccionFormulaService::crear();
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
        $this->json(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado: ' . $e->getMessage()]);
    }

    // ────────────────────────────────────────────────────────────────
    // LISTADO
    // ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefs     = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden     = OrdenListado::leer($prefs, 'nombre');
        $perPage   = $this->porPagina();
        $buscar    = trim((string) ($_GET['b'] ?? ''));
        $estado    = trim((string) ($_GET['estado'] ?? ''));
        $config    = $this->service->config();
        $instalado = $config->instalado();
        $cfg       = $instalado ? $config->get($idEmpresa) : null;

        $res = $cfg
            ? $this->service->getListado($idEmpresa, $buscar, 1, $perPage, $orden, $this->idUsuarioFiltro(), $estado)
            : ['total' => 0, 'rows' => []];

        $this->viewWithLayout('layouts.main', 'modulos/produccion_formulas/index', [
            'titulo'      => 'Fórmulas de producción',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => self::RUTA_MODULO,
            'vistaConfig' => $prefs,
            'instalado'   => $instalado,
            'config'      => $cfg,
            'filasHtml'   => $this->renderFilas($res['rows']),
            'total'       => (int) $res['total'],
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'estado'      => $estado,
            'ordenJson'   => OrdenListado::aJson($orden),
            'ordenParam'  => OrdenListado::aCadena($orden),
            'modos'       => ProduccionFormulaRules::MODOS,
            'tiposRecurso' => ProduccionFormulaRules::TIPOS_RECURSO,
            'basesRecurso' => ProduccionFormulaRules::BASES_RECURSO,
            'metodos'     => ProduccionConfiguracionService::METODOS_REPARTO,
            'base'        => BASE_URL,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefs   = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden   = OrdenListado::leer($prefs, 'nombre');
        $perPage = $this->porPagina();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $buscar  = trim((string) ($_GET['b'] ?? ''));
        $estado  = trim((string) ($_GET['estado'] ?? ''));

        if (!$this->service->config()->get($idEmpresa)) {
            $this->json(['ok' => true, 'rows' => $this->renderFilas([]), 'total' => 0, 'page' => 1, 'pages' => 1, 'info' => '0-0/0']);
        }

        $res   = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $this->idUsuarioFiltro(), $estado);
        $total = (int) $res['total'];
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $from  = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to    = $total > 0 ? min($page * $perPage, $total) : 0;
        $q     = 'b=' . urlencode($buscar) . '&estado=' . urlencode($estado) . '&orden=' . urlencode(OrdenListado::aCadena($orden));

        $this->json([
            'ok'        => true,
            'rows'      => $this->renderFilas($res['rows']),
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'info'      => "{$from}-{$to}/{$total}",
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?' . $q,
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?' . $q,
        ]);
    }

    private static function badgeEstado(string $estado): string
    {
        return match ($estado) {
            'vigente'  => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>',
            'inactiva' => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactiva</span>',
            default    => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>',
        };
    }

    private function renderFilas(array $rows): string
    {
        if ($rows === []) {
            return '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>No hay fórmulas que coincidan.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $mod = !empty($r['updated_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['updated_at'])) : '';
            $html .= '<tr class="pfo-row" role="button" onclick="window.PFO_abrir(' . (int) $r['id'] . ')">'
                . '<td class="ps-3" data-col="codigo"><code>' . htmlspecialchars((string) $r['codigo']) . '</code></td>'
                . '<td data-col="nombre">' . htmlspecialchars((string) $r['nombre']) . '</td>'
                . '<td data-col="producto">' . htmlspecialchars(trim($r['producto_codigo'] . ' - ' . $r['producto_nombre'], ' -')) . '</td>'
                . '<td class="text-center" data-col="version">v' . (int) $r['version'] . '</td>'
                . '<td class="text-end" data-col="cantidad_base">' . rtrim(rtrim(number_format((float) $r['cantidad_base'], 6, '.', ''), '0'), '.')
                    . ' <span class="text-muted small">' . htmlspecialchars((string) ($r['medida_nombre'] ?? '')) . '</span></td>'
                . '<td class="text-center" data-col="lineas">' . (int) $r['insumos'] . ' / ' . (int) $r['salidas'] . '</td>'
                . '<td class="text-center" data-col="estado">' . self::badgeEstado((string) $r['estado']) . '</td>'
                . '<td class="pe-3" data-col="updated_at">' . $mod . '</td>'
                . '</tr>';
        }
        return $html;
    }

    // ────────────────────────────────────────────────────────────────
    // FICHA / ESCRITURA
    // ────────────────────────────────────────────────────────────────

    public function getFormulaAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->service->getCompleta((int) ($_GET['id'] ?? 0), $idEmpresa);
        if (!$f) {
            $this->json(['ok' => false, 'mensaje' => 'La fórmula no existe.']);
        }
        $this->requireRegistroPropio($f);
        $cfg = $this->service->config()->get($idEmpresa);
        $f['estimado'] = $this->service->estimar($f, (float) $f['cantidad_base'], $cfg ? ((int) $cfg['id_bodega_insumos'] ?: null) : null, $idEmpresa);
        $this->json(['ok' => true, 'data' => $f]);
    }

    public function guardarAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if ($id > 0) {
                $this->requireRegistroPropio($this->service->repo()->getPorId($id, $idEmpresa));
            }
            $nuevo = $this->service->guardar($id, $_POST, $idEmpresa, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'id' => $nuevo, 'mensaje' => $id > 0 ? 'Fórmula actualizada.' : 'Fórmula creada en borrador. Actívela para poder producir con ella.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function nuevaVersionAjax(): void
    {
        $this->requireCrear();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->getPorId($id, $idEmpresa));
            $nuevo = $this->service->nuevaVersion($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'id' => $nuevo, 'mensaje' => 'Se creó una nueva versión en borrador.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function activarAjax(): void
    {
        $this->requireActualizar();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->getPorId($id, $idEmpresa));
            $this->service->activar($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'mensaje' => 'Fórmula vigente. Las órdenes nuevas de este producto la usarán.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function inactivarAjax(): void
    {
        $this->requireActualizar();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->getPorId($id, $idEmpresa));
            $this->service->inactivar($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'mensaje' => 'Fórmula inactiva.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->getPorId($id, $idEmpresa));
            $this->service->eliminar($id, $idEmpresa, (int) $_SESSION['id_usuario']);
            $this->json(['ok' => true, 'mensaje' => 'Fórmula eliminada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Costo estimado del formulario en edición (sin guardar). */
    public function estimarAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $d = $this->service->normalizar($_POST);
            $ids = array_filter(array_map(fn($l) => (int) ($l['id_producto'] ?? 0), $d['lineas']));
            $productos = $this->service->repo()->getProductos($ids, $idEmpresa);
            foreach ($d['lineas'] as &$l) {
                $l['costo_producto'] = $productos[(int) ($l['id_producto'] ?? 0)]['costo_producto'] ?? 0;
            }
            unset($l);
            $cfg = $this->service->config()->get($idEmpresa);
            $cantidad = (float) ($_POST['cantidad_estimar'] ?? 0) ?: (float) $d['cantidad_base'];
            $this->json(['ok' => true, 'data' => $this->service->estimar($d, $cantidad, $cfg ? ((int) $cfg['id_bodega_insumos'] ?: null) : null, $idEmpresa)]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        $this->json([
            'ok'   => true,
            'data' => $this->service->repo()->buscarProductos((int) $_SESSION['id_empresa'], trim((string) ($_GET['q'] ?? ''))),
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // EXPORTACIONES DEL LISTADO
    // ────────────────────────────────────────────────────────────────

    private function filasExport(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$this->service->config()->get($idEmpresa)) {
            return [];
        }
        $orden = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), 'nombre');
        return $this->service->getListado($idEmpresa, trim((string) ($_GET['b'] ?? '')), 1, 10000, $orden,
            $this->idUsuarioFiltro(), trim((string) ($_GET['estado'] ?? '')))['rows'];
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
                    (string) $r['codigo'], (string) $r['nombre'],
                    trim($r['producto_codigo'] . ' - ' . $r['producto_nombre'], ' -'),
                    (int) $r['version'], (float) $r['cantidad_base'], (string) ($r['medida_nombre'] ?? ''),
                    (int) $r['insumos'], (int) $r['salidas'], ucfirst((string) $r['estado']),
                    !empty($r['updated_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['updated_at'])) : '',
                ];
            }
            (new \App\Services\ReportService())->exportToExcel(
                'Fórmulas',
                ['Código', 'Nombre', 'Producto', 'Versión', 'Rinde', 'Unidad', 'Insumos', 'Salidas', 'Estado', 'Modificado'],
                $data,
                'Formulas_Produccion',
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
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; }
                .r { text-align: right; } .c { text-align: center; }
                h1 { margin: 0; font-size: 14pt; text-align: center; } h2 { margin: 3px 0 10px; font-size: 10pt; text-align: center; color: #666; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm" orientation="landscape">
                <h1><?= htmlspecialchars((string) ($empresa['nombre'] ?? '')) ?></h1>
                <h2>FÓRMULAS DE PRODUCCIÓN</h2>
                <table>
                    <thead><tr>
                        <th style="width:11%">Código</th><th style="width:25%">Nombre</th><th style="width:28%">Producto</th>
                        <th style="width:7%" class="c">Versión</th><th style="width:12%" class="r">Rinde</th>
                        <th style="width:8%" class="c">Ins./Sal.</th><th style="width:9%" class="c">Estado</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="width:11%"><?= htmlspecialchars((string) $r['codigo']) ?></td>
                            <td style="width:25%"><?= htmlspecialchars((string) $r['nombre']) ?></td>
                            <td style="width:28%"><?= htmlspecialchars(trim($r['producto_codigo'] . ' - ' . $r['producto_nombre'], ' -')) ?></td>
                            <td style="width:7%" class="c">v<?= (int) $r['version'] ?></td>
                            <td style="width:12%" class="r"><?= number_format((float) $r['cantidad_base'], 2) ?> <?= htmlspecialchars((string) ($r['medida_nombre'] ?? '')) ?></td>
                            <td style="width:8%" class="c"><?= (int) $r['insumos'] ?> / <?= (int) $r['salidas'] ?></td>
                            <td style="width:9%" class="c"><?= ucfirst((string) $r['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $html = ob_get_clean();
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Formulas_Produccion_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}
