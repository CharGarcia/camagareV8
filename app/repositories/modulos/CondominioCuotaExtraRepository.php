<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Condominios — cuotas extraordinarias con meta pagadas a plazos (p. ej. «Pintura del edificio»
 * en 24 cuotas). Tablas: condominios_aportes (tipo 'meta'), condominios_aportes_unidades (plan de
 * cada inmueble) y condominios_cargos (una fila por cuota, con el documento en que salió).
 * Acceso a datos puro; toda consulta operativa filtra id_empresa + eliminado = false.
 * SQL: database/migrations/20261117_condominios_cuotas_extraordinarias.sql.
 * Diseño: docs/diseno/condominios-cuotas-extraordinarias.md.
 */
class CondominioCuotaExtraRepository extends BaseRepository
{
    /** Ordenamiento del listado (whitelist + mapa, CLAUDE.md §9). */
    public const MAPA_ORDEN = [
        'nombre'      => 'a.nombre',
        'fecha_acta'  => 'a.fecha_acta',
        'meta'        => 'a.meta_total',
        'inmuebles'   => 'inmuebles',
        'emitido'     => 'emitido',
        'por_emitir'  => 'por_emitir',
        'estado'      => 'a.estado',
        'creado'      => 'a.created_at',
    ];

    public function __construct()
    {
        parent::__construct('condominios_aportes');
    }

    /** ¿Está aplicado 20261117_condominios_cuotas_extraordinarias.sql? */
    public function instalado(): bool
    {
        return $this->tablaExiste('condominios_cargos') && $this->columnaExiste('condominios_cargos', 'subtipo');
    }

    private static function arrInt(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        return '{' . implode(',', $ids) . '}';
    }

    // ── Cuotas extraordinarias ───────────────────────────────────────────────

    public function listar(int $idEmpresa, string $buscar = '', array $ordenMulti = []): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'a') . " AND a.tipo = 'meta'";
        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(['a.nombre', 'a.acta', 'a.descripcion', 'p.nombre'], $parsed['texto_libre'], $params, 'ce_b');
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => ['nombre' => 'a.nombre', 'acta' => 'a.acta', 'concepto' => 'p.nombre'],
            'exacto' => ['estado' => 'a.estado', 'donde' => 'a.donde_cobro'],
            'fecha'  => ['fecha_acta' => 'a.fecha_acta'],
            'numerico' => ['meta' => 'a.meta_total'],
        ]);
        // Sin orden elegido: las más recientes primero. clausula() ya devuelve «ORDER BY …».
        $criterios = OrdenListado::normalizar($ordenMulti) ?: [['col' => 'creado', 'dir' => 'DESC']];
        $orderBy = OrdenListado::clausula($criterios, self::MAPA_ORDEN, 'a.created_at', 'a.id DESC');
        $st = $this->db->prepare(
            "SELECT * FROM (
                SELECT a.id, a.nombre, a.acta, a.fecha_acta, a.meta_total, a.calculo, a.donde_cobro, a.estado,
                       a.plazo_defecto, a.primer_periodo, a.created_at, p.nombre AS producto_nombre,
                       COALESCE(pl.inmuebles, 0) AS inmuebles, COALESCE(pl.anticipos, 0) AS anticipos,
                       COALESCE(cg.emitido, 0) AS emitido, COALESCE(cg.por_emitir, 0) AS por_emitir
                  FROM condominios_aportes a
                  LEFT JOIN productos p ON p.id = a.id_producto
                  LEFT JOIN LATERAL (
                       SELECT COUNT(*) AS inmuebles, SUM(x.anticipo_aplicado) AS anticipos
                         FROM condominios_aportes_unidades x
                        WHERE x.id_aporte = a.id AND x.eliminado = false AND x.estado <> 'cancelado'
                  ) pl ON true
                  LEFT JOIN LATERAL (
                       SELECT SUM(c.monto) FILTER (WHERE c.estado = 'emitido')   AS emitido,
                              SUM(c.monto) FILTER (WHERE c.estado = 'pendiente') AS por_emitir
                         FROM condominios_cargos c
                        WHERE c.id_aporte = a.id AND c.eliminado = false AND c.concepto = 'aporte'
                  ) cg ON true
                 {$where}
             ) a
             {$orderBy}
             LIMIT 500"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAporte(int $id, int $idEmpresa, bool $bloquear = false): ?array
    {
        $st = $this->db->prepare(
            "SELECT a.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    es.codigo || '-' || pe.codigo_punto AS serie_texto, fp.nombre AS forma_anticipo_nombre
               FROM condominios_aportes a
               LEFT JOIN productos p ON p.id = a.id_producto
               LEFT JOIN empresa_punto_emision pe ON pe.id = a.id_punto_emision
               LEFT JOIN empresa_establecimiento es ON es.id = pe.id_establecimiento
               LEFT JOIN empresa_formas_pago fp ON fp.id = a.id_forma_anticipo
              WHERE a.id = :id AND a.id_empresa = :e AND a.eliminado = false AND a.tipo = 'meta'"
            . ($bloquear ? ' FOR UPDATE OF a' : '')
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertAporte(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_aportes
                (id_empresa, nombre, descripcion, id_producto, tipo, calculo, valor, meta_total, vigente_desde,
                 plazos_permitidos, plazo_defecto, primer_periodo, donde_cobro, pagador_defecto, acta, fecha_acta,
                 tipo_comprobante, id_punto_emision, id_forma_anticipo, estado, created_at, created_by)
             VALUES (:e, :nombre, :descripcion, :producto, 'meta', :calculo, 0, :meta, :vigente,
                 :plazos::jsonb, :plazo_def, :primer, :donde, :pagador, :acta, :fecha_acta,
                 :tipo_comp, :punto, :forma, 'activo', CURRENT_TIMESTAMP, :u)
             RETURNING id"
        );
        $st->execute($this->paramsAporte($d) + [':e' => $idEmpresa, ':u' => $idUsuario, ':vigente' => $d['vigente_desde']]);
        return (int) $st->fetchColumn();
    }

    public function updateAporte(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE condominios_aportes SET nombre = :nombre, descripcion = :descripcion, id_producto = :producto,
                    calculo = :calculo, meta_total = :meta, plazos_permitidos = :plazos::jsonb, plazo_defecto = :plazo_def,
                    primer_periodo = :primer, donde_cobro = :donde, pagador_defecto = :pagador, acta = :acta,
                    fecha_acta = :fecha_acta, tipo_comprobante = :tipo_comp, id_punto_emision = :punto,
                    id_forma_anticipo = :forma, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute($this->paramsAporte($d) + [':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    private function paramsAporte(array $d): array
    {
        return [
            ':nombre' => $d['nombre'], ':descripcion' => $d['descripcion'] !== '' ? $d['descripcion'] : null,
            ':producto' => $d['id_producto'], ':calculo' => $d['calculo'], ':meta' => $d['meta_total'],
            ':plazos' => json_encode(array_values($d['plazos_permitidos'])), ':plazo_def' => $d['plazo_defecto'],
            ':primer' => $d['primer_periodo'], ':donde' => $d['donde_cobro'], ':pagador' => $d['pagador_defecto'],
            ':acta' => $d['acta'] !== '' ? $d['acta'] : null, ':fecha_acta' => $d['fecha_acta'] ?: null,
            ':tipo_comp' => $d['tipo_comprobante'], ':punto' => $d['id_punto_emision'], ':forma' => $d['id_forma_anticipo'] ?: null,
        ];
    }

    public function setEstadoAporte(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_aportes SET estado = :s, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':s' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminarAporte(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_aportes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ── Inmuebles y planes ───────────────────────────────────────────────────

    /** Inmuebles activos del condominio con su propietario / arrendatario (base del reparto). */
    public function getInmueblesActivos(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT u.id, u.codigo, u.nombre, u.tipo, u.torre_bloque, u.alicuota_pct, u.area_m2, u.id_suscripcion,
                    u.id_propietario, cp.nombre AS propietario_nombre, cp.identificacion AS propietario_identificacion,
                    u.id_arrendatario, ca.nombre AS arrendatario_nombre
               FROM condominios_unidades u
               LEFT JOIN clientes cp ON cp.id = u.id_propietario
               LEFT JOIN clientes ca ON ca.id = u.id_arrendatario AND ca.eliminado = false
              WHERE u.id_empresa = :e AND u.eliminado = false AND u.estado = 'activo'
              ORDER BY u.codigo, u.id"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Planes de una cuota extraordinaria con el avance de sus cuotas. */
    public function getPlanes(int $idAporte, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT pl.*, u.codigo AS unidad_codigo, u.nombre AS unidad_nombre, u.tipo AS unidad_tipo,
                    u.id_propietario, u.id_arrendatario, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                    COALESCE(cg.cuotas_emitidas, 0) AS cuotas_emitidas, COALESCE(cg.cuotas_pendientes, 0) AS cuotas_pendientes,
                    COALESCE(cg.emitido, 0) AS emitido, COALESCE(cg.por_emitir, 0) AS por_emitir, cg.proximo_periodo
               FROM condominios_aportes_unidades pl
               JOIN condominios_unidades u ON u.id = pl.id_unidad
               LEFT JOIN clientes c ON c.id = pl.id_cliente
               LEFT JOIN LATERAL (
                    SELECT COUNT(*) FILTER (WHERE x.estado = 'emitido' AND x.subtipo = 'cuota')   AS cuotas_emitidas,
                           COUNT(*) FILTER (WHERE x.estado = 'pendiente' AND x.subtipo = 'cuota') AS cuotas_pendientes,
                           SUM(x.monto) FILTER (WHERE x.estado = 'emitido')   AS emitido,
                           SUM(x.monto) FILTER (WHERE x.estado = 'pendiente') AS por_emitir,
                           MIN(x.periodo) FILTER (WHERE x.estado = 'pendiente') AS proximo_periodo
                      FROM condominios_cargos x
                     WHERE x.id_aporte_unidad = pl.id AND x.eliminado = false
               ) cg ON true
              WHERE pl.id_aporte = :a AND pl.id_empresa = :e AND pl.eliminado = false
              ORDER BY u.codigo, pl.id"
        );
        $st->execute([':a' => $idAporte, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlan(int $id, int $idEmpresa, bool $bloquear = false): ?array
    {
        $st = $this->db->prepare(
            "SELECT pl.*, u.codigo AS unidad_codigo, u.nombre AS unidad_nombre, u.id_propietario, u.id_arrendatario,
                    c.nombre AS cliente_nombre, c.email AS cliente_email
               FROM condominios_aportes_unidades pl
               JOIN condominios_unidades u ON u.id = pl.id_unidad
               LEFT JOIN clientes c ON c.id = pl.id_cliente
              WHERE pl.id = :id AND pl.id_empresa = :e AND pl.eliminado = false"
            . ($bloquear ? ' FOR UPDATE OF pl' : '')
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Planes vigentes de un inmueble (para la pregunta de venta / cambio de arrendatario). */
    public function getPlanesVigentesUnidad(int $idUnidad, int $idEmpresa): array
    {
        if (!$this->instalado()) {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT pl.id, pl.id_aporte, pl.pagador, pl.id_cliente, a.nombre AS aporte_nombre, c.nombre AS cliente_nombre,
                    COALESCE((SELECT SUM(x.monto) FROM condominios_cargos x
                               WHERE x.id_aporte_unidad = pl.id AND x.eliminado = false AND x.estado = 'pendiente'), 0) AS por_emitir,
                    (SELECT COUNT(*) FROM condominios_cargos x
                      WHERE x.id_aporte_unidad = pl.id AND x.eliminado = false AND x.estado = 'pendiente' AND x.subtipo = 'cuota') AS cuotas_pendientes
               FROM condominios_aportes_unidades pl
               JOIN condominios_aportes a ON a.id = pl.id_aporte AND a.eliminado = false
               LEFT JOIN clientes c ON c.id = pl.id_cliente
              WHERE pl.id_unidad = :u AND pl.id_empresa = :e AND pl.eliminado = false AND pl.estado IN ('pendiente', 'en_curso')
              ORDER BY a.nombre"
        );
        $st->execute([':u' => $idUnidad, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existePlan(int $idAporte, int $idUnidad): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM condominios_aportes_unidades WHERE id_aporte = :a AND id_unidad = :u AND eliminado = false LIMIT 1"
        );
        $st->execute([':a' => $idAporte, ':u' => $idUnidad]);
        return (bool) $st->fetchColumn();
    }

    public function insertPlan(int $idEmpresa, array $p, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_aportes_unidades
                (id_empresa, id_aporte, id_unidad, monto_asignado, ajuste_manual, plazo_elegido, recargo_aplicado, monto_total,
                 anticipo_aplicado, saldo_plazos, cuota_valor, cuota_ultima, primer_periodo, pagador, id_cliente, estado,
                 created_at, created_by)
             VALUES (:e, :a, :u, :asignado, :manual, :plazo, 0, :total, :anticipo, :saldo, :cuota, :ultima, :primer,
                 :pagador, :cliente, :estado, CURRENT_TIMESTAMP, :usr)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':a' => $p['id_aporte'], ':u' => $p['id_unidad'], ':asignado' => $p['monto_asignado'],
            ':manual' => $p['ajuste_manual'] ? 'true' : 'false', ':plazo' => $p['plazo'], ':total' => $p['monto_asignado'],
            ':anticipo' => $p['anticipo'], ':saldo' => $p['saldo_plazos'], ':cuota' => $p['cuota_valor'],
            ':ultima' => $p['cuota_ultima'], ':primer' => $p['primer_periodo'], ':pagador' => $p['pagador'],
            ':cliente' => $p['id_cliente'], ':estado' => $p['saldo_plazos'] > 0 ? 'en_curso' : 'completo', ':usr' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarPlan(int $id, array $campos, int $idUsuario): void
    {
        $permitidos = ['plazo_elegido', 'saldo_plazos', 'cuota_valor', 'cuota_ultima', 'estado', 'pagador', 'id_cliente',
                       'tipo_documento_abono', 'id_documento_abono', 'id_ingreso_abono', 'fecha_liquidacion',
                       'motivo_liquidacion', 'id_cliente_liquidacion', 'observacion'];
        $set = [];
        $params = [':id' => $id, ':u' => $idUsuario];
        foreach ($campos as $k => $v) {
            if (in_array($k, $permitidos, true)) {
                $set[] = "{$k} = :{$k}";
                $params[":{$k}"] = $v;
            }
        }
        if ($set) {
            $this->db->prepare('UPDATE condominios_aportes_unidades SET ' . implode(', ', $set)
                . ', updated_at = CURRENT_TIMESTAMP, updated_by = :u WHERE id = :id')->execute($params);
        }
    }

    /** Pasa a 'completo' los planes en curso que ya no tienen cuotas pendientes. */
    public function cerrarPlanesSinPendientes(array $idsPlan): void
    {
        if (!$idsPlan) {
            return;
        }
        $this->db->prepare(
            "UPDATE condominios_aportes_unidades pl SET estado = 'completo', updated_at = CURRENT_TIMESTAMP
              WHERE pl.id = ANY(:ids::int[]) AND pl.estado = 'en_curso'
                AND NOT EXISTS (SELECT 1 FROM condominios_cargos x
                                 WHERE x.id_aporte_unidad = pl.id AND x.eliminado = false AND x.estado = 'pendiente')"
        )->execute([':ids' => self::arrInt($idsPlan)]);
    }

    // ── Cargos (cuotas) ──────────────────────────────────────────────────────

    public function insertCargo(int $idEmpresa, array $c, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO condominios_cargos
                (id_empresa, id_unidad, periodo, concepto, subtipo, id_aporte, id_aporte_unidad, id_producto, descripcion,
                 monto, numero_cuota, total_cuotas, id_cliente, donde_cobro, estado, origen, created_at, created_by)
             VALUES (:e, :u, :periodo, 'aporte', :subtipo, :a, :pl, :producto, :descripcion,
                 :monto, :num, :total, :cliente, :donde, 'pendiente', 'automatico', CURRENT_TIMESTAMP, :usr)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':u' => $c['id_unidad'], ':periodo' => $c['periodo'], ':subtipo' => $c['subtipo'],
            ':a' => $c['id_aporte'], ':pl' => $c['id_aporte_unidad'], ':producto' => $c['id_producto'],
            ':descripcion' => mb_substr($c['descripcion'], 0, 300), ':monto' => $c['monto'],
            ':num' => $c['numero_cuota'] ?? null, ':total' => $c['total_cuotas'] ?? null, ':cliente' => $c['id_cliente'],
            ':donde' => $c['donde_cobro'], ':usr' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Cuotas de un plan (todas o solo pendientes), con el documento en que salieron. */
    public function getCargosPlan(int $idPlan, bool $soloPendientes = false, bool $bloquear = false): array
    {
        $st = $this->db->prepare(
            "SELECT c.* FROM condominios_cargos c
              WHERE c.id_aporte_unidad = :pl AND c.eliminado = false"
            . ($soloPendientes ? " AND c.estado = 'pendiente'" : " AND c.estado <> 'anulado'")
            . ' ORDER BY c.periodo, c.numero_cuota NULLS FIRST, c.id'
            . ($bloquear ? ' FOR UPDATE' : '')
        );
        $st->execute([':pl' => $idPlan]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todas las cuotas de una cuota extraordinaria, con inmueble, cliente y documento (pestaña Cuotas y cobrado). */
    public function getCargosAporte(int $idAporte, int $idEmpresa, ?string $periodo = null): array
    {
        $params = [':a' => $idAporte, ':e' => $idEmpresa];
        $extra = '';
        if ($periodo !== null) {
            $extra = ' AND c.periodo = :p';
            $params[':p'] = $periodo;
        }
        $st = $this->db->prepare(
            "SELECT c.id, c.id_aporte_unidad, c.id_unidad, c.periodo, c.subtipo, c.numero_cuota, c.total_cuotas, c.monto,
                    c.estado, c.donde_cobro, c.tipo_documento, c.id_documento, c.fecha_emision, c.id_cliente, c.descripcion,
                    u.codigo AS unidad_codigo, cl.nombre AS cliente_nombre,
                    CASE WHEN c.tipo_documento = 'factura' THEN fv.establecimiento || '-' || fv.punto_emision || '-' || fv.secuencial
                         WHEN c.tipo_documento = 'recibo'  THEN rv.establecimiento || '-' || rv.punto_emision || '-' || rv.secuencial END AS documento_numero,
                    CASE WHEN c.tipo_documento = 'factura' THEN (fv.estado = 'anulado' OR fv.eliminado)
                         WHEN c.tipo_documento = 'recibo'  THEN (rv.estado = 'anulado' OR rv.eliminado)
                         ELSE false END AS documento_anulado
               FROM condominios_cargos c
               JOIN condominios_unidades u ON u.id = c.id_unidad
               LEFT JOIN clientes cl ON cl.id = c.id_cliente
               LEFT JOIN ventas_cabecera fv ON c.tipo_documento = 'factura' AND fv.id = c.id_documento
               LEFT JOIN recibos_venta_cabecera rv ON c.tipo_documento = 'recibo' AND rv.id = c.id_documento
              WHERE c.id_aporte = :a AND c.id_empresa = :e AND c.eliminado = false AND c.concepto = 'aporte'
                AND c.estado <> 'anulado'{$extra}
              ORDER BY c.periodo, u.codigo, c.numero_cuota NULLS FIRST, c.id"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCargo(int $id, int $idEmpresa, bool $bloquear = false): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM condominios_cargos WHERE id = :id AND id_empresa = :e AND eliminado = false" . ($bloquear ? ' FOR UPDATE' : '')
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cuotas «junto» que deben salir en el documento de una suscripción: pendientes, de los
     * inmuebles enlazados a ella, cobradas al mismo cliente y de este mes o atrasadas. Las
     * bloquea (FOR UPDATE) hasta el COMMIT del documento; las reservadas por una emisión aparte
     * en curso no se toman.
     */
    public function tomarCargosSuscripcion(int $idSuscripcion, int $idCliente, string $periodo, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_aporte_unidad, c.monto, c.descripcion, c.numero_cuota, c.total_cuotas,
                    p.id AS id_producto, p.codigo AS codigo_producto, p.nombre AS nombre_producto, p.tarifa_iva AS id_tarifa_iva,
                    COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva, COALESCE(ti.codigo::text, '0') AS codigo_porcentaje
               FROM condominios_cargos c
               JOIN condominios_unidades u ON u.id = c.id_unidad AND u.id_suscripcion = :s AND u.eliminado = false
               JOIN condominios_aportes a ON a.id = c.id_aporte AND a.eliminado = false AND a.estado = 'activo'
               JOIN productos p ON p.id = c.id_producto
               LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
               LEFT JOIN condominios_emisiones_items it ON it.id = c.id_emision_item
              WHERE c.id_empresa = :e AND c.eliminado = false AND c.estado = 'pendiente' AND c.concepto = 'aporte'
                AND c.subtipo = 'cuota' AND c.donde_cobro = 'junto' AND c.id_cliente = :cli AND c.periodo <= :per
                AND (c.id_emision_item IS NULL OR it.estado NOT IN ('pendiente', 'procesando'))
              ORDER BY c.periodo, c.numero_cuota, c.id
              FOR UPDATE OF c"
        );
        $st->execute([':s' => $idSuscripcion, ':cli' => $idCliente, ':per' => $periodo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuotas reservadas para un ítem de emisión aparte (worker), bloqueadas hasta el COMMIT. */
    public function tomarCargosItem(int $idItem, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_aporte_unidad, c.monto, c.descripcion, c.numero_cuota, c.total_cuotas,
                    p.id AS id_producto, p.codigo AS codigo_producto, p.nombre AS nombre_producto, p.tarifa_iva AS id_tarifa_iva,
                    COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva, COALESCE(ti.codigo::text, '0') AS codigo_porcentaje
               FROM condominios_cargos c
               JOIN productos p ON p.id = c.id_producto
               LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE c.id_emision_item = :i AND c.id_empresa = :e AND c.eliminado = false AND c.estado = 'pendiente'
              ORDER BY c.periodo, c.numero_cuota, c.id
              FOR UPDATE OF c"
        );
        $st->execute([':i' => $idItem, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cargos por id (abono inicial / liquidación), bloqueados hasta el COMMIT. */
    public function tomarCargosIds(array $ids, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_aporte_unidad, c.monto, c.descripcion, c.numero_cuota, c.total_cuotas,
                    p.id AS id_producto, p.codigo AS codigo_producto, p.nombre AS nombre_producto, p.tarifa_iva AS id_tarifa_iva,
                    COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva, COALESCE(ti.codigo::text, '0') AS codigo_porcentaje
               FROM condominios_cargos c
               JOIN productos p ON p.id = c.id_producto
               LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE c.id = ANY(:ids::int[]) AND c.id_empresa = :e AND c.eliminado = false AND c.estado = 'pendiente'
              ORDER BY c.id
              FOR UPDATE OF c"
        );
        $st->execute([':ids' => self::arrInt($ids), ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarEmitidos(array $ids, string $tipoDocumento, int $idDocumento, ?int $idSuscripcion, int $idUsuario): void
    {
        if (!$ids) {
            return;
        }
        $this->db->prepare(
            "UPDATE condominios_cargos SET estado = 'emitido', tipo_documento = :t, id_documento = :d, id_suscripcion = :s,
                    fecha_emision = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = ANY(:ids::int[]) AND estado = 'pendiente'"
        )->execute([':t' => $tipoDocumento, ':d' => $idDocumento, ':s' => $idSuscripcion, ':u' => $idUsuario ?: null, ':ids' => self::arrInt($ids)]);
    }

    /** Anula las cuotas pendientes de un plan (liquidación, cambio de plazo, cierre). Devuelve cuántas. */
    public function anularPendientesPlan(int $idPlan, int $idUsuario, string $observacion): int
    {
        $st = $this->db->prepare(
            "UPDATE condominios_cargos SET estado = 'anulado', observacion = :o, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id_aporte_unidad = :pl AND eliminado = false AND estado = 'pendiente'"
        );
        $st->execute([':o' => mb_substr($observacion, 0, 300), ':u' => $idUsuario, ':pl' => $idPlan]);
        return $st->rowCount();
    }

    /** Venta «seguir con la deuda»: las cuotas pendientes desde un mes pasan a otro cliente. */
    public function cambiarClientePendientes(int $idPlan, int $idCliente, string $desdePeriodo, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "UPDATE condominios_cargos SET id_cliente = :c, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id_aporte_unidad = :pl AND eliminado = false AND estado = 'pendiente' AND periodo >= :p"
        );
        $st->execute([':c' => $idCliente, ':u' => $idUsuario, ':pl' => $idPlan, ':p' => $desdePeriodo]);
        return $st->rowCount();
    }

    /** Devuelve a pendiente una cuota cuyo documento se anuló. */
    public function volverAPendiente(int $idCargo, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE condominios_cargos SET estado = 'pendiente', tipo_documento = NULL, id_documento = NULL, id_suscripcion = NULL,
                    id_emision_item = NULL, fecha_emision = NULL, updated_at = CURRENT_TIMESTAMP, updated_by = :u,
                    observacion = 'Vuelta a pendiente: su documento fue anulado.'
              WHERE id = :id AND estado = 'emitido'"
        )->execute([':u' => $idUsuario, ':id' => $idCargo]);
        $this->db->prepare(
            "UPDATE condominios_aportes_unidades pl SET estado = 'en_curso', updated_at = CURRENT_TIMESTAMP
              WHERE pl.id = (SELECT id_aporte_unidad FROM condominios_cargos WHERE id = :id) AND pl.estado = 'completo'"
        )->execute([':id' => $idCargo]);
    }

    public function documentoAnulado(string $tipo, int $idDocumento): bool
    {
        $tabla = $tipo === 'factura' ? 'ventas_cabecera' : 'recibos_venta_cabecera';
        // fetch() de la fila (no fetchColumn): un booleano false de PostgreSQL no debe confundirse
        // con «no existe el documento».
        $st = $this->db->prepare("SELECT (estado = 'anulado' OR eliminado) AS anulado FROM {$tabla} WHERE id = :id");
        $st->execute([':id' => $idDocumento]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return !$r || in_array($r['anulado'], [true, 't', 1, '1'], true);
    }

    // ── Emisión aparte ───────────────────────────────────────────────────────

    /**
     * Cuotas que se emiten en documento propio hasta un mes: las «aparte» y las «junto» que no
     * pueden ir con la alícuota (inmueble sin suscripción activa, o la suscripción es de otro
     * cliente). Excluye las reservadas por una emisión en curso.
     */
    public function getCargosParaAparte(int $idAporte, string $periodo, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_unidad, c.id_cliente, c.monto, c.periodo, c.numero_cuota, c.total_cuotas, c.donde_cobro,
                    u.codigo AS unidad_codigo, u.nombre AS unidad_nombre, cl.nombre AS cliente_nombre,
                    cl.identificacion AS cliente_identificacion, cl.email AS cliente_email,
                    CASE WHEN c.donde_cobro = 'aparte' THEN NULL
                         WHEN s.id IS NULL THEN 'El inmueble no tiene una suscripción activa.'
                         ELSE 'La alícuota la paga otro cliente.' END AS motivo_aparte
               FROM condominios_cargos c
               JOIN condominios_unidades u ON u.id = c.id_unidad
               JOIN clientes cl ON cl.id = c.id_cliente
               LEFT JOIN suscripciones s ON s.id = u.id_suscripcion AND s.eliminado = false AND s.estado IN ('activo', 'pausado')
               LEFT JOIN condominios_emisiones_items it ON it.id = c.id_emision_item
              WHERE c.id_aporte = :a AND c.id_empresa = :e AND c.eliminado = false AND c.estado = 'pendiente'
                AND c.concepto = 'aporte' AND c.subtipo = 'cuota' AND c.periodo <= :per
                AND (c.id_emision_item IS NULL OR it.estado NOT IN ('pendiente', 'procesando'))
                AND (c.donde_cobro = 'aparte' OR s.id IS NULL OR s.id_cliente <> c.id_cliente)
              ORDER BY u.codigo, c.periodo, c.numero_cuota"
        );
        $st->execute([':a' => $idAporte, ':per' => $periodo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reservarCargos(array $ids, int $idItem): void
    {
        $this->db->prepare(
            "UPDATE condominios_cargos SET id_emision_item = :i, updated_at = CURRENT_TIMESTAMP
              WHERE id = ANY(:ids::int[]) AND estado = 'pendiente'"
        )->execute([':i' => $idItem, ':ids' => self::arrInt($ids)]);
    }

    public function vincularEmision(int $idEmision, int $idAporte, string $periodo): void
    {
        $this->db->prepare("UPDATE condominios_emisiones SET id_aporte = :a, periodo = :p WHERE id = :id")
            ->execute([':a' => $idAporte, ':p' => $periodo, ':id' => $idEmision]);
    }

    public function ultimoItemEmision(int $idEmision): ?int
    {
        $st = $this->db->prepare('SELECT MAX(id) FROM condominios_emisiones_items WHERE id_emision = :e');
        $st->execute([':e' => $idEmision]);
        $v = $st->fetchColumn();
        return $v ? (int) $v : null;
    }

    // ── Cobrado (desde Cuentas por Cobrar) ───────────────────────────────────

    /**
     * Importe y abonado (cobros + retenciones + NC) de cada documento. Clave "factura:ID" /
     * "recibo:ID". Mismas fuentes que el saldo de Ingresos y Cuentas por Cobrar.
     */
    public function getAbonosDocumentos(array $idsFacturas, array $idsRecibos, int $idEmpresa): array
    {
        $out = [];
        if ($idsFacturas) {
            $st = $this->db->prepare(
                "SELECT v.id, v.importe_total, (v.estado = 'anulado' OR v.eliminado) AS anulado,
                        (SELECT COALESCE(SUM(d.monto_cobrado), 0) FROM ingresos_detalle d JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
                          WHERE d.tipo_documento = 'FACTURA' AND d.id_referencia_documento = v.id
                            AND ic.estado <> 'anulado' AND ic.eliminado = false)
                        + " . \App\Helpers\AbonosVentaSql::subRetenidoFactura('v') . "
                        + " . \App\Helpers\AbonosVentaSql::subNotasFactura('notas_credito_cabecera', 'v') . "
                        - " . \App\Helpers\AbonosVentaSql::subTrasladadoFactura('v') . " AS abonado
                   FROM ventas_cabecera v
                  WHERE v.id = ANY(:ids::int[]) AND v.id_empresa = :e"
            );
            $st->execute([':ids' => self::arrInt($idsFacturas), ':e' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out['factura:' . $r['id']] = $r;
            }
        }
        if ($idsRecibos) {
            $st = $this->db->prepare(
                "SELECT r.id, r.importe_total, (r.estado = 'anulado' OR r.eliminado) AS anulado,
                        (SELECT COALESCE(SUM(d.monto_cobrado), 0) FROM ingresos_detalle d JOIN ingresos_cabecera ic ON ic.id = d.id_ingreso
                          WHERE d.tipo_documento = 'RECIBO' AND d.id_referencia_documento = r.id
                            AND ic.estado <> 'anulado' AND ic.eliminado = false) AS abonado
                   FROM recibos_venta_cabecera r
                  WHERE r.id = ANY(:ids::int[]) AND r.id_empresa = :e"
            );
            $st->execute([':ids' => self::arrInt($idsRecibos), ':e' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out['recibo:' . $r['id']] = $r;
            }
        }
        return $out;
    }

    /** Suma con IVA de las líneas de cuotas extraordinarias de cada documento (para repartir lo abonado). */
    public function getCuotasPorDocumento(array $claves, int $idEmpresa): array
    {
        $out = [];
        foreach (['factura', 'recibo'] as $tipo) {
            $ids = [];
            foreach ($claves as $k) {
                [$t, $id] = explode(':', $k);
                if ($t === $tipo) {
                    $ids[] = (int) $id;
                }
            }
            if (!$ids) {
                continue;
            }
            $st = $this->db->prepare(
                "SELECT c.id_documento, c.id, c.monto, c.periodo, c.numero_cuota,
                        ROUND(c.monto * (1 + COALESCE(ti.porcentaje_iva, 0) / 100.0), 2) AS monto_con_iva
                   FROM condominios_cargos c
                   JOIN productos p ON p.id = c.id_producto
                   LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                  WHERE c.tipo_documento = :t AND c.id_documento = ANY(:ids::int[]) AND c.id_empresa = :e
                    AND c.eliminado = false AND c.estado = 'emitido'
                  ORDER BY c.id_documento, c.periodo, c.numero_cuota NULLS FIRST, c.id"
            );
            $st->execute([':t' => $tipo, ':ids' => self::arrInt($ids), ':e' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[$tipo . ':' . $r['id_documento']][] = $r;
            }
        }
        return $out;
    }

    /** Series activas con el nombre del punto de emisión y del establecimiento (selector del modal). */
    public function getSeries(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT pe.id AS id_punto_emision, ep.codigo AS establecimiento_codigo, pe.codigo_punto AS punto_emision_codigo,
                    NULLIF(TRIM(COALESCE(pe.nombre, '')), '') AS punto_nombre,
                    NULLIF(TRIM(COALESCE(ep.nombre, '')), '') AS establecimiento_nombre
               FROM empresa_punto_emision pe
               JOIN empresa_establecimiento ep ON ep.id = pe.id_establecimiento
              WHERE ep.id_empresa = :e AND ep.estado = 'activo' AND ep.eliminado = false
                AND pe.eliminado = false AND LOWER(COALESCE(pe.estado, '')) = 'activo'
              ORDER BY ep.codigo, pe.codigo_punto"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Anticipos ────────────────────────────────────────────────────────────

    /** Formas de cobro tipo ANTICIPO activas para ingresos. */
    public function getFormasAnticipo(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre FROM empresa_formas_pago
              WHERE id_empresa = :e AND eliminado = false AND activo = true AND tipo = 'ANTICIPO'
                AND COALESCE(aplica_en, 'AMBAS') IN ('INGRESO', 'AMBAS')
              ORDER BY nombre, id"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Candado de sesión sobre el anticipo de un cliente (leer saldo → emitir abono → aplicarlo). */
    public function bloquearAnticipo(int $idEmpresa, int $idCliente): void
    {
        $this->db->prepare("SELECT pg_advisory_lock(hashtext('anticipo_cliente:' || :e::text || ':' || :c::text))")
            ->execute([':e' => $idEmpresa, ':c' => $idCliente]);
    }

    public function soltarAnticipo(int $idEmpresa, int $idCliente): void
    {
        $this->db->prepare("SELECT pg_advisory_unlock(hashtext('anticipo_cliente:' || :e::text || ':' || :c::text))")
            ->execute([':e' => $idEmpresa, ':c' => $idCliente]);
    }

    /** Candado transaccional de una cuota extraordinaria (activar planes, reservar cuotas para emitir). */
    public function bloquearAporte(int $idAporte): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('cond_cuota_extra:' || :a::text))")->execute([':a' => $idAporte]);
    }

    public function getEmpresaNombreCliente(int $idCliente, int $idEmpresa): ?array
    {
        $st = $this->db->prepare('SELECT id, nombre, email FROM clientes WHERE id = :id AND id_empresa = :e AND eliminado = false');
        $st->execute([':id' => $idCliente, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
