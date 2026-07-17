<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ImportacionesRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\ImportacionesRules;
use App\Services\LogSistemaService;
use App\core\Database;

class ImportacionesService
{
    private ImportacionesRepository $repository;
    private ImportacionesRules $rules;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repository = new ImportacionesRepository();
        $this->rules = new ImportacionesRules();
        $this->logService = new LogSistemaService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD CABECERA
    // ─────────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->validarGastosVinculados($data);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $data['numero'] = $data['numero'] ?? $this->repository->getSiguienteNumero($idEmpresa);
            $idImportacion = $this->repository->insertCabecera($data);

            $this->guardarFacturasExterior($idImportacion, $data['facturas_exterior'] ?? [], $idUsuario);
            $this->guardarDetalles($idImportacion, $data['detalles'] ?? [], $idUsuario);
            $this->guardarGastos($idImportacion, $data['gastos'] ?? [], $idUsuario);
            $this->recalcularTotales($idImportacion);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CREAR', 'importaciones_cabecera', $idImportacion,
                null, ['id_importacion' => $idImportacion]
            );

            if ($managed) $db->commit();
            return $idImportacion;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Importación no encontrada.');
        }
        if (in_array($cabecera['estado'], ['nacionalizada', 'cerrada', 'anulada'], true)) {
            throw new \Exception('No se puede modificar una importación ya nacionalizada, cerrada o anulada.');
        }

        $this->rules->validar($data);
        $this->validarGastosVinculados($data);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idUsuario = (int) $data['id_usuario'];

            $this->repository->updateCabecera($id, $data);

            $this->repository->deleteFacturasExterior($id);
            $this->guardarFacturasExterior($id, $data['facturas_exterior'] ?? [], $idUsuario);

            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles'] ?? [], $idUsuario);

            $this->repository->deleteGastos($id);
            $this->guardarGastos($id, $data['gastos'] ?? [], $idUsuario);

            $this->recalcularTotales($id);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'MODIFICAR', 'importaciones_cabecera', $id,
                $cabecera, ['id_importacion' => $id]
            );

            if ($managed) $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            return null;
        }

        $importacion['detalles']          = $this->repository->getDetalles($id);
        $importacion['facturas_exterior'] = $this->repository->getFacturasExterior($id);
        $importacion['gastos']            = $this->repository->getGastos($id);

        return $importacion;
    }

    public function eliminar(int $id, int $idUsuario, int $idEmpresa): bool
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            throw new \Exception('Importación no encontrada.');
        }
        if ($importacion['estado'] === 'nacionalizada') {
            throw new \Exception('No se puede eliminar una importación ya nacionalizada. Reviértala desde el kardex de inventario primero.');
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->eliminarLogico($id, $idUsuario);
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'ELIMINAR', 'importaciones_cabecera', $id,
                ['id' => $id], null
            );
            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRORRATEO (landed cost)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcula el costo unitario nacionalizado por línea, repartiendo el total
     * capitalizable (FOB facturado + gastos capitalizables) según el criterio
     * elegido ('fob' | 'peso' | 'volumen' | 'cantidad'). El residual de redondeo
     * se ajusta en la línea de mayor peso para que la suma cuadre exacto contra
     * el total (mismo patrón que AsientoBuilderService::aplicarAjusteRedondeo).
     */
    public function calcularProrrateo(array $detalles, float $totalCapitalizable, string $criterio): array
    {
        if (empty($detalles)) {
            return [];
        }

        $pesos = array_map(function ($d) use ($criterio) {
            return match ($criterio) {
                'peso'     => (float) ($d['peso_kg'] ?? 0),
                'volumen'  => (float) ($d['volumen_m3'] ?? 0),
                'cantidad' => (float) ($d['cantidad'] ?? 0),
                default    => (float) ($d['precio_total_fob'] ?? 0),
            };
        }, $detalles);

        $totalPeso = array_sum($pesos);
        if ($totalPeso <= 0.0) {
            throw new \Exception("No se puede prorratear por '{$criterio}': el total de esa base es cero en las líneas.");
        }

        $acumulado = 0.0;
        $idxMayor  = 0;
        $mayorPeso = -1.0;
        $detalles  = array_values($detalles);

        foreach ($detalles as $i => $d) {
            $peso = $pesos[$i];
            if ($peso > $mayorPeso) { $mayorPeso = $peso; $idxMayor = $i; }
            $costoTotal = round($totalCapitalizable * ($peso / $totalPeso), 2);
            $detalles[$i]['costo_total_nacionalizado'] = $costoTotal;
            $acumulado += $costoTotal;
        }

        $residual = round($totalCapitalizable - $acumulado, 2);
        if ($residual !== 0.0) {
            $detalles[$idxMayor]['costo_total_nacionalizado'] = round($detalles[$idxMayor]['costo_total_nacionalizado'] + $residual, 2);
        }

        foreach ($detalles as $i => $d) {
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $detalles[$i]['costo_unitario_nacionalizado'] = $cantidad > 0
                ? round($d['costo_total_nacionalizado'] / $cantidad, 6)
                : 0.0;
        }

        return $detalles;
    }

    /**
     * Clasifica los gastos en los 4 baldes del landed cost. Un gasto VINCULADO
     * (ya registrado como Compra/Liquidación) siempre se trata como capitalizable:
     * su propio documento ya gestionó su IVA/CxP, aquí solo aporta al costo.
     */
    private function calcularTotalesGastos(array $gastos): array
    {
        $capitalizableManual    = 0.0;
        $capitalizableVinculado = 0.0;
        $iva                    = 0.0;
        $isd                    = 0.0;
        $otros                  = 0.0;

        foreach ($gastos as $g) {
            $monto        = (float) ($g['monto'] ?? 0);
            $origen       = $g['origen'] ?? 'dai_manual';
            $tipo         = $g['tipo_gasto'] ?? 'otro';
            $prorrateable = !empty($g['prorrateable']);

            if ($origen !== 'dai_manual') {
                $capitalizableVinculado += $monto;
                continue;
            }
            if ($prorrateable) {
                $capitalizableManual += $monto;
            } elseif ($tipo === 'iva_importacion') {
                $iva += $monto;
            } elseif ($tipo === 'isd') {
                $isd += $monto;
            } else {
                $otros += $monto;
            }
        }

        return [
            'capitalizable_manual'    => round($capitalizableManual, 2),
            'capitalizable_vinculado' => round($capitalizableVinculado, 2),
            'capitalizable_total'     => round($capitalizableManual + $capitalizableVinculado, 2),
            'iva'                     => round($iva, 2),
            'isd'                     => round($isd, 2),
            'otros'                   => round($otros, 2),
        ];
    }

    /**
     * Recalcula y persiste los totales de la cabecera a partir del estado actual
     * en BD (detalle + gastos + facturas del exterior). No prorratea a las líneas;
     * eso solo ocurre al procesar el inventario.
     */
    private function recalcularTotales(int $idImportacion): void
    {
        $detalles = $this->repository->getDetalles($idImportacion);
        $gastos   = $this->repository->getGastos($idImportacion);
        $facturas = $this->repository->getFacturasExterior($idImportacion);

        $subtotalFob          = array_sum(array_map(fn($d) => (float) $d['precio_total_fob'], $detalles));
        $totalFacturaExterior = array_sum(array_map(fn($f) => (float) $f['monto_usd'], $facturas));
        $tg = $this->calcularTotalesGastos($gastos);

        $this->repository->actualizarTotales($idImportacion, [
            'subtotal_fob'                => round($subtotalFob, 2),
            'total_gastos_capitalizables' => $tg['capitalizable_total'],
            'total_iva'                   => $tg['iva'],
            'total_isd'                   => $tg['isd'],
            'total_otros_gastos'          => $tg['otros'],
            'costo_total_nacionalizado'   => round($totalFacturaExterior, 2) + $tg['capitalizable_total'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROCESAR INVENTARIO (nacionalización — carga en lote al kardex)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Prorratea el costo entre las líneas y las postea al kardex (una entrada por
     * línea, referencia_tipo='importacion'), cambia el estado a 'nacionalizada' y
     * genera el asiento contable. Equivalente a ComprasController::procesarInventarioAjax
     * pero para todas las líneas de una vez (carga masiva).
     *
     * Nota (Fase 1): no integra el flujo de aprobación de cargas de inventario
     * (empresa_establecimiento.inv_requiere_aprobacion) — la nacionalización se
     * postea de inmediato. Queda pendiente para cuando se construya el Controller/Vista.
     */
    public function procesarInventario(int $id, int $idEmpresa, int $idUsuario): array
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            throw new \Exception('Importación no encontrada.');
        }

        $detalles = $this->repository->getDetalles($id);
        $gastos   = $this->repository->getGastos($id);
        $facturas = $this->repository->getFacturasExterior($id);

        $this->rules->validarParaNacionalizar($importacion, $gastos);

        foreach ($detalles as $d) {
            if (empty($d['id_producto'])) {
                $raw = $d['codigo_producto_raw'] ?? $d['descripcion'] ?? ('línea #' . $d['id']);
                throw new \Exception("La línea '{$raw}' no tiene producto homologado. Vincule el producto antes de procesar el inventario.");
            }
        }

        $totalFacturaExterior    = array_sum(array_map(fn($f) => (float) $f['monto_usd'], $facturas));
        $tg                      = $this->calcularTotalesGastos($gastos);
        $costoTotalNacionalizado = round($totalFacturaExterior + $tg['capitalizable_total'], 2);

        $detallesConCosto = $this->calcularProrrateo($detalles, $costoTotalNacionalizado, $importacion['criterio_prorrateo']);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $inventarioService = new InventarioService(new InventarioRepository(), $this->logService);

            foreach ($detallesConCosto as $d) {
                $idBodega = !empty($d['id_bodega']) ? (int) $d['id_bodega'] : (int) $importacion['id_bodega_destino'];

                $idKardex = $inventarioService->ajusteManual([
                    'id_producto'     => (int) $d['id_producto'],
                    'id_bodega'       => $idBodega,
                    'tipo_movimiento' => 'entrada',
                    'referencia_tipo' => 'importacion',
                    'referencia_id'   => (int) $d['id'],
                    'costo_unitario'  => $d['costo_unitario_nacionalizado'],
                    'cantidad'        => $d['cantidad'],
                    'numero_lote'     => $d['numero_lote'] ?? null,
                    'fecha_caducidad' => $d['fecha_caducidad'] ?? null,
                    'nup'             => $d['nup'] ?? null,
                    'id_medida'       => $d['id_medida'] ?? null,
                    'observaciones'   => 'Importación #' . $importacion['numero'],
                ], $idEmpresa, $idUsuario);

                $this->repository->actualizarCostoNacionalizado(
                    (int) $d['id'],
                    (float) $d['costo_unitario_nacionalizado'],
                    (float) $d['costo_total_nacionalizado']
                );
                $this->repository->actualizarKardexDetalle((int) $d['id'], $idKardex);
            }

            $this->repository->actualizarTotales($id, [
                'subtotal_fob'                => round(array_sum(array_map(fn($d) => (float) $d['precio_total_fob'], $detalles)), 2),
                'total_gastos_capitalizables' => $tg['capitalizable_total'],
                'total_iva'                   => $tg['iva'],
                'total_isd'                   => $tg['isd'],
                'total_otros_gastos'          => $tg['otros'],
                'costo_total_nacionalizado'   => $costoTotalNacionalizado,
            ]);

            $this->repository->actualizarEstado($id, 'nacionalizada', $idUsuario, date('Y-m-d'));

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'NACIONALIZAR', 'importaciones_cabecera', $id,
                ['estado' => $importacion['estado']],
                ['estado' => 'nacionalizada', 'costo_total_nacionalizado' => $costoTotalNacionalizado]
            );

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo de configuración de
        // cuentas no revierte la nacionalización ya posteada al kardex (mismo patrón que Compras).
        $warning = null;
        try {
            $this->procesarAsientoContable($id, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            error_log("[Importaciones] Asiento no generado para importación $id: " . $e->getMessage());
            $warning = $e->getMessage();
        }

        return [
            'id'                       => $id,
            'costo_total_nacionalizado' => $costoTotalNacionalizado,
            'asiento_warning'          => $warning,
        ];
    }

    public function procesarAsientoContable(int $idImportacion, int $idEmpresa, int $idUsuario): void
    {
        $builder  = new AsientoBuilderService();
        $detalles = $builder->generarAsientoImportacion($idEmpresa, $idImportacion);
        if (empty($detalles)) {
            return;
        }

        $importacion = $this->repository->getPorId($idImportacion, $idEmpresa);

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('importacion', $idImportacion, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $importacion['fecha_nacionalizacion'] ?? date('Y-m-d'),
            'tipo_comprobante'     => 'importaciones',
            'numero_comprobante'   => '',
            'concepto'             => 'Importación #' . $importacion['numero'] . ' - Proveedor: ' . $importacion['proveedor_nombre'],
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'importacion',
            'id_referencia_origen' => $idImportacion,
            'observaciones'        => $importacion['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idImportacion, $idAsientoGenerado);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PERSISTENCIA DE HIJOS
    // ─────────────────────────────────────────────────────────────────────

    private function guardarDetalles(int $idImportacion, array $detalles, int $idUsuario): void
    {
        foreach ($detalles as $det) {
            $det['id_importacion'] = $idImportacion;
            $det['id_usuario']     = $idUsuario;
            $cant   = (float) ($det['cantidad'] ?? 0);
            $precio = (float) ($det['precio_unitario_fob'] ?? 0);
            $det['precio_total_fob'] = $det['precio_total_fob'] ?? ($cant * $precio);
            $this->repository->insertDetalle($det);
        }
    }

    private function guardarFacturasExterior(int $idImportacion, array $facturas, int $idUsuario): void
    {
        foreach ($facturas as $f) {
            $f['id_importacion'] = $idImportacion;
            $f['id_usuario']     = $idUsuario;
            $this->repository->insertFacturaExterior($f);
        }
    }

    private function guardarGastos(int $idImportacion, array $gastos, int $idUsuario): void
    {
        foreach ($gastos as $g) {
            $g['id_importacion'] = $idImportacion;
            $g['id_usuario']     = $idUsuario;
            // Un gasto vinculado siempre es capitalizable (su propio documento ya
            // resolvió el IVA/CxP; aquí no puede ser un rubro tipo IVA/ISD).
            if (($g['origen'] ?? 'dai_manual') !== 'dai_manual') {
                $g['prorrateable'] = true;
            }
            $this->repository->insertGasto($g);
        }
    }

    /**
     * Verifica que las Compras/Liquidaciones vinculadas existan y sean de la misma
     * empresa (evita vincular documentos ajenos o inexistentes).
     */
    private function validarGastosVinculados(array $data): void
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        foreach ($data['gastos'] ?? [] as $idx => $g) {
            $origen = $g['origen'] ?? 'dai_manual';
            $num = $idx + 1;
            if ($origen === 'compra_vinculada') {
                $compra = $this->repository->getCompraParaVincular((int) ($g['id_compra'] ?? 0), $idEmpresa);
                if (!$compra) {
                    throw new \Exception("El gasto #{$num} referencia una Compra que no existe en esta empresa.");
                }
            } elseif ($origen === 'liquidacion_vinculada') {
                $liq = $this->repository->getLiquidacionParaVincular((int) ($g['id_liquidacion_compra'] ?? 0), $idEmpresa);
                if (!$liq) {
                    throw new \Exception("El gasto #{$num} referencia una Liquidación de Compra que no existe en esta empresa.");
                }
            }
        }
    }
}
