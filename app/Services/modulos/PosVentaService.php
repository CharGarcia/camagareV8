<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\models\FormaPagoSri;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\FormaPagoRepository;
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
    private FormaPagoRepository $formaPagoRepo;
    private ClienteRepository $clienteRepo;
    private FormaPagoSri $formaPagoSri;
    private PDO $db;

    public function __construct(CajaSesionService $cajaService, LogSistemaService $logService)
    {
        $this->cajaService = $cajaService;
        $this->logService = $logService;
        $this->reciboRepo = new ReciboVentaRepository();
        $this->formaPagoRepo = new FormaPagoRepository();
        $this->clienteRepo = new ClienteRepository();
        $this->formaPagoSri = new FormaPagoSri();
        $this->db = Database::getConnection();
    }

    /**
     * Turno de caja abierto de la empresa, sin exigir un punto de emisión. Lo
     * usan las comandas: el mesero no elige caja al abrir una mesa, pero la
     * comanda tiene que quedar atada a un turno porque de ahí sale el punto de
     * emisión con el que se factura al cobrar.
     */
    public function getSesionAbiertaEmpresa(int $idEmpresa): ?array
    {
        return $this->cajaService->getSesionAbiertaEmpresa($idEmpresa);
    }

    /**
     * ¿Ese turno sigue abierto y es de esta empresa? Se valida antes de atarle
     * una comanda: de ese turno sale el punto de emisión con el que se factura,
     * y el salón puede tener varios puntos abiertos al mismo tiempo.
     */
    public function esSesionAbiertaDeEmpresa(int $idCajaSesion, int $idEmpresa): bool
    {
        return $this->cajaService->esSesionAbiertaDeEmpresa($idCajaSesion, $idEmpresa);
    }

    /**
     * Recargo por servicio configurado para la empresa, resuelto ya con sus
     * reglas. Vive aquí porque lo comparten los dos puntos de venta: el salón
     * (comandas) y el mostrador (caja-pos).
     *
     * modo: 'no' | 'obligatorio' | 'opcional'. Sale 'no' —aunque esté
     * configurado— en dos casos:
     *  - "Mostrar el campo de propina en la factura" está apagado: el recargo
     *    se emite EN ese campo, sin él no hay dónde ponerlo.
     *  - La migración del recargo todavía no se ejecutó.
     */
    public function getConfigServicio(int $idEmpresa): array
    {
        $establecimientos = (new \App\models\Empresa())->getEstablecimientos($idEmpresa);
        $idEst = (int) ($establecimientos[0]['id'] ?? 0);
        $cfg = $idEst > 0
            ? (new \App\repositories\modulos\EmpresaRepository())->getConfigServicioRestaurante($idEst)
            : [];

        $propinaActiva = in_array((string) ($cfg['mostrar_propina_factura'] ?? 'false'), ['t', 'true', '1'], true)
            || ($cfg['mostrar_propina_factura'] ?? false) === true;

        $modo = (string) ($cfg['servicio_restaurante'] ?? 'no');
        if (!$propinaActiva || !in_array($modo, ['no', 'obligatorio', 'opcional'], true)) {
            $modo = 'no';
        }
        $porcentaje = (float) ($cfg['servicio_restaurante_porcentaje'] ?? 0);
        return [
            'modo'       => $modo,
            'porcentaje' => $modo === 'no' ? 0.0 : min(10.0, max(0.0, $porcentaje)),
            // Producto con el que se emite la propina VOLUNTARIA (una línea más
            // del detalle). Va aparte del recargo de arriba y no depende de él:
            // el salón puede cobrar propina voluntaria sin cobrar el 10%.
            'id_producto_propina' => (int) ($cfg['id_producto_propina'] ?? 0) ?: 0,
        ];
    }

    /**
     * Porcentaje que se debe cobrar en una venta suelta (mostrador), según lo
     * configurado y lo que pidió el cajero. En 'obligatorio' no importa lo que
     * llegue de la pantalla: se cobra igual.
     */
    public function porcentajeServicioVenta(int $idEmpresa, bool $pedidoPorElCajero): float
    {
        $cfg = $this->getConfigServicio($idEmpresa);
        if ($cfg['modo'] === 'no') {
            return 0.0;
        }
        if ($cfg['modo'] === 'obligatorio') {
            return $cfg['porcentaje'];
        }
        return $pedidoPorElCajero ? $cfg['porcentaje'] : 0.0;
    }

    /**
     * Código SRI del <formaPago> de una forma de pago de la empresa, cuando el
     * tipo de esa forma YA lo determina sin ambigüedad. Devuelve null para
     * EFECTIVO/OTRO —tipos que no dicen nada del medio de pago declarado— para
     * que el llamador siga la cascada de resolverCodigoSriPago().
     *
     * Es estática y pública porque el criterio lo comparten los dos puntos de
     * venta (CajaPosController y ComandasController lo usan para etiquetar el
     * combo de formas de pago); tenerlo en tres copias privadas ya había hecho
     * que divergieran.
     */
    public static function codigoSriDeterminante(?array $forma): ?string
    {
        $tipo = strtoupper((string) ($forma['tipo'] ?? ''));
        if ($tipo === 'BANCO') {
            return '20'; // Otros con utilización del sistema financiero (transferencia)
        }
        // Nuvei cobra siempre con tarjeta, igual que una forma de tipo TARJETA:
        // si la forma declara la modalidad se respeta, si no se asume crédito.
        if ($tipo === 'TARJETA' || $tipo === 'NUVEI') {
            return strtoupper((string) ($forma['modalidad_tarjeta'] ?? '')) === 'DEBITO' ? '16' : '19';
        }
        if ($tipo === 'PAYPHONE') {
            return '19'; // Payphone no distingue modalidad; se asume tarjeta de crédito
        }
        return null; // EFECTIVO, OTRO o sin forma: lo decide la cascada
    }

    /**
     * Código SRI con el que se emite el comprobante del POS.
     *
     * Se resuelve SIEMPRE en el servidor a partir del id de la forma de pago
     * (`empresa_formas_pago`), nunca del código que mande el navegador: el
     * comprobante es fiscal y el dato no puede depender de un campo del DOM.
     *
     * Precedencia:
     *   1. El TIPO de la forma de pago cobrada, cuando ya determina el medio
     *      (BANCO → 20, TARJETA/NUVEI → 16/19, PAYPHONE → 19). Manda sobre lo
     *      demás: una venta con tarjeta no puede declararse como efectivo.
     *   2. La forma de pago SRI de la ficha del CLIENTE
     *      (`clientes.id_forma_pago_sri`).
     *   3. La configurada en el ESTABLECIMIENTO
     *      (`empresa_establecimiento.id_forma_pago_sri_def`, Empresa →
     *      Facturación).
     *   4. '01' — sin utilización del sistema financiero.
     *
     * Los pasos 2 y 3 son la misma cascada que ya aplican la pantalla de
     * Factura de Venta y CargaFacturasValidacionService::resolverFormaPago().
     * Antes el POS no los consultaba: cobrar en efectivo emitía siempre '01',
     * aunque el cliente o la empresa tuvieran otra forma configurada.
     */
    public function resolverCodigoSriPago(
        int $idEmpresa,
        int $idFormaPagoEmpresa,
        int $idCliente,
        array $empresaConfig,
        int $idEstablecimiento = 0
    ): string {
        $forma = $idFormaPagoEmpresa > 0
            ? $this->formaPagoRepo->getPorId($idFormaPagoEmpresa, $idEmpresa)
            : null;
        $codigo = self::codigoSriDeterminante($forma);
        if ($codigo !== null) {
            return $codigo;
        }

        if ($idCliente > 0) {
            $cliente = $this->clienteRepo->getPorId($idCliente, $idEmpresa);
            $codigo = $this->formaPagoSri->getCodigoPorId((int) ($cliente['id_forma_pago_sri'] ?? 0));
            if ($codigo !== null) {
                return $codigo;
            }
        }

        // La config del establecimiento normalmente ya viaja fusionada en
        // $empresaConfig (getEmpresaConfig() de los dos controladores del POS).
        // Se relee solo si el llamador no la trae — p. ej. el cobro que dispara
        // Payphone desde el portal público.
        $idDefEst = (int) ($empresaConfig['id_forma_pago_sri_def'] ?? 0);
        if ($idDefEst <= 0 && !array_key_exists('id_forma_pago_sri_def', $empresaConfig) && $idEstablecimiento > 0) {
            try {
                $cfg = (new EmpresaRepository())->getEstablecimientoConfig($idEstablecimiento);
                $idDefEst = (int) ($cfg['id_forma_pago_sri_def'] ?? 0);
            } catch (\Throwable $e) {
                $idDefEst = 0;
            }
        }
        $codigo = $this->formaPagoSri->getCodigoPorId($idDefEst);

        return $codigo ?? '01';
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
        // Suma de las líneas marcadas como propina voluntaria: se factura como
        // una línea normal, pero queda fuera de la base del recargo por servicio.
        $totalPropinaItems = 0.0;
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
            // Excepción: si el llamador manda 'id_tarifa_iva', esa tarifa gana. La
            // usan las comandas, donde el ítem de la carta define con qué IVA se
            // vende el plato (ver ComandaRepository::SQL_SELECT_IVA); así el
            // comprobante sale con la misma tarifa que vio el mesero en pantalla.
            // Los demás flujos no mandan la clave y siguen resolviendo por producto.
            $tar = $this->reciboRepo->getTarifaIvaProducto($idProducto);
            $idTarItem = (int) ($it['id_tarifa_iva'] ?? 0);
            if ($idTarItem > 0 && (!$tar || $idTarItem !== (int) $tar['id'])) {
                $tarItem = $this->reciboRepo->getTarifaIvaById($idTarItem);
                if ($tarItem) {
                    // codigo_producto sigue saliendo del producto: la tarifa solo
                    // reemplaza el impuesto, no la identidad del ítem facturado.
                    $tarItem['codigo_producto'] = $tar['codigo_producto'] ?? '';
                    $tar = $tarItem;
                }
            }
            $pct = $tar ? (float) $tar['porcentaje_iva'] : 0.0;
            $codPct = $tar ? (string) $tar['codigo'] : '0';
            $idTar = $tar ? (int) $tar['id'] : 0;
            $ivaLinea = round($base * $pct / 100, 2);

            $totalSinImp += $base;
            $totalDesc += $dscto;
            $ivaTotal += $ivaLinea;
            if (!empty($it['es_propina'])) {
                $totalPropinaItems += $base;
            }

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
                'id_producto_variante' => !empty($it['id_producto_variante']) ? (int) $it['id_producto_variante'] : null,
                'id_unidad_medida' => !empty($it['id_unidad_medida']) ? (int) $it['id_unidad_medida'] : null,
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

        // Recargo por servicio ("el 10%"), que viaja al comprobante como
        // PROPINA: se suma DESPUÉS del IVA porque no forma base imponible.
        // Se recibe el PORCENTAJE, no el monto: quien cobra (salón o mostrador)
        // decide si aplica, pero el valor lo calcula siempre este servicio
        // sobre el subtotal real del documento. Así la propina nunca puede
        // superar el 10% que exige la Ficha Técnica del SRI —el comprobante
        // sería rechazado— ni depender de una cifra armada en el navegador.
        // La base del recargo es el CONSUMO, no el total de las líneas: si el
        // cliente dejó una propina voluntaria (que viaja como una línea más del
        // detalle, ver ComandaService::guardarPropina), esa línea no puede
        // inflar el recargo. Con consumo 80 + IVA 12 + servicio 8 = 100, una
        // propina de 5 tiene que dar 105 y no 105.50.
        $pctPropina = min(10.0, max(0.0, (float) ($data['porcentaje_propina'] ?? 0)));
        $baseServicio = round($totalSinImp - $totalPropinaItems, 2);
        if ($baseServicio < 0) $baseServicio = 0.0;
        $propina = round($baseServicio * $pctPropina / 100, 2);

        $importeTotal = round($totalSinImp + $ivaTotal + $propina, 2);

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

        $idFormaPagoEmpresa = (int) ($data['id_forma_pago_empresa'] ?? 0);
        // El 'forma_pago' que manda la pantalla se ignora a propósito: el código
        // que va al comprobante lo decide el servidor (ver resolverCodigoSriPago()).
        $formaPago = $this->resolverCodigoSriPago(
            $idEmpresa,
            $idFormaPagoEmpresa,
            $idCliente,
            $empresaConfig,
            (int) $puntoInfo['id_establecimiento']
        );

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (FacturaVentaService::crear() / ReciboVentaService::crear()): el lock de
        // obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $managedTransaction = !$this->db->inTransaction();
        if ($managedTransaction) {
            $this->db->beginTransaction();
        }

        try {
        $tipoDocSec = $tipoDocumento === 'FACTURA' ? 'Facturas de venta' : 'Recibos de venta';
        $sec = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, $tipoDocSec);
        $secuencial = $sec['formateado'];
        $numeroDoc = $puntoInfo['cod_establecimiento'] . '-' . $puntoInfo['codigo_punto'] . '-' . $secuencial;


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
            'propina' => $propina,
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
        if ($managedTransaction) {
            $this->db->commit();
        }
        } catch (\Throwable $e) {
            if ($managedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
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

        // Autorización del SRI, al final de todo: la venta, su inventario y su
        // Ingreso ya están firmes, así que nada de lo que pase aquí puede
        // deshacerlos. Solo cuando el llamador lo pide — ver
        // autorizarDocumentoEnSri().
        $sri = !empty($data['autorizar_sri'])
            ? $this->autorizarDocumentoEnSri($idDoc, $tipoDocumento, $numeroDoc, $idEmpresa, $idUsuario)
            : null;

        return [
            'id_documento' => $idDoc,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDoc,
            'importe_total' => $importeTotal,
            // Código SRI realmente emitido, para que quien llamó registre ESE y
            // no el que había propuesto la pantalla (ver resolverCodigoSriPago).
            'forma_pago' => $formaPago,
            'id_ingreso' => $idIngreso,
            'aviso_ingreso' => $avisoIngreso,
            'sri' => $sri,
        ];
    }

    /**
     * Envía al SRI la factura recién emitida y espera su autorización, para que
     * la tirilla que se imprime a continuación salga ya con el número de
     * autorización. Nunca lanza: el documento ya está emitido y cobrado.
     *
     * Devuelve null cuando no corresponde intentarlo:
     *   · Recibo de venta — no es comprobante electrónico.
     *   · Empresa sin certificado de firma activo — si no, cada cobro terminaría
     *     con un aviso de error en pantalla en los locales que aún no lo subieron.
     *
     * Se invoca de dos maneras, y la diferencia importa:
     *   · **POS mostrador**: con `autorizar_sri` en el payload de cobrar(), que
     *     lo llama al final — ahí no queda nada pendiente después de emitir.
     *   · **Cobro del salón**: ComandaService lo llama por su cuenta DESPUÉS de
     *     cerrar el grupo de cobro. No puede ir dentro de cobrar(): si el SRI
     *     tarda y PHP corta la petición, el grupo quedaría abierto con la
     *     factura ya emitida y el mesero podría cobrarlo por segunda vez.
     * El cobro que dispara Payphone no lo usa por ninguna de las dos vías: corre
     * dentro de la página de retorno del cliente, y sumarle la espera del SRI
     * arriesga que PHP corte esa petición.
     *
     * Tiempos de espera cortos a propósito (2/3/2 en vez de los 3/5/3 por
     * defecto de SriEnvioService): el caso normal resuelve en pocos segundos y,
     * si el SRI no contesta a tiempo, se devuelve el estado tal cual y la
     * pantalla deja imprimir sin autorización.
     */
    public function autorizarDocumentoEnSri(
        int $idDocumento,
        string $tipoDocumento,
        string $numeroDoc,
        int $idEmpresa,
        int $idUsuario
    ): ?array {
        if ($tipoDocumento !== 'FACTURA' || $idDocumento <= 0) {
            return null;
        }

        try {
            $svc = new \App\Services\Sri\SriEnvioService(
                esperaInicial:       2,
                maxIntentos:         3,
                intervaloReintentos: 2
            );

            if (!$svc->getFirmaConfig($idEmpresa)) {
                return null;
            }

            $r = $svc->enviarFacturaVenta($idDocumento, $idEmpresa, $idUsuario);

            return [
                'ok'                  => (bool) ($r['ok'] ?? false),
                'estado'              => (string) ($r['estado'] ?? 'error'),
                'mensaje'             => (string) ($r['mensaje'] ?? ''),
                'numero_autorizacion' => (string) ($r['numero_autorizacion'] ?? ''),
                'fecha_autorizacion'  => (string) ($r['fecha_autorizacion'] ?? ''),
                'errores'             => $r['errores'] ?? [],
            ];
        } catch (\Throwable $e) {
            error_log('[PosVentaService] Documento ' . $numeroDoc . ' emitido, pero falló el envío al SRI: ' . $e->getMessage());
            return [
                'ok'      => false,
                'estado'  => 'error',
                'mensaje' => $e->getMessage(),
                'errores' => [],
            ];
        }
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
        // Sin forma de cobro no hay dónde registrar el dinero. Se lanza en vez de
        // devolver null en silencio: el llamador lo convierte en 'aviso_ingreso'
        // y la pantalla lo muestra. Devolver null dejaba facturas cobradas con su
        // Cuenta por Cobrar abierta sin que nadie se enterara — solo quedaba
        // rastro en error_log (pasó con 001-101-000000130 y 000000131).
        if ($idFormaPagoEmpresa <= 0) {
            throw new Exception(
                'no hay una forma de pago seleccionada para el cobro ' .
                '(configúrelas en Formas de Cobros y Pagos).'
            );
        }

        $stCli = $this->db->prepare("SELECT nombre FROM clientes WHERE id = :id AND id_empresa = :id_empresa");
        $stCli->execute([':id' => $idCliente, ':id_empresa' => $idEmpresa]);
        $nombreCliente = (string) ($stCli->fetchColumn() ?: 'Consumidor Final');

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (IngresoService::crear()): el lock de obtenerSiguienteSecuencial() se libera
        // solo al COMMIT/ROLLBACK (CLAUDE.md §8). Independiente de la transacción de la venta
        // (el llamador ya tolera que esto falle sin revertir la venta).
        $managedTransaction = !$this->db->inTransaction();
        if ($managedTransaction) {
            $this->db->beginTransaction();
        }

        try {
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
        // true: el documento se acaba de emitir aquí mismo. Sin esto el Ingreso se
        // rechaza cuando la factura todavía está en 'borrador' (pendiente del SRI),
        // porque la búsqueda de documentos pendientes solo mira los autorizados y
        // devuelve saldo 0. Ver IngresoService::crear().
        $idIngreso = $ingresoService->crear($payload, true);
        if ($managedTransaction) {
            $this->db->commit();
        }
        return $idIngreso;
        } catch (\Throwable $e) {
            if ($managedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
