<?php

namespace App\Services\Modulos;

use App\Repositories\Modulos\PedidoRepository;
use App\Services\BloqueoEdicionService;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;
use Exception;
use PDOException;

class PedidoService {
    /** Debe coincidir con PedidosController::TABLA_BLOQUEO. */
    private const TABLA_BLOQUEO = 'pedidos_cabecera';

    private $repository;
    private $db;
    private BloqueoEdicionService $bloqueoService;
    private LogSistemaService $logService;

    public function __construct() {
        $this->repository = new PedidoRepository();
        $this->db = Database::getConnection();
        $this->bloqueoService = new BloqueoEdicionService();
        $this->logService = new LogSistemaService();
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
        // Defensa en profundidad: aunque la UI ya deshabilita "Guardar" cuando otro usuario
        // tiene el pedido en uso (edición o consumo desde Consignaciones), se re-verifica aquí
        // por si la petición llega igual (doble clic ya en vuelo, F5 con caché, etc.).
        if (!empty($cabecera['id'])) {
            $enUso = $this->bloqueoService->verificarLibreOPropio(self::TABLA_BLOQUEO, (int) $cabecera['id'], $id_empresa, $id_usuario);
            if ($enUso !== null) {
                throw new Exception("Este pedido lo está usando ahora mismo {$enUso['usuario']}. Espera a que termine e intenta de nuevo.");
            }
        }

        try {
            $this->db->beginTransaction();

            $fecha_entrega = !empty($cabecera['fecha_entrega']) ? $cabecera['fecha_entrega'] : null;
            $hora_inicial_entrega = !empty($cabecera['hora_inicial_entrega']) ? $cabecera['hora_inicial_entrega'] : null;
            $hora_maxima_entrega = !empty($cabecera['hora_maxima_entrega']) ? $cabecera['hora_maxima_entrega'] : null;

            if (empty($cabecera['id'])) {
                // Nuevo
                $stmtAmb = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa");
                $stmtAmb->execute(['id_empresa' => $id_empresa]);
                $tipoAmbiente = $stmtAmb->fetchColumn() ?: '1';

                $idPuntoEmision = !empty($cabecera['id_punto_emision']) ? (int) $cabecera['id_punto_emision'] : null;
                $secuencial = !empty($cabecera['secuencial']) ? $cabecera['secuencial'] : null;

                if ($idPuntoEmision !== null) {
                    // Serializa la generación del número por punto de emisión dentro de esta
                    // transacción (mismo patrón de advisory lock usado para comandas): así dos
                    // usuarios guardando "al mismo tiempo" quedan en fila y el segundo recalcula
                    // el secuencial ya viendo el pedido que acaba de confirmar el primero.
                    $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('pedido_secuencial:' || :punto || ':' || :amb))")
                              ->execute(['punto' => $idPuntoEmision, 'amb' => $tipoAmbiente]);

                    // Secuencial AUTORITATIVO del servidor: se recalcula aquí y NO se confía en el
                    // valor que trae el navegador (readonly / vista previa cargada al abrir el
                    // modal, puede llegar desfasado si el modal quedó abierto un rato).
                    $secRes = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, 'Pedidos');
                    $secuencial = $secRes['formateado'] ?? str_pad((string) ($secRes['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT);

                    if ($this->repository->existeSecuencial($id_empresa, $idPuntoEmision, $secuencial, $tipoAmbiente)) {
                        throw new Exception("El secuencial {$secuencial} ya está en uso para esta serie. Recargue e intente nuevamente.");
                    }
                }

                $sql = "INSERT INTO pedidos_cabecera
                        (id_empresa, id_cliente, fecha_pedido, observaciones,
                        estado, observaciones_internas, fecha_entrega, hora_inicial_entrega, hora_maxima_entrega, id_responsable_entrega,
                        id_establecimiento, id_punto_emision, establecimiento, punto_emision, secuencial, tipo_ambiente, created_by)
                        VALUES
                        (:id_empresa, :id_cliente, :fecha_pedido, :observaciones,
                        :estado, :observaciones_internas, :fecha_entrega, :hora_inicial_entrega, :hora_maxima_entrega, :id_responsable_entrega,
                        :id_establecimiento, :id_punto_emision, :establecimiento, :punto_emision, :secuencial, :tipo_ambiente, :created_by)
                        RETURNING id";

                $stmt = $this->db->prepare($sql);
                try {
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
                        'id_punto_emision' => $idPuntoEmision,
                        'establecimiento' => !empty($cabecera['establecimiento']) ? $cabecera['establecimiento'] : null,
                        'punto_emision' => !empty($cabecera['punto_emision']) ? $cabecera['punto_emision'] : null,
                        'secuencial' => $secuencial,
                        'tipo_ambiente' => $tipoAmbiente,
                        'created_by' => $id_usuario
                    ]);
                } catch (PDOException $e) {
                    if (($e->errorInfo[0] ?? '') === '23505') {
                        throw new Exception("El secuencial {$secuencial} ya está en uso para esta serie. Recargue e intente nuevamente.");
                    }
                    throw $e;
                }

                $id_pedido = $stmt->fetchColumn();

                $this->logService->registrar($id_usuario, $id_empresa, 'CREAR_PEDIDO', 'pedidos_cabecera', (int) $id_pedido, null, $cabecera);
            } else {
                // Actualizar Pedido
                $id_pedido = $cabecera['id'];
                $cabeceraAnterior = $this->repository->obtenerPorId((int) $id_pedido, $id_empresa);

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

                $this->logService->registrar($id_usuario, $id_empresa, 'ACTUALIZAR_PEDIDO', 'pedidos_cabecera', (int) $id_pedido, $cabeceraAnterior ?: null, $cabecera);

                $this->sincronizarDetalles((int) $id_pedido, $id_empresa, $detalles);
                $this->db->commit();
                return $id_pedido;
            }

            // Insertar Detalles (pedido nuevo: no hay nada que proteger todavía)
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

    /**
     * Reemplaza el detalle de un pedido EXISTENTE respetando lo ya consumido por
     * una Consignación de Venta o Factura de Venta (§ verificado con el usuario):
     * las líneas ya registradas en otro documento se actualizan en su lugar
     * (conservan su id, nunca se borran-y-reinsertan) y no se puede quitarlas ni
     * reducir su cantidad por debajo de lo ya consumido. Las líneas realmente
     * nuevas (sin id) se insertan; las que el usuario quitó y NO tenían consumo
     * se dan de baja lógica normalmente.
     */
    private function sincronizarDetalles(int $id_pedido, int $id_empresa, array $detallesNuevos): void {
        $existentes = $this->repository->obtenerDetalles($id_pedido, $id_empresa);
        $consumo = $this->repository->getCantidadConsumidaPorDetalle(array_column($existentes, 'id'));

        // Líneas nuevas que traen un id de pedidos_detalle real (edición in-situ).
        $porId = [];
        foreach ($detallesNuevos as $det) {
            $idDet = (int) ($det['id'] ?? 0);
            if ($idDet > 0) {
                $porId[$idDet] = $det;
            }
        }

        $stmtUpdate = $this->db->prepare(
            "UPDATE pedidos_detalle SET id_producto = :id_producto, cantidad = :cantidad,
                precio_unitario = :precio, subtotal = :subtotal, iva = :iva, total = :total
             WHERE id = :id"
        );
        $stmtBaja = $this->db->prepare("UPDATE pedidos_detalle SET eliminado = true WHERE id = :id");

        foreach ($existentes as $existente) {
            $idExistente = (int) $existente['id'];
            $consumida = (float) ($consumo[$idExistente] ?? 0);
            $nombre = $existente['producto_nombre'] ?? ('producto #' . $existente['id_producto']);

            if (!isset($porId[$idExistente])) {
                // El usuario quitó esta línea del formulario.
                if ($consumida > 0.0001) {
                    throw new Exception("No se puede quitar \"{$nombre}\" del pedido: ya tiene {$consumida} registrado en una consignación o factura.");
                }
                $stmtBaja->execute(['id' => $idExistente]);
                continue;
            }

            $nuevo = $porId[$idExistente];
            unset($porId[$idExistente]);

            if ($consumida > 0.0001 && (int) $nuevo['id_producto'] !== (int) $existente['id_producto']) {
                throw new Exception("No se puede cambiar el producto de \"{$nombre}\": ya tiene {$consumida} registrado en una consignación o factura.");
            }

            $nuevaCantidad = (float) ($nuevo['cantidad'] ?? 0);
            if ($nuevaCantidad < $consumida - 0.0001) {
                throw new Exception("No se puede reducir la cantidad de \"{$nombre}\" a {$nuevaCantidad}: ya tiene {$consumida} registrado en una consignación o factura.");
            }

            $stmtUpdate->execute([
                'id_producto' => $nuevo['id_producto'],
                'cantidad' => $nuevaCantidad,
                'precio' => $nuevo['precio_unitario'] ?? 0,
                'subtotal' => $nuevo['subtotal'] ?? 0,
                'iva' => $nuevo['iva'] ?? 0,
                'total' => $nuevo['total'] ?? 0,
                'id' => $idExistente,
            ]);
        }

        $stmtIns = $this->db->prepare(
            "INSERT INTO pedidos_detalle (id_pedido, id_producto, cantidad, precio_unitario, subtotal, iva, total)
             VALUES (:id_pedido, :id_producto, :cantidad, :precio, :subtotal, :iva, :total)"
        );
        foreach ($detallesNuevos as $det) {
            $idDet = (int) ($det['id'] ?? 0);
            if ($idDet > 0) {
                continue; // ya se procesó como actualización arriba (o se ignoró un id ajeno a este pedido)
            }
            $stmtIns->execute([
                'id_pedido' => $id_pedido,
                'id_producto' => $det['id_producto'],
                'cantidad' => $det['cantidad'],
                'precio' => $det['precio_unitario'] ?? 0,
                'subtotal' => $det['subtotal'] ?? 0,
                'iva' => $det['iva'] ?? 0,
                'total' => $det['total'] ?? 0,
            ]);
        }
    }

    public function eliminarPedido($id, $id_empresa, $id_usuario) {
        $enUso = $this->bloqueoService->verificarLibreOPropio(self::TABLA_BLOQUEO, (int) $id, $id_empresa, $id_usuario);
        if ($enUso !== null) {
            throw new Exception("Este pedido lo está usando ahora mismo {$enUso['usuario']}. Espera a que termine e intenta de nuevo.");
        }

        try {
            $this->db->beginTransaction();

            $cabeceraAnterior = $this->repository->obtenerPorId((int) $id, $id_empresa);

            $detalles = $this->repository->obtenerDetalles((int) $id, $id_empresa);
            $consumo = $this->repository->getCantidadConsumidaPorDetalle(array_column($detalles, 'id'));
            $totalConsumido = array_sum($consumo);
            if ($totalConsumido > 0.0001) {
                throw new Exception("No se puede eliminar este pedido: tiene ítems ya registrados en una consignación o factura ({$totalConsumido} unidad(es) en total).");
            }

            $sql = "UPDATE pedidos_cabecera SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :user
                    WHERE id = :id AND id_empresa = :id_empresa";
            $this->db->prepare($sql)->execute(['user' => $id_usuario, 'id' => $id, 'id_empresa' => $id_empresa]);

            $sqlDet = "UPDATE pedidos_detalle SET eliminado = true
                       WHERE id_pedido = :id";
            $this->db->prepare($sqlDet)->execute(['id' => $id]);

            $this->logService->registrar($id_usuario, $id_empresa, 'ELIMINAR_PEDIDO', 'pedidos_cabecera', (int) $id, $cabeceraAnterior ?: null);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
