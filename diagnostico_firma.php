<?php
/**
 * Diagnóstico de firma electrónica con TU certificado real.
 *
 * USO (desde terminal):
 *   C:\xampp\php\php.exe diagnostico_firma.php "C:\ruta\al\certificado.p12" "tu_contraseña"
 *
 * Muestra: carga del certificado, datos del emisor/serial, vigencia,
 * y firma un XML de prueba auto-verificando todos los digests + firma RSA.
 */

require __DIR__ . '/bootstrap.php';

use App\Services\Sri\FirmadorXmlService;

$p12Path = $argv[1] ?? '';
$p12Pass = $argv[2] ?? '';

if (!$p12Path || !is_file($p12Path)) {
    echo "ERROR: Indica la ruta del .p12.\n";
    echo "Uso: php diagnostico_firma.php \"C:\\ruta\\cert.p12\" \"contraseña\"\n";
    exit(1);
}

echo "==========================================================\n";
echo " DIAGNÓSTICO DE FIRMA ELECTRÓNICA SRI\n";
echo "==========================================================\n\n";

// ── 1. Cargar el .p12 ──────────────────────────────────────────────
$p12Raw = file_get_contents($p12Path);
$certs = [];
$modoCarga = '';

if (openssl_pkcs12_read($p12Raw, $certs, $p12Pass)) {
    $modoCarga = 'NATIVO (openssl_pkcs12_read)';
} else {
    $err = openssl_error_string();
    echo "[!] openssl_pkcs12_read falló: $err\n";
    echo "    Intentando fallback legacy CLI...\n\n";
    // Reusar la lógica del servicio via reflexión
    try {
        $ref = new ReflectionMethod(FirmadorXmlService::class, 'cargarP12');
        $ref->setAccessible(true);
        $certs = $ref->invoke(new FirmadorXmlService(), $p12Path, $p12Pass);
        $modoCarga = 'LEGACY CLI (openssl -legacy)';
    } catch (\Throwable $e) {
        echo "ERROR cargando certificado: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "1. CARGA DEL CERTIFICADO: OK\n";
echo "   Modo: $modoCarga\n\n";

// ── 2. Datos del certificado ───────────────────────────────────────
$certInfo = openssl_x509_parse($certs['cert']);

echo "2. DATOS DEL CERTIFICADO:\n";
echo "   Sujeto (CN): " . ($certInfo['subject']['CN'] ?? '(sin CN)') . "\n";
echo "   Emisor (CN): " . ($certInfo['issuer']['CN'] ?? '(sin CN)') . "\n";

// IssuerName como lo genera el firmador
$ref2 = new ReflectionMethod(FirmadorXmlService::class, 'formatIssuerDn');
$ref2->setAccessible(true);
$issuerName = $ref2->invoke(new FirmadorXmlService(), $certInfo['issuer'] ?? []);
echo "   IssuerName (XML): $issuerName\n";

// Serial
$ref3 = new ReflectionMethod(FirmadorXmlService::class, 'serialHexToDec');
$ref3->setAccessible(true);
$serialDec = $ref3->invoke(new FirmadorXmlService(), $certInfo['serialNumberHex'] ?? '0');
echo "   SerialNumber (dec): $serialDec\n";
echo "   SerialNumber (hex): " . ($certInfo['serialNumberHex'] ?? '?') . "\n";

// Vigencia
$validFrom = date('Y-m-d H:i:s', $certInfo['validFrom_time_t'] ?? 0);
$validTo   = date('Y-m-d H:i:s', $certInfo['validTo_time_t'] ?? 0);
$ahora = time();
$vigente = ($ahora >= ($certInfo['validFrom_time_t'] ?? 0) && $ahora <= ($certInfo['validTo_time_t'] ?? 0));
echo "   Válido desde: $validFrom\n";
echo "   Válido hasta: $validTo\n";
echo "   VIGENTE AHORA: " . ($vigente ? 'SI' : 'NO ¡EXPIRADO O NO VÁLIDO AÚN!') . "\n\n";

// ── 3. Verificar que la clave privada corresponde al certificado ────
$pubFromCert = openssl_pkey_get_public($certs['cert']);
$detPub = openssl_pkey_get_details($pubFromCert);
$detPriv = openssl_pkey_get_details($certs['pkey']);
$claveCoincide = ($detPub['key'] === $detPriv['key']);
echo "3. CLAVE PRIVADA vs CERTIFICADO:\n";
echo "   La clave privada corresponde al certificado: " . ($claveCoincide ? 'SI' : 'NO ¡MISMATCH!') . "\n";
echo "   Bits de la clave: " . ($detPriv['bits'] ?? '?') . "\n\n";

// ── 4. Firmar XML de prueba y auto-verificar ───────────────────────
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
'<factura id="comprobante" version="1.1.0"><infoTributaria><ambiente>1</ambiente>' .
'<razonSocial>PRUEBA</razonSocial><ruc>1234567890001</ruc>' .
'<claveAcceso>1234567890123456789012345678901234567890123456789</claveAcceso>' .
'<codDoc>01</codDoc><estab>001</estab><ptoEmi>001</ptoEmi><secuencial>000000001</secuencial>' .
'</infoTributaria><infoFactura><fechaEmision>01/01/2026</fechaEmision>' .
'<importeTotal>112.00</importeTotal></infoFactura></factura>';

echo "4. FIRMA DE PRUEBA + AUTO-VERIFICACIÓN:\n";
try {
    $firmador = new FirmadorXmlService();
    $xmlFirmado = $firmador->firmar($xml, $p12Path, $p12Pass);
} catch (\Throwable $e) {
    echo "   ERROR al firmar: " . $e->getMessage() . "\n";
    exit(1);
}

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadXML($xmlFirmado);
$xp = new DOMXPath($dom);
$xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$xp->registerNamespace('etsi', 'http://uri.etsi.org/01903/v1.3.2#');

$errores = 0;

// Digest comprobante
$domC = clone $dom;
$xpC = new DOMXPath($domC);
$xpC->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
foreach ($xpC->query('//ds:Signature') as $s) { $s->parentNode->removeChild($s); }
$dCompCalc = base64_encode(sha1($domC->documentElement->C14N(false, false), true));
$dCompXml = '';
foreach ($xp->query('//ds:Reference') as $r) {
    if ($r->getAttribute('URI') === '#comprobante') $dCompXml = trim($r->getElementsByTagName('DigestValue')->item(0)->textContent);
}
$ok = $dCompCalc === $dCompXml; if (!$ok) $errores++;
echo "   [".($ok?'OK':'XX')."] Digest del comprobante\n";

// Digest SignedProperties
$sp = $xp->query('//etsi:SignedProperties')->item(0);
$dSpCalc = base64_encode(sha1($sp->C14N(false, false), true));
$dSpXml = '';
foreach ($xp->query('//ds:Reference') as $r) {
    if (strpos($r->getAttribute('Type'), 'SignedProperties') !== false) $dSpXml = trim($r->getElementsByTagName('DigestValue')->item(0)->textContent);
}
$ok = $dSpCalc === $dSpXml; if (!$ok) $errores++;
echo "   [".($ok?'OK':'XX')."] Digest de SignedProperties\n";

// Firma RSA
$si = $xp->query('//ds:SignedInfo')->item(0);
$sv = $xp->query('//ds:SignatureValue')->item(0)->textContent;
$verify = openssl_verify($si->C14N(false, false), base64_decode(preg_replace('/\s+/', '', $sv)), $pubFromCert, OPENSSL_ALGO_SHA1);
$ok = $verify === 1; if (!$ok) $errores++;
echo "   [".($ok?'OK':'XX')."] Firma RSA del SignedInfo (openssl_verify=$verify)\n\n";

echo "==========================================================\n";
if ($errores === 0 && $claveCoincide && $vigente) {
    echo " RESULTADO: La firma es técnicamente VÁLIDA.\n";
    echo " Si el SRI sigue rechazando, el problema es la CADENA\n";
    echo " del certificado (CA no reconocida) o el certificado no\n";
    echo " está registrado/autorizado para firmar en el SRI.\n";
} else {
    echo " RESULTADO: SE DETECTARON PROBLEMAS (ver arriba).\n";
    if (!$vigente) echo " - El certificado NO está vigente.\n";
    if (!$claveCoincide) echo " - La clave privada NO corresponde al certificado.\n";
    if ($errores) echo " - $errores digest/firma no coinciden.\n";
}
echo "==========================================================\n";

file_put_contents(__DIR__ . '/diagnostico_xml_firmado.xml', $xmlFirmado);
echo "XML firmado guardado en: diagnostico_xml_firmado.xml\n";
