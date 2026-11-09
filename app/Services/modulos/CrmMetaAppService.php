<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmCanalRepository;
use App\repositories\modulos\CrmMetaAppRepository;
use App\Rules\modulos\CrmCanalRules;
use App\Services\InstagramApiService;
use App\Services\LogSistemaService;

/**
 * CRM: app de Meta de la empresa (Configuración del CRM → Canales).
 * Cada empresa registra su propia app; los secretos se guardan cifrados y el
 * App Secret nunca vuelve al navegador (solo se indica si está guardado).
 */
class CrmMetaAppService
{
    public function __construct(
        private CrmMetaAppRepository $repo,
        private CrmCanalRepository $canales,
        private CrmCanalRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CrmMetaAppRepository(), new CrmCanalRepository(), new CrmCanalRules(), new LogSistemaService());
    }

    /**
     * Lo que ve la pantalla. El token de verificación se muestra (hay que pegarlo
     * en Meta); el App Secret no.
     */
    public function obtener(int $idEmpresa): array
    {
        $a = $this->repo->getPorEmpresa($idEmpresa);
        if (!$a) {
            return ['configurada' => false, 'app_id' => '', 'secret_guardado' => false, 'verify_token' => '', 'actualizado' => null, 'actualizado_por' => null,
                    'config_id' => '', 'unificado' => false];
        }
        $configId = trim((string) ($a['config_id'] ?? ''));
        return [
            'configurada'     => true,
            'app_id'          => (string) $a['app_id'],
            'config_id'       => $configId,
            'unificado'       => $configId !== '',
            'secret_guardado' => $a['app_secret_cifrado'] !== '',
            'verify_token'    => CryptoHelper::desencriptar((string) $a['verify_token_cifrado']),
            'actualizado'     => $a['updated_at'] ?: $a['created_at'],
            'actualizado_por' => $a['actualizado_por'],
        ];
    }

    /**
     * Registra o actualiza la app. $secreto vacío al editar = se conserva.
     * $configId: ID de configuración de Meta (Unified login, apps nuevas); vacío =
     * Instagram Login (apps viejas). null = no se toca (llamadores anteriores).
     */
    public function guardar(int $idEmpresa, string $appId, string $secreto, int $idUsuario, ?string $configId = null): void
    {
        $appId = trim($appId);
        $secreto = trim($secreto);
        $configId = $configId === null ? null : trim($configId);
        $this->enTransaccion(function () use ($idEmpresa, $appId, $secreto, $idUsuario, $configId) {
            $this->repo->lockEmpresa($idEmpresa);
            $actual = $this->repo->getPorEmpresa($idEmpresa);
            $this->rules->validarApp($appId, $secreto, $actual === null);
            $admite = $this->repo->admiteConfigId();
            if ($configId !== null) {
                $this->rules->validarConfigId($configId, $admite);
                $antes = trim((string) ($actual['config_id'] ?? ''));
                $cambiaModo = $actual !== null && (($antes !== '') !== ($configId !== ''));
                $activas = $actual ? count(array_filter($this->canales->getPorEmpresa($idEmpresa),
                    fn($c) => $c['tipo'] === 'instagram' && $c['estado'] !== 'desconectado')) : 0;
                $this->rules->validarCambioModo($cambiaModo, $activas);
            }
            $secretCif = $secreto !== '' ? CryptoHelper::encriptar($secreto) : null;
            if ($actual === null) {
                $token = self::nuevoToken();
                $id = $this->repo->insertar($idEmpresa, $appId, (string) $secretCif, CryptoHelper::encriptar($token), hash('sha256', $token), $idUsuario);
                if ($configId !== null && $admite) {
                    $this->repo->guardarConfigId($id, $idEmpresa, $configId);
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_meta_apps', $id, null, ['app_id' => $appId, 'config_id' => $configId ?: null]);
            } else {
                $this->repo->actualizar((int) $actual['id'], $idEmpresa, $appId, $secretCif, null, null, $idUsuario);
                if ($configId !== null && $admite) {
                    $this->repo->guardarConfigId((int) $actual['id'], $idEmpresa, $configId);
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_meta_apps', (int) $actual['id'],
                    ['app_id' => $actual['app_id'], 'config_id' => $actual['config_id'] ?? null],
                    ['app_id' => $appId, 'secret_cambiado' => $secretCif !== null, 'config_id' => $configId ?? ($actual['config_id'] ?? null)]);
            }
            InstagramApiService::olvidarEmpresa($idEmpresa);
        });
    }

    /** Nuevo token de verificación del webhook (hay que pegarlo otra vez en Meta). */
    public function regenerarToken(int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($idEmpresa, $idUsuario) {
            $this->repo->lockEmpresa($idEmpresa);
            $actual = $this->repo->getPorEmpresa($idEmpresa);
            if (!$actual) {
                throw new \InvalidArgumentException('Registre primero la app de Meta.');
            }
            $token = self::nuevoToken();
            $this->repo->actualizar((int) $actual['id'], $idEmpresa, (string) $actual['app_id'], null, CryptoHelper::encriptar($token), hash('sha256', $token), $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'REGENERAR_TOKEN', 'crm_meta_apps', (int) $actual['id'], null, ['verify_token' => 'regenerado']);
            InstagramApiService::olvidarEmpresa($idEmpresa);
        });
    }

    /** Quita la app (solo si no hay cuentas conectadas con ella). */
    public function quitar(int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($idEmpresa, $idUsuario) {
            $this->repo->lockEmpresa($idEmpresa);
            $actual = $this->repo->getPorEmpresa($idEmpresa);
            if (!$actual) {
                throw new \RuntimeException('La empresa no tiene una app de Meta registrada.');
            }
            $activas = count(array_filter($this->canales->getPorEmpresa($idEmpresa), fn($c) => $c['estado'] !== 'desconectado'));
            $this->rules->validarQuitarApp($activas);
            $this->repo->eliminar((int) $actual['id'], $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_meta_apps', (int) $actual['id'], ['app_id' => $actual['app_id']], null);
            InstagramApiService::olvidarEmpresa($idEmpresa);
        });
    }

    private static function nuevoToken(): string
    {
        return bin2hex(random_bytes(20));
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
