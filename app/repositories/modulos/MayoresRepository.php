<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\Traits\DocumentoOrigenAsientoTrait;
use PDO;

class MayoresRepository
{
    use DocumentoOrigenAsientoTrait;

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
     * Tercero, documento y glosa se resuelven en cascada, porque no todas las líneas los
     * guardan (los asientos del sincronizador y los migrados llegan con esas columnas en
     * NULL, ver getSqlDocumentoOrigen()):
     *   - Tercero:   línea → documento origen del asiento.
     *   - Documento: línea → número del documento origen → número de comprobante del asiento.
     *   - Glosa:     línea → concepto del asiento.
     * El filtro por tercero usa la misma cascada, para que filtrar y ver den lo mismo.
     *
     * $filtros admite: fecha_inicio, fecha_fin, id_cuenta (ID exacto de la cuenta, opcional),
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

        if (!empty($filtros['id_cuenta'])) {
            $whereSql .= " AND pc.id = :id_cuenta";
            $params[':id_cuenta'] = (int) $filtros['id_cuenta'];
        }

        if (!empty($filtros['tipo_entidad']) && !empty($filtros['id_entidad'])) {
            // Misma cascada que la columna Tercero: la línea manda y, si no trae entidad,
            // vale la del documento origen. Si no, filtrar por cliente/proveedor dejaría
            // fuera movimientos que en pantalla sí muestran a ese tercero.
            $whereSql .= " AND COALESCE(ad.tipo_entidad, doc.tipo_entidad) = :tipo_entidad
                           AND COALESCE(ad.id_entidad, doc.id_entidad) = :id_entidad";
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

        $sql = $this->sqlMovimientos($whereSql, $this->sqlDocumentoOrigenAsiento());

        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Si en esta instalación falta alguna tabla o columna de documento, el mayor no
            // puede quedarse en blanco: se reintenta sin resolver el documento origen (manda
            // lo que traiga la línea del asiento). El JOIN neutro mantiene el SQL válido.
            error_log('Mayores: no se pudo resolver el documento origen del asiento. ' . $e->getMessage());
            $st = $this->db->prepare($this->sqlMovimientos($whereSql, self::$docOrigenNeutro));
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    /** Consulta de movimientos. `$docOrigen` es el JOIN que resuelve el documento origen. */
    private function sqlMovimientos(string $whereSql, string $docOrigen): string
    {
        return "SELECT
                    pc.id AS id_cuenta,
                    pc.codigo AS codigo_cuenta,
                    pc.nombre AS nombre_cuenta,
                    ac.id AS id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    COALESCE(NULLIF(ad.referencia_detalle, ''), ac.concepto) AS referencia_detalle,
                    COALESCE(NULLIF(ad.documento_referencia, ''), NULLIF(doc.numero_documento, ''), ac.numero_comprobante) AS documento_referencia,
                    ad.debe,
                    ad.haber,
                    COALESCE(ad.tipo_entidad, doc.tipo_entidad) AS tipo_entidad,
                    COALESCE(ad.id_entidad, doc.id_entidad) AS id_entidad,
                    COALESCE(cli.nombre, prov.razon_social, emp.nombres_apellidos) AS nombre_entidad,
                    doc.modulo_doc AS modulo_documento,
                    doc.id_doc AS id_documento
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN plan_cuentas pc ON ad.id_cuenta_contable = pc.id
                $docOrigen
                LEFT JOIN clientes cli ON COALESCE(ad.tipo_entidad, doc.tipo_entidad) = 'cliente' AND COALESCE(ad.id_entidad, doc.id_entidad) = cli.id
                LEFT JOIN proveedores prov ON COALESCE(ad.tipo_entidad, doc.tipo_entidad) = 'proveedor' AND COALESCE(ad.id_entidad, doc.id_entidad) = prov.id
                LEFT JOIN empleados emp ON COALESCE(ad.tipo_entidad, doc.tipo_entidad) = 'empleado' AND COALESCE(ad.id_entidad, doc.id_entidad) = emp.id
                $whereSql
                ORDER BY pc.codigo ASC, ac.fecha_asiento ASC, ac.id ASC";
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
