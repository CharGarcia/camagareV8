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
 *     origen es una línea de factura de venta (ventas_detalle) o una línea de
 *     ENTREGA de un cambio anterior (encadenado). Se controla el saldo.
 *   - líneas de ENTREGA (tipo_linea='entrega') → salida de inventario (catálogo).
 */
class CambioProductoCvRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('cambios_producto_cv');
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

    // ─── LISTADO PAGINADO ─────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE r.id_empresa = :e AND r.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND r.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['r.secuencial', 'c.nombre', 'c.identificacion', 'r.estado', 'r.motivo'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'    => 'c.nombre',
                'motivo'     => 'r.motivo',
            ],
            'exacto' => [
                'estado'     => 'r.estado',
                'serie'      => "CONCAT(r.establecimiento,'-',r.punto_emision)",
            ],
            'fecha'  => [
                'fecha'      => 'r.fecha_cambio',
            ],
            'numerico' => [
                'diferencia' => 'r.diferencia',
                'secuencial' => 'r.secuencial::numeric',
            ],
        ]);

        $sqlCount = "
            SELECT COUNT(*)
            FROM cambios_producto_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            $where
        ";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $colMap = [
            'fecha_cambio' => 'r.fecha_cambio',
            'secuencial'   => 'r.secuencial',
            'cliente'      => 'c.nombre',
            'estado'       => 'r.estado',
            'diferencia'   => 'r.diferencia',
        ];
        $sort = $colMap[$ordenCol] ?? 'r.fecha_cambio';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT r.*,
                   c.nombre as cliente_nombre, c.identificacion as cliente_identificacion
            FROM cambios_producto_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            $where
            ORDER BY $sort $dir, r.id DESC
            $limitClause
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    // ─── LÍNEAS DISPONIBLES PARA DEVOLVER (origen factura + cambios previos) ────

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
     *   (a) líneas de facturas de venta (ventas_detalle) → origen_tipo 'FACTURA';
     *   (b) líneas de ENTREGA de cambios previos Emitida → origen_tipo 'CAMBIO'.
     *
     * saldo = cantidad_origen − Σ(devuelto en cambios Emitida que referencian esa línea).
     *
     * Cada línea del documento sale por separado (una factura con 5 ítems devuelve 5 filas):
     * el cambio se hace por unidad / NUP, así que el usuario agrega cada ítem individualmente.
     *
     * $idCliente: null = buscar entre TODOS los clientes (el usuario localiza el ítem por su
     *   NUP o por el número de la factura, y el cliente del cambio se fija con el de esa línea).
     * $q: NUP, lote, número del documento (completo o solo el secuencial, con o sin ceros),
     *   código o nombre del producto. Vacío solo se admite con cliente (lista todo lo
     *   pendiente de ese cliente).
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
        // NUP, lote, número del documento, código o nombre del producto.
        $filtroQ = function (string $nombre, string $codigo, string $numero, string $secuencial, string $nup, string $lote) use ($q, &$params): string {
            if ($q === '') {
                return '';
            }
            $params[':q'] = '%' . $q . '%';
            $sql = " AND ($nombre ILIKE :q OR $codigo ILIKE :q OR $numero ILIKE :q OR $nup ILIKE :q OR $lote ILIKE :q";
            $qnum = self::numeroDesnudo($q);
            if ($qnum !== '') {
                $params[':qnum'] = $qnum;
                $sql .= " OR regexp_replace(TRIM(COALESCE($secuencial, '')), '^0+', '') = :qnum";
            }
            return $sql . ')';
        };

        // Subconsulta reutilizable de "cantidad ya devuelta" para un origen dado.
        $devuelto = function (string $origenTipo, string $colDetalle) use ($excSql): string {
            return "COALESCE((
                SELECT SUM(cd.cantidad)
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'devolucion' AND cd.origen_tipo = '$origenTipo'
                  AND cd.id_origen_detalle = $colDetalle
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                  $excSql
            ), 0)";
        };

        $numFactura = "(COALESCE(vc.establecimiento,'') || '-' || COALESCE(vc.punto_emision,'') || '-' || COALESCE(vc.secuencial,''))";
        $numCambio  = "(COALESCE(cx.serie,'') || '-' || COALESCE(cx.secuencial,''))";

        $sql = "
            SELECT * FROM (
                -- (a) Líneas de facturas de venta
                SELECT
                    'FACTURA'          AS origen_tipo,
                    vc.id              AS id_origen,
                    d.id               AS id_origen_detalle,
                    $numFactura        AS doc_numero,
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
                    NULL::integer      AS id_impuesto,
                    COALESCE((SELECT MAX(vdi.tarifa) FROM ventas_detalle_impuestos vdi WHERE vdi.id_venta_detalle = d.id), 0) AS porcentaje_impuesto,
                    d.numero_lote      AS lote,
                    d.nup              AS nup,
                    d.fecha_caducidad,
                    d.id_bodega,
                    b.nombre           AS bodega_nombre,
                    d.cantidad         AS cantidad_origen,
                    " . $devuelto('FACTURA', 'd.id') . " AS cantidad_devuelta
                FROM ventas_detalle d
                INNER JOIN ventas_cabecera vc ON vc.id = d.id_venta
                INNER JOIN clientes c ON c.id = vc.id_cliente
                INNER JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas b ON b.id = d.id_bodega
                WHERE vc.id_empresa = :e AND vc.eliminado = false
                  AND LOWER(COALESCE(vc.estado,'')) = 'autorizado'
                  {$filtroCliFac}
                  AND COALESCE(p.tipo_produccion,'01') = '01'   -- solo bienes/productos, no servicios
                  " . $filtroQ('p.nombre', 'p.codigo', $numFactura, 'vc.secuencial', 'd.nup', 'd.numero_lote') . "

                UNION ALL

                -- (b) Líneas de ENTREGA de cambios anteriores (Emitida)
                SELECT
                    'CAMBIO'           AS origen_tipo,
                    e.id_cambio        AS id_origen,
                    e.id               AS id_origen_detalle,
                    $numCambio         AS doc_numero,
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
                    " . $devuelto('CAMBIO', 'e.id') . " AS cantidad_devuelta
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
                  " . $filtroQ('p.nombre', 'p.codigo', $numCambio, 'cx.secuencial', 'e.nup', 'e.lote') . "
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

        $paramsDev = [':id' => $idOrigenDetalle, ':ot' => $origenTipo];
        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $paramsDev[':exc'] = $excluirCambio;
        }

        $sqlDev = "SELECT COALESCE(SUM(cd.cantidad), 0)
                   FROM cambios_producto_cv_detalles cd
                   INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                   WHERE cd.tipo_linea = 'devolucion' AND cd.origen_tipo = :ot
                     AND cd.id_origen_detalle = :id
                     AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                     $excSql";
        $stDev = $this->db->prepare($sqlDev);
        $stDev->execute($paramsDev);
        $devuelto = (float) $stDev->fetchColumn();

        return $cantidad - $devuelto;
    }

    /** Cantidad original de la línea de origen (factura o entrega de cambio previo). */
    private function getCantidadOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa): float
    {
        if ($origenTipo === 'FACTURA') {
            $sql = "SELECT d.cantidad
                    FROM ventas_detalle d
                    INNER JOIN ventas_cabecera v ON v.id = d.id_venta
                    WHERE d.id = :id AND v.id_empresa = :e AND v.eliminado = false
                      AND LOWER(COALESCE(v.estado,'')) = 'autorizado'";
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
     * Devuelve producto, precio, impuesto, lote, nup, bodega, caducidad, id_origen, número
     * del documento y el cliente dueño (el Service exige que sea el cliente del cambio).
     */
    public function getDatosLineaOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa): ?array
    {
        if ($origenTipo === 'FACTURA') {
            $sql = "SELECT v.id AS id_origen, v.id_cliente,
                           (COALESCE(v.establecimiento,'') || '-' || COALESCE(v.punto_emision,'') || '-' || COALESCE(v.secuencial,'')) AS doc_numero,
                           d.id_producto,
                           d.precio_unitario,
                           NULL::integer AS id_impuesto,
                           COALESCE((SELECT MAX(vdi.tarifa) FROM ventas_detalle_impuestos vdi WHERE vdi.id_venta_detalle = d.id), 0) AS porcentaje_impuesto,
                           d.id_bodega, d.numero_lote AS lote, d.nup, d.fecha_caducidad,
                           p.nombre AS producto_nombre, p.inventariable, p.tipo_produccion
                    FROM ventas_detalle d
                    INNER JOIN ventas_cabecera v ON v.id = d.id_venta
                    INNER JOIN productos p ON p.id = d.id_producto
                    WHERE d.id = :id AND v.id_empresa = :e AND v.eliminado = false
                      AND LOWER(COALESCE(v.estado,'')) = 'autorizado'";
        } else { // CAMBIO
            $sql = "SELECT e.id_cambio AS id_origen, cx.id_cliente,
                           (COALESCE(cx.serie,'') || '-' || COALESCE(cx.secuencial,'')) AS doc_numero,
                           e.id_producto,
                           e.precio_unitario, e.id_impuesto, e.porcentaje_impuesto,
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
     * Pública y estática a propósito: cuando Retornos CV, Facturación CV y el kardex de
     * la consignación deban descontar también lo entregado por cambios (hoy no lo hacen),
     * enchufan esta misma subconsulta junto a sus "retornado" y "facturado".
     */
    public static function sqlEntregadoEnCambios(string $idExpr, string $excSql = ''): string
    {
        return "SELECT SUM(cd.cantidad)
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
                  AND cd.id_origen_detalle = $idExpr
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                  $excSql";
    }

    /**
     * Cantidad entregada a cambio por cada línea de UNA consignación (cambios Emitida):
     * [id_consignacion_detalle => cantidad]. Alimenta las columnas "Cambio" del PDF, el
     * Excel y el modal de la consignación (mismo patrón que getRetornadoPorConsignacion).
     */
    public function getEntregadoPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "SELECT cd.id_origen_detalle AS idd, COALESCE(SUM(cd.cantidad), 0) AS cant
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
                  AND cd.id_origen = :idc AND cd.id_empresa = :e
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
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
     * Detalles del cambio con el número del documento de origen de cada línea
     * (factura / cambio previo para las devoluciones, consignación para las entregas
     * tomadas de una consignación).
     */
    public function getDetalles(int $idCambio, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*,
                   p.nombre as producto_nombre, p.codigo as producto_codigo, p.inventariable, p.tipo_produccion,
                   b.nombre as bodega_nombre,
                   CASE d.origen_tipo
                        WHEN 'FACTURA'      THEN (COALESCE(vo.establecimiento,'') || '-' || COALESCE(vo.punto_emision,'') || '-' || COALESCE(vo.secuencial,''))
                        WHEN 'CAMBIO'       THEN (COALESCE(co.serie,'') || '-' || COALESCE(co.secuencial,''))
                        WHEN 'CONSIGNACION' THEN (COALESCE(cvo.serie,'') || '-' || COALESCE(cvo.secuencial,''))
                        ELSE NULL
                   END AS origen_numero
            FROM cambios_producto_cv_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            LEFT JOIN ventas_cabecera vo        ON d.origen_tipo = 'FACTURA'      AND vo.id  = d.id_origen
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
