<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

/**
 * Repositorio del módulo Recibos de Venta.
 * Espejo de FacturaVentaRepository sobre las tablas recibos_venta_*.
 * Sin lógica SRI/XML/clave de acceso (el recibo no es un comprobante electrónico).
 */
class ReciboVentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('recibos_venta_cabecera');
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

        // Parsear filtros ("clave:valor") y texto libre
        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $where .= " AND (CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :buscar
                          OR c.nombre ILIKE :buscar
                          OR c.identificacion ILIKE :buscar
                          OR v.observaciones ILIKE :buscar)";
            $params[':buscar'] = "%$textoLibre%";
        }

        // Filtro especial: estado de pago (campo calculado con cobros de tipo RECIBO)
        $pagoFiltro = $filtros['estado_pago'] ?? $filtros['pago'] ?? null;
        unset($filtros['estado_pago'], $filtros['pago']);
        if ($pagoFiltro !== null) {
            $sqlAbonos =
                "(SELECT COALESCE(SUM(ind.monto_cobrado),0) FROM ingresos_detalle ind "
                . "INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id "
                . "WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'RECIBO' "
                . "AND inc.estado != 'anulado' AND inc.eliminado = false)";
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
            ],
            'exacto' => [
                'estado'          => 'v.estado',
                'estab'           => 'v.establecimiento',
                'establecimiento' => 'v.establecimiento',
                'punto'           => 'v.punto_emision',
                'punto_emision'   => 'v.punto_emision',
                'impuestos'       => 'v.con_impuestos',
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

        $sqlCount = "SELECT COUNT(*) FROM recibos_venta_cabecera v
                     INNER JOIN clientes   c   ON v.id_cliente  = c.id
                     LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                     LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id
                     $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'numero', 'importe_total', 'total_sin_impuestos', 'total_descuento', 'total_ice', 'propina', 'estado', 'cliente_nombre', 'cliente_ruc', 'vendedor_nombre', 'usuario_nombre', 'observaciones', 'iva'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'cliente_nombre'  => 'c.nombre',
            'cliente_ruc'     => 'c.identificacion',
            'vendedor_nombre' => 'ven.nombre',
            'usuario_nombre'  => 'u.nombre',
            'iva'             => '(v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice,0) - COALESCE(v.propina,0))',
            'numero'          => 'v.secuencial',
            default           => "v.$ordenCol",
        };

        $sql = "SELECT v.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       ven.nombre       AS vendedor_nombre,
                       u.nombre         AS usuario_nombre,
                       (SELECT COALESCE(SUM(ind.monto_cobrado), 0) FROM ingresos_detalle ind INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'RECIBO' AND inc.estado != 'anulado' AND inc.eliminado = false) AS total_cobrado
                FROM recibos_venta_cabecera v
                INNER JOIN clientes  c   ON v.id_cliente  = c.id
                LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => (int) $total];
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
                FROM recibos_venta_cabecera v
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

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE recibos_venta_cabecera SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$estado, $idUsuario, $id]);
    }

    public function actualizarVendedor(int $id, ?int $idVendedor, int $idUsuario): void
    {
        $sql = "UPDATE recibos_venta_cabecera SET id_vendedor = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$idVendedor, $idUsuario, $id]);
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $sql = "UPDATE recibos_venta_cabecera
                SET eliminado = true,
                    deleted_at = CURRENT_TIMESTAMP,
                    deleted_by = ?,
                    updated_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $this->db->prepare($sql)->execute([$idUsuario, $idUsuario, $id]);
    }

    public function getDetalles(int $idRecibo): array
    {
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) as producto_nombre, p.codigo as producto_codigo,
                       p.id_tipo_medida, p.id_medida as id_medida_base,
                       p.tipo_produccion, p.inventariable
                FROM recibos_venta_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                WHERE d.id_recibo = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idRecibo])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM recibos_venta_detalle_impuestos WHERE id_recibo_detalle = ?";
        return $this->query($sql, [$idDetalle])->fetchAll();
    }

    public function getPagos(int $idRecibo): array
    {
        $sql = "SELECT vp.*, COALESCE(fps.nombre, vp.forma_pago) AS nombre_forma_pago
                FROM recibos_venta_pagos vp
                LEFT JOIN formas_pago_sri fps ON fps.codigo = vp.forma_pago
                WHERE vp.id_recibo = ?
                ORDER BY vp.id ASC";
        return $this->query($sql, [$idRecibo])->fetchAll();
    }

    public function getInfoAdicional(int $idRecibo): array
    {
        $sql = "SELECT * FROM recibos_venta_adicional WHERE id_recibo = ?";
        return $this->query($sql, [$idRecibo])->fetchAll();
    }

    public function insertCabecera(array $data): int
    {
        $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;
        $numero = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');

        $sql = "INSERT INTO recibos_venta_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario, id_vendedor,
                    fecha_emision, establecimiento, punto_emision, secuencial, recibo_numero,
                    con_impuestos, total_sin_impuestos, total_descuento, total_ice, importe_total, propina,
                    moneda, estado, dias_credito, plazo, observaciones, tipo_ambiente,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_establecimiento, :id_punto_emision, :id_cliente, :id_usuario, :id_vendedor,
                    :fecha_emision, :establecimiento, :punto_emision, :secuencial, :recibo_numero,
                    :con_impuestos, :total_sin_impuestos, :total_descuento, :total_ice, :importe_total, :propina,
                    :moneda, :estado, :dias_credito, :plazo, :observaciones, :tipo_ambiente,
                    :created_by, :updated_by
                ) RETURNING id";

        $params = [
            ':id_empresa'          => (int) $data['id_empresa'],
            ':id_establecimiento'  => (int) $data['id_establecimiento'],
            ':id_punto_emision'    => (int) $data['id_punto_emision'],
            ':id_cliente'          => (int) $data['id_cliente'],
            ':id_usuario'          => (int) $data['id_usuario'],
            ':id_vendedor'         => $idVendedor,
            ':fecha_emision'       => $data['fecha_emision'],
            ':establecimiento'     => $data['establecimiento'],
            ':punto_emision'       => $data['punto_emision'],
            ':secuencial'          => $data['secuencial'],
            ':recibo_numero'       => $numero,
            ':con_impuestos'       => !empty($data['con_impuestos']) ? 'true' : 'false',
            ':total_sin_impuestos' => (float) $data['total_sin_impuestos'],
            ':total_descuento'     => (float) $data['total_descuento'],
            ':total_ice'           => (float) ($data['total_ice'] ?? 0),
            ':importe_total'       => (float) $data['importe_total'],
            ':propina'             => (float) ($data['propina'] ?? 0),
            ':moneda'              => $data['moneda'] ?? 'DOLAR',
            ':estado'              => $data['estado'] ?? 'emitido',
            ':dias_credito'        => (int) ($data['dias_credito'] ?? 0),
            ':plazo'               => !empty($data['plazo']) ? $data['plazo'] : null,
            ':observaciones'       => !empty($data['observaciones']) ? $data['observaciones'] : null,
            ':tipo_ambiente'       => $data['tipo_ambiente'] ?? '1',
            ':created_by'          => (int) $data['id_usuario'],
            ':updated_by'          => (int) $data['id_usuario'],
        ];

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Enlaza el recibo con la factura de origen (trazabilidad). Protegido: si la
     * columna id_factura_origen aún no existe (migración no aplicada), no falla.
     */
    public function setFacturaOrigen(int $idRecibo, int $idFactura): void
    {
        try {
            $sql = "UPDATE recibos_venta_cabecera SET id_factura_origen = :idf WHERE id = :id";
            $this->query($sql, [':idf' => $idFactura, ':id' => $idRecibo]);
        } catch (\Throwable $e) {
            error_log('[ReciboVenta] No se pudo enlazar id_factura_origen (¿migración pendiente?): ' . $e->getMessage());
        }
    }

    /**
     * Enlaza el recibo con el turno de caja (caja_sesiones) que lo cobró — solo
     * lo llena el POS. Protegido: si la columna id_caja_sesion aún no existe
     * (migración no aplicada), no falla.
     */
    public function setCajaSesion(int $idRecibo, ?int $idCajaSesion): void
    {
        if (empty($idCajaSesion)) {
            return;
        }
        try {
            $sql = "UPDATE recibos_venta_cabecera SET id_caja_sesion = :ics WHERE id = :id";
            $this->query($sql, [':ics' => $idCajaSesion, ':id' => $idRecibo]);
        } catch (\Throwable $e) {
            error_log('[ReciboVenta] No se pudo enlazar id_caja_sesion (¿migración pendiente?): ' . $e->getMessage());
        }
    }

    public function updateCabecera(int $id, array $data): void
    {
        $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;
        $numero = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');

        $sql = "UPDATE recibos_venta_cabecera SET
                    id_establecimiento  = :id_establecimiento,
                    id_punto_emision    = :id_punto_emision,
                    id_cliente          = :id_cliente,
                    fecha_emision       = :fecha_emision,
                    establecimiento     = :establecimiento,
                    punto_emision       = :punto_emision,
                    secuencial          = :secuencial,
                    recibo_numero       = :recibo_numero,
                    con_impuestos       = :con_impuestos,
                    total_sin_impuestos = :total_sin_impuestos,
                    total_descuento     = :total_descuento,
                    total_ice           = :total_ice,
                    importe_total       = :importe_total,
                    propina             = :propina,
                    id_vendedor         = :id_vendedor,
                    dias_credito        = :dias_credito,
                    plazo               = :plazo,
                    observaciones       = :observaciones,
                    updated_by          = :updated_by,
                    updated_at          = NOW()
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $params = [
            ':id_establecimiento'  => (int) $data['id_establecimiento'],
            ':id_punto_emision'    => (int) $data['id_punto_emision'],
            ':id_cliente'          => (int) $data['id_cliente'],
            ':fecha_emision'       => $data['fecha_emision'],
            ':establecimiento'     => $data['establecimiento'],
            ':punto_emision'       => $data['punto_emision'],
            ':secuencial'          => $data['secuencial'],
            ':recibo_numero'       => $numero,
            ':con_impuestos'       => !empty($data['con_impuestos']) ? 'true' : 'false',
            ':total_sin_impuestos' => (float) $data['total_sin_impuestos'],
            ':total_descuento'     => (float) $data['total_descuento'],
            ':total_ice'           => (float) ($data['total_ice'] ?? 0),
            ':importe_total'       => (float) $data['importe_total'],
            ':propina'             => (float) ($data['propina'] ?? 0),
            ':id_vendedor'         => $idVendedor,
            ':dias_credito'        => (int) ($data['dias_credito'] ?? 0),
            ':plazo'               => !empty($data['plazo']) ? $data['plazo'] : null,
            ':observaciones'       => !empty($data['observaciones']) ? $data['observaciones'] : null,
            ':updated_by'          => (int) $data['id_usuario'],
            ':id'                  => $id,
            ':id_empresa'          => (int) $data['id_empresa'],
        ];

        $this->query($sql, $params);
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM recibos_venta_cabecera
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
        $sql = "INSERT INTO recibos_venta_detalle (
                    id_recibo, id_producto, id_bodega, id_unidad_medida,
                    codigo_principal, codigo_auxiliar, descripcion, cantidad,
                    precio_unitario, descuento, precio_total_sin_impuesto,
                    id_tarifa_iva, casillero, info_adicional
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";

        $params = [
            (int) $data['id_recibo'],
            !empty($data['id_producto'])      ? (int) $data['id_producto']      : null,
            !empty($data['id_bodega'])        ? (int) $data['id_bodega']        : null,
            !empty($data['id_unidad_medida']) ? (int) $data['id_unidad_medida'] : (!empty($data['id_medida']) ? (int)$data['id_medida'] : null),
            $data['codigo_principal'] ?? null,
            !empty($data['codigo_auxiliar'])  ? $data['codigo_auxiliar']        : null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'],
            $data['precio_total_sin_impuesto'],
            (int) ($data['id_tarifa_iva'] ?? 0),
            !empty($data['casillero']) ? (string) $data['casillero'] : null,
            $data['info_adicional'] ?? null,
        ];

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO recibos_venta_detalle_impuestos (
                    id_recibo_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        $this->query($sql, [
            $data['id_recibo_detalle'], $data['codigo_impuesto'], $data['codigo_porcentaje'],
            $data['tarifa'], $data['base_imponible'], $data['valor']
        ]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO recibos_venta_pagos (id_recibo, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)";
        $this->query($sql, [$data['id_recibo'], $data['forma_pago'], $data['total'], $data['plazo'] ?? 0, $data['unidad_tiempo'] ?? 'dias']);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO recibos_venta_adicional (id_recibo, nombre, valor) VALUES (?, ?, ?)";
        $this->query($sql, [$data['id_recibo'], $data['nombre'], $data['valor']]);
    }

    public function deleteDetalles(int $idRecibo): void
    {
        $ids = $this->query("SELECT id FROM recibos_venta_detalle WHERE id_recibo = ?", [$idRecibo])->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->query("DELETE FROM recibos_venta_detalle_impuestos WHERE id_recibo_detalle IN ($placeholders)", $ids);
        }
        $this->query("DELETE FROM recibos_venta_detalle WHERE id_recibo = ?", [$idRecibo]);
    }

    public function deletePagos(int $idRecibo): void
    {
        $this->query("DELETE FROM recibos_venta_pagos WHERE id_recibo = ?", [$idRecibo]);
    }

    public function deleteInfoAdicional(int $idRecibo): void
    {
        $this->query("DELETE FROM recibos_venta_adicional WHERE id_recibo = ?", [$idRecibo]);
    }

    /**
     * Crea un producto tipo "servicio" con código secuencial al vuelo (facturación libre).
     */
    public function crearServicioLibre(int $idEmpresa, int $idUsuario, string $nombre, float $precio, ?float $porcentajeIva = null, ?string $codigoPorcentaje = null): int
    {
        $productoRepo = new ProductoRepository();
        $codigo = $productoRepo->getSiguienteCodigo($idEmpresa, '02');

        // Resolver por codigoPorcentaje del SRI cuando venga (distingue 0%/Exento/No objeto); si no, por %.
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
        $sql = "UPDATE recibos_venta_detalle
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
        $sql = "UPDATE recibos_venta_detalle SET id_inventario_kardex = :k WHERE id = :id";
        $st  = $this->db->prepare($sql);
        $st->execute([':k' => $idKardex, ':id' => $idDetalle]);
    }

    /**
     * Enlaza una línea de detalle con la variante de producto elegida
     * (Color/Talla, etc. — productos_variantes). Protegido: si la columna
     * id_producto_variante aún no existe (migración no aplicada), no falla.
     */
    public function setDetalleVariante(int $idDetalle, ?int $idProductoVariante): void
    {
        if (empty($idProductoVariante)) {
            return;
        }
        try {
            $sql = "UPDATE recibos_venta_detalle SET id_producto_variante = :idv WHERE id = :id";
            $this->query($sql, [':idv' => $idProductoVariante, ':id' => $idDetalle]);
        } catch (\Throwable $e) {
            error_log('[ReciboVenta] No se pudo enlazar id_producto_variante (¿migración pendiente?): ' . $e->getMessage());
        }
    }

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
            'tipo_id'             => $row['tipo_id'],
            'identificacion'      => $row['identificacion'],
            'nombre_tipo_id'      => $row['nombre_tipo_id'],
            'es_consumidor_final' => $esConsumidorFinal,
        ];
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
        return $this->getTarifasIva();
    }

    /** Tarifa de IVA (id, porcentaje_iva, codigo SRI) configurada en el producto, más su código principal. */
    public function getTarifaIvaProducto(int $idProducto): ?array
    {
        $sql = "SELECT ti.id, ti.porcentaje_iva, ti.codigo, p.codigo AS codigo_producto
                FROM productos p
                JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id = ?";
        $row = $this->query($sql, [$idProducto])->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Tarifa de IVA por id (id, porcentaje_iva, codigo SRI). */
    public function getTarifaIvaById(int $idTarifa): ?array
    {
        $sql = "SELECT id, porcentaje_iva, codigo FROM tarifa_iva WHERE id = ?";
        $row = $this->query($sql, [$idTarifa])->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateAsientoContable(int $id, ?int $idAsiento): void
    {
        $sql = "UPDATE recibos_venta_cabecera SET id_asiento_contable = :id_asiento WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id_asiento' => $idAsiento, ':id' => $id]);
    }
}
