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
    use \App\Traits\PeriodoContableTrait;

    private $repository;
    private $rules;
    private $logService;

    public function __construct(NotaCreditoRepository $repository, NotaCreditoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * El usuario no puede emitir notas de crédito: la empresa tiene bodegas, pero a él se le
     * denegaron todas (Bodegas → Accesos). Sin bodega de reintegro la NC no devuelve stock.
     * Una empresa sin ninguna bodega (solo servicios) no se bloquea.
     */
    public function usuarioSinBodegas(int $idUsuario, int $idEmpresa, int $nivel): bool
    {
        return $this->bodegasReintegro($idUsuario, $idEmpresa, $nivel)['sin_bodegas'];
    }

    /**
     * Bodegas donde el usuario puede reintegrar la mercadería de una NC.
     * 'aplica' = la empresa tiene bodegas; si no tiene ninguna, no hay nada que exigir.
     */
    private function bodegasReintegro(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $aplica     = !empty($bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, 3));
        $permitidas = $aplica ? $bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel) : [];

        return [
            'aplica'      => $aplica,
            'sin_bodegas' => $aplica && empty($permitidas),
            'ids'         => array_map('intval', array_column($permitidas, 'id')),
        ];
    }

    private function validarBodegaReintegro(array $data): void
    {
        $bodegas = $this->bodegasReintegro(
            (int) ($data['id_usuario'] ?? 0),
            (int) ($data['id_empresa'] ?? 0),
            (int) ($data['nivel'] ?? 1)
        );
        if (!$bodegas['aplica']) {
            return;
        }

        $this->rules->validarBodegaReintegro(
            $data,
            $bodegas['sin_bodegas'],
            in_array((int) ($data['id_bodega'] ?? 0), $bodegas['ids'], true)
        );
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->validarBodegaReintegro($data);

        $this->validarPeriodoContable(
            $data['fecha_emision'] ?? null,
            (int) ($data['id_empresa'] ?? 0),
            'No se puede emitir la nota de crédito porque el período contable de esa fecha está cerrado.'
        );

        // Normalizar espacios en el motivo (texto libre): colapsa espacios dobles y
        // saltos de línea a uno solo, y recorta los extremos.
        if (!empty($data['motivo'])) {
            $data['motivo'] = trim(preg_replace('/\s+/u', ' ', (string) $data['motivo']));
        }

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

            // La nota de crédito devuelve stock solo si la facturación del establecimiento lo
            // descuenta: con «La facturación afecta al inventario» apagada, la venta no sacó nada.
            $invService = new \App\Services\modulos\InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
            $devuelveStock = $invService->facturacionAfectaInventario((int) ($data['id_establecimiento'] ?? 0));
            $data['detalles'] = $this->enlazarLineasFactura($data['detalles'], $data, $invService);

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
                if ($devuelveStock && !empty($det['id_producto']) && !empty($data['id_bodega'])) {
                    $invService->registrarEntradaPorNC([
                        'id_empresa'      => $data['id_empresa'],
                        'id_producto'     => $det['id_producto'],
                        'id_bodega'       => $data['id_bodega'],
                        'cantidad'        => $det['cantidad'],
                        'id_referencia'   => $idNC,
                        'num_doc_modificado' => $data['num_doc_modificado'] ?? '',
                        'cod_doc_modificado' => $data['cod_doc_modificado'] ?? '01',
                        'linea_origen'    => $det['linea_origen'],
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
            // RUC Proveedor (Res. NAC-DGERCGC26-00000027) se agrega aquí, al crear —
            // así solo los documentos nuevos lo llevan; los ya emitidos no se alteran.
            $infoAdicional = is_array($data['info_adicional'] ?? null) ? $data['info_adicional'] : [];
            $infoAdicional = \App\Helpers\SriProveedorHelper::conRucProveedor($infoAdicional);
            $this->guardarInfoAdicional($idNC, $infoAdicional);
            $this->generarYGuardarXml($idNC, $data['empresa_config'] ?? []);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Asiento contable FUERA de la transacción: la NC ya está guardada; un fallo no la revierte.
        // Solo NC 'autorizado' se contabilizan: un borrador no debe generar un asiento activo,
        // o queda huérfano si el borrador se descarta después.
        try {
            if (($data['estado'] ?? 'borrador') === 'autorizado') {
                $this->procesarAsientoContable($idNC, $data);
            }
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

        // Interruptor por empresa (Configuración Contable → Módulos que contabilizan): apagado,
        // no se crea asiento a un documento que aún no lo tiene; el que ya lo tiene se mantiene al día.
        if (ContabilidadInterruptorService::crear()->omitirGeneracion($idEmpresa, 'notas_credito', 'nota_credito', $idNotaCredito)) {
            return;
        }
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
        $this->validarBodegaReintegro($data);

        $ncActual = $this->repository->getPorId($id);
        $this->validarPeriodoContableAlModificar(
            $ncActual['fecha_emision'] ?? null,
            $data['fecha_emision'] ?? null,
            (int) ($data['id_empresa'] ?? 0),
            'la nota de crédito'
        );

        // Normalizar espacios en el motivo (texto libre): colapsa espacios dobles y
        // saltos de línea a uno solo, y recorta los extremos.
        if (!empty($data['motivo'])) {
            $data['motivo'] = trim(preg_replace('/\s+/u', ' ', (string) $data['motivo']));
        }

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
            $devuelveStock = $invService->facturacionAfectaInventario((int) ($data['id_establecimiento'] ?? 0));
            $data['detalles'] = $this->enlazarLineasFactura($data['detalles'], $data, $invService);

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
                if ($devuelveStock && !empty($det['id_producto']) && !empty($data['id_bodega'])) {
                    $invService->registrarEntradaPorNC([
                        'id_empresa'      => $data['id_empresa'],
                        'id_producto'     => $det['id_producto'],
                        'id_bodega'       => $data['id_bodega'],
                        'cantidad'        => $det['cantidad'],
                        'id_referencia'   => $id,
                        'num_doc_modificado' => $data['num_doc_modificado'] ?? '',
                        'cod_doc_modificado' => $data['cod_doc_modificado'] ?? '01',
                        'linea_origen'    => $det['linea_origen'],
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
     * Cada ítem cargado desde la factura trae, oculto, el id de su línea (id_venta_detalle): de
     * ella sale el lote / NUP que vuelve al inventario. Solo se conserva si la línea es de la
     * factura que modifica la nota y del mismo producto (si el usuario cambió el producto del
     * ítem, ya no le corresponde); si no, el ítem queda sin enlace y se reparte por orden de salida.
     */
    private function enlazarLineasFactura(array $detalles, array $data, InventarioService $invService): array
    {
        $lineas = $invService->lineasFacturaParaNC(
            (int) $data['id_empresa'],
            (string) ($data['num_doc_modificado'] ?? ''),
            (string) ($data['cod_doc_modificado'] ?? '01'),
            array_column($detalles, 'id_venta_detalle')
        );

        foreach ($detalles as &$det) {
            $idLinea = (int) ($det['id_venta_detalle'] ?? 0);
            $linea   = $lineas[$idLinea] ?? null;
            if ($linea && $linea['id_producto'] !== (int) ($det['id_producto'] ?? 0)) {
                $linea = null;
            }
            $det['id_venta_detalle'] = $linea ? $idLinea : null;
            $det['linea_origen']     = $linea;
        }
        unset($det);

        return $detalles;
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

    /**
     * $esSuperAdmin (nivel 3) permite eliminar una NC que NO está en borrador
     * (autorizada o anulada) — misma excepción que FacturaVentaService::eliminar(),
     * para el caso de un documento cargado por error que no se quiere conservar.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario, bool $esSuperAdmin = false): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                throw new Exception("Nota de Crédito no encontrada.");
            }

            $estadoActual = $nc['estado'] ?? '';
            if ($estadoActual !== 'borrador' && !$esSuperAdmin) {
                throw new Exception("Solo se pueden eliminar Notas de Crédito en estado borrador.");
            }
            // Un borrador que el SRI ya recibió/autorizó no se borra: su secuencial ya
            // está ocupado allá con esa clave (ver SriDocumentoRules).
            if ($estadoActual === 'borrador' && !$esSuperAdmin) {
                \App\Rules\SriDocumentoRules::validarEliminable('nota_credito', $id, 'la nota de crédito');
            }

            // Vale también para el superadministrador: eliminar revierte el asiento y
            // el inventario, y un período cerrado no admite ese movimiento.
            $this->validarPeriodoContable(
                $nc['fecha_emision'] ?? null,
                $idEmpresa,
                'No se puede eliminar la nota de crédito porque su período contable está cerrado.'
            );

            // A propósito, sin verificación contra el SRI (a diferencia de FacturaVentaService::
            // anular()): el caso de uso es borrar del sistema un documento cargado por error/
            // duplicado sin intención de anularlo realmente — el registro en el SRI, si existe,
            // no se ve afectado por eliminar la copia local.

            // Revertir inventario (tolerante: no bloquear la eliminación si el
            // stock reintegrado ya fue consumido).
            if ((int)($nc['id_empresa'] ?? 0) > 0) {
                $invService = new \App\Services\modulos\InventarioService(
                    new \App\repositories\modulos\InventarioRepository(),
                    $this->logService
                );
                $invService->revertirMovimientosPorReferencia('nota_credito', $id, $idEmpresa, $idUsuario, true);
            }

            // Anular el asiento contable de la NC si existe (mismo patrón que
            // FacturaVentaService::eliminar()): un borrador no debería tener asiento activo
            // tras el fix en crear(), pero esto cierra el hueco para datos previos al fix.
            $idAsientoNc = (int)($nc['id_asiento_contable'] ?? 0);
            if ($idAsientoNc > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->logService
                );
                try {
                    $asientoService->anular($idAsientoNc, $idEmpresa, $idUsuario);
                } catch (\Throwable $eA) {
                    if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                        throw $eA;
                    }
                }
            }

            (new \App\repositories\modulos\CosteoVentaSeguimientoRepository())
                ->eliminar($idEmpresa, 'nota_credito_venta', $id, $idUsuario);

            // Limpiar casilleros de declaración 104 (igual que anular()) — solo aplica si
            // la NC llegó a estar autorizada y a marcar algún casillero.
            $this->limpiarCasillerosDeclaracion($idEmpresa, $id);

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                $estadoActual !== 'borrador' ? 'eliminar_forzado_superadmin' : 'eliminar',
                'notas_credito_cabecera',
                $id,
                $nc,
                ['estado_previo' => $estadoActual]
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

            // Anular revierte el asiento y el inventario de la nota: si su período
            // está cerrado, ese movimiento no puede tocarse.
            $this->validarPeriodoContable(
                $nc['fecha_emision'] ?? null,
                $idEmpresa,
                'No se puede anular la nota de crédito porque su período contable está cerrado.'
            );

            // Revertir inventario (tolerante: no bloquear la anulación si el
            // stock reintegrado ya fue consumido).
            $invService = new \App\Services\modulos\InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
            $invService->revertirMovimientosPorReferencia('nota_credito', $id, $idEmpresa, $idUsuario, true);

            // Limpiar casilleros de declaracion 104
            $this->limpiarCasillerosDeclaracion($idEmpresa, $id);

            // Anular el asiento contable de la NC si existe (antes no se hacía: una NC
            // anulada quedaba con su asiento "contabilizado" activo, igual que el hueco
            // ya corregido en eliminar()).
            $idAsientoNc = (int)($nc['id_asiento_contable'] ?? 0);
            if ($idAsientoNc > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->logService
                );
                try {
                    $asientoService->anular($idAsientoNc, $idEmpresa, $idUsuario);
                } catch (\Throwable $eA) {
                    if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                        throw $eA;
                    }
                }
            }

            (new \App\repositories\modulos\CosteoVentaSeguimientoRepository())
                ->eliminar($idEmpresa, 'nota_credito_venta', $id, $idUsuario);

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

    /**
     * Origen con que la NC de venta queda en casilleros_declaracion_sri. Debe ser el mismo que
     * esperan DeclaracionIvaRepository (limpieza de huérfanos, detalle de casilleros) y la vista
     * de la declaración; antes se grababa 'notas de credito' y esas piezas no la reconocían.
     */
    private const ORIGEN_CASILLEROS = 'notas_credito';

    /** Borra los casilleros 104 de la NC, incluidos los que quedaran con el origen antiguo. */
    private function limpiarCasillerosDeclaracion(int $idEmpresa, int $idNC): void
    {
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, self::ORIGEN_CASILLEROS, $idNC);
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'notas de credito', $idNC);
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
        $this->limpiarCasillerosDeclaracion($idEmpresa, $idNC);

        // Obtener configuración de casilleros de la empresa. La clave es 'nota_credito_venta'
        // (así la guarda EmpresaService en empresa_casilleros_iva_sri); con 'nota_credito' nunca
        // encontraba configuración y la NC no restaba nada en la declaración.
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);
        if (!$configDec || !isset($configDec['nota_credito_venta'])) return;
        $confNC = $configDec['nota_credito_venta'];

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
                        'id_empresa' => $idEmpresa, 'origen' => self::ORIGEN_CASILLEROS, 'id_origen' => $idNC,
                        'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => -1 * $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($neto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => self::ORIGEN_CASILLEROS, 'id_origen' => $idNC,
                        'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => -1 * $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($impC !== '' && $valorImp > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => self::ORIGEN_CASILLEROS, 'id_origen' => $idNC,
                        'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => -1 * $valorImp, 'concepto' => $concepto . ' (IVA)'
                    ]);
                }
            }
        }
    }
}
