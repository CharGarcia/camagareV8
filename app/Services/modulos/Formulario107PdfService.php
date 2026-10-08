<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Formulario 107 del SRI (formato 2023): comprobante de retenciones en la fuente
 * del impuesto a la renta por ingresos del trabajo en relación de dependencia,
 * que el empleador entrega a cada trabajador.
 *
 * Sale de la fila del trabajador en el Anexo RDEP: son los mismos valores, ya
 * calculados y validados, así que lo impreso coincide con lo que se declara.
 * Correspondencia de casilleros (campo de anexo_rdep_detalle):
 *   301 suel_sal · 303 sob_suel · 305 part_util · 307 int_grab_gen ·
 *   311 decim_ter · 313 decim_cuar · 315 fondo_reserva ·
 *   317 otros_ing_no_grav + salario_digno (el formato 2023 no tiene casillero propio
 *       para la compensación por salario digno, que tampoco es renta gravada) ·
 *   351 apo_per_iess · 353 apor_per_iess_otros ·
 *   361 vivienda · 362 turismo · 363 salud · 365 educación · 367 alimentación · 369 vestimenta ·
 *   371 exo_discap · 373 exo_ter_ed · 381 imp_rent_empl ·
 *   399 bas_imp · 401 imp_rent_caus · 402 rebaja_gastos · 403 imp_rent_rebaja ·
 *   404 val_ret_otros · 405 val_imp_asu_este · 407 val_ret · 349 ing_grav_este_empl.
 *
 * Una página A4 vertical por trabajador. Html2Pdf: cada <td> lleva su ancho
 * (sin eso las columnas no respetan el porcentaje) y nada de caracteres fuera
 * de Latin-1 (≥, —), que la fuente base no dibuja.
 */
class Formulario107PdfService
{
    /** Anchos de la tabla de liquidación: concepto, casillero, signo, valor. */
    private const W_LIQ = ['72%', '8%', '4%', '16%'];

    /**
     * @param array  $cab        cabecera del anexo (anio, num_ruc, razon_social)
     * @param array  $filas      filas de anexo_rdep_detalle (una página por cada una)
     * @param array  $firmas     nom_rep_legal, ced_rep_legal, nombre_contador, ruc_contador
     * @param string $fechaEntrega Y-m-d
     * @return string binario del PDF
     */
    public function generar(array $cab, array $filas, array $firmas, string $fechaEntrega): string
    {
        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        $html = $this->estilos();
        foreach ($filas as $f) {
            $html .= $this->pagina($cab, $f, $firmas, $fechaEntrega);
        }
        $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es', true, 'UTF-8', [8, 7, 8, 7]);
        $pdf->writeHTML($html);
        return (string) $pdf->output('formulario107.pdf', 'S');
    }

    /** Nombre de archivo: uno o varios trabajadores. */
    public function nombreArchivo(array $cab, array $filas): string
    {
        if (count($filas) === 1) {
            $id = preg_replace('/[^0-9A-Za-z]/', '', (string) $filas[0]['id_ret']) ?: 'trabajador';
            return 'Formulario107_' . (int) $cab['anio'] . '_' . $id . '.pdf';
        }
        return 'Formulario107_' . (int) $cab['anio'] . '_todos.pdf';
    }

    private function estilos(): string
    {
        return '<style>
            table { width: 100%; border-collapse: collapse; font-family: helvetica; font-size: 7.5pt; }
            td { border: 1px solid #666; padding: 3px 4px; vertical-align: middle; }
            .sin { border: none; }
            .tit { font-size: 8.5pt; font-weight: bold; text-align: center; }
            .f107 { font-size: 12pt; font-weight: bold; }
            .sec { background: #d9d9d9; font-weight: bold; font-size: 7.5pt; }
            .eti { background: #f2f2f2; font-size: 6.5pt; color: #333; }
            .cas { background: #f2f2f2; text-align: center; font-weight: bold; }
            .val { text-align: right; font-weight: bold; }
            .c { text-align: center; }
            .tot td { background: #f7f7f7; }
            .nota { font-size: 6.5pt; color: #333; }
            .firma { height: 40px; border-bottom: 1px solid #000; }
            .borrador { color: #b02a37; font-weight: bold; font-size: 8pt; text-align: center; border: 1px solid #b02a37; }
        </style>';
    }

    private function pagina(array $cab, array $f, array $firmas, string $fechaEntrega): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m = fn($v) => number_format(max(0.0, (float) $v), 2, '.', ',');
        [$wC, $wK, $wS, $wV] = self::W_LIQ;
        $ts = strtotime($fechaEntrega) ?: time();
        $graves = json_decode((string) ($f['graves'] ?? '[]'), true) ?: [];

        $linea = function (string $concepto, string $casillero, string $signo, $valor, bool $total = false) use ($e, $m, $wC, $wK, $wS, $wV): string {
            return '<tr' . ($total ? ' class="tot"' : '') . '>'
                . '<td style="width:' . $wC . '">' . $e($concepto) . '</td>'
                . '<td class="cas" style="width:' . $wK . '">' . $e($casillero) . '</td>'
                . '<td class="c" style="width:' . $wS . '">' . $e($signo) . '</td>'
                . '<td class="val" style="width:' . $wV . '">' . $m($valor) . '</td>'
                . '</tr>';
        };

        $otrosNoGravados = (float) ($f['otros_ing_no_grav'] ?? 0) + (float) ($f['salario_digno'] ?? 0);
        $nombreTrab = trim(($f['apellidos'] ?? '') . ' ' . ($f['nombres'] ?? ''));

        ob_start(); ?>
        <page backtop="0mm" backbottom="0mm" backleft="0mm" backright="0mm">
            <?php if ($graves): ?>
                <table><tr><td class="borrador" style="width:100%">BORRADOR: este trabajador tiene observaciones graves en el Anexo RDEP. Corríjalas antes de entregar el formulario.</td></tr></table>
                <br>
            <?php endif; ?>
            <table>
                <tr>
                    <td class="f107" style="width:18%" rowspan="2">FORMULARIO<br>107</td>
                    <td class="tit" style="width:64%" colspan="4">COMPROBANTE DE RETENCIONES EN LA FUENTE DEL IMPUESTO A LA RENTA<br>POR INGRESOS DEL TRABAJO EN RELACIÓN DE DEPENDENCIA</td>
                    <td class="eti" style="width:6%">No.</td>
                    <td style="width:12%"></td>
                </tr>
                <tr>
                    <td class="eti" style="width:16%">EJERCICIO FISCAL</td>
                    <td class="cas" style="width:6%">102</td>
                    <td class="c" style="width:12%"><b><?= (int) $cab['anio'] ?></b></td>
                    <td class="eti" style="width:30%">FECHA DE ENTREGA &nbsp;<b>103</b></td>
                    <td class="c" style="width:18%" colspan="2">AÑO <b><?= date('Y', $ts) ?></b> &nbsp; MES <b><?= date('m', $ts) ?></b> &nbsp; DÍA <b><?= date('d', $ts) ?></b></td>
                </tr>
            </table>
            <br>
            <table>
                <tr><td class="sec" style="width:100%" colspan="4">100 &nbsp; IDENTIFICACIÓN DEL EMPLEADOR (AGENTE DE RETENCIÓN)</td></tr>
                <tr>
                    <td class="cas" style="width:6%">105</td>
                    <td style="width:24%"><span class="eti">RUC</span><br><b><?= $e($cab['num_ruc']) ?></b></td>
                    <td class="cas" style="width:6%">106</td>
                    <td style="width:64%"><span class="eti">RAZÓN SOCIAL O APELLIDOS Y NOMBRES COMPLETOS</span><br><b><?= $e($cab['razon_social']) ?></b></td>
                </tr>
                <tr><td class="sec" style="width:100%" colspan="4">200 &nbsp; IDENTIFICACIÓN DEL TRABAJADOR (CONTRIBUYENTE)</td></tr>
                <tr>
                    <td class="cas" style="width:6%">201</td>
                    <td style="width:24%"><span class="eti">CÉDULA O PASAPORTE</span><br><b><?= $e($f['id_ret']) ?></b></td>
                    <td class="cas" style="width:6%">202</td>
                    <td style="width:64%"><span class="eti">APELLIDOS Y NOMBRES COMPLETOS</span><br><b><?= $e($nombreTrab) ?></b></td>
                </tr>
            </table>
            <br>
            <table>
                <tr><td class="sec" style="width:100%" colspan="4">LIQUIDACIÓN DEL IMPUESTO</td></tr>
                <?= $linea('SUELDOS, SALARIOS Y OTROS INGRESOS GRAVADOS DE IMPUESTO A LA RENTA (MATERIA GRAVADA DE LA SEGURIDAD SOCIAL)', '301', '+', $f['suel_sal']) ?>
                <?= $linea('OTROS INGRESOS GRAVADOS DE IMPUESTO A LA RENTA (MATERIA NO GRAVADA DE LA SEGURIDAD SOCIAL)', '303', '+', $f['sob_suel']) ?>
                <?= $linea('PARTICIPACIÓN UTILIDADES', '305', '+', $f['part_util']) ?>
                <?= $linea('INGRESOS GRAVADOS GENERADOS CON OTROS EMPLEADORES', '307', '+', $f['int_grab_gen']) ?>
                <?= $linea('DÉCIMO TERCER SUELDO', '311', '', $f['decim_ter']) ?>
                <?= $linea('DÉCIMO CUARTO SUELDO', '313', '', $f['decim_cuar']) ?>
                <?= $linea('FONDO DE RESERVA', '315', '', $f['fondo_reserva']) ?>
                <?= $linea('OTROS INGRESOS EN RELACIÓN DE DEPENDENCIA QUE NO CONSTITUYEN MATERIA GRAVADA DE IMPUESTO A LA RENTA', '317', '', $otrosNoGravados) ?>
                <?= $linea('(-) APORTE PERSONAL A LA SEGURIDAD SOCIAL CON ESTE EMPLEADOR (únicamente pagado por el trabajador), APORTES PERSONALES A LAS CAJAS MILITAR O POLICIAL PARA FINES DE RETIRO O CESANTÍA', '351', '-', $f['apo_per_iess']) ?>
                <?= $linea('(-) APORTE PERSONAL A LA SEGURIDAD SOCIAL CON OTROS EMPLEADORES (únicamente pagado por el trabajador)', '353', '-', $f['apor_per_iess_otros']) ?>
                <?= $linea('GASTOS PERSONALES - VIVIENDA - Informativo', '361', '', $f['deduc_vivienda']) ?>
                <?= $linea('GASTOS PERSONALES - TURISMO - Informativo', '362', '', $f['deduc_turismo']) ?>
                <?= $linea('GASTOS PERSONALES - SALUD - Informativo', '363', '', $f['deduc_salud']) ?>
                <?= $linea('GASTOS PERSONALES - EDUCACIÓN, ARTE Y CULTURA - Informativo', '365', '', $f['deduc_educ']) ?>
                <?= $linea('GASTOS PERSONALES - ALIMENTACIÓN - Informativo', '367', '', $f['deduc_aliment']) ?>
                <?= $linea('GASTOS PERSONALES - VESTIMENTA - Informativo', '369', '', $f['deduc_vestim']) ?>
                <?= $linea('(-) EXONERACIÓN POR DISCAPACIDAD', '371', '-', $f['exo_discap']) ?>
                <?= $linea('(-) EXONERACIÓN POR TERCERA EDAD', '373', '-', $f['exo_ter_ed']) ?>
                <?= $linea('IMPUESTO A LA RENTA ASUMIDO POR ESTE EMPLEADOR', '381', '+', $f['imp_rent_empl']) ?>
                <?= $linea('BASE IMPONIBLE GRAVADA (301+303+305+307-351-353-371-373+381; no menor a 0)', '399', '=', $f['bas_imp'], true) ?>
                <?= $linea('IMPUESTO A LA RENTA CAUSADO', '401', '=', $f['imp_rent_caus'], true) ?>
                <?= $linea('REBAJA POR GASTOS PERSONALES', '402', '=', $f['rebaja_gastos'], true) ?>
                <?= $linea('IMPUESTO A LA RENTA DESPUÉS DE REBAJA POR GASTOS PERSONALES', '403', '=', $f['imp_rent_rebaja'], true) ?>
                <?= $linea('VALOR DEL IMPUESTO RETENIDO Y ASUMIDO POR OTROS EMPLEADORES DURANTE EL PERÍODO DECLARADO', '404', '', $f['val_ret_otros']) ?>
                <?= $linea('VALOR DEL IMPUESTO ASUMIDO POR ESTE EMPLEADOR', '405', '', $f['val_imp_asu_este']) ?>
                <?= $linea('VALOR DEL IMPUESTO RETENIDO AL TRABAJADOR POR ESTE EMPLEADOR', '407', '', $f['val_ret']) ?>
                <?= $linea('INGRESOS GRAVADOS CON ESTE EMPLEADOR (informativo) 301+303+305+381', '349', '=', $f['ing_grav_este_empl'], true) ?>
            </table>
            <br>
            <table>
                <tr><td class="nota" style="width:100%">
                    <b>Notas para el trabajador.</b>
                    Si durante el mismo ejercicio pasa a trabajar con otro empleador, entréguele este formulario para que calcule sus retenciones del resto del año.
                    Este formulario hace las veces de su declaración de impuesto a la renta cuando en el ejercicio trabajó solo con este empleador y no tiene gastos
                    personales que reliquidar; si tuvo dos o más empleadores u otros ingresos que, sumados, superen la fracción básica exenta, debe presentar su propia
                    declaración. Las exoneraciones por discapacidad y por tercera edad no se aplican a la vez: se toma la más beneficiosa.
                    <?php if ((float) ($f['salario_digno'] ?? 0) > 0): ?>
                        El casillero 317 incluye la compensación económica para el salario digno (<?= $m($f['salario_digno']) ?>).
                    <?php endif; ?>
                </td></tr>
                <tr><td class="nota" style="width:100%"><b>Los firmantes declaran que los datos de este documento son exactos y verdaderos y asumen la responsabilidad legal que de ello se derive (Art. 101 de la Ley de Régimen Tributario Interno).</b></td></tr>
            </table>
            <br><br><br>
            <table>
                <tr>
                    <td class="sin firma" style="width:30%"></td>
                    <td class="sin" style="width:5%"></td>
                    <td class="sin firma" style="width:30%"></td>
                    <td class="sin" style="width:5%"></td>
                    <td class="sin firma" style="width:30%"></td>
                </tr>
                <tr>
                    <td class="sin c" style="width:30%"><b>FIRMA DEL AGENTE DE RETENCIÓN</b><br><?= $e($firmas['nom_rep_legal'] ?? '') ?><?= !empty($firmas['ced_rep_legal']) ? '<br>C.I. ' . $e($firmas['ced_rep_legal']) : '' ?></td>
                    <td class="sin" style="width:5%"></td>
                    <td class="sin c" style="width:30%"><b>FIRMA DEL TRABAJADOR CONTRIBUYENTE</b><br><?= $e($nombreTrab) ?><br>C.I. <?= $e($f['id_ret']) ?></td>
                    <td class="sin" style="width:5%"></td>
                    <td class="sin c" style="width:30%"><b>FIRMA DEL CONTADOR</b><br><?= $e($firmas['nombre_contador'] ?? '') ?><br><b>199</b> RUC CONTADOR: <?= $e($firmas['ruc_contador'] ?? '') ?></td>
                </tr>
            </table>
        </page>
        <?php
        return (string) ob_get_clean();
    }
}
