<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Órdenes de producción: cabecera, líneas (teórico/real), recursos y movimientos
 * (consumos y entregas). Acceso a datos puro: el stock lo mueve ProduccionOrdenService
 * con InventarioRepository (lockStock / registrarMovimiento / actualizarStock), igual
 * que Transferencias de Inventario.
 */
class ProduccionOrdenRepository extends BaseRepository
{
    public const REF_CONSUMO = 'produccion_consumo';
    public const REF_ENTREGA = 'produccion_entrega';

    public const MAPA_ORDEN = [
        'numero'               => 'o.secuencial',
        'fecha_inicio'         => 'o.fecha_inicio',
        'fecha_fin'            => 'o.fecha_fin',
        'producto'             => 'p.nombre',
        'cantidad_planificada' => 'o.cantidad_planificada',
        'cantidad_producida'   => 'o.cantidad_producida',
        'costo_total'          => 'o.costo_total',
        'estado'               => 'o.estado',
        'tipo'                 => 'o.tipo',
    ];

    public function __construct()
    {
        parent::__construct('produccion_ordenes');
    }

    public function getTipoAmbiente(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return (string) ($st->fetchColumn() ?: '1');
    }

    // ────────────────────────────────────────────────────────────────
    // LISTADO
    // ────────────────────────────────────────────────────────────────

    private function whereListado(int $idEmpresa, ?int $idUsuarioFiltro, array $filtros, string $buscar, array &$params): string
    {
        $where  = $this->getBaseWhere($idEmpresa, 'o', $idUsuarioFiltro);
        $params[':id_empresa'] = $idEmpresa;
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        // Listado operativo: sigue el ambiente actual de la empresa, como el kardex.
        $where .= " AND o.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        if (!empty($filtros['desde'])) {
            $where .= ' AND o.fecha_inicio >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where .= ' AND o.fecha_inicio <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where .= ' AND o.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $where .= ' AND o.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if (($parsed['texto_libre'] ?? '') !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['o.numero', 'p.codigo', 'p.nombre', 'o.lote', 'o.responsable', 'o.observaciones'],
                $parsed['texto_libre'], $params, 'po'
            );
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'] ?? [], [
            'texto'    => ['numero' => 'o.numero', 'producto' => 'p.nombre', 'lote' => 'o.lote', 'responsable' => 'o.responsable'],
            'exacto'   => ['estado' => 'o.estado', 'tipo' => 'o.tipo'],
            'fecha'    => ['fecha' => 'o.fecha_inicio', 'cierre' => 'o.fecha_fin'],
            'numerico' => ['cantidad' => 'o.cantidad_producida', 'costo' => 'o.costo_total'],
        ]);
        return $where;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro, array $filtros = []): array
    {
        $params = [];
        $where  = $this->whereListado($idEmpresa, $idUsuarioFiltro, $filtros, $buscar, $params);
        $from   = "FROM produccion_ordenes o LEFT JOIN productos p ON p.id = o.id_producto";

        $st = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = OrdenListado::clausula($orden, self::MAPA_ORDEN, 'o.secuencial', 'o.id DESC');
        $sql = "SELECT o.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre, um.nombre AS medida_nombre,
                       bi.nombre AS bodega_insumos_nombre, bd.nombre AS bodega_destino_nombre
                {$from}
                LEFT JOIN unidades_medida um ON um.id = p.id_medida
                LEFT JOIN bodegas bi ON bi.id = o.id_bodega_insumos
                LEFT JOIN bodegas bd ON bd.id = o.id_bodega_destino
                {$where}
                {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getResumen(int $idEmpresa, ?int $idUsuarioFiltro, array $filtros, string $buscar): array
    {
        $params = [];
        $where  = $this->whereListado($idEmpresa, $idUsuarioFiltro, $filtros, $buscar, $params);
        $st = $this->db->prepare(
            "SELECT COUNT(*) AS ordenes,
                    COALESCE(SUM(CASE WHEN o.estado IN ('liberada','en_proceso') THEN 1 ELSE 0 END), 0) AS abiertas,
                    COALESCE(SUM(CASE WHEN o.estado = 'cerrada' THEN 1 ELSE 0 END), 0) AS cerradas,
                    COALESCE(SUM(CASE WHEN o.estado = 'cerrada' THEN o.costo_total ELSE 0 END), 0) AS costo,
                    COALESCE(SUM(CASE WHEN o.estado = 'cerrada' THEN o.costo_merma ELSE 0 END), 0) AS merma
               FROM produccion_ordenes o LEFT JOIN productos p ON p.id = o.id_producto {$where}"
        );
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ────────────────────────────────────────────────────────────────
    // FICHA
    // ────────────────────────────────────────────────────────────────

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT o.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre, um.nombre AS medida_nombre,
                    f.codigo AS formula_codigo, f.nombre AS formula_nombre, f.instrucciones AS formula_instrucciones,
                    f.cantidad_base AS formula_cantidad_base, f.vida_util_dias,
                    bi.nombre AS bodega_insumos_nombre, bd.nombre AS bodega_destino_nombre,
                    u.nombre AS creado_por, uu.nombre AS modificado_por
               FROM produccion_ordenes o
               LEFT JOIN productos p ON p.id = o.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
               LEFT JOIN produccion_formulas f ON f.id = o.id_formula
               LEFT JOIN bodegas bi ON bi.id = o.id_bodega_insumos
               LEFT JOIN bodegas bd ON bd.id = o.id_bodega_destino
               LEFT JOIN usuarios u  ON u.id  = o.created_by
               LEFT JOIN usuarios uu ON uu.id = o.updated_by
              WHERE o.id = :id AND o.id_empresa = :e AND o.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Cabecera bloqueada para escribir (FOR UPDATE): dos acciones sobre la misma orden se esperan. */
    public function getParaActualizar(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM produccion_ordenes WHERE id = :id AND id_empresa = :e AND eliminado = false FOR UPDATE");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLineas(int $idOrden, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT l.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre, um.nombre AS medida_nombre,
                    p.precio_base, p.id_cuenta_inventario
               FROM produccion_ordenes_lineas l
               LEFT JOIN productos p ON p.id = l.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              WHERE l.id_orden = :o AND l.id_empresa = :e AND l.eliminado = false
              ORDER BY CASE l.rol WHEN 'insumo' THEN 0 WHEN 'principal' THEN 1 WHEN 'coproducto' THEN 2
                                  WHEN 'subproducto' THEN 3 ELSE 4 END, l.orden, l.id"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecursos(int $idOrden, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM produccion_ordenes_recursos WHERE id_orden = :o AND id_empresa = :e AND eliminado = false ORDER BY orden, id"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovimientos(int $idOrden, int $idEmpresa, bool $incluirAnulados = false): array
    {
        $st = $this->db->prepare(
            "SELECT m.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre, um.nombre AS medida_nombre,
                    b.nombre AS bodega_nombre, l.rol, u.nombre AS usuario_nombre
               FROM produccion_ordenes_movimientos m
               INNER JOIN productos p ON p.id = m.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
               LEFT JOIN bodegas b ON b.id = m.id_bodega
               LEFT JOIN produccion_ordenes_lineas l ON l.id = m.id_linea
               LEFT JOIN usuarios u ON u.id = m.created_by
              WHERE m.id_orden = :o AND m.id_empresa = :e" . ($incluirAnulados ? '' : ' AND m.eliminado = false') . "
              ORDER BY m.grupo, m.tipo, m.id"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCostosImputados(int $idOrden, int $idEmpresa): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(importe), 0) FROM produccion_ordenes_costos WHERE id_orden = :o AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return (float) $st->fetchColumn();
    }

    // ────────────────────────────────────────────────────────────────
    // ESCRITURA
    // ────────────────────────────────────────────────────────────────

    /**
     * Siguiente número de la empresa en el ambiente. El candado se libera al COMMIT/ROLLBACK:
     * el llamador DEBE tener abierta la transacción en la que inserta la cabecera (§8).
     */
    public function siguienteSecuencial(int $idEmpresa, string $ambiente): int
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('produccion_orden:' || :e || ':' || :amb))")
                 ->execute([':e' => $idEmpresa, ':amb' => $ambiente]);
        // Incluye eliminadas y anuladas: un número no se reutiliza nunca.
        $st = $this->db->prepare("SELECT COALESCE(MAX(secuencial), 0) + 1 FROM produccion_ordenes WHERE id_empresa = :e AND tipo_ambiente = :amb");
        $st->execute([':e' => $idEmpresa, ':amb' => $ambiente]);
        return (int) $st->fetchColumn();
    }

    public function insertarCabecera(array $d, int $idEmpresa, int $idUsuario): int
    {
        $cols = array_keys($d);
        $sql = 'INSERT INTO produccion_ordenes (id_empresa, ' . implode(', ', $cols) . ', created_by, updated_by)
                VALUES (:id_empresa, :' . implode(', :', $cols) . ', :uid, :uid) RETURNING id';
        $params = [':id_empresa' => $idEmpresa, ':uid' => $idUsuario];
        foreach ($d as $k => $v) {
            $params[':' . $k] = is_bool($v) ? ($v ? 'true' : 'false') : $v;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function actualizarCabecera(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $sets = [];
        $params = [':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario];
        foreach ($d as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[':' . $k] = is_bool($v) ? ($v ? 'true' : 'false') : $v;
        }
        $this->db->prepare(
            'UPDATE produccion_ordenes SET ' . implode(', ', $sets) . ', updated_by = :uid, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        )->execute($params);
    }

    public function insertarLinea(array $l, int $idOrden, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO produccion_ordenes_lineas
                (id_empresa, id_orden, rol, id_producto, descripcion, cantidad_teorica, cantidad_real, merma_pct,
                 exige_lote, reparto_pct, valor_neto_realizable, fuera_formula, orden, created_by, updated_by)
             VALUES (:e, :o, :rol, :p, :descripcion, :teorica, 0, :merma, :exige, :reparto, :vnr, :fuera, :orden, :uid, :uid)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':o' => $idOrden, ':uid' => $idUsuario,
            ':rol' => $l['rol'], ':p' => $l['id_producto'] ?: null,
            ':descripcion' => $this->caparTexto('descripcion', $l['descripcion'] ?? null, 'produccion_ordenes_lineas'),
            ':teorica' => $l['cantidad_teorica'], ':merma' => $l['merma_pct'] ?? 0,
            ':exige' => !empty($l['exige_lote']) ? 'true' : 'false',
            ':reparto' => $l['reparto_pct'] ?? null, ':vnr' => $l['valor_neto_realizable'] ?? null,
            ':fuera' => !empty($l['fuera_formula']) ? 'true' : 'false', ':orden' => (int) ($l['orden'] ?? 0),
        ]);
        return (int) $st->fetchColumn();
    }

    /** Los recursos se reemplazan al cerrar (MO/CIF ajustados en el cierre). */
    public function borrarRecursos(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_ordenes_recursos SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
              WHERE id_orden = :o AND id_empresa = :e AND eliminado = false"
        )->execute([':o' => $idOrden, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    public function sumarRealLinea(int $idLinea, float $cantidad, float $costo, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_ordenes_lineas
                SET cantidad_real = cantidad_real + :c, costo_real = costo_real + :k,
                    updated_by = :uid, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e"
        )->execute([':c' => $cantidad, ':k' => $costo, ':id' => $idLinea, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    public function fijarRealLinea(int $idLinea, float $cantidad, float $costo, float $unitario, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_ordenes_lineas
                SET cantidad_real = :c, costo_real = :k, costo_unitario = :u,
                    updated_by = :uid, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e"
        )->execute([':c' => $cantidad, ':k' => $costo, ':u' => $unitario, ':id' => $idLinea, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    public function insertarRecurso(array $r, int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "INSERT INTO produccion_ordenes_recursos
                (id_empresa, id_orden, tipo, descripcion, base, cantidad, tarifa, importe, orden, created_by, updated_by)
             VALUES (:e, :o, :tipo, :descripcion, :base, :cantidad, :tarifa, :importe, :orden, :uid, :uid)"
        )->execute([
            ':e' => $idEmpresa, ':o' => $idOrden, ':uid' => $idUsuario,
            ':tipo' => $r['tipo'], ':descripcion' => $this->caparTexto('descripcion', $r['descripcion'] ?? null, 'produccion_ordenes_recursos'),
            ':base' => $r['base'], ':cantidad' => $r['cantidad'], ':tarifa' => $r['tarifa'],
            ':importe' => $r['importe'] ?? 0, ':orden' => (int) ($r['orden'] ?? 0),
        ]);
    }

    public function siguienteGrupo(int $idOrden, int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(grupo), 0) + 1 FROM produccion_ordenes_movimientos WHERE id_orden = :o AND id_empresa = :e");
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function insertarMovimiento(array $m, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO produccion_ordenes_movimientos
                (id_empresa, id_orden, id_linea, grupo, tipo, id_producto, id_bodega, cantidad, numero_lote, fecha_caducidad,
                 peso_inicial, peso_final, costo_unitario, costo_total, id_kardex, fecha, observaciones, created_by, updated_by)
             VALUES (:e, :o, :l, :g, :tipo, :p, :b, :cant, :lote, :cad, :pi, :pf, :cu, :ct, :k, :fecha, :obs, :uid, :uid)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':uid' => $idUsuario,
            ':o' => $m['id_orden'], ':l' => $m['id_linea'], ':g' => $m['grupo'], ':tipo' => $m['tipo'],
            ':p' => $m['id_producto'], ':b' => $m['id_bodega'], ':cant' => $m['cantidad'],
            ':lote' => $m['numero_lote'] ?: null, ':cad' => $m['fecha_caducidad'] ?: null,
            ':pi' => $m['peso_inicial'] ?? null, ':pf' => $m['peso_final'] ?? null,
            ':cu' => $m['costo_unitario'], ':ct' => $m['costo_total'], ':k' => $m['id_kardex'],
            ':fecha' => $m['fecha'], ':obs' => $this->caparTexto('observaciones', $m['observaciones'] ?? null, 'produccion_ordenes_movimientos'),
        ]);
        return (int) $st->fetchColumn();
    }

    public function fijarAsientoGrupo(int $idOrden, int $grupo, string $tipo, ?int $idAsiento, int $idEmpresa): void
    {
        $this->db->prepare(
            "UPDATE produccion_ordenes_movimientos SET id_asiento = :a
              WHERE id_orden = :o AND grupo = :g AND tipo = :t AND id_empresa = :e"
        )->execute([':a' => $idAsiento, ':o' => $idOrden, ':g' => $grupo, ':t' => $tipo, ':e' => $idEmpresa]);
    }

    public function anularMovimientos(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_ordenes_movimientos SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
              WHERE id_orden = :o AND id_empresa = :e AND eliminado = false"
        )->execute([':o' => $idOrden, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_ordenes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    // ────────────────────────────────────────────────────────────────
    // APOYO
    // ────────────────────────────────────────────────────────────────

    /** Establecimiento de una bodega (da el método de costeo). */
    public function getEstablecimientoBodega(int $idBodega, int $idEmpresa): ?int
    {
        $st = $this->db->prepare("SELECT id_establecimiento FROM bodegas WHERE id = :b AND id_empresa = :e AND eliminado = false");
        $st->execute([':b' => $idBodega, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v ? (int) $v : null;
    }

    public function getNombreBodega(int $idBodega): string
    {
        $st = $this->db->prepare("SELECT nombre FROM bodegas WHERE id = :b");
        $st->execute([':b' => $idBodega]);
        return (string) ($st->fetchColumn() ?: '');
    }

    /** Stock de varios productos en una bodega (vista previa de faltantes). */
    public function getStocks(array $ids, int $idBodega, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $marcas = [];
        $params = [':e' => $idEmpresa, ':b' => $idBodega];
        foreach ($ids as $i => $id) {
            $marcas[] = ":p{$i}";
            $params[":p{$i}"] = $id;
        }
        $st = $this->db->prepare(
            'SELECT id_producto, ROUND(COALESCE(SUM(cantidad), 0), 6) AS stock
               FROM inventario_kardex
              WHERE id_empresa = :e AND id_bodega = :b AND eliminado = false
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                AND id_producto IN (' . implode(', ', $marcas) . ')
              GROUP BY id_producto'
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_producto']] = (float) $r['stock'];
        }
        return $out;
    }

    /**
     * Trazabilidad de la orden: qué lotes de insumo se consumieron y qué lotes salieron.
     */
    public function getTrazabilidad(int $idOrden, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT m.tipo, p.codigo, p.nombre, COALESCE(NULLIF(m.numero_lote, ''), 'Sin lote') AS lote,
                    MAX(m.fecha_caducidad) AS caducidad, SUM(m.cantidad) AS cantidad
               FROM produccion_ordenes_movimientos m
              INNER JOIN productos p ON p.id = m.id_producto
              WHERE m.id_orden = :o AND m.id_empresa = :e AND m.eliminado = false
              GROUP BY m.tipo, p.codigo, p.nombre, COALESCE(NULLIF(m.numero_lote, ''), 'Sin lote')
              ORDER BY m.tipo DESC, p.nombre"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
