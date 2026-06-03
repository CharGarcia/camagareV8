<?php

declare(strict_types=1);

namespace App\Services;

use App\core\Database;
use PDO;

class DashboardService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @param string $tipoAmbiente Ambiente activo de la empresa: '1'=Pruebas, '2'=Producción.
     *                             Las cifras de VENTAS se filtran por este ambiente.
     *                             Las de COMPRAS no (son comprobantes recibidos, sin ambiente).
     */
    public function getDashboardData(int $idEmpresa, string $tipoAmbiente = '1'): array
    {
        return [
            'ventas_mes_actual' => $this->getTotalVentasMes($idEmpresa, 0, $tipoAmbiente),
            'ventas_mes_anterior' => $this->getTotalVentasMes($idEmpresa, 1, $tipoAmbiente),
            'compras_mes_actual' => $this->getTotalComprasMes($idEmpresa, 0),
            'compras_mes_anterior' => $this->getTotalComprasMes($idEmpresa, 1),
            'facturas_recientes' => $this->getVentasRecientes($idEmpresa, 5, $tipoAmbiente),
            'compras_recientes' => $this->getComprasRecientes($idEmpresa, 5),
            'tendencia_6_meses' => $this->getTendenciaMensual($idEmpresa, 6, $tipoAmbiente),
        ];
    }

    private function getTotalVentasMes(int $idEmpresa, int $mesesAtras, string $tipoAmbiente = '1'): float
    {
        $sql = "SELECT COALESCE(SUM(importe_total), 0) as total
                FROM ventas_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado != 'anulado'
                  AND COALESCE(tipo_ambiente, '1') = :tipo_ambiente
                  AND EXTRACT(MONTH FROM CAST(fecha_emision AS DATE)) = EXTRACT(MONTH FROM CURRENT_DATE - INTERVAL '$mesesAtras months')
                  AND EXTRACT(YEAR FROM CAST(fecha_emision AS DATE)) = EXTRACT(YEAR FROM CURRENT_DATE - INTERVAL '$mesesAtras months')";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':tipo_ambiente' => $tipoAmbiente]);
        return (float) $st->fetchColumn();
    }

    private function getTotalComprasMes(int $idEmpresa, int $mesesAtras): float
    {
        $sql = "SELECT COALESCE(SUM(importe_total), 0) as total 
                FROM compras_cabecera 
                WHERE id_empresa = :id_empresa 
                  AND eliminado = false 
                  AND EXTRACT(MONTH FROM CAST(fecha_emision AS DATE)) = EXTRACT(MONTH FROM CURRENT_DATE - INTERVAL '$mesesAtras months')
                  AND EXTRACT(YEAR FROM CAST(fecha_emision AS DATE)) = EXTRACT(YEAR FROM CURRENT_DATE - INTERVAL '$mesesAtras months')";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return (float) $st->fetchColumn();
    }

    private function getVentasRecientes(int $idEmpresa, int $limit, string $tipoAmbiente = '1'): array
    {
        $sql = "SELECT c.nombre as entidad, v.importe_total as total, v.fecha_emision as fecha, v.estado,
                       CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) as comprobante
                FROM ventas_cabecera v
                INNER JOIN clientes c ON c.id = v.id_cliente
                WHERE v.id_empresa = :id_empresa AND v.eliminado = false
                  AND COALESCE(v.tipo_ambiente, '1') = :tipo_ambiente
                ORDER BY v.fecha_emision DESC, v.id DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':tipo_ambiente', $tipoAmbiente);
        $st->bindValue(':limite', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getComprasRecientes(int $idEmpresa, int $limit): array
    {
        $sql = "SELECT p.razon_social as entidad, c.importe_total as total, c.fecha_emision as fecha, 'registrado' as estado,
                       CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) as comprobante
                FROM compras_cabecera c 
                INNER JOIN proveedores p ON p.id = c.id_proveedor 
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false 
                ORDER BY c.fecha_emision DESC, c.id DESC 
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':limite', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTendenciaMensual(int $idEmpresa, int $meses, string $tipoAmbiente = '1'): array
    {
        // Obtener los últimos N meses (formato YYYY-MM) generados por PHP para evitar huecos vacíos
        $mesesData = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $mesesData[date('Y-m', strtotime("-$i months"))] = [
                'mes' => date('M Y', strtotime("-$i months")),
                'ventas' => 0,
                'compras' => 0
            ];
        }

        $sqlVentas = "SELECT TO_CHAR(CAST(fecha_emision AS DATE), 'YYYY-MM') as mes_key, SUM(importe_total) as total
                      FROM ventas_cabecera
                      WHERE id_empresa = :id_empresa AND eliminado = false AND estado != 'anulado'
                        AND COALESCE(tipo_ambiente, '1') = :tipo_ambiente
                        AND CAST(fecha_emision AS DATE) >= CURRENT_DATE - INTERVAL '$meses months'
                      GROUP BY TO_CHAR(CAST(fecha_emision AS DATE), 'YYYY-MM')";
        $stV = $this->db->prepare($sqlVentas);
        $stV->execute([':id_empresa' => $idEmpresa, ':tipo_ambiente' => $tipoAmbiente]);
        foreach ($stV->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($mesesData[$row['mes_key']])) {
                $mesesData[$row['mes_key']]['ventas'] = (float)$row['total'];
            }
        }

        $sqlCompras = "SELECT TO_CHAR(CAST(fecha_emision AS DATE), 'YYYY-MM') as mes_key, SUM(importe_total) as total 
                       FROM compras_cabecera 
                       WHERE id_empresa = :id_empresa AND eliminado = false 
                         AND CAST(fecha_emision AS DATE) >= CURRENT_DATE - INTERVAL '$meses months'
                       GROUP BY TO_CHAR(CAST(fecha_emision AS DATE), 'YYYY-MM')";
        $stC = $this->db->prepare($sqlCompras);
        $stC->execute([':id_empresa' => $idEmpresa]);
        foreach ($stC->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($mesesData[$row['mes_key']])) {
                $mesesData[$row['mes_key']]['compras'] = (float)$row['total'];
            }
        }

        return array_values($mesesData);
    }
}
