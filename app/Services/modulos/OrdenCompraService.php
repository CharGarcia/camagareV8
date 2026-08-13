<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\OrdenCompraRepository;
use App\Rules\modulos\OrdenCompraRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;
use PDO;

class OrdenCompraService
{
    public function __construct(
        private OrdenCompraRepository $repository,
        private OrdenCompraRules      $rules,
        private LogSistemaService     $logService
    ) {}

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $orden = $this->repository->getById($id, $idEmpresa);
        if (!$orden) return null;
        $orden['detalle'] = $this->repository->getDetalle($id, $idEmpresa);
        return $orden;
    }

    public function getSiguienteSecuencial(int $idPuntoEmision): array
    {
        $secService = new SecuencialService();
        return $secService->obtenerSiguienteSecuencial($idPuntoEmision, 'Órdenes de compra');
    }

    public function crear(array $data, array $items): int
    {
        $this->rules->validarCabecera($data);
        $this->rules->validarDetalle($items);
        if (!in_array($data['estado'] ?? 'borrador', ['borrador', 'anulado'], true)) {
            throw new \Exception('Estado no válido para guardar manualmente.');
        }

        $db = $this->repository->getDb();

        $this->repository->beginTransaction();
        try {
            $db = $this->repository->getDb();

            // Obtener datos del punto de emisión
            $estabData = $this->_getDatosSerie((int)$data['id_establecimiento'], (int)$data['id_punto_emision']);

            // Obtener siguiente secuencial
            $secService = new SecuencialService();
            $secResult  = $secService->obtenerSiguienteSecuencial((int)$data['id_punto_emision'], 'Órdenes de compra');

            $data['establecimiento'] = $estabData['establecimiento'];
            $data['punto_emision']   = $estabData['punto_emision'];
            $data['secuencial']      = $secResult['formateado'];

            // El listado filtra por el tipo_ambiente vigente de la empresa (separa
            // pruebas/producción); sin esto la orden se guarda pero no aparece en la lista.
            $stmtAmb = $db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa");
            $stmtAmb->execute([':id_empresa' => $data['id_empresa']]);
            $data['tipo_ambiente'] = $stmtAmb->fetchColumn() ?: '1';

            // Mismo control anti-duplicado que Factura de Venta: el candado de
            // obtenerSiguienteSecuencial() ya evita la mayoría de las carreras, pero si
            // hay puntos de emisión "gemelos" (mismo código, distinto id) el candado no
            // los detecta — este chequeo + el índice único de la tabla son la red final.
            if ($this->repository->existeSecuencial(
                (int) $data['id_empresa'],
                (int) $data['id_establecimiento'],
                (int) $data['id_punto_emision'],
                (string) $data['secuencial']
            )) {
                throw new \Exception('El número de secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
            }

            try {
                $idOrden = $this->repository->insertar($data);
            } catch (\PDOException $e) {
                if (($e->errorInfo[0] ?? '') === '23505') {
                    throw new \Exception('El secuencial ' . $data['secuencial'] . ' ya está en uso para esta serie. Recargue e intente nuevamente.');
                }
                throw $e;
            }

            foreach ($items as $item) {
                $this->repository->insertarDetalle([
                    'id_orden'        => $idOrden,
                    'id_empresa'      => $data['id_empresa'],
                    'id_producto'     => $item['id_producto'] ?? null,
                    'descripcion'     => trim($item['descripcion']),
                    'cantidad'        => (float)$item['cantidad'],
                    'precio_unitario' => (float)$item['precio_unitario'],
                    'created_by'      => $data['created_by'],
                ]);
            }

            $this->logService->registrar(
                (int)$data['created_by'],
                (int)$data['id_empresa'],
                'crear',
                'ordenes_compra',
                $idOrden,
                null,
                $data
            );

            $this->repository->commit();
            return $idOrden;
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data, array $items): void
    {
        $this->rules->validarCabecera($data);
        $this->rules->validarDetalle($items);

        $this->repository->beginTransaction();
        try {
            $anterior = $this->repository->getById($id, $idEmpresa);
            if (!$anterior) throw new \Exception('Orden de compra no encontrada.');
            $this->_validarEditable($anterior);

            // Enviado/Aprobado/Recibido solo los pone el sistema (enviar correo, aprobar,
            // vincular con una compra). El formulario de edición no puede saltarse ese flujo.
            if (!in_array($data['estado'] ?? 'borrador', ['borrador', 'anulado'], true)) {
                throw new \Exception('Estado no válido para guardar manualmente.');
            }

            $estabData = $this->_getDatosSerie((int)$data['id_establecimiento'], (int)$data['id_punto_emision']);
            $data['establecimiento'] = $estabData['establecimiento'];
            $data['punto_emision']   = $estabData['punto_emision'];

            $this->repository->actualizar($id, $idEmpresa, $data);
            $this->repository->eliminarDetalle($id, $idEmpresa);

            foreach ($items as $item) {
                $this->repository->insertarDetalle([
                    'id_orden'        => $id,
                    'id_empresa'      => $idEmpresa,
                    'id_producto'     => $item['id_producto'] ?? null,
                    'descripcion'     => trim($item['descripcion']),
                    'cantidad'        => (float)$item['cantidad'],
                    'precio_unitario' => (float)$item['precio_unitario'],
                    'created_by'      => $data['updated_by'],
                ]);
            }

            $this->logService->registrar(
                (int)$data['updated_by'],
                $idEmpresa,
                'actualizar',
                'ordenes_compra',
                $id,
                $anterior,
                $data
            );

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Solo se puede eliminar una orden en Borrador. Enviada/Aprobada se anulan (anular()); Recibida se desvincula primero. */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->repository->beginTransaction();
        try {
            $anterior = $this->repository->getById($id, $idEmpresa);
            if (!$anterior) throw new \Exception('Orden de compra no encontrada.');

            $estado = $anterior['estado'] ?? '';
            if ($estado !== 'borrador') {
                $mapa = [
                    'enviado'  => 'Esta orden ya fue enviada al proveedor: no se puede eliminar, solo anular.',
                    'aprobado' => 'Esta orden ya fue aprobada: no se puede eliminar, solo anular.',
                    'parcial'  => 'Esta orden ya tiene entregas recibidas; desvincule primero todas las compras desde Compras.',
                    'recibido' => 'Esta orden ya fue recibida (vinculada a una compra); desvincúlela primero desde la compra correspondiente.',
                    'anulado'  => 'Esta orden ya está anulada.',
                ];
                throw new \Exception($mapa[$estado] ?? 'Solo se puede eliminar una orden en estado Borrador.');
            }

            $this->repository->eliminar($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'ordenes_compra',
                $id,
                $anterior,
                null
            );

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Anula una orden Enviada o Aprobada (no elimina el registro, solo cambia su estado). */
    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $orden = $this->repository->getById($id, $idEmpresa);
        if (!$orden) throw new \Exception('Orden de compra no encontrada.');

        $estado = $orden['estado'] ?? '';
        if (!in_array($estado, ['enviado', 'aprobado'], true)) {
            $mapa = [
                'borrador' => 'Una orden en Borrador se elimina, no se anula.',
                'parcial'  => 'Esta orden ya tiene entregas recibidas; no se puede anular. Desvincule las compras o ciérrela como recibida desde Compras.',
                'recibido' => 'Esta orden ya fue recibida (vinculada a una compra); desvincúlela primero desde la compra correspondiente.',
                'anulado'  => 'Esta orden ya está anulada.',
            ];
            throw new \Exception($mapa[$estado] ?? 'Esta orden no se puede anular en su estado actual.');
        }

        $this->repository->cambiarEstado($id, $idEmpresa, 'anulado', $idUsuario, false);
        $this->logService->registrar($idUsuario, $idEmpresa, 'anular', 'ordenes_compra', $id, ['estado' => $estado], ['estado' => 'anulado']);
    }

    /**
     * Duplica una orden Enviada/Aprobada/Recibida Parcialmente en una nueva orden en
     * Borrador, para poder corregirla y volver a enviarla (la original ya no se puede
     * editar directamente). Si viene de Recibido Parcial, solo copia el SALDO pendiente
     * de cada línea (pedido - ya recibido en las compras vinculadas), no el pedido
     * completo — para no duplicar lo que ya llegó. $anularOriginal solo aplica cuando la
     * original está en Enviado/Aprobado (sin entregas reales todavía); una orden con
     * entregas parciales no se anula automáticamente, porque ya tiene historial real.
     */
    public function duplicar(int $id, int $idEmpresa, int $idUsuario, bool $anularOriginal): int
    {
        $orden = $this->repository->getById($id, $idEmpresa);
        if (!$orden) throw new \Exception('Orden de compra no encontrada.');

        $estado = $orden['estado'] ?? '';
        if (!in_array($estado, ['enviado', 'aprobado', 'parcial'], true)) {
            throw new \Exception('Solo se puede duplicar una orden Enviada, Aprobada o Recibida Parcialmente.');
        }

        $lineas = $this->repository->getDetalle($id, $idEmpresa);
        $items  = [];

        if ($estado === 'parcial') {
            $recibido = $this->repository->getRecibidoAcumuladoPorProducto($id, $idEmpresa);
            foreach ($lineas as $l) {
                $idProd       = (int) ($l['id_producto'] ?? 0);
                $cantPedida   = (float) ($l['cantidad'] ?? 0);
                $cantRestante = $idProd > 0 ? max(0.0, $cantPedida - ($recibido[$idProd] ?? 0.0)) : $cantPedida;
                if ($cantRestante <= 0.001) continue; // esta línea ya se recibió completa
                $items[] = [
                    'id_producto'     => $l['id_producto'] ?? null,
                    'descripcion'     => $l['descripcion'],
                    'cantidad'        => $cantRestante,
                    'precio_unitario' => $l['precio_unitario'],
                ];
            }
        } else {
            foreach ($lineas as $l) {
                $items[] = [
                    'id_producto'     => $l['id_producto'] ?? null,
                    'descripcion'     => $l['descripcion'],
                    'cantidad'        => $l['cantidad'],
                    'precio_unitario' => $l['precio_unitario'],
                ];
            }
        }
        if (empty($items)) {
            throw new \Exception('No quedan cantidades pendientes por duplicar: esta orden ya se recibió por completo.');
        }

        $data = [
            'id_empresa'         => $idEmpresa,
            'id_proveedor'       => $orden['id_proveedor'],
            'id_establecimiento' => $orden['id_establecimiento'],
            'id_punto_emision'   => $orden['id_punto_emision'],
            'fecha_orden'        => date('Y-m-d'),
            'fecha_recepcion'    => null,
            'observaciones'      => trim('Duplicada de la orden ' . ($orden['numero_orden'] ?? '') . '.'),
            'estado'             => 'borrador',
            'created_by'         => $idUsuario,
            'updated_by'         => $idUsuario,
        ];

        $idNueva = $this->crear($data, $items);

        // Solo se ofrece anular la original cuando aún no tiene entregas reales.
        if ($anularOriginal && in_array($estado, ['enviado', 'aprobado'], true)) {
            $this->anular($id, $idEmpresa, $idUsuario);
        }

        return $idNueva;
    }

    /**
     * Genera (o reutiliza si ya existe) el token de aprobación pública de la orden.
     * Se llama al enviar el correo, para poder incluir el enlace de aprobación.
     */
    public function obtenerTokenAprobacion(int $id, int $idEmpresa): string
    {
        $orden = $this->repository->getById($id, $idEmpresa);
        if (!$orden) throw new \Exception('Orden de compra no encontrada.');
        if (!empty($orden['aprobacion_token'])) {
            return (string) $orden['aprobacion_token'];
        }
        $token = bin2hex(random_bytes(24));
        $this->repository->setTokenAprobacion($id, $token);
        return $token;
    }

    /** Marca la orden como enviada al proveedor (borrador → enviado); no hace nada si ya no está en borrador. */
    public function marcarEnviado(int $id, int $idEmpresa, int $idUsuario): void
    {
        $orden = $this->repository->getById($id, $idEmpresa);
        if (!$orden) throw new \Exception('Orden de compra no encontrada.');
        if (($orden['estado'] ?? '') !== 'borrador') {
            return; // ya se envió antes (reenvío) o está en un estado posterior: no hay transición que hacer.
        }
        $this->repository->marcarEnviado($id, $idEmpresa, $idUsuario);
        $this->logService->registrar($idUsuario, $idEmpresa, 'enviar', 'ordenes_compra', $id, ['estado' => 'borrador'], ['estado' => 'enviado']);
    }

    /**
     * Aprueba la orden (enviado → aprobado). $idUsuario null = aprobación pública del
     * proveedor por token (sin sesión); con id = aprobación manual desde el sistema.
     */
    public function marcarAprobado(int $id, int $idEmpresa, ?int $idUsuario, string $aprobadoPor, string $ip = '0.0.0.0'): void
    {
        $orden = $this->repository->getById($id, $idEmpresa);
        if (!$orden) throw new \Exception('Orden de compra no encontrada.');

        $estado = $orden['estado'] ?? '';
        if ($estado !== 'enviado') {
            $mapa = [
                'borrador' => 'Esta orden todavía no ha sido enviada al proveedor.',
                'aprobado' => 'Esta orden ya fue aprobada.',
                'recibido' => 'Esta orden ya fue recibida.',
                'anulado'  => 'Esta orden está anulada.',
            ];
            throw new \Exception($mapa[$estado] ?? 'Esta orden no puede aprobarse en su estado actual.');
        }

        $this->repository->marcarAprobado($id, $idEmpresa, $idUsuario, $aprobadoPor, $ip);
        try {
            $this->logService->registrar(
                $idUsuario ?? 0, $idEmpresa, 'aprobar', 'ordenes_compra', $id,
                ['estado' => 'enviado'], ['estado' => 'aprobado', 'aprobado_por' => $aprobadoPor, 'ip' => $ip]
            );
        } catch (\Throwable $e) { /* log no crítico: no debe impedir que la aprobación quede registrada */ }
    }

    /** Orden por su token público de aprobación (para la página /aprobar-orden-compra/{token}). */
    public function getAprobacionPorToken(string $token): ?array
    {
        $orden = $this->repository->getPorTokenAprobacion($token);
        if (!$orden) return null;
        $orden['detalle'] = $this->repository->getDetalle((int) $orden['id'], (int) $orden['id_empresa']);
        return $orden;
    }

    /** Aprobación pública por el proveedor desde el enlace del correo (sin sesión). */
    public function aprobarPorTokenProveedor(string $token, string $ip): array
    {
        $orden = $this->getAprobacionPorToken($token);
        if (!$orden) {
            throw new \Exception('El enlace no es válido o la orden ya no está disponible.');
        }
        $this->marcarAprobado((int) $orden['id'], (int) $orden['id_empresa'], null, 'Proveedor (vía correo)', $ip);
        return ['numero_orden' => $orden['numero_orden'] ?? ''];
    }

    /** Bloquea edición/eliminación una vez enviada la orden (solo lectura desde 'enviado' en adelante). */
    private function _validarEditable(array $orden): void
    {
        $estado = $orden['estado'] ?? '';
        $participios = ['enviado' => 'enviada', 'aprobado' => 'aprobada', 'parcial' => 'recibida parcialmente', 'recibido' => 'recibida', 'anulado' => 'anulada'];
        if (isset($participios[$estado])) {
            throw new \Exception("Esta orden ya fue {$participios[$estado]} y no se puede editar.");
        }
    }

    private function _getDatosSerie(int $idEstablecimiento, int $idPuntoEmision): array
    {
        $db = $this->repository->getDb();
        $sql = "SELECT ee.codigo AS establecimiento, pe.codigo_punto AS punto_emision
                FROM empresa_establecimiento ee
                JOIN empresa_punto_emision pe ON pe.id = :id_punto AND pe.id_establecimiento = ee.id
                WHERE ee.id = :id_estab AND ee.estado = 'activo' AND pe.estado = 'activo'
                LIMIT 1";
        $st = $db->prepare($sql);
        $st->execute([':id_punto' => $idPuntoEmision, ':id_estab' => $idEstablecimiento]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \Exception('No se encontraron datos del establecimiento o punto de emisión seleccionado.');
        }
        return $row;
    }
}
