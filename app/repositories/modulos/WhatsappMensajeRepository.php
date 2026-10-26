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

    /**
     * Ventana de atención de 24 h de Meta: se abre con cada mensaje que escribe el cliente.
     * Devuelve si sigue abierta y cuántos segundos le quedan (calculado en la BD, con su reloj).
     */
    public function getVentanaChat(int $idEmpresa, int $idChat): array
    {
        $stmt = $this->db->prepare("
            SELECT MAX(fecha_hora) > NOW() - INTERVAL '24 hours' AS abierta,
                   EXTRACT(EPOCH FROM (MAX(fecha_hora) + INTERVAL '24 hours' - NOW())) AS segundos
            FROM whatsapp_mensajes
            WHERE id_empresa = ? AND id_chat = ? AND direccion = 'IN' AND eliminado = FALSE
        ");
        $stmt->execute([$idEmpresa, $idChat]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $abierta = in_array($row['abierta'] ?? false, [true, 't', 1, '1'], true);
        return ['abierta' => $abierta, 'segundos' => $abierta ? (int) $row['segundos'] : 0];
    }

    /** Clientes activos con teléfono, para iniciar un chat (buscador del modal «Nuevo chat»). */
    public function buscarClientesConTelefono(int $idEmpresa, string $texto, int $limite = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, identificacion, telefono
            FROM clientes
            WHERE id_empresa = :id_empresa AND eliminado = FALSE AND status = 1
              AND COALESCE(TRIM(telefono), '') <> ''
              AND (nombre ILIKE :t_nombre OR identificacion ILIKE :t_ident OR telefono ILIKE :t_tel)
            ORDER BY nombre ASC
            LIMIT " . max(1, min(50, $limite))
        );
        $like = '%' . $texto . '%';
        $stmt->execute([':id_empresa' => $idEmpresa, ':t_nombre' => $like, ':t_ident' => $like, ':t_tel' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
