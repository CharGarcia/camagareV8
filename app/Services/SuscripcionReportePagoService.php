<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\modulos\SuscripcionReportePagoRepository;

/**
 * Pago de suscripción REPORTADO por el cliente desde el modal de vigencia
 * (mensaje y/o comprobante adjunto).
 *
 * - Al reportar se avisa por correo a la empresa dueña de la suscripción (su campo `mail`)
 *   con el comprobante adjunto, y el aviso deja de salir mientras se revisa.
 * - La revisión no se cierra a mano: si la controladora registra el cobro, la fecha límite
 *   del aviso cambia y el reporte deja de aplicar solo. Si el comprobante no sirve, la
 *   controladora lo RECHAZA desde Suscripciones (el aviso vuelve y el usuario recibe el motivo).
 * - Tope: pasados DIAS_REVISION días sin respuesta el aviso vuelve a salir, para que no
 *   quede oculto para siempre si el correo se perdió.
 */
class SuscripcionReportePagoService
{
    /** Días máximos que el aviso queda oculto por un reporte en revisión (decisión del usuario, 13-10-2026). */
    public const DIAS_REVISION = 3;

    /** Días que el listado de la controladora muestra hacia atrás. */
    public const DIAS_LISTADO = 60;

    public const MAX_MENSAJE = 2000;
    public const MAX_BYTES   = 5 * 1024 * 1024;

    /** Extensión permitida => tipos MIME aceptados (verificados con finfo, no con lo que manda el navegador). */
    private const TIPOS = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    private const MODULO_GUARDADO = 'suscripcion_reporte_pago';

    private SuscripcionReportePagoRepository $repo;

    public function __construct(?SuscripcionReportePagoRepository $repo = null)
    {
        $this->repo = $repo ?? new SuscripcionReportePagoRepository();
    }

    public static function dirArchivos(): string
    {
        return MVC_ROOT . '/storage/suscripciones_reportes';
    }

    public function disponible(): bool
    {
        try {
            return $this->repo->disponible();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Estado del último reporte para esa fecha límite, tal como lo necesita el aviso:
     *   ['estado' => 'en_revision'|'rechazado'|'sin_respuesta', 'fecha', 'usuario', 'motivo', 'archivo']
     * 'sin_respuesta' = estaba en revisión pero pasó el tope: el aviso vuelve a salir.
     * null si no hay reporte (o la tabla aún no existe).
     */
    public function estadoReporte(int $idEmpresa, string $fechaLimite): ?array
    {
        if ($idEmpresa <= 0 || $fechaLimite === '' || !$this->disponible()) {
            return null;
        }
        $r = $this->repo->getUltimoPorFecha($idEmpresa, $fechaLimite, self::DIAS_REVISION);
        if ($r === null) {
            return null;
        }
        $estado = (string) $r['estado'];
        if ($estado === 'en_revision' && !$r['dentro_tope']) {
            $estado = 'sin_respuesta';
        }
        return [
            'estado'  => $estado,
            'fecha'   => substr((string) $r['created_at'], 0, 19),
            'usuario' => (string) ($r['usuario'] ?? ''),
            'motivo'  => $estado === 'rechazado' ? (string) ($r['motivo_rechazo'] ?? '') : '',
            'archivo' => (string) ($r['archivo_nombre'] ?? ''),
        ];
    }

    /**
     * Registra el reporte, avisa por correo a la controladora y oculta el aviso.
     *
     * @param array|null $archivo Entrada de $_FILES (o null si no adjuntó).
     * @return array{id:int, ya_existia:bool, correo_enviado:bool}
     */
    public function reportar(int $idEmpresa, int $idUsuario, string $mensaje, ?array $archivo, $token): array
    {
        if (!$this->disponible()) {
            throw new \RuntimeException('El envío de comprobantes aún no está habilitado. Comunícate con nosotros por otro medio.');
        }

        $mensaje = trim(str_replace("\r\n", "\n", $mensaje));
        if (mb_strlen($mensaje) > self::MAX_MENSAJE) {
            throw new \InvalidArgumentException('El mensaje no puede pasar de ' . self::MAX_MENSAJE . ' caracteres.');
        }
        $hayArchivo = $archivo !== null && (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($mensaje === '' && !$hayArchivo) {
            throw new \InvalidArgumentException('Escribe un mensaje o adjunta el comprobante de pago.');
        }

        // En vivo: el reporte queda atado a la fecha límite que el usuario está viendo.
        $vig = (new VigenciaSuscripcionService())->estado($idEmpresa, false);
        if ($vig === null || empty($vig['fecha'])) {
            throw new \RuntimeException('Tu empresa no tiene una suscripción pendiente de pago.');
        }

        $guardado = $hayArchivo ? $this->guardarArchivo($archivo, $idEmpresa) : null;

        $guardadoUnico = new GuardadoUnicoService();
        try {
            $this->repo->beginTransaction();

            // Reintento tras perderse la respuesta: no crear otro reporte ni otro correo.
            $previo = $guardadoUnico->previo($token, $idEmpresa, self::MODULO_GUARDADO);
            if ($previo !== null) {
                $this->repo->rollBack();
                $this->borrarArchivo($guardado);
                return ['id' => (int) $previo['id_registro'], 'ya_existia' => true, 'correo_enviado' => true];
            }

            $datos = [
                'id_empresa'              => $idEmpresa,
                'id_empresa_controladora' => (int) ($vig['id_empresa_controladora'] ?? 0),
                'id_suscripcion'          => (int) ($vig['id_suscripcion'] ?? 0),
                'fecha_limite'            => (string) $vig['fecha'],
                'fecha_periodo'           => (string) ($vig['fecha_periodo'] ?? ''),
                'documento'               => (string) ($vig['documento'] ?? ''),
                'saldo'                   => $vig['saldo'] ?? null,
                'mensaje'                 => $mensaje,
                'archivo_nombre'          => $guardado['nombre'] ?? null,
                'archivo_ruta'            => $guardado['ruta'] ?? null,
                'archivo_mime'            => $guardado['mime'] ?? null,
                'archivo_tamano'          => $guardado['tamano'] ?? null,
                'created_by'              => $idUsuario,
            ];
            $id = $this->repo->insertar($datos);

            (new LogSistemaService())->registrar(
                $idUsuario, $idEmpresa, 'REPORTAR_PAGO_SUSCRIPCION', 'suscripciones_reportes_pago', $id, null, $datos
            );
            $guardadoUnico->registrar($token, $idEmpresa, self::MODULO_GUARDADO, $id, null, $idUsuario);

            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            $this->borrarArchivo($guardado);
            throw $e;
        }

        VigenciaSuscripcionService::invalidar($idEmpresa);

        // Correo fuera de la transacción: si falla, el reporte queda igual (la controladora
        // lo ve en Suscripciones → Pagos reportados) y se anota el error.
        $enviado = false;
        $error   = null;
        try {
            [$enviado, $error] = $this->correoControladora($id, $datos, $vig, $guardado);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        try {
            $this->repo->marcarCorreo($id, $enviado, $error);
        } catch (\Throwable $e) {
            error_log('SuscripcionReportePagoService::marcarCorreo ' . $e->getMessage());
        }

        return ['id' => $id, 'ya_existia' => false, 'correo_enviado' => $enviado];
    }

    /** Listado de la controladora (pantalla Suscripciones → Pagos reportados). */
    public function listarParaControladora(int $idControladora): array
    {
        if (!$this->disponible()) {
            return [];
        }
        $rows = $this->repo->listarParaControladora($idControladora, self::DIAS_LISTADO, self::DIAS_REVISION);
        foreach ($rows as &$r) {
            if ($r['estado'] === 'en_revision' && !$r['dentro_tope']) {
                $r['estado'] = 'sin_respuesta';
            }
            unset($r['dentro_tope']);
            $r['tiene_archivo'] = !empty($r['archivo_nombre']);
        }
        unset($r);
        return $rows;
    }

    public function contarEnRevision(int $idControladora): int
    {
        return $this->disponible() ? $this->repo->contarEnRevision($idControladora, self::DIAS_REVISION) : 0;
    }

    /** Reporte + ruta absoluta del comprobante, solo si lo recibió esta controladora. */
    public function archivoParaControladora(int $id, int $idControladora): ?array
    {
        if (!$this->disponible()) {
            return null;
        }
        $r = $this->repo->getParaControladora($id, $idControladora);
        if ($r === null || empty($r['archivo_ruta'])) {
            return null;
        }
        $ruta = $this->rutaAbsoluta((string) $r['archivo_ruta']);
        return $ruta !== null ? ['ruta' => $ruta, 'nombre' => (string) $r['archivo_nombre'], 'mime' => (string) $r['archivo_mime']] : null;
    }

    /**
     * Rechaza un reporte en revisión: el aviso vuelve a salir a la empresa y se le
     * envía el motivo por correo (a quien reportó y al correo de la empresa).
     *
     * @return array{correo_enviado:bool}
     */
    public function rechazar(int $id, int $idControladora, string $motivo, int $idUsuario): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Indica el motivo del rechazo: se le enviará al cliente.');
        }
        if (mb_strlen($motivo) > self::MAX_MENSAJE) {
            throw new \InvalidArgumentException('El motivo no puede pasar de ' . self::MAX_MENSAJE . ' caracteres.');
        }

        try {
            $this->repo->beginTransaction();
            $antes = $this->repo->getParaControladora($id, $idControladora);
            if ($antes === null) {
                throw new \RuntimeException('El reporte no existe.');
            }
            if (!$this->repo->rechazar($id, $idControladora, $motivo, $idUsuario)) {
                throw new \RuntimeException('Este reporte ya fue rechazado.');
            }
            (new LogSistemaService())->registrar(
                $idUsuario, $idControladora, 'RECHAZAR_PAGO_SUSCRIPCION', 'suscripciones_reportes_pago', $id,
                ['estado' => $antes['estado']],
                ['estado' => 'rechazado', 'motivo_rechazo' => $motivo, 'id_empresa' => (int) $antes['id_empresa']]
            );
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        VigenciaSuscripcionService::invalidar((int) $antes['id_empresa']);

        $enviado = false;
        try {
            $enviado = $this->correoRechazo($antes, $motivo);
        } catch (\Throwable $e) {
            error_log('SuscripcionReportePagoService::correoRechazo ' . $e->getMessage());
        }
        return ['correo_enviado' => $enviado];
    }

    // ── Archivos ──────────────────────────────────────────────────────────

    /** @return array{nombre:string, ruta:string, mime:string, tamano:int} */
    private function guardarArchivo(array $f, int $idEmpresa): array
    {
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException('El archivo pesa demasiado (máximo 5 MB).');
        }
        if ($err !== UPLOAD_ERR_OK || empty($f['tmp_name']) || !is_uploaded_file((string) $f['tmp_name'])) {
            throw new \InvalidArgumentException('No se pudo recibir el archivo. Inténtalo de nuevo.');
        }
        $tamano = (int) ($f['size'] ?? 0);
        if ($tamano <= 0 || $tamano > self::MAX_BYTES) {
            throw new \InvalidArgumentException('El archivo pesa demasiado (máximo 5 MB).');
        }

        $nombre = $this->nombreSeguro((string) ($f['name'] ?? 'comprobante'));
        $ext    = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $mime   = (string) (@mime_content_type((string) $f['tmp_name']) ?: '');
        if (!isset(self::TIPOS[$ext]) || !in_array($mime, self::TIPOS[$ext], true)) {
            throw new \InvalidArgumentException('Adjunta el comprobante en PDF o como imagen (JPG, PNG o WEBP).');
        }

        $sub = $idEmpresa . '/' . date('Ym');
        $dir = self::dirArchivos() . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }
        $archivo = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $archivo)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        return ['nombre' => $nombre, 'ruta' => $sub . '/' . $archivo, 'mime' => $mime, 'tamano' => $tamano];
    }

    private function borrarArchivo(?array $guardado): void
    {
        if ($guardado !== null && ($abs = $this->rutaAbsoluta($guardado['ruta'])) !== null) {
            @unlink($abs);
        }
    }

    /** Ruta absoluta dentro de la carpeta de comprobantes; null si sale de ella o no existe. */
    private function rutaAbsoluta(string $relativa): ?string
    {
        $base = realpath(self::dirArchivos());
        $abs  = realpath(self::dirArchivos() . '/' . ltrim($relativa, '/'));
        if ($base === false || $abs === false || !str_starts_with($abs, $base . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return null;
        }
        return $abs;
    }

    private function nombreSeguro(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = preg_replace('/[^\w.\- ()áéíóúÁÉÍÓÚñÑ]/u', '_', $nombre) ?? 'comprobante';
        $nombre = trim($nombre, ' .');
        return mb_substr($nombre !== '' ? $nombre : 'comprobante', 0, 150);
    }

    // ── Correos ───────────────────────────────────────────────────────────

    /** Correos válidos de un campo que puede traer varios (coma, punto y coma o espacio). */
    private function correos(?string $campo): array
    {
        $lista = preg_split('/[\s,;]+/', (string) $campo) ?: [];
        return array_values(array_unique(array_filter(
            array_map('trim', $lista),
            static fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false
        )));
    }

    /** @return array{0:bool, 1:?string} */
    private function correoControladora(int $id, array $datos, array $vig, ?array $guardado): array
    {
        $idCtrl = (int) $datos['id_empresa_controladora'];
        $ctrl   = $idCtrl > 0 ? $this->repo->getContactoEmpresa($idCtrl) : null;
        $dest   = $this->correos($ctrl['mail'] ?? null);
        if (empty($dest)) {
            return [false, 'La empresa que controla las suscripciones no tiene correo registrado.'];
        }

        $empresa = $this->repo->getContactoEmpresa((int) $datos['id_empresa']) ?? [];
        $usuario = $this->repo->getUsuario((int) $datos['created_by']) ?? [];

        $data = [
            'id'            => $id,
            'empresa'       => (string) ($empresa['nombre'] ?? ''),
            'ruc'           => (string) ($empresa['ruc'] ?? ''),
            'empresa_mail'  => (string) ($empresa['mail'] ?? ''),
            'usuario'       => (string) ($usuario['nombre'] ?? ''),
            'usuario_mail'  => (string) ($usuario['mail'] ?? ''),
            'mensaje'       => $datos['mensaje'],
            'archivo'       => $guardado['nombre'] ?? '',
            'estado'        => (string) ($vig['estado'] ?? ''),
            'fecha_limite'  => (string) $datos['fecha_limite'],
            'fecha_periodo' => (string) $datos['fecha_periodo'],
            'documento'     => (string) $datos['documento'],
            'saldo'         => $datos['saldo'],
            'dias_revision' => self::DIAS_REVISION,
            'url'           => url_absoluta('modulos/suscripciones'),
        ];
        $html = $this->plantilla('suscripcion_reporte_pago', $data);

        $adjuntos = [];
        if ($guardado !== null && ($abs = $this->rutaAbsoluta($guardado['ruta'])) !== null) {
            $adjuntos[$abs] = $guardado['nombre'];
        }

        require_once MVC_APP . '/helpers/mail.php';
        $asunto = 'Pago de suscripción reportado — ' . ($data['empresa'] !== '' ? $data['empresa'] : 'cliente') . ($data['ruc'] !== '' ? ' (' . $data['ruc'] . ')' : '');
        $ok = enviar_correo_reporte($dest, $asunto, $html, $adjuntos);
        return [$ok, $ok ? null : (string) ($GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error desconocido al enviar el correo.')];
    }

    private function correoRechazo(array $reporte, string $motivo): bool
    {
        $dest = array_values(array_unique(array_merge(
            $this->correos($reporte['usuario_mail'] ?? null),
            $this->correos($reporte['empresa_mail'] ?? null)
        )));
        if (empty($dest)) {
            return false;
        }
        $data = [
            'empresa'      => (string) ($reporte['empresa'] ?? ''),
            'usuario'      => (string) ($reporte['usuario'] ?? ''),
            'fecha'        => substr((string) $reporte['created_at'], 0, 19),
            'fecha_limite' => (string) $reporte['fecha_limite'],
            'archivo'      => (string) ($reporte['archivo_nombre'] ?? ''),
            'motivo'       => $motivo,
            'url'          => url_absoluta(''),
        ];
        $html = $this->plantilla('suscripcion_reporte_rechazado', $data);

        require_once MVC_APP . '/helpers/mail.php';
        return enviar_correo_reporte($dest, 'Tu reporte de pago de la suscripción fue rechazado', $html);
    }

    private function plantilla(string $nombre, array $data): string
    {
        ob_start();
        require MVC_APP . '/views/emails/' . $nombre . '.php';
        return (string) ob_get_clean();
    }
}
