<?php
declare(strict_types=1);

namespace App\repositories;

/**
 * NuveiTarjetaRepository
 * Acceso a datos de nuvei_tarjetas_cliente (tarjetas tokenizadas guardadas por
 * cliente, reutilizables entre suscripciones/documentos) y nuvei_solicitudes_tarjeta
 * (enlaces de registro enviados al cliente). Global — mismo patrón que NuveiRepository.
 */
class NuveiTarjetaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('nuvei_tarjetas_cliente');
    }

    // ─── TARJETAS ─────────────────────────────────────────────────────────────

    public function crearTarjeta(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO nuvei_tarjetas_cliente
                (id_empresa, id_cliente, nuvei_user_id, token, bin, ultimos4, marca,
                 holder_name, expiry_month, expiry_year, status, created_by)
             VALUES
                (:ie, :icl, :uid, :token, :bin, :u4, :marca,
                 :holder, :em, :ey, :status, :cb)
             RETURNING id"
        );
        $st->execute([
            ':ie'     => $d['id_empresa'],
            ':icl'    => $d['id_cliente'],
            ':uid'    => $d['nuvei_user_id'],
            ':token'  => $d['token'],
            ':bin'    => $d['bin']          ?? null,
            ':u4'     => $d['ultimos4']     ?? null,
            ':marca'  => $d['marca']        ?? null,
            ':holder' => $d['holder_name']  ?? null,
            ':em'     => $d['expiry_month'] ?? null,
            ':ey'     => $d['expiry_year']  ?? null,
            ':status' => $d['status']       ?? 'valid',
            ':cb'     => $d['id_usuario']   ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function getTarjetasCliente(int $idEmpresa, int $idCliente): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_tarjetas_cliente
             WHERE id_empresa = :ie AND id_cliente = :icl AND eliminado = false
             ORDER BY predeterminada DESC, created_at DESC"
        );
        $st->execute([':ie' => $idEmpresa, ':icl' => $idCliente]);
        return $st->fetchAll();
    }

    public function getTarjetaPorId(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_tarjetas_cliente WHERE id = :id AND eliminado = false LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function eliminarTarjeta(int $id, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE nuvei_tarjetas_cliente
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :ub
             WHERE id = :id"
        )->execute([':ub' => $idUsuario, ':id' => $id]);
    }

    // ─── SOLICITUDES DE REGISTRO ────────────────────────────────────────────────

    public function crearSolicitud(array $d): void
    {
        $st = $this->db->prepare(
            "INSERT INTO nuvei_solicitudes_tarjeta
                (id_empresa, id_cliente, modulo, id_referencia, token, email, created_by)
             VALUES
                (:ie, :icl, :mod, :ref, :token, :email, :cb)"
        );
        $st->execute([
            ':ie'    => $d['id_empresa'],
            ':icl'   => $d['id_cliente'],
            ':mod'   => $d['modulo']        ?? 'general',
            ':ref'   => $d['id_referencia'] ?? null,
            ':token' => $d['token'],
            ':email' => $d['email']         ?? null,
            ':cb'    => $d['id_usuario']    ?? null,
        ]);
    }

    public function getSolicitudPorToken(string $token): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM nuvei_solicitudes_tarjeta
             WHERE token = :token AND eliminado = false LIMIT 1"
        );
        $st->execute([':token' => $token]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function marcarSolicitudCompletada(string $token, int $idNuveiTarjeta): void
    {
        $this->db->prepare(
            "UPDATE nuvei_solicitudes_tarjeta
             SET estado = 'completado', id_nuvei_tarjeta = :itc, updated_at = CURRENT_TIMESTAMP
             WHERE token = :token"
        )->execute([':itc' => $idNuveiTarjeta, ':token' => $token]);
    }
}
