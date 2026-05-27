<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\SriConfigDescarga;
use App\models\SriDescargaAutoLog;
use App\Services\modulos\DocumentoAutomatedRegisterService;
use Exception;

/**
 * Servicio de descarga automática de comprobantes recibidos desde el portal SRI en Línea.
 *
 * Flujo:
 *  1. Valida configuración activa y que no esté bloqueada por credenciales incorrectas.
 *  2. Llama al script Node.js (scripts/sri_scraper.js) que hace login, aplica filtros,
 *     hace clic en el ícono XML de cada fila e intercepta el XML descargado.
 *  3. Filtra por tipo de documento si la configuración lo requiere.
 *  4. Registra cada XML con DocumentoAutomatedRegisterService.
 *  5. Registra el log de la ejecución (sri_descarga_auto_log).
 *
 * Seguridad:
 *  - Credenciales cifradas con AES-256-CBC usando llave independiente de la BD.
 *  - Si el scraper reporta credenciales incorrectas, se bloquea la empresa
 *    para no volver a intentar (evita bloqueo de usuario en SRI).
 *  - Timeout configurable por empresa (default 2 minutos).
 */
class SriDescargaAutomaticaService
{
    // Timeout por defecto para Puppeteer (3 minutos — tiempo suficiente para captcha + descarga paralela)
    public const TIMEOUT_DEFAULT_MS = 180_000;

    // Mapeo codDoc (posición 8-9 de la clave) → tipo interno
    private const TIPOS_CODOC = [
        '01' => 'facturas',
        '03' => 'liquidaciones',
        '04' => 'notas_credito',
        '05' => 'notas_debito',
        '06' => 'guias',
        '07' => 'retenciones',
    ];

    // Tipo comprobante SRI — valores reales del select en el portal
    // <option value="1">Factura</option>
    // <option value="2">Liquidación de compra...</option>
    // <option value="3">Notas de Crédito</option>
    // <option value="4">Notas de Débito</option>
    // <option value="6">Comprobante de Retención</option>
    private const TIPOS_SELECT_SRI = [
        'facturas'      => '1',
        'liquidaciones' => '2',
        'notas_credito' => '3',
        'notas_debito'  => '4',
        'retenciones'   => '6',
        'todos'         => '0',
    ];

    private array $debugLog = [];

    public function __construct() { }

    // ─────────────────────────────────────────────
    // PUNTO DE ENTRADA PRINCIPAL
    // ─────────────────────────────────────────────

    public function ejecutarParaEmpresa(
        int     $idEmpresa,
        int     $idUsuario = 0,
        ?int    $anoParam  = null,
        ?int    $mesParam  = null,
        ?int    $diaParam  = null,
        ?string $tipoParam = null
    ): array {
        $inicio    = microtime(true);
        $configMod = new SriConfigDescarga();
        $logMod    = new SriDescargaAutoLog();

        $config = $configMod->getPorEmpresa($idEmpresa);
        if (!$config || $config['estado'] !== 'activo') {
            return ['ok' => false, 'error' => 'Empresa sin configuración activa de descarga automática.'];
        }

        // Bloqueo por credenciales incorrectas: no reintentar para evitar bloqueo de usuario en SRI
        if (!empty($config['login_bloqueado'])) {
            return [
                'ok'    => false,
                'error' => 'Descarga bloqueada: las credenciales SRI guardadas son incorrectas. ' .
                           'Actualice la clave en la configuración para desbloquear.',
                'login_bloqueado' => true,
            ];
        }

        $clave   = self::desencriptarClave($config['sri_clave']);
        $usuario = $config['sri_usuario'];
        // Si se pasó tipo por parámetro manual, tiene prioridad sobre la config guardada
        $tipos   = $tipoParam ?? $config['tipos_documento'];

        if (empty($clave)) {
            return ['ok' => false, 'error' => 'No se pudo desencriptar la clave SRI. Reingrese la clave.'];
        }

        // Siempre una sola llamada a Puppeteer — el portal SRI admite mes=0 (todos) y dia=0 (todos).
        // Nunca iterar períodos con múltiples procesos Node: cada uno implica login completo.
        if ($anoParam !== null) {
            // Parámetros manuales: usar exactamente lo que pidió el usuario
            $periodoUnico = [
                'ano' => $anoParam,
                'mes' => $mesParam ?? 0,   // 0 = todos los meses en el portal SRI
                'dia' => $diaParam ?? 0,
            ];
        } else {
            // Lógica automática del cron: mes actual, día 0 (todos)
            $hoy          = new \DateTime();
            $periodoUnico = [
                'ano' => (int) $hoy->format('Y'),
                'mes' => (int) $hoy->format('n'),
                'dia' => 0,
            ];
        }

        // xmlsTotales: array de ['clave' => '049...', 'xml' => '<?xml...>']
        $xmlsTotales = [];
        $detalles    = [];

        try {
            $tipoSri = $this->resolverTipoSri($tipos);

            // Verificar qué claves ya existen en BD antes de descargar
            // El scraper recibe la lista y solo descarga las que no existen
            $clavesExistentes = $this->obtenerClavesExistentes($idEmpresa);
            $this->debugLog[] = 'Claves ya existentes en BD: ' . count($clavesExistentes);

            $xmlsTotales = $this->obtenerXmlsViaPuppeteer(
                $usuario, $clave,
                $periodoUnico['ano'], $periodoUnico['mes'],
                $periodoUnico['dia'], $tipoSri,
                self::TIMEOUT_DEFAULT_MS,
                $clavesExistentes
            );
            $detalles[] = "Período {$periodoUnico['ano']}/{$periodoUnico['mes']}: " . count($xmlsTotales) . ' XMLs descargados';

            // Deduplicar por clave de acceso
            $vistos      = [];
            $xmlsUnicos  = [];
            foreach ($xmlsTotales as $item) {
                $k = $item['clave'] ?? '';
                if ($k && isset($vistos[$k])) continue;
                if ($k) $vistos[$k] = true;
                $xmlsUnicos[] = $item;
            }
            $xmlsTotales = $xmlsUnicos;

            // Filtrar por tipo si la config lo requiere y el portal no filtró
            if ($tipos !== 'todos' && $this->resolverTipoSri($tipos) === '0') {
                $tiposArr    = array_map('trim', explode(',', $tipos));
                $xmlsTotales = array_values(array_filter($xmlsTotales, function ($item) use ($tiposArr): bool {
                    $clave  = $item['clave'] ?? '';
                    if (strlen($clave) !== 49) return false;
                    $codDoc = substr($clave, 8, 2);
                    $tipo   = self::TIPOS_CODOC[$codDoc] ?? null;
                    return $tipo !== null && in_array($tipo, $tiposArr, true);
                }));
            }

        } catch (Exception $e) {
            $mensaje = $e->getMessage();

            if ($e->getCode() === 401) {
                $configMod->bloquearLogin($idEmpresa, $mensaje);
                $configMod->actualizarEstadoDescarga($idEmpresa, 'error', $mensaje);
                $this->insertarLog($logMod, $idEmpresa, $tipos, 'error', $mensaje, [], $inicio, $idUsuario);
                return ['ok' => false, 'error' => $mensaje, 'login_bloqueado' => true];
            }

            $configMod->actualizarEstadoDescarga($idEmpresa, 'error', $mensaje);
            $this->insertarLog($logMod, $idEmpresa, $tipos, 'error', $mensaje, [], $inicio, $idUsuario);
            return ['ok' => false, 'error' => $mensaje];
        }

        // Procesar cada XML directamente (ya no se necesita llamar al SOAP del SRI)
        $registerSvc = new DocumentoAutomatedRegisterService();

        $totalNuevos     = 0;
        $totalExistentes = 0;
        $totalIgnorados  = 0;
        $totalErrores    = 0;
        $detallesClaves  = [];

        foreach ($xmlsTotales as $item) {
            $claveAcceso = $item['clave'] ?? 'sin_clave';
            $xmlContent  = $item['xml']   ?? '';

            if (empty($xmlContent)) {
                $totalErrores++;
                $detallesClaves[] = ['clave' => $claveAcceso, 'estado' => 'XML_VACIO', 'msg' => 'XML vacío del scraper'];
                continue;
            }

            try {
                $res       = $registerSvc->procesarYRegistrar($xmlContent, $idEmpresa, $idUsuario);
                $estadoReg = $res['estado_registro'] ?? 'DESCONOCIDO';

                if ($estadoReg === 'REGISTRADO') {
                    $totalNuevos++;
                } elseif (in_array($estadoReg, ['YA_EXISTE', 'EXISTENTE'], true)) {
                    $totalExistentes++;
                } elseif ($estadoReg === 'IGNORADO') {
                    $totalIgnorados++;
                } else {
                    $totalErrores++;
                }

                $detallesClaves[] = ['clave' => $claveAcceso, 'estado' => $estadoReg, 'msg' => $res['mensaje'] ?? ''];

            } catch (Exception $e) {
                $totalErrores++;
                $detallesClaves[] = ['clave' => $claveAcceso, 'estado' => 'EXCEPCION', 'msg' => $e->getMessage()];
            }
        }

        $duracion = (int) (microtime(true) - $inicio);
        $msgFinal = "Descargados: " . count($xmlsTotales) .
                    " | Nuevas: $totalNuevos | Existentes: $totalExistentes" .
                    " | Ignoradas: $totalIgnorados | Errores: $totalErrores";

        $configMod->actualizarEstadoDescarga($idEmpresa, 'completado', $msgFinal);

        // Fechas del período real consultado para el log
        $mesPeriodo  = $periodoUnico['mes'] ?: (int) date('n');
        $fechaDesde  = sprintf('%04d-%02d-%02d', $periodoUnico['ano'], $mesPeriodo, $periodoUnico['dia'] ?: 1);
        $fechaHasta  = $periodoUnico['dia']
            ? $fechaDesde
            : sprintf('%04d-%02d-%02d', $periodoUnico['ano'], $mesPeriodo, (int) date('t', mktime(0,0,0,$mesPeriodo,1,$periodoUnico['ano'])));

        $logMod->insertar([
            'id_empresa'        => $idEmpresa,
            'fecha_desde'       => $fechaDesde,
            'fecha_hasta'       => $fechaHasta,
            'tipos_documento'   => $tipos,
            'total_encontrados' => count($xmlsTotales),
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'estado'            => 'completado',
            'detalle_json'      => json_encode(['resumen' => $detalles, 'claves' => $detallesClaves, 'debug' => $this->debugLog]),
            'duracion_seg'      => $duracion,
            'origen'            => $idUsuario > 0 ? 'manual' : 'cron',
            'created_by'        => $idUsuario,
        ]);

        return [
            'ok'                => true,
            'total_encontrados' => count($xmlsTotales),
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'duracion_seg'      => $duracion,
            'mensaje'           => $msgFinal,
        ];
    }

    // ─────────────────────────────────────────────
    // PUPPETEER
    // ─────────────────────────────────────────────

    /**
     * Llama al scraper Puppeteer y retorna array de ['clave'=>..., 'xml'=>...].
     * El scraper descarga directamente los XMLs desde la columna "Documento"
     * de la tabla de comprobantes recibidos (un click por fila → intercepta respuesta).
     */
    /**
     * Consulta todas las claves de acceso ya registradas en BD para esta empresa,
     * en todas las tablas que almacenan comprobantes recibidos.
     */
    private function obtenerClavesExistentes(int $idEmpresa): array
    {
        $db = \App\core\Database::getConnection();

        // Tablas y columnas donde se guarda la clave/autorización de comprobantes recibidos
        $consultas = [
            "SELECT clave_acceso        AS clave FROM ventas_cabecera            WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
            "SELECT numero_autorizacion AS clave FROM compras_cabecera            WHERE id_empresa = :id AND eliminado = false AND numero_autorizacion IS NOT NULL",
            "SELECT clave_acceso        AS clave FROM liquidaciones_cabecera      WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
            "SELECT clave_acceso        AS clave FROM retencion_compra_cabecera   WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
            "SELECT clave_acceso        AS clave FROM notas_credito_cabecera      WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
            "SELECT clave_acceso        AS clave FROM retencion_venta_cabecera    WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
            "SELECT clave_acceso        AS clave FROM notas_debito_cabecera       WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
            "SELECT clave_acceso        AS clave FROM documentos_ignorados_sri    WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL",
        ];

        $claves = [];
        foreach ($consultas as $sql) {
            try {
                $st = $db->prepare($sql);
                $st->execute([':id' => $idEmpresa]);
                foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $c) {
                    if (!empty($c)) $claves[] = $c;
                }
            } catch (\Exception $e) {
                // Tabla puede no existir aún — ignorar
                $this->debugLog[] = 'WARN obtenerClavesExistentes: ' . $e->getMessage();
            }
        }

        return array_values(array_unique($claves));
    }

    public function obtenerXmlsViaPuppeteer(
        string $usuario,
        string $clave,
        int    $ano,
        int    $mes,
        int    $dia,
        string $tipo,
        int    $timeoutMs = self::TIMEOUT_DEFAULT_MS,
        array  $clavesExcluir = []
    ): array {
        $scriptPath = MVC_ROOT . '/scripts/sri_scraper.js';
        if (!file_exists($scriptPath)) {
            throw new Exception('Script Puppeteer no encontrado. Ejecute: cd scripts && npm install');
        }

        $appCfg         = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $apiKey2captcha = getenv('TWOCAPTCHA_API_KEY') ?: ($appCfg['2captcha_api_key'] ?? '');

        $configJson = json_encode([
            'usuario'         => $usuario,
            'clave'           => $clave,
            'ano'             => $ano,
            'mes'             => $mes,
            'dia'             => $dia,
            'tipo'            => $tipo,
            'timeoutMs'       => $timeoutMs,
            'apiKey2captcha'  => $apiKey2captcha,
            'clavesExcluir'   => $clavesExcluir,
        ]);

        $cmd  = 'node ' . escapeshellarg($scriptPath);
        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($proc)) {
            throw new Exception('No se pudo iniciar Node.js. ¿Está instalado?');
        }

        fwrite($pipes[0], $configJson);
        fclose($pipes[0]);

        $phpTimeout = (int) ($timeoutMs / 1000 + 30);
        stream_set_timeout($pipes[1], $phpTimeout);
        stream_set_timeout($pipes[2], $phpTimeout);

        $output    = stream_get_contents($pipes[1]);
        $errOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $this->debugLog[] = "Puppeteer [{$ano}/{$mes}] stdout: " . substr($output ?? '', 0, 300);
        if ($errOutput) {
            $this->debugLog[] = "Puppeteer stderr: " . substr($errOutput, 0, 500);
        }

        if (empty($output)) {
            throw new Exception('El script Puppeteer no retornó salida. ¿Node.js instalado? ¿npm install ejecutado en /scripts?');
        }

        preg_match('/\{.*\}/s', $output, $m);
        if (empty($m[0])) {
            throw new Exception('Puppeteer no retornó JSON válido. Output: ' . substr($output, 0, 400));
        }

        $data = json_decode($m[0], true);

        // Credenciales incorrectas → excepción con código 401 para bloquear reintento
        if (!empty($data['credenciales_incorrectas'])) {
            throw new Exception('Credenciales SRI incorrectas: ' . ($data['error'] ?? 'sin detalle'), 401);
        }

        if (!($data['ok'] ?? false)) {
            throw new Exception('Puppeteer error: ' . ($data['error'] ?? 'desconocido'));
        }

        // Retorna array de ['clave' => '049...', 'xml' => '<?xml...>']
        return $data['xmls'] ?? [];
    }

    // ─────────────────────────────────────────────
    // VALIDACIÓN DE CLAVE DE ACCESO
    // ─────────────────────────────────────────────

    /**
     * Una clave de acceso válida tiene exactamente 49 dígitos numéricos
     * y en la posición 44 (base-0) tiene '2' (ambiente producción).
     */
    public static function validarClave(string $clave): bool
    {
        return strlen($clave) === 49
            && ctype_digit($clave)
            && $clave[44] === '2';
    }

    // ─────────────────────────────────────────────
    // HELPERS INTERNOS
    // ─────────────────────────────────────────────

    private function resolverTipoSri(string $tipos): string
    {
        if ($tipos === 'todos') return '0';
        $partes = array_map('trim', explode(',', $tipos));
        if (count($partes) === 1 && isset(self::TIPOS_SELECT_SRI[$partes[0]])) {
            return self::TIPOS_SELECT_SRI[$partes[0]];
        }
        return '0';
    }

    private function filtrarPorTipo(array $claves, array $tipos): array
    {
        return array_values(array_filter($claves, function (string $clave) use ($tipos): bool {
            $codDoc = substr($clave, 8, 2);
            $tipo   = self::TIPOS_CODOC[$codDoc] ?? null;
            return $tipo !== null && in_array($tipo, $tipos, true);
        }));
    }

    private function insertarLog(
        SriDescargaAutoLog $logMod,
        int    $idEmpresa,
        string $tipos,
        string $estado,
        string $mensaje,
        array  $detallesClaves,
        float  $inicio,
        int    $idUsuario
    ): void {
        $logMod->insertar([
            'id_empresa'        => $idEmpresa,
            'fecha_desde'       => date('Y-m-01'),
            'fecha_hasta'       => date('Y-m-d'),
            'tipos_documento'   => $tipos,
            'total_encontrados' => 0,
            'total_nuevos'      => 0,
            'total_existentes'  => 0,
            'total_ignorados'   => 0,
            'total_errores'     => 0,
            'estado'            => $estado,
            'detalle_json'      => json_encode(['error' => $mensaje, 'debug' => $this->debugLog]),
            'duracion_seg'      => (int) (microtime(true) - $inicio),
            'origen'            => $idUsuario > 0 ? 'manual' : 'cron',
            'created_by'        => $idUsuario,
        ]);
    }

    // ─────────────────────────────────────────────
    // CIFRADO DE CREDENCIALES
    // ─────────────────────────────────────────────

    public static function encriptarClave(string $clave): string
    {
        $key = self::derivarLlave();
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($clave, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function desencriptarClave(string $claveEncriptada): string
    {
        if (empty($claveEncriptada)) return '';
        $key  = self::derivarLlave();
        $data = base64_decode($claveEncriptada);
        if (strlen($data) <= 16) return '';
        $iv  = substr($data, 0, 16);
        $enc = substr($data, 16);
        $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec !== false ? $dec : '';
    }

    /**
     * Llave AES independiente de la contraseña de BD.
     * Lee SRI_ENCRYPTION_KEY del entorno; si no existe usa APP_KEY de config/app.php.
     * Así cambiar la contraseña de BD no invalida las claves guardadas.
     */
    private static function derivarLlave(): string
    {
        // 1. Variable de entorno (más segura, ideal para producción)
        $envKey = getenv('SRI_ENCRYPTION_KEY');
        if (!empty($envKey)) {
            return substr(hash('sha256', $envKey, true), 0, 32);
        }

        // 2. APP_KEY de config/app.php (estable entre deploys)
        $appCfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $appKey = $appCfg['key'] ?? $appCfg['app_key'] ?? '';
        if (!empty($appKey)) {
            return substr(hash('sha256', 'sri_cred_' . $appKey, true), 0, 32);
        }

        // 3. Fallback: salt fijo en código (peor opción, solo si las anteriores no están)
        return substr(hash('sha256', 'CaMaGaRe_SRI_DescargaAuto_v2', true), 0, 32);
    }

    public function getDebugLog(): array { return $this->debugLog; }
}
