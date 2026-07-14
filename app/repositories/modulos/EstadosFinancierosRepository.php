<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use PDO;

class EstadosFinancierosRepository
{
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
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento BETWEEN :fecha_inicio AND :fecha_fin THEN ad.haber ELSE 0 END), 0) AS total_haber,
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento < :fecha_inicio THEN ad.debe ELSE 0 END), 0) AS inicial_debe,
                COALESCE(SUM(CASE WHEN ac.estado = 'contabilizado' AND ac.fecha_asiento < :fecha_inicio THEN ad.haber ELSE 0 END), 0) AS inicial_haber
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

        $sql = "SELECT 
                    ac.id as id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    ad.referencia_detalle,
                    ad.documento_referencia,
                    ad.debe,
                    ad.haber,
                    pc.codigo as codigo_cuenta
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN plan_cuentas pc ON ad.id_cuenta_contable = pc.id
                $whereSql
                ORDER BY ac.fecha_asiento ASC, ac.id ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
