<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class AlumnoCampusRepository extends BaseRepository
{
    protected string $table = 'alumnos_campus';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $whereSql = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (c.nombre ILIKE :b OR c.direccion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $cols = ['nombre' => 'c.nombre', 'estado' => 'c.estado'];
        $col  = $cols[$ordenCol] ?? 'c.nombre';
        $dir  = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} c {$whereSql}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sqlRows = "SELECT c.* FROM {$this->table} c {$whereSql} ORDER BY {$col} {$dir}, c.id DESC";
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);

        return ['total' => $total, 'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Listado corto para selects (id, nombre) — usado por el modal de Alumno.
     */
    public function getParaSelect(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id_empresa = :id_empresa AND UPPER(nombre) = UPPER(:nombre) AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa, ':nombre' => $nombre];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function contarAlumnosAsignados(int $id, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(DISTINCT ap.id_alumno) FROM alumnos_periodos ap
                WHERE ap.id_campus = :id AND ap.id_empresa = :id_empresa AND ap.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (id_empresa, nombre, direccion, estado, created_by, updated_by)
                VALUES (:id_empresa, :nombre, :direccion, :estado, :id_usuario, :id_usuario)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':nombre'     => $data['nombre'],
            ':direccion'  => $data['direccion'] !== '' ? $data['direccion'] : null,
            ':estado'     => $data['estado'],
            ':id_usuario' => $data['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    nombre = :nombre, direccion = :direccion, estado = :estado,
                    updated_by = :id_usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'     => $data['nombre'],
            ':direccion'  => $data['direccion'] !== '' ? $data['direccion'] : null,
            ':estado'     => $data['estado'],
            ':id_usuario' => $data['id_usuario'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }
}
