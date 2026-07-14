<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos de la configuración BYOK de IA por empresa (tabla ia_config).
 * api_key_cifrada nunca sale de aquí en claro: se cifra/descifra en el Service.
 */
class IaConfigRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ia_config');
    }

    public function getByEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea o reemplaza la configuración de la empresa (1 fila por empresa).
     */
    public function upsert(int $idEmpresa, array $data, int $idUsuario): int
    {
        $existente = $this->getByEmpresa($idEmpresa);

        if ($existente === null) {
            $sql = "INSERT INTO {$this->table} (
                        id_empresa, proveedor, api_key_cifrada, modelo_chat, activo,
                        created_by, updated_by, created_at, updated_at, eliminado
                    ) VALUES (
                        :id_empresa, :proveedor, :api_key, :modelo, :activo,
                        :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                    )";
            $st = $this->db->prepare($sql);
            $st->execute([
                ':id_empresa' => $idEmpresa,
                ':proveedor'  => $data['proveedor'],
                ':api_key'    => $data['api_key_cifrada'],
                ':modelo'     => $data['modelo_chat'],
                ':activo'     => $data['activo'] ?? true,
                ':id_usuario' => $idUsuario,
            ]);
            return (int) $this->db->lastInsertId('ia_config_id_seq');
        }

        // Si no vino una nueva key (reemplazo opcional), conservar la actual.
        $apiKey = $data['api_key_cifrada'] !== null ? $data['api_key_cifrada'] : $existente['api_key_cifrada'];

        $sql = "UPDATE {$this->table} SET
                    proveedor = :proveedor,
                    api_key_cifrada = :api_key,
                    modelo_chat = :modelo,
                    activo = :activo,
                    updated_by = :id_usuario,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':proveedor'  => $data['proveedor'],
            ':api_key'    => $apiKey,
            ':modelo'     => $data['modelo_chat'],
            ':activo'     => $data['activo'] ?? true,
            ':id_usuario' => $idUsuario,
            ':id_empresa' => $idEmpresa,
        ]);
        return (int) $existente['id'];
    }
}
