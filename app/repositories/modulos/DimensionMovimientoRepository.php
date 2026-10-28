<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AmbienteReporte;
use App\repositories\BaseRepository;
use PDO;

/**
 * Movimientos contables de un proyecto o de un centro de costo: las líneas de asiento que
 * llevan esa dimensión (asientos_contables_detalle.id_proyecto / id_centro_costo).
 *
 * Un documento queda «asociado» a un proyecto o centro de costo solo a través de su asiento:
 * ningún documento guarda la dimensión en su cabecera. Por eso todo sale de aquí.
 *
 * Es un reporte de solo lectura: solo asientos contabilizados de PRODUCCIÓN (§4,
 * AmbienteReporte), no eliminados ni anulados.
 */
class DimensionMovimientoRepository extends BaseRepository
{
    /** tipo => columna de la línea de asiento. */
    public const TIPOS = ['proyecto' => 'id_proyecto', 'centro' => 'id_centro_costo'];

    /** Tope de documentos que devuelve una consulta (la pestaña filtra por fechas). */
    public const MAX_DOCUMENTOS = 2000;

    public function __construct()
    {
        parent::__construct('asientos_contables_detalle');
    }

    /**
     * FROM y WHERE comunes: líneas de la dimensión, de asientos vivos y contabilizados de
     * producción, opcionalmente entre dos fechas (Y-m-d, inclusive). Devuelve las dos partes
     * por separado para que cada consulta agregue sus propios JOIN entre ellas.
     *
     * @return array{0: string, 1: string} [FROM …, WHERE …]
     */
    private function base(string $tipo, int $id, int $idEmpresa, ?string $desde, ?string $hasta, array &$params): array
    {
        $col = self::TIPOS[$tipo] ?? null;
        if ($col === null) {
            throw new \InvalidArgumentException('Tipo de dimensión no válido.');
        }
        $params = [':id_empresa' => $idEmpresa, ':id_dim' => $id];
        $sql = "FROM asientos_contables_detalle d
                JOIN asientos_contables_cabecera c ON c.id = d.id_asiento
                JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable";
        $where = "WHERE d.id_empresa = :id_empresa
                  AND d.{$col} = :id_dim
                  AND d.eliminado = false
                  AND c.id_empresa = d.id_empresa
                  AND c.eliminado = false
                  AND c.estado = 'contabilizado'
                  AND " . AmbienteReporte::condicion('c');
        if ($desde !== null) {
            $where .= " AND c.fecha_asiento >= CAST(:desde AS date)";
            $params[':desde'] = $desde;
        }
        if ($hasta !== null) {
            $where .= " AND c.fecha_asiento < CAST(:hasta AS date) + 1";
            $params[':hasta'] = $hasta;
        }
        return [$sql, $where];
    }

    /**
     * Un registro por asiento (= por documento) con la suma de SUS líneas de la dimensión.
     * El tercero y el número salen de las líneas; si las líneas no los traen, quedan vacíos.
     */
    public function documentos(string $tipo, int $id, int $idEmpresa, ?string $desde, ?string $hasta): array
    {
        $params = [];
        [$from, $where] = $this->base($tipo, $id, $idEmpresa, $desde, $hasta, $params);
        $sql = "SELECT c.id AS id_asiento, c.numero_comprobante, c.fecha_asiento, c.modulo_origen,
                       c.id_referencia_origen, c.concepto,
                       MAX(d.documento_referencia) AS documento,
                       MAX(COALESCE(cl.nombre, pv.razon_social, em.nombres_apellidos)) AS tercero,
                       string_agg(DISTINCT pc.codigo || ' ' || pc.nombre, ' | ') AS cuentas,
                       SUM(d.debe) AS debe, SUM(d.haber) AS haber
                {$from}
                LEFT JOIN clientes    cl ON d.tipo_entidad = 'cliente'   AND cl.id = d.id_entidad
                LEFT JOIN proveedores pv ON d.tipo_entidad = 'proveedor' AND pv.id = d.id_entidad
                LEFT JOIN empleados   em ON d.tipo_entidad = 'empleado'  AND em.id = d.id_entidad
                {$where}
                GROUP BY c.id, c.numero_comprobante, c.fecha_asiento, c.modulo_origen, c.id_referencia_origen, c.concepto
                ORDER BY c.fecha_asiento DESC, c.id DESC
                LIMIT " . (self::MAX_DOCUMENTOS + 1);
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Debe y haber por cuenta contable (todas las clases). */
    public function porCuenta(string $tipo, int $id, int $idEmpresa, ?string $desde, ?string $hasta): array
    {
        $params = [];
        [$from, $where] = $this->base($tipo, $id, $idEmpresa, $desde, $hasta, $params);
        $st = $this->db->prepare(
            "SELECT pc.id AS id_cuenta, pc.codigo, pc.nombre, SUM(d.debe) AS debe, SUM(d.haber) AS haber
             {$from}
             {$where}
             GROUP BY pc.id, pc.codigo, pc.nombre
             ORDER BY pc.codigo"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Por mes: ingresos (clase 4, haber − debe) y costos y gastos (clases 5 y 6, debe − haber).
     * Costo y gasto conviven en 5 o en 5/6 según el plan de cada empresa.
     */
    public function porMes(string $tipo, int $id, int $idEmpresa, ?string $desde, ?string $hasta): array
    {
        $params = [];
        [$from, $where] = $this->base($tipo, $id, $idEmpresa, $desde, $hasta, $params);
        $st = $this->db->prepare(
            "SELECT to_char(date_trunc('month', c.fecha_asiento), 'YYYY-MM') AS mes,
                    SUM(CASE WHEN left(pc.codigo, 1) = '4' THEN d.haber - d.debe ELSE 0 END) AS ingresos,
                    SUM(CASE WHEN left(pc.codigo, 1) IN ('5', '6') THEN d.debe - d.haber ELSE 0 END) AS costos_gastos,
                    COUNT(DISTINCT c.id) AS documentos
             {$from}
             {$where}
             GROUP BY 1
             ORDER BY 1"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dónde se usa un proyecto o centro de costo: [descripción => cantidad], solo lo que tenga
     * al menos un uso. Cuenta TODO lo vivo (cualquier ambiente y estado del asiento): un
     * asiento en pruebas o anulado también lo referencia. Las tablas opcionales (periodos del
     * plan de cuentas, presupuestos) se consultan solo si existen en esta instalación.
     */
    public function usos(string $tipo, int $id, int $idEmpresa): array
    {
        $col = self::TIPOS[$tipo] ?? null;
        if ($col === null) {
            throw new \InvalidArgumentException('Tipo de dimensión no válido.');
        }
        $consultas = [
            'líneas de asientos contables' =>
                "SELECT COUNT(*) FROM asientos_contables_detalle d
                 JOIN asientos_contables_cabecera c ON c.id = d.id_asiento AND c.eliminado = false
                 WHERE d.id_empresa = :id_empresa AND d.{$col} = :id AND d.eliminado = false",
            // Columna fija antigua del plan de cuentas (antes de los periodos).
            'cuentas del plan de cuentas' =>
                "SELECT COUNT(*) FROM plan_cuentas WHERE id_empresa = :id_empresa AND "
                . ($tipo === 'centro' ? 'id_centro_costos' : 'id_proyecto') . " = :id AND eliminado = false",
        ];
        if ($this->tablaExiste(PlanCuentaDimensionRepository::TABLA)) {
            $consultas['periodos de cuentas del plan de cuentas'] =
                "SELECT COUNT(*) FROM " . PlanCuentaDimensionRepository::TABLA . "
                 WHERE id_empresa = :id_empresa AND {$col} = :id AND eliminado = false";
        }
        if ($this->tablaExiste('presupuestos')) {
            $consultas['presupuestos'] =
                "SELECT COUNT(*) FROM presupuestos WHERE id_empresa = :id_empresa AND {$col} = :id AND eliminado = false";
        }

        $out = [];
        foreach ($consultas as $que => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa, ':id' => $id]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $out[$que] = $n;
            }
        }
        return $out;
    }

    /**
     * LEFT JOIN LATERAL que expone `{$alias}.ejecutado`: costos y gastos (clases 5 y 6,
     * debe − haber) de la fila `$aliasDim` (proyecto o centro de costo, con id e id_empresa).
     * Mismas condiciones que el resumen (producción, contabilizado, no eliminado), para que el
     * % del listado coincida con la pestaña Resumen. Sin parámetros: sirve igual en el COUNT
     * y en el SELECT de filas.
     */
    public static function sqlLateralEjecutado(string $tipo, string $aliasDim, string $alias = 'ej'): string
    {
        $col = self::TIPOS[$tipo] ?? null;
        if ($col === null) {
            throw new \InvalidArgumentException('Tipo de dimensión no válido.');
        }
        return "LEFT JOIN LATERAL (
                    SELECT SUM(dx.debe - dx.haber) AS ejecutado
                    FROM asientos_contables_detalle dx
                    JOIN asientos_contables_cabecera cx ON cx.id = dx.id_asiento
                    JOIN plan_cuentas px ON px.id = dx.id_cuenta_contable
                    WHERE dx.id_empresa = {$aliasDim}.id_empresa
                      AND dx.{$col} = {$aliasDim}.id
                      AND dx.eliminado = false
                      AND left(px.codigo, 1) IN ('5', '6')
                      AND cx.id_empresa = dx.id_empresa
                      AND cx.eliminado = false
                      AND cx.estado = 'contabilizado'
                      AND " . AmbienteReporte::condicion('cx') . "
                ) {$alias} ON true";
    }
}
