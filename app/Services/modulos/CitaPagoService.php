<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CitaPagoRepository;
use App\Rules\modulos\CitaPagoRules;
use App\Services\LogSistemaService;

class CitaPagoService
{
    public function __construct(
        private CitaPagoRepository $repo,
        private CitaPagoRules      $rules,
        private LogSistemaService  $log
    ) {}

    // ─── LECTURA ──────────────────────────────────────────────────────────────

    public function getListado(
        int $idEmpresa, string $buscar, int $page, int $perPage,
        string $ordenCol, string $ordenDir, array $filtros = []
    ): array {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $filtros);
    }

    public function getResumen(int $idEmpresa): array
    {
        return $this->repo->getResumen($idEmpresa);
    }

    public function getById(int $id, int $idEmpresa): array
    {
        $row = $this->repo->getById($id, $idEmpresa);
        if ($row === null) {
            throw new \Exception('Pago no encontrado.');
        }
        return $row;
    }

    public function buscarCitas(string $buscar, int $idEmpresa): array
    {
        return $this->repo->buscarCitas($buscar, $idEmpresa);
    }

    // ─── ESCRITURA ────────────────────────────────────────────────────────────

    public function guardar(array $data): int
    {
        $this->rules->validarPago($data);

        $id        = (int) ($data['id'] ?? 0);
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repo->beginTransaction();
        try {
            if ($id > 0) {
                $anterior = $this->repo->getById($id, $idEmpresa);
                if ($anterior === null) throw new \Exception('Pago no encontrado.');
                $this->repo->update($id, $data);
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'citas_pagos', $id, $anterior, $data);
            } else {
                $id = $this->repo->create($data);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'citas_pagos', $id, null, $data);
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
            throw new \Exception('Pago no encontrado.');
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->delete($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'citas_pagos', $id, $anterior, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }
}
