<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\RetornoCvRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\RetornoCvRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;
use PDO;

/**
 * Lógica de negocio de Retornos de Consignaciones en Ventas.
 *
 * Al crear un retorno, por cada línea se registra una ENTRADA de inventario
 * (espejo de la salida que generó la consignación) devolviendo el stock a la
 * bodega de origen. Al eliminar, se reversa (salida). No genera asiento contable.
 */
class RetornoCvService
{
    private RetornoCvRepository $repository;
    private RetornoCvRules $rules;
    private LogSistemaService $logService;
    private InventarioRepository $inventarioRepo;
    private ProductoRepository $productoRepo;

    public function __construct(
        RetornoCvRepository $repository,
        RetornoCvRules $rules,
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

    public function getLineasPendientesPorCliente(int $idEmpresa, int $idCliente): array
    {
        return $this->repository->getLineasPendientesPorCliente($idEmpresa, $idCliente);
    }

    public function buscarConsignacionesPendientes(int $idEmpresa, string $q): array
    {
        return $this->repository->buscarConsignacionesPendientes($idEmpresa, $q);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) return null;
        $cabecera['detalles'] = $this->repository->getDetalles($id, $idEmpresa);
        return $cabecera;
    }

    public function crear(array $data): int
    {
        $this->rules->validarCreacion($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $empresaConfig = $data['empresa_config'] ?? [];

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // Cabecera
            $cabecera = [
                'id_empresa'              => $idEmpresa,
                'fecha_retorno'           => $data['fecha_retorno'],
                'serie'                   => $data['serie'] ?? '',
                'secuencial'              => str_pad((string)($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT),
                'id_punto_emision'        => empty($data['id_punto_emision']) ? null : (int) $data['id_punto_emision'],
                'establecimiento'         => $data['establecimiento'] ?? null,
                'punto_emision'           => $data['punto_emision'] ?? null,
                'tipo_ambiente'           => (string) ($empresaConfig['tipo_ambiente'] ?? '1'),
                'id_cliente'              => (int) $data['id_cliente'],
                'id_responsable_traslado' => empty($data['id_responsable_traslado']) ? null : (int) $data['id_responsable_traslado'],
                'punto_partida'           => $data['punto_partida'] ?? null,
                'punto_llegada'           => $data['punto_llegada'] ?? null,
                'motivo'                  => $data['motivo'] ?? null,
                'observaciones'           => $data['observaciones'] ?? null,
                'estado'                  => 'Emitida',
                'subtotal'                => 0,
                'impuesto'                => 0,
                'total'                   => 0,
                'created_by'              => $idUsuario,
                'updated_by'              => $idUsuario,
            ];
            $idRetorno = $this->repository->create($cabecera);

            $totSubtotal = 0.0;
            $totImpuesto = 0.0;
            $totTotal    = 0.0;

            foreach ($data['detalles'] as $det) {
                $cant = (float) ($det['cantidad'] ?? 0);
                if ($cant <= 0) continue;

                $idConsDet = (int) ($det['id_consignacion_detalle'] ?? 0);

                // Traer los datos "tal cual" de la línea de consignación de origen (autoritativo, no del cliente).
                $consLinea = $this->getConsignacionDetalle($db, $idConsDet, $idEmpresa);
                if (!$consLinea) {
                    throw new Exception("La línea de consignación #{$idConsDet} no existe o no pertenece a la empresa.");
                }

                // Validar saldo pendiente de retornar.
                $saldo = $this->repository->getSaldoLineaConsignacion($idConsDet, $idEmpresa);
                if ($cant > $saldo + 1e-9) {
                    $nombre = $consLinea['producto_nombre'] ?? 'Producto';
                    throw new Exception("No puede retornar {$cant} de \"{$nombre}\": el saldo pendiente es {$saldo}.");
                }

                // Valores proporcionales a la cantidad retornada, con el precio/impuesto de la consignación.
                $precio     = (float) $consLinea['precio_unitario'];
                $porcImp    = (float) ($consLinea['porcentaje_impuesto'] ?? 0);
                $subtotal   = round($precio * $cant, 6);
                $valorImp   = round($subtotal * ($porcImp / 100), 6);
                $totalLinea = round($subtotal + $valorImp, 6);

                $idBodega = (int) ($consLinea['id_bodega'] ?? 0);

                $this->repository->insertDetalle([
                    'id_retorno'              => $idRetorno,
                    'id_empresa'              => $idEmpresa,
                    'id_consignacion'         => (int) $consLinea['id_consignacion'],
                    'id_consignacion_detalle' => $idConsDet,
                    'id_producto'             => (int) $consLinea['id_producto'],
                    'cantidad'                => $cant,
                    'precio_unitario'         => $precio,
                    'subtotal'                => $subtotal,
                    'id_impuesto'             => $consLinea['id_impuesto'] ?? null,
                    'porcentaje_impuesto'     => $porcImp,
                    'valor_impuesto'          => $valorImp,
                    'total'                   => $totalLinea,
                    'id_bodega'               => $idBodega,
                    'lote'                    => $consLinea['lote'] ?? null,
                    'nup'                     => $consLinea['nup'] ?? null,
                    'fecha_caducidad'         => $consLinea['fecha_caducidad'] ?? null,
                ]);

                // Entrada de inventario (espejo de la salida de la consignación).
                if ($this->afectaInventario($consLinea, $empresaConfig) && $idBodega > 0) {
                    $stockActual = $this->inventarioRepo->getStockActual((int) $consLinea['id_producto'], $idBodega, $idEmpresa);
                    $nuevoStock  = $stockActual + $cant;

                    $this->inventarioRepo->registrarMovimiento([
                        'id_empresa'      => $idEmpresa,
                        'id_producto'     => (int) $consLinea['id_producto'],
                        'id_bodega'       => $idBodega,
                        'tipo_movimiento' => 'entrada',
                        'referencia_tipo' => 'RETORNO_CV',
                        'referencia_id'   => $idRetorno,
                        'cantidad'        => $cant,
                        'stock_anterior'  => $stockActual,
                        'stock_posterior' => $nuevoStock,
                        'numero_lote'     => (isset($consLinea['lote']) && $consLinea['lote'] !== '') ? $consLinea['lote'] : null,
                        'fecha_caducidad' => (isset($consLinea['fecha_caducidad']) && $consLinea['fecha_caducidad'] !== '') ? $consLinea['fecha_caducidad'] : null,
                        'nup'             => (isset($consLinea['nup']) && $consLinea['nup'] !== '') ? $consLinea['nup'] : null,
                        'observaciones'   => 'Entrada por Retorno de Consignación ' . ($cabecera['serie'] ?? '') . '-' . ($cabecera['secuencial'] ?? '') . ' (Consig. ' . ($consLinea['serie'] ?? '') . '-' . ($consLinea['secuencial'] ?? '') . ')',
                        'id_usuario'      => $idUsuario,
                    ]);

                    $this->inventarioRepo->actualizarStock((int) $consLinea['id_producto'], $idBodega, $idEmpresa, $nuevoStock, $idUsuario);
                }

                $totSubtotal += $subtotal;
                $totImpuesto += $valorImp;
                $totTotal    += $totalLinea;
            }

            // Actualizar totales de la cabecera.
            $this->repository->getDb()->prepare(
                "UPDATE retornos_cv SET subtotal = :s, impuesto = :i, total = :t WHERE id = :id AND id_empresa = :e"
            )->execute([
                ':s' => round($totSubtotal, 6),
                ':i' => round($totImpuesto, 6),
                ':t' => round($totTotal, 6),
                ':id' => $idRetorno,
                ':e' => $idEmpresa,
            ]);
            $cabecera['subtotal'] = round($totSubtotal, 6);
            $cabecera['impuesto'] = round($totImpuesto, 6);
            $cabecera['total']    = round($totTotal, 6);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_RETORNO_CV', 'retornos_cv', $idRetorno, null, $cabecera);

            $db->commit();

            // Asiento contable (inverso) fuera de la transacción del retorno.
            $this->procesarAsientoSeguro($idRetorno, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);

            return $idRetorno;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Edita un retorno en estado Borrador (inactivo): reemplaza sus detalles y cabecera.
     * No mueve inventario (un Borrador no tiene entrada aplicada).
     */
    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarCreacion($data);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Retorno no encontrado.");
        }
        if (($cab['estado'] ?? '') !== 'Borrador') {
            throw new Exception("Solo se pueden editar retornos en estado Borrador.");
        }

        $idUsuario = (int) $data['id_usuario'];
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->deleteDetalles($id, $idEmpresa);

            $totSub = 0.0; $totImp = 0.0; $totTot = 0.0;
            foreach ($data['detalles'] as $det) {
                $cant = (float) ($det['cantidad'] ?? 0);
                if ($cant <= 0) continue;

                $idConsDet = (int) ($det['id_consignacion_detalle'] ?? 0);
                $consLinea = $this->getConsignacionDetalle($db, $idConsDet, $idEmpresa);
                if (!$consLinea) {
                    throw new Exception("La línea de consignación #{$idConsDet} no existe o no pertenece a la empresa.");
                }
                // Aunque un Borrador no consume saldo, se valida contra el disponible (excluyéndose a sí mismo).
                $saldo = $this->repository->getSaldoLineaConsignacion($idConsDet, $idEmpresa, $id);
                if ($cant > $saldo + 1e-9) {
                    $nombre = $consLinea['producto_nombre'] ?? 'Producto';
                    throw new Exception("No puede retornar {$cant} de \"{$nombre}\": el saldo pendiente es {$saldo}.");
                }

                $precio     = (float) $consLinea['precio_unitario'];
                $porcImp    = (float) ($consLinea['porcentaje_impuesto'] ?? 0);
                $subtotal   = round($precio * $cant, 6);
                $valorImp   = round($subtotal * ($porcImp / 100), 6);
                $totalLinea = round($subtotal + $valorImp, 6);

                $this->repository->insertDetalle([
                    'id_retorno'              => $id,
                    'id_empresa'              => $idEmpresa,
                    'id_consignacion'         => (int) $consLinea['id_consignacion'],
                    'id_consignacion_detalle' => $idConsDet,
                    'id_producto'             => (int) $consLinea['id_producto'],
                    'cantidad'                => $cant,
                    'precio_unitario'         => $precio,
                    'subtotal'                => $subtotal,
                    'id_impuesto'             => $consLinea['id_impuesto'] ?? null,
                    'porcentaje_impuesto'     => $porcImp,
                    'valor_impuesto'          => $valorImp,
                    'total'                   => $totalLinea,
                    'id_bodega'               => (int) ($consLinea['id_bodega'] ?? 0),
                    'lote'                    => $consLinea['lote'] ?? null,
                    'nup'                     => $consLinea['nup'] ?? null,
                    'fecha_caducidad'         => $consLinea['fecha_caducidad'] ?? null,
                ]);

                $totSub += $subtotal; $totImp += $valorImp; $totTot += $totalLinea;
            }

            $this->repository->updateCabecera($id, $idEmpresa, [
                'fecha_retorno' => $data['fecha_retorno'],
                'id_cliente'    => (int) $data['id_cliente'],
                'motivo'        => $data['motivo'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'subtotal'      => round($totSub, 6),
                'impuesto'      => round($totImp, 6),
                'total'         => round($totTot, 6),
                'updated_by'    => $idUsuario,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_RETORNO_CV', 'retornos_cv', $id, $cab, $data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario, array $empresaConfig = []): void
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) {
            throw new Exception("Retorno no encontrado.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // Solo reversar inventario si el retorno estaba ACTIVO (Emitida); si estaba
            // Borrador/Anulada, su entrada ya fue reversada y no debe reversarse de nuevo.
            if (($cabecera['estado'] ?? '') === 'Emitida') {
                $detalles = $this->repository->getDetalles($id, $idEmpresa);
                $numero = ($cabecera['serie'] ?? '') . '-' . ($cabecera['secuencial'] ?? '');
                foreach ($detalles as $det) {
                    $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, 'salida',
                        'ELIMINACION_RETORNO_CV', $id, 'Reverso por Eliminación de Retorno ' . $numero);
                }
            }

            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_RETORNO_CV', 'retornos_cv', $id, $cabecera, null);

            $db->commit();

            // Si tenía asiento (estaba Emitida), anularlo tras el commit.
            $this->anularAsientoSiExiste($id, $idEmpresa, $idUsuario);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado de un retorno (Borrador | Emitida | Anulada).
     * El inventario está ligado al estado Emitida:
     *  - Emitida → Borrador/Anulada: reversa la entrada (salida) y libera el saldo.
     *  - Borrador/Anulada → Emitida: revalida saldo y re-aplica la entrada.
     */
    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado, array $empresaConfig = []): void
    {
        $permitidos = ['Borrador', 'Emitida', 'Anulada'];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new Exception("Estado no válido.");
        }

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Retorno no encontrado.");
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
                // Se desactiva: reversar la entrada (salida) y liberar saldo.
                foreach ($detalles as $det) {
                    $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, 'salida',
                        'CAMBIO_ESTADO_RETORNO_CV', $id, "Reverso por cambio a {$nuevoEstado} del Retorno {$numero}");
                }
            } elseif (!$wasActive && $willActive) {
                // Se reactiva: validar saldo (excluyendo este retorno) y volver a aplicar la entrada.
                foreach ($detalles as $det) {
                    $cant = (float) $det['cantidad'];
                    if ($cant <= 0) continue;
                    $saldo = $this->repository->getSaldoLineaConsignacion((int) $det['id_consignacion_detalle'], $idEmpresa, $id);
                    if ($cant > $saldo + 1e-9) {
                        $nombre = $det['producto_nombre'] ?? 'Producto';
                        throw new Exception("No se puede volver a Emitir: \"{$nombre}\" supera el saldo disponible ({$saldo}).");
                    }
                }
                foreach ($detalles as $det) {
                    $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, 'entrada',
                        'CAMBIO_ESTADO_RETORNO_CV', $id, "Entrada por reactivación (Emitida) del Retorno {$numero}");
                }
            }
            // Borrador ↔ Anulada (ambos inactivos): sin movimiento de inventario.

            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_RETORNO_CV', 'retornos_cv', $id,
                ['estado' => $actual], ['estado' => $nuevoEstado]);

            $db->commit();

            // Sincronizar el asiento contable según el nuevo estado (fuera de la transacción).
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

    // ─── Asiento contable (inverso a la consignación) ─────────────────────────

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    /** Asiento sugerido: reclasificación inversa (Debe Inventario / Haber Mercadería en consignación). */
    public function obtenerAsientoSugerido(int $idEmpresa, int $idRetorno): array
    {
        return (new \App\Services\modulos\AsientoBuilderService())->generarAsientoRetornoCv($idEmpresa, $idRetorno);
    }

    /** Genera el asiento sin propagar errores (no debe tumbar el guardado). */
    private function procesarAsientoSeguro(int $idRetorno, array $data): void
    {
        try {
            $this->procesarAsientoContable($idRetorno, $data);
        } catch (\Throwable $e) {
            error_log("[RetornoCV] Asiento no generado para $idRetorno: " . $e->getMessage());
        }
    }

    /**
     * Genera el asiento del retorno por sincronización masiva (control de asientos de Estados
     * Financieros / Auditoría Contable). Toma empresa y usuario de la propia cabecera y PROPAGA
     * la excepción si no se puede generar —al revés que procesarAsientoSeguro()—, para que la
     * corrida lo reporte como pendiente con su motivo.
     * Solo los retornos 'Emitida' tienen impacto contable; procesarAsientoContable() ya lo valida.
     */
    public function procesarAsientoContablePorSincronizacion(int $idRetorno): void
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id_empresa, created_by FROM retornos_cv WHERE id = ? AND eliminado = false");
        $st->execute([$idRetorno]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $this->procesarAsientoContable($idRetorno, [
            'id_empresa' => (int) $row['id_empresa'],
            'id_usuario' => (int) ($row['created_by'] ?? 0),
        ]);
    }

    /**
     * Persiste el asiento del retorno (solo si está en Emitida y queda completo y cuadrado).
     * Prioriza el asiento editado en la pestaña; si no viene completo, usa la sugerencia.
     */
    public function procesarAsientoContable(int $idRetorno, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $cab = $this->repository->find($idRetorno, $idEmpresa);
        if (!$cab) return;

        // Solo los retornos ACTIVOS (Emitida) tienen impacto contable.
        if (($cab['estado'] ?? '') !== 'Emitida') {
            return;
        }

        $numDoc = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
        $fecha  = $data['fecha_retorno'] ?? ($cab['fecha_retorno'] ?? date('Y-m-d'));

        $fuente = (!empty($data['asiento_detalles']) && is_array($data['asiento_detalles'])) ? $data['asiento_detalles'] : [];
        $manualCompleto = !empty($fuente);
        foreach ($fuente as $f) {
            if ((int) ($f['id_cuenta_contable'] ?? 0) <= 0) { $manualCompleto = false; break; }
        }
        if (!$manualCompleto) {
            $fuente = $this->obtenerAsientoSugerido($idEmpresa, $idRetorno);
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
                'referencia_detalle'   => ($d['referencia_detalle'] ?? '') ?: ('Retorno CV # ' . $numDoc),
                'documento_referencia' => 'Retorno CV # ' . $numDoc,
                'id_entidad'           => (int) ($cab['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles) || abs($totDebe - $totHaber) >= 0.005 || ($totDebe <= 0 && $totHaber <= 0)) {
            return;
        }

        $asientoService = new \App\Services\modulos\AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $previo = $asientoService->getAsientoPorOrigen('retorno_cv', $idRetorno, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $clienteNombre = $cab['cliente_nombre'] ?? 'Cliente';
        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'retorno_consignacion',
            'numero_comprobante'   => '',
            'concepto'             => 'Retorno de consignación # ' . $numDoc . ' - Cliente: ' . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'retorno_cv',
            'id_referencia_origen' => $idRetorno,
            'observaciones'        => $data['observaciones'] ?? ($cab['observaciones'] ?? null),
        ];

        $idGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idRetorno, $idEmpresa, $idGenerado);
    }

    /** Anula (y desvincula) el asiento del retorno, p. ej. al pasarlo a Borrador/Anulada. */
    private function anularAsientoSiExiste(int $idRetorno, int $idEmpresa, int $idUsuario): void
    {
        try {
            $cab = $this->repository->find($idRetorno, $idEmpresa);
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
            if ($idAsiento <= 0) return;
            $asientoService = new \App\Services\modulos\AsientoContableService(
                new \App\repositories\modulos\AsientoContableRepository(),
                new \App\Rules\modulos\AsientoContableRules(),
                $this->logService
            );
            $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
            $this->repository->updateAsientoContable($idRetorno, $idEmpresa, null);
        } catch (\Throwable $e) {
            error_log("[RetornoCV] No se pudo anular el asiento del retorno $idRetorno: " . $e->getMessage());
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Aplica un movimiento de inventario para una línea de retorno (entrada o salida),
     * respetando el criterio de si el producto/empresa afectan stock.
     */
    private function moverInventarioLinea(array $det, int $idEmpresa, int $idUsuario, array $empresaConfig, string $tipo, string $refTipo, int $refId, string $obs): void
    {
        $cant = (float) $det['cantidad'];
        $idBodega = (int) ($det['id_bodega'] ?? 0);
        if ($cant <= 0 || $idBodega <= 0) return;

        // El detalle de getDetalles ya trae inventariable/tipo_produccion (JOIN productos).
        if (!$this->afectaInventario($det, $empresaConfig)) return;

        $idProducto  = (int) $det['id_producto'];
        $stockActual = $this->inventarioRepo->getStockActual($idProducto, $idBodega, $idEmpresa);
        $delta       = ($tipo === 'entrada') ? $cant : -$cant;
        $nuevoStock  = $stockActual + $delta;

        $this->inventarioRepo->registrarMovimiento([
            'id_empresa'      => $idEmpresa,
            'id_producto'     => $idProducto,
            'id_bodega'       => $idBodega,
            'tipo_movimiento' => $tipo,
            'referencia_tipo' => $refTipo,
            'referencia_id'   => $refId,
            'cantidad'        => $delta,
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
     * Trae la línea de consignación de origen con los datos autoritativos a copiar "tal cual".
     */
    private function getConsignacionDetalle(PDO $db, int $idConsDet, int $idEmpresa): ?array
    {
        $sql = "SELECT cvd.*, cv.serie, cv.secuencial, cv.id_cliente,
                       p.nombre as producto_nombre, p.inventariable, p.tipo_produccion
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                WHERE cvd.id = :id AND cvd.id_empresa = :e
                  AND cvd.eliminado = false AND cv.eliminado = false
                  AND cv.estado = 'Entregada'";
        $st = $db->prepare($sql);
        $st->execute([':id' => $idConsDet, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Determina si una línea debe mover inventario: producto inventariable, no servicio (tipo 02)
     * y con el control de inventario de facturación activo (igual criterio que la consignación).
     */
    private function afectaInventario(array $linea, array $empresaConfig): bool
    {
        $esInv = ($linea['inventariable'] == true || $linea['inventariable'] === 'true' || $linea['inventariable'] == 1 || $linea['inventariable'] === 't')
                 && (($linea['tipo_produccion'] ?? '01') !== '02');
        $soloStockPos = (($empresaConfig['facturacion_inventario'] ?? true) === 'true' || ($empresaConfig['facturacion_inventario'] ?? true) === true);
        return $esInv && $soloStockPos;
    }
}
