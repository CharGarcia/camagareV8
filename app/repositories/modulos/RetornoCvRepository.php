<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de Retornos de Consignaciones en Ventas.
 *
 * Un retorno es la devolución (entrada de inventario) de mercadería entregada
 * previamente al cliente en una o varias consignaciones de venta.
 */
class RetornoCvRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('retornos_cv');
    }

    /**
     * Series REALMENTE usadas en retornos guardados, para el filtro "Serie" del
     * buscador — a diferencia de $puntos (solo sirve para elegir la serie de un
     * retorno NUEVO), esto incluye series de cualquier establecimiento y aunque
     * el punto ya no tenga secuencial configurado.
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM retornos_cv
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /** Responsables de traslado usados en retornos de la empresa: filtro del modal de filtros. */
    public function getResponsablesUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT rt.id, rt.nombre
                FROM retornos_cv r
                JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
                WHERE r.id_empresa = :id_empresa AND r.eliminado = false
                ORDER BY rt.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que registraron retornos en la empresa: filtro "Usuario que registró" del modal. */
    public function getUsuariosUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM retornos_cv r
                JOIN usuarios u ON u.id = r.created_by
                WHERE r.id_empresa = :id_empresa AND r.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de los retornos (pestaña "Detalles" del modal de filtros): cada
     * línea retornada (producto, lote, NUP, caducidad, bodega y consignación de origen) que
     * coincide con el texto, con el retorno al que pertenece. Mismo alcance que el listado:
     * empresa, no eliminados y registros propios (created_by) si $idUsuario viene informado.
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

        $condProd = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['p.codigo', 'p.nombre', 'p.codigo_barras', 'd.lote', 'd.nup', "TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')",
             'bo.nombre', "CONCAT(cv.serie, '-', cv.secuencial)", 'd.cantidad::text', 'd.precio_unitario::text', 'd.total::text'],
            $q, $params, 'pr'
        );
        if ($condProd === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT r.id, r.serie, r.secuencial, r.fecha_retorno, r.estado, c.nombre AS cliente_nombre
                    FROM retornos_cv r
                    INNER JOIN clientes c ON c.id = r.id_cliente
                    WHERE $whereBase
                )
                SELECT 'PRODUCTO' AS origen, p.codigo AS tipo, p.nombre AS descripcion,
                       NULLIF(CONCAT_WS(' / ', NULLIF(d.lote, ''), NULLIF(d.nup, ''), TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')), '') AS extra,
                       bo.nombre AS bodega,
                       CONCAT(cv.serie, '-', cv.secuencial) AS consignacion,
                       d.cantidad, d.total AS monto,
                       b.id, b.serie, b.secuencial, b.fecha_retorno, b.estado, b.cliente_nombre
                FROM retornos_cv_detalles d
                JOIN base b ON b.id = d.id_retorno
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas bo ON bo.id = d.id_bodega
                LEFT JOIN consignaciones_ventas cv ON cv.id = d.id_consignacion
                WHERE d.eliminado = false AND $condProd
                ORDER BY b.fecha_retorno DESC, b.id DESC, d.id
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
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
            // Texto libre (buscador FiltrosModal de la vista), por palabras y sin tildes: las
            // columnas del listado y lo que identifica al retorno aunque no sea columna.
            // Decisión del usuario: la columna Estado NO entra; se filtra desde el modal.
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    "TO_CHAR(r.fecha_retorno, 'DD-MM-YYYY')",             // Fecha (como se muestra)
                    'r.fecha_retorno::text',
                    "CONCAT(r.serie, '-', r.secuencial)",                 // Secuencial (serie-secuencial)
                    'c.nombre',                                           // Cliente
                    'c.identificacion',
                    'r.motivo',                                           // Motivo
                    'r.observaciones',
                    'rt.nombre',                                          // Responsable de traslado
                    'r.punto_partida',
                    'r.punto_llegada',
                    'r.total::text',
                    'u.nombre',                                           // Usuario que registró
                    // Productos retornados (código, nombre, lote y NUP)
                    "(SELECT STRING_AGG(CONCAT_WS(' ', p.codigo, p.nombre, d.lote, d.nup), ' ')
                        FROM retornos_cv_detalles d
                        LEFT JOIN productos p ON p.id = d.id_producto
                       WHERE d.id_retorno = r.id AND d.eliminado = false)",
                    // Documentos relacionados: consignaciones de origen
                    "(SELECT STRING_AGG(DISTINCT CONCAT(cv.serie, '-', cv.secuencial), ' ')
                        FROM retornos_cv_detalles d
                        JOIN consignaciones_ventas cv ON cv.id = d.id_consignacion
                       WHERE d.id_retorno = r.id AND d.eliminado = false)",
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
                'fecha'      => 'r.fecha_retorno',
            ],
            'numerico' => [
                'total'      => 'r.total',
                'subtotal'   => 'r.subtotal',
                'impuesto'   => 'r.impuesto',
                'secuencial' => 'r.secuencial::numeric',
            ],
            'existe' => [
                // Nº de la consignación de origen de alguna línea
                'consignacion' => ['tipo' => 'texto', 'col' => "CONCAT(cvx.serie, '-', cvx.secuencial)",
                                   'sql'  => 'EXISTS (SELECT 1 FROM retornos_cv_detalles dx
                                                       JOIN consignaciones_ventas cvx ON cvx.id = dx.id_consignacion
                                                      WHERE dx.id_retorno = r.id AND dx.eliminado = false AND {cond})'],
            ],
        ]);

        // Mismos JOIN que la consulta principal: el texto libre y los filtros usan c, rt y u.
        $sqlCount = "
            SELECT COUNT(*)
            FROM retornos_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            LEFT JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
            LEFT JOIN usuarios u ON u.id = r.created_by
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
            'fecha_retorno' => 'r.fecha_retorno',
            'secuencial'    => 'r.secuencial',
            'cliente'       => 'c.nombre',
            'estado'        => 'r.estado',
            'total'         => 'r.total',
        ];
        $sort = $colMap[$ordenCol] ?? 'r.fecha_retorno';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT r.*,
                   c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                   rt.nombre as responsable_traslado_nombre
            FROM retornos_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            LEFT JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
            LEFT JOIN usuarios u ON u.id = r.created_by
            $where
            ORDER BY $sort $dir, r.id DESC
            $limitClause
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    // ─── LÍNEAS DE CONSIGNACIÓN PENDIENTES DE RETORNAR ────────────────────────

    /**
     * Devuelve todas las líneas de consignaciones del cliente con saldo pendiente
     * de retornar (> 0), con los datos "tal cual" de la consignación de origen.
     *
     * saldo_pendiente = cantidad_consignada - Σ(retornos activos de esa línea)
     */
    public function getLineasPendientesPorCliente(int $idEmpresa, int $idCliente, ?int $excluirRetorno = null): array
    {
        return $this->getLineasPendientes($idEmpresa, $idCliente, null, $excluirRetorno);
    }

    /**
     * Líneas con saldo pendiente de retornar de UNA consignación concreta. Es lo que
     * alimenta la grilla del modal: el usuario agrega la consignación por su número y
     * solo ve los ítems de esa consignación.
     */
    public function getLineasPendientesPorConsignacion(int $idEmpresa, int $idConsignacion, ?int $excluirRetorno = null): array
    {
        return $this->getLineasPendientes($idEmpresa, null, $idConsignacion, $excluirRetorno);
    }

    /**
     * Motor común de las dos consultas anteriores: acota por cliente o por consignación
     * (exactamente uno de los dos) para no duplicar el cálculo del saldo pendiente.
     */
    private function getLineasPendientes(int $idEmpresa, ?int $idCliente, ?int $idConsignacion, ?int $excluirRetorno): array
    {
        $params = [':e' => $idEmpresa];

        if ($idConsignacion !== null) {
            $filtroAlcance = ' AND cv.id = :idc';
            $params[':idc'] = $idConsignacion;
        } else {
            $filtroAlcance = ' AND cv.id_cliente = :cli';
            $params[':cli'] = (int) $idCliente;
        }

        // Al editar un retorno, sus propias líneas no deben restar del saldo disponible.
        $excludeSql = '';
        if ($excluirRetorno !== null) {
            $excludeSql = ' AND rc.id <> :exc';
            $params[':exc'] = $excluirRetorno;
        }

        $sql = "
            SELECT * FROM (
                SELECT
                    cvd.id                AS id_consignacion_detalle,
                    cvd.id_consignacion   AS id_consignacion,
                    cvd.id_producto,
                    cvd.cantidad          AS cantidad_consignada,
                    cvd.precio_unitario,
                    cvd.subtotal,
                    cvd.id_impuesto,
                    cvd.porcentaje_impuesto,
                    cvd.valor_impuesto,
                    cvd.total,
                    cvd.id_bodega,
                    cvd.lote,
                    cvd.nup,
                    cvd.fecha_caducidad,
                    cv.serie,
                    cv.secuencial,
                    cv.fecha_emision,
                    cv.establecimiento,
                    cv.punto_emision,
                    p.nombre  AS producto_nombre,
                    p.codigo  AS producto_codigo,
                    p.inventariable,
                    p.tipo_produccion,
                    b.nombre  AS bodega_nombre,
                    COALESCE((
                        SELECT SUM(rcd.cantidad)
                        FROM retornos_cv_detalles rcd
                        INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                        WHERE rcd.id_consignacion_detalle = cvd.id
                          AND rcd.eliminado = false
                          AND rc.eliminado = false
                          AND rc.estado = 'Emitida'
                          $excludeSql
                    ), 0) AS cantidad_retornada,
                    COALESCE((
                        SELECT SUM(cfd.cantidad)
                        FROM consignaciones_facturas_detalles cfd
                        INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                        WHERE cfd.id_consignacion_detalle = cvd.id
                          AND cfd.eliminado = false
                          AND cf.eliminado = false
                          AND cf.estado = 'facturada'
                    ), 0) AS cantidad_facturada,
                    COALESCE((" . CambioProductoCvRepository::sqlEntregadoEnCambios('cvd.id') . "), 0) AS cantidad_cambiada
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                LEFT JOIN bodegas b ON b.id = cvd.id_bodega
                WHERE cv.id_empresa = :e
                  $filtroAlcance
                  AND cv.eliminado = false
                  AND cv.estado = 'Entregada'
                  AND cvd.eliminado = false
            ) t
            WHERE (t.cantidad_consignada - t.cantidad_retornada - t.cantidad_facturada - t.cantidad_cambiada) > 0
            ORDER BY t.fecha_emision DESC, t.id_consignacion DESC, t.id_consignacion_detalle ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // saldo = consignado − retornado − facturado − entregado a cambio (Cambios de productos Emitida)
        foreach ($rows as &$r) {
            $r['saldo_pendiente'] = (float) $r['cantidad_consignada'] - (float) $r['cantidad_retornada']
                - (float) $r['cantidad_facturada'] - (float) $r['cantidad_cambiada'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Busca consignaciones que tengan al menos una línea con saldo pendiente de retornar,
     * coincidiendo por nombre/identificación del cliente o por número de consignación (serie-secuencial).
     * Devuelve una fila por consignación con el cliente y el número.
     */
    public function buscarConsignacionesPendientes(int $idEmpresa, string $q): array
    {
        $params = [':e' => $idEmpresa, ':q' => '%' . $q . '%'];

        // Número "desnudo": del texto tecleado se toma el último segmento (para que
        // "001-001-000000012" también sirva), se dejan solo dígitos y se quitan los ceros
        // de relleno. Así "12", "000000012" y "001-001-000000012" encuentran el mismo
        // documento, sin importar si el secuencial quedó guardado con ceros o sin ellos.
        $partes = preg_split('/[-\s]+/', trim($q)) ?: [];
        $qnum   = ltrim(preg_replace('/\D/', '', (string) end($partes)), '0');
        $porNumero = '';
        if ($qnum !== '') {
            $porNumero = " OR regexp_replace(TRIM(cv.secuencial), '^0+', '') = :qnum";
            $params[':qnum'] = $qnum;
        }

        $sql = "
            SELECT cv.id AS id_consignacion, cv.serie, cv.secuencial, cv.fecha_emision,
                   cv.id_cliente, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                   c.email AS cliente_email
            FROM consignaciones_ventas cv
            INNER JOIN clientes c ON c.id = cv.id_cliente
            WHERE cv.id_empresa = :e AND cv.eliminado = false
              AND cv.estado = 'Entregada'
              AND (
                    c.nombre ILIKE :q OR c.identificacion ILIKE :q
                    OR cv.secuencial ILIKE :q
                    OR (cv.serie || '-' || cv.secuencial) ILIKE :q
                    {$porNumero}
              )
              AND EXISTS (
                    SELECT 1
                    FROM consignaciones_ventas_detalles cvd
                    WHERE cvd.id_consignacion = cv.id AND cvd.eliminado = false
                      AND (cvd.cantidad - COALESCE((
                            SELECT SUM(rcd.cantidad)
                            FROM retornos_cv_detalles rcd
                            INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                            WHERE rcd.id_consignacion_detalle = cvd.id
                              AND rcd.eliminado = false AND rc.eliminado = false AND rc.estado = 'Emitida'
                      ), 0) - COALESCE((
                            SELECT SUM(cfd.cantidad)
                            FROM consignaciones_facturas_detalles cfd
                            INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                            WHERE cfd.id_consignacion_detalle = cvd.id
                              AND cfd.eliminado = false AND cf.eliminado = false AND cf.estado = 'facturada'
                      ), 0) - COALESCE((" . CambioProductoCvRepository::sqlEntregadoEnCambios('cvd.id') . "), 0)) > 0
              )
            ORDER BY cv.fecha_emision DESC, cv.id DESC
            LIMIT 15
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saldo pendiente de retornar de UNA línea de consignación (para validación en el Service).
     */
    public function getSaldoLineaConsignacion(int $idConsignacionDetalle, int $idEmpresa, ?int $excluirRetorno = null): float
    {
        // Cantidad original de la línea de consignación (validando empresa y no eliminada).
        $sqlCant = "SELECT cvd.cantidad
                    FROM consignaciones_ventas_detalles cvd
                    INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                    WHERE cvd.id = :id AND cvd.id_empresa = :e
                      AND cvd.eliminado = false AND cv.eliminado = false";
        $stCant = $this->db->prepare($sqlCant);
        $stCant->execute([':id' => $idConsignacionDetalle, ':e' => $idEmpresa]);
        $cantidad = $stCant->fetchColumn();
        if ($cantidad === false) {
            return 0.0;
        }

        $paramsRet = [':id' => $idConsignacionDetalle];
        $excludeSql = '';
        if ($excluirRetorno !== null) {
            $excludeSql = ' AND rc.id <> :exc';
            $paramsRet[':exc'] = $excluirRetorno;
        }

        $sqlRet = "SELECT COALESCE(SUM(rcd.cantidad), 0)
                   FROM retornos_cv_detalles rcd
                   INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                   WHERE rcd.id_consignacion_detalle = :id
                     AND rcd.eliminado = false
                     AND rc.eliminado = false
                     AND rc.estado = 'Emitida'
                     $excludeSql";
        $stRet = $this->db->prepare($sqlRet);
        $stRet->execute($paramsRet);
        $retornado = (float) $stRet->fetchColumn();

        $sqlFact = "SELECT COALESCE(SUM(cfd.cantidad), 0)
                   FROM consignaciones_facturas_detalles cfd
                   INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                   WHERE cfd.id_consignacion_detalle = :id
                     AND cfd.eliminado = false
                     AND cf.eliminado = false
                     AND cf.estado = 'facturada'";
        $stFact = $this->db->prepare($sqlFact);
        $stFact->execute([':id' => $idConsignacionDetalle]);
        $facturado = (float) $stFact->fetchColumn();

        // Entregado a cambio desde esta línea (Cambios de productos Emitida): ya está en
        // poder del cliente como suyo, no vuelve por retorno.
        $stCam = $this->db->prepare("SELECT COALESCE((" . CambioProductoCvRepository::sqlEntregadoEnCambios(':id') . "), 0)");
        $stCam->execute([':id' => $idConsignacionDetalle]);
        $cambiado = (float) $stCam->fetchColumn();

        return (float) $cantidad - $retornado - $facturado - $cambiado;
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * ¿Ya hay un retorno ACTIVO con este número en esta serie (punto + ambiente)?
     *
     * Compara el secuencial SIN los ceros de relleno: '1' y '000000001' son el mismo número
     * para el generador —que trabaja con `CAST(secuencial AS BIGINT)`— pero no para una
     * comparación de texto plano. Misma clave que usa `SecuencialRepository` para saber qué
     * números están ocupados (`id_punto_emision`), para que el pre-check nunca rechace un
     * número que el generador acaba de proponer.
     */
    public function existeSecuencial(int $idEmpresa, int $idPuntoEmision, string $secuencial, string $tipoAmbiente, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1
                  FROM retornos_cv
                 WHERE id_empresa = :e
                   AND id_punto_emision = :punto
                   AND COALESCE(tipo_ambiente, '1') = :amb
                   AND eliminado = false
                   AND regexp_replace(TRIM(secuencial), '^0+', '') = regexp_replace(TRIM(:sec), '^0+', '')";
        $params = [':e' => $idEmpresa, ':punto' => $idPuntoEmision, ':amb' => $tipoAmbiente, ':sec' => $secuencial];

        if ($excluirId !== null) {
            $sql .= " AND id <> :excluir";
            $params[':excluir'] = $excluirId;
        }

        $st = $this->db->prepare($sql . " LIMIT 1");
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = "INSERT INTO retornos_cv (" . implode(', ', $fields) . ")
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
        $sql = "INSERT INTO retornos_cv_detalles (
                    id_retorno, id_empresa, id_consignacion, id_consignacion_detalle, id_producto,
                    cantidad, precio_unitario, subtotal, id_impuesto, porcentaje_impuesto,
                    valor_impuesto, total, id_bodega, lote, nup, fecha_caducidad, eliminado
                ) VALUES (
                    :idr, :e, :idc, :idcd, :prod, :cant, :pu, :sub, :idi, :pi,
                    :vi, :tot, :idb, :lote, :nup, :fc, false
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':idr'  => $d['id_retorno'],
            ':e'    => $d['id_empresa'],
            ':idc'  => $d['id_consignacion'],
            ':idcd' => $d['id_consignacion_detalle'],
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
        ]);
        return (int) $st->fetchColumn();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT r.*,
                       c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                       c.direccion as cliente_direccion, c.email as cliente_email,
                       rt.nombre as responsable_traslado_nombre
                FROM retornos_cv r
                INNER JOIN clientes c ON c.id = r.id_cliente
                LEFT JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
                WHERE r.id = :id AND r.id_empresa = :e AND r.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idRetorno, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*,
                   p.nombre as producto_nombre, p.codigo as producto_codigo, p.inventariable, p.tipo_produccion,
                   b.nombre as bodega_nombre,
                   cv.serie as consignacion_serie, cv.secuencial as consignacion_secuencial
            FROM retornos_cv_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            LEFT JOIN consignaciones_ventas cv ON cv.id = d.id_consignacion
            WHERE d.id_retorno = :id AND d.id_empresa = :e AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idRetorno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cantidad retornada por cada línea de consignación: [id_consignacion_detalle => cantidad]. */
    public function getRetornadoPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT rcd.id_consignacion_detalle AS idd, COALESCE(SUM(rcd.cantidad), 0) AS cant
            FROM retornos_cv_detalles rcd
            INNER JOIN retornos_cv r ON r.id = rcd.id_retorno AND r.eliminado = false
            WHERE rcd.id_consignacion = :idc AND rcd.id_empresa = :e
              AND (rcd.eliminado = false OR rcd.eliminado IS NULL)
            GROUP BY rcd.id_consignacion_detalle
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int)$r['idd']] = (float)$r['cant'];
        }
        return $map;
    }

    /** Líneas de retorno asociadas a una consignación específica (para la pestaña Retornos). */
    public function getPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT d.id, d.cantidad, d.precio_unitario, d.subtotal, d.total,
                   d.lote, d.nup, d.fecha_caducidad,
                   p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                   b.nombre AS bodega_nombre,
                   r.id AS id_retorno, r.fecha_retorno, r.serie, r.secuencial,
                   r.estado, r.motivo
            FROM retornos_cv_detalles d
            INNER JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
            INNER JOIN productos p   ON p.id = d.id_producto
            LEFT JOIN bodegas b      ON b.id = d.id_bodega
            WHERE d.id_consignacion = :idc AND d.id_empresa = :e
              AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY r.fecha_retorno DESC, r.id DESC, d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vincula (o desvincula con null) el asiento contable generado para el retorno. */
    public function updateAsientoContable(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE retornos_cv SET id_asiento_contable = :a WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAsiento, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Marca lógicamente como eliminados los detalles de un retorno (para reemplazarlos al editar). */
    public function deleteDetalles(int $idRetorno, int $idEmpresa): void
    {
        $sql = "UPDATE retornos_cv_detalles SET eliminado = true
                WHERE id_retorno = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idRetorno, ':e' => $idEmpresa]);
    }

    /** Actualiza campos de la cabecera del retorno. */
    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE retornos_cv SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
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
        $sql = "UPDATE retornos_cv
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE retornos_cv
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $sqlDet = "UPDATE retornos_cv_detalles
                   SET eliminado = true
                   WHERE id_retorno = :id AND id_empresa = :e AND eliminado = false";
        $stDet = $this->db->prepare($sqlDet);
        $stDet->execute([':id' => $id, ':e' => $idEmpresa]);
    }
}
