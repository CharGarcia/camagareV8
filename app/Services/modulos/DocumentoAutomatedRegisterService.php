<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\ComprasRepository;
use App\repositories\modulos\LiquidacionCompraRepository;
use App\repositories\modulos\NotaCreditoRepository;
use App\repositories\modulos\NotaDebitoRepository;
use App\repositories\modulos\ProveedorRepository;
use App\repositories\modulos\RetencionCompraRepository;
use App\repositories\modulos\RetencionRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\DocumentoIgnoradoRepository;
use App\repositories\modulos\RetencionVentaRepository;
use App\Services\modulos\RetencionCompraService;
use App\Services\modulos\RetencionVentaService;
use App\Rules\modulos\RetencionCompraRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;
use SimpleXMLElement;
use PDO;

class DocumentoAutomatedRegisterService
{
    private ClienteRepository $clienteRepo;
    private ProveedorRepository $proveedorRepo;
    private FacturaVentaRepository $ventaRepo;
    private ComprasRepository $compraRepo;
    private LiquidacionCompraRepository $liquidacionRepo;
    private NotaCreditoRepository $ncRepo;
    private NotaDebitoRepository $ndRepo;
    private RetencionRepository $retRepo;
    private EmpresaRepository $empresaRepo;
    private PeriodosContablesService $periodosService;
    private RetencionCompraService $retencionService;
    private RetencionCompraRepository $retencionCompraRepo;
    private RetencionVentaService $retVentaService;
    private RetencionVentaRepository $retVentaRepo;
    private DocumentoIgnoradoRepository $ignoradoRepo;

    public function __construct()
    {
        $this->clienteRepo = new ClienteRepository();
        $this->proveedorRepo = new ProveedorRepository();
        $this->ventaRepo = new FacturaVentaRepository();
        $this->compraRepo = new ComprasRepository();
        $this->liquidacionRepo = new LiquidacionCompraRepository();
        $this->ncRepo = new NotaCreditoRepository();
        $this->ndRepo = new NotaDebitoRepository();
        $this->retRepo = new RetencionRepository();
        $this->empresaRepo = new EmpresaRepository();
        
        // Carga manual de dependencias
        $logService = new LogSistemaService();
        $periodosRepo = new \App\repositories\modulos\PeriodosContablesRepository();
        $periodosRules = new \App\Rules\modulos\PeriodosContablesRules($periodosRepo);
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $logService);

        $this->retencionCompraRepo = new RetencionCompraRepository();
        $retencionRules = new RetencionCompraRules($this->retencionCompraRepo);
        $this->retencionService = new RetencionCompraService($this->retencionCompraRepo, $retencionRules, $logService);
        
        $retVentaRepo = new RetencionVentaRepository();
        $retVentaRules = new \App\Rules\modulos\RetencionVentaRules();
        $this->retVentaRepo = $retVentaRepo;
        $this->retVentaService = new RetencionVentaService($retVentaRepo, $retVentaRules, $logService);

        $this->ignoradoRepo = new DocumentoIgnoradoRepository();
    }

    /**
     * Procesa y registra un comprobante electrónico basado en la lógica del SRI.
     */
    public function procesarYRegistrar(string $xmlString, int $idEmpresa, int $idUsuario): array
    {
        try {
            $xml = new SimpleXMLElement($xmlString);
            
            $debugMsg = "[" . date('Y-m-d H:i:s') . "] XML Root Detected: " . $xml->getName() . "\n";
            file_put_contents(MVC_ROOT . '/storage/logs/debug_sri.log', $debugMsg, FILE_APPEND);
            
            // Si el XML viene envuelto en etiquetas de autorización del SRI
            if (isset($xml->comprobante)) {
                $xml = new SimpleXMLElement((string)$xml->comprobante);
                $debugMsg = "[" . date('Y-m-d H:i:s') . "] XML Root After Unwrapped: " . $xml->getName() . "\n";
                file_put_contents(MVC_ROOT . '/storage/logs/debug_sri.log', $debugMsg, FILE_APPEND);
            }

            if (!isset($xml->infoTributaria)) {
                throw new Exception("Formato XML no válido (falta infoTributaria)");
            }

            $it = $xml->infoTributaria;
            $codDoc      = (string) $it->codDoc;
            $claveAcceso = (string) $it->claveAcceso;
            $rucEmisor   = trim((string) $it->ruc);

            // 0. Verificar si el documento está en la lista de ignorados
            if ($this->ignoradoRepo->existeClave($claveAcceso, $idEmpresa)) {
                return [
                    'ok' => false,
                    'error' => "Documento ignorado por el usuario (Lista Negra).",
                    'estado_registro' => 'IGNORADO',
                    'numero_documento' => (string)$it->estab . '-' . (string)$it->ptoEmi . '-' . (string)$it->secuencial,
                    'clave' => $claveAcceso
                ];
            }

            // Obtener RUC y ambiente activo de la empresa
            // Los XMLs del SRI siempre traen ambiente=2, pero en nuestro sistema
            // usamos el ambiente activo de la empresa para registrar y verificar duplicados.
            // Así, pruebas y producción son entornos separados: un documento registrado
            // en pruebas no bloquea su registro cuando la empresa cambia a producción.
            $empresaActual  = $this->empresaRepo->getEmisorConfig($idEmpresa);
            $ambienteEmpresa = (string)($empresaActual['tipo_ambiente'] ?? (string)$it->ambiente);
            $rucActual = preg_replace('/[^0-9]/', '', (string)$empresaActual['ruc']);
            $rucEmisor = preg_replace('/[^0-9]/', '', (string)$it->ruc);
            
            $debugMsg = "[" . date('Y-m-d H:i:s') . "] RUC Check - Actual: '$rucActual', Emisor: '$rucEmisor', codDoc: '$codDoc'\n";
            file_put_contents(MVC_ROOT . '/storage/logs/debug_sri.log', $debugMsg, FILE_APPEND);

            $esEmitidaPorMi = ($rucEmisor === $rucActual);
            $esRecibidaPorMi = false;
            $esGastoPersonal = false;

            // Identificar si soy el receptor si no soy el emisor
            if (!$esEmitidaPorMi) {
                $rucReceptor = '';
                $tipoIdReceptor = '';
                
                if ($codDoc === '01') {
                    $rucReceptor = trim((string)($xml->infoFactura->identificacionComprador ?? ''));
                    $tipoIdReceptor = trim((string)($xml->infoFactura->tipoIdentificacionComprador ?? ''));
                } elseif ($codDoc === '03') {
                    $rucReceptor = trim((string)($xml->infoLiquidacionCompra->identificacionProveedor ?? ''));
                    $tipoIdReceptor = trim((string)($xml->infoLiquidacionCompra->tipoIdentificacionProveedor ?? ''));
                } elseif ($codDoc === '04') {
                    $rucReceptor = trim((string)($xml->infoNotaCredito->identificacionComprador ?? ''));
                    $tipoIdReceptor = trim((string)($xml->infoNotaCredito->tipoIdentificacionComprador ?? ''));
                } elseif ($codDoc === '05') {
                    $rucReceptor = trim((string)($xml->infoNotaDebito->identificacionComprador ?? ''));
                    $tipoIdReceptor = trim((string)($xml->infoNotaDebito->tipoIdentificacionComprador ?? ''));
                } elseif ($codDoc === '07') {
                    $infoNode = isset($xml->infoRetencion) ? $xml->infoRetencion : $xml->infoCompRetencion;
                    $rucReceptor = trim((string)($infoNode->identificacionSujetoRetenido ?? ''));
                    $tipoIdReceptor = trim((string)($infoNode->tipoIdentificacionSujetoRetenido ?? ''));
                    
                    // Debug structure
                    $tags = [];
                    if (isset($infoNode)) {
                        foreach ($infoNode->children() as $child) {
                            $tags[] = $child->getName();
                        }
                    }
                    $debugMsg = "[" . date('Y-m-d H:i:s') . "] infoNode Tags (" . ($infoNode ? $infoNode->getName() : 'NULL') . "): " . implode(', ', $tags) . "\n";
                    file_put_contents(MVC_ROOT . '/storage/logs/debug_sri.log', $debugMsg, FILE_APPEND);
                }
                
                $rucReceptor = preg_replace('/[^0-9]/', '', $rucReceptor);
                
                if ($rucReceptor === $rucActual) {
                    $esRecibidaPorMi = true;
                } elseif ($tipoIdReceptor === '05' && strlen($rucReceptor) === 10) {
                    // Caso Gasto Personal: Cédula de 10 dígitos coincide con los 10 primeros del RUC
                    if ($rucReceptor === substr($rucActual, 0, 10)) {
                        $esRecibidaPorMi = true;
                        $esGastoPersonal = true;
                    }
                }
                
                $debugMsg = "[" . date('Y-m-d H:i:s') . "] RUC Check - Receptor: '$rucReceptor', esRecibidaPorMi: " . ($esRecibidaPorMi ? 'true' : 'false') . "\n";
                file_put_contents(MVC_ROOT . '/storage/logs/debug_sri.log', $debugMsg, FILE_APPEND);
            }

            if (!$esEmitidaPorMi && !$esRecibidaPorMi) {
                return [
                    'ok' => true, 
                    'mensaje' => "El documento no pertenece al RUC de la empresa ($rucActual).", 
                    'estado_registro' => 'EMITIDO A OTRO CONTRIBUYENTE'
                ];
            }

            // Extraer info común para el reporte
            $numDoc = (string)$it->estab . '-' . (string)$it->ptoEmi . '-' . (string)$it->secuencial;
            $tipos = ['01'=>'Factura','03'=>'Liquidación de Compra','04'=>'Nota de Crédito','05'=>'Nota de Débito','07'=>'Comprobante de Retención'];
            $tipoNombre = $tipos[$codDoc] ?? 'Documento';
            $emisor = (string)$it->razonSocial;
            
            $totalDoc = 0;
            if ($codDoc === '01') $totalDoc = (float)($xml->infoFactura->importeTotal ?? 0);
            elseif ($codDoc === '03') $totalDoc = (float)($xml->infoLiquidacionCompra->importeTotal ?? 0);
            elseif ($codDoc === '04') $totalDoc = (float)($xml->infoNotaCredito->valorModificacion ?? 0);
            elseif ($codDoc === '05') $totalDoc = (float)($xml->infoNotaDebito->valorTotal ?? 0);

            // Extract date for period validation
            $fechaEmisionRaw = '';
            if ($codDoc === '01') $fechaEmisionRaw = (string)($xml->infoFactura->fechaEmision ?? '');
            elseif ($codDoc === '03') $fechaEmisionRaw = (string)($xml->infoLiquidacionCompra->fechaEmision ?? '');
            elseif ($codDoc === '04') $fechaEmisionRaw = (string)($xml->infoNotaCredito->fechaEmision ?? '');
            elseif ($codDoc === '05') $fechaEmisionRaw = (string)($xml->infoNotaDebito->fechaEmision ?? '');
            elseif ($codDoc === '07') $fechaEmisionRaw = (string)($xml->infoRetencion->fechaEmision ?? '');

            if (!empty($fechaEmisionRaw)) {
                $fechaEmision = $this->formatearFecha($fechaEmisionRaw);
                try {
                    $this->periodosService->validarFechaPermitida(
                        $fechaEmision, 
                        $idEmpresa,
                        'No se puede registrar el documento porque el periodo contable está cerrado.'
                    );
                } catch (Exception $e) {
                    return ['ok' => false, 'error' => $e->getMessage(), 'estado_registro' => 'ERROR'];
                }
            }

            // 2. Lógica por tipo de documento
            // Se pasa $ambienteEmpresa (no el del XML) para que el registro y la verificación
            // de duplicados usen el ambiente activo de la empresa.
            $res = match ($codDoc) {
                '01' => $this->handleFactura($xml, $idEmpresa, $idUsuario, $esEmitidaPorMi, $ambienteEmpresa, $esGastoPersonal),
                '03' => $this->handleLiquidacion($xml, $idEmpresa, $idUsuario, $esEmitidaPorMi, $ambienteEmpresa, $esGastoPersonal),
                '04' => $this->handleNotaCredito($xml, $idEmpresa, $idUsuario, $esEmitidaPorMi, $ambienteEmpresa, $esGastoPersonal),
                '05' => $this->handleNotaDebito($xml, $idEmpresa, $idUsuario, $esEmitidaPorMi, $ambienteEmpresa, $esGastoPersonal),
                '07' => $this->handleRetencion($xml, $idEmpresa, $idUsuario, $esEmitidaPorMi, $ambienteEmpresa),
                default => ['ok' => false, 'error' => "Tipo de documento no soportado ($codDoc)", 'estado_registro' => 'ERROR']
            };

            $res['numero_documento'] = $numDoc;
            $res['tipo_nombre']      = $tipoNombre;
            $res['emisor']           = $emisor;
            $res['total']            = $totalDoc;
            $res['es_emitido']       = $esEmitidaPorMi;

            if ($res['ok']) {
                if ($res['existe'] ?? false) {
                    $res['estado_registro'] = 'YA ESTABA REGISTRADO';
                    $res['mensaje'] = 'El documento ya ha sido registrado con anterioridad.';
                } else {
                    $res['estado_registro'] = 'REGISTRADO';
                    if (empty($res['mensaje'])) {
                        $res['mensaje'] = 'El documento fue registrado con éxito.';
                    }
                }
            } else {
                $res['estado_registro'] = 'ERROR';
                $res['mensaje'] = $res['error'] ?? 'Error desconocido.';
            }

            return $res;

        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'estado_registro' => 'ERROR'];
        }
    }

    private function handleFactura(SimpleXMLElement $xml, int $idEmpresa, int $idUsuario, bool $esEmitida, string $ambiente, bool $esGastoPersonal = false): array
    {
        $it = $xml->infoTributaria;
        $info = $xml->infoFactura;
        $claveAcceso = (string) $it->claveAcceso;
        $tipoId = (string) $info->tipoIdentificacionComprador;

        if ($esEmitida) {
            if ($this->existeEnTabla('ventas_cabecera', 'clave_acceso', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Venta ya registrada: $claveAcceso", 'existe' => true];
            }
            $idCliente = $this->getOrCreateCliente($info->identificacionComprador, $info->razonSocialComprador, $info->direccionComprador, $idEmpresa, $idUsuario, $tipoId);

            $idFactura = $this->insertarVenta($xml, $idEmpresa, $idCliente, $idUsuario, $ambiente);
            return ['ok' => true, 'mensaje' => "Venta registrada con éxito.", 'id' => $idFactura, 'existe' => false];
        } else {
            if ($this->existeEnTabla('compras_cabecera', 'numero_autorizacion', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Compra ya registrada: $claveAcceso", 'existe' => true];
            }
            $idProv = $this->getOrCreateProveedor($it->ruc, $it->razonSocial, $it->dirMatriz, $idEmpresa, $idUsuario, null, (string)($it->nombreComercial ?? ''));
            
            // Lógica de Sustento Tributario para Compras (codDoc 01)
            $idSustento = null;
            if ($tipoId === '05') { // Cédula
                $idSustento = $this->getSustentoIdByCodigo('02');
            } elseif ($tipoId === '04') { // RUC
                $idSustento = $this->getSustentoIdByCodigo('01');
            }

            $idCompra = $this->insertarCompra($xml, $idEmpresa, $idProv, $idUsuario, $ambiente, $esGastoPersonal, $idSustento);

            $msgRet = "";
            $msgEgreso = "";
            if ($idCompra > 0) {
                // Generar retención automática
                $msgRet = " | " . $this->generarRetencionAutomatica($idCompra, $idProv, $idEmpresa, $idUsuario, $xml, $esGastoPersonal);
                
                // Generar egreso automático si aplica (Solo Facturas emitidas a la empresa)
                $totalCompra = (float)($info->importeTotal ?? 0);
                $numDoc = (string)$it->estab . '-' . (string)$it->ptoEmi . '-' . (string)$it->secuencial;
                $fechaEmisionDoc = $this->formatearFecha((string)$info->fechaEmision);
                $msgEgreso = $this->generarEgresoAutomatico($idCompra, $idProv, $idEmpresa, $idUsuario, $totalCompra, $numDoc, $fechaEmisionDoc);
            }

            $msg = $esGastoPersonal ? "Gasto Personal registrado con éxito." : "Compra registrada con éxito.";
            return ['ok' => true, 'mensaje' => $msg . $msgRet . $msgEgreso, 'id' => $idCompra, 'existe' => false];
        }
    }

    private function insertarVenta(SimpleXMLElement $xml, int $idEmpresa, int $idCliente, int $idUsuario, string $ambiente): int
    {
        $it      = $xml->infoTributaria;
        $info    = $xml->infoFactura;
        $xmlStr  = $xml->asXML();
        
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $claveAcceso = (string)$it->claveAcceso;
            // 1. Cabecera
            $idVenta = $this->ventaRepo->insertCabecera([
                'id_empresa' => $idEmpresa,
                'id_establecimiento' => $this->getEstablecimientoId($idEmpresa, (string)$it->estab, $idUsuario),
                'id_punto_emision' => $this->getPuntoEmisionId($idEmpresa, (string)$it->estab, (string)$it->ptoEmi, $idUsuario),
                'id_cliente' => $idCliente,
                'id_usuario' => $idUsuario,
                'fecha_emision' => $this->formatearFecha((string)$info->fechaEmision),
                'establecimiento' => (string)$it->estab,
                'punto_emision' => (string)$it->ptoEmi,
                'secuencial' => (string)$it->secuencial,
                'clave_acceso' => $claveAcceso,
                'total_sin_impuestos' => (float)$info->totalSinImpuestos,
                'total_descuento' => (float)$info->totalDescuento,
                'importe_total' => (float)$info->importeTotal,
                'propina' => (float)($info->propina ?? 0),
                'estado' => 'autorizado',
                'moneda' => (string)($info->moneda ?? 'DOLAR'),
                'tipo_registro' => 'electronico',
                'dias_credito' => (int)($info->pagos->pago[0]->plazo ?? 0),
                'plazo' => (string)($info->pagos->pago[0]->plazo ?? ''),
                'tipo_ambiente' => $ambiente
            ]);

            // 1.5 Información Adicional
            if (isset($xml->infoAdicional->campoAdicional)) {
                foreach ($xml->infoAdicional->campoAdicional as $ad) {
                    $this->ventaRepo->insertInfoAdicional([
                        'id_venta' => $idVenta,
                        'nombre' => (string)$ad['nombre'],
                        'valor' => (string)$ad
                    ]);
                }
            }

            // 2. Detalles
            if (isset($xml->detalles->detalle)) {
                foreach ($xml->detalles->detalle as $d) {
                    $idDetalle = $this->ventaRepo->insertDetalle([
                        'id_venta' => $idVenta,
                        'id_producto' => $this->getOrCreateProductoId($idEmpresa, $idUsuario, (string)$d->codigoPrincipal, (string)$d->descripcion, (float)$d->precioUnitario, isset($d->impuestos->impuesto) ? (string)$d->impuestos->impuesto[0]->tarifa : '0'),
                        'descripcion' => (string)$d->descripcion,
                        'cantidad' => (float)$d->cantidad,
                        'precio_unitario' => (float)$d->precioUnitario,
                        'descuento' => (float)$d->descuento,
                        'precio_total_sin_impuesto' => (float)$d->precioTotalSinImpuesto,
                        'codigo_principal' => (string)$d->codigoPrincipal,
                        'codigo_auxiliar' => (string)$d->codigoAuxiliar
                    ]);

                    // 3. Impuestos por detalle
                    if (isset($d->impuestos->impuesto)) {
                        foreach ($d->impuestos->impuesto as $imp) {
                            $this->ventaRepo->insertImpuesto([
                                'id_venta_detalle' => $idDetalle,
                                'codigo_impuesto' => (string)$imp->codigo,
                                'codigo_porcentaje' => (string)$imp->codigoPorcentaje,
                                'tarifa' => (float)$imp->tarifa,
                                'base_imponible' => (float)$imp->baseImponible,
                                'valor' => (float)$imp->valor
                            ]);
                        }
                    }
                }
            }

            // 4. Pagos
            if (isset($info->pagos->pago)) {
                foreach ($info->pagos->pago as $p) {
                    $this->ventaRepo->insertPago([
                        'id_venta'      => $idVenta,
                        'forma_pago'    => (string)$p->formaPago,
                        'total'         => (float)$p->total,
                        'plazo'         => (int)($p->plazo ?? 0),
                        'unidad_tiempo' => $this->normalizarUnidadTiempo($p->unidadTiempo ?? 'Días')
                    ]);
                }
            }

            // 5. Persistir XML original
            $db->prepare("UPDATE ventas_cabecera SET detalle_xml = ? WHERE id = ?")
               ->execute([$xmlStr, $idVenta]);

            $db->commit();
            return $idVenta;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function insertarCompra(SimpleXMLElement $xml, int $idEmpresa, int $idProv, int $idUsuario, string $ambiente, bool $esGastoPersonal = false, ?int $idSustento = null): int
    {
        $it          = $xml->infoTributaria;
        $codDoc      = (string)$it->codDoc;
        $claveAcceso = (string)$it->claveAcceso;
        $secuencial  = (string)$it->secuencial;
        $xmlStr      = $xml->asXML();
        
        $info = null;
        $total = 0;
        $subtotal = 0;
        $descuento = 0;
        
        if ($codDoc === '01') {
            $info = $xml->infoFactura;
            $total = (float)$info->importeTotal;
            $subtotal = (float)$info->totalSinImpuestos;
            $descuento = (float)$info->totalDescuento;
        } elseif ($codDoc === '03') {
            $info = $xml->infoLiquidacionCompra;
            $total = (float)$info->importeTotal;
            $subtotal = (float)$info->totalSinImpuestos;
            $descuento = (float)$info->totalDescuento;
        } elseif ($codDoc === '04') {
            $info = $xml->infoNotaCredito;
            $total = (float)$info->valorModificacion;
            $subtotal = (float)$info->totalSinImpuestos;
            $descuento = (float)($info->totalDescuento ?? 0);
        } elseif ($codDoc === '05') {
            $info = $xml->infoNotaDebito;
            $total = (float)$info->valorTotal;
            $subtotal = (float)$info->totalSinImpuestos;
            $descuento = 0;
        }

        $db = Database::getConnection();

        // Determinar sustento tributario preferido del proveedor
        $stProv = $db->prepare("SELECT id_sustento_tributario FROM proveedores WHERE id = ? LIMIT 1");
        $stProv->execute([$idProv]);
        $rowProv = $stProv->fetch(PDO::FETCH_ASSOC);
        if (!empty($rowProv['id_sustento_tributario'])) {
            $idSustento = (int)$rowProv['id_sustento_tributario'];
        }
        // Si no tiene el proveedor Y no fue pasado por parámetro (null), fallback a defecto total 1 (01)
        if (!$idSustento) {
            $idSustento = 1; 
        }

        $db->beginTransaction();

        try {
            $fechaEmision = $this->formatearFecha((string)$info->fechaEmision);

            // 1. Cabecera
            $idCompra = $this->compraRepo->insertCabecera([
                'id_empresa' => $idEmpresa,
                'id_proveedor' => $idProv,
                'id_usuario' => $idUsuario,
                'id_sustento_tributario' => $idSustento,
                'tipo_comprobante' => $codDoc,
                'establecimiento_prov' => (string)$it->estab,
                'punto_emision_prov' => (string)$it->ptoEmi,
                'secuencial_prov' => $secuencial,
                'numero_autorizacion' => $claveAcceso,
                'fecha_emision' => $fechaEmision,
                'total_sin_impuestos' => $subtotal,
                'total_descuento' => $descuento,
                'importe_total' => $total,
                'propina' => (float)($info->propina ?? 0),
                'estado' => 'registrado',
                'deducible' => $esGastoPersonal ? 'gasto_personal' : 'declaracion_iva',
                'tipo_registro' => 'electronico',
                'autorizacion_desde' => $secuencial,
                'autorizacion_hasta' => $secuencial,
                'fecha_caducidad' => $fechaEmision,
                'tipo_ambiente' => $ambiente
            ]);

            // 1.5 Información Adicional
            if (isset($xml->infoAdicional->campoAdicional)) {
                foreach ($xml->infoAdicional->campoAdicional as $ad) {
                    $this->compraRepo->insertInfoAdicional([
                        'id_compra' => $idCompra,
                        'nombre' => (string)$ad['nombre'],
                        'valor' => (string)$ad
                    ]);
                }
            }

            // 2. Detalles
            if (isset($xml->detalles->detalle)) {
                foreach ($xml->detalles->detalle as $d) {
                    $idDetalle = $this->compraRepo->insertDetalle([
                        'id_compra' => $idCompra,
                        'codigo_principal' => (string)$d->codigoPrincipal,
                        'codigo_auxiliar' => (string)$d->codigoAuxiliar,
                        'descripcion' => (string)$d->descripcion,
                        'cantidad' => (float)$d->cantidad,
                        'precio_unitario' => (float)$d->precioUnitario,
                        'descuento' => (float)$d->descuento,
                        'precio_total_sin_impuesto' => (float)$d->precioTotalSinImpuesto
                    ]);

                    // 3. Impuestos por detalle
                    if (isset($d->impuestos->impuesto)) {
                        foreach ($d->impuestos->impuesto as $imp) {
                            $this->compraRepo->insertImpuesto([
                                'id_compra_detalle' => $idDetalle,
                                'codigo_impuesto' => (string)$imp->codigo,
                                'codigo_porcentaje' => (string)$imp->codigoPorcentaje,
                                'tarifa' => (float)$imp->tarifa,
                                'base_imponible' => (float)$imp->baseImponible,
                                'valor' => (float)$imp->valor
                            ]);
                        }
                    }
                }
            }

            // 4. Pagos
            if (isset($info->pagos->pago)) {
                foreach ($info->pagos->pago as $p) {
                    $this->compraRepo->insertPago([
                        'id_compra'     => $idCompra,
                        'forma_pago'    => (string)$p->formaPago,
                        'total'         => (float)$p->total,
                        'plazo'         => (int)($p->plazo ?? 0),
                        'unidad_tiempo' => $this->normalizarUnidadTiempo($p->unidadTiempo ?? 'Días')
                    ]);
                }
            }

            // 5. Persistir XML original
            $db->prepare("UPDATE compras_cabecera SET detalle_xml = ? WHERE id = ?")
               ->execute([$xmlStr, $idCompra]);

            $db->commit();
            return $idCompra;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function getEstablecimientoId(int $idEmpresa, string $cod, ?int $idUsuario = null): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM empresa_establecimiento WHERE id_empresa = ? AND codigo = ? LIMIT 1");
        $st->execute([$idEmpresa, $cod]);
        $res = $st->fetch();
        if ($res) {
            return (int)$res['id'];
        }

        if ($idUsuario) {
            $stIns = $db->prepare("INSERT INTO empresa_establecimiento (id_empresa, nombre, codigo, direccion, tipo, logo_ruta, leyenda_pdf_titulo, leyenda_pdf_mensaje, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $stIns->execute([$idEmpresa, "Establecimiento $cod", $cod, '', 'otro', '', '', '', $idUsuario, $idUsuario]);
            return (int)$stIns->fetchColumn();
        }

        return null;
    }

    private function getPuntoEmisionId(int $idEmpresa, string $estab, string $pto, ?int $idUsuario = null): ?int
    {
        $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
        if (!$idEst) return null;
        
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM empresa_punto_emision WHERE id_establecimiento = ? AND codigo_punto = ? LIMIT 1");
        $st->execute([$idEst, $pto]);
        $res = $st->fetch();
        if ($res) {
            return (int)$res['id'];
        }

        if ($idUsuario) {
            $stIns = $db->prepare("INSERT INTO empresa_punto_emision (id_empresa, id_establecimiento, nombre, codigo_punto, logo_ruta, estado, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $stIns->execute([$idEmpresa, $idEst, "Punto $pto", $pto, '', 'activo', $idUsuario, $idUsuario]);
            return (int)$stIns->fetchColumn();
        }

        return null;
    }

    private function getOrCreateProductoId(int $idEmpresa, int $idUsuario, string $codigoPrincipal, string $descripcion, float $precio, string $tarifaIvaStr): int
    {
        $db = Database::getConnection();
        
        // Buscar producto por código principal
        if (!empty($codigoPrincipal)) {
            $st = $db->prepare("SELECT id FROM productos WHERE id_empresa = ? AND (codigo = ? OR codigo_auxiliar = ?) AND eliminado = false LIMIT 1");
            $st->execute([$idEmpresa, $codigoPrincipal, $codigoPrincipal]);
            $res = $st->fetch();
            if ($res) return (int)$res['id'];
        }
        
        // Crear producto básico para que la venta pueda registrarse
        $codigo = !empty($codigoPrincipal) ? $codigoPrincipal : 'SRI-' . substr(md5(uniqid()), 0, 8);
        $nombre = !empty($descripcion) ? $descripcion : 'Producto SRI no mapeado';
        
        $tarifa_iva = 0; // 0%
        if ($tarifaIvaStr == '2' || $tarifaIvaStr == '3' || $tarifaIvaStr == '4') {
            $tarifa_iva = 2; // 12%, 14%, 15% etc.
        }
        
        // Se inserta como servicio para no afectar kardex estrictamente si no se desea, o como bien genérico.
        // Se establecen opciones mínimas
        $sql = "INSERT INTO productos (
                    id_empresa, id_usuario, created_by, codigo, nombre,
                    codigo_auxiliar, codigo_barras,
                    precio_base, tipo_produccion, tarifa_iva, status, inventariable, eliminado, created_at, opciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, false, CURRENT_TIMESTAMP, ?) RETURNING id";
        
        $stIns = $db->prepare($sql);
        $stIns->execute([
            $idEmpresa, $idUsuario, $idUsuario, $codigo, $nombre,
            '', '', // codigo_auxiliar, codigo_barras
            $precio, '02', $tarifa_iva, 1, 'false', '{"compra":true,"venta":true}'
        ]);
        
        return (int)$stIns->fetchColumn();
    }

    private function formatearFecha(string $fecha): string
    {
        // SRI usa d/m/Y
        $parts = explode('/', $fecha);
        if (count($parts) === 3) {
            return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        }
        return $fecha;
    }

    private function handleLiquidacion(SimpleXMLElement $xml, int $idEmpresa, int $idUsuario, bool $esEmitida, string $ambiente, bool $esGastoPersonal = false): array
    {
        $it = $xml->infoTributaria;
        $info = $xml->infoLiquidacionCompra;
        $claveAcceso = (string) $it->claveAcceso;

        if ($esEmitida) {
            if ($this->existeEnTabla('liquidaciones_cabecera', 'clave_acceso', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Liquidación ya registrada", 'existe' => true];
            }
            $idProv = $this->getOrCreateProveedor($info->identificacionProveedor, $info->razonSocialProveedor, $info->direccionProveedor, $idEmpresa, $idUsuario, $info->tipoIdentificacionProveedor, (string)($it->nombreComercial ?? ''));

            $idLiq = $this->insertarLiquidacion($xml, $idEmpresa, $idProv, $idUsuario, $ambiente);
            return ['ok' => true, 'mensaje' => "Liquidación registrada (ID: $idLiq)", 'id' => $idLiq, 'existe' => false];
        } else {
            if ($this->existeEnTabla('compras_cabecera', 'numero_autorizacion', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Compra ya registrada", 'existe' => true];
            }
            $idProv = $this->getOrCreateProveedor($it->ruc, $it->razonSocial, $it->dirMatriz, $idEmpresa, $idUsuario, null, (string)($it->nombreComercial ?? ''));
            $idCompra = $this->insertarCompra($xml, $idEmpresa, $idProv, $idUsuario, $ambiente, $esGastoPersonal);
            
            $msgRet = "";
            if ($idCompra > 0) {
                $msgRet = " | " . $this->generarRetencionAutomatica($idCompra, $idProv, $idEmpresa, $idUsuario, $xml, $esGastoPersonal);
            }

            $msg = $esGastoPersonal ? "Gasto Personal (Liquidación) registrado con éxito." : "Compra (Liquidación recibida) registrada con éxito.";
            return ['ok' => true, 'mensaje' => $msg . $msgRet, 'id' => $idCompra, 'existe' => false];
        }
    }

    private function insertarLiquidacion(SimpleXMLElement $xml, int $idEmpresa, int $idProv, int $idUsuario, string $ambiente): int
    {
        $it     = $xml->infoTributaria;
        $info   = $xml->infoLiquidacionCompra;
        $xmlStr = $xml->asXML();
        
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $idLiq = $this->liquidacionRepo->insertCabecera([
                'id_empresa' => $idEmpresa,
                'id_establecimiento' => $this->getEstablecimientoId($idEmpresa, (string)$it->estab, $idUsuario),
                'id_punto_emision' => $this->getPuntoEmisionId($idEmpresa, (string)$it->estab, (string)$it->ptoEmi, $idUsuario),
                'id_proveedor' => $idProv,
                'id_usuario' => $idUsuario,
                'fecha_emision' => $this->formatearFecha((string)$info->fechaEmision),
                'establecimiento' => (string)$it->estab,
                'punto_emision' => (string)$it->ptoEmi,
                'secuencial' => (string)$it->secuencial,
                'total_sin_impuestos' => (float)$info->totalSinImpuestos,
                'total_descuento' => (float)$info->totalDescuento,
                'importe_total' => (float)$info->importeTotal,
                'estado' => 'autorizado',
                'tipo_registro' => 'electronico',
                'clave_acceso' => (string)$it->claveAcceso,
                'tipo_ambiente' => $ambiente
            ]);

            // 1.5 Información Adicional
            if (isset($xml->infoAdicional->campoAdicional)) {
                foreach ($xml->infoAdicional->campoAdicional as $ad) {
                    $this->liquidacionRepo->insertInfoAdicional([
                        'id_cabecera' => $idLiq,
                        'nombre' => (string)$ad['nombre'],
                        'valor' => (string)$ad
                    ]);
                }
            }

            if (isset($xml->detalles->detalle)) {
                foreach ($xml->detalles->detalle as $d) {
                    $idDetalle = $this->liquidacionRepo->insertDetalle([
                        'id_cabecera' => $idLiq,
                        'codigo_principal' => (string)$d->codigoPrincipal,
                        'codigo_auxiliar' => (string)$d->codigoAuxiliar,
                        'descripcion' => (string)$d->descripcion,
                        'cantidad' => (float)$d->cantidad,
                        'precio_unitario' => (float)$d->precioUnitario,
                        'descuento' => (float)$d->descuento,
                        'precio_total_sin_impuesto' => (float)$d->precioTotalSinImpuesto
                    ]);

                    if (isset($d->impuestos->impuesto)) {
                        foreach ($d->impuestos->impuesto as $imp) {
                            $this->liquidacionRepo->insertImpuesto([
                                'id_detalle' => $idDetalle,
                                'codigo_impuesto' => (string)$imp->codigo,
                                'codigo_porcentaje' => (string)$imp->codigoPorcentaje,
                                'tarifa' => (float)$imp->tarifa,
                                'base_imponible' => (float)$imp->baseImponible,
                                'valor' => (float)$imp->valor
                            ]);
                        }
                    }
                }
            }

            if (isset($info->pagos->pago)) {
                foreach ($info->pagos->pago as $p) {
                    $this->liquidacionRepo->insertPago([
                        'id_cabecera' => $idLiq,
                        'forma_pago' => (string)$p->formaPago,
                        'total' => (float)$p->total,
                        'plazo' => (int)($p->plazo ?? 0),
                        'unidad_tiempo' => $this->normalizarUnidadTiempo($p->unidadTiempo ?? 'Días')
                    ]);
                }
            }

            // Clave de acceso + XML en liquidaciones_cabecera
            $db->prepare("UPDATE liquidaciones_cabecera SET clave_acceso = ?, detalle_xml = ? WHERE id = ?")
               ->execute([(string)$it->claveAcceso, $xmlStr, $idLiq]);

            $db->commit();
            return $idLiq;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function handleNotaCredito(SimpleXMLElement $xml, int $idEmpresa, int $idUsuario, bool $esEmitida, string $ambiente, bool $esGastoPersonal = false): array
    {
        $it = $xml->infoTributaria;
        $info = $xml->infoNotaCredito;
        $claveAcceso = (string) $it->claveAcceso;

        if ($esEmitida) {
            if ($this->existeEnTabla('notas_credito_cabecera', 'clave_acceso', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Nota de Crédito ya registrada", 'existe' => true];
            }
            $idCliente = $this->getOrCreateCliente($info->identificacionComprador, $info->razonSocialComprador, $info->direccionComprador, $idEmpresa, $idUsuario, $info->tipoIdentificacionComprador);

            $idNC = $this->insertarNotaCredito($xml, $idEmpresa, $idCliente, $idUsuario, $ambiente);
            return ['ok' => true, 'mensaje' => "Nota de Crédito (Venta) registrada (ID: $idNC)", 'id' => $idNC, 'existe' => false];
        } else {
            if ($this->existeEnTabla('compras_cabecera', 'numero_autorizacion', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "NC Compra ya registrada", 'existe' => true];
            }
            $idProv = $this->getOrCreateProveedor($it->ruc, $it->razonSocial, $it->dirMatriz, $idEmpresa, $idUsuario, null, (string)($it->nombreComercial ?? ''));
            $idCompra = $this->insertarCompraNC($xml, $idEmpresa, $idProv, $idUsuario, $ambiente, $esGastoPersonal);
            $msg = $esGastoPersonal ? "Gasto Personal (NC) registrado (ID: $idCompra)" : "NC Compra registrada como Compra (ID: $idCompra)";
            return ['ok' => true, 'mensaje' => $msg, 'id' => $idCompra, 'existe' => false];
        }
    }

    private function handleNotaDebito(SimpleXMLElement $xml, int $idEmpresa, int $idUsuario, bool $esEmitida, string $ambiente, bool $esGastoPersonal = false): array
    {
        $it = $xml->infoTributaria;
        $info = $xml->infoNotaDebito;
        $claveAcceso = (string) $it->claveAcceso;

        if ($esEmitida) {
            if ($this->existeEnTabla('nota_debito_cabecera', 'clave_acceso', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Nota de Débito ya registrada", 'existe' => true];
            }
            $idCliente = $this->getOrCreateCliente($info->identificacionComprador, $info->razonSocialComprador, $info->direccionComprador, $idEmpresa, $idUsuario, $info->tipoIdentificacionComprador);
            $idND = $this->insertarNotaDebito($xml, $idEmpresa, $idCliente, $idUsuario, $ambiente);
            return ['ok' => true, 'mensaje' => "Nota de Débito (Venta) registrada (ID: $idND)", 'id' => $idND, 'existe' => false];
        } else {
            if ($this->existeEnTabla('compras_cabecera', 'numero_autorizacion', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "ND Compra ya registrada", 'existe' => true];
            }
            $idProv = $this->getOrCreateProveedor($it->ruc, $it->razonSocial, $it->dirMatriz, $idEmpresa, $idUsuario, null, (string)($it->nombreComercial ?? ''));
            $idCompra = $this->insertarCompraND($xml, $idEmpresa, $idProv, $idUsuario, $ambiente, $esGastoPersonal);
            $msg = $esGastoPersonal ? "Gasto Personal (ND) registrado (ID: $idCompra)" : "ND Compra registrada como Compra (ID: $idCompra)";
            return ['ok' => true, 'mensaje' => $msg, 'id' => $idCompra, 'existe' => false];
        }
    }

    private function handleRetencion(SimpleXMLElement $xml, int $idEmpresa, int $idUsuario, bool $esEmitida, string $ambiente): array
    {
        $it = $xml->infoTributaria;
        $info = $xml->infoRetencion;
        $claveAcceso = (string) $it->claveAcceso;

        if ($esEmitida) {
            // RETENCIÓN EMITIDA POR NOSOTROS (Retención en Compras)
            if ($this->existeEnTabla('retencion_compra_cabecera', 'clave_acceso', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Retención de Compra ya registrada", 'existe' => true];
            }
            
            // Buscar proveedor
            $idProv = $this->getOrCreateProveedor(
                $info->identificacionSujetoRetenido, 
                $info->razonSocialSujetoRetenido, 
                $info->dirEstablecimiento ?? '', 
                $idEmpresa, 
                $idUsuario, 
                $info->tipoIdentificacionSujetoRetenido, 
                (string)($it->nombreComercial ?? '')
            );
            
            $idRet = $this->insertarRetencionCompra($xml, $idEmpresa, $idProv, $idUsuario, $ambiente);
            return ['ok' => true, 'mensaje' => "Retención de Compra (emitida) registrada con éxito.", 'id' => $idRet, 'existe' => false];
        } else {
            // RETENCIÓN EMITIDA A NOSOTROS (Retención en Ventas)
            if ($this->existeEnTabla('retencion_venta_cabecera', 'clave_acceso', $claveAcceso, $idEmpresa, false, $ambiente)) {
                return ['ok' => true, 'mensaje' => "Retención de Venta ya registrada", 'existe' => true];
            }
            
            // Buscar cliente
            $idCliente = $this->getOrCreateCliente($it->ruc, $it->razonSocial, $it->dirMatriz, $idEmpresa, $idUsuario);
            
            $idRet = $this->insertarRetencionVenta($xml, $idEmpresa, $idCliente, $idUsuario, $ambiente);
            return ['ok' => true, 'mensaje' => "Retención de Venta (recibida) registrada con éxito.", 'id' => $idRet, 'existe' => false];
        }
    }

    private function insertarNotaCredito(SimpleXMLElement $xml, int $idEmpresa, int $idCliente, int $idUsuario, string $ambiente): int
    {
        $it     = $xml->infoTributaria;
        $info   = $xml->infoNotaCredito;
        $db     = Database::getConnection();
        $xmlStr = $xml->asXML();
        $db->beginTransaction();
        try {
            $idNC = $this->ncRepo->insertCabecera([
                'id_empresa' => $idEmpresa,
                'id_establecimiento' => $this->getEstablecimientoId($idEmpresa, (string)$it->estab, $idUsuario),
                'id_punto_emision' => $this->getPuntoEmisionId($idEmpresa, (string)$it->estab, (string)$it->ptoEmi, $idUsuario),
                'id_cliente' => $idCliente,
                'id_usuario' => $idUsuario,
                'fecha_emision' => $this->formatearFecha((string)$info->fechaEmision),
                'establecimiento' => (string)$it->estab,
                'punto_emision' => (string)$it->ptoEmi,
                'secuencial' => (string)$it->secuencial,
                'clave_acceso' => (string)$it->claveAcceso,
                'total_sin_impuestos' => (float)$info->totalSinImpuestos,
                'total_descuento' => (float)($info->totalDescuento ?? 0),
                'importe_total' => (float)$info->valorModificacion,
                'num_doc_modificado' => (string)$info->numDocModificado,
                'fecha_emision_docs_sustento' => $this->formatearFecha((string)$info->fechaEmisionDocSustento),
                'motivo' => (string)$info->motivo,
                'estado' => 'autorizado',
                'tipo_registro' => 'electronico',
                'tipo_ambiente' => $ambiente
            ]);

            if (isset($xml->detalles->detalle)) {
                foreach ($xml->detalles->detalle as $d) {
                    $idDet = $this->ncRepo->insertDetalle([
                        'id_nota_credito' => $idNC,
                        'descripcion' => (string)$d->descripcion,
                        'cantidad' => (float)$d->cantidad,
                        'precio_unitario' => (float)$d->precioUnitario,
                        'descuento' => (float)$d->descuento,
                        'precio_total_sin_impuesto' => (float)$d->precioTotalSinImpuesto,
                        'codigo_principal' => (string)$d->codigoPrincipal
                    ]);
                    if (isset($d->impuestos->impuesto)) {
                        foreach ($d->impuestos->impuesto as $imp) {
                            $this->ncRepo->insertImpuesto([
                                'id_nota_credito_detalle' => $idDet,
                                'codigo_impuesto' => (string)$imp->codigo,
                                'codigo_porcentaje' => (string)$imp->codigoPorcentaje,
                                'tarifa' => (float)$imp->tarifa,
                                'base_imponible' => (float)$imp->baseImponible,
                                'valor' => (float)$imp->valor
                            ]);
                        }
                    }
                }
            }

            // Persistir XML original
            $db->prepare("UPDATE notas_credito_cabecera SET detalle_xml = ? WHERE id = ?")
               ->execute([$xmlStr, $idNC]);

            $db->commit();
            return $idNC;
        } catch (Exception $e) { $db->rollBack(); throw $e; }
    }

    private function insertarNotaDebito(SimpleXMLElement $xml, int $idEmpresa, int $idCliente, int $idUsuario, string $ambiente): int
    {
        $it     = $xml->infoTributaria;
        $info   = $xml->infoNotaDebito;
        $db     = Database::getConnection();
        $xmlStr = $xml->asXML();
        $db->beginTransaction();
        try {
            $idND = $this->ndRepo->insertCabecera([
                'id_empresa' => $idEmpresa,
                'id_establecimiento' => $this->getEstablecimientoId($idEmpresa, (string)$it->estab, $idUsuario),
                'id_punto_emision' => $this->getPuntoEmisionId($idEmpresa, (string)$it->estab, (string)$it->ptoEmi, $idUsuario),
                'id_cliente' => $idCliente,
                'id_usuario' => $idUsuario,
                'fecha_emision' => $this->formatearFecha((string)$info->fechaEmision),
                'establecimiento' => (string)$it->estab,
                'punto_emision' => (string)$it->ptoEmi,
                'secuencial' => (string)$it->secuencial,
                'clave_acceso' => (string)$it->claveAcceso,
                'total_sin_impuestos' => (float)$info->totalSinImpuestos,
                'importe_total' => (float)$info->valorTotal,
                'num_doc_modificado' => (string)$info->numDocModificado,
                'fecha_emision_docs_sustento' => $this->formatearFecha((string)$info->fechaEmisionDocSustento),
                'estado' => 'autorizado',
                'tipo_registro' => 'electronico',
                'tipo_ambiente' => $ambiente
            ]);

            if (isset($info->motivos->motivo)) {
                foreach ($info->motivos->motivo as $m) {
                    $this->ndRepo->insertMotivo(['id_nota_debito' => $idND, 'razon' => (string)$m->razon, 'valor' => (float)$m->valor]);
                }
            }
            if (isset($info->impuestos->impuesto)) {
                foreach ($info->impuestos->impuesto as $imp) {
                    $this->ndRepo->insertImpuesto([
                        'id_nota_debito' => $idND, 'codigo_impuesto' => (string)$imp->codigo, 'codigo_porcentaje' => (string)$imp->codigoPorcentaje,
                        'tarifa' => (float)$imp->tarifa, 'base_imponible' => (float)$imp->baseImponible, 'valor' => (float)$imp->valor
                    ]);
                }
            }
            if (isset($info->pagos->pago)) {
                foreach ($info->pagos->pago as $p) {
                    $this->ndRepo->insertPago([
                        'id_nota_debito' => $idND, 'forma_pago' => (string)$p->formaPago, 'total' => (float)$p->total,
                        'plazo' => (int)($p->plazo ?? 0), 'unidad_tiempo' => $this->normalizarUnidadTiempo($p->unidadTiempo ?? 'Días')
                    ]);
                }
            }

            // Persistir XML original (nota_debito_cabecera no estaba en la lista del usuario
            // pero se guarda igual para consistencia)
            $db->prepare("UPDATE nota_debito_cabecera SET detalle_xml = ? WHERE id = ?")
               ->execute([$xmlStr, $idND]);

            $db->commit();
            return $idND;
        } catch (Exception $e) { $db->rollBack(); throw $e; }
    }

    private function insertarRetencionCompra(SimpleXMLElement $xml, int $idEmpresa, int $idProv, int $idUsuario, string $ambiente): int
    {
        $it = $xml->infoTributaria;
        $info = isset($xml->infoRetencion) ? $xml->infoRetencion : $xml->infoCompRetencion;
        
        $fechaEmision = $this->formatearFecha((string)$info->fechaEmision);
        
        // Intentar vincular con una compra existente por número de documento de sustento
        $idCompra = null;
        $idLiquidacion = null;
        $tipoDocSustento = null;
        $numDocSustento = null;
        $fechaSustento = null;

        if (isset($xml->impuestos->impuesto)) {
            foreach ($xml->impuestos->impuesto as $imp) {
                if (isset($imp->numDocSustento)) {
                    $numDocSustento = (string)$imp->numDocSustento;
                    $tipoDocSustento = (string)$imp->codDocSustento;
                    $fechaSustento = $this->formatearFecha((string)$imp->fechaEmisionDocSustento);
                    
                    // Buscar la compra
                    $db = Database::getConnection();
                    if ($tipoDocSustento === '01') {
                        $st = $db->prepare("SELECT id FROM compras_cabecera WHERE id_empresa = ? AND id_proveedor = ? AND (establecimiento_prov || '-' || punto_emision_prov || '-' || secuencial_prov) = ? AND eliminado = false LIMIT 1");
                        $st->execute([$idEmpresa, $idProv, $numDocSustento]);
                        $idCompra = $st->fetchColumn() ?: null;
                    } elseif ($tipoDocSustento === '03') {
                        $st = $db->prepare("SELECT id FROM liquidaciones_cabecera WHERE id_empresa = ? AND id_proveedor = ? AND (establecimiento || '-' || punto_emision || '-' || secuencial) = ? AND eliminado = false LIMIT 1");
                        $st->execute([$idEmpresa, $idProv, $numDocSustento]);
                        $idLiquidacion = $st->fetchColumn() ?: null;
                    }
                    if ($idCompra || $idLiquidacion) break;
                }
            }
        }

        // 1. Extraer líneas de retención (Soporta v1.0 y v2.0)
        $lineas = [];

        // Versión 1.0.0 (impuestos/impuesto)
        if (isset($xml->impuestos->impuesto)) {
            foreach ($xml->impuestos->impuesto as $imp) {
                $codigoRet = (string)$imp->codigoRetencion;
                $db = Database::getConnection();
                $st = $db->prepare("SELECT id FROM retenciones_sri WHERE codigo_ret = ? AND status = 1 LIMIT 1");
                $st->execute([$codigoRet]);
                $idSri = $st->fetchColumn() ?: null;

                $lineas[] = [
                    'codigo_impuesto' => (string)$imp->codigo === '1' ? 'RENTA' : 'IVA',
                    'id_retencion_sri' => $idSri,
                    'codigo_retencion' => $codigoRet,
                    'base_imponible' => (float)$imp->baseImponible,
                    'porcentaje_retener' => (float)$imp->porcentajeRetener,
                    'valor_retenido' => (float)$imp->valorRetenido,
                    'cod_doc_sustento' => (string)($imp->codDocSustento ?? $tipoDocSustento),
                    'num_doc_sustento' => (string)($imp->numDocSustento ?? $numDocSustento),
                    'fecha_emision_doc_sustento' => $this->formatearFecha((string)($imp->fechaEmisionDocSustento ?? $fechaSustento))
                ];
            }
        }

        // Versión 2.0.0 (docsSustento/docSustento/retenciones/retencion)
        if (isset($xml->docsSustento->docSustento)) {
            foreach ($xml->docsSustento->docSustento as $doc) {
                $codSustento = (string)$doc->codDocSustento;
                $numSustento = (string)$doc->numDocSustento;
                $fecSustento = $this->formatearFecha((string)$doc->fechaEmisionDocSustento);

                if (isset($doc->retenciones->retencion)) {
                    foreach ($doc->retenciones->retencion as $ret) {
                        $codigoRet = (string)$ret->codigoRetencion;
                        $db = Database::getConnection();
                        $st = $db->prepare("SELECT id FROM retenciones_sri WHERE codigo_ret = ? AND status = 1 LIMIT 1");
                        $st->execute([$codigoRet]);
                        $idSri = $st->fetchColumn() ?: null;

                        $lineas[] = [
                            'codigo_impuesto' => (string)$ret->codigo === '1' ? 'RENTA' : 'IVA',
                            'id_retencion_sri' => $idSri,
                            'codigo_retencion' => $codigoRet,
                            'base_imponible' => (float)$ret->baseImponible,
                            'porcentaje_retener' => (float)$ret->porcentajeRetener,
                            'valor_retenido' => (float)$ret->valorRetenido,
                            'cod_doc_sustento' => $codSustento,
                            'num_doc_sustento' => $numSustento,
                            'fecha_emision_doc_sustento' => $fecSustento
                        ];
                    }
                }
            }
        }

        $data = [
            'id_empresa'                 => $idEmpresa,
            'id_proveedor'               => $idProv,
            'id_usuario'                 => $idUsuario,
            'fecha_emision'              => $fechaEmision,
            'establecimiento'            => (string)$it->estab,
            'punto_emision'              => (string)$it->ptoEmi,
            'secuencial'                 => (string)$it->secuencial,
            'clave_acceso'               => (string)$it->claveAcceso,
            'periodo_fiscal'             => (string)$info->periodoFiscal,
            'tipo_doc_sustento'          => $tipoDocSustento ?: '01',
            'num_doc_sustento'           => $numDocSustento,
            'fecha_emision_doc_sustento' => $fechaSustento,
            'id_compra'                  => $idCompra,
            'id_liquidacion'             => $idLiquidacion,
            'estado'                     => 'autorizado',
            'lineas'                     => $lineas,
            'origen'                     => 'electronico',
            'detalle_xml'                => $xml->asXML(),
            'tipo_ambiente'              => $ambiente
        ];

        return $this->retencionService->crear($data);
    }

    private function insertarRetencionVenta(SimpleXMLElement $xml, int $idEmpresa, int $idCliente, int $idUsuario, string $ambiente): int
    {
        $it   = $xml->infoTributaria;
        $info = isset($xml->infoRetencion) ? $xml->infoRetencion : $xml->infoCompRetencion;

        $fechaEmision = $this->formatearFecha((string)$info->fechaEmision);

        // XML completo para auditoria
        $xmlString = $xml->asXML();

        // Helper: codigo de impuesto SRI -> nombre interno
        $mapImpuesto = static function (string $cod): string {
            return match ($cod) {
                '1'     => 'RENTA',
                '2'     => 'IVA',
                '3'     => 'ISD',
                default => strtoupper($cod),
            };
        };

        // Helper: formatear numDocSustento a 000-000-000000000
        $fmtDoc = static function (string $raw): string {
            $raw = trim($raw);
            $partes = explode('-', $raw);
            if (count($partes) === 3) {
                return str_pad($partes[0], 3, '0', STR_PAD_LEFT)
                     . '-' . str_pad($partes[1], 3, '0', STR_PAD_LEFT)
                     . '-' . str_pad($partes[2], 9, '0', STR_PAD_LEFT);
            }
            if (strlen($raw) === 15) {
                return substr($raw, 0, 3) . '-' . substr($raw, 3, 3) . '-' . substr($raw, 6, 9);
            }
            return $raw;
        };

        // Intentar vincular con una venta existente
        $idVenta        = null;
        $numDocSustento = null;
        $fechaSustento  = null;

        if (isset($xml->impuestos->impuesto)) {
            foreach ($xml->impuestos->impuesto as $imp) {
                if (!empty((string)$imp->numDocSustento)) {
                    $numDocSustento = $fmtDoc((string)$imp->numDocSustento);
                    $fechaSustento  = $this->formatearFecha((string)$imp->fechaEmisionDocSustento);
                    $db = Database::getConnection();
                    $st = $db->prepare(
                        "SELECT id FROM ventas_cabecera WHERE id_empresa = ? AND id_cliente = ?
                         AND (establecimiento || '-' || punto_emision || '-' || secuencial) = ?
                         AND eliminado = false LIMIT 1"
                    );
                    $st->execute([$idEmpresa, $idCliente, $numDocSustento]);
                    $idVenta = $st->fetchColumn() ?: null;
                    if ($idVenta) break;
                }
            }
        }

        // 1. Extraer lineas de retencion (Soporta v1.0 y v2.0)
        $lineas = [];

        // Version 1.0.0 (impuestos/impuesto)
        if (isset($xml->impuestos->impuesto)) {
            foreach ($xml->impuestos->impuesto as $imp) {
                $numDoc = !empty((string)$imp->numDocSustento) ? $fmtDoc((string)$imp->numDocSustento) : '';
                $lineas[] = [
                    'codigo_impuesto'            => $mapImpuesto((string)$imp->codigo),
                    'codigo_retencion'           => (string)$imp->codigoRetencion,
                    'base_imponible'             => (float)$imp->baseImponible,
                    'porcentaje_retencion'       => (float)$imp->porcentajeRetener,
                    'valor_retenido'             => (float)$imp->valorRetenido,
                    'cod_doc_sustento'           => (string)$imp->codDocSustento,
                    'num_doc_sustento'           => $numDoc,
                    'fecha_emision_doc_sustento' => $this->formatearFecha((string)$imp->fechaEmisionDocSustento),
                ];
            }
        }

        // Version 2.0.0 (docsSustento/docSustento/retenciones/retencion)
        if (isset($xml->docsSustento->docSustento)) {
            foreach ($xml->docsSustento->docSustento as $doc) {
                $codSustento = (string)$doc->codDocSustento;
                $numSustento = !empty((string)$doc->numDocSustento) ? $fmtDoc((string)$doc->numDocSustento) : '';
                $fecSustento = $this->formatearFecha((string)$doc->fechaEmisionDocSustento);

                if (isset($doc->retenciones->retencion)) {
                    foreach ($doc->retenciones->retencion as $ret) {
                        $lineas[] = [
                            'codigo_impuesto'            => $mapImpuesto((string)$ret->codigo),
                            'codigo_retencion'           => (string)$ret->codigoRetencion,
                            'base_imponible'             => (float)$ret->baseImponible,
                            'porcentaje_retencion'       => (float)$ret->porcentajeRetener,
                            'valor_retenido'             => (float)$ret->valorRetenido,
                            'cod_doc_sustento'           => $codSustento,
                            'num_doc_sustento'           => $numSustento,
                            'fecha_emision_doc_sustento' => $fecSustento,
                        ];
                    }
                }
            }
        }

        $data = [
            'id_empresa'     => $idEmpresa,
            'id_cliente'     => $idCliente,
            'id_usuario'     => $idUsuario,
            'id_venta'       => $idVenta,
            'fecha_emision'  => $fechaEmision,
            'establecimiento'=> (string)$it->estab,
            'punto_emision'  => (string)$it->ptoEmi,
            'secuencial'     => (string)$it->secuencial,
            'clave_acceso'   => (string)$it->claveAcceso,
            'periodo_fiscal' => (string)$info->periodoFiscal,
            'estado'         => 'registrado',
            'lineas'         => $lineas,
            'origen'         => 'electronico',
            'detalle_xml'    => $xmlString,
            'tipo_ambiente'  => $ambiente,
        ];

        // Insertar siempre como nuevo registro.
        // Los registros con eliminado=true no bloquean esta insercion
        // gracias al indice parcial uq_ret_vta_cab_clave_active (WHERE eliminado = false).
        return $this->retVentaService->crearDesdeXml($data);
    }


    // Métodos stub para compras de NC/ND (se registran en compras_cabecera por ahora como pidió el usuario)
    private function insertarCompraNC($xml, $idEmpresa, $idProv, $idUsuario, string $ambiente = '1', bool $esGastoPersonal = false): int { return $this->insertarCompra($xml, $idEmpresa, $idProv, $idUsuario, $ambiente, $esGastoPersonal); }
    private function insertarCompraND($xml, $idEmpresa, $idProv, $idUsuario, string $ambiente = '1', bool $esGastoPersonal = false): int { return $this->insertarCompra($xml, $idEmpresa, $idProv, $idUsuario, $ambiente, $esGastoPersonal); }



    private function getOrCreateCliente($identificacion, $razonSocial, $direccion, int $idEmpresa, int $idUsuario, $tipoIdXml = null): int
    {
        $identificacion = trim((string)$identificacion);
        $tipoId = $this->mapearTipoId($tipoIdXml, $identificacion);
        
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM clientes WHERE identificacion = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$identificacion, $idEmpresa]);
        $res = $st->fetch();
        
        if ($res) return (int) $res['id'];

        return $this->clienteRepo->create([
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'nombre' => (string) $razonSocial,
            'tipo_id' => $tipoId,
            'identificacion' => $identificacion,
            'telefono' => null,
            'email' => null,
            'direccion' => (string) $direccion,
            'provincia' => null,
            'ciudad' => null,
            'id_vendedor' => null,
            'id_cuenta_cobrar' => null,
            'id_cuenta_ingreso' => null
        ]);
    }

    private function getOrCreateProveedor($identificacion, $razonSocial, $direccion, int $idEmpresa, int $idUsuario, $tipoIdXml = null, string $nombreComercial = ''): int
    {
        $identificacion = trim((string)$identificacion);
        $tipoId = $this->mapearTipoId($tipoIdXml, $identificacion);

        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM proveedores WHERE identificacion = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$identificacion, $idEmpresa]);
        $res = $st->fetch();

        if ($res) return (int) $res['id'];

        $tipoEmpresa = 1; // Por defecto: Persona Natural
        if ($tipoId === '04' && strlen($identificacion) >= 3) {
            $tercerDigito = (int)$identificacion[2];
            if ($tercerDigito === 9) {
                $tipoEmpresa = 3; // Privada
            } elseif ($tercerDigito === 6) {
                $tipoEmpresa = 5; // Pública
            }
        }

        return $this->proveedorRepo->create([
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'created_by' => $idUsuario,
            'razon_social' => (string) $razonSocial,
            'nombre_comercial' => $nombreComercial,
            'tipo_id_proveedor' => $tipoId,
            'identificacion' => $identificacion,
            'direccion' => (string) $direccion,
            'tipo_empresa' => $tipoEmpresa,
            'status' => 1,
            'id_sustento_tributario' => 1, // Por defecto '01'
            'eliminado' => false
        ]);
    }

    private function mapearTipoId($tipoXml, $identificacion): string
    {
        if ($tipoXml) {
             $t = (string) $tipoXml;
             if ($t === '04') return '04'; // RUC
             if ($t === '05') return '05'; // Cedula
             if ($t === '06') return '06'; // Pasaporte
        }
        
        $len = strlen($identificacion);
        if ($len === 13) return '04';
        if ($len === 10) return '05';
        return '06';
    }

    /**
     * @param ?string $tipoAmbiente '1'=pruebas | '2'=producción. Si se pasa, solo cuenta registros del mismo ambiente.
     *                               Garantiza que un documento de pruebas no bloquee el registro del mismo en producción.
     */
    private function existeEnTabla(string $tabla, string $campo, string $valor, int $idEmpresa, bool $incluirEliminados = false, ?string $tipoAmbiente = null): bool
    {
        try {
            $db = Database::getConnection();
            $whereEliminado = $incluirEliminados ? "" : " AND eliminado = false";
            $whereAmbiente  = '';
            $params         = [$valor, $idEmpresa];

            if ($tipoAmbiente !== null) {
                // Verificar que la tabla tiene columna tipo_ambiente antes de filtrar
                $cols = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$tabla' AND column_name = 'tipo_ambiente' AND table_schema = 'public'")->fetchColumn();
                if ($cols) {
                    $whereAmbiente = " AND tipo_ambiente = ?";
                    $params[]      = $tipoAmbiente;
                }
            }

            $sql = "SELECT id FROM $tabla WHERE $campo = ? AND id_empresa = ? $whereEliminado $whereAmbiente LIMIT 1";
            $st  = $db->prepare($sql);
            $st->execute($params);
            return (bool) $st->fetch();
        } catch (\PDOException $e) {
            return false;
        }
    }

    private function reactivarSiEstaEliminado(string $tabla, string $campo, string $valor, int $idEmpresa, int $idUsuario): bool
    {
        // Este método ya no se usa activamente (la lógica de recarga está dentro de cada insertarXxx).
        // Se mantiene para no romper referencias.
        return false;
    }

    private function getSustentoIdByCodigo(string $codigo): ?int
    {
        try {
            $db = Database::getConnection();
            $st = $db->prepare("SELECT id FROM sustento_tributario WHERE codigo = ? AND status = 1 LIMIT 1");
            $st->execute([$codigo]);
            $res = $st->fetch();
            return $res ? (int)$res['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    private function normalizarUnidadTiempo($unidad): string
    {
        if (empty($unidad)) return 'Días';
        $u = mb_strtolower(trim((string)$unidad), 'UTF-8');
        
        if (in_array($u, ['dias', 'días', 'diario'])) return 'Días';
        if (in_array($u, ['mes', 'meses', 'mensual'])) return 'Meses';
        if (in_array($u, ['año', 'años', 'anual', 'anios'])) return 'Años';
        
        return mb_convert_case($u, MB_CASE_TITLE, "UTF-8");
    }

    private function calcularDiasHabiles(\DateTime $desde, \DateTime $hasta): int
    {
        // Si la fecha desde es mayor a hasta (ej: factura del futuro), son 0 días
        if ($desde > $hasta) return 0;

        $dias = 0;
        $temp = clone $desde;
        
        // Iterar día por día hasta llegar a la fecha actual
        while ($temp->format('Y-m-d') < $hasta->format('Y-m-d')) {
            $temp->modify('+1 day');
            $w = (int)$temp->format('N'); // 1=Lunes, 7=Domingo
            if ($w < 6) { // Lunes a Viernes
                $dias++;
            }
        }
        return $dias;
    }

    private function generarRetencionAutomatica(int $idCompra, int $idProv, int $idEmpresa, int $idUsuario, SimpleXMLElement $xml, bool $esGastoPersonal = false): string
    {
        try {
            // 1. Obtener configuración del proveedor
            $prov = $this->proveedorRepo->getDetalleCompleto($idProv, $idEmpresa);
            if (!$prov) {
                return "No se pudo generar retención: No se encontró el proveedor.";
            }

            $idRetRenta = !empty($prov['id_retencion_renta']) ? (int)$prov['id_retencion_renta'] : null;
            $idRetIva   = !empty($prov['id_retencion_iva'])   ? (int)$prov['id_retencion_iva']   : null;

            if ($esGastoPersonal) {
                if ($idRetRenta || $idRetIva) {
                    return "No se puede generar una retención a un gasto personal.";
                }
                return ""; // No mostrar nada si es gasto personal y no tiene config
            }

            $db = Database::getConnection();
            $countSri = $db->query("SELECT COUNT(*) FROM retenciones_sri")->fetchColumn();

            if (!$idRetRenta && !$idRetIva) {
                return "No se generó retención: El proveedor no tiene conceptos de retención predefinidos para generar retenciones automáticas.";
            }

            $detallesConfig = "Configurado: " . ($idRetRenta ? "Renta (ID:$idRetRenta)" : "Sin Renta") . " y " . ($idRetIva ? "IVA (ID:$idRetIva)" : "Sin IVA");

            // 2. Extraer datos de la factura
            $it = $xml->infoTributaria;
            $info = $xml->infoFactura;
            
            $estab = str_pad((string)$it->estab, 3, '0', STR_PAD_LEFT);
            $pto   = str_pad((string)$it->ptoEmi, 3, '0', STR_PAD_LEFT);
            $sec   = str_pad((string)$it->secuencial, 9, '0', STR_PAD_LEFT);
            $numDocSustento = $estab . '-' . $pto . '-' . $sec;
            
            $fechaEmisionDoc = $this->formatearFecha((string)$info->fechaEmision);
            $fDoc = new \DateTime($fechaEmisionDoc);
            $hoy = new \DateTime();
            
            // Validar plazo de 5 días hábiles
            $diasHabiles = $this->calcularDiasHabiles($fDoc, $hoy);
            if ($diasHabiles > 5) {
                return "No se ha realizado la retención porque han transcurrido más de los 5 días permitidos por la ley desde la emisión del documento (" . $fDoc->format('d/m/Y') . ").";
            }

            $periodoFiscal = $fDoc->format('m/Y');

            // 3. Obtener bases imponibles
            $subtotal = (float)$info->totalSinImpuestos;
            $ivaTotal = 0;
            if (isset($info->totalConImpuestos->totalImpuesto)) {
                foreach ($info->totalConImpuestos->totalImpuesto as $imp) {
                    if ((string)$imp->codigo === '2') { // IVA
                        $ivaTotal += (float)$imp->valor;
                    }
                }
            }

            $lineas = [];

            // 4. Agregar línea de Renta
            if ($idRetRenta) {
                $retSri = $this->retencionCompraRepo->getRetencionSriPorId($idRetRenta);
                if ($retSri) {
                    $lineas[] = [
                        'codigo_impuesto'   => 'RENTA', 
                        'id_retencion_sri'  => $retSri['id'],
                        'codigo_retencion'  => $retSri['codigo_ret'],
                        'concepto'          => "RENTA: " . $retSri['concepto_ret'],
                        'base_imponible'    => $subtotal,
                        'porcentaje_retener'=> $retSri['porcentaje_ret'],
                        'cod_doc_sustento'  => '01',
                        'num_doc_sustento'  => $numDocSustento,
                        'fecha_emision_doc_sustento' => $fechaEmisionDoc
                    ];
                }
            }

            // 5. Agregar línea de IVA
            if ($idRetIva && $ivaTotal > 0) {
                $retSri = $this->retencionCompraRepo->getRetencionSriPorId($idRetIva);
                if ($retSri) {
                    $lineas[] = [
                        'codigo_impuesto'   => 'IVA',
                        'id_retencion_sri'  => $retSri['id'],
                        'codigo_retencion'  => $retSri['codigo_ret'],
                        'concepto'          => "IVA: " . $retSri['concepto_ret'],
                        'base_imponible'    => $ivaTotal,
                        'porcentaje_retener'=> $retSri['porcentaje_ret'],
                        'cod_doc_sustento'  => '01',
                        'num_doc_sustento'  => $numDocSustento,
                        'fecha_emision_doc_sustento' => $fechaEmisionDoc
                    ];
                }
            }

            if (empty($lineas)) {
                if ($idRetIva && $ivaTotal <= 0) {
                    return "No se generó retención de IVA: La factura no tiene base imponible de IVA.";
                }
                return "No se generó retención: No se pudieron calcular las líneas.";
            }

            // 6. Obtener establecimiento y punto de emisión propio
            $idEst = $this->empresaRepo->getPrimerEstablecimientoId($idEmpresa);
            $db = Database::getConnection();
            
            // Obtener el código del establecimiento
            $stEst = $db->prepare("SELECT codigo FROM empresa_establecimiento WHERE id = ? AND eliminado = false");
            $stEst->execute([$idEst]);
            $estRow = $stEst->fetch();
            $estabCodigo = $estRow ? $estRow['codigo'] : '001';

            // Obtener el punto de emisión
            $st = $db->prepare("SELECT id, codigo_punto FROM empresa_punto_emision WHERE id_establecimiento = ? AND eliminado = false ORDER BY codigo_punto ASC LIMIT 1");
            $st->execute([$idEst]);
            $punto = $st->fetch();

            if (!$punto) {
                return "No se generó retención: No hay un punto de emisión configurado.";
            }

            // 7. Preparar datos finales
            $hoy = new \DateTime();
            $diff = $hoy->diff($fDoc);
            
            // Si han pasado más de 5 días, usamos la fecha del documento para cumplir la regla del SRI
            $fechaRetencion = ($diff->days > 5) ? $fechaEmisionDoc : $hoy->format('Y-m-d');

            $retData = [
                'id_empresa'         => $idEmpresa,
                'id_proveedor'       => $idProv,
                'id_usuario'         => $idUsuario,
                'id_compra'          => $idCompra,
                'id_establecimiento' => $idEst,
                'establecimiento'    => $estabCodigo,
                'id_punto_emision'   => (int)$punto['id'],
                'punto_emision'      => (string)$punto['codigo_punto'],
                'fecha_emision'      => $fechaRetencion,
                'tipo_doc_sustento'  => '01',
                'num_doc_sustento'   => $numDocSustento,
                'fecha_emision_doc_sustento' => $fechaEmisionDoc,
                'periodo_fiscal'     => $periodoFiscal,
                'lineas'             => $lineas,
                'estado'             => 'borrador'
            ];

            $this->retencionService->crear($retData);
            
            $logMsg = "Retención automática generada con éxito para Compra $idCompra.";
            file_put_contents(dirname(__DIR__, 3) . '/storage/logs/retenciones_auto.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: $logMsg\n", FILE_APPEND);
            
            return "Retención automática generada con éxito.";

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            file_put_contents(dirname(__DIR__, 3) . '/storage/logs/retenciones_auto.log', "[" . date('Y-m-d H:i:s') . "] ERROR (Compra $idCompra): $errorMsg\n", FILE_APPEND);
            
            // Limpiar mensaje de error para el usuario si contiene palabras técnicas
            $userMsg = str_replace(['Duplicate entry', 'violates unique constraint'], 'Ya existe un registro similar', $errorMsg);
            return "No se generó retención: " . $userMsg;
        }
    }

    /**
     * Intenta auto-generar un egreso para liquidar una compra registrada desde el SRI, 
     * si el proveedor posee configurada forma de pago y cumple los límites establecidos.
     */
    private function generarEgresoAutomatico(int $idCompra, int $idProv, int $idEmpresa, int $idUsuario, float $totalCompra, string $numDocCompleto, string $fechaEmisionDoc): string
    {
        try {
            $db = Database::getConnection();
            
            // 1. Consultar el proveedor y su configuración de pagos y retenciones
            $stProv = $db->prepare("
                SELECT id_forma_pago_predeterminada, tipo_operacion_bancaria_predeterminada, monto_maximo_auto_pago,
                       id_retencion_renta, id_retencion_iva, id_egreso_concepto_predeterminado
                FROM proveedores 
                WHERE id = ? AND id_empresa = ? LIMIT 1
            ");
            $stProv->execute([$idProv, $idEmpresa]);
            $prov = $stProv->fetch(PDO::FETCH_ASSOC);
            
            if (!$prov || empty($prov['id_forma_pago_predeterminada'])) {
                return ""; // Sin forma de pago predeterminada, abortamos en silencio
            }

            // NUEVA REGLA: Si el proveedor tiene alguna retención asociada, omitimos el egreso automático
            if (!empty($prov['id_retencion_renta']) || !empty($prov['id_retencion_iva'])) {
                return " | Pago automático omitido: El proveedor posee retenciones configuradas.";
            }

            // NUEVA REGLA: Resolver concepto de egreso predeterminado o fallback por comportamiento
            $idEgresoConcepto = !empty($prov['id_egreso_concepto_predeterminado']) ? (int)$prov['id_egreso_concepto_predeterminado'] : null;
            if (!$idEgresoConcepto) {
                $stC = $db->prepare("
                    SELECT id FROM empresa_opciones_ingreso_egreso 
                    WHERE id_empresa = ? AND aplica_egresos = TRUE AND comportamiento = 'COMPRA' AND eliminado = FALSE 
                    ORDER BY id ASC LIMIT 1
                ");
                $stC->execute([$idEmpresa]);
                $cRow = $stC->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $idEgresoConcepto = (int)$cRow['id'];
                }
            }
            if (!$idEgresoConcepto) {
                return " | Pago falló: Se requiere un concepto de egreso para COMPRA.";
            }
            
            $idFormaPago = (int) $prov['id_forma_pago_predeterminada'];
            $montoMaximo = !empty($prov['monto_maximo_auto_pago']) ? (float)$prov['monto_maximo_auto_pago'] : null;
            $tipoOp = !empty($prov['tipo_operacion_bancaria_predeterminada']) ? $prov['tipo_operacion_bancaria_predeterminada'] : null;
            
            // 2. Comprobar el límite máximo para auto-generar (si es cero o nulo, no hay restricción)
            if ($montoMaximo !== null && $montoMaximo > 0.001 && $totalCompra > ($montoMaximo + 0.001)) {
                return " | Pago omitido ($" . number_format($totalCompra, 2) . " supera límite de $" . number_format($montoMaximo, 2) . ").";
            }
            
            // 3. Localizar el primer punto de emisión y establecimiento activo de la empresa
            $stPto = $db->prepare("
                SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
                WHERE es.id_empresa = ? AND pe.eliminado = FALSE AND es.eliminado = FALSE
                ORDER BY es.codigo ASC, pe.codigo_punto ASC LIMIT 1
            ");
            $stPto->execute([$idEmpresa]);
            $pto = $stPto->fetch(PDO::FETCH_ASSOC);
            
            if (!$pto) {
                return " | Pago falló: No se localizó punto de emisión activo.";
            }
            
            $idPunto = (int)$pto['id_punto'];
            $idEstab = (int)$pto['id_estab'];
            $codEstab = (string)$pto['estab'];
            $codPunto = (string)$pto['punto'];
            
            // 4. Obtener el secuencial automático del sistema
            $secService = new \App\Services\SecuencialService();
            $resSec = $secService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
            
            if (empty($resSec['secuencial'])) {
                return " | Pago falló: Error al reservar secuencial correlativo.";
            }
            
            $secuencial   = $resSec['formateado'] ?? str_pad((string)$resSec['secuencial'], 9, '0', STR_PAD_LEFT);
            $numeroEgreso = "{$codEstab}-{$codPunto}-{$secuencial}";
            
            // 5. Instanciación segura de la capa oficial de Egresos
            $egresoRepo   = new \App\repositories\modulos\EgresoRepository();
            $egresoRules  = new \App\Rules\modulos\EgresoRules();
            $logService   = new LogSistemaService();
            $egresoService = new \App\Services\modulos\EgresoService($egresoRepo, $egresoRules, $logService);
            
            $fechaHoy = date('Y-m-d');
            
            // 6. Ensamblado del payload estandarizado del Egreso
            $dataEgreso = [
                'id_empresa'         => $idEmpresa,
                'usuario_id'         => $idUsuario,
                'fecha_emision'      => $fechaEmisionDoc,
                'establecimiento'    => $codEstab,
                'punto_emision'      => $codPunto,
                'secuencial'         => $secuencial,
                'numero_egreso'      => $numeroEgreso,
                'id_punto_emision'   => $idPunto,
                'id_establecimiento' => $idEstab,
                'tipo_egreso'        => 'COMPRA',
                'tipo_sujeto'        => 'PROVEEDOR',
                'id_proveedor'       => $idProv,
                'id_egreso_concepto' => $idEgresoConcepto,
                'monto_total'        => $totalCompra,
                'observaciones'      => "Pago generado automáticamente por la descarga de Compra #" . $numDocCompleto . ".",
                'estado'             => 'registrado',
                'detalles'           => [
                    [
                        'tipo_documento'           => 'COMPRA',
                        'id_referencia_documento'  => $idCompra,
                        'numero_documento'         => $numDocCompleto,
                        'descripcion'              => "Liquidación de Compra #" . $numDocCompleto,
                        'monto_documento'          => $totalCompra,
                        'saldo_anterior'           => $totalCompra,
                        'monto_pagado'             => $totalCompra,
                        'saldo_actual'             => 0.0
                    ]
                ],
                'pagos' => [
                    [
                        'id_forma_pago'           => $idFormaPago,
                        'monto'                   => $totalCompra,
                        'tipo_operacion_bancaria' => $tipoOp,
                        'fecha_cobro'             => $fechaEmisionDoc
                    ]
                ]
            ];
            
            // 7. Registrar el egreso ejecutando todas las validaciones de negocio
            $idEgreso = $egresoService->registrar($dataEgreso);
            
            return " | Pago automático generado: " . $numeroEgreso . ".";
            
        } catch (\Throwable $e) {
            // Interceptamos la excepción para NO detener el registro de la compra si falla el egreso.
            // El error quedará documentado visualmente en el historial del log SRI.
            return " | Fallo al generar Pago automático: " . $e->getMessage();
        }
    }
}
