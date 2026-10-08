<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Rubros de gastos personales del SRI (formulario de proyección SRI-GP y Anexo de
 * Gastos Personales). Mismos códigos que `empleado_gastos_personales` /
 * `GastoPersonalRepository::RUBROS`, para que nómina, compras y la Declaración de
 * Renta hablen el mismo idioma.
 */
final class RubrosGastoPersonal
{
    public const SIN_RUBRO = 'sin_rubro';

    /** código => etiqueta que ve el usuario (orden del formulario del SRI). */
    public const CATALOGO = [
        'vivienda'     => 'Vivienda',
        'salud'        => 'Salud',
        'educacion'    => 'Educación, arte y cultura',
        'alimentacion' => 'Alimentación',
        'vestimenta'   => 'Vestimenta',
        'turismo'      => 'Turismo nacional',
    ];

    public static function etiqueta(?string $codigo): string
    {
        $codigo = (string) $codigo;
        if ($codigo === '' || $codigo === self::SIN_RUBRO) {
            return 'Sin rubro';
        }
        return self::CATALOGO[$codigo] ?? ucfirst($codigo);
    }

    /** Código saneado para guardar: null si viene vacío o no está en el catálogo. */
    public static function normalizar($codigo): ?string
    {
        $codigo = strtolower(trim((string) $codigo));
        return isset(self::CATALOGO[$codigo]) ? $codigo : null;
    }

    /** Opciones [['v' => código, 'l' => etiqueta], …] para selects y filtros. */
    public static function opciones(bool $conSinRubro = false): array
    {
        $out = [];
        foreach (self::CATALOGO as $v => $l) {
            $out[] = ['v' => $v, 'l' => $l];
        }
        if ($conSinRubro) {
            $out[] = ['v' => self::SIN_RUBRO, 'l' => 'Sin rubro'];
        }
        return $out;
    }
}
