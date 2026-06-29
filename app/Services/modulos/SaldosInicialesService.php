<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\SaldosInicialesRepository;
use App\Rules\modulos\SaldosInicialesRules;
use App\Services\LogSistemaService;
use App\core\Database;

class SaldosInicialesService
{
    private SaldosInicialesRepository $repo;
    private SaldosInicialesRules $rules;
    private LogSistemaService $log;
    private ?InventarioService $inventarioService = null;

    public function __construct(
        SaldosInicialesRepository $repo,
        SaldosInicialesRules $rules,
        LogSistemaService $log
    ) {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
    }

    // ─────────────────────────────────────────────────────────
    // CXC
    // ─────────────────────────────────────────────────────────

    public function getCxcListado(int $idEmpresa, array $filtros = []): array
    {
        return $this->repo->getCxcListado($idEmpresa, $filtros);
    }

    public function crearCxc(array $data): int
    {
        $this->normalizarNroDocumento($data);
        $this->resolverClientePorId($data, (int)$data['id_empresa']);
        $this->rules->validarCxc($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertCxc($data);
            $this->log->registrar(
                (int)($data['created_by'] ?? 0),
                (int)$data['id_empresa'],
                'CREAR',
                'saldos_iniciales_cxc',
                $id,
                null,
                $data
            );
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarCxc(int $id, int $idEmpresa, array $data): void
    {
        $registro = $this->repo->getCxcPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxcCobros($id)) {
            throw new \RuntimeException('No se puede modificar: el registro ya tiene cobros registrados.');
        }
        $this->normalizarNroDocumento($data);
        $this->resolverClientePorId($data, $idEmpresa);
        $this->rules->validarCxc($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->updateCxc($id, $idEmpresa, $data);
            $this->log->registrar(
                (int)($data['updated_by'] ?? 0),
                $idEmpresa,
                'ACTUALIZAR',
                'saldos_iniciales_cxc',
                $id,
                $registro,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarCxc(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repo->getCxcPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxcCobros($id)) {
            throw new \RuntimeException('No se puede eliminar: el registro ya tiene cobros registrados.');
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteCxc($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'saldos_iniciales_cxc', $id, $registro, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function importarCxcDesdeArray(int $idEmpresa, int $idUsuario, array $filas, string $nombreArchivo): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $errores   = [];
            $insertados = 0;
            $idLote    = null;

            foreach ($filas as $i => $fila) {
                $fila['id_empresa'] = $idEmpresa;
                $fila['created_by'] = $idUsuario;
                try {
                    $this->normalizarNroDocumento($fila);
                    $this->resolverClientePorIdentificacion($fila, $idEmpresa);
                    $this->rules->validarCxc($fila);
                    if ($idLote === null) {
                        $idLote = $this->repo->insertLote($idEmpresa, 'CXC', $nombreArchivo, 0, $idUsuario);
                    }
                    $fila['id_lote'] = $idLote;
                    $this->repo->insertCxc($fila);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = "Fila " . ($i + 2) . ": " . $e->getMessage();
                }
            }

            if ($idLote !== null) {
                $db->prepare("UPDATE saldos_iniciales_lotes SET total_registros = :n WHERE id = :id")
                   ->execute([':n' => $insertados, ':id' => $idLote]);
            }

            $db->commit();
            return ['insertados' => $insertados, 'errores' => $errores];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXP
    // ─────────────────────────────────────────────────────────

    public function getCxpListado(int $idEmpresa, array $filtros = []): array
    {
        return $this->repo->getCxpListado($idEmpresa, $filtros);
    }

    public function crearCxp(array $data): int
    {
        $this->normalizarNroDocumento($data);
        $this->resolverProveedorPorId($data, (int)$data['id_empresa']);
        $this->rules->validarCxp($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertCxp($data);
            $this->log->registrar(
                (int)($data['created_by'] ?? 0),
                (int)$data['id_empresa'],
                'CREAR',
                'saldos_iniciales_cxp',
                $id,
                null,
                $data
            );
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarCxp(int $id, int $idEmpresa, array $data): void
    {
        $registro = $this->repo->getCxpPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxpPagos($id)) {
            throw new \RuntimeException('No se puede modificar: el registro ya tiene pagos registrados.');
        }
        $this->normalizarNroDocumento($data);
        $this->resolverProveedorPorId($data, $idEmpresa);
        $this->rules->validarCxp($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->updateCxp($id, $idEmpresa, $data);
            $this->log->registrar(
                (int)($data['updated_by'] ?? 0),
                $idEmpresa,
                'ACTUALIZAR',
                'saldos_iniciales_cxp',
                $id,
                $registro,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarCxp(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repo->getCxpPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        if ($this->repo->tieneCxpPagos($id)) {
            throw new \RuntimeException('No se puede eliminar: el registro ya tiene pagos registrados.');
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteCxp($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'saldos_iniciales_cxp', $id, $registro, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function importarCxpDesdeArray(int $idEmpresa, int $idUsuario, array $filas, string $nombreArchivo): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $errores    = [];
            $insertados = 0;
            $idLote     = null;

            foreach ($filas as $i => $fila) {
                $fila['id_empresa'] = $idEmpresa;
                $fila['created_by'] = $idUsuario;
                try {
                    $this->normalizarNroDocumento($fila);
                    $this->resolverProveedorPorIdentificacion($fila, $idEmpresa);
                    $this->rules->validarCxp($fila);
                    if ($idLote === null) {
                        $idLote = $this->repo->insertLote($idEmpresa, 'CXP', $nombreArchivo, 0, $idUsuario);
                    }
                    $fila['id_lote'] = $idLote;
                    $this->repo->insertCxp($fila);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = "Fila " . ($i + 2) . ": " . $e->getMessage();
                }
            }

            if ($idLote !== null) {
                $db->prepare("UPDATE saldos_iniciales_lotes SET total_registros = :n WHERE id = :id")
                   ->execute([':n' => $insertados, ':id' => $idLote]);
            }

            $db->commit();
            return ['insertados' => $insertados, 'errores' => $errores];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // RESOLUCIÓN DE CLIENTE / PROVEEDOR
    // (deben existir registrados antes de cargar el saldo)
    // ─────────────────────────────────────────────────────────

    /**
     * Normaliza el Nº de documento al formato 000-000-000000000
     * (3 establecimiento - 3 punto emisión - 9 secuencial).
     * Si no se puede inferir, lo deja igual y las Rules lo rechazarán.
     */
    private function normalizarNroDocumento(array &$data): void
    {
        $v = trim((string)($data['nro_documento'] ?? ''));
        if ($v === '') return;

        $parts = explode('-', $v);
        if (count($parts) === 3) {
            $p1 = str_pad(preg_replace('/\D/', '', $parts[0]), 3, '0', STR_PAD_LEFT);
            $p2 = str_pad(preg_replace('/\D/', '', $parts[1]), 3, '0', STR_PAD_LEFT);
            $p3 = str_pad(preg_replace('/\D/', '', $parts[2]), 9, '0', STR_PAD_LEFT);
            $data['nro_documento'] = "{$p1}-{$p2}-{$p3}";
            return;
        }

        $digits = preg_replace('/\D/', '', $v);
        if ($digits !== '' && strlen($digits) <= 9) {
            $data['nro_documento'] = '001-001-' . str_pad($digits, 9, '0', STR_PAD_LEFT);
        }
    }

    public function buscarClientes(int $idEmpresa, string $q): array
    {
        return $this->repo->buscarClientes($idEmpresa, $q);
    }

    public function buscarProveedores(int $idEmpresa, string $q): array
    {
        return $this->repo->buscarProveedores($idEmpresa, $q);
    }

    /**
     * Carga manual: valida que el cliente exista (por id) y rellena
     * nombre_cliente / ruc_cliente con los valores oficiales del registro.
     */
    private function resolverClientePorId(array &$data, int $idEmpresa): void
    {
        $idCliente = (int)($data['id_cliente'] ?? 0);
        if ($idCliente <= 0) {
            throw new \RuntimeException('Debe seleccionar un cliente registrado.');
        }
        $cliente = $this->repo->getClientePorId($idEmpresa, $idCliente);
        if (!$cliente) {
            throw new \RuntimeException('El cliente seleccionado no está registrado. Créelo primero en el módulo de Clientes.');
        }
        $data['id_cliente']     = (int)$cliente['id'];
        $data['nombre_cliente'] = $cliente['nombre'];
        $data['ruc_cliente']    = $cliente['identificacion'];
    }

    /**
     * Importación: resuelve el cliente por su identificación. Si no existe,
     * rechaza la fila para que el usuario lo cree primero.
     */
    private function resolverClientePorIdentificacion(array &$data, int $idEmpresa): void
    {
        $ident = trim((string)($data['identificacion'] ?? $data['ruc_cliente'] ?? ''));
        if ($ident === '') {
            throw new \RuntimeException('La identificación del cliente es obligatoria.');
        }
        $cliente = $this->repo->getClientePorIdentificacion($idEmpresa, $ident);
        if (!$cliente) {
            throw new \RuntimeException("El cliente con identificación {$ident} no está registrado. Créelo primero en el módulo de Clientes.");
        }
        $data['id_cliente']     = (int)$cliente['id'];
        $data['nombre_cliente'] = $cliente['nombre'];
        $data['ruc_cliente']    = $cliente['identificacion'];
    }

    /**
     * Carga manual: valida que el proveedor exista (por id) y rellena
     * nombre_proveedor / ruc_proveedor con los valores oficiales del registro.
     */
    private function resolverProveedorPorId(array &$data, int $idEmpresa): void
    {
        $idProveedor = (int)($data['id_proveedor'] ?? 0);
        if ($idProveedor <= 0) {
            throw new \RuntimeException('Debe seleccionar un proveedor registrado.');
        }
        $proveedor = $this->repo->getProveedorPorId($idEmpresa, $idProveedor);
        if (!$proveedor) {
            throw new \RuntimeException('El proveedor seleccionado no está registrado. Créelo primero en el módulo de Proveedores.');
        }
        $data['id_proveedor']     = (int)$proveedor['id'];
        $data['nombre_proveedor'] = $proveedor['nombre'];
        $data['ruc_proveedor']    = $proveedor['identificacion'];
    }

    /**
     * Importación: resuelve el proveedor por su identificación. Si no existe,
     * rechaza la fila para que el usuario lo cree primero.
     */
    private function resolverProveedorPorIdentificacion(array &$data, int $idEmpresa): void
    {
        $ident = trim((string)($data['identificacion'] ?? $data['ruc_proveedor'] ?? ''));
        if ($ident === '') {
            throw new \RuntimeException('La identificación del proveedor es obligatoria.');
        }
        $proveedor = $this->repo->getProveedorPorIdentificacion($idEmpresa, $ident);
        if (!$proveedor) {
            throw new \RuntimeException("El proveedor con identificación {$ident} no está registrado. Créelo primero en el módulo de Proveedores.");
        }
        $data['id_proveedor']     = (int)$proveedor['id'];
        $data['nombre_proveedor'] = $proveedor['nombre'];
        $data['ruc_proveedor']    = $proveedor['identificacion'];
    }

    // ─────────────────────────────────────────────────────────
    // BANCOS
    // ─────────────────────────────────────────────────────────

    public function getBancosDisponibles(int $idEmpresa): array
    {
        return $this->repo->getBancosDisponibles($idEmpresa);
    }

    public function getEfectivoDisponibles(int $idEmpresa): array
    {
        return $this->repo->getEfectivoDisponibles($idEmpresa);
    }

    public function getAnticiposDisponibles(int $idEmpresa): array
    {
        return $this->repo->getAnticiposDisponibles($idEmpresa);
    }

    // ─────────────────────────────────────────────────────────
    // ANTICIPOS — saldo inicial atado a cliente o proveedor
    // ─────────────────────────────────────────────────────────

    public function getSaldosAnticipo(int $idEmpresa): array
    {
        return $this->repo->getSaldosAnticipo($idEmpresa);
    }

    public function crearAnticipo(array $data): int
    {
        $this->resolverAnticipo($data, (int)$data['id_empresa']);
        $this->rules->validarAnticipo($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertAnticipo($data);
            $this->log->registrar((int)($data['created_by'] ?? 0), (int)$data['id_empresa'], 'CREAR', 'saldos_iniciales_anticipos', $id, null, $data);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarAnticipo(int $id, int $idEmpresa, array $data): void
    {
        $registro = $this->repo->getAnticipoPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        $this->resolverAnticipo($data, $idEmpresa);
        $this->rules->validarAnticipo($data);
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->updateAnticipo($id, $idEmpresa, $data);
            $this->log->registrar((int)($data['updated_by'] ?? 0), $idEmpresa, 'ACTUALIZAR', 'saldos_iniciales_anticipos', $id, $registro, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarAnticipo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repo->getAnticipoPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteAnticipo($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'saldos_iniciales_anticipos', $id, $registro, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Resuelve la forma de anticipo (define la dirección) y el cliente/proveedor.
     * El anticipo SIEMPRE queda atado a un cliente (INGRESO) o proveedor (EGRESO).
     */
    private function resolverAnticipo(array &$data, int $idEmpresa): void
    {
        $idForma = (int)($data['id_forma_pago'] ?? 0);
        if ($idForma <= 0) {
            throw new \RuntimeException('Debe seleccionar una forma de anticipo.');
        }
        $forma = $this->repo->getFormaAnticipoPorId($idEmpresa, $idForma);
        if (!$forma) {
            throw new \RuntimeException('La forma de anticipo seleccionada no es válida.');
        }
        $tipo = strtoupper((string)($forma['aplica_en'] ?? '')) === 'EGRESO' ? 'PROVEEDOR' : 'CLIENTE';
        $data['tipo'] = $tipo;

        $idTercero = (int)($data['id_tercero'] ?? 0);
        if ($idTercero <= 0) {
            throw new \RuntimeException($tipo === 'CLIENTE'
                ? 'Debe seleccionar un cliente registrado.'
                : 'Debe seleccionar un proveedor registrado.');
        }
        $tercero = $tipo === 'CLIENTE'
            ? $this->repo->getClientePorId($idEmpresa, $idTercero)
            : $this->repo->getProveedorPorId($idEmpresa, $idTercero);
        if (!$tercero) {
            throw new \RuntimeException($tipo === 'CLIENTE'
                ? 'El cliente seleccionado no está registrado. Créelo primero.'
                : 'El proveedor seleccionado no está registrado. Créelo primero.');
        }
        $data['id_tercero']     = (int)$tercero['id'];
        $data['nombre_tercero'] = $tercero['nombre'];
        $data['ruc_tercero']    = $tercero['identificacion'];
    }

    // ─────────────────────────────────────────────────────────
    // INVENTARIO — saldos de apertura (entradas al kardex)
    // ─────────────────────────────────────────────────────────

    private function getInventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->log
            );
        }
        return $this->inventarioService;
    }

    public function getSaldosInventario(int $idEmpresa): array
    {
        return $this->repo->getSaldosInventario($idEmpresa);
    }

    public function guardarSaldoInventario(int $idEmpresa, int $idUsuario, array $data): int
    {
        $this->rules->validarInventario($data);
        // Verificar que el producto exista en la empresa
        if (!$this->repo->getProductoPorId($idEmpresa, (int)$data['id_producto'])) {
            throw new \RuntimeException('El producto seleccionado no está registrado.');
        }
        $payload = [
            'id_producto'     => (int)$data['id_producto'],
            'id_bodega'       => (int)$data['id_bodega'],
            'tipo_movimiento' => 'entrada',
            'referencia_tipo' => 'SALDO_INICIAL',
            'cantidad'        => abs((float)$data['cantidad']),
            'costo_unitario'  => (float)($data['costo_unitario'] ?? 0),
            'numero_lote'     => trim($data['lote'] ?? '') ?: null,
            'fecha_caducidad' => !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
            'nup'             => trim($data['nup'] ?? '') ?: null,
            'observaciones'   => trim($data['observaciones'] ?? '') ?: 'Saldo inicial de inventario',
        ];
        $idKardex = $this->getInventarioService()->ajusteManual($payload, $idEmpresa, $idUsuario);
        // El kardex tiene default fijo de tipo_ambiente='1'; alinearlo al ambiente real de la empresa.
        $this->repo->fixKardexAmbiente($idKardex, $idEmpresa);
        return $idKardex;
    }

    public function eliminarSaldoInventario(int $idMov, int $idEmpresa, int $idUsuario): void
    {
        // ignorarRestriccion=true: es nuestra entrada de apertura controlada (referencia SALDO_INICIAL)
        $this->getInventarioService()->eliminarMovimiento($idMov, $idEmpresa, $idUsuario, true);
    }

    /**
     * Importa saldos de inventario. Cada fila se resuelve por código de
     * producto y nombre de bodega; si no existen, la fila se rechaza.
     */
    public function importarInventarioDesdeArray(int $idEmpresa, int $idUsuario, array $filas): array
    {
        $errores = [];
        $insertados = 0;
        foreach ($filas as $i => $fila) {
            try {
                $codigo = trim((string)($fila['codigo'] ?? ''));
                if ($codigo === '') {
                    throw new \RuntimeException('El código del producto es obligatorio.');
                }
                $prod = $this->repo->getProductoPorCodigo($idEmpresa, $codigo);
                if (!$prod) {
                    throw new \RuntimeException("El producto con código {$codigo} no está registrado. Créelo primero.");
                }
                $nombreBodega = trim((string)($fila['bodega'] ?? ''));
                if ($nombreBodega === '') {
                    throw new \RuntimeException('La bodega es obligatoria.');
                }
                $bodega = $this->repo->getBodegaPorNombre($idEmpresa, $nombreBodega);
                if (!$bodega) {
                    throw new \RuntimeException("La bodega '{$nombreBodega}' no existe.");
                }
                $this->guardarSaldoInventario($idEmpresa, $idUsuario, [
                    'id_producto'     => (int)$prod['id'],
                    'id_bodega'       => (int)$bodega['id'],
                    'cantidad'        => $fila['cantidad'] ?? 0,
                    'costo_unitario'  => $fila['costo_unitario'] ?? 0,
                    'lote'            => $fila['lote'] ?? '',
                    'fecha_caducidad' => $fila['fecha_caducidad'] ?? '',
                    'nup'             => $fila['nup'] ?? '',
                    'observaciones'   => $fila['observaciones'] ?? '',
                ]);
                $insertados++;
            } catch (\Throwable $e) {
                $errores[] = 'Fila ' . ($i + 2) . ': ' . $e->getMessage();
            }
        }
        return ['insertados' => $insertados, 'errores' => $errores];
    }

    // ─────────────────────────────────────────────────────────
    // CONSIGNACIONES — registro de saldo pendiente (no afecta stock)
    // ─────────────────────────────────────────────────────────

    public function getSaldosConsignacion(int $idEmpresa): array
    {
        return $this->repo->getSaldosConsignacion($idEmpresa);
    }

    public function crearConsignacion(array $data): int
    {
        $this->normalizarNroDocumento($data);
        $this->resolverClientePorId($data, (int)$data['id_empresa']);
        $this->resolverProductoConsignacion($data, (int)$data['id_empresa']);
        $this->resolverVendedorBodegaConsignacion($data, (int)$data['id_empresa']);
        $this->rules->validarConsignacion($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertConsignacion($data);
            $this->log->registrar((int)($data['created_by'] ?? 0), (int)$data['id_empresa'], 'CREAR', 'saldos_iniciales_consignaciones', $id, null, $data);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarConsignacion(int $id, int $idEmpresa, array $data): void
    {
        $registro = $this->repo->getConsignacionPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        $this->normalizarNroDocumento($data);
        $this->resolverClientePorId($data, $idEmpresa);
        $this->resolverProductoConsignacion($data, $idEmpresa);
        $this->resolverVendedorBodegaConsignacion($data, $idEmpresa);
        $this->rules->validarConsignacion($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->updateConsignacion($id, $idEmpresa, $data);
            $this->log->registrar((int)($data['updated_by'] ?? 0), $idEmpresa, 'ACTUALIZAR', 'saldos_iniciales_consignaciones', $id, $registro, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarConsignacion(int $id, int $idEmpresa, int $idUsuario): void
    {
        $registro = $this->repo->getConsignacionPorId($id, $idEmpresa);
        if (!$registro) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteConsignacion($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'saldos_iniciales_consignaciones', $id, $registro, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Importa registros de consignación. Cada fila se resuelve por
     * identificación de cliente y código de producto (obligatorios, deben
     * existir); vendedor y bodega por nombre (opcionales).
     */
    public function importarConsignacionDesdeArray(int $idEmpresa, int $idUsuario, array $filas): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $errores = [];
            $insertados = 0;
            foreach ($filas as $i => $fila) {
                try {
                    $data = $fila;
                    $data['id_empresa'] = $idEmpresa;
                    $data['created_by'] = $idUsuario;

                    $ident = trim((string)($fila['identificacion'] ?? ''));
                    if ($ident === '') {
                        throw new \RuntimeException('La identificación del cliente es obligatoria.');
                    }
                    $cli = $this->repo->getClientePorIdentificacion($idEmpresa, $ident);
                    if (!$cli) {
                        throw new \RuntimeException("El cliente con identificación {$ident} no está registrado. Créelo primero.");
                    }
                    $data['id_cliente'] = (int)$cli['id'];

                    $codigo = trim((string)($fila['codigo'] ?? ''));
                    if ($codigo === '') {
                        throw new \RuntimeException('El código del producto es obligatorio.');
                    }
                    $prod = $this->repo->getProductoPorCodigo($idEmpresa, $codigo);
                    if (!$prod) {
                        throw new \RuntimeException("El producto con código {$codigo} no está registrado. Créelo primero.");
                    }
                    $data['id_producto'] = (int)$prod['id'];

                    $nv = trim((string)($fila['vendedor'] ?? ''));
                    if ($nv !== '') {
                        $v = $this->repo->getVendedorPorNombre($idEmpresa, $nv);
                        $data['id_vendedor'] = $v ? (int)$v['id'] : null;
                    }
                    $nb = trim((string)($fila['bodega'] ?? ''));
                    if ($nb !== '') {
                        $b = $this->repo->getBodegaPorNombre($idEmpresa, $nb);
                        $data['id_bodega'] = $b ? (int)$b['id'] : null;
                    }

                    $this->normalizarNroDocumento($data);
                    $this->resolverClientePorId($data, $idEmpresa);
                    $this->resolverProductoConsignacion($data, $idEmpresa);
                    $this->resolverVendedorBodegaConsignacion($data, $idEmpresa);
                    $this->rules->validarConsignacion($data);
                    $this->repo->insertConsignacion($data);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = 'Fila ' . ($i + 2) . ': ' . $e->getMessage();
                }
            }
            $db->commit();
            return ['insertados' => $insertados, 'errores' => $errores];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function resolverProductoConsignacion(array &$data, int $idEmpresa): void
    {
        $idProducto = (int)($data['id_producto'] ?? 0);
        if ($idProducto <= 0) {
            throw new \RuntimeException('Debe seleccionar un producto registrado.');
        }
        $p = $this->repo->getProductoPorId($idEmpresa, $idProducto);
        if (!$p) {
            throw new \RuntimeException('El producto seleccionado no está registrado. Créelo primero en el módulo de Productos.');
        }
        $data['id_producto']     = (int)$p['id'];
        $data['producto_nombre'] = $p['nombre'];
        $data['producto_codigo'] = $p['codigo'];
    }

    private function resolverVendedorBodegaConsignacion(array &$data, int $idEmpresa): void
    {
        $idVend = (int)($data['id_vendedor'] ?? 0);
        $data['nombre_vendedor'] = $idVend > 0 ? $this->repo->getVendedorNombre($idEmpresa, $idVend) : null;
        if ($idVend > 0 && $data['nombre_vendedor'] === null) {
            $data['id_vendedor'] = null;
        }

        $idBod = (int)($data['id_bodega'] ?? 0);
        $data['nombre_bodega'] = $idBod > 0 ? $this->repo->getBodegaNombre($idEmpresa, $idBod) : null;
        if ($idBod > 0 && $data['nombre_bodega'] === null) {
            $data['id_bodega'] = null;
        }
    }

    public function guardarBancos(int $idEmpresa, int $idUsuario, array $cuentas): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            foreach ($cuentas as $cuenta) {
                $this->rules->validarBanco($cuenta);
                $this->repo->upsertBanco($idEmpresa, (int)$cuenta['id_forma_pago'], array_merge($cuenta, ['created_by' => $idUsuario]));
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminarBanco(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->deleteBanco($id, $idEmpresa, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────
    // COBROS / PAGOS
    // ─────────────────────────────────────────────────────────

    public function registrarCobroCxc(int $idSaldo, int $idEmpresa, int $idUsuario, array $datos): array
    {
        $saldo = $this->repo->getCxcPorId($idSaldo, $idEmpresa);
        if (!$saldo) {
            throw new \RuntimeException('Saldo inicial no encontrado.');
        }
        $saldoPendiente = (float)$saldo['saldo_pendiente'];
        $monto = (float)$datos['monto'];
        if ($monto <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a 0.');
        }
        if ($monto > $saldoPendiente + 0.001) {
            throw new \RuntimeException("El monto ($monto) supera el saldo pendiente ($saldoPendiente).");
        }

        $punto = $datos['punto'];
        $codEst = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $codPto = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);

        $secuencialService = new \App\Services\SecuencialService();
        $secRes     = $secuencialService->obtenerSiguienteSecuencial((int)$datos['id_punto_emision'], 'Ingresos');
        $secuencial = $secRes['formateado'];
        $numDoc     = "{$codEst}-{$codPto}-{$secuencial}";

        $payload = [
            'id_empresa'         => $idEmpresa,
            'id_establecimiento' => (int)($punto['id_establecimiento'] ?? 0),
            'id_punto_emision'   => (int)$datos['id_punto_emision'],
            'id_cliente'         => !empty($saldo['id_cliente']) ? (int)$saldo['id_cliente'] : null,
            'id_usuario'         => $idUsuario,
            'fecha_emision'      => $datos['fecha_cobro'] ?: date('Y-m-d'),
            'establecimiento'    => $codEst,
            'punto_emision'      => $codPto,
            'secuencial'         => $secuencial,
            'numero_ingreso'     => $numDoc,
            'tipo_ingreso'       => 'SALDO_INICIAL',
            'id_ingreso_concepto'=> !empty($datos['id_ingreso_concepto']) ? (int)$datos['id_ingreso_concepto'] : null,
            'monto_total'        => $monto,
            'observaciones'      => $datos['observaciones'] ?: "Cobro saldo inicial {$saldo['nro_documento']}",
            'recibo_de'          => $saldo['nombre_cliente'],
            'id_recibo_cliente'  => !empty($saldo['id_cliente']) ? (int)$saldo['id_cliente'] : null,
            'detalles' => [[
                'tipo_documento'         => 'SALDO_INICIAL',
                'id_referencia_documento'=> $idSaldo,
                'numero_documento'       => $saldo['nro_documento'],
                'descripcion'            => "Cobro saldo inicial {$saldo['nro_documento']} — {$saldo['nombre_cliente']}",
                'monto_documento'        => $saldo['saldo_inicial'],
                'saldo_anterior'         => $saldoPendiente,
                'monto_cobrado'          => $monto,
                'saldo_actual'           => max(0.0, $saldoPendiente - $monto),
            ]],
            'pagos' => [[
                'id_forma_cobro'         => (int)$datos['id_forma_cobro'],
                'monto'                  => $monto,
                'fecha_cobro'            => $datos['fecha_cobro'] ?: date('Y-m-d'),
                'observaciones'          => $datos['observaciones'] ?: null,
                'tipo_operacion_bancaria'=> $datos['tipo_operacion_bancaria'] ?? null,
                'numero_cheque'          => $datos['numero_operacion'] ?? null,
                'referencia'             => $datos['numero_operacion'] ?? null,
            ]],
        ];

        $ingresoService = new IngresoService(
            new \App\repositories\modulos\IngresoRepository(),
            new \App\Rules\modulos\IngresoRules(),
            new \App\Services\LogSistemaService()
        );
        $idIngreso = $ingresoService->crear($payload);

        $this->repo->actualizarMontoCobradoCxc($idSaldo, $idEmpresa);

        $saldoActualizado = $this->repo->getCxcPorId($idSaldo, $idEmpresa);

        return [
            'id_ingreso'     => $idIngreso,
            'numero_ingreso' => $numDoc,
            'nuevo_saldo'    => number_format((float)$saldoActualizado['saldo_pendiente'], 2, '.', ''),
            'pagado'         => (float)$saldoActualizado['saldo_pendiente'] <= 0.001,
        ];
    }

    public function registrarPagoCxp(int $idSaldo, int $idEmpresa, int $idUsuario, array $datos): array
    {
        $saldo = $this->repo->getCxpPorId($idSaldo, $idEmpresa);
        if (!$saldo) {
            throw new \RuntimeException('Saldo inicial no encontrado.');
        }
        $saldoPendiente = (float)$saldo['saldo_pendiente'];
        $monto = (float)$datos['monto'];
        if ($monto <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a 0.');
        }
        if ($monto > $saldoPendiente + 0.001) {
            throw new \RuntimeException("El monto ($monto) supera el saldo pendiente ($saldoPendiente).");
        }

        $punto = $datos['punto'];
        $codEst = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $codPto = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);

        $secuencialService = new \App\Services\SecuencialService();
        $secRes     = $secuencialService->obtenerSiguienteSecuencial((int)$datos['id_punto_emision'], 'Egresos');
        $secuencial = $secRes['formateado'];
        $numDoc     = "{$codEst}-{$codPto}-{$secuencial}";

        $tipoSujeto = !empty($saldo['id_proveedor']) ? 'PROVEEDOR' : 'OTRO';

        $payload = [
            'id_empresa'        => $idEmpresa,
            'id_punto_emision'  => (int)$datos['id_punto_emision'],
            'id_establecimiento'=> (int)($punto['id_establecimiento'] ?? 0),
            'fecha_emision'     => $datos['fecha_pago'] ?: date('Y-m-d'),
            'establecimiento'   => $codEst,
            'punto_emision'     => $codPto,
            'secuencial'        => $secuencial,
            'numero_egreso'     => $numDoc,
            'tipo_egreso'       => 'SALDO_INICIAL',
            'tipo_sujeto'       => $tipoSujeto,
            'id_proveedor'      => !empty($saldo['id_proveedor']) ? (int)$saldo['id_proveedor'] : null,
            'id_empleado'       => null,
            'id_egreso_concepto'=> !empty($datos['id_egreso_concepto']) ? (int)$datos['id_egreso_concepto'] : null,
            'monto_total'       => $monto,
            'observaciones'     => $datos['observaciones'] ?: "Pago saldo inicial {$saldo['nro_documento']}",
            'estado'            => 'registrado',
            'usuario_id'        => $idUsuario,
            'detalles' => [[
                'tipo_documento'         => 'SALDO_INICIAL',
                'id_referencia_documento'=> $idSaldo,
                'numero_documento'       => $saldo['nro_documento'],
                'descripcion'            => "Pago saldo inicial {$saldo['nro_documento']} — {$saldo['nombre_proveedor']}",
                'monto_documento'        => $saldo['saldo_inicial'],
                'saldo_anterior'         => $saldoPendiente,
                'monto_pagado'           => $monto,
                'saldo_actual'           => max(0.0, $saldoPendiente - $monto),
            ]],
            'pagos' => [[
                'id_forma_pago'          => (int)$datos['id_forma_pago'],
                'monto'                  => $monto,
                'referencia'             => $datos['numero_operacion'] ?? null,
                'tipo_operacion_bancaria'=> $datos['tipo_operacion_bancaria'] ?? null,
                'numero_cheque'          => $datos['numero_operacion'] ?? null,
            ]],
        ];

        $egresoService = new EgresoService(
            new \App\repositories\modulos\EgresoRepository(),
            new \App\Rules\modulos\EgresoRules(),
            new \App\Services\LogSistemaService()
        );
        $idEgreso = $egresoService->registrar($payload);

        $this->repo->actualizarMontoPagadoCxp($idSaldo, $idEmpresa);

        $saldoActualizado = $this->repo->getCxpPorId($idSaldo, $idEmpresa);

        return [
            'id_egreso'     => $idEgreso,
            'numero_egreso' => $numDoc,
            'nuevo_saldo'   => number_format((float)$saldoActualizado['saldo_pendiente'], 2, '.', ''),
            'pagado'        => (float)$saldoActualizado['saldo_pendiente'] <= 0.001,
        ];
    }
}
