<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\ApiUsuarioResponsableTrasladoRepository;
use App\repositories\modulos\ConsignacionVentaRepository;
use App\repositories\modulos\EntregasConsignacionesRepository;
use App\Rules\modulos\ConsignacionVentaRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Entregas de Consignaciones en Ventas: consignaciones pendientes de entregar (por
 * defecto) y entregadas. Su única escritura es marcarEntregada(), que delega en
 * ConsignacionVentaService::cambiarEstado(). Reutiliza la misma tabla de
 * evidencia (consignaciones_ventas_entregas) que ya alimentan tanto la app móvil
 * (canal='movil') como el marcado manual "Entregada" desde el sistema (canal='web')
 * en ConsignacionVentaService::cambiarEstado().
 */
class EntregasConsignacionesService
{
    private EntregasConsignacionesRepository $repository;

    public function __construct(EntregasConsignacionesRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Ids de responsables_traslado a los que restringir la vista. El alcance lo
     * define el vínculo usuario <-> responsable de traslado que se administra en
     * config/usuarios-sistema (tabla usuarios_responsables_traslado), no el flag
     * "acceso total" del permiso:
     *   - Nivel 3 (superadministrador): ve todas las entregas de la empresa.
     *   - Usuario vinculado a uno o más responsables: solo las entregas de esos
     *     responsables (aunque tenga "acceso total" en el módulo).
     *   - Usuario sin ningún vínculo: ve todas las entregas de la empresa.
     * @return int[]|null null = ve todas.
     */
    public function resolverFiltroResponsables(int $idUsuario, int $idEmpresa, int $nivel): ?array
    {
        if ($nivel >= 3) {
            return null;
        }
        $repo = new ApiUsuarioResponsableTrasladoRepository();
        $ids  = $repo->getIdsResponsablesDeUsuario($idUsuario, $idEmpresa);
        return empty($ids) ? null : $ids;
    }

    /**
     * Listado de consignaciones según su estado de entrega (pendientes por defecto).
     * $orden: salida de OrdenListado::leer(). Devuelve total, rows y el estado resuelto.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?array $idsResponsables): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idsResponsables);
    }

    /** KPIs de la tarjeta superior: pendientes, entregadas (total y por canal), incompletas y tiempo promedio. */
    public function getResumen(int $idEmpresa, string $buscar, ?array $idsResponsables): array
    {
        return $this->repository->getResumen($idEmpresa, $buscar, $idsResponsables);
    }

    /**
     * ¿Esta entrega entra en lo que el usuario puede ver? Mismo criterio que el
     * listado: sin restricción ($idsResponsables = null: nivel 3 o usuario sin
     * vínculo) ve todas; si no, solo las de las consignaciones de sus
     * responsables de traslado.
     */
    public function puedeVerEntrega(int $idEntrega, int $idEmpresa, ?array $idsResponsables): bool
    {
        if ($idsResponsables === null) {
            return true;
        }
        if (empty($idsResponsables)) {
            return false;
        }
        $idResponsable = $this->repository->getResponsableDeEntrega($idEntrega, $idEmpresa);
        if ($idResponsable === null) {
            return false;
        }
        return in_array($idResponsable, array_map('intval', $idsResponsables), true);
    }

    /**
     * Marca una consignación PENDIENTE como entregada desde el módulo web. La única
     * acción de escritura del módulo: valida el alcance por responsable de traslado
     * (mismo criterio que el listado) y que la consignación siga en 'Emitida', y delega
     * en ConsignacionVentaService::cambiarEstado(), que es quien crea la evidencia
     * (canal 'web', GPS del navegador si lo hay, usuario, hora, observación) dentro de
     * su transacción y deja el rastro en log_sistema.
     *
     * @param array $datosEntrega latitud, longitud, precision_m, observaciones (opcionales).
     */
    public function marcarEntregada(int $idConsignacion, int $idEmpresa, int $idUsuario, ?array $idsResponsables, array $datosEntrega): void
    {
        $cvRepo = new ConsignacionVentaRepository();

        // Fuera del alcance del usuario: misma respuesta que una consignación inexistente.
        if (!$cvRepo->perteneceAResponsables($idConsignacion, $idEmpresa, $idsResponsables)) {
            throw new Exception('Consignación no encontrada.');
        }
        $cab = $cvRepo->find($idConsignacion, $idEmpresa);
        if (!$cab) {
            throw new Exception('Consignación no encontrada.');
        }
        if (($cab['estado'] ?? '') !== 'Emitida') {
            throw new Exception('Solo se puede marcar la entrega de una consignación pendiente (estado Emitida). Estado actual: ' . ($cab['estado'] ?? '—') . '.');
        }

        $cvService = new ConsignacionVentaService($cvRepo, new ConsignacionVentaRules(), new LogSistemaService());
        $cvService->cambiarEstado($idConsignacion, $idEmpresa, $idUsuario, 'Entregada', $datosEntrega);
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
