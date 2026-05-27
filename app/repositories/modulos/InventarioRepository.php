<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class InventarioRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('inventario_kardex');
    }

    // ────────────────────────────────────────────────────────────────
    // STOCK ACTUAL
    // ────────────────────────────────────────────────────────────────

    /**
     * Obtiene el stock actual real sumando todos los movimientos del Kardex.
     * Es la fuente de verdad absoluta para validaciones.
     * @param int|null $excludeRefId ID de referencia a excluir (para ediciones)
     * @param string|null $excludeRefTipo Tipo de referencia a excluir (para ediciones)
     */
    public function getStockActual(int $idProducto, int $idBodega, int $idEmpresa, ?int $excludeRefId = null, ?string $excludeRefTipo = null, ?string $lote = null): float
    {
        $whereExcluir = "";
        $params = [':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];

        if ($excludeRefId !== null && $excludeRefTipo !== null) {
            $whereExcluir = " AND NOT (referencia_id = :erid AND referencia_tipo = :ertipo)";
            $params[':erid'] = $excludeRefId;
            $params[':ertipo'] = $excludeRefTipo;
        }

        $whereLote = "";
        if ($lote !== null && $lote !== '') {
            if ($lote === 'sin_lote') {
                $whereLote = " AND (numero_lote IS NULL OR numero_lote = '' OR numero_lote = 'sin_lote')";
            } else {
                $whereLote = " AND numero_lote = :lote";
                $params[':lote'] = $lote;
            }
        }

        $sql = "SELECT ROUND(COALESCE(SUM(cantidad), 0), 2) 
                FROM inventario_kardex 
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b AND eliminado = false $whereExcluir $whereLote";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (float) $st->fetchColumn();
    }
    
    /**
     * Obtiene el stock actual registrado en la tabla de caché (denormalizada).
     * Útil para listados rápidos de stock resumen.
     */
    public function getStockCache(int $idProducto, int $idBodega, int $idEmpresa): float
    {
        $sql = "SELECT stock_actual FROM productos_bodegas
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    public function getStockLote(int $idProducto, int $idBodega, int $idEmpresa, string $lote, ?int $excludeRefId = null, ?string $excludeRefTipo = null): float
    {
        $whereExcluir = "";
        $params = [':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];

        if ($excludeRefId !== null && $excludeRefTipo !== null) {
            $whereExcluir = " AND NOT (referencia_id = :erid AND referencia_tipo = :ertipo)";
            $params[':erid'] = $excludeRefId;
            $params[':ertipo'] = $excludeRefTipo;
        }

        if ($lote === 'sin_lote') {
            $whereLote = " AND (numero_lote IS NULL OR numero_lote = '' OR numero_lote = 'sin_lote')";
        } else {
            $whereLote = " AND numero_lote = :l";
            $params[':l'] = $lote;
        }

        $sql = "SELECT ROUND(COALESCE(SUM(cantidad), 0), 2) FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b 
                  AND eliminado = false $whereLote $whereExcluir";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (float) ($st->fetchColumn() ?: 0);
    }

    public function actualizarStock(int $idProducto, int $idBodega, int $idEmpresa, float $nuevoStock, int $userId): void
    {
        $sql = "INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_actual, created_by, updated_by)
                VALUES (:e, :p, :b, :stock, :uid, :uid)
                ON CONFLICT (id_producto, id_bodega) 
                DO UPDATE SET 
                    stock_actual = EXCLUDED.stock_actual,
                    updated_by = EXCLUDED.updated_by,
                    updated_at = CURRENT_TIMESTAMP,
                    eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega,
            ':stock' => $nuevoStock, ':uid' => $userId
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // KARDEX MOVIMIENTOS
    // ────────────────────────────────────────────────────────────────

    /**
     * Busca el lote más antiguo (por FIFO) en una bodega específica.
     * @param bool $soloConStock Si es true, solo devuelve lotes con saldo > 0.
     */
    public function getLoteMasAntiguo(int $idProducto, int $idBodega, int $idEmpresa, ?string $soloLote = null, bool $soloConStock = true): ?array
    {
        $whereLote = $soloLote !== null ? "AND numero_lote = :lote" : "";
        $whereStock = $soloConStock ? "WHERE stock > 0" : "";
        
        $sql = "SELECT numero_lote, fecha_caducidad, nup
                FROM (
                    SELECT numero_lote, MAX(fecha_caducidad) as fecha_caducidad, nup, MIN(id) as first_id, SUM(cantidad) as stock
                    FROM inventario_kardex
                    WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b AND eliminado = false
                    $whereLote
                    GROUP BY numero_lote, nup
                ) t
                $whereStock
                ORDER BY fecha_caducidad ASC NULLS LAST, first_id ASC
                LIMIT 1";
        
        $params = [':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];
        if ($soloLote !== null) $params[':lote'] = $soloLote;

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function registrarMovimiento(array $data): int
    {
        // Verificar si existe la columna id_medida para compatibilidad
        $colsSql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'inventario_kardex' AND column_name = 'id_medida'";
        $hasMedida = (bool)$this->db->query($colsSql)->fetchColumn();

        $cols = "id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id, fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior, numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by";
        $vals = ":emp, :prod, :bod, :tipo, :ref_tipo, :ref_id, CURRENT_TIMESTAMP, :cant, :costo_u, :costo_t, :stock_ant, :stock_post, :lote, :cad, :nup, :obs, :uid, :uid";
        
        if ($hasMedida) {
            $cols .= ", id_medida";
            $vals .= ", :id_medida";
        }

        $sql = "INSERT INTO inventario_kardex ({$cols}) VALUES ({$vals}) RETURNING id";
        $st = $this->db->prepare($sql);
        
        $params = [
            ':emp'       => $data['id_empresa'],
            ':prod'      => $data['id_producto'],
            ':bod'       => $data['id_bodega'],
            ':tipo'      => $data['tipo_movimiento'],
            ':ref_tipo'  => $data['referencia_tipo']  ?? null,
            ':ref_id'    => $data['referencia_id']    ?? null,
            ':cant'      => $data['cantidad'],
            ':costo_u'   => $data['costo_unitario']  ?? 0,
            ':costo_t'   => $data['costo_total']     ?? 0,
            ':stock_ant' => $data['stock_anterior']  ?? 0,
            ':stock_post'=> $data['stock_posterior'] ?? 0,
            ':lote'      => $data['numero_lote']     ?? null,
            ':cad'       => $data['fecha_caducidad'] ?? null,
            ':nup'       => $data['nup']             ?? null,
            ':obs'       => $data['observaciones']   ?? null,
            ':uid'       => $data['id_usuario']
        ];

        if ($hasMedida) {
            $params[':id_medida'] = $data['id_medida'] ?? null;
        }

        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    /** Entradas ordenadas de más antigua a más nueva (FIFO) */
    public function getEntradasFIFO(int $idProducto, int $idBodega, int $idEmpresa): array
    {
        return $this->getEntradas($idProducto, $idBodega, $idEmpresa, 'ASC');
    }

    /** Entradas ordenadas de más nueva a más antigua (LIFO) */
    public function getEntradasLIFO(int $idProducto, int $idBodega, int $idEmpresa): array
    {
        return $this->getEntradas($idProducto, $idBodega, $idEmpresa, 'DESC');
    }

    private function getEntradas(int $idProducto, int $idBodega, int $idEmpresa, string $orden): array
    {
        // Entradas con stock residual > 0 (cantidad - salidas posteriores agrupadas)
        // Para simplificar: tomamos entradas directas con stock_posterior creciente
        $sql = "SELECT id, fecha_movimiento, cantidad, costo_unitario, numero_lote, fecha_caducidad, nup,
                       (cantidad - COALESCE(
                            (SELECT SUM(ABS(k2.cantidad))
                             FROM inventario_kardex k2
                             WHERE k2.id_empresa = k.id_empresa
                               AND k2.id_producto = k.id_producto
                               AND k2.id_bodega = k.id_bodega
                               AND k2.tipo_movimiento = 'salida'
                               AND k2.referencia_id IS DISTINCT FROM k.referencia_id
                               /* Simplificado: referencia a la entrada original en futuras versiones */
                               AND k2.eliminado = false
                            ), 0) 
                       ) AS stock_disponible
                FROM inventario_kardex k
                WHERE k.id_empresa = :e AND k.id_producto = :p AND k.id_bodega = :b
                  AND k.tipo_movimiento = 'entrada' AND k.eliminado = false
                ORDER BY k.fecha_movimiento {$orden}";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Costo promedio ponderado actual del producto en la bodega */
    public function getCostoPromedio(int $idProducto, int $idBodega, int $idEmpresa): float
    {
        $sql = "SELECT CASE WHEN SUM(cantidad) > 0
                    THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6)
                    ELSE 0 END AS costo_promedio
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b
                  AND tipo_movimiento = 'entrada' AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega]);
        return (float) ($st->fetchColumn() ?: 0);
    }



    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT k.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       b.nombre AS bodega_nombre, u.nombre AS usuario_nombre
                FROM inventario_kardex k
                INNER JOIN productos p ON p.id = k.id_producto
                INNER JOIN bodegas   b ON b.id = k.id_bodega
                LEFT JOIN unidades_medida um ON um.id = k.id_medida
                LEFT JOIN usuarios   u ON u.id = k.created_by
                WHERE k.id = :id AND k.id_empresa = :e AND k.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getMovimientosPorReferencia(string $tipo, int $id, int $idEmpresa): array
    {
        $sql = "SELECT k.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       b.nombre AS bodega_nombre, um.abreviatura AS medida_abreviatura
                FROM inventario_kardex k
                INNER JOIN productos p ON p.id = k.id_producto
                INNER JOIN bodegas   b ON b.id = k.id_bodega
                LEFT JOIN unidades_medida um ON um.id = k.id_medida
                WHERE k.referencia_tipo = :tipo AND k.referencia_id = :id 
                  AND k.id_empresa = :e AND k.eliminado = false
                ORDER BY k.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':tipo' => $tipo, ':id' => $id, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getKardex(int $idEmpresa, array $filtros = [], int $page = 1, int $perPage = 50): array
    {
        $params = [':e' => $idEmpresa];
        $where  = 'WHERE k.id_empresa = :e AND k.eliminado = false';

        if (!empty($filtros['buscar'])) {
            $where .= ' AND (p.nombre ILIKE :b OR p.codigo ILIKE :b OR k.observaciones ILIKE :b OR b.nombre ILIKE :b)';
            $params[':b'] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['id_producto'])) {
            $where .= ' AND k.id_producto = :prod';
            $params[':prod'] = (int)$filtros['id_producto'];
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= ' AND k.id_bodega = :bod';
            $params[':bod'] = (int)$filtros['id_bodega'];
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $where .= ' AND k.tipo_movimiento = :tipo';
            $params[':tipo'] = $filtros['tipo_movimiento'];
        }
        if (!empty($filtros['desde'])) {
            $where .= ' AND k.fecha_movimiento >= :desde';
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where .= ' AND k.fecha_movimiento <= :hasta';
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_usuario'])) {
            $where .= ' AND k.created_by = :uid_filtro';
            $params[':uid_filtro'] = (int)$filtros['id_usuario'];
        }
        if (!empty($filtros['numero_lote'])) {
            $where .= ' AND k.numero_lote ILIKE :lote';
            $params[':lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $where .= ' AND k.nup ILIKE :nup';
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['referencia_tipo'])) {
            $where .= ' AND k.referencia_tipo = :ref_tipo';
            $params[':ref_tipo'] = $filtros['referencia_tipo'];
        }
        if (!empty($filtros['id_medida'])) {
            $where .= ' AND k.id_medida = :id_m';
            $params[':id_m'] = (int)$filtros['id_medida'];
        }

        $sqlCount = "SELECT COUNT(*), COALESCE(SUM(k.cantidad), 0) as total_cantidad
                     FROM inventario_kardex k
                     INNER JOIN productos p ON p.id = k.id_producto
                     INNER JOIN bodegas b ON b.id = k.id_bodega
                     LEFT JOIN unidades_medida um ON um.id = k.id_medida
                     $where";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $resCount = $stCount->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($resCount['count'] ?? 0);
        $saldo = (float) ($resCount['total_cantidad'] ?? 0);

        // Mapeo selectivo para ordenamiento
        $colMap = [
            'fecha_movimiento' => 'k.fecha_movimiento',
            'producto_nombre'  => 'p.nombre',
            'bodega_nombre'    => 'b.nombre',
            'tipo_movimiento'  => 'k.tipo_movimiento',
            'cantidad'         => 'k.cantidad',
            'numero_lote'      => 'k.numero_lote',
            'fecha_caducidad'  => 'k.fecha_caducidad',
            'nup'              => 'k.nup',
            'usuario_nombre'   => 'u.nombre',
            'observaciones'    => 'k.observaciones'
        ];
        $sort = $colMap[$filtros['sort'] ?? ''] ?? 'k.fecha_movimiento';
        $dir  = strtoupper($filtros['dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT k.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       b.nombre AS bodega_nombre, u.nombre AS usuario_nombre,
                       um.nombre AS nombre_medida, um.abreviatura AS abreviatura_medida
                FROM inventario_kardex k
                INNER JOIN productos p ON p.id = k.id_producto
                INNER JOIN bodegas   b ON b.id = k.id_bodega
                LEFT JOIN unidades_medida um ON um.id = k.id_medida
                LEFT JOIN usuarios   u ON u.id = k.created_by
                $where
                ORDER BY $sort $dir, k.id DESC
                LIMIT $perPage OFFSET $offset";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return [
            'total' => $total, 
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'saldo' => $saldo
        ];
    }

    public function getStockResumen(int $idEmpresa, array $filtros = [], int $page = 1, int $perPage = 20): array
    {
        $params = [':e' => $idEmpresa];
        $where  = "WHERE p.id_empresa = :e AND p.eliminado = false AND p.inventariable = true";

        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :b OR p.codigo ILIKE :b OR b.nombre ILIKE :b)";
            $params[':b'] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND b.id = :id_bod";
            $params[':id_bod'] = (int) $filtros['id_bodega'];
        }

        // Conteo total para paginación
        $sqlCount = "SELECT COUNT(*)
                     FROM productos p
                     INNER JOIN productos_bodegas pb ON pb.id_producto = p.id AND pb.id_empresa = p.id_empresa AND pb.eliminado = false
                     INNER JOIN bodegas b ON b.id = pb.id_bodega AND b.eliminado = false
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Ordenamiento
        $colMap = [
            'codigo' => 'p.codigo',
            'nombre' => 'p.nombre',
            'bodega' => 'b.nombre',
            'stock'  => 'pb.stock_actual',
            'minimo' => 'pb.stock_minimo'
        ];
        $sort = $colMap[$filtros['sort'] ?? ''] ?? 'p.nombre';
        $dir  = strtoupper($filtros['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.id, p.codigo, p.nombre, p.tipo_produccion, p.inventariable,
                       b.id AS id_bodega, b.nombre AS bodega_nombre,
                       COALESCE(pb.stock_actual, 0) AS stock_actual,
                       COALESCE(pb.stock_minimo, 0) AS stock_minimo,
                       COALESCE(pb.stock_maximo, 0) AS stock_maximo
                FROM productos p
                INNER JOIN productos_bodegas pb ON pb.id_producto = p.id AND pb.id_empresa = p.id_empresa AND pb.eliminado = false
                INNER JOIN bodegas b ON b.id = pb.id_bodega AND b.eliminado = false
                $where
                ORDER BY $sort $dir, b.nombre
                LIMIT $perPage OFFSET $offset";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return [
            'total' => $total,
            'rows'  => $st->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public function getResumenEstadistico(int $idEmpresa): array
    {
        $sql = "SELECT 
                    COUNT(*) FILTER (WHERE pb.stock_actual <= 0)::int as quiebre,
                    COUNT(*) FILTER (WHERE pb.stock_actual > 0 AND pb.stock_actual <= pb.stock_minimo)::int as alerta,
                    COALESCE(SUM(pb.stock_actual * (
                        SELECT COALESCE(k.costo_unitario, 0)
                        FROM inventario_kardex k
                        WHERE k.id_producto = p.id AND k.id_empresa = :e AND k.eliminado = false
                        ORDER BY k.fecha_movimiento DESC, k.id DESC
                        LIMIT 1
                    )), 0)::float as valor_total
                FROM productos p
                INNER JOIN productos_bodegas pb ON pb.id_producto = p.id AND pb.id_empresa = :e AND pb.eliminado = false
                WHERE p.id_empresa = :e AND p.eliminado = false AND p.inventariable = true";
        
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['quiebre' => 0, 'alerta' => 0, 'valor_total' => 0];
    }

    /**
     * Obtiene los lotes con stock disponible para un producto en una bodega específica.
     */
    public function getLotesDisponibles(int $idProducto, int $idBodega, int $idEmpresa, ?int $excludeRefId = null, ?string $excludeRefTipo = null): array
    {
        $whereExcluir = "";
        $params = [':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];

        if ($excludeRefId !== null && $excludeRefTipo !== null) {
            $whereExcluir = " AND NOT (referencia_id = :erid AND referencia_tipo = :ertipo)";
            $params[':erid'] = $excludeRefId;
            $params[':ertipo'] = $excludeRefTipo;
        }

        $sql = "SELECT COALESCE(numero_lote, 'sin_lote') as numero_lote, 
                       MAX(fecha_caducidad) as fecha_caducidad, 
                       ROUND(SUM(cantidad), 2) as stock_lote
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b AND eliminado = false 
                  $whereExcluir
                GROUP BY COALESCE(numero_lote, 'sin_lote')
                HAVING ROUND(SUM(cantidad), 2) > 0
                ORDER BY MAX(fecha_caducidad) ASC NULLS LAST, numero_lote ASC";
        
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // insertarAjuste es un alias de registrarMovimiento para compatibilidad
    public function insertarAjuste(array $data): int
    {
        return $this->registrarMovimiento($data);
    }

    public function getTiposReferencia(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT referencia_tipo FROM inventario_kardex 
                WHERE id_empresa = :e AND referencia_tipo IS NOT NULL AND eliminado = false
                ORDER BY referencia_tipo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getUsuariosConMovimientos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre 
                FROM usuarios u
                INNER JOIN inventario_kardex k ON k.created_by = u.id
                WHERE k.id_empresa = :e AND k.eliminado = false
                ORDER BY u.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
