<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Services\InstagramApiService;

/**
 * CRM: reglas de envío por Instagram (diseño §7).
 *  - Mensaje directo: dentro de las 24 h siguientes al último mensaje del cliente.
 *    Entre 24 h y 7 días, solo si la cuenta tiene «Human Agent» y siempre por una
 *    persona (etiqueta HUMAN_AGENT). Después, no se puede escribir.
 *  - Respuesta privada a un comentario: una sola por comentario, dentro de 7 días.
 *  - Respuesta pública a un comentario: sin ventana.
 */
class CrmMensajeRules
{
    public const HORAS_VENTANA = 24;
    public const DIAS_HUMANO   = 7;

    /**
     * Estado de la ventana para mostrarla y validarla.
     * @return array{puede:bool, tag:?string, segundos_restantes:int, motivo:?string}
     */
    public function ventana(?string $ultimaEntrada, bool $ventanaHumana, ?int $ahora = null): array
    {
        $ahora ??= time();
        if (!$ultimaEntrada) {
            return ['puede' => false, 'tag' => null, 'segundos_restantes' => 0,
                    'motivo' => 'El cliente todavía no ha escrito por mensaje directo; Instagram no permite escribirle primero.'];
        }
        $desde = $ahora - strtotime($ultimaEntrada);
        $limite24 = self::HORAS_VENTANA * 3600;
        if ($desde <= $limite24) {
            return ['puede' => true, 'tag' => null, 'segundos_restantes' => $limite24 - $desde, 'motivo' => null];
        }
        $limite7 = self::DIAS_HUMANO * 86400;
        if ($ventanaHumana && $desde <= $limite7) {
            return ['puede' => true, 'tag' => 'HUMAN_AGENT', 'segundos_restantes' => $limite7 - $desde, 'motivo' => null];
        }
        return ['puede' => false, 'tag' => null, 'segundos_restantes' => 0,
                'motivo' => 'Han pasado más de 24 h desde el último mensaje del cliente. Instagram no permite escribirle hasta que vuelva a escribir.'];
    }

    public function validarTexto(string $texto): string
    {
        $texto = trim(str_replace("\r\n", "\n", $texto));
        if ($texto === '') {
            throw new \InvalidArgumentException('Escriba el mensaje.');
        }
        if (strlen($texto) > InstagramApiService::MAX_BYTES_MENSAJE) {
            throw new \InvalidArgumentException('El mensaje es demasiado largo para Instagram (máximo unos 1000 caracteres).');
        }
        return $texto;
    }

    /** Imágenes que Instagram acepta por mensaje directo (hasta 8 MB). */
    public const IMAGEN_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    public const IMAGEN_MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Valida el archivo subido por su CONTENIDO (no por el nombre ni por lo que
     * diga el navegador). Devuelve la extensión con que se guardará.
     */
    public function validarImagen(string $rutaTmp, int $bytes): string
    {
        if ($rutaTmp === '' || !is_file($rutaTmp) || $bytes <= 0) {
            throw new \InvalidArgumentException('No se recibió la imagen.');
        }
        if ($bytes > self::IMAGEN_MAX_BYTES) {
            throw new \InvalidArgumentException('La imagen supera 8 MB, el máximo que acepta Instagram.');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTmp);
        if (!isset(self::IMAGEN_MIME[$mime]) || @getimagesize($rutaTmp) === false) {
            throw new \InvalidArgumentException('Solo se pueden enviar imágenes JPG, PNG o GIF.');
        }
        return self::IMAGEN_MIME[$mime];
    }

    public function validarRespuestaPrivada(?array $comentario, bool $yaRespondido, ?int $ahora = null): void
    {
        $ahora ??= time();
        if (!$comentario || empty($comentario['id_externo'])) {
            throw new \InvalidArgumentException('No hay comentario del cliente al que responder.');
        }
        if ($yaRespondido) {
            throw new \InvalidArgumentException('Ese comentario ya recibió su respuesta privada (Instagram permite una sola).');
        }
        if ($ahora - strtotime((string) $comentario['fecha']) > self::DIAS_HUMANO * 86400) {
            throw new \InvalidArgumentException('Pasaron más de 7 días desde el comentario: Instagram ya no permite responderlo en privado.');
        }
    }
}
