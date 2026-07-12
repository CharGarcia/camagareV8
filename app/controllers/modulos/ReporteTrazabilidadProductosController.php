<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\TrazabilidadProductoService;
use App\Services\modulos\TrazabilidadProductoPdfService;

/**
 * Reporte de solo lectura: línea de tiempo de un producto (creación/modificación
 * del catálogo + movimientos de kardex resueltos contra su documento de origen).
 * No escribe en ninguna tabla.
 */
class ReporteTrazabilidadProductosController extends BaseModuloController
{
    private TrazabilidadProductoService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_trazabilidad_productos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new TrazabilidadProductoService();
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos/reporte_trazabilidad_productos/index', [
            'titulo'     => 'Trazabilidad de Productos',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'base'       => BASE_URL,
        ]);
    }

    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        $data = $this->service->buscarProductos($idEmpresa, $q);
        $this->json(['ok' => true, 'data' => $data]);
    }

    public function timelineAjax(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);

        if ($idProducto <= 0) {
            $this->json(['ok' => false, 'mensaje' => 'Seleccione un producto.']);
        }

        try {
            $data = $this->service->getLineaTiempo($idProducto, $idEmpresa, $this->leerFiltros());
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => 'Error al obtener la trazabilidad: ' . $e->getMessage()]);
        }

        if ($data === null) {
            $this->json(['ok' => false, 'mensaje' => 'Producto no encontrado.']);
        }

        $this->json(['ok' => true, 'data' => $data]);
    }

    public function exportarPdf(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);

        $data = $idProducto > 0 ? $this->service->getLineaTiempo($idProducto, $idEmpresa, $this->leerFiltros()) : null;
        if ($data === null) {
            $this->json(['ok' => false, 'mensaje' => 'Producto no encontrado.']);
        }

        $idEmpresaSesion = (int) ($_SESSION['id_empresa'] ?? 0);
        $empresa = [];
        if ($idEmpresaSesion > 0) {
            try {
                $empresa = (new \App\models\Empresa())->getPorId($idEmpresaSesion) ?: [];
            } catch (\Throwable $e) {
                $empresa = [];
            }
        }

        (new TrazabilidadProductoPdfService())->generar($data, $empresa, (string) ($_SESSION['nombre'] ?? ''), 'I');
        exit;
    }

    public function exportarExcel(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);

        $data = $idProducto > 0 ? $this->service->getLineaTiempo($idProducto, $idEmpresa, $this->leerFiltros()) : null;
        if ($data === null) {
            $this->json(['ok' => false, 'mensaje' => 'Producto no encontrado.']);
        }

        $fecha = date('Ymd_His');
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="trazabilidad_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $data['producto']['codigo']) . '_' . $fecha . '.xls"');
        echo "\xEF\xBB\xBF";
        echo $this->tablaExportHtml($data);
        exit;
    }

    private function leerFiltros(): array
    {
        $rgxFecha = '/^\d{4}-\d{2}-\d{2}$/';
        $desde = trim($_GET['desde'] ?? $_POST['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? $_POST['hasta'] ?? '');

        return [
            'desde' => preg_match($rgxFecha, $desde) ? $desde : null,
            'hasta' => preg_match($rgxFecha, $hasta) ? $hasta : null,
        ];
    }

    private function tablaExportHtml(array $data): string
    {
        ob_start();
        echo '<p><strong>' . htmlspecialchars($data['producto']['codigo'] . ' - ' . $data['producto']['nombre']) . '</strong></p>';
        echo '<table><thead><tr>'
            . '<th>Fecha</th><th>Evento</th><th>Documento</th><th>Contraparte</th>'
            . '<th>Movimiento</th><th>Cantidad</th><th>Saldo</th><th>Lote</th><th>Bodega</th><th>Usuario</th>'
            . '</tr></thead><tbody>';
        foreach ($data['eventos'] as $e) {
            echo '<tr>'
                . '<td>' . htmlspecialchars((string) $e['fecha']) . '</td>'
                . '<td>' . htmlspecialchars((string) $e['titulo']) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['doc_numero'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['doc_contraparte'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['tipo_movimiento'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars(isset($e['cantidad']) ? (string) $e['cantidad'] : '-') . '</td>'
                . '<td>' . htmlspecialchars(isset($e['stock_posterior']) ? (string) $e['stock_posterior'] : '-') . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['numero_lote'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['bodega'] ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($e['usuario'] ?? '-')) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table>';
        return (string) ob_get_clean();
    }
}
