<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: cola de eventos crudos del webhook de Meta (crm_webhook_eventos).
 * Tabla técnica: al llegar el evento aún no se sabe la empresa, por eso
 * id_empresa se completa al procesar. Diseño: docs/diseno/crm-redes-fase1.md §5.4 y §6.
 */
class CrmWebhookEventoRepository extends BaseRepository
{
    protected string $table = 'crm_webhook_eventos';

    /** Reintentos máximos de un evento con error antes de dejarlo para revisión. */
    public const MAX_INTENTOS = 5;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function disponible(): bool
    {
        return $this->tablaExiste('crm_webhook_eventos');
    }

    /** $appId: app de Meta cuya firma validó el evento. */
    public function insertar(string $objeto, string $payloadJson, bool $firmaOk, ?string $appId = null): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_webhook_eventos (objeto, payload, firma_ok, estado, app_id)
             VALUES (:o, CAST(:p AS jsonb), :f, 'pendiente', :a) RETURNING id"
        );
        $st->execute([':o' => mb_substr($objeto, 0, 40), ':p' => $payloadJson, ':f' => $firmaOk ? 'true' : 'false', ':a' => $appId]);
        return (int) $st->fetchColumn();
    }

    /** Ids por procesar: pendientes y con error que aún tienen reintentos, los más viejos primero. */
    public function idsPendientes(int $limite): array
    {
        $st = $this->db->prepare(
            "SELECT id FROM crm_webhook_eventos
              WHERE estado IN ('pendiente', 'error') AND intentos < :max
              ORDER BY id LIMIT " . max(1, min(1000, $limite))
        );
        $st->execute([':max' => self::MAX_INTENTOS]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Candado de sesión por evento (no transaccional: las llamadas a Instagram
     * del procesamiento ocurren fuera de la transacción). Evita que el webhook y
     * el cron procesen el mismo evento a la vez. Liberar con soltar().
     */
    public function tomar(int $id): bool
    {
        $st = $this->db->prepare("SELECT pg_try_advisory_lock(hashtext('crm_evento:' || CAST(:id AS text)))");
        $st->execute([':id' => $id]);
        return (bool) $st->fetchColumn();
    }

    public function soltar(int $id): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_unlock(hashtext('crm_evento:' || CAST(:id AS text)))");
        $st->execute([':id' => $id]);
    }

    /** El evento, solo si sigue por procesar (otro proceso pudo terminarlo antes). */
    public function getPorProcesar(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, objeto, payload, intentos, app_id FROM crm_webhook_eventos
              WHERE id = :id AND estado IN ('pendiente', 'error') AND intentos < :max"
        );
        $st->execute([':id' => $id, ':max' => self::MAX_INTENTOS]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['payload'] = json_decode((string) $r['payload'], true) ?: [];
        return $r;
    }

    public function marcar(int $id, string $estado, ?int $idEmpresa, ?string $detalle = null): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_webhook_eventos SET estado = :s, id_empresa = COALESCE(:e, id_empresa), error = :d, procesado_at = NOW()
              WHERE id = :id"
        );
        $st->execute([':s' => $estado, ':e' => $idEmpresa, ':d' => $detalle !== null ? mb_substr($detalle, 0, 2000) : null, ':id' => $id]);
    }

    public function marcarError(int $id, string $detalle): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_webhook_eventos SET estado = 'error', intentos = intentos + 1, error = :d WHERE id = :id"
        );
        $st->execute([':d' => mb_substr($detalle, 0, 2000), ':id' => $id]);
    }

    /** Borra físicamente los eventos ya resueltos con más de $dias (es una cola técnica, no un registro de negocio). */
    public function purgar(int $dias): int
    {
        $st = $this->db->prepare(
            "DELETE FROM crm_webhook_eventos
              WHERE estado IN ('procesado', 'ignorado') AND recibido_at < NOW() - make_interval(days => :d)"
        );
        $st->execute([':d' => $dias]);
        return $st->rowCount();
    }
}
