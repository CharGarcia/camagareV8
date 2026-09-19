<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Reglas de las solicitudes de vacaciones. Los mensajes los lee el empleado en la
 * página pública, así que dicen qué corregir sin jerga del sistema.
 */
class VacacionSolicitudRules
{
    /** Días de margen hacia atrás: se permite pedir desde ayer, no vacaciones de hace meses. */
    public const DIAS_RETROACTIVOS = 30;

    /** Correo al que se le manda el enlace. */
    public function validarCorreo(string $correo): void
    {
        if (trim($correo) === '') {
            throw new Exception('Indique el correo del empleado para enviarle la solicitud.');
        }
        if (!filter_var(trim($correo), FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo no tiene un formato válido.');
        }
    }

    /**
     * Lo que llena el empleado en el formulario público.
     *
     * @param float $saldo Días que le quedan según el sistema (informativo si no hay fecha de ingreso)
     */
    public function validarSolicitud(array $data, float $saldo): void
    {
        $desde = trim((string) ($data['fecha_desde'] ?? ''));
        $hasta = trim((string) ($data['fecha_hasta'] ?? ''));

        if ($desde === '' || $hasta === '') {
            throw new Exception('Indique desde y hasta qué día quiere sus vacaciones.');
        }
        $tsDesde = strtotime($desde);
        $tsHasta = strtotime($hasta);
        if ($tsDesde === false || $tsHasta === false) {
            throw new Exception('Las fechas no son válidas.');
        }
        if ($tsHasta < $tsDesde) {
            throw new Exception('La fecha hasta no puede ser anterior a la fecha desde.');
        }
        if ($tsDesde < strtotime('-' . self::DIAS_RETROACTIVOS . ' days', strtotime(date('Y-m-d')))) {
            throw new Exception('La fecha desde es demasiado antigua. Pida sus vacaciones con una fecha actual y comente el caso con Recursos Humanos.');
        }

        $dias = (float) ($data['dias_solicitados'] ?? 0);
        if ($dias <= 0) {
            throw new Exception('Los días solicitados deben ser mayores a cero.');
        }
        // Tope duro por el rango pedido: no se pueden pedir más días de los que abarca.
        $diasRango = (int) floor(($tsHasta - $tsDesde) / 86400) + 1;
        if ($dias > $diasRango) {
            throw new Exception("Entre esas fechas hay {$diasRango} día(s): no puede solicitar {$dias}.");
        }
        if ($saldo > 0 && $dias > $saldo) {
            throw new Exception('Solo tiene ' . rtrim(rtrim(number_format($saldo, 2, '.', ''), '0'), '.') . ' día(s) de vacaciones disponibles.');
        }

        if (mb_strlen((string) ($data['motivo'] ?? '')) > 500) {
            throw new Exception('El motivo es demasiado largo (máximo 500 caracteres).');
        }
        if (mb_strlen((string) ($data['contacto'] ?? '')) > 150) {
            throw new Exception('El contacto es demasiado largo (máximo 150 caracteres).');
        }
    }

    /** Resolución desde el sistema. */
    public function validarResolucion(string $estado, string $comentario): void
    {
        if (!in_array($estado, ['aprobada', 'rechazada'], true)) {
            throw new Exception('Indique si la solicitud se aprueba o se rechaza.');
        }
        if ($estado === 'rechazada' && trim($comentario) === '') {
            throw new Exception('Escriba el motivo del rechazo: el empleado debe saber por qué.');
        }
        if (mb_strlen($comentario) > 500) {
            throw new Exception('El comentario es demasiado largo (máximo 500 caracteres).');
        }
    }
}
