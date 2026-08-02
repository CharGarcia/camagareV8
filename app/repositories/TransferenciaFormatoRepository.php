<?php
declare(strict_types=1);

namespace App\repositories;

use PDO;

/**
 * Catálogo global (sin id_empresa) de Formatos de Transferencia Bancaria.
 * Cada fila define el tipo de archivo y las columnas (JSONB `campos`) que
 * TransferenciaFormatoConfigurable usa para generar el archivo de un lote,
 * o bien una `clase_formatter` (escape hatch) para un formato que el motor
 * genérico no pueda expresar. Ver database/migrations/20260801_transferencia_formatos.sql.
 */
class TransferenciaFormatoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('transferencia_formatos');
    }

    public function getAll(string $buscar = ''): array
    {
        $where = "WHERE tf.eliminado = false";
        $params = [];
        if (trim($buscar) !== '') {
            $where .= " AND (tf.nombre ILIKE :b OR b.nombre_banco ILIKE :b)";
            $params[':b'] = '%' . trim($buscar) . '%';
        }

        $sql = "SELECT tf.*, b.nombre_banco
                FROM transferencia_formatos tf
                LEFT JOIN bancos_ecuador b ON b.id = tf.id_banco
                $where
                ORDER BY tf.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'decodificarCampos'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT tf.*, b.nombre_banco
             FROM transferencia_formatos tf
             LEFT JOIN bancos_ecuador b ON b.id = tf.id_banco
             WHERE tf.id = :id AND tf.eliminado = false"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodificarCampos($row) : null;
    }

    /** Formatos activos disponibles para elegir al armar un lote: los de un banco puntual + los genéricos (id_banco NULL). */
    public function getActivosParaBanco(?int $idBanco = null): array
    {
        $where = "WHERE tf.eliminado = false AND tf.estado = 'activo'";
        $params = [];
        if ($idBanco !== null) {
            $where .= " AND (tf.id_banco = :banco OR tf.id_banco IS NULL)";
            $params[':banco'] = $idBanco;
        }

        $sql = "SELECT tf.id, tf.nombre, tf.id_banco, b.nombre_banco
                FROM transferencia_formatos tf
                LEFT JOIN bancos_ecuador b ON b.id = tf.id_banco
                $where
                ORDER BY tf.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO transferencia_formatos
                    (id_banco, nombre, descripcion, tipo_archivo, delimitador, incluye_encabezado,
                     nombre_hoja, campos, clase_formatter, estado, created_by, created_at)
                VALUES
                    (:id_banco, :nombre, :descripcion, :tipo_archivo, :delimitador, :encabezado,
                     :nombre_hoja, :campos, :clase, :estado, :cb, CURRENT_TIMESTAMP)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute($this->paramsEscritura($d));
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d): bool
    {
        $sql = "UPDATE transferencia_formatos SET
                    id_banco = :id_banco,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    tipo_archivo = :tipo_archivo,
                    delimitador = :delimitador,
                    incluye_encabezado = :encabezado,
                    nombre_hoja = :nombre_hoja,
                    campos = :campos,
                    clase_formatter = :clase,
                    estado = :estado,
                    updated_by = :ub,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false";
        $params = $this->paramsEscritura($d);
        unset($params[':cb']);
        $params[':ub'] = $d['updated_by'] ?? null;
        $params[':id'] = $id;
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    private function paramsEscritura(array $d): array
    {
        return [
            ':id_banco'    => $d['id_banco'] ?: null,
            ':nombre'      => $d['nombre'],
            ':descripcion' => $d['descripcion'] ?: null,
            ':tipo_archivo'=> $d['tipo_archivo'],
            ':delimitador' => $d['delimitador'] ?: null,
            ':encabezado'  => $d['incluye_encabezado'] ? 'true' : 'false',
            ':nombre_hoja' => $d['nombre_hoja'] ?: null,
            ':campos'      => json_encode($d['campos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':clase'       => $d['clase_formatter'] ?: null,
            ':estado'      => $d['estado'] ?? 'activo',
            ':cb'          => $d['created_by'] ?? null,
        ];
    }

    public function cambiarEstado(int $id, string $estado, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE transferencia_formatos SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND eliminado = false"
        );
        return $st->execute([':estado' => $estado, ':u' => $idUsuario, ':id' => $id]);
    }

    public function eliminar(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE transferencia_formatos SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND eliminado = false"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id]);
    }

    /** ¿Algún lote (no eliminado) ya usa este formato? Bloquea el borrado si es así. */
    public function tieneLotesAsociados(int $id): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM transferencias_lotes WHERE id_formato_transferencia = :id AND eliminado = false LIMIT 1"
        );
        $st->execute([':id' => $id]);
        return (bool) $st->fetchColumn();
    }

    private function decodificarCampos(array $row): array
    {
        $row['campos'] = $row['campos'] ? json_decode($row['campos'], true) : [];
        return $row;
    }
}
