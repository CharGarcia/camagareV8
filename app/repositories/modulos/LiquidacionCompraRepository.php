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

        $parsed  = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $filtros = $parsed['filtros'];

        // Abonos de la liquidación, con la misma regla que Compras: pagos de Egresos
        // (tipo_documento LIQUIDACION, egreso no anulado) + retenciones no anuladas
        // enlazadas por id_liquidacion. Los usan los filtros "pago:", "saldo:",
        // "retenido:" y "retencion:" (no son columnas del listado).
        $sqlPagado   = "(SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id WHERE ed.tipo_documento = 'LIQUIDACION' AND ed.id_referencia_documento = l.id AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false)";
        $sqlRetenido = "(SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r WHERE r.id_liquidacion = l.id AND r.id_empresa = l.id_empresa AND r.eliminado = false AND r.estado != 'anulada')";
        $sqlAbonos   = "($sqlPagado + $sqlRetenido)";
        $saldo       = "GREATEST(0, l.importe_total - $sqlAbonos)";

        // Texto libre: las columnas del listado —número, secuencial, fecha, proveedor,
        // identificación, subtotal, descuento, total y usuario— más las observaciones.
        //
        // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar (mismo
        // criterio que Facturas de Venta, Compras y las notas de crédito/débito):
        //   - Correo y Estado → modal de filtros (decisión anterior).
        //   - Número de autorización y clave de acceso → filtros `autorizacion:` y
        //     `clave:` (17-09-2026). En un comprobante electrónico son el MISMO número de
        //     49 dígitos —fecha, RUC, serie, secuencial y un código numérico aleatorio de
        //     8—, así que al escribir un número de documento caía dentro de la clave de
        //     OTRAS liquidaciones por puro azar y el listado devolvía filas sin ninguna
        //     coincidencia visible.
        //   - Productos del detalle (código y descripción) → pestaña "Detalles" del modal
        //     de filtros (buscarEnDetalles()), que SÍ dice qué línea coincidió. De paso se
        //     va la subconsulta STRING_AGG, que corría por cada liquidación de la empresa.
        if ($parsed['texto_libre'] !== '') {
            // Rendimiento: montos y fecha solo se comparan si la palabra tiene dígitos
            // (ver FiltrosBusqueda::condicionTexto). La subconsulta del detalle va al final.
            $digitos = \App\Helpers\FiltrosBusqueda::SI_DIGITOS;
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('l.establecimiento', 'l.punto_emision', 'l.secuencial'), // Nº Liquidación (canónico)
                    'l.secuencial',
                    'p.razon_social',                                                 // Proveedor
                    'p.identificacion',                                               // Identificación
                    'u.nombre',                                                       // Usuario
                    // Fuera del listado, pero identifican la liquidación:
                    'l.observaciones',
                    ['sql' => 'l.fecha_emision', 'si' => $digitos],                   // Fecha
                    ['sql' => 'l.total_sin_impuestos', 'si' => $digitos],             // Subtotal
                    ['sql' => 'l.total_descuento', 'si' => $digitos],                 // Descuento
                    ['sql' => 'l.importe_total', 'si' => $digitos],                   // Total
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        // ── Estado de pago (CALCULADO): pago:pendiente | pago:abonada | pago:pagada ──
        $pagoFiltro = $filtros['estado_pago'] ?? $filtros['pago'] ?? null;
        unset($filtros['estado_pago'], $filtros['pago']);
        if ($pagoFiltro !== null) {
            $valores = is_array($pagoFiltro['valor']) ? $pagoFiltro['valor'] : [$pagoFiltro['valor']];
            $conds = [];
            foreach ($valores as $val) {
                $v2 = strtolower(trim((string) $val));
                if (in_array($v2, ['pagada', 'pagado', 'pagadas'], true)) {
                    $conds[] = "($saldo <= 0.01)";
                } elseif (in_array($v2, ['abonada', 'abonado', 'abonadas', 'parcial'], true)) {
                    $conds[] = "($saldo > 0.01 AND $sqlAbonos > 0)";
                } elseif (in_array($v2, ['pendiente', 'pendientes'], true)) {
                    $conds[] = "($saldo > 0.01 AND $sqlAbonos <= 0)";
                }
            }
            if ($conds) {
                $cond = '(' . implode(' OR ', $conds) . ')';
                $where .= !empty($pagoFiltro['neg']) ? " AND NOT $cond" : " AND $cond";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => "CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial)",
                'nro'            => "CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial)",
                // Claves del modal de filtros (public/js/components/filtros_modal.js).
                'autorizacion'   => "CONCAT_WS(' ', l.numero_autorizacion, l.clave_acceso)",
                'obs'            => 'l.observaciones',
                'observacion'    => 'l.observaciones',
                'usuario'        => 'u.nombre',
            ],
            'exacto'   => [
                'estado' => 'l.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001") del punto de
                // emisión propio de la empresa que registra la liquidación (no del
                // proveedor), tal como se muestra en el selector "Serie" del modal.
                'serie'  => "CONCAT(l.establecimiento,'-',l.punto_emision)",
                'estado_correo' => "COALESCE(NULLIF(l.estado_correo, ''), 'pendiente')",
                'id_usuario'    => 'l.id_usuario',
                'id_sustento'   => 'l.id_sustento_tributario',
                'asiento'       => "CASE WHEN l.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
                'retencion'     => "CASE WHEN EXISTS (SELECT 1 FROM retencion_compra_cabecera rx WHERE rx.id_liquidacion = l.id AND rx.id_empresa = l.id_empresa AND rx.eliminado = false AND rx.estado != 'anulada') THEN 'si' ELSE 'no' END",
            ],
            'fecha'    => [ 'fecha' => 'l.fecha_emision', 'fecha_emision' => 'l.fecha_emision' ],
            'numerico' => [
                'monto'      => 'l.importe_total',
                'total'      => 'l.importe_total',
                'secuencial' => 'l.secuencial::numeric',
                'subtotal'   => 'l.total_sin_impuestos',
                'descuento'  => 'COALESCE(l.total_descuento, 0)',
                'saldo'      => $saldo,
                'retenido'   => $sqlRetenido,
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND l.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'importe_total', 'total_sin_impuestos', 'total_descuento', 'estado', 'estado_correo', 'proveedor_nombre', 'proveedor_ruc', 'usuario_nombre', 'observaciones'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'proveedor_nombre' => 'p.razon_social',
            'proveedor_ruc'    => 'p.identificacion',
            'usuario_nombre'   => 'u.nombre',
            default            => "l.$ordenCol",
        };

        // Rendimiento (2026-09-16): conteo + página en UNA consulta (el WHERE con texto
        // libre o filtros de saldo se evalúa una sola vez). Ver App\Helpers\ListadoPaginado.
        $joins = "INNER JOIN proveedores p ON l.id_proveedor = p.id
                LEFT  JOIN usuarios    u ON l.id_usuario   = u.id";

        return \App\Helpers\ListadoPaginado::consultar(
            fn(string $sql, array $p) => $this->query($sql, $p)->fetchAll(),
            [
                'tabla'       => 'liquidaciones_cabecera',
                'alias'       => 'l',
                'joinsFiltro' => $joins,
                'joinsFinal'  => $joins,
                'where'       => $where,
                'orderBy'     => "ORDER BY $ordenExpr $ordenDir, l.id DESC",
                'perPage'     => $perPage,
                'conBusqueda' => trim($buscar) !== '',   // sin buscar: forma liviana (ids por índice + COUNT aparte)
                'offset'      => $offset,
                'select'      => "l.*,
                       p.razon_social    AS proveedor_nombre,
                       p.identificacion   AS proveedor_ruc,
                       u.nombre          AS usuario_nombre",
            ],
            $params
        );
    }

    /**
     * Series (establecimiento-punto_emision) con al menos un documento real
     * registrado, para el filtro "Serie" del buscador. A diferencia de
     * $puntos (armado en el controller para "qué serie puedo usar en un
     * documento NUEVO", solo con el punto configurado con secuencial), esto
     * incluye series de cualquier establecimiento y aunque el punto ya no
     * tenga secuencial configurado (mismo patrón que
     * FacturaVentaRepository::getSeriesDistintas()).
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM liquidaciones_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /** Usuarios que han registrado alguna liquidación en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConLiquidaciones(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM liquidaciones_cabecera l
                JOIN usuarios u ON u.id = l.id_usuario
                WHERE l.id_empresa = :id_empresa AND l.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Sustentos tributarios REALMENTE usados en liquidaciones de la empresa (select del modal de filtros). */
    public function getSustentosUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT st.id, st.codigo, st.nombre
                FROM liquidaciones_cabecera l
                JOIN sustento_tributario st ON st.id = l.id_sustento_tributario
                WHERE l.id_empresa = :id_empresa AND l.eliminado = false
                ORDER BY st.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las liquidaciones (pestaña "Detalles" del modal de
     * filtros): cada producto/servicio, forma de pago SRI y dato de información
     * adicional que coincide con el texto, con la liquidación a la que pertenece.
     * Mismo alcance que el listado (empresa, no eliminadas, ambiente) y registros
     * propios por l.id_usuario, igual que getListado(). Las tablas hijas no tienen
     * columna `eliminado`.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "l.id_empresa = :id_empresa AND l.eliminado = false
                      AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND l.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.codigo_principal', 'd.codigo_auxiliar', 'd.descripcion', 'd.info_adicional', 'd.cantidad::text', 'd.precio_unitario::text', 'd.precio_total_sin_impuesto::text'],
            $q, $params, 'dt'
        );
        $condPago = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['fp.nombre', 'lp.forma_pago', 'lp.total::text', 'lp.plazo::text', 'lp.unidad_tiempo'],
            $q, $params, 'pg'
        );
        $condAdic = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['a.nombre', 'a.valor'],
            $q, $params, 'ad'
        );
        if ($condDet === '' || $condPago === '' || $condAdic === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT l.id, l.establecimiento, l.punto_emision, l.secuencial,
                           l.fecha_emision, l.estado, p.razon_social AS proveedor
                    FROM liquidaciones_cabecera l
                    INNER JOIN proveedores p ON p.id = l.id_proveedor
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(d.codigo_principal, ''), d.codigo_auxiliar) AS tipo,
                           d.descripcion,
                           d.cantidad,
                           d.precio_total_sin_impuesto AS monto,
                           b.*
                    FROM liquidaciones_detalle d
                    JOIN base b ON b.id = d.id_cabecera
                    WHERE $condDet
                    UNION ALL
                    SELECT 'PAGO' AS origen,
                           COALESCE(fp.nombre, lp.forma_pago) AS tipo,
                           CASE WHEN COALESCE(lp.plazo, 0) > 0
                                THEN CONCAT_WS(' ', lp.plazo::text, NULLIF(lp.unidad_tiempo, ''))
                                ELSE 'Contado' END AS descripcion,
                           NULL AS cantidad,
                           lp.total AS monto,
                           b.*
                    FROM liquidaciones_pagos lp
                    JOIN base b ON b.id = lp.id_cabecera
                    LEFT JOIN formas_pago_sri fp ON fp.codigo = lp.forma_pago
                    WHERE $condPago
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           NULL AS monto,
                           b.*
                    FROM liquidaciones_adicional a
                    JOIN base b ON b.id = a.id_cabecera
                    WHERE $condAdic
                ) x
                ORDER BY x.fecha_emision DESC, x.id DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Liquidaciones de compra del rango de fechas para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE l.id_empresa = :id_empresa AND l.eliminado = false
                   AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                   " . $this->condicionRangoDescargaMasiva('l.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params);
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND l.id_usuario = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT l.id, l.establecimiento, l.punto_emision, l.secuencial, l.fecha_emision, l.estado
                FROM liquidaciones_cabecera l
                $where
                ORDER BY l.fecha_emision ASC, l.id ASC";
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT l.*,
                       p.razon_social         AS proveedor_nombre,
                       p.identificacion       AS proveedor_ruc,
                       p.direccion            AS proveedor_direccion,
                       p.email                AS proveedor_email,
                       p.telefono             AS proveedor_telefono,
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

    /**
     * Impuestos de VARIAS líneas en UNA sola consulta, agrupados por línea.
     *
     * Evita el N+1 de llamar a getImpuestosDetalle() dentro del bucle de
     * detalles: con la base en un servidor remoto, un documento de 30 líneas
     * pagaba 30 viajes de red solo para esto.
     *
     * @param int[] $idsDetalle
     * @return array<int,array> id de la línea => sus impuestos
     */
    public function getImpuestosPorDetalles(array $idsDetalle): array
    {
        $ids = array_values(array_unique(array_filter(array_map("intval", $idsDetalle))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(",", array_fill(0, count($ids), "?"));
        $sql = "SELECT * FROM liquidaciones_detalle_impuestos WHERE id_detalle IN ($ph)";

        $porDetalle = [];
        foreach ($this->query($sql, $ids)->fetchAll() as $imp) {
            $porDetalle[(int) $imp["id_detalle"]][] = $imp;
        }
        return $porDetalle;
    }

    /**
     * Vincula el asiento contable generado a la liquidación de compra.
     */
    public function updateAsientoContable(int $idLiquidacion, int $idAsiento): void
    {
        $this->query("UPDATE liquidaciones_cabecera SET id_asiento_contable = ? WHERE id = ?", [$idAsiento, $idLiquidacion]);
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
