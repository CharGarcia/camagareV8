<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\models\CatalogoNovedades;
use Exception;

class NovedadRules
{
    public function validate(array $data): void
    {
        if (empty($data['id_empleado']) || (int) $data['id_empleado'] <= 0) {
            throw new Exception('Debe seleccionar un empleado.');
        }

        $tipo = trim((string) ($data['tipo_codigo'] ?? ''));
        if ($tipo === '' || !CatalogoNovedades::esTipoValido($tipo)) {
            throw new Exception('El tipo de novedad no es válido.');
        }

        $fecha = trim((string) ($data['fecha'] ?? ''));
        if ($fecha === '' || strtotime($fecha) === false) {
            throw new Exception('La fecha de la novedad es obligatoria y debe ser válida.');
        }

        $mes = (int) ($data['periodo_mes'] ?? 0);
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes del período debe estar entre 1 y 12.');
        }

        $anio = (int) ($data['periodo_anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año del período no es válido.');
        }

        // Valor: obligatorio y no negativo salvo en Aviso de salida (no lleva valor).
        if (!CatalogoNovedades::esAvisoSalida($tipo)) {
            $valor = (float) ($data['valor'] ?? 0);
            if ($valor < 0) {
                throw new Exception('El valor no puede ser negativo.');
            }
        }

        // Motivo: obligatorio y válido solo en Aviso de salida.
        if (CatalogoNovedades::esAvisoSalida($tipo)) {
            $motivo = trim((string) ($data['motivo_codigo'] ?? ''));
            if ($motivo === '' || !CatalogoNovedades::esMotivoValido($motivo)) {
                throw new Exception('Debe seleccionar un motivo de salida válido.');
            }
        }

        if (!empty($data['estado']) && !in_array($data['estado'], ['activo', 'anulado'], true)) {
            throw new Exception('El estado no es válido.');
        }

        if (!empty($data['aplica_en']) && !CatalogoNovedades::esAplicaEnValido((string) $data['aplica_en'])) {
            throw new Exception('La opción "Afecta a" no es válida.');
        }
    }
}
