<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\PlanCuentaDimensionRepository;
use App\repositories\modulos\PlanCuentaRepository;
use App\Rules\modulos\PlanCuentaDimensionRules;
use App\Services\LogSistemaService;

/**
 * Centro de costo / proyecto sugerido por periodo de una cuenta contable.
 * Ver PlanCuentaDimensionRepository: es una sugerencia para las líneas de los asientos,
 * nunca reescribe lo ya registrado.
 */
class PlanCuentaDimensionService
{
    private const TABLA = PlanCuentaDimensionRepository::TABLA;

    public function __construct(
        private PlanCuentaDimensionRepository $repository,
        private PlanCuentaRepository $cuentas,
        private PlanCuentaDimensionRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new PlanCuentaDimensionRepository(), new PlanCuentaRepository(), new PlanCuentaDimensionRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->repository->disponible();
    }

    /** $tipo = 'centro' | 'proyecto'. */
    public function listar(int $idCuenta, int $idEmpresa, string $tipo): array
    {
        return $this->repository->listarPorCuenta($idCuenta, $idEmpresa, $tipo);
    }

    /** Centro/proyecto a proponer en una línea de asiento con esa cuenta y esa fecha. */
    public function sugerencia(int $idCuenta, int $idEmpresa, string $fecha): ?array
    {
        if ($idCuenta <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return null;
        return $this->repository->getVigente($idCuenta, $idEmpresa, $fecha);
    }

    /** Crea ($id = 0) o actualiza un periodo. Devuelve su id. */
    public function guardar(int $id, array $datos, int $idEmpresa, int $idUsuario): int
    {
        if (!$this->repository->disponible()) {
            throw new \RuntimeException('Falta aplicar la migración 20261028_plan_cuentas_dimension_vigencia.sql.');
        }

        $d = $this->rules->normalizar($datos);
        $this->rules->validarCuenta($this->cuentas->findById($d['id_cuenta'], $idEmpresa));
        $tipo = $d['tipo'];
        unset($d['tipo']);
        if (!$this->repository->getDimension($tipo, (int) ($d['id_centro_costo'] ?? $d['id_proyecto']), $idEmpresa)) {
            throw new \InvalidArgumentException($tipo === 'centro' ? 'Centro de costo no encontrado.' : 'Proyecto no encontrado.');
        }

        $this->repository->beginTransaction();
        try {
            // Leer cruce → escribir, bajo el candado de la cuenta (§8).
            $this->repository->lockCuenta($d['id_cuenta'], $idEmpresa);

            $antes = null;
            if ($id > 0) {
                $antes = $this->repository->findById($id, $idEmpresa);
                $col = PlanCuentaDimensionRepository::TIPOS[$tipo];
                // Un periodo no cambia de tipo: el de centro sigue siendo de centro.
                if (!$antes || (int) $antes['id_cuenta'] !== $d['id_cuenta'] || $antes[$col] === null) {
                    throw new \InvalidArgumentException('Periodo no encontrado.');
                }
            }

            $this->rules->validarSinCruce(
                $this->repository->buscarCruce($d['id_cuenta'], $idEmpresa, $tipo, $d['vigente_desde'], $d['vigente_hasta'], $id > 0 ? $id : null),
                $tipo
            );

            if ($id > 0) {
                $this->repository->actualizar($id, $d, $idEmpresa, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', self::TABLA, $id, $antes, $d);
            } else {
                $id = $this->repository->crear($d, $idEmpresa, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', self::TABLA, $id, null, $d);
            }

            $this->repository->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->repository->beginTransaction();
        try {
            $antes = $this->repository->findById($id, $idEmpresa);
            if (!$antes) {
                throw new \InvalidArgumentException('Periodo no encontrado.');
            }
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', self::TABLA, $id, $antes, null);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
