<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CambioProductoCvRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\CambioProductoCvRules;
use App\Services\LogSistemaService;
use App\core\Database;
use Exception;

/**
 * Lógica de negocio de Cambios de productos.
 *
 * Un cambio registra, en un solo documento y por cliente:
 *   - DEVOLUCIONES → ENTRADA de inventario (el cliente regresa mercadería de una
 *     factura de consignación o de un cambio anterior). Se copia "tal cual" del origen y se valida saldo.
 *   - ENTREGAS (el cliente recibe otros productos a cambio) → tomadas de una CONSIGNACIÓN
 *     que el cliente ya tiene (origen_tipo 'CONSIGNACION': consume el saldo de esa línea de
 *     consignación y no mueve stock, porque ya salió con la consignación). Desde el
 *     18-09-2026 es el único origen aceptado (CambioProductoCvRules::validarCreacion); los
 *     cambios anteriores pueden tener entregas desde bodega (catálogo / existencias), que
 *     son SALIDA de inventario y un borrador conserva al editarlo.
 *
 * La diferencia de valor (entregado − devuelto) es informativa. Inventario y
 * asiento contable (a costo) van ligados al estado 'Emitida', igual que los registros en
 * Facturación de consignaciones de lo entregado desde consignación (crearRegistrosFacturacion).
 */
class CambioProductoCvService
{
    use \App\Traits\PeriodoContableTrait;

    /** Tipo de documento en empresa_secuencial / SecuencialRepository::DOCUMENT_MAP. */
    private const TIPO_SECUENCIAL = 'Cambios de productos';

    private CambioProductoCvRepository $repository;
    private CambioProductoCvRules $rules;
    private LogSistemaService $logService;
    private InventarioRepository $inventarioRepo;

    /** Número (serie-secuencial) que el servidor asignó en el último crear(); lo muestra el controlador. */
    private ?string $ultimoNumeroGenerado = null;

    /** Cache por petición de facturaAfectada() para unidades que llegaron en un cambio: id de la línea de entrega → número. */
    private array $facturasAfectadas = [];

    /** Cache por petición de ivaDelProducto(): "producto|tarifa de respaldo" → [id_impuesto, porcentaje] o null. */
    private array $tarifasIva = [];

    public function __construct(
        CambioProductoCvRepository $repository,
        CambioProductoCvRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository     = $repository;
        $this->rules          = $rules;
        $this->logService     = $logService;
        $this->inventarioRepo = new InventarioRepository();
    }

    /** Número (serie-secuencial) que el servidor asignó en el último crear(). */
    public function getUltimoNumeroGenerado(): ?string
    {
        return $this->ultimoNumeroGenerado;
    }

    /**
     * Reserva el número del cambio: serie y secuencial los decide el SERVIDOR.
     *
     * DEBE llamarse con la transacción del llamador YA ABIERTA y mantenerla hasta el INSERT de
     * la cabecera (CLAUDE.md §8): `obtenerSiguienteSecuencial()` toma por dentro un
     * `pg_advisory_xact_lock` por (punto, tipo de documento) que solo se libera en el
     * COMMIT/ROLLBACK — es ese candado el que pone en fila a dos usuarios guardando a la vez.
     *
     * Antes se insertaba el secuencial que llegaba del POST: el candado y el pre-check ya
     * estaban, pero al no recalcular, el segundo usuario recibía un error y tenía que recargar.
     * Ahora simplemente toma el siguiente número libre. La serie se deriva del punto validado
     * contra la empresa, no del texto del navegador.
     */
    private function reservarNumero(int $idEmpresa, array $data): array
    {
        $idPunto = (int) ($data['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            throw new Exception('Debe seleccionar la serie (punto de emisión) del documento.');
        }

        $secRepo = new \App\repositories\SecuencialRepository();
        $punto   = $secRepo->getPuntoEmisionSerie($idPunto, $idEmpresa);
        if (!$punto) {
            throw new Exception('La serie seleccionada no pertenece a esta empresa.');
        }
        if (empty($secRepo->getConfigSecuencial($idPunto, self::TIPO_SECUENCIAL)['id'])) {
            throw new Exception('La serie seleccionada no tiene configurado el secuencial de "' . self::TIPO_SECUENCIAL . '". Configúrelo en Empresa / Secuenciales.');
        }

        $res        = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL, $data['fecha_cambio'] ?? null);
        $secuencial = (string) ($res['formateado'] ?? str_pad((string) ($res['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT));

        $tipoAmbiente = (string) ($data['empresa_config']['tipo_ambiente'] ?? '1');

        // Pre-check para dar un mensaje entendible en vez del error crudo del índice único.
        if ($this->repository->existeSecuencial($idEmpresa, $idPunto, $secuencial, $tipoAmbiente)) {
            throw new Exception("El número {$punto['establecimiento']}-{$punto['punto']}-{$secuencial} ya está en uso. Vuelva a guardar para tomar el siguiente número libre.");
        }

        return [
            'id_punto_emision' => $idPunto,
            'establecimiento'  => (string) $punto['establecimiento'],
            'punto_emision'    => (string) $punto['punto'],
            'serie'            => $punto['establecimiento'] . '-' . $punto['punto'],
            'secuencial'       => $secuencial,
            'tipo_ambiente'    => $tipoAmbiente,
        ];
    }

    /**
     * Listado principal: una fila por pareja "entra ↔ sale" (ver CambioProductoCvRepository::getListado).
     * Agrega la factura de venta de la que vino el cambio en esa fila:
     *  - dev_factura_afectada: la de lo que entra (ver facturaAfectada());
     *  - factura_cambio: en las filas que solo tienen lo que sale, la de la última devolución del
     *    cambio, que es con la que se emparejan las entregas que sobran (misma regla que el registro
     *    en Facturación de consignaciones).
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro, array $ordenMulti = []): array
    {
        $res = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $ordenMulti);

        $soloSale = array_map(fn($r) => (int) $r['id'], array_filter($res['rows'], fn($r) => $r['dev_cantidad'] === null));
        $ultimas  = $this->repository->getUltimasDevoluciones($soloSale, $idEmpresa);

        foreach ($res['rows'] as &$r) {
            $r['dev_factura_afectada'] = $this->facturaAfectada($r['dev_origen_tipo'] ?? null, $r['dev_origen_numero'] ?? null,
                (int) ($r['dev_id_origen'] ?? 0), (int) ($r['dev_id_origen_detalle'] ?? 0), $idEmpresa);
            $r['factura_cambio'] = null;
            if ($r['dev_cantidad'] === null && isset($ultimas[(int) $r['id']])) {
                $u = $ultimas[(int) $r['id']];
                $r['factura_cambio'] = $this->facturaAfectada($u['origen_tipo'] ?? null, $u['origen_numero'] ?? null,
                    (int) ($u['id_origen'] ?? 0), (int) ($u['id_origen_detalle'] ?? 0), $idEmpresa);
            }
        }
        unset($r);
        return $res;
    }

    /** Búsqueda libre dentro de los cambios (líneas devueltas y entregadas): pestaña Detalles del buscador. */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuarioFiltro = null, int $limit = 50): array
    {
        return $this->repository->buscarEnDetalles($idEmpresa, $q, $idUsuarioFiltro, $limit);
    }

    /** Listas de los selects del modal de filtros: solo los valores que la empresa ya usó. */
    public function getOpcionesFiltros(int $idEmpresa): array
    {
        return [
            'responsables' => $this->repository->getResponsablesUsados($idEmpresa),
            'usuarios'     => $this->repository->getUsuariosUsados($idEmpresa),
        ];
    }

    /**
     * Líneas a devolver (facturas de consignación + cambios previos). $idCliente null = todos los clientes.
     * Agrega factura_afectada: la factura de venta de la que viene cada unidad (ver facturaAfectada()).
     */
    public function getLineasDisponiblesCliente(int $idEmpresa, ?int $idCliente, string $q, ?int $excluirCambio = null): array
    {
        $rows = $this->repository->getLineasDisponiblesCliente($idEmpresa, $idCliente, $q, $excluirCambio);
        foreach ($rows as &$r) {
            $r['factura_afectada'] = $this->facturaAfectada($r['origen_tipo'] ?? null, $r['doc_numero'] ?? null,
                (int) ($r['id_origen'] ?? 0), (int) ($r['id_origen_detalle'] ?? 0), $idEmpresa);
        }
        unset($r);
        return $rows;
    }

    /** Líneas de consignaciones entregadas con saldo en poder del cliente, para entregarlas a cambio. */
    public function getLineasConsignacionDisponibles(int $idEmpresa, string $q, ?int $idCliente, ?int $excluirCambio = null): array
    {
        return $this->repository->getLineasConsignacionDisponibles($idEmpresa, $q, $idCliente, $excluirCambio);
    }

    /**
     * Cabecera + detalles (modal, PDF, Excel, correo). Cada línea que entra lleva factura_afectada:
     * la factura de venta de la que viene (ver facturaAfectada()).
     */
    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) return null;
        $cabecera['detalles'] = $this->repository->getDetalles($id, $idEmpresa);
        foreach ($cabecera['detalles'] as &$d) {
            if (($d['tipo_linea'] ?? '') === 'devolucion') {
                $d['factura_afectada'] = $this->facturaAfectada($d['origen_tipo'] ?? null, $d['origen_numero'] ?? null,
                    (int) ($d['id_origen'] ?? 0), (int) ($d['id_origen_detalle'] ?? 0), $idEmpresa);
            }
        }
        unset($d);
        return $cabecera;
    }

    /**
     * Número de la factura de venta AFECTADA por una unidad que entra: el de su factura de venta
     * ($numeroOrigen, cuando viene de una factura de consignación) o, si la unidad llegó en un
     * cambio anterior, el de la factura de venta de origen de esa unidad (resolverFacturaDeVenta:
     * la factura donde quedó registrada o la del emparejamiento de ese cambio). Null si no se
     * encuentra: la vista muestra entonces el número del cambio.
     */
    private function facturaAfectada(?string $origenTipo, ?string $numeroOrigen, int $idOrigen, int $idOrigenDetalle, int $idEmpresa): ?string
    {
        $origenTipo = strtoupper((string) $origenTipo);
        if ($origenTipo === 'FACTURA') {
            $num = trim((string) $numeroOrigen);
            return $num !== '' ? $num : null;
        }
        if ($origenTipo !== 'CAMBIO' || $idOrigenDetalle <= 0) {
            return null;
        }
        if (!array_key_exists($idOrigenDetalle, $this->facturasAfectadas)) {
            $factura = $this->resolverFacturaDeVenta(
                ['origen_tipo' => 'CAMBIO', 'id_origen' => $idOrigen, 'id_origen_detalle' => $idOrigenDetalle],
                $idEmpresa
            );
            $num = trim((string) ($factura['numero_factura'] ?? ''));
            $this->facturasAfectadas[$idOrigenDetalle] = $num !== '' ? $num : null;
        }
        return $this->facturasAfectadas[$idOrigenDetalle];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->find($id, $idEmpresa);
    }

    // ─── CREAR ────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validarCreacion($data);

        $this->validarPeriodoContable(
            $data["fecha_cambio"] ?? null,
            (int) ($data["id_empresa"] ?? 0),
            "No se puede registrar el cambio porque el período contable de esa fecha está cerrado."
        );
        $this->rules->validarNumeracion($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $empresaConfig = $data['empresa_config'] ?? [];

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // Número del documento: lo decide el SERVIDOR, dentro de esta transacción.
            // Lo que manda el navegador es solo la vista previa que se cargó al abrir el modal.
            $num = $this->reservarNumero($idEmpresa, $data);

            $cabecera = [
                'id_empresa'              => $idEmpresa,
                'fecha_cambio'            => $data['fecha_cambio'],
                'serie'                   => $num['serie'],
                'secuencial'              => $num['secuencial'],
                'id_punto_emision'        => $num['id_punto_emision'],
                'establecimiento'         => $num['establecimiento'],
                'punto_emision'           => $num['punto_emision'],
                'tipo_ambiente'           => $num['tipo_ambiente'],
                'id_cliente'              => (int) $data['id_cliente'],
                'id_responsable_traslado' => empty($data['id_responsable_traslado']) ? null : (int) $data['id_responsable_traslado'],
                'motivo'                  => $data['motivo'] ?? null,
                'observaciones'           => $data['observaciones'] ?? null,
                'estado'                  => 'Emitida',
                'subtotal_devuelto'       => 0,
                'subtotal_entregado'      => 0,
                'diferencia'              => 0,
                'created_by'              => $idUsuario,
                'updated_by'              => $idUsuario,
            ];
            try {
                $idCambio = $this->repository->create($cabecera);
            } catch (\PDOException $e) {
                // Última línea de defensa: el índice único (uq_cambios_producto_cv_secuencial_activo).
                if (($e->errorInfo[0] ?? '') === '23505') {
                    throw new Exception("El número {$num['serie']}-{$num['secuencial']} ya está en uso. Vuelva a guardar para tomar el siguiente número libre.");
                }
                throw $e;
            }

            $numero = $num['serie'] . '-' . $num['secuencial'];
            $this->ultimoNumeroGenerado = $numero;

            $totDev = $this->procesarLineas($idCambio, $idEmpresa, $idUsuario, $empresaConfig, $data['devoluciones'] ?? [], 'devolucion', true, $numero, (int) $data['id_cliente']);
            $totEnt = $this->procesarLineas($idCambio, $idEmpresa, $idUsuario, $empresaConfig, $data['entregas'] ?? [], 'entrega', true, $numero, (int) $data['id_cliente']);

            $this->repository->updateCabecera($idCambio, $idEmpresa, [
                'subtotal_devuelto'  => round($totDev, 6),
                'subtotal_entregado' => round($totEnt, 6),
                'diferencia'         => round($totEnt - $totDev, 6),
            ]);
            $cabecera['subtotal_devuelto']  = round($totDev, 6);
            $cabecera['subtotal_entregado'] = round($totEnt, 6);
            $cabecera['diferencia']         = round($totEnt - $totDev, 6);

            // Lo entregado desde consignación queda facturado en la factura de venta de lo devuelto.
            $this->crearRegistrosFacturacion($idCambio, $idEmpresa, $idUsuario);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $idCambio, null, $cabecera);

            $db->commit();

            $this->procesarAsientoSeguro($idCambio, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);

            return $idCambio;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Inserta las líneas de un lado (devolucion|entrega). Si $aplicaInventario, mueve stock.
     * Devuelve el total monetario del lado.
     *
     * Los valores no se muestran en pantalla, pero alimentan la diferencia del cambio y el registro
     * en Facturación de consignaciones. Lo que entra copia precio e IVA de su línea de origen, menos
     * el descuento que tuvo esa línea; lo que sale lleva el IVA vigente del producto
     * (ivaDelProducto). El IVA nunca se toma del navegador.
     */
    private function procesarLineas(int $idCambio, int $idEmpresa, int $idUsuario, array $empresaConfig, array $lineas, string $tipoLinea, bool $aplicaInventario, string $numero, int $idCliente): float
    {
        $total = 0.0;

        foreach ($lineas as $det) {
            $cant = (float) ($det['cantidad'] ?? 0);
            if ($cant <= 0) continue;
            $descuento = 0.0;

            if ($tipoLinea === 'devolucion') {
                $origenTipo = (string) ($det['origen_tipo'] ?? '');
                $idOrigenDet = (int) ($det['id_origen_detalle'] ?? 0);

                $origen = $this->repository->getDatosLineaOrigen($origenTipo, $idOrigenDet, $idEmpresa);
                if (!$origen) {
                    throw new Exception("La línea de origen #{$idOrigenDet} ({$origenTipo}) no existe o no está disponible.");
                }
                // El ítem se localiza por NUP o número de documento sin fijar antes el cliente:
                // el documento de origen debe ser del cliente del cambio.
                if ((int) ($origen['id_cliente'] ?? 0) !== $idCliente) {
                    $docOri = ($origenTipo === 'CAMBIO' ? 'El cambio ' : 'La factura de consignación ') . ($origen['doc_numero'] ?? '');
                    throw new Exception("{$docOri} pertenece a otro cliente: no se puede devolver en este cambio.");
                }

                $saldo = $this->repository->getSaldoLineaOrigen($origenTipo, $idOrigenDet, $idEmpresa);
                if ($cant > $saldo + 1e-9) {
                    $nombre = $origen['producto_nombre'] ?? 'Producto';
                    throw new Exception("No puede devolver {$cant} de \"{$nombre}\": el saldo pendiente es {$saldo}.");
                }

                $precio  = (float) $origen['precio_unitario'];
                $idBodega = (int) ($origen['id_bodega'] ?? 0);

                // IVA con que se facturó la unidad (o con que salió en el cambio anterior). Si el
                // origen no guardó su tarifa —facturación migrada o entrega de un cambio anterior al
                // 17-09-2026—, el vigente del producto, como lo que sale.
                if (!empty($origen['id_impuesto'])) {
                    $idImp   = (int) $origen['id_impuesto'];
                    $porcImp = (float) ($origen['porcentaje_impuesto'] ?? 0);
                } else {
                    [$idImp, $porcImp] = $this->ivaDelProducto((int) $origen['id_producto'], null, (float) ($origen['porcentaje_impuesto'] ?? 0));
                }
                // Descuento de la línea de origen, en proporción a lo que se devuelve.
                $cantOrigen = (float) ($origen['cantidad_origen'] ?? 0);
                if ($cantOrigen > 0) {
                    $descuento = (float) ($origen['descuento_origen'] ?? 0) * $cant / $cantOrigen;
                }

                $linea = [
                    'tipo_linea'        => 'devolucion',
                    'origen_tipo'       => $origenTipo,
                    'id_origen'         => (int) $origen['id_origen'],
                    'id_origen_detalle' => $idOrigenDet,
                    'id_producto'       => (int) $origen['id_producto'],
                    'precio_unitario'   => $precio,
                    'id_impuesto'       => $idImp,
                    'porcentaje_impuesto' => $porcImp,
                    'id_bodega'         => $idBodega,
                    'lote'              => $origen['lote'] ?? null,
                    'nup'               => $origen['nup'] ?? null,
                    'fecha_caducidad'   => $origen['fecha_caducidad'] ?? null,
                    'inventariable'     => $origen['inventariable'] ?? null,
                    'tipo_produccion'   => $origen['tipo_produccion'] ?? null,
                ];
            } elseif (strtoupper((string) ($det['origen_tipo'] ?? '')) === 'CONSIGNACION') {
                // Entrega tomada de una CONSIGNACIÓN que el cliente ya tiene en su poder:
                // producto, bodega, lote y NUP son los de esa línea (autoritativo, no se confía
                // en el navegador); el precio llega oculto desde el formulario y el IVA es el
                // vigente del producto. Consume el saldo de la consignación y NO mueve stock (la
                // mercadería ya salió de bodega con la consignación; ver moverInventarioLinea).
                $idOrigenDet = (int) ($det['id_origen_detalle'] ?? 0);
                $origen = $this->repository->getDatosLineaConsignacion($idOrigenDet, $idEmpresa);
                if (!$origen) {
                    throw new Exception("La línea de consignación #{$idOrigenDet} no existe o la consignación ya no está Entregada.");
                }
                if ((int) ($origen['id_cliente'] ?? 0) !== $idCliente) {
                    throw new Exception("La consignación {$origen['doc_numero']} es de otro cliente: no se puede entregar desde ella en este cambio.");
                }

                $saldo = $this->repository->getSaldoLineaConsignacion($idOrigenDet, $idEmpresa, $idCambio);
                if ($cant > $saldo + 1e-9) {
                    $nombre = $origen['producto_nombre'] ?? 'Producto';
                    throw new Exception("No puede entregar {$cant} de \"{$nombre}\" desde la consignación {$origen['doc_numero']}: el saldo en poder del cliente es {$saldo}.");
                }

                $precio  = isset($det['precio_unitario']) ? (float) $det['precio_unitario'] : (float) $origen['precio_unitario'];
                [$idImp, $porcImp] = $this->ivaDelProducto((int) $origen['id_producto'],
                    empty($origen['id_impuesto']) ? null : (int) $origen['id_impuesto'],
                    (float) ($origen['porcentaje_impuesto'] ?? 0));

                $linea = [
                    'tipo_linea'        => 'entrega',
                    'origen_tipo'       => 'CONSIGNACION',
                    'id_origen'         => (int) $origen['id_origen'],
                    'id_origen_detalle' => $idOrigenDet,
                    'id_producto'       => (int) $origen['id_producto'],
                    'precio_unitario'   => $precio,
                    'id_impuesto'       => $idImp,
                    'porcentaje_impuesto' => $porcImp,
                    'id_bodega'         => (int) ($origen['id_bodega'] ?? 0),
                    'lote'              => $origen['lote'] ?? null,
                    'nup'               => $origen['nup'] ?? null,
                    'fecha_caducidad'   => $origen['fecha_caducidad'] ?? null,
                    'inventariable'     => $origen['inventariable'] ?? null,
                    'tipo_produccion'   => $origen['tipo_produccion'] ?? null,
                ];
            } else { // entrega desde bodega (catálogo / existencias)
                // Solo llega aquí la que un borrador anterior al 18-09-2026 ya tenía guardada:
                // CambioProductoCvRules::validarCreacion rechaza las nuevas.
                $idProducto = (int) ($det['id_producto'] ?? 0);
                $prod = $this->repository->getProductoParaEntrega($idProducto, $idEmpresa);
                if (!$prod) {
                    throw new Exception("El producto de entrega #{$idProducto} no existe.");
                }
                $precio   = (float) ($det['precio_unitario'] ?? 0);
                [$idImp, $porcImp] = $this->ivaDelProducto($idProducto);
                $idBodega = (int) ($det['id_bodega'] ?? 0);

                $linea = [
                    'tipo_linea'        => 'entrega',
                    'origen_tipo'       => null,
                    'id_origen'         => null,
                    'id_origen_detalle' => null,
                    'id_producto'       => $idProducto,
                    'precio_unitario'   => $precio,
                    'id_impuesto'       => $idImp,
                    'porcentaje_impuesto' => $porcImp,
                    'id_bodega'         => $idBodega,
                    'lote'              => (isset($det['lote']) && trim((string) $det['lote']) !== '') ? trim((string) $det['lote']) : null,
                    'nup'               => (isset($det['nup']) && trim((string) $det['nup']) !== '') ? trim((string) $det['nup']) : null,
                    'fecha_caducidad'   => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
                    'inventariable'     => $prod['inventariable'] ?? null,
                    'tipo_produccion'   => $prod['tipo_produccion'] ?? null,
                ];
            }

            $subtotal   = round($precio * $cant - $descuento, 6);
            $valorImp   = round($subtotal * ($porcImp / 100), 6);
            $totalLinea = round($subtotal + $valorImp, 6);

            $this->repository->insertDetalle([
                'id_cambio'           => $idCambio,
                'id_empresa'          => $idEmpresa,
                'tipo_linea'          => $linea['tipo_linea'],
                'origen_tipo'         => $linea['origen_tipo'],
                'id_origen'           => $linea['id_origen'],
                'id_origen_detalle'   => $linea['id_origen_detalle'],
                'id_producto'         => $linea['id_producto'],
                'cantidad'            => $cant,
                'precio_unitario'     => $precio,
                'subtotal'            => $subtotal,
                'id_impuesto'         => $linea['id_impuesto'],
                'porcentaje_impuesto' => $porcImp,
                'valor_impuesto'      => $valorImp,
                'total'               => $totalLinea,
                'id_bodega'           => $linea['id_bodega'],
                'lote'                => $linea['lote'],
                'nup'                 => $linea['nup'],
                'fecha_caducidad'     => $linea['fecha_caducidad'],
            ]);

            if ($aplicaInventario) {
                // Devolución = ENTRADA (regresa mercadería). Entrega = SALIDA.
                $tipoMov = ($tipoLinea === 'devolucion') ? 'entrada' : 'salida';
                $lineaInv = array_merge($linea, ['cantidad' => $cant]);
                $this->moverInventarioLinea($lineaInv, $idEmpresa, $idUsuario, $empresaConfig, $tipoMov,
                    'CAMBIO_PRODUCTO_CV', $idCambio, ($tipoMov === 'entrada' ? 'Entrada' : 'Salida') . " por Cambio de productos {$numero}");
            }

            $total += $totalLinea;
        }

        return $total;
    }

    /**
     * IVA vigente de un producto: [id_impuesto, porcentaje]. La tarifa ACTUAL del producto; si no
     * tiene, la de $idTarifaRespaldo (la de la línea de consignación) y, si tampoco, [null,
     * $porcentajeRespaldo]. Es la misma regla con la que Facturación de consignaciones factura
     * (ConsignacionFacturaService::normalizarDetalles), así que el registro que el cambio crea allí
     * queda con el mismo IVA que si se hubiera facturado esa unidad.
     */
    private function ivaDelProducto(int $idProducto, ?int $idTarifaRespaldo = null, float $porcentajeRespaldo = 0.0): array
    {
        $clave = $idProducto . '|' . (int) $idTarifaRespaldo;
        if (!array_key_exists($clave, $this->tarifasIva)) {
            $repo = new \App\repositories\modulos\ConsignacionFacturaRepository();
            $tar  = $idProducto > 0 ? $repo->getTarifaIvaProducto($idProducto) : null;
            if (!$tar && $idTarifaRespaldo) {
                $tar = $repo->getTarifaIvaById($idTarifaRespaldo);
            }
            $this->tarifasIva[$clave] = $tar ? [(int) $tar['id'], (float) $tar['porcentaje_iva']] : null;
        }
        return $this->tarifasIva[$clave] ?? [null, $porcentajeRespaldo];
    }

    // ─── EDITAR (solo Borrador; no mueve inventario) ──────────────────────────

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarCreacion($data, $this->entregasSinOrigenGuardadas($id, $idEmpresa));

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Cambio no encontrado.");
        }
        $this->validarPeriodoContableAlModificar(
            $cab['fecha_cambio'] ?? null,
            $data['fecha_cambio'] ?? null,
            $idEmpresa,
            'el cambio'
        );

        if (($cab['estado'] ?? '') !== 'Borrador') {
            throw new Exception("Solo se pueden editar cambios en estado Borrador.");
        }

        $idUsuario = (int) $data['id_usuario'];
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->deleteDetalles($id, $idEmpresa);

            $numero = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
            $totDev = $this->procesarLineas($id, $idEmpresa, $idUsuario, [], $data['devoluciones'] ?? [], 'devolucion', false, $numero, (int) $data['id_cliente']);
            $totEnt = $this->procesarLineas($id, $idEmpresa, $idUsuario, [], $data['entregas'] ?? [], 'entrega', false, $numero, (int) $data['id_cliente']);

            $this->repository->updateCabecera($id, $idEmpresa, [
                'fecha_cambio'       => $data['fecha_cambio'],
                'id_cliente'         => (int) $data['id_cliente'],
                'motivo'             => $data['motivo'] ?? null,
                'observaciones'      => $data['observaciones'] ?? null,
                'subtotal_devuelto'  => round($totDev, 6),
                'subtotal_entregado' => round($totEnt, 6),
                'diferencia'         => round($totEnt - $totDev, 6),
                'updated_by'         => $idUsuario,
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $id, $cab, $data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Entregas desde bodega o catálogo (sin origen) que el borrador tiene guardadas, por id de
     * línea: las únicas de ese tipo que CambioProductoCvRules acepta al volver a guardarlo.
     */
    private function entregasSinOrigenGuardadas(int $idCambio, int $idEmpresa): array
    {
        $out = [];
        foreach ($this->repository->getDetalles($idCambio, $idEmpresa) as $d) {
            if (($d['tipo_linea'] ?? '') === 'entrega' && trim((string) ($d['origen_tipo'] ?? '')) === '') {
                $out[(int) $d['id']] = $d;
            }
        }
        return $out;
    }

    // ─── ELIMINAR ─────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario, array $empresaConfig = []): void
    {
        $cabecera = $this->repository->find($id, $idEmpresa);
        if (!$cabecera) {
            throw new Exception("Cambio no encontrado.");
        }

        // Eliminar revierte inventario y anula el asiento del documento.
        $this->validarPeriodoContable(
            $cabecera["fecha_cambio"] ?? null,
            $idEmpresa,
            "No se puede eliminar el cambio porque su período contable está cerrado."
        );

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            if (($cabecera['estado'] ?? '') === 'Emitida') {
                $numero = ($cabecera['serie'] ?? '') . '-' . ($cabecera['secuencial'] ?? '');
                $this->reversarInventario($id, $idEmpresa, $idUsuario, $empresaConfig, "Reverso por Eliminación de Cambio {$numero}");
                $this->anularRegistrosFacturacion($id, $idEmpresa, $idUsuario);
            }

            $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $id, $cabecera, null);

            $db->commit();

            $this->anularAsientoSiExiste($id, $idEmpresa, $idUsuario);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─── CAMBIO DE ESTADO ─────────────────────────────────────────────────────

    /**
     * Cambia el estado (Borrador | Emitida | Anulada). Inventario ligado a Emitida:
     *  - Emitida → Borrador/Anulada: reversa ambos lados (entrada↔salida) y libera saldo.
     *  - Borrador/Anulada → Emitida: revalida saldo de devoluciones y re-aplica ambos lados.
     */
    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado, array $empresaConfig = []): void
    {
        $permitidos = ['Borrador', 'Emitida', 'Anulada'];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new Exception("Estado no válido.");
        }

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Cambio no encontrado.");
        }
        $actual = (string) ($cab['estado'] ?? '');
        if ($actual === $nuevoEstado) {
            return;
        }

        $wasActive  = ($actual === 'Emitida');
        $willActive = ($nuevoEstado === 'Emitida');
        $numero = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');

        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $detalles = $this->repository->getDetalles($id, $idEmpresa);

            if ($wasActive && !$willActive) {
                $this->reversarInventario($id, $idEmpresa, $idUsuario, $empresaConfig, "Reverso por cambio a {$nuevoEstado} del Cambio {$numero}");
                $this->anularRegistrosFacturacion($id, $idEmpresa, $idUsuario);
            } elseif (!$wasActive && $willActive) {
                // Revalidar saldo (excluyendo este cambio) antes de re-aplicar: devoluciones
                // contra su origen y entregas tomadas de una consignación contra su saldo.
                foreach ($detalles as $det) {
                    $cant = (float) $det['cantidad'];
                    if ($cant <= 0) continue;
                    $esDev    = (($det['tipo_linea'] ?? '') === 'devolucion');
                    $esConsig = !$esDev && strtoupper((string) ($det['origen_tipo'] ?? '')) === 'CONSIGNACION';
                    if (!$esDev && !$esConsig) continue;
                    $saldo = $esDev
                        ? $this->repository->getSaldoLineaOrigen((string) $det['origen_tipo'], (int) $det['id_origen_detalle'], $idEmpresa, $id)
                        : $this->repository->getSaldoLineaConsignacion((int) $det['id_origen_detalle'], $idEmpresa, $id);
                    if ($cant > $saldo + 1e-9) {
                        $nombre = $det['producto_nombre'] ?? 'Producto';
                        throw new Exception("No se puede volver a Emitir: \"{$nombre}\" supera el saldo disponible ({$saldo}).");
                    }
                }
                foreach ($detalles as $det) {
                    $tipoMov = (($det['tipo_linea'] ?? '') === 'devolucion') ? 'entrada' : 'salida';
                    $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, $tipoMov,
                        'CAMBIO_PRODUCTO_CV', $id, "Re-aplicación (Emitida) del Cambio {$numero}");
                }
                // Registros nuevos en Facturación de consignaciones (los anteriores quedaron anulados).
                $this->crearRegistrosFacturacion($id, $idEmpresa, $idUsuario);
            }
            // Borrador ↔ Anulada: sin movimiento.

            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_CAMBIO_PRODUCTO_CV', 'cambios_producto_cv', $id,
                ['estado' => $actual], ['estado' => $nuevoEstado]);

            $db->commit();

            if ($willActive) {
                $this->procesarAsientoSeguro($id, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);
            } elseif ($wasActive) {
                $this->anularAsientoSiExiste($id, $idEmpresa, $idUsuario);
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Reversa el inventario de ambos lados (devolucion→salida, entrega→entrada). */
    private function reversarInventario(int $id, int $idEmpresa, int $idUsuario, array $empresaConfig, string $obs): void
    {
        $detalles = $this->repository->getDetalles($id, $idEmpresa);
        foreach ($detalles as $det) {
            // Inverso del movimiento original.
            $tipoMov = (($det['tipo_linea'] ?? '') === 'devolucion') ? 'salida' : 'entrada';
            $this->moverInventarioLinea($det, $idEmpresa, $idUsuario, $empresaConfig, $tipoMov, 'CAMBIO_PRODUCTO_CV', $id, $obs);
        }
    }

    // ─── REGISTRO EN FACTURACIÓN DE CONSIGNACIONES ────────────────────────────

    /**
     * Registra en Facturación de consignaciones lo que el cambio entregó desde una consignación:
     * esas unidades quedan FACTURADAS en la factura de venta de la unidad devuelta con la que se
     * emparejan, sin crear factura nueva (ConsignacionFacturaService::crearRegistroDeCambio).
     *
     * Emparejamiento, el mismo del listado: la n-ésima entrega con la n-ésima devolución, en
     * orden de registro; las entregas que sobran van con la última devolución. Un registro por
     * factura de venta. Lo entregado desde bodega o catálogo no se registra (no es consignación).
     * Participa en la transacción del llamador: si el registro no se puede crear (p. ej. falta el
     * secuencial de Facturación de consignaciones en el punto de emisión), el cambio no se emite.
     */
    private function crearRegistrosFacturacion(int $idCambio, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repository->find($idCambio, $idEmpresa);
        if (!$cab) {
            return;
        }
        [$devoluciones, $entregas] = $this->separarLineas($this->repository->getDetalles($idCambio, $idEmpresa));

        $grupos = [];
        foreach ($entregas as $k => $ent) {
            if (strtoupper((string) ($ent['origen_tipo'] ?? '')) !== 'CONSIGNACION' || !$devoluciones) {
                continue;
            }
            $factura = $this->resolverFacturaDeVenta($devoluciones[min($k, count($devoluciones) - 1)], $idEmpresa);
            $clave   = (int) ($factura['id_factura'] ?? 0) . '|' . (string) ($factura['numero_factura'] ?? '');
            $grupos[$clave]['factura']  = $factura;
            $grupos[$clave]['lineas'][] = $ent;
        }
        if (!$grupos) {
            return;
        }

        $numeroCambio = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
        if (!CambioProductoCvRepository::registroFacturacionDisponible()) {
            throw new Exception("El cambio {$numeroCambio} entrega productos desde consignación y deben quedar registrados en Facturación de consignaciones, pero la base de datos aún no lo admite: aplique database/migrations/20260916_facturacion_cv_registro_cambio.sql.");
        }

        $facturacion = $this->getFacturacionService();
        foreach ($grupos as $g) {
            try {
                $facturacion->crearRegistroDeCambio([
                    'id_empresa'         => $idEmpresa,
                    'id_usuario'         => $idUsuario,
                    'tipo_ambiente'      => (string) ($cab['tipo_ambiente'] ?? '1'),
                    'id_punto_emision'   => (int) ($cab['id_punto_emision'] ?? 0),
                    'fecha_emision'      => $cab['fecha_cambio'] ?? date('Y-m-d'),
                    'id_cliente'         => (int) $cab['id_cliente'],
                    'id_vendedor'        => $g['factura']['id_vendedor'] ?? null,
                    'id_factura'         => $g['factura']['id_factura'] ?? null,
                    'numero_factura'     => $g['factura']['numero_factura'] ?? null,
                    'id_cambio_producto' => $idCambio,
                    'numero_cambio'      => $numeroCambio,
                    'lineas'             => $g['lineas'],
                ]);
            } catch (\Throwable $e) {
                throw new Exception("No se pudo registrar en Facturación de consignaciones lo entregado desde consignación en el cambio {$numeroCambio}: " . $e->getMessage(), 0, $e);
            }
        }
    }

    /** Anula los registros en Facturación de consignaciones del cambio (deja de estar Emitida o se elimina). */
    private function anularRegistrosFacturacion(int $idCambio, int $idEmpresa, int $idUsuario): void
    {
        $this->getFacturacionService()->anularRegistrosDeCambio($idCambio, $idEmpresa, $idUsuario);
    }

    /**
     * Factura de venta de la que viene una unidad DEVUELTA: la de su factura de consignación o, si
     * viene de un cambio anterior, la de la unidad que ese cambio entregó (la factura donde quedó
     * registrada o, si salió de bodega, la de la devolución con la que se emparejó en ese cambio).
     * Devuelve id_factura, numero_factura e id_vendedor, vacíos si no se encuentra.
     */
    private function resolverFacturaDeVenta(array $dev, int $idEmpresa, int $nivel = 0): array
    {
        $vacio  = ['id_factura' => null, 'numero_factura' => null, 'id_vendedor' => null];
        $origen = strtoupper((string) ($dev['origen_tipo'] ?? ''));

        if ($origen === 'FACTURA') {
            // Factura de consignación o, en devoluciones anteriores al 16-09-2026, factura de venta directa.
            return $this->repository->getFacturaVentaDeLinea((int) $dev['id_origen'], (int) ($dev['id_origen_detalle'] ?? 0),
                (int) ($dev['id_producto'] ?? 0), $idEmpresa) ?? $vacio;
        }
        if ($origen !== 'CAMBIO' || $nivel >= 20) {
            return $vacio;
        }

        $idEntregaPrevia = (int) ($dev['id_origen_detalle'] ?? 0);
        $registrada = $this->repository->getFacturaVentaDeEntregaRegistrada($idEntregaPrevia, $idEmpresa);
        if ($registrada) {
            return $registrada;
        }

        [$devPrevias, $entPrevias] = $this->separarLineas($this->repository->getDetalles((int) $dev['id_origen'], $idEmpresa));
        foreach ($entPrevias as $k => $ent) {
            if ((int) $ent['id'] === $idEntregaPrevia && $devPrevias) {
                return $this->resolverFacturaDeVenta($devPrevias[min($k, count($devPrevias) - 1)], $idEmpresa, $nivel + 1);
            }
        }
        return $vacio;
    }

    /** Devoluciones y entregas del cambio, cada grupo en orden de registro (id): base del emparejamiento. */
    private function separarLineas(array $detalles): array
    {
        $porId = static fn(array $a, array $b): int => (int) $a['id'] <=> (int) $b['id'];
        $dev = array_values(array_filter($detalles, static fn($d) => ($d['tipo_linea'] ?? '') === 'devolucion'));
        $ent = array_values(array_filter($detalles, static fn($d) => ($d['tipo_linea'] ?? '') === 'entrega'));
        usort($dev, $porId);
        usort($ent, $porId);
        return [$dev, $ent];
    }

    private function getFacturacionService(): ConsignacionFacturaService
    {
        return new ConsignacionFacturaService(
            new \App\repositories\modulos\ConsignacionFacturaRepository(),
            new \App\Rules\modulos\ConsignacionFacturaRules(),
            $this->logService
        );
    }

    // ─── ASIENTO CONTABLE (a costo) ───────────────────────────────────────────

    /** Asiento sugerido: reingreso de lo devuelto y salida de lo entregado, a costo. */
    public function obtenerAsientoSugerido(int $idEmpresa, int $idCambio): array
    {
        return (new AsientoBuilderService())->generarAsientoCambioProductoCv($idEmpresa, $idCambio);
    }

    /** ¿Cambio migrado del sistema anterior? No lleva asiento contable (ver procesarAsientoContable). */
    public function esMigrado(int $idCambio, int $idEmpresa): bool
    {
        return $this->repository->esMigrado($idCambio, $idEmpresa);
    }

    private function procesarAsientoSeguro(int $idCambio, array $data): void
    {
        try {
            $this->procesarAsientoContable($idCambio, $data);
        } catch (\Throwable $e) {
            error_log("[CambioProductoCV] Asiento no generado para $idCambio: " . $e->getMessage());
        }
    }

    /**
     * Genera el asiento del cambio por sincronización masiva (control de asientos de Estados
     * Financieros / Auditoría Contable). Toma empresa y usuario de la propia cabecera y PROPAGA
     * la excepción si no se puede generar —al revés que procesarAsientoSeguro()—, para que la
     * corrida lo reporte como pendiente con su motivo.
     * Solo los cambios 'Emitida' tienen impacto contable; procesarAsientoContable() ya lo valida.
     */
    public function procesarAsientoContablePorSincronizacion(int $idCambio): void
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id_empresa, created_by FROM cambios_producto_cv WHERE id = ? AND eliminado = false");
        $st->execute([$idCambio]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $this->procesarAsientoContable($idCambio, [
            'id_empresa' => (int) $row['id_empresa'],
            'id_usuario' => (int) ($row['created_by'] ?? 0),
        ]);
    }

    public function procesarAsientoContable(int $idCambio, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $cab = $this->repository->find($idCambio, $idEmpresa);
        if (!$cab) return;
        if (($cab['estado'] ?? '') !== 'Emitida') {
            return;
        }
        // Un cambio migrado del sistema anterior no lleva asiento por ninguna vía (sincronización,
        // Auditoría, pestaña Asiento contable, cambio de estado o guardado): ese sistema no
        // contabilizaba los cambios de productos.
        if ($this->repository->esMigrado($idCambio, $idEmpresa)) {
            return;
        }

        $numDoc = ($cab['serie'] ?? '') . '-' . ($cab['secuencial'] ?? '');
        $fecha  = $data['fecha_cambio'] ?? ($cab['fecha_cambio'] ?? date('Y-m-d'));

        $fuente = (!empty($data['asiento_detalles']) && is_array($data['asiento_detalles'])) ? $data['asiento_detalles'] : [];
        $manualCompleto = !empty($fuente);
        foreach ($fuente as $f) {
            if ((int) ($f['id_cuenta_contable'] ?? 0) <= 0) { $manualCompleto = false; break; }
        }
        if (!$manualCompleto) {
            $fuente = $this->obtenerAsientoSugerido($idEmpresa, $idCambio);
        }

        $detalles = [];
        $totDebe = 0.0; $totHaber = 0.0;
        foreach ($fuente as $d) {
            $idCuenta = (int) ($d['id_cuenta_contable'] ?? 0);
            if ($idCuenta <= 0) { $detalles = []; break; }
            $debe  = round((float) ($d['debe'] ?? 0), 2);
            $haber = round((float) ($d['haber'] ?? 0), 2);
            $totDebe += $debe; $totHaber += $haber;
            $detalles[] = [
                'id_cuenta_contable'   => $idCuenta,
                'debe'                 => $debe,
                'haber'                => $haber,
                'referencia_detalle'   => ($d['referencia_detalle'] ?? '') ?: ('Cambio productos # ' . $numDoc),
                'documento_referencia' => 'Cambio productos # ' . $numDoc,
                'id_entidad'           => (int) ($cab['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        if (empty($detalles) || abs($totDebe - $totHaber) >= 0.005 || ($totDebe <= 0 && $totHaber <= 0)) {
            return;
        }

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        $previo = $asientoService->getAsientoPorOrigen('cambio_producto_cv', $idCambio, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $clienteNombre = $cab['cliente_nombre'] ?? 'Cliente';
        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'cambio_producto',
            'numero_comprobante'   => '',
            'concepto'             => 'Cambio de productos # ' . $numDoc . ' - Cliente: ' . $clienteNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'cambio_producto_cv',
            'id_referencia_origen' => $idCambio,
            'observaciones'        => $data['observaciones'] ?? ($cab['observaciones'] ?? null),
        ];

        $idGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idCambio, $idEmpresa, $idGenerado);
    }

    private function anularAsientoSiExiste(int $idCambio, int $idEmpresa, int $idUsuario): void
    {
        try {
            $cab = $this->repository->find($idCambio, $idEmpresa);
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
            if ($idAsiento <= 0) return;
            $asientoService = new AsientoContableService(
                new \App\repositories\modulos\AsientoContableRepository(),
                new \App\Rules\modulos\AsientoContableRules(),
                $this->logService
            );
            $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
            $this->repository->updateAsientoContable($idCambio, $idEmpresa, null);
        } catch (\Throwable $e) {
            // Un período cerrado debe abortar: si no, el documento quedaría anulado con su
            // asiento aún vigente (descuadre silencioso).
            if (stripos($e->getMessage(), 'contable cerrado') !== false) {
                throw $e;
            }
            error_log("[CambioProductoCV] No se pudo anular el asiento del cambio $idCambio: " . $e->getMessage());
        }
    }

    // ─── Helpers de inventario ────────────────────────────────────────────────

    /**
     * Aplica un movimiento de inventario para una línea (entrada o salida),
     * valorado al costo promedio del producto en su bodega.
     */
    private function moverInventarioLinea(array $det, int $idEmpresa, int $idUsuario, array $empresaConfig, string $tipo, string $refTipo, int $refId, string $obs): void
    {
        $cant = (float) $det['cantidad'];
        $idBodega = (int) ($det['id_bodega'] ?? 0);
        if ($cant <= 0 || $idBodega <= 0) return;

        // Entrega tomada de una consignación: la mercadería ya salió de bodega con la
        // consignación (kardex CONSIGNACION_VENTA) y está en poder del cliente. Aquí solo
        // se consume el saldo de esa consignación; volver a dar salida duplicaría la baja
        // de stock (y el reverso al anular, la subiría de más).
        if (($det['tipo_linea'] ?? '') === 'entrega' && strtoupper((string) ($det['origen_tipo'] ?? '')) === 'CONSIGNACION') return;

        if (!$this->afectaInventario($det, $empresaConfig)) return;

        $idProducto  = (int) $det['id_producto'];
        $this->inventarioRepo->lockStock($idProducto, $idBodega, $idEmpresa);
        $stockActual = $this->inventarioRepo->getStockActual($idProducto, $idBodega, $idEmpresa);
        $delta       = ($tipo === 'entrada') ? $cant : -$cant;
        $nuevoStock  = $stockActual + $delta;

        $costoU = $this->inventarioRepo->getCostoPromedio($idProducto, $idBodega, $idEmpresa);

        $this->inventarioRepo->registrarMovimiento([
            'id_empresa'      => $idEmpresa,
            'id_producto'     => $idProducto,
            'id_bodega'       => $idBodega,
            'tipo_movimiento' => $tipo,
            'referencia_tipo' => $refTipo,
            'referencia_id'   => $refId,
            'cantidad'        => $delta,
            'costo_unitario'  => $costoU,
            'costo_total'     => round($costoU * $cant, 6),
            'stock_anterior'  => $stockActual,
            'stock_posterior' => $nuevoStock,
            'numero_lote'     => (isset($det['lote']) && $det['lote'] !== '') ? $det['lote'] : null,
            'fecha_caducidad' => (isset($det['fecha_caducidad']) && $det['fecha_caducidad'] !== '') ? $det['fecha_caducidad'] : null,
            'nup'             => (isset($det['nup']) && $det['nup'] !== '') ? $det['nup'] : null,
            'observaciones'   => $obs,
            'id_usuario'      => $idUsuario,
        ]);

        $this->inventarioRepo->actualizarStock($idProducto, $idBodega, $idEmpresa, $nuevoStock, $idUsuario);
    }

    /**
     * Determina si una línea debe mover inventario: producto inventariable, no servicio (02)
     * y con el control de inventario de facturación activo.
     */
    private function afectaInventario(array $linea, array $empresaConfig): bool
    {
        $esInv = (($linea['inventariable'] ?? null) == true || ($linea['inventariable'] ?? null) === 'true' || ($linea['inventariable'] ?? null) == 1 || ($linea['inventariable'] ?? null) === 't')
                 && (($linea['tipo_produccion'] ?? '01') !== '02');
        $soloStockPos = (($empresaConfig['facturacion_inventario'] ?? true) === 'true' || ($empresaConfig['facturacion_inventario'] ?? true) === true);
        return $esInv && $soloStockPos;
    }
}
