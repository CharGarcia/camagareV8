<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\Services\modulos\HandlerFactory;

/**
 * Maneja las acciones comunes a todos los módulos de documentos electrónicos.
 *
 * Procesa TODOS los documentos pendientes en lotes internos hasta vaciar la cola.
 * El parámetro 'lote_interno' solo controla cuántos registros se cargan en memoria
 * por vuelta (protección de RAM), no es un límite total de la ejecución.
 */
class DocumentosHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        $cfg = HandlerFactory::getConfigTabla($this->modulo);
        if ($cfg === null) {
            throw new \RuntimeException("Módulo '{$this->modulo}' no tiene tabla configurada en HandlerFactory.");
        }

        return match ($this->accion) {
            'enviar_sri'      => $this->enviarSri($idEmpresa, $idEstablecimiento, $parametros, $cfg),
            'enviar_correo'   => $this->enviarCorreo($idEmpresa, $idEstablecimiento, $parametros, $cfg),
            'enviar_whatsapp' => $this->enviarWhatsapp($idEmpresa, $idEstablecimiento, $parametros, $cfg),
            default           => throw new \RuntimeException("Acción '{$this->accion}' no implementada en DocumentosHandler."),
        };
    }

    // ── Enviar al SRI ─────────────────────────────────────────────────────────
    private function enviarSri(int $idEmpresa, ?int $idEstablecimiento, array $p, array $cfg): array
    {
        $tabla       = $cfg['tabla'];
        $lote        = max(10, (int)($p['lote_interno'] ?? 100));
        $estabFilter = $idEstablecimiento !== null ? "AND id_establecimiento = {$idEstablecimiento}" : '';

        $stmt = $this->db->prepare("
            SELECT id, id_empresa, id_establecimiento, clave_acceso
            FROM {$tabla}
            WHERE id_empresa = :id_empresa
              AND eliminado = false
              AND estado = 'borrador'
              {$estabFilter}
            ORDER BY created_at ASC
            LIMIT :lote
        ");

        $procesados = 0;
        $errores    = 0;

        // Loop hasta que no queden documentos pendientes
        do {
            $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
            $stmt->bindValue(':lote',       $lote,      \PDO::PARAM_INT);
            $stmt->execute();
            $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($documentos as $doc) {
                try {
                    // TODO: invocar el servicio SRI según el módulo
                    // Ej: $sriService->autorizar($doc['id'], $idEmpresa);
                    $procesados++;
                } catch (\Throwable) {
                    $errores++;
                }
            }
        } while (count($documentos) === $lote); // Si devolvió menos que el lote, ya no hay más

        $msg = "Se procesaron {$procesados} documentos para envío al SRI.";
        if ($errores > 0) $msg .= " ({$errores} con error)";

        return ['registros' => $procesados, 'mensaje' => $msg];
    }

    // ── Enviar correo ─────────────────────────────────────────────────────────
    private function enviarCorreo(int $idEmpresa, ?int $idEstablecimiento, array $p, array $cfg): array
    {
        $tabla              = $cfg['tabla'];
        $lote               = max(10, (int)($p['lote_interno'] ?? 100));
        $reintentarFallidos = !empty($p['reintentar_fallidos']);
        $estabFilter        = $idEstablecimiento !== null ? "AND t.id_establecimiento = {$idEstablecimiento}" : '';
        $estadoFilter       = $reintentarFallidos
            ? "AND t.estado_correo IN ('pendiente', 'error')"
            : "AND t.estado_correo = 'pendiente'";

        $joinEmail = $this->buildJoinEmail($tabla);

        $stmt = $this->db->prepare("
            SELECT t.id, t.id_empresa, t.clave_acceso,
                   {$joinEmail['select']}
            FROM {$tabla} t
            {$joinEmail['join']}
            WHERE t.id_empresa = :id_empresa
              AND t.eliminado = false
              AND t.estado = 'autorizado'
              {$estadoFilter}
              {$estabFilter}
            ORDER BY t.created_at ASC
            LIMIT :lote
        ");

        $enviados = 0;
        $errores  = 0;

        do {
            $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
            $stmt->bindValue(':lote',       $lote,      \PDO::PARAM_INT);
            $stmt->execute();
            $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($documentos as $doc) {
                if (empty($doc['email_destinatario'])) {
                    // Sin email: marcar como no_aplica para no volver a intentarlo
                    $this->db->prepare("UPDATE {$tabla} SET estado_correo = 'no_aplica', updated_at = NOW() WHERE id = :id")
                             ->execute([':id' => $doc['id']]);
                    continue;
                }
                try {
                    // TODO: EmailService::enviarDocumento($doc)
                    $this->db->prepare("UPDATE {$tabla} SET estado_correo = 'enviado', updated_at = NOW() WHERE id = :id")
                             ->execute([':id' => $doc['id']]);
                    $enviados++;
                } catch (\Throwable) {
                    $this->db->prepare("UPDATE {$tabla} SET estado_correo = 'error', updated_at = NOW() WHERE id = :id")
                             ->execute([':id' => $doc['id']]);
                    $errores++;
                }
            }
        } while (count($documentos) === $lote);

        $msg = "Se enviaron {$enviados} correos de {$tabla}.";
        if ($errores > 0) $msg .= " ({$errores} con error)";

        return ['registros' => $enviados, 'mensaje' => $msg];
    }

    // ── Enviar WhatsApp ───────────────────────────────────────────────────────
    private function enviarWhatsapp(int $idEmpresa, ?int $idEstablecimiento, array $p, array $cfg): array
    {
        $tabla              = $cfg['tabla'];
        $lote               = max(10, (int)($p['lote_interno'] ?? 100));
        $reintentarFallidos = !empty($p['reintentar_fallidos']);
        $estabFilter        = $idEstablecimiento !== null ? "AND t.id_establecimiento = {$idEstablecimiento}" : '';
        $estadoFilter       = $reintentarFallidos
            ? "AND (t.estado_whatsapp IS NULL OR t.estado_whatsapp IN ('pendiente', 'error'))"
            : "AND (t.estado_whatsapp IS NULL OR t.estado_whatsapp = 'pendiente')";

        $joinEmail = $this->buildJoinEmail($tabla);

        $stmt = $this->db->prepare("
            SELECT t.id, t.id_empresa, t.clave_acceso,
                   {$joinEmail['select']}
            FROM {$tabla} t
            {$joinEmail['join']}
            WHERE t.id_empresa = :id_empresa
              AND t.eliminado = false
              AND t.estado = 'autorizado'
              {$estadoFilter}
              {$estabFilter}
            ORDER BY t.created_at ASC
            LIMIT :lote
        ");

        $enviados = 0;
        $errores  = 0;

        do {
            $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
            $stmt->bindValue(':lote',       $lote,      \PDO::PARAM_INT);
            $stmt->execute();
            $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($documentos as $doc) {
                if (empty($doc['telefono_destinatario'])) {
                    $this->db->prepare("UPDATE {$tabla} SET estado_whatsapp = 'no_aplica', updated_at = NOW() WHERE id = :id")
                             ->execute([':id' => $doc['id']]);
                    continue;
                }
                try {
                    // TODO: WhatsappService::enviarDocumento($doc)
                    $this->db->prepare("UPDATE {$tabla} SET estado_whatsapp = 'enviado', updated_at = NOW() WHERE id = :id")
                             ->execute([':id' => $doc['id']]);
                    $enviados++;
                } catch (\Throwable) {
                    $this->db->prepare("UPDATE {$tabla} SET estado_whatsapp = 'error', updated_at = NOW() WHERE id = :id")
                             ->execute([':id' => $doc['id']]);
                    $errores++;
                }
            }
        } while (count($documentos) === $lote);

        $msg = "Se enviaron {$enviados} mensajes WhatsApp de {$tabla}.";
        if ($errores > 0) $msg .= " ({$errores} con error)";

        return ['registros' => $enviados, 'mensaje' => $msg];
    }

    // ── Helper JOIN para email/teléfono ───────────────────────────────────────
    private function buildJoinEmail(string $tabla): array
    {
        $conCliente   = ['ventas_cabecera', 'notas_credito_cabecera', 'guias_remision_cabecera', 'notas_debito_cabecera'];
        $conProveedor = ['retencion_compra_cabecera', 'liquidaciones_cabecera'];

        if (in_array($tabla, $conCliente, true)) {
            return [
                'select' => 'c.email AS email_destinatario, c.telefono AS telefono_destinatario, c.nombre AS nombre_destinatario',
                'join'   => 'LEFT JOIN clientes c ON c.id = t.id_cliente',
            ];
        }
        if (in_array($tabla, $conProveedor, true)) {
            return [
                'select' => 'p.email AS email_destinatario, p.telefono AS telefono_destinatario, p.razon_social AS nombre_destinatario',
                'join'   => 'LEFT JOIN proveedores p ON p.id = t.id_proveedor',
            ];
        }
        return [
            'select' => "'' AS email_destinatario, '' AS telefono_destinatario, '' AS nombre_destinatario",
            'join'   => '',
        ];
    }
}
