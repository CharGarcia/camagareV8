<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\CatalogoAdi;
use InvalidArgumentException;

/**
 * Validaciones de negocio del Anexo de Dividendos (ADI).
 *
 * Dos niveles, a propósito distintos:
 *
 *  - validarCabecera / validarBeneficiario / validarDetalle: se ejecutan al
 *    GUARDAR y lanzan excepción. Solo comprueban lo que hace inconsistente el
 *    registro (catálogos, formatos, relaciones entre campos de la ficha).
 *
 *  - validarAnexo: se ejecuta antes de GENERAR el archivo y devuelve listas de
 *    errores y advertencias sin lanzar nada. Aquí van los cuadres entre
 *    secciones y las comprobaciones que el portal del SRI hace al cargar
 *    (dígitos verificadores, sumatorias, vigencias). Se puede seguir trabajando
 *    con advertencias; con errores no se genera el archivo.
 */
class AnexoDividendosRules
{
    /** El anexo solo acepta períodos desde 2010 (Resolución NAC-DGERCGC15-00000564). */
    public const ANIO_MINIMO = 2010;

    /** Desde este año rige el esquema del catálogo que implementa el módulo. */
    public const ANIO_ESQUEMA_ACTUAL = 2020;

    // ── Validaciones de guardado ─────────────────────────────────────────────

    public function validarCabecera(array $d): void
    {
        $anio = (int) ($d['anio'] ?? 0);
        if ($anio < self::ANIO_MINIMO) {
            throw new InvalidArgumentException('El año informado debe ser ' . self::ANIO_MINIMO . ' o posterior.');
        }
        if ($anio > (int) date('Y')) {
            throw new InvalidArgumentException('El año informado no puede ser mayor al año en curso.');
        }

        $tipoInformante = (string) ($d['tipo_informante'] ?? '');
        if (!isset(CatalogoAdi::TIPO_INFORMANTE[$tipoInformante])) {
            throw new InvalidArgumentException('El tipo de informante no es válido.');
        }

        $tipoId = (string) ($d['tipo_id_informante'] ?? '');
        if (!isset(CatalogoAdi::TIPO_IDENTIFICACION[$tipoId])) {
            throw new InvalidArgumentException('El tipo de identificación del informante no es válido.');
        }
        // Ficha, sección A.2: solo la persona natural (código 02) puede informar
        // con cédula o pasaporte; el resto son sociedades y van siempre con RUC.
        if ($tipoInformante !== '02' && $tipoId !== 'R') {
            throw new InvalidArgumentException('Ese tipo de informante debe identificarse con RUC.');
        }
        if ($tipoInformante === '02' && !in_array($tipoId, ['R', 'C', 'P'], true)) {
            throw new InvalidArgumentException('La persona natural debe identificarse con RUC, cédula o pasaporte.');
        }

        $this->validarFormatoIdentificacion($tipoId, (string) ($d['id_informante'] ?? ''), 'del informante');

        $razon = trim((string) ($d['razon_social'] ?? ''));
        if (mb_strlen($razon) < 5 || mb_strlen($razon) > 500) {
            throw new InvalidArgumentException('La razón social debe tener entre 5 y 500 caracteres.');
        }
    }

    public function validarBeneficiario(array $d, int $anioInformado): void
    {
        $tipoId = (string) ($d['tipo_id_perceptor'] ?? '');
        if (!isset(CatalogoAdi::TIPO_IDENTIFICACION[$tipoId])) {
            throw new InvalidArgumentException('El tipo de identificación del beneficiario no es válido.');
        }

        $this->validarFormatoIdentificacion($tipoId, (string) ($d['numero_id_perceptor'] ?? ''), 'del beneficiario');

        $tipoBenef = (string) ($d['tipo_beneficiario'] ?? '');
        if (!isset(CatalogoAdi::TIPO_BENEFICIARIO[$tipoBenef])) {
            throw new InvalidArgumentException('El tipo de beneficiario no es válido.');
        }
        if (!in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_POR_TIPO_ID[$tipoId], true)) {
            throw new InvalidArgumentException(
                'Con identificación tipo "' . CatalogoAdi::TIPO_IDENTIFICACION[$tipoId] .
                '" no se puede usar el tipo de beneficiario ' . $tipoBenef . '.'
            );
        }
        // El código 13 (Asociaciones Público Privadas) rige desde 2016.
        if ($tipoBenef === '13' && $anioInformado < 2016) {
            throw new InvalidArgumentException('El tipo de beneficiario 13 rige a partir del período fiscal 2016.');
        }

        $pais = (string) ($d['pais_residencia'] ?? '');
        if (!CatalogoAdi::paisValido($tipoBenef, $pais)) {
            throw new InvalidArgumentException(
                'El país ' . $pais . ' no es válido para el tipo de beneficiario ' . $tipoBenef . '.'
            );
        }

        $regimen = (string) ($d['regimen_fiscal_preferente'] ?? '');
        $aplicaRegimen = in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_REGIMEN_FISCAL, true);
        if ($aplicaRegimen && !isset(CatalogoAdi::RESPUESTA[$regimen])) {
            throw new InvalidArgumentException(
                'Indique si el dividendo está gravado en el estado de residencia del beneficiario.'
            );
        }
        if (!$aplicaRegimen && $regimen !== '') {
            throw new InvalidArgumentException(
                'El tipo de beneficiario ' . $tipoBenef . ' no admite la pregunta de régimen fiscal preferente.'
            );
        }

        $tipoIdEfec   = (string) ($d['tipo_id_beneficiario_efectivo'] ?? '');
        $numeroIdEfec = (string) ($d['numero_id_beneficiario_efectivo'] ?? '');
        $aplicaEfec   = in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_CON_BENEF_EFECTIVO, true);

        if (!$aplicaEfec && ($tipoIdEfec !== '' || $numeroIdEfec !== '')) {
            throw new InvalidArgumentException(
                'El beneficiario efectivo solo se informa con tipos de beneficiario 09 u 11.'
            );
        }
        if ($tipoIdEfec !== '') {
            if (!in_array($tipoIdEfec, CatalogoAdi::TIPOS_ID_BENEFICIARIO_EFECTIVO, true)) {
                throw new InvalidArgumentException('El beneficiario efectivo solo admite RUC, cédula o pasaporte.');
            }
            $this->validarFormatoIdentificacion($tipoIdEfec, $numeroIdEfec, 'del beneficiario efectivo');
        }
        if ($tipoIdEfec === '' && $numeroIdEfec !== '') {
            throw new InvalidArgumentException(
                'Seleccione el tipo de identificación del beneficiario efectivo.'
            );
        }
    }

    public function validarDetalle(array $d, array $beneficiario, int $anioInformado): void
    {
        $anioGen = (int) ($d['anio_genera_utilidad'] ?? 0);
        if ($anioGen < self::ANIO_MINIMO - 10 || $anioGen > $anioInformado) {
            throw new InvalidArgumentException(
                'El año en que se generaron las utilidades no puede ser mayor al período informado.'
            );
        }

        $tipoDiv = (string) ($d['tipo_dividendo'] ?? '');
        if (!isset(CatalogoAdi::TIPO_DIVIDENDO[$tipoDiv])) {
            throw new InvalidArgumentException('El tipo de dividendo distribuido no es válido.');
        }
        if (!CatalogoAdi::dividendoVigente($tipoDiv, $anioInformado)) {
            throw new InvalidArgumentException(
                'El tipo de dividendo ' . $tipoDiv . ' no está vigente para el período ' . $anioInformado . '.'
            );
        }
        $tipoBenef = (string) ($beneficiario['tipo_beneficiario'] ?? '');
        if (!in_array($tipoBenef, CatalogoAdi::TIPO_DIVIDENDO[$tipoDiv][3], true)) {
            throw new InvalidArgumentException(
                'El tipo de dividendo ' . $tipoDiv . ' no aplica al tipo de beneficiario ' . $tipoBenef . '.'
            );
        }

        $fecha = (string) ($d['fecha_registro_contable'] ?? '');
        if (!$this->fechaValida($fecha)) {
            throw new InvalidArgumentException('La fecha de registro contable de la distribución no es válida.');
        }
        if ((int) substr($fecha, 0, 4) !== $anioInformado) {
            throw new InvalidArgumentException(
                'La fecha de registro contable debe corresponder al año informado (' . $anioInformado . ').'
            );
        }

        $monto = round((float) ($d['monto_dividendo_distribuido'] ?? 0), 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del dividendo distribuido debe ser mayor a cero.');
        }
        if ($monto > 9999999999.99) {
            throw new InvalidArgumentException('El monto del dividendo distribuido excede los 10 enteros permitidos.');
        }

        $gravado = round((float) ($d['ingreso_gravado'] ?? 0), 2);
        if ($gravado < 0 || $gravado > $monto) {
            throw new InvalidArgumentException(
                'El ingreso gravado por dividendos no puede ser negativo ni mayor al monto distribuido.'
            );
        }
        if (!CatalogoAdi::dividendoGravado($tipoDiv) && $gravado > 0) {
            throw new InvalidArgumentException(
                'El tipo de dividendo ' . $tipoDiv . ' es exento: el ingreso gravado debe ser 0,00.'
            );
        }

        $retencion = round((float) ($d['monto_retencion'] ?? 0), 2);
        if ($retencion < 0 || $retencion > $gravado) {
            throw new InvalidArgumentException(
                'El monto de la retención no puede ser negativo ni mayor al ingreso gravado por dividendos.'
            );
        }

        $pagado = (string) ($d['dividendo_pagado'] ?? '');
        if (!isset(CatalogoAdi::RESPUESTA[$pagado])) {
            throw new InvalidArgumentException('Indique si el dividendo está pagado.');
        }

        $isd = round((float) ($d['isd_pagado'] ?? 0), 2);
        if ($isd < 0 || $isd > $monto) {
            throw new InvalidArgumentException(
                'El ISD pagado no puede ser negativo ni mayor al monto del dividendo distribuido.'
            );
        }
        // Ficha, campo 16: el ISD solo se informa cuando el dividendo está pagado.
        if ($pagado === '02' && $isd > 0) {
            throw new InvalidArgumentException('El ISD pagado solo se informa cuando el dividendo está pagado.');
        }
    }

    // ── Validación previa a generar el archivo ───────────────────────────────

    /**
     * Revisa el anexo completo contra las reglas de la ficha técnica.
     *
     * @return array{errores: string[], advertencias: string[]}
     */
    public function validarAnexo(array $anexo, array $beneficiarios, array $detalles): array
    {
        $errores      = [];
        $advertencias = [];
        $anio         = (int) $anexo['anio'];

        if ($anio < self::ANIO_ESQUEMA_ACTUAL) {
            $advertencias[] = 'El módulo genera el esquema vigente desde ' . self::ANIO_ESQUEMA_ACTUAL .
                '. Para períodos anteriores el SRI exige campos adicionales (tarifa aplicada, crédito tributario, ' .
                'primera sociedad y forma de pago) que este anexo no reporta.';
        }

        // La cabecera y la sección B están calcadas de un archivo del DIMM; el
        // bloque de dividendos todavía no se ha contrastado con uno, así que sus
        // etiquetas de agrupación podrían no coincidir con las del esquema.
        if ($detalles !== []) {
            $advertencias[] = 'La estructura XML de la sección de dividendos aún no se ha verificado contra un ' .
                'archivo del DIMM: si el portal rechaza el archivo por esquema, avise para ajustarla. La sección ' .
                'de utilidades sí está verificada.';
        }

        // ── A.2 Informante ───────────────────────────────────────────────────
        // No se comprueba el dígito verificador de su identificación: el RUC sale
        // de la empresa activa, que ya lo validó al registrarse, y aquí no se
        // puede editar (se cambia en la configuración de la empresa), así que la
        // advertencia no tendría ninguna acción asociada. Además hay RUCs reales
        // en uso que no superan el módulo 11 y el aviso sería un falso positivo.
        $tipoInformante = (string) $anexo['tipo_informante'];
        $sinSeccionB = in_array($tipoInformante, CatalogoAdi::INFORMANTES_SIN_SECCION_B, true);
        $sinSeccionC = in_array($tipoInformante, CatalogoAdi::INFORMANTES_SIN_SECCION_C, true);

        // ── B. Información de utilidades ─────────────────────────────────────
        $b1 = round((float) $anexo['utilidad_ejercicio'], 2);
        $b2 = round((float) $anexo['utilidad_distribuida_distinta_reinv'], 2);
        $b3 = round((float) $anexo['utilidad_reinvertida_con_derecho'], 2);
        $b4 = round((float) $anexo['utilidad_reinvertida_sin_derecho'], 2);
        $b5 = round((float) $anexo['utilidad_pagada_anticipado'], 2);
        $b6 = round((float) $anexo['utilidad_no_distribuida'], 2);
        $b7 = round((float) $anexo['utilidad_no_distrib_ejer_ant'], 2);
        $b8 = round((float) $anexo['utilidad_distrib_ejercicios_ant'], 2);

        if ($sinSeccionB) {
            if ($b1 || $b2 || $b3 || $b4 || $b5 || $b6 || $b7 || $b8) {
                $errores[] = 'El tipo de informante ' . $tipoInformante .
                    ' no reporta la sección "Información de utilidades"; deje esos campos en cero.';
            }
        } else {
            if (!$this->distinto($b1 + $b2 + $b3 + $b4 + $b5 + $b6 + $b7 + $b8, 0.0)) {
                $advertencias[] = 'No se reportaron valores en ningún campo de la sección "Información de utilidades". Verifique.';
            }
            if ($this->distinto($b2 + $b3 + $b4 + $b6, $b1)) {
                $errores[] = sprintf(
                    'La suma de utilidad distribuida (%s), reinvertida con derecho (%s), reinvertida sin derecho (%s) ' .
                    'y no distribuida (%s) es %s y debe ser igual a la utilidad del ejercicio informado (%s).',
                    number_format($b2, 2), number_format($b3, 2), number_format($b4, 2),
                    number_format($b6, 2), number_format($b2 + $b3 + $b4 + $b6, 2), number_format($b1, 2)
                );
            }
            foreach ([['distribuida del ejercicio', $b2], ['reinvertida con derecho a reducción', $b3], ['reinvertida sin derecho a reducción', $b4]] as [$etiqueta, $valor]) {
                if ($valor > $b1) {
                    $errores[] = 'La utilidad ' . $etiqueta . ' no puede superar la utilidad del ejercicio informado.';
                }
            }
            if ($b8 > $b7) {
                $errores[] = 'La utilidad distribuida de ejercicios anteriores no puede superar la utilidad ' .
                    'generada en ejercicios anteriores pendiente de distribución.';
            }
        }

        // ── C. Dividendos distribuidos ───────────────────────────────────────
        if ($sinSeccionC && $detalles !== []) {
            $errores[] = 'El tipo de informante ' . $tipoInformante .
                ' no reporta dividendos distribuidos; elimine los registros de la sección C.';
        }

        if (!$sinSeccionC) {
            $sumaEjercicio  = 0.0;
            $sumaAnteriores = 0.0;
            $indice         = [];
            foreach ($beneficiarios as $b) {
                $indice[(int) $b['id']] = $b;
            }

            foreach ($detalles as $d) {
                $monto   = round((float) $d['monto_dividendo_distribuido'], 2);
                $anioGen = (int) $d['anio_genera_utilidad'];
                if ($anioGen >= $anio) {
                    $sumaEjercicio += $monto;
                } else {
                    $sumaAnteriores += $monto;
                }

                $tipoDiv = (string) $d['tipo_dividendo'];
                if (!CatalogoAdi::dividendoVigente($tipoDiv, $anio)) {
                    $errores[] = 'El tipo de dividendo ' . $tipoDiv . ' no está vigente para el período ' . $anio .
                        ' (beneficiario ' . ($d['numero_id_perceptor'] ?? '') . ').';
                }
                $benef = $indice[(int) $d['id_beneficiario']] ?? null;
                if ($benef !== null && !in_array((string) $benef['tipo_beneficiario'], CatalogoAdi::TIPO_DIVIDENDO[$tipoDiv][3] ?? [], true)) {
                    $errores[] = 'El tipo de dividendo ' . $tipoDiv . ' no aplica al beneficiario ' .
                        $benef['numero_id_perceptor'] . ' (tipo ' . $benef['tipo_beneficiario'] . ').';
                }
                if (CatalogoAdi::dividendoGravado($tipoDiv) && round((float) $d['ingreso_gravado'], 2) <= 0) {
                    $advertencias[] = 'El dividendo del ' . $d['fecha_registro_contable'] . ' a ' .
                        ($d['numero_id_perceptor'] ?? '') . ' es gravado y no tiene ingreso gravado registrado.';
                }
            }

            $sumaEjercicio  = round($sumaEjercicio, 2);
            $sumaAnteriores = round($sumaAnteriores, 2);

            if (!$sinSeccionB) {
                if (($b2 + $b3 + $b4) > 0 && !$this->distinto($sumaEjercicio, 0.0)) {
                    $errores[] = 'Se reportó utilidad distribuida del ejercicio informado pero no existe ningún ' .
                        'dividendo con año de generación ' . $anio . '.';
                }
                if ($b8 > 0 && !$this->distinto($sumaAnteriores, 0.0)) {
                    $errores[] = 'Se reportó utilidad distribuida de ejercicios anteriores pero no existe ningún ' .
                        'dividendo con año de generación anterior a ' . $anio . '.';
                }
                if ($this->distinto($b8, $sumaAnteriores)) {
                    $advertencias[] = sprintf(
                        'La utilidad distribuida de ejercicios anteriores (%s) no coincide con la suma de dividendos ' .
                        'distribuidos de años anteriores (%s).',
                        number_format($b8, 2), number_format($sumaAnteriores, 2)
                    );
                }
                if ($this->distinto($b2, $sumaEjercicio)) {
                    $advertencias[] = sprintf(
                        'La utilidad distribuida del ejercicio informado distinta de reinversión (%s) no coincide con ' .
                        'la suma de dividendos distribuidos del año %d (%s).',
                        number_format($b2, 2), $anio, number_format($sumaEjercicio, 2)
                    );
                }
            }

            foreach ($beneficiarios as $b) {
                $err = $this->verificarDigitoVerificador((string) $b['tipo_id_perceptor'], (string) $b['numero_id_perceptor']);
                if ($err !== null) {
                    $advertencias[] = 'Beneficiario ' . $b['numero_id_perceptor'] . ': ' . $err;
                }
                if ((int) ($b['total_detalles'] ?? 0) === 0) {
                    $advertencias[] = 'El beneficiario ' . $b['numero_id_perceptor'] .
                        ' no tiene ningún dividendo registrado.';
                }
            }

            // La clave del anexo es beneficiario + fecha de distribución: dos
            // registros iguales los rechaza el portal como duplicados.
            $vistos = [];
            foreach ($detalles as $d) {
                $clave = $d['id_beneficiario'] . '|' . $d['fecha_registro_contable'] . '|' . $d['anio_genera_utilidad'] . '|' . $d['tipo_dividendo'];
                if (isset($vistos[$clave])) {
                    $advertencias[] = 'Hay dos dividendos idénticos (mismo beneficiario, fecha, año de generación y ' .
                        'tipo) el ' . $d['fecha_registro_contable'] . '; el portal los tomará como duplicados.';
                }
                $vistos[$clave] = true;
            }

            if ($detalles === [] && !$sinSeccionB && ($b2 + $b3 + $b4 + $b8) > 0) {
                $errores[] = 'No hay dividendos registrados en la sección C pese a haber utilidades distribuidas.';
            }
        }

        return ['errores' => $errores, 'advertencias' => $advertencias];
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    /** Formato y longitud de la identificación según su tipo (tabla 1). */
    private function validarFormatoIdentificacion(string $tipoId, string $numero, string $sujeto): void
    {
        $numero = trim($numero);
        if ($numero === '') {
            throw new InvalidArgumentException('Ingrese el número de identificación ' . $sujeto . '.');
        }

        switch ($tipoId) {
            case 'R':
                if (!preg_match('/^\d{13}$/', $numero)) {
                    throw new InvalidArgumentException('El RUC ' . $sujeto . ' debe tener 13 dígitos.');
                }
                if (substr($numero, -3) !== '001') {
                    throw new InvalidArgumentException('El RUC ' . $sujeto . ' debe terminar en 001.');
                }
                break;

            case 'C':
                if (!preg_match('/^\d{10}$/', $numero)) {
                    throw new InvalidArgumentException('La cédula ' . $sujeto . ' debe tener 10 dígitos.');
                }
                break;

            case 'P':
            case 'E':
                if (!preg_match('/^[A-Za-z0-9]{3,13}$/', $numero)) {
                    throw new InvalidArgumentException(
                        'La identificación ' . $sujeto . ' debe tener entre 3 y 13 caracteres alfanuméricos, sin símbolos.'
                    );
                }
                break;
        }
    }

    /**
     * Dígito verificador de cédula y RUC ecuatorianos.
     * Devuelve el mensaje del problema, o null si está correcto (o si el tipo de
     * identificación es extranjero y no tiene algoritmo que comprobar).
     */
    public function verificarDigitoVerificador(string $tipoId, string $numero): ?string
    {
        $numero = trim($numero);

        if ($tipoId === 'C') {
            return $this->cedulaValida($numero) ? null : 'la cédula ' . $numero . ' no supera el dígito verificador.';
        }
        if ($tipoId === 'R') {
            if (!preg_match('/^\d{13}$/', $numero)) {
                return 'el RUC ' . $numero . ' no tiene 13 dígitos.';
            }
            return $this->rucValido($numero) ? null : 'el RUC ' . $numero . ' no supera el dígito verificador.';
        }

        return null; // pasaporte e identificación del exterior no tienen algoritmo
    }

    /** Módulo 10 sobre los 9 primeros dígitos (cédula de identidad). */
    public function cedulaValida(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }
        $provincia = (int) substr($cedula, 0, 2);
        if ($provincia < 1 || ($provincia > 24 && $provincia !== 30)) {
            return false;
        }
        if ((int) $cedula[2] > 5) {
            return false; // el tercer dígito de una cédula siempre es menor a 6
        }

        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $valor = (int) $cedula[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma += $valor > 9 ? $valor - 9 : $valor;
        }
        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int) $cedula[9];
    }

    /**
     * RUC ecuatoriano. Según el tercer dígito:
     *   0-5 persona natural  → cédula válida + 001
     *   6   sector público   → módulo 11 sobre 8 dígitos, verificador en la 9.ª posición
     *   9   sociedad privada → módulo 11 sobre 9 dígitos, verificador en la 10.ª posición
     */
    public function rucValido(string $ruc): bool
    {
        if (!preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }
        $provincia = (int) substr($ruc, 0, 2);
        if ($provincia < 1 || ($provincia > 24 && $provincia !== 30)) {
            return false;
        }

        $tercer = (int) $ruc[2];

        if ($tercer < 6) {
            return $this->cedulaValida(substr($ruc, 0, 10));
        }

        if ($tercer === 6) {
            return $this->modulo11($ruc, [3, 2, 7, 6, 5, 4, 3, 2], 8);
        }

        if ($tercer === 9) {
            return $this->modulo11($ruc, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9);
        }

        return false;
    }

    /** @param int[] $coeficientes */
    private function modulo11(string $numero, array $coeficientes, int $posicionVerificador): bool
    {
        $suma = 0;
        foreach ($coeficientes as $i => $coef) {
            $suma += (int) $numero[$i] * $coef;
        }
        $residuo     = $suma % 11;
        $verificador = $residuo === 0 ? 0 : 11 - $residuo;

        return $verificador === (int) $numero[$posicionVerificador];
    }

    /**
     * Dos importes difieren de verdad. Los montos del anexo son valores
     * monetarios que llegan desde NUMERIC de Postgres, de inputs de texto y de
     * sumas en PHP: compararlos con != daría falsos descuadres por el error de
     * representación de los flotantes, así que la tolerancia es medio centavo.
     */
    private function distinto(float $a, float $b): bool
    {
        return abs($a - $b) >= 0.005;
    }

    private function fechaValida(string $fecha): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
