<?php
declare(strict_types=1);

namespace App\Services\modulos;

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

        $cabecera['detalles'] = $detalles;
        return $cabecera;
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

                $this->inventarioRepo->registrarMovimiento([
                    'id_empresa' => $idEmpresa,
                    'id_producto' => $det['id_producto'],
                    'id_bodega' => $det['id_bodega'],
                    'tipo_movimiento' => 'salida',
                    'referencia_tipo' => 'CONSIGNACION_VENTA',
                    'referencia_id' => $idConsignacion,
                    'cantidad' => -$det['cantidad'], // negativo para salidas
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
        if ($cabecera['estado'] !== 'Emitida') {
            throw new Exception("Solo se pueden actualizar consignaciones en estado Emitida.");
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

                $this->inventarioRepo->registrarMovimiento([
                    'id_empresa' => $idEmpresa,
                    'id_producto' => $det['id_producto'],
                    'id_bodega' => $det['id_bodega'],
                    'tipo_movimiento' => 'salida',
                    'referencia_tipo' => 'CONSIGNACION_VENTA',
                    'referencia_id' => $id,
                    'cantidad' => -$det['cantidad'], // negativo para salidas
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
                'updated_by' => $idUsuario,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->repository->update($id, $idEmpresa, $updData);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_CONSIGNACION', 'consignaciones_ventas', $id, $cabecera, $updData);

            $this->reconciliarPedidosAfectados($db, $idEmpresa);

            $db->commit();
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
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
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
