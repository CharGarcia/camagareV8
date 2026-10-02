<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ControlBancarioRepository;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\FormaPagoRepository;
use App\repositories\modulos\ReporteInventarioRepository;
use App\Rules\modulos\ControlBancarioRules;
use App\Services\ComprobacionContableService;
use App\Services\LogSistemaService;
use App\Services\ReportService;

/**
 * "Cuadre con módulos" de Estados Financieros: la Comprobación con Contabilidad vista desde
 * la contabilidad. Para cada módulo con saldo propio (cada cuenta bancaria, Cuentas por
 * Cobrar, Cuentas por Pagar, Inventarios, cada caja y los anticipos de clientes y
 * proveedores) da el saldo según el módulo y según sus cuentas
 * contables, y el detalle documento por documento con la misma pantalla común
 * (public/js/comprobacion_contable.js). Solo lectura.
 *
 * Los cálculos son los mismos que usa cada módulo: aquí solo se reúnen.
 */
class CuadreModulosService
{
    /** Módulos con definición en el motor común: clave => [repositorio, nombre]. */
    private const MODULOS = [
        'cxc'        => [CuentasPorCobrarRepository::class, 'Cuentas por Cobrar'],
        'cxp'        => [CuentasPorPagarRepository::class, 'Cuentas por Pagar'],
        'inventario' => [ReporteInventarioRepository::class, 'Inventarios'],
    ];

    private ComprobacionContableService $comprobacion;
    private ControlBancarioService $bancos;
    private FormaPagoRepository $formas;

    public function __construct(?ComprobacionContableService $comprobacion = null, ?ControlBancarioService $bancos = null)
    {
        $this->formas = new FormaPagoRepository();
        $this->comprobacion = $comprobacion ?? new ComprobacionContableService();
        $this->bancos = $bancos ?? new ControlBancarioService(
            new ControlBancarioRepository(),
            new ControlBancarioRules(),
            new LogSistemaService(),
            new ReportService()
        );
    }

    private function validarPeriodo(string $fechaInicio, string $fechaFin): void
    {
        if ($fechaInicio === '' || $fechaFin === '') {
            throw new \InvalidArgumentException('Debe indicar el período a comprobar (Desde y Hasta).');
        }
        (new \App\Rules\RangoFechasRules())->validar($fechaInicio, $fechaFin);
    }

    private function definicion(string $modulo): array
    {
        if (!isset(self::MODULOS[$modulo])) {
            throw new \InvalidArgumentException('Módulo no reconocido para el cuadre.');
        }
        $clase = self::MODULOS[$modulo][0];
        $repo = new $clase();
        return $repo->definicionComprobacionContable();
    }

    /**
     * Una fila por cuenta bancaria (las que comparten cuenta contable van juntas, como en
     * Control Bancario) y una por cada módulo, con los saldos de cada lado.
     */
    public function resumen(int $idEmpresa, string $fechaInicio, string $fechaFin): array
    {
        $this->validarPeriodo($fechaInicio, $fechaFin);
        $filas = [];

        $vistas = [];
        foreach ($this->bancos->getFormasBancarias($idEmpresa) as $forma) {
            $idForma = (int) $forma['id'];
            if (isset($vistas[$idForma])) {
                continue; // ya entró junto con otra que comparte su cuenta contable
            }
            $r = $this->bancos->getComprobacionContable($idEmpresa, $idForma, $fechaInicio, $fechaFin, false);
            if (!empty($r['sin_cuenta_contable'])) {
                $filas[] = ['modulo' => 'banco', 'id_forma' => $idForma, 'nombre' => 'Banco: ' . $forma['nombre'], 'sin_cuentas' => true];
                continue;
            }
            foreach ($r['formas'] as $f) {
                $vistas[(int) $f['id']] = true;
            }
            $filas[] = [
                'modulo' => 'banco',
                'id_forma' => $idForma,
                'nombre' => 'Banco: ' . implode(', ', array_column($r['formas'], 'nombre')),
                'sin_cuentas' => false,
                'cuentas' => $r['cuentas'],
                'inicio' => $r['inicio'],
                'fin' => $r['fin'],
            ];
        }

        // Caja: una fila por forma de pago no bancaria (las que comparten cuenta, juntas).
        $formasCaja = $this->formas->getFormasCajaConCuentas($idEmpresa);
        $vistas = [];
        foreach ($formasCaja as $forma) {
            $idForma = (int) $forma['id'];
            if (isset($vistas[$idForma])) {
                continue;
            }
            $grupo = $this->grupoCaja($formasCaja, $idForma);
            if (!$grupo['cuentas']) {
                $filas[] = ['modulo' => 'caja', 'id_forma' => $idForma, 'nombre' => 'Caja: ' . $forma['nombre'], 'sin_cuentas' => true];
                continue;
            }
            foreach ($grupo['formas'] as $idF) {
                $vistas[$idF] = true;
            }
            $r = $this->comprobacion->resumir($this->definicionCaja($grupo), $idEmpresa, $fechaInicio, $fechaFin);
            $filas[] = ['modulo' => 'caja', 'id_forma' => $idForma, 'nombre' => 'Caja: ' . implode(', ', $grupo['nombres'])] + $r;
        }

        foreach (self::MODULOS as $clave => [, $nombre]) {
            $r = $this->comprobacion->resumir($this->definicion($clave), $idEmpresa, $fechaInicio, $fechaFin);
            $filas[] = ['modulo' => $clave, 'id_forma' => null, 'nombre' => $nombre] + $r;
        }

        foreach (self::ANTICIPOS as $clave => [$proveedor, $nombre]) {
            $r = $this->comprobacion->resumir($this->definicionAnticipos($idEmpresa, $proveedor), $idEmpresa, $fechaInicio, $fechaFin);
            $filas[] = ['modulo' => $clave, 'id_forma' => null, 'nombre' => $nombre] + $r;
        }

        return ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'filas' => $filas];
    }

    /** Anticipos: clave => [¿de proveedores?, nombre]. */
    private const ANTICIPOS = [
        'anticipos_clientes'    => [false, 'Anticipos de Clientes'],
        'anticipos_proveedores' => [true, 'Anticipos a Proveedores'],
    ];

    private function definicionAnticipos(int $idEmpresa, bool $proveedor): array
    {
        return $this->formas->definicionComprobacionAnticipos(
            $proveedor,
            $this->formas->getCuentasAnticipos($idEmpresa, $proveedor)
        );
    }

    /**
     * Grupo de caja de una forma, con el mismo criterio que Control Bancario: las cuentas
     * efectivas (cobro y pago) de la forma, y de cada forma de caja el flujo que va a ellas.
     */
    private function grupoCaja(array $formasCaja, int $idForma): array
    {
        $base = null;
        foreach ($formasCaja as $f) {
            if ((int) $f['id'] === $idForma) {
                $base = $f;
                break;
            }
        }
        if ($base === null) {
            throw new \InvalidArgumentException('La forma de pago indicada no es de caja.');
        }
        $cuentas = array_values(array_unique(array_filter([(int) $base['id_cuenta_cobro'], (int) $base['id_cuenta_pago']])));
        $grupo = ['cuentas' => $cuentas, 'cobro' => [], 'pago' => [], 'formas' => [], 'nombres' => []];
        foreach ($formasCaja as $f) {
            $usaCobro = in_array((int) $f['id_cuenta_cobro'], $cuentas, true);
            $usaPago = in_array((int) $f['id_cuenta_pago'], $cuentas, true);
            if (!$usaCobro && !$usaPago) {
                continue;
            }
            if ($usaCobro) {
                $grupo['cobro'][] = (int) $f['id'];
            }
            if ($usaPago) {
                $grupo['pago'][] = (int) $f['id'];
            }
            $grupo['formas'][] = (int) $f['id'];
            $grupo['nombres'][] = $f['nombre'];
        }
        return $grupo;
    }

    private function definicionCaja(array $grupo): array
    {
        return $this->formas->definicionComprobacionCaja($grupo['cuentas'], $grupo['cobro'], $grupo['pago'], $grupo['formas']);
    }

    /** Detalle documento por documento de una fila del resumen (formato de la pantalla común). */
    public function detalle(int $idEmpresa, string $modulo, int $idForma, string $fechaInicio, string $fechaFin): array
    {
        $this->validarPeriodo($fechaInicio, $fechaFin);
        if ($modulo === 'caja') {
            $grupo = $this->grupoCaja($this->formas->getFormasCajaConCuentas($idEmpresa), $idForma);
            return $this->comprobacion->comprobar($this->definicionCaja($grupo), $idEmpresa, $fechaInicio, $fechaFin);
        }
        if (isset(self::ANTICIPOS[$modulo])) {
            $def = $this->definicionAnticipos($idEmpresa, self::ANTICIPOS[$modulo][0]);
            $def['conceptos_texto'] = 'las opciones y formas de pago de anticipo';
            return $this->comprobacion->comprobar($def, $idEmpresa, $fechaInicio, $fechaFin);
        }
        if ($modulo !== 'banco') {
            return $this->comprobacion->comprobar($this->definicion($modulo), $idEmpresa, $fechaInicio, $fechaFin);
        }

        $r = $this->bancos->getComprobacionContable($idEmpresa, $idForma, $fechaInicio, $fechaFin);
        if (!empty($r['sin_cuenta_contable'])) {
            return ['sin_cuentas' => true, 'conceptos' => 'la forma de pago ' . $r['forma']];
        }
        $r['sin_cuentas'] = false;
        $r['cuentas'] = $this->comprobacion->saldosPorCuentas(
            array_column($r['cuentas'], 'id'), 1, $idEmpresa, $fechaInicio, $fechaFin
        );
        return $r;
    }
}
