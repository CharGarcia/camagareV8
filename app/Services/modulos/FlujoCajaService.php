<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FlujoCajaRepository;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\RolPagoRepository;
use App\repositories\modulos\ControlBancarioRepository;

/**
 * Orquesta el Flujo de Caja: saldo y movimientos reales (Fase 1, desde
 * FlujoCajaRepository, consolidando todas las cuentas de caja/banco) +
 * proyección con datos ya existentes en el sistema (Fase 2): CXC/CXP por
 * vencer, roles de pago programados y cheques posfechados. No inventa datos
 * nuevos: cada fuente ya existe en su módulo, este service solo las suma en
 * una sola línea de tiempo por período (día/semana/mes).
 */
class FlujoCajaService
{
    public function __construct(
        private FlujoCajaRepository $repo,
        private CuentasPorCobrarRepository $cxcRepo,
        private CuentasPorPagarRepository $cxpRepo,
        private RolPagoRepository $rolRepo,
        private ControlBancarioRepository $bancarioRepo
    ) {
    }

    /**
     * @return array{
     *   cuentas_configuradas:bool,
     *   saldo_inicial:float,
     *   saldo_actual:float,
     *   periodos:array,
     * }
     */
    public function getLineaTiempo(int $idEmpresa, string $desde, string $hasta, string $agrupacion): array
    {
        $agrupacion = in_array($agrupacion, ['dia', 'semana', 'mes'], true) ? $agrupacion : 'dia';
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $hoy = date('Y-m-d');

        $cuentas = $this->repo->getCuentasCaja($idEmpresa);
        $idsCuentaContable = array_values(array_unique(array_map('intval', array_column($cuentas, 'id_cuenta_contable'))));
        $idsFormaPago = array_column($cuentas, 'id');

        if (empty($cuentas)) {
            return [
                'cuentas_configuradas' => false,
                'saldo_inicial' => 0.0,
                'saldo_actual' => 0.0,
                'periodos' => [],
            ];
        }

        $diaAnterior = date('Y-m-d', strtotime($desde . ' -1 day'));
        $saldoInicial = $this->repo->getSaldoConsolidadoAFecha($idEmpresa, $idsCuentaContable, $idsFormaPago, $diaAnterior);
        $saldoActual = $this->repo->getSaldoConsolidadoAFecha($idEmpresa, $idsCuentaContable, $idsFormaPago, $hoy);

        // ── Histórico real: hasta hoy (o hasta $hasta si todo el rango ya pasó) ──
        $finHistorico = min($hasta, $hoy);
        $filas = []; // periodo => ['entradas'=>, 'salidas'=>, 'real'=>bool]
        if ($desde <= $finHistorico) {
            foreach ($this->repo->getMovimientosPorPeriodo($idEmpresa, $idsCuentaContable, $desde, $finHistorico, $agrupacion) as $m) {
                $filas[$m['periodo']] = ['entradas' => (float) $m['entradas'], 'salidas' => (float) $m['salidas'], 'real' => true];
            }
        }

        // ── Proyección: desde mañana (o desde $desde si todo el rango es futuro) ──
        $inicioProyeccion = date('Y-m-d', strtotime($hoy . ' +1 day'));
        if ($hasta >= $inicioProyeccion) {
            $desdeProy = max($desde, $inicioProyeccion);
            foreach ($this->buildProyeccion($idEmpresa, $desdeProy, $hasta, $agrupacion) as $periodo => $mov) {
                $filas[$periodo] = [
                    'entradas' => ($filas[$periodo]['entradas'] ?? 0) + $mov['entradas'],
                    'salidas' => ($filas[$periodo]['salidas'] ?? 0) + $mov['salidas'],
                    'real' => false,
                ];
            }
        }

        ksort($filas);

        $saldoCorrido = $saldoInicial;
        $periodos = [];
        foreach ($filas as $periodo => $f) {
            $saldoCorrido += $f['entradas'] - $f['salidas'];
            $periodos[] = [
                'periodo' => $periodo,
                'real' => $f['real'],
                'entradas' => round($f['entradas'], 2),
                'salidas' => round($f['salidas'], 2),
                'saldo' => round($saldoCorrido, 2),
            ];
        }

        return [
            'cuentas_configuradas' => true,
            'saldo_inicial' => round($saldoInicial, 2),
            'saldo_actual' => round($saldoActual, 2),
            'periodos' => $periodos,
        ];
    }

    /**
     * Suma, por período, las entradas/salidas esperadas: CXC y CXP por vencer
     * (facturas + saldos iniciales), roles de pago programados y cheques
     * posfechados. Estas fuentes no se solapan entre sí: los cheques posfechados
     * corresponden a documentos YA registrados (ingreso/egreso con asiento), y
     * las CXC/CXP pendientes son, por definición, facturas SIN cobro/pago
     * registrado todavía.
     *
     * @return array<string,array{entradas:float,salidas:float}>
     */
    private function buildProyeccion(int $idEmpresa, string $desde, string $hasta, string $agrupacion): array
    {
        $mov = [];
        $add = function (string $periodo, float $entradas, float $salidas) use (&$mov): void {
            if (!isset($mov[$periodo])) {
                $mov[$periodo] = ['entradas' => 0.0, 'salidas' => 0.0];
            }
            $mov[$periodo]['entradas'] += $entradas;
            $mov[$periodo]['salidas'] += $salidas;
        };
        $enRango = fn (?string $fecha): bool => $fecha !== null && $fecha !== '' && $fecha >= $desde && $fecha <= $hasta;

        // CXC por vencer (facturas)
        foreach ($this->cxcRepo->getListado($idEmpresa, ['estado' => 'PENDIENTES']) as $f) {
            $fv = substr((string) ($f['fecha_vencimiento'] ?? ''), 0, 10);
            if (!$enRango($fv)) continue;
            $add($this->periodoDe($fv, $agrupacion), (float) $f['saldo'], 0.0);
        }
        // CXC por vencer (saldos iniciales)
        foreach ($this->cxcRepo->getSaldosInicialesCxc($idEmpresa, ['estado' => 'TODOS']) as $s) {
            $pend = (float) ($s['saldo_pendiente'] ?? 0);
            $fv = substr((string) ($s['fecha_vencimiento'] ?? ''), 0, 10);
            if ($pend <= 0 || !$enRango($fv)) continue;
            $add($this->periodoDe($fv, $agrupacion), $pend, 0.0);
        }

        // CXP por vencer (compras/liquidaciones/importaciones)
        foreach ($this->cxpRepo->getListado($idEmpresa, ['estado' => 'PENDIENTES']) as $f) {
            $fv = substr((string) ($f['fecha_vencimiento'] ?? ''), 0, 10);
            if (!$enRango($fv)) continue;
            $add($this->periodoDe($fv, $agrupacion), 0.0, (float) $f['saldo']);
        }
        // CXP por vencer (saldos iniciales)
        foreach ($this->cxpRepo->getSaldosInicialesCxp($idEmpresa, ['estado' => 'TODOS']) as $s) {
            $pend = (float) ($s['saldo_pendiente'] ?? 0);
            $fv = substr((string) ($s['fecha_vencimiento'] ?? ''), 0, 10);
            if ($pend <= 0 || !$enRango($fv)) continue;
            $add($this->periodoDe($fv, $agrupacion), 0.0, $pend);
        }

        // Roles de pago programados (nómina no pagada aún, con fecha de pago futura)
        foreach ($this->rolRepo->getRolesProgramados($idEmpresa, $desde, $hasta) as $r) {
            $fp = substr((string) $r['fecha_pago'], 0, 10);
            $add($this->periodoDe($fp, $agrupacion), 0.0, (float) $r['total_neto']);
        }

        // Cheques posfechados (recibidos → entrada futura; emitidos → salida futura)
        foreach ($this->bancarioRepo->getChequesPosfechados($idEmpresa, null, 'RECIBIDO') as $c) {
            $fc = substr((string) ($c['fecha_cheque'] ?? ''), 0, 10);
            if (!$enRango($fc)) continue;
            $add($this->periodoDe($fc, $agrupacion), (float) $c['debe'], 0.0);
        }
        foreach ($this->bancarioRepo->getChequesPosfechados($idEmpresa, null, 'EMITIDO') as $c) {
            $fc = substr((string) ($c['fecha_cheque'] ?? ''), 0, 10);
            if (!$enRango($fc)) continue;
            $add($this->periodoDe($fc, $agrupacion), 0.0, (float) $c['haber']);
        }

        return $mov;
    }

    /** Debe coincidir con DATE_TRUNC('week', ...) de Postgres usado en getMovimientosPorPeriodo (semana ISO, inicia lunes). */
    private function periodoDe(string $fecha, string $agrupacion): string
    {
        if ($agrupacion === 'mes') {
            return substr($fecha, 0, 7);
        }
        if ($agrupacion === 'semana') {
            $ts = strtotime($fecha);
            $diaSemana = (int) date('N', $ts); // 1=lunes .. 7=domingo
            return date('Y-m-d', strtotime('-' . ($diaSemana - 1) . ' days', $ts));
        }
        return $fecha;
    }
}
