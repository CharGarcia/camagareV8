<?php

declare(strict_types=1);

namespace App\repositories;

use PDO;

/**
 * Catálogo global (sin filtro por id_empresa) de Perfiles de Mapeo de extractos
 * bancarios para Conciliación de Cobros. Se administra en config/conciliacion-perfiles
 * (nivel 3) y lo consume modulos/conciliacion-cobros al subir un extracto.
 * Ver database/migrations/20260923_conciliacion_perfiles_global.sql.
 */
class ConciliacionPerfilRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('conciliacion_perfiles');
    }

    public function getAll(string $buscar = ''): array
    {
        $where = "WHERE p.eliminado = FALSE";
        $params = [];
        if (trim($buscar) !== '') {
            $where .= " AND (p.nombre_perfil ILIKE :b OR b.nombre_banco ILIKE :b)";
            $params[':b'] = '%' . trim($buscar) . '%';
        }

        $sql = "SELECT p.*, b.nombre_banco
                FROM conciliacion_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                $where
                ORDER BY b.nombre_banco ASC NULLS FIRST, p.nombre_perfil ASC, p.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'decodificarMapeo'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Perfiles activos que se ofrecen al subir un extracto (todas las empresas). */
    public function getActivos(): array
    {
        $sql = "SELECT p.id, p.id_banco, p.nombre_perfil, p.tipo_archivo, b.nombre_banco
                FROM conciliacion_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                WHERE p.eliminado = FALSE AND p.activo = TRUE
                ORDER BY b.nombre_banco ASC NULLS FIRST, p.nombre_perfil ASC, p.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id, bool $soloActivo = false): ?array
    {
        $sql = "SELECT p.*, b.nombre_banco
                FROM conciliacion_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                WHERE p.id = :id AND p.eliminado = FALSE" . ($soloActivo ? " AND p.activo = TRUE" : "");
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodificarMapeo($row) : null;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO conciliacion_perfiles (
                    id_banco, nombre_perfil, tipo_archivo, fila_inicio,
                    formato_fecha, separador_decimal, mapeo_columnas, activo, created_by, updated_by
                ) VALUES (
                    :id_banco, :nombre_perfil, :tipo_archivo, :fila_inicio,
                    :formato_fecha, :separador_decimal, :mapeo_columnas, :activo, :usuario, :usuario
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute($this->parametros($data));
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE conciliacion_perfiles SET
                    id_banco = :id_banco, nombre_perfil = :nombre_perfil, tipo_archivo = :tipo_archivo,
                    fila_inicio = :fila_inicio, formato_fecha = :formato_fecha,
                    separador_decimal = :separador_decimal, mapeo_columnas = :mapeo_columnas,
                    activo = :activo, updated_by = :usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute($this->parametros($data) + [':id' => $id]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica: las cargas antiguas conservan la referencia y siguen mostrando el nombre. */
    public function eliminar(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE conciliacion_perfiles
             SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario,
                 updated_by = :usuario, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND eliminado = FALSE"
        );
        $st->execute([':id' => $id, ':usuario' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    private function parametros(array $data): array
    {
        return [
            ':id_banco' => $data['id_banco'] ?? null,
            ':nombre_perfil' => $data['nombre_perfil'],
            ':tipo_archivo' => $data['tipo_archivo'],
            ':fila_inicio' => $data['fila_inicio'] ?? 0,
            ':formato_fecha' => $data['formato_fecha'] ?? 'd/m/Y',
            ':separador_decimal' => $data['separador_decimal'] ?? '.',
            ':mapeo_columnas' => json_encode($data['mapeo_columnas'], JSON_UNESCAPED_UNICODE),
            // PDO + PostgreSQL: un false de PHP llega como cadena vacía; se envía como 't'/'f'.
            ':activo' => !empty($data['activo']) ? 't' : 'f',
            ':usuario' => $data['usuario_id'],
        ];
    }

    private function decodificarMapeo(array $row): array
    {
        if (isset($row['mapeo_columnas']) && is_string($row['mapeo_columnas'])) {
            $row['mapeo_columnas'] = json_decode($row['mapeo_columnas'], true) ?: [];
        }
        return $row;
    }
}
