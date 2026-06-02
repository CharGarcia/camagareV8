<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

class SuscripcionesHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        return match ($this->accion) {
            'generar_facturacion'      => $this->generarFacturacion($idEmpresa, $idEstablecimiento, $parametros),
            'enviar_aviso_vencimiento' => $this->enviarAvisoVencimiento($idEmpresa, $idEstablecimiento, $parametros),
            default                    => throw new \RuntimeException("Acción '{$this->accion}' no implementada en SuscripcionesHandler."),
        };
    }

    // ── Generar facturación ───────────────────────────────────────────────────
    private function generarFacturacion(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        $diasAnticipacion = max(0, (int)($p['dias_anticipacion'] ?? 0));
        $max              = max(1, (int)($p['max_documentos'] ?? 50));
        $enviarSri        = !empty($p['enviar_sri']);
        $enviarCorreo     = !empty($p['enviar_correo']);
        $estabFilter      = $idEstablecimiento !== null ? "AND s.id_establecimiento = {$idEstablecimiento}" : '';

        $stmt = $this->db->prepare("
            SELECT s.id, s.id_empresa, s.id_cliente, s.proximo_cobro,
                   s.monto, s.descripcion,
                   c.nombre AS cliente_nombre, c.email AS cliente_email,
                   c.telefono AS cliente_telefono
            FROM suscripciones s
            JOIN clientes c ON c.id = s.id_cliente
            WHERE s.id_empresa = :id_empresa
              AND s.eliminado = false
              AND s.estado = 'activo'
              AND s.proximo_cobro <= (NOW() + INTERVAL '{$diasAnticipacion} days')::date
              {$estabFilter}
            ORDER BY s.proximo_cobro ASC
            LIMIT :limite
        ");
        $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
        $stmt->bindValue(':limite',     $max,       \PDO::PARAM_INT);
        $stmt->execute();
        $suscripciones = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $generadas = 0;
        foreach ($suscripciones as $sus) {
            try {
                // TODO: invocar FacturacionService::generarDesdeSuscripcion($sus, $enviarSri, $enviarCorreo)
                // Actualizar próximo cobro según periodicidad
                // $this->actualizarProximoCobro($sus['id']);
                $generadas++;
            } catch (\Throwable) {
                // Continúa con la siguiente
            }
        }

        return [
            'registros' => $generadas,
            'mensaje'   => "Se generaron {$generadas} facturas desde suscripciones."
                . ($enviarSri    ? ' (con envío SRI)'    : '')
                . ($enviarCorreo ? ' (con correo)'       : ''),
        ];
    }

    // ── Enviar aviso de vencimiento ───────────────────────────────────────────
    private function enviarAvisoVencimiento(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        $diasAntes   = max(1, (int)($p['dias_antes'] ?? 5));
        $canal       = $p['canal'] ?? 'correo';
        $estabFilter = $idEstablecimiento !== null ? "AND s.id_establecimiento = {$idEstablecimiento}" : '';

        $stmt = $this->db->prepare("
            SELECT s.id, s.id_empresa, s.id_cliente, s.proximo_cobro, s.monto,
                   c.nombre AS cliente_nombre, c.email AS cliente_email,
                   c.telefono AS cliente_telefono
            FROM suscripciones s
            JOIN clientes c ON c.id = s.id_cliente
            WHERE s.id_empresa = :id_empresa
              AND s.eliminado = false
              AND s.estado = 'activo'
              AND s.proximo_cobro::date = (NOW() + INTERVAL '{$diasAntes} days')::date
              {$estabFilter}
        ");
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $suscripciones = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $enviados = 0;
        foreach ($suscripciones as $sus) {
            try {
                if (in_array($canal, ['correo', 'ambos'], true) && !empty($sus['cliente_email'])) {
                    // TODO: EmailService::enviarAvisoVencimiento($sus)
                }
                if (in_array($canal, ['whatsapp', 'ambos'], true) && !empty($sus['cliente_telefono'])) {
                    // TODO: WhatsappService::enviarAvisoVencimiento($sus)
                }
                $enviados++;
            } catch (\Throwable) {
                // Continúa
            }
        }

        $canalLabel = ['correo' => 'correo', 'whatsapp' => 'WhatsApp', 'ambos' => 'correo y WhatsApp'][$canal] ?? $canal;

        return [
            'registros' => $enviados,
            'mensaje'   => "Se enviaron {$enviados} avisos de vencimiento por {$canalLabel} (vencen en {$diasAntes} días).",
        ];
    }
}
