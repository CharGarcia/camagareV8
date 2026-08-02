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

        $html = '<tr class="align-middle">';
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

    /** Arma el HTML completo del reporte (tabla + totales) para PDF, reutilizado por descarga y envío por correo. */
    private function construirHtmlReporte(array $rows, array $stats, string $nombreEmpresa, array $filtros): string
    {
        ob_start();
        ?>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; margin: 0 auto 20px auto; }
            th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: center; }
            td { border: 1px solid #ccc; padding: 6px; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .header { text-align: center; margin-bottom: 20px; }
            .totales-table th { background: #e0e0e0; }
        </style>
        <div class="header">
            <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
            <h3>Reporte de Mejor Cliente</h3>
            <p>
                Período: <?= htmlspecialchars($filtros['fecha_desde']) ?> al <?= htmlspecialchars($filtros['fecha_hasta']) ?>
                &nbsp;|&nbsp; Orden: <?= $filtros['orden_por'] === 'cantidad' ? 'Cantidad de documentos' : 'Monto neto' ?>
            </p>
            <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table>
            <thead>
                <tr><th>#</th><th>Cliente</th><th>RUC/Cédula</th><th>Nro Documentos</th><th>Monto Neto</th><th>Venta Promedio</th></tr>
            </thead>
            <tbody>
                <?php $rank = 0; foreach ($rows as $r): $rank++; ?>
                    <tr>
                        <td class="text-center"><?= $rank ?></td>
                        <td><?= htmlspecialchars($r['cliente_nombre']) ?></td>
                        <td><?= htmlspecialchars($r['cliente_ruc']) ?></td>
                        <td class="text-center"><?= (int)$r['cantidad_documentos'] ?></td>
                        <td class="text-end"><strong><?= number_format((float)$r['monto_neto'], 2) ?></strong></td>
                        <td class="text-end"><?= number_format((float)$r['venta_promedio'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #e9ecef;">
                    <th colspan="3" class="text-center" style="font-size: 10pt;">TOTALES GENERALES:</th>
                    <th class="text-center" style="font-size: 10pt;"><?= (int)$stats['total_documentos'] ?></th>
                    <th class="text-end" style="font-size: 11pt; font-weight: bold; color: #198754;">$<?= number_format((float)$stats['monto_neto_total'], 2) ?></th>
                    <th class="text-end" style="font-size: 10pt;">$<?= number_format((float)$stats['venta_promedio'], 2) ?></th>
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
