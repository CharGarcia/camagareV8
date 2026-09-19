<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\repositories\modulos\VacacionRepository;
use App\repositories\modulos\VacacionSolicitudRepository;
use App\Rules\modulos\VacacionSolicitudRules;
use App\Services\EnvioDocumentosSRIService;
use App\Services\LogSistemaService;
use Exception;

/**
 * Solicitudes de vacaciones.
 *
 * Recursos Humanos envía al empleado un enlace por correo; el empleado llena el
 * formulario desde ese enlace (público, sin login) y la solicitud queda pendiente;
 * desde el sistema se aprueba —lo que CREA la vacación— o se rechaza.
 *
 * El enlace es de un solo uso y caduca. Todo lo que escribe va en transacción con
 * el candado de la solicitud (§8), para que un doble envío o dos aprobaciones
 * simultáneas no dupliquen nada.
 */
class VacacionSolicitudService
{
    /** Días que dura el enlace enviado al empleado. */
    public const DIAS_VALIDEZ = 15;

    private VacacionSolicitudRepository $repo;
    private VacacionSolicitudRules $rules;
    private LogSistemaService $log;
    private VacacionService $vacService;
    private VacacionRepository $vacRepo;

    public function __construct(
        VacacionSolicitudRepository $repo,
        VacacionSolicitudRules $rules,
        LogSistemaService $log,
        ?VacacionService $vacService = null,
        ?VacacionRepository $vacRepo = null
    ) {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
        $this->vacRepo    = $vacRepo ?? new VacacionRepository();
        $this->vacService = $vacService ?? new VacacionService(
            $this->vacRepo,
            new \App\Rules\modulos\VacacionRules(),
            $log
        );
    }

    /** Sin la tabla (SQL no aplicado), la función se anuncia como no habilitada en vez de reventar. */
    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    private function exigirDisponible(): void
    {
        if (!$this->disponible()) {
            throw new Exception('Las solicitudes de vacaciones todavía no están habilitadas en la base de datos. Avise al administrador del sistema.');
        }
    }

    // ─── 1. Enviar el enlace al empleado ─────────────────────────────────────

    /**
     * Crea la invitación y le manda el enlace al empleado. Si ya tenía uno vigente
     * sin usar, ese queda anulado: siempre vale el último enviado.
     *
     * @return array{id:int, url:string, enviado:bool, correo:string, expira:string}
     */
    public function enviarInvitacion(int $idEmpresa, int $idEmpleado, string $correo, int $idUsuario): array
    {
        $this->exigirDisponible();
        $correo = trim($correo);
        $this->rules->validarCorreo($correo);

        $emp = $this->vacRepo->getEmpleado($idEmpleado, $idEmpresa);
        if (!$emp) {
            throw new Exception('Empleado no encontrado.');
        }

        $expira = date('Y-m-d H:i:s', strtotime('+' . self::DIAS_VALIDEZ . ' days'));
        $token  = bin2hex(random_bytes(24));

        $this->repo->beginTransaction();
        try {
            // Un solo enlace vivo por empleado: el anterior deja de servir.
            $abierta = $this->repo->getInvitacionAbierta($idEmpleado, $idEmpresa);
            if ($abierta) {
                $this->repo->cancelarInvitacion((int) $abierta['id'], $idEmpresa, $idUsuario);
            }
            $id = $this->repo->crearInvitacion([
                'id_empresa'     => $idEmpresa,
                'id_empleado'    => $idEmpleado,
                'token'          => $token,
                'correo_destino' => $correo,
                'expira_at'      => $expira,
                'id_usuario'     => $idUsuario,
            ]);
            $this->log->registrar($idUsuario, $idEmpresa, 'ENVIAR_SOLICITUD', 'vacaciones_solicitudes', $id, null, [
                'id_empleado' => $idEmpleado,
                'correo'      => $correo,
                'expira_at'   => $expira,
            ]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        $url     = url_absoluta('solicitud-vacaciones/' . $token);
        $enviado = $this->enviarCorreoInvitacion($idEmpresa, $emp, $correo, $url, $expira);

        return [
            'id'      => $id,
            'url'     => $url,
            'enviado' => $enviado,
            'correo'  => $correo,
            'expira'  => $expira,
        ];
    }

    private function enviarCorreoInvitacion(int $idEmpresa, array $emp, string $correo, string $url, string $expira): bool
    {
        try {
            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $empresaNombre = (string) ($empresa['nombre'] ?? '');

            $data = [
                'empleado_nombre' => (string) ($emp['nombres_apellidos'] ?? ''),
                'empresa_nombre'  => $empresaNombre,
                'url'             => $url,
                'expira'          => date('d-m-Y H:i:s', strtotime($expira)),
            ];
            ob_start();
            require MVC_APP . '/views/emails/solicitud_vacaciones.php';
            $cuerpo = (string) ob_get_clean();

            return (new EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa,
                $correo,
                (string) ($emp['nombres_apellidos'] ?? ''),
                'Solicitud de vacaciones' . ($empresaNombre !== '' ? ' · ' . $empresaNombre : ''),
                $cuerpo,
                $empresaNombre
            );
        } catch (\Throwable $e) {
            // El enlace ya quedó creado: se puede copiar y enviar a mano.
            return false;
        }
    }

    // ─── 2. Formulario público (sin login) ───────────────────────────────────

    /**
     * Resuelve el enlace y devuelve lo que necesita la página pública: la
     * solicitud, el empleado y su saldo de vacaciones.
     *
     * @return array{solicitud:array, info:?array, error:string}
     */
    public function getContextoPublico(string $token): array
    {
        $token = trim($token);
        if ($token === '' || !$this->disponible()) {
            return ['solicitud' => [], 'info' => null, 'error' => 'El enlace no es válido o ya no está disponible.'];
        }

        $sol = $this->repo->getByToken($token);
        // Mismo mensaje para "no existe", "empresa inactiva" y "empleado dado de
        // baja": el enlace no debe revelar el motivo (§6).
        if (!$sol) {
            return ['solicitud' => [], 'info' => null, 'error' => 'El enlace no es válido o ya no está disponible.'];
        }
        if (($sol['empleado_estado'] ?? '') !== 'activo') {
            return ['solicitud' => [], 'info' => null, 'error' => 'El enlace no es válido o ya no está disponible.'];
        }

        $error = $this->motivoNoUsable($sol);
        $info  = null;
        if ($error === '') {
            try {
                $info = $this->vacService->getInfoEmpleado((int) $sol['id_empleado'], (int) $sol['id_empresa']);
            } catch (\Throwable $e) {
                $info = null; // sin fecha de ingreso: el formulario sigue sirviendo, sin saldo
            }
        }
        return ['solicitud' => $sol, 'info' => $info, 'error' => $error];
    }

    /** '' si el enlace se puede usar; si no, el mensaje que verá el empleado. */
    private function motivoNoUsable(array $sol): string
    {
        $estado = (string) ($sol['estado'] ?? '');
        if ($estado === 'pendiente') {
            return 'Ya enviamos su solicitud: está en revisión. Si necesita cambiarla, hable con Recursos Humanos.';
        }
        if ($estado === 'aprobada') {
            return 'Esta solicitud ya fue aprobada.';
        }
        if ($estado === 'rechazada') {
            return 'Esta solicitud fue rechazada. Pida un enlace nuevo si quiere volver a solicitar sus vacaciones.';
        }
        if ($estado !== 'enviada' || !empty($sol['token_usado'])) {
            return 'El enlace no es válido o ya no está disponible.';
        }
        if (!empty($sol['expira_at']) && strtotime((string) $sol['expira_at']) < time()) {
            return 'El enlace caducó. Pida uno nuevo a Recursos Humanos.';
        }
        return '';
    }

    /**
     * Guarda lo que llenó el empleado. Deja la solicitud pendiente de aprobación y
     * consume el enlace.
     */
    public function registrarSolicitud(string $token, array $data, string $ip): array
    {
        $ctx = $this->getContextoPublico($token);
        if ($ctx['error'] !== '') {
            throw new Exception($ctx['error']);
        }
        $sol       = $ctx['solicitud'];
        $idEmpresa = (int) $sol['id_empresa'];

        // Defensa en profundidad (§6): la empresa ya se validó al resolver el token,
        // se revalida antes de escribir.
        if (!$this->empresaActiva($idEmpresa)) {
            throw new Exception('El enlace no es válido o ya no está disponible.');
        }

        $saldo = (float) ($ctx['info']['saldo'] ?? 0);
        $this->rules->validarSolicitud($data, $saldo);

        $this->repo->beginTransaction();
        try {
            $this->repo->lockSolicitud((int) $sol['id']);
            $ok = $this->repo->guardarSolicitud((int) $sol['id'], [
                'fecha_desde'      => $data['fecha_desde'],
                'fecha_hasta'      => $data['fecha_hasta'],
                'dias_solicitados' => (float) $data['dias_solicitados'],
                'motivo'           => $data['motivo'] ?? '',
                'contacto'         => $data['contacto'] ?? '',
                'ip'               => $ip,
            ]);
            if (!$ok) {
                throw new Exception('Ya enviamos su solicitud: está en revisión.');
            }
            // Sin sesión: el usuario de la auditoría es 0, como en las demás
            // acciones por token del sistema.
            $this->log->registrar(0, $idEmpresa, 'SOLICITAR_VACACIONES', 'vacaciones_solicitudes', (int) $sol['id'], null, [
                'id_empleado' => (int) $sol['id_empleado'],
                'fecha_desde' => $data['fecha_desde'],
                'fecha_hasta' => $data['fecha_hasta'],
                'dias'        => (float) $data['dias_solicitados'],
                'ip'          => $ip,
            ]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return ['id' => (int) $sol['id'], 'empleado' => (string) ($sol['empleado_nombre'] ?? '')];
    }

    private function empresaActiva(int $idEmpresa): bool
    {
        $st = $this->repo->getDb()->prepare("SELECT 1 FROM empresas WHERE id = :id AND estado = '1' AND eliminado = false LIMIT 1");
        $st->execute([':id' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    // ─── 3. Aprobar / rechazar ───────────────────────────────────────────────

    /**
     * Aprueba la solicitud: crea la vacación (con sus días de derecho, valor y mes
     * del rol) y la deja enlazada.
     *
     * Primero se crea la vacación y después se marca la solicitud: así, si la
     * creación falla (p. ej. el rol mensual de ese período ya está pagado), la
     * solicitud queda intacta y el usuario ve el motivo real.
     *
     * @return int id de la vacación creada
     */
    public function aprobar(int $id, int $idEmpresa, int $idUsuario, array $opciones = []): int
    {
        $this->exigirDisponible();
        $sol = $this->repo->getDetalle($id, $idEmpresa);
        if (!$sol) {
            throw new Exception('Solicitud no encontrada.');
        }
        if (($sol['estado'] ?? '') !== 'pendiente') {
            throw new Exception('Esta solicitud ya fue resuelta. Actualice la ventana.');
        }
        $comentario = trim((string) ($opciones['comentario'] ?? ''));
        $this->rules->validarResolucion('aprobada', $comentario);

        $observacion = 'Solicitud del empleado'
            . (!empty($sol['solicitado_at']) ? ' del ' . date('d-m-Y', strtotime((string) $sol['solicitado_at'])) : '')
            . (!empty($sol['motivo']) ? ': ' . $sol['motivo'] : '.');

        // La vacación se crea con el service del módulo: él valida, calcula los días
        // de derecho y el valor, audita y resincroniza el rol del período.
        $idVacacion = $this->vacService->crear([
            'id_empresa'   => $idEmpresa,
            'id_usuario'   => $idUsuario,
            'id_empleado'  => (int) $sol['id_empleado'],
            'fecha_desde'  => $sol['fecha_desde'],
            'fecha_hasta'  => $sol['fecha_hasta'],
            'dias_gozados' => (float) $sol['dias_solicitados'],
            'periodo_mes'  => 0,   // lo deduce de fecha_desde
            'periodo_anio' => 0,
            'afecta_rol'   => 1,
            'observacion'  => mb_substr($observacion, 0, 255),
            'estado'       => 'registrado',
        ]);

        $this->repo->beginTransaction();
        try {
            $this->repo->lockSolicitud($id);
            $ok = $this->repo->resolver($id, $idEmpresa, 'aprobada', $idUsuario, $comentario, $idVacacion);
            if (!$ok) {
                // Otro usuario la resolvió mientras tanto: se deshace la vacación
                // recién creada para no dejarla duplicada.
                $this->repo->rollBack();
                try {
                    $this->vacService->eliminar($idVacacion, $idEmpresa, $idUsuario);
                } catch (\Throwable $e) {
                    // La vacación queda visible en el módulo; mejor eso que perder el dato.
                }
                throw new Exception('Esta solicitud ya fue resuelta por otro usuario. Actualice la ventana.');
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'APROBAR_SOLICITUD', 'vacaciones_solicitudes', $id, $sol, [
                'estado'      => 'aprobada',
                'id_vacacion' => $idVacacion,
                'comentario'  => $comentario,
            ]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        if (!empty($opciones['notificar'])) {
            $this->avisarEmpleado($idEmpresa, $sol, 'aprobada', $comentario);
        }
        return $idVacacion;
    }

    public function rechazar(int $id, int $idEmpresa, int $idUsuario, string $motivo, bool $notificar = false): void
    {
        $this->exigirDisponible();
        $sol = $this->repo->getDetalle($id, $idEmpresa);
        if (!$sol) {
            throw new Exception('Solicitud no encontrada.');
        }
        if (($sol['estado'] ?? '') !== 'pendiente') {
            throw new Exception('Esta solicitud ya fue resuelta. Actualice la ventana.');
        }
        $motivo = trim($motivo);
        $this->rules->validarResolucion('rechazada', $motivo);

        $this->repo->beginTransaction();
        try {
            $this->repo->lockSolicitud($id);
            if (!$this->repo->resolver($id, $idEmpresa, 'rechazada', $idUsuario, $motivo, null)) {
                throw new Exception('Esta solicitud ya fue resuelta por otro usuario. Actualice la ventana.');
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'RECHAZAR_SOLICITUD', 'vacaciones_solicitudes', $id, $sol, [
                'estado'     => 'rechazada',
                'comentario' => $motivo,
            ]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        if ($notificar) {
            $this->avisarEmpleado($idEmpresa, $sol, 'rechazada', $motivo);
        }
    }

    /** Anula un enlace enviado que el empleado todavía no usó. */
    public function cancelar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->exigirDisponible();
        $sol = $this->repo->getDetalle($id, $idEmpresa);
        if (!$sol) {
            throw new Exception('Solicitud no encontrada.');
        }
        if (($sol['estado'] ?? '') !== 'enviada') {
            throw new Exception('Solo se puede anular un enlace que el empleado todavía no usó.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->lockSolicitud($id);
            if (!$this->repo->cancelarInvitacion($id, $idEmpresa, $idUsuario)) {
                throw new Exception('El enlace ya no está disponible. Actualice la ventana.');
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR_SOLICITUD', 'vacaciones_solicitudes', $id, $sol, ['estado' => 'cancelada']);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Avisa al empleado cómo quedó su solicitud (silencioso: la resolución ya se guardó). */
    private function avisarEmpleado(int $idEmpresa, array $sol, string $estado, string $comentario): void
    {
        $correo = trim((string) ($sol['empleado_email'] ?? $sol['correo_destino'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $empresaNombre = (string) ($empresa['nombre'] ?? '');
            $e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

            $aprobada = $estado === 'aprobada';
            $desde = !empty($sol['fecha_desde']) ? date('d-m-Y', strtotime((string) $sol['fecha_desde'])) : '';
            $hasta = !empty($sol['fecha_hasta']) ? date('d-m-Y', strtotime((string) $sol['fecha_hasta'])) : '';

            $cuerpo = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">'
                . '<p>Hola ' . $e($sol['empleado_nombre'] ?? '') . ',</p>'
                . '<p>Su solicitud de vacaciones del <b>' . $e($desde) . '</b> al <b>' . $e($hasta) . '</b> ('
                . $e((float) ($sol['dias_solicitados'] ?? 0)) . ' día(s)) fue <b style="color:'
                . ($aprobada ? '#16a34a' : '#dc2626') . ';">' . ($aprobada ? 'APROBADA' : 'RECHAZADA') . '</b>.</p>'
                . ($comentario !== '' ? '<p><b>' . ($aprobada ? 'Comentario' : 'Motivo') . ':</b> ' . $e($comentario) . '</p>' : '')
                . '<p style="color:#666;font-size:12px;">' . $e($empresaNombre) . '</p></div>';

            (new EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa,
                $correo,
                (string) ($sol['empleado_nombre'] ?? ''),
                'Su solicitud de vacaciones fue ' . ($aprobada ? 'aprobada' : 'rechazada'),
                $cuerpo,
                $empresaNombre
            );
        } catch (\Throwable $e) {
            // Sin correo configurado: la resolución ya quedó guardada igual.
        }
    }

    // ─── 4. Lecturas ─────────────────────────────────────────────────────────

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getDetalle($id, $idEmpresa);
    }

    public function getPorEmpleado(int $idEmpleado, int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getPorEmpleado($idEmpleado, $idEmpresa, $idUsuarioFiltro);
    }

    public function getBandeja(int $idEmpresa, string $estado = '', ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getBandeja($idEmpresa, $estado, $idUsuarioFiltro);
    }

    public function contarPendientes(int $idEmpresa, ?int $idUsuarioFiltro = null): int
    {
        return $this->repo->contarPendientes($idEmpresa, $idUsuarioFiltro);
    }

    /** Enlace público de una invitación (para copiarlo si el correo no llegó). */
    public function urlDe(array $sol): string
    {
        return !empty($sol['token']) ? url_absoluta('solicitud-vacaciones/' . $sol['token']) : '';
    }
}
