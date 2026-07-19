<?php
/**
 * Vínculo usuario (login) <-> responsable de traslado. Determina, para un usuario
 * de la app móvil sin "acceso total" en modulos/entregas-consignaciones, qué
 * consignaciones puede ver/entregar (las de sus responsables vinculados).
 */

declare(strict_types=1);

namespace App\Services;

use App\repositories\ApiUsuarioResponsableTrasladoRepository;
use App\Repositories\Modulos\ResponsableTrasladoRepository;
use RuntimeException;

class UsuarioResponsableTrasladoService
{
    private ApiUsuarioResponsableTrasladoRepository $repo;
    private ResponsableTrasladoRepository $responsableRepo;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repo = new ApiUsuarioResponsableTrasladoRepository();
        $this->responsableRepo = new ResponsableTrasladoRepository();
        $this->logService = new LogSistemaService();
    }

    public function listar(int $idUsuario, int $idEmpresa): array
    {
        return $this->repo->listarPorUsuarioYEmpresa($idUsuario, $idEmpresa);
    }

    /** Responsables de traslado de la empresa que este usuario todavía no tiene vinculados. */
    public function disponibles(int $idUsuario, int $idEmpresa): array
    {
        $todos = $this->responsableRepo->listarPorEmpresa($idEmpresa);
        $vinculados = array_flip($this->repo->getIdsResponsablesDeUsuario($idUsuario, $idEmpresa));
        return array_values(array_filter($todos, fn($r) => !isset($vinculados[(int) $r['id']])));
    }

    public function vincular(int $idEmpresa, int $idUsuario, int $idResponsable, int $idActual): array
    {
        $existeResponsable = array_filter(
            $this->responsableRepo->listarPorEmpresa($idEmpresa),
            fn($r) => (int) $r['id'] === $idResponsable
        );
        if (empty($existeResponsable)) {
            throw new RuntimeException('El responsable de traslado no pertenece a esta empresa.');
        }

        $resultado = $this->repo->vincular($idEmpresa, $idUsuario, $idResponsable, $idActual);
        if ($resultado['creado']) {
            $this->logService->registrar(
                $idActual,
                $idEmpresa,
                'VINCULAR',
                'usuarios_responsables_traslado',
                $resultado['id'],
                null,
                ['id_usuario' => $idUsuario, 'id_responsable_traslado' => $idResponsable]
            );
        }
        return $resultado;
    }

    public function desvincular(int $id, int $idEmpresa, int $idActual): void
    {
        $existente = $this->repo->find($id, $idEmpresa);
        if (!$existente) {
            throw new RuntimeException('El vínculo no existe.');
        }

        $this->repo->desvincular($id, $idEmpresa, $idActual);
        $this->logService->registrar(
            $idActual,
            $idEmpresa,
            'DESVINCULAR',
            'usuarios_responsables_traslado',
            $id,
            $existente,
            null
        );
    }
}
