<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Validaciones de la consulta de Métricas del sistema (/config/metricas-sistema).
 */
class MetricasSistemaRules
{
    public const SECCIONES = ['resumen', 'usuarios', 'empresas', 'documentos', 'productos', 'sri', 'errores', 'tendencias', 'salud', 'modulos', 'comercial'];

    /** Tope del rango en vivo: más de un año vuelve pesadas las consultas. */
    public const MAX_DIAS = 366;

    /**
     * Tendencias lee el resumen precalculado (metricas_diarias), así que admite todo el
     * historial (documentos migrados desde 2017 o antes). Tope solo contra rangos absurdos.
     */
    public const MAX_DIAS_TENDENCIAS = 36600;

    public function validar(string $seccion, string $desde, string $hasta): void
    {
        if (!in_array($seccion, self::SECCIONES, true)) {
            throw new \InvalidArgumentException('Sección de métricas no válida.');
        }

        (new RangoFechasRules())->validar($desde, $hasta);

        $max  = $seccion === 'tendencias' ? self::MAX_DIAS_TENDENCIAS : self::MAX_DIAS;
        $dias = (new \DateTime($desde))->diff(new \DateTime($hasta))->days;
        if ($dias > $max) {
            throw new \InvalidArgumentException('El rango no puede superar ' . $max . ' días'
                . ($seccion === 'tendencias' ? '.' : ' (la pestaña Tendencias admite todo el historial).'));
        }
    }
}
