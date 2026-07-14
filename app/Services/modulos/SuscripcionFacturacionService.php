<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Services\SecuencialService;

/**
 * Genera el documento de venta de una suscripción (Factura de Venta o Recibo de
 * Venta, según suscripciones.tipo_comprobante) a partir de su detalle.
 * Es el ÚNICO punto de generación: lo usan tanto el botón manual del módulo
 * (SuscripcionesController::generarFacturasManualAjax) como el cron de
 * automatizaciones (SuscripcionesHandler).
 *
 * NOTA: solo CREA el documento (estado borrador). El envío al SRI de las
 * facturas lo hace la automatización separada "Facturas de venta → Enviar al SRI".
 * El recibo de venta no se envía al SRI.
 */
class SuscripcionFacturacionService
{
    public function __construct(
        private FacturaVentaService $facturaService,
        private SecuencialService   $secService,
        private ReciboVentaService  $reciboService
    ) {}

    /**
     * Genera el documento de UN período de la suscripción.
     *
     * @param array $susc          Fila de la suscripción (id_cliente, forma_cobro, tipo_comprobante, ...)
     * @param array $detalle       Ítems (de SuscripcionesRepository::getDetalle)
     * @param array $estabConfig   Establecimiento + punto de emisión (de getSeriesActivas + datos)
     * @param array $empresaConfig Config de empresa (Empresa::getPorId)
     * @param array  $extras        ['texto_item','info_concepto','info_detalle']
     * @param string $periodoFecha  Fecha del período facturado (Y-m-d) para los placeholders
     * @return array{id_factura:?int, id_recibo:?int, tipo:string, importe:float}
     */
    public function generarUnPeriodo(
        int $idEmpresa,
        int $idUsuario,
        array $susc,
        array $detalle,
        array $estabConfig,
        array $empresaConfig,
        array $extras = [],
        string $periodoFecha = ''
    ): array {
        $reemplazos   = $this->construirReemplazos($periodoFecha, (int)($susc['periodicidad_meses'] ?? 1), (string)($susc['periodicidad_codigo'] ?? ''));
        $textoItem    = strtr(trim($extras['texto_item']   ?? ''), $reemplazos);
        $infoConcepto = strtr(trim($extras['info_concepto'] ?? ''), $reemplazos);
        $infoDetalle  = strtr(trim($extras['info_detalle']  ?? ''), $reemplazos);

        $detallesFactura = [];
        $totalSinImp     = 0.0;
        $totalIva        = 0.0;

        foreach ($detalle as $det) {
            $tarifaIva = (float)($det['porcentaje_iva'] ?? 0);
            $base = round((float)$det['cantidad'] * (float)$det['precio_unitario'], 2);
            $iva  = round($base * ($tarifaIva / 100), 2);
            $totalSinImp += $base;
            $totalIva    += $iva;

            // El SRI exige declarar el IVA en CADA línea, incluso con tarifa 0%.
            // Omitir el impuesto cuando la tarifa era 0 generaba un
            // <totalConImpuestos> vacío y el rechazo "ARCHIVO NO CUMPLE ESTRUCTURA
            // XML ... 'totalImpuesto' is expected". Para tarifa > 0 el código se
            // deriva de la tarifa real; para tarifa 0 se respeta el código guardado
            // de la tarifa (0 = 0%, 6 = no objeto, 7 = exento), con '0' por defecto.
            $codigoPorcentaje = $tarifaIva > 0
                ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tarifaIva)
                : (string)($det['codigo_porcentaje'] ?? '0');

            $detallesFactura[] = [
                'id_producto'               => $det['id_producto'],
                'codigo_principal'          => $det['codigo_producto'] ?? '000',
                'descripcion'               => $det['descripcion'] ?? $det['nombre_producto'],
                'info_adicional'            => $textoItem !== '' ? $textoItem : null,
                'cantidad'                  => $det['cantidad'],
                'precio_unitario'           => $det['precio_unitario'],
                'descuento'                 => 0,
                'precio_total_sin_impuesto' => $base,
                // Guardar la tarifa en la propia línea (igual que una factura manual),
                // para que al re-abrir la factura el IVA se restaure aunque no hubiese
                // filas de impuestos, y para el correcto armado del XML.
                'porcentaje_iva'            => $tarifaIva,
                'id_tarifa_iva'             => !empty($det['id_tarifa_iva']) ? (int)$det['id_tarifa_iva'] : null,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codigoPorcentaje,
                    'tarifa'            => $tarifaIva,
                    'base_imponible'    => $base,
                    'valor'             => $iva,
                ]],
            ];
        }

        $importe = round($totalSinImp + $totalIva, 2);
        if ($importe <= 0) {
            throw new \RuntimeException("La suscripción #{$susc['id']} no tiene ítems con monto.");
        }

        // Tipo de documento configurado en la suscripción. Cada tipo tiene su
        // propio secuencial en el mismo punto de emisión.
        $esRecibo   = ($susc['tipo_comprobante'] ?? 'factura') === 'recibo';
        $tipoSec    = $esRecibo ? 'Recibos de venta' : 'Facturas de venta';
        $secRes     = $this->secService->obtenerSiguienteSecuencial((int)$estabConfig['id_punto_emision'], $tipoSec);
        $secuencial = $secRes['formateado'];

        $infoAdicional = [];

        // Información adicional propia de la suscripción (concepto/detalle),
        // capturada en el modal igual que en la factura de venta.
        $infoSusc = $susc['info_adicional'] ?? null;
        if (is_string($infoSusc) && $infoSusc !== '') {
            $infoSusc = json_decode($infoSusc, true);
        }
        if (is_array($infoSusc)) {
            foreach ($infoSusc as $fila) {
                $nombre = strtr(trim((string)($fila['concepto'] ?? '')), $reemplazos);
                $valor  = strtr(trim((string)($fila['detalle']  ?? '')), $reemplazos);
                if ($nombre !== '' && $valor !== '') {
                    $infoAdicional[] = ['nombre' => $nombre, 'valor' => $valor];
                }
            }
        }

        // Info adicional adicional pasada como override en la generación (lote).
        if ($infoConcepto !== '' && $infoDetalle !== '') {
            $infoAdicional[] = ['nombre' => $infoConcepto, 'valor' => $infoDetalle];
        }

        // Correo del cliente: campo fijo, usa el nombre 'correo del cliente' que la vista
        // reconoce como fila no eliminable (data-tipo="correo-cliente", sin botón borrar).
        $emailCliente = trim((string)($susc['cliente_email'] ?? ''));
        if ($emailCliente !== '') {
            $infoAdicional[] = ['nombre' => 'correo del cliente', 'valor' => $emailCliente];
        }

        $formaPago = ($susc['forma_cobro'] ?? '') === 'tarjeta' ? '16' : '20';

        $documentoData = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'id_cliente'          => $susc['id_cliente'],
            'id_establecimiento'  => $estabConfig['id'],
            'id_punto_emision'    => $estabConfig['id_punto_emision'],
            'id_bodega'           => null,
            'fecha_emision'       => date('Y-m-d'),
            'establecimiento'     => $estabConfig['codigo'],
            'punto_emision'       => $estabConfig['punto_emision_codigo'],
            'secuencial'          => $secuencial,
            'empresa_config'      => $empresaConfig,
            'detalles'            => $detallesFactura,
            'pagos'               => [[
                'forma_pago' => $formaPago,
                'total'      => $importe,
                'plazo'      => 0,
            ]],
            'info_adicional'      => $infoAdicional,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => 0,
            'importe_total'       => $importe,
            'propina'             => 0,
            'observaciones'       => '',
        ];

        if (!$esRecibo) {
            $idFactura = $this->facturaService->crear($documentoData);
            return ['id_factura' => $idFactura, 'id_recibo' => null, 'tipo' => 'factura', 'importe' => $importe];
        }

        // Recibo de venta: mismos ítems/totales; el recibo no va al SRI y nace en
        // borrador. Se emite CON impuestos porque el detalle de la suscripción
        // guarda el IVA por línea (espejo de la factura).
        $documentoData['con_impuestos'] = true;
        $documentoData['estado']        = 'borrador';
        $documentoData['moneda']        = 'DOLAR';
        $documentoData['id_vendedor']   = null;
        $documentoData['dias_credito']  = 0;
        $documentoData['plazo']         = 0;
        $documentoData['total_ice']     = 0;
        $documentoData['pagos'][0]['unidad_tiempo'] = 'dias';

        $idRecibo = $this->reciboService->crear($documentoData);
        return ['id_factura' => null, 'id_recibo' => $idRecibo, 'tipo' => 'recibo', 'importe' => $importe];
    }

    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * Construye el mapa de placeholders para el período facturado y el anterior.
     *
     * Período actual (el que se factura por adelantado):
     *   {mes} {MES} {mes_num} {anio}/{año} {mes_anio} {fecha}
     * Período anterior (un período atrás según la periodicidad):
     *   {mes_ant} {MES_ANT} {mes_num_ant} {anio_ant}/{año_ant} {mes_anio_ant} {fecha_ant}
     */
    private function construirReemplazos(string $periodoFecha, int $meses, string $codigo): array
    {
        if ($periodoFecha === '') {
            return [];
        }
        try {
            $dt = new \DateTime($periodoFecha);
        } catch (\Throwable) {
            return [];
        }

        // Período anterior: resta una periodicidad (inverso de calcularProximoCobro)
        $dtAnt = clone $dt;
        if     ($codigo === 'DIARIO')    { $dtAnt->modify('-1 day');   }
        elseif ($codigo === 'SEMANAL')   { $dtAnt->modify('-7 days');  }
        elseif ($codigo === 'QUINCENAL') { $dtAnt->modify('-15 days'); }
        else                             { $dtAnt->modify('-' . max(1, $meses) . ' months'); }

        // Fin del período actual: avanza una periodicidad y resta 1 día
        $dtFin = clone $dt;
        if     ($codigo === 'DIARIO')    { $dtFin->modify('+1 day');                        }
        elseif ($codigo === 'SEMANAL')   { $dtFin->modify('+7 days');                       }
        elseif ($codigo === 'QUINCENAL') { $dtFin->modify('+15 days');                      }
        else                             { $dtFin->modify('+' . max(1, $meses) . ' months'); }
        $dtFin->modify('-1 day');

        return array_merge(
            $this->mapaPeriodo($dt,    ''),
            $this->mapaPeriodo($dtAnt, '_ant'),
            $this->mapaFin($dt, $dtFin)
        );
    }

    private function mapaPeriodo(\DateTime $dt, string $suf): array
    {
        $mes  = self::MESES[(int)$dt->format('n')] ?? '';
        $anio = $dt->format('Y');
        return [
            '{mes' . $suf . '}'      => $mes,
            '{MES' . $suf . '}'      => mb_strtoupper($mes, 'UTF-8'),
            '{mes_num' . $suf . '}'  => $dt->format('m'),
            '{anio' . $suf . '}'     => $anio,
            '{año' . $suf . '}'      => $anio,
            '{mes_anio' . $suf . '}' => trim("{$mes} {$anio}"),
            '{fecha' . $suf . '}'    => $dt->format('d-m-Y'),
        ];
    }

    /**
     * Placeholders para inicio y fin del período facturado:
     *   {anio_mes}     → 2026-06  (año-mes del inicio)
     *   {anio_mes_fin} → 2027-05  (año-mes del último día del período)
     *   {fecha_fin}    → 31-05-2027 (último día del período)
     */
    private function mapaFin(\DateTime $dtInicio, \DateTime $dtFin): array
    {
        return [
            '{anio_mes}'     => $dtInicio->format('Y-m'),
            '{anio_mes_fin}' => $dtFin->format('Y-m'),
            '{fecha_fin}'    => $dtFin->format('d-m-Y'),
        ];
    }
}
