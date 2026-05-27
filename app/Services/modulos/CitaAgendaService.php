<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CitaAgendaRepository;
use App\Rules\modulos\CitaAgendaRules;
use App\Services\LogSistemaService;

class CitaAgendaService
{
    public function __construct(
        private CitaAgendaRepository $repo,
        private CitaAgendaRules      $rules,
        private LogSistemaService    $log
    ) {}

    // ─── LECTURA ──────────────────────────────────────────────────────────────

    public function getEventos(int $idEmpresa, string $inicio, string $fin, array $filtros = []): array
    {
        return $this->repo->getEventos($idEmpresa, $inicio, $fin, $filtros);
    }

    public function getListado(
        int $idEmpresa, string $buscar, int $page, int $perPage,
        string $ordenCol, string $ordenDir, array $filtros = []
    ): array {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $filtros);
    }

    public function getById(int $id, int $idEmpresa): array
    {
        $row = $this->repo->getById($id, $idEmpresa);
        if ($row === null) {
            throw new \Exception('Cita no encontrada.');
        }
        return $row;
    }

    public function getCatalogos(int $idEmpresa): array
    {
        return $this->repo->getCatalogos($idEmpresa);
    }

    public function buscarClientes(string $buscar, int $idEmpresa): array
    {
        return $this->repo->buscarClientes($buscar, $idEmpresa);
    }

    // ─── ESCRITURA ────────────────────────────────────────────────────────────

    public function guardar(array $data): int
    {
        $this->rules->validarCita($data);

        $id        = (int) ($data['id'] ?? 0);
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repo->beginTransaction();
        try {
            if ($id > 0) {
                $anterior = $this->repo->getById($id, $idEmpresa);
                $this->repo->update($id, $data);
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'citas', $id, $anterior, $data);
            } else {
                $id = $this->repo->create($data);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'citas', $id, null, $data);
            }
            $this->repo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $anterior = $this->repo->getById($id, $idEmpresa);
        if ($anterior === null) {
            throw new \Exception('Cita no encontrada.');
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->delete($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'citas', $id, $anterior, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id, string $estado, int $idEmpresa, int $idUsuario): void
    {
        $estadosValidos = ['pendiente', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio'];
        if (!in_array($estado, $estadosValidos, true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }

        $this->repo->beginTransaction();
        try {
            $anterior = $this->repo->getById($id, $idEmpresa);
            if ($anterior === null) throw new \Exception('Cita no encontrada.');
            $this->repo->cambiarEstado($id, $estado, $idEmpresa, $idUsuario);
            $this->log->registrar(
                $idUsuario, $idEmpresa, 'cambiar_estado', 'citas', $id,
                ['estado' => $anterior['estado']],
                ['estado' => $estado]
            );
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }
}
