<?php

declare(strict_types=1);

namespace App\Services\modulos\videollamadas;

use App\Helpers\Cache;
use App\Helpers\CryptoHelper;
use App\Rules\modulos\VideollamadaRules;

/**
 * Motor interno: WebRTC en malla (mesh) punto a punto.
 *
 * No hay servidor de medios. El audio y el video viajan directo entre los
 * navegadores de los participantes; el servidor solo actúa de "presentador"
 * durante los primeros segundos (señalización) y después se desentiende.
 *
 * Consecuencias del diseño:
 *   - Costo de infraestructura: prácticamente cero, y el video nunca pasa por
 *     el droplet ni consume su ancho de banda.
 *   - Techo de participantes bajo: con N personas cada navegador sube N-1
 *     copias de su video (ver VideollamadaRules::MAX_PARTICIPANTES_MESH).
 *   - Necesita TURN: entre el 10% y el 20% de las conexiones no logran camino
 *     directo (NAT simétrico, redes corporativas, CGNAT de operadoras móviles)
 *     y requieren un relay. Sin TURN configurado esas llamadas no conectan.
 *
 * En la Fase 1 este proveedor solo prepara la sala y arma la lista de servidores
 * ICE desde la configuración de la empresa. La negociación WebRTC en sí es
 * trabajo de la Fase 2.
 */
class ProveedorInterno implements ProveedorVideollamada
{
    /** API de Cloudflare Realtime para emitir credenciales TURN de corta duración. */
    private const URL_CLOUDFLARE_TURN = 'https://rtc.live.cloudflare.com/v1/turn/keys/';

    /** Vigencia pedida para la credencial efímera (2 horas: más que cualquier reunión). */
    private const TTL_CREDENCIAL = 7200;

    public function getNombre(): string
    {
        return 'interno';
    }

    public function getMaxParticipantes(): int
    {
        return VideollamadaRules::MAX_PARTICIPANTES_MESH;
    }

    /**
     * El motor interno no registra la sala en ningún sistema externo: la sala
     * "existe" por el solo hecho de estar en nuestra base de datos.
     */
    public function crearSala(array $sala, array $config): array
    {
        return ['id_externo' => null, 'url' => null];
    }

    /**
     * Devuelve los servidores ICE que el navegador usará para conectarse.
     *
     * El formato es el que espera RTCPeerConnection directamente:
     *   [ {urls: 'stun:...'}, {urls: 'turn:...', username: '...', credential: '...'} ]
     *
     * Orden de preferencia para el TURN:
     *   1. Credenciales EFÍMERAS pedidas por API (Cloudflare). La clave maestra
     *      se queda en el servidor; al navegador solo baja un usuario/clave que
     *      caduca en horas.
     *   2. Credenciales estáticas guardadas en la configuración.
     *   3. Sin TURN: se avisa a la interfaz, porque un porcentaje de las
     *      llamadas no va a conectar.
     */
    public function obtenerCredenciales(array $sala, array $config, array $participante): array
    {
        $iceServers = [];

        foreach ($this->separarUrls($config['stun_urls'] ?? '') as $url) {
            $iceServers[] = ['urls' => $url];
        }

        $turn = $this->resolverTurn($config);
        if ($turn !== null) {
            $iceServers[] = $turn;
        }

        return [
            'proveedor'        => $this->getNombre(),
            'ice_servers'      => $iceServers,
            'turn_configurado' => $turn !== null,
            'codigo_sala'      => $sala['codigo'] ?? null,
            'rol'              => $participante['rol'] ?? 'participante',
        ];
    }

    /** Arma la entrada TURN, efímera si se puede y estática si no. */
    private function resolverTurn(array $config): ?array
    {
        $efimero = $this->credencialEfimeraCloudflare($config);
        if ($efimero !== null) {
            return $efimero;
        }

        $turnUrls = $this->separarUrls($config['turn_urls'] ?? '');
        if ($turnUrls === []) {
            return null;
        }

        $turn = ['urls' => count($turnUrls) === 1 ? $turnUrls[0] : $turnUrls];
        if (!empty($config['turn_usuario'])) {
            $turn['username'] = (string) $config['turn_usuario'];
        }
        if (!empty($config['turn_credencial'])) {
            $turn['credential'] = CryptoHelper::desencriptar((string) $config['turn_credencial']);
        }
        return $turn;
    }

    /**
     * Pide a Cloudflare una credencial TURN de corta duración.
     *
     * Se cachea por empresa para no llamar a la API cada vez que alguien entra a
     * una sala: la credencial vive TTL_CREDENCIAL segundos y se guarda algo menos.
     *
     * Si algo falla (sin configurar, API caída, token inválido) devuelve null y
     * el llamador cae a las credenciales estáticas. Una videollamada nunca debe
     * romperse porque un servicio externo no respondió.
     *
     * NOTA: el endpoint y el formato de respuesta siguen la API de Cloudflare
     * Realtime. Si Cloudflare los cambia, esto degrada solo (devuelve null) en
     * lugar de fallar: revisar su documentación vigente si el TURN deja de
     * emitirse.
     */
    private function credencialEfimeraCloudflare(array $config): ?array
    {
        $keyId    = trim((string) ($config['turn_key_id'] ?? ''));
        $tokenRaw = trim((string) ($config['turn_api_token'] ?? ''));

        if ($keyId === '' || $tokenRaw === '') {
            return null;
        }

        $claveCache = 'vc:turn:cf:' . $keyId;
        $cacheado = Cache::get($claveCache);
        if (is_array($cacheado)) {
            return $cacheado;
        }

        $token = CryptoHelper::desencriptar($tokenRaw);
        if ($token === '') {
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::URL_CLOUDFLARE_TURN . rawurlencode($keyId) . '/credentials/generate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['ttl' => self::TTL_CREDENCIAL]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 6,
        ]);

        $respuesta = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($errorCurl !== '' || $httpCode < 200 || $httpCode >= 300) {
            error_log('Videollamadas TURN: Cloudflare respondió ' . $httpCode . ' ' . $errorCurl);
            return null;
        }

        $data = json_decode((string) $respuesta, true);
        $ice  = $data['iceServers'] ?? null;

        if (!is_array($ice) || empty($ice['urls']) || empty($ice['username'])) {
            error_log('Videollamadas TURN: respuesta de Cloudflare sin iceServers utilizables.');
            return null;
        }

        $turn = [
            'urls'       => $ice['urls'],
            'username'   => (string) $ice['username'],
            'credential' => (string) ($ice['credential'] ?? ''),
        ];

        // Se cachea algo menos que su vigencia, para no entregar nunca una a punto de vencer.
        Cache::set($claveCache, $turn, self::TTL_CREDENCIAL - 600);

        return $turn;
    }

    /** No hay nada que liberar: el motor interno no guarda estado fuera de la BD. */
    public function cerrarSala(array $sala, array $config): void
    {
    }

    /** Convierte "stun:a:1, turn:b:2" en ['stun:a:1', 'turn:b:2']. */
    private function separarUrls(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }
        $partes = array_map('trim', explode(',', $csv));
        return array_values(array_filter($partes, static fn (string $u): bool => $u !== ''));
    }
}
