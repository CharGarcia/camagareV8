<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\CrmPrivacidadService;

/**
 * Callbacks PÚBLICOS de privacidad de la app de Meta (registrado en
 * $publicControllers). Diseño: docs/diseno/crm-redes-fase1.md §12.
 *
 *  POST /instagram-datos/eliminar      → «Data Deletion Request URL» de la app.
 *       Responde {url, confirmation_code} como exige Meta.
 *  POST /instagram-datos/desautorizar  → «Deauthorize Callback URL»: la cuenta se desconecta.
 *  GET  /instagram-datos/estado?codigo → página legible con el estado de la solicitud
 *       (el código lo entrega Meta a la persona; no muestra datos personales).
 *
 * Las dos primeras solo aceptan un signed_request firmado con el App Secret.
 */
class InstagramDatosController extends Controller
{
    public function eliminar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }
        $svc = CrmPrivacidadService::crear();
        if (!$svc->disponible()) {
            http_response_code(503);
            echo json_encode(['error' => 'Servicio no disponible.']);
            exit;
        }
        try {
            $r = $svc->solicitarEliminacion((string) ($_POST['signed_request'] ?? ''), url_absoluta('instagram-datos/estado'));
            echo json_encode($r, JSON_UNESCAPED_SLASHES);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Solicitud no válida.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo registrar la solicitud.']);
        }
        exit;
    }

    public function desautorizar(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            http_response_code(405);
            exit;
        }
        $svc = CrmPrivacidadService::crear();
        if (!$svc->disponible()) {
            http_response_code(503);
            exit;
        }
        try {
            $svc->desautorizar((string) ($_POST['signed_request'] ?? ''));
            echo 'OK';
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
        }
        exit;
    }

    /** Página pública con el estado de la solicitud de eliminación. */
    public function estado(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        $svc = CrmPrivacidadService::crear();
        $codigo = (string) ($_GET['codigo'] ?? '');
        $sol = $svc->disponible() ? $svc->estado($codigo) : null;
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $fecha = fn($v) => $v ? date('d-m-Y H:i:s', strtotime((string) $v)) : '-';
        $estados = [
            'recibida'   => ['En proceso', 'Recibimos su solicitud y estamos eliminando la información.'],
            'completada' => ['Completada', 'Eliminamos la información que recibimos de Instagram asociada a su cuenta: mensajes, comentarios, archivos adjuntos y datos de perfil.'],
            'sin_datos'  => ['Completada', 'No teníamos información de Instagram asociada a su cuenta. No queda nada por eliminar.'],
            'error'      => ['En revisión', 'La eliminación no pudo terminar automáticamente. Nuestro equipo la completará y no se requiere ninguna acción de su parte.'],
        ];
        [$titulo, $texto] = $sol ? ($estados[$sol['estado']] ?? $estados['recibida']) : ['No encontrada', 'No encontramos una solicitud con ese código. Revise que esté completo.'];
        $tipo = ($sol['tipo'] ?? '') === 'desautorizacion' ? 'Desvinculación de la aplicación' : 'Eliminación de datos';
        ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Estado de su solicitud</title>
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background:#f4f6f8; color:#212529; }
        .caja { max-width:560px; margin:48px auto; background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); padding:28px 24px; }
        h1 { font-size:1.25rem; margin:0 0 4px; }
        .sub { color:#6c757d; font-size:.9rem; margin-bottom:20px; }
        .estado { display:inline-block; padding:4px 12px; border-radius:999px; background:#e7f1ff; color:#0d6efd; font-weight:600; font-size:.9rem; }
        dl { display:grid; grid-template-columns:auto 1fr; gap:6px 16px; font-size:.9rem; margin:20px 0 0; }
        dt { color:#6c757d; } dd { margin:0; word-break:break-all; }
        p { line-height:1.5; }
        @media (max-width:600px) { .caja { margin:16px; } }
    </style>
</head>
<body>
    <div class="caja">
        <h1><?= $h($tipo) ?></h1>
        <div class="sub">CaMaGaRe · datos recibidos de Instagram</div>
        <span class="estado"><?= $h($titulo) ?></span>
        <p><?= $h($texto) ?></p>
        <?php if ($sol): ?>
        <dl>
            <dt>Código</dt><dd><?= $h($sol['codigo']) ?></dd>
            <dt>Recibida</dt><dd><?= $h($fecha($sol['recibido_at'])) ?></dd>
            <dt>Terminada</dt><dd><?= $h($fecha($sol['completado_at'])) ?></dd>
        </dl>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
