<?php

declare(strict_types=1);

namespace App\Services\ImportarAntiguo;

use Exception;

/**
 * Cliente/escáner FTP del "antiguo CaMaGaRe".
 *
 * Lee los XML autorizados que viven en el servidor FTP legacy, organizados como:
 *   /ftp_documentos/{tipo_carpeta}/{ruc_empresa}/{ruc_cliente}/{archivo}.xml
 *
 * Todo lo que cuelga del RUC de la empresa fue EMITIDO por ella (ventas).
 * El XML es el comprobante firmado "pelado" (sin sobre de autorización).
 *
 * Esta clase NO escribe en BD: solo lista, clasifica y baja archivos a memoria.
 * La persistencia del manifiesto y el registro de documentos van en capas aparte.
 */
class FtpDocumentosScanner
{
    /** Carpetas de comprobantes EMITIDOS por la empresa (ventas), con su codDoc SRI. */
    public const CARPETAS_EMITIDOS = [
        'facturas_autorizadas'      => '01', // Factura
        'liquidaciones_autorizadas' => '03', // Liquidación de compra
        'nc_autorizadas'            => '04', // Nota de crédito
        'nd_autorizadas'            => '05', // Nota de débito
        'guias_autorizadas'         => '06', // Guía de remisión
        'retenciones_autorizadas'   => '07', // Comprobante de retención
    ];

    private string $host;
    private string $user;
    private string $pass;
    private int $port;
    private int $timeout;

    /** @var resource|\FTP\Connection|null */
    private $conn = null;

    public function __construct(string $host, string $user, string $pass, int $port = 21, int $timeout = 20)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    public function conectar(): void
    {
        $this->conn = @ftp_connect($this->host, $this->port, $this->timeout);
        if (!$this->conn) {
            throw new Exception("No se pudo conectar al servidor FTP {$this->host}:{$this->port}.");
        }
        if (!@ftp_login($this->conn, $this->user, $this->pass)) {
            @ftp_close($this->conn);
            $this->conn = null;
            throw new Exception('Login FTP fallido (usuario o contraseña).');
        }
        @ftp_pasv($this->conn, true);
    }

    public function cerrar(): void
    {
        if ($this->conn) {
            @ftp_close($this->conn);
            $this->conn = null;
        }
    }

    /**
     * Lista recursivamente todos los .xml bajo una ruta base (hasta $maxDepth niveles).
     * Devuelve rutas absolutas en el servidor.
     *
     * @return string[]
     */
    public function listarXml(string $rutaBase, int $maxDepth = 3): array
    {
        $this->asegurarConexion();
        $resultado = [];
        $this->listarXmlRec($rutaBase, $maxDepth, $resultado);
        sort($resultado);
        return $resultado;
    }

    private function listarXmlRec(string $ruta, int $depth, array &$acc): void
    {
        $items = @ftp_nlist($this->conn, $ruta);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            // ftp_nlist puede devolver ruta absoluta o solo el nombre
            $full = (strpos($item, '/') === 0) ? $item : rtrim($ruta, '/') . '/' . $item;
            $base = basename($full);
            if ($base === '.' || $base === '..') {
                continue;
            }
            if (preg_match('/\.xml$/i', $base)) {
                $acc[] = $full;
                continue;
            }
            // Si no es .xml y aún hay profundidad, asumir carpeta y descender.
            if ($depth > 0 && !preg_match('/\.[a-z0-9]{2,4}$/i', $base)) {
                $this->listarXmlRec($full, $depth - 1, $acc);
            }
        }
    }

    /**
     * Escaneo RÁPIDO (Fase 1): enumera los XML de las carpetas de emitidos SIN descargarlos.
     * Deriva cod_doc/estab/pto/secuencial del nombre del archivo. La clave de acceso,
     * fecha y total se obtienen recién al importar (Fase 2).
     *
     * @param string   $rucEmpresa
     * @param string[] $codDocs  codDoc a incluir; vacío = todos los emitidos
     * @return array<int,array<string,mixed>>  provisional: archivo, carpeta, cod_doc, estab, pto, secuencial
     */
    public function listarManifiestoRapido(string $rucEmpresa, array $codDocs = []): array
    {
        $this->asegurarConexion();

        $carpetas = self::CARPETAS_EMITIDOS;
        if (!empty($codDocs)) {
            $carpetas = array_filter($carpetas, fn($cd) => in_array($cd, $codDocs, true));
        }

        $items = [];
        foreach ($carpetas as $carpeta => $codDoc) {
            $base = "/ftp_documentos/{$carpeta}/{$rucEmpresa}";
            foreach ($this->listarXml($base) as $ruta) {
                $items[] = self::parsearNombre($ruta, $carpeta);
            }
        }
        return $items;
    }

    /**
     * Lista las carpetas-RUC existentes bajo una carpeta de tipo que comparten
     * el RUC base (primeros 10 dígitos). Devuelve los nombres (RUC de 13 dígitos).
     * @return string[]
     */
    public function listarFoldersRucBase(string $carpeta, string $rucBase10): array
    {
        $this->asegurarConexion();
        $items = @ftp_nlist($this->conn, "/ftp_documentos/{$carpeta}");
        if ($items === false) {
            return [];
        }
        $res = [];
        foreach ($items as $it) {
            $b = basename($it);
            if (strpos($b, $rucBase10) === 0 && preg_match('/^\d{11,13}$/', $b)) {
                $res[] = $b;
            }
        }
        sort($res);
        return array_values(array_unique($res));
    }

    /**
     * Escaneo RÁPIDO por EMPRESA: recorre todas las carpetas de establecimiento del
     * RUC base que correspondan a esta empresa (según la regla matriz/establecimiento).
     *
     * @param string   $rucBase10        primeros 10 dígitos del RUC
     * @param string   $selfEst          establecimiento (3 díg.) de la empresa seleccionada
     * @param string[] $estReclamadosOtros  establecimientos que pertenecen a OTRAS empresas del mismo base
     * @param bool     $esMatriz         true si esta empresa absorbe los establecimientos sin dueño
     * @param string[] $codDocs
     * @return array<int,array<string,mixed>>
     */
    public function listarManifiestoRapidoEmpresa(
        string $rucBase10,
        string $selfEst,
        array $estReclamadosOtros,
        bool $esMatriz,
        array $codDocs = []
    ): array {
        $this->asegurarConexion();

        $carpetas = self::CARPETAS_EMITIDOS;
        if (!empty($codDocs)) {
            $carpetas = array_filter($carpetas, fn($cd) => in_array($cd, $codDocs, true));
        }

        $items = [];
        foreach ($carpetas as $carpeta => $codDoc) {
            foreach ($this->listarFoldersRucBase($carpeta, $rucBase10) as $folder) {
                $folderEst = substr($folder, 10, 3);
                $incluir = ($folderEst === $selfEst)
                        || ($esMatriz && !in_array($folderEst, $estReclamadosOtros, true));
                if (!$incluir) {
                    continue;
                }
                foreach ($this->listarXml("/ftp_documentos/{$carpeta}/{$folder}") as $ruta) {
                    $items[] = self::parsearNombre($ruta, $carpeta);
                }
            }
        }
        return $items;
    }

    /** Deriva metadatos del nombre `ABBR{estab}-{pto}-{secuencial}.xml`. */
    public static function parsearNombre(string $ruta, string $carpeta): array
    {
        $base = basename($ruta);
        $estab = $pto = $secuencial = null;
        if (preg_match('/(\d{3})-(\d{3})-(\d{1,15})\.xml$/i', $base, $m)) {
            $estab = $m[1];
            $pto = $m[2];
            $secuencial = $m[3];
        }
        return [
            'archivo'    => $ruta,
            'carpeta'    => $carpeta,
            'cod_doc'    => self::CARPETAS_EMITIDOS[$carpeta] ?? null,
            'estab'      => $estab,
            'pto'        => $pto,
            'secuencial' => $secuencial,
        ];
    }

    /** Baja un archivo del FTP a un string en memoria. */
    public function obtenerContenido(string $rutaServidor): ?string
    {
        $this->asegurarConexion();
        $tmp = tempnam(sys_get_temp_dir(), 'impxml_');
        if ($tmp === false) {
            return null;
        }
        try {
            if (!@ftp_get($this->conn, $tmp, $rutaServidor, FTP_BINARY)) {
                return null;
            }
            $data = @file_get_contents($tmp);
            return $data === false ? null : $data;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Escanea las carpetas de comprobantes emitidos de un RUC de empresa y arma el manifiesto.
     * Lee la cabecera (infoTributaria/infoFactura) de cada XML para clasificar.
     *
     * @param string   $rucEmpresa  RUC de la empresa (carpeta origen)
     * @param string[] $codDocs     codDoc a incluir; vacío = todos los emitidos
     * @param callable|null $progreso  fn(int $procesados, int $total): void
     * @return array<int,array<string,mixed>>  Manifiesto: una entrada por XML
     */
    public function escanearEmpresa(string $rucEmpresa, array $codDocs = [], ?callable $progreso = null): array
    {
        $this->asegurarConexion();

        // Carpetas a recorrer según codDoc solicitados
        $carpetas = self::CARPETAS_EMITIDOS;
        if (!empty($codDocs)) {
            $carpetas = array_filter($carpetas, fn($cd) => in_array($cd, $codDocs, true));
        }

        // Recolectar todas las rutas de XML primero (para conocer el total)
        $archivos = []; // [ruta => carpeta]
        foreach ($carpetas as $carpeta => $codDoc) {
            $base = "/ftp_documentos/{$carpeta}/{$rucEmpresa}";
            foreach ($this->listarXml($base) as $ruta) {
                $archivos[$ruta] = $carpeta;
            }
        }

        $total = count($archivos);
        $manifiesto = [];
        $i = 0;
        foreach ($archivos as $ruta => $carpeta) {
            $i++;
            $contenido = $this->obtenerContenido($ruta);
            $entrada = $this->parsearCabecera($contenido, $ruta, $carpeta, $rucEmpresa);
            $manifiesto[] = $entrada;
            if ($progreso) {
                $progreso($i, $total);
            }
        }
        return $manifiesto;
    }

    /** Extrae la cabecera mínima de un XML de comprobante para el manifiesto. */
    public function parsearCabecera(?string $contenido, string $ruta, string $carpeta, string $rucEmpresa): array
    {
        $entrada = [
            'archivo'       => $ruta,
            'carpeta'       => $carpeta,
            'cod_doc'       => self::CARPETAS_EMITIDOS[$carpeta] ?? null,
            'clave_acceso'  => null,
            'ruc_emisor'    => null,
            'secuencial'    => null,
            'fecha_emision' => null,
            'total'         => null,
            'es_emitido'    => null,
            'error'         => null,
        ];

        if ($contenido === null || trim($contenido) === '') {
            $entrada['error'] = 'No se pudo leer el archivo';
            return $entrada;
        }

        // Quitar BOM
        if (strpos($contenido, "\xEF\xBB\xBF") === 0) {
            $contenido = substr($contenido, 3);
        }

        $xml = @simplexml_load_string($contenido);
        if ($xml === false || !isset($xml->infoTributaria)) {
            $entrada['error'] = 'XML sin infoTributaria';
            return $entrada;
        }

        $it = $xml->infoTributaria;
        $entrada['clave_acceso'] = (string)($it->claveAcceso ?? '') ?: null;
        $entrada['ruc_emisor']   = (string)($it->ruc ?? '') ?: null;
        $entrada['secuencial']   = (string)($it->secuencial ?? '') ?: null;
        $codDocXml = (string)($it->codDoc ?? '');
        if ($codDocXml !== '') {
            $entrada['cod_doc'] = $codDocXml;
        }
        $entrada['es_emitido'] = ($entrada['ruc_emisor'] === $rucEmpresa);

        // fechaEmision y total según el nodo de info del tipo
        $info = $xml->infoFactura ?? $xml->infoNotaCredito ?? $xml->infoNotaDebito
              ?? $xml->infoLiquidacionCompra ?? $xml->infoCompRetencion ?? $xml->infoGuiaRemision ?? null;
        if ($info !== null) {
            $fecha = (string)($info->fechaEmision ?? '');
            $entrada['fecha_emision'] = self::normalizarFecha($fecha);
            $totalRaw = $info->importeTotal ?? $info->valorTotal ?? $info->valorModificacion ?? null;
            if ($totalRaw !== null) {
                $entrada['total'] = (float)$totalRaw;
            }
        }

        return $entrada;
    }

    /**
     * Envuelve el comprobante firmado "pelado" en el sobre <autorizacion> del SRI,
     * sintetizando numeroAutorizacion (=claveAcceso) y fechaAutorizacion (=SigningTime).
     * Replica la lógica del sistema viejo para que detalle_xml guarde el sobre íntegro.
     */
    public static function envolverAutorizacion(string $xmlPelado): string
    {
        if (strpos($xmlPelado, "\xEF\xBB\xBF") === 0) {
            $xmlPelado = substr($xmlPelado, 3);
        }
        $meta = self::extraerAutorizacion($xmlPelado);

        $e  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n<autorizacion>\n";
        $e .= "  <estado>AUTORIZADO</estado>\n";
        $e .= "  <numeroAutorizacion>" . htmlspecialchars($meta['numeroAutorizacion'], ENT_QUOTES, 'UTF-8') . "</numeroAutorizacion>\n";
        $e .= "  <fechaAutorizacion>" . htmlspecialchars($meta['fechaAutorizacion'], ENT_QUOTES, 'UTF-8') . "</fechaAutorizacion>\n";
        $e .= "  <ambiente>" . htmlspecialchars($meta['ambienteTexto'], ENT_QUOTES, 'UTF-8') . "</ambiente>\n";
        $e .= "  <comprobante><![CDATA[" . $xmlPelado . "]]></comprobante>\n";
        $e .= "  <mensajes/>\n</autorizacion>";
        return $e;
    }

    /** Extrae numeroAutorizacion(=claveAcceso), fechaAutorizacion(=SigningTime) y ambiente del XML pelado. */
    public static function extraerAutorizacion(string $xmlPelado): array
    {
        $numeroAutorizacion = null;
        $fechaAutorizacion  = null;
        $ambienteTexto      = 'PRODUCCIÓN';

        $dom = new \DOMDocument();
        if (@$dom->loadXML($xmlPelado)) {
            $c = $dom->getElementsByTagName('claveAcceso');
            if ($c->length > 0) {
                $numeroAutorizacion = trim($c->item(0)->textContent);
            }
            $a = $dom->getElementsByTagName('ambiente');
            if ($a->length > 0) {
                $ambienteTexto = (trim($a->item(0)->textContent) === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
            }
            $xp = new \DOMXPath($dom);
            $xp->registerNamespace('etsi', 'http://uri.etsi.org/01903/v1.3.2#');
            $st = $xp->query('//etsi:SigningTime');
            if ($st && $st->length > 0) {
                try {
                    $dt = new \DateTime(trim($st->item(0)->textContent));
                    $dt->setTimezone(new \DateTimeZone('America/Guayaquil'));
                    $fechaAutorizacion = $dt->format('Y-m-d\TH:i:sP');
                } catch (\Exception $e) {
                }
            }
        }
        if (!$numeroAutorizacion) {
            $numeroAutorizacion = substr(hash('sha256', $xmlPelado), 0, 49);
        }
        if (!$fechaAutorizacion) {
            $n = new \DateTime('now', new \DateTimeZone('America/Guayaquil'));
            $fechaAutorizacion = $n->format('Y-m-d\TH:i:sP');
        }
        return compact('numeroAutorizacion', 'fechaAutorizacion', 'ambienteTexto');
    }

    /** dd/mm/yyyy -> Y-m-d (o null si no reconoce). */
    public static function normalizarFecha(string $fecha): ?string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $fecha, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $fecha, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return null;
    }

    private function asegurarConexion(): void
    {
        if (!$this->conn) {
            $this->conectar();
        }
    }
}
