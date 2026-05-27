<?php

namespace App\Services\Modulos;

use App\Repositories\Modulos\PedidoRepository;
use App\core\Database;
use Exception;

class PedidoService {
    private $repository;
    private $db;

    public function __construct() {
        $this->repository = new PedidoRepository();
        $this->db = Database::getConnection();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array {
        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        foreach ($result['rows'] as &$r) {
            if (!empty($r['fecha_pedido'])) $r['fecha_pedido'] = date('d-m-Y H:i:s', strtotime($r['fecha_pedido']));
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
        }
        unset($r);
        return $result;
    }

    public function guardarPedido($cabecera, $detalles, $id_empresa, $id_usuario) {
        try {
            $this->db->beginTransaction();

            $fecha_entrega = !empty($cabecera['fecha_entrega']) ? $cabecera['fecha_entrega'] : null;
            $hora_inicial_entrega = !empty($cabecera['hora_inicial_entrega']) ? $cabecera['hora_inicial_entrega'] : null;
            $hora_maxima_entrega = !empty($cabecera['hora_maxima_entrega']) ? $cabecera['hora_maxima_entrega'] : null;

            if (empty($cabecera['id'])) {
                // Nuevo
                $sql = "INSERT INTO pedidos_cabecera
                        (id_empresa, id_cliente, fecha_pedido, observaciones,
                        estado, observaciones_internas, fecha_entrega, hora_inicial_entrega, hora_maxima_entrega, id_responsable_entrega,
                        id_establecimiento, id_punto_emision, establecimiento, punto_emision, secuencial, created_by)
                        VALUES
                        (:id_empresa, :id_cliente, :fecha_pedido, :observaciones,
                        :estado, :observaciones_internas, :fecha_entrega, :hora_inicial_entrega, :hora_maxima_entrega, :id_responsable_entrega,
                        :id_establecimiento, :id_punto_emision, :establecimiento, :punto_emision, :secuencial, :created_by)
                        RETURNING id";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id_empresa' => $id_empresa,
                    'id_cliente' => $cabecera['id_cliente'],
                    'fecha_pedido' => $cabecera['fecha_pedido'],
                    'observaciones' => $cabecera['observaciones'] ?? '',
                    'estado' => 'Pendiente',
                    'observaciones_internas' => $cabecera['observaciones_internas'] ?? '',
                    'fecha_entrega' => $fecha_entrega,
                    'hora_inicial_entrega' => $hora_inicial_entrega,
                    'hora_maxima_entrega' => $hora_maxima_entrega,
                    'id_responsable_entrega' => !empty($cabecera['id_responsable_entrega']) ? $cabecera['id_responsable_entrega'] : null,
                    'id_establecimiento' => !empty($cabecera['id_establecimiento']) ? $cabecera['id_establecimiento'] : null,
                    'id_punto_emision' => !empty($cabecera['id_punto_emision']) ? $cabecera['id_punto_emision'] : null,
                    'establecimiento' => !empty($cabecera['establecimiento']) ? $cabecera['establecimiento'] : null,
                    'punto_emision' => !empty($cabecera['punto_emision']) ? $cabecera['punto_emision'] : null,
                    'secuencial' => !empty($cabecera['secuencial']) ? $cabecera['secuencial'] : null,
                    'created_by' => $id_usuario
                ]);
                
                $id_pedido = $stmt->fetchColumn();
            } else {
                // Actualizar Pedido
                $id_pedido = $cabecera['id'];
                $sql = "UPDATE pedidos_cabecera SET 
                        id_cliente = :id_cliente, 
                        fecha_pedido = :fecha_pedido, 
                        observaciones = :observaciones,
                        estado = :estado,
                        observaciones_internas = :observaciones_internas,
                        fecha_entrega = :fecha_entrega,
                        hora_inicial_entrega = :hora_inicial_entrega,
                        hora_maxima_entrega = :hora_maxima_entrega,
                        id_responsable_entrega = :id_responsable_entrega,
                        id_establecimiento = :id_establecimiento,
                        id_punto_emision = :id_punto_emision,
                        establecimiento = :establecimiento,
                        punto_emision = :punto_emision,
                        secuencial = :secuencial,
                        updated_at = CURRENT_TIMESTAMP,
                        updated_by = :updated_by
                        WHERE id = :id AND id_empresa = :id_empresa";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id_cliente' => $cabecera['id_cliente'],
                    'fecha_pedido' => $cabecera['fecha_pedido'],
                    'observaciones' => $cabecera['observaciones'] ?? '',
                    'estado' => $cabecera['estado'] ?? 'Pendiente',
                    'observaciones_internas' => $cabecera['observaciones_internas'] ?? '',
                    'fecha_entrega' => $fecha_entrega,
                    'hora_inicial_entrega' => $hora_inicial_entrega,
                    'hora_maxima_entrega' => $hora_maxima_entrega,
                    'id_responsable_entrega' => !empty($cabecera['id_responsable_entrega']) ? $cabecera['id_responsable_entrega'] : null,
                    'id_establecimiento' => !empty($cabecera['id_establecimiento']) ? $cabecera['id_establecimiento'] : null,
                    'id_punto_emision' => !empty($cabecera['id_punto_emision']) ? $cabecera['id_punto_emision'] : null,
                    'establecimiento' => !empty($cabecera['establecimiento']) ? $cabecera['establecimiento'] : null,
                    'punto_emision' => !empty($cabecera['punto_emision']) ? $cabecera['punto_emision'] : null,
                    'secuencial' => !empty($cabecera['secuencial']) ? $cabecera['secuencial'] : null,
                    'updated_by' => $id_usuario,
                    'id' => $id_pedido,
                    'id_empresa' => $id_empresa
                ]);

                // Eliminar detalles anteriores (Lógica de reemplazo en edición)
                $sqlDelDet = "UPDATE pedidos_detalle SET eliminado = true 
                             WHERE id_pedido = :id_pedido";
                $this->db->prepare($sqlDelDet)->execute(['id_pedido' => $id_pedido]);
            }

            // Insertar Detalles
            $sqlDet = "INSERT INTO pedidos_detalle 
                       (id_pedido, id_producto, cantidad, precio_unitario, subtotal, iva, total)
                       VALUES (:id_pedido, :id_producto, :cantidad, :precio, :subtotal, :iva, :total)";
            
            $stmtDet = $this->db->prepare($sqlDet);

            foreach ($detalles as $det) {
                $stmtDet->execute([
                    'id_pedido' => $id_pedido,
                    'id_producto' => $det['id_producto'],
                    'cantidad' => $det['cantidad'],
                    'precio' => $det['precio_unitario'] ?? 0,
                    'subtotal' => $det['subtotal'] ?? 0,
                    'iva' => $det['iva'] ?? 0,
                    'total' => $det['total'] ?? 0
                ]);
            }

            $this->db->commit();
            return $id_pedido;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function eliminarPedido($id, $id_empresa, $id_usuario) {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE pedidos_cabecera SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :user 
                    WHERE id = :id AND id_empresa = :id_empresa";
            $this->db->prepare($sql)->execute(['user' => $id_usuario, 'id' => $id, 'id_empresa' => $id_empresa]);

            $sqlDet = "UPDATE pedidos_detalle SET eliminado = true 
                       WHERE id_pedido = :id";
            $this->db->prepare($sqlDet)->execute(['id' => $id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
