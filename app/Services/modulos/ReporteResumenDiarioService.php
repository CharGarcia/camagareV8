<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ReporteResumenDiarioRepository;
use App\Rules\modulos\ReporteResumenDiarioRules;

/**
 * Resumen diario (módulo propio): todo lo de UN día en dos bloques.
 *  - Ventas e ingresos: facturas, recibos, notas de débito, notas de crédito (restan),
 *    retenciones recibidas y los Ingresos (cobros) del día.
 *  - Compras y egresos: compras, liquidaciones de compra, notas de crédito de proveedor
 *    (restan), retenciones emitidas y los Egresos (pagos) del día.
 * Al final, el resumen: ventas netas, compras netas y caja por forma de pago (lo que
 * entró, lo que salió y el neto).
 *
 * Cada sección trae sus columnas (etiqueta, clave, si es numérica y su ancho en el PDF)
 * para que pantalla, PDF y Excel pinten exactamente lo mismo.
 *
 * Solo lectura: no escribe en BD (no abre transacción ni registra auditoría).
 */
class ReporteResumenDiarioService
{
    private ReporteResumenDiarioRepository $repository;
    private ReporteResumenDiarioRules $rules;

    public function __construct(?ReporteResumenDiarioRepository $repository = null)
    {
        $this->repository = $repository ?? new ReporteResumenDiarioRepository();
        $this->rules      = new ReporteResumenDiarioRules();
    }

    /**
     * @param int      $idEmpresa       Empresa activa.
     * @param string   $fecha           Día del resumen (Y-m-d).
     * @param bool     $conBorradores   Cuenta también los comprobantes electrónicos en borrador.
     * @param int|null $idUsuarioFiltro Sin acceso total: solo lo que registró ese usuario (§6).
     */
    public function generar(int $idEmpresa, string $fecha, bool $conBorradores, ?int $idUsuarioFiltro): array
    {
        $fecha = $this->rules->validarFecha($fecha);
        $r     = $this->repository;
        $u     = $idUsuarioFiltro;

        $facturas  = $this->docs($r->getFacturas($idEmpresa, $fecha, $conBorradores, $u));
        $recibos   = $this->docs($r->getRecibos($idEmpresa, $fecha, $u));
        $ndVenta   = $this->docs($r->getNotasVenta('nota_debito_cabecera', $idEmpresa, $fecha, $conBorradores, $u));
        $ncVenta   = $this->docs($r->getNotasVenta('notas_credito_cabecera', $idEmpresa, $fecha, $conBorradores, $u));
        $retVenta  = $this->rets($r->getRetencionesVenta($idEmpresa, $fecha, $u));
        $ingresos  = $this->caja($r->getIngresos($idEmpresa, $fecha, $u));

        $compras   = $this->docs($r->getCompras('OTROS', $idEmpresa, $fecha, $u));
        $ncCompra  = $this->docs($r->getCompras('NC', $idEmpresa, $fecha, $u));
        $liqs      = $this->docs($r->getLiquidaciones($idEmpresa, $fecha, $conBorradores, $u));
        $retCompra = $this->rets($r->getRetencionesCompra($idEmpresa, $fecha, $conBorradores, $u));
        $egresos   = $this->caja($r->getEgresos($idEmpresa, $fecha, $u));

        $colsDoc = static fn (string $tercero, ?string $ref = null): array => array_values(array_filter([
            ['k' => 'numero',    'lbl' => 'Número',    'w' => 17],
            ['k' => 'tercero',   'lbl' => $tercero,    'w' => $ref === null ? 53 : 38, 'flex' => true],
            $ref !== null ? ['k' => 'referencia', 'lbl' => $ref, 'w' => 15] : null,
            ['k' => 'subtotal',  'lbl' => 'Subtotal',  'w' => 10, 'num' => true],
            ['k' => 'impuestos', 'lbl' => 'Impuestos', 'w' => 10, 'num' => true],
            ['k' => 'total',     'lbl' => 'Total',     'w' => 10, 'num' => true],
        ]));
        $colsRet = static fn (string $tercero): array => [
            ['k' => 'numero',     'lbl' => 'Número',         'w' => 17],
            ['k' => 'tercero',    'lbl' => $tercero,         'w' => 38, 'flex' => true],
            ['k' => 'referencia', 'lbl' => 'Doc. sustento',  'w' => 15],
            ['k' => 'renta',      'lbl' => 'Renta',          'w' => 10, 'num' => true],
            ['k' => 'iva',        'lbl' => 'IVA',            'w' => 10, 'num' => true],
            ['k' => 'total',      'lbl' => 'Total',          'w' => 10, 'num' => true],
        ];
        $colsCaja = static fn (string $tercero, string $numero): array => [
            ['k' => 'tercero', 'lbl' => $tercero,        'w' => 25, 'flex' => true],
            ['k' => 'numero',  'lbl' => $numero,         'w' => 15],
            ['k' => 'detalle', 'lbl' => 'Detalle',       'w' => 33, 'flex' => true],
            ['k' => 'forma',   'lbl' => 'Forma de pago', 'w' => 17],
            ['k' => 'valor',   'lbl' => 'Valor',         'w' => 10, 'num' => true],
        ];

        $sec = static fn (string $clave, string $titulo, array $cols, array $filas, string $nota = ''): array => [
            'clave'    => $clave,
            'titulo'   => $titulo,
            'nota'     => $nota,
            'columnas' => $cols,
            'filas'    => $filas,
            'totales'  => self::totales($cols, $filas),
        ];

        $grupos = [
            [
                'titulo'    => 'Ventas e ingresos',
                'secciones' => [
                    $sec('facturas', 'Facturas de venta', $colsDoc('Cliente'), $facturas),
                    $sec('recibos', 'Recibos de venta', $colsDoc('Cliente'), $recibos),
                    $sec('nd_venta', 'Notas de débito', $colsDoc('Cliente', 'Doc. modificado'), $ndVenta),
                    $sec('nc_venta', 'Notas de crédito', $colsDoc('Cliente', 'Doc. modificado'), $ncVenta, 'Restan de las ventas'),
                    $sec('ret_venta', 'Retenciones recibidas', $colsRet('Cliente'), $retVenta),
                    $sec('ingresos', 'Ingresos (cobros)', $colsCaja('Recibido de', 'N.º ingreso'), $ingresos),
                ],
            ],
            [
                'titulo'    => 'Compras y egresos',
                'secciones' => [
                    $sec('compras', 'Compras', $colsDoc('Proveedor', 'Tipo'), $compras),
                    $sec('liquidaciones', 'Liquidaciones de compra', $colsDoc('Proveedor'), $liqs),
                    $sec('nc_compra', 'Notas de crédito de proveedores', $colsDoc('Proveedor', 'Doc. modificado'), $ncCompra, 'Restan de las compras'),
                    $sec('ret_compra', 'Retenciones emitidas', $colsRet('Proveedor'), $retCompra),
                    $sec('egresos', 'Egresos (pagos)', $colsCaja('Pagado a', 'N.º egreso'), $egresos),
                ],
            ],
        ];

        // Solo se muestra lo que existe: fuera las secciones sin documentos y los bloques vacíos.
        foreach ($grupos as $i => $g) {
            $grupos[$i]['secciones'] = array_values(array_filter($g['secciones'], static fn (array $s): bool => $s['filas'] !== []));
        }
        $grupos = array_values(array_filter($grupos, static fn (array $g): bool => $g['secciones'] !== []));

        $sumTot = static fn (array $filas, string $k = 'total'): float
            => round(array_sum(array_map(static fn (array $f): float => (float) $f[$k], $filas)), 2);

        $ventasNetas  = round($sumTot($facturas) + $sumTot($recibos) + $sumTot($ndVenta) - $sumTot($ncVenta), 2);
        $comprasNetas = round($sumTot($compras) + $sumTot($liqs) - $sumTot($ncCompra), 2);
        $totIngresos  = $sumTot($ingresos, 'valor');
        $totEgresos   = $sumTot($egresos, 'valor');

        return [
            'fecha'   => $fecha,
            'grupos'  => $grupos,
            'resumen' => [
                'ventas' => self::bloqueResumen(
                    [['Facturas', $facturas, 1], ['Recibos', $recibos, 1], ['Notas de débito', $ndVenta, 1], ['(-) Notas de crédito', $ncVenta, -1]],
                    ['VENTAS NETAS', $ventasNetas],
                    ['Retenciones recibidas', $retVenta]
                ),
                'compras' => self::bloqueResumen(
                    [['Compras', $compras, 1], ['Liquidaciones', $liqs, 1], ['(-) Notas de crédito', $ncCompra, -1]],
                    ['COMPRAS NETAS', $comprasNetas],
                    ['Retenciones emitidas', $retCompra]
                ),
                'formas' => self::porForma($ingresos, $egresos),
                'caja'   => ['ingresos' => $totIngresos, 'egresos' => $totEgresos, 'neto' => round($totIngresos - $totEgresos, 2)],
            ],
            'kpis' => [
                'ventas_netas'  => $ventasNetas,
                'compras_netas' => $comprasNetas,
                'ingresos'      => $totIngresos,
                'egresos'       => $totEgresos,
                'neto_caja'     => round($totIngresos - $totEgresos, 2),
            ],
        ];
    }

    /**
     * Líneas [etiqueta, valor, estilo] de un bloque del resumen (ventas o compras) con solo
     * lo que existe: cada tipo de documento sale si tiene al menos uno (con su cantidad), el
     * neto solo si salió algún documento y las retenciones solo si hay. Sin nada, [] (el
     * bloque no se pinta).
     *
     * @param list<array{0:string,1:array,2:int}> $docs        [etiqueta, filas, signo]
     * @param array{0:string,1:float}             $neto        [etiqueta, valor]
     * @param array{0:string,1:array}             $retenciones [etiqueta, filas]
     */
    private static function bloqueResumen(array $docs, array $neto, array $retenciones): array
    {
        $total  = static fn (array $filas): float => round(array_sum(array_column($filas, 'total')), 2);
        $lineas = [];
        foreach ($docs as [$lbl, $filas, $signo]) {
            if ($filas) {
                $lineas[] = [$lbl . ' (' . count($filas) . ')', $signo < 0 ? 0 - $total($filas) : $total($filas), ''];
            }
        }
        if ($lineas) {
            $lineas[] = [$neto[0], $neto[1], 'bold'];
        }
        if ($retenciones[1]) {
            $lineas[] = [$retenciones[0] . ' (' . count($retenciones[1]) . ')', $total($retenciones[1]), 'sub'];
        }
        return $lineas;
    }

    /** Filas de un comprobante: el borrador se marca en el número; impuestos = total − subtotal. */
    private function docs(array $rows): array
    {
        return array_map(static function (array $r): array {
            $total    = round((float) $r['total'], 2);
            $subtotal = round((float) $r['subtotal'], 2);
            return [
                'numero'     => (string) $r['numero'] . (strtolower((string) $r['estado']) === 'borrador' ? ' (borrador)' : ''),
                'tercero'    => (string) $r['tercero'],
                'referencia' => (string) $r['referencia'],
                'subtotal'   => $subtotal,
                'impuestos'  => round($total - $subtotal, 2),
                'total'      => $total,
            ];
        }, $rows);
    }

    private function rets(array $rows): array
    {
        return array_map(static fn (array $r): array => [
            'numero'     => (string) $r['numero'],
            'tercero'    => (string) $r['tercero'],
            'referencia' => (string) $r['referencia'],
            'renta'      => round((float) $r['renta'], 2),
            'iva'        => round((float) $r['iva'], 2),
            'total'      => round((float) $r['total'], 2),
        ], $rows);
    }

    /**
     * Filas de caja: el detalle es lo que cobró/pagó el comprobante; si no tiene líneas,
     * su concepto o sus observaciones. La forma de pago lleva la referencia (n.º de
     * cheque o de transferencia) cuando la hay.
     */
    private function caja(array $rows): array
    {
        return array_map(static function (array $r): array {
            $detalle = trim((string) $r['detalle']);
            if ($detalle === '') {
                $detalle = trim((string) $r['concepto']) !== '' ? (string) $r['concepto'] : trim((string) $r['observaciones']);
            }
            $forma = (string) ($r['forma'] ?? '') !== '' ? (string) $r['forma'] : 'Sin forma de pago registrada';
            $ref   = trim((string) $r['referencia_pago']);
            return [
                'tercero'      => (string) $r['tercero'],
                'numero'       => (string) $r['numero'],
                'detalle'      => $detalle,
                'forma'        => $forma . ($ref !== '' ? ' · ' . $ref : ''),
                'forma_nombre' => $forma,
                'id_forma'     => (int) $r['id_forma'],
                'valor'        => round((float) $r['valor'], 2),
            ];
        }, $rows);
    }

    /** Total de cada columna numérica de una sección. */
    private static function totales(array $cols, array $filas): array
    {
        $tot = [];
        foreach ($cols as $c) {
            if (!empty($c['num'])) {
                $tot[$c['k']] = round(array_sum(array_map(static fn (array $f): float => (float) $f[$c['k']], $filas)), 2);
            }
        }
        return $tot;
    }

    /** Caja por forma de pago: lo que entró, lo que salió y el neto, de mayor a menor movimiento. */
    private static function porForma(array $ingresos, array $egresos): array
    {
        $formas = [];
        foreach ([['ingresos', $ingresos], ['egresos', $egresos]] as [$lado, $filas]) {
            foreach ($filas as $f) {
                $k = $f['id_forma'] . '|' . $f['forma_nombre'];
                $formas[$k] ??= ['forma' => $f['forma_nombre'], 'ingresos' => 0.0, 'egresos' => 0.0];
                $formas[$k][$lado] += $f['valor'];
            }
        }
        foreach ($formas as &$f) {
            $f['ingresos'] = round($f['ingresos'], 2);
            $f['egresos']  = round($f['egresos'], 2);
            $f['neto']     = round($f['ingresos'] - $f['egresos'], 2);
        }
        unset($f);
        uasort($formas, static fn (array $a, array $b): int => ($b['ingresos'] + $b['egresos']) <=> ($a['ingresos'] + $a['egresos']));
        return array_values($formas);
    }
}
