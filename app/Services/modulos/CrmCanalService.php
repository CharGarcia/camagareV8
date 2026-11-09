<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmCanalRepository;
use App\repositories\modulos\CrmCatalogoRepository;
use App\Rules\modulos\CrmCanalRules;
use App\Services\ErrorLogService;
use App\Services\InstagramApiService;
use App\Services\LogSistemaService;

/**
 * CRM: conexión de cuentas de Instagram por empresa (paso 4 del diseño,
 * docs/diseno/crm-redes-fase1.md §5.1, §9.3, §12).
 *
 * Las llamadas a Instagram van SIEMPRE fuera de la transacción: primero se
 * habla con Meta y, con la respuesta en mano, se escribe en una transacción
 * corta bajo candado por cuenta.
 */
class CrmCanalService
{
    public const TIPO_INSTAGRAM = 'instagram';

    /** Días antes del vencimiento en que el cron renueva el token (dura ~60). */
    public const DIAS_RENOVAR = 10;

    /**
     * $apiFija: solo para pruebas (una API falsa para todas las empresas). En uso
     * normal cada operación usa la app de Meta de SU empresa (crm_meta_apps).
     */
    public function __construct(
        private CrmCanalRepository $repo,
        private CrmCatalogoRepository $catalogos,
        private ?InstagramApiService $apiFija,
        private CrmCanalRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CrmCanalRepository(), new CrmCatalogoRepository(), null, new CrmCanalRules(), new LogSistemaService());
    }

    private function api(int $idEmpresa): InstagramApiService
    {
        return $this->apiFija ?? InstagramApiService::paraEmpresa($idEmpresa);
    }

    /** ¿La empresa registró su app de Meta? */
    public function apiConfigurada(int $idEmpresa): bool
    {
        return $this->api($idEmpresa)->configurado();
    }

    public function getCanales(int $idEmpresa): array
    {
        $canales = $this->repo->getPorEmpresa($idEmpresa);
        $tec = InstagramApiService::configTecnica();
        $api = $this->api($idEmpresa);
        $requeridosPorTipo = [
            'linkedin_perfil'    => ['w_member_social'],
            'linkedin_pagina'    => ['w_organization_social'],
        ];
        foreach ($canales as &$c) {
            // Cada cuenta de Instagram se mide con los permisos del modo con que se conectó.
            $unificada = $c['tipo'] === self::TIPO_INSTAGRAM && (($c['config']['modo'] ?? '') === 'unificado');
            $requeridos = $c['tipo'] === self::TIPO_INSTAGRAM
                ? ($unificada === $api->unificado() ? $api->scopes() : (array) $tec[$unificada ? 'scopes_unificado' : 'scopes'])
                : ($requeridosPorTipo[$c['tipo']] ?? []);
            $c['permisos_faltantes'] = array_values(array_diff($requeridos, (array) $c['permisos']));
            // Fase 3: la cuenta de Instagram se conectó antes de los permisos de publicar.
            $publicar = $c['tipo'] === self::TIPO_INSTAGRAM
                ? ($unificada ? 'instagram_content_publish' : 'instagram_business_content_publish') : ($requeridos[0] ?? '-');
            $c['falta_publicar'] = in_array($publicar, $c['permisos_faltantes'], true);
            $c['unificada'] = $unificada;
            // null = no vence (Unified login: token del portafolio).
            $c['dias_para_vencer'] = $c['token_expira_at']
                ? (int) floor((strtotime($c['token_expira_at']) - time()) / 86400) : null;
        }
        return $canales;
    }

    public function urlConexion(string $state, string $redirectUri, int $idEmpresa): string
    {
        $api = $this->api($idEmpresa);
        if (!$api->configurado()) {
            throw new \InvalidArgumentException('Registre primero la app de Meta de la empresa (App ID y App Secret) en esta misma pestaña.');
        }
        return $api->urlAutorizacion($state, $redirectUri);
    }

    /**
     * Retorno de la autorización de Instagram: código → token corto → token largo →
     * perfil → suscripción a webhooks → alta o reconexión del canal.
     * @return array{id:int, usuario:string, avisos:string[]}
     */
    public function completarConexion(string $code, string $redirectUri, int $idEmpresa, int $idUsuario): array
    {
        if ($this->api($idEmpresa)->unificado()) {
            return $this->completarConexionUnificada($code, $redirectUri, $idEmpresa, $idUsuario);
        }
        // 1. Meta (sin transacción abierta), con la app de la empresa.
        $api    = $this->api($idEmpresa);
        $corto  = $api->canjearCodigo($code, $redirectUri);
        $largo  = $api->tokenLargo($corto['token']);
        $perfil = $api->perfil($largo['token']);
        $this->rules->validarTipoCuenta($perfil['account_type']);

        $avisos = [];
        $faltan = array_diff($api->scopes(), $corto['permisos']);
        if ($faltan) {
            $avisos[] = 'No se concedieron todos los permisos (' . implode(', ', $faltan) . '). Sin ellos no llegarán mensajes o comentarios; vuelva a conectar y acepte todo.';
        }
        $webhooksOk = false;
        try {
            $webhooksOk = $api->suscribirWebhooks($largo['token']);
        } catch (\Throwable $e) {
            $avisos[] = 'La cuenta quedó conectada, pero Instagram no aceptó la suscripción a mensajes: ' . $e->getMessage();
        }

        $datos = [
            'tipo'            => self::TIPO_INSTAGRAM,
            'id_externo'      => $perfil['user_id'],
            'usuario'         => $perfil['username'],
            'nombre'          => $perfil['name'] ?: $perfil['username'],
            'token_cifrado'   => CryptoHelper::encriptar($largo['token']),
            'token_expira_at' => date('Y-m-d H:i:s', time() + $largo['expira_en']),
            'permisos'        => $corto['permisos'],
        ];
        $id = $this->guardarCuenta($datos, [
            'webhooks_ok' => $webhooksOk, 'cuenta_tipo' => $perfil['account_type'], 'conectado_at' => date('Y-m-d H:i:s'),
            'modo' => 'instagram',
        ], $idEmpresa, $idUsuario);
        return ['id' => $id, 'usuario' => $perfil['username'], 'avisos' => $avisos];
    }

    /**
     * Unified login (decisiones 3 y 4 de crm-redes-unified-login.md): código → token
     * del portafolio (no vence) → cuentas que el cliente compartió → por cada una:
     * datos, suscripción a webhooks y alta/reconexión. Un canal por cuenta, todos con
     * el mismo token. Una cuenta conectada a otra empresa se omite con aviso.
     * @return array{id:int, usuario:string, avisos:string[]}
     */
    private function completarConexionUnificada(string $code, string $redirectUri, int $idEmpresa, int $idUsuario): array
    {
        $api = $this->api($idEmpresa);
        $tok = $api->canjearCodigo($code, $redirectUri);
        $token = $tok['token'];
        $avisos = [];

        $permisos = [];
        try {
            $permisos = $api->permisosConcedidos($token);
        } catch (\RuntimeException $e) {
            $avisos[] = 'No se pudo leer qué permisos se concedieron: ' . $e->getMessage();
        }
        $faltan = $permisos ? array_values(array_diff($api->scopes(), $permisos)) : [];
        if ($faltan) {
            $avisos[] = 'La configuración de Meta no concede: ' . implode(', ', $faltan)
                . '. Agréguelos a la configuración de inicio de sesión de la app y vuelva a conectar.';
        }
        $portafolio = '';
        try {
            $portafolio = $api->clienteBusinessId($token);
        } catch (\RuntimeException $e) {
            // solo informativo
        }
        $cuentas = $api->cuentasAsignadas($token);
        if (!$cuentas) {
            throw new \InvalidArgumentException('Meta no compartió ninguna cuenta de Instagram. Vuelva a conectar y, en la ventana de Meta, '
                . 'elija el portafolio comercial y la cuenta de Instagram (debe ser profesional).');
        }

        $conectadas = [];
        $primerId = 0;
        foreach ($cuentas as $cta) {
            try {
                $perfil = $api->perfil($token, $cta['id']);
            } catch (\RuntimeException $e) {
                if ($e instanceof \App\Services\InstagramTokenInvalidoException) {
                    throw $e;
                }
                $perfil = ['user_id' => $cta['id'], 'username' => $cta['username'], 'name' => '', 'account_type' => 'BUSINESS'];
            }
            $usuario = $perfil['username'] !== '' ? $perfil['username'] : $cta['username'];
            $webhooksOk = false;
            try {
                $webhooksOk = $api->suscribirWebhooks($token, $cta['id']);
            } catch (\Throwable $e) {
                $avisos[] = "@{$usuario} quedó conectada, pero Instagram no aceptó la suscripción a mensajes: " . $e->getMessage();
            }
            $datos = [
                'tipo'            => self::TIPO_INSTAGRAM,
                'id_externo'      => $cta['id'],
                'usuario'         => $usuario,
                'nombre'          => $perfil['name'] ?: $usuario,
                'token_cifrado'   => CryptoHelper::encriptar($token),
                'token_expira_at' => $tok['expira_en'] > 0 ? date('Y-m-d H:i:s', time() + $tok['expira_en']) : null,
                'permisos'        => $permisos,
            ];
            try {
                $id = $this->guardarCuenta($datos, [
                    'webhooks_ok' => $webhooksOk, 'cuenta_tipo' => 'BUSINESS', 'conectado_at' => date('Y-m-d H:i:s'),
                    'modo' => 'unificado', 'client_business_id' => $portafolio,
                ], $idEmpresa, $idUsuario);
            } catch (\InvalidArgumentException $e) {
                if (count($cuentas) === 1) {
                    throw $e;
                }
                $avisos[] = $e->getMessage();
                continue;
            }
            $primerId = $primerId ?: $id;
            $conectadas[] = '@' . $usuario;
        }
        if (!$conectadas) {
            throw new \InvalidArgumentException('Ninguna de las cuentas compartidas se pudo conectar. ' . implode(' ', $avisos));
        }
        return ['id' => $primerId, 'usuario' => implode(', ', array_map(fn($u) => ltrim($u, '@'), $conectadas)), 'avisos' => $avisos];
    }

    /**
     * Alta o reconexión de una cuenta, bajo candado por cuenta (transacción corta).
     * Una cuenta activa en otra empresa no se toca (InvalidArgumentException).
     */
    private function guardarCuenta(array $datos, array $ajustes, int $idEmpresa, int $idUsuario): int
    {
        $perfil = ['user_id' => $datos['id_externo'], 'username' => $datos['usuario']];
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->repo->lockExterno(self::TIPO_INSTAGRAM, $perfil['user_id']);
            $existente = $this->repo->buscarPorExterno(self::TIPO_INSTAGRAM, $perfil['user_id']);
            if ($existente && (int) $existente['id_empresa'] !== $idEmpresa) {
                if ($existente['estado'] !== 'desconectado') {
                    throw new \InvalidArgumentException(
                        "La cuenta @{$perfil['username']} ya está conectada a otra empresa del sistema. Desconéctela allí primero."
                    );
                }
                $this->repo->liberar((int) $existente['id'], $idUsuario);
                $existente = null;
            }
            if ($existente) {
                $id = (int) $existente['id'];
                $this->repo->reconectar($id, $idEmpresa, $datos, $idUsuario);
                $accion = 'RECONECTAR';
            } else {
                $datos['config'] = ['asignacion' => ['regla' => 'sin_asignar', 'usuarios' => []]];
                $id = $this->repo->insertar($idEmpresa, $datos, $idUsuario);
                $accion = 'CONECTAR';
            }
            $this->repo->guardarAjustes($id, $idEmpresa, $ajustes, null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $accion, 'crm_canales', $id, null, [
                'tipo' => self::TIPO_INSTAGRAM, 'usuario' => $perfil['username'], 'id_externo' => $perfil['user_id'],
                'permisos' => $datos['permisos'], 'webhooks_ok' => $ajustes['webhooks_ok'] ?? null, 'modo' => $ajustes['modo'] ?? 'instagram',
            ]);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * Meta dijo que el acceso de un canal ya no sirve (error 190): el canal pasa a
     * «token_vencido» y la pantalla pide Reconectar (decisión 5). Nunca falla.
     */
    public static function accesoRevocado(int $idCanal, int $idEmpresa): void
    {
        try {
            if ((new CrmCanalRepository())->marcarRevocado($idCanal, $idEmpresa)) {
                (new LogSistemaService())->registrar(0, $idEmpresa, 'TOKEN_REVOCADO', 'crm_canales', $idCanal,
                    ['estado' => 'activo'], ['estado' => 'token_vencido']);
            }
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'accesoRevocado', 'id_canal' => $idCanal]);
        }
    }

    public function desconectar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario) {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Canal no encontrado.');
            }
            $this->repo->desconectar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'DESCONECTAR', 'crm_canales', $id,
                ['estado' => $antes['estado'], 'usuario' => $antes['usuario']], ['estado' => 'desconectado']);
        });
    }

    /** Comprueba que el token sigue sirviendo leyendo el perfil. */
    public function probar(int $id, int $idEmpresa): array
    {
        $canal = $this->repo->getPorId($id, $idEmpresa);
        if (!$canal) {
            throw new \RuntimeException('Canal no encontrado.');
        }
        $token = CryptoHelper::desencriptar((string) $this->repo->getTokenCifrado($id, $idEmpresa));
        if ($token === '') {
            throw new \InvalidArgumentException('El canal no tiene un acceso vigente. Vuelva a conectar la cuenta.');
        }
        try {
            return $this->api($idEmpresa)->perfil($token, (string) $canal['id_externo']);
        } catch (\App\Services\InstagramTokenInvalidoException $e) {
            self::accesoRevocado($id, $idEmpresa);
            throw $e;
        }
    }

    /** Asignación de conversaciones nuevas (decisión 5) y si la app tiene HUMAN_AGENT aprobado. */
    public function guardarAjustes(int $id, string $regla, array $usuarios, bool $ventanaHumana, int $idEmpresa, int $idUsuario): void
    {
        $validos = array_column($this->catalogos->usuariosEmpresa($idEmpresa), 'id');
        $asignacion = $this->rules->validarAsignacion($regla, $usuarios, $validos);
        $this->enTransaccion(function () use ($id, $asignacion, $ventanaHumana, $idEmpresa, $idUsuario) {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Canal no encontrado.');
            }
            $this->repo->guardarAjustes($id, $idEmpresa, ['asignacion' => $asignacion], $ventanaHumana, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_canales', $id,
                ['asignacion' => $antes['config']['asignacion'] ?? null, 'ventana_humana' => $antes['ventana_humana']],
                ['asignacion' => $asignacion, 'ventana_humana' => $ventanaHumana]);
        });
    }

    /**
     * Cron diario: renueva los tokens que vencen en DIAS_RENOVAR días o menos y
     * marca «token_vencido» los que ya vencieron (esos solo se recuperan
     * reconectando la cuenta). Una falla en un canal no detiene a los demás.
     * @return array{renovados:int, vencidos:int, errores:int}
     */
    public function renovarTokens(): array
    {
        $res = ['renovados' => 0, 'vencidos' => 0, 'errores' => 0];
        foreach ($this->repo->porRenovar(self::DIAS_RENOVAR) as $c) {
            $id = (int) $c['id'];
            $idEmpresa = (int) $c['id_empresa'];
            if (strtotime((string) $c['token_expira_at']) <= time()) {
                $this->repo->marcarEstado($id, 'token_vencido');
                $this->log->registrar(0, $idEmpresa, 'TOKEN_VENCIDO', 'crm_canales', $id, null, ['estado' => 'token_vencido']);
                $res['vencidos']++;
                continue;
            }
            // LinkedIn (fase 3) no entrega cómo renovar a apps sin convenio: se
            // reconecta al vencer (la pantalla avisa con los días que quedan).
            if (($c['tipo'] ?? self::TIPO_INSTAGRAM) !== self::TIPO_INSTAGRAM) {
                continue;
            }
            try {
                $token = CryptoHelper::desencriptar((string) $c['token_cifrado']);
                $nuevo = $this->api($idEmpresa)->renovarToken($token);
                $expira = date('Y-m-d H:i:s', time() + $nuevo['expira_en']);
                $this->repo->actualizarToken($id, CryptoHelper::encriptar($nuevo['token']), $expira);
                $this->log->registrar(0, $idEmpresa, 'RENOVAR_TOKEN', 'crm_canales', $id, null, ['expira' => $expira]);
                $res['renovados']++;
            } catch (\Throwable $e) {
                ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'renovarTokens', 'id_canal' => $id]);
                $res['errores']++;
            }
        }
        return $res;
    }

    private function enTransaccion(callable $fn)
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $r = $fn();
            if ($managed) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
