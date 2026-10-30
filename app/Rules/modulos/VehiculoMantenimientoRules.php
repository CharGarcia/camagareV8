<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones del control de mantenimientos por kilometraje.
 */
class VehiculoMantenimientoRules
{
    /** Tope razonable de un odómetro / intervalo (evita un km mal escrito con ceros de más). */
    public const KM_MAXIMO = 9999999;

    public function validarServicio(array $d): void
    {
        $errores = [];
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') {
            $errores[] = 'El nombre del servicio es obligatorio.';
        } elseif (mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre no puede pasar de 100 caracteres.';
        }
        $intervalo = (int) ($d['intervalo_km'] ?? 0);
        if ($intervalo <= 0 || $intervalo > self::KM_MAXIMO) {
            $errores[] = 'Indique cada cuántos km se hace el servicio (mayor que 0).';
        }
        $aviso = (int) ($d['aviso_km'] ?? -1);
        if ($aviso < 0) {
            $errores[] = 'Los km de aviso no pueden ser negativos.';
        } elseif ($intervalo > 0 && $aviso >= $intervalo) {
            $errores[] = 'Los km de aviso deben ser menores que el intervalo del servicio.';
        }
        if ($errores) throw new Exception(implode(' ', $errores));
    }

    public function validarIntervalo(?int $intervalo, string $servicio): void
    {
        if ($intervalo !== null && ($intervalo <= 0 || $intervalo > self::KM_MAXIMO)) {
            throw new Exception("El intervalo de «{$servicio}» debe ser mayor que 0 km.");
        }
    }

    /** Un cambio: km del cambio y km del próximo cambio (mayor que el del cambio). */
    public function validarCambio(?int $km, int $proximoKm, string $servicio, ?string $fecha = null): void
    {
        if ($km === null || $km < 0) {
            throw new Exception("Ingrese el kilometraje para registrar el cambio de «{$servicio}».");
        }
        if ($km > self::KM_MAXIMO || $proximoKm > self::KM_MAXIMO) {
            throw new Exception("El kilometraje de «{$servicio}» no es válido.");
        }
        if ($proximoKm <= $km) {
            throw new Exception("El próximo cambio de «{$servicio}» debe ser mayor que el km del cambio (" . number_format($km, 0, ',', '.') . ' km).');
        }
        if ($fecha !== null) {
            $d = \DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$d || $d->format('Y-m-d') !== $fecha) throw new Exception('La fecha del cambio no es válida.');
            if ($fecha > date('Y-m-d')) throw new Exception('La fecha del cambio no puede ser posterior a hoy.');
        }
    }
}
