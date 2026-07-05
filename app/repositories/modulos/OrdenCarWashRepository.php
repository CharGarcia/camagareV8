<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo Servicio Car-Wash.
 *
 * Una orden registra el ingreso de un vehículo al lavadero con sus servicios,
 * productos, novedades y próxima cita. El numero_orden es un correlativo INTERNO
 * por empresa (no es un secuencial SRI).
 */
class OrdenCarWashRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('carwash_ordenes');
    }

    // ─── LISTADO PAGINADO (historial) ─────────────────────────────────────────

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
            $where .= " AND (o.numero_orden ILIKE :b OR o.placa ILIKE :b OR c.nombre ILIKE :b OR c.identificacion ILIKE :b OR o.estado ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente' => 'c.nombre',
                'orden'   => 'o.numero_orden',
                'placa'   => 'o.placa',
            ],
            'exacto' => [
                'estado'  => 'o.estado',
            ],
            'fecha'  => [
                'fecha'   => 'o.fecha_ingreso',
            ],
            'numerico' => [
                'total'   => 'o.total',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM carwash_ordenes o
                     LEFT JOIN clientes c ON c.id = o.id_cliente
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $colMap = [
            'fecha_ingreso' => 'o.fecha_ingreso',
            'numero_orden'  => 'o.numero_orden',
            'placa'         => 'o.placa',
            'cliente'       => 'c.nombre',
            'estado'        => 'o.estado',
            'total'         => 'o.total',
        ];
        $sort = $colMap[$ordenCol] ?? 'o.fecha_ingreso';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT o.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion
                FROM carwash_ordenes o
                LEFT JOIN clientes c ON c.id = o.id_cliente
                $where
                ORDER BY $sort $dir, o.id DESC
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    // ─── TABLERO OPERATIVO (órdenes activas por estado) ───────────────────────

    /**
     * Órdenes activas (no facturadas ni anuladas) para la vista tablero.
     * Devuelve también un resumen de servicios/productos por orden.
     */
    public function getTablero(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE o.id_empresa = :e AND o.eliminado = false AND o.estado IN ('ingresado','en_proceso','terminado')";
        $params = [':e' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $where .= " AND o.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $sql = "SELECT o.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       (SELECT string_agg(d.descripcion, ', ')
                        FROM carwash_ordenes_detalle d
                        WHERE d.id_orden = o.id AND d.eliminado = false) AS servicios_resumen
                FROM carwash_ordenes o
                LEFT JOIN clientes c ON c.id = o.id_cliente
                $where
                ORDER BY o.fecha_ingreso ASC, o.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── BÚSQUEDA DE VEHÍCULOS (autocomplete del modal) ───────────────────────

    public function buscarVehiculos(int $idEmpresa, string $q): array
    {
        $sql = "SELECT id, placa, marca, chasis, anio, propietario, correo, telefono
                FROM vehiculos
                WHERE id_empresa = :e AND eliminado = false AND estado = 'activo'
                  AND (placa ILIKE :q OR marca ILIKE :q OR propietario ILIKE :q)
                ORDER BY placa ASC
                LIMIT 15";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── SECUENCIAL (mismas reglas que recibo de venta) ───────────────────────

    /** Verifica si el secuencial ya existe para el punto de emisión (evita duplicados). */
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM carwash_ordenes
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

    /** Tarifa de IVA (id, porcentaje_iva, codigo SRE) configurada en el producto. */
    public function getTarifaIvaProducto(int $idProducto): ?array
    {
        $sql = "SELECT ti.id, ti.porcentaje_iva, ti.codigo
                FROM productos p JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idProducto]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Tarifa de IVA por id. */
    public function getTarifaIvaById(int $idTarifa): ?array
    {
        $st = $this->db->prepare("SELECT id, porcentaje_iva, codigo FROM tarifa_iva WHERE id = ?");
        $st->execute([$idTarifa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Tarifa de IVA por porcentaje (para ítems libres sin id_tarifa_iva). */
    public function getTarifaIvaByPorcentaje(float $porcentaje): ?array
    {
        $st = $this->db->prepare("SELECT id, porcentaje_iva, codigo FROM tarifa_iva WHERE porcentaje_iva = ? AND status = 1 ORDER BY id LIMIT 1");
        $st->execute([$porcentaje]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Formas de pago SRI (para el mini-formulario de emisión). */
    public function getFormasPago(): array
    {
        return $this->db->query("SELECT codigo, nombre FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tarifas de IVA (para el selector de la grilla, igual que factura). */
    public function getTarifasIva(): array
    {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Unidades de medida (para el selector de medida de la grilla). */
    public function getUnidadesMedida(): array
    {
        return $this->db->query("SELECT * FROM unidades_medida WHERE eliminado = false AND status = true ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        $sql = "INSERT INTO carwash_ordenes (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function insertDetalle(array $d): int
    {
        $sql = "INSERT INTO carwash_ordenes_detalle (
                    id_orden, id_empresa, id_producto, tipo_linea, es_libre, descripcion,
                    id_bodega, cantidad, precio_unitario, descuento, porcentaje_iva,
                    valor_iva, total_linea, id_tarifa_iva, eliminado
                ) VALUES (
                    :ido, :e, :prod, :tipo, :libre, :desc,
                    :bod, :cant, :pu, :dscto, :piva,
                    :viva, :tot, :tar, false
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido'   => $d['id_orden'],
            ':e'     => $d['id_empresa'],
            ':prod'  => $d['id_producto'] ?? null,
            ':tipo'  => $d['tipo_linea'] ?? 'servicio',
            ':libre' => !empty($d['es_libre']) ? 'true' : 'false',
            ':desc'  => $d['descripcion'],
            ':bod'   => $d['id_bodega'] ?? null,
            ':cant'  => $d['cantidad'] ?? 1,
            ':pu'    => $d['precio_unitario'] ?? 0,
            ':dscto' => $d['descuento'] ?? 0,
            ':piva'  => $d['porcentaje_iva'] ?? 0,
            ':viva'  => $d['valor_iva'] ?? 0,
            ':tot'   => $d['total_linea'] ?? 0,
            ':tar'   => $d['id_tarifa_iva'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertNovedad(array $n): int
    {
        $sql = "INSERT INTO carwash_ordenes_novedades (id_orden, id_empresa, descripcion, severidad, eliminado)
                VALUES (:ido, :e, :desc, :sev, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ido'  => $n['id_orden'],
            ':e'    => $n['id_empresa'],
            ':desc' => $n['descripcion'],
            ':sev'  => $n['severidad'] ?? 'leve',
        ]);
        return (int) $st->fetchColumn();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT o.*,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       c.direccion AS cliente_direccion, c.email AS cliente_email, c.telefono AS cliente_telefono,
                       v.placa AS vehiculo_placa, v.marca AS vehiculo_marca
                FROM carwash_ordenes o
                LEFT JOIN clientes c ON c.id = o.id_cliente
                LEFT JOIN vehiculos v ON v.id = o.id_vehiculo
                WHERE o.id = :id AND o.id_empresa = :e AND o.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT d.*, p.codigo AS producto_codigo, b.nombre AS bodega_nombre
                FROM carwash_ordenes_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas b   ON b.id = d.id_bodega
                WHERE d.id_orden = :id AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNovedades(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT id, descripcion, severidad
                FROM carwash_ordenes_novedades
                WHERE id_orden = :id AND id_empresa = :e AND eliminado = false
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Marca lógicamente detalles y novedades de una orden (para reemplazarlos al editar). */
    public function limpiarLineas(int $idOrden, int $idEmpresa): void
    {
        $this->db->prepare("UPDATE carwash_ordenes_detalle SET eliminado = true WHERE id_orden = :id AND id_empresa = :e AND eliminado = false")
                 ->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        $this->db->prepare("UPDATE carwash_ordenes_novedades SET eliminado = true WHERE id_orden = :id AND id_empresa = :e AND eliminado = false")
                 ->execute([':id' => $idOrden, ':e' => $idEmpresa]);
    }

    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE carwash_ordenes SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function updateTotales(int $id, int $idEmpresa, float $subtotal, float $descuento, float $iva, float $total): void
    {
        $sql = "UPDATE carwash_ordenes
                SET subtotal = :s, descuento = :d, iva = :i, total = :t
                WHERE id = :id AND id_empresa = :e";
        $this->db->prepare($sql)->execute([
            ':s' => $subtotal, ':d' => $descuento, ':i' => $iva, ':t' => $total,
            ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario, bool $setFechaEntrega = false): void
    {
        $extra = $setFechaEntrega ? ", fecha_entrega = CURRENT_TIMESTAMP" : "";
        $sql = "UPDATE carwash_ordenes
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP $extra
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function marcarDocumentoGenerado(int $id, int $idEmpresa, string $tipo, int $idDoc, string $numero, int $idUsuario): void
    {
        $sql = "UPDATE carwash_ordenes
                SET tipo_documento = :tipo, id_documento = :idd, numero_documento = :num,
                    estado = 'facturado', updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':tipo' => $tipo, ':idd' => $idDoc, ':num' => $numero,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE carwash_ordenes
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }
}
