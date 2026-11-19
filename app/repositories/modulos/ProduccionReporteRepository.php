<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AmbienteReporte;
use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

/**
 * Reportes de producción (solo lectura). Como todo reporte, muestra únicamente
 * documentos de PRODUCCIÓN (AmbienteReporte), sin importar el ambiente de la empresa.
 * Solo cuentan las órdenes cerradas, por su fecha de cierre.
 */
class ProduccionReporteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('produccion_ordenes');
    }

    private function where(int $idEmpresa, array $f, ?int $idUsuarioFiltro, array &$params): string
    {
        $where = $this->getBaseWhere($idEmpresa, 'o', $idUsuarioFiltro)
               . " AND o.estado = 'cerrada' AND " . AmbienteReporte::condicion('o');
        $params[':id_empresa'] = $idEmpresa;
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        if (!empty($f['desde'])) {
            $where .= ' AND o.fecha_fin >= :desde';
            $params[':desde'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where .= ' AND o.fecha_fin <= :hasta';
            $params[':hasta'] = $f['hasta'];
        }
        if (!empty($f['id_producto'])) {
            $where .= ' AND o.id_producto = :id_producto';
            $params[':id_producto'] = (int) $f['id_producto'];
        }
        if (($f['tipo'] ?? '') !== '') {
            $where .= ' AND o.tipo = :tipo';
            $params[':tipo'] = $f['tipo'];
        }
        if (!empty($f['id_formula'])) {
            $where .= ' AND o.id_formula = :id_formula';
            $params[':id_formula'] = (int) $f['id_formula'];
        }
        if (!empty($f['id_bodega'])) {
            $where .= ' AND o.id_bodega_destino = :id_bodega';
            $params[':id_bodega'] = (int) $f['id_bodega'];
        }
        if (!empty($f['id_usuario'])) {
            $where .= ' AND o.created_by = :id_usuario_reg';
            $params[':id_usuario_reg'] = (int) $f['id_usuario'];
        }
        if (($f['responsable'] ?? '') !== '') {
            $cond = FiltrosBusqueda::condicionTexto(['o.responsable'], (string) $f['responsable'], $params, 'rr');
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        // Insumo: órdenes que consumieron ese producto (en «Consumo de insumos», además, solo esa línea).
        $condInsumo = $this->condicionInsumo($f, $params, 'ri', 'pi');
        if ($condInsumo !== '') {
            $where .= " AND EXISTS (SELECT 1 FROM produccion_ordenes_lineas li INNER JOIN productos pi ON pi.id = li.id_producto
                                     WHERE li.id_orden = o.id AND li.eliminado = false AND li.rol = 'insumo' AND {$condInsumo})";
        }
        return $where;
    }

    /** Condición del filtro Insumo (producto elegido) sobre el alias de productos dado ('' si no hay filtro). */
    private function condicionInsumo(array $f, array &$params, string $prefijo, string $alias): string
    {
        if (empty($f['id_insumo'])) {
            return '';
        }
        $params[":{$prefijo}_id_insumo"] = (int) $f['id_insumo'];
        return "{$alias}.id = :{$prefijo}_id_insumo";
    }

    /** Insumos de la empresa para el filtro: productos usados como insumo en fórmulas u órdenes. */
    public function insumosParaFiltro(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo, p.nombre
               FROM productos p
              WHERE p.id IN (SELECT fl.id_producto FROM produccion_formulas_lineas fl
                              WHERE fl.id_empresa = :e AND fl.eliminado = false AND fl.rol = 'insumo' AND fl.id_producto IS NOT NULL
                             UNION
                             SELECT ol.id_producto FROM produccion_ordenes_lineas ol
                              WHERE ol.id_empresa = :e AND ol.eliminado = false AND ol.rol = 'insumo' AND ol.id_producto IS NOT NULL)
              ORDER BY p.nombre, p.codigo"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Productos fabricados de la empresa para el filtro: los que tienen fórmula u orden (también inactivos). */
    public function productosParaFiltro(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.codigo, p.nombre
               FROM productos p
              WHERE p.id IN (SELECT f.id_producto FROM produccion_formulas f WHERE f.id_empresa = :e AND f.eliminado = false
                             UNION
                             SELECT o.id_producto FROM produccion_ordenes o WHERE o.id_empresa = :e AND o.eliminado = false)
              ORDER BY p.nombre, p.codigo"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Fórmulas de la empresa para el filtro (todas: también las inactivas, que pueden tener órdenes). */
    public function formulasParaFiltro(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT f.id, f.codigo, f.version, COALESCE(NULLIF(f.nombre, ''), p.nombre) AS nombre
               FROM produccion_formulas f
               INNER JOIN productos p ON p.id = f.id_producto
              WHERE f.id_empresa = :e AND f.eliminado = false
              ORDER BY COALESCE(NULLIF(f.nombre, ''), p.nombre), f.version DESC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Producción por producto: todo lo que entró al inventario (principal, coproductos, subproductos). */
    public function producido(int $idEmpresa, array $f, ?int $idUsuarioFiltro): array
    {
        $params = [];
        $where = $this->where($idEmpresa, $f, $idUsuarioFiltro, $params);
        $st = $this->db->prepare(
            "SELECT p.codigo, p.nombre, um.nombre AS medida, l.rol,
                    COUNT(DISTINCT o.id) AS ordenes,
                    SUM(l.cantidad_teorica) AS teorico, SUM(l.cantidad_real) AS real,
                    SUM(l.costo_real) AS costo,
                    CASE WHEN SUM(l.cantidad_real) > 0 THEN SUM(l.costo_real) / SUM(l.cantidad_real) ELSE 0 END AS costo_unitario
               FROM produccion_ordenes o
              INNER JOIN productos pp ON pp.id = o.id_producto
              INNER JOIN produccion_ordenes_lineas l ON l.id_orden = o.id AND l.eliminado = false AND l.rol <> 'insumo'
               LEFT JOIN productos p ON p.id = l.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              {$where}
              GROUP BY p.codigo, p.nombre, um.nombre, l.rol
              ORDER BY SUM(l.costo_real) DESC, p.nombre"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Consumo de insumos: teórico (con merma de fórmula) contra real. */
    public function consumo(int $idEmpresa, array $f, ?int $idUsuarioFiltro): array
    {
        $params = [];
        $where = $this->where($idEmpresa, $f, $idUsuarioFiltro, $params);
        $st = $this->db->prepare(
            "SELECT p.codigo, p.nombre, um.nombre AS medida,
                    COUNT(DISTINCT o.id) AS ordenes,
                    SUM(l.cantidad_teorica) AS teorico, SUM(l.cantidad_real) AS real,
                    SUM(l.cantidad_real) - SUM(l.cantidad_teorica) AS diferencia,
                    CASE WHEN SUM(l.cantidad_teorica) > 0
                         THEN (SUM(l.cantidad_real) - SUM(l.cantidad_teorica)) / SUM(l.cantidad_teorica) * 100 ELSE NULL END AS diferencia_pct,
                    SUM(l.costo_real) AS costo
               FROM produccion_ordenes o
              INNER JOIN productos pp ON pp.id = o.id_producto
              INNER JOIN produccion_ordenes_lineas l ON l.id_orden = o.id AND l.eliminado = false AND l.rol = 'insumo'
              INNER JOIN productos p ON p.id = l.id_producto
               LEFT JOIN unidades_medida um ON um.id = p.id_medida
              {$where}" . (($c = $this->condicionInsumo($f, $params, 'rl', 'p')) !== '' ? " AND {$c}" : '') . "
              GROUP BY p.codigo, p.nombre, um.nombre
              ORDER BY SUM(l.costo_real) DESC, p.nombre"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Órdenes cerradas con su costo, merma y desvío de consumo. */
    public function ordenes(int $idEmpresa, array $f, ?int $idUsuarioFiltro): array
    {
        $params = [];
        $where = $this->where($idEmpresa, $f, $idUsuarioFiltro, $params);
        $st = $this->db->prepare(
            "SELECT o.numero, o.fecha_fin, pp.codigo, pp.nombre, o.lote, o.responsable,
                    (SELECT f.codigo || ' v' || o.version_formula FROM produccion_formulas f WHERE f.id = o.id_formula) AS formula,
                    (SELECT b.nombre FROM bodegas b WHERE b.id = o.id_bodega_destino) AS bodega_destino,
                    o.cantidad_planificada, o.cantidad_producida,
                    o.cantidad_merma, o.costo_materiales, o.costo_mano_obra, o.costo_cif, o.valor_subproductos,
                    o.costo_merma, o.costo_total,
                    CASE WHEN o.cantidad_planificada > 0 THEN o.cantidad_producida / o.cantidad_planificada * 100 ELSE NULL END AS rendimiento_pct,
                    -- Desvío de consumo valorado: (real − teórico) × costo unitario real, línea por línea.
                    (SELECT COALESCE(SUM(l.costo_real - l.costo_real / NULLIF(l.cantidad_real, 0) * l.cantidad_teorica), 0)
                       FROM produccion_ordenes_lineas l
                      WHERE l.id_orden = o.id AND l.eliminado = false AND l.rol = 'insumo' AND l.cantidad_real > 0) AS desvio_consumo
               FROM produccion_ordenes o
              INNER JOIN productos pp ON pp.id = o.id_producto
              {$where}
              ORDER BY o.fecha_fin DESC, o.secuencial DESC"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Costo de producción por mes de cierre. La cantidad producida y el costo unitario solo
     * se dan cuando el mes tiene un único producto: sumar unidades distintas no tiene sentido.
     */
    public function porMes(int $idEmpresa, array $f, ?int $idUsuarioFiltro): array
    {
        $params = [];
        $where = $this->where($idEmpresa, $f, $idUsuarioFiltro, $params);
        $st = $this->db->prepare(
            "SELECT to_char(date_trunc('month', o.fecha_fin), 'MM-YYYY') AS mes,
                    COUNT(*) AS ordenes, COUNT(DISTINCT o.id_producto) AS productos, MIN(o.id_producto) AS id_producto,
                    CASE WHEN COUNT(DISTINCT o.id_producto) = 1 THEN SUM(o.cantidad_producida) END AS producido,
                    SUM(o.costo_materiales) AS costo_materiales, SUM(o.costo_mano_obra) AS costo_mano_obra,
                    SUM(o.costo_cif) AS costo_cif, SUM(o.valor_subproductos) AS valor_subproductos,
                    SUM(o.costo_merma) AS costo_merma, SUM(o.costo_total) AS costo_total,
                    CASE WHEN COUNT(DISTINCT o.id_producto) = 1 AND SUM(o.cantidad_producida) > 0
                         THEN SUM(o.costo_total) / SUM(o.cantidad_producida) END AS costo_unitario
               FROM produccion_ordenes o
              INNER JOIN productos pp ON pp.id = o.id_producto
              {$where}
              GROUP BY date_trunc('month', o.fecha_fin)
              ORDER BY date_trunc('month', o.fecha_fin)"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales para los KPIs. */
    public function resumen(int $idEmpresa, array $f, ?int $idUsuarioFiltro): array
    {
        $params = [];
        $where = $this->where($idEmpresa, $f, $idUsuarioFiltro, $params);
        $st = $this->db->prepare(
            "SELECT COUNT(*) AS ordenes, COALESCE(SUM(o.costo_total), 0) AS costo, COALESCE(SUM(o.costo_materiales), 0) AS materiales,
                    COALESCE(SUM(o.costo_mano_obra + o.costo_cif), 0) AS conversion, COALESCE(SUM(o.costo_merma), 0) AS merma
               FROM produccion_ordenes o INNER JOIN productos pp ON pp.id = o.id_producto {$where}"
        );
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
