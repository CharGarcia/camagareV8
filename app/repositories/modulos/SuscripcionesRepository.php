<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class SuscripcionesRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['nombre_cliente', 'nombre_periodicidad', 'tipo_comprobante', 'forma_cobro', 'proximo_cobro', 'fecha_inicio', 'fecha_fin', 'estado', 'created_at'];

    public function __construct()
    {
        parent::__construct('suscripciones');
    }

    // ── Listado principal ─────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'proximo_cobro';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = $this->getBaseWhere($idEmpresa, 's', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Expresiones calculadas (subconsultas: el COUNT solo une `clientes`).
        $exprItems = "(SELECT COUNT(*) FROM suscripciones_detalle sdi WHERE sdi.id_suscripcion = s.id AND sdi.eliminado = false)";
        // Monto de cada cobro: suma de los ítems con su IVA (igual que la ficha de empresa).
        $exprMonto = "ROUND(COALESCE((SELECT SUM(sdm.cantidad * sdm.precio_unitario * (1 + sdm.porcentaje_iva / 100))
                                      FROM suscripciones_detalle sdm
                                      WHERE sdm.id_suscripcion = s.id AND sdm.eliminado = false), 0), 2)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre (buscador FiltrosModal, sin sugerencias): columnas del listado +
            // campos que identifican la suscripción. Decisión del usuario: Estado,
            // Periodicidad, Comprobante y Cobro (tipos/clasificación) NO entran en el
            // texto libre; se filtran solo desde el modal de filtros.
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'c.nombre',                                                   // Cliente
                    'c.identificacion',                                           // RUC/Cédula
                    "TO_CHAR(s.proximo_cobro, 'DD-MM-YYYY')",                     // Próx. Cobro (como se muestra)
                    's.proximo_cobro::text',
                    "TO_CHAR(s.fecha_inicio, 'DD-MM-YYYY')",                      // Inicio
                    's.fecha_inicio::text',
                    "TO_CHAR(s.fecha_fin, 'DD-MM-YYYY')",                         // Fin
                    's.fecha_fin::text',
                    "CONCAT({$exprItems}, ' ítem')",                              // Ítems ("2 ítems")
                    "{$exprMonto}::text",                                         // Monto del cobro (con IVA)
                    's.observaciones',
                    's.info_adicional::text',
                    "CONCAT_WS(' ', s.kushki_card_last4, s.kushki_card_brand, s.kushki_card_name)",
                    '(SELECT CONCAT_WS(\' \', nt.ultimos4, nt.marca) FROM nuvei_tarjetas_cliente nt WHERE nt.id = s.id_nuvei_tarjeta)',
                    '(SELECT u.nombre FROM usuarios u WHERE u.id = s.created_by)', // Usuario que registró
                    // Productos/servicios de la suscripción (código, nombre y descripción)
                    "(SELECT STRING_AGG(CONCAT_WS(' ', p.codigo, p.nombre, sdt.descripcion), ' ')
                        FROM suscripciones_detalle sdt LEFT JOIN productos p ON p.id = sdt.id_producto
                       WHERE sdt.id_suscripcion = s.id AND sdt.eliminado = false)",
                    // Facturas / recibos generados por los cobros
                    "(SELECT STRING_AGG(COALESCE(NULLIF(rv.recibo_numero, ''), NULLIF(CONCAT_WS('-', rv.establecimiento, rv.punto_emision, rv.secuencial), ''), NULLIF(CONCAT_WS('-', vc.establecimiento, vc.punto_emision, vc.secuencial), '')), ' ')
                        FROM suscripciones_pagos sp
                        LEFT JOIN ventas_cabecera vc        ON vc.id = sp.id_factura
                        LEFT JOIN recibos_venta_cabecera rv ON rv.id = sp.id_recibo
                       WHERE sp.id_suscripcion = s.id AND sp.eliminado = false)",
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (vista suscripciones/index.php). Nunca quitar claves:
        // viajan también en los enlaces de PDF/Excel y en URLs guardadas.
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'observaciones'  => "CONCAT_WS(' ', s.observaciones, s.info_adicional::text)",
            ],
            'exacto'   => [
                'estado'       => 's.estado',
                'id'           => 's.id::text',   // texto: un valor no numérico no rompe la consulta
                'periodicidad' => 's.id_periodicidad',
                'comprobante'  => 's.tipo_comprobante',
                'forma_cobro'  => 's.forma_cobro',
                'pasarela'     => 's.pasarela_tarjeta',
                'usuario'      => 's.created_by',
                // con_pagos:si / con_pagos:no (ya tiene cobros registrados)
                'con_pagos'    => "CASE WHEN EXISTS (SELECT 1 FROM suscripciones_pagos spx WHERE spx.id_suscripcion = s.id AND spx.eliminado = false) THEN 'si' ELSE 'no' END",
            ],
            'fecha'    => [
                'proximo_cobro' => 's.proximo_cobro', 'fecha' => 's.proximo_cobro',
                'inicio'        => 's.fecha_inicio',
                'fin'           => 's.fecha_fin',
            ],
            'numerico' => [
                // `monto`/`total` apuntaban a s.monto, columna que no existe en `suscripciones`
                // (el filtro lanzaba error SQL): ahora es el monto calculado de los ítems.
                'monto'     => $exprMonto,
                'total'     => $exprMonto,
                'items'     => $exprItems,
                'intentos'  => 's.intentos_fallidos',
            ],
            'existe'   => [
                // N° de factura / recibo generado por algún cobro de la suscripción
                'documento' => ['tipo' => 'texto', 'col' => "COALESCE(NULLIF(rvd.recibo_numero, ''), NULLIF(CONCAT_WS('-', rvd.establecimiento, rvd.punto_emision, rvd.secuencial), ''), NULLIF(CONCAT_WS('-', vcd.establecimiento, vcd.punto_emision, vcd.secuencial), ''))",
                                'sql'  => 'EXISTS (SELECT 1 FROM suscripciones_pagos spd
                                                   LEFT JOIN ventas_cabecera vcd        ON vcd.id = spd.id_factura
                                                   LEFT JOIN recibos_venta_cabecera rvd ON rvd.id = spd.id_recibo
                                                   WHERE spd.id_suscripcion = s.id AND spd.eliminado = false AND {cond})'],
            ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM {$this->table} s
                     LEFT JOIN clientes c ON c.id = s.id_cliente
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $offset      = ($page - 1) * $perPage;
            $limitOffset = $perPage > 0 ? " LIMIT $perPage OFFSET $offset" : '';

            $orderExpr = match ($ordenCol) {
                'nombre_cliente'      => 'c.nombre',
                'nombre_periodicidad' => 'per.nombre',
                default               => "s.{$ordenCol}",
            };

            $sql = "SELECT s.*,
                           c.nombre         AS nombre_cliente,
                           c.identificacion AS identificacion_cliente,
                           c.email          AS email_cliente,
                           per.nombre       AS nombre_periodicidad,
                           per.meses        AS periodicidad_meses,
                           nt.ultimos4      AS nuvei_ultimos4,
                           nt.marca         AS nuvei_marca,
                           (SELECT COUNT(*) FROM suscripciones_pagos
                            WHERE id_suscripcion = s.id AND eliminado = false) AS total_pagos,
                           (SELECT COUNT(*) FROM suscripciones_detalle
                            WHERE id_suscripcion = s.id AND eliminado = false) AS total_items
                    FROM {$this->table} s
                    LEFT JOIN clientes c   ON c.id  = s.id_cliente
                    LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                    LEFT JOIN nuvei_tarjetas_cliente nt ON nt.id = s.id_nuvei_tarjeta
                    $where
                    ORDER BY $orderExpr $ordenDir, s.id DESC
                    $limitOffset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Valores realmente usados por las suscripciones de la empresa, para los selects
     * del modal de filtros: periodicidades y usuarios que registraron.
     */
    public function getOpcionesFiltro(int $idEmpresa): array
    {
        $q = function (string $sql) use ($idEmpresa): array {
            $st = $this->db->prepare($sql);
            $st->execute([':e' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };
        return [
            'periodicidades' => $q("SELECT DISTINCT per.id, per.nombre, per.meses FROM suscripciones s
                                    JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                                    WHERE s.id_empresa = :e AND s.eliminado = false ORDER BY per.meses, per.nombre"),
            'usuarios'       => $q("SELECT DISTINCT u.id, u.nombre FROM suscripciones s
                                    JOIN usuarios u ON u.id = s.created_by
                                    WHERE s.id_empresa = :e AND s.eliminado = false ORDER BY u.nombre"),
        ];
    }

    /**
     * Búsqueda libre DENTRO de las suscripciones (pestaña "Detalles" del modal de
     * filtros): productos/servicios facturados, cobros registrados (fecha, monto,
     * factura o recibo y n° de transacción) e información adicional. Mismo alcance que
     * el listado (empresa, no eliminadas, registros propios por created_by). No busca
     * en el estado del cobro.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "s.id_empresa = :id_empresa AND s.eliminado = false";
        if ($idUsuario !== null) {
            $whereBase .= " AND s.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $F = \App\Helpers\FiltrosBusqueda::class;
        $condDet = $F::condicionTexto(
            ['p.codigo', 'p.nombre', 'd.descripcion', 'd.cantidad::text', 'd.precio_unitario::text',
             'ROUND(d.cantidad * d.precio_unitario * (1 + d.porcentaje_iva / 100), 2)::text'],
            $q, $params, 'dt'
        );
        $condPag = $F::condicionTexto(
            ["COALESCE(NULLIF(rv.recibo_numero, ''), NULLIF(CONCAT_WS('-', rv.establecimiento, rv.punto_emision, rv.secuencial), ''), NULLIF(CONCAT_WS('-', vc.establecimiento, vc.punto_emision, vc.secuencial), ''))", "TO_CHAR(sp.fecha_cobro, 'DD-MM-YYYY')", 'sp.fecha_cobro::text',
             'sp.monto::text', 'sp.kushki_transaction_id', 'sp.nuvei_transaction_id'],
            $q, $params, 'pg'
        );
        $condInf = $F::condicionTexto(["ia.elem->>'concepto'", "ia.elem->>'detalle'"], $q, $params, 'ia');
        if ($condDet === '' || $condPag === '' || $condInf === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT s.id, s.proximo_cobro, s.estado, s.info_adicional, c.nombre AS cliente, c.identificacion
                    FROM suscripciones s
                    LEFT JOIN clientes c ON c.id = s.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'ITEM' AS origen, p.codigo AS referencia,
                           COALESCE(NULLIF(d.descripcion, ''), p.nombre) AS descripcion,
                           NULL::date AS fecha,
                           ROUND(d.cantidad * d.precio_unitario * (1 + d.porcentaje_iva / 100), 2) AS monto,
                           b.id AS id_suscripcion, b.proximo_cobro, b.estado, b.cliente, b.identificacion
                    FROM suscripciones_detalle d
                    JOIN base b ON b.id = d.id_suscripcion
                    LEFT JOIN productos p ON p.id = d.id_producto
                    WHERE d.eliminado = false AND $condDet
                    UNION ALL
                    SELECT 'COBRO' AS origen, COALESCE(NULLIF(rv.recibo_numero, ''), NULLIF(CONCAT_WS('-', rv.establecimiento, rv.punto_emision, rv.secuencial), ''), NULLIF(CONCAT_WS('-', vc.establecimiento, vc.punto_emision, vc.secuencial), '')) AS referencia,
                           NULLIF(CONCAT_WS(' ', CASE WHEN sp.id_recibo IS NOT NULL THEN 'Recibo' WHEN sp.id_factura IS NOT NULL THEN 'Factura' END,
                                                 sp.kushki_transaction_id, sp.nuvei_transaction_id), '') AS descripcion,
                           sp.fecha_cobro::date AS fecha, sp.monto,
                           b.id, b.proximo_cobro, b.estado, b.cliente, b.identificacion
                    FROM suscripciones_pagos sp
                    JOIN base b ON b.id = sp.id_suscripcion
                    LEFT JOIN ventas_cabecera vc        ON vc.id = sp.id_factura
                    LEFT JOIN recibos_venta_cabecera rv ON rv.id = sp.id_recibo
                    WHERE sp.eliminado = false AND $condPag
                    UNION ALL
                    SELECT 'INFO' AS origen, ia.elem->>'concepto' AS referencia, ia.elem->>'detalle' AS descripcion,
                           NULL::date, NULL::numeric,
                           b.id, b.proximo_cobro, b.estado, b.cliente, b.identificacion
                    FROM base b
                    CROSS JOIN LATERAL jsonb_array_elements(
                        CASE WHEN jsonb_typeof(b.info_adicional) = 'array' THEN b.info_adicional ELSE '[]'::jsonb END
                    ) AS ia(elem)
                    WHERE $condInf
                ) x
                ORDER BY x.proximo_cobro DESC NULLS LAST, x.id_suscripcion DESC, x.origen, x.fecha DESC NULLS LAST
                LIMIT $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen de suscripciones de una empresa controladora cuyo cliente coincide
     * por RUC/identificación. Alimenta la tarjeta "Suscripción y Vigencia" de la
     * ficha de empresa. Devuelve monto, periodicidad, próximo cobro, estado y el
     * último pago registrado (suscripciones_pagos).
     */
    /**
     * Igual que getResumenPorControladoraYRuc pero apuntando a un CLIENTE concreto
     * de la controladora (selección explícita de "empresa a la que facturamos").
     */
    public function getResumenPorControladoraYCliente(int $idControladora, int $idCliente): array
    {
        if ($idControladora <= 0 || $idCliente <= 0) {
            return [];
        }
        return $this->getResumenPorControladora($idControladora, 'c.id = :filtro', [':filtro' => $idCliente]);
    }

    /**
     * Resumen de UNA suscripción específica (reventa: el cliente facturado tiene
     * varias suscripciones y esta empresa está vinculada a una en concreto).
     */
    public function getResumenPorSuscripcion(int $idControladora, int $idSuscripcion): array
    {
        if ($idControladora <= 0 || $idSuscripcion <= 0) {
            return [];
        }
        return $this->getResumenPorControladora($idControladora, 's.id = :filtro', [':filtro' => $idSuscripcion]);
    }

    /** Suscripciones de un cliente, para elegir cuál corresponde a una empresa. */
    public function getListaPorCliente(int $idControladora, int $idCliente): array
    {
        if ($idControladora <= 0 || $idCliente <= 0) {
            return [];
        }
        // Se incluyen `info_adicional` (pares concepto/detalle, donde se suele
        // anotar el nombre del cliente final) y las descripciones del detalle,
        // para poder distinguir una suscripción de otra en el selector.
        $sql = "SELECT s.id, s.estado, s.fecha_inicio, s.fecha_fin, s.proximo_cobro,
                       s.observaciones, s.info_adicional,
                       per.nombre AS periodicidad,
                       COALESCE((SELECT SUM(d.cantidad * d.precio_unitario * (1 + d.porcentaje_iva / 100))
                                 FROM suscripciones_detalle d
                                 WHERE d.id_suscripcion = s.id AND d.eliminado = false), 0) AS monto,
                       (SELECT string_agg(d.descripcion, ' | ' ORDER BY d.orden, d.id)
                          FROM suscripciones_detalle d
                         WHERE d.id_suscripcion = s.id AND d.eliminado = false) AS items
                  FROM suscripciones s
                  LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                 WHERE s.id_empresa = :ctrl
                   AND s.id_cliente = :cli
                   AND s.eliminado = false
                 ORDER BY (s.estado = 'activo') DESC, s.proximo_cobro ASC, s.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ctrl' => $idControladora, ':cli' => $idCliente]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Aplana info_adicional a texto legible: "concepto: detalle · concepto: detalle"
        foreach ($rows as &$r) {
            $r['info_texto'] = $this->aplanarInfoAdicional($r['info_adicional'] ?? null);
        }
        unset($r);

        return $rows;
    }

    /**
     * Convierte el JSON de info_adicional ([{concepto, detalle}, ...]) en texto
     * legible: "concepto: detalle · concepto: detalle". Devuelve '' si no hay.
     */
    private function aplanarInfoAdicional($info): string
    {
        if ($info === null || $info === '') {
            return '';
        }
        $arr = is_array($info) ? $info : json_decode((string) $info, true);
        if (!is_array($arr)) {
            return '';
        }

        $partes = [];
        foreach ($arr as $item) {
            if (!is_array($item)) {
                continue;
            }
            $concepto = trim((string) ($item['concepto'] ?? ''));
            $detalle  = trim((string) ($item['detalle'] ?? ''));
            if ($concepto === '' && $detalle === '') {
                continue;
            }
            $partes[] = ($concepto !== '' && $detalle !== '')
                ? $concepto . ': ' . $detalle
                : ($concepto !== '' ? $concepto : $detalle);
        }

        return implode(' · ', $partes);
    }

    public function getResumenPorControladoraYRuc(int $idControladora, string $ruc): array
    {
        $ruc = preg_replace('/\D/', '', (string) $ruc);
        if ($idControladora <= 0 || $ruc === '') {
            return [];
        }
        return $this->getResumenPorControladora(
            $idControladora,
            "regexp_replace(c.identificacion, '[^0-9]', '', 'g') = :filtro",
            [':filtro' => $ruc]
        );
    }

    /**
     * Núcleo del resumen: aplica el filtro de cliente indicado (por id o por RUC)
     * y adjunta ítems y estado real de pago.
     */
    private function getResumenPorControladora(int $idControladora, string $filtroCliente, array $paramsFiltro): array
    {
        $sql = "SELECT s.id,
                       s.estado,
                       s.fecha_inicio,
                       s.fecha_fin,
                       s.proximo_cobro,
                       s.forma_cobro,
                       s.tipo_comprobante,
                       c.nombre         AS nombre_cliente,
                       c.identificacion AS identificacion_cliente,
                       per.nombre       AS periodicidad,
                       per.meses        AS periodicidad_meses,
                       COALESCE((SELECT SUM(d.cantidad * d.precio_unitario * (1 + d.porcentaje_iva / 100))
                                 FROM suscripciones_detalle d
                                 WHERE d.id_suscripcion = s.id AND d.eliminado = false), 0) AS monto,
                       (SELECT p.estado FROM suscripciones_pagos p
                         WHERE p.id_suscripcion = s.id AND p.eliminado = false
                         ORDER BY p.fecha_cobro DESC, p.id DESC LIMIT 1) AS ultimo_pago_estado,
                       (SELECT p.fecha_cobro FROM suscripciones_pagos p
                         WHERE p.id_suscripcion = s.id AND p.eliminado = false
                         ORDER BY p.fecha_cobro DESC, p.id DESC LIMIT 1) AS ultimo_pago_fecha,
                       (SELECT p.id_factura FROM suscripciones_pagos p
                         WHERE p.id_suscripcion = s.id AND p.eliminado = false
                         ORDER BY p.fecha_cobro DESC, p.id DESC LIMIT 1) AS ultimo_pago_id_factura
                FROM suscripciones s
                JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :ctrl
                  AND s.eliminado = false
                  AND c.eliminado = false
                  AND {$filtroCliente}
                ORDER BY (s.estado = 'activo') DESC, s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([':ctrl' => $idControladora], $paramsFiltro));
        $suscripciones = $st->fetchAll(PDO::FETCH_ASSOC);

        if (empty($suscripciones)) {
            return [];
        }

        // Ítems (detalle) de cada suscripción en una sola consulta.
        $ids = array_map(static fn($s) => (int) $s['id'], $suscripciones);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sqlItems = "SELECT d.id_suscripcion,
                            d.descripcion,
                            d.cantidad,
                            d.precio_unitario,
                            d.porcentaje_iva,
                            (d.cantidad * d.precio_unitario * (1 + d.porcentaje_iva / 100)) AS subtotal,
                            p.nombre AS nombre_producto
                     FROM suscripciones_detalle d
                     LEFT JOIN productos p ON p.id = d.id_producto
                     WHERE d.id_suscripcion IN ($ph) AND d.eliminado = false
                     ORDER BY d.id_suscripcion, d.orden, d.id";
        $stItems = $this->db->prepare($sqlItems);
        $stItems->execute($ids);

        $itemsPorSusc = [];
        foreach ($stItems->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $itemsPorSusc[(int) $it['id_suscripcion']][] = $it;
        }
        foreach ($suscripciones as &$s) {
            $s['items'] = $itemsPorSusc[(int) $s['id']] ?? [];
        }
        unset($s);

        // Estado REAL de pago de la factura del último período (aislado por id_factura,
        // no por cliente, para no confundir con otras facturas de la misma empresa).
        $idsFactura = array_values(array_unique(array_filter(array_map(
            static fn($s) => (int) ($s['ultimo_pago_id_factura'] ?? 0),
            $suscripciones
        ))));
        $estadoFacturas = $this->getEstadoFacturas($idsFactura, $idControladora);
        foreach ($suscripciones as &$s) {
            $idf = (int) ($s['ultimo_pago_id_factura'] ?? 0);
            $s['pago_real'] = ($idf > 0 && isset($estadoFacturas[$idf])) ? $estadoFacturas[$idf] : null;
        }
        unset($s);

        return $suscripciones;
    }

    /**
     * Estado de cobro de un conjunto de facturas (ventas_cabecera), aislado por id.
     * Saldo = importe_total − cobrado (ingresos activos) − retenido − notas de crédito.
     * Devuelve mapa: id_factura => ['total','cobrado','retenido','nota_credito','saldo','estado'].
     * estado ∈ pagado | parcial | pendiente.
     */
    public function getEstadoFacturas(array $idsFactura, int $idEmpresa): array
    {
        $idsFactura = array_values(array_filter(array_map('intval', $idsFactura)));
        if (empty($idsFactura)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($idsFactura), '?'));

        $sql = "SELECT v.id AS id_factura,
                       v.importe_total AS total,
                       COALESCE(cob.total_cobrado, 0)  AS cobrado,
                       COALESCE(ret.total_retenido, 0) AS retenido,
                       COALESCE(nc.total_nc, 0)        AS nota_credito
                FROM ventas_cabecera v
                LEFT JOIN (
                    SELECT d.id_referencia_documento AS id_factura, SUM(d.monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON i.id = d.id_ingreso
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.estado != 'anulado' AND i.eliminado = false
                      AND d.id_referencia_documento IN ($ph)
                    GROUP BY d.id_referencia_documento
                ) cob ON cob.id_factura = v.id
                LEFT JOIN (
                    SELECT id_venta, SUM(total_renta + total_iva + total_isd) AS total_retenido
                    FROM retencion_venta_cabecera
                    WHERE eliminado = false AND id_venta IS NOT NULL AND id_empresa = ?
                    GROUP BY id_venta
                ) ret ON ret.id_venta = v.id
                LEFT JOIN (
                    SELECT num_doc_modificado, SUM(importe_total) AS total_nc
                    FROM notas_credito_cabecera
                    WHERE estado != 'anulado' AND eliminado = false AND id_empresa = ?
                    GROUP BY num_doc_modificado
                ) nc ON nc.num_doc_modificado = CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial)
                WHERE v.id IN ($ph) AND v.eliminado = false";

        // Params: cobrado IN, retenido id_empresa, nc id_empresa, WHERE IN
        $params = array_merge($idsFactura, [$idEmpresa, $idEmpresa], $idsFactura);
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $total    = (float) $r['total'];
            $cobrado  = (float) $r['cobrado'];
            $retenido = (float) $r['retenido'];
            $nc       = (float) $r['nota_credito'];
            $saldo    = round($total - $cobrado - $retenido - $nc, 2);
            $aplicado = $cobrado + $retenido + $nc;

            if ($total > 0 && $saldo <= 0.005) {
                $estado = 'pagado';
            } elseif ($aplicado > 0.005) {
                $estado = 'parcial';
            } else {
                $estado = 'pendiente';
            }

            $out[(int) $r['id_factura']] = [
                'total'        => $total,
                'cobrado'      => $cobrado,
                'retenido'     => $retenido,
                'nota_credito' => $nc,
                'saldo'        => max(0, $saldo),
                'estado'       => $estado,
            ];
        }
        return $out;
    }

    // ── CRUD suscripción ──────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (id_empresa, id_cliente, id_periodicidad,
                     fecha_inicio, fecha_fin, proximo_cobro,
                     forma_cobro, pasarela_tarjeta, estado, tipo_comprobante,
                     kushki_token, kushki_card_last4, kushki_card_brand, kushki_card_name,
                     observaciones, info_adicional, created_by, created_at, eliminado)
                VALUES
                    (:id_empresa, :id_cliente, :id_periodicidad,
                     :fecha_inicio, :fecha_fin, :proximo_cobro,
                     :forma_cobro, :pasarela_tarjeta, :estado, :tipo_comprobante,
                     :kushki_token, :kushki_card_last4, :kushki_card_brand, :kushki_card_name,
                     :observaciones, :info_adicional, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'        => $data['id_empresa'],
            ':id_cliente'        => $data['id_cliente'],
            ':id_periodicidad'   => $data['id_periodicidad'],
            ':fecha_inicio'      => $data['fecha_inicio'],
            ':fecha_fin'         => empty($data['fecha_fin']) ? null : $data['fecha_fin'],
            ':proximo_cobro'     => $data['proximo_cobro'],
            ':forma_cobro'       => $data['forma_cobro'] ?? 'credito',
            ':pasarela_tarjeta'  => $data['pasarela_tarjeta'] ?? null,
            ':estado'            => $data['estado'] ?? 'activo',
            ':tipo_comprobante'  => $data['tipo_comprobante'] ?? 'factura',
            ':kushki_token'      => $data['kushki_token'] ?? null,
            ':kushki_card_last4' => $data['kushki_card_last4'] ?? null,
            ':kushki_card_brand' => $data['kushki_card_brand'] ?? null,
            ':kushki_card_name'  => $data['kushki_card_name'] ?? null,
            ':observaciones'     => $data['observaciones'] ?? null,
            ':info_adicional'    => $data['info_adicional'] ?? null,
            ':created_by'        => $data['id_usuario'],
        ]);
        return $this->lastInsertId('suscripciones_id_seq');
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_cliente      = :id_cliente,
                    id_periodicidad = :id_periodicidad,
                    fecha_inicio    = :fecha_inicio,
                    fecha_fin       = :fecha_fin,
                    proximo_cobro   = :proximo_cobro,
                    forma_cobro     = :forma_cobro,
                    pasarela_tarjeta= :pasarela_tarjeta,
                    estado          = :estado,
                    tipo_comprobante= :tipo_comprobante,
                    observaciones   = :observaciones,
                    info_adicional  = :info_adicional,
                    updated_by      = :updated_by,
                    updated_at      = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_cliente'      => $data['id_cliente'],
            ':id_periodicidad' => $data['id_periodicidad'],
            ':fecha_inicio'    => $data['fecha_inicio'],
            ':fecha_fin'       => empty($data['fecha_fin']) ? null : $data['fecha_fin'],
            ':proximo_cobro'   => $data['proximo_cobro'],
            ':forma_cobro'     => $data['forma_cobro'],
            ':pasarela_tarjeta'=> $data['pasarela_tarjeta'] ?? null,
            ':estado'          => $data['estado'],
            ':tipo_comprobante'=> $data['tipo_comprobante'] ?? 'factura',
            ':observaciones'   => $data['observaciones'] ?? null,
            ':info_adicional'  => $data['info_adicional'] ?? null,
            ':updated_by'      => $data['id_usuario'],
            ':id'              => $id,
            ':id_empresa'      => $idEmpresa,
        ]);
    }

    /**
     * Vincula una tarjeta guardada de Nuvei (nuvei_tarjetas_cliente) a la
     * suscripción como su método de cobro recurrente. Se llama automáticamente
     * cuando el cliente completa el registro de tarjeta vía el enlace público,
     * o manualmente al elegir una tarjeta ya existente del cliente.
     */
    public function updateNuveiTarjeta(int $id, int $idNuveiTarjeta): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
             SET pasarela_tarjeta = 'nuvei', id_nuvei_tarjeta = :itc, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        return $st->execute([':itc' => $idNuveiTarjeta, ':id' => $id]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true, deleted_by = :id_u,
                    deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id_u' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function updateEstado(int $id, string $estado, ?int $idUsuario = null): bool
    {
        $sql = "UPDATE {$this->table} SET estado = :estado, updated_by = :id_u, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $st  = $this->db->prepare($sql);
        return $st->execute([':estado' => $estado, ':id_u' => $idUsuario, ':id' => $id]);
    }

    public function updateKushkiToken(int $id, int $idEmpresa, string $token, string $last4, string $brand, string $cardName): bool
    {
        $sql = "UPDATE {$this->table} SET
                    kushki_token = :token, kushki_card_last4 = :last4,
                    kushki_card_brand = :brand, kushki_card_name = :card_name,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':token' => $token, ':last4' => $last4, ':brand' => $brand, ':card_name' => $cardName, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function updateProximoCobro(int $id, string $proximoCobro): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET proximo_cobro = :pc, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        return $st->execute([':pc' => $proximoCobro, ':id' => $id]);
    }

    public function incrementarIntentosFallidos(int $id): void
    {
        $this->db->prepare("UPDATE {$this->table} SET intentos_fallidos = intentos_fallidos + 1, ultimo_intento_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $id]);
    }

    public function resetIntentosFallidos(int $id): void
    {
        $this->db->prepare("UPDATE {$this->table} SET intentos_fallidos = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $id]);
    }

    // ── Detalle (productos/servicios) ─────────────────────────────────────────

    public function getDetalle(int $idSuscripcion): array
    {
        $sql = "SELECT sd.*, p.nombre AS nombre_producto, p.codigo AS codigo_producto,
                       ti.codigo AS codigo_porcentaje
                FROM suscripciones_detalle sd
                LEFT JOIN productos p ON p.id = sd.id_producto
                LEFT JOIN tarifa_iva ti ON ti.id = sd.id_tarifa_iva
                WHERE sd.id_suscripcion = :id AND sd.eliminado = false
                ORDER BY sd.orden ASC, sd.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO suscripciones_detalle
                    (id_suscripcion, id_empresa, id_producto, descripcion, cantidad,
                     precio_unitario, porcentaje_iva, id_tarifa_iva, orden, created_by, created_at, eliminado)
                VALUES
                    (:id_suscripcion, :id_empresa, :id_producto, :descripcion, :cantidad,
                     :precio_unitario, :porcentaje_iva, :id_tarifa_iva, :orden, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_suscripcion' => $data['id_suscripcion'],
            ':id_empresa'     => $data['id_empresa'],
            ':id_producto'    => $data['id_producto'],
            ':descripcion'    => $data['descripcion'] ?? null,
            ':cantidad'       => $data['cantidad'] ?? 1,
            ':precio_unitario'=> $data['precio_unitario'] ?? 0,
            ':porcentaje_iva' => $data['porcentaje_iva'] ?? 0,
            ':id_tarifa_iva'  => !empty($data['id_tarifa_iva']) ? (int) $data['id_tarifa_iva'] : null,
            ':orden'          => $data['orden'] ?? 0,
            ':created_by'     => $data['id_usuario'] ?? 0,
        ]);
        return $this->lastInsertId('suscripciones_detalle_id_seq');
    }

    public function deleteDetalle(int $idSuscripcion, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_detalle SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP WHERE id_suscripcion = :id AND eliminado = false"
        )->execute([':id_u' => $idUsuario, ':id' => $idSuscripcion]);
    }

    // ── Pagos ─────────────────────────────────────────────────────────────────

    public function insertPago(array $data): int
    {
        $sql = "INSERT INTO suscripciones_pagos
                    (id_suscripcion, id_empresa, fecha_cobro, monto, estado, id_factura, id_recibo,
                     kushki_transaction_id, kushki_response, nuvei_transaction_id, nuvei_response,
                     intentos, created_by, created_at, eliminado)
                VALUES
                    (:id_suscripcion, :id_empresa, :fecha_cobro, :monto, :estado, :id_factura, :id_recibo,
                     :kushki_transaction_id, :kushki_response, :nuvei_transaction_id, :nuvei_response,
                     :intentos, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_suscripcion'        => $data['id_suscripcion'],
            ':id_empresa'            => $data['id_empresa'],
            ':fecha_cobro'           => $data['fecha_cobro'] ?? date('Y-m-d'),
            ':monto'                 => $data['monto'],
            ':estado'                => $data['estado'] ?? 'pendiente',
            ':id_factura'            => $data['id_factura'] ?? null,
            ':id_recibo'             => $data['id_recibo'] ?? null,
            ':kushki_transaction_id' => $data['kushki_transaction_id'] ?? null,
            ':kushki_response'       => isset($data['kushki_response']) ? json_encode($data['kushki_response']) : null,
            ':nuvei_transaction_id'  => $data['nuvei_transaction_id'] ?? null,
            ':nuvei_response'        => isset($data['nuvei_response']) ? json_encode($data['nuvei_response']) : null,
            ':intentos'              => $data['intentos'] ?? 0,
            ':created_by'            => $data['id_usuario'] ?? 0,
        ]);
        return $this->lastInsertId('suscripciones_pagos_id_seq');
    }

    public function updatePago(int $idPago, array $data): bool
    {
        $sql = "UPDATE suscripciones_pagos SET
                    estado = :estado, id_factura = :id_factura,
                    kushki_transaction_id = :kushki_transaction_id,
                    kushki_response = :kushki_response,
                    nuvei_transaction_id = :nuvei_transaction_id,
                    nuvei_response = :nuvei_response,
                    intentos = :intentos, ultimo_intento_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':estado'                => $data['estado'],
            ':id_factura'            => $data['id_factura'] ?? null,
            ':kushki_transaction_id' => $data['kushki_transaction_id'] ?? null,
            ':kushki_response'       => isset($data['kushki_response']) ? json_encode($data['kushki_response']) : null,
            ':nuvei_transaction_id'  => $data['nuvei_transaction_id']  ?? null,
            ':nuvei_response'        => isset($data['nuvei_response']) ? json_encode($data['nuvei_response']) : null,
            ':intentos'              => $data['intentos'] ?? 0,
            ':id'                    => $idPago,
        ]);
    }

    /**
     * Pagos de suscripción pendientes de cobrar (o con intentos fallidos previos,
     * bajo el tope) cuya suscripción usa tarjeta vía Nuvei con token guardado.
     * Alimenta la automatización "Cobrar suscripciones (Nuvei)", desacoplada de
     * la generación del documento.
     */
    public function getPagosPendientesNuvei(int $idEmpresa, int $maxIntentos): array
    {
        $sql = "SELECT sp.id, sp.id_suscripcion, sp.id_factura, sp.id_recibo, sp.monto, sp.intentos,
                       s.id_nuvei_tarjeta, per.nombre AS periodicidad_nombre,
                       c.id AS id_cliente, c.nombre AS cliente_nombre, c.email AS cliente_email
                FROM suscripciones_pagos sp
                INNER JOIN suscripciones s ON s.id = sp.id_suscripcion
                LEFT JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE sp.id_empresa      = :id_empresa
                  AND sp.eliminado       = false
                  AND sp.estado IN ('pendiente', 'fallido')
                  AND sp.intentos        < :max_intentos
                  AND s.pasarela_tarjeta = 'nuvei'
                  AND s.id_nuvei_tarjeta IS NOT NULL
                  AND s.eliminado        = false
                ORDER BY sp.fecha_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':max_intentos' => $maxIntentos]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPagosPorSuscripcion(int $idSuscripcion): array
    {
        // El pago apunta a una factura (id_factura → ventas_cabecera) o a un recibo
        // (id_recibo → recibos_venta_cabecera), según el tipo_comprobante de la
        // suscripción. Se expone el número/estado del documento que corresponda.
        $sql = "SELECT sp.*,
                       COALESCE(vc.factura_numero, rv.recibo_numero) AS factura_numero,
                       COALESCE(vc.estado, rv.estado)                AS estado_factura,
                       CASE WHEN sp.id_recibo IS NOT NULL THEN 'recibo' ELSE 'factura' END AS tipo_documento
                FROM suscripciones_pagos sp
                LEFT JOIN ventas_cabecera vc        ON vc.id = sp.id_factura
                LEFT JOIN recibos_venta_cabecera rv ON rv.id = sp.id_recibo
                WHERE sp.id_suscripcion = :id AND sp.eliminado = false
                ORDER BY sp.fecha_cobro DESC, sp.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Notificaciones ────────────────────────────────────────────────────────

    public function insertNotificacion(array $data): int
    {
        $sql = "INSERT INTO suscripciones_notificaciones
                    (id_suscripcion, id_empresa, id_pago, tipo, destinatario, asunto,
                     estado, error_detalle, enviado_at, created_by, created_at, eliminado)
                VALUES
                    (:id_suscripcion, :id_empresa, :id_pago, :tipo, :destinatario, :asunto,
                     :estado, :error_detalle, CURRENT_TIMESTAMP, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_suscripcion' => $data['id_suscripcion'],
            ':id_empresa'     => $data['id_empresa'],
            ':id_pago'        => $data['id_pago'] ?? null,
            ':tipo'           => $data['tipo'],
            ':destinatario'   => $data['destinatario'],
            ':asunto'         => $data['asunto'] ?? null,
            ':estado'         => $data['estado'] ?? 'enviado',
            ':error_detalle'  => $data['error_detalle'] ?? null,
            ':created_by'     => $data['id_usuario'] ?? 0,
        ]);
        return $this->lastInsertId('suscripciones_notificaciones_id_seq');
    }

    // ── Periodicidades ────────────────────────────────────────────────────────

    public function getPeriodicidades(): array
    {
        $st = $this->db->query("SELECT * FROM suscripcion_periodicidades WHERE estado = true ORDER BY orden ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Para cron ─────────────────────────────────────────────────────────────

    public function getVencidasParaCobro(int $lote = 50): array
    {
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       c.tipo_id        AS cliente_tipo_id,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c   ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.estado = 'activo' AND s.eliminado = false AND s.proximo_cobro <= CURRENT_DATE
                ORDER BY s.proximo_cobro ASC
                LIMIT $lote
                FOR UPDATE SKIP LOCKED";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParaGeneracionManual(int $idEmpresa, int $idPeriodicidad): array
    {
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       c.tipo_id        AS cliente_tipo_id,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c   ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :id_empresa
                  AND s.id_periodicidad = :id_per
                  AND s.estado = 'activo' 
                  AND s.eliminado = false 
                  AND s.proximo_cobro <= CURRENT_DATE
                  AND (s.fecha_inicio IS NULL OR s.fecha_inicio <= CURRENT_DATE)
                  AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
                ORDER BY s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_per' => $idPeriodicidad]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suscripciones de una empresa con al menos un período vencido por facturar.
     * No filtra por periodicidad (el cron procesa todas).
     * Incluye las que ya pasaron su fecha_fin pero tienen períodos previos pendientes
     * (proximo_cobro <= fecha_fin). El bucle de "ponerse al día" del handler corta en fecha_fin.
     */
    public function getVencidasPorEmpresa(int $idEmpresa): array
    {
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       c.tipo_id        AS cliente_tipo_id,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :id_empresa
                  AND s.estado = 'activo'
                  AND s.eliminado = false
                  AND s.proximo_cobro <= CURRENT_DATE
                  AND (s.fecha_inicio IS NULL OR s.fecha_inicio <= CURRENT_DATE)
                  AND (s.fecha_fin IS NULL OR s.proximo_cobro <= s.fecha_fin)
                ORDER BY s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suscripciones de una empresa cuyo próximo cobro vence en EXACTAMENTE N días
     * (proximo_cobro = hoy + diasAntes). Usada por el aviso de vencimiento.
     * Solo activas, vigentes y con correo de cliente registrado.
     */
    public function getProximasAVencer(int $idEmpresa, int $diasAntes): array
    {
        $dias = max(0, $diasAntes);
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.telefono       AS cliente_telefono,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :id_empresa
                  AND s.estado = 'activo'
                  AND s.eliminado = false
                  AND s.proximo_cobro = CURRENT_DATE + CAST(:dias AS INTEGER)
                  AND (s.fecha_fin IS NULL OR s.proximo_cobro <= s.fecha_fin)
                ORDER BY s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':dias' => $dias]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Datos del establecimiento + punto de emisión a partir de la serie elegida. */
    public function getEstablecimientoPorPunto(int $idEmpresa, int $idPuntoEmision): ?array
    {
        $sql = "SELECT ep.*, pe.id AS id_punto_emision, pe.codigo_punto AS punto_emision_codigo
                FROM empresa_establecimiento ep
                JOIN empresa_punto_emision pe ON pe.id_establecimiento = ep.id
                WHERE pe.id = :id_punto AND ep.id_empresa = :id_empresa
                  AND ep.estado = 'activo' AND pe.eliminado = false AND ep.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Series (puntos de emisión) activas de la empresa, para el selector del cron. */
    public function getSeriesActivas(int $idEmpresa): array
    {
        $sql = "SELECT pe.id AS id_punto_emision,
                       ep.codigo AS establecimiento_codigo,
                       pe.codigo_punto AS punto_emision_codigo,
                       ep.nombre AS establecimiento_nombre
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento ep ON ep.id = pe.id_establecimiento
                WHERE ep.id_empresa = :id_empresa
                  AND ep.estado = 'activo'
                  AND ep.eliminado = false
                  AND pe.eliminado = false
                ORDER BY ep.codigo ASC, pe.codigo_punto ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetalleParaCobro(int $idSuscripcion): array
    {
        $sql = "SELECT sd.*, ti.codigo AS codigo_porcentaje
                FROM suscripciones_detalle sd
                LEFT JOIN tarifa_iva ti ON ti.id = sd.id_tarifa_iva
                WHERE sd.id_suscripcion = :id AND sd.eliminado = false
                ORDER BY sd.orden ASC, sd.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
