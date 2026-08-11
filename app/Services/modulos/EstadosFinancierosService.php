<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\Services\ReportService;
use Exception;
use TCPDF;

class EstadosFinancierosService
{
    private EstadosFinancierosRepository $repository;
    private ReportService $reportService;
    private EmpresaRepository $empresaRepo;
    private ConsolidacionGruposService $consolidacionSvc;

    public function __construct(
        EstadosFinancierosRepository $repository,
        ReportService $reportService,
        ?EmpresaRepository $empresaRepo = null,
        ?ConsolidacionGruposService $consolidacionSvc = null
    ) {
        $this->repository = $repository;
        $this->reportService = $reportService;
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepository();
        $this->consolidacionSvc = $consolidacionSvc ?? new ConsolidacionGruposService(
            new \App\repositories\modulos\ConsolidacionGruposRepository(),
            new \App\Rules\modulos\ConsolidacionGruposRules(),
            new \App\Services\LogSistemaService(),
            $this->empresaRepo
        );
    }

    /** Signo del saldo según el tipo contable del grupo consolidado (mismo criterio que el rollup por prefijo de código). */
    private function signoSaldoConsolidado(string $tipo, float $debe, float $haber): float
    {
        return match ($tipo) {
            'ACTIVO', 'COSTO', 'GASTO' => $debe - $haber,
            'PASIVO', 'PATRIMONIO', 'INGRESO' => $haber - $debe,
            default => 0.0,
        };
    }

    /**
     * Vista "Consolidado por RUC": arma un resumen adicional con los conceptos ya mapeados en
     * Balances Consolidados (sumados entre establecimientos del RUC accesible al usuario) y,
     * por separado, el reporte COMPLETO de cada establecimiento (Situación Financiera + Resultados,
     * sin modificar ninguna de las dos funciones existentes ni su jerarquía). Nunca reconstruye un
     * árbol único entre planes de cuentas distintos — solo suma lo que el usuario mapeó a mano.
     * Si el RUC solo tiene un establecimiento accesible, 'aplica' viene en false.
     */
    public function getConsolidadoRuc(int $idEmpresa, int $idUsuario, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $idsGrupo = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        if (count($idsGrupo) <= 1) {
            return ['aplica' => false, 'consolidado' => [], 'por_establecimiento' => []];
        }

        $mapa = $this->consolidacionSvc->getMapaCuentaGrupo($idEmpresa);
        $etiquetas = $this->empresaRepo->getEtiquetasEstablecimiento($idsGrupo);

        $gruposAcum = [];
        $porEstablecimiento = [];

        foreach ($idsGrupo as $idEmp) {
            $saldos = $this->repository->getSaldos($idEmp, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
            foreach ($saldos as $s) {
                $idCuenta = (int) $s['id_cuenta'];
                if (!isset($mapa[$idCuenta])) {
                    continue;
                }
                $g = $mapa[$idCuenta];
                if (!isset($gruposAcum[$g['id_grupo']])) {
                    $gruposAcum[$g['id_grupo']] = [
                        'nombre' => $g['nombre'], 'tipo' => $g['tipo'], 'orden' => $g['orden'],
                        'saldo' => 0.0, 'detalle' => [],
                    ];
                }
                $valor = $this->signoSaldoConsolidado($g['tipo'], (float) $s['total_debe'], (float) $s['total_haber']);
                $gruposAcum[$g['id_grupo']]['saldo'] += $valor;
                $gruposAcum[$g['id_grupo']]['detalle'][] = [
                    'establecimiento' => $etiquetas[$idEmp] ?? ('Empresa ' . $idEmp),
                    'codigo' => $s['codigo'], 'nombre' => $s['nombre'], 'valor' => $valor,
                ];
            }

            // Reporte completo de este establecimiento, tal cual existe hoy — no se toca ni se
            // excluyen las cuentas ya mapeadas, para no arriesgar el cuadre de cada reporte individual.
            $porEstablecimiento[] = [
                'id_empresa' => $idEmp,
                'etiqueta'   => $etiquetas[$idEmp] ?? ('Empresa ' . $idEmp),
                'situacion'  => $this->getEstadoSituacionFinanciera($idEmp, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivelReporte),
                'resultados' => $this->getEstadoResultados($idEmp, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivelReporte),
            ];
        }

        $consolidado = array_values($gruposAcum);
        usort($consolidado, fn ($a, $b) => $a['orden'] <=> $b['orden']);

        return ['aplica' => true, 'consolidado' => $consolidado, 'por_establecimiento' => $porEstablecimiento];
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repository->getAniosDisponibles($idEmpresa);
    }

    public function getCentrosCostoActivos(int $idEmpresa): array
    {
        return $this->repository->getCentrosCostoActivos($idEmpresa);
    }

    public function getProyectosActivos(int $idEmpresa): array
    {
        return $this->repository->getProyectosActivos($idEmpresa);
    }

    /**
     * Procesa y estructura el Estado de Resultados
     */
    public function getEstadoResultados(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $saldos = $this->repository->getSaldos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        $ingresos = [];
        $costos = [];
        $gastos = [];

        $totalIngresos = 0.0;
        $totalCostos = 0.0;
        $totalGastos = 0.0;

        // 1. Calcular saldos directos
        foreach ($saldos as &$saldo) {
            $codigoStr = (string)$saldo['codigo'];
            $debe = (float)$saldo['total_debe'];
            $haber = (float)$saldo['total_haber'];

            if (str_starts_with($codigoStr, '4')) {
                $saldo['saldo_directo'] = $haber - $debe;
                $totalIngresos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '5')) {
                $saldo['saldo_directo'] = $debe - $haber;
                $totalCostos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '6')) {
                $saldo['saldo_directo'] = $debe - $haber;
                $totalGastos += $saldo['saldo_directo'];
            } else {
                $saldo['saldo_directo'] = 0;
            }
        }
        unset($saldo);

        // 2. Acumular saldos de hijos a padres (Rollup)
        foreach ($saldos as &$padre) {
            $suma = 0;
            $prefijo = $padre['codigo'] . '.';
            foreach ($saldos as $hijo) {
                if (str_starts_with((string)$hijo['codigo'], $prefijo)) {
                    $suma += $hijo['saldo_directo'];
                }
            }
            $padre['saldo_final'] = $padre['saldo_directo'] + $suma;
        }
        unset($padre);

        // 3. Filtrar por nivel y eliminar ceros
        foreach ($saldos as $saldo) {
            if ((int)$saldo['nivel'] > $nivelReporte) {
                continue;
            }
            if (round($saldo['saldo_final'], 2) == 0) {
                continue;
            }

            $codigoStr = (string)$saldo['codigo'];
            if (str_starts_with($codigoStr, '4')) {
                $ingresos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '5')) {
                $costos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '6')) {
                $gastos[] = $saldo;
            }
        }

        $utilidadBruta = $totalIngresos - $totalCostos;
        $utilidadNeta = $utilidadBruta - $totalGastos;

        return [
            'ingresos' => $ingresos,
            'costos' => $costos,
            'gastos' => $gastos,
            'totales' => [
                'ingresos' => $totalIngresos,
                'costos' => $totalCostos,
                'gastos' => $totalGastos,
                'utilidad_bruta' => $utilidadBruta,
                'utilidad_neta' => $utilidadNeta
            ]
        ];
    }

    /**
     * Procesa y estructura el Estado de Situación Financiera
     */
    public function getEstadoSituacionFinanciera(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        // El saldo inicial de cada período se registra manualmente como un asiento de apertura
        // (no se arrastra automáticamente del histórico anterior a fecha_inicio); por eso solo
        // se usa el movimiento dentro del rango filtrado, igual que el Estado de Resultados.
        $saldos = $this->repository->getSaldos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        $activos = [];
        $pasivos = [];
        $patrimonio = [];

        $totalActivos = 0.0;
        $totalPasivos = 0.0;
        $totalPatrimonio = 0.0;

        // 1. Calcular saldos directos
        foreach ($saldos as &$saldo) {
            $codigoStr = (string)$saldo['codigo'];
            $debe = (float)$saldo['total_debe'];
            $haber = (float)$saldo['total_haber'];

            if (str_starts_with($codigoStr, '1')) {
                $saldo['saldo_directo'] = $debe - $haber;
                $totalActivos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '2')) {
                $saldo['saldo_directo'] = $haber - $debe;
                $totalPasivos += $saldo['saldo_directo'];
            } elseif (str_starts_with($codigoStr, '3')) {
                $saldo['saldo_directo'] = $haber - $debe;
                $totalPatrimonio += $saldo['saldo_directo'];
            } else {
                $saldo['saldo_directo'] = 0;
            }
        }
        unset($saldo);

        // 2. Acumular saldos de hijos a padres (Rollup)
        foreach ($saldos as &$padre) {
            $suma = 0;
            $prefijo = $padre['codigo'] . '.';
            foreach ($saldos as $hijo) {
                if (str_starts_with((string)$hijo['codigo'], $prefijo)) {
                    $suma += $hijo['saldo_directo'];
                }
            }
            $padre['saldo_final'] = $padre['saldo_directo'] + $suma;
        }
        unset($padre);

        // 3. Filtrar por nivel y eliminar ceros
        foreach ($saldos as $saldo) {
            if ((int)$saldo['nivel'] > $nivelReporte) {
                continue;
            }
            if (round($saldo['saldo_final'], 2) == 0) {
                continue;
            }

            $codigoStr = (string)$saldo['codigo'];
            if (str_starts_with($codigoStr, '1')) {
                $activos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '2')) {
                $pasivos[] = $saldo;
            } elseif (str_starts_with($codigoStr, '3')) {
                $patrimonio[] = $saldo;
            }
        }

        // Obtener la Utilidad del Ejercicio (de las cuentas 4/5/6)
        $resultados = $this->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $utilidadNeta = $resultados['totales']['utilidad_neta'];

        // CIERRE VIRTUAL: los datos migrados dejaron el resultado en la cuenta PIVOT (clase 7 =
        // "Resumen de Resultados", que ningún reporte muestra). Se suma su saldo (haber - debe) al
        // resultado para que el Balance cuadre. No hay doble conteo: el resultado está en 4/5/6
        // (utilidadNeta) o en la clase 7, nunca en ambos.
        $saldoPivot = 0.0;
        foreach ($saldos as $s) {
            if ((int) $s['nivel'] === 5 && str_starts_with((string) $s['codigo'], '7')) {
                $saldoPivot += (float) $s['total_haber'] - (float) $s['total_debe'];
            }
        }
        $resultado = $utilidadNeta + $saldoPivot;

        // Cuenta de patrimonio configurada según el signo (Cierre del Ejercicio en Config. Contable)
        $ctasCierre = $this->repository->getCuentasCierreEjercicio($idEmpresa);
        if ($resultado >= 0) {
            $cta = $ctasCierre['utilidad'] ?? null;
            $lblNeta = 'Utilidad del Ejercicio';
        } else {
            $cta = $ctasCierre['perdida'] ?? null;
            $lblNeta = 'Pérdida del Ejercicio';
        }
        $patrimonio[] = [
            'codigo' => $cta['codigo'] ?? '',
            'nombre' => $cta['nombre'] ?? $lblNeta,
            'saldo_final' => $resultado,
            'nivel' => 1
        ];

        $totalPatrimonio += $resultado;
        $totalPasivoPatrimonio = $totalPasivos + $totalPatrimonio;

        return [
            'activos' => $activos,
            'pasivos' => $pasivos,
            'patrimonio' => $patrimonio,
            'totales' => [
                'activos' => $totalActivos,
                'pasivos' => $totalPasivos,
                'patrimonio' => $totalPatrimonio,
                'pasivo_patrimonio' => $totalPasivoPatrimonio
            ]
        ];
    }

    /**
     * Límite de columnas del reporte "por periodos" (meses). Protege de rangos absurdos
     * (ej. 10 años) que generarían tablas horizontales inmanejables.
     */
    private const MAX_PERIODOS = 36;

    /**
     * Estado de Resultados horizontal por mes: cada columna es el movimiento propio de ese
     * mes (no acumulado), igual que el Estado de Resultados de un solo periodo pero repetido
     * mes a mes. La columna "total" es la suma de los meses (equivale al reporte de rango completo).
     */
    public function getEstadoResultadosPorPeriodos(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $periodos = $this->construirPeriodos($fechaInicio, $fechaFin);
        $claves = array_keys($periodos);

        $catalogo = $this->indexarPorId($this->repository->getPlanCuentas($idEmpresa));
        $mov = $this->indexarMovimientos($this->repository->getSaldosPorPeriodo($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto));

        $calc = $this->calcularClasesPorPeriodo($catalogo, $mov, $claves, ['4' => 'C', '5' => 'D', '6' => 'D'], $nivelReporte);

        $utilidadBruta = [];
        $utilidadNeta = [];
        foreach ($claves as $p) {
            $utilidadBruta[$p] = $calc['totales']['4'][$p] - $calc['totales']['5'][$p];
            $utilidadNeta[$p] = $utilidadBruta[$p] - $calc['totales']['6'][$p];
        }

        // Ocultar columnas de mes sin ningún movimiento (no aportan información y alargan
        // la tabla horizontal innecesariamente). El total de la derecha no cambia: los meses
        // ocultos sumaban cero.
        $clavesVisibles = $this->clavesConMovimiento($mov, $claves);

        return [
            'periodos' => array_intersect_key($periodos, array_flip($clavesVisibles)),
            'ingresos' => $this->filtrarValoresItems($this->conTotalItems($calc['grupos']['4']), $clavesVisibles),
            'costos' => $this->filtrarValoresItems($this->conTotalItems($calc['grupos']['5']), $clavesVisibles),
            'gastos' => $this->filtrarValoresItems($this->conTotalItems($calc['grupos']['6']), $clavesVisibles),
            'totales' => [
                'ingresos' => $this->filtrarPorPeriodo($this->conTotal($calc['totales']['4']), $clavesVisibles),
                'costos' => $this->filtrarPorPeriodo($this->conTotal($calc['totales']['5']), $clavesVisibles),
                'gastos' => $this->filtrarPorPeriodo($this->conTotal($calc['totales']['6']), $clavesVisibles),
                'utilidad_bruta' => $this->filtrarPorPeriodo($this->conTotal($utilidadBruta), $clavesVisibles),
                'utilidad_neta' => $this->filtrarPorPeriodo($this->conTotal($utilidadNeta), $clavesVisibles),
            ],
        ];
    }

    /**
     * Estado de Situación Financiera horizontal por mes: cada columna es el saldo ACUMULADO
     * desde fecha_inicio hasta el fin de ese mes (un balance es una fotografía a una fecha, no
     * un flujo mensual). El último periodo equivale al Estado de Situación Financiera de rango
     * completo; por eso no lleva columna "total" adicional.
     */
    public function getEstadoSituacionFinancieraPorPeriodos(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $periodos = $this->construirPeriodos($fechaInicio, $fechaFin);
        $claves = array_keys($periodos);
        $ultimaClave = end($claves);

        $catalogo = $this->indexarPorId($this->repository->getPlanCuentas($idEmpresa));
        $movMensual = $this->indexarMovimientos($this->repository->getSaldosPorPeriodo($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto));
        $movAcumulado = $this->acumularMovimientos($movMensual, $claves);

        $balance = $this->calcularClasesPorPeriodo($catalogo, $movAcumulado, $claves, ['1' => 'D', '2' => 'C', '3' => 'C'], $nivelReporte);
        $resultado = $this->calcularClasesPorPeriodo($catalogo, $movAcumulado, $claves, ['4' => 'C', '5' => 'D', '6' => 'D'], 5);

        $utilidadNetaPorPeriodo = [];
        foreach ($claves as $p) {
            $utilidadNetaPorPeriodo[$p] = $resultado['totales']['4'][$p] - $resultado['totales']['5'][$p] - $resultado['totales']['6'][$p];
        }

        // Cierre virtual: saldo acumulado de la cuenta pivote (clase 7), igual que en el reporte
        // de un solo periodo (ver getEstadoSituacionFinanciera).
        $saldoPivotPorPeriodo = array_fill_keys($claves, 0.0);
        foreach ($catalogo as $idCuenta => $cta) {
            if ((int)$cta['nivel'] === 5 && str_starts_with((string)$cta['codigo'], '7')) {
                foreach ($claves as $p) {
                    $debe = (float)($movAcumulado[$idCuenta][$p]['debe'] ?? 0);
                    $haber = (float)($movAcumulado[$idCuenta][$p]['haber'] ?? 0);
                    $saldoPivotPorPeriodo[$p] += $haber - $debe;
                }
            }
        }

        $resultadoFinalPorPeriodo = [];
        foreach ($claves as $p) {
            $resultadoFinalPorPeriodo[$p] = $utilidadNetaPorPeriodo[$p] + $saldoPivotPorPeriodo[$p];
        }

        $ctasCierre = $this->repository->getCuentasCierreEjercicio($idEmpresa);
        $signoFinal = $resultadoFinalPorPeriodo[$ultimaClave] ?? 0.0;
        if ($signoFinal >= 0) {
            $cta = $ctasCierre['utilidad'] ?? null;
            $lblNeta = 'Utilidad del Ejercicio';
        } else {
            $cta = $ctasCierre['perdida'] ?? null;
            $lblNeta = 'Pérdida del Ejercicio';
        }

        $patrimonio = $balance['grupos']['3'];
        $patrimonio[] = [
            'codigo' => $cta['codigo'] ?? '',
            'nombre' => $cta['nombre'] ?? $lblNeta,
            'nivel' => 1,
            'valores' => $resultadoFinalPorPeriodo,
        ];

        $totalPatrimonioPorPeriodo = $balance['totales']['3'];
        foreach ($claves as $p) {
            $totalPatrimonioPorPeriodo[$p] += $resultadoFinalPorPeriodo[$p];
        }
        $totalPasivoPatrimonioPorPeriodo = [];
        foreach ($claves as $p) {
            $totalPasivoPatrimonioPorPeriodo[$p] = $balance['totales']['2'][$p] + $totalPatrimonioPorPeriodo[$p];
        }

        // Ocultar columnas de mes sin ningún movimiento propio. El saldo acumulado de un mes sin
        // movimiento es idéntico al del mes anterior (no aporta información nueva); el criterio
        // usa el movimiento MENSUAL (no el acumulado) para decidir qué columnas mostrar.
        $clavesVisibles = $this->clavesConMovimiento($movMensual, $claves);

        return [
            'periodos' => array_intersect_key($periodos, array_flip($clavesVisibles)),
            'activos' => $this->filtrarValoresItems($balance['grupos']['1'], $clavesVisibles),
            'pasivos' => $this->filtrarValoresItems($balance['grupos']['2'], $clavesVisibles),
            'patrimonio' => $this->filtrarValoresItems($patrimonio, $clavesVisibles),
            'totales' => [
                'activos' => $this->filtrarPorPeriodo($balance['totales']['1'], $clavesVisibles),
                'pasivos' => $this->filtrarPorPeriodo($balance['totales']['2'], $clavesVisibles),
                'patrimonio' => $this->filtrarPorPeriodo($totalPatrimonioPorPeriodo, $clavesVisibles),
                'pasivo_patrimonio' => $this->filtrarPorPeriodo($totalPasivoPatrimonioPorPeriodo, $clavesVisibles),
            ],
        ];
    }

    /**
     * Claves de periodo (YYYY-MM => "Mes Año") entre fecha_inicio y fecha_fin, un mes por
     * columna. Limitado a MAX_PERIODOS para no generar tablas horizontales inmanejables.
     */
    private function construirPeriodos(string $fechaInicio, string $fechaFin): array
    {
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $cursor = new \DateTime(date('Y-m-01', strtotime($fechaInicio)));
        $fin = new \DateTime(date('Y-m-01', strtotime($fechaFin)));

        $periodos = [];
        while ($cursor <= $fin) {
            if (count($periodos) >= self::MAX_PERIODOS) {
                throw new Exception('El rango de fechas es demasiado amplio para el reporte por periodos (máximo ' . self::MAX_PERIODOS . ' meses).');
            }
            $clave = $cursor->format('Y-m');
            $periodos[$clave] = $meses[(int)$cursor->format('n') - 1] . ' ' . $cursor->format('Y');
            $cursor->modify('+1 month');
        }

        return $periodos;
    }

    private function indexarPorId(array $filas): array
    {
        $out = [];
        foreach ($filas as $f) {
            $out[(int)$f['id_cuenta']] = $f;
        }
        return $out;
    }

    /**
     * [id_cuenta][periodo] => ['debe' => float, 'haber' => float]. Solo incluye cuentas/periodos
     * con movimiento; el llamador debe usar ?? 0 para completar el resto de la matriz.
     */
    private function indexarMovimientos(array $filas): array
    {
        $out = [];
        foreach ($filas as $f) {
            if ($f['periodo'] === null) {
                continue;
            }
            $out[(int)$f['id_cuenta']][$f['periodo']] = [
                'debe' => (float)$f['total_debe'],
                'haber' => (float)$f['total_haber'],
            ];
        }
        return $out;
    }

    /**
     * Convierte movimientos mensuales en movimientos acumulados (running total) mes a mes,
     * para el Estado de Situación Financiera por periodos (un balance es acumulado, no un flujo).
     */
    private function acumularMovimientos(array $movPorPeriodo, array $claves): array
    {
        $acumulado = [];
        foreach ($movPorPeriodo as $idCuenta => $porPeriodo) {
            $runningDebe = 0.0;
            $runningHaber = 0.0;
            foreach ($claves as $p) {
                $runningDebe += (float)($porPeriodo[$p]['debe'] ?? 0);
                $runningHaber += (float)($porPeriodo[$p]['haber'] ?? 0);
                $acumulado[$idCuenta][$p] = ['debe' => $runningDebe, 'haber' => $runningHaber];
            }
        }
        return $acumulado;
    }

    /**
     * Calcula, para un conjunto de clases contables (prefijo de código => signo D/C), el saldo
     * directo y el rollup jerárquico (flat: padre + todos los descendientes por prefijo de
     * código, igual que getEstadoResultados/getEstadoSituacionFinanciera) por cada periodo.
     * Devuelve ['grupos' => [prefijo => [items filtrados por nivel y no-cero]], 'totales' =>
     * [prefijo => [periodo => valor]]] — los totales suman TODAS las cuentas (sin filtrar por
     * nivel), igual que en el reporte de un solo periodo.
     */
    private function calcularClasesPorPeriodo(array $catalogo, array $movPorPeriodo, array $claves, array $prefijosSigno, int $nivelReporte): array
    {
        $directo = [];
        $totales = [];
        foreach (array_keys($prefijosSigno) as $prefijo) {
            $totales[$prefijo] = array_fill_keys($claves, 0.0);
        }

        foreach ($catalogo as $idCuenta => $cta) {
            $codigo = (string)$cta['codigo'];
            $prefijoCuenta = $codigo !== '' ? $codigo[0] : '';
            if (!isset($prefijosSigno[$prefijoCuenta])) {
                continue;
            }
            $signo = $prefijosSigno[$prefijoCuenta];
            foreach ($claves as $p) {
                $debe = (float)($movPorPeriodo[$idCuenta][$p]['debe'] ?? 0);
                $haber = (float)($movPorPeriodo[$idCuenta][$p]['haber'] ?? 0);
                $valor = $signo === 'D' ? ($debe - $haber) : ($haber - $debe);
                $directo[$idCuenta][$p] = $valor;
                $totales[$prefijoCuenta][$p] += $valor;
            }
        }

        $final = [];
        foreach ($catalogo as $idCuenta => $cta) {
            $codigo = (string)$cta['codigo'];
            $prefijoCuenta = $codigo !== '' ? $codigo[0] : '';
            if (!isset($prefijosSigno[$prefijoCuenta])) {
                continue;
            }
            $prefijoHijos = $codigo . '.';
            foreach ($claves as $p) {
                $suma = $directo[$idCuenta][$p] ?? 0.0;
                foreach ($catalogo as $idHijo => $ctaHijo) {
                    if (str_starts_with((string)$ctaHijo['codigo'], $prefijoHijos)) {
                        $suma += $directo[$idHijo][$p] ?? 0.0;
                    }
                }
                $final[$idCuenta][$p] = $suma;
            }
        }

        $grupos = [];
        foreach (array_keys($prefijosSigno) as $prefijo) {
            $grupos[$prefijo] = [];
        }

        foreach ($catalogo as $idCuenta => $cta) {
            $codigo = (string)$cta['codigo'];
            $prefijoCuenta = $codigo !== '' ? $codigo[0] : '';
            if (!isset($prefijosSigno[$prefijoCuenta])) {
                continue;
            }
            if ((int)$cta['nivel'] > $nivelReporte) {
                continue;
            }

            $valores = $final[$idCuenta];
            $tieneValor = false;
            foreach ($valores as $v) {
                if (round($v, 2) != 0) {
                    $tieneValor = true;
                    break;
                }
            }
            if (!$tieneValor) {
                continue;
            }

            $grupos[$prefijoCuenta][] = [
                'codigo' => $cta['codigo'],
                'nombre' => $cta['nombre'],
                'nivel' => $cta['nivel'],
                'codigo_sri' => $cta['codigo_sri'] ?? null,
                'valores' => $valores,
            ];
        }

        return ['grupos' => $grupos, 'totales' => $totales];
    }

    /**
     * Claves de periodo (subconjunto de $claves) donde al menos una cuenta tuvo movimiento
     * (debe o haber distinto de cero) ese mes en $movMensual. Se usa para ocultar columnas de
     * mes sin ningún dato en los reportes "por periodos".
     */
    private function clavesConMovimiento(array $movMensual, array $claves): array
    {
        $conMovimiento = [];
        foreach ($claves as $p) {
            $tieneMovimiento = false;
            foreach ($movMensual as $porPeriodo) {
                $v = $porPeriodo[$p] ?? null;
                if ($v !== null && (round((float)$v['debe'], 2) != 0 || round((float)$v['haber'], 2) != 0)) {
                    $tieneMovimiento = true;
                    break;
                }
            }
            if ($tieneMovimiento) {
                $conMovimiento[] = $p;
            }
        }
        return $conMovimiento;
    }

    /**
     * Recorta el array 'valores' de cada item a solo las claves de periodo visibles, sin tocar
     * el resto del item (codigo, nombre, nivel, total…).
     */
    private function filtrarValoresItems(array $items, array $clavesVisibles): array
    {
        $keep = array_flip($clavesVisibles);
        foreach ($items as &$item) {
            $item['valores'] = array_intersect_key($item['valores'], $keep);
        }
        unset($item);
        return $items;
    }

    /**
     * Recorta un array periodo => valor a solo las claves visibles, preservando la clave
     * 'total' si existe (no es un periodo, es la suma agregada).
     */
    private function filtrarPorPeriodo(array $porPeriodo, array $clavesVisibles): array
    {
        $keep = $clavesVisibles;
        if (array_key_exists('total', $porPeriodo)) {
            $keep[] = 'total';
        }
        return array_intersect_key($porPeriodo, array_flip($keep));
    }

    private function conTotal(array $porPeriodo): array
    {
        $porPeriodo['total'] = array_sum($porPeriodo);
        return $porPeriodo;
    }

    private function conTotalItems(array $items): array
    {
        foreach ($items as &$item) {
            $item['total'] = array_sum($item['valores']);
        }
        unset($item);
        return $items;
    }

    public function exportarSri(string $tipo, array $datos, string $empresaNombre, string $rangoFechas, string $rucEmpresa = ''): void
    {
        $agrupadoSri = [];
        $sectores = $tipo === 'resultados' ? ['ingresos', 'costos', 'gastos'] : ['activos', 'pasivos', 'patrimonio'];
        
        foreach ($sectores as $sec) {
            if (!isset($datos[$sec])) continue;
            foreach ($datos[$sec] as $item) {
                if ((int)$item['nivel'] === 5 && !empty($item['codigo_sri'])) {
                    $sri = $item['codigo_sri'];
                    if (!isset($agrupadoSri[$sri])) {
                        $agrupadoSri[$sri] = 0.0;
                    }
                    $agrupadoSri[$sri] += (float)$item['saldo_final'];
                }
            }
        }

        ksort($agrupadoSri);

        $filename = 'reporte_sri_imp_renta_' . ($rucEmpresa ?: 'sin_ruc') . '.xml';
        
        // Limpiar el búfer de salida
        if (ob_get_length()) ob_end_clean();
        
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
        echo "<detallesDeclaracion>\n";
        
        foreach ($agrupadoSri as $codSri => $valor) {
            echo "<detalle concepto=\"{$codSri}\">" . number_format($valor, 2, '.', '') . "</detalle>\n";
        }
        
        if (!empty($rucEmpresa)) {
            echo "<detalle concepto=\"80\">{$rucEmpresa}</detalle>\n";
        }
        
        echo "</detallesDeclaracion>\n";
        
        exit;
    }

    public function exportarExcel(string $tipo, array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $headers = ['Código', 'Cuenta', 'Saldo'];
        $dataExport = [];

        if ($tipo === 'resultados') {
            $dataExport[] = ['INGRESOS', '', ''];
            foreach ($datos['ingresos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL INGRESOS', $datos['totales']['ingresos']];
            $dataExport[] = ['', '', ''];
            
            $dataExport[] = ['COSTOS', '', ''];
            foreach ($datos['costos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL COSTOS', $datos['totales']['costos']];
            $dataExport[] = ['', '', ''];
            
            $lblBruta = $datos['totales']['utilidad_bruta'] >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA';
            $dataExport[] = ['', $lblBruta, $datos['totales']['utilidad_bruta']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['GASTOS', '', ''];
            foreach ($datos['gastos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL GASTOS', $datos['totales']['gastos']];
            $dataExport[] = ['', '', ''];
            
            $lblNeta = $datos['totales']['utilidad_neta'] >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO';
            $dataExport[] = ['', $lblNeta, $datos['totales']['utilidad_neta']];
            
            $this->reportService->exportToExcel('Estado_Resultados', $headers, $dataExport, 'Estado Resultados', "{$empresaNombre} - Estado de Resultados ({$rangoFechas})");
        } else {
            $dataExport[] = ['ACTIVOS', '', ''];
            foreach ($datos['activos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL ACTIVOS', $datos['totales']['activos']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['PASIVOS', '', ''];
            foreach ($datos['pasivos'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL PASIVOS', $datos['totales']['pasivos']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['PATRIMONIO', '', ''];
            foreach ($datos['patrimonio'] as $item) {
                $dataExport[] = [$item['codigo'], $item['nombre'], $item['saldo_final']];
            }
            $dataExport[] = ['', 'TOTAL PATRIMONIO', $datos['totales']['patrimonio']];
            $dataExport[] = ['', '', ''];

            $dataExport[] = ['', 'TOTAL PASIVO + PATRIMONIO', $datos['totales']['pasivo_patrimonio']];

            $this->reportService->exportToExcel('Estado_Situacion_Financiera', $headers, $dataExport, 'Situacion Financiera', "{$empresaNombre} - Estado de Situación Financiera ({$rangoFechas})");
        }
    }

    /**
     * Excel horizontal por periodos: una columna por mes (más "Total" en el reporte de
     * Resultados; en Situación Financiera el último mes ya es el saldo final).
     */
    public function exportarExcelPorPeriodos(string $tipo, array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $esResultados = $tipo === 'resultados_periodos';
        $labels = array_values($datos['periodos']);
        $headers = array_merge(['Código', 'Cuenta'], $labels, $esResultados ? ['Total'] : []);

        $filaItem = function (array $item) use ($datos, $esResultados) {
            $fila = [$item['codigo'], $item['nombre']];
            foreach (array_keys($datos['periodos']) as $p) {
                $fila[] = $item['valores'][$p] ?? 0;
            }
            if ($esResultados) {
                $fila[] = $item['total'] ?? array_sum($item['valores']);
            }
            return $fila;
        };

        $filaTotal = function (string $titulo, array $porPeriodo) use ($datos, $esResultados) {
            $fila = ['', $titulo];
            foreach (array_keys($datos['periodos']) as $p) {
                $fila[] = $porPeriodo[$p] ?? 0;
            }
            if ($esResultados) {
                $fila[] = $porPeriodo['total'] ?? array_sum(array_intersect_key($porPeriodo, $datos['periodos']));
            }
            return $fila;
        };

        $dataExport = [];

        if ($esResultados) {
            $dataExport[] = array_merge(['INGRESOS'], array_fill(0, count($headers) - 1, ''));
            foreach ($datos['ingresos'] as $item) $dataExport[] = $filaItem($item);
            $dataExport[] = $filaTotal('TOTAL INGRESOS', $datos['totales']['ingresos']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = array_merge(['COSTOS'], array_fill(0, count($headers) - 1, ''));
            foreach ($datos['costos'] as $item) $dataExport[] = $filaItem($item);
            $dataExport[] = $filaTotal('TOTAL COSTOS', $datos['totales']['costos']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = $filaTotal('UTILIDAD/PÉRDIDA BRUTA', $datos['totales']['utilidad_bruta']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = array_merge(['GASTOS'], array_fill(0, count($headers) - 1, ''));
            foreach ($datos['gastos'] as $item) $dataExport[] = $filaItem($item);
            $dataExport[] = $filaTotal('TOTAL GASTOS', $datos['totales']['gastos']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = $filaTotal('UTILIDAD/PÉRDIDA DEL EJERCICIO', $datos['totales']['utilidad_neta']);

            $this->reportService->exportToExcel('Estado_Resultados_Periodos', $headers, $dataExport, 'Resultados x Periodo', "{$empresaNombre} - Estado de Resultados por Periodos ({$rangoFechas})");
        } else {
            $dataExport[] = array_merge(['ACTIVOS'], array_fill(0, count($headers) - 1, ''));
            foreach ($datos['activos'] as $item) $dataExport[] = $filaItem($item);
            $dataExport[] = $filaTotal('TOTAL ACTIVOS', $datos['totales']['activos']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = array_merge(['PASIVOS'], array_fill(0, count($headers) - 1, ''));
            foreach ($datos['pasivos'] as $item) $dataExport[] = $filaItem($item);
            $dataExport[] = $filaTotal('TOTAL PASIVOS', $datos['totales']['pasivos']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = array_merge(['PATRIMONIO'], array_fill(0, count($headers) - 1, ''));
            foreach ($datos['patrimonio'] as $item) $dataExport[] = $filaItem($item);
            $dataExport[] = $filaTotal('TOTAL PATRIMONIO', $datos['totales']['patrimonio']);
            $dataExport[] = array_fill(0, count($headers), '');

            $dataExport[] = $filaTotal('TOTAL PASIVO + PATRIMONIO', $datos['totales']['pasivo_patrimonio']);

            $this->reportService->exportToExcel('Estado_Situacion_Financiera_Periodos', $headers, $dataExport, 'Situación x Periodo', "{$empresaNombre} - Estado de Situación Financiera por Periodos ({$rangoFechas})");
        }
    }

    /**
     * PDF horizontal por periodos. Usa orientación horizontal (Landscape) porque el número de
     * columnas (una por mes) no cabe en A4 vertical.
     */
    public function exportarPdfPorPeriodos(string $tipo, array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $esResultados = $tipo === 'resultados_periodos';
        $labels = array_values($datos['periodos']);
        $claves = array_keys($datos['periodos']);

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $tituloReporte = $esResultados ? 'Estado de Resultados por Periodos' : 'Estado de Situación Financiera por Periodos';
        $pdf->SetTitle($tituloReporte);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, strtoupper($tituloReporte), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, "Período: " . $rangoFechas, 0, 1, 'C');
        $pdf->Ln(5);

        $formatoDinero = function ($val) {
            return number_format((float)$val, 2, '.', ',');
        };

        $numColsPeriodo = count($labels) + ($esResultados ? 1 : 0);
        $anchoCodigo = 8;
        $anchoCuenta = 25;
        $anchoPeriodo = max(6, (int)((100 - $anchoCodigo - $anchoCuenta) / max(1, $numColsPeriodo)));

        $html = '<table border="1" cellpadding="2" style="font-size:7px;">
                    <thead>
                        <tr style="background-color:#f0f0f0; font-weight:bold;">
                            <th width="' . $anchoCodigo . '%">Código</th>
                            <th width="' . $anchoCuenta . '%">Cuenta</th>';
        foreach ($labels as $lbl) {
            $html .= '<th width="' . $anchoPeriodo . '%" align="right">' . htmlspecialchars($lbl) . '</th>';
        }
        if ($esResultados) {
            $html .= '<th width="' . $anchoPeriodo . '%" align="right">Total</th>';
        }
        $html .= '</tr></thead><tbody>';

        $filaItemHtml = function (array $item) use ($claves, $esResultados, $formatoDinero) {
            $fila = "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td>";
            foreach ($claves as $p) {
                $fila .= '<td align="right">' . $formatoDinero($item['valores'][$p] ?? 0) . '</td>';
            }
            if ($esResultados) {
                $fila .= '<td align="right">' . $formatoDinero($item['total'] ?? array_sum($item['valores'])) . '</td>';
            }
            return $fila . '</tr>';
        };

        $filaTotalHtml = function (string $titulo, array $porPeriodo) use ($claves, $esResultados, $formatoDinero) {
            $fila = "<tr><td colspan=\"2\" style=\"font-weight:bold;\">{$titulo}</td>";
            foreach ($claves as $p) {
                $fila .= '<td align="right" style="font-weight:bold;">' . $formatoDinero($porPeriodo[$p] ?? 0) . '</td>';
            }
            if ($esResultados) {
                $fila .= '<td align="right" style="font-weight:bold;">' . $formatoDinero($porPeriodo['total'] ?? array_sum(array_intersect_key($porPeriodo, array_flip($claves)))) . '</td>';
            }
            return $fila . '</tr>';
        };

        if ($esResultados) {
            $html .= '<tr><td colspan="' . (2 + $numColsPeriodo) . '" style="font-weight:bold;">INGRESOS</td></tr>';
            foreach ($datos['ingresos'] as $item) $html .= $filaItemHtml($item);
            $html .= $filaTotalHtml('TOTAL INGRESOS', $datos['totales']['ingresos']);

            $html .= '<tr><td colspan="' . (2 + $numColsPeriodo) . '" style="font-weight:bold;">COSTOS</td></tr>';
            foreach ($datos['costos'] as $item) $html .= $filaItemHtml($item);
            $html .= $filaTotalHtml('TOTAL COSTOS', $datos['totales']['costos']);
            $html .= $filaTotalHtml('UTILIDAD/PÉRDIDA BRUTA', $datos['totales']['utilidad_bruta']);

            $html .= '<tr><td colspan="' . (2 + $numColsPeriodo) . '" style="font-weight:bold;">GASTOS</td></tr>';
            foreach ($datos['gastos'] as $item) $html .= $filaItemHtml($item);
            $html .= $filaTotalHtml('TOTAL GASTOS', $datos['totales']['gastos']);
            $html .= $filaTotalHtml('UTILIDAD/PÉRDIDA DEL EJERCICIO', $datos['totales']['utilidad_neta']);
        } else {
            $html .= '<tr><td colspan="' . (2 + $numColsPeriodo) . '" style="font-weight:bold;">ACTIVOS</td></tr>';
            foreach ($datos['activos'] as $item) $html .= $filaItemHtml($item);
            $html .= $filaTotalHtml('TOTAL ACTIVOS', $datos['totales']['activos']);

            $html .= '<tr><td colspan="' . (2 + $numColsPeriodo) . '" style="font-weight:bold;">PASIVOS</td></tr>';
            foreach ($datos['pasivos'] as $item) $html .= $filaItemHtml($item);
            $html .= $filaTotalHtml('TOTAL PASIVOS', $datos['totales']['pasivos']);

            $html .= '<tr><td colspan="' . (2 + $numColsPeriodo) . '" style="font-weight:bold;">PATRIMONIO</td></tr>';
            foreach ($datos['patrimonio'] as $item) $html .= $filaItemHtml($item);
            $html .= $filaTotalHtml('TOTAL PATRIMONIO', $datos['totales']['patrimonio']);
            $html .= $filaTotalHtml('TOTAL PASIVO + PATRIMONIO', $datos['totales']['pasivo_patrimonio']);
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $filename = "{$tituloReporte}_" . date('YmdHis') . ".pdf";
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }

    public function exportarPdf(string $tipo, array $datos, string $empresaNombre, string $rangoFechas): void
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $tituloReporte = $tipo === 'resultados' ? 'Estado de Resultados' : 'Estado de Situación Financiera';
        $pdf->SetTitle($tituloReporte);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, strtoupper($tituloReporte), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, "Período: " . $rangoFechas, 0, 1, 'C');
        $pdf->Ln(5);

        $html = '<table border="1" cellpadding="4">
                    <thead>
                        <tr style="background-color:#f0f0f0; font-weight:bold;">
                            <th width="20%">Código</th>
                            <th width="60%">Cuenta</th>
                            <th width="20%" align="right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>';

        $formatoDinero = function($val) {
            return number_format((float)$val, 2, '.', ',');
        };

        if ($tipo === 'resultados') {
            $html .= '<tr><td colspan="3" style="font-weight:bold;">INGRESOS</td></tr>';
            foreach ($datos['ingresos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL INGRESOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['ingresos'])}</td></tr>";
            
            $html .= '<tr><td colspan="3" style="font-weight:bold;">COSTOS</td></tr>';
            foreach ($datos['costos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL COSTOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['costos'])}</td></tr>";
            
            $lblBruta = $datos['totales']['utilidad_bruta'] >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA';
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">{$lblBruta}</td><td align=\"right\" style=\"font-weight:bold; color: ".($datos['totales']['utilidad_bruta'] < 0 ? 'red' : 'black').";\">{$formatoDinero($datos['totales']['utilidad_bruta'])}</td></tr>";

            $html .= '<tr><td colspan="3" style="font-weight:bold;">GASTOS</td></tr>';
            foreach ($datos['gastos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL GASTOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['gastos'])}</td></tr>";
            
            $lblNeta = $datos['totales']['utilidad_neta'] >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO';
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">{$lblNeta}</td><td align=\"right\" style=\"font-weight:bold; color: ".($datos['totales']['utilidad_neta'] < 0 ? 'red' : 'black').";\">{$formatoDinero($datos['totales']['utilidad_neta'])}</td></tr>";
        } else {
            $html .= '<tr><td colspan="3" style="font-weight:bold;">ACTIVOS</td></tr>';
            foreach ($datos['activos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL ACTIVOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['activos'])}</td></tr>";
            
            $html .= '<tr><td colspan="3" style="font-weight:bold;">PASIVOS</td></tr>';
            foreach ($datos['pasivos'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL PASIVOS</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['pasivos'])}</td></tr>";
            
            $html .= '<tr><td colspan="3" style="font-weight:bold;">PATRIMONIO</td></tr>';
            foreach ($datos['patrimonio'] as $item) {
                $html .= "<tr><td>{$item['codigo']}</td><td>{$item['nombre']}</td><td align=\"right\">{$formatoDinero($item['saldo_final'])}</td></tr>";
            }
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL PATRIMONIO</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['patrimonio'])}</td></tr>";
            
            $html .= "<tr><td colspan=\"2\" align=\"right\" style=\"font-weight:bold;\">TOTAL PASIVO + PATRIMONIO</td><td align=\"right\" style=\"font-weight:bold;\">{$formatoDinero($datos['totales']['pasivo_patrimonio'])}</td></tr>";
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $filename = "{$tituloReporte}_".date('YmdHis').".pdf";
        // Limpiar el output buffer
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($filename, 'D');
        exit;
    }

    public function generarMayorAuxiliar(
        int $idEmpresa,
        string $codigoCuenta,
        string $fechaInicio,
        string $fechaFin,
        ?int $idCentroCosto = null,
        ?int $idProyecto = null
    ): array {
        $movimientos = $this->repository->getMayorAuxiliar($idEmpresa, $codigoCuenta, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        $saldoArrastrado = 0.0;

        foreach ($movimientos as &$mov) {
            $debe = (float)$mov['debe'];
            $haber = (float)$mov['haber'];
            $cod = (string)$mov['codigo_cuenta'];
            $prefijo = $cod !== '' ? $cod[0] : '1';

            // 1=Activo (Deudora), 2=Pasivo (Acreedora), 3=Patrimonio (Acreedora), 4=Ingresos (Acreedora), 5=Costos (Deudora), 6=Gastos (Deudora)
            $naturaleza = in_array($prefijo, ['1', '5', '6']) ? 'deudora' : 'acreedora';

            if ($naturaleza === 'deudora') {
                $saldoArrastrado += ($debe - $haber);
            } else {
                $saldoArrastrado += ($haber - $debe);
            }

            $mov['saldo_acumulado'] = $saldoArrastrado;
        }

        return $movimientos;
    }
}
