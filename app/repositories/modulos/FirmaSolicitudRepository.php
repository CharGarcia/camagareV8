<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class FirmaSolicitudRepository extends BaseRepository
{
    protected string $table = 'firma_solicitudes';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (id_empresa, token, correo_destino, nombre_destino, estado, expira_at, observaciones, created_by)
                VALUES
                    (:id_empresa, :token, :correo_destino, :nombre_destino, 'pendiente', :expira_at, :observaciones, :created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'     => $data['id_empresa'],
            ':token'          => $data['token'],
            ':correo_destino' => $data['correo_destino'],
            ':nombre_destino' => $data['nombre_destino'] ?? null,
            ':expira_at'      => $data['expira_at'],
            ':observaciones'  => $data['observaciones'] ?? null,
            ':created_by'     => $data['created_by'],
        ]);
        return $this->lastInsertId('firma_solicitudes_id_seq');
    }

    public function getByToken(string $token): ?array
    {
        $sql = "SELECT s.*, e.nombre AS empresa_nombre, e.ruc AS empresa_ruc
                FROM {$this->table} s
                JOIN empresas e ON e.id = s.id_empresa
                WHERE s.token = :token AND s.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getListado(int $idEmpresa, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} WHERE id_empresa = :ie AND eliminado = false";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute([':ie' => $idEmpresa]);
        $total = (int) $stCount->fetchColumn();

        $sql = "SELECT s.id, s.token, s.correo_destino, s.nombre_destino, s.estado,
                       s.expira_at, s.completado_at, s.created_at, s.id_firma_generada,
                       f.nombres AS firma_nombres, f.apellidos AS firma_apellidos
                FROM {$this->table} s
                LEFT JOIN firmas_electronicas f ON f.id = s.id_firma_generada
                WHERE s.id_empresa = :ie AND s.eliminado = false
                ORDER BY s.created_at DESC
                LIMIT :lim OFFSET :off";
        $st = $this->db->prepare($sql);
        $st->bindValue(':ie',  $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':lim', $perPage,   PDO::PARAM_INT);
        $st->bindValue(':off', $offset,    PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    public function marcarCompletado(int $id, int $idFirma): bool
    {
        $sql = "UPDATE {$this->table}
                SET estado = 'completado', id_firma_generada = :id_firma, completado_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_firma' => $idFirma]);
    }

    public function cancelar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                SET estado = 'cancelado', updated_at = CURRENT_TIMESTAMP, updated_by = :u
                WHERE id = :id AND id_empresa = :ie AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':ie' => $idEmpresa, ':u' => $idUsuario]);
    }

    public function expirarVencidos(): int
    {
        $sql = "UPDATE {$this->table}
                SET estado = 'expirado', updated_at = CURRENT_TIMESTAMP
                WHERE estado = 'pendiente' AND expira_at < CURRENT_TIMESTAMP AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute();
        return $st->rowCount();
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND id_empresa = :ie AND eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
