<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\ApiUsuarioResponsableTrasladoRepository;
use App\repositories\modulos\EntregasConsignacionesRepository;

/**
 * Resumen de entregas de Consignaciones en Ventas (módulo de solo lectura). Reutiliza
 * la misma tabla de evidencia (consignaciones_ventas_entregas) que ya alimentan tanto
 * la app móvil (canal='movil') como el marcado manual "Entregada" desde el sistema
 * (canal='web') en ConsignacionVentaService::cambiarEstado().
 */
class EntregasConsignacionesService
{
    private EntregasConsignacionesRepository $repository;

    public function __construct(EntregasConsignacionesRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Ids de responsables_traslado a los que restringir la vista (repartidor sin
     * "acceso total" solo ve las entregas de los responsables que representa).
     * @return int[]|null null = ve todas.
     */
    public function resolverFiltroResponsables(int $idUsuario, int $idEmpresa, bool $accesoTotal): ?array
    {
        if ($accesoTotal) {
            return null;
        }
        $repo = new ApiUsuarioResponsableTrasladoRepository();
        return $repo->getIdsResponsablesDeUsuario($idUsuario, $idEmpresa);
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?array $idsResponsables): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idsResponsables);
    }

    /** KPIs de la tarjeta superior: totales por canal, incompletas, tiempo promedio y pendientes. */
    public function getResumen(int $idEmpresa, string $buscar, ?array $idsResponsables): array
    {
        $resumen = $this->repository->getResumen($idEmpresa, $buscar, $idsResponsables);
        $resumen['pendientes'] = $this->repository->getPendientes($idEmpresa, $buscar, $idsResponsables);
        return $resumen;
    }

    /** Ruta relativa de la firma de una entrega (validando empresa), o null. Anti path-traversal: solo storage/entregas/. */
    public function getFirmaEntrega(int $idEntrega, int $idEmpresa): ?string
    {
        $ent  = $this->repository->findById($idEntrega, $idEmpresa);
        $path = $ent['firma_path'] ?? '';
        if (is_string($path) && strpos($path, 'storage/entregas/') === 0 && strpos($path, '..') === false) {
            return $path;
        }
        return null;
    }
}
