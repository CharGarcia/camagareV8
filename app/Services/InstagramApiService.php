<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Único punto del sistema que habla con la API de Instagram (Instagram Login,
 * graph.instagram.com). Diseño: docs/diseno/crm-redes-fase1.md §7.
 *
 * Endpoints (documentación de Meta, verificados el 08-11-2026):
 *  - Autorizar:      https://www.instagram.com/oauth/authorize
 *  - Código→token:   POST https://api.instagram.com/oauth/access_token (token de ~1 h)
 *  - Token largo:    GET  https://graph.instagram.com/access_token?grant_type=ig_exchange_token (~60 días)
 *  - Renovar:        GET  https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token
 *                    (el token debe tener 24 h o más y seguir vigente)
 *  - Perfil:         GET  https://graph.instagram.com/{v}/me?fields=…
 *  - Webhooks:       POST https://graph.instagram.com/{v}/me/subscribed_apps?subscribed_fields=…
 *
 * Los tokens NUNCA se registran en logs ni se incluyen en los mensajes de error.
 * Todas las llamadas tienen tiempo máximo corto: el llamador debe soltar la
 * sesión PHP (session_write_close) antes de usar este servicio desde una petición web.
 */
class InstagramApiService
{
    private const URL_AUTORIZAR = 'https://www.instagram.com/oauth/authorize';
    private const URL_CODIGO    = 'https://api.instagram.com/oauth/access_token';
    private const URL_GRAPH     = 'https://graph.instagram.com';

    private const TIMEOUT_CONEXION = 8;
    private const TIMEOUT_TOTAL    = 20;

    private array $cfg;

    /** Instancias por empresa ya armadas en esta petición. */
    private static array $porEmpresa = [];

    /**
     * $cfg: credenciales (app_id, app_secret, webhook_verify_token) + lo técnico.
     * Lo técnico que no venga (versión, permisos, campos del webhook) sale de
     * config/app.php → meta_instagram. Las credenciales son de cada empresa
     * (crm_meta_apps): usar paraEmpresa().
     */
    public function __construct(array $cfg = [])
    {
        $this->cfg = $cfg + self::configTecnica() + ['app_id' => '', 'app_secret' => '', 'webhook_verify_token' => ''];
    }

    /** Versión de la API, permisos y campos del webhook (globales, sin secretos). */
    public static function configTecnica(): array
    {
        static $tec = null;
        if ($tec === null) {
            $app = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
            $m = (array) ($app['meta_instagram'] ?? []);
            $tec = [
                'graph_version'  => (string) ($m['graph_version'] ?? 'v27.0'),
                'scopes'         => (array) ($m['scopes'] ?? ['instagram_business_basic']),
                'webhook_fields' => (array) ($m['webhook_fields'] ?? []),
            ];
        }
        return $tec;
    }

    /**
     * Cliente con la app de Meta que registró la empresa (Configuración del CRM).
     * Si no tiene, devuelve uno sin configurar: configurado() = false.
     */
    public static function paraEmpresa(int $idEmpresa): self
    {
        if (!isset(self::$porEmpresa[$idEmpresa])) {
            $a = (new \App\repositories\modulos\CrmMetaAppRepository())->getPorEmpresa($idEmpresa);
            self::$porEmpresa[$idEmpresa] = new self($a && $a['estado'] === 'activo' ? [
                'app_id'               => (string) $a['app_id'],
                'app_secret'           => \App\Helpers\CryptoHelper::desencriptar((string) $a['app_secret_cifrado']),
                'webhook_verify_token' => \App\Helpers\CryptoHelper::desencriptar((string) $a['verify_token_cifrado']),
            ] : []);
        }
        return self::$porEmpresa[$idEmpresa];
    }

    /** La empresa cambió su app: la próxima llamada vuelve a leerla. */
    public static function olvidarEmpresa(int $idEmpresa): void
    {
        unset(self::$porEmpresa[$idEmpresa]);
    }

    public function appId(): string
    {
        return (string) $this->cfg['app_id'];
    }

    /** ¿Están el App ID y el App Secret? Sin ellos no se puede conectar ninguna cuenta. */
    public function configurado(): bool
    {
        return trim((string) $this->cfg['app_id']) !== '' && trim((string) $this->cfg['app_secret']) !== '';
    }

    public function scopes(): array
    {
        return array_values((array) $this->cfg['scopes']);
    }

    /** URL a la que se envía al usuario para autorizar la cuenta de Instagram. */
    public function urlAutorizacion(string $state, string $redirectUri): string
    {
        return self::URL_AUTORIZAR . '?' . http_build_query([
            'client_id'     => $this->cfg['app_id'],
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(',', $this->scopes()),
            'state'         => $state,
            'enable_fb_login' => 'false',
            'force_reauth'  => 'true',
        ]);
    }

    /**
     * Canjea el código del retorno OAuth por un token corto.
     * @return array{token:string, user_id:string, permisos:array}
     */
    public function canjearCodigo(string $code, string $redirectUri): array
    {
        $r = $this->http('POST', self::URL_CODIGO, [
            'client_id'     => $this->cfg['app_id'],
            'client_secret' => $this->cfg['app_secret'],
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);
        // La respuesta viene como {data:[{access_token,user_id,permissions}]} o plana.
        $d = isset($r['data'][0]) && is_array($r['data'][0]) ? $r['data'][0] : $r;
        if (empty($d['access_token'])) {
            throw new \RuntimeException('Instagram no entregó el acceso. Vuelva a intentar la conexión.');
        }
        $permisos = $d['permissions'] ?? [];
        if (is_string($permisos)) {
            $permisos = array_filter(array_map('trim', explode(',', $permisos)));
        }
        return ['token' => (string) $d['access_token'], 'user_id' => (string) ($d['user_id'] ?? ''), 'permisos' => array_values((array) $permisos)];
    }

    /** Token corto → token de larga duración. @return array{token:string, expira_en:int} */
    public function tokenLargo(string $tokenCorto): array
    {
        $r = $this->http('GET', self::URL_GRAPH . '/access_token', [
            'grant_type'    => 'ig_exchange_token',
            'client_secret' => $this->cfg['app_secret'],
            'access_token'  => $tokenCorto,
        ]);
        return $this->leerToken($r);
    }

    /** Renueva un token largo vigente (con 24 h o más). @return array{token:string, expira_en:int} */
    public function renovarToken(string $token): array
    {
        $r = $this->http('GET', self::URL_GRAPH . '/refresh_access_token', [
            'grant_type'   => 'ig_refresh_token',
            'access_token' => $token,
        ]);
        return $this->leerToken($r);
    }

    /** @return array{user_id:string, username:string, name:string, account_type:string} */
    public function perfil(string $token): array
    {
        $r = $this->http('GET', $this->graph('me'), [
            'fields'       => 'user_id,username,name,account_type',
            'access_token' => $token,
        ]);
        if (empty($r['user_id']) && empty($r['id'])) {
            throw new \RuntimeException('Instagram no devolvió los datos de la cuenta.');
        }
        return [
            'user_id'      => (string) ($r['user_id'] ?? $r['id']),
            'username'     => (string) ($r['username'] ?? ''),
            'name'         => (string) ($r['name'] ?? ''),
            'account_type' => (string) ($r['account_type'] ?? ''),
        ];
    }

    /** Suscribe la cuenta a los webhooks configurados (mensajes, comentarios…). */
    public function suscribirWebhooks(string $token): bool
    {
        $campos = array_values((array) $this->cfg['webhook_fields']);
        if (!$campos) {
            return false;
        }
        $r = $this->http('POST', $this->graph('me/subscribed_apps') . '?' . http_build_query([
            'subscribed_fields' => implode(',', $campos),
            'access_token'      => $token,
        ]), []);
        return !empty($r['success']);
    }

    // ── Webhook ──────────────────────────────────────────────────────────────

    /** Token que Meta envía en la verificación GET del webhook (hub.verify_token). */
    public function verifyTokenValido(string $token): bool
    {
        $esperado = (string) ($this->cfg['webhook_verify_token'] ?? '');
        return $esperado !== '' && $token !== '' && hash_equals($esperado, $token);
    }

    /**
     * X-Hub-Signature-256 = "sha256=" + HMAC-SHA256(cuerpo crudo, App Secret).
     * Sin App Secret configurado nunca es válida.
     */
    public function firmaValida(string $cuerpo, string $cabecera): bool
    {
        $secreto = (string) ($this->cfg['app_secret'] ?? '');
        if ($secreto === '' || !str_starts_with($cabecera, 'sha256=')) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $cuerpo, $secreto), substr($cabecera, 7));
    }

    /**
     * signed_request de Meta (eliminación de datos / desautorización):
     * "firma.payload" en base64url; la firma es HMAC-SHA256 del payload CODIFICADO
     * con el App Secret. Devuelve el payload (user_id, algorithm, issued_at…) o
     * null si la firma o el formato no son válidos.
     */
    public function leerSignedRequest(string $signed): ?array
    {
        $secreto = (string) ($this->cfg['app_secret'] ?? '');
        $partes = explode('.', trim($signed), 2);
        if ($secreto === '' || count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
            return null;
        }
        $b64 = static function (string $s) {
            $s = strtr($s, '-_', '+/');
            return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        };
        $firma = $b64($partes[0]);
        $json = $b64($partes[1]);
        $datos = $json !== false ? json_decode($json, true) : null;
        if ($firma === false || !is_array($datos) || strtoupper((string) ($datos['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            return null;
        }
        return hash_equals(hash_hmac('sha256', $partes[1], $secreto, true), $firma) ? $datos : null;
    }

    /**
     * Nombre y usuario de quien escribió (User Profile API). Solo funciona con
     * personas que ya escribieron a la cuenta. @return array{name:string, username:string}
     */
    public function perfilRemitente(string $token, string $igsid): array
    {
        $r = $this->http('GET', $this->graph(rawurlencode($igsid)), [
            'fields'       => 'name,username',
            'access_token' => $token,
        ]);
        return ['name' => (string) ($r['name'] ?? ''), 'username' => (string) ($r['username'] ?? '')];
    }

    /** Extensiones permitidas por tipo MIME para los adjuntos guardados. */
    private const EXT_MIME = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a',
        'audio/aac' => 'aac', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'application/pdf' => 'pdf',
    ];

    /**
     * Descarga un adjunto (las URLs de Meta caducan) a $dirAbs con nombre $base.
     * Solo HTTPS de dominios de Meta, tope de tamaño y tipos conocidos.
     * @return string|null nombre del archivo guardado, o null si no se pudo
     */
    public function descargarAdjunto(string $url, string $dirAbs, string $base, int $maxBytes = 26214400): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https'
            || !preg_match('/(^|\.)(fbcdn\.net|cdninstagram\.com|instagram\.com|facebook\.com|fbsbx\.com)$/', $host)) {
            return null;
        }
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0750, true) && !is_dir($dirAbs)) {
            return null;
        }
        $tmp = $dirAbs . '/' . $base . '.part';
        $fh = @fopen($tmp, 'wb');
        if (!$fh) {
            return null;
        }
        $bytes = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXION,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION  => function ($ch, $datos) use ($fh, &$bytes, $maxBytes) {
                $bytes += strlen($datos);
                if ($bytes > $maxBytes) {
                    return -1;   // corta la descarga
                }
                return fwrite($fh, $datos);
            },
        ]);
        $ok = curl_exec($ch) !== false;
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = strtolower(trim(explode(';', (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
        curl_close($ch);
        fclose($fh);

        $ext = self::EXT_MIME[$mime] ?? null;
        if (!$ok || $codigo !== 200 || $bytes === 0 || $ext === null) {
            @unlink($tmp);
            return null;
        }
        $final = $base . '.' . $ext;
        if (!@rename($tmp, $dirAbs . '/' . $final)) {
            @unlink($tmp);
            return null;
        }
        // El cron (root) baja los adjuntos: carpeta y archivo quedan del dueño de
        // storage/, para que Apache pueda mostrarlos y guardar en la misma carpeta.
        \App\Helpers\DuenoStorage::igualar($dirAbs . '/' . $final);
        return $final;
    }

    private function graph(string $ruta): string
    {
        return self::URL_GRAPH . '/' . trim((string) $this->cfg['graph_version'], '/') . '/' . ltrim($ruta, '/');
    }

    private function leerToken(array $r): array
    {
        if (empty($r['access_token'])) {
            throw new \RuntimeException('Instagram no entregó el token de larga duración.');
        }
        return ['token' => (string) $r['access_token'], 'expira_en' => (int) ($r['expires_in'] ?? 5184000)];
    }

    /**
     * Petición HTTP con verificación TLS activa y tiempos máximos cortos.
     * Lanza RuntimeException con un mensaje entendible (sin tokens ni secretos).
     */
    // ── Envío (Bandeja social) ───────────────────────────────────────────────

    /** Tope de Meta para el texto de un mensaje: 1000 bytes UTF-8. */
    public const MAX_BYTES_MENSAJE = 1000;

    /**
     * Mensaje directo a una persona (IGSID). $tag = 'HUMAN_AGENT' para responder
     * entre 24 h y 7 días (solo si la app tiene aprobada esa función).
     * @return string id del mensaje en Instagram (mid)
     */
    public function enviarMensaje(string $token, string $igsid, string $texto, ?string $tag = null): string
    {
        $cuerpo = ['recipient' => ['id' => $igsid], 'message' => ['text' => $texto]];
        if ($tag !== null) {
            $cuerpo['messaging_type'] = 'MESSAGE_TAG';
            $cuerpo['tag'] = $tag;
        }
        $r = $this->http('POST', $this->graph('me/messages'), $cuerpo, $token, true);
        if (empty($r['message_id'])) {
            throw new \RuntimeException('Instagram no confirmó el envío del mensaje.');
        }
        return (string) $r['message_id'];
    }

    /**
     * Imagen por mensaje directo. Instagram la descarga desde $urlPublica (debe
     * ser HTTPS y accesible sin sesión), así que se pasa un enlace firmado y temporal.
     */
    public function enviarImagen(string $token, string $igsid, string $urlPublica, ?string $tag = null): string
    {
        $cuerpo = ['recipient' => ['id' => $igsid],
                   'message' => ['attachment' => ['type' => 'image', 'payload' => ['url' => $urlPublica]]]];
        if ($tag !== null) {
            $cuerpo['messaging_type'] = 'MESSAGE_TAG';
            $cuerpo['tag'] = $tag;
        }
        $r = $this->http('POST', $this->graph('me/messages'), $cuerpo, $token, true);
        if (empty($r['message_id'])) {
            throw new \RuntimeException('Instagram no confirmó el envío de la imagen.');
        }
        return (string) $r['message_id'];
    }

    /** Respuesta privada (DM) a quien comentó: una sola por comentario, dentro de 7 días. */
    public function respuestaPrivada(string $token, string $idComentario, string $texto): string
    {
        $r = $this->http('POST', $this->graph('me/messages'),
            ['recipient' => ['comment_id' => $idComentario], 'message' => ['text' => $texto]], $token, true);
        if (empty($r['message_id'])) {
            throw new \RuntimeException('Instagram no confirmó la respuesta privada.');
        }
        return (string) $r['message_id'];
    }

    /** Respuesta pública bajo el comentario. @return string id del comentario de respuesta */
    public function responderComentario(string $token, string $idComentario, string $texto): string
    {
        $r = $this->http('POST', $this->graph(rawurlencode($idComentario) . '/replies'), ['message' => $texto], $token, true);
        if (empty($r['id'])) {
            throw new \RuntimeException('Instagram no confirmó la respuesta al comentario.');
        }
        return (string) $r['id'];
    }

    // ── Publicaciones (fase 3, docs/diseno/crm-redes-fase3.md) ─────────────
    // Flujo de Meta: contenedor (POST /{ig}/media) → esperar FINISHED
    // (GET /{contenedor}?fields=status_code) → publicar (POST /{ig}/media_publish).
    // Los archivos se pasan por URL PÚBLICA (enlace firmado del sistema); imágenes solo JPEG.

    /** Formatos de contenedor que arma el sistema. */
    public const CONTENEDOR_IMAGEN = 'imagen';
    public const CONTENEDOR_REEL = 'reel';
    public const CONTENEDOR_CARRUSEL = 'carrusel';
    public const CONTENEDOR_VIDEO_ITEM = 'video_item';

    /**
     * Crea un contenedor y devuelve su id.
     * $d: imagen → url, [texto], [alt], [carrusel_item], [ia]
     *     reel   → url (video), [texto], [ia], [portada_ms]
     *     carrusel → hijos (ids de contenedores), [texto], [ia]
     */
    public function crearContenedor(string $token, string $igId, string $tipo, array $d): string
    {
        $p = [];
        switch ($tipo) {
            case self::CONTENEDOR_IMAGEN:
                $p['image_url'] = (string) $d['url'];
                if (!empty($d['carrusel_item'])) {
                    $p['is_carousel_item'] = 'true';
                }
                if (trim((string) ($d['alt'] ?? '')) !== '') {
                    $p['alt_text'] = mb_substr(trim((string) $d['alt']), 0, 1000);
                }
                break;
            case self::CONTENEDOR_VIDEO_ITEM:   // video dentro de un carrusel
                $p['media_type'] = 'VIDEO';
                $p['video_url'] = (string) $d['url'];
                $p['is_carousel_item'] = 'true';
                $d['carrusel_item'] = true;
                break;
            case self::CONTENEDOR_REEL:
                $p['media_type'] = 'REELS';
                $p['video_url'] = (string) $d['url'];
                $p['share_to_feed'] = 'true';
                if (isset($d['portada_ms'])) {
                    $p['thumb_offset'] = (string) (int) $d['portada_ms'];
                }
                break;
            case self::CONTENEDOR_CARRUSEL:
                $hijos = array_values(array_filter(array_map('strval', (array) ($d['hijos'] ?? []))));
                if (count($hijos) < 2 || count($hijos) > 20) {
                    throw new \InvalidArgumentException('Un carrusel lleva de 2 a 20 imágenes o videos.');
                }
                $p['media_type'] = 'CAROUSEL';
                $p['children'] = implode(',', $hijos);
                break;
            default:
                throw new \InvalidArgumentException('Formato de Instagram no válido.');
        }
        // El texto y la marca de IA van en el contenedor principal, nunca en los hijos del carrusel.
        if (empty($d['carrusel_item'])) {
            if (trim((string) ($d['texto'] ?? '')) !== '') {
                $p['caption'] = (string) $d['texto'];
            }
            if (!empty($d['ia'])) {
                $p['is_ai_generated'] = 'true';
            }
        }
        $r = $this->http('POST', $this->graph(rawurlencode($igId) . '/media'), $p, $token, true);
        if (empty($r['id'])) {
            throw new \RuntimeException('Instagram no creó el contenido para publicar.');
        }
        return (string) $r['id'];
    }

    /** EXPIRED | ERROR | FINISHED | IN_PROGRESS | PUBLISHED (y el detalle si hubo error). */
    public function estadoContenedor(string $token, string $idContenedor): array
    {
        $r = $this->http('GET', $this->graph(rawurlencode($idContenedor)), ['fields' => 'status_code,status'], $token);
        return ['estado' => strtoupper((string) ($r['status_code'] ?? '')), 'detalle' => (string) ($r['status'] ?? '')];
    }

    /** Publica un contenedor listo (FINISHED). Devuelve el id de la publicación. */
    public function publicarContenedor(string $token, string $igId, string $idContenedor): string
    {
        $r = $this->http('POST', $this->graph(rawurlencode($igId) . '/media_publish'), ['creation_id' => $idContenedor], $token, true);
        if (empty($r['id'])) {
            throw new \RuntimeException('Instagram no confirmó la publicación.');
        }
        return (string) $r['id'];
    }

    /**
     * Publicaciones por API usadas en las últimas 24 h y el tope vigente de la
     * cuenta (la documentación dice 100 en un lugar y 50 en otro: se usa el real).
     * @return array{usadas:int, tope:int}
     */
    public function limitePublicacion(string $token, string $igId): array
    {
        $r = $this->http('GET', $this->graph(rawurlencode($igId) . '/content_publishing_limit'), ['fields' => 'quota_usage,config'], $token);
        $d = $r['data'][0] ?? $r;
        return ['usadas' => (int) ($d['quota_usage'] ?? 0), 'tope' => (int) ($d['config']['quota_total'] ?? 50)];
    }

    /** Enlace y datos básicos de una publicación. */
    public function datosPublicacion(string $token, string $idMedia): array
    {
        $r = $this->http('GET', $this->graph(rawurlencode($idMedia)), [
            'fields' => 'id,permalink,media_type,media_product_type,timestamp,like_count,comments_count',
        ], $token);
        return [
            'enlace' => (string) ($r['permalink'] ?? ''), 'tipo' => (string) ($r['media_product_type'] ?? $r['media_type'] ?? ''),
            'me_gusta' => isset($r['like_count']) ? (int) $r['like_count'] : null,
            'comentarios' => isset($r['comments_count']) ? (int) $r['comments_count'] : null,
        ];
    }

    /** Métricas que pide el sistema por formato (nombres de Meta, noviembre de 2026). */
    private const METRICAS = [
        'imagen'   => ['reach', 'views', 'likes', 'comments', 'saved', 'shares', 'total_interactions'],
        'carrusel' => ['reach', 'views', 'likes', 'comments', 'saved', 'shares', 'total_interactions'],
        'reel'     => ['reach', 'views', 'likes', 'comments', 'saved', 'shares', 'total_interactions'],
    ];

    /**
     * Métricas de una publicación: [nombre_meta => valor]. Meta devuelve vacío
     * (no cero) si aún no hay datos; una métrica que ya no exista no rompe las demás.
     */
    public function metricasPublicacion(string $token, string $idMedia, string $formato): array
    {
        $nombres = self::METRICAS[$formato] ?? self::METRICAS['imagen'];
        try {
            $r = $this->http('GET', $this->graph(rawurlencode($idMedia) . '/insights'), ['metric' => implode(',', $nombres)], $token);
        } catch (\RuntimeException $e) {
            // Una métrica retirada por Meta hace fallar toda la consulta: se reintenta de a una.
            $r = ['data' => []];
            foreach ($nombres as $n) {
                try {
                    $r['data'] = array_merge($r['data'], $this->http('GET', $this->graph(rawurlencode($idMedia) . '/insights'), ['metric' => $n], $token)['data'] ?? []);
                } catch (\RuntimeException $e2) {
                    // sin esa métrica
                }
            }
        }
        $out = [];
        foreach ((array) ($r['data'] ?? []) as $m) {
            $v = $m['total_value']['value'] ?? ($m['values'][0]['value'] ?? null);
            if (isset($m['name']) && is_numeric($v)) {
                $out[(string) $m['name']] = (int) $v;
            }
        }
        return $out;
    }

    /** Seguidores actuales de la cuenta. */
    public function seguidores(string $token): ?int
    {
        $r = $this->http('GET', $this->graph('me'), ['fields' => 'followers_count'], $token);
        return isset($r['followers_count']) ? (int) $r['followers_count'] : null;
    }

    /**
     * $bearer: token en la cabecera Authorization (envíos); $json: cuerpo JSON en
     * vez de formulario. Las llamadas de login siguen con el token en la consulta,
     * como las documenta Meta. Protegido solo para que las pruebas lo sustituyan.
     */
    protected function http(string $metodo, string $url, array $datos, ?string $bearer = null, bool $json = false): array
    {
        if (!$this->configurado()) {
            throw new \RuntimeException('La empresa no tiene registrada su app de Meta. Regístrela en Configuración del CRM → Canales.');
        }
        $ch = curl_init();
        if ($metodo === 'GET' && $datos) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($datos);
        }
        $cabeceras = ['Accept: application/json'];
        if ($bearer !== null) {
            $cabeceras[] = 'Authorization: Bearer ' . $bearer;
        }
        if ($json) {
            $cabeceras[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXION,
            CURLOPT_TIMEOUT        => self::TIMEOUT_TOTAL,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,   // droplets sin ruta IPv6 se cuelgan hasta el timeout
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $cabeceras,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($datos, JSON_UNESCAPED_UNICODE) : http_build_query($datos));
        }
        $cuerpo = curl_exec($ch);
        $errno  = curl_errno($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'Instagram no respondió a tiempo. Intente de nuevo en unos minutos.'
                : 'No se pudo conectar con Instagram (error de red ' . $errno . ').');
        }
        $json = json_decode((string) $cuerpo, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Respuesta inesperada de Instagram (HTTP ' . $codigo . ').');
        }
        if ($codigo >= 400 || isset($json['error']) || isset($json['error_type'])) {
            throw new \RuntimeException('Instagram rechazó la operación: ' . $this->mensajeError($json, $codigo));
        }
        return $json;
    }

    /** Mensaje de error de Meta en sus dos formatos ({error:{message}} o {error_message}). */
    private function mensajeError(array $j, int $codigo): string
    {
        $msg = $j['error']['message'] ?? $j['error_message'] ?? $j['error']['error_user_msg'] ?? null;
        if (is_string($j['error'] ?? null)) {
            $msg = $j['error_description'] ?? $j['error'];
        }
        $msg = trim((string) ($msg ?: 'HTTP ' . $codigo));
        // Por si Meta repite el token en el texto: nunca mostrarlo.
        return (string) preg_replace('/(access_token=)[^&\s]+/i', '$1***', mb_substr($msg, 0, 300));
    }
}
