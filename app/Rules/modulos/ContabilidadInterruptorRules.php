<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\ContabilidadModulos;

class ContabilidadInterruptorRules
{
    /**
     * Solo se puede tocar el interruptor de un módulo declarado en config/contabilidad_modulos.php
     * que tenga interruptor propio. Los que llevan 'sigue_a' (Retornos y Facturación CV) dependen
     * de su documento de origen: apagarlos por separado dejaría asientos inversos a medias.
     */
    public function validarClave(string $clave): array
    {
        $def = ContabilidadModulos::definicion($clave);
        if ($def === null) {
            throw new \Exception('Módulo contable no reconocido.');
        }
        if (!empty($def['sigue_a'])) {
            $madre = ContabilidadModulos::definicion((string) $def['sigue_a']);
            throw new \Exception(
                '«' . $def['nombre'] . '» no tiene interruptor propio: sigue a «'
                . ($madre['nombre'] ?? $def['sigue_a']) . '».'
            );
        }
        return $def;
    }
}
