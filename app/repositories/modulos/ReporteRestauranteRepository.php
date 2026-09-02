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
     * IngresoRepository::getFormasCobro(): son las que pueden recibir dinero.
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

    /** KPIs generales (tarjetas resumen arriba de la tabla). */
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
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['cantidad_documentos' => 0, 'cantidad_comandas' => 0, 'total_vendido' => 0];
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
