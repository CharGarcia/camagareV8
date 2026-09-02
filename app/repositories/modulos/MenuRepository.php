<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo Menú (carta del restaurante). Cada ítem puede
 * vincularse a un producto del sistema (id_producto, incluye productos
 * compuestos/combos ya soportados por InventarioService) o ser independiente.
 * La categoría (id_categoria) son las MISMAS de Productos (`categorias`): el
 * menú no lleva su propia lista. La estación de impresión que enruta a
 * cocina/barra se configura en el propio ítem (id_estacion_impresion) y, si no
 * la tiene, cae a la de su categoría o a la de la categoría del producto.
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
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['m.nombre', 'c.nombre', 'p.nombre'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['nombre' => 'm.nombre', 'categoria' => 'c.nombre', 'producto' => 'p.nombre'],
            'exacto'   => ['disponible' => 'm.disponible', 'destacado' => 'm.destacado'],
            'numerico' => ['precio' => 'm.precio'],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM menu_items m
                     LEFT JOIN productos p ON p.id = m.id_producto
                     LEFT JOIN categorias c ON c.id = m.id_categoria AND c.id_empresa = m.id_empresa
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
                       c.nombre AS categoria_nombre, ti.porcentaje_iva,
                       e.nombre AS estacion_nombre,
                       COALESCE(p.imagen, m.imagen) AS imagen_efectiva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN categorias c ON c.id = m.id_categoria AND c.id_empresa = m.id_empresa
                LEFT JOIN estaciones_impresion e ON e.id = m.id_estacion_impresion
                LEFT JOIN tarifa_iva ti ON ti.id = m.id_tarifa_iva
                {$where}
                ORDER BY {$col} {$dir}, m.id DESC
                {$limitClause}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = array_map([$this, 'aplicarImagenEfectiva'], $st->fetchAll(PDO::FETCH_ASSOC));

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * La foto del ítem es la del producto vinculado: son la misma cosa, y al
     * cambiarla desde la carta se actualiza también en Productos (ver
     * MenuService::armarDatos). Solo un ítem sin producto lleva foto propia.
     *
     * Se resuelve aquí y no en el SELECT para no tener dos columnas 'imagen'
     * (una de m.* y otra del COALESCE) y depender de cuál gana al leerlas.
     */
    private function aplicarImagenEfectiva(array $row): array
    {
        if (array_key_exists('imagen_efectiva', $row)) {
            $row['imagen'] = $row['imagen_efectiva'];
            unset($row['imagen_efectiva']);
        }
        return $row;
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT m.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       c.nombre AS categoria_nombre, ti.porcentaje_iva,
                       e.nombre AS estacion_nombre,
                       COALESCE(p.imagen, m.imagen) AS imagen_efectiva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN categorias c ON c.id = m.id_categoria AND c.id_empresa = m.id_empresa
                LEFT JOIN estaciones_impresion e ON e.id = m.id_estacion_impresion
                LEFT JOIN tarifa_iva ti ON ti.id = m.id_tarifa_iva
                WHERE m.id = :id AND m.id_empresa = :e AND m.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->aplicarImagenEfectiva($row) : null;
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO menu_items (
                    id_empresa, id_producto, nombre, descripcion, precio, imagen,
                    id_categoria, id_tarifa_iva, id_estacion_impresion, disponible, destacado, orden,
                    created_by, updated_by
                ) VALUES (
                    :e, :prod, :nombre, :desc, :precio, :img,
                    :cat, :iva, :est, :disp, :destacado, :orden,
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
            ':est'       => $d['id_estacion_impresion'] ?: null,
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
                    id_estacion_impresion = :est,
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
            ':est'       => $d['id_estacion_impresion'] ?: null,
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
     * se configura en el propio ítem y, si no tiene, cae a la de su categoría o a
     * la de la categoría del producto vinculado;
     * porcentaje_iva viene de la tarifa propia del ítem y, solo si no tiene una,
     * de la del producto vinculado. La carta manda sobre el producto: el ítem se
     * vende con el IVA con el que se lo creó, y ese mismo es el que se muestra
     * en la comanda y el que termina en el comprobante.
     */
    public function getDisponibles(int $idEmpresa, string $buscar = ''): array
    {
        $where = "WHERE m.id_empresa = :e AND m.eliminado = false AND m.disponible = true";
        $params = [':e' => $idEmpresa];
        if ($buscar !== '') {
            $where .= " AND (m.nombre ILIKE :b OR m.descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql = "SELECT m.id, m.id_producto, m.nombre, m.descripcion, m.precio, COALESCE(p.imagen, m.imagen) AS imagen,
                       m.destacado, m.orden,
                       COALESCE(m.id_estacion_impresion, c.id_estacion_impresion, cp.id_estacion_impresion) AS id_estacion_impresion,
                       p.codigo AS producto_codigo, p.codigo_barras, p.codigo_auxiliar, p.inventariable, p.tipo_produccion,
                       COALESCE(m.id_tarifa_iva, p.tarifa_iva) AS id_tarifa_iva,
                       COALESCE(ti.porcentaje_iva, tp.porcentaje_iva, 0) AS porcentaje_iva,
                       COALESCE(ti.codigo, tp.codigo, '0') AS codigo_iva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN categorias c ON c.id = m.id_categoria AND c.id_empresa = m.id_empresa
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
        $sql = "SELECT m.id, m.id_producto, m.nombre, m.descripcion, m.precio, COALESCE(p.imagen, m.imagen) AS imagen,
                       COALESCE(m.id_estacion_impresion, c.id_estacion_impresion, cp.id_estacion_impresion) AS id_estacion_impresion,
                       COALESCE(m.id_tarifa_iva, p.tarifa_iva) AS id_tarifa_iva
                FROM menu_items m
                LEFT JOIN productos p ON p.id = m.id_producto
                LEFT JOIN categorias c ON c.id = m.id_categoria AND c.id_empresa = m.id_empresa
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

    // ─── Categorías ───────────────────────────────────────────────────────────

    /**
     * Categorías para clasificar los ítems: son las MISMAS de Productos
     * (`categorias`), no una lista propia del menú. Se administran en su módulo;
     * aquí solo se leen para llenar el selector del ítem.
     */
    public function getCategorias(int $idEmpresa): array
    {
        $sql = "SELECT c.id, c.nombre
                FROM categorias c
                WHERE c.id_empresa = :e AND c.eliminado = false
                ORDER BY c.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Estaciones de impresión (catálogo compartido: Productos + Menú + KDS) ────

    /**
     * Catálogo de estaciones, solo LECTURA (selector "Preparar en", KDS y
     * tablero de mesas). El CRUD vive en ConfiguracionRestauranteRepository.
     */
    public function getEstaciones(int $idEmpresa): array
    {
        // Las columnas de impresora llegaron después que la tabla: si el SQL
        // no está aplicado todavía en este servidor, el catálogo se sirve igual
        // (el tablero de mesas y el KDS dependen de él para arrancar).
        $colsImpresion = $this->tablaExiste('comandas_impresiones')
            ? ', imprime_ordenes, imprimir_auto, ancho_papel, copias'
            : '';

        $sql = "SELECT id, nombre, tipo, orden, activo{$colsImpresion}
                FROM estaciones_impresion
                WHERE id_empresa = :e AND eliminado = false
                ORDER BY orden ASC, nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

}
