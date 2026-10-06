<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VendedorOcultoRepository;
use App\Rules\modulos\VendedorOcultoRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * "Vendedores que puede ver — {módulo}": de qué vendedores ve la información un
 * usuario de nivel 1 en un módulo del catálogo App\Helpers\VendedoresModulo
 * (hoy, Reporte de Ventas). Se configura en /config/permisos-modulos (niveles
 * 2 y 3).
 *
 * Se guardan solo los vendedores ocultos: sin configuración, el usuario ve a
 * todos. Cómo se aplica al reporte: AlcanceRegistros::acotarAVendedoresVisibles().
 */
class VendedorOcultoService
{
    public function __construct(
        private VendedorOcultoRepository $repository,
        private VendedorOcultoRules $rules,
        private LogSistemaService $logService
    ) {
    }

    /** Instancia lista para usar desde un controlador. */
    public static function crear(): self
    {
        return new self(new VendedorOcultoRepository(), new VendedorOcultoRules(), new LogSistemaService());
    }

    /** Vendedores de la empresa con la marca `visible`, para la tarjeta de configuración. */
    public function getVendedoresConMarca(int $idEmpresa, int $idUsuario, string $modulo): array
    {
        return $this->repository->getVendedoresConMarca($idEmpresa, $idUsuario, $modulo);
    }

    /**
     * Muestra u oculta un vendedor al usuario en el módulo. Idempotente: mostrar
     * uno que ya se ve (u ocultar uno ya oculto) no hace nada. Nunca deja ocultos
     * a todos los vendedores.
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
        int $idVendedor,
        bool $visible
    ): void {
        if (!$this->repository->disponible()) {
            throw new Exception('Falta ejecutar database/2026-10-06_usuarios_vendedores_ocultos.sql en la base de datos.');
        }
        $this->rules->validarActor($nivelActor);
        $this->rules->validarUsuarioDestino($usuarioDestino);
        $this->rules->validarModulo($modulo);
        $this->rules->validarVendedor($this->repository->getVendedor($idEmpresa, $idVendedor));

        $this->repository->beginTransaction();
        try {
            $this->repository->lock($idEmpresa, $idUsuario, $modulo);
            $actual = $this->repository->getOcultacion($idEmpresa, $idUsuario, $modulo, $idVendedor);

            if (!$visible && !$actual) {
                // Bajo el candado: el conteo de ocultos es el real aunque haya dos clics seguidos.
                $ocultos = $this->repository->getIdsOcultos($idEmpresa, $idUsuario, $modulo);
                $this->rules->validarQuedaAlgunoVisible(
                    count($this->repository->getIdsVendedoresEmpresa($idEmpresa)),
                    count($ocultos) + 1
                );
                $datos = ['id_usuario' => $idUsuario, 'modulo' => $modulo, 'id_vendedor' => $idVendedor];
                $id = $this->repository->crear($idEmpresa, $idUsuario, $modulo, $idVendedor, $idActor);
                $this->logService->registrar($idActor, $idEmpresa, 'crear', 'usuarios_vendedores_ocultos', $id, null, $datos);
            } elseif ($visible && $actual) {
                $this->repository->eliminar((int) $actual['id'], $idEmpresa, $idActor);
                $this->logService->registrar($idActor, $idEmpresa, 'eliminar', 'usuarios_vendedores_ocultos', (int) $actual['id'], $actual, null);
            }

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
