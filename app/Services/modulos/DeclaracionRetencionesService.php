<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DeclaracionRetencionesRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\RetencionCompraRepository;
use App\Rules\modulos\DeclaracionRetencionesRules;
use App\Services\LogSistemaService;

/**
 * Formulario 103 SRI — Declaración de Retenciones en la Fuente del Impuesto a la Renta.
 *
 * Fuente de datos:
 *  - Casilleros 303-346 / 402-433 (por pagos efectuados a proveedores): líneas de
 *    retencion_compra_detalle con codigo_impuesto = Renta, mapeadas a su casillero
 *    vía retenciones_sri.casillero_base/casillero_valor.
 *  - Casillero 302/352 (relación de dependencia): pendiente del motor de Impuesto a
 *    la Renta de empleados (Fase 2). Hasta entonces queda en 0 y es editable
 *    manualmente en la pestaña de detalle, igual que cualquier otro casillero.
 *
 * El F103 se presenta por RUC completo, no por establecimiento: el CÁLCULO (getResumenCompleto,
 * lo que se guarda en declaracion_retenciones_cabecera, y el asiento contable) siempre consolida
 * todas las empresas del grupo RUC accesibles al usuario — ver
 * EmpresaRepository::getIdsGrupoRucAccesible(). El asiento y el egreso solo pueden vivir en UNA
 * empresa (plan de cuentas/puntos de emisión/período contable son por-empresa): se generan
 * siempre contra la empresa que ejecuta la acción (normalmente la matriz).
 */
class DeclaracionRetencionesService
{
    private DeclaracionRetencionesRepository $repository;
    private RetencionCompraRepository $retCompraRepo;
    private DeclaracionRetencionesRules $rules;
    private LogSistemaService $logService;
    private EmpresaRepository $empresaRepo;

    public function __construct(DeclaracionRetencionesRepository $repository, ?EmpresaRepository $empresaRepo = null)
    {
        $this->repository = $repository;
        $this->retCompraRepo = new RetencionCompraRepository();
        $this->rules = new DeclaracionRetencionesRules();
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

    /** Sincroniza (recalcula) los casilleros del Formulario 103 para un período mensual. */
    public function sincronizarPeriodo(int $idEmpresa, string $anio, string $mes, int $idUsuario): array
    {
        $mes = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        $fechaDesde = "{$anio}-{$mes}-01";
        $fechaHasta = date('Y-m-t', strtotime($fechaDesde));

        $this->repository->limpiarCasillerosHuerfanos($idEmpresa, $fechaDesde, $fechaHasta);

        $retenciones = $this->repository->getRetencionesCompraPeriodo($idEmpresa, $fechaDesde, $fechaHasta);
        foreach ($retenciones as $r) {
            $this->sincronizarRetencionCompra((int) $r['id'], $idEmpresa);
        }

        $this->sincronizarEmpleadosIr($idEmpresa, $fechaDesde, $fechaHasta);

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

    /** Recalcula los casilleros (303-346 / 402-433) que genera un comprobante de retención de compra. */
    private function sincronizarRetencionCompra(int $idRetencion, int $idEmpresa): void
    {
        $this->repository->limpiarCasillerosDocumento($idEmpresa, 'retenciones_compras_renta', $idRetencion);

        $cabecera = $this->retCompraRepo->getPorId($idRetencion, $idEmpresa);
        if (!$cabecera) return;

        $lineas = $this->retCompraRepo->getDetalle($idRetencion);
        $fecha = $cabecera['fecha_emision'] ?? date('Y-m-d');

        $acumBase = [];
        $acumValor = [];

        foreach ($lineas as $linea) {
            $codImp = strtoupper((string) ($linea['codigo_impuesto'] ?? ''));
            if ($codImp !== '1' && $codImp !== 'RENTA') continue; // el F103 solo declara retenciones de Renta

            $cas = $this->repository->getCasilleroDeRetencionSri(
                !empty($linea['id_retencion_sri']) ? (int) $linea['id_retencion_sri'] : null,
                (string) ($linea['codigo_retencion'] ?? '')
            );

            $casBase = $cas['casillero_base'] ?? null;
            $casValor = $cas['casillero_valor'] ?? null;

            if ($casBase) {
                $acumBase[$casBase] = ($acumBase[$casBase] ?? 0.0) + (float) ($linea['base_imponible'] ?? 0);
            }
            if ($casValor) {
                $acumValor[$casValor] = ($acumValor[$casValor] ?? 0.0) + (float) ($linea['valor_retenido'] ?? 0);
            }
        }

        foreach ($acumBase as $casillero => $valor) {
            if (abs($valor) < 0.005) continue;
            $this->repository->insertarCasillero([
                'id_empresa' => $idEmpresa, 'origen' => 'retenciones_compras_renta', 'id_origen' => $idRetencion,
                'fecha' => $fecha, 'casillero' => $casillero, 'valor' => $valor, 'concepto' => 'Base imponible',
            ]);
        }
        foreach ($acumValor as $casillero => $valor) {
            if (abs($valor) < 0.005) continue;
            $this->repository->insertarCasillero([
                'id_empresa' => $idEmpresa, 'origen' => 'retenciones_compras_renta', 'id_origen' => $idRetencion,
                'fecha' => $fecha, 'casillero' => $casillero, 'valor' => $valor, 'concepto' => 'Valor retenido',
            ]);
        }
    }

    /**
     * Casillero 302/352 (relación de dependencia). Pendiente del motor de Impuesto a
     * la Renta de empleados (Fase 2, aún no implementado): no-op hasta entonces, el
     * casillero queda en 0 y puede corregirse manualmente en el detalle.
     */
    private function sincronizarEmpleadosIr(int $idEmpresa, string $fechaDesde, string $fechaHasta): void
    {
        // Intencionalmente vacío — ver tareas de Fase 2 (ImpuestoRentaEmpleadoService).
    }

    /**
     * Arma la estructura completa (layout oficial + valores) para la vista, el PDF y el Excel,
     * incluyendo los subtotales (349/399, 497/498) y el total (499).
     */
    public function getResumenCompleto(int $idEmpresa, string $fechaDesde, string $fechaHasta, int $idUsuario = 0): array
    {
        // El F103 se presenta por RUC completo: se consolidan todas las empresas del grupo
        // accesibles al usuario. Ver comentario de clase.
        $valores = [];
        foreach ($this->idsGrupo($idEmpresa, $idUsuario) as $idEmp) {
            foreach ($this->repository->getResumenPorCasilleros($idEmp, $fechaDesde, $fechaHasta) as $r) {
                $valores[$r['casillero']] = ($valores[$r['casillero']] ?? 0.0) + (float) $r['total'];
            }
        }

        $estructura = $this->repository->getEstructuraFormulario();

        $subtotalBaseNac = 0.0;
        $subtotalValNac  = 0.0;
        $subtotalBaseExt = 0.0;
        $subtotalValExt  = 0.0;

        foreach ($estructura as $e) {
            if ($e['tipo'] !== 'concepto') continue;
            $cb = $e['casillero_base'] ?? null;
            $cv = $e['casillero_valor'] ?? null;
            $vb = $cb ? ($valores[$cb] ?? 0.0) : 0.0;
            $vv = $cv ? ($valores[$cv] ?? 0.0) : 0.0;
            if (!isset($valores[$cb]) && $cb) $valores[$cb] = 0.0;
            if (!isset($valores[$cv]) && $cv) $valores[$cv] = 0.0;

            if ($e['seccion'] === 'NACIONAL') {
                $subtotalBaseNac += $vb;
                $subtotalValNac  += $vv;
            } elseif (in_array($e['seccion'], ['EXT_CONVENIO', 'EXT_SINCONVENIO', 'EXT_PARAISO'], true)) {
                $subtotalBaseExt += $vb;
                $subtotalValExt  += $vv;
            }
        }

        $valores['349'] = $subtotalBaseNac;
        $valores['399'] = $subtotalValNac;
        $valores['497'] = $subtotalBaseExt;
        $valores['498'] = $subtotalValExt;
        $valores['499'] = $subtotalValNac + $subtotalValExt;

        // 897/898/899 (interés/impuesto/multa "de imputación al pago") son informativos: hoy
        // nada los alimenta (no hay soporte de rectificativas con pago previo), así que quedan
        // en 0 salvo que algo escriba en casilleros_declaracion_sri con esos códigos. 902 =
        // "Total impuesto a pagar" oficial del F103 (499 - 898) — es el valor real a pagar,
        // distinto de 499 (total retenido) el día que exista un pago previo imputado.
        $valores['897'] = $valores['897'] ?? 0.0;
        $valores['898'] = $valores['898'] ?? 0.0;
        $valores['899'] = $valores['899'] ?? 0.0;
        $valores['902'] = round($valores['499'] - $valores['898'], 2);

        return ['layout' => $estructura, 'valores' => $valores];
    }

    /**
     * getDetalleDocumentos() de todo el grupo, unido y etiquetado con el establecimiento propio
     * de origen — igual patrón que DeclaracionIvaService::detalleDocumentosGrupo().
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

    /** Igual que detalleDocumentosGrupo() pero para getDetalleLineasRenta() (hoja de detalle del Excel). */
    public function detalleLineasRentaGrupo(int $idEmpresa, string $fechaDesde, string $fechaHasta, int $idUsuario): array
    {
        $idsGrupo = $this->idsGrupo($idEmpresa, $idUsuario);
        $etiquetas = $this->empresaRepo->getEtiquetasEstablecimiento($idsGrupo);
        $out = [];
        foreach ($idsGrupo as $idEmp) {
            foreach ($this->repository->getDetalleLineasRenta($idEmp, $fechaDesde, $fechaHasta) as $l) {
                $l['_establecimiento_propio'] = $etiquetas[$idEmp] ?? '';
                $out[] = $l;
            }
        }
        return $out;
    }

    // ==========================================================================
    // Declaración guardada: verificar duplicado, guardar, asiento y egreso
    // (mismo patrón que DeclaracionIvaService; sin saldo a favor/arrastre porque
    // el F103 es una retención ya reconocida documento a documento, no un
    // mecanismo de crédito tributario).
    // ==========================================================================

    private function ambienteEmpresa(int $idEmpresa): string
    {
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        return (string) ($empresa['tipo_ambiente'] ?? '1');
    }

    private function etiquetaPeriodo(array $decl): string
    {
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        return ($meses[(int) $decl['periodo_mes']] ?? (string) $decl['periodo_mes']) . ' ' . $decl['periodo_anio'];
    }

    /** Verifica si el período (año/mes) ya tiene una declaración guardada, para avisar al usuario. */
    public function verificarDeclarado(int $idEmpresa, int $anio, int $mes): ?array
    {
        $ambiente = $this->ambienteEmpresa($idEmpresa);
        return $this->repository->findDeclaracion($idEmpresa, $ambiente, $anio, $mes);
    }

    /**
     * Guarda (crea o actualiza) la declaración de un período mensual: recalcula los
     * componentes desde getResumenCompleto() y persiste un snapshot completo de los casilleros.
     */
    public function guardarDeclaracion(array $data): array
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['usuario_id'];
        $anio      = (int) $data['periodo_anio'];
        $mes       = (int) $data['periodo_mes'];

        $ambiente = $this->ambienteEmpresa($idEmpresa);
        $mesStr = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        $fechaDesde = "{$anio}-{$mesStr}-01";
        $fechaHasta = date('Y-m-t', strtotime($fechaDesde));

        $existente = $this->repository->findDeclaracion($idEmpresa, $ambiente, $anio, $mes);
        $this->rules->validarGuardado($data, $existente);

        $resumen = $this->getResumenCompleto($idEmpresa, $fechaDesde, $fechaHasta, $idUsuario);
        $valores = $resumen['valores'];

        $toSave = [
            'id_empresa'              => $idEmpresa,
            'tipo_ambiente'           => $ambiente,
            'periodo_anio'            => $anio,
            'periodo_mes'             => $mes,
            'fecha_desde'             => $fechaDesde,
            'fecha_hasta'             => $fechaHasta,
            'total_base_nacional'     => (float) ($valores['349'] ?? 0),
            'total_retenido_nacional' => (float) ($valores['399'] ?? 0),
            'total_base_exterior'     => (float) ($valores['497'] ?? 0),
            'total_retenido_exterior' => (float) ($valores['498'] ?? 0),
            'total_retenido'          => (float) ($valores['499'] ?? 0),
            'total_a_pagar'           => (float) ($valores['902'] ?? ($valores['499'] ?? 0)),
            'valores_casilleros'      => $valores,
            'estado'                  => $existente['estado'] ?? 'guardado',
            'observaciones'           => $data['observaciones'] ?? ($existente['observaciones'] ?? null),
            'usuario_id'              => $idUsuario,
        ];

        if ($existente) {
            $id = (int) $existente['id'];
            $this->repository->updateDeclaracion($id, $idEmpresa, $toSave);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'declaracion_retenciones_cabecera', $id, $existente, $toSave);
        } else {
            $id = $this->repository->insertDeclaracion($toSave);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'declaracion_retenciones_cabecera', $id, null, $toSave);
        }

        return $this->repository->findDeclaracionById($id, $idEmpresa) ?? [];
    }

    /** Genera (o regenera, sin duplicar) el asiento contable de la declaración de retenciones. */
    public function generarAsientoDeclaracion(int $idDeclaracion, int $idEmpresa, int $idUsuario): array
    {
        if (!$this->empresaRepo->getEsMatriz($idEmpresa)) {
            throw new \Exception('El asiento de la Declaración de Retenciones solo se puede generar desde el establecimiento matriz del RUC.');
        }

        $decl = $this->repository->findDeclaracionById($idDeclaracion, $idEmpresa);
        if (!$decl) {
            throw new \Exception('Declaración no encontrada.');
        }

        $idsGrupo = $this->idsGrupo($idEmpresa, $idUsuario);
        $builder  = new AsientoBuilderService();
        $detalles = $builder->generarAsientoDeclaracionRetenciones($idEmpresa, $decl['fecha_desde'], $decl['fecha_hasta'], $idsGrupo);
        if (empty($detalles)) {
            throw new \Exception('No hay valores para generar el asiento de esta declaración.');
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoService = new AsientoContableService(
            $asientoRepo,
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $previo    = $asientoService->getAsientoPorOrigen('declaracion_retenciones', $idDeclaracion, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $cabecera = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $decl['fecha_hasta'],
            'tipo_comprobante'     => 'declaracion_retenciones',
            'numero_comprobante'   => '',
            'concepto'             => 'Declaración de Retenciones ' . $this->etiquetaPeriodo($decl),
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'declaracion_retenciones',
            'id_referencia_origen' => $idDeclaracion,
            'observaciones'        => $decl['observaciones'] ?? null,
        ];

        $idGenerado = $asientoService->guardarAsiento($cabecera, $detalles, $idEmpresa, $idUsuario);
        $this->repository->marcarAsiento($idDeclaracion, $idEmpresa, $idGenerado, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_ASIENTO', 'declaracion_retenciones_cabecera', $idDeclaracion, null, ['id_asiento' => $idGenerado]);

        // El id interno no sirve para buscarlo en Asientos Contables — ahí se busca por
        // número de comprobante (p. ej. "DV-000001"), así que se devuelve también.
        $numeroComprobante = $asientoRepo->getDetalleAsiento($idGenerado, $idEmpresa)['numero_comprobante'] ?? '';

        return ['id_asiento' => $idGenerado, 'numero_comprobante' => $numeroComprobante];
    }

    /**
     * Genera el egreso del total de retenciones a pagar de la declaración, a nombre del
     * proveedor y con el concepto de egreso que elige el usuario. Reutiliza
     * EgresoService::registrar, igual patrón que DeclaracionIvaService::generarEgreso.
     *
     * @param array $opts ['id_proveedor','id_egreso_concepto','id_forma_pago','id_punto_emision','fecha',...]
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
        $sec    = (int) ($secSvc->obtenerSiguienteSecuencial($idPunto, 'Egresos')['secuencial'] ?? 0);
        $numero = $est . '-' . $pto . '-' . str_pad((string) $sec, 9, '0', STR_PAD_LEFT);

        // El monto del egreso es el casillero 902 ("Total impuesto a pagar" = 499 - 898), no el
        // 499 (total retenido) directo: son iguales mientras no exista un pago previo imputado
        // (898), pero conceptualmente son campos distintos del F103. Declaraciones guardadas
        // antes de este cambio no tienen total_a_pagar (columna nueva): cae a total_retenido.
        $monto        = round((float) ($decl['total_a_pagar'] ?? $decl['total_retenido']), 2);
        $periodoLabel = $this->etiquetaPeriodo($decl);

        $egSvc = new EgresoService(
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
            'tipo_egreso'        => 'DECLARACION_RETENCIONES',
            'tipo_sujeto'        => 'PROVEEDOR',
            'id_proveedor'       => $idProveedor,
            'id_egreso_concepto' => $idConcepto,
            'monto_total'        => $monto,
            'observaciones'      => 'Pago Declaración de Retenciones ' . $periodoLabel,
            'detalles' => [[
                'tipo_documento'          => 'DECLARACION_RETENCIONES',
                'id_referencia_documento' => $idDeclaracion,
                'numero_documento'        => 'Declaración Retenciones ' . $periodoLabel,
                'monto_documento'         => $monto,
                'saldo_anterior'          => $monto,
                'monto_pagado'            => $monto,
                'saldo_actual'            => 0,
            ]],
            'pagos' => [$this->armarPagoEgreso($idForma, $monto, $tipoOp, $numeroCheque, $fechaCobro, $fecha)],
        ]);

        $this->repository->marcarEgreso($idDeclaracion, $idEmpresa, $idEgreso, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_EGRESO', 'declaracion_retenciones_cabecera', $idDeclaracion, null, ['id_egreso' => $idEgreso]);

        if ($managedTransaction) {
            $db->commit();
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
