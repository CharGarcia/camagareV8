<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\ComprobantePdf;

/**
 * Validaciones de un comprobante leído desde su PDF (Descargas del SRI,
 * pestaña «Desde PDF»), antes de armar el XML y registrarlo.
 *
 * - `errores`: impiden registrar (dato faltante o incoherente con la clave de acceso).
 * - `avisos`: diferencias de sumas que el usuario debe revisar; puede registrar igual
 *   si las confirma (hay comprobantes autorizados por el SRI con esas diferencias).
 */
class DescargaPdfRules
{
    private const TOLERANCIA = 0.02;
    /** Redondeo acumulado de varias líneas. */
    private const TOLERANCIA_SUMA = 0.05;

    /**
     * @param array  $doc        Documento ya normalizado (ComprobantePdf::normalizar).
     * @param string $rucEmpresa RUC de la empresa activa (solo dígitos).
     * @return array{errores:list<string>, avisos:list<string>, rol:string}
     *         rol: 'emitido' (lo emitió la empresa), 'recibido' o '' si no le pertenece.
     */
    public function validar(array $doc, string $rucEmpresa): array
    {
        $e = [];
        $a = [];
        $cod = (string) $doc['cod_doc'];

        if ($cod === '') {
            $e[] = 'Indique el tipo de comprobante.';
        }

        // ── Clave de acceso ──────────────────────────────────────────────────
        $clave = (string) $doc['clave_acceso'];
        $partes = null;
        if ($clave === '') {
            $e[] = 'La clave de acceso es obligatoria: es lo que identifica al comprobante e impide registrarlo dos veces.';
        } elseif (strlen($clave) !== 49) {
            $e[] = 'La clave de acceso debe tener 49 dígitos (tiene ' . strlen($clave) . ').';
        } elseif (!ComprobantePdf::claveValida($clave)) {
            $e[] = 'La clave de acceso no supera el dígito verificador: revise que esté copiada completa y sin errores.';
        } else {
            $partes = ComprobantePdf::desglosarClave($clave);
        }

        // ── Emisor, número y fecha ───────────────────────────────────────────
        $ruc = (string) $doc['emisor']['ruc'];
        if (!preg_match('/^\d{13}$/', $ruc)) {
            $e[] = 'El RUC del emisor debe tener 13 dígitos.';
        }
        if ($doc['emisor']['razon_social'] === '') {
            $e[] = 'Falta la razón social del emisor.';
        }
        if ($doc['estab'] === '' || $doc['pto_emi'] === '' || $doc['secuencial'] === '') {
            $e[] = 'Falta el número del comprobante (establecimiento, punto de emisión y secuencial).';
        }
        $fecha = \DateTime::createFromFormat('!d/m/Y', (string) $doc['fecha_emision']) ?: null;
        if ($fecha === null) {
            $e[] = 'Falta la fecha de emisión (dd/mm/aaaa).';
        } elseif ($fecha > new \DateTime('tomorrow')) {
            $e[] = 'La fecha de emisión no puede ser futura.';
        }

        if ($partes !== null) {
            $cruces = [
                'el tipo de comprobante' => [$partes['cod_doc'], $cod],
                'el RUC del emisor'      => [$partes['ruc'], $ruc],
                'el establecimiento'     => [$partes['estab'], $doc['estab']],
                'el punto de emisión'    => [$partes['pto_emi'], $doc['pto_emi']],
                'el secuencial'          => [$partes['secuencial'], $doc['secuencial']],
                'la fecha de emisión'    => [$partes['fecha'], $doc['fecha_emision']],
            ];
            foreach ($cruces as $que => [$enClave, $enDoc]) {
                if ($enDoc !== '' && $enClave !== $enDoc) {
                    $e[] = "La clave de acceso no coincide con {$que}: la clave dice «{$enClave}» y el formulario «{$enDoc}».";
                }
            }
            if ($partes['ambiente'] === '1') {
                $a[] = 'La clave de acceso corresponde al ambiente de PRUEBAS: el comprobante no tiene validez tributaria.';
            }
        }

        // ── Receptor y pertenencia a la empresa ──────────────────────────────
        $idRec = (string) $doc['receptor']['identificacion'];
        if ($idRec === '') {
            $e[] = 'Falta la identificación del ' . $this->nombreReceptor($cod) . '.';
        }
        if ($doc['receptor']['razon_social'] === '') {
            $e[] = 'Falta el nombre o razón social del ' . $this->nombreReceptor($cod) . '.';
        }

        $rol = '';
        if ($rucEmpresa !== '' && $ruc === $rucEmpresa) {
            $rol = 'emitido';
        } elseif ($rucEmpresa !== '' && ($idRec === $rucEmpresa
                || (strlen($idRec) === 10 && $idRec === substr($rucEmpresa, 0, 10)))) {
            $rol = 'recibido';
        } elseif ($ruc !== '' && $idRec !== '') {
            $e[] = "El comprobante no pertenece a la empresa: su RUC ({$rucEmpresa}) no es el del emisor ni el del "
                . $this->nombreReceptor($cod) . '.';
        }

        // ── Contenido según el tipo ──────────────────────────────────────────
        if ($cod === '07') {
            $this->validarRetencion($doc, $e, $a);
        } elseif ($cod !== '') {
            $this->validarValores($doc, $cod, $e, $a);
        }
        if (in_array($cod, ['04', '05'], true)) {
            if ($doc['doc_modificado']['numero'] === '') {
                $e[] = 'Falta el número del documento que se modifica (001-001-000000001).';
            }
            if ($doc['doc_modificado']['fecha'] === '') {
                $e[] = 'Falta la fecha del documento que se modifica.';
            }
            if ($cod === '04' && $doc['doc_modificado']['motivo'] === '') {
                $e[] = 'Falta el motivo de la nota de crédito.';
            }
        }

        return ['errores' => $e, 'avisos' => $a, 'rol' => $rol];
    }

    private function nombreReceptor(string $cod): string
    {
        return match ($cod) {
            '03'    => 'proveedor',
            '07'    => 'sujeto retenido',
            default => 'comprador',
        };
    }

    private function validarRetencion(array $doc, array &$e, array &$a): void
    {
        if ($doc['periodo_fiscal'] === '') {
            $e[] = 'Falta el periodo fiscal de la retención (mm/aaaa).';
        }
        if ($doc['retenciones'] === []) {
            $e[] = 'La retención no tiene líneas.';
            return;
        }
        foreach ($doc['retenciones'] as $i => $r) {
            $n = $i + 1;
            if ($r['codigo_retencion'] === '') {
                $e[] = "Retención, línea {$n}: elija el código de retención (el PDF no siempre lo imprime).";
            }
            if ($r['base'] < 0 || $r['porcentaje'] < 0 || $r['valor'] < 0) {
                $e[] = "Retención, línea {$n}: la base, el porcentaje y el valor no pueden ser negativos.";
            }
            $calc = round($r['base'] * $r['porcentaje'] / 100, 2);
            if (abs($calc - $r['valor']) > self::TOLERANCIA) {
                $a[] = sprintf('Retención, línea %d: %s × %s%% da %s, pero el valor retenido es %s.',
                    $n, $this->f($r['base']), $this->f($r['porcentaje']), $this->f($calc), $this->f($r['valor']));
            }
            if ($r['num_doc_sustento'] === '') {
                $a[] = "Retención, línea {$n}: no tiene número de documento de sustento; no se enlazará con su factura.";
            } elseif ($r['fecha_doc_sustento'] === '') {
                $a[] = "Retención, línea {$n}: no tiene la fecha del documento de sustento.";
            }
        }
    }

    private function validarValores(array $doc, string $cod, array &$e, array &$a): void
    {
        $t = $doc['totales'];
        if ($t['total'] <= 0) {
            $e[] = 'El valor total del comprobante debe ser mayor que cero.';
        }
        if ($t['ice'] > 0) {
            $e[] = 'El comprobante tiene ICE o IRBPNR: esos impuestos no se pueden registrar desde el PDF porque el '
                . 'documento impreso no trae su código. Cárguelo con su archivo XML.';
        }

        $netos = [];   // cod_iva => suma de subtotales de las líneas
        if ($cod === '05') {
            if ($doc['motivos'] === []) {
                $e[] = 'La nota de débito no tiene motivos.';
            }
            $suma = 0.0;
            foreach ($doc['motivos'] as $i => $m) {
                if ($m['razon'] === '') {
                    $e[] = 'Motivo ' . ($i + 1) . ': falta la razón.';
                }
                $suma += $m['valor'];
            }
            if ($doc['motivos'] !== [] && abs(round($suma, 2) - $t['subtotal']) > self::TOLERANCIA_SUMA) {
                $a[] = sprintf('Los motivos suman %s y el subtotal del comprobante es %s.', $this->f($suma), $this->f($t['subtotal']));
            }
        } else {
            if ($doc['detalles'] === []) {
                $e[] = 'El comprobante no tiene líneas de detalle.';
            }
            $suma = 0.0;
            foreach ($doc['detalles'] as $i => $l) {
                $n = $i + 1;
                if ($l['descripcion'] === '') {
                    $e[] = "Detalle, línea {$n}: falta la descripción.";
                }
                if ($l['cantidad'] <= 0) {
                    $e[] = "Detalle, línea {$n}: la cantidad debe ser mayor que cero.";
                }
                if ($l['precio_unitario'] < 0 || $l['descuento'] < 0 || $l['subtotal'] < 0) {
                    $e[] = "Detalle, línea {$n}: precio, descuento y subtotal no pueden ser negativos.";
                }
                $calc = round($l['cantidad'] * $l['precio_unitario'] - $l['descuento'], 2);
                if (abs($calc - $l['subtotal']) > self::TOLERANCIA_SUMA) {
                    $a[] = sprintf('Detalle, línea %d: cantidad × precio − descuento da %s, pero el subtotal es %s.',
                        $n, $this->f($calc), $this->f($l['subtotal']));
                }
                $suma += $l['subtotal'];
                $netos[$l['cod_iva']] = ($netos[$l['cod_iva']] ?? 0.0) + $l['subtotal'];
            }
            if ($doc['detalles'] !== [] && abs(round($suma, 2) - $t['subtotal']) > self::TOLERANCIA_SUMA) {
                $a[] = sprintf('Las líneas suman %s y el subtotal del comprobante es %s.', $this->f($suma), $this->f($t['subtotal']));
            }
        }

        // IVA del total, por tarifa.
        $iva = 0.0;
        $bases = [];
        foreach ($doc['impuestos'] as $imp) {
            $tarifa = ComprobantePdf::tarifaIva($imp['cod_iva']);
            $etq = ComprobantePdf::IVA[$imp['cod_iva']][0] ?? $imp['cod_iva'];
            $calc = round($imp['base'] * $tarifa / 100, 2);
            if (abs($calc - $imp['valor']) > self::TOLERANCIA_SUMA) {
                $a[] = sprintf('IVA %s: la base %s da un impuesto de %s, pero el comprobante dice %s.',
                    $etq, $this->f($imp['base']), $this->f($calc), $this->f($imp['valor']));
            }
            $iva += $imp['valor'];
            $bases[$imp['cod_iva']] = ($bases[$imp['cod_iva']] ?? 0.0) + $imp['base'];
        }
        if ($cod !== '05' && $doc['impuestos'] !== []) {
            foreach ($netos as $c => $neto) {
                if (abs(round($neto, 2) - round($bases[$c] ?? 0.0, 2)) > self::TOLERANCIA_SUMA) {
                    $a[] = sprintf('Las líneas con IVA %s suman %s, pero la base de esa tarifa en los totales es %s.',
                        ComprobantePdf::IVA[$c][0] ?? $c, $this->f($neto), $this->f($bases[$c] ?? 0.0));
                }
            }
        }
        if ($doc['impuestos'] === [] && $cod !== '05') {
            foreach ($netos as $c => $neto) {
                $iva += round($neto * ComprobantePdf::tarifaIva((string) $c) / 100, 2);
            }
        }

        $esperado = round($t['subtotal'] + $iva + $t['propina'], 2);
        if ($t['total'] > 0 && abs($esperado - $t['total']) > self::TOLERANCIA_SUMA) {
            $a[] = sprintf('Subtotal %s + IVA %s%s da %s, pero el total del comprobante es %s.',
                $this->f($t['subtotal']), $this->f($iva),
                $t['propina'] > 0 ? ' + propina ' . $this->f($t['propina']) : '',
                $this->f($esperado), $this->f($t['total']));
        }

        if ($doc['pagos'] !== []) {
            $pagado = round(array_sum(array_column($doc['pagos'], 'total')), 2);
            if (abs($pagado - $t['total']) > self::TOLERANCIA_SUMA) {
                $a[] = sprintf('Las formas de pago suman %s y el total es %s.', $this->f($pagado), $this->f($t['total']));
            }
        }
    }

    private function f(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
