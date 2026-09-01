<?php

namespace App\Repositories\Modulos;

use App\core\Database;
use PDO;

class PedidoRepository {
    private $db;

    /** Caché por request: ¿existe ventas_detalle.id_pedido_detalle? (migración database/agregar_id_pedido_detalle_ventas.sql). */
    private ?bool $columnaVentasDetalleExiste = null;

    public const COLUMNAS_ORDEN = [
        'numero_pedido', 'establecimiento', 'punto_emision', 'secuencial', 'fecha_pedido', 'cliente_nombre',
        'fecha_entrega', 'rango_horario', 'responsable_entrega',
        'observaciones', 'observaciones_internas', 'estado',
        'created_at'
    ];

    public function __construct() {
        $this->db = Database::getConnection();
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

        $whereSql = "WHERE p.id_empresa = :id_empresa AND p.eliminado = false AND p.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $params   = [':id_empresa' => $idEmpresa];

        // Registros propios (§6): si el usuario no tiene "acceso total" en el
        // submódulo, solo ve lo que él mismo creó. $idUsuarioFiltro llega null
        // cuando el usuario SÍ tiene acceso total (ver todos).
        if ($idUsuarioFiltro !== null) {
            $whereSql .= " AND p.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ["(p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial)", 'c.nombre', 'rt.nombre', 'p.observaciones', 'p.observaciones_internas'],
                $parsed['texto_libre'],
                $params,
                'b'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'cliente'       => 'c.nombre',
                'responsable'   => 'rt.nombre',
                'observaciones' => 'p.observaciones',
                'obs'           => 'p.observaciones',
                'obs_internas'  => 'p.observaciones_internas',
            ],
            'exacto' => [
                'estado' => 'p.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del buscador.
                'serie'  => "CONCAT(p.establecimiento,'-',p.punto_emision)",
            ],
            'fecha' => [
                'fecha'         => 'p.fecha_pedido',
                'fecha_pedido'  => 'p.fecha_pedido',
                'fecha_entrega' => 'p.fecha_entrega',
            ],
            'numerico' => [
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'p.secuencial::numeric',
            ],
        ]);

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) 
                     FROM pedidos_cabecera p 
                     JOIN clientes c ON p.id_cliente = c.id 
                     LEFT JOIN responsables_traslado rt ON p.id_responsable_entrega = rt.id
                     {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $orderExpr = match($ordenCol) {
            'numero_pedido'      => "p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial",
            'cliente_nombre'     => 'c.nombre',
            'responsable_entrega'=> 'rt.nombre',
            'rango_horario'      => 'p.hora_inicial_entrega',
            default              => "p.{$ordenCol}"
        };

        $sqlRows = "SELECT p.*,
                           (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido,
                           c.nombre AS cliente_nombre,
                           rt.nombre AS responsable_entrega
                    FROM pedidos_cabecera p
                    JOIN clientes c ON p.id_cliente = c.id
                    LEFT JOIN responsables_traslado rt ON p.id_responsable_entrega = rt.id
                    {$whereSql}
                    ORDER BY $orderExpr $dir, p.id DESC";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    /** Series REALMENTE usadas en pedidos guardados (para el filtro "Serie" del buscador). */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM pedidos_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id, $id_empresa) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                       c.email as cliente_email,
                       uc.nombre as creado_por_nombre, uu.nombre as modificado_por_nombre,
                       rt.nombre as responsable_entrega,
                       (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido
                FROM pedidos_cabecera p
                JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN usuarios uc ON p.created_by = uc.id
                LEFT JOIN usuarios uu ON p.updated_by = uu.id
                LEFT JOIN responsables_traslado rt ON p.id_responsable_entrega = rt.id
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'id_empresa' => $id_empresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDetalles($id_pedido, $id_empresa) {
        $sql = "SELECT d.*, p.nombre as producto_nombre, p.codigo as producto_codigo
                FROM pedidos_detalle d
                JOIN productos p ON d.id_producto = p.id
                WHERE d.id_pedido = :id_pedido 
                AND d.eliminado = false";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $id_pedido]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿Ya se desplegó la migración que agrega ventas_detalle.id_pedido_detalle? Degradación segura si no. */
    private function columnaVentasDetalleExiste(): bool {
        if ($this->columnaVentasDetalleExiste === null) {
            $sql = "SELECT EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name = 'ventas_detalle' AND column_name = 'id_pedido_detalle'
                    )";
            $this->columnaVentasDetalleExiste = (bool) $this->db->query($sql)->fetchColumn();
        }
        return $this->columnaVentasDetalleExiste;
    }

    /**
     * Cantidad ya consumida (Consignación de Venta + Factura de Venta, no anuladas)
     * por cada línea de pedidos_detalle indicada. Solo devuelve entradas para las
     * líneas que sí tienen consumo (las demás se asumen en 0).
     *
     * @param int[] $idsDetalle IDs de pedidos_detalle a consultar.
     * @return array<int,float> [id_pedido_detalle => cantidad_consumida]
     */
    public function getCantidadConsumidaPorDetalle(array $idsDetalle): array {
        $idsDetalle = array_values(array_unique(array_map('intval', $idsDetalle)));
        if (empty($idsDetalle)) {
            return [];
        }
        $placeholders = implode(',', $idsDetalle);

        $sqlConsignacion = "
            SELECT cvd.id_pedido_detalle AS id, SUM(cvd.cantidad) AS cantidad
            FROM consignaciones_ventas_detalles cvd
            JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
            WHERE cv.eliminado = false AND cvd.eliminado = false
              AND cvd.id_pedido_detalle IN ({$placeholders})
            GROUP BY cvd.id_pedido_detalle
        ";

        $sqlFactura = $this->columnaVentasDetalleExiste() ? "
            UNION ALL
            SELECT vd.id_pedido_detalle AS id, SUM(vd.cantidad) AS cantidad
            FROM ventas_detalle vd
            JOIN ventas_cabecera v ON v.id = vd.id_venta
            WHERE v.eliminado = false AND v.estado <> 'anulado'
              AND vd.id_pedido_detalle IN ({$placeholders})
            GROUP BY vd.id_pedido_detalle
        " : '';

        $sql = "SELECT id, SUM(cantidad) AS cantidad FROM ({$sqlConsignacion} {$sqlFactura}) t GROUP BY id";
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('floatval', $rows);
    }

    /**
     * Historial de documentos que consumieron una línea del pedido (para el "ver
     * historial" al hacer clic en el ítem): Consignaciones de Venta y Facturas de
     * Venta que la referencian, con número, fecha y cantidad tomada.
     *
     * $idEmpresa es obligatorio y se valida contra el pedido dueño de la línea
     * (no contra el documento consumidor) para que un usuario no pueda leer el
     * historial de una línea de OTRA empresa adivinando/iterando el id.
     *
     * @return array<int,array{tipo:string,numero:string,fecha:?string,cantidad:float,estado:string}>
     */
    public function getHistorialConsumoDetalle(int $idDetalle, int $idEmpresa): array {
        $existeYPropia = $this->db->prepare(
            "SELECT 1 FROM pedidos_detalle d
             JOIN pedidos_cabecera p ON p.id = d.id_pedido
             WHERE d.id = :id AND p.id_empresa = :id_empresa"
        );
        $existeYPropia->execute([':id' => $idDetalle, ':id_empresa' => $idEmpresa]);
        if (!$existeYPropia->fetchColumn()) {
            return [];
        }

        $sqlConsignacion = "
            SELECT 'Consignación de Venta' AS tipo,
                   (cv.establecimiento || '-' || cv.punto_emision || '-' || cv.secuencial) AS numero,
                   cv.fecha_emision AS fecha,
                   cvd.cantidad AS cantidad,
                   cv.estado AS estado
            FROM consignaciones_ventas_detalles cvd
            JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
            WHERE cvd.id_pedido_detalle = :id AND cv.eliminado = false AND cvd.eliminado = false
        ";

        $sqlFactura = $this->columnaVentasDetalleExiste() ? "
            UNION ALL
            SELECT 'Factura de Venta' AS tipo,
                   (v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial) AS numero,
                   v.fecha_emision AS fecha,
                   vd.cantidad AS cantidad,
                   v.estado AS estado
            FROM ventas_detalle vd
            JOIN ventas_cabecera v ON v.id = vd.id_venta
            WHERE vd.id_pedido_detalle = :id2 AND v.eliminado = false
        " : '';

        $sql = "{$sqlConsignacion} {$sqlFactura} ORDER BY fecha DESC";
        $params = [':id' => $idDetalle];
        if ($this->columnaVentasDetalleExiste()) {
            $params[':id2'] = $idDetalle;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerUltimoNumero($id_empresa) {
        $sql = "SELECT MAX(CAST(numero_pedido AS INTEGER)) FROM pedidos_cabecera WHERE id_empresa = :id_empresa";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_empresa' => $id_empresa]);
        $ultimo = $stmt->fetchColumn();
        return $ultimo ? $ultimo + 1 : 1;
    }

    public function getTarifasIva(): array {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(int $idEmpresa): array {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true AND id_empresa = :id_empresa ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeSecuencial(int $idEmpresa, int $idPuntoEmision, string $secuencial, string $tipoAmbiente, ?int $excluirId = null): bool {
        $sql = "SELECT COUNT(*) FROM pedidos_cabecera
                WHERE id_empresa = :id_empresa AND id_punto_emision = :id_punto_emision
                  AND secuencial = :secuencial AND tipo_ambiente = :tipo_ambiente
                  AND eliminado = false";
        $params = [
            'id_empresa' => $idEmpresa,
            'id_punto_emision' => $idPuntoEmision,
            'secuencial' => $secuencial,
            'tipo_ambiente' => $tipoAmbiente,
        ];
        if ($excluirId !== null) {
            $sql .= " AND id <> :excluir_id";
            $params['excluir_id'] = $excluirId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getEmpresaConfig(int $idEmpresa): array {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->execute(['id' => $idEmpresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
