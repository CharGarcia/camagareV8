<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProformaRepository;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;

class ProformaService
{
    private ProformaRepository $repository;
    private ProformaRules $rules;
    private LogSistemaService $log;

    public function __construct(
        ProformaRepository $repository,
        ProformaRules $rules,
        LogSistemaService $log
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->log        = $log;
    }

    /**
     * Crea una nueva proforma. Retorna el id creado.
     * @throws \RuntimeException si hay errores de validación o duplicado
     */
    public function crear(array $data): int
    {
        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa  = (int) $data['id_empresa'];
        $idUsuario  = (int) $data['id_usuario'];
        $idEstab    = (int) $data['id_establecimiento'];
        $idPunto    = (int) $data['id_punto_emision'];
        $secuencial = $data['secuencial'];

        if ($this->repository->existeSecuencial($idEmpresa, $idEstab, $idPunto, $secuencial)) {
            throw new \RuntimeException("El secuencial {$secuencial} ya está en uso.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idProforma = $this->repository->insertCabecera($data);
            $this->guardarDetalles($idProforma, $data['detalles']);
            $this->guardarInfoAdicional($idProforma, $data['info_adicional'] ?? []);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'crear',
                    'proformas_cabecera',
                    $idProforma,
                    null,
                    ['secuencial' => $secuencial, 'id_cliente' => $data['id_cliente']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $idProforma;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza una proforma existente. Solo se puede editar si está en borrador.
     * @throws \RuntimeException
     */
    public function actualizar(int $id, array $data): int
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!in_array($proforma['estado'], ['borrador'], true)) {
            throw new \RuntimeException('Solo se pueden editar proformas en estado borrador.');
        }

        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        if ($this->repository->existeSecuencial(
            $idEmpresa,
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            $data['secuencial'],
            $id
        )) {
            throw new \RuntimeException("El secuencial {$data['secuencial']} ya está en uso.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->updateCabecera($id, $data);
            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles']);
            $this->repository->deleteInfoAdicional($id);
            $this->guardarInfoAdicional($id, $data['info_adicional'] ?? []);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'actualizar',
                    'proformas_cabecera',
                    $id,
                    $proforma,
                    ['secuencial' => $data['secuencial']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado de una proforma.
     * Transiciones permitidas:
     *   borrador → aprobada | anulada
     *   aprobada → rechazada | anulada
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $idEmpresa, int $idUsuario): void
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }

        $estadoActual = $proforma['estado'];
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
                'proformas_cabecera',
                $id,
                ['estado' => $estadoActual],
                ['estado' => $nuevoEstado]
            );
        } catch (\Throwable $e) { /* log falla silenciosamente */ }
    }

    /**
     * Elimina lógicamente una proforma (no permitido si está convertida).
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if ($proforma['estado'] === 'convertida') {
            throw new \RuntimeException('No se puede eliminar una proforma ya convertida a factura.');
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
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'eliminar',
                    'proformas_cabecera',
                    $id,
                    $proforma,
                    null
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }
        }
        return $ok;
    }

    /**
     * Retorna los datos de una proforma formateados para pre-llenar el formulario de ventas.
     */
    public function getForConversion(int $id, int $idEmpresa): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!in_array($proforma['estado'], ['borrador', 'aprobada'], true)) {
            throw new \RuntimeException('Solo se pueden convertir proformas en borrador o aprobadas.');
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$det) {
            $det['impuestos'] = $this->repository->getImpuestosDetalle((int) $det['id']);
        }
        unset($det);

        $adicional = $this->repository->getInfoAdicional($id);

        return [
            'proforma'       => $proforma,
            'detalles'       => $detalles,
            'info_adicional' => $adicional,
        ];
    }

    /**
     * Convierte una proforma en una factura de venta (borrador), copiando los datos
     * a las tablas de ventas en el servidor. Reutiliza FacturaVentaService::crear().
     *
     * Reglas:
     *  - No se convierten proformas rechazadas ni anuladas.
     *  - Si la proforma ya tiene una factura asociada, solo se crea otra cuando $forzar = true
     *    (de lo contrario se devuelve 'requiere_confirmacion' para que la UI confirme).
     *
     * @return array{id_factura?:int, requiere_confirmacion?:bool, mensaje?:string}
     * @throws \RuntimeException
     */
    public function convertirAFactura(int $id, int $idEmpresa, int $idUsuario, bool $forzar = false): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        // ¿Ya fue convertida alguna vez? (para no exigir 'aprobada' al refacturar)
        $yaConvertida = !empty($proforma['id_factura_convertida']) || $proforma['estado'] === 'convertida';

        // Solo se factura una proforma APROBADA (o una ya convertida, para permitir
        // generar otra factura tras confirmación del usuario).
        if (!$yaConvertida && $proforma['estado'] !== 'aprobada') {
            throw new \RuntimeException('La proforma debe estar aprobada para generar una factura.');
        }

        // Mostrar el mensaje de confirmación SOLO si existe alguna factura vigente
        // RELACIONADA con esta proforma (ventas.id_proforma). Si no hay (o todas fueron
        // eliminadas), se factura directo. Se usa el vínculo real con ventas.
        $facturaRepo      = new \App\repositories\modulos\FacturaVentaRepository();
        $facturasVigentes = $facturaRepo->getPorProforma($id, $idEmpresa);

        if (!empty($facturasVigentes) && !$forzar) {
            return [
                'requiere_confirmacion' => true,
                'mensaje' => 'Esta proforma ya tiene una factura asociada. ¿Desea crear otra factura de todos modos?',
            ];
        }

        // Cargar detalles + impuestos + info adicional de la proforma
        $detallesPf = $this->repository->getDetalles($id);
        foreach ($detallesPf as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);
        $adicionalPf = $this->repository->getInfoAdicional($id);

        // ── Resolver el contexto de la factura (establecimiento, punto, secuencial, bodega) ──
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            throw new \RuntimeException('La empresa no tiene establecimientos configurados.');
        }
        $est     = $establecimientos[0];
        $idEstab = (int) $est['id'];

        try {
            $estRepo   = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig($idEstab);
            if ($estConfig) {
                $empresaData = array_merge($empresaData, $estConfig);
            }
        } catch (\Throwable $e) { /* config opcional del establecimiento */ }

        $puntos = $empresaModel->getPuntosEmision($idEstab);
        if (empty($puntos)) {
            throw new \RuntimeException('El establecimiento no tiene puntos de emisión configurados.');
        }
        $punto   = $puntos[0];
        $idPunto = (int) $punto['id'];

        $secRes     = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Facturas de venta');
        $secuencial = $secRes['formateado'] ?? str_pad((string) ($secRes['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT);

        $nivel      = (int) ($_SESSION['nivel'] ?? 1);
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $idBodega   = !empty($bodegas) ? (int) $bodegas[0]['id'] : 0;

        // ── Pre-chequeo de stock según la configuración del establecimiento ──
        // Solo se exige saldo cuando la empresa configuró que la facturación afecta
        // inventario (facturacion_inventario) Y que solo se factura con stock positivo
        // (factura_solo_stock_positivo). Si alguna está apagada, se factura sin validar.
        $faltantes = $this->verificarStockDisponible($detallesPf, $estConfig ?? [], $idEmpresa, $idBodega);
        if (!empty($faltantes)) {
            return [
                'stock_insuficiente' => true,
                'faltantes'          => $faltantes,
            ];
        }

        // ── Mapear detalles (precios y descuentos tal cual de la proforma) ──
        $detallesFac = [];
        foreach ($detallesPf as $d) {
            $impuestos = [];
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impuestos[] = [
                    'codigo_impuesto'   => (string) ($imp['codigo_impuesto'] ?? '2'),
                    'codigo_porcentaje' => (string) ($imp['codigo_porcentaje'] ?? '0'),
                    'tarifa'            => (float) ($imp['tarifa'] ?? 0),
                    'base_imponible'    => (float) ($imp['base_imponible'] ?? 0),
                    'valor'             => (float) ($imp['valor'] ?? 0),
                ];
            }

            // Asignación automática de lote/caducidad/NUP (FEFO), igual que una
            // factura de venta: el más antiguo con saldo de la bodega destino. Necesario
            // para que pasen las reglas cuando el establecimiento exige lote/caducidad/NUP.
            $lote = $this->resolverLoteAutomatico($d, $estConfig ?? [], $idEmpresa, $idBodega);

            $detallesFac[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'codigo_principal'          => $d['codigo_principal'] ?? '',
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? '',
                'descripcion'               => $d['descripcion'] ?? '',
                'nombre'                    => $d['descripcion'] ?? '',
                'cantidad'                  => (float) ($d['cantidad'] ?? 0),
                'precio_unitario'           => (float) ($d['precio_unitario'] ?? 0),
                'descuento'                 => (float) ($d['descuento'] ?? 0),
                'precio_total_sin_impuesto' => (float) ($d['precio_total_sin_impuesto'] ?? 0),
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'id_bodega'                 => $idBodega,
                'es_libre'                  => empty($d['id_producto']) ? '1' : '0',
                'lote'                      => $lote['lote'],
                'caducidad'                 => $lote['caducidad'],
                'nup'                       => $lote['nup'],
                'impuestos'                 => $impuestos,
            ];
        }
        if (empty($detallesFac)) {
            throw new \RuntimeException('La proforma no tiene líneas de detalle para facturar.');
        }

        // ── Info adicional ──
        $infoAdic = [];
        foreach ($adicionalPf as $ia) {
            $infoAdic[] = ['nombre' => $ia['nombre'] ?? '', 'valor' => $ia['valor'] ?? ''];
        }

        // ── Pago por defecto: la factura exige al menos una forma de pago cuyo total
        //    cuadre con el importe. El usuario puede cambiarla al revisar el borrador. ──
        $importeTotal = (float) ($proforma['importe_total'] ?? 0);
        $pagos = [[
            'forma_pago'    => '01', // 01 = Sin utilización del sistema financiero (efectivo)
            'total'         => $importeTotal,
            'plazo'         => 0,
            'unidad_tiempo' => 'dias',
        ]];

        // ── Estructura que espera FacturaVentaService::crear() ──
        $dataFactura = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'id_proforma'         => $id,
            'id_cliente'          => (int) $proforma['id_cliente'],
            'id_vendedor'         => !empty($proforma['id_vendedor']) ? (int) $proforma['id_vendedor'] : null,
            'id_establecimiento'  => $idEstab,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => (string) ($punto['cod_establecimiento'] ?? $est['codigo'] ?? '001'),
            'punto_emision'       => (string) ($punto['codigo_punto'] ?? $punto['codigo'] ?? '001'),
            'secuencial'          => $secuencial,
            'fecha_emision'       => date('Y-m-d'),
            'id_bodega'           => $idBodega,
            'moneda'              => $proforma['moneda'] ?? 'DOLAR',
            'total_sin_impuestos' => (float) ($proforma['total_sin_impuestos'] ?? 0),
            'total_descuento'     => (float) ($proforma['total_descuento'] ?? 0),
            'total_ice'           => (float) ($proforma['total_ice'] ?? 0),
            'importe_total'       => $importeTotal,
            'propina'             => 0,
            'observaciones'       => $proforma['observaciones'] ?? '',
            'empresa_config'      => $empresaData,
            'detalles'            => $detallesFac,
            'info_adicional'      => $infoAdic,
            'pagos'               => $pagos,
        ];

        // crear() maneja su propia transacción, inventario, XML y asiento; por eso NO se
        // envuelve aquí. La factura queda en estado 'borrador' para revisión/emisión.
        $facService = new FacturaVentaService(
            $facturaRepo,
            new \App\Rules\modulos\FacturaVentaRules(),
            new LogSistemaService()
        );
        $idFactura = $facService->crear($dataFactura);

        // Enlazar la proforma con la factura (estado = convertida)
        $this->repository->marcarConvertida($id, $idFactura, $idUsuario);

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'convertir_a_factura',
                'proformas_cabecera',
                $id,
                null,
                ['id_factura' => $idFactura, 'forzado' => $forzar]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        return ['id_factura' => $idFactura];
    }

    /**
     * Convierte una proforma en un recibo de venta (borrador), aplicando EXACTAMENTE
     * las mismas reglas de inventario que la factura: pre-chequeo de stock según la
     * config del establecimiento y asignación automática FEFO de lote/caducidad/NUP.
     * Reutiliza ReciboVentaService::crear().
     *
     * @return array{id_recibo?:int, stock_insuficiente?:bool, faltantes?:array}
     * @throws \RuntimeException
     */
    public function convertirARecibo(int $id, int $idEmpresa, int $idUsuario): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        $yaConvertida = !empty($proforma['id_factura_convertida']) || $proforma['estado'] === 'convertida';
        if (!$yaConvertida && $proforma['estado'] !== 'aprobada') {
            throw new \RuntimeException('La proforma debe estar aprobada para generar un recibo de venta.');
        }

        // Detalles + impuestos + info adicional de la proforma
        $detallesPf = $this->repository->getDetalles($id);
        foreach ($detallesPf as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);
        $adicionalPf = $this->repository->getInfoAdicional($id);

        // ── Contexto (establecimiento, punto, secuencial de recibos, bodega, config) ──
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            throw new \RuntimeException('La empresa no tiene establecimientos configurados.');
        }
        $est     = $establecimientos[0];
        $idEstab = (int) $est['id'];

        $estConfig = null;
        try {
            $estRepo   = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig($idEstab);
            if ($estConfig) {
                $empresaData = array_merge($empresaData, $estConfig);
            }
        } catch (\Throwable $e) { /* config opcional del establecimiento */ }

        $puntos = $empresaModel->getPuntosEmision($idEstab);
        if (empty($puntos)) {
            throw new \RuntimeException('El establecimiento no tiene puntos de emisión configurados.');
        }
        $punto   = $puntos[0];
        $idPunto = (int) $punto['id'];

        $secRes     = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Recibos de venta');
        $secuencial = $secRes['formateado'] ?? str_pad((string) ($secRes['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT);

        $nivel      = (int) ($_SESSION['nivel'] ?? 1);
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $idBodega   = !empty($bodegas) ? (int) $bodegas[0]['id'] : 0;

        // ── Mismo pre-chequeo de stock que la factura ──
        $faltantes = $this->verificarStockDisponible($detallesPf, $estConfig ?? [], $idEmpresa, $idBodega);
        if (!empty($faltantes)) {
            return ['stock_insuficiente' => true, 'faltantes' => $faltantes];
        }

        // ── Mapear detalles con asignación FEFO de lote/caducidad/NUP (igual que factura) ──
        $detallesRec = [];
        foreach ($detallesPf as $d) {
            $impuestos = [];
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impuestos[] = [
                    'codigo_impuesto'   => (string) ($imp['codigo_impuesto'] ?? '2'),
                    'codigo_porcentaje' => (string) ($imp['codigo_porcentaje'] ?? '0'),
                    'tarifa'            => (float) ($imp['tarifa'] ?? 0),
                    'base_imponible'    => (float) ($imp['base_imponible'] ?? 0),
                    'valor'             => (float) ($imp['valor'] ?? 0),
                ];
            }

            $lote = $this->resolverLoteAutomatico($d, $estConfig ?? [], $idEmpresa, $idBodega);

            $detallesRec[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'id_bodega'                 => $idBodega,
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'id_medida'                 => $d['id_unidad_medida'] ?? null,
                'codigo_principal'          => $d['codigo_principal'] ?? '',
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? '',
                'descripcion'               => $d['descripcion'] ?? '',
                'nombre'                    => $d['descripcion'] ?? '',
                'cantidad'                  => (float) ($d['cantidad'] ?? 0),
                'precio_unitario'           => (float) ($d['precio_unitario'] ?? 0),
                'descuento'                 => (float) ($d['descuento'] ?? 0),
                'precio_total_sin_impuesto' => (float) ($d['precio_total_sin_impuesto'] ?? 0),
                'es_libre'                  => empty($d['id_producto']) ? '1' : '0',
                'lote'                      => $lote['lote'],
                'caducidad'                 => $lote['caducidad'],
                'nup'                       => $lote['nup'],
                'impuestos'                 => $impuestos,
            ];
        }
        if (empty($detallesRec)) {
            throw new \RuntimeException('La proforma no tiene líneas de detalle para el recibo.');
        }

        // ── Info adicional ──
        $infoAdic = [];
        foreach ($adicionalPf as $ia) {
            $infoAdic[] = ['nombre' => $ia['nombre'] ?? '', 'valor' => $ia['valor'] ?? ''];
        }

        // ── Pago por defecto (efectivo) que cuadra con el total ──
        $importeTotal = (float) ($proforma['importe_total'] ?? 0);
        $pagos = [[
            'forma_pago'    => '01',
            'total'         => $importeTotal,
            'plazo'         => 0,
            'unidad_tiempo' => 'dias',
        ]];

        $numProf = ($proforma['establecimiento'] ?? '') . '-' . ($proforma['punto_emision'] ?? '') . '-' . ($proforma['secuencial'] ?? '');

        $dataRecibo = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaData,
            'id_establecimiento'  => $idEstab,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => (string) ($punto['cod_establecimiento'] ?? $est['codigo'] ?? '001'),
            'punto_emision'       => (string) ($punto['codigo_punto'] ?? $punto['codigo'] ?? '001'),
            'secuencial'          => $secuencial,
            'fecha_emision'       => date('Y-m-d'),
            'id_cliente'          => (int) $proforma['id_cliente'],
            'id_vendedor'         => !empty($proforma['id_vendedor']) ? (int) $proforma['id_vendedor'] : null,
            'dias_credito'        => 0,
            'plazo'               => 0,
            'moneda'              => $proforma['moneda'] ?? 'DOLAR',
            'con_impuestos'       => true,
            'estado'              => 'borrador',
            'observaciones'       => trim('Generado desde proforma ' . $numProf . '. ' . ($proforma['observaciones'] ?? '')),
            'id_bodega'           => $idBodega,
            'total_sin_impuestos' => (float) ($proforma['total_sin_impuestos'] ?? 0),
            'total_descuento'     => (float) ($proforma['total_descuento'] ?? 0),
            'total_ice'           => (float) ($proforma['total_ice'] ?? 0),
            'propina'             => 0,
            'importe_total'       => $importeTotal,
            'detalles'            => $detallesRec,
            'pagos'               => $pagos,
            'info_adicional'      => $infoAdic,
        ];

        // crear() maneja su propia transacción, inventario y asiento. El recibo queda
        // en 'borrador' para revisión/emisión.
        $recService = new ReciboVentaService(
            new \App\repositories\modulos\ReciboVentaRepository(),
            new \App\Rules\modulos\ReciboVentaRules(),
            new LogSistemaService()
        );
        $idRecibo = $recService->crear($dataRecibo);

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'convertir_a_recibo',
                'proformas_cabecera',
                $id,
                null,
                ['id_recibo' => $idRecibo]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        return ['id_recibo' => $idRecibo];
    }

    /**
     * Verifica el stock disponible de los productos de la proforma según la
     * configuración del establecimiento. Devuelve la lista de productos sin saldo
     * suficiente (o [] si la config no exige stock o hay saldo para todos).
     *
     * Reglas (idénticas a FacturaVentaService::crear):
     *  - Solo aplica si facturacion_inventario Y factura_solo_stock_positivo están activas.
     *  - Solo productos de catálogo, inventariables y que no sean servicios (tipo_produccion '02').
     *  - Cantidades acumuladas por producto (varias líneas del mismo producto suman).
     *
     * @return array<int,array{producto:string,disponible:float,requerido:float}>
     */
    private function verificarStockDisponible(array $detalles, array $estConfig, int $idEmpresa, int $idBodega): array
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        $afectaInv    = $toBool($estConfig['facturacion_inventario'] ?? false);
        $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);

        // La empresa no exige stock: se factura sin validar (permite negativos).
        if (!$afectaInv || !$soloStockPos) {
            return [];
        }

        $productoRepo = new \App\repositories\modulos\ProductoRepository();
        $invRepo      = new \App\repositories\modulos\InventarioRepository();

        // Acumular cantidades requeridas por producto inventariable.
        $requerido = [];
        foreach ($detalles as $d) {
            if (empty($d['id_producto'])) continue; // línea libre: no afecta inventario
            $idProd = (int) $d['id_producto'];

            if (!isset($requerido[$idProd])) {
                $info = $productoRepo->getInfoControlInventario($idProd, $idEmpresa);
                if (empty($info['inventariable']) || ($info['tipo_produccion'] ?? '') === '02') {
                    continue; // no inventariable o servicio: no valida stock
                }
                $requerido[$idProd] = ['nombre' => $d['descripcion'] ?? 'Producto', 'cantidad' => 0.0];
            }
            $requerido[$idProd]['cantidad'] += (float) ($d['cantidad'] ?? 0);
        }

        // Comparar contra el saldo actual en la bodega destino.
        $faltantes = [];
        foreach ($requerido as $idProd => $info) {
            $stock = $invRepo->getStockActual($idProd, $idBodega, $idEmpresa);
            if ($stock < $info['cantidad']) {
                $faltantes[] = [
                    'producto'   => $info['nombre'],
                    'disponible' => $stock,
                    'requerido'  => $info['cantidad'],
                ];
            }
        }
        return $faltantes;
    }

    /**
     * Resuelve el lote/caducidad/NUP a usar en una línea al convertir a factura,
     * replicando la selección automática de la factura de venta (FEFO: el lote más
     * antiguo por caducidad de la bodega destino). Solo aplica a productos de catálogo
     * inventariables que no sean servicios (tipo_produccion '02').
     *
     * Si el establecimiento no factura con inventario, o el producto no es inventariable,
     * devuelve valores nulos (la línea no lleva lote).
     *
     * @return array{lote:?string,caducidad:?string,nup:?string}
     */
    private function resolverLoteAutomatico(array $det, array $estConfig, int $idEmpresa, int $idBodega): array
    {
        $vacio = ['lote' => null, 'caducidad' => null, 'nup' => null];

        if (empty($det['id_producto'])) {
            return $vacio; // línea libre
        }

        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        $afectaInv = $toBool($estConfig['facturacion_inventario'] ?? false);
        if (!$afectaInv) {
            return $vacio; // el establecimiento no factura con inventario
        }

        $productoRepo = new \App\repositories\modulos\ProductoRepository();
        $info = $productoRepo->getInfoControlInventario((int) $det['id_producto'], $idEmpresa);
        if (empty($info['inventariable']) || ($info['tipo_produccion'] ?? '') === '02') {
            return $vacio; // no inventariable o servicio
        }

        // FEFO: si el establecimiento exige stock positivo, solo lotes con saldo;
        // caso contrario, el más antiguo aunque no tenga saldo (permite negativo).
        $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);
        $invRepo  = new \App\repositories\modulos\InventarioRepository();
        $loteAuto = $invRepo->getLoteMasAntiguo((int) $det['id_producto'], $idBodega, $idEmpresa, null, $soloStockPos);

        if (!$loteAuto) {
            return $vacio; // sin lote disponible: lo resolverá el inventario o fallará la regla si es obligatorio
        }

        return [
            'lote'      => !empty($loteAuto['numero_lote'])    ? (string) $loteAuto['numero_lote']    : null,
            'caducidad' => !empty($loteAuto['fecha_caducidad']) ? (string) $loteAuto['fecha_caducidad'] : null,
            'nup'       => !empty($loteAuto['nup'])            ? (string) $loteAuto['nup']            : null,
        ];
    }

    /**
     * Obtiene el siguiente secuencial para proformas en un punto de emisión.
     */
    public function getSiguienteSecuencial(int $idPunto): array
    {
        $secService = new SecuencialService();
        return $secService->obtenerSiguienteSecuencial($idPunto, 'Proformas');
    }

    private function guardarDetalles(int $idProforma, array $detalles): void
    {
        foreach ($detalles as $det) {
            $det['id_proforma'] = $idProforma;
            $idDetalle = $this->repository->insertDetalle($det);

            if (!empty($det['impuestos']) && is_array($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    if ((float) ($imp['valor'] ?? 0) == 0 && (float) ($imp['base_imponible'] ?? 0) == 0) continue;
                    $this->repository->insertImpuesto([
                        'id_proforma_detalle' => $idDetalle,
                        'codigo_impuesto'     => $imp['codigo_impuesto'] ?? '2',
                        'codigo_porcentaje'   => $imp['codigo_porcentaje'] ?? '2',
                        'tarifa'              => (float) ($imp['tarifa'] ?? 0),
                        'base_imponible'      => (float) ($imp['base_imponible'] ?? 0),
                        'valor'               => (float) ($imp['valor'] ?? 0),
                    ]);
                }
            }
        }
    }

    private function guardarInfoAdicional(int $idProforma, array $adicional): void
    {
        foreach ($adicional as $item) {
            $nombre = trim($item['nombre'] ?? '');
            $valor  = trim($item['valor'] ?? '');
            if ($nombre === '' || $valor === '') continue;
            $this->repository->insertInfoAdicional([
                'id_proforma' => $idProforma,
                'nombre'      => $nombre,
                'valor'       => $valor,
            ]);
        }
    }
}
