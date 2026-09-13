<?php
/**
 * Vínculo usuario (login) <-> responsable de traslado. Determina, para un usuario
 * de la app móvil sin "acceso total" en modulos/entregas-consignaciones, qué
 * consignaciones puede ver/entregar (las de sus responsables vinculados).
 */

declare(strict_types=1);

namespace App\Services;

use App\models\EmpresaAsignada;
use App\repositories\ApiUsuarioResponsableTrasladoRepository;
use App\repositories\modulos\ResponsableTrasladoRepository;
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

    /** Vista INVERSA: usuarios vinculados a un responsable (módulo Responsables de Traslado). */
    public function usuariosDeResponsable(int $idResponsable, int $idEmpresa): array
    {
        return $this->repo->listarUsuariosDeResponsable($idResponsable, $idEmpresa);
    }

    /** Usuarios con esta empresa asignada que aún no están vinculados a este responsable. */
    public function usuariosDisponiblesParaResponsable(int $idResponsable, int $idEmpresa): array
    {
        return $this->repo->usuariosDisponiblesParaResponsable($idResponsable, $idEmpresa);
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

        // El usuario debe existir, estar activo y tener la empresa asignada. Desde la
        // ficha del usuario esto se cumplía por la UI (la empresa se elige de SUS
        // empresas); desde el módulo de Responsables se parte de la empresa activa y
        // del responsable, así que la comprobación tiene que hacerse aquí. Un vínculo
        // a una empresa que el usuario no tiene no le daría acceso, pero quedaría
        // colgado y confundiría al revisar quién entrega qué.
        $empresaAsignada = new EmpresaAsignada();
        $usuario = $empresaAsignada->getUsuarioPorId($idUsuario);
        if (!$usuario) {
            throw new RuntimeException('El usuario no existe o está inactivo.');
        }
        // El nivel 3 no necesita asignación para entrar a una empresa.
        if ((int) ($usuario['nivel'] ?? 1) < 3 && !$empresaAsignada->estaEmpresaAsignada($idEmpresa, $idUsuario)) {
            throw new RuntimeException('El usuario no tiene asignada esta empresa. Asígnesela primero en Usuarios del sistema.');
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
