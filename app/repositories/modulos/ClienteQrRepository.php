<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * QR de registro de clientes (uno por empresa) y bitácora de sus envíos.
 * Tablas: clientes_qr_config, clientes_qr_registros
 * (database/2026-11-26_clientes_qr_registro.sql).
 */
class ClienteQrRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('clientes_qr_config');
    }

    /** ¿Existe la tabla? Sin el SQL aplicado el módulo Clientes no debe romperse. */
    public function tablasDisponibles(): bool
    {
        $st = $this->db->query("SELECT to_regclass('public.clientes_qr_config') IS NOT NULL
                                   AND to_regclass('public.clientes_qr_registros') IS NOT NULL");
        return (bool) $st->fetchColumn();
    }

    public function getConfigPorEmpresa(int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM clientes_qr_config
             WHERE id_empresa = :id_empresa AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Resuelve el enlace público. Solo devuelve el QR si está activo y la EMPRESA está
     * activa y no eliminada (CLAUDE.md §6, endpoints públicos): con la empresa inactiva
     * el token se comporta como si no existiera.
     */
    public function getConfigPorToken(string $token): ?array
    {
        if ($token === '' || strlen($token) > 64) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT q.*, e.nombre AS empresa_nombre, e.nombre_comercial AS empresa_nombre_comercial
             FROM clientes_qr_config q
             JOIN empresas e ON e.id = q.id_empresa
             WHERE q.token = :token AND q.eliminado = false AND q.activo = true
               AND e.estado = '1' AND e.eliminado = false
             LIMIT 1"
        );
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Relee el QR bloqueándolo hasta el fin de la transacción (envío público). */
    public function bloquearConfig(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT q.* FROM clientes_qr_config q
             JOIN empresas e ON e.id = q.id_empresa
             WHERE q.id = :id AND q.eliminado = false AND q.activo = true
               AND e.estado = '1' AND e.eliminado = false
             FOR UPDATE OF q"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearConfig(int $idEmpresa, string $token, ?string $titulo, ?string $mensaje, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO clientes_qr_config (id_empresa, token, activo, titulo, mensaje, created_by, created_at, eliminado)
             VALUES (:id_empresa, :token, true, :titulo, :mensaje, :uid, NOW(), false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':token'      => $token,
            ':titulo'     => $titulo,
            ':mensaje'    => $mensaje,
            ':uid'        => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarConfig(int $id, int $idEmpresa, bool $activo, ?string $titulo, ?string $mensaje, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE clientes_qr_config
             SET activo = :activo, titulo = :titulo, mensaje = :mensaje,
                 updated_at = NOW(), updated_by = :uid
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([
            ':activo'     => $activo ? 'true' : 'false',
            ':titulo'     => $titulo,
            ':mensaje'    => $mensaje,
            ':uid'        => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }

    /** Candado transaccional (CLAUDE.md §8): se libera solo al COMMIT/ROLLBACK. */
    public function bloquearClave(string $clave): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext(:clave))");
        $st->execute([':clave' => $clave]);
    }

    /** Envíos de una IP a un QR en los últimos N minutos (anti-abuso). */
    public function contarEnviosPorIp(int $idConfig, string $ip, int $minutos): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM clientes_qr_registros
             WHERE id_config = :id_config AND ip_origen = :ip
               AND created_at >= NOW() - (:min || ' minutes')::interval"
        );
        $st->execute([':id_config' => $idConfig, ':ip' => $ip, ':min' => (string) $minutos]);
        return (int) $st->fetchColumn();
    }

    public function insertarRegistro(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO clientes_qr_registros
                (id_empresa, id_config, id_cliente, accion, datos, ip_origen, user_agent, created_by, created_at, eliminado)
             VALUES
                (:id_empresa, :id_config, :id_cliente, :accion, CAST(:datos AS jsonb), :ip, :ua, :uid, NOW(), false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa' => $d['id_empresa'],
            ':id_config'  => $d['id_config'],
            ':id_cliente' => $d['id_cliente'],
            ':accion'     => $d['accion'],
            ':datos'      => json_encode($d['datos'], JSON_UNESCAPED_UNICODE),
            ':ip'         => mb_substr((string) $d['ip_origen'], 0, 64),
            ':ua'         => mb_substr((string) $d['user_agent'], 0, 500),
            ':uid'        => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    /** Resumen para la ventana del QR: total de envíos y los de hoy. */
    public function resumenRegistros(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE) AS hoy,
                    COUNT(*) FILTER (WHERE accion = 'creado') AS creados,
                    COUNT(*) FILTER (WHERE accion = 'actualizado') AS actualizados
             FROM clientes_qr_registros
             WHERE id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $r + ['total' => 0, 'hoy' => 0, 'creados' => 0, 'actualizados' => 0]);
    }
}
