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
            if (($anterior['estado'] ?? '') === 'recibido') {
                throw new \Exception('Esta orden ya fue recibida (vinculada a una compra) y no se puede editar. Desvincúlela primero desde la compra correspondiente si necesita modificarla.');
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

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->repository->beginTransaction();
        try {
            $anterior = $this->repository->getById($id, $idEmpresa);
            if (!$anterior) throw new \Exception('Orden de compra no encontrada.');
            if (($anterior['estado'] ?? '') === 'recibido') {
                throw new \Exception('Esta orden ya fue recibida (vinculada a una compra) y no se puede eliminar. Desvincúlela primero desde la compra correspondiente.');
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
