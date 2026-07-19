<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ConsignacionVentaEntregaRepository;
use App\repositories\modulos\ConsignacionVentaRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\ConsignacionVentaRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;

class ConsignacionVentaService
{
    private ConsignacionVentaRepository $repository;
    private ConsignacionVentaRules $rules;
    private LogSistemaService $logService;
    private InventarioRepository $inventarioRepo;

    public function __construct(
        ConsignacionVentaRepository $repository,
        ConsignacionVentaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
        $this->inventarioRepo = new InventarioRepository();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) return null;

        $detalles = $this->repository->getDetalles($id, $idEmpresa);
        $repoProd = new \App\repositories\modulos\ProductoRepository();
        foreach ($detalles as &$d) {
            $d['precios_lista'] = $repoProd->getPrecios((int)$d['id_producto'], $idEmpresa);
        }
        unset($d);

        $cabecera['detalles']      = $detalles;
        $cabecera['tiene_factura'] = $this->tieneFacturaAsociada($id, $idEmpresa);
        return $cabecera;
    }

    /**
     * ¿La consignación tiene una factura de venta activa asociada?
     *
     * El vínculo vive en la tabla puente `consignaciones_facturas` (una fila activa por
     * factura generada desde la consignación). RESILIENTE: si la tabla no existe todavía
     * (migración pendiente), devuelve false y no restringe la edición.
     */
    public function tieneFacturaAsociada(int $id, int $idEmpresa): bool
    {
        try {
            $facturaRepo = new \App\repositories\modulos\ConsignacionFacturaRepository();
            return !empty($facturaRepo->getFacturadoPorConsignacion($id, $idEmpresa));
        } catch (\Throwable $e) {
            return false; // aún no existe el vínculo → no restringe
        }
    }

    public function crear(array $data): int
    {
        $this->rules->validarCreacion($data);
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $idEmpresa = $data['id_empresa'];
            $idUsuario = $data['id_usuario'];

            // Cabecera
            $cabecera = [
                'id_empresa' => $idEmpresa,
                'fecha_emision' => $data['fecha_emision'],
                'serie' => $data['serie'],
                'secuencial' => str_pad((string)($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT),
                'id_punto_emision' => $data['id_punto_emision'] ?? null,
                'establecimiento' => $data['establecimiento'] ?? null,
                'punto_emision' => $data['punto_emision'] ?? null,
                'tipo_ambiente' => (string) ($data['empresa_config']['tipo_ambiente'] ?? '1'),
                'id_vendedor' => empty($data['id_vendedor']) ? null : (int) $data['id_vendedor'],
                'id_cliente' => (int) $data['id_cliente'],
                'punto_partida' => $data['punto_partida'] ?? '',
                'punto_llegada' => $data['punto_llegada'] ?? '',
                'observaciones' => $data['observaciones'] ?? '',
                'fecha_entrega' => empty($data['fecha_entrega']) ? null : $data['fecha_entrega'],
                'hora_entrega_desde' => empty($data['hora_entrega_desde']) ? null : $data['hora_entrega_desde'],
                'hora_entrega_hasta' => empty($data['hora_entrega_hasta']) ? null : $data['hora_entrega_hasta'],
                'id_responsable_traslado' => empty($data['id_responsable_traslado']) ? null : (int) $data['id_responsable_traslado'],
                'estado' => 'Emitida',
                'subtotal' => $data['subtotal'] ?? 0,
                'impuesto' => $data['impuesto'] ?? 0,
                'total' => $data['total'] ?? 0,
                'created_by' => $idUsuario,
                'updated_by' => $idUsuario,
            ];

            $idConsignacion = $this->repository->create($cabecera);

            // Detalles y Kardex
            $detalles = $data['detalles'] ?? [];
            foreach ($detalles as $det) {
                $det['id_consignacion'] = $idConsignacion;
                $det['id_bodega'] = (int) ($det['id_bodega'] ?? ($data['id_bodega'] ?? 0));
                
                $sqlDet = "INSERT INTO consignaciones_ventas_detalles (
                               id_consignacion, id_empresa, id_producto, cantidad, nup, lote, fecha_caducidad, precio_unitario, subtotal, 
                               id_impuesto, porcentaje_impuesto, valor_impuesto, total, id_bodega, eliminado, id_pedido_detalle
                           ) VALUES (
                               :idc, :e, :prod, :cant, :nup, :lote, :fc, :pu, :sub, :idi, :pi, :vi, :tot, :idb, false, :idpd
                           )";
                $st = $db->prepare($sqlDet);
                $st->execute([
                    ':idc' => $idConsignacion,
                    ':e' => $idEmpresa,
                    ':prod' => $det['id_producto'],
                    ':cant' => $det['cantidad'],
                    ':nup' => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
                    ':lote' => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
                    ':fc' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
                    ':pu' => $det['precio_unitario'],
                    ':sub' => $det['subtotal'],
                    ':idi' => $det['id_impuesto'] ?? null,
                    ':pi' => $det['porcentaje_impuesto'] ?? 0,
                    ':vi' => $det['valor_impuesto'] ?? 0,
                    ':tot' => $det['total'] ?? 0,
                    ':idb' => $det['id_bodega'],
                    ':idpd' => empty($det['id_pedido_detalle']) ? null : (int)$det['id_pedido_detalle']
                ]);

                // Controlar stock de acuerdo a la configuración de facturación
                $soloStockPos = (($data['empresa_config']['facturacion_inventario'] ?? true) === 'true' || ($data['empresa_config']['facturacion_inventario'] ?? true) === true);
                $repoProd = new \App\repositories\modulos\ProductoRepository();
                $prodData = $repoProd->findById((int)$det['id_producto'], $idEmpresa);
                $esInv = $prodData && ($prodData['inventariable'] == true || $prodData['inventariable'] == 'true' || $prodData['inventariable'] == 1) && ($prodData['tipo_produccion'] ?? '01') !== '02';
                
                if ($soloStockPos && $esInv) {
                    $excludeId = $idConsignacion;
                    $excludeTipo = 'consignacion_venta';
                    $loteVal = (!empty($det['lote']) && $det['lote'] !== 'sin_lote') ? $det['lote'] : null;
                    
                    $stockTotal = $this->inventarioRepo->getStockActual(
                        (int)$det['id_producto'],
                        (int)$det['id_bodega'],
                        $idEmpresa,
                        $excludeId,
                        $excludeTipo,
                        $loteVal
                    );
                    
                    if ($stockTotal < $det['cantidad']) {
                        $prodNombre = $prodData['nombre'] ?? 'Producto';
                        $loteStr = $loteVal ? " (Lote: {$loteVal})" : "";
                        throw new Exception("Stock insuficiente para el producto: {$prodNombre}{$loteStr}. Saldo actual: {$stockTotal}, Requerido: {$det['cantidad']}");
                    }
                }

                // Afectar inventario (salida)
                $stockActual = $this->inventarioRepo->getStockActual((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa);
                $nuevoStock = $stockActual - $det['cantidad'];

                // Costo promedio actual → se registra en el kardex para el asiento de reclasificación.
                $costoUnitCons  = (float) $this->inventarioRepo->getCostoPromedio((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa);
                $costoTotalCons = round($costoUnitCons * (float)$det['cantidad'], 2);

                $this->inventarioRepo->registrarMovimiento([
                    'id_empresa' => $idEmpresa,
                    'id_producto' => $det['id_producto'],
                    'id_bodega' => $det['id_bodega'],
                    'tipo_movimiento' => 'salida',
                    'referencia_tipo' => 'CONSIGNACION_VENTA',
                    'referencia_id' => $idConsignacion,
                    'cantidad' => -$det['cantidad'], // negativo para salidas
                    'costo_unitario' => $costoUnitCons,
                    'costo_total' => $costoTotalCons,
                    'stock_anterior' => $stockActual,
                    'stock_posterior' => $nuevoStock,
                    'numero_lote' => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
                    'fecha_caducidad' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
                    'nup' => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
                    'observaciones' => 'Salida por Consignación Venta ' . $data['serie'] . '-' . $data['secuencial'],
                    'id_usuario' => $idUsuario
                ]);
                
                $this->inventarioRepo->actualizarStock((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa, $nuevoStock, $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_CONSIGNACION', 'consignaciones_ventas', $idConsignacion, null, $cabecera);

            $this->reconciliarPedidosAfectados($db, $idEmpresa);

            $db->commit();

            // El asiento se genera FUERA de la transacción: un fallo contable no debe
            // revertir la consignación ya guardada.
            $this->procesarAsientoSeguro($idConsignacion, $data);

            return $idConsignacion;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarCreacion($data);
        $idUsuario = (int) $data['id_usuario'];

        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) {
            throw new Exception("Consignación no encontrada.");
        }
        if ($cabecera['estado'] !== 'Borrador') {
            throw new Exception("Solo se pueden editar consignaciones en estado Borrador. Cambie el estado a Borrador para editar.");
        }
        if ($this->tieneFacturaAsociada($id, $idEmpresa)) {
            throw new Exception("No se puede editar: la consignación tiene una factura asociada.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // 1. Reversar inventario de los detalles anteriores
            $detallesAntiguos = $this->repository->getDetalles($id, $idEmpresa);
            foreach ($detallesAntiguos as $det) {
                $stockActual = $this->inventarioRepo->getStockActual((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa);
                $nuevoStock = $stockActual + $det['cantidad'];

                $this->inventarioRepo->registrarMovimiento([
                    'id_empresa' => $idEmpresa,
                    'id_producto' => $det['id_producto'],
                    'id_bodega' => $det['id_bodega'],
                    'tipo_movimiento' => 'entrada',
                    'referencia_tipo' => 'EDICION_CONSIGNACION_VENTA',
                    'referencia_id' => $id,
                    'cantidad' => $det['cantidad'], // positivo
                    'stock_anterior' => $stockActual,
                    'stock_posterior' => $nuevoStock,
                    'numero_lote' => $det['lote'] ?? null,
                    'fecha_caducidad' => $det['fecha_caducidad'] ?? null,
                    'nup' => $det['nup'] ?? null,
                    'observaciones' => 'Reverso por edición de Consignación ' . $cabecera['serie'] . '-' . $cabecera['secuencial'],
                    'id_usuario' => $idUsuario
                ]);
                $this->inventarioRepo->actualizarStock((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa, $nuevoStock, $idUsuario);
            }

            // 2. Eliminar detalles lógicamente
            $this->repository->deleteDetalles($id, $idEmpresa);

            // 3. Insertar nuevos detalles y descontar inventario
            $sqlDetalle = "INSERT INTO consignaciones_ventas_detalles (
                id_consignacion, id_empresa, id_producto, cantidad, nup, lote, fecha_caducidad, precio_unitario, subtotal, 
                id_impuesto, porcentaje_impuesto, valor_impuesto, total, id_bodega, eliminado, id_pedido_detalle
            ) VALUES (
                :idc, :e, :prod, :cant, :nup, :lote, :fc, :pu, :sub, :idi, :pi, :vi, :tot, :idb, false, :idpd
            )";
            $stDetalle = $db->prepare($sqlDetalle);

            $subtotal = 0; $impuesto = 0; $total = 0;
            foreach ($data['detalles'] as $det) {
                if (empty($det['id_producto'])) continue;
                $det['id_bodega'] = (int) ($det['id_bodega'] ?? ($data['id_bodega'] ?? 0));
                
                $stDetalle->execute([
                    ':idc' => $id,
                    ':e' => $idEmpresa,
                    ':prod' => $det['id_producto'],
                    ':cant' => $det['cantidad'],
                    ':nup' => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
                    ':lote' => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
                    ':fc' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
                    ':pu' => $det['precio_unitario'],
                    ':sub' => $det['subtotal'],
                    ':idi' => $det['id_impuesto'] ?? null,
                    ':pi' => $det['porcentaje_impuesto'] ?? 0,
                    ':vi' => $det['valor_impuesto'] ?? 0,
                    ':tot' => $det['total'] ?? 0,
                    ':idb' => $det['id_bodega'],
                    ':idpd' => empty($det['id_pedido_detalle']) ? null : (int)$det['id_pedido_detalle']
                ]);

                // Controlar stock de acuerdo a la configuración de facturación
                $soloStockPos = (($data['empresa_config']['facturacion_inventario'] ?? true) === 'true' || ($data['empresa_config']['facturacion_inventario'] ?? true) === true);
                $repoProd = new \App\repositories\modulos\ProductoRepository();
                $prodData = $repoProd->findById((int)$det['id_producto'], $idEmpresa);
                $esInv = $prodData && ($prodData['inventariable'] == true || $prodData['inventariable'] == 'true' || $prodData['inventariable'] == 1) && ($prodData['tipo_produccion'] ?? '01') !== '02';
                
                if ($soloStockPos && $esInv) {
                    $excludeId = $id;
                    $excludeTipo = 'consignacion_venta';
                    $loteVal = (!empty($det['lote']) && $det['lote'] !== 'sin_lote') ? $det['lote'] : null;
                    
                    $stockTotal = $this->inventarioRepo->getStockActual(
                        (int)$det['id_producto'],
                        (int)$det['id_bodega'],
                        $idEmpresa,
                        $excludeId,
                        $excludeTipo,
                        $loteVal
                    );
                    
                    if ($stockTotal < $det['cantidad']) {
                        $prodNombre = $prodData['nombre'] ?? 'Producto';
                        $loteStr = $loteVal ? " (Lote: {$loteVal})" : "";
                        throw new Exception("Stock insuficiente para el producto: {$prodNombre}{$loteStr}. Saldo actual: {$stockTotal}, Requerido: {$det['cantidad']}");
                    }
                }

                // Afectar inventario (salida)
                $stockActual = $this->inventarioRepo->getStockActual((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa);
                $nuevoStock = $stockActual - $det['cantidad'];

                // Costo promedio actual → se registra en el kardex para el asiento de reclasificación.
                $costoUnitCons  = (float) $this->inventarioRepo->getCostoPromedio((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa);
                $costoTotalCons = round($costoUnitCons * (float)$det['cantidad'], 2);

                $this->inventarioRepo->registrarMovimiento([
                    'id_empresa' => $idEmpresa,
                    'id_producto' => $det['id_producto'],
                    'id_bodega' => $det['id_bodega'],
                    'tipo_movimiento' => 'salida',
                    'referencia_tipo' => 'CONSIGNACION_VENTA',
                    'referencia_id' => $id,
                    'cantidad' => -$det['cantidad'], // negativo para salidas
                    'costo_unitario' => $costoUnitCons,
                    'costo_total' => $costoTotalCons,
                    'stock_anterior' => $stockActual,
                    'stock_posterior' => $nuevoStock,
                    'numero_lote' => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
                    'fecha_caducidad' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
                    'nup' => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
                    'observaciones' => 'Salida por Consignación Venta (Edición) ' . $cabecera['serie'] . '-' . $cabecera['secuencial'],
                    'id_usuario' => $idUsuario
                ]);
                
                $this->inventarioRepo->actualizarStock((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa, $nuevoStock, $idUsuario);

                $subtotal += $det['subtotal'];
                $total += $det['subtotal']; // asumiendo sin impuestos por ahora para coincidir con crear
            }

            // 4. Actualizar cabecera
            $updData = [
                'fecha_emision' => $data['fecha_emision'] ?? date('Y-m-d'),
                'id_punto_emision' => empty($data['id_punto_emision']) ? null : $data['id_punto_emision'],
                'establecimiento' => $data['establecimiento'] ?? null,
                'punto_emision' => $data['punto_emision'] ?? null,
                'id_vendedor' => empty($data['id_vendedor']) ? null : $data['id_vendedor'],
                'id_cliente' => empty($data['id_cliente']) ? null : $data['id_cliente'],
                'id_responsable_traslado' => empty($data['id_responsable_traslado']) ? null : $data['id_responsable_traslado'],
                'punto_partida' => empty($data['punto_partida']) ? null : trim($data['punto_partida']),
                'punto_llegada' => empty($data['punto_llegada']) ? null : trim($data['punto_llegada']),
                'fecha_entrega' => empty($data['fecha_entrega']) ? null : $data['fecha_entrega'],
                'hora_entrega_desde' => empty($data['hora_entrega_desde']) ? null : $data['hora_entrega_desde'],
                'hora_entrega_hasta' => empty($data['hora_entrega_hasta']) ? null : $data['hora_entrega_hasta'],
                'observaciones' => empty($data['observaciones']) ? null : trim($data['observaciones']),
                'subtotal' => $subtotal,
                'total' => $total,
                // Al actualizar un Borrador, la consignación pasa (de vuelta) a Emitida.
                'estado' => 'Emitida',
                'updated_by' => $idUsuario,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->repository->update($id, $idEmpresa, $updData);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_CONSIGNACION', 'consignaciones_ventas', $id, $cabecera, $updData);

            $this->reconciliarPedidosAfectados($db, $idEmpresa);

            $db->commit();

            // Regenerar el asiento con los valores actualizados (fuera de la transacción).
            $this->procesarAsientoSeguro($id, $data);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) {
            throw new Exception("Consignación no encontrada.");
        }
        if ($cabecera['eliminado']) {
            throw new Exception("La consignación ya está eliminada.");
        }
        if (in_array($cabecera['estado'], ['Entregada', 'Facturada'])) {
            throw new Exception("No se puede eliminar una consignación que ya está " . $cabecera['estado'] . ". Se debe realizar un retorno.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->eliminar($id, $idEmpresa, $idUsuario);

            // Reversar el inventario
            $detalles = $this->repository->getDetalles($id, $idEmpresa);
            foreach ($detalles as $det) {
                $stockActual = $this->inventarioRepo->getStockActual((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa);
                $nuevoStock = $stockActual + $det['cantidad'];

                $this->inventarioRepo->registrarMovimiento([
                    'id_empresa' => $idEmpresa,
                    'id_producto' => $det['id_producto'],
                    'id_bodega' => $det['id_bodega'],
                    'tipo_movimiento' => 'entrada',
                    'referencia_tipo' => 'ELIMINACION_CONSIGNACION_VENTA',
                    'referencia_id' => $id,
                    'cantidad' => $det['cantidad'], // positivo
                    'stock_anterior' => $stockActual,
                    'stock_posterior' => $nuevoStock,
                    'numero_lote' => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
                    'fecha_caducidad' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
                    'nup' => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
                    'observaciones' => 'Reverso por Eliminación Consignación ' . $cabecera['serie'] . '-' . $cabecera['secuencial'],
                    'id_usuario' => $idUsuario
                ]);
                
                $this->inventarioRepo->actualizarStock((int)$det['id_producto'], (int)$det['id_bodega'], $idEmpresa, $nuevoStock, $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_CONSIGNACION', 'consignaciones_ventas', $id, $cabecera);

            $this->reconciliarPedidosAfectados($db, $idEmpresa);

            $db->commit();

            // Anular el asiento contable de la consignación (si existe), fuera de la transacción.
            $idAsiento = (int) ($cabecera['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                try {
                    $asientoService = new \App\Services\modulos\AsientoContableService(
                        new \App\repositories\modulos\AsientoContableRepository(),
                        new \App\Rules\modulos\AsientoContableRules(),
                        $this->logService
                    );
                    $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
                } catch (\Throwable $e) {
                    error_log("[Consignacion] No se pudo anular el asiento $idAsiento: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Cabecera de la consignación (incluye id_asiento_contable). */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    /**
     * Cambia el estado de la consignación (Borrador | Entregada | Anulada).
     * Solo actualiza el estado; NO reversa inventario ni anula el asiento (eso se maneja
     * en el flujo de eliminar / retornos, aún por definir para "Anulada").
     */
    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado, array $datosEntrega = []): void
    {
        $permitidos = ['Emitida', 'Borrador', 'Entregada', 'Anulada'];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new Exception("Estado no válido.");
        }

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Consignación no encontrada.");
        }
        if (($cab['estado'] ?? '') === $nuevoEstado) {
            return; // sin cambios
        }
        if ($this->tieneFacturaAsociada($id, $idEmpresa)) {
            throw new Exception("No se puede cambiar el estado: la consignación tiene una factura asociada.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario);

            // Al marcar ENTREGADA manualmente desde el web se registra la evidencia de
            // entrega (ubicación + hora + usuario que la realizó, canal 'web'). Si ya existe
            // una evidencia (p. ej. desde la app móvil), no se duplica: solo se apunta.
            if ($nuevoEstado === 'Entregada') {
                $entregaRepo = new ConsignacionVentaEntregaRepository();
                $existentes  = $entregaRepo->getPorConsignacion($id, $idEmpresa);
                if (!empty($existentes)) {
                    $this->repository->updateEntregaConfirmada($id, $idEmpresa, (int) $existentes[0]['id']);
                } else {
                    $lat  = (isset($datosEntrega['latitud'])  && $datosEntrega['latitud']  !== '') ? $datosEntrega['latitud']  : null;
                    $lon  = (isset($datosEntrega['longitud']) && $datosEntrega['longitud'] !== '') ? $datosEntrega['longitud'] : null;
                    $prec = (isset($datosEntrega['precision_m']) && $datosEntrega['precision_m'] !== '') ? $datosEntrega['precision_m'] : null;
                    $res = $entregaRepo->crear([
                        'id_empresa'      => $idEmpresa,
                        'id_consignacion' => $id,
                        'uuid_cliente'    => 'web-' . $id . '-' . uniqid(),
                        'latitud'         => $lat,
                        'longitud'        => $lon,
                        'precision_m'     => $prec,
                        'firma_path'      => null,
                        'capturado_en'    => date('Y-m-d H:i:s'),
                        'dispositivo_id'  => null,
                        'canal'           => 'web',
                        'observaciones'   => 'Entrega registrada manualmente desde el sistema.',
                        'created_by'      => $idUsuario,
                    ]);
                    $this->repository->updateEntregaConfirmada($id, $idEmpresa, (int) $res['id']);
                }
            }

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_CONSIGNACION', 'consignaciones_ventas', $id,
                ['estado' => $cab['estado'] ?? null], ['estado' => $nuevoEstado]
            );
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Consignaciones pendientes de entrega (estado 'Emitida') para el módulo Entregas de la app móvil. */
    public function getPendientesEntrega(int $idEmpresa, ?array $idsResponsables, string $buscar, int $page, int $perPage): array
    {
        return $this->repository->getPendientesEntrega($idEmpresa, $idsResponsables, $buscar, $page, $perPage);
    }

    /**
     * Registra la entrega (GPS + firma) de una consignación y la pasa a 'Entregada'.
     * Idempotente por uuid_cliente: un reenvío (retry de red / cola offline) no crea
     * evidencia duplicada ni falla — devuelve el mismo resultado que la primera vez.
     *
     * @param array $data id_consignacion, id_empresa, id_usuario, uuid_cliente, latitud,
     *   longitud, precision_m, firma_path (ya guardada en disco por el controller),
     *   capturado_en, dispositivo_id, canal, observaciones
     * @return array{id_entrega:int, ya_entregada:bool}
     * @throws Exception si la consignación no existe o no admite entrega (estado inválido
     *   y no es un reenvío idempotente del mismo uuid).
     */
    public function registrarEntrega(array $data): array
    {
        $idConsignacion = (int) $data['id_consignacion'];
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $cab = $this->repository->find($idConsignacion, $idEmpresa);
        if (!$cab) {
            throw new Exception('Consignación no encontrada.');
        }

        $entregaRepo = new ConsignacionVentaEntregaRepository();

        // Reenvío idempotente (mismo uuid ya procesado antes, típico de la cola offline
        // o un timeout de red): responder éxito sin volver a tocar nada.
        $existente = $entregaRepo->buscarPorUuid($idEmpresa, (string) $data['uuid_cliente']);
        if ($existente) {
            return ['id_entrega' => (int) $existente['id'], 'ya_entregada' => true];
        }

        if (($cab['estado'] ?? '') !== 'Emitida') {
            throw new Exception("Esta consignación ya no admite entrega (estado actual: {$cab['estado']}).");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $resultado = $entregaRepo->crear([
                'id_empresa'      => $idEmpresa,
                'id_consignacion' => $idConsignacion,
                'uuid_cliente'    => $data['uuid_cliente'],
                'latitud'         => $data['latitud'] ?? null,
                'longitud'        => $data['longitud'] ?? null,
                'precision_m'     => $data['precision_m'] ?? null,
                'firma_path'      => $data['firma_path'] ?? null,
                'capturado_en'    => $data['capturado_en'],
                'dispositivo_id'  => $data['dispositivo_id'] ?? null,
                'canal'           => $data['canal'] ?? 'movil',
                'observaciones'   => $data['observaciones'] ?? null,
                'created_by'      => $idUsuario,
            ]);

            $this->repository->update($idConsignacion, $idEmpresa, [
                'id_entrega_confirmada' => $resultado['id'],
            ]);

            $this->repository->updateEstado($idConsignacion, $idEmpresa, 'Entregada', $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'REGISTRAR_ENTREGA', 'consignaciones_ventas', $idConsignacion,
                ['estado' => $cab['estado']],
                ['estado' => 'Entregada', 'id_entrega' => $resultado['id'], 'latitud' => $data['latitud'] ?? null, 'longitud' => $data['longitud'] ?? null]
            );

            $db->commit();

            return ['id_entrega' => $resultado['id'], 'ya_entregada' => false];
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Punto de entrada del Sincronizador de Asientos (Estados Financieros). */
    public function procesarAsientoContablePorSincronizacion(int $id): void
    {
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        if ($idEmpresa <= 0) return;
        if (!$this->repository->find($id, $idEmpresa)) return;

        $this->procesarAsientoContable($id, [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
        ]);
    }

    /** Cantidad retornada por línea de consignación: [id_consignacion_detalle => cantidad]. */
    public function getRetornadoPorLinea(int $idConsignacion, int $idEmpresa): array
    {
        try {
            $retornoRepo = new \App\repositories\modulos\RetornoCvRepository();
            return $retornoRepo->getRetornadoPorConsignacion($idConsignacion, $idEmpresa);
        } catch (\Throwable $e) {
            // Tabla de retornos inexistente (migración pendiente): sin datos de retorno.
            return [];
        }
    }

    /** Evidencias de entrega (GPS + firma) registradas desde la app móvil, para la pestaña Entrega. */
    public function getEntregasDeConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        if (!$this->repository->find($idConsignacion, $idEmpresa)) {
            return [];
        }
        try {
            $entregaRepo = new \App\repositories\modulos\ConsignacionVentaEntregaRepository();
            return $entregaRepo->getPorConsignacion($idConsignacion, $idEmpresa);
        } catch (\Throwable $e) {
            return []; // tabla aún no desplegada
        }
    }

    /** Ruta relativa de la firma de una entrega (validando empresa), o null. */
    public function getFirmaEntrega(int $idEntrega, int $idEmpresa): ?string
    {
        try {
            $entregaRepo = new \App\repositories\modulos\ConsignacionVentaEntregaRepository();
            $ent = $entregaRepo->find($idEntrega, $idEmpresa);
            $path = $ent['firma_path'] ?? '';
            // Solo se sirven archivos dentro de storage/entregas (evita path traversal).
            if (is_string($path) && strpos($path, 'storage/entregas/') === 0 && strpos($path, '..') === false) {
                return $path;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    /** Retornos (líneas de devolución) asociados a esta consignación, para la pestaña Retornos. */
    public function getRetornosDeConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        // Validar que la consignación pertenezca a la empresa antes de listar sus retornos.
        if (!$this->repository->find($idConsignacion, $idEmpresa)) {
            return [];
        }
        $retornoRepo = new \App\repositories\modulos\RetornoCvRepository();
        return $retornoRepo->getPorConsignacion($idConsignacion, $idEmpresa);
    }

    /** Asiento sugerido (reclasificación de inventario a costo) para la pestaña. */
    public function obtenerAsientoSugerido(int $idEmpresa, int $idConsignacion): array
    {
        $builder = new \App\Services\modulos\AsientoBuilderService();
        return $builder->generarAsientoConsignacion($idEmpresa, $idConsignacion);
    }

    /** Genera/actualiza el asiento sin propagar errores (no debe tumbar el guardado). */
    private function procesarAsientoSeguro(int $idConsignacion, array $data): void
    {
        try {
            $this->procesarAsientoContable($idConsignacion, $data);
        } catch (\Throwable $e) {
            error_log("[Consignacion] Asiento no generado para $idConsignacion: " . $e->getMessage());
        }
    }

    /**
     * Persiste el asiento de reclasificación de la consignación. Prioriza el asiento
     * editado por el usuario en la pestaña (si viene COMPLETO); si no, usa la sugerencia
     * automática. Solo se persiste cuando está completo (todas las líneas con cuenta) y
     * cuadrado; en caso contrario se omite y el usuario podrá completarlo al reabrir.
     */
    public function procesarAsientoContable(int $idConsignacion, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $cab = $this->repository->find($idConsignacion, $idEmpresa);
        if (!$cab) return;

        $numDoc = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
        $fecha  = $data['fecha_emision'] ?? ($cab['fecha_emision'] ?? date('Y-m-d'));

        // 1. Fuente: asiento manual del frontend si viene completo; si no, la sugerencia.
        $fuente = (!empty($data['asiento_detalles']) && is_array($data['asiento_detalles']))
            ? $data['asiento_detalles']
            : [];
        $manualCompleto = !empty($fuente);
        foreach ($fuente as $f) {
            if ((int) ($f['id_cuenta_contable'] ?? 0) <= 0) { $manualCompleto = false; break; }
        }
        if (!$manualCompleto) {
            $fuente = $this->obtenerAsientoSugerido($idEmpresa, $idConsignacion);
        }

        // 2. Normalizar y validar (todas las líneas con cuenta; cuadre exacto).
        $detalles = [];
        $totDebe = 0.0; $totHaber = 0.0;
        foreach ($fuente as $d) {
            $idCuenta = (int) ($d['id_cuenta_contable'] ?? 0);
            if ($idCuenta <= 0) { $detalles = []; break; } // incompleto → no persistir aún
            $debe  = round((float) ($d['debe'] ?? 0), 2);
            $haber = round((float) ($d['haber'] ?? 0), 2);
            $totDebe += $debe; $totHaber += $haber;
            $detalles[] = [
                'id_cuenta_contable'   => $idCuenta,
                'debe'                 => $debe,
                'haber'                => $haber,
                'referencia_detalle'   => ($d['referencia_detalle'] ?? '') ?: ('Consignación # ' . $numDoc),
                'documento_referencia' => 'Consignación # ' . $numDoc,
                'id_entidad'           => (int) ($cab['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles) || abs($totDebe - $totHaber) >= 0.005 || ($totDebe <= 0 && $totHaber <= 0)) {
            return; // incompleto o descuadrado: no se persiste el asiento
        }

        $asientoService = new \App\Services\modulos\AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $previo = $asientoService->getAsientoPorOrigen('consignacion_venta', $idConsignacion, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $clienteNombre = $cab['cliente_nombre'] ?? 'Cliente';
        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'consignacion',
            'numero_comprobante'   => '',
            'concepto'             => 'Consignación # ' . $numDoc . ' - Cliente: ' . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'consignacion_venta',
            'id_referencia_origen' => $idConsignacion,
            'observaciones'        => $data['observaciones'] ?? ($cab['observaciones'] ?? null),
        ];

        $idGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idConsignacion, $idEmpresa, $idGenerado);
    }

    private function reconciliarPedidosAfectados(\PDO $db, int $idEmpresa): void
    {
        // 1. Obtener todos los id_pedido únicos cuyos detalles han sido enlazados
        // en consignaciones de venta activas (no eliminadas)
        $sqlPedidos = "
            SELECT DISTINCT pd.id_pedido
            FROM consignaciones_ventas_detalles cvd
            JOIN pedidos_detalle pd ON cvd.id_pedido_detalle = pd.id
            WHERE cvd.id_empresa = :e AND cvd.id_pedido_detalle IS NOT NULL
        ";
        $st = $db->prepare($sqlPedidos);
        $st->execute([':e' => $idEmpresa]);
        $pedidos = $st->fetchAll(\PDO::FETCH_COLUMN);

        // También incluimos aquellos pedidos que antes estaban asociados pero que ahora ya no tienen enlaces 
        // (por ejemplo, porque eliminamos la consignación o le quitamos los ítems)
        // Para estar 100% seguros de no omitir ningún pedido que pudiera volver a 'Pendiente':
        // Buscamos pedidos que estén actualmente como 'Procesado' en esta empresa para verificar si su condición aún se cumple.
        $sqlProcesados = "SELECT id FROM pedidos_cabecera WHERE id_empresa = :e AND estado = 'Procesado' AND eliminado = false";
        $st2 = $db->prepare($sqlProcesados);
        $st2->execute([':e' => $idEmpresa]);
        $procesados = $st2->fetchAll(\PDO::FETCH_COLUMN);
        
        $pedidosAComprobar = array_unique(array_merge($pedidos, $procesados));

        foreach ($pedidosAComprobar as $idPedido) {
            $idPedido = (int) $idPedido;
            if ($idPedido <= 0) continue;

            // Obtener todos los detalles del pedido
            $sqlD = "SELECT id, cantidad FROM pedidos_detalle WHERE id_pedido = :id_p AND eliminado = false";
            $stD = $db->prepare($sqlD);
            $stD->execute([':id_p' => $idPedido]);
            $detallesPedido = $stD->fetchAll(\PDO::FETCH_ASSOC);

            $todoCompletado = true;
            foreach ($detallesPedido as $dp) {
                // Sumar la cantidad consignada en cualquier consignación activa de la empresa
                $sqlSum = "
                    SELECT COALESCE(SUM(cvd.cantidad), 0)
                    FROM consignaciones_ventas_detalles cvd
                    JOIN consignaciones_ventas cv ON cvd.id_consignacion = cv.id
                    WHERE cvd.id_pedido_detalle = :id_pd 
                      AND cv.eliminado = false 
                      AND cvd.eliminado = false
                ";
                $stSum = $db->prepare($sqlSum);
                $stSum->execute([':id_pd' => (int)$dp['id']]);
                $cantidadConsignada = (float) $stSum->fetchColumn();

                if ($cantidadConsignada < (float)$dp['cantidad']) {
                    $todoCompletado = false;
                    break;
                }
            }

            // Actualizar estado del pedido de acuerdo a si está todo completado o no
            $nuevoEstado = $todoCompletado ? 'Procesado' : 'Pendiente';
            $sqlUpd = "UPDATE pedidos_cabecera SET estado = :est, updated_at = CURRENT_TIMESTAMP WHERE id = :id_p AND id_empresa = :e";
            $stUpd = $db->prepare($sqlUpd);
            $stUpd->execute([':est' => $nuevoEstado, ':id_p' => $idPedido, ':e' => $idEmpresa]);
        }
    }
}
