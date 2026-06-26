<?php

declare(strict_types=1);

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;

/**
 * Validador del Anexo Transaccional Simplificado (ATS).
 *
 * El SRI no publica un XSD estricto del ATS; su portal valida el archivo y
 * devuelve un listado de "errores y advertencias". Esta clase reproduce esas
 * reglas a partir de la ficha técnica (obligatoriedad, longitudes, formatos,
 * catálogos y coherencias) para detectar problemas ANTES de cargar al SRI.
 *
 * Si existe un esquema en storage/xsd/ats.xsd, además valida con schemaValidate.
 */
class AtsValidatorService
{
    private const SUSTENTOS = ['00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15'];
    private const TP_ID_PROV = ['01','02','03'];

    /** @return array{errores: string[], advertencias: string[]} */
    public function validar(string $xml): array
    {
        $errores = [];
        $advertencias = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xml)) {
            foreach (libxml_get_errors() as $e) {
                $errores[] = 'XML mal formado: ' . trim($e->message);
            }
            libxml_clear_errors();
            return ['errores' => $errores, 'advertencias' => $advertencias];
        }

        $this->validarInformante($dom, $errores);
        $this->validarCompras($dom, $errores, $advertencias);
        $this->validarConXsd($dom, $errores);

        return [
            'errores'      => array_values(array_unique($errores)),
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }

    // ── Informante ───────────────────────────────────────────────────────────

    private function validarInformante(DOMDocument $dom, array &$err): void
    {
        $iva = $dom->getElementsByTagName('iva')->item(0);
        if (!$iva instanceof DOMElement) {
            $err[] = 'Falta el nodo raíz <iva>.';
            return;
        }

        $id = $this->texto($iva, 'IdInformante');
        if (!preg_match('/^\d{13}$/', $id)) {
            $err[] = "Informante: IdInformante debe tener 13 dígitos (actual: '{$id}').";
        } elseif (substr($id, -3) !== '001') {
            $err[] = "Informante: IdInformante debe terminar en 001 (actual: '{$id}').";
        } elseif (!$this->rucValido($id)) {
            $err[] = "Informante: IdInformante '{$id}' no supera el dígito verificador.";
        }

        $rs = $this->texto($iva, 'razonSocial');
        if (mb_strlen($rs) < 5) {
            $err[] = 'Informante: razonSocial es obligatoria (mínimo 5 caracteres).';
        } elseif (!preg_match('/^[A-Z0-9 ]+$/', $rs)) {
            $err[] = "Informante: razonSocial contiene caracteres no permitidos (solo letras, números y espacios): '{$rs}'.";
        }

        if (!preg_match('/^\d{4}$/', $this->texto($iva, 'Anio'))) {
            $err[] = 'Informante: Anio debe tener 4 dígitos.';
        }
        $mes = $this->texto($iva, 'Mes');
        if (!preg_match('/^(0[1-9]|1[0-2])$/', $mes)) {
            $err[] = "Informante: Mes inválido (actual: '{$mes}').";
        }
        $ne = $this->texto($iva, 'numEstabRuc');
        if (!preg_match('/^\d{3}$/', $ne) || (int) $ne < 1) {
            $err[] = "Informante: numEstabRuc debe ser 3 dígitos y mayor a 000 (actual: '{$ne}').";
        }
        if ($this->texto($iva, 'codigoOperativo') !== 'IVA') {
            $err[] = 'Informante: codigoOperativo debe ser IVA.';
        }
    }

    // ── Compras ──────────────────────────────────────────────────────────────

    private function validarCompras(DOMDocument $dom, array &$err, array &$adv): void
    {
        $detalles = $dom->getElementsByTagName('detalleCompras');
        $i = 0;
        foreach ($detalles as $d) {
            $i++;
            if (!$d instanceof DOMElement) {
                continue;
            }
            $p = "Compra #{$i}";

            $cod = $this->texto($d, 'codSustento');
            if (!in_array($cod, self::SUSTENTOS, true)) {
                $err[] = "{$p}: codSustento '{$cod}' no es válido (Tabla 5).";
            }

            $tp = $this->texto($d, 'tpIdProv');
            if (!in_array($tp, self::TP_ID_PROV, true)) {
                $err[] = "{$p}: tpIdProv debe ser 01, 02 o 03 (actual: '{$tp}').";
            }

            $idProv = $this->texto($d, 'idProv');
            if ($tp === '01' && !preg_match('/^\d{13}$/', $idProv)) {
                $err[] = "{$p}: idProv (RUC) debe tener 13 dígitos.";
            } elseif ($tp === '02' && !preg_match('/^\d{10}$/', $idProv)) {
                $err[] = "{$p}: idProv (Cédula) debe tener 10 dígitos.";
            } elseif ($idProv === '') {
                $err[] = "{$p}: idProv es obligatorio.";
            }

            $tipoComp = $this->texto($d, 'tipoComprobante');
            if (!preg_match('/^\d{2,3}$/', $tipoComp)) {
                $err[] = "{$p}: tipoComprobante inválido (actual: '{$tipoComp}').";
            }

            if (!in_array($this->texto($d, 'parteRel'), ['SI', 'NO'], true)) {
                $err[] = "{$p}: parteRel debe ser SI o NO.";
            }

            $fReg = $this->texto($d, 'fechaRegistro');
            $fEmi = $this->texto($d, 'fechaEmision');
            if (!$this->fechaValida($fReg)) {
                $err[] = "{$p}: fechaRegistro inválida (dd/mm/aaaa).";
            }
            if (!$this->fechaValida($fEmi)) {
                $err[] = "{$p}: fechaEmision inválida (dd/mm/aaaa).";
            }
            if ($this->fechaValida($fReg) && $this->fechaValida($fEmi)
                && $this->aTs($fEmi) > $this->aTs($fReg)) {
                $err[] = "{$p}: fechaEmision ({$fEmi}) no puede ser mayor a fechaRegistro ({$fReg}).";
            }

            if (!preg_match('/^\d{3}$/', $this->texto($d, 'establecimiento'))) {
                $err[] = "{$p}: establecimiento debe tener 3 dígitos.";
            }
            if (!preg_match('/^\d{3}$/', $this->texto($d, 'puntoEmision'))) {
                $err[] = "{$p}: puntoEmision debe tener 3 dígitos.";
            }
            if (!preg_match('/^\d{1,9}$/', $this->texto($d, 'secuencial'))) {
                $err[] = "{$p}: secuencial debe ser numérico (1 a 9 dígitos).";
            }
            $aut = $this->texto($d, 'autorizacion');
            if (mb_strlen($aut) < 3 || mb_strlen($aut) > 49) {
                $err[] = "{$p}: autorizacion debe tener entre 3 y 49 caracteres.";
            }

            // Bases y montos: formato 12 enteros, 2 decimales
            foreach (['baseNoGraIva','baseImponible','baseImpGrav','baseImpExe','montoIce','montoIva',
                      'valRetBien10','valRetServ20','valorRetBienes','valRetServ50','valorRetServicios','valRetServ100'] as $campo) {
                $v = $this->texto($d, $campo);
                if (!$this->montoValido($v)) {
                    $err[] = "{$p}: {$campo} con formato inválido (actual: '{$v}', se espera 0.00).";
                }
            }

            // Al menos una base mayor a 0
            $bNoGra = (float) $this->texto($d, 'baseNoGraIva');
            $b0     = (float) $this->texto($d, 'baseImponible');
            $bGrav  = (float) $this->texto($d, 'baseImpGrav');
            $bExe   = (float) $this->texto($d, 'baseImpExe');
            if ($bNoGra <= 0 && $b0 <= 0 && $bGrav <= 0 && $bExe <= 0) {
                $err[] = "{$p}: al menos una base (baseNoGraIva, baseImponible, baseImpGrav o baseImpExe) debe ser mayor a 0.00.";
            }

            // Coherencia suave: IVA cobrado sin base gravada
            if ((float) $this->texto($d, 'montoIva') > 0 && $bGrav <= 0) {
                $adv[] = "{$p}: montoIva mayor a 0 pero baseImpGrav es 0.00; verifique las tarifas.";
            }

            if ($this->hijo($d, 'pagoExterior') === null) {
                $err[] = "{$p}: falta el bloque <pagoExterior>.";
            }

            // air / detalleAir
            $air = $this->hijo($d, 'air');
            if ($air !== null) {
                $tieneLinea = false;
                foreach ($air->getElementsByTagName('detalleAir') as $da) {
                    $tieneLinea = true;
                    if ($this->texto($da, 'codRetAir') === '') {
                        $err[] = "{$p}: detalleAir sin codRetAir.";
                    }
                    if (!$this->montoValido($this->texto($da, 'baseImpAir'))) {
                        $err[] = "{$p}: detalleAir.baseImpAir con formato inválido.";
                    }
                    if (!$this->montoValido($this->texto($da, 'valRetAir'))) {
                        $err[] = "{$p}: detalleAir.valRetAir con formato inválido.";
                    }
                }
                if (!$tieneLinea) {
                    $err[] = "{$p}: <air> sin ninguna línea <detalleAir>.";
                }
            }

            // docModificado obligatorio en notas de crédito/débito
            if (in_array($tipoComp, ['04', '05'], true) && $this->hijo($d, 'docModificado') === null) {
                $err[] = "{$p}: tipoComprobante {$tipoComp} (N/C o N/D) requiere los campos de documento modificado.";
            }
        }
    }

    // ── XSD opcional ─────────────────────────────────────────────────────────

    private function validarConXsd(DOMDocument $dom, array &$err): void
    {
        $xsd = MVC_ROOT . '/storage/xsd/ats.xsd';
        if (!is_file($xsd)) {
            return;
        }
        libxml_clear_errors();
        if (!$dom->schemaValidate($xsd)) {
            foreach (libxml_get_errors() as $e) {
                $err[] = 'XSD: ' . trim($e->message);
            }
            libxml_clear_errors();
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function texto(DOMElement $parent, string $tag): string
    {
        foreach ($parent->childNodes as $c) {
            if ($c instanceof DOMElement && $c->nodeName === $tag) {
                return trim($c->textContent);
            }
        }
        return '';
    }

    private function hijo(DOMElement $parent, string $tag): ?DOMElement
    {
        foreach ($parent->childNodes as $c) {
            if ($c instanceof DOMElement && $c->nodeName === $tag) {
                return $c;
            }
        }
        return null;
    }

    private function montoValido(string $v): bool
    {
        return $v !== '' && (bool) preg_match('/^\d{1,12}\.\d{2}$/', $v);
    }

    private function fechaValida(string $v): bool
    {
        if (!preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
    }

    private function aTs(string $ddmmaaaa): int
    {
        [$d, $m, $a] = explode('/', $ddmmaaaa);
        return (int) mktime(0, 0, 0, (int) $m, (int) $d, (int) $a);
    }

    /** Dígito verificador de RUC/cédula (módulo 10/11 según tercer dígito). */
    private function rucValido(string $ruc): bool
    {
        if (!preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }
        $tercero = (int) $ruc[2];
        $cedula = substr($ruc, 0, 10);

        if ($tercero < 6) { // persona natural → validar cédula (módulo 10)
            return $this->cedulaValida($cedula);
        }
        if ($tercero === 6) { // público → módulo 11, 9 dígitos, verif. en pos 9
            return $this->modulo11($ruc, [3,2,7,6,5,4,3,2], 8);
        }
        if ($tercero === 9) { // jurídica/extranjero → módulo 11, verif. en pos 10
            return $this->modulo11($ruc, [4,3,2,7,6,5,4,3,2], 9);
        }
        return false;
    }

    private function cedulaValida(string $ced): bool
    {
        if (!preg_match('/^\d{10}$/', $ced)) {
            return false;
        }
        $coef = [2,1,2,1,2,1,2,1,2];
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $p = ((int) $ced[$i]) * $coef[$i];
            $suma += $p > 9 ? $p - 9 : $p;
        }
        $ver = (10 - ($suma % 10)) % 10;
        return $ver === (int) $ced[9];
    }

    private function modulo11(string $ruc, array $coef, int $posVerif): bool
    {
        $suma = 0;
        foreach ($coef as $i => $c) {
            $suma += ((int) $ruc[$i]) * $c;
        }
        $res = 11 - ($suma % 11);
        if ($res === 11) { $res = 0; }
        if ($res === 10) { return false; }
        return $res === (int) $ruc[$posVerif];
    }
}
