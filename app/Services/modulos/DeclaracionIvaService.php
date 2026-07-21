<?php

declare(strict_types=1);

namespace App\services\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\Rules\modulos\DeclaracionIvaRules;
use App\Services\LogSistemaService;

class DeclaracionIvaService
{
    private $repository;
    private $fvRepository;
    private $rules;
    private $logService;

    public function __construct(DeclaracionIvaRepository $repository)
    {
        $this->repository = $repository;
        $this->fvRepository = new FacturaVentaRepository();
        $this->rules = new DeclaracionIvaRules();
        $this->logService = new LogSistemaService();
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

        // 7. Importaciones (crédito tributario aduanero, solo nacionalizadas/cerradas)
        $importaciones = $this->repository->getDocumentosPeriodo($idEmpresa, 'importaciones_cabecera', $fechaDesde, $fechaHasta);
        $impService = new ImportacionesService();
        foreach ($importaciones as $imp) {
            $impService->sincronizarCasilleros((int)$imp['id'], $idEmpresa);
        }

        return ['ok' => true, 'mensaje' => 'Sincronización completa finalizada.'];
    }

    /**
     * Genera el resumen final del periodo, agrupando por casilleros,
     * limitando a 0 (para que no existan valores negativos), y 
     * resolviendo las fórmulas matemáticas.
     */
    public function getResumenCompleto(int $idEmpresa, string $fechaDesde, string $fechaHasta, string $tipoPeriodo = '', int $anio = 0, int $periodoValor = 0): array
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
            if ($fuente !== '' && $fuente !== null && $fuente !== 'documentos' && !str_starts_with($fuente, 'arrastre_')) {
                $casillero = $e['casillero_bruto'] ?: ($e['casillero_neto'] ?: $e['casillero_impuesto']);
                if ($casillero) {
                    $sums[$casillero] = (float) $this->repository->getConteoDocumentos($idEmpresa, $fuente, $fechaDesde, $fechaHasta);
                }
            }
        }

        // 2c. Casilleros de arrastre de crédito tributario (605/606 entrante, 615/617 saliente).
        // Solo se calculan si se recibió el contexto del período (tipo_periodo/anio/periodo_valor);
        // sin eso (llamadas antiguas) simplemente no se pintan estos casilleros.
        if ($tipoPeriodo !== '' && $anio > 0 && $periodoValor > 0) {
            $ambiente = $this->ambienteEmpresa($idEmpresa);
            [$anioAnt, $periodoAnt] = $this->periodoAnterior($tipoPeriodo, $anio, $periodoValor);
            $declAnterior = $this->repository->getDeclaracionAnterior($idEmpresa, $ambiente, $tipoPeriodo, $anioAnt, $periodoAnt);
            $creditoAnteriorCompras     = $declAnterior ? round((float) $declAnterior['saldo_favor_compras'], 2) : 0.0;
            $creditoAnteriorRetenciones = $declAnterior ? round((float) $declAnterior['saldo_favor_retenciones'], 2) : 0.0;
            $sums['605'] = $creditoAnteriorCompras;
            $sums['606'] = $creditoAnteriorRetenciones;

            // Si el período ya tiene una declaración guardada, se respeta el valor guardado
            // (pudo haber sido ajustado manualmente) en vez de recalcular el default y pisarlo.
            $declActual = $this->repository->findDeclaracion($idEmpresa, $ambiente, $tipoPeriodo, $anio, $periodoValor);
            if ($declActual) {
                $sums['615'] = round((float) $declActual['saldo_favor_compras'], 2);
                $sums['617'] = round((float) $declActual['saldo_favor_retenciones'], 2);
            } else {
                $comp = $this->repository->getResumenPagoDirecto($idEmpresa, $fechaDesde, $fechaHasta, $ambiente);
                $ivaVentasNeto      = round((float) $comp['iva_ventas'] - (float) $comp['iva_notas_credito'], 2);
                $creditoComprasNeto = round((float) $comp['iva_compras'] - (float) $comp['iva_notas_credito_compra'], 2);
                $split = $this->calcularSplitArrastre($ivaVentasNeto, $creditoComprasNeto, (float) $comp['retenciones'], $creditoAnteriorCompras, $creditoAnteriorRetenciones);
                $sums['615'] = $split['615'];
                $sums['617'] = $split['617'];
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
     * Los valores se obtienen DIRECTAMENTE de cada módulo (no de los casilleros
     * sincronizados), filtrando por el ambiente de la empresa:
     *   - IVA en ventas: facturas de venta autorizadas, menos notas de crédito autorizadas.
     *   - Crédito tributario: IVA de compras con deducible = 'declaracion_iva'.
     *   - Retenciones: IVA que le retuvieron en ventas.
     *   IVA a pagar = max(0, IVA ventas − crédito tributario − retenciones)
     *
     * El parámetro $sincronizar se mantiene por compatibilidad pero ya no es necesario,
     * porque el cálculo lee las tablas de origen en tiempo real.
     *
     * @return array{empresa:string,periodo:string,anio:int,mes:int,fecha_desde:string,
     *               fecha_hasta:string,iva_ventas:float,notas_credito:float,credito_tributario:float,
     *               notas_credito_compra:float,retenciones:float,a_pagar:float,saldo_favor:float,
     *               num_facturas_venta:int,fecha_limite:string}
     */
    public function getResumenPago(int $idEmpresa, string $anio, string $mes, bool $sincronizar = true, int $idUsuario = 0): array
    {
        $mes        = str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
        $fechaDesde = "{$anio}-{$mes}-01";
        $finMes     = date('Y-m-t', strtotime($fechaDesde));
        $hoy        = date('Y-m-d');
        // Para el mes en curso se corta hasta hoy ("acumulado hasta la fecha").
        $fechaHasta = ($finMes > $hoy && $fechaDesde <= $hoy) ? $hoy : $finMes;

        $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $ruc           = (string) ($empresa['ruc'] ?? '');
        $nombreEmpresa = trim((string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? ''));
        $ambiente      = (string) ($empresa['tipo_ambiente'] ?? '1');

        $comp = $this->repository->getResumenPagoDirecto($idEmpresa, $fechaDesde, $fechaHasta, $ambiente);

        $ivaVentas          = $comp['iva_ventas'];               // IVA facturas de venta autorizadas (bruto)
        $notasCredito       = $comp['iva_notas_credito'];        // NC de venta (resta del IVA en ventas)
        $credito            = $comp['iva_compras'];              // crédito tributario (bruto)
        $notasCreditoCompra = $comp['iva_notas_credito_compra']; // NC de compra (resta del crédito)
        $retenciones        = $comp['retenciones'];
        $numVentas          = $comp['num_ventas'];

        // La NC de compra reduce el crédito tributario → aumenta el valor a pagar (entra sumando).
        $neto       = $ivaVentas - $notasCredito - $credito + $notasCreditoCompra - $retenciones;
        $aPagar     = max(0.0, $neto);
        $saldoFavor = max(0.0, -$neto);

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
            'iva_ventas'           => round($ivaVentas, 2),
            'notas_credito'        => round($notasCredito, 2),
            'credito_tributario'   => round($credito, 2),
            'notas_credito_compra' => round($notasCreditoCompra, 2),
            'retenciones'          => round($retenciones, 2),
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

    // ==========================================================================
    // Declaración guardada: guardar/verificar duplicado, asiento contable y egreso
    // ==========================================================================

    private function rangoPeriodo(string $tipoPeriodo, int $anio, int $periodoValor): array
    {
        if ($tipoPeriodo === 'semestral') {
            return $periodoValor == 1
                ? ["{$anio}-01-01", "{$anio}-06-30"]
                : ["{$anio}-07-01", "{$anio}-12-31"];
        }
        $mesStr = str_pad((string) $periodoValor, 2, '0', STR_PAD_LEFT);
        $fechaDesde = "{$anio}-{$mesStr}-01";
        return [$fechaDesde, date('Y-m-t', strtotime($fechaDesde))];
    }

    private function periodoAnterior(string $tipoPeriodo, int $anio, int $periodoValor): array
    {
        if ($tipoPeriodo === 'semestral') {
            return $periodoValor <= 1 ? [$anio - 1, 2] : [$anio, 1];
        }
        return $periodoValor <= 1 ? [$anio - 1, 12] : [$anio, $periodoValor - 1];
    }

    /**
     * Descompone el arrastre de crédito tributario en sus dos orígenes (compras/adquisiciones
     * y retenciones), consumiendo el IVA en ventas en el MISMO orden que ya usa el cálculo
     * combinado de siempre (compras primero, retenciones después), para que el total
     * (615 + 617, o el a_pagar) coincida exactamente con el neto combinado tradicional.
     *
     * @return array{'615':float,'617':float,'a_pagar':float,'saldo_favor':float}
     */
    private function calcularSplitArrastre(float $ivaVentasNeto, float $creditoComprasNeto, float $retenciones, float $creditoAnteriorCompras, float $creditoAnteriorRetenciones): array
    {
        $disponibleCompras = round($creditoAnteriorCompras + $creditoComprasNeto, 2);
        $netoTrasCompras    = round($ivaVentasNeto - $disponibleCompras, 2);

        if ($netoTrasCompras <= 0) {
            // El crédito de compras por sí solo ya cubre el IVA en ventas: sobra para arrastrar,
            // y las retenciones del período (más lo que traía de antes) no se tocan, quedan enteras.
            $c615 = round(-$netoTrasCompras, 2);
            $c617 = round($creditoAnteriorRetenciones + $retenciones, 2);
            return ['615' => $c615, '617' => $c617, 'a_pagar' => 0.0, 'saldo_favor' => round($c615 + $c617, 2)];
        }

        // El crédito de compras se agotó (615 = 0); seguimos con las retenciones.
        $disponibleRetenciones = round($creditoAnteriorRetenciones + $retenciones, 2);
        $netoFinal = round($netoTrasCompras - $disponibleRetenciones, 2);

        if ($netoFinal <= 0) {
            $c617 = round(-$netoFinal, 2);
            return ['615' => 0.0, '617' => $c617, 'a_pagar' => 0.0, 'saldo_favor' => $c617];
        }

        return ['615' => 0.0, '617' => 0.0, 'a_pagar' => $netoFinal, 'saldo_favor' => 0.0];
    }

    private function etiquetaPeriodo(array $decl): string
    {
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $valor = (int) $decl['periodo_valor'];
        if (($decl['tipo_periodo'] ?? '') === 'semestral') {
            return ($valor === 1 ? 'Primer Semestre' : 'Segundo Semestre') . ' ' . $decl['periodo_anio'];
        }
        return ($meses[$valor] ?? (string) $valor) . ' ' . $decl['periodo_anio'];
    }

    private function ambienteEmpresa(int $idEmpresa): string
    {
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        return (string) ($empresa['tipo_ambiente'] ?? '1');
    }

    /**
     * Verifica si el período (año/tipo_periodo/periodo_valor) ya tiene una declaración
     * guardada, para avisar al usuario antes de que vuelva a declarar.
     */
    public function verificarDeclarado(int $idEmpresa, string $tipoPeriodo, int $anio, int $periodoValor): ?array
    {
        $ambiente = $this->ambienteEmpresa($idEmpresa);
        return $this->repository->findDeclaracion($idEmpresa, $ambiente, $tipoPeriodo, $anio, $periodoValor);
    }

    /**
     * Guarda (crea o actualiza) la declaración de un período: calcula los componentes,
     * aplica el arrastre automático del saldo a favor del período anterior, y persiste
     * un snapshot completo de los casilleros.
     */
    public function guardarDeclaracion(array $data): array
    {
        $idEmpresa    = (int) $data['id_empresa'];
        $idUsuario    = (int) $data['usuario_id'];
        $tipoPeriodo  = (string) $data['tipo_periodo'];
        $anio         = (int) $data['periodo_anio'];
        $periodoValor = (int) $data['periodo_valor'];

        $ambiente = $this->ambienteEmpresa($idEmpresa);
        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($tipoPeriodo, $anio, $periodoValor);

        $existente = $this->repository->findDeclaracion($idEmpresa, $ambiente, $tipoPeriodo, $anio, $periodoValor);
        $this->rules->validarGuardado($data, $existente);

        $comp = $this->repository->getResumenPagoDirecto($idEmpresa, $fechaDesde, $fechaHasta, $ambiente);
        $ivaVentas          = round((float) $comp['iva_ventas'], 2);
        $notasCreditoVenta  = round((float) $comp['iva_notas_credito'], 2);
        $creditoCompras     = round((float) $comp['iva_compras'], 2);
        $notasCreditoCompra = round((float) $comp['iva_notas_credito_compra'], 2);
        $retenciones        = round((float) $comp['retenciones'], 2);

        [$anioAnt, $periodoAnt] = $this->periodoAnterior($tipoPeriodo, $anio, $periodoValor);
        $declAnterior = $this->repository->getDeclaracionAnterior($idEmpresa, $ambiente, $tipoPeriodo, $anioAnt, $periodoAnt);
        // Entrante (casilleros 605/606): obligatorio = lo declarado como saliente (615/617) del período anterior.
        $creditoAnteriorCompras     = $declAnterior ? round((float) $declAnterior['saldo_favor_compras'], 2) : 0.0;
        $creditoAnteriorRetenciones = $declAnterior ? round((float) $declAnterior['saldo_favor_retenciones'], 2) : 0.0;
        $creditoAnteriorAplicado   = round($creditoAnteriorCompras + $creditoAnteriorRetenciones, 2);

        $ivaVentasNeto      = round($ivaVentas - $notasCreditoVenta, 2);
        $creditoComprasNeto = round($creditoCompras - $notasCreditoCompra, 2);
        $split = $this->calcularSplitArrastre($ivaVentasNeto, $creditoComprasNeto, $retenciones, $creditoAnteriorCompras, $creditoAnteriorRetenciones);

        // Saliente (casilleros 615/617): autocalculado, pero el usuario puede sobreescribirlo
        // desde el formulario antes de guardar (arrastre editable al período siguiente).
        $ajuste615 = (isset($data['ajuste_615']) && $data['ajuste_615'] !== '' && $data['ajuste_615'] !== null)
            ? round((float) $data['ajuste_615'], 2) : null;
        $ajuste617 = (isset($data['ajuste_617']) && $data['ajuste_617'] !== '' && $data['ajuste_617'] !== null)
            ? round((float) $data['ajuste_617'], 2) : null;

        $saldoFavorCompras     = $ajuste615 ?? $split['615'];
        $saldoFavorRetenciones = $ajuste617 ?? $split['617'];
        $saldoFavor = round($saldoFavorCompras + $saldoFavorRetenciones, 2);
        $aPagar     = round($split['a_pagar'], 2);

        $valoresCasilleros = $this->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta, $tipoPeriodo, $anio, $periodoValor)['valores'] ?? [];
        // Los valores efectivamente guardados (con el ajuste manual aplicado) mandan sobre
        // el default que haya calculado getResumenCompleto para el snapshot del formulario.
        $valoresCasilleros['605'] = $creditoAnteriorCompras;
        $valoresCasilleros['606'] = $creditoAnteriorRetenciones;
        $valoresCasilleros['615'] = $saldoFavorCompras;
        $valoresCasilleros['617'] = $saldoFavorRetenciones;

        $toSave = [
            'id_empresa'                   => $idEmpresa,
            'tipo_ambiente'                => $ambiente,
            'tipo_periodo'                 => $tipoPeriodo,
            'periodo_anio'                 => $anio,
            'periodo_valor'                => $periodoValor,
            'fecha_desde'                  => $fechaDesde,
            'fecha_hasta'                  => $fechaHasta,
            'iva_ventas'                   => $ivaVentas,
            'notas_credito_venta'          => $notasCreditoVenta,
            'credito_tributario_compras'   => $creditoCompras,
            'notas_credito_compra'         => $notasCreditoCompra,
            'retenciones_iva'              => $retenciones,
            'credito_anterior_aplicado'    => $creditoAnteriorAplicado,
            'credito_anterior_compras'     => $creditoAnteriorCompras,
            'credito_anterior_retenciones' => $creditoAnteriorRetenciones,
            'iva_a_pagar'                  => $aPagar,
            'saldo_favor'                  => $saldoFavor,
            'saldo_favor_compras'          => $saldoFavorCompras,
            'saldo_favor_retenciones'      => $saldoFavorRetenciones,
            'valores_casilleros'           => $valoresCasilleros,
            'estado'                       => $existente['estado'] ?? 'guardado',
            'observaciones'                => $data['observaciones'] ?? ($existente['observaciones'] ?? null),
            'usuario_id'                   => $idUsuario,
        ];

        if ($existente) {
            $id = (int) $existente['id'];
            $this->repository->updateDeclaracion($id, $idEmpresa, $toSave);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'declaracion_iva_cabecera', $id, $existente, $toSave);
        } else {
            $id = $this->repository->insertDeclaracion($toSave);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'declaracion_iva_cabecera', $id, null, $toSave);
        }

        return $this->repository->findDeclaracionById($id, $idEmpresa) ?? [];
    }

    /**
     * Genera (o regenera, sin duplicar) el asiento contable de la liquidación del IVA.
     */
    public function generarAsientoDeclaracion(int $idDeclaracion, int $idEmpresa, int $idUsuario): array
    {
        $decl = $this->repository->findDeclaracionById($idDeclaracion, $idEmpresa);
        if (!$decl) {
            throw new \Exception('Declaración no encontrada.');
        }

        $datos = [
            'iva_ventas_neto'           => round((float) $decl['iva_ventas'] - (float) $decl['notas_credito_venta'], 2),
            'credito_compras_neto'      => round((float) $decl['credito_tributario_compras'] - (float) $decl['notas_credito_compra'], 2),
            'retenciones'               => (float) $decl['retenciones_iva'],
            'iva_a_pagar'               => (float) $decl['iva_a_pagar'],
            'saldo_favor'               => (float) $decl['saldo_favor'],
            'credito_anterior_aplicado' => (float) $decl['credito_anterior_aplicado'],
        ];

        $builder  = new AsientoBuilderService();
        $detalles = $builder->generarAsientoDeclaracionIva($idEmpresa, $idDeclaracion, $datos);
        if (empty($detalles)) {
            throw new \Exception('No hay valores para generar el asiento de esta declaración.');
        }

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $previo    = $asientoService->getAsientoPorOrigen('declaracion_iva', $idDeclaracion, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $cabecera = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $decl['fecha_hasta'],
            'tipo_comprobante'     => 'declaracion_iva',
            'numero_comprobante'   => '',
            'concepto'             => 'Declaración de IVA ' . $this->etiquetaPeriodo($decl),
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'declaracion_iva',
            'id_referencia_origen' => $idDeclaracion,
            'observaciones'        => $decl['observaciones'] ?? null,
        ];

        $idGenerado = $asientoService->guardarAsiento($cabecera, $detalles, $idEmpresa, $idUsuario);
        $this->repository->marcarAsiento($idDeclaracion, $idEmpresa, $idGenerado, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_ASIENTO', 'declaracion_iva_cabecera', $idDeclaracion, null, ['id_asiento' => $idGenerado]);

        return ['id_asiento' => $idGenerado];
    }

    /**
     * Genera el egreso del IVA a pagar de la declaración, a nombre del proveedor y con el
     * concepto de egreso que elige el usuario. Reutiliza EgresoService::registrar (numeración,
     * validación de período y asiento contable propio del egreso), igual que RolEgresoLoteService.
     *
     * @param array $opts ['id_proveedor','id_egreso_concepto','id_forma_pago','id_punto_emision','fecha']
     */
    public function generarEgreso(int $idDeclaracion, int $idEmpresa, int $idUsuario, array $opts): int
    {
        $decl = $this->repository->findDeclaracionById($idDeclaracion, $idEmpresa);
        if (!$decl) {
            throw new \Exception('Declaración no encontrada.');
        }
        $this->rules->validarGenerarEgreso($decl);

        $idProveedor = (int) ($opts['id_proveedor'] ?? 0);
        $idConcepto  = (int) ($opts['id_egreso_concepto'] ?? 0);
        $idForma     = (int) ($opts['id_forma_pago'] ?? 0);
        $idPunto     = (int) ($opts['id_punto_emision'] ?? 0);
        $fecha       = !empty($opts['fecha']) ? $opts['fecha'] : date('Y-m-d');

        if ($idProveedor <= 0) throw new \Exception('Seleccione el proveedor a nombre de quien se emite el egreso.');
        if ($idConcepto <= 0) throw new \Exception('Seleccione el concepto de egreso.');
        if ($idForma <= 0) throw new \Exception('Seleccione la forma de pago.');
        if ($idPunto <= 0) throw new \Exception('Seleccione el punto de emisión.');

        // Operación bancaria (transferencia/débito/depósito/cheque), solo si la forma de pago es tipo BANCO.
        $tipoOp = strtoupper(trim((string) ($opts['tipo_operacion_bancaria'] ?? '')));
        $numeroCheque = trim((string) ($opts['numero_cheque'] ?? ''));
        $fechaCobro = trim((string) ($opts['fecha_cobro'] ?? ''));
        if ($tipoOp === 'CHEQUE' && $numeroCheque === '') {
            throw new \Exception('Ingrese el número de cheque.');
        }

        $db = \App\core\Database::getConnection();
        $stP = $db->prepare("SELECT e.codigo AS est, p.codigo_punto AS pto
                             FROM empresa_punto_emision p JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                             WHERE p.id = :idp");
        $stP->execute([':idp' => $idPunto]);
        $pRow = $stP->fetch(\PDO::FETCH_ASSOC);
        if (!$pRow) throw new \Exception('Punto de emisión no válido.');
        $est = str_pad((string) $pRow['est'], 3, '0', STR_PAD_LEFT);
        $pto = str_pad((string) $pRow['pto'], 3, '0', STR_PAD_LEFT);

        $secSvc = new \App\Services\SecuencialService();
        $sec    = (int) ($secSvc->obtenerSiguienteSecuencial($idPunto, 'Egresos')['secuencial'] ?? 0);
        $numero = $est . '-' . $pto . '-' . str_pad((string) $sec, 9, '0', STR_PAD_LEFT);

        $monto        = round((float) $decl['iva_a_pagar'], 2);
        $periodoLabel = $this->etiquetaPeriodo($decl);

        $egSvc = new \App\Services\modulos\EgresoService(
            new \App\repositories\modulos\EgresoRepository(),
            new \App\Rules\modulos\EgresoRules(),
            $this->logService
        );

        $idEgreso = $egSvc->registrar([
            'id_empresa'         => $idEmpresa,
            'usuario_id'         => $idUsuario,
            'id_punto_emision'   => $idPunto,
            'establecimiento'    => $est,
            'punto_emision'      => $pto,
            'secuencial'         => $sec,
            'numero_egreso'      => $numero,
            'fecha_emision'      => $fecha,
            'tipo_egreso'        => 'DECLARACION_IVA',
            'tipo_sujeto'        => 'PROVEEDOR',
            'id_proveedor'       => $idProveedor,
            'id_egreso_concepto' => $idConcepto,
            'monto_total'        => $monto,
            'observaciones'      => 'Pago Declaración de IVA ' . $periodoLabel,
            'detalles' => [[
                'tipo_documento'          => 'DECLARACION_IVA',
                'id_referencia_documento' => $idDeclaracion,
                'numero_documento'        => 'Declaración IVA ' . $periodoLabel,
                'monto_documento'         => $monto,
                'saldo_anterior'          => $monto,
                'monto_pagado'            => $monto,
                'saldo_actual'            => 0,
            ]],
            'pagos' => [$this->armarPagoEgreso($idForma, $monto, $tipoOp, $numeroCheque, $fechaCobro, $fecha)],
        ]);

        $this->repository->marcarEgreso($idDeclaracion, $idEmpresa, $idEgreso, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_EGRESO', 'declaracion_iva_cabecera', $idDeclaracion, null, ['id_egreso' => $idEgreso]);

        return $idEgreso;
    }

    private function armarPagoEgreso(int $idForma, float $monto, string $tipoOp, string $numeroCheque, string $fechaCobro, string $fechaEmision): array
    {
        $pago = ['id_forma_pago' => $idForma, 'monto' => $monto];
        if ($tipoOp !== '') {
            $pago['tipo_operacion_bancaria'] = $tipoOp;
            if ($tipoOp === 'CHEQUE') {
                $pago['numero_cheque'] = $numeroCheque;
                $pago['fecha_cobro']   = $fechaCobro !== '' ? $fechaCobro : $fechaEmision;
            }
        }
        return $pago;
    }
}
