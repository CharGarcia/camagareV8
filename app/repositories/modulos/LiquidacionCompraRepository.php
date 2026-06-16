<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;

class LiquidacionCompraRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('liquidaciones_cabecera');
        try {
            $this->db->exec("ALTER TABLE liquidaciones_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
        } catch (\Throwable $e) {}
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

        $where = "WHERE l.id_empresa = :id_empresa AND l.eliminado = false AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) ILIKE :buscar
                          OR p.razon_social ILIKE :buscar OR p.identificacion ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => "CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial)",
                'nro'            => "CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial)",
            ],
            'exacto'   => [ 'estado' => 'l.estado' ],
            'fecha'    => [ 'fecha' => 'l.fecha_emision', 'fecha_emision' => 'l.fecha_emision' ],
            'numerico' => [ 'monto' => 'l.importe_total', 'total' => 'l.importe_total' ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND l.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*) FROM liquidaciones_cabecera l INNER JOIN proveedores p ON l.id_proveedor = p.id $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'importe_total', 'total_sin_impuestos', 'total_descuento', 'estado', 'estado_correo', 'proveedor_nombre', 'proveedor_ruc', 'usuario_nombre', 'observaciones'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'proveedor_nombre' => 'p.razon_social',
            'proveedor_ruc'    => 'p.identificacion',
            'usuario_nombre'   => 'u.nombre',
            default            => "l.$ordenCol",
        };

        $sql = "SELECT l.*,
                       p.razon_social    AS proveedor_nombre,
                       p.identificacion   AS proveedor_ruc,
                       u.nombre          AS usuario_nombre
                FROM liquidaciones_cabecera l
                INNER JOIN proveedores p ON l.id_proveedor = p.id
                LEFT  JOIN usuarios    u ON l.id_usuario   = u.id
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT l.*,
                       p.razon_social         AS proveedor_nombre,
                       p.identificacion       AS proveedor_ruc,
                       p.direccion            AS proveedor_direccion,
                       p.email                AS proveedor_email,
                       p.tipo_id_proveedor    AS proveedor_tipo_id,
                       p.plazo                AS proveedor_plazo,
                       COALESCE(icv.nombre,'') AS proveedor_nombre_tipo_id,
                       u.nombre               AS usuario_nombre,
                       uc.nombre              AS creado_por_nombre,
                       st.nombre              AS sustento_nombre,
                       st.codigo              AS sustento_codigo
                FROM liquidaciones_cabecera l
                INNER JOIN proveedores p ON l.id_proveedor = p.id
                LEFT  JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
                LEFT  JOIN usuarios    u   ON l.id_usuario   = u.id
                LEFT  JOIN usuarios    uc  ON l.created_by   = uc.id
                LEFT  JOIN sustento_tributario st ON l.id_sustento_tributario = st.id
                WHERE l.id = ? AND l.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    public function getDetalles(int $idCabecera): array
    {
        $sql = "SELECT d.*
                FROM liquidaciones_detalle d 
                WHERE d.id_cabecera = ? 
                ORDER BY d.id ASC";
        return $this->query($sql, [$idCabecera])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM liquidaciones_detalle_impuestos WHERE id_detalle = ?";
        return $this->query($sql, [$idDetalle])->fetchAll();
    }

    public function getPagos(int $idCabecera): array
    {
        $sql = "SELECT * FROM liquidaciones_pagos WHERE id_cabecera = ?";
        return $this->query($sql, [$idCabecera])->fetchAll();
    }

    public function getInfoAdicional(int $idCabecera): array
    {
        $sql = "SELECT * FROM liquidaciones_adicional WHERE id_cabecera = ?";
        return $this->query($sql, [$idCabecera])->fetchAll();
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO liquidaciones_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_proveedor, id_usuario,
                    id_sustento_tributario, fecha_emision, establecimiento, punto_emision, secuencial,
                    total_sin_impuestos, total_descuento, importe_total,
                    moneda, estado, observaciones, created_by, updated_by,
                    tipo_ambiente, tipo_emision, clave_acceso
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING id";

        $params = [
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_proveedor'],
            (int) $data['id_usuario'],
            !empty($data['id_sustento_tributario']) ? (int) $data['id_sustento_tributario'] : null,
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            (float) $data['total_sin_impuestos'],
            (float) $data['total_descuento'],
            (float) $data['importe_total'],
            $data['moneda'] ?? 'DOLAR',
            $data['estado'] ?? 'borrador',
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
            $data['tipo_ambiente'] ?? null,
            $data['tipo_emision'] ?? null,
            $data['clave_acceso'] ?? null
        ];

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE liquidaciones_cabecera SET
                    id_establecimiento = ?, id_punto_emision = ?, id_proveedor = ?, 
                    id_sustento_tributario = ?, fecha_emision = ?, establecimiento = ?, 
                    punto_emision = ?, secuencial = ?, total_sin_impuestos = ?, 
                    total_descuento = ?, importe_total = ?, 
                    observaciones = ?, updated_by = ?, updated_at = NOW(),
                    tipo_ambiente = ?, tipo_emision = ?, clave_acceso = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = false";

        $params = [
            (int)   $data['id_establecimiento'],
            (int)   $data['id_punto_emision'],
            (int)   $data['id_proveedor'],
            !empty($data['id_sustento_tributario']) ? (int) $data['id_sustento_tributario'] : null,
                    $data['fecha_emision'],
                    $data['establecimiento'],
                    $data['punto_emision'],
                    $data['secuencial'],
            (float) $data['total_sin_impuestos'],
            (float) $data['total_descuento'],
            (float) $data['importe_total'],
                    $data['observaciones'] ?? null,
            (int)   $data['id_usuario'],
                    $data['tipo_ambiente'] ?? null,
                    $data['tipo_emision'] ?? null,
                    $data['clave_acceso'] ?? null,
            $id,
            (int)   $data['id_empresa']
        ];

        $this->query($sql, $params);
    }

    public function deleteDetalles(int $idCabecera): void
    {
        $this->query("DELETE FROM liquidaciones_detalle WHERE id_cabecera = ?", [$idCabecera]);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO liquidaciones_detalle (
                    id_cabecera, codigo_principal, codigo_auxiliar,
                    descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto, info_adicional
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";

        $params = [
            (int) $data['id_cabecera'],
            $data['codigo_principal'] ?? '',
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'] ?? '',
            (float) $data['cantidad'],
            (float) $data['precio_unitario'],
            (float) $data['descuento'],
            (float) $data['precio_total_sin_impuesto'],
            $data['info_adicional'] ?? null
        ];

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO liquidaciones_detalle_impuestos (
                    id_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        $this->query($sql, [
            (int) $data['id_detalle'], $data['codigo_impuesto'], $data['codigo_porcentaje'], 
            (float) $data['tarifa'], (float) $data['base_imponible'], (float) $data['valor']
        ]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO liquidaciones_pagos (id_cabecera, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)";
        $formaPago = $data['forma_pago'] ?? $data['id_forma_pago'] ?? '01';
        $this->query($sql, [
            (int) $data['id_cabecera'], $formaPago, (float) $data['total'], 
            $data['plazo'] ?? 0, $data['unidad_tiempo'] ?? 'dias'
        ]);
    }

    public function deletePagos(int $idCabecera): void
    {
        $this->query("DELETE FROM liquidaciones_pagos WHERE id_cabecera = ?", [$idCabecera]);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO liquidaciones_adicional (id_cabecera, nombre, valor) VALUES (?, ?, ?)";
        $this->query($sql, [
            (int) $data['id_cabecera'], $data['nombre'], $data['valor']
        ]);
    }

    public function deleteInfoAdicional(int $idCabecera): void
    {
        $this->query("DELETE FROM liquidaciones_adicional WHERE id_cabecera = ?", [$idCabecera]);
    }

    public function getFormasPago(): array
    {
        return $this->db->query("SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSustentosTributarios(): array
    {
        return $this->db->query("SELECT * FROM sustento_tributario WHERE status = 1 ORDER BY codigo ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getEgresosVinculados(int $idLiquidacion): array
    {
        $sql = "SELECT ec.id, ec.fecha_emision, ec.numero_egreso, ec.monto_total, ec.estado,
                       ed.monto_pagado,
                       c.nombre AS concepto_nombre,
                       (SELECT string_agg(efc.nombre, ', ') 
                        FROM egresos_pagos ep 
                        JOIN empresa_formas_pago efc ON ep.id_forma_pago = efc.id 
                        WHERE ep.id_egreso = ec.id AND ep.eliminado = false) as formas_pago
                FROM egresos_detalle ed
                JOIN egresos_cabecera ec ON ed.id_egreso = ec.id
                LEFT JOIN empresa_opciones_ingreso_egreso c ON ec.id_egreso_concepto = c.id
                WHERE ed.tipo_documento = 'LIQUIDACION'
                  AND ed.id_referencia_documento = ?
                  AND ed.eliminado = false
                  AND ec.eliminado = false
                ORDER BY ec.fecha_emision DESC";
        return $this->query($sql, [$idLiquidacion])->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    public function updateDetalleXml(int $id, string $xml): void
    {
        try {
            $this->db->exec("ALTER TABLE liquidaciones_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable) {}

        $st = $this->db->prepare(
            "UPDATE liquidaciones_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
