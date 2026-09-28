<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\Services\modulos\CarWashRecordatorioService;

/**
 * Recordatorio automático de la PRÓXIMA CITA de Car-Wash (Automatizaciones).
 *
 * En cada ejecución busca las órdenes cuya próxima cita cae entre hoy y hoy + N días y
 * que aún no tienen recordatorio automático enviado por ese canal, y avisa al cliente:
 *  - enviar_correo:   correo con asunto y mensaje configurables (etiquetas {cliente}…);
 *  - enviar_whatsapp: plantilla de WhatsApp aprobada por Meta.
 * Cada cita se avisa UNA sola vez por canal (carwash_recordatorios + índice único), así
 * que la automatización puede correr a diario sin repetir mensajes. El "cuándo" lo decide
 * la automatización; este handler solo busca y envía.
 */
class CarWashRecordatorioHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        $svc = new CarWashRecordatorioService();
        if (!$svc->tablaDisponible()) {
            return ['registros' => 0, 'mensaje' => 'Falta aplicar el SQL de recordatorios de Car-Wash (20260929_carwash_recordatorios.sql).'];
        }
        return match ($this->accion) {
            'recordatorio_cita_correo'   => $this->porCorreo($svc, $idEmpresa, $idEstablecimiento, $idUsuario, $parametros),
            'recordatorio_cita_whatsapp' => $this->porWhatsapp($svc, $idEmpresa, $idEstablecimiento, $idUsuario, $parametros),
            default => throw new \RuntimeException("Acción '{$this->accion}' no implementada en CarWashRecordatorioHandler."),
        };
    }

    private function porCorreo(CarWashRecordatorioService $svc, int $idEmpresa, ?int $idEst, int $idUsuario, array $p): array
    {
        $dias   = max(0, (int) ($p['dias_anticipacion'] ?? 1));
        $asunto = trim((string) ($p['asunto'] ?? '')) ?: CarWashRecordatorioService::ASUNTO_DEFECTO;
        $cuerpo = trim((string) ($p['cuerpo'] ?? '')) ?: CarWashRecordatorioService::MENSAJE_DEFECTO;

        $citas = $svc->citasPendientes($idEmpresa, $dias, 'correo', $idEst ?: null);
        if (!$citas) {
            return ['registros' => 0, 'mensaje' => "No hay citas en los próximos {$dias} día(s) pendientes de recordatorio por correo."];
        }
        $empresa = $svc->empresaNombre($idEmpresa);
        $enviados = 0; $sinCorreo = 0; $errores = 0;
        foreach ($citas as $c) {
            if ($svc->destinatarios($c)['correo'] === '') { $sinCorreo++; continue; }
            try {
                $r = $svc->enviarCorreo((int) $c['id_orden'], $idEmpresa, $idUsuario, '',
                    $svc->aplicarEtiquetas($asunto, $c, $empresa), $svc->aplicarEtiquetas($cuerpo, $c, $empresa), 'automatico');
                $r['ok'] ? $enviados++ : $errores++;
            } catch (\Throwable $e) {
                $errores++;
            }
        }
        return ['registros' => $enviados, 'mensaje' => $this->resumen($enviados, count($citas), $sinCorreo, $errores, 'sin correo')];
    }

    private function porWhatsapp(CarWashRecordatorioService $svc, int $idEmpresa, ?int $idEst, int $idUsuario, array $p): array
    {
        $dias      = max(0, (int) ($p['dias_anticipacion'] ?? 1));
        $plantilla = trim((string) ($p['plantilla_whatsapp'] ?? ''));
        if ($plantilla === '') {
            return ['registros' => 0, 'mensaje' => 'Debe seleccionar la plantilla de WhatsApp en la automatización.'];
        }
        $tpl = null;
        foreach ((new \App\models\WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa) as $pl) {
            if ((string) $pl['nombre'] === $plantilla) { $tpl = $pl; break; }
        }
        if ($tpl === null) {
            return ['registros' => 0, 'mensaje' => "La plantilla '{$plantilla}' no existe o no está aprobada."];
        }
        $numVars = 0;
        foreach (json_decode($tpl['componentes'] ?? '[]', true) ?: [] as $comp) {
            if (strtoupper($comp['type'] ?? '') === 'BODY' && preg_match_all('/{{(\d+)}}/', $comp['text'] ?? '', $m)) {
                $numVars = max($numVars, (int) max($m[1]));
            }
        }

        $citas = $svc->citasPendientes($idEmpresa, $dias, 'whatsapp', $idEst ?: null);
        if (!$citas) {
            return ['registros' => 0, 'mensaje' => "No hay citas en los próximos {$dias} día(s) pendientes de recordatorio por WhatsApp."];
        }
        $enviados = 0; $sinTel = 0; $errores = 0;
        foreach ($citas as $c) {
            if ($svc->destinatarios($c)['telefono'] === '') { $sinTel++; continue; }
            try {
                $svc->enviarWhatsappPlantilla($c, $idEmpresa, $idUsuario, $plantilla, (string) ($tpl['idioma'] ?? 'es'), $numVars)
                    ? $enviados++ : $errores++;
            } catch (\Throwable $e) {
                $errores++;
            }
        }
        return ['registros' => $enviados, 'mensaje' => $this->resumen($enviados, count($citas), $sinTel, $errores, 'sin teléfono')];
    }

    private function resumen(int $enviados, int $total, int $sinDestino, int $errores, string $etqSin): string
    {
        $txt = "Recordatorios enviados: {$enviados} de {$total} cita(s).";
        if ($sinDestino) $txt .= " {$sinDestino} {$etqSin} (ni en el vehículo ni en el cliente).";
        if ($errores)    $txt .= " {$errores} con error (ver historial en la ficha del vehículo).";
        return $txt;
    }
}
