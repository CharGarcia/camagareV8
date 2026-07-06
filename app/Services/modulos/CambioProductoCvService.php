<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CambioProductoCvRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\CambioProductoCvRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;

/**
 * Lógica de negocio de Cambios de productos.
 *
 * Un cambio registra, en un solo documento y por cliente:
 *   - DEVOLUCIONES → ENTRADA de inventario (el cliente regresa mercadería de una
 *     factura o de un cambio anterior). Se copia "tal cual" del origen y se valida saldo.
 *   - ENTREGAS → SALIDA de inventario (el cliente recibe otros productos a cambio).
 *
 * La diferencia de valor (entregado − devuelto) es informativa. Inventario y
 * asiento contable (a costo) van ligados al estado 'Emitida'.
 */
class CambioProductoCvService
{
    private CambioProductoCvRepository $repository;
    private CambioProductoCvRules $rules;
    private LogSistemaService $logService;
    private InventarioRepository $inventarioRepo;
    private ProductoRepository $productoRepo;

    public function __construct(
        CambioProductoCvRepository $repository,
        CambioProductoCvRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository     = $repository;
        $this->rules          = $rules;
        $this->logService     = $logService;
        $this->inventarioRepo = new InventarioRepository();
        $this->productoRepo   = new ProductoRepository();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getLineasDisponiblesCliente(int $idEmpresa, int $idCliente, string $q, ?int $excluirCambio = null): array
    {
        return $this->repository->getLineasDisponiblesCliente($idEmpresa, $idCliente, $q, $excluirCambio);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) return null;
        $cabecera['detalles'] = $this->repository->getDetalles($id, $idEmpresa);
        return $cabecera;
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    // ─── CREAR ────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validarCreacion($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $empresaConfig = $data['empresa_config'] ?? [];

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $cabecera = [
                'id_empresa'              => $idEmpresa,
                'fecha_cambio'            => $data['fecha_cambio'],
                'serie'                   => $data['serie'] ?? '',
                'secuencial'              => str_pad((string)($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT),
                'id_punto_emision'        => empty($data['id_punto_emision']) ? null : (int) $data['id_punto_emision'],
                'establecimiento'         => $data['establecimiento'] ?? null,
                'punto_emision'           => $data['punto_emision'] ?? null,
                'tipo_ambiente'           => (string) ($empresaConfig['tipo_ambiente'] ?? '1'),
                'id_cliente'              => (int) $data['id_cliente'],
                'id_responsable_traslado' => empty($data['id_responsable_traslado']) ? null : (int) $data['id_responsable_traslado'],
                'motivo'                  => $data['motivo'] ?? null,
                'observaciones'           => $data['observaciones'] ?? null,
                'estado'                  => 'Emitida',
                'subtotal_devuelto'       => 0,
                'subtotal_entregado'      => 0,
                'diferencia'              => 0,
                'created_by'              => $idUsuario,
                'updated_by'              => $idUsuario,
            ];
            $idCambio = $this->repository->create($cabecera);
            $numero = ($cabecera['serie'] ?? '') . '-' . ($cabecera['secuencial'] ?? '');

            $totDev = $this->procesarLineas($idCambio, $idEmpresa, $idUsuario, $empresaConfig, $data['devoluciones'] ?? [], 'devolucion', true, $numero, null);
            $totEnt = $this->procesarLineas($idCambio, $idEmpresa, $idUsuario, $empresaConfig, $data['entregas'] ?? [], 'entrega', true, $numero, null);

            $this->repository->updateCabecera($idCambio, $idEmpresa, [
                'subtotal_devuelto'  => round($totDev, 6),
                'subtotal_entregado' => round($totEnt, 6),
                'diferencia'         => round($totEnt - $totDev, 6),
            ]);
            $cabecera['subtotal_devuelto']  = round($totDev, 6);
            $cabecera['subtotal_entregado'] = round($totEnt, 6);
            $cabecera['diferencia']         = round($totEnt - $totDev, 6);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $idCambio, null, $cabecera);

            $db->commit();

            $this->procesarAsientoSeguro($idCambio, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);

            return $idCambio;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Inserta las líneas de un lado (devolucion|entrega). Si $aplicaInventario, mueve stock.
     * Devuelve el total monetario del lado.
     */
    private function procesarLineas(int $idCambio, int $idEmpresa, int $idUsuario, array $empresaConfig, array $lineas, string $tipoLinea, bool $aplicaInventario, string $numero, ?array $unused): float
    {
        $total = 0.0;

        foreach ($lineas as $det) {
            $cant = (float) ($det['cantidad'] ?? 0);
            if ($cant <= 0) continue;

            if ($tipoLinea === 'devolucion') {
                $origenTipo = (string) ($det['origen_tipo'] ?? '');
                $idOrigenDet = (int) ($det['id_origen_detalle'] ?? 0);

                $origen = $this->repository->getDatosLineaOrigen($origenTipo, $idOrigenDet, $idEmpresa);
                if (!$origen) {
                    throw new Exception("La línea de origen #{$idOrigenDet} ({$origenTipo}) no existe o no está disponible.");
                }

                $saldo = $this->repository->getSaldoLineaOrigen($origenTipo, $idOrigenDet, $idEmpresa);
                if ($cant > $saldo + 1e-9) {
                    $nombre = $origen['producto_nombre'] ?? 'Producto';
                    throw new Exception("No puede devolver {$cant} de \"{$nombre}\": el saldo pendiente es {$saldo}.");
                }

                $precio  = (float) $origen['precio_unitario'];
                $porcImp = (float) ($origen['porcentaje_impuesto'] ?? 0);
                $idBodega = (int) ($origen['id_bodega'] ?? 0);

                $linea = [
                    'tipo_linea'        => 'devolucion',
                    'origen_tipo'       => $origenTipo,
                    'id_origen'         => (int) $origen['id_origen'],
                    'id_origen_detalle' => $idOrigenDet,
                    'id_producto'       => (int) $origen['id_producto'],
                    'precio_unitario'   => $precio,
                    'id_impuesto'       => $origen['id_impuesto'] ?? null,
                    'porcentaje_impuesto' => $porcImp,
                    'id_bodega'         => $idBodega,
                    'lote'              => $origen['lote'] ?? null,
                    'nup'               => $origen['nup'] ?? null,
                    'fecha_caducidad'   => $origen['fecha_caducidad'] ?? null,
                    'inventariable'     => $origen['inventariable'] ?? null,
                    'tipo_produccion'   => $origen['tipo_produccion'] ?? null,
                ];
            } else { // entrega
                $idProducto = (int) ($det['id_producto'] ?? 0);
                $prod = $this->repository->getProductoParaEntrega($idProducto, $idEmpresa);
                if (!$prod) {
                    throw new Exception("El producto de entrega #{$idProducto} no existe.");
                }
                $precio   = (float) ($det['precio_unitario'] ?? 0);
                $porcImp  = (float) ($det['porcentaje_impuesto'] ?? 0);
                $idBodega = (int) ($det['id_bodega'] ?? 0);

                $linea = [
                    'tipo_linea'        => 'entrega',
                    'origen_tipo'       => null,
                    'id_origen'         => null,
                    'id_origen_detalle' => null,
                    'id_producto'       => $idProducto,
                    'precio_unitario'   => $precio,
                    'id_impuesto'       => empty($det['id_impuesto']) ? null : (int) $det['id_impuesto'],
                    'porcentaje_impuesto' => $porcImp,
                    'id_bodega'         => $idBodega,
                    'lote'              => $det['lote'] ?? null,
                    'nup'               => $det['nup'] ?? null,
                    'fecha_caducidad'   => $det['fecha_caducidad'] ?? null,
                    'inventariable'     => $prod['inventariable'] ?? null,
                    'tipo_produccion'   => $prod['tipo_produccion'] ?? null,
                ];
            }

            $subtotal   = round($precio * $cant, 6);
            $valorImp   = round($subtotal * ($porcImp / 100), 6);
            $totalLinea = round($subtotal + $valorImp, 6);

            $this->repository->insertDetalle([
                'id_cambio'           => $idCambio,
                'id_empresa'          => $idEmpresa,
                'tipo_linea'          => $linea['tipo_linea'],
                'origen_tipo'         => $linea['origen_tipo'],
                'id_origen'           => $linea['id_origen'],
                'id_origen_detalle'   => $linea['id_origen_detalle'],
                'id_producto'         => $linea['id_producto'],
                'cantidad'            => $cant,
                'precio_unitario'     => $precio,
                'subtotal'            => $subtotal,
                'id_impuesto'         => $linea['id_impuesto'],
                'porcentaje_impuesto' => $porcImp,
                'valor_impuesto'      => $valorImp,
                'total'               => $totalLinea,
                'id_bodega'           => $linea['id_bodega'],
                'lote'                => $linea['lote'],
                'nup'                 => $linea['nup'],
                'fecha_caducidad'     => $linea['fecha_caducidad'],
            ]);

            if ($aplicaInventario) {
                // Devolución = ENTRADA (regresa mercadería). Entrega = SALIDA.
                $tipoMov = ($tipoLinea === 'devolucion') ? 'entrada' : 'salida';
                $lineaInv = array_merge($linea, ['cantidad' => $cant]);
                $this->moverInventarioLinea($lineaInv, $idEmpresa, $idUsuario, $empresaConfig, $tipoMov,
                    'CAMBIO_PRODUCTO_CV', $idCambio, ($tipoMov === 'entrada' ? 'Entrada' : 'Salida') . " por Cambio de productos {$numero}");
            }

            $total += $totalLinea;
        }

        return $total;
    }

    // ─── EDITAR (solo Borrador; no mueve inventario) ──────────────────────────

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarCreacion($data);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Cambio no encontrado.");
        }
        if (($cab['estado'] ?? '') !== 'Borrador') {
            throw new Exception("Solo se pueden editar cambios en estado Borrador.");
        }

        $idUsuario = (int) $data['id_usuario'];
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->deleteDetalles($id, $idEmpresa);

            $numero = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
            $totDev = $this->procesarLineas($id, $idEmpresa, $idUsuario, [], $data['devoluciones'] ?? [], 'devolucion', false, $numero, null);
            $totEnt = $this->procesarLineas($id, $idEmpresa, $idUsuario, [], $data['entregas'] ?? [], 'entrega', false, $numero, null);

            $this->repository->updateCabecera($id, $idEmpresa, [
                'fecha_cambio'       => $data['fecha_cambio'],
                'id_cliente'         => (int) $data['id_cliente'],
                'motivo'             => $data['motivo'] ?? null,
                'observaciones'      => $data['observaciones'] ?? null,
                'subtotal_devuelto'  => round($totDev, 6),
                'subtotal_entregado' => round($totEnt, 6),
                'diferencia'         => round($totEnt - $totDev, 6),
                'updated_by'         => $idUsuario,
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $id, $cab, $data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── ELIMINAR ─────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario, array $empresaConfig = []): void
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) {
            throw new Exception("Cambio no encontrado.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            if (($cabecera['estado'] ?? '') === 'Emitida') {
                $numero = ($cabecera['serie'] ?? '') . '-' . ($cabecera['secuencial'] ?? '');
                $this->reversarInventario($id, $idEmpresa, $idUsuario, $empresaConfig, "Reverso por Eliminación de Cambio {$numero}");
            }

            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $id, $cabecera, null);

            $db->commit();

            $this->anularAsientoSiExiste($id, $idEmpresa, $idUsuario);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── CAMBIO DE ESTADO ─────────────────────────────────────────────────────

    /**
     * Cambia el estado (Borrador | Emitida | Anulada). Inventario ligado a Emitida:
     *  - Emitida → Borrador/Anulada: reversa ambos lados (entrada↔salida) y libera saldo.
     *  - Borrador/Anulada → Emitida: revalida saldo de devoluciones y re-aplica ambos lados.
     */
    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado, array $empresaConfig = []): void
    {
        $permitidos = ['Borrador', 'Emitida', 'Anulada'];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new Exception("Estado no válido.");
        }

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Cambio no encontrado.");
        }
        $actual = (string) ($cab['estado'] ?? '');
        if ($actual === $nuevoEstado) {
            return;
        }

        $wasActive  = ($actual === 'Emitida');
        $willActive = ($nuevoEstado === 'Emitida');
        $numero = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $detalles = $this->repository->getDetalles($id, $idEmpresa);

            if ($wasActive && !$willActive) {
                $this->reversarInventario($id, $idEmpresa, $idUsuario, $empresaConfig, "Reverso por cambio a {$nuevoEstado} del Cambio {$numero}");
            } elseif (!$wasActive && $willActive) {
                // Revalidar saldo de las devoluciones (excluyendo este cambio) antes de re-aplicar.
                foreach ($detalles as $det) {
                    if (($det['tipo_linea'] ?? '') !== 'devolucion') continue;
                    $cant = (float) $det['cantidad'];
                    if ($cant <= 0) continue;
                    $saldo = $this->repository->getSaldoLineaOrigen((string) $det['origen_tipo'], (int) $det['id_origen_detalle'], $idEmpresa, $id);
                    if ($cant > $saldo + 1e-9) {
                        $nombre = $det['producto_nombre'] ?? 'Producto';
                        throw new Exception("No se puede volver a Emitir: \"{$nombre}\" supera el saldo disponible ({$saldo}).");
                    }
                }
                foreach ($detalles as $det) {
                    $tipoMov = (($det['tipo_linea'] ?? '') === 'devolucion') ? 'entrada' : 'salida';
                    $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, $tipoMov,
                        'CAMBIO_PRODUCTO_CV', $id, "Re-aplicación (Emitida) del Cambio {$numero}");
                }
            }
            // Borrador ↔ Anulada: sin movimiento.

            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $id,
                ['estado' => $actual], ['estado' => $nuevoEstado]);

            $db->commit();

            if ($willActive) {
                $this->procesarAsientoSeguro($id, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);
            } elseif ($wasActive) {
                $this->anularAsientoSiExiste($id, $idEmpresa, $idUsuario);
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Reversa el inventario de ambos lados (devolucion→salida, entrega→entrada). */
    private function reversarInventario(int $id, int $idEmpresa, int $idUsuario, array $empresaConfig, string $obs): void
    {
        $detalles = $this->repository->getDetalles($id, $idEmpresa);
        foreach ($detalles as $det) {
            // Inverso del movimiento original.
            $tipoMov = (($det['tipo_linea'] ?? '') === 'devolucion') ? 'salida' : 'entrada';
            $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, $tipoMov, 'CAMBIO_PRODUCTO_CV', $id, $obs);
        }
    }

    // ─── ASIENTO CONTABLE (a costo) ───────────────────────────────────────────

    /** Asiento sugerido: reingreso de lo devuelto y salida de lo entregado, a costo. */
    public function obtenerAsientoSugerido(int $idEmpresa, int $idCambio): array
    {
        return (new AsientoBuilderService())->generarAsientoCambioProductoCv($idEmpresa, $idCambio);
    }

    private function procesarAsientoSeguro(int $idCambio, array $data): void
    {
        try {
            $this->procesarAsientoContable($idCambio, $data);
        } catch (\Throwable $e) {
            error_log("[CambioProductoCV] Asiento no generado para $idCambio: " . $e->getMessage());
        }
    }

    public function procesarAsientoContable(int $idCambio, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $cab = $this->repository->find($idCambio, $idEmpresa);
        if (!$cab) return;
        if (($cab['estado'] ?? '') !== 'Emitida') {
            return;
        }

        $numDoc = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
        $fecha  = $data['fecha_cambio'] ?? ($cab['fecha_cambio'] ?? date('Y-m-d'));

        $fuente = (!empty($data['asiento_detalles']) && is_array($data['asiento_detalles'])) ? $data['asiento_detalles'] : [];
        $manualCompleto = !empty($fuente);
        foreach ($fuente as $f) {
            if ((int) ($f['id_cuenta_contable'] ?? 0) <= 0) { $manualCompleto = false; break; }
        }
        if (!$manualCompleto) {
            $fuente = $this->obtenerAsientoSugerido($idEmpresa, $idCambio);
        }

        $detalles = [];
        $totDebe = 0.0; $totHaber = 0.0;
        foreach ($fuente as $d) {
            $idCuenta = (int) ($d['id_cuenta_contable'] ?? 0);
            if ($idCuenta <= 0) { $detalles = []; break; }
            $debe  = round((float) ($d['debe'] ?? 0), 2);
            $haber = round((float) ($d['haber'] ?? 0), 2);
            $totDebe += $debe; $totHaber += $haber;
            $detalles[] = [
                'id_cuenta_contable'   => $idCuenta,
                'debe'                 => $debe,
                'haber'                => $haber,
                'referencia_detalle'   => ($d['referencia_detalle'] ?? '') ?: ('Cambio productos # ' . $numDoc),
                'documento_referencia' => 'Cambio productos # ' . $numDoc,
                'id_entidad'           => (int) ($cab['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles) || abs($totDebe - $totHaber) >= 0.005 || ($totDebe <= 0 && $totHaber <= 0)) {
            return;
        }

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $previo = $asientoService->getAsientoPorOrigen('cambio_producto_cv', $idCambio, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $clienteNombre = $cab['cliente_nombre'] ?? 'Cliente';
        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'cambio_producto',
            'numero_comprobante'   => '',
            'concepto'             => 'Cambio de productos # ' . $numDoc . ' - Cliente: ' . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'cambio_producto_cv',
            'id_referencia_origen' => $idCambio,
            'observaciones'        => $data['observaciones'] ?? ($cab['observaciones'] ?? null),
        ];

        $idGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idCambio, $idEmpresa, $idGenerado);
    }

    private function anularAsientoSiExiste(int $idCambio, int $idEmpresa, int $idUsuario): void
    {
        try {
            $cab = $this->repository->find($idCambio, $idEmpresa);
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
            if ($idAsiento <= 0) return;
            $asientoService = new AsientoContableService(
                new \App\repositories\modulos\AsientoContableRepository(),
                new \App\Rules\modulos\AsientoContableRules(),
                $this->logService
            );
            $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
            $this->repository->updateAsientoContable($idCambio, $idEmpresa, null);
        } catch (\Throwable $e) {
            error_log("[CambioProductoCV] No se pudo anular el asiento del cambio $idCambio: " . $e->getMessage());
        }
    }

    // ─── Helpers de inventario ────────────────────────────────────────────────

    /**
     * Aplica un movimiento de inventario para una línea (entrada o salida),
     * valorado al costo promedio del producto en su bodega.
     */
    private function moverInventarioLinea(array $det, int $idEmpresa, int $idUsuario, array $empresaConfig, string $tipo, string $refTipo, int $refId, string $obs): void
    {
        $cant = (float) $det['cantidad'];
        $idBodega = (int) ($det['id_bodega'] ?? 0);
        if ($cant <= 0 || $idBodega <= 0) return;

        if (!$this->afectaInventario($det, $empresaConfig)) return;

        $idProducto  = (int) $det['id_producto'];
        $stockActual = $this->inventarioRepo->getStockActual($idProducto, $idBodega, $idEmpresa);
        $delta       = ($tipo === 'entrada') ? $cant : -$cant;
        $nuevoStock  = $stockActual + $delta;

        $costoU = $this->inventarioRepo->getCostoPromedio($idProducto, $idBodega, $idEmpresa);

        $this->inventarioRepo->registrarMovimiento([
            'id_empresa'      => $idEmpresa,
            'id_producto'     => $idProducto,
            'id_bodega'       => $idBodega,
            'tipo_movimiento' => $tipo,
            'referencia_tipo' => $refTipo,
            'referencia_id'   => $refId,
            'cantidad'        => $delta,
            'costo_unitario'  => $costoU,
            'costo_total'     => round($costoU * $cant, 6),
            'stock_anterior'  => $stockActual,
            'stock_posterior' => $nuevoStock,
            'numero_lote'     => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
            'fecha_caducidad' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
            'nup'             => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
            'observaciones'   => $obs,
            'id_usuario'      => $idUsuario,
        ]);

        $this->inventarioRepo->actualizarStock($idProducto, $idBodega, $idEmpresa, $nuevoStock, $idUsuario);
    }

    /**
     * Determina si una línea debe mover inventario: producto inventariable, no servicio (02)
     * y con el control de inventario de facturación activo.
     */
    private function afectaInventario(array $linea, array $empresaConfig): bool
    {
        $esInv = (($linea['inventariable'] ?? null) == true || ($linea['inventariable'] ?? null) === 'true' || ($linea['inventariable'] ?? null) == 1 || ($linea['inventariable'] ?? null) === 't')
                 && (($linea['tipo_produccion'] ?? '01') !== '02');
        $soloStockPos = (($empresaConfig['facturacion_inventario'] ?? true) === 'true' || ($empresaConfig['facturacion_inventario'] ?? true) === true);
        return $esInv && $soloStockPos;
    }
}
