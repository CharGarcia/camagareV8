<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\CatalogoAdi;
use App\Helpers\PreferenciasHelper;
use App\repositories\modulos\AnexoDividendosRepository;
use App\Rules\modulos\AnexoDividendosRules;
use App\Services\ErrorLogService;
use App\Services\LogSistemaService;
use App\Services\modulos\AnexoDividendosService;
use App\Services\Xml\XmlAnexoDividendosService;

/**
 * Anexo de Dividendos (ADI).
 * Ruta MVC: modulos/anexo-dividendos (submodulos_menu.ruta = 'modulos/anexo-dividendos').
 *
 * Genera el archivo ADI-aaaa.xml del período con la información de utilidades y
 * los dividendos distribuidos a cada beneficiario, a partir de los asientos
 * contables del año y de las correcciones que haga el usuario.
 */
class AnexoDividendosController extends BaseModuloController
{
    private AnexoDividendosService $service;
    private AnexoDividendosRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/anexo-dividendos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo = new AnexoDividendosRepository();
        $this->service = new AnexoDividendosService(
            $this->repo,
            new AnexoDividendosRules(),
            new LogSistemaService(),
            new XmlAnexoDividendosService()
        );
    }

    /** Registros por página del listado. */
    private const POR_PAGINA = 20;

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm      = $this->getPermisos();

        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'anio');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));

        $listo  = $this->repo->tablasListas();
        $result = $listo
            ? $this->service->getListado($idEmpresa, $buscar, $page, self::POR_PAGINA, $ordenCol, $ordenDir, $this->filtroPropios($perm))
            : ['total' => 0, 'rows' => []];

        $total      = (int) $result['total'];
        $totalPages = (int) ceil($total / self::POR_PAGINA) ?: 1;

        $this->viewWithLayout('layouts.main', 'modulos/anexo_dividendos/index', [
            'titulo'      => 'Anexo de Dividendos (ADI)',
            'perm'        => $perm,
            // Las filas van ya renderizadas: searchAjax devuelve exactamente
            // este mismo HTML, y tenerlo en un solo sitio evita que la carga
            // inicial y la recarga por AJAX se desincronicen.
            'rowsHtml'    => $this->filasHtml($result['rows']),
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => self::POR_PAGINA,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'anios'       => $listo ? $this->service->getAniosDisponibles($idEmpresa) : [],
            'defaults'    => $this->defaultsInformante($idEmpresa),
            // Salario básico por año: la pantalla lo muestra al cambiar el
            // período sin volver al servidor.
            'sbus'        => $listo ? $this->repo->getSalariosBasicos() : [],
            'rutaModulo'  => $this->getRutaModulo(),
            'sinTablas'   => !$listo,
            'catalogo'    => $this->catalogoParaVista(),
            'fullWidth'   => true,
        ]);
    }

    /** POST/GET: recarga la tabla con búsqueda, orden y paginación. */
    public function searchAjax(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $perm       = $this->getPermisos();
        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'anio');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));

        $result = $this->repo->tablasListas()
            ? $this->service->getListado($idEmpresa, $buscar, $page, self::POR_PAGINA, $ordenCol, $ordenDir, $this->filtroPropios($perm))
            : ['total' => 0, 'rows' => []];

        $total      = (int) $result['total'];
        $totalPages = (int) ceil($total / self::POR_PAGINA) ?: 1;
        $desde      = $total > 0 ? (($page - 1) * self::POR_PAGINA) + 1 : 0;
        $hasta      = $total > 0 ? min($page * self::POR_PAGINA, $total) : 0;

        $urlModulo = rtrim(BASE_URL, '/') . '/' . $this->getRutaModulo();
        $qs        = '?b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);

        $this->json([
            'ok'         => true,
            'rows'       => $this->filasHtml($result['rows']),
            'pagination' => $this->paginacionHtml($page, $totalPages),
            'info'       => "{$desde}-{$hasta}/{$total}",
            'total'      => $total,
            'pdf_url'    => $urlModulo . '/export-pdf' . $qs,
            'excel_url'  => $urlModulo . '/export-excel' . $qs,
        ]);
    }

    /** POST: abre (o crea) el anexo del año indicado y devuelve su contenido. */
    public function abrirAjax(): void
    {
        $this->requireCrear();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio      = (int) ($_POST['anio'] ?? 0);
        if ($anio <= 0) {
            $this->json(['ok' => false, 'mensaje' => 'Seleccione el año a informar.'], 422);
        }

        try {
            // Si el período ya está registrado se devuelve tal cual y se avisa:
            // la pantalla no debe pisar su cabecera con un formulario en blanco
            // (el año puede estar en otra página del listado y no verse).
            $existente = $this->repo->getPorAnio($idEmpresa, $anio, $this->repo->getTipoAmbiente($idEmpresa));
            if ($existente !== null) {
                $this->json([
                    'ok'         => true,
                    'ya_existia' => true,
                    'mensaje'    => 'El anexo del año ' . $anio . ' ya estaba registrado; se abrió el existente.',
                    'data'       => $this->paquete((int) $existente['id']),
                ]);
            }

            $anexo = $this->service->abrirAnexo($idEmpresa, (int) $_SESSION['id_usuario'], $anio);
            $this->json([
                'ok'         => true,
                'ya_existia' => false,
                'data'       => $this->paquete((int) $anexo['id']),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    /** GET: contenido completo de un anexo (?id=). */
    public function detalleAjax(): void
    {
        $this->requireLeer();

        try {
            $this->json(['ok' => true, 'data' => $this->paquete((int) ($_GET['id'] ?? 0))]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    /** POST: guarda el informante, la sección B y el origen contable. */
    public function guardarCabeceraAjax(): void
    {
        $this->requireActualizar();

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->guardarCabecera(
                $id,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $_POST
            );
            $this->json(['ok' => true, 'mensaje' => 'Anexo guardado.', 'data' => $this->paquete($id)]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    /** POST: arma el detalle del anexo con los asientos del año. */
    public function importarAjax(): void
    {
        $this->requireActualizar();

        try {
            $id  = (int) ($_POST['id'] ?? 0);
            $res = $this->service->importarDesdeContabilidad(
                $id,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            $this->json([
                'ok'          => true,
                'mensaje'     => $res['importados'] . ' dividendo(s) importados desde la contabilidad.',
                'importados'  => $res['importados'],
                'sin_tercero' => $res['sin_tercero'],
                'notas'       => $res['mensajes'],
                'data'        => $this->paquete($id),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    /** POST: recalcula ingreso gravado, retención y la sección B. */
    /**
     * POST: rehace la sección B con la contabilidad del año (pestaña Utilidades).
     */
    public function recalcularUtilidadesAjax(): void
    {
        $this->requireActualizar();

        try {
            $id    = (int) ($_POST['id'] ?? 0);
            $notas = $this->service->recalcularSeccionB(
                $id,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );

            $this->json([
                'ok'      => true,
                'mensaje' => 'Sección de utilidades recalculada con la contabilidad del año.',
                'notas'   => $notas,
                'data'    => $this->paquete($id),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    /**
     * POST: recalcula el ingreso gravado y la retención de cada dividendo
     * (pestaña Dividendos).
     */
    public function recalcularImpuestosAjax(): void
    {
        $this->requireActualizar();

        try {
            $id           = (int) ($_POST['id'] ?? 0);
            $actualizados = $this->service->recalcularImpuestos(
                $id,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );

            $this->json([
                'ok'      => true,
                'mensaje' => 'Se recalcularon el ingreso gravado y la retención de ' . $actualizados . ' dividendo(s).',
                'notas'   => [],
                'data'    => $this->paquete($id),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // ── Beneficiarios ────────────────────────────────────────────────────────

    public function guardarBeneficiarioAjax(): void
    {
        if (!empty($_POST['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }

        try {
            $this->service->guardarBeneficiario(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $_POST
            );
            $this->json([
                'ok'      => true,
                'mensaje' => 'Beneficiario guardado.',
                'data'    => $this->paquete((int) ($_POST['id_anexo'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function eliminarBeneficiarioAjax(): void
    {
        $this->requireEliminar();

        try {
            $this->service->eliminarBeneficiario(
                (int) ($_POST['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            $this->json([
                'ok'      => true,
                'mensaje' => 'Beneficiario eliminado junto con sus dividendos.',
                'data'    => $this->paquete((int) ($_POST['id_anexo'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // ── Detalle de la distribución ───────────────────────────────────────────

    public function guardarDetalleAjax(): void
    {
        if (!empty($_POST['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }

        try {
            $this->service->guardarDetalle(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $_POST
            );
            $this->json([
                'ok'      => true,
                'mensaje' => 'Dividendo guardado.',
                'data'    => $this->paquete((int) ($_POST['id_anexo'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function eliminarDetalleAjax(): void
    {
        $this->requireEliminar();

        try {
            $this->service->eliminarDetalle(
                (int) ($_POST['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            $this->json([
                'ok'      => true,
                'mensaje' => 'Dividendo eliminado.',
                'data'    => $this->paquete((int) ($_POST['id_anexo'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // ── Apoyos de la pantalla ────────────────────────────────────────────────

    /** GET: buscador de clientes, proveedores y empleados (?q=). */
    public function buscarTercerosAjax(): void
    {
        $this->requireLeer();

        $this->json([
            'ok'   => true,
            'data' => $this->service->buscarTerceros((int) $_SESSION['id_empresa'], (string) ($_GET['q'] ?? '')),
        ]);
    }

    /** GET: plan de cuentas con la selección de origen contable (?id=). */
    public function cuentasAjax(): void
    {
        $this->requireLeer();

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $anexo     = $this->repo->getPorId((int) ($_GET['id'] ?? 0), $idEmpresa);
            if ($anexo === null) {
                throw new \RuntimeException('El anexo no existe o no pertenece a la empresa activa.');
            }
            $this->json(['ok' => true, 'data' => $this->service->getCuentasParaConfiguracion($idEmpresa, $anexo)]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // ── Generación y descarga ────────────────────────────────────────────────

    public function generarAjax(): void
    {
        $this->requireLeer();

        try {
            $id  = (int) ($_POST['id'] ?? 0);
            $res = $this->service->generar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'Error al generar el anexo: ' . $e->getMessage()], 500);
        }

        if (empty($res['ok'])) {
            $this->json([
                'ok'           => false,
                'mensaje'      => $res['mensaje'] ?? 'No se pudo generar el anexo.',
                'errores'      => $res['errores'] ?? [],
                'advertencias' => $res['advertencias'] ?? [],
            ], 400);
        }

        $urlBase = rtrim(BASE_URL, '/') . '/' . $this->getRutaModulo() . '/descargar?archivo=';
        $this->json([
            'ok'            => true,
            'registros'     => $res['registros'],
            'beneficiarios' => $res['beneficiarios'],
            'xml'           => $res['nombre_xml'],
            'url_xml'       => $urlBase . urlencode($res['nombre_xml']),
            'zip'           => $res['nombre_zip'],
            'url_zip'       => $res['nombre_zip'] ? $urlBase . urlencode($res['nombre_zip']) : null,
            'errores'       => $res['errores'],
            'advertencias'  => $res['advertencias'],
            'data'          => $this->paquete((int) ($_POST['id'] ?? 0)),
        ]);
    }

    /** GET: descarga el archivo generado (?archivo=ADI-aaaa.xml|zip). */
    public function descargar(): void
    {
        $this->requireLeer();

        $nombre = (string) ($_GET['archivo'] ?? '');
        $ruta   = $this->service->rutaArchivo((int) $_SESSION['id_empresa'], $nombre);
        if ($ruta === null) {
            http_response_code(404);
            echo 'Archivo no encontrado. Genere el anexo nuevamente.';
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: ' . (str_ends_with($nombre, '.zip') ? 'application/zip' : 'application/xml'));
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . filesize($ruta));
            header('Cache-Control: no-store');
        }
        readfile($ruta);
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();

        try {
            $this->service->eliminar(
                (int) ($_POST['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            $this->json(['ok' => true, 'mensaje' => 'Anexo eliminado.']);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // ── Exportaciones ────────────────────────────────────────────────────────

    /** GET: PDF del listado con los mismos filtros y orden de la pantalla. */
    public function exportPdf(): void
    {
        $this->requireLeer();

        $rows          = $this->filasParaExportar();
        $nombreEmpresa = $this->nombreEmpresa();

        try {
            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .num { text-align: right; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Anexos de Dividendos (ADI)</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 8%">Año</th>
                            <th style="width: 16%">Identificación</th>
                            <th style="width: 30%">Informante</th>
                            <th style="width: 10%">Benef.</th>
                            <th style="width: 12%">Distribuido</th>
                            <th style="width: 12%">Retención</th>
                            <th style="width: 12%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= (int) $r['anio'] ?></td>
                                <td><?= htmlspecialchars((string) $r['id_informante']) ?></td>
                                <td><?= htmlspecialchars((string) $r['razon_social']) ?></td>
                                <td class="num"><?= (int) $r['total_beneficiarios'] ?></td>
                                <td class="num"><?= number_format((float) $r['total_distribuido'], 2) ?></td>
                                <td class="num"><?= number_format((float) $r['total_retencion'], 2) ?></td>
                                <td><?= htmlspecialchars($this->etiquetaEstado((string) $r['estado'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $contenido = (string) ob_get_clean();

            // Html2Pdf (no Dompdf): es el generador que usan los demás listados.
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $pdf->writeHTML($contenido);
            $pdf->output('Anexos_Dividendos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar el PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    /** GET: Excel del listado con los mismos filtros y orden de la pantalla. */
    public function exportExcel(): void
    {
        $this->requireLeer();

        $rows = $this->filasParaExportar();

        try {
            $encabezados = ['Año', 'Tipo informante', 'Identificación', 'Informante',
                            'Beneficiarios', 'Distribuido', 'Ingreso gravado', 'Retención', 'Estado'];

            $datos = [];
            foreach ($rows as $r) {
                $datos[] = [
                    (int) $r['anio'],
                    CatalogoAdi::TIPO_INFORMANTE[$r['tipo_informante']] ?? (string) $r['tipo_informante'],
                    (string) $r['id_informante'],
                    (string) $r['razon_social'],
                    (int) $r['total_beneficiarios'],
                    round((float) $r['total_distribuido'], 2),
                    round((float) $r['total_gravado'], 2),
                    round((float) $r['total_retencion'], 2),
                    $this->etiquetaEstado((string) $r['estado']),
                ];
            }

            (new \App\Services\ReportService())->exportToExcel(
                'Anexos_Dividendos',
                $encabezados,
                $datos,
                'Anexos de Dividendos (ADI)',
                $this->nombreEmpresa()
            );
            exit;
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar el Excel: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    // ── Apoyos internos ──────────────────────────────────────────────────────

    /** Estructura que consume la pantalla: anexo, detalle, totales y validaciones. */
    private function paquete(int $idAnexo): array
    {
        return $this->service->getAnexoCompleto($idAnexo, (int) $_SESSION['id_empresa']);
    }

    /**
     * Registros propios: sin acceso total, el usuario solo ve los anexos que creó.
     */
    private function filtroPropios(array $perm): ?int
    {
        return empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /** El listado completo, sin paginar, con los filtros de la pantalla. */
    private function filasParaExportar(): array
    {
        if (!$this->repo->tablasListas()) {
            return [];
        }

        $buscar   = trim($_GET['b'] ?? '');
        $ordenCol = trim($_GET['sort'] ?? 'anio');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'desc'));

        return $this->service->getListado(
            (int) $_SESSION['id_empresa'],
            $buscar,
            1,
            0,
            $ordenCol,
            $ordenDir,
            $this->filtroPropios($this->getPermisos())
        )['rows'];
    }

    private function nombreEmpresa(): string
    {
        $empresa = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);

        return (string) ($empresa['nombre'] ?? '');
    }

    /**
     * Datos del informante de la empresa activa. El anexo siempre se presenta a
     * su nombre, así que no se capturan: la pantalla solo los muestra, y el
     * Service los vuelve a leer de la empresa en cada guardado.
     */
    private function defaultsInformante(int $idEmpresa): array
    {
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?: [];
        $ruc     = (string) preg_replace('/\D/', '', (string) ($empresa['ruc'] ?? ''));
        $tipo    = CatalogoAdi::informantePorEmpresa($empresa['tipo'] ?? null, $ruc);

        return [
            'tipo_informante'        => $tipo,
            'tipo_informante_nombre' => CatalogoAdi::TIPO_INFORMANTE[$tipo] ?? '',
            'tipo_id_informante'     => 'R',
            'id_informante'          => $ruc,
            'razon_social'           => trim((string) ($empresa['nombre'] ?? '')),
            // Sin RUC o sin tipo de contribuyente, el anexo saldría mal: la
            // pantalla avisa y enlaza a la configuración de la empresa.
            'sin_ruc'                => $ruc === '',
            'sin_tipo'               => trim((string) ($empresa['tipo'] ?? '')) === '',
        ];
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'generado'   => 'Generado',
            'presentado' => 'Presentado',
            default      => 'Borrador',
        };
    }

    /** Catálogos del SRI que necesita la pantalla para armar sus selectores. */
    private function catalogoParaVista(): array
    {
        return [
            'tipo_identificacion' => CatalogoAdi::TIPO_IDENTIFICACION,
            'tipo_informante'     => CatalogoAdi::TIPO_INFORMANTE,
            'tipo_beneficiario'   => CatalogoAdi::TIPO_BENEFICIARIO,
            'tipo_dividendo'      => CatalogoAdi::TIPO_DIVIDENDO,
            'respuesta'           => CatalogoAdi::RESPUESTA,
            'paises'              => CatalogoAdi::paisesOrdenados(),
            'benef_por_tipo_id'   => CatalogoAdi::BENEFICIARIOS_POR_TIPO_ID,
            'benef_pais_ecuador'  => CatalogoAdi::BENEFICIARIOS_PAIS_ECUADOR,
            'benef_regimen'       => CatalogoAdi::BENEFICIARIOS_REGIMEN_FISCAL,
            'benef_efectivo'      => CatalogoAdi::BENEFICIARIOS_CON_BENEF_EFECTIVO,
        ];
    }

    /**
     * Filas de la tabla. Se renderizan aquí y no en la vista porque searchAjax
     * devuelve exactamente el mismo HTML que pinta el index, y duplicar el
     * marcado en dos sitios los desincroniza a la primera columna que cambie.
     */
    private function filasHtml(array $rows): string
    {
        if ($rows === []) {
            return '<tr><td colspan="9" class="text-center py-5 text-muted">'
                 . '<i class="bi bi-cash-coin fs-3 d-block mb-2"></i>'
                 . 'No se encontraron anexos. Seleccione un año y pulse «Abrir período».</td></tr>';
        }

        $html = '';
        foreach ($rows as $r) {
            $estado  = (string) $r['estado'];
            $badge   = match ($estado) {
                'generado'   => 'bg-success bg-opacity-10 text-success border-success',
                'presentado' => 'bg-primary bg-opacity-10 text-primary border-primary',
                default      => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
            };
            $tipoInf = CatalogoAdi::TIPO_INFORMANTE[$r['tipo_informante']] ?? (string) $r['tipo_informante'];
            $esc     = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

            $html .= '<tr class="adi-row" role="button" tabindex="0" onclick="ADI_abrir(' . (int) $r['id'] . ')">
                <td class="ps-3 fw-semibold" data-col="anio">' . (int) $r['anio'] . '</td>
                <td data-col="id_informante"><code class="text-secondary">' . $esc($r['id_informante']) . '</code></td>
                <td class="fw-medium text-truncate" style="max-width:320px" data-col="razon_social">' . $esc($r['razon_social']) . '</td>
                <td class="text-truncate" style="max-width:200px" data-col="tipo_informante">' . $esc($tipoInf) . '</td>
                <td class="text-center" data-col="total_beneficiarios">' . (int) $r['total_beneficiarios'] . '</td>
                <td class="text-end" data-col="total_distribuido">' . number_format((float) $r['total_distribuido'], 2) . '</td>
                <td class="text-end" data-col="total_gravado">' . number_format((float) $r['total_gravado'], 2) . '</td>
                <td class="text-end" data-col="total_retencion">' . number_format((float) $r['total_retencion'], 2) . '</td>
                <td class="text-center pe-3" data-col="estado">
                    <span class="badge ' . $badge . ' border border-opacity-25">' . $this->etiquetaEstado($estado) . '</span>
                </td>
              </tr>';
        }

        return $html;
    }

    private function paginacionHtml(int $page, int $totalPages): string
    {
        $prev = $page <= 1 ? 'disabled' : '';
        $next = $page >= $totalPages ? 'disabled' : '';

        return '<div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prev . '
                            onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $next . '
                            onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
                </div>';
    }
}
