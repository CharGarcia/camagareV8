<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\TransferenciaInventarioRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\TransferenciaInventarioRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Lógica de negocio de las transferencias de inventario.
 *
 * La transferencia es de UN SOLO PASO: dentro de una misma transacción se
 * registra la salida en la bodega origen y la entrada en la de destino (dos
 * filas de inventario_kardex con tipo_movimiento = 'transferencia', ambas
 * apuntando al documento con referencia_tipo = 'transferencia_inventario').
 *
 * Concurrencia (§8): antes de leer cualquier stock se toman TODOS los candados
 * (producto, bodega) que el documento va a tocar, en orden determinista, para
 * que dos transferencias simultáneas sobre las mismas existencias no se pisen
 * y para que A→B y B→A no se bloqueen mutuamente.
 *
 * No genera asiento contable: el inventario no cambia de valor ni de dueño,
 * solo de ubicación dentro de la misma empresa.
 */
class TransferenciaInventarioService
{
    public const REFERENCIA_TIPO = 'transferencia_inventario';

    private TransferenciaInventarioRepository $repo;
    private InventarioRepository $inventarioRepo;
    private TransferenciaInventarioRules $rules;
    private LogSistemaService $log;
    private ?InventarioService $inventarioService = null;

    public function __construct(
        TransferenciaInventarioRepository $repo,
        InventarioRepository $inventarioRepo,
        TransferenciaInventarioRules $rules,
        LogSistemaService $log
    ) {
        $this->repo           = $repo;
        $this->inventarioRepo = $inventarioRepo;
        $this->rules          = $rules;
        $this->log            = $log;
    }

    private function getInventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService($this->inventarioRepo, $this->log);
        }
        return $this->inventarioService;
    }

    private function getBodegaService(): BodegaService
    {
        return new BodegaService(
            new \App\repositories\modulos\BodegaRepository(),
            new \App\Rules\modulos\BodegaRules(),
            $this->log
        );
    }

    // ────────────────────────────────────────────────────────────────
    // LECTURA
    // ────────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, array $filtros = []): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $filtros);
    }

    public function getResumen(int $idEmpresa, ?int $idUsuarioFiltro = null, array $filtros = []): array
    {
        return $this->repo->getResumen($idEmpresa, $idUsuarioFiltro, $filtros);
    }

    /** Documento completo: cabecera + líneas. */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $cab = $this->repo->getPorId($id, $idEmpresa);
        if (!$cab) {
            return null;
        }
        $cab['detalles'] = $this->repo->getDetalle($id, $idEmpresa);
        return $cab;
    }

    // ────────────────────────────────────────────────────────────────
    // REGISTRO
    // ────────────────────────────────────────────────────────────────

    /**
     * Registra la transferencia y mueve el stock. Devuelve el id del documento.
     *
     * @param array $data id_empresa, id_usuario, nivel_usuario, fecha_transferencia,
     *                    id_bodega_origen, id_bodega_destino, responsable_envia,
     *                    responsable_recibe, observaciones, detalles[]
     */
    public function registrar(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $nivel     = (int) ($data['nivel_usuario'] ?? 1);
        $origen    = (int) $data['id_bodega_origen'];
        $destino   = (int) $data['id_bodega_destino'];

        // Acceso a bodegas: quien no puede operar la bodega tampoco puede
        // sacar ni meter mercadería en ella.
        $bodegaService = $this->getBodegaService();
        if (!$bodegaService->validarAccesoUsuario($idUsuario, $origen, $idEmpresa, $nivel)) {
            throw new Exception('No tiene acceso a la bodega de origen.');
        }
        if (!$bodegaService->validarAccesoUsuario($idUsuario, $destino, $idEmpresa, $nivel)) {
            throw new Exception('No tiene acceso a la bodega de destino.');
        }

        $bodegas = [];
        foreach ($this->repo->getBodegasConEstablecimiento($idEmpresa) as $b) {
            $bodegas[(int) $b['id']] = $b;
        }
        if (!isset($bodegas[$origen]) || !isset($bodegas[$destino])) {
            throw new Exception('La bodega de origen o la de destino no existe o está inactiva en esta empresa.');
        }

        $estOrigen  = $bodegas[$origen]['id_establecimiento']  !== null ? (int) $bodegas[$origen]['id_establecimiento']  : null;
        $estDestino = $bodegas[$destino]['id_establecimiento'] !== null ? (int) $bodegas[$destino]['id_establecimiento'] : null;
        $entreEst   = ($estOrigen !== null && $estDestino !== null && $estOrigen !== $estDestino);

        $fecha = $this->normalizarFecha((string) $data['fecha_transferencia']);

        $db = Database::getConnection();
        $manejaTransaccion = !$db->inTransaction();
        if ($manejaTransaccion) {
            $db->beginTransaction();
        }

        try {
            // 1) Candados de stock ANTES de leer nada (orden determinista para
            //    no generar interbloqueos entre transferencias cruzadas).
            $this->bloquearStocks($data['detalles'], $origen, $destino, $idEmpresa);

            // 2) Disponibilidad de TODAS las líneas antes de numerar: si algo no
            //    alcanza, el documento no llega a existir y el correlativo no se
            //    consume (importante cuando el llamador ya traía una transacción
            //    abierta y es él quien decide revertir).
            $this->validarDisponibilidad($data['detalles'], $origen, $idEmpresa, (string) $bodegas[$origen]['nombre']);

            // 3) Numeración correlativa (el candado del secuencial se libera al commit)
            $ambiente   = $this->repo->getTipoAmbiente($idEmpresa);
            $secuencial = $this->repo->siguienteSecuencial($idEmpresa, $ambiente);
            $numero     = 'TRF-' . str_pad((string) $secuencial, 6, '0', STR_PAD_LEFT);

            $idTransferencia = $this->repo->insertCabecera([
                'id_empresa'                 => $idEmpresa,
                'secuencial'                 => $secuencial,
                'numero'                     => $numero,
                'fecha_transferencia'        => $fecha,
                'id_bodega_origen'           => $origen,
                'id_bodega_destino'          => $destino,
                'id_establecimiento_origen'  => $estOrigen,
                'id_establecimiento_destino' => $estDestino,
                'entre_establecimientos'     => $entreEst,
                'responsable_envia'          => trim((string) ($data['responsable_envia'] ?? '')),
                'responsable_recibe'         => trim((string) ($data['responsable_recibe'] ?? '')),
                'observaciones'              => trim((string) ($data['observaciones'] ?? '')),
                'total_items'                => 0,
                'total_costo'                => 0,
                'estado'                     => 'registrada',
                'tipo_ambiente'              => $ambiente,
                'id_usuario'                 => $idUsuario,
            ]);

            // 4) Líneas: salida de origen + entrada en destino
            $totalItems = 0.0;
            $totalCosto = 0.0;

            foreach ($data['detalles'] as $i => $linea) {
                $resultado = $this->procesarLinea(
                    $linea,
                    (int) $i + 1,
                    $idTransferencia,
                    $numero,
                    $origen,
                    $destino,
                    (string) $bodegas[$origen]['nombre'],
                    (string) $bodegas[$destino]['nombre'],
                    $idEmpresa,
                    $idUsuario,
                    $fecha
                );
                $totalItems += $resultado['cantidad'];
                $totalCosto += $resultado['costo_total'];
            }

            $this->repo->actualizarTotales($idTransferencia, $idEmpresa, $totalItems, round($totalCosto, 2));

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR_TRANSFERENCIA_INVENTARIO',
                'transferencias_inventario_cabecera',
                $idTransferencia,
                null,
                [
                    'numero'                 => $numero,
                    'fecha_transferencia'    => $fecha,
                    'id_bodega_origen'       => $origen,
                    'id_bodega_destino'      => $destino,
                    'entre_establecimientos' => $entreEst,
                    'total_items'            => $totalItems,
                    'total_costo'            => round($totalCosto, 2),
                    'lineas'                 => count($data['detalles']),
                ]
            );

            if ($manejaTransaccion) {
                $db->commit();
            }
            return $idTransferencia;
        } catch (\Throwable $e) {
            if ($manejaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Toma el candado de cada par (producto, bodega) que el documento tocará,
     * ordenado siempre igual, ANTES de cualquier lectura de stock.
     */
    private function bloquearStocks(array $detalles, int $origen, int $destino, int $idEmpresa): void
    {
        $claves = [];
        foreach ($detalles as $l) {
            $idProducto = (int) ($l['id_producto'] ?? 0);
            if ($idProducto <= 0) {
                continue;
            }
            $claves[$idProducto . ':' . $origen]  = [$idProducto, $origen];
            $claves[$idProducto . ':' . $destino] = [$idProducto, $destino];
        }

        // Orden determinista: por producto y luego por bodega.
        usort($claves, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        foreach ($claves as [$idProducto, $idBodega]) {
            $this->inventarioRepo->lockStock($idProducto, $idBodega, $idEmpresa);
        }
    }

    /**
     * Comprueba que la bodega de origen alcanza para TODAS las líneas juntas,
     * sumando lo que pide cada una: dos líneas del mismo producto (o del mismo
     * lote) no pueden llevarse cada una el saldo completo.
     */
    private function validarDisponibilidad(array $detalles, int $origen, int $idEmpresa, string $nombreOrigen): void
    {
        $pedidoProducto = [];
        $pedidoLote     = [];
        $pedidoSerie    = [];

        foreach ($detalles as $i => $l) {
            $n          = (int) $i + 1;
            $idProducto = (int) $l['id_producto'];
            $cantidad   = round((float) $l['cantidad'], 6);
            $lote       = trim((string) ($l['numero_lote'] ?? ''));
            $nup        = trim((string) ($l['nup'] ?? ''));
            $nombre     = $this->nombreProducto($idProducto, $idEmpresa);

            $pedidoProducto[$idProducto] = ($pedidoProducto[$idProducto] ?? 0) + $cantidad;

            $stock = $this->inventarioRepo->getStockActual($idProducto, $origen, $idEmpresa);
            if ($pedidoProducto[$idProducto] > $stock + 0.000001) {
                throw new Exception("Línea {$n} ({$nombre}): stock insuficiente en «{$nombreOrigen}». Disponible: " . $this->fmt($stock) . ", solicitado en total: " . $this->fmt($pedidoProducto[$idProducto]) . '.');
            }

            if ($lote === '') {
                $lotesReales = array_filter(
                    $this->inventarioRepo->getLotesDisponibles($idProducto, $origen, $idEmpresa),
                    fn($x) => ($x['numero_lote'] ?? '') !== 'sin_lote'
                );
                if (!empty($lotesReales)) {
                    throw new Exception("Línea {$n} ({$nombre}): seleccione el lote del que sale la mercadería.");
                }
            } else {
                $clave = $idProducto . '|' . $lote;
                $pedidoLote[$clave] = ($pedidoLote[$clave] ?? 0) + $cantidad;
                $stockLote = $this->inventarioRepo->getStockLote($idProducto, $origen, $idEmpresa, $lote);
                if ($pedidoLote[$clave] > $stockLote + 0.000001) {
                    $etiqueta = $lote === 'sin_lote' ? 'sin lote' : "lote «{$lote}»";
                    throw new Exception("Línea {$n} ({$nombre}): stock insuficiente en el {$etiqueta} de «{$nombreOrigen}». Disponible: " . $this->fmt($stockLote) . '.');
                }
            }

            if ($nup !== '') {
                $claveSerie = $idProducto . '|' . mb_strtoupper($nup);
                $pedidoSerie[$claveSerie] = ($pedidoSerie[$claveSerie] ?? 0) + $cantidad;
                $stockSerie = $this->repo->getStockSerie($idProducto, $origen, $idEmpresa, $nup);
                if ($pedidoSerie[$claveSerie] > $stockSerie + 0.000001) {
                    throw new Exception("Línea {$n} ({$nombre}): la serie/NUP «{$nup}» no está disponible en «{$nombreOrigen}».");
                }
            }
        }
    }

    /**
     * Una línea = salida en origen + entrada en destino, con el mismo lote,
     * caducidad, serie y costo. El costo NUNCA viene del navegador: se toma del
     * historial de la bodega origen para que la transferencia no altere la
     * valoración del inventario.
     *
     * @return array{cantidad: float, costo_total: float}
     */
    private function procesarLinea(
        array $linea,
        int $numeroLinea,
        int $idTransferencia,
        string $numeroDocumento,
        int $origen,
        int $destino,
        string $nombreOrigen,
        string $nombreDestino,
        int $idEmpresa,
        int $idUsuario,
        string $fecha
    ): array {
        $idProducto = (int) $linea['id_producto'];
        $cantidad   = round((float) $linea['cantidad'], 6);
        $lote       = trim((string) ($linea['numero_lote'] ?? ''));
        $nup        = trim((string) ($linea['nup'] ?? ''));
        $caducidad  = trim((string) ($linea['fecha_caducidad'] ?? ''));
        $idMedida   = !empty($linea['id_medida']) ? (int) $linea['id_medida'] : null;
        $obsLinea   = trim((string) ($linea['observaciones'] ?? ''));

        $nombreProducto = $this->nombreProducto($idProducto, $idEmpresa);

        // ── Validación de existencias en origen (con el candado ya tomado) ──
        $stockOrigen = $this->inventarioRepo->getStockActual($idProducto, $origen, $idEmpresa);
        if ($cantidad > $stockOrigen + 0.000001) {
            throw new Exception("Línea {$numeroLinea} ({$nombreProducto}): stock insuficiente en «{$nombreOrigen}». Disponible: " . $this->fmt($stockOrigen) . ", solicitado: " . $this->fmt($cantidad) . '.');
        }

        // Si el producto maneja lotes en esa bodega, hay que decir de cuál sale:
        // descontar "sin lote" dejaría el saldo por lote descuadrado.
        if ($lote === '') {
            $lotesReales = array_filter(
                $this->inventarioRepo->getLotesDisponibles($idProducto, $origen, $idEmpresa),
                fn($l) => ($l['numero_lote'] ?? '') !== 'sin_lote'
            );
            if (!empty($lotesReales)) {
                throw new Exception("Línea {$numeroLinea} ({$nombreProducto}): seleccione el lote del que sale la mercadería.");
            }
        }

        if ($lote !== '') {
            $stockLote = $this->inventarioRepo->getStockLote($idProducto, $origen, $idEmpresa, $lote);
            if ($cantidad > $stockLote + 0.000001) {
                $etiqueta = $lote === 'sin_lote' ? 'sin lote' : "lote «{$lote}»";
                throw new Exception("Línea {$numeroLinea} ({$nombreProducto}): stock insuficiente en el {$etiqueta} de «{$nombreOrigen}». Disponible: " . $this->fmt($stockLote) . '.');
            }
        }

        if ($nup !== '') {
            $stockSerie = $this->repo->getStockSerie($idProducto, $origen, $idEmpresa, $nup);
            if ($stockSerie <= 0) {
                throw new Exception("Línea {$numeroLinea} ({$nombreProducto}): la serie/NUP «{$nup}» no está disponible en «{$nombreOrigen}».");
            }
        }

        // ── Costo con el que viaja la mercadería ──
        $costoUnitario = $this->repo->getCostoOrigen($idProducto, $origen, $idEmpresa, $lote !== '' ? $lote : null);
        $costoTotal    = round($cantidad * $costoUnitario, 2);

        $loteGuardar = ($lote !== '' && $lote !== 'sin_lote') ? $lote : null;

        // ── Salida de la bodega origen ──
        $stockOrigenPost = round($stockOrigen - $cantidad, 6);
        $idKardexSalida = $this->inventarioRepo->registrarMovimiento([
            'id_empresa'       => $idEmpresa,
            'id_producto'      => $idProducto,
            'id_bodega'        => $origen,
            'tipo_movimiento'  => 'transferencia',
            'referencia_tipo'  => self::REFERENCIA_TIPO,
            'referencia_id'    => $idTransferencia,
            'fecha_movimiento' => $fecha,
            'cantidad'         => -$cantidad,
            'costo_unitario'   => $costoUnitario,
            // costo_total va POSITIVO también en las salidas: es la convención
            // del kardex del sistema (ver InventarioService::registrarSalidaIndividual);
            // el signo del movimiento lo lleva `cantidad`.
            'costo_total'      => $costoTotal,
            'stock_anterior'   => $stockOrigen,
            'stock_posterior'  => $stockOrigenPost,
            'numero_lote'      => $loteGuardar,
            'fecha_caducidad'  => $caducidad !== '' ? $caducidad : null,
            'nup'              => $nup !== '' ? $nup : null,
            'id_medida'        => $idMedida,
            'observaciones'    => trim("Transferencia {$numeroDocumento} → {$nombreDestino}. {$obsLinea}"),
            'id_usuario'       => $idUsuario,
        ]);
        $this->inventarioRepo->actualizarStock($idProducto, $origen, $idEmpresa, $stockOrigenPost, $idUsuario);

        // ── Entrada en la bodega destino ──
        $stockDestino     = $this->inventarioRepo->getStockActual($idProducto, $destino, $idEmpresa);
        $stockDestinoPost = round($stockDestino + $cantidad, 6);
        $idKardexEntrada = $this->inventarioRepo->registrarMovimiento([
            'id_empresa'       => $idEmpresa,
            'id_producto'      => $idProducto,
            'id_bodega'        => $destino,
            'tipo_movimiento'  => 'transferencia',
            'referencia_tipo'  => self::REFERENCIA_TIPO,
            'referencia_id'    => $idTransferencia,
            'fecha_movimiento' => $fecha,
            'cantidad'         => $cantidad,
            'costo_unitario'   => $costoUnitario,
            'costo_total'      => $costoTotal,
            'stock_anterior'   => $stockDestino,
            'stock_posterior'  => $stockDestinoPost,
            'numero_lote'      => $loteGuardar,
            'fecha_caducidad'  => $caducidad !== '' ? $caducidad : null,
            'nup'              => $nup !== '' ? $nup : null,
            'id_medida'        => $idMedida,
            'observaciones'    => trim("Transferencia {$numeroDocumento} ← {$nombreOrigen}. {$obsLinea}"),
            'id_usuario'       => $idUsuario,
        ]);
        $this->inventarioRepo->actualizarStock($idProducto, $destino, $idEmpresa, $stockDestinoPost, $idUsuario);

        $this->repo->insertDetalle([
            'id_empresa'        => $idEmpresa,
            'id_transferencia'  => $idTransferencia,
            'id_producto'       => $idProducto,
            'id_medida'         => $idMedida,
            'cantidad'          => $cantidad,
            'costo_unitario'    => $costoUnitario,
            'costo_total'       => $costoTotal,
            'numero_lote'       => $loteGuardar,
            'fecha_caducidad'   => $caducidad !== '' ? $caducidad : null,
            'nup'               => $nup !== '' ? $nup : null,
            'observaciones'     => $obsLinea,
            'id_kardex_salida'  => $idKardexSalida,
            'id_kardex_entrada' => $idKardexEntrada,
            'id_usuario'        => $idUsuario,
        ]);

        return ['cantidad' => $cantidad, 'costo_total' => $costoTotal];
    }

    // ────────────────────────────────────────────────────────────────
    // ANULACIÓN
    // ────────────────────────────────────────────────────────────────

    /**
     * Anula la transferencia y deshace su efecto en el stock: los dos
     * movimientos de cada línea se marcan como anulados (mismo criterio que el
     * Kardex: no se crean movimientos de reverso) y el stock de ambas bodegas
     * se recalcula. Si la mercadería que entró al destino ya se consumió, la
     * anulación se rechaza en vez de dejar stock negativo.
     */
    public function anular(int $id, int $idEmpresa, int $idUsuario, int $nivel = 1): void
    {
        $cab = $this->repo->getPorId($id, $idEmpresa);
        if (!$cab) {
            throw new Exception('La transferencia no existe.');
        }
        if (($cab['estado'] ?? '') === 'anulada') {
            throw new Exception('La transferencia ya está anulada.');
        }

        $bodegaService = $this->getBodegaService();
        if (!$bodegaService->validarAccesoUsuario($idUsuario, (int) $cab['id_bodega_origen'], $idEmpresa, $nivel)
            || !$bodegaService->validarAccesoUsuario($idUsuario, (int) $cab['id_bodega_destino'], $idEmpresa, $nivel)) {
            throw new Exception('No tiene acceso a una de las bodegas de esta transferencia.');
        }

        $detalles = $this->repo->getDetalle($id, $idEmpresa);

        $db = Database::getConnection();
        $manejaTransaccion = !$db->inTransaction();
        if ($manejaTransaccion) {
            $db->beginTransaction();
        }

        try {
            $inventario = $this->getInventarioService();

            // Primero la entrada del destino (para que salte el error si ya se
            // consumió), después la salida del origen.
            foreach ($detalles as $d) {
                if (!empty($d['id_kardex_entrada'])) {
                    try {
                        $inventario->eliminarMovimiento((int) $d['id_kardex_entrada'], $idEmpresa, $idUsuario, true);
                    } catch (\Throwable $e) {
                        throw new Exception(
                            'No se puede anular: la mercadería que entró en la bodega de destino ya fue utilizada '
                            . '(' . $e->getMessage() . '). Reverse primero los documentos que la consumieron.'
                        );
                    }
                }
                if (!empty($d['id_kardex_salida'])) {
                    $inventario->eliminarMovimiento((int) $d['id_kardex_salida'], $idEmpresa, $idUsuario, true);
                }
            }

            if (!$this->repo->anular($id, $idEmpresa, $idUsuario)) {
                throw new Exception('No se pudo anular la transferencia.');
            }

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR_TRANSFERENCIA_INVENTARIO',
                'transferencias_inventario_cabecera',
                $id,
                ['estado' => $cab['estado'], 'numero' => $cab['numero']],
                ['estado' => 'anulada']
            );

            if ($manejaTransaccion) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($manejaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Eliminación lógica: solo se permite sobre una transferencia ya anulada,
     * porque el stock debe estar revertido antes de sacar el documento de la
     * lista.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->getPorId($id, $idEmpresa);
        if (!$cab) {
            throw new Exception('La transferencia no existe.');
        }
        if (($cab['estado'] ?? '') !== 'anulada') {
            throw new Exception('Solo se puede eliminar una transferencia anulada. Anúlela primero para devolver el stock a su bodega de origen.');
        }

        $db = Database::getConnection();
        $manejaTransaccion = !$db->inTransaction();
        if ($manejaTransaccion) {
            $db->beginTransaction();
        }

        try {
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR_TRANSFERENCIA_INVENTARIO',
                'transferencias_inventario_cabecera',
                $id,
                $cab,
                null
            );

            if ($manejaTransaccion) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($manejaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Vincula la guía de remisión emitida desde la transferencia. */
    public function vincularGuiaRemision(int $id, int $idEmpresa, ?int $idGuia): void
    {
        $this->repo->setGuiaRemision($id, $idEmpresa, $idGuia);
    }

    // ────────────────────────────────────────────────────────────────
    // AUXILIARES
    // ────────────────────────────────────────────────────────────────

    /** La fecha llega como 'Y-m-d' del formulario; se le agrega la hora actual. */
    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        if (strlen($fecha) <= 10) {
            return $fecha === date('Y-m-d')
                ? date('Y-m-d H:i:s')
                : $fecha . ' ' . date('H:i:s');
        }
        return $fecha;
    }

    private function nombreProducto(int $idProducto, int $idEmpresa): string
    {
        $db = Database::getConnection();
        $st = $db->prepare('SELECT nombre FROM productos WHERE id = :p AND id_empresa = :e');
        $st->execute([':p' => $idProducto, ':e' => $idEmpresa]);
        return (string) ($st->fetchColumn() ?: "producto #{$idProducto}");
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }
}
