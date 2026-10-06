<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\PestanasModulo;
use App\repositories\modulos\PestanasModuloRepository;
use App\Rules\modulos\PestanasModuloRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * "Pestañas que puede ver": qué pestañas de un módulo configurable (catálogo
 * App\Helpers\PestanasModulo) ve un usuario de nivel 1 en una empresa. Se
 * configura en /config/permisos-modulos (niveles 2 y 3).
 *
 * Se guardan solo las pestañas ocultas: sin configuración, el usuario ve todas.
 * Los niveles 2 y 3 ven todas siempre.
 */
class PestanasModuloService
{
    public function __construct(
        private PestanasModuloRepository $repository,
        private PestanasModuloRules $rules,
        private LogSistemaService $logService
    ) {
    }

    /** Instancia lista para usar desde un controlador. */
    public static function crear(): self
    {
        return new self(new PestanasModuloRepository(), new PestanasModuloRules(), new LogSistemaService());
    }

    /**
     * Pestañas del módulo, en el orden de su barra, con si el usuario las ve:
     * clave => bool. Es lo que el controlador del módulo usa para dibujar la
     * barra y para proteger los datos de cada pestaña. Si el módulo no está en
     * el catálogo, devuelve vacío.
     *
     * @return array<string,bool>
     */
    public function visibles(int $idEmpresa, int $idUsuario, int $nivel, string $modulo): array
    {
        $pestanas = PestanasModulo::pestanas($modulo);
        if (!$pestanas) {
            return [];
        }
        $ocultas = $nivel >= 2 ? [] : $this->repository->getOcultas($idEmpresa, $idUsuario, $modulo);
        $out = [];
        foreach (array_keys($pestanas) as $clave) {
            $out[$clave] = !in_array($clave, $ocultas, true);
        }
        return $out;
    }

    /**
     * Pestañas del módulo para la tarjeta de configuración: clave, título,
     * ícono y la marca `visible`.
     */
    public function getPestanasConMarca(int $idEmpresa, int $idUsuario, string $modulo): array
    {
        $ocultas = $this->repository->getOcultas($idEmpresa, $idUsuario, $modulo);
        $out = [];
        foreach (PestanasModulo::pestanas($modulo) as $clave => $def) {
            $out[] = [
                'clave'   => $clave,
                'titulo'  => $def['titulo'],
                'icono'   => $def['icono'],
                'visible' => !in_array($clave, $ocultas, true),
            ];
        }
        return $out;
    }

    /**
     * Muestra u oculta una pestaña al usuario. Idempotente: mostrar una que ya se
     * ve (u ocultar una ya oculta) no hace nada.
     *
     * @param array|null $usuarioDestino Fila del usuario (con `nivel`), leída por el llamador.
     */
    public function establecer(
        int $idActor,
        int $nivelActor,
        int $idEmpresa,
        ?array $usuarioDestino,
        int $idUsuario,
        string $modulo,
        string $pestana,
        bool $visible
    ): void {
        if (!$this->repository->disponible()) {
            throw new Exception('Falta ejecutar database/2026-10-06_usuarios_pestanas_ocultas.sql en la base de datos.');
        }
        $this->rules->validarActor($nivelActor);
        $this->rules->validarUsuarioDestino($usuarioDestino);
        $this->rules->validarPestana($modulo, $pestana);

        $this->repository->beginTransaction();
        try {
            $this->repository->lock($idEmpresa, $idUsuario, $modulo);
            $actual = $this->repository->getOcultacion($idEmpresa, $idUsuario, $modulo, $pestana);

            if (!$visible && !$actual) {
                $datos = ['id_usuario' => $idUsuario, 'modulo' => $modulo, 'pestana' => $pestana];
                $id = $this->repository->crear($idEmpresa, $idUsuario, $modulo, $pestana, $idActor);
                $this->logService->registrar($idActor, $idEmpresa, 'crear', 'usuarios_pestanas_ocultas', $id, null, $datos);
            } elseif ($visible && $actual) {
                $this->repository->eliminar((int) $actual['id'], $idEmpresa, $idActor);
                $this->logService->registrar($idActor, $idEmpresa, 'eliminar', 'usuarios_pestanas_ocultas', (int) $actual['id'], $actual, null);
            }

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
