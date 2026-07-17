<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\IngresoRepository;
use App\repositories\modulos\ReciboVentaRepository;
use App\Rules\modulos\FacturaVentaRules;
use App\Rules\modulos\IngresoRules;
use App\Rules\modulos\ReciboVentaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use Exception;
use PDO;

/**
 * Venta rápida del Punto de Venta: arma el carrito y lo cobra generando una
 * Factura o un Recibo de Venta, reutilizando FacturaVentaService/
 * ReciboVentaService::crear() — mismo patrón (y misma elección de tipo) que
 * OrdenCarWashService::generarDocumento(). Exige una caja_sesiones abierta
 * para el punto de emisión. La Factura queda creada (pendiente de enviar al
 * SRI desde el módulo Facturas de Venta); el POS no envía al SRI por su cuenta.
 */
class PosVentaService
{
    private CajaSesionService $cajaService;
    private LogSistemaService $logService;
    private ReciboVentaRepository $reciboRepo;
    private PDO $db;

    public function __construct(CajaSesionService $cajaService, LogSistemaService $logService)
    {
        $this->cajaService = $cajaService;
        $this->logService = $logService;
        $this->reciboRepo = new ReciboVentaRepository();
        $this->db = Database::getConnection();
    }

    public function cobrar(array $data, array $empresaConfig): array
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $idPuntoEmision = (int) $data['id_punto_emision'];

        $sesion = $this->cajaService->getSesionAbierta($idEmpresa, $idPuntoEmision);
        if (!$sesion) {
            throw new Exception('No hay una caja abierta para este punto de emisión. Abre el turno antes de vender.');
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new Exception('El carrito está vacío.');
        }

        $puntoInfo = $this->getPuntoEmisionInfo($idPuntoEmision);
        if (!$puntoInfo) {
            throw new Exception('El punto de emisión no es válido.');
        }

        $idBodega = (int) ($data['id_bodega'] ?? 0);
        if ($idBodega > 0) {
            if (!$this->bodegaPerteneceAEmpresa($idBodega, $idEmpresa)) {
                throw new Exception('La bodega seleccionada no es válida.');
            }
        } else {
            $idBodega = $this->getBodegaActiva($idEmpresa);
        }

        $det = [];
        $totalSinImp = 0.0;
        $totalDesc = 0.0;
        $ivaTotal = 0.0;
        foreach ($items as $it) {
            $idProducto = (int) ($it['id_producto'] ?? 0);
            $cant = (float) ($it['cantidad'] ?? 0);
            if ($idProducto <= 0 || $cant <= 0) {
                continue;
            }
            $precio = (float) ($it['precio_unitario'] ?? 0);
            $dscto = (float) ($it['descuento'] ?? 0);
            $base = round($precio * $cant - $dscto, 2);
            if ($base < 0) {
                $base = 0.0;
            }

            // Resolver IVA desde el producto (misma fuente de verdad que Recibos/Facturas).
            $tar = $this->reciboRepo->getTarifaIvaProducto($idProducto);
            $pct = $tar ? (float) $tar['porcentaje_iva'] : 0.0;
            $codPct = $tar ? (string) $tar['codigo'] : '0';
            $idTar = $tar ? (int) $tar['id'] : 0;
            $ivaLinea = round($base * $pct / 100, 2);

            $totalSinImp += $base;
            $totalDesc += $dscto;
            $ivaTotal += $ivaLinea;

            $descripcion = (string) ($it['descripcion'] ?? '');
            $det[] = [
                'id_producto' => $idProducto,
                'id_bodega' => $idBodega,
                'codigo_principal' => (string) ($tar['codigo_producto'] ?? ''),
                'descripcion' => $descripcion,
                'nombre' => $descripcion,
                'cantidad' => $cant,
                'precio_unitario' => $precio,
                'descuento' => $dscto,
                'precio_total_sin_impuesto' => $base,
                'id_tarifa_iva' => $idTar,
                'es_libre' => 0,
                'porcentaje_iva' => $pct,
                'lote' => (string) ($it['lote'] ?? ''),
                'caducidad' => (string) ($it['caducidad'] ?? ''),
                'nup' => (string) ($it['nup'] ?? ''),
                'impuestos' => [[
                    'codigo_impuesto' => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa' => $pct,
                    'base_imponible' => $base,
                    'valor' => $ivaLinea,
                ]],
            ];
        }

        if (empty($det)) {
            throw new Exception('No hay líneas válidas en el carrito.');
        }

        $totalSinImp = round($totalSinImp, 2);
        $totalDesc = round($totalDesc, 2);
        $ivaTotal = round($ivaTotal, 2);
        $importeTotal = round($totalSinImp + $ivaTotal, 2);

        $idCliente = (int) ($data['id_cliente'] ?? 0);
        if ($idCliente > 0) {
            if (!$this->clientePerteneceAEmpresa($idCliente, $idEmpresa)) {
                throw new Exception('El cliente seleccionado no es válido.');
            }
        } else {
            $idCliente = $this->getClienteConsumidorFinal($idEmpresa);
        }

        // Mismo límite de "Consumidor Final" que exige Facturación (empresa →
        // Facturación → valor_limite_consumidor_final, $50 si no está configurado).
        // ReciboVentaRules no lo valida por su cuenta, así que el POS lo hace aquí.
        $clienteInfo = $this->reciboRepo->getTipoIdCliente($idCliente, $idEmpresa);
        $esConsumidorFinal = ($clienteInfo['es_consumidor_final'] ?? false) || (($clienteInfo['tipo_id'] ?? '') === '07');
        if ($esConsumidorFinal) {
            $limiteCF = (float) ($empresaConfig['valor_limite_consumidor_final'] ?? 50);
            if ($importeTotal >= $limiteCF) {
                throw new Exception(
                    'Venta a Consumidor Final: máximo $' . number_format($limiteCF, 2) .
                    '. Selecciona o crea un cliente para continuar.'
                );
            }
        }

        $tipoDocumento = strtoupper((string) ($data['tipo_documento'] ?? 'RECIBO'));
        if (!in_array($tipoDocumento, ['RECIBO', 'FACTURA'], true)) {
            $tipoDocumento = 'RECIBO';
        }

        $tipoDocSec = $tipoDocumento === 'FACTURA' ? 'Facturas de venta' : 'Recibos de venta';
        $sec = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, $tipoDocSec);
        $secuencial = $sec['formateado'];
        $numeroDoc = $puntoInfo['cod_establecimiento'] . '-' . $puntoInfo['codigo_punto'] . '-' . $secuencial;

        $formaPago = (string) ($data['forma_pago'] ?? '01');
        $idFormaPagoEmpresa = (int) ($data['id_forma_pago_empresa'] ?? 0);

        // Dato informativo (no cambia el código SRI del pago) — mismo campo
        // y catálogo que ya usan Ingresos/Factura de Venta/Recibos de Venta
        // para el pago tipo BANCO: TRANSFERENCIA, DEPOSITO, DEBITO, CHEQUE.
        $infoAdicional = [];
        $tipoOperacionBancaria = strtoupper((string) ($data['tipo_operacion_bancaria'] ?? ''));
        if ($tipoOperacionBancaria !== '') {
            $detalleBanco = ucfirst(strtolower($tipoOperacionBancaria));
            $numeroOperacion = trim((string) ($data['numero_operacion'] ?? ''));
            if ($numeroOperacion !== '') {
                $detalleBanco .= ' — Ref: ' . $numeroOperacion;
            }
            if ($tipoOperacionBancaria === 'CHEQUE' && !empty($data['fecha_cobro'])) {
                $detalleBanco .= ' — Cobra: ' . $data['fecha_cobro'];
            }
            $infoAdicional[] = ['nombre' => 'Operación Bancaria', 'valor' => $detalleBanco];
        }

        $payload = [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'empresa_config' => $empresaConfig,
            'id_establecimiento' => (int) $puntoInfo['id_establecimiento'],
            'id_punto_emision' => $idPuntoEmision,
            'establecimiento' => $puntoInfo['cod_establecimiento'],
            'punto_emision' => $puntoInfo['codigo_punto'],
            'secuencial' => $secuencial,
            'fecha_emision' => date('Y-m-d'),
            'id_cliente' => $idCliente,
            'id_vendedor' => null,
            'dias_credito' => 0,
            'moneda' => 'DOLAR',
            'observaciones' => 'Venta POS — turno #' . $sesion['id'],
            'id_caja_sesion' => (int) $sesion['id'],
            'id_bodega' => $idBodega,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento' => $totalDesc,
            'total_ice' => 0,
            'propina' => 0,
            'importe_total' => $importeTotal,
            'detalles' => $det,
            'pagos' => [[
                'forma_pago' => $formaPago,
                'total' => $importeTotal,
                'plazo' => 0,
                'unidad_tiempo' => 'dias',
            ]],
            'info_adicional' => $infoAdicional,
        ];

        if ($tipoDocumento === 'FACTURA') {
            $svc = new FacturaVentaService(
                new FacturaVentaRepository(),
                new FacturaVentaRules(),
                $this->logService
            );
            $idDoc = $svc->crear($payload);
        } else {
            $payload['con_impuestos'] = true;
            $payload['estado'] = 'borrador';
            $payload['plazo'] = 0;
            $svc = new ReciboVentaService(
                new ReciboVentaRepository(),
                new ReciboVentaRules(),
                $this->logService
            );
            $idDoc = $svc->crear($payload);
        }

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'VENTA_POS',
            'caja_sesiones',
            (int) $sesion['id'],
            null,
            [
                'tipo_documento' => $tipoDocumento,
                'id_documento' => $idDoc,
                'numero_documento' => $numeroDoc,
                'importe_total' => $importeTotal,
                'forma_pago_sri' => $formaPago,
                'id_forma_pago_empresa' => $idFormaPagoEmpresa ?: null,
            ]
        );

        // El cobro ya quedó completo en el documento (ventas_pagos/recibos_venta_pagos),
        // pero eso NO limpia la Cuenta por Cobrar que su propio asiento generó (todo
        // documento debita CxC, cobrado o no — igual que Factura/Recibo fuera del POS).
        // Se genera aquí el mismo "Ingreso" que el cajero tendría que crear a mano desde
        // el módulo Ingresos para that. Si falla (p. ej. sin forma de cobro configurada o
        // sin cuenta contable para el concepto), la venta ya está guardada: no se revierte,
        // solo se avisa para que se registre manualmente.
        $idIngreso = null;
        $avisoIngreso = null;
        try {
            $idIngreso = $this->generarIngresoAutomatico(
                $idEmpresa,
                $idUsuario,
                $puntoInfo,
                $idPuntoEmision,
                $idCliente,
                $tipoDocumento,
                $idDoc,
                $numeroDoc,
                $importeTotal,
                (int) $sesion['id'],
                $idFormaPagoEmpresa,
                $tipoOperacionBancaria,
                trim((string) ($data['numero_operacion'] ?? '')),
                trim((string) ($data['fecha_cobro'] ?? ''))
            );
        } catch (\Throwable $e) {
            $avisoIngreso = 'La venta se registró correctamente, pero no se pudo generar el Ingreso automático: ' . $e->getMessage();
            error_log('[PosVentaService] No se pudo generar el Ingreso de ' . $numeroDoc . ': ' . $e->getMessage());
        }

        return [
            'id_documento' => $idDoc,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDoc,
            'importe_total' => $importeTotal,
            'id_ingreso' => $idIngreso,
            'aviso_ingreso' => $avisoIngreso,
        ];
    }

    /**
     * Genera el "Ingreso" (cobro de tesorería) que limpia la Cuenta por Cobrar que dejó
     * el asiento de la Factura/Recibo — mismo dato que un cajero registraría a mano desde
     * el módulo Ingresos al cobrar esa factura/recibo pendiente (ver
     * IngresosController::registrarCobroRapidoAjax, mismo payload). No lleva
     * id_ingreso_concepto: como tipo_ingreso no es 'OTRO', IngresoRules no lo exige; si la
     * empresa no tiene configurada la cuenta contable de Cuentas por Cobrar para este tipo
     * de cobro, el Ingreso igual se crea (queda visible y disponible) y solo su asiento
     * contable no se genera — mismo comportamiento no bloqueante que ya tiene todo asiento
     * en este sistema.
     */
    private function generarIngresoAutomatico(
        int $idEmpresa,
        int $idUsuario,
        array $puntoInfo,
        int $idPuntoEmision,
        int $idCliente,
        string $tipoDocumento,
        int $idDoc,
        string $numeroDoc,
        float $importeTotal,
        int $idSesion,
        int $idFormaPagoEmpresa,
        string $tipoOperacionBancaria,
        string $numeroOperacion,
        string $fechaCobro
    ): ?int {
        if ($idFormaPagoEmpresa <= 0) {
            error_log('[PosVentaService] Ingreso no generado para ' . $numeroDoc . ': no hay una forma de pago de la empresa asociada al cobro.');
            return null;
        }

        $stCli = $this->db->prepare("SELECT nombre FROM clientes WHERE id = :id AND id_empresa = :id_empresa");
        $stCli->execute([':id' => $idCliente, ':id_empresa' => $idEmpresa]);
        $nombreCliente = (string) ($stCli->fetchColumn() ?: 'Consumidor Final');

        $secIngreso = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, 'Ingresos');
        $secuencialIngreso = $secIngreso['formateado'];
        $numeroIngreso = $puntoInfo['cod_establecimiento'] . '-' . $puntoInfo['codigo_punto'] . '-' . $secuencialIngreso;

        $payload = [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'id_establecimiento' => (int) $puntoInfo['id_establecimiento'],
            'id_punto_emision' => $idPuntoEmision,
            'id_cliente' => $idCliente,
            'fecha_emision' => date('Y-m-d'),
            'establecimiento' => $puntoInfo['cod_establecimiento'],
            'punto_emision' => $puntoInfo['codigo_punto'],
            'secuencial' => $secuencialIngreso,
            'numero_ingreso' => $numeroIngreso,
            'tipo_ingreso' => $tipoDocumento === 'FACTURA' ? 'FACTURA_VENTA' : 'RECIBO_VENTA',
            'monto_total' => $importeTotal,
            'observaciones' => 'Cobro inmediato — Venta POS turno #' . $idSesion,
            'recibo_de' => $nombreCliente,
            'id_recibo_cliente' => $idCliente,
            'detalles' => [[
                'tipo_documento' => $tipoDocumento,
                'id_referencia_documento' => $idDoc,
                'numero_documento' => $numeroDoc,
                'descripcion' => 'Venta POS ' . $numeroDoc,
                'monto_documento' => $importeTotal,
                'saldo_anterior' => $importeTotal,
                'monto_cobrado' => $importeTotal,
                'saldo_actual' => 0,
            ]],
            'pagos' => [[
                'id_forma_cobro' => $idFormaPagoEmpresa,
                'monto' => $importeTotal,
                'referencia' => $numeroOperacion !== '' ? $numeroOperacion : null,
                'tipo_operacion_bancaria' => $tipoOperacionBancaria !== '' ? $tipoOperacionBancaria : null,
                'numero_cheque' => $tipoOperacionBancaria === 'CHEQUE' ? $numeroOperacion : null,
                'fecha_cobro' => $fechaCobro !== '' ? $fechaCobro : null,
            ]],
        ];

        $ingresoService = new IngresoService(new IngresoRepository(), new IngresoRules(), $this->logService);
        return $ingresoService->crear($payload);
    }

    private function getPuntoEmisionInfo(int $idPuntoEmision): ?array
    {
        $sql = "SELECT p.id, p.codigo_punto, p.id_establecimiento, e.codigo AS cod_establecimiento
                FROM empresa_punto_emision p
                JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                WHERE p.id = :id AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idPuntoEmision]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Bodega única que usa el POS (primera bodega activa de la empresa).
     * Pública porque el controller también la necesita para consultar lotes.
     */
    public function getBodegaActiva(int $idEmpresa): ?int
    {
        $empresaModel = new \App\models\Empresa();
        $bodegas = $empresaModel->getBodegas($idEmpresa);
        return !empty($bodegas) ? (int) $bodegas[0]['id'] : null;
    }

    private function clientePerteneceAEmpresa(int $idCliente, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM clientes WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCliente, ':id_empresa' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    private function bodegaPerteneceAEmpresa(int $idBodega, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM bodegas WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idBodega, ':id_empresa' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    private function getClienteConsumidorFinal(int $idEmpresa): int
    {
        $sql = "SELECT id FROM clientes
                WHERE id_empresa = :id_empresa AND tipo_id = '07' AND identificacion = '9999999999999' AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        $id = $st->fetchColumn();
        if (!$id) {
            throw new Exception('No se encontró el cliente Consumidor Final en el catálogo de clientes de esta empresa.');
        }
        return (int) $id;
    }
}
