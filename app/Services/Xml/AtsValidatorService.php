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

    /** Tipos de proveedor del ATS cuando la identificación es pasaporte: 01 natural, 02 sociedad. */
    private const TIPO_PROV = ['01','02'];

    /**
     * Catálogo `código de sustento → tipos de comprobante permitidos` de la tabla
     * `sustento_tributario`, inyectado por AtsService. Vacío = no se comprueba la
     * combinación (el resto de reglas sigue aplicándose igual).
     *
     * @var array<string, string[]>
     */
    private array $sustentoTipos = [];

    /**
     * @param array<string, string[]> $sustentoTipos Ver AtsRepository::getSustentosPermitidos()
     * @return array{errores: string[], advertencias: string[]}
     */
    public function validar(string $xml, array $sustentoTipos = []): array
    {
        $this->sustentoTipos = $sustentoTipos;

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
        $this->validarVentas($dom, $errores);
        $this->validarAnulados($dom, $errores);
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
        }
        // No se valida el dígito verificador: los RUC nuevos emitidos por el SRI
        // ya no cumplen el algoritmo módulo 10/11 y rechazarían informantes válidos.

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
            // Identificar la compra como lo hace el mensaje del SRI (serie +
            // proveedor + tipo), no por su posición en el archivo: así el usuario
            // sabe qué documento abrir en el módulo Compras.
            $idProv   = $this->texto($d, 'idProv');
            $tipoComp = $this->texto($d, 'tipoComprobante');
            $serie    = $this->texto($d, 'establecimiento') . '-'
                      . $this->texto($d, 'puntoEmision') . '-'
                      . $this->texto($d, 'secuencial');
            $p = "Compra {$serie} (prov. {$idProv}, tipo {$tipoComp})";

            $cod = $this->texto($d, 'codSustento');
            if (!in_array($cod, self::SUSTENTOS, true)) {
                $err[] = "{$p}: codSustento '{$cod}' no es válido (Tabla 5).";
            } elseif ($this->sustentoTipos !== []
                && isset($this->sustentoTipos[$cod])
                && $tipoComp !== ''
                && !in_array($tipoComp, $this->sustentoTipos[$cod], true)) {
                $err[] = "{$p}: el código de sustento tributario {$cod} no está permitido para comprobantes "
                       . "tipo {$tipoComp}. Corrija el sustento del documento en el módulo Compras.";
            }

            $tp = $this->texto($d, 'tpIdProv');
            if (!in_array($tp, self::TP_ID_PROV, true)) {
                $err[] = "{$p}: tpIdProv debe ser 01, 02 o 03 (actual: '{$tp}').";
            }

            if ($tp === '01' && !preg_match('/^\d{13}$/', $idProv)) {
                $err[] = "{$p}: idProv (RUC) debe tener 13 dígitos.";
            } elseif ($tp === '02' && !preg_match('/^\d{10}$/', $idProv)) {
                $err[] = "{$p}: idProv (Cédula) debe tener 10 dígitos.";
            } elseif ($idProv === '') {
                $err[] = "{$p}: idProv es obligatorio.";
            }

            // Proveedor con pasaporte / identificación del exterior: el SRI exige
            // tipo de proveedor y razón o denominación social (obligatoria desde
            // mayo de 2016), sea cual sea el tipo de comprobante.
            if ($tp === '03') {
                $tipoProv = $this->texto($d, 'tipoProv');
                if (!in_array($tipoProv, self::TIPO_PROV, true)) {
                    $err[] = "{$p}: falta el tipo de proveedor (tipoProv 01 persona natural / 02 sociedad), "
                           . "obligatorio cuando el proveedor se identifica con pasaporte. Complete el tipo "
                           . "de empresa en la ficha del proveedor.";
                }
                if ($this->texto($d, 'denoProv') === '') {
                    $err[] = "{$p}: falta la razón o denominación social del proveedor (denoProv), "
                           . "obligatoria desde mayo de 2016 para proveedores con pasaporte.";
                }
            }

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

            $this->validarPagoExterior($d, $p, $tipoComp, $err, $adv);

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

    /**
     * Bloque <pagoExterior>. Con pago local (01) los otros tres campos van en
     * "NA"; con pago al exterior (02) el SRI exige el país y las dos respuestas
     * SI/NO. Un comprobante emitido en el exterior (tipo 15) declarado como pago
     * local se avisa, pero no se marca como error: quien decide es el contador.
     */
    private function validarPagoExterior(DOMElement $d, string $p, string $tipoComp, array &$err, array &$adv): void
    {
        $pe = $this->hijo($d, 'pagoExterior');
        if ($pe === null) {
            $err[] = "{$p}: falta el bloque <pagoExterior>.";
            return;
        }

        $tipoPago = $this->texto($pe, 'pagoLocExt');
        if (!in_array($tipoPago, ['01', '02'], true)) {
            $err[] = "{$p}: pagoLocExt debe ser 01 (pago local) o 02 (pago al exterior), actual: '{$tipoPago}'.";
            return;
        }

        if ($tipoPago === '01') {
            if ($tipoComp === '15') {
                $adv[] = "{$p}: es un comprobante emitido en el exterior declarado como pago local. "
                       . "Revise la pestaña \"ATS\" del documento en Compras.";
            }
            return;
        }

        $pais = $this->texto($pe, 'paisEfecPago');
        if ($pais === '' || $pais === 'NA') {
            $err[] = "{$p}: en un pago al exterior hay que indicar el país donde se efectuó el pago.";
        }
        foreach (['aplicConvDobTrib' => 'el convenio de doble tributación',
                  'pagExtSujRetNorLeg' => 'la sujeción a retención según la norma legal'] as $campo => $texto) {
            if (!in_array($this->texto($pe, $campo), ['SI', 'NO'], true)) {
                $err[] = "{$p}: en un pago al exterior hay que indicar {$texto} (SI o NO).";
            }
        }
    }

    // ── Ventas ───────────────────────────────────────────────────────────────

    private function validarVentas(DOMDocument $dom, array &$err): void
    {
        $i = 0;
        foreach ($dom->getElementsByTagName('detalleVentas') as $d) {
            $i++;
            if (!$d instanceof DOMElement) {
                continue;
            }
            $tp    = $this->texto($d, 'tpIdCliente');
            $idCli = $this->texto($d, 'idCliente');
            $p = "Venta a {$idCli} (tipo id. {$tp}, comprobante " . $this->texto($d, 'tipoComprobante') . ')';

            if (!in_array($tp, ['04', '05', '06', '07'], true)) {
                $err[] = "{$p}: el tipo de identificación del cliente '{$tp}' no es válido en Ventas; "
                       . "el ATS solo admite 04 (RUC), 05 (cédula), 06 (pasaporte) y 07 (consumidor final). "
                       . "Corrija el tipo de identificación en la ficha del cliente.";
            }
            if ($idCli === '') {
                $err[] = "{$p}: idCliente es obligatorio.";
            }
            // Cliente con pasaporte / identificación del exterior: el SRI exige tipo
            // de cliente y su denominación.
            if ($tp === '06') {
                if (!in_array($this->texto($d, 'tipoCliente'), self::TIPO_PROV, true)) {
                    $err[] = "{$p}: falta el tipo de cliente (01 persona natural / 02 sociedad), "
                           . "obligatorio cuando el cliente se identifica con pasaporte.";
                }
                if ($this->texto($d, 'denoCli') === '') {
                    $err[] = "{$p}: falta la razón o denominación social del cliente (denoCli).";
                }
            }
            if (!in_array($this->texto($d, 'tipoEmision'), ['E', 'F'], true)) {
                $err[] = "{$p}: tipoEmision debe ser E (electrónica) o F (física).";
            }
            if (!preg_match('/^\d{1,12}$/', $this->texto($d, 'numeroComprobantes'))) {
                $err[] = "{$p}: numeroComprobantes debe ser numérico.";
            }
            foreach (['baseNoGraIva','baseImponible','baseImpGrav','montoIva','montoIce','valorRetIva','valorRetRenta'] as $campo) {
                $v = $this->texto($d, $campo);
                if (!$this->montoValido($v)) {
                    $err[] = "{$p}: {$campo} con formato inválido (actual: '{$v}').";
                }
            }
            if ((float) $this->texto($d, 'baseNoGraIva') <= 0
                && (float) $this->texto($d, 'baseImponible') <= 0
                && (float) $this->texto($d, 'baseImpGrav') <= 0) {
                $err[] = "{$p}: al menos una base debe ser mayor a 0.00.";
            }
        }
    }

    // ── Anulados ─────────────────────────────────────────────────────────────

    private function validarAnulados(DOMDocument $dom, array &$err): void
    {
        $i = 0;
        foreach ($dom->getElementsByTagName('detalleAnulados') as $d) {
            $i++;
            if (!$d instanceof DOMElement) {
                continue;
            }
            $p = "Anulado #{$i}";

            if (!preg_match('/^\d{2,3}$/', $this->texto($d, 'tipoComprobante'))) {
                $err[] = "{$p}: tipoComprobante inválido.";
            }
            if (!preg_match('/^\d{3}$/', $this->texto($d, 'establecimiento'))) {
                $err[] = "{$p}: establecimiento debe tener 3 dígitos.";
            }
            if (!preg_match('/^\d{3}$/', $this->texto($d, 'puntoEmision'))) {
                $err[] = "{$p}: puntoEmision debe tener 3 dígitos.";
            }
            if (!preg_match('/^\d{1,9}$/', $this->texto($d, 'secuencialInicio'))) {
                $err[] = "{$p}: secuencialInicio debe ser numérico (1 a 9 dígitos).";
            }
            if (!preg_match('/^\d{1,9}$/', $this->texto($d, 'secuencialFin'))) {
                $err[] = "{$p}: secuencialFin debe ser numérico (1 a 9 dígitos).";
            }
            $aut = $this->texto($d, 'autorizacion');
            if (mb_strlen($aut) < 3 || mb_strlen($aut) > 49) {
                $err[] = "{$p}: autorizacion debe tener entre 3 y 49 caracteres.";
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
}
