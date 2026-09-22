<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\LiquidacionCompraRepository;
use App\Rules\modulos\LiquidacionCompraRules;
use App\Services\LogSistemaService;

class LiquidacionCompraService
{
    use \App\Traits\PeriodoContableTrait;

    private $repository;
    private $rules;
    private $logService;

    public function __construct(LiquidacionCompraRepository $repository, LiquidacionCompraRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $data = $this->normalizarImpuestosYTotales($data);
        $this->rules->validar($data);

        $this->validarPeriodoContable(
            $data["fecha_emision"] ?? null,
            (int) ($data["id_empresa"] ?? 0),
            "No se puede emitir la liquidación porque el período contable de esa fecha está cerrado."
        );

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');

        $data['clave_acceso'] = \App\Services\ClaveAccesoService::generar(
            (string) ($data['fecha_emision']   ?? ''),
            \App\Services\ClaveAccesoService::LIQUIDACION_COMPRA,
            (string) ($empresaConfig['ruc']    ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision']
        );

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();

        try {
            $id = $this->repository->insertCabecera($data);

            foreach ($data['detalles'] as $det) {
                $det['id_cabecera'] = $id;
                $idDetalle = $this->repository->insertDetalle($det);

                if (!empty($det['impuestos'])) {
                    foreach ($det['impuestos'] as $imp) {
                        $imp['id_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }
            }

            foreach ($data['pagos'] as $pago) {
                $pago['id_cabecera'] = $id;
                $this->repository->insertPago($pago);
            }

            // RUC Proveedor (Res. NAC-DGERCGC26-00000027) se agrega aquí, al crear —
            // así solo los documentos nuevos lo llevan; los ya emitidos no se alteran.
            $infoAdicional = is_array($data['info_adicional'] ?? null) ? $data['info_adicional'] : [];
            $infoAdicional = \App\Helpers\SriProveedorHelper::conRucProveedor($infoAdicional);
            foreach ($infoAdicional as $info) {
                $info['id_cabecera'] = $id;
                $this->repository->insertInfoAdicional($info);
            }

            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'crear',
                'liquidaciones_cabecera',
                (int) $id,
                null,
                $data
            );

            $this->sincronizarCasilleros($id, $data);

            $db->commit();
            $this->generarYGuardarXml($id, $data);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la liquidación guardada.
        // Solo liquidaciones 'autorizado' se contabilizan: un borrador no debe generar un
        // asiento activo, o queda huérfano si el borrador se descarta después.
        try {
            if (($data['estado'] ?? 'borrador') === 'autorizado') {
                $this->procesarAsientoContable($id, $data);
            }
        } catch (\Throwable $eAs) {
            error_log("[Liquidacion] Asiento no generado para liquidación $id: " . $eAs->getMessage());
        }
        return $id;
    }

    /**
     * Impuestos de cada línea y totales de la cabecera, con la configuración de
     * facturación de la empresa (`empresa_establecimiento.calculo_iva_facturacion`).
     *
     * El servidor es la autoridad: los totales que manda la pantalla se recalculan
     * aquí y no se guardan tal cual. Antes esto vivía en el controller y el IVA se
     * guardaba SIN redondear (10.35 al 15% dejaba 1.5525 en
     * `liquidaciones_detalle_impuestos.valor`), así que el XML, el PDF, el asiento
     * y los casilleros de la Declaración de IVA —que suman esa columna— nunca
     * cuadraban con el `importe_total` que la pantalla calculó por centavos.
     *
     * `total_sin_impuestos` es el subtotal NETO (con el descuento ya restado), igual
     * que en Facturas de Venta: es lo que el SRI compara contra
     * `importeTotal = totalSinImpuestos + Σ impuestos`, y lo que el asiento reparte
     * entre Inventario y Gasto. La pantalla enviaba el bruto, así que con cualquier
     * descuento el XML se rechazaba por diferencias y el asiento quedaba descuadrado
     * justo por el monto del descuento.
     */
    private function normalizarImpuestosYTotales(array $data): array
    {
        $detalles = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];
        if (!$detalles) {
            return $data;
        }

        $config  = is_array($data['empresa_config'] ?? null) ? $data['empresa_config'] : [];
        $modoIva = ($config['calculo_iva_facturacion'] ?? 'linea_linea') === 'subtotal'
            ? 'subtotal'
            : 'linea_linea';

        $tarifas = [];
        foreach ($this->repository->getTarifasIva() as $t) {
            $tarifas[(int) $t['id']] = $t;
        }

        $totalSinImpuestos = 0.0;
        $totalDescuento    = 0.0;
        $grupos            = []; // id_tarifa => ['pct','codigo','base','lineas']

        foreach ($detalles as $i => $det) {
            $cantidad = (float) ($det['cantidad'] ?? 0);
            $precio   = (float) ($det['precio_unitario'] ?? 0);
            $desc     = round((float) ($det['descuento'] ?? 0), 2);
            // Mismo orden de redondeo que la pantalla: el bruto a centavos primero y
            // el descuento después. Hacerlo de una sola vez daría un neto que puede
            // diferir un centavo del que vio el usuario.
            $neto = max(0.0, round(round($cantidad * $precio, 2) - $desc, 2));

            $detalles[$i]['id_empresa']                = $data['id_empresa'] ?? null;
            $detalles[$i]['descuento']                 = $desc;
            $detalles[$i]['precio_total_sin_impuesto'] = $neto;
            $detalles[$i]['codigo_principal']          = trim((string) ($det['codigo_principal'] ?? $det['codigo'] ?? ''));
            $detalles[$i]['info_adicional']            = $det['info_adicional'] ?? $det['adicional'] ?? '';
            $detalles[$i]['impuestos']                 = [];

            $totalSinImpuestos = round($totalSinImpuestos + $neto, 2);
            $totalDescuento    = round($totalDescuento + $desc, 2);

            $idTarifa = (int) ($det['id_tarifa_iva'] ?? 0);
            if (!isset($tarifas[$idTarifa])) {
                continue; // tarifa desconocida: la línea queda sin impuestos, como antes
            }

            if (!isset($grupos[$idTarifa])) {
                $grupos[$idTarifa] = [
                    'pct'    => (float) ($tarifas[$idTarifa]['porcentaje_iva'] ?? 0),
                    'codigo' => (string) ($tarifas[$idTarifa]['codigo'] ?? '0'),
                    'base'   => 0.0,
                    'lineas' => [],
                ];
            }
            $grupos[$idTarifa]['base']     = round($grupos[$idTarifa]['base'] + $neto, 2);
            $grupos[$idTarifa]['lineas'][] = $i;
        }

        $totalIva = 0.0;
        foreach ($grupos as $g) {
            // 'subtotal': el IVA de la tarifa se calcula sobre la base acumulada del
            // grupo y el residuo de centavos se asienta en su última línea, para que
            // la suma de las líneas cuadre EXACTO con el total de la tarifa — es lo
            // que el SRI compara en totalConImpuestos.
            $ivaGrupo   = $modoIva === 'subtotal' ? round($g['base'] * $g['pct'] / 100, 2) : 0.0;
            $acumulado  = 0.0;
            $ultimaPos  = count($g['lineas']) - 1;

            foreach ($g['lineas'] as $pos => $i) {
                $base = (float) $detalles[$i]['precio_total_sin_impuesto'];
                $iva  = round($base * $g['pct'] / 100, 2);
                if ($modoIva === 'subtotal' && $pos === $ultimaPos) {
                    $iva = round($ivaGrupo - $acumulado, 2);
                }
                $acumulado = round($acumulado + $iva, 2);

                $detalles[$i]['impuestos'] = [[
                    'codigo_impuesto'   => '2', // IVA
                    'codigo_porcentaje' => $g['codigo'],
                    'tarifa'            => $g['pct'],
                    'base_imponible'    => $base,
                    'valor'             => $iva,
                ]];
                $totalIva = round($totalIva + $iva, 2);
            }
        }

        $data['detalles']            = $detalles;
        $data['total_sin_impuestos'] = $totalSinImpuestos;
        $data['total_descuento']     = $totalDescuento;
        $data['importe_total']       = round($totalSinImpuestos + $totalIva, 2);

        return $data;
    }

    /**
     * Punto de entrada del sincronizador (Estados Financieros) para liquidaciones sin asiento.
     */
    public function procesarAsientoContablePorSincronizacion(int $idLiquidacion): void
    {
        $cab = $this->repository->getPorId($idLiquidacion);
        if (!$cab) return;
        $this->procesarAsientoContable($idLiquidacion, $cab);
    }

    /**
     * Arma (vía AsientoBuilderService::generarAsientoLiquidacionCompra) y persiste el asiento de
     * una liquidación de compra. Idempotente: si ya existe asiento para esta liquidación lo actualiza.
     */
    public function procesarAsientoContable(int $idLiquidacion, array $data): void
    {
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? $data['created_by'] ?? $_SESSION['id_usuario'] ?? 0);
        $fecha = $data['fecha_emision'] ?? date('Y-m-d');
        $numDoc = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');
        $proveedorNombre = $data['proveedor_nombre'] ?? 'Proveedor';

        $builder = new \App\Services\modulos\AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoLiquidacionCompra($idEmpresa, $idLiquidacion);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Liquidación # $numDoc",
                'documento_referencia' => "Liquidación # $numDoc",
                'id_entidad'           => (int)($data['id_proveedor'] ?? 0),
                'tipo_entidad'         => 'proveedor',
            ];
        }

        if (empty($detalles)) {
            return;
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('liquidacion_compra', $idLiquidacion, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'compras',
            'numero_comprobante'   => '',
            'concepto'             => "Liquidación de compra # " . $numDoc . " - Proveedor: " . $proveedorNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'liquidacion_compra',
            'id_referencia_origen' => $idLiquidacion,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idLiquidacion, $idAsientoGenerado);
    }

    public function actualizar(int $id, array $data): int
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera) {
            throw new \Exception('Liquidación no encontrada.');
        }

        // Los comprobantes autorizados por el SRI (o anulados) son inmutables:
        // no se permite editar su contenido.
        $estadoActual = strtolower((string) ($cabecera['estado'] ?? ''));
        if ($estadoActual === 'autorizado') {
            throw new \Exception('La liquidación está autorizada por el SRI y no puede editarse.');
        }
        if ($estadoActual === 'anulado') {
            throw new \Exception('La liquidación está anulada y no puede editarse.');
        }

        $this->validarPeriodoContableAlModificar(
            $cabecera['fecha_emision'] ?? null,
            $data['fecha_emision'] ?? null,
            (int) ($data['id_empresa'] ?? 0),
            'la liquidación'
        );

        $data = $this->normalizarImpuestosYTotales($data);
        $this->rules->validar($data);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');

        $codigoNumerico = \App\Services\ClaveAccesoService::extraerCodigoNumerico($cabecera['clave_acceso'] ?? '');
        $data['clave_acceso'] = \App\Services\ClaveAccesoService::generar(
            (string) ($data['fecha_emision']  ?? ''),
            \App\Services\ClaveAccesoService::LIQUIDACION_COMPRA,
            (string) ($empresaConfig['ruc']   ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision'],
            $codigoNumerico
        );

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();

        try {
            $this->repository->updateCabecera($id, $data);

            // Reemplazar detalles
            $this->repository->deleteDetalles($id);
            foreach ($data['detalles'] as $det) {
                $det['id_cabecera'] = $id;
                $idDetalle = $this->repository->insertDetalle($det);

                if (!empty($det['impuestos'])) {
                    foreach ($det['impuestos'] as $imp) {
                        $imp['id_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }
            }

            // Reemplazar pagos
            $this->repository->deletePagos($id);
            foreach ($data['pagos'] as $pago) {
                $pago['id_cabecera'] = $id;
                $this->repository->insertPago($pago);
            }

            // Reemplazar info adicional. El RUC del proveedor del sistema
            // (Res. NAC-DGERCGC26-00000027) se vuelve a forzar aquí con el valor de la
            // configuración global: en pantalla es una fila fija que no se edita ni se
            // elimina, y esta línea lo garantiza aunque la petición llegue sin ella.
            // Solo alcanza a los borradores: una liquidación autorizada no llega hasta
            // aquí (se rechaza más arriba), así que ningún documento emitido se altera.
            $infoAdicional = is_array($data['info_adicional'] ?? null) ? $data['info_adicional'] : [];
            $infoAdicional = \App\Helpers\SriProveedorHelper::conRucProveedor($infoAdicional);

            $this->repository->deleteInfoAdicional($id);
            foreach ($infoAdicional as $info) {
                $info['id_cabecera'] = $id;
                $this->repository->insertInfoAdicional($info);
            }

            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'actualizar',
                'liquidaciones_cabecera',
                (int) $id,
                null,
                $data
            );

            $this->sincronizarCasilleros($id, $data);

            $db->commit();
            $this->generarYGuardarXml($id, $data);
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \Exception('Liquidación no encontrada.');
        }
        if (($cabecera['estado'] ?? '') === 'anulado') {
            throw new \Exception('La liquidación ya está anulada.');
        }

        // Anular revierte el asiento y el inventario de la liquidación: si su período
        // está cerrado, ese movimiento no puede tocarse.
        $this->validarPeriodoContable(
            $cabecera['fecha_emision'] ?? null,
            $idEmpresa,
            'No se puede anular la liquidación porque su período contable está cerrado.'
        );

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            // Anular el asiento contable de la liquidación si existe (antes no se hacía:
            // quedaba con su asiento "contabilizado" activo tras anular el documento).
            $idAsientoLiq = (int)($cabecera['id_asiento_contable'] ?? 0);
            if ($idAsientoLiq > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->logService
                );
                try {
                    $asientoService->anular($idAsientoLiq, $idEmpresa, $idUsuario);
                } catch (\Throwable $eA) {
                    if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                        throw $eA;
                    }
                }
            }

            $st = $db->prepare("UPDATE liquidaciones_cabecera SET estado = 'anulado', updated_at = NOW(), updated_by = ? WHERE id = ? AND id_empresa = ?");
            $st->execute([$idUsuario, $id, $idEmpresa]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR',
                'liquidaciones_cabecera',
                $id,
                $cabecera,
                ['nuevo_estado' => 'anulado']
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'liquidaciones_compras', $id);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \Exception('Liquidación no encontrada.');
        }

        // Solo borradores: un comprobante ya emitido (autorizado/anulado) es inmutable —
        // se anula, no se borra. Mismo candado que FacturaVentaService::eliminar().
        $estadoActual = strtolower((string)($cabecera['estado'] ?? ''));
        if ($estadoActual !== '' && $estadoActual !== 'borrador') {
            throw new \Exception('Solo se pueden eliminar liquidaciones en estado borrador.');
        }
        // Un borrador que el SRI ya recibió/autorizó no se borra: su secuencial ya está
        // ocupado allá con esa clave (ver SriDocumentoRules).
        \App\Rules\SriDocumentoRules::validarEliminable('liquidacion_compra', $id, 'la liquidación');

        $this->validarPeriodoContable(
            $cabecera['fecha_emision'] ?? null,
            $idEmpresa,
            'No se puede eliminar la liquidación porque su período contable está cerrado.'
        );

        // Retención vinculada: no dejarla huérfana apuntando a un documento borrado.
        // Mismo criterio que ComprasService::eliminar().
        $retRepo = new \App\repositories\modulos\RetencionCompraRepository();
        if ($retRepo->existeRetencionParaLiquidacion($id, $idEmpresa)) {
            throw new \Exception('No se puede eliminar la liquidación porque tiene una retención asociada. Elimine primero la retención (módulo Retenciones de Compra).');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();

        try {
            // Egresos (pagos internos) vinculados: se anulan antes del borrado lógico, igual
            // que en ComprasService::eliminar(), para no dejar el pago apuntando a un
            // documento eliminado. Un borrador normalmente no tiene, pero los migrados sí.
            $egresoService = new \App\Services\modulos\EgresoService(
                new \App\repositories\modulos\EgresoRepository(),
                new \App\Rules\modulos\EgresoRules(),
                $this->logService
            );
            foreach ($this->repository->getEgresosVinculados($id) as $egr) {
                if (strtolower((string)($egr['estado'] ?? '')) === 'anulado') continue;
                $egresoService->anular((int)$egr['id'], $idEmpresa, $idUsuario);
            }

            // Anular el asiento contable de la liquidación si existe (mismo patrón que
            // FacturaVentaService::eliminar()): un borrador no debería tener asiento activo
            // tras el fix en crear(), pero esto cierra el hueco para datos previos al fix.
            $idAsientoLiq = (int)($cabecera['id_asiento_contable'] ?? 0);
            if ($idAsientoLiq > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->logService
                );
                try {
                    $asientoService->anular($idAsientoLiq, $idEmpresa, $idUsuario);
                } catch (\Throwable $eA) {
                    if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                        throw $eA;
                    }
                }
            }

            $sql = "UPDATE liquidaciones_cabecera SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND id_empresa = ?";
            $st = $db->prepare($sql);
            $st->execute([$idUsuario, $id, $idEmpresa]);

            $this->logService->registrar(
                (int) $idUsuario,
                (int) $idEmpresa,
                'eliminar',
                'liquidaciones_cabecera',
                (int) $id
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'liquidaciones_compras', $id);

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    private function generarYGuardarXml(int $idLiq, array $data): void
    {
        try {
            $cabecera = $this->repository->getPorId($idLiq);
            if (!$cabecera) return;

            $detalles = $this->repository->getDetalles($idLiq);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($idLiq);
            $infoAdicional = $this->repository->getInfoAdicional($idLiq);

            $idEmpresa    = (int) $cabecera['id_empresa'];
            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            // XmlLiquidacionCompraService genera el XML del comprobante
            $xml = (new \App\Services\Xml\XmlLiquidacionCompraService())->generar(
                $cabecera, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento
            );
            $this->repository->updateDetalleXml($idLiq, $xml);
        } catch (\Throwable $e) {
            error_log('[Liq] Error generando XML para liquidación #' . $idLiq . ': ' . $e->getMessage());
        }
    }

    public function sincronizarCasilleros(int $idLiq, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;
        
        if (!$data) {
            $cabecera = $this->repository->getPorId($idLiq);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
            
            // Get details and taxes if we fetched from DB
            $data['detalles'] = $this->repository->getDetalles($idLiq);
            foreach ($data['detalles'] as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'liquidaciones_compras', $idLiq);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);
        if (!$configDec || !isset($configDec['liquidacion_compra'])) return;
        $confLiq = $configDec['liquidacion_compra'];

        $tarifaMap = $decIvaRepo->getMapaTarifasIva();
        $detalles = $data['detalles'] ?? [];

        foreach ($detalles as $det) {
            $desc = !empty($det['producto_nombre']) ? $det['producto_nombre'] : (!empty($det['descripcion']) ? $det['descripcion'] : 'Sin concepto');
            // 240 y no 255: al concepto se le concatena " (Base)" / " (IVA)" justo abajo,
            // así que cortar en el largo de la columna dejaba 262 caracteres y el INSERT
            // moría con SQLSTATE[22001] donde `concepto` es varchar(255). mb_substr, además,
            // para no partir una tilde a la mitad (substr corta bytes, no caracteres).
            $concepto = mb_substr(trim($desc), 0, 240);
            $impuestos = $det['impuestos'] ?? [];
            foreach ($impuestos as $imp) {
                // Solo IVA (codigo_impuesto = 2)
                if ((int)$imp['codigo_impuesto'] !== 2) continue;

                $codigoPorcentaje = (string)($imp['codigo_porcentaje'] ?? '');
                $tarifaKey = $tarifaMap[$codigoPorcentaje] ?? '';
                if (!$tarifaKey || !isset($confLiq[$tarifaKey])) continue;

                $c = $confLiq[$tarifaKey];
                $bruto = $c['bruto'] ?? '';
                $neto = $c['neto'] ?? '';
                $impC = $c['impuesto'] ?? '';

                $base = (float)($imp['base_imponible'] ?? 0);
                $valorImp = (float)($imp['valor'] ?? 0);

                if ($bruto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'liquidaciones_compras', 'id_origen' => $idLiq,
                        'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($neto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'liquidaciones_compras', 'id_origen' => $idLiq,
                        'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($impC !== '' && $valorImp > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'liquidaciones_compras', 'id_origen' => $idLiq,
                        'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => $valorImp, 'concepto' => $concepto . ' (IVA)'
                    ]);
                }
            }
        }
    }
}
