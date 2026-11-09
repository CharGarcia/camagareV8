<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: eliminación de datos pedida por Meta (crm_eliminaciones_datos) y el
 * borrado de lo que el sistema guardó de Instagram para un id de Meta.
 *
 * Las filas se conservan (eliminación lógica, CLAUDE.md §5), pero el CONTENIDO
 * que vino de Meta se vacía: textos, payloads, adjuntos, nombres y usuarios.
 * Es la única forma de cumplir la eliminación de datos que exige Meta.
 */
class CrmPrivacidadRepository extends BaseRepository
{
    public const NOMBRE_ANONIMO = 'Contacto de Instagram (datos eliminados)';

    public function __construct()
    {
        parent::__construct('crm_eliminaciones_datos');
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_eliminaciones_datos');
    }

    public function registrar(string $codigo, string $tipo, string $idExterno): int
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_eliminaciones_datos (codigo, tipo, id_externo) VALUES (:c, :t, :x) RETURNING id'
        );
        $st->execute([':c' => $codigo, ':t' => $tipo, ':x' => mb_substr($idExterno, 0, 64)]);
        return (int) $st->fetchColumn();
    }

    public function finalizar(int $id, string $estado, array $detalle): void
    {
        $st = $this->db->prepare(
            'UPDATE crm_eliminaciones_datos SET estado = :s, detalle = CAST(:d AS jsonb), completado_at = NOW() WHERE id = :id'
        );
        $st->execute([':s' => $estado, ':d' => json_encode($detalle), ':id' => $id]);
    }

    public function getPorCodigo(string $codigo): ?array
    {
        $st = $this->db->prepare(
            'SELECT codigo, tipo, estado, detalle, recibido_at, completado_at FROM crm_eliminaciones_datos WHERE codigo = :c'
        );
        $st->execute([':c' => $codigo]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $r['detalle'] = json_decode((string) ($r['detalle'] ?? '{}'), true) ?: [];
        }
        return $r ?: null;
    }

    // ── Qué hay de ese id ───────────────────────────────────────────────────
    // $empresas: empresas que usan la app de Meta que firmó la solicitud. Todo se
    // acota a ellas (una app no borra datos de otra empresa). null = sin acotar (pruebas).

    private static function enEmpresas(?array $empresas, string $col, array &$p): string
    {
        if ($empresas === null) {
            return '';
        }
        $p[':emp'] = self::arr($empresas ?: [0]);
        return " AND {$col} = ANY (CAST(:emp AS int[]))";
    }

    /** Empresas donde aparece ese id (cuenta conectada, persona de una conversación o contacto). */
    public function empresasDeId(string $idExterno): array
    {
        $st = $this->db->prepare(
            "SELECT id_empresa FROM crm_canales WHERE tipo = 'instagram' AND id_externo = :x1
             UNION SELECT id_empresa FROM crm_conversaciones WHERE id_hilo_externo = :x2
             UNION SELECT id_empresa FROM crm_contactos WHERE instagram_id = :x3"
        );
        $st->execute([':x1' => $idExterno, ':x2' => $idExterno, ':x3' => $idExterno]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Canales de esa cuenta (también los ya liberados) en las empresas indicadas. */
    public function canalesPorExterno(string $idExterno, ?array $empresas = null): array
    {
        $p = [':x' => $idExterno];
        $st = $this->db->prepare("SELECT id, id_empresa FROM crm_canales WHERE tipo = 'instagram' AND id_externo = :x" . self::enEmpresas($empresas, 'id_empresa', $p));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{id:int,id_empresa:int}> */
    public function conversacionesDeCanales(array $idsCanal): array
    {
        if (!$idsCanal) {
            return [];
        }
        $st = $this->db->prepare('SELECT id, id_empresa FROM crm_conversaciones WHERE id_canal = ANY (CAST(:ids AS int[]))');
        $st->execute([':ids' => self::arr($idsCanal)]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conversaciones de mensaje directo con esa persona (IGSID). */
    public function conversacionesDePersona(string $igsid, ?array $empresas = null): array
    {
        $p = [':x' => $igsid];
        $st = $this->db->prepare("SELECT id, id_empresa FROM crm_conversaciones WHERE tipo = 'dm' AND id_hilo_externo = :x" . self::enEmpresas($empresas, 'id_empresa', $p));
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Rutas de adjuntos guardados de esas conversaciones y de los comentarios de la persona. */
    public function adjuntos(array $idsConv, string $idPersona, ?array $empresas = null): array
    {
        $p = [':ids' => self::arr($idsConv), ':x' => $idPersona];
        $st = $this->db->prepare(
            "SELECT adjunto_ruta FROM crm_mensajes
              WHERE adjunto_ruta IS NOT NULL
                AND (id_conversacion = ANY (CAST(:ids AS int[])) OR (contenido->'from'->>'id' = :x" . self::enEmpresas($empresas, 'id_empresa', $p) . '))'
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    // ── Borrado ─────────────────────────────────────────────────────────────

    private const VACIAR_MENSAJE = "texto = NULL, contenido = '{}'::jsonb, adjunto_ruta = NULL, error = NULL,
        eliminado = true, deleted_at = COALESCE(deleted_at, NOW()), updated_at = NOW()";

    public function vaciarMensajesDeConversaciones(array $idsConv): int
    {
        if (!$idsConv) {
            return 0;
        }
        $st = $this->db->prepare('UPDATE crm_mensajes SET ' . self::VACIAR_MENSAJE . ' WHERE id_conversacion = ANY (CAST(:ids AS int[]))');
        $st->execute([':ids' => self::arr($idsConv)]);
        return $st->rowCount();
    }

    /** Comentarios que esa persona dejó en hilos de otras conversaciones. */
    public function vaciarComentariosDePersona(string $idPersona, ?array $empresas = null): int
    {
        $p = [':x' => $idPersona];
        $st = $this->db->prepare('UPDATE crm_mensajes SET ' . self::VACIAR_MENSAJE . " WHERE contenido->'from'->>'id' = :x" . self::enEmpresas($empresas, 'id_empresa', $p));
        $st->execute($p);
        return $st->rowCount();
    }

    public function vaciarConversaciones(array $idsConv): int
    {
        if (!$idsConv) {
            return 0;
        }
        $st = $this->db->prepare(
            "UPDATE crm_conversaciones SET ultimo_mensaje = NULL, sin_leer = 0, estado = 'cerrada',
                    eliminado = true, deleted_at = COALESCE(deleted_at, NOW()), updated_at = NOW()
              WHERE id = ANY (CAST(:ids AS int[]))"
        );
        $st->execute([':ids' => self::arr($idsConv)]);
        return $st->rowCount();
    }

    /**
     * Quita de los contactos lo que vino de Instagram: id y usuario siempre; el
     * nombre solo si lo creó el sistema (webhook), porque entonces también salió
     * del perfil de Instagram. Lo que la empresa escribió a mano se respeta.
     */
    public function anonimizarContactos(array $idsConv, string $igsid, ?array $empresas = null): int
    {
        $p = [':anon' => self::NOMBRE_ANONIMO, ':x' => $igsid, ':ids' => self::arr($idsConv)];
        $st = $this->db->prepare(
            "UPDATE crm_contactos c SET
                instagram_id = NULL, instagram_usuario = NULL,
                nombre = CASE WHEN c.created_by IS NULL THEN :anon ELSE c.nombre END,
                updated_at = NOW()
              WHERE ((c.instagram_id = :x" . self::enEmpresas($empresas, 'c.id_empresa', $p) . ")
                 OR (c.created_by IS NULL AND c.id IN (SELECT id_contacto FROM crm_conversaciones WHERE id = ANY (CAST(:ids AS int[])))))"
        );
        $st->execute($p);
        return $st->rowCount();
    }

    /** Desconecta la cuenta y borra su acceso; con $vaciarPerfil también su @usuario y nombre. */
    public function desconectarCanales(array $idsCanal, bool $vaciarPerfil): int
    {
        if (!$idsCanal) {
            return 0;
        }
        $extra = $vaciarPerfil ? ", usuario = NULL, nombre = NULL, permisos = '[]'::jsonb" : '';
        $st = $this->db->prepare(
            "UPDATE crm_canales SET estado = 'desconectado', token_cifrado = NULL, token_expira_at = NULL{$extra}, updated_at = NOW()
              WHERE id = ANY (CAST(:ids AS int[]))"
        );
        $st->execute([':ids' => self::arr($idsCanal)]);
        return $st->rowCount();
    }

    /** Vacía los eventos crudos del webhook que mencionan ese id. */
    /** Vacía los eventos crudos del webhook que mencionan ese id y que firmó esa app. */
    public function vaciarEventos(string $idExterno, ?string $appId = null): int
    {
        $p = [':x' => '%"' . str_replace(['%', '_'], ['\\%', '\\_'], $idExterno) . '"%'];
        $filtroApp = '';
        if ($appId !== null) {
            $filtroApp = ' AND app_id = :app';
            $p[':app'] = $appId;
        }
        $st = $this->db->prepare("UPDATE crm_webhook_eventos SET payload = '{}'::jsonb WHERE payload::text LIKE :x" . $filtroApp);
        $st->execute($p);
        return $st->rowCount();
    }

    private static function arr(array $ids): string
    {
        return '{' . implode(',', array_map('intval', $ids)) . '}';
    }
}
