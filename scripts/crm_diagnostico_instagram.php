<?php
/**
 * Diagnóstico CLI — webhooks de una cuenta de Instagram conectada al CRM.
 *
 * Uso:
 *   php scripts/crm_diagnostico_instagram.php {id_cuenta_instagram} [--suscribir]
 *
 * Pregunta a Meta, con el acceso guardado del canal: el perfil, los permisos
 * concedidos y a qué apps está suscrita la cuenta (y con qué campos). Con
 * --suscribir vuelve a suscribirla a los campos de config/app.php
 * (meta_instagram.webhook_fields) y repite la consulta. No imprime tokens.
 * Sirve cuando los mensajes no llegan a la Bandeja y el log de Apache no muestra
 * POST /instagram-webhook (Meta no está enviando).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers\CryptoHelper;
use App\Services\InstagramApiService;
use App\repositories\modulos\CrmCanalRepository;

$igId = preg_replace('/\D/', '', (string) ($argv[1] ?? ''));
$suscribir = in_array('--suscribir', $argv, true);
if ($igId === '') {
    fwrite(STDERR, "Indique el id de la cuenta de Instagram (solo números).\n");
    exit(1);
}

$repo = new CrmCanalRepository();
$canal = $repo->buscarPorExterno('instagram', $igId);
if (!$canal) {
    fwrite(STDERR, "No hay un canal de Instagram con id {$igId}.\n");
    exit(1);
}
$idCanal = (int) $canal['id'];
$idEmpresa = (int) $canal['id_empresa'];
$canal = $repo->getPorId($idCanal, $idEmpresa) ?? $canal;
echo "Canal #{$idCanal} · empresa {$idEmpresa} · @" . ($canal['usuario'] ?? '?') . ' · estado ' . ($canal['estado'] ?? '?') . "\n";

$token = CryptoHelper::desencriptar((string) $repo->getTokenCifrado($idCanal, $idEmpresa));
if ($token === '') {
    fwrite(STDERR, "El canal no tiene un acceso guardado.\n");
    exit(1);
}
$api = InstagramApiService::paraEmpresa($idEmpresa);
echo 'Modo: ' . ($api->unificado() ? 'Unified login' : 'Instagram login') . "\n\n";

$paso = static function (string $titulo, callable $fn): void {
    echo "== {$titulo}\n";
    try {
        $r = $fn();
        echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    } catch (\Throwable $e) {
        echo 'ERROR: ' . InstagramApiService::taparTokens($e->getMessage()) . "\n\n";
    }
};

$paso('Perfil', fn() => $api->perfil($token, $igId));
$paso('Permisos concedidos', fn() => $api->permisosConcedidos($token));
$paso('Apps suscritas (webhooks)', fn() => $api->suscripcionesWebhook($token, $igId));
if ($suscribir) {
    $paso('Volver a suscribir', fn() => ['ok' => $api->suscribirWebhooks($token, $igId)]);
    $paso('Apps suscritas (después)', fn() => $api->suscripcionesWebhook($token, $igId));
}
