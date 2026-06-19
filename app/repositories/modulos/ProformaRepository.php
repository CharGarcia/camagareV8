<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;
use PDO;

class ProformaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('proformas_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_emision',
        string $ordenDir = 'DESC',
        ?int $idUsuario = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE p.id_empresa = :id_empresa AND p.eliminado = FALSE";

        if ($idUsuario !== null) {
            $where .= " AND p.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (
                CONCAT(p.establecimiento,'-',p.punto_emision,'-',p.secuencial) ILIKE :b
                OR c.nombre ILIKE :b
                OR c.identificacion ILIKE :b
                OR p.observaciones ILIKE :b
            )";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'       => 'c.nombre',
                'ruc'           => 'c.identificacion',
                'numero'        => "CONCAT(p.establecimiento,'-',p.punto_emision,'-',p.secuencial)",
                'obs'           => 'p.observaciones',
                'observaciones' => 'p.observaciones',
            ],
            'exacto' => [
                'estado'  => 'p.estado',
            ],
            'fecha'   => [
                'fecha'   => 'p.fecha_emision',
            ],
            'numerico' => [
                'total'   => 'p.importe_total',
            ],
        ]);

        $joins = "INNER JOIN clientes c ON p.id_cliente = c.id
                  LEFT JOIN vendedores ven ON p.id_vendedor = ven.id
                  LEFT JOIN usuarios u ON p.id_usuario = u.id";

        $sqlCount = "SELECT COUNT(*) FROM proformas_cabecera p $joins $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'importe_total', 'estado', 'cliente_nombre', 'cliente_ruc', 'vendedor_nombre', 'observaciones', 'numero'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match ($ordenCol) {
            'cliente_nombre'  => 'c.nombre',
            'cliente_ruc'     => 'c.identificacion',
            'vendedor_nombre' => 'ven.nombre',
            'numero'          => "p.establecimiento||'-'||p.punto_emision||'-'||p.secuencial",
            default           => "p.$ordenCol",
        };

        $sql = "SELECT p.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.email          AS cliente_email,
                       ven.nombre       AS vendedor_nombre,
                       u.nombre         AS usuario_nombre
                FROM proformas_cabecera p $joins
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll();
        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT p.*,
                       c.nombre              AS cliente_nombre,
                       c.identificacion      AS cliente_ruc,
                       c.direccion           AS cliente_direccion,
                       c.email               AS cliente_email,
                       c.tipo_id             AS cliente_tipo_id,
                       ven.nombre            AS vendedor_nombre,
                       u.nombre              AS usuario_nombre
                FROM proformas_cabecera p
                INNER JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN vendedores ven ON p.id_vendedor = ven.id
                LEFT JOIN usuarios u ON p.id_usuario = u.id
                WHERE p.id = ? AND p.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM proformas_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO proformas_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    id_vendedor, fecha_emision, establecimiento, punto_emision, secuencial,
                    tipo_ambiente, dias_vigencia,
                    total_sin_impuestos, total_descuento, total_ice, importe_total, moneda,
                    estado, observaciones, created_by, updated_by
                ) VALUES (
                    ?,?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,?,?,  ?,?,?,?
                ) RETURNING id";
        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            (int) $data['id_usuario'],
            !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null,
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['tipo_ambiente'] ?? '1',
            (int) ($data['dias_vigencia'] ?? 15),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['total_ice'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            $data['estado'] ?? 'borrador',
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE proformas_cabecera SET
                    id_establecimiento    = ?,
                    id_punto_emision      = ?,
                    id_cliente            = ?,
                    id_vendedor           = ?,
                    fecha_emision         = ?,
                    establecimiento       = ?,
                    punto_emision         = ?,
                    secuencial            = ?,
                    dias_vigencia         = ?,
                    total_sin_impuestos   = ?,
                    total_descuento       = ?,
                    total_ice             = ?,
                    importe_total         = ?,
                    estado                = ?,
                    observaciones         = ?,
                    updated_by            = ?,
                    updated_at            = NOW()
                WHERE id = ?";
        $this->query($sql, [
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null,
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            (int) ($data['dias_vigencia'] ?? 15),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['total_ice'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            $data['estado'] ?? 'borrador',
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            $id,
        ]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE proformas_cabecera SET estado = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
        $this->query($sql, [$estado, $idUsuario, $id]);
    }

    public function marcarConvertida(int $id, int $idFactura, int $idUsuario): void
    {
        $sql = "UPDATE proformas_cabecera SET estado = 'convertida', id_factura_convertida = ?, fecha_convertida = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?";
        $this->query($sql, [$idFactura, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE proformas_cabecera
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->query($sql, [$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function getDetalles(int $idProforma): array
    {
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) AS producto_nombre, p.codigo AS producto_codigo
                FROM proformas_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                WHERE d.id_proforma = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idProforma])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM proformas_detalle_impuestos WHERE id_proforma_detalle = ?";
        return $this->query($sql, [$idDetalle])->fetchAll();
    }

    public function getInfoAdicional(int $idProforma): array
    {
        $sql = "SELECT * FROM proformas_adicional WHERE id_proforma = ?";
        return $this->query($sql, [$idProforma])->fetchAll();
    }

    public function deleteDetalles(int $idProforma): void
    {
        // Primero borra impuestos de los detalles
        $this->query("DELETE FROM proformas_detalle_impuestos WHERE id_proforma_detalle IN (SELECT id FROM proformas_detalle WHERE id_proforma = ?)", [$idProforma]);
        $this->query("DELETE FROM proformas_detalle WHERE id_proforma = ?", [$idProforma]);
    }

    public function deleteInfoAdicional(int $idProforma): void
    {
        $this->query("DELETE FROM proformas_adicional WHERE id_proforma = ?", [$idProforma]);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO proformas_detalle (
                    id_proforma, id_producto, id_unidad_medida,
                    codigo_principal, codigo_auxiliar, descripcion,
                    cantidad, precio_unitario, descuento, precio_total_sin_impuesto, id_tarifa_iva
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id";
        return (int) $this->query($sql, [
            (int) $data['id_proforma'],
            !empty($data['id_producto']) ? (int) $data['id_producto'] : null,
            !empty($data['id_unidad_medida']) ? (int) $data['id_unidad_medida'] : null,
            $data['codigo_principal'] ?? '',
            !empty($data['codigo_auxiliar']) ? $data['codigo_auxiliar'] : null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'],
            $data['precio_total_sin_impuesto'],
            (int) ($data['id_tarifa_iva'] ?? 0),
        ])->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO proformas_detalle_impuestos
                    (id_proforma_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
                VALUES (?,?,?,?,?,?)";
        $this->query($sql, [
            $data['id_proforma_detalle'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            $data['tarifa'],
            $data['base_imponible'],
            $data['valor'],
        ]);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO proformas_adicional (id_proforma, nombre, valor) VALUES (?,?,?)";
        $this->query($sql, [$data['id_proforma'], $data['nombre'], $data['valor']]);
    }
}
