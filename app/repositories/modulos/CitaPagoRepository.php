<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CitaPagoRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'created_at', 'monto', 'tipo_pago', 'gateway', 'estado',
        'fecha_cita', 'nombre_cliente', 'referencia_externa',
    ];

    public function __construct()
    {
        parent::__construct('citas_pagos');
    }

    // ─── LISTADO PAGINADO ─────────────────────────────────────────────────────

    public function getListado(
        int $idEmpresa, string $buscar, int $page, int $perPage,
        string $ordenCol, string $ordenDir, array $filtros = []
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'created_at';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $joins = "
            LEFT JOIN citas          c  ON c.id  = cp.id_cita
            LEFT JOIN citas_tipos    ct ON ct.id = c.id_tipo_cita
            LEFT JOIN citas_recursos cr ON cr.id = c.id_recurso
            LEFT JOIN clientes       cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa AND cl.eliminado = false
        ";

        $where  = "WHERE cp.id_empresa = :id_empresa AND cp.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (cl.nombre ILIKE :buscar OR cp.referencia_externa ILIKE :buscar OR ct.nombre ILIKE :buscar OR c.titulo ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => [
                'cliente'    => 'cl.nombre',
                'referencia' => 'cp.referencia_externa',
                'tipo_cita'  => 'ct.nombre',
            ],
            'exacto'   => [
                'estado'     => 'cp.estado',
                'gateway'    => 'cp.gateway',
                'tipo_pago'  => 'cp.tipo_pago',
            ],
            'fecha'    => [
                'fecha'      => 'cp.created_at',
                'fecha_cita' => 'c.fecha_inicio',
            ],
            'numerico' => [
                'monto'      => 'cp.monto',
            ],
        ]);

        if (!empty($filtros['estado'])) {
            $where .= " AND cp.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['gateway'])) {
            $where .= " AND cp.gateway = :gateway";
            $params[':gateway'] = $filtros['gateway'];
        }
        if (!empty($filtros['tipo_pago'])) {
            $where .= " AND cp.tipo_pago = :tipo_pago";
            $params[':tipo_pago'] = $filtros['tipo_pago'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND cp.created_at >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND cp.created_at <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_cita'])) {
            $where .= " AND cp.id_cita = :id_cita";
            $params[':id_cita'] = (int) $filtros['id_cita'];
        }

        $colMap = [
            'created_at'         => 'cp.created_at',
            'monto'              => 'cp.monto',
            'tipo_pago'          => 'cp.tipo_pago',
            'gateway'            => 'cp.gateway',
            'estado'             => 'cp.estado',
            'fecha_cita'         => 'c.fecha_inicio',
            'nombre_cliente'     => 'cl.nombre',
            'referencia_externa' => 'cp.referencia_externa',
        ];
        $orderExpr = ($colMap[$ordenCol] ?? 'cp.created_at') . " $ordenDir";

        $countSql = "SELECT COUNT(*) FROM citas_pagos cp $joins $where";
        $stmtC    = $this->db->prepare($countSql);
        $stmtC->execute($params);
        $total = (int) $stmtC->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT cp.id, cp.id_cita, cp.monto, cp.tipo_pago, cp.gateway,
                   cp.referencia_externa, cp.estado, cp.created_at, cp.updated_at,
                   c.fecha_inicio AS fecha_cita, c.titulo AS cita_titulo,
                   ct.nombre AS nombre_tipo, ct.color AS color_tipo,
                   cr.nombre AS nombre_recurso,
                   cl.nombre AS nombre_cliente
            FROM citas_pagos cp
            $joins
            $where
            ORDER BY $orderExpr
            LIMIT :limit OFFSET :offset
        ";
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    // ─── RESUMEN / STATS ──────────────────────────────────────────────────────

    public function getResumen(int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN estado = 'completado' THEN monto ELSE 0 END) AS total_cobrado,
                SUM(CASE WHEN estado = 'pendiente'  THEN monto ELSE 0 END) AS total_pendiente,
                SUM(CASE WHEN estado = 'reembolsado' THEN monto ELSE 0 END) AS total_reembolsado,
                COUNT(*) AS total_registros,
                COUNT(CASE WHEN estado = 'completado' THEN 1 END) AS completados,
                COUNT(CASE WHEN estado = 'pendiente'  THEN 1 END) AS pendientes,
                COUNT(CASE WHEN estado = 'fallido'    THEN 1 END) AS fallidos
            FROM citas_pagos
            WHERE id_empresa = :id_empresa AND eliminado = false
        ");
        $stmt->execute([':id_empresa' => $idEmpresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function getById(int $id, int $idEmpresa): ?array
    {
        $sql = "
            SELECT cp.*,
                   c.fecha_inicio AS fecha_cita, c.titulo AS cita_titulo,
                   ct.nombre AS nombre_tipo,
                   cl.nombre AS nombre_cliente
            FROM citas_pagos cp
            LEFT JOIN citas       c  ON c.id  = cp.id_cita
            LEFT JOIN citas_tipos ct ON ct.id = c.id_tipo_cita
            LEFT JOIN clientes    cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa AND cl.eliminado = false
            WHERE cp.id = :id AND cp.id_empresa = :id_empresa AND cp.eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $d): int
    {
        $sql = "
            INSERT INTO citas_pagos
                (id_empresa, id_cita, monto, tipo_pago, gateway,
                 referencia_externa, estado, datos_gateway,
                 created_at, updated_at, created_by, updated_by, eliminado)
            VALUES
                (:id_empresa, :id_cita, :monto, :tipo_pago, :gateway,
                 :referencia_externa, :estado, :datos_gateway,
                 NOW(), NOW(), :created_by, :updated_by, false)
            RETURNING id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa'         => $d['id_empresa'],
            ':id_cita'            => $d['id_cita'],
            ':monto'              => $d['monto'],
            ':tipo_pago'          => $d['tipo_pago'],
            ':gateway'            => $d['gateway'],
            ':referencia_externa' => $d['referencia_externa'] ?: null,
            ':estado'             => $d['estado'],
            ':datos_gateway'      => isset($d['datos_gateway']) ? json_encode($d['datos_gateway']) : null,
            ':created_by'         => $d['id_usuario'],
            ':updated_by'         => $d['id_usuario'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $d): void
    {
        $sql = "
            UPDATE citas_pagos SET
                id_cita            = :id_cita,
                monto              = :monto,
                tipo_pago          = :tipo_pago,
                gateway            = :gateway,
                referencia_externa = :referencia_externa,
                estado             = :estado,
                updated_at         = NOW(),
                updated_by         = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_cita'            => $d['id_cita'],
            ':monto'              => $d['monto'],
            ':tipo_pago'          => $d['tipo_pago'],
            ':gateway'            => $d['gateway'],
            ':referencia_externa' => $d['referencia_externa'] ?: null,
            ':estado'             => $d['estado'],
            ':updated_by'         => $d['id_usuario'],
            ':id'                 => $id,
            ':id_empresa'         => $d['id_empresa'],
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "
            UPDATE citas_pagos SET
                eliminado  = true,
                deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':deleted_by' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    // ─── CATÁLOGOS ────────────────────────────────────────────────────────────

    /** Busca citas para el selector del modal (autocomplete) */
    public function buscarCitas(string $buscar, int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id,
                   TO_CHAR(c.fecha_inicio, 'DD-MM-YYYY HH24:MI') AS fecha_fmt,
                   c.titulo,
                   ct.nombre AS nombre_tipo,
                   cl.nombre AS nombre_cliente,
                   ct.precio, ct.tipo_pago AS tipo_pago_tipo, ct.anticipo_porcentaje
            FROM citas c
            LEFT JOIN citas_tipos    ct ON ct.id = c.id_tipo_cita
            LEFT JOIN clientes       cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa AND cl.eliminado = false
            WHERE c.id_empresa = :id_empresa AND c.eliminado = false
              AND (cl.nombre ILIKE :q OR ct.nombre ILIKE :q OR c.titulo ILIKE :q
                   OR CAST(c.id AS TEXT) = :id_exact)
            ORDER BY c.fecha_inicio DESC
            LIMIT 20
        ");
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':q'          => '%' . $buscar . '%',
            ':id_exact'   => $buscar,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Pagos existentes para una cita (para validar duplicados/totales) */
    public function getPagosPorCita(int $idCita, int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT id, monto, tipo_pago, estado, gateway
            FROM citas_pagos
            WHERE id_cita = :id_cita AND id_empresa = :id_empresa
              AND eliminado = false AND estado != 'fallido'
        ");
        $stmt->execute([':id_cita' => $idCita, ':id_empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
