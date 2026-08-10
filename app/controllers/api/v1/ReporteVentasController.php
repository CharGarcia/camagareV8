<?php
/**
 * Controlador API v1: Reporte de Ventas (resumen para la app móvil).
 * Adaptador HTTP→JSON puro: reutiliza ReporteVentasRepository tal cual la web
 * (App\controllers\modulos\ReporteVentasController), sin duplicar el cálculo.
 *
 * Alcance móvil, más liviano que la web a propósito: estadísticas del rango +
 * agrupado por mes (tendencia) + agrupado por cliente. No incluye agrupado por
 * producto/variante ni exportar a PDF/Excel — eso se queda solo en la web.
 * Mismo permiso 'modulos/reporte_ventas' que la web.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\repositories\modulos\ReporteVentasRepository;

class ReporteVentasController extends ApiBaseController
{
    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_ventas';
    }

    /**
     * GET /api/v1/reporte-ventas/resumen?fecha_desde=&fecha_hasta=
     * Sin fechas: por defecto los últimos 6 meses (incluye el mes en curso).
     */
    public function resumen(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];

        $fechaDesde = trim($_GET['fecha_desde'] ?? '');
        $fechaHasta = trim($_GET['fecha_hasta'] ?? '');
        if ($fechaDesde === '' || $fechaHasta === '') {
            $fechaHasta = date('Y-m-d');
            $fechaDesde = date('Y-m-01', strtotime('-5 months'));
        }

        $filtros = [
            'tipo_documento' => 'FACTURA',
            'agrupar_por' => 'NINGUNO',
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'id_cliente' => '',
            'id_producto' => '',
            'producto_texto' => '',
            'variante_texto' => '',
            'estado' => 'TODOS',
            'buscar_info' => '',
        ];

        $repo = new ReporteVentasRepository();

        $this->jsonOk([
            'rango' => ['desde' => $fechaDesde, 'hasta' => $fechaHasta],
            'estadisticas' => $repo->getEstadisticas($idEmpresa, $filtros),
            'por_mes' => $repo->getReporteAgrupadoMes($idEmpresa, $filtros),
            'por_cliente' => $repo->getReporteAgrupadoCliente($idEmpresa, $filtros),
        ]);
    }
}
