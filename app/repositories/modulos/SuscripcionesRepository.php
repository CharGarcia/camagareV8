<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class SuscripcionesRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['nombre_cliente', 'nombre_periodicidad', 'tipo_comprobante', 'forma_cobro', 'proximo_cobro', 'fecha_inicio', 'fecha_fin', 'estado', 'created_at'];

    public function __construct()
    {
        parent::__construct('suscripciones');
    }

    // ── Listado principal ─────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'proximo_cobro';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = $this->getBaseWhere($idEmpresa, 's', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (c.nombre ILIKE :buscar OR c.identificacion ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
            ],
            'exacto'   => [ 'estado' => 's.estado' ],
            'fecha'    => [ 'proximo_cobro' => 's.proximo_cobro', 'fecha' => 's.proximo_cobro' ],
            'numerico' => [ 'monto' => 's.monto', 'total' => 's.monto' ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM {$this->table} s
                     LEFT JOIN clientes c ON c.id = s.id_cliente
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $offset      = ($page - 1) * $perPage;
            $limitOffset = $perPage > 0 ? " LIMIT $perPage OFFSET $offset" : '';

            $orderExpr = match ($ordenCol) {
                'nombre_cliente'      => 'c.nombre',
                'nombre_periodicidad' => 'per.nombre',
                default               => "s.{$ordenCol}",
            };

            $sql = "SELECT s.*,
                           c.nombre         AS nombre_cliente,
                           c.identificacion AS identificacion_cliente,
                           c.email          AS email_cliente,
                           per.nombre       AS nombre_periodicidad,
                           per.meses        AS periodicidad_meses,
                           (SELECT COUNT(*) FROM suscripciones_pagos
                            WHERE id_suscripcion = s.id AND eliminado = false) AS total_pagos,
                           (SELECT COUNT(*) FROM suscripciones_detalle
                            WHERE id_suscripcion = s.id AND eliminado = false) AS total_items
                    FROM {$this->table} s
                    LEFT JOIN clientes c   ON c.id  = s.id_cliente
                    LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                    $where
                    ORDER BY $orderExpr $ordenDir
                    $limitOffset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    // ── CRUD suscripción ──────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (id_empresa, id_cliente, id_periodicidad,
                     fecha_inicio, fecha_fin, proximo_cobro,
                     forma_cobro, estado, tipo_comprobante,
                     kushki_token, kushki_card_last4, kushki_card_brand, kushki_card_name,
                     observaciones, info_adicional, created_by, created_at, eliminado)
                VALUES
                    (:id_empresa, :id_cliente, :id_periodicidad,
                     :fecha_inicio, :fecha_fin, :proximo_cobro,
                     :forma_cobro, :estado, :tipo_comprobante,
                     :kushki_token, :kushki_card_last4, :kushki_card_brand, :kushki_card_name,
                     :observaciones, :info_adicional, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'        => $data['id_empresa'],
            ':id_cliente'        => $data['id_cliente'],
            ':id_periodicidad'   => $data['id_periodicidad'],
            ':fecha_inicio'      => $data['fecha_inicio'],
            ':fecha_fin'         => empty($data['fecha_fin']) ? null : $data['fecha_fin'],
            ':proximo_cobro'     => $data['proximo_cobro'],
            ':forma_cobro'       => $data['forma_cobro'] ?? 'credito',
            ':estado'            => $data['estado'] ?? 'activo',
            ':tipo_comprobante'  => $data['tipo_comprobante'] ?? 'factura',
            ':kushki_token'      => $data['kushki_token'] ?? null,
            ':kushki_card_last4' => $data['kushki_card_last4'] ?? null,
            ':kushki_card_brand' => $data['kushki_card_brand'] ?? null,
            ':kushki_card_name'  => $data['kushki_card_name'] ?? null,
            ':observaciones'     => $data['observaciones'] ?? null,
            ':info_adicional'    => $data['info_adicional'] ?? null,
            ':created_by'        => $data['id_usuario'],
        ]);
        return $this->lastInsertId('suscripciones_id_seq');
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_cliente      = :id_cliente,
                    id_periodicidad = :id_periodicidad,
                    fecha_inicio    = :fecha_inicio,
                    fecha_fin       = :fecha_fin,
                    proximo_cobro   = :proximo_cobro,
                    forma_cobro     = :forma_cobro,
                    estado          = :estado,
                    tipo_comprobante= :tipo_comprobante,
                    observaciones   = :observaciones,
                    info_adicional  = :info_adicional,
                    updated_by      = :updated_by,
                    updated_at      = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_cliente'      => $data['id_cliente'],
            ':id_periodicidad' => $data['id_periodicidad'],
            ':fecha_inicio'    => $data['fecha_inicio'],
            ':fecha_fin'       => empty($data['fecha_fin']) ? null : $data['fecha_fin'],
            ':proximo_cobro'   => $data['proximo_cobro'],
            ':forma_cobro'     => $data['forma_cobro'],
            ':estado'          => $data['estado'],
            ':tipo_comprobante'=> $data['tipo_comprobante'] ?? 'factura',
            ':observaciones'   => $data['observaciones'] ?? null,
            ':info_adicional'  => $data['info_adicional'] ?? null,
            ':updated_by'      => $data['id_usuario'],
            ':id'              => $id,
            ':id_empresa'      => $idEmpresa,
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true, deleted_by = :id_u,
                    deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id_u' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function updateEstado(int $id, string $estado, ?int $idUsuario = null): bool
    {
        $sql = "UPDATE {$this->table} SET estado = :estado, updated_by = :id_u, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $st  = $this->db->prepare($sql);
        return $st->execute([':estado' => $estado, ':id_u' => $idUsuario, ':id' => $id]);
    }

    public function updateKushkiToken(int $id, int $idEmpresa, string $token, string $last4, string $brand, string $cardName): bool
    {
        $sql = "UPDATE {$this->table} SET
                    kushki_token = :token, kushki_card_last4 = :last4,
                    kushki_card_brand = :brand, kushki_card_name = :card_name,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':token' => $token, ':last4' => $last4, ':brand' => $brand, ':card_name' => $cardName, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function updateProximoCobro(int $id, string $proximoCobro): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET proximo_cobro = :pc, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        return $st->execute([':pc' => $proximoCobro, ':id' => $id]);
    }

    public function incrementarIntentosFallidos(int $id): void
    {
        $this->db->prepare("UPDATE {$this->table} SET intentos_fallidos = intentos_fallidos + 1, ultimo_intento_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $id]);
    }

    public function resetIntentosFallidos(int $id): void
    {
        $this->db->prepare("UPDATE {$this->table} SET intentos_fallidos = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $id]);
    }

    // ── Detalle (productos/servicios) ─────────────────────────────────────────

    public function getDetalle(int $idSuscripcion): array
    {
        $sql = "SELECT sd.*, p.nombre AS nombre_producto, p.codigo AS codigo_producto,
                       ti.codigo AS codigo_porcentaje
                FROM suscripciones_detalle sd
                LEFT JOIN productos p ON p.id = sd.id_producto
                LEFT JOIN tarifa_iva ti ON ti.id = sd.id_tarifa_iva
                WHERE sd.id_suscripcion = :id AND sd.eliminado = false
                ORDER BY sd.orden ASC, sd.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO suscripciones_detalle
                    (id_suscripcion, id_empresa, id_producto, descripcion, cantidad,
                     precio_unitario, porcentaje_iva, id_tarifa_iva, orden, created_by, created_at, eliminado)
                VALUES
                    (:id_suscripcion, :id_empresa, :id_producto, :descripcion, :cantidad,
                     :precio_unitario, :porcentaje_iva, :id_tarifa_iva, :orden, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_suscripcion' => $data['id_suscripcion'],
            ':id_empresa'     => $data['id_empresa'],
            ':id_producto'    => $data['id_producto'],
            ':descripcion'    => $data['descripcion'] ?? null,
            ':cantidad'       => $data['cantidad'] ?? 1,
            ':precio_unitario'=> $data['precio_unitario'] ?? 0,
            ':porcentaje_iva' => $data['porcentaje_iva'] ?? 0,
            ':id_tarifa_iva'  => !empty($data['id_tarifa_iva']) ? (int) $data['id_tarifa_iva'] : null,
            ':orden'          => $data['orden'] ?? 0,
            ':created_by'     => $data['id_usuario'] ?? 0,
        ]);
        return $this->lastInsertId('suscripciones_detalle_id_seq');
    }

    public function deleteDetalle(int $idSuscripcion, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_detalle SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP WHERE id_suscripcion = :id AND eliminado = false"
        )->execute([':id_u' => $idUsuario, ':id' => $idSuscripcion]);
    }

    // ── Pagos ─────────────────────────────────────────────────────────────────

    public function insertPago(array $data): int
    {
        $sql = "INSERT INTO suscripciones_pagos
                    (id_suscripcion, id_empresa, fecha_cobro, monto, estado, id_factura,
                     kushki_transaction_id, kushki_response, intentos, created_by, created_at, eliminado)
                VALUES
                    (:id_suscripcion, :id_empresa, :fecha_cobro, :monto, :estado, :id_factura,
                     :kushki_transaction_id, :kushki_response, :intentos, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_suscripcion'        => $data['id_suscripcion'],
            ':id_empresa'            => $data['id_empresa'],
            ':fecha_cobro'           => $data['fecha_cobro'] ?? date('Y-m-d'),
            ':monto'                 => $data['monto'],
            ':estado'                => $data['estado'] ?? 'pendiente',
            ':id_factura'            => $data['id_factura'] ?? null,
            ':kushki_transaction_id' => $data['kushki_transaction_id'] ?? null,
            ':kushki_response'       => isset($data['kushki_response']) ? json_encode($data['kushki_response']) : null,
            ':intentos'              => $data['intentos'] ?? 0,
            ':created_by'            => $data['id_usuario'] ?? 0,
        ]);
        return $this->lastInsertId('suscripciones_pagos_id_seq');
    }

    public function updatePago(int $idPago, array $data): bool
    {
        $sql = "UPDATE suscripciones_pagos SET
                    estado = :estado, id_factura = :id_factura,
                    kushki_transaction_id = :kushki_transaction_id,
                    kushki_response = :kushki_response,
                    intentos = :intentos, ultimo_intento_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':estado'                => $data['estado'],
            ':id_factura'            => $data['id_factura'] ?? null,
            ':kushki_transaction_id' => $data['kushki_transaction_id'] ?? null,
            ':kushki_response'       => isset($data['kushki_response']) ? json_encode($data['kushki_response']) : null,
            ':intentos'              => $data['intentos'] ?? 0,
            ':id'                    => $idPago,
        ]);
    }

    public function getPagosPorSuscripcion(int $idSuscripcion): array
    {
        $sql = "SELECT sp.*, vc.factura_numero, vc.estado AS estado_factura
                FROM suscripciones_pagos sp
                LEFT JOIN ventas_cabecera vc ON vc.id = sp.id_factura
                WHERE sp.id_suscripcion = :id AND sp.eliminado = false
                ORDER BY sp.fecha_cobro DESC, sp.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Notificaciones ────────────────────────────────────────────────────────

    public function insertNotificacion(array $data): int
    {
        $sql = "INSERT INTO suscripciones_notificaciones
                    (id_suscripcion, id_empresa, id_pago, tipo, destinatario, asunto,
                     estado, error_detalle, enviado_at, created_by, created_at, eliminado)
                VALUES
                    (:id_suscripcion, :id_empresa, :id_pago, :tipo, :destinatario, :asunto,
                     :estado, :error_detalle, CURRENT_TIMESTAMP, :created_by, CURRENT_TIMESTAMP, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_suscripcion' => $data['id_suscripcion'],
            ':id_empresa'     => $data['id_empresa'],
            ':id_pago'        => $data['id_pago'] ?? null,
            ':tipo'           => $data['tipo'],
            ':destinatario'   => $data['destinatario'],
            ':asunto'         => $data['asunto'] ?? null,
            ':estado'         => $data['estado'] ?? 'enviado',
            ':error_detalle'  => $data['error_detalle'] ?? null,
            ':created_by'     => $data['id_usuario'] ?? 0,
        ]);
        return $this->lastInsertId('suscripciones_notificaciones_id_seq');
    }

    // ── Periodicidades ────────────────────────────────────────────────────────

    public function getPeriodicidades(): array
    {
        $st = $this->db->query("SELECT * FROM suscripcion_periodicidades WHERE estado = true ORDER BY orden ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Para cron ─────────────────────────────────────────────────────────────

    public function getVencidasParaCobro(int $lote = 50): array
    {
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       c.tipo_id        AS cliente_tipo_id,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c   ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.estado = 'activo' AND s.eliminado = false AND s.proximo_cobro <= CURRENT_DATE
                ORDER BY s.proximo_cobro ASC
                LIMIT $lote
                FOR UPDATE SKIP LOCKED";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParaGeneracionManual(int $idEmpresa, int $idPeriodicidad): array
    {
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       c.tipo_id        AS cliente_tipo_id,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c   ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :id_empresa
                  AND s.id_periodicidad = :id_per
                  AND s.estado = 'activo' 
                  AND s.eliminado = false 
                  AND s.proximo_cobro <= CURRENT_DATE
                  AND (s.fecha_inicio IS NULL OR s.fecha_inicio <= CURRENT_DATE)
                  AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
                ORDER BY s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_per' => $idPeriodicidad]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suscripciones de una empresa con al menos un período vencido por facturar.
     * No filtra por periodicidad (el cron procesa todas).
     * Incluye las que ya pasaron su fecha_fin pero tienen períodos previos pendientes
     * (proximo_cobro <= fecha_fin). El bucle de "ponerse al día" del handler corta en fecha_fin.
     */
    public function getVencidasPorEmpresa(int $idEmpresa): array
    {
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       c.tipo_id        AS cliente_tipo_id,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :id_empresa
                  AND s.estado = 'activo'
                  AND s.eliminado = false
                  AND s.proximo_cobro <= CURRENT_DATE
                  AND (s.fecha_inicio IS NULL OR s.fecha_inicio <= CURRENT_DATE)
                  AND (s.fecha_fin IS NULL OR s.proximo_cobro <= s.fecha_fin)
                ORDER BY s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suscripciones de una empresa cuyo próximo cobro vence en EXACTAMENTE N días
     * (proximo_cobro = hoy + diasAntes). Usada por el aviso de vencimiento.
     * Solo activas, vigentes y con correo de cliente registrado.
     */
    public function getProximasAVencer(int $idEmpresa, int $diasAntes): array
    {
        $dias = max(0, $diasAntes);
        $sql = "SELECT s.*,
                       c.email          AS cliente_email,
                       c.telefono       AS cliente_telefono,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_identificacion,
                       per.meses        AS periodicidad_meses,
                       per.codigo       AS periodicidad_codigo,
                       per.nombre       AS periodicidad_nombre
                FROM suscripciones s
                LEFT JOIN clientes c ON c.id = s.id_cliente
                LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                WHERE s.id_empresa = :id_empresa
                  AND s.estado = 'activo'
                  AND s.eliminado = false
                  AND s.proximo_cobro = CURRENT_DATE + CAST(:dias AS INTEGER)
                  AND (s.fecha_fin IS NULL OR s.proximo_cobro <= s.fecha_fin)
                ORDER BY s.proximo_cobro ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':dias' => $dias]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Datos del establecimiento + punto de emisión a partir de la serie elegida. */
    public function getEstablecimientoPorPunto(int $idEmpresa, int $idPuntoEmision): ?array
    {
        $sql = "SELECT ep.*, pe.id AS id_punto_emision, pe.codigo_punto AS punto_emision_codigo
                FROM empresa_establecimiento ep
                JOIN empresa_punto_emision pe ON pe.id_establecimiento = ep.id
                WHERE pe.id = :id_punto AND ep.id_empresa = :id_empresa
                  AND ep.estado = 'activo' AND pe.eliminado = false AND ep.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Series (puntos de emisión) activas de la empresa, para el selector del cron. */
    public function getSeriesActivas(int $idEmpresa): array
    {
        $sql = "SELECT pe.id AS id_punto_emision,
                       ep.codigo AS establecimiento_codigo,
                       pe.codigo_punto AS punto_emision_codigo,
                       ep.nombre AS establecimiento_nombre
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento ep ON ep.id = pe.id_establecimiento
                WHERE ep.id_empresa = :id_empresa
                  AND ep.estado = 'activo'
                  AND ep.eliminado = false
                  AND pe.eliminado = false
                ORDER BY ep.codigo ASC, pe.codigo_punto ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetalleParaCobro(int $idSuscripcion): array
    {
        $sql = "SELECT sd.*, ti.codigo AS codigo_porcentaje
                FROM suscripciones_detalle sd
                LEFT JOIN tarifa_iva ti ON ti.id = sd.id_tarifa_iva
                WHERE sd.id_suscripcion = :id AND sd.eliminado = false
                ORDER BY sd.orden ASC, sd.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
