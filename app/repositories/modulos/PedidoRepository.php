<?php

namespace App\Repositories\Modulos;

use App\core\Database;
use PDO;

class PedidoRepository {
    private $db;

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

        if ($buscar !== '') {
            $whereSql .= " AND ((p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) ILIKE :b OR c.nombre ILIKE :b OR rt.nombre ILIKE :b OR p.observaciones ILIKE :b OR p.observaciones_internas ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

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
                    ORDER BY $orderExpr $dir";
                    
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

    public function listar($id_empresa, $filtros = []) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre 
                FROM pedidos_cabecera p
                JOIN clientes c ON p.id_cliente = c.id
                WHERE p.id_empresa = :id_empresa 
                AND p.eliminado = false";

        if (!empty($filtros['buscar'])) {
            $sql .= " AND (p.numero_pedido LIKE :buscar OR c.nombre LIKE :buscar)";
        }

        $sql .= " ORDER BY p.fecha_pedido DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_empresa', $id_empresa, PDO::PARAM_INT);
        if (!empty($filtros['buscar'])) {
            $stmt->bindValue(':buscar', '%' . $filtros['buscar'] . '%', PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id, $id_empresa) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                       uc.nombre as creado_por_nombre, uu.nombre as modificado_por_nombre,
                       rt.nombre as responsable_entrega
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

    public function getUnidadesMedida(): array {
        return $this->db->query("SELECT * FROM unidades_medida WHERE eliminado = false AND status = true ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmpresaConfig(int $idEmpresa): array {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->execute(['id' => $idEmpresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
