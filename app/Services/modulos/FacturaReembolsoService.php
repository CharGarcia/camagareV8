<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FacturaReembolsoRepository;
use App\Rules\modulos\FacturaReembolsoRules;
use App\Services\LogSistemaService;
use App\Services\ClaveAccesoService;
use App\core\Database;
use Exception;

class FacturaReembolsoService
{
    private FacturaReembolsoRepository $repository;
    private FacturaReembolsoRules $rules;
    private LogSistemaService $logService;

    public function __construct(FacturaReembolsoRepository $repository, FacturaReembolsoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    /**
     * Suma la base imponible y el impuesto de todos los terceros: son los 3
     * campos agregados obligatorios en infoFactura cuando codDocReembolso=41
     * (Ficha Técnica SRI): totalComprobantesReembolso = base + impuesto.
     */
    private function calcularTotalesReembolso(array $terceros): array
    {
        $base = 0.0;
        $impuesto = 0.0;
        foreach ($terceros as $t) {
            foreach ($t['impuestos'] ?? [] as $imp) {
                $base     += (float) ($imp['base_imponible'] ?? 0);
                $impuesto += (float) ($imp['valor'] ?? 0);
            }
        }
        $base     = round($base, 2);
        $impuesto = round($impuesto, 2);

        return [
            'total_base_imponible_reembolso' => $base,
            'total_impuesto_reembolso'       => $impuesto,
            'total_comprobantes_reembolso'   => round($base + $impuesto, 2),
        ];
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
            throw new Exception('El número de secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->validarSecuencial($data);

        $totales = $this->calcularTotalesReembolso($data['terceros']);
        $data    = array_merge($data, $totales);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresaConfig['tipo_emision']  ?? '1');

        // codDoc SRI sigue siendo Factura ('01'): el 41 es solo la clasificación
        // ATS de codDocReembolso, no un tipo de comprobante distinto.
        $data['clave_acceso'] = ClaveAccesoService::generar(
            (string) ($data['fecha_emision']   ?? ''),
            ClaveAccesoService::FACTURA_VENTA,
            (string) ($empresaConfig['ruc']    ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            $data['tipo_emision']
        );

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $idFR = $this->repository->insertCabecera($data);

            $this->guardarDetalles($idFR, $data['detalles']);
            $this->guardarTerceros($idFR, $data['terceros']);

            foreach ($data['pagos'] ?? [] as $p) {
                $this->repository->insertPago([
                    'id_factura_reembolso' => $idFR,
                    'forma_pago'           => $p['forma_pago'],
                    'total'                => $p['total'],
                    'plazo'                => $p['plazo'] ?? 0,
                    'unidad_tiempo'        => $p['unidad_tiempo'] ?? 'dias',
                ]);
            }

            $infoAdicional = is_array($data['info_adicional'] ?? null) ? $data['info_adicional'] : [];
            $infoAdicional = \App\Helpers\SriProveedorHelper::conRucProveedor($infoAdicional);
            foreach ($infoAdicional as $ia) {
                $this->repository->insertInfoAdicional([
                    'id_factura_reembolso' => $idFR,
                    'nombre'               => $ia['nombre'] ?? '',
                    'valor'                => $ia['valor']  ?? '',
                ]);
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR',
                'factura_reembolso_cabecera',
                $idFR,
                null,
                ['id_factura_reembolso' => $idFR, 'total' => $data['importe_total'] ?? 0]
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        $this->generarYGuardarXml($idFR, $data['empresa_config'] ?? []);

        return $idFR;
    }

    public function actualizar(int $id, array $data): int
    {
        $original = $this->repository->getPorId($id);
        if (!$original || (int) ($original['id_empresa'] ?? 0) !== (int) $data['id_empresa']) {
            throw new Exception('Factura de reembolso no encontrada.');
        }
        if (($original['estado'] ?? '') !== 'borrador') {
            throw new Exception('Solo se pueden modificar facturas de reembolso en estado borrador.');
        }

        $this->rules->validar($data);
        $this->validarSecuencial($data, $id);

        $totales = $this->calcularTotalesReembolso($data['terceros']);
        $data    = array_merge($data, $totales);

        $empresaConfig = $data['empresa_config'] ?? [];
        $data['tipo_ambiente'] = (string) ($empresaConfig['tipo_ambiente'] ?? '1');

        $codigoNumerico = ClaveAccesoService::extraerCodigoNumerico($original['clave_acceso'] ?? '');
        $data['clave_acceso'] = ClaveAccesoService::generar(
            (string) ($data['fecha_emision']  ?? ''),
            ClaveAccesoService::FACTURA_VENTA,
            (string) ($empresaConfig['ruc']   ?? ''),
            $data['tipo_ambiente'],
            (string) ($data['establecimiento'] ?? ''),
            (string) ($data['punto_emision']   ?? ''),
            (string) ($data['secuencial']      ?? ''),
            (string) ($empresaConfig['tipo_emision'] ?? '1'),
            $codigoNumerico
        );

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $this->repository->updateCabecera($id, $data);

            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles']);

            $this->repository->deleteTerceros($id);
            $this->guardarTerceros($id, $data['terceros']);

            $this->repository->deletePagos($id);
            foreach ($data['pagos'] ?? [] as $p) {
                $this->repository->insertPago([
                    'id_factura_reembolso' => $id,
                    'forma_pago'           => $p['forma_pago'],
                    'total'                => $p['total'],
                    'plazo'                => $p['plazo'] ?? 0,
                    'unidad_tiempo'        => $p['unidad_tiempo'] ?? 'dias',
                ]);
            }

            $this->repository->deleteInfoAdicional($id);
            $infoAdicional = is_array($data['info_adicional'] ?? null) ? $data['info_adicional'] : [];
            $infoAdicional = \App\Helpers\SriProveedorHelper::conRucProveedor($infoAdicional);
            foreach ($infoAdicional as $ia) {
                $this->repository->insertInfoAdicional([
                    'id_factura_reembolso' => $id,
                    'nombre'               => $ia['nombre'] ?? '',
                    'valor'                => $ia['valor']  ?? '',
                ]);
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'MODIFICAR',
                'factura_reembolso_cabecera',
                $id,
                $original,
                ['id_factura_reembolso' => $id, 'total' => $data['importe_total'] ?? 0]
            );

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        $this->generarYGuardarXml($id, $data['empresa_config'] ?? []);

        return $id;
    }

    private function guardarDetalles(int $idFR, array $detalles): void
    {
        foreach ($detalles as $d) {
            $idDetalle = $this->repository->insertDetalle([
                'id_factura_reembolso'      => $idFR,
                'descripcion'                => $d['descripcion'],
                'cantidad'                   => $d['cantidad'],
                'precio_unitario'            => $d['precio_unitario'],
                'descuento'                  => $d['descuento'] ?? 0,
                'precio_total_sin_impuesto'  => $d['precio_total_sin_impuesto'],
                'es_reembolso'               => $d['es_reembolso'] ?? true,
            ]);
            foreach ($d['impuestos'] ?? [] as $imp) {
                $this->repository->insertImpuestoDetalle([
                    'id_factura_reembolso_detalle' => $idDetalle,
                    'codigo_impuesto'               => $imp['codigo_impuesto'],
                    'codigo_porcentaje'              => $imp['codigo_porcentaje'],
                    'tarifa'                         => $imp['tarifa'],
                    'base_imponible'                 => $imp['base_imponible'],
                    'valor'                          => $imp['valor'],
                ]);
            }
        }
    }

    private function guardarTerceros(int $idFR, array $terceros): void
    {
        foreach ($terceros as $orden => $t) {
            $baseTotal = 0.0;
            $impuestoTotal = 0.0;
            foreach ($t['impuestos'] ?? [] as $imp) {
                $baseTotal     += (float) ($imp['base_imponible'] ?? 0);
                $impuestoTotal += (float) ($imp['valor'] ?? 0);
            }

            $idTercero = $this->repository->insertTercero([
                'id_factura_reembolso'                     => $idFR,
                'id_compra'                                 => $t['id_compra'] ?? null,
                'orden'                                      => $orden,
                'tipo_identificacion_proveedor_reembolso'    => $t['tipo_identificacion_proveedor_reembolso'],
                'identificacion_proveedor_reembolso'         => $t['identificacion_proveedor_reembolso'],
                'razon_social_proveedor_reembolso'           => $t['razon_social_proveedor_reembolso'] ?? null,
                'cod_pais_pago_proveedor_reembolso'          => $t['cod_pais_pago_proveedor_reembolso'] ?? null,
                'tipo_proveedor_reembolso'                   => $t['tipo_proveedor_reembolso'],
                'cod_doc_reembolso'                          => $t['cod_doc_reembolso'] ?? '01',
                'estab_doc_reembolso'                        => $t['estab_doc_reembolso'],
                'pto_emi_doc_reembolso'                      => $t['pto_emi_doc_reembolso'],
                'secuencial_doc_reembolso'                   => $t['secuencial_doc_reembolso'],
                'fecha_emision_doc_reembolso'                => $t['fecha_emision_doc_reembolso'],
                'numero_autorizacion_doc_reemb'              => $t['numero_autorizacion_doc_reemb'],
                'base_imponible_total'                       => round($baseTotal, 2),
                'impuesto_total'                              => round($impuestoTotal, 2),
            ]);

            foreach ($t['impuestos'] ?? [] as $imp) {
                $this->repository->insertImpuestoTercero([
                    'id_factura_reembolso_tercero' => $idTercero,
                    'codigo_impuesto'                => $imp['codigo_impuesto'],
                    'codigo_porcentaje'               => $imp['codigo_porcentaje'],
                    'tarifa'                          => $imp['tarifa'],
                    'base_imponible'                  => $imp['base_imponible'],
                    'valor'                           => $imp['valor'],
                ]);
            }
        }
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $res = $this->repository->getPorId($id);
        if (!$res || (int) $res['id_empresa'] !== $idEmpresa) return null;

        $res['detalles'] = $this->repository->getDetalles($id);
        foreach ($res['detalles'] as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);

        $res['terceros'] = $this->repository->getTerceros($id);
        foreach ($res['terceros'] as &$t) {
            $t['impuestos'] = $this->repository->getImpuestosTercero((int) $t['id']);
        }
        unset($t);

        $res['pagos']          = $this->repository->getPagos($id);
        $res['info_adicional'] = $this->repository->getInfoAdicional($id);

        return $res;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $fr = $this->repository->getPorId($id);
            if (!$fr || (int) $fr['id_empresa'] !== $idEmpresa) {
                throw new Exception('Factura de reembolso no encontrada.');
            }
            if (($fr['estado'] ?? '') !== 'borrador') {
                throw new Exception('Solo se pueden eliminar facturas de reembolso en estado borrador.');
            }

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'factura_reembolso_cabecera', $id, $fr, null);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $fr = $this->repository->getPorId($id);
            if (!$fr || (int) $fr['id_empresa'] !== $idEmpresa) {
                throw new Exception('Factura de reembolso no encontrada.');
            }
            if (($fr['estado'] ?? '') === 'anulado') {
                throw new Exception('La factura de reembolso ya está anulada.');
            }

            $idAsiento = (int) ($fr['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
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

            $this->repository->updateEstado($id, 'anulado');

            $this->logService->registrar($idUsuario, $idEmpresa, 'ANULAR', 'factura_reembolso_cabecera', $id, $fr, ['estado' => 'anulado']);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Arma y persiste el asiento contable de "cuenta puente" tras la
     * autorización SRI (se llama desde SriEnvioService::enviarFacturaReembolso,
     * no al guardar el borrador).
     */
    public function procesarAsientoContable(int $idFacturaReembolso, array $data): void
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $idUsuario = (int) ($data['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);
        $fecha = $data['fecha_emision'] ?? date('Y-m-d');
        $numero = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');
        $clienteNombre = $data['cliente_nombre'] ?? 'Cliente';

        $builder = new AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoFacturaReembolso($idEmpresa, $idFacturaReembolso);
        if (empty($detallesSugeridos)) {
            return;
        }

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Factura de reembolso # $numero",
                'documento_referencia' => "Factura de reembolso # $numero",
                'id_entidad'           => (int) ($data['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('factura_reembolso', $idFacturaReembolso, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'ventas',
            'numero_comprobante'   => '',
            'concepto'             => 'Factura de reembolso # ' . $numero . ' - Cliente: ' . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'factura_reembolso',
            'id_referencia_origen' => $idFacturaReembolso,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idFacturaReembolso, $idAsientoGenerado);
    }

    /**
     * Genera el XML (sin firmar) y lo persiste en detalle_xml. Se llama fuera
     * de la transacción principal; los errores se silencian para no revertir
     * la factura ya guardada (se puede regenerar más adelante).
     */
    private function generarYGuardarXml(int $idFR, array $empresaConfig): void
    {
        try {
            $cabecera = $this->repository->getPorId($idFR);
            if (!$cabecera) return;

            $detalles = $this->repository->getDetalles($idFR);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
            }
            unset($d);

            $terceros = $this->repository->getTerceros($idFR);
            foreach ($terceros as &$t) {
                $t['impuestos'] = $this->repository->getImpuestosTercero((int) $t['id']);
            }
            unset($t);

            $pagos         = $this->repository->getPagos($idFR);
            $infoAdicional = $this->repository->getInfoAdicional($idFR);

            $empresa = $empresaConfig;
            if (empty($empresa)) {
                $empresaModel = new \App\models\Empresa();
                $empresa = $empresaModel->getPorId((int) $cabecera['id_empresa']) ?? [];
            }

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos((int) $cabecera['id_empresa']) as $est) {
                        if ((int) $est['id'] === (int) $cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            $xmlService = new \App\Services\Xml\XmlFacturaReembolsoService();
            $xmlString  = $xmlService->generar($cabecera, $detalles, $terceros, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

            $this->repository->updateDetalleXml($idFR, $xmlString);
        } catch (\Throwable $e) {
            error_log('[FacturaReembolso] XML no generado para factura ' . $idFR . ': ' . $e->getMessage());
        }
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }
}
