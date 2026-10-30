<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\Services\modulos\MantenimientoAvisoService;

/**
 * Aviso automático del PRÓXIMO MANTENIMIENTO por kilometraje (Automatizaciones →
 * Car-Wash): cambio de aceite de motor, de caja y demás servicios del catálogo.
 *
 * En cada ejecución estima el km de hoy de cada vehículo y avisa a los clientes cuyos
 * servicios están próximos (faltan los km de aviso o menos) o vencidos:
 *  - un primer aviso por cambio y canal;
 *  - si sigue vencido, un recordatorio cada "reavisar_cada_dias" días, hasta "max_reavisos".
 * Un solo mensaje por vehículo con todos sus servicios por avisar. Se puede programar a
 * diario: lo ya avisado no se repite (carwash_recordatorios + índice único).
 * Si había avisos y NINGUNO salió (todos con error), se lanza una excepción para que el log
 * de la automatización quede en "error" con el motivo.
 */
class MantenimientoAvisoHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        $svc = new MantenimientoAvisoService();
        if (!$svc->disponible()) {
            return ['registros' => 0, 'mensaje' => 'Falta aplicar el SQL de avisos de mantenimiento (20261031_mantenimientos_avisos.sql).'];
        }
        $res = match ($this->accion) {
            'aviso_mantenimiento_correo'          => $this->porCorreo($svc, $idEmpresa, $idUsuario, $parametros),
            'aviso_mantenimiento_whatsapp'        => $this->porWhatsapp($svc, $idEmpresa, $idUsuario, $parametros),
            'aviso_mantenimiento_correo_whatsapp' => $this->porAmbos($svc, $idEmpresa, $idUsuario, $parametros),
            default => throw new \RuntimeException("Acción '{$this->accion}' no implementada en MantenimientoAvisoHandler."),
        };
        if (($res['registros'] ?? 0) === 0 && !empty($res['errores'])) {
            throw new \RuntimeException($res['mensaje'] . ($svc->ultimoError ? ' Motivo: ' . $svc->ultimoError : ''));
        }
        unset($res['errores']);
        return $res;
    }

    private function porCorreo(MantenimientoAvisoService $svc, int $idEmpresa, int $idUsuario, array $p): array
    {
        $asunto = trim((string) ($p['asunto'] ?? '')) ?: MantenimientoAvisoService::ASUNTO_DEFECTO;
        $cuerpo = trim((string) ($p['cuerpo'] ?? '')) ?: MantenimientoAvisoService::MENSAJE_DEFECTO;
        $grupos = $svc->pendientes($idEmpresa, 'correo', $p);
        if (!$grupos) return ['registros' => 0, 'mensaje' => 'No hay mantenimientos pendientes de aviso por correo.'];

        $empresa = (new \App\Services\modulos\CarWashRecordatorioService())->empresaNombre($idEmpresa);
        $enviados = 0; $sinCorreo = 0; $errores = 0;
        foreach ($grupos as $g) {
            try {
                $r = $svc->enviarCorreo($g, $idEmpresa, $idUsuario, '',
                    $svc->aplicarEtiquetas($asunto, $g, $empresa), $svc->aplicarEtiquetas($cuerpo, $g, $empresa), 'automatico');
                $r['ok'] ? $enviados++ : $errores++;
            } catch (\Throwable $e) {
                // Sin correo en el vehículo ni en el cliente: no es un error del envío.
                str_contains($e->getMessage(), 'correo registrado') ? $sinCorreo++ : $errores++;
                if (!str_contains($e->getMessage(), 'correo registrado')) $svc->ultimoError = $e->getMessage();
            }
        }
        return ['registros' => $enviados, 'errores' => $errores, 'mensaje' => $this->resumen($enviados, count($grupos), $sinCorreo, $errores, 'sin correo')];
    }

    private function porWhatsapp(MantenimientoAvisoService $svc, int $idEmpresa, int $idUsuario, array $p): array
    {
        $plantilla = trim((string) ($p['plantilla_whatsapp'] ?? ''));
        if ($plantilla === '') return ['registros' => 0, 'mensaje' => 'Debe seleccionar la plantilla de WhatsApp en la automatización.'];
        $tpl = null;
        foreach ((new \App\models\WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa) as $pl) {
            if ((string) $pl['nombre'] === $plantilla) { $tpl = $pl; break; }
        }
        if ($tpl === null) return ['registros' => 0, 'mensaje' => "La plantilla '{$plantilla}' no existe o no está aprobada."];
        $numVars = 0;
        foreach (json_decode($tpl['componentes'] ?? '[]', true) ?: [] as $comp) {
            if (strtoupper($comp['type'] ?? '') === 'BODY' && preg_match_all('/{{(\d+)}}/', $comp['text'] ?? '', $m)) {
                $numVars = max($numVars, (int) max($m[1]));
            }
        }

        $grupos = $svc->pendientes($idEmpresa, 'whatsapp', $p);
        if (!$grupos) return ['registros' => 0, 'mensaje' => 'No hay mantenimientos pendientes de aviso por WhatsApp.'];
        $rec = new \App\Services\modulos\CarWashRecordatorioService();
        $enviados = 0; $sinTel = 0; $errores = 0;
        foreach ($grupos as $g) {
            if ($rec->destinatarios($g)['telefono'] === '') { $sinTel++; continue; }
            try {
                $svc->enviarWhatsappPlantilla($g, $idEmpresa, $idUsuario, $plantilla, (string) ($tpl['idioma'] ?? 'es'), $numVars)
                    ? $enviados++ : $errores++;
            } catch (\Throwable $e) {
                $errores++;
                $svc->ultimoError = $e->getMessage();
            }
        }
        return ['registros' => $enviados, 'errores' => $errores, 'mensaje' => $this->resumen($enviados, count($grupos), $sinTel, $errores, 'sin teléfono')];
    }

    private function porAmbos(MantenimientoAvisoService $svc, int $idEmpresa, int $idUsuario, array $p): array
    {
        $c = $this->porCorreo($svc, $idEmpresa, $idUsuario, $p);
        $w = $this->porWhatsapp($svc, $idEmpresa, $idUsuario, $p);
        return [
            'registros' => (int) $c['registros'] + (int) $w['registros'],
            'errores'   => (int) ($c['errores'] ?? 0) + (int) ($w['errores'] ?? 0),
            'mensaje'   => 'Correo: ' . $c['mensaje'] . ' | WhatsApp: ' . $w['mensaje'],
        ];
    }

    private function resumen(int $enviados, int $total, int $sinDestino, int $errores, string $etqSin): string
    {
        $txt = "Avisos de mantenimiento enviados: {$enviados} de {$total} vehículo(s).";
        if ($sinDestino) $txt .= " {$sinDestino} {$etqSin} (ni en el vehículo ni en el cliente).";
        if ($errores)    $txt .= " {$errores} con error (ver la pestaña Mantenimiento de la ficha del vehículo).";
        return $txt;
    }
}
