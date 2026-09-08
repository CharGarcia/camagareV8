<?php

declare(strict_types=1);

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;

/**
 * Genera el XML del Anexo de Dividendos (ADI) del SRI.
 *
 * Igual que XmlAtsService, es un serializador "tonto": recibe los datos ya
 * normalizados por AnexoDividendosService (todo en cadenas, con el formato
 * exacto del SRI) y los escribe respetando el orden de campos de la ficha
 * técnica. No calcula ni valida nada.
 *
 * ─── ESTRUCTURA DEL DOCUMENTO ────────────────────────────────────────────────
 * El catálogo del SRI publica los NOMBRES de cada campo pero no la jerarquía que
 * los contiene, así que los nodos CONTENEDORES viven en constantes, arriba.
 *
 * La cabecera y la sección B están calcadas de un archivo generado con el DIMM
 * Anexos del SRI, así que su orden y sus nombres son los definitivos. La sección
 * C (dividendos) sigue deducida: falta contrastarla con un ejemplo del DIMM que
 * tenga al menos un dividendo distribuido.
 *
 * Los nombres de los campos hoja SÍ provienen del catálogo oficial y no deben
 * cambiarse.
 *
 * Alcance: secciones A (informante), B (utilidades) y C (dividendos
 * distribuidos), esquema 2020 en adelante. Las secciones D (préstamos a
 * accionistas), E (dividendos anticipados) y F (dividendos del exterior) no se
 * emiten.
 */
class XmlAnexoDividendosService
{
    // ── Nodos contenedores (ajustar contra el XSD oficial) ───────────────────
    public const NODO_RAIZ = 'adi';

    /**
     * Contenedor de la sección B, confirmado con un archivo generado por el
     * DIMM Anexos del SRI. Vacío haría que los ocho campos colgaran de la raíz.
     */
    public const NODO_UTILIDADES = 'informacionUtilidad';

    public const NODO_DIVIDENDOS     = 'dividendos';
    public const NODO_DIVIDENDO      = 'detalleDividendo';
    public const NODO_DISTRIBUCIONES = 'detalleDistribucion';
    public const NODO_DISTRIBUCION   = 'distribucion';

    /**
     * Cómo se escriben los campos SI/NO. La ficha define la tabla 4 con códigos
     * 01/02, pero la columna "Formato" de esos mismos campos dice "SI / NO".
     * Con true se emite el texto (SI/NO); con false, el código (01/02).
     */
    public const RESPUESTA_COMO_TEXTO = true;

    /**
     * Mes de la cabecera. El ADI es anual y la ficha no menciona este campo,
     * pero el esquema lo exige tras el año y el DIMM del SRI lo emite en '00'.
     */
    public const MES_CABECERA = '00';

    /**
     * Código operativo, último campo de la cabecera: el ATS usa 'IVA' y el DIMM
     * emite 'ADI' para este anexo.
     */
    public const CODIGO_OPERATIVO = 'ADI';

    /**
     * Genera el contenido del archivo ADI-aaaa.xml.
     *
     * @param array $informante  anio, tipo_informante, tipo_id_informante, id_informante, razon_social
     * @param array $utilidades  los ocho campos de la sección B, ya formateados como cadena
     * @param array $dividendos  un elemento por beneficiario, cada uno con la clave
     *                           'distribuciones' conteniendo su detalle C.2
     */
    public function generar(array $informante, array $utilidades, array $dividendos): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput  = true;
        $dom->xmlStandalone = false;

        $raiz = $dom->createElement(self::NODO_RAIZ);
        $dom->appendChild($raiz);

        $this->construirInformante($dom, $raiz, $informante);

        if ($utilidades !== []) {
            $this->construirUtilidades($dom, $raiz, $utilidades);
        }

        if ($dividendos !== []) {
            $nodo = $dom->createElement(self::NODO_DIVIDENDOS);
            foreach ($dividendos as $beneficiario) {
                $nodo->appendChild($this->construirBeneficiario($dom, $beneficiario));
            }
            $raiz->appendChild($nodo);
        }

        return (string) $dom->saveXML();
    }

    // ── A. Período informado y datos del informante ──────────────────────────

    /**
     * Orden que exige el esquema del SRI, distinto al de la ficha técnica y
     * verificado contra un archivo del DIMM: la identificación del informante va
     * primero, luego el período, la razón social y el código del anexo.
     *
     * Ojo con las mayúsculas: el esquema distingue 'TipoIdInformante' e
     * 'IdInformante' de los demás campos, que llevan minúscula inicial.
     */
    private function construirInformante(DOMDocument $dom, DOMElement $raiz, array $inf): void
    {
        $this->add($dom, $raiz, 'TipoIdInformante', $inf['tipo_id_informante']);
        $this->add($dom, $raiz, 'IdInformante', $inf['id_informante']);
        $this->add($dom, $raiz, 'Anio', $inf['anio']);
        $this->add($dom, $raiz, 'Mes', self::MES_CABECERA);
        $this->add($dom, $raiz, 'razonSocial', $inf['razon_social']);
        $this->add($dom, $raiz, 'tipoInformante', $inf['tipo_informante']);
        $this->add($dom, $raiz, 'codigoOperativo', self::CODIGO_OPERATIVO);
    }

    // ── B. Información de utilidades ─────────────────────────────────────────

    /**
     * Escribe la sección B dentro de <informacionUtilidad>. Si NODO_UTILIDADES
     * quedara vacío, los campos colgarían directamente de la raíz.
     */
    private function construirUtilidades(DOMDocument $dom, DOMElement $raiz, array $u): void
    {
        $destino = self::NODO_UTILIDADES !== ''
            ? $raiz->appendChild($dom->createElement(self::NODO_UTILIDADES))
            : $raiz;

        $this->add($dom, $destino, 'utilidadEjercicioInformado', $u['utilidad_ejercicio']);
        $this->add($dom, $destino, 'utilidadDistribuidaDistintaReinv', $u['utilidad_distribuida_distinta_reinv']);
        $this->add($dom, $destino, 'utilidadReinvertidaConDerechoReduccion', $u['utilidad_reinvertida_con_derecho']);
        $this->add($dom, $destino, 'utilidadReinvertidaSinDerechoReduccion', $u['utilidad_reinvertida_sin_derecho']);
        $this->add($dom, $destino, 'utilidadPagadaAnticipado', $u['utilidad_pagada_anticipado']);
        $this->add($dom, $destino, 'utilidadNoDistribuidaEjerInfor', $u['utilidad_no_distribuida']);
        $this->add($dom, $destino, 'utilidadNoDistribEjerAntPeriodoInfor', $u['utilidad_no_distrib_ejer_ant']);
        $this->add($dom, $destino, 'utilidadDistribEjerciciosAnteriores', $u['utilidad_distrib_ejercicios_ant']);
    }

    // ── C.1. Datos del beneficiario del dividendo distribuido ────────────────

    private function construirBeneficiario(DOMDocument $dom, array $b): DOMElement
    {
        $n = $dom->createElement(self::NODO_DIVIDENDO);

        $this->add($dom, $n, 'secuencialDD', $b['secuencial']);
        $this->add($dom, $n, 'tipoIdPerceptor', $b['tipo_id_perceptor']);
        $this->add($dom, $n, 'numeroIdPerceptor', $b['numero_id_perceptor']);
        $this->add($dom, $n, 'tipoBeneficiarioDD', $b['tipo_beneficiario']);
        $this->add($dom, $n, 'paisResidenciaDD', $b['pais_residencia']);

        // Campos condicionales: solo se emiten cuando la ficha los habilita, para
        // no enviar etiquetas vacías que el portal rechaza.
        if (($b['regimen_fiscal_preferente'] ?? '') !== '') {
            $this->add($dom, $n, 'regimenFiscalPreferenteDD', $b['regimen_fiscal_preferente']);
        }
        if (($b['tipo_id_beneficiario_efectivo'] ?? '') !== '') {
            $this->add($dom, $n, 'tipoIdBeneficiarioEfectivo', $b['tipo_id_beneficiario_efectivo']);
            $this->add($dom, $n, 'numeroIdBeneficiarioEfectivo', $b['numero_id_beneficiario_efectivo']);
        }

        $distribuciones = $dom->createElement(self::NODO_DISTRIBUCIONES);
        foreach ($b['distribuciones'] as $d) {
            $distribuciones->appendChild($this->construirDistribucion($dom, $d));
        }
        $n->appendChild($distribuciones);

        return $n;
    }

    // ── C.2. Detalle de la distribución ──────────────────────────────────────

    private function construirDistribucion(DOMDocument $dom, array $d): DOMElement
    {
        $n = $dom->createElement(self::NODO_DISTRIBUCION);

        $this->add($dom, $n, 'anioGeneraUtilidadAtribuibles', $d['anio_genera_utilidad']);
        $this->add($dom, $n, 'tipoDividendoDistribuido', $d['tipo_dividendo']);
        $this->add($dom, $n, 'fechaRegistroContable', $d['fecha_registro_contable']);
        $this->add($dom, $n, 'montoDividendoDistribuido', $d['monto_dividendo_distribuido']);
        $this->add($dom, $n, 'ingresoGravadoDividendos', $d['ingreso_gravado']);
        $this->add($dom, $n, 'MontoRetencion', $d['monto_retencion']);
        $this->add($dom, $n, 'dividendopagdo', $d['dividendo_pagado']);
        $this->add($dom, $n, 'midPagaDividendo', $d['isd_pagado']);

        return $n;
    }

    // ── Utilidades del serializador ──────────────────────────────────────────

    private function add(DOMDocument $dom, DOMElement $padre, string $nombre, $valor): void
    {
        $padre->appendChild($dom->createElement($nombre, $this->escapar((string) $valor)));
    }

    /**
     * El SRI no acepta los caracteres reservados de XML sin escapar ni
     * caracteres de control; DOMDocument escapa &, < y >, pero no limpia los de
     * control, que sí rompen la carga.
     */
    private function escapar(string $valor): string
    {
        $valor = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor);
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valida el XML generado contra el esquema oficial, si está disponible.
     * El archivo .xsd se descarga desde SRI en Línea (Anexos → Anexo de
     * Dividendos) y se deja en la ruta indicada.
     *
     * @return string[] Mensajes de error; vacío si valida o si no hay esquema.
     */
    public function validarContraXsd(string $xml, string $rutaXsd): array
    {
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
