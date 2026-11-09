<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: cuentas de redes conectadas por empresa (crm_canales).
 * El token va cifrado (CryptoHelper) y solo lo leen getTokenCifrado() y el cron:
 * los listados para la pantalla nunca lo devuelven.
 */
class CrmCanalRepository extends BaseRepository
{
    protected string $table = 'crm_canales';

    /** Columnas seguras para la pantalla (sin token). */
    private const COLUMNAS = "c.id, c.id_empresa, c.tipo, c.id_externo, c.usuario, c.nombre, c.token_expira_at,
        c.permisos, c.ventana_humana, c.config, c.estado, c.created_at, c.updated_at, u.nombre AS conectado_por";

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getPorEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM crm_canales c LEFT JOIN usuarios u ON u.id = c.created_by
              WHERE c.id_empresa = :e AND c.eliminado = false ORDER BY c.tipo, c.usuario, c.id'
        );
        $st->execute([':e' => $idEmpresa]);
        return array_map([$this, 'decodificar'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM crm_canales c LEFT JOIN usuarios u ON u.id = c.created_by
              WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = false'
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? $this->decodificar($r) : null;
    }

    /** Cuenta ya registrada (en cualquier empresa): una cuenta no se conecta a dos empresas. */
    public function buscarPorExterno(string $tipo, string $idExterno): ?array
    {
        $st = $this->db->prepare(
            'SELECT id, id_empresa, estado FROM crm_canales WHERE tipo = :t AND id_externo = :x AND eliminado = false LIMIT 1'
        );
        $st->execute([':t' => $tipo, ':x' => $idExterno]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Webhook (endpoint público, CLAUDE.md §6): canal ACTIVO de la cuenta, solo si
     * su empresa está activa y no eliminada. Si no, se comporta como si no existiera.
     * Incluye el token cifrado (para leer perfiles y bajar adjuntos).
     */
    public function getActivoPorExterno(string $tipo, string $idExterno): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_empresa, c.id_externo, c.usuario, c.config, c.token_cifrado
               FROM crm_canales c
               JOIN empresas e ON e.id = c.id_empresa AND e.estado = '1' AND e.eliminado = false
              WHERE c.tipo = :t AND c.id_externo = :x AND c.eliminado = false AND c.estado = 'activo'
              LIMIT 1"
        );
        $st->execute([':t' => $tipo, ':x' => $idExterno]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['config'] = json_decode((string) ($r['config'] ?? '{}'), true) ?: [];
        return $r;
    }

    /** Empresas que tienen (o tuvieron, sin liberar) alguna de esas cuentas. Para elegir con qué app validar la firma. */
    public function empresasDeCuentas(string $tipo, array $idsExternos): array
    {
        $idsExternos = array_values(array_filter(array_map('strval', $idsExternos)));
        if (!$idsExternos) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($idsExternos), '?'));
        $st = $this->db->prepare("SELECT DISTINCT id_empresa FROM crm_canales WHERE tipo = ? AND eliminado = false AND id_externo IN ({$marcas})");
        $st->execute(array_merge([$tipo], $idsExternos));
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Candado por cuenta externa: dos conexiones simultáneas de la misma cuenta no crean dos filas. */
    public function lockExterno(string $tipo, string $idExterno): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_canal:' || :t || ':' || :x))");
        $st->execute([':t' => $tipo, ':x' => $idExterno]);
    }

    public function getTokenCifrado(int $id, int $idEmpresa): ?string
    {
        $st = $this->db->prepare('SELECT token_cifrado FROM crm_canales WHERE id = :id AND id_empresa = :e AND eliminado = false');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insertar(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_canales (id_empresa, tipo, id_externo, usuario, nombre, token_cifrado, token_expira_at,
                                      permisos, config, estado, created_by, updated_by)
             VALUES (:e, :t, :x, :us, :n, :tok, :exp, CAST(:perm AS jsonb), CAST(:cfg AS jsonb), 'activo', :u, :u2)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':t' => $d['tipo'], ':x' => $d['id_externo'],
            ':us' => $this->caparTexto('usuario', $d['usuario'] ?? null), ':n' => $this->caparTexto('nombre', $d['nombre'] ?? null),
            ':tok' => $d['token_cifrado'], ':exp' => $d['token_expira_at'],
            ':perm' => json_encode($d['permisos'] ?? []), ':cfg' => json_encode($d['config'] ?? new \stdClass()),
            ':u' => $idUsuario, ':u2' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Reconexión de una cuenta ya registrada en la empresa: token nuevo y activa. */
    public function reconectar(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_canales SET usuario = :us, nombre = :n, token_cifrado = :tok, token_expira_at = :exp,
                    permisos = CAST(:perm AS jsonb), estado = 'activo', updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':us' => $this->caparTexto('usuario', $d['usuario'] ?? null), ':n' => $this->caparTexto('nombre', $d['nombre'] ?? null),
            ':tok' => $d['token_cifrado'], ':exp' => $d['token_expira_at'], ':perm' => json_encode($d['permisos'] ?? []),
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function actualizarToken(int $id, string $tokenCifrado, string $expiraAt): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_canales SET token_cifrado = :tok, token_expira_at = :exp, estado = 'activo', updated_at = NOW()
              WHERE id = :id AND eliminado = false"
        );
        $st->execute([':tok' => $tokenCifrado, ':exp' => $expiraAt, ':id' => $id]);
    }

    public function marcarEstado(int $id, string $estado): void
    {
        $st = $this->db->prepare('UPDATE crm_canales SET estado = :s, updated_at = NOW() WHERE id = :id AND eliminado = false');
        $st->execute([':s' => $estado, ':id' => $id]);
    }

    /**
     * Desconectar: deja de recibir/enviar (estado desconectado) y BORRA el token.
     * La fila queda (no se elimina) para que, si la misma cuenta se vuelve a
     * conectar en esta empresa, sus conversaciones sigan en el mismo canal.
     */
    public function desconectar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_canales SET estado = 'desconectado', token_cifrado = NULL, token_expira_at = NULL,
                    updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /**
     * Libera una cuenta DESCONECTADA de otra empresa para que esta la conecte
     * (eliminación lógica de esa fila; sus conversaciones quedan en esa empresa).
     */
    public function liberar(int $id, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_canales SET eliminado = true, deleted_at = NOW(), deleted_by = :u
              WHERE id = :id AND eliminado = false AND estado = 'desconectado'"
        );
        $st->execute([':u' => $idUsuario, ':id' => $id]);
    }

    /** Mezcla claves en config (jsonb ||). ventana_humana solo cambia si no es null. */
    public function guardarAjustes(int $id, int $idEmpresa, array $config, ?bool $ventanaHumana, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_canales SET config = COALESCE(config, '{}'::jsonb) || CAST(:cfg AS jsonb),
                    ventana_humana = COALESCE(CAST(:vh AS boolean), ventana_humana),
                    updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':cfg' => json_encode($config),
            ':vh'  => $ventanaHumana === null ? null : ($ventanaHumana ? 'true' : 'false'),
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /**
     * Canales activos de empresas activas cuyo token vence dentro de $dias (cron).
     * Incluye el token cifrado: solo para el proceso de renovación.
     */
    public function porRenovar(int $dias): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.id_empresa, c.token_cifrado, c.token_expira_at, c.updated_at
               FROM crm_canales c
               JOIN empresas e ON e.id = c.id_empresa AND e.estado = '1' AND e.eliminado = false
              WHERE c.eliminado = false AND c.estado = 'activo' AND c.token_cifrado IS NOT NULL
                AND c.token_expira_at < NOW() + make_interval(days => :d)"
        );
        $st->execute([':d' => $dias]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function decodificar(array $r): array
    {
        $r['permisos'] = json_decode((string) ($r['permisos'] ?? '[]'), true) ?: [];
        $r['config']   = json_decode((string) ($r['config'] ?? '{}'), true) ?: [];
        $r['ventana_humana'] = in_array($r['ventana_humana'], [true, 't', 'true', 1, '1'], true);
        return $r;
    }
}
