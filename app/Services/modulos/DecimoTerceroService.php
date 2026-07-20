<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DecimoTerceroRepository;
use App\Rules\modulos\DecimoTerceroRules;
use App\Services\LogSistemaService;
use Exception;

class DecimoTerceroService
{
    private DecimoTerceroRepository $repo;
    private DecimoTerceroRules $rules;
    private LogSistemaService $log;
    private DecimoTerceroCalculoService $calc;

    public function __construct(DecimoTerceroRepository $repo, DecimoTerceroRules $rules, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->log = $log;
        $this->calc = new DecimoTerceroCalculoService();
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
     * Calcula (o recalcula) la declaración de un año: trae empleados activos,
     * agrega el total ganado en el período (dic año anterior - nov año actual)
     * desde los roles MENSUAL, y guarda cabecera + detalle.
     */
    public function calcular(int $idEmpresa, int $anio, string $baseCalculo, int $idUsuario): int
    {
        $this->rules->validarCalculo(['anio' => $anio, 'base_calculo' => $baseCalculo]);

        $existente = $this->repo->findCabeceraBorrador($idEmpresa, $anio);
        if ($existente) {
            $this->rules->validarRecalculo($existente, $this->repo->tienePagos((int) $existente['id']));
        }

        $periodo = $this->calc->periodoNacional($anio);
        $soloIess = $baseCalculo === 'solo_iess';

        $empleados = $this->repo->getEmpleadosActivos($idEmpresa);

        $filas = [];
        $totalValor = 0.0;
        foreach ($empleados as $emp) {
            $idEmp = (int) $emp['id'];
            $periodos = $this->repo->getPeriodosEmpleado($idEmp, $idEmpresa);
            $dias = $this->calc->diasLaborados360($periodos, $periodo['fecha_desde'], $periodo['fecha_hasta']);
            $totalGanado = $this->repo->getTotalGanadoEmpleado($idEmp, $idEmpresa, $periodo['fecha_desde'], $periodo['fecha_hasta'], $soloIess);
            $mensualiza = ($emp['decimo_tercero'] ?? '') === 'mensualiza';
            $valor = $this->calc->valor($totalGanado, $mensualiza);
            $totalValor += $valor;

            [$nombres, $apellidos] = $this->partirNombreCompleto((string) $emp['nombres_apellidos']);

            $filas[] = [
                'id_empleado'       => $idEmp,
                'identificacion'    => $emp['identificacion'],
                'nombres'           => $nombres,
                'apellidos'         => $apellidos,
                'sexo'              => $emp['sexo'],
                'codigo_ocupacion'  => $emp['codigo_sectorial_iess'],
                'dias_laborados'    => $dias,
                'total_ganado'      => $totalGanado,
                'valor'             => $valor,
                'mensualiza'        => $mensualiza,
                'tipo_pago'         => !empty($emp['numero_cuenta']) ? 'A' : 'P',
                'discapacidad'      => (bool) $emp['discapacidad'],
                'valor_retencion'   => (float) $emp['valor_retencion_judicial'],
            ];
        }

        $this->repo->beginTransaction();
        try {
            if ($existente) {
                $idCabecera = (int) $existente['id'];
                $this->repo->limpiarDetalle($idCabecera);
                $this->repo->actualizarBaseCalculo($idCabecera, $baseCalculo);
            } else {
                $idCabecera = $this->repo->crearCabecera([
                    'id_empresa'        => $idEmpresa,
                    'anio'              => $anio,
                    'fecha_desde'       => $periodo['fecha_desde'],
                    'fecha_hasta'       => $periodo['fecha_hasta'],
                    'fecha_limite_pago' => $periodo['fecha_limite'],
                    'base_calculo'      => $baseCalculo,
                    'id_usuario'        => $idUsuario,
                ]);
            }
            $this->repo->insertDetalleMasivo($idCabecera, $idEmpresa, $filas, $idUsuario);
            $this->repo->actualizarTotales($idCabecera, count($filas), round($totalValor, 2), 'calculado');
            $this->log->registrar($idUsuario, $idEmpresa, $existente ? 'RECALCULAR' : 'CALCULAR', 'decimo_tercero_cabecera', $idCabecera, $existente, [
                'anio' => $anio, 'base_calculo' => $baseCalculo, 'total_empleados' => count($filas), 'total_valor' => round($totalValor, 2),
            ]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return $idCabecera;
    }

    /**
     * Ajusta tipo_pago/discapacidad/retención/nombres/total_ganado de una fila
     * antes de exportar o pagar. Si se edita total_ganado, recalcula valor
     * (total_ganado/12, o 0 si el empleado mensualiza).
     */
    public function actualizarDetalleEmpleado(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): void
    {
        $this->rules->validarDetalle($campos);

        if (array_key_exists('total_ganado', $campos)) {
            $fila = $this->repo->getDetalleFila($idDetalle, $idEmpresa);
            if (!$fila) throw new Exception('Registro no encontrado.');
            $mensualiza = $fila['mensualiza'] === true || $fila['mensualiza'] === 't' || $fila['mensualiza'] === '1';
            $campos['valor'] = $this->calc->valor((float) $campos['total_ganado'], $mensualiza);
        }

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
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR', 'decimo_tercero_cabecera', $idCabecera, $cab, null);
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
}
