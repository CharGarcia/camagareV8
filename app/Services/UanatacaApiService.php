<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Cache;
use RuntimeException;

/**
 * Cliente de la API "Precompra" de Uanataca Ecuador (distribuidores / sistema PICCOLOS).
 * Documentación: namirial.atlassian.net → "APIs de integración Módulo Precompra v1.1".
 *
 *   POST /auth/login              usuario + clave → JWT (≈305 s)
 *   GET  /products                catálogo de productos
 *   GET  /uanacredits/balance     saldo de Uanacréditos (prepago)
 *   POST /certificateRequests     crea la solicitud (documentos en base64) → 201 + Location
 *   GET  /certificateRequests?uuid=…  estado de una solicitud
 *
 * Sin webhooks: el estado se consulta por cron. Solo transporte: la lógica de negocio
 * (qué se envía y cuándo) vive en FirmaElectronicaService.
 */
class UanatacaApiService
{
    /** Bases y ruta de creación por ambiente (en producción el POST lleva /api, según la documentación). */
    private const AMBIENTES = [
        'pruebas'    => ['base' => 'https://api-test.uanatacaec.com/piccolos', 'crear' => '/certificateRequests'],
        'produccion' => ['base' => 'https://distribuidores.uanatacaec.com',    'crear' => '/api/certificateRequests'],
    ];

    /** Estados de la solicitud en Uanataca, con su etiqueta. */
    public const ESTADOS = [
        'NEW'              => 'Nueva',
        'IN_VALIDATION'    => 'En validación',
        'UPDATE_REQUESTED' => 'Corrección solicitada',
        'UPDATED'          => 'Corregida',
        'APPROVED'         => 'Aprobada',
        'REJECTED'         => 'Rechazada',
        'ISSUED'           => 'Emitida',
        'CANCELED'         => 'Cancelada',
    ];

    private const LOG = '/storage/logs/uanataca_api.log';

    private string $ambiente;
    private string $usuario;
    private string $clave;
    private static array $tokens = [];

    public function __construct(string $ambiente, string $usuario, string $clave)
    {
        if (!isset(self::AMBIENTES[$ambiente])) {
            throw new RuntimeException('Ambiente de Uanataca no válido.');
        }
        if (trim($usuario) === '' || $clave === '') {
            throw new RuntimeException('Faltan el usuario o la clave de la API de Uanataca.');
        }
        $this->ambiente = $ambiente;
        $this->usuario  = trim($usuario);
        $this->clave    = $clave;
    }

    public function productos(): array
    {
        [$st, , $body] = $this->peticion('GET', '/products');
        $this->exigirOk($st, $body, 'consultar los productos');
        return is_array($json = json_decode($body, true)) ? $json : [];
    }

    public function saldo(): float
    {
        [$st, , $body] = $this->peticion('GET', '/uanacredits/balance');
        $this->exigirOk($st, $body, 'consultar el saldo');
        $json = json_decode($body, true);
        return (float) (is_array($json) ? ($json['balance'] ?? 0) : trim($body));
    }

    /**
     * Crea la solicitud y devuelve su uuid (último segmento del header Location).
     * La documentación muestra el cuerpo como objeto y el ejemplo como arreglo: se envía
     * como objeto y, si el servidor no lo acepta por formato, se reintenta como arreglo.
     */
    public function crearSolicitud(array $payload): string
    {
        $ruta = self::AMBIENTES[$this->ambiente]['crear'];
        [$st, $headers, $body] = $this->peticion('POST', $ruta, $payload);
        // La documentación pone /api solo en el POST de producción, mientras login, productos
        // y consultas van sin /api. Si esa ruta se niega (403) o no existe (404), se prueba la
        // misma ruta que usa el resto de la API: un 403/404 no crea nada, así que no duplica.
        if (in_array($st, [403, 404], true) && str_starts_with($ruta, '/api/')) {
            $ruta = substr($ruta, 4);
            [$st, $headers, $body] = $this->peticion('POST', $ruta, $payload);
        }
        if ($st === 400 && preg_match('/deserializ|START_ARRAY|START_OBJECT|JSON parse/i', $body)) {
            [$st, $headers, $body] = $this->peticion('POST', $ruta, [$payload]);
        }
        if ($st === 403) {
            throw new RuntimeException('Uanataca no permite a este usuario de API crear solicitudes (HTTP 403) en el ambiente '
                . ($this->ambiente === 'produccion' ? 'Producción' : 'Pruebas') . '. El usuario sí inicia sesión, así que la clave es correcta: '
                . 'pida a Uanataca que habilite la creación de solicitudes (Precompra) para ese usuario en ese ambiente, '
                . 'o revise que no esté usando un usuario de pruebas en Producción (o al revés).');
        }
        $this->exigirOk($st, $body, 'crear la solicitud');

        $location = $headers['location'] ?? '';
        $uuid = $location !== '' ? basename(parse_url($location, PHP_URL_PATH) ?: $location) : '';
        if ($uuid === '') {
            // Sin Location: se intenta leer del cuerpo por si la API devuelve el recurso.
            $json = json_decode($body, true);
            $uuid = (string) ($json['uuid'] ?? ($json[0]['uuid'] ?? ''));
        }
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $uuid)) {
            throw new RuntimeException('Uanataca creó la solicitud pero no devolvió su identificador. Revise el portal de distribuidores antes de reenviar.');
        }
        return $uuid;
    }

    /** Solicitudes del distribuidor que coinciden con un texto (identificación, correo, nombres). */
    public function buscarSolicitudes(string $q): array
    {
        [$st, , $body] = $this->peticion('GET', '/certificateRequests?' . http_build_query(['q' => $q]));
        $this->exigirOk($st, $body, 'buscar solicitudes');
        $json = json_decode($body, true);
        return is_array($json) ? array_values(array_filter($json, 'is_array')) : [];
    }

    /** Solicitud por uuid; null si Uanataca no la encuentra. */
    public function consultarSolicitud(string $uuid): ?array
    {
        [$st, , $body] = $this->peticion('GET', '/certificateRequests?' . http_build_query(['uuid' => $uuid]));
        $this->exigirOk($st, $body, 'consultar la solicitud');
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }
        if (isset($json['uuid'])) {
            return $json;
        }
        foreach ($json as $row) {
            if (is_array($row) && ($row['uuid'] ?? '') === $uuid) {
                return $row;
            }
        }
        return null;
    }

    // ── Transporte ────────────────────────────────────────────

    private function token(bool $renovar = false): string
    {
        $clave = 'uanataca_jwt:' . md5($this->ambiente . '|' . $this->usuario);
        if (!$renovar) {
            $t = self::$tokens[$clave] ?? Cache::get($clave);
            if (is_array($t) && $t['exp'] > time()) {
                return $t['token'];
            }
        }
        [$st, , $body] = $this->peticion('POST', '/auth/login', ['username' => $this->usuario, 'password' => $this->clave], false);
        if ($st === 401) {
            throw new RuntimeException('Uanataca rechazó el usuario o la clave de la API.');
        }
        $this->exigirOk($st, $body, 'iniciar sesión');
        $json  = json_decode($body, true);
        $token = (string) ($json['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Uanataca no devolvió el token de acceso.');
        }
        // Margen de 30 s sobre la vigencia que informa la API (≈305 s).
        $ttl = max(30, (int) ($json['expires_in'] ?? 300) - 30);
        $t = ['token' => $token, 'exp' => time() + $ttl];
        self::$tokens[$clave] = $t;
        Cache::set($clave, $t, $ttl);
        return $token;
    }

    /** @return array{0:int, 1:array<string,string>, 2:string} estado HTTP, headers (minúsculas) y cuerpo */
    private function peticion(string $metodo, string $ruta, ?array $json = null, bool $auth = true, bool $reintento = false): array
    {
        // UANATACA_API_BASE (config/local.php) permite apuntar el ambiente de pruebas a un
        // simulador en desarrollo; en producción no se define.
        $base = $this->ambiente === 'pruebas' && defined('UANATACA_API_BASE') ? (string) UANATACA_API_BASE : self::AMBIENTES[$this->ambiente]['base'];
        $url  = $base . $ruta;
        $headers = ['Accept: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }
        $cuerpo = null;
        if ($json !== null) {
            $cuerpo = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $resp = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $metodo === 'POST' && $auth ? 120 : 30,
            CURLOPT_HEADERFUNCTION => function ($c, $linea) use (&$resp) {
                $p = explode(':', $linea, 2);
                if (count($p) === 2) {
                    $resp[strtolower(trim($p[0]))] = trim($p[1]);
                }
                return strlen($linea);
            },
        ]);
        if ($cuerpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
        }
        $body  = curl_exec($ch);
        $error = curl_error($ch);
        $st    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $this->log($metodo, $ruta, $st, $error !== '' ? $error : (string) $body, $cuerpo !== null ? strlen($cuerpo) : 0);
        if ($body === false || $error !== '') {
            throw new RuntimeException('No se pudo conectar con Uanataca: ' . $error);
        }
        // Token vencido entre la caché y el uso: se renueva una vez.
        if ($auth && $st === 401 && !$reintento) {
            $this->token(true);
            return $this->peticion($metodo, $ruta, $json, $auth, true);
        }
        return [$st, $resp, (string) $body];
    }

    private function exigirOk(int $st, string $body, string $accion): void
    {
        if ($st >= 200 && $st < 300) {
            return;
        }
        $json = json_decode($body, true);
        $msg  = is_array($json)
            ? (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? $json['detail'] ?? '')
            : trim(strip_tags($body));
        if (is_array($json) && !empty($json['errors']) && is_array($json['errors'])) {
            $msg .= ' ' . implode(' ', array_map(static fn($e) => is_array($e) ? (string) ($e['message'] ?? json_encode($e)) : (string) $e, $json['errors']));
        }
        $prefijo = match (true) {
            $st === 401 => 'Sesión de Uanataca no válida',
            $st === 403 => 'El usuario de Uanataca no tiene permiso',
            $st >= 500  => 'Error interno de Uanataca',
            default     => 'Uanataca rechazó la petición',
        };
        throw new RuntimeException("{$prefijo} al {$accion} (HTTP {$st})" . ($msg !== '' ? ': ' . mb_substr($msg, 0, 500) : '.'));
    }

    /** Bitácora técnica sin credenciales ni documentos (solo tamaño del cuerpo enviado). */
    private function log(string $metodo, string $ruta, int $st, string $resp, int $bytes): void
    {
        $linea = sprintf("[%s] %s %s %s → %d (%d bytes enviados) %s\n",
            date('Y-m-d H:i:s'), $this->ambiente, $metodo, $ruta, $st, $bytes,
            str_contains($ruta, 'auth/login') ? '' : mb_substr(preg_replace('/\s+/', ' ', $resp), 0, 400));
        @file_put_contents(MVC_ROOT . self::LOG, $linea, FILE_APPEND);
    }
}
