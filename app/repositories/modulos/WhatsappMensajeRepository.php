<?php

namespace App\repositories\modulos;

use App\core\Database;

class WhatsappMensajeRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca el id_empresa dado un phone_number_id.
     */
    public function getEmpresaIdByPhoneNumberId(string $phoneNumberId): ?int
    {
        $stmt = $this->db->prepare("SELECT id_empresa FROM empresa_whatsapp_config WHERE phone_number_id = ? AND status = TRUE AND eliminado = FALSE LIMIT 1");
        $stmt->execute([$phoneNumberId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id_empresa'] : null;
    }

    /**
     * Actualiza el estado de un mensaje saliente a travs de su meta_message_id.
     */
    public function updateMessageStatus(string $metaMessageId, string $status, ?string $errorMessage = null): bool
    {
        $stmt = $this->db->prepare("UPDATE whatsapp_mensajes SET estado_meta = ?, error_message = ?, updated_at = CURRENT_TIMESTAMP WHERE meta_message_id = ?");
        return $stmt->execute([$status, $errorMessage, $metaMessageId]);
    }

    /**
     * Crea o recupera el id_chat para un telfono dado.
     */
    public function getOrCreateChat(int $idEmpresa, string $telefono, string $nombreCliente = '', string $ultimoMensaje = '', bool $esEntrante = false): int
    {
        $stmt = $this->db->prepare("SELECT id, mensajes_sin_leer FROM whatsapp_chats WHERE id_empresa = ? AND telefono_cliente = ? LIMIT 1");
        $stmt->execute([$idEmpresa, $telefono]);
        $chat = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($chat) {
            $idChat = (int)$chat['id'];
            $sinLeer = (int)$chat['mensajes_sin_leer'];
            if ($esEntrante) {
                $sinLeer++;
            }
            
            $updateSql = "UPDATE whatsapp_chats SET ultimo_mensaje = ?, mensajes_sin_leer = ?, updated_at = CURRENT_TIMESTAMP";
            $params = [$ultimoMensaje, $sinLeer];
            
            if ($nombreCliente !== '') {
                $updateSql .= ", nombre_cliente = ?";
                $params[] = $nombreCliente;
            }
            $updateSql .= " WHERE id = ?";
            $params[] = $idChat;

            $stmtUpdate = $this->db->prepare($updateSql);
            $stmtUpdate->execute($params);

            return $idChat;
        }

        // Crear nuevo
        $sinLeer = $esEntrante ? 1 : 0;
        $stmtInsert = $this->db->prepare("INSERT INTO whatsapp_chats (id_empresa, telefono_cliente, nombre_cliente, ultimo_mensaje, mensajes_sin_leer, created_by) VALUES (?, ?, ?, ?, ?, 0) RETURNING id");
        $stmtInsert->execute([$idEmpresa, $telefono, $nombreCliente, $ultimoMensaje, $sinLeer]);
        return (int)$stmtInsert->fetchColumn();
    }

    /**
     * Guarda un nuevo mensaje en el historial.
     */
    public function saveMessage(int $idEmpresa, int $idChat, string $direccion, string $telefono, string $tipo, array $contenido, ?string $metaMessageId, string $estado = 'sent'): int
    {
        $jsonContenido = json_encode($contenido, JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare("
            INSERT INTO whatsapp_mensajes (id_empresa, id_chat, direccion, telefono_cliente, tipo_mensaje, contenido, meta_message_id, estado_meta, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0) RETURNING id
        ");
        $stmt->execute([
            $idEmpresa,
            $idChat,
            $direccion,
            $telefono,
            $tipo,
            $jsonContenido,
            $metaMessageId,
            $estado
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function countUnreadChats(int $idEmpresa): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM whatsapp_chats WHERE id_empresa = ? AND mensajes_sin_leer > 0");
        $stmt->execute([$idEmpresa]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Actualiza mensajes sin leer a cero
     */
    public function resetUnread(int $idChat): void
    {
        $stmt = $this->db->prepare("UPDATE whatsapp_chats SET mensajes_sin_leer = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$idChat]);
    }

    /**
     * Obtiene la lista de chats para una empresa.
     */
    public function getChatsList(int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT id, telefono_cliente, nombre_cliente, ultimo_mensaje, mensajes_sin_leer, updated_at
            FROM whatsapp_chats
            WHERE id_empresa = ? AND eliminado = FALSE
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$idEmpresa]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los mensajes de un chat especfico.
     */
    public function getChatMessages(int $idEmpresa, int $idChat): array
    {
        $stmt = $this->db->prepare("
            SELECT id, direccion, tipo_mensaje, contenido, estado_meta, error_message, fecha_hora
            FROM whatsapp_mensajes
            WHERE id_empresa = ? AND id_chat = ? AND eliminado = FALSE
            ORDER BY fecha_hora ASC
        ");
        $stmt->execute([$idEmpresa, $idChat]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
