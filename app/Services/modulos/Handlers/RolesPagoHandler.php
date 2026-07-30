<?php

declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\models\CatalogoNovedades;
use App\repositories\modulos\RolPagoRepository;
use App\Rules\modulos\RolPagoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\RolPagoService;
use DateTime;

/**
 * Genera automáticamente corridas de Rol de Pagos (mensual, quincena, semanal).
 *
 * Alcance a propósito acotado: SOLO genera y calcula la corrida (queda en estado
 * 'generado', visible en el listado) para que un humano la revise antes de pagar.
 * Nunca crea egresos ni mueve dinero — eso se sigue haciendo manualmente desde el
 * módulo de Roles de Pago (o desde otra automatización, si en el futuro se decide
 * sumar ese paso por separado).
 *
 * Si la corrida del período calculado ya existe, no la duplica: informa y sigue.
 */
class RolesPagoHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        return match ($this->accion) {
            'generar_mensual'  => $this->generarMensual($idEmpresa, $idUsuario, $parametros),
            'generar_quincena' => $this->generarQuincena($idEmpresa, $idUsuario, $parametros),
            'generar_semanal'  => $this->generarSemanal($idEmpresa, $idUsuario, $parametros),
            default => throw new \RuntimeException("Acción '{$this->accion}' no implementada en RolesPagoHandler."),
        };
    }

    private function servicio(): RolPagoService
    {
        return new RolPagoService(new RolPagoRepository(), new RolPagoRules(), new LogSistemaService());
    }

    // ── MENSUAL ──────────────────────────────────────────────────────────────
    private function generarMensual(int $idEmpresa, int $idUsuario, array $p): array
    {
        $periodo = (string) ($p['periodo'] ?? 'mes_anterior');
        $fecha   = $periodo === 'mes_en_curso'
            ? date('Y-m-01')
            : date('Y-m-01', strtotime('first day of last month'));

        $anio = (int) date('Y', strtotime($fecha));
        $mes  = (int) date('n', strtotime($fecha));

        $fechaDesde = sprintf('%04d-%02d-01', $anio, $mes);
        $fechaHasta = date('Y-m-t', strtotime($fechaDesde));

        return $this->generarCorrida($idEmpresa, $idUsuario, 'MENSUAL', $anio, $mes, 0, $fechaDesde, $fechaHasta);
    }

    // ── QUINCENA (valor fijo, siempre días 1-15) ────────────────────────────
    // Solo existe UNA quincena por mes (la primera mitad, un anticipo de valor
    // fijo menos sus descuentos): la segunda mitad del mes no se paga aparte,
    // se liquida junto con el resto del sueldo en el rol MENSUAL de fin de mes
    // (el "neteo" ya se encarga de restar lo ya adelantado). Por eso no hay
    // parámetro que elegir: siempre son los días 1-15 del mes en que se ejecuta.
    private function generarQuincena(int $idEmpresa, int $idUsuario, array $p): array
    {
        $hoy  = new DateTime();
        $anio = (int) $hoy->format('Y');
        $mes  = (int) $hoy->format('n');

        $fechaDesde = sprintf('%04d-%02d-01', $anio, $mes);
        $fechaHasta = sprintf('%04d-%02d-15', $anio, $mes);

        return $this->generarCorrida($idEmpresa, $idUsuario, 'QUINCENA', $anio, $mes, 1, $fechaDesde, $fechaHasta);
    }

    // ── SEMANAL (semana calendario, lunes a domingo, máximo 3 por mes) ──────
    // Sin parámetro propio: el día en que corre (lunes, viernes, el que sea) lo
    // define la frecuencia de la automatización. Siempre se genera la última
    // semana YA COMPLETA (lunes a domingo anterior a la actual), sin importar
    // qué día de la semana dispare la corrida — así nunca se genera una semana
    // todavía en curso. Solo hay semanal para las primeras 3 semanas del mes:
    // el resto (la 4ª semana en adelante) se liquida junto con el rol MENSUAL
    // de fin de mes (el "neteo" resta lo ya adelantado en esas 3 semanas).
    private function generarSemanal(int $idEmpresa, int $idUsuario, array $p): array
    {
        $lunes   = (new DateTime())->modify('monday this week')->modify('-7 days');
        $domingo = (clone $lunes)->modify('+6 days');

        [$anio, $mes, $numero] = $this->numeroSemanaDelMes($lunes);

        if ($numero > 3) {
            $mesNombre = CatalogoNovedades::MESES[$mes] ?? (string) $mes;
            return ['registros' => 0, 'mensaje' => "La semana del {$lunes->format('d-m-Y')} al {$domingo->format('d-m-Y')} es la {$numero}ª de {$mesNombre}: no se genera aparte, se liquida con el rol mensual de fin de mes."];
        }

        return $this->generarCorrida(
            $idEmpresa, $idUsuario, 'SEMANAL', $anio, $mes, $numero,
            $lunes->format('Y-m-d'), $domingo->format('Y-m-d')
        );
    }

    /**
     * [anio, mes, numero_periodo(1-5)] del mes al que pertenece la semana cuyo
     * lunes es $lunes. La semana se asigna al mes de SU lunes (si la semana cruza
     * fin de mes, queda contada del lado del mes donde empieza). Se numera 1..N
     * contando semanas lunes-domingo completas desde el primer lunes del mes.
     */
    private function numeroSemanaDelMes(DateTime $lunes): array
    {
        $anio = (int) $lunes->format('Y');
        $mes  = (int) $lunes->format('n');

        $primerDia          = new DateTime(sprintf('%04d-%02d-01', $anio, $mes));
        $diaSemanaPrimerDia = (int) $primerDia->format('N'); // 1=lunes .. 7=domingo
        $diaPrimerLunes     = $diaSemanaPrimerDia === 1 ? 1 : (1 + (8 - $diaSemanaPrimerDia));

        $diaLunesObjetivo = (int) $lunes->format('j');
        $numero = intdiv($diaLunesObjetivo - $diaPrimerLunes, 7) + 1;

        return [$anio, $mes, max(1, min(5, $numero))];
    }

    // ── Común ────────────────────────────────────────────────────────────────
    private function generarCorrida(int $idEmpresa, int $idUsuario, string $tipo, int $anio, int $mes, int $numeroPeriodo, string $fechaDesde, string $fechaHasta): array
    {
        $rolSvc    = $this->servicio();
        $mesNombre = CatalogoNovedades::MESES[$mes] ?? (string) $mes;
        $etiqueta  = match ($tipo) {
            'QUINCENA' => "quincena {$numeroPeriodo} de {$mesNombre} {$anio}",
            'SEMANAL'  => "semana {$numeroPeriodo} de {$mesNombre} {$anio}",
            default    => "mensual de {$mesNombre} {$anio}",
        };

        try {
            $id = $rolSvc->crear([
                'id_empresa'     => $idEmpresa,
                'id_usuario'     => $idUsuario,
                'tipo_rol'       => $tipo,
                'periodo_anio'   => $anio,
                'periodo_mes'    => $mes,
                'numero_periodo' => $numeroPeriodo,
                'fecha_desde'    => $fechaDesde,
                'fecha_hasta'    => $fechaHasta,
                'fecha_pago'     => date('Y-m-d'),
                'descripcion'    => \App\models\CatalogoRol::nombreTipo($tipo) . " - {$mesNombre} {$anio}"
                    . ($numeroPeriodo > 0 ? " #{$numeroPeriodo}" : ''),
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Ya existe')) {
                return ['registros' => 0, 'mensaje' => "Ya existe la corrida {$etiqueta}; no se duplicó."];
            }
            throw $e;
        }

        $rolSvc->generar($id, $idEmpresa, $idUsuario);

        return ['registros' => 1, 'mensaje' => "Corrida {$etiqueta} generada. Revísala en Roles de Pago antes de pagar."];
    }
}
