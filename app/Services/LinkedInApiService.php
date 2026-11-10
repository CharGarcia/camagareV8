<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Único punto del sistema que habla con la API de LinkedIn (publicaciones, fase 3).
 * Diseño: docs/diseno/crm-redes-fase3.md §2.
 *
 *  - Autorizar:   https://www.linkedin.com/oauth/v2/authorization
 *  - Código→token POST https://www.linkedin.com/oauth/v2/accessToken (≈60 días)
 *  - Perfil:      GET  https://api.linkedin.com/v2/userinfo (OpenID: sub, name)
 *  - Páginas:     GET  /rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED
 *                 (requiere Community Management API aprobada)
 *  - Imagen:      POST /rest/images?action=initializeUpload → PUT del archivo a uploadUrl
 *  - Publicar:    POST /rest/posts (el id llega en la cabecera x-restli-id)
 *
 * Cabeceras REST: LinkedIn-Version (config/app.php → linkedin.api_version) y
 * X-Restli-Protocol-Version 2.0.0. Cada empresa usa SU app (crm_linkedin_apps).
 * Los tokens nunca se registran ni se incluyen en los mensajes de error.
 */
class LinkedInApiService
{
    private const URL_AUTORIZAR = 'https://www.linkedin.com/oauth/v2/authorization';
    private const URL_TOKEN     = 'https://www.linkedin.com/oauth/v2/accessToken';
    private const URL_API       = 'https://api.linkedin.com';

    private const TIMEOUT_CONEXION = 8;
    private const TIMEOUT_TOTAL    = 30;
    /** Tope de LinkedIn para el texto de una publicación. */
    public const MAX_TEXTO = 3000;

    private array $cfg;
    private static array $porEmpresa = [];

    public function __construct(array $cfg = [])
    {
        $this->cfg = $cfg + self::configTecnica() + ['client_id' => '', 'client_secret' => '', 'paginas_aprobadas' => false];
    }

    public static function configTecnica(): array
    {
        static $tec = null;
        if ($tec === null) {
            $app = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
            $l = (array) ($app['linkedin'] ?? []);
            $tec = [
                'api_version'   => (string) ($l['api_version'] ?? '202610'),
                'scopes_perfil' => (array) ($l['scopes_perfil'] ?? ['openid', 'profile', 'w_member_social']),
                'scopes_pagina' => (array) ($l['scopes_pagina'] ?? ['w_organization_social', 'r_organization_social', 'rw_organization_admin']),
            ];
        }
        return $tec;
    }

    /** Cliente con la app de LinkedIn de la empresa (o uno sin configurar). */
    public static function paraEmpresa(int $idEmpresa): self
    {
        if (!isset(self::$porEmpresa[$idEmpresa])) {
            $a = (new \App\repositories\modulos\CrmLinkedinAppRepository())->getPorEmpresa($idEmpresa);
            self::$porEmpresa[$idEmpresa] = new self($a && $a['estado'] === 'activo' ? [
                'client_id'         => (string) $a['client_id'],
                'client_secret'     => \App\Helpers\CryptoHelper::desencriptar((string) $a['client_secret_cifrado']),
                'paginas_aprobadas' => in_array($a['paginas_aprobadas'], [true, 't', 'true', 1, '1'], true),
            ] : []);
        }
        return self::$porEmpresa[$idEmpresa];
    }

    public static function olvidarEmpresa(int $idEmpresa): void
    {
        unset(self::$porEmpresa[$idEmpresa]);
    }

    public function configurado(): bool
    {
        return trim((string) $this->cfg['client_id']) !== '' && trim((string) $this->cfg['client_secret']) !== '';
    }

    public function paginasAprobadas(): bool
    {
        return (bool) $this->cfg['paginas_aprobadas'];
    }

    // ── OAuth ────────────────────────────────────────────────────────────────

    /** $paginas: además pide los permisos de página (solo si la app los tiene aprobados). */
    public function urlAutorizacion(string $state, string $redirectUri, bool $paginas): string
    {
        $scopes = $this->cfg['scopes_perfil'];
        if ($paginas) {
            if (!$this->paginasAprobadas()) {
                throw new \InvalidArgumentException('La app de LinkedIn de la empresa no tiene aprobada la publicación en páginas (Community Management API).');
            }
            $scopes = array_merge($scopes, $this->cfg['scopes_pagina']);
        }
        return self::URL_AUTORIZAR . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->cfg['client_id'],
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => implode(' ', array_unique($scopes)),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{token:string, expira_en:int, permisos:array} */
    public function canjearCodigo(string $code, string $redirectUri): array
    {
        $r = $this->http('POST', self::URL_TOKEN, [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri,
            'client_id' => $this->cfg['client_id'], 'client_secret' => $this->cfg['client_secret'],
        ], null, 'form');
        $j = $r['json'];
        if (empty($j['access_token'])) {
            throw new \RuntimeException('LinkedIn no entregó el acceso. Vuelva a intentar la conexión.');
        }
        return [
            'token' => (string) $j['access_token'],
            'expira_en' => (int) ($j['expires_in'] ?? 5184000),
            'permisos' => array_values(array_filter(preg_split('/[\s,]+/', (string) ($j['scope'] ?? '')) ?: [])),
        ];
    }

    /** Persona que autorizó. @return array{urn:string, nombre:string} */
    public function perfil(string $token): array
    {
        $j = $this->http('GET', self::URL_API . '/v2/userinfo', [], $token, 'ninguno')['json'];
        if (empty($j['sub'])) {
            throw new \RuntimeException('LinkedIn no devolvió los datos del perfil.');
        }
        return ['urn' => 'urn:li:person:' . $j['sub'], 'nombre' => trim((string) ($j['name'] ?? ''))];
    }

    /**
     * Páginas de empresa que administra quien autorizó.
     * @return list<array{urn:string, nombre:string}>
     */
    public function paginasAdministradas(string $token): array
    {
        $j = $this->rest('GET', '/rest/organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED'], $token)['json'];
        $out = [];
        foreach ((array) ($j['elements'] ?? []) as $e) {
            $urn = (string) ($e['organization'] ?? $e['organizationTarget'] ?? '');
            if (!preg_match('/^urn:li:organization:(\d+)$/', $urn, $m)) {
                continue;
            }
            $nombre = '';
            try {
                $o = $this->rest('GET', '/rest/organizations/' . $m[1], [], $token)['json'];
                $nombre = (string) ($o['localizedName'] ?? '');
            } catch (\RuntimeException $ex) {
                // sin nombre: se muestra el número
            }
            $out[] = ['urn' => $urn, 'nombre' => $nombre !== '' ? $nombre : 'Página ' . $m[1]];
        }
        return $out;
    }

    // ── Publicar ─────────────────────────────────────────────────────────────

    /** Sube una imagen para el autor y devuelve su URN (urn:li:image:…). */
    public function subirImagen(string $token, string $autorUrn, string $rutaArchivo): string
    {
        if (!is_file($rutaArchivo)) {
            throw new \RuntimeException('No se encontró la imagen a subir.');
        }
        $j = $this->rest('POST', '/rest/images?action=initializeUpload', ['initializeUploadRequest' => ['owner' => $autorUrn]], $token)['json'];
        $url = (string) ($j['value']['uploadUrl'] ?? '');
        $urn = (string) ($j['value']['image'] ?? '');
        // La subida lleva el token: solo a HTTPS de dominios de LinkedIn.
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $deLinkedin = (bool) preg_match('/(^|\.)(linkedin\.com|licdn\.com)$/', $host);
        if ($url === '' || $urn === '' || parse_url($url, PHP_URL_SCHEME) !== 'https' || !$deLinkedin) {
            throw new \RuntimeException('LinkedIn no preparó la subida de la imagen.');
        }
        $this->http('PUT', $url, ['_archivo' => $rutaArchivo], $token, 'binario');
        return $urn;
    }

    /**
     * Publica en el perfil o la página ($autorUrn). $imagenes: [[urn, alt], …]
     * (0 = solo texto, 1 = imagen, 2-20 = varias). Devuelve el URN de la publicación.
     *
     * $articulo (formato «Enlace»): ['url' => …, 'titulo' => …, 'miniatura' => URN de imagen o null].
     * Sale como tarjeta que lleva a la dirección; LinkedIn no lee la página, así que
     * el título y la imagen son los que se envían aquí. No se combina con $imagenes.
     */
    public function publicar(string $token, string $autorUrn, string $texto, array $imagenes = [], ?array $articulo = null): string
    {
        $cuerpo = [
            'author'       => $autorUrn,
            'commentary'   => self::textoLinkedIn($texto),
            'visibility'   => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];
        $imgs = array_map(fn($i) => array_filter(['id' => (string) $i[0], 'altText' => mb_substr(trim((string) ($i[1] ?? '')), 0, 4086)], fn($v) => $v !== ''), $imagenes);
        if ($articulo !== null) {
            $url = trim((string) ($articulo['url'] ?? ''));
            $titulo = trim((string) ($articulo['titulo'] ?? ''));
            if (!preg_match('#^https?://\S+$#i', $url) || $titulo === '') {
                throw new \InvalidArgumentException('La publicación de enlace necesita la dirección web y un título.');
            }
            $cuerpo['content'] = ['article' => array_filter([
                'source'    => $url,
                'title'     => mb_substr($titulo, 0, 200),
                'thumbnail' => (string) ($articulo['miniatura'] ?? ''),
            ], fn($v) => $v !== '')];
        } elseif (count($imgs) === 1) {
            $cuerpo['content'] = ['media' => $imgs[0]];
        } elseif (count($imgs) > 1) {
            if (count($imgs) > 20) {
                throw new \InvalidArgumentException('LinkedIn admite hasta 20 imágenes por publicación.');
            }
            $cuerpo['content'] = ['multiImage' => ['images' => $imgs]];
        }
        $r = $this->rest('POST', '/rest/posts', $cuerpo, $token);
        $id = $r['cabeceras']['x-restli-id'] ?? $r['cabeceras']['x-linkedin-id'] ?? ($r['json']['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('LinkedIn no confirmó la publicación.');
        }
        return (string) $id;
    }

    /**
     * Estadísticas de una publicación de PÁGINA (organizationalEntityShareStatistics;
     * requiere r_organization_social). Las de perfiles personales no se pueden leer.
     * @return array{impresiones:?int, alcance:?int, clics:?int, me_gusta:?int, comentarios:?int, compartidos:?int, crudo:array}
     */
    public function estadisticasPublicacion(string $token, string $orgUrn, string $postUrn): array
    {
        if (!preg_match('/^urn:li:organization:\d+$/', $orgUrn) || !preg_match('/^urn:li:(share|ugcPost):[\w-]+$/', $postUrn, $m)) {
            throw new \InvalidArgumentException('Solo hay estadísticas de publicaciones de páginas de empresa.');
        }
        // Rest.li 2.0: la lista va como List(urn) con la URN codificada; no pasa por http_build_query.
        $ruta = '/rest/organizationalEntityShareStatistics?q=organizationalEntity&organizationalEntity=' . rawurlencode($orgUrn)
              . '&' . ($m[1] === 'share' ? 'shares' : 'ugcPosts') . '=List(' . rawurlencode($postUrn) . ')';
        $j = $this->rest('GET', $ruta, [], $token)['json'];
        $s = $j['elements'][0]['totalShareStatistics'] ?? null;
        if (!is_array($s)) {
            return ['impresiones' => null, 'alcance' => null, 'clics' => null, 'me_gusta' => null, 'comentarios' => null, 'compartidos' => null, 'crudo' => []];
        }
        $n = fn($k) => isset($s[$k]) && is_numeric($s[$k]) ? (int) $s[$k] : null;
        return [
            'impresiones' => $n('impressionCount'), 'alcance' => $n('uniqueImpressionsCount'), 'clics' => $n('clickCount'),
            'me_gusta' => $n('likeCount'), 'comentarios' => $n('commentCount'), 'compartidos' => $n('shareCount'), 'crudo' => $s,
        ];
    }

    /** Enlace público de una publicación a partir de su URN. */
    public static function enlace(string $urn): string
    {
        return 'https://www.linkedin.com/feed/update/' . rawurlencode($urn) . '/';
    }

    /**
     * Texto en el formato de LinkedIn («little text»): los caracteres reservados
     * se escapan con \ y los #hashtags se convierten a su plantilla para que
     * sigan siendo hashtags. Las direcciones web van aparte: un «#seccion» dentro
     * de un enlace no es un hashtag (convertirlo rompía el enlace).
     */
    public static function textoLinkedIn(string $texto): string
    {
        $texto = mb_substr(str_replace("\r\n", "\n", trim($texto)), 0, self::MAX_TEXTO);
        $partes = preg_split('/(https?:\/\/\S+|#[\p{L}\p{N}_]+)/iu', $texto, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texto];
        $out = '';
        foreach ($partes as $p) {
            if (preg_match('/^#([\p{L}\p{N}_]+)$/u', $p, $m)) {
                $out .= '{hashtag|\#|' . self::escapar($m[1]) . '}';
            } else {
                $out .= self::escapar($p);
            }
        }
        return $out;
    }

    private static function escapar(string $s): string
    {
        return preg_replace('/([\\\\|{}@\[\]()<>#*_~])/u', '\\\\$1', $s);
    }

    // ── HTTP ─────────────────────────────────────────────────────────────────

    /** Llamada a /rest con las cabeceras de versión. $datos: consulta (GET) o cuerpo JSON. */
    private function rest(string $metodo, string $ruta, array $datos, string $token): array
    {
        $url = self::URL_API . $ruta;
        if ($metodo === 'GET' && $datos) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($datos);
            $datos = [];
        }
        return $this->http($metodo, $url, $datos, $token, $metodo === 'GET' ? 'ninguno' : 'json', [
            'LinkedIn-Version: ' . $this->cfg['api_version'],
            'X-Restli-Protocol-Version: 2.0.0',
        ]);
    }

    /**
     * $cuerpo: form | json | binario (PUT del archivo `_archivo`) | ninguno.
     * Devuelve código, JSON (o []) y cabeceras en minúsculas. Protegido solo para las pruebas.
     * @return array{codigo:int, json:array, cabeceras:array<string,string>}
     */
    protected function http(string $metodo, string $url, array $datos, ?string $token, string $cuerpo, array $extra = []): array
    {
        if (!$this->configurado()) {
            throw new \RuntimeException('La empresa no tiene registrada su app de LinkedIn. Regístrela en Configuración del CRM → Canales.');
        }
        $cab = array_merge(['Accept: application/json'], $extra);
        if ($token !== null) {
            $cab[] = 'Authorization: Bearer ' . $token;
        }
        $ch = curl_init($url);
        $resp = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXION,
            CURLOPT_TIMEOUT        => self::TIMEOUT_TOTAL,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HEADERFUNCTION => function ($c, $linea) use (&$resp) {
                $p = explode(':', $linea, 2);
                if (count($p) === 2) {
                    $resp[strtolower(trim($p[0]))] = trim($p[1]);
                }
                return strlen($linea);
            },
        ]);
        if ($cuerpo === 'form') {
            $cab[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        } elseif ($cuerpo === 'json') {
            $cab[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } elseif ($cuerpo === 'binario') {
            $cab[] = 'Content-Type: application/octet-stream';
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) file_get_contents((string) $datos['_archivo']));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $cab);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            throw new \RuntimeException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'LinkedIn no respondió a tiempo. Intente de nuevo en unos minutos.'
                : 'No se pudo conectar con LinkedIn (error de red ' . $errno . ').');
        }
        $json = json_decode((string) $raw, true);
        $json = is_array($json) ? $json : [];
        if ($codigo >= 400) {
            throw new \RuntimeException('LinkedIn rechazó la operación: ' . self::mensajeError($json, $codigo));
        }
        return ['codigo' => $codigo, 'json' => $json, 'cabeceras' => $resp];
    }

    private static function mensajeError(array $j, int $codigo): string
    {
        $msg = (string) ($j['message'] ?? $j['error_description'] ?? $j['error'] ?? ('HTTP ' . $codigo));
        if ($codigo === 401) {
            $msg = 'el acceso venció o fue revocado; reconecte la cuenta';
        } elseif ($codigo === 403) {
            $msg = 'la app o la persona no tiene permiso para esa acción (revise los productos de la app y el rol en la página)';
        }
        return (string) preg_replace('/(access_token=)[^&\s]+/i', '$1***', mb_substr($msg, 0, 300));
    }
}
