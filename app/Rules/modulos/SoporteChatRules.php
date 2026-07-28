<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio del chat de soporte.
 */
class SoporteChatRules
{
    public const ESTADOS = ['espera', 'atendiendo', 'resuelta', 'cerrada'];

    private const MAX_LARGO_MENSAJE = 4000;
    private const MAX_LARGO_ASUNTO  = 200;

    /** Mensajes por minuto y por conversación (freno a doble-submit y a bucles del front). */
    public const LIMITE_MENSAJES_POR_MINUTO = 20;

    /** Tamaño máximo de un adjunto. */
    public const MAX_BYTES_ADJUNTO = 10485760; // 10 MB

    /**
     * Lista BLANCA de adjuntos: extensión => MIME(s) reales admitidos.
     * Solo lo que sirve para explicar un problema (captura, PDF, hoja de
     * cálculo, XML de un comprobante). Nada ejecutable, y la extensión se
     * contrasta con el MIME real del archivo, no con el que declara el cliente.
     */
    public const EXTENSIONES_ADJUNTO = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'xml'  => ['text/xml', 'application/xml'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** @throws \InvalidArgumentException */
    public function validarMensaje(string $contenido): void
    {
        if (trim($contenido) === '') {
            throw new \InvalidArgumentException('Escriba un mensaje.');
        }
        if (mb_strlen($contenido) > self::MAX_LARGO_MENSAJE) {
            throw new \InvalidArgumentException(
                'El mensaje es demasiado largo (máximo ' . self::MAX_LARGO_MENSAJE . ' caracteres).'
            );
        }
    }

    /** @throws \InvalidArgumentException */
    public function validarAsunto(string $asunto): void
    {
        if (mb_strlen($asunto) > self::MAX_LARGO_ASUNTO) {
            throw new \InvalidArgumentException(
                'El asunto es demasiado largo (máximo ' . self::MAX_LARGO_ASUNTO . ' caracteres).'
            );
        }
    }

    /** @throws \InvalidArgumentException */
    public function validarEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new \InvalidArgumentException('Estado de conversación no válido.');
        }
    }

    /** @throws \InvalidArgumentException */
    public function validarCalificacion(int $calificacion): void
    {
        if ($calificacion < 1 || $calificacion > 5) {
            throw new \InvalidArgumentException('La calificación debe estar entre 1 y 5.');
        }
    }

    /** @throws \RuntimeException */
    public function validarRateLimit(int $mensajesUltimoMinuto): void
    {
        if ($mensajesUltimoMinuto >= self::LIMITE_MENSAJES_POR_MINUTO) {
            throw new \RuntimeException('Demasiados mensajes en poco tiempo. Espere un momento antes de continuar.');
        }
    }

    /**
     * No se escribe en una conversación ya cerrada: hay que reabrirla primero.
     * @throws \RuntimeException
     */
    public function validarConversacionAbierta(array $conversacion): void
    {
        if (($conversacion['estado'] ?? '') === 'cerrada') {
            throw new \RuntimeException('Esta conversación está cerrada. Abra una nueva consulta.');
        }
    }

    /** @throws \InvalidArgumentException */
    public function validarRespuestaRapida(string $titulo, string $contenido): void
    {
        if (trim($titulo) === '') {
            throw new \InvalidArgumentException('El título es obligatorio.');
        }
        if (mb_strlen($titulo) > 100) {
            throw new \InvalidArgumentException('El título es demasiado largo (máximo 100 caracteres).');
        }
        if (trim($contenido) === '') {
            throw new \InvalidArgumentException('El contenido es obligatorio.');
        }
        if (mb_strlen($contenido) > self::MAX_LARGO_MENSAJE) {
            throw new \InvalidArgumentException('El contenido es demasiado largo.');
        }
    }

    /**
     * Valida el archivo adjunto: peso, extensión y MIME real.
     *
     * La lista es blanca a propósito: se acepta lo que sirve para explicar un
     * problema (captura, PDF, hoja de cálculo, XML de un comprobante) y nada
     * ejecutable. La extensión se comprueba contra el MIME real del archivo,
     * no contra el que declara el navegador.
     *
     * @param array<string,mixed> $file entrada de $_FILES
     * @throws \InvalidArgumentException
     */
    public function validarAdjunto(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('Debe seleccionar un archivo.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException(
                'El archivo excede el tamaño máximo permitido por el servidor (revise upload_max_filesize en php.ini).'
            );
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }

        $tam = (int) ($file['size'] ?? 0);
        if ($tam <= 0) {
            throw new \InvalidArgumentException('El archivo está vacío.');
        }
        if ($tam > self::MAX_BYTES_ADJUNTO) {
            throw new \InvalidArgumentException(
                'El archivo supera el máximo de ' . (int) (self::MAX_BYTES_ADJUNTO / 1048576) . ' MB.'
            );
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::EXTENSIONES_ADJUNTO[$ext])) {
            throw new \InvalidArgumentException(
                'Tipo de archivo no permitido. Se aceptan: ' . implode(', ', array_keys(self::EXTENSIONES_ADJUNTO)) . '.'
            );
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = '';
        if ($tmp !== '' && is_uploaded_file($tmp) && function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string) finfo_file($fi, $tmp);
                finfo_close($fi);
            }
        }
        if ($mime !== '' && !in_array($mime, self::EXTENSIONES_ADJUNTO[$ext], true)) {
            throw new \InvalidArgumentException('El contenido del archivo no corresponde a su extensión .' . $ext . '.');
        }
    }

    /**
     * Normaliza y valida la configuración global del chat.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed> datos listos para guardar
     * @throws \InvalidArgumentException
     */
    public function validarConfig(array $data): array
    {
        $dias = array_values(array_filter(
            array_map('trim', explode(',', (string) ($data['dias_atencion'] ?? ''))),
            static fn ($d) => $d !== '' && ctype_digit($d) && (int) $d >= 1 && (int) $d <= 7
        ));
        if ($dias === []) {
            throw new \InvalidArgumentException('Seleccione al menos un día de atención.');
        }

        $horaInicio = $this->normalizarHora((string) ($data['hora_inicio'] ?? ''), '08:00');
        $horaFin    = $this->normalizarHora((string) ($data['hora_fin'] ?? ''), '18:00');
        if ($horaFin <= $horaInicio) {
            throw new \InvalidArgumentException('La hora de fin debe ser posterior a la de inicio.');
        }

        $correo = trim((string) ($data['correo_alertas'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo para alertas no es válido.');
        }

        $minutos = (int) ($data['minutos_alerta_sin_atender'] ?? 0);
        if ($minutos < 0 || $minutos > 1440) {
            throw new \InvalidArgumentException('Los minutos de alerta deben estar entre 0 y 1440.');
        }

        $diasArchivar = (int) ($data['dias_archivar_cerradas'] ?? 0);
        if ($diasArchivar < 0 || $diasArchivar > 3650) {
            throw new \InvalidArgumentException('Los días para archivar deben estar entre 0 y 3650.');
        }

        return [
            'activo'                     => !empty($data['activo']),
            'copiloto_activo'            => !empty($data['copiloto_activo']),
            'mensaje_bienvenida'         => mb_substr(trim((string) ($data['mensaje_bienvenida'] ?? '')), 0, 500),
            'mensaje_fuera_horario'      => mb_substr(trim((string) ($data['mensaje_fuera_horario'] ?? '')), 0, 500),
            'dias_atencion'              => implode(',', $dias),
            'hora_inicio'                => $horaInicio,
            'hora_fin'                   => $horaFin,
            'minutos_alerta_sin_atender' => $minutos,
            'correo_alertas'             => $correo !== '' ? $correo : null,
            'dias_archivar_cerradas'     => $diasArchivar,
        ];
    }

    private function normalizarHora(string $valor, string $porDefecto): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) === 1 ? $valor : $porDefecto;
    }

    /**
     * ¿Estamos dentro del horario de atención configurado?
     *
     * dias_atencion se guarda como texto '1,2,3,4,5' con la convención ISO
     * (1=lunes … 7=domingo), la misma que devuelve date('N').
     */
    public function esHorarioAtencion(array $config): bool
    {
        $dias = array_filter(array_map('trim', explode(',', (string) ($config['dias_atencion'] ?? ''))), 'strlen');
        if ($dias === []) {
            return true; // sin horario configurado, siempre disponible
        }

        if (!in_array(date('N'), $dias, true)) {
            return false;
        }

        $ahora  = date('H:i:s');
        $inicio = (string) ($config['hora_inicio'] ?? '00:00:00');
        $fin    = (string) ($config['hora_fin']    ?? '23:59:59');

        return $ahora >= $inicio && $ahora <= $fin;
    }
}
