<?php
/**
 * Modelo IaAgente - Catálogo GLOBAL de plantillas/prompts de agentes de IA
 * (módulo IA Soporte). Tabla: ia_agentes (sin id_empresa; único para toda la app).
 * Acceso a datos puro con PDO y consultas preparadas (CLAUDE.md §6).
 */

declare(strict_types=1);

namespace App\models;

class IaAgente extends BaseModel
{
    public const COLUMNAS_ORDEN = ['nombre', 'orden', 'activo'];

    public function getAll(string $ordenCol = 'orden', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'orden';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM ia_agentes WHERE eliminado = FALSE";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (nombre ILIKE :b OR descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql .= " ORDER BY {$col} {$dir}, id ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM ia_agentes WHERE id = :id AND eliminado = FALSE');
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO ia_agentes (nombre, descripcion, icono, prompt_sistema, orden, activo, created_by, updated_by)
                VALUES (:nombre, :descripcion, :icono, :prompt_sistema, :orden, :activo, :created_by, :created_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':nombre'         => $data['nombre'],
            ':descripcion'    => $data['descripcion'] ?? null,
            ':icono'          => $data['icono'] ?: 'bi-robot',
            ':prompt_sistema' => $data['prompt_sistema'],
            ':orden'          => $data['orden'] ?? 0,
            ':activo'         => $data['activo'] ?? true,
            ':created_by'     => $data['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE ia_agentes SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    icono = :icono,
                    prompt_sistema = :prompt_sistema,
                    orden = :orden,
                    activo = :activo,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'         => $data['nombre'],
            ':descripcion'    => $data['descripcion'] ?? null,
            ':icono'          => $data['icono'] ?: 'bi-robot',
            ':prompt_sistema' => $data['prompt_sistema'],
            ':orden'          => $data['orden'] ?? 0,
            ':activo'         => $data['activo'] ?? true,
            ':updated_by'     => $data['updated_by'] ?? null,
            ':id'             => $id,
        ]);
    }

    public function eliminarLogico(int $id, ?int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE ia_agentes SET eliminado = TRUE, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
             WHERE id = :id AND eliminado = FALSE"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id]);
    }
}
