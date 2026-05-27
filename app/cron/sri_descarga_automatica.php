<?php

/**
 * CRON: Descarga automática de comprobantes SRI recibidos
 * ========================================================
 * Procesa todas las empresas con descarga activa configurada.
 * Descarga del mes actual. Si hoy es día 1, también descarga el mes anterior.
 * Solo registra comprobantes no existentes en el sistema.
 *
 * Configuración recomendada (crontab Linux / Digital Ocean):
 *   # Cada noche a las 02:00
 *   0 2 * * *  php /var/www/sistema/app/cron/sri_descarga_automatica.php >> /var/log/sri_descarga.log 2>&1
 *
 * Windows (Programador de tareas):
 *   Programa  : C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\sistema\app\cron\sri_descarga_automatica.php
 *   Trigger   : Diario a las 02:00
 *
 * Variables de entorno opcionales:
 *   SRI_DESCARGA_DEBUG=1   → salida detallada
 *   SRI_DESCARGA_EMPRESA=5 → procesar solo una empresa (para pruebas)
 */

declare(strict_types=1);

define('MVC_ROOT', dirname(__DIR__, 2));
define('MVC_APP',  MVC_ROOT . '/app');

if (!file_exists(MVC_ROOT . '/bootstrap.php')) {
    fwrite(STDERR, "[SRI DESCARGA] No se encontró bootstrap.php en " . MVC_ROOT . "\n");
    exit(1);
}

require_once MVC_ROOT . '/bootstrap.php';

use App\models\SriConfigDescarga;
use App\Services\modulos\SriDescargaAutomaticaService;

$debug     = (bool) getenv('SRI_DESCARGA_DEBUG');
$soloEmp   = (int)  getenv('SRI_DESCARGA_EMPRESA');
$inicio    = microtime(true);

$log = function (string $nivel, string $msg) use ($debug): void {
    $ts = date('Y-m-d H:i:s');
    if ($nivel !== 'DEBUG' || $debug) {
        echo "[$ts][$nivel] $msg\n";
    }
};

$log('INFO', '=== SRI Descarga Automática iniciada ===');
$log('INFO', 'Fecha: ' . date('d-m-Y') . ' | Hora: ' . date('H:i:s'));

// Obtener empresas activas
$configModel = new SriConfigDescarga();
$empresas    = $configModel->getActivas();

if (empty($empresas)) {
    $log('INFO', 'No hay empresas con descarga automática activa. Fin.');
    exit(0);
}

// Filtrar por empresa específica si se indicó
if ($soloEmp > 0) {
    $empresas = array_filter($empresas, fn($e) => (int) $e['id_empresa'] === $soloEmp);
    $empresas = array_values($empresas);
    $log('INFO', "Modo test: procesando solo empresa #$soloEmp");
}

$log('INFO', 'Empresas a procesar: ' . count($empresas));

$totalEmpresasOk  = 0;
$totalEmpresasErr = 0;

foreach ($empresas as $cfg) {
    $idEmpresa = (int) $cfg['id_empresa'];
    $log('INFO', "─── Empresa #$idEmpresa ───");

    try {
        $svc = new SriDescargaAutomaticaService($debug);
        $res = $svc->ejecutarParaEmpresa($idEmpresa, 0); // idUsuario=0 = proceso automático

        if ($res['ok']) {
            $log('INFO', sprintf(
                '  ✓ Encontradas: %d | Nuevas: %d | Existentes: %d | Errores: %d | %ds',
                $res['total_encontrados'],
                $res['total_nuevos'],
                $res['total_existentes'],
                $res['total_errores'],
                $res['duracion_seg']
            ));

            if ($debug) {
                foreach ($svc->getDebugLog() as $dl) {
                    $log('DEBUG', '    ' . $dl);
                }
            }

            $totalEmpresasOk++;
        } else {
            $log('WARN', '  ✗ Error: ' . ($res['error'] ?? 'desconocido'));
            $totalEmpresasErr++;
        }

    } catch (\Throwable $e) {
        $log('ERROR', "  ✗ Excepción empresa #$idEmpresa: " . $e->getMessage());
        $totalEmpresasErr++;
    }

    // Pausa entre empresas para no saturar el portal SRI
    sleep(3);
}

$duracion = round(microtime(true) - $inicio, 1);
$log('INFO', "=== Finalizado: $totalEmpresasOk ok, $totalEmpresasErr errores | {$duracion}s ===");
exit(0);
