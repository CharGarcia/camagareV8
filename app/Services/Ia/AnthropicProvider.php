<?php
declare(strict_types=1);

namespace App\Services\Ia;

use Anthropic\Client;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\OverloadedException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Psr\Http\Client\ClientInterface;

/**
 * Proveedor Anthropic (Claude) con el SDK oficial `anthropic-ai/sdk`.
 *
 * - BYOK: la clave es la de la empresa (ia_config); nunca se registra ni se
 *   incluye en los mensajes de error.
 * - Respaldo ante rechazos: en los modelos que lo admiten se pide el respaldo
 *   del servidor (`fallbacks: "default"`), así un rechazo por política se
 *   reintenta en otro modelo sin código extra.
 * - El prompt del sistema va con caché (cache_control): en una conversación
 *   larga del bot se cobra una sola vez.
 * - Los bloques de la respuesta (incluido el razonamiento) se devuelven al
 *   modelo tal cual en el ciclo de herramientas, como exige la API.
 */
class AnthropicProvider implements IaProviderInterface, IaProviderHerramientasInterface, IaProviderJsonInterface
{
    public const MODELO_DEFECTO = 'claude-opus-5-5';

    private const URL_API = 'https://api.anthropic.com';
    private const BETA_RESPALDO ='server-side-fallback-2026-07-01';
    private const TIMEOUT_SEG = 60.0;

    /** Modelos con respaldo del servidor en su forma "default". */
    private const MODELOS_RESPALDO = ['claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1', 'claude-sonnet-5-5'];

    /** USD por millón de tokens: entrada, salida, lectura de caché. Referencial (para el consumo del mes). */
    private const PRECIOS = [
        'claude-opus-5-5'   => [4.00, 20.00, 0.20],
        'claude-opus-5'     => [5.00, 25.00, 0.50],
        'claude-sonnet-5-5' => [2.00, 10.00, 0.20],
        'claude-sonnet-5'   => [2.00, 10.00, 0.20],
        'claude-haiku-5-5'  => [0.10, 0.50, 0.01],
        'claude-fable-5-1'  => [10.00, 50.00, 0.25],
    ];

    /** Niveles de esfuerzo válidos. */
    public const ESFUERZOS = ['low', 'medium', 'high', 'xhigh', 'max'];

    /** @param ClientInterface|null $transporte Solo pruebas: cliente HTTP falso. */
    public function __construct(private ?ClientInterface $transporte = null)
    {
    }

    // ── Chat simple (IA Soporte) ─────────────────────────────────────────────

    public function chat(array $mensajes, string $promptSistema, string $apiKey, string $modelo): array
    {
        $r = $this->conversar($promptSistema, $mensajes, [], static fn() => '', [
            'modelo' => $modelo, 'api_key' => $apiKey, 'max_vueltas' => 1,
        ]);
        if ($r['rechazado']) {
            throw new \RuntimeException('Claude no pudo responder esta consulta. Reformúlela e intente de nuevo.');
        }
        return ['contenido' => $r['texto'], 'tokens_entrada' => $r['tokens_entrada'], 'tokens_salida' => $r['tokens_salida']];
    }

    // ── Conversación con herramientas (asistente virtual del CRM) ───────────

    public function conversar(string $promptSistema, array $historial, array $herramientas, callable $ejecutar, array $opciones): array
    {
        $apiKey = trim((string) ($opciones['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('La empresa no tiene configurada una clave de Anthropic.');
        }
        $modelo = trim((string) ($opciones['modelo'] ?? '')) ?: self::MODELO_DEFECTO;
        $maxVueltas = max(1, min(8, (int) ($opciones['max_vueltas'] ?? 4)));
        $esfuerzo = $opciones['esfuerzo'] ?? null;

        $mensajes = self::mensajes($historial);
        if (!$mensajes) {
            throw new \InvalidArgumentException('No hay mensajes para responder.');
        }
        $tools = array_map(static fn(array $h) => [
            'name' => $h['nombre'], 'description' => $h['descripcion'], 'inputSchema' => $h['esquema'],
        ], $herramientas);

        $parametros = [
            'model'     => $modelo,
            'maxTokens' => max(256, min(16000, (int) ($opciones['max_tokens'] ?? 2000))),
            // Lo fijo con caché; lo que cambia en cada llamada va en un bloque aparte, después.
            'system'    => array_values(array_filter([
                ['type' => 'text', 'text' => $promptSistema, 'cacheControl' => ['type' => 'ephemeral']],
                trim((string) ($opciones['sistema_variable'] ?? '')) !== ''
                    ? ['type' => 'text', 'text' => trim((string) $opciones['sistema_variable'])] : null,
            ])),
            'requestOptions' => ['timeout' => self::TIMEOUT_SEG, 'maxRetries' => 2],
        ];
        if ($tools) {
            $parametros['tools'] = $tools;
        }
        if (is_string($esfuerzo) && in_array($esfuerzo, self::ESFUERZOS, true) && self::admiteEsfuerzo($modelo)) {
            $parametros['outputConfig'] = ['effort' => $esfuerzo];
        }
        if (in_array($modelo, self::MODELOS_RESPALDO, true)) {
            $parametros['fallbacks'] = 'default';
            $parametros['betas'] = [self::BETA_RESPALDO];
        }

        $cliente = $this->cliente($apiKey);
        $usadas = [];
        $tokIn = $tokOut = $tokCache = 0;
        $costo = 0.0;
        $texto = '';
        $rechazado = false;
        $motivo = 'end_turn';
        $modeloUsado = $modelo;

        for ($vuelta = 1; $vuelta <= $maxVueltas; $vuelta++) {
            $resp = $this->llamar($cliente, $parametros + ['messages' => $mensajes]);
            $modeloUsado = (string) $resp->model;
            $u = $resp->usage;
            $tokIn += $u->inputTokens + (int) $u->cacheCreationInputTokens;
            $tokOut += $u->outputTokens;
            $tokCache += (int) $u->cacheReadInputTokens;
            $costo += self::costo($modeloUsado, $u->inputTokens + (int) $u->cacheCreationInputTokens, $u->outputTokens, (int) $u->cacheReadInputTokens);
            $motivo = (string) $resp->stopReason;

            if ($motivo === 'refusal') {
                $rechazado = true;
                $texto = '';
                break;
            }
            $texto = self::textoDe($resp->content);
            if ($motivo !== 'tool_use') {
                break;
            }
            if ($vuelta === $maxVueltas) {
                $motivo = 'max_vueltas';   // pidió otra herramienta sin terminar: el llamador decide (traspaso)
                break;
            }
            // Todos los resultados en UN mensaje de usuario, como pide la API.
            $resultados = [];
            foreach ($resp->content as $bloque) {
                if (!$bloque instanceof BetaToolUseBlock) {
                    continue;
                }
                $usadas[] = $bloque->name;
                try {
                    $salida = (string) $ejecutar($bloque->name, is_array($bloque->input) ? $bloque->input : []);
                    $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id, 'content' => $salida];
                } catch (\Throwable $e) {
                    $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id, 'isError' => true,
                        'content' => $e instanceof \InvalidArgumentException ? $e->getMessage() : 'La herramienta falló.'];
                }
            }
            $mensajes[] = ['role' => 'assistant', 'content' => $resp->content];
            $mensajes[] = ['role' => 'user', 'content' => $resultados];
        }

        return [
            'texto' => trim($texto), 'rechazado' => $rechazado, 'motivo_fin' => $motivo, 'herramientas' => $usadas,
            'tokens_entrada' => $tokIn, 'tokens_salida' => $tokOut, 'tokens_cache' => $tokCache,
            'costo_estimado' => round($costo, 6), 'modelo' => $modeloUsado,
        ];
    }

    // ── Respuesta estructurada con imágenes (redacción de publicaciones) ─────

    /** Tope de imágenes por llamada (cada una suma tokens de entrada). */
    public const MAX_IMAGENES = 10;
    private const MIME_IMAGEN = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function generarJson(string $promptSistema, string $instruccion, array $imagenes, array $esquema, array $opciones): array
    {
        $apiKey = trim((string) ($opciones['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('La empresa no tiene configurada una clave de Anthropic.');
        }
        $modelo = trim((string) ($opciones['modelo'] ?? '')) ?: self::MODELO_DEFECTO;

        // Imágenes primero y luego la instrucción, cada imagen con su etiqueta (para referirse a ella).
        $contenido = [];
        foreach (array_slice($imagenes, 0, self::MAX_IMAGENES) as $img) {
            $mime = (string) ($img['mime'] ?? '');
            if (!in_array($mime, self::MIME_IMAGEN, true) || !is_file((string) $img['ruta'])) {
                continue;
            }
            $contenido[] = ['type' => 'text', 'text' => (string) $img['etiqueta']];
            $contenido[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mime,
                'data' => base64_encode((string) file_get_contents((string) $img['ruta']))]];
        }
        $contenido[] = ['type' => 'text', 'text' => $instruccion];

        $p = [
            'model'     => $modelo,
            'maxTokens' => max(512, min(16000, (int) ($opciones['max_tokens'] ?? 4000))),
            'system'    => [['type' => 'text', 'text' => $promptSistema, 'cacheControl' => ['type' => 'ephemeral']]],
            'messages'  => [['role' => 'user', 'content' => $contenido]],
            'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => $esquema]],
            'requestOptions' => ['timeout' => self::TIMEOUT_SEG, 'maxRetries' => 2],
        ];
        $esfuerzo = $opciones['esfuerzo'] ?? null;
        if (is_string($esfuerzo) && in_array($esfuerzo, self::ESFUERZOS, true) && self::admiteEsfuerzo($modelo)) {
            $p['outputConfig']['effort'] = $esfuerzo;
        }
        if (in_array($modelo, self::MODELOS_RESPALDO, true)) {
            $p['fallbacks'] = 'default';
            $p['betas'] = [self::BETA_RESPALDO];
        }

        $resp = $this->llamar($this->cliente($apiKey), $p);
        $u = $resp->usage;
        $entrada = $u->inputTokens + (int) $u->cacheCreationInputTokens;
        $rechazado = $resp->stopReason === 'refusal';
        $datos = [];
        if (!$rechazado) {
            if ($resp->stopReason === 'max_tokens') {
                throw new \RuntimeException('La respuesta de la IA quedó incompleta. Pida un texto más corto.');
            }
            $json = json_decode(self::textoDe($resp->content), true);
            if (!is_array($json)) {
                throw new \RuntimeException('La IA no devolvió una propuesta válida. Intente de nuevo.');
            }
            $datos = $json;
        }
        return [
            'datos' => $datos, 'rechazado' => $rechazado,
            'tokens_entrada' => $entrada, 'tokens_salida' => $u->outputTokens, 'tokens_cache' => (int) $u->cacheReadInputTokens,
            'costo_estimado' => round(self::costo((string) $resp->model, $entrada, $u->outputTokens, (int) $u->cacheReadInputTokens), 6),
            'modelo' => (string) $resp->model,
        ];
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    private function cliente(string $apiKey): Client
    {
        $opc = $this->transporte ? ['transporter' => $this->transporte] : null;
        // URL fija: el SDK también lee ANTHROPIC_BASE_URL del entorno, y la clave
        // de la empresa no debe poder ir a otro servidor por una variable olvidada.
        return new Client(apiKey: $apiKey, baseUrl: self::URL_API, requestOptions: $opc);
    }

    private function llamar(Client $cliente, array $p)
    {
        try {
            return $cliente->beta->messages->create(...$p);
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new \RuntimeException('La clave de Anthropic configurada no es válida o no tiene permiso. Revísela en la configuración de IA.');
        } catch (NotFoundException $e) {
            throw new \RuntimeException('El modelo configurado no existe o la cuenta de Anthropic no tiene acceso a él.');
        } catch (RateLimitException $e) {
            throw new \RuntimeException('Anthropic pidió esperar: se superó el límite de solicitudes de la cuenta.');
        } catch (OverloadedException $e) {
            throw new \RuntimeException('El servicio de Anthropic está saturado en este momento. Se reintentará.');
        } catch (BadRequestException $e) {
            $m = (string) $e->getMessage();
            if (stripos($m, 'credit') !== false || stripos($m, 'billing') !== false) {
                throw new \RuntimeException('La cuenta de Anthropic de la empresa no tiene saldo. Revise la facturación en console.anthropic.com.');
            }
            // El motivo de Anthropic (p. ej. «output_config.format.schema: …») es lo único que
            // permite corregir: se muestra recortado y sin nada que parezca una clave.
            $motivo = self::motivoAnthropic($m);
            throw new \RuntimeException('Anthropic rechazó la solicitud (datos no válidos)' . ($motivo !== '' ? ': ' . $motivo : '.'));
        } catch (APIStatusException $e) {
            throw new \RuntimeException('Anthropic respondió con un error. Se reintentará.');
        } catch (APIConnectionException $e) {
            throw new \RuntimeException('No se pudo conectar con Anthropic (red o tiempo de espera).');
        }
    }

    /**
     * Historial del sistema → mensajes de la API: roles user/assistant
     * alternados, empezando por user (se juntan los consecutivos del mismo rol
     * y se descarta un assistant inicial).
     */
    public static function mensajes(array $historial): array
    {
        $out = [];
        foreach ($historial as $m) {
            $rol = ($m['rol'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $txt = trim((string) ($m['contenido'] ?? ''));
            if ($txt === '') {
                continue;
            }
            if (!$out && $rol === 'assistant') {
                continue;
            }
            $n = count($out);
            if ($n && $out[$n - 1]['role'] === $rol) {
                $out[$n - 1]['content'] .= "\n\n" . $txt;
            } else {
                $out[] = ['role' => $rol, 'content' => $txt];
            }
        }
        // La API espera que el último turno sea del usuario.
        while ($out && end($out)['role'] === 'assistant') {
            array_pop($out);
        }
        return $out;
    }

    /**
     * Texto del error 400 de Anthropic, legible y seguro de mostrar: saca el
     * «message» del JSON si viene, tapa cualquier cosa con forma de clave y recorta.
     */
    public static function motivoAnthropic(string $crudo): string
    {
        $t = $crudo;
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $crudo, $m)) {
            $t = (string) json_decode('"' . $m[1] . '"');
        }
        $t = (string) preg_replace('/sk-ant-[A-Za-z0-9_\-]+/', 'sk-ant-***', $t);
        $t = trim((string) preg_replace('/\s+/', ' ', $t));
        return mb_substr($t, 0, 240);
    }

    private static function textoDe(array $bloques): string
    {
        $t = [];
        foreach ($bloques as $b) {
            if (($b->type ?? '') === 'text') {
                $t[] = (string) $b->text;
            }
        }
        return implode("\n", $t);
    }

    /** Modelos que aceptan output_config.effort (Claude 4.6 en adelante). */
    public static function admiteEsfuerzo(string $modelo): bool
    {
        return (bool) preg_match('/^claude-(fable|mythos|opus-5|sonnet-5|haiku-5|opus-4-[6-9]|sonnet-4-6)/', $modelo);
    }

    public static function costo(string $modelo, int $entrada, int $salida, int $cache): float
    {
        $base = preg_replace('/-\d{8}$/', '', $modelo);
        [$pi, $po, $pc] = self::PRECIOS[$base] ?? [0, 0, 0];
        return ($entrada * $pi + $salida * $po + $cache * $pc) / 1_000_000;
    }
}
