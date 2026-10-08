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
     * Orígenes que CORRIGEN un movimiento anterior del mismo documento en vez de mover
     * mercadería nueva: el reverso al pasar un retorno a Borrador/Anulada y su entrada al
     * volver a Emitida, la reversa al eliminar, las ediciones de consignación. El valor es
     * la "familia": el origen del movimiento que corrigen. Las correcciones que se compensan
     * (reverso + reactivación) o que dejan el documento en 0 no movieron nada en neto, y el
     * listado puede ocultarlas (ver cteCorreccionesAnuladas()).
     */
    public const REFERENCIAS_CORRECCION = [
        'CAMBIO_ESTADO_RETORNO_CV'       => 'RETORNO_CV',
        'ELIMINACION_RETORNO_CV'         => 'RETORNO_CV',
        'EDICION_CONSIGNACION_VENTA'     => 'CONSIGNACION_VENTA',
        'ELIMINACION_CONSIGNACION_VENTA' => 'CONSIGNACION_VENTA',
    ];

    public static function esCorreccion(?string $tipo): bool
    {
        return isset(self::REFERENCIAS_CORRECCION[(string) $tipo]);
    }

    /** Lista SQL de literales. Solo para valores de las constantes de esta clase, nunca de la petición. */
    private static function sqlLista(array $valores): string
    {
        return implode(', ', array_map(static fn($v) => "'" . $v . "'", array_values(array_unique($valores))));
    }

    /** Familia del origen: el de una corrección pasa a ser el del movimiento que corrige. */
    private static function sqlFamiliaReferencia(string $columna): string
    {
        $when = '';
        foreach (self::REFERENCIAS_CORRECCION as $tipo => $familia) {
            $when .= " WHEN '{$tipo}' THEN '{$familia}'";
        }
        return "(CASE {$columna}{$when} ELSE {$columna} END)";
    }

    /** `k.referencia_tipo` es una corrección (false, no NULL, para los movimientos sin origen). */
    private static function sqlEsCorreccion(string $alias): string
    {
        return "COALESCE({$alias}.referencia_tipo IN (" . self::sqlLista(array_keys(self::REFERENCIAS_CORRECCION)) . "), false)";
    }

    /**
     * Si el listado oculta las correcciones que se anulan. Con un Origen elegido no se oculta
     * nada: quien filtra por "Retorno (cambio de estado)" quiere ver justo esos movimientos.
     */
    public static function ocultaCorrecciones(array $filtros): bool
    {
        return !empty($filtros['ocultar_correcciones']) && empty($filtros['referencia_tipo']);
    }

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

    /**
     * Excluye del WHERE las bodegas sin acceso para el usuario (módulo Bodegas →
     * pestaña "Accesos"). El controlador las resuelve una sola vez y las mete en $filtros,
     * así que TODAS las consultas del reporte — pantalla, KPIs y exportaciones — descartan
     * las mismas bodegas, y no solo el selector de bodega de la vista.
     *
     * Los ids se interpolan en vez de ir como parámetro porque son enteros que salen de la
     * propia base y porque el mismo WHERE se arma varias veces dentro de una consulta: un
     * placeholder repetido rompería el prepare. Lista vacía (lo habitual, ver
     * BodegaRepository::getIdsBodegasDenegadas) = no se añade nada y la consulta queda
     * exactamente igual que antes.
     */
    private function excluirBodegasDenegadas(array $filtros, string $columna): string
    {
        $ids = array_map('intval', (array) ($filtros['bodegas_denegadas'] ?? []));

        return $ids ? " AND {$columna} NOT IN (" . implode(', ', $ids) . ")" : '';
    }

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
        $where .= $this->excluirBodegasDenegadas($filtros, 'u.id_bodega');
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
     * Filtros de Existencias que se pueden aplicar ANTES de agregar, para que buscar un
     * producto, una bodega o una categoría no agregue igual el kardex de toda la empresa
     * (medido: 1 producto tardaba lo mismo que el listado completo, 1,1 s; 1 categoría,
     * 2,6 s). No reemplazan al WHERE de buildWhereExistencias(), que se sigue aplicando
     * fuera tal cual: solo recortan lo que se lee, así que el resultado no cambia.
     *
     * @return array{0: string, 1: string} [condiciones sin alias para inventario_kardex y
     *                                      productos_bodegas, condiciones con alias cvd]
     */
    private function condicionesExistenciasAntesDeAgregar(array $filtros): array
    {
        $sinAlias = '';
        $cvd = '';
        if (!empty($filtros['id_bodega'])) {
            $sinAlias .= ' AND id_bodega = :id_bodega';
            $cvd      .= ' AND cvd.id_bodega = :id_bodega';
        }
        $sinAlias .= $this->excluirBodegasDenegadas($filtros, 'id_bodega');
        $cvd      .= $this->excluirBodegasDenegadas($filtros, 'cvd.id_bodega');
        if (!empty($filtros['id_producto'])) {
            $sinAlias .= ' AND id_producto = :id_producto';
            $cvd      .= ' AND cvd.id_producto = :id_producto';
        }
        $deProducto = [];
        if (!empty($filtros['id_categoria'])) {
            $deProducto[] = 'fp.id_categoria = :id_categoria';
        }
        if (!empty($filtros['id_marca'])) {
            $deProducto[] = 'fp.id_marca = :id_marca';
        }
        if (!empty($filtros['buscar'])) {
            $deProducto[] = '(fp.nombre ILIKE :buscar OR fp.codigo ILIKE :buscar)';
        }
        if ($deProducto) {
            $productos = 'SELECT fp.id FROM productos fp WHERE fp.id_empresa = :id_empresa AND ' . implode(' AND ', $deProducto);
            $sinAlias .= " AND id_producto IN ({$productos})";
            $cvd      .= " AND cvd.id_producto IN ({$productos})";
        }
        return [$sinAlias, $cvd];
    }

    /**
     * Base: una fila por producto×bodega, con costo unitario (último movimiento) y estado
     * calculado. El universo de pares producto×bodega es la unión de productos_bodegas
     * activos y los pares con movimiento real en inventario_kardex — no solo
     * productos_bodegas — para que un producto con historial de kardex nunca desaparezca de
     * Existencias (con saldo cero o negativo incluido) aunque su fila en productos_bodegas
     * esté ausente o eliminada (caché desincronizado; ver
     * docs/manual/modulos/reporte-inventarios.md, pestaña Auditoría).
     *
     * RENDIMIENTO (medido con 300.000 movimientos, 2.000 productos × 5 bodegas y 20.000
     * líneas de consignación; ver el historial del manual, v1.15):
     *  - El kardex se agrega UNA vez y se cruza con productos_bodegas por FULL JOIN. Antes
     *    era un CTE MATERIALIZED usado dos veces (UNION + LEFT JOIN): sin estadísticas, con
     *    un filtro de bodega el planificador estimaba una fila y cruzaba 2.000 × 2.000 pares
     *    en bucle anidado. El FULL JOIN solo admite hash/merge y da el mismo universo: cada
     *    lado tiene un único par por producto×bodega (GROUP BY de un lado, UNIQUE
     *    (id_producto, id_bodega) del otro).
     *  - Último costo con MAX(ARRAY[fecha, id, costo])[3]: elige la misma fila que
     *    "ORDER BY fecha_movimiento DESC, id DESC" (el id desempata) sin ordenar cada grupo;
     *    ARRAY_AGG(… ORDER BY …) volcaba el ordenamiento a disco.
     *  - Los filtros de producto/bodega/categoría/marca se aplican antes de agregar
     *    (condicionesExistenciasAntesDeAgregar()).
     *  - El consignado se agrega por línea con joins (cteConsignadoPorGrupo()), no con tres
     *    subconsultas por cada línea de consignación.
     *
     * $fechaCorte (opcional): si viene, el saldo/costo/consignado se calculan "a esa fecha"
     * (solo movimientos/consignaciones hasta ese día), en vez del saldo corriente de hoy.
     */
    private function baseExistencias(string $where, array $filtros, bool $conFechaCorte = false): string
    {
        $condCorteKardex = $conFechaCorte ? " AND fecha_movimiento < CAST(:fecha_corte AS date) + 1" : "";
        $condCorteCv     = $conFechaCorte ? " AND cv.fecha_emision < CAST(:fecha_corte AS date) + 1" : "";
        [$condPrevias, $condPreviasCvd] = $this->condicionesExistenciasAntesDeAgregar($filtros);

        return "
            WITH " . $this->cteConsignadoPorGrupo('consignado_agg', [], $condCorteCv, $condPreviasCvd) . "
            SELECT * FROM (
                SELECT u.id_producto, u.id_bodega, COALESCE(u.stock_minimo, 0) AS stock_minimo,
                       COALESCE(u.stock_maximo, 0) AS stock_maximo,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       p.id_categoria, COALESCE(cat.nombre, 'Sin categoría') AS categoria_nombre,
                       p.id_marca, COALESCE(mar.nombre, 'Sin marca') AS marca_nombre,
                       b.nombre AS bodega_nombre,
                       -- Stock en vivo (suma del kardex), no el pb.stock_actual cacheado: ese
                       -- puede desincronizarse si algo toca productos_bodegas sin pasar por el
                       -- kardex — mismo motivo que el Saldo de Movimientos.
                       COALESCE(u.stock_actual, 0) AS stock_actual,
                       COALESCE(u.costo_unitario, 0) AS costo_unitario,
                       COALESCE(ca.consignado, 0) AS consignado
                FROM (
                    SELECT CAST(:id_empresa AS integer) AS id_empresa,
                           COALESCE(ka.id_producto, pb.id_producto) AS id_producto,
                           COALESCE(ka.id_bodega, pb.id_bodega) AS id_bodega,
                           pb.stock_minimo, pb.stock_maximo,
                           ka.stock_actual, ka.costo_unitario
                    FROM (
                        SELECT id_producto, id_bodega,
                               SUM(cantidad) AS stock_actual,
                               (MAX(ARRAY[EXTRACT(EPOCH FROM fecha_movimiento), id, costo_unitario]))[3] AS costo_unitario
                        FROM inventario_kardex
                        WHERE id_empresa = :id_empresa AND eliminado = false{$condCorteKardex}{$condPrevias}
                        GROUP BY id_producto, id_bodega
                    ) ka
                    FULL JOIN (
                        SELECT id_producto, id_bodega, stock_minimo, stock_maximo
                        FROM productos_bodegas
                        WHERE id_empresa = :id_empresa AND eliminado = false{$condPrevias}
                    ) pb ON pb.id_producto = ka.id_producto AND pb.id_bodega = ka.id_bodega
                ) u
                INNER JOIN productos p ON p.id = u.id_producto AND p.id_empresa = u.id_empresa
                INNER JOIN bodegas b ON b.id = u.id_bodega
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
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
        'producto_codigo', 'producto_nombre', 'categoria_nombre', 'bodega_nombre',
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
        $sql = "SELECT * FROM (" . $this->wrapValorYEstado($this->baseExistencias($where, $filtros, $conFechaCorte)) . ") e WHERE 1=1";
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

    /** @param string|null $campoCodigo expresión del código del grupo: solo la tiene "por Producto",
     *  y viaja aparte del label para que la tabla lo pinte en su propia primera columna. */
    private function getExistenciasAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel, ?string $campoCodigo = null): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $conFechaCorte = !empty($filtros['fecha_corte']);
        if ($conFechaCorte) {
            $params[':fecha_corte'] = $filtros['fecha_corte'];
        }
        $base = $this->wrapValorYEstado($this->baseExistencias($where, $filtros, $conFechaCorte));

        // Estado y Consignado se evalúan por producto×bodega ANTES de agrupar, igual que en
        // el detallado: el grupo suma solo los pares que cumplen.
        $condiciones = [];
        if (!empty($filtros['estado_stock'])) {
            $condiciones[] = 'estado_stock = :estado_stock';
            $params[':estado_stock'] = $filtros['estado_stock'];
        }
        if (($filtros['consignado'] ?? '') === 'CON') {
            $condiciones[] = 'consignado > 0';
        } elseif (($filtros['consignado'] ?? '') === 'SIN') {
            $condiciones[] = 'consignado = 0';
        }
        $whereConsignado = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

        $selCodigo = $campoCodigo !== null ? "MAX({$campoCodigo}) AS codigo_grupo," : '';

        $sql = "SELECT * FROM (
                    SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo, {$selCodigo}
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
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_producto', 'producto_nombre', 'producto_codigo');
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
        $where .= $this->excluirBodegasDenegadas($filtros, 'k.id_bodega');
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
            $where .= " AND k.fecha_movimiento < CAST(:fecha_corte AS date) + 1";
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
     * Solo salen los grupos con EXISTENCIA REAL: se descartan las filas cuyo
     * stock total (stock_actual + consignado) es 0. A este nivel el kardex guarda
     * todos los lotes/caducidades que alguna vez entraron, así que un lote agotado
     * — entró 100, salió 100 — seguiría apareciendo en 0 y el listado acaba siendo
     * casi todo histórico muerto. El nivel GENERAL sí conserva el 0, porque ahí la
     * fila es el producto en la bodega y "sin stock" (QUIEBRE) es justo lo que se
     * quiere ver. Se descarta solo el 0 exacto: un total negativo es una
     * inconsistencia y tiene que verse.
     *
     * @param string   $desglose LOTE | CADUCIDAD | LOTE_CADUCIDAD
     * @param int|null $limite   tope de filas para pantalla; null = sin tope (exportaciones).
     */
    /** Filtro "Consignado" (CON / SIN) sobre las filas del desglose por lote/caducidad. */
    private static function condConsignadoDesglose(array $filtros): string
    {
        return match ($filtros['consignado'] ?? '') {
            'CON'   => ' AND t.consignado > 0',
            'SIN'   => ' AND t.consignado = 0',
            default => '',
        };
    }

    public function getExistenciasPorDesglose(int $idEmpresa, array $filtros, string $desglose, ?int $limite = null): array
    {
        $conLote = in_array($desglose, ['LOTE', 'LOTE_CADUCIDAD'], true);
        $conCad  = in_array($desglose, ['CADUCIDAD', 'LOTE_CADUCIDAD'], true);
        $conNup  = $desglose === 'LOTE_CADUCIDAD';

        list($where, $params) = $this->buildWhereExistenciasKardex($idEmpresa, $filtros);
        $condCorteCv = !empty($filtros['fecha_corte']) ? " AND cv.fecha_emision < CAST(:fecha_corte AS date) + 1" : "";

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

        // El consignado va con las subconsultas por línea y NO con cteConsignadoPorGrupo(): se
        // cruza fila a fila del kardex con IS NOT DISTINCT FROM y, con la forma agrupada, el
        // planificador estimaba mal ese cruce (medido: 4,8 s → 9 s). Ver sqlRetornadoCv().
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
                WHERE (t.stock_actual + t.consignado) <> 0" . self::condConsignadoDesglose($filtros) . "
                ORDER BY {$orden}";
        if ($limite !== null) {
            $sql .= ' LIMIT ' . ((int) $limite + 1);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Columnas que pueden formar parte de la clave de una fila del desglose de Existencias.
     * Es la whitelist: el nombre de columna se interpola en el WHERE, así que lo único que
     * puede llegar al SQL sale de aquí (el valor sí va como parámetro).
     */
    private const CLAVE_DESGLOSE = [
        'lote'      => 'numero_lote',
        'nup'       => 'nup',
        'caducidad' => 'fecha_caducidad',
    ];

    /**
     * Tipo con el que se compara cada columna de la clave. `fecha_caducidad` es DATE y su
     * valor llega como texto —o como NULL, que es un grupo válido ("sin caducidad")—: sin el
     * cast explícito PostgreSQL tiene que deducir el tipo del parámetro, y de un NULL suelto
     * no hay nada que deducir. Las demás son varchar y no necesitan nada.
     */
    private const CLAVE_CAST = ['caducidad' => '::date'];

    /**
     * SEGUIMIENTO DE UN STOCK NEGATIVO — movimientos que componen UNA fila de Existencias.
     *
     * Devuelve, en orden cronológico y con saldo corrido, los movimientos de kardex que
     * suman exactamente el stock de esa fila: producto + bodega y las columnas de la clave
     * que la fila puede afirmar (lote / NUP / caducidad; en el nivel "En general", ninguna).
     * Con eso se ve en qué movimiento el saldo cruzó a negativo y con qué documento.
     *
     * Reproduce los filtros de getExistenciasPorDesglose(), NO los de Movimientos. La
     * diferencia que importa es `tipo_ambiente`: Movimientos filtra por el de la empresa y
     * Existencias no, así que si este seguimiento filtrara, su saldo final no cuadraría con
     * el número que está explicando — que es lo único que tiene que hacer. En vez de
     * esconderlos, los marca: `otro_ambiente` señala los movimientos de otro ambiente, que
     * son invisibles en la pestaña Movimientos y una causa típica de un negativo que "no
     * aparece por ningún lado".
     *
     * La clave se compara con IS NOT DISTINCT FROM: en el desglose, "sin lote" (NULL) es un
     * grupo más y se tiene que poder seguir igual que a los demás.
     *
     * @param array<string,string|null> $clave subconjunto de CLAVE_DESGLOSE; la PRESENCIA de
     *                                         la columna dice que forma parte del grupo, y su
     *                                         valor ('' = NULL) qué grupo es.
     * @param array                     $filtros los de Existencias que recortan el kardex
     *                                         (fecha_corte y, en los desgloses, lote/NUP/
     *                                         caducidad). Los decide el controlador según el
     *                                         nivel: en "En general" esos tres NO recortan la
     *                                         suma y no deben llegar aquí.
     */
    public function getSeguimientoClave(int $idEmpresa, int $idProducto, int $idBodega, array $clave, array $filtros = [], int $limite = 1000): array
    {
        // Se reutiliza el MISMO armador de WHERE que produce las filas del desglose y se le
        // fija el producto y la bodega de la fila. No es por ahorrar líneas: si mañana se
        // añade un filtro a Existencias que recorte el kardex, el seguimiento lo hereda y no
        // se puede quedar explicando un número distinto del que está en pantalla.
        $filtros['id_producto'] = $idProducto;
        $filtros['id_bodega']   = $idBodega;
        list($where, $params) = $this->buildWhereExistenciasKardex($idEmpresa, $filtros);
        $params[':tipo_ambiente'] = $this->tipoAmbienteEmpresa($idEmpresa);

        foreach ($clave as $nombre => $valor) {
            if (!isset(self::CLAVE_DESGLOSE[$nombre])) {
                continue;
            }
            $cast = self::CLAVE_CAST[$nombre] ?? '';
            $where .= " AND k." . self::CLAVE_DESGLOSE[$nombre] . " IS NOT DISTINCT FROM :clave_{$nombre}{$cast}";
            $params[":clave_{$nombre}"] = ($valor === '' ? null : $valor);
        }

        // El JOIN con productos y bodegas no es decorativo: buildWhereExistenciasKardex()
        // usa los alias p y b (producto no eliminado e inventariable, bodega no eliminada).
        $sql = "WITH k AS MATERIALIZED (
                    SELECT k.id, k.fecha_movimiento, k.tipo_movimiento, k.referencia_tipo,
                           k.referencia_id, k.cantidad, k.costo_unitario, k.costo_total,
                           k.numero_lote, k.nup, k.fecha_caducidad, k.observaciones, k.created_by,
                           (k.tipo_ambiente IS DISTINCT FROM :tipo_ambiente) AS otro_ambiente
                    FROM inventario_kardex k
                    INNER JOIN productos p ON p.id = k.id_producto AND p.id_empresa = k.id_empresa
                    INNER JOIN bodegas b ON b.id = k.id_bodega
                    WHERE {$where}
                )
                SELECT k.*, u.nombre AS usuario_nombre,
                       SUM(k.cantidad) OVER (ORDER BY k.fecha_movimiento, k.id
                                             ROWS UNBOUNDED PRECEDING) AS saldo
                FROM k
                LEFT JOIN usuarios u ON u.id = k.created_by
                ORDER BY k.fecha_movimiento ASC, k.id ASC
                LIMIT " . ((int) $limite + 1);

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['origen_label'] = self::labelOrigen($r['referencia_tipo']);
        }
        unset($r);

        return $rows;
    }

    /**
     * Los demás grupos lote/NUP/caducidad del MISMO producto y bodega, con su saldo.
     * Es la pista que más veces explica un negativo por lote: el mismo lote escrito de dos
     * formas ("A-123" y "A 123", o uno con un espacio al final) se agrupa por separado y
     * queda uno en positivo y el otro en negativo, cuadrando el total del producto.
     * Ordena por saldo ascendente para que los negativos encabecen la lista.
     */
    public function getGruposDeProductoBodega(int $idEmpresa, int $idProducto, int $idBodega, string $fechaCorte = ''): array
    {
        $params = [':id_empresa' => $idEmpresa, ':id_producto' => $idProducto, ':id_bodega' => $idBodega];
        // El mismo corte que el seguimiento: si las dos tablas del modal no miran el mismo
        // periodo, sus saldos no se pueden comparar y la comparación es justo lo que sirve.
        $condCorte = '';
        if ($fechaCorte !== '') {
            $condCorte = ' AND k.fecha_movimiento < CAST(:fecha_corte AS date) + 1';
            $params[':fecha_corte'] = $fechaCorte;
        }

        $sql = "SELECT k.numero_lote, k.nup, k.fecha_caducidad,
                       SUM(k.cantidad) AS saldo, COUNT(*) AS movimientos,
                       MIN(k.fecha_movimiento) AS primer_movimiento,
                       MAX(k.fecha_movimiento) AS ultimo_movimiento
                FROM inventario_kardex k
                WHERE k.id_empresa = :id_empresa AND k.eliminado = false
                  AND k.id_producto = :id_producto AND k.id_bodega = :id_bodega{$condCorte}
                GROUP BY k.numero_lote, k.nup, k.fecha_caducidad
                ORDER BY SUM(k.cantidad) ASC, k.numero_lote ASC NULLS LAST";

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
                FROM (" . $this->wrapValorYEstado($this->baseExistencias($where, $filtros, $conFechaCorte)) . ") e";

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

    /**
     * tipo_ambiente de la empresa, con la misma expresión que usaba la subconsulta del
     * filtro (si la empresa no existe queda NULL y no coincide ningún movimiento).
     * Va como parámetro y no como "(SELECT … FROM empresas)" dentro del WHERE: con la
     * subconsulta el planificador no conoce el valor, supone 0,5 % de coincidencias y
     * planifica para cientos de filas donde hay decenas de miles.
     */
    private function tipoAmbienteEmpresa(int $idEmpresa): ?string
    {
        // Regla de reportes: solo documentos de PRODUCCIÓN, sin importar el ambiente actual
        // de la empresa (ver App\Helpers\AmbienteReporte).
        return \App\Helpers\AmbienteReporte::PRODUCCION;
    }

    /**
     * Filtros de Movimientos, separados en dos grupos:
     *  - los del propio movimiento (columnas de inventario_kardex, alias k), que van en el
     *    CTE de cteMovimientos() y deciden cuánto kardex se lee;
     *  - los de producto/bodega (alias p, b, y el buscador que mezcla k/p/b), que se
     *    aplican al cruzar con productos y bodegas.
     * Los dos se aplican ANTES del saldo corrido, igual que antes: la ventana ve
     * exactamente las mismas filas. Categoría y marca además se adelantan al CTE (como
     * lista de productos) para no materializar todo el periodo y descartarlo después.
     *
     * @return array{0: string, 1: string, 2: array} [condiciones del kardex, condiciones de producto/bodega, parámetros]
     */
    private function buildWhereMovimientos(int $idEmpresa, array $filtros, bool $conAmbiente = true): array
    {
        // $conAmbiente = false solo para el cruce con Existencias del resumen de cuadre:
        // Existencias suma el kardex de todos los ambientes (ver getSeguimientoClave()).
        $whereKardex = "k.id_empresa = :id_empresa AND k.eliminado = false";
        $whereFuera  = "true";
        $params = [':id_empresa' => $idEmpresa];
        if ($conAmbiente) {
            $whereKardex .= " AND k.tipo_ambiente = :tipo_ambiente";
            $params[':tipo_ambiente'] = $this->tipoAmbienteEmpresa($idEmpresa);
        }

        if (!empty($filtros['fecha_desde'])) {
            $whereKardex .= " AND k.fecha_movimiento >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $whereKardex .= " AND k.fecha_movimiento <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_bodega'])) {
            $whereKardex .= " AND k.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        $whereKardex .= $this->excluirBodegasDenegadas($filtros, 'k.id_bodega');
        if (!empty($filtros['id_producto'])) {
            $whereKardex .= " AND k.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        $deProducto = [];
        if (!empty($filtros['id_categoria'])) {
            $whereFuera  .= " AND p.id_categoria = :id_categoria";
            $deProducto[] = "fp.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $whereFuera  .= " AND p.id_marca = :id_marca";
            $deProducto[] = "fp.id_marca = :id_marca";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }
        if ($deProducto) {
            $whereKardex .= " AND k.id_producto IN (SELECT fp.id FROM productos fp WHERE " . implode(' AND ', $deProducto) . ")";
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $whereKardex .= " AND k.tipo_movimiento = :tipo_movimiento";
            $params[':tipo_movimiento'] = $filtros['tipo_movimiento'];
        }
        if (!empty($filtros['referencia_tipo'])) {
            $whereKardex .= " AND k.referencia_tipo = :referencia_tipo";
            $params[':referencia_tipo'] = $filtros['referencia_tipo'];
        }
        if (!empty($filtros['id_usuario'])) {
            $whereKardex .= " AND k.created_by = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }
        if (!empty($filtros['numero_lote'])) {
            $whereKardex .= " AND k.numero_lote ILIKE :numero_lote";
            $params[':numero_lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $whereKardex .= " AND k.nup ILIKE :nup";
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['fecha_caducidad_desde'])) {
            $whereKardex .= " AND k.fecha_caducidad >= :fecha_caducidad_desde";
            $params[':fecha_caducidad_desde'] = $filtros['fecha_caducidad_desde'];
        }
        if (!empty($filtros['fecha_caducidad_hasta'])) {
            $whereKardex .= " AND k.fecha_caducidad <= :fecha_caducidad_hasta";
            $params[':fecha_caducidad_hasta'] = $filtros['fecha_caducidad_hasta'];
        }
        if (!empty($filtros['observaciones'])) {
            $whereKardex .= " AND k.observaciones ILIKE :observaciones";
            $params[':observaciones'] = '%' . $filtros['observaciones'] . '%';
        }
        if (!empty($filtros['buscar'])) {
            $whereFuera .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar OR b.nombre ILIKE :buscar OR k.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        return [$whereKardex, $whereFuera, $params];
    }

    /**
     * Movimientos que pasan los filtros propios del kardex, en un CTE MATERIALIZED SIN
     * joins. Cuando el kardex se cruzaba directo con productos, el planificador lo recorría
     * entero por idx_kardex_empresa_producto (orden útil para el cruce y el saldo) leyendo
     * al azar toda la empresa para quedarse con el año: 5,1 s el año en curso, que es la
     * búsqueda con la que arranca la pestaña. Aislado, elige el recorrido por fecha: 0,8 s
     * (y "Por mes" de 4,7 s a 0,4 s). El CTE se llama k para que las expresiones de
     * agrupación (k.fecha_movimiento, k.tipo_movimiento…) sigan valiendo.
     */
    private function cteMovimientos(string $whereKardex, bool $ocultarCorrecciones = false): string
    {
        $select = "SELECT k.id, k.id_producto, k.id_bodega, k.fecha_movimiento, k.tipo_movimiento,
                           k.referencia_tipo, k.referencia_id, k.cantidad, k.costo_unitario, k.costo_total,
                           k.numero_lote, k.fecha_caducidad, k.nup, k.observaciones
                    FROM inventario_kardex k
                    WHERE {$whereKardex}";
        if (!$ocultarCorrecciones) {
            return "WITH k AS MATERIALIZED ({$select})";
        }
        return "WITH " . $this->cteCorreccionesAnuladas($whereKardex) . ",
                k AS MATERIALIZED ({$select} AND NOT " . self::sqlEsAnulada('k') . ")";
    }

    /**
     * CTEs de las correcciones que se anulan dentro del periodo. La clave es siempre
     * documento + producto + bodega + lote + NUP (cada línea de un retorno es un NUP):
     *
     *  - `anuladas_corr`: correcciones del MISMO origen que suman 0 entre sí. Es el caso
     *    típico: un retorno pasado a Borrador (−1) y vuelto a Emitir (+1). Se ocultan solo
     *    esas dos filas; la entrada original del retorno sigue a la vista.
     *  - `anuladas_doc`: el documento entero (su movimiento original y sus correcciones)
     *    suma 0 y tuvo alguna corrección: un retorno creado y eliminado, o pasado a Borrador
     *    y dejado así. En neto no movió nada, y se oculta todo.
     *
     * Ocultar esos movimientos no cambia el saldo final ni el inicial (suman 0, y el saldo
     * inicial se calcula aparte con todo el kardex anterior). El saldo corrido de las filas
     * que quedan se lee como si la corrección no hubiera existido, que es lo que se busca.
     * Una corrección que NO se compensa sigue visible: p. ej. el reverso de un retorno que
     * se quedó en Borrador cuando su entrada original es de un periodo anterior.
     *
     * Solo leen los movimientos de las familias con correcciones, no todo el periodo.
     */
    private function cteCorreccionesAnuladas(string $whereKardex): string
    {
        $familias = array_merge(array_keys(self::REFERENCIAS_CORRECCION), array_values(self::REFERENCIAS_CORRECCION));
        $clave = "k.referencia_id, k.id_producto, k.id_bodega, COALESCE(k.numero_lote, '') AS lote, COALESCE(k.nup, '') AS nup";
        return "anuladas_corr AS MATERIALIZED (
                    SELECT k.referencia_tipo AS origen, {$clave}
                    FROM inventario_kardex k
                    WHERE {$whereKardex} AND k.referencia_tipo IN (" . self::sqlLista(array_keys(self::REFERENCIAS_CORRECCION)) . ")
                    GROUP BY 1, 2, 3, 4, 5, 6
                    HAVING SUM(k.cantidad) = 0
                ),
                anuladas_doc AS MATERIALIZED (
                    SELECT " . self::sqlFamiliaReferencia('k.referencia_tipo') . " AS origen, {$clave}
                    FROM inventario_kardex k
                    WHERE {$whereKardex} AND k.referencia_tipo IN (" . self::sqlLista($familias) . ")
                    GROUP BY 1, 2, 3, 4, 5, 6
                    HAVING SUM(k.cantidad) = 0 AND BOOL_OR(" . self::sqlEsCorreccion('k') . ")
                )";
    }

    /** El movimiento `$alias` pertenece a una clave de `anuladas_corr` o de `anuladas_doc`. */
    private static function sqlEsAnulada(string $alias): string
    {
        $mismaClave = "an.referencia_id = {$alias}.referencia_id
                          AND an.id_producto = {$alias}.id_producto AND an.id_bodega = {$alias}.id_bodega
                          AND an.lote = COALESCE({$alias}.numero_lote, '') AND an.nup = COALESCE({$alias}.nup, '')";
        return "(EXISTS (SELECT 1 FROM anuladas_corr an
                         WHERE an.origen = {$alias}.referencia_tipo AND {$mismaClave})
                 OR EXISTS (SELECT 1 FROM anuladas_doc an
                         WHERE an.origen = " . self::sqlFamiliaReferencia("{$alias}.referencia_tipo") . " AND {$mismaClave}))";
    }

    private function fromMovimientos(string $whereFuera): string
    {
        return "FROM k
                 INNER JOIN productos p ON p.id = k.id_producto
                 INNER JOIN bodegas b ON b.id = k.id_bodega
                 WHERE {$whereFuera}";
    }

    /**
     * @param int|null $limite tope de filas para pantalla; null = sin tope (exportaciones).
     *
     * OJO con el coste: el saldo corrido es una función de ventana sobre TODAS las filas
     * que cumplen el filtro, así que se calcula entero antes de aplicar el LIMIT — el tope
     * recorta lo que se envía, no lo que se lee. Lo que de verdad abarata esta consulta es
     * el filtro de fechas, y por eso la pestaña arranca con un año seleccionado en vez de
     * "Todos".
     */
    public function getMovimientosDetalle(int $idEmpresa, array $filtros, ?int $limite = null): array
    {
        list($whereKardex, $whereFuera, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        // El "Saldo" NO se lee de k.stock_posterior (es un valor cacheado en cada fila que
        // puede quedar desincronizado si algo externo toca productos_bodegas sin pasar por
        // el kardex). En su lugar se calcula en vivo: suma corrida de "cantidad" (entradas
        // positivas, salidas negativas) por producto+bodega, en orden cronológico, sobre las
        // filas que cumplen los filtros actuales. Con fecha "Desde", el saldo corrido parte
        // del SALDO INICIAL de cada producto+bodega (todo el kardex anterior a esa fecha);
        // ver getMovimientosDetalleConSaldoInicial().
        if (self::llevaSaldoInicial($filtros)) {
            return $this->getMovimientosDetalleConSaldoInicial($idEmpresa, $filtros, $limite, $whereKardex, $whereFuera, $params);
        }
        $sql = $this->cteMovimientos($whereKardex, self::ocultaCorrecciones($filtros)) . "
                SELECT k.id, k.fecha_movimiento, k.tipo_movimiento, k.referencia_tipo, k.referencia_id,
                       k.cantidad, k.costo_unitario, k.costo_total,
                       SUM(k.cantidad) OVER (
                           PARTITION BY k.id_producto, k.id_bodega
                           ORDER BY k.fecha_movimiento, k.id
                           ROWS UNBOUNDED PRECEDING
                       ) AS saldo,
                       k.numero_lote, k.fecha_caducidad, k.nup, k.observaciones,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       b.nombre AS bodega_nombre
                " . $this->fromMovimientos($whereFuera) . "
                ORDER BY k.fecha_movimiento ASC, k.id ASC"
                . ($limite !== null ? ' LIMIT ' . ((int) $limite + 1) : '');

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['origen_label']  = self::labelOrigen($r['referencia_tipo']);
            $r['es_correccion'] = self::esCorreccion($r['referencia_tipo']);
        }
        unset($r);
        return $rows;
    }

    /**
     * Filtros que eligen una CLASE de movimiento (no un producto, bodega o lote). Con uno
     * activo el saldo corrido ya no es un stock —p. ej. solo entradas = entradas
     * acumuladas—, así que sumarle un saldo inicial mezclaría dos cosas distintas: en ese
     * caso el detalle se queda como siempre, sin saldo inicial.
     */
    private const FILTROS_SIN_SALDO_INICIAL = ['tipo_movimiento', 'referencia_tipo', 'id_usuario', 'observaciones'];

    /** El saldo inicial solo aplica con fecha "Desde" y sin filtros de clase de movimiento. */
    public static function llevaSaldoInicial(array $filtros): bool
    {
        if (empty($filtros['fecha_desde'])) {
            return false;
        }
        foreach (self::FILTROS_SIN_SALDO_INICIAL as $clave) {
            if (!empty($filtros[$clave])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Detalle de Movimientos que arranca con un SALDO INICIAL por producto+bodega: lo que
     * sumaba el kardex antes de la fecha "Desde" (mismos filtros de empresa, ambiente,
     * producto, bodega, categoría, marca, lote, NUP y caducidad). Ese saldo se suma a la
     * suma corrida de cada fila, y además sale como una fila propia al principio
     * (es_saldo_inicial = 1), antes de los movimientos del periodo.
     *
     * Qué pares llevan fila de saldo inicial (solo si es distinto de cero):
     *  - sin producto elegido: los producto+bodega que tienen movimientos en el periodo
     *    (restringir a esos pares evita sumar el histórico de toda la empresa y cientos
     *    de filas de productos que no se movieron);
     *  - con producto elegido: todas sus bodegas, aunque no se hayan movido en el
     *    periodo — es el kardex de ese producto, y su saldo tiene que verse igual.
     *
     * Todo en una sola consulta: el CTE `si` se calcula una vez y sirve tanto para las
     * filas de saldo inicial como para el saldo corrido.
     */
    private function getMovimientosDetalleConSaldoInicial(int $idEmpresa, array $filtros, ?int $limite, string $whereKardex, string $whereFuera, array $params): array
    {
        // Mismos filtros de producto/bodega/lote, sin el rango del periodo ni el buscador
        // (el buscador mezcla k.observaciones, que no aplica a un saldo agregado).
        $filtrosSaldo = $filtros;
        unset($filtrosSaldo['fecha_desde'], $filtrosSaldo['fecha_hasta'], $filtrosSaldo['buscar']);
        list($whereKardexSaldo, , $paramsSaldo) = $this->buildWhereMovimientos($idEmpresa, $filtrosSaldo);
        $params += $paramsSaldo;   // mismos nombres, mismos valores

        $conProducto = !empty($filtros['id_producto']);
        $ctePares = $conProducto ? '' : ",
                pares AS MATERIALIZED (
                    SELECT DISTINCT k.id_producto, k.id_bodega
                    " . $this->fromMovimientos($whereFuera) . "
                )";
        $condPares = $conProducto ? '' : ' AND (k.id_producto, k.id_bodega) IN (SELECT id_producto, id_bodega FROM pares)';
        $condBuscarSaldo = !empty($filtros['buscar'])
            ? ' AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar OR b.nombre ILIKE :buscar)'
            : '';

        $sql = $this->cteMovimientos($whereKardex, self::ocultaCorrecciones($filtros)) . $ctePares . ",
                si AS MATERIALIZED (
                    SELECT k.id_producto, k.id_bodega, SUM(k.cantidad) AS saldo,
                           (MAX(ARRAY[EXTRACT(EPOCH FROM k.fecha_movimiento), k.id, k.costo_unitario]))[3] AS costo_unitario
                    FROM inventario_kardex k
                    WHERE {$whereKardexSaldo}
                      AND k.fecha_movimiento < :fecha_desde{$condPares}
                    GROUP BY k.id_producto, k.id_bodega
                )
                SELECT * FROM (
                    SELECT 0 AS es_saldo_inicial, k.id, k.fecha_movimiento, k.tipo_movimiento,
                           k.referencia_tipo, k.referencia_id, k.cantidad, k.costo_unitario, k.costo_total,
                           COALESCE(si.saldo, 0) + SUM(k.cantidad) OVER (
                               PARTITION BY k.id_producto, k.id_bodega
                               ORDER BY k.fecha_movimiento, k.id
                               ROWS UNBOUNDED PRECEDING
                           ) AS saldo,
                           k.numero_lote, k.fecha_caducidad, k.nup, k.observaciones,
                           p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                           b.nombre AS bodega_nombre
                    FROM k
                    INNER JOIN productos p ON p.id = k.id_producto
                    INNER JOIN bodegas b ON b.id = k.id_bodega
                    LEFT JOIN si ON si.id_producto = k.id_producto AND si.id_bodega = k.id_bodega
                    WHERE {$whereFuera}

                    UNION ALL

                    SELECT 1, NULL, CAST(:fecha_desde AS timestamp), NULL,
                           NULL, NULL, 0, si.costo_unitario, NULL,
                           si.saldo,
                           NULL, NULL, NULL, NULL,
                           p.codigo, p.nombre,
                           b.nombre
                    FROM si
                    INNER JOIN productos p ON p.id = si.id_producto
                    INNER JOIN bodegas b ON b.id = si.id_bodega
                    WHERE si.saldo <> 0{$condBuscarSaldo}
                ) t
                ORDER BY t.es_saldo_inicial DESC,
                         CASE WHEN t.es_saldo_inicial = 1 THEN t.producto_nombre END,
                         CASE WHEN t.es_saldo_inicial = 1 THEN t.bodega_nombre END,
                         t.fecha_movimiento ASC, t.id ASC"
                . ($limite !== null ? ' LIMIT ' . ((int) $limite + 1) : '');

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['es_saldo_inicial'] = (int) $r['es_saldo_inicial'] === 1;
            $r['origen_label'] = $r['es_saldo_inicial'] ? 'Saldo inicial' : self::labelOrigen($r['referencia_tipo']);
            $r['es_correccion'] = !$r['es_saldo_inicial'] && self::esCorreccion($r['referencia_tipo']);
        }
        unset($r);
        return $rows;
    }

    /** @param string|null $campoCodigo expresión del código del grupo: solo la tiene "por Producto",
     *  y viaja aparte del label para que la tabla lo pinte en su propia primera columna. */
    private function getMovimientosAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabelExpr, string $orderBy, ?string $campoCodigo = null): array
    {
        list($whereKardex, $whereFuera, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $selCodigo = $campoCodigo !== null ? "MAX({$campoCodigo}) AS codigo_grupo," : '';
        $sql = $this->cteMovimientos($whereKardex, self::ocultaCorrecciones($filtros)) . "
                SELECT {$campoId} AS id_grupo, MAX({$campoLabelExpr}) AS nombre_grupo, {$selCodigo}
                       COUNT(*) AS cantidad_movimientos,
                       SUM(CASE WHEN k.cantidad > 0 THEN k.cantidad ELSE 0 END) AS total_entradas,
                       SUM(CASE WHEN k.cantidad < 0 THEN ABS(k.cantidad) ELSE 0 END) AS total_salidas,
                       SUM(k.cantidad) AS saldo_neto,
                       SUM(k.costo_total) AS costo_total
                " . $this->fromMovimientos($whereFuera) . "
                GROUP BY {$campoId}
                ORDER BY {$orderBy}";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovimientosAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.id_producto', 'p.nombre', 'cantidad_movimientos DESC', 'p.codigo');
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
        list($whereKardex, $whereFuera, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $sql = $this->cteMovimientos($whereKardex, self::ocultaCorrecciones($filtros)) . "
                SELECT
                    COUNT(*) AS total_movimientos,
                    COALESCE(SUM(CASE WHEN k.cantidad > 0 THEN k.cantidad ELSE 0 END), 0) AS total_entradas,
                    COALESCE(SUM(CASE WHEN k.cantidad < 0 THEN ABS(k.cantidad) ELSE 0 END), 0) AS total_salidas,
                    COALESCE(SUM(k.cantidad), 0) AS saldo_neto
                " . $this->fromMovimientos($whereFuera);

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
     * El resumen de cuadre solo tiene sentido para UN producto y sin filtros de clase de
     * movimiento: con "solo salidas" o "solo del usuario X" el saldo ya no es stock.
     */
    public static function resumenCuadreAplica(array $filtros): bool
    {
        if (empty($filtros['id_producto'])) {
            return false;
        }
        foreach (self::FILTROS_SIN_SALDO_INICIAL as $clave) {
            if (!empty($filtros[$clave])) {
                return false;
            }
        }
        return true;
    }

    /**
     * RESUMEN DE CUADRE de un producto (pestaña Movimientos), una fila por bodega:
     *
     *   saldo inicial + entradas − salidas ± correcciones = saldo final  →  ¿= Existencias?
     *
     * - Saldo inicial: kardex anterior a "Desde", igual que la fila de saldo inicial del detalle.
     * - Entradas / salidas: movimientos del periodo que NO son correcciones.
     * - Correcciones: los orígenes de REFERENCIAS_CORRECCION (reversos por cambio de estado,
     *   reactivaciones, eliminaciones, ediciones). Con "ocultar correcciones que se anulan",
     *   las que se compensan dentro del periodo no suman en ninguna columna (su neto es 0) y
     *   solo se cuentan en `ocultos_mov`, igual que en el listado.
     * - Existencias: lo que muestra la pestaña Existencias a la fecha "Hasta" con los mismos
     *   filtros de bodega/lote/NUP/caducidad. Existencias suma el kardex de TODOS los ambientes
     *   y Movimientos solo el de la empresa: si hay movimientos de otro ambiente, esa es la
     *   diferencia, y se informa (`otro_amb_mov`, `otro_amb_cant`) en vez de esconderla.
     *
     * Solo lee el kardex de un producto, así que son consultas acotadas.
     *
     * @return array<int,array<string,mixed>> filas por bodega, ordenadas por nombre.
     */
    public function getResumenCuadre(int $idEmpresa, array $filtros): array
    {
        $ocultar = self::ocultaCorrecciones($filtros);

        // 1. Periodo: las mismas filas que el listado.
        list($whereKardex, , $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $visible = $ocultar ? 'NOT ' . self::sqlEsAnulada('k') : 'true';
        $sql = ($ocultar ? 'WITH ' . $this->cteCorreccionesAnuladas($whereKardex) : '') . "
                SELECT m.id_bodega,
                       COALESCE(SUM(m.cantidad) FILTER (WHERE m.visible AND NOT m.es_corr AND m.cantidad > 0), 0) AS entradas,
                       COALESCE(-SUM(m.cantidad) FILTER (WHERE m.visible AND NOT m.es_corr AND m.cantidad < 0), 0) AS salidas,
                       COALESCE(SUM(m.cantidad) FILTER (WHERE m.visible AND m.es_corr), 0) AS correcciones,
                       COUNT(*) FILTER (WHERE m.visible AND m.es_corr) AS correcciones_mov,
                       COUNT(*) FILTER (WHERE NOT m.visible) AS ocultos_mov
                FROM (
                    SELECT k.id_bodega, k.cantidad, " . self::sqlEsCorreccion('k') . " AS es_corr, {$visible} AS visible
                    FROM inventario_kardex k
                    WHERE {$whereKardex}
                ) m
                GROUP BY m.id_bodega";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $periodo = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $periodo[(int) $r['id_bodega']] = $r;
        }

        // Sin el rango del periodo: los dos cortes siguientes ponen el suyo.
        $filtrosSinFechas = $filtros;
        unset($filtrosSinFechas['fecha_desde'], $filtrosSinFechas['fecha_hasta'], $filtrosSinFechas['buscar']);

        // 2. Saldo inicial (mismo criterio que getMovimientosDetalleConSaldoInicial()).
        $inicial = [];
        if (!empty($filtros['fecha_desde'])) {
            list($whereSaldo, , $paramsSaldo) = $this->buildWhereMovimientos($idEmpresa, $filtrosSinFechas);
            $paramsSaldo[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            $st = $this->db->prepare("SELECT k.id_bodega, SUM(k.cantidad) AS saldo
                                        FROM inventario_kardex k
                                       WHERE {$whereSaldo} AND k.fecha_movimiento < :fecha_desde
                                       GROUP BY k.id_bodega");
            $st->execute($paramsSaldo);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $inicial[(int) $r['id_bodega']] = (float) $r['saldo'];
            }
        }

        // 3. Existencias a la fecha "Hasta", sin filtro de ambiente (como la pestaña Existencias).
        list($whereEx, , $paramsEx) = $this->buildWhereMovimientos($idEmpresa, $filtrosSinFechas, false);
        if (!empty($filtros['fecha_hasta'])) {
            $whereEx .= " AND k.fecha_movimiento < CAST(:fecha_corte AS date) + 1";
            $paramsEx[':fecha_corte'] = $filtros['fecha_hasta'];
        }
        $paramsEx[':ambiente_empresa'] = $this->tipoAmbienteEmpresa($idEmpresa);
        $st = $this->db->prepare("SELECT k.id_bodega, MAX(b.nombre) AS bodega_nombre,
                                         SUM(k.cantidad) AS existencias,
                                         COUNT(*) FILTER (WHERE k.tipo_ambiente IS DISTINCT FROM :ambiente_empresa) AS otro_amb_mov,
                                         COALESCE(SUM(k.cantidad) FILTER (WHERE k.tipo_ambiente IS DISTINCT FROM :ambiente_empresa), 0) AS otro_amb_cant
                                    FROM inventario_kardex k
                                    INNER JOIN bodegas b ON b.id = k.id_bodega
                                   WHERE {$whereEx}
                                   GROUP BY k.id_bodega");
        $st->execute($paramsEx);

        // Las bodegas del corte 3 contienen a las de 1 y 2: son todos los movimientos hasta
        // "Hasta", de cualquier ambiente.
        $filas = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $idBodega = (int) $r['id_bodega'];
            $p = $periodo[$idBodega] ?? [];
            $saldoInicial = $inicial[$idBodega] ?? 0.0;
            $entradas     = (float) ($p['entradas'] ?? 0);
            $salidas      = (float) ($p['salidas'] ?? 0);
            $correcciones = (float) ($p['correcciones'] ?? 0);
            $movimientos  = !empty($p);
            $saldoFinal   = $saldoInicial + $entradas - $salidas + $correcciones;
            $existencias  = (float) $r['existencias'];

            // Bodega sin nada que contar: ni saldo ni movimientos en el periodo.
            if (!$movimientos && abs($saldoInicial) < 1e-9 && abs($existencias) < 1e-9) {
                continue;
            }
            $filas[] = [
                'id_bodega'        => $idBodega,
                'bodega_nombre'    => (string) $r['bodega_nombre'],
                'saldo_inicial'    => $saldoInicial,
                'entradas'         => $entradas,
                'salidas'          => $salidas,
                'correcciones'     => $correcciones,
                'correcciones_mov' => (int) ($p['correcciones_mov'] ?? 0),
                'ocultos_mov'      => (int) ($p['ocultos_mov'] ?? 0),
                'saldo_final'      => $saldoFinal,
                'existencias'      => $existencias,
                'otro_amb_mov'     => (int) $r['otro_amb_mov'],
                'otro_amb_cant'    => (float) $r['otro_amb_cant'],
                'cuadra'           => abs($saldoFinal - $existencias) < 0.000001,
            ];
        }
        usort($filas, static fn($a, $b) => strcmp($a['bodega_nombre'], $b['bodega_nombre']));
        return $filas;
    }

    /**
     * Años con movimientos de kardex (alimenta el <select> "Año" de la pestaña
     * Movimientos; se ejecuta en cada carga del módulo).
     *
     * Con idx_kardex_empresa_fecha se salta de año en año por el índice (loose index
     * scan): tantos saltos como años tenga el histórico, ~1 ms. Dos detalles medidos:
     *  - "id_empresa BETWEEN :e AND :e" + "ORDER BY id_empresa, fecha_movimiento" en vez de
     *    "id_empresa = :e ORDER BY fecha_movimiento": con la igualdad el planificador trata
     *    id_empresa como constante, cualquier índice por fecha le sirve para el orden, y
     *    elegía idx_kardex_fecha (solo fecha, sin empresa), que recorre los movimientos de
     *    TODAS las empresas hasta dar con el primero de esta (88 ms en la prueba, y crece con
     *    el volumen de las demás empresas). Así el único índice que da ese orden es el
     *    compuesto.
     *  - Sin ese índice el salto no tiene atajo y cada paso relee toda la empresa: ahí se usa
     *    el DISTINCT de una sola pasada (mismo resultado). Ver
     *    database/20260916_reporte_inventarios_indices_ajuste.sql.
     */
    public function getAniosMovimientos(int $idEmpresa): array
    {
        if (!$this->indiceExiste('idx_kardex_empresa_fecha')) {
            $st = $this->db->prepare("SELECT DISTINCT EXTRACT(YEAR FROM fecha_movimiento)::int AS anio
                                        FROM inventario_kardex
                                       WHERE id_empresa = :id_empresa AND eliminado = false
                                         AND fecha_movimiento IS NOT NULL
                                       ORDER BY anio DESC");
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
        }

        $sql = "WITH RECURSIVE saltos AS (
                    (SELECT fecha_movimiento AS f
                       FROM inventario_kardex
                      WHERE id_empresa BETWEEN :id_empresa AND :id_empresa AND eliminado = false
                        AND fecha_movimiento IS NOT NULL
                      ORDER BY id_empresa, fecha_movimiento
                      LIMIT 1)
                    UNION ALL
                    SELECT (SELECT k.fecha_movimiento
                              FROM inventario_kardex k
                             WHERE k.id_empresa BETWEEN :id_empresa AND :id_empresa AND k.eliminado = false
                               AND k.fecha_movimiento >= date_trunc('year', s.f) + interval '1 year'
                             ORDER BY k.id_empresa, k.fecha_movimiento
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

    /** @param string|null $campoCodigo expresión del código del grupo: solo la tiene "por Producto",
     *  y viaja aparte del label para que la tabla lo pinte en su propia primera columna. */
    private function getValorizacionAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel, ?string $campoCodigo = null): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $base = $this->wrapValorYEstado($this->baseExistencias($where, $filtros));
        $selCodigo = $campoCodigo !== null ? "MAX({$campoCodigo}) AS codigo_grupo," : '';

        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo, {$selCodigo}
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
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_producto', 'producto_nombre', 'producto_codigo');
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
                FROM (" . $this->wrapValorYEstado($this->baseExistencias($where, $filtros)) . ") e
                WHERE valor_total > 0";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $sqlTop = "SELECT producto_nombre, valor_total
                   FROM (" . $this->wrapValorYEstado($this->baseExistencias($where, $filtros)) . ") e
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

    // Retornado, facturado y entregado a cambio de una línea de consignación. Cada uno tiene
    // dos formas con el MISMO criterio (las condiciones de vigencia de abajo):
    //  - agrupada por línea para muchas líneas a la vez (subconsulta con los ids de esas
    //    líneas), cruzada con LEFT JOIN: la usan Existencias y Consignaciones. Antes eran
    //    subconsultas correlacionadas, tres o cuatro búsquedas por cada línea (~20.000 por
    //    Mostrar en la prueba de carga). Filtrar por "id IN (líneas)" en vez de por empresa
    //    mantiene exactamente el criterio anterior;
    //  - correlacionada con cvd.id: la usa solo el desglose por lote/caducidad, que cruza el
    //    consignado fila a fila del kardex y con la forma agrupada el planificador estimaba
    //    mal ese cruce (medido: 4,8 s → 9 s). Ahí se deja como estaba.

    /** Retorno vigente (mismo criterio que ConsignacionFacturaRepository). */
    private const COND_RETORNO_VIGENTE = "rcd.eliminado = false AND rc.eliminado = false AND rc.estado = 'Emitida'";
    /** Facturación vigente (documentos 'facturada'). */
    private const COND_FACTURA_VIGENTE = "cfd.eliminado = false AND cf.eliminado = false AND cf.estado = 'facturada'";

    /** Cantidad retornada activa por línea de consignación, para las líneas de $idsLineas. */
    private function sqlRetornadoPorLinea(string $idsLineas): string
    {
        return "SELECT rcd.id_consignacion_detalle AS id, SUM(rcd.cantidad) AS total
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle IN ({$idsLineas})
                  AND " . self::COND_RETORNO_VIGENTE . "
                GROUP BY rcd.id_consignacion_detalle";
    }

    /** Cantidad facturada por línea de consignación, para las líneas de $idsLineas. */
    private function sqlFacturadoPorLinea(string $idsLineas): string
    {
        return "SELECT cfd.id_consignacion_detalle AS id, SUM(cfd.cantidad) AS total
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                WHERE cfd.id_consignacion_detalle IN ({$idsLineas})
                  AND " . self::COND_FACTURA_VIGENTE . "
                GROUP BY cfd.id_consignacion_detalle";
    }

    /** Cantidad retornada activa de UNA línea (correlacionada con cvd.id). */
    private function sqlRetornadoCv(): string
    {
        return "SELECT SUM(rcd.cantidad) AS total
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle = cvd.id
                  AND " . self::COND_RETORNO_VIGENTE;
    }

    /** Cantidad facturada de UNA línea (correlacionada con cvd.id). */
    private function sqlFacturadoCv(): string
    {
        return "SELECT SUM(cfd.cantidad) AS total
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                WHERE cfd.id_consignacion_detalle = cvd.id
                  AND " . self::COND_FACTURA_VIGENTE;
    }

    /** Cantidad entregada a cambio de UNA línea (correlacionada con cvd.id). */
    private function sqlCambiadoCv(): string
    {
        return \App\repositories\modulos\CambioProductoCvRepository::sqlEntregadoEnCambios('cvd.id') . " ";
    }

    /** Cantidad ENTREGADA A CAMBIO por línea (Cambios de productos Emitida): la unidad pasó a ser
     *  del cliente como reposición, así que sale del saldo consignado igual que una facturación.
     *  La definición vive en CambioProductoCvRepository, junto a la versión por línea que usan
     *  Retornos y Facturación CV. */
    private function sqlCambiadoPorLinea(string $idsLineas): string
    {
        return \App\repositories\modulos\CambioProductoCvRepository::sqlEntregadoEnCambiosPorLinea($idsLineas);
    }

    /**
     * Dos CTE: "{nombre}_lineas" (líneas de consignación vigentes de la empresa, hasta la
     * fecha de corte y con los filtros previos) y "{nombre}" (saldo consignado agrupado por
     * id_empresa + producto + bodega + las $columnasLinea pedidas: lote, nup,
     * fecha_caducidad). Lo usan Existencias (por producto×bodega) y su desglose por
     * lote/caducidad; comparten así una sola definición del saldo en poder del cliente.
     *
     * Devuelve el texto de los CTE sin la palabra WITH.
     */
    private function cteConsignadoPorGrupo(string $nombre, array $columnasLinea, string $condCorteCv, string $condCvd): string
    {
        $columnas = '';
        foreach ($columnasLinea as $columna) {
            $columnas .= ', l.' . $columna;
        }
        $idsLineas = "SELECT id FROM {$nombre}_lineas";

        return "{$nombre}_lineas AS MATERIALIZED (
                    SELECT cvd.id, cvd.id_empresa, cvd.id_producto, cvd.id_bodega, cvd.cantidad,
                           cvd.lote, cvd.nup, cvd.fecha_caducidad
                    FROM consignaciones_ventas_detalles cvd
                    INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                    WHERE cvd.id_empresa = :id_empresa AND cvd.eliminado = false
                      AND cv.eliminado = false{$condCorteCv}{$condCvd}
                ),
                {$nombre} AS (
                    SELECT l.id_empresa, l.id_producto, l.id_bodega{$columnas},
                           SUM(l.cantidad - COALESCE(ret.total, 0) - COALESCE(fac.total, 0) - COALESCE(cam.total, 0)) AS consignado
                    FROM {$nombre}_lineas l
                    LEFT JOIN (" . $this->sqlRetornadoPorLinea($idsLineas) . ") ret ON ret.id = l.id
                    LEFT JOIN (" . $this->sqlFacturadoPorLinea($idsLineas) . ") fac ON fac.id = l.id
                    LEFT JOIN (" . $this->sqlCambiadoPorLinea($idsLineas) . ") cam ON cam.id = l.id
                    GROUP BY l.id_empresa, l.id_producto, l.id_bodega{$columnas}
                )";
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
        $where .= $this->excluirBodegasDenegadas($filtros, 'cvd.id_bodega');
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
        // Categoría y marca llegan solo desde el desglose "Lote + consignación" de Existencias
        // (la pestaña Consignaciones no tiene esos filtros). Van como subconsulta sobre
        // productos en vez de un JOIN dentro del CTE de líneas: así no cambian el plan de
        // la consulta base, que es la más cara del módulo.
        if (!empty($filtros['id_categoria'])) {
            $where .= " AND cvd.id_producto IN (SELECT id FROM productos WHERE id_empresa = :id_empresa_cat AND id_categoria = :id_categoria)";
            $params[':id_empresa_cat'] = $idEmpresa;
            $params[':id_categoria']   = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $where .= " AND cvd.id_producto IN (SELECT id FROM productos WHERE id_empresa = :id_empresa_mar AND id_marca = :id_marca)";
            $params[':id_empresa_mar'] = $idEmpresa;
            $params[':id_marca']       = (int) $filtros['id_marca'];
        }
        // Texto de producto escrito sin elegirlo de la lista: solo llega desde el desglose
        // "Lote + consignación" de Existencias. Subconsulta, por lo mismo que categoría/marca.
        if (!empty($filtros['buscar'])) {
            $where .= " AND cvd.id_producto IN (SELECT id FROM productos WHERE id_empresa = :id_empresa_bus
                            AND (nombre ILIKE :buscar_nombre OR codigo ILIKE :buscar_codigo))";
            $params[':id_empresa_bus'] = $idEmpresa;
            $params[':buscar_nombre']  = '%' . $filtros['buscar'] . '%';
            $params[':buscar_codigo']  = '%' . $filtros['buscar'] . '%';
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

    /**
     * Base: una fila por línea de consignación, con saldo y valor a costo calculados.
     *
     * Las líneas que cumplen el filtro se resuelven primero (CTE "lineas") y retornado,
     * facturado, entregado a cambio y costo se agregan una sola vez para todas ellas.
     * Antes eran cuatro LATERAL por línea; el del costo, además, quedaba a merced del
     * planificador: con idx_kardex_stock_por_bodega creado dejaba de usar
     * idx_kardex_referencia y recorría todos los movimientos del producto por cada línea
     * (45 s el listado; 0,5 s así).
     */
    private function baseConsignaciones(string $where): string
    {
        $idsLineas = "SELECT id_detalle FROM lineas";

        return "
            SELECT * FROM (
                WITH lineas AS MATERIALIZED (
                    SELECT cv.id AS id_consignacion, cv.secuencial, cv.fecha_emision, cv.estado,
                           cv.id_cliente, cv.id_vendedor, cv.id_responsable_traslado,
                           cvd.id AS id_detalle, cvd.id_producto, cvd.id_bodega, cvd.lote, cvd.nup,
                           cvd.cantidad
                    FROM consignaciones_ventas_detalles cvd
                    INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                    WHERE {$where}
                )
                SELECT l.id_consignacion, l.secuencial, l.fecha_emision, l.estado,
                       l.id_cliente, COALESCE(c.nombre, '-') AS cliente_nombre,
                       COALESCE(c.identificacion, '') AS cliente_identificacion,
                       l.id_vendedor, COALESCE(v.nombre, '-') AS vendedor_nombre,
                       l.id_responsable_traslado, COALESCE(rt.nombre, '-') AS responsable_traslado_nombre,
                       l.id_detalle, l.id_producto,
                       COALESCE(p.codigo, '') AS producto_codigo, COALESCE(p.nombre, '-') AS producto_nombre,
                       l.id_bodega, COALESCE(bo.nombre, '-') AS bodega_nombre,
                       COALESCE(l.lote, '-') AS numero_lote, COALESCE(l.nup, '-') AS nup,
                       l.cantidad AS cantidad_consignada,
                       COALESCE(ret.total, 0) AS cantidad_retornada,
                       COALESCE(fac.total, 0) AS cantidad_facturada,
                       COALESCE(cam.total, 0) AS cantidad_cambiada,
                       COALESCE(kar.costo_unitario, 0) AS costo_unitario
                FROM lineas l
                -- productos y clientes van con LEFT JOIN a propósito: con INNER, una
                -- consignación cuyo producto o cliente ya no exista en su tabla desaparecía
                -- del reporte sin ningún aviso (y su saldo dejaba de sumar en los totales).
                LEFT JOIN productos p ON p.id = l.id_producto
                LEFT JOIN bodegas bo ON bo.id = l.id_bodega
                LEFT JOIN clientes c ON c.id = l.id_cliente
                LEFT JOIN vendedores v ON v.id = l.id_vendedor
                LEFT JOIN responsables_traslado rt ON rt.id = l.id_responsable_traslado
                LEFT JOIN (" . $this->sqlRetornadoPorLinea($idsLineas) . ") ret ON ret.id = l.id_detalle
                LEFT JOIN (" . $this->sqlFacturadoPorLinea($idsLineas) . ") fac ON fac.id = l.id_detalle
                LEFT JOIN (" . $this->sqlCambiadoPorLinea($idsLineas) . ") cam ON cam.id = l.id_detalle
                -- Último costo del kardex de la consignación para ese producto: la misma fila que
                -- ORDER BY fecha_movimiento DESC, id DESC (el id desempata), sin ordenar.
                LEFT JOIN (
                    SELECT k.referencia_id, k.id_producto,
                           (MAX(ARRAY[EXTRACT(EPOCH FROM k.fecha_movimiento), k.id, k.costo_unitario]))[3] AS costo_unitario
                    FROM inventario_kardex k
                    WHERE k.id_empresa = :id_empresa AND k.referencia_tipo = 'CONSIGNACION_VENTA'
                      AND k.eliminado = false
                      AND k.referencia_id IN (SELECT id_consignacion FROM lineas)
                    GROUP BY k.referencia_id, k.id_producto
                ) kar ON kar.referencia_id = l.id_consignacion AND kar.id_producto = l.id_producto
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

    /**
     * Una fila por LÍNEA de consignación (producto + lote + NUP), con los datos del documento.
     *
     * @param string   $saldo  '' todas las líneas, 'CON' solo las que siguen en poder del
     *                         cliente (saldo > 0), 'SIN' solo las ya liquidadas. Lo usa el
     *                         desglose "Lote + consignación" de Existencias, que reaprovecha
     *                         el selector Consignado de esa pestaña.
     * @param int|null $limite tope de filas para pantalla; null = sin tope (exportaciones).
     */
    public function getConsignacionesDetalle(int $idEmpresa, array $filtros, string $saldo = '', ?int $limite = null): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $sql = "SELECT * FROM (" . $this->wrapSaldoConsignacion($this->baseConsignaciones($where)) . ") s";
        if ($saldo === 'CON') {
            $sql .= " WHERE s.saldo > 0";
        } elseif ($saldo === 'SIN') {
            $sql .= " WHERE s.saldo <= 0";
        }
        $sql .= " ORDER BY s.fecha_emision DESC, s.id_consignacion DESC, s.producto_nombre ASC, s.id_detalle ASC";
        if ($limite !== null) {
            // +1 fila: así el llamador sabe que hay más y puede avisar, sin un COUNT(*) aparte.
            $sql .= ' LIMIT ' . ((int) $limite + 1);
        }

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
                       string_agg(DISTINCT NULLIF(s.producto_codigo, ''), ', ' ORDER BY NULLIF(s.producto_codigo, '')) AS codigos,
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
        // Las bodegas denegadas no son un filtro del formulario, así que no están en
        // FILTROS_LINEA_CONSIGNACION y hay que pasarlas aparte: el modal no puede mostrar
        // líneas de una bodega que el listado ya le oculta, ni con "Ver todas las líneas".
        $filtros['bodegas_denegadas'] = $filtrosLinea['bodegas_denegadas'] ?? [];
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

    /** @param string|null $campoCodigo expresión del código del grupo: solo la tiene "por Producto",
     *  y viaja aparte del label para que la tabla lo pinte en su propia primera columna. */
    private function getConsignacionesAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel, ?string $campoCodigo = null): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $base = $this->wrapSaldoConsignacion($this->baseConsignaciones($where));
        $selCodigo = $campoCodigo !== null ? "MAX({$campoCodigo}) AS codigo_grupo," : '';

        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo, {$selCodigo}
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

    /**
     * Números de las facturas de venta y de los retornos de un conjunto de líneas de consignación,
     * para las columnas del Excel. Mismos criterios que getFacturasDeLineaConsignacion() y
     * getRetornosDeLineaConsignacion() (solo facturadas / Emitidos, no eliminados), en dos
     * consultas para todas las líneas en vez de dos por línea.
     *
     * @param int[] $idsDetalle ids de consignaciones_ventas_detalles
     * @return array<int, array{facturas: string, retornos: string}> por id de línea
     */
    public function getNumerosDocumentosPorLineas(int $idEmpresa, array $idsDetalle): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsDetalle), fn($i) => $i > 0)));
        if (!$ids) {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa, ':ids' => '{' . implode(',', $ids) . '}'];
        $out = [];

        $sqlFacturas = "SELECT cfd.id_consignacion_detalle AS id,
                               STRING_AGG(DISTINCT CASE
                                   WHEN COALESCE(vc.establecimiento::text, '') <> '' AND COALESCE(vc.punto_emision::text, '') <> ''
                                        AND COALESCE(vc.secuencial::text, '') <> ''
                                   THEN vc.establecimiento::text || '-' || vc.punto_emision::text || '-' || vc.secuencial::text
                                   ELSE NULLIF(TRIM(cf.numero_factura), '')
                               END, ', ') AS numeros
                        FROM consignaciones_facturas_detalles cfd
                        INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                        LEFT JOIN ventas_cabecera vc ON vc.id = cf.id_factura AND vc.id_empresa = cf.id_empresa
                        WHERE cfd.id_consignacion_detalle = ANY(CAST(:ids AS int[]))
                          AND cfd.id_empresa = :id_empresa AND cfd.eliminado = false
                          AND cf.eliminado = false AND cf.estado = 'facturada'
                        GROUP BY cfd.id_consignacion_detalle";
        $st = $this->db->prepare($sqlFacturas);
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']]['facturas'] = (string) ($r['numeros'] ?? '');
        }

        $sqlRetornos = "SELECT rcd.id_consignacion_detalle AS id,
                               STRING_AGG(DISTINCT CONCAT_WS('-', NULLIF(TRIM(rc.serie::text), ''), rc.secuencial::text), ', ') AS numeros
                        FROM retornos_cv_detalles rcd
                        INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                        WHERE rcd.id_consignacion_detalle = ANY(CAST(:ids AS int[]))
                          AND rcd.id_empresa = :id_empresa AND rcd.eliminado = false
                          AND rc.eliminado = false AND rc.estado = 'Emitida'
                        GROUP BY rcd.id_consignacion_detalle";
        $st = $this->db->prepare($sqlRetornos);
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']]['retornos'] = (string) ($r['numeros'] ?? '');
        }

        foreach ($out as &$o) {
            $o += ['facturas' => '', 'retornos' => ''];
        }
        unset($o);
        return $out;
    }

    public function getConsignacionesAgrupadoCliente(int $idEmpresa, array $filtros): array
    {
        return $this->getConsignacionesAgrupado($idEmpresa, $filtros, 'id_cliente', 'cliente_nombre');
    }

    public function getConsignacionesAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getConsignacionesAgrupado($idEmpresa, $filtros, 'id_producto', 'producto_nombre', 'producto_codigo');
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

    /**
     * ¿Queda alguna línea de esta consignación en una bodega que el usuario sí puede ver?
     * Es el guard de lo que se sirve por id (el PDF de estado y los documentos de una
     * línea): sin esto, escribiendo el id en la URL se llegaba al documento completo que
     * el listado y el modal ya no muestran.
     */
    public function consignacionVisible(int $idEmpresa, int $idConsignacion, array $bodegasDenegadas): bool
    {
        return $this->existeLineaVisible($idEmpresa, 'cvd.id_consignacion = :id', $idConsignacion, $bodegasDenegadas);
    }

    /** Igual que consignacionVisible(), pero para UNA línea concreta del documento. */
    public function lineaConsignacionVisible(int $idEmpresa, int $idDetalle, array $bodegasDenegadas): bool
    {
        return $this->existeLineaVisible($idEmpresa, 'cvd.id = :id', $idDetalle, $bodegasDenegadas);
    }

    private function existeLineaVisible(int $idEmpresa, string $condicion, int $id, array $bodegasDenegadas): bool
    {
        $where = "cvd.id_empresa = :id_empresa AND cvd.eliminado = false AND {$condicion}"
               . $this->excluirBodegasDenegadas(['bodegas_denegadas' => $bodegasDenegadas], 'cvd.id_bodega');

        $st = $this->db->prepare("SELECT EXISTS (SELECT 1 FROM consignaciones_ventas_detalles cvd WHERE {$where})");
        $st->execute([':id_empresa' => $idEmpresa, ':id' => $id]);

        return (bool) $st->fetchColumn();
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
     * El "real" se calcula con EL MISMO criterio con el que los flujos que escriben stock
     * recalculan la copia guardada (InventarioRepository::getStockActual(): solo los
     * movimientos del tipo_ambiente de la empresa, redondeados a 2 decimales). Antes se
     * sumaba todo el kardex sin filtrar ni redondear, y la pestaña marcaba como
     * discrepancia movimientos de otro ambiente que el recálculo nunca iba a incluir —
     * y "Corregir" los igualaba a esa suma, con lo que el siguiente movimiento volvía a
     * desigualarlos. Movimientos de otro ambiente se diagnostican en el seguimiento de
     * un negativo (getSeguimientoClave), no aquí.
     *
     * @param int|null $limite tope de filas para pantalla; null = sin tope (exportaciones).
     */
    public function getAuditoriaStock(int $idEmpresa, array $filtros, ?int $limite = null): array
    {
        $where = "pb.id_empresa = :id_empresa AND pb.eliminado = false AND p.eliminado = false AND p.inventariable = true AND b.eliminado = false";
        $params = [':id_empresa' => $idEmpresa, ':tipo_ambiente' => $this->tipoAmbienteEmpresa($idEmpresa)];

        if (!empty($filtros['id_bodega'])) {
            $where .= " AND pb.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        $where .= $this->excluirBodegasDenegadas($filtros, 'pb.id_bodega');
        if (!empty($filtros['id_producto'])) {
            $where .= " AND pb.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        $sql = "WITH kardex_agg AS (
                    SELECT id_producto, id_bodega, ROUND(SUM(cantidad), 2) AS real_kardex
                    FROM inventario_kardex
                    WHERE id_empresa = :id_empresa AND eliminado = false
                      AND tipo_ambiente = :tipo_ambiente
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
            $inventario = new InventarioRepository();
            $inventario->lockStock($idProducto, $idBodega, $idEmpresa);

            $st = $this->db->prepare("SELECT stock_actual FROM productos_bodegas
                                       WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e AND eliminado = false");
            $st->execute([':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);
            $antes = (float) ($st->fetchColumn() ?: 0);

            // El valor corregido sale del MISMO cálculo que usan los movimientos para
            // recalcular la copia guardada (tipo_ambiente de la empresa, 2 decimales). Si aquí
            // se sumara con otro criterio, el siguiente movimiento la volvería a desigualar.
            $real = $inventario->getStockActual($idProducto, $idBodega, $idEmpresa);

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

    // ── Comprobación con Contabilidad ────────────────────────────────────────────
    //
    // Definición para App\Services\ComprobacionContableService (ver el formato en
    // App\repositories\ComprobacionContableRepository). Lado documento: el VALOR del kardex
    // por movimientos (entradas + costo, salidas − costo), agrupado por el documento que lo
    // originó. Lado contable: las cuentas de los conceptos de inventario de Configuración
    // Contable (código con "INVENTARIO", en cualquier nivel de la cascada).
    //
    // El valor por movimientos es el que registra la contabilidad (el costo de cada venta sale
    // del kardex); NO es el de la pestaña Valorización, que multiplica el stock por el último
    // costo. Las compras e importaciones guardan en el kardex el id de la LÍNEA, así que se
    // llevan a su cabecera, que es lo que enlaza el asiento.

    /** referencia_tipo del kardex => tipo del cruce (= modulo_origen del asiento). */
    private const COMPROBACION_TIPOS_KARDEX = [
        'CONSIGNACION_VENTA'             => 'consignacion_venta',
        'EDICION_CONSIGNACION_VENTA'     => 'consignacion_venta',
        'ELIMINACION_CONSIGNACION_VENTA' => 'consignacion_venta',
        'RETORNO_CV'                     => 'retorno_cv',
        'CAMBIO_ESTADO_RETORNO_CV'       => 'retorno_cv',
        'ELIMINACION_RETORNO_CV'         => 'retorno_cv',
        'CAMBIO_PRODUCTO_CV'             => 'cambio_producto_cv',
    ];

    public function definicionComprobacionContable(): array
    {
        $casos = '';
        foreach (self::COMPROBACION_TIPOS_KARDEX as $ref => $tipo) {
            $casos .= " WHEN '{$ref}' THEN '{$tipo}'";
        }
        $serie = \App\repositories\ComprobacionContableRepository::NUM_SERIE;

        $docs = "SELECT CASE WHEN cd.id_compra IS NOT NULL THEN 'compra'
                             WHEN idt.id_importacion IS NOT NULL THEN 'importacion'
                             WHEN k.referencia_tipo = 'SALDO_INICIAL' THEN 'saldo_inicial'
                             ELSE CASE k.referencia_tipo {$casos} ELSE k.referencia_tipo END
                        END AS tipo,
                        CASE WHEN cd.id_compra IS NOT NULL THEN cd.id_compra
                             WHEN idt.id_importacion IS NOT NULL THEN idt.id_importacion
                             WHEN k.referencia_tipo = 'SALDO_INICIAL' THEN 0
                             ELSE COALESCE(k.referencia_id, 0)
                        END AS id_doc,
                        k.fecha_movimiento::DATE AS fecha,
                        CASE WHEN k.tipo_movimiento = 'salida' OR k.cantidad < 0 THEN -1 ELSE 1 END
                          * ABS(COALESCE(k.costo_total, ABS(k.cantidad) * k.costo_unitario, 0)) AS monto
                 FROM inventario_kardex k
                 LEFT JOIN compras_detalle cd
                        ON k.referencia_tipo IN ('compra', 'compra_item') AND cd.id = k.referencia_id
                 LEFT JOIN importaciones_detalle idt
                        ON k.referencia_tipo = 'importacion' AND idt.id = k.referencia_id
                 WHERE k.id_empresa = :e AND k.eliminado = FALSE
                   AND (k.tipo_ambiente IS NULL OR k.tipo_ambiente = (SELECT t FROM amb))";

        $migrados = "SELECT id_asiento_contable AS id_asiento, 'factura_venta' AS tipo, id AS id_doc FROM ventas_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'recibo_venta', id FROM recibos_venta_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'nota_credito', id FROM notas_credito_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'compra', id FROM compras_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'importacion', id FROM importaciones_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'consignacion_venta', id FROM consignaciones_ventas WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'retorno_cv', id FROM retornos_cv WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'cambio_producto_cv', id FROM cambios_producto_cv WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL";

        return [
            'patron_concepto' => 'INVENTARIO',
            'conceptos_texto' => 'Inventario (compras, ventas, recibos, importaciones y consignaciones)',
            'signo' => 1,
            'docs' => $docs,
            'nativos' => [
                'factura_venta' => 'factura_venta', 'recibo_venta' => 'recibo_venta', 'nota_credito' => 'nota_credito',
                'compra' => 'compra', 'importacion' => 'importacion', 'consignacion_venta' => 'consignacion_venta',
                'retorno_cv' => 'retorno_cv', 'FACTURACION_CV' => 'FACTURACION_CV', 'cambio_producto_cv' => 'cambio_producto_cv',
            ],
            'migrados' => $migrados,
            'apertura' => true,
            'numeros' => [
                'factura_venta' => ['ventas_cabecera', $serie],
                'recibo_venta' => ['recibos_venta_cabecera', $serie],
                'nota_credito' => ['notas_credito_cabecera', $serie],
                'compra' => ['compras_cabecera', "CONCAT(x.establecimiento_prov, '-', x.punto_emision_prov, '-', x.secuencial_prov)"],
                'importacion' => ['importaciones_cabecera', 'x.numero_importacion'],
                'consignacion_venta' => ['consignaciones_ventas', $serie],
                'retorno_cv' => ['retornos_cv', $serie],
                'cambio_producto_cv' => ['cambios_producto_cv', $serie],
            ],
        ];
    }
}
