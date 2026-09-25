<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\TiposComprobanteCompra;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\EmpresaRepository;
use PDO;

class DashboardService
{
    private PDO $db;
    private EmpresaRepository $empresaRepo;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->empresaRepo = new EmpresaRepository();
    }

    /**
     * Retorna todos los datos del dashboard según filtros.
     * @param int    $anio      0 = año actual
     * @param int    $mes       1-12 = mes específico | -1 = todo el año | 0 = mes actual
     * @param int    $cantMeses 3, 6 o 12 — meses para el gráfico de tendencia
     * @param array  $alcanceCartera Alcance del usuario (§6) en la cartera, resuelto por
     *               el controller con los permisos de cada módulo: ['cxc' => filtros de
     *               AlcanceRegistros, 'cxp' => ['id_usuario_filtro' => ?int]]. Vacío = toda
     *               la empresa (la API móvil).
     */
    public function getDashboardData(
        int     $idEmpresa,
        string  $tipoAmbiente = '1',
        int     $anio         = 0,
        int     $mes          = 0,
        int     $cantMeses    = 6,
        ?string $rangoDesde   = null,
        ?string $rangoHasta   = null,
        int     $idUsuario    = 0,
        array   $alcanceCartera = []
    ): array {
        $cantMeses = in_array($cantMeses, [3, 6, 12, 24]) ? $cantMeses : 6;

        // Modo rango de fechas: si llegan desde/hasta válidos, mandan sobre año/mes.
        $modoRango = $this->fechaValida($rangoDesde) && $this->fechaValida($rangoHasta);
        if ($modoRango) {
            $desde = $rangoDesde;
            $hasta = $rangoHasta;
            if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }
            [$antDes, $antHas] = $this->rangoAnterior($desde, $hasta);
            $label = date('d/m/Y', strtotime($desde)) . ' – ' . date('d/m/Y', strtotime($hasta));
        } else {
            $anio  = $anio > 0 ? $anio : (int) date('Y');
            $mes   = ($mes >= -1 && $mes !== 0) ? $mes : (int) date('n');
            $desde = $this->fechaDesde($anio, $mes);
            $hasta = $this->fechaHasta($anio, $mes);
            [$antDes, $antHas] = $this->periodoAnterior($anio, $mes);
            $label = $mes === -1 ? "Año {$anio}" : $this->nombreMes($mes) . " {$anio}";
        }

        $corte      = $this->fechaCorteCartera($hasta);
        $alcanceCxc = $alcanceCartera['cxc'] ?? [];
        $alcanceCxp = $alcanceCartera['cxp'] ?? [];

        return [
            // Ventas
            'ventas_mes_actual'     => $this->sumVentas($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'ventas_mes_anterior'   => $this->sumVentas($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Compras
            'compras_mes_actual'    => $this->sumCompras($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'compras_mes_anterior'  => $this->sumCompras($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Ingresos
            'ingresos_mes_actual'   => $this->sumIngresos($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'ingresos_mes_anterior' => $this->sumIngresos($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Egresos
            'egresos_mes_actual'    => $this->sumEgresos($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'egresos_mes_anterior'  => $this->sumEgresos($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // Nómina (roles de pago del período)
            'nomina_mes_actual'     => $this->sumNomina($idEmpresa, $tipoAmbiente, $desde, $hasta),
            'nomina_mes_anterior'   => $this->sumNomina($idEmpresa, $tipoAmbiente, $antDes, $antHas),
            // CxC / CxP: saldo de cartera al corte, el mismo que muestran los módulos
            'cxc_total'             => $this->getCxcTotal($idEmpresa, $corte, $alcanceCxc),
            'cxp_total'             => $this->getCxpTotal($idEmpresa, $corte, $alcanceCxp),
            'cartera_corte'         => $corte,
            // Saldos de caja: bancos/efectivo (saldo real actual) y anticipos globales.
            // Estado puntual, NO filtrado por período. Las cuentas BANCO/CHEQUE que comparten
            // banco+número con otro establecimiento del mismo RUC (accesible al usuario) se
            // consolidan en una sola fila (ver consolidarFormasPorRuc()).
            'saldos_caja'           => $this->getSaldosCaja($idEmpresa, $idUsuario),
            // Tablas recientes
            'facturas_recientes'    => $this->getVentasRecientes($idEmpresa, 6, $tipoAmbiente),
            'compras_recientes'     => $this->getComprasRecientes($idEmpresa, 6, $tipoAmbiente),
            'ingresos_recientes'    => $this->getIngresosRecientes($idEmpresa, 5, $tipoAmbiente),
            'egresos_recientes'     => $this->getEgresosRecientes($idEmpresa, 5, $tipoAmbiente),
            // Vencidos
            'cxc_vencidas'          => $this->getCxcVencidas($idEmpresa, 5, $alcanceCxc),
            'cxp_vencidas'          => $this->getCxpVencidas($idEmpresa, 5, $alcanceCxp),
            // Gráficos
            'tendencia'             => $this->getTendenciaMensual($idEmpresa, $cantMeses, $tipoAmbiente),
            'top_productos'         => $this->getTopProductos($idEmpresa, $tipoAmbiente, $desde, $hasta, 5),
            'top_clientes'          => $this->getTopClientes($idEmpresa, $tipoAmbiente, $desde, $hasta, 5),
            'top_proveedores'       => $this->getTopProveedores($idEmpresa, $tipoAmbiente, $desde, $hasta, 5),
            'egresos_por_concepto'  => $this->getEgresosPorConcepto($idEmpresa, $tipoAmbiente, $desde, $hasta, 6),
            // Meta
            'anio'          => $modoRango ? (int) date('Y', strtotime($desde)) : $anio,
            'mes'           => $modoRango ? 0 : $mes,
            'cant_meses'    => $cantMeses,
            'modo_rango'    => $modoRango,
            'rango_desde'   => $desde,
            'rango_hasta'   => $hasta,
            'label_periodo' => $label,
        ];
    }

    // ── Helpers de fechas ─────────────────────────────────────────────────────

    private function fechaDesde(int $anio, int $mes): string
    {
        if ($mes === -1) return "{$anio}-01-01";
        return sprintf('%04d-%02d-01', $anio, $mes);
    }

    private function fechaHasta(int $anio, int $mes): string
    {
        if ($mes === -1) return "{$anio}-12-31";
        $ultimo = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        return sprintf('%04d-%02d-%02d', $anio, $mes, $ultimo);
    }

    private function periodoAnterior(int $anio, int $mes): array
    {
        if ($mes === -1) return [($anio - 1) . '-01-01', ($anio - 1) . '-12-31'];
        $m = $mes - 1;
        $a = $anio;
        if ($m < 1) { $m = 12; $a--; }
        return [$this->fechaDesde($a, $m), $this->fechaHasta($a, $m)];
    }

    /** Valida un string 'Y-m-d'. */
    private function fechaValida(?string $f): bool
    {
        if (!$f) return false;
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        return $d && $d->format('Y-m-d') === $f;
    }

    /** Ventana anterior de igual longitud, terminando el día antes de $desde. */
    private function rangoAnterior(string $desde, string $hasta): array
    {
        $d1 = new \DateTime($desde);
        $d2 = new \DateTime($hasta);
        $dias = (int) $d1->diff($d2)->days;             // longitud - 1
        $antHas = (clone $d1)->modify('-1 day');
        $antDes = (clone $antHas)->modify('-' . $dias . ' day');
        return [$antDes->format('Y-m-d'), $antHas->format('Y-m-d')];
    }

    private function nombreMes(int $mes): string
    {
        return ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes] ?? '';
    }

    /**
     * Filtro de ambiente equivalente a `COALESCE(col, '1') = $ta` (los documentos
     * legados sin tipo_ambiente cuentan como pruebas), pero escrito sobre la
     * columna desnuda para que el planificador pueda estimarlo.
     *
     * Con el COALESCE, PostgreSQL no tiene estadísticas de la expresión y supone
     * que sobrevive el 0,5 % de las filas. Con esa estimación cruzaba las facturas
     * contra los cobros agrupados con bucles anidados (facturas × grupos), y el
     * saldo de CxC tardaba minutos en una empresa con muchas ventas. Mismo
     * problema que resolvió AmbienteEmpresaTrait en Cuentas por Cobrar/Pagar.
     * $ta sale de la configuración de la empresa; solo se interpola si es un dígito.
     */
    private function condAmbiente(string $col, string $ta): string
    {
        if ($ta === '1') {
            return "({$col} = '1' OR {$col} IS NULL)";
        }
        return ctype_digit($ta) ? "{$col} = '{$ta}'" : 'FALSE';
    }

    /**
     * La factura cuenta como venta: solo AUTORIZADA, igual que el Reporte de Ventas y
     * Cuentas por Cobrar. Antes bastaba con no estar anulada, y el tablero sumaba
     * borradores, facturas pendientes de envío y rechazadas por el SRI.
     */
    private function condVentaValida(string $alias = ''): string
    {
        $p = $alias !== '' ? "{$alias}." : '';
        return "LOWER({$p}estado) IN ('autorizado', 'autorizada')";
    }

    /**
     * Valor neto de un comprobante de compra: las notas de crédito (04 y afines) RESTAN,
     * como en el Reporte de Compras. Antes se sumaban como una compra más y
     * la nota de crédito inflaba las compras en vez de reducirlas.
     */
    private function exprCompraNeta(string $alias = ''): string
    {
        $p  = $alias !== '' ? "{$alias}." : '';
        $nc = "'" . implode("','", TiposComprobanteCompra::NOTAS_CREDITO) . "'";
        return "CASE WHEN {$p}tipo_comprobante IN ({$nc}) THEN -{$p}importe_total ELSE {$p}importe_total END";
    }

    /** Compra vigente: fuera las anuladas y rechazadas (mismo criterio que Cuentas por Pagar). */
    private function condCompraVigente(string $alias = ''): string
    {
        $p = $alias !== '' ? "{$alias}." : '';
        return TiposComprobanteCompra::sqlCompraVigente("{$p}estado");
    }

    // ── Sumas de período ──────────────────────────────────────────────────────

    private function sumVentas(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(importe_total), 0)
             FROM ventas_cabecera
             WHERE id_empresa = ? AND eliminado = false AND {$this->condVentaValida()}
               AND {$this->condAmbiente('tipo_ambiente', $ta)}
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumCompras(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM({$this->exprCompraNeta()}), 0)
             FROM compras_cabecera
             WHERE id_empresa = ? AND eliminado = false AND {$this->condCompraVigente()}
               AND {$this->condAmbiente('tipo_ambiente', $ta)}
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumIngresos(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM ingresos_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND tipo_ambiente = ?
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $ta, $d, $h]);
        return (float) $st->fetchColumn();
    }

    private function sumEgresos(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM egresos_cabecera
             WHERE id_empresa = ? AND eliminado = false AND estado != 'anulado'
               AND tipo_ambiente = ?
               AND CAST(fecha_emision AS DATE) BETWEEN ? AND ?"
        );
        $st->execute([$e, $ta, $d, $h]);
        return (float) $st->fetchColumn();
    }

    /**
     * Nómina del período: total devengado (total_ingresos) de los roles de pago
     * del módulo Roles de Pago (rol_cabecera), filtrado por el ambiente de la
     * empresa. Se ubica el rol por su período (make_date(anio,mes,1)) y se
     * excluyen borradores y anulados.
     */
    private function sumNomina(int $e, string $ta, string $d, string $h): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(total_ingresos), 0)
             FROM rol_cabecera
             WHERE id_empresa = ? AND eliminado = false
               AND estado NOT IN ('anulado', 'borrador')
               AND {$this->condAmbiente('tipo_ambiente', $ta)}
               AND make_date(periodo_anio, periodo_mes, 1) BETWEEN ? AND ?"
        );
        $st->execute([$e, $d, $h]);
        return (float) $st->fetchColumn();
    }

    // ── CxC / CxP ────────────────────────────────────────────────────────────
    //
    // El saldo de cartera y los vencidos NO se calculan aquí: se piden a los
    // repositorios de los módulos Cuentas por Cobrar y Cuentas por Pagar con los
    // mismos filtros que esas pantallas usan al abrirse (estado PENDIENTES, todos
    // los tipos de documento, sin Fecha Desde y Fecha Hasta = corte). Así el
    // tablero y los módulos muestran exactamente el mismo saldo.
    //
    // Antes el tablero tenía su propia fórmula y se descuadraba: solo sumaba las
    // facturas EMITIDAS en el período (no la cartera acumulada), contaba facturas
    // en borrador o rechazadas, no sumaba las notas de débito ni los recibos de
    // venta, y en CxP dejaba fuera liquidaciones, importaciones, notas de venta y
    // demás comprobantes que generan deuda, además de los valores de terceros.

    /**
     * Fecha de corte de la cartera: el último día del período elegido o hoy, lo
     * que ocurra primero. En el mes en curso es hoy, igual que la Fecha Hasta con
     * la que abren Cuentas por Cobrar y Cuentas por Pagar.
     */
    private function fechaCorteCartera(string $hasta): string
    {
        $hoy = date('Y-m-d');
        return $hasta < $hoy ? $hasta : $hoy;
    }

    /** Filtros de Cuentas por Cobrar con los que abre la pantalla, más el alcance del usuario. */
    private function filtrosCxc(string $estado, string $corte, array $alcance): array
    {
        return array_merge([
            'estado'      => $estado,
            'tipo_doc'    => 'TODOS',
            'fecha_desde' => '',
            'fecha_hasta' => $corte,
        ], $alcance);
    }

    /** Filtros de Cuentas por Pagar con los que abre la pantalla, más registros propios. */
    private function filtrosCxp(string $estado, string $corte, array $alcance): array
    {
        return [
            'estado'            => $estado,
            'fecha_desde'       => '',
            'fecha_hasta'       => $corte,
            'id_usuario_filtro' => $alcance['id_usuario_filtro'] ?? null,
        ];
    }

    /** Saldo por cobrar al corte: la tarjeta «Saldo» de Cuentas por Cobrar. */
    private function getCxcTotal(int $e, string $corte, array $alcance): float
    {
        $stats = (new CuentasPorCobrarRepository())->getEstadisticas($e, $this->filtrosCxc('PENDIENTES', $corte, $alcance));
        return round((float) ($stats['total_saldo'] ?? 0), 2);
    }

    /** Saldo por pagar al corte: la tarjeta «Saldo» de Cuentas por Pagar. */
    private function getCxpTotal(int $e, string $corte, array $alcance): float
    {
        $stats = (new CuentasPorPagarRepository())->getEstadisticas($e, $this->filtrosCxp('PENDIENTES', $corte, $alcance));
        return round((float) ($stats['total_saldo'] ?? 0), 2);
    }

    // ── Saldos de caja: bancos/efectivo y anticipos ───────────────────────────

    /**
     * Saldos de bancos/efectivo (saldo real actual por forma de pago) y
     * anticipos globales (clientes y proveedores). Reutiliza la misma lógica
     * de cálculo del módulo de Ingresos (FormaPagoRepository::getSaldosActuales
     * y getSaldoAnticipo), pero los anticipos se agregan de forma global sin
     * depender de un tercero. Estado puntual: NO se filtra por período.
     *
     * Si alguna tabla de saldos iniciales aún no existe en el entorno
     * (migración no aplicada), degrada con elegancia y no tumba el dashboard.
     */
    private function getSaldosCaja(int $e, int $idUsuario = 0): array
    {
        try {
            $resultado = $this->calcularSaldosCaja($e);
            if ($idUsuario > 0) {
                $resultado['formas'] = $this->consolidarFormasPorRuc($e, $idUsuario, $resultado['formas']);
            }
            return $resultado;
        } catch (\Throwable $ex) {
            return [
                'formas'                => [],
                'anticipos_clientes'    => 0.0,
                'anticipos_proveedores' => 0.0,
                'tiene_datos'           => false,
            ];
        }
    }

    /**
     * Une, dentro de las formas BANCO/CHEQUE (las únicas con cuenta real: banco + número), las
     * que representan la MISMA cuenta en otro establecimiento del mismo RUC accesible al usuario
     * (nivel 3 o asignado — ver EmpresaRepository::getIdsGrupoRucAccesible()), sumando sus saldos
     * en una sola fila. El resto (EFECTIVO, TARJETA, ANTICIPO...) queda igual, por empresa. Si un
     * establecimiento hermano falla al calcular su saldo, se omite en vez de tumbar el dashboard.
     */
    private function consolidarFormasPorRuc(int $idEmpresa, int $idUsuario, array $formasPropias): array
    {
        $idsGrupo = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        $hermanas = array_values(array_diff($idsGrupo, [$idEmpresa]));
        if (!$hermanas) {
            return $formasPropias;
        }

        $todas = $formasPropias;
        foreach ($hermanas as $idHermana) {
            try {
                $todas = array_merge($todas, $this->calcularFormasCaja($idHermana));
            } catch (\Throwable $ex) {
                // Establecimiento hermano roto (tablas de migración faltantes, etc.): se omite.
            }
        }

        $out = [];
        $porCuenta = [];
        foreach ($todas as $f) {
            $esBancaria = in_array($f['tipo'], ['BANCO', 'CHEQUE'], true)
                && !empty($f['id_banco']) && trim((string) ($f['numero_cuenta'] ?? '')) !== '';
            if (!$esBancaria) {
                $out[] = $f;
                continue;
            }
            $clave = $f['id_banco'] . '|' . trim((string) $f['numero_cuenta']);
            $porCuenta[$clave][] = $f;
        }
        foreach ($porCuenta as $filas) {
            if (count($filas) === 1) {
                $out[] = $filas[0];
                continue;
            }
            $out[] = [
                'id'     => $filas[0]['id'],
                'nombre' => $filas[0]['nombre'] . ' · consolidado (' . count($filas) . ' establecimientos)',
                'tipo'   => $filas[0]['tipo'],
                'saldo'  => array_sum(array_column($filas, 'saldo')),
            ];
        }
        return $out;
    }

    private function calcularSaldosCaja(int $e): array
    {
        return [
            'formas'                => $this->calcularFormasCaja($e),
            'anticipos_clientes'    => $this->getAnticipoGlobal($e, 'CLIENTE'),
            'anticipos_proveedores' => $this->getAnticipoGlobal($e, 'PROVEEDOR'),
            'tiene_datos'           => true,
        ];
    }

    /**
     * Saldo real actual de cada forma de pago de la empresa (sin anticipos). El
     * consolidado por RUC lo pide por cada establecimiento hermano; antes se le
     * calculaban también los anticipos (seis consultas más por hermano) para
     * luego descartarlos.
     */
    private function calcularFormasCaja(int $e): array
    {
        // ── Bancos / Efectivo / Tarjeta / Otro: saldo real actual por forma ──
        //   saldo = saldo_inicial (saldos_iniciales_bancos)
        //           + Σ cobros (ingresos_pagos)  − Σ pagos (egresos_pagos)
        //           + Σ traspasos recibidos − Σ traspasos enviados (traspasos_cabecera)
        //   Filtrado por el ambiente real de la empresa (igual que Ingresos/Egresos).
        $sqlFormas = "
            SELECT efp.id, efp.nombre, efp.tipo, efp.id_banco, efp.numero_cuenta,
                   COALESCE(sib.saldo_inicial, 0)
                   + COALESCE(ing.total, 0)
                   - COALESCE(egr.total, 0)
                   + COALESCE(trsIn.total, 0)
                   - COALESCE(trsOut.total, 0) AS saldo
            FROM empresa_formas_pago efp
            LEFT JOIN saldos_iniciales_bancos sib
                   ON sib.id_forma_pago = efp.id
                  AND sib.id_empresa   = efp.id_empresa
                  AND sib.eliminado    = FALSE
            LEFT JOIN (
                SELECT ip.id_forma_cobro AS id_forma, SUM(ip.monto) AS total
                FROM ingresos_pagos ip
                INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                WHERE ic.id_empresa = :e
                  AND ic.eliminado  = FALSE
                  AND ic.estado    <> 'anulado'
                  AND ic.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                GROUP BY ip.id_forma_cobro
            ) ing ON ing.id_forma = efp.id
            LEFT JOIN (
                SELECT ep.id_forma_pago AS id_forma, SUM(ep.monto) AS total
                FROM egresos_pagos ep
                INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                WHERE ec.id_empresa = :e
                  AND ec.eliminado  = FALSE
                  AND ec.estado    <> 'anulado'
                  AND ep.eliminado  = FALSE
                  AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                GROUP BY ep.id_forma_pago
            ) egr ON egr.id_forma = efp.id
            LEFT JOIN (
                SELECT tc.id_forma_destino AS id_forma, SUM(tc.monto) AS total
                FROM traspasos_cabecera tc
                WHERE tc.id_empresa = :e
                  AND tc.eliminado  = FALSE
                  AND tc.estado    <> 'anulado'
                  AND tc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                GROUP BY tc.id_forma_destino
            ) trsIn ON trsIn.id_forma = efp.id
            LEFT JOIN (
                SELECT tc.id_forma_origen AS id_forma, SUM(tc.monto) AS total
                FROM traspasos_cabecera tc
                WHERE tc.id_empresa = :e
                  AND tc.eliminado  = FALSE
                  AND tc.estado    <> 'anulado'
                  AND tc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                GROUP BY tc.id_forma_origen
            ) trsOut ON trsOut.id_forma = efp.id
            WHERE efp.id_empresa = :e
              AND efp.eliminado  = FALSE
              AND efp.activo     = TRUE
              AND efp.tipo      <> 'ANTICIPO'
            ORDER BY efp.tipo, efp.nombre";
        $st = $this->db->prepare($sqlFormas);
        $st->execute([':e' => $e]);
        $formas = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $formas[] = [
                'id'            => (int) $r['id'],
                'nombre'        => $r['nombre'],
                'tipo'          => $r['tipo'],
                'saldo'         => (float) $r['saldo'],
                'id_banco'      => $r['id_banco'] !== null ? (int) $r['id_banco'] : null,
                'numero_cuenta' => $r['numero_cuenta'],
            ];
        }

        return $formas;
    }

    /**
     * Saldo global de anticipos por dirección (sin depender de un tercero):
     *   saldo = Σ saldo_inicial (saldos_iniciales_anticipos del tipo)
     *         + Σ generado (ingresos/egresos con opción ANTICIPO_CLIENTE/PROVEEDOR)
     *         − Σ aplicado (pagos que consumen formas de anticipo de esa dirección)
     * Misma lógica que FormaPagoRepository::getSaldoAnticipo pero agregada (todos los terceros).
     */
    private function getAnticipoGlobal(int $e, string $tipo): float
    {
        $ini = $this->db->prepare(
            "SELECT COALESCE(SUM(saldo_inicial), 0)
             FROM saldos_iniciales_anticipos
             WHERE id_empresa = :e AND eliminado = FALSE AND tipo = :t"
        );
        $ini->execute([':e' => $e, ':t' => $tipo]);
        $inicial = (float) $ini->fetchColumn();

        // Generado: anticipos registrados con una opción ANTICIPO_CLIENTE/PROVEEDOR.
        if ($tipo === 'PROVEEDOR') {
            $gen = $this->db->prepare(
                "SELECT COALESCE(SUM(ec.monto_total), 0)
                 FROM egresos_cabecera ec
                 INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ec.id_egreso_concepto
                 WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                   AND o.comportamiento = 'ANTICIPO_PROVEEDOR'"
            );
        } else {
            $gen = $this->db->prepare(
                "SELECT COALESCE(SUM(ic.monto_total), 0)
                 FROM ingresos_cabecera ic
                 INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ic.id_ingreso_concepto
                 WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
                   AND o.comportamiento = 'ANTICIPO_CLIENTE'"
            );
        }
        $gen->execute([':e' => $e]);
        $generado = (float) $gen->fetchColumn();

        if ($tipo === 'PROVEEDOR') {
            $apl = $this->db->prepare(
                "SELECT COALESCE(SUM(ep.monto), 0)
                 FROM egresos_pagos ep
                 INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                 INNER JOIN empresa_formas_pago efp ON efp.id = ep.id_forma_pago
                 WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                   AND ep.eliminado = FALSE
                   AND efp.tipo = 'ANTICIPO' AND UPPER(efp.aplica_en) = 'EGRESO'"
            );
        } else {
            $apl = $this->db->prepare(
                "SELECT COALESCE(SUM(ip.monto), 0)
                 FROM ingresos_pagos ip
                 INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                 INNER JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
                 WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
                   AND efp.tipo = 'ANTICIPO' AND UPPER(efp.aplica_en) = 'INGRESO'"
            );
        }
        $apl->execute([':e' => $e]);
        $aplicado = (float) $apl->fetchColumn();

        return round($inicial + $generado - $aplicado, 2);
    }

    // ── Tablas recientes ──────────────────────────────────────────────────────

    private function getVentasRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT cl.nombre AS entidad, v.importe_total AS total,
                    v.fecha_emision AS fecha, v.estado,
                    CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS comprobante
             FROM ventas_cabecera v
             INNER JOIN clientes cl ON cl.id = v.id_cliente
             WHERE v.id_empresa = ? AND v.eliminado = false
               AND {$this->condAmbiente('v.tipo_ambiente', $ta)}
             ORDER BY v.fecha_emision DESC, v.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getComprasRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT p.razon_social AS entidad, c.importe_total AS total,
                    c.fecha_emision AS fecha, 'registrado' AS estado,
                    CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS comprobante
             FROM compras_cabecera c
             INNER JOIN proveedores p ON p.id = c.id_proveedor
             WHERE c.id_empresa = ? AND c.eliminado = false
               AND {$this->condAmbiente('c.tipo_ambiente', $ta)}
             ORDER BY c.fecha_emision DESC, c.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getIngresosRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(i.recibo_de, cl.nombre, 'Sin nombre') AS entidad,
                    i.monto_total AS total, i.fecha_emision AS fecha,
                    i.estado, i.numero_ingreso AS comprobante
             FROM ingresos_cabecera i
             LEFT JOIN clientes cl ON cl.id = i.id_cliente
             WHERE i.id_empresa = ? AND i.eliminado = false
               AND i.tipo_ambiente = ?
             ORDER BY i.fecha_emision DESC, i.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getEgresosRecientes(int $e, int $lim, string $ta): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(p.razon_social, emp.nombres_apellidos, 'Sin nombre') AS entidad,
                    eg.monto_total AS total, eg.fecha_emision AS fecha,
                    eg.estado, eg.numero_egreso AS comprobante
             FROM egresos_cabecera eg
             LEFT JOIN proveedores p  ON p.id  = eg.id_proveedor
             LEFT JOIN empleados  emp ON emp.id = eg.id_empleado
             WHERE eg.id_empresa = ? AND eg.eliminado = false
               AND eg.tipo_ambiente = ?
             ORDER BY eg.fecha_emision DESC, eg.id DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Vencidos ─────────────────────────────────────────────────────────────
    //
    // Mismas filas que Cuentas por Cobrar / por Pagar con el estado «Vencidas»
    // (a hoy): el vencimiento es emisión + días de crédito y los días cuentan
    // desde el vencimiento, igual que la columna «Días vencido» de esos módulos.

    private function getCxcVencidas(int $e, int $lim, array $alcance): array
    {
        $repo    = new CuentasPorCobrarRepository();
        $filtros = $this->filtrosCxc('VENCIDAS', date('Y-m-d'), $alcance);

        $filas = [];
        foreach (array_merge($repo->getListado($e, $filtros), $repo->getListadoRecibos($e, $filtros)) as $r) {
            $filas[] = [
                'cliente'      => $r['cliente_nombre'],
                'comprobante'  => $r['numero_factura'],
                'fecha'        => $r['fecha_emision'],
                'saldo'        => (float) $r['saldo'],
                'dias_vencido' => (int) $r['dias_vencido'],
            ];
        }
        // Saldos iniciales: el módulo los trae todos y filtra el estado en PHP.
        if ($repo->incluyeSaldosIniciales($filtros)) {
            $saldos = $repo->getSaldosInicialesCxc($e, array_merge($filtros, ['estado' => 'TODOS']));
            foreach ($saldos as $s) {
                if ((float) $s['saldo_pendiente'] > 0 && (int) $s['dias_vencido'] > 0) {
                    $filas[] = [
                        'cliente'      => $s['nombre_cliente'],
                        'comprobante'  => $s['nro_documento'],
                        'fecha'        => $s['fecha_emision'],
                        'saldo'        => (float) $s['saldo_pendiente'],
                        'dias_vencido' => (int) $s['dias_vencido'],
                    ];
                }
            }
        }
        return $this->masVencidos($filas, $lim);
    }

    private function getCxpVencidas(int $e, int $lim, array $alcance): array
    {
        $repo    = new CuentasPorPagarRepository();
        $filtros = $this->filtrosCxp('VENCIDAS', date('Y-m-d'), $alcance);

        $filas = [];
        foreach ($repo->getListado($e, $filtros) as $r) {
            $filas[] = [
                'proveedor'    => $r['proveedor_nombre'],
                'comprobante'  => $r['numero_documento'],
                'fecha'        => $r['fecha_emision'],
                'saldo'        => (float) $r['saldo'],
                'dias_vencido' => (int) $r['dias_vencido'],
            ];
        }
        foreach ($repo->getSaldosInicialesCxp($e, array_merge($filtros, ['estado' => 'TODOS'])) as $s) {
            if ((float) $s['saldo_pendiente'] > 0 && (int) $s['dias_vencido'] > 0) {
                $filas[] = [
                    'proveedor'    => $s['nombre_proveedor'],
                    'comprobante'  => $s['nro_documento'],
                    'fecha'        => $s['fecha_emision'],
                    'saldo'        => (float) $s['saldo_pendiente'],
                    'dias_vencido' => (int) $s['dias_vencido'],
                ];
            }
        }
        return $this->masVencidos($filas, $lim);
    }

    /**
     * Los $lim documentos más vencidos. Desempate por saldo y comprobante: con
     * varios documentos del mismo día, sin él se mostraban unos u otros.
     */
    private function masVencidos(array $filas, int $lim): array
    {
        usort($filas, static fn (array $a, array $b): int =>
            [$b['dias_vencido'], $b['saldo'], $a['comprobante']] <=> [$a['dias_vencido'], $a['saldo'], $b['comprobante']]);
        return array_slice($filas, 0, $lim);
    }

    // ── Gráficos ─────────────────────────────────────────────────────────────

    private function getTendenciaMensual(int $e, int $meses, string $ta): array
    {
        $data = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            // «first day of»: restar meses a un día 29-31 salta al mes siguiente
            // (31-oct − 1 mes = 1-oct) y dejaba un mes repetido y otro sin barra.
            $ts         = strtotime("first day of -{$i} months");
            $key        = date('Y-m', $ts);
            $data[$key] = [
                'mes'      => date('M Y', $ts),
                'ventas'   => 0, 'compras'  => 0,
                'ingresos' => 0, 'egresos'  => 0,
                'nomina'   => 0,
            ];
        }
        $desde = date('Y-m-01', strtotime('first day of -' . ($meses - 1) . ' months'));

        // Nómina mensual (rol_cabecera por período; filtra por ambiente)
        $stN = $this->db->prepare(
            "SELECT TO_CHAR(make_date(periodo_anio, periodo_mes, 1),'YYYY-MM') k, SUM(total_ingresos) t
             FROM rol_cabecera
             WHERE id_empresa=? AND eliminado=false AND estado NOT IN ('anulado','borrador')
               AND {$this->condAmbiente('tipo_ambiente', $ta)}
               AND make_date(periodo_anio, periodo_mes, 1) >= ?
             GROUP BY k"
        );
        $stN->execute([$e, $desde]);
        foreach ($stN->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($data[$r['k']])) $data[$r['k']]['nomina'] = (float) $r['t'];
        }

        // Ventas y Compras: mismo criterio que las tarjetas (sumVentas / sumCompras)
        foreach ([
            'ventas'  => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(importe_total) t
                          FROM ventas_cabecera WHERE id_empresa=? AND eliminado=false AND {$this->condVentaValida()}
                            AND {$this->condAmbiente('tipo_ambiente', $ta)} AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
            'compras' => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM({$this->exprCompraNeta()}) t
                          FROM compras_cabecera WHERE id_empresa=? AND eliminado=false AND {$this->condCompraVigente()}
                            AND {$this->condAmbiente('tipo_ambiente', $ta)} AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
        ] as $campo => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([$e, $desde]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($data[$r['k']])) $data[$r['k']][$campo] = (float) $r['t'];
            }
        }

        // Ingresos y Egresos: filtran por ambiente igual que las tarjetas (sumIngresos /
        // sumEgresos). Antes el gráfico no lo filtraba y sumaba también los de pruebas,
        // así que la barra del mes no cuadraba con la tarjeta.
        foreach ([
            'ingresos' => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(monto_total) t
                           FROM ingresos_cabecera WHERE id_empresa=? AND eliminado=false AND estado!='anulado'
                             AND tipo_ambiente=? AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
            'egresos'  => "SELECT TO_CHAR(CAST(fecha_emision AS DATE),'YYYY-MM') k, SUM(monto_total) t
                           FROM egresos_cabecera WHERE id_empresa=? AND eliminado=false AND estado!='anulado'
                             AND tipo_ambiente=? AND CAST(fecha_emision AS DATE)>=? GROUP BY k",
        ] as $campo => $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([$e, $ta, $desde]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($data[$r['k']])) $data[$r['k']][$campo] = (float) $r['t'];
            }
        }

        return array_values($data);
    }

    private function getTopProductos(int $e, string $ta, string $d, string $h, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(p.nombre, det.descripcion) AS nombre,
                    SUM(det.cantidad) AS cantidad,
                    SUM(det.precio_total_sin_impuesto) AS total
             FROM ventas_detalle det
             INNER JOIN ventas_cabecera v ON v.id = det.id_venta
             LEFT JOIN productos p ON p.id = det.id_producto
             WHERE v.id_empresa = ? AND v.eliminado = false AND {$this->condVentaValida('v')}
               AND {$this->condAmbiente('v.tipo_ambiente', $ta)}
               AND CAST(v.fecha_emision AS DATE) BETWEEN ? AND ?
             GROUP BY COALESCE(p.nombre, det.descripcion)
             ORDER BY total DESC
             LIMIT ?"
        );
        $st->execute([$e, $d, $h, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopClientes(int $e, string $ta, string $d, string $h, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT cl.nombre, SUM(v.importe_total) AS total, COUNT(v.id) AS facturas
             FROM ventas_cabecera v
             INNER JOIN clientes cl ON cl.id = v.id_cliente
             WHERE v.id_empresa = ? AND v.eliminado = false AND {$this->condVentaValida('v')}
               AND {$this->condAmbiente('v.tipo_ambiente', $ta)}
               AND CAST(v.fecha_emision AS DATE) BETWEEN ? AND ?
             GROUP BY cl.nombre
             ORDER BY total DESC
             LIMIT ?"
        );
        $st->execute([$e, $d, $h, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Top proveedores por monto de compras del período. */
    private function getTopProveedores(int $e, string $ta, string $d, string $h, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT p.razon_social AS nombre, SUM({$this->exprCompraNeta('c')}) AS total, COUNT(c.id) AS compras
             FROM compras_cabecera c
             INNER JOIN proveedores p ON p.id = c.id_proveedor
             WHERE c.id_empresa = ? AND c.eliminado = false AND {$this->condCompraVigente('c')}
               AND {$this->condAmbiente('c.tipo_ambiente', $ta)}
               AND CAST(c.fecha_emision AS DATE) BETWEEN ? AND ?
             GROUP BY p.razon_social
             ORDER BY total DESC
             LIMIT ?"
        );
        $st->execute([$e, $d, $h, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Egresos del período agrupados por concepto (para gráfico de dona). */
    private function getEgresosPorConcepto(int $e, string $ta, string $d, string $h, int $lim): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(op.nombre, 'Sin concepto') AS nombre,
                    SUM(eg.monto_total) AS total
             FROM egresos_cabecera eg
             LEFT JOIN empresa_opciones_ingreso_egreso op ON op.id = eg.id_egreso_concepto
             WHERE eg.id_empresa = ? AND eg.eliminado = false AND eg.estado != 'anulado'
               AND eg.tipo_ambiente = ?
               AND CAST(eg.fecha_emision AS DATE) BETWEEN ? AND ?
             GROUP BY COALESCE(op.nombre, 'Sin concepto')
             ORDER BY total DESC
             LIMIT ?"
        );
        $st->execute([$e, $ta, $d, $h, $lim]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
