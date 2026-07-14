<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;

class FacturaVentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
        try {
            $this->db->exec("ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
        } catch (\Throwable $e) {}
        try {
            $this->db->exec("ALTER TABLE ventas_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
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

        $where = "WHERE v.id_empresa = :id_empresa AND v.eliminado = FALSE AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        // Parsear filtros (sintaxis tipo "clave:valor") y texto libre
        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        // Texto libre: busca en número, nombre cliente, RUC, observaciones
        if ($textoLibre !== '') {
            $where .= " AND (CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :buscar
                          OR c.nombre ILIKE :buscar
                          OR c.identificacion ILIKE :buscar
                          OR v.observaciones ILIKE :buscar)";
            $params[':buscar'] = "%$textoLibre%";
        }

        // ── Filtro especial: estado de pago (campo CALCULADO, no es columna) ──────
        // Sintaxis: pago:pendiente | pago:abonada | pago:pagada (acepta sinónimos
        // y lista, p. ej. pago:pendiente,abonada). Se resuelve con las mismas
        // sumatorias (cobros + notas de crédito + retenciones) que la columna.
        $pagoFiltro = $filtros['estado_pago'] ?? $filtros['pago'] ?? null;
        unset($filtros['estado_pago'], $filtros['pago']);
        if ($pagoFiltro !== null) {
            $sqlAbonos =
                "((SELECT COALESCE(SUM(ind.monto_cobrado),0) FROM ingresos_detalle ind "
                . "INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id "
                . "WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'FACTURA' "
                . "AND inc.estado != 'anulado' AND inc.eliminado = false) "
                . "+ (SELECT COALESCE(SUM(nc.importe_total),0) FROM notas_credito_cabecera nc "
                . "WHERE nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) "
                . "AND nc.id_empresa = v.id_empresa AND nc.estado != 'anulado' AND nc.eliminado = false) "
                . "+ (SELECT COALESCE(SUM(r.total_renta + r.total_iva + r.total_isd),0) FROM retencion_venta_cabecera r "
                . "WHERE r.eliminado = false AND r.id_empresa = v.id_empresa AND (r.id_venta = v.id "
                . "OR EXISTS (SELECT 1 FROM retencion_venta_detalle rd WHERE rd.id_retencion = r.id "
                . "AND rd.num_doc_sustento = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)))))";
            $saldo = "(v.importe_total - $sqlAbonos)";

            $valores = is_array($pagoFiltro['valor']) ? $pagoFiltro['valor'] : [$pagoFiltro['valor']];
            $conds = [];
            foreach ($valores as $val) {
                $v2 = strtolower(trim((string)$val));
                if (in_array($v2, ['pagada', 'pagado', 'pagadas', 'cobrada', 'cobrado'], true)) {
                    $conds[] = "$saldo <= 0.01";
                } elseif (in_array($v2, ['abonada', 'abonado', 'abonadas', 'parcial', 'abono'], true)) {
                    $conds[] = "($saldo > 0.01 AND $sqlAbonos > 0)";
                } elseif (in_array($v2, ['pendiente', 'pendientes', 'falta', 'sinpago', 'impaga', 'impagada'], true)) {
                    $conds[] = "($saldo > 0.01 AND $sqlAbonos <= 0)";
                }
            }
            if ($conds) {
                $cond = '(' . implode(' OR ', $conds) . ')';
                if (!empty($pagoFiltro['neg'])) $cond = "NOT $cond";
                $where .= " AND $cond";
            }
        }

        // Aplicar filtros estructurados usando el helper genérico
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => "CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)",
                'nro'            => "CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)",
                'vendedor'       => 'ven.nombre',
                'usuario'        => 'u.nombre',
                'obs'            => 'v.observaciones',
                'observacion'    => 'v.observaciones',
                'autorizacion'   => 'v.numero_autorizacion',
                'clave'          => 'v.clave_acceso',
                'clave_acceso'   => 'v.clave_acceso',
            ],
            'exacto' => [
                'estado'         => 'v.estado',
                'estado_correo'  => 'v.estado_correo',
                'correo'         => 'v.estado_correo',
                'estab'          => 'v.establecimiento',
                'establecimiento' => 'v.establecimiento',
                'punto'          => 'v.punto_emision',
                'punto_emision'  => 'v.punto_emision',
            ],
            'fecha' => [
                'fecha'         => 'v.fecha_emision',
                'fecha_emision' => 'v.fecha_emision',
            ],
            'numerico' => [
                'monto'     => 'v.importe_total',
                'total'     => 'v.importe_total',
                'subtotal'  => 'v.total_sin_impuestos',
                'descuento' => 'v.total_descuento',
                'ice'       => 'COALESCE(v.total_ice,0)',
                'propina'   => 'COALESCE(v.propina,0)',
                'iva'       => '(v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice,0) - COALESCE(v.propina,0))',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND v.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*) FROM ventas_cabecera v
                     INNER JOIN clientes   c   ON v.id_cliente  = c.id
                     LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                     LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id
                     $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'numero', 'importe_total', 'total_sin_impuestos', 'total_descuento', 'total_ice', 'propina', 'estado', 'estado_correo', 'estado_pago', 'cliente_nombre', 'cliente_ruc', 'vendedor_nombre', 'usuario_nombre', 'observaciones', 'iva'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        // Suma de abonos (cobros + notas de crédito + retenciones) para ordenar por
        // el ESTADO DE PAGO calculado. Mismas subconsultas que la columna/badge.
        $sqlAbonosOrden =
            "((SELECT COALESCE(SUM(ind.monto_cobrado),0) FROM ingresos_detalle ind "
            . "INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id "
            . "WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'FACTURA' "
            . "AND inc.estado != 'anulado' AND inc.eliminado = false) "
            . "+ (SELECT COALESCE(SUM(nc.importe_total),0) FROM notas_credito_cabecera nc "
            . "WHERE nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) "
            . "AND nc.id_empresa = v.id_empresa AND nc.estado != 'anulado' AND nc.eliminado = false) "
            . "+ (SELECT COALESCE(SUM(r.total_renta + r.total_iva + r.total_isd),0) FROM retencion_venta_cabecera r "
            . "WHERE r.eliminado = false AND r.id_empresa = v.id_empresa AND (r.id_venta = v.id "
            . "OR EXISTS (SELECT 1 FROM retencion_venta_detalle rd WHERE rd.id_retencion = r.id "
            . "AND rd.num_doc_sustento = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)))))";

        // Para columnas calculadas (JOIN) se prefija la tabla correcta
        $ordenExpr = match($ordenCol) {
            'cliente_nombre'  => 'c.nombre',
            'cliente_ruc'     => 'c.identificacion',
            'vendedor_nombre' => 'ven.nombre',
            'usuario_nombre'  => 'u.nombre',
            'iva'             => '(v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice,0) - COALESCE(v.propina,0))',
            'numero'          => 'v.secuencial',
            'estado_pago'     => "CASE WHEN v.estado = 'anulado' THEN 4 "
                                 . "WHEN (v.importe_total - $sqlAbonosOrden) <= 0.01 THEN 3 "
                                 . "WHEN $sqlAbonosOrden > 0 THEN 2 ELSE 1 END",
            default           => "v.$ordenCol",
        };

        $sql = "SELECT v.*,
                       c.nombre        AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       ven.nombre      AS vendedor_nombre,
                       u.nombre        AS usuario_nombre,
                       (SELECT COALESCE(SUM(ind.monto_cobrado), 0) FROM ingresos_detalle ind INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'FACTURA' AND inc.estado != 'anulado' AND inc.eliminado = false) AS total_cobrado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM notas_credito_cabecera nc WHERE nc.num_doc_modificado = CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AND nc.id_empresa = v.id_empresa AND nc.estado != 'anulado' AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_renta + r.total_iva + r.total_isd), 0) FROM retencion_venta_cabecera r WHERE r.eliminado = false AND r.id_empresa = v.id_empresa AND (r.id_venta = v.id OR EXISTS (SELECT 1 FROM retencion_venta_detalle rd WHERE rd.id_retencion = r.id AND rd.num_doc_sustento = CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial)))) AS total_retencion
                FROM ventas_cabecera v
                INNER JOIN clientes  c   ON v.id_cliente  = c.id
                LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id
                $where
                ORDER BY $ordenExpr $ordenDir
                " . ($perPage > 0 ? "LIMIT $perPage OFFSET $offset" : "");

        $rows = $this->query($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    /**
     * Facturas autorizadas/aprobadas de un cliente, para seleccionarlas como
     * documento a modificar en una Nota de Crédito. Filtro opcional de texto
     * sobre el número de comprobante (establecimiento-punto-secuencial).
     */
    public function getFacturasPorCliente(int $idEmpresa, int $idCliente, string $buscar = '', int $limit = 30): array
    {
        $params = [':id_empresa' => $idEmpresa, ':id_cliente' => $idCliente];
        $where  = "WHERE v.id_empresa = :id_empresa
                     AND v.eliminado = FALSE
                     AND v.id_cliente = :id_cliente
                     AND v.estado IN ('autorizado','aprobado')
                     AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $buscar = trim($buscar);
        if ($buscar !== '') {
            $where .= " AND CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :buscar";
            $params[':buscar'] = "%$buscar%";
        }

        $sql = "SELECT v.id, v.establecimiento, v.punto_emision, v.secuencial,
                       v.fecha_emision, v.importe_total, v.estado,
                       v.id_cliente, c.nombre AS cliente_nombre, c.identificacion AS cliente_ruc
                FROM ventas_cabecera v
                INNER JOIN clientes c ON v.id_cliente = c.id
                $where
                ORDER BY v.fecha_emision DESC, v.id DESC
                LIMIT $limit";
        return $this->query($sql, $params)->fetchAll();
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT v.*,
                       c.nombre              AS cliente_nombre,
                       c.identificacion      AS cliente_ruc,
                       c.direccion           AS cliente_direccion,
                       c.email               AS cliente_email,
                       c.tipo_id             AS cliente_tipo_id,
                       c.plazo               AS cliente_plazo,
                       COALESCE(icv.nombre,'') AS cliente_nombre_tipo_id,
                       ven.nombre            AS vendedor_nombre,
                       u.nombre              AS usuario_nombre,
                       uc.nombre             AS creado_por_nombre,
                       uu.nombre             AS actualizado_por_nombre
                FROM ventas_cabecera v
                INNER JOIN clientes   c   ON v.id_cliente  = c.id
                LEFT  JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id
                LEFT  JOIN usuarios   uc  ON v.created_by  = uc.id
                LEFT  JOIN usuarios   uu  ON v.updated_by  = uu.id
                WHERE v.id = ? AND v.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    /**
     * Devuelve las facturas (no eliminadas) generadas desde una proforma.
     */
    public function getPorProforma(int $idProforma, int $idEmpresa): array
    {
        $sql = "SELECT id, fecha_emision, establecimiento, punto_emision, secuencial,
                       importe_total, estado, estado_correo
                FROM ventas_cabecera
                WHERE id_proforma = ? AND id_empresa = ? AND eliminado = false
                ORDER BY fecha_emision DESC, id DESC";
        return $this->query($sql, [$idProforma, $idEmpresa])->fetchAll();
    }

    /**
     * Persiste el XML (sin firma o firmado/autorizado) en detalle_xml.
     */
    public function updateDetalleXml(int $id, string $xml): void
    {
        $st = $this->db->prepare(
            "UPDATE ventas_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$estado, $idUsuario, $id]);
    }

    public function actualizarVendedor(int $id, ?int $idVendedor, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera SET id_vendedor = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$idVendedor, $idUsuario, $id]);
    }



    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera 
                SET eliminado = true, 
                    deleted_at = CURRENT_TIMESTAMP, 
                    deleted_by = ?,
                    updated_by = ?,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $this->db->prepare($sql)->execute([$idUsuario, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ventas_cabecera
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ventas_cabecera
                SET estado = 'anulado', updated_at = NOW(), updated_by = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function getDetalles(int $idVenta): array
    {
        // El JOIN de unidades_medida NO filtra por eliminado/status: un documento
        // histórico debe seguir mostrando su unidad aunque el catálogo cambie.
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) as producto_nombre, p.codigo as producto_codigo,
                       p.id_tipo_medida, p.id_medida as id_medida_base,
                       p.tipo_produccion, p.inventariable,
                       um.abreviatura as unidad_abreviatura, um.nombre as unidad_nombre
                FROM ventas_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                LEFT JOIN unidades_medida um ON um.id = COALESCE(d.id_unidad_medida, p.id_medida)
                WHERE d.id_venta = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idVenta])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM ventas_detalle_impuestos WHERE id_venta_detalle = ?";
        return $this->query($sql, [$idDetalle])->fetchAll();
    }

    public function getPagos(int $idVenta): array
    {
        $sql = "SELECT vp.*, COALESCE(fps.nombre, vp.forma_pago) AS nombre_forma_pago
                FROM ventas_pagos vp
                LEFT JOIN formas_pago_sri fps ON fps.codigo = vp.forma_pago
                WHERE vp.id_venta = ?
                ORDER BY vp.id ASC";
        return $this->query($sql, [$idVenta])->fetchAll();
    }

    public function getInfoAdicional(int $idVenta): array
    {
        $sql = "SELECT * FROM ventas_adicional WHERE id_venta = ?";
        return $this->query($sql, [$idVenta])->fetchAll();
    }

    public function insertCabecera(array $data): int
    {
        $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;

        // Columnas y valores base (siempre presentes en el schema original)
        $cols   = [
            'id_empresa', 'id_establecimiento', 'id_punto_emision', 'id_cliente', 'id_usuario',
            'fecha_emision', 'establecimiento', 'punto_emision', 'secuencial',
            'total_sin_impuestos', 'total_descuento', 'importe_total', 'propina', 'moneda', 'estado',
            'id_vendedor', 'dias_credito', 'observaciones',
            'created_by', 'updated_by',
        ];
        $params = [
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            (int) $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            (float) $data['total_sin_impuestos'],
            (float) $data['total_descuento'],
            (float) $data['importe_total'],
            (float) ($data['propina'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            $data['estado'] ?? 'borrador',
            $idVendedor,
            (int) ($data['dias_credito'] ?? 0),
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ];

        // Columnas opcionales: se agregan solo si existen en la tabla
        $colsOpcionales = $this->columnasExistentes('ventas_cabecera');

        if (in_array('total_ice', $colsOpcionales)) {
            $cols[]   = 'total_ice';
            $params[] = (float) ($data['total_ice'] ?? 0);
        }
        if (in_array('plazo', $colsOpcionales)) {
            $cols[]   = 'plazo';
            $params[] = !empty($data['plazo']) ? $data['plazo'] : null;
        }
        if (in_array('tipo_ambiente', $colsOpcionales)) {
            $cols[]   = 'tipo_ambiente';
            $params[] = $data['tipo_ambiente'] ?? '1';
        }
        if (in_array('tipo_emision', $colsOpcionales)) {
            $cols[]   = 'tipo_emision';
            $params[] = $data['tipo_emision'] ?? '1';
        }
        if (in_array('estado_correo', $colsOpcionales)) {
            $cols[]   = 'estado_correo';
            $params[] = $data['estado_correo'] ?? 'pendiente';
        }
        if (in_array('clave_acceso', $colsOpcionales) && !empty($data['clave_acceso'])) {
            $cols[]   = 'clave_acceso';
            $params[] = $data['clave_acceso'];
        }
        if (in_array('id_proforma', $colsOpcionales) && !empty($data['id_proforma'])) {
            $cols[]   = 'id_proforma';
            $params[] = (int) $data['id_proforma'];
        }

        $colSql  = implode(', ', $cols);
        $valSql  = implode(', ', array_fill(0, count($params), '?'));
        $sql     = "INSERT INTO ventas_cabecera ({$colSql}) VALUES ({$valSql}) RETURNING id";

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    /** Devuelve (y cachea) las columnas existentes de una tabla. */
    private array $colsCache = [];
    private function columnasExistentes(string $tabla): array
    {
        if (!isset($this->colsCache[$tabla])) {
            $st = $this->db->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'"
            );
            $st->execute([$tabla]);
            $this->colsCache[$tabla] = $st->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $this->colsCache[$tabla];
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM ventas_cabecera 
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ? 
                  AND secuencial = ? AND eliminado = FALSE";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];

        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }

        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function insertDetalle(array $data): int
    {
        $cols   = [
            'id_venta', 'id_producto', 'id_bodega', 'id_unidad_medida',
            'codigo_principal', 'codigo_auxiliar',
            'descripcion', 'cantidad', 'precio_unitario', 'descuento', 'precio_total_sin_impuesto',
        ];
        $params = [
            (int) $data['id_venta'],
            !empty($data['id_producto']) ? (int)$data['id_producto'] : null,
            !empty($data['id_bodega'])         ? (int) $data['id_bodega']         : null,
            !empty($data['id_unidad_medida'])   ? (int) $data['id_unidad_medida']  : (!empty($data['id_medida']) ? (int)$data['id_medida'] : null),
            $data['codigo_principal'],
            !empty($data['codigo_auxiliar'])    ? $data['codigo_auxiliar']         : null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'],
            $data['precio_total_sin_impuesto'],
        ];

        $colsOpcionales = $this->columnasExistentes('ventas_detalle');

        if (in_array('id_tarifa_iva', $colsOpcionales)) {
            $cols[] = 'id_tarifa_iva';
            $params[] = (int) ($data['id_tarifa_iva'] ?? 0);
        }

        if (in_array('casillero', $colsOpcionales)) {
            $cols[] = 'casillero';
            $params[] = !empty($data['casillero']) ? (string)$data['casillero'] : null;
        }

        if (in_array('info_adicional', $colsOpcionales)) {
            $cols[]   = 'info_adicional';
            $params[] = $data['info_adicional'] ?? null;
        }

        $colSql = implode(', ', $cols);
        $valSql = implode(', ', array_fill(0, count($params), '?'));
        $sql    = "INSERT INTO ventas_detalle ({$colSql}) VALUES ({$valSql}) RETURNING id";

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO ventas_detalle_impuestos (
                    id_venta_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        $this->query($sql, [
            $data['id_venta_detalle'], $data['codigo_impuesto'], $data['codigo_porcentaje'], $data['tarifa'], $data['base_imponible'], $data['valor']
        ]);
    }

    /**
     * Obtiene todos los impuestos de los detalles de una factura.
     */
    public function getImpuestosPorVenta(int $idVenta): array
    {
        $sql = "SELECT i.*, d.id_venta 
                FROM ventas_detalle_impuestos i
                JOIN ventas_detalle d ON i.id_venta_detalle = d.id
                WHERE d.id_venta = ?";
        return $this->query($sql, [$idVenta])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO ventas_pagos (id_venta, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)";
        $this->query($sql, [$data['id_venta'], $data['forma_pago'], $data['total'], $data['plazo'] ?? 0, $data['unidad_tiempo'] ?? 'dias']);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO ventas_adicional (id_venta, nombre, valor) VALUES (?, ?, ?)";
        $this->query($sql, [$data['id_venta'], $data['nombre'], $data['valor']]);
    }

    public function updateCabecera(int $id, array $data): void
    {
        $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;

        $sets   = [
            'id_establecimiento  = ?',
            'id_punto_emision    = ?',
            'id_cliente          = ?',
            'fecha_emision       = ?',
            'establecimiento     = ?',
            'punto_emision       = ?',
            'secuencial          = ?',
            'total_sin_impuestos = ?',
            'total_descuento     = ?',
            'importe_total       = ?',
            'propina             = ?',
            'id_vendedor         = ?',
            'dias_credito        = ?',
            'observaciones       = ?',
            'updated_by          = ?',
            'updated_at          = NOW()',
        ];
        $params = [
            (int)   $data['id_establecimiento'],
            (int)   $data['id_punto_emision'],
            (int)   $data['id_cliente'],
                    $data['fecha_emision'],
                    $data['establecimiento'],
                    $data['punto_emision'],
                    $data['secuencial'],
            (float) $data['total_sin_impuestos'],
            (float) $data['total_descuento'],
            (float) $data['importe_total'],
            (float) ($data['propina'] ?? 0),
                    $idVendedor,
            (int)   ($data['dias_credito'] ?? 0),
                    !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int)   $data['id_usuario'],
        ];

        $colsOpcionales = $this->columnasExistentes('ventas_cabecera');
        if (in_array('total_ice', $colsOpcionales)) {
            $sets[]   = 'total_ice = ?';
            $params[] = (float) ($data['total_ice'] ?? 0);
        }
        if (in_array('plazo', $colsOpcionales)) {
            $sets[]   = 'plazo = ?';
            $params[] = !empty($data['plazo']) ? $data['plazo'] : null;
        }
        if (in_array('clave_acceso', $colsOpcionales) && !empty($data['clave_acceso'])) {
            $sets[]   = 'clave_acceso = ?';
            $params[] = $data['clave_acceso'];
        }

        $params[] = $id;
        $params[] = (int) $data['id_empresa'];

        $sql = "UPDATE ventas_cabecera SET " . implode(', ', $sets) . " WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $this->query($sql, $params);
    }

    public function deleteDetalles(int $idVenta): void
    {
        // Eliminar impuestos primero (FK)
        $ids = $this->query("SELECT id FROM ventas_detalle WHERE id_venta = ?", [$idVenta])->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->query("DELETE FROM ventas_detalle_impuestos WHERE id_venta_detalle IN ($placeholders)", $ids);
        }
        $this->query("DELETE FROM ventas_detalle WHERE id_venta = ?", [$idVenta]);
    }

    public function deletePagos(int $idVenta): void
    {
        $this->query("DELETE FROM ventas_pagos WHERE id_venta = ?", [$idVenta]);
    }

    public function deleteInfoAdicional(int $idVenta): void
    {
        $this->query("DELETE FROM ventas_adicional WHERE id_venta = ?", [$idVenta]);
    }











    /**
     * Crea un producto tipo "servicio" con código secuencial al vuelo (facturación libre).
     * Retorna el ID del producto creado.
     */
    public function crearServicioLibre(int $idEmpresa, int $idUsuario, string $nombre, float $precio, ?float $porcentajeIva = null, ?string $codigoPorcentaje = null): int
    {
        $productoRepo = new ProductoRepository();
        $codigo = $productoRepo->getSiguienteCodigo($idEmpresa, '02'); // Genera S001, S002, etc.

        // Resolver la tarifa de IVA por el codigoPorcentaje del SRI cuando venga (distingue
        // 0% / Exento / No objeto, que comparten porcentaje 0); si no, por el porcentaje.
        $idTarifaIva = null;
        if ($codigoPorcentaje !== null && $codigoPorcentaje !== '') {
            $stIva = $this->db->prepare("SELECT id FROM tarifa_iva WHERE codigo = :c LIMIT 1");
            $stIva->execute([':c' => $codigoPorcentaje]);
            $idTarifaIva = $stIva->fetchColumn() ?: null;
        }
        if (!$idTarifaIva && $porcentajeIva !== null) {
            $stIva = $this->db->prepare("SELECT id FROM tarifa_iva WHERE porcentaje_iva = :p AND status = 1 ORDER BY id LIMIT 1");
            $stIva->execute([':p' => $porcentajeIva]);
            $idTarifaIva = $stIva->fetchColumn() ?: null;
        }
        if (!$idTarifaIva) {
            $stIva = $this->db->prepare("SELECT id FROM tarifa_iva WHERE status = 1 ORDER BY id LIMIT 1");
            $stIva->execute();
            $idTarifaIva = $stIva->fetchColumn() ?: null;
        }

        $sql = "INSERT INTO productos (
                    id_empresa, id_usuario, created_by, updated_by, codigo, nombre,
                    codigo_auxiliar, codigo_barras, precio_base, tipo_produccion, tarifa_iva,
                    status, inventariable, eliminado, created_at
                ) VALUES (
                    :emp, :usr, :usr, :usr, :cod, :nom,
                    :cod, :cod, :precio, '02', :tarifa,
                    1, false, false, CURRENT_TIMESTAMP
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp'    => $idEmpresa,
            ':usr'    => $idUsuario,
            ':cod'    => $codigo,
            ':nom'    => $nombre,
            ':precio' => $precio,
            ':tarifa' => $idTarifaIva
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateDetalleLoteNup(int $idDetalle, array $data): void
    {
        $sql = "UPDATE ventas_detalle
                SET numero_lote = :lote, fecha_caducidad = :cad, nup = :nup
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':lote' => $data['numero_lote']     ?? null,
            ':cad'  => $data['fecha_caducidad'] ?? null,
            ':nup'  => $data['nup']             ?? null,
            ':id'   => $idDetalle,
        ]);
    }

    public function updateDetalleKardex(int $idDetalle, int $idKardex): void
    {
        $sql = "UPDATE ventas_detalle SET id_inventario_kardex = :k WHERE id = :id";
        $st  = $this->db->prepare($sql);
        $st->execute([':k' => $idKardex, ':id' => $idDetalle]);
    }

    /**
     * Retorna el tipo_id del cliente y si es consumidor final.
     * Detecta consumidor final por el nombre en identificador_comprador_vendedor (contiene 'consumidor')
     * o por la identificación '9999999999999'.
     */
    public function getTipoIdCliente(int $idCliente, int $idEmpresa): ?array
    {
        $sql = "SELECT c.tipo_id, c.identificacion,
                       COALESCE(icv.nombre, '') AS nombre_tipo_id
                FROM clientes c
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                WHERE c.id = ? AND c.id_empresa = ? AND c.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([$idCliente, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        $esConsumidorFinal = stripos($row['nombre_tipo_id'], 'consumidor') !== false
                          || $row['identificacion'] === '9999999999999';

        return [
            'tipo_id'            => $row['tipo_id'],
            'identificacion'     => $row['identificacion'],
            'nombre_tipo_id'     => $row['nombre_tipo_id'],
            'es_consumidor_final' => $esConsumidorFinal,
        ];
    }

    /**
     * Retorna el valor límite para consumidor final del establecimiento.
     * Retorna null si no hay límite configurado.
     */
    public function getValorLimiteConsumidorFinal(int $idEstablecimiento): ?float
    {
        $sql = "SELECT valor_limite_consumidor_final FROM empresa_establecimiento
                WHERE id = ? AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([$idEstablecimiento]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null || $val === '') return null;
        $f = (float) $val;
        return $f > 0 ? $f : null;
    }

    public function getFormasPago(): array
    {
        return $this->db->query("SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(): array
    {
        return $this->db->query("SELECT * FROM unidades_medida WHERE eliminado = false AND status = true ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getImpuestosConfig(): array
    {
        // Retornar una estructura básica de impuestos si es necesario, por ahora similar a tarifasIva
        return $this->getTarifasIva();
    }

    public function getPorNumeroCompleto(string $numero, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM ventas_cabecera 
                WHERE CONCAT(establecimiento,'-',punto_emision,'-',secuencial) = ? 
                  AND id_empresa = ? 
                  AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([$numero, $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function updateAsientoContable(int $id, ?int $idAsiento): void
    {
        $sql = "UPDATE ventas_cabecera SET id_asiento_contable = :id_asiento WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id_asiento' => $idAsiento, ':id' => $id]);
    }
}
