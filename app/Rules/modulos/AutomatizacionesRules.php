<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class AutomatizacionesRules
{
    private const FRECUENCIAS = ['minutos', 'horas', 'diario', 'semanal', 'mensual', 'cron_personalizado'];
    private const ESTADOS     = ['activo', 'inactivo'];
    private const MODULOS     = ['facturas', 'suscripciones', 'compras', 'inventario', 'general'];

    public function validar(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre de la automatización es obligatorio.');
        }

        if (trim($data['modulo'] ?? '') === '') {
            throw new \InvalidArgumentException('El módulo es obligatorio.');
        }

        if (trim($data['accion'] ?? '') === '') {
            throw new \InvalidArgumentException('La acción es obligatoria.');
        }

        $frecuencia = $data['frecuencia_tipo'] ?? '';
        if (!in_array($frecuencia, self::FRECUENCIAS, true)) {
            throw new \InvalidArgumentException('Tipo de frecuencia no válido.');
        }

        if ($frecuencia === 'cron_personalizado') {
            if (trim($data['cron_expression'] ?? '') === '') {
                throw new \InvalidArgumentException('Debe ingresar una expresión cron personalizada.');
            }
            if (!$this->validarCronExpression($data['cron_expression'])) {
                throw new \InvalidArgumentException('La expresión cron no tiene un formato válido (5 campos: min hora dom mes dow).');
            }
        } else {
            if (trim($data['frecuencia_valor'] ?? '') === '') {
                throw new \InvalidArgumentException('El valor de frecuencia es obligatorio.');
            }
        }

        $estado = $data['estado'] ?? '';
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new \InvalidArgumentException('Estado no válido. Use: activo o inactivo.');
        }
    }

    private function validarCronExpression(string $expr): bool
    {
        $partes = preg_split('/\s+/', trim($expr));
        return count($partes) === 5;
    }
}
