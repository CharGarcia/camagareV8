<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Proyección de gastos personales del empleado (formulario SRI-GP), por año.
 *
 * Es el insumo de la deducción aplicada al calcular la retención de Impuesto a
 * la Renta en relación de dependencia: se descuenta lo que el empleado proyectó,
 * limitado al tope legal del año (ver ImpuestoRentaEmpleadoService).
 */
class GastoPersonalRepository extends BaseRepository
{
    /** Rubros del formulario, en el orden en que se muestran. */
    public const RUBROS = ['vivienda', 'salud', 'educacion', 'alimentacion', 'vestimenta', 'turismo'];

    public function __construct()
    {
        parent::__construct('empleado_gastos_personales');
    }

    /** Todas las proyecciones del empleado (histórico por año, más reciente primero). */
    public function getPorEmpleado(int $idEmpleado, int $idEmpresa): array
    {
        try {
            $st = $this->db->prepare(
                "SELECT id, anio, vivienda, salud, educacion, alimentacion, vestimenta, turismo,
                        total_proyectado, numero_cargas_familiares, caso_especial,
                        fecha_presentacion, observacion
                 FROM empleado_gastos_personales
                 WHERE id_empleado = :e AND id_empresa = :c AND eliminado = false
                 ORDER BY anio DESC"
            );
            $st->execute([':e' => $idEmpleado, ':c' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // La tabla puede no existir todavía (migración pendiente): no romper la ficha.
            return [];
        }
    }

    /** Proyección vigente del empleado para un año (con cargas y caso especial). null si no presentó. */
    public function getPorAnio(int $idEmpleado, int $idEmpresa, int $anio): ?array
    {
        try {
            $st = $this->db->prepare(
                "SELECT total_proyectado, numero_cargas_familiares, caso_especial
                 FROM empleado_gastos_personales
                 WHERE id_empleado = :e AND id_empresa = :c AND anio = :a AND eliminado = false"
            );
            $st->execute([':e' => $idEmpleado, ':c' => $idEmpresa, ':a' => $anio]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Proyecciones de varios empleados para un año, en UNA consulta.
     * Devuelve [id_empleado => ['total_proyectado', 'numero_cargas_familiares', 'caso_especial']].
     */
    public function getProyeccionesMasivo(int $idEmpresa, array $idsEmpleado, int $anio): array
    {
        if (empty($idsEmpleado)) {
            return [];
        }

        try {
            $ph = implode(',', array_fill(0, count($idsEmpleado), '?'));
            $st = $this->db->prepare(
                "SELECT id_empleado, total_proyectado, numero_cargas_familiares, caso_especial
                 FROM empleado_gastos_personales
                 WHERE id_empresa = ? AND anio = ? AND eliminado = false
                   AND id_empleado IN ($ph)"
            );
            $st->execute(array_merge([$idEmpresa, $anio], array_map('intval', $idsEmpleado)));

            $map = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[(int) $r['id_empleado']] = $r;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Total proyectado por el empleado para un año. null si no presentó proyección. */
    public function getTotalAnio(int $idEmpleado, int $idEmpresa, int $anio): ?float
    {
        try {
            $st = $this->db->prepare(
                "SELECT total_proyectado FROM empleado_gastos_personales
                 WHERE id_empleado = :e AND id_empresa = :c AND anio = :a AND eliminado = false"
            );
            $st->execute([':e' => $idEmpleado, ':c' => $idEmpresa, ':a' => $anio]);
            $v = $st->fetchColumn();
            return $v === false ? null : (float) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Totales proyectados de varios empleados para un año, en UNA consulta.
     * Devuelve [id_empleado => total]. Los empleados sin proyección no aparecen.
     */
    public function getTotalesMasivo(int $idEmpresa, array $idsEmpleado, int $anio): array
    {
        if (empty($idsEmpleado)) {
            return [];
        }

        try {
            $ph = implode(',', array_fill(0, count($idsEmpleado), '?'));
            $st = $this->db->prepare(
                "SELECT id_empleado, total_proyectado
                 FROM empleado_gastos_personales
                 WHERE id_empresa = ? AND anio = ? AND eliminado = false
                   AND id_empleado IN ($ph)"
            );
            $st->execute(array_merge([$idEmpresa, $anio], array_map('intval', $idsEmpleado)));

            $map = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[(int) $r['id_empleado']] = (float) $r['total_proyectado'];
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Reemplaza las proyecciones del empleado por las recibidas (eliminación
     * lógica de las anteriores + inserción). Mismo patrón que rubros y períodos.
     */
    public function sync(int $idEmpleado, int $idEmpresa, array $filas, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE empleado_gastos_personales
             SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
             WHERE id_empleado = :e AND id_empresa = :c AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':e' => $idEmpleado, ':c' => $idEmpresa]);

        if (empty($filas)) {
            return;
        }

        $sql = "INSERT INTO empleado_gastos_personales
                    (id_empresa, id_empleado, anio, vivienda, salud, educacion, alimentacion,
                     vestimenta, turismo, total_proyectado, numero_cargas_familiares,
                     caso_especial, fecha_presentacion, observacion, created_by, updated_by)
                VALUES (:c, :e, :a, :vi, :sa, :ed, :al, :ve, :tu, :to, :cf, :ce, :fp, :ob, :u, :u)";
        $ins = $this->db->prepare($sql);

        $vistos = [];
        foreach ($filas as $f) {
            $anio = (int) ($f['anio'] ?? 0);
            if ($anio < 2000 || $anio > 2100 || isset($vistos[$anio])) {
                continue; // año inválido o repetido: el índice único solo admite uno por año
            }
            $vistos[$anio] = true;

            $vals = [];
            $total = 0.0;
            foreach (self::RUBROS as $r) {
                $v = round(max(0.0, (float) ($f[$r] ?? 0)), 2);
                $vals[$r] = $v;
                $total += $v;
            }

            $fp = !empty($f['fecha_presentacion']) ? substr((string) $f['fecha_presentacion'], 0, 10) : null;
            $ob = trim((string) ($f['observacion'] ?? ''));

            $ins->execute([
                ':c'  => $idEmpresa,
                ':e'  => $idEmpleado,
                ':a'  => $anio,
                ':vi' => $vals['vivienda'],
                ':sa' => $vals['salud'],
                ':ed' => $vals['educacion'],
                ':al' => $vals['alimentacion'],
                ':ve' => $vals['vestimenta'],
                ':tu' => $vals['turismo'],
                ':to' => round($total, 2),
                ':cf' => max(0, (int) ($f['numero_cargas_familiares'] ?? 0)),
                ':ce' => in_array($f['caso_especial'] ?? false, [true, 1, '1', 'si', 'true'], true) ? 'true' : 'false',
                ':fp' => $fp,
                ':ob' => $ob !== '' ? $ob : null,
                ':u'  => $idUsuario,
            ]);
        }
    }
}
