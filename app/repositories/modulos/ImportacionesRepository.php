<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ImportacionesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('importaciones_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    // ─────────────────────────────────────────────────────────────────────
    // LISTADO
    // ─────────────────────────────────────────────────────────────────────

    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_nacionalizacion',
        string $ordenDir = 'DESC',
        ?int $idUsuarioFiltro = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $where  = $this->getBaseWhere($idEmpresa, 'i', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $where .= " AND (
                p.razon_social ILIKE :buscar
                OR p.identificacion ILIKE :buscar
                OR i.referencia_dai ILIKE :buscar
                OR i.observaciones ILIKE :buscar
                OR i.numero_importacion ILIKE :buscar
            )";
            $params[':buscar'] = "%$textoLibre%";
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'dai'            => 'i.referencia_dai',
                'incoterm'       => 'i.incoterm',
                'obs'            => 'i.observaciones',
                'numero'         => 'i.numero_importacion',
            ],
            'exacto' => [
                'estado' => 'i.estado',
            ],
            'fecha' => [
                'fecha'              => 'i.fecha_nacionalizacion',
                'embarque'           => 'i.fecha_embarque',
                'llegada'            => 'i.fecha_llegada',
                'nacionalizacion'    => 'i.fecha_nacionalizacion',
            ],
            'numerico' => [
                'total'  => 'i.costo_total_nacionalizado',
                'fob'    => 'i.subtotal_fob',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM importaciones_cabecera i
                     INNER JOIN proveedores p ON p.id = i.id_proveedor
                     $where";
        $total = (int) $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = [
            'id', 'numero_importacion', 'referencia_dai', 'fecha_embarque', 'fecha_llegada',
            'fecha_nacionalizacion', 'estado', 'subtotal_fob', 'costo_total_nacionalizado',
            'proveedor_nombre',
        ];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_nacionalizacion';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $ordenExpr = $ordenCol === 'proveedor_nombre' ? 'p.razon_social' : "i.$ordenCol";

        $sql = "SELECT i.*,
                       p.razon_social   AS proveedor_nombre,
                       p.identificacion AS proveedor_identificacion,
                       b.nombre         AS bodega_nombre,
                       u.nombre         AS usuario_nombre
                FROM importaciones_cabecera i
                INNER JOIN proveedores p ON p.id = i.id_proveedor
                LEFT  JOIN bodegas   b ON b.id = i.id_bodega_destino
                LEFT  JOIN usuarios  u ON u.id = i.created_by
                $where
                ORDER BY $ordenExpr $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    // ─────────────────────────────────────────────────────────────────────
    // CABECERA
    // ─────────────────────────────────────────────────────────────────────

    public function getPorId(int $id, ?int $idEmpresa = null): ?array
    {
        $where  = "WHERE i.id = :id AND i.eliminado = false";
        $params = [':id' => $id];
        if ($idEmpresa !== null) {
            $where .= " AND i.id_empresa = :id_empresa";
            $params[':id_empresa'] = $idEmpresa;
        }

        $sql = "SELECT i.*,
                       p.razon_social      AS proveedor_nombre,
                       p.identificacion    AS proveedor_identificacion,
                       p.tipo_id_proveedor AS proveedor_tipo_id,
                       ag.razon_social     AS agente_nombre,
                       b.nombre            AS bodega_nombre
                FROM importaciones_cabecera i
                INNER JOIN proveedores p            ON p.id = i.id_proveedor
                LEFT  JOIN proveedores ag            ON ag.id = i.id_agente_afianzado
                LEFT  JOIN bodegas     b            ON b.id = i.id_bodega_destino
                $where";
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO importaciones_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, establecimiento, punto_emision, secuencial,
                    referencia_dai, id_proveedor, id_agente_afianzado,
                    id_bodega_destino, incoterm, fecha_embarque, fecha_llegada, fecha_nacionalizacion,
                    criterio_prorrateo, estado, observaciones, tipo_ambiente, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_establecimiento, :id_punto_emision, :establecimiento, :punto_emision, :secuencial,
                    :referencia_dai, :id_proveedor, :id_agente_afianzado,
                    :id_bodega_destino, :incoterm, :fecha_embarque, :fecha_llegada, :fecha_nacionalizacion,
                    :criterio_prorrateo, :estado, :observaciones,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta),
                    :id_usuario, :id_usuario
                ) RETURNING id";

        return (int) $this->query($sql, [
            ':id_empresa'            => (int) $data['id_empresa'],
            ':id_empresa_ta'         => (int) $data['id_empresa'],
            ':id_establecimiento'    => (int) $data['id_establecimiento'],
            ':id_punto_emision'      => (int) $data['id_punto_emision'],
            ':establecimiento'       => $data['establecimiento'],
            ':punto_emision'         => $data['punto_emision'],
            ':secuencial'            => $data['secuencial'],
            ':referencia_dai'        => $data['referencia_dai'] ?? null,
            ':id_proveedor'          => (int) $data['id_proveedor'],
            ':id_agente_afianzado'   => !empty($data['id_agente_afianzado']) ? (int) $data['id_agente_afianzado'] : null,
            ':id_bodega_destino'     => (int) $data['id_bodega_destino'],
            ':incoterm'              => $data['incoterm'] ?? null,
            ':fecha_embarque'        => $data['fecha_embarque'] ?? null,
            ':fecha_llegada'         => $data['fecha_llegada'] ?? null,
            ':fecha_nacionalizacion' => $data['fecha_nacionalizacion'] ?? null,
            ':criterio_prorrateo'    => $data['criterio_prorrateo'] ?? 'fob',
            ':estado'                => $data['estado'] ?? 'borrador',
            ':observaciones'         => $data['observaciones'] ?? null,
            ':id_usuario'            => (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    /**
     * Código de establecimiento (3 dígitos) y punto de emisión (3 dígitos) para
     * armar el secuencial, igual patrón que OrdenCompraService::_getDatosSerie().
     */
    public function getDatosSerie(int $idEstablecimiento, int $idPuntoEmision): ?array
    {
        $sql = "SELECT ee.codigo AS establecimiento, pe.codigo_punto AS punto_emision
                FROM empresa_establecimiento ee
                JOIN empresa_punto_emision pe ON pe.id = :id_punto AND pe.id_establecimiento = ee.id
                WHERE ee.id = :id_estab AND ee.estado = 'activo' AND pe.estado = 'activo'
                LIMIT 1";
        $row = $this->query($sql, [':id_punto' => $idPuntoEmision, ':id_estab' => $idEstablecimiento])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Datos del punto de emisión (sin filtrar por estado) más un flag `activo`
     * (punto Y establecimiento activos). Para la vista previa del secuencial:
     * a diferencia de getDatosSerie(), permite distinguir "no existe" de
     * "existe pero está inactivo" y devolver un mensaje específico al usuario.
     */
    public function getDatosSerieConEstado(int $idPuntoEmision): ?array
    {
        $sql = "SELECT ee.codigo AS establecimiento, pe.codigo_punto AS punto_emision,
                       (LOWER(ee.estado) = 'activo' AND LOWER(pe.estado) = 'activo') AS activo
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento ee ON ee.id = pe.id_establecimiento
                WHERE pe.id = :id_punto AND pe.eliminado = false AND ee.eliminado = false
                LIMIT 1";
        $row = $this->query($sql, [':id_punto' => $idPuntoEmision])->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['activo'] = in_array($row['activo'], [true, 't', 'true', 1, '1'], true);
        return $row;
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE importaciones_cabecera SET
                    referencia_dai         = :referencia_dai,
                    id_proveedor           = :id_proveedor,
                    id_agente_afianzado    = :id_agente_afianzado,
                    id_bodega_destino      = :id_bodega_destino,
                    incoterm               = :incoterm,
                    fecha_embarque         = :fecha_embarque,
                    fecha_llegada          = :fecha_llegada,
                    fecha_nacionalizacion  = :fecha_nacionalizacion,
                    criterio_prorrateo     = :criterio_prorrateo,
                    observaciones          = :observaciones,
                    updated_by             = :id_usuario,
                    updated_at             = NOW()
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $this->query($sql, [
            ':referencia_dai'        => $data['referencia_dai'] ?? null,
            ':id_proveedor'          => (int) $data['id_proveedor'],
            ':id_agente_afianzado'   => !empty($data['id_agente_afianzado']) ? (int) $data['id_agente_afianzado'] : null,
            ':id_bodega_destino'     => (int) $data['id_bodega_destino'],
            ':incoterm'              => $data['incoterm'] ?? null,
            ':fecha_embarque'        => $data['fecha_embarque'] ?? null,
            ':fecha_llegada'         => $data['fecha_llegada'] ?? null,
            ':fecha_nacionalizacion' => $data['fecha_nacionalizacion'] ?? null,
            ':criterio_prorrateo'    => $data['criterio_prorrateo'] ?? 'fob',
            ':observaciones'         => $data['observaciones'] ?? null,
            ':id_usuario'            => (int) $data['id_usuario'],
            ':id'                    => $id,
            ':id_empresa'            => (int) $data['id_empresa'],
        ]);
    }

    public function actualizarTotales(int $id, array $totales): void
    {
        $sql = "UPDATE importaciones_cabecera SET
                    subtotal_fob                 = :subtotal_fob,
                    total_gastos_capitalizables   = :total_gastos_capitalizables,
                    total_iva                     = :total_iva,
                    total_isd                     = :total_isd,
                    total_otros_gastos            = :total_otros_gastos,
                    costo_total_nacionalizado     = :costo_total_nacionalizado,
                    updated_at                    = NOW()
                WHERE id = :id";
        $this->query($sql, [
            ':subtotal_fob'               => (float) $totales['subtotal_fob'],
            ':total_gastos_capitalizables' => (float) $totales['total_gastos_capitalizables'],
            ':total_iva'                  => (float) $totales['total_iva'],
            ':total_isd'                  => (float) $totales['total_isd'],
            ':total_otros_gastos'         => (float) $totales['total_otros_gastos'],
            ':costo_total_nacionalizado'  => (float) $totales['costo_total_nacionalizado'],
            ':id'                         => $id,
        ]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario, ?string $fechaNacionalizacion = null): void
    {
        $this->query(
            "UPDATE importaciones_cabecera
             SET estado = :estado,
                 fecha_nacionalizacion = COALESCE(:fecha, fecha_nacionalizacion),
                 updated_by = :uid, updated_at = NOW()
             WHERE id = :id",
            [':estado' => $estado, ':fecha' => $fechaNacionalizacion, ':uid' => $idUsuario, ':id' => $id]
        );
    }

    /**
     * Transición de estado del flujo de aprobación (mismo patrón que
     * CargaInventarioRepository::actualizarEstado): pendiente_aprobacion →
     * nacionalizada (aprobar) o → borrador (rechazar, con motivo).
     */
    public function actualizarEstadoAprobacion(int $id, string $estado, ?int $aprobadaPor = null, ?string $motivoRechazo = null): void
    {
        $this->query(
            "UPDATE importaciones_cabecera
             SET estado = :estado,
                 aprobada_por = :apr,
                 aprobada_at = CASE WHEN :estado2 = 'nacionalizada' THEN CURRENT_TIMESTAMP ELSE aprobada_at END,
                 motivo_rechazo = :motivo,
                 updated_at = NOW()
             WHERE id = :id",
            [
                ':estado'  => $estado,
                ':estado2' => $estado,
                ':apr'     => $aprobadaPor,
                ':motivo'  => $motivoRechazo,
                ':id'      => $id,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // APROBACIÓN POR CORREO (token, sin sesión) — mismo patrón que
    // CargaInventarioRepository::setToken/getByToken/clearToken.
    // ─────────────────────────────────────────────────────────────────────

    public function setToken(int $id, string $token): void
    {
        $this->query(
            "UPDATE importaciones_cabecera SET token_aprobacion = :t WHERE id = :id",
            [':t' => $token, ':id' => $id]
        );
    }

    /** Importación por token (sin filtrar por empresa: la ruta pública no tiene sesión). */
    public function getByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') return null;
        $sql = "SELECT i.*,
                       p.razon_social      AS proveedor_nombre,
                       ag.razon_social     AS agente_nombre,
                       b.nombre            AS bodega_nombre,
                       u.nombre            AS creado_por_nombre,
                       e.nombre_comercial  AS empresa_nombre
                FROM importaciones_cabecera i
                INNER JOIN proveedores p ON p.id = i.id_proveedor
                LEFT  JOIN proveedores ag ON ag.id = i.id_agente_afianzado
                LEFT  JOIN bodegas     b ON b.id = i.id_bodega_destino
                LEFT  JOIN usuarios    u ON u.id = i.created_by
                LEFT  JOIN empresas    e ON e.id = i.id_empresa
                WHERE i.token_aprobacion = :t AND i.eliminado = false LIMIT 1";
        return $this->query($sql, [':t' => $token])->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function clearToken(int $id): void
    {
        $this->query(
            "UPDATE importaciones_cabecera SET token_aprobacion = NULL WHERE id = :id",
            [':id' => $id]
        );
    }

    /** Usuarios por ids (id, nombre, mail) — para mostrar/notificar a los aprobadores. */
    public function getNombresUsuarios(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, nombre, mail FROM usuarios WHERE id IN ($ph)");
        $st->execute($ids);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateAsientoContable(int $id, int $idAsiento): void
    {
        $this->query(
            "UPDATE importaciones_cabecera SET id_asiento_contable = :id_asiento WHERE id = :id",
            [':id_asiento' => $idAsiento, ':id' => $id]
        );
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $this->query(
            "UPDATE importaciones_cabecera
             SET eliminado = true, deleted_at = NOW(), deleted_by = :uid, updated_by = :uid, updated_at = NOW()
             WHERE id = :id",
            [':uid' => $idUsuario, ':id' => $id]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // DETALLE (líneas de producto FOB)
    // ─────────────────────────────────────────────────────────────────────

    public function getDetalles(int $idImportacion): array
    {
        $sql = "SELECT d.*, pr.nombre AS producto_nombre, pr.codigo AS producto_codigo,
                       pr.inventariable, pr.id_medida AS producto_id_medida
                FROM importaciones_detalle d
                LEFT JOIN productos pr ON pr.id = d.id_producto
                WHERE d.id_importacion = :id_importacion AND d.eliminado = false
                ORDER BY d.id ASC";
        return $this->query($sql, [':id_importacion' => $idImportacion])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO importaciones_detalle (
                    id_importacion, id_factura_exterior, id_producto, codigo_producto_raw, descripcion,
                    cantidad, id_medida, precio_unitario_fob, precio_total_fob, peso_kg, volumen_m3,
                    numero_lote, fecha_caducidad, nup, id_bodega, created_by, updated_by
                ) VALUES (
                    :id_importacion, :id_factura_exterior, :id_producto, :codigo_producto_raw, :descripcion,
                    :cantidad, :id_medida, :precio_unitario_fob, :precio_total_fob, :peso_kg, :volumen_m3,
                    :numero_lote, :fecha_caducidad, :nup, :id_bodega, :id_usuario, :id_usuario
                ) RETURNING id";

        $cantidad = (float) ($data['cantidad'] ?? 0);
        $precioUnitario = (float) ($data['precio_unitario_fob'] ?? 0);

        return (int) $this->query($sql, [
            ':id_importacion'      => (int) $data['id_importacion'],
            ':id_factura_exterior' => !empty($data['id_factura_exterior']) ? (int) $data['id_factura_exterior'] : null,
            ':id_producto'         => !empty($data['id_producto']) ? (int) $data['id_producto'] : null,
            ':codigo_producto_raw' => $data['codigo_producto_raw'] ?? null,
            ':descripcion'         => $data['descripcion'] ?? null,
            ':cantidad'            => $cantidad,
            ':id_medida'           => !empty($data['id_medida']) ? (int) $data['id_medida'] : null,
            ':precio_unitario_fob' => $precioUnitario,
            ':precio_total_fob'    => (float) ($data['precio_total_fob'] ?? ($cantidad * $precioUnitario)),
            ':peso_kg'             => (float) ($data['peso_kg'] ?? 0),
            ':volumen_m3'          => (float) ($data['volumen_m3'] ?? 0),
            ':numero_lote'         => $data['numero_lote'] ?? null,
            ':fecha_caducidad'     => $data['fecha_caducidad'] ?? null,
            ':nup'                 => $data['nup'] ?? null,
            ':id_bodega'           => !empty($data['id_bodega']) ? (int) $data['id_bodega'] : null,
            ':id_usuario'          => (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function actualizarCostoNacionalizado(int $idDetalle, float $costoUnitario, float $costoTotal): void
    {
        $this->query(
            "UPDATE importaciones_detalle
             SET costo_unitario_nacionalizado = :cu, costo_total_nacionalizado = :ct, updated_at = NOW()
             WHERE id = :id",
            [':cu' => $costoUnitario, ':ct' => $costoTotal, ':id' => $idDetalle]
        );
    }

    public function actualizarKardexDetalle(int $idDetalle, int $idKardex): void
    {
        $this->query(
            "UPDATE importaciones_detalle SET id_kardex = :id_kardex WHERE id = :id",
            [':id_kardex' => $idKardex, ':id' => $idDetalle]
        );
    }

    public function deleteDetalles(int $idImportacion): void
    {
        $this->query(
            "UPDATE importaciones_detalle SET eliminado = true, deleted_at = NOW() WHERE id_importacion = :id",
            [':id' => $idImportacion]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // FACTURAS DEL PROVEEDOR EXTERIOR
    // ─────────────────────────────────────────────────────────────────────

    public function getFacturasExterior(int $idImportacion): array
    {
        $sql = "SELECT f.*, p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_identificacion
                FROM importaciones_factura_exterior f
                INNER JOIN proveedores p ON p.id = f.id_proveedor
                WHERE f.id_importacion = :id_importacion AND f.eliminado = false
                ORDER BY f.id ASC";
        return $this->query($sql, [':id_importacion' => $idImportacion])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertFacturaExterior(array $data): int
    {
        $sql = "INSERT INTO importaciones_factura_exterior (
                    id_importacion, id_proveedor, numero_factura, fecha_factura,
                    monto_usd, forma_pago, plazo_dias, created_by, updated_by
                ) VALUES (
                    :id_importacion, :id_proveedor, :numero_factura, :fecha_factura,
                    :monto_usd, :forma_pago, :plazo_dias, :id_usuario, :id_usuario
                ) RETURNING id";
        return (int) $this->query($sql, [
            ':id_importacion' => (int) $data['id_importacion'],
            ':id_proveedor'   => (int) $data['id_proveedor'],
            ':numero_factura' => $data['numero_factura'] ?? null,
            ':fecha_factura'  => $data['fecha_factura'] ?? null,
            ':monto_usd'      => (float) ($data['monto_usd'] ?? 0),
            ':forma_pago'     => $data['forma_pago'] ?? null,
            ':plazo_dias'     => (int) ($data['plazo_dias'] ?? 0),
            ':id_usuario'     => (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function deleteFacturasExterior(int $idImportacion): void
    {
        $this->query(
            "UPDATE importaciones_factura_exterior SET eliminado = true, deleted_at = NOW() WHERE id_importacion = :id",
            [':id' => $idImportacion]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GASTOS DE NACIONALIZACIÓN
    // ─────────────────────────────────────────────────────────────────────

    public function getGastos(int $idImportacion): array
    {
        $sql = "SELECT g.*,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS compra_numero,
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial)                  AS liquidacion_numero
                FROM importaciones_gastos g
                LEFT JOIN compras_cabecera      c ON c.id = g.id_compra
                LEFT JOIN liquidaciones_cabecera l ON l.id = g.id_liquidacion_compra
                WHERE g.id_importacion = :id_importacion AND g.eliminado = false
                ORDER BY g.id ASC";
        return $this->query($sql, [':id_importacion' => $idImportacion])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertGasto(array $data): int
    {
        $sql = "INSERT INTO importaciones_gastos (
                    id_importacion, tipo_gasto, origen, id_compra, id_liquidacion_compra,
                    descripcion, monto, prorrateable, created_by, updated_by
                ) VALUES (
                    :id_importacion, :tipo_gasto, :origen, :id_compra, :id_liquidacion_compra,
                    :descripcion, :monto, :prorrateable, :id_usuario, :id_usuario
                ) RETURNING id";
        return (int) $this->query($sql, [
            ':id_importacion'        => (int) $data['id_importacion'],
            ':tipo_gasto'            => $data['tipo_gasto'],
            ':origen'                => $data['origen'] ?? 'dai_manual',
            ':id_compra'             => !empty($data['id_compra']) ? (int) $data['id_compra'] : null,
            ':id_liquidacion_compra' => !empty($data['id_liquidacion_compra']) ? (int) $data['id_liquidacion_compra'] : null,
            ':descripcion'           => $data['descripcion'] ?? null,
            ':monto'                 => (float) ($data['monto'] ?? 0),
            ':prorrateable'          => !empty($data['prorrateable']) ? 'true' : 'false',
            ':id_usuario'            => (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function deleteGastos(int $idImportacion): void
    {
        $this->query(
            "UPDATE importaciones_gastos SET eliminado = true, deleted_at = NOW() WHERE id_importacion = :id",
            [':id' => $idImportacion]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // VALIDACIONES / CATÁLOGOS
    // ─────────────────────────────────────────────────────────────────────

    public function getTipoIdProveedor(int $idProveedor, int $idEmpresa): ?string
    {
        $st = $this->query(
            "SELECT tipo_id_proveedor FROM proveedores WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $idProveedor, ':id_empresa' => $idEmpresa]
        );
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? str_pad((string) $row['tipo_id_proveedor'], 2, '0', STR_PAD_LEFT) : null;
    }

    /**
     * Resuelve un producto por código (para la carga masiva de líneas FOB desde
     * Excel/CSV, mismo patrón que CargaInventarioRepository::getProductoIdPorCodigo).
     */
    public function getProductoPorCodigo(string $codigo, int $idEmpresa): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') return null;
        $st = $this->query(
            "SELECT id, nombre, codigo, id_medida FROM productos
             WHERE id_empresa = :e AND eliminado = false AND TRIM(codigo) = :c
             ORDER BY id LIMIT 1",
            [':e' => $idEmpresa, ':c' => $codigo]
        );
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Catálogo (código, nombre) para la hoja de referencia de la plantilla Excel. */
    public function getProductosParaPlantilla(int $idEmpresa): array
    {
        $sql = "SELECT codigo, nombre FROM productos
                WHERE id_empresa = :e AND eliminado = false AND TRIM(COALESCE(codigo,'')) <> ''
                ORDER BY codigo ASC";
        return $this->query($sql, [':e' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Datos de una Compra/Liquidación de Compra ya registrada, para vincularla como gasto
     * (valida que pertenezca a la empresa y trae el monto para prellenar la línea).
     */
    public function getCompraParaVincular(int $idCompra, int $idEmpresa): ?array
    {
        $sql = "SELECT c.id, c.importe_total, p.razon_social AS proveedor_nombre,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero
                FROM compras_cabecera c
                JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id = :id AND c.id_empresa = :id_empresa AND c.eliminado = false";
        $row = $this->query($sql, [':id' => $idCompra, ':id_empresa' => $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getLiquidacionParaVincular(int $idLiquidacion, int $idEmpresa): ?array
    {
        $sql = "SELECT l.id, l.importe_total, p.razon_social AS proveedor_nombre,
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero
                FROM liquidaciones_cabecera l
                JOIN proveedores p ON p.id = l.id_proveedor
                WHERE l.id = :id AND l.id_empresa = :id_empresa AND l.eliminado = false";
        $row = $this->query($sql, [':id' => $idLiquidacion, ':id_empresa' => $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Typeahead para vincular una Compra existente como gasto de importación
     * (agente afianzado, transporte, almacenaje, etc. ya facturados localmente).
     */
    public function buscarComprasParaVincular(int $idEmpresa, string $buscar, int $limite = 15): array
    {
        $sql = "SELECT c.id, c.importe_total, c.fecha_emision, p.razon_social AS proveedor_nombre,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero
                FROM compras_cabecera c
                JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_comprobante = '01'
                  AND (
                    p.razon_social ILIKE :buscar
                    OR p.identificacion ILIKE :buscar
                    OR CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) ILIKE :buscar
                  )
                ORDER BY c.fecha_emision DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':buscar', "%$buscar%");
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Typeahead para vincular una Liquidación de Compra existente como gasto de importación.
     */
    public function buscarLiquidacionesParaVincular(int $idEmpresa, string $buscar, int $limite = 15): array
    {
        $sql = "SELECT l.id, l.importe_total, l.fecha_emision, p.razon_social AS proveedor_nombre,
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero
                FROM liquidaciones_cabecera l
                JOIN proveedores p ON p.id = l.id_proveedor
                WHERE l.id_empresa = :id_empresa AND l.eliminado = false
                  AND (
                    p.razon_social ILIKE :buscar
                    OR p.identificacion ILIKE :buscar
                    OR CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) ILIKE :buscar
                  )
                ORDER BY l.fecha_emision DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':buscar', "%$buscar%");
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Proveedores del exterior (tipo_id_proveedor = '08') para el typeahead de la cabecera.
     */
    public function buscarProveedoresExterior(int $idEmpresa, string $buscar, int $limite = 15): array
    {
        $sql = "SELECT id, razon_social AS nombre, identificacion
                FROM proveedores
                WHERE id_empresa = :id_empresa AND eliminado = false AND tipo_id_proveedor = '08'
                  AND (razon_social ILIKE :buscar OR identificacion ILIKE :buscar)
                ORDER BY razon_social ASC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':buscar', "%$buscar%");
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Proveedores locales (con RUC/cédula, tipo_id_proveedor != '08') para el
     * typeahead del agente afianzado.
     */
    public function buscarProveedoresLocales(int $idEmpresa, string $buscar, int $limite = 15): array
    {
        $sql = "SELECT id, razon_social AS nombre, identificacion
                FROM proveedores
                WHERE id_empresa = :id_empresa AND eliminado = false AND tipo_id_proveedor != '08'
                  AND (razon_social ILIKE :buscar OR identificacion ILIKE :buscar)
                ORDER BY razon_social ASC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':buscar', "%$buscar%");
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
