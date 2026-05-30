<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\models\TarifaIva;
use App\Rules\modulos\FacturaVentaRules;
use App\Services\LogSistemaService;
use App\core\Database;

class FacturaVentaService
{
    private FacturaVentaRepository $repository;
    private FacturaVentaRules $rules;
    private LogSistemaService $logService;
    private ?BodegaService $bodegaService = null;
    private ?InventarioService $inventarioService = null;
    private ?EmpresaRepository $empresaRepository = null;
    private ?string $lastAsientoWarning = null;

    public function getLastAsientoWarning(): ?string
    {
        return $this->lastAsientoWarning;
    }

    public function __construct(FacturaVentaRepository $repository, FacturaVentaRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Genera automáticamente un Ingreso (cobro) a partir de una transacción
     * Payphone APROBADA para una factura de venta. Idempotente: no crea el ingreso
     * si la transacción ya tiene uno vinculado.
     *
     * @param array $trans Fila de payphone_transacciones (debe tener id_empresa, id_referencia, monto, etc.)
     * @return int|null  ID del ingreso creado, o null si no aplica / ya existía.
     */
    public function generarIngresoDesdePayphone(array $trans): ?int
    {
        // Ya tiene ingreso vinculado → idempotente
        if (!empty($trans['id_ingreso'])) {
            return (int) $trans['id_ingreso'];
        }
        if (($trans['modulo'] ?? '') !== 'factura_venta' || empty($trans['id_referencia'])) {
            return null;
        }

        $db        = Database::getConnection();
        $idEmpresa = (int) $trans['id_empresa'];
        $idFactura = (int) $trans['id_referencia'];
        $monto     = round(((int) $trans['monto']) / 100, 2);
        $idUsuario = !empty($trans['created_by']) ? (int) $trans['created_by'] : null;

        // Forma de cobro "Tarjeta"
        $ppRepo = new \App\repositories\PayphoneRepository();
        $formaTarjeta = $ppRepo->getFormaCobroTarjeta($idEmpresa);
        if (!$formaTarjeta) {
            return null; // sin forma de cobro Tarjeta no se puede registrar
        }

        // Factura
        $factura = $this->repository->getPorId($idFactura);
        if (!$factura || (int) $factura['id_empresa'] !== $idEmpresa) {
            return null;
        }

        // Punto de emisión de la factura (con fallback a tabla renombrada)
        $punto = null;
        try {
            $stPunto = $db->prepare(
                "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto, p.id_establecimiento
                 FROM empresa_punto_emision p
                 JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id = ? AND p.id_empresa = ? AND p.eliminado = false"
            );
            $stPunto->execute([(int) $factura['id_punto_emision'], $idEmpresa]);
            $punto = $stPunto->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}
        if (!$punto) {
            try {
                $stPunto2 = $db->prepare(
                    "SELECT id, establecimiento, punto, id_establecimiento
                     FROM empresa_puntos_emision
                     WHERE id = ? AND id_empresa = ? AND eliminado = false"
                );
                $stPunto2->execute([(int) $factura['id_punto_emision'], $idEmpresa]);
                $punto = $stPunto2->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {}
        }
        if (!$punto) {
            return null;
        }

        // Secuencial de ingreso
        $secuencialService = new \App\Services\SecuencialService();
        $secRes = $secuencialService->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos');

        // Saldo anterior (cobros previos)
        $stSaldo = $db->prepare(
            "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
             FROM ingresos_detalle id2
             INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
             WHERE id2.tipo_documento = 'FACTURA'
               AND id2.id_referencia_documento = ?
               AND ic2.estado != 'anulado'
               AND ic2.eliminado = false"
        );
        $stSaldo->execute([$idFactura]);
        $totalCobrado  = (float) $stSaldo->fetchColumn();
        $saldoAnterior = round((float) $factura['importe_total'] - $totalCobrado, 2);

        $numDoc = $factura['establecimiento'] . '-' . $factura['punto_emision'] . '-' . $factura['secuencial'];
        $obs    = 'Cobro con tarjeta (Payphone) — autorización ' . ($trans['authorization_code'] ?? '');

        // Número de ingreso con formato 000-000-000000000
        $numeroIngreso = str_pad((string) $punto['establecimiento'], 3, '0', STR_PAD_LEFT) . '-'
                       . str_pad((string) $punto['punto'], 3, '0', STR_PAD_LEFT) . '-'
                       . str_pad((string) $secRes['secuencial'], 9, '0', STR_PAD_LEFT);

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_establecimiento'  => (int) ($punto['id_establecimiento'] ?? 0),
            'id_punto_emision'    => (int) $punto['id'],
            'id_cliente'          => (int) $factura['id_cliente'],
            'id_usuario'          => $idUsuario,
            'fecha_emision'       => date('Y-m-d'),
            'establecimiento'     => $punto['establecimiento'],
            'punto_emision'       => $punto['punto'],
            'secuencial'          => $secRes['secuencial'],
            'numero_ingreso'      => $numeroIngreso,
            'tipo_ingreso'        => 'FACTURA_VENTA',
            'id_ingreso_concepto' => null,
            'monto_total'         => $monto,
            'observaciones'       => $obs,
            'recibo_de'           => $factura['cliente_nombre'] ?? '',
            'id_recibo_cliente'   => (int) $factura['id_cliente'],
            'detalles'            => [[
                'tipo_documento'          => 'FACTURA',
                'id_referencia_documento' => $idFactura,
                'numero_documento'        => $numDoc,
                'descripcion'             => 'Cobro de factura ' . $numDoc,
                'monto_documento'         => (float) $factura['importe_total'],
                'saldo_anterior'          => $saldoAnterior,
                'monto_cobrado'           => $monto,
                'saldo_actual'            => max(0.0, $saldoAnterior - $monto),
            ]],
            'pagos' => [[
                'id_forma_cobro'          => (int) $formaTarjeta['id'],
                'monto'                   => $monto,
                'referencia'              => $trans['authorization_code'] ?? null,
                'tipo_operacion_bancaria' => null,
                'numero_cheque'           => null,
            ]],
        ];

        $ingresoService = new IngresoService(
            new \App\repositories\modulos\IngresoRepository(),
            new \App\Rules\modulos\IngresoRules(),
            $this->logService
        );

        $idIngreso = $ingresoService->crear($payload);

        // Vincular para idempotencia
        $ppRepo->vincularIngreso((string) $trans['client_transaction_id'], (int) $idIngreso);

        return (int) $idIngreso;
    }

    private function getInventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
        }
        return $this->inventarioService;
    }

    private function getBodegaService(): BodegaService
    {
        if ($this->bodegaService === null) {
            $this->bodegaService = new BodegaService(
                new \App\repositories\modulos\BodegaRepository(),
                new \App\Rules\modulos\BodegaRules(),
                $this->logService
            );
        }
        return $this->bodegaService;
    }

    private function getEmpresaRepository(): EmpresaRepository
    {
        if ($this->empresaRepository === null) {
            $this->empresaRepository = new EmpresaRepository();
        }
        return $this->empresaRepository;
    }

    /**
     * Genera el XML de la factura a partir de los datos persistidos en BD
     * y lo guarda en ventas_cabecera.detalle_xml.
     * Los errores se silencian para no revertir la factura ya guardada.
     *
     * @param int   $idVenta      ID de la factura recién guardada
     * @param array $empresaConfig Fila de la tabla empresas (ya cargada en el servicio)
     */
    private function generarYGuardarXml(int $idVenta, array $empresaConfig): void
    {
        try {
            // Cargar datos actualizados desde BD
            $cabecera = $this->repository->getPorId($idVenta);
            if (!$cabecera) {
                return;
            }

            $detalles = $this->repository->getDetalles($idVenta);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($idVenta);
            $infoAdicional = $this->repository->getInfoAdicional($idVenta);

            // Empresa: usar la config ya disponible; si está vacía cargar del modelo
            $empresa = $empresaConfig;
            if (empty($empresa)) {
                $empresaModel = new \App\models\Empresa();
                $empresa = $empresaModel->getPorId((int)$cabecera['id_empresa']) ?? [];
            }

            // Dirección del establecimiento
            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = $this->getEmpresaRepository();
                    foreach ($estRepo->getEstablecimientos((int)$cabecera['id_empresa']) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            $xmlService = new \App\Services\Xml\XmlFacturaVentaService();
            $xmlString  = $xmlService->generar(
                $cabecera,
                $detalles,
                $pagos,
                $infoAdicional,
                $empresa,
                $dirEstablecimiento
            );

            $this->repository->updateDetalleXml($idVenta, $xmlString);
        } catch (\Throwable $e) {
            error_log("[FacturaVenta] XML no generado para factura $idVenta: " . $e->getMessage());
        }
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }

    /**
     * Valida que el secuencial no esté duplicado
     */
    private function validarSecuencial(array $data, ?int $excluirId = null): void
    {
        if ($this->repository->existeSecuencial(
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (string) $data['secuencial'],
            $excluirId
        )) {
            throw new \Exception('El número de secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }
    }

    public function actualizarVendedor(int $id, ?int $idVendedor, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \Exception('Factura no encontrada.');
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $this->repository->actualizarVendedor($id, $idVendedor, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR_VENDEDOR',
                'ventas_cabecera',
                $id,
                ['id_vendedor' => $cabecera['id_vendedor']], // old
                ['id_vendedor' => $idVendedor] // new
            );

            if ($managedTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): int
    {
        // Solo se pueden actualizar facturas en estado borrador
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== (int)$data['id_empresa']) {
            throw new \Exception('Factura no encontrada.');
        }
        if (($cabecera['estado'] ?? '') !== 'borrador') {
            throw new \Exception('Solo se pueden modificar facturas en estado borrador.');
        }

        $this->validarSecuencial($data, $id);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');

        // Regenerar clave de acceso reutilizando el código numérico original
        $codigoNumerico = \App\Services\ClaveAccesoService::extraerCodigoNumerico($cabecera['clave_acceso'] ?? '');
        $data['clave_acceso'] = \App\Services\ClaveAccesoService::generar(
            (string) ($data['fecha_emision']  ?? ''),
            \App\Services\ClaveAccesoService::FACTURA_VENTA,
            (string) ($empresaConfig['ruc']   ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision'],
            $codigoNumerico
        );

        // 1. Validar reglas de negocio centralizadas
        $estConfig = $this->getEmpresaRepository()->getEstablecimientoConfig((int)$data['id_establecimiento']);
        $clienteInfo = $this->repository->getTipoIdCliente((int)$data['id_cliente'], (int)$data['id_empresa']);
        $data['tipo_id_cliente'] = $clienteInfo['tipo_identificacion'] ?? '';

        if (($clienteInfo['es_consumidor_final'] ?? false) || $data['tipo_id_cliente'] === '07') {
            $data['tipo_id_cliente'] = '07';
        }

        // 1. Enriquecer detalles con info de inventario antes de validar reglas
        if (!empty($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as &$det) {
                // Normalizar nombre
                if (empty($det['nombre']) && !empty($det['descripcion'])) {
                    $det['nombre'] = $det['descripcion'];
                }
                
                // Obtener info de control si es producto de catálogo
                if (!empty($det['id_producto'])) {
                    $infoInv = $this->getInventarioService()->getProductoRepository()->getInfoControlInventario((int)$det['id_producto'], (int)$data['id_empresa']);
                    $det['inventariable']   = $infoInv['inventariable'] ?? false;
                    $det['tipo_produccion'] = $infoInv['tipo_produccion'] ?? '';
                }
            }
        }

        $this->rules->validar($data, $estConfig ?? []);

        // 2. Lógica de selección automática de lotes/stock si aplica (CON AGREGACIÓN PARA VALIDACIÓN)
        if ($estConfig && !empty($data['detalles']) && is_array($data['detalles'])) {
            $cantidadesAgregadas = []; // [id_producto_bodega_lote] => total_cantidad
            
            // Primero, normalizar y asignar lotes 'sin_lote' si no son obligatorios
            foreach ($data['detalles'] as &$det) {
                if (!empty($det['id_producto'])) {
                    $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
                    $afectaInv = $toBool($estConfig['facturacion_inventario'] ?? false);
                    $obliLotes = $toBool($estConfig['obligatorio_lotes'] ?? false);

                    if ($det['inventariable'] && $afectaInv && $det['tipo_produccion'] !== '02' && empty($det['es_libre'])) {
                        if (!$obliLotes && empty($det['lote'])) {
                            $det['lote']      = 'sin_lote';
                            $det['caducidad'] = date('Y-m-d');
                            $det['nup']       = null;
                        }
                        
                        // Agregar cantidad para validación posterior
                        $key = (int)$det['id_producto'] . '_' . (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)) . '_' . ($det['lote'] ?? 'sin_lote');
                        $cantidadesAgregadas[$key] = ($cantidadesAgregadas[$key] ?? 0) + (float)($det['cantidad'] ?? 0);
                    }
                }
            }
            unset($det);

            // Segundo, validar stock acumulado si se requiere saldo positivo
            $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
            $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);
            
            if ($soloStockPos) {
                $validados = [];
                foreach ($data['detalles'] as $det) {
                    if (!empty($det['id_producto']) && $det['inventariable'] && empty($det['es_libre']) && $det['tipo_produccion'] !== '02') {
                        $key = (int)$det['id_producto'] . '_' . (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)) . '_' . ($det['lote'] ?? 'sin_lote');
                        
                        if (!in_array($key, $validados)) {
                            $idActual = isset($id) ? (int)$id : (isset($idVenta) ? (int)$idVenta : null);
                            $stockTotal = $this->getInventarioService()->getRepository()->getStockActual(
                                (int)$det['id_producto'],
                                (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)),
                                (int)$data['id_empresa'],
                                $idActual,
                                ($idActual ? 'factura_venta' : null),
                                !empty($det['lote']) ? (string)$det['lote'] : null
                            );
                            
                            $cantidadAcumulada = $cantidadesAgregadas[$key];
                            if ($stockTotal < $cantidadAcumulada) {
                                throw new \Exception("Stock insuficiente para el producto: " . ($det['nombre'] ?? 'Producto') . " (Lote: ".($det['lote'] ?? 'sin_lote')."). Saldo actual: {$stockTotal}, Requerido en factura: {$cantidadAcumulada}");
                            }
                            $validados[] = $key;
                        }
                    }
                }
            }
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];
            $nivel     = (int) ($_SESSION['nivel'] ?? 1);

            // Validar acceso a bodega
            $idBodega = (int) ($data['id_bodega'] ?? 0);
            if ($idBodega > 0 && !$this->getBodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \Exception('Acceso denegado a la bodega seleccionada.');
            }

            // Actualizar cabecera
            $this->repository->updateCabecera($id, $data);

            // Reemplazar detalles: eliminar y reinsertar
            $this->repository->deleteDetalles($id);
            if (!empty($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as &$d) {
                    if (!empty($d['es_libre']) && $d['es_libre'] == '1' && empty($d['id_producto'])) {
                        $d['id_producto'] = $this->repository->crearServicioLibre(
                            $idEmpresa,
                            $idUsuario,
                            $d['nombre'] ?? ($d['descripcion'] ?? ''),
                            (float) ($d['precio_unitario'] ?? 0),
                            isset($d['porcentaje_iva']) ? (float) $d['porcentaje_iva'] : null
                        );
                    }
                    $d['id_venta'] = $id;
                    $idDetalle     = $this->repository->insertDetalle($d);
                    $d['id']       = $idDetalle; // Guardar ID para registrarCasillerosFv

                    if (!empty($d['lote']) || !empty($d['caducidad']) || !empty($d['nup'])) {
                        $this->repository->updateDetalleLoteNup($idDetalle, [
                            'numero_lote'    => !empty($d['lote'])      ? $d['lote']      : null,
                            'fecha_caducidad' => !empty($d['caducidad']) ? $d['caducidad'] : null,
                            'nup'            => !empty($d['nup'])       ? $d['nup']       : null,
                        ]);
                    }

                    if (!empty($d['impuestos'])) {
                        foreach ($d['impuestos'] as $imp) {
                            $imp['id_venta_detalle'] = $idDetalle;
                            $this->repository->insertImpuesto($imp);
                        }
                    }
                }
            }
            unset($d);



            // Reemplazar pagos
            $this->repository->deletePagos($id);
            if (!empty($data['pagos']) && is_array($data['pagos'])) {
                foreach ($data['pagos'] as $p) {
                    $p['id_venta'] = $id;
                    $this->repository->insertPago($p);
                }
            }

            // Reemplazar info adicional
            $this->repository->deleteInfoAdicional($id);
            if (!empty($data['info_adicional']) && is_array($data['info_adicional'])) {
                foreach ($data['info_adicional'] as $ia) {
                    $ia['id_venta'] = $id;
                    $this->repository->insertInfoAdicional($ia);
                }
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'MODIFICAR',
                'ventas_cabecera',
                $id,
                $cabecera,
                ['id_venta' => $id, 'total' => $data['importe_total'] ?? 0]
            );

            // 5. Procesar Inventario: Primero revertir previos, luego registrar nuevos
            $numFactura = $data['establecimiento'] . '-' . $data['punto_emision'] . '-' . str_pad((string)$data['secuencial'], 9, '0', STR_PAD_LEFT);
            $this->getInventarioService()->revertirMovimientosPorReferencia('factura_venta', $id, $idEmpresa, $idUsuario);
            $this->getInventarioService()->procesarSalidaPorVenta(
                $id,
                $data['detalles'] ?? [],
                (int)$data['id_establecimiento'],
                $idEmpresa,
                $idUsuario,
                "Factura # $numFactura",
                true // esEdicion
            );

            $db->commit();

            // Generar XML y persistir en detalle_xml FUERA de la transacción principal
            $this->generarYGuardarXml($id, $data['empresa_config'] ?? []);

            // Generar/actualizar asiento contable FUERA de la transacción principal
            // para que un fallo en el asiento nunca revierta la factura ya guardada.
            $this->lastAsientoWarning = null;
            try {
                $this->procesarAsientoContable($id, $data, $numFactura);
            } catch (\Throwable $eAsiento) {
                error_log("[FacturaVenta] Asiento no generado para factura $id: " . $eAsiento->getMessage());
                $this->lastAsientoWarning = $eAsiento->getMessage();
            }

            return $id;
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function crear(array $data): int
    {
        $this->validarSecuencial($data);

        // Tomar tipo_ambiente y tipo_emision desde la configuración de empresa
        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');
        $data['estado_correo'] = 'pendiente';

        // Generar clave de acceso con código numérico nuevo
        $data['clave_acceso'] = \App\Services\ClaveAccesoService::generar(
            (string) ($data['fecha_emision']   ?? ''),
            \App\Services\ClaveAccesoService::FACTURA_VENTA,
            (string) ($empresaConfig['ruc']    ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision']
        );

        // 1. Validar reglas de negocio centralizadas
        $estConfig = $this->getEmpresaRepository()->getEstablecimientoConfig((int)$data['id_establecimiento']);
        $clienteInfo = $this->repository->getTipoIdCliente((int)$data['id_cliente'], (int)$data['id_empresa']);
        $data['tipo_id_cliente'] = $clienteInfo['tipo_identificacion'] ?? '';

        if (($clienteInfo['es_consumidor_final'] ?? false) || $data['tipo_id_cliente'] === '07') {
            $data['tipo_id_cliente'] = '07';
        }

        // 1. Enriquecer detalles con info de inventario antes de validar reglas
        if (!empty($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as &$det) {
                // Normalizar nombre
                if (empty($det['nombre']) && !empty($det['descripcion'])) {
                    $det['nombre'] = $det['descripcion'];
                }
                
                // Obtener info de control si es producto de catálogo
                if (!empty($det['id_producto'])) {
                    $infoInv = $this->getInventarioService()->getProductoRepository()->getInfoControlInventario((int)$det['id_producto'], (int)$data['id_empresa']);
                    $det['inventariable']   = $infoInv['inventariable'] ?? false;
                    $det['tipo_produccion'] = $infoInv['tipo_produccion'] ?? '';
                }
            }
        }

        $this->rules->validar($data, $estConfig ?? []);

        // 2. Lógica de selección automática de lotes/stock si aplica (CON AGREGACIÓN PARA VALIDACIÓN)
        if ($estConfig && !empty($data['detalles']) && is_array($data['detalles'])) {
            $cantidadesAgregadas = []; // [id_producto_bodega_lote] => total_cantidad
            
            // Primero, normalizar y asignar lotes 'sin_lote' si no son obligatorios
            foreach ($data['detalles'] as &$det) {
                if (!empty($det['id_producto'])) {
                    $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
                    $afectaInv = $toBool($estConfig['facturacion_inventario'] ?? false);
                    $obliLotes = $toBool($estConfig['obligatorio_lotes'] ?? false);

                    if ($det['inventariable'] && $afectaInv && $det['tipo_produccion'] !== '02' && empty($det['es_libre'])) {
                        if (!$obliLotes && empty($det['lote'])) {
                            $det['lote']      = 'sin_lote';
                            $det['caducidad'] = date('Y-m-d');
                            $det['nup']       = null;
                        }
                        
                        // Agregar cantidad para validación posterior
                        $key = (int)$det['id_producto'] . '_' . (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)) . '_' . ($det['lote'] ?? 'sin_lote');
                        $cantidadesAgregadas[$key] = ($cantidadesAgregadas[$key] ?? 0) + (float)($det['cantidad'] ?? 0);
                    }
                }
            }
            unset($det);

            // Segundo, validar stock acumulado si se requiere saldo positivo
            $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
            $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);
            
            if ($soloStockPos) {
                $validados = [];
                foreach ($data['detalles'] as $det) {
                    if (!empty($det['id_producto']) && $det['inventariable'] && empty($det['es_libre']) && $det['tipo_produccion'] !== '02') {
                        $key = (int)$det['id_producto'] . '_' . (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)) . '_' . ($det['lote'] ?? 'sin_lote');
                        
                        if (!in_array($key, $validados)) {
                            $idActual = isset($id) ? (int)$id : (isset($idVenta) ? (int)$idVenta : null);
                            $stockTotal = $this->getInventarioService()->getRepository()->getStockActual(
                                (int)$det['id_producto'],
                                (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)),
                                (int)$data['id_empresa'],
                                $idActual,
                                ($idActual ? 'factura_venta' : null),
                                !empty($det['lote']) ? (string)$det['lote'] : null
                            );
                            
                            $cantidadAcumulada = $cantidadesAgregadas[$key];
                            if ($stockTotal < $cantidadAcumulada) {
                                throw new \Exception("Stock insuficiente para el producto: " . ($det['nombre'] ?? 'Producto') . " (Lote: ".($det['lote'] ?? 'sin_lote')."). Saldo actual: {$stockTotal}, Requerido en factura: {$cantidadAcumulada}");
                            }
                            $validados[] = $key;
                        }
                    }
                }
            }
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];
            $nivel     = (int) ($_SESSION['nivel'] ?? 1);

            // Validar acceso a bodega
            $idBodega = (int) ($data['id_bodega'] ?? 0);
            if ($idBodega > 0 && !$this->getBodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \Exception('Acceso denegado a la bodega seleccionada.');
            }

            $idVenta   = $this->repository->insertCabecera($data);

            if (!empty($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as &$d) {
                    if (!empty($d['es_libre']) && $d['es_libre'] == '1' && empty($d['id_producto'])) {
                        $d['id_producto'] = $this->repository->crearServicioLibre(
                            $idEmpresa,
                            $idUsuario,
                            $d['nombre'] ?? ($d['descripcion'] ?? ''),
                            (float) ($d['precio_unitario'] ?? 0),
                            isset($d['porcentaje_iva']) ? (float) $d['porcentaje_iva'] : null
                        );
                    }
                    $d['id_venta'] = $idVenta;
                    $idDetalle     = $this->repository->insertDetalle($d);
                    $d['id']       = $idDetalle; // Guardar ID para registrarCasillerosFv

                    if (!empty($d['lote']) || !empty($d['caducidad']) || !empty($d['nup'])) {
                        $this->repository->updateDetalleLoteNup($idDetalle, [
                            'numero_lote'    => !empty($d['lote'])      ? $d['lote']      : null,
                            'fecha_caducidad' => !empty($d['caducidad']) ? $d['caducidad'] : null,
                            'nup'            => !empty($d['nup'])       ? $d['nup']       : null,
                        ]);
                    }

                    if (!empty($d['impuestos'])) {
                        foreach ($d['impuestos'] as $imp) {
                            $imp['id_venta_detalle'] = $idDetalle;
                            $this->repository->insertImpuesto($imp);
                        }
                    }
                }
            }
            unset($d);



            if (!empty($data['pagos']) && is_array($data['pagos'])) {
                foreach ($data['pagos'] as $p) {
                    $p['id_venta'] = $idVenta;
                    $this->repository->insertPago($p);
                }
            }

            if (!empty($data['info_adicional']) && is_array($data['info_adicional'])) {
                foreach ($data['info_adicional'] as $ia) {
                    $ia['id_venta'] = $idVenta;
                    $this->repository->insertInfoAdicional($ia);
                }
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR',
                'ventas_cabecera',
                $idVenta,
                null,
                ['id_venta' => $idVenta, 'total' => $data['importe_total'] ?? 0]
            );

            // Procesar Inventario
            $numFactura = $data['establecimiento'] . '-' . $data['punto_emision'] . '-' . str_pad((string)$data['secuencial'], 9, '0', STR_PAD_LEFT);
            $this->getInventarioService()->procesarSalidaPorVenta(
                $idVenta,
                $data['detalles'] ?? [],
                (int)$data['id_establecimiento'],
                $idEmpresa,
                $idUsuario,
                "Factura # $numFactura"
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Generar XML y persistir en detalle_xml FUERA de la transacción principal
        $this->generarYGuardarXml($idVenta, $data['empresa_config'] ?? []);

        // Generar asiento contable FUERA de la transacción principal
        // para que un fallo en el asiento nunca revierta la factura ya guardada.
        $this->lastAsientoWarning = null;
        try {
            $this->procesarAsientoContable($idVenta, $data, $numFactura);
        } catch (\Throwable $eAsiento) {
            error_log("[FacturaVenta] Asiento no generado para factura $idVenta: " . $eAsiento->getMessage());
            $this->lastAsientoWarning = $eAsiento->getMessage();
        }

        return $idVenta;
    }



    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $res = $this->repository->getPorId($id);
        if (!$res || (int)$res['id_empresa'] !== $idEmpresa) return null;

        $res['detalles']       = $this->repository->getDetalles($id);
        foreach ($res['detalles'] as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);

        $res['pagos']          = $this->repository->getPagos($id);
        $res['info_adicional'] = $this->repository->getInfoAdicional($id);


        return $res;
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \Exception('Factura no encontrada.');
        }

        if (($cabecera['estado'] ?? '') === 'anulado') {
            throw new \Exception('La factura ya está anulada.');
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $this->repository->actualizarEstado($id, 'anulado', $idUsuario);

            // Revertir inventario
            $this->getInventarioService()->revertirMovimientosPorReferencia('factura_venta', $id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR',
                'ventas_cabecera',
                $id,
                $cabecera,
                ['id_venta' => $id, 'nuevo_estado' => 'anulado']
            );

            if ($managedTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \Exception('Factura no encontrada.');
        }

        if (($cabecera['estado'] ?? '') !== 'borrador') {
            throw new \Exception('Solo se pueden eliminar facturas en estado borrador.');
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $this->repository->eliminarLogico($id, $idUsuario);

            // Revertir inventario si existiera algo (aunque en borrador no debería haber kardex, pero por seguridad)
            $this->getInventarioService()->revertirMovimientosPorReferencia('factura_venta', $id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'ventas_cabecera',
                $id,
                $cabecera,
                ['id_venta' => $id, 'eliminado' => true]
            );

            if ($managedTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function obtenerAsientoSugerido(int $idEmpresa, array $invoiceData): array
    {
        $builder = new \App\Services\modulos\AsientoBuilderService();
        return $builder->generarAsientoSugerido($idEmpresa, 'ventas_factura', $invoiceData);
    }

    public function procesarAsientoContable(int $idVenta, array $data, string $numFactura): void
    {
        $idEmpresa = (int)$data['id_empresa'];
        $idUsuario = (int)$data['id_usuario'];
        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        
        $clienteRepo = new \App\repositories\modulos\ClienteRepository();
        $cliente = $clienteRepo->findById((int)$data['id_cliente'], $idEmpresa);
        $clienteNombre = $cliente['nombre'] ?? 'Consumidor Final';

        // Siempre regenerar el asiento desde el builder usando los valores actuales de la factura.
        // El asiento manual del frontend se ignora: para borradores siempre se recalcula,
        // y para autorizados este método nunca se llama (ver guardarAjax).
        $data['id_venta'] = $idVenta;
        $detallesSugeridos = $this->obtenerAsientoSugerido($idEmpresa, $data);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Factura # $numFactura",
                'documento_referencia' => "Factura # $numFactura",
                'id_entidad'           => (int)$data['id_cliente'],
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles)) {
            return;
        }

        $asientoRepo = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('factura_venta', $idVenta, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'               => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'    => $fechaEmision,
            'tipo_comprobante' => 'ventas',
            // Para UPDATE el numero_comprobante no se modifica (no está en updateCabecera).
            // Para INSERT nuevo (idAsiento == 0) se deja vacío para que guardarAsiento
            // auto-genere un número único con el prefijo VE-, evitando duplicados cuando
            // el asiento anterior fue anulado y tenía el mismo "VF-<factura>".
            'numero_comprobante' => '',
            'concepto'         => "Factura # " . $numFactura . " - Cliente: " . $clienteNombre,
            'estado'           => 'contabilizado',
            'modulo_origen'    => 'factura_venta',
            'id_referencia_origen' => $idVenta,
            'observaciones'    => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idVenta, $idAsientoGenerado);
    }
}
