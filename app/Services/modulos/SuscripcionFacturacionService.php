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
     * @param array $extras        ['texto_item','info_concepto','info_detalle']
     * @return array{id_factura:int, importe:float}
     */
    public function generarUnPeriodo(
        int $idEmpresa,
        int $idUsuario,
        array $susc,
        array $detalle,
        array $estabConfig,
        array $empresaConfig,
        array $extras = []
    ): array {
        $textoItem    = trim($extras['texto_item']   ?? '');
        $infoConcepto = trim($extras['info_concepto'] ?? '');
        $infoDetalle  = trim($extras['info_detalle']  ?? '');

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
                    'codigo_porcentaje' => '2',
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
            'observaciones'       => 'Factura generada automáticamente por suscripción.',
        ];

        $idFactura = $this->facturaService->crear($facturaData);
        return ['id_factura' => $idFactura, 'importe' => $importe];
    }
}
