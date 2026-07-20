<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

class DecimoTerceroRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('decimo_tercero_cabecera');
    }

    // ─── Listado de cabeceras ───────────────────────────────────────────────
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['anio', 'estado', 'fecha_limite_pago', 'total_valor', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'id';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (c.estado ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['anio' => 'c.anio', 'estado' => 'c.estado'],
            'fecha'    => ['limite' => 'c.fecha_limite_pago'],
            'numerico' => ['total' => 'c.total_valor'],
        ]);

        $from = "FROM {$this->table} c {$where}";
        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT c.* {$from} ORDER BY c.{$ordenCol} {$ordenDir}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function findCabeceraBorrador(int $idEmpresa, int $anio): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id_empresa = :e AND anio = :a AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':a' => $anio]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearCabecera(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, anio, fecha_desde, fecha_hasta, fecha_limite_pago,
                    base_calculo, estado, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :anio, :fecha_desde, :fecha_hasta, :fecha_limite,
                    :base_calculo, 'borrador', :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $d['id_empresa'],
            ':anio'         => (int) $d['anio'],
            ':fecha_desde'  => $d['fecha_desde'],
            ':fecha_hasta'  => $d['fecha_hasta'],
            ':fecha_limite' => $d['fecha_limite_pago'],
            ':base_calculo' => $d['base_calculo'],
            ':id_u'         => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function limpiarDetalle(int $idCabecera): void
    {
        $st = $this->db->prepare("DELETE FROM decimo_tercero_detalle WHERE id_cabecera = :id");
        $st->execute([':id' => $idCabecera]);
    }

    public function actualizarBaseCalculo(int $idCabecera, string $baseCalculo): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET base_calculo = :b WHERE id = :id");
        $st->execute([':b' => $baseCalculo, ':id' => $idCabecera]);
    }

    public function actualizarTotales(int $idCabecera, int $totalEmpleados, float $totalValor, string $estado): void
    {
        $sql = "UPDATE {$this->table}
                   SET total_empleados = :te, total_valor = :tv, estado = :est, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':te' => $totalEmpleados, ':tv' => $totalValor, ':est' => $estado, ':id' => $idCabecera]);
    }

    /** True si algún empleado de la cabecera ya tiene un pago (Egreso) registrado. */
    public function tienePagos(int $idCabecera): bool
    {
        $sql = "SELECT 1 FROM egresos_detalle d
                INNER JOIN egresos_cabecera e ON e.id = d.id_egreso
                WHERE d.tipo_documento = 'DECIMO_TERCERO' AND e.estado != 'anulado'
                  AND e.eliminado = FALSE AND d.eliminado = FALSE
                  AND d.id_referencia_documento IN (
                      SELECT id FROM decimo_tercero_detalle WHERE id_cabecera = :id
                  )
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCabecera]);
        return $st->fetchColumn() !== false;
    }

    public function eliminarLogico(int $idCabecera, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table}
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $idCabecera, ':e' => $idEmpresa]);
    }

    // ─── Detalle (empleados de una cabecera) ────────────────────────────────
    public function insertDetalleMasivo(int $idCabecera, int $idEmpresa, array $filas, int $idUsuario): void
    {
        if (empty($filas)) return;
        $sql = "INSERT INTO decimo_tercero_detalle (
                    id_cabecera, id_empresa, id_empleado, identificacion, nombres, apellidos, sexo,
                    codigo_ocupacion, dias_laborados, total_ganado, valor, mensualiza, tipo_pago,
                    discapacidad, valor_retencion, created_by, updated_by
                ) VALUES (
                    :id_cabecera, :id_empresa, :id_empleado, :identificacion, :nombres, :apellidos, :sexo,
                    :codigo_ocupacion, :dias, :total_ganado, :valor, :mensualiza, :tipo_pago,
                    :discapacidad, :valor_retencion, :id_u, :id_u
                )";
        $st = $this->db->prepare($sql);
        foreach ($filas as $f) {
            $st->execute([
                ':id_cabecera'      => $idCabecera,
                ':id_empresa'       => $idEmpresa,
                ':id_empleado'      => $f['id_empleado'],
                ':identificacion'   => $f['identificacion'],
                ':nombres'          => $f['nombres'],
                ':apellidos'        => $f['apellidos'],
                ':sexo'             => $f['sexo'],
                ':codigo_ocupacion' => $f['codigo_ocupacion'],
                ':dias'             => (int) $f['dias_laborados'],
                ':total_ganado'     => (float) $f['total_ganado'],
                ':valor'            => (float) $f['valor'],
                ':mensualiza'       => $f['mensualiza'] ? 't' : 'f',
                ':tipo_pago'        => $f['tipo_pago'],
                ':discapacidad'     => $f['discapacidad'] ? 't' : 'f',
                ':valor_retencion'  => (float) $f['valor_retencion'],
                ':id_u'             => $idUsuario,
            ]);
        }
    }

    /** Detalle por empleado, con lo pagado vía Egresos (tipo_documento='DECIMO_TERCERO') y el saldo. */
    public function getDetalle(int $idCabecera, int $idEmpresa): array
    {
        $sql = "SELECT dtd.*,
                       COALESCE(p.total_pagado, 0) AS monto_pagado,
                       (dtd.valor - COALESCE(p.total_pagado, 0)) AS saldo_pendiente
                FROM decimo_tercero_detalle dtd
                LEFT JOIN (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'DECIMO_TERCERO' AND e.estado != 'anulado'
                      AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ) p ON p.id_referencia_documento = dtd.id
                WHERE dtd.id_cabecera = :id AND dtd.id_empresa = :e
                ORDER BY dtd.apellidos, dtd.nombres";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCabecera, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarDetalle(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): bool
    {
        $permitidos = ['tipo_pago', 'discapacidad', 'valor_retencion', 'nombres', 'apellidos', 'total_ganado', 'valor'];
        $sets = [];
        $params = [':id' => $idDetalle, ':e' => $idEmpresa, ':u' => $idUsuario];
        foreach ($campos as $campo => $valor) {
            if (!in_array($campo, $permitidos, true)) continue;
            $sets[] = "{$campo} = :{$campo}";
            if ($campo === 'discapacidad') {
                $params[":{$campo}"] = $valor ? 't' : 'f';
            } else {
                $params[":{$campo}"] = $valor === '' ? null : $valor;
            }
        }
        if (empty($sets)) return false;
        $sets[] = 'updated_by = :u';
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = "UPDATE decimo_tercero_detalle SET " . implode(', ', $sets) . "
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    public function getDetalleFila(int $idDetalle, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM decimo_tercero_detalle WHERE id = :id AND id_empresa = :e");
        $st->execute([':id' => $idDetalle, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─── Fuente: empleados activos + su(s) período(s) + total ganado ───────
    public function getEmpleadosActivos(int $idEmpresa): array
    {
        $sql = "SELECT id, identificacion, nombres_apellidos, sexo, decimo_tercero,
                       codigo_sectorial_iess, discapacidad, valor_retencion_judicial,
                       id_banco_ecuador, tipo_cuenta, numero_cuenta
                FROM empleados
                WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPeriodosEmpleado(int $idEmpleado, int $idEmpresa): array
    {
        $sql = "SELECT fecha_ingreso, fecha_salida FROM empleado_periodos
                 WHERE id_empleado = :e AND id_empresa = :emp AND eliminado = false
                 ORDER BY fecha_ingreso ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpleado, ':emp' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Suma los rubros de ingreso de los roles MENSUAL del empleado dentro del
     * período [fechaDesde, fechaHasta] (comparando por mes calendario). Excluye
     * siempre Fondos de Reserva y los propios décimos mensualizados (no forman
     * parte de la base, evita circularidad). Si $soloIess=true, además exige
     * que el rubro aporte IESS.
     */
    public function getTotalGanadoEmpleado(int $idEmpleado, int $idEmpresa, string $fechaDesde, string $fechaHasta, bool $soloIess): float
    {
        $filtroIess = $soloIess ? ' AND rr.aporta_iess = true' : '';
        $sql = "SELECT COALESCE(SUM(rr.valor), 0)
                FROM rol_detalle_rubro rr
                INNER JOIN rol_detalle rd ON rd.id = rr.id_detalle
                INNER JOIN rol_cabecera rc ON rc.id = rd.id_rol
                WHERE rd.id_empleado = :id_emp AND rd.id_empresa = :id_empresa
                  AND rc.tipo_rol = 'MENSUAL' AND rc.eliminado = false
                  AND rr.tipo = 'ingreso' AND rr.origen NOT IN ('decimo', 'fondos')
                  {$filtroIess}
                  AND make_date(rc.periodo_anio, rc.periodo_mes, 1)
                      BETWEEN date_trunc('month', :desde::date) AND date_trunc('month', :hasta::date)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_emp' => $idEmpleado, ':id_empresa' => $idEmpresa,
            ':desde' => $fechaDesde, ':hasta' => $fechaHasta,
        ]);
        return round((float) $st->fetchColumn(), 2);
    }
}
