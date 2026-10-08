<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\CatalogoRdep;
use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\repositories\modulos\AnexoRdepRepository;
use App\Rules\modulos\AnexoRdepRules;
use App\Services\ErrorLogService;
use App\Services\LogSistemaService;
use App\Services\modulos\AnexoRdepService;

/**
 * Anexo RDEP (SRI): retenciones en la fuente bajo relación de dependencia.
 * Solo recibe, valida lo básico, delega al Service y responde.
 */
class AnexoRdepController extends BaseModuloController
{
    private AnexoRdepService $service;
    private const RUTA_MODULO = 'modulos/anexo-rdep';
    private const ORDEN_DEFECTO = 'anio';

    public function __construct()
    {
        parent::__construct();
        $this->service = new AnexoRdepService(new AnexoRdepRepository(), new AnexoRdepRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    private function parametrosListado(): array
    {
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefsVista, self::ORDEN_DEFECTO, 'DESC');
        $perm  = $this->getPermisos();
        return [
            'prefsVista' => $prefsVista,
            'buscar'     => trim($_GET['b'] ?? $_POST['b'] ?? ''),
            'page'       => max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1)),
            'orden'      => $orden,
            'ordenCol'   => OrdenListado::primeraCol($orden, self::ORDEN_DEFECTO),
            'ordenDir'   => OrdenListado::primeraDir($orden, 'DESC'),
            'perm'       => $perm,
            'idUsuarioFiltro' => empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null,
        ];
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $p = $this->parametrosListado();
        $perPage = $this->porPagina();

        $result     = $this->service->getListado($idEmpresa, $p['buscar'], $p['page'], $perPage, $p['ordenCol'], $p['ordenDir'], $p['idUsuarioFiltro'], $p['orden']);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.anexo_rdep.index', [
            'titulo'      => 'Anexo RDEP',
            'instalado'   => $this->service->instalado(),
            'perm'        => $p['perm'],
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $p['page'],
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $p['buscar'],
            'ordenCol'    => $p['ordenCol'],
            'ordenDir'    => $p['ordenDir'],
            'ordenJson'   => OrdenListado::aJson($p['orden']),
            'ordenParam'  => OrdenListado::aCadena($p['orden']),
            'vistaConfig' => $p['prefsVista'],
            'idEmpresa'   => $idEmpresa,
            'fullWidth'   => true,
            'aniosDisponibles' => $this->service->getAniosDisponibles($idEmpresa),
            'establecimientos' => $this->service->getEstablecimientos($idEmpresa),
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $p = $this->parametrosListado();
        $perPage = $this->porPagina();

        $result     = $this->service->getListado($idEmpresa, $p['buscar'], $p['page'], $perPage, $p['ordenCol'], $p['ordenDir'], $p['idUsuarioFiltro'], $p['orden']);
        $total      = $result['total'];
        $page       = $p['page'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-code fs-3 d-block mb-2"></i>No se encontraron anexos.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) echo $this->renderFila($r);
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        $ordenParam = urlencode(OrdenListado::aCadena($p['orden']));
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($p['buscar']) . '&orden=' . $ordenParam,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($p['buscar']) . '&orden=' . $ordenParam,
        ]);
        exit;
    }

    private function renderFila(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $fmt = fn($v) => '$' . number_format((float) $v, 2);
        $colores = ['borrador' => 'secondary', 'generado' => 'success'];
        $c = $colores[$r['estado']] ?? 'secondary';
        $estado = '<span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . ' border-opacity-25">' . $h(ucfirst((string) $r['estado'])) . '</span>';
        $graves = (int) $r['total_graves'];
        $leves  = (int) $r['total_leves'];
        $obs = ($graves > 0 ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 me-1" title="Observaciones graves">' . $graves . '</span>' : '')
             . ($leves > 0 ? '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25" title="Observaciones leves">' . $leves . '</span>' : '')
             . ($graves === 0 && $leves === 0 ? '<i class="bi bi-check-circle text-success"></i>' : '');
        $generado = !empty($r['generado_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['generado_at'])) : '-';

        return '<tr class="rdep-row" role="button" tabindex="0" data-row=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') . '\' onclick="RDEP_abrir(this)">'
            . '<td class="ps-3 fw-medium" data-col="anio">' . (int) $r['anio'] . '</td>'
            . '<td data-col="empleador">' . $h(CatalogoRdep::TIPO_EMPLEADOR[$r['tipo_empleador']] ?? $r['tipo_empleador']) . '</td>'
            . '<td class="text-center" data-col="trabajadores">' . (int) $r['total_trabajadores'] . '</td>'
            . '<td class="text-end" data-col="ingresos">' . $fmt($r['total_ingresos']) . '</td>'
            . '<td class="text-end fw-bold" data-col="retenido">' . $fmt($r['total_retenido']) . '</td>'
            . '<td class="text-center" data-col="observaciones">' . $obs . '</td>'
            . '<td class="text-center" data-col="estado">' . $estado . '</td>'
            . '<td data-col="generado">' . $h($generado) . '</td>'
            . '<td class="text-center pe-3" onclick="event.stopPropagation()">'
            . (!empty($r['archivo_xml']) && $r['estado'] === 'generado'
                ? '<button class="btn btn-outline-secondary btn-xs border-0 px-2" onclick="RDEP_descargar(\'' . $h(str_replace('.xml', '.zip', (string) $r['archivo_xml'])) . '\')" title="Descargar ZIP"><i class="bi bi-file-earmark-zip"></i></button>'
                : '')
            . '</td></tr>';
    }

    // ─── Anexo ───────────────────────────────────────────────────────────────
    public function abrirAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $anio = (int) ($_POST['anio'] ?? 0);
            $id = $this->service->abrir((int) $_SESSION['id_empresa'], $anio, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getAnexoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        if (!$this->service->instalado()) { echo json_encode(['ok' => false, 'error' => AnexoRdepService::MSG_NO_INSTALADO]); exit; }
        $cab = $this->service->getCabecera($id, $idEmpresa);
        if (!$cab) { echo json_encode(['ok' => false, 'error' => 'Anexo no encontrado.']); exit; }
        echo json_encode([
            'ok'       => true,
            'cabecera' => $cab,
            'detalle'  => $this->service->getDetalle($id, $idEmpresa),
            'sin_tramos' => (new \App\Services\modulos\ImpuestoRentaEmpleadoService())->getTramosAnio((int) $cab['anio']) === [],
        ]);
        exit;
    }

    public function guardarCabeceraAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->guardarCabecera($id, (int) $_SESSION['id_empresa'], $_POST, (int) $_SESSION['id_usuario']);
            return ['msg' => 'Datos del anexo guardados y resumen recalculado.'];
        });
    }

    public function importarAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $r = $this->service->importar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => "Nómina importada: {$r['nuevos']} trabajador(es) nuevo(s), {$r['actualizados']} actualizado(s).", 'resultado' => $r];
        });
    }

    public function recalcularAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $r = $this->service->recalcular($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Resumen recalculado y validado.', 'resultado' => $r];
        });
    }

    public function validarAjax(): void
    {
        $this->requireLeer();
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            $r = $this->service->validar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Validación terminada.', 'resultado' => $r];
        });
    }

    public function generarAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $r = $this->service->generar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            $base = BASE_URL . '/' . self::RUTA_MODULO . '/descargar?archivo=';
            return [
                'msg'       => 'Archivo generado y validado contra el esquema del SRI.',
                'resultado' => $r,
                'url_xml'   => $base . urlencode($r['nombre_xml']),
                'url_zip'   => $r['nombre_zip'] ? $base . urlencode($r['nombre_zip']) : null,
            ];
        });
    }

    public function descargar(): void
    {
        $this->requireLeer();
        $nombre = (string) ($_GET['archivo'] ?? '');
        $ruta = $this->service->rutaArchivo((int) $_SESSION['id_empresa'], $nombre);
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
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Anexo eliminado.'];
        });
    }

    // ─── Trabajadores ─────────────────────────────────────────────────────────
    public function getTrabajadorAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $fila = $this->service->getTrabajador((int) ($_GET['id'] ?? 0), (int) $_SESSION['id_empresa']);
        if (!$fila) { echo json_encode(['ok' => false, 'error' => 'Trabajador no encontrado.']); exit; }
        echo json_encode(['ok' => true, 'trabajador' => $fila]);
        exit;
    }

    public function guardarTrabajadorAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $campos = [];
            foreach (AnexoRdepRules::camposEditables() as $c) {
                if (array_key_exists($c, $_POST)) $campos[$c] = $_POST[$c];
            }
            if (!array_key_exists('tercera_edad', $_POST)) $campos['tercera_edad'] = '0';
            $fila = $this->service->guardarTrabajador($id, (int) $_SESSION['id_empresa'], $campos, (int) $_SESSION['id_usuario']);
            return ['msg' => 'Trabajador guardado.', 'trabajador' => $fila];
        });
    }

    public function agregarTrabajadorAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $idAnexo = (int) ($_POST['id_anexo'] ?? 0);
            $idEmp = (int) ($_POST['id_empleado'] ?? 0) ?: null;
            $id = $this->service->agregarTrabajador($idAnexo, (int) $_SESSION['id_empresa'], $idEmp, (int) $_SESSION['id_usuario']);
            return ['msg' => 'Trabajador agregado.', 'id' => $id];
        });
    }

    public function eliminarTrabajadorAjax(): void
    {
        $this->requireActualizar();
        $this->json(function () {
            $this->service->eliminarTrabajador((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Trabajador quitado del anexo.'];
        });
    }

    public function buscarEmpleadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q = trim((string) ($_GET['q'] ?? ''));
        echo json_encode(['ok' => true, 'data' => $q === '' ? [] : $this->service->buscarEmpleados((int) $_SESSION['id_empresa'], $q)]);
        exit;
    }

    /** Respuesta JSON uniforme para las acciones: {ok, msg, …} o {ok:false, error}. */
    private function json(callable $fn): void
    {
        header('Content-Type: application/json');
        try {
            $r = $fn();
            echo json_encode(['ok' => true] + (array) $r);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'ajax']);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Exportación del listado ──────────────────────────────────────────────
    private function filasParaExportar(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $p = $this->parametrosListado();
        $data = $this->service->getListado($idEmpresa, $p['buscar'], 1, 0, $p['ordenCol'], $p['ordenDir'], $p['idUsuarioFiltro'], $p['orden']);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
        return [$data['rows'], (string) ($empresa['nombre'] ?? '')];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        try {
            [$rows, $nombreEmpresa] = $this->filasParaExportar();
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            $fmt = fn($v) => number_format((float) $v, 2);
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
                    <h2>Anexos RDEP</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%">Ejercicio</th>
                            <th style="width: 18%">Empleador</th>
                            <th style="width: 12%" class="num">Trabajadores</th>
                            <th style="width: 18%" class="num">Ingresos gravados</th>
                            <th style="width: 16%" class="num">Retenido</th>
                            <th style="width: 12%">Estado</th>
                            <th style="width: 14%">Generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="width: 10%"><?= (int) $r['anio'] ?></td>
                                <td style="width: 18%"><?= htmlspecialchars(CatalogoRdep::TIPO_EMPLEADOR[$r['tipo_empleador']] ?? (string) $r['tipo_empleador']) ?></td>
                                <td style="width: 12%" class="num"><?= (int) $r['total_trabajadores'] ?></td>
                                <td style="width: 18%" class="num"><?= $fmt($r['total_ingresos']) ?></td>
                                <td style="width: 16%" class="num"><?= $fmt($r['total_retenido']) ?></td>
                                <td style="width: 12%"><?= htmlspecialchars(ucfirst((string) $r['estado'])) ?></td>
                                <td style="width: 14%"><?= !empty($r['generado_at']) ? date('d-m-Y', strtotime((string) $r['generado_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('AnexoRDEP_' . date('Ymd_His') . '.pdf', 'D');
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
        try {
            [$rows, $nombreEmpresa] = $this->filasParaExportar();
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            $headers = ['Ejercicio', 'RUC', 'Razón social', 'Tipo de empleador', 'Ente seg. social', 'Trabajadores', 'Ingresos gravados', 'Retenido', 'Obs. graves', 'Obs. leves', 'Estado', 'Generado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string) (int) $r['anio'], (string) $r['num_ruc'], (string) $r['razon_social'],
                    CatalogoRdep::TIPO_EMPLEADOR[$r['tipo_empleador']] ?? (string) $r['tipo_empleador'],
                    (string) $r['ente_seg_social'], (int) $r['total_trabajadores'],
                    (float) $r['total_ingresos'], (float) $r['total_retenido'],
                    (int) $r['total_graves'], (int) $r['total_leves'],
                    ucfirst((string) $r['estado']),
                    !empty($r['generado_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['generado_at'])) : '',
                ];
            }
            (new \App\Services\ReportService())->exportToExcel('AnexoRDEP', $headers, $exportData, 'Anexo RDEP', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }
}
