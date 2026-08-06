<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DecimoCuartoRepository;
use App\Rules\modulos\DecimoCuartoRules;
use App\Services\LogSistemaService;
use Exception;

class DecimoCuartoService
{
    private DecimoCuartoRepository $repo;
    private DecimoCuartoRules $rules;
    private LogSistemaService $log;
    private DecimoCuartoCalculoService $calc;

    public function __construct(DecimoCuartoRepository $repo, DecimoCuartoRules $rules, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new DecimoCuartoCalculoService();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getCabecera(int $id, int $idEmpresa): ?array
    {
        return $this->repo->findById($id, $idEmpresa);
    }

    public function getDetalle(int $idCabecera, int $idEmpresa): array
    {
        return $this->repo->getDetalle($idCabecera, $idEmpresa);
    }

    /**
     * Calcula (o recalcula, si ya existe una en borrador/calculado) la declaración
     * de un año + grupo de región: trae empleados activos de esa región, calcula
     * días laborados y valor por empleado, y guarda cabecera + detalle.
     */
    public function calcular(int $idEmpresa, int $anio, string $regionGrupo, int $idUsuario): int
    {
        $this->rules->validarCalculo(['anio' => $anio, 'region_grupo' => $regionGrupo]);

        $existente = $this->repo->findCabeceraBorrador($idEmpresa, $anio, $regionGrupo);
        $anteriores = [];
        // Empleados con pago (Egreso) ya registrado: sus filas NO se tocan (ni se
        // recalculan ni se pisan sus datos); solo se recalculan los que faltan por pagar.
        $pagados = [];
        $totalValorPagados = 0.0;
        if ($existente) {
            $idExistente = (int) $existente['id'];
            $idsPagados = array_flip($this->repo->getEmpleadosPagados($idExistente));
            foreach ($this->repo->getDetalle($idExistente, $idEmpresa) as $d) {
                $idEmp = (int) $d['id_empleado'];
                if (isset($idsPagados[$idEmp])) {
                    $pagados[$idEmp] = true;
                    $totalValorPagados += (float) $d['valor'];
                } else {
                    $anteriores[$idEmp] = $d;
                }
            }
        }

        $periodo = $this->calc->periodoPorRegion($regionGrupo, $anio);
        $salario = $this->repo->getSalario($anio);
        $sbu = (float) ($salario['sbu'] ?? 0);

        $regiones = $regionGrupo === 'sierra_amazonia' ? ['sierra', 'oriente'] : ['costa', 'insular'];
        $empleados = $this->repo->getEmpleadosPorRegion($idEmpresa, $regiones);

        $filas = [];
        $totalValor = $totalValorPagados;
        foreach ($empleados as $emp) {
            $idEmp = (int) $emp['id'];
            if (isset($pagados[$idEmp])) continue; // ya pagado: no se recalcula ni se pisa

            // Al recalcular, se conservan los campos que el usuario edita a mano en la
            // grilla (tipo de pago, discapacidad, jubilación, retención, nombres) y solo
            // se recalculan los campos derivados (días, valor, mensualiza).
            $prev = $anteriores[$idEmp] ?? null;

            $periodos = $this->repo->getPeriodosEmpleado($idEmp, $idEmpresa);
            $dias = $this->calc->diasLaborados($periodos, $periodo['fecha_desde'], $periodo['fecha_hasta']);
            if ($dias <= 0) continue; // ningún período del empleado cae dentro del rango de esta declaración

            $mensualiza = ($emp['decimo_cuarto'] ?? '') === 'mensualiza';
            $valor = $this->calc->valor($sbu, $dias, $mensualiza);
            $totalValor += $valor;

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
                'dias_laborados'    => $dias,
                'valor'             => $valor,
                'mensualiza'        => $mensualiza,
                'tipo_pago'         => $prev ? (string) $prev['tipo_pago'] : (!empty($emp['numero_cuenta']) ? 'A' : 'P'),
                'discapacidad'      => $prev ? $this->truthy($prev['discapacidad']) : (bool) $emp['discapacidad'],
                'fecha_jubilacion'  => $prev ? $prev['fecha_jubilacion'] : $emp['fecha_jubilacion_patronal'],
                'valor_retencion'   => $prev ? (float) $prev['valor_retencion'] : (float) $emp['valor_retencion_judicial'],
            ];
        }

        $totalEmpleados = count($filas) + count($pagados);

        $this->repo->beginTransaction();
        try {
            if ($existente) {
                $idCabecera = (int) $existente['id'];
                $this->repo->limpiarDetalle($idCabecera, array_keys($pagados));
            } else {
                $idCabecera = $this->repo->crearCabecera([
                    'id_empresa'        => $idEmpresa,
                    'anio'              => $anio,
                    'region_grupo'      => $regionGrupo,
                    'fecha_desde'       => $periodo['fecha_desde'],
                    'fecha_hasta'       => $periodo['fecha_hasta'],
                    'fecha_limite_pago' => $periodo['fecha_limite'],
                    'sbu_aplicado'      => $sbu,
                    'id_usuario'        => $idUsuario,
                ]);
            }
            $this->repo->insertDetalleMasivo($idCabecera, $idEmpresa, $filas, $idUsuario);
            $this->repo->actualizarTotales($idCabecera, $totalEmpleados, round($totalValor, 2), 'calculado');
            $this->log->registrar($idUsuario, $idEmpresa, $existente ? 'RECALCULAR' : 'CALCULAR', 'decimo_cuarto_cabecera', $idCabecera, $existente, [
                'anio' => $anio, 'region_grupo' => $regionGrupo, 'total_empleados' => $totalEmpleados, 'total_valor' => round($totalValor, 2),
            ]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return $idCabecera;
    }

    /** Ajusta tipo_pago/discapacidad/jubilación/retención/nombres de una fila antes de exportar o pagar. */
    public function actualizarDetalleEmpleado(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): void
    {
        $this->rules->validarDetalle($campos);
        $ok = $this->repo->actualizarDetalle($idDetalle, $idEmpresa, $campos, $idUsuario);
        if (!$ok) throw new Exception('No se pudo actualizar el registro (verifique los campos enviados).');
    }

    public function anular(int $idCabecera, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findById($idCabecera, $idEmpresa);
        if (!$cab) throw new Exception('Declaración no encontrada.');
        $this->rules->validarAnulacion($cab, $this->repo->tienePagos($idCabecera));

        $this->repo->beginTransaction();
        try {
            $this->repo->eliminarLogico($idCabecera, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR', 'decimo_cuarto_cabecera', $idCabecera, $cab, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Divide "Nombres y Apellidos" (campo único del sistema) en dos mitades de
     * palabras (convención local: nombres primero, luego apellidos). Es un punto
     * de partida editable — el usuario lo ajusta en la grilla antes de exportar.
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
