<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo Servicio Externo.
 *
 * Una orden registra un servicio de mantenimiento realizado en el sitio del
 * cliente, con sus repuestos/mano de obra. El numero_orden es un correlativo
 * por punto de emisión (mismas reglas que un recibo de venta).
 */
class ServicioExternoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('servicioexterno_ordenes');
    }

    /** Series (establecimiento-punto_emision) usadas realmente en órdenes existentes, para el filtro del listado. */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM servicioexterno_ordenes
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── LISTADO PAGINADO ──────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = $this->getBaseWhere($idEmpresa, 'o', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['o.numero_orden', 'o.equipo_descripcion', 'c.nombre', 'c.identificacion', 'o.estado'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente' => 'c.nombre',
                'orden'   => 'o.numero_orden',
                'equipo'  => 'o.equipo_descripcion',
            ],
            'exacto' => [
                'estado'  => 'o.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), igual que factura.
                'serie'   => "CONCAT(o.establecimiento,'-',o.punto_emision)",
            ],
            'fecha'  => [
                'fecha'   => 'o.fecha_servicio',
            ],
            'numerico' => [
                'total'      => 'o.total',
                // Comparación EXACTA sin ceros a la izquierda ("298" no matchea "000000913").
                'secuencial' => 'o.secuencial::numeric',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM servicioexterno_ordenes o
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
            'fecha_servicio' => 'o.fecha_servicio',
            'numero_orden'   => 'o.numero_orden',
            'equipo'         => 'o.equipo_descripcion',
            'cliente'        => 'c.nombre',
            'estado'         => 'o.estado',
            'total'          => 'o.total',
        ];
        $sort = $colMap[$ordenCol] ?? 'o.fecha_servicio';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT o.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion
                FROM servicioexterno_ordenes o
                LEFT JOIN clientes c ON c.id = o.id_cliente
                $where
                ORDER BY $sort $dir, o.id DESC
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    // ─── SECUENCIAL (mismas reglas que recibo de venta) ───────────────────────

    /** Verifica si el secuencial ya existe para el punto de emisión (evita duplicados). */
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM servicioexterno_ordenes
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
    public function getUnidadesMedida(int $idEmpresa): array
    {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true AND id_empresa = :id_empresa ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        $sql = "INSERT INTO servicioexterno_ordenes (" . implode(', ', $fields) . ")
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
        $sql = "INSERT INTO servicioexterno_ordenes_detalle (
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

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT o.*,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       c.direccion AS cliente_direccion, c.email AS cliente_email, c.telefono AS cliente_telefono
                FROM servicioexterno_ordenes o
                LEFT JOIN clientes c ON c.id = o.id_cliente
                WHERE o.id = :id AND o.id_empresa = :e AND o.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT d.*, p.codigo AS producto_codigo, b.nombre AS bodega_nombre
                FROM servicioexterno_ordenes_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas b   ON b.id = d.id_bodega
                WHERE d.id_orden = :id AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Marca lógicamente el detalle de una orden (para reemplazarlo al editar). */
    public function limpiarLineas(int $idOrden, int $idEmpresa): void
    {
        $this->db->prepare("UPDATE servicioexterno_ordenes_detalle SET eliminado = true WHERE id_orden = :id AND id_empresa = :e AND eliminado = false")
                 ->execute([':id' => $idOrden, ':e' => $idEmpresa]);
    }

    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE servicioexterno_ordenes SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
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
        $sql = "UPDATE servicioexterno_ordenes
                SET subtotal = :s, descuento = :d, iva = :i, total = :t
                WHERE id = :id AND id_empresa = :e";
        $this->db->prepare($sql)->execute([
            ':s' => $subtotal, ':d' => $descuento, ':i' => $iva, ':t' => $total,
            ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario, bool $setFechaFinalizacion = false): void
    {
        $extra = $setFechaFinalizacion ? ", fecha_finalizacion = CURRENT_TIMESTAMP" : "";
        $sql = "UPDATE servicioexterno_ordenes
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP $extra
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function marcarDocumentoGenerado(int $id, int $idEmpresa, string $tipo, int $idDoc, string $numero, int $idUsuario): void
    {
        $sql = "UPDATE servicioexterno_ordenes
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
            "UPDATE servicioexterno_ordenes
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }
}
