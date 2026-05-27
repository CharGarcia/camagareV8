<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class DeclaracionIvaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('casilleros_declaracion_sri');
    }

    protected function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Obtiene facturas autorizadas en un periodo que tienen descuadres 
     * entre lo facturado y lo registrado en casilleros_declaracion_sri.
     */
    public function getDescuadresVentas(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        // Esta consulta busca facturas autorizadas donde la suma de impuestos de sus detalles
        // no coincide con la suma de valores registrados en casilleros_declaracion_sri.
        // También detecta facturas que no tienen NINGÚN registro en casilleros.
        
        $sql = "WITH impuestos_factura AS (
                    SELECT 
                        v.id AS id_venta,
                        v.establecimiento, v.punto_emision, v.secuencial, v.fecha_emision,
                        COALESCE(SUM(i.base_imponible), 0) AS total_bases_esperadas,
                        COALESCE(SUM(i.valor), 0) AS total_impuestos_esperados,
                        COUNT(i.id) AS num_items_impuestos
                    FROM ventas_cabecera v
                    JOIN ventas_detalle d ON v.id = d.id_venta
                    JOIN ventas_detalle_impuestos i ON d.id = i.id_venta_detalle
                    WHERE v.id_empresa = ? AND v.estado = 'autorizado' 
                      AND v.fecha_emision BETWEEN ? AND ?
                      AND v.eliminado = FALSE
                    GROUP BY v.id
                ),
                casilleros_factura AS (
                    SELECT 
                        id_origen AS id_venta,
                        COALESCE(SUM(valor), 0) AS total_registrado_casilleros
                    FROM casilleros_declaracion_sri
                    WHERE id_empresa = ? AND origen = 'facturas de venta'
                    GROUP BY id_origen
                )
                SELECT 
                    if.*,
                    COALESCE(cf.total_registrado_casilleros, 0) AS total_registrado_casilleros,
                    (if.total_bases_esperadas + if.total_impuestos_esperados) AS total_esperado_total
                FROM impuestos_factura if
                LEFT JOIN casilleros_factura cf ON if.id_venta = cf.id_venta
                WHERE ABS((if.total_bases_esperadas + if.total_impuestos_esperados) - COALESCE(cf.total_registrado_casilleros, 0)) > 0.01
                ORDER BY if.fecha_emision ASC";

        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Identifica qué tarifas de IVA o códigos de ICE están siendo usados en el periodo
     * pero NO tienen registros en la tabla de declaraciones.
     */
    public function getTarifasSinConfiguracion(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT DISTINCT i.codigo_impuesto, i.codigo_porcentaje, i.tarifa
                FROM ventas_cabecera v
                JOIN ventas_detalle d ON v.id = d.id_venta
                JOIN ventas_detalle_impuestos i ON d.id = i.id_venta_detalle
                WHERE v.id_empresa = ? AND v.estado = 'autorizado' 
                  AND v.fecha_emision BETWEEN ? AND ?
                  AND v.eliminado = FALSE
                  AND NOT EXISTS (
                      SELECT 1 FROM casilleros_declaracion_sri c
                      WHERE c.id_origen = v.id AND c.origen = 'facturas de venta'
                      -- Esta parte es simplificada, ideally checking specific mapping
                  )
                -- Nota: Esta consulta es orientativa para el auditor";
        // En una implementación más robusta, cruzaríamos con la tabla de mapeo de empresa
        // Pero por ahora buscaremos los que generan descuadre.
        return []; // Placeholder o refinado después
    }
    /**
     * Obtiene el resumen consolidado por casilleros para el periodo.
     */
    public function getResumenPorCasilleros(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT casillero, SUM(valor) as total
                FROM casilleros_declaracion_sri
                WHERE id_empresa = ? AND fecha BETWEEN ? AND ?
                GROUP BY casillero
                ORDER BY casillero ASC";
                
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $fechaDesde, $fechaHasta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
    /**
     * Obtiene los años que tienen registros de ventas para la empresa.
     */
    public function getAniosConVentas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision) as anio 
                FROM ventas_cabecera 
                WHERE id_empresa = ? AND eliminado = false 
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Obtiene todas las etiquetas oficiales y secciones para construir la estructura del PDF.
     */
    public function getEstructuraFormulario(): array
    {
        $sql = "SELECT * FROM sri_casilleros_etiquetas ORDER BY seccion ASC, orden ASC";
        return $this->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
