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
     *   - Documento: línea → número del documento origen. Si el asiento no tiene documento
     *                (diario manual, apertura, migrados sin documento) queda VACÍO: antes caía
     *                al número de comprobante y la columna repetía lo mismo que Comprobante.
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
                     AND ac.tipo_ambiente = '2'";

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
                    ad.id AS id_linea,
                    pc.id AS id_cuenta,
                    pc.codigo AS codigo_cuenta,
                    pc.nombre AS nombre_cuenta,
                    ac.id AS id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    COALESCE(NULLIF(ad.referencia_detalle, ''), ac.concepto) AS referencia_detalle,
                    COALESCE(NULLIF(ad.documento_referencia, ''), doc.numero_documento) AS documento_referencia,
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

    /**
     * Documentos cuyas líneas se pueden desglosar en el mayor: ingresos y egresos, cuyo asiento
     * suma por cuenta las líneas del documento (varias facturas cobradas, varios «otros
     * conceptos» al mismo gasto…). Tabla de detalle, FK, cabecera y columna del monto.
     */
    public const DESGLOSE_DOCUMENTOS = [
        'egreso'  => ['detalle' => 'egresos_detalle',  'fk' => 'id_egreso',  'cabecera' => 'egresos_cabecera',  'monto' => 'monto_pagado',  'eliminado' => true],
        'ingreso' => ['detalle' => 'ingresos_detalle', 'fk' => 'id_ingreso', 'cabecera' => 'ingresos_cabecera', 'monto' => 'monto_cobrado', 'eliminado' => false],
    ];

    /**
     * Cuántas líneas (con monto) tiene cada documento: id_documento => n. Solo se ofrece el
     * desglose en los documentos con dos o más líneas; con una sola no hay nada que abrir.
     *
     * @param int[] $ids
     * @return array<int,int>
     */
    public function contarLineasDocumentos(int $idEmpresa, string $modulo, array $ids): array
    {
        $cfg = self::DESGLOSE_DOCUMENTOS[$modulo] ?? null;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($cfg === null || !$ids) {
            return [];
        }

        $res = [];
        // Por tramos: un mayor de un año puede traer miles de documentos.
        foreach (array_chunk($ids, 1000) as $tramo) {
            $ph  = implode(',', array_fill(0, count($tramo), '?'));
            $eli = $cfg['eliminado'] ? ' AND d.eliminado = false' : '';
            $st  = $this->db->prepare("SELECT d.{$cfg['fk']} AS id_doc, COUNT(*) AS n
                                       FROM {$cfg['detalle']} d
                                       INNER JOIN {$cfg['cabecera']} c ON c.id = d.{$cfg['fk']}
                                       WHERE c.id_empresa = ? AND d.{$cfg['fk']} IN ($ph)
                                         AND d.{$cfg['monto']} > 0{$eli}
                                       GROUP BY d.{$cfg['fk']}");
            $st->execute(array_merge([$idEmpresa], $tramo));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $res[(int) $r['id_doc']] = (int) $r['n'];
            }
        }
        return $res;
    }

    /**
     * Líneas del mayor que se quieren desglosar, con su documento origen (misma resolución que
     * el listado). Solo de la empresa y con los mismos filtros de estado/ambiente del mayor.
     *
     * @param int[] $idsLinea
     */
    public function getLineasParaDesglose(int $idEmpresa, array $idsLinea): array
    {
        $idsLinea = array_values(array_unique(array_filter(array_map('intval', $idsLinea))));
        if (!$idsLinea) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($idsLinea), '?'));
        $sql = "SELECT ad.id AS id_linea, ad.id_asiento, ad.id_cuenta_contable, ad.debe, ad.haber,
                       doc.modulo_doc, doc.id_doc
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                {$this->sqlDocumentoOrigenAsiento()}
                WHERE ad.id IN ($ph)
                  AND ac.id_empresa = ?
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = false
                  AND ad.eliminado = false
                  AND ac.tipo_ambiente = '2'";
        try {
            $st = $this->db->prepare($sql);
            $st->execute(array_merge($idsLinea, [$idEmpresa]));
        } catch (\PDOException $e) {
            // Mismo respaldo que getMovimientos(): sin documento origen no hay desglose.
            error_log('Mayores (desglose): no se pudo resolver el documento origen. ' . $e->getMessage());
            return [];
        }
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Todas las líneas de los asientos indicados (para cruzar las líneas del documento con las
     * cuentas del asiento). Formato de CuentasDetalleDesdeAsiento.
     *
     * @param int[] $idsAsiento
     */
    public function getLineasAsientos(int $idEmpresa, array $idsAsiento): array
    {
        $idsAsiento = array_values(array_unique(array_filter(array_map('intval', $idsAsiento))));
        if (!$idsAsiento) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($idsAsiento), '?'));
        $st = $this->db->prepare("SELECT ad.id, ad.id_asiento, ad.id_cuenta_contable, pc.codigo AS codigo_cuenta,
                                         pc.nombre AS nombre_cuenta, ad.debe, ad.haber, ad.referencia_detalle
                                  FROM asientos_contables_detalle ad
                                  INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                                  LEFT JOIN plan_cuentas pc ON pc.id = ad.id_cuenta_contable
                                  WHERE ad.id_asiento IN ($ph) AND ac.id_empresa = ? AND ad.eliminado = false
                                  ORDER BY ad.id_asiento, ad.id");
        $st->execute(array_merge($idsAsiento, [$idEmpresa]));
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Líneas (con monto) de los documentos indicados, en el orden en que se registraron.
     * `monto` es lo pagado/cobrado en la línea; también se expone con su nombre original
     * (monto_pagado / monto_cobrado) para CuentasDetalleDesdeAsiento.
     *
     * @param int[] $ids
     */
    public function getLineasDocumentos(int $idEmpresa, string $modulo, array $ids): array
    {
        $cfg = self::DESGLOSE_DOCUMENTOS[$modulo] ?? null;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($cfg === null || !$ids) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $eli = $cfg['eliminado'] ? ' AND d.eliminado = false' : '';
        $st  = $this->db->prepare("SELECT d.id, d.{$cfg['fk']} AS id_doc, d.tipo_documento, d.numero_documento,
                                          d.descripcion, d.{$cfg['monto']} AS monto, d.{$cfg['monto']},
                                          d.id_cuenta_contable
                                   FROM {$cfg['detalle']} d
                                   INNER JOIN {$cfg['cabecera']} c ON c.id = d.{$cfg['fk']}
                                   WHERE c.id_empresa = ? AND d.{$cfg['fk']} IN ($ph)
                                     AND d.{$cfg['monto']} > 0{$eli}
                                   ORDER BY d.{$cfg['fk']}, d.id");
        $st->execute(array_merge([$idEmpresa], $ids));
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT extract(year from fecha_asiento) as anio
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false
                AND tipo_ambiente = '2'
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
