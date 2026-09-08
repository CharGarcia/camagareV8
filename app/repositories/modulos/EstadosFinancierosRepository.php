<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\Traits\DocumentoOrigenAsientoTrait;
use PDO;

class EstadosFinancierosRepository
{
    use DocumentoOrigenAsientoTrait;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene los saldos agrupados por cuenta contable para un rango de fechas.
     * Solo considera asientos aprobados (estado = 'aprobado' o similar) y no eliminados.
     */
    public function getSaldos(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $params = [
            'id_empresa' => $idEmpresa,
            'fecha_inicio' => $fechaInicio . ' 00:00:00',
            'fecha_fin' => $fechaFin . ' 23:59:59'
        ];

        $centroCostoFilter = '';
        if ($idCentroCosto !== null) {
            $centroCostoFilter = " AND ad.id_centro_costo = :id_centro_costo";
            $params['id_centro_costo'] = $idCentroCosto;
        }

        $proyectoFilter = '';
        if ($idProyecto !== null) {
            $proyectoFilter = " AND ad.id_proyecto = :id_proyecto";
            $params['id_proyecto'] = $idProyecto;
        }

        // Asumimos que el estado de un asiento válido es 'APROBADO'
        $sql = "
            SELECT 
                pc.id AS id_cuenta,
                pc.codigo,
                pc.nombre,
                pc.nivel,
                pc.codigo_sri,
                pc.supercias_esf,
                pc.supercias_eri,
                pc.supercias_ecp_codigo,
                pc.supercias_ecp_subcodigo,
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN ad.debe ELSE 0 END), 0) AS total_debe,
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN ad.haber ELSE 0 END), 0) AS total_haber
            FROM plan_cuentas pc
            LEFT JOIN asientos_contables_detalle ad ON pc.id = ad.id_cuenta_contable AND ad.eliminado = false
                $centroCostoFilter
                $proyectoFilter
            LEFT JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id AND ac.eliminado = false AND ac.id_empresa = pc.id_empresa AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            WHERE pc.id_empresa = :id_empresa 
              AND pc.eliminado = false
            GROUP BY pc.id, pc.codigo, pc.nombre, pc.nivel, pc.codigo_sri, pc.supercias_esf, pc.supercias_eri, pc.supercias_ecp_codigo, pc.supercias_ecp_subcodigo
            ORDER BY pc.codigo ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuentas de PATRIMONIO (clase 3) con las dos cifras que necesita el Estado de Cambios en el
     * Patrimonio (ECP) de Supercías, con los mismos filtros que los reportes en pantalla (rango de
     * fechas, centro de costo, proyecto, solo asientos contabilizados del ambiente activo):
     *  - saldo_inicial: asientos de tipo 'apertura' dentro del rango (así registra este sistema el
     *    saldo con que arranca el período; ver getEstadoSituacionFinanciera).
     *  - movimiento: el resto de asientos del rango (cambios del año).
     * Ambos con signo acreedor (haber - debe), la naturaleza del patrimonio.
     */
    public function getMovimientosPatrimonioEcp(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $params = [
            'id_empresa'   => $idEmpresa,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
        ];
        $centroCostoFilter = '';
        if ($idCentroCosto !== null) {
            $centroCostoFilter = " AND ad.id_centro_costo = :id_centro_costo";
            $params['id_centro_costo'] = $idCentroCosto;
        }
        $proyectoFilter = '';
        if ($idProyecto !== null) {
            $proyectoFilter = " AND ad.id_proyecto = :id_proyecto";
            $params['id_proyecto'] = $idProyecto;
        }

        $sql = "
            SELECT
                pc.id AS id_cuenta,
                pc.codigo,
                pc.nombre,
                pc.nivel,
                pc.supercias_ecp_codigo,
                pc.supercias_ecp_subcodigo,
                COALESCE(SUM(CASE WHEN ac.id IS NOT NULL AND COALESCE(ac.tipo_comprobante, '') = 'apertura'
                                  THEN ad.haber - ad.debe ELSE 0 END), 0) AS saldo_inicial,
                COALESCE(SUM(CASE WHEN ac.id IS NOT NULL AND COALESCE(ac.tipo_comprobante, '') <> 'apertura'
                                  THEN ad.haber - ad.debe ELSE 0 END), 0) AS movimiento
            FROM plan_cuentas pc
            LEFT JOIN asientos_contables_detalle ad ON pc.id = ad.id_cuenta_contable AND ad.eliminado = false
                $centroCostoFilter
                $proyectoFilter
            LEFT JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                AND ac.eliminado = false
                AND ac.estado = 'contabilizado'
                AND ac.id_empresa = pc.id_empresa
                AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin
                AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            WHERE pc.id_empresa = :id_empresa
              AND pc.eliminado = false
              AND pc.codigo LIKE '3%'
            GROUP BY pc.id, pc.codigo, pc.nombre, pc.nivel, pc.supercias_ecp_codigo, pc.supercias_ecp_subcodigo
            ORDER BY pc.codigo ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Todas las cuentas de nivel 5 con saldo de apertura (asientos tipo 'apertura' del rango) y
     * movimiento del resto del rango, ambos con signo DEUDOR (debe - haber). Mismos filtros que los
     * reportes en pantalla. Base del Estado de Flujos de Efectivo (efectivo inicial, variaciones
     * de capital de trabajo).
     */
    public function getAperturaYMovimientoPorCuenta(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $params = ['id_empresa' => $idEmpresa, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin];
        $centroCostoFilter = '';
        if ($idCentroCosto !== null) {
            $centroCostoFilter = " AND ad.id_centro_costo = :id_centro_costo";
            $params['id_centro_costo'] = $idCentroCosto;
        }
        $proyectoFilter = '';
        if ($idProyecto !== null) {
            $proyectoFilter = " AND ad.id_proyecto = :id_proyecto";
            $params['id_proyecto'] = $idProyecto;
        }

        $sql = "
            SELECT
                pc.id AS id_cuenta, pc.codigo, pc.nombre, pc.nivel, pc.supercias_esf, pc.supercias_eri,
                COALESCE(SUM(CASE WHEN ac.id IS NOT NULL AND COALESCE(ac.tipo_comprobante, '') = 'apertura'
                                  THEN ad.debe - ad.haber ELSE 0 END), 0) AS apertura,
                COALESCE(SUM(CASE WHEN ac.id IS NOT NULL AND COALESCE(ac.tipo_comprobante, '') <> 'apertura'
                                  THEN ad.debe - ad.haber ELSE 0 END), 0) AS movimiento
            FROM plan_cuentas pc
            LEFT JOIN asientos_contables_detalle ad ON pc.id = ad.id_cuenta_contable AND ad.eliminado = false
                $centroCostoFilter
                $proyectoFilter
            LEFT JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                AND ac.eliminado = false
                AND ac.estado = 'contabilizado'
                AND ac.id_empresa = pc.id_empresa
                AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin
                AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            WHERE pc.id_empresa = :id_empresa
              AND pc.eliminado = false
            GROUP BY pc.id, pc.codigo, pc.nombre, pc.nivel, pc.supercias_esf, pc.supercias_eri
            ORDER BY pc.codigo ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Líneas de los asientos contabilizados del rango (sin apertura) que tocan al menos una cuenta
     * de EFECTIVO (ESF 10101xx), con los datos de la cabecera y el mapeo ESF/ERI de cada cuenta.
     * Base del método directo del Estado de Flujos de Efectivo.
     */
    public function getLineasAsientosConEfectivo(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $params = ['id_empresa' => $idEmpresa, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin];
        $centroCostoFilter = '';
        if ($idCentroCosto !== null) {
            $centroCostoFilter = " AND ad.id_centro_costo = :id_centro_costo";
            $params['id_centro_costo'] = $idCentroCosto;
        }
        $proyectoFilter = '';
        if ($idProyecto !== null) {
            $proyectoFilter = " AND ad.id_proyecto = :id_proyecto";
            $params['id_proyecto'] = $idProyecto;
        }

        $sql = "
            SELECT
                ac.id AS id_asiento, ac.fecha_asiento, ac.modulo_origen, ac.tipo_comprobante, ac.concepto,
                pc.id AS id_cuenta, pc.codigo, pc.nombre, pc.supercias_esf, pc.supercias_eri,
                ad.debe, ad.haber
            FROM asientos_contables_cabecera ac
            JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
                $centroCostoFilter
                $proyectoFilter
            JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
            WHERE ac.id_empresa = :id_empresa
              AND ac.eliminado = false
              AND ac.estado = 'contabilizado'
              AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin
              AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
              AND COALESCE(ac.tipo_comprobante, '') <> 'apertura'
              AND EXISTS (
                    SELECT 1
                    FROM asientos_contables_detalle x
                    JOIN plan_cuentas p2 ON p2.id = x.id_cuenta_contable
                    WHERE x.id_asiento = ac.id AND x.eliminado = false
                      AND p2.supercias_esf LIKE '10101%'
              )
            ORDER BY ac.fecha_asiento ASC, ac.id ASC, ad.id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Cuentas de nivel 5 CON movimiento contabilizado en el rango (mismos filtros de pantalla), con
     * su mapeo Supercías, el nombre de su cuenta padre (para sugerir casilleros) y el neto del año.
     * Base del diagnóstico Supercías.
     */
    public function getCuentasConMovimientoParaDiagnostico(int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $sql = "
            SELECT pc.id AS id_cuenta, pc.codigo, pc.nombre, pc.nivel,
                   pc.codigo_sri, pc.supercias_esf, pc.supercias_eri, pc.supercias_ecp_codigo, pc.supercias_ecp_subcodigo,
                   (SELECT p4.nombre FROM plan_cuentas p4
                     WHERE p4.id_empresa = pc.id_empresa AND p4.eliminado = false
                       AND p4.codigo = substring(pc.codigo from '^(.*)\\.[^.]+$')
                     LIMIT 1) AS nombre_padre,
                   SUM(ad.debe) AS debe, SUM(ad.haber) AS haber,
                   SUM(CASE WHEN COALESCE(ac.tipo_comprobante, '') = 'apertura' THEN 1 ELSE 0 END) AS lineas_apertura
            FROM plan_cuentas pc
            JOIN asientos_contables_detalle ad ON ad.id_cuenta_contable = pc.id AND ad.eliminado = false
            JOIN asientos_contables_cabecera ac ON ac.id = ad.id_asiento
                AND ac.eliminado = false
                AND ac.estado = 'contabilizado'
                AND ac.id_empresa = pc.id_empresa
                AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin
                AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            WHERE pc.id_empresa = :id_empresa
              AND pc.eliminado = false
            GROUP BY pc.id, pc.codigo, pc.nombre, pc.nivel, pc.codigo_sri, pc.supercias_esf, pc.supercias_eri,
                     pc.supercias_ecp_codigo, pc.supercias_ecp_subcodigo, pc.id_empresa
            ORDER BY pc.codigo ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_empresa' => $idEmpresa, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Asientos contabilizados en la fecha de inicio del rango que NO son de tipo 'apertura' pero
     * parecen saldos iniciales: solo cuentas de balance (1, 2, 3) y varias líneas. Diagnóstico.
     */
    public function getAsientosAperturaSospechosos(int $idEmpresa, string $fechaInicio): array
    {
        $sql = "
            SELECT ac.id, ac.fecha_asiento, ac.tipo_comprobante, ac.concepto,
                   COUNT(ad.id) AS lineas, SUM(ad.debe) AS total_debe
            FROM asientos_contables_cabecera ac
            JOIN asientos_contables_detalle ad ON ad.id_asiento = ac.id AND ad.eliminado = false
            JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
            WHERE ac.id_empresa = :id_empresa
              AND ac.eliminado = false
              AND ac.estado = 'contabilizado'
              AND ac.fecha_asiento = :fecha_inicio
              AND COALESCE(ac.tipo_comprobante, '') <> 'apertura'
              AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            GROUP BY ac.id, ac.fecha_asiento, ac.tipo_comprobante, ac.concepto
            HAVING COUNT(ad.id) >= 4
               AND SUM(CASE WHEN pc.codigo ~ '^[4567]' THEN 1 ELSE 0 END) = 0
            ORDER BY ac.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_empresa' => $idEmpresa, 'fecha_inicio' => $fechaInicio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Casilleros de la estructura Supercías por tipo: codigo => formula (o '' si no tiene). */
    public function getCasillerosEstructura(string $tipo): array
    {
        $st = $this->db->prepare("SELECT codigo, COALESCE(formula, '') AS formula FROM supercias_estructuras WHERE tipo = :tipo AND eliminado = false AND COALESCE(subcodigo, '') = ''");
        $st->execute([':tipo' => $tipo]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(string) $r['codigo']] = trim((string) $r['formula']);
        }
        return $out;
    }

    /**
     * Catálogo de cuentas de la empresa (todas, sin filtrar por movimiento). Base para armar
     * la matriz cuenta × periodo del reporte "por periodos" (getSaldosPorPeriodo).
     */
    public function getPlanCuentas(int $idEmpresa): array
    {
        $sql = "SELECT id AS id_cuenta, codigo, nombre, nivel, codigo_sri,
                       supercias_esf, supercias_eri, supercias_ecp_codigo, supercias_ecp_subcodigo
                FROM plan_cuentas
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY codigo ASC";
        $st = $this->db->prepare($sql);
        $st->execute(['id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Movimientos (debe/haber) agrupados por cuenta y por mes (YYYY-MM), para los reportes
     * "por periodos" (Estado de Resultados / Situación Financiera horizontal por mes). A
     * diferencia de getSaldos(), no trae una fila por cuenta sin movimiento: el llamador
     * combina esto con getPlanCuentas() para completar el universo de cuentas y rellenar
     * con cero los periodos sin movimiento.
     */
    public function getSaldosPorPeriodo(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $params = [
            'id_empresa' => $idEmpresa,
            'fecha_inicio' => $fechaInicio . ' 00:00:00',
            'fecha_fin' => $fechaFin . ' 23:59:59'
        ];

        $centroCostoFilter = '';
        if ($idCentroCosto !== null) {
            $centroCostoFilter = " AND ad.id_centro_costo = :id_centro_costo";
            $params['id_centro_costo'] = $idCentroCosto;
        }

        $proyectoFilter = '';
        if ($idProyecto !== null) {
            $proyectoFilter = " AND ad.id_proyecto = :id_proyecto";
            $params['id_proyecto'] = $idProyecto;
        }

        $sql = "
            SELECT
                ad.id_cuenta_contable AS id_cuenta,
                to_char(date_trunc('month', ac.fecha_asiento), 'YYYY-MM') AS periodo,
                COALESCE(SUM(ad.debe), 0) AS total_debe,
                COALESCE(SUM(ad.haber), 0) AS total_haber
            FROM asientos_contables_detalle ad
            INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                AND ac.eliminado = false
                AND ac.estado = 'contabilizado'
                AND ac.id_empresa = :id_empresa
                AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin
                AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            WHERE ad.eliminado = false
              $centroCostoFilter
              $proyectoFilter
            GROUP BY ad.id_cuenta_contable, periodo
            ORDER BY periodo ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuentas configuradas del tipo "Cierre del Ejercicio" (modulos/configuracion_contable):
     * cuenta de Utilidad y cuenta de Pérdida (ambas de patrimonio) donde el Balance muestra el
     * resultado según el signo. Devuelve ['utilidad' => [...]|null, 'perdida' => [...]|null].
     */
    public function getCuentasCierreEjercicio(int $idEmpresa): array
    {
        $sql = "SELECT at.codigo AS slot, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre, pc.id AS id_cuenta
                FROM asientos_tipo at
                JOIN asientos_programados ap
                  ON ap.id_asiento_tipo = at.id AND ap.id_empresa = :emp
                 AND ap.id_referencia = at.id
                 AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
                 AND ap.eliminado = false
                JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE at.tipo_asiento = 'cierre_ejercicio' AND at.eliminado = false
                  AND at.codigo IN ('UTILIDADEJERCICIOCIERRE', 'PERDIDAEJERCICIOCIERRE')";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa]);
        $res = ['utilidad' => null, 'perdida' => null];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['slot'] === 'UTILIDADEJERCICIOCIERRE' ? 'utilidad' : 'perdida';
            $res[$key] = ['codigo' => $r['cuenta_codigo'], 'nombre' => $r['cuenta_nombre'], 'id' => (int) $r['id_cuenta']];
        }
        return $res;
    }

    /**
     * Obtiene los años distintos en los que existen asientos contables aprobados para la empresa.
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT extract(year from fecha_asiento) as anio 
                FROM asientos_contables_cabecera 
                WHERE id_empresa = :id_empresa AND eliminado = false 
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute(['id_empresa' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function getCentrosCostoActivos(int $idEmpresa): array
    {
        $sql = "SELECT id, codigo, nombre FROM centro_costos WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo' ORDER BY codigo ASC";
        $st = $this->db->prepare($sql);
        $st->execute(['id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProyectosActivos(int $idEmpresa): array
    {
        $sql = "SELECT id, codigo, nombre FROM proyectos WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo' ORDER BY codigo ASC";
        $st = $this->db->prepare($sql);
        $st->execute(['id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMayorAuxiliar(
        int $idEmpresa,
        string $codigoCuenta,
        string $fechaInicio,
        string $fechaFin,
        ?int $idCentroCosto = null,
        ?int $idProyecto = null
    ): array {
        $whereSql = "WHERE ac.id_empresa = :id_empresa 
                     AND ac.estado = 'contabilizado' 
                     AND ac.eliminado = false 
                     AND ad.eliminado = false 
                     AND ac.fecha_asiento >= :f_inicio 
                     AND ac.fecha_asiento <= :f_fin 
                     AND pc.codigo LIKE :codigo_cuenta
                     AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $params = [
            ':id_empresa' => $idEmpresa,
            ':f_inicio' => $fechaInicio,
            ':f_fin' => $fechaFin,
            ':codigo_cuenta' => $codigoCuenta . '%'
        ];

        if ($idCentroCosto) {
            $whereSql .= " AND ad.id_centro_costo = :id_centro_costo";
            $params[':id_centro_costo'] = $idCentroCosto;
        }

        if ($idProyecto) {
            $whereSql .= " AND ad.id_proyecto = :id_proyecto";
            $params[':id_proyecto'] = $idProyecto;
        }

        $sql = $this->sqlMayorAuxiliar($whereSql, $this->sqlDocumentoOrigenAsiento());

        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Igual que en Mayores: si falta alguna tabla o columna de documento en esta
            // instalación, se muestra el auxiliar con lo que traiga la línea del asiento.
            error_log('Estados Financieros: no se pudo resolver el documento origen del asiento. ' . $e->getMessage());
            $st = $this->db->prepare($this->sqlMayorAuxiliar($whereSql, self::$docOrigenNeutro));
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    /**
     * Consulta del mayor auxiliar. Documento y glosa se resuelven en cascada (línea → documento
     * origen → cabecera del asiento) porque no todas las líneas los guardan; ver el trait.
     */
    private function sqlMayorAuxiliar(string $whereSql, string $docOrigen): string
    {
        return "SELECT
                    ac.id as id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    COALESCE(NULLIF(ad.referencia_detalle, ''), ac.concepto) AS referencia_detalle,
                    COALESCE(NULLIF(ad.documento_referencia, ''), NULLIF(doc.numero_documento, ''), ac.numero_comprobante) AS documento_referencia,
                    ad.debe,
                    ad.haber,
                    pc.codigo as codigo_cuenta,
                    doc.modulo_doc AS modulo_documento,
                    doc.id_doc AS id_documento
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN plan_cuentas pc ON ad.id_cuenta_contable = pc.id
                $docOrigen
                $whereSql
                ORDER BY ac.fecha_asiento ASC, ac.id ASC";
    }
}
