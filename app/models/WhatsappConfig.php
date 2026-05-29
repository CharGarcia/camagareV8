<?php
/**
 * Modelo WhatsappConfig
 * Gestiona la configuración de la API de WhatsApp por empresa
 */

declare(strict_types=1);

namespace App\models;

class WhatsappConfig extends BaseModel
{
    /**
     * Obtiene la configuración de WhatsApp para una empresa.
     */
    public function obtenerConfiguracion(int $idEmpresa): ?array
    {
        $id = (int) $idEmpresa;
        $rows = $this->query(
            "SELECT * FROM empresa_whatsapp_config 
             WHERE id_empresa = {$id} AND eliminado = false 
             LIMIT 1"
        );
        
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Verifica si el token recibido por Meta coincide con alguna empresa.
     * Usado en la verificación GET del webhook (multitenant: una App, múltiples WABA).
     */
    public function verificarWebhookToken(string $token): bool
    {
        $token = $this->escape($token);
        $rows = $this->query(
            "SELECT id FROM empresa_whatsapp_config
             WHERE webhook_verify_token = '{$token}' AND eliminado = false
             LIMIT 1"
        );
        return !empty($rows);
    }

    /**
     * Guarda o actualiza la configuración.
     */
    public function guardarConfiguracion(int $idEmpresa, array $datos, int $idUsuario): bool
    {
        $id = (int) $idEmpresa;
        $idU = (int) $idUsuario;
        $accessToken = $this->escape($datos['access_token'] ?? '');
        $phoneId = $this->escape($datos['phone_number_id'] ?? '');
        $wabaId = $this->escape($datos['waba_id'] ?? '');
        $webhookToken = $this->escape($datos['webhook_verify_token'] ?? '');
        $appId = $this->escape($datos['app_id'] ?? '');
        
        $config = $this->obtenerConfiguracion($id);
        
        if ($config) {
            // Actualizar
            $sql = "UPDATE empresa_whatsapp_config 
                    SET access_token = '{$accessToken}', 
                        phone_number_id = '{$phoneId}', 
                        waba_id = '{$wabaId}', 
                        app_id = '{$appId}',
                        webhook_verify_token = '{$webhookToken}', 
                        updated_by = {$idU}, 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id_empresa = {$id} AND eliminado = false";
            return $this->execute($sql);
        } else {
            // Insertar
            $sql = "INSERT INTO empresa_whatsapp_config 
                    (id_empresa, access_token, phone_number_id, waba_id, app_id, webhook_verify_token, created_by) 
                    VALUES 
                    ({$id}, '{$accessToken}', '{$phoneId}', '{$wabaId}', '{$appId}', '{$webhookToken}', {$idU})";
            return $this->execute($sql);
        }
    }
}
