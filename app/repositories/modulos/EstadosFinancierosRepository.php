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
        try {
            $this->db->exec("ALTER TABLE asientos_contables_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) DEFAULT '1'");
        } catch (\Throwable $e) {}
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
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN ad.debe ELSE 0 END), 0) AS total_debe,
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN ad.haber ELSE 0 END), 0) AS total_haber
            FROM plan_cuentas pc
            LEFT JOIN asientos_contables_detalle ad ON pc.id = ad.id_cuenta_contable AND ad.eliminado = false
                $centroCostoFilter
                $proyectoFilter
            LEFT JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id AND ac.eliminado = false AND ac.id_empresa = pc.id_empresa AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
            WHERE pc.id_empresa = :id_empresa 
              AND pc.eliminado = false
            GROUP BY pc.id, pc.codigo, pc.nombre, pc.nivel
            ORDER BY pc.codigo ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Catálogo de cuentas de la empresa (todas, sin filtrar por movimiento). Base para armar
     * la matriz cuenta × periodo del reporte "por periodos" (getSaldosPorPeriodo).
     */
    public function getPlanCuentas(int $idEmpresa): array
    {
        $sql = "SELECT id AS id_cuenta, codigo, nombre, nivel, codigo_sri
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
