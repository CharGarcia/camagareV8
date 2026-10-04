<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\DetalleImpuestos;
use App\repositories\modulos\ReporteVentasRepository;
use App\Rules\modulos\ReporteVentasResumenDiarioRules;

/**
 * Resumen diario del Reporte de Ventas: un bloque por día con el mismo formato que la
 * tirilla del Reporte Restaurante y el correo del cierre de caja —Documentos, Detalle de
 * impuestos, Cobro por forma de pago— y, si el período tiene más de un día, un bloque
 * final con el total del período.
 *
 * Solo lectura: no escribe en BD (no abre transacción ni registra auditoría).
 *
 * Cada bloque cuadra consigo mismo:
 *  - Documentos: facturas + recibos − notas de crédito = TOTAL NETO (con impuestos).
 *  - Detalle de impuestos: del subtotal sin impuestos al mismo TOTAL NETO.
 *  - Cobro: lo que se cobró de las facturas y recibos del día por cada forma de pago, más
 *    retenciones, notas de crédito aplicadas y lo pendiente = total de facturas + recibos.
 *    Si a un documento se le cobró de más, la diferencia sale aparte como "Cobrado de más".
 */
class ReporteVentasResumenDiarioService
{
    private const ETIQUETA_TIPO = [
        'FACTURA'      => 'Facturas',
        'RECIBO'       => 'Recibos',
        'NOTA_CREDITO' => 'Notas de crédito',
    ];

    private ReporteVentasRepository $repository;
    private ReporteVentasResumenDiarioRules $rules;

    public function __construct(?ReporteVentasRepository $repository = null)
    {
        $this->repository = $repository ?? new ReporteVentasRepository();
        $this->rules      = new ReporteVentasResumenDiarioRules();
    }

    /**
     * @param int|int[] $idEmpresa Empresa activa o el grupo RUC (consolidado), ya resuelto
     *                             por el controlador, igual que el resto del reporte.
     * @param array     $filtros   Los del reporte, con el alcance del usuario ya aplicado.
     * @return array{dias: list<array>, total: ?array}
     */
    public function generar(int|array $idEmpresa, array $filtros): array
    {
        $this->rules->validarPeriodo((string) ($filtros['fecha_desde'] ?? ''), (string) ($filtros['fecha_hasta'] ?? ''));

        $docs      = $this->repository->getResumenDiarioDocumentos($idEmpresa, $filtros);
        $anulados  = $this->repository->getResumenDiarioAnulados($idEmpresa, $filtros);
        $impuestos = $this->repository->getResumenDiarioImpuestos($idEmpresa, $filtros);
        $cobros    = $this->repository->getResumenDiarioCobros($idEmpresa, $filtros);

        // Días con movimiento (documentos válidos o anulados), en orden.
        $fechas = array_unique(array_merge(array_column($docs, 'fecha'), array_column($anulados, 'fecha')));
        sort($fechas);

        $porFecha = static fn (array $rows, string $fecha): array
            => array_values(array_filter($rows, static fn (array $r): bool => $r['fecha'] === $fecha));

        $dias = [];
        foreach ($fechas as $fecha) {
            $dias[] = $this->armarBloque(
                date('d-m-Y', strtotime($fecha)),
                $porFecha($docs, $fecha),
                $porFecha($anulados, $fecha),
                $porFecha($impuestos['totales'], $fecha),
                $porFecha($impuestos['impuestos'], $fecha),
                $porFecha($cobros['formas'], $fecha),
                $porFecha($cobros['saldos'], $fecha)
            );
        }

        $total = count($dias) > 1
            ? $this->armarBloque(
                'Del ' . date('d-m-Y', strtotime((string) $filtros['fecha_desde']))
                    . ' al ' . date('d-m-Y', strtotime((string) $filtros['fecha_hasta'])),
                $docs, $anulados, $impuestos['totales'], $impuestos['impuestos'], $cobros['formas'], $cobros['saldos']
            )
            : null;

        return ['dias' => $dias, 'total' => $total];
    }

    /** Un bloque del resumen (un día o el total del período) a partir de las filas que le tocan. */
    private function armarBloque(string $titulo, array $docs, array $anulados, array $totImp, array $impuestos, array $formas, array $saldos): array
    {
        $sum = static fn (array $rows, string $campo): float
            => array_sum(array_map(static fn (array $r): float => (float) ($r[$campo] ?? 0), $rows));

        // Documentos por tipo (la NC con signo negativo) y el neto sin impuestos.
        $documentos   = [];
        $totalVendido = 0.0;
        $totalNeto    = 0.0;
        foreach (self::ETIQUETA_TIPO as $tipo => $etiqueta) {
            $filas = array_filter($docs, static fn (array $r): bool => $r['tipo'] === $tipo);
            if (!$filas) {
                continue;
            }
            $signo = $tipo === 'NOTA_CREDITO' ? -1 : 1;
            $documentos[] = [
                'etiqueta' => $etiqueta,
                'cantidad' => (int) $sum($filas, 'cantidad'),
                'total'    => round($signo * $sum($filas, 'total'), 2),
            ];
            $totalVendido += $signo * $sum($filas, 'subtotal');
            $totalNeto    += $signo * $sum($filas, 'total');
        }

        // Impuestos: se suman por código/tarifa (el bloque total junta varios días) y se
        // arman con el mismo helper que la tirilla del restaurante y el cierre de caja.
        $acum = [];
        foreach ($impuestos as $i) {
            $k = $i['codigo_impuesto'] . '|' . $i['codigo_porcentaje'] . '|' . (float) $i['tarifa'];
            $acum[$k] ??= [
                'codigo_impuesto'   => (string) $i['codigo_impuesto'],
                'codigo_porcentaje' => (string) $i['codigo_porcentaje'],
                'tarifa'            => (float) $i['tarifa'],
                'base'              => 0.0,
                'valor'             => 0.0,
            ];
            $acum[$k]['base']  += (float) $i['base'];
            $acum[$k]['valor'] += (float) $i['valor'];
        }
        $detalleImpuestos = DetalleImpuestos::armar([
            'subtotal'  => round($sum($totImp, 'subtotal'), 2),
            'servicio'  => round($sum($totImp, 'servicio'), 2),
            'total'     => round($sum($totImp, 'total'), 2),
            'impuestos' => array_values($acum),
        ]);

        // Cobro por forma de pago (sumado por forma: el total del período junta días).
        $porForma = [];
        foreach ($formas as $fp) {
            $k = (int) $fp['id_forma_pago'];
            $porForma[$k] ??= ['concepto' => (string) $fp['forma_pago_nombre'], 'documentos' => 0, 'total' => 0.0];
            $porForma[$k]['documentos'] += (int) $fp['cantidad_documentos'];
            $porForma[$k]['total']      += (float) $fp['total'];
        }
        uasort($porForma, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $lineasCobro = [];
        foreach ($porForma as $fp) {
            $lineasCobro[] = [
                'concepto' => $fp['concepto'],
                'detalle'  => $fp['documentos'] . ' documento(s)',
                'total'    => round($fp['total'], 2),
            ];
        }
        $cobrado   = round(array_sum(array_column($lineasCobro, 'total')), 2);
        $retenido  = round($sum($saldos, 'retenido'), 2);
        $nc        = round($sum($saldos, 'nc'), 2);
        $pendiente = round($sum($saldos, 'pendiente'), 2);
        $totalDocs = round($sum($saldos, 'total'), 2);
        // Lo que sobra al cuadrar es lo cobrado (o retenido/acreditado) de más.
        $excedente = round($cobrado + $retenido + $nc + $pendiente - $totalDocs, 2);

        return [
            'titulo'        => $titulo,
            'documentos'    => $documentos,
            'anulados'      => (int) $sum($anulados, 'cantidad'),
            'total_vendido' => round($totalVendido, 2),
            'total_neto'    => round($totalNeto, 2),
            'impuestos'     => $detalleImpuestos,
            'cobro'         => [
                'formas'    => $lineasCobro,
                'cobrado'   => $cobrado,
                'retenido'  => $retenido,
                'nc'        => $nc,
                'pendiente' => $pendiente,
                'excedente' => abs($excedente) >= 0.01 ? $excedente : 0.0,
                'total'     => $totalDocs,
            ],
        ];
    }
}
