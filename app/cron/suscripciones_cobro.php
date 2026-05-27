<?php
/**
 * CRON: Procesamiento automático de suscripciones vencidas
 * =========================================================
 * Genera facturas y ejecuta cobros automáticos con Kushki
 * para suscripciones cuyo proximo_cobro <= hoy.
 *
 * Configuración recomendada (crontab Linux):
 *   # Diariamente a las 06:00
 *   0 6 * * *  php /ruta/al/proyecto/app/cron/suscripciones_cobro.php >> /var/log/suscripciones_cobro.log 2>&1
 *
 * Windows (Task Scheduler):
 *   Programa : C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\sistema\app\cron\suscripciones_cobro.php
 *   Trigger  : Diariamente a las 06:00
 *
 * Variables de entorno opcionales:
 *   SUSC_CRON_LOTE  = número máximo de suscripciones a procesar (default: 30)
 *   SUSC_CRON_DEBUG = 1 para salida detallada
 */

declare(strict_types=1);

define('MVC_ROOT', dirname(__DIR__, 2));
define('MVC_APP',  MVC_ROOT . '/app');

if (!file_exists(MVC_ROOT . '/bootstrap.php')) {
    fwrite(STDERR, "[SUSC CRON] No se encontró bootstrap.php en " . MVC_ROOT . "\n");
    exit(1);
}
require_once MVC_ROOT . '/bootstrap.php';

use App\core\Database;
use App\repositories\modulos\SuscripcionesRepository;
use App\Services\modulos\KushkiService;
use App\Services\modulos\SuscripcionesService;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;

$debug   = (bool) getenv('SUSC_CRON_DEBUG');
$lote    = max(1, min(100, (int) (getenv('SUSC_CRON_LOTE') ?: 30)));
$inicio  = microtime(true);
$ok      = 0;
$errores = 0;

$log = function (string $nivel, string $msg) use ($debug): void {
    $ts = date('Y-m-d H:i:s');
    if ($nivel !== 'DEBUG' || $debug) {
        echo "[$ts][$nivel] $msg\n";
    }
};

$log('INFO', "=== SUSCRIPCIONES CRON iniciado (lote=$lote) ===");

// ── Conexión BD ────────────────────────────────────────────────────────────────
try {
    $db = Database::getConnection();
} catch (\Throwable $e) {
    $log('ERROR', "No se pudo conectar a la BD: " . $e->getMessage());
    exit(1);
}

// ── Verificar tablas ───────────────────────────────────────────────────────────
$tablaExiste = $db->query(
    "SELECT 1 FROM information_schema.tables
     WHERE table_name = 'suscripciones' AND table_schema = 'public'"
)->fetchColumn();

if (!$tablaExiste) {
    $log('WARN', "Tabla 'suscripciones' no existe. Ejecute create_suscripciones_module.sql");
    exit(0);
}

// ── Obtener suscripciones vencidas ─────────────────────────────────────────────
$suscRepo      = new SuscripcionesRepository();
$suscripciones = $suscRepo->getVencidasParaCobro($lote);

if (empty($suscripciones)) {
    $log('INFO', "Sin suscripciones vencidas para procesar.");
    exit(0);
}

$log('INFO', "Procesando " . count($suscripciones) . " suscripción(es)...");

$suscService = new SuscripcionesService(
    $suscRepo,
    new SuscripcionesRules(),
    new LogSistemaService()
);

$idUsuarioSistema = 0; // proceso automático

foreach ($suscripciones as $susc) {
    $idSusc    = (int) $susc['id'];
    $idEmpresa = (int) $susc['id_empresa'];
    $cliente   = $susc['cliente_nombre'] ?? "(id:{$susc['id_cliente']})";
    $meses     = (int) ($susc['periodicidad_meses'] ?? 1);

    $log('INFO', "Procesando suscripción #$idSusc — $cliente");

    try {
        // ── 1. Calcular monto total desde suscripciones_detalle ────────────────
        $detalle    = $suscRepo->getDetalleParaCobro($idSusc);
        $montoTotal = 0.0;
        foreach ($detalle as $det) {
            $base       = (float) $det['cantidad'] * (float) $det['precio_unitario'];
            $iva        = $base * ((float) ($det['porcentaje_iva'] ?? 0) / 100);
            $montoTotal += $base + $iva;
        }
        $montoTotal = round($montoTotal, 2);

        if ($montoTotal <= 0) {
            $log('WARN', "  → Suscripción #$idSusc sin monto (sin ítems activos). Saltando.");
            continue;
        }

        // ── 2. Registrar pago en estado 'pendiente' ───────────────────────────
        $idPago = $suscRepo->insertPago([
            'id_suscripcion' => $idSusc,
            'id_empresa'     => $idEmpresa,
            'fecha_cobro'    => date('Y-m-d'),
            'monto'          => $montoTotal,
            'estado'         => 'pendiente',
            'id_usuario'     => $idUsuarioSistema,
        ]);

        // ── 3. Generar factura ────────────────────────────────────────────────
        $idFactura = null;
        try {
            $idFactura = generarFacturaSuscripcion($susc, $detalle, $db, $log);
            $log('DEBUG', "  → Factura generada: #$idFactura");
        } catch (\Throwable $ef) {
            $log('WARN', "  → No se pudo generar factura: " . $ef->getMessage());
        }

        // ── 4. Si es tarjeta: cobrar con Kushki ───────────────────────────────
        $estadoPago = 'exitoso';
        $transId    = null;
        $kushkiResp = null;

        if ($susc['forma_cobro'] === 'tarjeta' && !empty($susc['kushki_token'])) {
            try {
                $kushki = new KushkiService();
                $result = $kushki->cobrar(
                    $susc['kushki_token'],
                    $montoTotal,
                    "Suscripción #{$idSusc} — " . date('F Y')
                );

                $transId    = $result['transaction_id'] ?? null;
                $kushkiResp = $result['response']       ?? null;
                $estadoPago = $result['estado'];

                $log('INFO', "  → Kushki: $estadoPago" . ($transId ? " (trans: $transId)" : ''));
            } catch (\Throwable $ek) {
                $estadoPago = 'fallido';
                $log('ERROR', "  → Kushki error: " . $ek->getMessage());
            }

            if ($estadoPago === 'fallido') {
                $suscRepo->incrementarIntentosFallidos($idSusc);
                $intentosFallidos = ((int) ($susc['intentos_fallidos'] ?? 0)) + 1;

                // Suspender tras 3 fallos consecutivos
                if ($intentosFallidos >= 3) {
                    $suscRepo->updateEstado($idSusc, 'suspendido', $idUsuarioSistema);
                    $log('WARN', "  → Suscripción #$idSusc SUSPENDIDA por 3 fallos consecutivos.");
                    enviarNotificacion($susc, $idPago, $idEmpresa, 'suspension', $suscRepo, $log);
                } else {
                    enviarNotificacion($susc, $idPago, $idEmpresa, 'cobro_fallido', $suscRepo, $log);
                }

                // Actualizar pago como fallido
                $suscRepo->updatePago($idPago, [
                    'estado'                => 'fallido',
                    'id_factura'            => $idFactura,
                    'kushki_transaction_id' => $transId,
                    'kushki_response'       => $kushkiResp,
                    'intentos'              => $intentosFallidos,
                ]);

                $errores++;
                continue;
            }

            $suscRepo->resetIntentosFallidos($idSusc);
        }

        // ── 5. Actualizar pago como exitoso ───────────────────────────────────
        $suscRepo->updatePago($idPago, [
            'estado'                => $estadoPago,
            'id_factura'            => $idFactura,
            'kushki_transaction_id' => $transId,
            'kushki_response'       => $kushkiResp,
            'intentos'              => 1,
        ]);

        // ── 6. Avanzar próximo cobro ──────────────────────────────────────────
        $proximoCobro = $suscService->calcularProximoCobro($susc['proximo_cobro'], $meses);
        $suscRepo->updateProximoCobro($idSusc, $proximoCobro);
        $log('INFO', "  → Próximo cobro: $proximoCobro");

        // ── 7. Notificar al cliente ───────────────────────────────────────────
        $susc['_monto'] = $montoTotal;
        $tipoNotif = $idFactura ? 'factura_generada' : 'cobro_exitoso';
        enviarNotificacion($susc, $idPago, $idEmpresa, $tipoNotif, $suscRepo, $log, $idFactura, $db);

        $ok++;

    } catch (\Throwable $e) {
        $log('ERROR', "  → Error en suscripción #$idSusc: " . $e->getMessage());
        $errores++;
    }
}

$duracion = round(microtime(true) - $inicio, 2);
$log('INFO', "=== Finalizado: $ok procesadas, $errores errores | {$duracion}s ===");
exit(0);

// ── Funciones auxiliares ───────────────────────────────────────────────────────

/**
 * Genera una factura de venta para la suscripción.
 */
function generarFacturaSuscripcion(array $susc, array $detalle, \PDO $db, callable $log): int
{
    // Obtener configuración de empresa
    $stmt = $db->prepare(
        "SELECT ec.*, e.ruc, e.razon_social
         FROM empresa_config ec
         JOIN empresas e ON e.id = ec.id_empresa
         WHERE ec.id_empresa = :id LIMIT 1"
    );
    $stmt->execute([':id' => $susc['id_empresa']]);
    $empresaConfig = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$empresaConfig) {
        throw new \Exception("No hay configuración de empresa para id_empresa={$susc['id_empresa']}");
    }

    // Obtener establecimiento y punto de emisión activos
    $stab = $db->prepare(
        "SELECT ep.*, pe.id AS id_punto_emision, pe.codigo AS punto_emision_codigo
         FROM establecimientos ep
         JOIN puntos_emision pe ON pe.id_establecimiento = ep.id
         WHERE ep.id_empresa = :id AND ep.estado = true AND pe.estado = true
         LIMIT 1"
    );
    $stab->execute([':id' => $susc['id_empresa']]);
    $estabConfig = $stab->fetch(\PDO::FETCH_ASSOC);

    if (!$estabConfig) {
        throw new \Exception("No hay establecimiento/punto de emisión activo para esta empresa.");
    }

    $secService  = new \App\Services\SecuencialService();
    $secuencial  = $secService->getSiguiente(
        (int) $susc['id_empresa'],
        (int) $estabConfig['id'],
        (int) $estabConfig['id_punto_emision'],
        'factura'
    );

    $detallesFactura = [];
    $totalSinImp     = 0.0;
    $totalIva        = 0.0;

    foreach ($detalle as $det) {
        $base = round((float) $det['cantidad'] * (float) $det['precio_unitario'], 2);
        $iva  = round($base * ((float) ($det['porcentaje_iva'] ?? 0) / 100), 2);
        $totalSinImp += $base;
        $totalIva    += $iva;

        $detallesFactura[] = [
            'id_producto'               => $det['id_producto'],
            'descripcion'               => $det['descripcion'] ?? $det['nombre_producto'],
            'cantidad'                  => $det['cantidad'],
            'precio_unitario'           => $det['precio_unitario'],
            'descuento'                 => 0,
            'precio_total_sin_impuesto' => $base,
            'impuestos'                 => $det['porcentaje_iva'] > 0 ? [[
                'codigo_impuesto'   => '2',
                'codigo_porcentaje' => '2',
                'tarifa'            => $det['porcentaje_iva'],
                'base_imponible'    => $base,
                'valor'             => $iva,
            ]] : [],
        ];
    }

    $importe = round($totalSinImp + $totalIva, 2);

    $facturaData = [
        'id_empresa'          => $susc['id_empresa'],
        'id_usuario'          => 0,
        'id_cliente'          => $susc['id_cliente'],
        'id_establecimiento'  => $estabConfig['id'],
        'id_punto_emision'    => $estabConfig['id_punto_emision'],
        'id_bodega'           => null,
        'fecha_emision'       => date('Y-m-d'),
        'establecimiento'     => $estabConfig['codigo'],
        'punto_emision'       => $estabConfig['punto_emision_codigo'],
        'secuencial'          => $secuencial,
        'empresa_config'      => $empresaConfig,
        'detalles'            => $detallesFactura,
        'pagos'               => [[
            'forma_pago' => $susc['forma_cobro'] === 'tarjeta' ? '16' : '20',
            'total'      => $importe,
            'plazo'      => 0,
        ]],
        'info_adicional'      => [
            ['nombre' => 'Suscripcion', 'valor' => "Periodicidad: " . ($susc['periodicidad_nombre'] ?? '')],
        ],
        'total_sin_impuestos' => $totalSinImp,
        'total_descuento'     => 0,
        'importe_total'       => $importe,
        'propina'             => 0,
        'observaciones'       => "Factura generada automáticamente por suscripción.",
    ];

    $factService = new \App\Services\modulos\FacturaVentaService(
        new \App\repositories\modulos\FacturaVentaRepository(),
        new \App\Rules\modulos\FacturaVentaRules(),
        new \App\Services\LogSistemaService()
    );

    return $factService->crear($facturaData);
}

/**
 * Envía un email de notificación al cliente de la suscripción.
 */
function enviarNotificacion(
    array $susc,
    int   $idPago,
    int   $idEmpresa,
    string $tipo,
    SuscripcionesRepository $repo,
    callable $log,
    ?int  $idFactura = null,
    ?\PDO $db = null
): void {
    $email = $susc['cliente_email'] ?? '';
    if (!$email) {
        $log('WARN', "  → Sin email para notificación de tipo '$tipo'.");
        return;
    }

    $asuntos = [
        'factura_generada'    => 'Nueva factura de suscripción generada',
        'cobro_exitoso'       => 'Cobro de suscripción procesado exitosamente',
        'cobro_fallido'       => 'No se pudo procesar el cobro de su suscripción',
        'suspension'          => 'Su suscripción ha sido suspendida',
        'vencimiento_proximo' => 'Recordatorio: próximo cobro de suscripción',
    ];

    $asunto = $asuntos[$tipo] ?? 'Notificación de suscripción';

    $data = [
        'cliente_nombre' => $susc['cliente_nombre'],
        'monto'          => number_format((float)($susc['_monto'] ?? 0), 2),
        'fecha_cobro'    => date('d-m-Y'),
        'proximo_cobro'  => $susc['proximo_cobro'],
        'periodicidad'   => $susc['periodicidad_nombre'] ?? '',
        'tipo'           => $tipo,
        'id_factura'     => $idFactura,
    ];

    require_once MVC_APP . '/helpers/mail.php';
    $enviado = enviar_correo_suscripcion($email, $asunto, $data, $tipo);

    $repo->insertNotificacion([
        'id_suscripcion' => (int) $susc['id'],
        'id_empresa'     => $idEmpresa,
        'id_pago'        => $idPago,
        'tipo'           => $tipo,
        'destinatario'   => $email,
        'asunto'         => $asunto,
        'estado'         => $enviado ? 'enviado' : 'fallido',
        'error_detalle'  => !$enviado ? ($GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error desconocido') : null,
        'id_usuario'     => 0,
    ]);

    $log($enviado ? 'INFO' : 'WARN', "  → Email '$tipo' a $email: " . ($enviado ? 'enviado' : 'falló'));
}
