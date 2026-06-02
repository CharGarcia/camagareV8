<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

class InventarioHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        return match ($this->accion) {
            'alerta_stock_minimo' => $this->alertaStockMinimo($idEmpresa, $idEstablecimiento, $parametros),
            default               => throw new \RuntimeException("Acción '{$this->accion}' no implementada en InventarioHandler."),
        };
    }

    private function alertaStockMinimo(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        $bodegaWhere = !empty($p['bodega_id']) ? "AND i.id_bodega = " . (int)$p['bodega_id'] : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM inventario i
            JOIN productos pr ON pr.id = i.id_producto
            WHERE i.id_empresa = :id_empresa
              AND i.eliminado = false
              AND pr.eliminado = false
              AND pr.stock_minimo IS NOT NULL
              AND i.cantidad <= pr.stock_minimo
              {$bodegaWhere}
        ");
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $total = (int)$stmt->fetchColumn();

        // TODO: enviar email con el reporte si hay destinatario configurado

        return [
            'registros' => $total,
            'mensaje'   => "Se detectaron {$total} productos bajo el stock mínimo.",
        ];
    }
}
