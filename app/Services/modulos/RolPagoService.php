<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\RolPagoRepository;
use App\repositories\modulos\VacacionRepository;
use App\Rules\modulos\RolPagoRules;
use App\Services\LogSistemaService;
use App\models\CatalogoRol;
use Exception;

class RolPagoService
{
    private RolPagoRepository $repo;
    private RolPagoRules $rules;
    private LogSistemaService $log;
    private RolCalculoService $calc;

    public function __construct(RolPagoRepository $repo, RolPagoRules $rules, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new RolCalculoService();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $cab = $this->repo->findCabecera($id, $idEmpresa);
        if (!$cab) return null;
        $cab['detalle'] = $this->repo->getDetalleCompleto($id, $idEmpresa);
        return $cab;
    }

    /** Línea (empleado) del rol con su cabecera, para PDF/correo individual. */
    public function getLineaEmpleado(int $idDetalle, int $idEmpresa): ?array
    {
        $lin = $this->repo->getLinea($idDetalle, $idEmpresa);
        if (!$lin) return null;
        $lin['cabecera'] = $this->repo->findCabecera((int) $lin['id_rol'], $idEmpresa);
        return $lin;
    }

    /** Detalle completo de un empleado del rol: general (rubros) + provisiones + asiento. */
    public function getEmpleadoCompleto(int $idDetalle, int $idEmpresa): ?array
    {
        $lin = $this->getLineaEmpleado($idDetalle, $idEmpresa);
        if (!$lin) return null;
        $esMensual = (($lin['cabecera']['tipo_rol'] ?? '') === 'MENSUAL');
        $salario = $this->repo->getSalario((int) ($lin['cabecera']['periodo_anio'] ?? 0));
        $prov = new RolProvisionService();
        // Provisiones y asiento solo tienen sentido en el rol mensual.
        $lin['provisiones'] = $esMensual ? $prov->calcularProvisiones($lin, $salario) : [];
        // Asiento con las cuentas reales resueltas (override por empleado > general).
        $lin['asiento'] = $esMensual
            ? (new RolAsientoService($this->repo, $this->log))->asientoEmpleado($lin, $idEmpresa, $salario)
            : null;
        return $lin;
    }

    public function crear(array $data): int
    {
        $this->rules->validateCabecera($data);
        $idEmpresa = (int) $data['id_empresa'];
        if ($this->repo->existsCorrida($idEmpresa, $data['tipo_rol'], (int) $data['periodo_anio'], (int) $data['periodo_mes'], (int) ($data['numero_periodo'] ?? 0))) {
            throw new Exception('Ya existe una corrida para ese tipo y período.');
        }

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->createCabecera($data);
            $this->log->registrar((int) $data['id_usuario'], $idEmpresa, 'CREAR', 'rol_cabecera', $id, null, $data);
            $this->repo->commit();
            return $id;
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validateCabecera($data);
        $cab = $this->repo->findCabecera($id, $idEmpresa);
        if (!$cab) throw new Exception('Corrida no encontrada.');
        if ($cab['estado'] !== 'borrador') {
            throw new Exception('Solo se puede editar una corrida en borrador. Vuelva a generarla si necesita recalcular.');
        }
        if ($this->repo->existsCorrida($idEmpresa, $data['tipo_rol'], (int) $data['periodo_anio'], (int) $data['periodo_mes'], (int) ($data['numero_periodo'] ?? 0), $id)) {
            throw new Exception('Ya existe otra corrida para ese tipo y período.');
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->updateCabecera($id, $idEmpresa, $data);
            $this->log->registrar((int) $data['id_usuario'], $idEmpresa, 'ACTUALIZAR', 'rol_cabecera', $id, $cab, $data);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Calcula (o recalcula) el detalle de la corrida para todos los empleados activos.
     */
    public function generar(int $id, int $idEmpresa, int $idUsuario): array
    {
        $cab = $this->repo->findCabecera($id, $idEmpresa);
        if (!$cab) throw new Exception('Corrida no encontrada.');
        if (in_array($cab['estado'], ['pagado', 'contabilizado', 'anulado'], true)) {
            throw new Exception('No se puede regenerar una corrida en estado ' . CatalogoRol::nombreEstado($cab['estado']) . '.');
        }

        $tipo    = (string) $cab['tipo_rol'];
        $anio    = (int) $cab['periodo_anio'];
        $mes     = (int) $cab['periodo_mes'];
        $aplica  = CatalogoRol::aplicaEn($tipo);

        // Rango del período para filtrar empleados por su fecha de ingreso vigente.
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));

        $empleados = $this->repo->getEmpleadosActivos($idEmpresa, $inicioMes, $finMes);
        $salario   = $this->repo->getSalario($anio);
        $vacRepo   = new VacacionRepository();

        $totales = ['ingresos' => 0.0, 'egresos' => 0.0, 'neto' => 0.0, 'aporte_patronal' => 0.0];

        $this->repo->beginTransaction();
        try {
            $this->repo->borrarDetalle($id);

            foreach ($empleados as $emp) {
                $idEmp    = (int) $emp['id'];
                $rubrosF  = $this->repo->getRubrosFijos($idEmp, $idEmpresa);
                $noveds   = $this->repo->getNovedades($idEmpresa, $idEmp, $anio, $mes, $aplica);
                $neteo    = $tipo === 'MENSUAL' ? $this->repo->getPagadoNeteo($idEmpresa, $idEmp, $anio, $mes) : 0.0;
                $vacacion = $tipo === 'MENSUAL' ? $vacRepo->getValorParaRol($idEmpresa, $idEmp, $anio, $mes) : 0.0;

                $calc = $this->calc->calcular($emp, $tipo, $salario, $rubrosF, $noveds, $neteo, $vacacion);

                // Omite empleados sin ningún concepto (p. ej. base 0 y sin novedades).
                if ($calc['total_ingresos'] == 0 && $calc['total_egresos'] == 0) {
                    continue;
                }

                $idDet = $this->repo->insertDetalle($id, $idEmpresa, $idEmp, $calc);
                foreach ($calc['rubros'] as $r) {
                    $this->repo->insertRubro($idDet, $idEmpresa, $r);
                }

                $totales['ingresos']        += $calc['total_ingresos'];
                $totales['egresos']         += $calc['total_egresos'];
                $totales['neto']            += $calc['neto'];
                $totales['aporte_patronal'] += $calc['aporte_patronal'];
            }

            foreach ($totales as $k => $v) $totales[$k] = round($v, 2);
            $this->repo->updateTotalesEstado($id, $totales, 'generado');
            $this->log->registrar($idUsuario, $idEmpresa, 'GENERAR', 'rol_cabecera', $id, $cab, $totales);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return $this->getDetalle($id, $idEmpresa);
    }

    /**
     * Auto-regenera los roles en estado 'generado' afectados por un cambio de
     * novedad/vacación (mismo período + tipo). No toca borrador/pagado/contabilizado/anulado.
     * Cada regeneración va en su propia transacción; los errores no rompen la operación origen.
     */
    public function regenerarAfectados(int $idEmpresa, string $aplicaEn, int $anio, int $mes, int $idUsuario): void
    {
        $tipo = match ($aplicaEn) {
            'quincena' => 'QUINCENA',
            'semanal'  => 'SEMANAL',
            default    => 'MENSUAL',
        };
        foreach ($this->repo->getRolesAfectados($idEmpresa, $tipo, $anio, $mes) as $idRol) {
            try {
                $this->generar($idRol, $idEmpresa, $idUsuario);
            } catch (\Throwable $e) {
                // No interrumpir el guardado de la novedad/vacación por un fallo de regeneración.
            }
        }
    }

    public function cambiarEstado(int $id, int $idEmpresa, string $nuevo, int $idUsuario): void
    {
        $cab = $this->repo->findCabecera($id, $idEmpresa);
        if (!$cab) throw new Exception('Corrida no encontrada.');
        if (!array_key_exists($nuevo, CatalogoRol::ESTADOS)) throw new Exception('Estado no válido.');
        if ($nuevo === 'contabilizado') throw new Exception('Use la opción Contabilizar para generar el asiento.');
        if ($cab['estado'] === 'borrador' && $nuevo === 'pagado') {
            throw new Exception('Genere la corrida antes de marcarla como pagada.');
        }

        // Al anular, revertir su asiento contable (si lo tiene).
        if ($nuevo === 'anulado' && (int) ($cab['id_asiento'] ?? 0) > 0) {
            (new RolAsientoService($this->repo, $this->log))->anularAsiento($cab, $idEmpresa, $idUsuario);
        }

        $this->repo->setEstado($id, $idEmpresa, $nuevo, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'ESTADO_' . strtoupper($nuevo), 'rol_cabecera', $id, $cab, ['estado' => $nuevo]);

        // Pagar/despagar una semana o quincena cambia el neteo del rol mensual del mismo período.
        if (in_array($cab['tipo_rol'], ['SEMANAL', 'QUINCENA'], true)) {
            $this->regenerarAfectados($idEmpresa, 'rol', (int) $cab['periodo_anio'], (int) $cab['periodo_mes'], $idUsuario);
        }
    }

    /** Genera y registra el asiento contable del rol mensual. */
    public function contabilizar(int $id, int $idEmpresa, int $idUsuario): array
    {
        return (new RolAsientoService($this->repo, $this->log))->contabilizar($id, $idEmpresa, $idUsuario);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findCabecera($id, $idEmpresa);
        if (!$cab) throw new Exception('Corrida no encontrada.');
        if (in_array($cab['estado'], ['contabilizado'], true)) {
            throw new Exception('No se puede eliminar una corrida contabilizada.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'rol_cabecera', $id, $cab, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }
}
