<?php

declare(strict_types=1);

namespace App\services\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\Rules\modulos\DeclaracionIvaRules;
use App\Services\LogSistemaService;

/**
 * El Formulario 104 (IVA) se presenta por RUC completo, no por establecimiento: el CÁLCULO
 * (getResumenCompleto/getResumenPago/lo que se guarda en declaracion_iva_cabecera) siempre
 * consolida todas las filas de `empresas` del mismo RUC accesibles al usuario — ver
 * EmpresaRepository::getIdsGrupoRucAccesible(). El asiento contable y el egreso, en cambio, solo
 * pueden vivir en UNA empresa (plan de cuentas, puntos de emisión y período contable cerrado son
 * por-empresa): se generan siempre contra la empresa que ejecuta la acción (normalmente la
 * matriz), leyendo el snapshot YA consolidado que quedó guardado en declaracion_iva_cabecera —
 * por eso generarAsientoDeclaracion()/generarEgreso() no necesitaron cambios.
 */
class DeclaracionIvaService
{
    private $repository;
    private $fvRepository;
    private $rules;
    private $logService;
    private EmpresaRepository $empresaRepo;

    /**
     * Casilleros que calcula el sistema y no la configuración: el arrastre de crédito tributario
     * al mes siguiente. Sus fórmulas configuradas en /config/sri-casilleros-etiquetas se ignoran:
     * una fórmula no puede expresar el consumo en orden (compras primero, retenciones después)
     * ni sumar el saldo del mes anterior — las que había (615 = 602, 617 = (615/615)*609) perdían
     * crédito de un mes a otro.
     */
    public const CASILLEROS_SISTEMA = ['615', '617'];

    public function __construct(DeclaracionIvaRepository $repository, ?EmpresaRepository $empresaRepo = null)
    {
        $this->repository = $repository;
        $this->fvRepository = new FacturaVentaRepository();
        $this->rules = new DeclaracionIvaRules();
        $this->logService = new LogSistemaService();
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepository();
    }

    /** IDs de empresas del mismo RUC accesibles al usuario (incluida siempre la activa). */
    private function idsGrupo(int $idEmpresa, int $idUsuario): array
    {
        $ids = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        if (!in_array($idEmpresa, $ids, true)) {
            $ids[] = $idEmpresa;
        }
        return $ids;
    }

    /** getResumenPorCasilleros() de todo el grupo, sumado por casillero. */
    private function resumenPorCasillerosGrupo(array $idsGrupo, string $fechaDesde, string $fechaHasta): array
    {
        $acc = [];
        foreach ($idsGrupo as $idEmp) {
            foreach ($this->repository->getResumenPorCasilleros($idEmp, $fechaDesde, $fechaHasta) as $row) {
                $acc[$row['casillero']] = ($acc[$row['casillero']] ?? 0.0) + (float) $row['total'];
            }
        }
        $out = [];
        foreach ($acc as $casillero => $total) {
            $out[] = ['casillero' => $casillero, 'total' => $total];
        }
        return $out;
    }

    /** getConteoDocumentos() sumado en todo el grupo. */
    private function conteoDocumentosGrupo(array $idsGrupo, string $fuente, string $fechaDesde, string $fechaHasta): int
    {
        $total = 0;
        foreach ($idsGrupo as $idEmp) {
            $total += (int) $this->repository->getConteoDocumentos($idEmp, $fuente, $fechaDesde, $fechaHasta);
        }
        return $total;
    }

    /** getTotalTransferenciasGravadas() sumado en todo el grupo. */
    private function totalTransferenciasGrupo(array $idsGrupo, string $fechaDesde, string $fechaHasta, string $ambiente): float
    {
        $total = 0.0;
        foreach ($idsGrupo as $idEmp) {
            $total += (float) $this->repository->getTotalTransferenciasGravadas($idEmp, $fechaDesde, $fechaHasta, $ambiente);
        }
        return $total;
    }

    /**
     * getDetalleDocumentos() de todo el grupo, unido y etiquetado con el establecimiento propio
     * de origen (para que el Excel de revisión muestre de cuál establecimiento viene cada fila,
     * ya que el resumen consolidado por sí solo no lo distingue).
     */
    public function detalleDocumentosGrupo(int $idEmpresa, string $fechaDesde, string $fechaHasta, int $idUsuario): array
    {
        $idsGrupo = $this->idsGrupo($idEmpresa, $idUsuario);
        $etiquetas = $this->empresaRepo->getEtiquetasEstablecimiento($idsGrupo);
        $out = [];
        foreach ($idsGrupo as $idEmp) {
            foreach ($this->repository->getDetalleDocumentos($idEmp, $fechaDesde, $fechaHasta) as $d) {
                $d['_id_empresa'] = $idEmp;
                $d['_establecimiento_propio'] = $etiquetas[$idEmp] ?? '';
                $out[] = $d;
            }
        }
        return $out;
    }

    /** getResumenPagoDirecto() sumado componente a componente en todo el grupo. */
    private function resumenPagoDirectoGrupo(array $idsGrupo, string $fechaDesde, string $fechaHasta, string $ambiente): array
    {
        $acc = [
            'iva_ventas' => 0.0, 'iva_notas_credito' => 0.0, 'iva_compras' => 0.0,
            'iva_notas_credito_compra' => 0.0, 'retenciones' => 0.0, 'num_ventas' => 0,
        ];
        foreach ($idsGrupo as $idEmp) {
            $c = $this->repository->getResumenPagoDirecto($idEmp, $fechaDesde, $fechaHasta, $ambiente);
            foreach ($acc as $k => $v) {
                $acc[$k] += (float) ($c[$k] ?? 0);
            }
        }
        $acc['num_ventas'] = (int) $acc['num_ventas'];
        return $acc;
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

        // 4.1 Notas de Débito (emitidas a clientes; aumentan el IVA en ventas)
        $notasDebito = $this->repository->getDocumentosPeriodo($idEmpresa, 'nota_debito_cabecera', $fechaDesde, $fechaHasta);
        $ndService = new NotaDebitoService(new \App\repositories\modulos\NotaDebitoRepository(), new \App\Rules\modulos\NotaDebitoRules(), new \App\services\LogSistemaService());
        foreach ($notasDebito as $nd) {
            $ndService->sincronizarCasilleros((int)$nd['id'], null);
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

    /** Igual que sincronizarPeriodo() pero para todas las empresas del grupo RUC accesible al usuario. */
    public function sincronizarPeriodoGrupo(int $idEmpresa, string $anio, string $mes, int $idUsuario): array
    {
        foreach ($this->idsGrupo($idEmpresa, $idUsuario) as $idEmp) {
            $this->sincronizarPeriodo($idEmp, $anio, $mes, $idUsuario);
        }
        return ['ok' => true, 'mensaje' => 'Sincronización completa finalizada.'];
    }

    /**
     * Genera el resumen final del periodo, agrupando por casilleros,
     * limitando a 0 (para que no existan valores negativos), y 
     * resolviendo las fórmulas matemáticas.
     */
    /**
     * @param bool $respetarGuardado Si true (default), los casilleros de arrastre/ajuste
     *             (605/606/615/617/480/481/483/484/485/486/902) se fijan al valor ya guardado
     *             cuando el período tiene una declaración (para no pisar un ajuste manual) — lo
     *             usan el Excel/PDF y la carga inicial. Si false, esos casilleros se recalculan
     *             siempre desde cero: lo usa el botón "GENERAR"/"Recalcular desde documentos" de
     *             la vista, para que sea una reconstrucción real y no un espejo de lo guardado.
     * @param array $ajustes Valores de los casilleros editables tal como están AHORA en el
     *             formulario del navegador (615/617/481/484/486/902), en el mismo formato que
     *             recibe guardarDeclaracion(): ['615' => '12.34', ...]. Las claves vacías o
     *             ausentes se ignoran. Se aplican ANTES de resolver las fórmulas, para que los
     *             casilleros derivados (480, 485, 499...) salgan coherentes. Sirve para que el
     *             Excel exporte exactamente lo que el usuario está viendo, aunque todavía no
     *             haya guardado la declaración.
     */
    public function getResumenCompleto(int $idEmpresa, string $fechaDesde, string $fechaHasta, string $tipoPeriodo = '', int $anio = 0, int $periodoValor = 0, int $idUsuario = 0, bool $respetarGuardado = true, array $ajustes = []): array
    {
        // El F104 se presenta por RUC completo: se consolidan todas las empresas del grupo
        // accesibles al usuario (ver EmpresaRepository::getIdsGrupoRucAccesible()). El arrastre
        // (605/606/615/617, más abajo) es la única excepción: sigue anclado a $idEmpresa, la
        // empresa que ejecuta/guarda la declaración — ver comentario de clase.
        $idsGrupo = $this->idsGrupo($idEmpresa, $idUsuario);

        // 1. Obtener sumatorias desde base de datos (se mantienen los agrupados por código '401', etc)
        $rawSums = $this->resumenPorCasillerosGrupo($idsGrupo, $fechaDesde, $fechaHasta);
        $sums = [];
        $casillerosNegativos = [];
        foreach ($rawSums as $row) {
            // Aplicar MAX(0, valor) para casilleros directos (facturas - notas de crédito)
            $total = (float) $row['total'];
            if ($total < -0.005) {
                $casillerosNegativos[(string) $row['casillero']] = round($total, 2);
            }
            $sums[$row['casillero']] = max(0, $total);
        }

        // 2. Obtener estructura oficial (ahora por filas de 7 columnas)
        $estructura = $this->repository->getEstructuraFormulario();

        // 2-bis. Avisos de notas de crédito que no restan donde deberían (ver avisosNotasCredito()).
        $avisosNotasCredito = $this->avisosNotasCredito($idsGrupo, $fechaDesde, $fechaHasta, $casillerosNegativos, $estructura);

        // 2b. Casilleros de conteo: filas cuya fuente es un conteo de documentos
        // del período (configurado en la estructura con fuente_valor)
        foreach ($estructura as $e) {
            $fuente = $e['fuente_valor'] ?? 'documentos';
            if ($fuente !== '' && $fuente !== null && $fuente !== 'documentos' && !str_starts_with($fuente, 'arrastre_')) {
                $casillero = $e['casillero_bruto'] ?: ($e['casillero_neto'] ?: $e['casillero_impuesto']);
                if ($casillero) {
                    $sums[$casillero] = (float) $this->conteoDocumentosGrupo($idsGrupo, $fuente, $fechaDesde, $fechaHasta);
                }
            }
        }

        // 2c. Casilleros de arrastre de crédito tributario (605/606 entrante, 615/617 saliente).
        // Solo se calculan si se recibió el contexto del período (tipo_periodo/anio/periodo_valor);
        // sin eso (llamadas antiguas) simplemente no se pintan estos casilleros.
        $declActual = null; // declaración ya guardada de este período, si la hay (ver 2c y 2d-bis)
        $tieneContexto = ($tipoPeriodo !== '' && $anio > 0 && $periodoValor > 0);
        $usaDiferida = $this->usaLiquidacionDiferida($idEmpresa);
        if ($tieneContexto) {
            $ambiente = $this->ambienteEmpresa($idEmpresa);
            [$anioAnt, $periodoAnt] = $this->periodoAnterior($tipoPeriodo, $anio, $periodoValor);
            $declAnterior = $this->repository->getDeclaracionAnterior($idEmpresa, $ambiente, $tipoPeriodo, $anioAnt, $periodoAnt);
            $sums['605'] = $declAnterior ? round((float) $declAnterior['saldo_favor_compras'], 2) : 0.0;
            $sums['606'] = $declAnterior ? round((float) $declAnterior['saldo_favor_retenciones'], 2) : 0.0;

            // Si el período ya tiene una declaración guardada, se respeta el valor guardado
            // (pudo haber sido ajustado manualmente) en vez de recalcular el default y pisarlo
            // — salvo que el llamador pida explícitamente reconstruir desde cero (ver docblock).
            // Sin declaración guardada, 615/617 (y el 902 si no tiene fórmula) se calculan en el
            // paso 4, DESPUÉS de las fórmulas: dependen del 499 y el 564, que son fórmulas.
            $declActual = $respetarGuardado
                ? $this->repository->findDeclaracion($idEmpresa, $ambiente, $tipoPeriodo, $anio, $periodoValor)
                : null;
            if ($declActual) {
                $sums['615'] = round((float) $declActual['saldo_favor_compras'], 2);
                $sums['617'] = round((float) $declActual['saldo_favor_retenciones'], 2);
                // 902 respeta el ajuste manual guardado, igual que 615/617 (ver comentario arriba).
                $sums['902'] = round((float) $declActual['total_a_pagar'], 2);
            }

            // 2d. Liquidación diferida de IVA por ventas a plazo (480/481/483/484/486 —
            // 482, 485 y 499 se resuelven solos con el motor de fórmulas de más abajo, y el 484
            // también cuando la empresa no usa liquidación diferida: ver formulasSistema()).
            $totalTransferencias = $this->totalTransferenciasGrupo($idsGrupo, $fechaDesde, $fechaHasta, $ambiente);
            $sums['483'] = $declAnterior ? round((float) $declAnterior['liquidacion_diferida_485'], 2) : 0.0;
            if ($declActual) {
                $sums['481'] = round((float) $declActual['transferencias_credito'], 2);
                $sums['484'] = round((float) $declActual['liquidacion_diferida_484'], 2);
                $sums['486'] = (float) $declActual['mes_pago_credito'];
            } else {
                $sums['481'] = 0.0;
                $sums['484'] = 0.0;
                $sums['486'] = 0.0;
            }
            $sums['480'] = round($totalTransferencias - $sums['481'], 2);
        }

        // 2d-bis. Casilleros editables SIN columna propia en declaracion_iva_cabecera (los
        // ajustes del resumen impositivo 610-614/622/623, la imputación al pago 898…): su valor
        // guardado vive dentro del snapshot valores_casilleros. Se restaura aquí para que al
        // reabrir un período declarado se vea lo mismo que se guardó.
        if ($respetarGuardado && !empty($declActual['valores_casilleros'])) {
            $snapshot = is_array($declActual['valores_casilleros'])
                ? $declActual['valores_casilleros']
                : (json_decode((string) $declActual['valores_casilleros'], true) ?: []);
            foreach ($estructura as $e) {
                if (empty($e['editable'])) {
                    continue;
                }
                foreach (['casillero_bruto', 'casillero_neto', 'casillero_impuesto'] as $campo) {
                    $codigo = trim((string) ($e[$campo] ?? ''));
                    if ($codigo !== '' && array_key_exists($codigo, $snapshot)) {
                        $sums[$codigo] = round((float) $snapshot[$codigo], 2);
                    }
                }
            }
        }

        // 2e. Ajustes manuales del formulario abierto en el navegador. Se aplican después de
        // calcular los defaults y ANTES del motor de fórmulas (paso 3), para que 482/485/499 y
        // demás derivados salgan con el mismo resultado que muestra la pantalla. Vale cualquier
        // casillero editable, no solo los seis que tienen columna propia.
        foreach ($ajustes as $codigo => $v) {
            if ($v === null || $v === '' || !preg_match('/^\d{3}$/', (string) $codigo)) {
                continue;
            }
            $sums[(string) $codigo] = round((float) $v, 2);
        }
        if (isset($ajustes['481']) && $ajustes['481'] !== '' && isset($totalTransferencias)) {
            // 480 (contado) siempre es el complemento de 481 (crédito) sobre el mismo total.
            $sums['480'] = round($totalTransferencias - $sums['481'], 2);
        }

        // 3. Extraer fórmulas y casilleros de la estructura matricial.
        // Una fórmula solo se aplica si su MISMA columna tiene casillero: escribirla en la
        // columna Bruto de una fila cuyo casillero vive en Impuesto la dejaba sin efecto y sin
        // ningún aviso ("el casillero está configurado para sumar pero sale en blanco"). Ahora
        // esos casos se recogen en $avisosFormulas y se devuelven a la vista y al Excel.
        $formulas = [];
        $avisosFormulas = [];
        $columnas = [
            'Bruto'    => ['casillero_bruto', 'formula_bruto'],
            'Neto'     => ['casillero_neto', 'formula_neto'],
            'Impuesto' => ['casillero_impuesto', 'formula_impuesto'],
        ];
        foreach ($estructura as $e) {
            $esTitulo = (($e['tipo'] ?? 'valor') === 'titulo');
            foreach ($columnas as $nombreCol => [$campoCas, $campoFormula]) {
                $casillero = trim((string) ($e[$campoCas] ?? ''));
                $formula   = trim((string) ($e[$campoFormula] ?? ''));

                if ($casillero !== '') {
                    if (!isset($sums[$casillero])) {
                        $sums[$casillero] = 0.0;
                    }
                    if ($formula !== '') {
                        $formulas[$casillero] = $formula;
                        // Las filas 'titulo' se pintan como un texto a todo lo ancho, sin
                        // columnas de valor: el resultado se calcula pero no se ve en ningún lado.
                        if ($esTitulo) {
                            $avisosFormulas[] = [
                                'casillero'   => $casillero,
                                'descripcion' => (string) ($e['descripcion'] ?? ''),
                                'formula'     => $formula,
                                'motivo'      => 'La fila es de tipo "título": se calcula pero no se muestra. Cámbiela a tipo "valor".',
                            ];
                        }
                    }
                } elseif ($formula !== '') {
                    $avisosFormulas[] = [
                        'casillero'   => '',
                        'descripcion' => (string) ($e['descripcion'] ?? ''),
                        'formula'     => $formula,
                        'motivo'      => 'La fórmula está en la columna ' . $nombreCol . ', que no tiene casillero asignado: no se aplica a ningún campo.',
                    ];
                }
            }
        }

        // 3b. Casilleros que calcula el sistema y no la configuración (ver formulasSistema()):
        // 615/617 salen del paso 4 (sus fórmulas configuradas se ignoran), y se agregan las
        // fórmulas por defecto (429/564 si no están configuradas, 484 = 482 sin liquidación diferida…).
        $hayAjuste = fn(string $c): bool => isset($ajustes[$c]) && $ajustes[$c] !== null && $ajustes[$c] !== '';
        $fijado484 = $declActual !== null || $hayAjuste('484');
        $configurada902 = isset($formulas['902']);
        $configurada620 = isset($formulas['620']);
        foreach (self::CASILLEROS_SISTEMA as $c) {
            unset($formulas[$c]);
        }
        $formulasSistema = $this->formulasSistema($estructura, $formulas, $usaDiferida, $fijado484);
        $formulas = array_replace($formulas, $formulasSistema);

        $sums = $this->ejecutarFormulas($formulas, $sums, $avisosFormulas);

        // 4. Arrastre de crédito tributario (615/617) y valor a pagar. Se consume el crédito en
        // el orden del F104: 601 = 499 − 564; primero el crédito de compras (605 + 602), después
        // el de retenciones (606 + 609). Lo que sobra de cada bolsa pasa al mes siguiente.
        $arrastreFijo = !$tieneContexto || $declActual !== null;
        $ivaAPagar = 0.0;
        if ($tieneContexto) {
            $split = $this->calcularSplitArrastre(
                round((float) ($sums['499'] ?? 0), 2),
                round((float) ($sums['564'] ?? 0), 2),
                round((float) ($sums['609'] ?? 0), 2),
                round((float) ($sums['605'] ?? 0), 2),
                round((float) ($sums['606'] ?? 0), 2)
            );
            if (!$arrastreFijo) {
                if (!$hayAjuste('615')) $sums['615'] = $split['615'];
                if (!$hayAjuste('617')) $sums['617'] = $split['617'];
                // Sin fórmula configurada en el 902, el total a pagar es el neto calculado.
                if (!$configurada902 && !$hayAjuste('902')) $sums['902'] = $split['a_pagar'];
                $sinAvisos = [];
                $sums = $this->ejecutarFormulas($formulas, $sums, $sinAvisos);
            }
            // IVA propio a pagar (sin las retenciones efectuadas como agente): el 620 si está
            // configurado (incluye los ajustes 610-614/622/623), si no el neto calculado.
            $ivaAPagar = $configurada620 ? round((float) ($sums['620'] ?? 0), 2) : $split['a_pagar'];
        }

        // Sin liquidación diferida el 484 lo fija el sistema (= 482): no se ofrece como editable.
        if (!$usaDiferida) {
            $estructura = $this->bloquearCasillero($estructura, '484');
        }

        // 5. Formatear la respuesta final retornando la estructura Y los valores por separado
        // para que la interfaz dibuje las 7 columnas
        return [
            'layout' => $estructura,
            'valores' => $sums,
            // Lo que el navegador necesita para recalcular en vivo igual que aquí (ver
            // recalcularFormulasJS() en la vista).
            'motor' => [
                'formulas_sistema'         => $formulasSistema,
                'casilleros_sistema'       => self::CASILLEROS_SISTEMA,
                'arrastre_fijo'            => $arrastreFijo,
                'fallback_902'             => !$configurada902,
                'usa_liquidacion_diferida' => $usaDiferida,
            ],
            'iva_a_pagar' => $ivaAPagar,
            // Total fijo que 480 (contado) + 481 (crédito) deben sumar: al editar 481 en el
            // navegador, 480 se recalcula como total_480_481 - 481 (ver punto 3 del plan).
            'total_480_481' => $totalTransferencias ?? 0.0,
            // Fórmulas configuradas que no llegan a verse en el formulario (ver paso 3).
            'avisos_formulas' => $avisosFormulas,
            // Notas de crédito que no restan donde deberían (ver paso 2-bis).
            'avisos_notas_credito' => $avisosNotasCredito,
        ];
    }

    /**
     * Avisos de notas de crédito de venta que no restan en el casillero que corresponde:
     *
     *  - 'tarifa_distinta': la NC se emitió con una tarifa de IVA que la factura no tiene (p. ej.
     *    factura al 0 % → 403/413 y NC como "No objeto" → 441). La NC resta en los casilleros de
     *    su propia tarifa, así que el casillero de la venta queda sin rebajar.
     *  - 'casillero_negativo': un casillero sumó negativo (las NC superan a las ventas o
     *    compras de esa tarifa) y el formulario lo muestra en cero: ese valor no se descuenta en ningún lado.
     *
     * @param array<string,float> $casillerosNegativos casillero => suma negativa del período
     */
    private function avisosNotasCredito(array $idsGrupo, string $fechaDesde, string $fechaHasta, array $casillerosNegativos, array $estructura): array
    {
        $avisos = [];

        foreach ($idsGrupo as $idEmp) {
            foreach ($this->repository->getNotasCreditoTarifaDistinta($idEmp, $fechaDesde, $fechaHasta) as $r) {
                $avisos[] = [
                    'tipo'            => 'tarifa_distinta',
                    'nota_credito'    => (string) $r['nota_credito'],
                    'factura'         => (string) $r['factura'],
                    'tarifa_nc'       => (string) $r['tarifa_nc'],
                    'tarifas_factura' => (string) $r['tarifas_factura'],
                    'base'            => round((float) $r['base'], 2),
                    'motivo'          => sprintf(
                        'La nota de crédito %s lleva %s, pero la factura %s vendió con %s. Resta en los casilleros de %s, no en los de la venta: el casillero de la factura queda sin rebajar (base %s).',
                        $r['nota_credito'], $r['tarifa_nc'], $r['factura'], $r['tarifas_factura'], $r['tarifa_nc'],
                        number_format((float) $r['base'], 2, '.', ',')
                    ),
                ];
            }
        }

        if ($casillerosNegativos) {
            $descripciones = [];
            foreach ($estructura as $e) {
                foreach (['casillero_bruto', 'casillero_neto', 'casillero_impuesto'] as $campo) {
                    $codigo = trim((string) ($e[$campo] ?? ''));
                    if ($codigo !== '' && !isset($descripciones[$codigo])) {
                        $descripciones[$codigo] = (string) ($e['descripcion'] ?? '');
                    }
                }
            }
            ksort($casillerosNegativos, SORT_STRING);
            foreach ($casillerosNegativos as $casillero => $total) {
                $avisos[] = [
                    'tipo'        => 'casillero_negativo',
                    'casillero'   => (string) $casillero,
                    'descripcion' => $descripciones[$casillero] ?? '',
                    'valor'       => $total,
                    'motivo'      => sprintf(
                        'Las notas de crédito superan a los documentos de este casillero (suma %s). El formulario lo muestra en 0 y esa diferencia no se descuenta en ningún casillero.',
                        number_format($total, 2, '.', ',')
                    ),
                ];
            }
        }

        return $avisos;
    }

    /**
     * Calcula el resumen del IVA a pagar de un período (pensado para avisos/automatizaciones).
     *
     * Usa el mismo cálculo que la declaración (getResumenCompleto, período mensual): IVA en
     * ventas (429), crédito tributario aplicable (564), crédito del mes anterior (605/606),
     * retenciones que le hicieron (609), retenciones efectuadas como agente (801) y el total
     * a pagar (902). Antes tenía una fórmula propia que no descontaba el crédito del mes
     * anterior ni sumaba las retenciones efectuadas, y el aviso no coincidía con la declaración.
     *
     * El parámetro $sincronizar se mantiene por compatibilidad pero no se usa.
     *
     * @return array{empresa:string,periodo:string,anio:int,mes:int,fecha_desde:string,
     *               fecha_hasta:string,iva_ventas:float,notas_credito:float,credito_tributario:float,
     *               notas_credito_compra:float,credito_anterior:float,retenciones:float,
     *               retenciones_efectuadas:float,a_pagar:float,saldo_favor:float,
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

        // Mismo cálculo que la declaración (Resumen 104): así el aviso dice lo mismo que se va a
        // declarar — incluye el crédito del mes anterior, el factor de proporcionalidad, las
        // Liquidaciones de Compra/Importaciones y las retenciones efectuadas como agente.
        // Consolidado por RUC, igual que la declaración.
        $resumen = $this->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta, 'mensual', (int) $anio, (int) $mes, $idUsuario);
        $v = fn(string $codigo): float => round((float) ($resumen['valores'][$codigo] ?? 0), 2);

        // Solo para el aviso de "no hubo facturas de venta en el mes".
        $comp = $this->resumenPagoDirectoGrupo($this->idsGrupo($idEmpresa, $idUsuario), $fechaDesde, $fechaHasta, $ambiente);
        $numVentas = $comp['num_ventas'];

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
            // 429 y 564 ya vienen netos de notas de crédito: las dos claves de NC quedan en 0 (se
            // mantienen porque las plantillas de correo pueden usarlas).
            'iva_ventas'             => $v('429'),
            'notas_credito'          => 0.0,
            'credito_tributario'     => $v('564'),
            'notas_credito_compra'   => 0.0,
            'credito_anterior'       => round($v('605') + $v('606'), 2),
            'retenciones'            => $v('609'),
            'retenciones_efectuadas' => $v('801'),
            'a_pagar'                => $v('902'),
            'saldo_favor'            => round($v('615') + $v('617'), 2),
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
    /**
     * Corre el motor de fórmulas hasta que los valores dejen de moverse.
     *
     * Varias pasadas porque una fórmula puede depender de otra (485 usa 482, que a su vez sale
     * de 429); se corta apenas no hay cambios. Los avisos solo se acumulan en la primera pasada,
     * para no repetir el mismo problema una vez por vuelta.
     *
     * @param array<string,string> $formulas casillero destino => fórmula
     * @param array<string,float>  $sums     valores actuales
     * @param array                $avisos   se le agregan las fórmulas que no se pudieron aplicar
     * @return array<string,float>
     */
    private function ejecutarFormulas(array $formulas, array $sums, array &$avisos = []): array
    {
        // Hasta 10 pasadas: las fórmulas se evalúan en el orden de la estructura, no en el de
        // sus dependencias (429 → 482 → 484 → 499 → 601 → 620 → 699 → 859 → 902…). El loop
        // termina en cuanto una pasada no cambia nada.
        for ($pasada = 0; $pasada < 10; $pasada++) {
            $cambio = false;
            foreach ($formulas as $casilleroObj => $formulaStr) {
                $desconocidos = [];
                $errorFormula = null;
                $resultado = $this->resolverFormula($formulaStr, $sums, $desconocidos, $errorFormula);

                if ($resultado === null) {
                    if ($pasada === 0) {
                        $avisos[] = [
                            'casillero'   => (string) $casilleroObj,
                            'descripcion' => '',
                            'formula'     => $formulaStr,
                            'motivo'      => 'No se pudo calcular. ' . ($errorFormula ?? 'Revise la sintaxis: solo códigos de casillero y + - * / paréntesis.'),
                        ];
                    }
                    continue;
                }
                if ($desconocidos && $pasada === 0) {
                    $avisos[] = [
                        'casillero'   => (string) $casilleroObj,
                        'descripcion' => '',
                        'formula'     => $formulaStr,
                        'motivo'      => 'Usa casilleros que no existen en la estructura (' . implode(', ', $desconocidos) . '): cuentan como 0.',
                    ];
                }

                $resultado = max(0, $resultado);
                if (abs(($sums[$casilleroObj] ?? 0.0) - $resultado) > 0.001) {
                    $sums[$casilleroObj] = $resultado;
                    $cambio = true;
                }
            }
            if (!$cambio) break;
        }
        return $sums;
    }

    /**
     * Fórmulas configuradas en la estructura: casillero => fórmula.
     *
     * @return array<string,string>
     */
    private function formulasConfiguradas(array $estructura): array
    {
        $formulas = [];
        foreach ($estructura as $e) {
            foreach ([['casillero_bruto', 'formula_bruto'], ['casillero_neto', 'formula_neto'], ['casillero_impuesto', 'formula_impuesto']] as [$campoCas, $campoFormula]) {
                $casillero = trim((string) ($e[$campoCas] ?? ''));
                $formula   = trim((string) ($e[$campoFormula] ?? ''));
                if ($casillero !== '' && $formula !== '') {
                    $formulas[$casillero] = $formula;
                }
            }
        }
        return $formulas;
    }

    /**
     * Resuelve una fórmula de casilleros ("401+421") contra los valores actuales.
     *
     * Devuelve null si la fórmula no se puede evaluar, para poder avisarlo en vez de dejar un
     * 0 mudo en el formulario. En $desconocidos deja los códigos que la fórmula menciona pero
     * que no existen en la estructura (se toman como 0).
     *
     * Tolera cómo escribe la gente las fórmulas en /config/sri-casilleros-etiquetas:
     * `401,421` y `401;421` (separadores de lista) se entienden como suma, y los adornos
     * (`=401+421`, `SUMA(401+421)`, `[401]+[421]`, `C401+C421`) se limpian ANTES de sustituir
     * los códigos — si se limpiaban después, un código pegado a una letra no se reconocía y
     * terminaba evaluándose como el número literal 401, dando totales absurdos.
     */
    private function resolverFormula(string $formula, array $sums, array &$desconocidos = [], ?string &$error = null): ?float
    {
        $desconocidos = [];
        $error = null;

        // Separadores de lista → suma.
        $expr = str_replace([',', ';'], '+', $formula);

        // Sanear primero: solo dígitos, operadores, punto decimal y paréntesis.
        // Los espacios SE CONSERVAN: son separadores. Si se borraran, "401 402" quedaría como el
        // número 401402 y la fórmula devolvería una barbaridad en silencio en vez de avisar que
        // faltan operadores.
        $expr = preg_replace('/[^0-9\+\-\*\/\.\(\)\s]/', ' ', $expr);
        if ($expr === null || trim($expr) === '') {
            $error = 'La fórmula quedó vacía después de quitar el texto que no es una operación.';
            return null;
        }

        // Sustituir cada código de 3 dígitos por su valor, entre paréntesis para que un valor
        // negativo no genere una expresión inválida ("401--5").
        $faltantes = [];
        $expr = preg_replace_callback('/\b(\d{3})\b/', function (array $m) use ($sums, &$faltantes): string {
            $codigo = $m[1];
            if (!array_key_exists($codigo, $sums)) {
                $faltantes[] = $codigo;
                return '(0)';
            }
            return '(' . (string) (float) $sums[$codigo] . ')';
        }, $expr);
        $desconocidos = array_values(array_unique($faltantes));

        return $this->evaluarMatematica((string) $expr, $error);
    }

    /**
     * Evalúa una expresión aritmética (+ - * / y paréntesis) sin usar eval().
     *
     * Dividir por cero da 0 en esa operación, no un error: en el F104 los cocientes son
     * factores de proporcionalidad (563 = ventas con derecho a crédito / total de ventas) o
     * interruptores del tipo (615/615)*609, y cuando el denominador es cero el resultado
     * correcto es cero, no "fórmula inválida". Antes esto reventaba con DivisionByZeroError y
     * se reportaba como un error de sintaxis inexistente.
     *
     * Devuelve null solo ante un error real de escritura, con el motivo en $error.
     */
    private function evaluarMatematica(string $expr, ?string &$error = null): ?float
    {
        $error = null;

        // Red de seguridad: la expresión ya viene saneada de resolverFormula(), pero este
        // método también protege a cualquier llamador futuro.
        $expr = preg_replace('/[^0-9\+\-\*\/\.\(\)\s]/', ' ', (string) $expr);
        if ($expr === null || trim($expr) === '') {
            $error = 'La fórmula está vacía.';
            return null;
        }

        // Tokenizar: números (con decimales), operadores y paréntesis. El espacio no es un
        // token, pero sí corta números: "401 402" son dos valores, no el número 401402.
        $tokens = [];
        $largo = strlen($expr);
        for ($i = 0; $i < $largo;) {
            $c = $expr[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if (ctype_digit($c) || $c === '.') {
                $numero = '';
                while ($i < $largo && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $numero .= $expr[$i];
                    $i++;
                }
                if (!is_numeric($numero)) {
                    $error = 'Número mal escrito en la fórmula: "' . $numero . '".';
                    return null;
                }
                $tokens[] = (float) $numero;
                continue;
            }
            $tokens[] = $c;
            $i++;
        }

        $pos = 0;
        $valor = $this->parseSuma($tokens, $pos, $error);
        if ($valor === null) {
            return null;
        }
        if ($pos !== count($tokens)) {
            $error = 'Sobra algo al final de la fórmula (revise los paréntesis y los operadores).';
            return null;
        }
        if (!is_finite($valor)) {
            $error = 'El resultado de la fórmula no es un número válido.';
            return null;
        }
        return $valor;
    }

    /** suma := producto (('+'|'-') producto)* */
    private function parseSuma(array $tokens, int &$pos, ?string &$error): ?float
    {
        $valor = $this->parseProducto($tokens, $pos, $error);
        if ($valor === null) return null;

        while ($pos < count($tokens) && ($tokens[$pos] === '+' || $tokens[$pos] === '-')) {
            $op = $tokens[$pos];
            $pos++;
            $derecha = $this->parseProducto($tokens, $pos, $error);
            if ($derecha === null) return null;
            $valor = ($op === '+') ? $valor + $derecha : $valor - $derecha;
        }
        return $valor;
    }

    /** producto := factor (('*'|'/') factor)* — dividir por cero da 0 (ver evaluarMatematica). */
    private function parseProducto(array $tokens, int &$pos, ?string &$error): ?float
    {
        $valor = $this->parseFactor($tokens, $pos, $error);
        if ($valor === null) return null;

        while ($pos < count($tokens) && ($tokens[$pos] === '*' || $tokens[$pos] === '/')) {
            $op = $tokens[$pos];
            $pos++;
            $derecha = $this->parseFactor($tokens, $pos, $error);
            if ($derecha === null) return null;
            if ($op === '*') {
                $valor = $valor * $derecha;
            } else {
                $valor = (abs($derecha) < 1e-12) ? 0.0 : $valor / $derecha;
            }
        }
        return $valor;
    }

    /** factor := ('+'|'-') factor | '(' suma ')' | número */
    private function parseFactor(array $tokens, int &$pos, ?string &$error): ?float
    {
        if ($pos >= count($tokens)) {
            $error = 'La fórmula termina esperando un valor (revise el último operador).';
            return null;
        }

        $t = $tokens[$pos];

        if ($t === '+' || $t === '-') {
            $pos++;
            $valor = $this->parseFactor($tokens, $pos, $error);
            if ($valor === null) return null;
            return ($t === '-') ? -$valor : $valor;
        }

        if ($t === '(') {
            $pos++;
            $valor = $this->parseSuma($tokens, $pos, $error);
            if ($valor === null) return null;
            if ($pos >= count($tokens) || $tokens[$pos] !== ')') {
                $error = 'Falta cerrar un paréntesis.';
                return null;
            }
            $pos++;
            return $valor;
        }

        if (is_float($t)) {
            $pos++;
            return $t;
        }

        $error = ($t === ')')
            ? 'Hay un paréntesis de cierre de más.'
            : 'Falta un valor junto al operador "' . $t . '".';
        return null;
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
     * Fórmulas que agrega el sistema a las configuradas, para que el valor a pagar no dependa
     * de que la configuración esté completa:
     *
     *  - 429 (total IVA en ventas) y 564 (crédito tributario aplicable): solo si NO tienen
     *    fórmula configurada, como respaldo; se arman sumando la columna Impuesto de las
     *    secciones '400'/'500' (nombres de la estructura antigua), sin repetir casilleros y
     *    sin los que no son IVA generado / crédito (522 sin derecho a crédito, notas de crédito
     *    por compensar, reembolsos, totales). Con la estructura vigente (secciones "Ventas" /
     *    "ADQUISICIONES", con 429 y 564 configurados) este respaldo no interviene.
     *  - 482 = 429, 485 = 482 − 484 y 499 = 483 + 484 si faltan (fórmulas oficiales del F104).
     *  - 484 = 482 cuando la empresa NO usa liquidación diferida (todo el IVA del mes se
     *    liquida en el mes). Antes quedaba en 0 y dejaba el 499 — y con él el 601 y el 902 — en
     *    cero: el IVA en ventas no llegaba al valor a pagar. Con liquidación diferida, 482 es
     *    solo el valor inicial mientras el usuario no haya fijado el suyo ($fijado484).
     *
     * @param array<string,string> $configuradas fórmulas de la estructura (casillero => fórmula)
     * @return array<string,string>
     */
    private function formulasSistema(array $estructura, array $configuradas, bool $usaDiferida, bool $fijado484): array
    {
        $f = [];
        if (!isset($configuradas['429'])) {
            $cods = $this->casillerosImpuestoSeccion($estructura, '400', ['429', '443', '453', '454']);
            if ($cods) $f['429'] = implode('+', $cods);
        }
        if (!isset($configuradas['564'])) {
            $cods = $this->casillerosImpuestoSeccion($estructura, '500', ['522', '529', '545', '554', '555', '563', '564', '565']);
            if ($cods) $f['564'] = implode('+', $cods);
        }
        $f += array_diff_key(['482' => '429', '485' => '482-484', '499' => '483+484'], $configuradas);
        if (!$usaDiferida || !$fijado484) {
            $f['484'] = '482';
        }
        return $f;
    }

    /** Casilleros de la columna Impuesto de una sección, sin repetir y sin los excluidos. */
    private function casillerosImpuestoSeccion(array $estructura, string $seccion, array $excluir): array
    {
        $cods = [];
        foreach ($estructura as $fila) {
            if (($fila['seccion'] ?? '') !== $seccion) continue;
            $cas = trim((string) ($fila['casillero_impuesto'] ?? ''));
            if ($cas === '' || in_array($cas, $excluir, true)) continue;
            $cods[$cas] = $cas;
        }
        return array_values($cods);
    }

    /** Marca como no editable toda fila que contenga el casillero (lo fija el sistema). */
    private function bloquearCasillero(array $estructura, string $casillero): array
    {
        foreach ($estructura as &$fila) {
            foreach (['casillero_bruto', 'casillero_neto', 'casillero_impuesto'] as $campo) {
                if (trim((string) ($fila[$campo] ?? '')) === $casillero) {
                    $fila['editable'] = false;
                }
            }
        }
        unset($fila);
        return $estructura;
    }

    private function usaLiquidacionDiferida(int $idEmpresa): bool
    {
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        return !empty($empresa['usa_liquidacion_diferida_iva']);
    }

    /**
     * Estructura y datos del motor de cálculo para pintar una declaración YA guardada desde su
     * snapshot (verificarDeclaradoAjax → "Cargar la guardada"): el arrastre queda fijo en lo
     * guardado, pero si el usuario edita un casillero el navegador recalcula igual que el servidor.
     */
    public function formularioDeclaracionGuardada(int $idEmpresa): array
    {
        $estructura = $this->repository->getEstructuraFormulario();
        $configuradas = $this->formulasConfiguradas($estructura);
        $usaDiferida = $this->usaLiquidacionDiferida($idEmpresa);
        if (!$usaDiferida) {
            $estructura = $this->bloquearCasillero($estructura, '484');
        }
        return [
            'layout' => $estructura,
            'motor'  => [
                'formulas_sistema'         => $this->formulasSistema($estructura, array_diff_key($configuradas, array_flip(self::CASILLEROS_SISTEMA)), $usaDiferida, true),
                'casilleros_sistema'       => self::CASILLEROS_SISTEMA,
                'arrastre_fijo'            => true,
                'fallback_902'             => !isset($configuradas['902']),
                'usa_liquidacion_diferida' => $usaDiferida,
            ],
        ];
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

        // Casilleros editables tal como están en el formulario (615/617 arrastre, 481/484/486
        // liquidación diferida, 902, ajustes 610-614/622/623, imputación 898…). Los de columna
        // propia que la pantalla no haya enviado conservan lo ya guardado, como antes.
        $ajustes = [];
        foreach ((array) ($data['ajustes'] ?? []) as $codigo => $valor) {
            if ($valor !== '' && $valor !== null && preg_match('/^\d{3}$/', (string) $codigo)) {
                $ajustes[(string) $codigo] = (string) $valor;
            }
        }
        foreach (['615', '617', '481', '484', '486', '902'] as $codigo) {
            $valor = $data['ajuste_' . $codigo] ?? null;
            if ($valor !== '' && $valor !== null) {
                $ajustes[$codigo] = (string) $valor;
            }
        }
        if ($existente) {
            $columnas = ['481' => 'transferencias_credito', '484' => 'liquidacion_diferida_484', '486' => 'mes_pago_credito'];
            foreach ($columnas as $codigo => $columna) {
                if (!isset($ajustes[$codigo]) && isset($existente[$columna])) {
                    $ajustes[$codigo] = (string) $existente[$columna];
                }
            }
        }

        // Una sola fuente de cálculo: lo que se guarda es EXACTAMENTE lo que arma el Resumen 104
        // (getResumenCompleto con los mismos ajustes que tiene la pantalla), sin un segundo
        // cálculo aparte que pueda desincronizarse. respetarGuardado=false: el arrastre 615/617
        // se recalcula desde el 499/564/605/606/609 salvo que la pantalla haya enviado su valor.
        // El F104 se presenta por RUC completo: getResumenCompleto ya consolida el grupo.
        $resumen = $this->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta, $tipoPeriodo, $anio, $periodoValor, $idUsuario, false, $ajustes);
        $valoresCasilleros = $resumen['valores'] ?? [];
        $v = fn(string $codigo): float => round((float) ($valoresCasilleros[$codigo] ?? 0), 2);

        $toSave = [
            'id_empresa'                   => $idEmpresa,
            'tipo_ambiente'                => $ambiente,
            'tipo_periodo'                 => $tipoPeriodo,
            'periodo_anio'                 => $anio,
            'periodo_valor'                => $periodoValor,
            'fecha_desde'                  => $fechaDesde,
            'fecha_hasta'                  => $fechaHasta,
            // 429 y 564 ya vienen netos de notas de crédito (comparten casillero con la factura,
            // signo negativo), así que las columnas de notas de crédito quedan en 0. El 564 es el
            // crédito APLICABLE: ya lleva el factor de proporcionalidad (563).
            'iva_ventas'                   => $v('429'),
            'notas_credito_venta'          => 0.0,
            'credito_tributario_compras'   => $v('564'),
            'notas_credito_compra'         => 0.0,
            'retenciones_iva'              => $v('609'),
            'credito_anterior_aplicado'    => round($v('605') + $v('606'), 2),
            'credito_anterior_compras'     => $v('605'),
            'credito_anterior_retenciones' => $v('606'),
            // IVA propio a pagar (620, o el neto calculado si no está configurado).
            'iva_a_pagar'                  => round((float) ($resumen['iva_a_pagar'] ?? 0), 2),
            // Casillero 902 "Total impuesto a pagar" (incluye las retenciones efectuadas como
            // agente de retención, 801): es el que usa el egreso.
            'total_a_pagar'                => $v('902'),
            'saldo_favor'                  => round($v('615') + $v('617'), 2),
            'saldo_favor_compras'          => $v('615'),
            'saldo_favor_retenciones'      => $v('617'),
            'transferencias_contado'       => $v('480'),
            'transferencias_credito'       => $v('481'),
            'mes_pago_credito'             => (int) $v('486'),
            'liquidacion_diferida_483'     => $v('483'),
            'liquidacion_diferida_484'     => $v('484'),
            'liquidacion_diferida_485'     => $v('485'),
            'liquidacion_diferida_499'     => $v('499'),
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
     * Reabre una declaración 'pagado' (cerrada, con asiento y egreso generados) para poder
     * recalcularla: anula el egreso (que a su vez anula su propio asiento de pago —
     * EgresoService::anular()) y el asiento de la declaración, y la regresa a 'guardado'.
     * Pedido explícito: la declaración queda bloqueada mientras está pagada, pero el usuario
     * puede reabrirla desde GENERAR — no tiene que ir manualmente a anular el egreso aparte.
     */
    public function reabrirDeclaracion(int $idDeclaracion, int $idEmpresa, int $idUsuario): array
    {
        $decl = $this->repository->findDeclaracionById($idDeclaracion, $idEmpresa);
        if (!$decl) {
            throw new \Exception('Declaración no encontrada.');
        }
        if (($decl['estado'] ?? '') !== 'pagado') {
            throw new \Exception('Esta declaración no está cerrada; no hace falta reabrirla.');
        }

        if (!empty($decl['id_egreso'])) {
            $egresoService = new EgresoService(
                new \App\repositories\modulos\EgresoRepository(),
                new \App\Rules\modulos\EgresoRules(),
                $this->logService
            );
            $egresoService->anular((int) $decl['id_egreso'], $idEmpresa, $idUsuario);
        }

        if (!empty($decl['id_asiento'])) {
            $asientoService = new AsientoContableService(
                new \App\repositories\modulos\AsientoContableRepository(),
                new \App\Rules\modulos\AsientoContableRules(),
                $this->logService
            );
            $asientoService->anular((int) $decl['id_asiento'], $idEmpresa, $idUsuario);
        }

        $this->repository->reabrir($idDeclaracion, $idEmpresa, $idUsuario);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'REABRIR', 'declaracion_iva_cabecera', $idDeclaracion,
            ['estado' => 'pagado', 'id_asiento' => $decl['id_asiento'], 'id_egreso' => $decl['id_egreso']],
            ['estado' => 'guardado', 'id_asiento' => null, 'id_egreso' => null]
        );

        return $this->repository->findDeclaracionById($idDeclaracion, $idEmpresa) ?? [];
    }

    /**
     * Genera (o regenera, sin duplicar) el asiento contable de la liquidación del IVA.
     */
    /**
     * Guarda como plantilla la cuenta elegida por casillero (upsert), para precargarla en el
     * próximo período si más adelante se vuelve a sugerir el asiento por casillero. Hoy el
     * modal se abre en blanco (ver vista), así que normalmente no hay nada que guardar aquí.
     */
    public function guardarPlantillaAsiento(int $idEmpresa, array $lineas, int $idUsuario): void
    {
        $plantillaRepo = new \App\repositories\modulos\DeclaracionAsientoPlantillaRepository();
        $plantillaRepo->guardarPlantilla($idEmpresa, 'iva', $lineas, $idUsuario);
    }

    /**
     * Vincula a la declaración el asiento que el usuario acaba de guardar en el modal estándar
     * de Asientos Contables (modulo_origen='declaracion_iva', id_referencia_origen=$idDeclaracion):
     * el modal no sabe nada de "declaraciones", así que este paso es el que actualiza
     * declaracion_iva_cabecera.id_asiento después del guardado. Si el asiento todavía es un
     * borrador (temporal, sin cuadrar — ver AsientoContableRules), solo se vincula el id para
     * que "Generar Asiento" lo reabra la próxima vez; la declaración recién pasa a
     * 'contabilizado' cuando el asiento ya está registrado (cuadrado).
     */
    public function vincularAsiento(int $idDeclaracion, int $idEmpresa, int $idUsuario): array
    {
        $asientoRepo = new \App\repositories\modulos\AsientoContableRepository();
        $asiento = $asientoRepo->getAsientoPorOrigen('declaracion_iva', $idDeclaracion, $idEmpresa);
        if (!$asiento) {
            throw new \Exception('No se encontró el asiento guardado para esta declaración.');
        }
        $idAsiento = (int) $asiento['id'];
        $detalle = $asientoRepo->getDetalleAsiento($idAsiento, $idEmpresa);
        $estadoAsiento = (string) ($detalle['estado'] ?? '');

        if ($estadoAsiento === 'contabilizado') {
            $this->repository->marcarAsiento($idDeclaracion, $idEmpresa, $idAsiento, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_ASIENTO', 'declaracion_iva_cabecera', $idDeclaracion, null, ['id_asiento' => $idAsiento]);
        } else {
            $this->repository->vincularAsientoSinEstado($idDeclaracion, $idEmpresa, $idAsiento, $idUsuario);
        }

        return [
            'id_asiento'      => $idAsiento,
            'estado_asiento'  => $estadoAsiento,
            'numero_comprobante' => $detalle['numero_comprobante'] ?? '',
        ];
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

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (EgresoService::registrar()): el lock de obtenerSiguienteSecuencial() se libera
        // solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
        $secSvc = new \App\Services\SecuencialService();
        $sec    = (int) ($secSvc->obtenerSiguienteSecuencial($idPunto, 'Egresos', $fecha)['secuencial'] ?? 0);
        $numero = $est . '-' . $pto . '-' . str_pad((string) $sec, 9, '0', STR_PAD_LEFT);

        // El monto del egreso lo ingresa el usuario en el modal (precargado con el casillero
        // 902 "Total impuesto a pagar" como sugerencia, pero editable): puede diferir del 902 si
        // ya hubo un abono previo u otro ajuste. Sin override, cae al 902/iva_a_pagar calculado
        // (declaraciones guardadas antes de este cambio no tienen total_a_pagar: cae a iva_a_pagar).
        $montoManual  = isset($opts['monto']) && $opts['monto'] !== '' ? round((float) $opts['monto'], 2) : null;
        $monto        = $montoManual ?? round((float) ($decl['total_a_pagar'] ?? $decl['iva_a_pagar']), 2);
        if ($monto <= 0.0) {
            throw new \Exception('El valor a pagar debe ser mayor a cero.');
        }
        $periodoLabel = $this->etiquetaPeriodo($decl);

        $egSvc = new \App\Services\modulos\EgresoService(
            new \App\repositories\modulos\EgresoRepository(),
            new \App\Rules\modulos\EgresoRules(),
            $this->logService
        );

        $payloadEgreso = [
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
        ];
        $idEgreso = $egSvc->registrar($payloadEgreso);

        $this->repository->marcarEgreso($idDeclaracion, $idEmpresa, $idEgreso, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_EGRESO', 'declaracion_iva_cabecera', $idDeclaracion, null, ['id_egreso' => $idEgreso]);

        if ($managedTransaction) {
            $db->commit();
            // La transacción es nuestra: el asiento se genera después del COMMIT (ver EgresoService::registrar).
            $egSvc->tareasPostCommit($idEgreso, $payloadEgreso);
        }

        // Recordar el proveedor elegido como sugerencia para el próximo egreso de este tipo de
        // declaración (no debe tumbar el egreso ya generado si falla por cualquier motivo).
        try {
            $prefSvc = new \App\Services\UsuarioPreferenciaService(new \App\repositories\UsuarioPreferenciaRepository());
            $prefSvc->guardarPreferencia($idUsuario, $idEmpresa, 'declaracion_iva', 'id_proveedor_egreso_default', $idProveedor);
        } catch (\Throwable $e) {
            // no crítico
        }

        return $idEgreso;
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
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
