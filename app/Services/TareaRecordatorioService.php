<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\TareaRepository;

/**
 * Recordatorio DIARIO por correo a los responsables de tareas vencidas o por vencer.
 *
 * Es un servicio FIJO (no configurable por el usuario): se ejecuta montado sobre el
 * `cron_runner.php` que ya corre cada minuto, y se autolimita a UN envío por día a
 * partir de una hora fija (marca de fecha en archivo). Así no hay que programar ni
 * configurar ningún cron adicional.
 */
class TareaRecordatorioService
{
    /** Hora del día (0-23, zona del servidor = America/Guayaquil) a partir de la cual se envía. */
    private const HORA_ENVIO = 6;

    /** Ventana "por vencer": tareas por_realizar cuya fecha cae dentro de estos días. */
    private const DIAS_POR_VENCER = 2;

    private TareaRepository $repo;

    public function __construct()
    {
        $this->repo = new TareaRepository();
    }

    /**
     * Marca como 'vencida' toda tarea 'por_realizar' cuya fecha ya pasó.
     * Barato e idempotente → pensado para correr en CADA tick del cron.
     * @return int Número de tareas actualizadas.
     */
    public function marcarVencidas(): int
    {
        return $this->repo->marcarVencidasPorFecha();
    }

    /** Archivo-marca con la última fecha (YYYY-MM-DD) en que se envió el recordatorio. */
    private function archivoMarca(): string
    {
        return MVC_ROOT . '/storage/cron/tareas_recordatorio.last';
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
     * Llamado por el cron cada minuto. Envía el recordatorio UNA vez al día, a partir de
     * HORA_ENVIO. Idempotente por la marca de fecha (no reintenta el resto del día).
     *
     * @return array{ejecutado:bool,correos:int,tareas:int}
     */
    public function ejecutarSiCorresponde(): array
    {
        $out = ['ejecutado' => false, 'correos' => 0, 'tareas' => 0, 'vencidas' => 0];

        // Solo a partir de la hora fijada.
        if ((int) date('G') < self::HORA_ENVIO) {
            return $out;
        }
        // Ya se envió hoy.
        $hoy = date('Y-m-d');
        if ($this->getUltimaFecha() === $hoy) {
            return $out;
        }

        $res = $this->enviarRecordatorios();

        // Marcar el día como procesado aunque haya 0 correos (evita reintentos todo el día).
        $this->setUltimaFecha($hoy);

        $out['ejecutado'] = true;
        $out['correos']   = $res['correos'];
        $out['tareas']    = $res['tareas'];
        $out['vencidas']  = $res['vencidas'];
        return $out;
    }

    /**
     * Agrupa las tareas vencidas/por vencer por responsable y envía un correo a cada uno.
     * Puede invocarse manualmente (p. ej. para pruebas) sin pasar por el guard de fecha.
     *
     * @return array{correos:int,tareas:int}
     */
    public function enviarRecordatorios(): array
    {
        // Antes de enviar: dejar la columna estado consistente con la fecha
        // (por_realizar cuya fecha ya pasó → vencida).
        $vencidas = $this->repo->marcarVencidasPorFecha();

        $filas = $this->repo->getTareasVencidasYPorVencer(self::DIAS_POR_VENCER);

        // Agrupar por correo de responsable (solo correos válidos).
        $porResp = [];
        foreach ($filas as $f) {
            $correo = strtolower(trim((string) ($f['resp_correo'] ?? '')));
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (!isset($porResp[$correo])) {
                $porResp[$correo] = [
                    'nombre' => trim((string) ($f['resp_nombre'] ?? '')),
                    'tareas' => [],
                ];
            }
            $porResp[$correo]['tareas'][] = [
                'obligacion'     => (string) ($f['obligacion'] ?? 'Tarea'),
                'cliente_nombre' => (string) ($f['cliente_nombre'] ?? ''),
                'fecha_tarea'    => (string) ($f['fecha_tarea'] ?? ''),
                'estado'         => (string) ($f['estado'] ?? ''),
            ];
        }

        require_once MVC_APP . '/helpers/mail.php';

        $correosEnviados = 0;
        $tareasTotal     = 0;
        foreach ($porResp as $correo => $info) {
            $ok = enviar_correo_recordatorio_tareas($correo, $info['nombre'], $info['tareas']);
            if ($ok) {
                $correosEnviados++;
                $tareasTotal += count($info['tareas']);
            }
        }

        return ['correos' => $correosEnviados, 'tareas' => $tareasTotal, 'vencidas' => $vencidas];
    }
}
