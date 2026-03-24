<?php
/**
 * Modelo Salario - CRUD de salarios por año
 * Tabla: salarios
 * Campos: ano, sbu, hora_normal, hora_nocturna, hora_suplementaria, hora_extraordinaria, fondo_reserva, aporte_personal, aporte_patronal, ext_conyugue, adicional, status
 * El año (ano) no puede repetirse.
 */

declare(strict_types=1);

namespace App\models;

class Salario extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['ano', 'sbu', 'status'];

    /**
     * Lista todos los salarios con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'ano', string $ordenDir = 'DESC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'ano';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (ano LIKE '%{$b}%' OR CAST(sbu AS CHAR) LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_salario AS id, ano, sbu, hora_normal, hora_nocturna, hora_suplementaria, hora_extraordinaria, fondo_reserva, aporte_personal, aporte_patronal, ext_conyugue, adicional, status FROM salarios{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, ano, sbu, hora_normal, hora_nocturna, hora_suplementaria, hora_extraordinaria, fondo_reserva, aporte_personal, aporte_patronal, ext_conyugue, adicional, status FROM salarios{$where} ORDER BY {$col} {$dir}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->query($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /**
     * Verifica si ya existe un salario con el año dado.
     */
    public function existeAno(int $ano, ?int $excluirId = null): bool
    {
        $excluir = $excluirId !== null ? ' AND id_salario != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM salarios WHERE ano = " . (int) $ano . $excluir,
            "SELECT 1 FROM salarios WHERE ano = " . (int) $ano . $excluirAlt,
        ];
        foreach ($queries as $sql) {
            try {
                return !empty($this->query($sql));
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Elimina un salario
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM salarios WHERE id_salario = {$id}",
            "DELETE FROM salarios WHERE id = {$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Crea un salario
     */
    public function crear(
        int $ano,
        float $sbu,
        float $horaNormal,
        float $horaNocturna,
        float $horaSuplementaria,
        float $horaExtraordinaria,
        float $fondoReserva,
        float $aportePersonal,
        float $aportePatronal,
        float $extConyugue,
        float $adicional,
        int $status
    ): int {
        $sbu = round((float) $sbu, 2);
        $horaNormal = round((float) $horaNormal, 2);
        $horaNocturna = round((float) $horaNocturna, 2);
        $horaSuplementaria = round((float) $horaSuplementaria, 2);
        $horaExtraordinaria = round((float) $horaExtraordinaria, 2);
        $fondoReserva = round((float) $fondoReserva, 2);
        $aportePersonal = round((float) $aportePersonal, 2);
        $aportePatronal = round((float) $aportePatronal, 2);
        $extConyugue = round((float) $extConyugue, 2);
        $adicional = round((float) $adicional, 2);
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO salarios (ano, sbu, hora_normal, hora_nocturna, hora_suplementaria, hora_extraordinaria, fondo_reserva, aporte_personal, aporte_patronal, ext_conyugue, adicional, status) " .
            "VALUES (" . (int) $ano . ", {$sbu}, {$horaNormal}, {$horaNocturna}, {$horaSuplementaria}, {$horaExtraordinaria}, {$fondoReserva}, {$aportePersonal}, {$aportePatronal}, {$extConyugue}, {$adicional}, {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un salario
     */
    public function actualizar(
        int $id,
        int $ano,
        float $sbu,
        float $horaNormal,
        float $horaNocturna,
        float $horaSuplementaria,
        float $horaExtraordinaria,
        float $fondoReserva,
        float $aportePersonal,
        float $aportePatronal,
        float $extConyugue,
        float $adicional,
        int $status
    ): bool {
        $id = (int) $id;
        $sbu = round((float) $sbu, 2);
        $horaNormal = round((float) $horaNormal, 2);
        $horaNocturna = round((float) $horaNocturna, 2);
        $horaSuplementaria = round((float) $horaSuplementaria, 2);
        $horaExtraordinaria = round((float) $horaExtraordinaria, 2);
        $fondoReserva = round((float) $fondoReserva, 2);
        $aportePersonal = round((float) $aportePersonal, 2);
        $aportePatronal = round((float) $aportePatronal, 2);
        $extConyugue = round((float) $extConyugue, 2);
        $adicional = round((float) $adicional, 2);
        $st = $status ? 1 : 0;
        $queries = [
            "UPDATE salarios SET ano=" . (int) $ano . ", sbu={$sbu}, hora_normal={$horaNormal}, hora_nocturna={$horaNocturna}, hora_suplementaria={$horaSuplementaria}, hora_extraordinaria={$horaExtraordinaria}, fondo_reserva={$fondoReserva}, aporte_personal={$aportePersonal}, aporte_patronal={$aportePatronal}, ext_conyugue={$extConyugue}, adicional={$adicional}, status={$st} WHERE id_salario={$id}",
            "UPDATE salarios SET ano=" . (int) $ano . ", sbu={$sbu}, hora_normal={$horaNormal}, hora_nocturna={$horaNocturna}, hora_suplementaria={$horaSuplementaria}, hora_extraordinaria={$horaExtraordinaria}, fondo_reserva={$fondoReserva}, aporte_personal={$aportePersonal}, aporte_patronal={$aportePatronal}, ext_conyugue={$extConyugue}, adicional={$adicional}, status={$st} WHERE id={$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}
