<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class TransportistaRepository extends BaseRepository
{
    protected string $table = 'transportistas';

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
        $whereSql = $this->getBaseWhere($idEmpresa, 't', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (t.nombre ILIKE :b OR t.identificacion ILIKE :b OR t.placa ILIKE :b OR t.telefono ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $cols = [
            'id'             => 't.id',
            'nombre'         => 't.nombre',
            'identificacion' => 't.identificacion',
            'tipo_id'        => 't.tipo_id',
            'placa'          => 't.placa',
            'telefono'       => 't.telefono',
            'email'          => 't.email',
            'estado'         => 't.estado',
        ];
        $col = $cols[$ordenCol] ?? 't.nombre';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} t {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT t.id, t.tipo_id, t.identificacion, t.nombre, t.placa,
                           t.email, t.telefono, t.direccion, t.estado, t.created_at
                    FROM {$this->table} t
                    {$whereSql}
                    ORDER BY {$col} {$dir}
                    LIMIT {$perPage} OFFSET {$offset}";

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);

        return ['total' => $total, 'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertar(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (id_empresa, id_usuario, tipo_id, identificacion, nombre, placa,
                     email, telefono, direccion, estado, created_by, updated_by)
                VALUES
                    (:id_empresa, :id_usuario, :tipo_id, :identificacion, :nombre, :placa,
                     :email, :telefono, :direccion, :estado, :created_by, :updated_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'     => $data['id_empresa'],
            ':id_usuario'     => $data['id_usuario'],
            ':tipo_id'        => $data['tipo_id'],
            ':identificacion' => $data['identificacion'],
            ':nombre'         => $data['nombre'],
            ':placa'          => $data['placa'] ?? null,
            ':email'          => $data['email'] ?? null,
            ':telefono'       => $data['telefono'] ?? null,
            ':direccion'      => $data['direccion'] ?? null,
            ':estado'         => $data['estado'] ?? 'activo',
            ':created_by'     => $data['id_usuario'],
            ':updated_by'     => $data['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET tipo_id = :tipo_id, identificacion = :identificacion, nombre = :nombre,
                    placa = :placa, email = :email, telefono = :telefono,
                    direccion = :direccion, estado = :estado,
                    updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':tipo_id'        => $data['tipo_id'],
            ':identificacion' => $data['identificacion'],
            ':nombre'         => $data['nombre'],
            ':placa'          => $data['placa'] ?? null,
            ':email'          => $data['email'] ?? null,
            ':telefono'       => $data['telefono'] ?? null,
            ':direccion'      => $data['direccion'] ?? null,
            ':estado'         => $data['estado'] ?? 'activo',
            ':updated_by'     => $data['id_usuario'],
            ':id'             => $id,
            ':id_empresa'     => $data['id_empresa'],
        ]);
        return $st->rowCount() > 0;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :del_by,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :upd_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':del_by'    => $idUsuario,
            ':upd_by'    => $idUsuario,
            ':id'        => $id,
            ':id_empresa'=> $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    public function buscarParaSelect(int $idEmpresa, string $q): array
    {
        $sql = "SELECT id, nombre, identificacion, tipo_id, placa
                FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = FALSE AND estado = 'activo'
                  AND (nombre ILIKE :q OR identificacion ILIKE :q OR placa ILIKE :q)
                ORDER BY nombre ASC
                LIMIT 15";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':q' => "%{$q}%"]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeIdentificacion(int $idEmpresa, string $identificacion, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_empresa = :id_empresa AND identificacion = :identificacion
                  AND eliminado = FALSE"
             . ($excluirId !== null ? " AND id <> :excluir" : "");
        $params = [':id_empresa' => $idEmpresa, ':identificacion' => $identificacion];
        if ($excluirId !== null) $params[':excluir'] = $excluirId;
        return (int) $this->db->prepare($sql)->execute($params) && (int) $this->db->prepare($sql)->execute($params) > 0
            ? (bool) $this->db->query(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE id_empresa = {$idEmpresa} AND identificacion = " . $this->db->quote($identificacion) .
                 " AND eliminado = FALSE" . ($excluirId !== null ? " AND id <> {$excluirId}" : "")
              )->fetchColumn()
            : false;
    }

    public function existeIdentificacionUnico(int $idEmpresa, string $identificacion, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_empresa = :emp AND identificacion = :ident AND eliminado = FALSE"
             . ($excluirId !== null ? " AND id <> :excluir" : "");
        $p = [':emp' => $idEmpresa, ':ident' => $identificacion];
        if ($excluirId !== null) $p[':excluir'] = $excluirId;
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn() > 0;
    }
}
