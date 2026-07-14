<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class IaMensajeRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ia_mensajes');
    }

    public function getPorConversacion(int $idConversacion, int $idEmpresa): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_conversacion = :id_conversacion AND id_empresa = :id_empresa
                ORDER BY created_at ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_conversacion' => $idConversacion, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_conversacion, rol, contenido, fuentes,
                    tokens_entrada, tokens_salida, created_by, created_at
                ) VALUES (
                    :id_empresa, :id_conversacion, :rol, :contenido, :fuentes,
                    :tokens_entrada, :tokens_salida, :id_usuario, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $data['id_empresa'],
            ':id_conversacion' => $data['id_conversacion'],
            ':rol'             => $data['rol'],
            ':contenido'       => $data['contenido'],
            ':fuentes'         => isset($data['fuentes']) ? json_encode($data['fuentes'], JSON_UNESCAPED_UNICODE) : null,
            ':tokens_entrada'  => $data['tokens_entrada'] ?? null,
            ':tokens_salida'   => $data['tokens_salida'] ?? null,
            ':id_usuario'      => $data['id_usuario'] ?? null,
        ]);
        return (int) $this->db->lastInsertId('ia_mensajes_id_seq');
    }

    /**
     * Cuenta los mensajes de rol 'user' enviados en el último minuto por el
     * usuario en la conversación, para el límite de tasa (rate limiting).
     */
    public function contarUltimoMinuto(int $idConversacion, int $idEmpresa, int $idUsuario): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_conversacion = :id_conversacion
                  AND id_empresa = :id_empresa
                  AND created_by = :id_usuario
                  AND rol = 'user'
                  AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '1 minute')";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_conversacion' => $idConversacion,
            ':id_empresa'      => $idEmpresa,
            ':id_usuario'      => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }
}
