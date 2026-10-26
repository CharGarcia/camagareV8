<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\models\WhatsappPlantilla;
use App\repositories\modulos\WhatsappMensajeRepository;
use App\services\WhatsappService;
use App\Services\LogSistemaService;
use Exception;

/**
 * Chat Center de WhatsApp: iniciar conversaciones con una plantilla aprobada.
 * Meta solo acepta texto libre dentro de las 24 h desde el último mensaje del cliente;
 * fuera de esa ventana (o con un número que nunca escribió) solo se puede enviar plantilla.
 */
class WhatsappChatService
{
    private const MAX_VARIABLE = 1000;

    private WhatsappMensajeRepository $repository;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repository = new WhatsappMensajeRepository();
        $this->logService = new LogSistemaService();
    }

    public function estadoVentana(int $idEmpresa, int $idChat): array
    {
        return $this->repository->getVentanaChat($idEmpresa, $idChat);
    }

    public function buscarClientes(int $idEmpresa, string $texto): array
    {
        $texto = trim($texto);
        if (mb_strlen($texto) < 2) {
            return [];
        }
        $out = [];
        foreach ($this->repository->buscarClientesConTelefono($idEmpresa, mb_substr($texto, 0, 60)) as $c) {
            $out[] = [
                'id'             => (int) $c['id'],
                'nombre'         => $c['nombre'],
                'identificacion' => $c['identificacion'],
                'telefono'       => $c['telefono'],
                'celular'        => self::celularDe((string) $c['telefono']),
            ];
        }
        return $out;
    }

    /**
     * Plantillas aprobadas con lo que el modal necesita para pedir sus variables y
     * mostrar la vista previa. Las que no se pueden enviar desde aquí (encabezado con
     * archivo, variables con nombre) se devuelven marcadas, con el motivo.
     */
    public function plantillasDisponibles(int $idEmpresa): array
    {
        $out = [];
        foreach ((new WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa) as $p) {
            $out[] = self::describirPlantilla($p);
        }
        return $out;
    }

    /**
     * Envía una plantilla a un número y la deja en el chat (lo crea si no existe).
     *
     * @return array{id_chat:int, telefono:string, nombre:string}
     */
    public function enviarPlantilla(int $idEmpresa, int $idUsuario, array $datos): array
    {
        $telefono = self::normalizarTelefono((string) ($datos['telefono'] ?? ''));
        if ($telefono === '') {
            throw new Exception('El celular no es válido. Escriba los 10 dígitos, por ejemplo 0958924835.');
        }
        $nombre = mb_substr(trim((string) ($datos['nombre'] ?? '')), 0, 150);

        $plantilla = null;
        foreach ($this->plantillasDisponibles($idEmpresa) as $p) {
            if ($p['nombre'] === (string) ($datos['plantilla'] ?? '')) {
                $plantilla = $p;
                break;
            }
        }
        if (!$plantilla) {
            throw new Exception('La plantilla no existe o ya no está aprobada por Meta.');
        }
        if (!$plantilla['soportada']) {
            throw new Exception('Esta plantilla no se puede enviar desde el chat: ' . $plantilla['motivo']);
        }

        $varsHeader = self::leerVariables($datos['vars_header'] ?? [], $plantilla['vars_header'], 'del encabezado');
        $varsBody   = self::leerVariables($datos['vars_body'] ?? [], $plantilla['vars_body'], 'del mensaje');
        $varBoton   = null;
        if ($plantilla['boton_url'] !== null) {
            $varBoton = trim((string) ($datos['var_boton'] ?? ''));
            if ($varBoton === '') {
                throw new Exception('Complete el final del enlace del botón «' . $plantilla['boton_url']['texto'] . '».');
            }
        }

        $componentes = [];
        if ($varsHeader) {
            $componentes[] = ['type' => 'header', 'parameters' => self::parametros($varsHeader)];
        }
        if ($varsBody) {
            $componentes[] = ['type' => 'body', 'parameters' => self::parametros($varsBody)];
        }
        if ($varBoton !== null) {
            $componentes[] = [
                'type' => 'button', 'sub_type' => 'url', 'index' => (string) $plantilla['boton_url']['indice'],
                'parameters' => [['type' => 'text', 'text' => $varBoton]],
            ];
        }

        $resp = (new WhatsappService())->sendTemplateMessage($idEmpresa, $telefono, $plantilla['nombre'], $plantilla['idioma'], $componentes);
        if (empty($resp['success'])) {
            throw new Exception((string) ($resp['message'] ?? 'Meta no aceptó el mensaje.'));
        }

        $texto = self::reemplazar($plantilla['cuerpo'], $varsBody);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idChat = $this->repository->getOrCreateChat($idEmpresa, $telefono, $nombre, mb_substr($texto, 0, 50), false);
            $this->repository->saveMessage($idEmpresa, $idChat, 'OUT', $telefono, 'template', [
                'template'      => $plantilla['nombre'],
                'variables'     => $varsBody,
                'template_text' => $texto,
                'header_type'   => $plantilla['encabezado'] !== '' ? 'text' : 'none',
                'header_text'   => self::reemplazar($plantilla['encabezado'], $varsHeader),
                'footer_text'   => $plantilla['pie'],
                'buttons'       => array_map(static fn($b) => ['text' => $b], $plantilla['botones']),
            ], $resp['data']['messages'][0]['id'] ?? null, 'sent');
            $this->logService->registrar($idUsuario, $idEmpresa, 'whatsapp_plantilla_chat', 'whatsapp_chats', $idChat, null, [
                'telefono' => $telefono, 'plantilla' => $plantilla['nombre'], 'variables' => $varsBody,
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // El mensaje ya salió: no se puede deshacer, solo avisar que no quedó en el historial.
            error_log('[WhatsApp chat] Plantilla enviada pero no guardada: ' . $e->getMessage());
            throw new Exception('El mensaje se envió, pero no se pudo guardar en el chat.');
        }

        return ['id_chat' => $idChat, 'telefono' => $telefono, 'nombre' => $nombre];
    }

    // ─────────────────────────────────────────────────────────────────────

    private static function describirPlantilla(array $p): array
    {
        $d = [
            'nombre' => (string) $p['nombre'], 'idioma' => (string) $p['idioma'], 'categoria' => (string) ($p['categoria'] ?? ''),
            'encabezado' => '', 'cuerpo' => '', 'pie' => '', 'botones' => [],
            'vars_header' => 0, 'vars_body' => 0, 'ejemplos_header' => [], 'ejemplos_body' => [],
            'boton_url' => null, 'soportada' => true, 'motivo' => '',
        ];
        foreach (json_decode((string) ($p['componentes'] ?? '[]'), true) ?: [] as $c) {
            switch (strtoupper((string) ($c['type'] ?? ''))) {
                case 'HEADER':
                    if (strtoupper((string) ($c['format'] ?? 'TEXT')) !== 'TEXT') {
                        $d['soportada'] = false;
                        $d['motivo'] = 'su encabezado lleva un archivo (imagen, documento o video).';
                        break;
                    }
                    $d['encabezado'] = (string) ($c['text'] ?? '');
                    $d['vars_header'] = self::contarVariables($d['encabezado']);
                    $d['ejemplos_header'] = array_values((array) ($c['example']['header_text'] ?? []));
                    break;
                case 'BODY':
                    $d['cuerpo'] = (string) ($c['text'] ?? '');
                    $d['vars_body'] = self::contarVariables($d['cuerpo']);
                    $d['ejemplos_body'] = array_values((array) ($c['example']['body_text'][0] ?? []));
                    break;
                case 'FOOTER':
                    $d['pie'] = (string) ($c['text'] ?? '');
                    break;
                case 'BUTTONS':
                    foreach (array_values($c['buttons'] ?? []) as $i => $b) {
                        $d['botones'][] = (string) ($b['text'] ?? '');
                        $url = (string) ($b['url'] ?? '');
                        if (strtoupper((string) ($b['type'] ?? '')) === 'URL' && str_contains($url, '{{1}}')) {
                            $base = str_replace('{{1}}', '', $url);
                            // Meta guarda el ejemplo como URL completa; al usuario solo se le pide el final.
                            $ejemplo = (string) (($b['example'] ?? [])[0] ?? '');
                            $d['boton_url'] = [
                                'indice'  => $i,
                                'texto'   => (string) ($b['text'] ?? ''),
                                'base'    => $base,
                                'ejemplo' => str_starts_with($ejemplo, $base) ? substr($ejemplo, strlen($base)) : $ejemplo,
                            ];
                        }
                    }
                    break;
            }
        }
        if ($d['soportada'] && preg_match('/{{\s*[a-zA-Z_]\w*\s*}}/', $d['encabezado'] . ' ' . $d['cuerpo'])) {
            $d['soportada'] = false;
            $d['motivo'] = 'usa variables con nombre en lugar de {{1}}, {{2}}…';
        }
        return $d;
    }

    /** Número de variables distintas {{n}} de un texto. */
    private static function contarVariables(string $texto): int
    {
        return preg_match_all('/{{(\d+)}}/', $texto, $m) ? count(array_unique($m[1])) : 0;
    }

    private static function leerVariables($valores, int $cantidad, string $donde): array
    {
        $valores = is_array($valores) ? array_values($valores) : [];
        $out = [];
        for ($i = 0; $i < $cantidad; $i++) {
            // Meta rechaza saltos de línea, tabs o más de 4 espacios seguidos dentro de una variable.
            $v = trim(preg_replace('/\s+/u', ' ', (string) ($valores[$i] ?? '')));
            if ($v === '') {
                throw new Exception('Complete la variable {{' . ($i + 1) . '}} ' . $donde . '.');
            }
            $out[] = mb_substr($v, 0, self::MAX_VARIABLE);
        }
        return $out;
    }

    private static function parametros(array $vars): array
    {
        return array_map(static fn($v) => ['type' => 'text', 'text' => $v], $vars);
    }

    private static function reemplazar(string $texto, array $vars): string
    {
        foreach ($vars as $i => $v) {
            $texto = str_replace('{{' . ($i + 1) . '}}', $v, $texto);
        }
        return $texto;
    }

    /** 0958924835 → 593958924835. También acepta números ya internacionales (11 a 15 dígitos). */
    public static function normalizarTelefono(string $tel): string
    {
        $d = preg_replace('/\D/', '', $tel);
        if (preg_match('/^09\d{8}$/', $d)) {
            return '593' . substr($d, 1);
        }
        if (preg_match('/^9\d{8}$/', $d)) {
            return '593' . $d;
        }
        return preg_match('/^[1-9]\d{10,14}$/', $d) ? $d : '';
    }

    /** Primer celular ecuatoriano (09…) dentro del teléfono del cliente, que puede traer varios números. */
    public static function celularDe(string $telefono): string
    {
        // Los números se separan por / , ; o letras («0999… / 022…», «cel 0999…»); dentro de
        // cada uno se ignoran espacios, guiones y paréntesis («099-123-4567»).
        foreach (preg_split('/[\/,;|]+|\p{L}+/u', $telefono) as $trozo) {
            $num = preg_replace('/\D/', '', $trozo);
            if (preg_match('/^09\d{8}$/', $num)) {
                return $num;
            }
            if (preg_match('/^5939(\d{8})$/', $num, $m)) {
                return '09' . $m[1];
            }
        }
        return '';
    }
}
