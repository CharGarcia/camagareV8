<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CotizacionPublicidadRepository;
use App\Rules\modulos\CotizacionPublicidadRules;
use App\Services\LogSistemaService;
use App\core\Database;

class CotizacionPublicidadService
{
    private CotizacionPublicidadRepository $repository;
    private CotizacionPublicidadRules $rules;
    private LogSistemaService $log;

    public function __construct(
        CotizacionPublicidadRepository $repository,
        CotizacionPublicidadRules $rules,
        LogSistemaService $log
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->log        = $log;
    }

    /**
     * Crea una nueva cotización (version=1). El número se calcula automáticamente
     * por cliente + año de la fecha de emisión (esquema legacy, no correlativo global).
     */
    public function crear(array $data): int
    {
        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $idCliente = (int) $data['id_cliente'];
        $anio      = (int) date('Y', strtotime($data['fecha_emision']));

        $data['numero']  = $this->repository->siguienteNumero($idEmpresa, $idCliente, $anio);
        $data['version'] = 1;

        $this->calcularTotales($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idCotizacion = $this->repository->insertCabecera($data);
            $this->guardarDetalles($idCotizacion, $data['detalles']);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'crear',
                    'cotizacion_publicidad_cabecera',
                    $idCotizacion,
                    null,
                    ['numero' => $data['numero'], 'version' => 1, 'id_cliente' => $idCliente]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $idCotizacion;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza una cotización existente. Solo editable en estado borrador.
     */
    public function actualizar(int $id, array $data): int
    {
        $cotizacion = $this->repository->getPorId($id);
        if (!$cotizacion) {
            throw new \RuntimeException('Cotización no encontrada.');
        }
        if ($cotizacion['estado'] !== 'borrador') {
            throw new \RuntimeException('Solo se pueden editar cotizaciones en estado borrador.');
        }

        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->calcularTotales($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->updateCabecera($id, $data);
            $this->reconciliarDetalles($id, $data['detalles']);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'actualizar',
                    'cotizacion_publicidad_cabecera',
                    $id,
                    $cotizacion,
                    ['numero' => $cotizacion['numero'], 'version' => $cotizacion['version']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Clona una cotización existente como nueva versión (mismo numero, version+1),
     * equivalente al botón "Versión" del legacy.
     */
    public function nuevaVersion(int $id, int $idEmpresa, int $idUsuario): int
    {
        $original = $this->repository->getPorId($id);
        if (!$original || (int) $original['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Cotización no encontrada.');
        }

        $detallesOriginal = $this->repository->getDetalles($id);
        if (empty($detallesOriginal)) {
            throw new \RuntimeException('La cotización no tiene líneas para clonar.');
        }

        $anio          = (int) date('Y', strtotime($original['fecha_emision']));
        $nuevaVersion  = $this->repository->siguienteVersion($idEmpresa, (int) $original['id_cliente'], (int) $original['numero'], $anio);

        $data = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'id_cliente'          => (int) $original['id_cliente'],
            'id_vendedor'         => $original['id_vendedor'],
            'contacto'            => $original['contacto'],
            'fecha_emision'       => date('Y-m-d'),
            'proyecto'            => $original['proyecto'],
            'numero'              => (int) $original['numero'],
            'version'             => $nuevaVersion,
            'presupuesto'         => (float) $original['presupuesto'],
            'id_tarifa_iva'       => (int) $original['id_tarifa_iva'],
            'comision'            => (float) $original['comision'],
            'observaciones'       => $original['observaciones'],
            'estado'              => 'borrador',
            'total_sin_impuestos' => (float) $original['total_sin_impuestos'],
            'total_comision'      => (float) $original['total_comision'],
            'total_iva'           => (float) $original['total_iva'],
            'importe_total'       => (float) $original['importe_total'],
            'moneda'              => $original['moneda'],
        ];

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idNueva = $this->repository->insertCabecera($data);
            foreach ($detallesOriginal as $det) {
                $this->repository->insertDetalle([
                    'id_cotizacion'             => $idNueva,
                    'id_categoria'              => $det['id_categoria'],
                    'descripcion'               => $det['descripcion'],
                    'precio_unitario'           => (float) $det['precio_unitario'],
                    'ciudades'                  => (int) $det['ciudades'],
                    'dias'                      => (int) $det['dias'],
                    'cantidad'                  => (float) $det['cantidad'],
                    'precio_total_sin_impuesto' => (float) $det['precio_total_sin_impuesto'],
                    // El costo (proveedor/factura/valor) NO se clona: cada versión evalúa costos propios.
                ]);
            }
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'nueva_version',
                    'cotizacion_publicidad_cabecera',
                    $idNueva,
                    null,
                    ['numero' => $data['numero'], 'version' => $nuevaVersion, 'id_origen' => $id]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $idNueva;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado de una cotización.
     * Transiciones permitidas: borrador -> aprobada|anulada; aprobada -> rechazada|anulada.
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $idEmpresa, int $idUsuario): void
    {
        $cotizacion = $this->repository->getPorId($id);
        if (!$cotizacion || (int) $cotizacion['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Cotización no encontrada.');
        }

        $estadoActual = $cotizacion['estado'];
        $permitidas = [
            'borrador' => ['aprobada', 'anulada'],
            'aprobada' => ['rechazada', 'anulada'],
        ];

        if (!isset($permitidas[$estadoActual]) || !in_array($nuevoEstado, $permitidas[$estadoActual], true)) {
            throw new \RuntimeException("No se puede cambiar de '{$estadoActual}' a '{$nuevoEstado}'.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->actualizarEstado($id, $nuevoEstado, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'cambiar_estado',
                'cotizacion_publicidad_cabecera',
                $id,
                ['estado' => $estadoActual],
                ['estado' => $nuevoEstado]
            );
        } catch (\Throwable $e) { /* log falla silenciosamente */ }
    }

    /**
     * Reemplaza los costos por proveedor (pestaña "Costos") de una cotización.
     * Una misma línea cotizada (id_detalle) puede tener varias filas de costo
     * (varios proveedores/facturas). No cambia el estado ni los montos cotizados.
     */
    public function guardarCostos(int $id, int $idEmpresa, int $idUsuario, array $costos): void
    {
        $cotizacion = $this->repository->getPorId($id);
        if (!$cotizacion || (int) $cotizacion['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Cotización no encontrada.');
        }

        $idsDetalleValidos = array_column($this->repository->getDetalles($id), 'id');

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->deleteCostosPorCotizacion($id);
            foreach ($costos as $costo) {
                $idDetalle = (int) ($costo['id_detalle'] ?? 0);
                if (!$idDetalle || !in_array($idDetalle, $idsDetalleValidos, true)) continue;
                // Fila vacía (sin proveedor, factura ni costo): no se guarda.
                if (empty($costo['id_proveedor']) && empty($costo['factura_proveedor']) && empty((float) ($costo['valor_costo'] ?? 0))) continue;
                $costo['id_detalle'] = $idDetalle;
                $this->repository->insertCosto($costo);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $this->log->registrar($idUsuario, $idEmpresa, 'guardar_costos', 'cotizacion_publicidad_cabecera', $id, null, null);
        } catch (\Throwable $e) { /* log falla silenciosamente */ }
    }

    /**
     * Elimina lógicamente una cotización (no permitido si está convertida).
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $cotizacion = $this->repository->getPorId($id);
        if (!$cotizacion || (int) $cotizacion['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Cotización no encontrada.');
        }
        if ($cotizacion['estado'] === 'convertida') {
            throw new \RuntimeException('No se puede eliminar una cotización ya convertida a factura.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $ok = $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        if ($ok) {
            try {
                $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'cotizacion_publicidad_cabecera', $id, $cotizacion, null);
            } catch (\Throwable $e) { /* log falla silenciosamente */ }
        }
        return $ok;
    }

    /**
     * Genera una Factura de Venta real (borrador) a partir de los datos armados
     * manualmente por el usuario en el modal "Generar Factura" (fecha, serie,
     * cliente y productos elegidos —no son los ítems de la cotización—),
     * reutilizando FacturaVentaService::crear().
     *
     * Solo se permite generar si NO existe ya una factura activa (no anulada)
     * vinculada a esta cotización.
     *
     * @param array $data { fecha_emision, id_establecimiento, id_punto_emision,
     *                      establecimiento, punto_emision, secuencial, id_cliente,
     *                      detalles: [{id_producto?, codigo_principal?, descripcion,
     *                      cantidad, precio_unitario, id_tarifa_iva}] }
     * @return array{id_factura:int}
     */
    public function convertirAFactura(int $id, int $idEmpresa, int $idUsuario, array $data): array
    {
        $cotizacion = $this->repository->getPorId($id);
        if (!$cotizacion || (int) $cotizacion['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Cotización no encontrada.');
        }

        if (!in_array($cotizacion['estado'], ['aprobada', 'convertida'], true)) {
            throw new \RuntimeException('La cotización debe estar aprobada para generar una factura.');
        }

        $facturaRepo      = new \App\repositories\modulos\FacturaVentaRepository();
        $facturasVigentes = $facturaRepo->getPorCotizacionPublicidad($id, $idEmpresa);
        foreach ($facturasVigentes as $f) {
            if (($f['estado'] ?? '') !== 'anulada') {
                throw new \RuntimeException('Ya existe una factura activa para esta cotización. Anúlela antes de generar otra.');
            }
        }

        if (empty($data['id_cliente'])) {
            throw new \RuntimeException('Debe seleccionar un cliente.');
        }
        if (empty($data['fecha_emision'])) {
            throw new \RuntimeException('Debe indicar la fecha de emisión.');
        }
        if (empty($data['id_establecimiento']) || empty($data['id_punto_emision']) || empty($data['secuencial'])) {
            throw new \RuntimeException('Debe seleccionar la serie de facturación.');
        }
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new \RuntimeException('Debe agregar al menos un producto a facturar.');
        }

        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
        $idEstab      = (int) $data['id_establecimiento'];

        try {
            $estRepo   = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig($idEstab);
            if ($estConfig) {
                $empresaData = array_merge($empresaData, $estConfig);
            }
        } catch (\Throwable $e) { /* config opcional del establecimiento */ }

        $nivel      = (int) ($_SESSION['nivel'] ?? 1);
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $idBodega   = !empty($bodegas) ? (int) $bodegas[0]['id'] : null;

        // ── Líneas elegidas manualmente por el usuario (productos reales del catálogo) ──
        $detallesFac  = [];
        $subtotalTotal = 0.0;
        $ivaTotal      = 0.0;
        foreach ($data['detalles'] as $i => $det) {
            $fila = $i + 1;
            $descripcion = trim((string) ($det['descripcion'] ?? ''));
            if ($descripcion === '') {
                throw new \RuntimeException("Fila {$fila}: la descripción es obligatoria.");
            }
            $cantidad = (float) ($det['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw new \RuntimeException("Fila {$fila}: la cantidad debe ser mayor a cero.");
            }
            $precio = (float) ($det['precio_unitario'] ?? 0);
            if ($precio < 0) {
                throw new \RuntimeException("Fila {$fila}: el precio no puede ser negativo.");
            }

            $tarifaIva = $this->repository->getTarifaIva((int) ($det['id_tarifa_iva'] ?? 0));
            $pct       = (float) ($tarifaIva['porcentaje_iva'] ?? 0);
            $codPct    = '0';
            if ($pct > 0) {
                $codPct = (abs($pct - 12) < 0.01) ? '2' : ((abs($pct - 15) < 0.01) ? '4' : '3');
            }

            $subtotalLinea = round($cantidad * $precio, 2);
            $ivaLinea      = round($subtotalLinea * $pct / 100, 2);
            $subtotalTotal += $subtotalLinea;
            $ivaTotal      += $ivaLinea;

            $idProducto = !empty($det['id_producto']) ? (int) $det['id_producto'] : null;

            $detallesFac[] = [
                'id_producto'               => $idProducto,
                'id_bodega'                 => $idBodega,
                'codigo_principal'          => $det['codigo_principal'] ?? '',
                'codigo_auxiliar'           => '',
                'descripcion'               => $descripcion,
                'nombre'                    => $descripcion,
                'cantidad'                  => $cantidad,
                'precio_unitario'           => $precio,
                'descuento'                 => 0,
                'precio_total_sin_impuesto' => $subtotalLinea,
                'es_libre'                  => $idProducto ? '0' : '1',
                'lote'                      => null,
                'caducidad'                 => null,
                'nup'                       => null,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa'            => $pct,
                    'base_imponible'    => $subtotalLinea,
                    'valor'             => $ivaLinea,
                ]],
            ];
        }

        $importeTotal = round($subtotalTotal + $ivaTotal, 2);
        $pagos = [[
            'forma_pago'    => '01',
            'total'         => $importeTotal,
            'plazo'         => 0,
            'unidad_tiempo' => 'dias',
        ]];

        $infoAdic = [
            ['nombre' => 'Proyecto', 'valor' => (string) ($cotizacion['proyecto'] ?? '')],
            ['nombre' => 'Cotización de publicidad origen', 'valor' => $this->formatearNumero($cotizacion)],
        ];
        if (!empty($cotizacion['contacto'])) {
            $infoAdic[] = ['nombre' => 'Contacto', 'valor' => (string) $cotizacion['contacto']];
        }

        $dataFactura = [
            'id_empresa'               => $idEmpresa,
            'id_usuario'               => $idUsuario,
            'id_cotizacion_publicidad' => $id,
            'id_cliente'                => (int) $data['id_cliente'],
            'id_vendedor'               => !empty($cotizacion['id_vendedor']) ? (int) $cotizacion['id_vendedor'] : null,
            'id_establecimiento'        => $idEstab,
            'id_punto_emision'          => (int) $data['id_punto_emision'],
            'establecimiento'           => (string) $data['establecimiento'],
            'punto_emision'             => (string) $data['punto_emision'],
            'secuencial'                => (string) $data['secuencial'],
            'fecha_emision'             => $data['fecha_emision'],
            'id_bodega'                 => $idBodega,
            'moneda'                    => $cotizacion['moneda'] ?? 'DOLAR',
            'total_sin_impuestos'       => round($subtotalTotal, 2),
            'total_descuento'           => 0,
            'total_ice'                 => 0,
            'importe_total'             => $importeTotal,
            'propina'                   => 0,
            'observaciones'             => $cotizacion['observaciones'] ?? '',
            'empresa_config'            => $empresaData,
            'detalles'                  => $detallesFac,
            'info_adicional'            => $infoAdic,
            'pagos'                     => $pagos,
        ];

        // crear() maneja su propia transacción, inventario, XML y asiento; no se envuelve aquí.
        $facService = new FacturaVentaService(
            $facturaRepo,
            new \App\Rules\modulos\FacturaVentaRules(),
            new LogSistemaService()
        );
        $idFactura = $facService->crear($dataFactura);

        $this->repository->marcarConvertida($id, $idFactura, $idUsuario);

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'convertir_a_factura',
                'cotizacion_publicidad_cabecera',
                $id,
                null,
                ['id_factura' => $idFactura]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        return ['id_factura' => $idFactura];
    }

    /**
     * Calcula los totales de la cotización sobre el array $data (por referencia):
     * subtotal (Σ precio*cantidad) -> + comisión de agencia % -> + IVA sobre
     * (subtotal+comisión) -> total. dias/ciudades son informativos, no afectan el cálculo.
     */
    private function calcularTotales(array &$data): void
    {
        $subtotal = 0.0;
        foreach ($data['detalles'] as &$det) {
            $lineaSubtotal = round((float) $det['precio_unitario'] * (float) $det['cantidad'], 2);
            $det['precio_total_sin_impuesto'] = $lineaSubtotal;
            $subtotal += $lineaSubtotal;
        }
        unset($det);

        $comisionPct = (float) ($data['comision'] ?? 0);
        $comision    = round($subtotal * $comisionPct / 100, 2);

        $tarifaIva = $this->repository->getTarifaIva((int) $data['id_tarifa_iva']);
        $pct       = (float) ($tarifaIva['porcentaje_iva'] ?? 0);
        $iva       = round(($subtotal + $comision) * $pct / 100, 2);

        $data['total_sin_impuestos'] = $subtotal;
        $data['total_comision']      = $comision;
        $data['total_iva']           = $iva;
        $data['importe_total']       = round($subtotal + $comision + $iva, 2);
    }

    private function guardarDetalles(int $idCotizacion, array $detalles): void
    {
        foreach ($detalles as $det) {
            $det['id_cotizacion'] = $idCotizacion;
            $this->repository->insertDetalle($det);
        }
    }

    /**
     * Reconcilia las líneas al editar una cotización (en vez de borrar-y-reinsertar
     * todo): las líneas que el usuario conserva (traen su id) se actualizan EN SITIO
     * para no perder los costos ya guardados en cotizacion_publicidad_costos
     * (id_detalle referencia el id de la línea). Las líneas nuevas se insertan y
     * las que el usuario quitó se eliminan junto con sus costos.
     */
    private function reconciliarDetalles(int $idCotizacion, array $detallesPayload): void
    {
        $idsActuales = array_column($this->repository->getDetalles($idCotizacion), 'id');

        $idsConservar = [];
        foreach ($detallesPayload as $det) {
            $detId = (int) ($det['id'] ?? 0);
            if ($detId > 0 && in_array($detId, $idsActuales, true)) {
                $idsConservar[] = $detId;
            }
        }

        $idsEliminar = array_values(array_diff($idsActuales, $idsConservar));
        if (!empty($idsEliminar)) {
            $this->repository->deleteCostosDeDetalles($idsEliminar);
            $this->repository->deleteDetallesPorIds($idsEliminar);
        }

        foreach ($detallesPayload as $det) {
            $detId = (int) ($det['id'] ?? 0);
            if ($detId > 0 && in_array($detId, $idsConservar, true)) {
                $this->repository->updateDetalle($detId, $det);
            } else {
                $det['id_cotizacion'] = $idCotizacion;
                $this->repository->insertDetalle($det);
            }
        }
    }

    private function formatearNumero(array $cotizacion): string
    {
        $numero = str_pad((string) $cotizacion['numero'], 3, '0', STR_PAD_LEFT);
        $anio   = date('Y', strtotime($cotizacion['fecha_emision']));
        return "{$numero}-{$anio} V{$cotizacion['version']}";
    }
}
