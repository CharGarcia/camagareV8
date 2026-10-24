<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\FirmaCaducidadRepository;

/**
 * Aviso por correo a la empresa cuando su firma electrónica está por caducar.
 *
 * Complementa al ícono del navbar (ContadoresNavbarService, umbral 5 días), que
 * solo ve quien entra al sistema: este aviso llega al correo registrado en la
 * ficha de la empresa (`empresas.mail`) aunque nadie haya iniciado sesión.
 *
 * Dos avisos por firma:
 *   1. Previo: DIAS_AVISO_SOCIEDAD días antes si la empresa es sociedad,
 *      DIAS_AVISO_NATURAL si es persona natural (tercer dígito del RUC; si no se
 *      puede leer, el tipo de contribuyente de la ficha).
 *   2. Con enlace: a DIAS_AVISO_ENLACE días (o ya caducada, hasta DIAS_CADUCADA_MAX
 *      días después), con el enlace del módulo Firmas Electrónicas de la empresa que
 *      le vende las firmas (su controladora de suscripciones o la administradora), para
 *      que el mismo cliente solicite su firma nueva.
 *
 * Es un servicio FIJO (no configurable): corre montado sobre `cron_runner.php`
 * (cada minuto) y se autolimita a UNA revisión por día a partir de HORA_ENVIO,
 * igual que TareaRecordatorioService. Cada aviso se envía UNA vez por firma: el
 * envío queda registrado en log_sistema (tabla empresa_firma, acción
 * aviso_caducidad_correo / aviso_caducidad_enlace) y esa fila es la marca.
 */
class FirmaCaducidadAvisoService
{
    /** Hora del día (0-23, zona del servidor = America/Guayaquil) a partir de la cual se revisa. */
    private const HORA_ENVIO = 6;

    /** Primer aviso: días antes del vencimiento según el tipo de contribuyente. */
    public const DIAS_AVISO_SOCIEDAD = 15;
    public const DIAS_AVISO_NATURAL  = 5;

    /** Segundo aviso, con el enlace para solicitar la firma nueva. */
    public const DIAS_AVISO_ENLACE = 2;

    /**
     * Si la firma ya caducó, avisar igual (la empresa pudo no recibir el aviso previo
     * por SMTP caído, cron detenido, etc.), pero solo hasta estos días después de la
     * fecha de caducidad: así, al desplegar, no se envían correos por firmas vencidas
     * hace meses que la empresa ya sabe que están fuera de uso.
     */
    public const DIAS_CADUCADA_MAX = 7;

    private FirmaCaducidadRepository $repo;
    private LogSistemaService $log;

    public function __construct(
        ?FirmaCaducidadRepository $repo = null,
        ?LogSistemaService $log = null
    ) {
        $this->repo = $repo ?? new FirmaCaducidadRepository();
        $this->log  = $log ?? new LogSistemaService();
    }

    /** Archivo-marca con la última fecha (YYYY-MM-DD) en que se hizo la revisión diaria. */
    private function archivoMarca(): string
    {
        return MVC_ROOT . '/storage/cron/firma_caducidad_aviso.last';
    }

    private function getUltimaFecha(): string
    {
        $f = $this->archivoMarca();
        return is_file($f) ? trim((string) @file_get_contents($f)) : '';
    }

    private function setUltimaFecha(string $fecha): void
    {
        $dir = dirname($this->archivoMarca());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->archivoMarca(), $fecha);
    }

    /**
     * Llamado por el cron cada minuto. Revisa UNA vez al día, a partir de HORA_ENVIO.
     *
     * Si un correo falla (SMTP caído), la firma NO queda marcada en log_sistema y se
     * vuelve a intentar en la revisión del día siguiente, sin martillar el SMTP cada minuto.
     *
     * @return array{ejecutado:bool,firmas:int,correos:int,sin_correo:int,fallidos:int}
     */
    public function ejecutarSiCorresponde(): array
    {
        $out = ['ejecutado' => false, 'firmas' => 0, 'correos' => 0, 'sin_correo' => 0, 'fallidos' => 0];

        if ((int) date('G') < self::HORA_ENVIO) {
            return $out;
        }
        $hoy = date('Y-m-d');
        if ($this->getUltimaFecha() === $hoy) {
            return $out;
        }

        $res = $this->enviarAvisos();
        $this->setUltimaFecha($hoy);

        return ['ejecutado' => true] + $res;
    }

    /**
     * Sociedad (incluye sector público) o persona natural. Manda el tercer dígito del RUC
     * (SRI: 9 sociedad privada, 6 sector público, 0-5 persona natural); si el RUC no es
     * legible, el tipo de contribuyente de la ficha (PN / PNOC = persona natural).
     */
    public static function esSociedad(string $ruc, ?string $tipoCodigo): bool
    {
        $d = preg_replace('/\D/', '', $ruc);
        if (strlen($d) === 13) {
            return in_array($d[2], ['6', '9'], true);
        }
        return !in_array(strtoupper(trim((string) $tipoCodigo)), ['PN', 'PNOC'], true);
    }

    /**
     * Qué aviso le toca hoy a una firma: 'enlace', 'previo' o null.
     * Si el primer aviso no alcanzó a salir (cron detenido) y ya faltan DIAS_AVISO_ENLACE
     * días o menos, solo sale el del enlace.
     */
    public static function avisoQueToca(int $dias, bool $esSociedad, bool $avisadoPrevio, bool $avisadoEnlace): ?string
    {
        if ($dias <= self::DIAS_AVISO_ENLACE) {
            return ($dias >= -self::DIAS_CADUCADA_MAX && !$avisadoEnlace) ? 'enlace' : null;
        }
        $umbral = $esSociedad ? self::DIAS_AVISO_SOCIEDAD : self::DIAS_AVISO_NATURAL;
        return ($dias <= $umbral && !$avisadoPrevio) ? 'previo' : null;
    }

    /**
     * Envía a cada empresa el aviso que le toca hoy (ver avisoQueToca). Puede invocarse
     * manualmente (pruebas) sin el guard diario.
     *
     * @return array{firmas:int,correos:int,sin_correo:int,fallidos:int}
     */
    public function enviarAvisos(): array
    {
        $out = ['firmas' => 0, 'correos' => 0, 'sin_correo' => 0, 'fallidos' => 0];

        $max    = max(self::DIAS_AVISO_SOCIEDAD, self::DIAS_AVISO_NATURAL, self::DIAS_AVISO_ENLACE);
        $firmas = $this->repo->getFirmasPorVencer($max, self::DIAS_CADUCADA_MAX);
        if ($firmas === []) {
            return $out;
        }

        require_once MVC_APP . '/helpers/mail.php';
        $bool = static fn($v) => in_array($v, [true, 't', 1, '1'], true);

        foreach ($firmas as $f) {
            $idEmpresa = (int) ($f['id_empresa'] ?? 0);
            $idFirma   = (int) ($f['id_firma'] ?? 0);
            $dias      = (int) ($f['dias'] ?? 0);
            $sociedad  = self::esSociedad((string) ($f['empresa_ruc'] ?? ''), $f['tipo_codigo'] ?? null);
            $aviso     = self::avisoQueToca($dias, $sociedad, $bool($f['avisado_previo'] ?? false), $bool($f['avisado_enlace'] ?? false));
            if ($aviso === null) {
                continue;
            }
            $out['firmas']++;

            $correo = strtolower(trim((string) ($f['correo'] ?? '')));
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                // Sin correo válido en la ficha de la empresa: no hay a quién avisar.
                // No se marca, por si la empresa completa el correo antes de la caducidad.
                $out['sin_correo']++;
                error_log('[FirmaCaducidadAviso] Empresa ' . $idEmpresa . ' sin correo válido; no se envió el aviso de caducidad de firma.');
                continue;
            }

            $data = [
                'empresa_nombre'   => (string) ($f['empresa_nombre'] ?? ''),
                'empresa_ruc'      => (string) ($f['empresa_ruc'] ?? ''),
                'fecha_expiracion' => (string) ($f['fecha_expiracion'] ?? ''),
                'dias'             => $dias,
                'url'              => $this->urlFirma(),
                'url_solicitud'    => $aviso === 'enlace' ? $this->enlaceSolicitud($f, $correo) : '',
            ];

            $ok = false;
            try {
                $ok = enviar_correo_firma_caducidad($correo, $data);
            } catch (\Throwable $e) {
                error_log('[FirmaCaducidadAviso] Empresa ' . $idEmpresa . ': ' . $e->getMessage());
            }

            if (!$ok) {
                $out['fallidos']++;
                error_log('[FirmaCaducidadAviso] Empresa ' . $idEmpresa . ': falló el envío a ' . $correo
                    . ' — ' . (string) ($GLOBALS['LAST_EMAIL_ERROR'] ?? 'sin detalle'));
                continue;
            }

            $out['correos']++;

            // Auditoría + marca de "ya avisado" de esta etapa para esta firma.
            $this->log->registrar(
                0,
                $idEmpresa,
                $aviso === 'enlace' ? FirmaCaducidadRepository::ACCION_AVISO_ENLACE : FirmaCaducidadRepository::ACCION_AVISO,
                FirmaCaducidadRepository::TABLA_AVISO,
                $idFirma,
                null,
                [
                    'correo'           => $correo,
                    'fecha_expiracion' => $data['fecha_expiracion'],
                    'dias'             => $dias,
                    'tipo'             => $sociedad ? 'sociedad' : 'persona_natural',
                    'url_solicitud'    => $data['url_solicitud'] !== '' ? $data['url_solicitud'] : null,
                ]
            );
        }

        return $out;
    }

    /**
     * Enlace de solicitud de firma en la empresa que le vende las firmas a esta empresa:
     * su controladora de suscripciones (por RUC) o, si no tiene, la administradora.
     * '' si no se puede armar (el correo sale igual, sin el botón).
     */
    private function enlaceSolicitud(array $f, string $correo): string
    {
        try {
            $idDirecto  = (int) ($f['id_empresa_suscripciones'] ?? 0);
            $idVendedora = (int) ((new \App\repositories\modulos\EmpresaRepository())->resolverEmpresaControladoraSuscripciones(
                (string) ($f['empresa_ruc'] ?? ''), $idDirecto > 0 ? $idDirecto : null
            ) ?? 0) ?: (int) ((new \App\models\Empresa())->getIdAdministradoraSuscripciones() ?? 0);
            // Desde el cron no hay HTTP_HOST: sin APP_URL el enlace saldría relativo e inservible.
            if ($idVendedora <= 0 || !preg_match('#^https?://#', url_absoluta(''))) {
                return '';
            }
            return (string) \App\Services\modulos\FirmaElectronicaService::instancia()->crearEnlaceAvisoVencimiento(
                $idVendedora, $correo, (string) ($f['empresa_nombre'] ?? ''), (string) ($f['telefono'] ?? ''), (int) $f['id_empresa']
            );
        } catch (\Throwable $e) {
            error_log('[FirmaCaducidadAviso] Enlace de solicitud para la empresa ' . ($f['id_empresa'] ?? '?') . ': ' . $e->getMessage());
            return '';
        }
    }

    /** Enlace a la ficha de la empresa (pestaña Firma) si hay URL pública configurada. */
    private function urlFirma(): string
    {
        $base = (defined('APP_URL') && APP_URL !== '') ? APP_URL : '';
        return $base !== '' ? $base . '/modulos/empresa' : '';
    }
}
