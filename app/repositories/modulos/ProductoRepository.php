<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ProductoRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'codigo', 'nombre', 'precio_base', 'status', 'tipo_produccion',
        'nombre_categoria', 'nombre_marca', 'codigo_auxiliar', 'codigo_barras',
        'nombre_medida', 'nombre_tarifa_iva', 'valor_iva', 'pvp',
        'inventariable', 'stock_minimo', 'stock_maximo', 'valor_ice', 'saldo_actual'
    ];

    public function __construct()
    {
        parent::__construct('productos');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null,
        ?string $soloOpcion = null,
        bool $soloActivos = false
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($soloActivos) {
            $whereSql .= " AND p.status = 1";
        }

        if ($soloOpcion !== null) {
            $whereSql .= " AND (p.opciones->>'" . $soloOpcion . "')::boolean = true";
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['p.nombre', 'p.codigo', 'p.codigo_auxiliar', 'p.codigo_barras'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }

        // El buscador envía etiquetas amigables ('activo'/'inactivo', 'bien'/'servicio')
        // pero p.status es entero (1/0) y p.tipo_produccion guarda códigos ('01'/'02').
        // Traducir antes de aplicarFiltros() para no comparar contra valores que nunca
        // van a coincidir (o, en el caso de status, romper el bind a entero).
        // Mismo patrón que ClienteRepository::getListado().
        $mapEstado = ['activo' => '1', 'inactivo' => '0'];
        $mapTipo   = ['bien' => '01', 'servicio' => '02'];
        $traducciones = ['estado' => $mapEstado, 'status' => $mapEstado, 'tipo' => $mapTipo];
        foreach ($traducciones as $claveFiltro => $mapa) {
            if (!isset($parsed['filtros'][$claveFiltro])) continue;
            $val = $parsed['filtros'][$claveFiltro]['valor'];
            $parsed['filtros'][$claveFiltro]['valor'] = is_array($val)
                ? array_map(fn($v) => $mapa[strtolower(trim((string)$v))] ?? $v, $val)
                : ($mapa[strtolower(trim((string)$val))] ?? $val);
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'        => 'p.nombre',
                'codigo'        => 'p.codigo',
                'codigo_aux'    => 'p.codigo_auxiliar',
                'codigo_barras' => 'p.codigo_barras',
                'barras'        => 'p.codigo_barras',
                'categoria'     => 'cat.nombre',
                'marca'         => 'mar.nombre',
                'medida'        => 'um.nombre',
            ],
            'exacto'   => [
                'estado'       => 'p.status',
                'status'       => 'p.status',
                'tipo'         => 'p.tipo_produccion',
                'inventariable' => 'p.inventariable',
            ],
            'numerico' => [
                'precio'    => 'p.precio_base',
                // p.stock no existe: el saldo real se calcula en vivo desde el Kardex
                // (misma subquery correlacionada que la columna saldo_actual del SELECT).
                'stock'     => '(SELECT COALESCE(SUM(k.cantidad), 0) FROM inventario_kardex k WHERE k.id_producto = p.id AND k.id_empresa = p.id_empresa AND k.eliminado = false)',
                'stock_min' => 'p.stock_minimo',
                'stock_max' => 'p.stock_maximo',
            ],
        ]);

        $countJoins = "LEFT JOIN categorias cat ON cat.id = p.id_categoria
                       LEFT JOIN marcas mar ON mar.id = p.id_marca
                       LEFT JOIN unidades_medida um ON um.id = p.id_medida";

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} p {$countJoins} {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        
        $orderExpr = match($ordenCol) {
            'nombre_categoria' => 'cat.nombre',
            'nombre_marca'     => 'mar.nombre',
            'nombre_medida'    => 'um.nombre',
            'nombre_tarifa_iva' => 'ti.tarifa',
            'valor_iva'        => '((p.precio_base + COALESCE(p.valor_ice, 0)) * (COALESCE(ti.porcentaje_iva, 0) / 100))',
            'pvp'              => '((p.precio_base + COALESCE(p.valor_ice, 0)) * (1 + COALESCE(ti.porcentaje_iva, 0) / 100))',
            'saldo_actual'     => '(SELECT COALESCE(SUM(k.cantidad), 0) FROM inventario_kardex k WHERE k.id_producto = p.id AND k.id_empresa = p.id_empresa AND k.eliminado = false)',
            default            => "p.{$ordenCol}"
        };

        $sqlRows = "SELECT p.*,
                           cat.nombre AS nombre_categoria,
                           mar.nombre AS nombre_marca,
                           ti.tarifa AS nombre_tarifa_iva,
                           ti.porcentaje_iva AS porcentaje_iva_final,
                           ti.codigo AS codigo_iva_final,
                           ti.status AS status_iva_final,
                           um.nombre AS nombre_medida,
                           um.abreviatura AS abreviatura_medida,
                           ((p.precio_base + COALESCE(p.valor_ice, 0)) * (COALESCE(ti.porcentaje_iva, 0)::numeric / 100)) AS valor_iva,
                           ((p.precio_base + COALESCE(p.valor_ice, 0)) * (1 + COALESCE(ti.porcentaje_iva, 0)::numeric / 100)) AS pvp,
                           (SELECT COALESCE(SUM(k.cantidad), 0)
                              FROM inventario_kardex k
                             WHERE k.id_producto = p.id
                               AND k.id_empresa = p.id_empresa
                               AND k.eliminado = false) AS saldo_actual
                    FROM {$this->table} p
                    LEFT JOIN categorias cat ON cat.id = p.id_categoria
                    LEFT JOIN marcas mar ON mar.id = p.id_marca
                    LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                    LEFT JOIN unidades_medida um ON um.id = p.id_medida
                    {$whereSql}
                    ORDER BY $orderExpr $dir";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    /**
     * Producto por id, con los mismos joins/columnas calculadas que getListado()
     * (porcentaje_iva_final, codigo_iva_final, etc.) — mismo shape que espera
     * seleccionarProductoEnFila() en Factura de Venta. Solo productos vendibles y
     * activos (mismos filtros que getListado($soloOpcion='venta', $soloActivos=true)),
     * para no facturar algo que ya no se puede vender aunque el id exista.
     */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.*,
                       cat.nombre AS nombre_categoria,
                       mar.nombre AS nombre_marca,
                       ti.tarifa AS nombre_tarifa_iva,
                       ti.porcentaje_iva AS porcentaje_iva_final,
                       ti.codigo AS codigo_iva_final,
                       ti.status AS status_iva_final,
                       um.nombre AS nombre_medida,
                       um.abreviatura AS abreviatura_medida
                FROM {$this->table} p
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                LEFT JOIN unidades_medida um ON um.id = p.id_medida
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false
                  AND p.status = 1 AND (p.opciones->>'venta')::boolean = true";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeCodigo(int $idEmpresa, string $codigo, ?int $excluirId = null): bool
    {
        // lower(): concuerda con el índice único productos_codigo_unico_idx, de modo
        // que 'P001' y 'p001' se consideran el mismo código.
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo) AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa, ':codigo' => trim($codigo)];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Busca por código + empresa, INCLUYENDO eliminados (para que la replicación
     * entre empresas reactive en vez de duplicar). Case-insensitive, mismo criterio
     * que existeCodigo().
     */
    public function findByCodigo(int $idEmpresa, string $codigo): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo)
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':codigo' => trim($codigo)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Reactiva un producto eliminado SIN tocar sus datos (a diferencia de un
     * update completo). Usado por la replicación entre empresas: si el producto
     * ya existía en la empresa destino pero estaba eliminado, se reactiva tal
     * cual estaba en vez de sobrescribirlo con los datos de la empresa origen.
     */
    public function reactivarSoloEliminado(int $id, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = false,
                    updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':id' => $id]);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, codigo, nombre,
                    codigo_auxiliar, codigo_barras, precio_base, tipo_produccion,
                    tarifa_iva, id_medida, id_tipo_medida, status, valor_ice, codigo_ice,
                    nombre_ice, inventariable, id_categoria, id_marca, imagen, costo_producto,
                    eliminado, created_at, stock_minimo, stock_maximo, id_ice, opciones
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :codigo, :nombre,
                    :codigo_auxiliar, :codigo_barras, :precio_base, :tipo_produccion,
                    :tarifa_iva, :id_medida, :id_tipo_medida, :status, :valor_ice, :codigo_ice,
                    :nombre_ice, :inventariable, :id_categoria, :id_marca, :imagen, :costo_producto,
                    :eliminado, CURRENT_TIMESTAMP, :stock_minimo, :stock_maximo, :id_ice, :opciones
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'             => $data['id_empresa'],
            ':id_usuario'             => $data['id_usuario'],
            ':created_by'             => $data['id_usuario'],
            ':codigo'                 => $data['codigo'],
            ':nombre'                 => $data['nombre'],
            ':codigo_auxiliar'        => $data['codigo_auxiliar'],
            ':codigo_barras'          => $data['codigo_barras'],
            ':precio_base'            => $data['precio_base'],
            ':tipo_produccion'        => $data['tipo_produccion'],
            ':tarifa_iva'             => $data['tarifa_iva'],
            ':id_medida'              => $data['id_medida'],
            ':id_tipo_medida'         => $data['id_tipo_medida'],
            ':status'                 => $data['status'] ? 1 : 0,
            ':valor_ice'              => $data['valor_ice'],
            ':codigo_ice'             => $data['codigo_ice'],
            ':nombre_ice'             => $data['nombre_ice'],
            ':inventariable'          => $data['inventariable'] ? 'true' : 'false',
            ':id_categoria'           => $data['id_categoria'],
            ':id_marca'               => $data['id_marca'],
            ':imagen'                 => $data['imagen'],
            ':costo_producto'         => $data['costo_producto'] ?? 0,
            ':eliminado'              => 'false',
            ':stock_minimo'           => $data['stock_minimo'] ?? 0,
            ':stock_maximo'           => $data['stock_maximo'] ?? 0,
            ':id_ice'                 => !empty($data['id_ice']) ? (int)$data['id_ice'] : null,
            ':opciones'               => $data['opciones'] ?? '{"compra":true,"venta":true}',
        ]);
        return (int) $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                codigo = :codigo,
                nombre = :nombre,
                codigo_auxiliar = :codigo_auxiliar,
                codigo_barras = :codigo_barras,
                precio_base = :precio_base,
                tipo_produccion = :tipo_produccion,
                tarifa_iva = :tarifa_iva,
                id_medida = :id_medida,
                id_tipo_medida = :id_tipo_medida,
                status = :status,
                valor_ice = :valor_ice,
                codigo_ice = :codigo_ice,
                nombre_ice = :nombre_ice,
                inventariable = :inventariable,
                id_categoria = :id_categoria,
                id_marca = :id_marca,
                imagen = :imagen,
                costo_producto = :costo_producto,
                stock_minimo = :stock_minimo,
                stock_maximo = :stock_maximo,
                id_ice = :id_ice,
                opciones = :opciones,
                id_usuario = :id_usuario,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'                 => $data['codigo'],
            ':nombre'                 => $data['nombre'],
            ':codigo_auxiliar'        => $data['codigo_auxiliar'],
            ':codigo_barras'          => $data['codigo_barras'],
            ':precio_base'            => $data['precio_base'],
            ':tipo_produccion'        => $data['tipo_produccion'],
            ':tarifa_iva'             => $data['tarifa_iva'],
            ':id_medida'              => $data['id_medida'],
            ':id_tipo_medida'         => $data['id_tipo_medida'],
            ':status'                 => $data['status'] ? 1 : 0,
            ':valor_ice'              => $data['valor_ice'],
            ':codigo_ice'             => $data['codigo_ice'],
            ':nombre_ice'             => $data['nombre_ice'],
            ':inventariable'          => $data['inventariable'] ? 'true' : 'false',
            ':id_categoria'           => $data['id_categoria'],
            ':id_marca'               => $data['id_marca'],
            ':imagen'                 => $data['imagen'],
            ':costo_producto'         => $data['costo_producto'] ?? 0,
            ':stock_minimo'           => $data['stock_minimo'] ?? 0,
            ':stock_maximo'           => $data['stock_maximo'] ?? 0,
            ':id_ice'                 => !empty($data['id_ice']) ? (int)$data['id_ice'] : null,
            ':opciones'               => $data['opciones'] ?? '{"compra":true,"venta":true}',
            ':id_usuario'             => $data['id_usuario'],
            ':updated_by'             => $data['id_usuario'],
            ':id'                     => $id,
            ':id_empresa'             => $idEmpresa
        ]);
    }

    /** Cambia solo la categoría del producto (edición en línea desde reportes). */
    public function actualizarCategoria(int $id, int $idEmpresa, ?int $idCategoria, int $userId): void
    {
        $sql = "UPDATE {$this->table}
                SET id_categoria = :id_categoria, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_categoria' => $idCategoria, ':id' => $id, ':id_empresa' => $idEmpresa, ':uid' => $userId,
        ]);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.*,
                       cat.nombre AS nombre_categoria,
                       mar.nombre AS nombre_marca,
                       um.nombre AS nombre_medida,
                       ti.porcentaje_iva,
                       ti.tarifa AS nombre_tarifa_iva,
                       u1.nombre AS creado_por_nombre,
                       u2.nombre AS actualizado_por_nombre
                FROM {$this->table} p
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                LEFT JOIN unidades_medida um ON um.id = p.id_medida
                LEFT JOIN tipo_medida tm ON tm.id = p.id_tipo_medida
                LEFT JOIN usuarios u1 ON u1.id = p.created_by
                LEFT JOIN usuarios u2 ON u2.id = p.updated_by
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        $row['inventarios']    = $this->getInventarios($id, $idEmpresa);
        $row['precios']        = $this->getPrecios($id, $idEmpresa);
        $row['componentes']    = $this->getComponentes($id, $idEmpresa);
        $row['variantes']      = $this->getVariantes($id, $idEmpresa);
        $row['homologaciones'] = $this->getHomologaciones($id, $idEmpresa);
        
        // Stock general calculado
        $row['stock_actual_general'] = $this->getInventarioGeneral($id, $idEmpresa);

        return $row;
    }

    public function getHomologaciones(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT ph.*, 
                       pr.razon_social AS nombre_proveedor, 
                       pr.identificacion AS id_proveedor_ruc,
                       (SELECT d.descripcion 
                        FROM compras_detalle d 
                        JOIN compras_cabecera c ON d.id_compra = c.id 
                        WHERE c.id_proveedor = ph.id_proveedor 
                          AND d.codigo_principal = ph.codigo_proveedor 
                          AND c.id_empresa = ph.id_empresa
                          AND c.eliminado = false
                        ORDER BY c.fecha_emision DESC LIMIT 1) AS descripcion_homologada
                FROM productos_homologacion ph
                JOIN proveedores pr ON pr.id = ph.id_proveedor
                WHERE ph.id_producto = :id_p AND ph.id_empresa = :id_e AND ph.eliminado = false
                ORDER BY pr.razon_social ASC";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getNombre(int $id): string
    {
        $sql = "SELECT nombre FROM {$this->table} WHERE id = ? LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([$id]);
        return (string) ($st->fetchColumn() ?: 'Producto #' . $id);
    }

    public function isInventariable(int $id, int $idEmpresa): bool
    {
        $info = $this->getInfoControlInventario($id, $idEmpresa);
        return $info['inventariable'] && $info['tipo_produccion'] !== '02';
    }

    public function getInfoControlInventario(int $id, int $idEmpresa): array
    {
        $sql = "SELECT inventariable, tipo_produccion FROM {$this->table} WHERE id = ? AND id_empresa = ? AND eliminado = false LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return ['inventariable' => false, 'tipo_produccion' => ''];
        }

        $inv = $row['inventariable'];
        return [
            'inventariable'   => ($inv === true || $inv === 'true' || $inv == 1 || $inv === 't'),
            'tipo_produccion' => (string) $row['tipo_produccion']
        ];
    }

    public function getComponentes(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT pc.*, p.nombre AS nombre_componente, p.codigo AS codigo_componente, um.nombre AS nombre_medida
                FROM productos_componentes pc
                JOIN productos p ON p.id = pc.id_producto_hijo
                LEFT JOIN unidades_medida um ON um.id = pc.id_medida
                WHERE pc.id_producto_padre = :id_p AND pc.id_empresa = :id_e AND pc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVariantes(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT * FROM productos_variantes 
                WHERE id_producto = :id_p AND id_empresa = :id_e AND eliminado = false
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInventarios(int $idProducto, int $idEmpresa): array
    {
        // Obtenemos stock actual sumando el Kardex en tiempo real
        $sql = "SELECT pb.id_bodega,
                       b.nombre AS nombre_bodega,
                       pb.stock_minimo,
                       pb.stock_maximo,
                       (SELECT COALESCE(SUM(cantidad), 0)
                        FROM inventario_kardex
                        WHERE id_producto = :id_producto
                          AND id_bodega = pb.id_bodega
                          AND id_empresa = :id_empresa
                          AND eliminado = false) AS stock_actual,
                       (SELECT CASE WHEN SUM(cantidad) > 0
                            THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6)
                            ELSE 0 END
                        FROM inventario_kardex
                        WHERE id_producto = :id_producto
                          AND id_bodega = pb.id_bodega
                          AND id_empresa = :id_empresa
                          AND tipo_movimiento = 'entrada' AND eliminado = false) AS costo_promedio
                FROM productos_bodegas pb
                JOIN bodegas b ON b.id = pb.id_bodega
                WHERE pb.id_producto = :id_producto
                  AND pb.id_empresa = :id_empresa
                  AND pb.eliminado = false
                  AND b.eliminado = false";

        $st = $this->db->prepare($sql);
        $st->execute([':id_producto' => $idProducto, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Costo promedio ponderado del producto a partir de las entradas del Kardex,
     * consolidando todas las bodegas. Null si no hay entradas registradas.
     */
    public function calcularCostoPromedioKardex(int $idProducto, int $idEmpresa): ?float
    {
        $sql = "SELECT CASE WHEN SUM(cantidad) > 0
                    THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6)
                    ELSE NULL END AS costo_promedio
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p
                  AND tipo_movimiento = 'entrada' AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto]);
        $valor = $st->fetchColumn();
        return ($valor === false || $valor === null) ? null : (float) $valor;
    }

    /** Actualiza únicamente el costo del producto (usado por "Actualizar desde Kardex"). */
    public function actualizarCosto(int $id, int $idEmpresa, float $costo, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table}
                SET costo_producto = :costo, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':costo' => $costo, ':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /** IDs + costo actual de los productos inventariables de la empresa (para la actualización masiva). */
    public function getInventariablesConCosto(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $whereSql = $this->getBaseWhere($idEmpresa, '', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT id, nombre, costo_producto FROM {$this->table}
                {$whereSql} AND inventariable = true";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrecios(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT id, nombre_precio, precio, valido_desde, valido_hasta, estado
                FROM productos_precios
                WHERE id_producto = :id_producto AND id_empresa = :id_empresa AND eliminado = false
                ORDER BY nombre_precio";
        $st = $this->db->prepare($sql);
        $st->execute([':id_producto' => $idProducto, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }


    public function syncInventarios(int $idProducto, int $idEmpresa, array $inventarios, int $userId): void
    {
        $sqlDel = "UPDATE productos_bodegas SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_minimo, stock_maximo, stock_actual, created_by, updated_by)
                   VALUES (:id_e, :id_p, :id_b, :s_min, :s_max, :s_act, :uid, :uid)
                   ON CONFLICT (id_producto, id_bodega) 
                   DO UPDATE SET eliminado = false, stock_minimo = EXCLUDED.stock_minimo, stock_maximo = EXCLUDED.stock_maximo, updated_by = EXCLUDED.updated_by, updated_at = CURRENT_TIMESTAMP";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($inventarios as $inv) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':id_b'  => $inv['id_bodega'],
                ':s_min' => $inv['stock_minimo'] ?? 0,
                ':s_max' => $inv['stock_maximo'] ?? 0,
                ':s_act' => $inv['stock_actual'] ?? 0,
                ':uid'   => $userId
            ]);
        }
    }
    
    /** Recalcula el stock_actual denormalizado para un producto/bodega desde el Kardex */
    public function recalcularStockCache(int $idProducto, int $idBodega, int $idEmpresa): void
    {
        $sql = "UPDATE productos_bodegas 
                SET stock_actual = (
                    SELECT COALESCE(SUM(cantidad), 0) 
                    FROM inventario_kardex 
                    WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e AND eliminado = false
                ),
                updated_at = CURRENT_TIMESTAMP
                WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);
    }

    public function syncPrecios(int $idProducto, int $idEmpresa, array $precios, int $userId): void
    {
        $sqlDel = "UPDATE productos_precios SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_precios (id_empresa, id_producto, nombre_precio, precio, valido_desde, valido_hasta, estado, created_by, updated_by)
                   VALUES (:id_e, :id_p, :nom, :pre, :des, :has, :est, :uid, :uid)";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($precios as $p) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':nom'   => $p['nombre_precio'],
                ':pre'   => $p['precio'],
                ':des'   => !empty($p['valido_desde']) ? $p['valido_desde'] : null,
                ':has'   => !empty($p['valido_hasta']) ? $p['valido_hasta'] : null,
                ':est'   => isset($p['estado']) ? ($p['estado'] ? 'true' : 'false') : 'true',
                ':uid'   => $userId
            ]);
        }
    }

    public function syncComponentes(int $idProducto, int $idEmpresa, array $componentes, int $userId): void
    {
        $sqlDel = "UPDATE productos_componentes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto_padre = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_componentes (id_empresa, id_producto_padre, id_producto_hijo, cantidad, id_medida, created_by, updated_by)
                   VALUES (:id_e, :id_p, :id_h, :can, :id_m, :uid, :uid)";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($componentes as $c) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':id_h'  => $c['id_producto_hijo'],
                ':can'   => $c['cantidad'],
                ':id_m'  => !empty($c['id_medida']) ? $c['id_medida'] : null,
                ':uid'   => $userId
            ]);
        }
    }

    public function syncVariantes(int $idProducto, int $idEmpresa, array $variantes, int $userId): void
    {
        $sqlDel = "UPDATE productos_variantes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_variantes (id_empresa, id_producto, nombre, valor, precio_adicional, created_by, updated_by)
                   VALUES (:id_e, :id_p, :nom, :val, :pad, :uid, :uid)";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($variantes as $v) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':nom'   => $v['nombre'],
                ':val'   => $v['valor'],
                ':pad'   => !empty($v['precio_adicional']) ? $v['precio_adicional'] : 0,
                ':uid'   => $userId
            ]);
        }
    }
    public function softDelete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_at = CURRENT_TIMESTAMP, 
                deleted_by = :uid,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :uid
                WHERE id = :id AND id_empresa = :id_e AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_e' => $idEmpresa]);
    }
    /**
     * Calcula el stock total del producto sumando todos los movimientos de Kardex
     * (Fuente de verdad absoluta)
     */
    public function getInventarioGeneral(int $idProducto, int $idEmpresa): float
    {
        $sql = "SELECT COALESCE(SUM(cantidad), 0) as total
                FROM inventario_kardex 
                WHERE id_producto = :id_p AND id_empresa = :id_e AND eliminado = false";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    /**
     * Verifica si el producto ha sido usado en facturas de venta o en movimientos de inventario.
     * Cuando es true, no se permite modificar código, nombre ni tipo_produccion.
     */
    public function estaUsadoEnDocumentos(int $id, int $idEmpresa): bool
    {
        $sqlV = "SELECT 1 FROM ventas_detalle WHERE id_producto = :id LIMIT 1";
        $stV  = $this->db->prepare($sqlV);
        $stV->execute([':id' => $id]);
        if ($stV->fetchColumn()) return true;

        $sqlK = "SELECT 1 FROM inventario_kardex
                 WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false LIMIT 1";
        $stK  = $this->db->prepare($sqlK);
        $stK->execute([':id' => $id, ':ide' => $idEmpresa]);
        return (bool) $stK->fetchColumn();
    }

    /**
     * Devuelve un listado de los módulos donde el producto está siendo utilizado,
     * filtrando por el ambiente activo de la empresa (tipo_ambiente).
     *
     * Regla de entornos separados:
     *  - Si la empresa está en PRUEBAS (1): solo revisa documentos de pruebas.
     *  - Si la empresa está en PRODUCCIÓN (2): solo revisa documentos de producción.
     * Así, un producto usado únicamente en pruebas puede eliminarse cuando la empresa
     * está en producción (y viceversa).
     *
     * Excepciones sin tipo_ambiente (suscripciones, órdenes, pedidos, componentes):
     * se verifican siempre porque son catálogos que aplican a ambos entornos.
     */
    public function obtenerUsos(int $id, int $idEmpresa, ?string $tipoAmbiente = null): array
    {
        $usos = [];
        $amb  = $tipoAmbiente; // '1' pruebas | '2' producción | null = todos

        // Documentos transaccionales con tipo_ambiente.
        // 'amb_col' = expresión de la columna tipo_ambiente; el filtro de ambiente
        // se agrega dinámicamente (con un único parámetro) para evitar errores de
        // tipo en PostgreSQL al reutilizar el placeholder.
        $checksAmbiente = [
            [
                'sql'    => "SELECT COUNT(*) FROM ventas_detalle vd
                             JOIN ventas_cabecera vc ON vc.id = vd.id_venta
                                AND vc.id_empresa = :ide AND vc.eliminado = false
                             WHERE vd.id_producto = :id",
                'amb_col' => 'vc.tipo_ambiente',
                'nombre' => 'Facturas de venta',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM compras_detalle cd
                             JOIN compras_cabecera cc ON cc.id = cd.id_compra
                                AND cc.id_empresa = :ide AND cc.eliminado = false
                             WHERE cd.id_producto = :id",
                'amb_col' => 'cc.tipo_ambiente',
                'nombre' => 'Compras',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM notas_credito_detalle ncd
                             JOIN notas_credito_cabecera ncc ON ncc.id = ncd.id_nota_credito
                                AND ncc.id_empresa = :ide AND ncc.eliminado = false
                             WHERE ncd.id_producto = :id",
                'amb_col' => 'ncc.tipo_ambiente',
                'nombre' => 'Notas de crédito',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM inventario_kardex
                             WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false",
                'amb_col' => 'tipo_ambiente',
                'nombre' => 'Movimientos de inventario',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM guias_remision_detalle grd
                             JOIN guias_remision_cabecera grc ON grc.id = grd.id_guia_remision
                                AND grc.id_empresa = :ide AND grc.eliminado = false
                             WHERE grd.id_producto = :id",
                'amb_col' => 'grc.tipo_ambiente',
                'nombre' => 'Guías de remisión',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM liquidaciones_detalle ld
                             JOIN liquidaciones_cabecera lc ON lc.id = ld.id_cabecera
                                AND lc.id_empresa = :ide AND lc.eliminado = false
                             WHERE ld.id_producto = :id",
                'amb_col' => 'lc.tipo_ambiente',
                'nombre' => 'Liquidaciones de compra',
            ],
        ];

        // Catálogos sin tipo_ambiente: se verifican siempre (aplican a ambos entornos)
        $checksSinAmbiente = [
            [
                'sql'    => "SELECT COUNT(*) FROM suscripciones_detalle sd
                             WHERE sd.id_producto = :id AND sd.id_empresa = :ide AND sd.eliminado = false",
                'nombre' => 'Suscripciones',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM ordenes_compra_detalle od
                             WHERE od.id_producto = :id AND od.id_empresa = :ide",
                'nombre' => 'Órdenes de compra',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM pedidos_detalle pd
                             JOIN pedidos_cabecera pc ON pc.id = pd.id_pedido
                                AND pc.id_empresa = :ide AND pc.eliminado = false
                             WHERE pd.id_producto = :id",
                'nombre' => 'Pedidos',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM productos_componentes
                             WHERE id_componente = :id AND id_empresa = :ide AND eliminado = false",
                'nombre' => 'Componente de otros productos',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM factura_express_items
                             WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false",
                'nombre' => 'Plantillas Factura Express',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM firmas_electronicas
                             WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false",
                'nombre' => 'Firmas electrónicas',
            ],
        ];

        foreach ($checksAmbiente as $check) {
            try {
                $sql    = $check['sql'];
                $params = [':id' => $id, ':ide' => $idEmpresa];
                // Filtrar por ambiente activo solo si se conoce (columnas varchar; comparar como texto)
                if ($amb !== null && !empty($check['amb_col'])) {
                    $sql .= " AND CAST({$check['amb_col']} AS VARCHAR) = :amb";
                    $params[':amb'] = $amb;
                }
                $st = $this->db->prepare($sql);
                $st->execute($params);
                if ((int)$st->fetchColumn() > 0) {
                    $usos[] = $check['nombre'];
                }
            } catch (\Throwable) {}
        }

        foreach ($checksSinAmbiente as $check) {
            try {
                $st = $this->db->prepare($check['sql']);
                $st->execute([':id' => $id, ':ide' => $idEmpresa]);
                if ((int)$st->fetchColumn() > 0) {
                    $usos[] = $check['nombre'];
                }
            } catch (\Throwable) {}
        }

        return $usos;
    }

    /**
     * Retorna el tipo_ambiente activo de la empresa ('1' pruebas | '2' producción).
     */
    public function getTipoAmbienteEmpresa(int $idEmpresa): ?string
    {
        $st = $this->db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ? AND eliminado = false LIMIT 1");
        $st->execute([$idEmpresa]);
        $val = $st->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    /**
     * Retorna los IDs del tipo medida "Unidad" (código "0") y su unidad "Unidad" para la empresa.
     */
    public function getMedidaDefaultUnidad(int $idEmpresa): ?array
    {
        $sqlTipo = "SELECT id FROM tipo_medida
                    WHERE id_empresa = :e AND eliminado = false AND status = true
                      AND (LOWER(nombre) = 'unidad' OR codigo = '0')
                    ORDER BY (CASE WHEN codigo = '0' THEN 0 ELSE 1 END) ASC
                    LIMIT 1";
        $stTipo = $this->db->prepare($sqlTipo);
        $stTipo->execute([':e' => $idEmpresa]);
        $idTipo = $stTipo->fetchColumn();
        if (!$idTipo) return null;

        $sqlMed = "SELECT id FROM unidades_medida
                   WHERE id_tipo = :t AND id_empresa = :e AND eliminado = false AND status = true
                   ORDER BY (CASE WHEN LOWER(nombre) = 'unidad' THEN 0 ELSE 1 END) ASC
                   LIMIT 1";
        $stMed = $this->db->prepare($sqlMed);
        $stMed->execute([':t' => $idTipo, ':e' => $idEmpresa]);
        $idMedida = $stMed->fetchColumn();
        if (!$idMedida) return null;

        return ['id_tipo_medida' => (int)$idTipo, 'id_medida' => (int)$idMedida];
    }

    public function getSiguienteCodigo(int $idEmpresa, string $tipo): string
    {
        $prefijo = ($tipo === '01') ? 'P' : 'S';

        // Saltar códigos ya ocupados (autogenerados o escritos a mano).
        $stExiste = $this->db->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo)
               AND eliminado = false
             LIMIT 1"
        );

        // 1) Continuar la numeración real de la empresa: si ya existen códigos
        //    puramente numéricos para este tipo (p. ej. 202401…202417, aunque no
        //    sigan el formato P00X/S00X), se toma el mayor y se sugiere el
        //    siguiente, conservando el mismo largo (relleno de ceros). Se usa el
        //    MÁXIMO y no "el último creado" porque un producto reciente con un
        //    código manual suelto (p. ej. '001') no debe desviar la secuencia.
        //    Se limita a 9 dígitos (además de excluir del cast a bigint) para
        //    no confundir un código de barras (EAN-13/UPC-A, 12-13 dígitos)
        //    escrito por error en 'codigo' con la numeración real del producto.
        $stMax = $this->db->prepare(
            "SELECT codigo FROM {$this->table}
             WHERE id_empresa = :id_empresa AND tipo_produccion = :tipo
               AND eliminado = false AND codigo ~ '^[0-9]{1,9}$'
             ORDER BY codigo::bigint DESC
             LIMIT 1"
        );
        $stMax->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipo]);
        $maxCodigo = (string) ($stMax->fetchColumn() ?: '');

        if ($maxCodigo !== '') {
            $longitud = strlen($maxCodigo);
            $numero = (int) $maxCodigo;
            do {
                $numero++;
                $codigo = str_pad((string) $numero, $longitud, '0', STR_PAD_LEFT);
                $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
            } while ($stExiste->fetchColumn() && $numero < 999999999999999);

            return $codigo;
        }

        // 2) Prefijo genérico + número (p. ej. 'VTA.0001', 'VTA.0002'… o el propio
        //    'P001', 'S001'…, que es solo un caso particular de este mismo patrón).
        //    Se separa cada código en "prefijo" (parte no numérica) + "sufijo"
        //    (dígitos finales, máx. 9) y se agrupa por prefijo. Gana el prefijo
        //    más usado (no el del último creado, por la misma razón del punto 1:
        //    un código manual suelto no debe desviar la convención real de la
        //    empresa); empate se resuelve por el sufijo más alto encontrado.
        $stTodos = $this->db->prepare(
            "SELECT codigo FROM {$this->table}
             WHERE id_empresa = :id_empresa AND tipo_produccion = :tipo
               AND eliminado = false"
        );
        $stTodos->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipo]);

        $grupos = [];
        foreach ($stTodos->fetchAll(PDO::FETCH_COLUMN) as $codigoExistente) {
            $codigoExistente = (string) $codigoExistente;
            if (!preg_match('/^(.*?)([0-9]{1,9})$/', $codigoExistente, $m) || $m[1] === '') {
                continue; // sin prefijo (numérico puro) ya se resolvió en el punto 1
            }
            $pref = $m[1];
            $valor = (int) $m[2];
            if (!isset($grupos[$pref])) {
                $grupos[$pref] = ['count' => 0, 'max' => -1, 'codigoMax' => ''];
            }
            $grupos[$pref]['count']++;
            if ($valor > $grupos[$pref]['max']) {
                $grupos[$pref]['max'] = $valor;
                $grupos[$pref]['codigoMax'] = $codigoExistente;
            }
        }

        if (!empty($grupos)) {
            uasort($grupos, fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: $b['max'] <=> $a['max']);
            $prefGanador = array_key_first($grupos);
            $g = $grupos[$prefGanador];

            $sufMax = substr($g['codigoMax'], strlen($prefGanador));
            $longitud = strlen($sufMax);
            $numero = (int) $sufMax;

            do {
                $numero++;
                $codigo = $prefGanador . str_pad((string) $numero, $longitud, '0', STR_PAD_LEFT);
                $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
            } while ($stExiste->fetchColumn() && $numero < 999999999);

            return $codigo;
        }

        // 3) Catálogo vacío para este tipo: no hay ninguna convención previa que
        //    seguir. Se arranca desde cero con el formato autogenerado (P001, S001…).
        $numero = 0;
        do {
            $numero++;
            $codigo = $prefijo . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
        } while ($stExiste->fetchColumn() && $numero < 999999999);

        return $codigo;
    }

    public function searchSimple(int $idEmpresa, string $q, int $limit = 10, string $tipo = '', int $exclude = 0, bool $soloActivos = false): array
    {
        $db = \App\core\Database::getConnection();
        $params = [$idEmpresa, "%$q%", "%$q%"];
        $whereSql = "";

        if ($tipo !== '') {
            $whereSql .= " AND p.tipo_produccion = ? ";
            $params[] = $tipo;
        }

        if ($soloActivos) {
            $whereSql .= " AND p.status = 1 ";
        }

        if ($exclude > 0) {
            $whereSql .= " AND p.id != ? ";
            $params[] = $exclude;
        }

        $params[] = $limit;

        $st = $db->prepare("SELECT p.id, p.codigo, p.nombre, p.id_medida, p.id_tipo_medida, um.nombre AS nombre_medida
                            FROM productos p
                            LEFT JOIN unidades_medida um ON um.id = p.id_medida
                            WHERE p.id_empresa = ? AND (p.codigo ILIKE ? OR p.nombre ILIKE ?) AND p.eliminado = false 
                            {$whereSql}
                            ORDER BY p.nombre ASC LIMIT ?");
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function softDeleteHomologacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE productos_homologacion SET 
                eliminado = true, 
                deleted_at = CURRENT_TIMESTAMP, 
                deleted_by = :uid,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :uid
                WHERE id = :id AND id_empresa = :id_e AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_e' => $idEmpresa]);
    }
}


