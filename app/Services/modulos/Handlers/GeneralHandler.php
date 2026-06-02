<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

class GeneralHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        return match ($this->accion) {
            'limpiar_sesiones' => $this->limpiarSesiones($parametros),
            'reporte_diario'   => $this->reporteDiario($idEmpresa, $parametros),
            default            => throw new \RuntimeException("Acción '{$this->accion}' no implementada en GeneralHandler."),
        };
    }

    private function limpiarSesiones(array $p): array
    {
        $dias = max(1, (int)($p['dias_expiracion'] ?? 30));

        $stmt = $this->db->prepare("
            DELETE FROM sesiones
            WHERE ultima_actividad < NOW() - INTERVAL '{$dias} days'
        ");
        $stmt->execute();
        $eliminados = $stmt->rowCount();

        return [
            'registros' => $eliminados,
            'mensaje'   => "Se eliminaron {$eliminados} sesiones expiradas.",
        ];
    }

    private function reporteDiario(int $idEmpresa, array $p): array
    {
        // TODO: construir y enviar reporte diario por email
        return [
            'registros' => 0,
            'mensaje'   => 'Reporte diario: pendiente de implementación.',
        ];
    }
}
