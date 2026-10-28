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
 *
 * Cada fila es de UN tipo (centro o proyecto): los periodos de centro de costo y los de
 * proyecto son independientes y solo no pueden cruzarse entre los del mismo tipo.
 */
class PlanCuentaDimensionRepository extends BaseRepository
{
    public const TABLA = 'plan_cuentas_dimension_vigencia';
    public const TIPOS = ['centro' => 'id_centro_costo', 'proyecto' => 'id_proyecto'];

    public function __construct()
    {
        parent::__construct(self::TABLA);
    }

    /** Sin la migración aplicada el módulo sigue funcionando, solo sin sugerencias. */
    public function disponible(): bool
    {
        return $this->tablaExiste(self::TABLA);
    }

    private static function columna(string $tipo): string
    {
        if (!isset(self::TIPOS[$tipo])) {
            throw new \InvalidArgumentException('Tipo no válido.');
        }
        return self::TIPOS[$tipo];
    }

    /** Periodos de un tipo para una cuenta, del más reciente al más antiguo. */
    public function listarPorCuenta(int $idCuenta, int $idEmpresa, string $tipo): array
    {
        if (!$this->disponible()) return [];
        $col = self::columna($tipo);

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
                  AND v.{$col} IS NOT NULL
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
     * Centro y proyecto vigentes a una fecha (cada uno de su propio periodo). Solo
     * devuelve centros/proyectos activos: uno que se inactivó después no se propone.
     */
    public function getVigente(int $idCuenta, int $idEmpresa, string $fecha): ?array
    {
        return $this->getVigentesPorCuentas([$idCuenta], $idEmpresa, $fecha)[$idCuenta] ?? null;
    }

    /**
     * Lo mismo que getVigente() para varias cuentas en una sola consulta (las líneas de un
     * asiento). Devuelve [id_cuenta => ['id_centro_costo' => ?int, 'id_proyecto' => ?int]],
     * solo con las cuentas que tienen alguno de los dos vigente a esa fecha.
     */
    public function getVigentesPorCuentas(array $idsCuenta, int $idEmpresa, string $fecha): array
    {
        $idsCuenta = array_values(array_unique(array_filter(array_map('intval', $idsCuenta), fn($v) => $v > 0)));
        if ($idsCuenta === [] || !$this->disponible() || !$this->empresaUsa($idEmpresa)) return [];

        // Una fila por cuenta y tipo (DISTINCT ON): la de inicio más reciente que cubra la fecha.
        $sql = "SELECT DISTINCT ON (v.id_cuenta, (v.id_centro_costo IS NULL))
                       v.id_cuenta, v.id_centro_costo, v.id_proyecto
                FROM {$this->table} v
                LEFT JOIN centro_costos cc ON cc.id = v.id_centro_costo
                LEFT JOIN proyectos     pr ON pr.id = v.id_proyecto
                " . $this->getBaseWhere($idEmpresa, 'v') . "
                  AND v.id_cuenta = ANY(CAST(:ids AS int[]))
                  AND daterange(v.vigente_desde, v.vigente_hasta, '[]') @> CAST(:fecha AS date)
                  AND (   (v.id_centro_costo IS NOT NULL AND cc.estado = 'activo' AND cc.eliminado = false)
                       OR (v.id_proyecto     IS NOT NULL AND pr.estado = 'activo' AND pr.eliminado = false))
                ORDER BY v.id_cuenta, (v.id_centro_costo IS NULL), v.vigente_desde DESC NULLS LAST, v.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':ids' => '{' . implode(',', $idsCuenta) . '}', ':fecha' => $fecha]);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['id_cuenta'];
            $out[$id] ??= ['id_centro_costo' => null, 'id_proyecto' => null];
            if ($r['id_centro_costo'] !== null) $out[$id]['id_centro_costo'] = (int) $r['id_centro_costo'];
            if ($r['id_proyecto'] !== null)     $out[$id]['id_proyecto']     = (int) $r['id_proyecto'];
        }
        return $out;
    }

    /**
     * ¿La empresa tiene algún periodo registrado? Casi ninguna usa centros / proyectos: así
     * la generación masiva de asientos (sincronizador) no consulta por cada uno. Cacheado por
     * proceso; una empresa que registra su primer periodo lo ve desde la siguiente petición.
     */
    private static array $empresaUsa = [];

    private function empresaUsa(int $idEmpresa): bool
    {
        $cache = &self::$empresaUsa;
        if (!isset($cache[$idEmpresa])) {
            $st = $this->db->prepare("SELECT EXISTS (SELECT 1 FROM {$this->table} WHERE id_empresa = :id_empresa AND eliminado = false)");
            $st->execute([':id_empresa' => $idEmpresa]);
            $cache[$idEmpresa] = (bool) $st->fetchColumn();
        }
        return $cache[$idEmpresa];
    }

    /**
     * Líneas de asientos YA registrados de la cuenta que quedan dentro de un periodo de
     * $tipo y tienen ese campo vacío: las que rellenarLineasAsientos() completaría.
     *
     * Solo líneas vacías (nunca se cambia un centro / proyecto ya puesto), de asientos no
     * eliminados ni anulados, fuera de los de cierre / apertura del ejercicio, con el centro /
     * proyecto activo. $enPeriodoCerrado elige el otro lado: las que NO se tocan porque su
     * asiento cae en un período contable cerrado (para avisarlo).
     */
    private function sqlLineasSinDimension(string $tipo, bool $enPeriodoCerrado): string
    {
        $col   = self::columna($tipo);
        $tabla = $tipo === 'proyecto' ? 'proyectos' : 'centro_costos';
        return "FROM asientos_contables_detalle d
                JOIN asientos_contables_cabecera c
                  ON c.id = d.id_asiento AND c.id_empresa = d.id_empresa
                 AND c.eliminado = false AND c.estado <> 'anulado'
                 AND c.modulo_origen NOT IN (:origen_cierre, :origen_apertura)
                JOIN {$this->table} v
                  ON v.id_empresa = d.id_empresa AND v.id_cuenta = d.id_cuenta_contable
                 AND v.eliminado = false AND v.{$col} IS NOT NULL
                 AND daterange(v.vigente_desde, v.vigente_hasta, '[]') @> CAST(c.fecha_asiento AS date)
                JOIN {$tabla} x ON x.id = v.{$col} AND x.estado = 'activo' AND x.eliminado = false
                WHERE d.id_empresa = :id_empresa AND d.id_cuenta_contable = :id_cuenta
                  AND d.eliminado = false AND d.{$col} IS NULL
                  AND " . ($enPeriodoCerrado ? '' : 'NOT ') . "EXISTS (
                      SELECT 1 FROM periodos_contables p
                      WHERE p.id_empresa = d.id_empresa AND p.eliminado = false AND p.status = 0
                        AND CAST(c.fecha_asiento AS date) BETWEEN p.fecha_inicial AND p.fecha_final)";
    }

    private function paramsLineas(int $idCuenta, int $idEmpresa): array
    {
        return [
            ':id_empresa'      => $idEmpresa,
            ':id_cuenta'       => $idCuenta,
            ':origen_cierre'   => \App\Helpers\CierreEjercicioSql::ORIGEN_CIERRE,
            ':origen_apertura' => \App\Helpers\CierreEjercicioSql::ORIGEN_APERTURA,
        ];
    }

    /** Cuántas líneas se completarían y cuántas quedan fuera por período cerrado, por tipo. */
    public function contarLineasSinDimension(int $idCuenta, int $idEmpresa): array
    {
        $out = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            $out[$tipo] = ['aplicables' => 0, 'en_cerrado' => 0];
            if (!$this->disponible()) continue;
            foreach (['aplicables' => false, 'en_cerrado' => true] as $clave => $cerrado) {
                $st = $this->db->prepare("SELECT COUNT(*) " . $this->sqlLineasSinDimension($tipo, $cerrado));
                $st->execute($this->paramsLineas($idCuenta, $idEmpresa));
                $out[$tipo][$clave] = (int) $st->fetchColumn();
            }
        }
        return $out;
    }

    /**
     * Completa el campo vacío ($tipo) de esas líneas con el centro / proyecto del periodo que
     * cubre la fecha de su asiento. Devuelve las líneas tocadas: [{id, id_asiento, valor}].
     */
    public function rellenarLineasAsientos(int $idCuenta, int $idEmpresa, string $tipo, int $idUsuario): array
    {
        $col = self::columna($tipo);
        $sql = "WITH obj AS (SELECT d.id, v.{$col} AS valor " . $this->sqlLineasSinDimension($tipo, false) . ")
                UPDATE asientos_contables_detalle t
                   SET {$col} = obj.valor, updated_at = CURRENT_TIMESTAMP, updated_by = :usuario
                  FROM obj
                 WHERE t.id = obj.id AND t.{$col} IS NULL
             RETURNING t.id, t.id_asiento, t.{$col} AS valor";
        $st = $this->db->prepare($sql);
        $st->execute($this->paramsLineas($idCuenta, $idEmpresa) + [':usuario' => $idUsuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Candado de los periodos de una cuenta: el chequeo de cruce y la escritura van juntos (§8). */
    public function lockCuenta(int $idCuenta, int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('pc_dimension:' || CAST(:e AS text) || ':' || CAST(:c AS text)))");
        $st->execute([':e' => $idEmpresa, ':c' => $idCuenta]);
    }

    /** Centro de costo / proyecto de la empresa (activo o no, pero no eliminado). */
    public function getDimension(string $tipo, int $id, int $idEmpresa): ?array
    {
        $sql = $tipo === 'proyecto'
            ? "SELECT id, nombre, fecha_inicio, fecha_fin FROM proyectos WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
            : "SELECT id, nombre FROM centro_costos WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function crear(array $d, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table}
                (id_empresa, id_cuenta, id_centro_costo, id_proyecto, vigente_desde, vigente_hasta, observacion, created_by, created_at)
             VALUES (:id_empresa, :id_cuenta, :id_centro_costo, :id_proyecto, :desde, :hasta, :observacion, :usuario, CURRENT_TIMESTAMP)
             RETURNING id"
        );
        unset(self::$empresaUsa[$idEmpresa]);
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
