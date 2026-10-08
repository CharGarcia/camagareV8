<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsientoContableRepository;
use App\repositories\modulos\AsientoProgramadoRepository;
use App\repositories\modulos\UtilidadesRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Rules\modulos\UtilidadesRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Participación de los trabajadores en las utilidades (15%): cálculo anual,
 * edición del detalle, asiento del 31 de diciembre y anulación. El pago se hace
 * desde Egresos → Nómina (tipo_documento 'UTILIDADES'); el informe al
 * Ministerio lo arma UtilidadesExportService.
 */
class UtilidadesService
{
    /** modulo_origen con el que se guarda el asiento del 31-dic. */
    public const MODULO_ORIGEN_ASIENTO = 'utilidades';

    private UtilidadesRepository $repo;
    private UtilidadesRules $rules;
    private LogSistemaService $log;
    private UtilidadesCalculoService $calc;

    public function __construct(UtilidadesRepository $repo, UtilidadesRules $rules, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new UtilidadesCalculoService();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, array $ordenMulti = []): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $ordenMulti);
    }

    public function getCabecera(int $id, int $idEmpresa): ?array
    {
        return $this->repo->findById($id, $idEmpresa);
    }

    public function getDetalle(int $idCabecera, int $idEmpresa): array
    {
        return $this->repo->getDetalle($idCabecera, $idEmpresa);
    }

    public function tienePagos(int $idCabecera): bool
    {
        return $this->repo->tienePagos($idCabecera);
    }

    /** False mientras no se haya ejecutado el SQL del módulo en la base. */
    public function instalado(): bool
    {
        return $this->repo->instalado();
    }

    /** Mensaje único para toda acción que necesita las tablas del módulo. */
    public const MSG_NO_INSTALADO = 'El módulo Utilidades todavía no está instalado en esta base: ejecute database/migrations/20261008_create_utilidades.sql.';

    /** 15% de la utilidad líquida: lo que el modal propone como monto a repartir. */
    public function montoLegal(float $utilidadLiquida): float
    {
        return $this->calc->montoLegal($utilidadLiquida);
    }

    /**
     * Calcula (o recalcula) el reparto de un ejercicio: trae a todos los
     * trabajadores y ex trabajadores del año, sus días laborados y cargas, y
     * guarda cabecera + detalle. Al recalcular se conservan los campos que el
     * usuario editó en la grilla (nombres, tipo de pago, retención, discapacidad y
     * cargas); días y valores se vuelven a calcular.
     */
    public function calcular(int $idEmpresa, int $anio, float $utilidadLiquida, float $montoRepartir, int $idUsuario): int
    {
        if (!$this->repo->instalado()) {
            throw new Exception(self::MSG_NO_INSTALADO);
        }
        $this->rules->validarCalculo(['anio' => $anio, 'utilidad_liquida' => $utilidadLiquida, 'monto_repartir' => $montoRepartir]);

        $existente = $this->repo->findCabeceraPorAnio($idEmpresa, $anio);
        $anteriores = [];
        if ($existente) {
            $this->rules->validarRecalculo($existente, $this->repo->tienePagos((int) $existente['id']));
            foreach ($this->repo->getDetalle((int) $existente['id'], $idEmpresa) as $d) {
                $anteriores[(int) $d['id_empleado']] = $d;
            }
        }

        $periodo = $this->calc->periodo($anio);
        $salario = $this->repo->getSalario($anio);
        $sbu = (float) ($salario['sbu'] ?? 0);

        $empleados = $this->repo->getEmpleadosDelEjercicio($idEmpresa, $periodo['fecha_desde'], $periodo['fecha_hasta']);
        $periodos  = $this->repo->getPeriodosPorEmpleado(array_map(fn($e) => (int) $e['id'], $empleados), $idEmpresa);

        $trabajadores = [];
        $datos = [];
        foreach ($empleados as $emp) {
            $idEmp = (int) $emp['id'];
            $dias = $this->calc->diasLaborados($periodos[$idEmp] ?? [], $periodo['fecha_desde'], $periodo['fecha_hasta']);
            if ($dias <= 0) continue;
            $prev = $anteriores[$idEmp] ?? null;
            // Las cargas salen de la ficha del empleado; si el usuario las corrigió en
            // la grilla de esta corrida, manda lo que dejó ahí.
            $cargas = $prev ? (int) $prev['cargas_familiares'] : max(0, (int) ($emp['cargas_familiares'] ?? 0));
            $trabajadores[$idEmp] = ['dias' => $dias, 'cargas' => $cargas];
            $datos[$idEmp] = ['emp' => $emp, 'prev' => $prev];
        }

        $reparto = $this->calc->repartir($trabajadores, $montoRepartir, $sbu);

        $filas = [];
        $totalValor = 0.0;
        $totalExced = 0.0;
        foreach ($trabajadores as $idEmp => $t) {
            $emp  = $datos[$idEmp]['emp'];
            $prev = $datos[$idEmp]['prev'];
            $r    = $reparto['filas'][$idEmp];
            $totalValor += $r['valor'];
            $totalExced += $r['excedente'];

            if ($prev) {
                $nombres = (string) $prev['nombres'];
                $apellidos = (string) $prev['apellidos'];
            } else {
                [$nombres, $apellidos] = $this->partirNombreCompleto((string) $emp['nombres_apellidos']);
            }

            $filas[] = [
                'id_empleado'       => $idEmp,
                'identificacion'    => $emp['identificacion'],
                'nombres'           => $nombres,
                'apellidos'         => $apellidos,
                'sexo'              => $emp['sexo'],
                'codigo_ocupacion'  => $emp['codigo_sectorial_iess'],
                'activo'            => ($emp['estado'] ?? '') === 'activo',
                'dias_laborados'    => $t['dias'],
                'cargas_familiares' => $t['cargas'],
                'valor_10'          => $r['valor_10'],
                'valor_5'           => $r['valor_5'],
                'valor_bruto'       => $r['valor_bruto'],
                'excedente'         => $r['excedente'],
                'valor'             => $r['valor'],
                // Retención judicial en 0 salvo que el usuario la haya fijado en esta corrida:
                // el valor mensual de la ficha es de un rol, no de este pago único.
                'valor_retencion'   => $prev ? (float) $prev['valor_retencion'] : 0.0,
                'tipo_pago'         => $prev ? (string) $prev['tipo_pago'] : 'P',
                'discapacidad'      => $prev ? $this->truthy($prev['discapacidad']) : $this->truthy($emp['discapacidad'] ?? false),
            ];
        }

        $this->repo->beginTransaction();
        try {
            if ($existente) {
                $idCabecera = (int) $existente['id'];
                $this->repo->limpiarDetalle($idCabecera);
            } else {
                $idCabecera = $this->repo->crearCabecera([
                    'id_empresa'        => $idEmpresa,
                    'anio'              => $anio,
                    'fecha_desde'       => $periodo['fecha_desde'],
                    'fecha_hasta'       => $periodo['fecha_hasta'],
                    'fecha_limite_pago' => $periodo['fecha_limite'],
                    'utilidad_liquida'  => $utilidadLiquida,
                    'monto_repartir'    => $montoRepartir,
                    'sbu_aplicado'      => $sbu,
                    'id_usuario'        => $idUsuario,
                ]);
            }
            $this->repo->insertDetalleMasivo($idCabecera, $idEmpresa, $filas, $idUsuario);
            // Si ya estaba contabilizado, el asiento quedó desfasado del nuevo monto:
            // vuelve a 'calculado' y el usuario lo contabiliza de nuevo (regenera el mismo asiento).
            $this->repo->actualizarCabecera($idCabecera, [
                'utilidad_liquida' => $utilidadLiquida,
                'monto_repartir'   => $montoRepartir,
                'monto_10'         => $reparto['monto_10'],
                'monto_5'          => $reparto['monto_5'],
                'sbu_aplicado'     => $sbu,
                'tope_trabajador'  => $reparto['tope'],
                'total_empleados'  => count($filas),
                'total_dias'       => $reparto['total_dias'],
                'total_cargas'     => $reparto['total_cargas'],
                'total_valor'      => round($totalValor, 2),
                'total_excedente'  => round($totalExced, 2),
                'estado'           => 'calculado',
            ], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $existente ? 'RECALCULAR' : 'CALCULAR', 'utilidades_cabecera', $idCabecera, $existente, [
                'anio' => $anio, 'utilidad_liquida' => $utilidadLiquida, 'monto_repartir' => $montoRepartir,
                'total_empleados' => count($filas), 'total_valor' => round($totalValor, 2), 'total_excedente' => round($totalExced, 2),
                'sin_cargas' => $reparto['sin_cargas'],
            ]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return $idCabecera;
    }

    /**
     * Ajusta una fila. Nombres, tipo de pago, retención y discapacidad no tocan
     * el reparto; las cargas sí (cambian el 5% de todos), así que al editarlas se
     * vuelve a repartir toda la corrida con los mismos montos.
     */
    public function actualizarDetalleEmpleado(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): void
    {
        $fila = $this->repo->findDetalle($idDetalle, $idEmpresa);
        if (!$fila) throw new Exception('Registro no encontrado.');
        $idCabecera = (int) $fila['id_cabecera'];
        $tienePagos = $this->repo->tienePagos($idCabecera);
        $this->rules->validarDetalle($campos, $tienePagos);

        $this->repo->beginTransaction();
        try {
            $ok = $this->repo->actualizarDetalle($idDetalle, $idEmpresa, $campos, $idUsuario);
            if (!$ok) throw new Exception('No se pudo actualizar el registro (verifique los campos enviados).');
            if (array_key_exists('cargas_familiares', $campos)) {
                $this->redistribuir($idCabecera, $idEmpresa, $idUsuario);
            }
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Vuelve a repartir con los días y cargas que hoy tiene el detalle (mismos montos de cabecera). */
    private function redistribuir(int $idCabecera, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findById($idCabecera, $idEmpresa);
        if (!$cab) throw new Exception('Utilidades no encontradas.');
        $detalle = $this->repo->getDetalle($idCabecera, $idEmpresa);

        $trabajadores = [];
        foreach ($detalle as $d) {
            $trabajadores[(int) $d['id']] = ['dias' => (int) $d['dias_laborados'], 'cargas' => (int) $d['cargas_familiares']];
        }
        $reparto = $this->calc->repartir($trabajadores, (float) $cab['monto_repartir'], (float) $cab['sbu_aplicado']);

        $totalValor = 0.0;
        $totalExced = 0.0;
        $totalCargas = 0;
        foreach ($detalle as $d) {
            $r = $reparto['filas'][(int) $d['id']];
            $totalValor += $r['valor'];
            $totalExced += $r['excedente'];
            $totalCargas += (int) $d['cargas_familiares'];
            $this->repo->actualizarValoresDetalle((int) $d['id'], [
                'dias_laborados'    => (int) $d['dias_laborados'],
                'cargas_familiares' => (int) $d['cargas_familiares'],
            ] + $r, $idUsuario);
        }
        $this->repo->actualizarCabecera($idCabecera, [
            'utilidad_liquida' => (float) $cab['utilidad_liquida'],
            'monto_repartir'   => (float) $cab['monto_repartir'],
            'monto_10'         => $reparto['monto_10'],
            'monto_5'          => $reparto['monto_5'],
            'sbu_aplicado'     => (float) $cab['sbu_aplicado'],
            'tope_trabajador'  => $reparto['tope'],
            'total_empleados'  => count($detalle),
            'total_dias'       => $reparto['total_dias'],
            'total_cargas'     => $totalCargas,
            'total_valor'      => round($totalValor, 2),
            'total_excedente'  => round($totalExced, 2),
            'estado'           => (string) $cab['estado'],
        ], $idUsuario);
    }

    /**
     * Asiento del 31 de diciembre del ejercicio: Gasto Participación Trabajadores
     * (debe) contra Participación Trabajadores por Pagar (haber), por el monto
     * repartido completo (lo que cobran los trabajadores + el excedente que va al
     * IESS). Si ya existe un asiento de esta corrida, se regenera en el mismo id.
     */
    public function contabilizar(int $idCabecera, int $idEmpresa, int $idUsuario): array
    {
        $cab = $this->repo->findById($idCabecera, $idEmpresa);
        if (!$cab) throw new Exception('Utilidades no encontradas.');
        $this->rules->validarContabilizacion($cab);

        $progRepo = new AsientoProgramadoRepository();
        $ctas = [];
        foreach ($progRepo->getReglasGeneralesPorConcepto($idEmpresa, 'nomina') as $r) {
            $ctas[$r['codigo']] = $r;
        }
        $faltan = [];
        $cuentaDe = function (string $codigo, string $nombre) use ($ctas, &$faltan): int {
            $idCuenta = (int) ($ctas[$codigo]['id_cuenta'] ?? 0);
            if ($idCuenta <= 0) $faltan[] = $nombre;
            return $idCuenta;
        };
        $idGasto  = $cuentaDe('GASTOPARTICIPACIONTRABAJADORESNOMINA', 'Gasto Participación Trabajadores');
        $idPasivo = $cuentaDe('PARTICIPACIONTRABAJADORESPORPAGARNOMINA', 'Participación Trabajadores por Pagar');
        if ($faltan) {
            throw new Exception('Configure las cuentas de nómina en Configuración Contable: ' . implode(', ', $faltan) . '.');
        }

        $monto = round((float) $cab['monto_repartir'], 2);
        $ref   = 'Utilidades ' . $cab['anio'];
        $lineas = [
            [
                'id_cuenta_contable'   => $idGasto,
                'debe'                 => $monto,
                'haber'                => 0.0,
                'referencia_detalle'   => 'Participación trabajadores 15% ' . $cab['anio'],
                'documento_referencia' => $ref,
            ],
            [
                'id_cuenta_contable'   => $idPasivo,
                'debe'                 => 0.0,
                'haber'                => $monto,
                'referencia_detalle'   => 'Participación trabajadores por pagar ' . $cab['anio'],
                'documento_referencia' => $ref,
            ],
        ];

        $asientoService = new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->log);
        $idsExistentes  = $asientoService->getIdsAsientosPorOrigen(self::MODULO_ORIGEN_ASIENTO, $idCabecera, $idEmpresa);
        $previoId = $idsExistentes[0] ?? null;
        foreach ($idsExistentes as $idExistente) {
            if ($idExistente !== $previoId) {
                $asientoService->anular($idExistente, $idEmpresa, $idUsuario);
            }
        }

        $idAsiento = $asientoService->guardarAsiento([
            'id'                   => $previoId,
            'fecha_asiento'        => (string) $cab['fecha_hasta'],
            'tipo_comprobante'     => 'nomina',
            'numero_comprobante'   => '',
            'concepto'             => 'Participación de trabajadores en utilidades ' . $cab['anio'],
            'estado'               => 'contabilizado',
            'modulo_origen'        => self::MODULO_ORIGEN_ASIENTO,
            'id_referencia_origen' => $idCabecera,
            'observaciones'        => null,
        ], $lineas, $idEmpresa, $idUsuario);

        $this->repo->setIdAsiento($idCabecera, $idEmpresa, $idAsiento);
        $this->repo->setEstado($idCabecera, $idEmpresa, 'contabilizado', $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'CONTABILIZAR', 'utilidades_cabecera', $idCabecera, $cab, ['id_asiento' => $idAsiento, 'monto' => $monto]);

        return ['id_asiento' => $idAsiento, 'monto' => $monto];
    }

    public function anular(int $idCabecera, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findById($idCabecera, $idEmpresa);
        if (!$cab) throw new Exception('Utilidades no encontradas.');
        $this->rules->validarAnulacion($cab, $this->repo->tienePagos($idCabecera));

        $this->repo->beginTransaction();
        try {
            // El asiento del 31-dic se anula con la corrida; si no, quedaría un gasto sin respaldo.
            $asientoService = new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->log);
            foreach ($asientoService->getIdsAsientosPorOrigen(self::MODULO_ORIGEN_ASIENTO, $idCabecera, $idEmpresa) as $idAsiento) {
                $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
            }
            $this->repo->eliminarLogico($idCabecera, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR', 'utilidades_cabecera', $idCabecera, $cab, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Divide "Nombres y Apellidos" (campo único de la ficha, convención: nombres
     * primero y luego apellidos) en dos mitades de palabras. Si en el informe sale
     * mal, se corrige el orden en la ficha del empleado o en la grilla.
     * @return array{0:string,1:string}
     */
    private function partirNombreCompleto(string $completo): array
    {
        $partes = preg_split('/\s+/', trim($completo)) ?: [];
        $n = count($partes);
        if ($n <= 1) return [$completo, ''];
        $mitad = (int) ceil($n / 2);
        return [
            implode(' ', array_slice($partes, 0, $mitad)),
            implode(' ', array_slice($partes, $mitad)),
        ];
    }

    private function truthy($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true'], true);
    }
}
