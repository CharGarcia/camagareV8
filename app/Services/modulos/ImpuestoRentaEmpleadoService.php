<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use PDO;

/**
 * Cálculo de la retención de Impuesto a la Renta en la fuente por rentas del
 * trabajo en relación de dependencia (Art. 43 LRTI / RALRTI), método de
 * proyección anual simplificado:
 *
 *   ingreso gravado anual proyectado = ingreso gravado mensual (base IESS) x 12
 *   base imponible anual = ingreso gravado anual − aporte IESS personal anual
 *                           − gasto personal máximo deducible del año
 *   impuesto anual = impuesto de la fracción básica del tramo + (base imponible
 *                    anual − fracción básica del tramo) x % excedente del tramo
 *   retención mensual = impuesto anual / 12
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
    private PDO $db;

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

    /** Tope de gasto personal deducible del año. 0 si no está configurado. */
    public function getGastoPersonalMaximo(int $anio): float
    {
        $st = $this->db->prepare("SELECT gasto_personal_maximo FROM impuesto_renta_parametros WHERE anio = :a");
        $st->execute([':a' => $anio]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    /**
     * Calcula la retención mensual de IR a partir del ingreso gravado mensual
     * (antes de IESS), el % de aporte personal y los parámetros del año.
     * Función pura — no toca la base de datos.
     */
    public static function calcularRetencionMensual(
        float $ingresoGravadoMensual,
        float $pctAportePersonal,
        array $tramosAnio,
        float $gastoPersonalMaximoAnual
    ): float {
        if (empty($tramosAnio) || $ingresoGravadoMensual <= 0) {
            return 0.0;
        }

        $ingresoAnual = $ingresoGravadoMensual * 12;
        $aporteIessAnual = round($ingresoAnual * $pctAportePersonal / 100, 2);
        $baseImponibleAnual = max(0.0, $ingresoAnual - $aporteIessAnual - max(0.0, $gastoPersonalMaximoAnual));

        $tramo = null;
        foreach ($tramosAnio as $t) {
            $desde = (float) $t['fraccion_basica'];
            $hasta = $t['exceso_hasta'] !== null ? (float) $t['exceso_hasta'] : null;
            if ($baseImponibleAnual >= $desde && ($hasta === null || $baseImponibleAnual < $hasta)) {
                $tramo = $t;
                break;
            }
        }
        if ($tramo === null) {
            return 0.0; // base imponible por debajo del primer tramo (no supera la base desgravada)
        }

        $impuestoAnual = (float) $tramo['impuesto_fraccion_basica']
            + ($baseImponibleAnual - (float) $tramo['fraccion_basica']) * ((float) $tramo['porcentaje_excedente'] / 100);

        return round(max(0.0, $impuestoAnual) / 12, 2);
    }
}
