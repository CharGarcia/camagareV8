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

    /**
     * Listado de documentos, incluyendo los agentes a los que está restringido
     * (columna "agentes": array de {id,nombre}; vacío = disponible para todos).
     */
    public function getListado(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $where = $this->getBaseWhere($idEmpresa, '', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT *,
                       (SELECT COALESCE(json_agg(json_build_object('id', a.id, 'nombre', a.nombre) ORDER BY a.orden), '[]')
                        FROM ia_documento_agentes da
                        INNER JOIN ia_agentes a ON a.id = da.id_agente
                        WHERE da.id_documento = {$this->table}.id) AS agentes
                FROM {$this->table}
                $where
                ORDER BY created_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['agentes'] = $r['agentes'] ? json_decode((string) $r['agentes'], true) : [];
        }
        unset($r);
        return $rows;
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

    // ── Relación documento ↔ agentes ────────────────────────────────────────

    /**
     * Reemplaza el conjunto de agentes a los que está restringido el documento.
     * Un arreglo vacío significa "disponible para todos los agentes".
     */
    public function sincronizarAgentes(int $idDocumento, array $idsAgentes): void
    {
        $del = $this->db->prepare('DELETE FROM ia_documento_agentes WHERE id_documento = :id');
        $del->execute([':id' => $idDocumento]);

        if (empty($idsAgentes)) {
            return;
        }
        $ins = $this->db->prepare('INSERT INTO ia_documento_agentes (id_documento, id_agente) VALUES (:id_documento, :id_agente)');
        foreach (array_unique(array_map('intval', $idsAgentes)) as $idAgente) {
            if ($idAgente > 0) {
                $ins->execute([':id_documento' => $idDocumento, ':id_agente' => $idAgente]);
            }
        }
    }

    public function getAgentesDocumento(int $idDocumento): array
    {
        $st = $this->db->prepare('SELECT id_agente FROM ia_documento_agentes WHERE id_documento = :id');
        $st->execute([':id' => $idDocumento]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    // ── Fragmentos (chunks) ─────────────────────────────────────────────────

    /**
     * Obtiene el texto de un fragmento puntual (para que el usuario vea qué
     * dice exactamente la fuente citada en una respuesta del chat).
     * Filtro por id_empresa SIEMPRE presente (aislamiento multiempresa).
     */
    public function getChunkContenido(int $idEmpresa, int $idDocumento, int $chunkIndex): ?string
    {
        $sql = "SELECT c.contenido
                FROM ia_documento_chunks c
                INNER JOIN ia_documentos d ON d.id = c.id_documento
                WHERE c.id_documento = :id_documento
                  AND c.chunk_index = :chunk_index
                  AND c.id_empresa = :id_empresa
                  AND c.eliminado = false
                  AND d.id_empresa = :id_empresa
                  AND d.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_documento' => $idDocumento,
            ':chunk_index'  => $chunkIndex,
            ':id_empresa'   => $idEmpresa,
        ]);
        $contenido = $st->fetchColumn();
        return $contenido !== false ? (string) $contenido : null;
    }

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
     * La consulta se arma en modo OR entre las palabras significativas de la
     * pregunta (no AND estricto de plainto_tsquery): una pregunta natural como
     * "qué datos son obligatorios para una factura de venta" no debe exigir que
     * las 4 palabras aparezcan juntas en el mismo fragmento — basta con que el
     * fragmento contenga varias de ellas, y ts_rank ya prioriza los que más
     * coincidencias tienen. Con AND estricto, muchos fragmentos relevantes
     * quedaban fuera solo porque el documento no repetía las mismas palabras
     * exactas de la pregunta en un mismo párrafo.
     *
     * Solo considera documentos disponibles para $idAgente: los que NO tienen
     * ninguna restricción (disponibles para todos) o los restringidos que
     * incluyen explícitamente a este agente (tabla ia_documento_agentes).
     *
     * @return array<int, array{id_documento:int,titulo:string,pagina:?int,chunk_index:int,contenido:string}>
     */
    public function buscarChunksRelevantes(int $idEmpresa, string $pregunta, int $idAgente, int $limite = 8): array
    {
        $sql = "WITH consulta AS (
                    SELECT NULLIF(replace(plainto_tsquery('spanish', :pregunta)::text, ' & ', ' | '), '')::tsquery AS q
                )
                SELECT c.id_documento, d.titulo, c.pagina, c.chunk_index, c.contenido,
                       ts_rank(c.contenido_tsv, consulta.q) AS relevancia
                FROM ia_documento_chunks c
                INNER JOIN ia_documentos d ON d.id = c.id_documento
                CROSS JOIN consulta
                WHERE c.id_empresa = :id_empresa
                  AND c.eliminado = false
                  AND d.id_empresa = :id_empresa
                  AND d.eliminado = false
                  AND consulta.q IS NOT NULL
                  AND c.contenido_tsv @@ consulta.q
                  AND (
                        NOT EXISTS (SELECT 1 FROM ia_documento_agentes da WHERE da.id_documento = d.id)
                        OR EXISTS (SELECT 1 FROM ia_documento_agentes da WHERE da.id_documento = d.id AND da.id_agente = :id_agente)
                      )
                ORDER BY relevancia DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':pregunta', $pregunta, PDO::PARAM_STR);
        $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':id_agente', $idAgente, PDO::PARAM_INT);
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
