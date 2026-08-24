<?php

declare(strict_types=1);

/**
 * Diagnóstico de la firma electrónica de una empresa (uso por consola).
 *
 * Toma la firma ACTIVA de la empresa tal como la usa el envío al SRI, firma un
 * comprobante de prueba y verifica que todo lo que exige el validador XAdES del
 * SRI esté correcto: los tres digests, la firma RSA y —sobre todo— los datos del
 * certificado (emisor, número de serie y huella), que son los que producen el
 * mensaje "La información sobre el certificado de firma no se ajusta a XAdES".
 *
 * No modifica nada: no toca la base de datos ni envía nada al SRI.
 *
 * Uso:
 *   php app/cron/diag_firma_sri.php --listar        (todas las empresas con firma activa)
 *   php app/cron/diag_firma_sri.php <id_empresa>
 *   php app/cron/diag_firma_sri.php --p12=/ruta/firma.p12 --pass=clave
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\Sri\FirmadorXmlService;
use App\Services\Sri\X509DerReader;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$linea = str_repeat('=', 92) . "\n";

// ── Argumentos ────────────────────────────────────────────────────────────────
$idEmpresa = null;
$p12Path   = null;
$p12Pass   = null;
$listar    = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--listar')                          { $listar = true; }
    elseif (preg_match('/^--p12=(.+)$/', $arg, $m))   { $p12Path = $m[1]; }
    elseif (preg_match('/^--pass=(.*)$/', $arg, $m))  { $p12Pass = $m[1]; }
    elseif (ctype_digit($arg))                        { $idEmpresa = (int)$arg; }
}

if (!$listar && $idEmpresa === null && $p12Path === null) {
    fwrite(STDERR, "Uso: php app/cron/diag_firma_sri.php --listar\n");
    fwrite(STDERR, "     php app/cron/diag_firma_sri.php <id_empresa>\n");
    fwrite(STDERR, "     php app/cron/diag_firma_sri.php --p12=/ruta/firma.p12 --pass=clave\n");
    exit(1);
}

// ── Modo listado: qué firma usa cada empresa ─────────────────────────────────
if ($listar) {
    $db = \App\core\Database::getConnection();
    $filas = $db->query(
        "SELECT e.id, e.nombre, e.ruc, f.id AS id_firma, f.archivo_ruta,
                f.password_firma, f.fecha_expiracion
           FROM empresas e
           JOIN empresa_firma f
             ON f.id_empresa = e.id AND f.es_activo = TRUE AND f.eliminado = FALSE
          WHERE e.eliminado = FALSE
          ORDER BY e.id"
    )->fetchAll(\PDO::FETCH_ASSOC);

    echo $linea . 'EMPRESAS CON FIRMA ACTIVA (' . count($filas) . ")\n" . $linea;
    printf("%-5s %-13s %-34s %-42s %s\n", 'ID', 'RUC', 'EMPRESA', 'EMISOR DEL CERTIFICADO', 'SERIE');
    echo str_repeat('-', 130) . "\n";

    $firmadorTmp = new FirmadorXmlService();
    $refTmp      = new ReflectionClass($firmadorTmp);
    $cargar      = $refTmp->getMethod('cargarP12');
    $cargar->setAccessible(true);

    foreach ($filas as $f) {
        $emisor = '?';
        $serie  = '?';
        try {
            $c   = $cargar->invoke($firmadorTmp, $f['archivo_ruta'], (string)$f['password_firma']);
            $p   = '';
            openssl_x509_export($c['cert'], $p);
            $d   = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $p) ?? '', true);
            $lec = new X509DerReader($d);
            $emisor = $lec->issuerRfc2253();
            $serie  = $lec->serialDecimal();
            // Un serial en notación científica delata la conversión rota.
            if (!ctype_digit(ltrim($serie, '-'))) { $serie .= '  <<< SERIAL INVALIDO'; }
        } catch (\Throwable $e) {
            $emisor = 'ERROR: ' . $e->getMessage();
        }
        // El CN no siempre es el primer atributo del DN (en UANATACA va después
        // de 2.5.4.97), así que se busca en cualquier posición.
        $nombreCa = $emisor;
        if (preg_match('/(?:^|,)CN=((?:[^,\\\\]|\\\\.)*)/', $emisor, $mCn)) {
            $nombreCa = str_replace(['\\,', '\\+', '\\\\'], [',', '+', '\\'], $mCn[1]);
        }
        printf(
            "%-5s %-13s %-34s %-42s %s\n",
            $f['id'],
            substr((string)$f['ruc'], 0, 13),
            substr((string)$f['nombre'], 0, 34),
            substr($nombreCa, 0, 42),
            $serie
        );
    }
    echo "\nPara revisar una empresa a fondo: php app/cron/diag_firma_sri.php <id>\n";
    exit(0);
}

// ── 0. ¿Está disponible el lector DER? ────────────────────────────────────────
echo $linea . "ENTORNO\n" . $linea;
$hayLector = class_exists(X509DerReader::class);
echo 'X509DerReader        : ' . ($hayLector ? 'disponible' : 'NO SE ENCUENTRA  <<< la firma usará el respaldo y el SRI la rechazará') . "\n";
if ($hayLector) {
    $rc = new ReflectionClass(X509DerReader::class);
    echo 'Archivo              : ' . $rc->getFileName() . "\n";
}
echo 'PHP                  : ' . PHP_VERSION . "\n";
echo 'OpenSSL (PHP)        : ' . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : '?') . "\n";
echo 'gmp / bcmath         : ' . (extension_loaded('gmp') ? 'gmp' : (extension_loaded('bcmath') ? 'bcmath' : 'NINGUNA  <<< el serial puede salir mal')) . "\n";

// ── 1. Localizar la firma ─────────────────────────────────────────────────────
if ($p12Path === null) {
    $db = \App\core\Database::getConnection();
    $st = $db->prepare(
        "SELECT id, archivo_nombre, archivo_ruta, password_firma, fecha_expiracion
           FROM empresa_firma
          WHERE id_empresa = ? AND es_activo = TRUE AND eliminado = FALSE
          ORDER BY fecha_expiracion DESC NULLS LAST, created_at DESC
          LIMIT 1"
    );
    $st->execute([$idEmpresa]);
    $row = $st->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
        fwrite(STDERR, "\nLa empresa {$idEmpresa} no tiene una firma activa.\n");
        exit(1);
    }
    $p12Path = $row['archivo_ruta'];
    $p12Pass = $row['password_firma'];

    echo "\n" . $linea . "FIRMA ACTIVA DE LA EMPRESA {$idEmpresa}\n" . $linea;
    echo 'Registro empresa_firma: ' . $row['id'] . "\n";
    echo 'Nombre                : ' . $row['archivo_nombre'] . "\n";
    echo 'Ruta                  : ' . $p12Path . "\n";
    echo 'Existe el archivo     : ' . (is_file($p12Path) ? 'si' : 'NO  <<<') . "\n";
    echo 'Clave guardada        : ' . ($p12Pass === null || $p12Pass === '' ? 'VACIA  <<<' : 'presente (' . strlen($p12Pass) . ' caracteres)') . "\n";
}

if (!is_file($p12Path)) {
    fwrite(STDERR, "\nNo existe el archivo de firma: {$p12Path}\n");
    exit(1);
}

// ── 2. Abrir el .p12 con el mismo cargador del sistema ────────────────────────
$firmador = new FirmadorXmlService();
$ref      = new ReflectionClass($firmador);
$invocar  = static function (string $metodo, ...$args) use ($ref, $firmador) {
    $m = $ref->getMethod($metodo);
    $m->setAccessible(true);
    return $m->invoke($firmador, ...$args);
};

try {
    $certs = $invocar('cargarP12', $p12Path, (string)$p12Pass);
} catch (\Throwable $e) {
    fwrite(STDERR, "\nNo se pudo abrir el .p12: " . $e->getMessage() . "\n");
    exit(1);
}

$certificado = $certs['cert'];
$info        = openssl_x509_parse($certificado);
openssl_x509_export($certificado, $pem);
$der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $pem) ?? '', true);

echo "\n" . $linea . "CERTIFICADO\n" . $linea;
echo 'Vigencia   : ' . date('d-m-Y H:i:s', $info['validFrom_time_t']) . '  ->  ' . date('d-m-Y H:i:s', $info['validTo_time_t']) . "\n";
echo 'Caducado   : ' . ($info['validTo_time_t'] < time() ? 'SI  <<<' : 'no') . "\n";
echo 'Huella SHA1: ' . base64_encode(sha1($der, true)) . "\n";

$problemas = [];

if ($hayLector) {
    $lector = new X509DerReader($der);
    echo 'Sujeto     : ' . $lector->subjectRfc2253() . "\n";
    echo 'Emisor     : ' . $lector->issuerRfc2253() . "\n";

    $serie = $lector->serialDecimal();
    echo 'Serie      : ' . $serie . "\n";

    // Comprobación independiente: el serial debe ser una cadena de dígitos. Si
    // sale en notación científica ("9.98E+26") la conversión hex->decimal está
    // rota y el SRI rechazará la firma. Se valida además contra el hexadecimal
    // que reporta OpenSSL, por una vía distinta a la del propio lector.
    if (!ctype_digit(ltrim($serie, '-'))) {
        echo "             <<< SERIAL INVALIDO: no es un numero entero.\n";
        $problemas[] = 'El número de serie sale en notación científica; el SRI rechazará la firma.';
    }
    $hexOpenssl = strtolower(ltrim((string)($info['serialNumberHex'] ?? ''), '0'));
    if ($hexOpenssl !== '') {
        $reconstruido = '';
        $n = $serie;
        // Divide entre 16 con aritmética de cadenas para volver al hexadecimal.
        while ($n !== '0' && $n !== '' && ctype_digit($n)) {
            $resto = 0;
            $cociente = '';
            for ($i = 0; $i < strlen($n); $i++) {
                $actual   = $resto * 10 + (int)$n[$i];
                $cociente .= (string)intdiv($actual, 16);
                $resto     = $actual % 16;
            }
            $reconstruido = dechex($resto) . $reconstruido;
            $n = ltrim($cociente, '0') ?: '0';
        }
        $reconstruido = ltrim($reconstruido, '0') ?: '0';
        if ($reconstruido !== $hexOpenssl) {
            echo "             <<< el serial no corresponde al hexadecimal del certificado ({$hexOpenssl})\n";
            $problemas[] = "El número de serie no corresponde al del certificado (hex {$hexOpenssl}).";
        }
    }
}

// ── 3. Firmar un comprobante de prueba y verificarlo ──────────────────────────
echo "\n" . $linea . "FIRMA DE UN COMPROBANTE DE PRUEBA\n" . $linea;

$xmlPrueba = '<?xml version="1.0" encoding="UTF-8"?>'
    . '<factura id="comprobante" version="1.0.0"><infoTributaria>'
    . '<ambiente>1</ambiente><tipoEmision>1</tipoEmision><razonSocial>PRUEBA</razonSocial>'
    . '<ruc>9999999999001</ruc>'
    . '<claveAcceso>0000000000000000000000000000000000000000000000000</claveAcceso>'
    . '<codDoc>01</codDoc><estab>001</estab><ptoEmi>001</ptoEmi><secuencial>000000001</secuencial>'
    . '<dirMatriz>QUITO</dirMatriz></infoTributaria></factura>';

try {
    $firmado = $firmador->firmar($xmlPrueba, $p12Path, (string)$p12Pass);

    $destino = MVC_ROOT . '/storage/debug_firma';
    if (!is_dir($destino)) { @mkdir($destino, 0755, true); }
    $archivo = $destino . '/diag_' . date('Ymd_His') . '.xml';
    file_put_contents($archivo, $firmado);
    echo "XML firmado guardado en: {$archivo}\n";

    $dom = new DOMDocument();
    $dom->loadXML($firmado);
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
    $xp->registerNamespace('etsi', 'http://uri.etsi.org/01903/v1.3.2#');

    $signedProps = $xp->query('//etsi:SignedProperties')->item(0);
    $keyInfo     = $xp->query('//ds:KeyInfo')->item(0);
    $signedInfo  = $xp->query('//ds:SignedInfo')->item(0);

    $declarados = [];
    foreach ($xp->query('//ds:SignedInfo/ds:Reference') as $r) {
        $dv = $r->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'DigestValue')->item(0);
        $declarados[$r->getAttribute('URI')] = trim($dv->textContent);
    }

    $calcSp = base64_encode(sha1($signedProps->C14N(false, false), true));
    $calcKi = base64_encode(sha1($keyInfo->C14N(false, false), true));

    // Digest del comprobante: se quita la firma y se canonicaliza el resto.
    $sinFirma = new DOMDocument();
    $sinFirma->loadXML($firmado);
    $sig = $sinFirma->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
    $sig->parentNode->removeChild($sig);
    $calcDoc = base64_encode(sha1($sinFirma->documentElement->C14N(false, false), true));

    $okSp  = ($declarados['#' . $signedProps->getAttribute('Id')] ?? '') === $calcSp;
    $okKi  = ($declarados['#' . $keyInfo->getAttribute('Id')] ?? '') === $calcKi;
    $okDoc = ($declarados['#comprobante'] ?? '') === $calcDoc;

    $firmaBin = base64_decode(preg_replace('/\s/', '', $xp->query('//ds:SignatureValue')->item(0)->textContent) ?? '');
    $okRsa    = openssl_verify($signedInfo->C14N(false, false), $firmaBin, openssl_pkey_get_public($certificado), OPENSSL_ALGO_SHA1) === 1;

    echo 'Digest SignedProperties : ' . ($okSp  ? 'ok' : 'DESCUADRA') . "\n";
    echo 'Digest KeyInfo          : ' . ($okKi  ? 'ok' : 'DESCUADRA') . "\n";
    echo 'Digest comprobante      : ' . ($okDoc ? 'ok' : 'DESCUADRA') . "\n";
    echo 'Firma RSA-SHA1          : ' . ($okRsa ? 'ok' : 'INVALIDA')  . "\n";

    if (!$okSp)  { $problemas[] = 'El digest de etsi:SignedProperties descuadra.'; }
    if (!$okKi)  { $problemas[] = 'El digest de ds:KeyInfo descuadra.'; }
    if (!$okDoc) { $problemas[] = 'El digest del comprobante descuadra.'; }
    if (!$okRsa) { $problemas[] = 'La firma RSA-SHA1 no valida contra la clave pública del certificado.'; }

    // Lo que el SRI compara contra el certificado real.
    $emisorXml = trim($xp->query('//etsi:IssuerSerial/ds:X509IssuerName')->item(0)->textContent);
    $serieXml  = trim($xp->query('//etsi:IssuerSerial/ds:X509SerialNumber')->item(0)->textContent);
    $huellaXml = trim($xp->query('//etsi:CertDigest/ds:DigestValue')->item(0)->textContent);

    echo "\nDatos del certificado escritos en la firma:\n";
    echo "  X509IssuerName   : {$emisorXml}\n";
    echo "  X509SerialNumber : {$serieXml}\n";
    echo "  CertDigest       : {$huellaXml}\n";

    if ($hayLector) {
        $emisorReal = $lector->issuerRfc2253();
        $serieReal  = $lector->serialDecimal();
        $huellaReal = base64_encode(sha1($der, true));

        echo "\nComparación con el certificado real:\n";
        echo '  IssuerName   : ' . ($emisorXml === $emisorReal ? 'ok' : "MAL\n     esperado: {$emisorReal}") . "\n";
        echo '  SerialNumber : ' . ($serieXml  === $serieReal  ? 'ok' : "MAL -> esperado: {$serieReal}") . "\n";
        echo '  CertDigest   : ' . ($huellaXml === $huellaReal ? 'ok' : 'MAL') . "\n";

        if ($emisorXml !== $emisorReal) { $problemas[] = 'ds:X509IssuerName no coincide con el emisor real.'; }
        if ($serieXml  !== $serieReal)  { $problemas[] = 'ds:X509SerialNumber no coincide con el serial real.'; }
        if ($huellaXml !== $huellaReal) { $problemas[] = 'etsi:CertDigest no coincide con la huella del certificado.'; }
    }
} catch (\Throwable $e) {
    echo 'ERROR al firmar: ' . $e->getMessage() . "\n";
    $problemas[] = 'La firma lanzó una excepción: ' . $e->getMessage();
}

if ($info['validTo_time_t'] < time()) { $problemas[] = 'El certificado está caducado.'; }
if (!$hayLector) { $problemas[] = 'No se encuentra X509DerReader: revise que el archivo esté desplegado.'; }

echo "\n" . $linea . "RESUMEN\n" . $linea;
if ($problemas) {
    foreach ($problemas as $i => $p) { echo '  ' . ($i + 1) . ') ' . $p . "\n"; }
    exit(1);
}
echo "  La firma es correcta: los tres digests, la firma RSA y los datos del\n";
echo "  certificado coinciden. Si el SRI sigue rechazando, comparta el XML\n";
echo "  guardado arriba para revisar el resto del XAdES.\n";
