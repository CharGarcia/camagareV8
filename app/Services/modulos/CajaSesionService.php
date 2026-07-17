<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CajaSesionRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Turnos de caja del Punto de Venta: abrir (con fondo inicial) y cerrar
 * (con arqueo). Es el prerrequisito común a las tres plantillas de pantalla
 * del POS — ninguna vende sin una sesión de caja abierta para su punto de
 * emisión.
 */
class CajaSesionService
{
    private CajaSesionRepository $repository;
    private CajaSesionRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        CajaSesionRepository $repository,
        CajaSesionRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getSesionAbierta(int $idEmpresa, int $idPuntoEmision): ?array
    {
        return $this->repository->getAbiertaPorPuntoEmision($idEmpresa, $idPuntoEmision);
    }

    public function abrir(array $data): array
    {
        $this->rules->validarApertura($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idPuntoEmision = (int) $data['id_punto_emision'];

        if ($this->repository->getAbiertaPorPuntoEmision($idEmpresa, $idPuntoEmision)) {
            throw new Exception('Este punto de emisión ya tiene una caja abierta. Ciérrala antes de abrir una nueva.');
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_punto_emision' => $idPuntoEmision,
                'id_usuario' => (int) $data['id_usuario'],
                'fondo_inicial' => (float) $data['fondo_inicial'],
                'created_by' => (int) $data['id_usuario'],
            ];

            $id = $this->repository->create($insertData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'crear',
                'caja_sesiones',
                $id,
                null,
                $insertData
            );

            $this->repository->commit();

            return $this->repository->findById($id, $idEmpresa) ?? $insertData;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function cerrar(int $id, int $idEmpresa, array $data): array
    {
        $this->rules->validarCierre($data);

        $sesion = $this->repository->findById($id, $idEmpresa);
        if (!$sesion) {
            throw new Exception('La sesión de caja no existe.');
        }
        if ($sesion['estado'] !== 'abierta') {
            throw new Exception('Esta sesión de caja ya está cerrada.');
        }

        // Hoy no hay ventas del POS vinculadas a la sesión todavía, así que
        // lo esperado es el fondo inicial. Cuando exista el flujo de venta,
        // aquí se suma el efectivo cobrado durante el turno.
        $montoEsperado = (float) $sesion['fondo_inicial'];
        $montoContado = (float) $data['monto_contado'];
        $diferencia = round($montoContado - $montoEsperado, 2);

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'monto_esperado' => $montoEsperado,
                'monto_contado' => $montoContado,
                'diferencia' => $diferencia,
                'observaciones_cierre' => trim($data['observaciones_cierre'] ?? '') ?: null,
                'updated_by' => (int) $data['id_usuario'],
            ];

            $this->repository->cerrar($id, $idEmpresa, $updateData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'caja_sesiones',
                $id,
                $sesion,
                $updateData
            );

            $this->repository->commit();

            return $this->repository->findById($id, $idEmpresa) ?? $updateData;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
