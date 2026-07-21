<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\DocumentosLegalesRepository;
use App\Services\LogSistemaService;

/**
 * Lógica de negocio de los documentos legales (Acuerdo de uso de datos y
 * Contrato de uso del sistema).
 *
 * - Se envían automáticamente al CREAR una empresa.
 * - Se pueden reenviar manualmente a empresas ya existentes.
 * - La aceptación se registra por token con fecha, IP y navegador (evidencia).
 *
 * Transacciones + auditoría en log_sistema.
 */
class DocumentosLegalesService
{
    private DocumentosLegalesRepository $repo;
    private DocumentosLegalesPdfService $pdf;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->repo = new DocumentosLegalesRepository();
        $this->pdf  = new DocumentosLegalesPdfService();
        $this->log  = new LogSistemaService();
    }

    // ─── Configuración de textos ────────────────────────────────────────────

    public function getVigentes(): array
    {
        return $this->repo->getVigentes();
    }

    public function getHistorial(string $tipo): array
    {
        return $this->repo->getHistorial($tipo);
    }

    /** Publica una nueva versión del texto legal (la anterior deja de ser vigente). */
    public function publicarVersion(string $tipo, string $titulo, string $contenido, int $idUsuario): int
    {
        if (!in_array($tipo, DocumentosLegalesRepository::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }
        $titulo    = trim($titulo);
        $contenido = trim($contenido);
        if ($titulo === '' || $contenido === '') {
            throw new \InvalidArgumentException('El título y el contenido son obligatorios.');
        }

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->publicarNuevaVersion($tipo, $titulo, $contenido, $idUsuario);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        $this->log->registrar($idUsuario, null, 'publicar_version', 'documentos_legales', $id, null, [
            'tipo'   => $tipo,
            'titulo' => $titulo,
        ]);

        return $id;
    }

    // ─── Envío ──────────────────────────────────────────────────────────────

    /**
     * Genera los PDFs y los envía al correo de la empresa, registrando el envío.
     *
     * @throws \RuntimeException si falta el correo, faltan los textos o falla el envío
     * @return array{id_envio:int, correo:string}
     */
    public function enviarAEmpresa(int $idEmpresa, int $idUsuario): array
    {
        $empresa = $this->repo->getEmpresaParaDocumento($idEmpresa);
        if (!$empresa) {
            throw new \RuntimeException('La empresa no existe.');
        }

        $correo = trim((string) ($empresa['mail'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('La empresa no tiene un correo válido registrado. Regístrelo para poder enviar los documentos.');
        }

        $vigentes = $this->repo->getVigentes();
        $acuerdo  = $vigentes['acuerdo_datos'] ?? null;
        $contrato = $vigentes['contrato_uso'] ?? null;
        if (!$acuerdo || !$contrato) {
            throw new \RuntimeException('No hay textos legales configurados. Configúrelos en Configuración → Documentos legales.');
        }

        $token = bin2hex(random_bytes(24));

        // PDFs temporales (se eliminan tras el envío; se pueden regenerar porque
        // el envío queda ligado a la VERSIÓN exacta del texto).
        $dir = MVC_ROOT . '/storage/documentos_legales';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $sistemaNombre = $this->getNombreSistema();
        $rutaAcuerdo   = $dir . '/' . $token . '_acuerdo.pdf';
        $rutaContrato  = $dir . '/' . $token . '_contrato.pdf';

        $this->pdf->generar($acuerdo, $empresa, 'F', $rutaAcuerdo, $sistemaNombre);
        $this->pdf->generar($contrato, $empresa, 'F', $rutaContrato, $sistemaNombre);

        $adjuntos = [
            $rutaAcuerdo  => $this->pdf->nombreArchivo($acuerdo, $empresa),
            $rutaContrato => $this->pdf->nombreArchivo($contrato, $empresa),
        ];

        $idEnvio = 0;
        $this->repo->beginTransaction();
        try {
            $idEnvio = $this->repo->registrarEnvio(
                $idEmpresa,
                (int) $acuerdo['id'],
                (int) $contrato['id'],
                $correo,
                $token,
                $idUsuario
            );

            require_once MVC_APP . '/helpers/mail.php';
            $ok = enviar_correo_documentos_legales($correo, [
                'empresa_nombre'  => (string) ($empresa['nombre'] ?? ''),
                'empresa_ruc'     => (string) ($empresa['ruc'] ?? ''),
                'acuerdo_titulo'  => (string) $acuerdo['titulo'],
                'contrato_titulo' => (string) $contrato['titulo'],
                'url_aceptacion'  => $this->urlAceptacion($token),
                'sistema_nombre'  => $sistemaNombre,
            ], $adjuntos);

            if (!$ok) {
                $err = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo.';
                throw new \RuntimeException($err);
            }

            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            $this->limpiar([$rutaAcuerdo, $rutaContrato]);
            throw $e;
        }

        $this->limpiar([$rutaAcuerdo, $rutaContrato]);

        $this->log->registrar($idUsuario, $idEmpresa, 'enviar_documentos_legales', 'empresas_documentos_envios', $idEnvio, null, [
            'correo'           => $correo,
            'acuerdo_version'  => $acuerdo['version'],
            'contrato_version' => $contrato['version'],
        ]);

        return ['id_envio' => $idEnvio, 'correo' => $correo];
    }

    /**
     * Envío "silencioso" usado al crear la empresa: no interrumpe la creación
     * si el correo falla (p. ej. empresa sin mail). Devuelve el error si lo hubo.
     */
    public function enviarAEmpresaSinFallar(int $idEmpresa, int $idUsuario): ?string
    {
        try {
            $this->enviarAEmpresa($idEmpresa, $idUsuario);

            return null;
        } catch (\Throwable $e) {
            error_log('DocumentosLegales: no se pudo enviar a empresa ' . $idEmpresa . ': ' . $e->getMessage());

            return $e->getMessage();
        }
    }

    // ─── Aceptación pública (por token) ─────────────────────────────────────

    public function getEnvioPorToken(string $token): ?array
    {
        $token = trim($token);

        return $token === '' ? null : $this->repo->getEnvioPorToken($token);
    }

    /** Documentos (con placeholders resueltos) para mostrarlos en la página pública. */
    public function getDocumentosDeEnvio(array $envio): array
    {
        $empresa = [
            'nombre'        => $envio['empresa_nombre'] ?? '',
            'ruc'           => $envio['empresa_ruc'] ?? '',
            'direccion'     => $envio['empresa_direccion'] ?? '',
            'nom_rep_legal' => $envio['empresa_representante'] ?? '',
            'mail'          => $envio['correo_destino'] ?? '',
        ];
        $sistemaNombre = $this->getNombreSistema();

        $out = [];
        foreach (['id_acuerdo', 'id_contrato'] as $campo) {
            $id = (int) ($envio[$campo] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $doc = $this->repo->getPorId($id);
            if ($doc) {
                $doc['contenido_resuelto'] = $this->pdf->resolverPlaceholders((string) $doc['contenido'], $empresa, $sistemaNombre);
                $out[] = $doc;
            }
        }

        return $out;
    }

    /**
     * Registra la aceptación. Guarda IP y user agent como evidencia.
     *
     * @throws \RuntimeException
     */
    public function aceptarPorToken(string $token, string $nombre, string $identificacion): array
    {
        $envio = $this->getEnvioPorToken($token);
        if (!$envio) {
            throw new \RuntimeException('El enlace no es válido.');
        }
        if (($envio['estado'] ?? '') === 'aceptado') {
            throw new \RuntimeException('Estos documentos ya fueron aceptados el ' . date('d-m-Y H:i:s', strtotime((string) $envio['aceptado_at'])) . '.');
        }

        $nombre         = trim($nombre);
        $identificacion = trim($identificacion);
        if ($nombre === '') {
            throw new \RuntimeException('Indique el nombre de quien acepta.');
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        $ok = $this->repo->marcarAceptado((int) $envio['id'], $nombre, $identificacion, (string) $ip, $ua);
        if (!$ok) {
            throw new \RuntimeException('No se pudo registrar la aceptación.');
        }

        $this->log->registrar(0, (int) $envio['id_empresa'], 'aceptar_documentos_legales', 'empresas_documentos_envios', (int) $envio['id'], null, [
            'nombre'         => $nombre,
            'identificacion' => $identificacion,
            'ip'             => $ip,
        ]);

        return ['empresa' => $envio['empresa_nombre'] ?? '', 'nombre' => $nombre];
    }

    // ─── Consultas para la UI ───────────────────────────────────────────────

    public function getEstadoPorEmpresa(): array
    {
        return $this->repo->getEstadoPorEmpresa();
    }

    public function getEnviosDeEmpresa(int $idEmpresa): array
    {
        return $this->repo->getEnviosDeEmpresa($idEmpresa);
    }

    /**
     * Regenera el PDF de un documento legal para una empresa.
     *
     * Usa la VERSIÓN que realmente se le envió (según el último envío); si nunca
     * se le envió, usa la versión vigente. Así el PDF descargado siempre coincide
     * con lo que la empresa recibió y aceptó.
     *
     * @param string $tipo 'acuerdo_datos' | 'contrato_uso'
     * @return array{bin:string, nombre:string}
     */
    public function generarPdfParaEmpresa(int $idEmpresa, string $tipo): array
    {
        if (!in_array($tipo, \App\repositories\DocumentosLegalesRepository::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }

        $empresa = $this->repo->getEmpresaParaDocumento($idEmpresa);
        if (!$empresa) {
            throw new \RuntimeException('La empresa no existe.');
        }

        $doc = null;
        $envios = $this->repo->getEnviosDeEmpresa($idEmpresa);
        if (!empty($envios)) {
            $ultimo = $envios[0];
            $campo  = $tipo === 'acuerdo_datos' ? 'id_acuerdo' : 'id_contrato';
            $idDoc  = (int) ($ultimo[$campo] ?? 0);
            if ($idDoc > 0) {
                $doc = $this->repo->getPorId($idDoc);
            }
        }
        if (!$doc) {
            $doc = $this->repo->getVigente($tipo);
        }
        if (!$doc) {
            throw new \RuntimeException('No hay un documento configurado para ese tipo.');
        }

        $bin = $this->pdf->generar($doc, $empresa, 'S', '', $this->getNombreSistema());

        return ['bin' => $bin, 'nombre' => $this->pdf->nombreArchivo($doc, $empresa)];
    }

    // ─── Internos ───────────────────────────────────────────────────────────

    private function urlAceptacion(string $token): string
    {
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base   = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');

        if ($host === '') {
            return $base . '/aceptar-documentos/' . $token;
        }

        return $scheme . '://' . $host . $base . '/aceptar-documentos/' . $token;
    }

    private function getNombreSistema(): string
    {
        try {
            $cfg = require MVC_CONFIG . '/app.php';

            return (string) ($cfg['app_name'] ?? 'CaMaGaRe');
        } catch (\Throwable $e) {
            return 'CaMaGaRe';
        }
    }

    private function limpiar(array $rutas): void
    {
        foreach ($rutas as $r) {
            if ($r !== '' && is_file($r)) {
                @unlink($r);
            }
        }
    }
}
