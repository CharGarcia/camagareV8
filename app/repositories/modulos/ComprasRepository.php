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
        try {
            $this->db->exec("ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
        } catch (\Throwable $e) {}
        try {
            $this->db->exec("ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS id_orden_compra INTEGER REFERENCES ordenes_compra(id);");
        } catch (\Throwable $e) {}
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
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)",
                    'p.razon_social',
                    'p.identificacion',
                    'c.numero_autorizacion',
                    'c.observaciones',
                ],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
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
                // El estado dejó de ser un literal fijo con la aprobación de
                // compras: 'registrado', 'pendiente_aprobacion', 'rechazada'…
                'estado'           => 'c.estado',
                // Serie del PROVEEDOR (establecimiento_prov-punto_emision_prov): a
                // diferencia de los documentos que emite esta empresa, en Compras
                // la numeración es la del comprobante del proveedor.
                'serie'            => "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov)",
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
                // Comparación numérica exacta (no substring), igual que en los
                // demás módulos: "298" encuentra "000000298" sin escribir ceros.
                // A diferencia del secuencial propio (siempre numérico, generado
                // por el sistema), secuencial_prov lo escribe el usuario a mano
                // desde el comprobante del proveedor y a veces no es puro número
                // — el CASE evita que un valor no numérico rompa la consulta con
                // un error de cast; simplemente no calza con ningún filtro numérico.
                'secuencial' => "(CASE WHEN c.secuencial_prov ~ '^[0-9]+$' THEN c.secuencial_prov::numeric END)",
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
                       p.razon_social      AS proveedor_nombre,
                       p.identificacion    AS proveedor_ruc,
                       st.nombre           AS sustento_nombre,
                       st.codigo           AS sustento_codigo,
                       u.nombre            AS usuario_nombre,
                       ca.comprobante      AS tipo_comprobante_nombre,
                       (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false) AS total_pagado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc WHERE nc.tipo_comprobante = '04' AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada') AS total_retencion
                FROM compras_cabecera c
                INNER JOIN proveedores p        ON c.id_proveedor = p.id
                LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                LEFT  JOIN usuarios u            ON c.created_by = u.id
                LEFT  JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                $where
                ORDER BY $ordenExpr $ordenDir";


        if ($perPage > 0) {
            $sql .= " LIMIT $perPage OFFSET $offset";
        }

        $rows = $this->query($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    /**
     * Series del PROVEEDOR (establecimiento_prov-puntoEmision_prov) que
     * REALMENTE tienen al menos una compra guardada, para poblar el filtro
     * "Serie" del buscador.
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento_prov AS establecimiento, punto_emision_prov AS punto_emision
                FROM compras_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /**
     * Compras del rango de fechas para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     * No filtra por estado: una compra pendiente de aprobación ya está registrada
     * como documento recibido (lo que la aprobación detiene es pagarla, procesar
     * su inventario y asentarla), así que también debe salir en la descarga.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                   AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                   " . $this->condicionRangoDescargaMasiva('c.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params, 'fecha_emision', 'secuencial_prov');
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND c.created_by = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT c.id, c.establecimiento_prov AS establecimiento, c.punto_emision_prov AS punto_emision,
                       c.secuencial_prov AS secuencial, c.fecha_emision
                FROM compras_cabecera c
                $where
                ORDER BY c.fecha_emision ASC, c.id ASC";
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OBTENER POR ID
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Candado para el flujo "leer saldo → calcular → escribir" de un pago de compra
     * (CLAUDE.md §8). Llamar SIEMPRE antes de calcularSaldoPendiente() dentro de la
     * misma transacción del INSERT del egreso — se libera solo al COMMIT/ROLLBACK.
     */
    public function lockPago(int $idCompra, int $idEmpresa): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext(?))")
            ->execute(['pago_compra:' . $idEmpresa . ':' . $idCompra]);
    }

    /**
     * Saldo pendiente real de una compra, recalculado en el servidor (misma fórmula
     * que el listado: importe_total - pagado - notas_de_crédito - retenciones).
     * Llamar DESPUÉS de lockPago() dentro de la misma transacción — nunca confiar en
     * un saldo que mande el cliente, es la única forma de evitar que dos pagos
     * concurrentes contra la misma compra pasen ambos la validación de "no pagar de más".
     */
    public function calcularSaldoPendiente(int $idCompra, int $idEmpresa): float
    {
        $sql = "SELECT c.importe_total,
                       (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed
                          INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id
                        WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id
                          AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false) AS total_pagado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc
                        WHERE nc.tipo_comprobante = '04'
                          AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov)
                          AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r
                        WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada') AS total_retencion
                FROM compras_cabecera c
                WHERE c.id = ? AND c.id_empresa = ? AND c.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$idCompra, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }

        $saldo = (float) $row['importe_total'] - (float) $row['total_pagado'] - (float) $row['total_nc'] - (float) $row['total_retencion'];
        return max(0.0, $saldo);
    }

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
                       p.razon_social          AS proveedor_nombre,
                       p.identificacion        AS proveedor_ruc,
                       p.direccion             AS proveedor_direccion,
                       p.email                 AS proveedor_email,
                       p.tipo_id_proveedor     AS proveedor_tipo_id,
                       COALESCE(icv.nombre,'') AS proveedor_nombre_tipo_id,
                       st.nombre               AS sustento_nombre,
                       st.codigo               AS sustento_codigo,
                       uc.nombre               AS creado_por_nombre,
                       uu.nombre               AS actualizado_por_nombre,
                       ca.comprobante          AS tipo_comprobante_nombre,
                       oc.numero_orden         AS orden_compra_numero,
                       oc.estado               AS orden_compra_estado,
                       oc.fecha_orden          AS orden_compra_fecha,
                       (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false) AS total_pagado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc WHERE nc.tipo_comprobante = '04' AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada') AS total_retencion
                FROM compras_cabecera c
                INNER JOIN proveedores p ON c.id_proveedor = p.id
                LEFT  JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
                LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                LEFT  JOIN usuarios uc ON c.created_by  = uc.id
                LEFT  JOIN usuarios uu ON c.updated_by  = uu.id
                LEFT  JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                LEFT  JOIN ordenes_compra oc ON oc.id = c.id_orden_compra AND oc.eliminado = false
                $where";
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    /** Vincula (o desvincula, pasando null) esta compra con una orden de compra. */
    public function vincularOrdenCompra(int $idCompra, int $idEmpresa, ?int $idOrdenCompra, int $idUsuario): void
    {
        $sql = "UPDATE compras_cabecera SET
                    id_orden_compra = :id_orden_compra,
                    updated_at      = NOW(),
                    updated_by      = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $this->query($sql, [
            ':id_orden_compra' => $idOrdenCompra,
            ':updated_by'      => $idUsuario,
            ':id'              => $idCompra,
            ':id_empresa'      => $idEmpresa,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETALLES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ¿La compra proviene de una migración? (tiene fila en migracion_mysql_map).
     * Las compras migradas no se pueden editar.
     */
    public function esMigrado(int $id, int $idEmpresa): bool
    {
        $row = $this->query(
            "SELECT 1 FROM migracion_mysql_map
              WHERE entidad = 'compras' AND id_destino = ? AND id_empresa = ?
              LIMIT 1",
            [$id, $idEmpresa]
        )->fetchColumn();
        return (bool) $row;
    }

    /**
     * Suma del importe de las notas de crédito (tipo 04) que modifican esta compra.
     * Vínculo: nc.documento_modificado = numero de la compra + mismo proveedor/empresa.
     */
    public function getTotalNotasCredito(int $idCompra, int $idEmpresa): float
    {
        $sql = "SELECT COALESCE(SUM(nc.importe_total), 0)
                  FROM compras_cabecera nc
                  JOIN compras_cabecera c
                       ON nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov)
                      AND nc.id_proveedor = c.id_proveedor
                      AND nc.id_empresa   = c.id_empresa
                 WHERE c.id = ? AND c.id_empresa = ?
                   AND nc.tipo_comprobante = '04' AND nc.eliminado = false";
        return (float) $this->query($sql, [$idCompra, $idEmpresa])->fetchColumn();
    }

    /**
     * Documentos relacionados de una compra:
     *  - Si la compra es una nota de crédito (tipo 04): devuelve la FACTURA que modifica.
     *  - Si es una factura/compra: devuelve sus NOTAS DE CRÉDITO (tipo 04).
     * Cada documento incluye sus detalles (líneas).
     */
    public function getDocumentosRelacionados(int $idCompra, int $idEmpresa): array
    {
        $cab = $this->getPorId($idCompra, $idEmpresa);
        if (!$cab) {
            return ['relacion' => 'ninguno', 'documentos' => []];
        }

        $numero = ($cab['establecimiento_prov'] ?? '') . '-' . ($cab['punto_emision_prov'] ?? '') . '-' . ($cab['secuencial_prov'] ?? '');
        $esNota = ((string)($cab['tipo_comprobante'] ?? '')) === '04';

        if ($esNota) {
            // Buscar la factura que esta nota de crédito modifica.
            $relacion = 'factura';
            $sql = "SELECT c.id,
                           CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
                           c.fecha_emision, c.importe_total, c.total_sin_impuestos, c.tipo_comprobante,
                           ca.comprobante AS tipo_comprobante_nombre
                      FROM compras_cabecera c
                      LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                     WHERE c.id_empresa = ? AND c.eliminado = false
                       AND c.id_proveedor = ?
                       AND CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) = ?
                       AND c.tipo_comprobante NOT IN ('04', '05')
                     ORDER BY c.id ASC";
            $params = [$idEmpresa, (int)($cab['id_proveedor'] ?? 0), (string)($cab['documento_modificado'] ?? '')];
        } else {
            // Buscar las notas de crédito que modifican esta compra.
            $relacion = 'nota_credito';
            $sql = "SELECT c.id,
                           CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
                           c.fecha_emision, c.importe_total, c.total_sin_impuestos, c.tipo_comprobante,
                           ca.comprobante AS tipo_comprobante_nombre
                      FROM compras_cabecera c
                      LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                     WHERE c.id_empresa = ? AND c.eliminado = false
                       AND c.id_proveedor = ?
                       AND c.tipo_comprobante = '04'
                       AND c.documento_modificado = ?
                     ORDER BY c.id ASC";
            $params = [$idEmpresa, (int)($cab['id_proveedor'] ?? 0), $numero];
        }

        $docs = $this->query($sql, $params)->fetchAll();
        foreach ($docs as &$doc) {
            $doc['detalles'] = $this->getDetalles((int)$doc['id']);
        }
        unset($doc);

        return ['relacion' => $relacion, 'documentos' => $docs];
    }

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
        $sql = "SELECT * FROM compras_detalle_impuestos WHERE id_compra_detalle IN ($ph)";

        $porDetalle = [];
        foreach ($this->query($sql, $ids)->fetchAll() as $imp) {
            $porDetalle[(int) $imp["id_compra_detalle"]][] = $imp;
        }
        return $porDetalle;
    }

    /**
     * Una sola línea de compra por su id, con datos de cabecera necesarios para
     * precargar un alta de Activo Fijo (proveedor, fecha de emisión).
     */
    public function getDetalleById(int $idDetalle, int $idEmpresa): ?array
    {
        $sql = "SELECT d.*, c.id_empresa, c.id_proveedor, c.fecha_emision,
                       p.razon_social AS proveedor_nombre
                FROM compras_detalle d
                INNER JOIN compras_cabecera c ON d.id_compra = c.id
                LEFT JOIN proveedores p ON c.id_proveedor = p.id
                WHERE d.id = ? AND c.id_empresa = ? AND c.eliminado = false";
        $row = $this->query($sql, [$idDetalle, $idEmpresa])->fetch();
        return $row ?: null;
    }

    /**
     * Vincula el asiento contable generado a la compra.
     */
    public function updateAsientoContable(int $idCompra, int $idAsiento): void
    {
        $this->query(
            "UPDATE compras_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento, $idCompra]
        );
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
    // FACTURA DE REEMBOLSO RECIBIDA (bloque <reembolsos> del XML, codDocReembolso=41)
    // ─────────────────────────────────────────────────────────────────────────

    public function getReembolsoTerceros(int $idCompra): array
    {
        return $this->query(
            "SELECT * FROM compras_reembolso_terceros WHERE id_compra = ? AND eliminado = false ORDER BY id ASC",
            [$idCompra]
        )->fetchAll();
    }

    public function getImpuestosReembolsoTercero(int $idCompraTercero): array
    {
        return $this->query(
            "SELECT * FROM compras_reembolso_terceros_impuestos WHERE id_compra_tercero = ?",
            [$idCompraTercero]
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
        // tipo_ambiente NO se toma de $data: el formulario manual nunca lo envía y quedaría
        // en 1 por defecto, mientras el listado filtra contra el ambiente ACTUAL de la
        // empresa (getListado(), ~línea 43) — un desfase deja la compra invisible para
        // siempre (mismo bug ya visto en Kardex y Pedidos). Se toma en vivo de `empresas`,
        // igual que ya hace DocumentoAutomatedRegisterService para las cargas del SRI.
        $sql = "INSERT INTO compras_cabecera (
                    id_empresa, id_proveedor, id_establecimiento,
                    id_sustento_tributario, tipo_comprobante, tipo_id_proveedor,
                    parte_relacionada, establecimiento_prov, punto_emision_prov,
                    secuencial_prov, numero_autorizacion, fecha_emision, fecha_registro,
                    total_sin_impuestos, total_descuento, importe_total, propina,
                    autorizacion_desde, autorizacion_hasta, fecha_caducidad,
                    tipo_registro, deducible, documento_modificado, motivo,
                    observaciones, estado, created_by, updated_by, id_usuario, tipo_ambiente
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
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
            // El DEFAULT de la columna es 'borrador', un estado que Compras nunca
            // usó: el estado real lo decide el Service (registrado, o pendiente
            // de aprobación si la empresa exige aprobar las compras).
            $data['estado'] ?? 'registrado',
            (int)   $data['id_usuario'], // created_by
            (int)   $data['id_usuario'], // updated_by
            (int)   $data['id_usuario'], // id_usuario
            (int)   $data['id_empresa'], // → subconsulta tipo_ambiente
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

    /**
     * Actualiza una línea de detalle EN SU SITIO (mismo id), a diferencia de
     * deleteDetalles()+insertDetalle(). Necesario para no romper el vínculo con
     * inventario_kardex.referencia_id, que apunta a este id — ver sincronizarDetalles()
     * en ComprasService.
     */
    public function updateDetalle(array $data): void
    {
        $sql = "UPDATE compras_detalle SET
                    id_producto = ?, codigo_principal = ?, codigo_auxiliar = ?,
                    descripcion = ?, cantidad = ?, precio_unitario = ?, descuento = ?,
                    precio_total_sin_impuesto = ?
                WHERE id = ?";
        $this->query($sql, [
            !empty($data['id_producto']) ? (int)$data['id_producto'] : null,
            $data['codigo_principal'] ?? '',
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'] ?? '',
            (float) ($data['cantidad'] ?? 1),
            (float) ($data['precio_unitario'] ?? 0),
            (float) ($data['descuento'] ?? 0),
            (float) ($data['precio_total_sin_impuesto'] ?? 0),
            (int)   $data['id'],
        ]);
    }

    public function deleteImpuestosDeDetalle(int $idDetalle): void
    {
        $this->query("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle = ?", [$idDetalle]);
    }

    /** Elimina líneas puntuales (y sus impuestos) por id — para las que el usuario quitó al editar. */
    public function deleteDetallesPorId(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return;

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->query("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle IN ($ph)", $ids);
        $this->query("DELETE FROM compras_detalle WHERE id IN ($ph)", $ids);
    }

    /** [id_compra_detalle => cantidad ya enviada a inventario (viva)] para una compra. */
    public function getCantidadProcesadaPorDetalle(int $idCompra): array
    {
        $sql = "SELECT k.referencia_id AS id_detalle, COALESCE(SUM(k.cantidad), 0) AS cantidad
                FROM inventario_kardex k
                JOIN compras_detalle d ON d.id = k.referencia_id
                WHERE k.referencia_tipo = 'compra' AND k.eliminado = false AND d.id_compra = ?
                GROUP BY k.referencia_id";
        $rows = $this->query($sql, [$idCompra])->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id_detalle']] = (float) $r['cantidad'];
        }
        return $out;
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
    // INSERTS — FACTURA DE REEMBOLSO RECIBIDA
    // ─────────────────────────────────────────────────────────────────────────

    /** Totales agregados del bloque &lt;reembolsos&gt; (codDocReembolso=41), en la propia cabecera. */
    public function updateReembolsoTotales(int $idCompra, array $t): void
    {
        $sql = "UPDATE compras_cabecera
                   SET cod_doc_reembolso              = ?,
                       total_comprobantes_reembolso   = ?,
                       total_base_imponible_reembolso = ?,
                       total_impuesto_reembolso        = ?
                 WHERE id = ?";
        $this->query($sql, [
            (string) ($t['cod_doc_reembolso'] ?? '41'),
            (float)  ($t['total_comprobantes_reembolso'] ?? 0),
            (float)  ($t['total_base_imponible_reembolso'] ?? 0),
            (float)  ($t['total_impuesto_reembolso'] ?? 0),
            $idCompra,
        ]);
    }

    public function insertReembolsoTercero(array $data): int
    {
        $sql = "INSERT INTO compras_reembolso_terceros (
                    id_compra, tipo_identificacion_proveedor_reembolso, identificacion_proveedor_reembolso,
                    razon_social_proveedor_reembolso, cod_pais_pago_proveedor_reembolso, tipo_proveedor_reembolso,
                    cod_doc_reembolso, estab_doc_reembolso, pto_emi_doc_reembolso, secuencial_doc_reembolso,
                    fecha_emision_doc_reembolso, numero_autorizacion_doc_reemb, base_imponible_total, impuesto_total
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                RETURNING id";
        return (int) $this->query($sql, [
            (int)    $data['id_compra'],
            (string) $data['tipo_identificacion_proveedor_reembolso'],
            (string) $data['identificacion_proveedor_reembolso'],
            $data['razon_social_proveedor_reembolso'] ?? null,
            $data['cod_pais_pago_proveedor_reembolso'] ?? null,
            (string) ($data['tipo_proveedor_reembolso'] ?? '02'),
            (string) ($data['cod_doc_reembolso'] ?? '01'),
            $data['estab_doc_reembolso'] ?? null,
            $data['pto_emi_doc_reembolso'] ?? null,
            $data['secuencial_doc_reembolso'] ?? null,
            $data['fecha_emision_doc_reembolso'] ?? null,
            $data['numero_autorizacion_doc_reemb'] ?? null,
            (float)  ($data['base_imponible_total'] ?? 0),
            (float)  ($data['impuesto_total'] ?? 0),
        ])->fetchColumn();
    }

    public function insertImpuestoReembolsoTercero(array $data): void
    {
        $sql = "INSERT INTO compras_reembolso_terceros_impuestos
                    (id_compra_tercero, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
                VALUES (?,?,?,?,?,?)";
        $this->query($sql, [
            (int)   $data['id_compra_tercero'],
            (string)$data['codigo_impuesto'],
            (string)$data['codigo_porcentaje'],
            (float) ($data['tarifa'] ?? 0),
            (float) ($data['base_imponible'] ?? 0),
            (float) ($data['valor'] ?? 0),
        ]);
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

    // ─── Aprobación de compras (checkpoint 'aprobacion_compras') ───────────────

    /** Guarda el token con el que el aprobador entra desde el enlace del correo. */
    public function setTokenAprobacion(int $id, string $token): void
    {
        $this->query(
            "UPDATE compras_cabecera SET token_aprobacion = ?, updated_at = NOW() WHERE id = ?",
            [$token, $id]
        );
    }

    /**
     * Resuelve la compra a partir del token del correo. Es un flujo PÚBLICO (sin
     * sesión), así que valida aquí mismo que la empresa dueña siga activa: si no
     * lo está, el token se comporta como si no existiera (CLAUDE.md §6).
     */
    public function getPorTokenAprobacion(string $token): ?array
    {
        $rows = $this->query(
            "SELECT c.*,
                    p.razon_social   AS proveedor_nombre,
                    p.identificacion AS proveedor_ruc,
                    u.nombre         AS creado_por_nombre,
                    e.nombre         AS empresa_nombre
               FROM compras_cabecera c
               INNER JOIN empresas e     ON e.id = c.id_empresa
               INNER JOIN proveedores p  ON p.id = c.id_proveedor
               LEFT  JOIN usuarios u     ON u.id = c.created_by
              WHERE c.token_aprobacion = ?
                AND c.eliminado = false
                AND e.estado = '1'
                AND e.eliminado = false
              LIMIT 1",
            [$token]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $rows[0] ?? null;
    }

    /**
     * Cierra el flujo de aprobación. El token se limpia siempre: un enlace de
     * correo ya usado no debe volver a resolver a nada.
     */
    public function resolverAprobacion(int $id, string $estado, int $idUsuario, ?string $motivo = null): void
    {
        $this->query(
            "UPDATE compras_cabecera
                SET estado = ?, aprobado_by = ?, aprobado_at = NOW(),
                    motivo_rechazo = ?, token_aprobacion = NULL,
                    updated_by = ?, updated_at = NOW()
              WHERE id = ?",
            [$estado, $idUsuario, $motivo, $idUsuario, $id]
        );
    }

    /** Nombre y correo de los usuarios aprobadores (para notificar y mostrar). */
    public function getNombresUsuarios(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];

        $ph = implode(',', array_fill(0, count($ids), '?'));
        return $this->query(
            "SELECT id, nombre, mail FROM usuarios WHERE id IN ($ph) ORDER BY nombre ASC",
            $ids
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Cuántas compras esperan aprobación en la empresa (badge del listado). */
    public function contarPendientesAprobacion(int $idEmpresa): int
    {
        $rows = $this->query(
            "SELECT COUNT(*) AS n FROM compras_cabecera
              WHERE id_empresa = ? AND estado = 'pendiente_aprobacion' AND eliminado = false",
            [$idEmpresa]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return (int) ($rows[0]['n'] ?? 0);
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

    public function existeNumeroAutorizacion(
        int $idEmpresa,
        string $numeroAutorizacion,
        ?int $excluirId = null
    ): bool {
        $sql = "SELECT COUNT(*) FROM compras_cabecera
                WHERE id_empresa = ? AND numero_autorizacion = ?
                  AND eliminado = FALSE";
        $params = [$idEmpresa, $numeroAutorizacion];
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
