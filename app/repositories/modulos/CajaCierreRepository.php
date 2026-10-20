<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AmbienteReporte;
use App\repositories\BaseRepository;
use PDO;

/**
 * Apertura y cierre de caja por día del Resumen Diario (opción «Caja: Cierre de caja»).
 * Tablas caja_cierres_diarios y caja_cierres_diarios_detalle
 * (database/20261020_caja_cierres_diarios.sql). Una caja por empresa + día + alcance:
 * un usuario (lo que él registró) o toda la empresa (id_usuario NULL).
 * Toda consulta filtra id_empresa + eliminado = false.
 */
class CajaCierreRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('caja_cierres_diarios');
    }

    /** ¿Ya se aplicó database/20261020_caja_cierres_diarios.sql? Sin las tablas, la opción avisa. */
    public function tablasDisponibles(): bool
    {
        static $ok = null;
        return $ok ??= (bool) $this->db->query(
            "SELECT to_regclass('public.caja_cierres_diarios') IS NOT NULL
                AND to_regclass('public.caja_cierres_diarios_detalle') IS NOT NULL"
        )->fetchColumn();
    }

    /**
     * Candado de la caja de un día y alcance (§8): abrir, cerrar y reabrir leen el estado y
     * escriben; dos peticiones a la vez no deben pisarse. Se libera al COMMIT/ROLLBACK.
     */
    public function lockAlcance(int $idEmpresa, string $fecha, ?int $idUsuario): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('caja_cierre:' || :e || ':' || :f || ':' || :u))");
        $st->execute([':e' => $idEmpresa, ':f' => $fecha, ':u' => $idUsuario ?? 0]);
    }

    /** Condición del alcance: un usuario o toda la empresa (id_usuario NULL). */
    private static function alcance(string $a, ?int $idUsuario, array &$p): string
    {
        if ($idUsuario === null) {
            return "{$a}.id_usuario IS NULL";
        }
        $p[':id_usuario'] = $idUsuario;
        return "{$a}.id_usuario = :id_usuario";
    }

    /** Caja del día y alcance, con los nombres de quién abrió y quién cerró. */
    public function getCierre(int $idEmpresa, string $fecha, ?int $idUsuario): ?array
    {
        $p = [':e' => $idEmpresa, ':f' => $fecha];
        $w = self::alcance('c', $idUsuario, $p);
        $st = $this->db->prepare(
            "SELECT c.*, COALESCE(ua.nombre, '') AS abierta_por_nombre, COALESCE(uc.nombre, '') AS cerrada_por_nombre
               FROM caja_cierres_diarios c
               LEFT JOIN usuarios ua ON ua.id = c.abierta_por
               LEFT JOIN usuarios uc ON uc.id = c.cerrada_por
              WHERE c.id_empresa = :e AND c.eliminado = false AND c.fecha = :f AND {$w}"
        );
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Último cierre (cerrado) del mismo alcance antes del día: de él arranca el saldo inicial. */
    public function getUltimoCerradoAntes(int $idEmpresa, string $fecha, ?int $idUsuario): ?array
    {
        $p = [':e' => $idEmpresa, ':f' => $fecha];
        $w = self::alcance('c', $idUsuario, $p);
        $st = $this->db->prepare(
            "SELECT c.* FROM caja_cierres_diarios c
              WHERE c.id_empresa = :e AND c.eliminado = false AND c.estado = 'cerrada' AND c.fecha < :f AND {$w}
              ORDER BY c.fecha DESC, c.id DESC LIMIT 1"
        );
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Detalle por forma de pago de una caja, ordenado por nombre. */
    public function getDetalle(int $idCierre, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM caja_cierres_diarios_detalle
              WHERE id_cierre = :c AND id_empresa = :e AND eliminado = false
              ORDER BY forma, id"
        );
        $st->execute([':c' => $idCierre, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Neto por forma de pago (ingresos − egresos ± traslados) de los días ENTRE dos fechas,
     * sin incluirlas, para el alcance (usuario o toda la empresa). Mismas reglas que el
     * saldo del resumen: producción, sin anulados ni pagos/cheques anulados.
     *
     * @return array<int, float> id_forma => neto
     */
    public function getNetoPorFormaEntre(int $idEmpresa, string $desdeExcl, string $hastaExcl, ?int $idUsuario): array
    {
        $p   = [':e' => $idEmpresa, ':desde' => $desdeExcl, ':hasta' => $hastaExcl];
        $amb = AmbienteReporte::literal();
        $uIng = $uEgr = $uTr = '';
        if ($idUsuario !== null) {
            $p[':u'] = $idUsuario;
            $uIng = ' AND COALESCE(c.created_by, c.id_usuario) = :u';
            $uEgr = ' AND c.created_by = :u';
            $uTr  = ' AND t.created_by = :u';
        }
        $traslados = (new ReporteResumenDiarioRepository())->tablasCajaDisponibles() ? "
                UNION ALL
                SELECT t.id_forma_destino, t.valor FROM caja_traslados t
                 WHERE t.id_empresa = :e AND t.eliminado = false AND t.fecha > :desde AND t.fecha < :hasta{$uTr}
                UNION ALL
                SELECT t.id_forma_origen, -t.valor FROM caja_traslados t
                 WHERE t.id_empresa = :e AND t.eliminado = false AND t.fecha > :desde AND t.fecha < :hasta{$uTr}" : '';

        $st = $this->db->prepare("
            SELECT m.id_forma, SUM(m.valor) AS neto FROM (
                SELECT pg.id_forma_cobro AS id_forma, pg.monto AS valor
                  FROM ingresos_pagos pg
                  JOIN ingresos_cabecera c ON c.id = pg.id_ingreso
                 WHERE c.id_empresa = :e AND c.eliminado = false AND LOWER(c.estado) <> 'anulado'
                   AND c.tipo_ambiente = {$amb} AND c.fecha_emision > :desde AND c.fecha_emision < :hasta{$uIng}
                UNION ALL
                SELECT pg.id_forma_pago, -pg.monto
                  FROM egresos_pagos pg
                  JOIN egresos_cabecera c ON c.id = pg.id_egreso
                 WHERE c.id_empresa = :e AND c.eliminado = false AND LOWER(c.estado) <> 'anulado'
                   AND c.tipo_ambiente = {$amb} AND c.fecha_emision > :desde AND c.fecha_emision < :hasta{$uEgr}
                   AND pg.eliminado = false AND COALESCE(pg.estado_cheque, 'vigente') <> 'anulado'{$traslados}
            ) m
             WHERE m.id_forma IS NOT NULL
             GROUP BY m.id_forma");
        $st->execute($p);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_forma']] = round((float) $r['neto'], 2);
        }
        return $out;
    }

    /** Usuarios asignados a la empresa (selector «Usuario»), más los indicados (p. ej. el actual). */
    public function getUsuariosEmpresa(int $idEmpresa, array $incluir = []): array
    {
        $p   = [':e' => $idEmpresa];
        $phs = [];
        foreach (array_values(array_unique(array_map('intval', $incluir))) as $i => $id) {
            $phs[] = ":i{$i}";
            $p[":i{$i}"] = $id;
        }
        $extra = $phs ? ' OR u.id IN (' . implode(', ', $phs) . ')' : '';
        $st = $this->db->prepare(
            "SELECT u.id, u.nombre FROM usuarios u
              WHERE u.id IN (SELECT ea.id_usuario FROM empresa_asignada ea WHERE ea.id_empresa = :e){$extra}
              ORDER BY u.nombre"
        );
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertarCierre(int $idEmpresa, string $fecha, ?int $idUsuario, float $totalInicial, string $obs, int $idUsuarioSesion): int
    {
        $st = $this->db->prepare(
            "INSERT INTO caja_cierres_diarios (id_empresa, fecha, id_usuario, estado, fecha_apertura, abierta_por,
                                               observaciones_apertura, total_inicial, created_by, updated_by)
             VALUES (:e, :f, :u, 'abierta', NOW(), :s, :obs, :tot, :s, :s) RETURNING id"
        );
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':f', $fecha);
        $st->bindValue(':u', $idUsuario, $idUsuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':s', $idUsuarioSesion, PDO::PARAM_INT);
        $st->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':tot', $totalInicial);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    /** Una fila de detalle. $d: id_forma_pago (0 = sin forma), forma, saldo_inicial y, al cerrar, el resto. */
    public function insertarDetalle(int $idCierre, int $idEmpresa, array $d, int $idUsuarioSesion): void
    {
        $st = $this->db->prepare(
            "INSERT INTO caja_cierres_diarios_detalle (id_cierre, id_empresa, id_forma_pago, forma, saldo_inicial,
                         ingresos, egresos, traslados, esperado, contado, diferencia, created_by, updated_by)
             VALUES (:c, :e, :fp, :forma, :ini, :ing, :egr, :tr, :esp, :cont, :dif, :s, :s)"
        );
        $idForma = (int) ($d['id_forma_pago'] ?? 0);
        $st->bindValue(':c', $idCierre, PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':fp', $idForma > 0 ? $idForma : null, $idForma > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':forma', mb_substr((string) ($d['forma'] ?? ''), 0, 150));
        $st->bindValue(':ini', (float) ($d['saldo_inicial'] ?? 0));
        foreach ([':ing' => 'ingresos', ':egr' => 'egresos', ':tr' => 'traslados', ':esp' => 'esperado', ':cont' => 'contado', ':dif' => 'diferencia'] as $ph => $k) {
            $v = $d[$k] ?? null;
            $st->bindValue($ph, $v === null ? null : (string) $v, $v === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $st->bindValue(':s', $idUsuarioSesion, PDO::PARAM_INT);
        $st->execute();
    }

    /** Quita (lógicamente) todo el detalle de una caja: al cerrar se vuelve a escribir completo. */
    public function eliminarDetalle(int $idCierre, int $idEmpresa, int $idUsuarioSesion): void
    {
        $st = $this->db->prepare(
            "UPDATE caja_cierres_diarios_detalle SET eliminado = true, deleted_at = NOW(), deleted_by = :s, updated_at = NOW(), updated_by = :s
              WHERE id_cierre = :c AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':c' => $idCierre, ':e' => $idEmpresa, ':s' => $idUsuarioSesion]);
    }

    public function marcarCerrada(int $id, int $idEmpresa, array $tot, string $obs, int $idUsuarioSesion): void
    {
        $st = $this->db->prepare(
            "UPDATE caja_cierres_diarios
                SET estado = 'cerrada', fecha_cierre = NOW(), cerrada_por = :s, observaciones_cierre = :obs,
                    total_esperado = :esp, total_contado = :cont, total_diferencia = :dif,
                    updated_at = NOW(), updated_by = :s
              WHERE id = :id AND id_empresa = :e AND eliminado = false AND estado = 'abierta'"
        );
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':s', $idUsuarioSesion, PDO::PARAM_INT);
        $st->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':esp', (string) $tot['esperado']);
        $st->bindValue(':cont', (string) $tot['contado']);
        $st->bindValue(':dif', (string) $tot['diferencia']);
        $st->execute();
    }

    /** Vuelve a abrir una caja cerrada: borra el arqueo, conserva los saldos iniciales. */
    public function marcarAbierta(int $id, int $idEmpresa, int $idUsuarioSesion): void
    {
        $st = $this->db->prepare(
            "UPDATE caja_cierres_diarios
                SET estado = 'abierta', fecha_cierre = NULL, cerrada_por = NULL, observaciones_cierre = NULL,
                    total_esperado = NULL, total_contado = NULL, total_diferencia = NULL,
                    updated_at = NOW(), updated_by = :s
              WHERE id = :id AND id_empresa = :e AND eliminado = false AND estado = 'cerrada'"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':s' => $idUsuarioSesion]);
        $st = $this->db->prepare(
            "UPDATE caja_cierres_diarios_detalle
                SET ingresos = NULL, egresos = NULL, traslados = NULL, esperado = NULL, contado = NULL, diferencia = NULL,
                    updated_at = NOW(), updated_by = :s
              WHERE id_cierre = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':s' => $idUsuarioSesion]);
    }
}
