<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\FlujoCajaService;
use App\repositories\modulos\FlujoCajaRepository;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\RolPagoRepository;
use App\repositories\modulos\ControlBancarioRepository;

class FlujoCajaController extends BaseModuloController
{
    private FlujoCajaService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/flujo-caja';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new FlujoCajaService(
            new FlujoCajaRepository(),
            new CuentasPorCobrarRepository(),
            new CuentasPorPagarRepository(),
            new RolPagoRepository(),
            new ControlBancarioRepository()
        );
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos/flujo_caja/index', [
            'titulo' => 'Flujo de Caja',
            'perm' => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'fullWidth' => true,
            'base' => BASE_URL,
        ]);
    }

    private function getFiltros(): array
    {
        $hoy = date('Y-m-d');
        return [
            'desde' => trim($_REQUEST['desde'] ?? date('Y-m-d', strtotime($hoy . ' -30 days'))),
            'hasta' => trim($_REQUEST['hasta'] ?? date('Y-m-d', strtotime($hoy . ' +30 days'))),
            'agrupacion' => trim($_REQUEST['agrupacion'] ?? 'dia'),
        ];
    }

    public function getLineaTiempoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();

        try {
            $data = $this->service->getLineaTiempo($idEmpresa, $f['desde'], $f['hasta'], $f['agrupacion']);
            $this->json(['ok' => true] + $data);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();
        $data = $this->service->getLineaTiempo($idEmpresa, $f['desde'], $f['hasta'], $f['agrupacion']);

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="flujo_caja_' . date('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF";

        echo implode("\t", ['Período', 'Tipo', 'Entradas', 'Salidas', 'Neto', 'Saldo']) . "\n";
        foreach ($data['periodos'] as $p) {
            echo implode("\t", [
                $p['periodo'],
                $p['real'] ? 'Real' : 'Proyectado',
                number_format($p['entradas'], 2, '.', ''),
                number_format($p['salidas'], 2, '.', ''),
                number_format($p['entradas'] - $p['salidas'], 2, '.', ''),
                number_format($p['saldo'], 2, '.', ''),
            ]) . "\n";
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();
        $data = $this->service->getLineaTiempo($idEmpresa, $f['desde'], $f['hasta'], $f['agrupacion']);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

        $filas = '';
        foreach ($data['periodos'] as $p) {
            $badge = $p['real'] ? 'Real' : 'Proyectado';
            $color = $p['saldo'] < 0 ? 'color:#dc3545;' : '';
            $filas .= '<tr><td>' . htmlspecialchars($p['periodo']) . '</td>'
                . '<td class="c">' . $badge . '</td>'
                . '<td class="r" style="color:#198754;">$' . number_format($p['entradas'], 2) . '</td>'
                . '<td class="r" style="color:#dc3545;">$' . number_format($p['salidas'], 2) . '</td>'
                . '<td class="r" style="' . $color . '"><strong>$' . number_format($p['saldo'], 2) . '</strong></td></tr>';
        }

        ob_start(); ?>
        <style>
            table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:8pt; }
            th { background:#f2f2f2; border:1px solid #ccc; padding:4px; }
            td { border:1px solid #ccc; padding:4px; }
            .r { text-align:right; } .c { text-align:center; }
            .head { text-align:center; margin-bottom:10px; }
        </style>
        <div class="head">
            <h3><?= htmlspecialchars($empresa['nombre'] ?? '') ?></h3>
            <h4>Flujo de Caja (<?= htmlspecialchars($f['desde']) ?> al <?= htmlspecialchars($f['hasta']) ?>)</h4>
            <p style="font-size:8pt">Saldo inicial: $<?= number_format($data['saldo_inicial'], 2) ?> — Generado: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table>
            <thead><tr><th>Período</th><th>Tipo</th><th class="r">Entradas</th><th class="r">Salidas</th><th class="r">Saldo</th></tr></thead>
            <tbody><?= $filas ?></tbody>
        </table>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('FlujoCaja_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }
}
