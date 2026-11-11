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
     * Empresas activas que tienen IA configurada con un proveedor dado.
     *
     * Listado GLOBAL a propósito (cruza empresas): solo debe usarse para el
     * superadministrador (nivel 3), que es quien puede prestar la IA de una
     * empresa a otra. Nunca devuelve la clave.
     *
     * @return list<array{id_empresa:int, nombre:string, ruc:string}>
     */
    public function listarEmpresasConIa(string $proveedor = 'anthropic'): array
    {
        $st = $this->db->prepare(
            "SELECT e.id AS id_empresa, COALESCE(NULLIF(TRIM(e.nombre_comercial), ''), e.nombre) AS nombre, e.ruc
               FROM {$this->table} c
               JOIN empresas e ON e.id = c.id_empresa AND e.eliminado = false AND e.estado = '1'
              WHERE c.eliminado = false
                AND c.proveedor = :proveedor
                AND COALESCE(c.api_key_cifrada, '') <> ''
              ORDER BY 2"
        );
        $st->execute([':proveedor' => $proveedor]);
        return array_map(static fn(array $f): array => [
            'id_empresa' => (int) $f['id_empresa'],
            'nombre'     => (string) $f['nombre'],
            'ruc'        => (string) $f['ruc'],
        ], $st->fetchAll(PDO::FETCH_ASSOC));
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
                ':activo'     => ($data['activo'] ?? true) ? 'true' : 'false',
                ':id_usuario' => $idUsuario,
            ]);
            $id = (int) $this->db->lastInsertId('ia_config_id_seq');
            $this->guardarEsfuerzo($idEmpresa, $data);
            return $id;
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
            ':activo'     => ($data['activo'] ?? true) ? 'true' : 'false',
            ':id_usuario' => $idUsuario,
            ':id_empresa' => $idEmpresa,
        ]);
        $this->guardarEsfuerzo($idEmpresa, $data);
        return (int) $existente['id'];
    }

    /** ia_config.esfuerzo (SQL del CRM fase 2). Sin la columna, no hace nada. */
    private function guardarEsfuerzo(int $idEmpresa, array $data): void
    {
        if (!array_key_exists('esfuerzo', $data) || !$this->columnaExiste('ia_config', 'esfuerzo')) {
            return;
        }
        $st = $this->db->prepare("UPDATE {$this->table} SET esfuerzo = :s WHERE id_empresa = :e AND eliminado = false");
        $st->execute([':s' => $data['esfuerzo'], ':e' => $idEmpresa]);
    }
}
