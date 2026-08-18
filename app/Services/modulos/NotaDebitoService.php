<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\NotaDebitoRepository;
use App\Rules\modulos\NotaDebitoRules;
use App\Services\LogSistemaService;
use App\Services\ClaveAccesoService;
use App\Services\Xml\XmlNotaDebitoService;
use App\core\Database;
use Exception;

class NotaDebitoService
{
    private $repository;
    private $rules;
    private $logService;

    public function __construct(NotaDebitoRepository $repository, NotaDebitoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        // Normalizar espacios en la razón de cada motivo (texto libre): colapsa espacios
        // dobles y saltos de línea a uno solo, y recorta los extremos.
        foreach ($data['motivos'] ?? [] as &$mot) {
            if (!empty($mot['razon'])) {
                $mot['razon'] = trim(preg_replace('/\s+/u', ' ', (string) $mot['razon']));
            }
        }
        unset($mot);

        // Validar que la suma de ND no exceda un margen razonable del total de la factura
        // (a diferencia de la NC, la ND no "consume" el saldo de la factura, solo lo aumenta;
        // se valida únicamente que la factura exista y esté autorizada).
        $numDocModificado = $data['num_doc_modificado'] ?? '';
        if (!empty($numDocModificado)) {
            $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $factura = $facturaRepo->getPorNumeroCompleto($numDocModificado, (int)$data['id_empresa']);
            if ($factura && ($factura['estado'] ?? '') !== 'autorizado') {
                throw new Exception("Solo se pueden generar notas de débito para facturas en estado 'autorizado'.");
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $empresaConfig = $data['empresa_config'] ?? [];
            $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');

            if (!empty($empresaConfig['ruc'])
                && !empty($data['establecimiento'])
                && !empty($data['punto_emision'])
                && !empty($data['secuencial'])
            ) {
                $data['clave_acceso'] = ClaveAccesoService::generar(
                    (string)($data['fecha_emision'] ?? date('Y-m-d')),
                    ClaveAccesoService::NOTA_DEBITO,
                    (string)$empresaConfig['ruc'],
                    (string)($empresaConfig['tipo_ambiente'] ?? '1'),
                    (string)$data['establecimiento'],
                    (string)$data['punto_emision'],
                    (string)$data['secuencial']
                );
            }

            $idND = $this->repository->insertCabecera($data);

            foreach ($data['motivos'] as $mot) {
                $this->repository->insertMotivo([
                    'id_nota_debito' => $idND,
                    'razon'          => $mot['razon'],
                    'valor'          => $mot['valor'],
                ]);
            }

            foreach ($data['impuestos'] ?? [] as $imp) {
                $this->repository->insertImpuesto([
                    'id_nota_debito'    => $idND,
                    'codigo_impuesto'   => $imp['codigo_impuesto'],
                    'codigo_porcentaje' => $imp['codigo_porcentaje'],
                    'tarifa'            => $imp['tarifa'],
                    'base_imponible'    => $imp['base_imponible'],
                    'valor'             => $imp['valor'],
                ]);
            }

            foreach ($data['pagos'] ?? [] as $pago) {
                $this->repository->insertPago([
                    'id_nota_debito' => $idND,
                    'forma_pago'     => $pago['forma_pago'],
                    'total'          => $pago['total'],
                    'plazo'          => $pago['plazo'] ?? 0,
                    'unidad_tiempo'  => $pago['unidad_tiempo'] ?? 'dias',
                ]);
            }

            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'crear',
                'nota_debito_cabecera',
                $idND,
                null,
                ['secuencial' => $data['secuencial']]
            );

            $this->sincronizarCasilleros($idND, $data);

            $db->commit();

            $infoAdicional = is_array($data['info_adicional'] ?? null) ? $data['info_adicional'] : [];
            $infoAdicional = \App\Helpers\SriProveedorHelper::conRucProveedor($infoAdicional);
            $this->guardarInfoAdicional($idND, $infoAdicional);
            $this->generarYGuardarXml($idND, $data['empresa_config'] ?? []);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            if (($data['estado'] ?? 'borrador') === 'autorizado') {
                $this->procesarAsientoContable($idND, $data);
            }
        } catch (\Throwable $eAs) {
            error_log("[NotaDebito] Asiento no generado para ND $idND: " . $eAs->getMessage());
        }
        return $idND;
    }

    public function procesarAsientoContablePorSincronizacion(int $idNotaDebito): void
    {
        $nd = $this->repository->getPorId($idNotaDebito);
        if (!$nd) return;
        $this->procesarAsientoContable($idNotaDebito, $nd);
    }

    /**
     * Arma (vía AsientoBuilderService::generarAsientoNotaDebitoVenta) y persiste
     * el asiento de una nota de débito de venta. Idempotente.
     */
    public function procesarAsientoContable(int $idNotaDebito, array $data): void
    {
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? $data['created_by'] ?? $_SESSION['id_usuario'] ?? 0);
        $fecha = $data['fecha_emision'] ?? date('Y-m-d');
        $numND = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');
        $clienteNombre = $data['cliente_nombre'] ?? 'Cliente';

        $builder = new \App\Services\modulos\AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoNotaDebitoVenta($idEmpresa, $idNotaDebito);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Nota de débito # $numND",
                'documento_referencia' => "Nota de débito # $numND",
                'id_entidad'           => (int)($data['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles)) {
            return;
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('nota_debito', $idNotaDebito, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'ventas',
            'numero_comprobante'   => '',
            'concepto'             => "Nota de débito # " . $numND . " - Cliente: " . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'nota_debito',
            'id_referencia_origen' => $idNotaDebito,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idNotaDebito, $idAsientoGenerado);
    }

    public function actualizar(int $id, array $data): int
    {
        $this->rules->validar($data);

        // Normalizar espacios en la razón de cada motivo (texto libre): colapsa espacios
        // dobles y saltos de línea a uno solo, y recorta los extremos.
        foreach ($data['motivos'] ?? [] as &$mot) {
            if (!empty($mot['razon'])) {
                $mot['razon'] = trim(preg_replace('/\s+/u', ' ', (string) $mot['razon']));
            }
        }
        unset($mot);

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $ndOriginal = $this->repository->getPorId($id);
            if (!$ndOriginal) {
                throw new Exception("Nota de Débito no encontrada.");
            }

            if ($ndOriginal['estado'] !== 'borrador') {
                throw new Exception("Solo se pueden editar Notas de Débito en estado borrador.");
            }

            $empresaConfig = $data['empresa_config'] ?? [];
            if (!empty($empresaConfig['ruc'])
                && !empty($data['establecimiento'])
                && !empty($data['punto_emision'])
                && !empty($data['secuencial'])
            ) {
                $codigoNumerico = ClaveAccesoService::extraerCodigoNumerico($ndOriginal['clave_acceso'] ?? '');
                $data['clave_acceso'] = ClaveAccesoService::generar(
                    (string)($data['fecha_emision'] ?? date('Y-m-d')),
                    ClaveAccesoService::NOTA_DEBITO,
                    (string)$empresaConfig['ruc'],
                    (string)($empresaConfig['tipo_ambiente'] ?? '1'),
                    (string)$data['establecimiento'],
                    (string)$data['punto_emision'],
                    (string)$data['secuencial'],
                    '1',
                    $codigoNumerico
                );
            }

            $this->repository->updateCabecera($id, $data);

            $this->repository->deleteMotivos($id);
            foreach ($data['motivos'] as $mot) {
                $this->repository->insertMotivo([
                    'id_nota_debito' => $id,
                    'razon'          => $mot['razon'],
                    'valor'          => $mot['valor'],
                ]);
            }

            $this->repository->deleteImpuestos($id);
            foreach ($data['impuestos'] ?? [] as $imp) {
                $this->repository->insertImpuesto([
                    'id_nota_debito'    => $id,
                    'codigo_impuesto'   => $imp['codigo_impuesto'],
                    'codigo_porcentaje' => $imp['codigo_porcentaje'],
                    'tarifa'            => $imp['tarifa'],
                    'base_imponible'    => $imp['base_imponible'],
                    'valor'             => $imp['valor'],
                ]);
            }

            $this->repository->deletePagos($id);
            foreach ($data['pagos'] ?? [] as $pago) {
                $this->repository->insertPago([
                    'id_nota_debito' => $id,
                    'forma_pago'     => $pago['forma_pago'],
                    'total'          => $pago['total'],
                    'plazo'          => $pago['plazo'] ?? 0,
                    'unidad_tiempo'  => $pago['unidad_tiempo'] ?? 'dias',
                ]);
            }

            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'actualizar',
                'nota_debito_cabecera',
                $id,
                $ndOriginal,
                $data
            );

            $this->sincronizarCasilleros($id, $data);

            $db->commit();
            $this->guardarInfoAdicional($id, $data['info_adicional'] ?? []);
            $this->generarYGuardarXml($id, $data['empresa_config'] ?? []);
            return $id;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function guardarInfoAdicional(int $idND, $infoAdicional): void
    {
        try {
            $this->repository->deleteInfoAdicional($idND);
            if (!empty($infoAdicional) && is_array($infoAdicional)) {
                foreach ($infoAdicional as $ia) {
                    $this->repository->insertInfoAdicional([
                        'id_nota_debito' => $idND,
                        'nombre'         => $ia['nombre'] ?? '',
                        'valor'          => $ia['valor'] ?? '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[NotaDebito] Info adicional no guardada para ND ' . $idND . ': ' . $e->getMessage());
        }
    }

    /**
     * $esSuperAdmin (nivel 3) permite eliminar una ND que NO está en borrador
     * (autorizada o anulada) — misma excepción que FacturaVentaService::eliminar(),
     * para el caso de un documento cargado por error que no se quiere conservar.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario, bool $esSuperAdmin = false): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $nd = $this->repository->getPorId($id);
            if (!$nd || (int)$nd['id_empresa'] !== $idEmpresa) {
                throw new Exception("Nota de Débito no encontrada.");
            }

            $estadoActual = $nd['estado'] ?? '';
            if ($estadoActual !== 'borrador' && !$esSuperAdmin) {
                throw new Exception("Solo se pueden eliminar Notas de Débito en estado borrador.");
            }

            // Igual que FacturaVentaService::eliminar(): si sigue AUTORIZADA en el SRI, no se
            // puede eliminar aquí — primero hay que anularla en el portal del SRI.
            $claveAcceso = trim((string) ($nd['clave_acceso'] ?? ''));
            if ($estadoActual === 'autorizado' && $claveAcceso !== '') {
                $tipoAmbiente = (string) ($nd['tipo_ambiente'] ?? '1');
                $envioSri = new \App\Services\Sri\SriEnvioService();
                $consulta = $envioSri->verificarAutorizacion($claveAcceso, $tipoAmbiente);
                $estadoSri = strtoupper($consulta['estado'] ?? '');
                if ($estadoSri === 'AUTORIZADO') {
                    throw new Exception(
                        'No se puede eliminar: el documento sigue AUTORIZADO en el SRI. ' .
                        'Primero debe anularlo en el portal del SRI; cuando deje de estar autorizado podrá eliminarlo aquí.'
                    );
                }
            }

            // Limpiar casilleros de declaración 104 (igual que anular()) — solo aplica si
            // la ND llegó a estar autorizada y a marcar algún casillero.
            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'notas de debito', $id);

            $idAsientoNd = (int)($nd['id_asiento_contable'] ?? 0);
            if ($idAsientoNd > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->logService
                );
                try {
                    $asientoService->anular($idAsientoNd, $idEmpresa, $idUsuario);
                } catch (\Throwable $eA) {
                    if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                        throw $eA;
                    }
                }
            }

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                $estadoActual !== 'borrador' ? 'eliminar_forzado_superadmin' : 'eliminar',
                'nota_debito_cabecera',
                $id,
                $nd,
                ['estado_previo' => $estadoActual]
            );

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $nd = $this->repository->getPorId($id);
            if (!$nd || (int)$nd['id_empresa'] !== $idEmpresa) {
                throw new Exception("Nota de Débito no encontrada.");
            }

            if ($nd['estado'] === 'anulado') {
                throw new Exception("La Nota de Débito ya se encuentra anulada.");
            }

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'notas de debito', $id);

            $idAsientoNd = (int)($nd['id_asiento_contable'] ?? 0);
            if ($idAsientoNd > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->logService
                );
                try {
                    $asientoService->anular($idAsientoNd, $idEmpresa, $idUsuario);
                } catch (\Throwable $eA) {
                    if (stripos($eA->getMessage(), 'ya se encuentra anulado') === false) {
                        throw $eA;
                    }
                }
            }

            $this->repository->updateEstado($id, 'anulado');

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'anular',
                'nota_debito_cabecera',
                $id,
                $nd,
                ['estado' => 'anulado']
            );

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    private function generarYGuardarXml(int $idND, array $empresaConfig): void
    {
        try {
            $cabecera = $this->repository->getPorId($idND);
            if (!$cabecera) return;

            $motivos   = $this->repository->getMotivos($idND);
            $impuestos = $this->repository->getImpuestos($idND);
            $pagos     = $this->repository->getPagos($idND);
            $infoAdicional = $this->repository->getInfoAdicional($idND);

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId((int)$cabecera['id_empresa']) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos((int)$cabecera['id_empresa']) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            $xml = (new XmlNotaDebitoService())->generar($cabecera, $motivos, $impuestos, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);
            $this->repository->updateDetalleXml($idND, $xml);
        } catch (\Throwable $e) {
            error_log('[ND] Error generando XML para ND #' . $idND . ': ' . $e->getMessage());
        }
    }

    /**
     * Sincroniza los casilleros de la declaración de IVA (104) para la ND.
     * A diferencia de NC, los impuestos de la ND están a nivel de cabecera
     * (no por línea de producto), así que se recorren directo.
     * Valores POSITIVOS: una ND aumenta la sumatoria de ventas/IVA del período.
     */
    public function sincronizarCasilleros(int $idND, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;

        if (!$data) {
            $cabecera = $this->repository->getPorId($idND);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        $impuestos = $data['impuestos'] ?? $this->repository->getImpuestos($idND);

        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'notas de debito', $idND);

        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);
        if (!$configDec || !isset($configDec['nota_debito'])) return;
        $confND = $configDec['nota_debito'];

        $tarifaMap = $decIvaRepo->getMapaTarifasIva();

        foreach ($impuestos as $imp) {
            if ((int)($imp['codigo_impuesto'] ?? 0) !== 2) continue;

            $codigoPorcentaje = (string)($imp['codigo_porcentaje'] ?? '');
            $tarifaKey = $tarifaMap[$codigoPorcentaje] ?? '';
            if (!$tarifaKey || !isset($confND[$tarifaKey])) continue;

            $c = $confND[$tarifaKey];
            $bruto = $c['bruto'] ?? '';
            $neto = $c['neto'] ?? '';
            $impC = $c['impuesto'] ?? '';

            $base = (float)($imp['base_imponible'] ?? 0);
            $valorImp = (float)($imp['valor'] ?? 0);
            $concepto = 'Nota de débito';

            if ($bruto !== '' && $base > 0) {
                $decIvaRepo->insertarCasilleroDeclaracion([
                    'id_empresa' => $idEmpresa, 'origen' => 'notas de debito', 'id_origen' => $idND,
                    'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                ]);
            }
            if ($neto !== '' && $base > 0) {
                $decIvaRepo->insertarCasilleroDeclaracion([
                    'id_empresa' => $idEmpresa, 'origen' => 'notas de debito', 'id_origen' => $idND,
                    'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => $base, 'concepto' => $concepto . ' (Base)'
                ]);
            }
            if ($impC !== '' && $valorImp > 0) {
                $decIvaRepo->insertarCasilleroDeclaracion([
                    'id_empresa' => $idEmpresa, 'origen' => 'notas de debito', 'id_origen' => $idND,
                    'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => $valorImp, 'concepto' => $concepto . ' (IVA)'
                ]);
            }
        }
    }
}
