<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos para la carga masiva de productos/servicios vía Excel.
 *
 * Precarga en mapas los catálogos y los productos existentes para poder validar
 * miles de filas sin lanzar una consulta por fila.
 *
 * El grueso de la escritura la hace ProductoService (para conservar sus reglas y
 * su auditoría). Aquí solo viven las escrituras que ese servicio no cubre:
 * las homologaciones con proveedores y el alta de categorías/marcas nuevas.
 */
class CargaProductosRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('productos');
    }

    /**
     * Productos y servicios existentes de la empresa, indexados por código en
     * minúsculas (el código es case-insensitive, igual que el índice único).
     */
    public function getMapaProductos(int $idEmpresa): array
    {
        $sql = "SELECT id, codigo, nombre, tipo_produccion, status, inventariable
                FROM productos
                WHERE id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim($row['codigo']))] = [
                'id'              => (int) $row['id'],
                'codigo'          => $row['codigo'],
                'nombre'          => $row['nombre'],
                'tipo_produccion' => $row['tipo_produccion'],
                'status'          => (int) $row['status'],
                'inventariable'   => (bool) $row['inventariable'],
            ];
        }
        return $mapa;
    }

    /**
     * Tarifas de IVA indexadas por su código (catálogo global).
     *
     * Por defecto incluye también las INACTIVAS: hay productos históricos con
     * tarifas ya derogadas (12%, 14%) que deben poder seguir validándose. El
     * generador de la plantilla pide solo las activas para el desplegable, de
     * modo que las derogadas no se ofrezcan para nuevos productos.
     */
    public function getMapaTarifasIva(bool $soloActivas = false): array
    {
        $sql = "SELECT id, codigo, tarifa, porcentaje_iva, status
                FROM tarifa_iva";
        if ($soloActivas) {
            $sql .= " WHERE status = 1";
        }
        $sql .= " ORDER BY codigo";
        $st = $this->db->query($sql);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[trim((string) $row['codigo'])] = [
                'id'             => (int) $row['id'],
                'tarifa'         => $row['tarifa'],
                'porcentaje_iva' => (int) $row['porcentaje_iva'],
                'activa'         => ((int) $row['status'] === 1),
            ];
        }
        return $mapa;
    }

    /** Unidades de medida activas de la empresa, indexadas por código en mayúsculas. */
    public function getMapaUnidadesMedida(int $idEmpresa): array
    {
        $sql = "SELECT um.id, um.id_tipo, um.codigo, um.nombre, um.abreviatura,
                       tm.nombre AS tipo_medida
                FROM unidades_medida um
                LEFT JOIN tipo_medida tm ON tm.id = um.id_tipo
                WHERE um.id_empresa = :id_empresa
                  AND um.eliminado = false AND um.status = true
                ORDER BY um.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtoupper(trim($row['codigo']))] = [
                'id'          => (int) $row['id'],
                'id_tipo'     => (int) $row['id_tipo'],
                'codigo'      => $row['codigo'],
                'nombre'      => $row['nombre'],
                'abreviatura' => $row['abreviatura'],
                'tipo_medida' => $row['tipo_medida'] ?? '',
            ];
        }
        return $mapa;
    }

    /** Categorías de la empresa, indexadas por nombre en minúsculas. */
    public function getMapaCategorias(int $idEmpresa): array
    {
        return $this->getMapaCatalogoNombre('categorias', $idEmpresa);
    }

    /** Marcas de la empresa, indexadas por nombre en minúsculas. */
    public function getMapaMarcas(int $idEmpresa): array
    {
        return $this->getMapaCatalogoNombre('marcas', $idEmpresa);
    }

    /** Bodegas de la empresa, indexadas por nombre en minúsculas. */
    public function getMapaBodegas(int $idEmpresa): array
    {
        return $this->getMapaCatalogoNombre('bodegas', $idEmpresa);
    }

    /**
     * Catálogos simples (id + nombre) filtrados por empresa.
     * $tabla es un literal controlado internamente, nunca entrada del usuario.
     */
    private function getMapaCatalogoNombre(string $tabla, int $idEmpresa): array
    {
        $permitidas = ['categorias', 'marcas', 'bodegas'];
        if (!in_array($tabla, $permitidas, true)) {
            return [];
        }

        $sql = "SELECT id, nombre FROM {$tabla}
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim($row['nombre']))] = [
                'id'     => (int) $row['id'],
                'nombre' => $row['nombre'],
            ];
        }
        return $mapa;
    }

    /** ICE configurado en la empresa, indexado por código ATS en mayúsculas. */
    public function getMapaIce(int $idEmpresa): array
    {
        $sql = "SELECT id, codigo_ats, nombre_ice, valor_ice
                FROM empresa_ice
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY codigo_ats";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtoupper(trim($row['codigo_ats']))] = [
                'id'         => (int) $row['id'],
                'codigo_ats' => $row['codigo_ats'],
                'nombre_ice' => $row['nombre_ice'],
                'valor_ice'  => (float) $row['valor_ice'],
            ];
        }
        return $mapa;
    }

    /**
     * Proveedores de la empresa indexados por RUC/cédula.
     * La homologación se enlaza por este identificador, no por nombre.
     */
    public function getMapaProveedores(int $idEmpresa): array
    {
        $sql = "SELECT id, identificacion, razon_social
                FROM proveedores
                WHERE id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[trim((string) $row['identificacion'])] = [
                'id'            => (int) $row['id'],
                'razon_social'  => $row['razon_social'],
            ];
        }
        return $mapa;
    }

    /**
     * Ids de productos que ya se usaron en documentos (ventas o kardex).
     * Se precarga como conjunto porque ProductoService::actualizar conserva
     * código, nombre y tipo cuando el producto ya está en uso; hay que avisarlo.
     */
    public function getIdsUsadosEnDocumentos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT vd.id_producto
                  FROM ventas_detalle vd
                  JOIN productos p ON p.id = vd.id_producto
                 WHERE p.id_empresa = :id_empresa AND vd.id_producto IS NOT NULL
                UNION
                SELECT DISTINCT k.id_producto
                  FROM inventario_kardex k
                 WHERE k.id_empresa = :id_empresa AND k.eliminado = false
                   AND k.id_producto IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $ids = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[(int) $id] = true;
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exportación masiva para la plantilla (una consulta por hoja, sin N+1)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Todos los productos/servicios de la empresa con los códigos de catálogo ya
     * resueltos, en el orden y formato que espera la hoja Productos.
     */
    public function getProductosParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT p.codigo, p.nombre, p.tipo_produccion, p.codigo_auxiliar,
                       p.codigo_barras, p.precio_base, p.costo_producto,
                       ti.codigo  AS codigo_iva,
                       um.codigo  AS codigo_medida,
                       cat.nombre AS categoria,
                       mar.nombre AS marca,
                       p.inventariable, p.stock_minimo, p.stock_maximo,
                       p.opciones, ice.codigo_ats AS codigo_ice, p.status
                FROM productos p
                LEFT JOIN tarifa_iva      ti  ON ti.id  = p.tarifa_iva
                LEFT JOIN unidades_medida um  ON um.id  = p.id_medida
                LEFT JOIN categorias      cat ON cat.id = p.id_categoria
                LEFT JOIN marcas          mar ON mar.id = p.id_marca
                LEFT JOIN empresa_ice     ice ON ice.id = p.id_ice
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY p.tipo_produccion, p.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Precios de todos los productos, con el código del producto. */
    public function getPreciosParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT p.codigo AS codigo_producto, pp.nombre_precio, pp.precio,
                       pp.valido_desde, pp.valido_hasta, pp.estado
                FROM productos_precios pp
                JOIN productos p ON p.id = pp.id_producto
                WHERE pp.id_empresa = :id_empresa AND pp.eliminado = false
                  AND p.eliminado = false
                ORDER BY p.codigo, pp.nombre_precio";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Variantes de todos los productos. */
    public function getVariantesParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT p.codigo AS codigo_producto, pv.nombre, pv.valor, pv.precio_adicional
                FROM productos_variantes pv
                JOIN productos p ON p.id = pv.id_producto
                WHERE pv.id_empresa = :id_empresa AND pv.eliminado = false
                  AND p.eliminado = false
                  AND p.tipo_produccion <> '02'
                ORDER BY p.codigo, pv.nombre, pv.valor";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Componentes (receta) con el código del padre y del hijo. */
    public function getComponentesParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT padre.codigo AS codigo_padre, hijo.codigo AS codigo_hijo,
                       pc.cantidad, um.codigo AS codigo_medida
                FROM productos_componentes pc
                JOIN productos padre ON padre.id = pc.id_producto_padre
                JOIN productos hijo  ON hijo.id  = pc.id_producto_hijo
                LEFT JOIN unidades_medida um ON um.id = pc.id_medida
                WHERE pc.id_empresa = :id_empresa AND pc.eliminado = false
                  AND padre.eliminado = false AND hijo.eliminado = false
                  AND padre.tipo_produccion <> '02'
                ORDER BY padre.codigo, hijo.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Stock mínimo/máximo por bodega (no incluye existencias). */
    public function getStockBodegasParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT p.codigo AS codigo_producto, b.nombre AS bodega,
                       pb.stock_minimo, pb.stock_maximo
                FROM productos_bodegas pb
                JOIN productos p ON p.id = pb.id_producto
                JOIN bodegas   b ON b.id = pb.id_bodega
                WHERE pb.id_empresa = :id_empresa AND pb.eliminado = false
                  AND p.eliminado = false AND b.eliminado = false
                  AND p.tipo_produccion <> '02'
                ORDER BY p.codigo, b.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Homologaciones con el RUC/cédula del proveedor (no su nombre). */
    public function getHomologacionesParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT p.codigo AS codigo_producto, pr.identificacion AS ruc_proveedor,
                       ph.codigo_proveedor
                FROM productos_homologacion ph
                JOIN productos    p  ON p.id  = ph.id_producto
                JOIN proveedores  pr ON pr.id = ph.id_proveedor
                WHERE ph.id_empresa = :id_empresa AND ph.eliminado = false
                  AND p.eliminado = false AND pr.eliminado = false
                ORDER BY p.codigo, pr.identificacion";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escrituras que ProductoService no cubre
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Reemplaza las homologaciones de un producto (mismo criterio que los sync*
     * de ProductoRepository: se marcan como eliminadas y se reinsertan).
     *
     * @param array $homologaciones [['id_proveedor'=>int,'codigo_proveedor'=>string], ...]
     */
    public function syncHomologaciones(int $idProducto, int $idEmpresa, array $homologaciones, int $idUsuario): void
    {
        $stDel = $this->db->prepare(
            "UPDATE productos_homologacion
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
              WHERE id_producto = :id_p AND id_empresa = :id_e AND eliminado = false"
        );
        $stDel->execute([':uid' => $idUsuario, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        // La tabla tiene UNIQUE (id_empresa, id_proveedor, codigo_proveedor):
        // si la homologación ya existía (aunque marcada como eliminada) se
        // reactiva en lugar de duplicarla.
        $stIns = $this->db->prepare(
            "INSERT INTO productos_homologacion
                    (id_empresa, id_proveedor, codigo_proveedor, id_producto,
                     created_by, updated_by, updated_at, eliminado)
             VALUES (:id_e, :id_prov, :cod, :id_p, :uid, :uid, CURRENT_TIMESTAMP, false)
             ON CONFLICT (id_empresa, id_proveedor, codigo_proveedor)
             DO UPDATE SET id_producto = EXCLUDED.id_producto,
                           eliminado   = false,
                           deleted_at  = NULL,
                           deleted_by  = NULL,
                           updated_by  = EXCLUDED.updated_by,
                           updated_at  = CURRENT_TIMESTAMP"
        );

        foreach ($homologaciones as $h) {
            if (empty($h['id_proveedor']) || trim((string) $h['codigo_proveedor']) === '') {
                continue;
            }
            $stIns->execute([
                ':id_e'    => $idEmpresa,
                ':id_prov' => (int) $h['id_proveedor'],
                ':cod'     => trim((string) $h['codigo_proveedor']),
                ':id_p'    => $idProducto,
                ':uid'     => $idUsuario,
            ]);
        }
    }

    /** Crea una categoría y devuelve su id. */
    public function crearCategoria(string $nombre, int $idEmpresa, int $idUsuario): int
    {
        return $this->crearCatalogoNombre('categorias', $nombre, $idEmpresa, $idUsuario);
    }

    /** Crea una marca y devuelve su id. */
    public function crearMarca(string $nombre, int $idEmpresa, int $idUsuario): int
    {
        return $this->crearCatalogoNombre('marcas', $nombre, $idEmpresa, $idUsuario);
    }

    /**
     * Alta de catálogo simple. Si otro proceso lo creó entretanto, devuelve el
     * existente en vez de fallar.
     */
    private function crearCatalogoNombre(string $tabla, string $nombre, int $idEmpresa, int $idUsuario): int
    {
        $permitidas = ['categorias', 'marcas'];
        if (!in_array($tabla, $permitidas, true)) {
            throw new \InvalidArgumentException('Catálogo no permitido: ' . $tabla);
        }

        $nombre = trim($nombre);

        $stBuscar = $this->db->prepare(
            "SELECT id FROM {$tabla}
              WHERE id_empresa = :e AND LOWER(nombre) = LOWER(:n) AND eliminado = false
              LIMIT 1"
        );
        $stBuscar->execute([':e' => $idEmpresa, ':n' => $nombre]);
        $id = $stBuscar->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stIns = $this->db->prepare(
            "INSERT INTO {$tabla} (id_empresa, id_usuario, nombre, status, created_by, updated_by, eliminado)
             VALUES (:e, :u, :n, 1, :u, :u, false) RETURNING id"
        );
        $stIns->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':n' => $nombre]);
        return (int) $stIns->fetchColumn();
    }

    /** Datos de la empresa para rotular la plantilla. */
    public function getEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT id, COALESCE(NULLIF(nombre_comercial, ''), nombre) AS nombre, ruc
                FROM empresas WHERE id = :id LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Códigos de barras ya usados, indexados en minúsculas => código de producto.
     * Sirve para detectar choques de código de barras entre productos distintos.
     */
    public function getMapaCodigosBarras(int $idEmpresa): array
    {
        $sql = "SELECT codigo, codigo_barras
                FROM productos
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND codigo_barras IS NOT NULL AND TRIM(codigo_barras) <> ''";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[mb_strtolower(trim($row['codigo_barras']))] = trim($row['codigo']);
        }
        return $mapa;
    }
}
