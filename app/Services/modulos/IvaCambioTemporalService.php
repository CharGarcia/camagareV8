<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\repositories\modulos\IvaCambioTemporalRepository;
use App\Rules\modulos\IvaCambioTemporalRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Cambio temporal de IVA en productos (empresas de turismo — IVA 8% en feriados).
 *
 * La empresa (marcada como de turismo por el superadmin) programa desde Productos
 * "tarifa actual → tarifa nueva" entre dos fechas. Al llegar la fecha desde se
 * cambia la tarifa de los productos que cumplen el filtro; al pasar la fecha hasta
 * cada uno vuelve a la tarifa que tenía. Lo dispara el cron cada minuto y, como
 * respaldo, AuthMiddleware al navegar (por si el cron no corrió).
 *
 * Las fechas se comparan con date('Y-m-d') de PHP (America/Guayaquil, bootstrap.php),
 * no con CURRENT_DATE de la BD, que puede estar en UTC: el SRI toma la fecha de
 * emisión del comprobante en Ecuador.
 */
class IvaCambioTemporalService
{
    public function __construct(
        private IvaCambioTemporalRepository $repo,
        private IvaCambioTemporalRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new IvaCambioTemporalRepository(), new IvaCambioTemporalRules(), new LogSistemaService());
    }

    /** ¿La empresa puede usar el cambio temporal de IVA? (migración aplicada + empresa de turismo). */
    public function disponible(int $idEmpresa): bool
    {
        return $this->repo->tablaDisponible() && (new Empresa())->esEmpresaTurismo($idEmpresa);
    }

    /** Datos del modal: tarifas con su conteo de productos e historial de cambios. */
    public function datosModal(int $idEmpresa): array
    {
        $this->exigirDisponible($idEmpresa);
        $conteo  = $this->repo->conteoPorTarifa($idEmpresa);
        $tarifas = [];
        foreach ($this->repo->getTarifas() as $t) {
            $c = $conteo[(int) $t['id']] ?? ['total' => 0, 'servicios' => 0, 'bienes' => 0];
            $tarifas[] = [
                'id'         => (int) $t['id'],
                'codigo'     => (string) $t['codigo'],
                'tarifa'     => (string) $t['tarifa'],
                'porcentaje' => (float) $t['porcentaje_iva'],
            ] + $c;
        }
        return [
            'hoy'       => date('Y-m-d'),
            'max_dias'  => IvaCambioTemporalRules::MAX_DIAS,
            'tarifas'   => $tarifas,
            'historial' => $this->repo->listar($idEmpresa),
        ];
    }

    public function getDetalle(int $id, int $idEmpresa): array
    {
        $this->exigirDisponible($idEmpresa);
        if ($this->repo->getById($id, $idEmpresa) === null) {
            throw new Exception('El cambio de IVA no existe.');
        }
        return $this->repo->getDetalle($id, $idEmpresa);
    }

    /**
     * Programa un cambio. Si la fecha desde es hoy, se aplica en el acto.
     *
     * @return array{id:int, estado:string, total_productos:int}
     */
    public function programar(array $d, int $idEmpresa, int $idUsuario): array
    {
        $this->exigirDisponible($idEmpresa);
        $hoy = date('Y-m-d');
        $d = [
            'id_empresa'       => $idEmpresa,
            'id_usuario'       => $idUsuario,
            'id_tarifa_actual' => (int) ($d['id_tarifa_actual'] ?? 0),
            'id_tarifa_nueva'  => (int) ($d['id_tarifa_nueva'] ?? 0),
            'aplica_a'         => (string) ($d['aplica_a'] ?? ''),
            'fecha_desde'      => trim((string) ($d['fecha_desde'] ?? '')),
            'fecha_hasta'      => trim((string) ($d['fecha_hasta'] ?? '')),
            'observaciones'    => trim(preg_replace('/\s+/', ' ', (string) ($d['observaciones'] ?? ''))),
        ];

        $this->repo->beginTransaction();
        try {
            $this->repo->lockEmpresa($idEmpresa);
            $this->rules->validarProgramacion(
                $d,
                $this->repo->getTarifa($d['id_tarifa_actual']),
                $this->repo->getTarifa($d['id_tarifa_nueva']),
                $hoy
            );
            // Revalidado bajo candado: dos cambios con fechas cruzadas dejarían a la
            // restauración del primero deshaciendo el segundo.
            $cruce = $this->repo->existeCruce($idEmpresa, $d['fecha_desde'], $d['fecha_hasta']);
            if ($cruce !== null) {
                throw new Exception(sprintf(
                    'Ya hay un cambio de IVA programado o vigente del %s al %s que se cruza con esas fechas.',
                    date('d-m-Y', strtotime($cruce['fecha_desde'])),
                    date('d-m-Y', strtotime($cruce['fecha_hasta']))
                ));
            }
            $id = $this->repo->insertar($d);
            $this->log->registrar($idUsuario, $idEmpresa, 'IVA_TEMPORAL_PROGRAMAR', 'iva_cambios_temporales', $id, null, $d);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        if ($d['fecha_desde'] <= $hoy) {
            $this->procesarCambio($id, $idEmpresa);
        }
        $cambio = $this->repo->getById($id, $idEmpresa) ?? [];
        return [
            'id'              => $id,
            'estado'          => (string) ($cambio['estado'] ?? 'programado'),
            'total_productos' => (int) ($cambio['total_productos'] ?? 0),
        ];
    }

    /** Cancela un cambio que todavía no se aplicó. */
    public function cancelar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->exigirDisponible($idEmpresa);
        $this->repo->beginTransaction();
        try {
            $this->repo->lockEmpresa($idEmpresa);
            $cambio = $this->repo->getById($id, $idEmpresa);
            if ($cambio === null) {
                throw new Exception('El cambio de IVA no existe.');
            }
            if ($cambio['estado'] !== 'programado') {
                throw new Exception('Solo se puede cancelar un cambio que todavía no se aplicó. Si ya está aplicado, use «Restaurar ahora».');
            }
            $this->repo->actualizarEstado($id, $idEmpresa, 'cancelado', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'IVA_TEMPORAL_CANCELAR', 'iva_cambios_temporales', $id,
                ['estado' => 'programado'], ['estado' => 'cancelado']);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Devuelve ya a los productos su tarifa anterior, sin esperar la fecha hasta.
     *
     * @return array{restaurados:int, omitidos:int}
     */
    public function restaurarAhora(int $id, int $idEmpresa, int $idUsuario): array
    {
        // Sin exigir "empresa de turismo": si el superadmin quitó la marca con un
        // cambio aplicado, la empresa igual debe poder volver a sus tarifas.
        if (!$this->repo->tablaDisponible()) {
            throw new Exception('El cambio temporal de IVA no está disponible.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->lockEmpresa($idEmpresa);
            $cambio = $this->repo->getById($id, $idEmpresa);
            if ($cambio === null) {
                throw new Exception('El cambio de IVA no existe.');
            }
            if ($cambio['estado'] !== 'aplicado') {
                throw new Exception('Solo se puede restaurar un cambio que está aplicado.');
            }
            $res = $this->restaurar($cambio, $idUsuario, 'IVA_TEMPORAL_RESTAURAR_MANUAL');
            $this->repo->commit();
            return $res;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Aplica o restaura lo que corresponda a la fecha de hoy en una empresa.
     * Lo llama AuthMiddleware como respaldo del cron. Nunca lanza.
     */
    public function procesarEmpresa(int $idEmpresa): void
    {
        if (!$this->repo->tablaDisponible()) {
            return;
        }
        foreach ($this->repo->getPendientes(date('Y-m-d'), $idEmpresa) as $p) {
            $this->procesarCambioSeguro((int) $p['id'], (int) $p['id_empresa']);
        }
    }

    /**
     * Cron: aplica o restaura lo pendiente de todas las empresas.
     *
     * @return string[] líneas para el log del cron
     */
    public function procesarPendientes(): array
    {
        if (!$this->repo->tablaDisponible()) {
            return [];
        }
        $lineas = [];
        foreach ($this->repo->getPendientes(date('Y-m-d')) as $p) {
            $r = $this->procesarCambioSeguro((int) $p['id'], (int) $p['id_empresa']);
            if ($r !== null) {
                $lineas[] = "Cambio de IVA #{$p['id']} (empresa {$p['id_empresa']}): {$r}";
            }
        }
        return $lineas;
    }

    private function procesarCambioSeguro(int $id, int $idEmpresa): ?string
    {
        try {
            return $this->procesarCambio($id, $idEmpresa);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'procesarCambio', 'id_cambio' => $id]);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Lleva un cambio al estado que le toca hoy, en su propia transacción y bajo el
     * candado de la empresa (el estado se relee dentro: otro proceso pudo ganarle).
     * Las acciones automáticas se registran a nombre de quien programó el cambio.
     */
    private function procesarCambio(int $id, int $idEmpresa): ?string
    {
        $hoy = date('Y-m-d');
        $this->repo->beginTransaction();
        try {
            $this->repo->lockEmpresa($idEmpresa);
            $cambio = $this->repo->getById($id, $idEmpresa);
            $resultado = null;
            if ($cambio !== null) {
                $idUsuario = (int) ($cambio['created_by'] ?? 0);
                if ($cambio['estado'] === 'programado' && $cambio['fecha_hasta'] < $hoy) {
                    // El período pasó entero sin que se aplicara (cron y sistema sin uso).
                    $this->repo->actualizarEstado($id, $idEmpresa, 'vencido', $idUsuario);
                    $this->log->registrar($idUsuario, $idEmpresa, 'IVA_TEMPORAL_VENCIDO', 'iva_cambios_temporales', $id,
                        ['estado' => 'programado'], ['estado' => 'vencido']);
                    $resultado = 'vencido sin aplicarse';
                } elseif ($cambio['estado'] === 'programado' && $cambio['fecha_desde'] <= $hoy) {
                    if (!(new Empresa())->esEmpresaTurismo($idEmpresa)) {
                        $this->repo->actualizarEstado($id, $idEmpresa, 'cancelado', $idUsuario);
                        $this->log->registrar($idUsuario, $idEmpresa, 'IVA_TEMPORAL_CANCELAR', 'iva_cambios_temporales', $id,
                            ['estado' => 'programado'], ['estado' => 'cancelado', 'motivo' => 'La empresa ya no está marcada como de turismo.']);
                        $resultado = 'cancelado: la empresa ya no es de turismo';
                    } else {
                        $n = $this->repo->aplicarProductos($cambio, $idUsuario);
                        $this->repo->actualizarEstado($id, $idEmpresa, 'aplicado', $idUsuario, ['total_productos' => $n]);
                        $this->log->registrar($idUsuario, $idEmpresa, 'IVA_TEMPORAL_APLICAR', 'iva_cambios_temporales', $id,
                            ['id_tarifa' => (int) $cambio['id_tarifa_actual']],
                            ['id_tarifa' => (int) $cambio['id_tarifa_nueva'], 'productos' => $n, 'aplica_a' => $cambio['aplica_a']]);
                        $resultado = "aplicado a {$n} producto(s)";
                    }
                } elseif ($cambio['estado'] === 'aplicado' && $cambio['fecha_hasta'] < $hoy) {
                    $res = $this->restaurar($cambio, $idUsuario, 'IVA_TEMPORAL_RESTAURAR');
                    $resultado = "restaurado en {$res['restaurados']} producto(s), {$res['omitidos']} omitido(s)";
                }
            }
            $this->repo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** @return array{restaurados:int, omitidos:int} (dentro de la transacción del llamador) */
    private function restaurar(array $cambio, int $idUsuario, string $accion): array
    {
        $id        = (int) $cambio['id'];
        $idEmpresa = (int) $cambio['id_empresa'];
        $res = $this->repo->restaurarProductos($id, $idEmpresa, $idUsuario);
        $this->repo->actualizarEstado($id, $idEmpresa, 'restaurado', $idUsuario, [
            'total_restaurados' => $res['restaurados'],
            'total_omitidos'    => $res['omitidos'],
        ]);
        $this->log->registrar($idUsuario, $idEmpresa, $accion, 'iva_cambios_temporales', $id,
            ['id_tarifa' => (int) $cambio['id_tarifa_nueva']],
            ['id_tarifa' => (int) $cambio['id_tarifa_actual']] + $res);
        return $res;
    }

    private function exigirDisponible(int $idEmpresa): void
    {
        if (!$this->disponible($idEmpresa)) {
            throw new Exception('El cambio temporal de IVA solo está disponible para empresas marcadas como de turismo.');
        }
    }
}
