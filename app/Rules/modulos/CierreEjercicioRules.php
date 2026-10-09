<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio del Cierre del Ejercicio. Las que dependen de la base (cierres
 * vigentes, ambiente, configuración) las arma el Service con lo que lee del repositorio.
 */
class CierreEjercicioRules
{
    public function validarAnio(int $anio, string $hoy): void
    {
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('Indique un año válido.');
        }
        if ("{$anio}-12-31" > $hoy) {
            throw new Exception("El año {$anio} todavía no termina: el cierre se registra al 31-12-{$anio}.");
        }
    }

    public function validarAmbiente(string $tipoAmbiente): void
    {
        if ($tipoAmbiente !== '2') {
            throw new Exception('La empresa está en ambiente de PRUEBAS. El cierre del ejercicio se hace sobre la contabilidad de producción, que es la que muestran los Estados Financieros.');
        }
    }

    /**
     * Un año se cierra una sola vez y en orden: si ya hay un cierre posterior, su apertura
     * arrancó con los saldos de este año tal como estaban, y cerrarlo ahora la dejaría desfasada.
     */
    public function validarOrden(int $anio, ?array $vigenteDelAnio, ?int $ultimoAnioVigente): void
    {
        if ($vigenteDelAnio !== null) {
            throw new Exception("El ejercicio {$anio} ya está cerrado. Para volver a cerrarlo, primero revierta ese cierre.");
        }
        if ($ultimoAnioVigente !== null && $ultimoAnioVigente > $anio) {
            throw new Exception("Ya existe el cierre del ejercicio {$ultimoAnioVigente}. Para cerrar {$anio}, revierta primero los cierres posteriores.");
        }
    }

    /** La fecha desde la que se acumulan los saldos no puede repetir una apertura ya generada. */
    public function validarSaldosDesde(?string $desde, string $fechaCierre, ?string $minimo): void
    {
        if ($desde === null) {
            if ($minimo !== null) {
                throw new Exception('Los saldos deben acumularse desde el ' . date('d-m-Y', strtotime($minimo))
                    . ', fecha de la apertura que generó el cierre anterior.');
            }
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || strtotime($desde) === false) {
            throw new Exception('La fecha "Saldos desde" no es válida.');
        }
        if ($desde > $fechaCierre) {
            throw new Exception('La fecha "Saldos desde" no puede ser posterior al cierre.');
        }
        if ($minimo !== null && $desde < $minimo) {
            throw new Exception('Los saldos no pueden acumularse desde antes del ' . date('d-m-Y', strtotime($minimo))
                . ': esa apertura (del cierre anterior) ya resume todo lo previo y se contaría dos veces.');
        }
    }

    /**
     * Las cuentas de resultados acumulados no pueden ser las del ejercicio: la apertura deja en
     * cero las del ejercicio y trasladaría el resultado sobre sí mismo.
     */
    public function validarCuentasDistintas(array $cuentas): void
    {
        $ejercicio = array_filter([$cuentas['utilidad']['id'] ?? null, $cuentas['perdida']['id'] ?? null]);
        $acumuladas = array_filter([$cuentas['utilidades_acumuladas']['id'] ?? null, $cuentas['perdidas_acumuladas']['id'] ?? null]);
        if (array_intersect($ejercicio, $acumuladas)) {
            throw new Exception('En Configuración Contable → Cierre del Ejercicio, las cuentas de Resultados Acumulados deben ser distintas de las de Utilidad/Pérdida del Ejercicio.');
        }
    }

    public function validarMotivo(string $motivo): void
    {
        if (mb_strlen(trim($motivo)) < 5) {
            throw new Exception('Indique el motivo de la reversión (al menos 5 caracteres).');
        }
    }
}
