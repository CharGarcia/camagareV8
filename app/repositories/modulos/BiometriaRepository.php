<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Credencial personal (QR token) y biometría facial del empleado.
 */
class BiometriaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('empleados_biometria');
    }

    /** Biometría vigente de un empleado. */
    public function getByEmpleado(int $idEmpleado, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Resuelve al empleado a partir de su token personal (global, no filtra por empresa).
     * Devuelve datos del empleado necesarios para marcar.
     */
    public function getByQrToken(string $qrToken): ?array
    {
        $sql = "SELECT b.*, e.nombres_apellidos, e.identificacion, e.id_usuario_sistema, e.estado AS empleado_estado
                FROM {$this->table} b
                JOIN empleados e ON e.id = b.id_empleado
                WHERE b.qr_token = :t AND b.eliminado = false AND b.activo = true
                  AND e.eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':t' => $qrToken]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeQrToken(string $qrToken): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE qr_token = :t LIMIT 1");
        $st->execute([':t' => $qrToken]);
        return (bool) $st->fetchColumn();
    }

    /** Mapa [id_empleado => qr_token] de credenciales vigentes de la empresa. */
    public function getTokensPorEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id_empleado, qr_token FROM {$this->table}
                                  WHERE id_empresa = :e AND eliminado = false AND activo = true");
        $st->execute([':e' => $idEmpresa]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_empleado']] = $r['qr_token'];
        }
        return $map;
    }

    /** Set [id_empleado => true] de empleados con rostro enrolado (descriptor_facial no nulo). */
    public function getEmpleadosConRostro(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id_empleado FROM {$this->table}
                                  WHERE id_empresa = :e AND eliminado = false AND descriptor_facial IS NOT NULL");
        $st->execute([':e' => $idEmpresa]);
        $set = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $set[(int) $id] = true;
        }
        return $set;
    }

    /** Descriptor facial de un empleado por su token personal (para reconocimiento público). */
    public function getDescriptorByQrToken(string $qrToken): ?array
    {
        $st = $this->db->prepare("SELECT descriptor_facial FROM {$this->table}
                                  WHERE qr_token = :t AND eliminado = false AND activo = true LIMIT 1");
        $st->execute([':t' => $qrToken]);
        $val = $st->fetchColumn();
        if (!$val) return null;
        $arr = json_decode((string) $val, true);
        return is_array($arr) ? $arr : null;
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_empleado, qr_token, dispositivo_id, consentimiento_at,
                    descriptor_facial, activo, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :qr_token, :dispositivo_id, :consentimiento_at,
                    :descriptor_facial, :activo, :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $d['id_empresa'],
            ':id_empleado'      => $d['id_empleado'],
            ':qr_token'         => $d['qr_token'],
            ':dispositivo_id'   => $d['dispositivo_id'] ?? null,
            ':consentimiento_at' => $d['consentimiento_at'] ?? null,
            ':descriptor_facial' => isset($d['descriptor_facial']) ? json_encode($d['descriptor_facial']) : null,
            ':activo'           => (($d['activo'] ?? true) ? 'true' : 'false'),
            ':id_u'             => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    /** Regenera el token personal del empleado. */
    public function updateToken(int $id, int $idEmpresa, string $qrToken, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET qr_token = :t, updated_by = :id_u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':t' => $qrToken, ':id_u' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /** Vincula el dispositivo (celular) en el primer uso. */
    public function setDispositivo(int $id, int $idEmpresa, string $dispositivoId): bool
    {
        $sql = "UPDATE {$this->table} SET dispositivo_id = :d, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':d' => $dispositivoId, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /** Registra el consentimiento biométrico (LOPDP). */
    public function setConsentimiento(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET consentimiento_at = CURRENT_TIMESTAMP, updated_by = :id_u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':id_u' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /** Guarda/actualiza el descriptor facial (Fase 3). */
    public function setDescriptor(int $id, int $idEmpresa, array $descriptor, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET descriptor_facial = :d, updated_by = :id_u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':d' => json_encode($descriptor), ':id_u' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }
}
