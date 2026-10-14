<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Pagos de suscripción reportados por el cliente desde el modal de vigencia
 * (tabla suscripciones_reportes_pago, database/20261013_suscripciones_reportes_pago.sql).
 *
 * Dos lados: la empresa que reporta (id_empresa) y la controladora que revisa
 * (id_empresa_controladora). Cada consulta filtra por la empresa del lado que pregunta.
 */
class SuscripcionReportePagoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('suscripciones_reportes_pago');
    }

    /** false mientras el SQL no se haya aplicado: el modal funciona como antes. */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    public function insertar(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO suscripciones_reportes_pago
                (id_empresa, id_empresa_controladora, id_suscripcion, fecha_limite, fecha_periodo,
                 documento, saldo, mensaje, archivo_nombre, archivo_ruta, archivo_mime, archivo_tamano,
                 estado, created_at, created_by)
             VALUES
                (:id_empresa, :id_ctrl, :id_susc, :fecha_limite, :fecha_periodo,
                 :documento, :saldo, :mensaje, :archivo_nombre, :archivo_ruta, :archivo_mime, :archivo_tamano,
                 'en_revision', NOW(), :created_by)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'     => $d['id_empresa'],
            ':id_ctrl'        => $d['id_empresa_controladora'] ?: null,
            ':id_susc'        => $d['id_suscripcion'] ?: null,
            ':fecha_limite'   => $d['fecha_limite'],
            ':fecha_periodo'  => $d['fecha_periodo'] ?: null,
            ':documento'      => $d['documento'] ?: null,
            ':saldo'          => $d['saldo'],
            ':mensaje'        => $d['mensaje'] !== '' ? $d['mensaje'] : null,
            ':archivo_nombre' => $d['archivo_nombre'] ?: null,
            ':archivo_ruta'   => $d['archivo_ruta'] ?: null,
            ':archivo_mime'   => $d['archivo_mime'] ?: null,
            ':archivo_tamano' => $d['archivo_tamano'] ?: null,
            ':created_by'     => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function marcarCorreo(int $id, bool $enviado, ?string $error): void
    {
        $st = $this->db->prepare(
            "UPDATE suscripciones_reportes_pago
                SET correo_enviado = CAST(:env AS BOOLEAN), correo_error = :err
              WHERE id = :id"
        );
        $st->execute([':env' => $enviado ? 'true' : 'false', ':err' => $error, ':id' => $id]);
    }

    /**
     * Último reporte de la empresa para esa fecha límite (cualquier estado), con el
     * nombre de quien lo envió y si sigue dentro del tope de revisión (`dentro_tope`,
     * calculado en la BD para no depender de la zona horaria de PHP). null si no hay.
     */
    public function getUltimoPorFecha(int $idEmpresa, string $fechaLimite, int $diasTope): ?array
    {
        $st = $this->db->prepare(
            "SELECT r.id, r.estado, r.mensaje, r.archivo_nombre, r.motivo_rechazo,
                    r.created_at, r.revisado_at, u.nombre AS usuario,
                    (r.created_at >= NOW() - (CAST(:dias AS INTEGER) * INTERVAL '1 day')) AS dentro_tope
               FROM suscripciones_reportes_pago r
               LEFT JOIN usuarios u ON u.id = r.created_by
              WHERE r.id_empresa = :id_empresa
                AND r.eliminado = false
                AND r.fecha_limite = CAST(:fecha AS DATE)
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':fecha' => $fechaLimite, ':dias' => $diasTope]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Reportes recibidos por la controladora en los últimos $dias días, más recientes primero. */
    public function listarParaControladora(int $idControladora, int $dias, int $diasTope): array
    {
        $st = $this->db->prepare(
            "SELECT r.id, r.id_empresa, r.fecha_limite, r.fecha_periodo, r.documento, r.saldo,
                    r.mensaje, r.archivo_nombre, r.estado, r.motivo_rechazo, r.created_at,
                    r.revisado_at, r.correo_enviado,
                    (r.created_at >= NOW() - (CAST(:tope AS INTEGER) * INTERVAL '1 day')) AS dentro_tope,
                    e.nombre AS empresa, e.ruc,
                    u.nombre AS usuario, u.mail AS usuario_mail,
                    ur.nombre AS revisado_por
               FROM suscripciones_reportes_pago r
               JOIN empresas e ON e.id = r.id_empresa
               LEFT JOIN usuarios u  ON u.id  = r.created_by
               LEFT JOIN usuarios ur ON ur.id = r.revisado_by
              WHERE r.id_empresa_controladora = :ctrl
                AND r.eliminado = false
                AND r.created_at >= NOW() - (CAST(:dias AS INTEGER) * INTERVAL '1 day')
              ORDER BY r.created_at DESC, r.id DESC"
        );
        $st->execute([':ctrl' => $idControladora, ':dias' => $dias, ':tope' => $diasTope]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Reportes en revisión vigentes (dentro del tope) que recibió la controladora. */
    public function contarEnRevision(int $idControladora, int $diasTope): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*)
               FROM suscripciones_reportes_pago
              WHERE id_empresa_controladora = :ctrl
                AND eliminado = false
                AND estado = 'en_revision'
                AND created_at >= NOW() - (CAST(:dias AS INTEGER) * INTERVAL '1 day')"
        );
        $st->execute([':ctrl' => $idControladora, ':dias' => $diasTope]);
        return (int) $st->fetchColumn();
    }

    /** Un reporte, solo si lo recibió esta controladora. */
    public function getParaControladora(int $id, int $idControladora): ?array
    {
        $st = $this->db->prepare(
            "SELECT r.*, e.nombre AS empresa, e.ruc, e.mail AS empresa_mail,
                    u.nombre AS usuario, u.mail AS usuario_mail
               FROM suscripciones_reportes_pago r
               JOIN empresas e ON e.id = r.id_empresa
               LEFT JOIN usuarios u ON u.id = r.created_by
              WHERE r.id = :id
                AND r.id_empresa_controladora = :ctrl
                AND r.eliminado = false"
        );
        $st->execute([':id' => $id, ':ctrl' => $idControladora]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Pasa a rechazado solo si sigue en revisión. true si cambió. */
    public function rechazar(int $id, int $idControladora, string $motivo, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE suscripciones_reportes_pago
                SET estado = 'rechazado', motivo_rechazo = :motivo,
                    revisado_by = :u, revisado_at = NOW(),
                    updated_by = :u2, updated_at = NOW()
              WHERE id = :id
                AND id_empresa_controladora = :ctrl
                AND estado = 'en_revision'
                AND eliminado = false"
        );
        $st->execute([':motivo' => $motivo, ':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':ctrl' => $idControladora]);
        return $st->rowCount() > 0;
    }

    /** Nombre y correo(s) de una empresa (sin filtrar por la sesión: es la controladora). */
    public function getContactoEmpresa(int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, ruc, mail FROM empresas WHERE id = :id AND eliminado = false");
        $st->execute([':id' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getUsuario(int $idUsuario): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, mail FROM usuarios WHERE id = :id");
        $st->execute([':id' => $idUsuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
