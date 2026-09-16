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

    /**
     * Total de una orden CON IVA, calculado desde su detalle (la cabecera no guarda
     * totales). Redondea igual que el cálculo en PHP de OrdenCompraService::calcularTotales()
     * — base de cada línea a centavos y el IVA de cada línea sobre esa base ya redondeada —
     * para que el importe del listado coincida al centavo con el del PDF y el modal.
     * Usa el alias `oc` de la tabla ordenes_compra.
     */
    private const SQL_TOTAL_ORDEN = "(SELECT COALESCE(SUM(ROUND(d.cantidad * d.precio_unitario, 2)), 0)
                                           + COALESCE(SUM(ROUND(ROUND(d.cantidad * d.precio_unitario, 2)
                                                                * COALESCE(d.porcentaje_iva, 0) / 100, 2)), 0)
                                      FROM ordenes_compra_detalle d
                                      WHERE d.id_orden = oc.id)";

    public function __construct()
    {
        parent::__construct('ordenes_compra');
    }

    /**
     * Series REALMENTE usadas en órdenes de compra guardadas, para el filtro
     * "Serie" del buscador — a diferencia de $puntosEmision (solo sirve para
     * elegir la serie de una orden NUEVA), esto incluye series de cualquier
     * establecimiento y aunque el punto ya no tenga secuencial configurado.
     */
    /** Catálogo de tarifas de IVA activas, para el selector de IVA de cada línea del detalle. */
    public function getTarifasIva(): array
    {
        return $this->db->query(
            "SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM ordenes_compra
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
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
        // Texto libre: las columnas del listado y lo que identifica la orden. Decisión
        // del usuario (igual que Compras/Ingresos/Egresos): la columna Estado NO entra
        // en el texto libre; se filtra solo desde el modal de filtros. Los datos de otras
        // tablas van en subconsultas para no cambiar los JOIN del COUNT.
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'oc.numero_orden',                 // N° Orden
                    'oc.secuencial',
                    'oc.fecha_orden::text',            // Fecha Orden
                    'p.razon_social',                  // Proveedor
                    'p.identificacion',                // Identificación
                    'oc.fecha_recepcion::text',        // Fecha Recepción
                    'oc.observaciones',                // Observaciones
                    // Fuera del listado, pero identifican la orden:
                    'oc.aprobado_por',
                    '(SELECT ux.nombre FROM usuarios ux WHERE ux.id = oc.created_by)',
                    "(SELECT STRING_AGG(CONCAT_WS(' ', px.codigo, dx.descripcion, dx.notas), ' ') FROM ordenes_compra_detalle dx LEFT JOIN productos px ON px.id = dx.id_producto WHERE dx.id_orden = oc.id)",
                    "(SELECT STRING_AGG(CONCAT(cx.establecimiento_prov,'-',cx.punto_emision_prov,'-',cx.secuencial_prov), ' ') FROM compras_cabecera cx WHERE cx.id_orden_compra = oc.id AND cx.id_empresa = oc.id_empresa AND cx.eliminado = false)",
                ],
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
                // Claves nuevas del modal de filtros.
                'aprobado_por'   => 'oc.aprobado_por',
            ],
            'exacto'   => [
                'estado' => 'oc.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), igual patrón
                // que FacturaVentaRepository::getListado().
                'serie'  => "CONCAT(oc.establecimiento,'-',oc.punto_emision)",
                'id_usuario' => 'oc.created_by',
                // compra:si → al menos una compra (no eliminada) vinculada a la orden.
                'compra'     => "CASE WHEN EXISTS (SELECT 1 FROM compras_cabecera cy WHERE cy.id_orden_compra = oc.id AND cy.id_empresa = oc.id_empresa AND cy.eliminado = false) THEN 'si' ELSE 'no' END",
            ],
            'fecha'    => [
                'fecha' => 'oc.fecha_orden', 'fecha_orden' => 'oc.fecha_orden',
                'fecha_recepcion'  => 'oc.fecha_recepcion',
                'fecha_envio'      => 'oc.fecha_envio',
                'fecha_aprobacion' => 'oc.fecha_aprobacion',
            ],
            'numerico' => [
                // La cabecera no guarda el total: se calcula desde el detalle (con IVA).
                'monto' => self::SQL_TOTAL_ORDEN,
                'total' => self::SQL_TOTAL_ORDEN,
                // Comparación numérica exacta: "298" encuentra "000000298" sin ceros
                // a la izquierda, pero nunca hace substring (ver FacturaVentaRepository).
                'secuencial' => 'oc.secuencial::numeric',
                'items'      => '(SELECT COUNT(*) FROM ordenes_compra_detalle dz WHERE dz.id_orden = oc.id)',
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
                    ORDER BY {$orderExpr} {$dir}, oc.id DESC
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

    /** Usuarios que han creado alguna orden de compra en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConOrdenes(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM ordenes_compra oc
                JOIN usuarios u ON u.id = oc.created_by
                WHERE oc.id_empresa = :id_empresa AND oc.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las órdenes de compra (pestaña "Detalles" del modal de
     * filtros): cada ítem pedido (código/nombre del producto, descripción, notas,
     * cantidad, precio) y cada compra vinculada (número del comprobante) que coincide
     * con el texto, con la orden a la que pertenece. Mismo alcance que el listado
     * (empresa, no eliminadas, ambiente, registros propios por created_by). Devuelve
     * también la cabecera completa (oc.* + proveedor) porque ocAbrirEditar() la lee
     * del data-row. ordenes_compra_detalle no tiene columna `eliminado`.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa, ':id_empresa_amb' => $idEmpresa];
        $whereBase = "oc.id_empresa = :id_empresa AND oc.eliminado = false
                      AND oc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)";
        if ($idUsuario !== null) {
            $whereBase .= " AND oc.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['pr.codigo', 'pr.nombre', 'd.descripcion', 'd.notas', 'd.cantidad::text', 'd.precio_unitario::text'],
            $q, $params, 'dt'
        );
        $condCom = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ["CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)", 'c.secuencial_prov', 'c.numero_autorizacion', 'c.importe_total::text'],
            $q, $params, 'cm'
        );
        if ($condDet === '' || $condCom === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT oc.*,
                           p.razon_social   AS proveedor_nombre,
                           p.identificacion AS proveedor_identificacion,
                           p.email          AS proveedor_email
                    FROM ordenes_compra oc
                    LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(pr.codigo, ''), '') AS det_tipo,
                           COALESCE(NULLIF(d.descripcion, ''), pr.nombre) AS det_descripcion,
                           d.cantidad AS det_cantidad,
                           ROUND(d.cantidad * d.precio_unitario, 2) AS det_monto,
                           b.*
                    FROM ordenes_compra_detalle d
                    JOIN base b ON b.id = d.id_orden
                    LEFT JOIN productos pr ON pr.id = d.id_producto
                    WHERE $condDet
                    UNION ALL
                    SELECT 'COMPRA' AS origen,
                           CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS det_tipo,
                           TO_CHAR(c.fecha_emision, 'DD-MM-YYYY') AS det_descripcion,
                           NULL AS det_cantidad,
                           c.importe_total AS det_monto,
                           b.*
                    FROM compras_cabecera c
                    JOIN base b ON b.id = c.id_orden_compra
                    WHERE c.id_empresa = b.id_empresa AND c.eliminado = false AND $condCom
                ) x
                ORDER BY x.fecha_orden DESC, x.id DESC, x.origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
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
        // nombre_tarifa_iva sale del catálogo por el codigo_iva guardado en la línea (no por
        // el porcentaje): 0%, Exento y No objeto comparten porcentaje 0 y solo el código los
        // distingue. Si esa tarifa ya no está en el catálogo, quien muestre el detalle cae al
        // porcentaje guardado en la propia línea.
        $sql = "SELECT d.*, COALESCE(p.codigo, '') AS codigo,
                       ti.tarifa AS nombre_tarifa_iva
                FROM ordenes_compra_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN tarifa_iva ti ON ti.codigo = d.codigo_iva
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
                    (id_orden, id_empresa, id_producto, descripcion, cantidad, precio_unitario,
                     codigo_iva, porcentaje_iva, notas, created_at, created_by)
                VALUES
                    (:id_orden, :id_empresa, :id_producto, :descripcion, :cantidad, :precio_unitario,
                     :codigo_iva, :porcentaje_iva, :notas, NOW(), :created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_orden'        => $item['id_orden'],
            ':id_empresa'      => $item['id_empresa'],
            ':id_producto'     => $item['id_producto'] ?: null,
            ':descripcion'     => $item['descripcion'],
            ':cantidad'        => $item['cantidad'],
            ':precio_unitario' => $item['precio_unitario'],
            ':codigo_iva'      => (string) ($item['codigo_iva'] ?? '') !== '' ? $item['codigo_iva'] : null,
            ':porcentaje_iva'  => $item['porcentaje_iva'] ?? 0,
            ':notas'           => (string) ($item['notas'] ?? '') !== '' ? $item['notas'] : null,
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
                       " . self::SQL_TOTAL_ORDEN . " AS total
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
