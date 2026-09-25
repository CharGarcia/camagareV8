<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\VendedorVisibleRepository;
use App\Rules\modulos\VendedorVisibleRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * "Vendedores que puede ver": vendedores adicionales (además del suyo) cuyas
 * ventas ve un usuario de nivel 1 en el Reporte de Ventas por Vendedor. Se
 * configura en /config/permisos-modulos (niveles 2 y 3).
 */
class VendedorVisibleService
{
    public function __construct(
        private VendedorVisibleRepository $repository,
        private VendedorVisibleRules $rules,
        private LogSistemaService $logService
    ) {
    }

    public function getVendedoresConMarca(int $idEmpresa, int $idUsuario): array
    {
        return $this->repository->getVendedoresConMarca($idEmpresa, $idUsuario);
    }

    /**
     * Habilita o quita un vendedor de la lista del usuario. Idempotente: marcar
     * uno que ya está (o quitar uno que no está) no hace nada.
     *
     * @param array|null $usuarioDestino Fila del usuario (con `nivel`), leída por el llamador.
     */
    public function establecer(
        int $idActor,
        int $nivelActor,
        int $idEmpresa,
        ?array $usuarioDestino,
        int $idUsuario,
        int $idVendedor,
        bool $visible
    ): void {
        if (!$this->repository->disponible()) {
            throw new Exception('Falta ejecutar database/usuarios_vendedores_visibles.sql en la base de datos.');
        }
        $this->rules->validarActor($nivelActor);
        $this->rules->validarUsuarioDestino($usuarioDestino);
        $this->rules->validarVendedor($this->repository->getVendedor($idEmpresa, $idVendedor));

        $this->repository->beginTransaction();
        try {
            $this->repository->lock($idEmpresa, $idUsuario);
            $actual = $this->repository->getAsignacion($idEmpresa, $idUsuario, $idVendedor);

            if ($visible && !$actual) {
                $datos = ['id_usuario' => $idUsuario, 'id_vendedor' => $idVendedor];
                $id = $this->repository->crear($idEmpresa, $idUsuario, $idVendedor, $idActor);
                $this->logService->registrar($idActor, $idEmpresa, 'crear', 'usuarios_vendedores_visibles', $id, null, $datos);
            } elseif (!$visible && $actual) {
                $this->repository->eliminar((int) $actual['id'], $idEmpresa, $idActor);
                $this->logService->registrar($idActor, $idEmpresa, 'eliminar', 'usuarios_vendedores_visibles', (int) $actual['id'], $actual, null);
            }

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
