<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

/**
 * CRM fase 2: asistente virtual. Configuración (crm_bot_config), estado y cola
 * del bot en cada conversación (columnas bot_* de crm_conversaciones),
 * registro de consumo (crm_bot_ejecuciones) y las consultas de sus herramientas.
 * Diseño: docs/diseno/crm-redes-fase2.md.
 */
class CrmBotRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('crm_bot_config');
    }

    /** El SQL de la fase 2 está aplicado. Sin él, nada del bot hace efecto. */
    public function disponible(): bool
    {
        static $hay = null;
        return $hay ??= $this->tablaExiste('crm_bot_config') && $this->tablaExiste('crm_bot_ejecuciones');
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function configPorCanal(int $idCanal, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM crm_bot_config WHERE id_canal = :c AND id_empresa = :e AND eliminado = false'
        );
        $st->execute([':c' => $idCanal, ':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['horario_atencion'] = json_decode((string) ($r['horario_atencion'] ?? '{}'), true) ?: [];
        foreach (['activo', 'mostrar_stock', 'crear_oportunidades'] as $b) {
            $r[$b] = in_array($r[$b], [true, 't', 'true', 1, '1'], true);
        }
        return $r;
    }

    /** Candado para crear/actualizar la configuración de una cuenta (dos guardados a la vez). */
    public function lockConfig(int $idCanal): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_bot_config:' || CAST(:c AS text)))");
        $st->execute([':c' => $idCanal]);
    }

    /** Crea o actualiza la configuración de la cuenta. Llamar bajo lockConfig(). */
    public function guardarConfig(int $idCanal, int $idEmpresa, array $d, int $idUsuario): int
    {
        $cols = ['activo', 'nombre_asistente', 'presentacion', 'tono', 'horario', 'horario_atencion', 'instrucciones',
                 'temas_prohibidos', 'mostrar_stock', 'crear_oportunidades', 'max_respuestas_conv', 'max_respuestas_dia'];
        $p = [];
        foreach ($cols as $c) {
            $v = $d[$c] ?? null;
            $p[':' . $c] = is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode((object) $v) : $v);
        }
        $existe = $this->configPorCanal($idCanal, $idEmpresa);
        if ($existe) {
            $sets = implode(', ', array_map(fn($c) => $c === 'horario_atencion' ? "{$c} = CAST(:{$c} AS jsonb)" : "{$c} = :{$c}", $cols));
            $st = $this->db->prepare("UPDATE crm_bot_config SET {$sets}, updated_at = NOW(), updated_by = :u
                                       WHERE id = :id AND id_empresa = :e");
            $st->execute($p + [':u' => $idUsuario, ':id' => (int) $existe['id'], ':e' => $idEmpresa]);
            return (int) $existe['id'];
        }
        $vals = implode(', ', array_map(fn($c) => $c === 'horario_atencion' ? "CAST(:{$c} AS jsonb)" : ":{$c}", $cols));
        $st = $this->db->prepare('INSERT INTO crm_bot_config (id_empresa, id_canal, ' . implode(', ', $cols) . ', created_by, updated_by)
                                  VALUES (:e, :c, ' . $vals . ', :u, :u2) RETURNING id');
        $st->execute($p + [':e' => $idEmpresa, ':c' => $idCanal, ':u' => $idUsuario, ':u2' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    /** Totales del mes en curso para la pantalla de configuración. */
    public function consumoMes(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FILTER (WHERE resultado = 'enviado')  AS respuestas,
                    COUNT(*) FILTER (WHERE resultado = 'traspaso') AS traspasos,
                    COUNT(*) FILTER (WHERE resultado = 'error')    AS errores,
                    COUNT(*) FILTER (WHERE resultado = 'prueba')   AS pruebas,
                    COALESCE(SUM(tokens_entrada), 0) AS tokens_entrada,
                    COALESCE(SUM(tokens_salida), 0)  AS tokens_salida,
                    COALESCE(SUM(costo_estimado), 0) AS costo
               FROM crm_bot_ejecuciones
              WHERE id_empresa = :e AND created_at >= date_trunc('month', NOW()) AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($v) => is_numeric($v) ? $v + 0 : $v, $r);
    }

    /** Pruebas de la empresa en la última hora (tope, para no gastar la clave sin querer). */
    public function pruebasUltimaHora(int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM crm_bot_ejecuciones WHERE id_empresa = :e AND resultado = 'prueba' AND created_at >= NOW() - INTERVAL '1 hour'"
        );
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    // ── Estado y cola en la conversación ─────────────────────────────────────

    /**
     * Mensaje directo nuevo del cliente: la conversación queda en cola para el
     * bot dentro de $segundos (para juntar varios mensajes seguidos), solo si
     * el bot está activo en ella y en la cuenta. Devuelve si quedó en cola.
     */
    public function encolar(int $idConv, int $idEmpresa, int $segundos): bool
    {
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones cv
                SET bot_pendiente_at = NOW() + make_interval(secs => :s)
              WHERE cv.id = :id AND cv.id_empresa = :e AND cv.tipo = 'dm' AND cv.eliminado = false
                AND cv.bot_estado = 'activo'
                AND EXISTS (SELECT 1 FROM crm_bot_config b
                             WHERE b.id_canal = cv.id_canal AND b.id_empresa = cv.id_empresa
                               AND b.activo = true AND b.eliminado = false)"
        );
        $st->execute([':s' => $segundos, ':id' => $idConv, ':e' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    /**
     * Toma (y saca de la cola) las conversaciones cuyo turno llegó. SKIP LOCKED:
     * dos cron simultáneos nunca toman la misma. Solo de empresas activas.
     * @return list<array{id:int, id_empresa:int, id_canal:int}>
     */
    public function tomarPendientes(int $limite): array
    {
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET bot_pendiente_at = NULL
              WHERE id IN (SELECT cv.id FROM crm_conversaciones cv
                             JOIN empresas em ON em.id = cv.id_empresa AND em.estado = '1' AND em.eliminado = false
                            WHERE cv.bot_pendiente_at IS NOT NULL AND cv.bot_pendiente_at <= NOW() AND cv.eliminado = false
                            ORDER BY cv.bot_pendiente_at
                            LIMIT " . max(1, min(200, $limite)) . "
                            FOR UPDATE OF cv SKIP LOCKED)
              RETURNING id, id_empresa, id_canal"
        );
        $st->execute();
        return array_map(fn($r) => array_map('intval', $r), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Fallo del proveedor: vuelve a la cola más tarde y cuenta el intento. */
    public function reencolar(int $idConv, int $segundos): int
    {
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET bot_pendiente_at = NOW() + make_interval(secs => :s), bot_intentos = bot_intentos + 1
              WHERE id = :id AND bot_estado = 'activo' RETURNING bot_intentos"
        );
        $st->execute([':s' => $segundos, ':id' => $idConv]);
        return (int) $st->fetchColumn();
    }

    /** Una persona respondió (Bandeja o app de Instagram): el bot se calla en esta conversación. */
    public function pausarPorPersona(int $idConv, int $idEmpresa): bool
    {
        if (!$this->disponible()) {
            return false;
        }
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET bot_estado = 'pausado', bot_pausado = true, bot_pendiente_at = NULL, updated_at = NOW()
              WHERE id = :id AND id_empresa = :e AND bot_estado = 'activo'"
        );
        $st->execute([':id' => $idConv, ':e' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    /** Pausa o reactiva a mano (Bandeja). Reactivar reinicia los contadores. */
    public function cambiarEstado(int $idConv, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $reinicio = $estado === 'activo' ? ', bot_respuestas = 0, bot_intentos = 0, bot_motivo_traspaso = NULL' : ', bot_pendiente_at = NULL';
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET bot_estado = :s, bot_pausado = :p{$reinicio}, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e"
        );
        $st->execute([':s' => $estado, ':p' => $estado === 'activo' ? 'false' : 'true', ':u' => $idUsuario, ':id' => $idConv, ':e' => $idEmpresa]);
    }

    /** El bot respondió: +1 respuesta, sin fallos pendientes y nada sin leer (ya la atendió). */
    public function respuestaEnviada(int $idConv): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_conversaciones SET bot_respuestas = bot_respuestas + 1, bot_intentos = 0, sin_leer = 0, updated_at = NOW() WHERE id = :id'
        );
        $st->execute([':id' => $idConv]);
    }

    /**
     * Pasa la conversación a una persona: el bot deja de responder, queda el
     * motivo, se asigna (si no tenía responsable) y vuelve a contar como «sin
     * leer» para que suene el aviso del navbar.
     */
    public function traspasar(int $idConv, int $idEmpresa, string $motivo, ?int $responsable): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET bot_estado = 'traspasada', bot_pausado = true, bot_pendiente_at = NULL,
                    bot_motivo_traspaso = :m, id_responsable = COALESCE(id_responsable, :r),
                    sin_leer = GREATEST(sin_leer, 1), estado = 'abierta', updated_at = NOW()
              WHERE id = :id AND id_empresa = :e"
        );
        $st->bindValue(':m', mb_substr($motivo, 0, 300));
        $st->bindValue(':r', $responsable, $responsable === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':id', $idConv, PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->execute();
    }

    /** Fila de la conversación con lo que el bot necesita, bajo FOR UPDATE. */
    public function bloquearConversacion(int $idConv, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT cv.*, ca.config AS canal_config, ca.estado AS canal_estado, ca.usuario AS canal_usuario
               FROM crm_conversaciones cv JOIN crm_canales ca ON ca.id = cv.id_canal
              WHERE cv.id = :id AND cv.id_empresa = :e AND cv.eliminado = false
              FOR UPDATE OF cv"
        );
        $st->execute([':id' => $idConv, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Últimos mensajes de la conversación en ORDEN DE LLEGADA (id), no por
     * fecha: la del cliente viene de Meta en segundos y la del bot es la del
     * servidor; si el cliente escribe en el mismo segundo de la respuesta, por
     * fecha su mensaje quedaría antes y nunca se contestaría.
     */
    public function historial(int $idConv, int $limite = 30): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM (
                SELECT id, direccion, autor, tipo, texto, adjunto_ruta, estado_envio, fecha
                  FROM crm_mensajes WHERE id_conversacion = :c AND eliminado = false
                 ORDER BY id DESC LIMIT " . max(1, min(100, $limite)) . "
             ) x ORDER BY id"
        );
        $st->execute([':c' => $idConv]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Nombre con que el asistente presenta a la empresa (comercial, si lo hay). */
    public function nombreEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT COALESCE(NULLIF(TRIM(nombre_comercial), ''), nombre) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return trim((string) $st->fetchColumn());
    }

    // ── Consumo ──────────────────────────────────────────────────────────────

    public function respuestasHoy(int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM crm_bot_ejecuciones
              WHERE id_empresa = :e AND created_at >= CURRENT_DATE AND resultado IN ('enviado', 'traspaso')"
        );
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function registrarEjecucion(array $d): int
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_bot_ejecuciones (id_empresa, id_conversacion, proveedor, modelo, tokens_entrada, tokens_salida,
                                              tokens_cache, costo_estimado, herramientas, resultado, detalle, duracion_ms, created_by)
             VALUES (:e, :c, :p, :m, :ti, :to, :tc, :co, CAST(:h AS jsonb), :r, :d, :ms, :u) RETURNING id'
        );
        $st->bindValue(':e', (int) $d['id_empresa'], PDO::PARAM_INT);
        $st->bindValue(':c', $d['id_conversacion'] ?? null, isset($d['id_conversacion']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':p', mb_substr((string) ($d['proveedor'] ?? ''), 0, 30));
        $st->bindValue(':m', mb_substr((string) ($d['modelo'] ?? ''), 0, 60));
        $st->bindValue(':ti', (int) ($d['tokens_entrada'] ?? 0), PDO::PARAM_INT);
        $st->bindValue(':to', (int) ($d['tokens_salida'] ?? 0), PDO::PARAM_INT);
        $st->bindValue(':tc', (int) ($d['tokens_cache'] ?? 0), PDO::PARAM_INT);
        $st->bindValue(':co', (string) round((float) ($d['costo_estimado'] ?? 0), 6));
        $st->bindValue(':h', json_encode(array_values($d['herramientas'] ?? [])));
        $st->bindValue(':r', (string) $d['resultado']);
        $st->bindValue(':d', isset($d['detalle']) ? mb_substr((string) $d['detalle'], 0, 500) : null);
        $st->bindValue(':ms', isset($d['duracion_ms']) ? (int) $d['duracion_ms'] : null, isset($d['duracion_ms']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':u', $d['created_by'] ?? null, isset($d['created_by']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    // ── Herramientas ─────────────────────────────────────────────────────────

    /**
     * Productos activos de la empresa que coinciden con el texto: nombre,
     * precio de venta con IVA y unidades en bodega (el servicio decide qué se
     * dice: sí/no, nunca la cantidad).
     */
    public function buscarProductos(int $idEmpresa, string $texto, int $limite = 5): array
    {
        $p = [':e' => $idEmpresa];
        $cond = FiltrosBusqueda::condicionTexto(['p.nombre', 'p.codigo'], $texto, $p, 'bq');
        if ($cond === '') {
            return [];
        }
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.precio_base, COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva,
                    COALESCE(p.inventariable, false) AS inventariable,
                    (SELECT COALESCE(SUM(pb.stock_actual), 0) FROM productos_bodegas pb
                      WHERE pb.id_producto = p.id AND pb.id_empresa = p.id_empresa AND pb.eliminado = false) AS stock
               FROM productos p
               LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
              WHERE p.id_empresa = :e AND p.eliminado = false AND COALESCE(p.status, 1) = 1 AND {$cond}
              ORDER BY p.nombre, p.id
              LIMIT " . max(1, min(10, $limite))
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Oportunidad ya creada desde esta conversación (manual o por el bot). */
    public function oportunidadDeConversacion(int $idConv, int $idEmpresa): ?int
    {
        $st = $this->db->prepare(
            'SELECT id FROM crm_oportunidades WHERE id_conversacion = :c AND id_empresa = :e AND eliminado = false ORDER BY id LIMIT 1'
        );
        $st->execute([':c' => $idConv, ':e' => $idEmpresa]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * Datos que dio el cliente en el chat. Solo toca los campos indicados; el
     * nombre únicamente si el contacto lo creó el sistema (no pisa lo que una
     * persona escribió a mano).
     */
    public function completarContacto(int $idContacto, int $idEmpresa, array $campos): array
    {
        $antes = $this->db->prepare('SELECT nombre, telefono, correo, created_by FROM crm_contactos WHERE id = :id AND id_empresa = :e AND eliminado = false');
        $antes->execute([':id' => $idContacto, ':e' => $idEmpresa]);
        $a = $antes->fetch(PDO::FETCH_ASSOC);
        if (!$a) {
            return [];
        }
        $sets = [];
        $despues = [];
        $p = [':id' => $idContacto, ':e' => $idEmpresa];
        foreach (['telefono', 'correo', 'nombre'] as $c) {
            if (!isset($campos[$c]) || $campos[$c] === '') {
                continue;
            }
            if ($c === 'nombre' && $a['created_by'] !== null) {
                continue;
            }
            $sets[] = "{$c} = :{$c}";
            $p[':' . $c] = $despues[$c] = $this->caparTexto($c, (string) $campos[$c], 'crm_contactos');
        }
        if (!$sets) {
            return [];
        }
        $st = $this->db->prepare('UPDATE crm_contactos SET ' . implode(', ', $sets) . ', updated_at = NOW()
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false');
        $st->execute($p);
        return ['antes' => array_intersect_key($a, $despues), 'despues' => $despues];
    }
}
