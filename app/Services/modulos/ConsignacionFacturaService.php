<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ConsignacionFacturaRepository;
use App\repositories\modulos\ConsignacionVentaRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\Rules\modulos\ConsignacionFacturaRules;
use App\Rules\modulos\FacturaVentaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;
use Exception;

/**
 * Documento "Facturación de Consignaciones en Ventas".
 *
 * Documento con estructura de factura (fecha, serie, secuencial propios, cliente
 * a facturar, vendedor) cuyas líneas provienen de una o varias consignaciones
 * ENTREGADAS. Flujo de DOS PASOS:
 *   1. crear()/actualizar(): guarda el documento como 'borrador' (editable). No
 *      toca inventario ni crea factura.
 *   2. generarFactura(): desde un borrador, REINGRESA la mercadería al inventario
 *      (entrada espejo + asiento inverso Inventario/Mercadería en Consignación) y
 *      crea la Factura de Venta NORMAL, facturada al cliente del documento.
 *      El documento queda 'facturada' y ligado a la factura.
 *
 * Al anular/eliminar la factura, reversarPorFactura() deshace el reingreso, libera
 * el saldo y deja el documento 'anulada'.
 */
class ConsignacionFacturaService
{
    private ConsignacionFacturaRepository $repository;
    private ConsignacionVentaRepository $consignacionRepo;
    private ConsignacionFacturaRules $rules;
    private LogSistemaService $logService;
    private InventarioRepository $inventarioRepo;
    private ?InventarioService $inventarioService = null;

    private const REF_TIPO = 'FACTURACION_CV';

    public function __construct(
        ConsignacionFacturaRepository $repository,
        ConsignacionFacturaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository       = $repository;
        $this->rules            = $rules;
        $this->logService       = $logService;
        $this->consignacionRepo = new ConsignacionVentaRepository();
        $this->inventarioRepo   = new InventarioRepository();
    }

    private function getInventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService($this->inventarioRepo, $this->logService);
        }
        return $this->inventarioService;
    }

    // ─── Lecturas ─────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function buscarConsignacionesFacturables(int $idEmpresa, string $q): array
    {
        return $this->repository->buscarConsignacionesFacturables($idEmpresa, $q);
    }

    public function getLineasFacturables(int $idEmpresa, int $idConsignacion): array
    {
        $lineas = $this->repository->getLineasFacturables($idEmpresa, $idConsignacion);
        $repoProd = new \App\repositories\modulos\ProductoRepository();
        foreach ($lineas as &$l) {
            $l['precios_lista'] = $repoProd->getPrecios((int) $l['id_producto'], $idEmpresa);
            // IVA actual del producto (misma fuente que al facturar) para que el ítem muestre
            // el porcentaje que realmente se aplicará.
            $tar = $this->repository->getTarifaIvaProducto((int) $l['id_producto']);
            if ($tar) {
                $l['porcentaje_impuesto'] = (float) $tar['porcentaje_iva'];
            }
        }
        unset($l);
        return $lineas;
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) return null;
        $cab['detalles'] = $this->repository->getDetalles($id, $idEmpresa);
        $cab['info_adicional'] = $this->decodeInfoAdicional($cab['info_adicional'] ?? null);
        $cab['pagos_sri'] = $this->decodePagos($cab['pagos_sri'] ?? null);
        return $cab;
    }

    /** Normaliza los pagos SRI [{forma_pago, valor}] (solo filas con forma). */
    private function limpiarPagos($pagos): array
    {
        if (is_string($pagos)) { $pagos = json_decode($pagos, true); }
        if (!is_array($pagos)) return [];
        $out = [];
        foreach ($pagos as $p) {
            $forma = trim((string) ($p['forma_pago'] ?? ''));
            if ($forma === '') continue;
            $out[] = ['forma_pago' => $forma, 'valor' => round((float) ($p['valor'] ?? 0), 2)];
        }
        return $out;
    }

    private function decodePagos($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $dec = json_decode($raw, true);
        return is_array($dec) ? $dec : [];
    }

    private function normalizarPlazoUnidad($u): string
    {
        $u = (string) $u;
        return in_array($u, ['dias', 'meses', 'anios'], true) ? $u : 'dias';
    }

    /** Normaliza info adicional [{nombre, valor}] a JSON para persistir. */
    private function encodeInfoAdicional($info): ?string
    {
        if (!is_array($info)) return null;
        $limpio = [];
        foreach ($info as $ia) {
            $nombre = trim((string) ($ia['nombre'] ?? $ia['concepto'] ?? ''));
            $valor  = trim((string) ($ia['valor'] ?? $ia['detalle'] ?? ''));
            if ($nombre !== '' && $valor !== '') {
                $limpio[] = ['nombre' => $nombre, 'valor' => $valor];
            }
        }
        return $limpio ? json_encode($limpio) : null;
    }

    /** Decodifica el JSON de info adicional a array [{nombre, valor}]. */
    private function decodeInfoAdicional($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $dec = json_decode($raw, true);
        return is_array($dec) ? $dec : [];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    /** Asiento de reingreso sugerido (Debe Inventario / Haber Mercadería en Consignación, a costo). */
    public function obtenerAsientoReingresoSugerido(int $idEmpresa, int $idDoc): array
    {
        return (new AsientoBuilderService())->generarAsientoReingresoFacturacion($idEmpresa, $idDoc);
    }

    /** Cantidad facturada por línea de consignación (para el modal de consignación). */
    public function getFacturadoPorLinea(int $idConsignacion, int $idEmpresa): array
    {
        return $this->repository->getFacturadoPorConsignacion($idConsignacion, $idEmpresa);
    }

    public function getFacturasDeConsignacion(int $idEmpresa, int $idConsignacion): array
    {
        return $this->repository->getFacturasDeConsignacion($idConsignacion, $idEmpresa);
    }

    // ─── Paso 1: guardar el documento (borrador) ──────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validarDocumento($data);
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            [$detalles, $tot] = $this->normalizarDetalles($data['detalles'], $idEmpresa, null);

            $idDoc = $this->repository->create([
                'id_empresa'       => $idEmpresa,
                'fecha_emision'    => $data['fecha_emision'],
                'serie'            => $data['serie'] ?? '',
                'secuencial'       => str_pad((string) ($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT),
                'id_punto_emision' => (int) $data['id_punto_emision'],
                'establecimiento'  => $data['establecimiento'] ?? null,
                'punto_emision'    => $data['punto_emision'] ?? null,
                'tipo_ambiente'    => (string) ($data['empresa_config']['tipo_ambiente'] ?? '1'),
                'id_cliente'       => (int) $data['id_cliente'],
                'id_vendedor'      => empty($data['id_vendedor']) ? null : (int) $data['id_vendedor'],
                'dias_credito'     => (int) ($data['dias_credito'] ?? 0),
                'plazo_unidad'     => $this->normalizarPlazoUnidad($data['plazo_unidad'] ?? 'dias'),
                'forma_pago_sri'   => ($p = $this->limpiarPagos($data['pagos_sri'] ?? [])) ? ($p[0]['forma_pago'] ?? null) : (!empty($data['forma_pago_sri']) ? (string) $data['forma_pago_sri'] : null),
                'pagos_sri'        => $p ? json_encode($p) : null,
                'observaciones'    => $data['observaciones'] ?? null,
                'info_adicional'   => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'estado'           => 'borrador',
                'subtotal'         => $tot['subtotal'],
                'impuesto'         => $tot['impuesto'],
                'total'            => $tot['total'],
                'created_by'       => $idUsuario,
                'updated_by'       => $idUsuario,
            ]);

            foreach ($detalles as $d) {
                $d['id_consignacion_factura'] = $idDoc;
                $d['id_empresa'] = $idEmpresa;
                $this->repository->insertDetalle($d);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_FACTURACION_CV', 'consignaciones_facturas', $idDoc, null, ['total' => $tot['total']]);
            $db->commit();
            return $idDoc;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarDocumento($data);
        $idUsuario = (int) $data['id_usuario'];

        $doc = $this->repository->find($id, $idEmpresa);
        if (!$doc) throw new Exception('Documento no encontrado.');
        if (($doc['estado'] ?? '') !== 'borrador') {
            throw new Exception('Solo se pueden editar documentos en estado Borrador.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            [$detalles, $tot] = $this->normalizarDetalles($data['detalles'], $idEmpresa, null);

            $this->repository->deleteDetalles($id, $idEmpresa);
            foreach ($detalles as $d) {
                $d['id_consignacion_factura'] = $id;
                $d['id_empresa'] = $idEmpresa;
                $this->repository->insertDetalle($d);
            }

            $this->repository->update($id, $idEmpresa, [
                'fecha_emision' => $data['fecha_emision'],
                'id_cliente'    => (int) $data['id_cliente'],
                'id_vendedor'   => empty($data['id_vendedor']) ? null : (int) $data['id_vendedor'],
                'dias_credito'  => (int) ($data['dias_credito'] ?? 0),
                'plazo_unidad'  => $this->normalizarPlazoUnidad($data['plazo_unidad'] ?? 'dias'),
                'forma_pago_sri'=> ($p = $this->limpiarPagos($data['pagos_sri'] ?? [])) ? ($p[0]['forma_pago'] ?? null) : (!empty($data['forma_pago_sri']) ? (string) $data['forma_pago_sri'] : null),
                'pagos_sri'     => $p ? json_encode($p) : null,
                'observaciones' => $data['observaciones'] ?? null,
                'info_adicional'=> $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'subtotal'      => $tot['subtotal'],
                'impuesto'      => $tot['impuesto'],
                'total'         => $tot['total'],
                'updated_by'    => $idUsuario,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_FACTURACION_CV', 'consignaciones_facturas', $id, $doc, ['total' => $tot['total']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $doc = $this->repository->find($id, $idEmpresa);
        if (!$doc) throw new Exception('Documento no encontrado.');
        if (($doc['estado'] ?? '') === 'facturada') {
            throw new Exception('No se puede eliminar: el documento ya tiene una factura. Anule primero la factura.');
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_FACTURACION_CV', 'consignaciones_facturas', $id, $doc, null);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Crea un nuevo documento en 'borrador' copiando la cabecera y las líneas de un
     * documento existente (típicamente uno 'anulada'), re-validando el saldo actual de
     * cada línea: la cantidad se capea al saldo facturable vigente (y el descuento se
     * escala proporcionalmente); las líneas sin saldo se omiten. Devuelve el id del
     * nuevo borrador para abrirlo y, si se desea, generar una nueva factura.
     */
    public function duplicar(int $idDoc, int $idEmpresa, int $idUsuario, array $empresaConfig): int
    {
        $src = $this->repository->find($idDoc, $idEmpresa);
        if (!$src) throw new Exception('Documento no encontrado.');

        $idPunto = (int) ($src['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            throw new Exception('El documento de origen no tiene punto de emisión; no se puede duplicar.');
        }

        // Nuevo secuencial propio para el punto de emisión.
        $repoSec = new \App\repositories\SecuencialRepository();
        $cfgSec  = $repoSec->getConfigSecuencial($idPunto, 'Facturacion consignaciones ventas');
        if (empty($cfgSec['id'])) {
            throw new Exception('No hay secuencial configurado para "Facturacion consignaciones ventas" en el punto de emisión del documento.');
        }
        $sec = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Facturacion consignaciones ventas');

        // Copiar líneas capando cada cantidad al saldo facturable actual.
        $detsSrc  = $this->repository->getDetalles($idDoc, $idEmpresa);
        $detalles = [];
        foreach ($detsSrc as $d) {
            $idConsDet = (int) ($d['id_consignacion_detalle'] ?? 0);
            $srcCant   = (float) ($d['cantidad'] ?? 0);
            if ($idConsDet <= 0 || $srcCant <= 0) continue;

            $saldo = (float) $this->repository->getSaldoFacturableLinea($idConsDet, $idEmpresa, null);
            if ($saldo <= 1e-9) continue;

            $cant = min($srcCant, $saldo);
            $desc = max(0.0, (float) ($d['descuento'] ?? 0));
            if ($cant < $srcCant) $desc = round($desc * ($cant / $srcCant), 2); // escala el descuento al capear

            $detalles[] = [
                'id_consignacion_detalle' => $idConsDet,
                'cantidad'                => $cant,
                'precio_unitario'         => (float) ($d['precio_unitario'] ?? 0),
                'descuento'               => $desc,
            ];
        }
        if (empty($detalles)) {
            throw new Exception('Las consignaciones del documento ya no tienen saldo facturable; no hay nada que duplicar.');
        }

        $data = [
            'id_empresa'       => $idEmpresa,
            'id_usuario'       => $idUsuario,
            'empresa_config'   => $empresaConfig,
            'fecha_emision'    => date('Y-m-d'),
            'serie'            => $src['serie'] ?? '',
            'secuencial'       => $sec['secuencial'],
            'id_punto_emision' => $idPunto,
            'establecimiento'  => $src['establecimiento'] ?? null,
            'punto_emision'    => $src['punto_emision'] ?? null,
            'id_cliente'       => (int) ($src['id_cliente'] ?? 0),
            'id_vendedor'      => $src['id_vendedor'] ?? null,
            'dias_credito'     => (int) ($src['dias_credito'] ?? 0),
            'plazo_unidad'     => $src['plazo_unidad'] ?? 'dias',
            'pagos_sri'        => $this->decodePagos($src['pagos_sri'] ?? null),
            'observaciones'    => $src['observaciones'] ?? null,
            'info_adicional'   => $this->decodeInfoAdicional($src['info_adicional'] ?? null),
            'detalles'         => $detalles,
        ];

        $newId = $this->crear($data);
        $this->logService->registrar($idUsuario, $idEmpresa, 'DUPLICAR_FACTURACION_CV', 'consignaciones_facturas', $newId, ['origen' => $idDoc], null);
        return $newId;
    }

    /**
     * Normaliza las líneas del documento leyendo la consignación de origen (autoritativo),
     * valida saldo y calcula impuestos. Devuelve [detalles, totales].
     */
    private function normalizarDetalles(array $lineas, int $idEmpresa, ?int $excluirDoc): array
    {
        $detalles = [];
        $subtotalTot = 0.0; $ivaTot = 0.0; $totalTot = 0.0;

        foreach ($lineas as $ln) {
            $cant = (float) ($ln['cantidad'] ?? 0);
            $idConsDet = (int) ($ln['id_consignacion_detalle'] ?? 0);
            if ($cant <= 0 || $idConsDet <= 0) continue;

            $cd = $this->repository->getConsignacionDetalle($idConsDet, $idEmpresa);
            if (!$cd) {
                throw new Exception("La línea de consignación #{$idConsDet} no existe o no pertenece a la empresa.");
            }
            if (($cd['consignacion_estado'] ?? '') !== 'Entregada') {
                throw new Exception("La consignación de la línea #{$idConsDet} no está Entregada.");
            }

            $saldo = $this->repository->getSaldoFacturableLinea($idConsDet, $idEmpresa, $excluirDoc);
            if ($cant > $saldo + 1e-9) {
                $nombre = $cd['producto_nombre'] ?? 'Producto';
                throw new Exception("No puede facturar {$cant} de \"{$nombre}\": el saldo facturable es {$saldo}.");
            }

            $idProducto = (int) $cd['id_producto'];
            // Precio y descuento elegidos en el modal (con fallback al precio de la consignación).
            $precio   = isset($ln['precio_unitario']) ? (float) $ln['precio_unitario'] : (float) $cd['precio_unitario'];
            $descuento = max(0.0, (float) ($ln['descuento'] ?? 0));
            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }
            $bruto = round($precio * $cant, 2);
            if ($descuento > $bruto + 1e-9) {
                $nombre = $cd['producto_nombre'] ?? 'Producto';
                throw new Exception("El descuento de \"{$nombre}\" no puede superar el subtotal ({$bruto}).");
            }
            $base = round($bruto - $descuento, 2);

            $tar = $idProducto > 0 ? $this->repository->getTarifaIvaProducto($idProducto) : null;
            if (!$tar && !empty($cd['id_impuesto'])) {
                $tar = $this->repository->getTarifaIvaById((int) $cd['id_impuesto']);
            }
            $pct   = $tar ? (float) $tar['porcentaje_iva'] : (float) ($cd['porcentaje_impuesto'] ?? 0);
            $idTar = $tar ? (int) $tar['id'] : (int) ($cd['id_impuesto'] ?? 0);
            $iva   = round($base * $pct / 100, 2);

            $lote = (isset($cd['lote']) && $cd['lote'] !== '') ? (string) $cd['lote'] : 'sin_lote';

            $detalles[] = [
                'id_consignacion'         => (int) $cd['id_consignacion'],
                'id_consignacion_detalle' => $idConsDet,
                'id_producto'             => $idProducto,
                'cantidad'                => $cant,
                'precio_unitario'         => $precio,
                'descuento'               => $descuento,
                'id_impuesto'             => $idTar ?: null,
                'porcentaje_impuesto'     => $pct,
                'valor_impuesto'          => $iva,
                'subtotal'                => $base,
                'total'                   => round($base + $iva, 2),
                'id_bodega'               => (int) ($cd['id_bodega'] ?? 0),
                'lote'                    => $lote,
                'nup'                     => (isset($cd['nup']) && $cd['nup'] !== '') ? $cd['nup'] : null,
                'fecha_caducidad'         => (isset($cd['fecha_caducidad']) && $cd['fecha_caducidad'] !== '') ? $cd['fecha_caducidad'] : null,
            ];

            $subtotalTot += $base;
            $ivaTot      += $iva;
            $totalTot    += $base + $iva;
        }

        if (empty($detalles)) {
            throw new Exception('No hay cantidades válidas para facturar.');
        }

        return [$detalles, [
            'subtotal' => round($subtotalTot, 2),
            'impuesto' => round($ivaTot, 2),
            'total'    => round($totalTot, 2),
        ]];
    }

    // ─── Paso 2: generar la factura de venta ──────────────────────────────────

    /** @return array{id_factura:int, numero_factura:string} */
    public function generarFactura(int $idDoc, int $idEmpresa, int $idUsuario, array $empresaConfig): array
    {
        $doc = $this->repository->find($idDoc, $idEmpresa);
        if (!$doc) throw new Exception('Documento no encontrado.');
        if (($doc['estado'] ?? '') === 'facturada') throw new Exception('El documento ya fue facturado.');
        if (($doc['estado'] ?? '') === 'anulada')   throw new Exception('El documento está anulado.');

        $detalles = $this->repository->getDetalles($idDoc, $idEmpresa);
        if (empty($detalles)) throw new Exception('El documento no tiene líneas.');

        $idPunto = (int) ($doc['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) throw new Exception('El documento no tiene punto de emisión.');

        // 1. Revalidar saldo y armar factura + reingreso.
        $detFactura = [];
        $idBodegaTop = 0;
        $totalSinImp = 0.0; $ivaTotal = 0.0; $descTotal = 0.0;
        foreach ($detalles as $d) {
            $cant = (float) $d['cantidad'];
            $idConsDet = (int) $d['id_consignacion_detalle'];
            $saldo = $this->repository->getSaldoFacturableLinea($idConsDet, $idEmpresa, $idDoc);
            if ($cant > $saldo + 1e-9) {
                throw new Exception("Saldo insuficiente en \"" . ($d['producto_nombre'] ?? 'Producto') . "\": disponible {$saldo}, requerido {$cant}. Edite el documento.");
            }
            $idBodega = (int) ($d['id_bodega'] ?? 0);
            if ($idBodegaTop === 0 && $idBodega > 0) $idBodegaTop = $idBodega;

            $base = round((float) $d['subtotal'], 2);
            $pct  = (float) ($d['porcentaje_impuesto'] ?? 0);
            $iva  = round((float) $d['valor_impuesto'], 2);
            $totalSinImp += $base;
            $ivaTotal    += $iva;
            $descTotal   += (float) ($d['descuento'] ?? 0);

            $codPct = '0';
            if ($pct > 0) { $codPct = (abs($pct - 12) < 0.01) ? '2' : ((abs($pct - 15) < 0.01) ? '4' : '3'); }

            $detFactura[] = [
                'id_producto'               => (int) $d['id_producto'],
                'id_bodega'                 => $idBodega ?: null,
                'descripcion'               => $d['producto_nombre'] ?? '',
                'nombre'                    => $d['producto_nombre'] ?? '',
                'codigo_principal'          => $d['producto_codigo'] ?? null,
                'cantidad'                  => $cant,
                'precio_unitario'           => (float) $d['precio_unitario'],
                'descuento'                 => (float) ($d['descuento'] ?? 0),
                'precio_total_sin_impuesto' => $base,
                'id_tarifa_iva'             => (int) ($d['id_impuesto'] ?? 0),
                'lote'                      => (isset($d['lote']) && $d['lote'] !== '') ? $d['lote'] : 'sin_lote',
                'caducidad'                 => $d['fecha_caducidad'] ?? null,
                'nup'                       => $d['nup'] ?? null,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa'            => $pct,
                    'base_imponible'    => $base,
                    'valor'             => $iva,
                ]],
            ];
        }
        $totalSinImp  = round($totalSinImp, 2);
        $ivaTotal     = round($ivaTotal, 2);
        $importeTotal = round($totalSinImp + $ivaTotal, 2);
        $idEstablecimiento = $this->repository->getEstablecimientoPorPunto($idPunto) ?? 0;
        $numDoc = ($doc['serie'] ?? '') . '-' . ($doc['secuencial'] ?? '');

        // 2. Reingreso de inventario a la bodega de origen (transacción propia).
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            foreach ($detalles as $d) {
                $this->reingresarLinea($d, $idEmpresa, $idUsuario, $empresaConfig, $idDoc, $numDoc);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // 2.1 Asiento inverso del reingreso (no fatal).
        $this->procesarAsientoReingresoSeguro($idDoc, $idEmpresa, $idUsuario, $numDoc, $doc['cliente_nombre'] ?? 'Cliente');

        // 3. Crear la factura de venta NORMAL, facturada al cliente del documento.
        $sec = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Facturas de venta');
        $secuencial = $sec['formateado'];
        $numFactura = ($doc['establecimiento'] ?? '') . '-' . ($doc['punto_emision'] ?? '') . '-' . $secuencial;

        // Pagos SRI del documento (una o varias formas). Si hay una sola forma, le toca el total;
        // si hay varias, se respetan los valores; si no hay ninguna, un pago único con la forma o '01'.
        $dias   = (int) ($doc['dias_credito'] ?? 0);
        $unidad = $this->normalizarPlazoUnidad($doc['plazo_unidad'] ?? 'dias');
        $pagosDoc = $this->limpiarPagos($doc['pagos_sri'] ?? []);
        $pagosFactura = [];
        if (count($pagosDoc) === 1) {
            $pagosFactura[] = ['forma_pago' => $pagosDoc[0]['forma_pago'], 'total' => $importeTotal, 'plazo' => $dias, 'unidad_tiempo' => $unidad];
        } elseif (count($pagosDoc) > 1) {
            foreach ($pagosDoc as $pp) {
                $pagosFactura[] = ['forma_pago' => $pp['forma_pago'], 'total' => round((float) $pp['valor'], 2), 'plazo' => $dias, 'unidad_tiempo' => $unidad];
            }
        } else {
            $pagosFactura[] = ['forma_pago' => !empty($doc['forma_pago_sri']) ? (string) $doc['forma_pago_sri'] : '01', 'total' => $importeTotal, 'plazo' => $dias, 'unidad_tiempo' => $unidad];
        }

        // Info adicional de la factura = la del documento + una línea con el/los número(s)
        // de consignación usados (concepto "Consignación").
        $infoFactura = $this->decodeInfoAdicional($doc['info_adicional'] ?? null);
        $consigNums = [];
        foreach ($detalles as $d) {
            $numC = trim(((string) ($d['consignacion_serie'] ?? '')) . '-' . ((string) ($d['consignacion_secuencial'] ?? '')), '-');
            if ($numC !== '' && !in_array($numC, $consigNums, true)) $consigNums[] = $numC;
        }
        if ($consigNums) {
            $infoFactura[] = ['nombre' => 'Consignación', 'valor' => implode(', ', $consigNums)];
        }

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaConfig,
            'id_establecimiento'  => $idEstablecimiento,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => $doc['establecimiento'] ?? '',
            'punto_emision'       => $doc['punto_emision'] ?? '',
            'secuencial'          => $secuencial,
            'fecha_emision'       => $doc['fecha_emision'] ?? date('Y-m-d'),
            'id_cliente'          => (int) $doc['id_cliente'],
            'id_vendedor'         => !empty($doc['id_vendedor']) ? (int) $doc['id_vendedor'] : null,
            'dias_credito'        => $dias,
            'plazo'               => $dias,
            'moneda'              => 'DOLAR',
            'observaciones'       => trim((string) ($doc['observaciones'] ?? '')),
            'id_bodega'           => $idBodegaTop,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => round($descTotal, 2),
            'total_ice'           => 0,
            'propina'             => 0,
            'importe_total'       => $importeTotal,
            'detalles'            => $detFactura,
            'pagos'               => $pagosFactura,
            'info_adicional'      => $infoFactura,
            // El stock ya fue reingresado a la bodega en el paso anterior; la validación
            // "solo stock positivo" de la factura es redundante (y bloquearía si el saldo
            // global quedó negativo por la consignación).
            'omitir_validacion_stock' => true,
        ];

        $facturaService = new FacturaVentaService(
            new FacturaVentaRepository(),
            new FacturaVentaRules(),
            $this->logService
        );

        try {
            $idFactura = $facturaService->crear($payload);
        } catch (\Throwable $e) {
            $this->revertirReingreso($idDoc, $idEmpresa, $idUsuario, false); // deshacer reingreso, no cambiar estado
            throw new Exception('No se pudo generar la factura: ' . $e->getMessage());
        }

        // 4. Enlazar y marcar facturada.
        try {
            $this->repository->linkFactura($idDoc, $idEmpresa, $idFactura, $numFactura, $idUsuario);
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'GENERAR_FACTURA_CV', 'consignaciones_facturas', $idDoc,
                null, ['id_factura' => $idFactura, 'numero_factura' => $numFactura]
            );
        } catch (\Throwable $e) {
            error_log("[FacturacionCV] Factura #$numFactura creada pero no se enlazó al documento $idDoc: " . $e->getMessage());
            throw new Exception("La factura {$numFactura} se generó, pero no se enlazó al documento. Revise manualmente. Detalle: " . $e->getMessage());
        }

        return ['id_factura' => $idFactura, 'numero_factura' => $numFactura];
    }

    // ─── Reversión automática (al anular/eliminar la factura de origen) ────────

    public function reversarPorFactura(int $idFactura, int $idEmpresa, int $idUsuario): void
    {
        try {
            $doc = $this->repository->getDocPorFactura($idFactura, $idEmpresa);
        } catch (\Throwable $e) {
            error_log('[FacturacionCV] No se pudo verificar documento de la factura #' . $idFactura . ': ' . $e->getMessage());
            return;
        }
        if (!$doc) return;

        $idDoc = (int) $doc['id'];
        $this->revertirReingreso($idDoc, $idEmpresa, $idUsuario, true); // deshacer + estado 'anulada'

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'REVERSAR_FACTURACION_CV', 'consignaciones_facturas', $idDoc,
            $doc, ['id_factura' => $idFactura, 'estado' => 'anulada']
        );
    }

    // ─── Helpers internos ─────────────────────────────────────────────────────

    private function reingresarLinea(array $d, int $idEmpresa, int $idUsuario, array $empresaConfig, int $idDoc, string $numDoc): void
    {
        // La consignación descuenta el stock de forma INCONDICIONAL (registra la salida
        // para toda línea). Por eso el reingreso debe hacerse igual, sin gatear por
        // facturacion_inventario; si no, la factura no encuentra el stock reingresado.
        $cant = (float) $d['cantidad'];
        $idBodega = (int) ($d['id_bodega'] ?? 0);
        if ($cant <= 0 || $idBodega <= 0) return;

        $idProducto  = (int) $d['id_producto'];
        $stockActual = $this->inventarioRepo->getStockActual($idProducto, $idBodega, $idEmpresa);
        $nuevoStock  = $stockActual + $cant;
        $costoUnit   = $this->repository->getCostoUnitarioConsignacion((int) $d['id_consignacion'], $idProducto);
        $costoTotal  = round($costoUnit * $cant, 2);

        $this->inventarioRepo->registrarMovimiento([
            'id_empresa'      => $idEmpresa,
            'id_producto'     => $idProducto,
            'id_bodega'       => $idBodega,
            'tipo_movimiento' => 'entrada',
            'referencia_tipo' => self::REF_TIPO,
            'referencia_id'   => $idDoc,
            'cantidad'        => $cant,
            'costo_unitario'  => $costoUnit,
            'costo_total'     => $costoTotal,
            'stock_anterior'  => $stockActual,
            'stock_posterior' => $nuevoStock,
            'numero_lote'     => (isset($d['lote']) && $d['lote'] !== '') ? $d['lote'] : null,
            'fecha_caducidad' => (isset($d['fecha_caducidad']) && $d['fecha_caducidad'] !== '') ? $d['fecha_caducidad'] : null,
            'nup'             => (isset($d['nup']) && $d['nup'] !== '') ? $d['nup'] : null,
            'observaciones'   => 'Reingreso por facturación de consignación ' . $numDoc,
            'id_usuario'      => $idUsuario,
        ]);
        $this->inventarioRepo->actualizarStock($idProducto, $idBodega, $idEmpresa, $nuevoStock, $idUsuario);
    }

    /**
     * Deshace el reingreso (revierte el kardex), anula el asiento inverso y, si
     * $marcarAnulada, deja el documento 'anulada'. Participa en la transacción activa.
     */
    private function revertirReingreso(int $idDoc, int $idEmpresa, int $idUsuario, bool $marcarAnulada): void
    {
        $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $idDoc, $idEmpresa, $idUsuario, true);

        // Anular el asiento de reingreso. Se busca por la columna del documento y, como
        // respaldo, por origen ('FACTURACION_CV' + id del documento) por si la columna no
        // quedó registrada; así el asiento SIEMPRE se anula al revertir.
        $asientoSvc = $this->getAsientoService();
        $doc = $this->repository->find($idDoc, $idEmpresa);
        $idAsiento = (int) ($doc['id_asiento_reingreso'] ?? 0);
        if ($idAsiento <= 0) {
            try {
                $prev = $asientoSvc->getAsientoPorOrigen(self::REF_TIPO, $idDoc, $idEmpresa);
                $idAsiento = $prev ? (int) $prev['id'] : 0;
            } catch (\Throwable $e) { $idAsiento = 0; }
        }
        if ($idAsiento > 0) {
            try {
                $asientoSvc->anular($idAsiento, $idEmpresa, $idUsuario);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'ya se encuentra anulado') === false) {
                    error_log("[FacturacionCV] No se pudo anular el asiento de reingreso $idAsiento: " . $e->getMessage());
                }
            }
            $this->repository->updateAsientoReingreso($idDoc, $idEmpresa, null);
        }

        if ($marcarAnulada) {
            $this->repository->updateEstado($idDoc, $idEmpresa, 'anulada', $idUsuario);
        }
    }

    private function procesarAsientoReingresoSeguro(int $idDoc, int $idEmpresa, int $idUsuario, string $numDoc, string $clienteNombre): void
    {
        try {
            $builder = new AsientoBuilderService();
            $fuente  = $builder->generarAsientoReingresoFacturacion($idEmpresa, $idDoc);

            $detalles = [];
            $totDebe = 0.0; $totHaber = 0.0;
            foreach ($fuente as $d) {
                $idCuenta = (int) ($d['id_cuenta_contable'] ?? 0);
                if ($idCuenta <= 0) return;
                $debe  = round((float) ($d['debe'] ?? 0), 2);
                $haber = round((float) ($d['haber'] ?? 0), 2);
                $totDebe += $debe; $totHaber += $haber;
                $detalles[] = [
                    'id_cuenta_contable'   => $idCuenta,
                    'debe'                 => $debe,
                    'haber'                => $haber,
                    'referencia_detalle'   => ($d['referencia_detalle'] ?? '') ?: ('Reingreso ' . $numDoc),
                    'documento_referencia' => 'Facturación consignación ' . $numDoc,
                ];
            }
            if (empty($detalles) || abs($totDebe - $totHaber) >= 0.005 || ($totDebe <= 0 && $totHaber <= 0)) return;

            $idAsiento = $this->getAsientoService()->guardarAsiento([
                'id'                   => null,
                'fecha_asiento'        => date('Y-m-d'),
                'tipo_comprobante'     => 'consignacion',
                'numero_comprobante'   => '',
                'concepto'             => 'Reingreso por facturación de consignación ' . $numDoc . ' - Cliente: ' . $clienteNombre,
                'estado'               => 'contabilizado',
                'modulo_origen'        => self::REF_TIPO,
                'id_referencia_origen' => $idDoc,
                'observaciones'        => null,
            ], $detalles, $idEmpresa, $idUsuario);
            $this->repository->updateAsientoReingreso($idDoc, $idEmpresa, $idAsiento);
        } catch (\Throwable $e) {
            error_log("[FacturacionCV] Asiento de reingreso no generado para documento $idDoc: " . $e->getMessage());
        }
    }

    private function getAsientoService(): AsientoContableService
    {
        return new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );
    }

    private function afectaInventario(array $linea, array $empresaConfig): bool
    {
        $esInv = ($linea['inventariable'] == true || $linea['inventariable'] === 'true' || $linea['inventariable'] == 1 || $linea['inventariable'] === 't')
                 && (($linea['tipo_produccion'] ?? '01') !== '02');
        $soloStockPos = (($empresaConfig['facturacion_inventario'] ?? true) === 'true' || ($empresaConfig['facturacion_inventario'] ?? true) === true);
        return $esInv && $soloStockPos;
    }
}
