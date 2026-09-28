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

    /**
     * Ambiente vigente de la empresa ('1' pruebas / '2' producción). Como en Facturas y
     * Recibos, cada ambiente tiene su propia numeración y su propio listado: las órdenes
     * de pruebas no se ven (ni ocupan números) en producción y viceversa.
     */
    public function ambienteEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return (string) $st->fetchColumn() === '2' ? '2' : '1';
    }

    /** Series (establecimiento-punto_emision) usadas realmente en órdenes existentes, para el filtro del listado. */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM carwash_ordenes
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                  AND tipo_ambiente = (SELECT COALESCE(CAST(tipo_ambiente AS VARCHAR(1)), '1') FROM empresas WHERE id = :id_empresa)
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── LISTADO PAGINADO (historial) ─────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE o.id_empresa = :e AND o.eliminado = false AND o.tipo_ambiente = (SELECT COALESCE(CAST(tipo_ambiente AS VARCHAR(1)), '1') FROM empresas WHERE id = :e)";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND o.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre (buscador FiltrosModal, sin sugerencias): columnas del listado
            // + campos que identifican la orden aunque no sean columnas.
            // Decisión del usuario: la columna Estado (y el tipo de documento generado)
            // NO entran en el texto libre; se filtran solo desde el modal de filtros.
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'o.numero_orden',                                           // N° Orden
                    'o.secuencial',
                    "CONCAT(o.establecimiento,'-',o.punto_emision)",            // Serie
                    "TO_CHAR(o.fecha_ingreso, 'DD-MM-YYYY HH24:MI')",           // Fecha (como se muestra)
                    'o.fecha_ingreso::text',
                    'o.placa',                                                  // Placa
                    'o.marca',
                    'o.modelo',
                    'c.nombre',                                                 // Cliente
                    'c.identificacion',
                    'o.total::text',                                            // Total
                    'o.observaciones',
                    'o.novedades_texto',
                    'o.numero_documento',                                       // Documento de venta generado
                    '(SELECT u.nombre FROM usuarios u WHERE u.id = o.created_by)', // Usuario que registró
                    // Servicios/productos de la orden (código + descripción) y novedades
                    "(SELECT STRING_AGG(CONCAT_WS(' ', p.codigo, d.descripcion), ' ')
                        FROM carwash_ordenes_detalle d LEFT JOIN productos p ON p.id = d.id_producto
                       WHERE d.id_orden = o.id AND d.eliminado = false)",
                    "(SELECT STRING_AGG(n.descripcion, ' ') FROM carwash_ordenes_novedades n
                       WHERE n.id_orden = o.id AND n.eliminado = false)",
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (vista car_wash/index.php). Nunca quitar claves:
        // viajan también en los enlaces de PDF/Excel y en URLs guardadas.
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'       => 'c.nombre',
                'orden'         => 'o.numero_orden',
                'placa'         => 'o.placa',
                'ruc'           => 'c.identificacion',
                'marca'         => 'o.marca',
                'modelo'        => 'o.modelo',
                'observaciones' => "CONCAT_WS(' ', o.observaciones, o.novedades_texto)",
                'documento'     => 'o.numero_documento',
            ],
            'exacto' => [
                'estado'  => 'o.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), igual que factura.
                'serie'   => "CONCAT(o.establecimiento,'-',o.punto_emision)",
                'usuario'        => 'o.created_by',
                'tipo_documento' => 'o.tipo_documento',
                // con_documento:si / con_documento:no (se generó factura o recibo)
                'con_documento'  => "CASE WHEN o.id_documento IS NULL THEN 'no' ELSE 'si' END",
            ],
            'fecha'  => [
                'fecha'         => 'o.fecha_ingreso',
                'fecha_entrega' => 'o.fecha_entrega',
                'proxima_cita'  => 'o.proxima_cita',
            ],
            'numerico' => [
                'total'      => 'o.total',
                // Comparación EXACTA sin ceros a la izquierda ("298" no matchea "000000913").
                'secuencial' => 'o.secuencial::numeric',
                'kilometraje' => 'o.kilometraje',
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

        $sql = "SELECT o.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       " . self::SQL_DOC_VIGENTE . " AS documento_vigente
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

    /** Usuarios que han registrado alguna orden en la empresa (filtro "Usuario" del modal de filtros). */
    public function getUsuariosConOrdenes(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM carwash_ordenes o
                JOIN usuarios u ON u.id = o.created_by
                WHERE o.id_empresa = :id_empresa AND o.eliminado = false
                  AND o.tipo_ambiente = (SELECT COALESCE(CAST(tipo_ambiente AS VARCHAR(1)), '1') FROM empresas WHERE id = :id_empresa)
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las órdenes (pestaña "Detalles" del modal de filtros):
     * cada servicio/producto y cada novedad que coincide con el texto, con la orden a
     * la que pertenece. Mismo alcance que el listado (empresa, no eliminadas, registros
     * propios por created_by si aplica). No busca en tipo de línea ni severidad.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "o.id_empresa = :id_empresa AND o.eliminado = false AND o.tipo_ambiente = (SELECT COALESCE(CAST(tipo_ambiente AS VARCHAR(1)), '1') FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND o.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['p.codigo', 'd.descripcion', 'b.nombre', 'd.cantidad::text', 'd.precio_unitario::text', 'd.total_linea::text'],
            $q, $params, 'dt'
        );
        $condNov = \App\Helpers\FiltrosBusqueda::condicionTexto(['n.descripcion'], $q, $params, 'nv');
        if ($condDet === '' || $condNov === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT o.id, o.numero_orden, o.fecha_ingreso, o.placa, o.estado, c.nombre AS cliente
                    FROM carwash_ordenes o
                    LEFT JOIN clientes c ON c.id = o.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'LINEA' AS origen, d.tipo_linea AS tipo, p.codigo AS referencia, d.descripcion,
                           d.cantidad, d.total_linea AS monto,
                           b2.id AS id_orden, b2.numero_orden, b2.fecha_ingreso, b2.placa, b2.estado, b2.cliente
                    FROM carwash_ordenes_detalle d
                    JOIN base b2 ON b2.id = d.id_orden
                    LEFT JOIN productos p ON p.id = d.id_producto
                    LEFT JOIN bodegas b ON b.id = d.id_bodega
                    WHERE d.eliminado = false AND $condDet
                    UNION ALL
                    SELECT 'NOVEDAD' AS origen, n.severidad AS tipo, NULL AS referencia, n.descripcion,
                           NULL AS cantidad, NULL AS monto,
                           b2.id AS id_orden, b2.numero_orden, b2.fecha_ingreso, b2.placa, b2.estado, b2.cliente
                    FROM carwash_ordenes_novedades n
                    JOIN base b2 ON b2.id = n.id_orden
                    WHERE n.eliminado = false AND $condNov
                ) x
                ORDER BY x.fecha_ingreso DESC, x.id_orden DESC, x.origen
                LIMIT $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
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
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, string $tipoAmbiente, ?int $excluirId = null): bool
    {
        // Por ambiente, igual que el índice único uq_carwash_secuencial y que SecuencialService:
        // sin este filtro, al pasar a producción el número 1 "ya existía" en pruebas y no se
        // podía registrar ninguna orden.
        $sql = "SELECT COUNT(*) FROM carwash_ordenes
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND tipo_ambiente = ? AND eliminado = false";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial, $tipoAmbiente];
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
        $id = (int) $st->fetchColumn();

        // Lote / caducidad / NUP / unidad (configuración de facturación). Solo si ya se
        // aplicó 20260928_carwash_lote_caducidad_nup.sql; sin esas columnas no se guardan.
        if ($this->tieneColumnasLote()) {
            $this->db->prepare("UPDATE carwash_ordenes_detalle
                                   SET lote = :l, fecha_caducidad = :c, nup = :n, id_unidad_medida = :u
                                 WHERE id = :id")
                ->execute([
                    ':l'  => ($d['lote'] ?? '') !== '' && ($d['lote'] ?? '') !== 'sin_lote' ? mb_substr((string) $d['lote'], 0, 100) : null,
                    ':c'  => !empty($d['caducidad']) ? substr((string) $d['caducidad'], 0, 10) : null,
                    ':n'  => ($d['nup'] ?? '') !== '' ? mb_substr((string) $d['nup'], 0, 100) : null,
                    ':u'  => !empty($d['id_unidad_medida']) ? (int) $d['id_unidad_medida'] : null,
                    ':id' => $id,
                ]);
        }
        return $id;
    }

    /** ¿Existe ya carwash_ordenes.condiciones_html? (el código no debe romperse sin el SQL). */
    public function tieneColumnaCondiciones(): bool
    {
        static $tiene = false;
        if (!$tiene) {
            $tiene = (bool) $this->db->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'carwash_ordenes' AND column_name = 'condiciones_html'")->fetchColumn();
        }
        return $tiene;
    }

    /** ¿Existen ya las columnas lote/fecha_caducidad/nup/id_unidad_medida en el detalle? */
    public function tieneColumnasLote(): bool
    {
        // Solo se guarda en caché el "sí": si falta el SQL se vuelve a consultar (consulta barata).
        static $tiene = false;
        if (!$tiene) {
            $tiene = (int) $this->db->query("SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_name = 'carwash_ordenes_detalle' AND column_name IN ('lote','fecha_caducidad','nup','id_unidad_medida')")->fetchColumn() === 4;
        }
        return $tiene;
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
                       (c.id IS NOT NULL AND c.status = 1 AND c.eliminado = false) AS cliente_activo,
                       v.placa AS vehiculo_placa, v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo,
                       v.anio AS vehiculo_anio, v.color AS vehiculo_color, v.chasis AS vehiculo_chasis,
                       v.motor AS vehiculo_motor, v.tipo_vehiculo AS vehiculo_tipo,
                       " . self::SQL_DOC_VIGENTE . " AS documento_vigente
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
        // inventariable / tipo_produccion / medida del producto: la pantalla los necesita para
        // mostrar Lote-Caducidad-NUP y la unidad igual que en Factura de Venta.
        $sql = "SELECT d.*, p.codigo AS producto_codigo, b.nombre AS bodega_nombre,
                       p.inventariable AS producto_inventariable, p.tipo_produccion AS producto_tipo_produccion,
                       p.id_tipo_medida AS producto_id_tipo_medida, p.id_medida AS producto_id_medida,
                       p.codigo_auxiliar AS producto_codigo_auxiliar,
                       (SELECT ti.codigo FROM tarifa_iva ti WHERE ti.id = d.id_tarifa_iva) AS tarifa_codigo
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

    /** IVA y total de una línea (se recalculan al emitir si la orden quedó con importes viejos). */
    public function updateLineaImportes(int $idLinea, int $idEmpresa, float $iva, float $total): void
    {
        $this->db->prepare("UPDATE carwash_ordenes_detalle SET valor_iva = :i, total_linea = :t WHERE id = :id AND id_empresa = :e")
                 ->execute([':i' => $iva, ':t' => $total, ':id' => $idLinea, ':e' => $idEmpresa]);
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

    // ─── DOCUMENTO VIGENTE / HISTORIAL DE FACTURACIÓN ─────────────────────────

    /**
     * Expresión SQL (sobre el alias `o`) que dice si el documento enlazado a la orden sigue
     * vigente: NULL = la orden no tiene documento enlazado; false = la factura/recibo fue
     * anulado o eliminado en su módulo (la orden puede volver a facturarse); true = vigente.
     */
    private const SQL_DOC_VIGENTE = "CASE WHEN o.id_documento IS NULL THEN NULL
            WHEN o.tipo_documento = 'FACTURA' THEN EXISTS (SELECT 1 FROM ventas_cabecera vcx
                 WHERE vcx.id = o.id_documento AND vcx.eliminado = false AND vcx.estado <> 'anulado')
            ELSE EXISTS (SELECT 1 FROM recibos_venta_cabecera rvx
                 WHERE rvx.id = o.id_documento AND rvx.eliminado = false AND rvx.estado <> 'anulado') END";

    /** Nombre, categoría y marca de un producto (base para buscar productos similares). */
    public function getProductoBasico(int $idProducto, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT id, nombre, id_categoria, id_marca FROM productos WHERE id = :id AND id_empresa = :e");
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** ¿El cliente existe en la empresa, está activo (status = 1) y no está eliminado? */
    public function clienteActivo(int $idCliente, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM clientes WHERE id = :id AND id_empresa = :e AND status = 1 AND eliminado = false");
        $st->execute([':id' => $idCliente, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /** ¿El documento enlazado a la orden sigue vigente? (null si no tiene documento). */
    public function documentoVigente(int $idOrden, int $idEmpresa): ?bool
    {
        $st = $this->db->prepare("SELECT " . self::SQL_DOC_VIGENTE . " AS v FROM carwash_ordenes o WHERE o.id = :id AND o.id_empresa = :e");
        $st->execute([':id' => $idOrden, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) return null;
        return $v === true || $v === 't' || $v === 1 || $v === '1';
    }

    /** ¿Existe ya la tabla del historial de documentos? (el código no debe romperse si aún no se aplicó el SQL). */
    public function existeTablaDocumentos(): bool
    {
        static $existe = false;
        if (!$existe) {
            $existe = (bool) $this->db->query("SELECT to_regclass('public.carwash_ordenes_documentos') IS NOT NULL")->fetchColumn();
        }
        return $existe;
    }

    /** Registra un documento de venta emitido desde la orden (historial de facturación). */
    public function insertDocumento(array $d): void
    {
        if (!$this->existeTablaDocumentos()) return;
        $this->db->prepare(
            "INSERT INTO carwash_ordenes_documentos
                (id_empresa, id_orden, tipo_documento, id_documento, numero_documento, fecha_emision, total, origen, created_by, updated_by)
             VALUES (:e, :o, :t, :idd, :num, :f, :tot, :ori, :u, :u)"
        )->execute([
            ':e'   => $d['id_empresa'],
            ':o'   => $d['id_orden'],
            ':t'   => $d['tipo_documento'],
            ':idd' => $d['id_documento'] ?: null,
            ':num' => $d['numero_documento'],
            ':f'   => $d['fecha_emision'],
            ':tot' => $d['total'] ?? 0,
            ':ori' => $d['origen'] ?? 'sistema',
            ':u'   => $d['id_usuario'],
        ]);
    }

    /**
     * Historial de documentos (facturas/recibos) emitidos desde la orden, con el estado ACTUAL
     * de cada documento en su módulo (autorizado, borrador, anulado o eliminado). Si la tabla
     * del historial aún no existe, se arma con el documento enlazado en la cabecera.
     */
    public function getDocumentos(int $idOrden, int $idEmpresa): array
    {
        $estadoDoc = "CASE
                WHEN d.tipo_documento = 'FACTURA' THEN (SELECT CASE WHEN vc.eliminado THEN 'eliminado' ELSE vc.estado END FROM ventas_cabecera vc WHERE vc.id = d.id_documento AND vc.id_empresa = :e)
                ELSE (SELECT CASE WHEN rv.eliminado THEN 'eliminado' ELSE rv.estado END FROM recibos_venta_cabecera rv WHERE rv.id = d.id_documento AND rv.id_empresa = :e)
            END";

        if ($this->existeTablaDocumentos()) {
            $sql = "SELECT d.id, d.tipo_documento, d.id_documento, d.numero_documento, d.fecha_emision, d.total, d.origen,
                           d.created_at, u.nombre AS usuario, $estadoDoc AS estado_documento
                    FROM carwash_ordenes_documentos d
                    LEFT JOIN usuarios u ON u.id = d.created_by
                    WHERE d.id_orden = :o AND d.id_empresa = :e AND d.eliminado = false
                    ORDER BY d.fecha_emision DESC, d.id DESC";
        } else {
            $sql = "SELECT d.id, d.tipo_documento, d.id_documento, d.numero_documento, d.updated_at AS fecha_emision, d.total,
                           'sistema' AS origen, d.updated_at AS created_at, u.nombre AS usuario, $estadoDoc AS estado_documento
                    FROM carwash_ordenes d
                    LEFT JOIN usuarios u ON u.id = d.updated_by
                    WHERE d.id = :o AND d.id_empresa = :e AND d.eliminado = false AND d.tipo_documento IS NOT NULL";
        }
        $st = $this->db->prepare($sql);
        $st->execute([':o' => $idOrden, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historial de órdenes de la empresa por VEHÍCULO o por CLIENTE (pestaña Historial del modal).
     * - Con $idVehiculo / $idCliente: todas las órdenes de ese vehículo / cliente.
     * - Con texto: búsqueda multipalabra sin acentos sobre placa/marca/modelo (vehículo) o
     *   nombre/identificación (cliente).
     * Respeta registros propios (§6) y trae un resumen de servicios y el documento emitido.
     */
    public function getHistorial(int $idEmpresa, string $modo, string $q, ?int $idVehiculo, ?int $idCliente, ?int $idUsuarioFiltro, int $limit = 200): array
    {
        $where  = "o.id_empresa = :e AND o.eliminado = false AND o.tipo_ambiente = (SELECT COALESCE(CAST(tipo_ambiente AS VARCHAR(1)), '1') FROM empresas WHERE id = :e)";
        $params = [':e' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $where .= " AND o.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $q = trim($q);
        if ($modo === 'cliente') {
            if ($idCliente) {
                $where .= " AND o.id_cliente = :idc";
                $params[':idc'] = $idCliente;
            } elseif ($q !== '') {
                $cond = \App\Helpers\FiltrosBusqueda::condicionTexto(['c.nombre', 'c.identificacion'], $q, $params, 'hc');
                if ($cond === '') return [];
                $where .= " AND $cond";
            } else {
                return [];
            }
        } else {
            if ($idVehiculo) {
                $where .= " AND o.id_vehiculo = :idv";
                $params[':idv'] = $idVehiculo;
            } elseif ($q !== '') {
                $cond = \App\Helpers\FiltrosBusqueda::condicionTexto(['o.placa', 'v.placa', 'o.marca', 'o.modelo', 'v.propietario'], $q, $params, 'hv');
                if ($cond === '') return [];
                $where .= " AND $cond";
            } else {
                return [];
            }
        }

        $limit = max(1, min(500, $limit));
        $sql = "SELECT o.id, o.numero_orden, o.fecha_ingreso, o.placa, o.marca, o.kilometraje, o.total, o.estado,
                       o.tipo_documento, o.numero_documento, o.id_vehiculo, o.id_cliente,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       (SELECT STRING_AGG(d.descripcion, ', ' ORDER BY d.id)
                          FROM carwash_ordenes_detalle d
                         WHERE d.id_orden = o.id AND d.eliminado = false) AS servicios
                FROM carwash_ordenes o
                LEFT JOIN clientes c  ON c.id = o.id_cliente
                LEFT JOIN vehiculos v ON v.id = o.id_vehiculo
                WHERE $where
                ORDER BY o.fecha_ingreso DESC, o.id DESC
                LIMIT $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
