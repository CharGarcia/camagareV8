<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

/**
 * Acceso a datos de la Declaración de Impuesto a la Renta (reporte anual).
 *
 * Todo se calcula en vivo a partir de los documentos del ejercicio (ventas, notas de
 * crédito/débito de venta, compras con su marca "deducible", liquidaciones de compra y
 * retenciones de renta que le hicieron a la empresa). Las consultas filtran siempre por
 * empresa, eliminado = false y el ambiente (producción/pruebas) activo de la empresa.
 *
 * Nada se escribe en la base de datos: el módulo es un reporte.
 */
class DeclaracionRentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Datos de la empresa que definen qué formulario le corresponde (tipo, régimen, contabilidad). */
    public function getEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT e.id, e.ruc, e.nombre, e.nombre_comercial, e.direccion, e.tipo,
                       e.obligado_contabilidad, e.id_tipo_regimen, e.tipo_ambiente,
                       e.nom_rep_legal, e.ced_rep_legal, e.nombre_contador, e.ruc_contador,
                       te.nombre AS tipo_nombre, tr.nombre AS regimen_nombre
                FROM empresas e
                LEFT JOIN tipo_empresa te ON te.id = NULLIF(regexp_replace(COALESCE(e.tipo, ''), '[^0-9]', '', 'g'), '')::int
                LEFT JOIN tipo_regimen tr ON tr.id = e.id_tipo_regimen
                WHERE e.id = :id AND e.eliminado = false";
        $row = $this->query($sql, [':id' => $idEmpresa])->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Ambiente (1 producción / 2 pruebas) de cada empresa del grupo: [id => '1'|'2']. */
    public function getAmbientes(array $idsEmpresa): array
    {
        if (!$idsEmpresa) {
            return [];
        }
        $ids = implode(',', array_map('intval', $idsEmpresa));
        $out = [];
        foreach ($this->db->query("SELECT id, tipo_ambiente FROM empresas WHERE id IN ({$ids})") as $r) {
            $out[(int) $r['id']] = (string) ((int) ($r['tipo_ambiente'] ?? 1));
        }
        return $out;
    }

    /**
     * Años con movimientos (ventas, compras o asientos), de mayor a menor.
     */
    public function getAnios(int $idEmpresa, string $ambiente): array
    {
        $sql = "SELECT anio FROM (
                    SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                    FROM ventas_cabecera WHERE id_empresa = :e1 AND eliminado = false AND tipo_ambiente = :a1
                    UNION
                    SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int
                    FROM compras_cabecera WHERE id_empresa = :e2 AND eliminado = false AND tipo_ambiente = :a2
                    UNION
                    SELECT DISTINCT EXTRACT(YEAR FROM fecha_asiento)::int
                    FROM asientos_contables_cabecera WHERE id_empresa = :e3 AND eliminado = false AND tipo_ambiente = :a3
                ) t WHERE anio IS NOT NULL ORDER BY anio DESC";
        $rows = $this->query($sql, [
            ':e1' => $idEmpresa, ':a1' => $ambiente,
            ':e2' => $idEmpresa, ':a2' => $ambiente,
            ':e3' => $idEmpresa, ':a3' => $ambiente,
        ])->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    /**
     * Ventas del ejercicio: facturas autorizadas, notas de crédito y notas de débito.
     * Cada bloque trae cantidad, base (sin impuestos, neta de descuento), IVA y total.
     */
    public function getResumenVentas(int $idEmpresa, string $desde, string $hasta, string $ambiente): array
    {
        $p = [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente];

        $facturas = $this->query(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_sin_impuestos), 0) AS base,
                    COALESCE(SUM(importe_total), 0) AS total
             FROM ventas_cabecera
             WHERE id_empresa = :emp AND estado = 'autorizado' AND eliminado = false
               AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb", $p
        )->fetch(\PDO::FETCH_ASSOC);

        $notasCredito = $this->query(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_sin_impuestos), 0) AS base,
                    COALESCE(SUM(importe_total), 0) AS total
             FROM notas_credito_cabecera
             WHERE id_empresa = :emp AND estado = 'autorizado' AND eliminado = false
               AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb", $p
        )->fetch(\PDO::FETCH_ASSOC);

        $notasDebito = $this->query(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_sin_impuestos), 0) AS base,
                    COALESCE(SUM(importe_total), 0) AS total
             FROM nota_debito_cabecera
             WHERE id_empresa = :emp AND estado = 'autorizado' AND eliminado = false
               AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb", $p
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'facturas'      => $this->num($facturas),
            'notas_credito' => $this->num($notasCredito),
            'notas_debito'  => $this->num($notasDebito),
        ];
    }

    /**
     * Compras del ejercicio agrupadas por la marca "deducible" de cada compra y por tipo de
     * comprobante (01 factura, 04 nota de crédito, 05 nota de débito, otros).
     *
     * Devuelve: [deducible => [tipo_comprobante => ['cantidad','base','total']]]
     * donde deducible es 'declaracion_iva' (giro del negocio), 'gasto_personal' u 'otro'
     * (valores antiguos o vacíos, que se muestran aparte para que el usuario los revise).
     */
    public function getResumenCompras(int $idEmpresa, string $desde, string $hasta, string $ambiente): array
    {
        $sql = "SELECT CASE WHEN COALESCE(deducible, '') IN ('declaracion_iva', 'gasto_personal') THEN deducible ELSE 'otro' END AS deducible,
                       CASE WHEN COALESCE(tipo_comprobante, '') IN ('01', '04', '05') THEN tipo_comprobante ELSE 'otro' END AS tipo,
                       COUNT(*) AS cantidad,
                       COALESCE(SUM(total_sin_impuestos), 0) AS base,
                       COALESCE(SUM(importe_total), 0) AS total
                FROM compras_cabecera
                WHERE id_empresa = :emp AND eliminado = false
                  AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb
                GROUP BY 1, 2";
        $rows = $this->query($sql, [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente])
            ->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[$r['deducible']][$r['tipo']] = $this->num($r);
        }
        return $out;
    }

    /**
     * Gastos personales del ejercicio por rubro SRI (vivienda, salud, educacion, alimentacion,
     * vestimenta, turismo; 'sin_rubro' para las compras sin clasificar). Facturas y notas de
     * débito suman, notas de crédito restan. Base sin IVA y total con IVA.
     * Si la columna rubro_gasto_personal aún no existe, todo cae en 'sin_rubro'.
     *
     * @return array [rubro => ['cantidad' => n, 'base' => x, 'total' => y]]
     */
    public function getGastosPersonalesPorRubro(int $idEmpresa, string $desde, string $hasta, string $ambiente): array
    {
        $colRubro = $this->columnaExiste('compras_cabecera', 'rubro_gasto_personal')
            ? "COALESCE(NULLIF(rubro_gasto_personal, ''), 'sin_rubro')"
            : "'sin_rubro'";
        $signo = "CASE WHEN COALESCE(tipo_comprobante, '') = '04' THEN -1 ELSE 1 END";
        $sql = "SELECT {$colRubro} AS rubro,
                       COUNT(*) AS cantidad,
                       COALESCE(SUM({$signo} * total_sin_impuestos), 0) AS base,
                       COALESCE(SUM({$signo} * importe_total), 0) AS total
                FROM compras_cabecera
                WHERE id_empresa = :emp AND eliminado = false AND deducible = 'gasto_personal'
                  AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb
                GROUP BY 1";
        $out = [];
        foreach ($this->query($sql, [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente]) as $r) {
            $out[(string) $r['rubro']] = $this->num($r);
        }
        return $out;
    }

    /** Liquidaciones de compra autorizadas (siempre son gasto del giro del negocio). */
    public function getResumenLiquidaciones(int $idEmpresa, string $desde, string $hasta, string $ambiente): array
    {
        $row = $this->query(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_sin_impuestos), 0) AS base,
                    COALESCE(SUM(importe_total), 0) AS total
             FROM liquidaciones_cabecera
             WHERE id_empresa = :emp AND estado = 'autorizado' AND eliminado = false
               AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb",
            [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente]
        )->fetch(\PDO::FETCH_ASSOC);
        return $this->num($row);
    }

    /**
     * Retenciones de Impuesto a la Renta que los clientes le hicieron a la empresa en el
     * ejercicio (crédito tributario). Solo el impuesto renta (código 1 / RENTA), no IVA ni ISD.
     */
    public function getRetencionesRenta(int $idEmpresa, string $desde, string $hasta, string $ambiente): array
    {
        $row = $this->query(
            "SELECT COUNT(DISTINCT r.id) AS cantidad,
                    COALESCE(SUM(d.base_imponible), 0) AS base,
                    COALESCE(SUM(d.valor_retenido), 0) AS total
             FROM retencion_venta_cabecera r
             JOIN retencion_venta_detalle d ON d.id_retencion = r.id
             WHERE r.id_empresa = :emp AND r.eliminado = false
               AND r.fecha_emision BETWEEN :d AND :h AND r.tipo_ambiente = :amb
               AND UPPER(COALESCE(d.codigo_impuesto, '')) IN ('1', 'RENTA')",
            [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente]
        )->fetch(\PDO::FETCH_ASSOC);
        return $this->num($row);
    }

    /**
     * Detalle documento a documento de una fuente, para la pestaña "Detalle de documentos" y
     * la hoja de detalle del Excel. $fuente: ventas | notas_credito | notas_debito |
     * compras_negocio | compras_personal | compras_otro | liquidaciones | retenciones.
     */
    public function getDetalle(int $idEmpresa, string $desde, string $hasta, string $ambiente, string $fuente): array
    {
        $p = [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente];

        switch ($fuente) {
            case 'ventas':
                $sql = "SELECT v.fecha_emision, v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial AS numero,
                               c.nombre AS tercero, c.identificacion,
                               v.total_sin_impuestos AS base, v.importe_total AS total, '01' AS tipo
                        FROM ventas_cabecera v
                        LEFT JOIN clientes c ON c.id = v.id_cliente
                        WHERE v.id_empresa = :emp AND v.estado = 'autorizado' AND v.eliminado = false
                          AND v.fecha_emision BETWEEN :d AND :h AND v.tipo_ambiente = :amb
                        ORDER BY v.fecha_emision, v.id";
                break;
            case 'notas_credito':
                $sql = "SELECT n.fecha_emision, n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial AS numero,
                               c.nombre AS tercero, c.identificacion,
                               n.total_sin_impuestos AS base, n.importe_total AS total, '04' AS tipo
                        FROM notas_credito_cabecera n
                        LEFT JOIN clientes c ON c.id = n.id_cliente
                        WHERE n.id_empresa = :emp AND n.estado = 'autorizado' AND n.eliminado = false
                          AND n.fecha_emision BETWEEN :d AND :h AND n.tipo_ambiente = :amb
                        ORDER BY n.fecha_emision, n.id";
                break;
            case 'notas_debito':
                $sql = "SELECT n.fecha_emision, n.establecimiento || '-' || n.punto_emision || '-' || n.secuencial AS numero,
                               c.nombre AS tercero, c.identificacion,
                               n.total_sin_impuestos AS base, n.importe_total AS total, '05' AS tipo
                        FROM nota_debito_cabecera n
                        LEFT JOIN clientes c ON c.id = n.id_cliente
                        WHERE n.id_empresa = :emp AND n.estado = 'autorizado' AND n.eliminado = false
                          AND n.fecha_emision BETWEEN :d AND :h AND n.tipo_ambiente = :amb
                        ORDER BY n.fecha_emision, n.id";
                break;
            case 'compras_negocio':
            case 'compras_personal':
            case 'compras_otro':
                $filtro = match ($fuente) {
                    'compras_negocio'  => "AND COALESCE(c.deducible, '') = 'declaracion_iva'",
                    'compras_personal' => "AND COALESCE(c.deducible, '') = 'gasto_personal'",
                    default            => "AND COALESCE(c.deducible, '') NOT IN ('declaracion_iva', 'gasto_personal')",
                };
                $colRubro = $this->columnaExiste('compras_cabecera', 'rubro_gasto_personal')
                    ? "COALESCE(c.rubro_gasto_personal, '')"
                    : "''";
                $sql = "SELECT c.id, c.id_empresa, c.id_proveedor, c.fecha_emision,
                               COALESCE(c.establecimiento_prov, '') || '-' || COALESCE(c.punto_emision_prov, '') || '-' || COALESCE(c.secuencial_prov, '') AS numero,
                               p.razon_social AS tercero, p.identificacion,
                               c.total_sin_impuestos AS base, c.importe_total AS total,
                               COALESCE(c.tipo_comprobante, '') AS tipo, COALESCE(c.deducible, '') AS deducible,
                               {$colRubro} AS rubro
                        FROM compras_cabecera c
                        LEFT JOIN proveedores p ON p.id = c.id_proveedor
                        WHERE c.id_empresa = :emp AND c.eliminado = false
                          AND c.fecha_emision BETWEEN :d AND :h AND c.tipo_ambiente = :amb {$filtro}
                        ORDER BY c.fecha_emision, c.id";
                break;
            case 'liquidaciones':
                $sql = "SELECT l.fecha_emision, l.establecimiento || '-' || l.punto_emision || '-' || l.secuencial AS numero,
                               p.razon_social AS tercero, p.identificacion,
                               l.total_sin_impuestos AS base, l.importe_total AS total, '03' AS tipo
                        FROM liquidaciones_cabecera l
                        LEFT JOIN proveedores p ON p.id = l.id_proveedor
                        WHERE l.id_empresa = :emp AND l.estado = 'autorizado' AND l.eliminado = false
                          AND l.fecha_emision BETWEEN :d AND :h AND l.tipo_ambiente = :amb
                        ORDER BY l.fecha_emision, l.id";
                break;
            case 'retenciones':
                $sql = "SELECT r.fecha_emision, r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial AS numero,
                               c.nombre AS tercero, c.identificacion,
                               COALESCE(SUM(d.base_imponible), 0) AS base, COALESCE(SUM(d.valor_retenido), 0) AS total,
                               '07' AS tipo, STRING_AGG(DISTINCT d.codigo_retencion, ', ') AS codigos
                        FROM retencion_venta_cabecera r
                        JOIN retencion_venta_detalle d ON d.id_retencion = r.id
                        LEFT JOIN clientes c ON c.id = r.id_cliente
                        WHERE r.id_empresa = :emp AND r.eliminado = false
                          AND r.fecha_emision BETWEEN :d AND :h AND r.tipo_ambiente = :amb
                          AND UPPER(COALESCE(d.codigo_impuesto, '')) IN ('1', 'RENTA')
                        GROUP BY r.id, r.fecha_emision, r.establecimiento, r.punto_emision, r.secuencial, c.nombre, c.identificacion
                        ORDER BY r.fecha_emision, r.id";
                break;
            default:
                return [];
        }

        return $this->query($sql, $p)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Una compra de gasto personal (para validar antes de asignarle rubro). */
    public function getCompraGastoPersonal(int $idCompra, int $idEmpresa): ?array
    {
        $colRubro = $this->columnaExiste('compras_cabecera', 'rubro_gasto_personal') ? 'rubro_gasto_personal' : 'NULL AS rubro_gasto_personal';
        $row = $this->query(
            "SELECT id, id_empresa, id_proveedor, fecha_emision, tipo_ambiente, deducible, {$colRubro}
             FROM compras_cabecera WHERE id = :id AND id_empresa = :emp AND eliminado = false",
            [':id' => $idCompra, ':emp' => $idEmpresa]
        )->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Ids de las compras de gasto personal SIN rubro de un proveedor en el ejercicio (misma empresa). */
    public function getIdsGastoPersonalSinRubroProveedor(int $idEmpresa, int $idProveedor, string $desde, string $hasta, string $ambiente): array
    {
        if (!$this->columnaExiste('compras_cabecera', 'rubro_gasto_personal')) {
            return [];
        }
        $rows = $this->query(
            "SELECT id FROM compras_cabecera
             WHERE id_empresa = :emp AND id_proveedor = :prov AND eliminado = false
               AND deducible = 'gasto_personal' AND COALESCE(rubro_gasto_personal, '') = ''
               AND fecha_emision BETWEEN :d AND :h AND tipo_ambiente = :amb
             ORDER BY id",
            [':emp' => $idEmpresa, ':prov' => $idProveedor, ':d' => $desde, ':h' => $hasta, ':amb' => $ambiente]
        )->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    /** Normaliza una fila de totales a floats/ints. */
    private function num(?array $row): array
    {
        return [
            'cantidad' => (int) ($row['cantidad'] ?? 0),
            'base'     => round((float) ($row['base'] ?? 0), 2),
            'total'    => round((float) ($row['total'] ?? 0), 2),
        ];
    }
}
