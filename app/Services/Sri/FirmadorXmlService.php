<?php

declare(strict_types=1);

namespace App\Services\Sri;

/**
 * Firma un comprobante XML bajo el estándar XAdES-BES requerido por el SRI Ecuador.
 *
 * Algoritmos (según Ficha Técnica SRI v2.32):
 *   Canonicalización : C14N  — http://www.w3.org/TR/2001/REC-xml-c14n-20010315
 *   Firma            : RSA-SHA1
 *   Digest           : SHA1
 *   Tipo             : ENVELOPED (la firma se inserta dentro del elemento raíz)
 *
 * Requiere extensiones PHP: openssl, dom (ambas disponibles en XAMPP).
 */
class FirmadorXmlService
{
    /**
     * Firma el XML del comprobante con el certificado .p12 indicado.
     *
     * @param  string $xmlString   XML sin firma (generado por XmlFacturaVentaService)
     * @param  string $p12Path     Ruta absoluta al archivo .p12
     * @param  string $p12Password Contraseña del .p12
     * @return string              XML con firma XAdES-BES incrustada
     * @throws \RuntimeException   Si el certificado es inválido o la firma falla
     */
    public function firmar(string $xmlString, string $p12Path, string $p12Password): string
    {
        // ── 1. Cargar certificado P12 ──────────────────────────────────────────
        $certs = $this->cargarP12($p12Path, $p12Password);

        $privateKey  = $certs['pkey'];
        $certificate = $certs['cert'];

        // ── 2. Datos del certificado ───────────────────────────────────────────
        $certInfo   = openssl_x509_parse($certificate);
        $issuerName = $this->formatIssuerDn($certInfo['issuer'] ?? []);
        $serialDec  = $this->serialHexToDec($certInfo['serialNumberHex'] ?? '0');

        openssl_x509_export($certificate, $certPem);
        $certDer    = $this->pemToDer($certPem);
        $certBase64 = base64_encode($certDer);
        $certDigest = base64_encode(sha1($certDer, true));

        // ── 3. Módulo RSA del certificado (para ds:KeyValue) ──────────────────
        $pubKey     = openssl_pkey_get_public($certificate);
        $pubDetails = openssl_pkey_get_details($pubKey);
        $modulus    = base64_encode($pubDetails['rsa']['n']);

        // ── 4. Cargar XML en DOM ───────────────────────────────────────────────
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xmlString);
        $facturaEl = $dom->documentElement; // <factura id="comprobante">

        // ── 5. Digest del comprobante ANTES de insertar la firma ───────────────
        //    El transform "enveloped-signature" excluye el ds:Signature al verificar.
        //    Como aún no existe, calculamos el C14N del elemento original directamente.
        $comprobanteDigest = $this->c14nDigest($facturaEl);

        // ── 6. IDs aleatorios para los elementos de firma ─────────────────────
        $sigId      = mt_rand(100000, 999999);
        $sigInfoId  = mt_rand(100000, 999999);
        $spRefId    = mt_rand(100000, 999999);   // referencia → SignedProperties
        $spId       = mt_rand(100000, 999999);   // SignedProperties
        $certId     = mt_rand(100000, 999999);   // KeyInfo / Certificate
        $docRefId   = mt_rand(100000, 999999);   // referencia → comprobante
        $sigValId   = mt_rand(100000, 999999);
        $objId      = mt_rand(100000, 999999);

        $dsNs   = 'http://www.w3.org/2000/09/xmldsig#';
        $etsiNs = 'http://uri.etsi.org/01903/v1.3.2#';

        // ── 7. Construir ds:Signature con placeholders ────────────────────────
        $sigEl = $dom->createElementNS($dsNs, 'ds:Signature');
        $sigEl->setAttribute('Id', "Signature{$sigId}");
        // NOTA: NO declarar xmlns:etsi en ds:Signature mediante setAttributeNS.
        // PHP's C14N interno no lo reconoce como namespace en-scope, pero el
        // validador Java del SRI sí lo leería al parsear el XML, causando mismatch
        // en el digest de ds:SignedInfo → FIRMA INVALIDA.
        // El namespace etsi: se declara automáticamente en etsi:QualifyingProperties.

        // ─── ds:SignedInfo ────────────────────────────────────────────────────
        $signedInfoEl = $dom->createElementNS($dsNs, 'ds:SignedInfo');
        $signedInfoEl->setAttribute('Id', "Signature-SignedInfo{$sigInfoId}");

        $c14mEl = $dom->createElementNS($dsNs, 'ds:CanonicalizationMethod');
        $c14mEl->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfoEl->appendChild($c14mEl);

        $sigMethEl = $dom->createElementNS($dsNs, 'ds:SignatureMethod');
        $sigMethEl->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfoEl->appendChild($sigMethEl);

        // Referencia 1: etsi:SignedProperties (digest calculado después de insertar en DOM)
        $ref1El = $dom->createElementNS($dsNs, 'ds:Reference');
        $ref1El->setAttribute('Id', "SignedPropertiesID{$spRefId}");
        $ref1El->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $ref1El->setAttribute('URI', "#Signature{$sigId}-SignedProperties{$spId}");
        $ref1El->appendChild($this->digestMethodEl($dom, $dsNs));
        $dv1El = $dom->createElementNS($dsNs, 'ds:DigestValue');
        $dv1El->appendChild($dom->createTextNode('__SP__'));
        $ref1El->appendChild($dv1El);
        $signedInfoEl->appendChild($ref1El);

        // Referencia 2: ds:KeyInfo/Certificate (digest calculado después de insertar en DOM)
        $ref2El = $dom->createElementNS($dsNs, 'ds:Reference');
        $ref2El->setAttribute('URI', "#Certificate{$certId}");
        $ref2El->appendChild($this->digestMethodEl($dom, $dsNs));
        $dv2El = $dom->createElementNS($dsNs, 'ds:DigestValue');
        $dv2El->appendChild($dom->createTextNode('__KI__'));
        $ref2El->appendChild($dv2El);
        $signedInfoEl->appendChild($ref2El);

        // Referencia 3: comprobante (#comprobante) — digest ya calculado
        $ref3El = $dom->createElementNS($dsNs, 'ds:Reference');
        $ref3El->setAttribute('Id', "Reference-ID-{$docRefId}");
        $ref3El->setAttribute('URI', '#comprobante');
        $transEl   = $dom->createElementNS($dsNs, 'ds:Transforms');
        $transfEl  = $dom->createElementNS($dsNs, 'ds:Transform');
        $transfEl->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transEl->appendChild($transfEl);
        // Transform C14N explícito: elimina ambigüedad en cómo el validador
        // serializa el node-set resultante del enveloped-signature.
        $c14nTrEl = $dom->createElementNS($dsNs, 'ds:Transform');
        $c14nTrEl->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transEl->appendChild($c14nTrEl);
        $ref3El->appendChild($transEl);
        $ref3El->appendChild($this->digestMethodEl($dom, $dsNs));
        $dv3El = $dom->createElementNS($dsNs, 'ds:DigestValue');
        $dv3El->appendChild($dom->createTextNode($comprobanteDigest));
        $ref3El->appendChild($dv3El);
        $signedInfoEl->appendChild($ref3El);

        $sigEl->appendChild($signedInfoEl);

        // ─── ds:SignatureValue ────────────────────────────────────────────────
        $sigValEl = $dom->createElementNS($dsNs, 'ds:SignatureValue');
        $sigValEl->setAttribute('Id', "SignatureValue{$sigValId}");
        $sigValEl->appendChild($dom->createTextNode('__SV__'));
        $sigEl->appendChild($sigValEl);

        // ─── ds:KeyInfo ───────────────────────────────────────────────────────
        $keyInfoEl = $dom->createElementNS($dsNs, 'ds:KeyInfo');
        $keyInfoEl->setAttribute('Id', "Certificate{$certId}");

        $x509DataEl = $dom->createElementNS($dsNs, 'ds:X509Data');
        $x509CertEl = $dom->createElementNS($dsNs, 'ds:X509Certificate');
        $x509CertEl->appendChild($dom->createTextNode($certBase64));
        $x509DataEl->appendChild($x509CertEl);
        $keyInfoEl->appendChild($x509DataEl);

        $keyValEl = $dom->createElementNS($dsNs, 'ds:KeyValue');
        $rsaKeyEl = $dom->createElementNS($dsNs, 'ds:RSAKeyValue');
        $modEl    = $dom->createElementNS($dsNs, 'ds:Modulus');
        $modEl->appendChild($dom->createTextNode("\n" . chunk_split($modulus, 76, "\n")));
        $rsaKeyEl->appendChild($modEl);
        $expEl = $dom->createElementNS($dsNs, 'ds:Exponent');
        $expEl->appendChild($dom->createTextNode(base64_encode($pubDetails['rsa']['e'])));
        $rsaKeyEl->appendChild($expEl);
        $keyValEl->appendChild($rsaKeyEl);
        $keyInfoEl->appendChild($keyValEl);

        $sigEl->appendChild($keyInfoEl);

        // ─── ds:Object > etsi:QualifyingProperties > etsi:SignedProperties ────
        $objectEl   = $dom->createElementNS($dsNs, 'ds:Object');
        $objectEl->setAttribute('Id', "Signature{$sigId}-Object{$objId}");

        $qualPropsEl = $dom->createElementNS($etsiNs, 'etsi:QualifyingProperties');
        $qualPropsEl->setAttribute('Target', "#Signature{$sigId}");

        $signedPropsEl = $dom->createElementNS($etsiNs, 'etsi:SignedProperties');
        $signedPropsEl->setAttribute('Id', "Signature{$sigId}-SignedProperties{$spId}");

        // etsi:SignedSignatureProperties
        $ssp = $dom->createElementNS($etsiNs, 'etsi:SignedSignatureProperties');

        $stEl = $dom->createElementNS($etsiNs, 'etsi:SigningTime');
        $stEl->appendChild($dom->createTextNode((new \DateTime())->format('Y-m-d\TH:i:sP')));
        $ssp->appendChild($stEl);

        $scEl   = $dom->createElementNS($etsiNs, 'etsi:SigningCertificate');
        $certEl = $dom->createElementNS($etsiNs, 'etsi:Cert');

        $cdEl = $dom->createElementNS($etsiNs, 'etsi:CertDigest');
        $cdEl->appendChild($this->digestMethodEl($dom, $dsNs));
        $cdvEl = $dom->createElementNS($dsNs, 'ds:DigestValue');
        $cdvEl->appendChild($dom->createTextNode($certDigest));
        $cdEl->appendChild($cdvEl);
        $certEl->appendChild($cdEl);

        $isEl   = $dom->createElementNS($etsiNs, 'etsi:IssuerSerial');
        $inEl   = $dom->createElementNS($dsNs, 'ds:X509IssuerName');
        $inEl->appendChild($dom->createTextNode($issuerName));
        $isEl->appendChild($inEl);
        $snEl = $dom->createElementNS($dsNs, 'ds:X509SerialNumber');
        $snEl->appendChild($dom->createTextNode($serialDec));
        $isEl->appendChild($snEl);
        $certEl->appendChild($isEl);

        $scEl->appendChild($certEl);
        $ssp->appendChild($scEl);
        $signedPropsEl->appendChild($ssp);

        // etsi:SignedDataObjectProperties
        $sdopEl  = $dom->createElementNS($etsiNs, 'etsi:SignedDataObjectProperties');
        $dofEl   = $dom->createElementNS($etsiNs, 'etsi:DataObjectFormat');
        $dofEl->setAttribute('ObjectReference', "#Reference-ID-{$docRefId}");
        $descEl  = $dom->createElementNS($etsiNs, 'etsi:Description');
        $descEl->appendChild($dom->createTextNode('contenido comprobante'));
        $dofEl->appendChild($descEl);
        $mimeEl  = $dom->createElementNS($etsiNs, 'etsi:MimeType');
        $mimeEl->appendChild($dom->createTextNode('text/xml'));
        $dofEl->appendChild($mimeEl);
        $sdopEl->appendChild($dofEl);
        $signedPropsEl->appendChild($sdopEl);

        $qualPropsEl->appendChild($signedPropsEl);
        $objectEl->appendChild($qualPropsEl);
        $sigEl->appendChild($objectEl);

        // ── 8. Insertar ds:Signature en el documento ──────────────────────────
        $facturaEl->appendChild($sigEl);

        // ── 9. Computar digests en contexto DOM (namespaces heredados correctos) ─
        $this->replaceText($dv1El, $this->c14nDigest($signedPropsEl));
        $this->replaceText($dv2El, $this->c14nDigest($keyInfoEl));

        // ── 10. Canonicalizar ds:SignedInfo y firmar ───────────────────────────
        $signedInfoC14n = $signedInfoEl->C14N(false, false);
        $rawSignature   = '';
        if (!openssl_sign($signedInfoC14n, $rawSignature, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new \RuntimeException("Falló la firma RSA-SHA1: " . openssl_error_string());
        }
        $this->replaceText($sigValEl, "\n" . chunk_split(base64_encode($rawSignature), 76, "\n"));

        return $dom->saveXML();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Calcula SHA1(C14N(element)) en base64. */
    private function c14nDigest(\DOMElement $el): string
    {
        return base64_encode(sha1($el->C14N(false, false), true));
    }

    /** Crea un elemento ds:DigestMethod con el algoritmo SHA1. */
    private function digestMethodEl(\DOMDocument $dom, string $dsNs): \DOMElement
    {
        $el = $dom->createElementNS($dsNs, 'ds:DigestMethod');
        $el->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        return $el;
    }

    /** Reemplaza el contenido de texto de un elemento DOM. */
    private function replaceText(\DOMElement $el, string $value): void
    {
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        $el->appendChild($el->ownerDocument->createTextNode($value));
    }

    /**
     * Formatea el DN del emisor en el formato esperado por el SRI.
     * PHP devuelve los componentes de más general a más específico;
     * array_reverse da el orden más específico primero (L, CN, ST, OU, O, C).
     */
    private function formatIssuerDn(array $issuer): string
    {
        $parts = [];
        foreach ($issuer as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $v) {
                    $parts[] = "$key=$v";
                }
            } else {
                $parts[] = "$key=$val";
            }
        }
        return implode(',', array_reverse($parts));
    }

    /** Convierte un número de serie hexadecimal a decimal (usa GMP si está disponible). */
    private function serialHexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0') ?: '0';
        if (extension_loaded('gmp')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        if (extension_loaded('bcmath')) {
            $dec = '0';
            foreach (str_split($hex) as $ch) {
                $dec = bcadd(bcmul($dec, '16'), (string)hexdec($ch));
            }
            return $dec;
        }
        // Fallback para números que caben en un entero de 64 bits
        return (string)hexdec($hex);
    }

    /** Extrae los bytes DER del PEM (quita cabeceras y decodifica base64). */
    private function pemToDer(string $pem): string
    {
        $pem = preg_replace('/-----[^-]+-----/', '', $pem);
        $pem = str_replace(["\r", "\n", ' '], '', $pem);
        return base64_decode($pem);
    }

    /**
     * Carga un PKCS12 (.p12) y devuelve ['pkey' => ..., 'cert' => ...].
     *
     * Intenta primero con la extensión nativa. Si OpenSSL 3.0 rechaza el
     * formato legacy (error 0308010c), hace un fallback al binario openssl
     * con la flag -legacy, que sí soporta RC2 / 3DES de los certificados
     * emitidos por las CA del SRI Ecuador.
     */
    private function cargarP12(string $p12Path, string $p12Password): array
    {
        $p12Raw = @file_get_contents($p12Path);
        if ($p12Raw === false) {
            throw new \RuntimeException("No se pudo leer el archivo .p12: {$p12Path}");
        }

        $p12Password = trim($p12Password);
        $certs = [];

        if (openssl_pkcs12_read($p12Raw, $certs, $p12Password)) {
            return $certs;
        }

        // OpenSSL 3.0 deshabilitó algoritmos legacy (RC2, 3DES) usados por las
        // CA del SRI Ecuador. Reintentamos vía binario openssl con -legacy.
        $opensslErr = openssl_error_string() ?: '';
        if (stripos($opensslErr, '0308010c') !== false || stripos($opensslErr, 'unsupported') !== false) {
            return $this->cargarP12ConLegacyCli($p12Path, $p12Password);
        }

        throw new \RuntimeException(
            "Certificado .p12 inválido o contraseña incorrecta. Error: {$opensslErr}"
        );
    }

    /**
     * Fallback: extrae clave y certificado usando el binario openssl con -legacy.
     * Necesario cuando OpenSSL 3.0+ rechaza el cifrado RC2/3DES del PKCS12.
     *
     * OpenSSL 3.0 requiere el módulo legacy.dll. Si no está en el directorio
     * de trabajo actual, hay que indicar la ruta con -provider-path.
     */
    private function cargarP12ConLegacyCli(string $p12Path, string $p12Password): array
    {
        $bin          = $this->encontrarOpensslBin();
        $providerPath = $this->encontrarOpensslProviderPath($bin);

        if ($providerPath) {
            // Carga explícita del provider legacy con ruta absoluta
            $baseArgs = ['pkcs12', '-provider-path', $providerPath, '-provider', 'legacy', '-provider', 'default'];
        } else {
            // Intento con -legacy (shorthand; puede funcionar si OPENSSL_MODULES está configurado)
            $baseArgs = ['pkcs12', '-legacy'];
        }

        $keyPem  = $this->ejecutarOpensslCmd($bin, array_merge($baseArgs, ['-nocerts', '-nodes']),  $p12Path, $p12Password);
        $certPem = $this->ejecutarOpensslCmd($bin, array_merge($baseArgs, ['-nokeys',  '-clcerts']), $p12Path, $p12Password);

        $privateKey  = openssl_pkey_get_private($keyPem);
        $certificate = openssl_x509_read($certPem);

        if (!$privateKey) {
            throw new \RuntimeException("Error cargando clave PEM extraída: " . openssl_error_string());
        }
        if (!$certificate) {
            throw new \RuntimeException("Error cargando certificado PEM extraído: " . openssl_error_string());
        }

        return ['pkey' => $privateKey, 'cert' => $certificate, 'extracerts' => []];
    }

    /**
     * Ejecuta el binario openssl con proc_open pasando la contraseña por stdin
     * (evita exponerla en la línea de comandos / ps aux).
     */
    private function ejecutarOpensslCmd(string $bin, array $args, string $p12Path, string $password): string
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException(
                "proc_open está deshabilitado en PHP. Habilítelo o actualice OpenSSL a v3.0+ " .
                "activando el proveedor legacy en openssl.cnf."
            );
        }

        // En Windows, proc_open con array necesita comillas en el primer elemento si tiene espacios.
        // Usamos string command para compatibilidad entre plataformas.
        $p12PathQ = escapeshellarg($p12Path);
        $argsStr  = implode(' ', array_map('escapeshellarg', $args));
        $cmd = escapeshellarg($bin) . " {$argsStr} -in {$p12PathQ} -passin stdin";

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($proc)) {
            throw new \RuntimeException("No se pudo ejecutar el binario openssl: {$bin}");
        }

        fwrite($pipes[0], $password);
        fclose($pipes[0]);

        $output   = stream_get_contents($pipes[1]);
        $errOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0 || trim($output) === '') {
            $hint = '';
            if (stripos($errOutput, 'legacy') !== false || stripos($errOutput, 'provider') !== false) {
                $hint = " | SOLUCIÓN: verifique que C:\\xampp\\apache\\bin\\ossl-modules\\legacy.dll existe. " .
                        "Si no existe, descárguela de https://slproweb.com/products/Win32OpenSSL.html " .
                        "instalando 'OpenSSL Full' y copiando legacy.dll al directorio indicado.";
            }
            throw new \RuntimeException(
                "openssl terminó con error (exit={$exitCode}). " .
                "Verifique la contraseña del .p12. Detalle: {$errOutput}{$hint}"
            );
        }

        return $output;
    }

    /**
     * Busca el binario openssl en ubicaciones comunes de XAMPP y el sistema.
     * Lanza excepción si no lo encuentra.
     */
    private function encontrarOpensslBin(): string
    {
        $candidatos = [
            'C:\xampp\apache\bin\openssl.exe',
            'C:\xampp\php\openssl.exe',
            '/usr/bin/openssl',
            '/usr/local/bin/openssl',
            '/opt/homebrew/bin/openssl',
        ];

        foreach ($candidatos as $bin) {
            if (is_file($bin)) {
                return $bin;
            }
        }

        // Intento final: confiar en el PATH del sistema
        exec('openssl version 2>&1', $out, $code);
        if ($code === 0) {
            return 'openssl';
        }

        throw new \RuntimeException(
            "No se encontró el binario 'openssl'. " .
            "En XAMPP Windows: C:\\xampp\\apache\\bin\\openssl.exe debe existir. " .
            "Alternativamente, active el proveedor legacy en openssl.cnf de PHP."
        );
    }

    /**
     * Busca el directorio ossl-modules que contiene legacy.dll / legacy.so.
     * OpenSSL 3.0 lo necesita cuando el proceso no arranca desde el directorio del binario.
     * Devuelve null si no lo encuentra (el llamador intentará -legacy sin provider-path).
     */
    private function encontrarOpensslProviderPath(string $bin): ?string
    {
        $binDir = str_replace('\\', '/', dirname($bin));

        $candidatos = [
            $binDir . '/ossl-modules',
            $binDir . '/../lib/ossl-modules',
            'C:/xampp/php/extras/ssl',
            'C:/xampp/apache/bin/ossl-modules',
            'C:/xampp/apache/lib/ossl-modules',
            'C:/xampp/php/ossl-modules',
            'C:/Program Files/OpenSSL-Win64/bin/ossl-modules',
            '/usr/lib/x86_64-linux-gnu/ossl-modules',
            '/usr/lib64/ossl-modules',
            '/usr/local/lib/ossl-modules',
        ];

        foreach ($candidatos as $dir) {
            $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
            $dll = $dir . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'legacy.dll' : 'legacy.so');
            if (file_exists($dll)) {
                return $dir;
            }
        }

        return null;
    }
}
