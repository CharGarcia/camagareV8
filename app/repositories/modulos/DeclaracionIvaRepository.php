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
     * Obtiene los IDs de los documentos de un periodo específico.
     */
    public function getDocumentosPeriodo(int $idEmpresa, string $tabla, string $fechaDesde, string $fechaHasta): array
    {
        // Parche en caliente para la columna tipo_ambiente y concepto
        $this->db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) DEFAULT '1'");
        $this->db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN IF NOT EXISTS concepto VARCHAR(255)");

        $estadoFilter = "AND estado = 'autorizado'";
        if ($tabla === 'compras_cabecera') {
            $estadoFilter = "AND COALESCE(deducible, '') = 'declaracion_iva'";
        } elseif ($tabla === 'retencion_venta_cabecera') {
            $estadoFilter = ""; // Estas tablas no tienen columna estado, solo nos guiamos por eliminado
        }

        $sql = "SELECT id FROM {$tabla} WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h {$estadoFilter} AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :emp)";
        return $this->query($sql, [':emp' => $idEmpresa, ':d' => $fechaDesde, ':h' => $fechaHasta])->fetchAll();
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
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                GROUP BY casillero
                ORDER BY casillero ASC";
                
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa]);
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
        // Secciones agrupadas: la posición de cada sección la define el menor
        // valor de 'orden' entre sus filas; dentro de la sección manda 'orden'.
        $sql = "SELECT e.*
                FROM sri_casilleros_etiquetas e
                JOIN (
                    SELECT seccion, MIN(orden) AS min_orden
                    FROM sri_casilleros_etiquetas
                    WHERE eliminado = false
                    GROUP BY seccion
                ) s ON s.seccion = e.seccion
                WHERE e.eliminado = false
                ORDER BY s.min_orden ASC, e.seccion ASC, e.orden ASC, e.id ASC";
        return $this->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Limpia los casilleros de un documento específico.
     */
    public function limpiarCasillerosDocumento(int $idEmpresa, string $origen, int $idOrigen): void
    {
        $sql = "DELETE FROM casilleros_declaracion_sri WHERE id_empresa = ? AND origen = ? AND id_origen = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $origen, $idOrigen]);
    }

    /**
     * Limpia los casilleros huérfanos (documentos eliminados o anulados)
     */
    public function limpiarCasillerosHuerfanos(int $idEmpresa, string $fechaDesde, string $fechaHasta): void
    {
        $sql = "DELETE FROM casilleros_declaracion_sri c
                WHERE c.id_empresa = ? AND c.fecha BETWEEN ? AND ?
                AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                AND (
                    (c.origen = 'facturas de venta' AND NOT EXISTS (SELECT 1 FROM ventas_cabecera v WHERE v.id = CAST(c.id_origen AS INTEGER) AND COALESCE(v.eliminado, false) = false AND v.estado = 'autorizado'))
                    OR
                    (c.origen = 'compras' AND NOT EXISTS (SELECT 1 FROM compras_cabecera com WHERE com.id = CAST(c.id_origen AS INTEGER) AND COALESCE(com.eliminado, false) = false AND COALESCE(com.deducible, '') = 'declaracion_iva'))
                    OR
                    (c.origen = 'liquidaciones_compras' AND NOT EXISTS (SELECT 1 FROM liquidaciones_cabecera l WHERE l.id = CAST(c.id_origen AS INTEGER) AND COALESCE(l.eliminado, false) = false AND l.estado = 'autorizado'))
                    OR
                    (c.origen = 'notas_credito' AND NOT EXISTS (SELECT 1 FROM notas_credito_cabecera nc WHERE nc.id = CAST(c.id_origen AS INTEGER) AND COALESCE(nc.eliminado, false) = false AND nc.estado = 'autorizado'))
                    OR
                    (c.origen = 'retenciones_compras' AND NOT EXISTS (SELECT 1 FROM retencion_compra_cabecera rc WHERE rc.id = CAST(c.id_origen AS INTEGER) AND COALESCE(rc.eliminado, false) = false AND rc.estado = 'autorizado'))
                    OR
                    (c.origen = 'retenciones_ventas' AND NOT EXISTS (SELECT 1 FROM retencion_venta_cabecera rv WHERE rv.id = CAST(c.id_origen AS INTEGER) AND COALESCE(rv.eliminado, false) = false))
                )";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa]);
    }

    /**
     * Inserta un valor en la tabla de casilleros de declaración.
     */
    public function insertarCasilleroDeclaracion(array $datos): void
    {
        $sql = "INSERT INTO casilleros_declaracion_sri (id_empresa, origen, id_origen, fecha, casillero, valor, concepto, tipo_ambiente)
                VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?))";
        $st = $this->db->prepare($sql);
        $st->execute([
            $datos['id_empresa'],
            $datos['origen'],
            $datos['id_origen'],
            $datos['fecha'],
            $datos['casillero'],
            $datos['valor'],
            $datos['concepto'] ?? null,
            $datos['id_empresa']
        ]);
    }

    /**
     * Obtiene los detalles de los documentos para la pestaña de edición de casilleros.
     */
    public function getDetalleDocumentos(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT c.id, c.origen, c.id_origen, c.fecha, c.casillero, c.valor, c.editado_manualmente, c.concepto,
                       COALESCE(v.establecimiento, com.establecimiento_prov, l.establecimiento, nc.establecimiento, rc.establecimiento, rv.establecimiento) AS establecimiento,
                       COALESCE(v.punto_emision, com.punto_emision_prov, l.punto_emision, nc.punto_emision, rc.punto_emision, rv.punto_emision) AS punto_emision,
                       COALESCE(v.secuencial, com.secuencial_prov, l.secuencial, nc.secuencial, rc.secuencial, rv.secuencial) AS secuencial,
                       COALESCE(cl_v.nombre, pr_com.razon_social, pr_com.nombre_comercial, pr_l.razon_social, pr_l.nombre_comercial, cl_nc.nombre, pr_rc.razon_social, pr_rc.nombre_comercial, cl_rv.nombre) AS entidad
                FROM casilleros_declaracion_sri c
                LEFT JOIN ventas_cabecera v ON c.id_origen = v.id AND c.origen = 'facturas de venta'
                LEFT JOIN clientes cl_v ON v.id_cliente = cl_v.id
                LEFT JOIN compras_cabecera com ON c.id_origen = com.id AND c.origen = 'compras'
                LEFT JOIN proveedores pr_com ON com.id_proveedor = pr_com.id
                LEFT JOIN liquidaciones_cabecera l ON c.id_origen = l.id AND c.origen = 'liquidaciones_compras'
                LEFT JOIN proveedores pr_l ON l.id_proveedor = pr_l.id
                LEFT JOIN notas_credito_cabecera nc ON c.id_origen = nc.id AND c.origen = 'notas_credito'
                LEFT JOIN clientes cl_nc ON nc.id_cliente = cl_nc.id
                LEFT JOIN retencion_compra_cabecera rc ON c.id_origen = rc.id AND c.origen = 'retenciones_compras'
                LEFT JOIN proveedores pr_rc ON rc.id_proveedor = pr_rc.id
                LEFT JOIN retencion_venta_cabecera rv ON c.id_origen = rv.id AND c.origen = 'retenciones_ventas'
                LEFT JOIN clientes cl_rv ON rv.id_cliente = cl_rv.id
                WHERE c.id_empresa = ? AND c.fecha BETWEEN ? AND ?
                AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                ORDER BY c.fecha ASC, c.origen ASC, c.id_origen ASC";
                
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza el casillero de una fila específica.
     */
    public function actualizarCasilleroManual(int $id, string $nuevoCasillero): void
    {
        $sql = "UPDATE casilleros_declaracion_sri SET casillero = ?, editado_manualmente = TRUE WHERE id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$nuevoCasillero, $id]);
    }

    /**
     * Cuenta documentos del período según la fuente configurada en la
     * estructura del formulario (filas con fuente_valor de tipo conteo).
     */
    public function getConteoDocumentos(int $idEmpresa, string $fuente, string $fechaDesde, string $fechaHasta): int
    {
        $filtroAmbiente = "AND CAST(tipo_ambiente AS VARCHAR) = (SELECT CAST(tipo_ambiente AS VARCHAR) FROM empresas WHERE id = :emp2)";

        $consultas = [
            'conteo_ventas_emitidas' =>
                "SELECT COUNT(*) FROM ventas_cabecera
                 WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h
                   AND estado = 'autorizado' AND eliminado = false {$filtroAmbiente}",
            'conteo_ventas_anuladas' =>
                "SELECT COUNT(*) FROM ventas_cabecera
                 WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h
                   AND estado = 'anulado' AND eliminado = false {$filtroAmbiente}",
            'conteo_compras_recibidas' =>
                "SELECT COUNT(*) FROM compras_cabecera
                 WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h
                   AND COALESCE(tipo_comprobante, '01') <> '02' AND eliminado = false {$filtroAmbiente}",
            'conteo_notas_venta_recibidas' =>
                "SELECT COUNT(*) FROM compras_cabecera
                 WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h
                   AND tipo_comprobante = '02' AND eliminado = false {$filtroAmbiente}",
            'conteo_liquidaciones_emitidas' =>
                "SELECT COUNT(*) FROM liquidaciones_cabecera
                 WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h
                   AND estado = 'autorizado' AND eliminado = false {$filtroAmbiente}",
        ];

        if (!isset($consultas[$fuente])) {
            return 0;
        }

        try {
            $st = $this->db->prepare($consultas[$fuente]);
            $st->execute([':emp' => $idEmpresa, ':d' => $fechaDesde, ':h' => $fechaHasta, ':emp2' => $idEmpresa]);
            return (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Calcula los componentes del IVA a pagar leyendo directamente de cada módulo
     * (no depende de la sincronización de casilleros). Filtra por el ambiente de la empresa.
     *
     *  - iva_ventas:                IVA de facturas de venta AUTORIZADAS (codigo_impuesto = 2)
     *  - iva_notas_credito:         IVA de notas de crédito de venta AUTORIZADAS (resta del IVA en ventas)
     *  - iva_compras:               IVA de compras deducible='declaracion_iva', excluyendo notas de crédito ('04')
     *  - iva_notas_credito_compra:  IVA de notas de crédito de compra ('04') (resta del crédito tributario)
     *  - retenciones:               IVA que le retuvieron en ventas (retencion_venta_cabecera.total_iva)
     *  - num_ventas:                cantidad de facturas de venta autorizadas en el período
     *
     * @return array{iva_ventas:float,iva_notas_credito:float,iva_compras:float,
     *               iva_notas_credito_compra:float,retenciones:float,num_ventas:int}
     */
    public function getResumenPagoDirecto(int $idEmpresa, string $fechaDesde, string $fechaHasta, string $ambiente): array
    {
        $p = [':emp' => $idEmpresa, ':d' => $fechaDesde, ':h' => $fechaHasta, ':amb' => $ambiente];

        $ivaVentas = (float) $this->query(
            "SELECT COALESCE(SUM(i.valor), 0)
             FROM ventas_cabecera v
             JOIN ventas_detalle d ON d.id_venta = v.id
             JOIN ventas_detalle_impuestos i ON i.id_venta_detalle = d.id
             WHERE v.id_empresa = :emp AND v.estado = 'autorizado' AND v.eliminado = false
               AND v.fecha_emision BETWEEN :d AND :h
               AND i.codigo_impuesto = '2' AND v.tipo_ambiente = :amb", $p)->fetchColumn();

        $ivaNotasCredito = (float) $this->query(
            "SELECT COALESCE(SUM(i.valor), 0)
             FROM notas_credito_cabecera nc
             JOIN notas_credito_detalle d ON d.id_nota_credito = nc.id
             JOIN notas_credito_detalle_impuestos i ON i.id_nota_credito_detalle = d.id
             WHERE nc.id_empresa = :emp AND nc.estado = 'autorizado' AND nc.eliminado = false
               AND nc.fecha_emision BETWEEN :d AND :h
               AND i.codigo_impuesto = '2' AND nc.tipo_ambiente = :amb", $p)->fetchColumn();

        $ivaCompras = (float) $this->query(
            "SELECT COALESCE(SUM(i.valor), 0)
             FROM compras_cabecera c
             JOIN compras_detalle d ON d.id_compra = c.id
             JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
             WHERE c.id_empresa = :emp AND c.deducible = 'declaracion_iva' AND c.eliminado = false
               AND c.fecha_emision BETWEEN :d AND :h
               AND i.codigo_impuesto = '2' AND c.tipo_ambiente = :amb
               AND COALESCE(c.tipo_comprobante, '01') <> '04'", $p)->fetchColumn();

        // Notas de crédito de compra (tipo_comprobante = '04'): reducen el crédito tributario.
        $ivaNotasCreditoCompra = (float) $this->query(
            "SELECT COALESCE(SUM(i.valor), 0)
             FROM compras_cabecera c
             JOIN compras_detalle d ON d.id_compra = c.id
             JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
             WHERE c.id_empresa = :emp AND c.deducible = 'declaracion_iva' AND c.eliminado = false
               AND c.fecha_emision BETWEEN :d AND :h
               AND i.codigo_impuesto = '2' AND c.tipo_ambiente = :amb
               AND c.tipo_comprobante = '04'", $p)->fetchColumn();

        $retenciones = (float) $this->query(
            "SELECT COALESCE(SUM(total_iva), 0)
             FROM retencion_venta_cabecera
             WHERE id_empresa = :emp AND eliminado = false
               AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb", $p)->fetchColumn();

        $numVentas = (int) $this->query(
            "SELECT COUNT(*)
             FROM ventas_cabecera v
             WHERE v.id_empresa = :emp AND v.estado = 'autorizado' AND v.eliminado = false
               AND v.fecha_emision BETWEEN :d AND :h AND v.tipo_ambiente = :amb", $p)->fetchColumn();

        return [
            'iva_ventas'               => $ivaVentas,
            'iva_notas_credito'        => $ivaNotasCredito,
            'iva_compras'              => $ivaCompras,
            'iva_notas_credito_compra' => $ivaNotasCreditoCompra,
            'retenciones'              => $retenciones,
            'num_ventas'               => $numVentas,
        ];
    }

    public function getMapaTarifasIva(): array
    {
        $stmt = $this->db->query("SELECT id, codigo FROM tarifa_iva");
        $mapa = [];
        if ($stmt) {
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $mapa[(string)$row['codigo']] = (string)$row['id'];
            }
        }
        return $mapa;
    }
}
