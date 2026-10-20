<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de la apertura y el cierre de caja del Resumen Diario. Los importes
 * vienen del navegador: solo se aceptan formas de pago que el servidor ya conoce y
 * valores numéricos.
 */
class CajaCierreRules
{
    /** Texto opcional, recortado al largo de la columna. */
    public function observaciones(?string $v, int $max): string
    {
        return mb_substr(trim((string) $v), 0, $max);
    }

    /** Importe escrito por el usuario: vacío = 0; si no es número, error con el nombre de la forma. */
    private function importe($v, string $forma): float
    {
        $v = trim(str_replace(',', '.', (string) $v));
        if ($v === '') {
            return 0.0;
        }
        if (!is_numeric($v)) {
            throw new \InvalidArgumentException("El valor de «{$forma}» no es un número.");
        }
        return round((float) $v, 2);
    }

    /**
     * Saldos iniciales al abrir: solo de las formas permitidas ($formas: id => nombre).
     * Pueden ser negativos (un banco sobregirado). Devuelve id => saldo.
     */
    public function validarApertura(array $filas, array $formas): array
    {
        $out = [];
        foreach ($filas as $f) {
            $id = (int) ($f['id_forma_pago'] ?? 0);
            if (!isset($formas[$id])) {
                continue;
            }
            $out[$id] = $this->importe($f['saldo'] ?? '', $formas[$id]);
        }
        return $out;
    }

    /**
     * Lo contado al cerrar: obligatorio en cada forma de la caja ($formas: id => nombre).
     * Devuelve id => contado.
     */
    public function validarCierre(array $filas, array $formas): array
    {
        $recibido = [];
        foreach ($filas as $f) {
            $recibido[(int) ($f['id_forma_pago'] ?? -1)] = $f['contado'] ?? '';
        }
        $out = [];
        foreach ($formas as $id => $nombre) {
            $v = $recibido[$id] ?? '';
            if (trim((string) $v) === '') {
                throw new \InvalidArgumentException("Indique lo contado en «{$nombre}».");
            }
            // Puede ser negativo: el saldo de un banco sobregirado también se "cuenta".
            $out[$id] = $this->importe($v, $nombre);
        }
        return $out;
    }
}
