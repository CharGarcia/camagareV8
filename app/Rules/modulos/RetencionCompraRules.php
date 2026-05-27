<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class RetencionCompraRules
{
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa']))   $errores[] = 'El identificador de empresa es obligatorio.';
        if (empty($data['id_proveedor'])) $errores[] = 'El proveedor es obligatorio.';
        if (empty($data['fecha_emision'])) $errores[] = 'La fecha de emisión es obligatoria.';

        if (empty($data['tipo_doc_sustento'])) {
            $errores[] = 'El tipo de documento de sustento es obligatorio.';
        } elseif (!in_array($data['tipo_doc_sustento'], ['01','03','05'], true)) {
            $errores[] = 'El tipo de documento de sustento no es válido.';
        }

        if (empty($data['num_doc_sustento'])) {
            $errores[] = 'El número del documento de sustento es obligatorio.';
        }

        if (empty($data['fecha_emision_doc_sustento'])) {
            $errores[] = 'La fecha de emisión del documento de sustento es obligatoria.';
        }

        // Validación de plazo máximo (5 días según normativa)
        // Se omite si ya está autorizado (importación de SRI)
        if (!empty($data['fecha_emision']) && !empty($data['fecha_emision_doc_sustento']) && ($data['estado'] ?? '') !== 'autorizado') {
            $fRet = new \DateTime($data['fecha_emision']);
            $fDoc = new \DateTime($data['fecha_emision_doc_sustento']);
            
            if ($fRet < $fDoc) {
                $errores[] = 'La fecha de la retención no puede ser anterior a la del documento de sustento.';
            } else {
                $diff = $fRet->diff($fDoc);
                if ($diff->days > 5) {
                    $errores[] = 'La retención debe emitirse máximo 5 días después de la fecha de emisión de la compra.';
                }
            }
        }

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
                if (empty($linea['codigo_retencion']))
                    $errores[] = "Línea {$n}: el código de retención es obligatorio.";
                if (!isset($linea['base_imponible']) || (float)$linea['base_imponible'] <= 0)
                    $errores[] = "Línea {$n}: la base imponible debe ser mayor a 0.";
                if (!isset($linea['porcentaje_retener']) || (float)$linea['porcentaje_retener'] <= 0)
                    $errores[] = "Línea {$n}: el porcentaje de retención debe ser mayor a 0.";
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
