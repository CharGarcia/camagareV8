<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de Cambios de productos (`modulos/cambio-producto-cv`).
 *
 * Un cambio agrupa, por cliente y en un solo documento:
 *   - líneas de DEVOLUCIÓN (tipo_linea='devolucion') → entrada de inventario. Su
 *     origen es una línea de FACTURA DE CONSIGNACIÓN (consignaciones_facturas_detalles,
 *     módulo Facturación de consignaciones, estado 'facturada') o una línea de
 *     ENTREGA de un cambio anterior (encadenado). Se controla el saldo.
 *   - líneas de ENTREGA (tipo_linea='entrega') → salida de inventario (catálogo).
 */
class CambioProductoCvRepository extends BaseRepository
{
    /** Cache por petición de registroFacturacionDisponible(). */
    private static ?bool $registroFacturacion = null;

    public function __construct()
    {
        parent::__construct('cambios_producto_cv');
    }

    // ─── REGISTRO EN FACTURACIÓN DE CONSIGNACIONES ────────────────────────────
    //
    // Al emitir un cambio, lo que se entrega desde una consignación queda como 'facturada' en
    // Facturación de consignaciones (consignaciones_facturas.id_cambio_producto), dentro de la
    // factura de venta de la unidad devuelta y sin crear factura nueva. A partir de ahí esa
    // unidad cuenta como FACTURADA en el saldo de la consignación y deja de contar como
    // "entregada a cambio". Estos fragmentos son la única definición de esa regla: los usan
    // Retornos, Facturación CV, el kardex de la consignación, el Reporte de inventarios, la
    // sincronización de asientos, Auditoría contable y la migración.

    /**
     * ¿La base ya tiene las columnas del registro (database/migrations/20260916_facturacion_cv_registro_cambio.sql)?
     * Mientras no se apliquen, todo se comporta como antes: los fragmentos de abajo no agregan
     * nada y el cambio no crea registros. Se consulta una vez por petición.
     */
    public static function registroFacturacionDisponible(): bool
    {
        if (self::$registroFacturacion === null) {
            try {
                $st = \App\core\Database::getConnection()->query(
                    "SELECT COUNT(*) FROM information_schema.columns
                      WHERE table_schema = current_schema()
                        AND ((table_name = 'consignaciones_facturas'          AND column_name = 'id_cambio_producto')
                          OR (table_name = 'consignaciones_facturas_detalles' AND column_name = 'id_cambio_detalle'))"
                );
                self::$registroFacturacion = ((int) $st->fetchColumn()) === 2;
            } catch (\Throwable $e) {
                self::$registroFacturacion = false;
            }
        }
        return self::$registroFacturacion;
    }

    /**
     * Condición " AND …": la línea de ENTREGA del cambio ($aliasLinea, cambios_producto_cv_detalles)
     * NO quedó registrada como facturada. Las que sí, ya descuentan el saldo como facturación.
     */
    public static function sqlSinRegistroFacturacion(string $aliasLinea): string
    {
        if (!self::registroFacturacionDisponible()) {
            return '';
        }
        return " AND NOT EXISTS (SELECT 1
                                   FROM consignaciones_facturas_detalles rfd
                                   INNER JOIN consignaciones_facturas rf ON rf.id = rfd.id_consignacion_factura
                                  WHERE rfd.id_cambio_detalle = {$aliasLinea}.id
                                    AND rfd.eliminado = false AND rf.eliminado = false AND rf.estado = 'facturada')";
    }

    /**
     * Condición " AND …": el documento de Facturación de consignaciones ($aliasCf, alias o nombre
     * de tabla) NO es un registro generado por un cambio (tiene factura de venta propia).
     */
    public static function sqlNoEsRegistroDeCambio(string $aliasCf): string
    {
        return self::registroFacturacionDisponible() ? " AND {$aliasCf}.id_cambio_producto IS NULL" : '';
    }

    /**
     * LEFT JOIN a la factura de una línea 'FACTURA' ($alias = cambios_producto_cv_detalles).
     *
     * 'FACTURA' tuvo dos significados: hasta el 15-09-2026 la devolución apuntaba a la FACTURA DE
     * VENTA (id_origen = ventas_cabecera, id_origen_detalle = ventas_detalle); desde el 16-09-2026, a
     * la FACTURA DE CONSIGNACIÓN (consignaciones_facturas / _detalles). Los id de esas tablas se pisan,
     * así que la línea se reconoce por el PAR cabecera + detalle + producto: primero como factura de
     * consignación y, si no calza, como factura de venta directa. Alias con prefijo $p: {$p}cfd,
     * {$p}cf, {$p}fv (factura de consignación y su venta) y {$p}vd, {$p}vc (venta directa, formato antiguo).
     */
    private static function sqlJoinsFacturaDeLinea(string $alias, string $p): string
    {
        return "
            LEFT JOIN consignaciones_facturas_detalles {$p}cfd ON {$alias}.origen_tipo = 'FACTURA'
                  AND {$p}cfd.id = {$alias}.id_origen_detalle AND {$p}cfd.id_consignacion_factura = {$alias}.id_origen
                  AND {$p}cfd.id_producto = {$alias}.id_producto
            LEFT JOIN consignaciones_facturas {$p}cf ON {$p}cf.id = {$p}cfd.id_consignacion_factura
            LEFT JOIN ventas_cabecera {$p}fv ON {$p}fv.id = {$p}cf.id_factura
            LEFT JOIN ventas_detalle {$p}vd ON {$alias}.origen_tipo = 'FACTURA' AND {$p}cfd.id IS NULL
                  AND {$p}vd.id = {$alias}.id_origen_detalle AND {$p}vd.id_venta = {$alias}.id_origen
                  AND {$p}vd.id_producto = {$alias}.id_producto
            LEFT JOIN ventas_cabecera {$p}vc ON {$p}vc.id = {$p}vd.id_venta";
    }

    /**
     * Número de la factura de venta de una línea 'FACTURA', con los alias de sqlJoinsFacturaDeLinea():
     * la venta de la factura de consignación (o el número guardado al enlazarla) o la venta directa.
     */
    private static function sqlNumeroFacturaDeJoins(string $p): string
    {
        return "COALESCE({$p}fv.establecimiento || '-' || {$p}fv.punto_emision || '-' || {$p}fv.secuencial,
                         NULLIF(TRIM({$p}cf.numero_factura), ''),
                         {$p}vc.establecimiento || '-' || {$p}vc.punto_emision || '-' || {$p}vc.secuencial)";
    }

    /**
     * Factura de venta de la que viene una devolución 'FACTURA' (para el registro de un cambio y la
     * columna Origen): id, número y vendedor. Reconoce los dos formatos (ver sqlJoinsFacturaDeLinea).
     */
    public function getFacturaVentaDeLinea(int $idOrigen, int $idOrigenDetalle, int $idProducto, int $idEmpresa): ?array
    {
        $sql = "SELECT COALESCE(ocf.id_factura, ovc.id)          AS id_factura,
                       " . self::sqlNumeroFacturaDeJoins('o') . " AS numero_factura,
                       COALESCE(ocf.id_vendedor, ovc.id_vendedor) AS id_vendedor
                FROM (SELECT CAST('FACTURA' AS VARCHAR(15)) AS origen_tipo, CAST(:io AS INTEGER) AS id_origen,
                             CAST(:iod AS INTEGER) AS id_origen_detalle, CAST(:prod AS INTEGER) AS id_producto) l
                " . self::sqlJoinsFacturaDeLinea('l', 'o') . "
                WHERE (ocfd.id IS NOT NULL OR ovd.id IS NOT NULL)
                  AND COALESCE(ocf.id_empresa, ovc.id_empresa) = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':io' => $idOrigen, ':iod' => $idOrigenDetalle, ':prod' => $idProducto, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ¿El cambio lo insertó la migración desde el sistema anterior? Esos cambios no llevan asiento
     * contable: aquel sistema no contabilizaba los cambios de productos (misma regla que
     * SincronizadorAsientosService y Auditoría contable, que miran el mismo mapa).
     */
    public function esMigrado(int $idCambio, int $idEmpresa): bool
    {
        static $hayMapa = null;
        if ($hayMapa === null) {
            $hayMapa = (bool) $this->db->query("SELECT to_regclass('public.migracion_mysql_map')")->fetchColumn();
        }
        if (!$hayMapa) {
            return false;
        }
        $st = $this->db->prepare(
            "SELECT 1 FROM migracion_mysql_map
              WHERE entidad = 'cambios_producto' AND id_destino = :id AND id_empresa = :e AND vinculado IS NOT TRUE
              LIMIT 1"
        );
        $st->execute([':id' => $idCambio, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Factura de venta en la que quedó registrada una línea de ENTREGA de un cambio (registro
     * vigente en Facturación de consignaciones). Null si no hay registro.
     */
    public function getFacturaVentaDeEntregaRegistrada(int $idCambioDetalle, int $idEmpresa): ?array
    {
        if (!self::registroFacturacionDisponible()) {
            return null;
        }
        $sql = "SELECT rf.id_factura, rf.numero_factura, rf.id_vendedor
                FROM consignaciones_facturas_detalles rfd
                INNER JOIN consignaciones_facturas rf ON rf.id = rfd.id_consignacion_factura
                WHERE rfd.id_cambio_detalle = :id AND rf.id_empresa = :e
                  AND rfd.eliminado = false AND rf.eliminado = false AND rf.estado = 'facturada'
                ORDER BY rf.id DESC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCambioDetalle, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Series REALMENTE usadas en cambios de productos guardados, para el filtro
     * "Serie" del buscador — a diferencia de $puntos (solo sirve para elegir la
     * serie de un cambio NUEVO), esto incluye series de cualquier establecimiento
     * y aunque el punto ya no tenga secuencial configurado.
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM cambios_producto_cv
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /**
     * Expresión SQL con el número del documento de origen de una línea de cambio, tal como se
     * MUESTRA: FACTURA → n.º de la factura de venta (en los dos formatos, ver
     * sqlJoinsFacturaDeLinea), CAMBIO → cambio anterior, CONSIGNACION → consignación. NULL en
     * las entregas de bodega / catálogo y en las devoluciones migradas (sin origen).
     */
    private static function sqlNumeroOrigen(string $alias): string
    {
        return "(CASE {$alias}.origen_tipo
                    WHEN 'FACTURA'      THEN (SELECT " . self::sqlNumeroFacturaDeJoins('x') . "
                                                FROM (SELECT 1) uno " . self::sqlJoinsFacturaDeLinea($alias, 'x') . ")
                    WHEN 'CAMBIO'       THEN (SELECT CONCAT(ox.serie, '-', ox.secuencial) FROM cambios_producto_cv ox WHERE ox.id = {$alias}.id_origen)
                    WHEN 'CONSIGNACION' THEN (SELECT CONCAT(ox.serie, '-', ox.secuencial) FROM consignaciones_ventas ox WHERE ox.id = {$alias}.id_origen)
                 END)";
    }

    /**
     * Lo mismo que sqlNumeroOrigen(), pero para BUSCAR: en las líneas de factura de consignación
     * incluye además el número propio de esa factura de consignación, así se encuentran por
     * cualquiera de los dos.
     */
    private static function sqlNumerosOrigenBusqueda(string $alias): string
    {
        return "(CASE {$alias}.origen_tipo
                    WHEN 'FACTURA' THEN (SELECT CONCAT_WS(' ', " . self::sqlNumeroFacturaDeJoins('x') . ", NULLIF(CONCAT(xcf.serie, '-', xcf.secuencial), '-'))
                                           FROM (SELECT 1) uno " . self::sqlJoinsFacturaDeLinea($alias, 'x') . ")
                    ELSE " . self::sqlNumeroOrigen($alias) . "
                 END)";
    }

    /**
     * Número de la FACTURA DE VENTA (ventas_cabecera) que generó una factura de consignación:
     * es el comprobante que tiene el cliente y el que se muestra como origen de lo devuelto
     * (modal y PDF), en lugar del número interno de la factura de consignación.
     * $aliasCf = consignaciones_facturas; $aliasVenta = LEFT JOIN ventas_cabecera por
     * {$aliasCf}.id_factura. Sin esa fila, cae al número guardado al enlazar la factura.
     */
    private static function sqlNumeroFacturaVenta(string $aliasCf, string $aliasVenta): string
    {
        return "COALESCE({$aliasVenta}.establecimiento || '-' || {$aliasVenta}.punto_emision || '-' || {$aliasVenta}.secuencial,
                         NULLIF(TRIM({$aliasCf}.numero_factura), ''))";
    }

    /** Responsables de traslado usados en cambios de la empresa: filtro del modal de filtros. */
    public function getResponsablesUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT rt.id, rt.nombre
                FROM cambios_producto_cv r
                JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
                WHERE r.id_empresa = :id_empresa AND r.eliminado = false
                ORDER BY rt.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que registraron cambios en la empresa: filtro "Usuario que registró" del modal. */
    public function getUsuariosUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM cambios_producto_cv r
                JOIN usuarios u ON u.id = r.created_by
                WHERE r.id_empresa = :id_empresa AND r.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de los cambios (pestaña "Detalles" del modal de filtros): cada
     * línea devuelta o entregada (producto, lote, NUP, caducidad, bodega y documento de
     * origen) que coincide con el texto, con el cambio al que pertenece. Mismo alcance que
     * el listado: empresa, no eliminados y registros propios (created_by) si $idUsuario viene.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "r.id_empresa = :id_empresa AND r.eliminado = false";
        if ($idUsuario !== null) {
            $whereBase .= " AND r.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Rendimiento (17-09-2026): las líneas se filtran por empresa, y producto y bodega se
        // buscan en su catálogo como conjunto (FiltrosBusqueda::condicionTexto, `col` + `sql`);
        // montos, cantidades y fechas solo si la palabra tiene dígitos.
        $fecha   = \App\Helpers\FiltrosBusqueda::SI_FECHA;
        $numero  = \App\Helpers\FiltrosBusqueda::SI_NUMERO;
        $condLinea = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.lote', 'd.nup', self::sqlNumerosOrigenBusqueda('d'),
             ['col' => "CONCAT_WS(' ', px.codigo, px.nombre, px.codigo_barras)",
              'sql' => "d.id_producto IN (SELECT px.id FROM productos px WHERE px.id_empresa = :id_empresa AND {cond})"],
             ['col' => 'bx.nombre',
              'sql' => "d.id_bodega IN (SELECT bx.id FROM bodegas bx WHERE bx.id_empresa = :id_empresa AND {cond})"],
             ['sql' => "TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')", 'si' => $fecha],
             ['sql' => 'd.cantidad', 'si' => $numero],
             ['sql' => 'd.precio_unitario', 'si' => $numero],
             ['sql' => 'd.total', 'si' => $numero]],
            $q, $params, 'ln'
        );
        if ($condLinea === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT r.id, r.serie, r.secuencial, r.fecha_cambio, r.estado, c.nombre AS cliente_nombre
                    FROM cambios_producto_cv r
                    INNER JOIN clientes c ON c.id = r.id_cliente
                    WHERE $whereBase
                )
                SELECT d.tipo_linea, d.origen_tipo, p.codigo AS tipo, p.nombre AS descripcion,
                       NULLIF(CONCAT_WS(' / ', NULLIF(d.lote, ''), NULLIF(d.nup, ''), TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')), '') AS extra,
                       bo.nombre AS bodega,
                       " . self::sqlNumeroOrigen('d') . " AS documento_origen,
                       d.cantidad, d.total AS monto,
                       b.id, b.serie, b.secuencial, b.fecha_cambio, b.estado, b.cliente_nombre
                FROM cambios_producto_cv_detalles d
                JOIN base b ON b.id = d.id_cambio
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas bo ON bo.id = d.id_bodega
                WHERE d.id_empresa = :id_empresa AND d.eliminado = false AND $condLinea
                ORDER BY b.fecha_cambio DESC, b.id DESC, d.tipo_linea, d.id
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── LISTADO PAGINADO ─────────────────────────────────────────────────────

    /**
     * Número del documento de origen de la línea que ENTRA en el listado (alias dv): factura de
     * venta en sus dos formatos (misma expresión que sqlNumeroFacturaDeJoins('o'), con los joins
     * de sqlJoinsFacturaDeLinea('dv', 'o') de getListado) o cambio anterior. Constante para poder
     * usarla también en MAPA_ORDEN.
     */
    private const SQL_ORIGEN_DEV = "(CASE dv.origen_tipo
                WHEN 'FACTURA' THEN COALESCE(ofv.establecimiento || '-' || ofv.punto_emision || '-' || ofv.secuencial,
                                             NULLIF(TRIM(ocf.numero_factura), ''),
                                             ovc.establecimiento || '-' || ovc.punto_emision || '-' || ovc.secuencial)
                WHEN 'CAMBIO'  THEN COALESCE(cao.serie, '') || '-' || COALESCE(cao.secuencial, '')
             END)";

    /**
     * Orden del listado (whitelist de OrdenListado): data-col de la vista => expresión SQL.
     * En las columnas de un solo lado se ordena primero por "vacío" para que las filas sin
     * producto de ese lado queden al final en los dos sentidos. secuencial, estado y diferencia
     * ya no son columnas: siguen para las preferencias guardadas.
     */
    public const MAPA_ORDEN = [
        'fecha_cambio'  => 'r.fecha_cambio',
        // Lo que ENTRA (devolución)
        'dev_cantidad'  => 'dv.cantidad IS NULL, dv.cantidad',
        'dev_producto'  => 'pdv.nombre IS NULL, pdv.nombre',
        'dev_lote'      => "COALESCE(dv.lote, '') = '', dv.lote",
        'dev_nup'       => "COALESCE(dv.nup, '') = '', dv.nup",
        'dev_bodega'    => 'bdv.nombre IS NULL, bdv.nombre',
        'dev_factura'   => self::SQL_ORIGEN_DEV . ' IS NULL, ' . self::SQL_ORIGEN_DEV,
        // Lo que SALE (entrega)
        'ent_cantidad'  => 'en.cantidad IS NULL, en.cantidad',
        'ent_producto'  => 'pen.nombre IS NULL, pen.nombre',
        'ent_lote'      => "COALESCE(en.lote, '') = '', en.lote",
        'ent_nup'       => "COALESCE(en.nup, '') = '', en.nup",
        'ent_bodega'    => 'ben.nombre IS NULL, ben.nombre',
        'cliente'       => 'c.nombre',
        'observaciones' => "COALESCE(r.observaciones, '') = '', r.observaciones",
        // Sin columna en pantalla
        'secuencial'    => 'r.secuencial',
        'estado'        => 'r.estado',
        'diferencia'    => 'r.diferencia',
    ];

    /**
     * Listado principal: una fila por PAREJA "entra ↔ sale" de cada cambio. La n-ésima
     * devolución va con la n-ésima entrega del mismo cambio (en orden de registro); si un lado
     * tiene más líneas, las celdas del otro quedan vacías. El buscador y los filtros siguen
     * siendo por cambio: de un cambio que calza se listan todas sus parejas. `total` cuenta
     * filas (parejas), no cambios.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro, array $ordenMulti = []): array
    {
        [$where, $params] = $this->whereListado($idEmpresa, $buscar, $idUsuarioFiltro);

        $ctes = "
            WITH base AS (
                SELECT r.id
                FROM cambios_producto_cv r
                INNER JOIN clientes c ON c.id = r.id_cliente
                LEFT JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
                LEFT JOIN usuarios u ON u.id = r.created_by
                $where
            ),
            lineas AS (
                SELECT d.id, d.id_cambio, d.tipo_linea,
                       ROW_NUMBER() OVER (PARTITION BY d.id_cambio, d.tipo_linea ORDER BY d.id) AS rn
                FROM cambios_producto_cv_detalles d
                WHERE d.id_cambio IN (SELECT id FROM base) AND COALESCE(d.eliminado, false) = false
            ),
            pares AS (
                SELECT COALESCE(ld.id_cambio, le.id_cambio) AS id_cambio,
                       COALESCE(ld.rn, le.rn)               AS rn,
                       ld.id AS id_dev,
                       le.id AS id_ent
                FROM (SELECT * FROM lineas WHERE tipo_linea = 'devolucion') ld
                FULL JOIN (SELECT * FROM lineas WHERE tipo_linea = 'entrega') le
                       ON le.id_cambio = ld.id_cambio AND le.rn = ld.rn
            )";

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        if ($ordenMulti === []) {
            $ordenMulti = [['col' => $ordenCol, 'dir' => $ordenDir]];
        }
        // Desempate: el cambio y, dentro de él, el orden de las parejas.
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'r.fecha_cambio', 'r.id DESC, p.rn');

        // Lo que entra (dv) y lo que sale (en) de cada pareja, con el origen de la devolución.
        $joinsLineas = "
            LEFT JOIN cambios_producto_cv_detalles dv ON dv.id = p.id_dev
            LEFT JOIN productos pdv ON pdv.id = dv.id_producto
            LEFT JOIN bodegas bdv ON bdv.id = dv.id_bodega
            " . self::sqlJoinsFacturaDeLinea('dv', 'o') . "
            LEFT JOIN cambios_producto_cv cao ON dv.origen_tipo = 'CAMBIO' AND cao.id = dv.id_origen
            LEFT JOIN cambios_producto_cv_detalles en ON en.id = p.id_ent
            LEFT JOIN productos pen ON pen.id = en.id_producto
            LEFT JOIN bodegas ben ON ben.id = en.id_bodega";
        // Rendimiento (17-09-2026): conteo + página en UNA consulta (antes el WHERE con texto
        // libre se evaluaba dos veces), y las líneas con su origen solo se unen a las parejas de
        // la página; para ordenar entran a todas las parejas únicamente si el orden las usa.
        $ordenUsaLineas = preg_match('/(?<![\w.])(dv|pdv|bdv|ocfd|ocf|ofv|ovd|ovc|cao|en|pen|ben)\./', $orderBy) === 1;

        $sql = "$ctes,
            pagina AS MATERIALIZED (
                SELECT p.id_cambio, p.rn, p.id_dev, p.id_ent,
                       ROW_NUMBER() OVER ($orderBy) AS __rn,
                       COUNT(*) OVER () AS __total
                FROM pares p
                INNER JOIN cambios_producto_cv r ON r.id = p.id_cambio
                INNER JOIN clientes c ON c.id = r.id_cliente
                " . ($ordenUsaLineas ? $joinsLineas : '') . "
                $orderBy
                $limitClause
            )
            SELECT p.id_cambio AS id, p.rn,
                   r.serie, r.secuencial, r.estado, r.fecha_cambio, r.observaciones,
                   c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                   dv.cantidad    AS dev_cantidad,
                   dv.id_producto AS dev_id_producto,
                   pdv.nombre     AS dev_producto_nombre,
                   pdv.codigo     AS dev_producto_codigo,
                   dv.lote        AS dev_lote,
                   dv.nup         AS dev_nup,
                   bdv.nombre     AS dev_bodega,
                   dv.origen_tipo AS dev_origen_tipo,
                   dv.id_origen   AS dev_id_origen,
                   dv.id_origen_detalle AS dev_id_origen_detalle,
                   " . self::SQL_ORIGEN_DEV . " AS dev_origen_numero,
                   en.cantidad    AS ent_cantidad,
                   pen.nombre     AS ent_producto_nombre,
                   pen.codigo     AS ent_producto_codigo,
                   en.lote        AS ent_lote,
                   en.nup         AS ent_nup,
                   ben.nombre     AS ent_bodega,
                   p.__total
            FROM pagina p
            INNER JOIN cambios_producto_cv r ON r.id = p.id_cambio
            INNER JOIN clientes c ON c.id = r.id_cliente
            $joinsLineas
            ORDER BY p.__rn";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            $total = (int) $rows[0]['__total'];
        } elseif ($perPage > 0 && $page > 1) {
            // Página fuera de rango: sin filas no hay __total; se cuenta aparte (raro).
            $stCount = $this->db->prepare("$ctes SELECT COUNT(*) FROM pares");
            $stCount->execute($params);
            $total = (int) $stCount->fetchColumn();
        } else {
            $total = 0;
        }
        foreach ($rows as &$fila) {
            unset($fila['__total']);
        }
        unset($fila);

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Última devolución (en orden de registro) de cada cambio de $idsCambio: con ella se emparejan
     * las entregas que sobran. [id_cambio => origen_tipo, id_origen, id_origen_detalle y
     * origen_numero (la factura de venta o el cambio anterior)].
     */
    public function getUltimasDevoluciones(array $idsCambio, int $idEmpresa): array
    {
        $idsCambio = array_values(array_unique(array_filter(array_map('intval', $idsCambio))));
        if ($idsCambio === []) {
            return [];
        }
        $params = [':e' => $idEmpresa];
        $marcas = [];
        foreach ($idsCambio as $i => $id) {
            $marcas[] = ":c{$i}";
            $params[":c{$i}"] = $id;
        }
        $sql = "SELECT DISTINCT ON (d.id_cambio)
                       d.id_cambio, d.origen_tipo, d.id_origen, d.id_origen_detalle, d.id_producto,
                       CASE d.origen_tipo
                            WHEN 'FACTURA' THEN " . self::sqlNumeroFacturaDeJoins('o') . "
                            WHEN 'CAMBIO'  THEN (COALESCE(co.serie,'') || '-' || COALESCE(co.secuencial,''))
                       END AS origen_numero
                FROM cambios_producto_cv_detalles d
                " . self::sqlJoinsFacturaDeLinea('d', 'o') . "
                LEFT JOIN cambios_producto_cv co    ON d.origen_tipo = 'CAMBIO' AND co.id = d.id_origen
                WHERE d.id_cambio IN (" . implode(', ', $marcas) . ") AND d.id_empresa = :e
                  AND d.tipo_linea = 'devolucion' AND COALESCE(d.eliminado, false) = false
                ORDER BY d.id_cambio, d.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id_cambio']] = $row;
        }
        return $out;
    }

    /**
     * WHERE del listado, por CAMBIO: empresa, no eliminados, registros propios, texto libre del
     * buscador y claves del modal de filtros. Usa los alias r (cambio), c (cliente),
     * rt (responsable de traslado) y u (usuario que registró). Devuelve [where, params].
     */
    private function whereListado(int $idEmpresa, string $buscar, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE r.id_empresa = :e AND r.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND r.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre (buscador FiltrosModal de la vista): las columnas del listado. Este
            // listado muestra las dos patas del cambio línea a línea (producto, lote, NUP,
            // bodega y factura de origen, tanto de lo devuelto como de lo entregado), así que
            // casi todo lo que busca SÍ se ve; por eso aquí se conservan.
            //
            // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar:
            //   - Estado → modal de filtros (decisión anterior).
            //   - Identificación del cliente, responsable de traslado y diferencia → sus
            //     filtros del modal (17-09-2026).
            //   - Usuario que registró → selector del modal.
            //
            // Rendimiento (17-09-2026): lo que vive en otra tabla (cliente, productos, bodegas y
            // documentos de origen) se busca como CONJUNTO por palabra
            // (FiltrosBusqueda::condicionTexto, `col` + `sql`) en vez de un STRING_AGG de las
            // líneas por cada cambio; la fecha solo si la palabra puede serlo.
            $fecha   = \App\Helpers\FiltrosBusqueda::SI_FECHA;
            $numero  = \App\Helpers\FiltrosBusqueda::SI_NUMERO;
            $lineasEmpresa = "SELECT d.id_cambio FROM cambios_producto_cv_detalles d WHERE d.id_empresa = :e AND d.eliminado = false";
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    "CONCAT(r.serie, '-', r.secuencial)",                 // N.° de cambio
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('r.establecimiento', 'r.punto_emision', 'r.secuencial'), // el mismo nº en formato canónico
                    'r.motivo',
                    'r.observaciones',                                    // Observaciones
                    ['sql' => "TO_CHAR(r.fecha_cambio, 'DD-MM-YYYY')", 'si' => $fecha], // Fecha
                    ['sql' => 'r.fecha_cambio', 'si' => $fecha],
                    // Cliente: SOLO el nombre, que es la columna del listado. La identificación,
                    // el responsable de traslado, el usuario que registró y la diferencia
                    // salieron del texto libre el 17-09-2026 (no son columnas de esta tabla):
                    // se buscan desde el modal de filtros.
                    ['col' => 'cx.nombre',
                     'sql' => "r.id_cliente IN (SELECT cx.id FROM clientes cx WHERE cx.id_empresa = :e AND {cond})"],
                    // Productos que entran y salen: código y nombre en el catálogo, bodega, lote y NUP
                    ['col' => "CONCAT_WS(' ', px.codigo, px.nombre)",
                     'sql' => "r.id IN ($lineasEmpresa AND d.id_producto IN (SELECT px.id FROM productos px WHERE px.id_empresa = :e AND {cond}))"],
                    ['col' => 'bx.nombre',
                     'sql' => "r.id IN ($lineasEmpresa AND d.id_bodega IN (SELECT bx.id FROM bodegas bx WHERE bx.id_empresa = :e AND {cond}))"],
                    ['col' => "CONCAT_WS(' ', d.lote, d.nup)",
                     'sql' => "r.id IN ($lineasEmpresa AND {cond})"],
                    // Documentos de origen de las líneas (factura de venta y de consignación, cambio, consignación)
                    ['col' => self::sqlNumerosOrigenBusqueda('d'),
                     'sql' => "r.id IN ($lineasEmpresa AND {cond})"],
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (FiltrosModal en la vista). Las claves viejas se
        // conservan: viajan en los enlaces de PDF/Excel.
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'       => 'c.nombre',
                'ruc'           => 'c.identificacion',
                'motivo'        => 'r.motivo',
                'observaciones' => 'r.observaciones',
                'numero'        => "CONCAT(r.serie, '-', r.secuencial)",
            ],
            'exacto' => [
                'estado'         => 'r.estado',
                'serie'          => "CONCAT(r.establecimiento,'-',r.punto_emision)",
                'id_responsable' => 'r.id_responsable_traslado',
                'id_usuario'     => 'r.created_by',
                // asiento:si / asiento:no
                'asiento'        => "CASE WHEN r.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
            ],
            'fecha'  => [
                'fecha'      => 'r.fecha_cambio',
            ],
            'numerico' => [
                'diferencia'         => 'r.diferencia',
                'subtotal_devuelto'  => 'r.subtotal_devuelto',
                'subtotal_entregado' => 'r.subtotal_entregado',
                'secuencial'         => 'r.secuencial::numeric',
            ],
            'existe' => [
                // Nº del documento de origen de alguna línea (factura de venta o de consignación, cambio o consignación)
                'documento_origen' => ['tipo' => 'texto', 'col' => self::sqlNumerosOrigenBusqueda('dx'),
                                       'sql'  => 'EXISTS (SELECT 1 FROM cambios_producto_cv_detalles dx
                                                           WHERE dx.id_cambio = r.id AND dx.eliminado = false AND {cond})'],
            ],
        ]);

        return [$where, $params];
    }

    // ─── LÍNEAS DISPONIBLES PARA DEVOLVER (facturas de consignación + cambios previos) ────

    /**
     * Número "desnudo" de lo que teclea el usuario para buscar un documento por su
     * secuencial: se toma el último segmento ("001-001-000000012" → "000000012"), se
     * dejan solo dígitos y se quitan los ceros de relleno ("12"). Así "12", "000000012"
     * y "001-001-000000012" encuentran el mismo documento, sin importar si el secuencial
     * quedó guardado con ceros o sin ellos (mismo criterio que Retornos CV).
     */
    public static function numeroDesnudo(string $q): string
    {
        $partes = preg_split('/[-\s]+/', trim($q)) ?: [];
        return ltrim(preg_replace('/\D/', '', (string) end($partes)), '0');
    }

    /**
     * Líneas que pueden devolverse, con saldo pendiente (> 0):
     *   (a) líneas de facturas de consignación 'facturada' (consignaciones_facturas_detalles)
     *       → origen_tipo 'FACTURA' (no facturas de venta directas). Su doc_numero es el de
     *       la FACTURA DE VENTA que generó esa factura de consignación;
     *   (b) líneas de ENTREGA de cambios previos Emitida → origen_tipo 'CAMBIO'.
     *
     * saldo = cantidad_origen − Σ(devuelto en cambios Emitida que referencian esa línea).
     *
     * Cada línea del documento sale por separado (una factura con 5 ítems devuelve 5 filas):
     * el cambio se hace por unidad / NUP, así que el usuario agrega cada ítem individualmente.
     *
     * $idCliente: null = buscar entre TODOS los clientes (el usuario localiza el ítem por su
     *   NUP o por el número de la factura, y el cliente del cambio se fija con el de esa línea).
     * $q: NUP, lote, número del documento (completo o solo el secuencial, con o sin ceros;
     *   una factura de consignación se encuentra por el número de su factura de venta y
     *   también por el suyo propio), código o nombre del producto. Vacío solo se admite con
     *   cliente (lista todo lo pendiente de ese cliente).
     */
    public function getLineasDisponiblesCliente(int $idEmpresa, ?int $idCliente, string $q, ?int $excluirCambio = null): array
    {
        $q = trim($q);
        if ($q === '' && ($idCliente === null || $idCliente <= 0)) {
            return [];
        }

        $params = [':e' => $idEmpresa];

        $filtroCliFac = '';
        $filtroCliCam = '';
        if ($idCliente !== null && $idCliente > 0) {
            $filtroCliFac = ' AND vc.id_cliente = :cli';
            $filtroCliCam = ' AND cx.id_cliente = :cli';
            $params[':cli'] = $idCliente;
        }

        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $params[':exc'] = $excluirCambio;
        }

        // Filtro de texto, aplicado DENTRO de cada rama (antes de calcular el saldo por fila):
        // NUP, lote, número(s) del documento, código o nombre del producto. Una rama puede
        // traer varios números (la factura de consignación: el de su factura de venta y el suyo).
        $filtroQ = function (string $nombre, string $codigo, array $numeros, array $secuenciales, string $nup, string $lote) use ($q, &$params): string {
            if ($q === '') {
                return '';
            }
            $params[':q'] = '%' . $q . '%';
            $sql = " AND ($nombre ILIKE :q OR $codigo ILIKE :q OR $nup ILIKE :q OR $lote ILIKE :q";
            foreach ($numeros as $numero) {
                $sql .= " OR $numero ILIKE :q";
            }
            $qnum = self::numeroDesnudo($q);
            if ($qnum !== '') {
                $params[':qnum'] = $qnum;
                foreach ($secuenciales as $secuencial) {
                    $sql .= " OR regexp_replace(TRIM(COALESCE($secuencial, '')), '^0+', '') = :qnum";
                }
            }
            return $sql . ')';
        };

        // Subconsulta reutilizable de "cantidad ya devuelta" para un origen dado.
        // Se compara el PAR cabecera + detalle: las devoluciones anteriores al 16-09-2026 guardan en
        // 'FACTURA' ids de ventas_cabecera / ventas_detalle, que se pisan con los de la factura de
        // consignación (ver sqlJoinsFacturaDeLinea); solo por detalle descontarían saldo ajeno.
        $devuelto = function (string $origenTipo, string $colDetalle, string $colOrigen) use ($excSql): string {
            return "COALESCE((
                SELECT SUM(cd.cantidad)
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'devolucion' AND cd.origen_tipo = '$origenTipo'
                  AND cd.id_origen_detalle = $colDetalle AND cd.id_origen = $colOrigen
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                  $excSql
            ), 0)";
        };

        $numFactura = "(COALESCE(vc.establecimiento,'') || '-' || COALESCE(vc.punto_emision,'') || '-' || COALESCE(vc.secuencial,''))";
        $numVenta   = self::sqlNumeroFacturaVenta('vc', 'fv');
        $numCambio  = "(COALESCE(cx.serie,'') || '-' || COALESCE(cx.secuencial,''))";

        $sql = "
            SELECT * FROM (
                -- (a) Líneas de FACTURAS DE CONSIGNACIÓN (módulo Facturación de consignaciones,
                --     consignaciones_facturas en estado 'facturada'). NO se ofrecen facturas de
                --     venta directas: lo que se cambia es mercadería que salió en consignación
                --     y ya se facturó. El número que se muestra es el de la factura de venta
                --     generada (fv), no el interno de la factura de consignación.
                SELECT
                    'FACTURA'          AS origen_tipo,
                    vc.id              AS id_origen,
                    d.id               AS id_origen_detalle,
                    $numVenta          AS doc_numero,
                    -- Número propio de la factura de consignación: identifica el documento en el
                    -- buscador cuando no tiene factura de venta enlazada (doc_numero vacío).
                    $numFactura        AS doc_numero_consignacion,
                    vc.fecha_emision   AS doc_fecha,
                    vc.id_cliente,
                    c.nombre           AS cliente_nombre,
                    c.identificacion   AS cliente_identificacion,
                    c.email            AS cliente_email,
                    d.id_producto,
                    p.codigo           AS producto_codigo,
                    p.nombre           AS producto_nombre,
                    p.inventariable,
                    p.tipo_produccion,
                    d.precio_unitario,
                    d.id_impuesto,
                    COALESCE(d.porcentaje_impuesto, 0) AS porcentaje_impuesto,
                    d.lote             AS lote,
                    d.nup              AS nup,
                    d.fecha_caducidad,
                    d.id_bodega,
                    b.nombre           AS bodega_nombre,
                    d.cantidad         AS cantidad_origen,
                    " . $devuelto('FACTURA', 'd.id', 'vc.id') . " AS cantidad_devuelta
                FROM consignaciones_facturas_detalles d
                INNER JOIN consignaciones_facturas vc ON vc.id = d.id_consignacion_factura
                LEFT JOIN ventas_cabecera fv ON fv.id = vc.id_factura
                INNER JOIN clientes c ON c.id = vc.id_cliente
                INNER JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas b ON b.id = d.id_bodega
                WHERE vc.id_empresa = :e AND vc.eliminado = false
                  AND vc.estado = 'facturada'
                  -- Un registro generado por un cambio no se devuelve por aquí: esa unidad se
                  -- devuelve desde el cambio que la entregó (rama b).
                  " . self::sqlNoEsRegistroDeCambio('vc') . "
                  AND COALESCE(d.eliminado, false) = false
                  {$filtroCliFac}
                  AND COALESCE(p.tipo_produccion,'01') = '01'   -- solo bienes/productos, no servicios
                  " . $filtroQ('p.nombre', 'p.codigo', [$numVenta, $numFactura], ['fv.secuencial', 'vc.secuencial'], 'd.nup', 'd.lote') . "

                UNION ALL

                -- (b) Líneas de ENTREGA de cambios anteriores (Emitida)
                SELECT
                    'CAMBIO'           AS origen_tipo,
                    e.id_cambio        AS id_origen,
                    e.id               AS id_origen_detalle,
                    $numCambio         AS doc_numero,
                    NULL               AS doc_numero_consignacion,
                    cx.fecha_cambio    AS doc_fecha,
                    cx.id_cliente,
                    c.nombre           AS cliente_nombre,
                    c.identificacion   AS cliente_identificacion,
                    c.email            AS cliente_email,
                    e.id_producto,
                    p.codigo           AS producto_codigo,
                    p.nombre           AS producto_nombre,
                    p.inventariable,
                    p.tipo_produccion,
                    e.precio_unitario,
                    e.id_impuesto,
                    e.porcentaje_impuesto,
                    e.lote,
                    e.nup,
                    e.fecha_caducidad,
                    e.id_bodega,
                    b.nombre           AS bodega_nombre,
                    e.cantidad         AS cantidad_origen,
                    " . $devuelto('CAMBIO', 'e.id', 'e.id_cambio') . " AS cantidad_devuelta
                FROM cambios_producto_cv_detalles e
                INNER JOIN cambios_producto_cv cx ON cx.id = e.id_cambio
                INNER JOIN clientes c ON c.id = cx.id_cliente
                INNER JOIN productos p ON p.id = e.id_producto
                LEFT JOIN bodegas b ON b.id = e.id_bodega
                WHERE e.tipo_linea = 'entrega' AND e.eliminado = false
                  AND cx.id_empresa = :e
                  AND cx.eliminado = false AND cx.estado = 'Emitida'
                  {$filtroCliCam}
                  AND COALESCE(p.tipo_produccion,'01') = '01'   -- solo bienes/productos, no servicios
                  " . $filtroQ('p.nombre', 'p.codigo', [$numCambio], ['cx.secuencial'], 'e.nup', 'e.lote') . "
            ) t
            WHERE (t.cantidad_origen - t.cantidad_devuelta) > 0
            ORDER BY t.doc_fecha DESC, t.id_origen DESC, t.id_origen_detalle ASC
            LIMIT 100
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['saldo_pendiente'] = (float) $r['cantidad_origen'] - (float) $r['cantidad_devuelta'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Saldo pendiente de devolver de UNA línea de origen (para validación en el Service).
     */
    public function getSaldoLineaOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa, ?int $excluirCambio = null): float
    {
        $cantidad = $this->getCantidadOrigen($origenTipo, $idOrigenDetalle, $idEmpresa);
        if ($cantidad <= 0) {
            return 0.0;
        }

        $paramsDev = [':id' => $idOrigenDetalle, ':ot' => $origenTipo, ':id2' => $idOrigenDetalle];
        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $paramsDev[':exc'] = $excluirCambio;
        }

        // Par cabecera + detalle (ver el comentario de $devuelto en getLineasDisponiblesCliente).
        $cabecera = $origenTipo === 'FACTURA'
            ? '(SELECT x.id_consignacion_factura FROM consignaciones_facturas_detalles x WHERE x.id = :id2)'
            : '(SELECT x.id_cambio FROM cambios_producto_cv_detalles x WHERE x.id = :id2)';

        $sqlDev = "SELECT COALESCE(SUM(cd.cantidad), 0)
                   FROM cambios_producto_cv_detalles cd
                   INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                   WHERE cd.tipo_linea = 'devolucion' AND cd.origen_tipo = :ot
                     AND cd.id_origen_detalle = :id AND cd.id_origen = {$cabecera}
                     AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                     $excSql";
        $stDev = $this->db->prepare($sqlDev);
        $stDev->execute($paramsDev);
        $devuelto = (float) $stDev->fetchColumn();

        return $cantidad - $devuelto;
    }

    /** Cantidad original de la línea de origen (factura de consignación o entrega de cambio previo). */
    private function getCantidadOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa): float
    {
        if ($origenTipo === 'FACTURA') { // factura de consignación (no un registro de cambio)
            $sql = "SELECT d.cantidad
                    FROM consignaciones_facturas_detalles d
                    INNER JOIN consignaciones_facturas v ON v.id = d.id_consignacion_factura
                    WHERE d.id = :id AND v.id_empresa = :e AND v.eliminado = false
                      AND COALESCE(d.eliminado, false) = false
                      AND v.estado = 'facturada'" . self::sqlNoEsRegistroDeCambio('v');
        } else { // CAMBIO
            $sql = "SELECT e.cantidad
                    FROM cambios_producto_cv_detalles e
                    INNER JOIN cambios_producto_cv cx ON cx.id = e.id_cambio
                    WHERE e.id = :id AND e.tipo_linea = 'entrega' AND cx.id_empresa = :e
                      AND e.eliminado = false AND cx.eliminado = false AND cx.estado = 'Emitida'";
        }
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrigenDetalle, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v === false ? 0.0 : (float) $v;
    }

    /**
     * Datos autoritativos "tal cual" de la línea de origen a devolver (para el Service).
     * Devuelve producto, precio, impuesto, cantidad y descuento de la línea (cantidad_origen,
     * descuento_origen), lote, nup, bodega, caducidad, id_origen, número del documento y el
     * cliente dueño (el Service exige que sea el cliente del cambio).
     */
    public function getDatosLineaOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa): ?array
    {
        if ($origenTipo === 'FACTURA') { // factura de consignación
            $sql = "SELECT v.id AS id_origen, v.id_cliente,
                           (COALESCE(v.establecimiento,'') || '-' || COALESCE(v.punto_emision,'') || '-' || COALESCE(v.secuencial,'')) AS doc_numero,
                           d.id_producto,
                           d.precio_unitario,
                           d.id_impuesto,
                           COALESCE(d.porcentaje_impuesto, 0) AS porcentaje_impuesto,
                           d.cantidad AS cantidad_origen,
                           COALESCE(d.descuento, 0) AS descuento_origen,
                           d.id_bodega, d.lote, d.nup, d.fecha_caducidad,
                           p.nombre AS producto_nombre, p.inventariable, p.tipo_produccion
                    FROM consignaciones_facturas_detalles d
                    INNER JOIN consignaciones_facturas v ON v.id = d.id_consignacion_factura
                    INNER JOIN productos p ON p.id = d.id_producto
                    WHERE d.id = :id AND v.id_empresa = :e AND v.eliminado = false
                      AND COALESCE(d.eliminado, false) = false
                      AND v.estado = 'facturada'" . self::sqlNoEsRegistroDeCambio('v');
        } else { // CAMBIO
            $sql = "SELECT e.id_cambio AS id_origen, cx.id_cliente,
                           (COALESCE(cx.serie,'') || '-' || COALESCE(cx.secuencial,'')) AS doc_numero,
                           e.id_producto,
                           e.precio_unitario, e.id_impuesto, e.porcentaje_impuesto,
                           e.cantidad AS cantidad_origen,
                           0 AS descuento_origen,
                           e.id_bodega, e.lote, e.nup, e.fecha_caducidad,
                           p.nombre AS producto_nombre, p.inventariable, p.tipo_produccion
                    FROM cambios_producto_cv_detalles e
                    INNER JOIN cambios_producto_cv cx ON cx.id = e.id_cambio
                    INNER JOIN productos p ON p.id = e.id_producto
                    WHERE e.id = :id AND e.tipo_linea = 'entrega' AND cx.id_empresa = :e
                      AND e.eliminado = false AND cx.eliminado = false AND cx.estado = 'Emitida'";
        }
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrigenDetalle, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─── LÍNEAS DE CONSIGNACIÓN DISPONIBLES PARA ENTREGAR A CAMBIO ─────────────

    /**
     * Subconsulta: cantidad de una línea de consignación ya ENTREGADA a cambio en
     * cambios Emitida (líneas de entrega con origen_tipo 'CONSIGNACION').
     *
     * Pública y estática a propósito: Retornos CV, Facturación CV, el kardex de la
     * consignación y el Reporte de inventarios la suman junto a sus "retornado" y "facturado".
     * No cuenta las entregas que quedaron registradas en Facturación de consignaciones: esas
     * ya descuentan el saldo como facturadas (sqlSinRegistroFacturacion).
     */
    public static function sqlEntregadoEnCambios(string $idExpr, string $excSql = ''): string
    {
        return "SELECT SUM(cd.cantidad)
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
                  AND cd.id_origen_detalle = $idExpr
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                  $excSql" . self::sqlSinRegistroFacturacion('cd');
    }

    /**
     * Lo mismo que sqlEntregadoEnCambios(), pero agrupado por línea de consignación para
     * MUCHAS líneas a la vez: devuelve (id, total) para las líneas de $idsSubquery (una
     * subconsulta que lista ids de consignaciones_ventas_detalles). Pensada para cruzarse
     * con LEFT JOIN en reportes: una subconsulta correlacionada por línea cuesta una
     * búsqueda por cada fila del reporte (Reporte de Inventarios: ~20.000 por Mostrar).
     * Mismo criterio que la versión por línea: si cambia uno, cambia el otro.
     */
    public static function sqlEntregadoEnCambiosPorLinea(string $idsSubquery): string
    {
        return "SELECT cd.id_origen_detalle AS id, SUM(cd.cantidad) AS total
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
                  AND cd.id_origen_detalle IN ($idsSubquery)
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'"
                . self::sqlSinRegistroFacturacion('cd') . "
                GROUP BY cd.id_origen_detalle";
    }

    /**
     * Cantidad entregada a cambio por cada línea de UNA consignación (cambios Emitida):
     * [id_consignacion_detalle => cantidad]. Alimenta las columnas "Cambio" del PDF, el
     * Excel y el modal de la consignación (mismo patrón que getRetornadoPorConsignacion).
     * Lo registrado en Facturación de consignaciones va en "Facturados", no aquí.
     */
    public function getEntregadoPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "SELECT cd.id_origen_detalle AS idd, COALESCE(SUM(cd.cantidad), 0) AS cant
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
                  AND cd.id_origen = :idc AND cd.id_empresa = :e
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'"
                . self::sqlSinRegistroFacturacion('cd') . "
                GROUP BY cd.id_origen_detalle";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['idd']] = (float) $r['cant'];
        }
        return $out;
    }

    /** Subconsulta de cantidad retornada (retornos Emitida) de una línea de consignación. */
    private function sqlRetornado(string $idExpr): string
    {
        return "SELECT SUM(rcd.cantidad)
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle = $idExpr
                  AND rcd.eliminado = false
                  AND rc.eliminado = false
                  AND rc.estado = 'Emitida'";
    }

    /** Subconsulta de cantidad facturada (docs 'facturada') de una línea de consignación. */
    private function sqlFacturado(string $idExpr): string
    {
        return "SELECT SUM(cfd.cantidad)
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                WHERE cfd.id_consignacion_detalle = $idExpr
                  AND cfd.eliminado = false
                  AND cf.eliminado = false AND cf.estado = 'facturada'";
    }

    /**
     * Líneas de consignaciones ENTREGADAS con saldo en poder del cliente (> 0), para
     * entregarlas a cambio. Una fila por línea de consignación (cada unidad / NUP sale
     * por separado, igual que en Retornos CV).
     *
     * saldo = consignado − retornado (Retornos Emitida) − facturado (Facturación CV)
     *         − entregado en otros cambios Emitida.
     *
     * $idCliente: null = todas las consignaciones (el cliente del cambio se fija después
     *   con el de la consignación elegida). $q: número de consignación (completo o solo
     *   el secuencial), cliente, NUP, lote, código o nombre del producto. Vacío solo se
     *   admite con cliente.
     */
    public function getLineasConsignacionDisponibles(int $idEmpresa, string $q, ?int $idCliente, ?int $excluirCambio = null): array
    {
        $q = trim($q);
        if ($q === '' && ($idCliente === null || $idCliente <= 0)) {
            return [];
        }

        $params = [':e' => $idEmpresa];

        $filtroCli = '';
        if ($idCliente !== null && $idCliente > 0) {
            $filtroCli = ' AND cv.id_cliente = :cli';
            $params[':cli'] = $idCliente;
        }

        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $params[':exc'] = $excluirCambio;
        }

        $numero  = "(COALESCE(cv.serie,'') || '-' || COALESCE(cv.secuencial,''))";
        $filtroQ = '';
        if ($q !== '') {
            $params[':q'] = '%' . $q . '%';
            $filtroQ = " AND (p.nombre ILIKE :q OR p.codigo ILIKE :q OR cvd.nup ILIKE :q OR cvd.lote ILIKE :q
                              OR $numero ILIKE :q OR cv.secuencial ILIKE :q
                              OR c.nombre ILIKE :q OR c.identificacion ILIKE :q";
            $qnum = self::numeroDesnudo($q);
            if ($qnum !== '') {
                $params[':qnum'] = $qnum;
                $filtroQ .= " OR regexp_replace(TRIM(COALESCE(cv.secuencial, '')), '^0+', '') = :qnum";
            }
            $filtroQ .= ')';
        }

        $sql = "
            SELECT * FROM (
                SELECT
                    'CONSIGNACION'       AS origen_tipo,
                    cv.id                AS id_origen,
                    cvd.id               AS id_origen_detalle,
                    $numero              AS doc_numero,
                    cv.fecha_emision     AS doc_fecha,
                    cv.id_cliente,
                    c.nombre             AS cliente_nombre,
                    c.identificacion     AS cliente_identificacion,
                    c.email              AS cliente_email,
                    cvd.id_producto,
                    p.codigo             AS producto_codigo,
                    p.nombre             AS producto_nombre,
                    p.inventariable,
                    p.tipo_produccion,
                    cvd.precio_unitario,
                    cvd.id_impuesto,
                    cvd.porcentaje_impuesto,
                    cvd.lote,
                    cvd.nup,
                    cvd.fecha_caducidad,
                    cvd.id_bodega,
                    b.nombre             AS bodega_nombre,
                    cvd.cantidad         AS cantidad_origen,
                    COALESCE((" . $this->sqlRetornado('cvd.id') . "), 0)                 AS cantidad_retornada,
                    COALESCE((" . $this->sqlFacturado('cvd.id') . "), 0)                 AS cantidad_facturada,
                    COALESCE((" . self::sqlEntregadoEnCambios('cvd.id', $excSql) . "), 0) AS cantidad_entregada
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN clientes c ON c.id = cv.id_cliente
                INNER JOIN productos p ON p.id = cvd.id_producto
                LEFT JOIN bodegas b ON b.id = cvd.id_bodega
                WHERE cv.id_empresa = :e AND cv.eliminado = false
                  AND cv.estado = 'Entregada'
                  AND cvd.eliminado = false
                  {$filtroCli}
                  AND COALESCE(p.tipo_produccion,'01') = '01'   -- solo bienes/productos, no servicios
                  {$filtroQ}
            ) t
            WHERE (t.cantidad_origen - t.cantidad_retornada - t.cantidad_facturada - t.cantidad_entregada) > 0
            ORDER BY t.doc_fecha DESC, t.id_origen DESC, t.id_origen_detalle ASC
            LIMIT 100
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['saldo_pendiente'] = (float) $r['cantidad_origen']
                - (float) $r['cantidad_retornada']
                - (float) $r['cantidad_facturada']
                - (float) $r['cantidad_entregada'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Saldo en poder del cliente de UNA línea de consignación (para validación en el Service):
     * consignado − retornado − facturado − entregado en cambios Emitida (sin contar $excluirCambio).
     */
    public function getSaldoLineaConsignacion(int $idConsignacionDetalle, int $idEmpresa, ?int $excluirCambio = null): float
    {
        $params = [':id' => $idConsignacionDetalle, ':e' => $idEmpresa];
        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $params[':exc'] = $excluirCambio;
        }

        $sql = "SELECT cvd.cantidad
                       - COALESCE((" . $this->sqlRetornado('cvd.id') . "), 0)
                       - COALESCE((" . $this->sqlFacturado('cvd.id') . "), 0)
                       - COALESCE((" . self::sqlEntregadoEnCambios('cvd.id', $excSql) . "), 0)
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                WHERE cvd.id = :id AND cv.id_empresa = :e
                  AND cv.eliminado = false AND cv.estado = 'Entregada'
                  AND cvd.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? 0.0 : (float) $v;
    }

    /**
     * Datos autoritativos "tal cual" de la línea de consignación que se entrega a cambio
     * (producto, precio, impuesto, lote, nup, bodega, caducidad, número y cliente dueño).
     */
    public function getDatosLineaConsignacion(int $idConsignacionDetalle, int $idEmpresa): ?array
    {
        $sql = "SELECT cv.id AS id_origen, cv.id_cliente,
                       (COALESCE(cv.serie,'') || '-' || COALESCE(cv.secuencial,'')) AS doc_numero,
                       cvd.id_producto, cvd.precio_unitario, cvd.id_impuesto, cvd.porcentaje_impuesto,
                       cvd.id_bodega, cvd.lote, cvd.nup, cvd.fecha_caducidad,
                       p.nombre AS producto_nombre, p.inventariable, p.tipo_produccion
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                WHERE cvd.id = :id AND cv.id_empresa = :e
                  AND cv.eliminado = false AND cv.estado = 'Entregada'
                  AND cvd.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacionDetalle, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─── INVENTARIO: stock por bodega / lote / NUP (para entregar desde bodega) ─

    /**
     * Existencias con stock > 0 que coinciden con $q, una fila por producto + bodega +
     * lote + NUP (kardex agrupado, mismo ambiente de la empresa). Permite localizar la
     * unidad exacta a entregar por su NUP o lote, además de por código o nombre.
     */
    public function buscarInventario(int $idEmpresa, string $q, int $limite = 30): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $limite = max(1, min($limite, 100));

        $sql = "SELECT k.id_producto,
                       p.codigo                       AS producto_codigo,
                       p.nombre                       AS producto_nombre,
                       p.inventariable,
                       p.tipo_produccion,
                       k.id_bodega,
                       b.nombre                       AS bodega_nombre,
                       COALESCE(k.numero_lote, '')    AS lote,
                       COALESCE(k.nup, '')            AS nup,
                       MAX(k.fecha_caducidad)         AS fecha_caducidad,
                       ROUND(SUM(k.cantidad), 6)      AS stock
                FROM inventario_kardex k
                INNER JOIN productos p ON p.id = k.id_producto
                INNER JOIN bodegas b ON b.id = k.id_bodega
                WHERE k.id_empresa = :e AND k.eliminado = false
                  AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                  AND p.eliminado = false AND b.eliminado = false
                  AND COALESCE(p.tipo_produccion,'01') = '01'
                  AND (p.nombre ILIKE :q OR p.codigo ILIKE :q OR k.nup ILIKE :q OR k.numero_lote ILIKE :q)
                GROUP BY k.id_producto, p.codigo, p.nombre, p.inventariable, p.tipo_produccion,
                         k.id_bodega, b.nombre, COALESCE(k.numero_lote, ''), COALESCE(k.nup, '')
                HAVING ROUND(SUM(k.cantidad), 6) > 0
                ORDER BY p.nombre ASC, b.nombre ASC, lote ASC, nup ASC
                LIMIT {$limite}";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ¿Ya está usado ese secuencial en el mismo punto de emisión y ambiente?
     *
     * Misma defensa que Facturas de Venta (FacturaVentaRepository::existeSecuencial):
     * el número se calcula por AJAX al abrir el modal, así que entre ese cálculo y el
     * guardado otro usuario pudo haber emitido con el mismo número. Se compara por
     * VALOR numérico porque el secuencial puede estar guardado con o sin ceros a la
     * izquierda ('100' vs '000000100').
     */
    public function existeSecuencial(int $idEmpresa, int $idPunto, string $secuencial, string $tipoAmbiente, ?int $excluirId = null): bool
    {
        $num = (int) preg_replace('/\D/', '', $secuencial);
        if ($idPunto <= 0 || $num <= 0) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                FROM cambios_producto_cv
                WHERE id_empresa = :e AND id_punto_emision = :p
                  AND COALESCE(tipo_ambiente, '1') = :ta
                  AND eliminado = false
                  AND CAST(NULLIF(regexp_replace(COALESCE(secuencial, ''), '\D', '', 'g'), '') AS BIGINT) = :sec";
        $params = [':e' => $idEmpresa, ':p' => $idPunto, ':ta' => $tipoAmbiente, ':sec' => $num];

        if ($excluirId !== null) {
            $sql .= " AND id <> :ex";
            $params[':ex'] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    /** Datos del producto de catálogo para una línea de ENTREGA (autoritativo). */
    public function getProductoParaEntrega(int $idProducto, int $idEmpresa): ?array
    {
        $sql = "SELECT p.id AS id_producto, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       p.inventariable, p.tipo_produccion
                FROM productos p
                WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false
                  AND COALESCE(p.tipo_produccion,'01') = '01'";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = "INSERT INTO cambios_producto_cv (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function insertDetalle(array $d): int
    {
        $sql = "INSERT INTO cambios_producto_cv_detalles (
                    id_cambio, id_empresa, tipo_linea, origen_tipo, id_origen, id_origen_detalle,
                    id_producto, cantidad, precio_unitario, subtotal, id_impuesto, porcentaje_impuesto,
                    valor_impuesto, total, id_bodega, lote, nup, fecha_caducidad, eliminado
                ) VALUES (
                    :idc, :e, :tl, :ot, :io, :iod, :prod, :cant, :pu, :sub, :idi, :pi,
                    :vi, :tot, :idb, :lote, :nup, :fc, false
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        try {
            $st->execute($this->paramsInsertDetalle($d));
        } catch (\PDOException $e) {
            // origen_tipo nació como VARCHAR(10) y 'CONSIGNACION' tiene 12: si aún no se
            // aplicó la migración que lo amplía, avisar qué falta en vez del error crudo.
            if (($e->errorInfo[0] ?? '') === '22001' && strtoupper((string) ($d['origen_tipo'] ?? '')) === 'CONSIGNACION') {
                throw new \Exception('La base de datos aún no admite entregas desde consignación: aplique database/migrations/20260915_cambios_producto_cv_origen_consignacion.sql.');
            }
            throw $e;
        }
        return (int) $st->fetchColumn();
    }

    /** Parámetros del INSERT de detalle (separado para poder envolver el execute). */
    private function paramsInsertDetalle(array $d): array
    {
        return [
            ':idc'  => $d['id_cambio'],
            ':e'    => $d['id_empresa'],
            ':tl'   => $d['tipo_linea'],
            ':ot'   => $d['origen_tipo'] ?? null,
            ':io'   => $d['id_origen'] ?? null,
            ':iod'  => $d['id_origen_detalle'] ?? null,
            ':prod' => $d['id_producto'],
            ':cant' => $d['cantidad'],
            ':pu'   => $d['precio_unitario'] ?? 0,
            ':sub'  => $d['subtotal'] ?? 0,
            ':idi'  => $d['id_impuesto'] ?? null,
            ':pi'   => $d['porcentaje_impuesto'] ?? 0,
            ':vi'   => $d['valor_impuesto'] ?? 0,
            ':tot'  => $d['total'] ?? 0,
            ':idb'  => $d['id_bodega'] ?? null,
            ':lote' => (isset($d['lote']) && $d['lote'] !== '') ? $d['lote'] : null,
            ':nup'  => (isset($d['nup']) && $d['nup'] !== '') ? $d['nup'] : null,
            ':fc'   => (isset($d['fecha_caducidad']) && $d['fecha_caducidad'] !== '') ? $d['fecha_caducidad'] : null,
        ];
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT r.*,
                       c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                       c.direccion as cliente_direccion, c.email as cliente_email
                FROM cambios_producto_cv r
                INNER JOIN clientes c ON c.id = r.id_cliente
                WHERE r.id = :id AND r.id_empresa = :e AND r.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Detalles del cambio con el número del documento de origen de cada línea: para las
     * devoluciones, el de la FACTURA DE VENTA (de la factura de consignación o, en los cambios
     * anteriores al 16-09-2026, la factura de venta directa; ver sqlJoinsFacturaDeLinea) o el del
     * cambio previo; para las entregas tomadas de una consignación, el de la consignación.
     */
    public function getDetalles(int $idCambio, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*,
                   p.nombre as producto_nombre, p.codigo as producto_codigo, p.inventariable, p.tipo_produccion,
                   b.nombre as bodega_nombre,
                   CASE d.origen_tipo
                        WHEN 'FACTURA'      THEN " . self::sqlNumeroFacturaDeJoins('o') . "
                        WHEN 'CAMBIO'       THEN (COALESCE(co.serie,'') || '-' || COALESCE(co.secuencial,''))
                        WHEN 'CONSIGNACION' THEN (COALESCE(cvo.serie,'') || '-' || COALESCE(cvo.secuencial,''))
                        ELSE NULL
                   END AS origen_numero
            FROM cambios_producto_cv_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            " . self::sqlJoinsFacturaDeLinea('d', 'o') . "
            LEFT JOIN cambios_producto_cv co    ON d.origen_tipo = 'CAMBIO'       AND co.id  = d.id_origen
            LEFT JOIN consignaciones_ventas cvo ON d.origen_tipo = 'CONSIGNACION' AND cvo.id = d.id_origen
            WHERE d.id_cambio = :id AND d.id_empresa = :e AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY d.tipo_linea DESC, d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCambio, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vincula (o desvincula con null) el asiento contable generado para el cambio. */
    public function updateAsientoContable(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE cambios_producto_cv SET id_asiento_contable = :a WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAsiento, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Marca lógicamente como eliminados los detalles (para reemplazarlos al editar). */
    public function deleteDetalles(int $idCambio, int $idEmpresa): void
    {
        $sql = "UPDATE cambios_producto_cv_detalles SET eliminado = true
                WHERE id_cambio = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCambio, ':e' => $idEmpresa]);
    }

    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE cambios_producto_cv SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE cambios_producto_cv
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE cambios_producto_cv
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $sqlDet = "UPDATE cambios_producto_cv_detalles
                   SET eliminado = true
                   WHERE id_cambio = :id AND id_empresa = :e AND eliminado = false";
        $stDet = $this->db->prepare($sqlDet);
        $stDet->execute([':id' => $id, ':e' => $idEmpresa]);
    }
}
