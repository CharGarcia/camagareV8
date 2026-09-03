<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ReporteComprasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('compras_cabecera');
    }

    /**
     * Expresión de signo por tipo de comprobante para netear las compras.
     * Solo en la vista "Todas las compras" (sin filtro de tipo) las Notas de
     * Crédito de compra (04) RESTAN; si se filtra por un tipo específico, cada
     * documento se muestra con su valor natural (signo = 1).
     */
    private function signoNc(array $filtros): string
    {
        if (!empty($filtros['tipo_comprobante'])) {
            return "1";
        }
        return "CASE WHEN c.tipo_comprobante = '04' THEN -1 ELSE 1 END";
    }

    /**
     * Obtiene los años disponibles con compras registradas para la empresa.
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision) as anio
                FROM compras_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
    }

    /**
     * CTE de bases e impuestos para compras.
     * Se une contra la cabecera y se filtra por id_empresa: sin este JOIN, el GROUP BY
     * agregaba el detalle de TODOS los documentos del sistema (todas las empresas) en
     * cada consulta del reporte — el costo crecía con el tamaño de la BD completa, no
     * con los datos de la empresa activa (mismo problema encontrado y corregido en
     * ReporteVentasRepository). Requiere que el llamador incluya :id_empresa en sus
     * params (ya lo hace vía buildWhereYParams).
     */
    private function getCteBasesImpuestos(): string
    {
        return "
            SELECT
                d.id_compra,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(i.valor) as valor_iva
            FROM compras_detalle d
            JOIN compras_cabecera cbc ON cbc.id = d.id_compra AND cbc.id_empresa = :id_empresa
            LEFT JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
            GROUP BY d.id_compra
        ";
    }

    /**
     * Construye condiciones WHERE y parámetros desde los filtros.
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasVenta, string $aliasDetalle = null): array
    {
        $where = "{$aliasVenta}.id_empresa = :id_empresa
                  AND {$aliasVenta}.eliminado = false
                  AND {$aliasVenta}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$aliasVenta}.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$aliasVenta}.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_proveedor'])) {
            $proveedores = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : [$filtros['id_proveedor']];
            $inNames = [];
            foreach ($proveedores as $i => $id) {
                $pName = ":prov$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            $where .= " AND {$aliasVenta}.id_proveedor IN (" . implode(',', $inNames) . ")";
        }
        if (!empty($filtros['id_producto'])) {
            $productos = is_array($filtros['id_producto']) ? $filtros['id_producto'] : [$filtros['id_producto']];
            $inNames = [];
            foreach ($productos as $i => $id) {
                $pName = ":prod$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            if ($aliasDetalle) {
                $where .= " AND {$aliasDetalle}.id_producto IN (" . implode(',', $inNames) . ")";
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM compras_detalle cd WHERE cd.id_compra = {$aliasVenta}.id AND cd.id_producto IN (" . implode(',', $inNames) . "))";
            }
        }
        if (!empty($filtros['tipo_comprobante'])) {
            $where .= " AND {$aliasVenta}.tipo_comprobante = :tipo_comprobante";
            $params[':tipo_comprobante'] = $filtros['tipo_comprobante'];
        }

        // Filtro por Producto = texto de los ítems de las compras (descripción o código de línea)
        if (!empty($filtros['producto_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM compras_detalle cdp
                WHERE cdp.id_compra = {$aliasVenta}.id
                  AND (cdp.descripcion ILIKE :prodtxt OR cdp.codigo_principal ILIKE :prodtxt)
            )";
            $params[':prodtxt'] = '%' . trim($filtros['producto_texto']) . '%';
        }

        // Filtro por Información Adicional del documento (campos adicionales nombre/valor)
        if (!empty($filtros['buscar_info'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM compras_adicional ca
                WHERE ca.id_compra = {$aliasVenta}.id
                  AND (ca.nombre ILIKE :info OR ca.valor ILIKE :info)
            )";
            $params[':info'] = '%' . trim($filtros['buscar_info']) . '%';
        }

        return [$where, $params];
    }

    /**
     * Reporte detallado (por comprobante).
     */
    public function getReporteDetallado(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'c');
        $sgn = $this->signoNc($filtros);

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                c.id,
                c.fecha_emision,
                c.fecha_registro,
                CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) as numero_documento,
                p.identificacion  as proveedor_ruc,
                p.razon_social    as proveedor_nombre,
                COALESCE(ca.comprobante, c.tipo_comprobante) as tipo_comprobante_nombre,
                COALESCE(usr.nombre, '') as usuario_nombre,
                COALESCE(c.numero_autorizacion, '') as numero_autorizacion,
                (COALESCE(b.base_0, 0)    * {$sgn}) as base_0,
                (COALESCE(b.base_iva, 0)  * {$sgn}) as base_iva,
                (COALESCE(b.valor_iva, 0) * {$sgn}) as valor_iva,
                (c.importe_total          * {$sgn}) as total,
                COALESCE((
                    SELECT SUM(r.total_retenido)
                    FROM retencion_compra_cabecera r
                    WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada'
                ), 0) as retenciones
            FROM compras_cabecera c
            JOIN proveedores p ON p.id = c.id_proveedor
            LEFT JOIN bases b ON b.id_compra = c.id
            LEFT JOIN usuarios usr ON usr.id = c.id_usuario
            LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
            WHERE {$where}
            ORDER BY c.fecha_emision DESC, c.secuencial_prov DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por proveedor.
     */
    public function getReporteAgrupadoProveedor(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'c');
        $sgn = $this->signoNc($filtros);

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                p.id as id_proveedor,
                p.identificacion  as proveedor_ruc,
                p.razon_social    as proveedor_nombre,
                COUNT(c.id) as cantidad_comprobantes,
                SUM(COALESCE(b.base_0, 0)   * {$sgn}) as base_0,
                SUM(COALESCE(b.base_iva, 0) * {$sgn}) as base_iva,
                SUM(COALESCE(b.valor_iva, 0) * {$sgn}) as valor_iva,
                SUM(c.importe_total * {$sgn}) as total
            FROM compras_cabecera c
            JOIN proveedores p ON p.id = c.id_proveedor
            LEFT JOIN bases b ON b.id_compra = c.id
            WHERE {$where}
            GROUP BY p.id, p.identificacion, p.razon_social
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por producto.
     */
    public function getReporteAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'c', 'd');
        $sgn = $this->signoNc($filtros);

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(prod.codigo, '') as producto_codigo,
                COALESCE(prod.nombre, d.descripcion) as producto_nombre,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad * {$sgn}) as cantidad_comprada,
                SUM((CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) * {$sgn}) as base_0,
                SUM((CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) * {$sgn}) as base_iva,
                SUM(COALESCE(i.valor, 0) * {$sgn}) as valor_iva,
                SUM((d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) * {$sgn}) as total
            FROM compras_detalle d
            JOIN compras_cabecera c ON c.id = d.id_compra
            LEFT JOIN productos prod ON prod.id = d.id_producto
            LEFT JOIN compras_detalle_impuestos i ON i.id_compra_detalle = d.id
            WHERE {$where}
            GROUP BY d.id_producto, prod.codigo, COALESCE(prod.nombre, d.descripcion), COALESCE(i.tarifa, 0)
            ORDER BY cantidad_comprada DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por fecha.
     */
    public function getReporteAgrupadoFecha(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'c');
        $sgn = $this->signoNc($filtros);

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                c.fecha_emision as fecha,
                COUNT(c.id) as cantidad_comprobantes,
                SUM(COALESCE(b.base_0, 0)   * {$sgn}) as base_0,
                SUM(COALESCE(b.base_iva, 0) * {$sgn}) as base_iva,
                SUM(COALESCE(b.valor_iva, 0) * {$sgn}) as valor_iva,
                SUM(c.importe_total * {$sgn}) as total
            FROM compras_cabecera c
            LEFT JOIN bases b ON b.id_compra = c.id
            WHERE {$where}
            GROUP BY c.fecha_emision
            ORDER BY c.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por mes (año-mes).
     */
    public function getReporteAgrupadoMes(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'c');
        $sgn = $this->signoNc($filtros);

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                TO_CHAR(c.fecha_emision, 'YYYY-MM') as mes,
                COUNT(c.id) as cantidad_comprobantes,
                SUM(COALESCE(b.base_0, 0)   * {$sgn}) as base_0,
                SUM(COALESCE(b.base_iva, 0) * {$sgn}) as base_iva,
                SUM(COALESCE(b.valor_iva, 0) * {$sgn}) as valor_iva,
                SUM(c.importe_total * {$sgn}) as total
            FROM compras_cabecera c
            LEFT JOIN bases b ON b.id_compra = c.id
            WHERE {$where}
            GROUP BY TO_CHAR(c.fecha_emision, 'YYYY-MM')
            ORDER BY mes DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Autocompletado: descripciones distintas de los ítems de compra (compras_detalle).
     */
    public function buscarItems(int $idEmpresa, string $q, int $limit = 15): array
    {
        // Busca por nombre (descripción) o por código de línea, igual que el filtro
        // producto_texto del reporte (ver buildWhereYParams: descripcion OR codigo_principal).
        $sql = "SELECT DISTINCT TRIM(d.descripcion) AS descripcion, TRIM(COALESCE(d.codigo_principal, '')) AS codigo
                FROM compras_detalle d
                JOIN compras_cabecera c ON c.id = d.id_compra
                WHERE c.id_empresa = :ie AND c.eliminado = false
                  AND d.descripcion IS NOT NULL AND TRIM(d.descripcion) <> ''
                  AND (d.descripcion ILIKE :q OR d.codigo_principal ILIKE :q)
                ORDER BY descripcion
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // valor = lo que queda escrito en el buscador al elegir: siempre el nombre
        // (descripción), igual que si el usuario lo hubiera tecleado a mano — así el
        // filtro producto_texto (que ya busca por descripcion OR codigo_principal)
        // encuentra TODAS las líneas con ese nombre, no solo las que comparten el
        // código exacto de la línea elegida (un mismo producto puede aparecer con
        // código vacío o distinto entre documentos). El código solo se muestra como
        // referencia (sub) en la lista, para que el usuario pueda buscar por código
        // sin que eso reduzca el resultado a una sola variante.
        return array_map(fn($r) => [
            'valor' => $r['descripcion'],
            'label' => $r['descripcion'],
            'sub'   => $r['codigo'],
        ], $rows);
    }

    /**
     * Autocompletado: info adicional (nombre/valor distintos de compras_adicional).
     */
    public function buscarInfoAdicional(int $idEmpresa, string $q, int $limit = 15): array
    {
        $sql = "SELECT DISTINCT ca.nombre, ca.valor
                FROM compras_adicional ca
                JOIN compras_cabecera c ON c.id = ca.id_compra
                WHERE c.id_empresa = :ie AND c.eliminado = false
                  AND COALESCE(ca.valor, '') <> ''
                  AND (ca.nombre ILIKE :q OR ca.valor ILIKE :q)
                ORDER BY ca.nombre, ca.valor
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => [
            'valor' => $r['valor'],
            'label' => $r['valor'],
            'sub'   => $r['nombre'],
        ], $rows);
    }

    /**
     * Estadísticas globales para el rango de fechas.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'c');
        $sgn = $this->signoNc($filtros);

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos() . ")
            SELECT
                SUM(COALESCE(b.base_0, 0)    * {$sgn}) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)  * {$sgn}) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0) * {$sgn}) as total_iva,
                SUM(c.importe_total          * {$sgn}) as gran_total,
                COUNT(c.id)                    as total_documentos
            FROM compras_cabecera c
            LEFT JOIN bases b ON b.id_compra = c.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_base_0'     => (float)($row['total_base_0']  ?? 0),
            'total_base_iva'   => (float)($row['total_base_iva'] ?? 0),
            'total_iva'        => (float)($row['total_iva']      ?? 0),
            'gran_total'       => (float)($row['gran_total']     ?? 0),
            'total_documentos' => (int)($row['total_documentos'] ?? 0),
        ];
    }

    /**
     * Tipos de comprobante para el selector "Tipo de Documento" del reporte:
     * SOLO los que tengan compras registradas en la empresa y ambiente activo
     * (facturas 01, notas de crédito 04, notas de venta 02, etc.). Un tipo sin
     * compras no se ofrece. El nombre sale sin los espacios/saltos de línea
     * que arrastran algunos registros del catálogo.
     */
    public function getTiposComprobante(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT c.tipo_comprobante,
                       BTRIM(COALESCE(ca.comprobante, c.tipo_comprobante), E' \\t\\r\\n') as nombre
                FROM compras_cabecera c
                LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                  AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY c.tipo_comprobante";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
