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
 * Es un servicio FIJO (no configurable): corre montado sobre `cron_runner.php`
 * (cada minuto) y se autolimita a UNA revisión por día a partir de HORA_ENVIO,
 * igual que TareaRecordatorioService. Cada firma recibe UN solo correo: el
 * envío queda registrado en log_sistema (tabla empresa_firma, acción
 * aviso_caducidad_correo) y esa fila es la marca que evita repetirlo.
 */
class FirmaCaducidadAvisoService
{
    /** Hora del día (0-23, zona del servidor = America/Guayaquil) a partir de la cual se revisa. */
    private const HORA_ENVIO = 6;

    /** Avisar cuando falten estos días o menos para la caducidad (1 = "caduca mañana"). */
    public const DIAS_AVISO = 1;

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
     * vuelve a intentar en la revisión del día siguiente (sigue dentro de la ventana
     * DIAS_CADUCADA_MAX), sin martillar el SMTP cada minuto.
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
     * Envía el aviso a cada empresa con firma por caducar (o recién caducada) que
     * aún no lo recibió. Puede invocarse manualmente (pruebas) sin el guard diario.
     *
     * @return array{firmas:int,correos:int,sin_correo:int,fallidos:int}
     */
    public function enviarAvisos(): array
    {
        $out = ['firmas' => 0, 'correos' => 0, 'sin_correo' => 0, 'fallidos' => 0];

        $firmas = $this->repo->getFirmasPorAvisar(self::DIAS_AVISO, self::DIAS_CADUCADA_MAX);
        $out['firmas'] = count($firmas);
        if ($firmas === []) {
            return $out;
        }

        require_once MVC_APP . '/helpers/mail.php';

        foreach ($firmas as $f) {
            $idEmpresa = (int) ($f['id_empresa'] ?? 0);
            $idFirma   = (int) ($f['id_firma'] ?? 0);
            $correo    = strtolower(trim((string) ($f['correo'] ?? '')));

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
                'dias'             => (int) ($f['dias'] ?? 0),
                'url'              => $this->urlFirma(),
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

            // Auditoría + marca de "ya avisado" para esta firma (una sola vez por firma).
            $this->log->registrar(
                0,
                $idEmpresa,
                FirmaCaducidadRepository::ACCION_AVISO,
                FirmaCaducidadRepository::TABLA_AVISO,
                $idFirma,
                null,
                [
                    'correo'           => $correo,
                    'fecha_expiracion' => $data['fecha_expiracion'],
                    'dias'             => $data['dias'],
                ]
            );
        }

        return $out;
    }

    /** Enlace a la ficha de la empresa (pestaña Firma) si hay URL pública configurada. */
    private function urlFirma(): string
    {
        $base = (defined('APP_URL') && APP_URL !== '') ? APP_URL : '';
        return $base !== '' ? $base . '/modulos/empresa' : '';
    }
}
