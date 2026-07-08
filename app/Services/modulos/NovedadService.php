<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\NovedadRepository;
use App\Rules\modulos\NovedadRules;
use App\Services\LogSistemaService;
use App\models\CatalogoNovedades;
use Exception;

class NovedadService
{
    private NovedadRepository $repository;
    private NovedadRules $rules;
    private LogSistemaService $logService;

    public function __construct(NovedadRepository $repository, NovedadRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getDetalle($id, $idEmpresa);
    }

    public function crear(array $data): int
    {
        $data = $this->normalizar($data);
        $this->rules->validate($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'novedades', $id, null, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, $idUsuario);
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $data = $this->normalizar($data);
        $this->rules->validate($data);

        $old = $this->repository->getDetalle($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Novedad no encontrada.');
        }

        $idUsuario = (int) $data['id_usuario'];
        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'novedades', $id, $old, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        // Regenerar el rol del período nuevo y, si cambió, también el del período anterior.
        $this->sincronizarRol($idEmpresa, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, $idUsuario);
        if (($old['aplica_en'] ?? '') !== ($data['aplica_en'] ?? '')
            || (int) ($old['periodo_mes'] ?? 0) !== (int) ($data['periodo_mes'] ?? 0)
            || (int) ($old['periodo_anio'] ?? 0) !== (int) ($data['periodo_anio'] ?? 0)) {
            $this->sincronizarRol($idEmpresa, $old['aplica_en'] ?? 'rol', $old['periodo_anio'] ?? 0, $old['periodo_mes'] ?? 0, $idUsuario);
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->getDetalle($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Novedad no encontrada.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'novedades', $id, $old, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $old['aplica_en'] ?? 'rol', $old['periodo_anio'] ?? 0, $old['periodo_mes'] ?? 0, $idUsuario);
    }

    /** Auto-regenera el rol 'generado' afectado por el cambio de novedad (silencioso si falla). */
    private function sincronizarRol(int $idEmpresa, ?string $aplicaEn, $anio, $mes, int $idUsuario): void
    {
        if ((int) $mes < 1 || (int) $anio < 2000) return;
        try {
            $rolSvc = new RolPagoService(
                new \App\repositories\modulos\RolPagoRepository(),
                new \App\Rules\modulos\RolPagoRules(),
                $this->logService
            );
            $rolSvc->regenerarAfectados($idEmpresa, (string) ($aplicaEn ?: 'rol'), (int) $anio, (int) $mes, $idUsuario);
        } catch (\Throwable $e) {
            // Silencioso: la novedad ya se guardó.
        }
    }

    /**
     * Denormaliza nombres desde el catálogo y limpia campos según el tipo.
     */
    private function normalizar(array $data): array
    {
        $tipo = trim((string) ($data['tipo_codigo'] ?? ''));
        $data['tipo_nombre'] = CatalogoNovedades::nombreTipo($tipo) ?? '';

        if (CatalogoNovedades::esAvisoSalida($tipo)) {
            $motivo = trim((string) ($data['motivo_codigo'] ?? ''));
            $data['motivo_codigo'] = $motivo !== '' ? $motivo : null;
            $data['motivo_nombre'] = $motivo !== '' ? CatalogoNovedades::nombreMotivo($motivo) : null;
            $data['valor'] = 0; // Aviso de salida no lleva valor
        } else {
            $data['motivo_codigo'] = null;
            $data['motivo_nombre'] = null;
        }

        return $data;
    }
}
