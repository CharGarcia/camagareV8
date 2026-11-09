<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmCanalRepository;
use App\repositories\modulos\CrmLinkedinAppRepository;
use App\Services\LinkedInApiService;
use App\Services\LogSistemaService;

/**
 * CRM fase 3: app de LinkedIn de la empresa y conexión de cuentas (perfil
 * personal y, si la app lo tiene aprobado, páginas de empresa).
 * Diseño: docs/diseno/crm-redes-fase3.md §2 (decisiones 1 y 2).
 *
 * Las llamadas a LinkedIn van fuera de la transacción; con la respuesta en
 * mano se escribe en una transacción corta bajo candado por cuenta.
 */
class CrmLinkedinService
{
    public const TIPO_PERFIL = 'linkedin_perfil';
    public const TIPO_PAGINA = 'linkedin_pagina';

    public function __construct(
        private CrmLinkedinAppRepository $apps,
        private CrmCanalRepository $canales,
        private LogSistemaService $log,
        private ?LinkedInApiService $apiFija = null   // solo pruebas
    ) {
    }

    public static function crear(?LinkedInApiService $api = null): self
    {
        return new self(new CrmLinkedinAppRepository(), new CrmCanalRepository(), new LogSistemaService(), $api);
    }

    public function disponible(): bool
    {
        return $this->apps->disponible();
    }

    private function api(int $idEmpresa): LinkedInApiService
    {
        return $this->apiFija ?? LinkedInApiService::paraEmpresa($idEmpresa);
    }

    // ── App de LinkedIn ──────────────────────────────────────────────────────

    /** Datos para la pantalla (nunca el secreto). */
    public function obtenerApp(int $idEmpresa): array
    {
        $a = $this->apps->getPorEmpresa($idEmpresa);
        return [
            'configurada' => (bool) $a,
            'client_id' => (string) ($a['client_id'] ?? ''),
            'secret_guardado' => $a && trim((string) $a['client_secret_cifrado']) !== '',
            'paginas_aprobadas' => $a && in_array($a['paginas_aprobadas'], [true, 't', 'true', 1, '1'], true),
        ];
    }

    /** $secret vacío al editar = se conserva el guardado. */
    public function guardarApp(int $idEmpresa, string $clientId, string $secret, bool $paginas, int $idUsuario): void
    {
        $clientId = trim($clientId);
        $secret = trim($secret);
        if (!preg_match('/^[A-Za-z0-9]{6,60}$/', $clientId)) {
            throw new \InvalidArgumentException('El Client ID de LinkedIn solo tiene letras y números (revíselo en la pestaña Auth de la app).');
        }
        if ($secret !== '' && !preg_match('/^[A-Za-z0-9._=\-]{8,120}$/', $secret)) {
            throw new \InvalidArgumentException('El Client Secret no tiene un formato válido.');
        }
        $this->enTransaccion(function () use ($idEmpresa, $clientId, $secret, $paginas, $idUsuario) {
            $this->apps->lockEmpresa($idEmpresa);
            $antes = $this->apps->getPorEmpresa($idEmpresa);
            if (!$antes) {
                if ($secret === '') {
                    throw new \InvalidArgumentException('Escriba el Client Secret de la app de LinkedIn.');
                }
                $id = $this->apps->insertar($idEmpresa, $clientId, CryptoHelper::encriptar($secret), $paginas, $idUsuario);
            } else {
                $id = (int) $antes['id'];
                $this->apps->actualizar($id, $idEmpresa, $clientId, $secret !== '' ? CryptoHelper::encriptar($secret) : null, $paginas, $idUsuario);
            }
            $this->log->registrar($idUsuario, $idEmpresa, $antes ? 'ACTUALIZAR' : 'CREAR', 'crm_linkedin_apps', $id,
                $antes ? ['client_id' => $antes['client_id'], 'paginas_aprobadas' => $antes['paginas_aprobadas']] : null,
                ['client_id' => $clientId, 'paginas_aprobadas' => $paginas, 'secreto_nuevo' => $secret !== '']);
        });
        LinkedInApiService::olvidarEmpresa($idEmpresa);
    }

    public function quitarApp(int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($idEmpresa, $idUsuario) {
            $this->apps->lockEmpresa($idEmpresa);
            $a = $this->apps->getPorEmpresa($idEmpresa) ?? throw new \RuntimeException('La empresa no tiene app de LinkedIn.');
            $activas = array_filter($this->canales->getPorEmpresa($idEmpresa),
                fn($c) => in_array($c['tipo'], [self::TIPO_PERFIL, self::TIPO_PAGINA], true) && $c['estado'] !== 'desconectado');
            if ($activas) {
                throw new \InvalidArgumentException('Hay cuentas de LinkedIn conectadas con esta app. Desconéctelas primero.');
            }
            $this->apps->eliminar((int) $a['id'], $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_linkedin_apps', (int) $a['id'], ['client_id' => $a['client_id']], null);
        });
        LinkedInApiService::olvidarEmpresa($idEmpresa);
    }

    // ── Conexión ─────────────────────────────────────────────────────────────

    public function urlConexion(string $state, string $redirectUri, int $idEmpresa, bool $paginas): string
    {
        $api = $this->api($idEmpresa);
        if (!$api->configurado()) {
            throw new \InvalidArgumentException('Registre primero la app de LinkedIn de la empresa (Client ID y Client Secret).');
        }
        return $api->urlAutorizacion($state, $redirectUri, $paginas);
    }

    /**
     * Retorno de LinkedIn: código → token → perfil (y páginas que administra si
     * se pidieron). Da de alta o reconecta el perfil y cada página.
     * @return array{cuentas:list<string>, avisos:list<string>}
     */
    public function completarConexion(string $code, string $redirectUri, int $idEmpresa, int $idUsuario, bool $paginas): array
    {
        $api = $this->api($idEmpresa);
        $tok = $api->canjearCodigo($code, $redirectUri);
        $perfil = $api->perfil($tok['token']);
        $avisos = [];
        if (!in_array('w_member_social', $tok['permisos'], true)) {
            $avisos[] = 'LinkedIn no concedió el permiso de publicar (w_member_social). Revise que la app tenga el producto «Share on LinkedIn» y vuelva a conectar.';
        }
        $cuentas = [[
            'tipo' => self::TIPO_PERFIL, 'id_externo' => $perfil['urn'], 'usuario' => $perfil['nombre'] ?: 'Perfil de LinkedIn',
            'nombre' => $perfil['nombre'] ?: 'Perfil de LinkedIn',
        ]];
        if ($paginas) {
            try {
                foreach ($api->paginasAdministradas($tok['token']) as $pg) {
                    $cuentas[] = ['tipo' => self::TIPO_PAGINA, 'id_externo' => $pg['urn'], 'usuario' => $pg['nombre'], 'nombre' => $pg['nombre']];
                }
                if (count($cuentas) === 1) {
                    $avisos[] = 'No se encontraron páginas de empresa que usted administre en LinkedIn.';
                }
            } catch (\RuntimeException $e) {
                $avisos[] = 'Se conectó el perfil, pero no se pudieron leer las páginas: ' . $e->getMessage();
            }
        }
        $comun = [
            'token_cifrado' => CryptoHelper::encriptar($tok['token']),
            'token_expira_at' => date('Y-m-d H:i:s', time() + $tok['expira_en']),
            'permisos' => $tok['permisos'],
        ];
        $nombres = [];
        $this->enTransaccion(function () use ($cuentas, $comun, $idEmpresa, $idUsuario, &$nombres, &$avisos) {
            foreach ($cuentas as $c) {
                $this->canales->lockExterno($c['tipo'], $c['id_externo']);
                $existente = $this->canales->buscarPorExterno($c['tipo'], $c['id_externo']);
                if ($existente && (int) $existente['id_empresa'] !== $idEmpresa) {
                    if ($existente['estado'] !== 'desconectado') {
                        $avisos[] = "«{$c['nombre']}» ya está conectada a otra empresa del sistema; no se conectó aquí.";
                        continue;
                    }
                    $this->canales->liberar((int) $existente['id'], $idUsuario);
                    $existente = null;
                }
                $datos = $c + $comun;
                if ($existente) {
                    $id = (int) $existente['id'];
                    $this->canales->reconectar($id, $idEmpresa, $datos, $idUsuario);
                    $accion = 'RECONECTAR';
                } else {
                    $id = $this->canales->insertar($idEmpresa, $datos + ['config' => ['conectado_at' => date('Y-m-d H:i:s')]], $idUsuario);
                    $accion = 'CONECTAR';
                }
                $this->log->registrar($idUsuario, $idEmpresa, $accion, 'crm_canales', $id, null,
                    ['tipo' => $c['tipo'], 'nombre' => $c['nombre'], 'id_externo' => $c['id_externo'], 'permisos' => $comun['permisos']]);
                $nombres[] = $c['nombre'];
            }
        });
        return ['cuentas' => $nombres, 'avisos' => $avisos];
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
