<?php
/**
 * Modelo VideoAyuda - Catálogo GLOBAL de videos de ayuda del sistema.
 * Tabla: videos_ayuda (sin id_empresa; ayuda única para toda la aplicación).
 *
 * Acceso a datos puro con PDO y consultas preparadas (CLAUDE.md §6).
 * La lógica de negocio (subida de archivo, transacción y auditoría) vive en
 * App\Services\VideoAyudaService.
 */

declare(strict_types=1);

namespace App\models;

class VideoAyuda extends BaseModel
{
    /** Columnas permitidas para ordenar el listado de gestión. */
    public const COLUMNAS_ORDEN = ['titulo', 'categoria', 'orden', 'estado', 'tamano_bytes', 'vistas', 'created_at'];

    /**
     * Videos visibles para el visor (cualquier usuario autenticado):
     * activos y no eliminados. Búsqueda opcional por título/categoría/descripción.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVisibles(string $buscar = '', ?int $idUsuario = null): array
    {
        // "liked" = si el usuario actual ya dio like (para pintar el corazón).
        $params = [];
        if ($idUsuario !== null) {
            $likedSql = "EXISTS (SELECT 1 FROM videos_ayuda_likes l WHERE l.id_video = videos_ayuda.id AND l.id_usuario = :u_liked) AS liked";
            $params[':u_liked'] = $idUsuario;
        } else {
            $likedSql = "FALSE AS liked";
        }
        $sql = "SELECT id, titulo, descripcion, categoria, etiquetas, mime_type, tamano_bytes, orden,
                       COALESCE(likes, 0) AS likes,
                       $likedSql
                FROM videos_ayuda
                WHERE eliminado = FALSE AND estado = 'activo'";
        if ($buscar !== '') {
            $sql .= " AND (titulo ILIKE :b OR categoria ILIKE :b OR descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql .= " ORDER BY orden ASC, categoria ASC NULLS FIRST, titulo ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Listado para la pantalla de gestión (superadmin): todos los no eliminados.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(string $ordenCol = 'orden', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'orden';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id, titulo, descripcion, categoria, etiquetas, archivo, nombre_original,
                       mime_type, tamano_bytes, orden, estado, vistas, COALESCE(likes,0) AS likes, created_at
                FROM videos_ayuda
                WHERE eliminado = FALSE";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (titulo ILIKE :b OR categoria ILIKE :b OR descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql .= " ORDER BY {$col} {$dir}, id DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Un video por id (no eliminado). Devuelve todas las columnas (incluye "archivo").
     */
    public function find(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM videos_ayuda WHERE id = :id AND eliminado = FALSE LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Inserta un video. Retorna el id generado.
     *
     * @param array{titulo:string,descripcion:?string,categoria:?string,archivo:string,
     *   nombre_original:?string,mime_type:?string,tamano_bytes:int,orden:int,estado:string,
     *   created_by:?int} $data
     */
    public function crear(array $data): int
    {
        $sql = "INSERT INTO videos_ayuda
                    (titulo, descripcion, categoria, etiquetas, archivo, nombre_original, mime_type,
                     tamano_bytes, orden, estado, created_by, updated_by)
                VALUES
                    (:titulo, :descripcion, :categoria, :etiquetas, :archivo, :nombre_original, :mime_type,
                     :tamano_bytes, :orden, :estado, :created_by, :created_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':titulo'          => $data['titulo'],
            ':descripcion'     => $data['descripcion'] ?? null,
            ':categoria'       => $data['categoria'] ?? null,
            ':etiquetas'       => $data['etiquetas'] ?? null,
            ':archivo'         => $data['archivo'],
            ':nombre_original' => $data['nombre_original'] ?? null,
            ':mime_type'       => $data['mime_type'] ?? null,
            ':tamano_bytes'    => $data['tamano_bytes'] ?? 0,
            ':orden'           => $data['orden'] ?? 0,
            ':estado'          => $data['estado'] ?? 'activo',
            ':created_by'      => $data['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Actualiza los metadatos de un video. Si $archivo es null, conserva el archivo actual.
     *
     * @param array{titulo:string,descripcion:?string,categoria:?string,orden:int,
     *   estado:string,updated_by:?int,archivo?:?string,nombre_original?:?string,
     *   mime_type?:?string,tamano_bytes?:?int} $data
     */
    public function actualizar(int $id, array $data): bool
    {
        $sets = [
            'titulo = :titulo',
            'descripcion = :descripcion',
            'categoria = :categoria',
            'etiquetas = :etiquetas',
            'orden = :orden',
            'estado = :estado',
            'updated_by = :updated_by',
            'updated_at = CURRENT_TIMESTAMP',
        ];
        $params = [
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':categoria'   => $data['categoria'] ?? null,
            ':etiquetas'   => $data['etiquetas'] ?? null,
            ':orden'       => $data['orden'] ?? 0,
            ':estado'      => $data['estado'] ?? 'activo',
            ':updated_by'  => $data['updated_by'] ?? null,
            ':id'          => $id,
        ];

        // Reemplazo del archivo (opcional).
        if (!empty($data['archivo'])) {
            $sets[] = 'archivo = :archivo';
            $sets[] = 'nombre_original = :nombre_original';
            $sets[] = 'mime_type = :mime_type';
            $sets[] = 'tamano_bytes = :tamano_bytes';
            $params[':archivo']         = $data['archivo'];
            $params[':nombre_original'] = $data['nombre_original'] ?? null;
            $params[':mime_type']       = $data['mime_type'] ?? null;
            $params[':tamano_bytes']    = $data['tamano_bytes'] ?? 0;
        }

        $sql = 'UPDATE videos_ayuda SET ' . implode(', ', $sets) . ' WHERE id = :id AND eliminado = FALSE';
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    /**
     * Incrementa el contador rápido de vistas de un video (activo, no eliminado).
     * Devuelve true si se incrementó (el video existe y está activo).
     */
    public function incrementarVista(int $id): bool
    {
        $st = $this->db->prepare(
            "UPDATE videos_ayuda
             SET vistas = COALESCE(vistas, 0) + 1
             WHERE id = :id AND eliminado = FALSE"
        );
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }

    /**
     * Inserta el detalle de una reproducción (log de uso).
     */
    public function insertarVistaDetalle(int $idVideo, ?int $idUsuario, ?int $idEmpresa, string $ip, string $userAgent): void
    {
        $st = $this->db->prepare(
            "INSERT INTO videos_ayuda_vistas (id_video, id_usuario, id_empresa, ip, user_agent)
             VALUES (:v, :u, :e, :ip, :ua)"
        );
        $st->execute([
            ':v'  => $idVideo,
            ':u'  => $idUsuario,
            ':e'  => $idEmpresa,
            ':ip' => $ip,
            ':ua' => $userAgent,
        ]);
    }

    // ── Likes ────────────────────────────────────────────────────────────

    /** ¿El usuario ya dio like a este video? */
    public function usuarioDioLike(int $idVideo, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM videos_ayuda_likes WHERE id_video = :v AND id_usuario = :u LIMIT 1"
        );
        $st->execute([':v' => $idVideo, ':u' => $idUsuario]);
        return (bool) $st->fetchColumn();
    }

    public function insertarLike(int $idVideo, int $idUsuario): void
    {
        // ON CONFLICT: si por concurrencia ya existe, no falla.
        $st = $this->db->prepare(
            "INSERT INTO videos_ayuda_likes (id_video, id_usuario) VALUES (:v, :u)
             ON CONFLICT (id_video, id_usuario) DO NOTHING"
        );
        $st->execute([':v' => $idVideo, ':u' => $idUsuario]);
    }

    public function eliminarLike(int $idVideo, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "DELETE FROM videos_ayuda_likes WHERE id_video = :v AND id_usuario = :u"
        );
        $st->execute([':v' => $idVideo, ':u' => $idUsuario]);
    }

    /** Cuenta real de likes (fuente de verdad = tabla de likes). */
    public function contarLikes(int $idVideo): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM videos_ayuda_likes WHERE id_video = :v");
        $st->execute([':v' => $idVideo]);
        return (int) $st->fetchColumn();
    }

    /** Sincroniza el contador rápido en videos_ayuda. */
    public function actualizarContadorLikes(int $idVideo, int $likes): void
    {
        $st = $this->db->prepare("UPDATE videos_ayuda SET likes = :n WHERE id = :v");
        $st->execute([':n' => $likes, ':v' => $idVideo]);
    }

    // ── Detalle de vistas (quién ha visto) ──────────────────────────────

    /**
     * Quién ha visto un video, agrupado por usuario: nombre, número de
     * reproducciones y última fecha. Ordenado por la más reciente.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVistasDetalle(int $idVideo): array
    {
        $sql = "SELECT vv.id_usuario,
                       COALESCE(u.nombre, 'Usuario #' || vv.id_usuario) AS usuario,
                       COUNT(*)          AS reproducciones,
                       MAX(vv.created_at) AS ultima
                FROM videos_ayuda_vistas vv
                LEFT JOIN usuarios u ON u.id = vv.id_usuario
                WHERE vv.id_video = :v
                GROUP BY vv.id_usuario, u.nombre
                ORDER BY ultima DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':v' => $idVideo]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Eliminación lógica.
     */
    public function eliminarLogico(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE videos_ayuda
             SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND eliminado = FALSE"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id]);
    }
}
