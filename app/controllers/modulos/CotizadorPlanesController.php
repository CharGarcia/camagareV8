<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\Services\modulos\CotizadorPlanesService;

/**
 * Cotizador de Planes (modulos/cotizador-planes).
 * El vendedor arma el precio del sistema según el RUC del cliente, sus establecimientos,
 * módulos y usuarios, y genera una Proforma enlazada a una oportunidad del CRM.
 * Solo recibe, valida lo básico, delega al Service y responde (CLAUDE.md §3).
 * Diseño: docs/diseno/cotizador-planes-fase1.md · Manual: docs/manual/modulos/cotizador-planes.md
 */
class CotizadorPlanesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/cotizador-planes';
    /** La configuración y el catálogo de precios solo los administran estos niveles. */
    private const NIVEL_CONFIGURACION = 2;

    private CotizadorPlanesService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = CotizadorPlanesService::crear();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    private function idEmpresa(): int
    {
        return (int) $_SESSION['id_empresa'];
    }

    private function idUsuario(): int
    {
        return (int) $_SESSION['id_usuario'];
    }

    private function esAdministrador(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 1) >= self::NIVEL_CONFIGURACION;
    }

    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? $this->idUsuario() : null;
    }

    private function cuerpo(): array
    {
        $raw = file_get_contents('php://input');
        $json = $raw !== '' && $raw !== false ? json_decode($raw, true) : null;
        return is_array($json) ? $json : $_POST;
    }

    private function requireAdministrador(): void
    {
        if (!$this->esAdministrador()) {
            $this->json(['ok' => false, 'error' => 'Solo un administrador puede cambiar la configuración del cotizador.'], 403);
        }
    }

    private function requireDisponible(): void
    {
        if (!$this->service->disponible()) {
            $this->json(['ok' => false, 'error' => 'Falta ejecutar el SQL del módulo (database/2026-11-13_cotizador_planes.sql).'], 409);
        }
    }

    /** Cotización accesible para el usuario: sin acceso total, solo las suyas (§6). */
    private function fichaPropia(int $id): array
    {
        $c = $id > 0 ? $this->service->getFicha($id, $this->idEmpresa()) : null;
        $filtro = $this->idUsuarioFiltro();
        if (!$c || ($filtro !== null && (int) $c['created_by'] !== $filtro)) {
            $this->json(['ok' => false, 'error' => 'La cotización no existe o no tiene acceso a ella.'], 404);
        }
        return $c;
    }

    private function errorJson(\Throwable $e): never
    {
        if (!($e instanceof \InvalidArgumentException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class]);
        }
        $this->json(['ok' => false, 'error' => $e->getMessage()]);
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $perPage = $this->porPagina();
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefsVista, 'numero', 'DESC');
        $buscar = trim((string) ($_GET['b'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $disponible = $this->service->disponible();

        $result = $disponible
            ? $this->service->getListado($this->idEmpresa(), $buscar, $page, $perPage, $orden, $this->idUsuarioFiltro())
            : ['rows' => [], 'total' => 0];

        $this->viewWithLayout('layouts.main', 'modulos.cotizador_planes.index', [
            'titulo'      => 'Cotizador de Planes',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => self::RUTA_MODULO,
            'disponible'  => $disponible,
            'esAdmin'     => $this->esAdministrador(),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => $perPage > 0 ? max(1, (int) ceil($result['total'] / $perPage)) : 1,
            'buscar'      => $buscar,
            'ordenJson'   => OrdenListado::aJson($orden),
            'ordenParam'  => OrdenListado::aCadena($orden),
            'vistaConfig' => $prefsVista,
            'vendedores'  => $disponible ? $this->service->getVendedores($this->idEmpresa()) : [],
            'puedeProforma' => !empty(\App\Helpers\Permisos::porRuta('modulos/proformas')['crear']),
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        $this->requireDisponible();
        $perPage = $this->porPagina();
        $orden = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), 'numero', 'DESC');
        $buscar = trim((string) ($_GET['b'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $res = $this->service->getListado($this->idEmpresa(), $buscar, $page, $perPage, $orden, $this->idUsuarioFiltro());
        $total = (int) $res['total'];
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to = $total > 0 ? min($page * $perPage, $total) : 0;
        $q = '?b=' . urlencode($buscar) . '&orden=' . urlencode(OrdenListado::aCadena($orden));

        $this->json([
            'ok'         => true,
            'rows'       => self::filasHtml($res['rows']),
            'pagination' => '<button type="button" class="btn btn-outline-secondary" ' . ($page <= 1 ? 'disabled' : '') . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
                          . '<button type="button" class="btn btn-outline-secondary" ' . ($page >= $totalPages ? 'disabled' : '') . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>',
            'info'       => "{$from}-{$to}/{$total}",
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf' . $q,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel' . $q,
        ]);
    }

    /** Filas del listado (las usan la vista inicial y searchAjax). */
    public static function filasHtml(array $rows): string
    {
        if ($rows === []) {
            return '<tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-calculator fs-3 d-block mb-2"></i>No hay cotizaciones.</td></tr>';
        }
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m = fn($v) => '$' . number_format((float) $v, 2);
        $estados = [
            'borrador' => ['Borrador', 'secondary'],
            'proforma' => ['Proforma generada', 'success'],
            'anulada'  => ['Anulada', 'danger'],
        ];
        $html = '';
        foreach ($rows as $r) {
            [$txt, $color] = $estados[$r['estado']] ?? [$r['estado'], 'secondary'];
            $fecha = !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '';
            $creado = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['created_at'])) : '';
            $html .= '<tr class="cp-row" role="button" tabindex="0" onclick="CP_abrir(' . (int) $r['id'] . ')">'
                . '<td class="ps-3" data-col="numero"><code class="text-secondary">' . (int) $r['numero'] . '</code></td>'
                . '<td data-col="fecha" class="small" title="Registrada el ' . $e($creado) . '">' . $e($fecha) . '</td>'
                . '<td data-col="identificacion" class="small">' . $e($r['identificacion']) . '</td>'
                . '<td data-col="razon_social" class="fw-medium cp-celda" style="max-width:280px;" title="' . $e($r['razon_social']) . '">' . $e($r['razon_social']) . '</td>'
                . '<td data-col="establecimientos" class="text-center">' . (int) $r['establecimientos'] . '</td>'
                . '<td data-col="usuarios" class="text-center">' . (int) $r['usuarios'] . '</td>'
                . '<td data-col="modalidad" class="text-capitalize">' . $e($r['modalidad']) . '</td>'
                . '<td data-col="total_anual" class="text-end">' . $m($r['total_anual']) . '</td>'
                . '<td data-col="total_mensual" class="text-end">' . $m($r['total_mensual']) . '</td>'
                . '<td data-col="total_unico" class="text-end">' . $m($r['total_unico']) . '</td>'
                . '<td data-col="vendedor" class="small cp-celda" style="max-width:160px;">' . $e($r['vendedor'] ?? '') . '</td>'
                . '<td data-col="estado" class="text-center pe-3"><span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color
                . ' border border-' . $color . ' border-opacity-25">' . $e($txt) . '</span></td>'
                . '</tr>';
        }
        return $html;
    }

    // ── Cotización ───────────────────────────────────────────────────────────

    /** Catálogo activo y parámetros para armar una cotización. */
    public function getCatalogoAjax(): void
    {
        $this->requireLeer();
        $this->requireDisponible();
        $cfg = $this->service->getConfig($this->idEmpresa());
        $this->json([
            'ok'        => true,
            'items'     => $this->service->getCatalogo($this->idEmpresa(), true),
            'parametros'=> [
                'recargo_mensual_pct'            => $cfg['recargo_mensual_pct'],
                'descuento_establecimiento_pct'  => $cfg['descuento_establecimiento_pct'],
                'usuarios_por_establecimiento'   => $cfg['usuarios_por_establecimiento'],
                'precio_usuario_adicional_anual' => $cfg['precio_usuario_adicional_anual'],
                'permitir_descuento_vendedor'    => $cfg['permitir_descuento_vendedor'],
                'descuento_vendedor_max_pct'     => $cfg['descuento_vendedor_max_pct'],
            ],
        ]);
    }

    public function getCotizacionAjax(): void
    {
        $this->requireLeer();
        $this->requireDisponible();
        $c = $this->fichaPropia((int) ($_GET['id'] ?? 0));
        $c['created_at_fmt'] = !empty($c['created_at']) ? date('d-m-Y H:i:s', strtotime((string) $c['created_at'])) : '';
        if (!empty($c['id_proforma'])) {
            $c += $this->service->numeroProforma((int) $c['id_proforma']);
            $c['url_proforma'] = BASE_URL . '/modulos/proformas?b=' . urlencode((string) $c['secuencial_proforma']);
        }
        $this->json(['ok' => true, 'data' => $c]);
    }

    /** Precio en vivo: no guarda nada. */
    public function calcularAjax(): void
    {
        $this->requireLeer();
        $this->requireDisponible();
        try {
            $this->json(['ok' => true, 'calculo' => $this->service->calcular($this->idEmpresa(), $this->cuerpo())]);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    public function guardarAjax(): void
    {
        $this->requireDisponible();
        $d = $this->cuerpo();
        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            $this->requireActualizar();
            $this->fichaPropia($id);
        } else {
            $this->requireCrear();
        }
        try {
            $res = $this->service->guardar($this->idEmpresa(), $d, $this->idUsuario());
            $this->json([
                'ok'  => true,
                'id'  => $res['id'],
                'numero' => $res['numero'],
                'msg' => $res['previo']
                    ? 'Esta cotización ya estaba registrada; no se creó otra.'
                    : ($id > 0 ? 'Cotización actualizada.' : 'Cotización N.º ' . $res['numero'] . ' creada.'),
            ]);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        $this->requireDisponible();
        $id = (int) ($this->cuerpo()['id'] ?? 0);
        $this->fichaPropia($id);
        try {
            $this->service->eliminar($id, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'msg' => 'Cotización eliminada.']);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    /** RUC o cédula → datos del SRI (con sus establecimientos) y si ya es cliente. */
    public function consultarSriAjax(): void
    {
        $this->requireLeer();
        $num = preg_replace('/\D/', '', (string) ($_GET['identificacion'] ?? ''));
        if (!preg_match('/^\d{10}$|^\d{13}$/', $num)) {
            $this->json(['ok' => false, 'error' => 'Escriba un RUC (13 dígitos) o una cédula (10 dígitos).']);
        }
        // No bloquear las demás peticiones del usuario mientras responde el SRI.
        $idEmpresa = $this->idEmpresa();
        session_write_close();
        try {
            $this->json($this->service->consultarIdentificacion($num, $idEmpresa));
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    /** Series (puntos de emisión) con secuencial de Proformas, para generar la proforma. */
    public function getSeriesAjax(): void
    {
        $this->requireLeer();
        $this->json(['ok' => true, 'data' => $this->service->getSeriesProforma($this->idEmpresa())]);
    }

    public function generarProformaAjax(): void
    {
        $this->requireActualizar();
        $this->requireDisponible();
        if (empty(\App\Helpers\Permisos::porRuta('modulos/proformas')['crear'])) {
            $this->json(['ok' => false, 'error' => 'Necesita permiso de crear en Proformas para generar la proforma.'], 403);
        }
        $d = $this->cuerpo();
        $id = (int) ($d['id'] ?? 0);
        $this->fichaPropia($id);
        try {
            $res = $this->service->generarProforma($id, $this->idEmpresa(), $this->idUsuario(), (int) ($d['id_punto_emision'] ?? 0));
            $this->json([
                'ok' => true,
                'id_proforma'    => $res['id_proforma'],
                'id_oportunidad' => $res['id_oportunidad'],
                'numero_proforma' => $res['numero_proforma'],
                'msg' => $res['previo']
                    ? 'La proforma ' . $res['numero_proforma'] . ' ya estaba generada; no se creó otra.'
                    : 'Proforma ' . $res['numero_proforma'] . ' generada' . ($res['id_oportunidad'] ? ' y enlazada a una oportunidad del CRM.' : '.'),
                'aviso' => $res['aviso'],
                'url_proforma' => BASE_URL . '/modulos/proformas?b=' . urlencode($res['secuencial_proforma']),
            ]);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    // ── Configuración (niveles 2 y 3) ────────────────────────────────────────

    public function getConfigAjax(): void
    {
        $this->requireLeer();
        $this->requireAdministrador();
        $this->requireDisponible();
        $this->json([
            'ok'         => true,
            'config'     => $this->service->getConfig($this->idEmpresa()),
            'items'      => $this->service->getCatalogo($this->idEmpresa(), false),
            'submodulos' => $this->service->getSubmodulosMenu(),
        ]);
    }

    public function guardarConfigAjax(): void
    {
        $this->requireLeer();
        $this->requireAdministrador();
        $this->requireDisponible();
        try {
            $this->service->guardarConfig($this->idEmpresa(), $this->cuerpo(), $this->idUsuario());
            $this->json(['ok' => true, 'msg' => 'Parámetros guardados.']);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    public function guardarItemAjax(): void
    {
        $this->requireLeer();
        $this->requireAdministrador();
        $this->requireDisponible();
        try {
            $id = $this->service->guardarItem($this->idEmpresa(), $this->cuerpo(), $this->idUsuario());
            $this->json(['ok' => true, 'id' => $id, 'msg' => 'Ítem guardado.']);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    public function eliminarItemAjax(): void
    {
        $this->requireLeer();
        $this->requireAdministrador();
        $this->requireDisponible();
        try {
            $this->service->eliminarItem((int) ($this->cuerpo()['id'] ?? 0), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'msg' => 'Ítem eliminado del catálogo.']);
        } catch (\Throwable $e) {
            $this->errorJson($e);
        }
    }

    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        $this->requireAdministrador();
        $this->json(['ok' => true, 'data' => $this->service->buscarProductos($this->idEmpresa(), trim((string) ($_GET['q'] ?? '')))]);
    }

    // ── Exportación del listado ──────────────────────────────────────────────

    private function filasExport(): array
    {
        $orden = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), 'numero', 'DESC');
        return $this->service->getListado($this->idEmpresa(), trim((string) ($_GET['b'] ?? '')), 1, 0, $orden, $this->idUsuarioFiltro())['rows'];
    }

    private const COLUMNAS_EXPORT = ['N.º', 'Fecha', 'RUC / cédula', 'Razón social', 'Establec.', 'Usuarios', 'Modalidad', 'Anual', 'Mensual', 'Pago único', 'Vendedor', 'Estado'];

    private function filaExport(array $r): array
    {
        return [
            (int) $r['numero'],
            !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '',
            (string) $r['identificacion'],
            (string) $r['razon_social'],
            (int) $r['establecimientos'],
            (int) $r['usuarios'],
            ucfirst((string) $r['modalidad']),
            (float) $r['total_anual'],
            (float) $r['total_mensual'],
            (float) $r['total_unico'],
            (string) ($r['vendedor'] ?? ''),
            ['borrador' => 'Borrador', 'proforma' => 'Proforma generada', 'anulada' => 'Anulada'][$r['estado']] ?? (string) $r['estado'],
        ];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $this->requireDisponible();
        $empresa = (new \App\models\Empresa())->getPorId($this->idEmpresa());
        $data = array_map(fn($r) => $this->filaExport($r), $this->filasExport());
        (new \App\Services\ReportService())->exportToExcel('Cotizaciones', self::COLUMNAS_EXPORT, $data, 'Cotizador de Planes', $empresa['nombre'] ?? '');
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $this->requireDisponible();
        $empresa = (new \App\models\Empresa())->getPorId($this->idEmpresa());
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $anchos = [5, 8, 11, 22, 6, 6, 7, 8, 8, 7, 7, 5];
        $html = '<style>table{width:100%;border-collapse:collapse;font-family:Arial;font-size:7pt}th{background:#f2f2f2;border:1px solid #ccc;padding:3px;text-align:left}'
              . 'td{border:1px solid #ccc;padding:3px}h1{font-size:12pt;text-align:center;margin:0}h2{font-size:9pt;text-align:center;color:#666;margin:4px 0 10px}</style>'
              . '<page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm"><h1>' . $e($empresa['nombre'] ?? '') . '</h1><h2>COTIZADOR DE PLANES</h2><table><thead><tr>';
        foreach (self::COLUMNAS_EXPORT as $i => $c) {
            $html .= '<th style="width:' . $anchos[$i] . '%">' . $e($c) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($this->filasExport() as $r) {
            $html .= '<tr>';
            foreach ($this->filaExport($r) as $i => $v) {
                $txt = is_float($v) ? number_format($v, 2) : (string) $v;
                $html .= '<td style="width:' . $anchos[$i] . '%' . (is_float($v) ? ';text-align:right' : '') . '">' . $e($txt) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></page>';
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;
        $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
        $pdf->writeHTML($html);
        $pdf->output('Cotizaciones_' . date('Ymd_His') . '.pdf', 'D');
        exit;
    }
}
