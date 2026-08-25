<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class OrdenCompraRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'numero_orden', 'fecha_orden', 'fecha_recepcion',
        'proveedor_nombre', 'proveedor_identificacion',
        'estado', 'observaciones', 'created_at'
    ];

    public function __construct()
    {
        parent::__construct('ordenes_compra');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'created_at';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = $this->getBaseWhere($idEmpresa, 'oc', $idUsuarioFiltro);
        // :id_empresa ya lo usa getBaseWhere(); PDO (sin EMULATE_PREPARES) no admite
        // repetir el mismo placeholder con nombre, por eso esta subconsulta usa uno propio.
        $whereSql .= " AND oc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)";
        $params   = [':id_empresa' => $idEmpresa, ':id_empresa_amb' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['oc.numero_orden', 'p.razon_social', 'p.identificacion', 'oc.observaciones'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => 'oc.numero_orden',
                'nro'            => 'oc.numero_orden',
                'obs'            => 'oc.observaciones',
            ],
            'exacto'   => [
                'estado' => 'oc.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), igual patrón
                // que FacturaVentaRepository::getListado().
                'serie'  => "CONCAT(oc.establecimiento,'-',oc.punto_emision)",
            ],
            'fecha'    => [ 'fecha' => 'oc.fecha_orden', 'fecha_orden' => 'oc.fecha_orden' ],
            'numerico' => [
                'monto' => 'oc.total',
                'total' => 'oc.total',
                // Comparación numérica exacta: "298" encuentra "000000298" sin ceros
                // a la izquierda, pero nunca hace substring (ver FacturaVentaRepository).
                'secuencial' => 'oc.secuencial::numeric',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM ordenes_compra oc
                     LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                     {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $orderExpr = match($ordenCol) {
            'proveedor_nombre'         => 'p.razon_social',
            'proveedor_identificacion' => 'p.identificacion',
            default                    => "oc.{$ordenCol}"
        };

        $limitSql = $perPage > 0 ? "LIMIT :limit OFFSET :offset" : '';

        $sqlRows = "SELECT oc.*,
                           p.razon_social AS proveedor_nombre,
                           p.identificacion AS proveedor_identificacion,
                           p.email AS proveedor_email,
                           u_created.nombre AS creado_por_nombre,
                           u_updated.nombre AS actualizado_por_nombre
                    FROM ordenes_compra oc
                    LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                    LEFT JOIN usuarios u_created ON u_created.id = oc.created_by
                    LEFT JOIN usuarios u_updated ON u_updated.id = oc.updated_by
                    {$whereSql}
                    ORDER BY {$orderExpr} {$dir}
                    {$limitSql}";

        $stRows = $this->db->prepare($sqlRows);
        foreach ($params as $k => $v) {
            $stRows->bindValue($k, $v);
        }
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $stRows->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stRows->bindValue(':offset', $offset,  PDO::PARAM_INT);
        }
        $stRows->execute();
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    /** Mismo chequeo anti-duplicado que FacturaVentaRepository::existeSecuencial(). */
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM ordenes_compra
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];

        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT oc.*,
                       p.razon_social AS proveedor_nombre,
                       p.identificacion AS proveedor_identificacion,
                       p.email AS proveedor_email
                FROM ordenes_compra oc
                LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                WHERE oc.id = :id AND oc.id_empresa = :id_empresa AND oc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Genera (o reutiliza) el token de aprobación pública de la orden, sin tocar su estado. */
    public function setTokenAprobacion(int $id, string $token): void
    {
        $st = $this->db->prepare("UPDATE ordenes_compra SET aprobacion_token = :token, updated_at = NOW() WHERE id = :id");
        $st->execute([':token' => $token, ':id' => $id]);
    }

    /** Marca la orden como enviada al proveedor: borrador → enviado. */
    public function marcarEnviado(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE ordenes_compra SET
                    estado     = 'enviado',
                    fecha_envio = NOW(),
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':updated_by' => $idUsuario]);
    }

    /** Marca la orden como aprobada: enviado → aprobado. $idUsuario puede ser null (aprobación pública, sin sesión). */
    public function marcarAprobado(int $id, int $idEmpresa, ?int $idUsuario, string $aprobadoPor, string $ip): void
    {
        $sql = "UPDATE ordenes_compra SET
                    estado            = 'aprobado',
                    fecha_aprobacion  = NOW(),
                    aprobado_por      = :aprobado_por,
                    aprobacion_ip     = :ip,
                    updated_at        = NOW(),
                    updated_by        = COALESCE(:updated_by, updated_by)
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'           => $id,
            ':id_empresa'   => $idEmpresa,
            ':aprobado_por' => $aprobadoPor,
            ':ip'           => $ip,
            ':updated_by'   => $idUsuario,
        ]);
    }

    /**
     * Orden por su token público de aprobación. Defensa en profundidad §6 CLAUDE.md:
     * valida empresa activa en la MISMA consulta (los endpoints públicos no pasan por
     * AuthMiddleware, así que nunca reciben la revalidación de empresa activa normal).
     */
    public function getPorTokenAprobacion(string $token): ?array
    {
        $sql = "SELECT oc.*,
                       p.razon_social AS proveedor_nombre,
                       p.identificacion AS proveedor_identificacion,
                       p.email AS proveedor_email
                FROM ordenes_compra oc
                LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                INNER JOIN empresas emp ON emp.id = oc.id_empresa
                WHERE oc.aprobacion_token = :token AND oc.eliminado = false
                  AND emp.estado = '1' AND emp.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':token' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalle(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT d.*, COALESCE(p.codigo, '') AS codigo
                FROM ordenes_compra_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                WHERE d.id_orden = :id_orden AND d.id_empresa = :id_empresa
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_orden' => $idOrden, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertar(array $data): int
    {
        $sql = "INSERT INTO ordenes_compra
                    (id_empresa, id_proveedor, id_establecimiento, id_punto_emision,
                     establecimiento, punto_emision, secuencial, tipo_ambiente,
                     fecha_orden, fecha_recepcion, observaciones, estado,
                     created_at, updated_at, created_by, updated_by, eliminado)
                VALUES
                    (:id_empresa, :id_proveedor, :id_establecimiento, :id_punto_emision,
                     :establecimiento, :punto_emision, :secuencial, :tipo_ambiente,
                     :fecha_orden, :fecha_recepcion, :observaciones, :estado,
                     NOW(), NOW(), :created_by, :updated_by, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'         => $data['id_empresa'],
            ':id_proveedor'       => $data['id_proveedor'],
            ':id_establecimiento' => $data['id_establecimiento'],
            ':id_punto_emision'   => $data['id_punto_emision'],
            ':establecimiento'    => $data['establecimiento'],
            ':punto_emision'      => $data['punto_emision'],
            ':secuencial'         => $data['secuencial'],
            ':tipo_ambiente'      => $data['tipo_ambiente'] ?? '1',
            ':fecha_orden'        => $data['fecha_orden'],
            ':fecha_recepcion'    => $data['fecha_recepcion'] ?: null,
            ':observaciones'      => $data['observaciones'] ?: null,
            ':estado'             => $data['estado'] ?? 'borrador',
            ':created_by'         => $data['created_by'],
            ':updated_by'         => $data['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertarDetalle(array $item): void
    {
        $sql = "INSERT INTO ordenes_compra_detalle
                    (id_orden, id_empresa, id_producto, descripcion, cantidad, precio_unitario, created_at, created_by)
                VALUES
                    (:id_orden, :id_empresa, :id_producto, :descripcion, :cantidad, :precio_unitario, NOW(), :created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_orden'        => $item['id_orden'],
            ':id_empresa'      => $item['id_empresa'],
            ':id_producto'     => $item['id_producto'] ?: null,
            ':descripcion'     => $item['descripcion'],
            ':cantidad'        => $item['cantidad'],
            ':precio_unitario' => $item['precio_unitario'],
            ':created_by'      => $item['created_by'],
        ]);
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $sql = "UPDATE ordenes_compra SET
                    id_proveedor       = :id_proveedor,
                    id_establecimiento = :id_establecimiento,
                    id_punto_emision   = :id_punto_emision,
                    establecimiento    = :establecimiento,
                    punto_emision      = :punto_emision,
                    fecha_orden        = :fecha_orden,
                    fecha_recepcion    = :fecha_recepcion,
                    observaciones      = :observaciones,
                    estado             = :estado,
                    updated_at         = NOW(),
                    updated_by         = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'                 => $id,
            ':id_empresa'         => $idEmpresa,
            ':id_proveedor'       => $data['id_proveedor'],
            ':id_establecimiento' => $data['id_establecimiento'],
            ':id_punto_emision'   => $data['id_punto_emision'],
            ':establecimiento'    => $data['establecimiento'],
            ':punto_emision'      => $data['punto_emision'],
            ':fecha_orden'        => $data['fecha_orden'],
            ':fecha_recepcion'    => $data['fecha_recepcion'] ?: null,
            ':observaciones'      => $data['observaciones'] ?: null,
            ':estado'             => $data['estado'] ?? 'borrador',
            ':updated_by'         => $data['updated_by'],
        ]);
    }

    /**
     * Órdenes de un proveedor disponibles para vincular con una compra: no
     * eliminadas, y todavía "recibibles" — Aprobada (nada recibido aún) o
     * Recibido Parcial (falta parte del pedido). Una orden puede vincularse
     * con VARIAS compras a lo largo del tiempo (entregas parciales del
     * proveedor); solo se excluye cuando ya está completamente Recibida.
     */
    public function getAbiertasPorProveedor(int $idProveedor, int $idEmpresa): array
    {
        $sql = "SELECT oc.id, oc.numero_orden, oc.fecha_orden, oc.fecha_recepcion, oc.estado,
                       COALESCE((SELECT SUM(d.cantidad * d.precio_unitario)
                                 FROM ordenes_compra_detalle d
                                 WHERE d.id_orden = oc.id), 0) AS total
                FROM ordenes_compra oc
                WHERE oc.id_empresa = :id_empresa
                  AND oc.id_proveedor = :id_proveedor
                  AND oc.eliminado = false
                  AND oc.estado IN ('aprobado', 'parcial')
                  AND oc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa2)
                ORDER BY oc.fecha_orden DESC, oc.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $idEmpresa,
            ':id_empresa2'  => $idEmpresa,
            ':id_proveedor' => $idProveedor,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cantidad recibida acumulada por producto, sumando TODAS las compras ya
     * vinculadas a esta orden (entregas parciales incluidas). El producto se
     * resuelve igual que en ComprasRepository::getDetalles(): directo por
     * id_producto, o por la homologación código-proveedor → producto cuando
     * la línea de la compra no está vinculada directamente.
     * Devuelve [id_producto => cantidad_recibida].
     */
    public function getRecibidoAcumuladoPorProducto(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT COALESCE(pr.id, ph_pr.id) AS id_producto,
                       SUM(cd.cantidad) AS cantidad_recibida
                FROM compras_cabecera cc
                JOIN compras_detalle cd ON cd.id_compra = cc.id
                LEFT JOIN productos pr ON cd.id_producto = pr.id
                LEFT JOIN productos_homologacion ph ON ph.id_proveedor = cc.id_proveedor
                                                     AND ph.id_empresa = cc.id_empresa
                                                     AND ph.codigo_proveedor = cd.codigo_principal
                                                     AND ph.eliminado = false
                LEFT JOIN productos ph_pr ON ph.id_producto = ph_pr.id
                WHERE cc.id_orden_compra = :id_orden
                  AND cc.id_empresa = :id_empresa
                  AND cc.eliminado = false
                GROUP BY COALESCE(pr.id, ph_pr.id)
                HAVING COALESCE(pr.id, ph_pr.id) IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':id_orden' => $idOrden, ':id_empresa' => $idEmpresa]);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id_producto']] = (float) $row['cantidad_recibida'];
        }
        return $out;
    }

    /** Compras (no eliminadas) vinculadas a una orden, para mostrar el historial de entregas. */
    public function getComprasVinculadas(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT cc.id,
                       CONCAT(cc.establecimiento_prov, '-', cc.punto_emision_prov, '-', cc.secuencial_prov) AS numero,
                       cc.fecha_emision, cc.importe_total
                FROM compras_cabecera cc
                WHERE cc.id_orden_compra = :id_orden AND cc.id_empresa = :id_empresa AND cc.eliminado = false
                ORDER BY cc.fecha_emision ASC, cc.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_orden' => $idOrden, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cambia solo el estado (y opcionalmente marca fecha_recepcion) sin tocar el resto de
     * la cabecera. $cierreForzado se escribe siempre de forma explícita (no solo cuando es
     * true) para que un recómputo automático posterior (vincular/desvincular otra compra)
     * limpie la marca de un cierre manual anterior que ya dejó de reflejar la realidad.
     */
    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario, bool $marcarFechaRecepcion = false, bool $cierreForzado = false): void
    {
        $sql = "UPDATE ordenes_compra SET
                    estado         = :estado,
                    cierre_forzado = :cierre_forzado,
                    updated_at     = NOW(),
                    updated_by     = :updated_by"
                . ($marcarFechaRecepcion ? ", fecha_recepcion = COALESCE(fecha_recepcion, CURRENT_DATE)" : "")
                . " WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'             => $id,
            ':id_empresa'     => $idEmpresa,
            ':estado'         => $estado,
            ':updated_by'     => $idUsuario,
            ':cierre_forzado' => $cierreForzado ? 'true' : 'false',
        ]);
    }

    public function eliminarDetalle(int $idOrden, int $idEmpresa): void
    {
        $sql = "DELETE FROM ordenes_compra_detalle WHERE id_orden = :id_orden AND id_empresa = :id_empresa";
        $st  = $this->db->prepare($sql);
        $st->execute([':id_orden' => $idOrden, ':id_empresa' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE ordenes_compra SET
                    eliminado  = true,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
            ':deleted_by' => $idUsuario,
            ':updated_by' => $idUsuario,
        ]);
    }
}
