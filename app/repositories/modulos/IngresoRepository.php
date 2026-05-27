<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class IngresoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ingresos_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE i.id_empresa = :id_empresa AND i.eliminado = FALSE";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (i.numero_ingreso ILIKE :buscar OR i.recibo_de ILIKE :buscar OR c.nombre ILIKE :buscar OR c.identificacion ILIKE :buscar OR i.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'recibo_de'      => 'i.recibo_de',
                'beneficiario'   => 'i.recibo_de',
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => 'i.numero_ingreso',
                'nro'            => 'i.numero_ingreso',
                'concepto'       => 'i.observaciones',
                'obs'            => 'i.observaciones',
            ],
            'exacto'   => [ 'estado' => 'i.estado', 'tipo' => 'i.tipo_ingreso' ],
            'fecha'    => [ 'fecha' => 'i.fecha_emision', 'fecha_emision' => 'i.fecha_emision' ],
            'numerico' => [ 'monto' => 'i.monto_total', 'total' => 'i.monto_total' ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND i.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*) FROM ingresos_cabecera i LEFT JOIN clientes c ON i.id_cliente = c.id $where";
        $total = (int) $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'numero_ingreso', 'tipo_ingreso', 'monto_total', 'estado', 'cliente_nombre', 'observaciones', 'recibo_de'];
        if (!in_array($ordenCol, $allowedCols)) {
            $ordenCol = 'fecha_emision';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'cliente_nombre' => 'COALESCE(i.recibo_de, c.nombre, \'—\')',
            'recibo_de'      => 'COALESCE(i.recibo_de, c.nombre, \'—\')',
            default          => "i.$ordenCol",
        };

        $sql = "SELECT i.*,
                       c.nombre AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       rc.nombre AS recibo_cliente_nombre,
                       u.nombre AS usuario_nombre,
                       eic.nombre AS concepto_nombre
                FROM ingresos_cabecera i
                LEFT JOIN clientes c  ON i.id_cliente        = c.id
                LEFT JOIN clientes rc ON i.id_recibo_cliente  = rc.id
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso eic ON i.id_ingreso_concepto = eic.id
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT i.*,
                       c.nombre AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       rc.nombre AS recibo_cliente_nombre,
                       u.nombre AS usuario_nombre,
                       eic.nombre AS concepto_nombre,
                       est.nombre AS establecimiento_nombre,
                       pto.nombre AS punto_emision_nombre
                FROM ingresos_cabecera i
                LEFT JOIN clientes c  ON i.id_cliente        = c.id
                LEFT JOIN clientes rc ON i.id_recibo_cliente  = rc.id
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso eic ON i.id_ingreso_concepto = eic.id
                LEFT JOIN empresa_establecimiento est ON i.id_establecimiento = est.id
                LEFT JOIN empresa_punto_emision pto ON i.id_punto_emision = pto.id
                WHERE i.id = :id AND i.id_empresa = :id_empresa AND i.eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idIngreso): array
    {
        $sql = "SELECT d.*,
                       c.id   AS id_cliente,
                       c.nombre AS cliente_nombre,
                       v.fecha_emision AS fecha_documento
                FROM ingresos_detalle d
                LEFT JOIN ventas_cabecera v ON d.id_referencia_documento = v.id AND d.tipo_documento = 'FACTURA'
                LEFT JOIN clientes c ON v.id_cliente = c.id
                WHERE d.id_ingreso = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPagos(int $idIngreso): array
    {
        $sql = "SELECT ip.*, efc.nombre AS forma_cobro_nombre
                FROM ingresos_pagos ip
                INNER JOIN empresa_formas_pago efc ON ip.id_forma_cobro = efc.id
                WHERE ip.id_ingreso = ?
                ORDER BY ip.id ASC";
        return $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormasCobro(int $idEmpresa): array
    {
        $sql = "SELECT * FROM empresa_formas_pago 
                WHERE id_empresa = :id_empresa AND activo = TRUE AND eliminado = FALSE 
                  AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
                ORDER BY nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConceptosIngreso(int $idEmpresa): array
    {
        $sql = "SELECT * FROM empresa_opciones_ingreso_egreso 
                WHERE id_empresa = :id_empresa 
                  AND aplica_ingresos = TRUE 
                  AND UPPER(estado) = 'ACTIVO' 
                  AND eliminado = FALSE 
                ORDER BY nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFacturasPendientes(int $idCliente, int $idEmpresa, ?int $excluirIngresoId = null): array
    {
        $excluirSql = "";
        $params = [':id_cliente' => $idCliente, ':id_empresa' => $idEmpresa];

        if ($excluirIngresoId !== null) {
            $excluirSql = " AND i.id <> :excluir ";
            $params[':excluir'] = $excluirIngresoId;
        }

        // Nota: Se calcula el saldo dinámicamente restando lo ya cobrado en ingresos activos
        $sql = "WITH cobrado AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) as total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA' 
                      AND i.estado != 'anulado' 
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                )
                SELECT v.id, 
                       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                       v.fecha_emision,
                       v.importe_total,
                       COALESCE(c.total_cobrado, 0) AS monto_cobrado,
                       (v.importe_total - COALESCE(c.total_cobrado, 0)) AS saldo_pendiente
                FROM ventas_cabecera v
                LEFT JOIN cobrado c ON v.id = c.id_referencia_documento
                WHERE v.id_cliente = :id_cliente 
                  AND v.id_empresa = :id_empresa
                  AND v.estado = 'autorizado' -- Solo facturas vigentes/autorizadas
                  AND v.eliminado = FALSE
                  AND (v.importe_total - COALESCE(c.total_cobrado, 0)) > 0.01
                ORDER BY v.fecha_emision ASC, v.id ASC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO ingresos_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, numero_ingreso,
                    tipo_ingreso, id_ingreso_concepto, monto_total, observaciones, estado,
                    recibo_de, id_recibo_cliente,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :id_empresa, :id_establecimiento, :id_punto_emision, :id_cliente, :id_usuario,
                    :fecha_emision, :establecimiento, :punto_emision, :secuencial, :numero_ingreso,
                    :tipo_ingreso, :id_ingreso_concepto, :monto_total, :observaciones, :estado,
                    :recibo_de, :id_recibo_cliente,
                    :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'           => (int) $data['id_empresa'],
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':id_cliente'           => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            ':id_usuario'           => (int) $data['id_usuario'],
            ':fecha_emision'        => $data['fecha_emision'],
            ':establecimiento'      => $data['establecimiento'] ?? null,
            ':punto_emision'        => $data['punto_emision'] ?? null,
            ':secuencial'           => $data['secuencial'],
            ':numero_ingreso'       => $data['numero_ingreso'],
            ':tipo_ingreso'         => $data['tipo_ingreso'],
            ':id_ingreso_concepto'  => !empty($data['id_ingreso_concepto']) ? (int) $data['id_ingreso_concepto'] : null,
            ':monto_total'          => (float) $data['monto_total'],
            ':observaciones'        => $data['observaciones'] ?? null,
            ':estado'               => $data['estado'] ?? 'registrado',
            ':recibo_de'            => !empty($data['recibo_de']) ? trim($data['recibo_de']) : null,
            ':id_recibo_cliente'    => !empty($data['id_recibo_cliente']) ? (int) $data['id_recibo_cliente'] : null,
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE ingresos_cabecera SET
                    id_establecimiento  = :id_establecimiento,
                    id_punto_emision    = :id_punto_emision,
                    id_cliente          = :id_cliente,
                    fecha_emision       = :fecha_emision,
                    establecimiento     = :establecimiento,
                    punto_emision       = :punto_emision,
                    secuencial          = :secuencial,
                    numero_ingreso      = :numero_ingreso,
                    tipo_ingreso        = :tipo_ingreso,
                    id_ingreso_concepto = :id_ingreso_concepto,
                    monto_total         = :monto_total,
                    observaciones       = :observaciones,
                    recibo_de           = :recibo_de,
                    id_recibo_cliente   = :id_recibo_cliente,
                    updated_by          = :id_usuario,
                    updated_at          = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'                   => $id,
            ':id_empresa'           => (int) $data['id_empresa'],
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':id_cliente'           => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            ':id_usuario'           => (int) $data['id_usuario'],
            ':fecha_emision'        => $data['fecha_emision'],
            ':establecimiento'      => $data['establecimiento'] ?? null,
            ':punto_emision'        => $data['punto_emision'] ?? null,
            ':secuencial'           => $data['secuencial'],
            ':numero_ingreso'       => $data['numero_ingreso'],
            ':tipo_ingreso'         => $data['tipo_ingreso'],
            ':id_ingreso_concepto'  => !empty($data['id_ingreso_concepto']) ? (int) $data['id_ingreso_concepto'] : null,
            ':monto_total'          => (float) $data['monto_total'],
            ':observaciones'        => $data['observaciones'] ?? null,
            ':recibo_de'            => !empty($data['recibo_de']) ? trim($data['recibo_de']) : null,
            ':id_recibo_cliente'    => !empty($data['id_recibo_cliente']) ? (int) $data['id_recibo_cliente'] : null,
        ]);
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO ingresos_detalle (
                    id_ingreso, tipo_documento, id_referencia_documento, numero_documento, 
                    descripcion, monto_documento, saldo_anterior, monto_cobrado, saldo_actual
                ) VALUES (
                    :id_ingreso, :tipo_documento, :id_ref, :num_doc,
                    :desc, :monto_doc, :saldo_ant, :monto_cob, :saldo_act
                )";
        $this->query($sql, [
            ':id_ingreso'   => (int) $data['id_ingreso'],
            ':tipo_documento' => $data['tipo_documento'],
            ':id_ref'       => !empty($data['id_referencia_documento']) ? (int)$data['id_referencia_documento'] : null,
            ':num_doc'      => $data['numero_documento'] ?? null,
            ':desc'         => $data['descripcion'] ?? null,
            ':monto_doc'    => (float) ($data['monto_documento'] ?? 0),
            ':saldo_ant'    => (float) ($data['saldo_anterior'] ?? 0),
            ':monto_cob'    => (float) ($data['monto_cobrado'] ?? 0),
            ':saldo_act'    => (float) ($data['saldo_actual'] ?? 0),
        ]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO ingresos_pagos (
                    id_ingreso, id_forma_cobro, monto, referencia, observaciones,
                    tipo_operacion_bancaria, numero_cheque, fecha_cobro
                ) VALUES (
                    :id_ingreso, :id_forma, :monto, :ref, :obs,
                    :tipo_op, :num_chq, :fec_cob
                )";
        $this->query($sql, [
            ':id_ingreso' => (int) $data['id_ingreso'],
            ':id_forma'   => (int) $data['id_forma_cobro'],
            ':monto'      => (float) $data['monto'],
            ':ref'        => $data['referencia'] ?? null,
            ':obs'        => $data['observaciones'] ?? null,
            ':tipo_op'    => $data['tipo_operacion_bancaria'] ?? null,
            ':num_chq'    => $data['numero_cheque'] ?? null,
            ':fec_cob'    => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null,
        ]);
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ingresos_cabecera 
                SET estado = 'anulado', updated_at = CURRENT_TIMESTAMP, updated_by = :usr 
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ingresos_cabecera 
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr 
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function deleteDetalles(int $idIngreso): void
    {
        $this->query("DELETE FROM ingresos_detalle WHERE id_ingreso = ?", [$idIngreso]);
    }

    public function deletePagos(int $idIngreso): void
    {
        $this->query("DELETE FROM ingresos_pagos WHERE id_ingreso = ?", [$idIngreso]);
    }

    public function buscarDocumentosPendientes(int $idEmpresa, string $q = '', ?int $excluirIngresoId = null): array
    {
        $params     = [':id_empresa' => $idEmpresa];
        $excluirSql = '';
        $filtroBusq = '';

        if ($excluirIngresoId !== null) {
            $excluirSql = " AND i.id <> :excluir";
            $params[':excluir'] = $excluirIngresoId;
        }

        if ($q !== '') {
            $filtroBusq = " AND (
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :q
                OR c.nombre          ILIKE :q
                OR c.identificacion  ILIKE :q
            )";
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "WITH cobrado AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                )
                SELECT v.id,
                       CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                       v.fecha_emision,
                       v.dias_credito,
                       v.importe_total,
                       COALESCE(cb.total_cobrado, 0) AS monto_cobrado,
                       (v.importe_total - COALESCE(cb.total_cobrado, 0)) AS saldo_pendiente,
                       c.id             AS id_cliente,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc
                FROM ventas_cabecera v
                INNER JOIN clientes c ON v.id_cliente = c.id
                LEFT  JOIN cobrado cb ON v.id = cb.id_referencia_documento
                WHERE v.id_empresa = :id_empresa
                  AND v.estado = 'autorizado'
                  AND v.eliminado = FALSE
                  AND (v.importe_total - COALESCE(cb.total_cobrado, 0)) > 0.01
                  $filtroBusq
                ORDER BY c.nombre ASC, v.fecha_emision ASC, v.id ASC
                LIMIT 301";

        $rows    = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 300;
        return ['data' => array_slice($rows, 0, 300), 'has_more' => $hasMore];
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM ingresos_cabecera 
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ? 
                  AND secuencial = ? AND eliminado = FALSE";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];

        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }

        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }
}
