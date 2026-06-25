<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

class SriConfigDescarga
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getPorEmpresa(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM sri_config_descarga_auto
             WHERE id_empresa = ? AND eliminado = FALSE LIMIT 1"
        );
        $st->execute([$idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function upsert(array $data): bool
    {
        $existente = $this->getPorEmpresa((int) $data['id_empresa']);

        if ($existente) {
            $st = $this->db->prepare(
                "UPDATE sri_config_descarga_auto SET
                    sri_usuario     = :u,
                    sri_clave       = :c,
                    estado          = :e,
                    tipos_documento = :t,
                    login_bloqueado = FALSE,
                    updated_at      = CURRENT_TIMESTAMP,
                    updated_by      = :ub
                 WHERE id_empresa = :ie AND eliminado = FALSE"
            );
            return $st->execute([
                ':u'  => $data['sri_usuario'],
                ':c'  => $data['sri_clave'],
                ':e'  => $data['estado'],
                ':t'  => $data['tipos_documento'],
                ':ub' => $data['updated_by'] ?? 0,
                ':ie' => $data['id_empresa'],
            ]);
        }

        $st = $this->db->prepare(
            "INSERT INTO sri_config_descarga_auto
                (id_empresa, sri_usuario, sri_clave, estado, tipos_documento,
                 login_bloqueado, created_by, updated_by)
             VALUES (:ie, :u, :c, :e, :t, FALSE, :cb, :ub)"
        );
        return $st->execute([
            ':ie' => $data['id_empresa'],
            ':u'  => $data['sri_usuario'],
            ':c'  => $data['sri_clave'],
            ':e'  => $data['estado'],
            ':t'  => $data['tipos_documento'],
            ':cb' => $data['created_by'] ?? 0,
            ':ub' => $data['updated_by']  ?? 0,
        ]);
    }

    public function actualizarEstadoDescarga(int $idEmpresa, string $estado, string $mensaje): void
    {
        $st = $this->db->prepare(
            "UPDATE sri_config_descarga_auto SET
                ultima_descarga = CURRENT_TIMESTAMP,
                ultimo_estado   = :e,
                ultimo_mensaje  = :m,
                updated_at      = CURRENT_TIMESTAMP
             WHERE id_empresa = :ie AND eliminado = FALSE"
        );
        $st->execute([':e' => $estado, ':m' => $mensaje, ':ie' => $idEmpresa]);
    }

    /**
     * Bloquea el login de la empresa para evitar que el scraper
     * siga intentando con credenciales incorrectas (bloqueo de usuario SRI).
     * Solo se desbloquea al guardar una clave nueva.
     */
    public function bloquearLogin(int $idEmpresa, string $motivo): void
    {
        $st = $this->db->prepare(
            "UPDATE sri_config_descarga_auto SET
                login_bloqueado        = TRUE,
                login_bloqueado_motivo = :m,
                ultima_descarga        = CURRENT_TIMESTAMP,
                ultimo_estado          = 'error',
                ultimo_mensaje         = :msg,
                updated_at             = CURRENT_TIMESTAMP
             WHERE id_empresa = :ie AND eliminado = FALSE"
        );
        $st->execute([
            ':m'   => substr($motivo, 0, 500),
            ':msg' => 'Bloqueado por credenciales incorrectas.',
            ':ie'  => $idEmpresa,
        ]);
    }

    /** Devuelve todas las empresas con descarga activa y sin bloqueo (para el cron). */
    public function getActivas(): array
    {
        $st = $this->db->query(
            "SELECT * FROM sri_config_descarga_auto
             WHERE estado = 'activo'
               AND login_bloqueado = FALSE
               AND eliminado = FALSE
             ORDER BY id_empresa"
        );
        return $st->fetchAll();
    }
}
