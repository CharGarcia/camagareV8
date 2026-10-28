<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Centro de costo / proyecto SUGERIDO de una cuenta por periodo
 * (tabla plan_cuentas_dimension_vigencia).
 *
 * Es solo una sugerencia para llenar las líneas de los asientos: lo que cuenta en los
 * reportes es el centro/proyecto guardado en cada línea (asientos_contables_detalle).
 * Por eso una cuenta puede cambiar de proyecto de un periodo a otro sin tocar lo ya
 * registrado. vigente_desde / vigente_hasta en NULL = sin límite por ese lado.
 */
class PlanCuentaDimensionRepository extends BaseRepository
{
    public const TABLA = 'plan_cuentas_dimension_vigencia';

    public function __construct()
    {
        parent::__construct(self::TABLA);
    }

    /** Sin la migración aplicada el módulo sigue funcionando, solo sin sugerencias. */
    public function disponible(): bool
    {
        return $this->tablaExiste(self::TABLA);
    }

    /** Periodos de una cuenta, del más reciente al más antiguo. */
    public function listarPorCuenta(int $idCuenta, int $idEmpresa): array
    {
        if (!$this->disponible()) return [];

        $sql = "SELECT v.id, v.id_cuenta, v.id_centro_costo, v.id_proyecto,
                       v.vigente_desde, v.vigente_hasta, v.observacion,
                       cc.codigo AS centro_codigo, cc.nombre AS centro_nombre,
                       pr.codigo AS proyecto_codigo, pr.nombre AS proyecto_nombre,
                       daterange(v.vigente_desde, v.vigente_hasta, '[]') @> CURRENT_DATE AS vigente_hoy
                FROM {$this->table} v
                LEFT JOIN centro_costos cc ON cc.id = v.id_centro_costo
                LEFT JOIN proyectos     pr ON pr.id = v.id_proyecto
                " . $this->getBaseWhere($idEmpresa, 'v') . "
                  AND v.id_cuenta = :id_cuenta
                ORDER BY v.vigente_desde DESC NULLS LAST, v.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuenta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        if (!$this->disponible()) return null;

        $st = $this->db->prepare("SELECT * FROM {$this->table} v " . $this->getBaseWhere($idEmpresa, 'v') . " AND v.id = :id");
        $st->execute([':id_empresa' => $idEmpresa, ':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Sugerencia vigente a una fecha. Solo devuelve centros/proyectos activos: uno que
     * se inactivó después no se propone (la línea queda vacía y el usuario elige).
     */
    public function getVigente(int $idCuenta, int $idEmpresa, string $fecha): ?array
    {
        if (!$this->disponible()) return null;

        $sql = "SELECT CASE WHEN cc.estado = 'activo' AND cc.eliminado = false THEN v.id_centro_costo END AS id_centro_costo,
                       CASE WHEN pr.estado = 'activo' AND pr.eliminado = false THEN v.id_proyecto END AS id_proyecto
                FROM {$this->table} v
                LEFT JOIN centro_costos cc ON cc.id = v.id_centro_costo
                LEFT JOIN proyectos     pr ON pr.id = v.id_proyecto
                " . $this->getBaseWhere($idEmpresa, 'v') . "
                  AND v.id_cuenta = :id_cuenta
                  AND daterange(v.vigente_desde, v.vigente_hasta, '[]') @> CAST(:fecha AS date)
                ORDER BY v.vigente_desde DESC NULLS LAST, v.id DESC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuenta, ':fecha' => $fecha]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || ($r['id_centro_costo'] === null && $r['id_proyecto'] === null)) return null;
        return $r;
    }

    /**
     * Periodo de la misma cuenta que se cruza con el rango dado (para no tener dos
     * sugerencias a la vez). $excluirId = la fila que se está editando.
     */
    public function buscarCruce(int $idCuenta, int $idEmpresa, ?string $desde, ?string $hasta, ?int $excluirId): ?array
    {
        $sql = "SELECT v.id, v.vigente_desde, v.vigente_hasta
                FROM {$this->table} v
                " . $this->getBaseWhere($idEmpresa, 'v') . "
                  AND v.id_cuenta = :id_cuenta
                  AND daterange(v.vigente_desde, v.vigente_hasta, '[]')
                      && daterange(CAST(:desde AS date), CAST(:hasta AS date), '[]')";
        $params = [':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuenta, ':desde' => $desde, ':hasta' => $hasta];
        if ($excluirId !== null) {
            $sql .= " AND v.id <> :excluir";
            $params[':excluir'] = $excluirId;
        }
        $st = $this->db->prepare($sql . " LIMIT 1");
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Candado de los periodos de una cuenta: el chequeo de cruce y la escritura van juntos (§8). */
    public function lockCuenta(int $idCuenta, int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('pc_dimension:' || CAST(:e AS text) || ':' || CAST(:c AS text)))");
        $st->execute([':e' => $idEmpresa, ':c' => $idCuenta]);
    }

    /** ¿Existe el centro de costo / proyecto en la empresa (activo o no, pero no eliminado)? */
    public function existeDimension(string $tipo, int $id, int $idEmpresa): bool
    {
        $tabla = $tipo === 'proyecto' ? 'proyectos' : 'centro_costos';
        $st = $this->db->prepare("SELECT 1 FROM {$tabla} WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    public function crear(array $d, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table}
                (id_empresa, id_cuenta, id_centro_costo, id_proyecto, vigente_desde, vigente_hasta, observacion, created_by, created_at)
             VALUES (:id_empresa, :id_cuenta, :id_centro_costo, :id_proyecto, :desde, :hasta, :observacion, :usuario, CURRENT_TIMESTAMP)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'      => $idEmpresa,
            ':id_cuenta'       => $d['id_cuenta'],
            ':id_centro_costo' => $d['id_centro_costo'],
            ':id_proyecto'     => $d['id_proyecto'],
            ':desde'           => $d['vigente_desde'],
            ':hasta'           => $d['vigente_hasta'],
            ':observacion'     => $d['observacion'],
            ':usuario'         => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table} SET
                id_centro_costo = :id_centro_costo, id_proyecto = :id_proyecto,
                vigente_desde = :desde, vigente_hasta = :hasta, observacion = :observacion,
                updated_by = :usuario, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([
            ':id_centro_costo' => $d['id_centro_costo'],
            ':id_proyecto'     => $d['id_proyecto'],
            ':desde'           => $d['vigente_desde'],
            ':hasta'           => $d['vigente_hasta'],
            ':observacion'     => $d['observacion'],
            ':usuario'         => $idUsuario,
            ':id'              => $id,
            ':id_empresa'      => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table} SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }
}
