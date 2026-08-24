<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Rules\modulos\FacturaVentaRules;
use App\Rules\modulos\ProductoRules;
use App\repositories\modulos\CargaFacturasRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ProductoRepository;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;

/**
 * Aplica una carga de facturas ya validada.
 *
 * Escribe SIEMPRE a través de los servicios de cada módulo —FacturaVentaService
 * y ProductoService— para conservar sus reglas de negocio, sus transacciones, el
 * movimiento de inventario y su auditoría. Aquí no hay una sola sentencia SQL.
 *
 * Los clientes NO se crean: deben existir antes, y la validación ya bloquea las
 * facturas cuyo cliente no esté registrado.
 *
 * Se procesa FACTURA A FACTURA, cada una con su propia transacción abierta ANTES
 * de pedir el secuencial y mantenida hasta el INSERT final (CLAUDE.md §8: el
 * advisory lock del secuencial se libera solo al COMMIT/ROLLBACK). No se puede
 * envolver todo en una transacción externa porque el commit de la primera
 * factura cerraría la de las demás. A cambio, un fallo aislado no tumba la carga
 * completa: se informa y se sigue con la siguiente.
 */
class CargaFacturasAplicacionService
{
    private CargaFacturasRepository $repository;
    private LogSistemaService $logService;
    private EmpresaRepository $empresaRepository;

    /** Config de empresa + establecimiento, cacheada por id de establecimiento. */
    private array $empresaConfigCache = [];

    /** Datos base de la empresa (una sola consulta por carga). */
    private ?array $empresaBase = null;

    public function __construct(
        CargaFacturasRepository $repository,
        LogSistemaService $logService,
        ?EmpresaRepository $empresaRepository = null
    ) {
        $this->repository        = $repository;
        $this->logService        = $logService;
        $this->empresaRepository = $empresaRepository ?? new EmpresaRepository();
    }

    /**
     * @param array $informe Salida de CargaFacturasValidacionService::validar().
     * @return array Resultado de la aplicación.
     */
    public function aplicar(array $informe, int $idEmpresa, int $idUsuario): array
    {
        $resultado = [
            'creadas'           => 0,
            'omitidas'          => 0,
            'fallidas'          => 0,
            'productos_creados' => 0,
            'total_facturado'   => 0.0,
            'detalle'           => [],
        ];

        // ── Paso 0: separar lo aplicable de lo bloqueado ─────────────────────
        $aplicables = [];
        foreach ($informe['facturas'] ?? [] as $clave => $f) {
            if (!empty($f['errores'])) {
                $resultado['omitidas']++;
                $resultado['detalle'][] = [
                    'clave'   => $clave,
                    'estado'  => 'omitida',
                    'numero'  => '',
                    'mensaje' => 'Tiene errores de validación.',
                ];
                continue;
            }
            $aplicables[$clave] = $f;
        }

        if (!$aplicables) {
            return $resultado;
        }

        // ── Paso 1: productos que faltan ─────────────────────────────────────
        // Van primero, cada uno con su transacción, para que las facturas del
        // paso 2 puedan referenciarlos por id.
        $idsProductos = $this->crearProductosFaltantes($informe['productos_nuevos'] ?? [], $idEmpresa, $idUsuario, $resultado);

        // ── Paso 2: una factura por transacción ──────────────────────────────
        foreach ($aplicables as $clave => $f) {
            try {
                $numero = $this->crearFactura($f, $idsProductos, $idEmpresa, $idUsuario);

                $resultado['creadas']++;
                $resultado['total_facturado'] += $f['importe_total'];
                $resultado['detalle'][] = [
                    'clave'   => $clave,
                    'estado'  => 'creada',
                    'numero'  => $numero,
                    'mensaje' => '',
                ];
            } catch (\Throwable $e) {
                $resultado['fallidas']++;
                $resultado['detalle'][] = [
                    'clave'   => $clave,
                    'estado'  => 'error',
                    'numero'  => '',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        $resultado['total_facturado'] = round($resultado['total_facturado'], 2);

        // Auditoría de la carga como bloque (cada factura ya registra su propio
        // CREAR sobre ventas_cabecera desde FacturaVentaService).
        //
        // Además de auditar, esta entrada ES el control de cargas repetidas: lleva
        // el hash del archivo, y la validación lo consulta para impedir que el
        // mismo libro se aplique dos veces. Por eso se registra aunque falle todo
        // lo demás — pero solo si se creó al menos una factura: un archivo que no
        // llegó a crear nada debe poder reintentarse.
        if ($resultado['creadas'] > 0) {
            $numeros = array_values(array_filter(array_map(
                static fn($d) => $d['estado'] === 'creada' ? $d['numero'] : null,
                $resultado['detalle']
            )));

            try {
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'CARGA_MASIVA_FACTURAS',
                    'ventas_cabecera',
                    null,
                    null,
                    [
                        'hash_archivo'      => (string) ($informe['hash_archivo'] ?? ''),
                        'creadas'           => $resultado['creadas'],
                        'omitidas'          => $resultado['omitidas'],
                        'fallidas'          => $resultado['fallidas'],
                        'productos_creados' => $resultado['productos_creados'],
                        'total_facturado'   => $resultado['total_facturado'],
                        // Se listan los primeros para poder nombrarlos si alguien
                        // reintenta el mismo archivo; el detalle completo ya está
                        // en el log de cada factura.
                        'numeros'           => implode(', ', array_slice($numeros, 0, 20))
                            . (count($numeros) > 20 ? ' …' : ''),
                    ]
                );
            } catch (\Throwable $e) {
                // La auditoría del bloque no debe tumbar una carga ya escrita, pero
                // sin ella se pierde la protección contra recargar el mismo archivo.
                error_log('[CargaFacturas] No se pudo registrar la auditoría del bloque (se pierde el '
                    . 'control de archivo repetido): ' . $e->getMessage());
            }
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Paso 1: productos que faltan
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea los productos cuyo código no existía, según la columna TIPO.
     *
     * - Servicio (02): sin control de inventario.
     * - Producto (01): CON control de inventario y SIN existencias — nace en
     *   cero. El stock se ingresa aparte, desde Cargas de Inventario; aquí no se
     *   siembra nada, porque inventar existencias que nadie compró falsearía el
     *   kardex y el costo.
     *
     * Que un Producto nuevo no se pueda facturar en establecimientos que exigen
     * stock positivo ya lo advierte la validación, antes de llegar aquí.
     *
     * @return array<string,int> código en minúsculas => id_producto
     */
    private function crearProductosFaltantes(array $productos, int $idEmpresa, int $idUsuario, array &$resultado): array
    {
        if (!$productos) {
            return [];
        }

        $productoRepo = new ProductoRepository();
        $servicio     = new ProductoService(
            $productoRepo,
            new ProductoRules(),
            $this->logService,
            new InventarioService(new InventarioRepository(), $this->logService)
        );

        $tarifas = $this->repository->getMapaTarifasIva();
        $ids = [];

        foreach ($productos as $p) {
            // La clave del array puede haberse convertido a int si el código es
            // numérico ("12345" => 12345); el valor manda.
            $claveCodigo = mb_strtolower((string) $p['codigo']);
            $tipo        = $p['tipo_produccion'] ?? CargaFacturasEsquema::TIPO_SERVICIO;

            try {
                $ids[$claveCodigo] = $servicio->crear([
                    'id_empresa'      => $idEmpresa,
                    'id_usuario'      => $idUsuario,
                    'codigo'          => $p['codigo'],
                    'nombre'          => $p['nombre'],
                    'precio_base'     => $p['precio_base'],
                    'tipo_produccion' => $tipo,
                    // Un bien lleva control de inventario; un servicio nunca (el
                    // propio ProductoService lo fuerza a false para el tipo 02).
                    // No se pasa 'inventarios': el producto nace con stock cero.
                    'inventariable'   => ($tipo === CargaFacturasEsquema::TIPO_BIEN),
                    'tarifa_iva'      => $tarifas[$p['codigo_iva']]['id'] ?? null,
                    'status'          => true,
                ]);
                $resultado['productos_creados']++;
            } catch (\Throwable $e) {
                $resultado['detalle'][] = [
                    'clave'   => 'Producto ' . $p['codigo'],
                    'estado'  => 'error',
                    'numero'  => '',
                    'mensaje' => 'No se pudo crear el producto: ' . $e->getMessage(),
                ];
            }
        }

        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Paso 2: la factura
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea una factura de venta en estado borrador.
     * @return string Número asignado (EST-PTO-SECUENCIAL).
     */
    private function crearFactura(array $f, array $idsProductos, int $idEmpresa, int $idUsuario): string
    {
        $idCliente = $f['id_cliente'] ?? null;
        if (!$idCliente) {
            throw new \RuntimeException('El cliente ' . $f['identificacion'] . ' no está registrado.');
        }

        $empresaConfig = $this->getEmpresaConfig($idEmpresa, (int) $f['id_establecimiento']);
        $detalles      = $this->construirDetalles($f, $idsProductos);

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            // La transacción ya está abierta: el candado de obtenerSiguienteSecuencial()
            // se libera al COMMIT/ROLLBACK y protege hasta el INSERT final.
            $sec        = (new SecuencialService())->obtenerSiguienteSecuencial((int) $f['id_punto_emision'], 'Facturas de venta');
            $secuencial = $sec['formateado'];

            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_usuario'          => $idUsuario,
                'empresa_config'      => $empresaConfig,
                'id_establecimiento'  => (int) $f['id_establecimiento'],
                'id_punto_emision'    => (int) $f['id_punto_emision'],
                'establecimiento'     => $f['establecimiento'],
                'punto_emision'       => $f['punto_emision'],
                'secuencial'          => $secuencial,
                'fecha_emision'       => $f['fecha_emision'],
                'id_cliente'          => (int) $idCliente,
                'id_vendedor'         => $f['id_vendedor'],
                'dias_credito'        => (int) $f['dias_credito'],
                'plazo'               => (int) $f['dias_credito'],
                'moneda'              => 'DOLAR',
                'estado'              => 'borrador',
                'observaciones'       => $f['observaciones'],
                'id_bodega'           => (int) ($f['id_bodega'] ?? 0),
                'total_sin_impuestos' => $f['total_sin_impuestos'],
                'total_descuento'     => $f['total_descuento'],
                'total_ice'           => 0,
                'propina'             => $f['propina'],
                'importe_total'       => $f['importe_total'],
                'detalles'            => $detalles,
                'pagos'               => $f['pagos'],
                'info_adicional'      => $f['info_adicional'],
            ];

            $facturaService = new FacturaVentaService(
                new FacturaVentaRepository(),
                new FacturaVentaRules(),
                $this->logService
            );

            $idFactura = $facturaService->crear($payload);

            if ($managedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // El XML se genera FUERA de la transacción. FacturaVentaService::crear()
        // solo lo hace cuando él controla la transacción; aquí la controlamos
        // nosotros, así que nos toca llamarlo después del commit.
        try {
            $facturaService->generarYGuardarXml($idFactura, $empresaConfig);
        } catch (\Throwable $e) {
            // La factura ya existe; el XML se puede regenerar desde el módulo.
            error_log('[CargaFacturas] Factura ' . $idFactura . ' creada sin XML: ' . $e->getMessage());
        }

        return $f['establecimiento'] . '-' . $f['punto_emision'] . '-' . $secuencial;
    }

    /**
     * Traduce las líneas del informe al formato que espera FacturaVentaService,
     * resolviendo los productos que se acaban de crear.
     */
    private function construirDetalles(array $f, array $idsProductos): array
    {
        $detalles = [];

        foreach ($f['detalles'] as $d) {
            $idProducto = $d['id_producto'];
            if ($idProducto === null && !$d['es_libre']) {
                $idProducto = $idsProductos[mb_strtolower((string) $d['codigo_producto'])] ?? null;
                if ($idProducto === null) {
                    throw new \RuntimeException(
                        'El producto "' . $d['codigo_producto'] . '" no está disponible (no se pudo crear).'
                    );
                }
            }

            // Un ítem libre no lleva lote/caducidad/NUP ni bodega: no mueve inventario.
            $esLibre = $d['es_libre'];

            $detalles[] = [
                'id_producto'               => $idProducto,
                'es_libre'                  => $esLibre ? '1' : '0',
                'id_bodega'                 => $esLibre ? null : ($f['id_bodega'] ?: null),
                'codigo_principal'          => $d['codigo_producto'] !== '' ? $d['codigo_producto'] : null,
                'descripcion'               => $d['descripcion'],
                'nombre'                    => $d['descripcion'],
                'info_adicional'            => $d['info_adicional'] !== '' ? $d['info_adicional'] : null,
                'cantidad'                  => $d['cantidad'],
                'precio_unitario'           => $d['precio_unitario'],
                'descuento'                 => $d['descuento'],
                'precio_total_sin_impuesto' => $d['precio_total_sin_impuesto'],
                'porcentaje_iva'            => $d['porcentaje_iva'],
                'id_tarifa_iva'             => $d['id_tarifa_iva'],
                'codigo_porcentaje'         => $d['codigo_iva'],
                'lote'                      => $d['lote'] !== '' ? $d['lote'] : null,
                'caducidad'                 => $d['caducidad'] !== '' ? $d['caducidad'] : null,
                'nup'                       => $d['nup'] !== '' ? $d['nup'] : null,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $d['codigo_iva'],
                    'tarifa'            => $d['porcentaje_iva'],
                    'base_imponible'    => $d['precio_total_sin_impuesto'],
                    'valor'             => $d['valor_iva'],
                ]],
            ];
        }

        return $detalles;
    }

    /**
     * Datos de la empresa fusionados con la configuración del establecimiento de
     * la factura (decimales, ambiente, etc.), igual que hace el controlador de
     * Factura de Venta al guardar desde el modal.
     */
    private function getEmpresaConfig(int $idEmpresa, int $idEstablecimiento): array
    {
        if (isset($this->empresaConfigCache[$idEstablecimiento])) {
            return $this->empresaConfigCache[$idEstablecimiento];
        }

        if ($this->empresaBase === null) {
            $this->empresaBase = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        }

        $config = $this->empresaBase;
        if ($idEstablecimiento > 0) {
            $estConfig = $this->empresaRepository->getEstablecimientoConfig($idEstablecimiento);
            if ($estConfig) {
                $config = array_merge($config, $estConfig);
            }
        }

        return $this->empresaConfigCache[$idEstablecimiento] = $config;
    }
}
