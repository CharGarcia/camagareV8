<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ConsignacionVentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('consignaciones_ventas');
    }

    /** Series (establecimiento-punto_emision) usadas realmente en documentos existentes, para el filtro del listado. */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM consignaciones_ventas
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Asesores (vendedores) usados en consignaciones de la empresa: filtro "Asesor" del modal de filtros. */
    public function getVendedoresUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT v.id, v.nombre
                FROM consignaciones_ventas cv
                JOIN vendedores v ON v.id = cv.id_vendedor
                WHERE cv.id_empresa = :id_empresa AND cv.eliminado = false
                ORDER BY v.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Responsables de traslado usados en consignaciones de la empresa: filtro del modal de filtros. */
    public function getResponsablesUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT rt.id, rt.nombre
                FROM consignaciones_ventas cv
                JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                WHERE cv.id_empresa = :id_empresa AND cv.eliminado = false
                ORDER BY rt.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que registraron consignaciones en la empresa: filtro "Usuario que registró" del modal. */
    public function getUsuariosUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM consignaciones_ventas cv
                JOIN usuarios u ON u.id = cv.created_by
                WHERE cv.id_empresa = :id_empresa AND cv.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las consignaciones (pestaña "Detalles" del modal de filtros):
     * cada producto consignado y cada documento relacionado (factura de consignación,
     * retorno, cambio de producto) que coincide con el texto, junto con la consignación a
     * la que pertenece. Mismo alcance que el listado: empresa, no eliminadas y registros
     * propios (created_by) cuando $idUsuario viene informado.
     *
     * La CTE `base` trae la cabecera completa (cv.* + nombres) para que la vista pueda abrir
     * el modal de la consignación con los mismos datos que la fila del listado.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "cv.id_empresa = :id_empresa AND cv.eliminado = false";
        if ($idUsuario !== null) {
            $whereBase .= " AND cv.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Rendimiento (17-09-2026): cada rama filtra su tabla por empresa y la cabecera se une
        // solo a las líneas que coinciden (antes se materializaban TODAS las consignaciones y se
        // recorrían las líneas y documentos de todas las empresas). Producto y bodega se buscan
        // en su catálogo como conjunto; montos, cantidades y fechas solo si la palabra puede ser
        // un número o una fecha.
        $fecha   = \App\Helpers\FiltrosBusqueda::SI_FECHA;
        $numero  = \App\Helpers\FiltrosBusqueda::SI_NUMERO;
        $condProd = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.lote', 'd.nup',
             ['col' => "CONCAT_WS(' ', px.codigo, px.nombre, px.codigo_barras)",
              'sql' => "d.id_producto IN (SELECT px.id FROM productos px WHERE px.id_empresa = :id_empresa AND {cond})"],
             ['col' => 'bx.nombre',
              'sql' => "d.id_bodega IN (SELECT bx.id FROM bodegas bx WHERE bx.id_empresa = :id_empresa AND {cond})"],
             ['sql' => "TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')", 'si' => $fecha],
             ['sql' => 'd.cantidad', 'si' => $numero],
             ['sql' => 'd.precio_unitario', 'si' => $numero],
             ['sql' => 'd.total', 'si' => $numero]],
            $q, $params, 'pr'
        );
        $condFac = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ["CONCAT(cf.serie, '-', cf.secuencial)", 'cf.numero_factura', 'cf.observaciones', ['sql' => 'cf.total', 'si' => $numero]],
            $q, $params, 'fc'
        );
        $condRet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ["CONCAT(r.serie, '-', r.secuencial)", 'r.motivo', 'r.observaciones', ['sql' => 'r.total', 'si' => $numero]],
            $q, $params, 'rt'
        );
        $condCam = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ["CONCAT(cc.serie, '-', cc.secuencial)", 'cc.motivo', 'cc.observaciones'],
            $q, $params, 'cm'
        );
        if ($condProd === '' || $condFac === '' || $condRet === '' || $condCam === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH coincidencias AS (
                    SELECT 'PRODUCTO' AS origen, p.codigo AS tipo, p.nombre AS descripcion,
                           NULLIF(CONCAT_WS(' / ', NULLIF(d.lote, ''), NULLIF(d.nup, ''), TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')), '') AS extra,
                           d.cantidad, d.total AS monto, d.id_consignacion AS id_cons
                    FROM consignaciones_ventas_detalles d
                    LEFT JOIN productos p ON p.id = d.id_producto
                    WHERE d.id_empresa = :id_empresa AND d.eliminado = false AND $condProd
                    UNION ALL
                    SELECT DISTINCT 'FACTURA' AS origen, CONCAT(cf.serie, '-', cf.secuencial) AS tipo,
                           cf.numero_factura AS descripcion, NULL AS extra,
                           NULL::numeric AS cantidad, cf.total AS monto, cfd.id_consignacion AS id_cons
                    FROM consignaciones_facturas cf
                    JOIN consignaciones_facturas_detalles cfd ON cfd.id_consignacion_factura = cf.id
                    WHERE cf.id_empresa = :id_empresa AND cf.eliminado = false AND $condFac
                    UNION ALL
                    SELECT DISTINCT 'RETORNO' AS origen, CONCAT(r.serie, '-', r.secuencial) AS tipo,
                           NULLIF(r.motivo, '') AS descripcion, NULL AS extra,
                           NULL::numeric AS cantidad, r.total AS monto, rd.id_consignacion AS id_cons
                    FROM retornos_cv r
                    JOIN retornos_cv_detalles rd ON rd.id_retorno = r.id
                    WHERE r.id_empresa = :id_empresa AND r.eliminado = false AND $condRet
                    UNION ALL
                    SELECT DISTINCT 'CAMBIO' AS origen, CONCAT(cc.serie, '-', cc.secuencial) AS tipo,
                           NULLIF(cc.motivo, '') AS descripcion, NULL AS extra,
                           NULL::numeric AS cantidad, cc.diferencia AS monto, ccd.id_origen AS id_cons
                    FROM cambios_producto_cv cc
                    JOIN cambios_producto_cv_detalles ccd ON ccd.id_cambio = cc.id
                         AND ccd.origen_tipo = 'CONSIGNACION' AND ccd.eliminado = false
                    WHERE cc.id_empresa = :id_empresa AND cc.eliminado = false AND $condCam
                )
                SELECT x.origen, x.tipo, x.descripcion, x.extra, x.cantidad, x.monto,
                       cv.*,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       v.nombre AS vendedor_nombre,
                       rt.nombre AS responsable_traslado_nombre
                FROM coincidencias x
                JOIN consignaciones_ventas cv ON cv.id = x.id_cons
                INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN vendedores v ON v.id = cv.id_vendedor
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                WHERE $whereBase
                ORDER BY cv.fecha_emision DESC, cv.id DESC, x.origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Texto propio de la consignación que entra en la búsqueda libre: número (serie-secuencial),
     * observaciones, punto de partida, punto de llegada, la fecha en los dos formatos en que se
     * puede escribir (como se muestra y como la guarda PostgreSQL) y el total.
     * `$a` es el prefijo del alias ('cv.' en la consulta, '' en el CREATE INDEX): la MISMA
     * expresión alimenta la consulta y el índice, así que no se pueden desalinear.
     */
    private static function exprConsignacion(string $a = ''): string
    {
        $m = \App\Helpers\MotorBusqueda::class;
        return "COALESCE({$a}serie, '') || '-' || COALESCE({$a}secuencial, '')"
             . " || ' ' || COALESCE({$a}observaciones, '')"
             . " || ' ' || COALESCE({$a}punto_partida, '')"
             . " || ' ' || COALESCE({$a}punto_llegada, '')"
             . " || ' ' || " . $m::fechaDmy("{$a}fecha_emision")
             . " || ' ' || " . $m::fechaIso("{$a}fecha_emision")
             . " || ' ' || COALESCE({$a}total::text, '')";
    }

    /**
     * Fuentes del texto libre del listado (ver App\Helpers\MotorBusqueda). Cada una es un
     * conjunto que PostgreSQL resuelve UNA vez por palabra con su índice trigram, en lugar de
     * recorrer la tabla entera: antes, buscar un cliente o un producto obligaba a quitarle las
     * tildes al nombre de los 26.000 clientes o de los 68.000 productos en cada búsqueda, y el
     * número, las observaciones y los puntos se comparaban consignación por consignación.
     *
     * Busca exactamente lo mismo que antes: número, observaciones, punto de partida y de
     * llegada, fecha, total, cliente (nombre e identificación), asesor, responsable de traslado,
     * usuario que registró, productos consignados (código y nombre), lote y NUP de las líneas, y
     * los números de las facturaciones, retornos y cambios de producto de esa consignación.
     */
    private function fuentesBusqueda(): array
    {
        $digitos = \App\Helpers\FiltrosBusqueda::SI_DIGITOS;

        return [
            // Datos propios de la consignación
            [
                'sql'    => "cv.id IN (SELECT bx.id FROM consignaciones_ventas bx WHERE bx.id_empresa = :e AND {cond})",
                'expr'   => self::exprConsignacion('bx.'),
                'indice' => ['tabla' => 'consignaciones_ventas', 'nombre' => 'idx_trgm_consignaciones_ventas', 'expr' => self::exprConsignacion()],
            ],
            // Total escrito con coma decimal ("34,78"): el texto indexado lo guarda con punto,
            // así que esa forma se compara aparte, y solo cuando la palabra es un monto así.
            ['expr' => 'cv.total', 'crudo' => true, 'si' => '/^\d+,\d{1,2}$/'],
            // Cliente: nombre e identificación (mismo índice que usan los demás módulos)
            [
                'sql'    => "cv.id_cliente IN (SELECT cx.id FROM clientes cx WHERE cx.id_empresa = :e AND {cond})",
                'expr'   => "COALESCE(cx.nombre, '') || ' ' || COALESCE(cx.identificacion, '')",
                'indice' => ['tabla' => 'clientes', 'nombre' => 'idx_trgm_clientes', 'expr' => "COALESCE(nombre, '') || ' ' || COALESCE(identificacion, '')"],
            ],
            // Asesor, responsable de traslado y usuario que registró: tablas chicas, sin índice.
            ['sql' => "cv.id_vendedor IN (SELECT vx.id FROM vendedores vx WHERE vx.id_empresa = :e AND {cond})", 'expr' => "COALESCE(vx.nombre, '')"],
            ['sql' => "cv.id_responsable_traslado IN (SELECT rx.id FROM responsables_traslado rx WHERE rx.id_empresa = :e AND {cond})", 'expr' => "COALESCE(rx.nombre, '')"],
            ['sql' => "cv.created_by IN (SELECT ux.id FROM usuarios ux WHERE {cond})", 'expr' => "COALESCE(ux.nombre, '')"],
            // Productos consignados: código y nombre del catálogo
            [
                'sql'    => "cv.id IN (SELECT d.id_consignacion
                                         FROM consignaciones_ventas_detalles d
                                        WHERE d.id_empresa = :e AND d.eliminado = false
                                          AND d.id_producto IN (SELECT px.id FROM productos px WHERE px.id_empresa = :e AND {cond}))",
                'expr'   => "COALESCE(px.codigo, '') || ' ' || COALESCE(px.nombre, '')",
                'indice' => ['tabla' => 'productos', 'nombre' => 'idx_trgm_productos', 'expr' => "COALESCE(codigo, '') || ' ' || COALESCE(nombre, '')"],
            ],
            // Lote y NUP de las líneas
            [
                'sql'    => "cv.id IN (SELECT d.id_consignacion
                                         FROM consignaciones_ventas_detalles d
                                        WHERE d.id_empresa = :e AND d.eliminado = false AND {cond})",
                'expr'   => "COALESCE(d.lote, '') || ' ' || COALESCE(d.nup, '')",
                'indice' => ['tabla' => 'consignaciones_ventas_detalles', 'nombre' => 'idx_trgm_cons_det_lote_nup', 'expr' => "COALESCE(lote, '') || ' ' || COALESCE(nup, '')"],
            ],
            // Facturaciones de la consignación: nº interno y nº de la factura de venta
            [
                'sql'    => "cv.id IN (SELECT cfd.id_consignacion
                                         FROM consignaciones_facturas cf
                                         JOIN consignaciones_facturas_detalles cfd ON cfd.id_consignacion_factura = cf.id
                                        WHERE cf.id_empresa = :e AND cf.eliminado = false AND {cond})",
                'expr'   => "COALESCE(cf.serie, '') || '-' || COALESCE(cf.secuencial, '') || ' ' || COALESCE(cf.numero_factura, '')",
                'indice' => ['tabla' => 'consignaciones_facturas', 'nombre' => 'idx_trgm_consignaciones_facturas', 'expr' => "COALESCE(serie, '') || '-' || COALESCE(secuencial, '') || ' ' || COALESCE(numero_factura, '')"],
            ],
            // Retornos de esta consignación
            [
                'sql'    => "cv.id IN (SELECT rd.id_consignacion
                                         FROM retornos_cv r
                                         JOIN retornos_cv_detalles rd ON rd.id_retorno = r.id
                                        WHERE r.id_empresa = :e AND r.eliminado = false AND {cond})",
                'expr'   => "COALESCE(r.serie, '') || '-' || COALESCE(r.secuencial, '')",
                'si'     => $digitos,
                'indice' => ['tabla' => 'retornos_cv', 'nombre' => 'idx_trgm_retornos_numero', 'expr' => "COALESCE(serie, '') || '-' || COALESCE(secuencial, '')"],
            ],
            // Cambios de producto que entregan desde esta consignación
            [
                'sql'    => "cv.id IN (SELECT ccd.id_origen
                                         FROM cambios_producto_cv cc
                                         JOIN cambios_producto_cv_detalles ccd ON ccd.id_cambio = cc.id
                                              AND ccd.origen_tipo = 'CONSIGNACION' AND ccd.eliminado = false
                                        WHERE cc.id_empresa = :e AND cc.eliminado = false
                                          AND ccd.id_origen IS NOT NULL AND {cond})",
                'expr'   => "COALESCE(cc.serie, '') || '-' || COALESCE(cc.secuencial, '')",
                'si'     => $digitos,
                'indice' => ['tabla' => 'cambios_producto_cv', 'nombre' => 'idx_trgm_cambios_numero', 'expr' => "COALESCE(serie, '') || '-' || COALESCE(secuencial, '')"],
            ],
        ];
    }

    /** SQL de los índices que necesita la búsqueda de este módulo (para database/*.sql). */
    public function sqlIndicesBusqueda(): array
    {
        return \App\Helpers\MotorBusqueda::sqlIndices($this->fuentesBusqueda());
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $params = [':e' => $idEmpresa];
        $where = "WHERE cv.id_empresa = :e AND cv.eliminado = false";

        if ($idUsuarioFiltro !== null) {
            $where .= " AND cv.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            // Texto libre (buscador FiltrosModal de la vista): las columnas del listado y
            // lo que identifica a la consignación aunque no sea columna. Decisión del
            // usuario: la columna Estado NO entra en el texto libre; se filtra desde el
            // modal de filtros. Ver fuentesBusqueda().
            $condicion = \App\Helpers\MotorBusqueda::condicion(
                $this->fuentesBusqueda(),
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        // Claves del modal de filtros (FiltrosModal en la vista). Las claves viejas se
        // conservan aunque la UI ya no las muestre: viajan en los enlaces de PDF/Excel.
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                // Nº completo "serie-secuencial" (contiene al secuencial, así que las
                // búsquedas viejas por secuencial siguen encontrando lo mismo).
                'numero'         => "CONCAT(cv.serie, '-', cv.secuencial)",
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'vendedor'       => 'v.nombre',
                'asesor'         => 'v.nombre',
                'responsable'    => 'rt.nombre',
                'obs'            => 'cv.observaciones',
                'observacion'    => 'cv.observaciones',
                'observaciones'  => 'cv.observaciones',
                'punto_llegada'  => 'cv.punto_llegada',
            ],
            'exacto' => [
                'estado'         => 'cv.estado',
                'serie'          => "CONCAT(cv.establecimiento,'-',cv.punto_emision)",
                'id_vendedor'    => 'cv.id_vendedor',
                'id_responsable' => 'cv.id_responsable_traslado',
                'id_usuario'     => 'cv.created_by',
                // asiento:si / asiento:no
                'asiento'        => "CASE WHEN cv.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
                // facturada:si / facturada:no — tiene alguna factura de consignación no anulada
                'facturada'      => "CASE WHEN EXISTS (SELECT 1 FROM consignaciones_facturas_detalles cfd
                                                        JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                                                       WHERE cfd.id_consignacion = cv.id AND cf.eliminado = false
                                                         AND LOWER(cf.estado) <> 'anulada')
                                          THEN 'si' ELSE 'no' END",
            ],
            'fecha' => [
                'fecha'         => 'cv.fecha_emision',
                'fecha_emision' => 'cv.fecha_emision',
                'entrega'       => 'cv.fecha_entrega',
                'fecha_entrega' => 'cv.fecha_entrega',
            ],
            'numerico' => [
                'total'      => 'cv.total',
                'monto'      => 'cv.total',
                'subtotal'   => 'cv.subtotal',
                'impuesto'   => 'cv.impuesto',
                'secuencial' => 'cv.secuencial::numeric',
            ],
        ]);

        $colMap = [
            'fecha_emision' => 'cv.fecha_emision',
            'secuencial' => 'cv.secuencial',
            'cliente' => 'c.nombre',
            'vendedor' => 'v.nombre',
            'estado' => 'cv.estado',
            'total' => 'cv.total'
        ];
        $sort = $colMap[$ordenCol] ?? 'cv.fecha_emision';
        $dir = $ordenDir === 'DESC' ? 'DESC' : 'ASC';

        // Conteo + página en UNA consulta (App\Helpers\ListadoPaginado): el WHERE con texto
        // libre se evaluaba dos veces (COUNT y SELECT). Los JOIN del filtro son los que usan
        // el texto libre, los filtros y el orden (c, v, rt, u).
        $joinsFinal = "INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN vendedores v ON v.id = cv.id_vendedor
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado";

        return \App\Helpers\ListadoPaginado::consultar(
            function (string $sql, array $p): array {
                $st = $this->db->prepare($sql);
                $st->execute($p);
                return $st->fetchAll(PDO::FETCH_ASSOC);
            },
            [
                'tabla'       => 'consignaciones_ventas',
                'alias'       => 'cv',
                'joinsFiltro' => $joinsFinal . "
                LEFT JOIN usuarios u ON u.id = cv.created_by",
                'joinsFinal'  => $joinsFinal,
                'where'       => $where,
                'orderBy'     => "ORDER BY $sort $dir, cv.id DESC",
                'perPage'     => $perPage,
                'offset'      => $perPage > 0 ? ($page - 1) * $perPage : 0,
                'conBusqueda' => trim($buscar) !== '',
                'select'      => "cv.*,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       v.nombre AS vendedor_nombre,
                       rt.nombre AS responsable_traslado_nombre",
            ],
            $params
        );
    }

    /**
     * Consignaciones en estado 'Emitida' (pendientes de entrega) para el módulo Entregas
     * de la app móvil. $idsResponsables: si viene no-null, filtra a esos responsables de
     * traslado (repartidor sin "acceso total"); null = ve todas (acceso total).
     */
    public function getPendientesEntrega(int $idEmpresa, ?array $idsResponsables, string $buscar, int $page, int $perPage): array
    {
        $params = [':e' => $idEmpresa];
        $where = "WHERE cv.id_empresa = :e AND cv.eliminado = false AND cv.estado = 'Emitida'";

        if ($idsResponsables !== null) {
            if (empty($idsResponsables)) {
                return ['total' => 0, 'rows' => []];
            }
            $marcadores = [];
            foreach (array_values($idsResponsables) as $i => $idResp) {
                $clave = ":r{$i}";
                $marcadores[] = $clave;
                $params[$clave] = $idResp;
            }
            $where .= " AND cv.id_responsable_traslado IN (" . implode(',', $marcadores) . ")";
        }

        if ($buscar !== '') {
            $where .= " AND (cv.secuencial ILIKE :b OR c.nombre ILIKE :b OR c.identificacion ILIKE :b)";
            $params[':b'] = "%$buscar%";
        }

        $sqlCount = "SELECT COUNT(*) FROM consignaciones_ventas cv
                     INNER JOIN clientes c ON c.id = cv.id_cliente
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $sql = "SELECT cv.id, cv.serie, cv.secuencial, cv.fecha_emision, cv.fecha_entrega,
                       cv.hora_entrega_desde, cv.hora_entrega_hasta, cv.punto_partida, cv.punto_llegada,
                       cv.total, cv.estado,
                       c.nombre AS cliente_nombre, c.direccion AS cliente_direccion, c.identificacion AS cliente_identificacion,
                       rt.nombre AS responsable_traslado_nombre
                FROM consignaciones_ventas cv
                INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                $where
                ORDER BY cv.fecha_entrega ASC NULLS LAST, cv.id DESC
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * ¿Esta consignación entra en el alcance de responsables del usuario?
     *
     * Mismo criterio que getPendientesEntrega(): $idsResponsables null = acceso total
     * (t) y ve todas; array = solo las de esos responsables de traslado (array vacío =
     * ninguna); una consignación sin responsable asignado tampoco entra. Lo usa la API
     * móvil en las acciones que reciben un id suelto, donde el filtro del listado no
     * protege nada.
     */
    public function perteneceAResponsables(int $id, int $idEmpresa, ?array $idsResponsables): bool
    {
        if ($idsResponsables === null) {
            return true;
        }
        if (empty($idsResponsables)) {
            return false;
        }

        $params = [':id' => $id, ':e' => $idEmpresa];
        $marcadores = [];
        foreach (array_values($idsResponsables) as $i => $idResp) {
            $clave = ":r{$i}";
            $marcadores[] = $clave;
            $params[$clave] = (int) $idResp;
        }

        $sql = "SELECT 1
                  FROM consignaciones_ventas
                 WHERE id = :id AND id_empresa = :e AND eliminado = false
                   AND id_responsable_traslado IN (" . implode(',', $marcadores) . ")
                 LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function getDetalles(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*, p.nombre as producto_nombre, p.codigo as producto_codigo, p.tipo_produccion, p.inventariable, p.precio_base as precio_base,
                   b.nombre as bodega_nombre
            FROM consignaciones_ventas_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            WHERE d.id_consignacion = :id AND d.id_empresa = :e AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kardex de la consignación: un movimiento por fila. Se muestra AGRUPADO POR TIPO
     * (primero todas las líneas "Consignación Inicial", luego todos los "Retorno", luego
     * todas las "Facturación"; cronológico dentro de cada grupo). El saldo corriente por
     * PRODUCTO (columna "saldo") sí se calcula en orden cronológico real dentro de la
     * ventana (fecha, orden, orden_id) — el agrupado es solo de presentación, no afecta
     * el cálculo del saldo.
     */
    public function getKardex(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT t.*,
                   SUM(t.mov) OVER (
                       PARTITION BY t.id_producto
                       ORDER BY t.fecha, t.orden, t.orden_id
                       ROWS UNBOUNDED PRECEDING
                   ) AS saldo
            FROM (
                -- 1. Consignación inicial (una fila por línea)
                SELECT cvd.id AS id_consignacion_detalle, cv.fecha_emision AS fecha, 1 AS orden, cvd.id AS orden_id,
                       'Consignación Inicial' AS tipo,
                       (cv.serie || '-' || cv.secuencial) AS documento, NULL AS estado_doc,
                       cvd.id_producto, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       cvd.lote, cvd.nup,
                       cvd.cantidad AS entrada, 0 AS salida, cvd.cantidad AS mov
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                WHERE cvd.id_consignacion = :id1 AND cvd.id_empresa = :e1 AND (cvd.eliminado = false OR cvd.eliminado IS NULL)

                UNION ALL

                -- 2. Retornos activos (Emitida)
                SELECT rcd.id_consignacion_detalle, rc.fecha_retorno, 2, rcd.id,
                       'Retorno',
                       (rc.serie || '-' || rc.secuencial), rc.estado,
                       rcd.id_producto, p.nombre, p.codigo,
                       rcd.lote, rcd.nup,
                       0, rcd.cantidad, -rcd.cantidad
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno AND rc.eliminado = false AND rc.estado = 'Emitida'
                INNER JOIN productos p ON p.id = rcd.id_producto
                WHERE rcd.id_consignacion = :id2 AND rcd.id_empresa = :e2 AND rcd.eliminado = false

                UNION ALL

                -- 3. Facturaciones (facturada)
                SELECT cfd.id_consignacion_detalle, cf.fecha_emision, 3, cfd.id,
                       'Facturación',
                       COALESCE(cf.numero_factura, (cf.serie || '-' || cf.secuencial)), cf.estado,
                       cfd.id_producto, p.nombre, p.codigo,
                       cfd.lote, cfd.nup,
                       0, cfd.cantidad, -cfd.cantidad
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
                INNER JOIN productos p ON p.id = cfd.id_producto
                WHERE cfd.id_consignacion = :id3 AND cfd.id_empresa = :e3 AND (cfd.eliminado = false OR cfd.eliminado IS NULL)

                UNION ALL

                -- 4. Entregado a cambio (Cambios de productos Emitida): la unidad consignada pasó
                --    a ser del cliente como reposición de otra devuelta. Sale del saldo igual que
                --    una facturación, sin mover stock (ya había salido con la consignación). Si el
                --    cambio la registró en Facturación de consignaciones, ya salió en la rama 3.
                SELECT cd.id_origen_detalle, cc.fecha_cambio, 4, cd.id,
                       'Cambio de producto',
                       (cc.serie || '-' || cc.secuencial), cc.estado,
                       cd.id_producto, p.nombre, p.codigo,
                       cd.lote, cd.nup,
                       0, cd.cantidad, -cd.cantidad
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio AND cc.eliminado = false AND cc.estado = 'Emitida'
                INNER JOIN productos p ON p.id = cd.id_producto
                WHERE cd.tipo_linea = 'entrega' AND cd.origen_tipo = 'CONSIGNACION'
                  AND cd.id_origen = :id4 AND cd.id_empresa = :e4 AND cd.eliminado = false
                  " . CambioProductoCvRepository::sqlSinRegistroFacturacion('cd') . "
            ) t
            ORDER BY t.orden ASC, t.fecha ASC, t.orden_id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id1' => $idConsignacion, ':e1' => $idEmpresa,
            ':id2' => $idConsignacion, ':e2' => $idEmpresa,
            ':id3' => $idConsignacion, ':e3' => $idEmpresa,
            ':id4' => $idConsignacion, ':e4' => $idEmpresa,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿La consignación proviene de una migración? (tiene fila en migracion_mysql_map). */
    public function esMigrado(int $id, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM migracion_mysql_map
              WHERE entidad = 'consignaciones' AND id_destino = :id AND id_empresa = :e
              LIMIT 1"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /**
     * ¿Ya hay una consignación ACTIVA con este número en esta serie (punto + ambiente)?
     *
     * Compara el secuencial SIN los ceros de relleno (`regexp_replace(...,'^0+','')`) porque
     * '1' y '000000001' son el mismo número para el generador — que trabaja con
     * `CAST(secuencial AS BIGINT)` — pero no para una comparación de texto plano. Los migrados
     * del sistema viejo no siempre llegaron con el relleno de 9 dígitos, así que comparar por
     * texto crudo dejaría pasar duplicados reales (pasó en ingresos/egresos).
     *
     * Se cruza por `id_punto_emision`, la misma clave que usa `SecuencialRepository` para saber
     * qué números están ocupados: así el pre-check nunca rechaza un número que el generador
     * acaba de proponer (con `serie` en texto rechazaría los de migrados sin punto, que el
     * generador no ve, y el módulo quedaría sin poder emitir).
     */
    public function existeSecuencial(int $idEmpresa, int $idPuntoEmision, string $secuencial, string $tipoAmbiente, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1
                  FROM consignaciones_ventas
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

        $sql = "INSERT INTO consignaciones_ventas (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";

        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->execute();
        
        // PostgreSQL RETURNING id
        return (int) $st->fetchColumn();
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_ventas
                   SET estado = :est, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':est' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function updateEntregaConfirmada(int $id, int $idEmpresa, ?int $idEntrega): void
    {
        $sql = "UPDATE consignaciones_ventas
                   SET id_entrega_confirmada = :en, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->bindValue(':en', $idEntrega, $idEntrega === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, \PDO::PARAM_INT);
        $st->execute();
    }

    public function updateAsientoContable(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE consignaciones_ventas
                   SET id_asiento_contable = :a, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->bindValue(':a', $idAsiento, $idAsiento === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, \PDO::PARAM_INT);
        $st->execute();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        // `creado_por_nombre`: el usuario que registró la consignación. Lo usa el PDF en la
        // firma "Emitido por"; el dato es del documento, no de la sesión que lo imprime.
        $sql = "SELECT cv.*,
                       c.nombre as cliente_nombre, c.identificacion as cliente_identificacion, c.direccion as cliente_direccion,
                       c.email as cliente_email,
                       v.nombre as vendedor_nombre,
                       rt.nombre as responsable_traslado_nombre,
                       ucre.nombre as creado_por_nombre
                FROM consignaciones_ventas cv
                INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN vendedores v ON v.id = cv.id_vendedor
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                LEFT JOIN usuarios ucre ON ucre.id = cv.created_by
                WHERE cv.id = :id AND cv.id_empresa = :e AND cv.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }

        $sql = "UPDATE consignaciones_ventas SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function deleteDetalles(int $idConsignacion, int $idEmpresa): void
    {
        $sql = "UPDATE consignaciones_ventas_detalles SET eliminado = true WHERE id_consignacion = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacion, ':e' => $idEmpresa]);
    }
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_ventas 
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u 
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $sqlDet = "UPDATE consignaciones_ventas_detalles
                   SET eliminado = true
                   WHERE id_consignacion = :id AND id_empresa = :e AND eliminado = false";
        $stDet = $this->db->prepare($sqlDet);
        $stDet->execute([':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Documentos vigentes que dependen de las líneas de la consignación: retornos (Emitida o
     * Borrador), facturaciones de consignación (borrador o facturada) y cambios de productos que
     * entregan desde ella (Emitida o Borrador). Mientras existan, la consignación no se puede
     * editar ni eliminar: editar recrea sus líneas (quedarían apuntando a líneas eliminadas) y
     * eliminar devolvería a bodega unidades que esos documentos ya movieron.
     *
     * @return array<int,array{tipo:string,numero:string,estado:string}>
     */
    public function getDocumentosRelacionadosActivos(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT 'Retorno' AS tipo, rc.serie || '-' || rc.secuencial AS numero, rc.estado
                  FROM retornos_cv_detalles rcd
                  JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                 WHERE rcd.id_consignacion = :c1 AND rcd.eliminado = false
                   AND rc.id_empresa = :e1 AND rc.eliminado = false AND UPPER(rc.estado) IN ('EMITIDA', 'BORRADOR')
                UNION
                SELECT DISTINCT 'Facturación', cf.serie || '-' || cf.secuencial, cf.estado
                  FROM consignaciones_facturas_detalles cfd
                  JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                 WHERE cfd.id_consignacion = :c2 AND cfd.eliminado = false
                   AND cf.id_empresa = :e2 AND cf.eliminado = false AND LOWER(cf.estado) IN ('borrador', 'facturada')
                UNION
                SELECT DISTINCT 'Cambio de productos', cc.serie || '-' || cc.secuencial, cc.estado
                  FROM cambios_producto_cv_detalles cd
                  JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                 WHERE cd.origen_tipo = 'CONSIGNACION' AND cd.eliminado = false
                   AND cd.id_origen_detalle IN (SELECT cvd.id FROM consignaciones_ventas_detalles cvd
                                                 WHERE cvd.id_consignacion = :c3 AND cvd.eliminado = false)
                   AND cc.id_empresa = :e3 AND cc.eliminado = false AND UPPER(cc.estado) IN ('EMITIDA', 'BORRADOR')
                ORDER BY 1, 2";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':c1' => $idConsignacion, ':e1' => $idEmpresa,
            ':c2' => $idConsignacion, ':e2' => $idEmpresa,
            ':c3' => $idConsignacion, ':e3' => $idEmpresa,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────
    // ESTADO DE LOS PEDIDOS QUE ALIMENTAN LA CONSIGNACIÓN
    // ────────────────────────────────────────────────────────────────

    /**
     * Líneas de pedido (`id_pedido_detalle`) enlazadas a las líneas vigentes de la consignación.
     *
     * @return int[]
     */
    public function getIdsPedidoDetalle(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT id_pedido_detalle
                  FROM consignaciones_ventas_detalles
                 WHERE id_consignacion = :id AND id_empresa = :e AND eliminado = false
                   AND id_pedido_detalle IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacion, ':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Pedidos de los que se cargaron líneas en la consignación (pestaña Pedidos del modal): una
     * fila por línea de cada pedido —todas, no solo las cargadas aquí, para ver qué quedó
     * pendiente— con lo tomado en ESTA consignación. Solo pedidos de la empresa y no eliminados;
     * una línea eliminada del pedido aparece únicamente si esta consignación la usó.
     */
    public function getPedidosRelacionados(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "WITH usadas AS (
                    SELECT d.id_pedido_detalle, SUM(d.cantidad) AS cantidad
                      FROM consignaciones_ventas_detalles d
                     WHERE d.id_consignacion = :id AND d.id_empresa = :e
                       AND d.eliminado = false AND d.id_pedido_detalle IS NOT NULL
                     GROUP BY d.id_pedido_detalle
                ),
                pedidos AS (
                    SELECT DISTINCT pd.id_pedido
                      FROM usadas u
                      JOIN pedidos_detalle pd ON pd.id = u.id_pedido_detalle
                )
                SELECT p.id AS id_pedido,
                       (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido,
                       p.fecha_pedido, p.fecha_entrega, p.hora_inicial_entrega, p.hora_maxima_entrega,
                       p.estado, p.observaciones,
                       c.nombre AS cliente_nombre, rt.nombre AS responsable_entrega,
                       pd.id AS id_detalle, pd.eliminado AS linea_eliminada,
                       pr.codigo AS producto_codigo, pr.nombre AS producto_nombre,
                       pd.cantidad AS cantidad_pedida, pd.precio_unitario, pd.total,
                       COALESCE(u.cantidad, 0) AS cantidad_consignacion
                  FROM pedidos x
                  JOIN pedidos_cabecera p ON p.id = x.id_pedido AND p.id_empresa = :e AND p.eliminado = false
                  LEFT JOIN clientes c ON c.id = p.id_cliente
                  LEFT JOIN responsables_traslado rt ON rt.id = p.id_responsable_entrega
                  JOIN pedidos_detalle pd ON pd.id_pedido = p.id
                  LEFT JOIN usadas u ON u.id_pedido_detalle = pd.id
                  LEFT JOIN productos pr ON pr.id = pd.id_producto
                 WHERE pd.eliminado = false OR u.id_pedido_detalle IS NOT NULL
                 ORDER BY p.fecha_pedido, p.id, pd.id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pedidos de la empresa dueños de esas líneas, en orden ascendente. Quedan fuera los
     * eliminados y los anulados: su estado no se recalcula por lo consignado.
     *
     * @param array $idsPedidoDetalle ids de pedidos_detalle (los vacíos se ignoran)
     * @return int[]
     */
    public function getPedidosDeLineas(array $idsPedidoDetalle, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsPedidoDetalle), static fn(int $v) => $v > 0)));
        if (empty($ids)) {
            return [];
        }

        $in  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT DISTINCT pd.id_pedido
                  FROM pedidos_detalle pd
                  JOIN pedidos_cabecera p ON p.id = pd.id_pedido
                 WHERE pd.id IN ($in)
                   AND p.id_empresa = ? AND p.eliminado = false
                   AND UPPER(COALESCE(p.estado, '')) <> 'ANULADO'
                 ORDER BY pd.id_pedido";
        $st = $this->db->prepare($sql);
        $st->execute([...$ids, $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Candado transaccional por pedido (CLAUDE.md §8) antes de recalcular su estado: sin él, dos
     * consignaciones que consumen el mismo pedido y se guardan a la vez calculan cada una sin ver
     * las líneas de la otra, y el pedido queda "Pendiente" aunque ya esté cubierto. Se toman en
     * orden ascendente para que dos guardados no queden esperándose mutuamente.
     *
     * @param int[] $idsPedido
     */
    public function lockEstadoPedidos(array $idsPedido, int $idEmpresa): void
    {
        $ids = array_values(array_unique(array_map('intval', $idsPedido)));
        sort($ids);
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('pedido_estado:' || ? || ':' || ?))");
        foreach ($ids as $idPedido) {
            $st->execute([$idEmpresa, $idPedido]);
        }
    }

    /**
     * Deja "Procesado" cada pedido cuyas líneas vigentes quedaron cubiertas por lo consignado en
     * consignaciones vigentes, y "Pendiente" a los demás, en una sola sentencia. Solo escribe los
     * pedidos que cambian de estado. Un pedido sin líneas vigentes queda "Procesado".
     *
     * @param int[] $idsPedido
     * @return int Pedidos que cambiaron de estado.
     */
    public function actualizarEstadoPedidosPorConsignado(array $idsPedido, int $idEmpresa, int $idUsuario): int
    {
        $ids = array_values(array_unique(array_map('intval', $idsPedido)));
        if (empty($ids)) {
            return 0;
        }

        // `consignado` va MATERIALIZED y con `= ANY (ARRAY(...))`: la suma se calcula una sola vez
        // y, con idx_cons_ventas_det_pedido_detalle, entra directo por las líneas de estos pedidos.
        // Como subconsulta enlazada el planificador la repetía por cada pedido o recorría el
        // índice completo.
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "WITH consignado AS MATERIALIZED (
                    SELECT cvd.id_pedido_detalle, SUM(cvd.cantidad) AS cantidad
                      FROM consignaciones_ventas_detalles cvd
                      JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
                     WHERE cvd.id_empresa = ? AND cvd.eliminado = false
                       AND cvd.id_pedido_detalle = ANY (ARRAY(SELECT pdx.id FROM pedidos_detalle pdx WHERE pdx.id_pedido IN ($in)))
                     GROUP BY cvd.id_pedido_detalle
                ), calc AS (
                    SELECT pc.id,
                           CASE WHEN COALESCE(BOOL_AND(COALESCE(c.cantidad, 0) >= pd.cantidad), true)
                                THEN 'Procesado' ELSE 'Pendiente' END AS nuevo_estado
                      FROM pedidos_cabecera pc
                      LEFT JOIN pedidos_detalle pd ON pd.id_pedido = pc.id AND pd.eliminado = false
                      LEFT JOIN consignado c ON c.id_pedido_detalle = pd.id
                     WHERE pc.id IN ($in)
                     GROUP BY pc.id
                )
                UPDATE pedidos_cabecera p
                   SET estado = calc.nuevo_estado, updated_at = CURRENT_TIMESTAMP, updated_by = ?
                  FROM calc
                 WHERE p.id = calc.id AND p.id_empresa = ?
                   AND p.estado IS DISTINCT FROM calc.nuevo_estado";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, ...$ids, ...$ids, $idUsuario, $idEmpresa]);
        return $st->rowCount();
    }
}
