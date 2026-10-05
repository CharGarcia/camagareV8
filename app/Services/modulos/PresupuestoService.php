<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\PresupuestoRepository;
use App\Rules\modulos\PresupuestoRules;
use App\Services\LogSistemaService;

/**
 * Presupuestos: cabecera + versiones (Original y Reformas) con líneas por cuenta y mes.
 *
 * Reglas:
 *  - Al crear nace la versión 1 «Original» en borrador. La grilla solo se edita en una versión
 *    en borrador. Aprobar la congela (acta, quién, cuándo) y deja a las aprobadas anteriores como
 *    «reemplazada». La vigente es la última aprobada.
 *  - Reforma = nueva versión en borrador copiando la vigente; se edita y se aprueba con su motivo.
 *  - Mientras ninguna versión está aprobada, la cabecera (período, alcance) se puede cambiar; después
 *    solo nombre, umbrales, observaciones y la marca «base de alícuotas».
 *  - Ejecución: lo real sale de los asientos (PresupuestoRepository::getEjecucion), nunca se digita.
 */
class PresupuestoService
{
    public function __construct(
        private PresupuestoRepository $repo,
        private PresupuestoRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new PresupuestoRepository(), new PresupuestoRules(), new LogSistemaService());
    }

    public function repo(): PresupuestoRepository
    {
        return $this->repo;
    }

    // ── Listado y detalle ───────────────────────────────────────────────────

    /** Listado con % de ejecución de la versión vigente (una consulta por fila: son pocos). */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $ordenMulti, ?int $idUsuarioFiltro): array
    {
        $res = $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenMulti, $idUsuarioFiltro);
        foreach ($res['rows'] as &$r) {
            $r['ejecutado_gastos'] = null;
            $r['pct_ejecucion'] = null;
            if (!empty($r['id_version_vigente']) && $r['version_estado'] === 'aprobada') {
                try {
                    $ej = $this->ejecucion((int) $r['id'], $idEmpresa, (int) $r['id_version_vigente'], false);
                    $r['ejecutado_gastos'] = $ej['totales']['gastos']['ejecutado'];
                    $r['pct_ejecucion']    = $ej['totales']['gastos']['pct'];
                } catch (\Throwable $e) {
                    // La fila se muestra igual, sin ejecución.
                }
            }
        }
        unset($r);
        return $res;
    }

    /**
     * Cabecera + versiones + líneas de la versión pedida (o la vigente; si no hay, el borrador).
     */
    public function getDetalle(int $id, int $idEmpresa, ?int $idVersion = null): array
    {
        $cab = $this->repo->findById($id, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        $versiones = $this->repo->getVersiones($id, $idEmpresa);
        $ver = null;
        if ($idVersion) {
            foreach ($versiones as $v) {
                if ((int) $v['id'] === $idVersion) {
                    $ver = $v;
                }
            }
            if (!$ver) {
                throw new \DomainException('La versión no pertenece a este presupuesto.');
            }
        } else {
            $ver = $this->repo->getVersionVigente($id, $idEmpresa) ?? $this->repo->getVersionBorrador($id, $idEmpresa);
        }
        $meses = PresupuestoRules::listarMeses((string) $cab['periodo_desde'], (string) $cab['periodo_hasta']);
        $cab['meses'] = $meses;
        $cab['tiene_aprobada'] = (bool) array_filter($versiones, fn($v) => $v['estado'] !== 'borrador');
        $cab['id_version_borrador'] = $this->repo->getVersionBorrador($id, $idEmpresa)['id'] ?? null;
        $cab['id_version_vigente']  = $this->repo->getVersionVigente($id, $idEmpresa)['id'] ?? null;

        return [
            'cabecera'  => $cab,
            'versiones' => $versiones,
            'version'   => $ver,
            'lineas'    => $ver ? $this->repo->getLineas((int) $ver['id'], $idEmpresa) : [],
            'rubros'    => $this->repo->getRubros($idEmpresa),
        ];
    }

    // ── Cabecera ────────────────────────────────────────────────────────────

    public function crearPresupuesto(array $data, int $idEmpresa, int $idUsuario): int
    {
        $d = $this->rules->validarCabecera($data) + ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario];
        $this->repo->beginTransaction();
        try {
            $id = $this->repo->create($d);
            $this->repo->crearVersion($idEmpresa, $id, 1, 'Original', null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'presupuestos', $id, null, $d);
            $this->repo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function actualizarPresupuesto(int $id, array $data, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repo->findById($id, $idEmpresa);
        if (!$antes) {
            throw new \DomainException('El presupuesto no existe.');
        }
        if ($antes['estado'] === 'cerrado') {
            throw new \DomainException('El presupuesto está cerrado. Reábralo para modificarlo.');
        }
        $d = $this->rules->validarCabecera($data) + ['id_usuario' => $idUsuario];
        $tieneAprobada = $this->repo->getVersionVigente($id, $idEmpresa) !== null;

        $this->repo->beginTransaction();
        try {
            if ($tieneAprobada) {
                // Con una versión aprobada el período y el alcance quedan fijos: cambiarlos
                // dejaría sin sentido lo ya aprobado y la ejecución comparada.
                $this->repo->updateDatosAbiertos($id, $idEmpresa, $d);
            } else {
                $this->repo->update($id, $idEmpresa, $d);
                // Si el período se acortó, los valores de meses que quedaron fuera se descartan.
                $meses = PresupuestoRules::listarMeses($d['periodo_desde'], $d['periodo_hasta']);
                $borrador = $this->repo->getVersionBorrador($id, $idEmpresa);
                if ($borrador) {
                    foreach ($this->repo->getLineas((int) $borrador['id'], $idEmpresa) as $l) {
                        $filtrados = array_intersect_key($l['valores'], array_flip($meses));
                        if (count($filtrados) !== count($l['valores'])) {
                            $this->repo->guardarValores((int) $l['id'], $idEmpresa, $filtrados);
                        }
                    }
                }
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'presupuestos', $id, $antes, $d);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Solo se elimina un presupuesto que nunca se aprobó. */
    public function eliminarPresupuesto(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findById($id, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        if ($this->repo->getVersionVigente($id, $idEmpresa) !== null) {
            throw new \DomainException('Este presupuesto tiene una versión aprobada: no se elimina, se cierra.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'presupuestos', $id, $cab, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id, string $estado, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findById($id, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        if ($estado === 'cerrado' && $cab['estado'] !== 'aprobado') {
            throw new \DomainException('Solo se cierra un presupuesto aprobado.');
        }
        if ($estado === 'aprobado' && $cab['estado'] !== 'cerrado') {
            throw new \DomainException('Solo se reabre un presupuesto cerrado.');
        }
        if (!in_array($estado, ['aprobado', 'cerrado'], true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }
        $this->repo->updateEstado($id, $idEmpresa, $estado, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, $estado === 'cerrado' ? 'cerrar' : 'reabrir', 'presupuestos', $id, ['estado' => $cab['estado']], ['estado' => $estado]);
    }

    // ── Líneas ──────────────────────────────────────────────────────────────

    /**
     * Reemplaza las líneas de la versión en borrador con lo que manda la grilla. Las que vienen
     * con id se actualizan, las nuevas se insertan y las que ya no vienen se dan de baja.
     */
    public function guardarLineas(int $idVersion, array $lineas, int $idEmpresa, int $idUsuario): array
    {
        $ver = $this->repo->getVersion($idVersion, $idEmpresa);
        if (!$ver) {
            throw new \DomainException('La versión no existe.');
        }
        if ($ver['estado'] !== 'borrador') {
            throw new \DomainException('Esta versión ya está aprobada y no se puede modificar. Cree una reforma.');
        }
        $cab = $this->repo->findById((int) $ver['id_presupuesto'], $idEmpresa);
        if ($cab['estado'] === 'cerrado') {
            throw new \DomainException('El presupuesto está cerrado.');
        }
        $meses   = PresupuestoRules::listarMeses((string) $cab['periodo_desde'], (string) $cab['periodo_hasta']);
        $cuentas = $this->repo->getCuentasPorIds($idEmpresa, array_column($lineas, 'id_cuenta'));
        $validas = $this->rules->validarLineas($lineas, $cuentas, $meses);

        $this->repo->beginTransaction();
        try {
            $this->repo->bloquear('presupuesto_version:' . $idVersion);
            $existentes = [];
            foreach ($this->repo->getLineas($idVersion, $idEmpresa) as $l) {
                $existentes[(int) $l['id']] = (int) $l['id_cuenta'];
            }
            $conservar = [];
            foreach ($validas as $l) {
                $idLinea = $l['id'];
                if ($idLinea > 0 && isset($existentes[$idLinea]) && $existentes[$idLinea] === $l['id_cuenta']) {
                    $this->repo->actualizarLinea($idLinea, $idEmpresa, $l['id_rubro'], $l['orden'], $idUsuario);
                } else {
                    $idLinea = $this->repo->insertarLinea($idEmpresa, $idVersion, $l['id_cuenta'], $l['id_rubro'], $l['orden'], $idUsuario);
                }
                $this->repo->guardarValores($idLinea, $idEmpresa, $l['valores']);
                $conservar[] = $idLinea;
            }
            $this->repo->eliminarLineasExcepto($idVersion, $idEmpresa, $conservar, $idUsuario);

            $total = 0.0;
            foreach ($validas as $l) {
                $total += array_sum($l['valores']);
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'guardar_lineas', 'presupuestos_versiones', $idVersion, null,
                ['lineas' => count($validas), 'total' => round($total, 2)]);
            $this->repo->commit();
            return ['lineas' => count($validas), 'total' => round($total, 2)];
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Versiones ───────────────────────────────────────────────────────────

    public function aprobarVersion(int $idVersion, array $data, int $idEmpresa, int $idUsuario): void
    {
        $ver = $this->repo->getVersion($idVersion, $idEmpresa);
        if (!$ver) {
            throw new \DomainException('La versión no existe.');
        }
        if ($ver['estado'] !== 'borrador') {
            throw new \DomainException('Esta versión ya fue aprobada.');
        }
        if (!$this->repo->getLineas($idVersion, $idEmpresa)) {
            throw new \DomainException('La versión no tiene líneas: agregue cuentas y montos antes de aprobar.');
        }
        $ap = $this->rules->validarAprobacion($data);
        $idPresupuesto = (int) $ver['id_presupuesto'];

        $this->repo->beginTransaction();
        try {
            $this->repo->bloquear('presupuesto:' . $idPresupuesto);
            $this->repo->aprobarVersion($idVersion, $idEmpresa, $ap['acta'], $ap['observacion'], $idUsuario);
            $this->repo->reemplazarVersionesAnteriores($idPresupuesto, $idVersion, $idEmpresa, $idUsuario);
            $this->repo->updateEstado($idPresupuesto, $idEmpresa, 'aprobado', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'aprobar_version', 'presupuestos_versiones', $idVersion,
                ['estado' => 'borrador'], ['estado' => 'aprobada', 'numero' => $ver['numero']] + $ap);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Nueva versión en borrador copiando la vigente. Solo puede haber un borrador a la vez. */
    public function crearReforma(int $idPresupuesto, string $motivo, int $idEmpresa, int $idUsuario): int
    {
        $cab = $this->repo->findById($idPresupuesto, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        if ($cab['estado'] === 'cerrado') {
            throw new \DomainException('El presupuesto está cerrado. Reábralo para reformarlo.');
        }
        $vigente = $this->repo->getVersionVigente($idPresupuesto, $idEmpresa);
        if (!$vigente) {
            throw new \DomainException('Aún no hay una versión aprobada: edite la Original en lugar de reformar.');
        }
        if ($this->repo->getVersionBorrador($idPresupuesto, $idEmpresa)) {
            throw new \DomainException('Ya hay una reforma en borrador. Apruébela o elimínela antes de crear otra.');
        }
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Indique el motivo de la reforma.|#pre_motivo');
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->bloquear('presupuesto:' . $idPresupuesto);
            $versiones = $this->repo->getVersiones($idPresupuesto, $idEmpresa);
            $numero    = count($versiones) ? max(array_map(fn($v) => (int) $v['numero'], $versiones)) + 1 : 2;
            $idNueva   = $this->repo->crearVersion($idEmpresa, $idPresupuesto, $numero, 'Reforma ' . ($numero - 1), $motivo, $idUsuario);
            $this->repo->copiarLineas((int) $vigente['id'], $idNueva, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'crear_reforma', 'presupuestos_versiones', $idNueva, null,
                ['id_presupuesto' => $idPresupuesto, 'numero' => $numero, 'motivo' => $motivo, 'copiada_de' => $vigente['id']]);
            $this->repo->commit();
            return $idNueva;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Descarta una reforma en borrador (nunca la Original ni una aprobada). */
    public function eliminarReforma(int $idVersion, int $idEmpresa, int $idUsuario): void
    {
        $ver = $this->repo->getVersion($idVersion, $idEmpresa);
        if (!$ver) {
            throw new \DomainException('La versión no existe.');
        }
        if ($ver['estado'] !== 'borrador' || (int) $ver['numero'] === 1) {
            throw new \DomainException('Solo se descarta una reforma en borrador.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->eliminarLineasExcepto($idVersion, $idEmpresa, [], $idUsuario);
            $this->repo->eliminarVersion($idVersion, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar_reforma', 'presupuestos_versiones', $idVersion, $ver, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Herramientas para armar la grilla ──────────────────────────────────

    /**
     * Líneas para precargar la grilla (sin grabar): copiadas de otra versión, ajustando los meses al
     * período de este presupuesto por posición (enero→enero si ambos son anuales; si el período
     * origen es más corto, el resto queda en 0; si es más largo, se recorta).
     */
    public function lineasDesdeVersion(int $idVersionOrigen, int $idPresupuestoDestino, int $idEmpresa, float $pct = 0.0): array
    {
        $origen = $this->repo->getVersion($idVersionOrigen, $idEmpresa);
        $cabDest = $this->repo->findById($idPresupuestoDestino, $idEmpresa);
        if (!$origen || !$cabDest) {
            throw new \DomainException('Presupuesto o versión no encontrados.');
        }
        $cabOri    = $this->repo->findById((int) $origen['id_presupuesto'], $idEmpresa);
        $mesesOri  = PresupuestoRules::listarMeses((string) $cabOri['periodo_desde'], (string) $cabOri['periodo_hasta']);
        $mesesDest = PresupuestoRules::listarMeses((string) $cabDest['periodo_desde'], (string) $cabDest['periodo_hasta']);
        $factor    = 1 + $pct / 100;
        $out = [];
        foreach ($this->repo->getLineas($idVersionOrigen, $idEmpresa) as $l) {
            $valores = [];
            foreach ($mesesDest as $i => $ym) {
                $origYm = $mesesOri[$i] ?? null;
                $valores[$ym] = $origYm !== null ? round(((float) ($l['valores'][$origYm] ?? 0)) * $factor, 2) : 0.0;
            }
            $out[] = ['id' => 0, 'id_cuenta' => (int) $l['id_cuenta'], 'cuenta_codigo' => $l['cuenta_codigo'], 'cuenta_nombre' => $l['cuenta_nombre'],
                      'id_rubro' => $l['id_rubro'] !== null ? (int) $l['id_rubro'] : null, 'rubro_nombre' => $l['rubro_nombre'], 'valores' => $valores,
                      'es_ingreso' => str_starts_with((string) $l['cuenta_codigo'], '4')];
        }
        return $out;
    }

    /**
     * Líneas (sin grabar) a partir de lo realmente ejecutado en un período anterior, más un %.
     * Toma las cuentas de detalle con movimiento y las alinea por posición de mes.
     */
    public function lineasDesdeEjecutado(int $idPresupuesto, string $desdeYm, string $hastaYm, float $pct, int $idEmpresa): array
    {
        $cab = $this->repo->findById($idPresupuesto, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $desdeYm) || !preg_match('/^\d{4}-\d{2}$/', $hastaYm) || $hastaYm < $desdeYm) {
            throw new \InvalidArgumentException('Indique el período de referencia (mes inicial y final).');
        }
        $mesesRef  = PresupuestoRules::listarMeses($desdeYm . '-01', $hastaYm . '-01');
        $mesesDest = PresupuestoRules::listarMeses((string) $cab['periodo_desde'], (string) $cab['periodo_hasta']);
        $hastaFin  = (new \DateTimeImmutable($hastaYm . '-01'))->modify('last day of this month')->format('Y-m-d');
        $factor    = 1 + $pct / 100;

        $porCuenta = [];
        foreach ($this->repo->getEjecutadoPorCuentaDetalle($idEmpresa, $desdeYm . '-01', $hastaFin, (string) $cab['alcance'], $cab['id_centro_costo'] ?? $cab['id_proyecto'] ?? null) as $r) {
            $porCuenta[(int) $r['id_cuenta']] ??= ['id' => 0, 'id_cuenta' => (int) $r['id_cuenta'], 'cuenta_codigo' => $r['codigo'], 'cuenta_nombre' => $r['nombre'],
                'id_rubro' => null, 'rubro_nombre' => null, 'ref' => [], 'es_ingreso' => str_starts_with((string) $r['codigo'], '4')];
            $porCuenta[(int) $r['id_cuenta']]['ref'][(string) $r['periodo']] = (float) $r['monto'];
        }
        $out = [];
        foreach ($porCuenta as $l) {
            $valores = [];
            foreach ($mesesDest as $i => $ym) {
                $refYm = $mesesRef[$i] ?? null;
                $valores[$ym] = $refYm !== null ? round(max(0.0, $l['ref'][$refYm] ?? 0) * $factor, 2) : 0.0;
            }
            unset($l['ref']);
            $l['valores'] = $valores;
            $out[] = $l;
        }
        return $out;
    }

    // ── Ejecución ───────────────────────────────────────────────────────────

    /**
     * Presupuesto vs real de una versión, por línea y por mes, con totales por rubro y generales y
     * el semáforo de la cabecera (verde < amarillo ≤ … < rojo). En ingresos el semáforo se invierte:
     * quedarse corto es lo malo. Los meses futuros (después del mes actual) no se evalúan.
     *
     * @param bool $conDetalle false = solo totales (para el listado)
     */
    public function ejecucion(int $idPresupuesto, int $idEmpresa, ?int $idVersion = null, bool $conDetalle = true): array
    {
        $cab = $this->repo->findById($idPresupuesto, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        $ver = $idVersion ? $this->repo->getVersion($idVersion, $idEmpresa) : $this->repo->getVersionVigente($idPresupuesto, $idEmpresa);
        if (!$ver || (int) $ver['id_presupuesto'] !== $idPresupuesto) {
            throw new \DomainException('No hay una versión aprobada para comparar.');
        }
        $meses  = PresupuestoRules::listarMeses((string) $cab['periodo_desde'], (string) $cab['periodo_hasta']);
        $lineas = $this->repo->getLineas((int) $ver['id'], $idEmpresa);

        // Cuentas ordenadas de la más específica a la más general (ver getEjecucion).
        $cuentas = array_map(fn($l) => ['id_cuenta' => (int) $l['id_cuenta'], 'codigo' => (string) $l['cuenta_codigo']], $lineas);
        usort($cuentas, fn($a, $b) => strlen($b['codigo']) <=> strlen($a['codigo']));
        $hastaFin = (new \DateTimeImmutable((string) $cab['periodo_hasta']))->modify('last day of this month')->format('Y-m-d');
        $real = $this->repo->getEjecucion($idEmpresa, $cuentas, (string) $cab['periodo_desde'], $hastaFin,
            (string) $cab['alcance'], $cab['id_centro_costo'] ?? $cab['id_proyecto'] ?? null);

        $mesActual = date('Y-m');
        $am = (float) $cab['umbral_amarillo'];
        $ro = (float) $cab['umbral_rojo'];
        $semaforo = function (float $pres, float $ejec, bool $ingreso, bool $cerrado) use ($am, $ro): string {
            if ($pres <= 0 && $ejec <= 0) return 'gris';
            if ($pres <= 0) return $ingreso ? 'verde' : 'rojo';
            $pct = $ejec / $pres * 100;
            if ($ingreso) {
                // Ingresos: lo malo es no llegar. Solo se juzga un mes ya cerrado.
                if (!$cerrado) return 'gris';
                return $pct >= 100 ? 'verde' : ($pct >= $am ? 'amarillo' : 'rojo');
            }
            return $pct >= $ro ? 'rojo' : ($pct >= $am ? 'amarillo' : 'verde');
        };

        $filas = [];
        $tot = ['ingresos' => ['presupuesto' => 0.0, 'ejecutado' => 0.0], 'gastos' => ['presupuesto' => 0.0, 'ejecutado' => 0.0]];
        $totMes = [];
        $rubros = [];
        foreach ($lineas as $l) {
            $ingreso = (bool) $l['es_ingreso'];
            $clase = $ingreso ? 'ingresos' : 'gastos';
            $fila = ['id_cuenta' => (int) $l['id_cuenta'], 'cuenta_codigo' => $l['cuenta_codigo'], 'cuenta_nombre' => $l['cuenta_nombre'],
                     'rubro' => $l['rubro_nombre'] ?: 'Sin rubro', 'es_ingreso' => $ingreso, 'meses' => [], 'presupuesto' => 0.0, 'ejecutado' => 0.0];
            foreach ($meses as $ym) {
                $p = round((float) ($l['valores'][$ym] ?? 0), 2);
                $e = round((float) ($real[(int) $l['id_cuenta']][$ym] ?? 0), 2);
                $fila['meses'][$ym] = ['presupuesto' => $p, 'ejecutado' => $e, 'diferencia' => round($e - $p, 2),
                                       'semaforo' => $ym > $mesActual ? 'futuro' : $semaforo($p, $e, $ingreso, $ym < $mesActual)];
                $fila['presupuesto'] += $p;
                $fila['ejecutado']   += $e;
                $totMes[$clase][$ym]['presupuesto'] = round(($totMes[$clase][$ym]['presupuesto'] ?? 0) + $p, 2);
                $totMes[$clase][$ym]['ejecutado']   = round(($totMes[$clase][$ym]['ejecutado'] ?? 0) + $e, 2);
            }
            $fila['presupuesto'] = round($fila['presupuesto'], 2);
            $fila['ejecutado']   = round($fila['ejecutado'], 2);
            $fila['diferencia']  = round($fila['ejecutado'] - $fila['presupuesto'], 2);
            $fila['pct']         = $fila['presupuesto'] > 0 ? round($fila['ejecutado'] / $fila['presupuesto'] * 100, 1) : null;
            $fila['semaforo']    = $semaforo($fila['presupuesto'], $fila['ejecutado'], $ingreso, (string) end($meses) < $mesActual);
            $tot[$clase]['presupuesto'] += $fila['presupuesto'];
            $tot[$clase]['ejecutado']   += $fila['ejecutado'];
            $rubros[$clase][$fila['rubro']]['presupuesto'] = round(($rubros[$clase][$fila['rubro']]['presupuesto'] ?? 0) + $fila['presupuesto'], 2);
            $rubros[$clase][$fila['rubro']]['ejecutado']   = round(($rubros[$clase][$fila['rubro']]['ejecutado'] ?? 0) + $fila['ejecutado'], 2);
            if ($conDetalle) {
                $filas[] = $fila;
            }
        }
        foreach ($tot as $clase => &$t) {
            $t['presupuesto'] = round($t['presupuesto'], 2);
            $t['ejecutado']   = round($t['ejecutado'], 2);
            $t['diferencia']  = round($t['ejecutado'] - $t['presupuesto'], 2);
            $t['pct']         = $t['presupuesto'] > 0 ? round($t['ejecutado'] / $t['presupuesto'] * 100, 1) : null;
            $t['semaforo']    = $semaforo($t['presupuesto'], $t['ejecutado'], $clase === 'ingresos', (string) end($meses) < $mesActual);
        }
        unset($t);
        foreach ($rubros as $clase => &$rs) {
            foreach ($rs as $nombre => &$r) {
                $r['diferencia'] = round($r['ejecutado'] - $r['presupuesto'], 2);
                $r['pct']        = $r['presupuesto'] > 0 ? round($r['ejecutado'] / $r['presupuesto'] * 100, 1) : null;
                $r['semaforo']   = $semaforo($r['presupuesto'], $r['ejecutado'], $clase === 'ingresos', (string) end($meses) < $mesActual);
            }
            unset($r);
        }
        unset($rs);

        return [
            'cabecera'   => ['id' => (int) $cab['id'], 'nombre' => $cab['nombre'], 'alcance' => $cab['alcance'], 'umbral_amarillo' => $am, 'umbral_rojo' => $ro,
                             'centro_costo_nombre' => $cab['centro_costo_nombre'] ?? null, 'proyecto_nombre' => $cab['proyecto_nombre'] ?? null],
            'version'    => ['id' => (int) $ver['id'], 'numero' => (int) $ver['numero'], 'nombre' => $ver['nombre'], 'estado' => $ver['estado']],
            'meses'      => $meses,
            'mes_actual' => $mesActual,
            'filas'      => $filas,
            'rubros'     => $rubros,
            'por_mes'    => $totMes,
            'totales'    => $tot,
        ];
    }

    public function asientosEjecucion(int $idPresupuesto, int $idCuenta, string $periodoYm, int $idEmpresa): array
    {
        $cab = $this->repo->findById($idPresupuesto, $idEmpresa);
        if (!$cab || !preg_match('/^\d{4}-\d{2}$/', $periodoYm)) {
            throw new \DomainException('Datos no válidos.');
        }
        $cuenta = $this->repo->getCuentasPorIds($idEmpresa, [$idCuenta])[$idCuenta] ?? null;
        if (!$cuenta) {
            throw new \DomainException('La cuenta no existe.');
        }
        return $this->repo->getAsientosEjecucion($idEmpresa, (string) $cuenta['codigo'], $periodoYm, (string) $cab['alcance'], $cab['id_centro_costo'] ?? $cab['id_proyecto'] ?? null);
    }

    // ── Rubros ──────────────────────────────────────────────────────────────

    public function crearRubro(string $nombre, int $idEmpresa, int $idUsuario): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new \InvalidArgumentException('Indique el nombre del rubro.');
        }
        $existe = $this->repo->buscarRubroPorNombre($idEmpresa, $nombre);
        if ($existe) {
            return $existe;
        }
        $id = $this->repo->crearRubro($idEmpresa, $nombre, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'presupuestos_rubros', $id, null, ['nombre' => $nombre]);
        return $id;
    }

    public function renombrarRubro(int $id, string $nombre, int $idEmpresa, int $idUsuario): void
    {
        if (trim($nombre) === '') {
            throw new \InvalidArgumentException('Indique el nombre del rubro.');
        }
        $this->repo->renombrarRubro($id, $idEmpresa, $nombre, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'presupuestos_rubros', $id, null, ['nombre' => trim($nombre)]);
    }

    public function eliminarRubro(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->repo->eliminarRubro($id, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'presupuestos_rubros', $id, null, null);
    }

    // ── Excel: plantilla, importación y exportación ─────────────────────────

    /** Plantilla con una fila por cuenta de detalle (4/5/6) y una columna por mes del período. */
    public function plantillaExcel(int $idPresupuesto, int $idEmpresa): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $cab = $this->repo->findById($idPresupuesto, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        $meses = PresupuestoRules::listarMeses((string) $cab['periodo_desde'], (string) $cab['periodo_hasta']);
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $h = $libro->getActiveSheet();
        $h->setTitle('Presupuesto');
        $cabeceras = array_merge(['CUENTA', 'NOMBRE', 'RUBRO'], $meses);
        $h->fromArray([$cabeceras], null, 'A1');
        $h->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cabeceras)) . '1')->getFont()->setBold(true);
        $fila = 2;
        // Precarga con las líneas actuales (vigente o borrador) si las hay; si no, con las cuentas de detalle.
        $ver = $this->repo->getVersionBorrador($idPresupuesto, $idEmpresa) ?? $this->repo->getVersionVigente($idPresupuesto, $idEmpresa);
        $lineas = $ver ? $this->repo->getLineas((int) $ver['id'], $idEmpresa) : [];
        if ($lineas) {
            foreach ($lineas as $l) {
                $h->fromArray([array_merge([$l['cuenta_codigo'], $l['cuenta_nombre'], $l['rubro_nombre'] ?? ''],
                    array_map(fn($ym) => (float) ($l['valores'][$ym] ?? 0), $meses))], null, 'A' . $fila++);
            }
        } else {
            foreach ($this->repo->buscarCuentas($idEmpresa, '', 50) as $c) {
                if (empty($c['es_grupo']) || $c['es_grupo'] === 'f' || $c['es_grupo'] === false) {
                    $h->fromArray([array_merge([$c['codigo'], $c['nombre'], ''], array_fill(0, count($meses), 0))], null, 'A' . $fila++);
                }
            }
        }
        $h->getStyle('A2:A' . max(2, $fila))->getNumberFormat()->setFormatCode('@');
        foreach (['A' => 16, 'B' => 40, 'C' => 22] as $col => $w) {
            $h->getColumnDimension($col)->setWidth($w);
        }
        $inst = $libro->createSheet();
        $inst->setTitle('Instrucciones');
        $inst->fromArray([
            ['CARGA DE PRESUPUESTO'],
            ['- Una fila por cuenta contable. CUENTA es el código exacto del plan de cuentas (ingresos 4, costos y gastos 5 y 6).'],
            ['- NOMBRE es solo de referencia; se toma el del plan de cuentas.'],
            ['- RUBRO es opcional: un nombre para agrupar (Guardianía, Servicios básicos…). Si no existe, se crea.'],
            ['- Una columna por mes del período, con el monto presupuestado (sin signo, sin separador de miles).'],
            ['- Al cargar se REEMPLAZAN todas las líneas de la versión en borrador por las del archivo.'],
            ['- No cambie los encabezados ni agregue columnas.'],
        ], null, 'A1');
        $inst->getColumnDimension('A')->setWidth(110);
        $libro->setActiveSheetIndex(0);
        return $libro;
    }

    /**
     * Lee el Excel de la plantilla y devuelve las líneas listas para la grilla (sin grabar) más los
     * errores por fila. El usuario las ve en la grilla y las guarda si está conforme.
     */
    public function leerExcel(string $ruta, int $idPresupuesto, int $idEmpresa): array
    {
        $cab = $this->repo->findById($idPresupuesto, $idEmpresa);
        if (!$cab) {
            throw new \DomainException('El presupuesto no existe.');
        }
        $meses = PresupuestoRules::listarMeses((string) $cab['periodo_desde'], (string) $cab['periodo_hasta']);
        try {
            $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
        } catch (\Throwable $e) {
            throw new \RuntimeException('El archivo no es un Excel válido o está dañado.');
        }
        $h = $libro->getSheetByName('Presupuesto') ?? $libro->getSheet(0);
        $datos = $h->toArray(null, true, false, false);
        $cabecera = array_map(fn($v) => strtoupper(trim((string) $v)), $datos[0] ?? []);
        $esperada = array_merge(['CUENTA', 'NOMBRE', 'RUBRO'], $meses);
        if (array_slice($cabecera, 0, count($esperada)) !== array_map('strtoupper', $esperada)) {
            throw new \RuntimeException('Los encabezados no coinciden con la plantilla de este presupuesto. Se esperaba: ' . implode(' | ', $esperada) . '.');
        }
        $rubros = [];
        foreach ($this->repo->getRubros($idEmpresa) as $r) {
            $rubros[mb_strtolower((string) $r['nombre'])] = (int) $r['id'];
        }
        $lineas = [];
        $errores = [];
        $vistas = [];
        foreach (array_slice($datos, 1) as $i => $fila) {
            $nFila = $i + 2;
            $codigo = trim((string) ($fila[0] ?? ''));
            if ($codigo === '' && trim(implode('', array_map(fn($v) => (string) $v, $fila))) === '') {
                continue;
            }
            $cuenta = $codigo !== '' ? $this->repo->getCuentaPorCodigo($idEmpresa, $codigo) : null;
            if (!$cuenta) {
                $errores[] = "Fila {$nFila}: la cuenta «{$codigo}» no existe en el plan de cuentas.";
                continue;
            }
            if (!preg_match('/^[456]/', (string) $cuenta['codigo'])) {
                $errores[] = "Fila {$nFila}: la cuenta {$cuenta['codigo']} no es de ingresos, costos ni gastos.";
                continue;
            }
            if (isset($vistas[(int) $cuenta['id']])) {
                $errores[] = "Fila {$nFila}: la cuenta {$cuenta['codigo']} está repetida.";
                continue;
            }
            $vistas[(int) $cuenta['id']] = true;
            $nombreRubro = trim((string) ($fila[2] ?? ''));
            $valores = [];
            foreach ($meses as $k => $ym) {
                $v = str_replace([',', '$', ' '], '', (string) ($fila[3 + $k] ?? '0'));
                if ($v !== '' && !is_numeric($v)) {
                    $errores[] = "Fila {$nFila}: el valor de {$ym} («{$v}») no es un número.";
                    $v = '0';
                }
                $valores[$ym] = round(max(0.0, (float) $v), 2);
            }
            $lineas[] = ['id' => 0, 'id_cuenta' => (int) $cuenta['id'], 'cuenta_codigo' => $cuenta['codigo'], 'cuenta_nombre' => $cuenta['nombre'],
                         'id_rubro' => $nombreRubro !== '' ? ($rubros[mb_strtolower($nombreRubro)] ?? null) : null,
                         'rubro_nombre' => $nombreRubro ?: null, 'rubro_nuevo' => $nombreRubro !== '' && !isset($rubros[mb_strtolower($nombreRubro)]),
                         'valores' => $valores, 'es_ingreso' => str_starts_with((string) $cuenta['codigo'], '4')];
        }
        return ['lineas' => $lineas, 'errores' => $errores];
    }

    /** Libro con la grilla de la versión y, si hay versión aprobada, la hoja de ejecución. */
    public function excelPresupuesto(int $idPresupuesto, int $idEmpresa, ?int $idVersion, string $nombreEmpresa): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $det = $this->getDetalle($idPresupuesto, $idEmpresa, $idVersion);
        $cab = $det['cabecera'];
        $meses = $cab['meses'];
        $fmt = fn($ym) => self::mesCorto($ym);

        $filas = [];
        foreach ($det['lineas'] as $l) {
            $vals = array_map(fn($ym) => (float) ($l['valores'][$ym] ?? 0), $meses);
            $filas[] = array_merge([$l['cuenta_codigo'], $l['cuenta_nombre'], $l['rubro_nombre'] ?? ''], $vals, [array_sum($vals)]);
        }
        $reportService = new \App\Services\ReportService();
        $libro = $reportService->construirSpreadsheet(
            array_merge(['Cuenta', 'Nombre', 'Rubro'], array_map($fmt, $meses), ['Total']),
            $filas,
            'Presupuesto',
            $nombreEmpresa,
            ['Presupuesto' => (string) $cab['nombre'], 'Versión' => $det['version'] ? (string) $det['version']['nombre'] . ' (' . $det['version']['estado'] . ')' : '—',
             'Período' => self::mesCorto($meses[0]) . ' a ' . self::mesCorto(end($meses)), 'Alcance' => self::alcanceTexto($cab)]
        );

        try {
            $ej = $this->ejecucion($idPresupuesto, $idEmpresa, $det['version'] && $det['version']['estado'] !== 'borrador' ? (int) $det['version']['id'] : null);
            $h = $libro->createSheet();
            $h->setTitle('Ejecución');
            $cabs = ['Rubro', 'Cuenta', 'Nombre', 'Presupuesto', 'Ejecutado', 'Diferencia', '% Ejec.'];
            $h->fromArray([$cabs], null, 'A1');
            $h->getStyle('A1:G1')->getFont()->setBold(true);
            $fila = 2;
            foreach ($ej['filas'] as $f) {
                $h->fromArray([[$f['rubro'], $f['cuenta_codigo'], $f['cuenta_nombre'], $f['presupuesto'], $f['ejecutado'], $f['diferencia'], $f['pct']]], null, 'A' . $fila++);
            }
            foreach (['ingresos' => 'TOTAL INGRESOS', 'gastos' => 'TOTAL COSTOS Y GASTOS'] as $k => $et) {
                $t = $ej['totales'][$k];
                $h->fromArray([[$et, '', '', $t['presupuesto'], $t['ejecutado'], $t['diferencia'], $t['pct']]], null, 'A' . $fila);
                $h->getStyle('A' . $fila . ':G' . $fila)->getFont()->setBold(true);
                $fila++;
            }
            $h->getStyle('D2:F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
            foreach (range('A', 'G') as $c) {
                $h->getColumnDimension($c)->setAutoSize(true);
            }
        } catch (\DomainException $e) {
            // Sin versión aprobada no hay ejecución.
        }
        $libro->setActiveSheetIndex(0);
        return $libro;
    }

    // ── Utilidades ──────────────────────────────────────────────────────────

    public static function mesCorto(string $ym): string
    {
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return preg_match('/^(\d{4})-(\d{2})$/', $ym, $x) ? $m[(int) $x[2] - 1] . '-' . $x[1] : $ym;
    }

    public static function alcanceTexto(array $cab): string
    {
        return match ($cab['alcance'] ?? 'empresa') {
            'centro_costo' => 'Centro de costo: ' . ($cab['centro_costo_nombre'] ?? ''),
            'proyecto'     => 'Proyecto: ' . ($cab['proyecto_nombre'] ?? ''),
            default        => 'Toda la empresa',
        };
    }
}
