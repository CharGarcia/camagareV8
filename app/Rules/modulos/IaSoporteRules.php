<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class IaSoporteRules
{
    public const PROVEEDORES_SOPORTADOS = ['openai'];
    private const MAX_BYTES_PDF = 20971520; // 20 MB
    private const MAX_LARGO_PREGUNTA = 4000;
    public const LIMITE_MENSAJES_POR_MINUTO = 12;

    /**
     * Valida los datos de configuración BYOK (proveedor, modelo, api key).
     * @throws \InvalidArgumentException
     */
    public function validarConfig(array $data, bool $esNueva): void
    {
        $proveedor = trim((string) ($data['proveedor'] ?? ''));
        if (!in_array($proveedor, self::PROVEEDORES_SOPORTADOS, true)) {
            throw new \InvalidArgumentException('Proveedor de IA no soportado.');
        }
        if (trim((string) ($data['modelo_chat'] ?? '')) === '') {
            throw new \InvalidArgumentException('Debe indicar el modelo a usar.');
        }
        if ($esNueva && trim((string) ($data['api_key'] ?? '')) === '') {
            throw new \InvalidArgumentException('Debe ingresar la API key del proveedor.');
        }
    }

    /**
     * Valida el archivo subido antes de guardarlo (tamaño, extensión, MIME real).
     * @param array<string,mixed> $file entrada de $_FILES['archivo']
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function validarArchivoPdf(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('Debe seleccionar un archivo PDF.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException(
                'El archivo excede el tamaño máximo permitido por el servidor (revise upload_max_filesize / post_max_size en php.ini).'
            );
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }

        $tam = (int) ($file['size'] ?? 0);
        if ($tam <= 0) {
            throw new \InvalidArgumentException('El archivo está vacío.');
        }
        if ($tam > self::MAX_BYTES_PDF) {
            throw new \InvalidArgumentException(
                'El PDF supera el máximo de ' . (int) (self::MAX_BYTES_PDF / 1048576) . ' MB.'
            );
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new \InvalidArgumentException('Solo se permiten archivos PDF.');
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
        if ($mime !== 'application/pdf') {
            throw new \InvalidArgumentException('El archivo no es un PDF válido.');
        }
    }

    /**
     * Valida el texto de una pregunta del chat.
     * @throws \InvalidArgumentException
     */
    public function validarPregunta(string $pregunta): void
    {
        if (trim($pregunta) === '') {
            throw new \InvalidArgumentException('Escriba una pregunta.');
        }
        if (mb_strlen($pregunta) > self::MAX_LARGO_PREGUNTA) {
            throw new \InvalidArgumentException('La pregunta es demasiado larga (máximo ' . self::MAX_LARGO_PREGUNTA . ' caracteres).');
        }
    }

    /**
     * Límite simple de mensajes por minuto por conversación, para evitar
     * loops de doble-submit consumiendo la API key de la empresa.
     * @throws \RuntimeException
     */
    public function validarRateLimit(int $mensajesUltimoMinuto): void
    {
        if ($mensajesUltimoMinuto >= self::LIMITE_MENSAJES_POR_MINUTO) {
            throw new \RuntimeException('Demasiadas consultas en poco tiempo. Espere un momento antes de continuar.');
        }
    }
}
