<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Portal de representantes del módulo Alumnos (QR general del colegio).
 * Único punto de acceso a BD del portal (CLAUDE.md §3). Sin lógica de negocio.
 */
class AlumnoPortalRepository extends BaseRepository
{
    protected string $table = 'alumnos_portal';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /** Tablas del portal (SQL 20260929_alumnos_portal_representantes.sql). */
    public function existeEsquema(): bool
    {
        $st = $this->db->query("SELECT to_regclass('public.alumnos_portal') IS NOT NULL
                                   AND to_regclass('public.alumnos_portal_codigos') IS NOT NULL
                                   AND EXISTS (SELECT 1 FROM information_schema.columns
                                                WHERE table_name = 'alumnos' AND column_name = 'origen')");
        return (bool) $st->fetchColumn();
    }

    // ── Portal (un QR por empresa) ───────────────────────────────────────────

    public function getPortalEmpresa(int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM alumnos_portal WHERE id_empresa = ? AND eliminado = false LIMIT 1");
        $st->execute([$idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crearPortal(int $idEmpresa, string $token, int $idUsuario): int
    {
        $st = $this->db->prepare("INSERT INTO alumnos_portal (id_empresa, token, activo, created_by, updated_by) VALUES (?, ?, true, ?, ?) RETURNING id");
        $st->execute([$idEmpresa, $token, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function actualizarPortal(int $id, int $idEmpresa, array $campos, int $idUsuario): void
    {
        $sets = [];
        $params = [':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario];
        if (array_key_exists('token', $campos)) { $sets[] = 'token = :t'; $params[':t'] = $campos['token']; }
        if (array_key_exists('activo', $campos)) { $sets[] = 'activo = :a'; $params[':a'] = $campos['activo'] ? 'true' : 'false'; }
        if (!$sets) { return; }
        $st = $this->db->prepare("UPDATE alumnos_portal SET " . implode(', ', $sets) . ", updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute($params);
    }

    /**
     * Portal por token del QR. Solo si está activo y la EMPRESA está activa
     * (CLAUDE.md §6: endpoint público sin sesión).
     */
    public function getPortalPorToken(string $token): ?array
    {
        $st = $this->db->prepare("SELECT p.id, p.id_empresa, p.created_by, p.updated_by,
                                         COALESCE(NULLIF(emp.nombre_comercial, ''), emp.nombre) AS empresa_nombre
                                    FROM alumnos_portal p
                                    JOIN empresas emp ON emp.id = p.id_empresa
                                   WHERE p.token = :t AND p.activo = true AND p.eliminado = false
                                     AND emp.estado = '1' AND emp.eliminado = false
                                   LIMIT 1");
        $st->execute([':t' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Representante (cliente) ──────────────────────────────────────────────

    /** Clientes vivos de la empresa con alguna de esas identificaciones (cédula ↔ RUC). */
    public function buscarClientes(int $idEmpresa, array $identificaciones): array
    {
        $ids = array_values(array_unique(array_filter($identificaciones, fn($v) => $v !== '')));
        if (!$ids) { return []; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, nombre, tipo_id, identificacion, email, telefono, direccion
                                    FROM clientes
                                   WHERE id_empresa = ? AND eliminado = false AND identificacion IN ($ph)
                                   ORDER BY (identificacion = ?) DESC, id ASC");
        $st->execute(array_merge([$idEmpresa], $ids, [$ids[0]]));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarCliente(int $idCliente, int $idEmpresa, array $d, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE clientes SET nombre = :n, direccion = :dir, email = :em, telefono = :tel,
                                         updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':n' => $d['nombre'], ':dir' => $d['direccion'] ?: null, ':em' => $d['email'], ':tel' => $d['telefono'] ?: null,
                      ':u' => $idUsuario, ':id' => $idCliente, ':e' => $idEmpresa]);
    }

    public function crearCliente(int $idEmpresa, array $d, int $idUsuario): int
    {
        $st = $this->db->prepare("INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, status, created_by, updated_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?) RETURNING id");
        $st->execute([$idEmpresa, $idUsuario, $d['nombre'], $d['tipo_id'], $d['identificacion'], $d['telefono'] ?: null,
                      $d['email'], $d['direccion'] ?: null, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function getCliente(int $idCliente, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, tipo_id, identificacion, email, telefono, direccion FROM clientes WHERE id = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$idCliente, $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Códigos de verificación ──────────────────────────────────────────────

    /**
     * Códigos pedidos en los últimos $minutos: [por identificación, por IP]. La
     * identificación se cuenta con todas sus variantes (cédula ↔ RUC …001): si no,
     * la misma persona duplicaría su cupo alternando cómo la escribe.
     */
    public function contarCodigosRecientes(int $idEmpresa, array $identificaciones, string $ip, int $minutos): array
    {
        $ids = array_values(array_unique(array_filter($identificaciones, fn($v) => $v !== ''))) ?: [''];
        $ph = [];
        $params = [':e' => $idEmpresa, ':ip' => $ip, ':m' => $minutos];
        foreach ($ids as $k => $v) { $ph[] = ":i$k"; $params[":i$k"] = $v; }
        $st = $this->db->prepare("SELECT
                COUNT(*) FILTER (WHERE id_empresa = :e AND identificacion IN (" . implode(',', $ph) . ")) AS por_ident,
                COUNT(*) FILTER (WHERE ip = :ip) AS por_ip
              FROM alumnos_portal_codigos
             WHERE created_at >= NOW() - (CAST(:m AS INTEGER) * INTERVAL '1 minute')");
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [(int) ($r['por_ident'] ?? 0), (int) ($r['por_ip'] ?? 0)];
    }

    public function crearCodigo(array $d): int
    {
        $st = $this->db->prepare("INSERT INTO alumnos_portal_codigos (id_empresa, identificacion, id_cliente, correo, codigo_hash, expira_at, ip)
                                  VALUES (?, ?, ?, ?, ?, NOW() + (CAST(? AS INTEGER) * INTERVAL '1 minute'), ?) RETURNING id");
        $st->execute([$d['id_empresa'], $d['identificacion'], $d['id_cliente'], $d['correo'], $d['codigo_hash'], $d['minutos'], $d['ip']]);
        return (int) $st->fetchColumn();
    }

    /** Código con banderas de vigencia calculadas en la BD (misma hora que expira_at). */
    public function getCodigo(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT c.*, (c.expira_at > NOW()) AS vigente,
                                         (c.verificado_at IS NOT NULL AND c.verificado_at > NOW() - (CAST(:sm AS INTEGER) * INTERVAL '1 minute')) AS sesion_vigente
                                    FROM alumnos_portal_codigos c
                                   WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':sm' => self::MINUTOS_SESION]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public const MINUTOS_SESION = 45;

    public function getCodigoPorSesion(string $sesion, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id FROM alumnos_portal_codigos WHERE sesion = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$sesion, $idEmpresa]);
        $id = $st->fetchColumn();
        return $id ? $this->getCodigo((int) $id, $idEmpresa) : null;
    }

    public function sumarIntento(int $id): void
    {
        $this->db->prepare("UPDATE alumnos_portal_codigos SET intentos = intentos + 1 WHERE id = ?")->execute([$id]);
    }

    public function marcarVerificado(int $id, string $sesion): void
    {
        $this->db->prepare("UPDATE alumnos_portal_codigos SET verificado_at = NOW(), sesion = ? WHERE id = ? AND verificado_at IS NULL")->execute([$sesion, $id]);
    }

    /** Consume el código. Devuelve false si ya estaba usado (doble envío concurrente). */
    public function marcarUsado(int $id): bool
    {
        $st = $this->db->prepare("UPDATE alumnos_portal_codigos SET usado_at = NOW() WHERE id = ? AND usado_at IS NULL");
        $st->execute([$id]);
        return $st->rowCount() === 1;
    }

    // ── Alumnos del representante ────────────────────────────────────────────

    public function getAlumnosDeCliente(int $idCliente, int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, nombres, apellidos, tipo_identificacion, numero_identificacion, fecha_nacimiento, sexo,
                                         nacionalidad, tipo_sangre, alergias_condiciones, contacto_emergencia_nombre, contacto_emergencia_telefono
                                    FROM alumnos
                                   WHERE id_cliente = ? AND id_empresa = ? AND eliminado = false
                                   ORDER BY apellidos, nombres, id");
        $st->execute([$idCliente, $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Campos que el representante puede cambiar (nada de facturación, serie ni matrícula). */
    public const CAMPOS_ALUMNO = ['nombres', 'apellidos', 'tipo_identificacion', 'numero_identificacion', 'fecha_nacimiento', 'sexo',
                                  'nacionalidad', 'tipo_sangre', 'alergias_condiciones', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono'];

    public function actualizarAlumno(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $sets = [];
        $params = [':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario];
        foreach (self::CAMPOS_ALUMNO as $c) {
            $sets[] = "$c = :$c";
            $params[":$c"] = ($d[$c] ?? '') !== '' ? $d[$c] : null;
        }
        $params[':nombres'] = $d['nombres'];
        $params[':apellidos'] = $d['apellidos'];
        $st = $this->db->prepare("UPDATE alumnos SET " . implode(', ', $sets) . ", updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute($params);
    }

    public function crearAlumno(int $idEmpresa, int $idCliente, array $d, int $idUsuario): int
    {
        $cols = self::CAMPOS_ALUMNO;
        $vals = [];
        foreach ($cols as $c) { $vals[] = ($d[$c] ?? '') !== '' ? $d[$c] : null; }
        $vals[0] = $d['nombres'];
        $vals[1] = $d['apellidos'];
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        $st = $this->db->prepare("INSERT INTO alumnos (id_empresa, id_cliente, estado_academico, origen, " . implode(', ', $cols) . ", created_by, updated_by)
                                  VALUES (?, ?, 'activo', 'portal', $ph, ?, ?) RETURNING id");
        $st->execute(array_merge([$idEmpresa, $idCliente], $vals, [$idUsuario, $idUsuario]));
        return (int) $st->fetchColumn();
    }

    /** Matrícula vigente (desde hoy) del alumno registrado desde el portal. */
    public function crearMatricula(int $idAlumno, int $idEmpresa, ?int $idCampus, ?int $idNivel, int $idUsuario): void
    {
        $st = $this->db->prepare("INSERT INTO alumnos_periodos (id_alumno, id_empresa, id_campus, id_nivel, fecha_ingreso, estado, observacion, created_by, updated_by)
                                  VALUES (?, ?, ?, ?, CURRENT_DATE, 'activo', 'Registrado desde el portal de representantes', ?, ?)");
        $st->execute([$idAlumno, $idEmpresa, $idCampus, $idNivel, $idUsuario, $idUsuario]);
    }

    // ── Envío masivo del enlace ──────────────────────────────────────────────

    /** Clientes (con su correo) de los alumnos indicados. */
    public function getClientesDeAlumnos(array $idsAlumno, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsAlumno))));
        if (!$ids) { return []; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT DISTINCT c.id, c.nombre, c.email, c.identificacion
                                    FROM alumnos a
                                    JOIN clientes c ON c.id = a.id_cliente AND c.id_empresa = a.id_empresa AND c.eliminado = false
                                   WHERE a.id_empresa = ? AND a.eliminado = false AND a.id IN ($ph)
                                   ORDER BY c.nombre");
        $st->execute(array_merge([$idEmpresa], $ids));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
