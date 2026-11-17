<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FacturaVentaRepository;

/**
 * Saldo a favor del cliente en una factura de venta: cuando cobros + retenciones + notas
 * de crédito superan el total (más notas de débito), la diferencia es dinero que la empresa
 * le debe al cliente. Casos típicos: retención y luego NC por el total; factura ya cobrada
 * a la que después llega una NC o una retención.
 *
 * Solo AVISA (no bloquea): la nota de crédito o la retención pueden ser obligatorias. Lo
 * usan Notas de Crédito y Retenciones de Venta al guardar, para decirlo en el momento.
 */
class SaldoFavorVentaService
{
    /** Desde cuánto se considera saldo a favor (un centavo; menos es redondeo). */
    public const MINIMO = 0.01;

    private FacturaVentaRepository $facturas;

    public function __construct(?FacturaVentaRepository $facturas = null)
    {
        $this->facturas = $facturas ?? new FacturaVentaRepository();
    }

    /** Saldo a favor (positivo) que deja el saldo real de una factura; 0 si no hay. */
    public static function aFavor(float $saldoReal): float
    {
        $s = round($saldoReal, 2);
        return $s <= -self::MINIMO ? -$s : 0.0;
    }

    /** Aviso de la factura, o null si no queda saldo a favor (o está anulada / no existe). */
    public function avisoFactura(int $idVenta, int $idEmpresa): ?string
    {
        $s = $this->facturas->getSaldoReal($idVenta, $idEmpresa);
        if (!$s || $s['estado'] === 'anulado') {
            return null;
        }
        $favor = self::aFavor($s['saldo']);
        if ($favor <= 0) {
            return null;
        }

        $partes = [];
        if ($s['total_cobrado'] > 0)   $partes[] = 'cobros $' . number_format($s['total_cobrado'], 2);
        if ($s['total_retencion'] > 0) $partes[] = 'retenciones $' . number_format($s['total_retencion'], 2);
        if ($s['total_nc'] > 0)        $partes[] = 'notas de crédito $' . number_format($s['total_nc'], 2);

        $total = $s['importe_total'] + $s['total_nd'];

        return 'La factura ' . $s['numero'] . ' queda con un saldo a favor del cliente de $'
            . number_format($favor, 2) . ': ' . implode(' + ', $partes)
            . ' superan su total de $' . number_format($total, 2)
            . '. Ese valor debe devolverse al cliente o aplicarse a otra factura.';
    }

    /**
     * Pasa el saldo a favor de una factura al ANTICIPO del cliente, para usarlo después en
     * otra factura (forma de cobro tipo Anticipo, desde Ingresos).
     *
     * Crea un ingreso de anticipo normal —concepto de comportamiento ANTICIPO_CLIENTE, por el
     * valor del saldo a favor—, así el saldo de anticipos del cliente sube sin tocar ninguna
     * de sus fórmulas. Su única línea es de tipo SALDO_FAVOR y apunta a la factura: con eso
     * la factura vuelve a saldo cero (AbonosVentaSql::subTrasladadoFactura) y el asiento sale
     * Debe Cuentas por Cobrar / Haber Anticipo de clientes. No entra dinero: la forma de cobro
     * es una interna, no monetaria. Anular ese ingreso deshace el traslado.
     *
     * El monto NO viene del navegador: se calcula aquí, con el cobro de la factura bloqueado
     * (mismo candado que usan los cobros, §8), así un doble clic o dos usuarios a la vez no
     * trasladan dos veces el mismo saldo.
     *
     * @return array{id_ingreso:int, numero:string, monto:float}
     */
    public function pasarAAnticipo(int $idVenta, int $idEmpresa, int $idUsuario, int $idPuntoEmision): array
    {
        $factura = $this->facturas->getPorId($idVenta);
        if (!$factura || (int) ($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \Exception('Factura no encontrada.');
        }
        if (($factura['estado'] ?? '') === 'anulado') {
            throw new \Exception('La factura está anulada: no tiene saldo a favor que trasladar.');
        }

        $punto = (new \App\repositories\SecuencialRepository())->getPuntoEmisionSerie($idPuntoEmision, $idEmpresa);
        if (!$punto) {
            throw new \Exception('Seleccione la serie (punto de emisión) con la que se registrará el ingreso de anticipo.');
        }

        $ingresoRepo = new \App\repositories\modulos\IngresoRepository();
        $concepto    = null;
        foreach ($ingresoRepo->getConceptosIngreso($idEmpresa) as $c) {
            if (strtoupper((string) ($c['comportamiento'] ?? '')) === 'ANTICIPO_CLIENTE') {
                $concepto = $c;
                break;
            }
        }
        if ($concepto === null) {
            throw new \Exception('No hay un concepto de ingreso activo de tipo «Anticipo de cliente». Créelo en Opciones de Ingresos y Egresos para poder pasar el saldo a favor a anticipo.');
        }

        $log            = new \App\Services\LogSistemaService();
        $ingresoService = new IngresoService($ingresoRepo, new \App\Rules\modulos\IngresoRules(), $log);

        $db = \App\core\Database::getConnection();
        // Si la transacción la abrió el llamador, él confirma y él genera el asiento después
        // (mismo criterio que IngresoService::crear).
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        try {
            $ingresoRepo->lockDocumentoPago('FACTURA', $idVenta, $idEmpresa);

            $saldo = $this->facturas->getSaldoReal($idVenta, $idEmpresa);
            $monto = $saldo ? self::aFavor($saldo['saldo']) : 0.0;
            if ($monto <= 0) {
                throw new \Exception('Esta factura ya no tiene saldo a favor del cliente. Vuelva a abrir la pestaña Pagos para ver su estado actual.');
            }

            $idForma = (new \App\repositories\modulos\FormaPagoRepository())->getIdFormaTrasladoSaldoFavor($idEmpresa, $idUsuario);

            $fecha  = date('Y-m-d');
            $secRes = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, 'Ingresos', $fecha);
            $numDoc = $saldo['numero'];
            $numero = str_pad((string) ($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT)
                    . '-' . str_pad((string) ($punto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT)
                    . '-' . $secRes['formateado'];
            $texto  = 'Saldo a favor de la factura de venta ' . $numDoc . ' pasado a anticipo del cliente';

            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int) ($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => (int) $punto['id'],
                'id_cliente'          => (int) $factura['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $fecha,
                'establecimiento'     => $punto['establecimiento'],
                'punto_emision'       => $punto['punto'],
                'secuencial'          => $secRes['formateado'],
                'numero_ingreso'      => $numero,
                'tipo_ingreso'        => 'ANTICIPO_CLIENTE',
                'id_ingreso_concepto' => (int) $concepto['id'],
                'monto_total'         => $monto,
                'observaciones'       => $texto . ' (no ingresa dinero)',
                'recibo_de'           => (string) ($factura['cliente_nombre'] ?? ''),
                'id_recibo_cliente'   => (int) $factura['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => \App\Helpers\AbonosVentaSql::TIPO_TRASLADO_SALDO_FAVOR,
                    'id_referencia_documento' => $idVenta,
                    'numero_documento'        => $numDoc,
                    'fecha_documento'         => $saldo['fecha_emision'] ?: null,
                    'descripcion'             => $texto,
                    'monto_documento'         => $saldo['importe_total'],
                    'saldo_anterior'          => $monto,
                    'monto_cobrado'           => $monto,
                    'saldo_actual'            => 0.0,
                ]],
                'pagos' => [[
                    'id_forma_cobro' => $idForma,
                    'monto'          => $monto,
                    'referencia'     => mb_substr('Factura ' . $numDoc, 0, 100),
                ]],
            ];

            // true: no revalidar el "saldo pendiente" del documento (un traslado no cobra la
            // factura; su tope —el saldo a favor— ya se validó arriba, bajo el candado).
            $idIngreso = $ingresoService->crear($payload, true);

            $log->registrar(
                $idUsuario, $idEmpresa, 'SALDO_FAVOR_A_ANTICIPO', 'ventas_cabecera', $idVenta,
                ['saldo_a_favor' => $monto],
                ['id_ingreso' => $idIngreso, 'numero_ingreso' => $numero, 'monto' => $monto]
            );

            if ($propia) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Asiento fuera de la transacción (ver IngresoService::crear).
        if ($propia) {
            $ingresoService->tareasPostCommit($idIngreso, $payload);
        }

        return ['id_ingreso' => $idIngreso, 'numero' => $numero, 'monto' => $monto];
    }

    /**
     * Aviso para varias facturas (una retención puede sustentar varias): por id y/o por número.
     * Nunca lanza: un fallo aquí no debe estropear el guardado que ya se hizo.
     *
     * @param int[]    $idsVenta
     * @param string[] $numeros
     */
    public function aviso(int $idEmpresa, array $idsVenta = [], array $numeros = []): ?string
    {
        try {
            $ids = array_filter(array_map('intval', $idsVenta));
            if ($numeros) {
                $ids = array_merge($ids, $this->facturas->getIdsPorNumeros($numeros, $idEmpresa));
            }
            $avisos = [];
            foreach (array_unique($ids) as $id) {
                $a = $this->avisoFactura((int) $id, $idEmpresa);
                if ($a !== null) {
                    $avisos[] = $a;
                }
            }
            return $avisos ? implode("\n", $avisos) : null;
        } catch (\Throwable $e) {
            error_log('[SaldoFavorVenta] ' . $e->getMessage());
            return null;
        }
    }
}
