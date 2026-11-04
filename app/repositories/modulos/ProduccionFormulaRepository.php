<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Fórmulas de producción (lista de materiales / receta / plantilla): cabecera,
 * entradas y salidas (produccion_formulas_lineas) y recursos (MO, máquina, CIF).
 * Acceso a datos puro.
 *
 * No toca productos_componentes: esos componentes se descuentan al VENDER y siguen
 * igual; aquí solo se LEEN para avisar del doble descuento.
 */
class ProduccionFormulaRepository extends BaseRepository
{
    /** Whitelist y mapa del ORDER BY (OrdenListado). */
    public const MAPA_ORDEN = [
        'codigo'        => 'f.codigo',
        'nombre'        => 'f.nombre',
        'producto'      => 'p.nombre',
        'version'       => 'f.version',
        'cantidad_base' => 'f.cantidad_base',
        'estado'        => 'f.estado',
        'updated_at'    => 'f.updated_at',
    ];

    public function __construct()
    {
        parent::__construct('produccion_formulas');
    }

    // ────────────────────────────────────────────────────────────────
    // LISTADO
    // ────────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro, string $estado = ''): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'f', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        if ($estado !== '') {
            $where .= ' AND f.estado = :estado';
            $params[':estado'] = $estado;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if (($parsed['texto_libre'] ?? '') !== '') {
            $cond = FiltrosBusqueda::condicionTexto(['f.codigo', 'f.nombre', 'p.codigo', 'p.nombre'], $parsed['texto_libre'], $params, 'pf');
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'] ?? [], [
            'texto'    => ['codigo' => 'f.codigo', 'nombre' => 'f.nombre', 'producto' => 'p.nombre'],
            'exacto'   => ['estado' => 'f.estado'],
            'fecha'    => ['modificado' => 'f.updated_at'],
            'numerico' => ['version' => 'f.version'],
        ]);

        $from = "FROM produccion_formulas f INNER JOIN productos p ON p.id = f.id_producto";

        $st = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = OrdenListado::clausula($orden, self::MAPA_ORDEN, 'f.nombre', 'f.id DESC');
        $sql = "SELECT f.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       um.nombre AS medida_nombre,
                       (SELECT COUNT(*) FROM produccion_formulas_lineas l
                         WHERE l.id_formula = f.id AND l.eliminado = false AND l.rol = 'insumo') AS insumos,
                       (SELECT COUNT(*) FROM produccion_formulas_lineas l
                         WHERE l.id_formula = f.id AND l.eliminado = false AND l.rol <> 'insumo') AS salidas
                {$from}
                LEFT JOIN unidades_medida um ON um.id = p.id_medida
                {$where}
                {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    // ────────────────────────────────────────────────────────────────
    // FICHA
    // ────────────────────────────────────────────────────────────────

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT f.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre, um.nombre AS medida_nombre,
                    u.nombre AS creado_por, uu.nombre AS modificado_por
               FROM produccion_formulas f
              INNER JOIN productos p ON p.id = f.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
               LEFT JOIN usuarios u  ON u.id  = f.created_by
               LEFT JOIN usuarios uu ON uu.id = f.updated_by
              WHERE f.id = :id AND f.id_empresa = :e AND f.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLineas(int $idFormula, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT l.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                    um.nombre AS medida_nombre, p.costo_producto, p.precio_base
               FROM produccion_formulas_lineas l
               LEFT JOIN productos p ON p.id = l.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              WHERE l.id_formula = :f AND l.id_empresa = :e AND l.eliminado = false
              ORDER BY CASE l.rol WHEN 'principal' THEN 0 WHEN 'coproducto' THEN 1 WHEN 'subproducto' THEN 2
                                  WHEN 'desperdicio' THEN 3 ELSE 4 END, l.orden, l.id"
        );
        $st->execute([':f' => $idFormula, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecursos(int $idFormula, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM produccion_formulas_recursos
              WHERE id_formula = :f AND id_empresa = :e AND eliminado = false
              ORDER BY orden, id"
        );
        $st->execute([':f' => $idFormula, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Fórmula vigente de un producto principal (para crear órdenes). */
    public function getVigentePorProducto(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id FROM produccion_formulas
              WHERE id_empresa = :e AND id_producto = :p AND estado = 'vigente' AND eliminado = false LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto]);
        $id = $st->fetchColumn();
        return $id ? $this->getPorId((int) $id, $idEmpresa) : null;
    }

    /** Fórmulas vigentes (selector de la orden de producción). */
    public function getVigentes(int $idEmpresa, string $q = ''): array
    {
        $params = [':e' => $idEmpresa];
        $where = "WHERE f.id_empresa = :e AND f.eliminado = false AND f.estado = 'vigente'";
        $cond = FiltrosBusqueda::condicionTexto(['f.codigo', 'f.nombre', 'p.codigo', 'p.nombre'], $q, $params, 'pv');
        if ($cond !== '') {
            $where .= " AND {$cond}";
        }
        $st = $this->db->prepare(
            "SELECT f.id, f.codigo, f.nombre, f.version, f.cantidad_base, f.id_producto,
                    p.codigo AS producto_codigo, p.nombre AS producto_nombre, um.nombre AS medida_nombre
               FROM produccion_formulas f
              INNER JOIN productos p ON p.id = f.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              {$where}
              ORDER BY f.nombre LIMIT 30"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────
    // ESCRITURA
    // ────────────────────────────────────────────────────────────────

    public function insertarCabecera(array $d, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO produccion_formulas
                (id_empresa, codigo, nombre, version, id_producto, cantidad_base, vida_util_dias,
                 metodo_reparto, instrucciones, observaciones, estado, created_by, updated_by)
             VALUES (:e, :codigo, :nombre, :version, :id_producto, :cantidad_base, :vida_util_dias,
                     :metodo_reparto, :instrucciones, :observaciones, :estado, :uid, :uid)
             RETURNING id"
        );
        $st->execute($this->paramsCabecera($d) + [':e' => $idEmpresa, ':uid' => $idUsuario,
            ':version' => (int) $d['version'], ':estado' => (string) $d['estado']]);
        return (int) $st->fetchColumn();
    }

    public function actualizarCabecera(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE produccion_formulas
                SET codigo = :codigo, nombre = :nombre, id_producto = :id_producto,
                    cantidad_base = :cantidad_base, vida_util_dias = :vida_util_dias,
                    metodo_reparto = :metodo_reparto, instrucciones = :instrucciones,
                    observaciones = :observaciones, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute($this->paramsCabecera($d) + [':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    private function paramsCabecera(array $d): array
    {
        return [
            ':codigo'         => $this->caparTexto('codigo', $d['codigo'], 'produccion_formulas'),
            ':nombre'         => $this->caparTexto('nombre', $d['nombre'], 'produccion_formulas'),
            ':id_producto'    => (int) $d['id_producto'],
            ':cantidad_base'  => $d['cantidad_base'],
            ':vida_util_dias' => $d['vida_util_dias'],
            ':metodo_reparto' => $d['metodo_reparto'],
            ':instrucciones'  => $d['instrucciones'],
            ':observaciones'  => $d['observaciones'],
        ];
    }

    /** Reemplaza las líneas y recursos (los anteriores quedan con eliminación lógica). */
    public function reemplazarDetalle(int $idFormula, array $lineas, array $recursos, int $idEmpresa, int $idUsuario): void
    {
        foreach (['produccion_formulas_lineas', 'produccion_formulas_recursos'] as $tabla) {
            $this->db->prepare(
                "UPDATE {$tabla} SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
                  WHERE id_formula = :f AND id_empresa = :e AND eliminado = false"
            )->execute([':f' => $idFormula, ':e' => $idEmpresa, ':uid' => $idUsuario]);
        }

        $stL = $this->db->prepare(
            "INSERT INTO produccion_formulas_lineas
                (id_empresa, id_formula, rol, id_producto, descripcion, modo_cantidad, cantidad, merma_pct,
                 exige_lote, reparto_pct, valor_neto_realizable, orden, observaciones, created_by, updated_by)
             VALUES (:e, :f, :rol, :id_producto, :descripcion, :modo, :cantidad, :merma,
                     :exige_lote, :reparto, :vnr, :orden, :obs, :uid, :uid)"
        );
        foreach ($lineas as $i => $l) {
            $stL->execute([
                ':e' => $idEmpresa, ':f' => $idFormula, ':uid' => $idUsuario,
                ':rol'         => $l['rol'],
                ':id_producto' => $l['id_producto'],
                ':descripcion' => $this->caparTexto('descripcion', $l['descripcion'], 'produccion_formulas_lineas'),
                ':modo'        => $l['modo_cantidad'],
                ':cantidad'    => $l['cantidad'],
                ':merma'       => $l['merma_pct'],
                ':exige_lote'  => $l['exige_lote'] ? 'true' : 'false',
                ':reparto'     => $l['reparto_pct'],
                ':vnr'         => $l['valor_neto_realizable'],
                ':orden'       => $i + 1,
                ':obs'         => $this->caparTexto('observaciones', $l['observaciones'], 'produccion_formulas_lineas'),
            ]);
        }

        $stR = $this->db->prepare(
            "INSERT INTO produccion_formulas_recursos
                (id_empresa, id_formula, tipo, descripcion, base, cantidad, tarifa, orden, created_by, updated_by)
             VALUES (:e, :f, :tipo, :descripcion, :base, :cantidad, :tarifa, :orden, :uid, :uid)"
        );
        foreach ($recursos as $i => $r) {
            $stR->execute([
                ':e' => $idEmpresa, ':f' => $idFormula, ':uid' => $idUsuario,
                ':tipo'        => $r['tipo'],
                ':descripcion' => $this->caparTexto('descripcion', $r['descripcion'], 'produccion_formulas_recursos'),
                ':base'        => $r['base'],
                ':cantidad'    => $r['cantidad'],
                ':tarifa'      => $r['tarifa'],
                ':orden'       => $i + 1,
            ]);
        }
    }

    public function cambiarEstado(int $id, string $estado, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_formulas SET estado = :estado, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':estado' => $estado, ':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    /** Deja inactivas las demás versiones vigentes del mismo producto. */
    public function inactivarOtrasVigentes(int $idProducto, int $exceptoId, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_formulas SET estado = 'inactiva', updated_by = :uid, updated_at = CURRENT_TIMESTAMP
              WHERE id_empresa = :e AND id_producto = :p AND id <> :id AND estado = 'vigente' AND eliminado = false"
        )->execute([':e' => $idEmpresa, ':p' => $idProducto, ':id' => $exceptoId, ':uid' => $idUsuario]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE produccion_formulas SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);
    }

    public function siguienteVersion(int $idProducto, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(version), 0) + 1 FROM produccion_formulas WHERE id_empresa = :e AND id_producto = :p"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto]);
        return (int) $st->fetchColumn();
    }

    public function existeCodigo(string $codigo, int $idEmpresa, int $exceptoId = 0, int $version = 0): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM produccion_formulas
              WHERE id_empresa = :e AND UPPER(codigo) = UPPER(:c) AND version = :v AND id <> :id AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $codigo, ':v' => $version, ':id' => $exceptoId]);
        return (bool) $st->fetchColumn();
    }

    /** ¿La fórmula ya se usó en alguna orden? (entonces no se edita: se crea otra versión) */
    public function tieneOrdenes(int $idFormula, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM produccion_ordenes WHERE id_formula = :f AND id_empresa = :e AND eliminado = false LIMIT 1"
        );
        $st->execute([':f' => $idFormula, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    // ────────────────────────────────────────────────────────────────
    // PRODUCTOS (solo lectura)
    // ────────────────────────────────────────────────────────────────

    /** Productos inventariables de tipo bien, para las líneas de la fórmula. */
    public function buscarProductos(int $idEmpresa, string $q): array
    {
        $params = [':e' => $idEmpresa];
        $where  = "WHERE p.id_empresa = :e AND p.eliminado = false AND COALESCE(p.inventariable, false) = true
                     AND COALESCE(p.tipo_produccion, '01') <> '02'";
        $cond = FiltrosBusqueda::condicionTexto(['p.codigo', 'p.nombre', 'p.codigo_barras'], $q, $params, 'pp');
        if ($cond !== '') {
            $where .= " AND {$cond}";
        }
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo, p.nombre, p.costo_producto, p.precio_base, p.id_medida, um.nombre AS medida_nombre
               FROM productos p
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              {$where}
              ORDER BY p.nombre LIMIT 30"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Datos de varios productos (validación y costeo).
     *
     * @param int[] $ids
     * @return array<int, array>
     */
    public function getProductos(array $ids, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $marcas = [];
        $params = [':e' => $idEmpresa];
        foreach ($ids as $i => $id) {
            $marcas[] = ":p{$i}";
            $params[":p{$i}"] = $id;
        }
        $st = $this->db->prepare(
            'SELECT p.id, p.codigo, p.nombre, p.inventariable, p.tipo_produccion, p.costo_producto,
                    p.precio_base, p.id_medida, p.id_cuenta_inventario, um.nombre AS medida_nombre
               FROM productos p
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              WHERE p.id_empresa = :e AND p.eliminado = false AND p.id IN (' . implode(', ', $marcas) . ')'
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /** ¿El producto tiene componentes de venta? (se descuentan al vender: doble descuento) */
    public function tieneComponentesVenta(int $idProducto, int $idEmpresa): bool
    {
        if (!$this->tablaExiste('productos_componentes')) {
            return false;
        }
        $st = $this->db->prepare(
            "SELECT 1 FROM productos_componentes
              WHERE id_producto_padre = :p AND id_empresa = :e AND eliminado = false LIMIT 1"
        );
        $st->execute([':p' => $idProducto, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Mapa insumo → productos principales de las fórmulas vigentes (para detectar ciclos).
     *
     * @return array<int, int[]> id_producto principal => ids de insumos
     */
    public function getGrafoVigentes(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT f.id_producto AS principal, l.id_producto AS insumo
               FROM produccion_formulas f
              INNER JOIN produccion_formulas_lineas l ON l.id_formula = f.id AND l.eliminado = false AND l.rol = 'insumo'
              WHERE f.id_empresa = :e AND f.eliminado = false AND f.estado = 'vigente'"
        );
        $st->execute([':e' => $idEmpresa]);
        $g = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $g[(int) $r['principal']][] = (int) $r['insumo'];
        }
        return $g;
    }
}
