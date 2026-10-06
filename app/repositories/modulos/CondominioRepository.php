<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Condominios — fase 1 (módulo base): configuración por empresa, unidades (inmuebles),
 * historial de propietarios y catálogo de multas. Aportes, cargos y emisión llegan en el
 * siguiente paso sobre las mismas tablas (database/migrations/20261005_condominios.sql).
 *
 * Acceso a datos puro. Toda consulta operativa filtra id_empresa + eliminado = false
 * (getBaseWhere). Si el SQL aún no se aplicó, `instalado()` devuelve false y el módulo avisa.
 */
class CondominioRepository extends BaseRepository
{
    /** Columnas ordenables del listado de unidades (whitelist + mapa de OrdenListado). */
    public const MAPA_ORDEN = [
        'codigo'       => 'u.codigo',
        'nombre'       => 'u.nombre',
        'tipo'         => 'u.tipo',
        'torre_bloque' => 'u.torre_bloque',
        'piso'         => 'u.piso',
        'area_m2'      => 'u.area_m2',
        'alicuota_pct' => 'u.alicuota_pct',
        'propietario'  => 'cp.nombre',
        'pagador'      => "CASE WHEN u.pagador = 'arrendatario' THEN ca.nombre ELSE cp.nombre END",
        'metodo'       => 'COALESCE(u.metodo_alicuota, cfg.metodo_alicuota)',
        'restringida'  => 'u.restringida',
        'estado'       => 'u.estado',
    ];

    public function __construct()
    {
        parent::__construct('condominios_unidades');
    }

    /** ¿Está aplicado el SQL del módulo? (el código se despliega antes que la BD). */
    public function instalado(): bool
    {
        return $this->tablaExiste('condominios_config') && $this->tablaExiste('condominios_unidades');
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        if (!$this->tablaExiste('condominios_config')) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT c.*,
                    pf.nombre AS producto_fondo_nombre,
                    pi.nombre AS producto_interes_nombre
               FROM condominios_config c
               LEFT JOIN productos pf ON pf.id = c.id_producto_fondo
               LEFT JOIN productos pi ON pi.id = c.id_producto_interes
              WHERE c.id_empresa = :e AND c.eliminado = false"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Campos de la configuración que se escriben desde el formulario (el resto son auditoría). */
    public const CAMPOS_CONFIG = [
        'nombre_condominio', 'direccion',
        'administrador_nombre', 'administrador_cedula', 'administrador_cargo', 'presidente_nombre', 'presidente_cedula',
        'dias_gracia',
        'id_producto_fondo', 'id_producto_interes',
        'metodo_alicuota', 'reparto_manuales',
        'fondo_reserva_tipo', 'fondo_reserva_valor',
        'cobra_intereses', 'interes_tipo', 'interes_tasa_mensual', 'interes_destino',
        'cobra_multas',
        'pronto_pago_activo', 'pronto_pago_pct', 'pronto_pago_dia',
        'anticipado_activo', 'anticipado_pct', 'anticipado_meses_min',
        'restriccion_auto', 'restriccion_meses', 'liquidacion_min_vencidas',
        'observaciones',
    ];

    public function insertConfig(int $idEmpresa, array $d, int $idUsuario): int
    {
        $cols = self::CAMPOS_CONFIG;
        $sql = "INSERT INTO condominios_config (id_empresa, " . implode(', ', $cols) . ", created_by, created_at)
                VALUES (:id_empresa, " . implode(', ', array_map(fn($c) => ':' . $c, $cols)) . ", :created_by, CURRENT_TIMESTAMP)
                RETURNING id";
        $p = [':id_empresa' => $idEmpresa, ':created_by' => $idUsuario];
        foreach ($cols as $c) {
            $p[':' . $c] = $this->valorSql($d[$c] ?? null);
        }
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    }

    public function updateConfig(int $idEmpresa, array $d, int $idUsuario): void
    {
        $cols = self::CAMPOS_CONFIG;
        $sql = "UPDATE condominios_config SET " . implode(', ', array_map(fn($c) => "{$c} = :{$c}", $cols)) . ",
                       updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                 WHERE id_empresa = :id_empresa AND eliminado = false";
        $p = [':id_empresa' => $idEmpresa, ':updated_by' => $idUsuario];
        foreach ($cols as $c) {
            $p[':' . $c] = $this->valorSql($d[$c] ?? null);
        }
        $this->db->prepare($sql)->execute($p);
    }

    /** Booleanos de PHP → 'true'/'false' (PDO + PostgreSQL no entienden `false` nativo). */
    private function valorSql(mixed $v): mixed
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        return $v;
    }

    // ── Unidades: listado ────────────────────────────────────────────────────

    /** JOINs que usan el listado, el texto libre y los filtros (iguales en COUNT y SELECT). */
    private const JOINS_UNIDADES = "
        LEFT JOIN clientes cp ON cp.id = u.id_propietario
        LEFT JOIN clientes ca ON ca.id = u.id_arrendatario
        LEFT JOIN condominios_config cfg ON cfg.id_empresa = u.id_empresa AND cfg.eliminado = false";

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $ordenMulti, ?int $idUsuarioFiltro = null): array
    {
        $orderBy  = OrdenListado::clausula(OrdenListado::normalizar($ordenMulti), self::MAPA_ORDEN, 'u.codigo', 'u.id DESC');
        $whereSql = $this->getBaseWhere($idEmpresa, 'u', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre por palabras y sin tildes sobre lo visible. Tipo, método, estado y
            // restricción quedan fuera: se filtran desde el modal (tipo:, estado:, …).
            $cond = FiltrosBusqueda::condicionTexto([
                'u.codigo', 'u.nombre', 'u.torre_bloque', 'u.piso',
                'cp.nombre', 'cp.identificacion', 'ca.nombre', 'ca.identificacion',
            ], $parsed['texto_libre'], $params, 'cu_b');
            if ($cond !== '') {
                $whereSql .= ' AND ' . $cond;
            }
        }
        FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto'    => [
                'codigo' => 'u.codigo', 'nombre' => 'u.nombre', 'torre' => 'u.torre_bloque', 'piso' => 'u.piso',
                'propietario' => 'cp.nombre', 'arrendatario' => 'ca.nombre',
            ],
            'exacto'   => [
                'tipo'          => 'u.tipo',
                'estado'        => 'u.estado',
                'pagador'       => 'u.pagador',
                'metodo'        => 'COALESCE(u.metodo_alicuota, cfg.metodo_alicuota)',
                'restringida'   => "CASE WHEN u.restringida THEN 'si' ELSE 'no' END",
                'con_arrendatario' => "CASE WHEN u.id_arrendatario IS NULL THEN 'no' ELSE 'si' END",
                'id_propietario' => 'u.id_propietario',
                'usuario'       => 'u.created_by',
            ],
            'numerico' => ['area' => 'u.area_m2', 'alicuota' => 'u.alicuota_pct'],
            'fecha'    => ['registro' => 'u.created_at'],
        ]);

        $st = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} u " . self::JOINS_UNIDADES . " {$whereSql}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $sql = "SELECT u.*,
                       cp.nombre AS propietario_nombre, cp.identificacion AS propietario_identificacion,
                       ca.nombre AS arrendatario_nombre, ca.identificacion AS arrendatario_identificacion,
                       CASE WHEN u.pagador = 'arrendatario' THEN ca.nombre ELSE cp.nombre END AS pagador_nombre,
                       COALESCE(u.metodo_alicuota, cfg.metodo_alicuota) AS metodo_efectivo
                  FROM {$this->table} u " . self::JOINS_UNIDADES . "
                 {$whereSql} {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** Opciones de los selects del modal de filtros: solo valores que la empresa usa. */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $leer = function (string $sql) use ($idEmpresa): array {
            $st = $this->db->prepare($sql);
            $st->execute([':e' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };
        return [
            'propietarios' => $leer("SELECT DISTINCT c.id, c.nombre FROM {$this->table} u JOIN clientes c ON c.id = u.id_propietario
                                      WHERE u.id_empresa = :e AND u.eliminado = false ORDER BY c.nombre"),
            'torres'       => $leer("SELECT DISTINCT u.torre_bloque AS nombre FROM {$this->table} u
                                      WHERE u.id_empresa = :e AND u.eliminado = false AND COALESCE(u.torre_bloque, '') <> '' ORDER BY 1"),
            'usuarios'     => $leer("SELECT DISTINCT us.id, us.nombre FROM {$this->table} u JOIN usuarios us ON us.id = u.created_by
                                      WHERE u.id_empresa = :e AND u.eliminado = false ORDER BY us.nombre"),
        ];
    }

    /** Totales para el pie del listado: unidades activas, Σ % y Σ m². */
    public function getResumen(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FILTER (WHERE estado = 'activo') AS activas,
                    COUNT(*) AS total,
                    COALESCE(SUM(alicuota_pct) FILTER (WHERE estado = 'activo'), 0) AS suma_pct,
                    COALESCE(SUM(area_m2) FILTER (WHERE estado = 'activo'), 0) AS suma_m2,
                    COUNT(*) FILTER (WHERE restringida) AS restringidas
               FROM {$this->table} WHERE id_empresa = :e AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['activas' => 0, 'total' => 0, 'suma_pct' => 0, 'suma_m2' => 0, 'restringidas' => 0];
    }

    // ── Unidades: CRUD ───────────────────────────────────────────────────────

    public function getUnidad(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT u.*,
                    cp.nombre AS propietario_nombre, cp.identificacion AS propietario_identificacion, cp.email AS propietario_email,
                    ca.nombre AS arrendatario_nombre, ca.identificacion AS arrendatario_identificacion,
                    s.estado AS suscripcion_estado, s.proximo_cobro AS suscripcion_proximo_cobro
               FROM {$this->table} u " . self::JOINS_UNIDADES . "
               LEFT JOIN suscripciones s ON s.id = u.id_suscripcion AND s.eliminado = false
              WHERE u.id = :id AND u.id_empresa = :e AND u.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getUnidadPorCodigo(int $idEmpresa, string $codigo): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id_empresa = :e AND eliminado = false AND UPPER(codigo) = UPPER(:c) LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':c' => $codigo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeCodigo(int $idEmpresa, string $codigo, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE id_empresa = :e AND eliminado = false AND UPPER(codigo) = UPPER(:c)";
        $p = [':e' => $idEmpresa, ':c' => $codigo];
        if ($excluirId) {
            $sql .= ' AND id <> :x';
            $p[':x'] = $excluirId;
        }
        $st = $this->db->prepare($sql . ' LIMIT 1');
        $st->execute($p);
        return (bool) $st->fetchColumn();
    }

    public const CAMPOS_UNIDAD = [
        'codigo', 'nombre', 'tipo', 'torre_bloque', 'piso', 'area_m2', 'alicuota_pct',
        'id_propietario', 'id_arrendatario', 'pagador',
        'metodo_alicuota', 'monto_manual', 'fondo_reserva_valor_propio',
        'estado', 'observaciones',
    ];

    public function insertUnidad(int $idEmpresa, array $d, int $idUsuario): int
    {
        $cols = self::CAMPOS_UNIDAD;
        $sql = "INSERT INTO {$this->table} (id_empresa, " . implode(', ', $cols) . ", created_by, created_at)
                VALUES (:id_empresa, " . implode(', ', array_map(fn($c) => ':' . $c, $cols)) . ", :created_by, CURRENT_TIMESTAMP)
                RETURNING id";
        $p = [':id_empresa' => $idEmpresa, ':created_by' => $idUsuario];
        foreach ($cols as $c) {
            $p[':' . $c] = $this->valorSql($d[$c] ?? null);
        }
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    }

    public function updateUnidad(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $cols = self::CAMPOS_UNIDAD;
        $sql = "UPDATE {$this->table} SET " . implode(', ', array_map(fn($c) => "{$c} = :{$c}", $cols)) . ",
                       updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $p = [':id' => $id, ':id_empresa' => $idEmpresa, ':updated_by' => $idUsuario];
        foreach ($cols as $c) {
            $p[':' . $c] = $this->valorSql($d[$c] ?? null);
        }
        $this->db->prepare($sql)->execute($p);
    }

    public function deleteUnidad(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE {$this->table} SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    public function setSuscripcionUnidad(int $id, int $idEmpresa, ?int $idSuscripcion): void
    {
        $this->db->prepare("UPDATE {$this->table} SET id_suscripcion = :s WHERE id = :id AND id_empresa = :e")
            ->execute([':s' => $idSuscripcion, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** ¿La unidad tiene cargos emitidos? (si los hay, no se elimina: se inactiva). */
    public function tieneCargosEmitidos(int $id, int $idEmpresa): bool
    {
        if (!$this->tablaExiste('condominios_cargos')) {
            return false;
        }
        $st = $this->db->prepare(
            "SELECT 1 FROM condominios_cargos WHERE id_unidad = :id AND id_empresa = :e AND eliminado = false AND estado = 'emitido' LIMIT 1"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    // ── Historial de propietarios ────────────────────────────────────────────

    public function getHistorialPropietarios(int $idUnidad, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT h.*, cp.nombre AS propietario_nombre, ca.nombre AS arrendatario_nombre
               FROM condominios_unidades_propietarios h
               LEFT JOIN clientes cp ON cp.id = h.id_propietario
               LEFT JOIN clientes ca ON ca.id = h.id_arrendatario
              WHERE h.id_unidad = :u AND h.id_empresa = :e AND h.eliminado = false
              ORDER BY h.desde DESC, h.id DESC"
        );
        $st->execute([':u' => $idUnidad, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPropietarioVigente(int $idUnidad, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM condominios_unidades_propietarios
              WHERE id_unidad = :u AND id_empresa = :e AND eliminado = false AND hasta IS NULL
              ORDER BY desde DESC, id DESC LIMIT 1"
        );
        $st->execute([':u' => $idUnidad, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Cierra la fila vigente (hasta = día anterior a $desde) y abre una nueva. */
    public function abrirPropietario(int $idUnidad, int $idEmpresa, int $idPropietario, ?int $idArrendatario, string $pagador, string $desde, ?string $observacion, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_unidades_propietarios
                SET hasta = GREATEST(desde, (:desde::date - INTERVAL '1 day')::date), updated_by = :u, updated_at = CURRENT_TIMESTAMP
              WHERE id_unidad = :uni AND id_empresa = :e AND eliminado = false AND hasta IS NULL"
        )->execute([':desde' => $desde, ':u' => $idUsuario, ':uni' => $idUnidad, ':e' => $idEmpresa]);
        $this->db->prepare(
            "INSERT INTO condominios_unidades_propietarios
                    (id_empresa, id_unidad, id_propietario, id_arrendatario, pagador, desde, observacion, created_by, created_at)
             VALUES (:e, :uni, :p, :a, :pag, :desde, :obs, :u, CURRENT_TIMESTAMP)"
        )->execute([':e' => $idEmpresa, ':uni' => $idUnidad, ':p' => $idPropietario, ':a' => $idArrendatario,
                    ':pag' => $pagador, ':desde' => $desde, ':obs' => $observacion, ':u' => $idUsuario]);
    }

    // ── Valores que rigen (tarifa por m² / monto a repartir) ─────────────────

    /** Historial de valores, del más reciente al más antiguo. */
    public function getValores(int $idEmpresa): array
    {
        if (!$this->tablaExiste('condominios_alicuotas_valores')) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT v.*, p.nombre AS presupuesto_nombre, pv.nombre AS version_nombre, us.nombre AS usuario_nombre
               FROM condominios_alicuotas_valores v
               LEFT JOIN presupuestos p ON p.id = v.id_presupuesto
               LEFT JOIN presupuestos_versiones pv ON pv.id = v.id_presupuesto_version
               LEFT JOIN usuarios us ON us.id = v.created_by
              WHERE v.id_empresa = :e AND v.eliminado = false
              ORDER BY v.vigente_desde DESC, v.id DESC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** La fila que rige en una fecha: la última con vigente_desde <= fecha. */
    public function getValorVigente(int $idEmpresa, string $fecha): ?array
    {
        if (!$this->tablaExiste('condominios_alicuotas_valores')) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT * FROM condominios_alicuotas_valores
              WHERE id_empresa = :e AND eliminado = false AND vigente_desde <= :f
              ORDER BY vigente_desde DESC, id DESC LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':f' => $fecha]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getValor(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM condominios_alicuotas_valores WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeValorDesde(int $idEmpresa, string $vigenteDesde): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM condominios_alicuotas_valores WHERE id_empresa = :e AND eliminado = false AND vigente_desde = :f LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':f' => $vigenteDesde]);
        return (bool) $st->fetchColumn();
    }

    public function insertValor(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_alicuotas_valores
                    (id_empresa, vigente_desde, tarifa_m2, monto_a_repartir, id_presupuesto, id_presupuesto_version, acta, observacion, created_by, created_at)
             VALUES (:e, :f, :t, :m, :p, :pv, :a, :o, :u, CURRENT_TIMESTAMP) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':f' => $d['vigente_desde'], ':t' => $d['tarifa_m2'], ':m' => $d['monto_a_repartir'],
                      ':p' => $d['id_presupuesto'], ':pv' => $d['id_presupuesto_version'], ':a' => $d['acta'], ':o' => $d['observacion'], ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function deleteValor(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_alicuotas_valores SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Inmuebles activos con lo necesario para calcular su cuota (vista previa de un valor). */
    public function getUnidadesParaCuota(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT u.id, u.codigo, u.nombre, u.tipo, u.torre_bloque, u.area_m2, u.alicuota_pct, u.metodo_alicuota, u.monto_manual,
                    u.fondo_reserva_valor_propio, cp.nombre AS propietario_nombre
               FROM {$this->table} u LEFT JOIN clientes cp ON cp.id = u.id_propietario
              WHERE u.id_empresa = :e AND u.eliminado = false AND u.estado = 'activo'
              ORDER BY u.torre_bloque, u.codigo"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Presupuestos aprobados de la empresa (módulo Presupuestos) con su versión vigente y el
     * total mensual de costos y gastos (cuentas 5 y 6) de cada mes: base del método por %.
     * Vacío si Presupuestos no está instalado.
     */
    public function getPresupuestosAprobados(int $idEmpresa): array
    {
        if (!$this->tablaExiste('presupuestos') || !$this->tablaExiste('presupuestos_versiones')) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.periodo_desde, p.periodo_hasta, p.base_alicuotas, pv.id AS id_version, pv.nombre AS version_nombre,
                    COALESCE((SELECT json_object_agg(to_char(val.periodo, 'YYYY-MM'), val.total)
                              FROM (SELECT v.periodo, SUM(v.monto) AS total
                                      FROM presupuestos_valores v
                                      JOIN presupuestos_lineas l ON l.id = v.id_linea AND l.eliminado = false
                                      JOIN plan_cuentas pc ON pc.id = l.id_cuenta
                                     WHERE l.id_version = pv.id AND (pc.codigo LIKE '5%' OR pc.codigo LIKE '6%')
                                     GROUP BY v.periodo) val), '{}'::json) AS gastos_mes
               FROM presupuestos p
               JOIN presupuestos_versiones pv ON pv.id_presupuesto = p.id AND pv.eliminado = false AND pv.estado = 'aprobada'
              WHERE p.id_empresa = :e AND p.eliminado = false AND p.estado IN ('aprobado', 'cerrado')
              ORDER BY p.base_alicuotas DESC, p.periodo_desde DESC, p.id DESC"
        );
        $st->execute([':e' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['gastos_mes'] = json_decode((string) $r['gastos_mes'], true) ?: [];
        }
        unset($r);
        return $rows;
    }

    // ── Reajuste masivo de cuotas de las suscripciones ───────────────────────

    /** Conceptos (productos) presentes en las suscripciones activas de la empresa. */
    public function getProductosEnSuscripciones(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.codigo, COUNT(DISTINCT s.id) AS suscripciones
               FROM suscripciones_detalle d
               JOIN suscripciones s ON s.id = d.id_suscripcion AND s.eliminado = false AND s.estado IN ('activo', 'pausado')
               JOIN productos p ON p.id = d.id_producto
              WHERE d.id_empresa = :e AND d.eliminado = false
              GROUP BY p.id, p.nombre, p.codigo ORDER BY suscripciones DESC, p.nombre"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Un producto con lo necesario para crear una línea de suscripción (IVA incluido). */
    public function getProductoLinea(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.codigo, p.tarifa_iva AS id_tarifa_iva, COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva
               FROM productos p LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false"
        );
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Suscripciones activas/pausadas de la empresa con la línea del concepto (si la tienen) y su
     * inmueble (si está enlazado): la materia prima del reajuste. Sin el SQL de Condominios en
     * `suscripciones` (id_unidad) solo se devuelven suscripciones sin inmueble.
     */
    public function getSuscripcionesParaReajuste(int $idEmpresa, int $idProducto, bool $incluirSinInmueble): array
    {
        $conUnidad = $this->columnaExiste('suscripciones', 'id_unidad');
        $joinU  = $conUnidad ? "LEFT JOIN condominios_unidades u ON u.id = s.id_unidad AND u.eliminado = false" : '';
        $selU   = $conUnidad
            ? "u.id AS id_unidad, u.codigo AS unidad_codigo, u.nombre AS unidad_nombre, u.tipo AS unidad_tipo, u.torre_bloque, u.area_m2, u.alicuota_pct, u.metodo_alicuota, u.monto_manual, u.fondo_reserva_valor_propio,"
            : "NULL::int AS id_unidad, NULL::text AS unidad_codigo, NULL::text AS unidad_nombre, NULL::text AS unidad_tipo, NULL::text AS torre_bloque, NULL::numeric AS area_m2, NULL::numeric AS alicuota_pct, NULL::text AS metodo_alicuota, NULL::numeric AS monto_manual, NULL::numeric AS fondo_reserva_valor_propio,";
        $filtroU = $incluirSinInmueble || !$conUnidad ? '' : ' AND s.id_unidad IS NOT NULL';
        $st = $this->db->prepare(
            "SELECT s.id AS id_suscripcion, s.estado, c.nombre AS cliente, c.identificacion,
                    {$selU}
                    d.id AS id_detalle, d.precio_unitario AS actual, d.cantidad, d.descripcion AS linea_descripcion
               FROM suscripciones s
               JOIN clientes c ON c.id = s.id_cliente
               {$joinU}
               LEFT JOIN LATERAL (SELECT d.id, d.precio_unitario, d.cantidad, d.descripcion FROM suscripciones_detalle d
                                   WHERE d.id_suscripcion = s.id AND d.eliminado = false AND d.id_producto = :p
                                   ORDER BY d.orden, d.id LIMIT 1) d ON true
              WHERE s.id_empresa = :e AND s.eliminado = false AND s.estado IN ('activo', 'pausado'){$filtroU}
              ORDER BY " . ($conUnidad ? 'u.torre_bloque, u.codigo NULLS LAST, ' : '') . "c.nombre, s.id"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tieneReajustes(): bool
    {
        return $this->tablaExiste('condominios_reajustes');
    }

    public function insertReajuste(int $idEmpresa, array $r, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_reajustes
                    (id_empresa, descripcion, id_producto, forma, parametro, incluir_sin_inmueble, fecha_aplicar, estado, filas, total_filas, suma_actual, suma_nueva, created_by, created_at)
             VALUES (:e, :d, :p, :f, :par, :inc, :fa, :est, :filas::jsonb, :n, :sa, :sn, :u, CURRENT_TIMESTAMP) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $r['descripcion'], ':p' => $r['id_producto'], ':f' => $r['forma'], ':par' => $r['parametro'],
                      ':inc' => $r['incluir_sin_inmueble'] ? 'true' : 'false', ':fa' => $r['fecha_aplicar'], ':est' => $r['estado'],
                      ':filas' => json_encode($r['filas'], JSON_UNESCAPED_UNICODE), ':n' => count($r['filas']), ':sa' => $r['suma_actual'], ':sn' => $r['suma_nueva'], ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function marcarReajuste(int $id, string $estado, ?string $resultado, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_reajustes
                SET estado = :est, resultado = :res, aplicado_at = CASE WHEN :est2 = 'aplicado' THEN CURRENT_TIMESTAMP ELSE aplicado_at END,
                    aplicado_por = CASE WHEN :est3 = 'aplicado' THEN :u ELSE aplicado_por END, updated_by = :u2, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND eliminado = false"
        )->execute([':est' => $estado, ':res' => $resultado, ':est2' => $estado, ':est3' => $estado, ':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id]);
    }

    public function getReajustes(int $idEmpresa): array
    {
        if (!$this->tieneReajustes()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT r.*, p.nombre AS producto_nombre, us.nombre AS usuario_nombre
               FROM condominios_reajustes r LEFT JOIN productos p ON p.id = r.id_producto LEFT JOIN usuarios us ON us.id = r.created_by
              WHERE r.id_empresa = :e AND r.eliminado = false
              ORDER BY (r.estado = 'pendiente') DESC, r.fecha_aplicar DESC, r.id DESC LIMIT 100"
        );
        $st->execute([':e' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['filas'] = json_decode((string) $r['filas'], true) ?: [];
        }
        unset($r);
        return $rows;
    }

    public function getReajuste(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM condominios_reajustes WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $r['filas'] = json_decode((string) $r['filas'], true) ?: [];
        }
        return $r ?: null;
    }

    /** Reajustes programados cuya fecha ya llegó, de TODAS las empresas (cron fijo diario). */
    public function getReajustesVencidos(): array
    {
        if (!$this->tieneReajustes()) {
            return [];
        }
        $st = $this->db->query(
            "SELECT * FROM condominios_reajustes WHERE eliminado = false AND estado = 'pendiente' AND fecha_aplicar <= CURRENT_DATE ORDER BY fecha_aplicar, id"
        );
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['filas'] = json_decode((string) $r['filas'], true) ?: [];
        }
        unset($r);
        return $rows;
    }

    // ── Restricción de áreas comunes ─────────────────────────────────────────

    public function setRestriccion(int $idUnidad, int $idEmpresa, bool $restringida, ?string $desde, ?string $motivo, int $idUsuario, bool $automatico = false): void
    {
        $this->db->prepare(
            "UPDATE {$this->table}
                SET restringida = :r, restringida_desde = :d, restringida_motivo = :m, restringida_por = :u,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':r' => $restringida ? 'true' : 'false', ':d' => $restringida ? $desde : null,
                    ':m' => $restringida ? $motivo : null, ':u' => $idUsuario, ':id' => $idUnidad, ':e' => $idEmpresa]);
        $this->db->prepare(
            "INSERT INTO condominios_restricciones_log (id_empresa, id_unidad, accion, fecha, motivo, automatico, created_by)
             VALUES (:e, :uni, :acc, :f, :m, :auto, :u)"
        )->execute([':e' => $idEmpresa, ':uni' => $idUnidad, ':acc' => $restringida ? 'marcar' : 'quitar',
                    ':f' => $desde ?: date('Y-m-d'), ':m' => $motivo, ':auto' => $automatico ? 'true' : 'false', ':u' => $idUsuario]);
    }

    public function getRestriccionesLog(int $idUnidad, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT l.*, us.nombre AS usuario_nombre
               FROM condominios_restricciones_log l LEFT JOIN usuarios us ON us.id = l.created_by
              WHERE l.id_unidad = :u AND l.id_empresa = :e ORDER BY l.fecha DESC, l.id DESC"
        );
        $st->execute([':u' => $idUnidad, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Unidades restringidas (listado imprimible para administración y guardianía). */
    public function getRestringidas(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT u.codigo, u.nombre, u.tipo, u.torre_bloque, u.piso, u.restringida_desde, u.restringida_motivo,
                    cp.nombre AS propietario_nombre, ca.nombre AS arrendatario_nombre
               FROM {$this->table} u " . self::JOINS_UNIDADES . "
              WHERE u.id_empresa = :e AND u.eliminado = false AND u.restringida
              ORDER BY u.torre_bloque, u.codigo"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Catálogo de multas ───────────────────────────────────────────────────

    public function getMultas(int $idEmpresa, bool $soloActivas = false): array
    {
        $sql = "SELECT m.*, p.nombre AS producto_nombre
                  FROM condominios_multas_catalogo m LEFT JOIN productos p ON p.id = m.id_producto
                 WHERE m.id_empresa = :e AND m.eliminado = false" . ($soloActivas ? " AND m.estado = 'activo'" : '') . "
                 ORDER BY m.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMulta(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM condominios_multas_catalogo WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertMulta(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_multas_catalogo (id_empresa, nombre, descripcion, valor, id_producto, estado, created_by, created_at)
             VALUES (:e, :n, :d, :v, :p, :s, :u, CURRENT_TIMESTAMP) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':n' => $d['nombre'], ':d' => $d['descripcion'], ':v' => $d['valor'],
                      ':p' => $d['id_producto'], ':s' => $d['estado'], ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function updateMulta(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_multas_catalogo
                SET nombre = :n, descripcion = :d, valor = :v, id_producto = :p, estado = :s, updated_by = :u, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':n' => $d['nombre'], ':d' => $d['descripcion'], ':v' => $d['valor'], ':p' => $d['id_producto'],
                    ':s' => $d['estado'], ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function deleteMulta(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_multas_catalogo SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ── Apoyo: productos, clientes, series, programados ──────────────────────

    /** Servicios activos de la empresa para los selectores de concepto (buscador tipo chip). */
    public function buscarServicios(int $idEmpresa, string $q, int $limite = 12): array
    {
        $params = [':e' => $idEmpresa];
        $where  = "p.id_empresa = :e AND p.eliminado = false AND COALESCE(p.status, 1) = 1 AND p.tipo_produccion = '02'";
        $q = trim($q);
        if ($q !== '') {
            $cond = FiltrosBusqueda::condicionTexto(['p.nombre', 'p.codigo'], $q, $params, 'cs_b');
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.codigo, p.precio_base, COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva
               FROM productos p LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE {$where} ORDER BY p.nombre LIMIT " . (int) $limite
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Un producto de la empresa (para validar los seleccionados). */
    public function getProducto(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, tipo_produccion, status FROM productos WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCliente(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, identificacion, email, status FROM clientes WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Suscripciones del cliente que aún no pertenecen a ninguna unidad (programados migrados u
     * otras suscripciones manuales): candidatas a enlazarse como expensa de la unidad.
     */
    public function getSuscripcionesSinUnidad(int $idCliente, int $idEmpresa): array
    {
        if (!$this->columnaExiste('suscripciones', 'id_unidad')) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT s.id, s.estado, s.proximo_cobro, s.tipo_comprobante, sp.nombre AS periodicidad,
                    (SELECT COALESCE(SUM(d.cantidad * d.precio_unitario), 0) FROM suscripciones_detalle d
                      WHERE d.id_suscripcion = s.id AND d.eliminado = false) AS monto
               FROM suscripciones s LEFT JOIN suscripcion_periodicidades sp ON sp.id = s.id_periodicidad
              WHERE s.id_cliente = :c AND s.id_empresa = :e AND s.eliminado = false AND s.id_unidad IS NULL
                AND s.estado IN ('activo', 'pausado')
              ORDER BY s.id DESC"
        );
        $st->execute([':c' => $idCliente, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inmuebles activos que PAGA este cliente (propietario que paga, o arrendatario pagador):
     * son los que se pueden asociar a una suscripción suya.
     */
    public function getUnidadesPorCliente(int $idCliente, int $idEmpresa): array
    {
        if (!$this->tablaExiste('condominios_unidades')) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT u.id, u.codigo, u.nombre, u.tipo, u.torre_bloque, u.piso, u.id_suscripcion,
                    cp.nombre AS propietario_nombre
               FROM {$this->table} u
               LEFT JOIN clientes cp ON cp.id = u.id_propietario
              WHERE u.id_empresa = :e AND u.eliminado = false AND u.estado = 'activo'
                AND ((u.pagador = 'propietario' AND u.id_propietario = :c1)
                  OR (u.pagador = 'arrendatario' AND u.id_arrendatario = :c2))
              ORDER BY u.torre_bloque, u.codigo"
        );
        $st->execute([':e' => $idEmpresa, ':c1' => $idCliente, ':c2' => $idCliente]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mantiene `condominios_unidades.id_suscripcion` en espejo de `suscripciones.id_unidad`:
     * libera el inmueble que apuntaba a esta suscripción y fija el nuevo (si hay).
     */
    public function espejarSuscripcionEnUnidad(int $idSuscripcion, ?int $idUnidad, int $idEmpresa): void
    {
        if (!$this->tablaExiste('condominios_unidades')) {
            return;
        }
        $this->db->prepare("UPDATE {$this->table} SET id_suscripcion = NULL WHERE id_empresa = :e AND id_suscripcion = :s AND (:u::int IS NULL OR id <> :u2::int)")
            ->execute([':e' => $idEmpresa, ':s' => $idSuscripcion, ':u' => $idUnidad, ':u2' => $idUnidad]);
        if ($idUnidad) {
            $this->db->prepare("UPDATE {$this->table} SET id_suscripcion = :s WHERE id = :u AND id_empresa = :e AND eliminado = false")
                ->execute([':s' => $idSuscripcion, ':u' => $idUnidad, ':e' => $idEmpresa]);
        }
    }

    public function getSuscripcion(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, id_cliente, estado, id_unidad FROM suscripciones WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function enlazarSuscripcion(int $idSuscripcion, int $idEmpresa, ?int $idUnidad, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones SET id_unidad = :u, updated_by = :usr, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':u' => $idUnidad, ':usr' => $idUsuario, ':id' => $idSuscripcion, ':e' => $idEmpresa]);
    }
}
