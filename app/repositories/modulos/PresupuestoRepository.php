<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Presupuestos (modulos/presupuestos): cabecera, versiones, líneas por cuenta, valores por mes,
 * rubros y la ejecución real tomada de los asientos contables.
 *
 * SQL: database/migrations/20261005_presupuestos.sql.
 */
class PresupuestoRepository extends BaseRepository
{
    /** Whitelist y mapa del ORDER BY del listado (OrdenListado). */
    public const MAPA_ORDEN = [
        'nombre'        => 'p.nombre',
        'periodo'       => 'p.periodo_desde',
        'alcance'       => 'p.alcance',
        'estado'        => 'p.estado',
        'version'       => 'vv.numero',
        'ingresos'      => 'tot.ingresos',
        'gastos'        => 'tot.gastos',
        'created_at'    => 'p.created_at',
    ];

    public function __construct()
    {
        parent::__construct('presupuestos');
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('presupuestos');
    }

    /** Candado transaccional (CLAUDE.md §8). */
    public function bloquear(string $clave): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext(:clave))")->execute([':clave' => $clave]);
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    /**
     * Listado con la versión vigente (última aprobada, o el borrador si nunca se aprobó) y sus
     * totales de ingresos y gastos. La ejecución la completa el service (una consulta por fila).
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $ordenMulti, ?int $idUsuarioFiltro = null): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= ' AND ' . FiltrosBusqueda::condicionTexto(
                ['p.nombre', 'p.observaciones', 'cc.nombre', 'pr.nombre'],
                $parsed['texto_libre'], $params, 'q'
            );
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => ['nombre' => 'p.nombre', 'centro' => 'cc.nombre', 'proyecto' => 'pr.nombre'],
            'exacto' => ['estado' => 'p.estado', 'alcance' => 'p.alcance', 'periodo' => 'p.tipo_periodo',
                         'anio' => "EXTRACT(YEAR FROM p.periodo_desde)::text"],
            'fecha'  => ['desde' => 'p.periodo_desde', 'hasta' => 'p.periodo_hasta'],
        ]);

        // Versión que se muestra: la última aprobada; si no hay, el borrador.
        $joinVersion = "LEFT JOIN LATERAL (
                SELECT v.id, v.numero, v.nombre, v.estado
                FROM presupuestos_versiones v
                WHERE v.id_presupuesto = p.id AND v.eliminado = false
                ORDER BY CASE WHEN v.estado = 'aprobada' THEN 0 ELSE 1 END, v.numero DESC
                LIMIT 1
            ) vv ON true
            LEFT JOIN LATERAL (
                SELECT COALESCE(SUM(CASE WHEN pc.codigo LIKE '4%' THEN val.monto ELSE 0 END), 0) AS ingresos,
                       COALESCE(SUM(CASE WHEN pc.codigo NOT LIKE '4%' THEN val.monto ELSE 0 END), 0) AS gastos
                FROM presupuestos_lineas l
                JOIN plan_cuentas pc ON pc.id = l.id_cuenta
                JOIN presupuestos_valores val ON val.id_linea = l.id
                WHERE l.id_version = vv.id AND l.eliminado = false
            ) tot ON true
            LEFT JOIN centro_costos cc ON cc.id = p.id_centro_costo
            LEFT JOIN proyectos pr ON pr.id = p.id_proyecto";

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM presupuestos p {$joinVersion} {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $orderBy = OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'p.periodo_desde DESC', 'p.id DESC');
            $limit   = $perPage > 0 ? ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage) : '';
            $st = $this->db->prepare(
                "SELECT p.*, vv.id AS id_version_vigente, vv.numero AS version_numero, vv.nombre AS version_nombre,
                        vv.estado AS version_estado, tot.ingresos, tot.gastos,
                        cc.nombre AS centro_costo_nombre, pr.nombre AS proyecto_nombre
                 FROM presupuestos p {$joinVersion} {$where}
                 {$orderBy} {$limit}"
            );
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        return ['rows' => $rows, 'total' => $total];
    }

    // ── Cabecera ─────────────────────────────────────────────────────────────

    public function findById(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.*, cc.nombre AS centro_costo_nombre, pr.nombre AS proyecto_nombre
             FROM presupuestos p
             LEFT JOIN centro_costos cc ON cc.id = p.id_centro_costo
             LEFT JOIN proyectos pr ON pr.id = p.id_proyecto
             WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO presupuestos (id_empresa, nombre, tipo_periodo, periodo_desde, periodo_hasta, alcance,
                                       id_centro_costo, id_proyecto, estado, base_alicuotas, umbral_amarillo, umbral_rojo,
                                       observaciones, created_by, created_at, eliminado)
             VALUES (:e, :nombre, :tipo, :desde, :hasta, :alcance, :cc, :pr, 'borrador', :base, :am, :ro,
                     :obs, :u, CURRENT_TIMESTAMP, false)
             RETURNING id"
        );
        $st->execute([
            ':e' => $d['id_empresa'], ':nombre' => $d['nombre'], ':tipo' => $d['tipo_periodo'],
            ':desde' => $d['periodo_desde'], ':hasta' => $d['periodo_hasta'], ':alcance' => $d['alcance'],
            ':cc' => $d['id_centro_costo'] ?? null, ':pr' => $d['id_proyecto'] ?? null,
            ':base' => !empty($d['base_alicuotas']) ? 'true' : 'false',
            ':am' => $d['umbral_amarillo'] ?? 90, ':ro' => $d['umbral_rojo'] ?? 100,
            ':obs' => $d['observaciones'] ?? null, ':u' => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    /** Todos los campos editables (solo mientras ninguna versión está aprobada). */
    public function update(int $id, int $idEmpresa, array $d): void
    {
        $this->db->prepare(
            "UPDATE presupuestos SET nombre = :nombre, tipo_periodo = :tipo, periodo_desde = :desde, periodo_hasta = :hasta,
                    alcance = :alcance, id_centro_costo = :cc, id_proyecto = :pr, base_alicuotas = :base,
                    umbral_amarillo = :am, umbral_rojo = :ro, observaciones = :obs,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([
            ':nombre' => $d['nombre'], ':tipo' => $d['tipo_periodo'], ':desde' => $d['periodo_desde'], ':hasta' => $d['periodo_hasta'],
            ':alcance' => $d['alcance'], ':cc' => $d['id_centro_costo'] ?? null, ':pr' => $d['id_proyecto'] ?? null,
            ':base' => !empty($d['base_alicuotas']) ? 'true' : 'false',
            ':am' => $d['umbral_amarillo'] ?? 90, ':ro' => $d['umbral_rojo'] ?? 100,
            ':obs' => $d['observaciones'] ?? null, ':u' => $d['id_usuario'], ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /** Lo que sigue siendo editable con una versión aprobada: nombre, umbrales, observaciones, marca. */
    public function updateDatosAbiertos(int $id, int $idEmpresa, array $d): void
    {
        $this->db->prepare(
            "UPDATE presupuestos SET nombre = :nombre, base_alicuotas = :base, umbral_amarillo = :am, umbral_rojo = :ro,
                    observaciones = :obs, updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([
            ':nombre' => $d['nombre'], ':base' => !empty($d['base_alicuotas']) ? 'true' : 'false',
            ':am' => $d['umbral_amarillo'] ?? 90, ':ro' => $d['umbral_rojo'] ?? 100,
            ':obs' => $d['observaciones'] ?? null, ':u' => $d['id_usuario'], ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos SET estado = :s, updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':s' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
        $this->db->prepare(
            "UPDATE presupuestos_versiones SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id_presupuesto = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ── Versiones ────────────────────────────────────────────────────────────

    public function getVersiones(int $idPresupuesto, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT v.*, u.nombre AS aprobado_por_nombre,
                    (SELECT COALESCE(SUM(val.monto), 0) FROM presupuestos_lineas l
                     JOIN presupuestos_valores val ON val.id_linea = l.id
                     JOIN plan_cuentas pc ON pc.id = l.id_cuenta
                     WHERE l.id_version = v.id AND l.eliminado = false AND pc.codigo LIKE '4%') AS ingresos,
                    (SELECT COALESCE(SUM(val.monto), 0) FROM presupuestos_lineas l
                     JOIN presupuestos_valores val ON val.id_linea = l.id
                     JOIN plan_cuentas pc ON pc.id = l.id_cuenta
                     WHERE l.id_version = v.id AND l.eliminado = false AND pc.codigo NOT LIKE '4%') AS gastos
             FROM presupuestos_versiones v
             LEFT JOIN usuarios u ON u.id = v.aprobado_por
             WHERE v.id_presupuesto = :p AND v.id_empresa = :e AND v.eliminado = false
             ORDER BY v.numero"
        );
        $st->execute([':p' => $idPresupuesto, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVersion(int $idVersion, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT v.*, p.id AS id_presupuesto FROM presupuestos_versiones v
             JOIN presupuestos p ON p.id = v.id_presupuesto AND p.eliminado = false
             WHERE v.id = :id AND v.id_empresa = :e AND v.eliminado = false"
        );
        $st->execute([':id' => $idVersion, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Última versión aprobada (la que se compara con lo ejecutado), o null. */
    public function getVersionVigente(int $idPresupuesto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM presupuestos_versiones
             WHERE id_presupuesto = :p AND id_empresa = :e AND eliminado = false AND estado = 'aprobada'
             ORDER BY numero DESC LIMIT 1"
        );
        $st->execute([':p' => $idPresupuesto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getVersionBorrador(int $idPresupuesto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM presupuestos_versiones
             WHERE id_presupuesto = :p AND id_empresa = :e AND eliminado = false AND estado = 'borrador'
             LIMIT 1"
        );
        $st->execute([':p' => $idPresupuesto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crearVersion(int $idEmpresa, int $idPresupuesto, int $numero, string $nombre, ?string $motivo, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO presupuestos_versiones (id_empresa, id_presupuesto, numero, nombre, estado, motivo, created_by, created_at, eliminado)
             VALUES (:e, :p, :n, :nom, 'borrador', :m, :u, CURRENT_TIMESTAMP, false) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idPresupuesto, ':n' => $numero, ':nom' => $nombre, ':m' => $motivo, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function aprobarVersion(int $idVersion, int $idEmpresa, string $acta, ?string $observacion, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos_versiones
             SET estado = 'aprobada', acta = :acta, observacion_aprobacion = :obs, aprobado_at = CURRENT_TIMESTAMP,
                 aprobado_por = :u, updated_by = :u2, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e"
        )->execute([':acta' => $acta, ':obs' => $observacion, ':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $idVersion, ':e' => $idEmpresa]);
    }

    /** Las aprobadas anteriores pasan a 'reemplazada' cuando se aprueba una nueva. */
    public function reemplazarVersionesAnteriores(int $idPresupuesto, int $idVersionNueva, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos_versiones SET estado = 'reemplazada', updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id_presupuesto = :p AND id_empresa = :e AND eliminado = false AND estado = 'aprobada' AND id <> :v"
        )->execute([':u' => $idUsuario, ':p' => $idPresupuesto, ':e' => $idEmpresa, ':v' => $idVersionNueva]);
    }

    public function eliminarVersion(int $idVersion, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos_versiones SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e"
        )->execute([':u' => $idUsuario, ':id' => $idVersion, ':e' => $idEmpresa]);
    }

    // ── Líneas y valores ─────────────────────────────────────────────────────

    /**
     * Líneas de una versión con sus valores por mes (clave 'YYYY-MM' => monto) y los datos de la
     * cuenta y el rubro. Se devuelven en el orden guardado.
     */
    public function getLineas(int $idVersion, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT l.id, l.id_cuenta, l.id_rubro, l.orden,
                    pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre, pc.nivel AS cuenta_nivel,
                    r.nombre AS rubro_nombre,
                    COALESCE((SELECT json_object_agg(to_char(val.periodo, 'YYYY-MM'), val.monto)
                              FROM presupuestos_valores val WHERE val.id_linea = l.id), '{}'::json) AS valores
             FROM presupuestos_lineas l
             JOIN plan_cuentas pc ON pc.id = l.id_cuenta
             LEFT JOIN presupuestos_rubros r ON r.id = l.id_rubro
             WHERE l.id_version = :v AND l.id_empresa = :e AND l.eliminado = false
             ORDER BY l.orden, pc.codigo"
        );
        $st->execute([':v' => $idVersion, ':e' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['valores'] = json_decode((string) $r['valores'], true) ?: [];
            $r['es_ingreso'] = str_starts_with((string) $r['cuenta_codigo'], '4');
        }
        return $rows;
    }

    public function insertarLinea(int $idEmpresa, int $idVersion, int $idCuenta, ?int $idRubro, int $orden, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO presupuestos_lineas (id_empresa, id_version, id_cuenta, id_rubro, orden, created_by, created_at, eliminado)
             VALUES (:e, :v, :c, :r, :o, :u, CURRENT_TIMESTAMP, false) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':v' => $idVersion, ':c' => $idCuenta, ':r' => $idRubro, ':o' => $orden, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function actualizarLinea(int $idLinea, int $idEmpresa, ?int $idRubro, int $orden, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos_lineas SET id_rubro = :r, orden = :o, updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e"
        )->execute([':r' => $idRubro, ':o' => $orden, ':u' => $idUsuario, ':id' => $idLinea, ':e' => $idEmpresa]);
    }

    /** Da de baja las líneas de la versión que no están en $idsConservar. */
    public function eliminarLineasExcepto(int $idVersion, int $idEmpresa, array $idsConservar, int $idUsuario): void
    {
        $ids = array_values(array_filter(array_map('intval', $idsConservar)));
        $sql = "UPDATE presupuestos_lineas SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id_version = :v AND id_empresa = :e AND eliminado = false";
        if ($ids) {
            $sql .= ' AND id NOT IN (' . implode(',', $ids) . ')';
        }
        $this->db->prepare($sql)->execute([':u' => $idUsuario, ':v' => $idVersion, ':e' => $idEmpresa]);
    }

    /** Reemplaza los valores mensuales de una línea: upsert de los enviados y borrado del resto. */
    public function guardarValores(int $idLinea, int $idEmpresa, array $valores): void
    {
        $periodos = [];
        $up = $this->db->prepare(
            "INSERT INTO presupuestos_valores (id_empresa, id_linea, periodo, monto)
             VALUES (:e, :l, :p, :m)
             ON CONFLICT (id_linea, periodo) DO UPDATE SET monto = EXCLUDED.monto"
        );
        foreach ($valores as $ym => $monto) {
            if (!preg_match('/^\d{4}-\d{2}$/', (string) $ym)) {
                continue;
            }
            $periodo = $ym . '-01';
            $periodos[] = $periodo;
            $up->execute([':e' => $idEmpresa, ':l' => $idLinea, ':p' => $periodo, ':m' => round((float) $monto, 2)]);
        }
        $sql = "DELETE FROM presupuestos_valores WHERE id_linea = :l";
        $params = [':l' => $idLinea];
        if ($periodos) {
            $ph = [];
            foreach ($periodos as $i => $p) {
                $ph[] = ":p{$i}";
                $params[":p{$i}"] = $p;
            }
            $sql .= ' AND periodo NOT IN (' . implode(',', $ph) . ')';
        }
        $this->db->prepare($sql)->execute($params);
    }

    /** Copia las líneas y valores de una versión a otra (reforma, copiar de otro presupuesto). */
    public function copiarLineas(int $idVersionOrigen, int $idVersionDestino, int $idEmpresa, int $idUsuario): int
    {
        $n = 0;
        foreach ($this->getLineas($idVersionOrigen, $idEmpresa) as $l) {
            $idLinea = $this->insertarLinea($idEmpresa, $idVersionDestino, (int) $l['id_cuenta'], $l['id_rubro'] !== null ? (int) $l['id_rubro'] : null, (int) $l['orden'], $idUsuario);
            $this->guardarValores($idLinea, $idEmpresa, $l['valores']);
            $n++;
        }
        return $n;
    }

    // ── Rubros ───────────────────────────────────────────────────────────────

    public function getRubros(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, orden, estado FROM presupuestos_rubros
             WHERE id_empresa = :e AND eliminado = false ORDER BY orden, nombre"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarRubroPorNombre(int $idEmpresa, string $nombre): ?int
    {
        $st = $this->db->prepare(
            "SELECT id FROM presupuestos_rubros WHERE id_empresa = :e AND eliminado = false AND LOWER(nombre) = LOWER(:n) LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':n' => trim($nombre)]);
        $v = $st->fetchColumn();
        return $v ? (int) $v : null;
    }

    public function crearRubro(int $idEmpresa, string $nombre, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO presupuestos_rubros (id_empresa, nombre, orden, created_by, created_at, eliminado)
             VALUES (:e, :n, COALESCE((SELECT MAX(orden) + 1 FROM presupuestos_rubros WHERE id_empresa = :e2 AND eliminado = false), 1), :u, CURRENT_TIMESTAMP, false)
             RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':n' => mb_substr(trim($nombre), 0, 120), ':e2' => $idEmpresa, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function renombrarRubro(int $id, int $idEmpresa, string $nombre, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE presupuestos_rubros SET nombre = :n, updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':n' => mb_substr(trim($nombre), 0, 120), ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminarRubro(int $id, int $idEmpresa, int $idUsuario): void
    {
        // Las líneas que lo usaban quedan sin rubro.
        $this->db->prepare("UPDATE presupuestos_lineas SET id_rubro = NULL WHERE id_rubro = :id AND id_empresa = :e")
            ->execute([':id' => $id, ':e' => $idEmpresa]);
        $this->db->prepare(
            "UPDATE presupuestos_rubros SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e"
        )->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ── Cuentas ──────────────────────────────────────────────────────────────

    /**
     * Cuentas de ingresos (4), costos y gastos (5 y 6) de la empresa, de cualquier nivel (una de
     * grupo suma todas sus subcuentas al ejecutar). Por código o nombre.
     */
    public function buscarCuentas(int $idEmpresa, string $q, int $limite = 20): array
    {
        $st = $this->db->prepare(
            "SELECT id, codigo, nombre, nivel,
                    EXISTS (SELECT 1 FROM plan_cuentas h WHERE h.id_empresa = pc.id_empresa AND h.eliminado = false
                            AND h.codigo LIKE pc.codigo || '.%') AS es_grupo
             FROM plan_cuentas pc
             WHERE pc.id_empresa = :e AND pc.eliminado = false AND pc.status = 1
               AND (pc.codigo LIKE '4%' OR pc.codigo LIKE '5%' OR pc.codigo LIKE '6%')
               AND (pc.codigo ILIKE :q OR pc.nombre ILIKE :q2)
             ORDER BY pc.codigo
             LIMIT " . max(1, min(50, $limite))
        );
        $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%', ':q2' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuentas por id (validación al guardar), indexadas por id. */
    public function getCuentasPorIds(int $idEmpresa, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT id, codigo, nombre, nivel FROM plan_cuentas
             WHERE id_empresa = :e AND eliminado = false AND id IN (" . implode(',', $ids) . ")"
        );
        $st->execute([':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    public function getCuentaPorCodigo(int $idEmpresa, string $codigo): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, codigo, nombre FROM plan_cuentas WHERE id_empresa = :e AND eliminado = false AND codigo = :c LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':c' => trim($codigo)]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCentrosCosto(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, codigo, nombre FROM centro_costos WHERE id_empresa = :e AND eliminado = false AND estado = 'activo' ORDER BY nombre");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProyectos(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, codigo, nombre FROM proyectos WHERE id_empresa = :e AND eliminado = false AND estado = 'activo' ORDER BY nombre");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Ejecución (lo real, desde los asientos) ──────────────────────────────

    /**
     * Movimiento real por cuenta y por mes entre dos fechas, en el ambiente activo de la empresa,
     * de asientos vivos y no anulados. Devuelve el saldo con el signo natural de cada clase:
     * ingresos (4) = haber − debe; costos y gastos (5, 6) = debe − haber.
     *
     * $cuentas: lista de ['id_cuenta' => int, 'codigo' => string]. Una cuenta de grupo suma todo lo
     * que tiene debajo (codigo = X o codigo LIKE 'X.%').
     * $alcance / $idRef: 'centro_costo' o 'proyecto' filtran por la línea del asiento.
     *
     * @return array<int, array<string, float>> id_cuenta => ['YYYY-MM' => monto]
     */
    public function getEjecucion(int $idEmpresa, array $cuentas, string $desde, string $hasta, string $alcance = 'empresa', ?int $idRef = null): array
    {
        if (!$cuentas) {
            return [];
        }
        $condCuentas = [];
        $params = [':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':e2' => $idEmpresa];
        $casos = [];
        foreach (array_values($cuentas) as $i => $c) {
            $condCuentas[] = "(pc.codigo = :c{$i} OR pc.codigo LIKE :cl{$i})";
            $casos[] = "WHEN pc.codigo = :cc{$i} OR pc.codigo LIKE :ccl{$i} THEN " . (int) $c['id_cuenta'];
            $params[":c{$i}"] = $c['codigo'];
            $params[":cl{$i}"] = $c['codigo'] . '.%';
            $params[":cc{$i}"] = $c['codigo'];
            $params[":ccl{$i}"] = $c['codigo'] . '.%';
        }
        $filtroRef = '';
        if ($alcance === 'centro_costo' && $idRef) {
            $filtroRef = ' AND det.id_centro_costo = :ref';
            $params[':ref'] = $idRef;
        } elseif ($alcance === 'proyecto' && $idRef) {
            $filtroRef = ' AND det.id_proyecto = :ref';
            $params[':ref'] = $idRef;
        }
        // El CASE se evalúa en orden: si una línea cae en dos cuentas presupuestadas anidadas (un
        // grupo y una de sus subcuentas), gana la primera. El service entrega $cuentas ordenadas
        // por código más largo primero, así la más específica se lleva el movimiento y no se
        // cuenta dos veces.
        $st = $this->db->prepare(
            "SELECT CASE " . implode(' ', $casos) . " END AS id_cuenta,
                    to_char(a.fecha_asiento, 'YYYY-MM') AS periodo,
                    SUM(CASE WHEN pc.codigo LIKE '4%' THEN det.haber - det.debe ELSE det.debe - det.haber END) AS monto
             FROM asientos_contables_detalle det
             JOIN asientos_contables_cabecera a ON a.id = det.id_asiento
             JOIN plan_cuentas pc ON pc.id = det.id_cuenta_contable
             WHERE a.id_empresa = :e AND a.eliminado = false AND a.estado <> 'anulado'
               AND det.eliminado = false
               AND a.fecha_asiento BETWEEN :d AND :h
               AND a.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)
               AND (" . implode(' OR ', $condCuentas) . ")
               {$filtroRef}
             GROUP BY 1, 2"
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_cuenta']][(string) $r['periodo']] = round((float) $r['monto'], 2);
        }
        return $out;
    }

    /**
     * Asientos que forman lo ejecutado de una cuenta en un mes (detalle al hacer clic en una cifra).
     */
    public function getAsientosEjecucion(int $idEmpresa, string $codigoCuenta, string $periodoYm, string $alcance = 'empresa', ?int $idRef = null): array
    {
        $params = [':e' => $idEmpresa, ':c' => $codigoCuenta, ':cl' => $codigoCuenta . '.%', ':p' => $periodoYm, ':e2' => $idEmpresa];
        $filtroRef = '';
        if ($alcance === 'centro_costo' && $idRef) {
            $filtroRef = ' AND det.id_centro_costo = :ref';
            $params[':ref'] = $idRef;
        } elseif ($alcance === 'proyecto' && $idRef) {
            $filtroRef = ' AND det.id_proyecto = :ref';
            $params[':ref'] = $idRef;
        }
        $st = $this->db->prepare(
            "SELECT a.id, a.fecha_asiento, a.numero_comprobante, a.concepto, a.modulo_origen,
                    pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre, det.debe, det.haber,
                    CASE WHEN pc.codigo LIKE '4%' THEN det.haber - det.debe ELSE det.debe - det.haber END AS monto
             FROM asientos_contables_detalle det
             JOIN asientos_contables_cabecera a ON a.id = det.id_asiento
             JOIN plan_cuentas pc ON pc.id = det.id_cuenta_contable
             WHERE a.id_empresa = :e AND a.eliminado = false AND a.estado <> 'anulado' AND det.eliminado = false
               AND (pc.codigo = :c OR pc.codigo LIKE :cl)
               AND to_char(a.fecha_asiento, 'YYYY-MM') = :p
               AND a.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)
               {$filtroRef}
             ORDER BY a.fecha_asiento, a.id
             LIMIT 500"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Hay movimientos en el período para armar un presupuesto «desde lo ejecutado». */
    public function getEjecutadoPorCuentaDetalle(int $idEmpresa, string $desde, string $hasta, string $alcance = 'empresa', ?int $idRef = null): array
    {
        $params = [':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':e2' => $idEmpresa];
        $filtroRef = '';
        if ($alcance === 'centro_costo' && $idRef) {
            $filtroRef = ' AND det.id_centro_costo = :ref';
            $params[':ref'] = $idRef;
        } elseif ($alcance === 'proyecto' && $idRef) {
            $filtroRef = ' AND det.id_proyecto = :ref';
            $params[':ref'] = $idRef;
        }
        $st = $this->db->prepare(
            "SELECT pc.id AS id_cuenta, pc.codigo, pc.nombre, to_char(a.fecha_asiento, 'YYYY-MM') AS periodo,
                    SUM(CASE WHEN pc.codigo LIKE '4%' THEN det.haber - det.debe ELSE det.debe - det.haber END) AS monto
             FROM asientos_contables_detalle det
             JOIN asientos_contables_cabecera a ON a.id = det.id_asiento
             JOIN plan_cuentas pc ON pc.id = det.id_cuenta_contable
             WHERE a.id_empresa = :e AND a.eliminado = false AND a.estado <> 'anulado' AND det.eliminado = false
               AND a.fecha_asiento BETWEEN :d AND :h
               AND (pc.codigo LIKE '4%' OR pc.codigo LIKE '5%' OR pc.codigo LIKE '6%')
               AND a.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)
               {$filtroRef}
             GROUP BY pc.id, pc.codigo, pc.nombre, 4
             ORDER BY pc.codigo, 4"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
