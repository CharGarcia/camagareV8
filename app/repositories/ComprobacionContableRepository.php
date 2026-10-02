<?php
declare(strict_types=1);

namespace App\repositories;

use PDO;

/**
 * Motor común de la "Comprobación con Contabilidad": compara el saldo que lleva un módulo
 * (inventario según kardex, cartera por cobrar, cartera por pagar…) con el saldo de sus
 * cuentas contables, al inicio y al fin de un período, y cruza documento por documento lo
 * que cada lado movió para mostrar dónde se descuadra. Solo lectura.
 *
 * Es la generalización de la comprobación de Control Bancario
 * (ControlBancarioRepository::sqlCruceContable). Cada módulo aporta una DEFINICIÓN (ver
 * los métodos definicionComprobacionContable() de su repositorio):
 *
 *   'conceptos' string[] códigos de asientos_tipo cuyas cuentas (en cualquier nivel de la
 *                       cascada) forman el saldo contable del módulo; 'patron_concepto'
 *                       agrega los códigos que contengan ese texto. El Service las resuelve
 *                       y deja los ids en 'cuentas'. 'conceptos_texto': cómo se llaman en
 *                       Configuración Contable (para el aviso de "sin cuentas").
 *   'cuentas_ids' int[] en lugar de 'conceptos': cuentas fijas (p. ej. las de una forma de
 *                       pago o de las opciones de anticipo), ya resueltas por el módulo.
 *   'cuentas'   int[]   cuentas contables del módulo (ids de plan_cuentas).
 *   'signo'     1 | -1  1 = cuenta deudora (activo: saldo = debe − haber);
 *                       −1 = acreedora (pasivo: saldo = haber − debe).
 *   'ctes'      string  CTE auxiliares opcionales ("x AS (...), y AS (...)"), sin coma final.
 *   'docs'      string  SELECT del lado documento, una fila por movimiento con las columnas
 *                       tipo (varchar), id_doc (int), fecha (date), monto (numeric, con el
 *                       signo con que afecta el saldo del módulo).
 *   'nativos'   array   modulo_origen del asiento => tipo del documento. El asiento se
 *                       enlaza por (tipo, id_referencia_origen).
 *   'migrados'  string  SELECT opcional (id_asiento, tipo, id_doc) para asientos enlazados
 *                       desde el documento (id_asiento_contable), p. ej. los migrados.
 *   'apertura'  bool    si true, los asientos de apertura cuentan como la partida
 *                       ('saldo_inicial', 0), contra los saldos iniciales del módulo.
 *   'numeros'   array   tipo => [tabla, expresión sobre el alias x] con el número a mostrar
 *                       (p. ej. ['factura_venta' => ['ventas_cabecera', self::NUM_SERIE]]).
 *   'params'    array   parámetros extra de 'ctes'/'docs'/'migrados'.
 *
 * Placeholders reservados: :e (empresa) y los de fechas (:fi*, :ff*). Los CTE disponibles
 * para las definiciones son `amb` (columna t = tipo_ambiente de la empresa).
 */
class ComprobacionContableRepository extends BaseRepository
{
    /** Número EEE-PPP-SSSSSSSSS de las tablas con establecimiento / punto_emision / secuencial. */
    public const NUM_SERIE = "CONCAT(x.establecimiento, '-', x.punto_emision, '-', x.secuencial)";

    public function __construct()
    {
        parent::__construct('asientos_contables_cabecera');
    }

    /**
     * Cuentas contables configuradas para los conceptos indicados (códigos de asientos_tipo),
     * en CUALQUIER nivel de la cascada (General, Cliente/Proveedor, Producto, Categoría…):
     * mismo criterio que AsientoBuilderService::cuentasDelSlot().
     *
     * @param string[] $codigos  códigos exactos.
     * @param string|null $patron además, códigos que contengan este texto (ILIKE).
     * @return array<int,array{id:int,codigo:string,nombre:string}>
     */
    public function getCuentasPorConceptos(int $idEmpresa, array $codigos, ?string $patron = null): array
    {
        $params = [':e' => $idEmpresa];
        $cond = [];
        if ($codigos) {
            $marcas = [];
            foreach (array_values($codigos) as $i => $c) {
                $marcas[] = ":c{$i}";
                $params[":c{$i}"] = (string) $c;
            }
            $cond[] = 'at.codigo IN (' . implode(', ', $marcas) . ')';
        }
        if ($patron !== null && $patron !== '') {
            $cond[] = 'at.codigo ILIKE :patron';
            $params[':patron'] = '%' . $patron . '%';
        }
        if (!$cond) {
            return [];
        }

        $sql = "SELECT DISTINCT pc.id, pc.codigo, pc.nombre
                FROM asientos_programados ap
                JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                JOIN plan_cuentas pc ON pc.id = ap.id_cuenta AND pc.id_empresa = ap.id_empresa
                WHERE ap.id_empresa = :e AND ap.eliminado = FALSE AND ap.id_cuenta IS NOT NULL
                  AND (" . implode(' OR ', $cond) . ")
                ORDER BY pc.codigo";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'codigo' => (string) $r['codigo'], 'nombre' => (string) $r['nombre'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Código y nombre de cuentas dadas por id (definiciones con cuentas fijas, p. ej. la de
     * una forma de pago).
     * @return array<int,array{id:int,codigo:string,nombre:string}>
     */
    public function getCuentasPorIds(int $idEmpresa, array $ids): array
    {
        $st = $this->db->prepare("SELECT id, codigo, nombre FROM plan_cuentas
                                  WHERE id_empresa = :e AND id IN ({$this->inEnteros($ids)}) ORDER BY codigo");
        $st->execute([':e' => $idEmpresa]);
        return array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'codigo' => (string) $r['codigo'], 'nombre' => (string) $r['nombre'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Lista SQL de ids enteros (ya validados) para un IN. */
    private function inEnteros(array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids ? implode(', ', $ids) : '0';
    }

    /** Literal SQL seguro (solo para valores de las definiciones, nunca de la petición). */
    private function literal(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }

    /**
     * CTE completos hasta `j`: una fila por documento (o por asiento sin documento) con su
     * monto y fecha en cada lado.
     */
    private function sqlCruce(array $def): string
    {
        $cuentas = $this->inEnteros($def['cuentas'] ?? []);
        $signo = ((int) ($def['signo'] ?? 1)) < 0 ? -1 : 1;
        $ctes = trim((string) ($def['ctes'] ?? ''));
        $migrados = trim((string) ($def['migrados'] ?? ''));

        // Enlace nativo: modulo_origen → tipo (literales de la definición).
        $casosTipo = [];
        $modulos = [];
        foreach (($def['nativos'] ?? []) as $modulo => $tipo) {
            $casosTipo[] = 'WHEN ' . $this->literal((string) $modulo) . ' THEN ' . $this->literal((string) $tipo);
            $modulos[] = $this->literal((string) $modulo);
        }
        $condNativo = $modulos
            ? 'ac.modulo_origen IN (' . implode(', ', $modulos) . ') AND ac.id_referencia_origen IS NOT NULL'
            : 'FALSE';
        $tipoNativo = $casosTipo ? 'CASE ac.modulo_origen ' . implode(' ', $casosTipo) . ' END' : 'NULL';
        $condApertura = !empty($def['apertura']) ? "LOWER(ac.tipo_comprobante) = 'apertura'" : 'FALSE';

        $cteMigrados = $migrados !== ''
            ? "m AS (SELECT DISTINCT ON (id_asiento) id_asiento, tipo::VARCHAR AS tipo, id_doc FROM ({$migrados}) mm
                     WHERE id_asiento IS NOT NULL ORDER BY id_asiento, tipo, id_doc),"
            : "m AS (SELECT NULL::INT AS id_asiento, NULL::VARCHAR AS tipo, NULL::INT AS id_doc WHERE FALSE),";

        return "WITH amb AS (
                    SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e
                ),
                " . ($ctes !== '' ? $ctes . ',' : '') . "
                {$cteMigrados}
                d AS (
                    SELECT dd.tipo::VARCHAR AS tipo, dd.id_doc::INT AS id_doc,
                           MIN(dd.fecha)::DATE AS fecha, SUM(dd.monto) AS monto
                    FROM ({$def['docs']}) dd
                    GROUP BY 1, 2
                ),
                ka AS (
                    SELECT CASE WHEN {$condNativo} THEN {$tipoNativo}
                                WHEN m.id_asiento IS NOT NULL THEN m.tipo
                                WHEN {$condApertura} THEN 'saldo_inicial'
                                ELSE 'asiento' END::VARCHAR AS tipo,
                           CASE WHEN {$condNativo} THEN ac.id_referencia_origen
                                WHEN m.id_asiento IS NOT NULL THEN m.id_doc
                                WHEN {$condApertura} THEN 0
                                ELSE ac.id END AS id_doc,
                           ac.id AS id_asiento, ac.fecha_asiento, ac.numero_comprobante, ac.concepto,
                           {$signo} * (ad.debe - ad.haber) AS monto
                    FROM asientos_contables_detalle ad
                    JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
                    LEFT JOIN m ON m.id_asiento = ac.id
                    WHERE ac.id_empresa = :e AND ac.estado = 'contabilizado'
                      AND ac.eliminado = FALSE AND ad.eliminado = FALSE
                      AND ac.tipo_ambiente = (SELECT t FROM amb)
                      AND ad.id_cuenta_contable IN ({$cuentas})
                ),
                k AS (
                    SELECT tipo, id_doc,
                           MIN(fecha_asiento)::DATE AS fecha,
                           SUM(monto) AS monto,
                           MIN(id_asiento) AS id_asiento,
                           COUNT(DISTINCT id_asiento) AS n_asientos,
                           STRING_AGG(DISTINCT numero_comprobante, ', ') AS numero_asiento,
                           MIN(concepto) AS concepto
                    FROM ka
                    GROUP BY 1, 2
                ),
                j AS (
                    SELECT COALESCE(d.tipo, k.tipo) AS tipo,
                           COALESCE(d.id_doc, k.id_doc) AS id_doc,
                           d.fecha AS fecha_doc, d.monto AS monto_doc,
                           k.fecha AS fecha_asiento, k.monto AS monto_asiento,
                           k.id_asiento, k.n_asientos, k.numero_asiento, k.concepto
                    FROM d
                    FULL OUTER JOIN k ON k.tipo = d.tipo AND k.id_doc = d.id_doc
                )";
    }

    /**
     * Totales de cada lado antes del período y hasta su fin.
     * @return array{doc_ini:float,doc_fin:float,cont_ini:float,cont_fin:float}
     */
    public function getTotales(array $def, int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $sql = $this->sqlCruce($def) . "
                SELECT COALESCE(SUM(monto_doc)     FILTER (WHERE fecha_doc     <  :fi1), 0) AS doc_ini,
                       COALESCE(SUM(monto_doc)     FILTER (WHERE fecha_doc     <= :ff1), 0) AS doc_fin,
                       COALESCE(SUM(monto_asiento) FILTER (WHERE fecha_asiento <  :fi2), 0) AS cont_ini,
                       COALESCE(SUM(monto_asiento) FILTER (WHERE fecha_asiento <= :ff2), 0) AS cont_fin
                FROM j";
        $st = $this->db->prepare($sql);
        $st->execute(($def['params'] ?? []) + [
            ':e' => $idEmpresa,
            ':fi1' => $fechaInicio, ':ff1' => $fechaFin, ':fi2' => $fechaInicio, ':ff2' => $fechaFin,
        ]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('floatval', $r + ['doc_ini' => 0, 'doc_fin' => 0, 'cont_ini' => 0, 'cont_fin' => 0]);
    }

    /**
     * Movimientos DEL PERÍODO de los dos lados, cruzados documento por documento, en orden de
     * fecha. Por cada uno: efecto_doc / efecto_contable (lo que suma dentro del rango en cada
     * lado), diferencia y clase:
     *  - cuadra:            sin diferencia.
     *  - solo_documento:    el documento no tiene asiento en las cuentas del módulo.
     *  - solo_contabilidad: asiento sin documento detrás (manual, migrado sin enlace…).
     *  - fuera_modulo:      asiento de un documento que el módulo no cuenta (anulado,
     *                       borrador, eliminado, o que no afecta este saldo).
     *  - monto_distinto:    los dos lados mueven montos distintos.
     *  - fecha_distinta:    los montos coinciden, pero las fechas caen en períodos distintos.
     */
    public function getPartidas(array $def, int $idEmpresa, string $fechaInicio, string $fechaFin, int $limite = 3000): array
    {
        // Número del documento: una subconsulta por tipo, solo sobre las filas del período.
        $casosNumero = [];
        foreach (($def['numeros'] ?? []) as $tipo => [$tabla, $expr]) {
            if (!preg_match('/^[a-z_]+$/', (string) $tabla)) {
                continue;
            }
            $casosNumero[] = 'WHEN ' . $this->literal((string) $tipo)
                . " THEN (SELECT {$expr} FROM {$tabla} x WHERE x.id = p.id_doc AND x.id_empresa = :e LIMIT 1)";
        }
        $numero = $casosNumero ? 'CASE p.tipo ' . implode(' ', $casosNumero) . ' END' : 'NULL';

        $sql = $this->sqlCruce($def) . ",
                p AS (
                    SELECT j.*,
                           CASE WHEN fecha_doc BETWEEN :fi1 AND :ff1 THEN monto_doc ELSE 0 END AS efecto_doc,
                           CASE WHEN fecha_asiento BETWEEN :fi2 AND :ff2 THEN monto_asiento ELSE 0 END AS efecto_contable,
                           COALESCE(CASE WHEN fecha_doc BETWEEN :fi5 AND :ff5 THEN fecha_doc END, fecha_asiento) AS fecha_orden
                    FROM j
                    WHERE (fecha_doc BETWEEN :fi3 AND :ff3 OR fecha_asiento BETWEEN :fi4 AND :ff4)
                      -- Documento que en neto no mueve nada y sin nada en contabilidad (p. ej. una
                      -- transferencia entre bodegas): no aporta y solo llenaría la lista.
                      AND NOT (ABS(COALESCE(monto_doc, 0)) < 0.005 AND ABS(COALESCE(monto_asiento, 0)) < 0.005)
                )
                SELECT p.tipo, p.id_doc, p.fecha_doc, p.monto_doc, p.fecha_asiento, p.monto_asiento,
                       p.id_asiento, p.n_asientos, p.numero_asiento, p.concepto,
                       p.efecto_doc, p.efecto_contable, p.fecha_orden,
                       CASE WHEN p.tipo IN ('asiento', 'saldo_inicial') THEN NULL ELSE ({$numero}) END AS numero,
                       p.efecto_doc - p.efecto_contable AS diferencia,
                       CASE WHEN ABS(p.efecto_doc - p.efecto_contable) <= 0.005 THEN 'cuadra'
                            WHEN p.monto_asiento IS NULL THEN 'solo_documento'
                            WHEN p.monto_doc IS NULL AND p.tipo = 'asiento' THEN 'solo_contabilidad'
                            WHEN p.monto_doc IS NULL THEN 'fuera_modulo'
                            WHEN ABS(p.monto_doc - p.monto_asiento) > 0.005 THEN 'monto_distinto'
                            ELSE 'fecha_distinta' END AS clase
                FROM p
                ORDER BY p.fecha_orden, p.tipo, p.id_doc
                LIMIT " . max(1, $limite);
        $st = $this->db->prepare($sql);
        $st->execute(($def['params'] ?? []) + [
            ':e' => $idEmpresa,
            ':fi1' => $fechaInicio, ':ff1' => $fechaFin, ':fi2' => $fechaInicio, ':ff2' => $fechaFin,
            ':fi3' => $fechaInicio, ':ff3' => $fechaFin, ':fi4' => $fechaInicio, ':ff4' => $fechaFin,
            ':fi5' => $fechaInicio, ':ff5' => $fechaFin,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Saldo contable de cada cuenta del módulo al inicio y al fin del período (con el signo
     * de la definición), para mostrar qué cuenta aporta cada parte del total.
     */
    public function getSaldosPorCuenta(array $def, int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $cuentas = $this->inEnteros($def['cuentas'] ?? []);
        $signo = ((int) ($def['signo'] ?? 1)) < 0 ? -1 : 1;
        $sql = "SELECT pc.id, pc.codigo, pc.nombre,
                       COALESCE(SUM({$signo} * (ad.debe - ad.haber)) FILTER (WHERE ac.fecha_asiento <  :fi), 0) AS saldo_ini,
                       COALESCE(SUM({$signo} * (ad.debe - ad.haber)) FILTER (WHERE ac.fecha_asiento <= :ff), 0) AS saldo_fin
                FROM plan_cuentas pc
                LEFT JOIN asientos_contables_detalle ad
                       ON ad.id_cuenta_contable = pc.id AND ad.eliminado = FALSE
                LEFT JOIN asientos_contables_cabecera ac
                       ON ac.id = ad.id_asiento AND ac.id_empresa = :e1 AND ac.estado = 'contabilizado'
                      AND ac.eliminado = FALSE
                      AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)
                WHERE pc.id_empresa = :e3 AND pc.id IN ({$cuentas})
                GROUP BY pc.id, pc.codigo, pc.nombre
                ORDER BY pc.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':e1' => $idEmpresa, ':e2' => $idEmpresa, ':e3' => $idEmpresa, ':fi' => $fechaInicio, ':ff' => $fechaFin]);
        return array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'codigo' => $r['codigo'], 'nombre' => $r['nombre'],
            'saldo_ini' => round((float) $r['saldo_ini'], 2), 'saldo_fin' => round((float) $r['saldo_fin'], 2),
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}
