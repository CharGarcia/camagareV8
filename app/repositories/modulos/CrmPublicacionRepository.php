<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * CRM fase 3: publicaciones, sus medios y sus destinos (una fila por red/cuenta).
 * Diseño: docs/diseno/crm-redes-fase3.md.
 */
class CrmPublicacionRepository extends BaseRepository
{
    public const TIPOS_CANAL = ['instagram', 'linkedin_perfil', 'linkedin_pagina'];

    public const MAPA_ORDEN = [
        'titulo'        => 'p.titulo',
        'estado'        => 'p.estado',
        'programada_at' => 'p.programada_at',
        'publicada_at'  => 'p.publicada_at',
        'responsable'   => 'ur.nombre',
        'created_at'    => 'p.created_at',
    ];

    public function __construct()
    {
        parent::__construct('crm_publicaciones');
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_publicaciones');
    }

    // ── Configuración por empresa ────────────────────────────────────────────

    public function config(int $idEmpresa): array
    {
        $st = $this->db->prepare('SELECT requiere_aprobacion, max_publicaciones_dia FROM crm_publicaciones_config WHERE id_empresa = :e AND eliminado = false');
        $st->execute([':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return [
            'requiere_aprobacion' => $r ? in_array($r['requiere_aprobacion'], [true, 't', 'true', 1, '1'], true) : false,
            'max_publicaciones_dia' => $r ? (int) $r['max_publicaciones_dia'] : 25,
            'guardada' => (bool) $r,
        ];
    }

    public function guardarConfig(int $idEmpresa, bool $aprobacion, int $maxDia, int $idUsuario): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_pub_config:' || CAST(:e AS text)))");
        $st->execute([':e' => $idEmpresa]);
        $p = [':a' => $aprobacion ? 'true' : 'false', ':m' => $maxDia, ':u' => $idUsuario, ':e' => $idEmpresa];
        $st = $this->db->prepare('UPDATE crm_publicaciones_config SET requiere_aprobacion = :a, max_publicaciones_dia = :m, updated_at = NOW(), updated_by = :u
                                   WHERE id_empresa = :e AND eliminado = false');
        $st->execute($p);
        if ($st->rowCount() === 0) {
            $st = $this->db->prepare('INSERT INTO crm_publicaciones_config (id_empresa, requiere_aprobacion, max_publicaciones_dia, created_by, updated_by)
                                       VALUES (:e, :a, :m, :u, :u2)');
            $st->execute($p + [':u2' => $idUsuario]);
        }
    }

    // ── Publicación ──────────────────────────────────────────────────────────

    public function insertar(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_publicaciones (id_empresa, titulo, texto, estado, es_contenido_ia, id_responsable, created_by, updated_by)
             VALUES (:e, :t, :x, 'borrador', :ia, :r, :u, :u2) RETURNING id"
        );
        $st->execute([':e' => $idEmpresa, ':t' => $this->caparTexto('titulo', $d['titulo']), ':x' => $d['texto'],
                      ':ia' => !empty($d['es_contenido_ia']) ? 'true' : 'false', ':r' => $d['id_responsable'] ?? $idUsuario,
                      ':u' => $idUsuario, ':u2' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function actualizarCabecera(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_publicaciones SET titulo = :t, texto = :x, es_contenido_ia = :ia, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':t' => $this->caparTexto('titulo', $d['titulo']), ':x' => $d['texto'], ':ia' => !empty($d['es_contenido_ia']) ? 'true' : 'false',
                      ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Fila bajo FOR UPDATE (todo cambio de estado pasa por aquí). */
    public function bloquear(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare('SELECT * FROM crm_publicaciones WHERE id = :id AND id_empresa = :e AND eliminado = false FOR UPDATE');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            'SELECT p.*, ur.nombre AS responsable, ua.nombre AS aprobada_por_nombre, uc.nombre AS creada_por
               FROM crm_publicaciones p
               LEFT JOIN usuarios ur ON ur.id = p.id_responsable
               LEFT JOIN usuarios ua ON ua.id = p.aprobada_por
               LEFT JOIN usuarios uc ON uc.id = p.created_by
              WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false'
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** $extra: programada_at, aprobada_por/aprobada_at, publicada_at (solo esas). */
    public function cambiarEstado(int $id, int $idEmpresa, string $estado, ?int $idUsuario, array $extra = []): void
    {
        $sets = ['estado = :s', 'updated_at = NOW()', 'updated_by = COALESCE(:u, updated_by)'];
        $p = [':s' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa];
        foreach (['programada_at', 'aprobada_por', 'aprobada_at', 'publicada_at'] as $c) {
            if (array_key_exists($c, $extra)) {
                $sets[] = "{$c} = :{$c}";
                $p[':' . $c] = $extra[$c];
            }
        }
        $st = $this->db->prepare('UPDATE crm_publicaciones SET ' . implode(', ', $sets) . ' WHERE id = :id AND id_empresa = :e');
        $st->execute($p);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_publicaciones SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ── Medios y destinos ────────────────────────────────────────────────────

    /** Medios de la publicación en orden (solo los que siguen en la biblioteca). */
    public function medios(int $idPub): array
    {
        $st = $this->db->prepare(
            'SELECT m.*, pm.orden FROM crm_publicaciones_medios pm
               JOIN crm_medios m ON m.id = pm.id_medio AND m.eliminado = false
              WHERE pm.id_publicacion = :p AND pm.eliminado = false ORDER BY pm.orden, pm.id'
        );
        $st->execute([':p' => $idPub]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Medios de la empresa que existen, en el orden pedido. */
    public function mediosDeEmpresa(int $idEmpresa, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $st = $this->db->prepare('SELECT * FROM crm_medios WHERE id_empresa = :e AND eliminado = false AND id = ANY (CAST(:ids AS int[]))');
        $st->execute([':e' => $idEmpresa, ':ids' => '{' . implode(',', $ids) . '}']);
        $porId = array_column($st->fetchAll(PDO::FETCH_ASSOC), null, 'id');
        return array_values(array_filter(array_map(fn($i) => $porId[$i] ?? null, $ids)));
    }

    public function reemplazarMedios(int $idPub, int $idEmpresa, array $idsMedios, int $idUsuario): void
    {
        $st = $this->db->prepare('UPDATE crm_publicaciones_medios SET eliminado = true, deleted_at = NOW(), deleted_by = :u
                                   WHERE id_publicacion = :p AND id_empresa = :e AND eliminado = false');
        $st->execute([':u' => $idUsuario, ':p' => $idPub, ':e' => $idEmpresa]);
        $ins = $this->db->prepare('INSERT INTO crm_publicaciones_medios (id_empresa, id_publicacion, id_medio, orden, created_by, updated_by)
                                    VALUES (:e, :p, :m, :o, :u, :u2)');
        foreach (array_values($idsMedios) as $i => $idMedio) {
            $ins->execute([':e' => $idEmpresa, ':p' => $idPub, ':m' => (int) $idMedio, ':o' => $i, ':u' => $idUsuario, ':u2' => $idUsuario]);
        }
    }

    /**
     * ¿Está aplicado database/2026-11-10_crm_publicaciones_enlace.sql? Sin él, el
     * módulo sigue funcionando sin el formato «Enlace» de LinkedIn.
     */
    public function admiteEnlace(): bool
    {
        return $this->admiteEnlace ??= $this->columnaExiste('crm_publicaciones_destinos', 'link_url');
    }

    private ?bool $admiteEnlace = null;

    // ── Respuesta automática a comentarios (Instagram) ───────────────────────

    /** ¿Está aplicado database/2026-11-10_crm_publicaciones_respuesta_auto.sql? */
    public function admiteRespuestaAuto(): bool
    {
        return $this->admiteRespuestaAuto ??= $this->columnaExiste('crm_publicaciones', 'auto_palabra');
    }

    private ?bool $admiteRespuestaAuto = null;

    public function guardarRespuestaAuto(int $id, int $idEmpresa, ?string $palabra, ?string $mensaje, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_publicaciones SET auto_palabra = :p, auto_mensaje = :m, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':p' => $palabra, ':m' => $mensaje, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Respuesta automática de la publicación que salió en esa cuenta con ese id
     * de Instagram (el que trae el comentario). null si no tiene.
     * @return array{id:int, auto_palabra:string, auto_mensaje:string}|null
     */
    public function respuestaAutoDeExterna(int $idEmpresa, int $idCanal, string $idExterno): ?array
    {
        if ($idExterno === '' || !$this->admiteRespuestaAuto()) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT p.id, p.auto_palabra, p.auto_mensaje
               FROM crm_publicaciones_destinos d
               JOIN crm_publicaciones p ON p.id = d.id_publicacion AND p.id_empresa = d.id_empresa AND p.eliminado = false
              WHERE d.id_empresa = :e AND d.id_canal = :c AND d.id_externo = :x AND d.eliminado = false
                AND COALESCE(TRIM(p.auto_palabra), '') <> '' AND COALESCE(TRIM(p.auto_mensaje), '') <> ''
              ORDER BY d.id DESC LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $idCanal, ':x' => $idExterno]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Destinos con los datos de su cuenta (sin token). */
    public function destinos(int $idPub): array
    {
        $link = $this->admiteEnlace() ? 'd.link_url, d.link_titulo' : 'NULL AS link_url, NULL AS link_titulo';
        $st = $this->db->prepare(
            "SELECT d.id, d.id_canal, d.formato, d.texto, {$link}, d.estado, d.intentos, d.siguiente_intento_at, d.id_externo, d.enlace,
                    d.error, d.publicada_at, c.tipo AS canal_tipo, c.usuario AS canal_usuario, c.nombre AS canal_nombre, c.estado AS canal_estado,
                    c.permisos AS canal_permisos
               FROM crm_publicaciones_destinos d JOIN crm_canales c ON c.id = d.id_canal
              WHERE d.id_publicacion = :p AND d.eliminado = false ORDER BY d.id"
        );
        $st->execute([':p' => $idPub]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reemplazarDestinos(int $idPub, int $idEmpresa, array $destinos, int $idUsuario): void
    {
        $st = $this->db->prepare('UPDATE crm_publicaciones_destinos SET eliminado = true, deleted_at = NOW(), deleted_by = :u
                                   WHERE id_publicacion = :p AND id_empresa = :e AND eliminado = false');
        $st->execute([':u' => $idUsuario, ':p' => $idPub, ':e' => $idEmpresa]);
        $conLink = $this->admiteEnlace();
        $ins = $this->db->prepare('INSERT INTO crm_publicaciones_destinos (id_empresa, id_publicacion, id_canal, formato, texto'
            . ($conLink ? ', link_url, link_titulo' : '') . ', created_by, updated_by)
                                    VALUES (:e, :p, :c, :f, :t' . ($conLink ? ', :lu, :lt' : '') . ', :u, :u2)');
        foreach ($destinos as $d) {
            $p = [':e' => $idEmpresa, ':p' => $idPub, ':c' => (int) $d['id_canal'], ':f' => $d['formato'],
                  ':t' => $d['texto'] ?? null, ':u' => $idUsuario, ':u2' => $idUsuario];
            if ($conLink) {
                $p[':lu'] = $d['link_url'] ?? null;
                $p[':lt'] = $d['link_titulo'] ?? null;
            }
            $ins->execute($p);
        }
    }

    /** Cuentas a las que la empresa puede publicar. */
    public function canalesPublicables(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, tipo, usuario, nombre, estado, permisos FROM crm_canales
              WHERE id_empresa = :e AND eliminado = false AND tipo = ANY (CAST(:t AS text[])) ORDER BY tipo, usuario, id"
        );
        $st->execute([':e' => $idEmpresa, ':t' => '{' . implode(',', self::TIPOS_CANAL) . '}']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Programar / reprogramar: los destinos aún no publicados vuelven a pendiente. */
    public function destinosAPendiente(int $idPub, int $idEmpresa): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_publicaciones_destinos SET estado = 'pendiente', intentos = 0, error = NULL, siguiente_intento_at = NULL,
                    contenedor_externo = NULL, contenedores = NULL, updated_at = NOW()
              WHERE id_publicacion = :p AND id_empresa = :e AND eliminado = false AND estado <> 'publicada'"
        );
        $st->execute([':p' => $idPub, ':e' => $idEmpresa]);
    }

    /** Cancela lo que aún no salió. Devuelve cuántos destinos canceló. */
    public function cancelarDestinosPendientes(int $idPub, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "UPDATE crm_publicaciones_destinos SET estado = 'cancelada', siguiente_intento_at = NULL, updated_at = NOW()
              WHERE id_publicacion = :p AND id_empresa = :e AND eliminado = false AND estado IN ('pendiente', 'preparando', 'con_error')"
        );
        $st->execute([':p' => $idPub, ':e' => $idEmpresa]);
        return $st->rowCount();
    }

    public function estadosDestinos(int $idPub): array
    {
        $st = $this->db->prepare('SELECT estado FROM crm_publicaciones_destinos WHERE id_publicacion = :p AND eliminado = false');
        $st->execute([':p' => $idPub]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── Listado y calendario ─────────────────────────────────────────────────

    /** Sin acceso total: las que creó o tiene a cargo. */
    private static function condicionPropios(array &$p, ?int $idUsuarioFiltro): string
    {
        if ($idUsuarioFiltro === null) {
            return '';
        }
        $p[':pc'] = $idUsuarioFiltro;
        $p[':pr'] = $idUsuarioFiltro;
        return ' AND (p.created_by = :pc OR p.id_responsable = :pr)';
    }

    private const SELECT_LISTA = "SELECT p.id, p.titulo, p.estado, p.programada_at, p.publicada_at, p.created_at, p.es_contenido_ia,
            ur.nombre AS responsable,
            (SELECT string_agg(CASE c.tipo WHEN 'instagram' THEN 'Instagram' ELSE 'LinkedIn' END || ' @' || COALESCE(c.usuario, c.nombre, ''), ', ' ORDER BY d.id)
               FROM crm_publicaciones_destinos d JOIN crm_canales c ON c.id = d.id_canal
              WHERE d.id_publicacion = p.id AND d.eliminado = false) AS destinos,
            (SELECT m.id FROM crm_publicaciones_medios pm JOIN crm_medios m ON m.id = pm.id_medio AND m.eliminado = false
              WHERE pm.id_publicacion = p.id AND pm.eliminado = false ORDER BY pm.orden, pm.id LIMIT 1) AS id_medio_portada";

    public function listar(int $idEmpresa, array $f, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro): array
    {
        $p = [':id_empresa' => $idEmpresa];
        $where = $this->getBaseWhere($idEmpresa, 'p') . self::condicionPropios($p, $idUsuarioFiltro);
        if (($f['estado'] ?? '') !== '' && preg_match('/^[a-z_]+$/', (string) $f['estado'])) {
            $where .= ' AND p.estado = :est';
            $p[':est'] = $f['estado'];
        }
        $parsed = FiltrosBusqueda::parsear((string) ($f['b'] ?? ''));
        if ($parsed['texto_libre'] !== '') {
            $c = FiltrosBusqueda::condicionTexto(['p.titulo', 'p.texto'], $parsed['texto_libre'], $p, 'pb');
            if ($c !== '') $where .= ' AND ' . $c;
        }
        FiltrosBusqueda::aplicarFiltros($where, $p, $parsed['filtros'], [
            'texto' => ['titulo' => 'p.titulo', 'responsable' => 'ur.nombre'],
            'exacto' => ['estado' => 'p.estado'],
            'fecha' => ['programada' => 'p.programada_at', 'publicada' => 'p.publicada_at', 'creada' => 'p.created_at'],
        ]);
        $from = ' FROM crm_publicaciones p LEFT JOIN usuarios ur ON ur.id = p.id_responsable ';
        $st = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $st->execute($p);
        $total = (int) $st->fetchColumn();
        $orderBy = OrdenListado::clausula(OrdenListado::normalizar($orden), self::MAPA_ORDEN, 'p.created_at', 'p.id DESC');
        $st = $this->db->prepare(self::SELECT_LISTA . " {$from} {$where} {$orderBy} LIMIT " . max(1, min(200, $perPage))
            . ' OFFSET ' . (max(1, $page) - 1) * max(1, min(200, $perPage)));
        $st->execute($p);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** Indicadores de la tarjeta superior (respeta registros propios). */
    public function resumen(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        $p = [':id_empresa' => $idEmpresa];
        $where = $this->getBaseWhere($idEmpresa, 'p') . self::condicionPropios($p, $idUsuarioFiltro);
        $st = $this->db->prepare(
            "SELECT COUNT(*) FILTER (WHERE p.estado = 'programada') AS programadas,
                    COUNT(*) FILTER (WHERE p.estado IN ('borrador', 'en_revision', 'aprobada')) AS borradores,
                    COUNT(*) FILTER (WHERE p.estado = 'en_revision') AS en_revision,
                    COUNT(*) FILTER (WHERE p.estado IN ('publicada', 'parcial') AND p.publicada_at >= date_trunc('month', NOW())) AS publicadas_mes,
                    COUNT(*) FILTER (WHERE p.estado IN ('con_error', 'parcial')) AS con_error
               FROM crm_publicaciones p {$where}"
        );
        $st->execute($p);
        return array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    /** Publicaciones con fecha (programada o publicada) en el rango, para el calendario. */
    public function calendario(int $idEmpresa, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        $p = [':id_empresa' => $idEmpresa, ':d' => $desde, ':h' => $hasta];
        $where = $this->getBaseWhere($idEmpresa, 'p') . self::condicionPropios($p, $idUsuarioFiltro)
            . ' AND COALESCE(p.publicada_at, p.programada_at) >= CAST(:d AS timestamp) AND COALESCE(p.publicada_at, p.programada_at) < CAST(:h AS timestamp)';
        $st = $this->db->prepare(self::SELECT_LISTA . ' FROM crm_publicaciones p LEFT JOIN usuarios ur ON ur.id = p.id_responsable '
            . "{$where} ORDER BY COALESCE(p.publicada_at, p.programada_at), p.id LIMIT 1000");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Cola del publicador (cron) ───────────────────────────────────────────

    /**
     * Toma destinos cuyo turno llegó y los «alquila» 10 minutos (si el proceso
     * muere, vuelven solos a la cola). SKIP LOCKED: dos cron nunca toman el mismo.
     * Solo de empresas activas.
     */
    public function tomarDestinos(int $limite): array
    {
        $st = $this->db->prepare(
            "UPDATE crm_publicaciones_destinos SET siguiente_intento_at = NOW() + INTERVAL '10 minutes'
              WHERE id IN (SELECT d.id FROM crm_publicaciones_destinos d
                             JOIN crm_publicaciones p ON p.id = d.id_publicacion AND p.eliminado = false
                             JOIN empresas em ON em.id = d.id_empresa AND em.estado = '1' AND em.eliminado = false
                            WHERE d.eliminado = false AND d.estado IN ('pendiente', 'preparando', 'publicando')
                              AND p.estado IN ('programada', 'publicando')
                              AND COALESCE(p.programada_at, p.updated_at) <= NOW()
                              AND (d.siguiente_intento_at IS NULL OR d.siguiente_intento_at <= NOW())
                            ORDER BY COALESCE(p.programada_at, p.updated_at), d.id
                            LIMIT " . max(1, min(100, $limite)) . "
                            FOR UPDATE OF d SKIP LOCKED)
              RETURNING id, id_empresa, id_publicacion"
        );
        $st->execute();
        return array_map(fn($r) => array_map('intval', $r), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Destino con su publicación y su cuenta (incluye el token cifrado: solo para el publicador). */
    public function destinoParaPublicar(int $idDestino): ?array
    {
        $st = $this->db->prepare(
            'SELECT d.*, p.texto AS texto_publicacion, p.es_contenido_ia, p.estado AS estado_publicacion, p.titulo,
                    c.tipo AS canal_tipo, c.id_externo AS canal_externo, c.token_cifrado, c.estado AS canal_estado, c.usuario AS canal_usuario
               FROM crm_publicaciones_destinos d
               JOIN crm_publicaciones p ON p.id = d.id_publicacion
               JOIN crm_canales c ON c.id = d.id_canal
              WHERE d.id = :id AND d.eliminado = false'
        );
        $st->execute([':id' => $idDestino]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $r['contenedores'] = json_decode((string) ($r['contenedores'] ?? ''), true) ?: [];
        }
        return $r ?: null;
    }

    /** Actualiza campos del destino (lista cerrada). */
    public function actualizarDestino(int $id, array $c): void
    {
        $permitidos = ['estado', 'intentos', 'siguiente_intento_at', 'contenedor_externo', 'contenedores', 'id_externo', 'enlace', 'error', 'publicada_at'];
        $sets = ['updated_at = NOW()'];
        $p = [':id' => $id];
        foreach ($c as $k => $v) {
            if (!in_array($k, $permitidos, true)) {
                continue;
            }
            if ($k === 'contenedores') {
                $sets[] = 'contenedores = CAST(:contenedores AS jsonb)';
                $p[':contenedores'] = $v === null ? null : json_encode($v);
                continue;
            }
            if ($k === 'error' && $v !== null) {
                $v = mb_substr((string) $v, 0, 500);
            }
            $sets[] = "{$k} = :{$k}";
            $p[':' . $k] = $v;
        }
        $st = $this->db->prepare('UPDATE crm_publicaciones_destinos SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $st->execute($p);
    }

    /** Destinos publicados hoy por la empresa (tope diario propio). */
    public function publicadasHoy(int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM crm_publicaciones_destinos WHERE id_empresa = :e AND estado = 'publicada' AND publicada_at >= CURRENT_DATE");
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /** Propuestas con IA pedidas por la empresa en la última hora (tope de uso). */
    public function redaccionesUltimaHora(int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM log_sistema WHERE id_empresa = :e AND accion = 'REDACTAR_IA' AND created_at >= NOW() - INTERVAL '1 hour'"
        );
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function nombreEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT COALESCE(NULLIF(TRIM(nombre_comercial), ''), nombre) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return trim((string) $st->fetchColumn());
    }

    public function idsMedios(int $idPub): array
    {
        $st = $this->db->prepare('SELECT id_medio FROM crm_publicaciones_medios WHERE id_publicacion = :p AND eliminado = false');
        $st->execute([':p' => $idPub]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
