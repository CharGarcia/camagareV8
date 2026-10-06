<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\Rules\modulos\CondominioRules;
use App\Services\modulos\CondominioService;

/**
 * Condominios (modulos/condominios) — fase 1: inmuebles, historial de propietarios, restricción,
 * enlace de suscripciones y carga Excel. Sin lógica de negocio: valida lo básico, delega al
 * Service y responde.
 *
 * Permisos: ver = consultar; crear = inmuebles y Excel; actualizar = editar, restringir, enlazar;
 * eliminar = eliminar inmuebles. La configuración y el catálogo de multas viven en su propio
 * submódulo (modulos/condominios-config, CondominiosConfigController). Registros propios no aplica a inmuebles: la cartera del condominio es una sola
 * (decisión de diseño, docs/diseno/condominios-fase1.md §9).
 */
class CondominiosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/condominios';

    private CondominioService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = CondominioService::crear();
    }

    private function sesion(): array
    {
        return [(int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']];
    }

    /** Respuesta uniforme de error: los de negocio se muestran tal cual, el resto se registra. */
    private function error(\Throwable $e, string $accion): never
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
            $this->json(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado.']);
        }
        $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    private function autoload(): void
    {
        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    /** Error en una descarga: JSON con {error} si aún no se envió nada (CMG_descargar lo muestra). */
    private function errorDescarga(\Throwable $e, string $accion): void
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    // ── Listado de inmuebles ──────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $prefs   = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden   = OrdenListado::leer($prefs, 'codigo');
        $buscar  = trim((string) ($_GET['b'] ?? ''));
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->porPagina();

        $instalado = $this->service->instalado();
        $cfg = $instalado ? $this->service->getConfig($idEmpresa) : null;
        $res = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, null);

        $this->viewWithLayout('layouts.main', 'modulos/condominios/index', [
            'titulo'          => 'Inmuebles',
            'perm'            => $this->getPermisos(),
            'rutaModulo'      => self::RUTA_MODULO,
            'vistaConfig'     => $prefs,
            'rows'            => $res['rows'],
            'total'           => $res['total'],
            'page'            => $page,
            'perPage'         => $perPage,
            'totalPages'      => max(1, (int) ceil($res['total'] / max(1, $perPage))),
            'buscar'          => $buscar,
            'ordenJson'       => OrdenListado::aJson($orden),
            'ordenParam'      => OrdenListado::aCadena($orden),
            'instalado'       => $instalado,
            'config'          => $cfg,
            'pendientes'      => $instalado ? $this->service->pendientesConfig($cfg) : [],
            'resumen'         => $instalado && $cfg ? $this->service->repo()->getResumen($idEmpresa) : null,
            'opcionesFiltro'  => $instalado ? $this->service->repo()->getOpcionesFiltroListado($idEmpresa) : [],
            'tiposUnidad'     => CondominioRules::TIPOS_LABEL,
            'metodos'         => CondominioRules::METODOS_LABEL,
            'base'            => BASE_URL,
        ]);
    }

    /** Filas del listado en JSON (el JS las pinta; sin recarga, porque el orden es multi-columna). */
    public function searchAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $prefs   = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
            $orden   = OrdenListado::leer($prefs, 'codigo');
            $buscar  = trim((string) ($_GET['b'] ?? ''));
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = $this->porPagina();
            $res     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, null);
            $qs      = http_build_query(['b' => $buscar, 'orden' => OrdenListado::aCadena($orden)]);
            $this->json([
                'ok'          => true,
                'rows'        => $res['rows'],
                'total'       => $res['total'],
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => max(1, (int) ceil($res['total'] / max(1, $perPage))),
                'resumen'     => $this->service->repo()->getResumen($idEmpresa),
                'excel_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel?' . $qs,
                'pdf_url'     => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Inmuebles ─────────────────────────────────────────────────────────────

    public function getAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->service->getUnidad((int) ($_GET['id'] ?? 0), $idEmpresa));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function storeAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = $this->service->crearUnidad($_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Inmueble creado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function updateAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->actualizarUnidad($id, $_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Inmueble actualizado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->service->eliminarUnidad((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Inmueble eliminado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function restringirAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $restringir = in_array($_POST['restringir'] ?? '1', ['1', 'true', 'si'], true);
            $this->service->restringir((int) ($_POST['id'] ?? 0), $restringir, (string) ($_POST['fecha'] ?? ''), (string) ($_POST['motivo'] ?? ''), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => $restringir ? 'Restricción de áreas comunes registrada.' : 'Restricción levantada.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function enlazarSuscripcionAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->service->enlazarSuscripcion((int) ($_POST['id'] ?? 0), (int) ($_POST['id_suscripcion'] ?? 0), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => (int) ($_POST['id_suscripcion'] ?? 0) > 0 ? 'Suscripción enlazada al inmueble.' : 'Suscripción desenlazada.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Buscadores (chips) ───────────────────────────────────────────────────

    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $repo = new \App\repositories\modulos\ClienteRepository();
        $res = $repo->getListado($idEmpresa, trim((string) ($_GET['q'] ?? '')), 1, 12, 'nombre', 'ASC', null, true);
        $this->json(['ok' => true, 'rows' => array_map(fn($c) => ['id' => (int) $c['id'], 'nombre' => $c['nombre'], 'identificacion' => $c['identificacion'] ?? '', 'email' => $c['email'] ?? ''], $res['rows'])]);
    }

    // ── Exportaciones del listado ────────────────────────────────────────────

    private function filasExport(int $idEmpresa): array
    {
        $prefs = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefs, 'codigo');
        return $this->service->getListado($idEmpresa, trim((string) ($_GET['b'] ?? '')), 1, 0, $orden, null)['rows'];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $rows = $this->filasExport($idEmpresa);
            $headers = ['Código', 'Nombre', 'Tipo', 'Torre/Bloque', 'Piso', 'Área m²', 'Alícuota %', 'Propietario', 'Identificación', 'Arrendatario', 'Pagador', 'Método', 'Monto manual', 'Cuota estimada', 'Comprobante', 'Restringida', 'Estado'];
            $data = array_map(fn($r) => [
                $r['codigo'], $r['nombre'], $r['tipo_label'], $r['torre_bloque'] ?? '', $r['piso'] ?? '', (float) $r['area_m2'], (float) $r['alicuota_pct'],
                $r['propietario_nombre'] ?? '', $r['propietario_identificacion'] ?? '', $r['arrendatario_nombre'] ?? '', $r['pagador'],
                $r['metodo_label'], $r['monto_manual'] !== null ? (float) $r['monto_manual'] : '', $r['cuota_estimada'] ?? '',
                $r['comprobante_efectivo'] ?? '', $r['restringida'] ? 'Sí' : 'No', $r['estado'],
            ], $rows);
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            (new \App\Services\ReportService())->exportToExcel('Unidades_Condominio', $headers, $data, 'Inmuebles', $empresa['nombre'] ?? '');
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $rows = $this->filasExport($idEmpresa);
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
            $html = '<style>table{width:100%;border-collapse:collapse;font-family:Arial;font-size:8pt}th{background:#f2f2f2;border:1px solid #ccc;padding:4px;text-align:left}td{border:1px solid #ccc;padding:4px}h1{margin:0;font-size:13pt;text-align:center}h2{margin:4px 0 12px;font-size:9pt;text-align:center;color:#666;text-transform:uppercase}</style>'
                . '<page orientation="landscape" backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">'
                . '<h1>' . $h($empresa['nombre'] ?? '') . '</h1><h2>Inmuebles del condominio</h2><table><thead><tr>'
                . '<th>Código</th><th>Nombre</th><th>Tipo</th><th>Torre</th><th>Piso</th><th style="text-align:right">m²</th><th style="text-align:right">%</th><th>Propietario</th><th>Arrendatario</th><th>Pagador</th><th>Método</th><th style="text-align:right">Cuota</th><th>Estado</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $html .= '<tr><td>' . $h($r['codigo']) . '</td><td>' . $h($r['nombre']) . '</td><td>' . $h($r['tipo_label']) . '</td><td>' . $h($r['torre_bloque']) . '</td><td>' . $h($r['piso']) . '</td>'
                    . '<td style="text-align:right">' . number_format((float) $r['area_m2'], 2) . '</td><td style="text-align:right">' . number_format((float) $r['alicuota_pct'], 4) . '</td>'
                    . '<td>' . $h($r['propietario_nombre']) . '</td><td>' . $h($r['arrendatario_nombre']) . '</td><td>' . $h($r['pagador']) . '</td><td>' . $h($r['metodo_label']) . '</td>'
                    . '<td style="text-align:right">' . ($r['cuota_estimada'] === null ? '—' : number_format((float) $r['cuota_estimada'], 2)) . '</td>'
                    . '<td>' . $h($r['estado']) . ($r['restringida'] ? ' (restringida)' : '') . '</td></tr>';
            }
            $html .= '</tbody></table></page>';
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Unidades_Condominio_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    /** Listado imprimible de inmuebles con restricción de áreas comunes (administración / guardianía). */
    public function pdfRestringidas(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $rows = $this->service->repo()->getRestringidas($idEmpresa);
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
            $html = '<style>table{width:100%;border-collapse:collapse;font-family:Arial;font-size:9pt}th{background:#f2f2f2;border:1px solid #ccc;padding:5px;text-align:left}td{border:1px solid #ccc;padding:5px}h1{margin:0;font-size:14pt;text-align:center}h2{margin:4px 0 12px;font-size:10pt;text-align:center;color:#666}</style>'
                . '<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm"><h1>' . $h($empresa['nombre'] ?? '') . '</h1>'
                . '<h2>Inmuebles con restricción de uso de áreas comunes · al ' . date('d-m-Y') . '</h2><table><thead><tr><th>Inmueble</th><th>Torre / Piso</th><th>Propietario</th><th>Arrendatario</th><th>Desde</th><th>Motivo notificado</th></tr></thead><tbody>';
            if (!$rows) {
                $html .= '<tr><td colspan="6" style="text-align:center">No hay inmuebles restringidos.</td></tr>';
            }
            foreach ($rows as $r) {
                $html .= '<tr><td>' . $h($r['codigo'] . ' · ' . $r['nombre']) . '</td><td>' . $h(trim(($r['torre_bloque'] ?? '') . ' ' . ($r['piso'] ?? ''))) . '</td><td>' . $h($r['propietario_nombre']) . '</td><td>' . $h($r['arrendatario_nombre']) . '</td>'
                    . '<td>' . ($r['restringida_desde'] ? date('d-m-Y', strtotime($r['restringida_desde'])) : '') . '</td><td>' . $h($r['restringida_motivo']) . '</td></tr>';
            }
            $html .= '</tbody></table></page>';
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Restricciones_' . date('Ymd') . '.pdf', 'I');
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    // ── Excel de inmuebles: plantilla, vista previa y aplicación ─────────────

    public function plantillaExcel(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            (new \App\Services\ReportService())->descargarSpreadsheet($this->service->plantillaExcel($idEmpresa), 'Plantilla_Unidades_Condominio');
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    public function importarExcelAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $archivo = $_FILES['archivo'] ?? null;
            if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('No se recibió el archivo.');
            }
            $ext = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                throw new \RuntimeException('El archivo debe ser un Excel (.xlsx).');
            }
            if ($archivo['size'] > 10 * 1024 * 1024) {
                throw new \RuntimeException('El archivo excede los 10 MB.');
            }
            $dir = MVC_ROOT . '/storage/condominios/' . $idEmpresa;
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('No se pudo preparar la carpeta temporal.');
            }
            $ruta = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
                throw new \RuntimeException('No se pudo guardar el archivo subido.');
            }
            try {
                $res = $this->service->leerExcel($ruta, $idEmpresa);
            } finally {
                @unlink($ruta);
            }
            $this->json(['ok' => true] + $res);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function aplicarExcelAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $filas = json_decode((string) ($_POST['filas'] ?? '[]'), true);
            if (!is_array($filas) || !$filas) {
                throw new \InvalidArgumentException('No hay filas para cargar.');
            }
            $res = $this->service->aplicarExcel($filas, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => "Carga lista: {$res['creadas']} inmueble(s) creado(s), {$res['actualizadas']} actualizado(s), {$res['clientes_nuevos']} cliente(s) nuevo(s)."] + $res);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }
}
