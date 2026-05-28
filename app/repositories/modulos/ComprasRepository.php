<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;

class ComprasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('compras_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTADO
    // ─────────────────────────────────────────────────────────────────────────

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

        $where = "WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $where .= " AND (
                CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) ILIKE :buscar
                OR p.razon_social ILIKE :buscar
                OR p.identificacion ILIKE :buscar
                OR c.numero_autorizacion ILIKE :buscar
                OR c.observaciones ILIKE :buscar
            )";
            $params[':buscar'] = "%$textoLibre%";
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)",
                'nro'            => "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)",
                'autorizacion'   => 'c.numero_autorizacion',
                'obs'            => 'c.observaciones',
                'observacion'    => 'c.observaciones',
                'usuario'        => 'u.nombre',
                'sustento'       => 'st.nombre',
            ],
            'exacto' => [
                'tipo_comprobante' => 'c.tipo_comprobante',
                'tipo'             => 'c.tipo_comprobante',
            ],
            'fecha' => [
                'fecha'          => 'c.fecha_emision',
                'fecha_emision'  => 'c.fecha_emision',
                'fecha_registro' => 'c.fecha_registro',
            ],
            'numerico' => [
                'monto'    => 'c.importe_total',
                'total'    => 'c.importe_total',
                'subtotal' => 'c.total_sin_impuestos',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND c.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*)
                     FROM compras_cabecera c
                     INNER JOIN proveedores p          ON c.id_proveedor          = p.id
                     LEFT  JOIN usuarios   u          ON c.created_by             = u.id
                     LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                     $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = [
            'id',
            'fecha_emision',
            'fecha_registro',
            'secuencial_prov',
            'importe_total',
            'total_sin_impuestos',
            'tipo_comprobante',
            'proveedor_nombre',
            'proveedor_ruc',
            'usuario_nombre',
            'observaciones'
        ];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match ($ordenCol) {
            'proveedor_nombre' => 'p.razon_social',
            'proveedor_ruc'    => 'p.identificacion',
            'usuario_nombre'   => 'u.nombre',
            'tipo_comprobante' => 'ca.comprobante',
            default            => "c.$ordenCol",
        };

        $sql = "SELECT c.*,
                       (c.importe_total - c.total_sin_impuestos - COALESCE(c.propina, 0)) AS monto_iva,
                       'registrado' AS estado,
                       p.razon_social      AS proveedor_nombre,
                       p.identificacion    AS proveedor_ruc,
                       st.nombre           AS sustento_nombre,
                       st.codigo           AS sustento_codigo,
                       u.nombre            AS usuario_nombre,
                       ca.comprobante      AS tipo_comprobante_nombre
                FROM compras_cabecera c
                INNER JOIN proveedores p        ON c.id_proveedor = p.id
                LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                LEFT  JOIN usuarios u            ON c.created_by = u.id
                LEFT  JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OBTENER POR ID
    // ─────────────────────────────────────────────────────────────────────────

    public function getPorId(int $id, ?int $idEmpresa = null): ?array
    {
        $where = "WHERE c.id = ? AND c.eliminado = FALSE";
        $params = [$id];

        if ($idEmpresa !== null) {
            $where .= " AND c.id_empresa = ?";
            $params[] = $idEmpresa;
        }

        $sql = "SELECT c.*,
                       (c.importe_total - c.total_sin_impuestos - COALESCE(c.propina, 0)) AS monto_iva,
                       'registrado' AS estado,
                       p.razon_social          AS proveedor_nombre,
                       p.identificacion        AS proveedor_ruc,
                       p.direccion             AS proveedor_direccion,
                       p.email                 AS proveedor_email,
                       p.tipo_id_proveedor     AS proveedor_tipo_id,
                       COALESCE(icv.nombre,'') AS proveedor_nombre_tipo_id,
                       st.nombre               AS sustento_nombre,
                       st.codigo               AS sustento_codigo,
                       uc.nombre               AS creado_por_nombre,
                       uu.nombre               AS actualizado_por_nombre
                FROM compras_cabecera c
                INNER JOIN proveedores p ON c.id_proveedor = p.id
                LEFT  JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
                LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                LEFT  JOIN usuarios uc ON c.created_by  = uc.id
                LEFT  JOIN usuarios uu ON c.updated_by  = uu.id
                $where";
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETALLES
    // ─────────────────────────────────────────────────────────────────────────

    public function getDetalles(int $idCompra): array
    {
        $sql = "SELECT d.*, 
                       COALESCE(pr.nombre, ph_pr.nombre, d.descripcion) AS producto_nombre, 
                       COALESCE(pr.codigo, ph_pr.codigo) AS producto_codigo, 
                       COALESCE(pr.id_medida, ph_pr.id_medida) AS product_id_medida, 
                       COALESCE(um.id_tipo, ph_um.id_tipo) AS product_id_tipo_medida,
                       COALESCE(pr.id, ph_pr.id) AS id_producto_vinculado
                FROM compras_detalle d
                LEFT JOIN compras_cabecera c ON d.id_compra = c.id
                LEFT JOIN productos pr ON d.id_producto = pr.id
                LEFT JOIN unidades_medida um ON um.id = pr.id_medida
                LEFT JOIN productos_homologacion ph ON ph.id_proveedor = c.id_proveedor 
                                                     AND ph.id_empresa = c.id_empresa 
                                                     AND ph.codigo_proveedor = d.codigo_principal 
                                                     AND ph.eliminado = false
                LEFT JOIN productos ph_pr ON ph.id_producto = ph_pr.id
                LEFT JOIN unidades_medida ph_um ON ph_um.id = ph_pr.id_medida
                WHERE d.id_compra = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idCompra])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        return $this->query(
            "SELECT * FROM compras_detalle_impuestos WHERE id_compra_detalle = ?",
            [$idDetalle]
        )->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PAGOS
    // ─────────────────────────────────────────────────────────────────────────

    public function getPagos(int $idCompra): array
    {
        return $this->query(
            "SELECT cp.*, fp.nombre AS forma_pago_nombre
             FROM compras_pagos cp
             LEFT JOIN formas_pago_sri fp ON fp.codigo = cp.forma_pago
             WHERE cp.id_compra = ?",
            [$idCompra]
        )->fetchAll();
    }

    public function getInfoAdicional(int $idCompra): array
    {
        return $this->query(
            "SELECT * FROM compras_adicional WHERE id_compra = ?",
            [$idCompra]
        )->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RETENCIONES
    // ─────────────────────────────────────────────────────────────────────────



    // Asiento contable: gestionado por módulo de contabilidad independiente

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS — CABECERA
    // ─────────────────────────────────────────────────────────────────────────

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO compras_cabecera (
                    id_empresa, id_proveedor, id_establecimiento,
                    id_sustento_tributario, tipo_comprobante, tipo_id_proveedor,
                    parte_relacionada, establecimiento_prov, punto_emision_prov,
                    secuencial_prov, numero_autorizacion, fecha_emision, fecha_registro,
                    total_sin_impuestos, total_descuento, importe_total, propina,
                    autorizacion_desde, autorizacion_hasta, fecha_caducidad,
                    tipo_registro, deducible, documento_modificado, motivo,
                    observaciones, created_by, updated_by, id_usuario
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?
                ) RETURNING id";

        $params = [
            (int)   $data['id_empresa'],
            (int)   $data['id_proveedor'],
            !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            !empty($data['id_sustento_tributario']) ? (int)$data['id_sustento_tributario'] : null,
            $data['tipo_comprobante'] ?? '01',
            $data['tipo_id_proveedor'] ?? null,
            !empty($data['parte_relacionada']) ? 'true' : 'false',
            $data['establecimiento_prov'] ?? null,
            $data['punto_emision_prov'] ?? null,
            $data['secuencial_prov'] ?? null,
            $data['numero_autorizacion'] ?? null,
            $data['fecha_emision'],
            $data['fecha_registro'] ?? date('Y-m-d'),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            (float) ($data['propina'] ?? 0),
            $data['autorizacion_desde'] ?? null,
            $data['autorizacion_hasta'] ?? null,
            !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
            $data['tipo_registro'] ?? 'fisica',
            $data['deducible'] ?? 'declaracion_iva',
            $data['documento_modificado'] ?? null,
            $data['motivo'] ?? null,
            $data['observaciones'] ?? null,
            (int)   $data['id_usuario'], // created_by
            (int)   $data['id_usuario'], // updated_by
            (int)   $data['id_usuario']  // id_usuario
        ];

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE — CABECERA
    // ─────────────────────────────────────────────────────────────────────────

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE compras_cabecera SET
                    id_proveedor            = ?,
                    id_establecimiento      = ?,
                    id_sustento_tributario  = ?,
                    tipo_comprobante        = ?,
                    tipo_id_proveedor       = ?,
                    parte_relacionada       = ?,
                    establecimiento_prov    = ?,
                    punto_emision_prov      = ?,
                    secuencial_prov         = ?,
                    numero_autorizacion     = ?,
                    fecha_emision           = ?,
                    fecha_registro          = ?,
                    total_sin_impuestos     = ?,
                    total_descuento         = ?,
                    importe_total           = ?,
                    propina                 = ?,
                    autorizacion_desde      = ?,
                    autorizacion_hasta      = ?,
                    fecha_caducidad         = ?,
                    tipo_registro           = ?,
                    deducible               = ?,
                    documento_modificado    = ?,
                    motivo                  = ?,
                    observaciones           = ?,
                    updated_by              = ?,
                    updated_at              = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";

        $params = [
            (int)   $data['id_proveedor'],
            !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            !empty($data['id_sustento_tributario']) ? (int)$data['id_sustento_tributario'] : null,
            $data['tipo_comprobante'] ?? '01',
            $data['tipo_id_proveedor'] ?? null,
            !empty($data['parte_relacionada']) ? 'true' : 'false',
            $data['establecimiento_prov'] ?? null,
            $data['punto_emision_prov'] ?? null,
            $data['secuencial_prov'] ?? null,
            $data['numero_autorizacion'] ?? null,
            $data['fecha_emision'],
            $data['fecha_registro'] ?? date('Y-m-d'),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            (float) ($data['propina'] ?? 0),
            $data['autorizacion_desde'] ?? null,
            $data['autorizacion_hasta'] ?? null,
            !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
            $data['tipo_registro'] ?? 'fisica',
            $data['deducible'] ?? 'declaracion_iva',
            $data['documento_modificado'] ?? null,
            $data['motivo'] ?? null,
            $data['observaciones'] ?? null,
            (int)   $data['id_usuario'],
            $id,
            (int)   $data['id_empresa'],
        ];

        $this->query($sql, $params);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS / DELETES — DETALLE
    // ─────────────────────────────────────────────────────────────────────────

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO compras_detalle (
                    id_compra, id_producto, codigo_principal, codigo_auxiliar,
                    descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                ) VALUES (?,?,?,?,?,?,?,?,?) RETURNING id";

        return (int) $this->query($sql, [
            (int)   $data['id_compra'],
            !empty($data['id_producto']) ? (int)$data['id_producto'] : null,
            $data['codigo_principal'] ?? '',
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'] ?? '',
            (float) ($data['cantidad'] ?? 1),
            (float) ($data['precio_unitario'] ?? 0),
            (float) ($data['descuento'] ?? 0),
            (float) ($data['precio_total_sin_impuesto'] ?? 0),
        ])->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO compras_detalle_impuestos (
                    id_compra_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?,?,?,?,?,?)";
        $this->query($sql, [
            (int)   $data['id_compra_detalle'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            (float) $data['tarifa'],
            (float) $data['base_imponible'],
            (float) $data['valor'],
        ]);
    }

    public function deleteDetalles(int $idCompra): void
    {
        // Primero eliminar impuestos (FK en cascada lo haría, pero lo hacemos explícito)
        $ids = $this->query(
            "SELECT id FROM compras_detalle WHERE id_compra = ?",
            [$idCompra]
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $this->query("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle IN ($ph)", $ids);
        }
        $this->query("DELETE FROM compras_detalle WHERE id_compra = ?", [$idCompra]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS / DELETES — PAGOS
    // ─────────────────────────────────────────────────────────────────────────

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO compras_pagos (id_compra, forma_pago, total, plazo, unidad_tiempo) VALUES (?,?,?,?,?)";
        $this->query($sql, [
            (int)   $data['id_compra'],
            (string)($data['forma_pago'] ?? '01'), // Mantener como string para SRI (ej: "01")
            (float) ($data['total'] ?? 0),
            (int)   ($data['plazo'] ?? 0),
            $data['unidad_tiempo'] ?? 'dias',
        ]);
    }

    public function deletePagos(int $idCompra): void
    {
        $this->query("DELETE FROM compras_pagos WHERE id_compra = ?", [$idCompra]);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO compras_adicional (id_compra, nombre, valor) VALUES (?, ?, ?)";
        $this->query($sql, [
            (int)   $data['id_compra'],
            $data['nombre'],
            $data['valor'],
        ]);
    }

    public function deleteInfoAdicional(int $idCompra): void
    {
        $this->query("DELETE FROM compras_adicional WHERE id_compra = ?", [$idCompra]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS / DELETES — RETENCIONES
    // ─────────────────────────────────────────────────────────────────────────





    // ─────────────────────────────────────────────────────────────────────────
    // ESTADO / ELIMINACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $this->query(
            "UPDATE compras_cabecera SET estado = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
            [$estado, $idUsuario, $id]
        );
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $this->query(
            "UPDATE compras_cabecera
             SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?",
            [$idUsuario, $idUsuario, $id]
        );
    }

    public function getEgresosAsociados(int $idCompra, int $idEmpresa): array
    {
        $sql = "SELECT ec.id 
                FROM egresos_cabecera ec
                INNER JOIN egresos_detalle ed ON ec.id = ed.id_egreso
                WHERE ed.tipo_documento = 'COMPRA'
                  AND ed.id_referencia_documento = ?
                  AND ed.eliminado = FALSE
                  AND ec.id_empresa = ?
                  AND ec.eliminado = FALSE
                  AND ec.estado != 'anulado'";
        
        $st = $this->query($sql, [$idCompra, $idEmpresa]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    public function getEgresosVinculados(int $idCompra): array
    {
        $sql = "SELECT ed.monto_pagado, ec.id AS id_egreso, ec.numero_egreso, ec.fecha_emision, ec.estado, 
                       COALESCE(eoe.nombre, 'Sin Concepto') AS concepto_nombre,
                       (SELECT string_agg(fp.nombre, ', ') 
                        FROM egresos_pagos ep 
                        JOIN empresa_formas_pago fp ON ep.id_forma_pago = fp.id 
                        WHERE ep.id_egreso = ec.id AND ep.eliminado = FALSE) AS formas_pago
                FROM egresos_detalle ed
                JOIN egresos_cabecera ec ON ed.id_egreso = ec.id
                LEFT JOIN empresa_opciones_ingreso_egreso eoe ON ec.id_egreso_concepto = eoe.id
                WHERE ed.tipo_documento = 'COMPRA' 
                  AND ed.id_referencia_documento = ? 
                  AND ed.eliminado = FALSE 
                  AND ec.eliminado = FALSE
                ORDER BY ec.fecha_emision DESC";
        return $this->query($sql, [$idCompra])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function existeSecuencial(
        int $idEmpresa,
        int $idProveedor,
        string $estab,
        string $pto,
        string $sec,
        string $tipoComprobante,
        ?int $excluirId = null
    ): bool {
        $sql = "SELECT COUNT(*) FROM compras_cabecera
                WHERE id_empresa = ? AND id_proveedor = ?
                  AND establecimiento_prov = ? AND punto_emision_prov = ?
                  AND secuencial_prov = ? AND tipo_comprobante = ?
                  AND eliminado = FALSE";
        $params = [$idEmpresa, $idProveedor, $estab, $pto, $sec, $tipoComprobante];
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CATÁLOGOS
    // ─────────────────────────────────────────────────────────────────────────

    public function getFormasPago(): array
    {
        return $this->db->query(
            "SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query(
            "SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSustentosTributarios(): array
    {
        return $this->db->query(
            "SELECT * FROM sustento_tributario WHERE status = 1 ORDER BY codigo ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRetencionesDisponibles(string $tipoImpuesto = '', string $buscar = ''): array
    {
        $where = "WHERE status = 1";
        $params = [];
        if ($tipoImpuesto !== '') {
            $where .= " AND impuesto_ret = ?";
            $params[] = strtoupper($tipoImpuesto);
        }
        if ($buscar !== '') {
            $where .= " AND (codigo_ret ILIKE ? OR concepto_ret ILIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
        }
        // Intentar con ambos posibles nombres de PK
        try {
            return $this->query(
                "SELECT id AS id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret
                 FROM retenciones_sri $where ORDER BY codigo_ret ASC",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return $this->query(
                "SELECT id_ret AS id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret
                 FROM retenciones_sri $where ORDER BY codigo_ret ASC",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
}
