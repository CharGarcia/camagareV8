<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ReciboVentaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\ReciboVentaRules;
use App\Services\LogSistemaService;
use App\core\Database;

/**
 * Lógica de negocio del módulo Recibos de Venta.
 * Espejo simplificado de FacturaVentaService: mismos ítems, cálculo de
 * impuestos, inventario y asiento contable, pero SIN SRI/XML/clave de acceso,
 * notas de crédito, retenciones ni casilleros de declaración.
 *
 * El toggle "con_impuestos" se calcula en el frontend (igual que factura pero
 * sin sumar IVA/ICE cuando está desactivado); aquí solo se persiste la bandera
 * y los impuestos que llegan (cero cuando es "sin impuestos").
 */
class ReciboVentaService
{
    private ReciboVentaRepository $repository;
    private ReciboVentaRules $rules;
    private LogSistemaService $logService;
    private ?BodegaService $bodegaService = null;
    private ?InventarioService $inventarioService = null;
    private ?EmpresaRepository $empresaRepository = null;
    private ?string $lastAsientoWarning = null;

    /** Tipo de referencia usado en el kardex y en el asiento contable. */
    private const REF_TIPO = 'recibo_venta';

    public function getLastAsientoWarning(): ?string
    {
        return $this->lastAsientoWarning;
    }

    public function __construct(ReciboVentaRepository $repository, ReciboVentaRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
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

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }

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

    /**
     * Enriquece cada detalle con la info de control de inventario del producto y
     * normaliza el nombre. Devuelve el arreglo modificado.
     */
    private function enriquecerDetalles(array &$data): void
    {
        if (!empty($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as &$det) {
                if (empty($det['nombre']) && !empty($det['descripcion'])) {
                    $det['nombre'] = $det['descripcion'];
                }
                if (!empty($det['id_producto'])) {
                    $infoInv = $this->getInventarioService()->getProductoRepository()->getInfoControlInventario((int)$det['id_producto'], (int)$data['id_empresa']);
                    $det['inventariable']   = $infoInv['inventariable'] ?? false;
                    $det['tipo_produccion'] = $infoInv['tipo_produccion'] ?? '';
                }
            }
            unset($det);
        }
    }

    /**
     * Normaliza lotes y valida stock acumulado (si el establecimiento exige
     * solo stock positivo). Idéntico a factura pero excluyendo por REF_TIPO.
     */
    private function normalizarYValidarStock(array &$data, ?array $estConfig, ?int $idActual): void
    {
        if (!$estConfig || empty($data['detalles']) || !is_array($data['detalles'])) {
            return;
        }
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

        $cantidadesAgregadas = [];
        foreach ($data['detalles'] as &$det) {
            if (!empty($det['id_producto'])) {
                $afectaInv = $toBool($estConfig['facturacion_inventario'] ?? false);
                $obliLotes = $toBool($estConfig['obligatorio_lotes'] ?? false);

                if ($det['inventariable'] && $afectaInv && ($det['tipo_produccion'] ?? '') !== '02' && empty($det['es_libre'])) {
                    if (!$obliLotes && empty($det['lote'])) {
                        $det['lote']      = 'sin_lote';
                        $det['caducidad'] = date('Y-m-d');
                        $det['nup']       = null;
                    }
                    $key = (int)$det['id_producto'] . '_' . (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)) . '_' . ($det['lote'] ?? 'sin_lote');
                    $cantidadesAgregadas[$key] = ($cantidadesAgregadas[$key] ?? 0) + (float)($det['cantidad'] ?? 0);
                }
            }
        }
        unset($det);

        $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);
        if (!$soloStockPos) {
            return;
        }

        $validados = [];
        foreach ($data['detalles'] as $det) {
            if (!empty($det['id_producto']) && ($det['inventariable'] ?? false) && empty($det['es_libre']) && ($det['tipo_produccion'] ?? '') !== '02') {
                $key = (int)$det['id_producto'] . '_' . (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)) . '_' . ($det['lote'] ?? 'sin_lote');
                if (!in_array($key, $validados)) {
                    $stockTotal = $this->getInventarioService()->getRepository()->getStockActual(
                        (int)$det['id_producto'],
                        (int)($det['id_bodega'] ?? ($data['id_bodega'] ?? 0)),
                        (int)$data['id_empresa'],
                        $idActual,
                        ($idActual ? self::REF_TIPO : null),
                        !empty($det['lote']) ? (string)$det['lote'] : null
                    );
                    $cantidadAcumulada = $cantidadesAgregadas[$key];
                    if ($stockTotal < $cantidadAcumulada) {
                        throw new \Exception("Stock insuficiente para el producto: " . ($det['nombre'] ?? 'Producto') . " (Lote: ".($det['lote'] ?? 'sin_lote')."). Saldo actual: {$stockTotal}, Requerido en recibo: {$cantidadAcumulada}");
                    }
                    $validados[] = $key;
                }
            }
        }
    }

    /** Persiste detalles, impuestos, lotes, pagos e info adicional (crear/editar). */
    private function guardarLineas(int $idRecibo, array &$data, int $idEmpresa, int $idUsuario): void
    {
        if (!empty($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as &$d) {
                if (!empty($d['es_libre']) && $d['es_libre'] == '1' && empty($d['id_producto'])) {
                    $d['id_producto'] = $this->repository->crearServicioLibre(
                        $idEmpresa,
                        $idUsuario,
                        $d['nombre'] ?? ($d['descripcion'] ?? ''),
                        (float) ($d['precio_unitario'] ?? 0),
                        isset($d['porcentaje_iva']) ? (float) $d['porcentaje_iva'] : null,
                        isset($d['codigo_porcentaje']) ? (string) $d['codigo_porcentaje'] : null
                    );
                }
                $d['id_recibo'] = $idRecibo;
                $idDetalle      = $this->repository->insertDetalle($d);
                $d['id']        = $idDetalle;

                if (!empty($d['lote']) || !empty($d['caducidad']) || !empty($d['nup'])) {
                    $this->repository->updateDetalleLoteNup($idDetalle, [
                        'numero_lote'     => !empty($d['lote'])      ? $d['lote']      : null,
                        'fecha_caducidad' => !empty($d['caducidad']) ? $d['caducidad'] : null,
                        'nup'             => !empty($d['nup'])       ? $d['nup']       : null,
                    ]);
                }

                if (!empty($d['impuestos'])) {
                    foreach ($d['impuestos'] as $imp) {
                        $imp['id_recibo_detalle'] = $idDetalle;
                        $this->repository->insertImpuesto($imp);
                    }
                }
            }
            unset($d);
        }

        if (!empty($data['pagos']) && is_array($data['pagos'])) {
            foreach ($data['pagos'] as $p) {
                $p['id_recibo'] = $idRecibo;
                $this->repository->insertPago($p);
            }
        }

        if (!empty($data['info_adicional']) && is_array($data['info_adicional'])) {
            foreach ($data['info_adicional'] as $ia) {
                $ia['id_recibo'] = $idRecibo;
                $this->repository->insertInfoAdicional($ia);
            }
        }
    }

    public function crear(array $data): int
    {
        $this->validarSecuencial($data);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        // El recibo nace como BORRADOR (editable). Se puede anular (queda visible, sin
        // validez, conservando su número) o eliminar (borrado lógico, libera la numeración).
        $data['estado'] = $data['estado'] ?? 'borrador';

        $estConfig   = $this->getEmpresaRepository()->getEstablecimientoConfig((int)$data['id_establecimiento']);
        $clienteInfo = $this->repository->getTipoIdCliente((int)$data['id_cliente'], (int)$data['id_empresa']);
        $data['tipo_id_cliente'] = $clienteInfo['tipo_id'] ?? '';
        if (($clienteInfo['es_consumidor_final'] ?? false) || $data['tipo_id_cliente'] === '07') {
            $data['tipo_id_cliente'] = '07';
        }

        $this->enriquecerDetalles($data);
        $this->rules->validar($data, $estConfig ?? []);
        $this->normalizarYValidarStock($data, $estConfig, null);

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];
            $nivel     = (int) ($_SESSION['nivel'] ?? 1);

            $idBodega = (int) ($data['id_bodega'] ?? 0);
            if ($idBodega > 0 && !$this->getBodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \Exception('Acceso denegado a la bodega seleccionada.');
            }

            $idRecibo = $this->repository->insertCabecera($data);
            $this->guardarLineas($idRecibo, $data, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CREAR', 'recibos_venta_cabecera', $idRecibo,
                null, ['id_recibo' => $idRecibo, 'total' => $data['importe_total'] ?? 0]
            );

            $numRecibo = $data['establecimiento'] . '-' . $data['punto_emision'] . '-' . str_pad((string)$data['secuencial'], 9, '0', STR_PAD_LEFT);
            $this->getInventarioService()->procesarSalidaPorVenta(
                $idRecibo, $data['detalles'] ?? [], (int)$data['id_establecimiento'],
                $idEmpresa, $idUsuario, "Recibo # $numRecibo", false, self::REF_TIPO
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Asiento contable fuera de la transacción (un fallo no revierte el recibo).
        $this->lastAsientoWarning = null;
        try {
            $this->procesarAsientoContable($idRecibo, $data, $numRecibo);
        } catch (\Throwable $eAsiento) {
            error_log("[ReciboVenta] Asiento no generado para recibo $idRecibo: " . $eAsiento->getMessage());
            $this->lastAsientoWarning = $eAsiento->getMessage();
        }

        return $idRecibo;
    }

    public function actualizar(int $id, array $data): int
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== (int)$data['id_empresa']) {
            throw new \Exception('Recibo no encontrado.');
        }
        if (($cabecera['estado'] ?? '') === 'anulado') {
            throw new \Exception('No se puede modificar un recibo anulado.');
        }

        $this->validarSecuencial($data, $id);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? ($cabecera['tipo_ambiente'] ?? '1'));

        $estConfig   = $this->getEmpresaRepository()->getEstablecimientoConfig((int)$data['id_establecimiento']);
        $clienteInfo = $this->repository->getTipoIdCliente((int)$data['id_cliente'], (int)$data['id_empresa']);
        $data['tipo_id_cliente'] = $clienteInfo['tipo_id'] ?? '';
        if (($clienteInfo['es_consumidor_final'] ?? false) || $data['tipo_id_cliente'] === '07') {
            $data['tipo_id_cliente'] = '07';
        }

        $this->enriquecerDetalles($data);
        $this->rules->validar($data, $estConfig ?? []);
        $this->normalizarYValidarStock($data, $estConfig, $id);

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];
            $nivel     = (int) ($_SESSION['nivel'] ?? 1);

            $idBodega = (int) ($data['id_bodega'] ?? 0);
            if ($idBodega > 0 && !$this->getBodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \Exception('Acceso denegado a la bodega seleccionada.');
            }

            $this->repository->updateCabecera($id, $data);
            $this->repository->deleteDetalles($id);
            $this->repository->deletePagos($id);
            $this->repository->deleteInfoAdicional($id);
            $this->guardarLineas($id, $data, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'MODIFICAR', 'recibos_venta_cabecera', $id,
                $cabecera, ['id_recibo' => $id, 'total' => $data['importe_total'] ?? 0]
            );

            $numRecibo = $data['establecimiento'] . '-' . $data['punto_emision'] . '-' . str_pad((string)$data['secuencial'], 9, '0', STR_PAD_LEFT);
            $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $id, $idEmpresa, $idUsuario);
            $this->getInventarioService()->procesarSalidaPorVenta(
                $id, $data['detalles'] ?? [], (int)$data['id_establecimiento'],
                $idEmpresa, $idUsuario, "Recibo # $numRecibo", true, self::REF_TIPO
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        $this->lastAsientoWarning = null;
        try {
            $this->procesarAsientoContable($id, $data, $numRecibo);
        } catch (\Throwable $eAsiento) {
            error_log("[ReciboVenta] Asiento no generado para recibo $id: " . $eAsiento->getMessage());
            $this->lastAsientoWarning = $eAsiento->getMessage();
        }

        return $id;
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $res = $this->repository->getPorId($id);
        if (!$res || (int)$res['id_empresa'] !== $idEmpresa) return null;

        $res['detalles'] = $this->repository->getDetalles($id);
        foreach ($res['detalles'] as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);

        $res['pagos']          = $this->repository->getPagos($id);
        $res['info_adicional'] = $this->repository->getInfoAdicional($id);

        return $res;
    }

    /** Anula los ingresos (cobros) activos vinculados a este recibo. */
    private function anularCobrosVinculados(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $st = $db->prepare(
            "SELECT DISTINCT ic.id
             FROM ingresos_detalle idt
             INNER JOIN ingresos_cabecera ic ON idt.id_ingreso = ic.id
             WHERE idt.tipo_documento = 'RECIBO'
               AND idt.id_referencia_documento = ?
               AND ic.id_empresa = ?
               AND ic.eliminado = false
               AND ic.estado != 'anulado'"
        );
        $st->execute([$id, $idEmpresa]);
        $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($ids)) return;

        $ingresoService = new IngresoService(
            new \App\repositories\modulos\IngresoRepository(),
            new \App\Rules\modulos\IngresoRules(),
            $this->logService
        );
        foreach ($ids as $idIngreso) {
            $ingresoService->anular((int)$idIngreso, $idEmpresa, $idUsuario);
        }
    }

    /** Anula el asiento contable del recibo (si existe). */
    private function anularAsiento(array $cabecera, int $idEmpresa, int $idUsuario): void
    {
        $idAsiento = (int)($cabecera['id_asiento_contable'] ?? 0);
        if ($idAsiento <= 0) return;

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );
        try {
            $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
        } catch (\Throwable $eA) {
            if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                throw $eA;
            }
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \Exception('Recibo no encontrado.');
        }
        if (($cabecera['estado'] ?? '') === 'anulado') {
            throw new \Exception('El recibo ya está anulado.');
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $this->anularCobrosVinculados($id, $idEmpresa, $idUsuario);
            $this->anularAsiento($cabecera, $idEmpresa, $idUsuario);
            $this->repository->actualizarEstado($id, 'anulado', $idUsuario);
            $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'ANULAR', 'recibos_venta_cabecera', $id,
                $cabecera, ['id_recibo' => $id, 'nuevo_estado' => 'anulado']
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
            throw new \Exception('Recibo no encontrado.');
        }

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            // Revertir todo antes de eliminar lógicamente.
            $this->anularCobrosVinculados($id, $idEmpresa, $idUsuario);
            $this->anularAsiento($cabecera, $idEmpresa, $idUsuario);
            $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $id, $idEmpresa, $idUsuario);
            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'ELIMINAR', 'recibos_venta_cabecera', $id,
                $cabecera, ['id_recibo' => $id, 'eliminado' => true]
            );

            if ($managedTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Genera una FACTURA DE VENTA a partir de un recibo:
     *  - Recalcula el IVA de cada ítem según la tarifa del producto (la factura
     *    lleva impuestos obligatoriamente si los ítems tienen impuestos).
     *  - Crea la factura (numeración propia de facturas, inventario y asiento de
     *    factura los maneja FacturaVentaService).
     *  - Deja el recibo en estado 'facturado' (visible, conserva su número) y
     *    revierte todo lo suyo: inventario, cobros y asiento contable.
     *
     * @return array ['id_factura' => int, 'numero_factura' => string]
     */
    public function generarFacturaDesdeRecibo(int $idRecibo, int $idEmpresa, int $idUsuario, array $empresaConfig): array
    {
        $recibo = $this->getPorId($idRecibo, $idEmpresa);
        if (!$recibo) {
            throw new \Exception('Recibo no encontrado.');
        }
        $estado = $recibo['estado'] ?? '';
        if ($estado === 'anulado')   throw new \Exception('El recibo está anulado; no se puede facturar.');
        if ($estado === 'facturado') throw new \Exception('El recibo ya fue facturado.');
        if (empty($recibo['detalles'])) throw new \Exception('El recibo no tiene ítems.');

        $numRecibo = ($recibo['establecimiento'] ?? '') . '-' . ($recibo['punto_emision'] ?? '') . '-' . ($recibo['secuencial'] ?? '');

        // ── 1. Construir los detalles de la factura recalculando el IVA por ítem ──
        $detFactura = [];
        $ivaTotal   = 0.0;
        $idBodega   = 0;
        foreach ($recibo['detalles'] as $d) {
            $base = round((float) $d['precio_total_sin_impuesto'], 2);

            // Tarifa de IVA: la del producto (catálogo) o, si es ítem libre, la de la línea.
            $tar = null;
            if (!empty($d['id_producto'])) {
                $tar = $this->repository->getTarifaIvaProducto((int) $d['id_producto']);
            }
            if (!$tar && !empty($d['id_tarifa_iva'])) {
                $tar = $this->repository->getTarifaIvaById((int) $d['id_tarifa_iva']);
            }
            $pct    = $tar ? (float) $tar['porcentaje_iva'] : 0.0;
            $codPct = $tar ? (string) $tar['codigo'] : '0';
            $idTar  = $tar ? (int) $tar['id'] : (int) ($d['id_tarifa_iva'] ?? 0);

            $ivaLinea = round($base * $pct / 100, 2);
            $ivaTotal += $ivaLinea;

            if ($idBodega === 0 && !empty($d['id_bodega'])) {
                $idBodega = (int) $d['id_bodega'];
            }

            $detFactura[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'id_bodega'                 => !empty($d['id_bodega']) ? (int) $d['id_bodega'] : null,
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'codigo_principal'          => $d['codigo_principal'] ?? null,
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? null,
                'descripcion'               => $d['descripcion'],
                'nombre'                    => $d['descripcion'],
                'cantidad'                  => $d['cantidad'],
                'precio_unitario'           => $d['precio_unitario'],
                'descuento'                 => $d['descuento'],
                'precio_total_sin_impuesto' => $base,
                'id_tarifa_iva'             => $idTar,
                'lote'                      => $d['numero_lote'] ?? null,
                'caducidad'                 => $d['fecha_caducidad'] ?? null,
                'nup'                       => $d['nup'] ?? null,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa'            => $pct,
                    'base_imponible'    => $base,
                    'valor'             => $ivaLinea,
                ]],
            ];
        }

        $ivaTotal     = round($ivaTotal, 2);
        // total_sin_impuestos ya es NETO (suma de precio_total_sin_impuesto = base tras descuento),
        // por eso el descuento NO se vuelve a restar en el importe total.
        $totalSinImp  = round((float) $recibo['total_sin_impuestos'], 2);
        $totalDesc    = round((float) $recibo['total_descuento'], 2);
        $totalIce     = round((float) ($recibo['total_ice'] ?? 0), 2);
        $propina      = round((float) ($recibo['propina'] ?? 0), 2);
        $importeTotal = round($totalSinImp + $ivaTotal + $totalIce + $propina, 2);

        // ── 2. Secuencial propio de facturas para el mismo punto de emisión ──
        $sec = (new \App\Services\SecuencialService())
            ->obtenerSiguienteSecuencial((int) $recibo['id_punto_emision'], 'Facturas de venta');
        $secuencial = $sec['formateado'];
        $numFactura = ($recibo['establecimiento'] ?? '') . '-' . ($recibo['punto_emision'] ?? '') . '-' . $secuencial;

        // ── 3. Payload de la factura ──
        $pagos = [[
            'forma_pago'    => '01', // Sin utilización del sistema financiero (efectivo)
            'total'         => $importeTotal,
            'plazo'         => (int) ($recibo['dias_credito'] ?? 0),
            'unidad_tiempo' => 'dias',
        ]];

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaConfig,
            'id_establecimiento'  => (int) $recibo['id_establecimiento'],
            'id_punto_emision'    => (int) $recibo['id_punto_emision'],
            'establecimiento'     => $recibo['establecimiento'],
            'punto_emision'       => $recibo['punto_emision'],
            'secuencial'          => $secuencial,
            'fecha_emision'       => $recibo['fecha_emision'],
            'id_cliente'          => (int) $recibo['id_cliente'],
            'id_vendedor'         => !empty($recibo['id_vendedor']) ? (int) $recibo['id_vendedor'] : null,
            'dias_credito'        => (int) ($recibo['dias_credito'] ?? 0),
            'moneda'              => $recibo['moneda'] ?? 'DOLAR',
            'observaciones'       => trim('Generada desde recibo ' . $numRecibo . '. ' . ($recibo['observaciones'] ?? '')),
            'id_bodega'           => $idBodega,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => $totalDesc,
            'total_ice'           => $totalIce,
            'propina'             => $propina,
            'importe_total'       => $importeTotal,
            'detalles'            => $detFactura,
            'pagos'               => $pagos,
            'info_adicional'      => $recibo['info_adicional'] ?? [],
        ];

        $facturaService = new FacturaVentaService(
            new \App\repositories\modulos\FacturaVentaRepository(),
            new \App\Rules\modulos\FacturaVentaRules(),
            $this->logService
        );

        // ── 4. Liberar el inventario del recibo para que la factura pueda consumirlo,
        //       crear la factura y, si falla, restaurar el inventario del recibo. ──
        $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $idRecibo, $idEmpresa, $idUsuario);
        try {
            $idFactura = $facturaService->crear($payload);
        } catch (\Throwable $e) {
            try {
                $this->getInventarioService()->procesarSalidaPorVenta(
                    $idRecibo, $detFactura, (int) $recibo['id_establecimiento'],
                    $idEmpresa, $idUsuario, "Recibo # $numRecibo", false, self::REF_TIPO
                );
            } catch (\Throwable $e2) {
                error_log("[ReciboVenta] No se pudo restaurar inventario del recibo $idRecibo tras fallo de facturación: " . $e2->getMessage());
            }
            throw $e;
        }

        // ── 5. Factura creada: finalizar el recibo (cobros + asiento) y marcarlo facturado ──
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->anularCobrosVinculados($idRecibo, $idEmpresa, $idUsuario);
            $this->anularAsiento($recibo, $idEmpresa, $idUsuario);
            $this->repository->actualizarEstado($idRecibo, 'facturado', $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'FACTURAR', 'recibos_venta_cabecera', $idRecibo,
                $recibo, ['id_recibo' => $idRecibo, 'nuevo_estado' => 'facturado', 'id_factura' => $idFactura, 'numero_factura' => $numFactura]
            );

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            error_log("[ReciboVenta] Factura #$numFactura creada pero el recibo $idRecibo no quedó facturado: " . $e->getMessage());
            throw new \Exception("La factura {$numFactura} se generó, pero el recibo no pudo marcarse como facturado automáticamente. Anúlelo manualmente. Detalle: " . $e->getMessage());
        }

        return ['id_factura' => $idFactura, 'numero_factura' => $numFactura];
    }

    /**
     * Genera un RECIBO DE VENTA a partir de una factura de venta.
     *  - El recibo es un documento COMPLETO: descarga inventario y genera asiento
     *    (regla confirmada con el usuario), con numeración PROPIA de recibos.
     *  - Factura AUTORIZADA: no se toca (queda intacta).
     *  - Factura BORRADOR: según $accionBorrador se ELIMINA (traspasando su
     *    inventario al recibo) o se DEJA tal cual.
     *
     * @param string $accionBorrador 'eliminar' | 'dejar' (solo aplica a borradores)
     * @return array{id_recibo:int, numero_recibo:string, factura_eliminada:bool}
     */
    public function generarReciboDesdeFactura(int $idFactura, int $idEmpresa, int $idUsuario, array $empresaConfig, string $accionBorrador = 'dejar'): array
    {
        $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
        $factura = $facturaRepo->getPorId($idFactura);
        if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \Exception('Factura no encontrada.');
        }
        $estadoFactura = $factura['estado'] ?? '';
        if ($estadoFactura === 'anulado') {
            throw new \Exception('La factura está anulada; no se puede generar un recibo.');
        }
        $eliminarFactura = ($estadoFactura === 'borrador') && ($accionBorrador === 'eliminar');

        $detallesF = $facturaRepo->getDetalles($idFactura);
        foreach ($detallesF as &$d) {
            $d['impuestos'] = $facturaRepo->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);
        if (empty($detallesF)) {
            throw new \Exception('La factura no tiene ítems.');
        }
        $pagosF = $facturaRepo->getPagos($idFactura);
        $infoF  = $facturaRepo->getInfoAdicional($idFactura);

        $numFactura = ($factura['establecimiento'] ?? '') . '-' . ($factura['punto_emision'] ?? '') . '-' . ($factura['secuencial'] ?? '');

        // ── Detalles del recibo (espejo de la factura, con impuestos) ──
        $detRecibo = [];
        $idBodega  = 0;
        foreach ($detallesF as $d) {
            if ($idBodega === 0 && !empty($d['id_bodega'])) $idBodega = (int)$d['id_bodega'];
            $detRecibo[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int)$d['id_producto'] : null,
                'id_bodega'                 => !empty($d['id_bodega']) ? (int)$d['id_bodega'] : null,
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'id_medida'                 => $d['id_unidad_medida'] ?? null,
                'codigo_principal'          => $d['codigo_principal'] ?? null,
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? null,
                'descripcion'               => $d['descripcion'] ?? '',
                'nombre'                    => $d['descripcion'] ?? '',
                'info_adicional'            => $d['info_adicional'] ?? null,
                'cantidad'                  => $d['cantidad'],
                'precio_unitario'           => $d['precio_unitario'],
                'descuento'                 => $d['descuento'] ?? 0,
                'precio_total_sin_impuesto' => $d['precio_total_sin_impuesto'] ?? 0,
                'id_tarifa_iva'             => !empty($d['id_tarifa_iva']) ? (int)$d['id_tarifa_iva'] : null,
                'lote'                      => $d['numero_lote'] ?? null,
                'caducidad'                 => $d['fecha_caducidad'] ?? null,
                'nup'                       => $d['nup'] ?? null,
                'impuestos'                 => $d['impuestos'] ?? [],
            ];
        }

        // ── Secuencial PROPIO de recibos para el mismo punto de emisión ──
        $sec = (new \App\Services\SecuencialService())
            ->obtenerSiguienteSecuencial((int)$factura['id_punto_emision'], 'Recibos de venta');
        $secuencial   = $sec['formateado'];
        $numeroRecibo = ($factura['establecimiento'] ?? '') . '-' . ($factura['punto_emision'] ?? '') . '-' . $secuencial;

        // ── Pagos (metadata de forma de pago, espejo de la factura) ──
        $pagosRecibo = [];
        foreach ($pagosF as $p) {
            $pagosRecibo[] = [
                'forma_pago'    => $p['forma_pago'] ?? '01',
                'total'         => (float)($p['total'] ?? 0),
                'plazo'         => (int)($p['plazo'] ?? 0),
                'unidad_tiempo' => 'dias',
            ];
        }
        if (empty($pagosRecibo)) {
            $pagosRecibo = [[
                'forma_pago'    => '01',
                'total'         => (float)($factura['importe_total'] ?? 0),
                'plazo'         => (int)($factura['plazo'] ?? 0),
                'unidad_tiempo' => 'dias',
            ]];
        }

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaConfig,
            'id_establecimiento'  => (int)$factura['id_establecimiento'],
            'id_punto_emision'    => (int)$factura['id_punto_emision'],
            'establecimiento'     => $factura['establecimiento'],
            'punto_emision'       => $factura['punto_emision'],
            'secuencial'          => $secuencial,
            'fecha_emision'       => $factura['fecha_emision'] ?? date('Y-m-d'),
            'id_cliente'          => (int)$factura['id_cliente'],
            'id_vendedor'         => !empty($factura['id_vendedor']) ? (int)$factura['id_vendedor'] : null,
            'dias_credito'        => (int)($factura['plazo'] ?? 0),
            'plazo'               => (int)($factura['plazo'] ?? 0),
            'moneda'              => $factura['moneda'] ?? 'DOLAR',
            'con_impuestos'       => true,
            'estado'              => 'borrador',
            'observaciones'       => trim('Generado desde factura ' . $numFactura . '. ' . ($factura['observaciones'] ?? '')),
            'id_bodega'           => $idBodega,
            'total_sin_impuestos' => (float)($factura['total_sin_impuestos'] ?? 0),
            'total_descuento'     => (float)($factura['total_descuento'] ?? 0),
            'total_ice'           => (float)($factura['total_ice'] ?? 0),
            'propina'             => (float)($factura['propina'] ?? 0),
            'importe_total'       => (float)($factura['importe_total'] ?? 0),
            'detalles'            => $detRecibo,
            'pagos'               => $pagosRecibo,
            'info_adicional'      => $infoF,
        ];

        // Si vamos a ELIMINAR la factura borrador, revertimos primero su inventario
        // para liberar stock (así el recibo lo consume sin chocar con "solo stock
        // positivo"). El revert es idempotente: el eliminar posterior no lo duplica.
        if ($eliminarFactura) {
            $this->getInventarioService()->revertirMovimientosPorReferencia('factura_venta', $idFactura, $idEmpresa, $idUsuario);
        }

        try {
            $idRecibo = $this->crear($payload);
        } catch (\Throwable $e) {
            if ($eliminarFactura) {
                // Restaurar el inventario de la factura que revertimos.
                try {
                    $this->getInventarioService()->procesarSalidaPorVenta(
                        $idFactura, $detallesF, (int)$factura['id_establecimiento'],
                        $idEmpresa, $idUsuario, "Factura # $numFactura", false, 'factura_venta'
                    );
                } catch (\Throwable $e2) {
                    error_log("[ReciboVenta] No se pudo restaurar inventario de factura $idFactura tras fallo de recibo: " . $e2->getMessage());
                }
            }
            throw $e;
        }

        // Trazabilidad recibo → factura de origen.
        $this->repository->setFacturaOrigen($idRecibo, $idFactura);

        // Eliminar la factura borrador si corresponde (su inventario ya fue revertido).
        $facturaEliminada = false;
        if ($eliminarFactura) {
            try {
                $facturaService = new FacturaVentaService(
                    $facturaRepo,
                    new \App\Rules\modulos\FacturaVentaRules(),
                    $this->logService
                );
                $facturaService->eliminar($idFactura, $idEmpresa, $idUsuario);
                $facturaEliminada = true;
            } catch (\Throwable $e) {
                error_log("[ReciboVenta] Recibo $numeroRecibo creado, pero no se pudo eliminar la factura borrador $idFactura: " . $e->getMessage());
            }
        }

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'GENERAR_RECIBO', 'recibos_venta_cabecera', $idRecibo,
            null,
            ['id_factura_origen' => $idFactura, 'numero_factura' => $numFactura, 'numero_recibo' => $numeroRecibo, 'factura_eliminada' => $facturaEliminada]
        );

        return ['id_recibo' => $idRecibo, 'numero_recibo' => $numeroRecibo, 'factura_eliminada' => $facturaEliminada];
    }

    public function obtenerAsientoSugerido(int $idEmpresa, array $invoiceData): array
    {
        // Reutiliza la plantilla de asiento de facturas de venta (mismas cuentas:
        // CxC/caja al debe, ventas + IVA al haber).
        $builder = new AsientoBuilderService();
        return $builder->generarAsientoSugerido($idEmpresa, 'ventas_factura', $invoiceData);
    }

    public function procesarAsientoContablePorSincronizacion(int $idRecibo): void
    {
        $cabecera = $this->repository->getPorId($idRecibo);
        if (!$cabecera) return;
        $numRecibo = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-' . ($cabecera['secuencial'] ?? '');
        $this->procesarAsientoContable($idRecibo, $cabecera, $numRecibo);
    }

    public function procesarAsientoContable(int $idRecibo, array $data, string $numRecibo): void
    {
        $idEmpresa = (int)$data['id_empresa'];
        $idUsuario = (int)$data['id_usuario'];
        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');

        $clienteRepo = new \App\repositories\modulos\ClienteRepository();
        $cliente = $clienteRepo->findById((int)$data['id_cliente'], $idEmpresa);
        $clienteNombre = $cliente['nombre'] ?? 'Consumidor Final';

        $data['id_venta'] = $idRecibo;
        $detallesSugeridos = $this->obtenerAsientoSugerido($idEmpresa, $data);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Recibo # $numRecibo",
                'documento_referencia' => "Recibo # $numRecibo",
                'id_entidad'           => (int)$data['id_cliente'],
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles)) {
            return;
        }

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $asientoPrevio = $asientoService->getAsientoPorOrigen(self::REF_TIPO, $idRecibo, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fechaEmision,
            'tipo_comprobante'     => 'ventas',
            'numero_comprobante'   => '',
            'concepto'             => "Recibo # " . $numRecibo . " - Cliente: " . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => self::REF_TIPO,
            'id_referencia_origen' => $idRecibo,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idRecibo, $idAsientoGenerado);
    }
}
