<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Reportes del POS Restaurantes: ventas por mesa, por ítem del menú, por
 * categoría del menú y por mesero. Se arma desde comanda_grupos_cobro
 * (estado='cobrado') — el dinero REALMENTE facturado, no lo que quedó en la
 * comanda sin cobrar. El split "por ítems" toma la línea completa
 * (comanda_detalle.id_grupo_cobro); el split "partes iguales" reparte cada
 * línea del pool compartido 1/total_partes por cada parte ya cobrada (ver
 * ComandaService::crearGruposPartesIguales/cobrarGrupo) — así una parte
 * cobrada sí cuenta aunque las otras N-1 sigan pendientes.
 *
 * Dos fuentes, dos importes distintos:
 *   · Las líneas de la comanda (CTE "ventas"): precio SIN IVA ni servicio. De
 *     ahí salen las vistas por mesa, mesero, menú y categoría, y el Total
 *     vendido.
 *   · Los comprobantes que emitió cada cobro (CTE "docs", ver cteDocumentos()):
 *     importe CON impuestos. De ahí salen la vista por forma de pago, el Total
 *     cobrado y el detalle de impuestos — lo mismo que suma el cierre de caja.
 */
class ReporteRestauranteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('comanda_grupos_cobro');
    }

    /**
     * CTE "ventas" (id_grupo, id_comanda, fecha_cobro, id_menu_item,
     * descripcion, cantidad, monto) con los filtros de fecha ya aplicados.
     * Fuente común de todos los modos del reporte.
     */
    private function cteVentas(int $idEmpresa, array $filtros): array
    {
        $params = [':e1' => $idEmpresa, ':e2' => $idEmpresa];

        $condFecha1 = '';
        $condFecha2 = '';
        if (!empty($filtros['fecha_desde'])) {
            $condFecha1 .= " AND g.created_at::date >= :fd1";
            $condFecha2 .= " AND g.created_at::date >= :fd2";
            $params[':fd1'] = $filtros['fecha_desde'];
            $params[':fd2'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $condFecha1 .= " AND g.created_at::date <= :fh1";
            $condFecha2 .= " AND g.created_at::date <= :fh2";
            $params[':fh1'] = $filtros['fecha_hasta'];
            $params[':fh2'] = $filtros['fecha_hasta'];
        }

        // Forma de pago de la empresa (Efectivo, un banco, Payphone…). La cuenta
        // cobrada NO la guarda: en `forma_pago` lleva el código SRI ('01', '20'),
        // que agrupa demasiado —Pichincha y Guayaquil son ambos '20'—. La forma
        // real vive en el Ingreso que generó ese cobro, así que se llega por
        // documento → ingresos_detalle → ingresos_pagos.
        //
        // Va como EXISTS y no como JOIN a propósito: un ingreso puede tener
        // varias filas de pago y un documento varios ingresos (cobros
        // parciales); un JOIN multiplicaría las líneas de venta e inflaría los
        // totales del reporte. EXISTS filtra sin duplicar nada.
        $condFp1 = '';
        $condFp2 = '';
        if (!empty($filtros['id_forma_pago'])) {
            $existe = static fn(string $p): string => "
                  AND EXISTS (
                      SELECT 1
                        FROM ingresos_detalle idet
                        JOIN ingresos_cabecera ic ON ic.id = idet.id_ingreso
                                                 AND ic.eliminado = false
                                                 AND ic.estado <> 'anulado'
                        JOIN ingresos_pagos ip ON ip.id_ingreso = ic.id
                       WHERE idet.id_referencia_documento = g.id_documento
                         AND idet.tipo_documento = g.tipo_documento
                         AND ip.id_forma_cobro = :{$p}
                  )";
            $condFp1 = $existe('ifp1');
            $condFp2 = $existe('ifp2');
            $params[':ifp1'] = (int) $filtros['id_forma_pago'];
            $params[':ifp2'] = (int) $filtros['id_forma_pago'];
        }

        $sql = "
            WITH ventas AS (
                SELECT g.id AS id_grupo, g.id_comanda, g.created_at AS fecha_cobro,
                       cd.id AS id_linea, cd.id_menu_item, cd.descripcion,
                       cd.cantidad AS cantidad, cd.subtotal AS monto
                FROM comanda_grupos_cobro g
                JOIN comanda_detalle cd ON cd.id_grupo_cobro = g.id
                WHERE g.id_empresa = :e1 AND g.eliminado = false AND g.estado = 'cobrado'
                  AND g.tipo_split = 'items'
                  {$condFecha1}
                  {$condFp1}

                UNION ALL

                SELECT g.id AS id_grupo, g.id_comanda, g.created_at AS fecha_cobro,
                       cd.id AS id_linea, cd.id_menu_item, cd.descripcion,
                       ROUND(cd.cantidad / g.total_partes, 4) AS cantidad,
                       ROUND(cd.subtotal / g.total_partes, 2) AS monto
                FROM comanda_grupos_cobro g
                JOIN comanda_grupo_partes_lineas gpl ON gpl.id_grupo_raiz = COALESCE(g.id_grupo_padre, g.id)
                JOIN comanda_detalle cd ON cd.id = gpl.id_linea
                WHERE g.id_empresa = :e2 AND g.eliminado = false AND g.estado = 'cobrado'
                  AND g.tipo_split = 'partes_iguales'
                  {$condFecha2}
                  {$condFp2}
            )
        ";

        return [$sql, $params];
    }

    /**
     * Formas de cobro de la empresa, para el filtro. Mismo criterio que
     * FormaPagoRepository::getFormasFiltradas(..., 'INGRESO'): son las que pueden recibir dinero.
     */
    public function getFormasPago(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo
                FROM empresa_formas_pago
                WHERE id_empresa = :e AND activo = TRUE AND eliminado = FALSE
                  AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** WHERE + params comunes de mesa/mesero, aplicados sobre "c" (comandas) tras unir con la CTE. */
    private function condComanda(array $filtros, array &$params): string
    {
        $cond = '';
        if (!empty($filtros['id_mesa'])) {
            $cond .= " AND c.id_mesa = :id_mesa";
            $params[':id_mesa'] = (int) $filtros['id_mesa'];
        }
        if (!empty($filtros['id_usuario'])) {
            $cond .= " AND c.id_usuario_mesero = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }
        return $cond;
    }

    /** Ventas por mesa. */
    public function getVentasPorMesa(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);

        $sql = $cte . "
            SELECT m.id AS id_mesa, m.nombre AS mesa_nombre, m.ubicacion,
                   COUNT(DISTINCT c.id) AS cantidad_comandas,
                   COUNT(DISTINCT ventas.id_grupo) AS cantidad_documentos,
                   COALESCE(SUM(ventas.monto), 0) AS total
            FROM ventas
            JOIN comandas c ON c.id = ventas.id_comanda
            JOIN mesas m ON m.id = c.id_mesa
            WHERE 1=1 {$condComanda}
            GROUP BY m.id, m.nombre, m.ubicacion
            ORDER BY total DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ventas por mesero (usuario atribuido a la comanda). */
    public function getVentasPorMesero(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);

        $sql = $cte . "
            SELECT u.id AS id_usuario, u.nombre AS mesero_nombre,
                   COUNT(DISTINCT c.id) AS cantidad_comandas,
                   COUNT(DISTINCT ventas.id_grupo) AS cantidad_documentos,
                   COALESCE(SUM(ventas.monto), 0) AS total
            FROM ventas
            JOIN comandas c ON c.id = ventas.id_comanda
            JOIN usuarios u ON u.id = c.id_usuario_mesero
            WHERE 1=1 {$condComanda}
            GROUP BY u.id, u.nombre
            ORDER BY total DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ítems del menú más vendidos. Ítems sin id_menu_item (del catálogo general de Stock) se agrupan por su descripción. */
    public function getVentasPorMenu(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);

        $condMenu = '';
        if (!empty($filtros['id_menu_item'])) {
            $condMenu = " AND ventas.id_menu_item = :id_menu_item";
            $params[':id_menu_item'] = (int) $filtros['id_menu_item'];
        }
        if (!empty($filtros['id_categoria'])) {
            $condMenu .= " AND mi.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }

        $sql = $cte . "
            SELECT COALESCE(mi.id, 0) AS id_menu_item,
                   COALESCE(mi.nombre, ventas.descripcion) AS item_nombre,
                   COALESCE(mc.nombre, 'Sin categoría') AS categoria_nombre,
                   SUM(ventas.cantidad) AS cantidad_vendida,
                   SUM(ventas.monto) AS total
            FROM ventas
            JOIN comandas c ON c.id = ventas.id_comanda
            LEFT JOIN menu_items mi ON mi.id = ventas.id_menu_item
            LEFT JOIN categorias mc ON mc.id = mi.id_categoria AND mc.id_empresa = mi.id_empresa
            WHERE 1=1 {$condComanda} {$condMenu}
            GROUP BY COALESCE(mi.id, 0), COALESCE(mi.nombre, ventas.descripcion), COALESCE(mc.nombre, 'Sin categoría')
            ORDER BY cantidad_vendida DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ventas por categoría del menú. */
    public function getVentasPorCategoria(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);

        $sql = $cte . "
            SELECT COALESCE(mc.id, 0) AS id_categoria,
                   COALESCE(mc.nombre, 'Sin categoría') AS categoria_nombre,
                   SUM(ventas.cantidad) AS cantidad_vendida,
                   SUM(ventas.monto) AS total
            FROM ventas
            JOIN comandas c ON c.id = ventas.id_comanda
            LEFT JOIN menu_items mi ON mi.id = ventas.id_menu_item
            LEFT JOIN categorias mc ON mc.id = mi.id_categoria AND mc.id_empresa = mi.id_empresa
            WHERE 1=1 {$condComanda}
            GROUP BY COALESCE(mc.id, 0), COALESCE(mc.nombre, 'Sin categoría')
            ORDER BY total DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * KPIs generales (tarjetas resumen arriba de la tabla).
     *
     * Dos totales que no son lo mismo: `total_vendido` suma las líneas de la
     * comanda, SIN IVA ni servicio; `total_cobrado` suma el importe de las
     * facturas y recibos, CON impuestos, y es la cifra que cuadra con el cierre
     * de caja. `documentos_vigentes` cuenta esos comprobantes: si es menor que
     * `cantidad_documentos` (los cobros), hay cobros cuyo comprobante se anuló o
     * eliminó —siguen en el Total vendido, pero ya no en el Total cobrado—.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);

        $sql = $cte . "
            SELECT COUNT(DISTINCT ventas.id_grupo) AS cantidad_documentos,
                   COUNT(DISTINCT c.id) AS cantidad_comandas,
                   COALESCE(SUM(ventas.monto), 0) AS total_vendido
            FROM ventas
            JOIN comandas c ON c.id = ventas.id_comanda
            WHERE 1=1 {$condComanda}
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $stats = $st->fetch(PDO::FETCH_ASSOC) ?: ['cantidad_documentos' => 0, 'cantidad_comandas' => 0, 'total_vendido' => 0];

        [$cteDocs, $paramsDocs] = $this->cteDocumentos($idEmpresa, $filtros);
        $st = $this->db->prepare($cteDocs . "
            SELECT COUNT(*) AS documentos_vigentes,
                   COALESCE(SUM(importe_total), 0) AS total_cobrado
            FROM docs
        ");
        $st->execute($paramsDocs);
        $docs = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['documentos_vigentes'] = (int) ($docs['documentos_vigentes'] ?? 0);
        $stats['total_cobrado']       = round((float) ($docs['total_cobrado'] ?? 0), 2);

        return $stats;
    }

    /**
     * Servicio y propina voluntaria de las ventas que entran en el reporte, para
     * el bloque "resumen por forma de pago" de la tirilla —el mismo que manda el
     * correo del cierre de caja (CajaSesionRepository::getPropinasDelTurno()),
     * solo que acotado por los filtros del reporte y no por turno—. Las dos
     * viajan en sitios distintos del comprobante, así que se suman aparte:
     *   · **Servicio** (el recargo del local): campo `propina` de la factura o
     *     recibo que generó cada grupo de cobro. Cada documento se cuenta una
     *     sola vez aunque lo alcancen varias líneas de la CTE.
     *   · **Propina voluntaria** del cliente: líneas de comanda cuyo producto es
     *     el configurado como propina en algún establecimiento de la empresa.
     *     Ya están dentro del "total vendido" (la CTE las incluye como una línea
     *     más); aquí solo se dice cuánto de ese total es propina.
     *
     * @return array{servicio: float, voluntaria: float}
     */
    public function getPropinas(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);
        $params[':e3'] = $idEmpresa;

        $sql = $cte . ",
            docs AS (
                SELECT DISTINCT g2.id_documento, g2.tipo_documento
                FROM ventas
                JOIN comandas c ON c.id = ventas.id_comanda
                JOIN comanda_grupos_cobro g2 ON g2.id = ventas.id_grupo
                WHERE g2.id_documento IS NOT NULL {$condComanda}
            )
            SELECT
                COALESCE((SELECT SUM(v.propina)
                            FROM docs d
                            JOIN ventas_cabecera v ON v.id = d.id_documento
                           WHERE d.tipo_documento = 'FACTURA'
                             AND v.eliminado = false AND v.estado <> 'anulado'), 0)
              + COALESCE((SELECT SUM(r.propina)
                            FROM docs d
                            JOIN recibos_venta_cabecera r ON r.id = d.id_documento
                           WHERE d.tipo_documento = 'RECIBO'
                             AND r.eliminado = false AND r.estado <> 'anulado'), 0) AS servicio,
                COALESCE((SELECT SUM(ventas.monto)
                            FROM ventas
                            JOIN comandas c ON c.id = ventas.id_comanda
                            JOIN comanda_detalle cd ON cd.id = ventas.id_linea
                           WHERE cd.id_producto IN (
                                     SELECT ee.id_producto_propina
                                       FROM empresa_establecimiento ee
                                      WHERE ee.id_empresa = :e3
                                        AND ee.id_producto_propina IS NOT NULL
                                 ) {$condComanda}), 0) AS voluntaria
        ";

        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Mismo criterio defensivo que el cierre de caja: si falta la columna
            // de propina por una migración pendiente, la tirilla sale igual sin
            // este par de líneas en vez de romperse.
            error_log('[ReporteRestaurante] No se pudieron sumar servicio/propina: ' . $e->getMessage());
            $r = [];
        }

        return [
            'servicio'   => round((float) ($r['servicio'] ?? 0), 2),
            'voluntaria' => round((float) ($r['voluntaria'] ?? 0), 2),
        ];
    }

    /**
     * CTE "docs" (tipo, id, total_sin_impuestos, propina, importe_total): los
     * comprobantes —Factura o Recibo— que emitieron los cobros que entran en el
     * reporte, cada uno una sola vez, con los mismos filtros que el resto.
     *
     * Es la fuente de lo que tiene impuestos: la CTE "ventas" parte de las líneas
     * de la comanda, que guardan el precio SIN IVA ni servicio; el IVA, el
     * servicio y el total cobrado solo existen en el comprobante. Los anulados o
     * eliminados no entran, igual que en el cierre de caja
     * (CajaSesionRepository::getCobrosPorFormaPagoEnTurno()).
     */
    private function cteDocumentos(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteVentas($idEmpresa, $filtros);
        $condComanda = $this->condComanda($filtros, $params);
        $params[':ed1'] = $idEmpresa;
        $params[':ed2'] = $idEmpresa;

        $sql = $cte . ",
            grupos_doc AS (
                SELECT DISTINCT g2.tipo_documento, g2.id_documento
                FROM ventas
                JOIN comandas c ON c.id = ventas.id_comanda
                JOIN comanda_grupos_cobro g2 ON g2.id = ventas.id_grupo
                WHERE g2.id_documento IS NOT NULL {$condComanda}
            ),
            docs AS (
                SELECT 'FACTURA' AS tipo, v.id, v.total_sin_impuestos, v.propina, v.importe_total
                  FROM grupos_doc gd
                  JOIN ventas_cabecera v ON v.id = gd.id_documento
                 WHERE gd.tipo_documento = 'FACTURA'
                   AND v.id_empresa = :ed1 AND v.eliminado = false AND v.estado <> 'anulado'
                UNION ALL
                SELECT 'RECIBO', r.id, r.total_sin_impuestos, r.propina, r.importe_total
                  FROM grupos_doc gd
                  JOIN recibos_venta_cabecera r ON r.id = gd.id_documento
                 WHERE gd.tipo_documento = 'RECIBO'
                   AND r.id_empresa = :ed2 AND r.eliminado = false AND r.estado <> 'anulado'
            )
        ";

        return [$sql, $params];
    }

    /**
     * Resumen por forma de pago (vista de pantalla, PDF, Excel, correo y
     * tirilla): cuánto entró por cada una —Efectivo, un banco, Payphone…— CON
     * impuestos, es decir, el importe total de cada factura o recibo (subtotal +
     * IVA + servicio). Mismo cálculo que el correo del cierre de caja
     * (CajaSesionRepository::getCobrosPorFormaPagoEnTurno()), solo que acotado
     * por los filtros del reporte y no por turno. Suma el Total cobrado, no el
     * Total vendido (que va sin impuestos).
     *
     * La forma no está en la cuenta cobrada —ahí solo vive el código SRI—, así
     * que se resuelve por el Ingreso que generó cada comprobante. Va como LEFT
     * JOIN LATERAL ... LIMIT 1 y no como JOIN llano: un ingreso puede tener
     * varias filas de pago y un documento varios ingresos (cobros parciales), y
     * un JOIN contaría el mismo comprobante más de una vez.
     *
     * Los cobros sin Ingreso registrado caen en "Sin forma de pago registrada".
     * No es ruido: es justo lo que hay que ir a corregir al módulo Ingresos.
     */
    public function getCobrosPorFormaPago(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocumentos($idEmpresa, $filtros);
        $params[':ei'] = $idEmpresa;

        $sql = $cte . "
            SELECT COALESCE(fp.id, 0) AS id_forma_pago,
                   COALESCE(fp.nombre, 'Sin forma de pago registrada') AS forma_pago_nombre,
                   COALESCE(fp.tipo, '') AS forma_pago_tipo,
                   COUNT(*) AS cantidad_documentos,
                   COALESCE(SUM(d.importe_total), 0) AS total
            FROM docs d
            LEFT JOIN LATERAL (
                SELECT ip.id_forma_cobro
                  FROM ingresos_detalle idet
                  JOIN ingresos_cabecera ic ON ic.id = idet.id_ingreso
                                           AND ic.id_empresa = :ei
                                           AND ic.eliminado = false
                                           AND ic.estado <> 'anulado'
                  JOIN ingresos_pagos ip ON ip.id_ingreso = ic.id
                 WHERE idet.id_referencia_documento = d.id
                   AND idet.tipo_documento = d.tipo
                 ORDER BY ic.id DESC, ip.id ASC
                 LIMIT 1
            ) fpago ON true
            LEFT JOIN empresa_formas_pago fp ON fp.id = fpago.id_forma_cobro
            GROUP BY COALESCE(fp.id, 0),
                     COALESCE(fp.nombre, 'Sin forma de pago registrada'),
                     COALESCE(fp.tipo, '')
            ORDER BY total DESC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de impuestos de los comprobantes del reporte, para la tirilla:
     * los totales de cabecera (subtotal sin impuestos, servicio, importe total)
     * y, aparte, base y valor de cada impuesto agrupados por código y tarifa,
     * tal como se guardaron línea por línea en el comprobante.
     *
     * Una línea sin fila de IVA se cuenta como base 0%, igual que el RIDE de la
     * factura (FacturaVentaPdfService): así las bases siempre suman el subtotal.
     *
     * @return array{documentos:int, subtotal:float, servicio:float, total:float,
     *               impuestos: list<array{codigo_impuesto:string, codigo_porcentaje:string,
     *                                     tarifa:float, base:float, valor:float}>}
     */
    public function getResumenImpuestos(int $idEmpresa, array $filtros): array
    {
        [$cte, $params] = $this->cteDocumentos($idEmpresa, $filtros);

        $st = $this->db->prepare($cte . "
            SELECT COUNT(*) AS documentos,
                   COALESCE(SUM(total_sin_impuestos), 0) AS subtotal,
                   COALESCE(SUM(propina), 0) AS servicio,
                   COALESCE(SUM(importe_total), 0) AS total
            FROM docs
        ");
        $st->execute($params);
        $tot = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st = $this->db->prepare($cte . ",
            impuestos AS (
                SELECT vi.codigo_impuesto, vi.codigo_porcentaje, vi.tarifa, vi.base_imponible AS base, vi.valor
                  FROM docs d
                  JOIN ventas_detalle vd ON vd.id_venta = d.id
                  JOIN ventas_detalle_impuestos vi ON vi.id_venta_detalle = vd.id
                 WHERE d.tipo = 'FACTURA'
                UNION ALL
                SELECT '2', '0', 0, vd.precio_total_sin_impuesto, 0
                  FROM docs d
                  JOIN ventas_detalle vd ON vd.id_venta = d.id
                 WHERE d.tipo = 'FACTURA'
                   AND NOT EXISTS (SELECT 1 FROM ventas_detalle_impuestos vi
                                    WHERE vi.id_venta_detalle = vd.id AND TRIM(vi.codigo_impuesto) = '2')
                UNION ALL
                SELECT ri.codigo_impuesto, ri.codigo_porcentaje, ri.tarifa, ri.base_imponible, ri.valor
                  FROM docs d
                  JOIN recibos_venta_detalle rd ON rd.id_recibo = d.id
                  JOIN recibos_venta_detalle_impuestos ri ON ri.id_recibo_detalle = rd.id
                 WHERE d.tipo = 'RECIBO'
                UNION ALL
                SELECT '2', '0', 0, rd.precio_total_sin_impuesto, 0
                  FROM docs d
                  JOIN recibos_venta_detalle rd ON rd.id_recibo = d.id
                 WHERE d.tipo = 'RECIBO'
                   AND NOT EXISTS (SELECT 1 FROM recibos_venta_detalle_impuestos ri
                                    WHERE ri.id_recibo_detalle = rd.id AND TRIM(ri.codigo_impuesto) = '2')
            )
            SELECT TRIM(codigo_impuesto) AS codigo_impuesto,
                   TRIM(codigo_porcentaje) AS codigo_porcentaje,
                   COALESCE(tarifa, 0) AS tarifa,
                   COALESCE(SUM(base), 0) AS base,
                   COALESCE(SUM(valor), 0) AS valor
            FROM impuestos
            GROUP BY TRIM(codigo_impuesto), TRIM(codigo_porcentaje), COALESCE(tarifa, 0)
            ORDER BY 1, 3 DESC, 2
        ");
        $st->execute($params);

        return [
            'documentos' => (int) ($tot['documentos'] ?? 0),
            'subtotal'   => round((float) ($tot['subtotal'] ?? 0), 2),
            'servicio'   => round((float) ($tot['servicio'] ?? 0), 2),
            'total'      => round((float) ($tot['total'] ?? 0), 2),
            'impuestos'  => array_map(static fn(array $r): array => [
                'codigo_impuesto'   => (string) $r['codigo_impuesto'],
                'codigo_porcentaje' => (string) $r['codigo_porcentaje'],
                'tarifa'            => (float) $r['tarifa'],
                'base'              => round((float) $r['base'], 2),
                'valor'             => round((float) $r['valor'], 2),
            ], $st->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    /** Mesas de la empresa (para el filtro). */
    public function getMesas(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, ubicacion FROM mesas WHERE id_empresa = :e AND eliminado = false ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Meseros: usuarios que tienen al menos una comanda atribuida (para el filtro). */
    public function getMeseros(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM comandas c
                JOIN usuarios u ON u.id = c.id_usuario_mesero
                WHERE c.id_empresa = :e AND c.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ítems del menú de la empresa (para el filtro). */
    public function getMenuItems(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM menu_items WHERE id_empresa = :e AND eliminado = false ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Categorías del menú de la empresa (para el filtro). */
    public function getCategoriasMenu(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM categorias WHERE id_empresa = :e AND eliminado = false ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
