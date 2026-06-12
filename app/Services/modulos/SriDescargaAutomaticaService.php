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
    public const TIMEOUT_DEFAULT_MS = 600000;

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
        $esManual = ($idUsuario > 0 || $anoParam !== null);
        
        if (!$config) {
            return ['ok' => false, 'error' => 'Empresa sin configuración de descargas guardada. Por favor guarde la configuración primero.'];
        }
        
        if (!$esManual && $config['estado'] !== 'activo') {
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

            $respuestaPuppeteer = $this->obtenerXmlsViaPuppeteer(
                $usuario, $clave,
                $periodoUnico['ano'], $periodoUnico['mes'],
                $periodoUnico['dia'], $tipoSri,
                self::TIMEOUT_DEFAULT_MS,
                $clavesExistentes
            );
            $xmlsTotales = $respuestaPuppeteer['xmls'] ?? [];
            $yaExistentesReportados = $respuestaPuppeteer['ya_existentes'] ?? 0;
            
            $detalles[] = "Período {$periodoUnico['ano']}/{$periodoUnico['mes']}: " . count($xmlsTotales) . ' XMLs descargados, ' . $yaExistentesReportados . ' ya existían.';

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
        $totalExistentes = $yaExistentesReportados ?? 0; // Iniciar con los ya existentes reportados por el scraper
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
        $totalEncontrados = count($xmlsTotales) + ($yaExistentesReportados ?? 0);
        
        $msgFinal = "Descargados: " . count($xmlsTotales) .
                    " | Nuevas: $totalNuevos | Existentes: $totalExistentes" .
                    " | Ignoradas: $totalIgnorados | Errores: $totalErrores";

        $estadoProceso = 'completado';
        $finalOk = true;

        if (isset($respuestaPuppeteer['ok']) && $respuestaPuppeteer['ok'] === false) {
            $msgFinal = 'Descarga parcial finalizada por error: ' . ($respuestaPuppeteer['error'] ?? 'desconocido') . '. ' . $msgFinal;
            $estadoProceso = 'error';
            $finalOk = false;
        }

        $configMod->actualizarEstadoDescarga($idEmpresa, $estadoProceso, $msgFinal);

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
            'total_encontrados' => $totalEncontrados,
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'estado'            => $estadoProceso,
            'detalle_json'      => json_encode(['resumen' => $detalles, 'claves' => $detallesClaves, 'debug' => $this->debugLog]),
            'duracion_seg'      => $duracion,
            'origen'            => $idUsuario > 0 ? 'manual' : 'cron',
            'created_by'        => $idUsuario,
        ]);

        return [
            'ok'                => $finalOk,
            'total_encontrados' => $totalEncontrados,
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'duracion_seg'      => $duracion,
            'mensaje'           => $msgFinal,
        ];
    }

    // ─────────────────────────────────────────────
    // STREAMING (NUEVO)
    // ─────────────────────────────────────────────

    public function ejecutarParaEmpresaStream(
        int     $idEmpresa,
        int     $idUsuario = 0,
        ?int    $anoParam  = null,
        ?int    $mesParam  = null,
        ?int    $diaParam  = null,
        ?string $tipoParam = null
    ): void {
        $inicio    = microtime(true);
        $configMod = new SriConfigDescarga();
        $logMod    = new SriDescargaAutoLog();

        $config = $configMod->getPorEmpresa($idEmpresa);
        if (!$config) {
            echo json_encode(['type' => 'error', 'error' => 'Empresa sin configuración de descargas guardada.']) . "\n";
            return;
        }

        if (!empty($config['login_bloqueado'])) {
            echo json_encode(['type' => 'error', 'error' => 'Descarga bloqueada: credenciales SRI incorrectas.']) . "\n";
            return;
        }

        $clave   = self::desencriptarClave($config['sri_clave']);
        $usuario = $config['sri_usuario'];
        $tipos   = $tipoParam ?? $config['tipos_documento'];

        if (empty($clave)) {
            echo json_encode(['type' => 'error', 'error' => 'No se pudo desencriptar la clave SRI.']) . "\n";
            return;
        }

        if ($anoParam !== null) {
            $periodoUnico = [
                'ano' => $anoParam,
                'mes' => $mesParam ?? 0,
                'dia' => $diaParam ?? 0,
            ];
        } else {
            $hoy = new \DateTime();
            $periodoUnico = [
                'ano' => (int) $hoy->format('Y'),
                'mes' => (int) $hoy->format('n'),
                'dia' => 0,
            ];
        }

        $xmlsTotales = [];
        $detalles    = [];

        try {
            $tipoSri = $this->resolverTipoSri($tipos);
            $clavesExistentes = $this->obtenerClavesExistentes($idEmpresa);
            $this->debugLog[] = 'Claves ya existentes en BD: ' . count($clavesExistentes);

            $respuestaPuppeteer = $this->obtenerXmlsViaPuppeteerStream(
                $usuario, $clave,
                $periodoUnico['ano'], $periodoUnico['mes'],
                $periodoUnico['dia'], $tipoSri,
                self::TIMEOUT_DEFAULT_MS,
                $clavesExistentes
            );
            $xmlsTotales = $respuestaPuppeteer['xmls'] ?? [];
            $yaExistentesReportados = $respuestaPuppeteer['ya_existentes'] ?? 0;
            
            $detalles[] = "Período {$periodoUnico['ano']}/{$periodoUnico['mes']}: " . count($xmlsTotales) . ' XMLs descargados, ' . $yaExistentesReportados . ' ya existían.';

            $vistos      = [];
            $xmlsUnicos  = [];
            foreach ($xmlsTotales as $item) {
                $k = $item['clave'] ?? '';
                if ($k && isset($vistos[$k])) continue;
                if ($k) $vistos[$k] = true;
                $xmlsUnicos[] = $item;
            }
            $xmlsTotales = $xmlsUnicos;

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
            } else {
                $configMod->actualizarEstadoDescarga($idEmpresa, 'error', $mensaje);
                $this->insertarLog($logMod, $idEmpresa, $tipos, 'error', $mensaje, [], $inicio, $idUsuario);
            }
            echo json_encode(['type' => 'error', 'error' => $mensaje]) . "\n";
            return;
        }

        echo json_encode(['type' => 'progress', 'pct' => 97, 'message' => 'Procesando XMLs descargados en base de datos...']) . "\n";
        ob_flush(); flush();

        $registerSvc = new DocumentoAutomatedRegisterService();

        $totalNuevos     = 0;
        $totalExistentes = $yaExistentesReportados ?? 0;
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
        $totalEncontrados = count($xmlsTotales) + ($yaExistentesReportados ?? 0);
        
        $msgFinal = "Descargados: " . count($xmlsTotales) .
                    " | Nuevas: $totalNuevos | Existentes: $totalExistentes" .
                    " | Ignoradas: $totalIgnorados | Errores: $totalErrores";

        $configMod->actualizarEstadoDescarga($idEmpresa, 'completado', $msgFinal);

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
            'total_encontrados' => $totalEncontrados,
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'estado'            => 'completado',
            'detalle_json'      => json_encode(['resumen' => $detalles, 'claves' => $detallesClaves, 'debug' => $this->debugLog]),
            'duracion_seg'      => $duracion,
            'origen'            => 'manual',
            'created_by'        => $idUsuario,
        ]);

        echo json_encode([
            'type'              => 'resultado',
            'ok'                => true,
            'total_encontrados' => $totalEncontrados,
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'duracion_seg'      => $duracion,
            'mensaje'           => $msgFinal,
        ]) . "\n";
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
     * Consulta las claves de acceso ya registradas en BD para esta empresa
     * filtrando por el tipo_ambiente activo de la empresa.
     * Pruebas y producción son entornos separados: claves de pruebas no deben
     * bloquear la descarga/registro de los mismos documentos en producción.
     */
    private function obtenerClavesExistentes(int $idEmpresa): array
    {
        $db = \App\core\Database::getConnection();

        // Obtener el tipo_ambiente activo de la empresa
        $stAmb = $db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ? LIMIT 1");
        $stAmb->execute([$idEmpresa]);
        $tipoAmbiente = (string)($stAmb->fetchColumn() ?: '2'); // default producción
        $this->debugLog[] = "tipo_ambiente activo empresa #{$idEmpresa}: {$tipoAmbiente}";

        // Tablas operativas con clave y su columna tipo_ambiente
        // Los documentos_ignorados_sri se excluyen siempre sin filtro de ambiente
        $consultas = [
            "SELECT clave_acceso        AS clave FROM ventas_cabecera            WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL            AND tipo_ambiente = :amb",
            "SELECT numero_autorizacion AS clave FROM compras_cabecera            WHERE id_empresa = :id AND eliminado = false AND numero_autorizacion IS NOT NULL    AND tipo_ambiente = :amb",
            "SELECT clave_acceso        AS clave FROM liquidaciones_cabecera      WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL            AND tipo_ambiente = :amb",
            "SELECT clave_acceso        AS clave FROM retencion_compra_cabecera   WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL            AND tipo_ambiente = :amb",
            "SELECT clave_acceso        AS clave FROM notas_credito_cabecera      WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL            AND tipo_ambiente = :amb",
            "SELECT clave_acceso        AS clave FROM retencion_venta_cabecera    WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL            AND tipo_ambiente = :amb",
            "SELECT clave_acceso        AS clave FROM notas_debito_cabecera       WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL            AND tipo_ambiente = :amb",
        ];

        // Ignorados aplica a todos los ambientes (es una lista negra global de la empresa)
        $consultaIgnorados = "SELECT clave_acceso AS clave FROM documentos_ignorados_sri WHERE id_empresa = :id AND eliminado = false AND clave_acceso IS NOT NULL";

        $claves = [];

        foreach ($consultas as $sql) {
            try {
                $st = $db->prepare($sql);
                $st->execute([':id' => $idEmpresa, ':amb' => $tipoAmbiente]);
                foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $c) {
                    if (!empty($c)) $claves[] = $c;
                }
            } catch (\Exception $e) {
                $this->debugLog[] = 'WARN obtenerClavesExistentes: ' . $e->getMessage();
            }
        }

        try {
            $st = $db->prepare($consultaIgnorados);
            $st->execute([':id' => $idEmpresa]);
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $c) {
                if (!empty($c)) $claves[] = $c;
            }
        } catch (\Exception $e) {
            $this->debugLog[] = 'WARN obtenerClavesExistentes (ignorados): ' . $e->getMessage();
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

        $lines = explode("\n", trim($output));
        $json = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $data = json_decode($line, true);
            if ($data && isset($data['type']) && $data['type'] === 'finish') {
                $json = $data['data'] ?? $data;
            } elseif ($data && !isset($data['type']) && isset($data['ok'])) {
                $json = $data;
            }
        }

        if ($json === null) {
            throw new Exception('Puppeteer no retornó un JSON de finalización. Output: ' . substr($output, 0, 400));
        }

        // Credenciales incorrectas → excepción con código 401 para bloquear reintento
        if (!empty($json['credenciales_incorrectas'])) {
            throw new Exception('Credenciales SRI incorrectas: ' . ($json['error'] ?? 'sin detalle'), 401);
        }

        if (empty($json['ok']) && empty($json['xmls'])) {
            throw new Exception('Puppeteer error: ' . ($json['error'] ?? 'desconocido'));
        }

        // Retorna todo el array de respuesta
        return $json;
    }

    public function obtenerXmlsViaPuppeteerStream(
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
            throw new Exception('Script Puppeteer no encontrado.');
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
            1 => ['pipe', 'w'], // stdout (streaming json)
            2 => ['pipe', 'w'], // stderr (debug)
        ], $pipes);

        if (!is_resource($proc)) {
            throw new Exception('No se pudo iniciar Node.js.');
        }

        fwrite($pipes[0], $configJson);
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $finalData = null;
        $errorMsg = null;

        $finEspera = microtime(true) + ($timeoutMs / 1000) + 15;
        $stdoutBuffer = '';
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            if (connection_aborted()) {
                proc_terminate($proc);
                throw new Exception('Descarga cancelada por el usuario.', 499);
            }

            if (microtime(true) > $finEspera) {
                proc_terminate($proc);
                throw new Exception('Timeout general del proceso.', 504);
            }

            $numChangedStreams = stream_select($read, $write, $except, 1);

            if ($numChangedStreams === false) {
                break; // Error interrupido
            } elseif ($numChangedStreams > 0) {
                foreach ($read as $stream) {
                    if ($stream === $pipes[1]) {
                        $chunk = fread($pipes[1], 8192);
                        if ($chunk !== false && $chunk !== '') {
                            $stdoutBuffer .= $chunk;
                            while (($pos = strpos($stdoutBuffer, "\n")) !== false) {
                                $line = substr($stdoutBuffer, 0, $pos);
                                $stdoutBuffer = substr($stdoutBuffer, $pos + 1);
                                $line = trim($line);
                                if (empty($line)) continue;

                                $json = json_decode($line, true);
                                if (!$json) {
                                    $this->debugLog[] = "STDOUT no JSON: " . $line;
                                    continue;
                                }

                                if (isset($json['type']) && $json['type'] === 'progress') {
                                    echo $line . "\n";
                                    ob_flush(); flush();
                                } elseif (isset($json['type']) && $json['type'] === 'finish') {
                                    $finalData = $json['data'] ?? [];
                                }
                            }
                        }
                    } elseif ($stream === $pipes[2]) {
                        while (($line = fgets($pipes[2])) !== false) {
                            $line = trim($line);
                            if (!empty($line)) {
                                $this->debugLog[] = "Puppeteer log: " . $line;
                            }
                        }
                    }
                }
            }

            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
        }

        // Leer restos finales
        $chunk = '';
        while (!feof($pipes[1])) {
            $c = fread($pipes[1], 8192);
            if ($c !== false) {
                $chunk .= $c;
            }
        }
        $stdoutBuffer .= $chunk;
        
        $lines = explode("\n", $stdoutBuffer);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $json = json_decode($line, true);
            if ($json && isset($json['type']) && $json['type'] === 'finish') {
                $finalData = $json['data'] ?? [];
            }
        }

        while (!feof($pipes[2])) {
            $lineErr = fgets($pipes[2]);
            if ($lineErr !== false) {
                $lineErr = trim($lineErr);
                if (!empty($lineErr)) $this->debugLog[] = "Puppeteer log: " . $lineErr;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if (!$finalData) {
            $logsStr = implode(' | ', array_slice($this->debugLog, -5));
            throw new Exception('El script de descarga falló o se cerró inesperadamente. Logs: ' . $logsStr);
        }

        if (!empty($finalData['credenciales_incorrectas'])) {
            throw new Exception('Credenciales SRI incorrectas: ' . ($finalData['error'] ?? 'sin detalle'), 401);
        }

        if (!($finalData['ok'] ?? false)) {
            throw new Exception('Puppeteer error: ' . ($finalData['error'] ?? 'desconocido'));
        }

        return $finalData;
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
