<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\IvaSubtotal;
use App\repositories\modulos\OrdenCarWashRepository;
use App\Rules\modulos\OrdenCarWashRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;

/**
 * Lógica de negocio del módulo Servicio Car-Wash.
 *
 * Una orden registra el ingreso de un vehículo, sus servicios/productos, las
 * novedades encontradas y la próxima cita. La orden descarga inventario al guardarse
 * (bodega de la cabecera); al emitir el documento de venta (Factura o Recibo) desde
 * generarDocumento() esa salida se devuelve y la hace el documento. El asiento contable
 * lo genera el documento, nunca la orden.
 */
class OrdenCarWashService
{
    private OrdenCarWashRepository $repository;
    private OrdenCarWashRules $rules;
    private LogSistemaService $logService;
    private ?InventarioService $inventarioService = null;

    /** Tipo de referencia en el kardex para los movimientos de la orden. */
    private const REF_TIPO = 'carwash_orden';

    public function __construct(
        OrdenCarWashRepository $repository,
        OrdenCarWashRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    private function getInventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
        }
        return $this->inventarioService;
    }

    /**
     * Líneas de la orden en el formato que espera el motor de inventario.
     * Solo productos del catálogo (los servicios libres no mueven stock);
     * el propio motor ignora los no inventariables y los de tipo servicio.
     */
    private function detallesParaInventario(array $detalles, int $idBodega): array
    {
        $out = [];
        foreach ($detalles as $d) {
            $cant = (float) ($d['cantidad'] ?? 0);
            $idProd = (int) ($d['id_producto'] ?? 0);
            if ($cant <= 0 || $idProd <= 0) continue;
            $out[] = [
                'id_producto' => $idProd,
                'id_bodega'   => $idBodega ?: (int) ($d['id_bodega'] ?? 0), // la bodega de la cabecera aplica a toda la orden
                'cantidad'    => $cant,
                'nombre'      => $d['descripcion'] ?? '',
                // Mismas claves que la factura: el motor descuenta del lote elegido y convierte
                // la cantidad según la unidad de la línea (venta por caja, etc.).
                'lote'             => $d['lote'] ?? null,
                'caducidad'        => $d['caducidad'] ?? ($d['fecha_caducidad'] ?? null),
                'nup'              => $d['nup'] ?? null,
                'id_unidad_medida' => !empty($d['id_unidad_medida']) ? (int) $d['id_unidad_medida'] : null,
            ];
        }
        return $out;
    }

    /**
     * Registra la SALIDA de inventario de la orden (los productos se consumen al
     * realizar el servicio). Respeta la config de la empresa: si el establecimiento
     * no trabaja con inventario, el motor no hace nada; si exige stock positivo,
     * lanza excepción cuando no alcanza.
     */
    private function aplicarSalidaInventario(int $idOrden, int $idEmpresa, int $idUsuario, array $detalles, int $idEstablecimiento, int $idBodega, string $numeroOrden, bool $esEdicion): void
    {
        if ($idEstablecimiento <= 0) return;
        $lineas = $this->detallesParaInventario($detalles, $idBodega);
        if (empty($lineas)) return;

        $this->getInventarioService()->procesarSalidaPorVenta(
            $idOrden, $lineas, $idEstablecimiento, $idEmpresa, $idUsuario,
            'Orden Car-Wash # ' . $numeroOrden, $esEdicion, self::REF_TIPO
        );
    }

    /** Revierte (devuelve al stock) los movimientos de inventario de la orden. */
    private function revertirInventario(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $idOrden, $idEmpresa, $idUsuario);
    }

    /**
     * La bodega es OBLIGATORIA en las líneas de productos que descuentan inventario
     * (inventariables y no-servicio). Los servicios e ítems libres no la requieren.
     * Si el establecimiento no trabaja con inventario, no se exige nada.
     */
    private function validarBodegasLineas(array $detalles, int $idBodegaDefault, int $idEmpresa, int $idEstablecimiento): void
    {
        if ($idEstablecimiento <= 0) return;
        try {
            $estConfig = (new \App\repositories\modulos\EmpresaRepository())->getEstablecimientoConfig($idEstablecimiento);
        } catch (\Throwable $e) {
            return;
        }
        // Se guarda como texto 'true'/'false': sin Booleano::es(), 'false' contaba como activo.
        if (!\App\Helpers\Booleano::es($estConfig['facturacion_inventario'] ?? false)) return;

        $prodRepo = new \App\repositories\modulos\ProductoRepository();
        foreach ($detalles as $d) {
            $idProd = (int) ($d['id_producto'] ?? 0);
            $cant   = (float) ($d['cantidad'] ?? 0);
            if ($idProd <= 0 || $cant <= 0) continue;

            $bodega = $idBodegaDefault ?: (int) ($d['id_bodega'] ?? 0);
            if ($bodega > 0) continue;

            $info  = $prodRepo->getInfoControlInventario($idProd, $idEmpresa);
            $esInv = !empty($info['inventariable']) && (($info['tipo_produccion'] ?? '01') !== '02');
            if ($esInv) {
                throw new Exception('Seleccione la bodega para el producto "' . trim((string) ($d['descripcion'] ?? '')) . '".');
            }
        }
    }

    /**
     * Aplica la configuración de facturación del establecimiento de la orden (ítems libres,
     * lote, caducidad y NUP obligatorios), con las mismas reglas que Factura de Venta.
     */
    private function validarConfiguracionFacturacion(array $detalles, int $idEmpresa, int $idEstablecimiento): void
    {
        $estConfig = $this->configEstablecimiento($idEstablecimiento);
        if (!$estConfig) return;
        $prodRepo = new \App\repositories\modulos\ProductoRepository();
        foreach ($detalles as &$d) {
            if (!empty($d['id_producto'])) {
                $info = $prodRepo->getInfoControlInventario((int) $d['id_producto'], $idEmpresa);
                $d['inventariable']   = $info['inventariable'] ?? false;
                $d['tipo_produccion'] = $info['tipo_produccion'] ?? '';
            }
        }
        unset($d);
        $this->rules->validarConfiguracionFacturacion($detalles, $estConfig);
    }

    // ─── Lecturas ─────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Búsqueda libre dentro de las órdenes (servicios/productos y novedades) — pestaña Detalles del buscador. */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        return $this->repository->buscarEnDetalles($idEmpresa, $q, $idUsuario, $limit);
    }

    public function getTablero(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getTablero($idEmpresa, $idUsuarioFiltro);
    }

    public function buscarVehiculos(int $idEmpresa, string $q): array
    {
        return $this->repository->buscarVehiculos($idEmpresa, $q);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) return null;
        $cab['detalles']  = $this->repository->getDetalles($id, $idEmpresa);
        $cab['novedades'] = $this->repository->getNovedades($id, $idEmpresa);
        $cab['info_adicional'] = $this->decodeInfoAdicional($cab['info_adicional'] ?? null);
        $cab['documentos'] = $this->repository->getDocumentos($id, $idEmpresa);
        $cab['documento_vigente'] = self::aBool($cab['documento_vigente'] ?? null);
        $cab['cliente_activo'] = self::aBool($cab['cliente_activo'] ?? null) === true;
        $cab['puede_facturar'] = $this->puedeFacturar($cab);
        $cab['editable'] = $this->esEditable($cab);
        return $cab;
    }

    /**
     * Productos SIMILARES con saldo en la bodega de la orden, para ofrecerlos cuando el
     * producto elegido no tiene stock. Candidatos (sin repetir, sin el propio producto):
     *  1. misma categoría, 2. misma marca, 3. que compartan las palabras principales del nombre.
     * Se quedan solo los que controlan inventario y tienen saldo > 0 en esa bodega, ordenados
     * por parecido (categoría + marca + palabras en común) y luego por saldo.
     *
     * @return array filas con el mismo formato del buscador de productos + stock_actual,
     *               controla_stock y `coincide` (por qué se sugiere).
     */
    public function productosSimilares(int $idProducto, int $idEmpresa, int $idBodega, ?int $idOrden, int $limite = 8): array
    {
        $base = $this->repository->getProductoBasico($idProducto, $idEmpresa);
        if (!$base || $idBodega <= 0) return [];

        $prodRepo = new \App\repositories\modulos\ProductoRepository();
        $invRepo  = new \App\repositories\modulos\InventarioRepository();
        $buscar = fn(string $q) => $prodRepo->getListado($idEmpresa, $q, 1, 40, 'nombre', 'ASC', null, 'venta', true)['rows'] ?? [];

        // Palabras principales del nombre (sin números ni palabras cortas).
        $norm = fn(string $s) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s)));
        $palabras = array_values(array_filter(
            explode(' ', preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $norm((string) $base['nombre']))),
            fn($w) => mb_strlen($w) >= 4 && !is_numeric($w)
        ));

        $cand = [];
        $agregar = function (array $rows) use (&$cand, $idProducto) {
            foreach ($rows as $r) {
                if ((int) $r['id'] !== $idProducto && !isset($cand[(int) $r['id']])) $cand[(int) $r['id']] = $r;
            }
        };
        if (!empty($base['id_categoria'])) $agregar($buscar('id_categoria:' . (int) $base['id_categoria']));
        if (!empty($base['id_marca']))     $agregar($buscar('id_marca:' . (int) $base['id_marca']));
        foreach (array_slice($palabras, 0, 2) as $w) $agregar($buscar($w));

        // Solo los que controlan inventario; su saldo en UNA consulta.
        $cand = array_filter($cand, fn($p) => ($p['inventariable'] === true || $p['inventariable'] === 't' || $p['inventariable'] === 'true' || $p['inventariable'] == 1)
                                              && (($p['tipo_produccion'] ?? '01') !== '02'));
        $stocks = $invRepo->getStockActualPorProductos(array_keys($cand), $idBodega, $idEmpresa, $idOrden ?: null, $idOrden ? self::REF_TIPO : null);

        $out = [];
        foreach ($cand as $p) {
            $stock = (float) ($stocks[(int) $p['id']] ?? 0);
            if ($stock <= 0) continue;

            $motivos = [];
            $puntaje = 0;
            if (!empty($base['id_categoria']) && (int) ($p['id_categoria'] ?? 0) === (int) $base['id_categoria']) { $puntaje += 3; $motivos[] = 'categoría'; }
            if (!empty($base['id_marca']) && (int) ($p['id_marca'] ?? 0) === (int) $base['id_marca'])             { $puntaje += 2; $motivos[] = 'marca'; }
            $nombreP = $norm((string) $p['nombre']);
            $comunes = count(array_filter($palabras, fn($w) => str_contains($nombreP, $w)));
            if ($comunes > 0) { $puntaje += $comunes; $motivos[] = 'nombre'; }

            $p['stock_actual']   = $stock;
            $p['controla_stock'] = true;
            $p['coincide']       = implode(', ', $motivos);
            $p['_puntaje']       = $puntaje;
            $out[] = $p;
        }
        usort($out, fn($a, $b) => [$b['_puntaje'], $b['stock_actual']] <=> [$a['_puntaje'], $a['stock_actual']]);
        return array_map(function ($p) { unset($p['_puntaje']); return $p; }, array_slice($out, 0, $limite));
    }

    /** Historial de órdenes por vehículo o por cliente (pestaña Historial del modal). */
    public function getHistorial(int $idEmpresa, string $modo, string $q, ?int $idVehiculo, ?int $idCliente, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getHistorial($idEmpresa, $modo === 'cliente' ? 'cliente' : 'vehiculo', $q, $idVehiculo, $idCliente, $idUsuarioFiltro);
    }

    /** Solo se puede asignar (y facturar a) un cliente activo y no eliminado de la empresa. */
    private function validarClienteActivo(int $idCliente, int $idEmpresa): void
    {
        if (!$this->repository->clienteActivo($idCliente, $idEmpresa)) {
            throw new Exception('El cliente seleccionado está inactivo o eliminado. Solo se puede facturar a clientes activos: actívelo en Clientes o elija otro.');
        }
    }

    private static function aBool($v): ?bool
    {
        if ($v === null) return null;
        return $v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1';
    }

    /**
     * Una orden facturada queda bloqueada mientras su documento siga vigente. Si la factura o el
     * recibo se ANULÓ o ELIMINÓ en su módulo, la orden se libera: se puede corregir y volver a
     * facturar (el documento anterior queda en el historial de Facturación).
     * Las órdenes migradas del sistema anterior pueden estar facturadas sin documento enlazado
     * (el documento no existe en el sistema nuevo): siguen bloqueadas.
     */
    private function documentoLiberado(array $cab): bool
    {
        return !empty($cab['id_documento']) && self::aBool($cab['documento_vigente'] ?? null) === false;
    }

    private function esEditable(array $cab): bool
    {
        if (($cab['estado'] ?? '') === 'anulado') return false;
        if ($this->documentoLiberado($cab)) return true;
        return empty($cab['id_documento']) && ($cab['estado'] ?? 'borrador') !== 'facturado';
    }

    private function puedeFacturar(array $cab): bool
    {
        return $this->esEditable($cab);
    }

    // ─── Crear ────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validarCreacion($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        if (!empty($data['id_cliente'])) {
            $this->validarClienteActivo((int) $data['id_cliente'], $idEmpresa);
        }

        // Ambiente vigente de la EMPRESA, leído de la base. Antes salía de empresa_config, que
        // store() nunca envía: toda orden quedaba en pruebas ('1') aunque la empresa estuviera
        // en producción.
        $tipoAmbiente  = $this->repository->ambienteEmpresa($idEmpresa);
        $idEstab       = (int) ($data['id_establecimiento'] ?? 0);
        $idPunto       = (int) ($data['id_punto_emision'] ?? 0);
        $secuencial    = str_pad((string) $data['secuencial'], 9, '0', STR_PAD_LEFT);

        if ($this->repository->existeSecuencial($idEmpresa, $idEstab, $idPunto, $secuencial, $tipoAmbiente)) {
            throw new \Exception('El secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }

        $numeroOrden = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . $secuencial;

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $cabecera = [
                'id_empresa'        => $idEmpresa,
                'id_establecimiento'=> $idEstab ?: null,
                'id_punto_emision'  => $idPunto ?: null,
                'establecimiento'   => $data['establecimiento'] ?? null,
                'punto_emision'     => $data['punto_emision'] ?? null,
                'secuencial'        => $secuencial,
                'tipo_ambiente'     => $tipoAmbiente,
                'numero_orden'      => $numeroOrden,
                'id_vehiculo'       => (int) $data['id_vehiculo'],
                'id_cliente'        => empty($data['id_cliente']) ? null : (int) $data['id_cliente'],
                'id_bodega'         => empty($data['id_bodega']) ? null : (int) $data['id_bodega'],
                'placa'             => $data['placa'] ?? null,
                'marca'             => $data['marca'] ?? null,
                'modelo'            => $data['modelo'] ?? null,
                'kilometraje'       => ($data['kilometraje'] ?? '') === '' ? null : (int) $data['kilometraje'],
                'nivel_combustible' => $data['nivel_combustible'] ?? null,
                'fecha_ingreso'     => $data['fecha_ingreso'],
                'novedades_texto'   => $data['novedades_texto'] ?? null,
                'observaciones'     => $data['observaciones'] ?? null,
                'info_adicional'    => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'proxima_cita'      => empty($data['proxima_cita']) ? null : $data['proxima_cita'],
                'estado'            => 'borrador',
                'subtotal'          => 0,
                'descuento'         => 0,
                'iva'               => 0,
                'total'             => 0,
                'created_by'        => $idUsuario,
                'updated_by'        => $idUsuario,
            ];
            if ($this->repository->tieneColumnaCondiciones()) {
                // Mismo saneamiento que las Condiciones de la Proforma (solo etiquetas de formato).
                $cabecera['condiciones_html'] = \App\Rules\modulos\ProformaRules::sanitizarCondiciones($data['condiciones_html'] ?? null);
            }
            $idOrden = $this->repository->create($cabecera);

            $tot = $this->guardarLineas($idOrden, $idEmpresa, $data, $this->modoIvaEstablecimiento($idEstab));

            $this->repository->updateTotales($idOrden, $idEmpresa, $tot['subtotal'], $tot['descuento'], $tot['iva'], $tot['total']);
            $cabecera = array_merge($cabecera, $tot);

            // Salida de inventario: los productos se consumen al realizar el servicio.
            // Valida bodega por línea y stock según la config (rollback si algo falla).
            $this->validarBodegasLineas($data['detalles'], (int) ($data['id_bodega'] ?? 0), $idEmpresa, $idEstab);
            $this->validarConfiguracionFacturacion($data['detalles'], $idEmpresa, $idEstab);
            $this->aplicarSalidaInventario(
                $idOrden, $idEmpresa, $idUsuario, $data['detalles'],
                $idEstab, (int) ($data['id_bodega'] ?? 0), $numeroOrden, false
            );

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_ORDEN_CARWASH', 'carwash_ordenes', $idOrden, null, $cabecera);

            $db->commit();
            return $idOrden;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Actualizar ───────────────────────────────────────────────────────────

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarCreacion($data);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        if (($cab['estado'] ?? '') === 'anulado') {
            throw new Exception("No se puede editar una orden anulada.");
        }
        if (!$this->esEditable($cab)) {
            throw new Exception("No se puede editar una orden que ya generó un documento vigente ("
                . ($cab['numero_documento'] ?? '') . "). Anule primero el documento.");
        }
        // Solo se exige al CAMBIAR de cliente: una orden cuyo cliente se desactivó después
        // puede seguir editándose (lo que no puede es facturarse, ver generarDocumento).
        if (!empty($data['id_cliente']) && (int) $data['id_cliente'] !== (int) ($cab['id_cliente'] ?? 0)) {
            $this->validarClienteActivo((int) $data['id_cliente'], $idEmpresa);
        }

        $idUsuario = (int) $data['id_usuario'];
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->limpiarLineas($id, $idEmpresa);
            $tot = $this->guardarLineas($id, $idEmpresa, $data, $this->modoIvaEstablecimiento((int) ($data['id_establecimiento'] ?? $cab['id_establecimiento'] ?? 0)));

            $this->repository->updateCabecera($id, $idEmpresa, [
                'id_vehiculo'       => (int) $data['id_vehiculo'],
                'id_cliente'        => empty($data['id_cliente']) ? null : (int) $data['id_cliente'],
                'id_bodega'         => empty($data['id_bodega']) ? null : (int) $data['id_bodega'],
                'placa'             => $data['placa'] ?? null,
                'marca'             => $data['marca'] ?? null,
                'modelo'            => $data['modelo'] ?? null,
                'kilometraje'       => ($data['kilometraje'] ?? '') === '' ? null : (int) $data['kilometraje'],
                'nivel_combustible' => $data['nivel_combustible'] ?? null,
                'fecha_ingreso'     => $data['fecha_ingreso'],
                // La pantalla no edita estos dos campos: se conservan (p. ej. la nota de las
                // órdenes migradas). Antes se enviaban vacíos y se borraban al editar.
                'novedades_texto'   => $data['novedades_texto'] ?? ($cab['novedades_texto'] ?? null),
                'observaciones'     => $data['observaciones'] ?? ($cab['observaciones'] ?? null),
                'info_adicional'    => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'proxima_cita'      => empty($data['proxima_cita']) ? null : $data['proxima_cita'],
                'subtotal'          => $tot['subtotal'],
                'descuento'         => $tot['descuento'],
                'iva'               => $tot['iva'],
                'total'             => $tot['total'],
                'updated_by'        => $idUsuario,
                'updated_at'        => date('Y-m-d H:i:s'),
            ] + ($this->repository->tieneColumnaCondiciones()
                ? ['condiciones_html' => \App\Rules\modulos\ProformaRules::sanitizarCondiciones($data['condiciones_html'] ?? null)]
                : []
            ) + ($this->documentoLiberado($cab) && $this->repository->existeTablaDocumentos() ? [
                // Su factura/recibo se anuló: la orden vuelve a borrador para re-facturarla.
                // El documento anterior queda en el historial de Facturación.
                'estado'           => 'borrador',
                'tipo_documento'   => null,
                'id_documento'     => null,
                'numero_documento' => null,
            ] : []));

            // Inventario: se revierte la salida anterior y se vuelve a aplicar con las líneas nuevas.
            $this->validarBodegasLineas($data['detalles'], (int) ($data['id_bodega'] ?? 0), $idEmpresa, (int) ($data['id_establecimiento'] ?? $cab['id_establecimiento'] ?? 0));
            $this->validarConfiguracionFacturacion($data['detalles'], $idEmpresa, (int) ($data['id_establecimiento'] ?? $cab['id_establecimiento'] ?? 0));
            $this->revertirInventario($id, $idEmpresa, $idUsuario);
            $this->aplicarSalidaInventario(
                $id, $idEmpresa, $idUsuario, $data['detalles'],
                (int) ($data['id_establecimiento'] ?? $cab['id_establecimiento'] ?? 0),
                (int) ($data['id_bodega'] ?? 0),
                (string) ($cab['numero_orden'] ?? ''), true
            );

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_ORDEN_CARWASH', 'carwash_ordenes', $id, $cab, $data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Cambio de estado ─────────────────────────────────────────────────────

    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado): void
    {
        $this->rules->validarEstado($nuevoEstado);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        $actual = (string) ($cab['estado'] ?? '');
        if ($actual === $nuevoEstado) {
            return;
        }
        if ($actual === 'facturado' || !empty($cab['id_documento'])) {
            throw new Exception("La orden ya fue facturada; su estado no puede cambiarse manualmente.");
        }
        if ($nuevoEstado === 'facturado') {
            throw new Exception("El estado 'facturado' se asigna automáticamente al generar el documento.");
        }

        $setEntrega = ($nuevoEstado === 'terminado' && empty($cab['fecha_entrega']));

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario, $setEntrega);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_ORDEN_CARWASH', 'carwash_ordenes', $id,
                ['estado' => $actual], ['estado' => $nuevoEstado]);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Eliminar ─────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        if ((!empty($cab['id_documento']) || ($cab['estado'] ?? '') === 'facturado') && !$this->documentoLiberado($cab)) {
            throw new Exception("No se puede eliminar una orden que ya generó un documento. Anule primero el documento.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            // Devolver al stock lo que la orden había consumido.
            $this->revertirInventario($id, $idEmpresa, $idUsuario);
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_ORDEN_CARWASH', 'carwash_ordenes', $id, $cab, null);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── Generar documento de venta (Factura / Recibo) ───────────────────────

    /**
     * Genera un documento de venta (FACTURA o RECIBO) a partir de la orden,
     * reutilizando FacturaVentaService / ReciboVentaService. La orden solo arma el
     * payload; inventario, XML/SRI y asiento contable los maneja cada service.
     *
     * @return array{tipo:string,id_documento:int,numero_documento:string}
     */
    public function generarDocumento(int $idOrden, int $idEmpresa, int $idUsuario, string $tipo, array $extra, array $empresaConfig): array
    {
        $tipo  = strtoupper($tipo);
        $orden = $this->getDetalleCompleto($idOrden, $idEmpresa);
        if (!$orden) {
            throw new Exception('Orden no encontrada.');
        }
        $this->rules->validarGeneracionDocumento($orden, $tipo, $extra);
        // Estado del cliente leído en este momento (pudo desactivarse después de guardar la orden).
        $this->validarClienteActivo((int) $orden['id_cliente'], $idEmpresa);
        // Configuración de facturación vigente (pudo cambiar desde que se guardó la orden).
        $this->validarConfiguracionFacturacion(array_map(
            fn($d) => $d + ['caducidad' => $d['fecha_caducidad'] ?? null],
            $orden['detalles'] ?? []
        ), $idEmpresa, (int) ($orden['id_establecimiento'] ?? 0));

        $detalles = $orden['detalles'] ?? [];
        if (empty($detalles)) {
            throw new Exception('La orden no tiene servicios ni productos.');
        }

        $idPunto = (int) ($orden['id_punto_emision'] ?? 0);
        $idEstab = (int) ($orden['id_establecimiento'] ?? 0);
        if ($idPunto <= 0) {
            throw new Exception('La orden no tiene punto de emisión para numerar el documento.');
        }
        $estCod   = (string) ($orden['establecimiento'] ?? '');
        $puntoCod = (string) ($orden['punto_emision'] ?? '');
        $formaPago = (string) ($extra['forma_pago'] ?? '01');
        // La bodega de la orden (cabecera) manda; si no, la que venga en la emisión.
        $idBodegaExtra = (int) ($orden['id_bodega'] ?? 0) ?: (int) ($extra['id_bodega'] ?? 0);

        // Tarifa de IVA de cada línea: la que se guardó en la orden (id y % de la línea). Solo
        // si la línea no la tiene se cae al producto y, al final, al porcentaje. Antes mandaba
        // la del producto y el documento podía salir con un total distinto al de la orden.
        $validas = []; $tarifas = [];
        foreach ($detalles as $k => $d) {
            if ((float) $d['cantidad'] <= 0) continue;
            $pctLinea = (float) ($d['porcentaje_iva'] ?? 0);
            $tar = null;
            if (!empty($d['id_tarifa_iva'])) $tar = $this->repository->getTarifaIvaById((int) $d['id_tarifa_iva']);
            // La tarifa guardada no coincide con el % de la línea (dato viejo o tarifa editada):
            // manda el % con el que se cotizó la orden.
            if ($tar && abs((float) $tar['porcentaje_iva'] - $pctLinea) > 0.001) $tar = null;
            if (!$tar) $tar = $this->repository->getTarifaIvaByPorcentaje($pctLinea);
            if (!$tar && !empty($d['id_producto'])) $tar = $this->repository->getTarifaIvaProducto((int) $d['id_producto']);
            $tarifas[$k] = $tar;
            $validas[$k] = $d + [
                'porcentaje_iva' => $pctLinea,
                'grupo' => !empty($d['id_tarifa_iva']) ? (string) (int) $d['id_tarifa_iva'] : '',
            ];
        }
        if (empty($validas)) {
            throw new Exception('No hay líneas válidas para facturar.');
        }

        // MISMO cálculo que la orden (calcularLineas) y con el modo de IVA del establecimiento
        // DE LA ORDEN: antes se tomaba el del primer establecimiento de la empresa y, con modos
        // distintos entre establecimientos, el documento podía diferir de la orden en centavos.
        $empresaConfig = array_merge($empresaConfig, $this->configEstablecimiento($idEstab));
        $modoIva = IvaSubtotal::modo($empresaConfig);
        $calc = self::calcularLineas($validas, $modoIva);

        $det = []; $idBodega = 0;
        foreach ($validas as $k => $d) {
            $c   = $calc['lineas'][$k];
            $tar = $tarifas[$k];
            $pct    = $c['pct'];
            $codPct = $tar ? (string) $tar['codigo'] : '0';
            $idTar  = $tar ? (int) $tar['id'] : (!empty($d['id_tarifa_iva']) ? (int) $d['id_tarifa_iva'] : 0);

            // La bodega de la cabecera aplica a toda la orden (las líneas ya no eligen bodega).
            $bodegaLinea = $idBodegaExtra ?: (int) ($d['id_bodega'] ?? 0);
            if ($idBodega === 0 && $bodegaLinea > 0) $idBodega = $bodegaLinea;

            $esLibre = empty($d['id_producto']);
            $det[] = [
                'id_producto'               => $esLibre ? null : (int) $d['id_producto'],
                'id_bodega'                 => $bodegaLinea ?: null,
                // El XML SRI exige codigoPrincipal: sin él la factura sale con <codigoPrincipal/>
                // vacío y el SRI la devuelve. Los ítems libres toman el código del servicio que
                // se crea en el catálogo al emitir (crearServicioLibre).
                'codigo_principal'          => $esLibre ? null : ((string) ($d['producto_codigo'] ?? '') ?: null),
                // Lote / caducidad / NUP / unidad elegidos en la orden (configuración de facturación).
                'lote'                      => ($d['lote'] ?? '') !== '' ? $d['lote'] : null,
                'caducidad'                 => !empty($d['fecha_caducidad']) ? substr((string) $d['fecha_caducidad'], 0, 10) : null,
                'nup'                       => ($d['nup'] ?? '') !== '' ? $d['nup'] : null,
                'id_unidad_medida'          => !empty($d['id_unidad_medida']) ? (int) $d['id_unidad_medida'] : null,
                'descripcion'               => $d['descripcion'],
                'nombre'                    => $d['descripcion'],
                'cantidad'                  => $c['cantidad'],
                'precio_unitario'           => $c['precio'],
                'descuento'                 => $c['descuento'],
                'precio_total_sin_impuesto' => $c['base'],
                'id_tarifa_iva'             => $idTar,
                'codigo_porcentaje'         => $codPct,
                'es_libre'                  => $esLibre ? '1' : 0,
                'porcentaje_iva'            => $pct,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa'            => $pct,
                    'base_imponible'    => $c['base'],
                    'valor'             => $c['iva'],
                ]],
            ];
        }

        $totalSinImp  = $calc['subtotal'];
        $totalDesc    = $calc['descuento'];
        $ivaTotal     = $calc['iva'];
        $importeTotal = $calc['total'];
        if ($idBodegaExtra > 0) $idBodega = $idBodegaExtra;

        // Garantía de exactitud: el documento debe salir con el MISMO total que la orden. Si
        // la orden guardada tiene otro total (se guardó con una versión anterior del cálculo),
        // se recalcula y se actualiza la orden con los importes exactos de sus líneas.
        $ordenDesactualizada = abs((float) ($orden['total'] ?? 0) - $importeTotal) > 0.001
            || abs((float) ($orden['subtotal'] ?? 0) - $totalSinImp) > 0.001
            || abs((float) ($orden['iva'] ?? 0) - $ivaTotal) > 0.001;

        // Info adicional del documento: la de la orden + placa y número de orden (como hacía el
        // sistema anterior), sin duplicar si el usuario ya los escribió.
        $infoAdicional = is_array($orden['info_adicional'] ?? null) ? $orden['info_adicional'] : [];
        $yaTiene = array_map(fn($ia) => mb_strtolower(trim((string) ($ia['nombre'] ?? ''))), $infoAdicional);
        if (!empty($orden['placa']) && !in_array('placa', $yaTiene, true)) {
            $infoAdicional[] = ['nombre' => 'Placa', 'valor' => (string) $orden['placa']];
        }
        if (!empty($orden['numero_orden']) && !in_array('orden n.', $yaTiene, true)) {
            $infoAdicional[] = ['nombre' => 'Orden N.', 'valor' => (string) $orden['numero_orden']];
        }
        // "Correo del cliente": UNA sola fila y con el correo VIGENTE del cliente, igual en
        // factura y recibo. La factura ya lo hacía (FacturaVentaService actualiza la fila),
        // pero el recibo guardaba lo que trajera la orden: un correo viejo si el cliente lo
        // cambió, o ninguno si la orden no tenía la fila (p. ej. órdenes migradas).
        $infoAdicional = array_values(array_filter($infoAdicional,
            fn($ia) => strcasecmp(trim((string) ($ia['nombre'] ?? '')), 'Correo del cliente') !== 0));
        $correoCliente = trim((string) ($orden['cliente_email'] ?? ''));
        if ($correoCliente !== '') {
            $infoAdicional[] = ['nombre' => 'Correo del cliente', 'valor' => $correoCliente];
        }

        // TODO el proceso va en UNA transacción: secuencial (su candado se libera solo al
        // COMMIT/ROLLBACK, CLAUDE.md §8) → devolver el inventario de la orden → crear el
        // documento → marcar la orden + historial. Si algo falla, el rollback deja la orden
        // exactamente como estaba (incluida su salida de inventario). Antes la emisión y el
        // marcado iban en transacciones separadas y, al fallar, se "restauraba" la salida de
        // la orden a mano sobre un rollback que ya la había conservado → descontaba dos veces.
        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            $tipoDocSec = ($tipo === 'FACTURA') ? 'Facturas de venta' : 'Recibos de venta';
            $sec = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, $tipoDocSec, date('Y-m-d'));
            $secuencial = $sec['formateado'];
            $numeroDoc  = $estCod . '-' . $puntoCod . '-' . $secuencial;

            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_usuario'          => $idUsuario,
                'empresa_config'      => $empresaConfig,
                'id_establecimiento'  => $idEstab,
                'id_punto_emision'    => $idPunto,
                'establecimiento'     => $estCod,
                'punto_emision'       => $puntoCod,
                'secuencial'          => $secuencial,
                'fecha_emision'       => date('Y-m-d'),
                'id_cliente'          => (int) $orden['id_cliente'],
                'id_vendedor'         => null,
                'dias_credito'        => 0,
                'moneda'              => 'DOLAR',
                'observaciones'       => 'Generado desde orden car-wash ' . ($orden['numero_orden'] ?? ''),
                'id_bodega'           => $idBodega ?: null,
                'total_sin_impuestos' => $totalSinImp,
                'total_descuento'     => $totalDesc,
                'total_ice'           => 0,
                'propina'             => 0,
                'importe_total'       => $importeTotal,
                'detalles'            => $det,
                'pagos'               => [[
                    'forma_pago'    => $formaPago,
                    'total'         => $importeTotal,
                    'plazo'         => 0,
                    'unidad_tiempo' => 'dias',
                ]],
                'info_adicional'      => $infoAdicional,
            ];

            if ($ordenDesactualizada) {
                foreach ($validas as $k => $d) {
                    $this->repository->updateLineaImportes((int) $d['id'], $idEmpresa, $calc['lineas'][$k]['iva'], $calc['lineas'][$k]['total']);
                }
                $this->repository->updateTotales($idOrden, $idEmpresa, $totalSinImp, $totalDesc, $ivaTotal, $importeTotal);
            }

            // El documento hace su propia salida de inventario: primero devolvemos al stock
            // lo que consumió la orden, para no descontar dos veces.
            $this->revertirInventario($idOrden, $idEmpresa, $idUsuario);

            $svcFactura = null;
            if ($tipo === 'FACTURA') {
                $svcFactura = new FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    $this->logService
                );
                $idDoc = $svcFactura->crear($payload);
            } else {
                $payload['con_impuestos'] = true;
                $payload['estado']        = 'borrador';
                $payload['plazo']         = 0;
                $svc = new ReciboVentaService(
                    new \App\repositories\modulos\ReciboVentaRepository(),
                    new \App\Rules\modulos\ReciboVentaRules(),
                    $this->logService
                );
                $idDoc = $svc->crear($payload);
            }

            // Marcar la orden como facturada + historial de Facturación.
            $this->repository->marcarDocumentoGenerado($idOrden, $idEmpresa, $tipo, (int) $idDoc, $numeroDoc, $idUsuario);
            $this->repository->insertDocumento([
                'id_empresa'       => $idEmpresa,
                'id_orden'         => $idOrden,
                'tipo_documento'   => $tipo,
                'id_documento'     => (int) $idDoc,
                'numero_documento' => $numeroDoc,
                'fecha_emision'    => date('Y-m-d H:i:s'),
                'total'            => $importeTotal,
                'origen'           => 'sistema',
                'id_usuario'       => $idUsuario,
            ]);
            $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_DOCUMENTO_CARWASH', 'carwash_ordenes', $idOrden,
                ['estado' => $orden['estado'] ?? '', 'id_documento' => $orden['id_documento'] ?? null],
                ['tipo_documento' => $tipo, 'id_documento' => $idDoc, 'numero_documento' => $numeroDoc]);

            if ($managedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // XML de la factura FUERA de la transacción: FacturaVentaService::crear() solo lo genera
        // cuando controla él la transacción; anidado aquí no lo hacía y la factura quedaba sin
        // XML (no se podía enviar al SRI). Si falla, la factura queda creada y el XML puede
        // regenerarse desde Facturas de Venta.
        if ($svcFactura !== null && $managedTransaction) {
            $svcFactura->generarYGuardarXml((int) $idDoc, $empresaConfig);
        }

        return ['tipo' => $tipo, 'id_documento' => (int) $idDoc, 'numero_documento' => $numeroDoc];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Normaliza y serializa la info adicional [{nombre,valor}] a JSON para guardar. */
    private function encodeInfoAdicional($info): ?string
    {
        if (!is_array($info)) return null;
        $limpio = [];
        foreach ($info as $ia) {
            $nom = trim((string) ($ia['nombre'] ?? ''));
            $val = trim((string) ($ia['valor'] ?? ''));
            if ($nom !== '' && $val !== '') {
                $limpio[] = ['nombre' => $nom, 'valor' => $val];
            }
        }
        return empty($limpio) ? null : json_encode($limpio, JSON_UNESCAPED_UNICODE);
    }

    /** Configuración del establecimiento (vacía si no hay o falla la lectura). */
    private function configEstablecimiento(int $idEstablecimiento): array
    {
        if ($idEstablecimiento <= 0) return [];
        try {
            return (new \App\repositories\modulos\EmpresaRepository())->getEstablecimientoConfig($idEstablecimiento) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Modo de cálculo del IVA del establecimiento ('subtotal' | 'linea_linea'). */
    private function modoIvaEstablecimiento(int $idEstablecimiento): string
    {
        if ($idEstablecimiento <= 0) return 'linea_linea';
        try {
            return IvaSubtotal::modo((new \App\repositories\modulos\EmpresaRepository())->getEstablecimientoConfig($idEstablecimiento));
        } catch (\Throwable $e) {
            return 'linea_linea';
        }
    }

    /** Decodifica la info adicional almacenada (JSON) a array [{nombre,valor}]. */
    private function decodeInfoAdicional($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * Inserta las líneas de detalle y novedades de una orden y devuelve los totales.
     * Los importes se calculan en el backend a partir de cantidad/precio/descuento/%IVA.
     */
    /**
     * ÚNICO cálculo de importes de la orden, usado al GUARDAR la orden y al EMITIR la factura
     * o el recibo: así el documento sale con exactamente los mismos subtotales, IVA y total
     * que la orden. Los valores se llevan primero a la precisión con la que se guardan
     * (cantidad y precio a 6 decimales, descuento a 2), para que recalcular desde lo
     * guardado dé lo mismo que se calculó al guardar.
     *
     * @param array $lineas [k => ['cantidad','precio_unitario','descuento','porcentaje_iva','grupo']]
     *        `grupo` = tarifa de IVA (id, o el % si no hay id) para el modo 'subtotal'.
     * @return array{lineas: array, subtotal: float, descuento: float, iva: float, total: float}
     *         lineas[k] = cantidad, precio, descuento, base, iva, total (normalizados).
     */
    public static function calcularLineas(array $lineas, string $modoIva): array
    {
        $norm = [];
        foreach ($lineas as $k => $l) {
            $cant   = round((float) ($l['cantidad'] ?? 0), 6);
            $precio = round((float) ($l['precio_unitario'] ?? 0), 6);
            $dscto  = round((float) ($l['descuento'] ?? 0), 2);
            $norm[$k] = [
                'cantidad'  => $cant,
                'precio'    => $precio,
                'descuento' => $dscto,
                'pct'       => (float) ($l['porcentaje_iva'] ?? 0),
                'grupo'     => (string) ($l['grupo'] ?? ''),
                'base'      => max(0.0, round($precio * $cant - $dscto, 2)),
            ];
        }

        // IVA por línea según el modo del establecimiento: en 'subtotal' se calcula sobre la
        // suma de bases de cada tarifa y se reparte entre las líneas (cuadra al centavo).
        $ivas = IvaSubtotal::repartir(array_map(fn($n) => [
            'grupo' => $n['grupo'] !== '' ? $n['grupo'] : (string) $n['pct'],
            'base'  => $n['base'],
            'pct'   => $n['pct'],
        ], $norm), $modoIva);

        $sub = 0.0; $desc = 0.0; $iva = 0.0;
        foreach ($norm as $k => &$n) {
            $n['iva']   = (float) $ivas[$k];
            $n['total'] = round($n['base'] + $n['iva'], 2);
            $sub  += $n['base'];
            $desc += $n['descuento'];
            $iva  += $n['iva'];
        }
        unset($n);

        $sub = round($sub, 2); $iva = round($iva, 2);
        return [
            'lineas'    => $norm,
            'subtotal'  => $sub,
            'descuento' => round($desc, 2),
            'iva'       => $iva,
            'total'     => round($sub + $iva, 2),
        ];
    }

    private function guardarLineas(int $idOrden, int $idEmpresa, array $data, string $modoIva = 'linea_linea'): array
    {
        $validas = [];
        foreach ($data['detalles'] as $k => $det) {
            if ((float) ($det['cantidad'] ?? 0) <= 0 || trim((string) ($det['descripcion'] ?? '')) === '') continue;
            $validas[$k] = $det + ['grupo' => !empty($det['id_tarifa_iva']) ? (string) (int) $det['id_tarifa_iva'] : ''];
        }
        $calc = self::calcularLineas($validas, $modoIva);

        foreach ($validas as $k => $det) {
            $c = $calc['lineas'][$k];
            $this->repository->insertDetalle([
                'id_orden'        => $idOrden,
                'id_empresa'      => $idEmpresa,
                'id_producto'     => empty($det['id_producto']) ? null : (int) $det['id_producto'],
                'tipo_linea'      => ($det['tipo_linea'] ?? 'servicio') === 'producto' ? 'producto' : 'servicio',
                'es_libre'        => !empty($det['es_libre']),
                'descripcion'     => trim((string) $det['descripcion']),
                'id_bodega'       => empty($det['id_bodega']) ? null : (int) $det['id_bodega'],
                'cantidad'        => $c['cantidad'],
                'precio_unitario' => $c['precio'],
                'descuento'       => $c['descuento'],
                'porcentaje_iva'  => $c['pct'],
                'valor_iva'       => $c['iva'],
                'total_linea'     => $c['total'],
                'id_tarifa_iva'   => empty($det['id_tarifa_iva']) ? null : (int) $det['id_tarifa_iva'],
                'lote'             => $det['lote'] ?? null,
                'caducidad'        => $det['caducidad'] ?? null,
                'nup'              => $det['nup'] ?? null,
                'id_unidad_medida' => $det['id_unidad_medida'] ?? null,
            ]);
        }

        foreach (($data['novedades'] ?? []) as $nov) {
            $descNov = trim((string) ($nov['descripcion'] ?? ''));
            if ($descNov === '') continue;
            $this->repository->insertNovedad([
                'id_orden'    => $idOrden,
                'id_empresa'  => $idEmpresa,
                'descripcion' => $descNov,
                'severidad'   => in_array(($nov['severidad'] ?? 'leve'), ['leve', 'media', 'grave'], true) ? $nov['severidad'] : 'leve',
            ]);
        }

        return [
            'subtotal'  => $calc['subtotal'],
            'descuento' => $calc['descuento'],
            'iva'       => $calc['iva'],
            'total'     => $calc['total'],
        ];
    }
}
