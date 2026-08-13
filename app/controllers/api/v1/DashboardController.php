<?php
/**
 * Controlador API v1: Dashboard (resumen de ventas y compras para la app móvil).
 * Adaptador HTTP→JSON puro: reutiliza DashboardService::getDashboardData() tal cual
 * la usa la web (App\controllers\modulos\DashboardController::dataAjax()), sin duplicar
 * el cálculo. Solo expone los campos de ventas/compras del mes actual — el resto del
 * dashboard web (CxC/CxP, saldos de caja, nómina, top productos, tendencia) queda fuera
 * del alcance de la app a propósito.
 *
 * Mismo permiso 'modulos/dashboard' que la web: solo visible para quien lo tenga
 * asignado en modulos_asignados (o nivel 3).
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\repositories\modulos\EmpresaRepository;
use App\Services\modulos\DashboardService;
use Throwable;

class DashboardController extends ApiBaseController
{
    protected function getRutaModulo(): string
    {
        return 'modulos/dashboard';
    }

    /**
     * GET /api/v1/dashboard/resumen?anio=&mes=
     * Ventas, compras, CxC y CxP del período seleccionado vs. el período anterior,
     * mismo tipo_ambiente activo de la empresa que usa por defecto el dashboard web.
     * anio: 0 o ausente = año actual. mes: 1-12 = mes específico, -1 = año completo,
     * 0 o ausente = mes actual — mismo contrato que DashboardService::getDashboardData().
     */
    public function resumen(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio = (int) ($_GET['anio'] ?? 0);
        $mes = (int) ($_GET['mes'] ?? 0);

        try {
            $emisor = (new EmpresaRepository())->getEmisorConfig($idEmpresa);
            $tipoAmbiente = (string) ($emisor['tipo_ambiente'] ?? '1');
            if ($tipoAmbiente === '') {
                $tipoAmbiente = '1';
            }

            $data = (new DashboardService())->getDashboardData($idEmpresa, $tipoAmbiente, $anio, $mes);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_DASHBOARD', $e->getMessage(), 500);
        }

        $this->jsonOk([
            'label_periodo'        => $data['label_periodo'],
            'anio'                 => $data['anio'],
            'mes'                  => $data['mes'],
            'ventas_mes_actual'    => $data['ventas_mes_actual'],
            'ventas_mes_anterior'  => $data['ventas_mes_anterior'],
            'compras_mes_actual'   => $data['compras_mes_actual'],
            'compras_mes_anterior' => $data['compras_mes_anterior'],
            'cxc_total'            => $data['cxc_total'],
            'cxp_total'            => $data['cxp_total'],
        ]);
    }
}
