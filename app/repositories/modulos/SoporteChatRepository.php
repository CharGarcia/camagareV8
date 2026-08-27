<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos del chat de soporte.
 *
 * Dos lados con reglas distintas de visibilidad:
 *   - Lado USUARIO: solo sus propias conversaciones (created_by), dentro de su
 *     empresa. Filtro completo, como cualquier módulo operativo (§4).
 *   - Lado AGENTE (bandeja): SIN filtro de id_empresa — el equipo de soporte
 *     atiende a todas las empresas. Es la excepción documentada a §4; quién
 *     puede llegar aquí lo decide el Service/Controller, no esta clase.
 */
class SoporteChatRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('soporte_conversaciones');
    }

    // ── Conversaciones ───────────────────────────────────────────────────────

    public function crearConversacion(array $data): int
    {
        $sql = "INSERT INTO soporte_conversaciones (
                    id_empresa, id_empresa_destino, canal, asunto, estado,
                    origen_url, origen_modulo,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :id_empresa, :id_empresa_destino, :canal, :asunto, 'espera',
                    :origen_url, :origen_modulo,
                    :created_by, :updated_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'         => $data['id_empresa'],
            ':id_empresa_destino' => $data['id_empresa_destino'] ?? null,
            ':canal'              => $data['canal'] ?? 'interno',
            ':asunto'             => $data['asunto'] ?? null,
            ':origen_url'         => $data['origen_url'] ?? null,
            ':origen_modulo'      => $data['origen_modulo'] ?? null,
            ':created_by'         => $data['id_usuario'],
            ':updated_by'         => $data['id_usuario'],
        ]);
        return (int) $this->db->lastInsertId('soporte_conversaciones_id_seq');
    }

    /**
     * Conversación por id SIN filtrar por empresa (la bandeja del agente la
     * necesita así). El control de acceso lo aplica el Service.
     */
    public function findConversacion(int $id): ?array
    {
        $sql = "SELECT c.*,
                       e.nombre  AS empresa_nombre,
                       u.nombre  AS usuario_nombre,
                       a.nombre  AS agente_nombre
                  FROM soporte_conversaciones c
                  LEFT JOIN empresas e ON e.id = c.id_empresa
                  LEFT JOIN usuarios u ON u.id = c.created_by
                  LEFT JOIN usuarios a ON a.id = c.id_agente_asignado
                 WHERE c.id = :id AND c.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Conversaciones del propio usuario (las que ve en su burbuja).
     * Filtro completo por empresa + propias, según §4/§6.
     */
    public function getListadoUsuario(int $idUsuario, int $idEmpresa, bool $incluirArchivadas = false): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'c', $idUsuario);
        if (!$incluirArchivadas) {
            $where .= " AND c.archivada = false";
        }

        $sql = "SELECT c.id, c.asunto, c.estado, c.sin_leer_usuario,
                       c.ultimo_mensaje, c.ultimo_mensaje_at, c.archivada,
                       c.calificacion, c.origen_modulo,
                       a.nombre AS agente_nombre
                  FROM soporte_conversaciones c
                  LEFT JOIN usuarios a ON a.id = c.id_agente_asignado
                {$where}
                 ORDER BY c.ultimo_mensaje_at DESC NULLS LAST, c.id DESC
                 LIMIT 50";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario_filtro' => $idUsuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bandeja del agente: TODAS las empresas (excepción a §4).
     *
     * @param array{estado?:string,buscar?:string,archivadas?:bool,solo_mias?:int} $filtros
     */
    public function getListadoBandeja(array $filtros = []): array
    {
        $where  = ['c.eliminado = false'];
        $params = [];

        if (empty($filtros['archivadas'])) {
            $where[] = 'c.archivada = false';
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '' && $estado !== 'todas') {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = $estado;
        }

        if (!empty($filtros['solo_mias'])) {
            $where[] = 'c.id_agente_asignado = :id_agente';
            $params[':id_agente'] = (int) $filtros['solo_mias'];
        }

        // Texto libre sobre asunto, empresa y nombre del usuario. Nunca se
        // concatena la entrada: siempre parámetro preparado (§9).
        // Un placeholder distinto por ocurrencia: con prepares nativos de
        // PostgreSQL (sin ATTR_EMULATE_PREPARES) PDO no admite reutilizar el
        // mismo nombre en varias posiciones.
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $where[] = '(c.asunto ILIKE :buscar_a OR e.nombre ILIKE :buscar_b OR u.nombre ILIKE :buscar_c)';
            $like = '%' . $buscar . '%';
            $params[':buscar_a'] = $like;
            $params[':buscar_b'] = $like;
            $params[':buscar_c'] = $like;
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT c.id, c.id_empresa, c.asunto, c.estado, c.sin_leer_agente,
                       c.ultimo_mensaje, c.ultimo_mensaje_at, c.archivada,
                       c.origen_modulo, c.id_agente_asignado, c.created_at,
                       e.nombre AS empresa_nombre,
                       u.nombre AS usuario_nombre,
                       a.nombre AS agente_nombre
                  FROM soporte_conversaciones c
                  LEFT JOIN empresas e ON e.id = c.id_empresa
                  LEFT JOIN usuarios u ON u.id = c.created_by
                  LEFT JOIN usuarios a ON a.id = c.id_agente_asignado
                {$sqlWhere}
                 ORDER BY (c.estado = 'espera') DESC,
                          c.ultimo_mensaje_at DESC NULLS LAST,
                          c.id DESC
                 LIMIT 100";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asignarAgente(int $id, int $idAgente): void
    {
        $sql = "UPDATE soporte_conversaciones
                   SET id_agente_asignado = :id_agente,
                       estado = CASE WHEN estado = 'espera' THEN 'atendiendo' ELSE estado END,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :updated_by
                 WHERE id = :id AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':id'         => $id,
            ':id_agente'  => $idAgente,
            ':updated_by' => $idAgente,
        ]);
    }

    public function cambiarEstado(int $id, string $estado, int $idUsuario): void
    {
        // :estado va dos veces con nombres distintos: PDO con prepares nativos
        // no permite repetir el mismo placeholder.
        $sql = "UPDATE soporte_conversaciones
                   SET estado     = :estado,
                       cerrada_at = CASE WHEN :estado_cierre IN ('resuelta','cerrada')
                                         THEN COALESCE(cerrada_at, CURRENT_TIMESTAMP)
                                         ELSE NULL END,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :id_usuario
                 WHERE id = :id AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':id'            => $id,
            ':estado'        => $estado,
            ':estado_cierre' => $estado,
            ':id_usuario'    => $idUsuario,
        ]);
    }

    public function calificar(int $id, int $calificacion, ?string $comentario, int $idUsuario): void
    {
        $sql = "UPDATE soporte_conversaciones
                   SET calificacion = :calificacion,
                       calificacion_comentario = :comentario,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :id_usuario
                 WHERE id = :id AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':id'           => $id,
            ':calificacion' => $calificacion,
            ':comentario'   => $comentario,
            ':id_usuario'   => $idUsuario,
        ]);
    }

    public function eliminarConversacion(int $id, int $idUsuario): void
    {
        $sql = "UPDATE soporte_conversaciones
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :id_usuario
                 WHERE id = :id";
        $this->db->prepare($sql)->execute([':id' => $id, ':id_usuario' => $idUsuario]);
    }

    // ── Mensajes ─────────────────────────────────────────────────────────────

    public function crearMensaje(array $data): int
    {
        $sql = "INSERT INTO soporte_mensajes (
                    id_empresa, id_conversacion, rol, contenido,
                    adjunto, adjunto_nombre, adjunto_mime, adjunto_bytes,
                    sugerida_por_ia, fuentes, created_by, updated_by,
                    created_at, updated_at
                ) VALUES (
                    :id_empresa, :id_conversacion, :rol, :contenido,
                    :adjunto, :adjunto_nombre, :adjunto_mime, :adjunto_bytes,
                    :sugerida_por_ia, :fuentes, :created_by, :updated_by,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $data['id_empresa'],
            ':id_conversacion' => $data['id_conversacion'],
            ':rol'             => $data['rol'],
            ':contenido'       => $data['contenido'],
            ':adjunto'         => $data['adjunto']        ?? null,
            ':adjunto_nombre'  => $data['adjunto_nombre'] ?? null,
            ':adjunto_mime'    => $data['adjunto_mime']   ?? null,
            ':adjunto_bytes'   => $data['adjunto_bytes']  ?? null,
            // Postgres espera 't'/'f' vía PDO; el bool de PHP se envía como '' cuando
            // es false, y '' no es un boolean válido. Se normaliza explícitamente.
            ':sugerida_por_ia' => !empty($data['sugerida_por_ia']) ? 'true' : 'false',
            ':fuentes'         => isset($data['fuentes']) ? json_encode($data['fuentes'], JSON_UNESCAPED_UNICODE) : null,
            ':created_by'      => $data['id_usuario'] ?? null,
            ':updated_by'      => $data['id_usuario'] ?? null,
        ]);
        return (int) $this->db->lastInsertId('soporte_mensajes_id_seq');
    }

    /**
     * Mensajes de una conversación. Con $desdeId solo devuelve los posteriores,
     * que es lo que pide el polling incremental.
     */
    public function getMensajes(int $idConversacion, int $desdeId = 0): array
    {
        $sql = "SELECT m.id, m.rol, m.contenido, m.adjunto, m.adjunto_nombre,
                       m.adjunto_mime, m.adjunto_bytes, m.sugerida_por_ia,
                       m.fuentes, m.leido_at, m.created_at, m.created_by,
                       u.nombre AS autor_nombre
                  FROM soporte_mensajes m
                  LEFT JOIN usuarios u ON u.id = m.created_by
                 WHERE m.id_conversacion = :id_conversacion
                   AND m.eliminado = false
                   AND m.id > :desde_id
                 ORDER BY m.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_conversacion' => $idConversacion, ':desde_id' => $desdeId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findMensaje(int $idMensaje): ?array
    {
        $sql = "SELECT * FROM soporte_mensajes WHERE id = :id AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idMensaje]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Marca como leídos los mensajes escritos por la contraparte y pone a cero
     * el contador del lado que está leyendo.
     *
     * @param string $ladoQueLee 'usuario' | 'agente'
     */
    public function marcarLeidos(int $idConversacion, string $ladoQueLee): void
    {
        $rolContrario = $ladoQueLee === 'usuario' ? 'agente' : 'usuario';
        $columna      = $ladoQueLee === 'usuario' ? 'sin_leer_usuario' : 'sin_leer_agente';

        $st = $this->db->prepare(
            "UPDATE soporte_mensajes
                SET leido_at = CURRENT_TIMESTAMP
              WHERE id_conversacion = :id AND rol = :rol AND leido_at IS NULL AND eliminado = false"
        );
        $st->execute([':id' => $idConversacion, ':rol' => $rolContrario]);

        $this->db->prepare(
            "UPDATE soporte_conversaciones SET {$columna} = 0 WHERE id = :id"
        )->execute([':id' => $idConversacion]);
    }

    /**
     * Refresca la vista previa de la conversación e incrementa el contador de
     * no leídos del lado contrario al que escribió.
     */
    public function actualizarResumen(int $idConversacion, string $preview, string $rolAutor, int $idUsuario): void
    {
        // Un mensaje del sistema no marca nada como pendiente de leer.
        $incremento = match ($rolAutor) {
            'usuario' => 'sin_leer_agente  = sin_leer_agente + 1',
            'agente'  => 'sin_leer_usuario = sin_leer_usuario + 1',
            default   => 'sin_leer_agente  = sin_leer_agente',
        };

        // primera_respuesta_at se sella solo la primera vez que contesta un agente.
        $primeraResp = $rolAutor === 'agente'
            ? 'primera_respuesta_at = COALESCE(primera_respuesta_at, CURRENT_TIMESTAMP),'
            : '';

        $sql = "UPDATE soporte_conversaciones
                   SET ultimo_mensaje    = :preview,
                       ultimo_mensaje_at = CURRENT_TIMESTAMP,
                       {$primeraResp}
                       {$incremento},
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :id_usuario
                 WHERE id = :id";
        $this->db->prepare($sql)->execute([
            ':preview'    => mb_substr($preview, 0, 200),
            ':id_usuario' => $idUsuario,
            ':id'         => $idConversacion,
        ]);
    }

    /** Mensajes que el usuario envió en el último minuto (límite de tasa). */
    public function contarMensajesUltimoMinuto(int $idConversacion, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM soporte_mensajes
              WHERE id_conversacion = :id_conversacion
                AND created_by = :id_usuario
                AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '1 minute')"
        );
        $st->execute([':id_conversacion' => $idConversacion, ':id_usuario' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /** Id del último mensaje de la conversación: es el "número de versión" del pulso. */
    public function getUltimoMensajeId(int $idConversacion): int
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(id), 0) FROM soporte_mensajes WHERE id_conversacion = :id"
        );
        $st->execute([':id' => $idConversacion]);
        return (int) $st->fetchColumn();
    }

    /** Id del último mensaje de todo el sistema: versión de la bandeja del agente. */
    public function getUltimoMensajeIdGlobal(): int
    {
        return (int) $this->db->query("SELECT COALESCE(MAX(id), 0) FROM soporte_mensajes")->fetchColumn();
    }

    // ── Contadores para el navbar ────────────────────────────────────────────

    /** Mensajes sin leer que esperan al USUARIO (sus propias conversaciones). */
    public function contarSinLeerUsuario(int $idUsuario): int
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(sin_leer_usuario), 0)
               FROM soporte_conversaciones
              WHERE created_by = :id_usuario AND eliminado = false AND archivada = false"
        );
        $st->execute([':id_usuario' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /**
     * Carga pendiente de la bandeja (global, sin filtro de empresa):
     * conversaciones en espera y conversaciones con mensajes sin leer.
     *
     * @return array{espera:int,sin_leer:int}
     */
    public function contarBandeja(): array
    {
        $sql = "SELECT
                  COUNT(*) FILTER (WHERE estado = 'espera')      AS espera,
                  COUNT(*) FILTER (WHERE sin_leer_agente > 0)    AS sin_leer
                FROM soporte_conversaciones
               WHERE eliminado = false AND archivada = false";
        $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'espera'   => (int) ($row['espera'] ?? 0),
            'sin_leer' => (int) ($row['sin_leer'] ?? 0),
        ];
    }

    // ── Respuestas rápidas ───────────────────────────────────────────────────

    /**
     * Plantillas visibles para un agente: las de su empresa y las suyas propias.
     * @return array<int,array<string,mixed>>
     */
    public function getRespuestasRapidas(int $idEmpresa, int $idUsuario): array
    {
        $sql = "SELECT id, id_usuario, titulo, contenido, orden
                  FROM soporte_respuestas_rapidas
                 WHERE id_empresa = :id_empresa
                   AND eliminado = false
                   AND (id_usuario IS NULL OR id_usuario = :id_usuario)
                 ORDER BY id_usuario NULLS FIRST, orden ASC, titulo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRespuestaRapida(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM soporte_respuestas_rapidas
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearRespuestaRapida(int $idEmpresa, ?int $idUsuarioDueno, string $titulo, string $contenido, int $idUsuario): int
    {
        $sql = "INSERT INTO soporte_respuestas_rapidas
                    (id_empresa, id_usuario, titulo, contenido, orden, created_by, updated_by)
                VALUES (:id_empresa, :id_usuario_dueno, :titulo, :contenido, 0, :created_by, :updated_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $idEmpresa,
            ':id_usuario_dueno' => $idUsuarioDueno,
            ':titulo'           => $titulo,
            ':contenido'        => $contenido,
            ':created_by'       => $idUsuario,
            ':updated_by'       => $idUsuario,
        ]);
        return (int) $this->db->lastInsertId('soporte_respuestas_rapidas_id_seq');
    }

    public function actualizarRespuestaRapida(int $id, int $idEmpresa, string $titulo, string $contenido, int $idUsuario): void
    {
        $sql = "UPDATE soporte_respuestas_rapidas
                   SET titulo = :titulo, contenido = :contenido,
                       updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by
                 WHERE id = :id AND id_empresa = :id_empresa";
        $this->db->prepare($sql)->execute([
            ':titulo'     => $titulo,
            ':contenido'  => $contenido,
            ':updated_by' => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }

    public function eliminarRespuestaRapida(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE soporte_respuestas_rapidas
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by
                 WHERE id = :id AND id_empresa = :id_empresa";
        $this->db->prepare($sql)->execute([
            ':deleted_by' => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }

    // ── Alerta de conversaciones sin atender (cron) ──────────────────────────

    /**
     * Conversaciones que llevan más de N minutos esperando respuesta del equipo.
     * Es lo que dispara el aviso por correo.
     *
     * Cubre los dos casos en que alguien se queda esperando:
     *   - 'espera'    : nadie la ha tomado todavía.
     *   - 'atendiendo': ya la tomaron, pero quien consulta volvió a escribir y
     *                   esos mensajes siguen sin leer. Sin esta rama, un hilo
     *                   tomado y luego abandonado no avisaría nunca.
     *
     * Las resueltas y cerradas quedan fuera: ahí ya no se espera respuesta.
     * El filtro por sin_leer_agente evita avisar de hilos donde la última
     * palabra la tuvo el propio equipo.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getSinAtender(int $minutos): array
    {
        $sql = "SELECT c.id, c.id_empresa, c.asunto, c.estado, c.ultimo_mensaje, c.ultimo_mensaje_at, c.origen_modulo,
                       e.nombre AS empresa_nombre,
                       u.nombre AS usuario_nombre,
                       a.nombre AS agente_nombre,
                       EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - c.ultimo_mensaje_at)) / 60 AS minutos_espera
                  FROM soporte_conversaciones c
                  LEFT JOIN empresas e ON e.id = c.id_empresa
                  LEFT JOIN usuarios u ON u.id = c.created_by
                  LEFT JOIN usuarios a ON a.id = c.id_agente_asignado
                 WHERE c.eliminado = false
                   AND c.archivada = false
                   AND c.ultimo_mensaje_at IS NOT NULL
                   AND c.sin_leer_agente > 0
                   AND c.estado IN ('espera', 'atendiendo')
                   AND c.ultimo_mensaje_at < (CURRENT_TIMESTAMP - (:minutos || ' minutes')::interval)
                 ORDER BY c.ultimo_mensaje_at ASC
                 LIMIT 50";
        $st = $this->db->prepare($sql);
        $st->execute([':minutos' => $minutos]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Correos de las empresas que tienen asignado el submódulo del chat: son
     * las que atienden, así que son las que deben enterarse de lo que espera.
     *
     * No hay concepto aparte de "empresa de soporte": basta con asignar el
     * submódulo en modulos_asignados, como en cualquier otro módulo.
     *
     * @param int $idSubmodulo id de submodulos_menu para 'modulos/soporte-chat'
     * @return array<int,string>
     */
    public function getCorreosEmpresasAsignadas(int $idSubmodulo): array
    {
        if ($idSubmodulo <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT e.mail
                  FROM modulos_asignados ma
                  INNER JOIN empresas e ON e.id = ma.id_empresa
                 WHERE ma.id_submodulo = :id_submodulo
                   AND ma.r = 1
                   AND e.eliminado = false
                   AND e.mail IS NOT NULL
                   AND TRIM(e.mail) <> ''";
        $st = $this->db->prepare($sql);
        $st->execute([':id_submodulo' => $idSubmodulo]);

        return array_values(array_filter(
            array_map('trim', $st->fetchAll(PDO::FETCH_COLUMN)),
            static fn (string $m): bool => filter_var($m, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /**
     * Empresas que atienden (tienen asignado el submódulo del chat) y además
     * tienen WhatsApp configurado, en orden de id. La primera es la que se usa
     * para emitir el aviso: sus credenciales de Meta son las que envían.
     *
     * @param int $idSubmodulo id de submodulos_menu para 'modulos/soporte-chat'
     * @return int[] ids de empresa
     */
    public function getEmpresasAsignadasConWhatsapp(int $idSubmodulo): array
    {
        if ($idSubmodulo <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT e.id
                  FROM modulos_asignados ma
                  INNER JOIN empresas e ON e.id = ma.id_empresa
                  INNER JOIN empresa_whatsapp_config w ON w.id_empresa = e.id
                 WHERE ma.id_submodulo = :id_submodulo
                   AND ma.r = 1
                   AND e.eliminado = false
                   AND e.estado = '1'
                   AND COALESCE(w.eliminado, false) = false
                   AND COALESCE(w.status, true) = true
                   AND w.access_token    IS NOT NULL AND TRIM(w.access_token)    <> ''
                   AND w.phone_number_id IS NOT NULL AND TRIM(w.phone_number_id) <> ''
                 ORDER BY e.id";
        $st = $this->db->prepare($sql);
        $st->execute([':id_submodulo' => $idSubmodulo]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** ¿Esta empresa tiene WhatsApp utilizable (credenciales completas y activo)? */
    public function empresaTieneWhatsapp(int $idEmpresa): bool
    {
        if ($idEmpresa <= 0) {
            return false;
        }

        $sql = "SELECT 1
                  FROM empresa_whatsapp_config w
                  INNER JOIN empresas e ON e.id = w.id_empresa
                 WHERE w.id_empresa = :id_empresa
                   AND COALESCE(w.eliminado, false) = false
                   AND COALESCE(w.status, true) = true
                   AND w.access_token    IS NOT NULL AND TRIM(w.access_token)    <> ''
                   AND w.phone_number_id IS NOT NULL AND TRIM(w.phone_number_id) <> ''
                   AND e.eliminado = false
                   AND e.estado = '1'
                 LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        return $st->fetchColumn() !== false;
    }

    /**
     * Números que reciben los avisos de WhatsApp de una empresa. Es la misma
     * lista que usa el aviso de chats sin leer del módulo de WhatsApp
     * (whatsapp_aviso_numeros): quien atiende no tiene por qué mantener dos.
     *
     * @return array<int,string> solo dígitos
     */
    public function getNumerosAvisoWhatsapp(int $idEmpresa): array
    {
        if ($idEmpresa <= 0) {
            return [];
        }

        $sql = "SELECT telefono
                  FROM whatsapp_aviso_numeros
                 WHERE id_empresa = :id_empresa
                   AND activo    = true
                   AND eliminado = false
                 ORDER BY id";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $numeros = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $tel) {
            $limpio = preg_replace('/\D/', '', (string) $tel);
            if ($limpio !== '') {
                $numeros[$limpio] = true;   // clave = número: descarta repetidos
            }
        }

        return array_keys($numeros);
    }

    // ── Configuración global ─────────────────────────────────────────────────

    public function getConfig(): array
    {
        $row = $this->db->query("SELECT * FROM soporte_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    /**
     * @param array<string,mixed> $data claves ya validadas por Rules
     *
     * Las columnas de WhatsApp llegaron después (migración
     * 20260827_soporte_chat_whatsapp_alertas.sql). Si todavía no están en la
     * base, se guarda el resto en lugar de dejar la pantalla de configuración
     * sin poder guardar nada hasta desplegar el SQL.
     */
    public function guardarConfig(array $data, int $idUsuario): void
    {
        $comunes = [
            ':activo'                => $data['activo'] ? 'true' : 'false',
            ':copiloto_activo'       => $data['copiloto_activo'] ? 'true' : 'false',
            ':mensaje_bienvenida'    => $data['mensaje_bienvenida'],
            ':mensaje_fuera_horario' => $data['mensaje_fuera_horario'],
            ':dias_atencion'         => $data['dias_atencion'],
            ':hora_inicio'           => $data['hora_inicio'],
            ':hora_fin'              => $data['hora_fin'],
            ':minutos_alerta'        => $data['minutos_alerta_sin_atender'],
            ':correo_alertas'        => $data['correo_alertas'],
            ':dias_archivar'         => $data['dias_archivar_cerradas'],
            ':updated_by'            => $idUsuario,
        ];

        $set = "activo                     = :activo,
                       copiloto_activo            = :copiloto_activo,
                       mensaje_bienvenida         = :mensaje_bienvenida,
                       mensaje_fuera_horario      = :mensaje_fuera_horario,
                       dias_atencion              = :dias_atencion,
                       hora_inicio                = :hora_inicio,
                       hora_fin                   = :hora_fin,
                       minutos_alerta_sin_atender = :minutos_alerta,
                       correo_alertas             = :correo_alertas,
                       dias_archivar_cerradas     = :dias_archivar,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :updated_by";

        $setWhatsapp = "whatsapp_alertas          = :whatsapp_alertas,
                       whatsapp_plantilla         = :whatsapp_plantilla,
                       whatsapp_plantilla_idioma  = :whatsapp_idioma,
                       ";

        // Se comprueba antes en vez de intentar y capturar el error: un fallo de
        // SQL dentro de una transacción la deja abortada (25P02) y el reintento
        // fallaría igual.
        if (!$this->tieneColumnasWhatsapp()) {
            $this->db->prepare("UPDATE soporte_config SET {$set} WHERE id = 1")->execute($comunes);
            return;
        }

        $this->db->prepare("UPDATE soporte_config SET {$setWhatsapp}{$set} WHERE id = 1")->execute(
            $comunes + [
                ':whatsapp_alertas'   => $data['whatsapp_alertas'] ?? null,
                ':whatsapp_plantilla' => $data['whatsapp_plantilla'] ?? null,
                ':whatsapp_idioma'    => $data['whatsapp_plantilla_idioma'] ?? 'es',
            ]
        );
    }

    /** ¿Está desplegada la migración del aviso por WhatsApp? Se resuelve una vez por request. */
    private function tieneColumnasWhatsapp(): bool
    {
        static $existe = null;
        if ($existe !== null) {
            return $existe;
        }

        try {
            $st = $this->db->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_name = 'soporte_config' AND column_name = 'whatsapp_alertas'
                  LIMIT 1"
            );
            return $existe = ($st !== false && $st->fetchColumn() !== false);
        } catch (\Throwable $e) {
            return $existe = false;
        }
    }

    // ── Archivado (lo ejecuta el cron) ───────────────────────────────────────

    /**
     * Archiva las conversaciones cerradas/resueltas que ya cumplieron el plazo.
     * Archivar NO borra: solo las saca de la bandeja.
     *
     * @return int conversaciones archivadas
     */
    public function archivarCerradas(int $dias): int
    {
        if ($dias <= 0) {
            return 0;
        }
        $sql = "UPDATE soporte_conversaciones
                   SET archivada = true, archivada_at = CURRENT_TIMESTAMP
                 WHERE archivada = false
                   AND eliminado = false
                   AND cerrada_at IS NOT NULL
                   AND cerrada_at < (CURRENT_TIMESTAMP - (:dias || ' days')::interval)";
        $st = $this->db->prepare($sql);
        $st->execute([':dias' => $dias]);
        return $st->rowCount();
    }
}
