<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\MesaRules;
use App\Services\LogSistemaService;
use Exception;

class MesaService
{
    private MesaRepository $repository;
    private MesaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        MesaRepository $repository,
        MesaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $nombre    = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre)) {
            throw new Exception("Ya existe una mesa con el nombre '{$nombre}' en su empresa.");
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'      => $idEmpresa,
                'id_usuario'      => (int)$data['id_usuario'],
                'created_by'      => (int)$data['id_usuario'],
                'nombre'          => mb_strtoupper($nombre, 'UTF-8'),
                'estado'          => $data['estado'] ?? 'disponible',
                'ubicacion'       => trim((string) ($data['ubicacion'] ?? '')) ?: null,
                'permite_factura' => array_key_exists('permite_factura', $data) ? (bool) $data['permite_factura'] : true,
                'permite_recibo'  => (bool) ($data['permite_recibo'] ?? false),
                'eliminado'       => false
            ];

            $id = $this->repository->create($insertData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'crear',
                'mesas',
                $id,
                null,
                $insertData
            );

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        // El controller no siempre trae id_empresa dentro de $data (viene aparte
        // como argumento); MesaRules::validar() sí lo exige, así que se completa aquí.
        $data['id_empresa'] = $idEmpresa;
        $this->rules->validar($data);

        $nombre = trim($data['nombre']);

        if ($this->repository->existeNombre($idEmpresa, $nombre, $id)) {
            throw new Exception("Ya existe otra mesa con el nombre '{$nombre}'.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La mesa no existe o ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'nombre'          => mb_strtoupper($nombre, 'UTF-8'),
                'estado'          => $data['estado'] ?? 'disponible',
                'ubicacion'       => trim((string) ($data['ubicacion'] ?? '')) ?: null,
                'permite_factura' => array_key_exists('permite_factura', $data) ? (bool) $data['permite_factura'] : true,
                'permite_recibo'  => (bool) ($data['permite_recibo'] ?? false),
                'updated_by'      => (int)$data['id_usuario']
            ];

            $this->repository->update($id, $idEmpresa, $updateData);
            
            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'mesas',
                $id,
                $antes,
                $updateData
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La mesa no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'mesas',
                $id,
                $antes,
                ['eliminado' => true]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getDetalleCompleto($id, $idEmpresa);
    }

    /** Token del QR de la mesa — lo genera la primera vez que se pide (no exige un paso aparte de "activar QR"). */
    public function getOrCrearQrToken(int $id, int $idEmpresa): string
    {
        $mesa = $this->repository->getDetalleCompleto($id, $idEmpresa);
        if (!$mesa) {
            throw new Exception('La mesa no existe.');
        }
        if (!empty($mesa['qr_token'])) {
            return $mesa['qr_token'];
        }
        $token = $this->repository->regenerarQrToken($id, $idEmpresa);
        if (!$token) {
            throw new Exception('No se pudo generar el código QR de la mesa.');
        }
        return $token;
    }

    /** Invalida el QR anterior (por si se filtró o hay que reimprimirlo) y genera uno nuevo. */
    public function regenerarQrToken(int $id, int $idEmpresa, int $idUsuario): string
    {
        $mesa = $this->repository->getDetalleCompleto($id, $idEmpresa);
        if (!$mesa) {
            throw new Exception('La mesa no existe.');
        }
        $token = $this->repository->regenerarQrToken($id, $idEmpresa);
        if (!$token) {
            throw new Exception('No se pudo regenerar el código QR de la mesa.');
        }
        $this->logService->registrar($idUsuario, $idEmpresa, 'REGENERAR_QR_MESA', 'mesas', $id, null, ['qr_token' => 'regenerado']);
        return $token;
    }

    /** Reubicar una mesa en el lienzo del tablero (arrastrar y soltar). */
    public function actualizarPosicion(int $id, int $idEmpresa, float $posX, float $posY): void
    {
        $posX = max(0.0, min(100.0, $posX));
        $posY = max(0.0, min(100.0, $posY));
        $this->repository->actualizarPosicion($id, $idEmpresa, $posX, $posY);
    }
}
