<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos del Anexo RDEP: tablas anexo_rdep / anexo_rdep_detalle
 * (database/migrations/20261008_create_anexo_rdep.sql) y las consultas de
 * nómina con las que se arma cada fila. Sin lógica de negocio.
 */
class AnexoRdepRepository extends BaseRepository
{
    public const MAPA_ORDEN = [
        'anio'         => 'c.anio',
        'trabajadores' => 'c.total_trabajadores',
        'ingresos'     => 'c.total_ingresos',
        'retenido'     => 'c.total_retenido',
        'estado'       => 'c.estado',
        'generado'     => 'c.generado_at',
        'id'           => 'c.id',
    ];

    /** Columnas del detalle que se escriben (todas menos id/auditoría). */
    public const COLUMNAS_DETALLE = [
        'id_empleado', 'tip_id_ret', 'id_ret', 'apellidos', 'nombres', 'estab', 'residencia', 'pais_residencia',
        'aplica_convenio', 'tipo_discap', 'porcentaje_discap', 'tip_id_discap', 'id_discap', 'ben_galapagos',
        'enf_catastro', 'num_cargas', 'tercera_edad', 'fecha_nacimiento',
        'suel_sal', 'sob_suel', 'part_util', 'int_grab_gen', 'imp_rent_empl', 'decim_ter', 'decim_cuar',
        'fondo_reserva', 'salario_digno', 'otros_ing_no_grav', 'ing_grav_este_empl',
        'sis_sal_net', 'apo_per_iess', 'apor_per_iess_otros', 'deduc_vivienda', 'deduc_salud', 'deduc_educ',
        'deduc_aliment', 'deduc_vestim', 'deduc_turismo', 'exo_discap', 'exo_ter_ed',
        'bas_imp', 'imp_rent_caus', 'rebaja_gastos', 'imp_rent_rebaja', 'val_ret_otros', 'val_imp_asu_este', 'val_ret',
        'campos_manuales', 'graves', 'leves', 'observaciones',
    ];

    public function __construct()
    {
        parent::__construct('anexo_rdep');
    }

    public function instalado(): bool
    {
        return $this->tablaExiste('anexo_rdep') && $this->tablaExiste('anexo_rdep_detalle');
    }

    // ─── Cabeceras ──────────────────────────────────────────────────────────
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, array $ordenMulti = []): array
    {
        if (!$this->instalado()) {
            return ['rows' => [], 'total' => 0];
        }
        $ordenMulti = OrdenListado::normalizar($ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]);

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(['CAST(c.anio AS TEXT)', 'c.razon_social', 'c.num_ruc'], $parsed['texto_libre'], $params, 'tl');
            if ($cond !== '') $where .= " AND {$cond}";
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['anio' => 'c.anio', 'estado' => 'c.estado', 'empleador' => 'c.tipo_empleador'],
            'fecha'    => ['generado' => 'c.generado_at'],
            'numerico' => ['trabajadores' => 'c.total_trabajadores', 'retenido' => 'c.total_retenido', 'ingresos' => 'c.total_ingresos'],
        ]);

        $from = "FROM {$this->table} c {$where}";
        $st = $this->db->prepare("SELECT COUNT(*) {$from}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'c.anio', 'c.id DESC');
        $sql = "SELECT c.* {$from} {$orderBy}";
        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        if (!$this->instalado()) return [];
        $st = $this->db->prepare("SELECT DISTINCT anio FROM {$this->table} WHERE id_empresa = :e AND eliminado = false ORDER BY anio DESC");
        $st->execute([':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findPorAnio(int $idEmpresa, int $anio): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id_empresa = :e AND anio = :a AND eliminado = false");
        $st->execute([':e' => $idEmpresa, ':a' => $anio]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function crearCabecera(array $d, int $idUsuario): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, anio, num_ruc, razon_social, tipo_empleador, ente_seg_social,
                    fraccion_basica, canasta_basica, porcentaje_rebaja, ipceg, factores_canastas,
                    estado, created_by, updated_by
                ) VALUES (
                    :e, :anio, :ruc, :rs, :te, :ente, :fb, :cb, :pr, :ip, :fc, 'borrador', :u, :u
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e' => $d['id_empresa'], ':anio' => (int) $d['anio'], ':ruc' => $d['num_ruc'], ':rs' => $d['razon_social'],
            ':te' => $d['tipo_empleador'], ':ente' => $d['ente_seg_social'],
            ':fb' => (float) $d['fraccion_basica'], ':cb' => (float) $d['canasta_basica'],
            ':pr' => (float) $d['porcentaje_rebaja'], ':ip' => (float) $d['ipceg'],
            ':fc' => json_encode($d['factores_canastas'] ?? new \stdClass()),
            ':u' => $idUsuario,
        ]);
        return $this->lastInsertId();
    }

    public function actualizarCabecera(int $id, int $idEmpresa, array $d, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table}
                   SET num_ruc = :ruc, razon_social = :rs, tipo_empleador = :te, ente_seg_social = :ente,
                       fraccion_basica = :fb, canasta_basica = :cb, porcentaje_rebaja = :pr, ipceg = :ip,
                       observaciones = :obs, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ruc' => $d['num_ruc'], ':rs' => $d['razon_social'], ':te' => $d['tipo_empleador'], ':ente' => $d['ente_seg_social'],
            ':fb' => (float) $d['fraccion_basica'], ':cb' => (float) $d['canasta_basica'],
            ':pr' => (float) $d['porcentaje_rebaja'], ':ip' => (float) $d['ipceg'],
            ':obs' => $d['observaciones'] !== '' ? $d['observaciones'] : null,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function setTotales(int $id, int $trabajadores, float $ingresos, float $retenido, int $graves, int $leves, int $idUsuario, bool $importado = false): void
    {
        $sql = "UPDATE {$this->table}
                   SET total_trabajadores = :t, total_ingresos = :i, total_retenido = :r, total_graves = :g, total_leves = :l,
                       updated_by = :u, updated_at = CURRENT_TIMESTAMP" . ($importado ? ", importado_at = CURRENT_TIMESTAMP" : '') . "
                 WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':t' => $trabajadores, ':i' => $ingresos, ':r' => $retenido, ':g' => $graves, ':l' => $leves, ':u' => $idUsuario, ':id' => $id]);
    }

    public function setGenerado(int $id, int $idEmpresa, string $archivo, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET estado = 'generado', archivo_xml = :a, generado_at = CURRENT_TIMESTAMP, updated_by = :u, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id_empresa = :e");
        $st->execute([':a' => $archivo, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function setBorrador(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET estado = 'borrador', updated_by = :u, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id_empresa = :e AND estado <> 'borrador'");
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u WHERE id = :id AND id_empresa = :e");
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ─── Detalle ────────────────────────────────────────────────────────────
    /** Detalle con el correo de la ficha del empleado (destino por defecto del Formulario 107). */
    public function getDetalle(int $idAnexo, int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT d.*, e.email AS email_empleado
                                  FROM anexo_rdep_detalle d
                                  LEFT JOIN empleados e ON e.id = d.id_empleado AND e.id_empresa = d.id_empresa
                                  WHERE d.id_anexo = :a AND d.id_empresa = :e
                                  ORDER BY d.apellidos, d.nombres, d.id");
        $st->execute([':a' => $idAnexo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDetalle(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT d.*, e.email AS email_empleado
                                  FROM anexo_rdep_detalle d
                                  LEFT JOIN empleados e ON e.id = d.id_empleado AND e.id_empresa = d.id_empresa
                                  WHERE d.id = :id AND d.id_empresa = :e");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Anota el último envío del Formulario 107 (si la columna existe: SQL 20261008_anexo_rdep_envio_107). */
    public function marcarEnvio107(int $idDetalle, int $idEmpresa, string $destinos): void
    {
        if (!$this->columnaExiste('anexo_rdep_detalle', 'f107_enviado_at')) return;
        $st = $this->db->prepare("UPDATE anexo_rdep_detalle SET f107_enviado_at = CURRENT_TIMESTAMP, f107_enviado_a = :a WHERE id = :id AND id_empresa = :e");
        $st->execute([':a' => mb_substr($destinos, 0, 300), ':id' => $idDetalle, ':e' => $idEmpresa]);
    }

    public function insertDetalle(int $idAnexo, int $idEmpresa, array $f, int $idUsuario): int
    {
        $cols = self::COLUMNAS_DETALLE;
        $sql = "INSERT INTO anexo_rdep_detalle (id_anexo, id_empresa, " . implode(', ', $cols) . ", created_by, updated_by)
                VALUES (:id_anexo, :id_empresa, :" . implode(', :', $cols) . ", :u, :u)";
        $st = $this->db->prepare($sql);
        $st->execute($this->paramsDetalle($f, [':id_anexo' => $idAnexo, ':id_empresa' => $idEmpresa, ':u' => $idUsuario]));
        return $this->lastInsertId();
    }

    public function updateDetalle(int $id, int $idEmpresa, array $f, int $idUsuario): void
    {
        $cols = self::COLUMNAS_DETALLE;
        $sets = array_map(fn($c) => "{$c} = :{$c}", $cols);
        $sql = "UPDATE anexo_rdep_detalle SET " . implode(', ', $sets) . ", updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        $st->execute($this->paramsDetalle($f, [':id' => $id, ':id_empresa' => $idEmpresa, ':u' => $idUsuario]));
    }

    public function setValidaciones(int $id, array $graves, array $leves): void
    {
        $st = $this->db->prepare("UPDATE anexo_rdep_detalle SET graves = :g, leves = :l WHERE id = :id");
        $st->execute([':g' => json_encode(array_values($graves), JSON_UNESCAPED_UNICODE), ':l' => json_encode(array_values($leves), JSON_UNESCAPED_UNICODE), ':id' => $id]);
    }

    public function deleteDetalle(int $id, int $idEmpresa): void
    {
        $st = $this->db->prepare("DELETE FROM anexo_rdep_detalle WHERE id = :id AND id_empresa = :e");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
    }

    private function paramsDetalle(array $f, array $extra): array
    {
        $p = $extra;
        foreach (self::COLUMNAS_DETALLE as $c) {
            $v = $f[$c] ?? null;
            if ($c === 'tercera_edad') {
                $v = (is_bool($v) ? $v : in_array(strtolower((string) $v), ['1', 't', 'true'], true)) ? 't' : 'f';
            } elseif (in_array($c, ['campos_manuales', 'graves', 'leves'], true)) {
                $v = is_string($v) ? $v : json_encode(array_values((array) $v), JSON_UNESCAPED_UNICODE);
            } elseif ($c === 'id_empleado') {
                $v = $v !== null && $v !== '' ? (int) $v : null;
            } elseif ($c === 'fecha_nacimiento' || $c === 'observaciones') {
                $v = ($v === '' || $v === null) ? null : $v;
            }
            $p[':' . $c] = $v;
        }
        return $p;
    }

    // ─── Fuentes de nómina del ejercicio ────────────────────────────────────
    /**
     * Acumulado del año por trabajador desde los roles MENSUALES (los de quincena
     * y semana se netean dentro del mensual, así que no se suman aparte).
     * @return array<int, array> id_empleado => suel_sal, sob_suel, decim_ter_rol, decim_cuar_rol, fondo_reserva, apo_per_iess, val_ret
     */
    public function getAcumuladoRoles(int $idEmpresa, int $anio): array
    {
        $sql = "WITH roles AS (
                    SELECT rd.id, rd.id_empleado, rd.aporte_iess, rd.retencion_renta
                    FROM rol_detalle rd
                    INNER JOIN rol_cabecera rc ON rc.id = rd.id_rol
                    WHERE rc.id_empresa = :e AND rc.eliminado = false AND rc.tipo_rol = 'MENSUAL'
                      AND rc.periodo_anio = :anio AND rc.estado IN ('generado', 'pagado', 'contabilizado')
                ),
                rubros AS (
                    SELECT r.id_empleado,
                           SUM(CASE WHEN rr.tipo = 'ingreso' AND rr.aporta_iess = true THEN rr.valor ELSE 0 END) AS suel_sal,
                           SUM(CASE WHEN rr.tipo = 'ingreso' AND rr.aporta_iess = false AND rr.origen NOT IN ('decimo', 'fondos') THEN rr.valor ELSE 0 END) AS sob_suel,
                           SUM(CASE WHEN rr.tipo = 'ingreso' AND rr.origen = 'decimo' AND rr.concepto ILIKE 'D_cimo Tercero%' THEN rr.valor ELSE 0 END) AS decim_ter_rol,
                           SUM(CASE WHEN rr.tipo = 'ingreso' AND rr.origen = 'decimo' AND rr.concepto ILIKE 'D_cimo Cuarto%' THEN rr.valor ELSE 0 END) AS decim_cuar_rol,
                           SUM(CASE WHEN rr.tipo = 'ingreso' AND rr.origen = 'fondos' THEN rr.valor ELSE 0 END) AS fondo_reserva
                    FROM roles r
                    INNER JOIN rol_detalle_rubro rr ON rr.id_detalle = r.id
                    GROUP BY r.id_empleado
                ),
                lineas AS (
                    SELECT id_empleado, SUM(aporte_iess) AS apo_per_iess, SUM(retencion_renta) AS val_ret, COUNT(*) AS meses
                    FROM roles GROUP BY id_empleado
                )
                SELECT l.id_empleado, l.meses, l.apo_per_iess, l.val_ret,
                       COALESCE(ru.suel_sal, 0) AS suel_sal, COALESCE(ru.sob_suel, 0) AS sob_suel,
                       COALESCE(ru.decim_ter_rol, 0) AS decim_ter_rol, COALESCE(ru.decim_cuar_rol, 0) AS decim_cuar_rol,
                       COALESCE(ru.fondo_reserva, 0) AS fondo_reserva
                FROM lineas l LEFT JOIN rubros ru ON ru.id_empleado = l.id_empleado";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':anio' => $anio]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_empleado']] = $r;
        }
        return $out;
    }

    /**
     * Décimos acumulados pagados en el año (los que no se mensualizan): el tercero
     * de la declaración del año (se paga en diciembre) y el cuarto (marzo/agosto).
     * @return array<int, array{decim_ter: float, decim_cuar: float}>
     */
    public function getDecimosAcumulados(int $idEmpresa, int $anio): array
    {
        $out = [];
        foreach ([['decimo_tercero_detalle', 'decimo_tercero_cabecera', 'decim_ter'], ['decimo_cuarto_detalle', 'decimo_cuarto_cabecera', 'decim_cuar']] as [$det, $cab, $clave]) {
            if (!$this->tablaExiste($det)) continue;
            $sql = "SELECT d.id_empleado, SUM(d.valor) AS valor
                    FROM {$det} d INNER JOIN {$cab} c ON c.id = d.id_cabecera
                    WHERE c.id_empresa = :e AND c.eliminado = false AND c.anio = :anio AND d.mensualiza = false
                    GROUP BY d.id_empleado";
            $st = $this->db->prepare($sql);
            $st->execute([':e' => $idEmpresa, ':anio' => $anio]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int) $r['id_empleado']][$clave] = (float) $r['valor'];
            }
        }
        return $out;
    }

    /** Datos de ficha de varios empleados: [id => fila]. */
    public function getEmpleados(int $idEmpresa, array $ids): array
    {
        if (empty($ids)) return [];
        $ph = [];
        $params = [':e' => $idEmpresa];
        foreach (array_values($ids) as $i => $id) {
            $ph[] = ":i{$i}";
            $params[":i{$i}"] = (int) $id;
        }
        // % de discapacidad de la ficha (columna nueva: 0 si aún no se desplegó el SQL).
        $pct = $this->columnaExiste('empleados', 'porcentaje_discapacidad') ? 'porcentaje_discapacidad' : '0 AS porcentaje_discapacidad';
        $sql = "SELECT id, tipo_id, identificacion, nombres_apellidos, fecha_nacimiento, discapacidad, {$pct}, cargas_familiares, estado
                FROM empleados WHERE id_empresa = :e AND id IN (" . implode(',', $ph) . ")";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /** Buscador de empleados para agregar un trabajador a mano. */
    public function buscarEmpleados(int $idEmpresa, string $texto, int $limite = 15): array
    {
        $sql = "SELECT id, identificacion, nombres_apellidos, estado
                FROM empleados
                WHERE id_empresa = :e AND eliminado = false
                  AND (identificacion ILIKE :t OR nombres_apellidos ILIKE :t)
                ORDER BY nombres_apellidos LIMIT " . (int) $limite;
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':t' => '%' . $texto . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Gastos personales proyectados del año: [id_empleado => fila de empleado_gastos_personales]. */
    public function getGastosPersonales(int $idEmpresa, int $anio): array
    {
        if (!$this->tablaExiste('empleado_gastos_personales')) return [];
        $st = $this->db->prepare("SELECT * FROM empleado_gastos_personales WHERE id_empresa = :e AND anio = :a AND eliminado = false");
        $st->execute([':e' => $idEmpresa, ':a' => $anio]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_empleado']] = $r;
        }
        return $out;
    }

    /** Datos del empleador: RUC, razón social y código del establecimiento matriz. */
    public function getEmpleador(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT ruc, nombre FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        $emp = $st->fetch(PDO::FETCH_ASSOC) ?: ['ruc' => '', 'nombre' => ''];
        $estab = '001';
        try {
            $st = $this->db->prepare("SELECT codigo FROM empresa_establecimiento WHERE id_empresa = :e AND eliminado = false ORDER BY (tipo ILIKE 'Matriz') DESC, codigo ASC LIMIT 1");
            $st->execute([':e' => $idEmpresa]);
            $c = (string) $st->fetchColumn();
            if (preg_match('/^\d{3}$/', $c)) $estab = $c;
        } catch (\Throwable $e) {
            // sin tabla de establecimientos: matriz 001
        }
        return ['num_ruc' => (string) $emp['ruc'], 'razon_social' => (string) $emp['nombre'], 'estab' => $estab];
    }

    /** Años con tabla de impuesto a la renta cargada (tabla global: sin id_empresa). */
    public function aniosConTramos(): array
    {
        $st = $this->db->query("SELECT DISTINCT anio FROM impuesto_renta_tramos WHERE eliminado = false ORDER BY anio DESC");
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Fija la fracción básica y la canasta del anexo (las que se completaron desde la configuración). */
    public function actualizarParametrosCalculo(int $id, int $idEmpresa, float $fraccionBasica, float $canasta, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table}
                                     SET fraccion_basica = :fb, canasta_basica = :cb, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                   WHERE id = :id AND id_empresa = :e");
        $st->execute([':fb' => $fraccionBasica, ':cb' => $canasta, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Firmantes del Formulario 107: representante legal y contador de la empresa. */
    public function getFirmas(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT nom_rep_legal, ced_rep_legal, nombre_contador, ruc_contador FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Códigos de establecimiento de la empresa (para el combo del trabajador). */
    public function getEstablecimientos(int $idEmpresa): array
    {
        try {
            $st = $this->db->prepare("SELECT codigo, nombre FROM empresa_establecimiento WHERE id_empresa = :e AND eliminado = false ORDER BY codigo");
            $st->execute([':e' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
