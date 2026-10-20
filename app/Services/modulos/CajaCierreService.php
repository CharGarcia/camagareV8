<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CajaCierreRepository;
use App\repositories\modulos\CajaMovimientoRepository;
use App\repositories\modulos\ReporteResumenDiarioRepository;
use App\Rules\modulos\CajaCierreRules;
use App\Rules\modulos\ReporteResumenDiarioRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * Apertura y cierre de caja por día del Resumen Diario (opción «Caja: Cierre de caja»).
 *
 * Una caja por empresa + día + alcance: un usuario (cuenta lo que ese usuario registró)
 * o toda la empresa. Al ABRIR se fija el saldo inicial de cada forma de pago (propuesto:
 * lo contado en el último cierre del mismo alcance más lo que se movió en los días sin
 * cierre; sin cierres previos, para toda la empresa el saldo del resumen y para un
 * usuario cero). Al CERRAR, por cada forma: saldo inicial + ingresos − egresos ±
 * traslados = esperado; el usuario escribe lo contado y queda la diferencia.
 *
 * No toca los saldos de apertura ni los traslados, no genera asientos y no bloquea
 * Ingresos/Egresos: si después del cierre entra un movimiento de ese día, el resumen lo
 * avisa. Cada escritura va en transacción, bajo candado del día y alcance, con su
 * registro en log_sistema.
 */
class CajaCierreService
{
    public const SIN_FORMA = 'Sin forma de pago registrada';

    private CajaCierreRepository $repo;
    private ReporteResumenDiarioRepository $resumen;
    private CajaCierreRules $rules;
    private LogSistemaService $log;

    public function __construct(?CajaCierreRepository $repo = null)
    {
        $this->repo    = $repo ?? new CajaCierreRepository();
        $this->resumen = new ReporteResumenDiarioRepository();
        $this->rules   = new CajaCierreRules();
        $this->log     = new LogSistemaService();
    }

    public function disponible(): bool
    {
        return $this->repo->tablasDisponibles();
    }

    /** Usuarios para el selector «Usuario»: los asignados a la empresa y el que consulta. */
    public function usuarios(int $idEmpresa, int $idUsuarioSesion): array
    {
        return $this->repo->getUsuariosEmpresa($idEmpresa, [$idUsuarioSesion]);
    }

    /** ¿El usuario elegido en el selector es de la empresa? (no se confía en el navegador). */
    public function usuarioEsDeEmpresa(int $idEmpresa, int $idUsuario, int $idUsuarioSesion): bool
    {
        foreach ($this->usuarios($idEmpresa, $idUsuarioSesion) as $u) {
            if ((int) $u['id'] === $idUsuario) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ingresos, egresos y traslados del día por forma de pago, a partir de las filas que
     * devuelve el repositorio del resumen (una por comprobante y forma de pago).
     * La forma 0 son los ingresos sin forma de pago registrada.
     *
     * @return array<int, array{forma:string, ingresos:float, egresos:float, traslados:float}>
     */
    public static function movimientosPorForma(array $ingresos, array $egresos, array $traslados): array
    {
        $m = [];
        $fila = static function (int $id, ?string $nombre) use (&$m): void {
            $m[$id] ??= ['forma' => (string) ($nombre ?? '') !== '' ? (string) $nombre : self::SIN_FORMA,
                         'ingresos' => 0.0, 'egresos' => 0.0, 'traslados' => 0.0];
        };
        foreach ([['ingresos', $ingresos], ['egresos', $egresos]] as [$lado, $filas]) {
            foreach ($filas as $r) {
                $id = (int) $r['id_forma'];
                $fila($id, $r['forma'] ?? null);
                $m[$id][$lado] += (float) $r['valor'];
            }
        }
        foreach ($traslados as $t) {
            $fila((int) $t['id_forma_origen'], $t['forma_origen'] ?? null);
            $fila((int) $t['id_forma_destino'], $t['forma_destino'] ?? null);
            $m[(int) $t['id_forma_origen']]['traslados']  -= (float) $t['valor'];
            $m[(int) $t['id_forma_destino']]['traslados'] += (float) $t['valor'];
        }
        return $m;
    }

    /** Movimientos del día del alcance, con las mismas reglas que el resumen (producción, sin anulados). */
    public function movimientosDelDia(int $idEmpresa, string $fecha, ?int $idUsuario): array
    {
        return self::movimientosPorForma(
            $this->resumen->getIngresos($idEmpresa, $fecha, $idUsuario),
            $this->resumen->getEgresos($idEmpresa, $fecha, $idUsuario),
            $this->resumen->getTraslados($idEmpresa, $fecha, $idUsuario)
        );
    }

    /** id => nombre de las formas de pago activas de la empresa. */
    private function nombresFormas(int $idEmpresa): array
    {
        $out = [];
        foreach ((new CajaMovimientoRepository())->getFormasPago($idEmpresa) as $f) {
            $out[(int) $f['id']] = (string) $f['nombre'];
        }
        return $out;
    }

    /**
     * Saldo inicial que se propone al abrir, por forma de pago (id => [forma, saldo]):
     *  - Con un cierre anterior del mismo alcance: lo contado en él más el neto de los días
     *    entre ese cierre y hoy (si hubo días sin cerrar).
     *  - Sin cierres, toda la empresa: el saldo inicial del resumen (apertura + movimientos).
     *  - Sin cierres, un usuario: nada (cero); el usuario escribe su fondo.
     */
    public function saldosPropuestos(int $idEmpresa, string $fecha, ?int $idUsuario): array
    {
        $ult = $this->repo->getUltimoCerradoAntes($idEmpresa, $fecha, $idUsuario);
        if (!$ult) {
            if ($idUsuario !== null) {
                return [];
            }
            return array_map(static fn (array $s): array => ['forma' => $s['nombre'], 'saldo' => $s['saldo']],
                $this->resumen->getSaldosIniciales($idEmpresa, $fecha));
        }
        $nombres = $this->nombresFormas($idEmpresa);
        $out = [];
        foreach ($this->repo->getDetalle((int) $ult['id'], $idEmpresa) as $d) {
            if ($d['id_forma_pago'] === null) {
                continue; // lo cobrado sin forma de pago no tiene saldo
            }
            $out[(int) $d['id_forma_pago']] = ['forma' => (string) $d['forma'], 'saldo' => (float) ($d['contado'] ?? 0)];
        }
        foreach ($this->repo->getNetoPorFormaEntre($idEmpresa, (string) $ult['fecha'], $fecha, $idUsuario) as $id => $neto) {
            $out[$id] ??= ['forma' => $nombres[$id] ?? "Forma #{$id}", 'saldo' => 0.0];
            $out[$id]['saldo'] += $neto;
        }
        foreach ($out as $id => $s) {
            $out[$id]['saldo'] = round($s['saldo'], 2);
            if ($out[$id]['saldo'] == 0.0) {
                unset($out[$id]);
            }
        }
        return $out;
    }

    /**
     * Estado de la caja del día y alcance:
     *  - cierre:    la fila de caja_cierres_diarios o null (sin abrir).
     *  - detalle:   id_forma => fila de detalle.
     *  - iniciales: id_forma => [forma, saldo] (los guardados al abrir o, sin abrir, los propuestos).
     */
    public function estado(int $idEmpresa, string $fecha, ?int $idUsuario): array
    {
        $cierre = $this->repo->getCierre($idEmpresa, $fecha, $idUsuario);
        if (!$cierre) {
            return ['cierre' => null, 'detalle' => [], 'iniciales' => $this->saldosPropuestos($idEmpresa, $fecha, $idUsuario)];
        }
        $detalle = $iniciales = [];
        foreach ($this->repo->getDetalle((int) $cierre['id'], $idEmpresa) as $d) {
            $id = (int) ($d['id_forma_pago'] ?? 0);
            $detalle[$id]   = $d;
            $iniciales[$id] = ['forma' => (string) $d['forma'], 'saldo' => (float) $d['saldo_inicial']];
        }
        return ['cierre' => $cierre, 'detalle' => $detalle, 'iniciales' => $iniciales];
    }

    /**
     * Filas de la caja por forma de pago. Con caja cerrada: lo guardado al cerrar (foto del
     * arqueo). Si no: saldo inicial + movimientos del día = esperado, para las formas con
     * movimiento o con saldo inicial.
     *
     * @return list<array{id_forma:int, forma:string, saldo_inicial:float, ingresos:float, egresos:float,
     *                    traslados:float, esperado:float, contado:?float, diferencia:?float}>
     */
    public static function filas(array $movs, array $estado): array
    {
        $cerrada = ($estado['cierre']['estado'] ?? '') === 'cerrada';
        $filas = [];
        if ($cerrada) {
            foreach ($estado['detalle'] as $id => $d) {
                $filas[] = [
                    'id_forma' => $id, 'forma' => (string) $d['forma'],
                    'saldo_inicial' => round((float) $d['saldo_inicial'], 2),
                    'ingresos' => round((float) $d['ingresos'], 2), 'egresos' => round((float) $d['egresos'], 2),
                    'traslados' => round((float) $d['traslados'], 2), 'esperado' => round((float) $d['esperado'], 2),
                    'contado' => round((float) $d['contado'], 2), 'diferencia' => round((float) $d['diferencia'], 2),
                ];
            }
        } else {
            $ids = array_unique(array_merge(array_keys($movs), array_keys(array_filter(
                $estado['iniciales'], static fn (array $s): bool => round((float) $s['saldo'], 2) != 0.0
            ))));
            foreach ($ids as $id) {
                $m   = $movs[$id] ?? ['forma' => '', 'ingresos' => 0.0, 'egresos' => 0.0, 'traslados' => 0.0];
                $ini = round((float) ($estado['iniciales'][$id]['saldo'] ?? 0), 2);
                $f = [
                    'id_forma' => (int) $id,
                    'forma' => $m['forma'] !== '' ? $m['forma'] : (string) ($estado['iniciales'][$id]['forma'] ?? ''),
                    'saldo_inicial' => $ini,
                    'ingresos' => round($m['ingresos'], 2), 'egresos' => round($m['egresos'], 2), 'traslados' => round($m['traslados'], 2),
                    'contado' => null, 'diferencia' => null,
                ];
                $f['esperado'] = round($ini + $f['ingresos'] - $f['egresos'] + $f['traslados'], 2);
                $filas[] = $f;
            }
        }
        usort($filas, static fn (array $a, array $b): int => strcmp($a['forma'], $b['forma']));
        return $filas;
    }

    /** Para el modal de apertura: formas activas (y las que traen saldo) con el saldo propuesto. */
    public function formasParaAbrir(int $idEmpresa, string $fecha, ?int $idUsuario): array
    {
        $fecha = (new ReporteResumenDiarioRules())->validarFecha($fecha);
        $prop = $this->saldosPropuestos($idEmpresa, $fecha, $idUsuario);
        $out  = [];
        foreach ($this->nombresFormas($idEmpresa) as $id => $nombre) {
            $out[$id] = ['id_forma_pago' => $id, 'forma' => $nombre, 'saldo' => (float) ($prop[$id]['saldo'] ?? 0)];
        }
        foreach ($prop as $id => $p) {
            $out[$id] ??= ['id_forma_pago' => $id, 'forma' => $p['forma'], 'saldo' => (float) $p['saldo']];
        }
        return array_values($out);
    }

    /**
     * Abre la caja del día y alcance con los saldos iniciales del formulario. Con la misma
     * clave de formulario ($token) un reintento devuelve la caja ya abierta (§8).
     *
     * @return array{id:int, ya_existia:bool}
     */
    public function abrir(int $idEmpresa, string $fecha, ?int $idUsuario, array $filas, string $obs, string $token, int $idUsuarioSesion): array
    {
        $fecha = (new ReporteResumenDiarioRules())->validarFecha($fecha);
        $guardado = new GuardadoUnicoService();
        $this->repo->beginTransaction();
        try {
            if ($previo = $guardado->previo($token, $idEmpresa, 'caja_cierres_diarios')) {
                $this->repo->rollBack();
                return ['id' => (int) $previo['id_registro'], 'ya_existia' => true];
            }
            $this->repo->lockAlcance($idEmpresa, $fecha, $idUsuario);
            if ($this->repo->getCierre($idEmpresa, $fecha, $idUsuario)) {
                throw new \InvalidArgumentException('La caja de este día ya fue abierta.');
            }
            $formas = [];
            foreach ($this->formasParaAbrir($idEmpresa, $fecha, $idUsuario) as $f) {
                $formas[$f['id_forma_pago']] = $f['forma'];
            }
            $saldos = $this->rules->validarApertura($filas, $formas);
            $obs    = $this->rules->observaciones($obs, 300);
            $total  = round(array_sum($saldos), 2);

            $id = $this->repo->insertarCierre($idEmpresa, $fecha, $idUsuario, $total, $obs, $idUsuarioSesion);
            $detalle = [];
            foreach ($saldos as $idForma => $saldo) {
                if ($saldo == 0.0) {
                    continue;
                }
                $d = ['id_forma_pago' => $idForma, 'forma' => $formas[$idForma], 'saldo_inicial' => $saldo];
                $this->repo->insertarDetalle($id, $idEmpresa, $d, $idUsuarioSesion);
                $detalle[] = $d;
            }
            $this->log->registrar($idUsuarioSesion, $idEmpresa, 'ABRIR_CAJA_DIARIA', 'caja_cierres_diarios', $id, null, [
                'fecha' => $fecha, 'id_usuario' => $idUsuario, 'total_inicial' => $total, 'observaciones' => $obs, 'detalle' => $detalle,
            ]);
            $guardado->registrar($token, $idEmpresa, 'caja_cierres_diarios', $id, $fecha, $idUsuarioSesion);
            $this->repo->commit();
            return ['id' => $id, 'ya_existia' => false];
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Para el modal de cierre: cada forma de la caja con su esperado. La caja debe estar abierta. */
    public function formasParaCerrar(int $idEmpresa, string $fecha, ?int $idUsuario): array
    {
        $fecha = (new ReporteResumenDiarioRules())->validarFecha($fecha);
        $estado = $this->estado($idEmpresa, $fecha, $idUsuario);
        $this->exigirAbierta($estado['cierre']);
        return self::filas($this->movimientosDelDia($idEmpresa, $fecha, $idUsuario), $estado);
    }

    private function exigirAbierta(?array $cierre): void
    {
        if (!$cierre) {
            throw new \InvalidArgumentException('Primero abra la caja de este día.');
        }
        if ($cierre['estado'] !== 'abierta') {
            throw new \InvalidArgumentException('La caja de este día ya está cerrada.');
        }
    }

    /**
     * Cierra la caja: el esperado se recalcula aquí, bajo candado (no se usa el que mostró
     * la pantalla), se guarda la foto por forma de pago con lo contado y la diferencia.
     *
     * @return array{esperado:float, contado:float, diferencia:float}
     */
    public function cerrar(int $idEmpresa, string $fecha, ?int $idUsuario, array $contados, string $obs, int $idUsuarioSesion): array
    {
        $fecha = (new ReporteResumenDiarioRules())->validarFecha($fecha);
        $this->repo->beginTransaction();
        try {
            $this->repo->lockAlcance($idEmpresa, $fecha, $idUsuario);
            $estado = $this->estado($idEmpresa, $fecha, $idUsuario);
            $this->exigirAbierta($estado['cierre']);
            $id    = (int) $estado['cierre']['id'];
            $filas = self::filas($this->movimientosDelDia($idEmpresa, $fecha, $idUsuario), $estado);
            if (!$filas) {
                throw new \InvalidArgumentException('La caja no tiene saldos ni movimientos que cerrar.');
            }
            $formas = array_column($filas, 'forma', 'id_forma');
            $cont   = $this->rules->validarCierre($contados, $formas);
            $obs    = $this->rules->observaciones($obs, 500);

            $this->repo->eliminarDetalle($id, $idEmpresa, $idUsuarioSesion);
            $tot = ['esperado' => 0.0, 'contado' => 0.0, 'diferencia' => 0.0];
            foreach ($filas as $i => $f) {
                $f['contado']    = $cont[$f['id_forma']];
                $f['diferencia'] = round($f['contado'] - $f['esperado'], 2);
                $f['id_forma_pago'] = $f['id_forma'];
                $this->repo->insertarDetalle($id, $idEmpresa, $f, $idUsuarioSesion);
                foreach ($tot as $k => $v) {
                    $tot[$k] = round($v + $f[$k], 2);
                }
                $filas[$i] = $f;
            }
            $this->repo->marcarCerrada($id, $idEmpresa, $tot, $obs, $idUsuarioSesion);
            $this->log->registrar($idUsuarioSesion, $idEmpresa, 'CERRAR_CAJA_DIARIA', 'caja_cierres_diarios', $id,
                ['estado' => 'abierta'], ['estado' => 'cerrada', 'observaciones' => $obs, 'totales' => $tot, 'detalle' => $filas]);
            $this->repo->commit();
            return $tot;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Vuelve a abrir una caja cerrada (para corregir): se borra el arqueo y se conservan los saldos iniciales. */
    public function reabrir(int $idEmpresa, string $fecha, ?int $idUsuario, string $motivo, int $idUsuarioSesion): void
    {
        $fecha = (new ReporteResumenDiarioRules())->validarFecha($fecha);
        $motivo = $this->rules->observaciones($motivo, 300);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Indique el motivo para reabrir la caja.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->lockAlcance($idEmpresa, $fecha, $idUsuario);
            $estado = $this->estado($idEmpresa, $fecha, $idUsuario);
            if (($estado['cierre']['estado'] ?? '') !== 'cerrada') {
                throw new \InvalidArgumentException('La caja de este día no está cerrada.');
            }
            $id = (int) $estado['cierre']['id'];
            $this->repo->marcarAbierta($id, $idEmpresa, $idUsuarioSesion);
            $this->log->registrar($idUsuarioSesion, $idEmpresa, 'REABRIR_CAJA_DIARIA', 'caja_cierres_diarios', $id,
                ['cierre' => $estado['cierre'], 'detalle' => array_values($estado['detalle'])],
                ['estado' => 'abierta', 'motivo' => $motivo]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }
}
