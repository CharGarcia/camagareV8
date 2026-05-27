<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\InventarioRepository;
use App\Services\LogSistemaService;
use App\core\Database;

class InventarioService
{
    private InventarioRepository $repo;
    private LogSistemaService $log;
    private ?BodegaService $bodegaService = null;
    private ?\App\repositories\modulos\ProductoRepository $prodRepo = null;
    private ?\App\repositories\modulos\EmpresaRepository $empRepo = null;

    public function __construct(InventarioRepository $repo, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->log  = $log;
    }

    public function getRepository(): InventarioRepository
    {
        return $this->repo;
    }

    private function getBodegaService(): BodegaService
    {
        if ($this->bodegaService === null) {
            $this->bodegaService = new BodegaService(
                new \App\repositories\modulos\BodegaRepository(),
                new \App\Rules\modulos\BodegaRules(),
                $this->log
            );
        }
        return $this->bodegaService;
    }

    public function getProductoRepository(): \App\repositories\modulos\ProductoRepository
    {
        if ($this->prodRepo === null) {
            $this->prodRepo = new \App\repositories\modulos\ProductoRepository();
        }
        return $this->prodRepo;
    }

    private function getEmpresaRepository(): \App\repositories\modulos\EmpresaRepository
    {
        if ($this->empRepo === null) {
            $this->empRepo = new \App\repositories\modulos\EmpresaRepository();
        }
        return $this->empRepo;
    }

    // ────────────────────────────────────────────────────────────────
    // SALIDA POR FACTURA DE VENTA
    // ────────────────────────────────────────────────────────────────

    /**
     * Procesa salidas de inventario para todos los ítems de una factura.
     * Solo aplica a productos inventariables.
     * Devuelve array con id_inventario_kardex por posición de ítem.
     */
    public function procesarSalidaPorVenta(int $idVenta, array $detalles, int $idEstablecimiento, int $idEmpresa, int $idUsuario, string $obsPrefix = '', bool $esEdicion = false): array
    {
        $estConfig = $this->getEmpresaRepository()->getEstablecimientoConfig($idEstablecimiento);
        
        // Si no está activa la facturación con inventario, no hacemos nada
        if (!($estConfig['facturacion_inventario'] ?? false)) {
            return [];
        }

        $metodo          = $estConfig['metodo_costeo'] ?? 'promedio';
        $soloStockPos    = (bool)($estConfig['factura_solo_stock_positivo'] ?? false);
        $obliLotes       = (bool)($estConfig['obligatorio_lotes']   ?? false);
        $kardexIds        = [];
        
        $obsText = !empty($obsPrefix) ? $obsPrefix : "Factura #$idVenta";

        foreach ($detalles as $i => $d) {
            $idProducto = (int) ($d['id_producto'] ?? 0);
            $idBodega   = (int) ($d['id_bodega']   ?? 0);
            $cantidad   = abs((float) ($d['cantidad'] ?? 0));

            if (!$idProducto || !$idBodega || $cantidad <= 0) {
                $kardexIds[$i] = null;
                continue;
            }

            $kardexIds[$i] = [
                'principal'   => null,
                'componentes' => []
            ];

            // 1. Registrar salida del producto principal si es inventariable
            $prodData = $this->getProductoRepository()->getDetalleCompleto($idProducto, $idEmpresa);
            $isInventariable = $prodData && 
                               ($prodData['inventariable'] === true || $prodData['inventariable'] === 'true' || $prodData['inventariable'] == 1) &&
                               ($prodData['tipo_produccion'] !== '02');

            if ($isInventariable) {
                // Lógica de selección automática de lote si no es obligatorio y viene vacío
                $loteParaKardex = !empty($d['lote']) ? (string)$d['lote'] : null;
                $cadParaKardex  = !empty($d['caducidad']) ? (string)$d['caducidad'] : null;

                if (!$obliLotes && empty($loteParaKardex)) {
                    // Si soloStockPos es TRUE, buscamos solo con stock. Si es FALSE, buscamos el más antiguo histórico.
                    $loteAuto = $this->repo->getLoteMasAntiguo($idProducto, $idBodega, $idEmpresa, null, $soloStockPos);
                    if ($loteAuto) {
                        $loteParaKardex = (string)$loteAuto['numero_lote'];
                        $cadParaKardex  = (string)($loteAuto['fecha_caducidad'] ?? '');
                    } else {
                        // Fallback si no hay historial ni saldo
                        $loteParaKardex = 'SIN LOTE';
                        $cadParaKardex  = date('Y-m-d');
                    }
                } else {
                    // Si el lote fue manual, pero el NUP es opcional y está vacío, buscar el NUP más antiguo de ese lote específico
                    $obliNup = (bool)($estConfig['obligatorio_nup'] ?? false);
                    if (!$obliNup && empty($d['nup']) && !empty($loteParaKardex)) {
                         $nupAuto = $this->repo->getLoteMasAntiguo($idProducto, $idBodega, $idEmpresa, (string)$loteParaKardex, $soloStockPos);
                    }
                }

                if (empty($cadParaKardex)) {
                    $cadParaKardex = date('Y-m-d');
                } else {
                    $cadParaKardex = trim((string)$cadParaKardex);
                    if ($cadParaKardex === '') $cadParaKardex = date('Y-m-d');
                }

                $kardexIds[$i]['principal'] = $this->registrarSalidaIndividual(
                    $idProducto,
                    $idBodega,
                    $cantidad,
                    $metodo,
                    !$soloStockPos,
                    $idVenta,
                    $idEmpresa,
                    $idUsuario,
                    [
                        'lote'      => !empty($loteParaKardex) ? $loteParaKardex : null,
                        'caducidad' => $cadParaKardex,
                        'nup'       => !empty($d['nup']) ? $d['nup'] : null,
                        'id_medida' => !empty($d['id_medida']) ? (int)$d['id_medida'] : (!empty($prodData['id_medida']) ? (int)$prodData['id_medida'] : null),
                        'obs'       => "Salida por $obsText",
                        'exclude_id'   => $esEdicion ? $idVenta : null,
                        'exclude_tipo' => $esEdicion ? 'factura_venta' : null
                    ]
                );
            }

            // 2. Registrar salida de componentes si existen
            $componentes = $this->getProductoRepository()->getComponentes($idProducto, $idEmpresa);
            if (!empty($componentes)) {
                $nombrePadre = $this->getProductoRepository()->getNombre($idProducto);
                foreach ($componentes as $comp) {
                    $cantComp = $cantidad * (float)$comp['cantidad'];
                    // Para componentes, usualmente no se hereda el lote/nup del padre
                    $kardexIds[$i]['componentes'][] = $this->registrarSalidaIndividual(
                        (int)$comp['id_producto_hijo'],
                        $idBodega,
                        $cantComp,
                        $metodo,
                        !$soloStockPos,
                        $idVenta,
                        $idEmpresa,
                        $idUsuario,
                        [
                            'lote'      => null,
                            'caducidad' => null,
                            'nup'       => null,
                            'id_medida' => !empty($comp['id_medida']) ? (int)$comp['id_medida'] : null,
                            'componente_de' => $nombrePadre,
                            'obs'       => "Salida componente de '$nombrePadre' (#$idProducto) vía $obsText",
                            'exclude_id'   => $esEdicion ? $idVenta : null,
                            'exclude_tipo' => $esEdicion ? 'factura_venta' : null
                        ]
                    );
                }
            }
        }

        return $kardexIds;
    }

    /**
     * Helper para registrar una única salida en el kardex y actualizar stock.
     */
    private function registrarSalidaIndividual(int $idProducto, int $idBodega, float $cantidad, string $metodo, bool $facturacionLibre, int $idVenta, int $idEmpresa, int $idUsuario, array $extra): int
    {
        $excludeId   = $extra['exclude_id']   ?? null;
        $excludeTipo = $extra['exclude_tipo'] ?? null;
        $lote        = $extra['lote']         ?? null;

        $stockActual = $this->repo->getStockActual($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo, $lote);
        $stockPosterior = $stockActual - $cantidad;

        // Validación de stock negativo si la facturación no es libre
        if (!$facturacionLibre && $stockPosterior < 0) {
            $nombreProd = $this->getProductoRepository()->getNombre($idProducto);
            $msg = "Stock insuficiente para el producto '{$nombreProd}'. Disponible: {$stockActual}, Requerido: {$cantidad}.";
            
            if (!empty($extra['componente_de'])) {
                $msg = "Stock insuficiente para el componente '{$nombreProd}' (Combo: '{$extra['componente_de']}'). Disponible: {$stockActual}, Requerido: {$cantidad}.";
            }
            
            throw new \Exception($msg);
        }

        // Calcular costo de salida según método
        $costoUnitario = match ($metodo) {
            'fifo'    => $this->costoFIFO($idProducto, $idBodega, $idEmpresa),
            'lifo'    => $this->costoLIFO($idProducto, $idBodega, $idEmpresa),
            default   => $this->repo->getCostoPromedio($idProducto, $idBodega, $idEmpresa),
        };

        $kardexData = [
            'id_empresa'      => $idEmpresa,
            'id_producto'     => $idProducto,
            'id_bodega'       => $idBodega,
            'tipo_movimiento' => 'salida',
            'referencia_tipo' => 'factura_venta',
            'referencia_id'   => $idVenta,
            'cantidad'        => -$cantidad,
            'costo_unitario'  => $costoUnitario,
            'costo_total'     => round($costoUnitario * $cantidad, 2),
            'stock_anterior'  => $stockActual,
            'stock_posterior' => $stockPosterior,
            'numero_lote'     => !empty($extra['lote'])      ? $extra['lote']      : null,
            'fecha_caducidad' => !empty($extra['caducidad'])  ? $extra['caducidad'] : null,
            'nup'             => !empty($extra['nup'])       ? $extra['nup']       : null,
            'id_medida'       => !empty($extra['id_medida'])  ? (int)$extra['id_medida'] : null,
            'observaciones'   => $extra['obs']       ?? 'Salida por Factura',
            'id_usuario'      => $idUsuario,
        ];

        $idKardex = $this->repo->registrarMovimiento($kardexData);
        $this->repo->actualizarStock($idProducto, $idBodega, $idEmpresa, $stockPosterior, $idUsuario);

        return $idKardex;
    }

    // ────────────────────────────────────────────────────────────────
    // CÁLCULO DE COSTO SEGÚN MÉTODO
    // ────────────────────────────────────────────────────────────────

    private function costoFIFO(int $idProducto, int $idBodega, int $idEmpresa): float
    {
        $entradas = $this->repo->getEntradasFIFO($idProducto, $idBodega, $idEmpresa);
        return !empty($entradas) ? (float) $entradas[0]['costo_unitario'] : 0.0;
    }

    private function costoLIFO(int $idProducto, int $idBodega, int $idEmpresa): float
    {
        $entradas = $this->repo->getEntradasLIFO($idProducto, $idBodega, $idEmpresa);
        return !empty($entradas) ? (float) $entradas[0]['costo_unitario'] : 0.0;
    }

    // ────────────────────────────────────────────────────────────────
    // AJUSTE MANUAL
    // ────────────────────────────────────────────────────────────────

    public function ajusteManual(array $data, int $idEmpresa, int $idUsuario): int
    {
        $idProducto   = (int) $data['id_producto'];
        $idBodega     = (int) $data['id_bodega'];
        $tipo         = $data['tipo_movimiento']; // 'entrada' | 'salida' | 'ajuste'
        $isIndividual = (isset($data['is_individual']) && $data['is_individual'] == '1');
        $costoUnit    = (float) ($data['costo_unitario'] ?? 0);
        $totalQty     = abs((float) $data['cantidad']);

        $db = \App\core\Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            $nups = [$data['nup'] ?? null];
            $qtyPerMovement = $totalQty;

            // Si es individual, dividimos el proceso por cada serial
            if ($isIndividual && !empty($data['nup'])) {
                $nups = array_filter(array_map('trim', explode("\n", $data['nup'])));
                if (count($nups) > 0) {
                    $qtyPerMovement = 1.0;
                } else {
                    $nups = [$data['nup']]; // fallback
                }
            }

            // Validación de permisos de bodega (Nivel 1)
            $nivel = (int)($_SESSION['nivel'] ?? 1);
            if (!$this->getBodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \Exception("Acceso denegado. No tiene permisos para operar en esta bodega.");
            }

            // Validación de stock para salidas
            $stockActualTotal = $this->repo->getStockActual($idProducto, $idBodega, $idEmpresa);
            if ($tipo === 'salida') {
                if ($totalQty > $stockActualTotal) {
                    throw new \Exception("Stock insuficiente en bodega. No se puede registrar una salida de {$totalQty} si solo hay {$stockActualTotal} unidades disponibles.");
                }
                
                // Validación opcional por lote si se especifica uno
                if (!empty($data['numero_lote'])) {
                    $stockLote = $this->repo->getStockLote($idProducto, $idBodega, $idEmpresa, $data['numero_lote']);
                    if ($totalQty > $stockLote) {
                        throw new \Exception("Stock insuficiente en el lote '{$data['numero_lote']}'. Disponible: {$stockLote}.");
                    }
                }
            }

            $lastKardexId = 0;
            foreach ($nups as $nup) {
                // El ajuste manual sin signo explícito se trata como entrada, excepto 'salida'
                $finalQty = ($tipo === 'salida') ? -$qtyPerMovement : $qtyPerMovement;
                
                $stockActual  = $this->repo->getStockActual($idProducto, $idBodega, $idEmpresa);
                $stockPost    = max(0, $stockActual + $finalQty);

                $kardexData = [
                    'id_empresa'      => $idEmpresa,
                    'id_producto'     => $idProducto,
                    'id_bodega'       => $idBodega,
                    'tipo_movimiento' => $tipo,
                    'referencia_tipo' => $data['referencia_tipo'] ?? 'ajuste_manual',
                    'referencia_id'   => $data['referencia_id']   ?? null,
                    'cantidad'        => $finalQty,
                    'costo_unitario'  => $costoUnit,
                    'costo_total'     => round($qtyPerMovement * $costoUnit, 2),
                    'stock_anterior'  => $stockActual,
                    'stock_posterior' => $stockPost,
                    'numero_lote'     => !empty($data['numero_lote'])     ? $data['numero_lote']     : null,
                    'fecha_caducidad' => !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
                    'nup'             => !empty($nup)                     ? $nup                     : null,
                    'id_medida'       => !empty($data['id_medida'])        ? (int)$data['id_medida']  : null,
                    'observaciones'   => $data['observaciones']   ?? 'Ajuste manual',
                    'id_usuario'      => $idUsuario,
                ];

                $lastKardexId = $this->repo->registrarMovimiento($kardexData);
                $this->repo->actualizarStock($idProducto, $idBodega, $idEmpresa, $stockPost, $idUsuario);

                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    strtoupper($tipo),
                    'inventario_kardex',
                    $lastKardexId,
                    null,
                    $kardexData
                );
            }

            if ($managedTransaction) $db->commit();
            return $lastKardexId;

        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        return $this->repo->find($id, $idEmpresa);
    }

    public function eliminarMovimiento(int $id, int $idEmpresa, int $idUsuario, bool $ignorarRestriccion = false, ?int $idUsuarioNivel = null): void
    {
        $mov = $this->repo->find($id, $idEmpresa);
        if (!$mov) throw new \Exception("Movimiento no encontrado.");

        // Restricción: No eliminar movimientos vinculados a documentos (Facturas, etc.), excepto Superadmin
        $this->validarRestriccionMovimiento($mov, $ignorarRestriccion, $idUsuarioNivel);

        $db = \App\core\Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            // Revertir el stock: restamos el impacto original
            $stockActual = $this->repo->getStockActual((int)$mov['id_producto'], (int)$mov['id_bodega'], $idEmpresa);
            $nuevoStock  = $stockActual - (float)$mov['cantidad'];

            if ($nuevoStock < 0) {
                throw new \Exception("No se puede eliminar el movimiento porque el stock resultante sería negativo ({$nuevoStock}).");
            }

            // Actualizar stock en bodega
            $this->repo->actualizarStock((int)$mov['id_producto'], (int)$mov['id_bodega'], $idEmpresa, $nuevoStock, $idUsuario);

            // Eliminación lógica
            $sql = "UPDATE inventario_kardex 
                    SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                    WHERE id = :id";
            $st = $db->prepare($sql);
            $st->execute([':id' => $id, ':uid' => $idUsuario]);

            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR_MOV', 'inventario_kardex', $id, $mov, null);

            if ($managedTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Registra un ingreso de inventario por devolución via Nota de Crédito.
     * Si el producto no es inventariable, no hace nada (sin error).
     */
    public function registrarEntradaPorNC(array $data): void
    {
        $idProducto = (int) ($data['id_producto'] ?? 0);
        $idBodega   = (int) ($data['id_bodega']   ?? 0);
        $idEmpresa  = (int) ($data['id_empresa']  ?? 0);
        $cantidad   = abs((float) ($data['cantidad'] ?? 0));
        $idRef      = (int) ($data['id_referencia'] ?? 0);
        $idUsuario  = (int) ($data['id_usuario']  ?? 0);
        $descripcion = $data['descripcion'] ?? "Devolución por NC #{$idRef}";

        if (!$idProducto || !$idBodega || !$idEmpresa || $cantidad <= 0) return;

        // Verificar si el producto es inventariable
        $prodData = $this->getProductoRepository()->getDetalleCompleto($idProducto, $idEmpresa);
        if (!$prodData) return;
        $isInventariable = ($prodData['inventariable'] === true || $prodData['inventariable'] === 'true' || $prodData['inventariable'] == 1);
        if (!$isInventariable) return;

        $stockActual  = $this->repo->getStockActual($idProducto, $idBodega, $idEmpresa);
        $stockPost    = $stockActual + $cantidad;
        $costoUnit    = $this->repo->getCostoPromedio($idProducto, $idBodega, $idEmpresa);

        $kardexData = [
            'id_empresa'      => $idEmpresa,
            'id_producto'     => $idProducto,
            'id_bodega'       => $idBodega,
            'tipo_movimiento' => 'entrada',
            'referencia_tipo' => 'nota_credito',
            'referencia_id'   => $idRef,
            'cantidad'        => $cantidad,
            'costo_unitario'  => $costoUnit,
            'costo_total'     => round($costoUnit * $cantidad, 2),
            'stock_anterior'  => $stockActual,
            'stock_posterior' => $stockPost,
            'numero_lote'     => null,
            'fecha_caducidad' => null,
            'nup'             => null,
            'id_medida'       => !empty($prodData['id_medida']) ? (int)$prodData['id_medida'] : null,
            'observaciones'   => $descripcion,
            'id_usuario'      => $idUsuario,
        ];

        $this->repo->registrarMovimiento($kardexData);
        $this->repo->actualizarStock($idProducto, $idBodega, $idEmpresa, $stockPost, $idUsuario);
    }

    public function revertirMovimientosPorReferencia(string $tipoRef, int $idRef, int $idEmpresa, int $idUsuario): void
    {
        $sql = "SELECT id FROM inventario_kardex 
                WHERE referencia_tipo = :tipo AND referencia_id = :id 
                  AND id_empresa = :e AND eliminado = false";
        $db = \App\core\Database::getConnection();
        $st = $db->prepare($sql);
        $st->execute([':tipo' => $tipoRef, ':id' => $idRef, ':e' => $idEmpresa]);
        $movs = $st->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($movs as $m) {
            $this->eliminarMovimiento((int)$m['id'], $idEmpresa, $idUsuario, true);
        }
    }

    public function actualizarMovimiento(int $id, array $data, int $idEmpresa, int $idUsuario, ?int $idUsuarioNivel = null): void
    {
        $movOld = $this->repo->find($id, $idEmpresa);
        if (!$movOld) throw new \Exception("Movimiento no encontrado para actualizar.");

        // Restricción: No editar movimientos vinculados a documentos (Facturas, etc.), excepto Superadmin
        $this->validarRestriccionMovimiento($movOld, false, $idUsuarioNivel);

        $db = \App\core\Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) $db->beginTransaction();

        try {
            // Validación de permisos de bodega (Nivel 1)
            $idBodega = (int)$data['id_bodega'];
            $nivel = (int)($_SESSION['nivel'] ?? 1);
            if (!$this->getBodegaService()->validarAccesoUsuario($idUsuario, $idBodega, $idEmpresa, $nivel)) {
                throw new \Exception("Acceso denegado. No tiene permisos para operar en la bodega de destino.");
            }

            // 1. Revertir impacto anterior
            $stockActual = $this->repo->getStockActual((int)$movOld['id_producto'], (int)$movOld['id_bodega'], $idEmpresa);
            $stockBase   = $stockActual - (float)$movOld['cantidad'];

            // 2. Calcular nuevo impacto
            $tipo      = $data['tipo_movimiento'];
            $newQtyRaw = abs((float)$data['cantidad']);
            $finalQty  = ($tipo === 'salida') ? -$newQtyRaw : $newQtyRaw;
            
            $stockPost = $stockBase + $finalQty;

            if ($stockPost < 0) {
                throw new \Exception("La actualización dejaría el stock de la bodega en negativo ({$stockPost}). Disponible sin este movimiento: {$stockBase}.");
            }

            // 2.1 Validación por lote si es salida
            if ($tipo === 'salida' && !empty($data['numero_lote'])) {
                $stockLoteActual = $this->repo->getStockLote((int)$data['id_producto'], (int)$data['id_bodega'], $idEmpresa, $data['numero_lote']);
                
                // Stock base del lote sin el movimiento anterior (si el anterior era del mismo lote)
                $stockLoteBase = $stockLoteActual;
                if ($movOld['tipo_movimiento'] === 'salida' && $movOld['numero_lote'] === $data['numero_lote']) {
                    $stockLoteBase += abs((float)$movOld['cantidad']);
                } elseif ($movOld['tipo_movimiento'] === 'entrada' && $movOld['numero_lote'] === $data['numero_lote']) {
                    $stockLoteBase -= abs((float)$movOld['cantidad']);
                }

                if ($newQtyRaw > $stockLoteBase) {
                    throw new \Exception("Stock insuficiente en el lote '{$data['numero_lote']}'. Disponible: {$stockLoteBase}.");
                }
            }

            // 3. Actualizar registro
            $sql = "UPDATE inventario_kardex SET
                        id_producto = :prod, id_bodega = :bod, tipo_movimiento = :tipo,
                        cantidad = :cant, costo_unitario = :costo_u, costo_total = :costo_t,
                        stock_anterior = :stock_ant, stock_posterior = :stock_post,
                        numero_lote = :lote, fecha_caducidad = :cad, nup = :nup,
                        id_medida = :id_m,
                        observaciones = :obs, updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                    WHERE id = :id AND id_empresa = :e";
            
            $st = $db->prepare($sql);
            $st->execute([
                ':prod'       => $data['id_producto'],
                ':bod'        => $data['id_bodega'],
                ':tipo'       => $tipo,
                ':cant'       => $finalQty,
                ':costo_u'    => $data['costo_unitario']  ?? 0,
                ':costo_t'    => round($newQtyRaw * ($data['costo_unitario'] ?? 0), 2),
                ':stock_ant'  => $stockBase,
                ':stock_post' => $stockPost,
                ':lote'       => !empty($data['numero_lote'])     ? $data['numero_lote']     : null,
                ':cad'        => !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
                ':nup'        => !empty($data['nup'])             ? $data['nup']             : null,
                ':id_m'       => !empty($data['id_medida'])        ? (int)$data['id_medida']  : null,
                ':obs'        => $data['observaciones']   ?? 'Actualización manual',
                ':uid'        => $idUsuario,
                ':id'         => $id,
                ':e'          => $idEmpresa
            ]);

            // 4. Actualizar stock global
            $this->repo->actualizarStock((int)$data['id_producto'], (int)$data['id_bodega'], $idEmpresa, $stockPost, $idUsuario);

            $this->log->registrar($idUsuario, $idEmpresa, 'EDITAR_MOV', 'inventario_kardex', $id, $movOld, $data);

            if ($managedTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ────────────────────────────────────────────────────────────────
    // CONSULTAS PARA VISTAS
    // ────────────────────────────────────────────────────────────────

    public function getKardex(int $idEmpresa, array $filtros, int $page, int $perPage): array
    {
        return $this->repo->getKardex($idEmpresa, $filtros, $page, $perPage);
    }

    public function getStockResumen(int $idEmpresa, array $filtros = [], int $page = 1, int $perPage = 20): array
    {
        return $this->repo->getStockResumen($idEmpresa, $filtros, $page, $perPage);
    }

    public function getResumenEstadistico(int $idEmpresa): array
    {
        return $this->repo->getResumenEstadistico($idEmpresa);
    }

    /**
     * Valida si un movimiento puede ser gestionado manualmente.
     * Los movimientos vinculados a documentos externos (Facturas, Compras) están bloqueados.
     * Bypass: 
     *  1. Si ignorarRestriccion es true (procesos automáticos del sistema).
     *  2. Si el usuario es nivel 3 (Superadmin).
     *  3. Si el documento de referencia (ej. factura) ya ha sido eliminado.
     */
    private function validarRestriccionMovimiento(array $mov, bool $ignorarRestriccion = false, ?int $nivelUsuario = null): void
    {
        if ($ignorarRestriccion === true) return;

        $tipo = $mov['referencia_tipo'] ?? null;
        $idRef = (int)($mov['referencia_id'] ?? 0);

        if (!empty($tipo) && $tipo !== 'ajuste_manual') {
            // Caso especial: Si el documento de origen ya está eliminado, permitimos la limpieza manual del inventario
            $tablaRef = match ($tipo) {
                'factura_venta', 'nota_debito' => 'ventas_cabecera',
                'compra', 'nota_credito_compra' => 'compras_cabecera',
                'nota_credito' => 'ventas_notas_credito',
                'ajuste_inventario' => 'inventario_ajustes',
                default => null
            };

            if ($tablaRef && $idRef > 0) {
                try {
                    $db = \App\core\Database::getConnection();
                    $sqlCheck = "SELECT eliminado FROM {$tablaRef} WHERE id = ? AND id_empresa = ?";
                    $stCheck = $db->prepare($sqlCheck);
                    $stCheck->execute([$idRef, (int)$mov['id_empresa']]);
                    if ((bool)$stCheck->fetchColumn() === true) {
                        return; // El origen está eliminado, permitimos procesar el movimiento en Kardex
                    }
                } catch (\Throwable $e) {
                    // Si falla la consulta a la tabla de referencia (ej. tabla no existe), seguimos con la restricción por seguridad
                }
            }

            $nombreRef = ucwords(str_replace('_', ' ', $tipo));
            throw new \Exception("Este movimiento está vinculado a [{$nombreRef} #{$mov['referencia_id']}]. No puede ser modificado manualmente desde el Kardex. Por favor, gestione el documento original.");
        }
    }
}
