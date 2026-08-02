<?php
declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Acceso a datos puro de `registros_en_uso`. Sin lógica de negocio: la política
 * (TTL, qué significa "propio", etc.) vive en App\Services\BloqueoEdicionService.
 */
class BloqueoEdicionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function limpiarExpirados(string $tabla, int $idRegistro, int $idEmpresa, int $ttlSegundos): void
    {
        $sql = "DELETE FROM registros_en_uso
                WHERE tabla = :tabla AND id_registro = :id_registro AND id_empresa = :id_empresa
                  AND fecha_ultimo_ping < (CURRENT_TIMESTAMP - (:ttl || ' seconds')::INTERVAL)";
        $this->db->prepare($sql)->execute([
            ':tabla' => $tabla,
            ':id_registro' => $idRegistro,
            ':id_empresa' => $idEmpresa,
            ':ttl' => $ttlSegundos,
        ]);
    }

    /** Intenta insertar el lock. true si lo tomó; false si ya existía uno vigente. */
    public function tomar(string $tabla, int $idRegistro, int $idEmpresa, int $idUsuario, string $nombreUsuario, ?string $moduloContexto): bool
    {
        $sql = "INSERT INTO registros_en_uso (id_empresa, tabla, id_registro, id_usuario, nombre_usuario, modulo_contexto)
                VALUES (:id_empresa, :tabla, :id_registro, :id_usuario, :nombre_usuario, :modulo_contexto)
                ON CONFLICT (id_empresa, tabla, id_registro) DO NOTHING";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':tabla' => $tabla,
            ':id_registro' => $idRegistro,
            ':id_usuario' => $idUsuario,
            ':nombre_usuario' => $nombreUsuario,
            ':modulo_contexto' => $moduloContexto,
        ]);
        return $st->rowCount() > 0;
    }

    public function buscar(string $tabla, int $idRegistro, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM registros_en_uso WHERE tabla = :tabla AND id_registro = :id_registro AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        $st->execute([':tabla' => $tabla, ':id_registro' => $idRegistro, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Renueva el heartbeat. false si el lock ya no es de este usuario (se perdió). */
    public function renovarPing(string $tabla, int $idRegistro, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE registros_en_uso SET fecha_ultimo_ping = CURRENT_TIMESTAMP
                WHERE tabla = :tabla AND id_registro = :id_registro AND id_empresa = :id_empresa AND id_usuario = :id_usuario";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':tabla' => $tabla, ':id_registro' => $idRegistro, ':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario,
        ]);
        return $st->rowCount() > 0;
    }

    public function liberar(string $tabla, int $idRegistro, int $idEmpresa, int $idUsuario): void
    {
        $sql = "DELETE FROM registros_en_uso
                WHERE tabla = :tabla AND id_registro = :id_registro AND id_empresa = :id_empresa AND id_usuario = :id_usuario";
        $this->db->prepare($sql)->execute([
            ':tabla' => $tabla, ':id_registro' => $idRegistro, ':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario,
        ]);
    }
}
