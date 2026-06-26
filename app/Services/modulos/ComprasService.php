<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ComprasRepository;
use App\Rules\modulos\ComprasRules;
use App\Services\LogSistemaService;
use App\Services\modulos\PeriodosContablesService;
use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\core\Database;

class ComprasService
{
    private ComprasRepository $repository;
    private ComprasRules $rules;
    private LogSistemaService $logService;
    private PeriodosContablesService $periodosService;
    private ?string $lastAsientoWarning = null;

    public function __construct()
    {
        $this->repository = new ComprasRepository();
        $this->rules = new ComprasRules();
        $this->logService = new LogSistemaService();
        
        // Inicialización manual de dependencias del servicio de periodos
        $periodosRepo = new PeriodosContablesRepository();
        $periodosRules = new PeriodosContablesRules();
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $this->logService);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->verificarSecuencialDuplicado($data);
        $this->validarPeriodo($data, 'No se puede registrar la compra porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $data = $this->calcularTotales($data);
            $idCompra = $this->repository->insertCabecera($data);

            $this->guardarDetalles($idCompra, $data['detalles'] ?? [], $idEmpresa, (int)$data['id_proveedor'], $idUsuario);
            $this->guardarPagos($idCompra, $data['pagos'] ?? []);
            $this->guardarAdicionales($idCompra, $data['adicionales'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'CREAR', 'compras_cabecera', $idCompra,
                null, ['id_compra' => $idCompra, 'total' => $data['importe_total'] ?? 0]
            );

            $this->sincronizarCasilleros($idCompra, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la compra ya guardada.
        $this->generarAsientoTrasGuardar($idCompra, $data);
        return $idCompra;
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $compra = $this->repository->getPorId($id, $idEmpresa);
        if (!$compra) {
            return null;
        }

        $compra['detalles']    = $this->repository->getDetalles($id);
        $compra['pagos']       = $this->repository->getPagos($id);
        $compra['adicionales'] = $this->repository->getInfoAdicional($id);

        // Formatear adicionales para que sea un objeto clave:valor
        $adicionales = [];
        foreach ($compra['adicionales'] as $adj) {
            $adicionales[$adj['nombre']] = $adj['valor'];
        }
        $compra['adicionales'] = $adicionales;

        // Cargar impuestos de cada detalle
        foreach ($compra['detalles'] as &$det) {
            $det['impuestos'] = $this->repository->getImpuestosDetalle((int)$det['id']);
        }

        $compra['egresos_vinculados'] = $this->repository->getEgresosVinculados($id);

        return $compra;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE
    // ─────────────────────────────────────────────────────────────────────────

    public function getLastAsientoWarning(): ?string
    {
        return $this->lastAsientoWarning;
    }

    /**
     * Genera el asiento contable tras guardar una compra (fuera de la transacción principal).
     * Un fallo —p. ej. cuentas sin configurar— no revierte la compra: solo se registra el aviso.
     */
    private function generarAsientoTrasGuardar(int $idCompra, array $data): void
    {
        $this->lastAsientoWarning = null;
        try {
            $numDoc = ($data['establecimiento_prov'] ?? '') . '-'
                    . ($data['punto_emision_prov'] ?? '') . '-'
                    . ($data['secuencial_prov'] ?? '');
            $this->procesarAsientoContable($idCompra, $data, $numDoc);
        } catch (\Throwable $e) {
            error_log("[Compras] Asiento no generado para compra $idCompra: " . $e->getMessage());
            $this->lastAsientoWarning = $e->getMessage();
        }
    }

    /**
     * Punto de entrada del sincronizador (Estados Financieros) para compras sin asiento.
     */
    public function procesarAsientoContablePorSincronizacion(int $idCompra): void
    {
        $cabecera = $this->repository->getPorId($idCompra);
        if (!$cabecera) return;
        $numDoc = ($cabecera['establecimiento_prov'] ?? '') . '-'
                . ($cabecera['punto_emision_prov'] ?? '') . '-'
                . ($cabecera['secuencial_prov'] ?? '');
        $this->procesarAsientoContable($idCompra, $cabecera, $numDoc);
    }

    /**
     * Arma (vía AsientoBuilderService, concepto 'adquisiciones_compras') y persiste el asiento
     * de una compra. Idempotente: si ya existe asiento para esta compra, lo actualiza.
     * El enrutamiento inventariable→Inventario / resto→Gasto y la dirección (factura vs NC)
     * los resuelve el builder, evitando duplicar costo/gasto con el costeo de la venta.
     */
    public function procesarAsientoContable(int $idCompra, array $data, string $numDoc): void
    {
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? $data['created_by'] ?? $_SESSION['id_usuario'] ?? 0);
        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        $proveedorNombre = $data['proveedor_nombre'] ?? 'Proveedor';

        // Siempre regenerar desde el builder con los valores actuales del documento.
        $data['id_compra'] = $idCompra;
        $builder = new \App\Services\modulos\AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoSugerido($idEmpresa, 'adquisiciones_compras', $data);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Compra # $numDoc",
                'documento_referencia' => "Compra # $numDoc",
                'id_entidad'           => (int)($data['id_proveedor'] ?? 0),
                'tipo_entidad'         => 'proveedor',
            ];
        }

        // Documento excluido (p. ej. retención) o sin cuentas configuradas: no se genera asiento.
        if (empty($detalles)) {
            return;
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('compra', $idCompra, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fechaEmision,
            'tipo_comprobante'     => 'compras',
            'numero_comprobante'   => '',
            'concepto'             => "Compra # " . $numDoc . " - Proveedor: " . $proveedorNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'compra',
            'id_referencia_origen' => $idCompra,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idCompra, $idAsientoGenerado);
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Compra no encontrada.');
        }

        $this->rules->validar($data);
        $this->verificarSecuencialDuplicado($data, $id);
        
        // Validar tanto la fecha original como la nueva
        $this->periodosService->validarFechaPermitida(
            $cabecera['fecha_emision'], 
            $idEmpresa,
            'No se puede modificar el registro porque el periodo contable original está cerrado.'
        );
        $this->validarPeriodo($data, 'No se puede guardar el registro en la fecha seleccionada porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        
        try {
            if ($managed) $db->beginTransaction();

            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $data = $this->calcularTotales($data);
            
            // 1. Actualizar cabecera. Si falla, el catch capturará el error REAL.
            $this->repository->updateCabecera($id, $data);

            // 2. Procesar el resto solo si la cabecera fue exitosa
            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles'] ?? [], $idEmpresa, (int)$data['id_proveedor'], $idUsuario);

            $this->repository->deletePagos($id);
            $this->guardarPagos($id, $data['pagos'] ?? []);

            $this->repository->deleteInfoAdicional($id);
            $this->guardarAdicionales($id, $data['adicionales'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'MODIFICAR', 'compras_cabecera', $id,
                $cabecera, ['total' => $data['importe_total'] ?? 0]
            );

            $this->sincronizarCasilleros($id, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la compra ya guardada.
        $this->generarAsientoTrasGuardar($id, $data);
        return $id;
    }

    public function eliminar(int $id, int $idUsuario, int $idEmpresa): bool
    {
        $compra = $this->repository->getPorId($id, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }

        // Validar periodo contable antes de eliminar
        $this->periodosService->validarFechaPermitida(
            $compra['fecha_emision'], 
            $idEmpresa,
            'No se puede eliminar el registro porque el periodo contable está cerrado.'
        );

        // Validar si tiene retenciones asociadas
        $retRepo = new \App\repositories\modulos\RetencionCompraRepository();
        if ($retRepo->existeRetencionParaCompra($id, $idEmpresa)) {
            throw new \Exception("No se puede eliminar la compra porque tiene una retención asociada. Debe eliminar la retención primero.");
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            // NUEVA REGLA: Anular automáticamente cualquier Egreso asociado que no esté anulado aún
            $egresoRepo   = new \App\repositories\modulos\EgresoRepository();
            $egresoRules  = new \App\Rules\modulos\EgresoRules();
            $egresoService = new \App\Services\modulos\EgresoService($egresoRepo, $egresoRules, $this->logService);

            $egresosIds = $this->repository->getEgresosAsociados($id, $idEmpresa);
            foreach ($egresosIds as $egresoId) {
                $egresoService->anular($egresoId, $idEmpresa, $idUsuario);
            }

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ELIMINAR', 'compras_cabecera', $id,
                ['id' => $id], null
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'compras', $id);

            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function guardarDetalles(int $idCompra, array $detalles, int $idEmpresa, int $idProveedor, int $idUsuario): void
    {
        foreach ($detalles as $det) {
            $det['id_compra'] = $idCompra;
            $det['precio_total_sin_impuesto'] = (float)($det['precio_total_sin_impuesto']
                ?? ((float)($det['cantidad'] ?? 1) * (float)($det['precio_unitario'] ?? 0) - (float)($det['descuento'] ?? 0)));

            $idDetalle = $this->repository->insertDetalle($det);

            if (!empty($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    $imp['id_compra_detalle'] = $idDetalle;
                    $this->repository->insertImpuesto($imp);
                }
            }
        }
    }

    private function guardarPagos(int $idCompra, array $pagos): void
    {
        foreach ($pagos as $pago) {
            $pago['id_compra'] = $idCompra;
            $this->repository->insertPago($pago);
        }
    }

    private function guardarAdicionales(int $idCompra, array $adicionales): void
    {
        foreach ($adicionales as $campo => $valor) {
            if ($valor !== null && $valor !== '') {
                $this->repository->insertInfoAdicional([
                    'id_compra' => $idCompra,
                    'nombre'    => $campo,
                    'valor'     => (string)$valor
                ]);
            }
        }
    }

    private function calcularTotales(array $data): array
    {
        $subtotal = 0;
        $descuento = 0;
        $total = 0;

        foreach ($data['detalles'] ?? [] as $det) {
            $cant = (float)($det['cantidad'] ?? 0);
            $prec = (float)($det['precio_unitario'] ?? 0);
            $desc = (float)($det['descuento'] ?? 0);
            
            $sub = $cant * $prec;
            $subtotal += $sub;
            $descuento += $desc;

            // Sumar impuestos del detalle
            if (!empty($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    $total += (float)($imp['valor'] ?? 0);
                }
            }
        }

        $data['total_sin_impuestos'] = $subtotal - $descuento;
        $data['total_descuento']     = $descuento;
        $data['importe_total']       = $data['total_sin_impuestos'] + ($total) + (float)($data['propina'] ?? 0);

        return $data;
    }

    private function verificarSecuencialDuplicado(array $data, ?int $excludeId = null): void
    {
        $existe = $this->repository->existeSecuencial(
            (int)$data['id_empresa'],
            (int)$data['id_proveedor'],
            $data['establecimiento_prov'] ?? '',
            $data['punto_emision_prov'] ?? '',
            $data['secuencial_prov'] ?? '',
            $data['tipo_comprobante'] ?? '01',
            $excludeId
        );

        if ($existe) {
            throw new \Exception('Ya existe una compra registrada con ese número de comprobante para este proveedor.');
        }
    }

    private function validarPeriodo(array $data, ?string $mensaje = null): void
    {
        $fecha = $data['fecha_emision'] ?? null;
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        
        if ($fecha && $idEmpresa) {
            $this->periodosService->validarFechaPermitida($fecha, $idEmpresa, $mensaje);
        }
    }

    public function sincronizarCasilleros(int $idCompra, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;
        
        if (!$data) {
            $cabecera = $this->repository->getPorId($idCompra);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
            
            // Get details and taxes if we fetched from DB
            $data['detalles'] = $this->repository->getDetalles($idCompra);
            foreach ($data['detalles'] as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        // 'deducible' determines the grouping key. Default to 'declaracion_iva' if empty.
        $deducible = $data['deducible'] ?? 'declaracion_iva';
        if ($deducible === '') $deducible = 'declaracion_iva';
        
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'compras', $idCompra);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);

        // Para compras usamos 'factura_compra'
        $keyDocumento = 'factura_compra';
        // Podríamos en el futuro añadir factura_compra_no_deducible si existiera en configDec.
        if (isset($data['tipo_comprobante']) && $data['tipo_comprobante'] === '02') {
             $keyDocumento = 'nota_venta_compra';
        }

        if (!$configDec || !isset($configDec[$keyDocumento])) return;
        $confCompras = $configDec[$keyDocumento];

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
                if (!$tarifaKey || !isset($confCompras[$tarifaKey])) continue;

                $c = $confCompras[$tarifaKey];
                $bruto = $c['bruto'] ?? '';
                $neto = $c['neto'] ?? '';
                $impC = $c['impuesto'] ?? '';

                $base = (float)($imp['base_imponible'] ?? 0);
                $valorImp = (float)($imp['valor'] ?? 0);

                if ($bruto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($neto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($impC !== '' && $valorImp > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => $valorImp, 'concepto' => $concepto . ' (IVA)'
                    ]);
                }
            }
        }
    }
}
