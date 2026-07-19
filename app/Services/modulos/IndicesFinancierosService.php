<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ActivoFijoRepository;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\repositories\modulos\IndicesFinancierosRepository;
use App\repositories\modulos\ReporteComprasRepository;
use App\repositories\modulos\ReporteInventarioRepository;
use App\repositories\modulos\ReporteVentasRepository;
use App\Rules\modulos\IndicesFinancierosRules;
use App\Services\LogSistemaService;
use App\Services\ReportService;
use TCPDF;

/**
 * Calcula índices financieros a partir de una fórmula configurable (árbol JSON),
 * evaluada con un intérprete propio — nunca eval(). No confundir con
 * App\Services\SuperciasEvaluatorService (motor de fórmulas de texto plano +/-
 * para los reportes regulatorios ESF/ERI/ECP de Supercías): son independientes.
 */
class IndicesFinancierosService
{
    private EstadosFinancierosService $estadosFinancierosService;

    public function __construct(
        private IndicesFinancierosRepository $repository,
        private IndicesFinancierosRules $rules,
        private LogSistemaService $logService,
        private EstadosFinancierosRepository $estadosFinancierosRepository,
        private CuentasPorCobrarRepository $cxcRepository,
        private CuentasPorPagarRepository $cxpRepository,
        private ReporteInventarioRepository $inventarioRepository,
        private ReporteVentasRepository $ventasRepository,
        private ReporteComprasRepository $comprasRepository,
        private ActivoFijoRepository $activoFijoRepository
    ) {
        $this->estadosFinancierosService = new EstadosFinancierosService($estadosFinancierosRepository, new ReportService());
    }

    // ════════════════════════════════════════════════════════════════════
    // NIVEL 1 — Clasificación de cuentas
    // ════════════════════════════════════════════════════════════════════

    public function getCuentasSinClasificar(int $idEmpresa): array
    {
        $cuentas = $this->repository->getCuentasSinClasificar($idEmpresa);
        foreach ($cuentas as &$c) {
            $c['sugerencia'] = $this->sugerirGrupoPorSupercias((string) ($c['supercias_esf'] ?? ''));
        }
        return $cuentas;
    }

    public function getClasificacion(int $idEmpresa): array
    {
        return $this->repository->getClasificacion($idEmpresa);
    }

    /** Sugerencia inicial (no vinculante) según el prefijo Supercías ESF ya cargado en la cuenta. */
    private function sugerirGrupoPorSupercias(string $supercias): ?string
    {
        if ($supercias === '') {
            return null;
        }
        return match (true) {
            str_starts_with($supercias, '101') => 'ACTIVO_CORRIENTE',
            str_starts_with($supercias, '102') => 'ACTIVO_NO_CORRIENTE',
            str_starts_with($supercias, '201') => 'PASIVO_CORRIENTE',
            str_starts_with($supercias, '202') => 'PASIVO_NO_CORRIENTE',
            default => null,
        };
    }

    public function guardarClasificacion(int $idEmpresa, int $idCuenta, string $grupo, int $idUsuario): void
    {
        $this->rules->validarClasificacion($grupo);
        $this->repository->guardarClasificacion($idEmpresa, $idCuenta, $grupo, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'indices_financieros_grupo_cuentas', $idCuenta, null, ['grupo' => $grupo]);
    }

    // ════════════════════════════════════════════════════════════════════
    // NIVEL 2 — Grupos personalizados
    // ════════════════════════════════════════════════════════════════════

    public function getGrupos(int $idEmpresa): array
    {
        return $this->repository->getGrupos($idEmpresa);
    }

    public function getCuentasDeGrupo(int $idGrupo): array
    {
        return $this->repository->getCuentasDeGrupo($idGrupo);
    }

    public function getCuentasParaSelector(int $idEmpresa, string $buscar = ''): array
    {
        return $this->repository->getCuentasParaSelector($idEmpresa, $buscar);
    }

    public function crearGrupo(array $data): int
    {
        $this->rules->validarGrupo($data);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repository->crearGrupo($data);
            $idCuentas = array_map('intval', $data['id_cuentas'] ?? []);
            if (!empty($idCuentas)) {
                $this->repository->setCuentasDeGrupo($id, (int) $data['id_empresa'], $idCuentas, (int) $data['id_usuario']);
            }
            $this->logService->registrar((int) $data['id_usuario'], (int) $data['id_empresa'], 'crear', 'indices_financieros_grupos', $id, null, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function actualizarGrupo(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $actual = $this->repository->getGrupoPorId($id, $idEmpresa);
        if (!$actual) {
            throw new \Exception('Grupo no encontrado.');
        }
        $this->rules->validarGrupo($data, $id);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->actualizarGrupo($id, $data);
            $idCuentas = array_map('intval', $data['id_cuentas'] ?? []);
            $this->repository->setCuentasDeGrupo($id, $idEmpresa, $idCuentas, (int) $data['id_usuario']);
            $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'indices_financieros_grupos', $id, $actual, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function eliminarGrupo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->repository->eliminarGrupo($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'indices_financieros_grupos', $id, null, null);
    }

    // ════════════════════════════════════════════════════════════════════
    // ÍNDICES (catálogo + siembra de estándar)
    // ════════════════════════════════════════════════════════════════════

    public function getIndices(int $idEmpresa, ?string $categoria = null): array
    {
        return $this->repository->getIndices($idEmpresa, $categoria);
    }

    public function crearIndice(array $data): int
    {
        $grupos = array_column($this->repository->getGrupos($data['id_empresa']), 'codigo');
        $this->rules->validarIndice($data, null, $grupos);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repository->crearIndice($data);
            $this->logService->registrar((int) $data['id_usuario'], (int) $data['id_empresa'], 'crear', 'indices_financieros_indices', $id, null, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function actualizarIndice(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $actual = $this->repository->getIndicePorId($id, $idEmpresa);
        if (!$actual) {
            throw new \Exception('Índice no encontrado.');
        }
        if (($actual['tipo'] ?? '') === 'estandar') {
            throw new \Exception('Los índices estándar del sistema no se pueden editar; puede desactivarlos o crear uno personalizado equivalente.');
        }
        $grupos = array_column($this->repository->getGrupos($idEmpresa), 'codigo');
        $this->rules->validarIndice($data, $id, $grupos);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->actualizarIndice($id, $data);
            $this->logService->registrar((int) $data['id_usuario'], $idEmpresa, 'actualizar', 'indices_financieros_indices', $id, $actual, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function eliminarIndice(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repository->getIndicePorId($id, $idEmpresa);
        if ($actual && ($actual['tipo'] ?? '') === 'estandar') {
            throw new \Exception('Los índices estándar del sistema no se pueden eliminar; desactívelos en su lugar.');
        }
        $this->repository->eliminarIndice($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'indices_financieros_indices', $id, null, null);
    }

    /** Activar/desactivar cualquier índice (estándar o personalizado) sin tocar su fórmula. */
    public function cambiarActivo(int $id, int $idEmpresa, int $idUsuario, bool $activo): void
    {
        $actual = $this->repository->getIndicePorId($id, $idEmpresa);
        if (!$actual) {
            throw new \Exception('Índice no encontrado.');
        }
        $data = $actual;
        $data['activo'] = $activo;
        $data['id_usuario'] = $idUsuario;
        $data['id_empresa'] = $idEmpresa;
        $this->repository->actualizarIndice($id, $data);
        $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'indices_financieros_indices', $id, $actual, $data);
    }

    /** Siembra el catálogo estándar (idempotente: no duplica si ya existen los códigos). */
    public function inicializarIndicesEstandar(int $idEmpresa, int $idUsuario): void
    {
        $existentes = array_column($this->repository->getIndices($idEmpresa), 'codigo');

        foreach ($this->catalogoEstandar() as $def) {
            if (in_array($def['codigo'], $existentes, true)) {
                continue;
            }
            $this->repository->crearIndice([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'codigo' => $def['codigo'],
                'nombre' => $def['nombre'],
                'categoria' => $def['categoria'],
                'tipo' => 'estandar',
                'unidad' => $def['unidad'],
                'formula' => $def['formula'],
                'descripcion' => $def['descripcion'],
                'orden' => $def['orden'],
                'activo' => true,
            ]);
        }
    }

    private function catalogoEstandar(): array
    {
        $g = fn (string $grupo) => ['grupo' => $grupo];
        $f = fn (string $fuente) => ['fuente' => $fuente];
        $c = fn ($n) => ['const' => $n];
        $op = fn (string $op, $l, $r) => ['op' => $op, 'left' => $l, 'right' => $r];

        return [
            // Liquidez
            ['codigo' => 'RAZON_CORRIENTE', 'nombre' => 'Razón Corriente', 'categoria' => 'liquidez', 'unidad' => 'razon', 'orden' => 1,
                'formula' => $op('/', $g('ACTIVO_CORRIENTE'), $g('PASIVO_CORRIENTE')),
                'descripcion' => 'Activo Corriente / Pasivo Corriente'],
            ['codigo' => 'PRUEBA_ACIDA', 'nombre' => 'Prueba Ácida', 'categoria' => 'liquidez', 'unidad' => 'razon', 'orden' => 2,
                'formula' => $op('/', $op('-', $g('ACTIVO_CORRIENTE'), $f('INVENTARIO_VALOR')), $g('PASIVO_CORRIENTE')),
                'descripcion' => '(Activo Corriente - Inventario) / Pasivo Corriente'],
            ['codigo' => 'CAPITAL_TRABAJO', 'nombre' => 'Capital de Trabajo', 'categoria' => 'liquidez', 'unidad' => 'monto', 'orden' => 3,
                'formula' => $op('-', $g('ACTIVO_CORRIENTE'), $g('PASIVO_CORRIENTE')),
                'descripcion' => 'Activo Corriente - Pasivo Corriente'],
            // Endeudamiento
            ['codigo' => 'ENDEUDAMIENTO_TOTAL', 'nombre' => 'Endeudamiento Total', 'categoria' => 'endeudamiento', 'unidad' => 'porcentaje', 'orden' => 1,
                'formula' => $op('/', $f('PASIVO_TOTAL'), $f('ACTIVO_TOTAL')),
                'descripcion' => 'Pasivo Total / Activo Total'],
            ['codigo' => 'APALANCAMIENTO', 'nombre' => 'Apalancamiento', 'categoria' => 'endeudamiento', 'unidad' => 'razon', 'orden' => 2,
                'formula' => $op('/', $f('PASIVO_TOTAL'), $f('PATRIMONIO')),
                'descripcion' => 'Pasivo Total / Patrimonio'],
            // Rentabilidad
            ['codigo' => 'MARGEN_BRUTO', 'nombre' => 'Margen Bruto', 'categoria' => 'rentabilidad', 'unidad' => 'porcentaje', 'orden' => 1,
                'formula' => $op('/', $op('-', $f('INGRESOS'), $f('COSTOS')), $f('INGRESOS')),
                'descripcion' => '(Ingresos - Costos) / Ingresos'],
            ['codigo' => 'MARGEN_NETO', 'nombre' => 'Margen Neto', 'categoria' => 'rentabilidad', 'unidad' => 'porcentaje', 'orden' => 2,
                'formula' => $op('/', $f('UTILIDAD_NETA'), $f('INGRESOS')),
                'descripcion' => 'Utilidad Neta / Ingresos'],
            ['codigo' => 'ROA', 'nombre' => 'ROA (Retorno sobre Activos)', 'categoria' => 'rentabilidad', 'unidad' => 'porcentaje', 'orden' => 3,
                'formula' => $op('/', $f('UTILIDAD_NETA'), $f('ACTIVO_TOTAL')),
                'descripcion' => 'Utilidad Neta / Activo Total'],
            ['codigo' => 'ROE', 'nombre' => 'ROE (Retorno sobre Patrimonio)', 'categoria' => 'rentabilidad', 'unidad' => 'porcentaje', 'orden' => 4,
                'formula' => $op('/', $f('UTILIDAD_NETA'), $f('PATRIMONIO')),
                'descripcion' => 'Utilidad Neta / Patrimonio'],
            // Actividad
            ['codigo' => 'ROTACION_INVENTARIO', 'nombre' => 'Rotación de Inventario', 'categoria' => 'actividad', 'unidad' => 'razon', 'orden' => 1,
                'formula' => $op('/', $f('COSTOS'), $f('INVENTARIO_VALOR')),
                'descripcion' => 'Costos / Valor de Inventario'],
            ['codigo' => 'DIAS_INVENTARIO', 'nombre' => 'Días de Inventario', 'categoria' => 'actividad', 'unidad' => 'dias', 'orden' => 2,
                'formula' => $op('/', $op('*', $f('INVENTARIO_VALOR'), $c(365)), $f('COSTOS')),
                'descripcion' => '(Valor de Inventario x 365) / Costos'],
            ['codigo' => 'ROTACION_CARTERA', 'nombre' => 'Rotación de Cartera', 'categoria' => 'actividad', 'unidad' => 'razon', 'orden' => 3,
                'formula' => $op('/', $f('VENTAS'), $f('CXC_SALDO')),
                'descripcion' => 'Ventas / Saldo por Cobrar'],
            ['codigo' => 'DIAS_COBRO', 'nombre' => 'Días de Cobro (DSO)', 'categoria' => 'actividad', 'unidad' => 'dias', 'orden' => 4,
                'formula' => $op('/', $op('*', $f('CXC_SALDO'), $c(365)), $f('VENTAS')),
                'descripcion' => '(Saldo por Cobrar x 365) / Ventas'],
            ['codigo' => 'ROTACION_CXP', 'nombre' => 'Rotación de Cuentas por Pagar', 'categoria' => 'actividad', 'unidad' => 'razon', 'orden' => 5,
                'formula' => $op('/', $f('COMPRAS'), $f('CXP_SALDO')),
                'descripcion' => 'Compras / Saldo por Pagar'],
            ['codigo' => 'DIAS_PAGO', 'nombre' => 'Días de Pago (DPO)', 'categoria' => 'actividad', 'unidad' => 'dias', 'orden' => 6,
                'formula' => $op('/', $op('*', $f('CXP_SALDO'), $c(365)), $f('COMPRAS')),
                'descripcion' => '(Saldo por Pagar x 365) / Compras'],
            ['codigo' => 'CICLO_CONVERSION_EFECTIVO', 'nombre' => 'Ciclo de Conversión de Efectivo', 'categoria' => 'actividad', 'unidad' => 'dias', 'orden' => 7,
                'formula' => $op('-',
                    $op('+', $op('/', $op('*', $f('INVENTARIO_VALOR'), $c(365)), $f('COSTOS')), $op('/', $op('*', $f('CXC_SALDO'), $c(365)), $f('VENTAS'))),
                    $op('/', $op('*', $f('CXP_SALDO'), $c(365)), $f('COMPRAS'))
                ),
                'descripcion' => 'Días Inventario + Días Cobro - Días Pago'],
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // CÁLCULO
    // ════════════════════════════════════════════════════════════════════

    public function calcularTodos(int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $valoresGrupo = $this->resolverValoresGrupo($idEmpresa, $fechaInicio, $fechaFin);
        $valoresFuente = $this->resolverValoresFuente($idEmpresa, $fechaInicio, $fechaFin);

        $indices = $this->repository->getIndices($idEmpresa, null, true);
        $resultado = ['liquidez' => [], 'endeudamiento' => [], 'rentabilidad' => [], 'actividad' => []];

        foreach ($indices as $indice) {
            $formula = json_decode($indice['formula'], true) ?: [];
            $valor = $this->evaluarFormula($formula, $valoresGrupo, $valoresFuente);

            $resultado[$indice['categoria']][] = [
                'codigo' => $indice['codigo'],
                'nombre' => $indice['nombre'],
                'unidad' => $indice['unidad'],
                'descripcion' => $indice['descripcion'],
                'valor' => $valor,
                'interpretacion' => $valor !== null ? $this->interpretar($indice['codigo'], $valor) : null,
            ];
        }

        return $resultado;
    }

    private function resolverValoresGrupo(int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $saldos = $this->estadosFinancierosRepository->getSaldos($idEmpresa, $fechaInicio, $fechaFin);

        $saldoDirectoPorCuenta = [];
        foreach ($saldos as $s) {
            $saldoDirectoPorCuenta[(int) $s['id_cuenta']] = $this->saldoDirectoConSigno(
                (string) $s['codigo'],
                (float) $s['total_debe'],
                (float) $s['total_haber']
            );
        }

        $valores = ['ACTIVO_CORRIENTE' => 0.0, 'ACTIVO_NO_CORRIENTE' => 0.0, 'PASIVO_CORRIENTE' => 0.0, 'PASIVO_NO_CORRIENTE' => 0.0];

        foreach ($this->repository->getMapaGrupoCuentas($idEmpresa) as $idCuenta => $grupo) {
            $valores[$grupo] = ($valores[$grupo] ?? 0.0) + ($saldoDirectoPorCuenta[$idCuenta] ?? 0.0);
        }

        foreach ($this->repository->getMapaCuentasGrupoPersonalizado($idEmpresa) as $codigoGrupo => $idCuentas) {
            $suma = 0.0;
            foreach ($idCuentas as $idCuenta) {
                $suma += $saldoDirectoPorCuenta[$idCuenta] ?? 0.0;
            }
            $valores[$codigoGrupo] = $suma;
        }

        return $valores;
    }

    private function saldoDirectoConSigno(string $codigo, float $debe, float $haber): float
    {
        $prefijo = $codigo !== '' ? $codigo[0] : '1';
        // 1=Activo, 5=Costo, 6=Gasto → naturaleza deudora. 2=Pasivo, 3=Patrimonio, 4=Ingreso → acreedora.
        return in_array($prefijo, ['1', '5', '6'], true) ? ($debe - $haber) : ($haber - $debe);
    }

    private function resolverValoresFuente(int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $situacion = $this->estadosFinancierosService->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin);
        $resultados = $this->estadosFinancierosService->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin);

        $filtrosPeriodo = ['fecha_desde' => $fechaInicio, 'fecha_hasta' => $fechaFin];
        $filtrosCorte = ['fecha_hasta' => $fechaFin];

        return [
            'ACTIVO_TOTAL' => (float) $situacion['totales']['activos'],
            'PASIVO_TOTAL' => (float) $situacion['totales']['pasivos'],
            'PATRIMONIO' => (float) $situacion['totales']['patrimonio'],
            'INGRESOS' => (float) $resultados['totales']['ingresos'],
            'COSTOS' => (float) $resultados['totales']['costos'],
            'GASTOS' => (float) $resultados['totales']['gastos'],
            'UTILIDAD_BRUTA' => (float) $resultados['totales']['utilidad_bruta'],
            'UTILIDAD_NETA' => (float) $resultados['totales']['utilidad_neta'],
            'CXC_SALDO' => (float) ($this->cxcRepository->getEstadisticas($idEmpresa, $filtrosCorte)['total_saldo'] ?? 0),
            'CXP_SALDO' => (float) ($this->cxpRepository->getEstadisticas($idEmpresa, $filtrosCorte)['total_saldo'] ?? 0),
            'INVENTARIO_VALOR' => (float) ($this->inventarioRepository->getExistenciasKpis($idEmpresa, [])['valor_total'] ?? 0),
            'ACTIVO_FIJO_NETO' => $this->activoFijoRepository->getValorNetoAgregado($idEmpresa),
            'VENTAS' => (float) ($this->ventasRepository->getEstadisticas($idEmpresa, $filtrosPeriodo)['gran_total'] ?? 0),
            'COMPRAS' => (float) ($this->comprasRepository->getEstadisticas($idEmpresa, $filtrosPeriodo)['gran_total'] ?? 0),
        ];
    }

    /** Evaluador propio del árbol de fórmula — nunca eval(). Null ante división por cero o término faltante. */
    private function evaluarFormula(array $nodo, array $valoresGrupo, array $valoresFuente): ?float
    {
        if (array_key_exists('const', $nodo)) {
            return (float) $nodo['const'];
        }
        if (array_key_exists('grupo', $nodo)) {
            return isset($valoresGrupo[$nodo['grupo']]) ? (float) $valoresGrupo[$nodo['grupo']] : 0.0;
        }
        if (array_key_exists('fuente', $nodo)) {
            return isset($valoresFuente[$nodo['fuente']]) ? (float) $valoresFuente[$nodo['fuente']] : 0.0;
        }
        if (array_key_exists('op', $nodo)) {
            $izq = $this->evaluarFormula($nodo['left'], $valoresGrupo, $valoresFuente);
            $der = $this->evaluarFormula($nodo['right'], $valoresGrupo, $valoresFuente);
            if ($izq === null || $der === null) {
                return null;
            }
            return match ($nodo['op']) {
                '+' => $izq + $der,
                '-' => $izq - $der,
                '*' => $izq * $der,
                '/' => $der != 0.0 ? $izq / $der : null,
                default => null,
            };
        }
        return null;
    }

    private function interpretar(string $codigo, float $valor): ?string
    {
        return match ($codigo) {
            'RAZON_CORRIENTE' => match (true) {
                $valor < 1 => 'Alerta: posible dificultad para cubrir obligaciones de corto plazo',
                $valor <= 2 => 'Normal',
                default => 'Alto: revisar si hay activos corrientes ociosos',
            },
            'PRUEBA_ACIDA' => $valor < 1 ? 'Alerta: liquidez inmediata ajustada' : 'Normal',
            'ENDEUDAMIENTO_TOTAL' => match (true) {
                $valor > 0.6 => 'Alto: empresa muy apalancada',
                $valor > 0.4 => 'Moderado',
                default => 'Bajo',
            },
            default => null,
        };
    }

    // ════════════════════════════════════════════════════════════════════
    // PDF — datos de empresa + firmas de responsabilidad
    // ════════════════════════════════════════════════════════════════════

    /**
     * Genera el PDF de Índices Financieros (encabezado con logo/RUC de la empresa,
     * tablas por categoría y 3 firmas de responsabilidad) y lo envía al navegador.
     * Sigue el mismo estilo TCPDF que EstadosFinancierosService::exportarPdf y
     * ComprobanteCajaPdfService::dibujarEncabezado/dibujarFirmas/resolverLogo.
     */
    public function exportarPdf(array $indicesPorCategoria, array $empresa, string $fechaInicio, string $fechaFin, string $nombreUsuario): void
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor((string) ($empresa['nombre'] ?? ''));
        $pdf->SetTitle('Índices Financieros');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 30);
        $mL = 12;
        $pdf->SetMargins($mL, $mL, $mL);
        $pdf->AddPage();
        $contentW = 210 - (2 * $mL);
        $y0 = $mL;

        // Encabezado: logo (si existe) + nombre/RUC/dirección de la empresa
        $logoPath = $this->resolverLogo($empresa);
        $textoX = $mL;
        if ($logoPath !== '') {
            $pdf->Image($logoPath, $mL, $y0, 24, 0, '', '', 'T', false, 300);
            $textoX = $mL + 27;
        }
        $pdf->SetXY($textoX, $y0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->MultiCell($contentW - ($textoX - $mL), 5, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 'L', false, 1);
        $pdf->SetFont('helvetica', '', 9);
        foreach (array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
        ]) as $linea) {
            $pdf->SetX($textoX);
            $pdf->MultiCell($contentW - ($textoX - $mL), 4, $linea, 0, 'L', false, 1);
        }

        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 7, 'ÍNDICES FINANCIEROS', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Período: ' . date('d-m-Y', strtotime($fechaInicio)) . ' al ' . date('d-m-Y', strtotime($fechaFin)), 0, 1, 'C');
        $pdf->Ln(3);

        $categorias = ['liquidez' => 'LIQUIDEZ', 'endeudamiento' => 'ENDEUDAMIENTO', 'rentabilidad' => 'RENTABILIDAD', 'actividad' => 'ACTIVIDAD'];
        foreach ($categorias as $clave => $titulo) {
            $items = $indicesPorCategoria[$clave] ?? [];
            if (empty($items)) {
                continue;
            }
            $html = '<table cellpadding="3" border="1">
                        <thead><tr style="background-color:#f0f0f0; font-weight:bold;">
                            <th width="35%">' . $titulo . '</th><th width="20%" align="right">Valor</th><th width="45%">Interpretación</th>
                        </tr></thead><tbody>';
            foreach ($items as $ind) {
                $html .= '<tr><td>' . htmlspecialchars($ind['nombre']) . '</td>'
                    . '<td align="right">' . $this->formatoValorPdf($ind['valor'], $ind['unidad']) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($ind['interpretacion'] ?? '')) . '</td></tr>';
            }
            $html .= '</tbody></table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(3);
        }

        $this->dibujarFirmasResponsabilidad($pdf, $mL, $contentW, $nombreUsuario);

        if (ob_get_length()) {
            ob_end_clean();
        }
        $pdf->Output('indices_financieros_' . date('YmdHis') . '.pdf', 'D');
        exit;
    }

    /** 3 firmas: Elaborado por (usuario actual) / Revisado por / Aprobado por. */
    private function dibujarFirmasResponsabilidad(TCPDF $pdf, float $mL, float $contentW, string $nombreUsuario): void
    {
        $yLinea = $pdf->GetY() + 20;
        if ($yLinea > 260) {
            $pdf->AddPage();
            $yLinea = 40;
        }

        $colW = $contentW / 3;
        $firmas = [
            ['Elaborado por', $nombreUsuario],
            ['Revisado por', ''],
            ['Aprobado por', ''],
        ];

        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->Line($x + 6, $yLinea, $x + $colW - 6, $yLinea);
        }
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($mL, $yLinea + 1);
        foreach ($firmas as $f) {
            $pdf->Cell($colW, 4, $f[0], 0, 0, 'C');
        }
        $pdf->SetFont('helvetica', '', 7.5);
        foreach ($firmas as $i => $f) {
            $x = $mL + $i * $colW;
            $pdf->SetXY($x + 3, $yLinea + 5);
            $pdf->MultiCell($colW - 6, 3.4, $f[1] !== '' ? $f[1] : ' ', 0, 'C', false, 0, '', '', true, 0, false, true, 0, 'T');
        }
    }

    private function formatoValorPdf(?float $valor, string $unidad): string
    {
        if ($valor === null) {
            return 'N/D';
        }
        return match ($unidad) {
            'porcentaje' => number_format($valor * 100, 2) . '%',
            'dias'       => number_format($valor, 1) . ' días',
            'monto'      => '$' . number_format($valor, 2),
            default      => number_format($valor, 2),
        };
    }

    /** Ruta en disco del logo de la empresa (maneja el prefijo web /sistema/public), igual que ComprobanteCajaPdfService::resolverLogo. */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string) $ruta, '/');
            if (strpos($clean, 'sistema/public/') === 0) {
                $clean = substr($clean, strlen('sistema/public/'));
            } elseif (strpos($clean, 'sistema/') === 0) {
                $clean = substr($clean, strlen('sistema/'));
            }
            if (strpos($clean, 'public/') === 0) {
                $clean = substr($clean, strlen('public/'));
            }
            foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return '';
    }
}
