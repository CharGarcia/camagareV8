<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\Booleano;
use App\Helpers\CatalogoMedidas;
use App\repositories\modulos\UnidadesMedidaRepository;
use App\Rules\modulos\UnidadesMedidaRules;
use App\Services\LogSistemaService;
use Exception;

class UnidadesMedidaService
{
    private UnidadesMedidaRepository $repository;
    private UnidadesMedidaRules      $rules;
    private LogSistemaService        $logService;

    public function __construct(
        UnidadesMedidaRepository $repository,
        UnidadesMedidaRules      $rules,
        LogSistemaService        $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ─── TIPOS ──────────────────────────────────────────────────────────────

    public function getTiposListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repository->getTiposListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function crearTipo(array $data): int
    {
        $this->rules->validarTipo($data);

        $idEmpresa = (int) $data['id_empresa'];
        $nombre    = mb_strtoupper(trim($data['nombre']), 'UTF-8');

        if ($this->repository->existeNombreTipo($idEmpresa, $nombre)) {
            throw new Exception('Ya existe un tipo de medida con ese nombre en esta empresa.');
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'  => $idEmpresa,
                'id_usuario'  => (int) $data['id_usuario'],
                'codigo'      => mb_strtoupper(trim($data['codigo'] ?? ''), 'UTF-8'),
                'nombre'      => $nombre,
                'status'      => isset($data['status']) ? (bool) $data['status'] : true,
                'created_by'  => (int) $data['id_usuario'],
            ];

            $id = $this->repository->createTipo($insertData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'crear',
                'tipo_medida',
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

    public function actualizarTipo(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarTipo($data);

        $nombre = mb_strtoupper(trim($data['nombre']), 'UTF-8');

        if ($this->repository->existeNombreTipo($idEmpresa, $nombre, $id)) {
            throw new Exception('Ya existe otro tipo de medida con ese nombre en esta empresa.');
        }

        $antes = $this->repository->getTipoById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Tipo de medida no encontrado o ya fue eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'codigo'     => mb_strtoupper(trim($data['codigo'] ?? ''), 'UTF-8'),
                'nombre'     => $nombre,
                'status'     => isset($data['status']) ? (bool) $data['status'] : true,
                'updated_by' => (int) $data['id_usuario'],
            ];

            $this->repository->updateTipo($id, $idEmpresa, $updateData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'tipo_medida',
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

    public function eliminarTipo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->getTipoById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Tipo de medida no encontrado o ya fue eliminado.');
        }

        if ($this->repository->tieneUnidades($id, $idEmpresa)) {
            throw new Exception('No se puede eliminar el tipo de medida porque tiene unidades asociadas.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteTipo($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'tipo_medida',
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

    // ─── UNIDADES ───────────────────────────────────────────────────────────

    public function getUnidadesListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $filtroTipo = null,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repository->getUnidadesListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $filtroTipo, $idUsuarioFiltro);
    }

    public function crearUnidad(array $data): int
    {
        $this->rules->validarUnidad($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idTipo    = (int) $data['id_tipo'];
        $nombre    = mb_strtoupper(trim($data['nombre']), 'UTF-8');
        $esBase    = !empty($data['es_base']);

        if ($this->repository->existeNombreUnidad($idEmpresa, $nombre, $idTipo)) {
            throw new Exception('Ya existe una unidad con ese nombre para este tipo de medida.');
        }

        if ($esBase && $this->repository->tieneBaseEnTipo($idTipo, $idEmpresa)) {
            throw new Exception('Ya existe una unidad base para este tipo de medida. Solo puede haber una.');
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa'  => $idEmpresa,
                'id_tipo'     => $idTipo,
                'codigo'      => mb_strtoupper(trim($data['codigo'] ?? ''), 'UTF-8'),
                'nombre'      => $nombre,
                'abreviatura' => trim($data['abreviatura']),
                'factor_base' => $esBase ? 1.0 : (float) ($data['factor_base'] ?? 1),
                'es_base'     => $esBase,
                'status'      => isset($data['status']) ? (bool) $data['status'] : true,
                'created_by'  => (int) $data['id_usuario'],
            ];

            $id = $this->repository->createUnidad($insertData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'crear',
                'unidades_medida',
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

    public function actualizarUnidad(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarUnidad($data);

        $idTipo = (int) $data['id_tipo'];
        $nombre = mb_strtoupper(trim($data['nombre']), 'UTF-8');
        $esBase = !empty($data['es_base']);

        if ($this->repository->existeNombreUnidad($idEmpresa, $nombre, $idTipo, $id)) {
            throw new Exception('Ya existe otra unidad con ese nombre para este tipo de medida.');
        }

        // Si se está marcando como base, verificar que no haya otra base en el mismo tipo
        if ($esBase && $this->repository->tieneBaseEnTipo($idTipo, $idEmpresa, $id)) {
            throw new Exception('Ya existe una unidad base para este tipo de medida. Solo puede haber una.');
        }

        $antes = $this->repository->getUnidadById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Unidad de medida no encontrada o ya fue eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'id_tipo'     => $idTipo,
                'codigo'      => mb_strtoupper(trim($data['codigo'] ?? ''), 'UTF-8'),
                'nombre'      => $nombre,
                'abreviatura' => trim($data['abreviatura']),
                'factor_base' => $esBase ? 1.0 : (float) ($data['factor_base'] ?? 1),
                'es_base'     => $esBase,
                'status'      => isset($data['status']) ? (bool) $data['status'] : true,
                'updated_by'  => (int) $data['id_usuario'],
            ];

            $this->repository->updateUnidad($id, $idEmpresa, $updateData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'unidades_medida',
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

    public function eliminarUnidad(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->getUnidadById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Unidad de medida no encontrada o ya fue eliminada.');
        }

        if ($this->repository->estaEnUso($id, $idEmpresa)) {
            throw new Exception('No se puede eliminar la unidad de medida porque está asignada a uno o más productos/componentes.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteUnidad($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'unidades_medida',
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

    // ─── SIEMBRA DE CATÁLOGO DEFAULT ────────────────────────────────────────

    /**
     * Siembra el catálogo por defecto de tipos y unidades de medida
     * (App\Helpers\CatalogoMedidas) en la empresa actual. Completa lo que
     * falte sin tocar ni duplicar lo que la empresa ya tenga:
     *   - Reutiliza un tipo existente si coincide por código, nombre o sinónimo
     *     (p. ej. una empresa con "MASA" no recibe otro tipo "PESO").
     *   - Omite la unidad si su código ya existe en la empresa. El código debe ser
     *     único a nivel de empresa porque al importar productos la unidad se
     *     resuelve solo por código.
     *   - Omite la unidad si ya hay otra con el mismo nombre en ese tipo.
     *   - Solo marca es_base cuando el tipo aún no tiene unidad base
     *     (hay un índice único parcial por id_tipo + id_empresa).
     * Usado tanto por el botón manual del módulo como por EmpresaInicializadorService
     * al crear/actualizar una empresa.
     *
     * @return array{tipos_creados:int, unidades_creadas:int}
     */
    public function sembrarCatalogoDefault(int $idEmpresa, int $idUsuario): array
    {
        $tiposPorClave = [];
        foreach ($this->repository->getTiposParaSiembra($idEmpresa) as $t) {
            foreach ([$t['codigo'], $t['nombre']] as $clave) {
                $clave = CatalogoMedidas::normalizar((string) $clave);
                if ($clave !== '' && !isset($tiposPorClave[$clave])) {
                    $tiposPorClave[$clave] = (int) $t['id'];
                }
            }
        }

        $codigosUsados  = [];
        $nombresPorTipo = [];
        $tiposConBase   = [];
        foreach ($this->repository->getUnidadesParaSiembra($idEmpresa) as $u) {
            $idTipoExistente = (int) $u['id_tipo'];

            $cod = CatalogoMedidas::normalizar((string) $u['codigo']);
            if ($cod !== '') {
                $codigosUsados[$cod] = true;
            }

            $nom = CatalogoMedidas::normalizar((string) $u['nombre']);
            if ($nom !== '') {
                $nombresPorTipo[$idTipoExistente . '|' . $nom] = true;
            }

            if (Booleano::es($u['es_base'])) {
                $tiposConBase[$idTipoExistente] = true;
            }
        }

        $tiposCreados    = 0;
        $unidadesCreadas = 0;

        $this->repository->beginTransaction();
        try {
            foreach (CatalogoMedidas::getCatalogo() as $tipo) {
                $claves = array_merge([$tipo['codigo'], $tipo['nombre']], $tipo['sinonimos']);

                $idTipo = null;
                foreach ($claves as $clave) {
                    $clave = CatalogoMedidas::normalizar((string) $clave);
                    if ($clave !== '' && isset($tiposPorClave[$clave])) {
                        $idTipo = $tiposPorClave[$clave];
                        break;
                    }
                }

                if ($idTipo === null) {
                    $idTipo = $this->repository->createTipo([
                        'id_empresa' => $idEmpresa,
                        'id_usuario' => $idUsuario,
                        'codigo'     => $tipo['codigo'],
                        'nombre'     => $tipo['nombre'],
                        'status'     => true,
                        'created_by' => $idUsuario,
                    ]);
                    $tiposCreados++;

                    foreach ($claves as $clave) {
                        $clave = CatalogoMedidas::normalizar((string) $clave);
                        if ($clave !== '') {
                            $tiposPorClave[$clave] = $idTipo;
                        }
                    }
                }

                foreach ($tipo['unidades'] as $unidad) {
                    $codNorm = CatalogoMedidas::normalizar($unidad['codigo']);
                    $nomNorm = CatalogoMedidas::normalizar($unidad['nombre']);

                    if (isset($codigosUsados[$codNorm]) || isset($nombresPorTipo[$idTipo . '|' . $nomNorm])) {
                        continue;
                    }

                    $esBase = $unidad['es_base'] && !isset($tiposConBase[$idTipo]);

                    $this->repository->createUnidad([
                        'id_empresa'  => $idEmpresa,
                        'id_tipo'     => $idTipo,
                        'codigo'      => $unidad['codigo'],
                        'nombre'      => $unidad['nombre'],
                        'abreviatura' => $unidad['abreviatura'],
                        // number_format evita la notación científica de PHP
                        // para valores como 0.000001 ("1.0E-6").
                        'factor_base' => number_format((float) $unidad['factor_base'], 6, '.', ''),
                        'es_base'     => $esBase,
                        'status'      => true,
                        'created_by'  => $idUsuario,
                    ]);
                    $unidadesCreadas++;

                    $codigosUsados[$codNorm] = true;
                    $nombresPorTipo[$idTipo . '|' . $nomNorm] = true;
                    if ($esBase) {
                        $tiposConBase[$idTipo] = true;
                    }
                }
            }

            if ($tiposCreados > 0 || $unidadesCreadas > 0) {
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'sembrar',
                    'unidades_medida',
                    null,
                    null,
                    ['tipos_creados' => $tiposCreados, 'unidades_creadas' => $unidadesCreadas]
                );
            }

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }

        return ['tipos_creados' => $tiposCreados, 'unidades_creadas' => $unidadesCreadas];
    }
}
