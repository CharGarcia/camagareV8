<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\Services\ReportService;
use Exception;

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
     * Utilidad/Pérdida del Ejercicio de una empresa (cuentas 4/5/6 + pivote de migrados clase 7).
     * Extraído de getEstadoSituacionFinanciera() para reutilizarlo también en el Total General
     * Consolidado (getConsolidadoRuc()), sin duplicar la lógica del cierre virtual.
     */
    private function calcularResultadoEjercicio(int $idEmpresa, array $saldos, string $fechaInicio, string $fechaFin, ?int $idCentroCosto, ?int $idProyecto): float
    {
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
        return $utilidadNeta + $saldoPivot;
    }

    /**
     * Vista "Consolidado por RUC": arma un resumen con los conceptos ya mapeados en Balances
     * Consolidados (sumados entre establecimientos del RUC accesible al usuario, salvo los grupos
     * en modo "cuenta única" — ver abajo), el Total General Consolidado (un solo Estado de
     * Situación Financiera / Resultados para todo el RUC, sin duplicar nada) y, por separado, el
     * reporte COMPLETO de cada establecimiento (sin modificar ninguna de las dos funciones
     * existentes ni su jerarquía, para no arriesgar el cuadre de cada reporte individual).
     * Si el RUC solo tiene un establecimiento accesible, 'aplica' viene en false.
     *
     * Modo de un grupo consolidado (consolidacion_grupos.modo_consolidacion):
     * - SUMA (default): el concepto existe de forma independiente en cada establecimiento
     *   (caja, cuentas por cobrar, etc.) — se suman los valores de todos los establecimientos
     *   mapeados.
     * - UNICA: el concepto es el mismo registro para todo el RUC (capital, reservas de
     *   patrimonio) — solo cuenta el valor del establecimiento marcado como id_empresa_fuente;
     *   los demás establecimientos mapeados se muestran en 'detalle' solo como referencia
     *   (incluido=false), sin sumarse, para no inflar el total con el mismo capital repetido.
     */
    public function getConsolidadoRuc(int $idEmpresa, int $idUsuario, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivelReporte = 5): array
    {
        $idsGrupo = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        if (count($idsGrupo) <= 1) {
            return ['aplica' => false, 'consolidado' => [], 'por_establecimiento' => [], 'total_general' => null];
        }

        $mapa = $this->consolidacionSvc->getMapaCuentaGrupo($idEmpresa);
        $etiquetas = $this->empresaRepo->getEtiquetasEstablecimiento($idsGrupo);

        $gruposAcum = [];
        $porEstablecimiento = [];
        $resultadoAcumuladoRuc = 0.0;

        // Total General Consolidado: solo trabaja con cuentas de MOVIMIENTO (nivel 5) — son
        // atómicas, no hay ambigüedad de rollup entre planes de cuentas distintos.
        $tiposTG = ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'COSTO', 'GASTO'];
        $lineasTG = array_fill_keys($tiposTG, []);
        $totalesTG = array_fill_keys($tiposTG, 0.0);

        foreach ($idsGrupo as $idEmp) {
            $saldos = $this->repository->getSaldos($idEmp, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
            foreach ($saldos as $s) {
                $idCuenta = (int) $s['id_cuenta'];

                if (isset($mapa[$idCuenta])) {
                    $g = $mapa[$idCuenta];
                    if (!isset($gruposAcum[$g['id_grupo']])) {
                        $gruposAcum[$g['id_grupo']] = [
                            'nombre' => $g['nombre'], 'tipo' => $g['tipo'], 'orden' => $g['orden'],
                            'modo' => $g['modo'], 'saldo' => 0.0, 'detalle' => [],
                        ];
                    }
                    $valor = $this->signoSaldoConsolidado($g['tipo'], (float) $s['total_debe'], (float) $s['total_haber']);
                    $incluido = $g['modo'] !== 'UNICA' || (int) $idEmp === (int) $g['id_empresa_fuente'];
                    if ($incluido) {
                        $gruposAcum[$g['id_grupo']]['saldo'] += $valor;
                    }
                    $gruposAcum[$g['id_grupo']]['detalle'][] = [
                        'establecimiento' => $etiquetas[$idEmp] ?? ('Empresa ' . $idEmp),
                        'codigo' => $s['codigo'], 'nombre' => $s['nombre'], 'valor' => $valor, 'incluido' => $incluido,
                    ];
                    continue; // ya representada en el grupo — no se duplica como línea suelta del Total General.
                }

                // Cuenta no mapeada a ningún grupo: entra al Total General como línea propia de
                // este establecimiento (nivel 5 únicamente; clase 7 = pivote de migrados, se
                // refleja aparte en la Utilidad/Pérdida del Ejercicio consolidada, más abajo).
                if ((int) $s['nivel'] !== 5) {
                    continue;
                }
                $codigoStr = (string) $s['codigo'];
                $tipoInferido = match (true) {
                    str_starts_with($codigoStr, '1') => 'ACTIVO',
                    str_starts_with($codigoStr, '2') => 'PASIVO',
                    str_starts_with($codigoStr, '3') => 'PATRIMONIO',
                    str_starts_with($codigoStr, '4') => 'INGRESO',
                    str_starts_with($codigoStr, '5') => 'COSTO',
                    str_starts_with($codigoStr, '6') => 'GASTO',
                    default => null,
                };
                if ($tipoInferido === null) {
                    continue;
                }
                $valor = $this->signoSaldoConsolidado($tipoInferido, (float) $s['total_debe'], (float) $s['total_haber']);
                if (round($valor, 2) === 0.0) {
                    continue;
                }
                $lineasTG[$tipoInferido][] = [
                    'origen' => 'establecimiento',
                    'establecimiento' => $etiquetas[$idEmp] ?? ('Empresa ' . $idEmp),
                    'codigo' => $s['codigo'], 'nombre' => $s['nombre'], 'valor' => $valor,
                ];
                $totalesTG[$tipoInferido] += $valor;
            }

            $resultadoAcumuladoRuc += $this->calcularResultadoEjercicio($idEmp, $saldos, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

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

        // Los grupos consolidados también entran al Total General, con su valor ya resuelto
        // (SUMA o ÚNICA) — antepuestos a las líneas sueltas por establecimiento de ese mismo tipo.
        foreach ($consolidado as $g) {
            $lineasTG[$g['tipo']] = array_merge(
                [['origen' => 'grupo', 'establecimiento' => null, 'codigo' => null, 'nombre' => $g['nombre'], 'valor' => $g['saldo']]],
                $lineasTG[$g['tipo']]
            );
            $totalesTG[$g['tipo']] += $g['saldo'];
        }

        // Utilidad/Pérdida del Ejercicio consolidada: suma de cada establecimiento (a diferencia
        // del capital, el resultado del período SÍ es propio y aditivo por establecimiento — cada
        // uno refleja su propia operación real, sumarlos da el resultado real de todo el RUC).
        $lblResultado = $resultadoAcumuladoRuc >= 0 ? 'Utilidad del Ejercicio (consolidado)' : 'Pérdida del Ejercicio (consolidado)';
        $lineasTG['PATRIMONIO'][] = ['origen' => 'resultado', 'establecimiento' => null, 'codigo' => null, 'nombre' => $lblResultado, 'valor' => $resultadoAcumuladoRuc];
        $totalesTG['PATRIMONIO'] += $resultadoAcumuladoRuc;

        $totalGeneral = [
            'activo' => $lineasTG['ACTIVO'], 'pasivo' => $lineasTG['PASIVO'], 'patrimonio' => $lineasTG['PATRIMONIO'],
            'ingreso' => $lineasTG['INGRESO'], 'costo' => $lineasTG['COSTO'], 'gasto' => $lineasTG['GASTO'],
            'totales' => [
                'activo' => $totalesTG['ACTIVO'], 'pasivo' => $totalesTG['PASIVO'], 'patrimonio' => $totalesTG['PATRIMONIO'],
                'pasivo_patrimonio' => $totalesTG['PASIVO'] + $totalesTG['PATRIMONIO'],
                'ingreso' => $totalesTG['INGRESO'], 'costo' => $totalesTG['COSTO'], 'gasto' => $totalesTG['GASTO'],
                'utilidad_bruta' => $totalesTG['INGRESO'] - $totalesTG['COSTO'],
                'utilidad_neta' => $totalesTG['INGRESO'] - $totalesTG['COSTO'] - $totalesTG['GASTO'],
            ],
        ];

        return ['aplica' => true, 'consolidado' => $consolidado, 'por_establecimiento' => $porEstablecimiento, 'total_general' => $totalGeneral];
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

        // Obtener la Utilidad/Pérdida del Ejercicio (cuentas 4/5/6 + pivote de migrados clase 7)
        $resultado = $this->calcularResultadoEjercicio($idEmpresa, $saldos, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

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
                'id_cuenta' => (int) ($cta['id_cuenta'] ?? 0),
                'codigo' => $cta['codigo'],
                'nombre' => $cta['nombre'],
                'nivel' => $cta['nivel'],
                'codigo_sri' => $cta['codigo_sri'] ?? null,
                'supercias_esf' => $cta['supercias_esf'] ?? null,
                'supercias_eri' => $cta['supercias_eri'] ?? null,
                'supercias_ecp_codigo' => $cta['supercias_ecp_codigo'] ?? null,
                'supercias_ecp_subcodigo' => $cta['supercias_ecp_subcodigo'] ?? null,
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

    /**
     * TXT de Supercías (ESF / ERI / ECP / EFE) con los MISMOS valores que el usuario ve en pantalla:
     * parte del Estado de Resultados y del Estado de Situación Financiera calculados con los
     * filtros de la pantalla (fechas, centro de costo, proyecto; solo asientos contabilizados del
     * ambiente activo), agrupa las cuentas de nivel 5 por su casillero Supercías y resuelve las
     * fórmulas de la estructura con SuperciasEvaluatorService.
     *
     * Formato de línea: codigo<TAB>valor (ECP: codigo<TAB>subcodigo<TAB>valor), 2 decimales, CRLF.
     */
    public function exportarSupercias(string $superciasTipo, int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): void
    {
        $superciasTipo = strtoupper($superciasTipo);
        if (!in_array($superciasTipo, ['ESF', 'ERI', 'ECP', 'EFE'], true)) {
            throw new Exception('Tipo Supercías no válido.');
        }

        $casilleros = $this->evaluarSupercias($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto)['casilleros'][$superciasTipo] ?? [];

        $filename = 'SUPERCIAS_' . $superciasTipo . '_' . str_replace('-', '', $fechaInicio) . '_' . str_replace('-', '', $fechaFin) . '.txt';
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        foreach ($casilleros as $key => $casillero) {
            $valorTxt = number_format((float) $casillero['valor'], 2, '.', '');
            if ($superciasTipo === 'ECP') {
                $partes = explode('.', (string) $key);
                $codigo = $partes[0];
                $subcodigo = $partes[1] ?? '';
                fwrite($out, $subcodigo !== ''
                    ? $codigo . "\t" . $subcodigo . "\t" . $valorTxt . "\r\n"
                    : $codigo . "\t" . $valorTxt . "\r\n");
            } else {
                fwrite($out, $key . "\t" . $valorTxt . "\r\n");
            }
        }
        fclose($out);
        exit;
    }

    /**
     * Evalúa TODOS los casilleros Supercías (ESF, ERI, ECP, EFE) con los valores del reporte en
     * pantalla. Devuelve ['casilleros' => resultado del evaluador por tipo, 'ecp' => detalle del
     * cálculo del ECP (ver calcularEcp)]. Lo usan la exportación TXT y la vista previa del ECP.
     */
    private function evaluarSupercias(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto, ?int $idProyecto): array
    {
        // Nivel 5: se necesitan las cuentas de movimiento (el nivel de pantalla solo agrupa la vista).
        $resultados = $this->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, 5);
        $situacion  = $this->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, 5);

        // ESF / ERI: cada cuenta de nivel 5 aporta su saldo al casillero que tiene mapeado.
        $valoresBase = ['ESF' => [], 'ERI' => [], 'ECP' => [], 'EFE' => []];
        $acumular = function (array $item) use (&$valoresBase): void {
            $valor = (float) ($item['saldo_final'] ?? 0);
            if (!empty($item['supercias_esf'])) {
                $cas = (string) $item['supercias_esf'];
                $valoresBase['ESF'][$cas] = ($valoresBase['ESF'][$cas] ?? 0.0) + $valor;
            }
            if (!empty($item['supercias_eri'])) {
                $cas = (string) $item['supercias_eri'];
                $valoresBase['ERI'][$cas] = ($valoresBase['ERI'][$cas] ?? 0.0) + $valor;
            }
        };

        foreach (['ingresos', 'costos', 'gastos'] as $sec) {
            foreach ($resultados[$sec] ?? [] as $item) {
                if ((int) ($item['nivel'] ?? 0) === 5) $acumular($item);
            }
        }
        foreach (['activos', 'pasivos', 'patrimonio'] as $sec) {
            foreach ($situacion[$sec] ?? [] as $item) {
                if ((int) ($item['nivel'] ?? 0) === 5) $acumular($item);
            }
        }

        // La fila "Utilidad / Pérdida del Ejercicio" del balance no es una cuenta con movimiento
        // (getEstadoSituacionFinanciera la agrega sintética, sin id_cuenta): se suma al casillero
        // ESF que tenga mapeado la cuenta de cierre configurada, la misma que se muestra en pantalla.
        $resultadoEjercicio = 0.0;
        foreach ($situacion['patrimonio'] ?? [] as $p) {
            if (!isset($p['id_cuenta'])) {
                $resultadoEjercicio = (float) ($p['saldo_final'] ?? 0);
                break;
            }
        }
        $ctasCierre = $this->repository->getCuentasCierreEjercicio($idEmpresa);
        $ctaCierre = $resultadoEjercicio >= 0 ? ($ctasCierre['utilidad'] ?? null) : ($ctasCierre['perdida'] ?? null);
        if ($ctaCierre && !empty($ctaCierre['id']) && round($resultadoEjercicio, 2) != 0) {
            $catalogo = $this->indexarPorId($this->repository->getPlanCuentas($idEmpresa));
            $ctaMapeo = $catalogo[(int) $ctaCierre['id']] ?? null;
            if ($ctaMapeo) {
                $acumular(array_merge($ctaMapeo, ['saldo_final' => $resultadoEjercicio]));
            }
        }

        // ECP: matriz fila × columna calculada a partir de saldo inicial, movimientos y resultado.
        $ecp = $this->calcularEcp($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $resultadoEjercicio);
        $valoresBase['ECP'] = $ecp['valores_base'];

        // Pasada 1: ESF / ERI / ECP evaluados (con fórmulas). El EFE necesita casilleros del ERI
        // (utilidad antes de impuestos, depreciación, participación, impuesto) y el ESF 10101.
        $evaluador = new \App\Services\SuperciasEvaluatorService(\App\core\Database::getConnection());
        $pasada1 = $evaluador->evaluarConValoresBase($valoresBase);

        // EFE: método directo desde los asientos de efectivo + conciliación desde ESF/ERI.
        $efe = $this->calcularEfe($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $pasada1);
        $valoresBase['EFE'] = $efe['valores_base'];

        // Pasada 2: todo junto (las fórmulas que el usuario ponga en EFE mandan sobre lo calculado).
        return [
            'casilleros' => $evaluador->evaluarConValoresBase($valoresBase),
            'ecp'        => $ecp,
            'efe'        => $efe,
        ];
    }

    /**
     * Estado de Flujos de Efectivo (Supercías). Devuelve ['valores_base' => [casillero => valor],
     * 'asientos' => detalle por asiento de efectivo, 'controles' => cuadres, 'sin_efectivo' => bool].
     *
     * Método directo (95xx): por cada asiento contabilizado del rango (sin apertura) que toca una
     * cuenta de efectivo (ESF 10101xx), el efecto en efectivo de cada contrapartida es
     * −(debe − haber) de esa línea; las líneas accesorias (IVA, retenciones) se suman a la
     * contrapartida principal; cada contrapartida se clasifica con SuperciasEfe::clasificar().
     * Entradas positivas, salidas negativas. Las transferencias entre cuentas de efectivo no
     * generan flujo.
     *
     * Conciliación (96-9820): 96 = ERI 600 (ganancia antes de participación e impuesto);
     * 9701 = depreciaciones y amortizaciones del ERI; 9709 = impuesto a la renta (ERI 603);
     * 9710 = participación trabajadores (ERI 601); 98xx = −(variación del año) de las cuentas de
     * capital de trabajo según su ESF (SuperciasEfe::casilleroCambio()).
     *
     * Totales: 9501/9502/9503/9505, 9506 = efectivo en la apertura, 9507 = 9506 + 9505,
     * 97, 98 y 9820 = 96 + 97 + 98.
     */
    public function calcularEfe(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto, ?int $idProyecto, array $casillerosEvaluados): array
    {
        $base = [];
        $add = function (string $cas, float $v) use (&$base): void {
            $base[$cas] = ($base[$cas] ?? 0.0) + $v;
        };

        // ── Método directo ────────────────────────────────────────────────────────────────────
        $lineas = $this->repository->getLineasAsientosConEfectivo($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $porAsiento = [];
        foreach ($lineas as $l) {
            $porAsiento[(int) $l['id_asiento']][] = $l;
        }

        $asientos = [];
        $totalEfectivoDirecto = 0.0;
        foreach ($porAsiento as $idAsiento => $ls) {
            $cab = $ls[0];
            $efectivo = 0.0;
            $principales = [];
            $accesorias = [];
            foreach ($ls as $l) {
                $esf = trim((string) ($l['supercias_esf'] ?? ''));
                $neto = (float) $l['debe'] - (float) $l['haber'];
                if (\App\Helpers\SuperciasEfe::esEfectivo($esf)) {
                    $efectivo += $neto;
                } elseif (\App\Helpers\SuperciasEfe::esAccesoria($esf)) {
                    $accesorias[] = $l;
                } else {
                    $principales[] = $l;
                }
            }
            if (round($efectivo, 2) == 0) {
                continue; // transferencia entre cuentas de efectivo o asiento sin efecto neto
            }
            if (empty($principales)) {
                $principales = $accesorias; // solo había IVA/retenciones: se clasifican solas
                $accesorias = [];
            }

            // Efecto en efectivo de cada contrapartida, agrupado por cuenta
            $porCuenta = [];
            foreach ($principales as $l) {
                $id = (int) $l['id_cuenta'];
                if (!isset($porCuenta[$id])) {
                    $porCuenta[$id] = ['codigo' => $l['codigo'], 'nombre' => $l['nombre'], 'esf' => $l['supercias_esf'], 'eri' => $l['supercias_eri'], 'valor' => 0.0];
                }
                $porCuenta[$id]['valor'] += -((float) $l['debe'] - (float) $l['haber']);
            }
            // Accesorias → a la contrapartida principal de mayor importe
            if (!empty($accesorias)) {
                $idMax = null; $max = -1.0;
                foreach ($porCuenta as $id => $c) {
                    if (abs($c['valor']) > $max) { $max = abs($c['valor']); $idMax = $id; }
                }
                foreach ($accesorias as $l) {
                    $porCuenta[$idMax]['valor'] += -((float) $l['debe'] - (float) $l['haber']);
                }
            }

            $asignaciones = [];
            foreach ($porCuenta as $c) {
                $v = round($c['valor'], 2);
                if ($v == 0) continue;
                $cl = \App\Helpers\SuperciasEfe::clasificar($c['esf'], $c['eri'], $v > 0, $cab['modulo_origen'] ?? null);
                $add($cl['casillero'], $v);
                $totalEfectivoDirecto += $v;
                $asignaciones[] = [
                    'codigo'    => $c['codigo'],
                    'nombre'    => $c['nombre'],
                    'casillero' => $cl['casillero'],
                    'regla'     => $cl['regla'],
                    'valor'     => $v,
                    'otros'     => in_array($cl['casillero'], \App\Helpers\SuperciasEfe::OTROS, true),
                ];
            }
            $asientos[] = [
                'id_asiento'   => $idAsiento,
                'fecha'        => substr((string) $cab['fecha_asiento'], 0, 10),
                'origen'       => (string) ($cab['modulo_origen'] ?? ''),
                'concepto'     => (string) ($cab['concepto'] ?? ''),
                'efectivo'     => round($efectivo, 2),
                'asignaciones' => $asignaciones,
            ];
        }

        // Totales del método directo. Closure con referencia: $base sigue creciendo después de definirla
        // (una función flecha capturaría una copia y los totales 97/98 saldrían en cero).
        $suma = function (array $cods) use (&$base): float {
            $s = 0.0;
            foreach ($cods as $c) $s += $base[$c] ?? 0.0;
            return $s;
        };
        $base['950101'] = $suma(\App\Helpers\SuperciasEfe::OPERACION_COBROS);
        $base['950102'] = $suma(\App\Helpers\SuperciasEfe::OPERACION_PAGOS);
        $base['9501']   = $base['950101'] + $base['950102'] + $suma(\App\Helpers\SuperciasEfe::OPERACION_OTROS);
        $base['9502']   = $suma(\App\Helpers\SuperciasEfe::INVERSION);
        $base['9503']   = $suma(\App\Helpers\SuperciasEfe::FINANCIACION);
        $base['9504']   = $base['950401'] ?? 0.0;
        $base['9505']   = $base['9501'] + $base['9502'] + $base['9503'] + $base['9504'];
        $base['95']     = $base['9501'] + $base['9502'] + $base['9503'];

        // ── Efectivo inicial / final y conciliación ──────────────────────────────────────────
        $cuentas = $this->repository->getAperturaYMovimientoPorCuenta($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $efectivoApertura = 0.0;
        $efectivoMovimiento = 0.0;
        $hayCuentasEfectivo = false;
        foreach ($cuentas as $c) {
            if ((int) $c['nivel'] !== 5) continue;
            $esf = trim((string) ($c['supercias_esf'] ?? ''));
            if (\App\Helpers\SuperciasEfe::esEfectivo($esf)) {
                $hayCuentasEfectivo = true;
                $efectivoApertura   += (float) $c['apertura'];
                $efectivoMovimiento += (float) $c['movimiento'];
                continue;
            }
            $cas = \App\Helpers\SuperciasEfe::casilleroCambio($esf);
            if ($cas !== null && round((float) $c['movimiento'], 2) != 0) {
                $add($cas, -(float) $c['movimiento']); // aumento de activo resta efectivo; aumento de pasivo lo suma
            }
        }
        $base['9506'] = round($efectivoApertura, 2);
        $base['9507'] = $base['9506'] + $base['9505'];

        $eri = fn(string $cod) => (float) ($casillerosEvaluados['ERI'][$cod]['valor'] ?? 0);
        $base['96']   = $eri('600');
        $base['9701'] = $eri('5010401') + $eri('5020120') + $eri('5020121') + $eri('5020221') + $eri('5020222');
        $base['9709'] = $eri('603') + $eri('5020126') + $eri('5020227');
        $base['9710'] = $eri('601');
        $base['97']   = $suma(\App\Helpers\SuperciasEfe::AJUSTES);
        $base['98']   = $suma(\App\Helpers\SuperciasEfe::CAMBIOS);
        $base['9820'] = $base['96'] + $base['97'] + $base['98'];

        foreach ($base as $k => $v) $base[$k] = round($v, 2);

        $efectivoFinalEsf = round((float) ($casillerosEvaluados['ESF']['10101']['valor'] ?? 0), 2);
        $controles = [
            'efectivo_final_esf'      => $efectivoFinalEsf,
            'dif_9507_vs_esf'         => round($base['9507'] - $efectivoFinalEsf, 2),
            'movimiento_efectivo'     => round($efectivoMovimiento, 2),
            'dif_9505_vs_movimiento'  => round($base['9505'] - $efectivoMovimiento, 2),
            'dif_9820_vs_9501'        => round($base['9820'] - $base['9501'], 2),
        ];

        return [
            'valores_base' => $base,
            'asientos'     => $asientos,
            'controles'    => $controles,
            'sin_efectivo' => !$hayCuentasEfectivo,
        ];
    }

    /**
     * Detalle del EFE para la vista previa: casilleros en el orden de la estructura con su valor
     * ya evaluado, controles de cuadre y el detalle de asientos con su clasificación.
     */
    public function getEfeDetalle(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $ev = $this->evaluarSupercias($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $casilleros = $ev['casilleros']['EFE'] ?? [];

        $filas = [];
        foreach ($casilleros as $cod => $cas) {
            $cod = (string) $cod;
            $filas[] = [
                'codigo'  => $cod,
                'nombre'  => (string) ($cas['nombre'] ?? ''),
                'valor'   => round((float) ($cas['valor'] ?? 0), 2),
                'formula' => (string) ($cas['formula'] ?? ''),
                'nivel'   => strlen($cod) <= 2 ? 1 : (strlen($cod) <= 4 ? 2 : (strlen($cod) <= 6 ? 3 : 4)),
                'otros'   => in_array($cod, \App\Helpers\SuperciasEfe::OTROS, true),
            ];
        }

        $totalOtros = 0.0;
        foreach ($ev['efe']['asientos'] as $a) {
            foreach ($a['asignaciones'] as $x) {
                if ($x['otros']) $totalOtros += abs($x['valor']);
            }
        }

        return [
            'filas'        => $filas,
            'controles'    => $ev['efe']['controles'],
            'asientos'     => $ev['efe']['asientos'],
            'sin_efectivo' => $ev['efe']['sin_efectivo'],
            'total_otros'  => round($totalOtros, 2),
        ];
    }

    /** Filas del ECP (Supercías) en el orden del formulario oficial. Fuente: App\Helpers\SuperciasEcp. */
    public const ECP_FILAS = \App\Helpers\SuperciasEcp::FILAS;

    /** Filas de "cambios del año" que una cuenta puede fijar en su mapeo (Supercias ECP Fila). */
    public const ECP_FILAS_CAMBIO = \App\Helpers\SuperciasEcp::FILAS_CAMBIO;

    /**
     * Fila por defecto de "cambios del año" según la columna (componente del patrimonio):
     * capital → aumento de capital; aportes → aportes; prima → prima; reservas y resultados
     * acumulados → transferencia de resultados; otros resultados integrales → otros cambios.
     */
    private function ecpFilaPorDefecto(string $columna): string
    {
        if ($columna === '301') return '990201';
        if ($columna === '302') return '990202';
        if ($columna === '303') return '990203';
        if (str_starts_with($columna, '305')) return '990209';
        return '990205'; // 304xx reservas, 306xx resultados acumulados, 307xx resultado del ejercicio
    }

    /**
     * Estado de Cambios en el Patrimonio (Supercías): valores base por celda "fila.columna".
     *  - Columna: el campo Supercias ECP Subcódigo de cada cuenta de patrimonio (301 … 30702).
     *  - 990101 = saldo inicial (asientos de apertura del rango) de las cuentas de la columna.
     *  - 9902xx = movimiento del año de cada cuenta, en la fila fijada en su mapeo (Supercias ECP
     *    Código, si es una fila de cambio) o en la fila por defecto de su columna.
     *  - 990210 = resultado del ejercicio en 30701 (ganancia) o 30702 (pérdida), igual que el balance.
     *  - 9901, 9902 y 99 se totalizan aquí; si el casillero tiene fórmula en /config/supercias
     *    (p. ej. 99 = [ESF:301]), la fórmula manda y este valor solo sirve para comparar.
     */
    public function calcularEcp(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto, ?int $idProyecto, float $resultadoEjercicio): array
    {
        $cuentas = $this->repository->getMovimientosPatrimonioEcp($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);

        $base = [];
        $columnas = [];
        $detalle = [];
        $add = function (string $fila, string $col, float $v) use (&$base): void {
            $k = $fila . '.' . $col;
            $base[$k] = ($base[$k] ?? 0.0) + $v;
        };

        $sinMapeo = []; // cuentas de patrimonio con saldo/movimiento que NO entran al ECP (sin columna o columna inválida)
        foreach ($cuentas as $c) {
            if ((int) $c['nivel'] !== 5) continue;
            $saldoInicial = (float) $c['saldo_inicial'];
            $movimiento   = (float) $c['movimiento'];
            $tieneValor   = round($saldoInicial, 2) != 0 || round($movimiento, 2) != 0;

            $col = trim((string) ($c['supercias_ecp_subcodigo'] ?? ''));
            if ($col === '' || !in_array($col, \App\Helpers\SuperciasEcp::COLUMNAS, true)) {
                if ($tieneValor) {
                    $sinMapeo[] = [
                        'codigo'        => $c['codigo'],
                        'nombre'        => $c['nombre'],
                        'columna'       => $col,
                        'motivo'        => $col === '' ? 'Sin columna ECP' : 'Columna ECP no válida',
                        'saldo_inicial' => $saldoInicial,
                        'movimiento'    => $movimiento,
                    ];
                }
                continue;
            }
            $columnas[$col] = true;

            $filaFijada = trim((string) ($c['supercias_ecp_codigo'] ?? ''));
            $filaCambio = in_array($filaFijada, self::ECP_FILAS_CAMBIO, true) ? $filaFijada : $this->ecpFilaPorDefecto($col);

            if (round($saldoInicial, 2) != 0) $add('990101', $col, $saldoInicial);
            if (round($movimiento, 2) != 0)   $add($filaCambio, $col, $movimiento);

            if ($tieneValor) {
                $detalle[] = [
                    'codigo'        => $c['codigo'],
                    'nombre'        => $c['nombre'],
                    'columna'       => $col,
                    'fila_cambio'   => $filaCambio,
                    'saldo_inicial' => $saldoInicial,
                    'movimiento'    => $movimiento,
                ];
            }
        }

        // Resultado del ejercicio: ganancia en 30701 (positivo) o pérdida en 30702 (negativo).
        if (round($resultadoEjercicio, 2) != 0) {
            $colRes = $resultadoEjercicio >= 0 ? '30701' : '30702';
            $columnas[$colRes] = true;
            $add('990210', $colRes, $resultadoEjercicio);
        }

        // Totales por columna: 9901 = 990101+990102+990103; 9902 = Σ 9902xx; 99 = 9901 + 9902.
        foreach (array_keys($columnas) as $col) {
            $s9901 = 0.0;
            foreach (['990101', '990102', '990103'] as $f) $s9901 += $base[$f . '.' . $col] ?? 0.0;
            $s9902 = 0.0;
            foreach (array_keys(self::ECP_FILAS) as $f) {
                $f = (string) $f; // PHP convierte las claves numéricas a int
                if (str_starts_with($f, '9902') && $f !== '9902') $s9902 += $base[$f . '.' . $col] ?? 0.0;
            }
            $base['9901.' . $col] = $s9901;
            $base['9902.' . $col] = $s9902;
            $base['99.' . $col]   = $s9901 + $s9902;
        }

        return [
            'valores_base'        => $base,
            'columnas'            => array_keys($columnas),
            'detalle'             => $detalle,
            'sin_mapeo'           => $sinMapeo,
            'resultado_ejercicio' => $resultadoEjercicio,
        ];
    }

    /**
     * Matriz del ECP ya evaluada (fórmulas incluidas) para la vista previa en pantalla:
     * columnas (de la estructura ECP de /config/supercias), filas (orden oficial), valor por celda,
     * total por fila, y por columna la diferencia entre la fila 99 (saldo final, normalmente
     * fórmula = ESF) y 9901 + 9902 (saldo inicial + cambios calculados desde los asientos).
     */
    public function getEcpMatriz(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $ev = $this->evaluarSupercias($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $casilleros = $ev['casilleros']['ECP'] ?? [];

        // Columnas y nombres desde la estructura (fila 99 = "SALDO AL FINAL … / <componente>").
        $columnas = [];
        foreach ($casilleros as $key => $cas) {
            $partes = explode('.', (string) $key, 2);
            if (count($partes) < 2 || $partes[1] === '') continue;
            $col = $partes[1];
            if (!isset($columnas[$col])) {
                $nombre = (string) ($cas['nombre'] ?? '');
                $pos = strrpos($nombre, '/');
                $columnas[$col] = $pos !== false ? trim(substr($nombre, $pos + 1)) : $col;
            }
        }
        ksort($columnas, SORT_STRING);

        $valores = [];
        $totalesFila = [];
        foreach (self::ECP_FILAS as $fila => $_) {
            $totalesFila[$fila] = 0.0;
            foreach (array_keys($columnas) as $col) {
                $v = (float) ($casilleros[$fila . '.' . $col]['valor'] ?? 0);
                $valores[$fila][$col] = $v;
                $totalesFila[$fila] += $v;
            }
        }

        $diferencias = [];
        foreach (array_keys($columnas) as $col) {
            $diferencias[$col] = round(($valores['99'][$col] ?? 0) - (($valores['9901'][$col] ?? 0) + ($valores['9902'][$col] ?? 0)), 2);
        }

        return [
            'columnas'            => $columnas,
            'filas'               => self::ECP_FILAS,
            'valores'             => $valores,
            'totales_fila'        => $totalesFila,
            'diferencias'         => $diferencias,
            'detalle'             => $ev['ecp']['detalle'],
            'sin_mapeo'           => $ev['ecp']['sin_mapeo'],
            'resultado_ejercicio' => $ev['ecp']['resultado_ejercicio'],
        ];
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
     * PDF horizontal por periodos (una columna por mes). El diseño (logo, cabecera de la
     * empresa, filas por nivel, pie y firmas) vive en EstadosFinancierosPdfService.
     *
     * @param array $empresa Fila completa de `empresas` (logo, RUC, representante legal, contador)
     */
    public function exportarPdfPorPeriodos(string $tipo, array $datos, array $empresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivel = 5): void
    {
        $filtros = $this->filtrosParaPdf((int)($empresa['id'] ?? 0), $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        (new EstadosFinancierosPdfService())->exportar($tipo, $datos, $empresa, $filtros);
    }

    /**
     * PDF vertical de un solo periodo. Ver EstadosFinancierosPdfService.
     *
     * @param array $empresa Fila completa de `empresas` (logo, RUC, representante legal, contador)
     */
    public function exportarPdf(string $tipo, array $datos, array $empresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null, int $nivel = 5): void
    {
        $filtros = $this->filtrosParaPdf((int)($empresa['id'] ?? 0), $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        (new EstadosFinancierosPdfService())->exportar($tipo, $datos, $empresa, $filtros);
    }

    /**
     * Filtros del reporte en forma legible para la cabecera del PDF: fechas y nivel, y el
     * nombre (no el id) del centro de costo y del proyecto seleccionados.
     */
    private function filtrosParaPdf(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto, ?int $idProyecto, int $nivel): array
    {
        $nombreDe = function (array $lista, ?int $id): ?string {
            if ($id === null || $id <= 0) {
                return null;
            }
            foreach ($lista as $fila) {
                if ((int)$fila['id'] === $id) {
                    return trim(($fila['codigo'] ?? '') . ' - ' . ($fila['nombre'] ?? ''), ' -');
                }
            }
            return '#' . $id;
        };

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'nivel'        => $nivel,
            'centro_costo' => $nombreDe($idCentroCosto ? $this->repository->getCentrosCostoActivos($idEmpresa) : [], $idCentroCosto),
            'proyecto'     => $nombreDe($idProyecto ? $this->repository->getProyectosActivos($idEmpresa) : [], $idProyecto),
        ];
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
