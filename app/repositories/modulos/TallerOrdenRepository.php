<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo Taller Mecánico (Órdenes de Trabajo).
 *
 * Acceso a datos puro: cabecera de la OT, líneas (repuestos / mano de obra),
 * etapas por departamento, bitácora, checklist de recepción y fotos.
 * Toda consulta filtra por id_empresa y eliminado = false.
 */
class TallerOrdenRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('taller_ordenes');
    }

    // ─── LISTADO PAGINADO ─────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE o.id_empresa = :e AND o.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND o.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (o.numero_orden ILIKE :b OR o.placa ILIKE :b OR o.marca ILIKE :b
                             OR o.modelo ILIKE :b OR o.nombre_usuario ILIKE :b
                             OR c.nombre ILIKE :b OR c.identificacion ILIKE :b
                             OR o.motivo_ingreso ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'orden'        => 'o.numero_orden',
                'placa'        => 'o.placa',
                'cliente'      => 'c.nombre',
                'marca'        => 'o.marca',
                'modelo'       => 'o.modelo',
                'aseguradora'  => 'o.aseguradora',
                'siniestro'    => 'o.numero_siniestro',
            ],
            'exacto' => [
                'estado'       => 'o.estado',
                'tipo'         => 'o.tipo_servicio',
                'prioridad'    => 'o.prioridad',
                'departamento' => 'd.nombre',
                'aprobada'     => 'o.aprobado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), igual que factura.
                'serie'        => "CONCAT(o.establecimiento,'-',o.punto_emision)",
            ],
            'fecha'  => [
                'fecha'        => 'o.fecha_ingreso',
                'entrega'      => 'o.fecha_entrega',
            ],
            'numerico' => [
                'total'        => 'o.total',
                'km'           => 'o.kilometraje',
                // Comparación EXACTA sin ceros a la izquierda ("298" no matchea "000000913").
                'secuencial'   => 'o.secuencial::numeric',
            ],
        ]);

        $joins = "LEFT JOIN clientes c            ON c.id = o.id_cliente
                  LEFT JOIN taller_departamentos d ON d.id = o.id_departamento_actual";

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM taller_ordenes o $joins $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $colMap = [
            'fecha_ingreso' => 'o.fecha_ingreso',
            'numero_orden'  => 'o.numero_orden',
            'placa'         => 'o.placa',
            'vehiculo'      => 'o.marca',
            'cliente'       => 'c.nombre',
            'departamento'  => 'd.orden',
            'estado'        => 'o.estado',
            'total'         => 'o.total',
        ];
        $sort = $colMap[$ordenCol] ?? 'o.fecha_ingreso';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $limit = '';
        if ($perPage > 0) {
            $limit = 'LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }

        $sql = "SELECT o.*,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       d.nombre AS departamento_nombre, d.color AS departamento_color
                FROM taller_ordenes o
                $joins
                $where
                ORDER BY $sort $dir, o.id DESC
                $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    // ─── TABLERO: órdenes activas agrupadas por departamento ──────────────────

    /**
     * Órdenes en curso con el resumen que necesita la tarjeta del tablero.
     * Se excluyen las cerradas (entregada/facturada/anulada).
     */
    public function getTablero(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE o.id_empresa = :e AND o.eliminado = false
                   AND o.estado NOT IN ('entregada','facturada','anulada')";
        $params = [':e' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $where .= " AND o.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $sql = "SELECT o.id, o.numero_orden, o.placa, o.marca, o.modelo, o.anio, o.color,
                       o.estado, o.prioridad, o.tipo_servicio, o.aprobado,
                       o.fecha_ingreso, o.fecha_estimada_entrega, o.total,
                       o.id_departamento_actual, o.motivo_ingreso,
                       c.nombre AS cliente_nombre,
                       e.fecha_inicio AS etapa_inicio, e.estado AS etapa_estado,
                       (SELECT COUNT(*) FROM taller_ordenes_detalle td
                         WHERE td.id_orden = o.id AND td.eliminado = false
                           AND td.estado_linea = 'sugerida') AS lineas_pendientes
                FROM taller_ordenes o
                LEFT JOIN clientes c ON c.id = o.id_cliente
                LEFT JOIN taller_ordenes_etapas e
                       ON e.id_orden = o.id
                      AND e.id_departamento = o.id_departamento_actual
                      AND e.eliminado = false
                      AND e.estado IN ('pendiente','en_proceso')
                $where
                ORDER BY
                    CASE o.prioridad WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
                    o.fecha_ingreso ASC, o.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Órdenes que están ahora mismo en un departamento (pantalla de la tablet).
     * Trae lo que el operario necesita ver: qué vehículo es, por qué entró, qué
     * lleva hecho su departamento y si el presupuesto está aprobado.
     */
    public function getOrdenesPorDepartamento(int $idEmpresa, int $idDepartamento): array
    {
        $sql = "SELECT o.id, o.numero_orden, o.placa, o.marca, o.modelo, o.anio, o.color,
                       o.kilometraje, o.estado, o.prioridad, o.aprobado, o.tipo_servicio,
                       o.fecha_ingreso, o.fecha_estimada_entrega, o.motivo_ingreso,
                       o.diagnostico_texto,
                       c.nombre AS cliente_nombre,
                       e.id AS id_etapa, e.estado AS etapa_estado, e.fecha_inicio AS etapa_inicio,
                       e.trabajo_realizado, e.id_empleado_responsable,
                       (SELECT COUNT(*) FROM taller_ordenes_detalle td
                         WHERE td.id_orden = o.id AND td.id_departamento = :d
                           AND td.eliminado = false) AS lineas_departamento
                FROM taller_ordenes o
                JOIN taller_ordenes_etapas e
                     ON e.id_orden = o.id AND e.id_departamento = :d AND e.eliminado = false
                    AND e.estado IN ('pendiente','en_proceso')
                LEFT JOIN clientes c ON c.id = o.id_cliente
                WHERE o.id_empresa = :e AND o.eliminado = false
                  AND o.estado NOT IN ('entregada','facturada','anulada')
                ORDER BY
                    CASE o.prioridad WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
                    e.fecha_asignacion ASC, o.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':d' => $idDepartamento]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── AUTOCOMPLETES ────────────────────────────────────────────────────────

    public function buscarVehiculos(int $idEmpresa, string $q): array
    {
        $sql = "SELECT v.id, v.placa, v.marca, v.modelo, v.chasis, v.anio, v.color,
                       v.motor, v.propietario, v.correo, v.telefono, v.kilometraje_actual,
                       v.id_cliente, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion
                FROM vehiculos v
                LEFT JOIN clientes c ON c.id = v.id_cliente AND c.eliminado = false
                WHERE v.id_empresa = :e AND v.eliminado = false AND v.estado = 'activo'
                  AND (v.placa ILIKE :q OR v.marca ILIKE :q OR v.modelo ILIKE :q OR v.propietario ILIKE :q)
                ORDER BY v.placa ASC
                LIMIT 15";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Historial de servicios de un vehículo (lo pregunta siempre el asesor). */
    public function getHistorialVehiculo(int $idVehiculo, int $idEmpresa, int $excluirOrden = 0): array
    {
        $sql = "SELECT o.id, o.numero_orden, o.fecha_ingreso, o.fecha_entrega, o.kilometraje,
                       o.estado, o.total, o.motivo_ingreso, o.numero_documento
                FROM taller_ordenes o
                WHERE o.id_vehiculo = :v AND o.id_empresa = :e AND o.eliminado = false
                  AND o.id <> :x
                ORDER BY o.fecha_ingreso DESC
                LIMIT 20";
        $st = $this->db->prepare($sql);
        $st->execute([':v' => $idVehiculo, ':e' => $idEmpresa, ':x' => $excluirOrden]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── SECUENCIAL ───────────────────────────────────────────────────────────

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM taller_ordenes
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = false";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    // ─── CATÁLOGOS AUXILIARES ─────────────────────────────────────────────────

    public function getTarifaIvaProducto(int $idProducto): ?array
    {
        $sql = "SELECT ti.id, ti.porcentaje_iva, ti.codigo
                FROM productos p JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idProducto]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTarifaIvaById(int $idTarifa): ?array
    {
        $st = $this->db->prepare("SELECT id, porcentaje_iva, codigo FROM tarifa_iva WHERE id = ?");
        $st->execute([$idTarifa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTarifaIvaByPorcentaje(float $porcentaje): ?array
    {
        $st = $this->db->prepare("SELECT id, porcentaje_iva, codigo FROM tarifa_iva WHERE porcentaje_iva = ? AND status = 1 ORDER BY id LIMIT 1");
        $st->execute([$porcentaje]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFormasPago(): array
    {
        return $this->db->query("SELECT codigo, nombre FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(int $idEmpresa): array
    {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true AND id_empresa = :id_empresa ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Costo unitario del producto, para calcular el margen de la OT. */
    public function getCostoProducto(int $idProducto, int $idEmpresa): float
    {
        $st = $this->db->prepare("SELECT COALESCE(costo_producto, 0) FROM productos WHERE id = :p AND id_empresa = :e");
        $st->execute([':p' => $idProducto, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v === false ? 0.0 : (float) $v;
    }

    // ─── CABECERA ─────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $sql = "INSERT INTO taller_ordenes (" . implode(', ', $fields) . ")
                VALUES (:" . implode(', :', $fields) . ") RETURNING id";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT o.*,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       c.direccion AS cliente_direccion, c.email AS cliente_email,
                       c.telefono AS cliente_telefono,
                       v.placa AS vehiculo_placa, v.propietario AS vehiculo_propietario,
                       d.nombre AS departamento_nombre, d.color AS departamento_color,
                       ua.nombre AS asesor_nombre,
                       ea.nombres_apellidos AS empleado_asesor_nombre,
                       ej.nombres_apellidos AS empleado_jefe_nombre
                FROM taller_ordenes o
                LEFT JOIN clientes c             ON c.id = o.id_cliente
                LEFT JOIN vehiculos v            ON v.id = o.id_vehiculo
                LEFT JOIN taller_departamentos d ON d.id = o.id_departamento_actual
                LEFT JOIN usuarios ua            ON ua.id = o.id_asesor
                LEFT JOIN empleados ea           ON ea.id = o.id_empleado_asesor
                LEFT JOIN empleados ej           ON ej.id = o.id_empleado_jefe
                WHERE o.id = :id AND o.id_empresa = :e AND o.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        if (empty($data)) return;
        $sets = [];
        foreach (array_keys($data) as $k) {
            $sets[] = "$k = :$k";
        }
        $st = $this->db->prepare("UPDATE taller_ordenes SET " . implode(', ', $sets) . " WHERE id = :id_ AND id_empresa = :e_");
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function updateTotales(int $id, int $idEmpresa, array $t): void
    {
        $sql = "UPDATE taller_ordenes
                SET subtotal_repuestos = :sr, subtotal_mano_obra = :sm, subtotal = :s,
                    descuento = :d, iva = :i, total = :t
                WHERE id = :id AND id_empresa = :e";
        $this->db->prepare($sql)->execute([
            ':sr' => $t['subtotal_repuestos'], ':sm' => $t['subtotal_mano_obra'],
            ':s'  => $t['subtotal'], ':d' => $t['descuento'], ':i' => $t['iva'], ':t' => $t['total'],
            ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario, bool $setFechaEntrega = false): void
    {
        $extra = $setFechaEntrega ? ", fecha_entrega = CURRENT_TIMESTAMP" : "";
        $sql = "UPDATE taller_ordenes
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP $extra
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function updateDepartamentoActual(int $id, int $idEmpresa, ?int $idDepartamento, int $idUsuario): void
    {
        $sql = "UPDATE taller_ordenes
                SET id_departamento_actual = :d, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':d' => $idDepartamento, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /** Registra la aprobación del presupuesto por parte del cliente. */
    public function registrarAprobacion(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE taller_ordenes
                SET aprobado = true, aprobado_por = :por, aprobado_medio = :medio,
                    aprobado_fecha = CURRENT_TIMESTAMP, aprobado_usuario = :u,
                    aprobado_observacion = :obs, estado = 'aprobada',
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':por'   => $d['aprobado_por'],
            ':medio' => $d['aprobado_medio'],
            ':u'     => $d['id_usuario'],
            ':obs'   => $d['aprobado_observacion'] ?? null,
            ':id'    => $id,
            ':e'     => $idEmpresa,
        ]);
    }

    public function marcarDocumentoGenerado(int $id, int $idEmpresa, string $tipo, int $idDoc, string $numero, int $idUsuario): void
    {
        $sql = "UPDATE taller_ordenes
                SET tipo_documento = :tipo, id_documento = :idd, numero_documento = :num,
                    estado = 'facturada', updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':tipo' => $tipo, ':idd' => $idDoc, ':num' => $numero,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE taller_ordenes
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ─── DETALLE (repuestos / mano de obra) ───────────────────────────────────

    public function insertDetalle(array $d): int
    {
        $sql = "INSERT INTO taller_ordenes_detalle (
                    id_orden, id_empresa, id_departamento, id_usuario_registro, id_empleado_tecnico,
                    tipo_linea, id_producto, es_libre, descripcion, id_bodega,
                    cantidad, horas, precio_unitario, costo_unitario, descuento,
                    porcentaje_iva, valor_iva, total_linea, id_tarifa_iva,
                    estado_linea, facturable, provisto_cliente, observacion,
                    created_by, updated_by
                ) VALUES (
                    :ido, :e, :dep, :ureg, :tec,
                    :tipo, :prod, :libre, :desc, :bod,
                    :cant, :horas, :pu, :cu, :dscto,
                    :piva, :viva, :tot, :tar,
                    :est, :fact, :prov, :obs,
                    :ureg, :ureg
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido'   => $d['id_orden'],
            ':e'     => $d['id_empresa'],
            ':dep'   => $d['id_departamento'] ?? null,
            ':ureg'  => $d['id_usuario_registro'] ?? null,
            ':tec'   => $d['id_empleado_tecnico'] ?? null,
            ':tipo'  => $d['tipo_linea'] ?? 'repuesto',
            ':prod'  => $d['id_producto'] ?? null,
            ':libre' => !empty($d['es_libre']) ? 'true' : 'false',
            ':desc'  => $d['descripcion'],
            ':bod'   => $d['id_bodega'] ?? null,
            ':cant'  => $d['cantidad'] ?? 1,
            ':horas' => $d['horas'] ?? 0,
            ':pu'    => $d['precio_unitario'] ?? 0,
            ':cu'    => $d['costo_unitario'] ?? 0,
            ':dscto' => $d['descuento'] ?? 0,
            ':piva'  => $d['porcentaje_iva'] ?? 0,
            ':viva'  => $d['valor_iva'] ?? 0,
            ':tot'   => $d['total_linea'] ?? 0,
            ':tar'   => $d['id_tarifa_iva'] ?? null,
            ':est'   => $d['estado_linea'] ?? 'sugerida',
            ':fact'  => (!isset($d['facturable']) || $d['facturable']) ? 'true' : 'false',
            ':prov'  => !empty($d['provisto_cliente']) ? 'true' : 'false',
            ':obs'   => $d['observacion'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function updateDetalle(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE taller_ordenes_detalle SET
                    descripcion = :desc, cantidad = :cant, horas = :horas,
                    precio_unitario = :pu, descuento = :dscto,
                    porcentaje_iva = :piva, valor_iva = :viva, total_linea = :tot,
                    id_tarifa_iva = :tar, id_bodega = :bod, id_empleado_tecnico = :tec,
                    facturable = :fact, provisto_cliente = :prov, observacion = :obs,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':desc'  => $d['descripcion'],
            ':cant'  => $d['cantidad'],
            ':horas' => $d['horas'] ?? 0,
            ':pu'    => $d['precio_unitario'],
            ':dscto' => $d['descuento'] ?? 0,
            ':piva'  => $d['porcentaje_iva'] ?? 0,
            ':viva'  => $d['valor_iva'] ?? 0,
            ':tot'   => $d['total_linea'] ?? 0,
            ':tar'   => $d['id_tarifa_iva'] ?? null,
            ':bod'   => $d['id_bodega'] ?? null,
            ':tec'   => $d['id_empleado_tecnico'] ?? null,
            ':fact'  => (!isset($d['facturable']) || $d['facturable']) ? 'true' : 'false',
            ':prov'  => !empty($d['provisto_cliente']) ? 'true' : 'false',
            ':obs'   => $d['observacion'] ?? null,
            ':u'     => $d['id_usuario'],
            ':id'    => $id,
            ':e'     => $idEmpresa,
        ]);
    }

    public function findDetalle(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM taller_ordenes_detalle
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Líneas de la OT. Con $idDepartamento se limita a las de un departamento
     * (lo que ve el operario en su tablet).
     */
    public function getDetalles(int $idOrden, int $idEmpresa, ?int $idDepartamento = null): array
    {
        $where = "WHERE d.id_orden = :id AND d.id_empresa = :e AND d.eliminado = false";
        $params = [':id' => $idOrden, ':e' => $idEmpresa];
        if ($idDepartamento !== null) {
            $where .= " AND d.id_departamento = :dep";
            $params[':dep'] = $idDepartamento;
        }

        $sql = "SELECT d.*,
                       p.codigo AS producto_codigo,
                       b.nombre AS bodega_nombre,
                       dep.nombre AS departamento_nombre, dep.color AS departamento_color,
                       u.nombre AS usuario_registro_nombre,
                       emp.nombres_apellidos AS tecnico_nombre
                FROM taller_ordenes_detalle d
                LEFT JOIN productos p            ON p.id = d.id_producto
                LEFT JOIN bodegas b              ON b.id = d.id_bodega
                LEFT JOIN taller_departamentos dep ON dep.id = d.id_departamento
                LEFT JOIN usuarios u             ON u.id = d.id_usuario_registro
                LEFT JOIN empleados emp          ON emp.id = d.id_empleado_tecnico
                $where
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cambia el estado de una línea (aprobación / rechazo / ejecución). */
    public function updateEstadoLinea(int $id, int $idEmpresa, string $estado, int $idUsuario, ?string $motivo = null): void
    {
        $sql = "UPDATE taller_ordenes_detalle
                SET estado_linea = :est, motivo_rechazo = :mot,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':est' => $estado, ':mot' => $motivo, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /** Aprueba en bloque todas las líneas sugeridas de la orden. */
    public function aprobarLineasPendientes(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE taller_ordenes_detalle
                SET estado_linea = 'aprobada', updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id_orden = :id AND id_empresa = :e AND eliminado = false
                  AND estado_linea = 'sugerida'";
        $this->db->prepare($sql)->execute([':id' => $idOrden, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    /** Marca el resultado del descuento de stock de una línea. */
    public function marcarInventarioLinea(int $id, int $idEmpresa, bool $aplicado, ?int $idKardex): void
    {
        $sql = "UPDATE taller_ordenes_detalle
                SET inventario_aplicado = :ap, id_kardex = :k, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e";
        $this->db->prepare($sql)->execute([
            ':ap' => $aplicado ? 'true' : 'false', ':k' => $idKardex, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function eliminarDetalle(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE taller_ordenes_detalle
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    /** Totales de la OT: solo lo aprobado/ejecutado y facturable entra al total. */
    public function calcularTotales(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN tipo_linea IN ('repuesto','insumo','tercero')
                                      THEN (precio_unitario * cantidad - descuento) ELSE 0 END), 0) AS subtotal_repuestos,
                    COALESCE(SUM(CASE WHEN tipo_linea = 'mano_obra'
                                      THEN (precio_unitario * cantidad - descuento) ELSE 0 END), 0) AS subtotal_mano_obra,
                    COALESCE(SUM(precio_unitario * cantidad - descuento), 0) AS subtotal,
                    COALESCE(SUM(descuento), 0) AS descuento,
                    COALESCE(SUM(valor_iva), 0)  AS iva,
                    COALESCE(SUM(total_linea), 0) AS total
                FROM taller_ordenes_detalle
                WHERE id_orden = :id AND id_empresa = :e AND eliminado = false
                  AND facturable = true AND estado_linea IN ('aprobada','ejecutada')";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'subtotal_repuestos' => round((float) ($row['subtotal_repuestos'] ?? 0), 2),
            'subtotal_mano_obra' => round((float) ($row['subtotal_mano_obra'] ?? 0), 2),
            'subtotal'           => round((float) ($row['subtotal'] ?? 0), 2),
            'descuento'          => round((float) ($row['descuento'] ?? 0), 2),
            'iva'                => round((float) ($row['iva'] ?? 0), 2),
            'total'              => round((float) ($row['total'] ?? 0), 2),
        ];
    }

    // ─── ETAPAS (recorrido por departamentos) ─────────────────────────────────

    public function insertEtapa(array $d): int
    {
        $sql = "INSERT INTO taller_ordenes_etapas
                    (id_orden, id_empresa, id_departamento, secuencia, estado,
                     id_empleado_responsable, created_by, updated_by)
                VALUES (:ido, :e, :dep, :sec, :est, :emp, :u, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido' => $d['id_orden'],
            ':e'   => $d['id_empresa'],
            ':dep' => $d['id_departamento'],
            ':sec' => (int) ($d['secuencia'] ?? 0),
            ':est' => $d['estado'] ?? 'pendiente',
            ':emp' => $d['id_empleado_responsable'] ?? null,
            ':u'   => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function getEtapas(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT e.*, dep.nombre AS departamento_nombre, dep.color AS departamento_color,
                       dep.icono AS departamento_icono,
                       ui.nombre AS usuario_inicio_nombre, uf.nombre AS usuario_fin_nombre,
                       emp.nombres_apellidos AS responsable_nombre
                FROM taller_ordenes_etapas e
                LEFT JOIN taller_departamentos dep ON dep.id = e.id_departamento
                LEFT JOIN usuarios ui  ON ui.id = e.id_usuario_inicio
                LEFT JOIN usuarios uf  ON uf.id = e.id_usuario_fin
                LEFT JOIN empleados emp ON emp.id = e.id_empleado_responsable
                WHERE e.id_orden = :id AND e.id_empresa = :e AND e.eliminado = false
                ORDER BY e.secuencia ASC, e.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEtapa(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM taller_ordenes_etapas WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Etapa abierta (pendiente o en proceso) de una orden en un departamento. */
    public function findEtapaAbierta(int $idOrden, int $idDepartamento, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM taller_ordenes_etapas
                WHERE id_orden = :o AND id_departamento = :d AND id_empresa = :e
                  AND eliminado = false AND estado IN ('pendiente','en_proceso')
                ORDER BY id DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':o' => $idOrden, ':d' => $idDepartamento, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function iniciarEtapa(int $id, int $idEmpresa, int $idUsuario, ?int $idEmpleado): void
    {
        $sql = "UPDATE taller_ordenes_etapas
                SET estado = 'en_proceso', fecha_inicio = COALESCE(fecha_inicio, CURRENT_TIMESTAMP),
                    id_usuario_inicio = COALESCE(id_usuario_inicio, :u),
                    id_empleado_responsable = COALESCE(:emp, id_empleado_responsable),
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':u' => $idUsuario, ':emp' => $idEmpleado, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function terminarEtapa(int $id, int $idEmpresa, int $idUsuario, array $d): void
    {
        $sql = "UPDATE taller_ordenes_etapas
                SET estado = 'terminada', fecha_fin = CURRENT_TIMESTAMP,
                    fecha_inicio = COALESCE(fecha_inicio, CURRENT_TIMESTAMP),
                    id_usuario_fin = :u,
                    id_empleado_responsable = COALESCE(:emp, id_empleado_responsable),
                    trabajo_realizado = :trab, observaciones = :obs,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':u'    => $idUsuario,
            ':emp'  => $d['id_empleado_responsable'] ?? null,
            ':trab' => $d['trabajo_realizado'] ?? null,
            ':obs'  => $d['observaciones'] ?? null,
            ':id'   => $id,
            ':e'    => $idEmpresa,
        ]);
    }

    /** Guarda el avance del trabajo sin cerrar la etapa. */
    public function guardarAvanceEtapa(int $id, int $idEmpresa, int $idUsuario, array $d): void
    {
        $sql = "UPDATE taller_ordenes_etapas
                SET trabajo_realizado = :trab, observaciones = :obs,
                    id_empleado_responsable = COALESCE(:emp, id_empleado_responsable),
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':trab' => $d['trabajo_realizado'] ?? null,
            ':obs'  => $d['observaciones'] ?? null,
            ':emp'  => $d['id_empleado_responsable'] ?? null,
            ':u'    => $idUsuario,
            ':id'   => $id,
            ':e'    => $idEmpresa,
        ]);
    }

    public function haySiguienteSecuencia(int $idOrden, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(secuencia), 0) + 1
             FROM taller_ordenes_etapas
             WHERE id_orden = :o AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /** ¿Quedan etapas sin terminar? Sirve para saber si la OT puede cerrarse. */
    public function tieneEtapasAbiertas(int $idOrden, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM taller_ordenes_etapas
             WHERE id_orden = :o AND id_empresa = :e AND eliminado = false
               AND estado IN ('pendiente','en_proceso') LIMIT 1"
        );
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    // ─── BITÁCORA ─────────────────────────────────────────────────────────────

    public function insertBitacora(array $d): int
    {
        $sql = "INSERT INTO taller_ordenes_bitacora
                    (id_orden, id_empresa, id_departamento, id_usuario, tipo_evento, concepto, detalle)
                VALUES (:ido, :e, :dep, :u, :tipo, :con, :det)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido'  => $d['id_orden'],
            ':e'    => $d['id_empresa'],
            ':dep'  => $d['id_departamento'] ?? null,
            ':u'    => $d['id_usuario'] ?? null,
            ':tipo' => $d['tipo_evento'] ?? 'nota',
            ':con'  => $d['concepto'],
            ':det'  => $d['detalle'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function getBitacora(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT b.*, u.nombre AS usuario_nombre,
                       dep.nombre AS departamento_nombre, dep.color AS departamento_color
                FROM taller_ordenes_bitacora b
                LEFT JOIN usuarios u ON u.id = b.id_usuario
                LEFT JOIN taller_departamentos dep ON dep.id = b.id_departamento
                WHERE b.id_orden = :id AND b.id_empresa = :e AND b.eliminado = false
                ORDER BY b.fecha ASC, b.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eliminarBitacora(int $id, int $idEmpresa): void
    {
        $this->db->prepare("UPDATE taller_ordenes_bitacora SET eliminado = true WHERE id = :id AND id_empresa = :e")
                 ->execute([':id' => $id, ':e' => $idEmpresa]);
    }

    // ─── CHECKLIST DE RECEPCIÓN ───────────────────────────────────────────────

    public function insertChecklist(array $d): int
    {
        $sql = "INSERT INTO taller_ordenes_checklist (id_orden, id_empresa, grupo, item, valor, observacion, orden)
                VALUES (:ido, :e, :g, :i, :v, :o, :ord) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido' => $d['id_orden'],
            ':e'   => $d['id_empresa'],
            ':g'   => $d['grupo'] ?? 'accesorios',
            ':i'   => $d['item'],
            ':v'   => $d['valor'] ?? 'no',
            ':o'   => $d['observacion'] ?? null,
            ':ord' => (int) ($d['orden'] ?? 0),
        ]);
        return (int) $st->fetchColumn();
    }

    public function getChecklist(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT id, grupo, item, valor, observacion, orden
                FROM taller_ordenes_checklist
                WHERE id_orden = :id AND id_empresa = :e AND eliminado = false
                ORDER BY orden ASC, id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function limpiarChecklist(int $idOrden, int $idEmpresa): void
    {
        $this->db->prepare("UPDATE taller_ordenes_checklist SET eliminado = true WHERE id_orden = :id AND id_empresa = :e AND eliminado = false")
                 ->execute([':id' => $idOrden, ':e' => $idEmpresa]);
    }

    // ─── FOTOS ────────────────────────────────────────────────────────────────

    public function insertFoto(array $d): int
    {
        $sql = "INSERT INTO taller_ordenes_fotos
                    (id_orden, id_empresa, id_departamento, momento, ruta_archivo,
                     nombre_original, descripcion, id_usuario)
                VALUES (:ido, :e, :dep, :mom, :ruta, :nom, :desc, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido'  => $d['id_orden'],
            ':e'    => $d['id_empresa'],
            ':dep'  => $d['id_departamento'] ?? null,
            ':mom'  => $d['momento'] ?? 'ingreso',
            ':ruta' => $d['ruta_archivo'],
            ':nom'  => $d['nombre_original'] ?? null,
            ':desc' => $d['descripcion'] ?? null,
            ':u'    => $d['id_usuario'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function getFotos(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT f.*, u.nombre AS usuario_nombre, dep.nombre AS departamento_nombre
                FROM taller_ordenes_fotos f
                LEFT JOIN usuarios u ON u.id = f.id_usuario
                LEFT JOIN taller_departamentos dep ON dep.id = f.id_departamento
                WHERE f.id_orden = :id AND f.id_empresa = :e AND f.eliminado = false
                ORDER BY f.created_at ASC, f.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFoto(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM taller_ordenes_fotos WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function eliminarFoto(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE taller_ordenes_fotos
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ─── INDICADORES ──────────────────────────────────────────────────────────

    /** Tiempo promedio (horas) y volumen de OT por departamento, en un rango. */
    public function getTiemposPorDepartamento(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT dep.id, dep.nombre, dep.color,
                       COUNT(e.id) AS etapas,
                       COUNT(*) FILTER (WHERE e.estado = 'terminada') AS terminadas,
                       ROUND(AVG(EXTRACT(EPOCH FROM (e.fecha_fin - e.fecha_inicio)) / 3600.0)::numeric, 2) AS horas_promedio
                FROM taller_ordenes_etapas e
                JOIN taller_departamentos dep ON dep.id = e.id_departamento
                JOIN taller_ordenes o ON o.id = e.id_orden AND o.eliminado = false
                WHERE e.id_empresa = :e AND e.eliminado = false
                  AND o.fecha_ingreso >= :desde AND o.fecha_ingreso <= :hasta
                GROUP BY dep.id, dep.nombre, dep.color, dep.orden
                ORDER BY dep.orden ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':desde' => $desde, ':hasta' => $hasta . ' 23:59:59']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Producción por técnico: líneas ejecutadas, horas y valor generado. */
    public function getProductividadTecnicos(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT emp.id, emp.nombres_apellidos AS tecnico,
                       COUNT(d.id) AS lineas,
                       COALESCE(SUM(d.horas), 0) AS horas,
                       COALESCE(SUM(d.total_linea), 0) AS valor
                FROM taller_ordenes_detalle d
                JOIN empleados emp ON emp.id = d.id_empleado_tecnico
                JOIN taller_ordenes o ON o.id = d.id_orden AND o.eliminado = false
                WHERE d.id_empresa = :e AND d.eliminado = false
                  AND d.estado_linea IN ('aprobada','ejecutada')
                  AND o.fecha_ingreso >= :desde AND o.fecha_ingreso <= :hasta
                GROUP BY emp.id, emp.nombres_apellidos
                ORDER BY valor DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':desde' => $desde, ':hasta' => $hasta . ' 23:59:59']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
