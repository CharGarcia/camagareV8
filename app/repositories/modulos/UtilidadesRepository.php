<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos del módulo Utilidades (participación de los trabajadores en
 * las utilidades). Tablas: utilidades_cabecera / utilidades_detalle
 * (database/migrations/20261008_create_utilidades.sql). Sin lógica de negocio.
 */
class UtilidadesRepository extends BaseRepository
{
    /** tipo_documento con el que Egresos registra el pago de cada fila del detalle. */
    public const TIPO_DOCUMENTO_EGRESO = 'UTILIDADES';

    /** Whitelist y mapa del ORDER BY del listado (ver OrdenListado). */
    public const MAPA_ORDEN = [
        'anio'      => 'c.anio',
        'limite'    => 'c.fecha_limite_pago',
        'repartir'  => 'c.monto_repartir',
        'empleados' => 'c.total_empleados',
        'total'     => 'c.total_valor',
        'estado'    => 'c.estado',
        'id'        => 'c.id',
    ];

    public function __construct()
    {
        parent::__construct('utilidades_cabecera');
    }

    /**
     * ¿Ya se ejecutó database/migrations/20261008_create_utilidades.sql en esta
     * base? El código se despliega antes que el SQL: mientras falte, el módulo
     * muestra el aviso en vez de reventar con un error 500.
     */
    public function instalado(): bool
    {
        return $this->tablaExiste('utilidades_cabecera') && $this->tablaExiste('utilidades_detalle');
    }

    // ─── Listado de cabeceras ───────────────────────────────────────────────
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, array $ordenMulti = []): array
    {
        if (!$this->instalado()) {
            return ['rows' => [], 'total' => 0];
        }
        $ordenMulti = OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = FiltrosBusqueda::condicionTexto(
                ['CAST(c.anio AS TEXT)', 'c.estado'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['anio' => 'c.anio', 'estado' => 'c.estado'],
            'fecha'    => ['limite' => 'c.fecha_limite_pago'],
            'numerico' => ['total' => 'c.total_valor', 'repartir' => 'c.monto_repartir'],
        ]);

        $from = "FROM {$this->table} c {$where}";
        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        // clausula() ya devuelve el texto completo "ORDER BY ...".
        $orderBy = OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'c.anio', 'c.id DESC');
        $sql = "SELECT c.* {$from} {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** Ejercicios con cálculo en la empresa (para el filtro del listado), del más reciente al más antiguo. */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        if (!$this->instalado()) return [];
        $st = $this->db->prepare("SELECT DISTINCT anio FROM {$this->table} WHERE id_empresa = :e AND eliminado = false ORDER BY anio DESC");
        $st->execute([':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findCabeceraPorAnio(int $idEmpresa, int $anio): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id_empresa = :e AND anio = :a AND eliminado = false");
        $st->execute([':e' => $idEmpresa, ':a' => $anio]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearCabecera(array $d): int
    {
        // fecha_emision = hoy (día del cálculo), no el plazo legal: Egresos valida que
        // el pago no sea anterior al documento, y el plazo casi siempre es futuro.
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, anio, fecha_desde, fecha_hasta, fecha_limite_pago, fecha_emision,
                    utilidad_liquida, monto_repartir, sbu_aplicado, estado,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :anio, :fecha_desde, :fecha_hasta, :fecha_limite, CURRENT_DATE,
                    :utilidad, :repartir, :sbu, 'borrador',
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $d['id_empresa'],
            ':anio'         => (int) $d['anio'],
            ':fecha_desde'  => $d['fecha_desde'],
            ':fecha_hasta'  => $d['fecha_hasta'],
            ':fecha_limite' => $d['fecha_limite_pago'],
            ':utilidad'     => (float) $d['utilidad_liquida'],
            ':repartir'     => (float) $d['monto_repartir'],
            ':sbu'          => (float) $d['sbu_aplicado'],
            ':id_u'         => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    /** Actualiza montos y totales de la cabecera tras un (re)cálculo. */
    public function actualizarCabecera(int $idCabecera, array $d, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table}
                   SET utilidad_liquida = :utilidad, monto_repartir = :repartir,
                       monto_10 = :m10, monto_5 = :m5, sbu_aplicado = :sbu, tope_trabajador = :tope,
                       total_empleados = :te, total_dias = :td, total_cargas = :tc,
                       total_valor = :tv, total_excedente = :tx, estado = :est,
                       updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':utilidad' => (float) $d['utilidad_liquida'],
            ':repartir' => (float) $d['monto_repartir'],
            ':m10'      => (float) $d['monto_10'],
            ':m5'       => (float) $d['monto_5'],
            ':sbu'      => (float) $d['sbu_aplicado'],
            ':tope'     => (float) $d['tope_trabajador'],
            ':te'       => (int) $d['total_empleados'],
            ':td'       => (int) $d['total_dias'],
            ':tc'       => (int) $d['total_cargas'],
            ':tv'       => (float) $d['total_valor'],
            ':tx'       => (float) $d['total_excedente'],
            ':est'      => (string) $d['estado'],
            ':u'        => $idUsuario,
            ':id'       => $idCabecera,
        ]);
    }

    public function setEstado(int $idCabecera, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET estado = :est, updated_by = :u, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id_empresa = :e");
        $st->execute([':est' => $estado, ':u' => $idUsuario, ':id' => $idCabecera, ':e' => $idEmpresa]);
    }

    public function setIdAsiento(int $idCabecera, int $idEmpresa, ?int $idAsiento): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET id_asiento = :a WHERE id = :id AND id_empresa = :e");
        $st->execute([':a' => $idAsiento, ':id' => $idCabecera, ':e' => $idEmpresa]);
    }

    public function eliminarLogico(int $idCabecera, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u WHERE id = :id AND id_empresa = :e");
        $st->execute([':u' => $idUsuario, ':id' => $idCabecera, ':e' => $idEmpresa]);
    }

    /** True si alguna fila de la cabecera ya tiene un pago (Egreso) registrado. */
    public function tienePagos(int $idCabecera): bool
    {
        $sql = "SELECT 1 FROM egresos_detalle d
                INNER JOIN egresos_cabecera e ON e.id = d.id_egreso
                WHERE d.tipo_documento = :tipo AND e.estado != 'anulado'
                  AND e.eliminado = FALSE AND d.eliminado = FALSE
                  AND d.id_referencia_documento IN (SELECT id FROM utilidades_detalle WHERE id_cabecera = :id)
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':tipo' => self::TIPO_DOCUMENTO_EGRESO, ':id' => $idCabecera]);
        return $st->fetchColumn() !== false;
    }

    // ─── Detalle ────────────────────────────────────────────────────────────
    public function limpiarDetalle(int $idCabecera): void
    {
        $st = $this->db->prepare("DELETE FROM utilidades_detalle WHERE id_cabecera = :id");
        $st->execute([':id' => $idCabecera]);
    }

    public function insertDetalleMasivo(int $idCabecera, int $idEmpresa, array $filas, int $idUsuario): void
    {
        if (empty($filas)) return;
        $sql = "INSERT INTO utilidades_detalle (
                    id_cabecera, id_empresa, id_empleado, identificacion, nombres, apellidos, sexo,
                    codigo_ocupacion, activo, dias_laborados, cargas_familiares,
                    valor_10, valor_5, valor_bruto, excedente, valor, valor_retencion, tipo_pago,
                    discapacidad, created_by, updated_by
                ) VALUES (
                    :id_cabecera, :id_empresa, :id_empleado, :identificacion, :nombres, :apellidos, :sexo,
                    :codigo_ocupacion, :activo, :dias, :cargas,
                    :v10, :v5, :bruto, :excedente, :valor, :retencion, :tipo_pago,
                    :discapacidad, :id_u, :id_u
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
                ':activo'           => $f['activo'] ? 't' : 'f',
                ':dias'             => (int) $f['dias_laborados'],
                ':cargas'           => (int) $f['cargas_familiares'],
                ':v10'              => (float) $f['valor_10'],
                ':v5'               => (float) $f['valor_5'],
                ':bruto'            => (float) $f['valor_bruto'],
                ':excedente'        => (float) $f['excedente'],
                ':valor'            => (float) $f['valor'],
                ':retencion'        => (float) $f['valor_retencion'],
                ':tipo_pago'        => $f['tipo_pago'],
                ':discapacidad'     => $f['discapacidad'] ? 't' : 'f',
                ':id_u'             => $idUsuario,
            ]);
        }
    }

    /** Detalle por trabajador, con lo pagado vía Egresos y el saldo. */
    public function getDetalle(int $idCabecera, int $idEmpresa): array
    {
        $sql = "SELECT ud.*,
                       COALESCE(p.total_pagado, 0) AS monto_pagado,
                       (ud.valor - COALESCE(p.total_pagado, 0)) AS saldo_pendiente
                FROM utilidades_detalle ud
                LEFT JOIN (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = :tipo AND e.estado != 'anulado'
                      AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ) p ON p.id_referencia_documento = ud.id
                WHERE ud.id_cabecera = :id AND ud.id_empresa = :e
                ORDER BY ud.apellidos, ud.nombres";
        $st = $this->db->prepare($sql);
        $st->execute([':tipo' => self::TIPO_DOCUMENTO_EGRESO, ':id' => $idCabecera, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDetalle(int $idDetalle, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM utilidades_detalle WHERE id = :id AND id_empresa = :e");
        $st->execute([':id' => $idDetalle, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function actualizarDetalle(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): bool
    {
        $permitidos = ['tipo_pago', 'discapacidad', 'valor_retencion', 'nombres', 'apellidos', 'cargas_familiares'];
        $sets = [];
        $params = [':id' => $idDetalle, ':e' => $idEmpresa, ':u' => $idUsuario];
        foreach ($campos as $campo => $valor) {
            if (!in_array($campo, $permitidos, true)) continue;
            $sets[] = "{$campo} = :{$campo}";
            if ($campo === 'discapacidad') {
                $params[":{$campo}"] = $valor ? 't' : 'f';
            } elseif ($campo === 'cargas_familiares') {
                $params[":{$campo}"] = (int) $valor;
            } else {
                $params[":{$campo}"] = $valor === '' ? null : $valor;
            }
        }
        if (empty($sets)) return false;
        $sets[] = 'updated_by = :u';
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $st = $this->db->prepare("UPDATE utilidades_detalle SET " . implode(', ', $sets) . " WHERE id = :id AND id_empresa = :e");
        return $st->execute($params);
    }

    /** Reescribe los valores calculados de una fila (tras recalcular el reparto). */
    public function actualizarValoresDetalle(int $idDetalle, array $v, int $idUsuario): void
    {
        $sql = "UPDATE utilidades_detalle
                   SET dias_laborados = :dias, cargas_familiares = :cargas, valor_10 = :v10, valor_5 = :v5,
                       valor_bruto = :bruto, excedente = :excedente, valor = :valor,
                       updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':dias'      => (int) $v['dias_laborados'],
            ':cargas'    => (int) $v['cargas_familiares'],
            ':v10'       => (float) $v['valor_10'],
            ':v5'        => (float) $v['valor_5'],
            ':bruto'     => (float) $v['valor_bruto'],
            ':excedente' => (float) $v['excedente'],
            ':valor'     => (float) $v['valor'],
            ':u'         => $idUsuario,
            ':id'        => $idDetalle,
        ]);
    }

    // ─── Fuente: trabajadores del ejercicio ─────────────────────────────────
    /**
     * Todo trabajador (activo o no) con al menos un período de empleo que se
     * traslape con el ejercicio: los ex trabajadores del año también participan.
     */
    public function getEmpleadosDelEjercicio(int $idEmpresa, string $desde, string $hasta): array
    {
        // «Participa en utilidades = No» en la ficha (dueño, representante legal por
        // mandato…): queda fuera del reparto y sus días no cuentan en el total. La
        // condición solo se aplica si la columna ya existe (SQL desplegado).
        $soloParticipan = $this->columnaExiste('empleados', 'participa_utilidades')
            ? 'AND e.participa_utilidades = true'
            : '';
        $sql = "SELECT e.id, e.identificacion, e.nombres_apellidos, e.sexo, e.estado,
                       e.codigo_sectorial_iess, e.discapacidad, e.cargas_familiares
                FROM empleados e
                WHERE e.id_empresa = :id_empresa AND e.eliminado = false
                  {$soloParticipan}
                  AND EXISTS (
                      SELECT 1 FROM empleado_periodos p
                      WHERE p.id_empleado = e.id AND p.id_empresa = e.id_empresa AND p.eliminado = false
                        AND p.fecha_ingreso <= :hasta
                        AND (p.fecha_salida IS NULL OR p.fecha_salida >= :desde)
                  )
                ORDER BY e.nombres_apellidos";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':desde' => $desde, ':hasta' => $hasta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Períodos de empleo de varios trabajadores en una sola consulta: [id_empleado => filas]. */
    public function getPeriodosPorEmpleado(array $idsEmpleado, int $idEmpresa): array
    {
        if (empty($idsEmpleado)) return [];
        $ph = [];
        $params = [':emp' => $idEmpresa];
        foreach (array_values($idsEmpleado) as $i => $id) {
            $ph[] = ":e{$i}";
            $params[":e{$i}"] = (int) $id;
        }
        $sql = "SELECT id_empleado, fecha_ingreso, fecha_salida FROM empleado_periodos
                 WHERE id_empresa = :emp AND eliminado = false AND id_empleado IN (" . implode(',', $ph) . ")
                 ORDER BY id_empleado, fecha_ingreso ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_empleado']][] = $r;
        }
        return $out;
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

    /**
     * Utilidades efectivamente pagadas (por Egresos) a cada trabajador dentro de un
     * año calendario: [id_empleado => total]. Lo usará el Anexo RDEP (campo
     * partUtil: utilidades percibidas en el ejercicio informado).
     */
    public function getPagadoPorEmpleadoEnAnio(int $idEmpresa, int $anioPago): array
    {
        if (!$this->instalado()) {
            return [];
        }
        $sql = "SELECT ud.id_empleado, SUM(d.monto_pagado) AS pagado
                FROM egresos_detalle d
                INNER JOIN egresos_cabecera e ON e.id = d.id_egreso
                INNER JOIN utilidades_detalle ud ON ud.id = d.id_referencia_documento
                WHERE d.tipo_documento = :tipo AND ud.id_empresa = :e
                  AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                  AND EXTRACT(YEAR FROM e.fecha_emision) = :anio
                GROUP BY ud.id_empleado";
        $st = $this->db->prepare($sql);
        $st->execute([':tipo' => self::TIPO_DOCUMENTO_EGRESO, ':e' => $idEmpresa, ':anio' => $anioPago]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_empleado']] = (float) $r['pagado'];
        }
        return $out;
    }
}
