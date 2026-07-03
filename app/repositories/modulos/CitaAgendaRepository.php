<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CitaAgendaRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'fecha_inicio', 'fecha_fin', 'estado', 'titulo',
        'nombre_tipo', 'nombre_cliente', 'nombre_recurso', 'origen'
    ];

    public function __construct()
    {
        parent::__construct('citas');
    }

    // ─── EVENTOS (FullCalendar) ───────────────────────────────────────────────

    public function getEventos(int $idEmpresa, string $inicio, string $fin, array $filtros = []): array
    {
        $sql = "
            SELECT c.id, c.titulo, c.fecha_inicio, c.fecha_fin, c.estado, c.notas, c.origen,
                   c.id_tipo_cita, c.id_recurso, c.id_cliente,
                   ct.nombre AS nombre_tipo, ct.color,
                   cr.nombre AS nombre_recurso,
                   cl.nombre AS nombre_cliente, cl.identificacion AS cliente_identificacion
            FROM citas c
            LEFT JOIN citas_tipos    ct ON ct.id = c.id_tipo_cita
            LEFT JOIN citas_recursos cr ON cr.id = c.id_recurso
            LEFT JOIN clientes       cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa AND cl.eliminado = false
            WHERE c.id_empresa = :id_empresa
              AND c.eliminado  = false
              AND c.fecha_inicio < :fin
              AND c.fecha_fin   > :inicio
        ";
        $params = [
            ':id_empresa' => $idEmpresa,
            ':inicio'     => $inicio,
            ':fin'        => $fin,
        ];

        if (!empty($filtros['estado'])) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['id_recurso'])) {
            $sql .= " AND c.id_recurso = :id_recurso";
            $params[':id_recurso'] = (int) $filtros['id_recurso'];
        }
        if (!empty($filtros['id_tipo_cita'])) {
            $sql .= " AND c.id_tipo_cita = :id_tipo_cita";
            $params[':id_tipo_cita'] = (int) $filtros['id_tipo_cita'];
        }

        $sql .= " ORDER BY c.fecha_inicio ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── LISTADO (paginado) ───────────────────────────────────────────────────

    public function getListado(
        int $idEmpresa, string $buscar, int $page, int $perPage,
        string $ordenCol, string $ordenDir, array $filtros = []
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'fecha_inicio';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = "WHERE c.id_empresa = :id_empresa AND c.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= " AND (cl.nombre ILIKE :buscar OR cl.identificacion ILIKE :buscar OR c.titulo ILIKE :buscar OR ct.nombre ILIKE :buscar)";
            $params[':buscar'] = '%' . $buscar . '%';
        }
        if (!empty($filtros['estado'])) {
            $where .= " AND c.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['id_recurso'])) {
            $where .= " AND c.id_recurso = :id_recurso";
            $params[':id_recurso'] = (int) $filtros['id_recurso'];
        }
        if (!empty($filtros['id_tipo_cita'])) {
            $where .= " AND c.id_tipo_cita = :id_tipo_cita";
            $params[':id_tipo_cita'] = (int) $filtros['id_tipo_cita'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND c.fecha_inicio >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND c.fecha_inicio <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $joins = "
            LEFT JOIN citas_tipos    ct ON ct.id = c.id_tipo_cita
            LEFT JOIN citas_recursos cr ON cr.id = c.id_recurso
            LEFT JOIN clientes       cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa AND cl.eliminado = false
        ";

        $colMap = [
            'fecha_inicio'   => 'c.fecha_inicio',
            'fecha_fin'      => 'c.fecha_fin',
            'estado'         => 'c.estado',
            'titulo'         => 'c.titulo',
            'nombre_tipo'    => 'ct.nombre',
            'nombre_cliente' => 'cl.nombre',
            'nombre_recurso' => 'cr.nombre',
            'origen'         => 'c.origen',
        ];
        $orderExpr = ($colMap[$ordenCol] ?? 'c.fecha_inicio') . " $ordenDir";

        $countSql = "SELECT COUNT(*) FROM citas c $joins $where";
        $stmtC = $this->db->prepare($countSql);
        $stmtC->execute($params);
        $total = (int) $stmtC->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT c.id, c.titulo, c.fecha_inicio, c.fecha_fin, c.estado, c.origen,
                   c.id_tipo_cita, c.id_recurso, c.id_cliente,
                   ct.nombre AS nombre_tipo, ct.color,
                   cr.nombre AS nombre_recurso,
                   cl.nombre AS nombre_cliente
            FROM citas c
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

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function getById(int $id, int $idEmpresa): ?array
    {
        $sql = "
            SELECT c.*, ct.nombre AS nombre_tipo, ct.color, ct.duracion_minutos,
                   cr.nombre AS nombre_recurso,
                   cl.nombre AS nombre_cliente, cl.identificacion AS cliente_identificacion
            FROM citas c
            LEFT JOIN citas_tipos    ct ON ct.id = c.id_tipo_cita
            LEFT JOIN citas_recursos cr ON cr.id = c.id_recurso
            LEFT JOIN clientes       cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa AND cl.eliminado = false
            WHERE c.id = :id AND c.id_empresa = :id_empresa AND c.eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $d): int
    {
        $sql = "
            INSERT INTO citas
                (id_empresa, id_tipo_cita, id_recurso, id_cliente,
                 titulo, fecha_inicio, fecha_fin, estado, notas, origen,
                 created_at, updated_at, created_by, updated_by, eliminado)
            VALUES
                (:id_empresa, :id_tipo_cita, :id_recurso, :id_cliente,
                 :titulo, :fecha_inicio, :fecha_fin, :estado, :notas, :origen,
                 NOW(), NOW(), :created_by, :updated_by, false)
            RETURNING id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa'   => $d['id_empresa'],
            ':id_tipo_cita' => $d['id_tipo_cita'] ?: null,
            ':id_recurso'   => $d['id_recurso'] ?: null,
            ':id_cliente'   => $d['id_cliente'] ?: null,
            ':titulo'       => $d['titulo'] ?: null,
            ':fecha_inicio' => $d['fecha_inicio'],
            ':fecha_fin'    => $d['fecha_fin'],
            ':estado'       => $d['estado'],
            ':notas'        => $d['notas'] ?: null,
            ':origen'       => $d['origen'] ?? 'interno',
            ':created_by'   => $d['id_usuario'],
            ':updated_by'   => $d['id_usuario'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $d): void
    {
        $sql = "
            UPDATE citas SET
                id_tipo_cita = :id_tipo_cita,
                id_recurso   = :id_recurso,
                id_cliente   = :id_cliente,
                titulo       = :titulo,
                fecha_inicio = :fecha_inicio,
                fecha_fin    = :fecha_fin,
                estado       = :estado,
                notas        = :notas,
                updated_at   = NOW(),
                updated_by   = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_tipo_cita' => $d['id_tipo_cita'] ?: null,
            ':id_recurso'   => $d['id_recurso'] ?: null,
            ':id_cliente'   => $d['id_cliente'] ?: null,
            ':titulo'       => $d['titulo'] ?: null,
            ':fecha_inicio' => $d['fecha_inicio'],
            ':fecha_fin'    => $d['fecha_fin'],
            ':estado'       => $d['estado'],
            ':notas'        => $d['notas'] ?: null,
            ':updated_by'   => $d['id_usuario'],
            ':id'           => $id,
            ':id_empresa'   => $d['id_empresa'],
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "
            UPDATE citas SET
                eliminado  = true,
                deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':deleted_by' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    public function cambiarEstado(int $id, string $estado, int $idEmpresa, int $idUsuario): void
    {
        $sql = "
            UPDATE citas SET estado = :estado, updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':estado' => $estado, ':updated_by' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    // ─── CATÁLOGOS ────────────────────────────────────────────────────────────

    public function getCatalogos(int $idEmpresa): array
    {
        $stTipos = $this->db->prepare("
            SELECT id, nombre, color, duracion_minutos, precio, tipo_pago
            FROM citas_tipos
            WHERE id_empresa = :ie AND status = 1 AND eliminado = false
            ORDER BY nombre
        ");
        $stTipos->execute([':ie' => $idEmpresa]);
        $tiposData = $stTipos->fetchAll(PDO::FETCH_ASSOC);

        // Adjuntar recursos_ids a cada tipo
        if (!empty($tiposData)) {
            $ids = implode(',', array_map('intval', array_column($tiposData, 'id')));
            $tr  = $this->db->query(
                "SELECT id_tipo, id_recurso FROM citas_tipos_recursos WHERE id_tipo IN ($ids) ORDER BY id_tipo"
            )->fetchAll(PDO::FETCH_ASSOC);
            $mapa = [];
            foreach ($tr as $r) {
                $mapa[(int)$r['id_tipo']][] = (int)$r['id_recurso'];
            }
            foreach ($tiposData as &$t) {
                $t['recursos_ids'] = $mapa[(int)$t['id']] ?? [];
            }
            unset($t);
        }

        $stRecursos = $this->db->prepare("
            SELECT id, nombre, tipo
            FROM citas_recursos
            WHERE id_empresa = :ie AND status = 1 AND eliminado = false
            ORDER BY nombre
        ");
        $stRecursos->execute([':ie' => $idEmpresa]);

        return [
            'tipos'    => $tiposData,
            'recursos' => $stRecursos->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function buscarClientes(string $buscar, int $idEmpresa): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, identificacion, email, telefono
            FROM clientes
            WHERE id_empresa = :ie AND eliminado = false AND status = 1
              AND (nombre ILIKE :q OR identificacion ILIKE :q OR email ILIKE :q)
            ORDER BY nombre
            LIMIT 20
        ");
        $stmt->execute([':ie' => $idEmpresa, ':q' => '%' . $buscar . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
