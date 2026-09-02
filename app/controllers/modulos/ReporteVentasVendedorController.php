<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteVentasVendedorRepository;

class ReporteVentasVendedorController extends BaseModuloController
{
    private ReporteVentasVendedorRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_ventas_vendedor';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteVentasVendedorRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $vendedores = (new \App\repositories\modulos\VendedorRepository())
            ->getListado($idEmpresa, '', 1, 500, 'nombre', 'ASC')['rows'] ?? [];
        $marcas = (new \App\repositories\modulos\MarcaRepository())
            ->getListado($idEmpresa, '', 1, 500, 'nombre', 'ASC')['rows'] ?? [];
        $categorias = (new \App\repositories\modulos\CategoriaRepository())
            ->getListado($idEmpresa, '', 1, 500, 'nombre', 'ASC')['rows'] ?? [];

        $anios = $this->repository->getAniosDisponibles($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/reporte_ventas_vendedor/index', [
            'titulo'              => 'Reporte de Ventas por Vendedor',
            'perm'                => $this->getPermisos(),
            'vistaConfig'         => $prefsVista,
            'rutaModulo'          => $this->getRutaModulo(),
            'vendedores'          => $vendedores,
            'marcas'              => $marcas,
            'categorias'          => $categorias,
            'anios'               => $anios,
            'vendedorRestringido' => $this->restriccionVendedor(),
            'fullWidth'           => true,
            'base'                => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        $filtros = [
            'tipo_documento' => $_REQUEST['tipo_documento'] ?? 'FACTURA_MENOS_NC',
            'agrupar_por'    => $_REQUEST['agrupar_por'] ?? 'VENDEDOR',
            'fecha_desde'    => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'    => $_REQUEST['fecha_hasta'] ?? '',
            'id_vendedor'    => $_REQUEST['id_vendedor'] ?? '',
            'id_producto'    => $_REQUEST['id_producto'] ?? '',
            'id_marca'       => $_REQUEST['id_marca'] ?? '',
            'id_categoria'   => $_REQUEST['id_categoria'] ?? '',
        ];

        // Si el usuario no tiene "acceso total" sobre este submódulo, se le
        // fuerza a ver únicamente las ventas ASIGNADAS a su propio vendedor
        // (ventas_cabecera.id_vendedor), sin importar quién facturó/tecleó el
        // documento y sin importar lo que venga en la petición del cliente.
        $restriccion = $this->restriccionVendedor();
        if ($restriccion !== null) {
            $filtros['id_vendedor'] = $restriccion['id'];
        }

        return $filtros;
    }

    /**
     * Resuelve si el usuario actual debe quedar restringido a su propio
     * vendedor. Retorna null si tiene "acceso total" (sin restricción), o un
     * array ['id' => int, 'nombre' => ?string] cuando está restringido:
     * 'id' es el id de su vendedor vinculado, o -1 si no tiene ninguno
     * vinculado (fuerza un reporte vacío en vez de mostrar datos ajenos).
     */
    private function restriccionVendedor(): ?array
    {
        $perm = $this->getPermisos();
        if (!empty($perm['todo'])) {
            return null;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $vendedor = (new \App\repositories\modulos\VendedorRepository())->getPorUsuario($idEmpresa, $idUsuario);

        return [
            'id'     => $vendedor['id'] ?? -1,
            'nombre' => $vendedor['nombre'] ?? null,
        ];
    }

    /**
     * Despacha al método del repository según la agrupación elegida.
     * Centralizado para no repetir el switch en generarAjax/exportExcel/exportPdf/enviarCorreoAjax.
     */
    private function obtenerFilas(array $filtros): array
    {
        return match ($filtros['agrupar_por'] ?? 'VENDEDOR') {
            'PRODUCTO'  => $this->repository->getReporteAgrupadoProducto((int) $_SESSION['id_empresa'], $filtros),
            'MARCA'     => $this->repository->getReporteAgrupadoMarca((int) $_SESSION['id_empresa'], $filtros),
            'CATEGORIA' => $this->repository->getReporteAgrupadoCategoria((int) $_SESSION['id_empresa'], $filtros),
            'MES'       => $this->repository->getReporteAgrupadoMes((int) $_SESSION['id_empresa'], $filtros),
            'NINGUNO'   => $this->repository->getReporteDetallado((int) $_SESSION['id_empresa'], $filtros),
            default     => $this->repository->getReporteAgrupadoVendedor((int) $_SESSION['id_empresa'], $filtros),
        };
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            $rows = $this->obtenerFilas($filtros);
            $stats = $this->repository->getEstadisticas($idEmpresa, $filtros);
            $resumenEstados = $this->repository->getResumenEstados($idEmpresa, $filtros);

            ob_start();
            if (empty($rows)) {
                $colSpan = $this->colSpanPara($filtros['agrupar_por']);
                echo '<tr><td colspan="' . $colSpan . '" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaAgrupadaHtml($r, $filtros['agrupar_por']);
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'rawData'    => $rows,
                'stats'      => $stats,
                'estados'    => $resumenEstados,
                'agrupacion' => $filtros['agrupar_por'],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function colSpanPara(string $agruparPor): int
    {
        return match ($agruparPor) {
            'PRODUCTO'  => 10,
            'MARCA', 'CATEGORIA' => 6,
            'NINGUNO'   => 10,
            default     => 7, // VENDEDOR, MES
        };
    }

    private function renderFilaAgrupadaHtml(array $r, string $agruparPor): string
    {
        $attrs = '';
        if ($agruparPor === 'NINGUNO' && !empty($r['id'])) {
            $tipoDoc = $r['_doc_tipo'] ?? 'FACTURA';
            $attrs = ' style="cursor:pointer;" title="Clic para ver el detalle"'
                   . ' data-doc-id="' . (int) $r['id'] . '"'
                   . ' data-doc-tipo="' . $tipoDoc . '"'
                   . ' data-doc-numero="' . htmlspecialchars($r['numero_factura'] ?? '', ENT_QUOTES) . '"'
                   . ' data-doc-sujeto="' . htmlspecialchars($r['cliente_nombre'] ?? '', ENT_QUOTES) . '"';
        } elseif (!in_array($agruparPor, ['PRODUCTO', 'MARCA', 'CATEGORIA', 'MES', 'NINGUNO'], true)) {
            // VENDEDOR: clic abre el detalle documento por documento (drill-down).
            $attrs = ' style="cursor:pointer;" title="Clic para ver el detalle de documentos"'
                   . ' data-vendedor-id="' . (int) ($r['id_vendedor'] ?? 0) . '"'
                   . ' data-vendedor-nombre="' . htmlspecialchars($r['vendedor_nombre'] ?? '', ENT_QUOTES) . '"';
        }

        $html = '<tr class="align-middle"' . $attrs . '>';

        $base0   = number_format((float) ($r['base_0'] ?? 0), 2);
        $baseIva = number_format((float) ($r['base_iva'] ?? 0), 2);
        $iva     = number_format((float) ($r['valor_iva'] ?? 0), 2);
        $total   = number_format((float) ($r['total'] ?? 0), 2);
        $saldo   = number_format((float) ($r['saldo'] ?? 0), 2);
        // Saldo pendiente del documento (o suma de saldos del grupo): rojo si queda
        // algo por cobrar, verde si ya está cancelado.
        $saldoCls = (float) ($r['saldo'] ?? 0) > 0.01 ? 'text-danger' : 'text-success';

        if ($agruparPor === 'PRODUCTO') {
            $tarifa = (float) ($r['tarifa_iva'] ?? 0);
            $html .= "<td>".htmlspecialchars($r['producto_codigo'] ?? '')."</td>";
            $html .= "<td class='fw-bold'>".htmlspecialchars($r['producto_nombre'] ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['marca_nombre'] ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['categoria_nombre'] ?? '')."</td>";
            $html .= "<td class='text-center'>".(float)($r['cantidad_vendida'] ?? 0)."</td>";
            $html .= "<td class='text-center'>{$tarifa}%</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'MARCA') {
            $html .= "<td class='fw-bold'>".htmlspecialchars($r['marca_nombre'] ?? '')."</td>";
            $html .= "<td class='text-center'>".(float)($r['cantidad_vendida'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'CATEGORIA') {
            $html .= "<td class='fw-bold'>".htmlspecialchars($r['categoria_nombre'] ?? '')."</td>";
            $html .= "<td class='text-center'>".(float)($r['cantidad_vendida'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'MES') {
            $html .= "<td class='fw-bold'>".self::formatearMes($r['mes'] ?? '')."</td>";
            $html .= "<td class='text-center'>".(int)($r['cantidad_documentos'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
            $html .= "<td class='text-end fw-bold {$saldoCls}'>$saldo</td>";
        } elseif ($agruparPor === 'NINGUNO') {
            $estado = strtolower($r['estado'] ?? '');
            $badgeColor = match ($estado) {
                'autorizado', 'autorizada' => 'bg-success bg-opacity-10 text-success border-success',
                'borrador' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                default => 'bg-primary bg-opacity-10 text-primary border-primary',
            };
            $html .= "<td class='text-center'>".date('d/m/Y', strtotime($r['fecha_emision'] ?? ''))."</td>";
            $html .= "<td class='fw-bold'>".htmlspecialchars($r['numero_factura'] ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['cliente_nombre'] ?? '')."</td>";
            $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>".strtoupper($estado)."</span></td>";
            $html .= "<td>".htmlspecialchars($r['vendedor_nombre'] ?? '')."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
            $html .= "<td class='text-end fw-bold {$saldoCls}'>$saldo</td>";
        } else {
            // VENDEDOR (vista principal)
            $html .= "<td class='fw-bold'>".htmlspecialchars($r['vendedor_nombre'] ?? '')."</td>";
            $html .= "<td class='text-center'>".(int)($r['cantidad_documentos'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
            $html .= "<td class='text-end fw-bold {$saldoCls}'>$saldo</td>";
        }

        $html .= '</tr>';
        return $html;
    }

    private static function formatearMes(string $mes): string
    {
        if ($mes === '' || strpos($mes, '-') === false) {
            return htmlspecialchars($mes);
        }
        [$anio, $num] = explode('-', $mes);
        $nombres = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        $nombre = $nombres[str_pad($num, 2, '0', STR_PAD_LEFT)] ?? $num;
        return $nombre . ' ' . $anio;
    }

    /** Autocompletado de productos de venta para el filtro de Producto. */
    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    /**
     * ¿La agrupación trabaja a nivel de documento y por lo tanto lleva columna
     * "Saldo"? Producto/Marca/Categoría agrupan por línea de detalle, donde el
     * saldo (que es de la factura completa) no aplica.
     */
    private static function agrupacionConSaldo(string $agruparPor): bool
    {
        return !in_array($agruparPor, ['PRODUCTO', 'MARCA', 'CATEGORIA'], true);
    }

    /**
     * Encabezados de columnas y "row mapper" para Excel/PDF, según la agrupación.
     * @return array{headers: string[], rows: array<int, array>}
     */
    private function armarExport(array $rows, string $agruparPor): array
    {
        if ($agruparPor === 'PRODUCTO') {
            $headers = ['Código', 'Producto', 'Marca', 'Categoría', 'Cant. Vendida', 'Tipo IVA', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
            $data = array_map(fn($r) => [
                $r['producto_codigo'], $r['producto_nombre'], $r['marca_nombre'], $r['categoria_nombre'],
                (float) $r['cantidad_vendida'], $r['tarifa_iva'] . '%',
                (float) $r['base_0'], (float) $r['base_iva'], (float) $r['valor_iva'], (float) $r['total'],
            ], $rows);
        } elseif ($agruparPor === 'MARCA') {
            $headers = ['Marca', 'Cant. Vendida', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
            $data = array_map(fn($r) => [
                $r['marca_nombre'], (float) $r['cantidad_vendida'],
                (float) $r['base_0'], (float) $r['base_iva'], (float) $r['valor_iva'], (float) $r['total'],
            ], $rows);
        } elseif ($agruparPor === 'CATEGORIA') {
            $headers = ['Categoría', 'Cant. Vendida', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
            $data = array_map(fn($r) => [
                $r['categoria_nombre'], (float) $r['cantidad_vendida'],
                (float) $r['base_0'], (float) $r['base_iva'], (float) $r['valor_iva'], (float) $r['total'],
            ], $rows);
        } elseif ($agruparPor === 'MES') {
            $headers = ['Mes', 'Nro Documentos', 'Base 0%', 'Base IVA', 'IVA', 'Total', 'Saldo'];
            $data = array_map(fn($r) => [
                self::formatearMes($r['mes'] ?? ''), (int) $r['cantidad_documentos'],
                (float) $r['base_0'], (float) $r['base_iva'], (float) $r['valor_iva'], (float) $r['total'],
                (float) ($r['saldo'] ?? 0),
            ], $rows);
        } elseif ($agruparPor === 'NINGUNO') {
            $headers = ['Fecha', 'Documento', 'Cliente', 'RUC/Cédula', 'Estado', 'Vendedor', 'Base 0%', 'Base IVA', 'IVA', 'Total', 'Saldo'];
            $data = array_map(fn($r) => [
                date('d/m/Y', strtotime($r['fecha_emision'])), $r['numero_factura'], $r['cliente_nombre'], $r['cliente_ruc'],
                strtoupper($r['estado'] ?? ''), $r['vendedor_nombre'] ?? '',
                (float) ($r['base_0'] ?? 0), (float) ($r['base_iva'] ?? 0), (float) ($r['valor_iva'] ?? 0), (float) ($r['total'] ?? 0),
                (float) ($r['saldo'] ?? 0),
            ], $rows);
        } else {
            $headers = ['Vendedor', 'Nro Documentos', 'Base 0%', 'Base IVA', 'IVA', 'Total', 'Saldo'];
            $data = array_map(fn($r) => [
                $r['vendedor_nombre'], (int) $r['cantidad_documentos'],
                (float) $r['base_0'], (float) $r['base_iva'], (float) $r['valor_iva'], (float) $r['total'],
                (float) ($r['saldo'] ?? 0),
            ], $rows);
        }

        return ['headers' => $headers, 'rows' => $data];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();
        $rows = $this->obtenerFilas($filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $export = $this->armarExport($rows, $filtros['agrupar_por']);

            $reportService = new \App\Services\ReportService();
            $spreadsheet = $reportService->construirSpreadsheet($export['headers'], $export['rows'], 'Ventas por Vendedor', $nombreEmpresa);

            // Agrupando por Vendedor se agrega una segunda hoja con el detalle
            // documento por documento (factura, subtotal, NC, total y saldo pendiente)
            // de cada asesor.
            if (($filtros['agrupar_por'] ?? 'VENDEDOR') === 'VENDEDOR') {
                $detalle = $this->repository->getDetalleDocumentosVendedor($idEmpresa, $filtros);
                $headersDetalle = ['Vendedor', 'Fecha', 'Factura', 'Cliente', 'Subtotal', 'NC', 'Total', 'Saldo'];
                $filasDetalle = array_map(fn($d) => [
                    $d['vendedor_nombre'],
                    date('d/m/Y', strtotime($d['fecha_emision'])),
                    $d['numero_factura'],
                    $d['cliente_nombre'],
                    (float) $d['subtotal'],
                    (float) $d['nc'],
                    (float) $d['total'],
                    (float) ($d['saldo'] ?? 0),
                ], $detalle);
                $reportService->agregarHoja($spreadsheet, $headersDetalle, $filasDetalle, 'Detalle Documentos');
            }

            $reportService->descargarSpreadsheet($spreadsheet, 'VentasPorVendedor');
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    /**
     * Arma el HTML del PDF (tabla + totales). Se reutiliza para la descarga
     * directa (exportPdf) y para el envío por correo (enviarCorreoAjax).
     */
    private function construirHtmlPdf(array $rows, array $filtros, array $totales, string $nombreEmpresa): string
    {
        $agruparPor = $filtros['agrupar_por'];
        $export = $this->armarExport($rows, $agruparPor);

        ob_start();
        ?>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; margin: 0 auto 20px auto; }
            th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: center; }
            td { border: 1px solid #ccc; padding: 6px; }
            .text-end { text-align: right; }
            .header { text-align: center; margin-bottom: 20px; }
        </style>
        <div class="header">
            <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
            <h3>Reporte de Ventas por Vendedor</h3>
            <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <?php foreach ($export['headers'] as $h): ?>
                        <th><?= htmlspecialchars($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($export['rows'] as $fila): ?>
                    <tr>
                        <?php foreach ($fila as $i => $val): ?>
                            <?php $esNumerica = is_float($val) || is_int($val); ?>
                            <td class="<?= $esNumerica ? 'text-end' : '' ?>">
                                <?= $esNumerica ? number_format((float) $val, 2) : htmlspecialchars((string) $val) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php $conSaldo = self::agrupacionConSaldo($agruparPor); ?>
                <tr style="background-color: #e9ecef;">
                    <th colspan="<?= max(count($export['headers']) - ($conSaldo ? 5 : 4), 1) ?>" class="text-center">TOTALES GENERALES:</th>
                    <th class="text-end"><?= number_format((float) $totales['total_base_0'], 2) ?></th>
                    <th class="text-end"><?= number_format((float) $totales['total_base_iva'], 2) ?></th>
                    <th class="text-end"><?= number_format((float) $totales['total_iva'], 2) ?></th>
                    <th class="text-end" style="font-weight:bold;color:#198754;">$<?= number_format((float) $totales['gran_total'], 2) ?></th>
                    <?php if ($conSaldo): ?>
                        <th class="text-end" style="font-weight:bold;color:#dc3545;">$<?= number_format((float) ($totales['total_saldo'] ?? 0), 2) ?></th>
                    <?php endif; ?>
                </tr>
            </tfoot>
        </table>
        <?php
        return ob_get_clean();
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();
        $rows = $this->obtenerFilas($filtros);
        $totales = $this->repository->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE VENTAS POR VENDEDOR';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $html = $this->construirHtmlPdf($rows, $filtros, $totales, $nombreEmpresa);

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteVentasVendedor_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }

    /**
     * Envía el reporte (mismo PDF que exportPdf) adjunto por correo.
     */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $email = trim($_POST['email'] ?? '');
            $asunto = trim($_POST['asunto'] ?? '') ?: 'Reporte de Ventas por Vendedor';
            $mensaje = trim($_POST['mensaje'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'error' => 'Ingrese un correo destinatario válido.']);
                exit;
            }

            $filtros = $this->getFiltrosDesdeRequest();
            $rows = $this->obtenerFilas($filtros);
            $totales = $this->repository->getEstadisticas($idEmpresa, $filtros);

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Reporte de Ventas por Vendedor';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $htmlTabla = $this->construirHtmlPdf($rows, $filtros, $totales, $nombreEmpresa);

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($htmlTabla);
            $pdfString = $html2pdf->output('ReporteVentasVendedor.pdf', 'S');

            $cuerpoHtml = "<div style='font-family: Arial, sans-serif; line-height: 1.5;'>"
                . "<p>Adjunto el Reporte de Ventas por Vendedor generado desde " . htmlspecialchars($nombreEmpresa) . ".</p>"
                . ($mensaje !== '' ? '<p>' . nl2br(htmlspecialchars($mensaje)) . '</p>' : '')
                . "</div>";

            $enviado = (new \App\Services\EnvioDocumentosSRIService())->enviarPdfSimple(
                $idEmpresa,
                $email,
                $email,
                $asunto,
                $cuerpoHtml,
                $pdfString,
                'ReporteVentasVendedor_' . date('Ymd_His'),
                $nombreEmpresa
            );

            if ($enviado) {
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo. Verifique la configuración de correo de la empresa.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Drill-down: documento por documento (facturas) de un vendedor, con su
     * subtotal, la NC que lo afecta y el total neto. Se abre al hacer clic en
     * una fila de la agrupación Vendedor.
     */
    public function detalleVendedorAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            $idVendedor = (int) ($_REQUEST['id_vendedor'] ?? 0);
            $restriccion = $this->restriccionVendedor();
            if ($restriccion !== null) {
                // Ignora lo pedido por el cliente: solo puede ver su propio vendedor.
                $idVendedor = $restriccion['id'];
            }

            $documentos = $this->repository->getDocumentosPorVendedor($idEmpresa, $idVendedor, $filtros);

            echo json_encode(['ok' => true, 'documentos' => $documentos]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
