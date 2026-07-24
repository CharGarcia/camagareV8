<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo Menú (carta del restaurante). Cada ítem puede
 * vincularse a un producto del sistema (id_producto, incluye productos
 * compuestos/combos ya soportados por InventarioService) o ser independiente.
 * La categoría (id_categoria) es una tabla PROPIA del menú (menu_categorias),
 * separada de `categorias` de Productos — ambas apuntan a la misma estación
 * de impresión (estaciones_impresion) para enrutar a cocina/barra.
 */
class MenuRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('menu_items');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'm', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (m.nombre ILIKE :b OR c.nombre ILIKE :b OR p.nombre ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['nombre' => 'm.nombre', 'categoria' => 'c.nombre', 'producto' => 'p.nombre'],
            'exacto'   => ['disponible' => 'm.disponible', 'destacado' => 'm.destacado'],
            'numerico' => ['precio' => 'm.precio'],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM menu_items m
                     LEFT JOIN productos p ON p.id = m.id_producto
                     LEFT JOIN menu_categorias c ON c.id = m.id_categoria
                     {$where}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $colMap = [
            'nombre'    => 'm.nombre',
            'categoria' => 'c.nombre',
            'precio'    => 'm.precio',
            'orden'     => 'm.orden',
        ];
        $col = $colMap[$ordenCol] ?? 'm.orden';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT {$perPage} OFFSET {$offset}";
        }

        $sql = "SELECT m.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       c.nombre AS categoria_nombre, ti.porcentaje_iva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN menu_categorias c ON c.id = m.id_categoria
                LEFT JOIN tarifa_iva ti ON ti.id = m.id_tarifa_iva
                {$where}
                ORDER BY {$col} {$dir}, m.id DESC
                {$limitClause}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT m.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       c.nombre AS categoria_nombre, ti.porcentaje_iva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN menu_categorias c ON c.id = m.id_categoria
                LEFT JOIN tarifa_iva ti ON ti.id = m.id_tarifa_iva
                WHERE m.id = :id AND m.id_empresa = :e AND m.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO menu_items (
                    id_empresa, id_producto, nombre, descripcion, precio, imagen,
                    id_categoria, id_tarifa_iva, disponible, destacado, orden,
                    created_by, updated_by
                ) VALUES (
                    :e, :prod, :nombre, :desc, :precio, :img,
                    :cat, :iva, :disp, :destacado, :orden,
                    :cb, :cb
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'         => $d['id_empresa'],
            ':prod'      => $d['id_producto'] ?: null,
            ':nombre'    => $d['nombre'],
            ':desc'      => $d['descripcion'] ?: null,
            ':precio'    => $d['precio'],
            ':img'       => $d['imagen'] ?: null,
            ':cat'       => $d['id_categoria'] ?: null,
            ':iva'       => $d['id_tarifa_iva'] ?: null,
            ':disp'      => !empty($d['disponible']) ? 'true' : 'false',
            ':destacado' => !empty($d['destacado']) ? 'true' : 'false',
            ':orden'     => $d['orden'] ?? 0,
            ':cb'        => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function update(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE menu_items SET
                    id_producto = :prod, nombre = :nombre, descripcion = :desc, precio = :precio,
                    imagen = :img, id_categoria = :cat, id_tarifa_iva = :iva,
                    disponible = :disp, destacado = :destacado, orden = :orden,
                    updated_by = :ub, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':prod'      => $d['id_producto'] ?: null,
            ':nombre'    => $d['nombre'],
            ':desc'      => $d['descripcion'] ?: null,
            ':precio'    => $d['precio'],
            ':img'       => $d['imagen'] ?: null,
            ':cat'       => $d['id_categoria'] ?: null,
            ':iva'       => $d['id_tarifa_iva'] ?: null,
            ':disp'      => !empty($d['disponible']) ? 'true' : 'false',
            ':destacado' => !empty($d['destacado']) ? 'true' : 'false',
            ':orden'     => $d['orden'] ?? 0,
            ':ub'        => $d['updated_by'],
            ':id'        => $id,
            ':e'         => $idEmpresa,
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE menu_items SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Ítems disponibles para mostrar/buscar (usado por el selector de
     * modulos/comandas/ver y por el portal público del menú). id_estacion_impresion
     * viene de la categoría vinculada, o si no de la categoría del producto;
     * porcentaje_iva viene del producto si hay uno vinculado, si no de la
     * tarifa propia del ítem.
     */
    public function getDisponibles(int $idEmpresa, string $buscar = ''): array
    {
        $where = "WHERE m.id_empresa = :e AND m.eliminado = false AND m.disponible = true";
        $params = [':e' => $idEmpresa];
        if ($buscar !== '') {
            $where .= " AND (m.nombre ILIKE :b OR m.descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql = "SELECT m.id, m.id_producto, m.nombre, m.descripcion, m.precio, m.imagen,
                       m.destacado, m.orden,
                       COALESCE(c.id_estacion_impresion, cp.id_estacion_impresion) AS id_estacion_impresion,
                       p.codigo AS producto_codigo, p.codigo_barras, p.codigo_auxiliar, p.inventariable, p.tipo_produccion,
                       COALESCE(p.tarifa_iva, m.id_tarifa_iva) AS id_tarifa_iva,
                       COALESCE(tp.porcentaje_iva, ti.porcentaje_iva, 0) AS porcentaje_iva,
                       COALESCE(tp.codigo, ti.codigo, '0') AS codigo_iva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN menu_categorias c ON c.id = m.id_categoria
                LEFT JOIN categorias cp ON cp.id = p.id_categoria
                LEFT JOIN tarifa_iva tp ON tp.id = p.tarifa_iva
                LEFT JOIN tarifa_iva ti ON ti.id = m.id_tarifa_iva
                {$where}
                ORDER BY m.orden ASC, m.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mismo shape que getDisponibles() pero para un solo ítem — usado al agregarlo a una comanda. */
    public function getDisponibleById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT m.id, m.id_producto, m.nombre, m.descripcion, m.precio, m.imagen,
                       COALESCE(c.id_estacion_impresion, cp.id_estacion_impresion) AS id_estacion_impresion,
                       COALESCE(p.tarifa_iva, m.id_tarifa_iva) AS id_tarifa_iva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN menu_categorias c ON c.id = m.id_categoria
                LEFT JOIN categorias cp ON cp.id = p.id_categoria
                WHERE m.id = :id AND m.id_empresa = :e AND m.eliminado = false AND m.disponible = true";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeParaProducto(int $idProducto, int $idEmpresa, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM menu_items WHERE id_producto = :p AND id_empresa = :e AND eliminado = false";
        $params = [':p' => $idProducto, ':e' => $idEmpresa];
        if ($excluirId !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    // ─── Categorías del menú (tabla propia, separada de `categorias` de Productos) ─

    public function getMenuCategorias(int $idEmpresa): array
    {
        $sql = "SELECT mc.id, mc.nombre, mc.orden, mc.id_estacion_impresion, ei.nombre AS estacion_nombre
                FROM menu_categorias mc
                LEFT JOIN estaciones_impresion ei ON ei.id = mc.id_estacion_impresion
                WHERE mc.id_empresa = :e AND mc.eliminado = false
                ORDER BY mc.orden ASC, mc.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeMenuCategoriaNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM menu_categorias WHERE id_empresa = :e AND UPPER(nombre) = UPPER(:n) AND eliminado = false";
        $params = [':e' => $idEmpresa, ':n' => $nombre];
        if ($excluirId !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function crearMenuCategoria(array $d): int
    {
        $sql = "INSERT INTO menu_categorias (id_empresa, nombre, id_estacion_impresion, orden, created_by, updated_by)
                VALUES (:e, :nombre, :est, :orden, :cb, :cb) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $d['id_empresa'],
            ':nombre'=> $d['nombre'],
            ':est'   => $d['id_estacion_impresion'] ?: null,
            ':orden' => $d['orden'] ?? 0,
            ':cb'    => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarMenuCategoria(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE menu_categorias SET nombre = :nombre, id_estacion_impresion = :est, orden = :orden,
                    updated_by = :ub, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':nombre' => $d['nombre'],
            ':est'    => $d['id_estacion_impresion'] ?: null,
            ':orden'  => $d['orden'] ?? 0,
            ':ub'     => $d['updated_by'],
            ':id'     => $id,
            ':e'      => $idEmpresa,
        ]);
    }

    public function eliminarMenuCategoria(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE menu_categorias SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function contarMenuItemsEnCategoria(int $idCategoria, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM menu_items WHERE id_categoria = :c AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':c' => $idCategoria, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    // ─── Estaciones de impresión (catálogo compartido: Productos + Menú + KDS) ────

    public function getEstaciones(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo, orden, activo
                FROM estaciones_impresion
                WHERE id_empresa = :e AND eliminado = false
                ORDER BY orden ASC, nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeEstacionNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM estaciones_impresion WHERE id_empresa = :e AND UPPER(nombre) = UPPER(:n) AND eliminado = false";
        $params = [':e' => $idEmpresa, ':n' => $nombre];
        if ($excluirId !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function crearEstacion(array $d): int
    {
        $sql = "INSERT INTO estaciones_impresion (id_empresa, nombre, tipo, orden, created_by, updated_by)
                VALUES (:e, :nombre, :tipo, :orden, :cb, :cb) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'      => $d['id_empresa'],
            ':nombre' => $d['nombre'],
            ':tipo'   => $d['tipo'] ?? 'cocina',
            ':orden'  => $d['orden'] ?? 0,
            ':cb'     => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarEstacion(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE estaciones_impresion SET nombre = :nombre, tipo = :tipo, orden = :orden,
                    updated_by = :ub, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':nombre' => $d['nombre'],
            ':tipo'   => $d['tipo'] ?? 'cocina',
            ':orden'  => $d['orden'] ?? 0,
            ':ub'     => $d['updated_by'],
            ':id'     => $id,
            ':e'      => $idEmpresa,
        ]);
    }

    public function eliminarEstacion(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE estaciones_impresion SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function contarUsosEstacion(int $idEstacion, int $idEmpresa): int
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM menu_categorias WHERE id_estacion_impresion = :est AND id_empresa = :e AND eliminado = false) +
                    (SELECT COUNT(*) FROM categorias WHERE id_estacion_impresion = :est AND id_empresa = :e AND eliminado = false)";
        $st = $this->db->prepare($sql);
        $st->execute([':est' => $idEstacion, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }
}
