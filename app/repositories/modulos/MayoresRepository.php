<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use PDO;

class MayoresRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Movimientos de mayorización: solo cuentas de movimiento (nivel 5), con datos del
     * tercero (cliente/proveedor/empleado) resueltos por id_entidad/tipo_entidad. Mismos
     * filtros de estado/eliminado/tipo_ambiente que el resto de reportes contables.
     *
     * $filtros admite: fecha_inicio, fecha_fin, codigo_cuenta (prefijo, opcional),
     * tipo_entidad + id_entidad (opcional), id_centro_costo, id_proyecto (opcional).
     */
    public function getMovimientos(int $idEmpresa, array $filtros): array
    {
        $whereSql = "WHERE ac.id_empresa = :id_empresa
                     AND ac.estado = 'contabilizado'
                     AND ac.eliminado = false
                     AND ad.eliminado = false
                     AND ac.fecha_asiento >= :f_inicio
                     AND ac.fecha_asiento <= :f_fin
                     AND pc.nivel = '5'
                     AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $params = [
            ':id_empresa' => $idEmpresa,
            ':f_inicio' => $filtros['fecha_inicio'],
            ':f_fin' => $filtros['fecha_fin'],
        ];

        if (!empty($filtros['codigo_cuenta'])) {
            $whereSql .= " AND pc.codigo LIKE :codigo_cuenta";
            $params[':codigo_cuenta'] = $filtros['codigo_cuenta'] . '%';
        }

        if (!empty($filtros['tipo_entidad']) && !empty($filtros['id_entidad'])) {
            $whereSql .= " AND ad.tipo_entidad = :tipo_entidad AND ad.id_entidad = :id_entidad";
            $params[':tipo_entidad'] = $filtros['tipo_entidad'];
            $params[':id_entidad'] = (int) $filtros['id_entidad'];
        }

        if (!empty($filtros['id_centro_costo'])) {
            $whereSql .= " AND ad.id_centro_costo = :id_centro_costo";
            $params[':id_centro_costo'] = (int) $filtros['id_centro_costo'];
        }

        if (!empty($filtros['id_proyecto'])) {
            $whereSql .= " AND ad.id_proyecto = :id_proyecto";
            $params[':id_proyecto'] = (int) $filtros['id_proyecto'];
        }

        $sql = "SELECT
                    pc.id AS id_cuenta,
                    pc.codigo AS codigo_cuenta,
                    pc.nombre AS nombre_cuenta,
                    ac.id AS id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    ad.referencia_detalle,
                    ad.documento_referencia,
                    ad.debe,
                    ad.haber,
                    ad.tipo_entidad,
                    ad.id_entidad,
                    COALESCE(cli.nombre, prov.razon_social, emp.nombres_apellidos) AS nombre_entidad
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN plan_cuentas pc ON ad.id_cuenta_contable = pc.id
                LEFT JOIN clientes cli ON ad.tipo_entidad = 'cliente' AND ad.id_entidad = cli.id
                LEFT JOIN proveedores prov ON ad.tipo_entidad = 'proveedor' AND ad.id_entidad = prov.id
                LEFT JOIN empleados emp ON ad.tipo_entidad = 'empleado' AND ad.id_entidad = emp.id
                $whereSql
                ORDER BY pc.codigo ASC, ac.fecha_asiento ASC, ac.id ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

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
}
