<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\CierreEjercicioSql;
use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Cierre del Ejercicio (modulos/cierre_ejercicio): registro por año cerrado, saldos de los que
 * salen los asientos de cierre y apertura, y los períodos contables que el cierre bloquea.
 *
 * Todos los saldos se leen de la contabilidad de PRODUCCIÓN (tipo_ambiente '2'), la misma que
 * muestran los Estados Financieros: el módulo exige que la empresa esté en producción.
 */
class CierreEjercicioRepository extends BaseRepository
{
    /** Whitelist y mapa del ORDER BY (§9). */
    public const MAPA_ORDEN = [
        'anio'       => 'ce.anio',
        'resultado'  => 'ce.resultado',
        'estado'     => 'ce.estado',
        'created_at' => 'ce.created_at',
    ];

    /** Códigos de asientos_tipo (tipo 'cierre_ejercicio') → clave interna. */
    private const SLOTS = [
        'UTILIDADEJERCICIOCIERRE'    => 'utilidad',
        'PERDIDAEJERCICIOCIERRE'     => 'perdida',
        'UTILIDADESACUMULADASCIERRE' => 'utilidades_acumuladas',
        'PERDIDASACUMULADASCIERRE'   => 'perdidas_acumuladas',
    ];

    public function __construct()
    {
        parent::__construct('cierre_ejercicio');
    }

    public function tablaDisponible(): bool
    {
        return $this->tablaExiste('cierre_ejercicio');
    }

    // ── Listado ─────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro = null): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'ce', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (CAST(ce.anio AS TEXT) ILIKE :b OR ce.estado ILIKE :b
                             OR COALESCE(ac.numero_comprobante, '') ILIKE :b
                             OR COALESCE(aa.numero_comprobante, '') ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['estado' => 'ce.estado'],
            'numerico' => ['anio' => 'ce.anio', 'resultado' => 'ce.resultado'],
            'fecha'    => ['fecha' => 'ce.created_at'],
        ]);

        $from = "FROM cierre_ejercicio ce
                 LEFT JOIN asientos_contables_cabecera ac ON ac.id = ce.id_asiento_cierre
                 LEFT JOIN asientos_contables_cabecera aa ON aa.id = ce.id_asiento_apertura
                 LEFT JOIN usuarios u ON u.id = ce.created_by
                 LEFT JOIN usuarios ur ON ur.id = ce.revertido_by
                 {$where}";

        $st = $this->db->prepare("SELECT COUNT(*) {$from}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = OrdenListado::clausula($orden, self::MAPA_ORDEN, 'ce.anio', 'ce.id DESC');
        $sql = "SELECT ce.*, ac.numero_comprobante AS numero_cierre, aa.numero_comprobante AS numero_apertura,
                       u.nombre AS creado_por_nombre, ur.nombre AS revertido_por_nombre
                {$from}
                {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        if ($perPage > 0) {
            $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $st->bindValue(':offset', (max(1, $page) - 1) * $perPage, PDO::PARAM_INT);
        }
        $st->execute();

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT ce.*, ac.numero_comprobante AS numero_cierre, aa.numero_comprobante AS numero_apertura,
                    u.nombre AS creado_por_nombre, ur.nombre AS revertido_por_nombre
               FROM cierre_ejercicio ce
               LEFT JOIN asientos_contables_cabecera ac ON ac.id = ce.id_asiento_cierre
               LEFT JOIN asientos_contables_cabecera aa ON aa.id = ce.id_asiento_apertura
               LEFT JOIN usuarios u ON u.id = ce.created_by
               LEFT JOIN usuarios ur ON ur.id = ce.revertido_by
              WHERE ce.id = :id AND ce.id_empresa = :e AND ce.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Líneas de un asiento (código, nombre, debe, haber) para mostrarlo en el detalle. */
    public function getLineasAsiento(int $idAsiento, int $idEmpresa): array
    {
        if ($idAsiento <= 0) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT pc.codigo, pc.nombre, ad.debe, ad.haber
               FROM asientos_contables_detalle ad
               JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento AND ac.id_empresa = :e
               JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
              WHERE ad.id_asiento = :id AND ad.eliminado = false
              ORDER BY pc.codigo, ad.id"
        );
        $st->execute([':id' => $idAsiento, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Estado de los cierres de la empresa ────────────────────────────────

    /** Candado de la empresa: dos cierres o reversiones simultáneos se esperan (§8). */
    public function lockEmpresa(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('cierre_ejercicio:' || CAST(:e AS TEXT)))");
        $st->execute([':e' => $idEmpresa]);
    }

    public function getVigente(int $idEmpresa, int $anio): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM cierre_ejercicio
              WHERE id_empresa = :e AND anio = :a AND estado = 'vigente' AND eliminado = false
              LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':a' => $anio]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Año del último cierre vigente (null si no hay ninguno). */
    public function getUltimoAnioVigente(int $idEmpresa): ?int
    {
        $st = $this->db->prepare(
            "SELECT MAX(anio) FROM cierre_ejercicio WHERE id_empresa = :e AND estado = 'vigente' AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v !== null && $v !== false ? (int) $v : null;
    }

    public function getTipoAmbienteEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return (string) $st->fetchColumn();
    }

    /** Años con asientos de producción (para el selector), del más reciente al más antiguo. */
    public function getAniosConAsientos(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT EXTRACT(YEAR FROM fecha_asiento)::int AS anio
               FROM asientos_contables_cabecera
              WHERE id_empresa = :e AND eliminado = false AND estado = 'contabilizado' AND tipo_ambiente = '2'
              ORDER BY 1 DESC"
        );
        $st->execute([':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    // ── Configuración contable ──────────────────────────────────────────────

    /**
     * Cuentas configuradas en Configuración Contable → Cierre del Ejercicio.
     * @return array<string, array{id:int,codigo:string,nombre:string}|null>
     */
    public function getCuentasConfiguradas(int $idEmpresa): array
    {
        $res = array_fill_keys(array_values(self::SLOTS), null);
        $marcas = [];
        $params = [':emp' => $idEmpresa];
        foreach (array_keys(self::SLOTS) as $i => $codigo) {
            $marcas[] = ":s{$i}";
            $params[":s{$i}"] = $codigo;
        }
        $st = $this->db->prepare(
            "SELECT at.codigo AS slot, pc.id, pc.codigo, pc.nombre
               FROM asientos_tipo at
               JOIN asientos_programados ap
                 ON ap.id_asiento_tipo = at.id AND ap.id_empresa = :emp
                AND ap.id_referencia = at.id
                AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
                AND ap.eliminado = false
               JOIN plan_cuentas pc ON pc.id = ap.id_cuenta AND pc.eliminado = false
              WHERE at.tipo_asiento = 'cierre_ejercicio' AND at.eliminado = false
                AND at.codigo IN (" . implode(', ', $marcas) . ")"
        );
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $res[self::SLOTS[$r['slot']]] = ['id' => (int) $r['id'], 'codigo' => (string) $r['codigo'], 'nombre' => (string) $r['nombre']];
        }
        return $res;
    }

    // ── Saldos ──────────────────────────────────────────────────────────────

    /**
     * Saldo (debe − haber) por cuenta de movimiento entre dos fechas, de los asientos
     * contabilizados de producción. $desde null = desde el primer asiento. No mira los asientos
     * de este módulo (los vigentes de otros años sí: la apertura del año anterior es justamente
     * de donde arranca el año que se cierra) salvo el cierre, que nunca forma parte del saldo
     * que se cierra.
     *
     * @return array<int, array{id:int,codigo:string,nombre:string,saldo:float}>
     */
    public function getSaldosPorCuenta(int $idEmpresa, ?string $desde, string $hasta): array
    {
        $params = [':e' => $idEmpresa, ':hasta' => $hasta];
        $condDesde = '';
        if ($desde !== null) {
            $condDesde = 'AND ac.fecha_asiento >= CAST(:desde AS DATE)';
            $params[':desde'] = $desde;
        }
        $st = $this->db->prepare(
            "SELECT pc.id, pc.codigo, pc.nombre, pc.eliminado, ROUND(SUM(ad.debe - ad.haber), 2) AS saldo
               FROM asientos_contables_detalle ad
               JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
               JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
              WHERE ac.id_empresa = :e
                AND ac.eliminado = false AND ad.eliminado = false
                AND ac.estado = 'contabilizado'
                AND ac.tipo_ambiente = '2'
                AND ac.fecha_asiento <= CAST(:hasta AS DATE)
                {$condDesde}
                AND " . CierreEjercicioSql::sinCierre('ac') . "
              GROUP BY pc.id, pc.codigo, pc.nombre, pc.eliminado
             HAVING ROUND(SUM(ad.debe - ad.haber), 2) <> 0
              ORDER BY pc.codigo"
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = [
                'id' => (int) $r['id'], 'codigo' => (string) $r['codigo'],
                'nombre' => (string) $r['nombre'], 'saldo' => (float) $r['saldo'],
                'eliminada' => in_array($r['eliminado'], [true, 1, 't', 'true', '1'], true),
            ];
        }
        return $out;
    }

    /** Días hacia atrás desde la última apertura en los que otra apertura cuenta como la misma. */
    public const VENTANA_APERTURA_DIAS = 31;

    /**
     * Punto de partida de los saldos de balance: la PRIMERA apertura (cualquier asiento tipo
     * 'apertura' contabilizado de producción) del último grupo de aperturas hasta $hasta. Un
     * grupo son las aperturas registradas hasta VENTANA_APERTURA_DIAS antes de la última: la
     * empresa que cargó bancos el 01-01 y cartera el 02-01 arranca el 01-01, no el 02-01. Se
     * mira hasta el 31-12 del año que se cierra, así una apertura a mitad de año (la empresa
     * empezó en marzo) también es punto de partida. null si nunca hubo una apertura.
     */
    public function getPuntoPartidaApertura(int $idEmpresa, string $hasta): ?string
    {
        $st = $this->db->prepare(
            "WITH ap AS (
                 SELECT fecha_asiento FROM asientos_contables_cabecera
                  WHERE id_empresa = :e AND eliminado = false AND estado = 'contabilizado' AND tipo_ambiente = '2'
                    AND LOWER(COALESCE(tipo_comprobante, '')) = 'apertura'
                    AND fecha_asiento <= CAST(:h AS DATE)
             )
             SELECT MIN(fecha_asiento) FROM ap
              WHERE fecha_asiento >= (SELECT MAX(fecha_asiento) FROM ap) - CAST(:dias AS INTEGER)"
        );
        $st->execute([':e' => $idEmpresa, ':h' => $hasta, ':dias' => self::VENTANA_APERTURA_DIAS]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /**
     * Aperturas que NO generó este módulo, con fecha en el año siguiente al que se cierra: la
     * apertura del módulo las duplicaría (los reportes de ese año sumarían las dos).
     */
    public function getAperturasAjenasEntre(int $idEmpresa, string $despuesDe, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT id, numero_comprobante, fecha_asiento, concepto, estado
               FROM asientos_contables_cabecera
              WHERE id_empresa = :e AND eliminado = false AND estado <> 'anulado' AND tipo_ambiente = '2'
                AND LOWER(COALESCE(tipo_comprobante, '')) = 'apertura'
                AND COALESCE(modulo_origen, '') <> '" . CierreEjercicioSql::ORIGEN_APERTURA . "'
                AND fecha_asiento > CAST(:d AS DATE) AND fecha_asiento <= CAST(:h AS DATE)
              ORDER BY fecha_asiento, id"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $despuesDe, ':h' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asientos del rango que ya CERRARON resultados por fuera del módulo: los de tipo 'cierre'
     * (manuales o de la migración) y los que solo tocan cuentas de resultados (4/5/6) contra
     * patrimonio o la clase 7 (el cierre del sistema anterior llegó como 'diario'). Un asiento
     * que mueve también activo o pasivo (provisiones, impuestos) no es un cierre.
     * `utilidad_movida` es lo que sacó de las cuentas 4/5/6 (+ utilidad, − pérdida).
     */
    public function getCierresAjenos(int $idEmpresa, string $desde, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT ac.id, ac.numero_comprobante, ac.fecha_asiento, ac.tipo_comprobante,
                    ROUND(SUM(CASE WHEN LEFT(pc.codigo, 1) IN ('4', '5', '6') THEN ad.debe - ad.haber ELSE 0 END), 2) AS utilidad_movida
               FROM asientos_contables_cabecera ac
               JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
               JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
              WHERE ac.id_empresa = :e AND ac.eliminado = false AND ac.estado = 'contabilizado' AND ac.tipo_ambiente = '2'
                AND ac.fecha_asiento BETWEEN CAST(:d AS DATE) AND CAST(:h AS DATE)
                AND " . CierreEjercicioSql::acumulado('ac') . "
              GROUP BY ac.id, ac.numero_comprobante, ac.fecha_asiento, ac.tipo_comprobante
             HAVING BOOL_AND(LEFT(pc.codigo, 1) IN ('3', '4', '5', '6', '7'))
                AND (LOWER(COALESCE(ac.tipo_comprobante, '')) = 'cierre'
                     OR (BOOL_OR(LEFT(pc.codigo, 1) IN ('4', '5', '6')) AND BOOL_OR(LEFT(pc.codigo, 1) IN ('3', '7'))))
              ORDER BY ac.fecha_asiento, ac.id"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrimeraFechaAsiento(int $idEmpresa): ?string
    {
        $st = $this->db->prepare(
            "SELECT MIN(fecha_asiento) FROM asientos_contables_cabecera
              WHERE id_empresa = :e AND eliminado = false AND estado = 'contabilizado' AND tipo_ambiente = '2'"
        );
        $st->execute([':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Asientos en borrador del rango: no entran al cierre (se avisa). */
    public function contarBorradores(int $idEmpresa, string $desde, string $hasta): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM asientos_contables_cabecera
              WHERE id_empresa = :e AND eliminado = false AND estado = 'borrador' AND tipo_ambiente = '2'
                AND fecha_asiento BETWEEN CAST(:d AS DATE) AND CAST(:h AS DATE)"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta]);
        return (int) $st->fetchColumn();
    }

    /**
     * Asientos con fecha en el tramo cerrado que se crearon o modificaron DESPUÉS del cierre
     * (alguien reabrió un período): el cierre ya no refleja esos saldos.
     */
    public function contarCambiosPosteriores(int $idEmpresa, ?string $desde, string $hasta, string $fechaCierre): int
    {
        $params = [':e' => $idEmpresa, ':h' => $hasta, ':fc' => $fechaCierre];
        $condDesde = '';
        if ($desde !== null) {
            $condDesde = 'AND fecha_asiento >= CAST(:d AS DATE)';
            $params[':d'] = $desde;
        }
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM asientos_contables_cabecera ac
              WHERE ac.id_empresa = :e AND ac.eliminado = false AND ac.tipo_ambiente = '2'
                AND ac.fecha_asiento <= CAST(:h AS DATE) {$condDesde}
                AND GREATEST(ac.created_at, COALESCE(ac.updated_at, ac.created_at)) > CAST(:fc AS TIMESTAMP)
                AND " . CierreEjercicioSql::acumulado('ac')
        );
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    // ── Registro ────────────────────────────────────────────────────────────

    public function insertar(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO cierre_ejercicio
                (id_empresa, anio, fecha_cierre, fecha_apertura, saldos_desde, id_asiento_cierre, id_asiento_apertura,
                 resultado, resultado_anterior, total_activos, total_pasivos, total_patrimonio, periodos,
                 estado, observaciones, created_by, created_at)
             VALUES
                (:id_empresa, :anio, :fecha_cierre, :fecha_apertura, :saldos_desde, :id_asiento_cierre, :id_asiento_apertura,
                 :resultado, :resultado_anterior, :total_activos, :total_pasivos, :total_patrimonio, CAST(:periodos AS JSONB),
                 'vigente', :observaciones, :created_by, now())
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'          => $d['id_empresa'],
            ':anio'                => $d['anio'],
            ':fecha_cierre'        => $d['fecha_cierre'],
            ':fecha_apertura'      => $d['fecha_apertura'],
            ':saldos_desde'        => $d['saldos_desde'],
            ':id_asiento_cierre'   => $d['id_asiento_cierre'],
            ':id_asiento_apertura' => $d['id_asiento_apertura'],
            ':resultado'           => $d['resultado'],
            ':resultado_anterior'  => $d['resultado_anterior'],
            ':total_activos'       => $d['total_activos'],
            ':total_pasivos'       => $d['total_pasivos'],
            ':total_patrimonio'    => $d['total_patrimonio'],
            ':periodos'            => json_encode($d['periodos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':observaciones'       => $d['observaciones'],
            ':created_by'          => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    /** Enlaza los asientos al registro (se crean después, porque llevan su id como referencia). */
    public function setAsientos(int $id, int $idEmpresa, ?int $idCierre, ?int $idApertura): void
    {
        $st = $this->db->prepare(
            "UPDATE cierre_ejercicio SET id_asiento_cierre = :c, id_asiento_apertura = :a
              WHERE id = :id AND id_empresa = :e"
        );
        $st->bindValue(':c', $idCierre, $idCierre === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':a', $idApertura, $idApertura === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->execute();
    }

    public function setPeriodos(int $id, int $idEmpresa, array $periodos): void
    {
        $st = $this->db->prepare(
            "UPDATE cierre_ejercicio SET periodos = CAST(:p AS JSONB) WHERE id = :id AND id_empresa = :e"
        );
        $st->execute([':p' => json_encode($periodos, JSON_UNESCAPED_UNICODE), ':id' => $id, ':e' => $idEmpresa]);
    }

    public function marcarRevertido(int $id, int $idEmpresa, int $idUsuario, string $motivo): void
    {
        $st = $this->db->prepare(
            "UPDATE cierre_ejercicio
                SET estado = 'revertido', motivo_reversion = :m, revertido_at = now(), revertido_by = :u,
                    updated_at = now(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND estado = 'vigente' AND eliminado = false"
        );
        $st->execute([':m' => $motivo, ':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ── Períodos contables del año ──────────────────────────────────────────

    /** Períodos vivos que caen completos dentro del año. */
    public function getPeriodosDentro(int $idEmpresa, string $desde, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, fecha_inicial, fecha_final, status FROM periodos_contables
              WHERE id_empresa = :e AND eliminado = false
                AND fecha_inicial >= CAST(:d AS DATE) AND fecha_final <= CAST(:h AS DATE)
              ORDER BY fecha_inicial"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿Hay un período cerrado que cubre el año completo? */
    public function existePeriodoCerradoQueCubre(int $idEmpresa, string $desde, string $hasta): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM periodos_contables
              WHERE id_empresa = :e AND eliminado = false AND status = 0
                AND fecha_inicial <= CAST(:d AS DATE) AND fecha_final >= CAST(:h AS DATE)
              LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':d' => $desde, ':h' => $hasta]);
        return (bool) $st->fetchColumn();
    }

    public function setStatusPeriodo(int $idPeriodo, int $idEmpresa, int $status, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE periodos_contables SET status = :s, updated_by = :u, updated_at = now()
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':s' => $status, ':u' => $idUsuario, ':id' => $idPeriodo, ':e' => $idEmpresa]);
    }

    public function crearPeriodoCerrado(int $idEmpresa, string $nombre, string $desde, string $hasta, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO periodos_contables (id_empresa, id_usuario, nombre, fecha_inicial, fecha_final, status, created_by, created_at)
             VALUES (:e, :u, :n, :d, :h, 0, :u2, now())
             RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':n' => $nombre, ':d' => $desde, ':h' => $hasta, ':u2' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function eliminarPeriodo(int $idPeriodo, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE periodos_contables SET eliminado = true, deleted_at = now(), deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id' => $idPeriodo, ':e' => $idEmpresa]);
    }

    public function getPeriodo(int $idPeriodo, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM periodos_contables WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idPeriodo, ':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
}
