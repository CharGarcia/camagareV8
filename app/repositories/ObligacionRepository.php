<?php
declare(strict_types=1);

namespace App\repositories;

use App\repositories\BaseRepository;
use PDO;

class ObligacionRepository extends BaseRepository
{
    protected string $table = 'cat_obligaciones';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Listado paginado con búsqueda para el catálogo de obligaciones.
     * Tabla global: sin filtro de id_empresa.
     */
    public function getListado(
        string $buscar,
        int    $page,
        int    $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        $whereSql = 'WHERE o.eliminado = false';
        $params   = [];

        if ($buscar !== '') {
            $whereSql .= ' AND (o.nombre ILIKE :b OR o.descripcion ILIKE :b2)';
            $params[':b']  = '%' . $buscar . '%';
            $params[':b2'] = '%' . $buscar . '%';
        }

        $cols = [
            'nombre'     => 'o.nombre',
            'status'     => 'o.status',
            'created_at' => 'o.created_at',
        ];
        $col = $cols[$ordenCol] ?? 'o.nombre';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        // Contar
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} o {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Filas
        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT o.id, o.nombre, o.descripcion, o.status,
                           o.created_at, o.updated_at,
                           uc.nombre AS creado_por_nombre,
                           ua.nombre AS actualizado_por_nombre
                    FROM {$this->table} o
                    LEFT JOIN usuarios uc ON uc.id = o.created_by
                    LEFT JOIN usuarios ua ON ua.id = o.updated_by
                    {$whereSql}
                    ORDER BY {$col} {$dir}";

        if ($perPage > 0) {
            $sqlRows .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Todas las obligaciones activas para selector en modal.
     */
    public function getAllActivas(): array
    {
        $sql = "SELECT id, nombre FROM {$this->table}
                WHERE eliminado = false AND status = 1
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si existe otra obligación con el mismo nombre.
     */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $nombreLimpio = trim($nombre);
        $sql    = "SELECT 1 FROM {$this->table} WHERE TRIM(UPPER(nombre)) = TRIM(UPPER(:nombre)) AND eliminado = false";
        $params = [':nombre' => $nombreLimpio];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Obtiene una obligación por ID.
     */
    public function findByIdGlobal(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea una nueva obligación.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (nombre, descripcion, status, eliminado, created_at, created_by)
                VALUES (:nombre, :descripcion, :status, false, CURRENT_TIMESTAMP, :created_by)";
        $st  = $this->db->prepare($sql);
        $st->execute([
            ':nombre'      => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':status'      => (int) ($data['status'] ?? 1),
            ':created_by'  => $data['created_by'],
        ]);
        return $this->lastInsertId('cat_obligaciones_id_seq');
    }

    /**
     * Actualiza una obligación.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET nombre       = :nombre,
                    descripcion  = :descripcion,
                    status       = :status,
                    updated_by   = :updated_by,
                    updated_at   = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false";
        $st  = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'      => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':status'      => (int) ($data['status'] ?? 1),
            ':updated_by'  => $data['updated_by'],
            ':id'          => $id,
        ]);
    }

    /**
     * Eliminación lógica.
     */
    public function delete(int $id, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                SET eliminado = true, deleted_by = :uid, deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st  = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':uid' => $idUsuario]);
    }

    /**
     * Cuenta tareas que usan esta obligación.
     */
    public function contarTareasAsignadas(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM tareas WHERE id_obligacion = :id AND eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        return (int) $st->fetchColumn();
    }
}
