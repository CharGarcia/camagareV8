<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: conversaciones (crm_conversaciones) y sus mensajes (crm_mensajes).
 * Diseño: docs/diseno/crm-redes-fase1.md §5.3.
 *
 * Concurrencia (CLAUDE.md §8): «obtener o crear» la conversación de un hilo va
 * bajo lockHilo(); los contadores (sin_leer) se actualizan con UPDATE atómico,
 * nunca leyendo y escribiendo desde PHP.
 */
class CrmConversacionRepository extends BaseRepository
{
    protected string $table = 'crm_conversaciones';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function lockHilo(int $idCanal, string $tipo, string $hilo): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_conv:' || CAST(:c AS text) || ':' || :t || ':' || :h))");
        $st->execute([':c' => $idCanal, ':t' => $tipo, ':h' => $hilo]);
    }

    /** Candado del reparto por turnos de un canal (leer carga → elegir → asignar). */
    public function lockReparto(int $idCanal): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_reparto:' || CAST(:c AS text)))");
        $st->execute([':c' => $idCanal]);
    }

    public function buscarHilo(int $idEmpresa, int $idCanal, string $tipo, string $hilo): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM crm_conversaciones
              WHERE id_empresa = :e AND id_canal = :c AND tipo = :t AND id_hilo_externo = :h AND eliminado = false'
        );
        $st->execute([':e' => $idEmpresa, ':c' => $idCanal, ':t' => $tipo, ':h' => $hilo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(int $idEmpresa, array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_conversaciones (id_empresa, id_canal, tipo, id_hilo_externo, id_publicacion_externa,
                                             id_contacto, id_responsable, estado, created_by)
             VALUES (:e, :c, :t, :h, :p, :ct, :r, 'abierta', NULL) RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':c' => $d['id_canal'], ':t' => $d['tipo'], ':h' => $d['id_hilo_externo'],
            ':p' => $d['id_publicacion_externa'] ?? null, ':ct' => $d['id_contacto'] ?? null, ':r' => $d['id_responsable'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Tras guardar un mensaje: extracto y hora del último mensaje; si es del
     * cliente, además ventana de 24 h, +1 sin leer y reabre la conversación.
     * Un mensaje viejo que llega tarde (reintento de Meta) no pisa uno más nuevo.
     */
    public function registrarMensaje(int $id, string $extracto, string $fecha, bool $entrante): void
    {
        $extra = $entrante
            ? ", ultima_entrada_at = GREATEST(COALESCE(ultima_entrada_at, :f2), :f3), sin_leer = sin_leer + 1, estado = 'abierta'"
            : '';
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET
                ultimo_mensaje = CASE WHEN ultimo_mensaje_at IS NULL OR ultimo_mensaje_at <= :f4 THEN :x ELSE ultimo_mensaje END,
                ultimo_mensaje_at = GREATEST(COALESCE(ultimo_mensaje_at, :f), :f1),
                updated_at = NOW(){$extra}
              WHERE id = :id"
        );
        $p = [':f4' => $fecha, ':x' => mb_substr($extracto, 0, 200), ':f' => $fecha, ':f1' => $fecha, ':id' => $id];
        if ($entrante) {
            $p[':f2'] = $fecha;
            $p[':f3'] = $fecha;
        }
        $st->execute($p);
    }

    /**
     * Reparto por turnos: el usuario de la lista con menos conversaciones
     * ABIERTAS asignadas en el canal (empate: el primero de la lista).
     * Llamar bajo lockReparto().
     */
    public function usuarioConMenosCarga(int $idCanal, array $usuarios): ?int
    {
        $usuarios = array_values(array_filter(array_map('intval', $usuarios)));
        if (!$usuarios) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT id_responsable, COUNT(*) AS n FROM crm_conversaciones
              WHERE id_canal = :c AND eliminado = false AND estado = 'abierta' AND id_responsable IS NOT NULL
              GROUP BY id_responsable"
        );
        $st->execute([':c' => $idCanal]);
        $carga = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'n', 'id_responsable');
        $mejor = null;
        foreach ($usuarios as $u) {
            if ($mejor === null || (int) ($carga[$u] ?? 0) < (int) ($carga[$mejor] ?? 0)) {
                $mejor = $u;
            }
        }
        return $mejor;
    }

    // ── Bandeja ──────────────────────────────────────────────────────────────

    /**
     * Visibilidad en la bandeja (decisión 4 del diseño, §10): sin acceso total el
     * usuario ve las conversaciones asignadas a él y las que están sin asignar.
     */
    private static function condicionVisibles(string $alias, array &$p, ?int $idUsuarioFiltro): string
    {
        if ($idUsuarioFiltro === null) {
            return '';
        }
        $p[':vis_u'] = $idUsuarioFiltro;
        return " AND ({$alias}.id_responsable = :vis_u OR {$alias}.id_responsable IS NULL)";
    }

    private const SELECT_BANDEJA = "SELECT cv.id, cv.id_canal, cv.tipo, cv.id_hilo_externo, cv.id_publicacion_externa,
            cv.id_contacto, cv.id_responsable, cv.estado, cv.ultimo_mensaje, cv.ultimo_mensaje_at,
            cv.ultima_entrada_at, cv.sin_leer, cv.created_at,
            ct.nombre AS contacto, ct.instagram_usuario, ct.id_cliente,
            ca.usuario AS canal_usuario, ca.estado AS canal_estado, ca.ventana_humana,
            ur.nombre AS responsable";

    private const FROM_BANDEJA = "
          FROM crm_conversaciones cv
          JOIN crm_canales ca ON ca.id = cv.id_canal
          LEFT JOIN crm_contactos ct ON ct.id = cv.id_contacto
          LEFT JOIN usuarios ur ON ur.id = cv.id_responsable";

    /**
     * Lista de la bandeja, más recientes primero.
     * @param array $f alcance (mias|sin_asignar|todas), tipo (dm|comentario|''), estado (abierta|cerrada|''), b, antes (cursor ultimo_mensaje_at)
     */
    public function listarBandeja(int $idEmpresa, array $f, int $idUsuario, ?int $idUsuarioFiltro, int $limite = 50): array
    {
        $p = [':e' => $idEmpresa];
        $where = 'WHERE cv.id_empresa = :e AND cv.eliminado = false' . self::condicionVisibles('cv', $p, $idUsuarioFiltro);
        if (($f['alcance'] ?? '') === 'mias') {
            $where .= ' AND cv.id_responsable = :mias';
            $p[':mias'] = $idUsuario;
        } elseif (($f['alcance'] ?? '') === 'sin_asignar') {
            $where .= ' AND cv.id_responsable IS NULL';
        }
        if (in_array($f['tipo'] ?? '', ['dm', 'comentario'], true)) {
            $where .= ' AND cv.tipo = :tipo';
            $p[':tipo'] = $f['tipo'];
        }
        if (in_array($f['estado'] ?? '', ['abierta', 'cerrada'], true)) {
            $where .= ' AND cv.estado = :est';
            $p[':est'] = $f['estado'];
        }
        $b = trim((string) ($f['b'] ?? ''));
        if ($b !== '') {
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto(['ct.nombre', 'ct.instagram_usuario', 'cv.ultimo_mensaje'], $b, $p, 'bq');
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        if (!empty($f['antes'])) {
            $where .= ' AND cv.ultimo_mensaje_at < :antes';
            $p[':antes'] = $f['antes'];
        }
        $st = $this->db->prepare(self::SELECT_BANDEJA . self::FROM_BANDEJA . " {$where}
            ORDER BY cv.ultimo_mensaje_at DESC NULLS LAST, cv.id DESC LIMIT " . max(1, min(200, $limite)));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conversaciones con mensajes sin leer visibles para el usuario (aviso del menú). */
    public function contarSinLeer(int $idEmpresa, ?int $idUsuarioFiltro): int
    {
        $p = [':e' => $idEmpresa];
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM crm_conversaciones cv WHERE cv.id_empresa = :e AND cv.eliminado = false
                AND cv.estado = \'abierta\' AND cv.sin_leer > 0' . self::condicionVisibles('cv', $p, $idUsuarioFiltro)
        );
        $st->execute($p);
        return (int) $st->fetchColumn();
    }

    /** Una conversación con sus datos para la bandeja (incluye la cuenta externa). */
    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(self::SELECT_BANDEJA . ', ca.id_externo AS canal_id_externo' . self::FROM_BANDEJA . '
            WHERE cv.id = :id AND cv.id_empresa = :e AND cv.eliminado = false');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Bloquea la conversación (enviar, asignar, cerrar) hasta el fin de la transacción. */
    public function bloquear(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare('SELECT * FROM crm_conversaciones WHERE id = :id AND id_empresa = :e AND eliminado = false FOR UPDATE');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Mensajes (los más nuevos que $desdeId), en orden cronológico. */
    public function mensajes(int $idConversacion, int $idEmpresa, int $desdeId = 0, int $limite = 300): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM (
                SELECT m.id, m.direccion, m.autor, m.id_usuario, m.tipo, m.texto, m.adjunto_ruta, m.id_externo,
                       m.estado_envio, m.error, m.fecha, m.eliminado, u.nombre AS usuario
                  FROM crm_mensajes m
                  LEFT JOIN usuarios u ON u.id = m.id_usuario
                 WHERE m.id_conversacion = :c AND m.id_empresa = :e AND m.id > :d
                 ORDER BY m.fecha DESC, m.id DESC LIMIT " . max(1, min(500, $limite)) . "
             ) x ORDER BY x.fecha, x.id"
        );
        $st->execute([':c' => $idConversacion, ':e' => $idEmpresa, ':d' => $desdeId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMensaje(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare('SELECT * FROM crm_mensajes WHERE id = :id AND id_empresa = :e');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Último comentario del cliente en un hilo de comentarios (a ese se responde). */
    public function ultimoComentarioCliente(int $idConversacion): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, id_externo, fecha FROM crm_mensajes
              WHERE id_conversacion = :c AND direccion = 'IN' AND tipo = 'comment' AND eliminado = false
              ORDER BY fecha DESC, id DESC LIMIT 1"
        );
        $st->execute([':c' => $idConversacion]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** ¿Ya se envió la respuesta privada (única) a ese comentario? */
    public function tieneRespuestaPrivada(int $idConversacion, string $idComentario): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM crm_mensajes
              WHERE id_conversacion = :c AND tipo = 'private_reply' AND eliminado = false
                AND COALESCE(estado_envio, '') <> 'fallido' AND contenido->>'comment_id' = :x LIMIT 1"
        );
        $st->execute([':c' => $idConversacion, ':x' => $idComentario]);
        return (bool) $st->fetchColumn();
    }

    public function marcarLeida(int $id, int $idEmpresa): void
    {
        $st = $this->db->prepare('UPDATE crm_conversaciones SET sin_leer = 0 WHERE id = :id AND id_empresa = :e AND sin_leer > 0');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
    }

    public function asignar(int $id, int $idEmpresa, ?int $idResponsable, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_conversaciones SET id_responsable = :r, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':r' => $idResponsable, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_conversaciones SET estado = :s, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':s' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function enlazarContacto(int $id, int $idEmpresa, int $idContacto, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_conversaciones SET id_contacto = :c, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':c' => $idContacto, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Mensaje saliente escrito en el sistema, antes de enviarlo (estado «enviando»). */
    public function insertarSaliente(int $idEmpresa, int $idConversacion, string $tipo, string $texto, int $idUsuario, array $contenido): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_mensajes (id_empresa, id_conversacion, direccion, autor, id_usuario, tipo, texto,
                                       estado_envio, contenido, fecha, created_by)
             VALUES (:e, :c, 'OUT', 'usuario', :u, :t, :tx, 'enviando', CAST(:j AS jsonb), NOW(), :u2) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $idConversacion, ':u' => $idUsuario, ':t' => $tipo, ':tx' => $texto,
                      ':j' => json_encode($contenido, JSON_UNESCAPED_UNICODE), ':u2' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /**
     * Resultado del envío. El id externo no se pisa si el eco del webhook ya lo
     * «adoptó» (ver adoptarEco()).
     */
    public function resultadoEnvio(int $idMensaje, ?string $idExterno, string $estado, ?string $error): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_mensajes SET id_externo = COALESCE(id_externo, :x),
                    estado_envio = CASE WHEN estado_envio = 'leido' THEN estado_envio ELSE :s END,
                    error = :er, updated_at = NOW()
              WHERE id = :id"
        );
        $st->execute([':x' => $idExterno, ':s' => $estado, ':er' => $error, ':id' => $idMensaje]);
    }

    /** Reintento de un mensaje fallido: vuelve a «enviando». */
    public function reintentar(int $idMensaje): void
    {
        $st = $this->db->prepare("UPDATE crm_mensajes SET estado_envio = 'enviando', error = NULL, updated_at = NOW() WHERE id = :id");
        $st->execute([':id' => $idMensaje]);
    }

    /**
     * El eco del webhook de un mensaje que salió del propio sistema puede llegar
     * antes de que el envío termine de anotar su id: si hay un saliente reciente
     * sin id con el mismo texto, se le asigna el id del eco en vez de duplicarlo.
     * Devuelve true si lo adoptó.
     */
    public function adoptarEco(int $idConversacion, string $idExterno, ?string $texto): bool
    {
        if ($texto === null || $texto === '') {
            return false;
        }
        $st = $this->db->prepare(
            "UPDATE crm_mensajes SET id_externo = :x, estado_envio = CASE WHEN estado_envio = 'enviando' THEN 'enviado' ELSE estado_envio END,
                    updated_at = NOW()
              WHERE id = (SELECT id FROM crm_mensajes
                           WHERE id_conversacion = :c AND direccion = 'OUT' AND autor = 'usuario' AND id_externo IS NULL
                             AND texto = :t AND fecha > NOW() - INTERVAL '10 minutes'
                           ORDER BY fecha, id LIMIT 1)"
        );
        $st->execute([':x' => $idExterno, ':c' => $idConversacion, ':t' => $texto]);
        return $st->rowCount() > 0;
    }

    // ── Mensajes ─────────────────────────────────────────────────────────────

    /**
     * Inserta el mensaje si su id externo no existía (reintentos de Meta).
     * Devuelve el id nuevo o null si ya estaba.
     */
    public function insertarMensajeSiNuevo(int $idEmpresa, array $m): ?int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_mensajes (id_empresa, id_conversacion, direccion, autor, id_usuario, tipo, texto,
                                       adjunto_ruta, id_externo, estado_envio, contenido, fecha, created_by)
             VALUES (:e, :c, :d, :a, :u, :t, :tx, :adj, :x, :es, CAST(:j AS jsonb), :f, NULL)
             ON CONFLICT DO NOTHING
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':c' => $m['id_conversacion'], ':d' => $m['direccion'], ':a' => $m['autor'],
            ':u' => $m['id_usuario'] ?? null, ':t' => mb_substr((string) $m['tipo'], 0, 20), ':tx' => $m['texto'] ?? null,
            ':adj' => $m['adjunto_ruta'] ?? null, ':x' => $m['id_externo'], ':es' => $m['estado_envio'] ?? null,
            ':j' => json_encode($m['contenido'] ?? [], JSON_UNESCAPED_UNICODE), ':f' => $m['fecha'],
        ]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** El cliente leyó: los mensajes salientes hasta esa hora pasan a «leído». */
    public function marcarLeidos(int $idConversacion, string $hasta): int
    {
        $st = $this->db->prepare(
            "UPDATE crm_mensajes SET estado_envio = 'leido', updated_at = NOW()
              WHERE id_conversacion = :c AND direccion = 'OUT' AND eliminado = false
                AND COALESCE(estado_envio, '') IN ('', 'enviado') AND fecha <= :h"
        );
        $st->execute([':c' => $idConversacion, ':h' => $hasta]);
        return $st->rowCount();
    }

    /** El cliente borró su mensaje en Instagram: se marca eliminado (no se borra). */
    public function marcarMensajeBorrado(int $idEmpresa, string $idExterno): int
    {
        $st = $this->db->prepare(
            "UPDATE crm_mensajes SET eliminado = true, deleted_at = NOW(), updated_at = NOW()
              WHERE id_empresa = :e AND id_externo = :x AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa, ':x' => $idExterno]);
        return $st->rowCount();
    }
}
