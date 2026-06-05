<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Services\SecuencialService;

/**
 * Genera una factura de venta a partir de una suscripción y su detalle.
 * Extraído de SuscripcionesController::generarFacturasManualAjax para que
 * tanto el botón manual como el cron de automatizaciones usen la misma lógica.
 *
 * NOTA: solo CREA la factura (estado borrador). El envío al SRI lo hace
 * la automatización separada de "Facturas de venta → Enviar al SRI".
 */
class SuscripcionFacturacionService
{
    public function __construct(
        private FacturaVentaService $facturaService,
        private SecuencialService   $secService
    ) {}

    /**
     * Genera una factura para UN período de la suscripción.
     *
     * @param array $susc          Fila de la suscripción (id_cliente, forma_cobro, ...)
     * @param array $detalle       Ítems (de SuscripcionesRepository::getDetalle)
     * @param array $estabConfig   Establecimiento + punto de emisión (de getSeriesActivas + datos)
     * @param array $empresaConfig Config de empresa (Empresa::getPorId)
     * @param array  $extras        ['texto_item','info_concepto','info_detalle']
     * @param string $periodoFecha  Fecha del período facturado (Y-m-d) para los placeholders
     * @return array{id_factura:int, importe:float}
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
            $base = round((float)$det['cantidad'] * (float)$det['precio_unitario'], 2);
            $iva  = round($base * ((float)($det['porcentaje_iva'] ?? 0) / 100), 2);
            $totalSinImp += $base;
            $totalIva    += $iva;

            $detallesFactura[] = [
                'id_producto'               => $det['id_producto'],
                'codigo_principal'          => $det['codigo_producto'] ?? '000',
                'descripcion'               => $det['descripcion'] ?? $det['nombre_producto'],
                'info_adicional'            => $textoItem !== '' ? $textoItem : null,
                'cantidad'                  => $det['cantidad'],
                'precio_unitario'           => $det['precio_unitario'],
                'descuento'                 => 0,
                'precio_total_sin_impuesto' => $base,
                'impuestos'                 => $det['porcentaje_iva'] > 0 ? [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => \App\Helpers\SriIvaHelper::codigoPorcentaje($det['porcentaje_iva']),
                    'tarifa'            => $det['porcentaje_iva'],
                    'base_imponible'    => $base,
                    'valor'             => $iva,
                ]] : [],
            ];
        }

        $importe = round($totalSinImp + $totalIva, 2);
        if ($importe <= 0) {
            throw new \RuntimeException("La suscripción #{$susc['id']} no tiene ítems con monto.");
        }

        $secRes     = $this->secService->obtenerSiguienteSecuencial((int)$estabConfig['id_punto_emision'], 'Facturas de venta');
        $secuencial = $secRes['formateado'];

        $infoAdicional = [];
        if ($infoConcepto !== '' && $infoDetalle !== '') {
            $infoAdicional[] = ['nombre' => $infoConcepto, 'valor' => $infoDetalle];
        }

        // Correo del cliente: campo fijo, usa el nombre 'correo del cliente' que la vista
        // reconoce como fila no eliminable (data-tipo="correo-cliente", sin botón borrar).
        $emailCliente = trim((string)($susc['cliente_email'] ?? ''));
        if ($emailCliente !== '') {
            $infoAdicional[] = ['nombre' => 'correo del cliente', 'valor' => $emailCliente];
        }

        $facturaData = [
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
                'forma_pago' => ($susc['forma_cobro'] ?? '') === 'tarjeta' ? '16' : '20',
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

        $idFactura = $this->facturaService->crear($facturaData);
        return ['id_factura' => $idFactura, 'importe' => $importe];
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

        return array_merge(
            $this->mapaPeriodo($dt,    ''),
            $this->mapaPeriodo($dtAnt, '_ant')
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
}
