<?php

declare(strict_types=1);

namespace App\Services\Sri;

/**
 * Lee el emisor y el número de serie DIRECTAMENTE del DER del certificado X.509.
 *
 * ¿Por qué no usar openssl_x509_parse()? Porque el validador del SRI está escrito
 * en Java y deriva el IssuerName con
 * `X509Certificate::getIssuerX500Principal()->getName(RFC2253)`, que trabaja sobre
 * los bytes DER originales. Reconstruir ese nombre desde el array de PHP pierde
 * dos datos que Java sí usa y produce "FIRMA INVÁLIDA — La información sobre el
 * certificado de firma no se ajusta a XAdES":
 *
 *   1. El OID real. Para atributos que PHP no reconoce, openssl_x509_parse()
 *      devuelve la clave literal "UNDEF" en lugar del OID, y varios atributos
 *      desconocidos colapsan bajo esa misma clave.
 *   2. El tipo ASN.1 real del valor. Java emite los atributos de OID desconocido
 *      como "oid=#<hexDER>" copiando el DER tal cual; si el certificado codifica
 *      el valor como PrintableString (0x13) y nosotros asumimos UTF8String (0x0c),
 *      el hex difiere en un byte y el SRI rechaza la firma. Caso real: emisor
 *      "ANF High Assurance Ecuador Intermediate CA", cuyo 2.5.4.5 es PrintableString.
 *
 * Esta clase reproduce el algoritmo de Java sobre el DER, así que ambos datos
 * salen correctos para cualquier CA (ANF, UANATACA, Security Data, BCE, etc.).
 *
 * @see FirmadorXmlService::firmar()
 */
class X509DerReader
{
    /**
     * Únicos keywords que java.security.cert.X500Principal reconoce en modo
     * RFC2253. Cualquier otro OID se emite como "oid=#<hexDER>".
     */
    private const KEYWORDS = [
        '2.5.4.3'                    => 'CN',
        '2.5.4.7'                    => 'L',
        '2.5.4.8'                    => 'ST',
        '2.5.4.10'                   => 'O',
        '2.5.4.11'                   => 'OU',
        '2.5.4.6'                    => 'C',
        '2.5.4.9'                    => 'STREET',
        '0.9.2342.19200300.100.1.25' => 'DC',
        '0.9.2342.19200300.100.1.1'  => 'UID',
    ];

    private string $issuerRaw;
    private string $subjectRaw;
    private string $serialRaw;

    /**
     * @param  string $der Bytes DER del certificado (no PEM).
     * @throws \RuntimeException Si la estructura no es un certificado X.509 válido.
     */
    public function __construct(string $der)
    {
        try {
            $cert = $this->leerNodo($der, 0);              // SEQUENCE Certificate
            $tbs  = $this->leerNodo($cert['content'], 0);  // SEQUENCE tbsCertificate
            $body = $tbs['content'];

            $off  = 0;
            $nodo = $this->leerNodo($body, $off);
            if ($nodo['tag'] === 0xA0) {                   // [0] EXPLICIT version (opcional)
                $off  = $nodo['end'];
                $nodo = $this->leerNodo($body, $off);
            }
            $this->serialRaw = $nodo['content'];           // INTEGER serialNumber
            $off = $nodo['end'];

            $nodo = $this->leerNodo($body, $off);          // AlgorithmIdentifier
            $off  = $nodo['end'];

            $nodo = $this->leerNodo($body, $off);          // Name issuer
            $this->issuerRaw = $nodo['raw'];
            $off  = $nodo['end'];

            $nodo = $this->leerNodo($body, $off);          // Validity
            $off  = $nodo['end'];

            $nodo = $this->leerNodo($body, $off);          // Name subject
            $this->subjectRaw = $nodo['raw'];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'No se pudo interpretar la estructura DER del certificado: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /** Construye el lector a partir de un certificado abierto por OpenSSL. */
    public static function desdeCertificado($certificate): self
    {
        $pem = '';
        if (!openssl_x509_export($certificate, $pem)) {
            throw new \RuntimeException('No se pudo exportar el certificado: ' . openssl_error_string());
        }
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $pem) ?? '', true);
        if ($der === false || $der === '') {
            throw new \RuntimeException('El certificado exportado no contiene bytes DER válidos.');
        }
        return new self($der);
    }

    /** DN del emisor en formato RFC2253, idéntico al que calcula Java. */
    public function issuerRfc2253(): string
    {
        return $this->nombreARfc2253($this->issuerRaw);
    }

    /** DN del sujeto en formato RFC2253 (útil para diagnóstico). */
    public function subjectRfc2253(): string
    {
        return $this->nombreARfc2253($this->subjectRaw);
    }

    /**
     * Número de serie en decimal con la semántica de java.math.BigInteger:
     * el INTEGER de ASN.1 lleva signo (complemento a dos).
     */
    public function serialDecimal(): string
    {
        $bytes = $this->serialRaw;
        if ($bytes === '') {
            return '0';
        }
        if ((ord($bytes[0]) & 0x80) === 0) {
            return $this->hexADecimal(bin2hex($bytes));
        }
        // Negativo: invertir bits y sumar uno para obtener la magnitud.
        $invertido = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $invertido .= chr(~ord($bytes[$i]) & 0xFF);
        }
        return '-' . $this->sumarUno($this->hexADecimal(bin2hex($invertido)));
    }

    // ── RFC2253 al estilo java.security.cert.X500Principal ────────────────────

    private function nombreARfc2253(string $nameRaw): string
    {
        $seq  = $this->leerNodo($nameRaw, 0);              // SEQUENCE OF RelativeDistinguishedName
        $rdns = [];
        $off  = 0;
        while ($off < strlen($seq['content'])) {
            $set = $this->leerNodo($seq['content'], $off); // SET OF AttributeTypeAndValue
            $off = $set['end'];

            $atributos = [];
            $offAtv = 0;
            while ($offAtv < strlen($set['content'])) {
                $atv    = $this->leerNodo($set['content'], $offAtv);
                $offAtv = $atv['end'];
                $atributos[] = $this->atributoARfc2253($atv['content']);
            }
            // Varios atributos en un mismo RDN se unen con "+".
            $rdns[] = implode('+', $atributos);
        }
        // Java emite los RDN del más específico al más general (orden DER invertido).
        return implode(',', array_reverse($rdns));
    }

    private function atributoARfc2253(string $atvContent): string
    {
        $nodoOid = $this->leerNodo($atvContent, 0);
        $oid     = $this->decodificarOid($nodoOid['content']);
        $nodoVal = $this->leerNodo($atvContent, $nodoOid['end']);

        // OID no reconocido por Java, o valor que no es un tipo string:
        // se emite el DER completo del valor en hexadecimal.
        if (!isset(self::KEYWORDS[$oid])) {
            return $oid . '=#' . bin2hex($nodoVal['raw']);
        }
        $texto = $this->decodificarTexto($nodoVal['tag'], $nodoVal['content']);
        if ($texto === null) {
            return $oid . '=#' . bin2hex($nodoVal['raw']);
        }
        return self::KEYWORDS[$oid] . '=' . $this->escaparRfc2253($texto);
    }

    /** Devuelve null si el tag no es un tipo de cadena reconocido. */
    private function decodificarTexto(int $tag, string $contenido): ?string
    {
        switch ($tag) {
            case 0x0C: // UTF8String
            case 0x13: // PrintableString
            case 0x16: // IA5String
            case 0x14: // TeletexString / T61String
            case 0x12: // NumericString
            case 0x15: // VideotexString
                return $contenido;
            case 0x1E: // BMPString (UTF-16BE)
                return mb_convert_encoding($contenido, 'UTF-8', 'UTF-16BE');
            case 0x1C: // UniversalString (UTF-32BE)
                return mb_convert_encoding($contenido, 'UTF-8', 'UTF-32BE');
            default:
                return null;
        }
    }

    private function escaparRfc2253(string $valor): string
    {
        $valor = str_replace(
            ['\\', ',', '+', '"', '<', '>', ';'],
            ['\\\\', '\\,', '\\+', '\\"', '\\<', '\\>', '\\;'],
            $valor
        );
        if ($valor !== '' && ($valor[0] === '#' || $valor[0] === ' ')) {
            $valor = '\\' . $valor;
        }
        if ($valor !== '' && substr($valor, -1) === ' ') {
            $valor = substr($valor, 0, -1) . '\\ ';
        }
        return $valor;
    }

    // ── Lectura ASN.1 DER ─────────────────────────────────────────────────────

    /**
     * Lee un nodo TLV a partir de un desplazamiento.
     *
     * @return array{tag:int, content:string, raw:string, end:int}
     */
    private function leerNodo(string $buffer, int $offset): array
    {
        if (!isset($buffer[$offset], $buffer[$offset + 1])) {
            throw new \RuntimeException('DER truncado en el desplazamiento ' . $offset);
        }
        $tag = ord($buffer[$offset]);
        $pos = $offset + 1;
        $len = ord($buffer[$pos]);
        $pos++;

        if ($len & 0x80) {
            $numBytes = $len & 0x7F;
            $len = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                if (!isset($buffer[$pos])) {
                    throw new \RuntimeException('Longitud DER truncada.');
                }
                $len = ($len << 8) | ord($buffer[$pos]);
                $pos++;
            }
        }

        return [
            'tag'     => $tag,
            'content' => substr($buffer, $pos, $len),
            'raw'     => substr($buffer, $offset, ($pos - $offset) + $len),
            'end'     => $pos + $len,
        ];
    }

    private function decodificarOid(string $contenido): string
    {
        $bytes = array_values(unpack('C*', $contenido));
        $arcos = [intdiv($bytes[0], 40), $bytes[0] % 40];
        $valor = 0;
        for ($i = 1; $i < count($bytes); $i++) {
            $valor = ($valor << 7) | ($bytes[$i] & 0x7F);
            if (!($bytes[$i] & 0x80)) {
                $arcos[] = $valor;
                $valor = 0;
            }
        }
        return implode('.', $arcos);
    }

    // ── Aritmética de enteros grandes ─────────────────────────────────────────

    /**
     * Convierte un hexadecimal de longitud arbitraria a decimal.
     *
     * La aritmética es decimal sobre cadenas, a propósito: NO se usan gmp ni
     * bcmath. Los números de serie de los certificados llegan a 20 bytes, muy por
     * encima de PHP_INT_MAX, y en un servidor sin esas extensiones `hexdec()`
     * devuelve un float que al pasarlo a cadena queda como "9.9834935390145E+26".
     * Ese valor acaba en ds:X509SerialNumber y el SRI responde "La información
     * sobre el certificado de firma no se ajusta a XAdES". El servidor de
     * producción no tiene ninguna de las dos extensiones, así que depender de
     * ellas no es una opción.
     */
    public static function hexADecimal(string $hex): string
    {
        $hex = ltrim($hex, '0') ?: '0';
        $dec = '0';
        foreach (str_split($hex) as $ch) {
            $dec = self::multiplicarYSumar($dec, 16, (int)hexdec($ch));
        }
        return $dec;
    }

    private function sumarUno(string $decimal): string
    {
        return self::multiplicarYSumar($decimal, 1, 1);
    }

    /**
     * Calcula ($decimal * $multiplicador) + $sumando con aritmética de cadenas.
     * El producto parcial por dígito nunca pasa de 9*16+15, así que cabe holgado
     * en un entero de PHP y el resultado es exacto para cualquier longitud.
     */
    private static function multiplicarYSumar(string $decimal, int $multiplicador, int $sumando): string
    {
        $resultado = '';
        $acarreo   = $sumando;
        for ($i = strlen($decimal) - 1; $i >= 0; $i--) {
            $producto  = ((int)$decimal[$i]) * $multiplicador + $acarreo;
            $resultado = ((string)($producto % 10)) . $resultado;
            $acarreo   = intdiv($producto, 10);
        }
        while ($acarreo > 0) {
            $resultado = ((string)($acarreo % 10)) . $resultado;
            $acarreo   = intdiv($acarreo, 10);
        }
        return ltrim($resultado, '0') ?: '0';
    }
}
