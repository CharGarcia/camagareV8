<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\PlanCuentaDimensionRepository;
use App\repositories\modulos\PlanCuentaRepository;
use App\Rules\modulos\PlanCuentaDimensionRules;
use App\Services\LogSistemaService;

/**
 * Centro de costo / proyecto sugerido por periodo de una cuenta contable.
 * Ver PlanCuentaDimensionRepository: es una sugerencia para las líneas de los asientos,
 * nunca reescribe lo ya registrado.
 */
class PlanCuentaDimensionService
{
    private const TABLA = PlanCuentaDimensionRepository::TABLA;

    public function __construct(
        private PlanCuentaDimensionRepository $repository,
        private PlanCuentaRepository $cuentas,
        private PlanCuentaDimensionRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new PlanCuentaDimensionRepository(), new PlanCuentaRepository(), new PlanCuentaDimensionRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->repository->disponible();
    }

    /** $tipo = 'centro' | 'proyecto'. */
    public function listar(int $idCuenta, int $idEmpresa, string $tipo): array
    {
        return $this->repository->listarPorCuenta($idCuenta, $idEmpresa, $tipo);
    }

    /** Centro/proyecto a proponer en una línea de asiento con esa cuenta y esa fecha. */
    public function sugerencia(int $idCuenta, int $idEmpresa, string $fecha): ?array
    {
        if ($idCuenta <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return null;
        return $this->repository->getVigente($idCuenta, $idEmpresa, $fecha);
    }

    /**
     * Líneas de asientos ya registrados de la cuenta que quedarían con centro / proyecto si se
     * aplican sus periodos: ['centro' => ['aplicables' => n, 'en_cerrado' => n], 'proyecto' => …].
     */
    public function lineasPorRellenar(int $idCuenta, int $idEmpresa): array
    {
        return $this->repository->contarLineasSinDimension($idCuenta, $idEmpresa);
    }

    /**
     * Aplica los periodos de la cuenta a sus asientos ya registrados: SOLO a las líneas que
     * tienen el centro de costo / proyecto vacío. Nunca cambia uno ya puesto, ni toca asientos
     * anulados, de cierre / apertura del ejercicio o en períodos contables cerrados.
     * Todo en una transacción, con el candado de la cuenta y auditoría por tipo.
     * Devuelve ['centro' => n, 'proyecto' => n] líneas completadas.
     */
    public function rellenarAsientos(int $idCuenta, int $idEmpresa, int $idUsuario): array
    {
        if (!$this->repository->disponible()) {
            throw new \RuntimeException('Falta aplicar la migración 20261028_plan_cuentas_dimension_vigencia.sql.');
        }
        $this->rules->validarCuenta($this->cuentas->findById($idCuenta, $idEmpresa));

        $out = ['centro' => 0, 'proyecto' => 0];
        $this->repository->beginTransaction();
        try {
            // Que nadie cambie los periodos de la cuenta mientras se aplican (§8).
            $this->repository->lockCuenta($idCuenta, $idEmpresa);

            foreach (array_keys(PlanCuentaDimensionRepository::TIPOS) as $tipo) {
                $lineas = $this->repository->rellenarLineasAsientos($idCuenta, $idEmpresa, $tipo, $idUsuario);
                if ($lineas === []) continue;
                $out[$tipo] = count($lineas);

                // Una entrada por tipo: qué líneas recibieron qué centro / proyecto.
                $porValor = [];
                foreach ($lineas as $l) {
                    $porValor[(int) $l['valor']][] = (int) $l['id'];
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'RELLENAR_' . strtoupper($tipo), 'asientos_contables_detalle', null,
                    ['id_cuenta' => $idCuenta, PlanCuentaDimensionRepository::TIPOS[$tipo] => null],
                    [
                        'id_cuenta' => $idCuenta,
                        'campo'     => PlanCuentaDimensionRepository::TIPOS[$tipo],
                        'lineas'    => count($lineas),
                        'asientos'  => array_values(array_unique(array_map(fn($l) => (int) $l['id_asiento'], $lineas))),
                        'detalle_por_valor' => $porValor,
                    ]);
            }

            $this->repository->commit();
            return $out;
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Completa el centro de costo y el proyecto de las líneas de un asiento con los vigentes
     * de cada cuenta a la fecha del asiento. Solo llena lo que la línea trae vacío: si el
     * módulo ya mandó un centro o un proyecto, se respeta. Una sola consulta por asiento.
     */
    public function completarLineas(array $lineas, int $idEmpresa, string $fecha): array
    {
        $fecha = substr($fecha, 0, 10);
        if ($lineas === [] || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return $lineas;

        $vigentes = $this->repository->getVigentesPorCuentas(array_column($lineas, 'id_cuenta_contable'), $idEmpresa, $fecha);
        if ($vigentes === []) return $lineas;

        foreach ($lineas as &$l) {
            $v = $vigentes[(int) ($l['id_cuenta_contable'] ?? 0)] ?? null;
            if ($v === null) continue;
            if (empty($l['id_centro_costo']) && $v['id_centro_costo'] !== null) $l['id_centro_costo'] = $v['id_centro_costo'];
            if (empty($l['id_proyecto']) && $v['id_proyecto'] !== null)         $l['id_proyecto']     = $v['id_proyecto'];
        }
        unset($l);
        return $lineas;
    }

    /**
     * Las líneas de un asiento solo pueden llevar centros de costo / proyectos de la propia
     * empresa (no eliminados). Un inactivo se admite: puede venir de un asiento anterior.
     * Una consulta por id distinto; sin centro ni proyecto no consulta nada.
     */
    public function validarDeEmpresa(array $lineas, int $idEmpresa): void
    {
        foreach (PlanCuentaDimensionRepository::TIPOS as $tipo => $col) {
            $ids = array_unique(array_filter(array_map(fn($l) => (int) ($l[$col] ?? 0), $lineas), fn($v) => $v > 0));
            foreach ($ids as $id) {
                if (!$this->repository->getDimension($tipo, $id, $idEmpresa)) {
                    throw new \InvalidArgumentException($tipo === 'centro'
                        ? 'Una línea del asiento tiene un centro de costo que no existe en esta empresa.'
                        : 'Una línea del asiento tiene un proyecto que no existe en esta empresa.');
                }
            }
        }
    }

    /**
     * Deja los periodos de la cuenta exactamente como vienen en las líneas de sus pestañas:
     * $porTipo = ['centro' => [...], 'proyecto' => [...]], cada línea con 'id' (0 = nueva),
     * id_dimension, vigente_desde, vigente_hasta. Crea las nuevas, actualiza las que
     * cambiaron y elimina (lógico) las que ya no están. Un tipo que no viene no se toca.
     * Todo o nada, en una transacción y bajo el candado de la cuenta. Devuelve cuántas
     * filas se tocaron.
     */
    public function sincronizar(int $idCuenta, array $porTipo, int $idEmpresa, int $idUsuario): int
    {
        if (!$this->repository->disponible()) {
            throw new \RuntimeException('Falta aplicar la migración 20261028_plan_cuentas_dimension_vigencia.sql.');
        }
        $this->rules->validarCuenta($this->cuentas->findById($idCuenta, $idEmpresa));

        // 1. Validar todas las líneas antes de escribir nada.
        $nuevasPorTipo = [];
        foreach (PlanCuentaDimensionRepository::TIPOS as $tipo => $col) {
            if (!isset($porTipo[$tipo]) || !is_array($porTipo[$tipo])) continue;
            $nuevas = [];
            foreach (array_values($porTipo[$tipo]) as $i => $f) {
                $f = (array) $f;
                $d = $this->rules->normalizar(['tipo' => $tipo, 'id_cuenta' => $idCuenta] + $f);
                unset($d['tipo']);
                if (!$this->repository->getDimension($tipo, (int) $d[$col], $idEmpresa)) {
                    throw new \InvalidArgumentException($tipo === 'centro' ? 'Centro de costo no encontrado.' : 'Proyecto no encontrado.');
                }
                $nuevas[] = ['id' => (int) ($f['id'] ?? 0), 'datos' => $d, 'linea' => $i + 1];
            }
            $this->rules->validarSinCruces($nuevas, $tipo);
            $nuevasPorTipo[$tipo] = $nuevas;
        }
        if ($nuevasPorTipo === []) return 0;

        $campos = ['id_centro_costo', 'id_proyecto', 'vigente_desde', 'vigente_hasta', 'observacion'];
        $auditar = static fn(array $r) => array_intersect_key($r, array_flip(array_merge(['id_cuenta'], $campos)));
        $tocadas = 0;

        $this->repository->beginTransaction();
        try {
            // 2. Leer los periodos actuales → escribir, bajo el candado de la cuenta (§8).
            $this->repository->lockCuenta($idCuenta, $idEmpresa);

            foreach ($nuevasPorTipo as $tipo => $nuevas) {
                $actuales = [];
                foreach ($this->repository->listarPorCuenta($idCuenta, $idEmpresa, $tipo) as $r) {
                    $actuales[(int) $r['id']] = $r;
                }

                $conservar = [];
                foreach ($nuevas as $n) {
                    $d = $n['datos'];
                    if ($n['id'] > 0) {
                        // Solo se editan periodos de esta cuenta y de este tipo.
                        if (!isset($actuales[$n['id']])) {
                            throw new \InvalidArgumentException('Una línea de ' . ($tipo === 'centro' ? 'Centro de costos' : 'Proyectos')
                                . ' ya no existe. Cierre y vuelva a abrir la cuenta.');
                        }
                        $antes = $actuales[$n['id']];
                        $conservar[$n['id']] = true;
                        $cambio = false;
                        foreach ($campos as $c) {
                            if ((string) ($antes[$c] ?? '') !== (string) ($d[$c] ?? '')) { $cambio = true; break; }
                        }
                        if (!$cambio) continue;
                        $this->repository->actualizar($n['id'], $d, $idEmpresa, $idUsuario);
                        $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', self::TABLA, $n['id'], $auditar($antes), $d);
                    } else {
                        $id = $this->repository->crear($d, $idEmpresa, $idUsuario);
                        $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', self::TABLA, $id, null, $d);
                    }
                    $tocadas++;
                }

                foreach ($actuales as $id => $antes) {
                    if (isset($conservar[$id])) continue;
                    $this->repository->eliminar($id, $idEmpresa, $idUsuario);
                    $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', self::TABLA, $id, $auditar($antes), null);
                    $tocadas++;
                }
            }

            $this->repository->commit();
            return $tocadas;
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
