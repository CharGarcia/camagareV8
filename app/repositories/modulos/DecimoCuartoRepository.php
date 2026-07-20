<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

class DecimoCuartoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('decimo_cuarto_cabecera');
    }

    // ─── Listado de cabeceras ───────────────────────────────────────────────
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['anio', 'region_grupo', 'estado', 'fecha_limite_pago', 'total_valor', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'id';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (c.region_grupo ILIKE :b OR c.estado ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['anio' => 'c.anio', 'region' => 'c.region_grupo', 'estado' => 'c.estado'],
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

    public function findCabeceraBorrador(int $idEmpresa, int $anio, string $regionGrupo): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :e AND anio = :a AND region_grupo = :r AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':a' => $anio, ':r' => $regionGrupo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearCabecera(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, anio, region_grupo, fecha_desde, fecha_hasta, fecha_limite_pago,
                    sbu_aplicado, estado, created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :anio, :region_grupo, :fecha_desde, :fecha_hasta, :fecha_limite,
                    :sbu, 'borrador', :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $d['id_empresa'],
            ':anio'         => (int) $d['anio'],
            ':region_grupo' => $d['region_grupo'],
            ':fecha_desde'  => $d['fecha_desde'],
            ':fecha_hasta'  => $d['fecha_hasta'],
            ':fecha_limite' => $d['fecha_limite_pago'],
            ':sbu'          => (float) $d['sbu_aplicado'],
            ':id_u'         => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function limpiarDetalle(int $idCabecera): void
    {
        $st = $this->db->prepare("DELETE FROM decimo_cuarto_detalle WHERE id_cabecera = :id");
        $st->execute([':id' => $idCabecera]);
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
                WHERE d.tipo_documento = 'DECIMO_CUARTO' AND e.estado != 'anulado'
                  AND e.eliminado = FALSE AND d.eliminado = FALSE
                  AND d.id_referencia_documento IN (
                      SELECT id FROM decimo_cuarto_detalle WHERE id_cabecera = :id
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
        $sql = "INSERT INTO decimo_cuarto_detalle (
                    id_cabecera, id_empresa, id_empleado, identificacion, nombres, apellidos, sexo,
                    codigo_ocupacion, dias_laborados, valor, mensualiza, tipo_pago,
                    discapacidad, fecha_jubilacion, valor_retencion, created_by, updated_by
                ) VALUES (
                    :id_cabecera, :id_empresa, :id_empleado, :identificacion, :nombres, :apellidos, :sexo,
                    :codigo_ocupacion, :dias, :valor, :mensualiza, :tipo_pago,
                    :discapacidad, :fecha_jubilacion, :valor_retencion, :id_u, :id_u
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
                ':valor'            => (float) $f['valor'],
                ':mensualiza'       => $f['mensualiza'] ? 't' : 'f',
                ':tipo_pago'        => $f['tipo_pago'],
                ':discapacidad'     => $f['discapacidad'] ? 't' : 'f',
                ':fecha_jubilacion' => $f['fecha_jubilacion'] ?: null,
                ':valor_retencion'  => (float) $f['valor_retencion'],
                ':id_u'             => $idUsuario,
            ]);
        }
    }

    /** Detalle por empleado, con lo pagado vía Egresos (tipo_documento='DECIMO_CUARTO') y el saldo. */
    public function getDetalle(int $idCabecera, int $idEmpresa): array
    {
        $sql = "SELECT dcd.*,
                       COALESCE(p.total_pagado, 0) AS monto_pagado,
                       (dcd.valor - COALESCE(p.total_pagado, 0)) AS saldo_pendiente
                FROM decimo_cuarto_detalle dcd
                LEFT JOIN (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'DECIMO_CUARTO' AND e.estado != 'anulado'
                      AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ) p ON p.id_referencia_documento = dcd.id
                WHERE dcd.id_cabecera = :id AND dcd.id_empresa = :e
                ORDER BY dcd.apellidos, dcd.nombres";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCabecera, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarDetalle(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): bool
    {
        $permitidos = ['tipo_pago', 'discapacidad', 'fecha_jubilacion', 'valor_retencion', 'nombres', 'apellidos'];
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
        $sql = "UPDATE decimo_cuarto_detalle SET " . implode(', ', $sets) . "
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    // ─── Fuente: empleados activos de una región + su(s) período(s) ────────
    public function getEmpleadosPorRegion(int $idEmpresa, array $regiones): array
    {
        if (empty($regiones)) return [];
        $ph = [];
        $params = [':id_empresa' => $idEmpresa];
        foreach ($regiones as $i => $r) {
            $key = ":r{$i}";
            $ph[] = $key;
            $params[$key] = $r;
        }
        $sql = "SELECT id, identificacion, nombres_apellidos, sexo, region, decimo_cuarto,
                       codigo_sectorial_iess, discapacidad, fecha_jubilacion_patronal,
                       valor_retencion_judicial, id_banco_ecuador, tipo_cuenta, numero_cuenta
                FROM empleados
                WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                  AND region IN (" . implode(',', $ph) . ")";
        $st = $this->db->prepare($sql);
        $st->execute($params);
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

    /** Tarifas del año desde la tabla global 'salarios' (mismo patrón que RolPagoRepository::getSalario). */
    public function getSalario(int $anio): array
    {
        $st = $this->db->prepare("SELECT * FROM salarios WHERE ano = :a AND status = 1 ORDER BY ano DESC LIMIT 1");
        $st->execute([':a' => $anio]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) return $r;
        $r = $this->db->query("SELECT * FROM salarios WHERE status = 1 ORDER BY ano DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $r ?: ['sbu' => 460];
    }
}
