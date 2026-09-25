<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class AlumnoRepository extends BaseRepository
{
    protected string $table = 'alumnos';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Listado con campus/nivel "actuales" resueltos por el período abierto
     * (o el más reciente si no hay ninguno abierto) — no se cachean en la
     * cabecera para evitar desincronización (CLAUDE.md §8).
     */
    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $whereSql = $this->getBaseWhere($idEmpresa, 'a', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (a.nombres ILIKE :b OR a.apellidos ILIKE :b OR a.numero_identificacion ILIKE :b OR cli.nombre ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $joins = "LEFT JOIN clientes cli ON cli.id = a.id_cliente
                  LEFT JOIN LATERAL (
                        SELECT ap.id_campus, ap.id_nivel, ap.anio_lectivo, ap.fecha_ingreso, ap.fecha_salida
                        FROM alumnos_periodos ap
                        WHERE ap.id_alumno = a.id AND ap.eliminado = false
                        ORDER BY (ap.fecha_salida IS NULL) DESC, ap.fecha_ingreso DESC
                        LIMIT 1
                  ) per ON true
                  LEFT JOIN alumnos_campus camp ON camp.id = per.id_campus
                  LEFT JOIN alumnos_niveles niv ON niv.id = per.id_nivel";

        $cols = [
            'nombres'      => 'a.nombres',
            'apellidos'    => 'a.apellidos',
            'campus'       => 'camp.nombre',
            'nivel'        => 'niv.nombre',
            'estado_academico' => 'a.estado_academico',
            'representante'=> 'cli.nombre',
        ];
        $col = $cols[$ordenCol] ?? 'a.apellidos';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} a {$joins} {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sqlRows = "SELECT a.*, cli.nombre AS representante_nombre, cli.identificacion AS representante_identificacion,
                           camp.id AS campus_actual_id, camp.nombre AS campus_actual_nombre,
                           niv.id AS nivel_actual_id, niv.nombre AS nivel_actual_nombre,
                           per.anio_lectivo AS anio_lectivo_actual,
                           (per.fecha_salida IS NULL AND per.fecha_ingreso IS NOT NULL) AS matricula_vigente
                    FROM {$this->table} a {$joins}
                    {$whereSql}
                    ORDER BY {$col} {$dir}, a.id DESC";
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);

        return ['total' => $total, 'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, nombres, apellidos, tipo_identificacion, numero_identificacion,
                    fecha_nacimiento, sexo, nacionalidad, foto_ruta, estado_academico,
                    id_cliente, id_punto_emision,
                    tipo_sangre, alergias_condiciones, contacto_emergencia_nombre, contacto_emergencia_telefono,
                    observaciones, created_by, updated_by
                ) VALUES (
                    :id_empresa, :nombres, :apellidos, :tipo_identificacion, :numero_identificacion,
                    :fecha_nacimiento, :sexo, :nacionalidad, :foto_ruta, :estado_academico,
                    :id_cliente, :id_punto_emision,
                    :tipo_sangre, :alergias_condiciones, :contacto_emergencia_nombre, :contacto_emergencia_telefono,
                    :observaciones, :id_usuario, :id_usuario
                )";
        $st = $this->db->prepare($sql);
        $st->execute($this->paramsDesdeData($data));
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    nombres = :nombres, apellidos = :apellidos,
                    tipo_identificacion = :tipo_identificacion, numero_identificacion = :numero_identificacion,
                    fecha_nacimiento = :fecha_nacimiento, sexo = :sexo, nacionalidad = :nacionalidad,
                    foto_ruta = :foto_ruta, estado_academico = :estado_academico,
                    id_cliente = :id_cliente, id_punto_emision = :id_punto_emision,
                    tipo_sangre = :tipo_sangre, alergias_condiciones = :alergias_condiciones,
                    contacto_emergencia_nombre = :contacto_emergencia_nombre, contacto_emergencia_telefono = :contacto_emergencia_telefono,
                    observaciones = :observaciones, updated_by = :id_usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = $this->paramsDesdeData($data);
        $params[':id'] = $id;
        $params[':id_empresa'] = $idEmpresa;
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    private function paramsDesdeData(array $data): array
    {
        return [
            ':id_empresa'                   => $data['id_empresa'],
            ':nombres'                      => $data['nombres'],
            ':apellidos'                    => $data['apellidos'],
            ':tipo_identificacion'          => $data['tipo_identificacion'] !== '' ? $data['tipo_identificacion'] : null,
            ':numero_identificacion'        => $data['numero_identificacion'] !== '' ? $data['numero_identificacion'] : null,
            ':fecha_nacimiento'             => $data['fecha_nacimiento'] !== '' ? $data['fecha_nacimiento'] : null,
            ':sexo'                         => $data['sexo'] !== '' ? $data['sexo'] : null,
            ':nacionalidad'                 => $data['nacionalidad'] !== '' ? $data['nacionalidad'] : null,
            ':foto_ruta'                    => $data['foto_ruta'] !== '' ? $data['foto_ruta'] : null,
            ':estado_academico'             => $data['estado_academico'],
            ':id_cliente'                   => $data['id_cliente'],
            ':id_punto_emision'             => $data['id_punto_emision'] > 0 ? $data['id_punto_emision'] : null,
            ':tipo_sangre'                  => $data['tipo_sangre'] !== '' ? $data['tipo_sangre'] : null,
            ':alergias_condiciones'         => $data['alergias_condiciones'] !== '' ? $data['alergias_condiciones'] : null,
            ':contacto_emergencia_nombre'   => $data['contacto_emergencia_nombre'] !== '' ? $data['contacto_emergencia_nombre'] : null,
            ':contacto_emergencia_telefono' => $data['contacto_emergencia_telefono'] !== '' ? $data['contacto_emergencia_telefono'] : null,
            ':observaciones'                => $data['observaciones'] !== '' ? $data['observaciones'] : null,
            ':id_usuario'                   => $data['id_usuario'],
        ];
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    // ------------------------------------------------------------------
    // Matrícula (períodos): historial + sincronización (mismo patrón que
    // EmpleadoRepository::getPeriodos/syncPeriodos).
    // ------------------------------------------------------------------

    public function getPeriodos(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT ap.*, camp.nombre AS campus_nombre, niv.nombre AS nivel_nombre
                FROM alumnos_periodos ap
                LEFT JOIN alumnos_campus camp ON camp.id = ap.id_campus
                LEFT JOIN alumnos_niveles niv ON niv.id = ap.id_nivel
                WHERE ap.id_alumno = :id_a AND ap.id_empresa = :id_e AND ap.eliminado = false
                ORDER BY ap.fecha_ingreso DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_a' => $idAlumno, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncPeriodos(int $idAlumno, int $idEmpresa, array $periodos, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_periodos SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($periodos)) {
            return;
        }

        $sql = "INSERT INTO alumnos_periodos (
                    id_alumno, id_empresa, id_campus, id_nivel, anio_lectivo,
                    fecha_ingreso, fecha_salida, motivo_salida, estado, observacion, created_by, updated_by
                ) VALUES (
                    :a, :e, :campus, :nivel, :anio, :fi, :fs, :motivo, :estado, :obs, :u, :u
                )";
        $st = $this->db->prepare($sql);
        foreach ($periodos as $p) {
            if (empty($p['fecha_ingreso'])) {
                continue;
            }
            $abierto = empty($p['fecha_salida']);
            $st->execute([
                ':a'      => $idAlumno,
                ':e'      => $idEmpresa,
                ':campus' => !empty($p['id_campus']) ? (int)$p['id_campus'] : null,
                ':nivel'  => !empty($p['id_nivel']) ? (int)$p['id_nivel'] : null,
                ':anio'   => $p['anio_lectivo'] ?? null,
                ':fi'     => $p['fecha_ingreso'],
                ':fs'     => !empty($p['fecha_salida']) ? $p['fecha_salida'] : null,
                ':motivo' => !empty($p['motivo_salida']) ? $p['motivo_salida'] : null,
                ':estado' => $abierto ? 'activo' : 'finalizado',
                ':obs'    => $p['observacion'] ?? null,
                ':u'      => $idUsuario,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Horario (individual por alumno).
    // ------------------------------------------------------------------

    public function getHorarios(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT * FROM alumnos_horarios
                WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false
                ORDER BY dia_semana ASC, hora_inicio ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncHorarios(int $idAlumno, int $idEmpresa, array $horarios, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_horarios SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($horarios)) {
            return;
        }

        $sql = "INSERT INTO alumnos_horarios (id_alumno, id_empresa, dia_semana, hora_inicio, hora_fin, jornada, observacion, created_by, updated_by)
                VALUES (:a, :e, :dia, :hi, :hf, :jor, :obs, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($horarios as $h) {
            if (empty($h['dia_semana']) || empty($h['hora_inicio']) || empty($h['hora_fin'])) {
                continue;
            }
            $st->execute([
                ':a'   => $idAlumno,
                ':e'   => $idEmpresa,
                ':dia' => (int) $h['dia_semana'],
                ':hi'  => $h['hora_inicio'],
                ':hf'  => $h['hora_fin'],
                ':jor' => $h['jornada'] ?? null,
                ':obs' => $h['observacion'] ?? null,
                ':u'   => $idUsuario,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Representantes y personas autorizadas a retirar (datos libres).
    // ------------------------------------------------------------------

    private ?bool $hayTablaRepresentantes = null;

    /**
     * La tabla llega con database/migrations/20260924_alumnos_representantes.sql.
     * Si el código se despliega antes que el SQL, un UPDATE/INSERT sobre una tabla
     * inexistente abortaría toda la transacción del guardado del alumno; con este
     * chequeo (un SELECT que no falla) el Service simplemente se salta la pestaña.
     */
    public function existeTablaRepresentantes(): bool
    {
        if ($this->hayTablaRepresentantes === null) {
            $st = $this->db->query("SELECT to_regclass('public.alumnos_representantes') IS NOT NULL");
            $this->hayTablaRepresentantes = (bool) $st->fetchColumn();
        }
        return $this->hayTablaRepresentantes;
    }

    public function getRepresentantes(int $idAlumno, int $idEmpresa): array
    {
        if (!$this->existeTablaRepresentantes()) {
            return [];
        }
        $sql = "SELECT id, nombres, identificacion, telefono, relacion, puede_retirar, observacion
                FROM alumnos_representantes
                WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncRepresentantes(int $idAlumno, int $idEmpresa, array $representantes, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_representantes SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($representantes)) {
            return;
        }

        $sql = "INSERT INTO alumnos_representantes
                    (id_alumno, id_empresa, nombres, identificacion, telefono, relacion, puede_retirar, observacion, created_by, updated_by)
                VALUES (:a, :e, :nom, :ide, :tel, :rel, :ret, :obs, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($representantes as $r) {
            $nombres = trim((string) ($r['nombres'] ?? ''));
            if ($nombres === '') {
                continue;
            }
            $st->bindValue(':a', $idAlumno, PDO::PARAM_INT);
            $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
            $st->bindValue(':nom', $nombres);
            $st->bindValue(':ide', trim((string) ($r['identificacion'] ?? '')) ?: null);
            $st->bindValue(':tel', trim((string) ($r['telefono'] ?? '')) ?: null);
            $st->bindValue(':rel', trim((string) ($r['relacion'] ?? '')) ?: null);
            // PARAM_BOOL explícito: un false de PHP llega vacío a PostgreSQL si no.
            $st->bindValue(':ret', !empty($r['puede_retirar']), PDO::PARAM_BOOL);
            $st->bindValue(':obs', trim((string) ($r['observacion'] ?? '')) ?: null);
            $st->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $st->execute();
        }
    }

    // ------------------------------------------------------------------
    // Servicios/productos predeterminados a facturar.
    // ------------------------------------------------------------------

    private ?bool $hayEsquemaFacturacion = null;

    /**
     * alumnos_facturas llega con database/migrations/20260924_alumnos_facturacion.sql. Mismo criterio que
     * existeTablaRepresentantes(): si el código se despliega antes que el SQL,
     * nada de lo que ya funcionaba se rompe.
     */
    public function existeEsquemaFacturacion(): bool
    {
        if ($this->hayEsquemaFacturacion === null) {
            $st = $this->db->query(
                "SELECT to_regclass('public.alumnos_facturas') IS NOT NULL"
            );
            $this->hayEsquemaFacturacion = (bool) $st->fetchColumn();
        }
        return $this->hayEsquemaFacturacion;
    }

    private ?bool $hayInfoAdicional = null;

    /**
     * alumnos.info_adicional + alumnos_servicios.detalle/id_tarifa_iva (mismo
     * script 20260924_alumnos_facturacion.sql). Sin ellas no se leen ni se escriben.
     */
    public function existeColumnasFacturacion(): bool
    {
        if ($this->hayInfoAdicional === null) {
            $st = $this->db->query(
                "SELECT (SELECT count(*) FROM information_schema.columns
                          WHERE (table_name = 'alumnos' AND column_name = 'info_adicional')
                             OR (table_name = 'alumnos_servicios' AND column_name IN ('detalle', 'id_tarifa_iva'))) = 3"
            );
            $this->hayInfoAdicional = (bool) $st->fetchColumn();
        }
        return $this->hayInfoAdicional;
    }

    /** Filas concepto/detalle que llevan las facturas del alumno (null = por defecto). */
    public function guardarInfoAdicional(int $id, int $idEmpresa, ?array $filas): void
    {
        $st = $this->db->prepare("UPDATE {$this->table} SET info_adicional = CAST(:j AS jsonb)
                                   WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([
            ':j'  => $filas === null ? null : json_encode(array_values($filas), JSON_UNESCAPED_UNICODE),
            ':id' => $id,
            ':e'  => $idEmpresa,
        ]);
    }

    /**
     * Ficha del alumno con el nombre del cliente que factura (el buscador de la
     * pestaña Facturación lo muestra al editar; findById() solo trae a.*).
     */
    public function findDetalle(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT a.*, cli.nombre AS representante_nombre, cli.identificacion AS representante_identificacion
                FROM {$this->table} a
                LEFT JOIN clientes cli ON cli.id = a.id_cliente
                WHERE a.id = :id AND a.id_empresa = :id_empresa AND a.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getServicios(int $idAlumno, int $idEmpresa): array
    {
        $tarifa = $this->existeColumnasFacturacion() ? 'COALESCE(s.id_tarifa_iva, p.tarifa_iva)' : 'p.tarifa_iva';
        $sql = "SELECT s.*, p.nombre AS producto_nombre, p.precio_base AS producto_precio_base,
                       p.tarifa_iva AS producto_id_tarifa_iva,
                       {$tarifa} AS id_tarifa_efectiva,
                       COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva
                FROM alumnos_servicios s
                LEFT JOIN productos p ON p.id = s.id_producto
                LEFT JOIN tarifa_iva ti ON ti.id = {$tarifa}
                WHERE s.id_alumno = :a AND s.id_empresa = :e AND s.eliminado = false
                ORDER BY s.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Servicios activos del alumno listos para facturar: producto, precio vigente
     * (override o precio base), tarifa de IVA actual del producto y el cliente que
     * factura (alumnos.id_cliente, pestaña Facturación).
     */
    public function getServiciosParaFacturar(int $idAlumno, int $idEmpresa): array
    {
        $colDetalle = $this->existeColumnasFacturacion() ? 's.detalle' : 'NULL AS detalle';
        $tarifa = $this->existeColumnasFacturacion() ? 'COALESCE(s.id_tarifa_iva, p.tarifa_iva)' : 'p.tarifa_iva';
        $sql = "SELECT s.id, s.id_producto, s.cantidad_default, s.precio_override, {$colDetalle},
                       p.codigo AS codigo_producto, p.nombre AS nombre_producto, p.precio_base,
                       {$tarifa} AS id_tarifa_iva,
                       COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva,
                       COALESCE(ti.codigo, '0') AS codigo_porcentaje,
                       a.id_cliente,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       c.email AS cliente_email
                FROM alumnos_servicios s
                JOIN alumnos a ON a.id = s.id_alumno
                JOIN productos p ON p.id = s.id_producto AND p.id_empresa = s.id_empresa AND p.eliminado = false
                LEFT JOIN tarifa_iva ti ON ti.id = {$tarifa}
                JOIN clientes c ON c.id = a.id_cliente
                               AND c.id_empresa = s.id_empresa AND c.eliminado = false
                WHERE s.id_alumno = :a AND s.id_empresa = :e AND s.eliminado = false AND s.activo = true
                ORDER BY s.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Facturas generadas desde el alumno que siguen vigentes (factura no
     * eliminada ni anulada). Con $periodo, solo las de ese mes.
     */
    public function getFacturasAlumno(int $idAlumno, int $idEmpresa, ?string $periodo = null): array
    {
        $sql = "SELECT af.id, af.id_factura, af.id_cliente, af.periodo, af.importe, af.items, af.created_at,
                       c.nombre AS cliente_nombre,
                       v.establecimiento, v.punto_emision, v.secuencial, v.fecha_emision,
                       v.estado AS estado_factura, v.importe_total
                FROM alumnos_facturas af
                JOIN ventas_cabecera v ON v.id = af.id_factura AND v.id_empresa = af.id_empresa
                LEFT JOIN clientes c ON c.id = af.id_cliente
                WHERE af.id_alumno = :a AND af.id_empresa = :e AND af.eliminado = false
                  AND v.eliminado = false";
        $params = [':a' => $idAlumno, ':e' => $idEmpresa];
        if ($periodo !== null) {
            $sql .= " AND af.periodo = :p AND v.estado <> 'anulado'";
            $params[':p'] = $periodo;
        }
        $sql .= " ORDER BY af.periodo DESC, af.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Líneas de las facturas (código, descripción, texto del ítem, cantidad,
     * precio, descuento, subtotal e IVA), agrupadas por id de factura. Mismo
     * cálculo de IVA por línea que la pestaña Facturas de Suscripciones.
     */
    public function getLineasFacturas(array $idsFactura, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsFactura))));
        if (!$ids) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare(
            "SELECT d.id_venta,
                    COALESCE(TRIM(d.codigo_principal), '') AS codigo,
                    COALESCE(TRIM(d.descripcion), '') AS descripcion,
                    COALESCE(TRIM(d.info_adicional), '') AS detalle,
                    d.cantidad, d.precio_unitario,
                    COALESCE(d.descuento, 0) AS descuento,
                    COALESCE(d.precio_total_sin_impuesto, 0) AS subtotal,
                    COALESCE(imp.iva, 0) AS iva
             FROM ventas_detalle d
             JOIN ventas_cabecera v ON v.id = d.id_venta AND v.id_empresa = ?
             LEFT JOIN LATERAL (
                 SELECT SUM(i.valor) AS iva
                 FROM ventas_detalle_impuestos i
                 WHERE i.id_venta_detalle = d.id AND i.codigo_impuesto = '2'
             ) imp ON true
             WHERE d.id_venta IN ({$ph})
             ORDER BY d.id_venta, d.id"
        );
        $st->execute(array_merge([$idEmpresa], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $out[(int) $l['id_venta']][] = $l;
        }
        return $out;
    }

    public function registrarFacturaAlumno(array $d): int
    {
        $sql = "INSERT INTO alumnos_facturas (id_empresa, id_alumno, id_cliente, id_factura, periodo, importe, items, created_by, updated_by)
                VALUES (:e, :a, :c, :f, :p, :imp, CAST(:items AS jsonb), :u, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $d['id_empresa'],
            ':a'     => $d['id_alumno'],
            ':c'     => $d['id_cliente'],
            ':f'     => $d['id_factura'],
            ':p'     => $d['periodo'],
            ':imp'   => $d['importe'],
            ':items' => json_encode($d['items'] ?? [], JSON_UNESCAPED_UNICODE),
            ':u'     => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function syncServicios(int $idAlumno, int $idEmpresa, array $servicios, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_servicios SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($servicios)) {
            return;
        }

        // «detalle» (texto del ítem) solo si la columna ya existe (SQL 20260924).
        $conDetalle = $this->existeColumnasFacturacion();
        $sql = $conDetalle
            ? "INSERT INTO alumnos_servicios (id_alumno, id_empresa, id_producto, cantidad_default, precio_override, frecuencia, activo, detalle, id_tarifa_iva, created_by, updated_by)
               VALUES (:a, :e, :prod, :cant, :precio, :frec, :act, :det, :tar, :u, :u)"
            : "INSERT INTO alumnos_servicios (id_alumno, id_empresa, id_producto, cantidad_default, precio_override, frecuencia, activo, created_by, updated_by)
               VALUES (:a, :e, :prod, :cant, :precio, :frec, :act, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($servicios as $s) {
            if (empty($s['id_producto'])) {
                continue;
            }
            $params = [
                ':a'      => $idAlumno,
                ':e'      => $idEmpresa,
                ':prod'   => (int) $s['id_producto'],
                ':cant'   => (float) ($s['cantidad_default'] ?? 1),
                ':precio' => ($s['precio_override'] ?? '') !== '' ? (float) $s['precio_override'] : null,
                ':frec'   => $s['frecuencia'] ?? 'mensual',
                ':act'    => (!isset($s['activo']) || $s['activo']) ? 'true' : 'false',
                ':u'      => $idUsuario,
            ];
            if ($conDetalle) {
                $params[':det'] = trim((string) ($s['detalle'] ?? '')) !== '' ? trim((string) $s['detalle']) : null;
                $params[':tar'] = !empty($s['id_tarifa_iva']) ? (int) $s['id_tarifa_iva'] : null;
            }
            $st->execute($params);
        }
    }

    // ------------------------------------------------------------------
    // Documentos adjuntos (altas/bajas individuales, no se sincronizan
    // en bloque porque cada fila representa un archivo ya subido).
    // ------------------------------------------------------------------

    public function getDocumentos(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT * FROM alumnos_documentos
                WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false
                ORDER BY fecha_carga DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarDocumento(int $idAlumno, int $idEmpresa, array $data, int $idUsuario): int
    {
        $sql = "INSERT INTO alumnos_documentos (id_alumno, id_empresa, tipo_documento, nombre_archivo, ruta_archivo, id_usuario)
                VALUES (:a, :e, :tipo, :nombre, :ruta, :u)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':a'      => $idAlumno,
            ':e'      => $idEmpresa,
            ':tipo'   => $data['tipo_documento'],
            ':nombre' => $data['nombre_archivo'],
            ':ruta'   => $data['ruta_archivo'],
            ':u'      => $idUsuario,
        ]);
        return $this->lastInsertId();
    }

    public function eliminarDocumento(int $id, int $idAlumno, int $idEmpresa, int $idUsuario): ?array
    {
        $st = $this->db->prepare("SELECT * FROM alumnos_documentos WHERE id = :id AND id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':a' => $idAlumno, ':e' => $idEmpresa]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            return null;
        }
        $upd = $this->db->prepare("UPDATE alumnos_documentos SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP WHERE id = :id");
        $upd->execute([':u' => $idUsuario, ':id' => $id]);
        return $doc;
    }

    // ------------------------------------------------------------------
    // Catálogos auxiliares para el modal.
    // ------------------------------------------------------------------

    /** Tarifas de IVA activas (catálogo global, sin id_empresa). */
    public function getTarifasIva(): array
    {
        $st = $this->db->query("SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva DESC, id ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tarifa de IVA de cada producto de la empresa: id_producto => id_tarifa_iva. */
    public function getTarifasProductos(array $idsProducto, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        if (!$ids) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, tarifa_iva FROM productos WHERE id_empresa = ? AND eliminado = false AND id IN ($ph)");
        $st->execute(array_merge([$idEmpresa], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = $r['tarifa_iva'] !== null ? (int) $r['tarifa_iva'] : null;
        }
        return $out;
    }

    public function getPuntosEmisionParaSelect(int $idEmpresa): array
    {
        // Series activas como en la Factura de Venta: «001-001» (establecimiento-punto),
        // punto y establecimiento activos, y solo las que tienen secuencial de
        // "Facturas de venta" (sin él no se puede generar la factura del alumno).
        $sql = "SELECT pe.id, e.codigo || '-' || pe.codigo_punto AS serie,
                       pe.nombre, e.nombre AS establecimiento_nombre
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento e ON e.id = pe.id_establecimiento
                WHERE e.id_empresa = :id_empresa
                  AND pe.eliminado = false AND pe.estado = 'activo'
                  AND e.eliminado = false AND e.estado = 'activo'
                  AND EXISTS (SELECT 1 FROM empresa_secuencial s
                               WHERE s.id_punto_emision = pe.id
                                 AND s.tipo_documento = 'Facturas de venta'
                                 AND s.eliminado = false)
                ORDER BY e.codigo ASC, pe.codigo_punto ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
