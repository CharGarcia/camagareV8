<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Rules\modulos\CondominioRules;
use App\Services\modulos\CondominioService;

/**
 * Configuración de condominios (modulos/condominios-config): único submódulo de Condominios.
 * Pestañas: condominio, condóminos (todos los clientes y sus inmuebles), alícuota y fondo, mora
 * y multas, reajuste de cuotas, descuentos. Permisos: ver = consultar; crear = asignar inmuebles
 * y carga Excel; actualizar = guardar la configuración, editar, restringir y enlazar; eliminar =
 * inmuebles, multas y valores. Guardar la configuración ACTIVA el módulo para la empresa.
 * Registros propios no aplica: la cartera del condominio es una sola (diseño §9).
 * Sin lógica de negocio: delega a CondominioService.
 */
class CondominiosConfigController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/condominios-config';

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

    private function error(\Throwable $e, string $accion): never
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
            $this->json(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado.']);
        }
        $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    public function index(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $instalado = $this->service->instalado();
        $cfg = $instalado ? $this->service->getConfig($idEmpresa) : null;
        // Valores por defecto para un condominio aún sin configurar: el nombre de la empresa y la
        // dirección de su establecimiento principal (el condominio ES la empresa).
        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $defaults = [
            'nombre_condominio' => (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? '')),
            'direccion'         => (string) (($establecimientos[0]['direccion'] ?? '') ?: ($empresa['direccion'] ?? '')),
            // El representante legal de la empresa suele ser quien administra el condominio.
            'administrador_nombre' => (string) ($empresa['nom_rep_legal'] ?? ''),
            'administrador_cedula' => (string) ($empresa['ced_rep_legal'] ?? ''),
        ];
        $prefs = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefs, 'nombre');
        $this->porPagina(); // registra el módulo para el selector de filas (la pestaña carga por AJAX)
        $this->viewWithLayout('layouts.main', 'modulos/condominios_config/index', [
            'ordenJson'     => OrdenListado::aJson($orden),
            'ordenParam'    => OrdenListado::aCadena($orden),
            'tiposUnidad'   => CondominioRules::TIPOS_LABEL,
            'opcionesFiltro' => $instalado && $cfg ? $this->service->repo()->getOpcionesFiltroListado($idEmpresa) : [],
            'titulo'        => 'Configuración de condominios',
            'perm'          => $this->getPermisos(),
            'rutaModulo'    => self::RUTA_MODULO,
            'vistaConfig'   => \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO),
            'instalado'     => $instalado,
            'config'        => $cfg,
            'defaults'      => $defaults,
            'pendientes'    => $instalado ? $this->service->pendientesConfig($cfg) : [],
            'multas'        => $instalado && $cfg ? $this->service->repo()->getMultas($idEmpresa) : [],
            'metodos'       => CondominioRules::METODOS_LABEL,
            'base'          => BASE_URL,
        ]);
    }

    public function guardarAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $cfg = $this->service->guardarConfig($_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'config' => $cfg, 'pendientes' => $this->service->pendientesConfig($cfg),
                         'mensaje' => 'Configuración guardada. El módulo Condominios está activo para esta empresa.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Catálogo de multas: listar (r), guardar (w nueva / u existente), eliminar (d). */
    public function multasAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $accion = (string) ($_POST['accion'] ?? 'listar');
            if ($accion === 'guardar') {
                (int) ($_POST['id'] ?? 0) > 0 ? $this->requireActualizar() : $this->requireCrear();
                $id = $this->service->guardarMulta($_POST, $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'id' => $id, 'multas' => $this->service->repo()->getMultas($idEmpresa), 'mensaje' => 'Multa guardada.']);
            }
            if ($accion === 'eliminar') {
                $this->requireEliminar();
                $this->service->eliminarMulta((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'multas' => $this->service->repo()->getMultas($idEmpresa), 'mensaje' => 'Multa eliminada.']);
            }
            $this->json(['ok' => true, 'multas' => $this->service->repo()->getMultas($idEmpresa)]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Valores que rigen (tarifa por m² / monto a repartir) ─────────────────

    public function valoresAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true, 'valores' => $this->service->listarValores($idEmpresa), 'presupuestos' => $this->service->presupuestosParaValor($idEmpresa)]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Cuota de cada inmueble con el valor propuesto; nada se graba. */
    public function valorPreviewAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->service->previsualizarValor($_POST, $idEmpresa));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function guardarValorAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = $this->service->guardarValor($_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id' => $id, 'valores' => $this->service->listarValores($idEmpresa), 'mensaje' => 'Valor guardado. Rige desde el mes indicado; los recibos de ese mes en adelante salen con la cuota nueva.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function eliminarValorAjax(): void
    {
        $this->requireEliminar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->service->eliminarValor((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'valores' => $this->service->listarValores($idEmpresa), 'mensaje' => 'Valor eliminado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Reajuste masivo de cuotas ────────────────────────────────────────────

    public function reajusteOpcionesAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->service->opcionesReajuste($idEmpresa));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function reajustePreviewAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->service->previsualizarReajuste($_POST, $idEmpresa));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function reajusteAplicarAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $res = $this->service->aplicarReajuste($_POST, $idEmpresa, $idUsuario);
            $msg = $res['programado']
                ? "Reajuste programado para {$res['suscripciones']} suscripción(es). Se aplicará automáticamente ese día."
                : "Reajuste aplicado a {$res['suscripciones']} suscripción(es): {$res['resultado']}";
            $this->json(['ok' => true, 'mensaje' => $msg, 'reajustes' => $this->service->repo()->getReajustes($idEmpresa)] + $res);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function reajusteCancelarAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->service->cancelarReajuste((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Reajuste cancelado.', 'reajustes' => $this->service->repo()->getReajustes($idEmpresa)]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }


    // ── Pestaña Condóminos: todos los clientes y sus inmuebles ───────────────

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

    private function ordenCondominos(): array
    {
        return OrdenListado::leer(\App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), 'nombre');
    }

    /** Filas del listado de condóminos en JSON (el JS las pinta; orden multi-columna sin recarga). */
    public function condominosAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $orden   = $this->ordenCondominos();
            $buscar  = trim((string) ($_GET['b'] ?? ''));
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = $this->porPagina();
            $res     = $this->service->getCondominos($idEmpresa, $buscar, $page, $perPage, $orden);
            $qs      = http_build_query(['b' => $buscar, 'orden' => OrdenListado::aCadena($orden)]);
            $this->json([
                'ok'          => true,
                'rows'        => $res['rows'],
                'total'       => $res['total'],
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => max(1, (int) ceil($res['total'] / max(1, $perPage))),
                'resumen'     => $this->service->instalado() ? $this->service->repo()->getResumenCondominos($idEmpresa) : null,
                'excel_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel?' . $qs,
                'pdf_url'     => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

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
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Inmueble asignado.']);
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

    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $repo = new \App\repositories\modulos\ClienteRepository();
        $res = $repo->getListado($idEmpresa, trim((string) ($_GET['q'] ?? '')), 1, 12, 'nombre', 'ASC', null, true);
        $this->json(['ok' => true, 'rows' => array_map(fn($c) => ['id' => (int) $c['id'], 'nombre' => $c['nombre'], 'identificacion' => $c['identificacion'] ?? '', 'email' => $c['email'] ?? ''], $res['rows'])]);
    }

    // ── Exportaciones del listado de condóminos ──────────────────────────────

    /** Una fila por cliente; sus inmuebles van en una sola celda («DPTO-104 (propietario, paga)»). */
    private function filasExport(int $idEmpresa): array
    {
        $rows = $this->service->getCondominos($idEmpresa, trim((string) ($_GET['b'] ?? '')), 1, 0, $this->ordenCondominos())['rows'];
        foreach ($rows as &$r) {
            $r['inmuebles_texto'] = implode('; ', array_map(
                fn($u) => $u['codigo'] . ($u['nombre'] !== $u['codigo'] ? ' ' . $u['nombre'] : '') . ' (' . $u['rol'] . ($u['paga'] ? ', paga' : '') . ($u['restringida'] ? ', restringida' : '') . ')',
                $r['lista']
            ));
        }
        unset($r);
        return $rows;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $rows = $this->filasExport($idEmpresa);
            $headers = ['Cliente', 'Identificación', 'Correo', 'Teléfono', 'N.º inmuebles', 'Inmuebles', 'Σ alícuota % (propietario)', 'Restringido'];
            $data = array_map(fn($r) => [
                $r['nombre'], $r['identificacion'] ?? '', $r['email'] ?? '', $r['telefono'] ?? '', $r['inmuebles'], $r['inmuebles_texto'],
                (float) $r['suma_pct'], $r['restringida'] ? 'Sí' : 'No',
            ], $rows);
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            (new \App\Services\ReportService())->exportToExcel('Condominos', $headers, $data, 'Condóminos', $empresa['nombre'] ?? '');
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
            $html = '<style>table{border-collapse:collapse;font-family:Arial;font-size:8pt}th{background:#f2f2f2;border:1px solid #ccc;padding:4px;text-align:left}td{border:1px solid #ccc;padding:4px}h1{margin:0;font-size:13pt;text-align:center}h2{margin:4px 0 12px;font-size:9pt;text-align:center;color:#666;text-transform:uppercase}</style>'
                . '<page orientation="landscape" backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">'
                . '<h1>' . $h($empresa['nombre'] ?? '') . '</h1><h2>Condóminos</h2><table><thead><tr>'
                . '<th style="width:60mm">Cliente</th><th style="width:30mm">Identificación</th><th style="width:45mm">Correo</th><th style="width:25mm">Teléfono</th><th style="width:100mm">Inmuebles</th><th style="width:15mm;text-align:right">% prop.</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $html .= '<tr><td style="width:60mm">' . $h($r['nombre']) . '</td><td style="width:30mm">' . $h($r['identificacion']) . '</td><td style="width:45mm">' . $h($r['email']) . '</td><td style="width:25mm">' . $h($r['telefono']) . '</td>'
                    . '<td style="width:100mm">' . ($r['inmuebles_texto'] !== '' ? $h($r['inmuebles_texto']) : '—') . '</td><td style="width:15mm;text-align:right">' . number_format((float) $r['suma_pct'], 4) . '</td></tr>';
            }
            $html .= '</tbody></table></page>';
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Condominos_' . date('Ymd_His') . '.pdf', 'D');
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
            (new \App\Services\ReportService())->descargarSpreadsheet($this->service->plantillaExcel($idEmpresa), 'Plantilla_Inmuebles_Condominio');
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

    // ── Cobros desde Condóminos: emitir en bloque / agregar a suscripciones ──

    private function cobros(): \App\Services\modulos\CondominioCobroService
    {
        return \App\Services\modulos\CondominioCobroService::crear();
    }

    /** Series, periodicidades, multas del catálogo y últimas emisiones (modal «Generar cobro»). */
    public function cobroOpcionesAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->cobros()->opciones($idEmpresa));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Vista previa: qué documento o qué cambio de suscripción le toca a cada condómino. Nada se graba. */
    public function cobroPreviewAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->cobros()->previsualizar($_POST, $idEmpresa));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Crea la emisión en bloque y lanza su proceso en segundo plano. */
    public function emitirAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $res = $this->cobros()->crearEmision($_POST, $idEmpresa, $idUsuario, (string) ($_POST['token_guardado'] ?? ''));
            $this->liberarSesionSiExiste();
            $lanzado = \App\Helpers\ProcesoSegundoPlano::lanzar('procesar_emision_condominios.php', ['emision' => $res['id']]);
            $msg = $res['previo']
                ? 'Esta emisión ya estaba registrada; no se creó otra.'
                : "Emisión creada con {$res['items']} documento(s). " . ($lanzado
                    ? 'Se están generando en segundo plano; puede seguir trabajando.'
                    : 'No se pudo iniciar el proceso ahora; el sistema lo retomará en unos minutos.');
            $this->json(['ok' => true, 'id' => $res['id'], 'previo' => $res['previo'], 'lanzado' => $lanzado, 'mensaje' => $msg]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Agrega el cobro recurrente a las suscripciones (crea, actualiza o agrega línea; nunca duplica). */
    public function suscripcionesAplicarAjax(): void
    {
        $this->requireCrear();
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $n = $this->cobros()->aplicarSuscripciones($_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true] + $n + ['mensaje' => "Listo: {$n['creadas']} suscripción(es) creada(s), {$n['agregadas']} con la línea agregada y {$n['actualizadas']} con el valor actualizado."]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function emisionEstadoAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true] + $this->cobros()->estadoEmision((int) ($_GET['id'] ?? 0), $idEmpresa, !empty($_GET['items'])));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function emisionesAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true, 'emisiones' => $this->cobros()->repo()->instalado() ? $this->cobros()->repo()->getEmisiones($idEmpresa, 30) : []]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function emisionCancelarAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->cobros()->cancelarEmision((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Emisión cancelada. Los documentos ya generados se conservan; los pendientes no se emitirán.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Suelta el candado de la sesión antes de lanzar el proceso (no bloquea las demás peticiones). */
    private function liberarSesionSiExiste(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /** Servicios activos de la empresa para los selectores de concepto (buscador tipo chip). */
    public function buscarServiciosAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $this->json(['ok' => true, 'rows' => $this->service->repo()->buscarServicios($idEmpresa, (string) ($_GET['q'] ?? ''))]);
    }
}
