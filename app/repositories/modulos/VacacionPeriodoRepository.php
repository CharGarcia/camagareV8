<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Períodos de vacaciones que el empleado ya tomó o cobró ANTES de usar el sistema
 * (ajuste de saldo de empleados que vienen de otro sistema). Una fila viva por
 * empleado y período de servicio; los pendientes no se guardan, se calculan desde
 * la fecha de ingreso (VacacionCalculoService::periodos()).
 *
 * Sin tipo_ambiente, a propósito: es historia del empleado —como su fecha de
 * ingreso— y debe valer igual en pruebas y en producción.
 */
class VacacionPeriodoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('vacaciones_periodos');
    }

    /** ¿Ya se aplicó database/modulos_vacaciones_periodos.sql? Sin la tabla, el módulo sigue como antes. */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /**
     * Candado del empleado (§8): "leer los marcados → validar → insertar" no debe
     * correr a la vez para el mismo empleado. Se libera solo al COMMIT/ROLLBACK.
     */
    public function lockEmpleado(int $idEmpresa, int $idEmpleado): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('vacaciones_periodos:' || :emp || ':' || :e))")
                 ->execute([':emp' => $idEmpresa, ':e' => $idEmpleado]);
    }

    /** Períodos marcados (vivos) del empleado, indexados por número de período. */
    public function getMarcados(int $idEmpleado, int $idEmpresa): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $st = $this->db->prepare("SELECT p.id, p.numero_periodo, p.fecha_inicio, p.fecha_fin, p.dias_derecho, p.dias,
                                         p.estado, p.observacion, p.created_by, p.created_at, u.nombre AS usuario_nombre
                                  FROM {$this->table} p
                                  LEFT JOIN usuarios u ON u.id = p.created_by
                                  WHERE p.id_empresa = :emp AND p.id_empleado = :e AND p.eliminado = false
                                  ORDER BY p.numero_periodo");
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado]);

        $marcados = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $marcados[(int) $r['numero_periodo']] = $r;
        }
        return $marcados;
    }

    public function crear(array $d): int
    {
        $st = $this->db->prepare("INSERT INTO {$this->table} (
                    id_empresa, id_empleado, numero_periodo, fecha_inicio, fecha_fin, dias_derecho, dias,
                    estado, observacion, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :numero_periodo, :fecha_inicio, :fecha_fin, :dias_derecho, :dias,
                    :estado, :observacion, :created_by, :updated_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                ) RETURNING id");
        $st->execute([
            ':id_empresa'     => (int) $d['id_empresa'],
            ':id_empleado'    => (int) $d['id_empleado'],
            ':numero_periodo' => (int) $d['numero_periodo'],
            ':fecha_inicio'   => $d['fecha_inicio'],
            ':fecha_fin'      => $d['fecha_fin'],
            ':dias_derecho'   => (float) $d['dias_derecho'],
            ':dias'           => (float) $d['dias'],
            ':estado'         => $d['estado'],
            ':observacion'    => $this->caparTexto('observacion', ($d['observacion'] ?? '') !== '' ? $d['observacion'] : null),
            ':created_by'     => (int) $d['id_usuario'],
            ':updated_by'     => (int) $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table}
                                  SET eliminado = true, deleted_by = :deleted_by, deleted_at = CURRENT_TIMESTAMP,
                                      updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                                  WHERE id = :id AND id_empresa = :emp AND eliminado = false");
        $st->execute([':deleted_by' => $idUsuario, ':updated_by' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
