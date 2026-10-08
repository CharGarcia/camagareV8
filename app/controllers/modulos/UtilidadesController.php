<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\repositories\modulos\UtilidadesRepository;
use App\Rules\modulos\UtilidadesRules;
use App\Services\ErrorLogService;
use App\Services\LogSistemaService;
use App\Services\modulos\UtilidadesExportService;
use App\Services\modulos\UtilidadesService;

/**
 * Utilidades (Nómina): participación de los trabajadores en las utilidades.
 * Solo recibe, valida lo básico, delega al Service y responde.
 */
class UtilidadesController extends BaseModuloController
{
    private UtilidadesService $service;
    private const RUTA_MODULO = 'modulos/utilidades';
    private const ORDEN_DEFECTO = 'anio';

    public function __construct()
    {
        parent::__construct();
        $this->service = new UtilidadesService(new UtilidadesRepository(), new UtilidadesRules(), new LogSistemaService());
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /** Parámetros comunes del listado (index, searchAjax y exportaciones). */
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

        $this->viewWithLayout('layouts.main', 'modulos.utilidades.index', [
            'titulo'      => 'Utilidades',
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
            echo '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-pie-chart fs-3 d-block mb-2"></i>No se encontraron utilidades.</td></tr>';
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
        $limite = $r['fecha_limite_pago'] ? date('d-m-Y', strtotime((string) $r['fecha_limite_pago'])) : '-';
        $colores = ['borrador' => 'secondary', 'calculado' => 'info', 'contabilizado' => 'success'];
        $c = $colores[$r['estado']] ?? 'secondary';
        $estado = '<span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . ' border-opacity-25">' . $h(ucfirst((string) $r['estado'])) . '</span>';

        return '<tr class="ut-row" role="button" tabindex="0" data-row=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') . '\' onclick="abrirModalVer(this)">'
            . '<td class="ps-3 fw-medium" data-col="anio">' . (int) $r['anio'] . '</td>'
            . '<td data-col="limite">' . $h($limite) . '</td>'
            . '<td class="text-end" data-col="repartir">' . $fmt($r['monto_repartir']) . '</td>'
            . '<td class="text-center" data-col="empleados">' . (int) $r['total_empleados'] . '</td>'
            . '<td class="text-end fw-bold" data-col="total">' . $fmt($r['total_valor']) . '</td>'
            . '<td class="text-end text-muted" data-col="excedente">' . $fmt($r['total_excedente']) . '</td>'
            . '<td class="text-center pe-3" data-col="estado">' . $estado . '</td>'
            . '</tr>';
    }

    public function calcularAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $anio     = (int) ($_POST['anio'] ?? 0);
            $utilidad = (float) str_replace(',', '.', (string) ($_POST['utilidad_liquida'] ?? '0'));
            $monto    = (float) str_replace(',', '.', (string) ($_POST['monto_repartir'] ?? '0'));
            if ($monto <= 0 && $utilidad > 0) {
                $monto = $this->service->montoLegal($utilidad);
            }
            $id = $this->service->calcular((int) $_SESSION['id_empresa'], $anio, $utilidad, $monto, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Utilidades calculadas.', 'id' => $id]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        if (!$this->service->instalado()) { echo json_encode(['ok' => false, 'error' => UtilidadesService::MSG_NO_INSTALADO]); exit; }
        $cab = $this->service->getCabecera($id, $idEmpresa);
        if (!$cab) { echo json_encode(['ok' => false, 'error' => 'No encontrado']); exit; }
        echo json_encode([
            'ok'          => true,
            'cabecera'    => $cab,
            'detalle'     => $this->service->getDetalle($id, $idEmpresa),
            'tiene_pagos' => $this->service->tienePagos($id),
        ]);
        exit;
    }

    public function actualizarDetalleAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idDetalle = (int) ($_POST['id'] ?? 0);
            $campos = [];
            foreach (['tipo_pago', 'discapacidad', 'valor_retencion', 'nombres', 'apellidos', 'cargas_familiares'] as $c) {
                if (array_key_exists($c, $_POST)) {
                    $campos[$c] = $c === 'discapacidad' ? !empty($_POST[$c]) : trim((string) $_POST[$c]);
                }
            }
            $this->service->actualizarDetalleEmpleado($idDetalle, (int) $_SESSION['id_empresa'], $campos, (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Registro actualizado.', 'redistribuido' => array_key_exists('cargas_familiares', $campos)]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function contabilizarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $r = $this->service->contabilizar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Asiento del 31 de diciembre generado.', 'id_asiento' => $r['id_asiento']]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** CSV con el informe para el Ministerio del Trabajo (una corrida). */
    public function exportarCsv(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        try {
            $export = new UtilidadesExportService(new UtilidadesRepository(), new UtilidadesRules());
            $cab = $this->service->getCabecera($id, $idEmpresa);
            if (!$cab) throw new \Exception('Utilidades no encontradas.');
            $contenido = $export->generar($id, $idEmpresa);
            $nombre = $export->nombreArchivo($cab);

            header('Content-Type: text/csv; charset=windows-1252');
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . strlen($contenido));
            echo $contenido;
        } catch (\Throwable $e) {
            http_response_code(400);
            echo 'No se pudo generar el archivo: ' . $e->getMessage();
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->anular($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Utilidades anuladas.']);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Exportación del listado (PDF / Excel), mismo patrón que Proveedores ──
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
            if (file_exists($autoload)) {
                require_once $autoload;
            }
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
                    <h2>Listado de Utilidades</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%">Ejercicio</th>
                            <th style="width: 16%">Fecha límite</th>
                            <th style="width: 18%" class="num">Monto a repartir</th>
                            <th style="width: 12%" class="num">Trabajadores</th>
                            <th style="width: 16%" class="num">A pagar</th>
                            <th style="width: 14%" class="num">Excedente</th>
                            <th style="width: 14%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="width: 10%"><?= (int) $r['anio'] ?></td>
                                <td style="width: 16%"><?= $r['fecha_limite_pago'] ? date('d-m-Y', strtotime((string) $r['fecha_limite_pago'])) : '-' ?></td>
                                <td style="width: 18%" class="num"><?= $fmt($r['monto_repartir']) ?></td>
                                <td style="width: 12%" class="num"><?= (int) $r['total_empleados'] ?></td>
                                <td style="width: 16%" class="num"><?= $fmt($r['total_valor']) ?></td>
                                <td style="width: 14%" class="num"><?= $fmt($r['total_excedente']) ?></td>
                                <td style="width: 14%"><?= htmlspecialchars(ucfirst((string) $r['estado'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Utilidades_' . date('Ymd_His') . '.pdf', 'D');
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
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $headers = ['Ejercicio', 'Fecha límite de pago', 'Utilidad líquida', 'Monto a repartir', '10% por tiempo', '5% por cargas', 'Trabajadores', 'A pagar', 'Excedente (IESS)', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string) (int) $r['anio'],
                    $r['fecha_limite_pago'] ? date('d-m-Y', strtotime((string) $r['fecha_limite_pago'])) : '',
                    (float) $r['utilidad_liquida'],
                    (float) $r['monto_repartir'],
                    (float) $r['monto_10'],
                    (float) $r['monto_5'],
                    (int) $r['total_empleados'],
                    (float) $r['total_valor'],
                    (float) $r['total_excedente'],
                    ucfirst((string) $r['estado']),
                ];
            }
            (new \App\Services\ReportService())->exportToExcel('Utilidades', $headers, $exportData, 'Utilidades', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }
}
