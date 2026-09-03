<?php
/**
 * Modelo NovedadSistema - Novedades / noticias GLOBALES del sistema.
 *
 * Tablas: novedades_sistema (sin id_empresa: son de toda la plataforma),
 *         novedades_sistema_lecturas (quién leyó qué),
 *         novedades_sistema_adjuntos (archivos descargables).
 *
 * Acceso a datos puro con PDO y consultas preparadas (CLAUDE.md §3, §6).
 * La lógica de negocio (transacciones, auditoría, limpieza del HTML) vive en
 * App\Services\NovedadSistemaService.
 */

declare(strict_types=1);

namespace App\models;

class NovedadSistema extends BaseModel
{
    public const TIPOS   = ['nuevo', 'mejora', 'aviso', 'correccion'];
    public const ESTADOS = ['borrador', 'publicada', 'archivada'];

    /** Columnas permitidas para ordenar el listado de gestión. */
    public const COLUMNAS_ORDEN = ['publicado_at', 'tipo', 'titulo', 'modulo', 'vigente_hasta', 'estado', 'leidas', 'created_at'];

    /** Condición SQL de "publicada y vigente" (alias n). */
    private const SQL_VIGENTE = "n.eliminado = FALSE AND n.estado = 'publicada'
                                 AND (n.vigente_hasta IS NULL OR n.vigente_hasta >= CURRENT_DATE)";

    // ────────────────────────────────────────────────────────────────────
    //  Lectura para los usuarios
    // ────────────────────────────────────────────────────────────────────

    /**
     * Novedades publicadas y vigentes, con la marca de si el usuario ya las leyó.
     * Orden: no leídas primero, luego por fecha de publicación descendente.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getPublicadas(int $idUsuario, int $limite = 100): array
    {
        $sql = "SELECT n.id, n.tipo, n.titulo, n.resumen, n.contenido, n.modulo, n.ruta_modulo, n.enlace,
                       n.publicado_at, n.vigente_hasta,
                       l.leido_at,
                       (l.id IS NOT NULL) AS leida
                FROM novedades_sistema n
                LEFT JOIN novedades_sistema_lecturas l
                       ON l.id_novedad = n.id AND l.id_usuario = :id_usuario
                WHERE " . self::SQL_VIGENTE . "
                ORDER BY (l.id IS NOT NULL) ASC, n.publicado_at DESC, n.id DESC
                LIMIT :limite";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_usuario', $idUsuario, \PDO::PARAM_INT);
        $st->bindValue(':limite', max(1, $limite), \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Cantidad de novedades vigentes que el usuario aún no leyó. */
    public function contarPendientes(int $idUsuario): int
    {
        $sql = "SELECT COUNT(*)
                FROM novedades_sistema n
                WHERE " . self::SQL_VIGENTE . "
                  AND NOT EXISTS (SELECT 1 FROM novedades_sistema_lecturas l
                                  WHERE l.id_novedad = n.id AND l.id_usuario = :id_usuario)";
        $st = $this->db->prepare($sql);
        $st->execute([':id_usuario' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /**
     * Ids de las novedades vigentes (publicadas) — para "marcar todas como leídas"
     * sin confiar en los ids que manda el navegador.
     *
     * @return int[]
     */
    public function getIdsVigentes(): array
    {
        $st = $this->db->prepare("SELECT n.id FROM novedades_sistema n WHERE " . self::SQL_VIGENTE);
        $st->execute();
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Marca como leídas las novedades indicadas para el usuario. Idempotente
     * (ON CONFLICT DO NOTHING). Retorna cuántas filas nuevas se insertaron.
     *
     * @param int[] $ids
     */
    public function marcarLeidas(array $ids, int $idUsuario, ?int $idEmpresa, string $ip, string $ua): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0)));
        if ($ids === []) {
            return 0;
        }
        $sql = "INSERT INTO novedades_sistema_lecturas (id_novedad, id_usuario, id_empresa, ip, user_agent)
                VALUES (:id_novedad, :id_usuario, :id_empresa, :ip, :ua)
                ON CONFLICT (id_novedad, id_usuario) DO NOTHING";
        $st = $this->db->prepare($sql);
        $nuevas = 0;
        foreach ($ids as $id) {
            $st->execute([
                ':id_novedad' => $id,
                ':id_usuario' => $idUsuario,
                ':id_empresa' => $idEmpresa,
                ':ip'         => $ip !== '' ? $ip : null,
                ':ua'         => $ua !== '' ? mb_substr($ua, 0, 500) : null,
            ]);
            $nuevas += $st->rowCount();
        }
        return $nuevas;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gestión (superadmin)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Listado completo para la pantalla de gestión, con el conteo de lecturas.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAll(string $ordenCol = 'publicado_at', string $ordenDir = 'DESC', string $buscar = '', int $limit = 0, int $offset = 0): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'publicado_at';
        $dir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $nulls = $dir === 'DESC' ? 'NULLS LAST' : 'NULLS FIRST';

        $sql = "SELECT n.id, n.tipo, n.titulo, n.resumen, n.contenido, n.modulo, n.ruta_modulo, n.enlace,
                       n.estado, n.publicado_at, n.vigente_hasta, n.created_at,
                       (SELECT COUNT(*) FROM novedades_sistema_lecturas l WHERE l.id_novedad = n.id) AS leidas
                FROM novedades_sistema n
                WHERE n.eliminado = FALSE";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (n.titulo ILIKE :b OR n.resumen ILIKE :b OR n.modulo ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $sql .= " ORDER BY {$col} {$dir} {$nulls}, n.id DESC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int) $limit . " OFFSET " . max(0, (int) $offset);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Total de novedades (no eliminadas) que cumplen la búsqueda, para paginar. */
    public function contar(string $buscar = ''): int
    {
        $sql = "SELECT COUNT(*) FROM novedades_sistema n WHERE n.eliminado = FALSE";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (n.titulo ILIKE :b OR n.resumen ILIKE :b OR n.modulo ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    /** Total de usuarios activos del sistema (denominador de "leída por X / Y"). */
    public function contarUsuariosActivos(): int
    {
        $st = $this->db->query("SELECT COUNT(*) FROM usuarios WHERE estado = 1 AND eliminado = false");
        return (int) $st->fetchColumn();
    }

    /** Una novedad por id (no eliminada). */
    public function find(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM novedades_sistema WHERE id = :id AND eliminado = FALSE LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Inserta una novedad. Retorna el id generado.
     *
     * @param array<string,mixed> $d tipo, titulo, resumen, contenido, modulo, enlace,
     *                               estado, publicado_at, vigente_hasta, created_by
     */
    public function crear(array $d): int
    {
        $sql = "INSERT INTO novedades_sistema
                    (tipo, titulo, resumen, contenido, modulo, ruta_modulo, enlace, estado,
                     publicado_at, vigente_hasta, created_by, updated_by)
                VALUES
                    (:tipo, :titulo, :resumen, :contenido, :modulo, :ruta_modulo, :enlace, :estado,
                     :publicado_at, :vigente_hasta, :created_by, :created_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':tipo'          => $d['tipo'],
            ':titulo'        => $d['titulo'],
            ':resumen'       => $d['resumen'] ?? null,
            ':contenido'     => $d['contenido'],
            ':modulo'        => $d['modulo'] ?? null,
            ':ruta_modulo'   => $d['ruta_modulo'] ?? null,
            ':enlace' => $d['enlace'] ?? null,
            ':estado'        => $d['estado'],
            ':publicado_at'  => $d['publicado_at'] ?? null,
            ':vigente_hasta' => $d['vigente_hasta'] ?? null,
            ':created_by'    => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Actualiza los datos editables de una novedad.
     *
     * @param array<string,mixed> $d tipo, titulo, resumen, contenido, modulo, enlace,
     *                               estado, publicado_at, vigente_hasta, updated_by
     */
    public function actualizar(int $id, array $d): bool
    {
        $sql = "UPDATE novedades_sistema
                SET tipo = :tipo, titulo = :titulo, resumen = :resumen, contenido = :contenido,
                    modulo = :modulo, ruta_modulo = :ruta_modulo, enlace = :enlace, estado = :estado,
                    publicado_at = :publicado_at, vigente_hasta = :vigente_hasta,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by
                WHERE id = :id AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':tipo'          => $d['tipo'],
            ':titulo'        => $d['titulo'],
            ':resumen'       => $d['resumen'] ?? null,
            ':contenido'     => $d['contenido'],
            ':modulo'        => $d['modulo'] ?? null,
            ':ruta_modulo'   => $d['ruta_modulo'] ?? null,
            ':enlace' => $d['enlace'] ?? null,
            ':estado'        => $d['estado'],
            ':publicado_at'  => $d['publicado_at'] ?? null,
            ':vigente_hasta' => $d['vigente_hasta'] ?? null,
            ':updated_by'    => $d['updated_by'] ?? null,
            ':id'            => $id,
        ]);
    }

    /** Cambia solo el estado (publicar / archivar / volver a borrador). */
    public function cambiarEstado(int $id, string $estado, ?string $publicadoAt, int $idUsuario): bool
    {
        $sql = "UPDATE novedades_sistema
                SET estado = :estado,
                    publicado_at = COALESCE(:publicado_at, publicado_at),
                    updated_at = CURRENT_TIMESTAMP, updated_by = :u
                WHERE id = :id AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':estado'       => $estado,
            ':publicado_at' => $publicadoAt,
            ':u'            => $idUsuario,
            ':id'           => $id,
        ]);
    }

    /** Eliminación lógica. */
    public function eliminar(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE novedades_sistema
             SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u,
                 updated_at = CURRENT_TIMESTAMP, updated_by = :u2
             WHERE id = :id AND eliminado = FALSE"
        );
        return $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Adjuntos
    // ────────────────────────────────────────────────────────────────────

    /**
     * Adjuntos (no eliminados) de una novedad, en orden de carga.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAdjuntos(int $idNovedad): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_novedad, nombre_original, archivo, mime_type, tamano_bytes, orden, created_at
             FROM novedades_sistema_adjuntos
             WHERE id_novedad = :id AND eliminado = FALSE
             ORDER BY orden ASC, id ASC"
        );
        $st->execute([':id' => $idNovedad]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Adjuntos de varias novedades en una sola consulta, agrupados por id_novedad.
     *
     * @param int[] $ids
     * @return array<int,array<int,array<string,mixed>>>
     */
    public function getAdjuntosPorNovedades(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $marcas = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $marcas[] = ':id' . $i;
            $params[':id' . $i] = $id;
        }
        $st = $this->db->prepare(
            "SELECT id, id_novedad, nombre_original, archivo, mime_type, tamano_bytes, orden
             FROM novedades_sistema_adjuntos
             WHERE eliminado = FALSE AND id_novedad IN (" . implode(',', $marcas) . ")
             ORDER BY id_novedad, orden ASC, id ASC"
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_novedad']][] = $r;
        }
        return $out;
    }

    /** Un adjunto por id (no eliminado), con el estado de su novedad. */
    public function findAdjunto(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT a.*, n.estado AS novedad_estado, n.eliminado AS novedad_eliminada
             FROM novedades_sistema_adjuntos a
             JOIN novedades_sistema n ON n.id = a.id_novedad
             WHERE a.id = :id AND a.eliminado = FALSE
             LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Inserta un adjunto. Retorna el id generado.
     *
     * @param array{id_novedad:int,nombre_original:string,archivo:string,mime_type:?string,tamano_bytes:int,orden:int,created_by:?int} $d
     */
    public function crearAdjunto(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO novedades_sistema_adjuntos
                 (id_novedad, nombre_original, archivo, mime_type, tamano_bytes, orden, created_by)
             VALUES (:id_novedad, :nombre_original, :archivo, :mime_type, :tamano_bytes, :orden, :created_by)
             RETURNING id"
        );
        $st->execute([
            ':id_novedad'      => $d['id_novedad'],
            ':nombre_original' => $d['nombre_original'],
            ':archivo'         => $d['archivo'],
            ':mime_type'       => $d['mime_type'] ?? null,
            ':tamano_bytes'    => $d['tamano_bytes'] ?? 0,
            ':orden'           => $d['orden'] ?? 0,
            ':created_by'      => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Siguiente valor de orden para los adjuntos de una novedad. */
    public function siguienteOrdenAdjunto(int $idNovedad): int
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM novedades_sistema_adjuntos WHERE id_novedad = :id AND eliminado = FALSE");
        $st->execute([':id' => $idNovedad]);
        return (int) $st->fetchColumn();
    }

    /** Eliminación lógica de un adjunto. */
    public function eliminarAdjunto(int $id, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE novedades_sistema_adjuntos
             SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND eliminado = FALSE"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id]);
    }

    /** Cuántos usuarios marcaron como leída una novedad. */
    public function contarLecturas(int $id): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM novedades_sistema_lecturas WHERE id_novedad = :id");
        $st->execute([':id' => $id]);
        return (int) $st->fetchColumn();
    }

    /**
     * Quién leyó una novedad (nombre de usuario y fecha), más reciente primero.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLecturasDetalle(int $id): array
    {
        $sql = "SELECT COALESCE(u.nombre, 'Usuario #' || l.id_usuario) AS usuario,
                       e.nombre AS empresa,
                       l.leido_at
                FROM novedades_sistema_lecturas l
                LEFT JOIN usuarios u ON u.id = l.id_usuario
                LEFT JOIN empresas e ON e.id = l.id_empresa
                WHERE l.id_novedad = :id
                ORDER BY l.leido_at DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
