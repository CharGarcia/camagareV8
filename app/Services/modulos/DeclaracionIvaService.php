<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\repositories\modulos\FacturaVentaRepository;

class DeclaracionIvaService
{
    private $repository;
    private $fvRepository;

    public function __construct(DeclaracionIvaRepository $repository)
    {
        $this->repository = $repository;
        $this->fvRepository = new FacturaVentaRepository();
    }

    /**
     * Ejecuta la auditoría para un periodo.
     */
    public function auditarPeriodo(int $idEmpresa, string $anio, string $mes): array
    {
        $fechaDesde = "{$anio}-{$mes}-01";
        $fechaHasta = date("Y-m-t", strtotime($fechaDesde));

        $descuadres = $this->repository->getDescuadresVentas($idEmpresa, $fechaDesde, $fechaHasta);
        
        return [
            'ok' => true,
            'descuadres' => $descuadres,
            'recuento' => count($descuadres)
        ];
    }

    /**
     * Regenera los casilleros para las facturas que presentan inconsistencias.
     */
    public function sincronizarPeriodo(int $idEmpresa, string $anio, string $mes, int $idUsuario): array
    {
        $fechaDesde = "{$anio}-{$mes}-01";
        $fechaHasta = date("Y-m-t", strtotime($fechaDesde));

        // Limpiar huérfanos (documentos que fueron eliminados o anulados recientemente)
        $this->repository->limpiarCasillerosHuerfanos($idEmpresa, $fechaDesde, $fechaHasta);

        // 1. Facturas de Venta
        $ventas = $this->repository->getDocumentosPeriodo($idEmpresa, 'ventas_cabecera', $fechaDesde, $fechaHasta);
        $fvService = new FacturaVentaService($this->fvRepository, new \App\Rules\modulos\FacturaVentaRules(), new \App\Services\LogSistemaService());
        foreach ($ventas as $v) {
            $fvService->sincronizarCasilleros((int)$v['id'], null);
        }

        // 2. Compras
        $compras = $this->repository->getDocumentosPeriodo($idEmpresa, 'compras_cabecera', $fechaDesde, $fechaHasta);
        $compService = new ComprasService();
        foreach ($compras as $c) {
            $compService->sincronizarCasilleros((int)$c['id'], null);
        }

        // 3. Liquidaciones de Compra
        $liq = $this->repository->getDocumentosPeriodo($idEmpresa, 'liquidaciones_cabecera', $fechaDesde, $fechaHasta);
        $liqService = new LiquidacionCompraService(new \App\repositories\modulos\LiquidacionCompraRepository(), new \App\Rules\modulos\LiquidacionCompraRules(), new \App\Services\LogSistemaService());
        foreach ($liq as $l) {
            $liqService->sincronizarCasilleros((int)$l['id'], null);
        }

        // 4. Notas de Crédito
        $ncs = $this->repository->getDocumentosPeriodo($idEmpresa, 'notas_credito_cabecera', $fechaDesde, $fechaHasta);
        $ncService = new NotaCreditoService(new \App\repositories\modulos\NotaCreditoRepository(), new \App\Rules\modulos\NotaCreditoRules(), new \App\Services\LogSistemaService());
        foreach ($ncs as $nc) {
            $ncService->sincronizarCasilleros((int)$nc['id'], null);
        }

        // 5. Retenciones en Compras
        $retC = $this->repository->getDocumentosPeriodo($idEmpresa, 'retencion_compra_cabecera', $fechaDesde, $fechaHasta);
        $retCService = new RetencionCompraService(new \App\repositories\modulos\RetencionCompraRepository(), new \App\Rules\modulos\RetencionCompraRules(), new \App\Services\LogSistemaService());
        foreach ($retC as $rc) {
            $retCService->sincronizarCasilleros((int)$rc['id'], null);
        }

        // 6. Retenciones en Ventas
        $retV = $this->repository->getDocumentosPeriodo($idEmpresa, 'retencion_venta_cabecera', $fechaDesde, $fechaHasta);
        $retVService = new RetencionVentaService(new \App\repositories\modulos\RetencionVentaRepository(), new \App\Rules\modulos\RetencionVentaRules(), new \App\Services\LogSistemaService());
        foreach ($retV as $rv) {
            $retVService->sincronizarCasilleros((int)$rv['id'], null);
        }

        return ['ok' => true, 'mensaje' => 'Sincronización completa finalizada.'];
    }

    /**
     * Genera el resumen final del periodo, agrupando por casilleros,
     * limitando a 0 (para que no existan valores negativos), y 
     * resolviendo las fórmulas matemáticas.
     */
    public function getResumenCompleto(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        // 1. Obtener sumatorias desde base de datos (se mantienen los agrupados por código '401', etc)
        $rawSums = $this->repository->getResumenPorCasilleros($idEmpresa, $fechaDesde, $fechaHasta);
        $sums = [];
        foreach ($rawSums as $row) {
            // Aplicar MAX(0, valor) para casilleros directos (facturas - notas de crédito)
            $sums[$row['casillero']] = max(0, (float)$row['total']);
        }

        // 2. Obtener estructura oficial (ahora por filas de 7 columnas)
        $estructura = $this->repository->getEstructuraFormulario();

        // 3. Extraer fórmulas y casilleros de la estructura matricial
        $formulas = [];
        foreach ($estructura as $e) {
            if ($e['casillero_bruto']) {
                if ($e['formula_bruto']) $formulas[$e['casillero_bruto']] = $e['formula_bruto'];
                if (!isset($sums[$e['casillero_bruto']])) $sums[$e['casillero_bruto']] = 0.0;
            }
            if ($e['casillero_neto']) {
                if ($e['formula_neto']) $formulas[$e['casillero_neto']] = $e['formula_neto'];
                if (!isset($sums[$e['casillero_neto']])) $sums[$e['casillero_neto']] = 0.0;
            }
            if ($e['casillero_impuesto']) {
                if ($e['formula_impuesto']) $formulas[$e['casillero_impuesto']] = $e['formula_impuesto'];
                if (!isset($sums[$e['casillero_impuesto']])) $sums[$e['casillero_impuesto']] = 0.0;
            }
        }

        // Ejecutar las fórmulas (simple string replace and eval)
        $maxPasadas = 3;
        for ($i = 0; $i < $maxPasadas; $i++) {
            $cambio = false;
            foreach ($formulas as $casilleroObj => $formulaStr) {
                // Remplazar los códigos por los valores actuales
                $expresion = preg_replace_callback('/\b(\d{3})\b/', function($matches) use ($sums) {
                    $key = $matches[1];
                    return isset($sums[$key]) ? (string)$sums[$key] : '0';
                }, $formulaStr);

                // Evaluar la expresión matemática de forma segura
                $resultado = $this->evaluarMatematica($expresion);
                $resultado = max(0, $resultado);

                if (abs($sums[$casilleroObj] - $resultado) > 0.001) {
                    $sums[$casilleroObj] = $resultado;
                    $cambio = true;
                }
            }
            if (!$cambio) break;
        }

        // 4. Formatear la respuesta final retornando la estructura Y los valores por separado
        // para que la interfaz dibuje las 7 columnas
        return [
            'layout' => $estructura,
            'valores' => $sums
        ];
    }

    /**
     * Evaluador simple y seguro de expresiones matemáticas (+, -, *, /, paréntesis)
     */
    private function evaluarMatematica(string $expr): float
    {
        // Limpiar espacios y caracteres no permitidos
        $expr = preg_replace('/[^0-9\+\-\*\/\.\(\)]/', '', $expr);
        if (empty($expr)) return 0.0;

        try {
            // Evaluador seguro usando Tokenizer o eval controlado
            // Por simplicidad en un entorno controlado sin variables:
            $result = @eval('return ' . $expr . ';');
            return is_numeric($result) ? (float)$result : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
