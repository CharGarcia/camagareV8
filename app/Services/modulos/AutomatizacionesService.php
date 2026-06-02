<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AutomatizacionesRepository;
use App\Rules\modulos\AutomatizacionesRules;
use App\Services\LogSistemaService;

class AutomatizacionesService
{
    public function __construct(
        private AutomatizacionesRepository $repository,
        private AutomatizacionesRules      $rules,
        private LogSistemaService          $logService
    ) {}

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        return $this->repository->findById($id, $idEmpresa);
    }

    public function crear(array $data, int $idEmpresa, int $idUsuario): int
    {
        $this->rules->validar($data);

        $params = $this->prepararParams($data, $idEmpresa, $idUsuario, false);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->crear($params);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'automatizaciones', $id, null, $params);
            $this->repository->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data, int $idEmpresa, int $idUsuario): void
    {
        $this->rules->validar($data);

        $anterior = $this->repository->findById($id, $idEmpresa);
        if ($anterior === null) {
            throw new \RuntimeException('Automatización no encontrada.');
        }

        $params = $this->prepararParams($data, $idEmpresa, $idUsuario, true);

        $this->repository->beginTransaction();
        try {
            $this->repository->actualizar($id, $idEmpresa, $params);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'automatizaciones', $id, $anterior, $params);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repository->findById($id, $idEmpresa);
        if ($registro === null) {
            throw new \RuntimeException('Automatización no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'automatizaciones', $id, $registro, null);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getLog(int $idAutomatizacion, int $idEmpresa, int $page = 1, int $perPage = 30): array
    {
        $automatizacion = $this->repository->findById($idAutomatizacion, $idEmpresa);
        if ($automatizacion === null) {
            throw new \RuntimeException('Automatización no encontrada.');
        }
        return $this->repository->getLog($idAutomatizacion, $page, $perPage);
    }

    /** Ejecuta manualmente una automatización (desde el frontend) */
    public function ejecutarManual(int $id, int $idEmpresa, int $idUsuario): array
    {
        $automatizacion = $this->repository->findById($id, $idEmpresa);
        if ($automatizacion === null) {
            throw new \RuntimeException('Automatización no encontrada.');
        }
        if ($automatizacion['estado'] === 'en_proceso') {
            throw new \RuntimeException('La tarea ya está en ejecución.');
        }

        return $this->ejecutarTarea($automatizacion, 'manual');
    }

    /** Calcula la próxima ejecución según la configuración */
    public static function calcularProximaEjecucion(string $frecuenciaTipo, string $frecuenciaValor, ?string $cronExpression = null): string
    {
        $ahora = new \DateTime('now', new \DateTimeZone('America/Guayaquil'));

        switch ($frecuenciaTipo) {
            case 'minutos':
                $minutos = max(1, (int) $frecuenciaValor);
                $ahora->modify("+{$minutos} minutes");
                break;

            case 'horas':
                $horas = max(1, (int) $frecuenciaValor);
                $ahora->modify("+{$horas} hours");
                break;

            case 'diario':
                // frecuenciaValor = "HH:MM"
                [$hora, $min] = explode(':', $frecuenciaValor . ':00');
                $proxima = clone $ahora;
                $proxima->setTime((int)$hora, (int)$min, 0);
                if ($proxima <= $ahora) {
                    $proxima->modify('+1 day');
                }
                return $proxima->format('Y-m-d H:i:s');

            case 'semanal':
                // frecuenciaValor = "DIA_SEMANA HH:MM" ej: "1 08:00" (1=lunes)
                [$diaSemana, $horaMin] = explode(' ', $frecuenciaValor . ' 00:00');
                [$hora, $min] = explode(':', $horaMin . ':00');
                $diasSemana = ['domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3,
                               'jueves' => 4, 'viernes' => 5, 'sabado' => 6];
                $diaNum = is_numeric($diaSemana) ? (int)$diaSemana : ($diasSemana[$diaSemana] ?? 1);
                $proxima = clone $ahora;
                $diasDiff = ($diaNum - (int)$ahora->format('w') + 7) % 7;
                if ($diasDiff === 0) $diasDiff = 7;
                $proxima->modify("+{$diasDiff} days");
                $proxima->setTime((int)$hora, (int)$min, 0);
                return $proxima->format('Y-m-d H:i:s');

            case 'mensual':
                // frecuenciaValor = "DIA HH:MM" ej: "1 08:00" o "15 20:00"
                [$dia, $horaMin] = explode(' ', $frecuenciaValor . ' 00:00');
                [$hora, $min] = explode(':', $horaMin . ':00');
                $proxima = clone $ahora;
                $proxima->setDate((int)$ahora->format('Y'), (int)$ahora->format('m'), (int)$dia);
                $proxima->setTime((int)$hora, (int)$min, 0);
                if ($proxima <= $ahora) {
                    $proxima->modify('+1 month');
                }
                return $proxima->format('Y-m-d H:i:s');

            case 'cron_personalizado':
                // Para cron personalizado, calcular basado en la expresión
                return self::calcularProximaEjecucionCron($cronExpression ?? '0 0 * * *');
        }

        return $ahora->format('Y-m-d H:i:s');
    }

    private static function calcularProximaEjecucionCron(string $expr): string
    {
        // Implementación simple: avanzar de minuto en minuto hasta encontrar coincidencia
        // Para producción usar una librería como dragonmantank/cron-expression
        $parts = preg_split('/\s+/', trim($expr));
        if (count($parts) !== 5) {
            return (new \DateTime('+1 hour', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d H:i:s');
        }

        $now  = new \DateTime('now', new \DateTimeZone('America/Guayaquil'));
        $now->modify('+1 minute');
        $now->setTime((int)$now->format('H'), (int)$now->format('i'), 0);

        // Búsqueda máxima de 7 días
        $limite = (clone $now)->modify('+7 days');
        while ($now <= $limite) {
            if (self::matchCronField($parts[0], (int)$now->format('i'))
                && self::matchCronField($parts[1], (int)$now->format('H'))
                && self::matchCronField($parts[2], (int)$now->format('d'))
                && self::matchCronField($parts[3], (int)$now->format('n'))
                && self::matchCronField($parts[4], (int)$now->format('w'))) {
                return $now->format('Y-m-d H:i:s');
            }
            $now->modify('+1 minute');
        }

        return (new \DateTime('+1 hour', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d H:i:s');
    }

    private static function matchCronField(string $field, int $value): bool
    {
        if ($field === '*') return true;

        foreach (explode(',', $field) as $part) {
            if (str_contains($part, '/')) {
                [$range, $step] = explode('/', $part);
                $step = (int)$step;
                $start = $range === '*' ? 0 : (int)$range;
                if ($value >= $start && ($value - $start) % $step === 0) return true;
            } elseif (str_contains($part, '-')) {
                [$from, $to] = explode('-', $part);
                if ($value >= (int)$from && $value <= (int)$to) return true;
            } else {
                if ((int)$part === $value) return true;
            }
        }
        return false;
    }

    /** Ejecuta una tarea y retorna resultado */
    public function ejecutarTarea(array $automatizacion, string $ejecutadoPor = 'cron'): array
    {
        $idLog = $this->repository->crearLog($automatizacion['id'], $automatizacion['id_empresa'], $ejecutadoPor);
        $this->repository->marcarEnProceso($automatizacion['id']);

        $resultado      = 'exitoso';
        $registros      = 0;
        $mensaje        = null;
        $detalleError   = null;

        try {
            $handler = HandlerFactory::crear($automatizacion['modulo'], $automatizacion['accion']);
            $parametros = is_string($automatizacion['parametros'])
                ? json_decode($automatizacion['parametros'], true) ?? []
                : ($automatizacion['parametros'] ?? []);

            $retorno    = $handler->ejecutar($automatizacion['id_empresa'], $automatizacion['id_establecimiento'], $parametros);
            $registros  = $retorno['registros'] ?? 0;
            $mensaje    = $retorno['mensaje'] ?? 'Ejecución completada.';
        } catch (\Throwable $e) {
            $resultado    = 'error';
            $mensaje      = 'Error en la ejecución.';
            $detalleError = $e->getMessage();
        } finally {
            $this->repository->cerrarLog($idLog, $resultado, $registros, $mensaje, $detalleError);

            $proximaEjecucion = self::calcularProximaEjecucion(
                $automatizacion['frecuencia_tipo'],
                $automatizacion['frecuencia_valor'],
                $automatizacion['cron_expression'] ?? null
            );
            $this->repository->actualizarEjecucion($automatizacion['id'], $proximaEjecucion, $resultado);
            $this->repository->marcarActivo($automatizacion['id']);
        }

        return [
            'resultado'  => $resultado,
            'registros'  => $registros,
            'mensaje'    => $mensaje,
            'error'      => $detalleError,
        ];
    }

    // ── Catálogos para el frontend ────────────────────────────────────────────

    public function getModulosDisponibles(): array
    {
        return HandlerFactory::getModulosDisponibles();
    }

    public function getAccionesPorModulo(string $modulo): array
    {
        return HandlerFactory::getAccionesPorModulo($modulo);
    }

    public function getParametrosPorAccion(string $modulo, string $accion): array
    {
        return HandlerFactory::getParametrosPorAccion($modulo, $accion);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function prepararParams(array $data, int $idEmpresa, int $idUsuario, bool $esActualizacion): array
    {
        $proximaEjecucion = self::calcularProximaEjecucion(
            $data['frecuencia_tipo'],
            $data['frecuencia_valor'] ?? '',
            $data['cron_expression'] ?? null
        );

        $params = [
            ':id_empresa'          => $idEmpresa,
            ':id_establecimiento'  => !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            ':nombre'              => trim($data['nombre']),
            ':descripcion'         => trim($data['descripcion'] ?? ''),
            ':modulo'              => trim($data['modulo']),
            ':accion'              => trim($data['accion']),
            ':parametros'          => json_encode($data['parametros'] ?? [], JSON_UNESCAPED_UNICODE),
            ':frecuencia_tipo'     => $data['frecuencia_tipo'],
            ':frecuencia_valor'    => $data['frecuencia_valor'] ?? '',
            ':cron_expression'     => $data['frecuencia_tipo'] === 'cron_personalizado' ? trim($data['cron_expression']) : null,
            ':proxima_ejecucion'   => $data['estado'] === 'activo' ? $proximaEjecucion : null,
            ':estado'              => $data['estado'],
            ':updated_by'          => $idUsuario,
        ];

        if (!$esActualizacion) {
            $params[':created_by'] = $idUsuario;
        }

        return $params;
    }
}
