<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\AsientoProgramadoRepository;
use App\Rules\modulos\AsientoProgramadoRules;
use App\Services\LogSistemaService;
use Exception;
use PDO;

class AsientoProgramadoService
{
    private PDO $db;
    private AsientoProgramadoRepository $repo;
    private AsientoProgramadoRules $rules;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->repo = new AsientoProgramadoRepository();
        $this->rules = new AsientoProgramadoRules();
        $this->logService = new LogSistemaService();
    }

    /**
     * Registra un asiento programado dentro de una transacción.
     */
    public function registrar(array $data, int $idEmpresa, int $idUsuario): int
    {
        $this->rules->validar($data);

        $idAsientoTipo = (int) $data['id_asiento_tipo'];
        $idReferencia = !empty($data['id_referencia']) ? (int) $data['id_referencia'] : null;
        $tipoReferencia = !empty($data['tipo_referencia']) ? trim($data['tipo_referencia']) : null;
        $referenciaTexto = !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null;

        // Resolving legacy or general 'asientos tipo' to actual concept name dynamically
        if ($tipoReferencia === 'asientos tipo' && $idAsientoTipo > 0) {
            $tipoNombre = $this->repo->getTipoAsientoNombre($idAsientoTipo);
            if ($tipoNombre) {
                $tipoReferencia = $tipoNombre;
                $data['tipo_referencia'] = $tipoNombre;
            }
        }

        // Validar si ya existe una regla idéntica para evitar redundancia
        if ($this->repo->existeRegla($idEmpresa, $idAsientoTipo, $idReferencia, $tipoReferencia, null, $referenciaTexto)) {
            throw new Exception('Ya existe un asiento programado con la misma configuración para el tipo de asiento y entidad seleccionados.');
        }

        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = $idUsuario;
        $data['created_by'] = $idUsuario;

        $this->db->beginTransaction();
        try {
            $id = $this->repo->create($data);

            // Obtener datos insertados para la auditoría
            $nuevo = $this->repo->findByIdAndEmpresa($id, $idEmpresa);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR ASIENTO PROGRAMADO',
                'asientos_programados',
                $id,
                null,
                $nuevo
            );

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza un asiento programado dentro de una transacción.
     */
    public function actualizar(int $id, array $data, int $idEmpresa, int $idUsuario): bool
    {
        $this->rules->validar($data);

        $idAsientoTipo = (int) $data['id_asiento_tipo'];
        $idReferencia = !empty($data['id_referencia']) ? (int) $data['id_referencia'] : null;
        $tipoReferencia = !empty($data['tipo_referencia']) ? trim($data['tipo_referencia']) : null;
        $referenciaTexto = !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null;

        // Resolving legacy or general 'asientos tipo' to actual concept name dynamically
        if ($tipoReferencia === 'asientos tipo' && $idAsientoTipo > 0) {
            $tipoNombre = $this->repo->getTipoAsientoNombre($idAsientoTipo);
            if ($tipoNombre) {
                $tipoReferencia = $tipoNombre;
                $data['tipo_referencia'] = $tipoNombre;
            }
        }

        // Validar si ya existe otra regla idéntica que no sea la actual
        if ($this->repo->existeRegla($idEmpresa, $idAsientoTipo, $idReferencia, $tipoReferencia, $id, $referenciaTexto)) {
            throw new Exception('Ya existe otro asiento programado configurado con el mismo tipo de asiento y entidad seleccionados.');
        }

        $data['updated_by'] = $idUsuario;

        $this->db->beginTransaction();
        try {
            $anterior = $this->repo->findByIdAndEmpresa($id, $idEmpresa);
            if (!$anterior) {
                throw new Exception('El asiento programado solicitado no existe o no pertenece a su empresa.');
            }

            $ok = $this->repo->update($id, $idEmpresa, $data);

            $nuevo = $this->repo->findByIdAndEmpresa($id, $idEmpresa);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR ASIENTO PROGRAMADO',
                'asientos_programados',
                $id,
                $anterior,
                $nuevo
            );

            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina lógicamente un asiento programado dentro de una transacción.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $this->db->beginTransaction();
        try {
            $anterior = $this->repo->findByIdAndEmpresa($id, $idEmpresa);
            if (!$anterior) {
                throw new Exception('El asiento programado solicitado no existe o no pertenece a su empresa.');
            }

            $ok = $this->repo->delete($id, $idEmpresa, $idUsuario);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR ASIENTO PROGRAMADO',
                'asientos_programados',
                $id,
                $anterior,
                null
            );

            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene la preferencia de método de contabilización de la empresa para un tipo de asiento.
     */
    public function getMetodoPreferencia(int $idEmpresa, string $tipoAsiento): string
    {
        return $this->repo->getMetodoPreferencia($idEmpresa, $tipoAsiento);
    }

    /**
     * Guarda la preferencia de método de contabilización de la empresa.
     */
    public function guardarMetodoPreferencia(int $idEmpresa, string $tipoAsiento, string $metodo, int $idUsuario): void
    {
        $this->db->beginTransaction();
        try {
            $this->repo->guardarMetodoPreferencia($idEmpresa, $tipoAsiento, $metodo, $idUsuario);

            // Registrar log de auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CAMBIAR PREFERENCIA CONTABILIZACION',
                'asientos_preferencia_empresa',
                0,
                null,
                ['tipo_asiento' => $tipoAsiento, 'metodo' => $metodo]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
