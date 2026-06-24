<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\SriConfigDescarga;
use App\models\SriDescargaAutoLog;
use App\Services\modulos\DocumentoAutomatedRegisterService;
use App\Services\SriService;
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

        $detalles    = [];

        // Contadores (se actualizan en tiempo real vía $onXml)
        $totalNuevos     = 0;
        $totalExistentes = 0;
        $totalIgnorados  = 0;
        $totalErrores    = 0;
        $detallesClaves  = [];
        $vistos          = [];
        $totalDescargados = 0;

        $registerSvc = new DocumentoAutomatedRegisterService();

        // Filtro por tipo (cuando el portal no filtró)
        $tiposArrFiltro = null;
        $tipoSri = $this->resolverTipoSri($tipos);
        if ($tipos !== 'todos' && $tipoSri === '0') {
            $tiposArrFiltro = array_map('trim', explode(',', $tipos));
        }

        // Callback de registro incremental: se invoca por cada XML conforme llega del scraper.
        // Permite que el CRON también guarde documentos mientras descarga, sin esperar al final.
        $onXml = function (string $claveAcceso, string $xmlContent) use (
            $registerSvc, $idEmpresa, $idUsuario, $tiposArrFiltro,
            &$totalNuevos, &$totalExistentes, &$totalIgnorados, &$totalErrores,
            &$detallesClaves, &$vistos, &$totalDescargados
        ): void {
            if ($claveAcceso === '') $claveAcceso = 'sin_clave';
            if (isset($vistos[$claveAcceso])) return; // dedup
            $vistos[$claveAcceso] = true;
            $totalDescargados++;

            // Filtrar por tipo si aplica
            if ($tiposArrFiltro !== null && strlen($claveAcceso) === 49) {
                $codDoc = substr($claveAcceso, 8, 2);
                $tipo   = self::TIPOS_CODOC[$codDoc] ?? null;
                if ($tipo === null || !in_array($tipo, $tiposArrFiltro, true)) return;
            }

            if (empty($xmlContent)) {
                $totalErrores++;
                $detallesClaves[] = ['clave' => $claveAcceso, 'estado' => 'XML_VACIO', 'msg' => 'XML vacío del scraper'];
                return;
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
        };

        $yaExistentesReportados = 0;

        try {
            // Verificar qué claves ya existen en BD antes de descargar
            $clavesExistentes = $this->obtenerClavesExistentes($idEmpresa);
            $this->debugLog[] = 'Claves ya existentes en BD: ' . count($clavesExistentes);

            // Cada XML se registra en BD apenas llega (via $onXml), sin esperar al final.
            $respuestaPuppeteer = $this->obtenerXmlsViaPuppeteer(
                $usuario, $clave,
                $periodoUnico['ano'], $periodoUnico['mes'],
                $periodoUnico['dia'], $tipoSri,
                self::TIMEOUT_DEFAULT_MS,
                $clavesExistentes,
                $onXml
            );
            $yaExistentesReportados = $respuestaPuppeteer['ya_existentes'] ?? 0;

            $detalles[] = "Período {$periodoUnico['ano']}/{$periodoUnico['mes']}: {$totalDescargados} XMLs descargados, {$yaExistentesReportados} ya existían.";

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

        // Sumar existentes pre-filtrados (no descargados) al contador
        $totalExistentes += (int)($yaExistentesReportados ?? 0);
        $totalEncontrados = $totalDescargados + ($yaExistentesReportados ?? 0);
        $duracion = (int) (microtime(true) - $inicio);

        $msgFinal = "Descargados: {$totalDescargados}" .
                    " | Nuevas: {$totalNuevos} | Existentes: {$totalExistentes}" .
                    " | Ignoradas: {$totalIgnorados} | Errores: {$totalErrores}";

        $scraperOk = (bool)($respuestaPuppeteer['ok'] ?? true);
        if ($scraperOk) {
            $estadoProceso = 'completado';
            $finalOk = true;
        } elseif ($totalDescargados > 0 || $yaExistentesReportados > 0) {
            $msgFinal = 'Descarga parcial (' . ($respuestaPuppeteer['error'] ?? 'interrumpida') . '). ' . $msgFinal;
            $estadoProceso = 'parcial';
            $finalOk = false;
        } else {
            $msgFinal = 'Sin documentos: ' . ($respuestaPuppeteer['error'] ?? 'desconocido') . '. ' . $msgFinal;
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

        $detalles = [];

        // Estado de registro: se actualiza incrementalmente conforme llega cada XML.
        $registerSvc     = new DocumentoAutomatedRegisterService();
        $totalNuevos     = 0;
        $totalExistentes = 0;
        $totalIgnorados  = 0;
        $totalErrores    = 0;
        $detallesClaves  = [];
        $vistos          = [];

        // Si la config pide tipos específicos pero el portal no filtró (tipo SRI 0),
        // se descarta por tipo aquí mismo, antes de registrar.
        $tiposArrFiltro = ($tipos !== 'todos' && $this->resolverTipoSri($tipos) === '0')
            ? array_map('trim', explode(',', $tipos))
            : null;

        // Callback de registro incremental: se invoca por cada XML descargado.
        $onXml = function (string $claveAcceso, string $xmlContent) use (
            $registerSvc, &$totalNuevos, &$totalExistentes, &$totalIgnorados, &$totalErrores,
            &$detallesClaves, &$vistos, $tiposArrFiltro, $idEmpresa, $idUsuario
        ): void {
            if ($claveAcceso === '') $claveAcceso = 'sin_clave';
            if (isset($vistos[$claveAcceso])) return; // dedup
            $vistos[$claveAcceso] = true;

            if ($tiposArrFiltro !== null && strlen($claveAcceso) === 49) {
                $codDoc = substr($claveAcceso, 8, 2);
                $tipo   = self::TIPOS_CODOC[$codDoc] ?? null;
                if ($tipo === null || !in_array($tipo, $tiposArrFiltro, true)) return; // no es del tipo pedido
            }

            if ($xmlContent === '') {
                $totalErrores++;
                $detallesClaves[] = ['clave' => $claveAcceso, 'estado' => 'XML_VACIO', 'msg' => 'XML vacío del scraper'];
                return;
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
        };

        $yaExistentesReportados = 0;

        try {
            $tipoSri = $this->resolverTipoSri($tipos);
            $clavesExistentes = $this->obtenerClavesExistentes($idEmpresa);
            $this->debugLog[] = 'Claves ya existentes en BD: ' . count($clavesExistentes);

            // El scraper invoca $onXml por cada XML conforme lo descarga (registro incremental).
            $respuestaPuppeteer = $this->obtenerXmlsViaPuppeteerStream(
                $usuario, $clave,
                $periodoUnico['ano'], $periodoUnico['mes'],
                $periodoUnico['dia'], $tipoSri,
                self::TIMEOUT_DEFAULT_MS,
                $clavesExistentes,
                $onXml
            );

            $yaExistentesReportados = (int) ($respuestaPuppeteer['ya_existentes'] ?? 0);
            $scraperOk    = (bool) ($respuestaPuppeteer['ok'] ?? false);
            $scraperError = $respuestaPuppeteer['error'] ?? null;

        } catch (Exception $e) {
            // Solo llegan aquí credenciales inválidas (401) o cancelación del usuario (499).
            // Lo descargado antes de la interrupción ya quedó registrado vía $onXml.
            $mensaje = $e->getMessage();

            if ($e->getCode() === 401) {
                $configMod->bloquearLogin($idEmpresa, $mensaje);
                $configMod->actualizarEstadoDescarga($idEmpresa, 'error', $mensaje);
                $this->insertarLog($logMod, $idEmpresa, $tipos, 'error', $mensaje, [], $inicio, $idUsuario);
                echo json_encode(['type' => 'error', 'error' => $mensaje]) . "\n";
                return;
            }

            // Cancelación u otra interrupción: conservar en el log lo ya registrado.
            $hayParciales = count($detallesClaves) > 0;
            $estadoX      = $hayParciales ? 'parcial' : 'error';
            $msgX         = $hayParciales
                ? "Descarga interrumpida ($mensaje). Registrados: $totalNuevos nuevos, $totalExistentes existentes, $totalErrores errores."
                : $mensaje;

            $configMod->actualizarEstadoDescarga($idEmpresa, $estadoX, $msgX);
            $this->registrarLogStream(
                $logMod, $idEmpresa, $periodoUnico, $tipos, $estadoX, $idUsuario, $inicio,
                count($detallesClaves), $totalNuevos, $totalExistentes, $totalIgnorados, $totalErrores,
                ['resumen' => $detalles, 'claves' => $detallesClaves, 'debug' => $this->debugLog, 'error' => $mensaje]
            );
            echo json_encode(['type' => 'error', 'error' => $mensaje]) . "\n";
            return;
        }

        // Los ya existentes pre-filtrados (no descargados) también cuentan como existentes.
        $totalExistentes += $yaExistentesReportados;

        $descargados      = count($detallesClaves);
        $totalEncontrados = $descargados + $yaExistentesReportados;
        $detalles[] = "Período {$periodoUnico['ano']}/{$periodoUnico['mes']}: {$descargados} XMLs procesados, {$yaExistentesReportados} ya existían.";

        // Estado del proceso: completo si el scraper terminó bien; parcial si se interrumpió
        // pero alcanzó a descargar algo; error si no se obtuvo nada.
        if ($scraperOk) {
            $estadoProceso = 'completado';
        } elseif ($descargados > 0 || $yaExistentesReportados > 0) {
            $estadoProceso = 'parcial';
        } else {
            $estadoProceso = 'error';
        }

        // Error real sin nada descargado: notificar como error y salir.
        if ($estadoProceso === 'error') {
            $mensaje = $scraperError ?? 'No se obtuvieron documentos.';
            $configMod->actualizarEstadoDescarga($idEmpresa, 'error', $mensaje);
            $this->insertarLog($logMod, $idEmpresa, $tipos, 'error', $mensaje, [], $inicio, $idUsuario);
            echo json_encode(['type' => 'error', 'error' => $mensaje]) . "\n";
            return;
        }

        $duracion = (int) (microtime(true) - $inicio);
        $msgFinal = "Descargados: {$descargados} | Nuevas: {$totalNuevos} | Existentes: {$totalExistentes}" .
                    " | Ignoradas: {$totalIgnorados} | Errores: {$totalErrores}";
        if ($estadoProceso === 'parcial') {
            $msgFinal = "Descarga parcial (" . ($scraperError ?? 'interrumpida') . "). " . $msgFinal;
        }

        $configMod->actualizarEstadoDescarga($idEmpresa, $estadoProceso, $msgFinal);
        $this->registrarLogStream(
            $logMod, $idEmpresa, $periodoUnico, $tipos, $estadoProceso, $idUsuario, $inicio,
            $totalEncontrados, $totalNuevos, $totalExistentes, $totalIgnorados, $totalErrores,
            ['resumen' => $detalles, 'claves' => $detallesClaves, 'debug' => $this->debugLog]
        );

        echo json_encode([
            'type'              => 'resultado',
            'ok'                => $scraperOk,
            'estado'            => $estadoProceso,
            'total_encontrados' => $totalEncontrados,
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'duracion_seg'      => $duracion,
            'mensaje'           => $msgFinal,
        ]) . "\n";
    }

    /**
     * Inserta el log de una ejecución por streaming reutilizando la lógica de fechas
     * del período. Centraliza el formato para los caminos completo / parcial.
     */
    private function registrarLogStream(
        SriDescargaAutoLog $logMod,
        int    $idEmpresa,
        array  $periodoUnico,
        string $tipos,
        string $estado,
        int    $idUsuario,
        float  $inicio,
        int    $totalEncontrados,
        int    $totalNuevos,
        int    $totalExistentes,
        int    $totalIgnorados,
        int    $totalErrores,
        array  $detalleJson,
        string $origen = 'manual'
    ): void {
        $mesPeriodo = $periodoUnico['mes'] ?: (int) date('n');
        $fechaDesde = sprintf('%04d-%02d-%02d', $periodoUnico['ano'], $mesPeriodo, $periodoUnico['dia'] ?: 1);
        $fechaHasta = $periodoUnico['dia']
            ? $fechaDesde
            : sprintf('%04d-%02d-%02d', $periodoUnico['ano'], $mesPeriodo, (int) date('t', mktime(0, 0, 0, $mesPeriodo, 1, $periodoUnico['ano'])));

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
            'estado'            => $estado,
            'detalle_json'      => json_encode($detalleJson),
            'duracion_seg'      => (int) (microtime(true) - $inicio),
            'origen'            => $origen,
            'created_by'        => $idUsuario,
        ]);
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

    /**
     * Lanza el scraper Node.js y lee su salida línea a línea en tiempo real.
     * Invoca $onXml($clave, $xml) por cada XML que llega, permitiendo guardado
     * incremental antes de que el proceso termine o sea interrumpido por timeout.
     * Si $onXml es null, los XMLs se acumulan en el array de retorno (comportamiento
     * compatible con el modo CRON).
     */
    public function obtenerXmlsViaPuppeteer(
        string    $usuario,
        string    $clave,
        int       $ano,
        int       $mes,
        int       $dia,
        string    $tipo,
        int       $timeoutMs = self::TIMEOUT_DEFAULT_MS,
        array     $clavesExcluir = [],
        ?callable $onXml = null
    ): array {
        $scriptPath = MVC_ROOT . '/scripts/sri_scraper.js';
        if (!file_exists($scriptPath)) {
            throw new Exception('Script Puppeteer no encontrado. Ejecute: cd scripts && npm install');
        }

        $appCfg         = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $apiKey2captcha = getenv('TWOCAPTCHA_API_KEY') ?: ($appCfg['2captcha_api_key'] ?? '');

        $configJson = json_encode([
            'usuario'        => $usuario,
            'clave'          => $clave,
            'ano'            => $ano,
            'mes'            => $mes,
            'dia'            => $dia,
            'tipo'           => $tipo,
            'timeoutMs'      => $timeoutMs,
            'apiKey2captcha' => $apiKey2captcha,
            'clavesExcluir'  => $clavesExcluir,
            'streamXml'      => true,  // Siempre en modo stream para registro incremental
        ]);

        $nodeCmd = 'node ' . escapeshellarg($scriptPath);
        $cmd  = (PHP_OS_FAMILY === 'Windows') ? $nodeCmd : ('xvfb-run -a ' . $nodeCmd);
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

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $finalData      = null;
        $xmlsAcumulados = [];   // Solo si no se proporcionó $onXml
        $stdoutBuffer   = '';
        $finEspera      = microtime(true) + ($timeoutMs / 1000) + 15;

        while (true) {
            if (microtime(true) > $finEspera) {
                proc_terminate($proc);
                @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($proc);
                $this->debugLog[] = 'PHP timeout general del proceso.';
                return [
                    'ok'       => false,
                    'error'    => 'Timeout general del proceso.',
                    'xmls'     => $onXml ? [] : $xmlsAcumulados,
                    'partial'  => true,
                ];
            }

            $read  = [$pipes[1], $pipes[2]];
            $write = $except = null;
            $n = stream_select($read, $write, $except, 1);

            if ($n === false) break;

            if ($n > 0) {
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
                                    $this->debugLog[] = "STDOUT no JSON: " . substr($line, 0, 120);
                                    continue;
                                }

                                $type = $json['type'] ?? '';
                                if ($type === 'xml') {
                                    $d    = $json['data'] ?? [];
                                    $clv  = (string)($d['clave'] ?? '');
                                    $xml  = (string)($d['xml']   ?? '');
                                    if ($onXml) {
                                        $onXml($clv, $xml);
                                    } else {
                                        $xmlsAcumulados[] = ['clave' => $clv, 'xml' => $xml];
                                    }
                                } elseif ($type === 'finish') {
                                    $finalData = $json['data'] ?? [];
                                }
                                // Los eventos 'progress' se ignoran en modo no-streaming
                            }
                        }
                    } elseif ($stream === $pipes[2]) {
                        while (($lineErr = fgets($pipes[2])) !== false) {
                            $lineErr = trim($lineErr);
                            if ($lineErr !== '') $this->debugLog[] = "Puppeteer log: " . $lineErr;
                        }
                    }
                }
            }

            $status = proc_get_status($proc);
            if (!$status['running']) break;
        }

        // Leer restos del buffer antes de cerrar los pipes
        stream_set_blocking($pipes[1], true);
        $remaining = @stream_get_contents($pipes[1]);
        if ($remaining) $stdoutBuffer .= $remaining;

        foreach (explode("\n", $stdoutBuffer) as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $json = json_decode($line, true);
            if (!$json || !isset($json['type'])) continue;
            if ($json['type'] === 'xml') {
                $d   = $json['data'] ?? [];
                $clv = (string)($d['clave'] ?? '');
                $xml = (string)($d['xml']   ?? '');
                if ($onXml) { $onXml($clv, $xml); }
                else        { $xmlsAcumulados[] = ['clave' => $clv, 'xml' => $xml]; }
            } elseif ($json['type'] === 'finish') {
                $finalData = $json['data'] ?? [];
            }
        }

        while (!feof($pipes[2])) {
            $lineErr = fgets($pipes[2]);
            if ($lineErr !== false && trim($lineErr) !== '') {
                $this->debugLog[] = "Puppeteer log: " . trim($lineErr);
            }
        }
        @fclose($pipes[1]); @fclose($pipes[2]); proc_close($proc);

        $this->debugLog[] = "Puppeteer [{$ano}/{$mes}]: " .
            ($onXml ? 'XMLs procesados via callback.' : count($xmlsAcumulados) . ' XMLs acumulados.');

        if (!$finalData) {
            $logsStr = implode(' | ', array_slice($this->debugLog, -5));
            return [
                'ok'      => false,
                'error'   => 'El script de descarga cerró inesperadamente. Logs: ' . $logsStr,
                'xmls'    => $onXml ? [] : $xmlsAcumulados,
                'partial' => true,
            ];
        }

        if (!empty($finalData['credenciales_incorrectas'])) {
            throw new Exception('Credenciales SRI incorrectas: ' . ($finalData['error'] ?? 'sin detalle'), 401);
        }

        // Incluir XMLs acumulados en el resultado (cuando no hay callback)
        if (!$onXml) {
            $finalData['xmls'] = array_merge($xmlsAcumulados, $finalData['xmls'] ?? []);
        } else {
            $finalData['xmls'] = [];
        }

        return $finalData;
    }

    /**
     * Variante con streaming: lanza el scraper y, conforme cada XML se descarga,
     * invoca $onXml($clave, $xml) para registrarlo de inmediato (descarga incremental).
     * Así, si el proceso se interrumpe en 30/56, esos 30 ya quedan registrados.
     * En caso de error NO lanza excepción (salvo credenciales inválidas / cancelación):
     * retorna la data parcial para que el llamador finalice con lo ya registrado.
     */
    public function obtenerXmlsViaPuppeteerStream(
        string    $usuario,
        string    $clave,
        int       $ano,
        int       $mes,
        int       $dia,
        string    $tipo,
        int       $timeoutMs = self::TIMEOUT_DEFAULT_MS,
        array     $clavesExcluir = [],
        ?callable $onXml = null
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
            'streamXml'       => true,
        ]);

        // En Linux (servidor) el navegador corre en modo headful bajo un display virtual
        // (xvfb) para conservar un score alto de reCAPTCHA v3. En Windows se lanza directo.
        $nodeCmd = 'node ' . escapeshellarg($scriptPath);
        $cmd  = (PHP_OS_FAMILY === 'Windows') ? $nodeCmd : ('xvfb-run -a ' . $nodeCmd);
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
                @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($proc);
                // Lo descargado hasta aquí ya se registró vía $onXml: retornar parcial.
                return ['ok' => false, 'error' => 'Timeout general del proceso.', 'partial' => true];
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
                                } elseif (isset($json['type']) && $json['type'] === 'xml') {
                                    // Registro incremental: cada XML se procesa apenas llega.
                                    if ($onXml) {
                                        $d = $json['data'] ?? [];
                                        $onXml((string)($d['clave'] ?? ''), (string)($d['xml'] ?? ''));
                                    }
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
            if (!$json || !isset($json['type'])) continue;
            if ($json['type'] === 'xml') {
                if ($onXml) {
                    $d = $json['data'] ?? [];
                    $onXml((string)($d['clave'] ?? ''), (string)($d['xml'] ?? ''));
                }
            } elseif ($json['type'] === 'finish') {
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
            // El proceso se cerró sin emitir 'finish'. Lo ya registrado vía $onXml se conserva.
            $logsStr = implode(' | ', array_slice($this->debugLog, -5));
            return ['ok' => false, 'error' => 'El script de descarga se cerró inesperadamente. Logs: ' . $logsStr, 'partial' => true];
        }

        // Credenciales inválidas: sí se lanza para bloquear reintentos (no hay nada útil descargado).
        if (!empty($finalData['credenciales_incorrectas'])) {
            throw new Exception('Credenciales SRI incorrectas: ' . ($finalData['error'] ?? 'sin detalle'), 401);
        }

        // Para cualquier otro error NO se lanza excepción: se retorna la data (ok=false)
        // y el llamador finaliza con lo que ya se registró incrementalmente.
        return $finalData;
    }

    // ─────────────────────────────────────────────
    // DESCARGA ASISTIDA (visor remoto + humano en el loop)
    // ─────────────────────────────────────────────

    /**
     * Descarga ASISTIDA con streaming. El scraper (modo asistido) loguea y aplica filtros;
     * el HUMANO hace clic en "Consultar" sobre el portal real (visto por pantalla remota),
     * con lo que el reCAPTCHA del SRI lo evalúa como humano. El scraper devuelve solo las
     * CLAVES de acceso del listado; luego cada XML se descarga por el webservice oficial
     * (SriService, sin captcha) y se registra con DocumentoAutomatedRegisterService.
     *
     * Emite por stdout (JSON-line) los eventos: progress, esperando_humano, resultado, error.
     */
    public function iniciarSesionAsistidaStream(
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
            echo json_encode(['type' => 'error', 'error' => 'Descarga bloqueada: credenciales SRI incorrectas. Actualice la clave.']) . "\n";
            return;
        }

        $clave   = self::desencriptarClave($config['sri_clave']);
        $usuario = $config['sri_usuario'];
        $tipos   = $tipoParam ?? $config['tipos_documento'];
        if (empty($clave)) {
            echo json_encode(['type' => 'error', 'error' => 'No se pudo desencriptar la clave SRI. Reingrese la clave.']) . "\n";
            return;
        }

        if ($anoParam !== null) {
            $periodoUnico = ['ano' => $anoParam, 'mes' => $mesParam ?? 0, 'dia' => $diaParam ?? 0];
        } else {
            $hoy = new \DateTime();
            $periodoUnico = ['ano' => (int) $hoy->format('Y'), 'mes' => (int) $hoy->format('n'), 'dia' => 0];
        }

        $tipoSri = $this->resolverTipoSri($tipos);

        // 1) Lanzar scraper asistido → retransmite progress/esperando_humano y devuelve las claves.
        try {
            $claves = $this->lanzarScraperAsistido(
                $usuario, $clave,
                $periodoUnico['ano'], $periodoUnico['mes'], $periodoUnico['dia'], $tipoSri,
                self::TIMEOUT_DEFAULT_MS
            );
        } catch (Exception $e) {
            $mensaje = $e->getMessage();
            if ($e->getCode() === 401) {
                $configMod->bloquearLogin($idEmpresa, $mensaje);
            }
            $configMod->actualizarEstadoDescarga($idEmpresa, 'error', $mensaje);
            $this->insertarLog($logMod, $idEmpresa, $tipos, 'error', $mensaje, [], $inicio, $idUsuario);
            echo json_encode(['type' => 'error', 'error' => $mensaje]) . "\n";
            return;
        }

        // 2) Filtrar por tipo (si la config pide tipos específicos y el portal no filtró)
        $tiposArrFiltro = ($tipos !== 'todos' && $tipoSri === '0')
            ? array_map('trim', explode(',', $tipos)) : null;

        if ($tiposArrFiltro !== null) {
            $claves = array_values(array_filter($claves, function (string $c) use ($tiposArrFiltro): bool {
                if (strlen($c) !== 49) return false;
                $codDoc = substr($c, 8, 2);
                $tipo   = self::TIPOS_CODOC[$codDoc] ?? null;
                return $tipo !== null && in_array($tipo, $tiposArrFiltro, true);
            }));
        }

        // 3) Separar las que ya existen en BD de las nuevas
        $setExist     = array_flip($this->obtenerClavesExistentes($idEmpresa));
        $nuevasClaves = [];
        $yaExistentes = 0;
        foreach (array_values(array_unique($claves)) as $c) {
            if ($c === '' || strlen($c) !== 49) continue;
            if (isset($setExist[$c])) { $yaExistentes++; continue; }
            $nuevasClaves[] = $c;
        }

        $totalListado = count($claves);
        $n = count($nuevasClaves);
        echo json_encode(['type' => 'progress', 'pct' => 82,
            'message' => "Listado: {$totalListado} comprobantes. Descargando {$n} nuevos por webservice..."]) . "\n";
        @ob_flush(); @flush();

        // 4) Descargar cada XML nuevo por el webservice oficial (sin captcha) y registrar
        $sriService  = new SriService();
        $registerSvc = new DocumentoAutomatedRegisterService();

        $totalNuevos    = 0;
        $totalExistentes = $yaExistentes;
        $totalIgnorados = 0;
        $totalErrores   = 0;
        $detallesClaves = [];

        foreach ($nuevasClaves as $i => $c) {
            if (connection_aborted()) break;
            $pct = 82 + (int) floor((($i + 1) / max(1, $n)) * 16); // 82..98
            echo json_encode(['type' => 'progress', 'pct' => $pct, 'message' => 'Descargando ' . ($i + 1) . "/{$n}..."]) . "\n";
            @ob_flush(); @flush();

            try {
                $resp = $sriService->obtenerComprobanteXml($c);
                if (empty($resp['ok']) || empty($resp['xml'])) {
                    $totalErrores++;
                    $detallesClaves[] = ['clave' => $c, 'estado' => 'ERROR', 'msg' => $resp['mensaje'] ?? 'Sin XML/no autorizado'];
                    continue;
                }
                $res       = $registerSvc->procesarYRegistrar($resp['xml'], $idEmpresa, $idUsuario);
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
                $detallesClaves[] = ['clave' => $c, 'estado' => $estadoReg, 'msg' => $res['mensaje'] ?? ''];
            } catch (Exception $e) {
                $totalErrores++;
                $detallesClaves[] = ['clave' => $c, 'estado' => 'EXCEPCION', 'msg' => $e->getMessage()];
            }
        }

        // 5) Resultado final + log
        $estadoProceso    = 'completado';
        $totalEncontrados = $totalListado;
        $duracion         = (int) (microtime(true) - $inicio);
        $msgFinal = "Listado: {$totalListado} | Nuevas: {$totalNuevos} | Existentes: {$totalExistentes}" .
                    " | Ignoradas: {$totalIgnorados} | Errores: {$totalErrores}";

        $configMod->actualizarEstadoDescarga($idEmpresa, $estadoProceso, $msgFinal);
        $this->registrarLogStream(
            $logMod, $idEmpresa, $periodoUnico, $tipos, $estadoProceso, $idUsuario, $inicio,
            $totalEncontrados, $totalNuevos, $totalExistentes, $totalIgnorados, $totalErrores,
            ['resumen' => ["Asistido {$periodoUnico['ano']}/{$periodoUnico['mes']}: {$totalListado} en listado, {$n} nuevos"],
             'claves' => $detallesClaves, 'debug' => $this->debugLog],
            'asistido'
        );

        echo json_encode([
            'type'              => 'resultado',
            'ok'                => true,
            'estado'            => $estadoProceso,
            'total_encontrados' => $totalEncontrados,
            'total_nuevos'      => $totalNuevos,
            'total_existentes'  => $totalExistentes,
            'total_ignorados'   => $totalIgnorados,
            'total_errores'     => $totalErrores,
            'duracion_seg'      => $duracion,
            'mensaje'           => $msgFinal,
        ]) . "\n";
    }

    /**
     * Lanza el scraper en modo ASISTIDO sobre el display fijo :99 (compartido por VNC).
     * NO usa xvfb-run (que crea un display efímero que el VNC no vería). Retransmite los
     * eventos progress/esperando_humano al cliente y devuelve el array de claves recolectadas.
     * Lanza Exception(401) si las credenciales son inválidas.
     *
     * @return string[] claves de acceso
     */
    private function lanzarScraperAsistido(
        string $usuario,
        string $clave,
        int    $ano,
        int    $mes,
        int    $dia,
        string $tipo,
        int    $timeoutMs = self::TIMEOUT_DEFAULT_MS
    ): array {
        $scriptPath = MVC_ROOT . '/scripts/sri_scraper.js';
        if (!file_exists($scriptPath)) {
            throw new Exception('Script Puppeteer no encontrado. Ejecute: cd scripts && npm install');
        }

        $appCfg         = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $apiKey2captcha = getenv('TWOCAPTCHA_API_KEY') ?: ($appCfg['2captcha_api_key'] ?? '');

        $configJson = json_encode([
            'usuario'        => $usuario,
            'clave'          => $clave,
            'ano'            => $ano,
            'mes'            => $mes,
            'dia'            => $dia,
            'tipo'           => $tipo,
            'timeoutMs'      => $timeoutMs,
            'apiKey2captcha' => $apiKey2captcha,
            'modoAsistido'   => true,
            // Usar la Chromium de Playwright (channel vacío), NO el Chrome real: en Windows,
            // Chrome real hace "handoff" del perfil con el navegador personal del usuario y
            // Playwright pierde la sesión. La Chromium empaquetada no comparte ese mecanismo.
            // Perfil dedicado y exclusivo del modo asistido (el captcha lo resuelve el humano,
            // así que no necesita el perfil sembrado con Google del modo automático).
            'channel'        => '',
            'profileDir'     => MVC_ROOT . '/scripts/.sri_profile_asistido',
        ]);

        // Display fijo :99 (compartido por VNC). Configurable con SRI_VISOR_DISPLAY.
        $display = getenv('SRI_VISOR_DISPLAY') ?: ':99';
        $nodeCmd = 'node ' . escapeshellarg($scriptPath);
        $cmd     = (PHP_OS_FAMILY === 'Windows')
            ? $nodeCmd
            : ('DISPLAY=' . escapeshellarg($display) . ' ' . $nodeCmd);

        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($proc)) {
            throw new Exception('No se pudo iniciar Node.js.');
        }

        fwrite($pipes[0], $configJson);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $claves       = null;
        $finalData    = null;
        $stdoutBuffer = '';
        $finEspera    = microtime(true) + ($timeoutMs / 1000) + 20;

        while (true) {
            if (connection_aborted()) {
                proc_terminate($proc);
                @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($proc);
                throw new Exception('Sesión asistida cancelada por el usuario.', 499);
            }
            if (microtime(true) > $finEspera) {
                proc_terminate($proc);
                @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($proc);
                throw new Exception('Tiempo de espera agotado en la sesión asistida.');
            }

            $read = [$pipes[1], $pipes[2]];
            $w = $e = null;
            $nsel = stream_select($read, $w, $e, 1);
            if ($nsel === false) break;

            if ($nsel > 0) {
                foreach ($read as $stream) {
                    if ($stream === $pipes[1]) {
                        $chunk = fread($pipes[1], 8192);
                        if ($chunk !== false && $chunk !== '') {
                            $stdoutBuffer .= $chunk;
                            while (($pos = strpos($stdoutBuffer, "\n")) !== false) {
                                $line = trim(substr($stdoutBuffer, 0, $pos));
                                $stdoutBuffer = substr($stdoutBuffer, $pos + 1);
                                if ($line === '') continue;

                                $json = json_decode($line, true);
                                if (!$json || !isset($json['type'])) {
                                    $this->debugLog[] = 'STDOUT no JSON: ' . substr($line, 0, 120);
                                    continue;
                                }
                                $t = $json['type'];
                                if ($t === 'progress' || $t === 'esperando_humano') {
                                    echo $line . "\n"; @ob_flush(); @flush();
                                } elseif ($t === 'claves') {
                                    $claves = is_array($json['data'] ?? null) ? $json['data'] : [];
                                } elseif ($t === 'finish') {
                                    $finalData = $json['data'] ?? [];
                                }
                            }
                        }
                    } elseif ($stream === $pipes[2]) {
                        while (($l = fgets($pipes[2])) !== false) {
                            $l = trim($l);
                            if ($l !== '') $this->debugLog[] = 'Puppeteer log: ' . $l;
                        }
                    }
                }
            }

            $status = proc_get_status($proc);
            if (!$status['running']) break;
        }

        // Restos del buffer
        stream_set_blocking($pipes[1], true);
        $rem = @stream_get_contents($pipes[1]);
        if ($rem) $stdoutBuffer .= $rem;
        foreach (explode("\n", $stdoutBuffer) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $json = json_decode($line, true);
            if (!$json || !isset($json['type'])) continue;
            if ($json['type'] === 'claves') {
                $claves = is_array($json['data'] ?? null) ? $json['data'] : [];
            } elseif ($json['type'] === 'finish') {
                $finalData = $json['data'] ?? [];
            }
        }
        while (!feof($pipes[2])) {
            $l = fgets($pipes[2]);
            if ($l !== false && trim($l) !== '') $this->debugLog[] = 'Puppeteer log: ' . trim($l);
        }
        @fclose($pipes[1]); @fclose($pipes[2]); proc_close($proc);

        if ($finalData && !empty($finalData['credenciales_incorrectas'])) {
            throw new Exception('Credenciales SRI incorrectas: ' . ($finalData['error'] ?? 'sin detalle'), 401);
        }

        if ($claves === null) {
            $err = $finalData['error'] ?? 'No se obtuvo el listado del SRI. ¿Hiciste clic en CONSULTAR?';
            throw new Exception($err);
        }

        return $claves;
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
