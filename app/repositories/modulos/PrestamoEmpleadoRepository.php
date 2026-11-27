<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Préstamo Empresa registrado desde el modal de Novedades (modo «Préstamo empresa»):
 * cabecera `prestamos_empleado` + tabla de cuotas `prestamos_empleado_cuotas`.
 * Cada cuota por cobrar es además una novedad tipo 9 con `novedades.id_prestamo`
 * (database/2026-11-27_prestamos_empleado.sql).
 */
class PrestamoEmpleadoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('prestamos_empleado');
    }

    /** ¿Ya se aplicó database/2026-11-27_prestamos_empleado.sql? */
    public function estructuraDisponible(): bool
    {
        return $this->columnaExiste('novedades', 'id_prestamo')
            && $this->columnaExiste('prestamos_empleado_cuotas', 'id_novedad');
    }

    /** Empleado activo de la empresa (id, nombre, identificación) o null. */
    public function getEmpleadoActivo(int $idEmpleado, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, nombres_apellidos, identificacion
                                    FROM empleados
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false AND estado = 'activo'");
        $st->execute([':id' => $idEmpleado, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO prestamos_empleado (
                    id_empresa, id_empleado, fecha, monto, tasa_interes, valor_interes, total,
                    num_cuotas, mes_inicio, anio_inicio, aplica_en, desembolso, observacion, estado,
                    tipo_ambiente, eliminado, created_at, updated_at, created_by, updated_by
                ) VALUES (
                    :e, :emp, :fecha, :monto, :tasa, :interes, :total,
                    :n, :mes, :anio, :aplica, :desemb, :obs, 'activo',
                    COALESCE((SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e), '1'),
                    false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u, :u
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'       => (int) $d['id_empresa'],
            ':emp'     => (int) $d['id_empleado'],
            ':fecha'   => $d['fecha'],
            ':monto'   => round((float) $d['monto'], 2),
            ':tasa'    => round((float) ($d['tasa_interes'] ?? 0), 4),
            ':interes' => round((float) ($d['valor_interes'] ?? 0), 2),
            ':total'   => round((float) $d['total'], 2),
            ':n'       => (int) $d['num_cuotas'],
            ':mes'     => (int) $d['mes_inicio'],
            ':anio'    => (int) $d['anio_inicio'],
            ':aplica'  => (string) $d['aplica_en'],
            ':desemb'  => (string) $d['desembolso'],
            ':obs'     => $d['observacion'] !== '' ? $d['observacion'] : null,
            ':u'       => (int) $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function crearCuota(array $c): int
    {
        $sql = "INSERT INTO prestamos_empleado_cuotas (
                    id_empresa, id_prestamo, numero, periodo_mes, periodo_anio, capital, interes, valor,
                    cobrada_fuera, id_novedad, eliminado, created_at, updated_at, created_by, updated_by
                ) VALUES (
                    :e, :p, :num, :mes, :anio, :cap, :int, :valor,
                    :fuera, :nov, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u, :u
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => (int) $c['id_empresa'],
            ':p'     => (int) $c['id_prestamo'],
            ':num'   => (int) $c['numero'],
            ':mes'   => (int) $c['periodo_mes'],
            ':anio'  => (int) $c['periodo_anio'],
            ':cap'   => round((float) $c['capital'], 2),
            ':int'   => round((float) ($c['interes'] ?? 0), 2),
            ':valor' => round((float) $c['valor'], 2),
            // Booleano como texto: un false de PHP llega a pgsql como '' y falla.
            ':fuera' => !empty($c['cobrada_fuera']) ? 'true' : 'false',
            ':nov'   => isset($c['id_novedad']) ? (int) $c['id_novedad'] : null,
            ':u'     => (int) $c['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    /** Une la novedad recién creada con su préstamo. */
    public function vincularNovedad(int $idNovedad, int $idPrestamo, int $idEmpresa): void
    {
        $st = $this->db->prepare("UPDATE novedades SET id_prestamo = :p
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':p' => $idPrestamo, ':id' => $idNovedad, ':e' => $idEmpresa]);
    }

    /** Cabecera del préstamo (vigente) con el nombre del empleado. */
    public function getPrestamo(int $idPrestamo, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT p.*, e.nombres_apellidos AS empleado_nombre
                                    FROM prestamos_empleado p
                                    JOIN empleados e ON e.id = p.id_empleado
                                   WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false");
        $st->execute([':id' => $idPrestamo, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cuotas por cobrar del préstamo que siguen vigentes (novedades tipo 9 no eliminadas),
     * ordenadas por período.
     */
    public function getNovedadesVigentes(int $idPrestamo, int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT n.*
                                    FROM novedades n
                                   WHERE n.id_prestamo = :p AND n.id_empresa = :e AND n.eliminado = false
                                   ORDER BY n.periodo_anio, n.periodo_mes, n.id");
        $st->execute([':p' => $idPrestamo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tabla de cuotas del préstamo (incluidas las cobradas fuera del sistema). */
    public function getCuotas(int $idPrestamo, int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT * FROM prestamos_empleado_cuotas
                                   WHERE id_prestamo = :p AND id_empresa = :e AND eliminado = false
                                   ORDER BY numero");
        $st->execute([':p' => $idPrestamo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Eliminación lógica de la cabecera y de su tabla de cuotas. */
    public function eliminar(int $idPrestamo, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE prestamos_empleado
                                     SET eliminado = true, estado = 'anulado', deleted_at = CURRENT_TIMESTAMP, deleted_by = :u,
                                         updated_at = CURRENT_TIMESTAMP, updated_by = :u
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idPrestamo, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $st = $this->db->prepare("UPDATE prestamos_empleado_cuotas
                                     SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                                   WHERE id_prestamo = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idPrestamo, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    /**
     * Cuotas de Préstamo Empresa (tipo 9) que el empleado ya tiene vigentes en esos
     * períodos (de otros préstamos o registradas a mano): para avisar antes de guardar.
     * $periodos = [[anio, mes], …]. Devuelve filas periodo_anio, periodo_mes, valor.
     */
    public function getCuotasExistentesEnPeriodos(int $idEmpresa, int $idEmpleado, array $periodos): array
    {
        if (empty($periodos)) {
            return [];
        }
        $claves = array_map(fn($p) => ((int) $p[0]) * 100 + (int) $p[1], $periodos);
        $in = implode(',', array_unique($claves));
        $st = $this->db->prepare("SELECT periodo_anio, periodo_mes, valor
                                    FROM novedades
                                   WHERE id_empresa = :e AND id_empleado = :emp AND tipo_codigo = '9'
                                     AND eliminado = false AND estado = 'activo'
                                     AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                                     AND (periodo_anio * 100 + periodo_mes) IN ($in)
                                   ORDER BY periodo_anio, periodo_mes");
        $st->execute([':e' => $idEmpresa, ':emp' => $idEmpleado]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuánto de lo desembolsado por egreso (PRESTAMO9) le corresponde a ESTE préstamo.
     * El egreso de desembolso se registra por empleado (no por préstamo), así que se
     * reparte en orden de registro: primero cubre las cuotas por desembolsar de los
     * préstamos/cuotas anteriores del empleado y lo que sobra es de este préstamo.
     * Así un préstamo nuevo no queda bloqueado por el desembolso de uno anterior.
     */
    public function desembolsadoDelPrestamo(int $idEmpresa, int $idEmpleado, int $idPrestamo): float
    {
        $sql = "WITH propias AS (
                    SELECT MIN(id) AS primer_id FROM novedades
                     WHERE id_prestamo = :p AND id_empresa = :e
                ),
                egresos AS (
                    SELECT COALESCE(SUM(ed.monto_pagado), 0) AS pagado
                      FROM egresos_detalle ed
                      JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                     WHERE ed.tipo_documento = 'PRESTAMO9' AND ed.id_referencia_documento = :emp
                       AND ec.estado != 'anulado' AND ec.eliminado = false AND ed.eliminado = false
                       AND ec.id_empresa = :e
                ),
                anteriores AS (
                    SELECT COALESCE(SUM(n.valor), 0) AS deuda
                      FROM novedades n, propias
                     WHERE n.id_empresa = :e AND n.id_empleado = :emp AND n.tipo_codigo = '9'
                       AND n.eliminado = false AND n.estado = 'activo' AND n.desembolsado_migrado = false
                       AND n.id_prestamo IS DISTINCT FROM :p
                       AND n.id < COALESCE(propias.primer_id, 0)
                )
                SELECT GREATEST(egresos.pagado - anteriores.deuda, 0) FROM egresos, anteriores";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':p' => $idPrestamo, ':e' => $idEmpresa, ':emp' => $idEmpleado]);
            return round((float) $st->fetchColumn(), 2);
        } catch (\Throwable $e) {
            return 0.0; // egresos no disponible → sin desembolso
        }
    }
}
