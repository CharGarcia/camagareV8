<?php

declare(strict_types=1);

namespace App\Rules;

use InvalidArgumentException;

class TareaRules
{
    private const PERIODICIDADES_VALIDAS = [
        'semanal',
        'quincenal',
        'mensual',
        'trimestral',
        'semestral',
        'anual',
        'dos_anios',
        'tres_anios',
        'cuatro_anios',
        'cinco_anios',
    ];

    private const ESTADOS_VALIDOS = [
        'por_realizar',
        'realizada_continua',
        'realizada_finalizada',
        'vencida',
        'cancelada',
    ];

    public function validar(array $data): void
    {
        // Obligación
        $idObligacion = (int) ($data['id_obligacion'] ?? 0);
        if ($idObligacion <= 0) {
            throw new InvalidArgumentException('Debe seleccionar o crear una obligación.');
        }

        // Cliente nombre
        $clienteNombre = trim($data['cliente_nombre'] ?? '');
        if ($clienteNombre === '') {
            throw new InvalidArgumentException('El nombre del cliente es obligatorio.');
        }
        if (mb_strlen($clienteNombre, 'UTF-8') > 200) {
            throw new InvalidArgumentException('El nombre del cliente no puede superar los 200 caracteres.');
        }

        // Cliente correo
        $clienteCorreo = trim($data['cliente_correo'] ?? '');
        if ($clienteCorreo === '') {
            throw new InvalidArgumentException('El correo del cliente es obligatorio.');
        }
        // Validar lista de correos (permitidos: email1@test.com, email2@test.com)
        $emails = array_map('trim', explode(',', $clienteCorreo));
        foreach ($emails as $email) {
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("El correo '{$email}' no tiene un formato válido.");
            }
        }

        // Periodicidad
        $periodicidad = trim($data['periodicidad'] ?? '');
        if (!in_array($periodicidad, self::PERIODICIDADES_VALIDAS, true)) {
            throw new InvalidArgumentException('La periodicidad seleccionada no es válida.');
        }

        // Fecha tarea
        $fecha = trim($data['fecha_tarea'] ?? '');
        if ($fecha === '') {
            throw new InvalidArgumentException('La fecha de la tarea es obligatoria.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('La fecha de la tarea debe tener el formato AAAA-MM-DD.');
        }

        // Estado
        $estado = trim($data['estado'] ?? '');
        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException('El estado indicado no es válido.');
        }

        // Resumen obligatorio si estado es realizada_continua o realizada_finalizada
        if (in_array($estado, ['realizada_continua', 'realizada_finalizada'], true)) {
            $resumen = trim($data['resumen'] ?? '');
            if ($resumen === '') {
                throw new InvalidArgumentException('El resumen es obligatorio cuando la tarea fue realizada.');
            }
        }

        // Motivo obligatorio si estado es cancelada
        if ($estado === 'cancelada') {
            $motivo = trim($data['motivo_cancelacion'] ?? '');
            if ($motivo === '') {
                throw new InvalidArgumentException('El motivo de cancelación es obligatorio.');
            }
        }

        // Al menos un responsable
        $responsables = $data['responsables'] ?? [];
        if (empty($responsables)) {
            throw new InvalidArgumentException('Debe asignar al menos un responsable a la tarea.');
        }
    }

    /**
     * Calcula la próxima fecha según la periodicidad.
     */
    public static function calcularProximaFecha(string $fechaActual, string $periodicidad): string
    {
        $dt = new \DateTime($fechaActual);
        match ($periodicidad) {
            'semanal'       => $dt->modify('+1 week'),
            'quincenal'     => $dt->modify('+15 days'),
            'mensual'     => $dt->modify('+1 month'),
            'trimestral'  => $dt->modify('+3 months'),
            'semestral'   => $dt->modify('+6 months'),
            'anual'       => $dt->modify('+1 year'),
            'dos_anios'   => $dt->modify('+2 years'),
            'tres_anios'  => $dt->modify('+3 years'),
            'cuatro_anios'  => $dt->modify('+4 years'),
            'cinco_anios'   => $dt->modify('+5 years'),
            default       => $dt->modify('+1 month'),
        };
        return $dt->format('Y-m-d');
    }
}
