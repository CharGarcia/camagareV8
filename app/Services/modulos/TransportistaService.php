<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\TransportistaRepository;
use App\Rules\modulos\TransportistaRules;
use App\Services\LogSistemaService;
use App\core\Database;

class TransportistaService
{
    private TransportistaRepository $repo;
    private TransportistaRules      $rules;
    private LogSistemaService       $log;

    public function __construct(
        TransportistaRepository $repo,
        TransportistaRules      $rules,
        LogSistemaService       $log
    ) {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function crear(array $data): int
    {
        $this->rules->validarCrear($data);

        if ($this->repo->existeIdentificacionUnico((int)$data['id_empresa'], trim($data['identificacion']))) {
            throw new \InvalidArgumentException('Ya existe un transportista con esa identificación en esta empresa.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $data['nombre']          = mb_strtoupper(trim($data['nombre']));
            $data['identificacion']  = trim($data['identificacion']);
            $data['placa']           = !empty($data['placa']) ? mb_strtoupper(trim($data['placa'])) : null;

            $id = $this->repo->insertar($data);

            $this->log->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'CREAR',
                'transportistas',
                null,
                ['id' => $id, 'nombre' => $data['nombre'], 'identificacion' => $data['identificacion']]
            );

            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): void
    {
        $this->rules->validarActualizar($data);

        $actual = $this->repo->getPorId($id, (int)$data['id_empresa']);
        if (!$actual) {
            throw new \RuntimeException('Transportista no encontrado.');
        }

        if ($this->repo->existeIdentificacionUnico((int)$data['id_empresa'], trim($data['identificacion']), $id)) {
            throw new \InvalidArgumentException('Ya existe otro transportista con esa identificación en esta empresa.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $data['nombre']         = mb_strtoupper(trim($data['nombre']));
            $data['identificacion'] = trim($data['identificacion']);
            $data['placa']          = !empty($data['placa']) ? mb_strtoupper(trim($data['placa'])) : null;

            $this->repo->actualizar($id, $data);

            $this->log->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'ACTUALIZAR',
                'transportistas',
                $actual,
                $data
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getPorId($id, $idEmpresa);
        if (!$actual) {
            throw new \RuntimeException('Transportista no encontrado.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);

            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'transportistas', $actual, null);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
