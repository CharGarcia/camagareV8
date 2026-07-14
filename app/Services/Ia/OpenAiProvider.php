<?php
declare(strict_types=1);

namespace App\Services\Ia;

/**
 * Implementación del proveedor OpenAI (Chat Completions), vía curl nativo
 * (mismo patrón que PayphoneService::apiPostCajita()), sin agregar Guzzle.
 *
 * La API key nunca se loguea: los mensajes de error solo incluyen el
 * código HTTP y el mensaje devuelto por OpenAI, nunca los headers enviados.
 */
class OpenAiProvider implements IaProviderInterface
{
    private const URL_CHAT = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT_SEG = 60;

    public function chat(array $mensajes, string $promptSistema, string $apiKey, string $modelo): array
    {
        if (trim($apiKey) === '') {
            throw new \RuntimeException('La empresa no tiene configurada una API key de OpenAI.');
        }

        $payloadMensajes = [
            ['role' => 'system', 'content' => $promptSistema],
        ];
        foreach ($mensajes as $m) {
            $rol = ($m['rol'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $payloadMensajes[] = ['role' => $rol, 'content' => (string) ($m['contenido'] ?? '')];
        }

        $payload = [
            'model'       => $modelo,
            'messages'    => $payloadMensajes,
            'temperature' => 0.2,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::URL_CHAT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEG,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Error de conexión con OpenAI: ' . $curlErr);
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Respuesta inválida de OpenAI (HTTP ' . $httpCode . ').');
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException($this->mensajeError($httpCode, $data));
        }

        $contenido = (string) ($data['choices'][0]['message']['content'] ?? '');
        $tokensEntrada = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $tokensSalida  = (int) ($data['usage']['completion_tokens'] ?? 0);

        return [
            'contenido'      => $contenido,
            'tokens_entrada' => $tokensEntrada,
            'tokens_salida'  => $tokensSalida,
        ];
    }

    /**
     * Traduce el error de OpenAI a un mensaje claro en español, distinguiendo
     * los casos más comunes (sin saldo/cuota, key inválida, límite de tasa).
     */
    private function mensajeError(int $httpCode, array $data): string
    {
        $codigo  = (string) ($data['error']['code'] ?? '');
        $tipo    = (string) ($data['error']['type'] ?? '');
        $mensaje = (string) ($data['error']['message'] ?? '');

        if ($codigo === 'insufficient_quota' || $tipo === 'insufficient_quota') {
            return 'La cuenta de OpenAI de esta empresa se quedó sin saldo/cuota disponible. '
                 . 'Revise el saldo y método de pago en platform.openai.com → Settings → Billing, y vuelva a intentar.';
        }

        if ($httpCode === 401 || $codigo === 'invalid_api_key') {
            return 'La API key configurada no es válida o fue revocada. Verifíquela en la pestaña Configuración.';
        }

        if ($httpCode === 429 || $tipo === 'rate_limit_exceeded') {
            return 'OpenAI rechazó la solicitud por exceso de solicitudes en poco tiempo. Espere unos segundos e intente de nuevo.';
        }

        if ($codigo === 'model_not_found' || str_contains($mensaje, 'does not exist')) {
            return 'El modelo configurado ("' . $mensaje . '") no existe o la cuenta no tiene acceso a él. Cambie el modelo en Configuración (ej. gpt-4o-mini).';
        }

        return 'OpenAI: ' . ($mensaje !== '' ? $mensaje : 'Error desconocido (HTTP ' . $httpCode . ').');
    }
}
