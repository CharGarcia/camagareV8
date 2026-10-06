<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Impuestos de cabecera de una NOTA DE DÉBITO del SRI (<infoNotaDebito><impuestos>).
 *
 * El XML de una ND no desglosa impuestos por motivo: trae un bloque único en la
 * cabecera. Este helper lo lee para que Compras (registro automático y vista previa
 * del XML) lo adjunte a la primera línea del detalle, y además resuelve una
 * inconsistencia frecuente de algunos emisores: declaran el impuesto con
 * <baseImponible>0.00</baseImponible> aunque <totalSinImpuestos> sea mayor a cero
 * (p. ej. intereses por mora, código 6 = no objeto de IVA, base 0.00, total 625.21).
 * El SRI autoriza el comprobante igual, pero con esa base en cero el documento sale
 * con las cuatro bases en 0,00 en el ATS y el SRI lo rechaza ("al menos una base
 * debe ser mayor a 0.00").
 *
 * Mismo criterio que los ajustes 1.7/1.8 del registro automático de facturas
 * (DocumentoAutomatedRegisterService::resolverAjusteIvaDetalle / resolverAjusteBasesIva):
 * cuando el caso es inequívoco se corrige y se deja constancia en las observaciones
 * de la compra; cuando no lo es, se guarda tal cual y solo se observa.
 */
final class NotaDebitoXmlHelper
{
    /**
     * @param \SimpleXMLElement $info Nodo <infoNotaDebito>.
     * @return array{
     *     impuestos: list<array{codigo_impuesto:string,codigo_porcentaje:string,tarifa:float,base_imponible:float,valor:float}>,
     *     observacion: ?string
     * }
     */
    public static function impuestosCabecera(\SimpleXMLElement $info): array
    {
        $impuestos = [];
        if (isset($info->impuestos->impuesto)) {
            foreach ($info->impuestos->impuesto as $imp) {
                $impuestos[] = [
                    'codigo_impuesto'   => trim((string) $imp->codigo),
                    'codigo_porcentaje' => trim((string) $imp->codigoPorcentaje),
                    'tarifa'            => (float) $imp->tarifa,
                    'base_imponible'    => (float) $imp->baseImponible,
                    'valor'             => (float) $imp->valor,
                ];
            }
        }

        $total = round((float) ($info->totalSinImpuestos ?? 0), 2);
        if ($total <= 0) {
            return ['impuestos' => $impuestos, 'observacion' => null];
        }

        $idxIva = [];
        foreach ($impuestos as $i => $imp) {
            if ($imp['codigo_impuesto'] === '2') {
                $idxIva[] = $i;
            }
        }
        if ($idxIva === []) {
            return ['impuestos' => $impuestos, 'observacion' => null];
        }
        foreach ($idxIva as $i) {
            if ($impuestos[$i]['base_imponible'] > 0) {
                // Al menos un impuesto de IVA trae base: el XML es coherente, no se toca.
                return ['impuestos' => $impuestos, 'observacion' => null];
            }
        }

        $obs = sprintf(
            'XML del SRI inconsistente: el impuesto de IVA de la nota de débito viene con base 0.00 '
            . 'y el total sin impuestos es %s.',
            number_format($total, 2, '.', '')
        );

        if (count($idxIva) === 1) {
            $i   = $idxIva[0];
            $imp = $impuestos[$i];
            $pct = SriIvaHelper::porcentajeDesdeCodigo($imp['codigo_porcentaje']);
            if ($pct === null) {
                $pct = $imp['tarifa'];
            }
            $ivaEsperado = round($total * $pct / 100, 2);
            if (abs($ivaEsperado - $imp['valor']) <= 0.02) {
                $impuestos[$i]['base_imponible'] = $total;
                return [
                    'impuestos'   => $impuestos,
                    'observacion' => $obs . sprintf(
                        ' La base se registró con ese total (código %s, IVA %s), que es el que cuadra con el valor del comprobante.',
                        $imp['codigo_porcentaje'],
                        number_format($imp['valor'], 2, '.', '')
                    ),
                ];
            }
        }

        return [
            'impuestos'   => $impuestos,
            'observacion' => $obs . ' El impuesto se registró tal como vino en el XML; revise la base y la tarifa de IVA antes de declarar.',
        ];
    }
}
