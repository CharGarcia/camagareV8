<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\NotaCreditoRepository;
use App\Rules\modulos\NotaCreditoRules;
use App\Services\LogSistemaService;
use App\Services\ClaveAccesoService;
use App\Services\Xml\XmlNotaCreditoService;
use App\core\Database;
use Exception;

class NotaCreditoService
{
    private $repository;
    private $rules;
    private $logService;

    public function __construct(NotaCreditoRepository $repository, NotaCreditoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        // Validar que la suma de NC no exceda el total de la factura
        $numDocModificado = $data['num_doc_modificado'] ?? '';
        if (!empty($numDocModificado)) {
            $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $factura = $facturaRepo->getPorNumeroCompleto($numDocModificado, (int)$data['id_empresa']);
            if ($factura) {
                // Validar estado de la factura (Solo 'autorizado')
                if (($factura['estado'] ?? '') !== 'autorizado') {
                    throw new Exception("Solo se pueden generar notas de crédito para facturas en estado 'autorizado'.");
                }

                $sumaExistente = $this->repository->getSumaImporteNotasCredito($numDocModificado, (int)$data['id_empresa']);
                $nuevoTotalNC = $sumaExistente + (float)($data['importe_total'] ?? 0);
                
                if ($nuevoTotalNC > (float)$factura['importe_total'] + 0.01) {
                    throw new Exception("La suma de las notas de crédito ($nuevoTotalNC) excede el total de la factura (" . $factura['importe_total'] . ").");
                }
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Generar Clave de Acceso (si es para SRI)
            $empresaConfig = $data['empresa_config'] ?? [];

            // Ambiente de la empresa (1 pruebas / 2 producción). Sin esto la NC se
            // guardaría siempre como '1' y, en producción, no aparecería en el
            // listado (que filtra por el ambiente real de la empresa).
            $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');

            if (!empty($empresaConfig['ruc'])
                && !empty($data['establecimiento'])
                && !empty($data['punto_emision'])
                && !empty($data['secuencial'])
            ) {
                $data['clave_acceso'] = ClaveAccesoService::generar(
                    (string)($data['fecha_emision'] ?? date('Y-m-d')),
                    ClaveAccesoService::NOTA_CREDITO,
                    (string)$empresaConfig['ruc'],
                    (string)($empresaConfig['tipo_ambiente'] ?? '1'),
                    (string)$data['establecimiento'],
                    (string)$data['punto_emision'],
                    (string)$data['secuencial']
                );
            }

            $idNC = $this->repository->insertCabecera($data);

            foreach ($data['detalles'] as $det) {
                $det['id_nota_credito'] = $idNC;
                $idDetalle = $this->repository->insertDetalle($det);

                if (!empty($det['impuestos'])) {
                    foreach ($det['impuestos'] as $imp) {
                        $imp['id_nota_credito_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }

                // Lógica de Inventario: Reintegrar stock si es una NC de Venta
                if (!empty($det['id_producto']) && !empty($data['id_bodega'])) {
                    $invService = new \App\Services\modulos\InventarioService(
                        new \App\repositories\modulos\InventarioRepository(),
                        $this->logService
                    );
                    $invService->registrarEntradaPorNC([
                        'id_empresa'      => $data['id_empresa'],
                        'id_producto'     => $det['id_producto'],
                        'id_bodega'       => $data['id_bodega'],
                        'cantidad'        => $det['cantidad'],
                        'id_referencia'   => $idNC,
                        'descripcion'     => "Devolución NC {$data['establecimiento']}-{$data['punto_emision']}-{$data['secuencial']}",
                        'id_usuario'      => $data['id_usuario']
                    ]);
                }
            }

            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'crear',
                'notas_credito_cabecera',
                $idNC,
                null,
                ['secuencial' => $data['secuencial']]
            );

            // Sincronizar con casilleros SRI 104
            $this->sincronizarCasilleros($idNC, $data);

            $db->commit();
            // Info adicional fuera de la transacción: si la tabla no existe (BD sin
            // migrar) NO debe impedir que la NC se guarde.
            $this->guardarInfoAdicional($idNC, $data['info_adicional'] ?? []);
            $this->generarYGuardarXml($idNC, $data['empresa_config'] ?? []);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Asiento contable FUERA de la transacción: la NC ya está guardada; un fallo no la revierte.
        try {
            $this->procesarAsientoContable($idNC, $data);
        } catch (\Throwable $eAs) {
            error_log("[NotaCredito] Asiento no generado para NC $idNC: " . $eAs->getMessage());
        }
        return $idNC;
    }

    /**
     * Punto de entrada del sincronizador (Estados Financieros) para NC de venta sin asiento.
     */
    public function procesarAsientoContablePorSincronizacion(int $idNotaCredito): void
    {
        $nc = $this->repository->getPorId($idNotaCredito);
        if (!$nc) return;
        $this->procesarAsientoContable($idNotaCredito, $nc);
    }

    /**
     * Arma (vía AsientoBuilderService::generarAsientoNotaCreditoVenta — cuentas de venta invertidas)
     * y persiste el asiento de una nota de crédito de venta. Idempotente.
     */
    public function procesarAsientoContable(int $idNotaCredito, array $data): void
    {
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? $data['created_by'] ?? $_SESSION['id_usuario'] ?? 0);
        $fecha = $data['fecha_emision'] ?? date('Y-m-d');
        $numNC = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');
        $clienteNombre = $data['cliente_nombre'] ?? 'Cliente';

        $builder = new \App\Services\modulos\AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoNotaCreditoVenta($idEmpresa, $idNotaCredito);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Nota de crédito # $numNC",
                'documento_referencia' => "Nota de crédito # $numNC",
                'id_entidad'           => (int)($data['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles)) {
            return;
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('nota_credito', $idNotaCredito, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'ventas',
            'numero_comprobante'   => '',
            'concepto'             => "Nota de crédito # " . $numNC . " - Cliente: " . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'nota_credito',
            'id_referencia_origen' => $idNotaCredito,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idNotaCredito, $idAsientoGenerado);
    }

    public function actualizar(int $id, array $data): int
    {
        $this->rules->validar($data);

        // Validar que la suma de NC no exceda el total de la factura (excluyendo la actual)
        $numDocModificado = $data['num_doc_modificado'] ?? '';
        if (!empty($numDocModificado)) {
            $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $factura = $facturaRepo->getPorNumeroCompleto($numDocModificado, (int)$data['id_empresa']);
            if ($factura) {
                // Validar estado de la factura (Solo 'autorizado')
                if (($factura['estado'] ?? '') !== 'autorizado') {
                    throw new Exception("Solo se pueden generar notas de crédito para facturas en estado 'autorizado'.");
                }

                $sumaExistente = $this->repository->getSumaImporteNotasCredito($numDocModificado, (int)$data['id_empresa'], $id);
                $nuevoTotalNC = $sumaExistente + (float)($data['importe_total'] ?? 0);
                
                if ($nuevoTotalNC > (float)$factura['importe_total'] + 0.01) {
                    throw new Exception("La suma de las notas de crédito ($nuevoTotalNC) excede el total de la factura (" . $factura['importe_total'] . ").");
                }
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $ncOriginal = $this->repository->getPorId($id);
            if (!$ncOriginal) {
                throw new Exception("Nota de Crédito no encontrada.");
            }

            if ($ncOriginal['estado'] !== 'borrador') {
                throw new Exception("Solo se pueden editar Notas de Crédito en estado borrador.");
            }

            // Regenerar Clave de Acceso si cambió algo clave
            $empresaConfig = $data['empresa_config'] ?? [];
            if (!empty($empresaConfig['ruc'])
                && !empty($data['establecimiento'])
                && !empty($data['punto_emision'])
                && !empty($data['secuencial'])
            ) {
                $codigoNumerico = ClaveAccesoService::extraerCodigoNumerico($ncOriginal['clave_acceso'] ?? '');
                $data['clave_acceso'] = ClaveAccesoService::generar(
                    (string)($data['fecha_emision'] ?? date('Y-m-d')),
                    ClaveAccesoService::NOTA_CREDITO,
                    (string)$empresaConfig['ruc'],
                    (string)($empresaConfig['tipo_ambiente'] ?? '1'),
                    (string)$data['establecimiento'],
                    (string)$data['punto_emision'],
                    (string)$data['secuencial'],
                    '1',
                    $codigoNumerico
                );
            }

            $this->repository->updateCabecera($id, $data);

            // Revertir movimientos de inventario anteriores para esta NC antes de recrear
            $invService = new \App\Services\modulos\InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
            $invService->revertirMovimientosPorReferencia('nota_credito', $id, (int)$data['id_empresa'], (int)$data['id_usuario']);

            $this->repository->deleteDetalles($id);

            foreach ($data['detalles'] as $det) {
                $det['id_nota_credito'] = $id;
                $idDetalle = $this->repository->insertDetalle($det);

                if (!empty($det['impuestos'])) {
                    foreach ($det['impuestos'] as $imp) {
                        $imp['id_nota_credito_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }

                // Registrar nuevos movimientos de inventario
                if (!empty($det['id_producto']) && !empty($data['id_bodega'])) {
                    $invService->registrarEntradaPorNC([
                        'id_empresa'      => $data['id_empresa'],
                        'id_producto'     => $det['id_producto'],
                        'id_bodega'       => $data['id_bodega'],
                        'cantidad'        => $det['cantidad'],
                        'id_referencia'   => $id,
                        'descripcion'     => "Devolución NC Actualizada {$data['secuencial']}",
                        'id_usuario'      => $data['id_usuario']
                    ]);
                }
            }

            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'actualizar',
                'notas_credito_cabecera',
                $id,
                $ncOriginal,
                $data
            );

            // Sincronizar con casilleros SRI 104
            $this->sincronizarCasilleros($id, $data);

            $db->commit();
            // Info adicional fuera de la transacción (no debe bloquear el guardado).
            $this->guardarInfoAdicional($id, $data['info_adicional'] ?? []);
            $this->generarYGuardarXml($id, $data['empresa_config'] ?? []);
            return $id;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Persiste la información adicional fuera de la transacción principal.
     * Si la tabla notas_credito_adicional no existe (BD sin migrar), se registra
     * el error pero NO se interrumpe el guardado de la nota de crédito.
     */
    private function guardarInfoAdicional(int $idNC, $infoAdicional): void
    {
        try {
            $this->repository->deleteInfoAdicional($idNC);
            if (!empty($infoAdicional) && is_array($infoAdicional)) {
                foreach ($infoAdicional as $ia) {
                    $this->repository->insertInfoAdicional([
                        'id_nota_credito' => $idNC,
                        'nombre'          => $ia['nombre'] ?? '',
                        'valor'           => $ia['valor'] ?? '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[NotaCredito] Info adicional no guardada para NC ' . $idNC . ': ' . $e->getMessage());
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                throw new Exception("Nota de Crédito no encontrada.");
            }

            if ($nc['estado'] !== 'borrador') {
                throw new Exception("Solo se pueden eliminar Notas de Crédito en estado borrador.");
            }

            // Revertir inventario
            if ((int)($nc['id_empresa'] ?? 0) > 0) {
                $invService = new \App\Services\modulos\InventarioService(
                    new \App\repositories\modulos\InventarioRepository(),
                    $this->logService
                );
                $invService->revertirMovimientosPorReferencia('nota_credito', $id, $idEmpresa, $idUsuario);
            }

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'notas_credito_cabecera',
                $id,
                $nc,
                null
            );

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                throw new Exception("Nota de Crédito no encontrada.");
            }

            if ($nc['estado'] === 'anulado') {
                throw new Exception("La Nota de Crédito ya se encuentra anulada.");
            }

            // Revertir inventario (eliminar los ingresos generados por la NC)
            $invService = new \App\Services\modulos\InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
            $invService->revertirMovimientosPorReferencia('nota_credito', $id, $idEmpresa, $idUsuario);

            // Limpiar casilleros de declaracion 104
            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'notas de credito', $id);

            $this->repository->updateEstado($id, 'anulado');

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'anular',
                'notas_credito_cabecera',
                $id,
                $nc,
                ['estado' => 'anulado']
            );

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    private function generarYGuardarXml(int $idNC, array $empresaConfig): void
    {
        try {
            $cabecera = $this->repository->getPorId($idNC);
            if (!$cabecera) return;

            $detalles = $this->repository->getDetalles($idNC);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $infoAdicional = $this->repository->getInfoAdicional($idNC);

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId((int)$cabecera['id_empresa']) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos((int)$cabecera['id_empresa']) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            $xml = (new XmlNotaCreditoService())->generar($cabecera, $detalles, $infoAdicional, $empresa, $dirEstablecimiento);
            $this->repository->updateDetalleXml($idNC, $xml);
        } catch (\Throwable $e) {
            error_log('[NC] Error generando XML para NC #' . $idNC . ': ' . $e->getMessage());
        }
    }

    public function sincronizarCasilleros(int $idNC, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;
        
        if (!$data) {
            $cabecera = $this->repository->getPorId($idNC);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
            
            // Get details and taxes if we fetched from DB
            $data['detalles'] = $this->repository->getDetalles($idNC);
            foreach ($data['detalles'] as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'notas de credito', $idNC);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);
        if (!$configDec || !isset($configDec['nota_credito'])) return;
        $confNC = $configDec['nota_credito'];

        $tarifaMap = $decIvaRepo->getMapaTarifasIva();
        $detalles = $data['detalles'] ?? [];

        foreach ($detalles as $det) {
            $desc = !empty($det['producto_nombre']) ? $det['producto_nombre'] : (!empty($det['descripcion']) ? $det['descripcion'] : 'Sin concepto');
            $concepto = substr(trim($desc), 0, 255);
            $impuestos = $det['impuestos'] ?? [];
            foreach ($impuestos as $imp) {
                // Solo IVA (codigo_impuesto = 2)
                if ((int)$imp['codigo_impuesto'] !== 2) continue;

                $codigoPorcentaje = (string)($imp['codigo_porcentaje'] ?? '');
                $tarifaKey = $tarifaMap[$codigoPorcentaje] ?? '';
                if (!$tarifaKey || !isset($confNC[$tarifaKey])) continue;

                $c = $confNC[$tarifaKey];
                $bruto = $c['bruto'] ?? '';
                $neto = $c['neto'] ?? '';
                $impC = $c['impuesto'] ?? '';

                $base = (float)($imp['base_imponible'] ?? 0);
                $valorImp = (float)($imp['valor'] ?? 0);

                // Guardamos el valor NEGATIVO para que reste en la sumatoria final
                if ($bruto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'notas de credito', 'id_origen' => $idNC,
                        'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => -1 * $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($neto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'notas de credito', 'id_origen' => $idNC,
                        'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => -1 * $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($impC !== '' && $valorImp > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'notas de credito', 'id_origen' => $idNC,
                        'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => -1 * $valorImp, 'concepto' => $concepto . ' (IVA)'
                    ]);
                }
            }
        }
    }
}
