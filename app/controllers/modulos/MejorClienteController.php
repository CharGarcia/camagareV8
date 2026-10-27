<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\MejorClienteRepository;

class MejorClienteController extends BaseModuloController
{
    private MejorClienteRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/mejor_cliente';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new MejorClienteRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores = $vendedorRepo->getVendedoresActivos($idEmpresa);

        $anios = $this->repository->getAniosDisponibles($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/mejor_cliente/index', [
            'titulo'        => 'Mejor Cliente',
            'perm'          => $this->getPermisos(),
            'vistaConfig'   => $prefsVista,
            'rutaModulo'    => $this->getRutaModulo(),
            'vendedores'    => $vendedores,
            'anios'         => $anios,
            'puedeFacturas' => !empty(\App\Helpers\Permisos::porRuta('modulos/factura-venta')['ver']),
            'puedeRecibos'  => !empty(\App\Helpers\Permisos::porRuta('modulos/recibo-venta')['ver']),
            'fullWidth'     => true,
            'base'          => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(int $idEmpresa): array
    {
        $puedeFacturas = !empty(\App\Helpers\Permisos::porRuta('modulos/factura-venta')['ver']);
        $puedeRecibos  = !empty(\App\Helpers\Permisos::porRuta('modulos/recibo-venta')['ver']);

        $incluirFacturas = $puedeFacturas && ($_REQUEST['incluir_facturas'] ?? '1') === '1';
        $incluirRecibos  = $puedeRecibos && ($_REQUEST['incluir_recibos'] ?? '0') === '1';

        // Si el usuario no marcó ninguna fuente a la que tiene acceso, usar la primera disponible.
        if (!$incluirFacturas && !$incluirRecibos) {
            if ($puedeFacturas) {
                $incluirFacturas = true;
            } elseif ($puedeRecibos) {
                $incluirRecibos = true;
            }
        }

        $ordenPor = $_REQUEST['orden_por'] ?? 'monto';
        if (!in_array($ordenPor, ['monto', 'cantidad'], true)) {
            $ordenPor = 'monto';
        }

        return [
            'id_empresa'       => $idEmpresa,
            'fecha_desde'      => $_REQUEST['fecha_desde'] ?? date('Y-m-01'),
            'fecha_hasta'      => $_REQUEST['fecha_hasta'] ?? date('Y-m-t'),
            'id_vendedor'      => $_REQUEST['id_vendedor'] ?? '',
            'incluir_facturas' => $incluirFacturas,
            'incluir_recibos'  => $incluirRecibos,
            'orden_por'        => $ordenPor,
            'top_x'            => max(0, (int)($_REQUEST['top_x'] ?? 10)),
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest($idEmpresa);

            $rows = $this->repository->getRanking($filtros);
            $stats = $this->repository->getEstadisticas($filtros);

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-trophy fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                $rank = 0;
                foreach ($rows as $r) {
                    $rank++;
                    echo $this->renderFilaHtml($r, $rank);
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode([
                'ok'      => true,
                'rows'    => $rowsHtml,
                'rawData' => $rows,
                'stats'   => $stats,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Productos vendidos a un cliente con los filtros actuales del reporte (modal de detalle). */
    public function detalleProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idCliente = (int) ($_REQUEST['id_cliente'] ?? 0);
            if ($idCliente <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Cliente no válido.']);
                exit;
            }

            $filtros = $this->getFiltrosDesdeRequest((int) $_SESSION['id_empresa']);
            echo json_encode([
                'ok'   => true,
                'rows' => $this->repository->getDetalleProductos($filtros, $idCliente),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r, int $rank): string
    {
        $medalClass = match (true) {
            $rank === 1 => 'bg-warning bg-opacity-25 text-warning border-warning',
            $rank === 2 => 'bg-secondary bg-opacity-25 text-secondary border-secondary',
            $rank === 3 => 'bg-danger bg-opacity-10 text-danger border-danger',
            default     => 'bg-light text-muted border-secondary-subtle',
        };

        $monto = number_format((float)$r['monto_neto'], 2);
        $ventaPromedio = number_format((float)$r['venta_promedio'], 2);

        // Clic en la fila: abre el modal con los productos vendidos a ese cliente.
        $html = '<tr class="align-middle mc-fila-cliente" role="button" title="Ver productos vendidos"'
            . ' data-id-cliente="' . (int) $r['id_cliente'] . '"'
            . ' data-nombre="' . htmlspecialchars($r['cliente_nombre'] ?? '') . '"'
            . ' data-ruc="' . htmlspecialchars($r['cliente_ruc'] ?? '') . '"'
            . ' data-monto="' . (float) $r['monto_neto'] . '"'
            . ' onclick="window.MC_verDetalle(this)">';
        $html .= "<td class='text-center'><span class='badge border {$medalClass}' style='font-size:.8rem;'>#{$rank}</span></td>";
        $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['cliente_ruc'] ?? '') . "</small></td>";
        $html .= "<td class='text-center'>" . (int)($r['cantidad_documentos'] ?? 0) . "</td>";
        $html .= "<td class='text-end fw-bold text-success'>\${$monto}</td>";
        $html .= "<td class='text-end'>\${$ventaPromedio}</td>";
        $html .= '</tr>';
        return $html;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest($idEmpresa);
        $rows = $this->repository->getRanking($filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $headers = ['#', 'RUC/Cédula', 'Cliente', 'Nro Documentos', 'Monto Neto', 'Venta Promedio'];
            $exportData = [];
            $rank = 0;
            foreach ($rows as $r) {
                $rank++;
                $exportData[] = [
                    $rank,
                    $r['cliente_ruc'],
                    $r['cliente_nombre'],
                    $r['cantidad_documentos'],
                    (float)$r['monto_neto'],
                    (float)$r['venta_promedio'],
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('MejorCliente', $headers, $exportData, 'Mejor_Cliente', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    private function fechaTxt(string $f): string
    {
        return ($t = strtotime($f)) ? date('d-m-Y', $t) : $f;
    }

    private function periodoTxt(array $filtros): string
    {
        if ($filtros['fecha_desde'] === '' && $filtros['fecha_hasta'] === '') {
            return 'Todo el período';
        }
        return 'Período del ' . $this->fechaTxt($filtros['fecha_desde']) . ' al ' . $this->fechaTxt($filtros['fecha_hasta']);
    }

    private function fuentesTxt(array $filtros): string
    {
        return implode(' y ', array_filter([
            $filtros['incluir_facturas'] ? 'Facturas' : '',
            $filtros['incluir_recibos'] ? 'Recibos de venta' : '',
        ]));
    }

    private function nombreVendedor(array $filtros): string
    {
        // Por id y no por la lista de activos: un vendedor ya inactivo también puede filtrarse.
        if ((int) $filtros['id_vendedor'] <= 0) {
            return 'Todos';
        }
        $v = (new \App\repositories\modulos\VendedorRepository())->findById((int) $filtros['id_vendedor'], $filtros['id_empresa']);
        return trim((string) ($v['nombre'] ?? '')) ?: 'Vendedor #' . (int) $filtros['id_vendedor'];
    }

    /**
     * Datos de las exportaciones del detalle de un cliente: los mismos productos del modal y,
     * si el usuario escribió algo en su buscador, solo los que coinciden (todas las palabras,
     * sin distinguir mayúsculas), para descargar lo que está viendo.
     */
    private function datosDetalleExport(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idCliente = (int) ($_REQUEST['id_cliente'] ?? 0);
        $cliente = $idCliente > 0 ? $this->repository->getCliente($idCliente, $idEmpresa) : null;
        if (!$cliente) {
            throw new \RuntimeException('Cliente no válido.');
        }

        $filtros = $this->getFiltrosDesdeRequest($idEmpresa);
        $rows = $this->repository->getDetalleProductos($filtros, $idCliente);

        $buscar = trim((string) ($_REQUEST['buscar'] ?? ''));
        if ($buscar !== '') {
            $palabras = preg_split('/\s+/u', mb_strtolower($buscar));
            $rows = array_values(array_filter($rows, static function (array $r) use ($palabras): bool {
                $texto = mb_strtolower($r['producto_codigo'] . ' ' . $r['producto_nombre']);
                foreach ($palabras as $p) {
                    if (!str_contains($texto, $p)) {
                        return false;
                    }
                }
                return true;
            }));
        }

        // Monto del cliente en el ranking (sale de las cabeceras) para mostrar la diferencia con
        // la suma de los productos, igual que el modal. Con un filtro de texto no aplica.
        $diferencia = 0.0;
        if ($buscar === '') {
            $fila = $this->repository->getRanking(['id_cliente' => $idCliente, 'top_x' => 0] + $filtros)[0] ?? null;
            $suma = array_sum(array_column($rows, 'monto_neto'));
            $diferencia = round((float) ($fila['monto_neto'] ?? $suma) - $suma, 2);
        }

        return [$filtros, $cliente, $rows, $buscar, $diferencia];
    }

    public function exportDetalleExcel(): void
    {
        $this->requireLeer();

        try {
            [$filtros, $cliente, $rows, $buscar, $diferencia] = $this->datosDetalleExport();
            $empresa = (new \App\models\Empresa())->getPorId($filtros['id_empresa']) ?? [];

            $headers = ['Código', 'Producto', 'Unidad', 'Cantidad', 'Documentos', 'Monto Neto', '% del Cliente'];
            $data = [];
            foreach ($rows as $r) {
                $data[] = [$r['producto_codigo'], $r['producto_nombre'], $r['unidad_medida'], $r['cantidad'],
                           $r['cantidad_documentos'], $r['monto_neto'], $r['pct']];
            }
            $data[] = ['', 'TOTAL', '', '', '', array_sum(array_column($rows, 'monto_neto')), ''];
            if (abs($diferencia) >= 0.01) {
                $data[] = ['', 'Diferencia con el monto neto del ranking (documentos sin detalle de productos)', '', '', '', $diferencia, ''];
            }

            $info = [
                'Cliente'    => $cliente['nombre'] . ' (' . $cliente['identificacion'] . ')',
                'Período'    => $this->periodoTxt($filtros),
                'Vendedor'   => $this->nombreVendedor($filtros),
                'Documentos' => $this->fuentesTxt($filtros),
            ];
            if ($buscar !== '') {
                $info['Filtro'] = $buscar;
            }

            (new \App\Services\ReportService())->exportToExcel('MejorCliente_Productos', $headers, $data,
                'Productos', ($empresa['nombre'] ?? '') . ' - Productos vendidos al cliente', $info);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportDetallePdf(): void
    {
        $this->requireLeer();

        try {
            [$filtros, $cliente, $rows, $buscar, $diferencia] = $this->datosDetalleExport();
            $empresa = (new \App\models\Empresa())->getPorId($filtros['id_empresa']) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $pdf = \App\Helpers\ReportePdf::class;
            $pt  = 8.0;
            $wProducto = 39.0;
            $anchoProd = $pdf::anchoPt($wProducto, false);

            $filtrosTxt = [
                'Cliente'    => $cliente['nombre'],
                'RUC/Cédula' => $cliente['identificacion'],
                'Desde'      => $this->fechaTxt($filtros['fecha_desde']),
                'Hasta'      => $this->fechaTxt($filtros['fecha_hasta']),
                'Vendedor'   => $this->nombreVendedor($filtros),
                'Documentos' => $this->fuentesTxt($filtros),
            ];
            if ($buscar !== '') {
                $filtrosTxt['Filtro'] = $buscar;
            }

            $cols = [
                ['lbl' => 'Código', 'w' => 14.0,
                 'val' => static fn (array $r): string => htmlspecialchars((string) $r['producto_codigo'])],
                ['lbl' => 'Producto', 'w' => $wProducto,
                 'val' => static fn (array $r): string => $pdf::texto((string) $r['producto_nombre'], $anchoProd, $pt)],
                ['lbl' => 'Unidad', 'w' => 9.0,
                 'val' => static fn (array $r): string => htmlspecialchars((string) $r['unidad_medida'])],
                ['lbl' => 'Cantidad', 'w' => 10.0, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => rtrim(rtrim(number_format((float) $r['cantidad'], 4), '0'), '.')],
                ['lbl' => 'Docs.', 'w' => 7.0, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => (string) (int) $r['cantidad_documentos']],
                ['lbl' => 'Monto neto', 'w' => 12.0, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => '<b>' . number_format((float) $r['monto_neto'], 2) . '</b>',
                 'tot' => static fn (array $fs): float => array_sum(array_column($fs, 'monto_neto'))],
                ['lbl' => '% cliente', 'w' => 9.0, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => number_format((float) $r['pct'], 2) . '%'],
            ];

            $n = count($rows);
            $html = $pdf::encabezado($filtros['id_empresa'], $nombreEmpresa, 'Productos vendidos al cliente',
                    $cliente['nombre'] . ' · ' . $this->periodoTxt($filtros))
                . $pdf::filtros($filtrosTxt)
                . $pdf::listado($cols, $rows, 'TOTAL (' . $n . ' ' . ($n === 1 ? 'producto' : 'productos') . ')',
                    'Los documentos de este cliente no tienen detalle de productos.');
            if (abs($diferencia) >= 0.01) {
                $html .= "<p style='font-size:7pt;color:#666;margin-top:4px;'>Diferencia con el monto neto del ranking: <b>"
                    . number_format($diferencia, 2) . '</b> (documentos sin detalle de productos).</p>';
            }

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($pdf::pagina($pt, $html));
            $html2pdf->output('MejorCliente_Productos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    /**
     * Arma el documento completo del PDF, reutilizado por descarga y envío por correo. Mismo
     * formato que los demás reportes (App\Helpers\ReportePdf): encabezado con logo, filtros
     * aplicados, indicadores del período y listado a todo el ancho de la hoja.
     */
    private function construirHtmlReporte(array $rows, array $stats, string $nombreEmpresa, array $filtros): string
    {
        $pdf = \App\Helpers\ReportePdf::class;
        $pt  = 8.0;
        $fecha = \Closure::fromCallable([$this, 'fechaTxt']);

        $vendedor = $this->nombreVendedor($filtros);
        $filtrosTxt = [
            'Desde'        => $fecha($filtros['fecha_desde']),
            'Hasta'        => $fecha($filtros['fecha_hasta']),
            'Vendedor'     => $vendedor,
            'Documentos'   => $this->fuentesTxt($filtros),
            'Ordenado por' => $filtros['orden_por'] === 'cantidad' ? 'Cantidad de documentos' : 'Monto neto',
            'Mostrar'      => $filtros['top_x'] > 0 ? 'Top ' . $filtros['top_x'] : 'Todos los clientes',
        ];

        $montoTotal = (float) $stats['monto_neto_total'];
        $dinero = static fn (float $v): string => ($v < 0 ? '-$' : '$') . number_format(abs($v), 2);
        $kpis = [
            ['Clientes con ventas', number_format((int) $stats['total_clientes'])],
            ['Documentos', number_format((int) $stats['total_documentos'])],
            ['Monto neto total', $dinero($montoTotal), true],
            ['Venta promedio', $dinero((float) $stats['venta_promedio'])],
        ];
        if ($rows) {
            $kpis[] = ['Mejor cliente', $dinero((float) $rows[0]['monto_neto'])];
        }

        // El puesto viaja en cada fila para que la columna # no dependa de un contador externo.
        $filas = [];
        foreach (array_values($rows) as $i => $r) {
            $filas[] = $r + ['__rank' => $i + 1];
        }
        $wCliente = 38.0;
        $anchoCli = $pdf::anchoPt($wCliente, false);
        $suma = static fn (string $k): \Closure => static fn (array $fs): float => array_sum(array_map(static fn ($r) => (float) $r[$k], $fs));
        $cols = [
            ['lbl' => '#', 'w' => 6.0, 'cls' => 'text-center',
             'val' => static fn (array $r): string => '<b>' . $r['__rank'] . '</b>'],
            ['lbl' => 'Cliente', 'w' => $wCliente,
             'val' => static fn (array $r): string => $pdf::texto((string) ($r['cliente_nombre'] ?? ''), $anchoCli, $pt)],
            ['lbl' => 'RUC / Cédula', 'w' => 15.0, 'cls' => 'text-center',
             'val' => static fn (array $r): string => htmlspecialchars((string) ($r['cliente_ruc'] ?? ''))],
            ['lbl' => 'Documentos', 'w' => 10.0, 'cls' => 'text-center',
             'val' => static fn (array $r): string => (string) (int) $r['cantidad_documentos'],
             'tot' => $suma('cantidad_documentos'), 'fmt' => static fn (float $v): string => number_format($v)],
            ['lbl' => 'Monto neto', 'w' => 12.0, 'cls' => 'text-end',
             'val' => static fn (array $r): string => '<b>' . number_format((float) $r['monto_neto'], 2) . '</b>',
             'tot' => $suma('monto_neto')],
            ['lbl' => 'Venta promedio', 'w' => 11.0, 'cls' => 'text-end',
             'val' => static fn (array $r): string => number_format((float) $r['venta_promedio'], 2)],
            ['lbl' => '% del total', 'w' => 8.0, 'cls' => 'text-end',
             'val' => static fn (array $r): string => $montoTotal != 0.0 ? number_format((float) $r['monto_neto'] * 100 / $montoTotal, 2) . '%' : '-',
             'tot' => static fn (array $fs): float => $montoTotal != 0.0 ? array_sum(array_map(static fn ($r) => (float) $r['monto_neto'], $fs)) * 100 / $montoTotal : 0.0,
             'fmt' => static fn (float $v): string => number_format($v, 2) . '%'],
        ];

        $n = count($filas);
        $etiquetaTotal = 'TOTAL ' . ($filtros['top_x'] > 0 && $n < (int) $stats['total_clientes']
            ? "DE LOS {$n} MEJORES CLIENTES"
            : "({$n} " . ($n === 1 ? 'cliente' : 'clientes') . ')');

        return $pdf::pagina($pt,
            $pdf::encabezado($filtros['id_empresa'], $nombreEmpresa, 'Reporte de Mejor Cliente',
                'Período del ' . $fecha($filtros['fecha_desde']) . ' al ' . $fecha($filtros['fecha_hasta'])
                . ' · Vendedor: ' . $vendedor)
            . $pdf::filtros($filtrosTxt)
            . $pdf::indicadores($kpis)
            . $pdf::listado($cols, $filas, $etiquetaTotal, 'No se encontraron ventas para los filtros aplicados.')
        );
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest($idEmpresa);
        $rows = $this->repository->getRanking($filtros);
        $stats = $this->repository->getEstadisticas($filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE MEJOR CLIENTE';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $html = $this->construirHtmlReporte($rows, $stats, $nombreEmpresa, $filtros);
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('MejorCliente_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }

    /** Envía el reporte (con los filtros actuales) por correo, con el PDF adjunto. */
    public function enviarEmailAjax(): void
    {
        $this->requireLeer();
        // Soltar el candado de la sesión antes de armar el reporte y enviarlo por correo: si no, las demás
        // peticiones del usuario hacen fila hasta que termine.
        session_write_close();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $emailDest = trim($_POST['email'] ?? '');
        $asunto    = trim($_POST['asunto'] ?? '');
        $mensaje   = trim($_POST['mensaje'] ?? '');

        if ($emailDest === '') {
            echo json_encode(['ok' => false, 'error' => 'Debe indicar un correo de destino.']);
            exit;
        }

        try {
            $filtros = $this->getFiltrosDesdeRequest($idEmpresa);
            $rows = $this->repository->getRanking($filtros);
            $stats = $this->repository->getEstadisticas($filtros);

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $html = $this->construirHtmlReporte($rows, $stats, $nombreEmpresa, $filtros);
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $pdfString = $html2pdf->output('MejorCliente.pdf', 'S');

            $asuntoFinal = $asunto !== '' ? $asunto : ('Reporte de Mejor Cliente — ' . $filtros['fecha_desde'] . ' al ' . $filtros['fecha_hasta']);
            $cuerpo = '<p>' . nl2br(htmlspecialchars($mensaje !== '' ? $mensaje : 'Adjunto el reporte de Mejor Cliente solicitado.')) . '</p>';

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado = $emailSvc->enviarPdfSimple(
                $idEmpresa,
                $emailDest,
                $nombreEmpresa,
                $asuntoFinal,
                $cuerpo,
                $pdfString,
                'MejorCliente_' . date('Ymd_His'),
                $nombreEmpresa
            );

            if (!$enviado) {
                $detalle = $GLOBALS['LAST_EMAIL_ERROR'] ?? null;
                echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo. Verifica la configuración de correo de la empresa.' . ($detalle ? ' Detalle: ' . $detalle : '')]);
                exit;
            }

            (new \App\Services\LogSistemaService())->registrar(
                (int)$_SESSION['id_usuario'],
                $idEmpresa,
                'EMAIL_MEJOR_CLIENTE',
                'ventas_cabecera',
                null,
                null,
                ['email' => $emailDest, 'fecha_desde' => $filtros['fecha_desde'], 'fecha_hasta' => $filtros['fecha_hasta']]
            );

            echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente a ' . $emailDest]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
