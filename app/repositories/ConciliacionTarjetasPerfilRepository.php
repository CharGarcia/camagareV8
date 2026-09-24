<?php

declare(strict_types=1);

namespace App\repositories;

use PDO;

/**
 * Catálogo global (sin filtro por id_empresa) de Perfiles de lectura del estado de
 * cuenta de las procesadoras de tarjeta (Payphone, Nuvei, datáfono). Se administra en
 * config/conciliacion-tarjetas-perfiles (nivel 3) y lo consume
 * modulos/conciliacion-tarjetas al cargar el estado de cuenta.
 * Ver database/migrations/20260924_conciliacion_tarjetas_perfiles_global.sql.
 */
class ConciliacionTarjetasPerfilRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('conciliacion_tarjetas_perfiles');
    }

    public function getAll(string $buscar = ''): array
    {
        $where = "WHERE p.eliminado = FALSE";
        $params = [];
        if (trim($buscar) !== '') {
            $where .= " AND (p.nombre_perfil ILIKE :b OR b.nombre_banco ILIKE :b2 OR p.tipo_procesadora ILIKE :b3)";
            $params[':b'] = $params[':b2'] = $params[':b3'] = '%' . trim($buscar) . '%';
        }

        $sql = "SELECT p.*, b.nombre_banco
                FROM conciliacion_tarjetas_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                $where
                ORDER BY p.tipo_procesadora ASC NULLS FIRST, b.nombre_banco ASC NULLS FIRST, p.nombre_perfil ASC, p.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'decodificarMapeo'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Perfiles activos que sirven para una procesadora: los de su tipo o genéricos,
     * y del banco de la forma de cobro o sin banco. Si la forma de cobro no tiene
     * banco, no se descarta ninguno por banco.
     */
    public function getActivosPara(string $tipoProcesadora, ?int $idBanco): array
    {
        $sql = "SELECT p.id, p.tipo_procesadora, p.id_banco, p.nombre_perfil, p.tipo_archivo, p.nivel,
                       b.nombre_banco
                FROM conciliacion_tarjetas_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                WHERE p.eliminado = FALSE AND p.activo = TRUE
                  AND (p.tipo_procesadora IS NULL OR p.tipo_procesadora = :tipo)
                  AND (p.id_banco IS NULL OR CAST(:banco AS INTEGER) IS NULL OR p.id_banco = CAST(:banco2 AS INTEGER))
                ORDER BY p.tipo_procesadora ASC NULLS LAST, p.id_banco ASC NULLS LAST, p.nombre_perfil ASC, p.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':tipo' => strtoupper($tipoProcesadora), ':banco' => $idBanco, ':banco2' => $idBanco]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id, bool $soloActivo = false): ?array
    {
        $sql = "SELECT p.*, b.nombre_banco
                FROM conciliacion_tarjetas_perfiles p
                LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                WHERE p.id = :id AND p.eliminado = FALSE" . ($soloActivo ? " AND p.activo = TRUE" : "");
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodificarMapeo($row) : null;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO conciliacion_tarjetas_perfiles (
                    tipo_procesadora, id_banco, nombre_perfil, tipo_archivo, nivel, fila_inicio,
                    formato_fecha, separador_decimal, mapeo_columnas, activo, created_by, updated_by
                ) VALUES (
                    :tipo_procesadora, :id_banco, :nombre_perfil, :tipo_archivo, :nivel, :fila_inicio,
                    :formato_fecha, :separador_decimal, CAST(:mapeo_columnas AS JSONB), :activo, :usuario, :usuario2
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute($this->parametros($data) + [':usuario2' => $data['usuario_id']]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE conciliacion_tarjetas_perfiles SET
                    tipo_procesadora = :tipo_procesadora, id_banco = :id_banco,
                    nombre_perfil = :nombre_perfil, tipo_archivo = :tipo_archivo, nivel = :nivel,
                    fila_inicio = :fila_inicio, formato_fecha = :formato_fecha,
                    separador_decimal = :separador_decimal, mapeo_columnas = CAST(:mapeo_columnas AS JSONB),
                    activo = :activo, updated_by = :usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute($this->parametros($data) + [':id' => $id]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica: las conciliaciones antiguas conservan la referencia y siguen mostrando el nombre. */
    public function eliminar(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE conciliacion_tarjetas_perfiles
             SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario,
                 updated_by = :usuario2, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND eliminado = FALSE"
        );
        $st->execute([':id' => $id, ':usuario' => $idUsuario, ':usuario2' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    private function parametros(array $data): array
    {
        return [
            ':tipo_procesadora' => $data['tipo_procesadora'] ?? null,
            ':id_banco' => $data['id_banco'] ?? null,
            ':nombre_perfil' => $data['nombre_perfil'],
            ':tipo_archivo' => $data['tipo_archivo'],
            ':nivel' => $data['nivel'],
            ':fila_inicio' => (int) ($data['fila_inicio'] ?? 0),
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
