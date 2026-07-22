<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\GastoPersonalRepository;
use PDO;

/**
 * Cálculo de la retención de Impuesto a la Renta en la fuente por rentas del
 * trabajo en relación de dependencia (Art. 43 LRTI / RALRTI), método de
 * proyección anual simplificado:
 *
 *   ingreso gravado anual = ingreso gravado mensual (base IESS) x 12
 *   base imponible anual  = ingreso gravado anual − aporte IESS personal anual
 *   impuesto causado      = impuesto de la fracción básica del tramo
 *                           + (base imponible − fracción básica) x % excedente
 *   rebaja gastos pers.   = % rebaja x MIN(gastos proyectados, tope por cargas)
 *   impuesto anual        = MAX(0, impuesto causado − rebaja)
 *   retención mensual     = impuesto anual / 12
 *
 * IMPORTANTE — los gastos personales NO se restan de la base imponible: generan
 * una REBAJA DEL IMPUESTO CAUSADO. Además:
 *
 *  - El valor que cuenta es la PROYECCIÓN que cada trabajador presenta al
 *    empleador (formulario SRI-GP, en enero). Quien no la presenta no rebaja nada.
 *  - El tope no es único por año: es la canasta familiar básica multiplicada por
 *    un factor que depende del número de cargas familiares del trabajador
 *    (0 cargas → 7 canastas … 5 o más → 20; caso especial por discapacidad o
 *    enfermedad catastrófica → 100).
 *
 * Ver empleado_gastos_personales e impuesto_renta_parametros.
 *
 * Simplificación deliberada (v1): la proyección no reconstruye el acumulado real
 * pagado en meses anteriores del año ni prorratea los meses restantes; asume un
 * ingreso mensual estable y reparte el impuesto anual en partes iguales. Suficiente
 * para la mayoría de nóminas estables; casos con cambios de sueldo a mitad de año
 * pueden requerir un ajuste manual puntual.
 *
 * Si no hay tabla de tramos cargada para el año (catálogo se entrega vacío a
 * propósito, ver migración), la retención es 0 — no se inventa una tabla.
 */
class ImpuestoRentaEmpleadoService
{
    /** Factores por defecto (canastas básicas) si el año no los tiene configurados. */
    public const FACTORES_DEFECTO = ['0' => 7, '1' => 9, '2' => 11, '3' => 14, '4' => 17, '5' => 20, 'especial' => 100];

    /** Porcentaje de rebaja por defecto si el año no lo tiene configurado. */
    public const PORCENTAJE_REBAJA_DEFECTO = 18.0;

    private PDO $db;
    private ?GastoPersonalRepository $gastoRepo = null;
    private array $cacheParametros = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Tramos vigentes del año (ordenados). Vacío si el catálogo no ha sido cargado. */
    public function getTramosAnio(int $anio): array
    {
        $st = $this->db->prepare(
            "SELECT fraccion_basica, exceso_hasta, impuesto_fraccion_basica, porcentaje_excedente
             FROM impuesto_renta_tramos
             WHERE anio = :a AND eliminado = false
             ORDER BY orden ASC"
        );
        $st->execute([':a' => $anio]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Parámetros del año para gastos personales:
     * ['canasta_basica', 'porcentaje_rebaja', 'factores', 'configurado'].
     */
    public function getParametrosAnio(int $anio): array
    {
        if (isset($this->cacheParametros[$anio])) {
            return $this->cacheParametros[$anio];
        }

        $canasta = 0.0;
        $pct = self::PORCENTAJE_REBAJA_DEFECTO;
        $factores = self::FACTORES_DEFECTO;

        try {
            $st = $this->db->prepare(
                "SELECT canasta_basica, porcentaje_rebaja, factores_canastas
                 FROM impuesto_renta_parametros WHERE anio = :a"
            );
            $st->execute([':a' => $anio]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $canasta = (float) ($row['canasta_basica'] ?? 0);
                if ((float) ($row['porcentaje_rebaja'] ?? 0) > 0) {
                    $pct = (float) $row['porcentaje_rebaja'];
                }
                $f = json_decode((string) ($row['factores_canastas'] ?? ''), true);
                if (is_array($f) && $f) {
                    $factores = $f;
                }
            }
        } catch (\Throwable $e) {
            // Migración pendiente: se usan los valores por defecto (canasta 0 => sin tope).
        }

        return $this->cacheParametros[$anio] = [
            'canasta_basica'    => $canasta,
            'porcentaje_rebaja' => $pct,
            'factores'          => $factores,
            'configurado'       => $canasta > 0,
        ];
    }

    /**
     * Tope anual de gastos personales de un trabajador: canasta básica x factor
     * según sus cargas familiares. 0 si la canasta no está configurada (sin tope).
     */
    public function getTopeGastoPersonal(int $anio, int $cargas, bool $casoEspecial = false): float
    {
        $p = $this->getParametrosAnio($anio);
        if ($p['canasta_basica'] <= 0) {
            return 0.0;
        }
        return round($p['canasta_basica'] * self::factorCanastas($p['factores'], $cargas, $casoEspecial), 2);
    }

    /** Número de canastas que corresponde a esas cargas familiares. */
    public static function factorCanastas(array $factores, int $cargas, bool $casoEspecial = false): float
    {
        if ($casoEspecial) {
            return (float) ($factores['especial'] ?? self::FACTORES_DEFECTO['especial']);
        }
        $cargas = max(0, $cargas);
        // Las claves numéricas van de 0 a N; a partir de ahí se aplica la última.
        $maxClave = 0;
        foreach (array_keys($factores) as $k) {
            if (is_numeric($k)) {
                $maxClave = max($maxClave, (int) $k);
            }
        }
        $clave = (string) min($cargas, $maxClave);

        return (float) ($factores[$clave] ?? self::FACTORES_DEFECTO['0']);
    }

    /**
     * Rebaja anual de un empleado: % rebaja x MIN(proyectado, tope por sus cargas).
     * Devuelve el desglose completo para poder mostrarlo y auditarlo.
     *
     * @return array{proyectado: ?float, tope: float, base_rebaja: float, rebaja: float,
     *               cargas: int, caso_especial: bool, topado: bool, porcentaje: float}
     */
    public function getRebajaGastoPersonal(int $idEmpresa, int $idEmpleado, int $anio, ?float $proyectadoOverride = null, ?int $cargasOverride = null, ?bool $especialOverride = null): array
    {
        $fila = $this->gastoRepo()->getPorAnio($idEmpleado, $idEmpresa, $anio);

        $proyectado = $proyectadoOverride ?? ($fila ? (float) $fila['total_proyectado'] : null);
        $cargas     = $cargasOverride ?? (int) ($fila['numero_cargas_familiares'] ?? 0);
        $especial   = $especialOverride ?? $this->esVerdadero($fila['caso_especial'] ?? false);

        return $this->armarRebaja($anio, $proyectado, $cargas, $especial);
    }

    /**
     * Igual que getRebajaGastoPersonal() pero para muchos empleados en una sola
     * consulta (generación del rol mensual). Devuelve [id_empleado => rebaja anual].
     * Los empleados sin proyección quedan fuera del mapa (rebaja 0).
     */
    public function getRebajaGastoPersonalMasivo(int $idEmpresa, array $idsEmpleado, int $anio): array
    {
        $filas = $this->gastoRepo()->getProyeccionesMasivo($idEmpresa, $idsEmpleado, $anio);

        $map = [];
        foreach ($filas as $idEmp => $f) {
            $d = $this->armarRebaja(
                $anio,
                (float) $f['total_proyectado'],
                (int) $f['numero_cargas_familiares'],
                $this->esVerdadero($f['caso_especial'] ?? false)
            );
            if ($d['rebaja'] > 0) {
                $map[$idEmp] = $d['rebaja'];
            }
        }
        return $map;
    }

    /** Proyección presentada por el empleado (sin limitar). null si no presentó. */
    public function getGastoPersonalProyectado(int $idEmpresa, int $idEmpleado, int $anio): ?float
    {
        return $this->gastoRepo()->getTotalAnio($idEmpleado, $idEmpresa, $anio);
    }

    /**
     * Retención mensual de IR.
     *
     * @param float $rebajaGastosPersonalesAnual Rebaja YA resuelta del empleado
     *        (ver getRebajaGastoPersonal). No es el tope ni los gastos proyectados.
     */
    public static function calcularRetencionMensual(
        float $ingresoGravadoMensual,
        float $pctAportePersonal,
        array $tramosAnio,
        float $rebajaGastosPersonalesAnual = 0.0
    ): float {
        if (empty($tramosAnio) || $ingresoGravadoMensual <= 0) {
            return 0.0;
        }

        $ingresoAnual = $ingresoGravadoMensual * 12;
        $aporteIessAnual = round($ingresoAnual * $pctAportePersonal / 100, 2);
        $baseImponibleAnual = max(0.0, $ingresoAnual - $aporteIessAnual);

        $impuestoCausado = self::impuestoCausado($baseImponibleAnual, $tramosAnio);
        $impuestoAnual = max(0.0, $impuestoCausado - max(0.0, $rebajaGastosPersonalesAnual));

        return round($impuestoAnual / 12, 2);
    }

    /** Impuesto causado según la tabla de tramos. 0 si la base no supera el primer tramo. */
    public static function impuestoCausado(float $baseImponibleAnual, array $tramosAnio): float
    {
        foreach ($tramosAnio as $t) {
            $desde = (float) $t['fraccion_basica'];
            $hasta = $t['exceso_hasta'] !== null ? (float) $t['exceso_hasta'] : null;
            if ($baseImponibleAnual >= $desde && ($hasta === null || $baseImponibleAnual < $hasta)) {
                $impuesto = (float) $t['impuesto_fraccion_basica']
                    + ($baseImponibleAnual - $desde) * ((float) $t['porcentaje_excedente'] / 100);
                return round(max(0.0, $impuesto), 2);
            }
        }
        return 0.0; // fuera de la tabla cargada
    }

    /** Arma el desglose de la rebaja a partir de proyección, cargas y caso especial. */
    private function armarRebaja(int $anio, ?float $proyectado, int $cargas, bool $especial): array
    {
        $p = $this->getParametrosAnio($anio);
        $tope = $this->getTopeGastoPersonal($anio, $cargas, $especial);

        // Sin proyección presentada no hay rebaja. Si la canasta no está configurada
        // (tope 0) no se limita: se toma la proyección completa y la vista avisa.
        if ($proyectado === null || $proyectado <= 0) {
            $base = 0.0;
        } elseif ($tope <= 0) {
            $base = round($proyectado, 2);
        } else {
            $base = round(min($proyectado, $tope), 2);
        }

        return [
            'proyectado'    => $proyectado === null ? null : round($proyectado, 2),
            'tope'          => $tope,
            'base_rebaja'   => $base,
            'rebaja'        => round($base * $p['porcentaje_rebaja'] / 100, 2),
            'cargas'        => $cargas,
            'caso_especial' => $especial,
            'topado'        => $proyectado !== null && $tope > 0 && $proyectado > $tope,
            'porcentaje'    => $p['porcentaje_rebaja'],
        ];
    }

    private function esVerdadero($v): bool
    {
        return in_array($v, [true, 1, '1', 't', 'true', 'si'], true);
    }

    private function gastoRepo(): GastoPersonalRepository
    {
        return $this->gastoRepo ??= new GastoPersonalRepository();
    }
}
