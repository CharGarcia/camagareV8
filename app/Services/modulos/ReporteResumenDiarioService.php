<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ReporteResumenDiarioRepository;
use App\Rules\modulos\ReporteResumenDiarioRules;

/**
 * Resumen diario (módulo propio): todo lo de UN día.
 *  - Ventas e ingresos: facturas y recibos (total y saldo pendiente hoy), notas de débito,
 *    notas de crédito (restan), retenciones recibidas y los Ingresos del día separados en
 *    "Cobros de ventas del día" y "Cobros de días anteriores y otros".
 *  - Compras y egresos: compras, liquidaciones de compra, notas de crédito de proveedor
 *    (restan), retenciones emitidas y los Egresos (pagos) del día.
 *  - Traslados entre formas de pago del día (p. ej. depositar el efectivo en el banco).
 * Al final, el resumen: ventas netas, compras netas y la caja por forma de pago con su
 * saldo inicial (apertura + movimientos anteriores), ingresos, egresos, traslados y saldo
 * final. El saldo solo se calcula para quien ve toda la empresa (acceso total): con
 * registros propios no tendría sentido.
 *
 * Solo se devuelve lo que existe: secciones, bloques y líneas sin documentos no salen.
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

        $facturas  = $this->docsVenta($r->getFacturas($idEmpresa, $fecha, $conBorradores, $u));
        $recibos   = $this->docsVenta($r->getRecibos($idEmpresa, $fecha, $u));
        $ndVenta   = $this->docs($r->getNotasVenta('nota_debito_cabecera', $idEmpresa, $fecha, $conBorradores, $u));
        $ncVenta   = $this->docs($r->getNotasVenta('notas_credito_cabecera', $idEmpresa, $fecha, $conBorradores, $u));
        $retVenta  = $this->rets($r->getRetencionesVenta($idEmpresa, $fecha, $u));
        $ingresos  = $this->caja($r->getIngresos($idEmpresa, $fecha, $u));
        $cobrosDia = array_values(array_filter($ingresos, static fn (array $f): bool => $f['grupo'] === 'DIA'));
        $cobrosOtr = array_values(array_filter($ingresos, static fn (array $f): bool => $f['grupo'] !== 'DIA'));

        $compras   = $this->docs($r->getCompras('OTROS', $idEmpresa, $fecha, $u));
        $ncCompra  = $this->docs($r->getCompras('NC', $idEmpresa, $fecha, $u));
        $liqs      = $this->docs($r->getLiquidaciones($idEmpresa, $fecha, $conBorradores, $u));
        $retCompra = $this->rets($r->getRetencionesCompra($idEmpresa, $fecha, $conBorradores, $u));
        $egresos   = $this->caja($r->getEgresos($idEmpresa, $fecha, $u));
        $traslados = $this->traslados($r->getTraslados($idEmpresa, $fecha, $u));

        // Documentos de venta: solo total y saldo (pendiente hoy). Notas: solo total.
        $colsVenta = static fn (?string $ref = null): array => array_values(array_filter([
            ['k' => 'numero',     'lbl' => 'Número',  'w' => 17],
            ['k' => 'tercero',    'lbl' => 'Cliente', 'w' => 53, 'flex' => true],
            $ref !== null ? ['k' => 'referencia', 'lbl' => $ref, 'w' => 15] : null,
            ['k' => 'total',      'lbl' => 'Total',   'w' => 15, 'num' => true],
            $ref === null ? ['k' => 'saldo', 'lbl' => 'Saldo', 'w' => 15, 'num' => true] : null,
        ]));
        $colsCompra = static fn (?string $ref = null): array => array_values(array_filter([
            ['k' => 'numero',    'lbl' => 'Número',    'w' => 17],
            ['k' => 'tercero',   'lbl' => 'Proveedor', 'w' => $ref === null ? 53 : 38, 'flex' => true],
            $ref !== null ? ['k' => 'referencia', 'lbl' => $ref, 'w' => 15] : null,
            ['k' => 'subtotal',  'lbl' => 'Subtotal',  'w' => 10, 'num' => true],
            ['k' => 'impuestos', 'lbl' => 'Impuestos', 'w' => 10, 'num' => true],
            ['k' => 'total',     'lbl' => 'Total',     'w' => 10, 'num' => true],
        ]));
        $colsRet = static fn (string $tercero): array => [
            ['k' => 'numero',     'lbl' => 'Número',        'w' => 17],
            ['k' => 'tercero',    'lbl' => $tercero,        'w' => 38, 'flex' => true],
            ['k' => 'referencia', 'lbl' => 'Doc. sustento', 'w' => 15],
            ['k' => 'renta',      'lbl' => 'Renta',         'w' => 10, 'num' => true],
            ['k' => 'iva',        'lbl' => 'IVA',           'w' => 10, 'num' => true],
            ['k' => 'total',      'lbl' => 'Total',         'w' => 10, 'num' => true],
        ];
        $colsCaja = static fn (string $tercero, string $numero): array => [
            ['k' => 'tercero', 'lbl' => $tercero,        'w' => 25, 'flex' => true],
            ['k' => 'numero',  'lbl' => $numero,         'w' => 15],
            ['k' => 'detalle', 'lbl' => 'Detalle',       'w' => 33, 'flex' => true],
            ['k' => 'forma',   'lbl' => 'Forma de pago', 'w' => 17],
            ['k' => 'valor',   'lbl' => 'Valor',         'w' => 10, 'num' => true],
        ];
        $colsTraslado = [
            ['k' => 'origen',        'lbl' => 'Desde',         'w' => 25],
            ['k' => 'destino',       'lbl' => 'Hacia',         'w' => 25],
            ['k' => 'observaciones', 'lbl' => 'Observaciones', 'w' => 35, 'flex' => true],
            ['k' => 'valor',         'lbl' => 'Valor',         'w' => 15, 'num' => true],
        ];

        $grupos = [
            ['titulo' => 'Ventas e ingresos', 'secciones' => [
                self::seccion('facturas', 'Facturas de venta', $colsVenta(), $facturas),
                self::seccion('recibos', 'Recibos de venta', $colsVenta(), $recibos),
                self::seccion('nd_venta', 'Notas de débito', $colsVenta('Doc. modificado'), $ndVenta),
                self::seccion('nc_venta', 'Notas de crédito', $colsVenta('Doc. modificado'), $ncVenta, 'Restan de las ventas'),
                self::seccion('ret_venta', 'Retenciones recibidas', $colsRet('Cliente'), $retVenta),
                self::seccion('cobros_dia', 'Cobros de ventas del día', $colsCaja('Recibido de', 'N.º ingreso'), $cobrosDia),
                self::seccion('cobros_otros', 'Cobros de días anteriores y otros', $colsCaja('Recibido de', 'N.º ingreso'), $cobrosOtr),
            ]],
            ['titulo' => 'Compras y egresos', 'secciones' => [
                self::seccion('compras', 'Compras', $colsCompra('Tipo'), $compras),
                self::seccion('liquidaciones', 'Liquidaciones de compra', $colsCompra(), $liqs),
                self::seccion('nc_compra', 'Notas de crédito de proveedores', $colsCompra('Doc. modificado'), $ncCompra, 'Restan de las compras'),
                self::seccion('ret_compra', 'Retenciones emitidas', $colsRet('Proveedor'), $retCompra),
                self::seccion('egresos', 'Egresos (pagos)', $colsCaja('Pagado a', 'N.º egreso'), $egresos),
            ]],
            ['titulo' => 'Traslados entre formas de pago', 'secciones' => [
                self::seccion('traslados', 'Traslados', $colsTraslado, $traslados),
            ]],
        ];

        // Solo se muestra lo que existe: fuera las secciones sin documentos y los bloques vacíos.
        foreach ($grupos as $i => $g) {
            $grupos[$i]['secciones'] = array_values(array_filter($g['secciones'], static fn (array $s): bool => $s['filas'] !== []));
        }
        $grupos = array_values(array_filter($grupos, static fn (array $g): bool => $g['secciones'] !== []));

        $sum = static fn (array $filas, string $k = 'total'): float
            => round(array_sum(array_map(static fn (array $f): float => (float) $f[$k], $filas)), 2);

        $ventasNetas  = round($sum($facturas) + $sum($recibos) + $sum($ndVenta) - $sum($ncVenta), 2);
        $comprasNetas = round($sum($compras) + $sum($liqs) - $sum($ncCompra), 2);
        $totIngresos  = $sum($ingresos, 'valor');
        $totEgresos   = $sum($egresos, 'valor');

        // Saldo por forma de pago: solo para quien ve toda la empresa.
        $conSaldo = $idUsuarioFiltro === null;
        $saldos   = $conSaldo ? $r->getSaldosIniciales($idEmpresa, $fecha) : [];
        $caja     = self::cajaPorForma($ingresos, $egresos, $traslados, $saldos, $conSaldo);

        $ventas = self::bloqueResumen(
            [['Facturas', $facturas, 1], ['Recibos', $recibos, 1], ['Notas de débito', $ndVenta, 1], ['(-) Notas de crédito', $ncVenta, -1]],
            ['VENTAS NETAS', $ventasNetas],
            ['Retenciones recibidas', $retVenta]
        );
        if ($facturas || $recibos) {
            $ventas[] = ['Cobrado de las ventas del día', $sum($cobrosDia, 'valor'), 'sub'];
            $ventas[] = ['Saldo pendiente de las ventas del día', round($sum($facturas, 'saldo') + $sum($recibos, 'saldo'), 2), 'sub'];
        }

        return [
            'fecha'     => $fecha,
            'grupos'    => $grupos,
            'con_saldo' => $conSaldo,
            'resumen'   => [
                'ventas'  => $ventas,
                'compras' => self::bloqueResumen(
                    [['Compras', $compras, 1], ['Liquidaciones', $liqs, 1], ['(-) Notas de crédito', $ncCompra, -1]],
                    ['COMPRAS NETAS', $comprasNetas],
                    ['Retenciones emitidas', $retCompra]
                ),
                'caja'    => $caja,
            ],
            'kpis' => [
                'ventas_netas'  => $ventasNetas,
                'compras_netas' => $comprasNetas,
                'ingresos'      => $totIngresos,
                'egresos'       => $totEgresos,
                'neto_caja'     => round($totIngresos - $totEgresos, 2),
                'saldo_final'   => $caja !== null && $conSaldo ? $caja['totales']['saldo_final'] : null,
            ],
        ];
    }

    private static function seccion(string $clave, string $titulo, array $cols, array $filas, string $nota = ''): array
    {
        return [
            'clave'    => $clave,
            'titulo'   => $titulo,
            'nota'     => $nota,
            'columnas' => $cols,
            'filas'    => $filas,
            'totales'  => self::totales($cols, $filas),
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

    /** Número del documento, marcado si es borrador. */
    private static function numero(array $r): string
    {
        return (string) $r['numero'] . (strtolower((string) $r['estado']) === 'borrador' ? ' (borrador)' : '');
    }

    /** Facturas y recibos: total y saldo pendiente hoy. */
    private function docsVenta(array $rows): array
    {
        return array_map(static fn (array $r): array => [
            'numero'  => self::numero($r),
            'tercero' => (string) $r['tercero'],
            'total'   => round((float) $r['total'], 2),
            'saldo'   => round((float) $r['saldo'], 2),
        ], $rows);
    }

    /** Notas y compras: impuestos = total − subtotal. */
    private function docs(array $rows): array
    {
        return array_map(static function (array $r): array {
            $total    = round((float) $r['total'], 2);
            $subtotal = round((float) $r['subtotal'], 2);
            return [
                'numero'     => self::numero($r),
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
                'grupo'        => (string) ($r['grupo'] ?? ''),
                'valor'        => round((float) $r['valor'], 2),
            ];
        }, $rows);
    }

    private function traslados(array $rows): array
    {
        return array_map(static fn (array $r): array => [
            'id'            => (int) $r['id'],
            'origen'        => (string) $r['forma_origen'],
            'destino'       => (string) $r['forma_destino'],
            'id_origen'     => (int) $r['id_forma_origen'],
            'id_destino'    => (int) $r['id_forma_destino'],
            'observaciones' => (string) $r['observaciones'],
            'valor'         => round((float) $r['valor'], 2),
        ], $rows);
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

    /**
     * Caja por forma de pago, con la misma forma que una sección (columnas, filas y
     * totales). Solo entran las formas de pago usadas ese día (con ingreso, egreso o
     * traslado). Con saldo: saldo inicial + ingresos − egresos ± traslados = saldo final.
     * Sin saldo (registros propios): ingresos, egresos, traslados y neto. null si ese día
     * no se usó ninguna forma.
     */
    private static function cajaPorForma(array $ingresos, array $egresos, array $traslados, array $saldos, bool $conSaldo): ?array
    {
        $formas = [];
        $fila = static function (int $id, string $nombre) use (&$formas): void {
            $formas[$id] ??= ['forma' => $nombre, 'saldo_inicial' => 0.0, 'ingresos' => 0.0, 'egresos' => 0.0, 'traslados' => 0.0];
        };
        foreach ([['ingresos', $ingresos], ['egresos', $egresos]] as [$lado, $filas]) {
            foreach ($filas as $f) {
                $fila($f['id_forma'], $f['forma_nombre']);
                $formas[$f['id_forma']][$lado] += $f['valor'];
            }
        }
        foreach ($traslados as $t) {
            $fila($t['id_origen'], $t['origen']);
            $fila($t['id_destino'], $t['destino']);
            $formas[$t['id_origen']]['traslados']  -= $t['valor'];
            $formas[$t['id_destino']]['traslados'] += $t['valor'];
        }
        if (!$formas) {
            return null;
        }
        // Solo las formas usadas ese día; a cada una se le suma el saldo que traía.
        foreach ($formas as $id => &$f) {
            $f['saldo_inicial'] = $saldos[$id]['saldo'] ?? 0.0;
            foreach (['saldo_inicial', 'ingresos', 'egresos', 'traslados'] as $k) {
                $f[$k] = round($f[$k], 2);
            }
            $f['neto']        = round($f['ingresos'] - $f['egresos'] + $f['traslados'], 2);
            $f['saldo_final'] = round($f['saldo_inicial'] + $f['neto'], 2);
        }
        unset($f);
        uasort($formas, static fn (array $a, array $b): int => strcmp($a['forma'], $b['forma']));
        $filas = array_values($formas);

        $cols = array_values(array_filter([
            ['k' => 'forma', 'lbl' => 'Forma de pago', 'w' => $conSaldo ? 30 : 40, 'flex' => true],
            $conSaldo ? ['k' => 'saldo_inicial', 'lbl' => 'Saldo inicial', 'w' => 14, 'num' => true] : null,
            ['k' => 'ingresos',  'lbl' => 'Ingresos',  'w' => $conSaldo ? 14 : 15, 'num' => true],
            ['k' => 'egresos',   'lbl' => 'Egresos',   'w' => $conSaldo ? 14 : 15, 'num' => true],
            ['k' => 'traslados', 'lbl' => 'Traslados', 'w' => $conSaldo ? 14 : 15, 'num' => true],
            $conSaldo
                ? ['k' => 'saldo_final', 'lbl' => 'Saldo final', 'w' => 14, 'num' => true]
                : ['k' => 'neto', 'lbl' => 'Neto', 'w' => 15, 'num' => true],
        ]));
        return self::seccion('caja', 'Caja por forma de pago', $cols, $filas);
    }
}
