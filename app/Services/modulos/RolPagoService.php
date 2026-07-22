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
    private ImpuestoRentaEmpleadoService $ir;

    public function __construct(RolPagoRepository $repo, RolPagoRules $rules, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new RolCalculoService();
        $this->ir = new ImpuestoRentaEmpleadoService();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa, ?int $idUsuario = null): ?array
    {
        $cab = $this->repo->findCabecera($id, $idEmpresa);
        if (!$cab) return null;
        // Auto-refresco al abrir: si el rol está 'generado' (aún no pagado/contabilizado),
        // se regenera para reflejar cambios recientes (novedades, préstamos desembolsados,
        // neteo, etc.). Los borrador/pagado/contabilizado/anulado NO se tocan. Barato (batch).
        if ($idUsuario !== null && ($cab['estado'] ?? '') === 'generado' && !$this->repo->tienePagos($id)) {
            try {
                $this->generar($id, $idEmpresa, $idUsuario);
                $cab = $this->repo->findCabecera($id, $idEmpresa);
            } catch (\Throwable $e) {
                // Si la regeneración falla, se muestra lo último guardado.
            }
        }
        $cab['detalle'] = $this->repo->getDetalleCompleto($id, $idEmpresa);
        // Avisos: anticipos/préstamos del período aún sin desembolsar (no se descuentan en el rol).
        $cab['avisos'] = $this->repo->getAvisosPendientes(
            $idEmpresa,
            (int) $cab['periodo_anio'],
            (int) $cab['periodo_mes'],
            CatalogoRol::aplicaEn((string) $cab['tipo_rol'])
        );
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
        // Detalle de asistencia del mes del rol (contexto de faltas/atrasos/extras).
        $lin['asistencia'] = $this->getAsistenciaEmpleado(
            $idEmpresa,
            (int) $lin['id_empleado'],
            (int) ($lin['cabecera']['periodo_anio'] ?? 0),
            (int) ($lin['cabecera']['periodo_mes'] ?? 0)
        );
        return $lin;
    }

    /** Asistencia (jornadas) del empleado en el mes del rol: resumen + detalle diario. Silencioso si no está el módulo. */
    private function getAsistenciaEmpleado(int $idEmpresa, int $idEmpleado, int $anio, int $mes): array
    {
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            return ['dias' => [], 'resumen' => null];
        }
        try {
            $desde = sprintf('%04d-%02d-01', $anio, $mes);
            $hasta = date('Y-m-t', strtotime($desde));
            $jornRepo = new \App\repositories\modulos\AsistenciaJornadaRepository();
            $resumen  = $jornRepo->getResumenPeriodo($idEmpresa, $desde, $hasta, $idEmpleado);
            return [
                'dias'    => $jornRepo->getDiasEmpleado($idEmpresa, $idEmpleado, $desde, $hasta),
                'resumen' => $resumen[0] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['dias' => [], 'resumen' => null];
        }
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
        // Si el rol ya tiene pagos (egresos), NO se regenera: al recrear rol_detalle cambiarían
        // los IDs y se romperían los vínculos de pago (quedarían huérfanos → mostraría "pendiente"
        // y podría duplicarse el pago). El rol queda congelado una vez que se empieza a pagar.
        if ($this->repo->tienePagos($id)) {
            return $this->getDetalle($id, $idEmpresa);
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

        // Impuesto a la Renta (solo aplica al rol MENSUAL): tramos y tope de gasto
        // personal del año. Si el catálogo aún no está cargado, quedan vacíos y
        // la retención calculada es 0 (ver ImpuestoRentaEmpleadoService).
        $tramosIr = $tipo === 'MENSUAL' ? $this->ir->getTramosAnio($anio) : [];

        $ids = array_map(fn($e) => (int) $e['id'], $empleados);

        // Rebaja por gastos personales POR EMPLEADO: % sobre la proyección que cada
        // trabajador presentó (form. SRI-GP), topada según sus cargas familiares.
        // Rebaja el impuesto causado, no la base. Una consulta para toda la nómina.
        $rebajaGastoMap = $tipo === 'MENSUAL'
            ? $this->ir->getRebajaGastoPersonalMasivo($idEmpresa, $ids, $anio)
            : [];
        $esMensual = ($tipo === 'MENSUAL');
        // En SEMANAL/QUINCENA se omiten los empleados cuyo rol de fin de mes (MENSUAL) ya
        // está pagado: ese mes ya se cerró para ellos. Solo se generan los pendientes.
        $esParcial = ($tipo === 'SEMANAL' || $tipo === 'QUINCENA');

        // Pre-carga masiva: una consulta por dato para TODOS los empleados (evita N+1).
        $rubrosMap        = $this->repo->getRubrosFijosMasivo($idEmpresa, $ids);
        $novedMap         = $this->repo->getNovedadesMasivo($idEmpresa, $ids, $anio, $mes, $aplica);
        // Anticipos: montos pagados por egreso (descuenta solo lo pagado).
        // Préstamos: cuota descuenta solo si el préstamo ya fue desembolsado (pagado).
        $idsNovAnticipo = [];
        $idsNovPrestamo = [];
        foreach ($novedMap as $lista) {
            foreach ($lista as $n) {
                $cod = (string) $n['tipo_codigo'];
                if (\App\models\CatalogoNovedades::esPagoPorEgreso($cod)) {
                    $idsNovAnticipo[] = (int) $n['id'];
                } elseif (\App\models\CatalogoNovedades::esPrestamo($cod)) {
                    $idsNovPrestamo[] = (int) $n['id'];
                }
            }
        }
        $anticiposMap     = $this->repo->getAnticiposPagadosMasivo($idEmpresa, $idsNovAnticipo);
        $prestamosNoDesemb = $this->repo->getPrestamosNoDesembolsadosMasivo($idEmpresa, $idsNovPrestamo);
        $neteoMap         = $esMensual ? $this->repo->getPagadoNeteoMasivo($idEmpresa, $ids, $anio, $mes) : [];
        $vacMap           = $esMensual ? $vacRepo->getValorParaRolMasivo($idEmpresa, $ids, $anio, $mes) : [];
        $mensualPagadoSet = $esParcial ? $this->repo->getMensualPagadoMasivo($idEmpresa, $ids, $anio, $mes) : [];

        $totales = ['ingresos' => 0.0, 'egresos' => 0.0, 'neto' => 0.0, 'aporte_patronal' => 0.0];
        $totalCandidatos = count($empleados);
        $excluidosMensualPagado = 0;

        $this->repo->beginTransaction();
        try {
            $this->repo->borrarDetalle($id);

            // Loop en memoria: solo cálculo, sin consultas.
            $filasDetalle = []; // [ ['id_empleado'=>int, 'calc'=>[...]], ... ]
            foreach ($empleados as $emp) {
                $idEmp = (int) $emp['id'];

                if ($esParcial && isset($mensualPagadoSet[$idEmp])) {
                    $excluidosMensualPagado++;
                    continue;
                }

                $rubrosF  = $rubrosMap[$idEmp] ?? [];
                $noveds   = $novedMap[$idEmp] ?? [];
                $neteo    = $neteoMap[$idEmp] ?? 0.0;
                $vacacion = $vacMap[$idEmp] ?? 0.0;
                $dias = 30;
                if ($esMensual) {
                    $periodos = json_decode((string) ($emp['periodos_json'] ?? ''), true) ?: [];
                    $dias = self::diasTrabajadosMes($anio, $mes, $periodos);
                }

                $calc = $this->calc->calcular($emp, $tipo, $salario, $rubrosF, $noveds, $neteo, $vacacion, $dias, $anticiposMap, $prestamosNoDesemb, $tramosIr, (float) ($rebajaGastoMap[$idEmp] ?? 0.0));

                // Omite empleados sin ningún concepto (p. ej. base 0 y sin novedades).
                if ($calc['total_ingresos'] == 0 && $calc['total_egresos'] == 0) {
                    continue;
                }

                $filasDetalle[] = ['id_empleado' => $idEmp, 'calc' => $calc];
                $totales['ingresos']        += $calc['total_ingresos'];
                $totales['egresos']         += $calc['total_egresos'];
                $totales['neto']            += $calc['neto'];
                $totales['aporte_patronal'] += $calc['aporte_patronal'];
            }

            // Si es semanal/quincena y TODOS los empleados del período quedaron excluidos
            // porque su rol de fin de mes ya está pagado, no tiene sentido la corrida.
            if ($esParcial && $totalCandidatos > 0 && $excluidosMensualPagado === $totalCandidatos) {
                throw new Exception('No se puede generar esta corrida: el rol de fin de mes de ' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio . ' ya está pagado para todos los empleados del período.');
            }

            // Inserción en lote: detalle (con RETURNING para mapear id) + rubros.
            if (!empty($filasDetalle)) {
                $idsDetalle = $this->repo->insertDetalleMasivo($id, $idEmpresa, $filasDetalle);
                $filasRubro = [];
                foreach ($filasDetalle as $f) {
                    $idDet = $idsDetalle[$f['id_empleado']] ?? null;
                    if ($idDet === null) continue;
                    foreach ($f['calc']['rubros'] as $r) {
                        $filasRubro[] = ['id_detalle' => $idDet, 'rubro' => $r];
                    }
                }
                $this->repo->insertRubrosMasivo($idEmpresa, $filasRubro);
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
     * Días efectivamente laborados en el mes (convención de 30 días de Ecuador),
     * SUMANDO todos los tramos de empleo que solapan el mes (un empleado puede tener
     * varios períodos, p. ej. salida y reingreso en el mismo mes). Cada tramo cuenta
     * inclusivo el día de ingreso y el de salida. Sin tramos → mes completo (30).
     * El total se limita a 30 (los tramos de un mismo empleado no se solapan).
     *
     * @param array<int,array{i?:?string,s?:?string}> $periodos
     */
    public static function diasTrabajadosMes(int $anio, int $mes, array $periodos): int
    {
        if (empty($periodos)) return 30; // sin registros de períodos → mes completo

        $diaEnEsteMes = function (?string $fecha) use ($anio, $mes): ?int {
            if (empty($fecha)) return null;
            $ts = strtotime($fecha);
            if ($ts === false) return null;
            if ((int) date('Y', $ts) !== $anio || (int) date('n', $ts) !== $mes) return null;
            return min(30, max(1, (int) date('j', $ts)));
        };

        $total = 0;
        foreach ($periodos as $p) {
            $inicioDia = $diaEnEsteMes($p['i'] ?? null) ?? 1;   // ingresó este mes → desde ese día
            $finDia    = $diaEnEsteMes($p['s'] ?? null) ?? 30;   // salió este mes → hasta ese día
            $total    += max(0, $finDia - $inicioDia + 1);
        }
        return max(0, min(30, $total));
    }

    /**
     * Impide editar las fechas de ingreso/salida de un empleado cuando el cambio
     * alteraría un rol YA PAGADO: si cambia la elegibilidad (empleado dentro/fuera de
     * un mes pagado) o los días laborados de un mes con rol MENSUAL pagado, lanza excepción.
     * Los cambios que no tocan meses pagados (registrar una salida/reingreso a futuro) sí se permiten.
     * Silencioso (no bloquea) si el módulo de roles/egresos no está disponible.
     *
     * @param array<int,array> $periodosNuevos  Períodos entrantes (fecha_ingreso/fecha_salida)
     * @param array<int,array> $periodosViejos  Períodos actuales en BD
     */
    public function validarPeriodosContraRolesPagados(int $idEmpresa, int $idEmpleado, array $periodosNuevos, array $periodosViejos): void
    {
        try {
            $meses = $this->repo->getMesesRolPagado($idEmpresa, $idEmpleado);
        } catch (\Throwable $e) {
            return; // roles/egresos no desplegado → sin restricción
        }
        if (empty($meses)) return;

        $norm = fn(array $ps) => array_values(array_map(
            fn($p) => ['i' => $p['fecha_ingreso'] ?? null, 's' => $p['fecha_salida'] ?? null],
            array_filter($ps, fn($p) => !empty($p['fecha_ingreso']))
        ));
        $viejos = $norm($periodosViejos);
        $nuevos = $norm($periodosNuevos);

        foreach ($meses as $m) {
            $anio = (int) $m['anio'];
            $mes  = (int) $m['mes'];
            $tipo = (string) ($m['tipo_rol'] ?? 'MENSUAL');

            // (a) Elegibilidad: el empleado no puede quedar dentro/fuera de un mes ya pagado.
            if ($this->elegibleMes($viejos, $anio, $mes) !== $this->elegibleMes($nuevos, $anio, $mes)) {
                throw new Exception($this->msgRolPagado($mes, $anio));
            }
            // (b) Prorrateo: en un MENSUAL pagado, no pueden cambiar los días laborados.
            if ($tipo === 'MENSUAL'
                && $this->diasEfectivosMes($viejos, $anio, $mes) !== $this->diasEfectivosMes($nuevos, $anio, $mes)) {
                throw new Exception($this->msgRolPagado($mes, $anio));
            }
        }
    }

    /** Roles abiertos ('generado' sin pagar) del empleado (para avisar/regenerar al editarlo). */
    public function getRolesAbiertosEmpleado(int $idEmpresa, int $idEmpleado): array
    {
        return $this->repo->getRolesAbiertosEmpleado($idEmpresa, $idEmpleado);
    }

    private function msgRolPagado(int $mes, int $anio): string
    {
        return 'No se puede modificar la fecha de ingreso/salida: el empleado tiene un rol pagado en '
            . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio
            . ' y el cambio afectaría ese rol. Anule el pago/rol de ese período si necesita corregir la fecha.';
    }

    /** ¿El empleado es elegible para el mes? (sin períodos = sí; con períodos = al menos uno solapa). */
    private function elegibleMes(array $periodos, int $anio, int $mes): bool
    {
        if (empty($periodos)) return true;
        return !empty($this->periodosQueSolapan($periodos, $anio, $mes));
    }

    /** Días efectivos del mes considerando solo los tramos que lo solapan (0 si tiene períodos pero ninguno cubre el mes). */
    private function diasEfectivosMes(array $periodos, int $anio, int $mes): int
    {
        if (empty($periodos)) return 30;
        $solapan = $this->periodosQueSolapan($periodos, $anio, $mes);
        if (empty($solapan)) return 0;
        return self::diasTrabajadosMes($anio, $mes, $solapan);
    }

    /** @return array<int,array{i?:?string,s?:?string}> tramos que solapan el mes */
    private function periodosQueSolapan(array $periodos, int $anio, int $mes): array
    {
        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes    = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));
        return array_values(array_filter($periodos, function ($p) use ($inicioMes, $finMes) {
            $i = $p['i'] ?? null;
            $s = $p['s'] ?? null;
            if (empty($i)) return false;
            $i = substr((string) $i, 0, 10);
            $s = $s ? substr((string) $s, 0, 10) : null;
            return $i <= $finMes && ($s === null || $s === '' || $s >= $inicioMes);
        }));
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
