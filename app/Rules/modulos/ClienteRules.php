<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class ClienteRules
{
    // Códigos de identificador_comprador_vendedor (tipo=1).
    private const RUC = '04';
    private const CEDULA = '05';
    private const PASAPORTE = '06';

    /** Frecuencias de visita admitidas (mismo dominio que el CHECK de la tabla). */
    private const FRECUENCIAS_VISITA = [
        'SEMANAL'   => 'semanal',
        'QUINCENAL' => 'quincenal',
        'MENSUAL'   => 'mensual',
    ];

    /**
     * Valida los datos básicos de un cliente.
     * @throws \InvalidArgumentException si la validación falla.
     */
    public function validar(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }

        $tipoId = trim($data['tipo_id'] ?? '');
        if ($tipoId === '') {
            throw new \InvalidArgumentException('El tipo de identificación es obligatorio.');
        }

        $identificacion = trim($data['identificacion'] ?? '');
        if ($identificacion === '') {
            throw new \InvalidArgumentException('La identificación es obligatoria.');
        }

        if ($tipoId === self::CEDULA) {
            if (!preg_match('/^[0-9]{10}$/', $identificacion)) {
                throw new \InvalidArgumentException('La cédula debe tener exactamente 10 dígitos numéricos.');
            }
        } elseif ($tipoId === self::RUC) {
            if (!preg_match('/^[0-9]{13}$/', $identificacion)) {
                throw new \InvalidArgumentException('El RUC debe tener exactamente 13 dígitos numéricos.');
            }
            $sufijo = substr($identificacion, -3);
            if ($sufijo !== '001' && $sufijo !== '002') {
                throw new \InvalidArgumentException('El RUC debe terminar en 001 o 002.');
            }
        } elseif ($tipoId === self::PASAPORTE) {
            if (mb_strlen($identificacion) > 20) {
                throw new \InvalidArgumentException('El pasaporte no puede exceder 20 caracteres.');
            }
        } elseif (mb_strlen($identificacion) > 30) {
            throw new \InvalidArgumentException('El número de identificación no puede exceder 30 caracteres.');
        }

        if (trim($data['email'] ?? '') === '') {
            throw new \InvalidArgumentException('El correo electrónico es obligatorio.');
        }

        $emails = array_map('trim', explode(',', trim($data['email'])));
        foreach ($emails as $email) {
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("El formato del correo electrónico '{$email}' no es válido.");
            }
        }

        $this->validarVisitas($data);
    }

    /**
     * Valida la pauta de visita del vendedor (días, frecuencia, semanas, ventana
     * horaria). Toda la sección es opcional: un cliente sin ruta definida es
     * válido. Lo que se valida es que, si se define, quede coherente.
     *
     * Las claves pueden no venir (p. ej. la replicación entre empresas arma su
     * propio arreglo), así que todo se lee con ?? y se trata como "sin definir".
     */
    private function validarVisitas(array $data): void
    {
        $dias    = $data['dias_visita'] ?? null;
        $semanas = $data['semanas_visita'] ?? null;
        $frec    = $data['frecuencia_visita'] ?? null;

        if ($dias !== null && !is_array($dias)) {
            throw new \InvalidArgumentException('Los días de visita no tienen un formato válido.');
        }
        if ($semanas !== null && !is_array($semanas)) {
            throw new \InvalidArgumentException('Las semanas de visita no tienen un formato válido.');
        }

        foreach ((array) $dias as $d) {
            if (!is_numeric($d) || (int) $d < 1 || (int) $d > 7) {
                throw new \InvalidArgumentException('Los días de visita deben estar entre lunes y domingo.');
            }
        }
        foreach ((array) $semanas as $s) {
            if (!is_numeric($s) || (int) $s < 1 || (int) $s > 5) {
                throw new \InvalidArgumentException('Las semanas de visita deben estar entre 1 y 5.');
            }
        }

        if ($frec !== null && $frec !== '' && !isset(self::FRECUENCIAS_VISITA[strtoupper((string) $frec)])) {
            throw new \InvalidArgumentException('La frecuencia de visita seleccionada no es válida.');
        }

        $tieneDias = !empty($dias);
        $frecNorm  = strtoupper(trim((string) $frec));

        // Sin días no hay ruta: la frecuencia y las semanas quedarían huérfanas.
        if (!$tieneDias && ($frecNorm !== '' || !empty($semanas))) {
            throw new \InvalidArgumentException('Seleccione al menos un día de visita para definir la frecuencia.');
        }

        if ($tieneDias && $frecNorm === '') {
            throw new \InvalidArgumentException('Indique la frecuencia de visita (semanal, quincenal o mensual).');
        }

        // Quincenal/mensual solo tienen sentido si se dice en qué semanas del mes.
        if ($tieneDias && $frecNorm !== 'SEMANAL' && $frecNorm !== '' && empty($semanas)) {
            $etiqueta = self::FRECUENCIAS_VISITA[$frecNorm] ?? 'esta frecuencia';
            throw new \InvalidArgumentException("Con frecuencia {$etiqueta} debe indicar al menos una semana del mes.");
        }

        $desde = trim((string) ($data['hora_visita_desde'] ?? ''));
        $hasta = trim((string) ($data['hora_visita_hasta'] ?? ''));

        foreach (['hora_visita_desde' => $desde, 'hora_visita_hasta' => $hasta] as $hora) {
            if ($hora !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $hora)) {
                throw new \InvalidArgumentException('El horario de visita debe tener el formato HH:MM.');
            }
        }

        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            throw new \InvalidArgumentException('La hora inicial del horario de visita no puede ser mayor que la final.');
        }

        $orden = $data['orden_visita'] ?? null;
        if ($orden !== null && $orden !== '' && (!is_numeric($orden) || (int) $orden < 0)) {
            throw new \InvalidArgumentException('El orden de visita debe ser un número mayor o igual a cero.');
        }
    }
}
