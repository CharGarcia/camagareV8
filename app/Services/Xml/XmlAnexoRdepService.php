<?php

declare(strict_types=1);

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;

/**
 * Serializa el Anexo RDEP (retenciones en la fuente bajo relación de
 * dependencia) con la estructura exacta del esquema oficial rdep.xsd (2023):
 *
 *   <rdep>
 *     <numRuc/> <anio/> <tipoEmpleador/> <enteSegSocial/>
 *     <retRelDep>
 *       <datRetRelDep>
 *         <empleado> … 15 campos … </empleado>
 *         … 11 ingresos, 13 gastos, 7 de resumen …
 *       </datRetRelDep>
 *     </retRelDep>
 *   </rdep>
 *
 * Es un serializador "tonto", como XmlAnexoDividendosService: recibe los
 * valores ya normalizados (cadenas con el formato del SRI) y los escribe en el
 * orden que exige el esquema. No calcula ni valida nada; eso lo hacen
 * AnexoRdepCalculoService y AnexoRdepRules. El archivo va en ISO-8859-1, como
 * el ejemplo publicado por el SRI.
 */
class XmlAnexoRdepService
{
    public const ENCODING = 'ISO-8859-1';

    /** Campos opcionales del esquema que se omiten (deducciones de esquemas viejos y la contribución 2016). */
    private const OMITIR = ['deducEduca', 'deducArtycult', 'contribucion'];

    /**
     * @param array $cab   num_ruc, anio, tipo_empleador, ente_seg_social
     * @param array $filas cada una con las claves de anexo_rdep_detalle (ya calculadas)
     */
    public function generar(array $cab, array $filas): string
    {
        $dom = new DOMDocument('1.0', self::ENCODING);
        $dom->formatOutput  = true;
        $dom->xmlStandalone = true;

        $raiz = $dom->createElement('rdep');
        $dom->appendChild($raiz);

        $this->add($dom, $raiz, 'numRuc', $cab['num_ruc']);
        $this->add($dom, $raiz, 'anio', (string) (int) $cab['anio']);
        $this->add($dom, $raiz, 'tipoEmpleador', $cab['tipo_empleador']);
        $this->add($dom, $raiz, 'enteSegSocial', $cab['ente_seg_social']);

        $ret = $dom->createElement('retRelDep');
        $raiz->appendChild($ret);
        foreach ($filas as $f) {
            $ret->appendChild($this->construirTrabajador($dom, $f));
        }

        return (string) $dom->saveXML();
    }

    private function construirTrabajador(DOMDocument $dom, array $f): DOMElement
    {
        $d = $dom->createElement('datRetRelDep');

        $e = $dom->createElement('empleado');
        $this->add($dom, $e, 'benGalpg', $f['ben_galapagos']);
        $this->add($dom, $e, 'enfcatastro', $f['enf_catastro']);
        $this->add($dom, $e, 'numCargRebGastPers', (string) (int) $f['num_cargas']);
        $this->add($dom, $e, 'tipIdRet', $f['tip_id_ret']);
        $this->add($dom, $e, 'idRet', $f['id_ret']);
        $this->add($dom, $e, 'apellidoTrab', $f['apellidos']);
        $this->add($dom, $e, 'nombreTrab', $f['nombres']);
        $this->add($dom, $e, 'estab', $f['estab']);
        $this->add($dom, $e, 'residenciaTrab', $f['residencia']);
        $this->add($dom, $e, 'paisResidencia', $f['pais_residencia']);
        $this->add($dom, $e, 'aplicaConvenio', $f['aplica_convenio']);
        $this->add($dom, $e, 'tipoTrabajDiscap', $f['tipo_discap']);
        $this->add($dom, $e, 'porcentajeDiscap', (string) (int) $f['porcentaje_discap']);
        $this->add($dom, $e, 'tipIdDiscap', $f['tip_id_discap']);
        $this->add($dom, $e, 'idDiscap', $f['id_discap']);
        $d->appendChild($e);

        // Ingresos
        $this->money($dom, $d, 'suelSal', $f['suel_sal']);
        $this->money($dom, $d, 'sobSuelComRemu', $f['sob_suel']);
        $this->money($dom, $d, 'partUtil', $f['part_util']);
        $this->money($dom, $d, 'intGrabGen', $f['int_grab_gen']);
        $this->money($dom, $d, 'impRentEmpl', $f['imp_rent_empl']);
        $this->money($dom, $d, 'decimTer', $f['decim_ter']);
        $this->money($dom, $d, 'decimCuar', $f['decim_cuar']);
        $this->money($dom, $d, 'fondoReserva', $f['fondo_reserva']);
        $this->money($dom, $d, 'salarioDigno', $f['salario_digno']);
        $this->money($dom, $d, 'otrosIngRenGrav', $f['otros_ing_no_grav']);
        $this->money($dom, $d, 'ingGravConEsteEmpl', $f['ing_grav_este_empl']);

        // Gastos, deducciones y exoneraciones
        $this->add($dom, $d, 'sisSalNet', (string) (int) $f['sis_sal_net']);
        $this->money($dom, $d, 'apoPerIess', $f['apo_per_iess']);
        $this->money($dom, $d, 'aporPerIessConOtrosEmpls', $f['apor_per_iess_otros']);
        $this->money($dom, $d, 'deducVivienda', $f['deduc_vivienda']);
        $this->money($dom, $d, 'deducSalud', $f['deduc_salud']);
        $this->money($dom, $d, 'deducEducartcult', $f['deduc_educ']);
        $this->money($dom, $d, 'deducAliement', $f['deduc_aliment']);
        $this->money($dom, $d, 'deducVestim', $f['deduc_vestim']);
        $this->money($dom, $d, 'deduccionTurismo', $f['deduc_turismo']);
        $this->money($dom, $d, 'exoDiscap', $f['exo_discap']);
        $this->money($dom, $d, 'exoTerEd', $f['exo_ter_ed']);

        // Resumen impositivo
        $this->money($dom, $d, 'basImp', $f['bas_imp']);
        $this->money($dom, $d, 'impRentCaus', $f['imp_rent_caus']);
        $this->money($dom, $d, 'rebajaGastosPersonales', $f['rebaja_gastos']);
        $this->money($dom, $d, 'impuestoRentaRebajaGastosPersonales', $f['imp_rent_rebaja']);
        $this->money($dom, $d, 'valRetAsuOtrosEmpls', $f['val_ret_otros']);
        $this->money($dom, $d, 'valImpAsuEsteEmpl', $f['val_imp_asu_este']);
        $this->money($dom, $d, 'valRet', $f['val_ret']);

        return $d;
    }

    private function money(DOMDocument $dom, DOMElement $padre, string $nombre, $valor): void
    {
        $this->add($dom, $padre, $nombre, number_format(max(0.0, (float) $valor), 2, '.', ''));
    }

    private function add(DOMDocument $dom, DOMElement $padre, string $nombre, $valor): void
    {
        $padre->appendChild($dom->createElement($nombre, $this->escapar((string) $valor)));
    }

    /** Sin caracteres de control; DOMDocument escapa &, < y >. */
    private function escapar(string $valor): string
    {
        $valor = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor);
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Esquema oficial, incluido en el repositorio. */
    public static function rutaXsd(): string
    {
        return __DIR__ . '/xsd/rdep.xsd';
    }

    /**
     * Valida contra el esquema oficial.
     * @return string[] Mensajes de error; vacío si valida o si no hay esquema.
     */
    public function validarContraXsd(string $xml, ?string $rutaXsd = null): array
    {
        $rutaXsd = $rutaXsd ?? self::rutaXsd();
        if (!is_file($rutaXsd)) {
            return [];
        }
        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $valido = $dom->schemaValidate($rutaXsd);

        $errores = [];
        if (!$valido) {
            foreach (libxml_get_errors() as $error) {
                $errores[] = trim($error->message) . ' (línea ' . $error->line . ')';
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        return $errores;
    }
}
