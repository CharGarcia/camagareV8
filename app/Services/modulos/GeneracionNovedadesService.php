<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsistenciaJornadaRepository;
use App\repositories\modulos\AsistenciaConfigRepository;
use App\repositories\modulos\NovedadRepository;
use App\models\CatalogoNovedades;
use Exception;

/**
 * Paso 4: traduce las jornadas calculadas (faltas, atrasos, horas extra) en
 * Novedades del catálogo estándar, para que el rol de pagos las consuma sin
 * cambios. Reusa NovedadService (candado de rol pagado, auditoría, transacciones
 * y regeneración automática del rol) — este servicio solo agrega y decide qué
 * novedad crear/actualizar/eliminar.
 *
 * Idempotente: cada novedad generada lleva un marcador en la observación
 * (p. ej. "[ASISTENCIA-FALTA 2026-07]"). Volver a generar el mismo período
 * actualiza esas novedades sin duplicar y sin tocar las creadas a mano.
 */
class GeneracionNovedadesService
{
    private const TIPO_FALTA  = '10'; // Días no laborados
    private const TIPO_EXTRA  = '5';  // Horas Suplementarias
    private const TIPO_DESCUENTO = '2'; // Descuento

    private const MARCADOR_FALTA        = 'ASISTENCIA-FALTA';
    private const MARCADOR_EXTRA        = 'ASISTENCIA-EXTRA';
    private const MARCADOR_ATRASO_DESC  = 'ASISTENCIA-ATRASO';
    private const MARCADOR_ATRASO_DIAS  = 'ASISTENCIA-ATRASO-DIAS';
    private const MARCADOR_ATRASO_INFO  = 'ASISTENCIA-ATRASO-INFO'; // 'Solo informativo': novedad de registro con valor 0

    private AsistenciaJornadaRepository $jornadaRepo;
    private AsistenciaConfigRepository $configRepo;
    private NovedadRepository $novedadRepo;
    private NovedadService $novedadService;

    public function __construct(
        AsistenciaJornadaRepository $jornadaRepo,
        AsistenciaConfigRepository $configRepo,
        NovedadRepository $novedadRepo,
        NovedadService $novedadService
    ) {
        $this->jornadaRepo = $jornadaRepo;
        $this->configRepo = $configRepo;
        $this->novedadRepo = $novedadRepo;
        $this->novedadService = $novedadService;
    }

    /**
     * Genera/actualiza/limpia las novedades del período para todos los empleados
     * con datos de asistencia (o uno solo, si se indica).
     *
     * @return array{creadas:int,actualizadas:int,eliminadas:int,omitidas:int,empleados:int,detalle:array}
     */
    public function generar(int $idEmpresa, int $mes, int $anio, string $aplicaEn, int $idUsuario, ?int $idEmpleado = null): array
    {
        if ($mes < 1 || $mes > 12) throw new Exception('El mes del período no es válido.');
        if ($anio < 2000 || $anio > 2100) throw new Exception('El año del período no es válido.');
        if (!CatalogoNovedades::esAplicaEnValido($aplicaEn)) throw new Exception('La opción "Afecta a" no es válida.');

        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $resumen = $this->jornadaRepo->getResumenPeriodo($idEmpresa, $desde, $hasta, $idEmpleado);
        $atrasoModo = $this->configRepo->getByEmpresa($idEmpresa)['atraso_modo'] ?? 'informativo';

        $creadas = 0; $actualizadas = 0; $eliminadas = 0; $omitidas = 0;
        $detalle = [];

        foreach ($resumen as $r) {
            $idEmp = (int) $r['id_empleado'];
            $diasFalta = (int) $r['dias_falta'];
            $horasExtra = round(((int) $r['extra_min']) / 60, 2);
            $horasAtraso = round(((int) $r['atraso_min']) / 60, 2);
            $sueldoBase = (float) ($r['sueldo_base'] ?? 0);

            $movs = [];

            // Faltas → Días no laborados.
            $movs[] = $this->upsertTipo(
                $idEmpresa, $idEmp, self::TIPO_FALTA, self::MARCADOR_FALTA, $mes, $anio, $aplicaEn, $idUsuario,
                $diasFalta > 0 ? (float) $diasFalta : 0,
                $diasFalta > 0 ? "Faltas detectadas por Control de Asistencia ({$diasFalta} día(s))." : null
            );

            // Horas extra → Horas Suplementarias.
            $movs[] = $this->upsertTipo(
                $idEmpresa, $idEmp, self::TIPO_EXTRA, self::MARCADOR_EXTRA, $mes, $anio, $aplicaEn, $idUsuario,
                $horasExtra > 0 ? $horasExtra : 0,
                $horasExtra > 0 ? "Horas extra detectadas por Control de Asistencia ({$horasExtra} h)." : null
            );

            // Atrasos → según el modo EFECTIVO del empleado: su override si lo tiene,
            // si no el default de la empresa. Se llama a los TRES marcadores (los
            // inactivos con valor 0 → se eliminan), así al cambiar de modo no quedan huérfanos.
            //   'descuento'       → Descuento = horas × sueldo/240
            //   'dias'            → Días no laborados = horas/8 (heredable del default de empresa)
            //   'informativo_reg' → 'Solo informativo': novedad de registro con valor 0
            //   'no_descuenta' / 'informativo' / vacío → no genera nada
            $modoEmp = !empty($r['atraso_modo']) ? (string) $r['atraso_modo'] : $atrasoModo;
            $valorHora = $sueldoBase > 0 ? $sueldoBase / 240 : 0;
            $montoDesc = $modoEmp === 'descuento' ? round($horasAtraso * $valorHora, 2) : 0;
            $fraccion  = $modoEmp === 'dias' ? round($horasAtraso / 8, 2) : 0;
            $infoGate  = $modoEmp === 'informativo_reg' ? $horasAtraso : 0; // >0 si hay atraso que registrar

            $movs[] = $this->upsertTipo(
                $idEmpresa, $idEmp, self::TIPO_DESCUENTO, self::MARCADOR_ATRASO_DESC, $mes, $anio, $aplicaEn, $idUsuario,
                $montoDesc,
                $montoDesc > 0 ? "Descuento por atrasos, Control de Asistencia ({$horasAtraso} h)." : null
            );
            $movs[] = $this->upsertTipo(
                $idEmpresa, $idEmp, self::TIPO_FALTA, self::MARCADOR_ATRASO_DIAS, $mes, $anio, $aplicaEn, $idUsuario,
                $fraccion,
                $fraccion > 0 ? "Fracción de día por atrasos, Control de Asistencia ({$horasAtraso} h)." : null
            );
            // Registro informativo: se persiste con valor 0 (no descuenta), solo deja constancia.
            $movs[] = $this->upsertTipo(
                $idEmpresa, $idEmp, self::TIPO_DESCUENTO, self::MARCADOR_ATRASO_INFO, $mes, $anio, $aplicaEn, $idUsuario,
                $infoGate,
                $infoGate > 0 ? "Atraso informativo (no descuenta), Control de Asistencia ({$horasAtraso} h)." : null,
                0.0
            );

            foreach ($movs as $m) {
                if ($m === null) continue;
                if ($m['accion'] === 'omitida') { $omitidas++; continue; }
                if ($m['accion'] === 'creada') $creadas++;
                if ($m['accion'] === 'actualizada') $actualizadas++;
                if ($m['accion'] === 'eliminada') $eliminadas++;
            }

            $detalle[] = [
                'id_empleado' => $idEmp,
                'nombre'      => $r['empleado_nombre'],
                'dias_falta'  => $diasFalta,
                'horas_extra' => $horasExtra,
                'horas_atraso' => $horasAtraso,
            ];
        }

        return [
            'creadas'      => $creadas,
            'actualizadas' => $actualizadas,
            'eliminadas'   => $eliminadas,
            'omitidas'     => $omitidas,
            'empleados'    => count($resumen),
            'detalle'      => $detalle,
        ];
    }

    /**
     * Crea, actualiza o elimina la novedad marcada de un tipo para un empleado/período,
     * según el valor calculado. Omite (no lanza) si el rol de ese período ya está pagado.
     */
    private function upsertTipo(
        int $idEmpresa, int $idEmpleado, string $tipoCodigo, string $marcador,
        int $mes, int $anio, string $aplicaEn, int $idUsuario, float $valor, ?string $observacionBase,
        ?float $valorPersistir = null
    ): ?array {
        // $valor decide crear/eliminar (>0 crea, ≤0 elimina). Si $valorPersistir viene,
        // ese es el valor que se guarda (p. ej. 0 para el registro "Solo informativo").
        $marcadorTag = "[{$marcador} {$anio}-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . ']';
        $existente = $this->novedadRepo->getByMarcador($idEmpresa, $idEmpleado, $tipoCodigo, $aplicaEn, $mes, $anio, $marcadorTag);

        try {
            if ($valor <= 0) {
                if ($existente) {
                    $this->novedadService->eliminar((int) $existente['id'], $idEmpresa, $idUsuario);
                    return ['accion' => 'eliminada'];
                }
                return null; // nada que hacer
            }

            $fecha = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mes)));
            $data = [
                'id_empresa'   => $idEmpresa,
                'id_empleado'  => $idEmpleado,
                'tipo_codigo'  => $tipoCodigo,
                'fecha'        => $fecha,
                'periodo_mes'  => $mes,
                'periodo_anio' => $anio,
                'valor'        => $valorPersistir ?? $valor,
                'aplica_en'    => $aplicaEn,
                'observacion'  => trim($marcadorTag . ' ' . $observacionBase),
                'estado'       => 'activo',
                'id_usuario'   => $idUsuario,
            ];

            if ($existente) {
                $this->novedadService->actualizar((int) $existente['id'], $idEmpresa, $data);
                return ['accion' => 'actualizada'];
            }
            $this->novedadService->crear($data);
            return ['accion' => 'creada'];
        } catch (Exception $e) {
            // Rol ya pagado u otra regla de negocio: se omite este movimiento sin frenar el resto.
            return ['accion' => 'omitida'];
        }
    }
}
