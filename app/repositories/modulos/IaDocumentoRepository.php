<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos de documentos PDF por empresa (ia_documentos) y sus
 * fragmentos indexados para búsqueda de texto completo (ia_documento_chunks).
 */
class IaDocumentoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ia_documentos');
    }

    public function getListado(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $where = $this->getBaseWhere($idEmpresa, '', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT * FROM {$this->table} $where ORDER BY created_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, titulo, categoria, archivo, nombre_original, mime_type,
                    tamano_bytes, estado, created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :titulo, :categoria, :archivo, :nombre_original, :mime_type,
                    :tamano_bytes, 'pendiente', :id_usuario, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $data['id_empresa'],
            ':titulo'          => $data['titulo'],
            ':categoria'       => $data['categoria'],
            ':archivo'         => $data['archivo'],
            ':nombre_original' => $data['nombre_original'],
            ':mime_type'       => $data['mime_type'],
            ':tamano_bytes'    => $data['tamano_bytes'],
            ':id_usuario'      => $data['id_usuario'],
        ]);
        return (int) $this->db->lastInsertId('ia_documentos_id_seq');
    }

    public function updateEstado(int $id, string $estado, ?string $errorMensaje = null, ?int $paginas = null): bool
    {
        $sql = "UPDATE {$this->table} SET
                    estado = :estado,
                    error_mensaje = :error_mensaje,
                    paginas = COALESCE(:paginas, paginas),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':estado'        => $estado,
            ':error_mensaje' => $errorMensaje,
            ':paginas'       => $paginas,
            ':id'            => $id,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true,
                    deleted_by = :id_usuario,
                    deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);

        $stChunks = $this->db->prepare(
            "UPDATE ia_documento_chunks SET eliminado = true WHERE id_documento = :id AND id_empresa = :id_empresa"
        );
        $stChunks->execute([':id' => $id, ':id_empresa' => $idEmpresa]);

        return true;
    }

    // ── Fragmentos (chunks) ─────────────────────────────────────────────────

    public function insertarChunk(int $idEmpresa, int $idDocumento, int $chunkIndex, ?int $pagina, string $contenido): void
    {
        $sql = "INSERT INTO ia_documento_chunks (id_empresa, id_documento, chunk_index, pagina, contenido, eliminado)
                VALUES (:id_empresa, :id_documento, :chunk_index, :pagina, :contenido, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => $idEmpresa,
            ':id_documento'=> $idDocumento,
            ':chunk_index' => $chunkIndex,
            ':pagina'      => $pagina,
            ':contenido'   => $contenido,
        ]);
    }

    /**
     * Busca los fragmentos más relevantes para la pregunta dentro de los
     * documentos de la empresa, usando búsqueda de texto completo en español.
     * Filtro por id_empresa SIEMPRE presente (aislamiento multiempresa).
     *
     * @return array<int, array{id_documento:int,titulo:string,pagina:?int,chunk_index:int,contenido:string}>
     */
    public function buscarChunksRelevantes(int $idEmpresa, string $pregunta, int $limite = 8): array
    {
        $sql = "SELECT c.id_documento, d.titulo, c.pagina, c.chunk_index, c.contenido,
                       ts_rank(c.contenido_tsv, plainto_tsquery('spanish', :pregunta)) AS relevancia
                FROM ia_documento_chunks c
                INNER JOIN ia_documentos d ON d.id = c.id_documento
                WHERE c.id_empresa = :id_empresa
                  AND c.eliminado = false
                  AND d.id_empresa = :id_empresa
                  AND d.eliminado = false
                  AND c.contenido_tsv @@ plainto_tsquery('spanish', :pregunta)
                ORDER BY relevancia DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':pregunta', $pregunta, PDO::PARAM_STR);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
