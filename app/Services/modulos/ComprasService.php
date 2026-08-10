<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ComprasRepository;
use App\Rules\modulos\ComprasRules;
use App\Services\LogSistemaService;
use App\Services\modulos\PeriodosContablesService;
use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\core\Database;

class ComprasService
{
    private ComprasRepository $repository;
    private ComprasRules $rules;
    private LogSistemaService $logService;
    private PeriodosContablesService $periodosService;
    private ?string $lastAsientoWarning = null;

    public function __construct()
    {
        $this->repository = new ComprasRepository();
        $this->rules = new ComprasRules();
        $this->logService = new LogSistemaService();
        
        // Inicialización manual de dependencias del servicio de periodos
        $periodosRepo = new PeriodosContablesRepository();
        $periodosRules = new PeriodosContablesRules();
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $this->logService);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->verificarSecuencialDuplicado($data);
        $this->validarPeriodo($data, 'No se puede registrar la compra porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            // El documento del proveedor no trae "nuestro" establecimiento; si no viene ya
            // resuelto (ej. desde el registro automático del SRI), se atribuye al
            // establecimiento activo de la empresa (mismo criterio que el registro por XML).
            if (empty($data['id_establecimiento'])) {
                $data['id_establecimiento'] = (new \App\repositories\modulos\EmpresaRepository())->getPrimerEstablecimientoId($idEmpresa);
            }

            $data = $this->calcularTotales($data);
            $idCompra = $this->repository->insertCabecera($data);

            $this->sincronizarDetalles($idCompra, $data['detalles'] ?? []);
            $this->guardarPagos($idCompra, $data['pagos'] ?? []);
            $this->guardarAdicionales($idCompra, $data['adicionales'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'CREAR', 'compras_cabecera', $idCompra,
                null, ['id_compra' => $idCompra, 'total' => $data['importe_total'] ?? 0]
            );

            $this->sincronizarCasilleros($idCompra, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            $this->relanzarSiDuplicado($e);
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la compra ya guardada.
        $this->generarAsientoTrasGuardar($idCompra, $data);
        return $idCompra;
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $compra = $this->repository->getPorId($id, $idEmpresa);
        if (!$compra) {
            return null;
        }

        $compra['detalles']    = $this->repository->getDetalles($id);
        $compra['pagos']       = $this->repository->getPagos($id);
        $compra['adicionales'] = $this->repository->getInfoAdicional($id);

        // Formatear adicionales para que sea un objeto clave:valor
        $adicionales = [];
        foreach ($compra['adicionales'] as $adj) {
            $adicionales[$adj['nombre']] = $adj['valor'];
        }
        $compra['adicionales'] = $adicionales;

        // Cargar impuestos de cada detalle
        foreach ($compra['detalles'] as &$det) {
            $det['impuestos'] = $this->repository->getImpuestosDetalle((int)$det['id']);
        }

        $compra['egresos_vinculados'] = $this->repository->getEgresosVinculados($id);

        // Detalle de "Factura de Reembolso" recibida (bloque <reembolsos> del XML, solo si aplica).
        $terceros = $this->repository->getReembolsoTerceros($id);
        foreach ($terceros as &$t) {
            $t['impuestos'] = $this->repository->getImpuestosReembolsoTercero((int) $t['id']);
        }
        unset($t);
        $compra['terceros_reembolso'] = $terceros;

        return $compra;
    }

    /**
     * Flags de solo lectura de una compra para el modal:
     *  - es_migrado: proviene de una migración (no editable).
     *  - periodo_cerrado: su fecha cae en un período contable cerrado (no editable).
     */
    public function getFlagsSoloLectura(int $id, int $idEmpresa, ?string $fecha): array
    {
        return [
            'es_migrado'      => $this->repository->esMigrado($id, $idEmpresa),
            'periodo_cerrado' => $this->periodosService->esFechaEnPeriodoCerrado($fecha, $idEmpresa),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PARSEO DEL XML (para generar el PDF a partir del comprobante electrónico)
    // ─────────────────────────────────────────────────────────────────────────

    /** Nombres de formas de pago SRI (código => descripción). */
    private const FORMAS_PAGO_SRI = [
        '01' => 'Sin utilización del sistema financiero',
        '15' => 'Compensación de deudas',
        '16' => 'Tarjeta de débito',
        '17' => 'Dinero electrónico',
        '18' => 'Tarjeta prepago',
        '19' => 'Tarjeta de crédito',
        '20' => 'Otros con utilización del sistema financiero',
        '21' => 'Endoso de títulos',
    ];

    /**
     * Convierte el XML autorizado de un comprobante de compra en las estructuras
     * que consume ComprasPdfService: cabecera, detalles, pagos, infoAdicional y
     * los datos del adquirente (comprador). Lanza excepción si el XML es inválido.
     */
    public function parsearComprobanteXml(string $xmlString): array
    {
        $xmlString = trim($xmlString);
        if ($xmlString === '') {
            throw new \RuntimeException('El comprobante no tiene XML.');
        }

        // La fecha de autorización solo existe en el sobre <autorizacion> del SRI
        // (no dentro del comprobante). Se captura ANTES de quitar el sobre.
        $fechaAutorizacion = '';
        if (strpos($xmlString, '<autorizacion>') !== false) {
            if (preg_match('#<fechaAutorizacion>(.*?)</fechaAutorizacion>#s', $xmlString, $mfa)) {
                $fechaAutorizacion = trim($mfa[1]);
            }
            // Quitar el sobre de autorización para quedarnos con el comprobante.
            if (preg_match('/<comprobante><!\[CDATA\[(.*?)\]\]><\/comprobante>/s', $xmlString, $m)) {
                $xmlString = $m[1];
            } elseif (preg_match('/<comprobante>(.*?)<\/comprobante>/s', $xmlString, $m)) {
                $xmlString = htmlspecialchars_decode($m[1]);
            }
        }

        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($xmlString);
        libxml_use_internal_errors($prev);
        if ($xml === false || !isset($xml->infoTributaria)) {
            throw new \RuntimeException('El XML del comprobante no tiene un formato válido del SRI.');
        }

        $t      = $xml->infoTributaria;
        $codDoc = (string) $t->codDoc;

        // Nodo de información según tipo de documento.
        $nodoInfo = match ($codDoc) {
            '01'    => 'infoFactura',
            '03'    => 'infoLiquidacionCompra',
            '04'    => 'infoNotaCredito',
            '05'    => 'infoNotaDebito',
            default => 'infoFactura',
        };
        $info = $xml->$nodoInfo ?? null;
        if ($info === null) {
            // Buscar el primer nodo "info..." distinto de infoTributaria/infoAdicional.
            foreach ($xml->children() as $child) {
                $n = $child->getName();
                if (str_starts_with($n, 'info') && $n !== 'infoTributaria' && $n !== 'infoAdicional') {
                    $info = $child;
                    break;
                }
            }
        }
        if ($info === null) {
            throw new \RuntimeException('El XML no contiene la información del comprobante.');
        }

        // ── Cabecera (emisor = proveedor; número y autorización del documento) ──
        $cabecera = [
            'tipo_comprobante'         => $codDoc,
            'establecimiento_prov'     => (string) $t->estab,
            'punto_emision_prov'       => (string) $t->ptoEmi,
            'secuencial_prov'          => (string) $t->secuencial,
            'numero_autorizacion'      => (string) $t->claveAcceso,
            'fecha_autorizacion'       => $fechaAutorizacion,
            'tipo_ambiente'            => (string) $t->ambiente,
            'fecha_emision'            => (string) ($info->fechaEmision ?? ''),
            'proveedor_nombre'         => (string) $t->razonSocial,
            'proveedor_ruc'            => (string) $t->ruc,
            'proveedor_direccion'      => (string) ($t->dirMatriz ?? ''),
            'proveedor_email'          => '',
            'proveedor_nombre_tipo_id' => 'R.U.C.',
            'total_sin_impuestos'      => (float) ($info->totalSinImpuestos ?? 0),
            'importe_total'            => (float) ($info->importeTotal ?? $info->valorModificacion ?? 0),
            'propina'                  => (float) ($info->propina ?? 0),
        ];

        // ── Adquirente (comprador / receptor) ─────────────────────────────────
        $comprador = [
            'nombre'    => (string) ($info->razonSocialComprador ?? ''),
            'ruc'       => (string) ($info->identificacionComprador ?? ''),
            'direccion' => (string) ($info->direccionComprador ?? ''),
        ];

        // ── Detalles ──────────────────────────────────────────────────────────
        $detalles = [];
        // El nodo de detalle puede ser <detalle> (factura) o venir bajo <detalles>.
        $listaDet = $xml->detalles->detalle ?? [];
        foreach ($listaDet as $d) {
            $impuestos = [];
            if (isset($d->impuestos->impuesto)) {
                foreach ($d->impuestos->impuesto as $imp) {
                    $impuestos[] = [
                        'codigo_impuesto'   => (string) $imp->codigo,
                        'codigo_porcentaje' => (string) $imp->codigoPorcentaje,
                        'tarifa'            => (float) $imp->tarifa,
                        'base_imponible'    => (float) $imp->baseImponible,
                        'valor'             => (float) $imp->valor,
                    ];
                }
            }
            $detalles[] = [
                'codigo_principal'          => (string) ($d->codigoPrincipal ?? ''),
                'descripcion'               => (string) ($d->descripcion ?? ''),
                'cantidad'                  => (float) ($d->cantidad ?? 0),
                'precio_unitario'           => (float) ($d->precioUnitario ?? 0),
                'descuento'                 => (float) ($d->descuento ?? 0),
                'precio_total_sin_impuesto' => (float) ($d->precioTotalSinImpuesto ?? 0),
                'impuestos'                 => $impuestos,
            ];
        }

        // ── Pagos ─────────────────────────────────────────────────────────────
        $pagos = [];
        if (isset($info->pagos->pago)) {
            foreach ($info->pagos->pago as $p) {
                $codFp = (string) $p->formaPago;
                $pagos[] = [
                    'forma_pago'        => $codFp,
                    'forma_pago_nombre' => self::FORMAS_PAGO_SRI[$codFp] ?? $codFp,
                    'total'             => (float) $p->total,
                    'plazo'             => (int) ($p->plazo ?? 0),
                    'unidad_tiempo'     => (string) ($p->unidadTiempo ?? 'dias'),
                ];
            }
        }

        // ── Información adicional ──────────────────────────────────────────────
        $infoAdicional = [];
        if (isset($xml->infoAdicional->campoAdicional)) {
            foreach ($xml->infoAdicional->campoAdicional as $campo) {
                $infoAdicional[] = [
                    'nombre' => (string) $campo['nombre'],
                    'valor'  => (string) $campo,
                ];
            }
        }

        return [
            'cabecera'      => $cabecera,
            'detalles'      => $detalles,
            'pagos'         => $pagos,
            'infoAdicional' => $infoAdicional,
            'comprador'     => $comprador,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE
    // ─────────────────────────────────────────────────────────────────────────

    public function getLastAsientoWarning(): ?string
    {
        return $this->lastAsientoWarning;
    }

    /**
     * Genera el asiento contable tras guardar una compra (fuera de la transacción principal).
     * Un fallo —p. ej. cuentas sin configurar— no revierte la compra: solo se registra el aviso.
     */
    private function generarAsientoTrasGuardar(int $idCompra, array $data): void
    {
        $this->lastAsientoWarning = null;
        try {
            $numDoc = ($data['establecimiento_prov'] ?? '') . '-'
                    . ($data['punto_emision_prov'] ?? '') . '-'
                    . ($data['secuencial_prov'] ?? '');
            $this->procesarAsientoContable($idCompra, $data, $numDoc);
        } catch (\Throwable $e) {
            error_log("[Compras] Asiento no generado para compra $idCompra: " . $e->getMessage());
            $this->lastAsientoWarning = $e->getMessage();
        }
    }

    /**
     * Punto de entrada del sincronizador (Estados Financieros) para compras sin asiento.
     */
    public function procesarAsientoContablePorSincronizacion(int $idCompra): void
    {
        $cabecera = $this->repository->getPorId($idCompra);
        if (!$cabecera) return;
        $numDoc = ($cabecera['establecimiento_prov'] ?? '') . '-'
                . ($cabecera['punto_emision_prov'] ?? '') . '-'
                . ($cabecera['secuencial_prov'] ?? '');
        $this->procesarAsientoContable($idCompra, $cabecera, $numDoc);
    }

    /**
     * Arma (vía AsientoBuilderService, concepto 'adquisiciones_compras') y persiste el asiento
     * de una compra. Idempotente: si ya existe asiento para esta compra, lo actualiza.
     * El enrutamiento inventariable→Inventario / resto→Gasto y la dirección (factura vs NC)
     * los resuelve el builder, evitando duplicar costo/gasto con el costeo de la venta.
     */
    public function procesarAsientoContable(int $idCompra, array $data, string $numDoc): void
    {
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? $data['created_by'] ?? $_SESSION['id_usuario'] ?? 0);
        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        $proveedorNombre = $data['proveedor_nombre'] ?? 'Proveedor';

        // Siempre regenerar desde el builder con los valores actuales del documento.
        $data['id_compra'] = $idCompra;
        $builder = new \App\Services\modulos\AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoSugerido($idEmpresa, 'adquisiciones_compras', $data);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Compra # $numDoc",
                'documento_referencia' => "Compra # $numDoc",
                'id_entidad'           => (int)($data['id_proveedor'] ?? 0),
                'tipo_entidad'         => 'proveedor',
            ];
        }

        // Documento excluido (p. ej. retención) o sin cuentas configuradas: no se genera asiento.
        if (empty($detalles)) {
            return;
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('compra', $idCompra, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fechaEmision,
            'tipo_comprobante'     => 'compras',
            'numero_comprobante'   => '',
            'concepto'             => "Compra # " . $numDoc . " - Proveedor: " . $proveedorNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'compra',
            'id_referencia_origen' => $idCompra,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idCompra, $idAsientoGenerado);
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Compra no encontrada.');
        }

        // Las compras migradas son de solo lectura (no se editan).
        if ($this->repository->esMigrado($id, $idEmpresa)) {
            throw new \Exception('Esta compra proviene de una migración y no puede editarse.');
        }

        // Factura de Reembolso recibida (codDocReembolso=41): el sustento tributario
        // SIEMPRE es código 08, sin importar lo que el usuario intente cambiar en el
        // formulario (el JS ya bloquea el selector; esto es el refuerzo del servidor).
        if ((string) ($cabecera['cod_doc_reembolso'] ?? '') === '41') {
            $idSustento08 = $this->getSustentoIdByCodigo('08');
            if ($idSustento08) {
                $data['id_sustento_tributario'] = $idSustento08;
            }
        }

        $this->rules->validar($data);
        $this->verificarSecuencialDuplicado($data, $id);
        
        // Validar tanto la fecha original como la nueva
        $this->periodosService->validarFechaPermitida(
            $cabecera['fecha_emision'], 
            $idEmpresa,
            'No se puede modificar el registro porque el periodo contable original está cerrado.'
        );
        $this->validarPeriodo($data, 'No se puede guardar el registro en la fecha seleccionada porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        
        try {
            if ($managed) $db->beginTransaction();

            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $data = $this->calcularTotales($data);
            
            // 1. Actualizar cabecera. Si falla, el catch capturará el error REAL.
            $this->repository->updateCabecera($id, $data);

            // 2. Procesar el resto solo si la cabecera fue exitosa
            $this->sincronizarDetalles($id, $data['detalles'] ?? []);

            $this->repository->deletePagos($id);
            $this->guardarPagos($id, $data['pagos'] ?? []);

            $this->repository->deleteInfoAdicional($id);
            $this->guardarAdicionales($id, $data['adicionales'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'MODIFICAR', 'compras_cabecera', $id,
                $cabecera, ['total' => $data['importe_total'] ?? 0]
            );

            $this->sincronizarCasilleros($id, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->relanzarSiDuplicado($e);
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la compra ya guardada.
        $this->generarAsientoTrasGuardar($id, $data);
        return $id;
    }

    public function eliminar(int $id, int $idUsuario, int $idEmpresa): bool
    {
        $compra = $this->repository->getPorId($id, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }

        // Validar periodo contable antes de eliminar
        $this->periodosService->validarFechaPermitida(
            $compra['fecha_emision'], 
            $idEmpresa,
            'No se puede eliminar el registro porque el periodo contable está cerrado.'
        );

        // Validar si tiene retenciones asociadas
        $retRepo = new \App\repositories\modulos\RetencionCompraRepository();
        if ($retRepo->existeRetencionParaCompra($id, $idEmpresa)) {
            throw new \Exception("No se puede eliminar la compra porque tiene una retención asociada. Debe eliminar la retención primero.");
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            // NUEVA REGLA: Anular automáticamente cualquier Egreso asociado que no esté anulado aún
            $egresoRepo   = new \App\repositories\modulos\EgresoRepository();
            $egresoRules  = new \App\Rules\modulos\EgresoRules();
            $egresoService = new \App\Services\modulos\EgresoService($egresoRepo, $egresoRules, $this->logService);

            $egresosIds = $this->repository->getEgresosAsociados($id, $idEmpresa);
            foreach ($egresosIds as $egresoId) {
                $egresoService->anular($egresoId, $idEmpresa, $idUsuario);
            }

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ELIMINAR', 'compras_cabecera', $id,
                ['id' => $id], null
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'compras', $id);

            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function getSustentoIdByCodigo(string $codigo): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM sustento_tributario WHERE codigo = ? AND status = 1 LIMIT 1");
        $st->execute([$codigo]);
        $res = $st->fetch();
        return $res ? (int) $res['id'] : null;
    }

    /**
     * Sincroniza el detalle de la compra con lo que vino del formulario, PRESERVANDO el
     * id de las líneas existentes (UPDATE en su sitio en vez de borrar todo y reinsertar).
     * Necesario porque inventario_kardex.referencia_id apunta a compras_detalle.id: si el
     * id se regenerara en cada guardado, getInventarioStatusAjax "olvidaría" qué ya se
     * envió a inventario y dejaría reenviar cantidades ya procesadas o quitar la
     * vinculación de un producto que ya tiene inventario registrado.
     *
     * Reglas:
     *  - Línea nueva (sin id, o con id que ya no existe en esta compra): se inserta.
     *  - Línea existente: se actualiza EN SU SITIO (mismo id).
     *  - Línea existente con cantidad ya enviada a inventario: no se puede reducir la
     *    cantidad por debajo de lo ya enviado, ni cambiar el producto vinculado.
     *  - Línea que existía y ya no viene en $detalles (el usuario la quitó del form):
     *    se elimina si no tiene nada procesado; si tiene algo procesado, bloquea todo
     *    el guardado (debe usarse un Retorno de Compra, no borrar la línea).
     */
    private function sincronizarDetalles(int $idCompra, array $detalles): void
    {
        $actualesPorId = [];
        foreach ($this->repository->getDetalles($idCompra) as $a) {
            $actualesPorId[(int) $a['id']] = $a;
        }
        $procesado = $this->repository->getCantidadProcesadaPorDetalle($idCompra);

        $idsRecibidos = [];

        foreach ($detalles as $det) {
            $idExistente = !empty($det['id']) ? (int) $det['id'] : 0;
            $esExistente = $idExistente > 0 && isset($actualesPorId[$idExistente]);

            $det['precio_total_sin_impuesto'] = (float) ($det['precio_total_sin_impuesto']
                ?? ((float) ($det['cantidad'] ?? 1) * (float) ($det['precio_unitario'] ?? 0) - (float) ($det['descuento'] ?? 0)));

            if ($esExistente) {
                $idsRecibidos[] = $idExistente;
                $cantProcesada = (float) ($procesado[$idExistente] ?? 0.0);

                if ($cantProcesada > 0) {
                    $nuevaCantidad = (float) ($det['cantidad'] ?? 0);
                    if ($nuevaCantidad < $cantProcesada - 0.0001) {
                        throw new \Exception(sprintf(
                            'No se puede reducir la cantidad de "%s" a %s: ya se enviaron %s a inventario.',
                            $det['descripcion'] ?? '', $nuevaCantidad, $cantProcesada
                        ));
                    }

                    $idProductoActual = !empty($actualesPorId[$idExistente]['id_producto']) ? (int) $actualesPorId[$idExistente]['id_producto'] : null;
                    $idProductoNuevo  = !empty($det['id_producto']) ? (int) $det['id_producto'] : null;
                    if ($idProductoNuevo !== $idProductoActual) {
                        throw new \Exception(sprintf(
                            'No se puede cambiar el producto vinculado de "%s": ya tiene %s enviado a inventario.',
                            $det['descripcion'] ?? '', $cantProcesada
                        ));
                    }
                }

                $det['id'] = $idExistente;
                $this->repository->updateDetalle($det);

                $this->repository->deleteImpuestosDeDetalle($idExistente);
                foreach ($det['impuestos'] ?? [] as $imp) {
                    $imp['id_compra_detalle'] = $idExistente;
                    $this->repository->insertImpuesto($imp);
                }
            } else {
                $det['id_compra'] = $idCompra;
                $idDetalle = $this->repository->insertDetalle($det);
                foreach ($det['impuestos'] ?? [] as $imp) {
                    $imp['id_compra_detalle'] = $idDetalle;
                    $this->repository->insertImpuesto($imp);
                }
            }
        }

        // Líneas que existían y ya no vinieron: el usuario las quitó del formulario.
        $idsAEliminar = array_diff(array_keys($actualesPorId), $idsRecibidos);
        foreach ($idsAEliminar as $idElim) {
            $cantProcesada = (float) ($procesado[$idElim] ?? 0.0);
            if ($cantProcesada > 0) {
                $desc = $actualesPorId[$idElim]['descripcion'] ?? '';
                throw new \Exception(sprintf(
                    'No se puede quitar la línea "%s": ya tiene %s enviado a inventario.',
                    $desc, $cantProcesada
                ));
            }
        }
        if (!empty($idsAEliminar)) {
            $this->repository->deleteDetallesPorId($idsAEliminar);
        }
    }

    private function guardarPagos(int $idCompra, array $pagos): void
    {
        foreach ($pagos as $pago) {
            $pago['id_compra'] = $idCompra;
            $this->repository->insertPago($pago);
        }
    }

    private function guardarAdicionales(int $idCompra, array $adicionales): void
    {
        foreach ($adicionales as $campo => $valor) {
            if ($valor !== null && $valor !== '') {
                $this->repository->insertInfoAdicional([
                    'id_compra' => $idCompra,
                    'nombre'    => $campo,
                    'valor'     => (string)$valor
                ]);
            }
        }
    }

    private function calcularTotales(array $data): array
    {
        $subtotal = 0;
        $descuento = 0;
        $total = 0;

        foreach ($data['detalles'] ?? [] as $det) {
            $cant = (float)($det['cantidad'] ?? 0);
            $prec = (float)($det['precio_unitario'] ?? 0);
            $desc = (float)($det['descuento'] ?? 0);
            
            $sub = $cant * $prec;
            $subtotal += $sub;
            $descuento += $desc;

            // Sumar impuestos del detalle
            if (!empty($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    $total += (float)($imp['valor'] ?? 0);
                }
            }
        }

        $data['total_sin_impuestos'] = $subtotal - $descuento;
        $data['total_descuento']     = $descuento;
        $data['importe_total']       = $data['total_sin_impuestos'] + ($total) + (float)($data['propina'] ?? 0);

        return $data;
    }

    private function verificarSecuencialDuplicado(array $data, ?int $excludeId = null): void
    {
        $existe = $this->repository->existeSecuencial(
            (int)$data['id_empresa'],
            (int)$data['id_proveedor'],
            $data['establecimiento_prov'] ?? '',
            $data['punto_emision_prov'] ?? '',
            $data['secuencial_prov'] ?? '',
            $data['tipo_comprobante'] ?? '01',
            $excludeId
        );

        if ($existe) {
            throw new \Exception('Ya existe una compra registrada con ese número de comprobante para este proveedor.');
        }

        // El numero_autorizacion solo es único por documento en comprobantes ELECTRÓNICOS
        // (es la clave de acceso, 49 dígitos). En comprobantes FÍSICOS, el SRI otorga UN
        // único número de autorización (10 dígitos) para todo un RANGO de secuenciales
        // (una chequera completa) — por diseño varias compras físicas del mismo proveedor
        // comparten el mismo numero_autorizacion con distinto secuencial_prov, y eso es
        // válido. Ahí el duplicado real ya lo cubre existeSecuencial() arriba. Mismo
        // criterio que el índice uq_compras_numaut_activo (ver
        // database/compras_numaut_unico_solo_electronicas.sql): solo aplica con 49 dígitos.
        $numAutorizacion = trim((string)($data['numero_autorizacion'] ?? ''));
        $esClaveElectronica = strlen(preg_replace('/\D/', '', $numAutorizacion)) === 49;
        if ($numAutorizacion !== '' && $esClaveElectronica) {
            $existeAutorizacion = $this->repository->existeNumeroAutorizacion(
                (int)$data['id_empresa'],
                $numAutorizacion,
                $excludeId
            );

            if ($existeAutorizacion) {
                throw new \Exception('Ya existe una compra registrada con ese número de autorización/clave de acceso.');
            }
        }
    }

    /**
     * Traduce la violación del índice único uq_compras_numaut_activo (23505) a un
     * mensaje amigable. Red de seguridad para la carrera entre el chequeo previo
     * (verificarSecuencialDuplicado) y el INSERT/UPDATE real.
     */
    private function relanzarSiDuplicado(\Throwable $e): void
    {
        $msg = $e->getMessage();
        if ($e->getCode() === '23505'
            || stripos($msg, 'uq_compras_numaut_activo') !== false
            || stripos($msg, 'duplicate key') !== false
            || stripos($msg, 'llave duplicada') !== false) {
            throw new \Exception('Ya existe una compra registrada con ese número de autorización/clave de acceso.');
        }
    }

    private function validarPeriodo(array $data, ?string $mensaje = null): void
    {
        $fecha = $data['fecha_emision'] ?? null;
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        
        if ($fecha && $idEmpresa) {
            $this->periodosService->validarFechaPermitida($fecha, $idEmpresa, $mensaje);
        }
    }

    public function sincronizarCasilleros(int $idCompra, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;
        
        if (!$data) {
            $cabecera = $this->repository->getPorId($idCompra);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
            
            // Get details and taxes if we fetched from DB
            $data['detalles'] = $this->repository->getDetalles($idCompra);
            foreach ($data['detalles'] as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        // 'deducible' determines the grouping key. Default to 'declaracion_iva' if empty.
        $deducible = $data['deducible'] ?? 'declaracion_iva';
        if ($deducible === '') $deducible = 'declaracion_iva';
        
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'compras', $idCompra);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);

        // Para compras usamos 'factura_compra'
        $keyDocumento = 'factura_compra';
        // Podríamos en el futuro añadir factura_compra_no_deducible si existiera en configDec.
        if (isset($data['tipo_comprobante']) && $data['tipo_comprobante'] === '02') {
             $keyDocumento = 'nota_venta_compra';
        }

        if (!$configDec || !isset($configDec[$keyDocumento])) return;
        $confCompras = $configDec[$keyDocumento];

        $tarifaMap = $decIvaRepo->getMapaTarifasIva();
        $detalles = $data['detalles'] ?? [];

        foreach ($detalles as $det) {
            $desc = !empty($det['producto_nombre']) ? $det['producto_nombre'] : (!empty($det['descripcion']) ? $det['descripcion'] : 'Sin concepto');
            $concepto = substr(trim($desc), 0, 255);
            $impuestos = $det['impuestos'] ?? [];
            foreach ($impuestos as $imp) {
                // Solo IVA (codigo_impuesto = 2)
                if ((int)$imp['codigo_impuesto'] !== 2) continue;

                $codigoPorcentaje = (string)($imp['codigo_porcentaje'] ?? '');
                $tarifaKey = $tarifaMap[$codigoPorcentaje] ?? '';
                if (!$tarifaKey || !isset($confCompras[$tarifaKey])) continue;

                $c = $confCompras[$tarifaKey];
                $bruto = $c['bruto'] ?? '';
                $neto = $c['neto'] ?? '';
                $impC = $c['impuesto'] ?? '';

                $base = (float)($imp['base_imponible'] ?? 0);
                $valorImp = (float)($imp['valor'] ?? 0);

                if ($bruto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($neto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($impC !== '' && $valorImp > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => $valorImp, 'concepto' => $concepto . ' (IVA)'
                    ]);
                }
            }
        }
    }
}
