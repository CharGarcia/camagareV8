<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Recordatorios agregados a mano en la ficha del vehículo (tabla vehiculos_recordatorios):
 * fecha + motivo, sin necesidad de una orden de Car-Wash. Sus envíos quedan en
 * carwash_recordatorios (id_recordatorio), junto a los de las citas de las órdenes.
 */
class VehiculoRecordatorioRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('vehiculos_recordatorios');
    }

    /** ¿Ya existe la tabla? (el código no debe romperse si aún no se aplicó el SQL). */
    public function existeTabla(): bool
    {
        static $existe = false;
        if (!$existe) {
            $existe = (bool) $this->db->query("SELECT to_regclass('public.vehiculos_recordatorios') IS NOT NULL")->fetchColumn();
        }
        return $existe;
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, id_empresa, id_vehiculo, fecha, motivo, observacion, created_by
               FROM vehiculos_recordatorios
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO vehiculos_recordatorios (id_empresa, id_vehiculo, fecha, motivo, observacion, created_by, updated_by)
             VALUES (:e, :v, :f, :m, :o, :u, :u) RETURNING id"
        );
        $st->execute([
            ':e' => $d['id_empresa'], ':v' => $d['id_vehiculo'], ':f' => $d['fecha'],
            ':m' => $d['motivo'], ':o' => $d['observacion'] ?: null, ':u' => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, array $d): void
    {
        $st = $this->db->prepare(
            "UPDATE vehiculos_recordatorios
                SET fecha = :f, motivo = :m, observacion = :o, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':f' => $d['fecha'], ':m' => $d['motivo'], ':o' => $d['observacion'] ?: null,
            ':u' => $d['id_usuario'], ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE vehiculos_recordatorios
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }
}
