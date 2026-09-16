<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del Reporte de Inventarios (5 pestañas: Existencias,
 * Movimientos/Kardex, Valorización, Consignaciones, Auditoría). Sigue el
 * mismo patrón (Controller → Repository, sin Service) que
 * ReporteVentasRepository / ReporteComprasRepository. Es de solo lectura
 * salvo por corregirStockAuditoria(), la única escritura del módulo (corrige
 * productos_bodegas.stock_actual para que coincida con el kardex real).
 */
class ReporteInventarioRepository extends BaseRepository
{
    /** Etiquetas legibles para inventario_kardex.referencia_tipo (origen del movimiento). */
    private const ORIGEN_LABELS = [
        'ajuste_manual'                  => 'Ajuste manual',
        'carga_inventario'               => 'Carga de inventario',
        'compra'                         => 'Compra',
        'factura_venta'                  => 'Factura de venta',
        'nota_credito'                   => 'Nota de crédito',
        'recibo_venta'                   => 'Recibo de venta',
        'SALDO_INICIAL'                  => 'Saldo inicial',
        'CONSIGNACION_VENTA'             => 'Consignación',
        'EDICION_CONSIGNACION_VENTA'     => 'Consignación (editada)',
        'ELIMINACION_CONSIGNACION_VENTA' => 'Consignación (reversa)',
        'FACTURACION_CV'                 => 'Facturación de consignación',
        'RETORNO_CV'                     => 'Retorno de consignación',
        'ELIMINACION_RETORNO_CV'         => 'Retorno de consignación (reversa)',
        'CAMBIO_ESTADO_RETORNO_CV'       => 'Retorno de consignación (cambio de estado)',
        'CAMBIO_PRODUCTO_CV'             => 'Cambio de producto',
        'migracion'                      => 'Migración histórica',
    ];

    /**
     * Tope de filas que se envían A LA PANTALLA en las consultas que pueden devolver
     * un resultado sin cota. El desglose por lote/caducidad de Existencias llegó a
     * 288.000 filas en la prueba de carga (2.000 productos × 5 bodegas × 400 lotes):
     * son minutos de HTML y un navegador colgado, por un listado que nadie puede leer.
     * Las EXPORTACIONES no lo aplican (pasan limite = null): un Excel sí puede con
     * todas las filas, y ahí el usuario sí quiere el dato completo.
     */
    public const LIMITE_FILAS_PANTALLA = 5000;

    /**
     * Tope para Excel y PDF. Muy por encima del de pantalla (un archivo sí puede con
     * decenas de miles de filas), pero no ilimitado: sin tope, exportar el kardex de una
     * empresa con 300.000 movimientos agota la memoria de PHP antes de escribir nada.
     * Si se alcanza, la exportación lo dice en su última fila en vez de callarlo.
     */
    public const LIMITE_FILAS_EXPORT = 50000;

    public function __construct()
    {
        parent::__construct('inventario_kardex');
    }

    public static function labelOrigen(?string $tipo): string
    {
        if ($tipo === null || $tipo === '') {
            return 'Sin origen';
        }
        return self::ORIGEN_LABELS[$tipo] ?? $tipo;
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 1 — EXISTENCIAS (stock actual)
    // ════════════════════════════════════════════════════════════════════

    private function buildWhereExistencias(int $idEmpresa, array $filtros): array
    {
        $where = "u.id_empresa = :id_empresa
                   AND p.eliminado = false AND p.inventariable = true
                   AND b.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['id_bodega'])) {
            $where .= " AND u.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_categoria'])) {
            $where .= " AND p.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $where .= " AND p.id_marca = :id_marca";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND u.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }
        // productos_bodegas es un caché de stock total (no desglosa por lote), así que
        // lote/NUP/caducidad solo restringen qué producto×bodega aparece: se exige que
        // exista algún movimiento de kardex con ese dato para ese producto y bodega.
        if (!empty($filtros['numero_lote'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM inventario_kardex k2
                WHERE k2.id_producto = u.id_producto AND k2.id_bodega = u.id_bodega
                  AND k2.id_empresa = u.id_empresa AND k2.eliminado = false
                  AND k2.numero_lote ILIKE :numero_lote
            )";
            $params[':numero_lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM inventario_kardex k3
                WHERE k3.id_producto = u.id_producto AND k3.id_bodega = u.id_bodega
                  AND k3.id_empresa = u.id_empresa AND k3.eliminado = false
                  AND k3.nup ILIKE :nup
            )";
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['fecha_caducidad_desde'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM inventario_kardex k4
                WHERE k4.id_producto = u.id_producto AND k4.id_bodega = u.id_bodega
                  AND k4.id_empresa = u.id_empresa AND k4.eliminado = false
                  AND k4.fecha_caducidad >= :fecha_caducidad_desde
            )";
            $params[':fecha_caducidad_desde'] = $filtros['fecha_caducidad_desde'];
        }
        if (!empty($filtros['fecha_caducidad_hasta'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM inventario_kardex k5
                WHERE k5.id_producto = u.id_producto AND k5.id_bodega = u.id_bodega
                  AND k5.id_empresa = u.id_empresa AND k5.eliminado = false
                  AND k5.fecha_caducidad <= :fecha_caducidad_hasta
            )";
            $params[':fecha_caducidad_hasta'] = $filtros['fecha_caducidad_hasta'];
        }

        return [$where, $params];
    }

    /**
     * Base: una fila por producto×bodega, con costo unitario (último movimiento) y estado
     * calculado. El universo de pares producto×bodega sale de UNION(productos_bodegas activos,
     * pares con movimiento real en inventario_kardex) — no solo de productos_bodegas — para que
     * un producto con historial de kardex nunca desaparezca de Existencias (con saldo cero o
     * negativo incluido) aunque su fila en productos_bodegas esté ausente o eliminada (caché
     * desincronizado; ver docs/manual/modulos/reporte-inventarios.md, pestaña Auditoría).
     * productos_bodegas (pb) queda como LEFT JOIN solo para leer stock_minimo/stock_maximo.
     *
     * RENDIMIENTO — por qué CTEs agregados y no subconsultas en el SELECT:
     * stock, costo y consignado se calculaban con tres subconsultas correlacionadas, una
     * por cada uno de esos tres datos y POR CADA FILA del resultado. Con 2.000 productos ×
     * 5 bodegas son 10.000 filas → 30.000 subconsultas, cada una entrando otra vez al
     * kardex: 12,5 s para el listado (y 15 s para Valorización, que usa esta misma base).
     * Aquí se agrega UNA vez todo el kardex de la empresa (y una vez las consignaciones) y
     * el resultado se cruza por LEFT JOIN. Los CTE van MATERIALIZED a propósito: kardex_agg
     * se usa dos veces (para el universo de pares y para el stock) y sin materializar
     * PostgreSQL lo calcularía dos veces.
     *
     * $fechaCorte (opcional): si viene, el saldo/costo/consignado se calculan "a esa fecha"
     * (solo movimientos/consignaciones hasta ese día), en vez del saldo corriente de hoy.
     */
    private function baseExistencias(string $where, bool $conFechaCorte = false): string
    {
        $condCorteKardex = $conFechaCorte ? " AND fecha_movimiento <= :fecha_corte" : "";
        $condCorteCv     = $conFechaCorte ? " AND cv.fecha_emision <= :fecha_corte" : "";

        return "
            WITH kardex_agg AS MATERIALIZED (
                SELECT id_empresa, id_producto, id_bodega,
                       SUM(cantidad) AS stock_actual,
                       (ARRAY_AGG(costo_unitario ORDER BY fecha_movimiento DESC, id DESC))[1] AS costo_unitario
                FROM inventario_kardex
                WHERE id_empresa = :id_empresa AND eliminado = false{$condCorteKardex}
                GROUP BY id_empresa, id_producto, id_bodega
            ),
            consignado_agg AS (
                SELECT cvd.id_empresa, cvd.id_producto, cvd.id_bodega,
                       SUM(cvd.cantidad
                           - COALESCE((" . $this->sqlRetornadoCv() . "), 0)
                           - COALESCE((" . $this->sqlFacturadoCv() . "), 0)
                           - COALESCE((" . $this->sqlCambiadoCv() . "), 0)) AS consignado
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                WHERE cvd.id_empresa = :id_empresa AND cvd.eliminado = false
                  AND cv.eliminado = false{$condCorteCv}
                GROUP BY cvd.id_empresa, cvd.id_producto, cvd.id_bodega
            )
            SELECT * FROM (
                SELECT u.id_producto, u.id_bodega, COALESCE(pb.stock_minimo, 0) AS stock_minimo,
                       COALESCE(pb.stock_maximo, 0) AS stock_maximo,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       p.id_categoria, COALESCE(cat.nombre, 'Sin categoría') AS categoria_nombre,
                       p.id_marca, COALESCE(mar.nombre, 'Sin marca') AS marca_nombre,
                       b.nombre AS bodega_nombre,
                       -- Stock en vivo (suma del kardex), no el pb.stock_actual cacheado: ese
                       -- puede desincronizarse si algo toca productos_bodegas sin pasar por el
                       -- kardex — mismo motivo que el Saldo de Movimientos.
                       COALESCE(ka.stock_actual, 0) AS stock_actual,
                       COALESCE(ka.costo_unitario, 0) AS costo_unitario,
                       COALESCE(ca.consignado, 0) AS consignado
                FROM (
                    SELECT id_empresa, id_producto, id_bodega FROM productos_bodegas
                    WHERE id_empresa = :id_empresa AND eliminado = false
                    UNION
                    SELECT id_empresa, id_producto, id_bodega FROM kardex_agg
                ) u
                INNER JOIN productos p ON p.id = u.id_producto AND p.id_empresa = u.id_empresa
                INNER JOIN bodegas b ON b.id = u.id_bodega
                LEFT JOIN productos_bodegas pb ON pb.id_producto = u.id_producto
                    AND pb.id_bodega = u.id_bodega AND pb.id_empresa = u.id_empresa AND pb.eliminado = false
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
                LEFT JOIN kardex_agg ka ON ka.id_producto = u.id_producto
                    AND ka.id_bodega = u.id_bodega AND ka.id_empresa = u.id_empresa
                LEFT JOIN consignado_agg ca ON ca.id_producto = u.id_producto
                    AND ca.id_bodega = u.id_bodega AND ca.id_empresa = u.id_empresa
                WHERE {$where}
            ) base
        ";
    }

    private function wrapValorYEstado(string $baseSql): string
    {
        return "
            SELECT t.*, (t.stock_actual * t.costo_unitario) AS valor_total,
                   (t.stock_actual + t.consignado) AS stock_total,
                   CASE
                       WHEN t.stock_actual <= 0 THEN 'QUIEBRE'
                       WHEN t.stock_minimo > 0 AND t.stock_actual <= t.stock_minimo THEN 'ALERTA'
                       WHEN t.stock_maximo > 0 AND t.stock_actual > t.stock_maximo THEN 'EXCESO'
                       ELSE 'NORMAL'
                   END AS estado_stock
            FROM ({$baseSql}) t
        ";
    }

    /** Columnas permitidas para ordenar el detalle de existencias (whitelist anti-inyección). */
    private const SORT_COLUMNAS_EXISTENCIAS = [
        'producto_nombre', 'categoria_nombre', 'bodega_nombre',
        'stock_actual', 'consignado', 'stock_total', 'stock_minimo', 'stock_maximo', 'costo_unitario', 'valor_total',
    ];

    /** @param int|null $limite tope de filas para pantalla; null = sin tope (exportaciones). */
    public function getExistenciasDetalle(int $idEmpresa, array $filtros, ?int $limite = null): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $conFechaCorte = !empty($filtros['fecha_corte']);
        if ($conFechaCorte) {
            $params[':fecha_corte'] = $filtros['fecha_corte'];
        }
        $sql = "SELECT * FROM (" . $this->wrapValorYEstado($this->baseExistencias($where, $conFechaCorte)) . ") e WHERE 1=1";
        if (!empty($filtros['estado_stock'])) {
            $sql .= " AND e.estado_stock = :estado_stock";
            $params[':estado_stock'] = $filtros['estado_stock'];
        }
        if (($filtros['consignado'] ?? '') === 'CON') {
            $sql .= " AND e.consignado > 0";
        } elseif (($filtros['consignado'] ?? '') === 'SIN') {
            $sql .= " AND e.consignado = 0";
        }

        $orden = in_array($filtros['orden'] ?? '', self::SORT_COLUMNAS_EXISTENCIAS, true) ? $filtros['orden'] : 'producto_nombre';
        $dir = strtoupper($filtros['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY e.{$orden} {$dir}, e.producto_nombre ASC, e.bodega_nombre ASC";
        if ($limite !== null) {
            // +1 fila: así el llamador sabe que hay más y puede avisar, sin un COUNT(*) aparte.
            $sql .= ' LIMIT ' . ((int) $limite + 1);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getExistenciasAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $conFechaCorte = !empty($filtros['fecha_corte']);
        if ($conFechaCorte) {
            $params[':fecha_corte'] = $filtros['fecha_corte'];
        }
        $base = $this->wrapValorYEstado($this->baseExistencias($where, $conFechaCorte));

        $whereConsignado = '';
        if (($filtros['consignado'] ?? '') === 'CON') {
            $whereConsignado = ' WHERE consignado > 0';
        } elseif (($filtros['consignado'] ?? '') === 'SIN') {
            $whereConsignado = ' WHERE consignado = 0';
        }

        $sql = "SELECT * FROM (
                    SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo,
                           SUM(stock_actual) AS stock_actual,
                           SUM(consignado) AS consignado,
                           SUM(stock_actual + consignado) AS stock_total,
                           SUM(stock_minimo) AS stock_minimo,
                           SUM(stock_maximo) AS stock_maximo,
                           SUM(valor_total) AS valor_total,
                           CASE WHEN SUM(stock_actual) > 0 THEN SUM(valor_total) / SUM(stock_actual) ELSE 0 END AS costo_unitario,
                           COUNT(DISTINCT id_producto) AS cantidad_productos
                    FROM ({$base}) t{$whereConsignado}
                    GROUP BY {$campoId}
                ) g
                ORDER BY valor_total DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExistenciasAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_producto', "producto_codigo || ' - ' || producto_nombre");
    }

    public function getExistenciasAgrupadoCategoria(int $idEmpresa, array $filtros): array
    {
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_categoria', 'categoria_nombre');
    }

    public function getExistenciasAgrupadoBodega(int $idEmpresa, array $filtros): array
    {
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_bodega', 'bodega_nombre');
    }

    // ── Desglose por Lote / Caducidad: a diferencia de Producto/Categoría/Bodega
    // (que suman el stock_actual ya calculado por producto×bodega), aquí el desglose es
    // POR DEBAJO de ese nivel — productos_bodegas no guarda el stock por lote/NUP/caducidad
    // (ver comentario en baseExistencias()), así que se agrupa directo desde inventario_kardex.

    private function buildWhereExistenciasKardex(int $idEmpresa, array $filtros): array
    {
        $where = "k.id_empresa = :id_empresa AND k.eliminado = false
                   AND p.eliminado = false AND p.inventariable = true
                   AND b.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['id_bodega'])) {
            $where .= " AND k.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_categoria'])) {
            $where .= " AND p.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $where .= " AND p.id_marca = :id_marca";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND k.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['numero_lote'])) {
            $where .= " AND k.numero_lote ILIKE :numero_lote";
            $params[':numero_lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $where .= " AND k.nup ILIKE :nup";
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['fecha_caducidad_desde'])) {
            $where .= " AND k.fecha_caducidad >= :fecha_caducidad_desde";
            $params[':fecha_caducidad_desde'] = $filtros['fecha_caducidad_desde'];
        }
        if (!empty($filtros['fecha_caducidad_hasta'])) {
            $where .= " AND k.fecha_caducidad <= :fecha_caducidad_hasta";
            $params[':fecha_caducidad_hasta'] = $filtros['fecha_caducidad_hasta'];
        }
        if (!empty($filtros['fecha_corte'])) {
            $where .= " AND k.fecha_movimiento <= :fecha_corte";
            $params[':fecha_corte'] = $filtros['fecha_corte'];
        }

        return [$where, $params];
    }

    /**
     * Desglose del stock POR DEBAJO del nivel producto×bodega. `productos_bodegas`
     * no guarda el stock por lote/caducidad (ver el comentario de baseExistencias()),
     * así que estas filas se agrupan directo desde inventario_kardex.
     *
     * El selector "Detalle" de la pestaña Existencias elige hasta dónde baja el
     * desglose, y eso cambia tanto las filas como las columnas:
     *
     *   LOTE           una fila por producto×bodega×LOTE. Si un mismo lote entró con
     *                  dos caducidades distintas, aquí se ven SUMADAS en una sola fila.
     *                  Sin columna NUP (un lote puede tener varios).
     *   CADUCIDAD      una fila por producto×bodega×FECHA DE CADUCIDAD, sumando todos
     *                  los lotes que caducan ese día. Es la vista para "qué se me vence".
     *   LOTE_CADUCIDAD una fila por cada combinación lote+NUP+caducidad — el máximo
     *                  detalle, y lo que hacía la antigua opción "Por Lote" de
     *                  "Agrupar por".
     *
     * "consignado" (cuánto de ese mismo grupo está en poder de clientes) se cruza
     * EXACTAMENTE por las columnas que definen el grupo: si la fila no distingue
     * caducidad, el consignado tampoco, o los números no cuadrarían entre sí.
     *
     * @param string   $desglose LOTE | CADUCIDAD | LOTE_CADUCIDAD
     * @param int|null $limite   tope de filas para pantalla; null = sin tope (exportaciones).
     */
    public function getExistenciasPorDesglose(int $idEmpresa, array $filtros, string $desglose, ?int $limite = null): array
    {
        $conLote = in_array($desglose, ['LOTE', 'LOTE_CADUCIDAD'], true);
        $conCad  = in_array($desglose, ['CADUCIDAD', 'LOTE_CADUCIDAD'], true);
        $conNup  = $desglose === 'LOTE_CADUCIDAD';

        list($where, $params) = $this->buildWhereExistenciasKardex($idEmpresa, $filtros);
        $condCorteCv = !empty($filtros['fecha_corte']) ? " AND cv.fecha_emision <= :fecha_corte" : "";

        // Las columnas que no forman parte del grupo viajan en NULL: la fila no puede
        // afirmar un lote/NUP/caducidad concretos cuando está sumando varios.
        $selLote = $conLote ? 'k.numero_lote' : 'NULL::varchar';
        $selNup  = $conNup  ? 'k.nup'         : 'NULL::varchar';
        $selCad  = $conCad  ? 'k.fecha_caducidad' : 'NULL::date';

        // El consignado se cruza EXACTAMENTE por las columnas que definen el grupo: si la
        // fila no distingue caducidad, el consignado tampoco, o los números no cuadrarían.
        $groupExtra = '';
        $selCvGroup = '';
        $joinCv     = '';
        $ordenExtra = [];
        if ($conLote) {
            $groupExtra .= ', k.numero_lote';
            $selCvGroup .= ', cvd.lote';
            $joinCv     .= ' AND ca.lote IS NOT DISTINCT FROM k.numero_lote';
            $ordenExtra[] = 'lote ASC NULLS LAST';
        }
        if ($conNup) {
            $groupExtra .= ', k.nup';
            $selCvGroup .= ', cvd.nup';
            $joinCv     .= ' AND ca.nup IS NOT DISTINCT FROM k.nup';
        }
        if ($conCad) {
            $groupExtra .= ', k.fecha_caducidad';
            $selCvGroup .= ', cvd.fecha_caducidad';
            $joinCv     .= ' AND ca.fecha_caducidad IS NOT DISTINCT FROM k.fecha_caducidad';
            $ordenExtra[] = 'fecha_caducidad ASC NULLS LAST';
        }
        $orden = 'producto_nombre ASC, bodega_nombre ASC' . ($ordenExtra ? ', ' . implode(', ', $ordenExtra) : '');

        $sql = "WITH consignado_desglose AS (
                    SELECT cvd.id_empresa, cvd.id_producto, cvd.id_bodega{$selCvGroup},
                           SUM(cvd.cantidad
                               - COALESCE((" . $this->sqlRetornadoCv() . "), 0)
                               - COALESCE((" . $this->sqlFacturadoCv() . "), 0)
                               - COALESCE((" . $this->sqlCambiadoCv() . "), 0)) AS consignado
                    FROM consignaciones_ventas_detalles cvd
                    INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                    WHERE cvd.id_empresa = :id_empresa AND cvd.eliminado = false
                      AND cv.eliminado = false{$condCorteCv}
                    GROUP BY cvd.id_empresa, cvd.id_producto, cvd.id_bodega{$selCvGroup}
                )
                SELECT *, (stock_actual * costo_unitario) AS valor_total,
                       (stock_actual + consignado) AS stock_total
                FROM (
                    SELECT k.id_producto, k.id_bodega,
                           {$selLote} AS lote, {$selNup} AS nup, {$selCad} AS fecha_caducidad,
                           p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                           COALESCE(cat.nombre, 'Sin categoría') AS categoria_nombre,
                           COALESCE(mar.nombre, 'Sin marca') AS marca_nombre,
                           b.nombre AS bodega_nombre,
                           SUM(k.cantidad) AS stock_actual,
                           (ARRAY_AGG(k.costo_unitario ORDER BY k.fecha_movimiento DESC, k.id DESC))[1] AS costo_unitario,
                           COALESCE(MAX(ca.consignado), 0) AS consignado
                    FROM inventario_kardex k
                    INNER JOIN productos p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
                    INNER JOIN bodegas b ON b.id = k.id_bodega
                    LEFT JOIN categorias cat ON cat.id = p.id_categoria
                    LEFT JOIN marcas mar ON mar.id = p.id_marca
                    LEFT JOIN consignado_desglose ca ON ca.id_empresa = k.id_empresa
                        AND ca.id_producto = k.id_producto AND ca.id_bodega = k.id_bodega{$joinCv}
                    WHERE {$where}
                    GROUP BY k.id_empresa, k.id_producto, k.id_bodega{$groupExtra},
                             p.codigo, p.nombre, cat.nombre, mar.nombre, b.nombre
                ) t
                ORDER BY {$orden}";
        if ($limite !== null) {
            $sql .= ' LIMIT ' . ((int) $limite + 1);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExistenciasKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $conFechaCorte = !empty($filtros['fecha_corte']);
        if ($conFechaCorte) {
            $params[':fecha_corte'] = $filtros['fecha_corte'];
        }
        $sql = "SELECT
                    COUNT(*) AS total_filas,
                    COUNT(DISTINCT id_producto) AS total_productos,
                    COALESCE(SUM(valor_total), 0) AS valor_total,
                    COUNT(*) FILTER (WHERE estado_stock = 'QUIEBRE') AS en_quiebre,
                    COUNT(*) FILTER (WHERE estado_stock = 'ALERTA')  AS en_alerta,
                    COUNT(*) FILTER (WHERE estado_stock = 'EXCESO')  AS en_exceso
                FROM (" . $this->wrapValorYEstado($this->baseExistencias($where, $conFechaCorte)) . ") e";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_productos' => (int) ($row['total_productos'] ?? 0),
            'valor_total'     => (float) ($row['valor_total'] ?? 0),
            'en_quiebre'      => (int) ($row['en_quiebre'] ?? 0),
            'en_alerta'       => (int) ($row['en_alerta'] ?? 0),
            'en_exceso'       => (int) ($row['en_exceso'] ?? 0),
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 2 — MOVIMIENTOS (Kardex)
    // ════════════════════════════════════════════════════════════════════

    private function buildWhereMovimientos(int $idEmpresa, array $filtros): array
    {
        $where = "k.id_empresa = :id_empresa AND k.eliminado = false
                   AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND k.fecha_movimiento >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND k.fecha_movimiento <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND k.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND k.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['id_categoria'])) {
            $where .= " AND p.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $where .= " AND p.id_marca = :id_marca";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $where .= " AND k.tipo_movimiento = :tipo_movimiento";
            $params[':tipo_movimiento'] = $filtros['tipo_movimiento'];
        }
        if (!empty($filtros['referencia_tipo'])) {
            $where .= " AND k.referencia_tipo = :referencia_tipo";
            $params[':referencia_tipo'] = $filtros['referencia_tipo'];
        }
        if (!empty($filtros['id_usuario'])) {
            $where .= " AND k.created_by = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }
        if (!empty($filtros['numero_lote'])) {
            $where .= " AND k.numero_lote ILIKE :numero_lote";
            $params[':numero_lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $where .= " AND k.nup ILIKE :nup";
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['fecha_caducidad_desde'])) {
            $where .= " AND k.fecha_caducidad >= :fecha_caducidad_desde";
            $params[':fecha_caducidad_desde'] = $filtros['fecha_caducidad_desde'];
        }
        if (!empty($filtros['fecha_caducidad_hasta'])) {
            $where .= " AND k.fecha_caducidad <= :fecha_caducidad_hasta";
            $params[':fecha_caducidad_hasta'] = $filtros['fecha_caducidad_hasta'];
        }
        if (!empty($filtros['observaciones'])) {
            $where .= " AND k.observaciones ILIKE :observaciones";
            $params[':observaciones'] = '%' . $filtros['observaciones'] . '%';
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar OR b.nombre ILIKE :buscar OR k.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        return [$where, $params];
    }

    private function fromMovimientos(string $where): string
    {
        return "FROM inventario_kardex k
                 INNER JOIN productos p ON p.id = k.id_producto
                 INNER JOIN bodegas b ON b.id = k.id_bodega
                 LEFT JOIN usuarios u ON u.id = k.created_by
                 LEFT JOIN unidades_medida um ON um.id = k.id_medida
                 LEFT JOIN categorias cat ON cat.id = p.id_categoria
                 LEFT JOIN marcas mar ON mar.id = p.id_marca
                 WHERE {$where}";
    }

    /**
     * @param int|null $limite tope de filas para pantalla; null = sin tope (exportaciones).
     *
     * OJO con el coste: el saldo corrido es una función de ventana sobre TODAS las filas
     * que cumplen el filtro, así que se calcula entero antes de aplicar el LIMIT — el tope
     * recorta lo que se envía, no lo que se lee. Lo que de verdad abarata esta consulta es
     * el filtro de fechas (medido: 2,6 s sin filtro vs 0,12 s acotando a un mes), y por eso
     * la pestaña arranca con un año seleccionado en vez de "Todos".
     */
    public function getMovimientosDetalle(int $idEmpresa, array $filtros, ?int $limite = null): array
    {
        list($where, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        // El "Saldo" NO se lee de k.stock_posterior (es un valor cacheado en cada fila que
        // puede quedar desincronizado si algo externo toca productos_bodegas sin pasar por
        // el kardex). En su lugar se calcula en vivo: suma corrida de "cantidad" (entradas
        // positivas, salidas negativas) por producto+bodega, en orden cronológico, sobre las
        // filas que cumplen los filtros actuales. Nota: si se filtra por rango de fechas, el
        // saldo corrido arranca desde la primera fila visible en ese rango, no desde el inicio
        // absoluto del historial (igual que una suma acumulada sobre un rango filtrado).
        $sql = "SELECT k.id, k.fecha_movimiento, k.tipo_movimiento, k.referencia_tipo, k.referencia_id,
                       k.cantidad, k.costo_unitario, k.costo_total,
                       SUM(k.cantidad) OVER (
                           PARTITION BY k.id_producto, k.id_bodega
                           ORDER BY k.fecha_movimiento, k.id
                           ROWS UNBOUNDED PRECEDING
                       ) AS saldo,
                       k.numero_lote, k.fecha_caducidad, k.nup, k.observaciones,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       b.nombre AS bodega_nombre, u.nombre AS usuario_nombre,
                       um.abreviatura AS medida_abreviatura
                " . $this->fromMovimientos($where) . "
                ORDER BY p.nombre ASC, b.nombre ASC, k.fecha_movimiento ASC, k.id ASC"
                . ($limite !== null ? ' LIMIT ' . ((int) $limite + 1) : '');

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['origen_label'] = self::labelOrigen($r['referencia_tipo']);
        }
        unset($r);
        return $rows;
    }

    private function getMovimientosAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabelExpr, string $orderBy): array
    {
        list($where, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabelExpr}) AS nombre_grupo,
                       COUNT(*) AS cantidad_movimientos,
                       SUM(CASE WHEN k.cantidad > 0 THEN k.cantidad ELSE 0 END) AS total_entradas,
                       SUM(CASE WHEN k.cantidad < 0 THEN ABS(k.cantidad) ELSE 0 END) AS total_salidas,
                       SUM(k.cantidad) AS saldo_neto,
                       SUM(k.costo_total) AS costo_total
                " . $this->fromMovimientos($where) . "
                GROUP BY {$campoId}
                ORDER BY {$orderBy}";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovimientosAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.id_producto', "p.codigo || ' - ' || p.nombre", 'cantidad_movimientos DESC');
    }

    public function getMovimientosAgrupadoBodega(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.id_bodega', 'b.nombre', 'cantidad_movimientos DESC');
    }

    public function getMovimientosAgrupadoTipo(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.tipo_movimiento', 'k.tipo_movimiento', 'cantidad_movimientos DESC');
    }

    public function getMovimientosAgrupadoOrigen(int $idEmpresa, array $filtros): array
    {
        $rows = $this->getMovimientosAgrupado($idEmpresa, $filtros, "COALESCE(k.referencia_tipo, '')", "COALESCE(k.referencia_tipo, '')", 'cantidad_movimientos DESC');
        foreach ($rows as &$r) {
            $r['nombre_grupo'] = self::labelOrigen($r['nombre_grupo'] !== '' ? $r['nombre_grupo'] : null);
        }
        unset($r);
        return $rows;
    }

    public function getMovimientosAgrupadoFecha(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'CAST(k.fecha_movimiento AS DATE)', 'CAST(k.fecha_movimiento AS DATE)', 'id_grupo DESC');
    }

    public function getMovimientosAgrupadoMes(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, "TO_CHAR(k.fecha_movimiento, 'YYYY-MM')", "TO_CHAR(k.fecha_movimiento, 'YYYY-MM')", 'id_grupo DESC');
    }

    public function getMovimientosKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $sql = "SELECT
                    COUNT(*) AS total_movimientos,
                    COALESCE(SUM(CASE WHEN k.cantidad > 0 THEN k.cantidad ELSE 0 END), 0) AS total_entradas,
                    COALESCE(SUM(CASE WHEN k.cantidad < 0 THEN ABS(k.cantidad) ELSE 0 END), 0) AS total_salidas,
                    COALESCE(SUM(k.cantidad), 0) AS saldo_neto
                " . $this->fromMovimientos($where);

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_movimientos' => (int) ($row['total_movimientos'] ?? 0),
            'total_entradas'    => (float) ($row['total_entradas'] ?? 0),
            'total_salidas'     => (float) ($row['total_salidas'] ?? 0),
            'saldo_neto'        => (float) ($row['saldo_neto'] ?? 0),
        ];
    }

    /**
     * Años con movimientos de kardex (alimenta el <select> "Año" de la pestaña
     * Movimientos; se ejecuta en cada carga del módulo).
     *
     * El "SELECT DISTINCT EXTRACT(YEAR …)" obligaba a leer todos los
     * movimientos de la empresa y calcular la función en cada fila: ~265 ms
     * con 600.000 filas, y creciendo cada año. Aquí se salta de año en año
     * por el índice (loose index scan): tantos saltos como años tenga el
     * histórico. Medido en ~0,4 ms con esas mismas 600.000 filas. Requiere
     * idx_kardex_empresa_fecha — ver
     * database/indices_reporte_inventarios_arranque.sql.
     */
    public function getAniosMovimientos(int $idEmpresa): array
    {
        $sql = "WITH RECURSIVE saltos AS (
                    (SELECT fecha_movimiento AS f
                       FROM inventario_kardex
                      WHERE id_empresa = :id_empresa AND eliminado = false
                        AND fecha_movimiento IS NOT NULL
                      ORDER BY fecha_movimiento
                      LIMIT 1)
                    UNION ALL
                    SELECT (SELECT k.fecha_movimiento
                              FROM inventario_kardex k
                             WHERE k.id_empresa = :id_empresa AND k.eliminado = false
                               AND k.fecha_movimiento >= date_trunc('year', s.f) + interval '1 year'
                             ORDER BY k.fecha_movimiento
                             LIMIT 1)
                      FROM saltos s
                     WHERE s.f IS NOT NULL
                )
                SELECT EXTRACT(YEAR FROM f)::int AS anio
                  FROM saltos
                 WHERE f IS NOT NULL
                 ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 3 — VALORIZACIÓN (a la fecha actual)
    // ════════════════════════════════════════════════════════════════════

    private function getValorizacionAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $base = $this->wrapValorYEstado($this->baseExistencias($where));

        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo,
                       SUM(stock_actual) AS stock_actual,
                       SUM(valor_total) AS valor_total,
                       CASE WHEN SUM(stock_actual) > 0 THEN SUM(valor_total) / SUM(stock_actual) ELSE 0 END AS costo_promedio,
                       COUNT(DISTINCT id_producto) AS cantidad_productos
                FROM ({$base}) t
                WHERE valor_total > 0
                GROUP BY {$campoId}
                ORDER BY valor_total DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getValorizacionAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_producto', "producto_codigo || ' - ' || producto_nombre");
    }

    public function getValorizacionAgrupadoCategoria(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_categoria', 'categoria_nombre');
    }

    public function getValorizacionAgrupadoBodega(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_bodega', 'bodega_nombre');
    }

    public function getValorizacionAgrupadoMarca(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_marca', 'marca_nombre');
    }

    public function getValorizacionKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $sql = "SELECT
                    COALESCE(SUM(valor_total), 0) AS valor_total,
                    COUNT(DISTINCT id_producto) AS total_productos,
                    COUNT(DISTINCT id_categoria) AS total_categorias
                FROM (" . $this->wrapValorYEstado($this->baseExistencias($where)) . ") e
                WHERE valor_total > 0";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $sqlTop = "SELECT producto_nombre, valor_total
                   FROM (" . $this->wrapValorYEstado($this->baseExistencias($where)) . ") e
                   ORDER BY valor_total DESC LIMIT 1";
        $stTop = $this->db->prepare($sqlTop);
        $stTop->execute($params);
        $top = $stTop->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'valor_total'       => (float) ($row['valor_total'] ?? 0),
            'total_productos'   => (int) ($row['total_productos'] ?? 0),
            'total_categorias'  => (int) ($row['total_categorias'] ?? 0),
            'producto_top'      => $top['producto_nombre'] ?? null,
            'producto_top_valor'=> (float) ($top['valor_total'] ?? 0),
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 4 — CONSIGNACIONES (saldo vigente en poder de clientes)
    // ════════════════════════════════════════════════════════════════════

    /** Cantidad retornada activa de una línea de consignación (mismo criterio que ConsignacionFacturaRepository).
     *  Va como LEFT JOIN LATERAL, no como subconsulta en el SELECT: el saldo se usa dos veces
     *  (unidades y valor a costo) y, al aplanar la vista, Postgres re-ejecutaba la subconsulta
     *  una vez por cada uso — el doble de trabajo por cada línea del reporte. */
    private function sqlRetornadoCv(): string
    {
        return "SELECT SUM(rcd.cantidad) AS total
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle = cvd.id
                  AND rcd.eliminado = false AND rc.eliminado = false AND rc.estado = 'Emitida'";
    }

    /** Cantidad facturada (docs 'facturada') de una línea de consignación. Ver nota en sqlRetornadoCv(). */
    private function sqlFacturadoCv(): string
    {
        return "SELECT SUM(cfd.cantidad) AS total
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                WHERE cfd.id_consignacion_detalle = cvd.id
                  AND cfd.eliminado = false AND cf.eliminado = false AND cf.estado = 'facturada'";
    }

    /** Cantidad ENTREGADA A CAMBIO desde una línea de consignación (Cambios de productos Emitida):
     *  la unidad pasó a ser del cliente como reposición, así que sale del saldo consignado igual
     *  que una facturación. Misma definición que Retornos y Facturación CV. Ver nota en sqlRetornadoCv(). */
    private function sqlCambiadoCv(): string
    {
        return \App\repositories\modulos\CambioProductoCvRepository::sqlEntregadoEnCambios('cvd.id') . " ";
    }

    /** Último costo unitario registrado en el kardex para la línea (documento + producto).
     *  Depende del índice idx_kardex_referencia (id_empresa, referencia_tipo, referencia_id,
     *  id_producto) WHERE eliminado = false: sin él cada línea recorre TODOS los movimientos
     *  del producto en la empresa y el reporte se vuelve inusable. */
    private function sqlCostoKardexCv(): string
    {
        return "SELECT k.costo_unitario
                FROM inventario_kardex k
                WHERE k.id_empresa = cv.id_empresa AND k.referencia_tipo = 'CONSIGNACION_VENTA'
                  AND k.referencia_id = cv.id AND k.id_producto = cvd.id_producto
                  AND k.eliminado = false
                ORDER BY k.fecha_movimiento DESC, k.id DESC
                LIMIT 1";
    }

    private function buildWhereConsignaciones(int $idEmpresa, array $filtros): array
    {
        // El filtro de empresa se repite en el detalle (no solo en la cabecera): sin él,
        // Postgres arranca leyendo TODOS los detalles de consignación de la base (de todas
        // las empresas) para luego cruzarlos contra la cabecera ya filtrada. Con el filtro
        // en cvd puede entrar directo por el índice de (id_empresa, ...) del detalle.
        $where = "cv.id_empresa = :id_empresa AND cvd.id_empresa = :id_empresa_det
                  AND cv.eliminado = false AND cvd.eliminado = false";
        $params = [':id_empresa' => $idEmpresa, ':id_empresa_det' => $idEmpresa];

        $estado = $filtros['estado'] ?? 'TODOS';
        if ($estado !== '' && strtoupper($estado) !== 'TODOS') {
            $where .= " AND cv.estado = :estado";
            $params[':estado'] = $estado;
        }

        if (!empty($filtros['id_cliente'])) {
            $where .= " AND cv.id_cliente = :id_cliente";
            $params[':id_cliente'] = (int) $filtros['id_cliente'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND cvd.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND cvd.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_vendedor'])) {
            $where .= " AND cv.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = (int) $filtros['id_vendedor'];
        }
        if (!empty($filtros['id_responsable_traslado'])) {
            $where .= " AND cv.id_responsable_traslado = :id_responsable_traslado";
            $params[':id_responsable_traslado'] = (int) $filtros['id_responsable_traslado'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND cv.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND cv.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['numero_lote'])) {
            $where .= " AND cvd.lote ILIKE :numero_lote";
            $params[':numero_lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $where .= " AND cvd.nup ILIKE :nup";
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['fecha_caducidad_desde'])) {
            $where .= " AND cvd.fecha_caducidad >= :fecha_caducidad_desde";
            $params[':fecha_caducidad_desde'] = $filtros['fecha_caducidad_desde'];
        }
        if (!empty($filtros['fecha_caducidad_hasta'])) {
            $where .= " AND cvd.fecha_caducidad <= :fecha_caducidad_hasta";
            $params[':fecha_caducidad_hasta'] = $filtros['fecha_caducidad_hasta'];
        }
        if (!empty($filtros['secuencial'])) {
            // Acepta buscar solo por el secuencial ("000000113") o por el número completo
            // con serie ("001-001-000000113"): antes solo comparaba contra cv.secuencial,
            // así que el formato completo (con guiones) nunca calzaba.
            $where .= " AND (cv.secuencial ILIKE :secuencial OR (cv.serie || '-' || cv.secuencial) ILIKE :secuencial)";
            $params[':secuencial'] = '%' . $filtros['secuencial'] . '%';
        }

        return [$where, $params];
    }

    /** Base: una fila por línea de consignación, con saldo y valor a costo calculados. */
    private function baseConsignaciones(string $where): string
    {
        return "
            SELECT * FROM (
                SELECT cv.id AS id_consignacion, cv.secuencial, cv.fecha_emision, cv.estado,
                       cv.id_cliente, COALESCE(c.nombre, '-') AS cliente_nombre,
                       COALESCE(c.identificacion, '') AS cliente_identificacion,
                       cv.id_vendedor, COALESCE(v.nombre, '-') AS vendedor_nombre,
                       cv.id_responsable_traslado, COALESCE(rt.nombre, '-') AS responsable_traslado_nombre,
                       cvd.id AS id_detalle, cvd.id_producto,
                       COALESCE(p.codigo, '') AS producto_codigo, COALESCE(p.nombre, '-') AS producto_nombre,
                       cvd.id_bodega, COALESCE(bo.nombre, '-') AS bodega_nombre,
                       COALESCE(cvd.lote, '-') AS numero_lote, COALESCE(cvd.nup, '-') AS nup,
                       cvd.cantidad AS cantidad_consignada,
                       COALESCE(ret.total, 0) AS cantidad_retornada,
                       COALESCE(fac.total, 0) AS cantidad_facturada,
                       COALESCE(cam.total, 0) AS cantidad_cambiada,
                       COALESCE(kar.costo_unitario, 0) AS costo_unitario
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                -- productos y clientes van con LEFT JOIN a propósito: con INNER, una
                -- consignación cuyo producto o cliente ya no exista en su tabla desaparecía
                -- del reporte sin ningún aviso (y su saldo dejaba de sumar en los totales).
                LEFT JOIN productos p ON p.id = cvd.id_producto
                LEFT JOIN bodegas bo ON bo.id = cvd.id_bodega
                LEFT JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN vendedores v ON v.id = cv.id_vendedor
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                LEFT JOIN LATERAL (" . $this->sqlRetornadoCv() . ") ret ON true
                LEFT JOIN LATERAL (" . $this->sqlFacturadoCv() . ") fac ON true
                LEFT JOIN LATERAL (SELECT (" . $this->sqlCambiadoCv() . ") AS total) cam ON true
                LEFT JOIN LATERAL (" . $this->sqlCostoKardexCv() . ") kar ON true
                WHERE {$where}
            ) base
        ";
    }

    private function wrapSaldoConsignacion(string $baseSql): string
    {
        // saldo = consignado − retornado − facturado − entregado a cambio
        return "
            SELECT t.*, (t.cantidad_consignada - t.cantidad_retornada - t.cantidad_facturada - t.cantidad_cambiada) AS saldo,
                   (t.cantidad_consignada - t.cantidad_retornada - t.cantidad_facturada - t.cantidad_cambiada) * t.costo_unitario AS valor_saldo
            FROM ({$baseSql}) t
        ";
    }

    public function getConsignacionesDetalle(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $sql = "SELECT * FROM (" . $this->wrapSaldoConsignacion($this->baseConsignaciones($where)) . ") s
                ORDER BY s.fecha_emision DESC, s.id_consignacion DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Una fila por CONSIGNACIÓN (no por línea de producto): para el listado "Detallado", que ahora
     *  agrupa por documento y deja el detalle de productos para el modal (ver getConsignacionDetalleLineas). */
    public function getConsignacionesCabeceras(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $base = $this->wrapSaldoConsignacion($this->baseConsignaciones($where));

        // Lote y NUP se agregan con string_agg: una consignación puede tener varias líneas con
        // distintos lotes/NUP y el listado es a nivel de documento. Se descarta el '-' que pone
        // baseConsignaciones() cuando el campo viene nulo, para no ensuciar la celda con guiones.
        $sql = "SELECT s.id_consignacion, MAX(s.secuencial) AS secuencial, MAX(s.fecha_emision) AS fecha_emision,
                       MAX(s.estado) AS estado, MAX(s.id_cliente) AS id_cliente,
                       MAX(s.cliente_nombre) AS cliente_nombre, MAX(s.cliente_identificacion) AS cliente_identificacion,
                       MAX(s.vendedor_nombre) AS vendedor_nombre,
                       MAX(s.responsable_traslado_nombre) AS responsable_traslado_nombre,
                       string_agg(DISTINCT NULLIF(s.numero_lote, '-'), ', ' ORDER BY NULLIF(s.numero_lote, '-')) AS lotes,
                       string_agg(DISTINCT NULLIF(s.nup, '-'), ', ' ORDER BY NULLIF(s.nup, '-')) AS nups,
                       -- Cuenta LÍNEAS del documento, no productos distintos: una misma
                       -- consignación suele llevar el mismo producto en varias líneas (un
                       -- lote/NUP/caducidad por línea). Con COUNT(DISTINCT id_producto) la
                       -- columna Productos anunciaba menos líneas de las que luego
                       -- aparecían al abrir el detalle.
                       COUNT(DISTINCT s.id_detalle) AS cantidad_productos,
                       SUM(s.cantidad_consignada) AS total_productos,
                       SUM(s.cantidad_retornada) AS total_retornado,
                       SUM(s.cantidad_facturada) AS total_facturado,
                       SUM(s.cantidad_cambiada) AS total_cambiado,
                       SUM(s.saldo) AS saldo, SUM(s.valor_saldo) AS valor_saldo
                FROM ({$base}) s
                GROUP BY s.id_consignacion
                ORDER BY MAX(s.fecha_emision) DESC, s.id_consignacion DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Filtros del reporte que actúan sobre la LÍNEA de consignación (no sobre la cabecera).
     *  Son los únicos que el modal de detalle reaplica: los de cabecera (cliente, fecha, estado,
     *  asesor, responsable) ya se cumplen por el solo hecho de estar viendo ese documento. */
    public const FILTROS_LINEA_CONSIGNACION = [
        'id_producto', 'id_bodega', 'numero_lote', 'nup',
        'fecha_caducidad_desde', 'fecha_caducidad_hasta',
    ];

    /** Líneas de producto de UNA consignación puntual (para el modal de detalle del listado).
     *  $filtrosLinea reaplica los filtros del listado a propósito: el total y el saldo de la fila
     *  ya vienen filtrados, así que el modal debe sumar exactamente lo mismo. Sin esto, una
     *  búsqueda por lote mostraba "10 unidades" en la fila y el documento entero en el modal. */
    public function getConsignacionDetalleLineas(int $idEmpresa, int $idConsignacion, array $filtrosLinea = []): array
    {
        $filtros = array_intersect_key($filtrosLinea, array_flip(self::FILTROS_LINEA_CONSIGNACION));
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $where .= " AND cv.id = :id_consignacion";
        $params[':id_consignacion'] = $idConsignacion;

        $sql = "SELECT * FROM (" . $this->wrapSaldoConsignacion($this->baseConsignaciones($where)) . ") s
                ORDER BY s.producto_nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Documentos de RETORNO que explican la cantidad "Retornado" de una línea de consignación puntual. */
    public function getRetornosDeLineaConsignacion(int $idEmpresa, int $idDetalleConsignacion): array
    {
        $sql = "SELECT rc.id, rc.serie, rc.secuencial, rc.fecha_retorno, rc.estado, rc.motivo,
                       rcd.cantidad, rcd.total
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle = :id_detalle
                  AND rcd.id_empresa = :id_empresa AND rcd.eliminado = false
                  AND rc.eliminado = false AND rc.estado = 'Emitida'
                ORDER BY rc.fecha_retorno ASC, rc.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_detalle' => $idDetalleConsignacion, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Documentos de FACTURA que explican la cantidad "Facturado" de una línea de consignación puntual.
     *  Devuelve el número de la FACTURA DE VENTA (ventas_cabecera vía cf.id_factura), no el del
     *  documento interno de facturación de consignación: el usuario necesita el documento que
     *  puede imprimir y buscar en Facturas de Venta. Si la factura ya no existe (o nunca se
     *  enlazó) queda `numero_factura`, el número que guardó la facturación en su momento. */
    public function getFacturasDeLineaConsignacion(int $idEmpresa, int $idDetalleConsignacion): array
    {
        $sql = "SELECT cf.id, cf.id_factura, cf.numero_factura,
                       cf.serie AS serie_consignacion, cf.secuencial AS secuencial_consignacion,
                       vc.id AS id_venta, vc.establecimiento, vc.punto_emision, vc.secuencial,
                       COALESCE(vc.fecha_emision, cf.fecha_emision) AS fecha_emision,
                       COALESCE(vc.estado, cf.estado) AS estado,
                       vc.eliminado AS factura_eliminada,
                       cfd.cantidad, cfd.total
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                LEFT JOIN ventas_cabecera vc ON vc.id = cf.id_factura AND vc.id_empresa = cf.id_empresa
                WHERE cfd.id_consignacion_detalle = :id_detalle
                  AND cfd.id_empresa = :id_empresa AND cfd.eliminado = false
                  AND cf.eliminado = false AND cf.estado = 'facturada'
                ORDER BY COALESCE(vc.fecha_emision, cf.fecha_emision) ASC, cf.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_detalle' => $idDetalleConsignacion, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getConsignacionesAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $base = $this->wrapSaldoConsignacion($this->baseConsignaciones($where));

        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo,
                       SUM(s.saldo) AS saldo,
                       SUM(s.valor_saldo) AS valor_saldo,
                       COUNT(DISTINCT s.id_consignacion) AS cantidad_consignaciones
                FROM ({$base}) s
                GROUP BY {$campoId}
                ORDER BY valor_saldo DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConsignacionesAgrupadoCliente(int $idEmpresa, array $filtros): array
    {
        return $this->getConsignacionesAgrupado($idEmpresa, $filtros, 'id_cliente', 'cliente_nombre');
    }

    public function getConsignacionesAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getConsignacionesAgrupado($idEmpresa, $filtros, 'id_producto', "producto_codigo || ' - ' || producto_nombre");
    }

    /** Indicadores del saldo vigente. Hoy la pestaña no los pinta, así que el controlador NO
     *  la llama (repetía entera la consulta de saldos por cada "Mostrar"). Si en algún momento
     *  se agregan tarjetas de KPI a la pestaña, este es el método que las alimenta. */
    public function getConsignacionesKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $sql = "SELECT
                    COALESCE(SUM(saldo), 0) AS unidades_vigentes,
                    COALESCE(SUM(valor_saldo), 0) AS valor_vigente,
                    COUNT(DISTINCT id_cliente) AS clientes_con_saldo,
                    COUNT(DISTINCT id_consignacion) AS consignaciones_activas
                FROM (" . $this->wrapSaldoConsignacion($this->baseConsignaciones($where)) . ") s
                WHERE saldo > 0";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'unidades_vigentes'      => (float) ($row['unidades_vigentes'] ?? 0),
            'valor_vigente'          => (float) ($row['valor_vigente'] ?? 0),
            'clientes_con_saldo'     => (int) ($row['clientes_con_saldo'] ?? 0),
            'consignaciones_activas' => (int) ($row['consignaciones_activas'] ?? 0),
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 5 — AUDITORÍA (stock cacheado vs. real del kardex)
    // ════════════════════════════════════════════════════════════════════

    /** Producto×bodega donde productos_bodegas.stock_actual no coincide con la suma real del kardex. */
    /**
     * Discrepancias entre el stock guardado (productos_bodegas.stock_actual) y el saldo
     * real del kardex.
     *
     * El saldo real se agrega UNA vez para toda la empresa y se cruza por LEFT JOIN, en
     * lugar de una subconsulta al kardex por cada fila de productos_bodegas: con 10.000
     * pares producto×bodega eran 10.000 entradas al kardex (4,9 s medidos).
     *
     * @param int|null $limite tope de filas para pantalla; null = sin tope (exportaciones).
     */
    public function getAuditoriaStock(int $idEmpresa, array $filtros, ?int $limite = null): array
    {
        $where = "pb.id_empresa = :id_empresa AND pb.eliminado = false AND p.eliminado = false AND p.inventariable = true AND b.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['id_bodega'])) {
            $where .= " AND pb.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND pb.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        $sql = "WITH kardex_agg AS (
                    SELECT id_producto, id_bodega, SUM(cantidad) AS real_kardex
                    FROM inventario_kardex
                    WHERE id_empresa = :id_empresa AND eliminado = false
                    GROUP BY id_producto, id_bodega
                )
                SELECT * FROM (
                    SELECT pb.id_empresa, pb.id_producto, pb.id_bodega,
                           p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                           b.nombre AS bodega_nombre,
                           pb.stock_actual AS cacheado,
                           COALESCE(ka.real_kardex, 0) AS real_kardex
                    FROM productos_bodegas pb
                    INNER JOIN productos p ON p.id = pb.id_producto AND p.id_empresa = pb.id_empresa
                    INNER JOIN bodegas b ON b.id = pb.id_bodega
                    LEFT JOIN kardex_agg ka ON ka.id_producto = pb.id_producto AND ka.id_bodega = pb.id_bodega
                    WHERE {$where}
                ) t
                WHERE cacheado <> real_kardex
                ORDER BY ABS(cacheado - real_kardex) DESC";
        if ($limite !== null) {
            $sql .= ' LIMIT ' . ((int) $limite + 1);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Corrige productos_bodegas.stock_actual para que coincida con la suma real del kardex. Devuelve [antes, despues]. */
    public function corregirStockAuditoria(int $idProducto, int $idBodega, int $idEmpresa, int $userId): array
    {
        // pg_advisory_xact_lock solo se libera al COMMIT/ROLLBACK: hace falta una transacción
        // explícita para que el candado siga tomado durante las 3 sentencias siguientes (si no,
        // en modo autocommit cada sentencia es su propia transacción y el candado no protege nada).
        $managedTransaction = !$this->db->inTransaction();
        if ($managedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Mismo candado que usa el resto del sistema para leer-antes-de-escribir stock (ver
            // CLAUDE.md §8): evita corregir sobre un valor que un movimiento concurrente está
            // recalculando en este mismo instante.
            (new InventarioRepository())->lockStock($idProducto, $idBodega, $idEmpresa);

            $st = $this->db->prepare("SELECT stock_actual FROM productos_bodegas
                                       WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e AND eliminado = false");
            $st->execute([':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);
            $antes = (float) ($st->fetchColumn() ?: 0);

            $stReal = $this->db->prepare("SELECT COALESCE(SUM(k.cantidad), 0) FROM inventario_kardex k
                                           WHERE k.id_producto = :p AND k.id_bodega = :b AND k.id_empresa = :e AND k.eliminado = false");
            $stReal->execute([':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);
            $real = (float) $stReal->fetchColumn();

            $stUpd = $this->db->prepare("UPDATE productos_bodegas
                                          SET stock_actual = :real, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                                          WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e");
            $stUpd->execute([':real' => $real, ':uid' => $userId, ':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);

            if ($managedTransaction) {
                $this->db->commit();
            }

            return ['antes' => $antes, 'despues' => $real];
        } catch (\Throwable $e) {
            if ($managedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
