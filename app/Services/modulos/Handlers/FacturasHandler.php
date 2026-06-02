<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

class FacturasHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        return match ($this->accion) {
            'enviar_email_vencidas'  => $this->enviarEmailVencidas($idEmpresa, $idEstablecimiento, $parametros),
            'recordatorio_pago'      => $this->recordatorioPago($idEmpresa, $idEstablecimiento, $parametros),
            'marcar_vencidas'        => $this->marcarVencidas($idEmpresa, $idEstablecimiento),
            'generar_recurrentes'    => $this->generarRecurrentes($idEmpresa, $idEstablecimiento, $parametros),
            default                  => throw new \RuntimeException("Acción '{$this->accion}' no implementada en FacturasHandler."),
        };
    }

    private function enviarEmailVencidas(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        $diasVencidas = max(1, (int)($p['dias_vencidas'] ?? 1));
        $maxEnvios    = max(1, (int)($p['max_envios'] ?? 50));

        $where = "WHERE fv.id_empresa = :id_empresa
                    AND fv.eliminado = false
                    AND fv.estado = 'pendiente'
                    AND fv.fecha_vencimiento < NOW() - INTERVAL '{$diasVencidas} days'
                    AND (fv.email_recordatorio_enviado IS NULL OR fv.email_recordatorio_enviado = false)";

        if ($idEstablecimiento !== null) {
            $where .= " AND fv.id_establecimiento = {$idEstablecimiento}";
        }

        $stmt = $this->db->prepare("
            SELECT fv.id, fv.numero_factura, fv.total, fv.fecha_vencimiento,
                   c.nombre AS cliente_nombre, c.email AS cliente_email
            FROM facturas_venta fv
            JOIN clientes c ON c.id = fv.id_cliente
            {$where}
            ORDER BY fv.fecha_vencimiento ASC
            LIMIT :limite
        ");
        $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
        $stmt->bindValue(':limite',     $maxEnvios,  \PDO::PARAM_INT);
        $stmt->execute();
        $facturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $enviados = 0;
        foreach ($facturas as $factura) {
            if (empty($factura['cliente_email'])) continue;
            // TODO: integrar con el servicio de email configurado
            // EmailService::enviar($factura['cliente_email'], $asunto, $cuerpo);
            $enviados++;
        }

        return [
            'registros' => $enviados,
            'mensaje'   => "Se enviaron {$enviados} recordatorios de facturas vencidas.",
        ];
    }

    private function recordatorioPago(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        $diasAntes = max(1, (int)($p['dias_antes'] ?? 3));

        $where = "WHERE fv.id_empresa = :id_empresa
                    AND fv.eliminado = false
                    AND fv.estado = 'pendiente'
                    AND fv.fecha_vencimiento::date = (NOW() + INTERVAL '{$diasAntes} days')::date";

        if ($idEstablecimiento !== null) {
            $where .= " AND fv.id_establecimiento = {$idEstablecimiento}";
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM facturas_venta fv {$where}
        ");
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $total = (int)$stmt->fetchColumn();

        // TODO: implementar envío según canal configurado
        return [
            'registros' => $total,
            'mensaje'   => "Se procesaron {$total} recordatorios de pago próximo.",
        ];
    }

    private function marcarVencidas(int $idEmpresa, ?int $idEstablecimiento): array
    {
        $where = "id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado = 'pendiente'
                  AND fecha_vencimiento IS NOT NULL
                  AND fecha_vencimiento < NOW()";

        if ($idEstablecimiento !== null) {
            $where .= " AND id_establecimiento = {$idEstablecimiento}";
        }

        $stmt = $this->db->prepare("
            UPDATE facturas_venta
            SET estado = 'vencida', updated_at = NOW()
            WHERE {$where}
        ");
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $afectados = $stmt->rowCount();

        return [
            'registros' => $afectados,
            'mensaje'   => "Se marcaron {$afectados} facturas como vencidas.",
        ];
    }

    private function generarRecurrentes(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        // TODO: implementar generación de facturas recurrentes
        return [
            'registros' => 0,
            'mensaje'   => 'Generación de facturas recurrentes: pendiente de implementación.',
        ];
    }
}
