<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\OrdenCarWashRepository;
use App\Rules\modulos\OrdenCarWashRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;

/**
 * Lógica de negocio del módulo Servicio Car-Wash.
 *
 * Una orden registra el ingreso de un vehículo, sus servicios/productos, las
 * novedades encontradas y la próxima cita. La orden NO mueve inventario ni genera
 * asiento contable por sí misma: eso ocurre al emitir el documento de venta
 * (Factura o Recibo) desde generarDocumento() — ver Fase 2.
 */
class OrdenCarWashService
{
    private OrdenCarWashRepository $repository;
    private OrdenCarWashRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        OrdenCarWashRepository $repository,
        OrdenCarWashRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ─── Lecturas ─────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getTablero(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getTablero($idEmpresa, $idUsuarioFiltro);
    }

    public function buscarVehiculos(int $idEmpresa, string $q): array
    {
        return $this->repository->buscarVehiculos($idEmpresa, $q);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) return null;
        $cab['detalles']  = $this->repository->getDetalles($id, $idEmpresa);
        $cab['novedades'] = $this->repository->getNovedades($id, $idEmpresa);
        $cab['info_adicional'] = $this->decodeInfoAdicional($cab['info_adicional'] ?? null);
        return $cab;
    }

    // ─── Crear ────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validarCreacion($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $empresaConfig = $data['empresa_config'] ?? [];
        $tipoAmbiente  = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $idEstab       = (int) ($data['id_establecimiento'] ?? 0);
        $idPunto       = (int) ($data['id_punto_emision'] ?? 0);
        $secuencial    = str_pad((string) $data['secuencial'], 9, '0', STR_PAD_LEFT);

        if ($this->repository->existeSecuencial($idEmpresa, $idEstab, $idPunto, $secuencial)) {
            throw new \Exception('El secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }

        $numeroOrden = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . $secuencial;

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $cabecera = [
                'id_empresa'        => $idEmpresa,
                'id_establecimiento'=> $idEstab ?: null,
                'id_punto_emision'  => $idPunto ?: null,
                'establecimiento'   => $data['establecimiento'] ?? null,
                'punto_emision'     => $data['punto_emision'] ?? null,
                'secuencial'        => $secuencial,
                'tipo_ambiente'     => $tipoAmbiente,
                'numero_orden'      => $numeroOrden,
                'id_vehiculo'       => (int) $data['id_vehiculo'],
                'id_cliente'        => empty($data['id_cliente']) ? null : (int) $data['id_cliente'],
                'id_bodega'         => empty($data['id_bodega']) ? null : (int) $data['id_bodega'],
                'placa'             => $data['placa'] ?? null,
                'marca'             => $data['marca'] ?? null,
                'modelo'            => $data['modelo'] ?? null,
                'kilometraje'       => ($data['kilometraje'] ?? '') === '' ? null : (int) $data['kilometraje'],
                'nivel_combustible' => $data['nivel_combustible'] ?? null,
                'fecha_ingreso'     => $data['fecha_ingreso'],
                'novedades_texto'   => $data['novedades_texto'] ?? null,
                'observaciones'     => $data['observaciones'] ?? null,
                'info_adicional'    => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'proxima_cita'      => empty($data['proxima_cita']) ? null : $data['proxima_cita'],
                'estado'            => 'borrador',
                'subtotal'          => 0,
                'descuento'         => 0,
                'iva'               => 0,
                'total'             => 0,
                'created_by'        => $idUsuario,
                'updated_by'        => $idUsuario,
            ];
            $idOrden = $this->repository->create($cabecera);

            $tot = $this->guardarLineas($idOrden, $idEmpresa, $data);

            $this->repository->updateTotales($idOrden, $idEmpresa, $tot['subtotal'], $tot['descuento'], $tot['iva'], $tot['total']);
            $cabecera = array_merge($cabecera, $tot);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_ORDEN_CARWASH', 'carwash_ordenes', $idOrden, null, $cabecera);

            $db->commit();
            return $idOrden;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Actualizar ───────────────────────────────────────────────────────────

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarCreacion($data);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        if (!empty($cab['id_documento'])) {
            throw new Exception("No se puede editar una orden que ya generó un documento.");
        }
        if (($cab['estado'] ?? '') === 'anulado') {
            throw new Exception("No se puede editar una orden anulada.");
        }

        $idUsuario = (int) $data['id_usuario'];
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->limpiarLineas($id, $idEmpresa);
            $tot = $this->guardarLineas($id, $idEmpresa, $data);

            $this->repository->updateCabecera($id, $idEmpresa, [
                'id_vehiculo'       => (int) $data['id_vehiculo'],
                'id_cliente'        => empty($data['id_cliente']) ? null : (int) $data['id_cliente'],
                'id_bodega'         => empty($data['id_bodega']) ? null : (int) $data['id_bodega'],
                'placa'             => $data['placa'] ?? null,
                'marca'             => $data['marca'] ?? null,
                'modelo'            => $data['modelo'] ?? null,
                'kilometraje'       => ($data['kilometraje'] ?? '') === '' ? null : (int) $data['kilometraje'],
                'nivel_combustible' => $data['nivel_combustible'] ?? null,
                'fecha_ingreso'     => $data['fecha_ingreso'],
                'novedades_texto'   => $data['novedades_texto'] ?? null,
                'observaciones'     => $data['observaciones'] ?? null,
                'info_adicional'    => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'proxima_cita'      => empty($data['proxima_cita']) ? null : $data['proxima_cita'],
                'subtotal'          => $tot['subtotal'],
                'descuento'         => $tot['descuento'],
                'iva'               => $tot['iva'],
                'total'             => $tot['total'],
                'updated_by'        => $idUsuario,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_ORDEN_CARWASH', 'carwash_ordenes', $id, $cab, $data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Cambio de estado ─────────────────────────────────────────────────────

    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado): void
    {
        $this->rules->validarEstado($nuevoEstado);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        $actual = (string) ($cab['estado'] ?? '');
        if ($actual === $nuevoEstado) {
            return;
        }
        if ($actual === 'facturado' || !empty($cab['id_documento'])) {
            throw new Exception("La orden ya fue facturada; su estado no puede cambiarse manualmente.");
        }
        if ($nuevoEstado === 'facturado') {
            throw new Exception("El estado 'facturado' se asigna automáticamente al generar el documento.");
        }

        $setEntrega = ($nuevoEstado === 'terminado' && empty($cab['fecha_entrega']));

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario, $setEntrega);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_ORDEN_CARWASH', 'carwash_ordenes', $id,
                ['estado' => $actual], ['estado' => $nuevoEstado]);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Eliminar ─────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        if (!empty($cab['id_documento'])) {
            throw new Exception("No se puede eliminar una orden que ya generó un documento. Anule primero el documento.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_ORDEN_CARWASH', 'carwash_ordenes', $id, $cab, null);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Generar documento de venta (Factura / Recibo) ───────────────────────

    /**
     * Genera un documento de venta (FACTURA o RECIBO) a partir de la orden,
     * reutilizando FacturaVentaService / ReciboVentaService. La orden solo arma el
     * payload; inventario, XML/SRI y asiento contable los maneja cada service.
     *
     * @return array{tipo:string,id_documento:int,numero_documento:string}
     */
    public function generarDocumento(int $idOrden, int $idEmpresa, int $idUsuario, string $tipo, array $extra, array $empresaConfig): array
    {
        $tipo  = strtoupper($tipo);
        $orden = $this->getDetalleCompleto($idOrden, $idEmpresa);
        if (!$orden) {
            throw new Exception('Orden no encontrada.');
        }
        $this->rules->validarGeneracionDocumento($orden, $tipo, $extra);

        $detalles = $orden['detalles'] ?? [];
        if (empty($detalles)) {
            throw new Exception('La orden no tiene servicios ni productos.');
        }

        $idPunto = (int) ($orden['id_punto_emision'] ?? 0);
        $idEstab = (int) ($orden['id_establecimiento'] ?? 0);
        if ($idPunto <= 0) {
            throw new Exception('La orden no tiene punto de emisión para numerar el documento.');
        }
        $estCod   = (string) ($orden['establecimiento'] ?? '');
        $puntoCod = (string) ($orden['punto_emision'] ?? '');
        $formaPago = (string) ($extra['forma_pago'] ?? '01');
        // La bodega de la orden (cabecera) manda; si no, la que venga en la emisión.
        $idBodegaExtra = (int) ($orden['id_bodega'] ?? 0) ?: (int) ($extra['id_bodega'] ?? 0);

        // Construir detalles del documento con impuestos por línea.
        $det = [];
        $totalSinImp = 0.0; $totalDesc = 0.0; $ivaTotal = 0.0; $idBodega = 0;
        foreach ($detalles as $d) {
            $cant = (float) $d['cantidad'];
            if ($cant <= 0) continue;
            $precio = (float) $d['precio_unitario'];
            $dscto  = (float) $d['descuento'];
            $base   = round($precio * $cant - $dscto, 2);
            if ($base < 0) $base = 0.0;

            // Resolver tarifa de IVA: producto → id_tarifa_iva → por porcentaje.
            $tar = null;
            if (!empty($d['id_producto'])) $tar = $this->repository->getTarifaIvaProducto((int) $d['id_producto']);
            if (!$tar && !empty($d['id_tarifa_iva'])) $tar = $this->repository->getTarifaIvaById((int) $d['id_tarifa_iva']);
            if (!$tar) $tar = $this->repository->getTarifaIvaByPorcentaje((float) ($d['porcentaje_iva'] ?? 0));

            $pct    = $tar ? (float) $tar['porcentaje_iva'] : (float) ($d['porcentaje_iva'] ?? 0);
            $codPct = $tar ? (string) $tar['codigo'] : '0';
            $idTar  = $tar ? (int) $tar['id'] : (!empty($d['id_tarifa_iva']) ? (int) $d['id_tarifa_iva'] : 0);
            $ivaLinea = round($base * $pct / 100, 2);

            $ivaTotal    += $ivaLinea;
            $totalSinImp += $base;
            $totalDesc   += $dscto;

            $bodegaLinea = (int) ($d['id_bodega'] ?? 0) ?: $idBodegaExtra;
            if ($idBodega === 0 && $bodegaLinea > 0) $idBodega = $bodegaLinea;

            $det[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'id_bodega'                 => $bodegaLinea ?: null,
                'descripcion'               => $d['descripcion'],
                'nombre'                    => $d['descripcion'],
                'cantidad'                  => $cant,
                'precio_unitario'           => $precio,
                'descuento'                 => $dscto,
                'precio_total_sin_impuesto' => $base,
                'id_tarifa_iva'             => $idTar,
                'es_libre'                  => empty($d['id_producto']) ? '1' : 0,
                'porcentaje_iva'            => $pct,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa'            => $pct,
                    'base_imponible'    => $base,
                    'valor'             => $ivaLinea,
                ]],
            ];
        }
        if (empty($det)) {
            throw new Exception('No hay líneas válidas para facturar.');
        }

        $totalSinImp  = round($totalSinImp, 2);
        $totalDesc    = round($totalDesc, 2);
        $ivaTotal     = round($ivaTotal, 2);
        $importeTotal = round($totalSinImp + $ivaTotal, 2);
        if ($idBodegaExtra > 0) $idBodega = $idBodegaExtra;

        // Secuencial propio del documento (Factura o Recibo) para el mismo punto.
        $tipoDocSec = ($tipo === 'FACTURA') ? 'Facturas de venta' : 'Recibos de venta';
        $sec = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, $tipoDocSec);
        $secuencial = $sec['formateado'];
        $numeroDoc  = $estCod . '-' . $puntoCod . '-' . $secuencial;

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaConfig,
            'id_establecimiento'  => $idEstab,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => $estCod,
            'punto_emision'       => $puntoCod,
            'secuencial'          => $secuencial,
            'fecha_emision'       => date('Y-m-d'),
            'id_cliente'          => (int) $orden['id_cliente'],
            'id_vendedor'         => null,
            'dias_credito'        => 0,
            'moneda'              => 'DOLAR',
            'observaciones'       => 'Generado desde orden car-wash ' . ($orden['numero_orden'] ?? ''),
            'id_bodega'           => $idBodega ?: null,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => $totalDesc,
            'total_ice'           => 0,
            'propina'             => 0,
            'importe_total'       => $importeTotal,
            'detalles'            => $det,
            'pagos'               => [[
                'forma_pago'    => $formaPago,
                'total'         => $importeTotal,
                'plazo'         => 0,
                'unidad_tiempo' => 'dias',
            ]],
            'info_adicional'      => is_array($orden['info_adicional'] ?? null) ? $orden['info_adicional'] : [],
        ];

        if ($tipo === 'FACTURA') {
            $svc = new FacturaVentaService(
                new \App\repositories\modulos\FacturaVentaRepository(),
                new \App\Rules\modulos\FacturaVentaRules(),
                $this->logService
            );
            $idDoc = $svc->crear($payload);
        } else {
            $payload['con_impuestos'] = true;
            $payload['estado']        = 'borrador';
            $payload['plazo']         = 0;
            $svc = new ReciboVentaService(
                new \App\repositories\modulos\ReciboVentaRepository(),
                new \App\Rules\modulos\ReciboVentaRules(),
                $this->logService
            );
            $idDoc = $svc->crear($payload);
        }

        // Marcar la orden como facturada con el documento generado.
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->repository->marcarDocumentoGenerado($idOrden, $idEmpresa, $tipo, (int) $idDoc, $numeroDoc, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_DOCUMENTO_CARWASH', 'carwash_ordenes', $idOrden,
                ['estado' => $orden['estado'] ?? ''], ['tipo_documento' => $tipo, 'id_documento' => $idDoc, 'numero_documento' => $numeroDoc]);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw new Exception("El documento {$numeroDoc} se generó, pero la orden no pudo marcarse como facturada: " . $e->getMessage());
        }

        return ['tipo' => $tipo, 'id_documento' => (int) $idDoc, 'numero_documento' => $numeroDoc];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Normaliza y serializa la info adicional [{nombre,valor}] a JSON para guardar. */
    private function encodeInfoAdicional($info): ?string
    {
        if (!is_array($info)) return null;
        $limpio = [];
        foreach ($info as $ia) {
            $nom = trim((string) ($ia['nombre'] ?? ''));
            $val = trim((string) ($ia['valor'] ?? ''));
            if ($nom !== '' && $val !== '') {
                $limpio[] = ['nombre' => $nom, 'valor' => $val];
            }
        }
        return empty($limpio) ? null : json_encode($limpio, JSON_UNESCAPED_UNICODE);
    }

    /** Decodifica la info adicional almacenada (JSON) a array [{nombre,valor}]. */
    private function decodeInfoAdicional($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * Inserta las líneas de detalle y novedades de una orden y devuelve los totales.
     * Los importes se calculan en el backend a partir de cantidad/precio/descuento/%IVA.
     */
    private function guardarLineas(int $idOrden, int $idEmpresa, array $data): array
    {
        $subtotal = 0.0; $descuento = 0.0; $iva = 0.0; $total = 0.0;

        foreach ($data['detalles'] as $det) {
            $cant = (float) ($det['cantidad'] ?? 0);
            $desc = trim((string) ($det['descripcion'] ?? ''));
            if ($cant <= 0 || $desc === '') continue;

            $precio  = (float) ($det['precio_unitario'] ?? 0);
            $dscto   = (float) ($det['descuento'] ?? 0);
            $porcIva = (float) ($det['porcentaje_iva'] ?? 0);

            $baseLinea = round($precio * $cant - $dscto, 2);
            if ($baseLinea < 0) $baseLinea = 0.0;
            $valorIva  = round($baseLinea * ($porcIva / 100), 2);
            $totalLin  = round($baseLinea + $valorIva, 2);

            $this->repository->insertDetalle([
                'id_orden'        => $idOrden,
                'id_empresa'      => $idEmpresa,
                'id_producto'     => empty($det['id_producto']) ? null : (int) $det['id_producto'],
                'tipo_linea'      => ($det['tipo_linea'] ?? 'servicio') === 'producto' ? 'producto' : 'servicio',
                'es_libre'        => !empty($det['es_libre']),
                'descripcion'     => $desc,
                'id_bodega'       => empty($det['id_bodega']) ? null : (int) $det['id_bodega'],
                'cantidad'        => $cant,
                'precio_unitario' => $precio,
                'descuento'       => $dscto,
                'porcentaje_iva'  => $porcIva,
                'valor_iva'       => $valorIva,
                'total_linea'     => $totalLin,
                'id_tarifa_iva'   => empty($det['id_tarifa_iva']) ? null : (int) $det['id_tarifa_iva'],
            ]);

            $subtotal  += $baseLinea;
            $descuento += $dscto;
            $iva       += $valorIva;
            $total     += $totalLin;
        }

        foreach (($data['novedades'] ?? []) as $nov) {
            $descNov = trim((string) ($nov['descripcion'] ?? ''));
            if ($descNov === '') continue;
            $this->repository->insertNovedad([
                'id_orden'    => $idOrden,
                'id_empresa'  => $idEmpresa,
                'descripcion' => $descNov,
                'severidad'   => in_array(($nov['severidad'] ?? 'leve'), ['leve', 'media', 'grave'], true) ? $nov['severidad'] : 'leve',
            ]);
        }

        return [
            'subtotal'  => round($subtotal, 2),
            'descuento' => round($descuento, 2),
            'iva'       => round($iva, 2),
            'total'     => round($total, 2),
        ];
    }
}
