<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\SriDescargaAutoLog;
use App\repositories\modulos\DocumentoEntreEmpresasRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Services\LogSistemaService;
use App\Services\SriService;
use SimpleXMLElement;

/**
 * Comprobantes emitidos entre empresas del sistema.
 *
 * Cuando una empresa del sistema emite un comprobante y el SRI lo AUTORIZA en
 * producción, si el receptor (comprador, sujeto retenido o proveedor de la
 * liquidación) es otra empresa del sistema, el comprobante se registra solo en la
 * receptora —como si lo hubiera bajado de Descargas SRI—, sin esperar a que lo
 * descargue del portal.
 *
 * Dos tiempos, a propósito:
 *  1. encolarSiCorresponde(): lo llama SriEnvioService al autorizar. Solo lee el XML ya
 *     guardado, identifica al receptor y deja una fila en la cola. Barato y blindado
 *     (try/catch total): nunca suma tiempo al envío ni puede afectar la factura emitida.
 *  2. procesarPendientes(): lo llama el cron cada minuto. Registra cada comprobante en la
 *     receptora con el MISMO registrador de Descargas SRI
 *     (DocumentoAutomatedRegisterService::procesarYRegistrar): lista de ignorados,
 *     periodo cerrado, duplicados, retención y egreso automáticos, aprobación de compras.
 *     Si luego la receptora descarga el mismo documento del SRI, queda como "Ya estaba
 *     registrado".
 */
class DocumentoEntreEmpresasService
{
    /** Comprobantes por tick del cron. */
    private const LOTE = 20;

    /**
     * Intentos de un comprobante: el primero y UN reintento a los 30 min (ver
     * getPendientes). Si vuelve a fallar queda en 'error' y no se insiste más, para no
     * cargar al cron con documentos que no van a entrar (periodo cerrado, XML
     * inválido…); la receptora siempre puede bajarlo desde Descargas SRI.
     */
    private const MAX_INTENTOS = 2;

    /**
     * Tope de tiempo por tick. El cron es un proceso en segundo plano (no lo espera
     * ningún usuario), pero es UNO solo y corre las tareas en fila: este paso no debe
     * retrasar a las que siguen. Lo que no alcanza queda pendiente para el próximo tick.
     */
    private const TIEMPO_MAXIMO_TICK = 30;

    private float $limiteTick = 0.0;

    /** Mismo namespace del candado por RUC que usa SriDescargaAutomaticaService::registrarXmls(). */
    private const LOCK_NS_DESCARGA = 815;

    /** codDoc soportados por el registrador (la guía de remisión, 06, no). */
    private const CODDOC_SOPORTADOS = ['01', '03', '04', '05', '07'];

    private DocumentoEntreEmpresasRepository $repo;

    public function __construct()
    {
        $this->repo = new DocumentoEntreEmpresasRepository();
    }

    // ── 1. Al autorizar (empresa emisora) ──────────────────────────────────────

    /**
     * Encola el comprobante recién autorizado si su receptor es otra empresa del
     * sistema con la recepción activada. Nunca lanza.
     */
    public function encolarSiCorresponde(string $tipoComprobante, int $idDocumento, int $idEmpresaEmisora, int $idUsuario): void
    {
        // Si el llamador tiene una transacción abierta, un error aquí la abortaría (25P02)
        // y tumbaría lo que viene después: se aísla en un SAVEPOINT.
        $db = $this->repo->getDb();
        $sp = $db->inTransaction();
        try {
            if ($sp) {
                $db->exec('SAVEPOINT entre_empresas_encolar');
            }
            $this->encolar($tipoComprobante, $idDocumento, $idEmpresaEmisora, $idUsuario);
            if ($sp) {
                $db->exec('RELEASE SAVEPOINT entre_empresas_encolar');
            }
        } catch (\Throwable $e) {
            if ($sp) {
                try { $db->exec('ROLLBACK TO SAVEPOINT entre_empresas_encolar'); } catch (\Throwable) {}
            }
            error_log("[Entre empresas] No se pudo encolar {$tipoComprobante} #{$idDocumento}: " . $e->getMessage());
        }
    }

    private function encolar(string $tipoComprobante, int $idDocumento, int $idEmpresaEmisora, int $idUsuario): void
    {
        $tabla = DocumentoEntreEmpresasRepository::TABLAS_ORIGEN[$tipoComprobante] ?? null;
        if ($tabla === null || !$this->repo->disponible()) {
            return;
        }

        $doc = $this->repo->getDocumentoOrigen($tabla, $idDocumento, $idEmpresaEmisora);
        if (!$doc) {
            return;
        }
        $clave = trim((string) ($doc['clave_acceso'] ?? ''));
        // Solo producción (posición 24 de la clave): un comprobante de pruebas no
        // tiene validez tributaria y llenaría de compras falsas a la receptora.
        if (strlen($clave) !== 49 || substr($clave, 23, 1) !== '2') {
            return;
        }

        $comprobante = self::comprobanteDesdeSobre((string) ($doc['detalle_xml'] ?? ''));
        if ($comprobante === null) {
            error_log("[Entre empresas] {$tipoComprobante} #{$idDocumento}: sin XML autorizado guardado; no se encola.");
            return;
        }

        $codDoc = (string) $comprobante->infoTributaria->codDoc;
        if (!in_array($codDoc, self::CODDOC_SOPORTADOS, true)) {
            return;
        }

        $rucEmisor = self::soloDigitos((string) $this->repo->getRucEmpresa($idEmpresaEmisora));
        $candidatos = self::rucsCandidatos(self::identificacionReceptor($comprobante, $codDoc));
        // Autoconsumo (mismo RUC que el emisor): no es una compra de otra empresa.
        $candidatos = array_values(array_filter($candidatos, static fn($r) => $r !== $rucEmisor));
        if (!$candidatos) {
            return;
        }

        $receptora = $this->repo->buscarEmpresaReceptora($candidatos);
        if ($receptora === null || !$receptora['recibir'] || $receptora['id'] === $idEmpresaEmisora) {
            return;
        }

        $it = $comprobante->infoTributaria;
        $this->repo->encolar([
            'id_empresa'         => $receptora['id'],
            'id_empresa_emisora' => $idEmpresaEmisora,
            'tipo_comprobante'   => $tipoComprobante,
            'tabla_origen'       => $tabla,
            'id_origen'          => $idDocumento,
            'clave_acceso'       => $clave,
            'numero_documento'   => (string) $it->estab . '-' . (string) $it->ptoEmi . '-' . (string) $it->secuencial,
            'created_by'         => $idUsuario,
        ]);
    }

    // ── 2. Cron (empresa receptora) ────────────────────────────────────────────

    /**
     * Registra en cada empresa receptora los comprobantes encolados.
     *
     * @return array{procesados:int, registrados:int, existentes:int, ignorados:int, errores:int}
     */
    public function procesarPendientes(): array
    {
        $tot = ['procesados' => 0, 'registrados' => 0, 'existentes' => 0, 'ignorados' => 0, 'errores' => 0];
        if (!$this->repo->disponible()) {
            return $tot;
        }

        $this->limiteTick = microtime(true) + self::TIEMPO_MAXIMO_TICK;
        $porEmpresa = [];
        foreach ($this->repo->getPendientes(self::LOTE, self::MAX_INTENTOS) as $fila) {
            $porEmpresa[(int) $fila['id_empresa']][] = $fila;
        }

        foreach ($porEmpresa as $idEmpresa => $filas) {
            if (microtime(true) >= $this->limiteTick) {
                break;
            }
            $parcial = $this->procesarEmpresa($idEmpresa, (string) $filas[0]['ruc_receptora'], $filas);
            foreach ($parcial as $k => $v) {
                $tot[$k] += $v;
            }
        }
        return $tot;
    }

    private function procesarEmpresa(int $idEmpresa, string $ruc, array $filas): array
    {
        $tot = ['procesados' => 0, 'registrados' => 0, 'existentes' => 0, 'ignorados' => 0, 'errores' => 0];
        $db  = $this->repo->getDb();

        // Mismo candado por RUC que la descarga del SRI: si la receptora está bajando
        // comprobantes en este momento, se espera al siguiente tick en vez de competir.
        $stLock = $db->prepare('SELECT pg_try_advisory_lock(?, hashtext(?))');
        $stLock->execute([self::LOCK_NS_DESCARGA, $ruc]);
        if (!$stLock->fetchColumn()) {
            return $tot;
        }

        $inicio    = microtime(true);
        $detalles  = [];
        $register  = new DocumentoAutomatedRegisterService();
        $sri       = null;
        $log       = new LogSistemaService();

        try {
            $recibir = $this->repo->getRecibir($idEmpresa);
            // created_by tiene FK a usuarios: se registra a nombre de un usuario de la
            // receptora; si no tiene ninguno asignado, del que emitió el documento.
            $idUsuarioReceptora = $this->repo->getUsuarioResponsable($idEmpresa);

            foreach ($filas as $fila) {
                if (microtime(true) >= $this->limiteTick) {
                    break; // el resto sigue 'pendiente' para el próximo tick
                }
                $idCola = (int) $fila['id'];
                $clave  = (string) $fila['clave_acceso'];
                $tot['procesados']++;

                if (!$recibir) {
                    $this->repo->marcarResultado($idCola, 'ignorado', 'La empresa desactivó la recepción de documentos de empresas del sistema.', null);
                    $tot['ignorados']++;
                    continue;
                }

                try {
                    $doc = $this->repo->getDocumentoOrigen((string) $fila['tabla_origen'], (int) $fila['id_origen'], (int) $fila['id_empresa_emisora']);
                    $xml = (string) ($doc['detalle_xml'] ?? '');
                    if (self::comprobanteDesdeSobre($xml) === null) {
                        // Respaldo: el webservice de autorización del SRI.
                        $sri ??= new SriService();
                        $resp = $sri->obtenerComprobanteXml($clave);
                        $xml  = !empty($resp['ok']) ? (string) ($resp['xml'] ?? '') : '';
                    }
                    if ($xml === '') {
                        throw new \RuntimeException('No se encontró el XML autorizado del comprobante.');
                    }

                    $idUsuario = $idUsuarioReceptora ?? (int) $fila['created_by'];
                    if ($idUsuario <= 0) {
                        throw new \RuntimeException('La empresa no tiene usuarios asignados a cuyo nombre registrar el documento.');
                    }
                    $res    = $register->procesarYRegistrar($xml, $idEmpresa, $idUsuario);
                    $estReg = (string) ($res['estado_registro'] ?? 'ERROR');
                    $msg    = (string) ($res['mensaje'] ?? $res['error'] ?? '');
                    $estado = match ($estReg) {
                        'REGISTRADO'                  => 'registrado',
                        'YA ESTABA REGISTRADO'        => 'existente',
                        'IGNORADO',
                        'EMITIDO A OTRO CONTRIBUYENTE' => 'ignorado',
                        default                       => 'error',
                    };
                    $idRegistro = isset($res['id']) ? (int) $res['id'] : null;
                    $this->repo->marcarResultado($idCola, $estado, $msg, $idRegistro);

                    $tot[match ($estado) {
                        'registrado' => 'registrados',
                        'existente'  => 'existentes',
                        'ignorado'   => 'ignorados',
                        default      => 'errores',
                    }]++;

                    if ($estado === 'registrado') {
                        try {
                            $log->registrar($idUsuario, $idEmpresa, 'registro_documento_entre_empresas', 'sri_documentos_entre_empresas', $idCola, null, [
                                'clave_acceso'       => $clave,
                                'numero_documento'   => $fila['numero_documento'],
                                'id_empresa_emisora' => (int) $fila['id_empresa_emisora'],
                                'id_registro'        => $idRegistro,
                                'mensaje'            => $msg,
                            ]);
                        } catch (\Throwable) {}
                    }

                    $detalles[] = [
                        'clave'  => $clave,
                        'estado' => $estReg,
                        'msg'    => $msg,
                        'numero' => $res['numero_documento'] ?? ($fila['numero_documento'] ?? ''),
                        'tipo'   => $res['tipo_nombre'] ?? '',
                        'emisor' => $res['emisor'] ?? '',
                        'total'  => $res['total'] ?? null,
                    ];
                } catch (\Throwable $e) {
                    $this->repo->marcarResultado($idCola, 'error', $e->getMessage(), null);
                    $tot['errores']++;
                    $detalles[] = ['clave' => $clave, 'estado' => 'EXCEPCION', 'msg' => $e->getMessage()];
                }
            }

            // Un solo correo con las compras que quedaron pendientes de aprobación.
            $register->notificarComprasPendientes();

            if ($detalles) {
                $hoy = new \DateTime();
                (new SriDescargaAutoLog())->insertar([
                    'id_empresa'        => $idEmpresa,
                    'fecha_desde'       => $hoy->format('Y-m-d'),
                    'fecha_hasta'       => $hoy->format('Y-m-d'),
                    'tipos_documento'   => 'todos',
                    'total_encontrados' => count($detalles),
                    'total_nuevos'      => $tot['registrados'],
                    'total_existentes'  => $tot['existentes'],
                    'total_ignorados'   => $tot['ignorados'],
                    'total_errores'     => $tot['errores'],
                    'estado'            => 'completado',
                    'detalle_json'      => json_encode(['claves' => $detalles], JSON_UNESCAPED_UNICODE),
                    'duracion_seg'      => (int) (microtime(true) - $inicio),
                    'origen'            => 'empresa_sistema',
                    'created_by'        => $idUsuarioReceptora ?? 0,
                ]);
            }
        } finally {
            $db->prepare('SELECT pg_advisory_unlock(?, hashtext(?))')->execute([self::LOCK_NS_DESCARGA, $ruc]);
        }

        return $tot;
    }

    // ── Configuración (pantalla Descargas SRI) ─────────────────────────────────

    /** @return array{disponible:bool, recibir:bool, resumen:array<string,int>} */
    public function getConfig(int $idEmpresa): array
    {
        if (!$this->repo->disponible()) {
            return ['disponible' => false, 'recibir' => false, 'resumen' => []];
        }
        return [
            'disponible' => true,
            'recibir'    => $this->repo->getRecibir($idEmpresa),
            'resumen'    => $this->repo->getResumenRecibidos($idEmpresa),
        ];
    }

    /**
     * Activa o desactiva la recepción. Se aplica a TODAS las empresas del mismo RUC:
     * el comprobante se registra en la primera del RUC, así que la opción tiene que
     * valer igual desde cualquiera de sus establecimientos.
     */
    public function guardarRecibir(int $idEmpresa, bool $recibir, int $idUsuario): void
    {
        if (!$this->repo->disponible()) {
            throw new \RuntimeException('Falta aplicar database/2026-10-29_sri_documentos_entre_empresas.sql.');
        }
        $ids = (new EmpresaRepository())->getIdsEmpresaMismoRuc($idEmpresa);
        $log = new LogSistemaService();

        $this->repo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $antes = $this->repo->getConfig($id);
                $this->repo->guardarRecibir($id, $recibir, $idUsuario);
                $log->registrar($idUsuario, $id, 'actualizar', 'sri_recepcion_entre_empresas_config',
                    $antes ? (int) $antes['id'] : null,
                    $antes ? ['recibir' => (bool) $antes['recibir']] : null,
                    ['recibir' => $recibir]);
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Lectura del XML ────────────────────────────────────────────────────────

    /** Comprobante (factura, NC…) desde el sobre de autorización o el comprobante suelto. */
    private static function comprobanteDesdeSobre(string $xml): ?SimpleXMLElement
    {
        $xml = trim($xml, "\xEF\xBB\xBF \t\n\r\0\x0B");
        if ($xml === '' || strpos($xml, '<') === false) {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        try {
            $raiz = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
            if ($raiz === false) {
                return null;
            }
            $interno = null;
            if (isset($raiz->autorizacion->comprobante)) {
                $interno = (string) $raiz->autorizacion->comprobante;
            } elseif (isset($raiz->comprobante)) {
                $interno = (string) $raiz->comprobante;
            }
            if ($interno !== null) {
                $raiz = simplexml_load_string(trim($interno, "\xEF\xBB\xBF \t\n\r\0\x0B"), SimpleXMLElement::class, LIBXML_NOCDATA);
                if ($raiz === false) {
                    return null;
                }
            }
            return isset($raiz->infoTributaria) ? $raiz : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    /** Identificación del receptor según el tipo, con los mismos nodos que lee el registrador. */
    private static function identificacionReceptor(SimpleXMLElement $x, string $codDoc): string
    {
        $v = match ($codDoc) {
            '01'    => $x->infoFactura->identificacionComprador ?? '',
            '03'    => $x->infoLiquidacionCompra->identificacionProveedor ?? '',
            '04'    => $x->infoNotaCredito->identificacionComprador ?? '',
            '05'    => $x->infoNotaDebito->identificacionComprador ?? '',
            '07'    => (isset($x->infoRetencion) ? $x->infoRetencion : $x->infoCompRetencion)->identificacionSujetoRetenido ?? '',
            default => '',
        };
        return self::soloDigitos((string) $v);
    }

    /**
     * RUC de empresa con el que puede coincidir la identificación: el RUC tal cual, o la
     * cédula + "001" (persona natural con RUC a quien le facturaron con cédula: el
     * registrador lo trata como gasto personal).
     */
    private static function rucsCandidatos(string $identificacion): array
    {
        return match (strlen($identificacion)) {
            13      => [$identificacion],
            10      => [$identificacion . '001'],
            default => [],
        };
    }

    private static function soloDigitos(string $v): string
    {
        return preg_replace('/[^0-9]/', '', $v) ?? '';
    }
}
