<?php

declare(strict_types=1);

namespace App\Services\ImportarAntiguo;

use App\repositories\modulos\ImportacionXmlRepository;
use App\Services\modulos\DocumentoAutomatedRegisterService;
use App\Services\Sri\SriWebserviceService;
use App\core\Database;
use SimpleXMLElement;
use Throwable;

/**
 * Orquesta la importación de comprobantes XML del "antiguo CaMaGaRe".
 *
 * Fase 1 (escanear): lista rápido (sin descargar) y arma el manifiesto en BD.
 * Fase 2 (importar): baja cada XML pendiente, lo envuelve, lo registra con el
 * motor compartido y aplica los parches (ambiente del XML + fecha_autorizacion),
 * sin tocar dicho motor.
 */
class ImportarAntiguoService
{
    private ImportacionXmlRepository $repo;

    private string $ftpHost;
    private string $ftpUser;
    private string $ftpPass;
    private int $ftpPort;

    public function __construct(
        ?ImportacionXmlRepository $repo = null,
        ?string $ftpHost = null,
        ?string $ftpUser = null,
        ?string $ftpPass = null
    ) {
        $this->repo = $repo ?? new ImportacionXmlRepository();
        $cfg = self::configFtp();
        $this->ftpHost = $ftpHost ?? $cfg['host'];
        $this->ftpUser = $ftpUser ?? $cfg['user'];
        $this->ftpPass = $ftpPass ?? $cfg['pass'];
        $this->ftpPort = $cfg['port'];
    }

    /**
     * Credenciales del servidor FTP legacy. Se leen de config/parametros.xml
     * (no versionado, por entorno): claves ftp_docs_host/user/pass/port.
     * Si no están, cae a los valores por defecto conocidos (no rompe producción).
     */
    private static function configFtp(): array
    {
        $cfg = ['host' => '64.225.69.65', 'user' => 'char', 'pass' => 'CmGr1980', 'port' => 21];
        $file = (defined('MVC_CONFIG') ? MVC_CONFIG : dirname(__DIR__, 3) . '/config') . '/parametros.xml';
        if (is_file($file)) {
            $xml = @simplexml_load_file($file);
            if ($xml !== false) {
                if (!empty($xml->ftp_docs_host)) $cfg['host'] = (string) $xml->ftp_docs_host;
                if (!empty($xml->ftp_docs_user)) $cfg['user'] = (string) $xml->ftp_docs_user;
                if (!empty($xml->ftp_docs_pass)) $cfg['pass'] = (string) $xml->ftp_docs_pass;
                if (!empty($xml->ftp_docs_port)) $cfg['port'] = (int) $xml->ftp_docs_port;
            }
        }
        return $cfg;
    }

    private function nuevoScanner(): FtpDocumentosScanner
    {
        return new FtpDocumentosScanner($this->ftpHost, $this->ftpUser, $this->ftpPass, $this->ftpPort);
    }

    /**
     * FASE 1 — Escanea las carpetas de emitidos de un RUC (rápido, sin descargar)
     * y registra el manifiesto. Dedup contra lo ya registrado.
     *
     * @param string[] $codDocs
     */
    public function escanear(int $idEmpresa, string $ruc, string $est, array $codDocs, ?int $idUsuario): array
    {
        $base = substr($ruc, 0, 10);

        // Empresas del mismo RUC base: qué establecimientos están reclamados por OTRAS,
        // y si esta empresa es la "matriz" (establecimiento más bajo) que absorbe los sin dueño.
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id, establecimiento FROM empresas WHERE LEFT(ruc,10) = ? AND eliminado = false");
        $st->execute([$base]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        $estReclamadosOtros = [];
        $ests = [];
        foreach ($rows as $r) {
            $ests[] = $r['establecimiento'];
            if ((int) $r['id'] !== $idEmpresa) {
                $estReclamadosOtros[] = $r['establecimiento'];
            }
        }
        $esMatriz = empty($ests) || $est === min($ests);

        $scanner = $this->nuevoScanner();
        $scanner->conectar();
        try {
            $items = $scanner->listarManifiestoRapidoEmpresa($base, $est, $estReclamadosOtros, $esMatriz, $codDocs);
        } finally {
            $scanner->cerrar();
        }

        $idLote = $this->repo->crearLote([
            'id_empresa'          => $idEmpresa,
            'ruc'                 => $ruc,
            'ruta_base'           => "/ftp_documentos/*/{$base}" . ($esMatriz ? '* (matriz)' : $est),
            'tipos_seleccionados' => implode(',', $codDocs),
            'created_by'          => $idUsuario,
        ]);

        $nuevos = $this->repo->insertarItemsProvisionales($idLote, $idEmpresa, $items, $idUsuario);
        $this->repo->recalcularTotalesLote($idLote);

        $detectados = count($items);
        return [
            'id_lote'         => $idLote,
            'detectados'      => $detectados,
            'nuevos'          => $nuevos,
            'ya_registrados'  => $detectados - $nuevos,
            // Estado real de la empresa por tipo (acumulado entre lotes), no solo lo nuevo.
            'resumen'         => $this->repo->getResumenEmpresa($idEmpresa, $codDocs),
        ];
    }

    /**
     * FASE 2 — Importa un bloque de ítems pendientes de la empresa.
     * Baja, envuelve, registra y parcha. Idempotente (dedup del motor + registro).
     *
     * @param string[]    $codDocs  filtro opcional por tipo
     * @param string|null $desde    filtro opcional fechaEmision (Y-m-d)
     * @param string|null $hasta    filtro opcional fechaEmision (Y-m-d)
     */
    public function importarBloque(
        int $idEmpresa,
        int $idUsuario,
        int $limite = 25,
        array $codDocs = [],
        ?string $desde = null,
        ?string $hasta = null,
        bool $verificarSri = false
    ): array {
        $pendientes = $this->repo->getPendientes($idEmpresa, $limite, $codDocs);

        $res = ['procesados' => 0, 'importados' => 0, 'duplicados' => 0, 'omitidos' => 0, 'no_autorizados' => 0, 'errores' => 0];
        if (empty($pendientes)) {
            $res['restantes'] = 0;
            return $res;
        }

        $scanner = $this->nuevoScanner();
        $scanner->conectar();
        $register = new DocumentoAutomatedRegisterService();
        $sri = $verificarSri ? new SriWebserviceService(30) : null;

        try {
            foreach ($pendientes as $item) {
                $res['procesados']++;
                $this->procesarItem($item, $idEmpresa, $idUsuario, $scanner, $register, $sri, $desde, $hasta, $res);
            }
        } finally {
            $scanner->cerrar();
        }

        $res['restantes'] = $this->repo->contarPendientes($idEmpresa, $codDocs);
        return $res;
    }

    private function procesarItem(
        array $item,
        int $idEmpresa,
        int $idUsuario,
        FtpDocumentosScanner $scanner,
        DocumentoAutomatedRegisterService $register,
        ?SriWebserviceService $sri,
        ?string $desde,
        ?string $hasta,
        array &$res
    ): void {
        $idItem = (int) $item['id'];
        try {
            $pelado = $scanner->obtenerContenido($item['archivo']);
            if ($pelado === null || trim($pelado) === '') {
                $this->repo->actualizarItem($idItem, ['estado' => 'error', 'mensaje' => 'No se pudo descargar el XML del FTP']);
                $res['errores']++;
                return;
            }

            $meta = $this->extraerMeta($pelado);

            // Filtro opcional por fecha de emisión (requiere leer el XML)
            if (($desde || $hasta) && $meta['fecha_emision']) {
                if (($desde && $meta['fecha_emision'] < $desde) || ($hasta && $meta['fecha_emision'] > $hasta)) {
                    $this->repo->actualizarItem($idItem, array_merge($meta, [
                        'estado'  => 'omitido',
                        'mensaje' => 'Fuera del rango de fechas seleccionado',
                    ]));
                    $res['omitidos']++;
                    return;
                }
            }

            // Verificación opcional del estado real en el SRI (por clave de acceso).
            if ($sri !== null && !empty($meta['clave_acceso'])) {
                $amb = substr((string) $meta['clave_acceso'], 23, 1) ?: ($meta['ambiente'] ?? '2');
                $cons = $sri->consultarAutorizacion($meta['clave_acceso'], $amb);
                $meta['sri_estado'] = $cons['estado'] ?? 'ERROR';
                if (($cons['estado'] ?? '') !== 'AUTORIZADO') {
                    $msg = 'SRI: ' . ($cons['estado'] ?? 'ERROR');
                    if (!empty($cons['errores'][0]['mensaje'])) {
                        $msg .= ' — ' . $cons['errores'][0]['mensaje'];
                    }
                    $this->repo->actualizarItem($idItem, array_merge($meta, [
                        'estado'  => 'no_autorizado',
                        'mensaje' => $msg,
                    ]));
                    $res['no_autorizados']++;
                    return;
                }
                // Autorizado: usar la autorización REAL del SRI (más fiable que SigningTime).
                if (!empty($cons['fecha_autorizacion'])) {
                    $meta['fecha_autorizacion'] = $cons['fecha_autorizacion'];
                }
            }

            $sobre = FtpDocumentosScanner::envolverAutorizacion($pelado);
            $r = $register->procesarYRegistrar($sobre, $idEmpresa, $idUsuario);

            if (!($r['ok'] ?? false)) {
                $this->repo->actualizarItem($idItem, array_merge($meta, [
                    'estado'  => 'error',
                    'mensaje' => $r['mensaje'] ?? 'Error desconocido al registrar',
                ]));
                $res['errores']++;
                return;
            }

            $idDoc = (int) ($r['id'] ?? 0);
            $tabla = $this->tablaDestino($meta['cod_doc'], (bool) ($r['es_emitido'] ?? false));

            // Duplicado (ya existía) vs importado nuevo
            $estado = !empty($r['existe']) ? 'duplicado' : 'importado';
            if ($estado === 'duplicado') {
                $res['duplicados']++;
            } else {
                $res['importados']++;
                // Parche: ambiente del XML + fecha_autorizacion (solo facturas de venta).
                if ($idDoc > 0 && $tabla === 'ventas_cabecera') {
                    $this->parcharVenta($idDoc, $meta);
                }
            }

            $this->repo->actualizarItem($idItem, array_merge($meta, [
                'estado'                => $estado,
                'mensaje'               => $r['mensaje'] ?? null,
                'id_documento_generado' => $idDoc ?: null,
                'tabla_documento'       => $tabla,
            ]));
        } catch (Throwable $e) {
            $this->repo->actualizarItem($idItem, ['estado' => 'error', 'mensaje' => substr($e->getMessage(), 0, 500)]);
            $res['errores']++;
        }
    }

    /** UPDATE post-registro: ambiente real del XML y fecha_autorizacion. No toca el motor compartido. */
    private function parcharVenta(int $idVenta, array $meta): void
    {
        $db = Database::getConnection();
        $sets = [];
        $params = [':id' => $idVenta];
        if ($meta['ambiente'] !== null) {
            $sets[] = 'tipo_ambiente = :amb';
            $params[':amb'] = $meta['ambiente'];
        }
        if ($meta['fecha_autorizacion'] !== null) {
            $sets[] = 'fecha_autorizacion = :fa';
            $params[':fa'] = $meta['fecha_autorizacion'];
        }
        if (empty($sets)) {
            return;
        }
        $st = $db->prepare("UPDATE ventas_cabecera SET " . implode(', ', $sets) . " WHERE id = :id");
        $st->execute($params);
    }

    /** Extrae metadatos del comprobante pelado para el ítem y el parche. */
    private function extraerMeta(string $pelado): array
    {
        $meta = [
            'clave_acceso'         => null,
            'ruc_emisor'           => null,
            'razon_social_emisor'  => null,
            'cod_doc'              => null,
            'fecha_emision'        => null,
            'total'                => null,
            'es_emitido'           => null,
            'ambiente'             => null,
            'fecha_autorizacion'   => null,
        ];

        $xml = @simplexml_load_string($pelado);
        if ($xml === false || !isset($xml->infoTributaria)) {
            return $meta;
        }
        $it = $xml->infoTributaria;
        $meta['clave_acceso']        = (string) ($it->claveAcceso ?? '') ?: null;
        $meta['ruc_emisor']          = (string) ($it->ruc ?? '') ?: null;
        $meta['razon_social_emisor'] = (string) ($it->razonSocial ?? '') ?: null;
        $meta['cod_doc']             = (string) ($it->codDoc ?? '') ?: null;
        $meta['ambiente']            = (string) ($it->ambiente ?? '') ?: null;

        $info = $xml->infoFactura ?? $xml->infoNotaCredito ?? $xml->infoNotaDebito
              ?? $xml->infoLiquidacionCompra ?? $xml->infoCompRetencion ?? $xml->infoGuiaRemision ?? null;
        if ($info !== null) {
            $meta['fecha_emision'] = FtpDocumentosScanner::normalizarFecha((string) ($info->fechaEmision ?? ''));
            $totalRaw = $info->importeTotal ?? $info->valorTotal ?? $info->valorModificacion ?? null;
            if ($totalRaw !== null) {
                $meta['total'] = (float) $totalRaw;
            }
        }

        $aut = FtpDocumentosScanner::extraerAutorizacion($pelado);
        $meta['fecha_autorizacion'] = $aut['fechaAutorizacion'];

        return $meta;
    }

    private function tablaDestino(?string $codDoc, bool $esEmitido): ?string
    {
        if (!$esEmitido) {
            return null; // recibido: compras/retenciones compra, no aplica a esta migración de ventas
        }
        return match ($codDoc) {
            '01'    => 'ventas_cabecera',
            '04'    => 'notas_credito',
            '07'    => 'retenciones_ventas',
            default => null,
        };
    }
}
