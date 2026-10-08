<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\CatalogoPaisesSri;
use App\Helpers\CatalogoRdep;
use App\Helpers\DigitoVerificador;
use Exception;

/**
 * Validaciones del Anexo RDEP. Reproduce las reglas del catálogo del SRI
 * (ejercicios 2024 en adelante) para avisar ANTES de subir el archivo: las
 * «graves» hacen que el portal rechace el anexo; las «leves» solo avisan.
 */
class AnexoRdepRules
{
    public const MAX_CARGAS = 5;

    public function validarCabecera(array $d): void
    {
        $anio = (int) ($d['anio'] ?? 0);
        if ($anio < 2006 || $anio > 2100) {
            throw new Exception('El ejercicio del anexo no es válido.');
        }
        if (!isset(CatalogoRdep::TIPO_EMPLEADOR[$d['tipo_empleador'] ?? ''])) {
            throw new Exception('El tipo de empleador no es válido.');
        }
        if (!isset(CatalogoRdep::ENTE_SEG_SOCIAL[$d['ente_seg_social'] ?? ''])) {
            throw new Exception('El ente de seguridad social no es válido.');
        }
        if (($d['tipo_empleador'] ?? '') === 'PRIVADO_MIXTO' && ($d['ente_seg_social'] ?? '') !== 'IESS') {
            throw new Exception('Un empleador privado o mixto solo puede informar IESS como ente de seguridad social.');
        }
        if (!preg_match('/^\d{10}001$/', (string) ($d['num_ruc'] ?? ''))) {
            throw new Exception('El RUC del empleador debe tener 13 dígitos y terminar en 001.');
        }
        foreach (['fraccion_basica', 'canasta_basica', 'porcentaje_rebaja', 'ipceg'] as $k) {
            if ((float) ($d[$k] ?? 0) < 0) {
                throw new Exception('Los parámetros del ejercicio no pueden ser negativos.');
            }
        }
    }

    public function validarEdicionPermitida(array $cab): void
    {
        if (($cab['estado'] ?? '') === 'generado') {
            // Se permite seguir editando: el archivo se vuelve a generar. Solo se avisa.
            return;
        }
    }

    /**
     * Campos de la fila que el usuario puede escribir a mano. Los calculados no.
     * @return string[]
     */
    public static function camposEditables(): array
    {
        return [
            'tip_id_ret', 'id_ret', 'apellidos', 'nombres', 'estab', 'residencia', 'pais_residencia',
            'aplica_convenio', 'tipo_discap', 'porcentaje_discap', 'tip_id_discap', 'id_discap',
            'ben_galapagos', 'enf_catastro', 'num_cargas', 'tercera_edad',
            'suel_sal', 'sob_suel', 'part_util', 'int_grab_gen', 'imp_rent_empl', 'decim_ter', 'decim_cuar',
            'fondo_reserva', 'salario_digno', 'otros_ing_no_grav',
            'sis_sal_net', 'apo_per_iess', 'apor_per_iess_otros',
            'deduc_vivienda', 'deduc_salud', 'deduc_educ', 'deduc_aliment', 'deduc_vestim', 'deduc_turismo',
            'val_ret_otros', 'val_imp_asu_este', 'val_ret', 'observaciones',
        ];
    }

    /** Campos monetarios (NUMERIC ≥ 0, 2 decimales). */
    public static function camposMonto(): array
    {
        return [
            'suel_sal', 'sob_suel', 'part_util', 'int_grab_gen', 'imp_rent_empl', 'decim_ter', 'decim_cuar',
            'fondo_reserva', 'salario_digno', 'otros_ing_no_grav', 'apo_per_iess', 'apor_per_iess_otros',
            'deduc_vivienda', 'deduc_salud', 'deduc_educ', 'deduc_aliment', 'deduc_vestim', 'deduc_turismo',
            'val_ret_otros', 'val_imp_asu_este', 'val_ret',
        ];
    }

    /** Normaliza y valida los campos que llegan del formulario del trabajador. Lanza si algo es inadmisible. */
    public function normalizarTrabajador(array $campos): array
    {
        $out = [];
        foreach ($campos as $k => $v) {
            if (!in_array($k, self::camposEditables(), true)) continue;
            if (in_array($k, self::camposMonto(), true)) {
                $num = (float) str_replace(',', '.', (string) $v);
                if ($num < 0) throw new Exception("El campo {$k} no puede ser negativo.");
                $out[$k] = round($num, 2);
            } elseif ($k === 'porcentaje_discap' || $k === 'num_cargas' || $k === 'sis_sal_net') {
                $out[$k] = (int) $v;
            } elseif ($k === 'tercera_edad') {
                $out[$k] = in_array(strtolower((string) $v), ['1', 't', 'true', 'on', 'si'], true);
            } elseif ($k === 'apellidos' || $k === 'nombres') {
                $out[$k] = CatalogoRdep::limpiarNombre((string) $v);
            } elseif ($k === 'id_ret' || $k === 'id_discap') {
                $out[$k] = CatalogoRdep::limpiarIdentificacion((string) $v);
            } elseif ($k === 'observaciones') {
                $out[$k] = trim((string) $v);
            } else {
                $out[$k] = strtoupper(trim((string) $v));
            }
        }
        if (isset($out['tip_id_ret']) && !isset(CatalogoRdep::TIPO_ID[$out['tip_id_ret']])) throw new Exception('Tipo de identificación no válido.');
        if (isset($out['residencia']) && !isset(CatalogoRdep::RESIDENCIA[$out['residencia']])) throw new Exception('Residencia no válida.');
        if (isset($out['aplica_convenio']) && !isset(CatalogoRdep::CONVENIO[$out['aplica_convenio']])) throw new Exception('Convenio no válido.');
        if (isset($out['tipo_discap']) && !isset(CatalogoRdep::TIPO_DISCAP[$out['tipo_discap']])) throw new Exception('Condición de discapacidad no válida.');
        if (isset($out['tip_id_discap']) && !isset(CatalogoRdep::TIPO_ID_DISCAP[$out['tip_id_discap']])) throw new Exception('Tipo de identificación del dependiente no válido.');
        foreach (['ben_galapagos', 'enf_catastro'] as $k) {
            if (isset($out[$k]) && !isset(CatalogoRdep::SI_NO[$out[$k]])) throw new Exception('Valor no válido en ' . $k . ' (SI/NO).');
        }
        if (isset($out['sis_sal_net']) && !isset(CatalogoRdep::SISTEMA_SALARIO_NETO[$out['sis_sal_net']])) throw new Exception('Sistema de salario neto no válido (1 o 2).');
        if (isset($out['num_cargas']) && ($out['num_cargas'] < 0 || $out['num_cargas'] > self::MAX_CARGAS)) throw new Exception('Las cargas familiares van de 0 a 5 (5 = cinco o más).');
        if (isset($out['porcentaje_discap']) && ($out['porcentaje_discap'] < 0 || $out['porcentaje_discap'] > 100)) throw new Exception('El porcentaje de discapacidad debe estar entre 0 y 100.');
        if (isset($out['estab'])) {
            $out['estab'] = str_pad(preg_replace('/\D/', '', $out['estab']) ?: '', 3, '0', STR_PAD_LEFT);
        }
        return $out;
    }

    /**
     * Validaciones de una fila ya calculada, al estilo del catálogo del SRI.
     * @return array{graves: string[], leves: string[]}
     */
    public function validarTrabajador(array $f, array $cab): array
    {
        $g = [];
        $l = [];
        $n = fn($k) => round((float) ($f[$k] ?? 0), 2);
        $fb = (float) ($cab['fraccion_basica'] ?? 0);

        // ── Identificación y nombres ──
        $tip = (string) $f['tip_id_ret'];
        $id  = (string) $f['id_ret'];
        if (!isset(CatalogoRdep::TIPO_ID[$tip])) {
            $g[] = 'Tipo de identificación no válido (C, P o E).';
        } elseif ($tip === 'C') {
            if (!preg_match('/^\d{10}$/', $id) || !DigitoVerificador::cedulaValida($id)) {
                $g[] = "La cédula {$id} no es válida.";
            }
        } elseif (!preg_match('/^[0-9A-Za-z]{3,13}$/', $id)) {
            $g[] = "La identificación {$id} debe tener entre 3 y 13 letras o números.";
        }
        foreach (['apellidos' => 'Apellidos', 'nombres' => 'Nombres'] as $k => $et) {
            $v = (string) ($f[$k] ?? '');
            if (!preg_match('/^[A-Za-z ]{2,100}$/', $v)) {
                $g[] = "{$et}: solo letras y espacios, de 2 a 100 caracteres (sin ñ ni tildes).";
            } elseif (str_contains($v, '  ')) {
                $g[] = "{$et}: no puede tener dos espacios seguidos.";
            }
        }
        if (!preg_match('/^\d{3}$/', (string) $f['estab']) || $f['estab'] === '000') {
            $g[] = 'El código de establecimiento debe ser de 3 dígitos y distinto de 000.';
        }

        // ── Residencia / convenio ──
        $res = (string) $f['residencia'];
        $pais = (string) $f['pais_residencia'];
        $conv = (string) $f['aplica_convenio'];
        if ($res === '01') {
            if ($pais !== CatalogoPaisesSri::ECUADOR) $g[] = 'Residente local: el país debe ser 593 (Ecuador).';
            if ($conv !== 'NA') $g[] = 'Residente local: el convenio de doble imposición debe ser NA.';
        } elseif ($res === '02') {
            if ($pais === CatalogoPaisesSri::ECUADOR || $pais === '000' || !preg_match('/^\d{3}$/', $pais)) $g[] = 'Residente del exterior: indique el país (distinto de Ecuador).';
            if (!in_array($conv, ['SI', 'NO'], true)) $g[] = 'Residente del exterior: el convenio debe ser SI o NO.';
        } else {
            $g[] = 'Residencia no válida (01 o 02).';
        }

        // ── Discapacidad ──
        $td  = (string) $f['tipo_discap'];
        $pct = (int) $f['porcentaje_discap'];
        if (!isset(CatalogoRdep::TIPO_DISCAP[$td])) {
            $g[] = 'Condición de discapacidad no válida (01, 02 o 03).';
        } elseif ($td === '01') {
            if ($pct !== 0) $g[] = 'Sin discapacidad el porcentaje debe ser 0.';
            if ((string) $f['tip_id_discap'] !== 'N' || (string) $f['id_discap'] !== '999') $g[] = 'Sin discapacidad, la identificación del dependiente debe ser N / 999.';
        } else {
            if ($pct < CatalogoRdep::DISCAP_MINIMO || $pct >= 100) $g[] = 'El porcentaje de discapacidad debe ser de 30% a 99%.';
            if ($td === '02' && ((string) $f['tip_id_discap'] !== 'N' || (string) $f['id_discap'] !== '999')) {
                $g[] = 'Trabajador con discapacidad: la identificación del dependiente debe ser N / 999.';
            }
            if ($td === '03') {
                $tdd = (string) $f['tip_id_discap'];
                $idd = (string) $f['id_discap'];
                if (!in_array($tdd, ['C', 'P', 'E'], true)) $g[] = 'Sustituto: indique el tipo de identificación de la persona con discapacidad (C, P o E).';
                elseif ($tdd === 'C' && (!preg_match('/^\d{10}$/', $idd) || !DigitoVerificador::cedulaValida($idd))) $g[] = 'Sustituto: la cédula de la persona con discapacidad no es válida.';
                elseif ($tdd !== 'C' && !preg_match('/^[0-9A-Za-z]{3,13}$/', $idd)) $g[] = 'Sustituto: la identificación de la persona con discapacidad no es válida.';
                if ($idd === $id) $g[] = 'Sustituto: la identificación de la persona con discapacidad debe ser distinta a la del trabajador.';
            }
        }
        if ($n('exo_discap') > 0 && !in_array($td, ['02', '03'], true)) $g[] = 'La exoneración por discapacidad solo aplica a trabajadores con discapacidad o sustitutos.';
        if ($fb > 0 && $n('exo_discap') > round(2 * $fb, 2) + 0.01) $g[] = 'La exoneración por discapacidad supera el doble de la fracción básica.';
        if ($fb > 0 && $n('exo_ter_ed') > $fb + 0.01) $g[] = 'La exoneración por tercera edad supera la fracción básica.';
        if ($n('exo_discap') > 0 && $n('exo_ter_ed') > 0) $g[] = 'No pueden aplicarse a la vez la exoneración por discapacidad y por tercera edad.';

        $cargas = (int) $f['num_cargas'];
        if ($cargas < 0 || $cargas > self::MAX_CARGAS) $g[] = 'Las cargas familiares deben ir de 0 a 5.';

        // ── Aportes y sistema de salario neto ──
        $sis = (int) $f['sis_sal_net'];
        if ($sis === 2 && $n('apo_per_iess') > 0) {
            $g[] = 'Con sistema de salario neto el aporte personal con este empleador debe ser 0,00.';
        }
        if ($sis === 1 && $n('apo_per_iess') <= 0) {
            $l[] = 'Sin sistema de salario neto y sin aporte personal a la seguridad social con este empleador.';
        }
        if ($n('apo_per_iess') > 0 && $n('suel_sal') <= 0) {
            $l[] = 'Hay aporte personal pero no sueldos (materia gravada de seguridad social).';
        }
        $topePct = (($cab['tipo_empleador'] ?? '') === 'PUBLICO') ? (($cab['ente_seg_social'] ?? '') === 'IESS' ? 11.45 : 23.10) : 9.45;
        if ($n('suel_sal') > 0 && $n('apo_per_iess') > round($n('suel_sal') * $topePct / 100, 2) + 0.05) {
            $l[] = "El aporte personal supera el {$topePct}% de los sueldos y salarios.";
        }
        if ($n('int_grab_gen') > 0 && $n('apor_per_iess_otros') <= 0) {
            $l[] = 'Hay ingresos con otros empleadores sin aporte personal con otros empleadores.';
        }
        if ($n('int_grab_gen') <= 0 && $n('apor_per_iess_otros') > 0) {
            $l[] = 'Hay aporte personal con otros empleadores sin ingresos con otros empleadores.';
        }
        if ($n('int_grab_gen') > 0 && $n('apor_per_iess_otros') > round($n('int_grab_gen') * 23.10 / 100, 2) + 0.05) {
            $l[] = 'El aporte personal con otros empleadores supera el 23,10% de esos ingresos.';
        }

        // ── Resumen impositivo ──
        if ($n('int_grab_gen') <= 0 && $n('val_ret_otros') > 0) {
            $g[] = 'Sin ingresos con otros empleadores, el impuesto retenido por otros empleadores debe ser 0,00.';
        }
        if ($n('int_grab_gen') > 0 && $n('val_ret_otros') >= $n('int_grab_gen')) {
            $l[] = 'El impuesto retenido por otros empleadores debería ser menor que esos ingresos.';
        }
        if ($n('val_imp_asu_este') + 0.01 < $n('imp_rent_empl')) {
            $l[] = 'El impuesto asumido por este empleador es menor que el IR asumido registrado como ingreso.';
        }
        $sumaRet = round($n('val_ret') + $n('val_imp_asu_este') + $n('val_ret_otros'), 2);
        $dif = round($sumaRet - $n('imp_rent_rebaja'), 2);
        if (abs($dif) > 0.01) {
            $l[] = 'Retenido + asumido (' . number_format($sumaRet, 2) . ') difiere del impuesto después de la rebaja ('
                . number_format($n('imp_rent_rebaja'), 2) . '): diferencia ' . number_format($dif, 2) . '.';
        }
        if ($n('val_ret') > round($n('imp_rent_caus') - $n('val_imp_asu_este'), 2) + 0.01) {
            $l[] = 'Lo retenido al trabajador supera el impuesto causado menos el asumido por el empleador.';
        }

        return ['graves' => $g, 'leves' => $l];
    }

    /** Duplicados por identificación dentro del anexo (el esquema los rechaza). */
    public function duplicados(array $filas): array
    {
        $vistos = [];
        $dup = [];
        foreach ($filas as $f) {
            $k = $f['tip_id_ret'] . '|' . $f['id_ret'];
            if (isset($vistos[$k])) $dup[$k] = true;
            $vistos[$k] = true;
        }
        return array_keys($dup);
    }

    public function validarGeneracion(array $cab, int $graves, int $trabajadores): void
    {
        if ($trabajadores <= 0) {
            throw new Exception('El anexo no tiene trabajadores: importe la nómina del ejercicio o agregue trabajadores.');
        }
        if ($graves > 0) {
            throw new Exception("Hay {$graves} observación(es) grave(s) que el SRI rechazaría. Corríjalas antes de generar el archivo.");
        }
    }
}
