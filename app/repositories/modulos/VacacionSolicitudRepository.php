<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Solicitudes de vacaciones: el empleado las envía desde el enlace que recibe por
 * correo (sin login) y se aprueban o rechazan desde el sistema.
 *
 * Sin tipo_ambiente, a propósito (igual que vacaciones_periodos): la solicitud es
 * historia del empleado, no un documento electrónico.
 */
class VacacionSolicitudRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('vacaciones_solicitudes');
    }

    /** ¿Ya se aplicó database/modulos_vacaciones_solicitudes.sql? Sin la tabla, el módulo sigue como antes. */
    public function disponible(): bool
    {
        return $this->tablaExiste($this->table);
    }

    /**
     * Candado de la solicitud (§8): "leer el estado → validar → escribir" no debe
     * correr dos veces a la vez sobre la misma fila (doble envío del formulario
     * público, dos aprobaciones simultáneas). Se libera solo al COMMIT/ROLLBACK.
     */
    public function lockSolicitud(int $id): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('vacacion_solicitud:' || :id))")
                 ->execute([':id' => $id]);
    }

    /** Crea la invitación (el enlace que se le envía al empleado). */
    public function crearInvitacion(array $d): int
    {
        $st = $this->db->prepare("INSERT INTO {$this->table} (
                    id_empresa, id_empleado, token, correo_destino, enviado_at, expira_at,
                    estado, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :id_empleado, :token, :correo, CURRENT_TIMESTAMP, :expira,
                    'enviada', :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                ) RETURNING id");
        $st->execute([
            ':id_empresa'  => (int) $d['id_empresa'],
            ':id_empleado' => (int) $d['id_empleado'],
            ':token'       => (string) $d['token'],
            ':correo'      => $this->caparTexto('correo_destino', $d['correo_destino'] ?? null),
            ':expira'      => $d['expira_at'],
            ':id_u'        => (int) $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Resuelve el enlace público. Filtra la empresa activa en el mismo query (§6):
     * si la empresa se desactiva, el enlace debe comportarse como si no existiera.
     */
    public function getByToken(string $token): ?array
    {
        $st = $this->db->prepare("SELECT s.*,
                                         e.nombres_apellidos AS empleado_nombre,
                                         e.identificacion    AS empleado_identificacion,
                                         e.email             AS empleado_email,
                                         e.estado            AS empleado_estado,
                                         emp.nombre          AS empresa_nombre
                                  FROM {$this->table} s
                                  JOIN empleados e  ON e.id  = s.id_empleado
                                  JOIN empresas  emp ON emp.id = s.id_empresa
                                  WHERE s.token = :t AND s.eliminado = false
                                    AND e.eliminado = false
                                    AND emp.estado = '1' AND emp.eliminado = false
                                  LIMIT 1");
        $st->execute([':t' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Guarda lo que llenó el empleado y consume el enlace (queda pendiente de aprobación). */
    public function guardarSolicitud(int $id, array $d): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET
                    fecha_desde      = :desde,
                    fecha_hasta      = :hasta,
                    dias_solicitados = :dias,
                    motivo           = :motivo,
                    contacto         = :contacto,
                    solicitado_at    = CURRENT_TIMESTAMP,
                    solicitud_ip     = :ip,
                    estado           = 'pendiente',
                    token_usado      = true,
                    updated_at       = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false AND estado = 'enviada'");
        $st->execute([
            ':desde'    => $d['fecha_desde'],
            ':hasta'    => $d['fecha_hasta'],
            ':dias'     => (float) $d['dias_solicitados'],
            ':motivo'   => $this->caparTexto('motivo', ($d['motivo'] ?? '') !== '' ? $d['motivo'] : null),
            ':contacto' => $this->caparTexto('contacto', ($d['contacto'] ?? '') !== '' ? $d['contacto'] : null),
            ':ip'       => $d['ip'] ?? null,
            ':id'       => $id,
        ]);
        return $st->rowCount() > 0;
    }

    /**
     * Aprueba o rechaza. Solo desde 'pendiente', para que dos usuarios que la
     * resuelven a la vez no la resuelvan dos veces (el segundo no toca nada).
     */
    public function resolver(int $id, int $idEmpresa, string $estado, int $idUsuario, ?string $comentario, ?int $idVacacion): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET
                    estado      = :estado,
                    resuelto_by = :u,
                    resuelto_at = CURRENT_TIMESTAMP,
                    comentario  = :comentario,
                    id_vacacion = :id_vacacion,
                    updated_by  = :u,
                    updated_at  = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp AND eliminado = false AND estado = 'pendiente'");
        $st->execute([
            ':estado'      => $estado,
            ':u'           => $idUsuario,
            ':comentario'  => $this->caparTexto('comentario', ($comentario ?? '') !== '' ? $comentario : null),
            ':id_vacacion' => $idVacacion,
            ':id'          => $id,
            ':emp'         => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    /** Anula una invitación que todavía no usó el empleado (el enlace deja de servir). */
    public function cancelarInvitacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET
                    estado     = 'cancelada',
                    token      = NULL,
                    updated_by = :u,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp AND eliminado = false AND estado = 'enviada'");
        $st->execute([':u' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $st = $this->db->prepare("SELECT s.*,
                                         e.nombres_apellidos AS empleado_nombre,
                                         e.identificacion    AS empleado_identificacion,
                                         e.email             AS empleado_email,
                                         e.cargo             AS empleado_cargo,
                                         u.nombre            AS resuelto_nombre,
                                         ue.nombre           AS enviado_nombre
                                  FROM {$this->table} s
                                  JOIN empleados e ON e.id = s.id_empleado
                                  LEFT JOIN usuarios u  ON u.id  = s.resuelto_by
                                  LEFT JOIN usuarios ue ON ue.id = s.created_by
                                  WHERE s.id = :id AND s.id_empresa = :emp AND s.eliminado = false");
        $st->execute([':id' => $id, ':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Solicitudes de un empleado, de la más reciente a la más antigua (ficha del empleado). */
    public function getPorEmpleado(int $idEmpleado, int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $where  = $this->getBaseWhere($idEmpresa, 's', $idUsuarioFiltro) . " AND s.id_empleado = :e";
        $params = [':id_empresa' => $idEmpresa, ':e' => $idEmpleado];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $st = $this->db->prepare("SELECT s.*, u.nombre AS resuelto_nombre
                                  FROM {$this->table} s
                                  LEFT JOIN usuarios u ON u.id = s.resuelto_by
                                  {$where}
                                  ORDER BY s.created_at DESC, s.id DESC");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bandeja del módulo: solicitudes de toda la empresa. `$estado` vacío = las que
     * siguen abiertas (esperando al empleado o esperando aprobación).
     */
    public function getBandeja(int $idEmpresa, string $estado = '', ?int $idUsuarioFiltro = null): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $where  = $this->getBaseWhere($idEmpresa, 's', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        if ($estado !== '') {
            $where .= " AND s.estado = :estado";
            $params[':estado'] = $estado;
        } else {
            $where .= " AND s.estado IN ('enviada', 'pendiente')";
        }

        $st = $this->db->prepare("SELECT s.*,
                                         e.nombres_apellidos AS empleado_nombre,
                                         e.identificacion    AS empleado_identificacion,
                                         u.nombre            AS resuelto_nombre
                                  FROM {$this->table} s
                                  JOIN empleados e ON e.id = s.id_empleado
                                  LEFT JOIN usuarios u ON u.id = s.resuelto_by
                                  {$where}
                                  ORDER BY (s.estado = 'pendiente') DESC, s.solicitado_at DESC NULLS LAST, s.id DESC
                                  LIMIT 200");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuántas esperan aprobación (badge de la bandeja). */
    public function contarPendientes(int $idEmpresa, ?int $idUsuarioFiltro = null): int
    {
        if (!$this->disponible()) {
            return 0;
        }
        $where  = $this->getBaseWhere($idEmpresa, 's', $idUsuarioFiltro) . " AND s.estado = 'pendiente'";
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} s {$where}");
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    /** ¿El empleado ya tiene un enlace vigente sin usar? (para no mandarle dos). */
    public function getInvitacionAbierta(int $idEmpleado, int $idEmpresa): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $st = $this->db->prepare("SELECT * FROM {$this->table}
                                  WHERE id_empresa = :emp AND id_empleado = :e AND eliminado = false
                                    AND estado = 'enviada' AND (expira_at IS NULL OR expira_at > CURRENT_TIMESTAMP)
                                  ORDER BY id DESC LIMIT 1");
        $st->execute([':emp' => $idEmpresa, ':e' => $idEmpleado]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
