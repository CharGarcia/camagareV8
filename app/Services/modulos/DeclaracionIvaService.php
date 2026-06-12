<?php

declare(strict_types=1);

namespace App\services\modulos;

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
        $fvService = new FacturaVentaService($this->fvRepository, new \App\Rules\modulos\FacturaVentaRules(), new \App\services\LogSistemaService());
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
        $liquidaciones = $this->repository->getDocumentosPeriodo($idEmpresa, 'liquidaciones_cabecera', $fechaDesde, $fechaHasta);
        $liqService = new LiquidacionCompraService(new \App\repositories\modulos\LiquidacionCompraRepository(), new \App\Rules\modulos\LiquidacionCompraRules(), new \App\services\LogSistemaService());
        foreach ($liquidaciones as $l) {
            $liqService->sincronizarCasilleros((int)$l['id'], null);
        }

        // 4. Notas de Crédito
        $notasCredito = $this->repository->getDocumentosPeriodo($idEmpresa, 'notas_credito_cabecera', $fechaDesde, $fechaHasta);
        $ncService = new NotaCreditoService(new \App\repositories\modulos\NotaCreditoRepository(), new \App\Rules\modulos\NotaCreditoRules(), new \App\services\LogSistemaService());
        foreach ($notasCredito as $n) {
            $ncService->sincronizarCasilleros((int)$n['id'], null);
        }

        // 5. Retenciones en Compras
        $retCompras = $this->repository->getDocumentosPeriodo($idEmpresa, 'retencion_compra_cabecera', $fechaDesde, $fechaHasta);
        $retCService = new RetencionCompraService(new \App\repositories\modulos\RetencionCompraRepository(), new \App\Rules\modulos\RetencionCompraRules(), new \App\services\LogSistemaService());
        foreach ($retCompras as $rc) {
            $retCService->sincronizarCasilleros((int)$rc['id'], null);
        }

        // 6. Retenciones en Ventas
        $retVentas = $this->repository->getDocumentosPeriodo($idEmpresa, 'retencion_venta_cabecera', $fechaDesde, $fechaHasta);
        $retVService = new RetencionVentaService(new \App\repositories\modulos\RetencionVentaRepository(), new \App\Rules\modulos\RetencionVentaRules(), new \App\services\LogSistemaService());
        foreach ($retVentas as $rv) {
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

        // 2b. Casilleros de conteo: filas cuya fuente es un conteo de documentos
        // del período (configurado en la estructura con fuente_valor)
        foreach ($estructura as $e) {
            $fuente = $e['fuente_valor'] ?? 'documentos';
            if ($fuente !== '' && $fuente !== null && $fuente !== 'documentos') {
                $casillero = $e['casillero_bruto'] ?: ($e['casillero_neto'] ?: $e['casillero_impuesto']);
                if ($casillero) {
                    $sums[$casillero] = (float) $this->repository->getConteoDocumentos($idEmpresa, $fuente, $fechaDesde, $fechaHasta);
                }
            }
        }

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
     * Calcula el resumen del IVA a pagar de un período (pensado para avisos/automatizaciones).
     *
     * El sistema no consolida un casillero único de "valor a pagar", así que el neto se
     * calcula aquí a partir de los componentes:
     *   IVA a pagar = max(0, IVA ventas − crédito tributario − retenciones de IVA que le hicieron)
     *
     * Los casilleros de IVA se derivan de la estructura (sección 400 = ventas, 500 = compras),
     * por lo que sigue funcionando si se agregan nuevas tarifas.
     *
     * @return array{empresa:string,periodo:string,anio:int,mes:int,fecha_desde:string,
     *               fecha_hasta:string,iva_ventas:float,credito_tributario:float,
     *               retenciones:float,a_pagar:float,saldo_favor:float,num_facturas_venta:int,
     *               fecha_limite:string}
     */
    public function getResumenPago(int $idEmpresa, string $anio, string $mes, bool $sincronizar = true, int $idUsuario = 0): array
    {
        $mes        = str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
        $fechaDesde = "{$anio}-{$mes}-01";
        $finMes     = date('Y-m-t', strtotime($fechaDesde));
        $hoy        = date('Y-m-d');
        // Para el mes en curso se corta hasta hoy ("acumulado hasta la fecha").
        $fechaHasta = ($finMes > $hoy && $fechaDesde <= $hoy) ? $hoy : $finMes;

        if ($sincronizar) {
            $this->sincronizarPeriodo($idEmpresa, (string)$anio, $mes, $idUsuario);
        }

        $resumen = $this->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta);
        $valores = $resumen['valores'] ?? [];
        $layout  = $resumen['layout'] ?? [];

        $ivaVentas = 0.0;
        $credito   = 0.0;
        foreach ($layout as $row) {
            $casImp = $row['casillero_impuesto'] ?? '';
            if ($casImp === '' || !isset($valores[$casImp])) {
                continue;
            }
            $seccion = (string)($row['seccion'] ?? '');
            if (str_starts_with($seccion, '400')) {
                $ivaVentas += (float) $valores[$casImp];
            } elseif (str_starts_with($seccion, '500')) {
                $credito += (float) $valores[$casImp];
            }
        }

        $retenciones = $this->repository->getRetencionesIvaPeriodo($idEmpresa, $fechaDesde, $fechaHasta);

        // Conteo de facturas de venta emitidas (autorizadas) en el período.
        $numVentas = $this->repository->getConteoDocumentos($idEmpresa, 'conteo_ventas_emitidas', $fechaDesde, $fechaHasta);

        $neto       = $ivaVentas - $credito - $retenciones;
        $aPagar     = max(0.0, $neto);
        $saldoFavor = max(0.0, -$neto);

        $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $ruc           = (string) ($empresa['ruc'] ?? '');
        $nombreEmpresa = trim((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? ''));

        $nombresMes = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                       'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $periodoLabel = ($nombresMes[(int)$mes] ?? $mes) . ' ' . $anio;

        return [
            'id_empresa'         => $idEmpresa,
            'empresa'            => $nombreEmpresa,
            'periodo'            => $periodoLabel,
            'anio'               => (int) $anio,
            'mes'                => (int) $mes,
            'fecha_desde'        => $fechaDesde,
            'fecha_hasta'        => $fechaHasta,
            'iva_ventas'         => round($ivaVentas, 2),
            'credito_tributario' => round($credito, 2),
            'retenciones'        => round($retenciones, 2),
            'a_pagar'            => round($aPagar, 2),
            'saldo_favor'        => round($saldoFavor, 2),
            'num_facturas_venta' => $numVentas,
            'fecha_limite'       => $this->calcularFechaLimitePago($ruc, (int)$anio, (int)$mes),
        ];
    }

    /**
     * Calcula la fecha máxima de pago según el noveno dígito del RUC (calendario SRI
     * para declaraciones mensuales). La fecha cae en el mes siguiente al período.
     */
    public function calcularFechaLimitePago(string $ruc, int $anioPeriodo, int $mesPeriodo): string
    {
        $diaPorDigito = ['1' => 10, '2' => 12, '3' => 14, '4' => 16, '5' => 18,
                         '6' => 20, '7' => 22, '8' => 24, '9' => 26, '0' => 28];

        $soloDigitos = preg_replace('/\D/', '', $ruc);
        $noveno      = (strlen($soloDigitos) >= 9) ? $soloDigitos[8] : '0';
        $dia         = $diaPorDigito[$noveno] ?? 28;

        $fecha = (new \DateTime(sprintf('%04d-%02d-01', $anioPeriodo, $mesPeriodo)))
            ->modify('first day of next month');
        $fecha->setDate((int)$fecha->format('Y'), (int)$fecha->format('n'), $dia);

        return $fecha->format('d-m-Y');
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
