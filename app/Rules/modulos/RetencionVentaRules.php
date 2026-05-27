<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class RetencionVentaRules
{
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa']))  $errores[] = 'El identificador de empresa es obligatorio.';
        if (empty($data['id_cliente']))  $errores[] = 'El cliente es obligatorio.';
        if (empty($data['fecha_emision'])) $errores[] = 'La fecha de emisión es obligatoria.';
        if (empty($data['establecimiento'])) $errores[] = 'El establecimiento es obligatorio.';
        if (empty($data['punto_emision']))   $errores[] = 'El punto de emisión es obligatorio.';
        if (empty($data['secuencial']))      $errores[] = 'El secuencial es obligatorio.';

        if (empty($data['periodo_fiscal'])) {
            $errores[] = 'El período fiscal es obligatorio.';
        } elseif (!preg_match('/^\d{2}\/\d{4}$/', $data['periodo_fiscal'])) {
            $errores[] = 'El período fiscal debe tener el formato MM/YYYY.';
        }

        if (empty($data['lineas']) || !is_array($data['lineas'])) {
            $errores[] = 'Debe agregar al menos una línea de retención.';
        } else {
            foreach ($data['lineas'] as $i => $linea) {
                $n = $i + 1;
                if (empty($linea['cod_doc_sustento']))
                    $errores[] = "Línea {$n}: el código del documento de sustento es obligatorio.";
                if (empty($linea['num_doc_sustento']))
                    $errores[] = "Línea {$n}: el número del documento de sustento es obligatorio.";
                if (empty($linea['fecha_emision_doc_sustento']))
                    $errores[] = "Línea {$n}: la fecha del documento de sustento es obligatoria.";
                if (empty($linea['codigo_retencion']))
                    $errores[] = "Línea {$n}: el código de retención es obligatorio.";
                if (!isset($linea['base_imponible']) || (float)$linea['base_imponible'] <= 0)
                    $errores[] = "Línea {$n}: la base imponible debe ser mayor a 0.";
                if (!isset($linea['porcentaje_retencion']) || (float)$linea['porcentaje_retencion'] <= 0)
                    $errores[] = "Línea {$n}: el porcentaje de retención debe ser mayor a 0.";
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
