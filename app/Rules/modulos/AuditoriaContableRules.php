<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio del módulo Auditoría Contable.
 * Lanzan \Exception con mensaje legible (patrón del resto de Rules).
 */
class AuditoriaContableRules
{
    /** Estados de revisión que el usuario puede fijar manualmente. */
    private const REVISIONES_MANUALES = ['revisada', 'justificada', 'pendiente'];

    /** Valida el estado de revisión recibido desde la UI. */
    public function validarEstadoRevision(string $estado): void
    {
        if (!in_array($estado, self::REVISIONES_MANUALES, true)) {
            throw new \Exception('Estado de revisión no válido.');
        }
    }

    /**
     * Valida los parámetros de una regeneración masiva.
     *
     * @param string   $origen          modulo_origen solicitado
     * @param string[] $origenesValidos whitelist de orígenes del repository
     */
    public function validarRegeneracion(string $origen, array $origenesValidos, ?string $fechaDesde, ?string $fechaHasta): void
    {
        if (!in_array($origen, $origenesValidos, true)) {
            throw new \Exception('El módulo de origen indicado no es válido para regeneración.');
        }

        $d = $this->parsearFecha($fechaDesde, 'fecha desde');
        $h = $this->parsearFecha($fechaHasta, 'fecha hasta');

        if ($d !== null && $h !== null && $d > $h) {
            throw new \Exception('La "fecha desde" no puede ser mayor que la "fecha hasta".');
        }
    }

    /** Valida un rango de fechas suelto (filtro del listado / corrida acotada). */
    public function validarRango(?string $fechaDesde, ?string $fechaHasta): void
    {
        $d = $this->parsearFecha($fechaDesde, 'fecha desde');
        $h = $this->parsearFecha($fechaHasta, 'fecha hasta');
        if ($d !== null && $h !== null && $d > $h) {
            throw new \Exception('La "fecha desde" no puede ser mayor que la "fecha hasta".');
        }
    }

    /** Valida un origen contra la whitelist (para acciones por fila). */
    public function validarOrigen(string $origen, array $origenesValidos): void
    {
        if (!in_array($origen, $origenesValidos, true)) {
            throw new \Exception('Módulo de origen no válido.');
        }
    }

    private function parsearFecha(?string $fecha, string $etiqueta): ?\DateTimeImmutable
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if ($dt === false) {
            throw new \Exception("El formato de la {$etiqueta} debe ser AAAA-MM-DD.");
        }
        return $dt;
    }
}
