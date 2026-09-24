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
     * Stock actual de varios productos a la vez en una sola bodega (una sola
     * consulta, evita N+1). Devuelve [id_producto => stock]; los productos
     * sin ningún movimiento no aparecen en el resultado (asumir 0).
     * @param int|null    $excludeRefId   ID de referencia a excluir (para ediciones)
     * @param string|null $excludeRefTipo Tipo de referencia a excluir (para ediciones)
     */
    public function getStockActualPorProductos(
        array $idProductos,
        int $idBodega,
        int $idEmpresa,
        ?int $excludeRefId = null,
        ?string $excludeRefTipo = null
    ): array {
        $idProductos = array_values(array_unique(array_map('intval', $idProductos)));
        if (empty($idProductos)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($idProductos), '?'));
        $params = array_merge([$idBodega, $idEmpresa, $idEmpresa], $idProductos);

        // Al editar un documento, su propio movimiento no debe restar del saldo que se
        // muestra: el saldo relevante es el que habría "sin este documento" (igual que
        // hace getStockActual() / getLotesDisponibles()).
        $whereExcluir = "";
        if ($excludeRefId !== null && $excludeRefTipo !== null) {
            $whereExcluir = " AND NOT (referencia_id = ? AND referencia_tipo = ?)";
            $params[] = $excludeRefId;
            $params[] = $excludeRefTipo;
        }

        $sql = "SELECT id_producto, COALESCE(SUM(cantidad), 0) AS stock
                FROM inventario_kardex
                WHERE id_bodega = ? AND id_empresa = ? AND eliminado = false
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                  AND id_producto IN ($placeholders)
                  $whereExcluir
                GROUP BY id_producto";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $resultado = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resultado[(int) $row['id_producto']] = (float) $row['stock'];
        }
        return $resultado;
    }

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
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b AND eliminado = false 
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                  $whereExcluir $whereLote";
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
                  AND eliminado = false 
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                  $whereLote $whereExcluir";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (float) ($st->fetchColumn() ?: 0);
    }

    /** Id de la factura de venta a partir de su número «001-001-000000123» (null si no existe). */
    public function getIdVentaPorNumero(int $idEmpresa, string $numDoc): ?int
    {
        if (!preg_match('/^(\d+)-(\d+)-(\d+)$/', trim($numDoc), $m)) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT id FROM ventas_cabecera
             WHERE id_empresa = :e AND eliminado = false
               AND LPAD(establecimiento::text, 3, '0') = :est
               AND LPAD(punto_emision::text, 3, '0') = :pto
               AND LPAD(secuencial::text, 9, '0') = :sec
             ORDER BY id DESC LIMIT 1"
        );
        $st->execute([
            ':e'   => $idEmpresa,
            ':est' => str_pad($m[1], 3, '0', STR_PAD_LEFT),
            ':pto' => str_pad($m[2], 3, '0', STR_PAD_LEFT),
            ':sec' => str_pad($m[3], 9, '0', STR_PAD_LEFT),
        ]);
        $id = $st->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Lo que la factura sacó de inventario para un producto, con su lote/caducidad/NUP. */
    public function getSalidasVentaProducto(int $idEmpresa, int $idProducto, int $idVenta): array
    {
        $st = $this->db->prepare(
            "SELECT numero_lote, fecha_caducidad, nup, ABS(cantidad) AS cantidad
             FROM inventario_kardex
             WHERE id_empresa = :e AND id_producto = :p AND referencia_id = :v
               AND referencia_tipo = 'factura_venta' AND tipo_movimiento = 'salida'
               AND eliminado = false
             ORDER BY id"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':v' => $idVenta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Lote/caducidad/NUP escritos en la línea de la factura (respaldo cuando el kardex no lo trae). */
    public function getLotesDetalleVenta(int $idProducto, int $idVenta): array
    {
        $st = $this->db->prepare(
            "SELECT numero_lote, fecha_caducidad, nup, cantidad
             FROM ventas_detalle
             WHERE id_venta = :v AND id_producto = :p
             ORDER BY id"
        );
        $st->execute([':v' => $idVenta, ':p' => $idProducto]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Líneas de una factura por id, con su producto y lote/NUP (enlace de cada ítem de una NC). */
    public function getLineasVenta(int $idVenta, array $idsDetalle): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsDetalle))));
        if (!$ids) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare(
            "SELECT id, id_producto, numero_lote, nup, id_unidad_medida
             FROM ventas_detalle
             WHERE id_venta = ? AND id IN ($marcas)"
        );
        $st->execute(array_merge([$idVenta], $ids));

        $lineas = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $l) {
            $lineas[(int) $l['id']] = $l;
        }
        return $lineas;
    }

    /** Lo ya devuelto por notas de crédito vigentes del mismo documento, por lote y NUP. */
    public function getDevueltoPorNcDeDocumento(int $idEmpresa, int $idProducto, string $numDoc): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(numero_lote, '') AS lote, COALESCE(nup, '') AS nup, SUM(cantidad) AS cantidad
             FROM inventario_kardex
             WHERE id_empresa = :e AND id_producto = :p
               AND referencia_tipo = 'nota_credito' AND eliminado = false
               AND referencia_id IN (
                   SELECT id FROM notas_credito_cabecera
                   WHERE id_empresa = :e2 AND num_doc_modificado = :n AND eliminado = false
               )
             GROUP BY 1, 2"
        );
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':e2' => $idEmpresa, ':n' => $numDoc]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Bloqueo transaccional (se libera solo al COMMIT/ROLLBACK) por producto+bodega.
     * DEBE llamarse antes de leer el stock (getStockActual/getStockCache) en cualquier
     * secuencia que luego escriba (registrarMovimiento + actualizarStock), para que dos
     * movimientos concurrentes del mismo producto/bodega no lean el mismo stock de partida
     * y uno sobreescriba silenciosamente el resultado del otro en productos_bodegas.stock_actual.
     */
    public function lockStock(int $idProducto, int $idBodega, int $idEmpresa): void
    {
        $sql = "SELECT pg_advisory_xact_lock(hashtext('stock:' || :e || ':' || :p || ':' || :b))";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega]);
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

    /** Fila actual de productos_bodegas de un producto/bodega (para el "antes" de la auditoría). */
    public function getProductoBodega(int $idProducto, int $idBodega, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM productos_bodegas
                WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Actualiza el mínimo/máximo de stock de un producto en una bodega puntual (edición en línea desde reportes). */
    public function actualizarMinMax(int $idProducto, int $idBodega, int $idEmpresa, float $stockMinimo, float $stockMaximo, int $userId): void
    {
        $sql = "UPDATE productos_bodegas
                SET stock_minimo = :min, stock_maximo = :max, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':min' => $stockMinimo, ':max' => $stockMaximo,
            ':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa, ':uid' => $userId,
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
        
        // GROUP BY solo numero_lote (no nup): el NUP es opcional (obligatorio_nup),
        // y agrupar también por él fragmentaba el saldo del lote en una fila por
        // cada serial — un lote con stock repartido entre varios NUP podía
        // aparecer sin saldo suficiente en ninguna fila individual, aunque el
        // lote completo sí tuviera stock.
        //
        // Los lotes REALES van primero y el grupo sin lote (NULL / '' / 'SIN LOTE')
        // queda al final: ese grupo arrastra como caducidad las fechas de ventas
        // anteriores (antes se grababa la fecha del día cuando la salida no traía
        // una real), así que parecía el próximo a vencer y se elegía antes que un
        // lote real con saldo. Entre lotes reales manda la caducidad, como siempre.
        $sql = "SELECT numero_lote, fecha_caducidad, nup
                FROM (
                    SELECT numero_lote,
                           (numero_lote IS NULL OR numero_lote = '' OR UPPER(numero_lote) IN ('SIN LOTE', 'SIN_LOTE')) AS sin_lote,
                           MAX(fecha_caducidad) as fecha_caducidad, MIN(nup) as nup, MIN(id) as first_id, SUM(cantidad) as stock
                    FROM inventario_kardex
                    WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b AND eliminado = false
                      AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)
                    $whereLote
                    GROUP BY numero_lote
                ) t
                $whereStock
                ORDER BY sin_lote ASC, fecha_caducidad ASC NULLS LAST, first_id ASC
                LIMIT 1";

        $params = [':e' => $idEmpresa, ':e2' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];
        if ($soloLote !== null) $params[':lote'] = $soloLote;

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function registrarMovimiento(array $data): int
    {
        // Verificar si existe la columna id_medida para compatibilidad. Una vez por proceso: la
        // consulta a information_schema costaba más que el propio INSERT y se hacía por movimiento.
        static $hasMedida = null;
        if ($hasMedida === null) {
            $colsSql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'inventario_kardex' AND column_name = 'id_medida'";
            $hasMedida = (bool)$this->db->query($colsSql)->fetchColumn();
        }

        // fecha_movimiento: por defecto CURRENT_TIMESTAMP (comportamiento de siempre, sin
        // parámetro de por medio). Si el llamador pasa 'fecha_movimiento' explícitamente
        // (p. ej. una corrección retroactiva de kardex con la fecha real del documento
        // original), se usa esa fecha en su lugar — único caso donde se aparta de "ahora".
        $fechaMovExpr = !empty($data['fecha_movimiento']) ? ':fecha_mov' : 'CURRENT_TIMESTAMP';

        // tipo_ambiente DEBE guardarse con el ambiente actual de la empresa: el
        // listado del kardex filtra por él. Si se deja el DEFAULT ('1'), los
        // movimientos de una empresa en producción ('2') quedan invisibles.
        $cols = "id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id, fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior, numero_lote, fecha_caducidad, nup, observaciones, created_by, updated_by, tipo_ambiente";
        $vals = ":emp, :prod, :bod, :tipo, :ref_tipo, :ref_id, {$fechaMovExpr}, :cant, :costo_u, :costo_t, :stock_ant, :stock_post, :lote, :cad, :nup, :obs, :uid, :uid, (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp_amb)";

        if ($hasMedida) {
            $cols .= ", id_medida";
            $vals .= ", :id_medida";
        }

        $sql = "INSERT INTO inventario_kardex ({$cols}) VALUES ({$vals}) RETURNING id";
        $st = $this->db->prepare($sql);

        $params = [
            ':emp'       => $data['id_empresa'],
            ':emp_amb'   => $data['id_empresa'],
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

        if (!empty($data['fecha_movimiento'])) {
            $params[':fecha_mov'] = $data['fecha_movimiento'];
        }

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

    /**
     * Costo unitario al que salió un producto con un documento (sus salidas vigentes, p. ej. las de
     * una consignación). Lo usa quien devuelve esas unidades a bodega, para que entren al mismo
     * costo con que salieron y no bajen el costo promedio. 0 si no hay salidas con costo.
     */
    public function getCostoUnitarioSalidas(string $referenciaTipo, int $referenciaId, int $idProducto, int $idEmpresa): float
    {
        $sql = "SELECT COALESCE(SUM(costo_total), 0) / NULLIF(SUM(ABS(cantidad)), 0)
                FROM inventario_kardex
                WHERE id_empresa = :e AND referencia_tipo = :tipo AND referencia_id = :ref
                  AND id_producto = :p AND tipo_movimiento = 'salida' AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':tipo' => $referenciaTipo, ':ref' => $referenciaId, ':p' => $idProducto]);
        return round((float) ($st->fetchColumn() ?: 0), 6);
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

    /** Igual que find(), pero incluye movimientos anulados (eliminado=true) — para verlos/habilitarlos. */
    public function findIncluyendoEliminados(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT k.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       b.nombre AS bodega_nombre, u.nombre AS usuario_nombre,
                       um.nombre AS nombre_medida, um.abreviatura AS abreviatura_medida,
                       ua.nombre AS anulado_por
                FROM inventario_kardex k
                INNER JOIN productos p ON p.id = k.id_producto
                INNER JOIN bodegas   b ON b.id = k.id_bodega
                LEFT JOIN unidades_medida um ON um.id = k.id_medida
                LEFT JOIN usuarios   u ON u.id = k.created_by
                LEFT JOIN usuarios   ua ON ua.id = k.deleted_by
                WHERE k.id = :id AND k.id_empresa = :e";
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

    /**
     * ¿Ya se usó lo que ingresó un documento? Antes de anular sus movimientos (p. ej. una
     * carga de inventario aprobada) se recorre en orden cronológico el kardex de cada
     * producto/bodega que tocó y se recalcula el saldo que habría habido SIN el documento,
     * desde el momento en que se aplicó.
     *
     * Devuelve una fila por cada grupo en conflicto, con el primer movimiento que lo provoca:
     *  - nivel 'producto' (producto+bodega), 'lote' y 'nup' (solo los lotes y series que trae
     *    el documento): si el documento ingresó stock y sin él el saldo habría quedado
     *    negativo, esas unidades ya salieron (venta, consignación, transferencia…). Si el
     *    saldo ya era negativo al aplicarse, el movimiento devuelto es el del propio documento.
     *  - nivel 'nup' de un documento que SACÓ la serie: si sin él la serie quedaría más de una
     *    vez en stock (volvió a entrar después).
     *
     * Solo cuentan los movimientos vigentes del mismo ambiente (pruebas/producción) que los
     * del documento. Llamarlo con el stock bloqueado (lockStock) si a continuación se anula.
     *
     * @return array<int, array<string, mixed>> nivel, id_producto, id_bodega, clave (lote o
     *         NUP), cantidad_doc, saldo_sin, datos del movimiento (id_movimiento,
     *         fecha_movimiento, referencia_tipo, referencia_id, observaciones) y nombres.
     */
    public function getConsumoPosteriorPorReferencia(string $referenciaTipo, int $referenciaId, int $idEmpresa): array
    {
        $sql = "WITH doc AS (
                    SELECT id_producto, id_bodega, tipo_ambiente,
                           NULLIF(TRIM(numero_lote), '') AS lote,
                           NULLIF(TRIM(nup), '')         AS nup
                    FROM inventario_kardex
                    WHERE id_empresa = :e AND referencia_tipo = :tipo AND referencia_id = :ref
                      AND eliminado = false
                ),
                grupos AS (
                    SELECT 'producto'::text AS nivel, id_producto, id_bodega, tipo_ambiente, ''::text AS clave FROM doc
                    UNION
                    SELECT 'lote', id_producto, id_bodega, tipo_ambiente, lote FROM doc WHERE lote IS NOT NULL
                    UNION
                    SELECT 'nup', id_producto, id_bodega, tipo_ambiente, nup FROM doc WHERE nup IS NOT NULL
                ),
                movs AS (
                    SELECT g.nivel, g.id_producto, g.id_bodega, g.clave,
                           k.id, k.fecha_movimiento, k.cantidad, k.referencia_tipo, k.referencia_id, k.observaciones,
                           (k.referencia_tipo = :tipo2 AND k.referencia_id = :ref2) AS es_doc
                    FROM grupos g
                    JOIN inventario_kardex k
                      ON k.id_empresa = :e2 AND k.id_producto = g.id_producto AND k.id_bodega = g.id_bodega
                     AND k.tipo_ambiente = g.tipo_ambiente AND k.eliminado = false
                     AND (g.nivel = 'producto'
                          OR (g.nivel = 'lote' AND TRIM(k.numero_lote) = g.clave)
                          OR (g.nivel = 'nup'  AND TRIM(k.nup) = g.clave))
                ),
                saldos AS (
                    SELECT m.*,
                           SUM(CASE WHEN m.es_doc THEN 0 ELSE m.cantidad END) OVER w AS saldo_sin,
                           BOOL_OR(m.es_doc) OVER w AS desde_doc,
                           SUM(CASE WHEN m.es_doc THEN m.cantidad ELSE 0 END)
                               OVER (PARTITION BY m.nivel, m.id_producto, m.id_bodega, m.clave) AS cantidad_doc
                    FROM movs m
                    WINDOW w AS (PARTITION BY m.nivel, m.id_producto, m.id_bodega, m.clave
                                 ORDER BY m.fecha_movimiento, m.id)
                )
                SELECT DISTINCT ON (s.nivel, s.id_producto, s.id_bodega, s.clave)
                       s.nivel, s.id_producto, s.id_bodega, s.clave, s.cantidad_doc,
                       ROUND(s.saldo_sin, 4) AS saldo_sin,
                       s.id AS id_movimiento, s.fecha_movimiento, s.cantidad,
                       s.referencia_tipo, s.referencia_id, s.observaciones,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre, b.nombre AS bodega_nombre
                FROM saldos s
                LEFT JOIN productos p ON p.id = s.id_producto
                LEFT JOIN bodegas   b ON b.id = s.id_bodega
                WHERE s.desde_doc
                  AND ((s.cantidad_doc > 0 AND s.saldo_sin < -0.005)
                    OR (s.nivel = 'nup' AND s.cantidad_doc < 0 AND s.saldo_sin > 1.005))
                ORDER BY s.nivel, s.id_producto, s.id_bodega, s.clave, s.fecha_movimiento, s.id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $idEmpresa,
            ':e2'    => $idEmpresa,
            ':tipo'  => $referenciaTipo,
            ':tipo2' => $referenciaTipo,
            ':ref'   => $referenciaId,
            ':ref2'  => $referenciaId,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getKardex(int $idEmpresa, array $filtros = [], int $page = 1, int $perPage = 50): array
    {
        $params = [':e' => $idEmpresa];
        // "Ver anulados" muestra SOLO los movimientos anulados (eliminado=true), como una
        // vista separada — el listado normal sigue mostrando solo los activos por defecto.
        $condEliminado = !empty($filtros['ver_anulados']) ? 'k.eliminado = true' : 'k.eliminado = false';
        $where  = "WHERE k.id_empresa = :e AND $condEliminado AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)";

        // Buscador con sintaxis de tokens (texto libre + filtros clave:valor).
        // Ver §9 CLAUDE.md — mismo patrón que Proveedores.
        $parsed = \App\Helpers\FiltrosBusqueda::parsear((string)($filtros['buscar'] ?? ''));
        if ($parsed['texto_libre'] !== '') {
            // Texto libre del listado de Movimientos de Inventario (único llamador que manda
            // texto): fecha, producto (nombre y código), cantidad, lote, caducidad, NUP y
            // observaciones — estas llevan el nº del documento de origen ("Salida por Factura
            // # 001-101-000000127"), así que buscar ese número trae sus movimientos.
            //
            // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar:
            //   - Tipo (entrada/salida) y Origen (referencia_tipo) → modal de filtros
            //     (decisión anterior).
            //   - Bodega, medida y usuario (18-09-2026; aunque son columnas, el usuario los
            //     quiso fuera) → selectores del modal y filtros `bodega:`, `medida:`, `usuario:`.
            //   - Código auxiliar y código de barras del producto (no se ven en la tabla) →
            //     filtros `auxiliar:` y `barras:`.
            //
            // Rendimiento (18-09-2026). El kardex es la tabla más grande del sistema, y antes
            // cada búsqueda formateaba fecha, caducidad y cantidad como texto en TODOS los
            // movimientos de la empresa aunque se escribieran letras, y comparaba el producto
            // movimiento por movimiento. Ahora esas tres columnas solo se evalúan si la palabra
            // puede ser una fecha o un número, y el producto se resuelve UNA vez por palabra en
            // su propia tabla (conjunto `col` + `sql`). Encuentra lo mismo.
            $fecha  = \App\Helpers\FiltrosBusqueda::SI_FECHA;
            $numero = \App\Helpers\FiltrosBusqueda::SI_NUMERO;
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'k.numero_lote',                                        // Lote
                    'k.nup',                                                // NUP/Serial
                    'k.observaciones',                                      // Obs.
                    // Producto: nombre y código (los dos se ven en la celda).
                    ['col' => "CONCAT_WS(' ', px.codigo, px.nombre)",
                     'sql' => "k.id_producto IN (SELECT px.id FROM productos px WHERE px.id_empresa = :e AND {cond})"],
                    ['sql' => "TO_CHAR(k.fecha_movimiento, 'DD-MM-YYYY HH24:MI:SS')", 'si' => $fecha], // Fecha (como se muestra)
                    // La misma fecha en formato ISO: "2026-08" encuentra el mes, igual que en
                    // Facturas, Consignaciones y Pedidos.
                    ['sql' => "TO_CHAR(k.fecha_movimiento, 'YYYY-MM-DD')", 'si' => $fecha],
                    ['sql' => "TO_CHAR(k.fecha_caducidad, 'DD-MM-YYYY')", 'si' => $fecha],            // Caducidad
                    ['sql' => 'ROUND(ABS(k.cantidad), 2)', 'si' => $numero],                           // Cant.
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'producto' => 'p.nombre',
                'codigo'   => 'p.codigo',
                'lote'     => 'k.numero_lote',
                'nup'      => 'k.nup',
                'obs'      => 'k.observaciones',
                'bodega'   => 'b.nombre',
                // Salieron del texto libre el 18-09-2026: estos filtros son ahora la forma de
                // buscarlos a propósito (los JOIN um y u ya están en el conteo y en las filas).
                'medida'          => 'um.nombre',
                'usuario'         => 'u.nombre',
                'barras'          => 'p.codigo_barras',
                'codigo_barras'   => 'p.codigo_barras',
                'auxiliar'        => 'p.codigo_auxiliar',
                'codigo_auxiliar' => 'p.codigo_auxiliar',
            ],
            'exacto' => [
                'tipo'       => 'k.tipo_movimiento',
                'origen'     => 'k.referencia_tipo',
                'id_bodega'  => 'k.id_bodega',
                'id_usuario' => 'k.created_by',
                'id_medida'  => 'k.id_medida',
                // Selects nuevos del modal de filtros.
                'id_categoria' => 'p.id_categoria',
                'con_lote'     => "CASE WHEN NULLIF(TRIM(COALESCE(k.numero_lote, '')), '') IS NULL THEN 'no' ELSE 'si' END",
                'con_nup'      => "CASE WHEN NULLIF(TRIM(COALESCE(k.nup, '')), '') IS NULL THEN 'no' ELSE 'si' END",
            ],
            'fecha' => [
                'fecha'      => 'k.fecha_movimiento',
                'caducidad'  => 'k.fecha_caducidad',
                'registro'   => 'k.created_at',
            ],
            'numerico' => [
                // La columna Cant. se muestra sin signo (el signo lo da el tipo).
                'cantidad'       => 'ABS(k.cantidad)',
                'costo_unitario' => 'k.costo_unitario',
                'costo_total'    => 'ABS(k.costo_total)',
            ],
        ]);

        // Filtros explícitos (compatibilidad con exportaciones y getKardexAjax)
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

        // Mismos JOIN que las filas: el texto libre usa también u (usuario).
        $sqlCount = "SELECT COUNT(*), COALESCE(SUM(k.cantidad), 0) as total_cantidad
                     FROM inventario_kardex k
                     INNER JOIN productos p ON p.id = k.id_producto
                     INNER JOIN bodegas b ON b.id = k.id_bodega
                     LEFT JOIN unidades_medida um ON um.id = k.id_medida
                     LEFT JOIN usuarios u ON u.id = k.created_by
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
                ORDER BY $sort $dir, k.id DESC" . ($perPage > 0 ? " LIMIT $perPage OFFSET $offset" : "");
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
                ORDER BY $sort $dir, b.nombre, p.id DESC" . ($perPage > 0 ? " LIMIT $perPage OFFSET $offset" : "");

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
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
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

    /**
     * Tipos de referencia (origen del movimiento) presentes en el kardex de la
     * empresa. Alimenta el <select> "Origen" de Inventario y del Reporte de
     * Inventarios, así que se ejecuta en CADA carga de esas páginas.
     *
     * Un "SELECT DISTINCT ... WHERE id_empresa = :e" recorre TODOS los
     * movimientos de la empresa para devolver ~10 valores: con 600.000 filas
     * eso medía ~140 ms y crece con el histórico. Aquí se usa el patrón
     * "loose index scan" (recursiva que salta al siguiente valor distinto
     * usando idx_kardex_referencia): hace tantos saltos como valores distintos
     * existen, no como filas hay. Medido en ~0,8 ms con esas mismas 600.000
     * filas. Requiere idx_kardex_referencia (id_empresa, referencia_tipo, …)
     * WHERE eliminado = false — ver database/indices_reporte_consignaciones.sql.
     *
     * SIN ese índice el salto no tiene atajo: cada paso relee todos los
     * movimientos de la empresa, y con ~10 tipos queda muy por encima del
     * DISTINCT. Por eso, si el índice no existe (SQL aún no aplicado en esa
     * base), se usa el DISTINCT de una sola pasada. Mismo resultado.
     */
    /**
     * Categorías de producto que tienen algún movimiento de inventario en la empresa
     * (select "Categoría" del modal de filtros de Movimientos de Inventario). Recorre
     * categorías → productos → EXISTS en el kardex (idx_kardex_empresa_producto), sin
     * leer el kardex completo.
     */
    public function getCategoriasConMovimientos(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT c.id, c.nombre
                                    FROM categorias c
                                   WHERE c.id_empresa = :e AND c.eliminado = false
                                     AND EXISTS (SELECT 1 FROM productos p
                                                  WHERE p.id_categoria = c.id AND p.id_empresa = :e
                                                    AND EXISTS (SELECT 1 FROM inventario_kardex k
                                                                 WHERE k.id_empresa = :e AND k.id_producto = p.id
                                                                   AND k.eliminado = false))
                                   ORDER BY c.nombre ASC");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTiposReferencia(int $idEmpresa): array
    {
        if (!$this->indiceExiste('idx_kardex_referencia')) {
            $st = $this->db->prepare("SELECT DISTINCT referencia_tipo
                                        FROM inventario_kardex
                                       WHERE id_empresa = :e AND eliminado = false
                                         AND referencia_tipo IS NOT NULL
                                       ORDER BY referencia_tipo ASC");
            $st->execute([':e' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_COLUMN);
        }

        $sql = "WITH RECURSIVE tipos AS (
                    (SELECT referencia_tipo
                       FROM inventario_kardex
                      WHERE id_empresa = :e AND eliminado = false
                        AND referencia_tipo IS NOT NULL
                      ORDER BY referencia_tipo
                      LIMIT 1)
                    UNION ALL
                    SELECT (SELECT k.referencia_tipo
                              FROM inventario_kardex k
                             WHERE k.id_empresa = :e AND k.eliminado = false
                               AND k.referencia_tipo > t.referencia_tipo
                             ORDER BY k.referencia_tipo
                             LIMIT 1)
                      FROM tipos t
                     WHERE t.referencia_tipo IS NOT NULL
                )
                SELECT referencia_tipo
                  FROM tipos
                 WHERE referencia_tipo IS NOT NULL
                 ORDER BY referencia_tipo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Usuarios que tienen al menos un movimiento de kardex en la empresa.
     * Alimenta el <select> "Usuario" de Inventario y del Reporte de
     * Inventarios (se ejecuta en cada carga de esas páginas).
     *
     * Misma historia que getTiposReferencia(): el DISTINCT sobre el JOIN
     * recorría el kardex completo (~157 ms con 600.000 filas) para devolver
     * un puñado de usuarios. Se resuelve primero la lista de created_by
     * distintos con un loose index scan (~1 ms) y recién ahí se cruza contra
     * usuarios. Requiere idx_kardex_empresa_usuario — ver
     * database/20260916_reporte_inventarios_indices_ajuste.sql.
     *
     * Sin ese índice cada salto relee toda la empresa (medido: 2,4 s con 300.000
     * movimientos y 6 usuarios, contra 0,4 s de una sola pasada), así que en una
     * base donde el SQL todavía no se aplicó se usa la pasada única.
     */
    public function getUsuariosConMovimientos(int $idEmpresa): array
    {
        if (!$this->indiceExiste('idx_kardex_empresa_usuario')) {
            $st = $this->db->prepare("SELECT u.id, u.nombre
                                        FROM usuarios u
                                       WHERE u.id IN (SELECT created_by FROM inventario_kardex
                                                       WHERE id_empresa = :e AND eliminado = false)
                                       ORDER BY u.nombre ASC");
            $st->execute([':e' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql = "WITH RECURSIVE autores AS (
                    (SELECT created_by
                       FROM inventario_kardex
                      WHERE id_empresa = :e AND eliminado = false
                        AND created_by IS NOT NULL
                      ORDER BY created_by
                      LIMIT 1)
                    UNION ALL
                    SELECT (SELECT k.created_by
                              FROM inventario_kardex k
                             WHERE k.id_empresa = :e AND k.eliminado = false
                               AND k.created_by > a.created_by
                             ORDER BY k.created_by
                             LIMIT 1)
                      FROM autores a
                     WHERE a.created_by IS NOT NULL
                )
                SELECT u.id, u.nombre
                  FROM autores a
                  JOIN usuarios u ON u.id = a.created_by
                 WHERE a.created_by IS NOT NULL
                 ORDER BY u.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
