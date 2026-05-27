<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CitaPagoRepository;
use App\Rules\modulos\CitaPagoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CitaPagoService;
use App\Helpers\PreferenciasHelper;

class CitasPagosController extends BaseModuloController
{
    private CitaPagoService $service;
    private const RUTA_MODULO = 'modulos/citas-pagos';

    public function __construct()
    {
        parent::__construct();
        $this->service = new CitaPagoService(
            new CitaPagoRepository(),
            new CitaPagoRules(),
            new LogSistemaService()
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ─── INDEX ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $resumen    = $this->service->getResumen($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/citas_pagos/index', [
            'titulo'      => 'Pagos de Citas',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'vistaConfig' => $prefsVista,
            'resumen'     => $resumen,
            'fullWidth'   => true,
        ]);
    }

    // ─── AJAX: listado paginado ───────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $page      = max(1, (int) ($_GET['page']     ?? 1));
        $perPage   = max(5, min(100, (int) ($_GET['per_page'] ?? 20)));
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = trim($_GET['dir']  ?? 'DESC');
        $rawQ      = trim($_GET['q']    ?? '');

        $filtros = [
            'estado'      => trim($_GET['estado']      ?? ''),
            'gateway'     => trim($_GET['gateway']     ?? ''),
            'tipo_pago'   => trim($_GET['tipo_pago']   ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'id_cita'     => (int) ($_GET['id_cita']   ?? 0) ?: null,
        ];

        $result = $this->service->getListado($idEmpresa, $rawQ, $page, $perPage, $ordenCol, $ordenDir, $filtros);
        $total  = $result['total'];
        $from   = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to     = $total > 0 ? min($page * $perPage, $total) : 0;

        $params = http_build_query([
            'q'          => $rawQ,
            'estado'     => $filtros['estado'],
            'gateway'    => $filtros['gateway'],
            'tipo_pago'  => $filtros['tipo_pago'],
            'fecha_desde'=> $filtros['fecha_desde'],
            'fecha_hasta'=> $filtros['fecha_hasta'],
            'sort'       => $ordenCol,
            'dir'        => $ordenDir,
        ]);
        $baseExport = BASE_URL . '/' . self::RUTA_MODULO;

        echo json_encode([
            'ok'       => true,
            'data'     => $result['rows'],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'info'     => "$from-$to/$total",
            'pdf_url'  => "$baseExport/export-pdf?$params",
            'excel_url'=> "$baseExport/export-excel?$params",
        ]);
        exit;
    }

    // ─── GUARDAR ─────────────────────────────────────────────────────────────

    public function guardar(): void
    {
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();

        $data = [
            'id'                  => $id,
            'id_empresa'          => (int) $_SESSION['id_empresa'],
            'id_usuario'          => (int) $_SESSION['id_usuario'],
            'id_cita'             => (int) ($_POST['id_cita']             ?? 0),
            'monto'               => max(0.01, (float) ($_POST['monto']   ?? 0)),
            'tipo_pago'           => trim($_POST['tipo_pago']             ?? 'total'),
            'gateway'             => trim($_POST['gateway']               ?? 'sitio'),
            'referencia_externa'  => trim($_POST['referencia_externa']    ?? '') ?: null,
            'estado'              => trim($_POST['estado']                ?? 'pendiente'),
        ];

        try {
            $newId = $this->service->guardar($data);
            $msg   = $id > 0 ? 'Pago actualizado correctamente.' : 'Pago registrado correctamente.';
            echo json_encode(['ok' => true, 'mensaje' => $msg, 'id' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── ELIMINAR ─────────────────────────────────────────────────────────────

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Pago eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: detalle ───────────────────────────────────────────────────────

    public function getAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $id   = (int) ($_GET['id'] ?? 0);
            $data = $this->service->getById($id, (int) $_SESSION['id_empresa']);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─── AJAX: buscar citas ───────────────────────────────────────────────────

    public function buscarCitasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rows      = $this->service->buscarCitas($q, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    // ─── EXPORT PDF ──────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rawQ      = trim($_GET['q']    ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = trim($_GET['dir']  ?? 'DESC');
        $filtros   = [
            'estado'      => trim($_GET['estado']      ?? ''),
            'gateway'     => trim($_GET['gateway']     ?? ''),
            'tipo_pago'   => trim($_GET['tipo_pago']   ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
        ];

        $result = $this->service->getListado($idEmpresa, $rawQ, 1, 5000, $ordenCol, $ordenDir, $filtros);
        $rows   = $result['rows'];

        $estadoLabel  = ['pendiente' => 'Pendiente', 'completado' => 'Completado', 'fallido' => 'Fallido', 'reembolsado' => 'Reembolsado'];
        $gatewayLabel = ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'transferencia' => 'Transferencia', 'sitio' => 'En sitio', 'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta'];
        $tipoLabel    = ['total' => 'Total', 'anticipo' => 'Anticipo'];

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            body{font-family:Arial,sans-serif;font-size:10px;}
            h2{text-align:center;margin-bottom:4px;font-size:13px;}
            p.sub{text-align:center;color:#666;margin:0 0 12px;font-size:9px;}
            table{width:100%;border-collapse:collapse;}
            th{background:#0d6efd;color:#fff;padding:5px 6px;text-align:left;font-size:9px;}
            td{padding:4px 6px;border-bottom:1px solid #e5e7eb;font-size:9px;}
            tr:nth-child(even) td{background:#f8f9fa;}
            .text-right{text-align:right;}
        </style></head><body>';
        $html .= '<h2>Pagos de Citas</h2>';
        $html .= '<p class="sub">Generado el ' . date('d-m-Y H:i:s') . '</p>';
        $html .= '<table><thead><tr>
            <th>Fecha registro</th><th>Cita / Cliente</th><th>Tipo cita</th>
            <th>Tipo pago</th><th>Método</th><th>Referencia</th>
            <th class="text-right">Monto</th><th>Estado</th>
        </tr></thead><tbody>';

        foreach ($rows as $r) {
            $fecha = $r['created_at'] ? date('d-m-Y H:i', strtotime($r['created_at'])) : '—';
            $cita  = $r['fecha_cita'] ? date('d-m-Y H:i', strtotime($r['fecha_cita'])) : '—';
            if ($r['nombre_cliente']) $cita .= ' — ' . htmlspecialchars($r['nombre_cliente']);
            elseif ($r['cita_titulo']) $cita .= ' — ' . htmlspecialchars($r['cita_titulo']);

            $html .= '<tr>
                <td>' . htmlspecialchars($fecha) . '</td>
                <td>' . $cita . '</td>
                <td>' . htmlspecialchars($r['nombre_tipo'] ?? '—') . '</td>
                <td>' . htmlspecialchars($tipoLabel[$r['tipo_pago']] ?? $r['tipo_pago']) . '</td>
                <td>' . htmlspecialchars($gatewayLabel[$r['gateway']] ?? $r['gateway']) . '</td>
                <td>' . htmlspecialchars($r['referencia_externa'] ?? '—') . '</td>
                <td class="text-right">$' . number_format((float)$r['monto'], 2) . '</td>
                <td>' . htmlspecialchars($estadoLabel[$r['estado']] ?? $r['estado']) . '</td>
            </tr>';
        }
        $html .= '</tbody></table></body></html>';

        try {
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('PagosCitas_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/plain');
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    // ─── EXPORT EXCEL ────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rawQ      = trim($_GET['q']    ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = trim($_GET['dir']  ?? 'DESC');
        $filtros   = [
            'estado'      => trim($_GET['estado']      ?? ''),
            'gateway'     => trim($_GET['gateway']     ?? ''),
            'tipo_pago'   => trim($_GET['tipo_pago']   ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
        ];

        $result = $this->service->getListado($idEmpresa, $rawQ, 1, 5000, $ordenCol, $ordenDir, $filtros);
        $rows   = $result['rows'];

        $estadoLabel  = ['pendiente' => 'Pendiente', 'completado' => 'Completado', 'fallido' => 'Fallido', 'reembolsado' => 'Reembolsado'];
        $gatewayLabel = ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'transferencia' => 'Transferencia', 'sitio' => 'En sitio', 'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta'];
        $tipoLabel    = ['total' => 'Total', 'anticipo' => 'Anticipo'];

        $headers    = ['Fecha registro', 'Fecha cita', 'Cliente', 'Tipo cita', 'Tipo pago', 'Método', 'Referencia', 'Monto', 'Estado'];
        $exportData = [];

        foreach ($rows as $r) {
            $exportData[] = [
                $r['created_at'] ? date('d-m-Y H:i', strtotime($r['created_at'])) : '',
                $r['fecha_cita'] ? date('d-m-Y H:i', strtotime($r['fecha_cita'])) : '',
                $r['nombre_cliente'] ?? '',
                $r['nombre_tipo']    ?? '',
                $tipoLabel[$r['tipo_pago']]   ?? $r['tipo_pago'],
                $gatewayLabel[$r['gateway']]  ?? $r['gateway'],
                $r['referencia_externa']      ?? '',
                (float) $r['monto'],
                $estadoLabel[$r['estado']]    ?? $r['estado'],
            ];
        }

        try {
            $reportService = new \App\Services\ReportService();
            $nombreEmpresa = $_SESSION['nombre_empresa'] ?? 'Empresa';
            $reportService->exportToExcel('PagosCitas', $headers, $exportData, 'Pagos de Citas', $nombreEmpresa);
        } catch (\Throwable $e) {
            header('Content-Type: text/plain');
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }
}
