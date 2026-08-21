<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsientoProgramadoRepository;
use App\repositories\modulos\CosteoVentaSeguimientoRepository;
use Exception;

/**
 * Servicio centralizado y modular para la generación y armado de asientos contables personalizados
 * para cada módulo operativo del sistema (Factura de Venta, Compras, Retenciones, etc.).
 * 
 * Sigue estrictamente la arquitectura Controller -> Service -> Rules -> Repository.
 */
class AsientoBuilderService
{
    /**
     * Diferencia máxima (en valor absoluto) que se acepta como redondeo y se lleva a la
     * cuenta de Ajuste por redondeo. Un descuadre mayor se considera error real de
     * configuración (cuenta/impuesto faltante) y lanza excepción.
     */
    private const TOPE_AJUSTE_REDONDEO = 0.03;

    /**
     * tipo_documento (egresos_detalle) => código (asientos_tipo, tipo 'nomina') cuyo pasivo
     * ya se provisiona mes a mes en el rol y que, al pagarse por Egresos, debe cancelarse
     * directo contra esa cuenta específica (no contra la cuenta genérica del concepto).
     */
    private const CONTRAPARTIDA_ESPECIFICA_NOMINA = [
        'DECIMO_TERCERO' => 'DECIMOTERCEROPORPAGARNOMINA',
        'DECIMO_CUARTO'  => 'DECIMOCUARTOPORPAGARNOMINA',
    ];

    private const NOMBRE_CONTRAPARTIDA_NOMINA = [
        'DECIMO_TERCERO' => 'Décimo Tercero por Pagar',
        'DECIMO_CUARTO'  => 'Décimo Cuarto por Pagar',
    ];

    /**
     * tipo_documento (egresos_detalle) de la CARTERA de compras (documentos elegidos en el
     * selector "Facturas/Liquidaciones pendientes de pago") => modulo_origen con el que ese
     * documento guardó SU PROPIO asiento al registrarse. Se usa para leer de ahí la distribución
     * real de su Cuenta por Pagar (puede estar repartida por línea/Producto/Categoría/Marca —
     * ver contrapartidaCarteraCompras) en vez de asumir una única cuenta global.
     */
    private const MODULO_ORIGEN_CARTERA_COMPRAS = [
        'COMPRA'      => 'compra',
        'LIQUIDACION' => 'liquidacion_compra',
    ];

    private const NOMBRE_CONTRAPARTIDA_CARTERA_COMPRAS = [
        'COMPRA'      => 'Cuentas por Pagar (Factura de Compra)',
        'LIQUIDACION' => 'Cuentas por Pagar (Liquidación de Compra)',
    ];

    /**
     * Mismo mecanismo que MODULO_ORIGEN_CARTERA_COMPRAS, pero para la CARTERA de ventas
     * (ingresos_detalle.tipo_documento IN ('FACTURA','RECIBO')): modulo_origen con el que ese
     * documento guardó su propio asiento (Cuenta por Cobrar en el Debe, posiblemente repartida
     * por línea/Cliente/Producto — misma cascada que Compras).
     */
    private const MODULO_ORIGEN_CARTERA_VENTAS = [
        'FACTURA' => 'factura_venta',
        'RECIBO'  => 'recibo_venta',
    ];

    private const NOMBRE_CONTRAPARTIDA_CARTERA_VENTAS = [
        'FACTURA' => 'Cuentas por Cobrar (Factura de Venta)',
        'RECIBO'  => 'Cuentas por Cobrar (Recibo de Venta)',
    ];

    /**
     * tipo_documento => código (asientos_tipo) del slot de cartera con el que ese documento
     * resolvió su Cuenta por Cobrar/Pagar. Sirve para quedarse SOLO con esas cuentas al leer el
     * asiento del documento: el Debe de una venta no es únicamente la cartera —lleva también
     * Costo de Ventas (5.x) y Descuento en ventas (4.x, deudor)— y prorratear el cobro sobre
     * todas esas líneas acreditaba cuentas de resultado dejando cartera sin cancelar.
     */
    private const SLOT_CARTERA_VENTAS = [
        'FACTURA' => 'PORCOBRARFACTURAVENTA',
        'RECIBO'  => 'PORCOBRARRECIBOVENTA',
    ];

    private const SLOT_CARTERA_COMPRAS = [
        'COMPRA'      => 'PORPAGARFACTURACOMPRA',
        'LIQUIDACION' => 'PORPAGARFACTURACOMPRA',
    ];

    /** Caché de cuentasDelSlot() por "empresa:codigo": se consulta una vez por lote, no por documento. */
    private array $cacheCuentasSlot = [];

    private AsientoProgramadoRepository $programadoRepo;
    private CosteoVentaSeguimientoRepository $costeoRepo;

    public function __construct()
    {
        $this->programadoRepo = new AsientoProgramadoRepository();
        $this->costeoRepo = new CosteoVentaSeguimientoRepository();
    }

    /**
     * Genera la estructura y distribución sugerida del asiento contable para un documento específico,
     * adaptándose al método de contabilización preferido de la empresa (General, Cliente, Producto, etc.).
     *
     * @param int $idEmpresa ID de la empresa activa.
     * @param string $tipoAsiento Tipo de asiento del documento (ej. 'ventas_factura', 'adquisiciones_compras').
     * @param array $documentData Datos económicos y de referencia del documento.
     * @return array Detalles del asiento generado (cuentas, importes Debe/Haber y referencias).
     * @throws Exception Si ocurre algún error en la resolución de cuentas.
     */
    public function generarAsientoSugerido(int $idEmpresa, string $tipoAsiento, array $documentData): array
    {
        // 1. Obtener la plantilla de origen (reglas base) para el tipo de asiento especificado
        $reglasBase = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, $tipoAsiento);
        if (empty($reglasBase)) {
            return [];
        }

        // 2. CASCADA (Opción 2: la ENTIDAD del documento manda).
        //    Cliente (ventas) o Proveedor (compras): si tiene reglas, sus cuentas sobreescriben los
        //    conceptos que configuró; lo no configurado queda en General y NO se reparte por línea.
        //    Si la entidad no tiene reglas, recién ahí se reparte por línea (producto → categoría → marca).
        $entidadTipo = match ($tipoAsiento) {
            'ventas_factura', 'recibos_venta' => 'cliente',
            'adquisiciones_compras' => 'proveedor',
            default                 => '',
        };

        // Asegurar el id de la entidad en documentData (si no vino, leerlo de la cabecera).
        if ($entidadTipo === 'cliente' && empty($documentData['id_cliente']) && !empty($documentData['id_recibo'])) {
            $documentData['id_cliente'] = $this->buscarEntidadDocumento('recibos_venta_cabecera', 'id_cliente', (int)$documentData['id_recibo']);
        } elseif ($entidadTipo === 'cliente' && empty($documentData['id_cliente']) && !empty($documentData['id_venta'])) {
            $documentData['id_cliente'] = $this->buscarEntidadDocumento('ventas_cabecera', 'id_cliente', (int)$documentData['id_venta']);
        } elseif ($entidadTipo === 'proveedor' && empty($documentData['id_proveedor'])) {
            $idCompra = (int)($documentData['id_compra'] ?? $documentData['id'] ?? 0);
            if ($idCompra > 0) {
                $documentData['id_proveedor'] = $this->buscarEntidadDocumento('compras_cabecera', 'id_proveedor', $idCompra);
            }
        }

        $customAccounts = [];
        $entidadTieneReglas = false;
        if ($entidadTipo !== '') {
            $customAccounts = $this->resolverCuentasPorMetodo($idEmpresa, $tipoAsiento, $entidadTipo, $documentData);
            $entidadTieneReglas = !empty($customAccounts);
        }

        // 3. Combinar la plantilla base con las cuentas de la entidad (fallback a General).
        foreach ($reglasBase as &$r) {
            $idAsientoTipo = (int)$r['id_asiento_tipo'];
            if (isset($customAccounts[$idAsientoTipo])) {
                $r['id_cuenta'] = $customAccounts[$idAsientoTipo]['id_cuenta'];
                $r['cuenta_codigo'] = $customAccounts[$idAsientoTipo]['cuenta_codigo'];
                $r['cuenta_nombre'] = $customAccounts[$idAsientoTipo]['cuenta_nombre'];
            }
        }
        unset($r);

        // 4. Solo se reparte por línea (producto/categoría/marca) cuando la entidad NO tiene reglas (Opción 2).
        $documentData['__reparte_por_linea__'] = !$entidadTieneReglas;
        $asientoResult = match ($tipoAsiento) {
            'ventas_factura' => $this->armarDistribucionVentasFactura($reglasBase, $documentData),
            // Recibos de Venta tiene su propio catálogo de cuentas (independiente de ventas_factura)
            // pero sus datos viven en tablas separadas (recibos_venta_*, no ventas_*) — ver ReciboVentaService.
            'recibos_venta' => $this->armarDistribucionRecibosVenta($reglasBase, $documentData),
            'adquisiciones_compras' => $this->armarDistribucionCompras($reglasBase, $documentData),
            // Utilizar la distribución dinámica para el resto de módulos por defecto
            default => $this->armarDistribucionDinamica($reglasBase, $documentData),
        };

        usort($asientoResult, function($a, $b) {
            $debeA = (float)($a['debe'] ?? 0);
            $debeB = (float)($b['debe'] ?? 0);
            if ($debeA > 0 && $debeB <= 0) return -1;
            if ($debeB > 0 && $debeA <= 0) return 1;
            return 0;
        });

        return $asientoResult;
    }

    /**
     * Asiento de RECLASIFICACIÓN de inventario para una consignación de venta.
     *
     * Una consignación NO es una venta (no transfiere propiedad): la mercadería entregada
     * se reclasifica desde Inventario hacia "Mercadería en consignación / poder de terceros",
     * SIEMPRE a COSTO (nunca a precio de venta).
     *
     *   Debe : Mercadería en consignación (poder de terceros)   = costo total
     *   Haber: Inventario                                        = costo total
     *
     * El costo se toma del kardex (salidas de la consignación). Las cuentas se resuelven desde
     * asientos_programados (concepto 'consignacion_venta'); si la empresa aún no las configuró,
     * la cuenta de inventario cae a la de 'ventas_factura' y la de consignación queda vacía
     * (id 0) para que el usuario la seleccione en la pestaña Asiento contable.
     *
     * @return array<int,array> Líneas del asiento o [] si la consignación no tiene costo.
     */
    public function generarAsientoConsignacion(int $idEmpresa, int $idConsignacion): array
    {
        $db = \App\core\Database::getConnection();

        // 1. Costo total desde el kardex (salidas de esta consignación).
        $stCosto = $db->prepare(
            "SELECT COALESCE(SUM(costo_total), 0)
             FROM inventario_kardex
             WHERE referencia_tipo = 'CONSIGNACION_VENTA'
               AND referencia_id   = ?
               AND tipo_movimiento = 'salida'
               AND eliminado       = false"
        );
        $stCosto->execute([$idConsignacion]);
        $costo = round((float) $stCosto->fetchColumn(), 2);
        if ($costo <= 0) {
            return [];
        }

        // 2. Cuentas del concepto propio 'consignacion_venta' (si está configurado).
        $cuentaConsignacion = null; // Debe
        $cuentaInventario   = null; // Haber
        foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'consignacion_venta') as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
            $cuenta = [
                'id_cuenta'     => (int) $r['id_cuenta'],
                'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
            ];
            if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $cuentaInventario = $cuenta;
            } elseif (str_contains($codigo, 'CONSIGNACION') || str_contains($codigo, 'MERCADERIA') || str_contains($concepto, 'consignaci')) {
                $cuentaConsignacion = $cuenta;
            }
        }

        // 3. Fallback de la cuenta de Inventario: reutilizar la de 'ventas_factura'.
        if ($cuentaInventario === null) {
            foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura') as $r) {
                if (empty($r['id_cuenta'])) continue;
                $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
                $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
                if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                    $cuentaInventario = [
                        'id_cuenta'     => (int) $r['id_cuenta'],
                        'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                    ];
                    break;
                }
            }
        }

        // 4. Dos líneas al costo. La cuenta que no esté configurada queda con id 0 para
        //    que el usuario la seleccione manualmente en la pestaña.
        return [
            [
                'id_cuenta_contable' => $cuentaConsignacion['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaConsignacion['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaConsignacion['cuenta_nombre'] ?? '',
                'debe'               => $costo,
                'haber'              => 0.0,
                'referencia_detalle' => 'Mercadería en consignación (poder de terceros)',
            ],
            [
                'id_cuenta_contable' => $cuentaInventario['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaInventario['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaInventario['cuenta_nombre'] ?? '',
                'debe'               => 0.0,
                'haber'              => $costo,
                'referencia_detalle' => 'Inventario',
            ],
        ];
    }

    /**
     * Asiento de REINGRESO por facturación desde consignación: es el INVERSO de la
     * consignación por las cantidades a facturar. La mercadería vuelve del "poder de
     * terceros" al Inventario (a costo) para que la Factura de Venta la descargue de
     * forma normal.
     *   DEBE  : Inventario
     *   HABER : Mercadería en Consignación
     * El costo se valora al mismo costo que la consignación de origen (kardex de la salida).
     *
     * @return array<int,array> Líneas del asiento o [] si no hay costo.
     */
    public function generarAsientoReingresoFacturacion(int $idEmpresa, int $idConsignacionFactura): array
    {
        $db = \App\core\Database::getConnection();

        // 1. Costo reingresado = cantidad de cada línea × costo unitario de la consignación de origen.
        $stCosto = $db->prepare(
            "SELECT COALESCE(SUM(
                cfd.cantidad * (
                    SELECT COALESCE(SUM(k.costo_total), 0) / NULLIF(SUM(ABS(k.cantidad)), 0)
                    FROM inventario_kardex k
                    WHERE k.referencia_tipo = 'CONSIGNACION_VENTA'
                      AND k.referencia_id   = cfd.id_consignacion
                      AND k.id_producto     = cfd.id_producto
                      AND k.tipo_movimiento = 'salida'
                      AND k.eliminado       = false
                )
             ), 0)
             FROM consignaciones_facturas_detalles cfd
             WHERE cfd.id_consignacion_factura = ? AND cfd.eliminado = false"
        );
        $stCosto->execute([$idConsignacionFactura]);
        $costo = round((float) $stCosto->fetchColumn(), 2);
        if ($costo <= 0) {
            return [];
        }

        // 2. Mismas cuentas que la consignación (concepto 'consignacion_venta'), con
        //    fallback de Inventario a 'ventas_factura'.
        $cuentaConsignacion = null;
        $cuentaInventario   = null;
        foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'consignacion_venta') as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
            $cuenta = [
                'id_cuenta'     => (int) $r['id_cuenta'],
                'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
            ];
            if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $cuentaInventario = $cuenta;
            } elseif (str_contains($codigo, 'CONSIGNACION') || str_contains($codigo, 'MERCADERIA') || str_contains($concepto, 'consignaci')) {
                $cuentaConsignacion = $cuenta;
            }
        }
        if ($cuentaInventario === null) {
            foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura') as $r) {
                if (empty($r['id_cuenta'])) continue;
                $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
                $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
                if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                    $cuentaInventario = [
                        'id_cuenta'     => (int) $r['id_cuenta'],
                        'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                    ];
                    break;
                }
            }
        }

        // 3. INVERSO de la consignación: Debe Inventario / Haber Mercadería en consignación.
        return [
            [
                'id_cuenta_contable' => $cuentaInventario['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaInventario['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaInventario['cuenta_nombre'] ?? '',
                'debe'               => $costo,
                'haber'              => 0.0,
                'referencia_detalle' => 'Inventario (reingreso por facturación de consignación)',
            ],
            [
                'id_cuenta_contable' => $cuentaConsignacion['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaConsignacion['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaConsignacion['cuenta_nombre'] ?? '',
                'debe'               => 0.0,
                'haber'              => $costo,
                'referencia_detalle' => 'Mercadería en consignación (facturación)',
            ],
        ];
    }

    /**
     * Asiento de un RETORNO de consignación: es el INVERSO exacto del asiento de la consignación.
     * La mercadería vuelve del "poder de terceros" al Inventario, a costo.
     *   DEBE  : Inventario
     *   HABER : Mercadería en Consignación
     * El costo se valora al mismo costo que la consignación de origen (kardex de la salida).
     *
     * @return array<int,array> Líneas del asiento o [] si no hay costo.
     */
    public function generarAsientoRetornoCv(int $idEmpresa, int $idRetorno): array
    {
        $db = \App\core\Database::getConnection();

        // 1. Costo devuelto = cantidad de cada línea × costo unitario de la consignación de origen.
        $stCosto = $db->prepare(
            "SELECT COALESCE(SUM(
                rcd.cantidad * (
                    SELECT COALESCE(SUM(k.costo_total), 0) / NULLIF(SUM(ABS(k.cantidad)), 0)
                    FROM inventario_kardex k
                    WHERE k.referencia_tipo = 'CONSIGNACION_VENTA'
                      AND k.referencia_id   = rcd.id_consignacion
                      AND k.id_producto     = rcd.id_producto
                      AND k.tipo_movimiento = 'salida'
                      AND k.eliminado       = false
                )
             ), 0)
             FROM retornos_cv_detalles rcd
             WHERE rcd.id_retorno = ? AND rcd.eliminado = false"
        );
        $stCosto->execute([$idRetorno]);
        $costo = round((float) $stCosto->fetchColumn(), 2);
        if ($costo <= 0) {
            return [];
        }

        // 2. Mismas cuentas que la consignación (concepto 'consignacion_venta').
        $cuentaConsignacion = null;
        $cuentaInventario   = null;
        foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'consignacion_venta') as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
            $cuenta = [
                'id_cuenta'     => (int) $r['id_cuenta'],
                'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
            ];
            if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $cuentaInventario = $cuenta;
            } elseif (str_contains($codigo, 'CONSIGNACION') || str_contains($codigo, 'MERCADERIA') || str_contains($concepto, 'consignaci')) {
                $cuentaConsignacion = $cuenta;
            }
        }

        // 3. Fallback de la cuenta de Inventario: reutilizar la de 'ventas_factura'.
        if ($cuentaInventario === null) {
            foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura') as $r) {
                if (empty($r['id_cuenta'])) continue;
                $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
                $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
                if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                    $cuentaInventario = [
                        'id_cuenta'     => (int) $r['id_cuenta'],
                        'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                    ];
                    break;
                }
            }
        }

        // 4. INVERSO de la consignación: Debe Inventario / Haber Mercadería en consignación.
        return [
            [
                'id_cuenta_contable' => $cuentaInventario['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaInventario['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaInventario['cuenta_nombre'] ?? '',
                'debe'               => $costo,
                'haber'              => 0.0,
                'referencia_detalle' => 'Inventario (retorno de consignación)',
            ],
            [
                'id_cuenta_contable' => $cuentaConsignacion['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaConsignacion['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaConsignacion['cuenta_nombre'] ?? '',
                'debe'               => 0.0,
                'haber'              => $costo,
                'referencia_detalle' => 'Mercadería en consignación (devolución)',
            ],
        ];
    }

    /**
     * Asiento de un CAMBIO DE PRODUCTOS, a costo. Refleja el neto entre lo que
     * REINGRESA (productos devueltos) y lo que SALE (productos entregados):
     *   - reingreso de lo devuelto: Debe Inventario / Haber Costo de Ventas
     *   - salida de lo entregado:   Debe Costo de Ventas / Haber Inventario
     * Consolidado por cuenta (cuadra por construcción). Valorado al costo promedio
     * del producto en su bodega (excluyendo los movimientos de este mismo cambio).
     * Reutiliza las cuentas del concepto 'ventas_factura' (Inventario + Costo de Ventas).
     *
     * @return array<int,array> Líneas del asiento o [] si el neto es ~0 o faltan cuentas.
     */
    public function generarAsientoCambioProductoCv(int $idEmpresa, int $idCambio): array
    {
        $db = \App\core\Database::getConnection();

        // 1. Costo de cada lado, al costo promedio del producto/bodega (sin este cambio).
        $st = $db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN cd.tipo_linea = 'devolucion' THEN cd.cantidad * cp.costo ELSE 0 END), 0) AS costo_dev,
                COALESCE(SUM(CASE WHEN cd.tipo_linea = 'entrega'    THEN cd.cantidad * cp.costo ELSE 0 END), 0) AS costo_ent
             FROM cambios_producto_cv_detalles cd
             LEFT JOIN LATERAL (
                SELECT CASE WHEN SUM(k.cantidad) > 0
                            THEN SUM(k.costo_total)::numeric / NULLIF(SUM(k.cantidad), 0)
                            ELSE 0 END AS costo
                FROM inventario_kardex k
                WHERE k.id_empresa = :e AND k.id_producto = cd.id_producto AND k.id_bodega = cd.id_bodega
                  AND k.tipo_movimiento = 'entrada' AND k.eliminado = false
                  AND NOT (k.referencia_tipo = 'CAMBIO_PRODUCTO_CV' AND k.referencia_id = :id)
             ) cp ON true
             WHERE cd.id_cambio = :id AND cd.eliminado = false"
        );
        $st->execute([':e' => $idEmpresa, ':id' => $idCambio]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: ['costo_dev' => 0, 'costo_ent' => 0];

        $invNet = round((float) $row['costo_dev'] - (float) $row['costo_ent'], 2);
        if (abs($invNet) < 0.005) {
            return [];
        }

        // 2. Cuentas del concepto 'ventas_factura': Inventario y Costo de Ventas.
        $cuentaInventario = null;
        $cuentaCosto      = null;
        foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura') as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']   ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '');
            $cuenta = [
                'id_cuenta'     => (int) $r['id_cuenta'],
                'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
            ];
            if (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $cuentaInventario = $cuenta;
            } elseif (str_contains($codigo, 'COSTO') || str_contains($concepto, 'costo')) {
                $cuentaCosto = $cuenta;
            }
        }

        // 3. Neto por cuenta (Inventario vs Costo de Ventas).
        $invDebe  = max($invNet, 0.0);
        $invHaber = max(-$invNet, 0.0);

        return [
            [
                'id_cuenta_contable' => $cuentaInventario['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaInventario['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaInventario['cuenta_nombre'] ?? '',
                'debe'               => round($invDebe, 2),
                'haber'              => round($invHaber, 2),
                'referencia_detalle' => 'Inventario (cambio de productos)',
            ],
            [
                'id_cuenta_contable' => $cuentaCosto['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaCosto['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaCosto['cuenta_nombre'] ?? '',
                'debe'               => round($invHaber, 2),
                'haber'              => round($invDebe, 2),
                'referencia_detalle' => 'Costo de ventas (cambio de productos)',
            ],
        ];
    }

    /**
     * Lee el id de la entidad (cliente/proveedor) de la cabecera del documento. $tabla y $columna son
     * constantes internas (no entrada de usuario), por lo que es seguro interpolarlas.
     */
    private function buscarEntidadDocumento(string $tabla, string $columna, int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        $db = \App\core\Database::getConnection();
        $st = $db->prepare("SELECT {$columna} FROM {$tabla} WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    /**
     * Resuelve las cuentas personalizadas de una ENTIDAD del documento (cliente/proveedor) por concepto.
     */
    private function resolverCuentasPorMetodo(int $idEmpresa, string $tipoAsiento, string $metodo, array $documentData): array
    {
        $customAccounts = [];
        if ($metodo === 'general' || $metodo === '') {
            return $customAccounts;
        }

        $idReferencia = 0;
        $tipoReferencia = '';

        // Determinar el ID de referencia y el tipo según el método activo
        switch ($metodo) {
            case 'cliente':
                $idReferencia = (int)($documentData['id_cliente'] ?? 0);
                $tipoReferencia = 'cliente';
                break;
            case 'proveedor':
                $idReferencia = (int)($documentData['id_proveedor'] ?? 0);
                $tipoReferencia = 'proveedor';
                break;
            case 'producto':
                $idReferencia = (int)($documentData['id_producto'] ?? 0);
                $tipoReferencia = 'producto';
                break;
            case 'categoria':
                $idReferencia = (int)($documentData['id_categoria'] ?? 0);
                $tipoReferencia = 'categoria';
                break;
            case 'marca':
                $idReferencia = (int)($documentData['id_marca'] ?? 0);
                $tipoReferencia = 'marca';
                break;
            case 'iva':
                $idReferencia = (int)($documentData['id_iva'] ?? 0);
                $tipoReferencia = 'iva';
                break;
        }

        if ($idReferencia > 0 && $tipoReferencia !== '') {
            $sql = "SELECT ap.id_asiento_tipo, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                    FROM asientos_programados ap
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    WHERE ap.id_empresa = :id_empresa 
                      AND ap.tipo_referencia = :tipo_ref 
                      AND ap.id_referencia = :id_ref 
                      AND ap.eliminado = false";
            
            $db = \App\core\Database::getConnection();
            $st = $db->prepare($sql);
            $st->execute([
                ':id_empresa' => $idEmpresa,
                ':tipo_ref' => $tipoReferencia,
                ':id_ref' => $idReferencia
            ]);

            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $customAccounts[(int)$row['id_asiento_tipo']] = $row;
            }
        }

        return $customAccounts;
    }

    /**
     * Reparto POR LÍNEA en CASCADA para VENTAS: cada línea toma la cuenta de su producto; si no, la
     * de su categoría; si no, la de su marca; si ninguna, la cuenta base (General). Agrupa por la
     * cuenta resultante y concilia el redondeo contra $montoTotal.
     *
     * Generalizada para reutilizarse en cualquier concepto que tenga un valor real por línea (no
     * solo Subtotal): Cuenta por Cobrar, Costo de Ventas, Inventario, ICE — ver "Diseño: reparto
     * completo por categoría" (2026-07-31). $valorExpr es la expresión SQL a sumar por línea
     * (columna de `ventas_detalle d`, o de un join agregado en $joinsExtra); $joinsExtra son JOINs
     * adicionales que $valorExpr pueda necesitar. Ambos son literales del código, no entrada de
     * usuario — seguros de interpolar.
     *
     * A diferencia de antes, la cuenta BASE (General/Cliente) puede venir VACÍA — pasa cuando el
     * concepto solo está configurado a nivel de Producto/Categoría/Marca y en ningún otro lado. En
     * ese caso, las líneas que sí resuelven cuenta (por su producto/categoría/marca) se postean
     * igual; lo que no resuelve NI por línea NI por la base se devuelve aparte en 'sin_cuenta' —
     * nunca se postea una línea con id_cuenta=0.
     *
     * @return array{partes: array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>, sin_cuenta: float}
     */
    private function repartirVentasCascada(\PDO $db, int $idEmpresa, int $idVenta, int $idAsientoTipo, array $cuentaBase, float $montoTotal, string $valorExpr = 'd.precio_total_sin_impuesto', string $joinsExtra = '', bool $colapsarPorProducto = false): array
    {
        $idCuentaBase = (int)($cuentaBase['id_cuenta'] ?? 0);
        $baseLinea = [
            'id_cuenta'     => $idCuentaBase,
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($montoTotal <= 0) {
            return ['partes' => [], 'sin_cuenta' => 0.0];
        }
        if ($idVenta <= 0) {
            // Sin id_venta (preview): no hay líneas que consultar todavía.
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        // $colapsarPorProducto (costo/inventario): inventario_kardex no guarda id_venta_detalle,
        // solo id_producto — el join contra `d` por id_producto es necesariamente 1:N. Si el mismo
        // producto aparece en 2+ líneas de la misma venta (multi-lote/NUP), unir el kardex
        // directamente contra `ventas_detalle d` multiplica cada movimiento por cada línea
        // (producto cartesiano línea×movimiento) y duplica el costo. Se colapsa `d` a productos
        // distintos ANTES de unir el kardex, para que cada movimiento se cuente una sola vez.
        // Los demás conceptos (Cuenta por Cobrar, Subtotal, ICE) NO usan esto: su valor sí es por
        // línea real (columnas propias de `d` o joins 1:1 por d.id) y deben sumarse por línea.
        $fromClause = $colapsarPorProducto
            ? "(SELECT DISTINCT id_producto, id_venta FROM ventas_detalle WHERE id_venta = :id_doc) d"
            : "ventas_detalle d";

        // COALESCE(producto, categoría, marca, tipo de producción) → la cuenta más específica
        // configurada para cada línea. Tipo de Producción (Bien=1/Servicio=2, ver mapeo de
        // productos.tipo_produccion '01'/'02' en el CASE de ap_tp) es la dimensión MENOS específica
        // de las cuatro (solo 2 valores posibles) — por eso va al final del COALESCE, justo antes de
        // caer a la cuenta base (Cliente/General).
        $sql = "SELECT COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM({$valorExpr})::numeric, 2) AS monto
                FROM {$fromClause}
                LEFT JOIN productos p ON p.id = d.id_producto
                {$joinsExtra}
                LEFT JOIN asientos_programados ap_p
                       ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                      AND ap_p.id_asiento_tipo = :id_tipo1 AND ap_p.id_empresa = :emp1 AND ap_p.eliminado = false
                LEFT JOIN asientos_programados ap_c
                       ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                      AND ap_c.id_asiento_tipo = :id_tipo2 AND ap_c.id_empresa = :emp2 AND ap_c.eliminado = false
                LEFT JOIN asientos_programados ap_m
                       ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                      AND ap_m.id_asiento_tipo = :id_tipo3 AND ap_m.id_empresa = :emp3 AND ap_m.eliminado = false
                LEFT JOIN asientos_programados ap_tp
                       ON ap_tp.tipo_referencia = 'tipo_produccion'
                      AND ap_tp.id_referencia = (CASE WHEN p.tipo_produccion = '02' THEN 2 WHEN p.tipo_produccion = '01' THEN 1 END)
                      AND ap_tp.id_asiento_tipo = :id_tipo4 AND ap_tp.id_empresa = :emp4 AND ap_tp.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta)
                " . ($colapsarPorProducto ? "" : "WHERE d.id_venta = :id_doc") . "
                GROUP BY COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_tipo4' => $idAsientoTipo, ':emp4' => $idEmpresa,
            ':id_doc'   => $idVenta,
        ]);

        $mapa      = [];
        $total     = 0.0;
        $sinCuenta = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            if ($tieneCta) {
                $idCta = (int)$row['dim_cuenta'];
                $cod   = $row['dim_codigo'] ?? '';
                $nom   = $row['dim_nombre'] ?? '';
            } elseif ($idCuentaBase > 0) {
                $idCta = $idCuentaBase;
                $cod   = $baseLinea['cuenta_codigo'];
                $nom   = $baseLinea['cuenta_nombre'];
            } else {
                // Ni la línea (producto/categoría/marca) ni la General/Cliente tienen cuenta:
                // no se puede postear — se reporta aparte, nunca con id_cuenta=0.
                $sinCuenta = round($sinCuenta + $monto, 2);
                $total     = round($total + $monto, 2);
                continue;
            }
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa) && $sinCuenta <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        // Conciliación de redondeo contra el total esperado: se ajusta el bucket resuelto más
        // grande; si no hay ninguno resuelto, la diferencia se suma a "sin cuenta".
        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            if (!empty($mapa)) {
                $keys = array_keys($mapa);
                $ult  = end($keys);
                $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
            } else {
                $sinCuenta = round($sinCuenta + $dif, 2);
            }
        }

        return ['partes' => array_values($mapa), 'sin_cuenta' => round(max(0.0, $sinCuenta), 2)];
    }

    /**
     * Aplica repartirVentasCascada() para UN concepto (Cuenta por Cobrar, Subtotal, ICE, Costo,
     * Inventario) y traduce el resultado a líneas de asiento, agregándolas a $detalles (o a
     * $costoLineas si es del bloque de costo). Lo que ninguna línea ni la cuenta base resuelven
     * se reporta en $reglasSinCuenta (mismo criterio de mensaje que el resto del builder).
     */
    private function aplicarRepartoPorCategoria(
        \PDO $db, int $idEmpresa, int $idVenta, array $r, float $monto, string $valorExpr, string $joinsExtra,
        string $refBase, bool $esLineaCosto, array &$detalles, array &$costoLineas, array &$reglasSinCuenta
    ): void {
        if ($monto <= 0) return;
        $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
        $cuentaBase = [
            'id_cuenta'     => (int)($r['id_cuenta'] ?? 0),
            'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
        ];
        $res = $this->repartirVentasCascada($db, $idEmpresa, $idVenta, (int)$r['id_asiento_tipo'], $cuentaBase, $monto, $valorExpr, $joinsExtra, $esLineaCosto);
        foreach ($res['partes'] as $pte) {
            $linea = [
                'id_cuenta_contable' => $pte['id_cuenta'],
                'cuenta_codigo'      => $pte['cuenta_codigo'],
                'cuenta_nombre'      => $pte['cuenta_nombre'],
                'debe'               => $lado === 'debe' ? round($pte['monto'], 2) : 0.0,
                'haber'              => $lado === 'debe' ? 0.0 : round($pte['monto'], 2),
                'referencia_detalle' => $refBase . ' · por línea',
            ];
            if ($esLineaCosto) { $costoLineas[] = $linea; } else { $detalles[] = $linea; }
        }
        if ($res['sin_cuenta'] >= 0.01) {
            $reglasSinCuenta[] = $refBase . ' (algunas líneas sin cuenta por producto/categoría/marca/tipo de producción, ni en la General)';
        }
    }

    /**
     * Igual que repartirVentasCascada() pero para RECIBOS DE VENTA, que guardan sus líneas en
     * recibos_venta_detalle (tabla separada de ventas_detalle, con su propia numeración de IDs).
     * Generalizada igual que su par de ventas (2026-08-01): $valorExpr/$joinsExtra para reutilizarse
     * en Cuenta por Cobrar/ICE/Costo/Inventario, y nunca postea con id_cuenta=0 (ver 'sin_cuenta').
     *
     * @return array{partes: array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>, sin_cuenta: float}
     */
    private function repartirRecibosCascada(\PDO $db, int $idEmpresa, int $idRecibo, int $idAsientoTipo, array $cuentaBase, float $montoTotal, string $valorExpr = 'd.precio_total_sin_impuesto', string $joinsExtra = '', bool $colapsarPorProducto = false): array
    {
        $idCuentaBase = (int)($cuentaBase['id_cuenta'] ?? 0);
        $baseLinea = [
            'id_cuenta'     => $idCuentaBase,
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($montoTotal <= 0) {
            return ['partes' => [], 'sin_cuenta' => 0.0];
        }
        if ($idRecibo <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        // $colapsarPorProducto (costo/inventario): ver la misma nota en repartirVentasCascada().
        $fromClause = $colapsarPorProducto
            ? "(SELECT DISTINCT id_producto, id_recibo FROM recibos_venta_detalle WHERE id_recibo = :id_doc) d"
            : "recibos_venta_detalle d";

        $sql = "SELECT COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM({$valorExpr})::numeric, 2) AS monto
                FROM {$fromClause}
                LEFT JOIN productos p ON p.id = d.id_producto
                {$joinsExtra}
                LEFT JOIN asientos_programados ap_p
                       ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                      AND ap_p.id_asiento_tipo = :id_tipo1 AND ap_p.id_empresa = :emp1 AND ap_p.eliminado = false
                LEFT JOIN asientos_programados ap_c
                       ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                      AND ap_c.id_asiento_tipo = :id_tipo2 AND ap_c.id_empresa = :emp2 AND ap_c.eliminado = false
                LEFT JOIN asientos_programados ap_m
                       ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                      AND ap_m.id_asiento_tipo = :id_tipo3 AND ap_m.id_empresa = :emp3 AND ap_m.eliminado = false
                LEFT JOIN asientos_programados ap_tp
                       ON ap_tp.tipo_referencia = 'tipo_produccion'
                      AND ap_tp.id_referencia = (CASE WHEN p.tipo_produccion = '02' THEN 2 WHEN p.tipo_produccion = '01' THEN 1 END)
                      AND ap_tp.id_asiento_tipo = :id_tipo4 AND ap_tp.id_empresa = :emp4 AND ap_tp.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta)
                " . ($colapsarPorProducto ? "" : "WHERE d.id_recibo = :id_doc") . "
                GROUP BY COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_tipo4' => $idAsientoTipo, ':emp4' => $idEmpresa,
            ':id_doc'   => $idRecibo,
        ]);

        $mapa      = [];
        $total     = 0.0;
        $sinCuenta = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            if ($tieneCta) {
                $idCta = (int)$row['dim_cuenta'];
                $cod   = $row['dim_codigo'] ?? '';
                $nom   = $row['dim_nombre'] ?? '';
            } elseif ($idCuentaBase > 0) {
                $idCta = $idCuentaBase;
                $cod   = $baseLinea['cuenta_codigo'];
                $nom   = $baseLinea['cuenta_nombre'];
            } else {
                $sinCuenta = round($sinCuenta + $monto, 2);
                $total     = round($total + $monto, 2);
                continue;
            }
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa) && $sinCuenta <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            if (!empty($mapa)) {
                $keys = array_keys($mapa);
                $ult  = end($keys);
                $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
            } else {
                $sinCuenta = round($sinCuenta + $dif, 2);
            }
        }

        return ['partes' => array_values($mapa), 'sin_cuenta' => round(max(0.0, $sinCuenta), 2)];
    }

    /**
     * Aplica repartirRecibosCascada() para UN concepto y traduce el resultado a líneas de asiento
     * (o a $costoLineas si es del bloque de costo). Espejo de aplicarRepartoPorCategoria() para
     * Recibos de Venta.
     */
    private function aplicarRepartoPorCategoriaRecibos(
        \PDO $db, int $idEmpresa, int $idRecibo, array $r, float $monto, string $valorExpr, string $joinsExtra,
        string $refBase, bool $esLineaCosto, array &$detalles, array &$costoLineas, array &$reglasSinCuenta
    ): void {
        if ($monto <= 0) return;
        $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
        $cuentaBase = [
            'id_cuenta'     => (int)($r['id_cuenta'] ?? 0),
            'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
        ];
        $res = $this->repartirRecibosCascada($db, $idEmpresa, $idRecibo, (int)$r['id_asiento_tipo'], $cuentaBase, $monto, $valorExpr, $joinsExtra, $esLineaCosto);
        foreach ($res['partes'] as $pte) {
            $linea = [
                'id_cuenta_contable' => $pte['id_cuenta'],
                'cuenta_codigo'      => $pte['cuenta_codigo'],
                'cuenta_nombre'      => $pte['cuenta_nombre'],
                'debe'               => $lado === 'debe' ? round($pte['monto'], 2) : 0.0,
                'haber'              => $lado === 'debe' ? 0.0 : round($pte['monto'], 2),
                'referencia_detalle' => $refBase . ' · por línea',
            ];
            if ($esLineaCosto) { $costoLineas[] = $linea; } else { $detalles[] = $linea; }
        }
        if ($res['sin_cuenta'] >= 0.01) {
            $reglasSinCuenta[] = $refBase . ' (algunas líneas sin cuenta por producto/categoría/marca/tipo de producción, ni en la General)';
        }
    }

    /**
     * Igual que repartirVentasCascada()/repartirRecibosCascada() pero para NOTAS DE CRÉDITO de
     * venta, que guardan sus líneas en notas_credito_detalle. Construye en el "lado natural de
     * venta" (igual que el resto de generarAsientoNotaCreditoVenta()); la inversión final Debe/Haber
     * la hace ese método, no esta función.
     *
     * @return array{partes: array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>, sin_cuenta: float}
     */
    private function repartirNotaCreditoCascada(\PDO $db, int $idEmpresa, int $idNotaCredito, int $idAsientoTipo, array $cuentaBase, float $montoTotal, string $valorExpr = 'd.precio_total_sin_impuesto', string $joinsExtra = '', bool $colapsarPorProducto = false): array
    {
        $idCuentaBase = (int)($cuentaBase['id_cuenta'] ?? 0);
        $baseLinea = [
            'id_cuenta'     => $idCuentaBase,
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($montoTotal <= 0) {
            return ['partes' => [], 'sin_cuenta' => 0.0];
        }
        if ($idNotaCredito <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        // $colapsarPorProducto (costo/inventario): ver la misma nota en repartirVentasCascada().
        $fromClause = $colapsarPorProducto
            ? "(SELECT DISTINCT id_producto, id_nota_credito FROM notas_credito_detalle WHERE id_nota_credito = :id_doc) d"
            : "notas_credito_detalle d";

        $sql = "SELECT COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM({$valorExpr})::numeric, 2) AS monto
                FROM {$fromClause}
                LEFT JOIN productos p ON p.id = d.id_producto
                {$joinsExtra}
                LEFT JOIN asientos_programados ap_p
                       ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                      AND ap_p.id_asiento_tipo = :id_tipo1 AND ap_p.id_empresa = :emp1 AND ap_p.eliminado = false
                LEFT JOIN asientos_programados ap_c
                       ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                      AND ap_c.id_asiento_tipo = :id_tipo2 AND ap_c.id_empresa = :emp2 AND ap_c.eliminado = false
                LEFT JOIN asientos_programados ap_m
                       ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                      AND ap_m.id_asiento_tipo = :id_tipo3 AND ap_m.id_empresa = :emp3 AND ap_m.eliminado = false
                LEFT JOIN asientos_programados ap_tp
                       ON ap_tp.tipo_referencia = 'tipo_produccion'
                      AND ap_tp.id_referencia = (CASE WHEN p.tipo_produccion = '02' THEN 2 WHEN p.tipo_produccion = '01' THEN 1 END)
                      AND ap_tp.id_asiento_tipo = :id_tipo4 AND ap_tp.id_empresa = :emp4 AND ap_tp.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta)
                " . ($colapsarPorProducto ? "" : "WHERE d.id_nota_credito = :id_doc") . "
                GROUP BY COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_tipo4' => $idAsientoTipo, ':emp4' => $idEmpresa,
            ':id_doc'   => $idNotaCredito,
        ]);

        $mapa      = [];
        $total     = 0.0;
        $sinCuenta = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            if ($tieneCta) {
                $idCta = (int)$row['dim_cuenta'];
                $cod   = $row['dim_codigo'] ?? '';
                $nom   = $row['dim_nombre'] ?? '';
            } elseif ($idCuentaBase > 0) {
                $idCta = $idCuentaBase;
                $cod   = $baseLinea['cuenta_codigo'];
                $nom   = $baseLinea['cuenta_nombre'];
            } else {
                $sinCuenta = round($sinCuenta + $monto, 2);
                $total     = round($total + $monto, 2);
                continue;
            }
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa) && $sinCuenta <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            if (!empty($mapa)) {
                $keys = array_keys($mapa);
                $ult  = end($keys);
                $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
            } else {
                $sinCuenta = round($sinCuenta + $dif, 2);
            }
        }

        return ['partes' => array_values($mapa), 'sin_cuenta' => round(max(0.0, $sinCuenta), 2)];
    }

    /**
     * Aplica repartirNotaCreditoCascada() para UN concepto y traduce el resultado a líneas en el
     * "lado natural de venta" (Debe/Haber según $r['debe_haber'], igual que Cliente/General); la
     * inversión final la hace generarAsientoNotaCreditoVenta().
     */
    private function aplicarRepartoPorCategoriaNC(
        \PDO $db, int $idEmpresa, int $idNotaCredito, array $r, float $monto, string $valorExpr, string $joinsExtra,
        string $refBase, bool $esLineaCosto, array &$comercial, array &$costoLineas, array &$reglasSinCuenta
    ): void {
        if ($monto <= 0) return;
        $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
        $cuentaBase = [
            'id_cuenta'     => (int)($r['id_cuenta'] ?? 0),
            'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
        ];
        $res = $this->repartirNotaCreditoCascada($db, $idEmpresa, $idNotaCredito, (int)$r['id_asiento_tipo'], $cuentaBase, $monto, $valorExpr, $joinsExtra, $esLineaCosto);
        foreach ($res['partes'] as $pte) {
            $linea = [
                'id_cuenta_contable' => $pte['id_cuenta'],
                'cuenta_codigo'      => $pte['cuenta_codigo'],
                'cuenta_nombre'      => $pte['cuenta_nombre'],
                'debe'               => $lado === 'debe' ? round($pte['monto'], 2) : 0.0,
                'haber'              => $lado === 'debe' ? 0.0 : round($pte['monto'], 2),
                'referencia_detalle' => $refBase . ' · por línea (NC)',
            ];
            if ($esLineaCosto) { $costoLineas[] = $linea; } else { $comercial[] = $linea; }
        }
        if ($res['sin_cuenta'] >= 0.01) {
            $reglasSinCuenta[] = $refBase . ' (algunas líneas sin cuenta por producto/categoría/marca/tipo de producción, ni en la General)';
        }
    }

    /**
     * Reparto POR LÍNEA de COMPRAS por NOMBRE del ítem: cada línea toma la cuenta de la regla
     * 'item_compra' cuya `referencia_texto` coincide con su descripción; si no, la de su categoría;
     * si no, la de su marca; si ninguna, la cuenta base (General). Incluye los ítems de texto libre
     * (sin id_producto). Concilia el redondeo contra $montoTotal.
     *
     * Generalizada (2026-08-01, mismo diseño que ventas/recibos/NC): $valorExpr/$joinsExtra para
     * reutilizarse en Por Pagar/Inventario además de Subtotal/Gasto; $soloInventariable filtra qué
     * líneas entran (true=solo inventariables, para Inventario; false=solo no inventariables, para
     * Subtotal/Gasto — comportamiento por defecto, igual que antes; null=todas, para Por Pagar).
     * Nunca postea con id_cuenta=0: lo no resuelto ni por línea ni por la base va en 'sin_cuenta'.
     *
     * @return array{partes: array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>, sin_cuenta: float}
     */
    private function repartirComprasPorItem(\PDO $db, int $idEmpresa, int $idCompra, int $idAsientoTipo, array $cuentaBase, float $montoTotal, string $valorExpr = 'd.precio_total_sin_impuesto', string $joinsExtra = '', ?bool $soloInventariable = false): array
    {
        $idCuentaBase = (int)($cuentaBase['id_cuenta'] ?? 0);
        $baseLinea = [
            'id_cuenta'     => $idCuentaBase,
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($montoTotal <= 0) {
            return ['partes' => [], 'sin_cuenta' => 0.0];
        }
        if ($idCompra <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        $filtroInv = '';
        if ($soloInventariable === true) {
            $filtroInv = "AND d.id_producto IS NOT NULL AND COALESCE(p.inventariable, false) = true AND COALESCE(p.tipo_produccion, '') <> '02'";
        } elseif ($soloInventariable === false) {
            $filtroInv = "AND (d.id_producto IS NULL OR COALESCE(p.inventariable, false) <> true OR COALESCE(p.tipo_produccion, '') = '02')";
        }

        $sql = "SELECT COALESCE(ap_i.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM({$valorExpr})::numeric, 2) AS monto
                FROM compras_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                {$joinsExtra}
                LEFT JOIN asientos_programados ap_i
                       ON TRIM(ap_i.referencia_texto) = TRIM(d.descripcion) AND ap_i.tipo_referencia = 'item_compra'
                      AND ap_i.id_asiento_tipo = :id_tipo1 AND ap_i.id_empresa = :emp1 AND ap_i.eliminado = false
                LEFT JOIN asientos_programados ap_c
                       ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                      AND ap_c.id_asiento_tipo = :id_tipo2 AND ap_c.id_empresa = :emp2 AND ap_c.eliminado = false
                LEFT JOIN asientos_programados ap_m
                       ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                      AND ap_m.id_asiento_tipo = :id_tipo3 AND ap_m.id_empresa = :emp3 AND ap_m.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_i.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta)
                WHERE d.id_compra = :id_doc
                  {$filtroInv}
                GROUP BY COALESCE(ap_i.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_doc'   => $idCompra,
        ]);

        $mapa      = [];
        $total     = 0.0;
        $sinCuenta = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            if ($tieneCta) {
                $idCta = (int)$row['dim_cuenta'];
                $cod   = $row['dim_codigo'] ?? '';
                $nom   = $row['dim_nombre'] ?? '';
            } elseif ($idCuentaBase > 0) {
                $idCta = $idCuentaBase;
                $cod   = $baseLinea['cuenta_codigo'];
                $nom   = $baseLinea['cuenta_nombre'];
            } else {
                $sinCuenta = round($sinCuenta + $monto, 2);
                $total     = round($total + $monto, 2);
                continue;
            }
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa) && $sinCuenta <= 0) {
            return $idCuentaBase > 0
                ? ['partes' => [$baseLinea], 'sin_cuenta' => 0.0]
                : ['partes' => [], 'sin_cuenta' => round($montoTotal, 2)];
        }

        // Conciliación de redondeo contra el total esperado.
        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            if (!empty($mapa)) {
                $keys = array_keys($mapa);
                $ult  = end($keys);
                $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
            } else {
                $sinCuenta = round($sinCuenta + $dif, 2);
            }
        }

        return ['partes' => array_values($mapa), 'sin_cuenta' => round(max(0.0, $sinCuenta), 2)];
    }

    /**
     * Cuadra el asiento llevando la diferencia Debe/Haber a la CUENTA DE AJUSTE POR REDONDEO
     * configurada para el concepto (asiento_tipo con código que contiene 'REDONDEO', p. ej.
     * AJUSTEREDONDEOVENTA / AJUSTEREDONDEOCOMPRA). Reemplaza la antigua absorción de centavos
     * en una línea existente: el asiento queda cuadrado EXACTO, sin tolerancia residual.
     *
     * Lado dinámico según el signo de la diferencia:
     *   diff = totalDebe - totalHaber
     *   diff > 0 (falta Haber) → línea de ajuste al HABER por abs(diff)
     *   diff < 0 (falta Debe)  → línea de ajuste al DEBE  por abs(diff)
     *
     * Salvaguardas:
     *   - |diff| > TOPE_AJUSTE_REDONDEO (0.03) → excepción: descuadre real de configuración,
     *     NO se enmascara en la cuenta de ajuste.
     *   - Descuadre dentro del tope pero sin cuenta de ajuste configurada → excepción pidiendo
     *     configurarla (la empresa debe dejar la config completa).
     *
     * @param array  $reglas   Reglas base del concepto (incluye la regla de ajuste si existe).
     * @param string $etiqueta Texto para los mensajes de error ('ventas', 'compras', …).
     */
    private function aplicarAjusteRedondeo(array $detalles, array $reglas, string $etiqueta, array $reglasSinCuenta = [], ?array $cuentaRedondeoCategoria = null): array
    {
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);
        $diff = round($totalDebe - $totalHaber, 2);

        if ($diff === 0.0) {
            return $detalles;
        }

        // Descuadre mayor al tope = error real de configuración (cuenta/impuesto faltante).
        if (abs($diff) > self::TOPE_AJUSTE_REDONDEO) {
            // Causa casi siempre real: una regla activa sin cuenta asignada se salta en
            // silencio y su lado del asiento desaparece. Se nombra para que el mensaje
            // diga qué hacer en vez de solo "Debe: $0.00, Haber: $905.50".
            if (!empty($reglasSinCuenta)) {
                throw new \Exception(
                    "El asiento no cuadra (Debe: $" . number_format($totalDebe, 2) .
                    ", Haber: $" . number_format($totalHaber, 2) . "). Falta asignar la cuenta contable de: " .
                    implode(', ', array_unique($reglasSinCuenta)) .
                    // Llaves obligatorias: «» son multibyte y PHP los absorbería en el nombre de la variable.
                    ". Configúrela en Contabilidad → Configuración contable, concepto «{$etiqueta}»."
                );
            }
            throw new \Exception(
                "El asiento no cuadra. Debe: $" . number_format($totalDebe, 2) .
                ", Haber: $" . number_format($totalHaber, 2) .
                ". La diferencia ($" . number_format(abs($diff), 2) . ") supera el máximo de ajuste por " .
                "redondeo (3 centavos). Revise la configuración de cuentas contables para $etiqueta."
            );
        }

        // Cuenta de ajuste por redondeo: primero la de Categoría (si el llamador ya la resolvió
        // — reparto por categoría activo y alguna categoría del documento la tiene configurada),
        // y solo si no hay ninguna, la General. A diferencia de Costo/Inventario, esta función NO
        // arma su propia cascada (el redondeo es un valor único del documento, no por línea) — el
        // llamador es quien decide la categoría vía resolverCuentaRedondeoPorCategoria().
        $ajuste = $cuentaRedondeoCategoria;
        if ($ajuste === null) {
            foreach ($reglas as $r) {
                $cod = strtoupper($r['asiento_tipo_codigo'] ?? $r['codigo'] ?? '');
                if (str_contains($cod, 'REDONDEO') && !empty($r['id_cuenta'])) {
                    $ajuste = $r;
                    break;
                }
            }
        }

        if ($ajuste === null) {
            throw new \Exception(
                "El asiento difiere por redondeo en $" . number_format(abs($diff), 2) .
                " pero no se ha configurado la cuenta de «Ajuste por redondeo» para $etiqueta. " .
                "Configúrela en la pantalla de asientos programados para que el asiento cuadre."
            );
        }

        $lado = $diff > 0 ? 'haber' : 'debe';
        $detalles[] = [
            'id_cuenta_contable' => (int) $ajuste['id_cuenta'],
            'cuenta_codigo'      => $ajuste['cuenta_codigo'] ?? '',
            'cuenta_nombre'      => $ajuste['cuenta_nombre'] ?? '',
            'debe'               => $lado === 'debe'  ? round(abs($diff), 2) : 0.0,
            'haber'              => $lado === 'haber' ? round(abs($diff), 2) : 0.0,
            'referencia_detalle' => 'Ajuste por redondeo',
        ];

        return $detalles;
    }

    /**
     * Resuelve la cuenta de "Ajuste por Redondeo" con la MISMA cascada Producto → Categoría →
     * Marca que usan Costo/Inventario/Subtotal/etc. (repartirVentasCascada() y equivalentes),
     * para documentos con reparto por categoría activo. El redondeo es UN solo valor por
     * documento (no por línea), así que no reparte/prorratea como Costo o Subtotal: toma la
     * PRIMERA línea (entre los productos del documento) cuya cascada resuelva alguna cuenta —
     * criterio de desempate razonable si el documento mezcla productos/categorías/marcas con y
     * sin cuenta de redondeo propia. $tabla/$colDoc son literales del código (no entrada de
     * usuario), seguros de interpolar — mismo patrón que repartirVentasCascada()/etc.
     */
    private function resolverCuentaRedondeoPorCategoria(\PDO $db, int $idEmpresa, int $idAsientoTipo, string $tabla, string $colDoc, int $idDocumento): ?array
    {
        if ($idAsientoTipo <= 0 || $idDocumento <= 0) {
            return null;
        }
        $sql = "SELECT COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta) AS id_cuenta,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM {$tabla} d
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN asientos_programados ap_p
                       ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                      AND ap_p.id_asiento_tipo = :id_tipo1 AND ap_p.id_empresa = :emp1 AND ap_p.eliminado = false
                LEFT JOIN asientos_programados ap_c
                       ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                      AND ap_c.id_asiento_tipo = :id_tipo2 AND ap_c.id_empresa = :emp2 AND ap_c.eliminado = false
                LEFT JOIN asientos_programados ap_m
                       ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                      AND ap_m.id_asiento_tipo = :id_tipo3 AND ap_m.id_empresa = :emp3 AND ap_m.eliminado = false
                LEFT JOIN asientos_programados ap_tp
                       ON ap_tp.tipo_referencia = 'tipo_produccion'
                      AND ap_tp.id_referencia = (CASE WHEN p.tipo_produccion = '02' THEN 2 WHEN p.tipo_produccion = '01' THEN 1 END)
                      AND ap_tp.id_asiento_tipo = :id_tipo4 AND ap_tp.id_empresa = :emp4 AND ap_tp.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta)
                WHERE d.{$colDoc} = :id_doc
                  AND COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_tp.id_cuenta) IS NOT NULL
                LIMIT 1";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_tipo4' => $idAsientoTipo, ':emp4' => $idEmpresa,
            ':id_doc'   => $idDocumento,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Estructura y distribuye los montos Debe/Haber específicos para el módulo de Ventas con Factura.
     *
     * Regla de balance:
     *   Sin cuenta de descuento:
     *     DEBE: importe_total (por cobrar)
     *     HABER: total_sin_impuestos (ventas netas) + IVA + ICE + propina
     *     → cuadra porque importe_total = total_sin_impuestos + IVA + ICE + propina
     *
     *   Con cuenta de descuento (enfoque bruto):
     *     DEBE: importe_total + descuento
     *     HABER: (total_sin_impuestos + descuento) + IVA + ICE + propina
     *     → cuadra igualmente
     */
    private function armarDistribucionVentasFactura(array $reglas, array $data): array
    {
        $idVenta  = (int)($data['id_venta'] ?? 0);
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        // Cascada: solo se reparte la línea de Ventas por producto/categoría/marca si el cliente NO
        // tiene reglas propias (cuando las tiene, manda el cliente y no se reparte — Opción 2).
        $repartePorLinea = (bool)($data['__reparte_por_linea__'] ?? false);
        $db = \App\core\Database::getConnection();

        // ── 1. Totales: leer SIEMPRE desde la BD cuando hay id_venta (fuente de verdad) ──
        if ($idVenta > 0) {
            $stCab = $db->prepare(
                "SELECT importe_total,
                        total_sin_impuestos,
                        total_descuento,
                        COALESCE(total_ice, 0) AS total_ice,
                        COALESCE(propina, 0)   AS propina
                 FROM ventas_cabecera
                 WHERE id = ?"
            );
            $stCab->execute([$idVenta]);
            $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];

            $importeTotal = round((float)($cab['importe_total']        ?? 0), 2);
            $subtotal     = round((float)($cab['total_sin_impuestos']   ?? 0), 2);
            $descuento    = round((float)($cab['total_descuento']        ?? 0), 2);
            $totalIce     = round((float)($cab['total_ice']             ?? 0), 2);
            $propina      = round((float)($cab['propina']               ?? 0), 2);
        } else {
            $importeTotal = round((float)($data['importe_total'] ?? $data['total']    ?? 0), 2);
            $subtotal     = round((float)($data['total_sin_impuestos'] ?? $data['subtotal'] ?? 0), 2);
            $descuento    = round((float)($data['total_descuento']      ?? $data['descuento'] ?? 0), 2);
            $totalIce     = round((float)($data['total_ice']  ?? 0), 2);
            $propina      = round((float)($data['propina']    ?? 0), 2);
        }

        // ── 2. Costo de Ventas desde Kardex ──
        $costoRealInventario = 0.0;
        if ($idVenta > 0) {
            $stCosto = $db->prepare(
                "SELECT COALESCE(SUM(costo_total), 0)
                 FROM inventario_kardex
                 WHERE referencia_tipo = 'factura_venta'
                   AND referencia_id   = ?
                   AND tipo_movimiento = 'salida'
                   AND eliminado       = false"
            );
            $stCosto->execute([$idVenta]);
            $costoRealInventario = round((float)$stCosto->fetchColumn(), 2);
        }

        // ── 3. IVA: mapeo por tasa → cuenta específica (asientos_programados) ──
        $detalles        = [];   // líneas del asiento
        $totalIvaTotal   = 0.0; // IVA total del documento
        $totalIvaMapeado = 0.0; // IVA ya asignado a cuentas específicas
        $ivaTarifasSinCuenta = []; // tarifas usadas por la factura sin cuenta configurada

        if ($idVenta > 0) {
            // 3a. Total IVA del documento (todas las tasas)
            $stIvaSum = $db->prepare(
                "SELECT COALESCE(SUM(i.valor), 0)
                 FROM ventas_detalle_impuestos i
                 JOIN ventas_detalle d ON i.id_venta_detalle = d.id
                 WHERE d.id_venta = ? AND i.codigo_impuesto = '2'"
            );
            $stIvaSum->execute([$idVenta]);
            $totalIvaTotal = round((float)$stIvaSum->fetchColumn(), 2);

            // 3b. IVA por tasa con cuenta específica en asientos_programados. Cascada de especificidad
            // (cliente > producto > categoría > marca > general) igual filosofía que el resto del motor:
            // si hay un override de IVA configurado para esa tarifa en algún nivel, se usa esa cuenta;
            // si no hay ninguno, COALESCE cae en ap_gen (la regla general), idéntico al comportamiento previo.
            $idCliente = (int) ($data['id_cliente'] ?? 0);
            $sqlIva = "SELECT i.codigo_porcentaje,
                              SUM(i.valor)    AS total_valor,
                              COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta) AS id_cuenta_contable,
                              pc.codigo       AS cuenta_codigo,
                              pc.nombre       AS cuenta_nombre
                       FROM ventas_detalle_impuestos i
                       JOIN ventas_detalle d ON i.id_venta_detalle = d.id
                       LEFT JOIN productos p ON p.id = d.id_producto
                       LEFT JOIN asientos_programados ap_cli
                              ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                             AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_cli.direccion_iva = 'venta' AND ap_cli.id_empresa = :id_empresa AND ap_cli.eliminado = false
                       LEFT JOIN asientos_programados ap_p
                              ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                             AND ap_p.id_asiento_tipo = 0 AND ap_p.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_p.direccion_iva = 'venta' AND ap_p.id_empresa = :id_empresa AND ap_p.eliminado = false
                       LEFT JOIN asientos_programados ap_c
                              ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                             AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_c.direccion_iva = 'venta' AND ap_c.id_empresa = :id_empresa AND ap_c.eliminado = false
                       LEFT JOIN asientos_programados ap_m
                              ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                             AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_m.direccion_iva = 'venta' AND ap_m.id_empresa = :id_empresa AND ap_m.eliminado = false
                       LEFT JOIN asientos_programados ap_gen
                              ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                             AND ap_gen.tipo_referencia = 'iva_ventas_factura'
                             AND ap_gen.id_empresa      = :id_empresa
                             AND ap_gen.eliminado       = false
                       LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta)
                       WHERE d.id_venta = :id_venta AND i.codigo_impuesto = '2'
                       GROUP BY i.codigo_porcentaje, COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
            $stIva = $db->prepare($sqlIva);
            $stIva->execute([':id_empresa' => $idEmpresa, ':id_venta' => $idVenta, ':id_cliente' => $idCliente]);

            while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
                $valorIva = round((float)$row['total_valor'], 2);
                if ($valorIva <= 0 || empty($row['id_cuenta_contable'])) continue;

                $detalles[] = [
                    'id_cuenta_contable' => (int)$row['id_cuenta_contable'],
                    'cuenta_codigo'      => $row['cuenta_codigo'],
                    'cuenta_nombre'      => $row['cuenta_nombre'],
                    'debe'               => 0.0,
                    'haber'              => $valorIva,
                    'referencia_detalle' => 'IVA Ventas',
                ];
                $totalIvaMapeado += $valorIva;
            }
            $totalIvaMapeado = round($totalIvaMapeado, 2);

            // 3c. Tarifas de IVA que la factura USA pero que NO tienen cuenta configurada (en ningún
            //     nivel de la cascada: cliente/producto/categoría/marca/general). Es la causa más común
            //     de descuadre. Se nombran para que el aviso diga exactamente qué configurar.
            $sqlIvaSin = "SELECT DISTINCT i.codigo_porcentaje, t.tarifa
                          FROM ventas_detalle_impuestos i
                          JOIN ventas_detalle d ON i.id_venta_detalle = d.id
                          LEFT JOIN productos p ON p.id = d.id_producto
                          LEFT JOIN tarifa_iva t ON CAST(t.codigo AS INTEGER) = CAST(i.codigo_porcentaje AS INTEGER)
                          LEFT JOIN asientos_programados ap_cli
                                 ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                                AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_cli.direccion_iva = 'venta' AND ap_cli.id_empresa = :id_empresa AND ap_cli.eliminado = false
                          LEFT JOIN asientos_programados ap_p
                                 ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                                AND ap_p.id_asiento_tipo = 0 AND ap_p.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_p.direccion_iva = 'venta' AND ap_p.id_empresa = :id_empresa AND ap_p.eliminado = false
                          LEFT JOIN asientos_programados ap_c
                                 ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                                AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_c.direccion_iva = 'venta' AND ap_c.id_empresa = :id_empresa AND ap_c.eliminado = false
                          LEFT JOIN asientos_programados ap_m
                                 ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                                AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_m.direccion_iva = 'venta' AND ap_m.id_empresa = :id_empresa AND ap_m.eliminado = false
                          LEFT JOIN asientos_programados ap_gen
                                 ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                                AND ap_gen.tipo_referencia = 'iva_ventas_factura'
                                AND ap_gen.id_empresa      = :id_empresa
                                AND ap_gen.eliminado       = false
                          WHERE d.id_venta = :id_venta AND i.codigo_impuesto = '2'
                            AND i.valor > 0
                            AND ap_cli.id IS NULL AND ap_p.id IS NULL AND ap_c.id IS NULL AND ap_m.id IS NULL AND ap_gen.id IS NULL";
            $stIvaSin = $db->prepare($sqlIvaSin);
            $stIvaSin->execute([':id_empresa' => $idEmpresa, ':id_venta' => $idVenta, ':id_cliente' => $idCliente]);
            while ($row = $stIvaSin->fetch(\PDO::FETCH_ASSOC)) {
                $ivaTarifasSinCuenta[] = 'IVA tarifa ' . ($row['tarifa'] ?: $row['codigo_porcentaje']);
            }
        } else {
            // Sin id_venta (preview): calcular IVA por diferencia
            $totalIvaTotal = round(max(0.0, $importeTotal - $subtotal - $totalIce - $propina), 2);
        }

        // IVA no asignado a cuenta específica → irá a la cuenta IVA general de las reglas base
        $ivaParaCuentaGeneral = round($totalIvaTotal - $totalIvaMapeado, 2);

        // ── 4. Pre-scan: ¿existe regla de DESCUENTO? ──
        // Si hay una CUENTA de descuento realmente CONFIGURADA (no solo el concepto en el catálogo
        // asientos_tipo, que siempre existe con o sin cuenta) Y hay descuento en la factura, el
        // SUBTOTAL (HABER) debe ser el importe bruto (neto + descuento) para que cuadre.
        //
        // BUG corregido (encontrado 2026-08-01): antes no se comprobaba `id_cuenta`, así que
        // CUALQUIER factura con descuento > 0 activaba este modo aunque no hubiera ninguna cuenta
        // de Descuento configurada — desactivando TODO el reparto por categoría (Subtotal, y ahora
        // también Cuenta por Cobrar/ICE/Costo/Inventario) sin motivo real, y sin ningún aviso que lo
        // explicara (el mensaje de "faltan cuentas" no distinguía este caso del de verdad faltante).
        $tieneReglaDescuento = false;
        if ($descuento > 0) {
            foreach ($reglas as $r) {
                if (empty($r['id_cuenta'])) continue;
                $c  = strtoupper($r['asiento_tipo_codigo']      ?? $r['codigo']     ?? '');
                $cv = strtolower($r['asiento_tipo_referencia']  ?? $r['concepto']   ?? $r['referencia'] ?? '');
                if (str_contains($c, 'DESC') || str_contains($cv, 'descuento')) {
                    $tieneReglaDescuento = true;
                    break;
                }
            }
        }

        // ── 5. Procesar reglas base ──
        // El bloque comercial (por cobrar, ventas, IVA, ICE, propina, descuento) se agrega
        // directamente a $detalles. El bloque de costo (Costo de Ventas + Inventario) se
        // recolecta aparte en $costoLineas y solo se añade si está COMPLETO y CUADRADO
        // (ambas cuentas configuradas). Si falta una de las dos, se descarta el bloque de
        // costo para que el asiento comercial se genere igual y no descuadre.
        $costoLineas = [];
        // Reglas activas sin cuenta configurada: se saltan, pero se recuerdan para poder
        // decir QUÉ falta si el asiento termina descuadrado (antes solo se veía "Debe: $0").
        // Arranca con las tarifas de IVA sin cuenta: son la causa más frecuente del descuadre.
        $reglasSinCuenta = $ivaTarifasSinCuenta;

        // Reparto por categoría (Producto → Categoría → Marca → General/Cliente): activo solo cuando
        // la entidad NO tiene reglas propias y no hay cuenta de Descuento (misma restricción que ya
        // tenía Subtotal — ver "Diseño: reparto completo por categoría", 2026-07-31). Cuenta por
        // Cobrar, Subtotal, ICE, Costo de Ventas e Inventario participan; Descuento y Propina NO
        // (Propina por decisión del usuario: siempre Cliente/General; Descuento queda pendiente).
        $aplicaRepartoPorCategoria = $repartePorLinea && !$tieneReglaDescuento && $idVenta > 0;

        // Joins reutilizables para sumar el valor real POR LÍNEA de cada concepto repartible, sin
        // fan-out (subconsultas agregadas primero, 1 fila por línea, luego JOIN 1:1 a `d`).
        $joinImpuestosPorLinea = "LEFT JOIN (
                SELECT id_venta_detalle, SUM(valor) AS total_impuestos
                FROM ventas_detalle_impuestos WHERE codigo_impuesto IN ('2','3') GROUP BY id_venta_detalle
            ) imp_cxc ON imp_cxc.id_venta_detalle = d.id";
        $joinIcePorLinea = "LEFT JOIN (
                SELECT id_venta_detalle, SUM(valor) AS total_ice
                FROM ventas_detalle_impuestos WHERE codigo_impuesto = '3' GROUP BY id_venta_detalle
            ) imp_ice ON imp_ice.id_venta_detalle = d.id";
        // Por producto+documento (no por id_inventario_kardex): un lote/kit puede generar más de un
        // movimiento de Kardex por línea; así se capturan todos los de ese producto en esta venta.
        $joinCostoPorLinea = "LEFT JOIN inventario_kardex kc
                ON kc.referencia_tipo = 'factura_venta' AND kc.referencia_id = d.id_venta
               AND kc.id_producto = d.id_producto AND kc.tipo_movimiento = 'salida' AND kc.eliminado = false";

        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // La cuenta de Ajuste por redondeo no se mapea aquí (se aplica al final) NI se reporta
            // como faltante: su ausencia solo importa en descuadres de centavos, y para ese caso
            // aplicarAjusteRedondeo() ya emite su propio mensaje. Reportarla en un descuadre grande
            // desviaba el diagnóstico de la cuenta que realmente falta (p. ej. el IVA de una tarifa).
            if (str_contains($codigo, 'REDONDEO')) continue;

            // Descuento tampoco se reporta como faltante: sin cuenta configurada, el Subtotal se
            // postea NETO (ya refleja el descuento) y el asiento cuadra igual — no es una cuenta
            // bloqueante, a diferencia del resto. Reportarla generaba ruido en facturas con
            // descuento aunque todo lo demás (Cuenta por Cobrar, Subtotal, IVA...) ya se generara bien.
            if (str_contains($codigo, 'DESC') || str_contains($concepto, 'descuento')) {
                if (!empty($r['id_cuenta']) && $descuento > 0) {
                    $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($descuento, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($descuento, 2),
                        'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '',
                    ];
                }
                continue;
            }

            $esPorCobrar  = str_contains($codigo, 'PORCOBRAR')  || str_contains($concepto, 'cobrar');
            $esSubtotal   = str_contains($codigo, 'SUBTOTAL')   || str_contains($concepto, 'subtotal');
            $esIce        = str_contains($codigo, 'ICE')        || str_contains($concepto, 'ice');
            $esCosto      = str_contains($codigo, 'COSTO')      || str_contains($concepto, 'costo');
            $esInventario = str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario');
            $esRepartible = $esPorCobrar || $esSubtotal || $esIce || $esCosto || $esInventario;

            // Si NO hay cuenta General/Cliente para este concepto, solo se perdona cuando el reparto
            // por categoría está activo Y el concepto participa de él — puede que la cuenta exista
            // solo a nivel de Producto/Categoría/Marca (antes esto se descartaba sin más, aunque la
            // categoría SÍ tuviera la cuenta configurada).
            if (empty($r['id_cuenta']) && !($aplicaRepartoPorCategoria && $esRepartible)) {
                $reglasSinCuenta[] = $r['asiento_tipo_referencia'] ?? $r['concepto']
                                  ?? $r['asiento_tipo_codigo'] ?? $r['codigo'] ?? 'sin nombre';
                continue;
            }

            $refBase = $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '';
            $lado    = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';

            // Cuenta por cobrar → importe total. Se reparte por categoría con el valor REAL de cada
            // línea (subtotal neto + IVA + ICE, todo ya guardado por línea). La Propina NO participa
            // (no existe por línea en la BD) — se agrega aparte, a la cuenta General/Cliente, para
            // que la suma total siga cuadrando contra el importe real de la factura.
            if ($esPorCobrar) {
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoria(
                        $db, $idEmpresa, $idVenta, $r, round($importeTotal - $propina, 2),
                        '(d.precio_total_sin_impuesto + COALESCE(imp_cxc.total_impuestos, 0))', $joinImpuestosPorLinea,
                        $refBase, false, $detalles, $costoLineas, $reglasSinCuenta
                    );
                    if ($propina > 0) {
                        if (!empty($r['id_cuenta'])) {
                            $detalles[] = [
                                'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                                'debe' => $lado === 'debe' ? round($propina, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($propina, 2),
                                'referencia_detalle' => $refBase . ' · propina',
                            ];
                        } else {
                            $reglasSinCuenta[] = $refBase . ' (para la propina; configure la cuenta en Cliente o en la General)';
                        }
                    }
                } elseif (!empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($importeTotal, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($importeTotal, 2),
                        'referencia_detalle' => $refBase, 'es_total_documento' => true,
                    ];
                }
                continue;
            }

            // Subtotal / Ventas: neto si no hay regla de descuento, bruto si la hay (sin reparto en
            // ese caso — igual que antes).
            if ($esSubtotal) {
                $valorMapeado = $tieneReglaDescuento ? ($subtotal + $descuento) : $subtotal;
                if ($valorMapeado <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoria(
                        $db, $idEmpresa, $idVenta, $r, $valorMapeado,
                        'd.precio_total_sin_impuesto', '',
                        $refBase, false, $detalles, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($valorMapeado, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($valorMapeado, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            // ICE: se reparte por categoría igual que el IVA (ya se calcula por línea en
            // ventas_detalle_impuestos, codigo_impuesto='3').
            if ($esIce) {
                if ($totalIce <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoria(
                        $db, $idEmpresa, $idVenta, $r, $totalIce,
                        'COALESCE(imp_ice.total_ice, 0)', $joinIcePorLinea,
                        $refBase, false, $detalles, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($totalIce, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($totalIce, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            // Propina: SIEMPRE Cliente/General, nunca por categoría (decisión confirmada con el
            // usuario — no tiene dato por línea en la BD).
            if (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                if (!empty($r['id_cuenta']) && $propina > 0) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($propina, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($propina, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            // Costo de Ventas / Inventario (bloque de costo): por categoría, con el costo real del
            // Kardex de cada producto dentro de esta venta. Costo e Inventario se reparten cada uno
            // con SU PROPIA cascada (pueden tener cuentas distintas configuradas por categoría).
            if ($esCosto || $esInventario) {
                if ($costoRealInventario <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoria(
                        $db, $idEmpresa, $idVenta, $r, $costoRealInventario,
                        'COALESCE(kc.costo_total, 0)', $joinCostoPorLinea,
                        $refBase, true, $detalles, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $costoLineas[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($costoRealInventario, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($costoRealInventario, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            // IVA general: recibe el IVA que no pudo mapearse a cuenta específica por tasa (ya
            // resuelto arriba, sección 3 — cascada propia por tarifa, independiente de este reparto).
            if (str_contains($codigo, 'IVA') || str_contains($concepto, 'iva')) {
                $valorIvaGen = $ivaParaCuentaGeneral > 0 ? $ivaParaCuentaGeneral : 0.0;
                if ($valorIvaGen > 0 && !empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($valorIvaGen, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($valorIvaGen, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }
        }

        // ── 5.1 Bloque de costo: solo se agrega si está COMPLETO y CUADRADO ──
        // Si están las dos cuentas (Costo de Ventas e Inventario) y suman igual en Debe/Haber,
        // se incorpora el bloque de costo. Si falta una (configuración incompleta), se descarta
        // y el asiento comercial se genera igual; se deja traza para diagnóstico.
        $costoGenerado = false;
        $motivoCostoPendiente = null;
        if (!empty($costoLineas)) {
            $debeCosto  = round(array_sum(array_column($costoLineas, 'debe')),  2);
            $haberCosto = round(array_sum(array_column($costoLineas, 'haber')), 2);
            if ($debeCosto > 0 && $debeCosto === $haberCosto) {
                $detalles = array_merge($detalles, $costoLineas);
                $costoGenerado = true;
            } else {
                $motivoCostoPendiente = 'bloque_incompleto_descuadrado';
                error_log(
                    "[AsientoBuilder] Bloque de costo de ventas omitido por configuración incompleta " .
                    "(Debe: $debeCosto, Haber: $haberCosto). Configure ambas cuentas (Costo de Ventas e Inventario) " .
                    "para contabilizar el costo. El asiento comercial se generó igualmente."
                );
            }
        } elseif ($costoRealInventario > 0) {
            $motivoCostoPendiente = 'cuenta_no_configurada';
        }

        // ── 6. Validación de balance ──
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);

        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            // Antes se descartaba $reglasSinCuenta (ya calculada arriba) y se lanzaba un mensaje
            // genérico. Se reutiliza aquí para que el aviso diga qué cuenta(s) configurar, igual
            // que hace aplicarAjusteRedondeo() en el caso de descuadre.
            if (!empty($reglasSinCuenta)) {
                throw new \Exception(
                    "No se generó ninguna línea del asiento. Falta asignar la cuenta contable de: " .
                    implode(', ', array_unique($reglasSinCuenta)) .
                    ". Configúrela en Contabilidad → Configuración contable, concepto «ventas»."
                );
            }
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }

        // Cuadre exacto vía la cuenta de Ajuste por redondeo (la cuenta por cobrar = total del
        // documento es la fuente de verdad; Base + IVA pueden diferir por ±centavos al redondear
        // por separado). Un descuadre > 3 centavos = error real de configuración → excepción.
        $idAsientoTipoRedondeo = 0;
        foreach ($reglas as $r) {
            if (str_contains(strtoupper($r['asiento_tipo_codigo'] ?? $r['codigo'] ?? ''), 'REDONDEO')) {
                $idAsientoTipoRedondeo = (int) ($r['id_asiento_tipo'] ?? 0);
                break;
            }
        }
        $cuentaRedondeoCategoria = ($aplicaRepartoPorCategoria && $idAsientoTipoRedondeo > 0)
            ? $this->resolverCuentaRedondeoPorCategoria($db, $idEmpresa, $idAsientoTipoRedondeo, 'ventas_detalle', 'id_venta', $idVenta)
            : null;
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'ventas', $reglasSinCuenta, $cuentaRedondeoCategoria);

        // Seguimiento de costeo (solo documentos reales, nunca en preview sin id_venta): se
        // escribe AL FINAL, después de que el asiento pasó balance y ajuste de redondeo sin
        // excepción — si cualquiera de esos pasos falla, ni siquiera llegamos aquí, así que
        // "costo_generado" nunca queda en true para un asiento que en realidad no se guardó
        // (bug corregido: antes se escribía justo después del bloque de costo, ANTES de estas
        // validaciones, y un descuadre de redondeo no relacionado con el costo dejaba la tabla
        // diciendo "generado" para un asiento que nunca llegó a guardarse).
        if ($idVenta > 0) {
            $this->costeoRepo->registrar(
                $idEmpresa, 'factura_venta', $idVenta,
                $costoRealInventario > 0, $costoGenerado, $costoRealInventario,
                $motivoCostoPendiente, (int)($data['id_usuario'] ?? 0) ?: null
            );
        }

        return $detalles;
    }

    /**
     * Igual que armarDistribucionVentasFactura() pero para RECIBOS DE VENTA: reutiliza el MISMO
     * catálogo de cuentas (tipo_asiento='ventas_factura' — misma plantilla, mismas cuentas CxC/
     * Subtotal/IVA/Costo), pero lee los datos del documento de recibos_venta_cabecera/
     * recibos_venta_detalle/recibos_venta_detalle_impuestos, que son tablas separadas con su
     * propia numeración de IDs (NO ventas_cabecera/ventas_detalle — usar esas tablas con el ID
     * de un recibo contabilizaba los montos de una venta ajena que coincidiera con ese mismo ID).
     */
    private function armarDistribucionRecibosVenta(array $reglas, array $data): array
    {
        $idRecibo  = (int)($data['id_recibo'] ?? 0);
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        // Cascada: solo se reparte la línea de Ventas por producto/categoría/marca si el cliente NO
        // tiene reglas propias (cuando las tiene, manda el cliente y no se reparte — Opción 2).
        $repartePorLinea = (bool)($data['__reparte_por_linea__'] ?? false);
        $db = \App\core\Database::getConnection();

        // ── 1. Totales: leer SIEMPRE desde la BD cuando hay id_recibo (fuente de verdad) ──
        if ($idRecibo > 0) {
            $stCab = $db->prepare(
                "SELECT importe_total,
                        total_sin_impuestos,
                        total_descuento,
                        COALESCE(total_ice, 0) AS total_ice,
                        COALESCE(propina, 0)   AS propina
                 FROM recibos_venta_cabecera
                 WHERE id = ?"
            );
            $stCab->execute([$idRecibo]);
            $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];

            $importeTotal = round((float)($cab['importe_total']        ?? 0), 2);
            $subtotal     = round((float)($cab['total_sin_impuestos']   ?? 0), 2);
            $descuento    = round((float)($cab['total_descuento']        ?? 0), 2);
            $totalIce     = round((float)($cab['total_ice']             ?? 0), 2);
            $propina      = round((float)($cab['propina']               ?? 0), 2);
        } else {
            $importeTotal = round((float)($data['importe_total'] ?? $data['total']    ?? 0), 2);
            $subtotal     = round((float)($data['total_sin_impuestos'] ?? $data['subtotal'] ?? 0), 2);
            $descuento    = round((float)($data['total_descuento']      ?? $data['descuento'] ?? 0), 2);
            $totalIce     = round((float)($data['total_ice']  ?? 0), 2);
            $propina      = round((float)($data['propina']    ?? 0), 2);
        }

        // ── 2. Costo de Ventas desde Kardex (referencia_tipo='recibo_venta', ver ReciboVentaService::REF_TIPO) ──
        $costoRealInventario = 0.0;
        if ($idRecibo > 0) {
            $stCosto = $db->prepare(
                "SELECT COALESCE(SUM(costo_total), 0)
                 FROM inventario_kardex
                 WHERE referencia_tipo = 'recibo_venta'
                   AND referencia_id   = ?
                   AND tipo_movimiento = 'salida'
                   AND eliminado       = false"
            );
            $stCosto->execute([$idRecibo]);
            $costoRealInventario = round((float)$stCosto->fetchColumn(), 2);
        }

        // ── 3. IVA: mapeo por tasa → cuenta específica (asientos_programados) ──
        $detalles        = [];   // líneas del asiento
        $totalIvaTotal   = 0.0; // IVA total del documento
        $totalIvaMapeado = 0.0; // IVA ya asignado a cuentas específicas
        $ivaTarifasSinCuenta = []; // tarifas usadas por el recibo sin cuenta configurada

        if ($idRecibo > 0) {
            // 3a. Total IVA del documento (todas las tasas)
            $stIvaSum = $db->prepare(
                "SELECT COALESCE(SUM(i.valor), 0)
                 FROM recibos_venta_detalle_impuestos i
                 JOIN recibos_venta_detalle d ON i.id_recibo_detalle = d.id
                 WHERE d.id_recibo = ? AND i.codigo_impuesto = '2'"
            );
            $stIvaSum->execute([$idRecibo]);
            $totalIvaTotal = round((float)$stIvaSum->fetchColumn(), 2);

            // 3b. IVA por tasa con cuenta específica en asientos_programados. Misma cascada de
            // especificidad (cliente > producto > categoría > marca > general) que en facturas, pero
            // con catálogo PROPIO e independiente: tipo_referencia='iva_recibos_venta' y
            // direccion_iva='recibo' (no 'iva_ventas_factura'/'venta' — esos son de Facturas).
            $idCliente = (int) ($data['id_cliente'] ?? 0);
            $sqlIva = "SELECT i.codigo_porcentaje,
                              SUM(i.valor)    AS total_valor,
                              COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta) AS id_cuenta_contable,
                              pc.codigo       AS cuenta_codigo,
                              pc.nombre       AS cuenta_nombre
                       FROM recibos_venta_detalle_impuestos i
                       JOIN recibos_venta_detalle d ON i.id_recibo_detalle = d.id
                       LEFT JOIN productos p ON p.id = d.id_producto
                       LEFT JOIN asientos_programados ap_cli
                              ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                             AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_cli.direccion_iva = 'recibo' AND ap_cli.id_empresa = :id_empresa AND ap_cli.eliminado = false
                       LEFT JOIN asientos_programados ap_p
                              ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                             AND ap_p.id_asiento_tipo = 0 AND ap_p.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_p.direccion_iva = 'recibo' AND ap_p.id_empresa = :id_empresa AND ap_p.eliminado = false
                       LEFT JOIN asientos_programados ap_c
                              ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                             AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_c.direccion_iva = 'recibo' AND ap_c.id_empresa = :id_empresa AND ap_c.eliminado = false
                       LEFT JOIN asientos_programados ap_m
                              ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                             AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_m.direccion_iva = 'recibo' AND ap_m.id_empresa = :id_empresa AND ap_m.eliminado = false
                       LEFT JOIN asientos_programados ap_gen
                              ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                             AND ap_gen.tipo_referencia = 'iva_recibos_venta'
                             AND ap_gen.id_empresa      = :id_empresa
                             AND ap_gen.eliminado       = false
                       LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta)
                       WHERE d.id_recibo = :id_recibo AND i.codigo_impuesto = '2'
                       GROUP BY i.codigo_porcentaje, COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
            $stIva = $db->prepare($sqlIva);
            $stIva->execute([':id_empresa' => $idEmpresa, ':id_recibo' => $idRecibo, ':id_cliente' => $idCliente]);

            while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
                $valorIva = round((float)$row['total_valor'], 2);
                if ($valorIva <= 0 || empty($row['id_cuenta_contable'])) continue;

                $detalles[] = [
                    'id_cuenta_contable' => (int)$row['id_cuenta_contable'],
                    'cuenta_codigo'      => $row['cuenta_codigo'],
                    'cuenta_nombre'      => $row['cuenta_nombre'],
                    'debe'               => 0.0,
                    'haber'              => $valorIva,
                    'referencia_detalle' => 'IVA Recibo de Venta',
                ];
                $totalIvaMapeado += $valorIva;
            }
            $totalIvaMapeado = round($totalIvaMapeado, 2);

            // 3c. Tarifas de IVA que el recibo USA pero que NO tienen cuenta configurada (en ningún
            //     nivel de la cascada: cliente/producto/categoría/marca/general).
            $sqlIvaSin = "SELECT DISTINCT i.codigo_porcentaje, t.tarifa
                          FROM recibos_venta_detalle_impuestos i
                          JOIN recibos_venta_detalle d ON i.id_recibo_detalle = d.id
                          LEFT JOIN productos p ON p.id = d.id_producto
                          LEFT JOIN tarifa_iva t ON CAST(t.codigo AS INTEGER) = CAST(i.codigo_porcentaje AS INTEGER)
                          LEFT JOIN asientos_programados ap_cli
                                 ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                                AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_cli.direccion_iva = 'recibo' AND ap_cli.id_empresa = :id_empresa AND ap_cli.eliminado = false
                          LEFT JOIN asientos_programados ap_p
                                 ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                                AND ap_p.id_asiento_tipo = 0 AND ap_p.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_p.direccion_iva = 'recibo' AND ap_p.id_empresa = :id_empresa AND ap_p.eliminado = false
                          LEFT JOIN asientos_programados ap_c
                                 ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                                AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_c.direccion_iva = 'recibo' AND ap_c.id_empresa = :id_empresa AND ap_c.eliminado = false
                          LEFT JOIN asientos_programados ap_m
                                 ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                                AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                                AND ap_m.direccion_iva = 'recibo' AND ap_m.id_empresa = :id_empresa AND ap_m.eliminado = false
                          LEFT JOIN asientos_programados ap_gen
                                 ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                                AND ap_gen.tipo_referencia = 'iva_recibos_venta'
                                AND ap_gen.id_empresa      = :id_empresa
                                AND ap_gen.eliminado       = false
                          WHERE d.id_recibo = :id_recibo AND i.codigo_impuesto = '2'
                            AND i.valor > 0
                            AND ap_cli.id IS NULL AND ap_p.id IS NULL AND ap_c.id IS NULL AND ap_m.id IS NULL AND ap_gen.id IS NULL";
            $stIvaSin = $db->prepare($sqlIvaSin);
            $stIvaSin->execute([':id_empresa' => $idEmpresa, ':id_recibo' => $idRecibo, ':id_cliente' => $idCliente]);
            while ($row = $stIvaSin->fetch(\PDO::FETCH_ASSOC)) {
                $ivaTarifasSinCuenta[] = 'IVA tarifa ' . ($row['tarifa'] ?: $row['codigo_porcentaje']);
            }
        } else {
            // Sin id_recibo (preview): calcular IVA por diferencia
            $totalIvaTotal = round(max(0.0, $importeTotal - $subtotal - $totalIce - $propina), 2);
        }

        // IVA no asignado a cuenta específica → irá a la cuenta IVA general de las reglas base
        $ivaParaCuentaGeneral = round($totalIvaTotal - $totalIvaMapeado, 2);

        // ── 4. Pre-scan: ¿existe regla de DESCUENTO? ──
        // Mismo fix que en armarDistribucionVentasFactura (2026-08-01): solo cuenta si la cuenta de
        // Descuento está REALMENTE configurada (id_cuenta), no solo el concepto en el catálogo.
        $tieneReglaDescuento = false;
        if ($descuento > 0) {
            foreach ($reglas as $r) {
                if (empty($r['id_cuenta'])) continue;
                $c  = strtoupper($r['asiento_tipo_codigo']      ?? $r['codigo']     ?? '');
                $cv = strtolower($r['asiento_tipo_referencia']  ?? $r['concepto']   ?? $r['referencia'] ?? '');
                if (str_contains($c, 'DESC') || str_contains($cv, 'descuento')) {
                    $tieneReglaDescuento = true;
                    break;
                }
            }
        }

        // ── 5. Procesar reglas base (mismo diseño que armarDistribucionVentasFactura, 2026-08-01) ──
        $costoLineas = [];
        $reglasSinCuenta = $ivaTarifasSinCuenta;

        $aplicaRepartoPorCategoria = $repartePorLinea && !$tieneReglaDescuento && $idRecibo > 0;

        $joinImpuestosPorLinea = "LEFT JOIN (
                SELECT id_recibo_detalle, SUM(valor) AS total_impuestos
                FROM recibos_venta_detalle_impuestos WHERE codigo_impuesto IN ('2','3') GROUP BY id_recibo_detalle
            ) imp_cxc ON imp_cxc.id_recibo_detalle = d.id";
        $joinIcePorLinea = "LEFT JOIN (
                SELECT id_recibo_detalle, SUM(valor) AS total_ice
                FROM recibos_venta_detalle_impuestos WHERE codigo_impuesto = '3' GROUP BY id_recibo_detalle
            ) imp_ice ON imp_ice.id_recibo_detalle = d.id";
        $joinCostoPorLinea = "LEFT JOIN inventario_kardex kc
                ON kc.referencia_tipo = 'recibo_venta' AND kc.referencia_id = d.id_recibo
               AND kc.id_producto = d.id_producto AND kc.tipo_movimiento = 'salida' AND kc.eliminado = false";

        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            if (str_contains($codigo, 'REDONDEO')) continue;

            // Descuento no se reporta como faltante: sin cuenta configurada, el Subtotal se postea
            // NETO y el asiento cuadra igual.
            if (str_contains($codigo, 'DESC') || str_contains($concepto, 'descuento')) {
                if (!empty($r['id_cuenta']) && $descuento > 0) {
                    $ladoDesc = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $ladoDesc === 'debe' ? round($descuento, 2) : 0.0, 'haber' => $ladoDesc === 'debe' ? 0.0 : round($descuento, 2),
                        'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '',
                    ];
                }
                continue;
            }

            $esPorCobrar  = str_contains($codigo, 'PORCOBRAR')  || str_contains($concepto, 'cobrar');
            $esSubtotal   = str_contains($codigo, 'SUBTOTAL')   || str_contains($concepto, 'subtotal');
            $esIce        = str_contains($codigo, 'ICE')        || str_contains($concepto, 'ice');
            $esCosto      = str_contains($codigo, 'COSTO')      || str_contains($concepto, 'costo');
            $esInventario = str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario');
            $esRepartible = $esPorCobrar || $esSubtotal || $esIce || $esCosto || $esInventario;

            if (empty($r['id_cuenta']) && !($aplicaRepartoPorCategoria && $esRepartible)) {
                $reglasSinCuenta[] = $r['asiento_tipo_referencia'] ?? $r['concepto']
                                  ?? $r['asiento_tipo_codigo'] ?? $r['codigo'] ?? 'sin nombre';
                continue;
            }

            $refBase = $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '';
            $lado    = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';

            if ($esPorCobrar) {
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaRecibos(
                        $db, $idEmpresa, $idRecibo, $r, round($importeTotal - $propina, 2),
                        '(d.precio_total_sin_impuesto + COALESCE(imp_cxc.total_impuestos, 0))', $joinImpuestosPorLinea,
                        $refBase, false, $detalles, $costoLineas, $reglasSinCuenta
                    );
                    if ($propina > 0) {
                        if (!empty($r['id_cuenta'])) {
                            $detalles[] = [
                                'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                                'debe' => $lado === 'debe' ? round($propina, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($propina, 2),
                                'referencia_detalle' => $refBase . ' · propina',
                            ];
                        } else {
                            $reglasSinCuenta[] = $refBase . ' (para la propina; configure la cuenta en Cliente o en la General)';
                        }
                    }
                } elseif (!empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($importeTotal, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($importeTotal, 2),
                        'referencia_detalle' => $refBase, 'es_total_documento' => true,
                    ];
                }
                continue;
            }

            if ($esSubtotal) {
                $valorMapeado = $tieneReglaDescuento ? ($subtotal + $descuento) : $subtotal;
                if ($valorMapeado <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaRecibos(
                        $db, $idEmpresa, $idRecibo, $r, $valorMapeado,
                        'd.precio_total_sin_impuesto', '',
                        $refBase, false, $detalles, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($valorMapeado, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($valorMapeado, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            if ($esIce) {
                if ($totalIce <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaRecibos(
                        $db, $idEmpresa, $idRecibo, $r, $totalIce,
                        'COALESCE(imp_ice.total_ice, 0)', $joinIcePorLinea,
                        $refBase, false, $detalles, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($totalIce, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($totalIce, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            // Propina: SIEMPRE Cliente/General, nunca por categoría.
            if (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                if (!empty($r['id_cuenta']) && $propina > 0) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($propina, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($propina, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            if ($esCosto || $esInventario) {
                if ($costoRealInventario <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaRecibos(
                        $db, $idEmpresa, $idRecibo, $r, $costoRealInventario,
                        'COALESCE(kc.costo_total, 0)', $joinCostoPorLinea,
                        $refBase, true, $detalles, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $costoLineas[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($costoRealInventario, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($costoRealInventario, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }

            // IVA general: recibe el IVA que no pudo mapearse a cuenta específica por tasa.
            if (str_contains($codigo, 'IVA') || str_contains($concepto, 'iva')) {
                $valorIvaGen = $ivaParaCuentaGeneral > 0 ? $ivaParaCuentaGeneral : 0.0;
                if ($valorIvaGen > 0 && !empty($r['id_cuenta'])) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'], 'cuenta_nombre' => $r['cuenta_nombre'],
                        'debe' => $lado === 'debe' ? round($valorIvaGen, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($valorIvaGen, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
                continue;
            }
        }

        // ── 5.1 Bloque de costo: solo se agrega si está COMPLETO y CUADRADO ──
        $costoGenerado = false;
        $motivoCostoPendiente = null;
        if (!empty($costoLineas)) {
            $debeCosto  = round(array_sum(array_column($costoLineas, 'debe')),  2);
            $haberCosto = round(array_sum(array_column($costoLineas, 'haber')), 2);
            if ($debeCosto > 0 && $debeCosto === $haberCosto) {
                $detalles = array_merge($detalles, $costoLineas);
                $costoGenerado = true;
            } else {
                $motivoCostoPendiente = 'bloque_incompleto_descuadrado';
                error_log(
                    "[AsientoBuilder] Bloque de costo de recibo de venta omitido por configuración incompleta " .
                    "(Debe: $debeCosto, Haber: $haberCosto). Configure ambas cuentas (Costo de Ventas e Inventario) " .
                    "para contabilizar el costo. El asiento comercial se generó igualmente."
                );
            }
        } elseif ($costoRealInventario > 0) {
            $motivoCostoPendiente = 'cuenta_no_configurada';
        }

        // ── 6. Validación de balance ──
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);

        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            if (!empty($reglasSinCuenta)) {
                throw new \Exception(
                    "No se generó ninguna línea del asiento. Falta asignar la cuenta contable de: " .
                    implode(', ', array_unique($reglasSinCuenta)) .
                    ". Configúrela en Contabilidad → Configuración contable, concepto «recibos de venta»."
                );
            }
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }

        $idAsientoTipoRedondeo = 0;
        foreach ($reglas as $r) {
            if (str_contains(strtoupper($r['asiento_tipo_codigo'] ?? $r['codigo'] ?? ''), 'REDONDEO')) {
                $idAsientoTipoRedondeo = (int) ($r['id_asiento_tipo'] ?? 0);
                break;
            }
        }
        $cuentaRedondeoCategoria = ($aplicaRepartoPorCategoria && $idAsientoTipoRedondeo > 0)
            ? $this->resolverCuentaRedondeoPorCategoria($db, $idEmpresa, $idAsientoTipoRedondeo, 'recibos_venta_detalle', 'id_recibo', $idRecibo)
            : null;
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'recibos de venta', $reglasSinCuenta, $cuentaRedondeoCategoria);

        // Seguimiento de costeo, al final — ver la misma nota en armarDistribucionVentasFactura().
        if ($idRecibo > 0) {
            $this->costeoRepo->registrar(
                $idEmpresa, 'recibo_venta', $idRecibo,
                $costoRealInventario > 0, $costoGenerado, $costoRealInventario,
                $motivoCostoPendiente, (int)($data['id_usuario'] ?? 0) ?: null
            );
        }

        return $detalles;
    }

    /**
     * Clasifica la dirección del asiento de un documento de compra según su tipo de comprobante
     * (catálogo comprobantes_autorizados / SRI):
     *   - 'reversa'  : Notas de crédito (devolución/descuento al proveedor) → se invierte Debe/Haber.
     *   - 'excluido' : Comprobante de retención (07) → lo maneja el módulo de retenciones, no este asiento.
     *   - 'normal'   : Factura (01), Nota de venta (02), Liquidación (03), Nota de débito (05) y
     *                  cualquier otro documento de compra (12, 18, 19, …) → dirección de compra estándar.
     */
    private function clasificarDireccionCompra(string $tipoComprobante): string
    {
        $tipo = trim($tipoComprobante);
        // Notas de crédito (incluye TC, reembolso e instituciones del Estado)
        $reversa  = ['04', '23', '47', '51'];
        // Comprobante de retención: no genera asiento de compra
        $excluido = ['07'];

        if (in_array($tipo, $excluido, true)) return 'excluido';
        if (in_array($tipo, $reversa, true))  return 'reversa';
        return 'normal';
    }

    /**
     * Arma el asiento contable de un documento de COMPRA (Adquisiciones).
     *
     * Anti-duplicación de costo/gasto (clave): el subtotal se separa POR LÍNEA según la
     * naturaleza del producto, no por elección libre del usuario:
     *   - Líneas de productos INVENTARIABLES  → cuenta de Inventario (activo). El gasto nace
     *     después, en la venta (Costo de Ventas). Así no se duplica.
     *   - Líneas NO inventariables / servicios → cuenta de Gasto/Costo (resultado).
     *
     * Dirección según tipo de comprobante (clasificarDireccionCompra):
     *   normal  → DEBE Inventario/Gasto + IVA crédito ; HABER Cuentas por pagar.
     *   reversa → se invierte Debe/Haber (Nota de crédito de compra).
     *   excluido→ devuelve [] (no genera asiento).
     *
     * Reglas base esperadas (asientos_tipo de 'adquisiciones_compras'):
     *   INVENTARIOFACTURACOMPRA (activo, debe), SUBTOTALFACTURACOMPRA (costo/gasto, debe),
     *   PORPAGARFACTURACOMPRA (pasivo, haber), y las reglas IVA por tarifa (iva_compras_factura).
     */
    private function armarDistribucionCompras(array $reglas, array $data): array
    {
        $idCompra  = (int)($data['id_compra'] ?? $data['id'] ?? 0);
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        // Cascada: solo se reparte el gasto por nombre de ítem si el proveedor NO tiene reglas propias
        // (cuando las tiene, manda el proveedor y no se reparte — Opción 2).
        $repartePorLinea = (bool)($data['__reparte_por_linea__'] ?? false);
        $db = \App\core\Database::getConnection();

        // ── 1. Cabecera + tipo de comprobante (fuente de verdad: BD) ──
        $importeTotal = 0.0; $subtotal = 0.0; $propina = 0.0; $tipoComprobante = '01';
        if ($idCompra > 0) {
            $stCab = $db->prepare(
                "SELECT importe_total,
                        total_sin_impuestos,
                        COALESCE(propina, 0)          AS propina,
                        COALESCE(tipo_comprobante,'01') AS tipo_comprobante
                 FROM compras_cabecera WHERE id = ?"
            );
            $stCab->execute([$idCompra]);
            $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
            $importeTotal    = round((float)($cab['importe_total']      ?? 0), 2);
            $subtotal        = round((float)($cab['total_sin_impuestos'] ?? 0), 2);
            $propina         = round((float)($cab['propina']            ?? 0), 2);
            $tipoComprobante = (string)($cab['tipo_comprobante']        ?? '01');
        }

        $direccion = $this->clasificarDireccionCompra($tipoComprobante);
        if ($direccion === 'excluido') {
            return [];
        }
        $reversa = ($direccion === 'reversa');

        // ── 2. Separar el subtotal POR LÍNEA: inventariable → Inventario, resto → Gasto ──
        $subInventario = 0.0;
        $subGasto      = 0.0;
        if ($idCompra > 0) {
            $stDet = $db->prepare(
                "SELECT d.precio_total_sin_impuesto, d.id_producto,
                        p.inventariable, p.tipo_produccion
                 FROM compras_detalle d
                 LEFT JOIN productos p ON p.id = d.id_producto
                 WHERE d.id_compra = ?"
            );
            $stDet->execute([$idCompra]);
            foreach ($stDet as $d) {
                $monto = round((float)($d['precio_total_sin_impuesto'] ?? 0), 2);
                $inv   = $d['inventariable'] ?? null;
                $esInventariable = !empty($d['id_producto'])
                    && ($inv === true || $inv === 't' || $inv === 'true' || $inv == 1 || $inv === '1')
                    && (($d['tipo_produccion'] ?? '') !== '02');
                if ($esInventariable) {
                    $subInventario += $monto;
                } else {
                    $subGasto += $monto;
                }
            }
        }
        $subInventario = round($subInventario, 2);
        $subGasto      = round($subGasto, 2);

        // Ajuste por redondeo: la suma de líneas debe igualar el subtotal de cabecera.
        $diferencia = round($subtotal - ($subInventario + $subGasto), 2);
        if (abs($diferencia) >= 0.01) {
            $subGasto = round($subGasto + $diferencia, 2);
        }

        // ── 3. IVA crédito tributario por tarifa (cuenta configurada en iva_compras_factura). Cascada
        // de especificidad (proveedor > ítem > categoría > marca > general), misma filosofía que ventas.
        // La dimensión "Producto" en Compras siempre usa la clave de TEXTO del ítem (item_compra),
        // igual que repartirComprasPorItem() — no hay dimensión por id_producto en Compras.
        $ivaRows = [];
        if ($idCompra > 0) {
            $idProveedor = (int) ($data['id_proveedor'] ?? 0);
            $sqlIva = "SELECT i.codigo_porcentaje,
                              SUM(i.valor)  AS total_valor,
                              COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta) AS id_cuenta,
                              pc.codigo     AS cuenta_codigo,
                              pc.nombre     AS cuenta_nombre,
                              t.tarifa      AS tarifa_nombre
                       FROM compras_detalle_impuestos i
                       JOIN compras_detalle d ON i.id_compra_detalle = d.id
                       LEFT JOIN productos p ON p.id = d.id_producto
                       LEFT JOIN tarifa_iva t ON CAST(t.codigo AS INTEGER) = CAST(i.codigo_porcentaje AS INTEGER)
                       LEFT JOIN asientos_programados ap_prov
                              ON ap_prov.id_referencia = :id_proveedor AND ap_prov.tipo_referencia = 'proveedor'
                             AND ap_prov.id_asiento_tipo = 0 AND ap_prov.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_prov.direccion_iva = 'compra' AND ap_prov.id_empresa = :emp AND ap_prov.eliminado = false
                       LEFT JOIN asientos_programados ap_item
                              ON TRIM(ap_item.referencia_texto) = TRIM(d.descripcion) AND ap_item.tipo_referencia = 'item_compra'
                             AND ap_item.id_asiento_tipo = 0 AND ap_item.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_item.direccion_iva = 'compra' AND ap_item.id_empresa = :emp AND ap_item.eliminado = false
                       LEFT JOIN asientos_programados ap_c
                              ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                             AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_c.direccion_iva = 'compra' AND ap_c.id_empresa = :emp AND ap_c.eliminado = false
                       LEFT JOIN asientos_programados ap_m
                              ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                             AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                             AND ap_m.direccion_iva = 'compra' AND ap_m.id_empresa = :emp AND ap_m.eliminado = false
                       LEFT JOIN asientos_programados ap_gen
                              ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                             AND ap_gen.tipo_referencia = 'iva_compras_factura'
                             AND ap_gen.id_empresa      = :emp
                             AND ap_gen.eliminado       = false
                       LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta)
                       WHERE d.id_compra = :id AND i.codigo_impuesto = '2'
                       GROUP BY i.codigo_porcentaje, COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre, t.tarifa";
            $stIva = $db->prepare($sqlIva);
            $stIva->execute([':emp' => $idEmpresa, ':id' => $idCompra, ':id_proveedor' => $idProveedor]);
            while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
                $ivaRows[] = [
                    'id_cuenta'         => $row['id_cuenta'],
                    'cuenta_codigo'     => $row['cuenta_codigo'],
                    'cuenta_nombre'     => $row['cuenta_nombre'],
                    'valor'             => $row['total_valor'],
                    'codigo_porcentaje' => $row['codigo_porcentaje'],
                    'tarifa_nombre'     => $row['tarifa_nombre'],
                ];
            }
        }

        // ── 4. Ensamblar el cuerpo (Inventario/Gasto/IVA/Por pagar) respetando la dirección ──
        // Reparto por línea (item_compra → categoría → marca → General), mismo diseño que
        // ventas/recibos/NC (2026-08-01): Por Pagar, Subtotal/Gasto e Inventario reparten cada uno
        // con SU PROPIA cascada — no solo Subtotal como antes. Solo si el proveedor NO tiene reglas
        // propias (si las tiene, manda — Opción 2). ICE/Descuento siguen fuera de alcance en compras
        // (el subtotal ya viene neto por línea, decisión de diseño previa).
        $gastoLineas = null; $inventarioLineas = null; $porPagarLineas = null;
        $sinCuentaExtra = [];
        if ($repartePorLinea && $idCompra > 0) {
            $joinImpuestosPorLinea = "LEFT JOIN (
                    SELECT id_compra_detalle, SUM(valor) AS total_impuestos
                    FROM compras_detalle_impuestos WHERE codigo_impuesto = '2' GROUP BY id_compra_detalle
                ) imp_pp ON imp_pp.id_compra_detalle = d.id";

            foreach ($reglas as $rr) {
                $cod = strtoupper($rr['asiento_tipo_codigo'] ?? $rr['codigo'] ?? '');
                $con = strtolower($rr['asiento_tipo_referencia'] ?? $rr['concepto'] ?? $rr['referencia'] ?? '');
                $cuentaBaseRr = ['id_cuenta' => (int)($rr['id_cuenta'] ?? 0), 'cuenta_codigo' => $rr['cuenta_codigo'] ?? '', 'cuenta_nombre' => $rr['cuenta_nombre'] ?? ''];
                $refConcepto = $rr['asiento_tipo_referencia'] ?? $rr['concepto'] ?? $rr['referencia'] ?? '';

                if ((str_contains($cod, 'PORPAGAR') || str_contains($con, 'pagar')) && $importeTotal > 0) {
                    $res = $this->repartirComprasPorItem(
                        $db, $idEmpresa, $idCompra, (int)$rr['id_asiento_tipo'], $cuentaBaseRr, $importeTotal,
                        '(d.precio_total_sin_impuesto + COALESCE(imp_pp.total_impuestos, 0))', $joinImpuestosPorLinea, null
                    );
                    $porPagarLineas = $res['partes'];
                    if ($res['sin_cuenta'] >= 0.01) {
                        $sinCuentaExtra[] = $refConcepto . ' (algunas líneas sin cuenta por ítem/categoría/marca, ni en la General)';
                    }
                } elseif ((str_contains($cod, 'SUBTOTAL') || str_contains($con, 'subtotal')) && $subGasto > 0) {
                    $res = $this->repartirComprasPorItem(
                        $db, $idEmpresa, $idCompra, (int)$rr['id_asiento_tipo'], $cuentaBaseRr, $subGasto,
                        'd.precio_total_sin_impuesto', '', false
                    );
                    $gastoLineas = $res['partes'];
                    if ($res['sin_cuenta'] >= 0.01) {
                        $sinCuentaExtra[] = $refConcepto . ' (algunas líneas sin cuenta por ítem/categoría/marca, ni en la General)';
                    }
                } elseif ((str_contains($cod, 'INVENTARIO') || str_contains($con, 'inventario')) && $subInventario > 0) {
                    $res = $this->repartirComprasPorItem(
                        $db, $idEmpresa, $idCompra, (int)$rr['id_asiento_tipo'], $cuentaBaseRr, $subInventario,
                        'd.precio_total_sin_impuesto', '', true
                    );
                    $inventarioLineas = $res['partes'];
                    if ($res['sin_cuenta'] >= 0.01) {
                        $sinCuentaExtra[] = $refConcepto . ' (algunas líneas sin cuenta por ítem/categoría/marca, ni en la General)';
                    }
                }
            }
        }

        return $this->ensamblarAdquisicion($reglas, $importeTotal, $subInventario, $subGasto, $propina, $ivaRows, $reversa, $gastoLineas, $porPagarLineas, $inventarioLineas, $sinCuentaExtra);
    }

    /**
     * Ensambla el cuerpo de un asiento de adquisición (compra o liquidación de compra) a partir
     * de los montos ya calculados, respetando la dirección (normal o reversa para notas de crédito).
     * Reglas base esperadas (asientos_tipo de 'adquisiciones_compras'): INVENTARIO (activo),
     * SUBTOTAL (gasto/costo), PORPAGAR (pasivo), PROPINA. El IVA crédito se pasa en $ivaRows
     * (lado natural = Debe). Reutilizado por compras y liquidaciones.
     *
     * $gastoLineas/$porPagarLineas/$inventarioLineas (2026-08-01, solo los usa Compras — Liquidaciones
     * sigue sin reparto por línea, no los pasa): si vienen no-null, cada uno reemplaza su línea única
     * por varias (una por cuenta resuelta vía Producto/Categoría/Marca), igual patrón que ya tenía
     * Subtotal/Gasto. $sinCuentaExtra son mensajes ya armados por el caller sobre líneas sin cuenta
     * en ese reparto, para no perder el detalle al fusionarlos con $reglasSinCuenta.
     *
     * @param array $ivaRows [['id_cuenta','cuenta_codigo','cuenta_nombre','valor'], ...]
     */
    private function ensamblarAdquisicion(array $reglas, float $importeTotal, float $subInventario, float $subGasto, float $propina, array $ivaRows, bool $reversa, ?array $gastoLineas = null, ?array $porPagarLineas = null, ?array $inventarioLineas = null, array $sinCuentaExtra = []): array
    {
        $detalles = [];
        // Reglas/tarifas activas sin cuenta configurada: se saltan, pero se recuerdan para poder
        // decir QUÉ falta si el asiento termina sin líneas o descuadrado (antes se descartaban
        // en silencio y solo se veía "Debe: $0" o un mensaje genérico).
        $reglasSinCuenta = $sinCuentaExtra;

        // helper local: agrega una línea respetando la dirección (normal/reversa)
        $push = function (array $r, float $valor, string $ladoNatural, string $refDefault) use (&$detalles, $reversa) {
            if ($valor <= 0) return;
            $lado = $reversa ? ($ladoNatural === 'debe' ? 'haber' : 'debe') : $ladoNatural;
            $detalles[] = [
                'id_cuenta_contable' => (int)$r['id_cuenta'],
                'cuenta_codigo'      => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $r['cuenta_nombre'] ?? '',
                'debe'               => $lado === 'debe' ? round($valor, 2) : 0.0,
                'haber'              => $lado === 'debe' ? 0.0 : round($valor, 2),
                'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? $refDefault,
            ];
        };

        // IVA crédito tributario (lado natural Debe; se invierte en notas de crédito)
        foreach ($ivaRows as $iva) {
            $valorIva = round((float)($iva['valor'] ?? 0), 2);
            if ($valorIva <= 0) continue;
            if (empty($iva['id_cuenta'])) {
                $reglasSinCuenta[] = 'IVA compras tarifa ' . ($iva['tarifa_nombre'] ?: $iva['codigo_porcentaje']);
                continue;
            }
            $push(
                ['id_cuenta' => (int)$iva['id_cuenta'], 'cuenta_codigo' => $iva['cuenta_codigo'] ?? '', 'cuenta_nombre' => $iva['cuenta_nombre'] ?? ''],
                $valorIva,
                'debe',
                'IVA crédito tributario'
            );
        }

        // Reglas base (cuerpo del asiento). La eventual diferencia de redondeo del documento se
        // cuadra al final con la cuenta de Ajuste por redondeo (aplicarAjusteRedondeo).
        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // La cuenta de Ajuste por redondeo no se mapea aquí: se aplica al final para cuadrar.
            if (str_contains($codigo, 'REDONDEO')) continue;

            $esPorPagar   = str_contains($codigo, 'PORPAGAR')   || str_contains($concepto, 'pagar');
            $esInventario = str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario');
            $esSubtotal   = str_contains($codigo, 'SUBTOTAL')   || str_contains($concepto, 'subtotal');
            // Si NO hay cuenta base para este concepto, solo se perdona cuando su reparto por línea
            // ya viene calculado (puede que la cuenta exista solo en Ítem/Categoría/Marca) — mismo
            // criterio que ventas/recibos/NC.
            $tieneRepartoPropio = ($esPorPagar && $porPagarLineas !== null)
                || ($esInventario && $inventarioLineas !== null)
                || ($esSubtotal && $gastoLineas !== null);
            if (empty($r['id_cuenta']) && !$tieneRepartoPropio) {
                $reglasSinCuenta[] = $r['asiento_tipo_referencia'] ?? $r['concepto']
                                  ?? $r['asiento_tipo_codigo'] ?? $r['codigo'] ?? 'sin nombre';
                continue;
            }

            $ladoNatural = ($r['debe_haber'] ?? 'debe') === 'haber' ? 'haber' : 'debe';
            $refConcepto = $r['concepto'] ?? $r['referencia'] ?? '';

            if ($esPorPagar) {
                if ($porPagarLineas !== null) {
                    foreach ($porPagarLineas as $gl) {
                        $m = round((float)($gl['monto'] ?? 0), 2);
                        if ($m <= 0) continue;
                        $push(
                            ['id_cuenta' => (int)$gl['id_cuenta'], 'cuenta_codigo' => $gl['cuenta_codigo'] ?? '', 'cuenta_nombre' => $gl['cuenta_nombre'] ?? '', 'asiento_tipo_referencia' => ($refConcepto ?: 'Por Pagar')],
                            $m, $ladoNatural, ($refConcepto ?: 'Por Pagar')
                        );
                    }
                    continue;
                }
                $push($r, $importeTotal, $ladoNatural, $refConcepto);
            } elseif ($esInventario) {
                if ($inventarioLineas !== null) {
                    foreach ($inventarioLineas as $gl) {
                        $m = round((float)($gl['monto'] ?? 0), 2);
                        if ($m <= 0) continue;
                        $push(
                            ['id_cuenta' => (int)$gl['id_cuenta'], 'cuenta_codigo' => $gl['cuenta_codigo'] ?? '', 'cuenta_nombre' => $gl['cuenta_nombre'] ?? '', 'asiento_tipo_referencia' => ($refConcepto ?: 'Inventario')],
                            $m, $ladoNatural, ($refConcepto ?: 'Inventario')
                        );
                    }
                    continue;
                }
                $push($r, $subInventario, $ladoNatural, $refConcepto);
            } elseif ($esSubtotal) {
                if ($gastoLineas !== null) {
                    // Reparto por dimensión: una línea por cuenta en vez de un solo Subtotal.
                    foreach ($gastoLineas as $gl) {
                        $m = round((float)($gl['monto'] ?? 0), 2);
                        if ($m <= 0) continue;
                        $push(
                            ['id_cuenta' => (int)$gl['id_cuenta'], 'cuenta_codigo' => $gl['cuenta_codigo'] ?? '', 'cuenta_nombre' => $gl['cuenta_nombre'] ?? '', 'asiento_tipo_referencia' => ($refConcepto ?: 'Gasto')],
                            $m, $ladoNatural, ($refConcepto ?: 'Gasto')
                        );
                    }
                    continue;
                }
                $push($r, $subGasto, $ladoNatural, $refConcepto);
            } elseif (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                $push($r, $propina, $ladoNatural, $refConcepto);
            }
            // DESCUENTO e ICE: el subtotal ya viene neto por línea; se omiten en v1.
        }

        // ── Validación de balance + cuadre por cuenta de Ajuste por redondeo ──
        // Los documentos del SRI suelen diferir en centavos entre importe_total y (subtotal + IVA)
        // por redondeo línea a línea. Esa diferencia (≤ 3 centavos) se lleva a la cuenta de Ajuste
        // por redondeo; un descuadre mayor = cuenta/impuesto realmente faltante → excepción.
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);
        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            if (!empty($reglasSinCuenta)) {
                throw new \Exception(
                    "No se generó ninguna línea del asiento. Falta asignar la cuenta contable de: " .
                    implode(', ', array_unique($reglasSinCuenta)) .
                    ". Configúrela en Contabilidad → Configuración contable, concepto «compras»."
                );
            }
            throw new \Exception("No se ha configurado ninguna cuenta para el asiento de adquisición o los montos son cero.");
        }

        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'compras', $reglasSinCuenta);

        return $detalles;
    }

    /**
     * Arma el asiento contable de una LIQUIDACIÓN DE COMPRA emitida.
     * Contablemente es una adquisición (dirección normal): reutiliza las cuentas de
     * 'adquisiciones_compras' y el IVA crédito por tarifa (iva_compras_factura), separando
     * el subtotal por línea (inventariable → Inventario, resto → Gasto).
     */
    public function generarAsientoLiquidacionCompra(int $idEmpresa, int $idLiquidacion): array
    {
        $db = \App\core\Database::getConnection();

        // 1. Totales (la liquidación siempre va en dirección normal de adquisición)
        $stCab = $db->prepare("SELECT importe_total, total_sin_impuestos, id_proveedor FROM liquidaciones_cabecera WHERE id = ?");
        $stCab->execute([$idLiquidacion]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
        $importeTotal = round((float)($cab['importe_total']      ?? 0), 2);
        $subtotal     = round((float)($cab['total_sin_impuestos'] ?? 0), 2);
        $idProveedor  = (int) ($cab['id_proveedor'] ?? 0);
        if ($importeTotal <= 0.0 && $subtotal <= 0.0) {
            return [];
        }

        // 2. Separar el subtotal por línea (inventariable → Inventario, resto → Gasto)
        $subInventario = 0.0;
        $subGasto      = 0.0;
        $stDet = $db->prepare(
            "SELECT d.precio_total_sin_impuesto, d.id_producto, p.inventariable, p.tipo_produccion
             FROM liquidaciones_detalle d
             LEFT JOIN productos p ON p.id = d.id_producto
             WHERE d.id_cabecera = ?"
        );
        $stDet->execute([$idLiquidacion]);
        foreach ($stDet as $d) {
            $monto = round((float)($d['precio_total_sin_impuesto'] ?? 0), 2);
            $inv   = $d['inventariable'] ?? null;
            $esInventariable = !empty($d['id_producto'])
                && ($inv === true || $inv === 't' || $inv === 'true' || $inv == 1 || $inv === '1')
                && (($d['tipo_produccion'] ?? '') !== '02');
            if ($esInventariable) {
                $subInventario += $monto;
            } else {
                $subGasto += $monto;
            }
        }
        $subInventario = round($subInventario, 2);
        $subGasto      = round($subGasto, 2);
        $diferencia = round($subtotal - ($subInventario + $subGasto), 2);
        if (abs($diferencia) >= 0.01) {
            $subGasto = round($subGasto + $diferencia, 2);
        }

        // 3. IVA crédito por tarifa (reutiliza la config de compras: iva_compras_factura), con la misma
        // cascada proveedor > ítem > categoría > marca > general que una compra normal.
        $ivaRows = [];
        $sqlIva = "SELECT i.codigo_porcentaje,
                          SUM(i.valor)  AS total_valor,
                          COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta) AS id_cuenta,
                          pc.codigo     AS cuenta_codigo,
                          pc.nombre     AS cuenta_nombre,
                          t.tarifa      AS tarifa_nombre
                   FROM liquidaciones_detalle_impuestos i
                   JOIN liquidaciones_detalle d ON i.id_detalle = d.id
                   LEFT JOIN productos p ON p.id = d.id_producto
                   LEFT JOIN tarifa_iva t ON CAST(t.codigo AS INTEGER) = CAST(i.codigo_porcentaje AS INTEGER)
                   LEFT JOIN asientos_programados ap_prov
                          ON ap_prov.id_referencia = :id_proveedor AND ap_prov.tipo_referencia = 'proveedor'
                         AND ap_prov.id_asiento_tipo = 0 AND ap_prov.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_prov.direccion_iva = 'compra' AND ap_prov.id_empresa = :emp AND ap_prov.eliminado = false
                   LEFT JOIN asientos_programados ap_item
                          ON TRIM(ap_item.referencia_texto) = TRIM(d.descripcion) AND ap_item.tipo_referencia = 'item_compra'
                         AND ap_item.id_asiento_tipo = 0 AND ap_item.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_item.direccion_iva = 'compra' AND ap_item.id_empresa = :emp AND ap_item.eliminado = false
                   LEFT JOIN asientos_programados ap_c
                          ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                         AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_c.direccion_iva = 'compra' AND ap_c.id_empresa = :emp AND ap_c.eliminado = false
                   LEFT JOIN asientos_programados ap_m
                          ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                         AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_m.direccion_iva = 'compra' AND ap_m.id_empresa = :emp AND ap_m.eliminado = false
                   LEFT JOIN asientos_programados ap_gen
                          ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                         AND ap_gen.tipo_referencia = 'iva_compras_factura'
                         AND ap_gen.id_empresa      = :emp
                         AND ap_gen.eliminado       = false
                   LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta)
                   WHERE d.id_cabecera = :id AND i.codigo_impuesto = '2'
                   GROUP BY i.codigo_porcentaje, COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre, t.tarifa";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idLiquidacion, ':id_proveedor' => $idProveedor]);
        while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
            $ivaRows[] = [
                'id_cuenta'         => $row['id_cuenta'],
                'cuenta_codigo'     => $row['cuenta_codigo'],
                'cuenta_nombre'     => $row['cuenta_nombre'],
                'valor'             => $row['total_valor'],
                'codigo_porcentaje' => $row['codigo_porcentaje'],
                'tarifa_nombre'     => $row['tarifa_nombre'],
            ];
        }

        // 4. Reglas base de adquisiciones_compras (mismas cuentas que una compra)
        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'adquisiciones_compras');

        return $this->ensamblarAdquisicion($reglas, $importeTotal, $subInventario, $subGasto, 0.0, $ivaRows, false);
    }

    /**
     * Arma el asiento contable de la nacionalización de una IMPORTACIÓN (concepto
     * 'adquisiciones_importacion'). No usa la cascada por proveedor/producto de
     * 'adquisiciones_compras' (Fase 1): las 7 cuentas del concepto se resuelven a nivel
     * general (asientos_programados con tipo_referencia = 'adquisiciones_importacion'),
     * configurables en /config/asientos-contables igual que cualquier otro concepto.
     *
     * Construcción balanceada por diseño (ver ImportacionesService::calcularTotales):
     *   Debe  INVENTARIOIMPORTACION       = costo_total_nacionalizado (FOB facturado + TODOS los gastos capitalizables)
     *   Debe  IVAIMPORTACION              = IVA pagado en la DAI (crédito tributario, no se capitaliza)
     *   Debe  ISDIMPORTACION              = ISD pagado (gasto financiero, no se capitaliza)
     *   Debe  OTROSGASTOSIMPORTACION      = gastos manuales no prorrateables distintos de IVA/ISD
     *   Haber PORPAGARPROVEEDOREXTERIOR   = total facturado por el proveedor del exterior
     *   Haber PORPAGARTRIBUTOSADUANEROS   = total de gastos manuales de la DAI (arancel, fodinfa, agente, iva, isd, otros)
     *   Haber RECLASIFICACIONGASTOIMPORTACION = gastos que YA se registraron como Compra/Liquidación
     *         (su propio documento generó su propio gasto+CxP; aquí solo se reclasifica a Inventario)
     */
    public function generarAsientoImportacion(int $idEmpresa, int $idImportacion): array
    {
        $db = \App\core\Database::getConnection();

        $stCab = $db->prepare(
            "SELECT total_gastos_capitalizables, total_iva, total_isd, total_otros_gastos, costo_total_nacionalizado
             FROM importaciones_cabecera WHERE id = ?"
        );
        $stCab->execute([$idImportacion]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];

        $costoTotalNacionalizado = round((float) ($cab['costo_total_nacionalizado'] ?? 0), 2);
        $totalIva                = round((float) ($cab['total_iva'] ?? 0), 2);
        $totalIsd                = round((float) ($cab['total_isd'] ?? 0), 2);
        $totalOtros              = round((float) ($cab['total_otros_gastos'] ?? 0), 2);
        if ($costoTotalNacionalizado <= 0.0 && $totalIva <= 0.0 && $totalIsd <= 0.0 && $totalOtros <= 0.0) {
            return [];
        }

        $stFact = $db->prepare("SELECT COALESCE(SUM(monto_usd),0) FROM importaciones_factura_exterior WHERE id_importacion = ? AND eliminado = false");
        $stFact->execute([$idImportacion]);
        $totalFacturaExterior = round((float) $stFact->fetchColumn(), 2);

        $stGastos = $db->prepare(
            "SELECT origen, COALESCE(SUM(monto),0) AS total
             FROM importaciones_gastos
             WHERE id_importacion = ? AND eliminado = false
             GROUP BY origen"
        );
        $stGastos->execute([$idImportacion]);
        $totalManual = 0.0;
        $totalVinculado = 0.0;
        foreach ($stGastos as $row) {
            if ($row['origen'] === 'dai_manual') {
                $totalManual = round((float) $row['total'], 2);
            } else {
                $totalVinculado += round((float) $row['total'], 2);
            }
        }
        $totalVinculado = round($totalVinculado, 2);

        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'adquisiciones_importacion');
        $porCodigo = [];
        foreach ($reglas as $r) {
            $porCodigo[strtoupper((string) $r['codigo'])] = $r;
        }

        // Inventario: si la empresa configuró AMBAS cuentas (Materia Prima y Producto
        // Terminado), se reparte el costo nacionalizado entre las dos según la línea
        // de producto (importaciones_detalle.tipo_inventario) en vez de ir todo a la
        // cuenta general única 'INVENTARIOIMPORTACION'. El reparto usa el costo YA
        // prorrateado por línea (más preciso que estimar por FOB), disponible en este
        // punto porque el asiento se genera después de calcularProrrateo()/aplicarNacionalizacion().
        // Las líneas 'activo_fijo' caen en el balde Producto Terminado (no tienen cuenta
        // propia en este reparto; solo afectan el casillero de la Declaración de IVA).
        $usaSplitInventario = !empty($porCodigo['INVENTARIOIMPORTACIONMATERIAPRIMA']['id_cuenta'])
            && !empty($porCodigo['INVENTARIOIMPORTACIONPRODUCTOTERMINADO']['id_cuenta']);

        $costoMateriaPrima = 0.0;
        $costoProductoTerminado = 0.0;
        if ($usaSplitInventario) {
            $stDet = $db->prepare(
                "SELECT tipo_inventario, COALESCE(costo_total_nacionalizado, 0) AS costo
                 FROM importaciones_detalle WHERE id_importacion = ? AND eliminado = false"
            );
            $stDet->execute([$idImportacion]);
            foreach ($stDet as $d) {
                if (($d['tipo_inventario'] ?? '') === 'materia_prima') {
                    $costoMateriaPrima += (float) $d['costo'];
                } else {
                    $costoProductoTerminado += (float) $d['costo'];
                }
            }
            $costoMateriaPrima = round($costoMateriaPrima, 2);
            // Residual de redondeo (si lo hay) se absorbe en Producto Terminado, para
            // que la suma de las dos líneas cuadre exacto con $costoTotalNacionalizado.
            $costoProductoTerminado = round($costoTotalNacionalizado - $costoMateriaPrima, 2);
        }

        $lineas = $usaSplitInventario
            ? [
                ['codigo' => 'INVENTARIOIMPORTACIONMATERIAPRIMA',     'monto' => $costoMateriaPrima],
                ['codigo' => 'INVENTARIOIMPORTACIONPRODUCTOTERMINADO', 'monto' => $costoProductoTerminado],
            ]
            : [
                ['codigo' => 'INVENTARIOIMPORTACION', 'monto' => $costoTotalNacionalizado],
            ];
        $lineas = array_merge($lineas, [
            ['codigo' => 'IVAIMPORTACION',                  'monto' => $totalIva],
            ['codigo' => 'ISDIMPORTACION',                  'monto' => $totalIsd],
            ['codigo' => 'OTROSGASTOSIMPORTACION',           'monto' => $totalOtros],
            ['codigo' => 'PORPAGARPROVEEDOREXTERIOR',        'monto' => $totalFacturaExterior],
            ['codigo' => 'PORPAGARTRIBUTOSADUANEROS',        'monto' => $totalManual],
            ['codigo' => 'RECLASIFICACIONGASTOIMPORTACION',  'monto' => $totalVinculado],
        ]);

        $detalles = [];
        foreach ($lineas as $l) {
            if ($l['monto'] <= 0.0) continue;
            $regla = $porCodigo[$l['codigo']] ?? null;
            if (!$regla || empty($regla['id_cuenta'])) {
                throw new \Exception("No se ha configurado la cuenta contable para '{$l['codigo']}' del concepto Importaciones. Configúrela en Contabilidad → Configuración contable, concepto «importaciones».");
            }
            $esDebe = ($regla['debe_haber'] ?? 'debe') === 'debe';
            $detalles[] = [
                'id_cuenta_contable' => (int) $regla['id_cuenta'],
                'cuenta_codigo'      => $regla['cuenta_codigo'],
                'cuenta_nombre'      => $regla['cuenta_nombre'],
                'debe'               => $esDebe ? round($l['monto'], 2) : 0.0,
                'haber'              => $esDebe ? 0.0 : round($l['monto'], 2),
                'referencia_detalle' => $regla['concepto'] ?? $regla['detalle'] ?? $l['codigo'],
            ];
        }

        if (empty($detalles)) {
            return [];
        }

        return $this->aplicarAjusteRedondeo($detalles, $reglas, 'la importación');
    }

    /**
     * Arma el asiento de ALTA de un activo fijo dado de alta MANUALMENTE (sin factura
     * de compra — cuando hay factura, esa compra ya generó su propio asiento y este
     * método no se invoca). Debe = cuenta de Activo del propio activo (id_cuenta_activo);
     * Haber = contrapartida configurada en el propio activo (id_cuenta_contrapartida_alta)
     * o, en su defecto, la regla general del concepto 'activos_fijos_alta'
     * (código CONTRAPARTIDAALTAACTIVOFIJO), configurable en Configuración Contable.
     */
    public function generarAsientoAltaActivoFijo(int $idEmpresa, int $idActivo): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT a.valor_adquisicion, a.id_cuenta_contrapartida_alta, a.nombre,
                    a.id_cuenta_activo,
                    pa.codigo AS cuenta_activo_codigo, pa.nombre AS cuenta_activo_nombre
             FROM activos_fijos a
             INNER JOIN plan_cuentas pa ON pa.id = a.id_cuenta_activo
             WHERE a.id = ? AND a.id_empresa = ?"
        );
        $st->execute([$idActivo, $idEmpresa]);
        $activo = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$activo) return [];

        $valor = round((float) $activo['valor_adquisicion'], 2);
        if ($valor <= 0.0) return [];

        $idCuentaContrapartida = !empty($activo['id_cuenta_contrapartida_alta']) ? (int) $activo['id_cuenta_contrapartida_alta'] : null;
        $cuentaContrapartidaCodigo = null;
        $cuentaContrapartidaNombre = null;

        if ($idCuentaContrapartida) {
            $stC = $db->prepare("SELECT codigo, nombre FROM plan_cuentas WHERE id = ?");
            $stC->execute([$idCuentaContrapartida]);
            $c = $stC->fetch(\PDO::FETCH_ASSOC) ?: [];
            $cuentaContrapartidaCodigo = $c['codigo'] ?? null;
            $cuentaContrapartidaNombre = $c['nombre'] ?? null;
        } else {
            foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'activos_fijos_alta') as $r) {
                if (strtoupper((string) $r['codigo']) === 'CONTRAPARTIDAALTAACTIVOFIJO' && !empty($r['id_cuenta'])) {
                    $idCuentaContrapartida = (int) $r['id_cuenta'];
                    $cuentaContrapartidaCodigo = $r['cuenta_codigo'];
                    $cuentaContrapartidaNombre = $r['cuenta_nombre'];
                    break;
                }
            }
        }

        if (!$idCuentaContrapartida) {
            throw new \Exception(
                "No se ha configurado la cuenta contrapartida para el alta manual de activos fijos. " .
                "Selecciónela en el propio activo o configure la regla general en Contabilidad → " .
                "Configuración contable, concepto «Activos Fijos - Alta»."
            );
        }

        return [
            [
                'id_cuenta_contable' => (int) $activo['id_cuenta_activo'],
                'cuenta_codigo'      => $activo['cuenta_activo_codigo'],
                'cuenta_nombre'      => $activo['cuenta_activo_nombre'],
                'debe'               => $valor,
                'haber'              => 0.0,
                'referencia_detalle' => 'Alta de activo fijo - ' . $activo['nombre'],
            ],
            [
                'id_cuenta_contable' => $idCuentaContrapartida,
                'cuenta_codigo'      => $cuentaContrapartidaCodigo,
                'cuenta_nombre'      => $cuentaContrapartidaNombre,
                'debe'               => 0.0,
                'haber'              => $valor,
                'referencia_detalle' => 'Contrapartida alta activo fijo - ' . $activo['nombre'],
            ],
        ];
    }

    /**
     * Arma el asiento CONSOLIDADO del lote mensual de depreciación de activos fijos:
     * agrupa las cuotas ya insertadas en activos_fijos_depreciaciones (para este
     * $idLote) por PAR DE CUENTAS del activo (gasto + depreciación acumulada) y arma
     * una línea Debe (Gasto) + Haber (Depreciación Acumulada) por cada par, leyendo las
     * cuentas directo de activos_fijos (no pasan por la cascada de asientos_programados).
     */
    public function generarAsientoDepreciacionLote(int $idEmpresa, int $idLote): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT a.id_cuenta_gasto_depreciacion, a.id_cuenta_depreciacion_acumulada,
                    pg.codigo AS gasto_codigo, pg.nombre AS gasto_nombre,
                    pa.codigo AS acumulada_codigo, pa.nombre AS acumulada_nombre,
                    SUM(d.valor_depreciado) AS total
             FROM activos_fijos_depreciaciones d
             INNER JOIN activos_fijos a ON d.id_activo = a.id
             INNER JOIN plan_cuentas pg ON pg.id = a.id_cuenta_gasto_depreciacion
             INNER JOIN plan_cuentas pa ON pa.id = a.id_cuenta_depreciacion_acumulada
             WHERE d.id_lote = ? AND d.eliminado = false
             GROUP BY a.id_cuenta_gasto_depreciacion, a.id_cuenta_depreciacion_acumulada,
                      pg.codigo, pg.nombre, pa.codigo, pa.nombre
             ORDER BY pg.codigo ASC, pa.codigo ASC"
        );
        $st->execute([$idLote]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) return [];

        $detalles = [];
        foreach ($rows as $r) {
            $monto = round((float) $r['total'], 2);
            if ($monto <= 0.0) continue;
            $detalles[] = [
                'id_cuenta_contable' => (int) $r['id_cuenta_gasto_depreciacion'],
                'cuenta_codigo'      => $r['gasto_codigo'],
                'cuenta_nombre'      => $r['gasto_nombre'],
                'debe'               => $monto,
                'haber'              => 0.0,
                'referencia_detalle' => 'Gasto depreciación - ' . $r['gasto_nombre'],
            ];
            $detalles[] = [
                'id_cuenta_contable' => (int) $r['id_cuenta_depreciacion_acumulada'],
                'cuenta_codigo'      => $r['acumulada_codigo'],
                'cuenta_nombre'      => $r['acumulada_nombre'],
                'debe'               => 0.0,
                'haber'              => $monto,
                'referencia_detalle' => 'Depreciación acumulada - ' . $r['acumulada_nombre'],
            ];
        }

        if (empty($detalles)) return [];

        // Cuadra por construcción (cada par de cuentas aporta el mismo monto a Debe y Haber);
        // se aplica el ajuste por redondeo igual que el resto de conceptos, por si acaso.
        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'activos_fijos_depreciacion');
        return $this->aplicarAjusteRedondeo($detalles, $reglas, 'la depreciación de activos fijos');
    }

    /**
     * Distribución dinámica para Compras, Liquidaciones, Notas de Crédito, etc.
     */
    private function armarDistribucionDinamica(array $reglas, array $data): array
    {
        $importeTotal = round((float)($data['importe_total'] ?? $data['total'] ?? 0), 2);
        $subtotal = round((float)($data['total_sin_impuestos'] ?? $data['subtotal'] ?? 0), 2);
        $descuento = round((float)($data['total_descuento'] ?? $data['descuento'] ?? 0), 2);
        $totalIce = round((float)($data['total_ice'] ?? 0), 2);
        $propina = round((float)($data['propina'] ?? 0), 2);
        // Intentar calcular IVA
        $totalIvaTotal = round((float)($data['total_iva'] ?? max(0.0, $importeTotal - $subtotal - $totalIce - $propina)), 2);

        $detalles = [];
        // Reglas activas sin cuenta configurada: se saltan, pero se recuerdan para poder decir
        // QUÉ falta si el asiento termina vacío o descuadrado (antes se descartaban en silencio).
        $reglasSinCuenta = [];

        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // La cuenta de Ajuste por redondeo no se mapea aquí: se aplica al final para cuadrar.
            if (str_contains($codigo, 'REDONDEO')) continue;

            if (empty($r['id_cuenta'])) {
                $reglasSinCuenta[] = $r['asiento_tipo_referencia'] ?? $r['concepto']
                                  ?? $r['asiento_tipo_codigo'] ?? $r['codigo'] ?? 'sin nombre';
                continue;
            }

            $debe  = 0.00;
            $haber = 0.00;
            $valorMapeado = 0.00;

            if (str_contains($codigo, 'PORPAGAR') || str_contains($concepto, 'pagar') || str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valorMapeado = $importeTotal;
            } elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal') || str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valorMapeado = $subtotal;
            } elseif (str_contains($codigo, 'DESC') || str_contains($concepto, 'descuento')) {
                $valorMapeado = $descuento;
            } elseif (str_contains($codigo, 'ICE') || str_contains($concepto, 'ice')) {
                $valorMapeado = $totalIce;
            } elseif (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                $valorMapeado = $propina;
            } elseif (str_contains($codigo, 'IVA') || str_contains($concepto, 'iva')) {
                $valorMapeado = $totalIvaTotal;
            } else {
                $valorMapeado = $importeTotal; // Fallback
            }

            if ($valorMapeado > 0) {
                if (($r['debe_haber'] ?? 'debe') === 'debe') {
                    $debe = $valorMapeado;
                } else {
                    $haber = $valorMapeado;
                }

                $detalles[] = [
                    'id_cuenta_contable' => (int)$r['id_cuenta'],
                    'cuenta_codigo'      => $r['cuenta_codigo'],
                    'cuenta_nombre'      => $r['cuenta_nombre'],
                    'debe'               => round($debe, 2),
                    'haber'              => round($haber, 2),
                    'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '',
                ];
            }
        }

        // Validación básica de balance
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);

        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            if (!empty($reglasSinCuenta)) {
                throw new \Exception(
                    "No se generó ninguna línea del asiento. Falta asignar la cuenta contable de: " .
                    implode(', ', array_unique($reglasSinCuenta)) .
                    ". Configúrela en Contabilidad → Configuración contable."
                );
            }
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }

        // Cuadre exacto vía la cuenta de Ajuste por redondeo; descuadre > 3 centavos → excepción.
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'el documento', $reglasSinCuenta);

        return $detalles;
    }

    /**
     * Arma el asiento contable de una NOTA DE CRÉDITO de venta (devolución / descuento al cliente).
     *
     * Reusa las MISMAS cuentas configuradas para la factura de venta (ventas_factura) pero
     * INVERTIDAS, porque la NC revierte la venta:
     *   - Comercial: DEBE Ventas + IVA ; HABER Cuentas por cobrar.
     *   - Costo (si está configurado y hay reingreso en Kardex): DEBE Inventario ; HABER Costo de Ventas.
     *
     * El costo se toma del reingreso al Kardex que ya hace la NC (referencia_tipo='nota_credito',
     * tipo_movimiento='entrada'), igual que la factura toma el costo de la salida.
     * Devuelve [] si no hay montos.
     */
    public function generarAsientoNotaCreditoVenta(int $idEmpresa, int $idNotaCredito): array
    {
        $db = \App\core\Database::getConnection();

        // ── 1. Totales de la NC ──
        $stCab = $db->prepare(
            "SELECT importe_total, total_sin_impuestos, COALESCE(total_descuento,0) AS total_descuento, id_cliente
             FROM notas_credito_cabecera WHERE id = ?"
        );
        $stCab->execute([$idNotaCredito]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
        $importeTotal = round((float)($cab['importe_total']      ?? 0), 2);
        $subtotal     = round((float)($cab['total_sin_impuestos'] ?? 0), 2);
        $idCliente    = (int) ($cab['id_cliente'] ?? 0);
        if ($importeTotal <= 0.0 && $subtotal <= 0.0) {
            return [];
        }

        // ── 2. Costo que la NC reingresó al inventario (Kardex entrada por la devolución) ──
        $stCosto = $db->prepare(
            "SELECT COALESCE(SUM(costo_total),0) FROM inventario_kardex
             WHERE referencia_tipo = 'nota_credito' AND referencia_id = ?
               AND tipo_movimiento = 'entrada' AND eliminado = false"
        );
        $stCosto->execute([$idNotaCredito]);
        $costo = round((float)$stCosto->fetchColumn(), 2);

        // Se construye en el "lado natural de venta" y al final se INVIERTE.
        $comercial   = []; // por cobrar + ventas + IVA (lado natural)
        $costoLineas = []; // costo + inventario (lado natural)

        // ── 3. IVA por tarifa: mismas cuentas que la factura (iva_ventas_factura), lado natural HABER,
        // con la misma cascada cliente > producto > categoría > marca > general que una factura de venta ──
        $sqlIva = "SELECT i.codigo_porcentaje, SUM(i.valor) AS total_valor,
                          COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta) AS id_cuenta,
                          pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                   FROM notas_credito_detalle_impuestos i
                   JOIN notas_credito_detalle d ON i.id_nota_credito_detalle = d.id
                   LEFT JOIN productos p ON p.id = d.id_producto
                   LEFT JOIN asientos_programados ap_cli
                          ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                         AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_cli.direccion_iva = 'venta' AND ap_cli.id_empresa = :emp AND ap_cli.eliminado = false
                   LEFT JOIN asientos_programados ap_p
                          ON ap_p.id_referencia = d.id_producto AND ap_p.tipo_referencia = 'producto'
                         AND ap_p.id_asiento_tipo = 0 AND ap_p.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_p.direccion_iva = 'venta' AND ap_p.id_empresa = :emp AND ap_p.eliminado = false
                   LEFT JOIN asientos_programados ap_c
                          ON ap_c.id_referencia = p.id_categoria AND ap_c.tipo_referencia = 'categoria'
                         AND ap_c.id_asiento_tipo = 0 AND ap_c.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_c.direccion_iva = 'venta' AND ap_c.id_empresa = :emp AND ap_c.eliminado = false
                   LEFT JOIN asientos_programados ap_m
                          ON ap_m.id_referencia = p.id_marca AND ap_m.tipo_referencia = 'marca'
                         AND ap_m.id_asiento_tipo = 0 AND ap_m.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_m.direccion_iva = 'venta' AND ap_m.id_empresa = :emp AND ap_m.eliminado = false
                   LEFT JOIN asientos_programados ap_gen
                          ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                         AND ap_gen.tipo_referencia = 'iva_ventas_factura'
                         AND ap_gen.id_empresa      = :emp AND ap_gen.eliminado = false
                   LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta)
                   WHERE d.id_nota_credito = :id AND i.codigo_impuesto = '2'
                   GROUP BY i.codigo_porcentaje, COALESCE(ap_cli.id_cuenta, ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idNotaCredito, ':id_cliente' => $idCliente]);
        while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
            $valorIva = round((float)$row['total_valor'], 2);
            if ($valorIva <= 0 || empty($row['id_cuenta'])) continue;
            $comercial[] = [
                'id_cuenta_contable' => (int)$row['id_cuenta'],
                'cuenta_codigo'      => $row['cuenta_codigo'],
                'cuenta_nombre'      => $row['cuenta_nombre'],
                'debe'               => 0.0,
                'haber'              => $valorIva,
                'referencia_detalle' => 'IVA Ventas (NC)',
            ];
        }

        // ── 4. Reglas base de ventas_factura → por cobrar, subtotal, costo, inventario.
        // Mismo diseño que ventas_factura/recibos_venta (2026-08-01): si el CLIENTE tiene reglas
        // propias, mandan sobre toda la NC (Opción 2); si no, se reparte por Producto → Categoría →
        // Marca con datos reales de notas_credito_detalle. ICE/Propina/Descuento siguen sin aplicar
        // a la NC (decisión de diseño previa, no tocada aquí).
        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura');
        $customAccounts = $this->resolverCuentasPorMetodo($idEmpresa, 'ventas_factura', 'cliente', ['id_cliente' => $idCliente]);
        $entidadTieneReglas = !empty($customAccounts);
        foreach ($reglas as &$rr) {
            $idTipo = (int) $rr['id_asiento_tipo'];
            if (isset($customAccounts[$idTipo])) {
                $rr['id_cuenta']     = $customAccounts[$idTipo]['id_cuenta'];
                $rr['cuenta_codigo'] = $customAccounts[$idTipo]['cuenta_codigo'];
                $rr['cuenta_nombre'] = $customAccounts[$idTipo]['cuenta_nombre'];
            }
        }
        unset($rr);
        $aplicaRepartoPorCategoria = !$entidadTieneReglas && $idNotaCredito > 0;

        $joinImpuestosPorLinea = "LEFT JOIN (
                SELECT id_nota_credito_detalle, SUM(valor) AS total_impuestos
                FROM notas_credito_detalle_impuestos WHERE codigo_impuesto = '2' GROUP BY id_nota_credito_detalle
            ) imp_cxc ON imp_cxc.id_nota_credito_detalle = d.id";
        $joinCostoPorLinea = "LEFT JOIN inventario_kardex kc
                ON kc.referencia_tipo = 'nota_credito' AND kc.referencia_id = d.id_nota_credito
               AND kc.id_producto = d.id_producto AND kc.tipo_movimiento = 'entrada' AND kc.eliminado = false";

        $reglasSinCuenta = [];
        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['codigo']   ?? '');
            $concepto = strtolower($r['concepto'] ?? $r['referencia'] ?? '');

            $esPorCobrar  = str_contains($codigo, 'PORCOBRAR')  || str_contains($concepto, 'cobrar');
            $esSubtotal   = str_contains($codigo, 'SUBTOTAL')   || str_contains($concepto, 'subtotal');
            $esCosto      = str_contains($codigo, 'COSTO')      || str_contains($concepto, 'costo');
            $esInventario = str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario');
            if (!$esPorCobrar && !$esSubtotal && !$esCosto && !$esInventario) {
                continue; // ICE / propina / descuento / IVA-base no aplican a la NC
            }

            $refBase = ($r['concepto'] ?? $r['referencia'] ?? '') . ' (NC)';

            if (empty($r['id_cuenta']) && !$aplicaRepartoPorCategoria) {
                $reglasSinCuenta[] = $refBase;
                continue;
            }

            if ($esPorCobrar) {
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaNC(
                        $db, $idEmpresa, $idNotaCredito, $r, $importeTotal,
                        '(d.precio_total_sin_impuesto + COALESCE(imp_cxc.total_impuestos, 0))', $joinImpuestosPorLinea,
                        $refBase, false, $comercial, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
                    $comercial[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'] ?? '', 'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                        'debe' => $lado === 'debe' ? round($importeTotal, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($importeTotal, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
            } elseif ($esSubtotal) {
                if ($subtotal <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaNC(
                        $db, $idEmpresa, $idNotaCredito, $r, $subtotal,
                        'd.precio_total_sin_impuesto', '',
                        $refBase, false, $comercial, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
                    $comercial[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'] ?? '', 'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                        'debe' => $lado === 'debe' ? round($subtotal, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($subtotal, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
            } elseif ($esCosto || $esInventario) {
                if ($costo <= 0) continue;
                if ($aplicaRepartoPorCategoria) {
                    $this->aplicarRepartoPorCategoriaNC(
                        $db, $idEmpresa, $idNotaCredito, $r, $costo,
                        'COALESCE(kc.costo_total, 0)', $joinCostoPorLinea,
                        $refBase, true, $comercial, $costoLineas, $reglasSinCuenta
                    );
                } elseif (!empty($r['id_cuenta'])) {
                    $lado = (($r['debe_haber'] ?? 'debe') === 'debe') ? 'debe' : 'haber';
                    $costoLineas[] = [
                        'id_cuenta_contable' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'] ?? '', 'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                        'debe' => $lado === 'debe' ? round($costo, 2) : 0.0, 'haber' => $lado === 'debe' ? 0.0 : round($costo, 2),
                        'referencia_detalle' => $refBase,
                    ];
                }
            }
        }

        // El bloque de costo solo entra si está COMPLETO y CUADRADO (ambas cuentas configuradas).
        $detallesNatural = $comercial;
        $costoGenerado = false;
        $motivoCostoPendiente = null;
        if (!empty($costoLineas)) {
            $dc = round(array_sum(array_column($costoLineas, 'debe')),  2);
            $hc = round(array_sum(array_column($costoLineas, 'haber')), 2);
            if ($dc > 0 && $dc === $hc) {
                $detallesNatural = array_merge($detallesNatural, $costoLineas);
                $costoGenerado = true;
            } else {
                $motivoCostoPendiente = 'bloque_incompleto_descuadrado';
            }
        } elseif ($costo > 0) {
            $motivoCostoPendiente = 'cuenta_no_configurada';
        }

        // ── 5. INVERTIR Debe/Haber → asiento de la nota de crédito ──
        $detalles = [];
        foreach ($detallesNatural as $d) {
            $detalles[] = [
                'id_cuenta_contable' => $d['id_cuenta_contable'],
                'cuenta_codigo'      => $d['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $d['cuenta_nombre'] ?? '',
                'debe'               => round((float)$d['haber'], 2),
                'haber'              => round((float)$d['debe'], 2),
                'referencia_detalle' => $d['referencia_detalle'] ?? 'Nota de crédito',
            ];
        }

        // ── 6. Validación de balance ──
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);
        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            if (!empty($reglasSinCuenta)) {
                throw new \Exception(
                    "No se generó ninguna línea del asiento. Falta asignar la cuenta contable de: " .
                    implode(', ', array_unique($reglasSinCuenta)) .
                    ". Configúrela en Contabilidad → Configuración contable, concepto «ventas»."
                );
            }
            throw new \Exception("No hay cuentas configuradas para la nota de crédito de venta o los montos son cero.");
        }

        // Cuadre exacto vía la cuenta de Ajuste por redondeo (reusa la config de ventas_factura).
        // Un descuadre > 3 centavos = error real de configuración → excepción.
        $idAsientoTipoRedondeo = 0;
        foreach ($reglas as $r) {
            if (str_contains(strtoupper($r['codigo'] ?? ''), 'REDONDEO')) {
                $idAsientoTipoRedondeo = (int) ($r['id_asiento_tipo'] ?? 0);
                break;
            }
        }
        $cuentaRedondeoCategoria = ($aplicaRepartoPorCategoria && $idAsientoTipoRedondeo > 0)
            ? $this->resolverCuentaRedondeoPorCategoria($db, $idEmpresa, $idAsientoTipoRedondeo, 'notas_credito_detalle', 'id_nota_credito', $idNotaCredito)
            : null;
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'ventas (nota de crédito)', $reglasSinCuenta, $cuentaRedondeoCategoria);

        // Seguimiento de costeo, al final — ver la misma nota en armarDistribucionVentasFactura().
        if ($idNotaCredito > 0) {
            $this->costeoRepo->registrar(
                $idEmpresa, 'nota_credito_venta', $idNotaCredito,
                $costo > 0, $costoGenerado, $costo, $motivoCostoPendiente
            );
        }

        return $detalles;
    }

    /**
     * Arma el asiento contable de una nota de débito de venta.
     *   DEBE : Cuentas por Cobrar, por el valor total (aumenta lo que debe el cliente).
     *   HABER: Ventas (subtotal) + IVA Ventas (si aplica).
     * A diferencia de la NC, NO se invierte (una ND es del mismo signo que una
     * factura) y NO hay líneas de costo/inventario (la ND no tiene producto).
     */
    public function generarAsientoNotaDebitoVenta(int $idEmpresa, int $idNotaDebito): array
    {
        $db = \App\core\Database::getConnection();

        $stCab = $db->prepare(
            "SELECT importe_total, total_sin_impuestos, id_cliente
             FROM nota_debito_cabecera WHERE id = ?"
        );
        $stCab->execute([$idNotaDebito]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
        $importeTotal = round((float)($cab['importe_total']      ?? 0), 2);
        $subtotal     = round((float)($cab['total_sin_impuestos'] ?? 0), 2);
        $idCliente    = (int) ($cab['id_cliente'] ?? 0);
        if ($importeTotal <= 0.0 && $subtotal <= 0.0) {
            return [];
        }

        $comercial = []; // por cobrar + ventas + IVA

        // IVA por tarifa: los impuestos de la ND están a nivel de cabecera (sin
        // producto), así que la cascada solo llega hasta cliente > general.
        $sqlIva = "SELECT i.codigo_porcentaje, SUM(i.valor) AS total_valor,
                          COALESCE(ap_cli.id_cuenta, ap_gen.id_cuenta) AS id_cuenta,
                          pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                   FROM nota_debito_impuestos i
                   LEFT JOIN asientos_programados ap_cli
                          ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                         AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = i.codigo_porcentaje::text
                         AND ap_cli.direccion_iva = 'venta' AND ap_cli.id_empresa = :emp AND ap_cli.eliminado = false
                   LEFT JOIN asientos_programados ap_gen
                          ON ap_gen.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                         AND ap_gen.tipo_referencia = 'iva_ventas_factura'
                         AND ap_gen.id_empresa      = :emp AND ap_gen.eliminado = false
                   LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_cli.id_cuenta, ap_gen.id_cuenta)
                   WHERE i.id_nota_debito = :id AND i.codigo_impuesto = '2'
                   GROUP BY i.codigo_porcentaje, COALESCE(ap_cli.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idNotaDebito, ':id_cliente' => $idCliente]);
        while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
            $valorIva = round((float)$row['total_valor'], 2);
            if ($valorIva <= 0 || empty($row['id_cuenta'])) continue;
            $comercial[] = [
                'id_cuenta_contable' => (int)$row['id_cuenta'],
                'cuenta_codigo'      => $row['cuenta_codigo'],
                'cuenta_nombre'      => $row['cuenta_nombre'],
                'debe'               => 0.0,
                'haber'              => $valorIva,
                'referencia_detalle' => 'IVA Ventas (ND)',
            ];
        }

        // Reglas base de ventas_factura → por cobrar (debe) y subtotal/ventas (haber).
        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura');
        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo   = strtoupper($r['codigo']   ?? '');
            $concepto = strtolower($r['concepto'] ?? $r['referencia'] ?? '');
            $ladoNatural = ($r['debe_haber'] ?? 'debe') === 'haber' ? 'haber' : 'debe';

            $valor = 0.0;
            if (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valor = $importeTotal;
            } elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valor = $subtotal;
            } else {
                continue; // costo/inventario/ICE/propina/descuento no aplican a la ND
            }
            if ($valor <= 0) continue;

            $comercial[] = [
                'id_cuenta_contable' => (int)$r['id_cuenta'],
                'cuenta_codigo'      => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $r['cuenta_nombre'] ?? '',
                'debe'               => $ladoNatural === 'debe' ? round($valor, 2) : 0.0,
                'haber'              => $ladoNatural === 'debe' ? 0.0 : round($valor, 2),
                'referencia_detalle' => ($r['concepto'] ?? $r['referencia'] ?? '') . ' (ND)',
            ];
        }

        if (empty($comercial)) {
            return [];
        }

        $totalDebe  = round(array_sum(array_column($comercial, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($comercial, 'haber')), 2);
        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No hay cuentas configuradas para la nota de débito de venta o los montos son cero.");
        }

        return $this->aplicarAjusteRedondeo($comercial, $reglas, 'ventas (nota de débito)');
    }

    /**
     * Arma el asiento contable "cuenta puente" de una Factura de Reembolso
     * (ATS 41 — mismo patrón que generarAsientoConsignacion): el reembolso NO
     * es ingreso propio de la empresa.
     *
     *   DEBE : Cuentas por Cobrar Cliente        = importe_total (todo lo que paga el cliente)
     *   HABER: Reembolso a Terceros (puente)     = base + IVA reembolsado (terceros)
     *   HABER: Ingresos por Honorarios            = solo líneas es_reembolso=false
     *   HABER: IVA Ventas (honorarios)            = solo si esas líneas llevan IVA
     *
     * CXC/Ingresos/IVA honorarios reutilizan, si la empresa no configuró las
     * cuentas propias del concepto 'factura_reembolso', las mismas cuentas ya
     * configuradas para 'ventas_factura' (es el mismo tipo de cuenta que
     * cualquier venta normal). La cuenta puente NO tiene fallback: es el único
     * concepto genuinamente nuevo y debe configurarse explícitamente.
     */
    public function generarAsientoFacturaReembolso(int $idEmpresa, int $idFacturaReembolso): array
    {
        $db = \App\core\Database::getConnection();

        $stCab = $db->prepare(
            "SELECT importe_total, total_base_imponible_reembolso, total_impuesto_reembolso, id_cliente
             FROM factura_reembolso_cabecera WHERE id = ?"
        );
        $stCab->execute([$idFacturaReembolso]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
        $importeTotal  = round((float) ($cab['importe_total'] ?? 0), 2);
        $baseReembolso = round((float) ($cab['total_base_imponible_reembolso'] ?? 0), 2);
        $ivaReembolso  = round((float) ($cab['total_impuesto_reembolso'] ?? 0), 2);
        $idCliente     = (int) ($cab['id_cliente'] ?? 0);
        if ($importeTotal <= 0.0) {
            return [];
        }

        $comercial = [];

        // 1. Reglas propias del concepto 'factura_reembolso' (las 4 cuentas del seed).
        $reglasReembolso = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'factura_reembolso');
        $cuentaCxc = null;
        $cuentaPuente = null;
        $cuentaIngresoHonorarios = null;
        $cuentaIvaHonorarios = null;
        foreach ($reglasReembolso as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo = strtoupper($r['asiento_tipo_codigo'] ?? $r['codigo'] ?? '');
            $cuenta = [
                'id_cuenta'     => (int) $r['id_cuenta'],
                'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
            ];
            if (str_contains($codigo, 'CXC_CLIENTE')) {
                $cuentaCxc = $cuenta;
            } elseif (str_contains($codigo, 'PUENTE_TERCEROS')) {
                $cuentaPuente = $cuenta;
            } elseif (str_contains($codigo, 'INGRESO_HONORARIOS')) {
                $cuentaIngresoHonorarios = $cuenta;
            } elseif (str_contains($codigo, 'IVA_VENTAS_HONORARIOS')) {
                $cuentaIvaHonorarios = $cuenta;
            }
        }

        // 2. Fallback de CXC/Ingresos a 'ventas_factura' (mismas cuentas de cualquier venta).
        $reglasVentas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura');
        if ($cuentaCxc === null || $cuentaIngresoHonorarios === null) {
            foreach ($reglasVentas as $r) {
                if (empty($r['id_cuenta'])) continue;
                $codigo   = strtoupper($r['codigo']   ?? '');
                $concepto = strtolower($r['concepto'] ?? $r['referencia'] ?? '');
                $cuenta = [
                    'id_cuenta'     => (int) $r['id_cuenta'],
                    'cuenta_codigo' => $r['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
                ];
                if ($cuentaCxc === null && (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar'))) {
                    $cuentaCxc = $cuenta;
                } elseif ($cuentaIngresoHonorarios === null && (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal'))) {
                    $cuentaIngresoHonorarios = $cuenta;
                }
            }
        }

        // 3. DEBE: Cuentas por Cobrar Cliente, por el total de la factura.
        $comercial[] = [
            'id_cuenta_contable' => $cuentaCxc['id_cuenta']     ?? 0,
            'cuenta_codigo'      => $cuentaCxc['cuenta_codigo'] ?? '',
            'cuenta_nombre'      => $cuentaCxc['cuenta_nombre'] ?? '',
            'debe'               => $importeTotal,
            'haber'              => 0.0,
            'referencia_detalle' => 'Cuentas por cobrar (factura de reembolso)',
        ];

        // 4. HABER: cuenta puente, por el total reembolsado a terceros (sin fallback).
        if ($baseReembolso + $ivaReembolso > 0) {
            $comercial[] = [
                'id_cuenta_contable' => $cuentaPuente['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaPuente['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaPuente['cuenta_nombre'] ?? '',
                'debe'               => 0.0,
                'haber'              => round($baseReembolso + $ivaReembolso, 2),
                'referencia_detalle' => 'Reembolso a terceros (pendiente de reconciliar con la compra origen)',
            ];
        }

        // 5. HABER: Ingresos por honorarios (solo líneas es_reembolso = false).
        $stHon = $db->prepare(
            "SELECT COALESCE(SUM(precio_total_sin_impuesto), 0)
             FROM factura_reembolso_detalle
             WHERE id_factura_reembolso = ? AND es_reembolso = false"
        );
        $stHon->execute([$idFacturaReembolso]);
        $baseHonorarios = round((float) $stHon->fetchColumn(), 2);
        if ($baseHonorarios > 0) {
            $comercial[] = [
                'id_cuenta_contable' => $cuentaIngresoHonorarios['id_cuenta']     ?? 0,
                'cuenta_codigo'      => $cuentaIngresoHonorarios['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $cuentaIngresoHonorarios['cuenta_nombre'] ?? '',
                'debe'               => 0.0,
                'haber'              => $baseHonorarios,
                'referencia_detalle' => 'Ingresos por honorarios',
            ];
        }

        // 6. HABER: IVA de las líneas de honorarios, por tarifa — cascada cliente > general
        //    (mismo mecanismo que 'ventas_factura'), con fallback a la cuenta plana del
        //    concepto 'factura_reembolso' si esa tarifa específica no está configurada.
        $sqlIva = "SELECT di.codigo_porcentaje, SUM(di.valor) AS total_valor,
                          COALESCE(ap_cli.id_cuenta, ap_gen.id_cuenta) AS id_cuenta,
                          pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                   FROM factura_reembolso_detalle_impuestos di
                   INNER JOIN factura_reembolso_detalle d ON d.id = di.id_factura_reembolso_detalle
                   LEFT JOIN asientos_programados ap_cli
                          ON ap_cli.id_referencia = :id_cliente AND ap_cli.tipo_referencia = 'cliente'
                         AND ap_cli.id_asiento_tipo = 0 AND ap_cli.codigo_tarifa_iva = di.codigo_porcentaje::text
                         AND ap_cli.direccion_iva = 'venta' AND ap_cli.id_empresa = :emp AND ap_cli.eliminado = false
                   LEFT JOIN asientos_programados ap_gen
                          ON ap_gen.id_referencia   = CAST(di.codigo_porcentaje AS INTEGER)
                         AND ap_gen.tipo_referencia = 'iva_ventas_factura'
                         AND ap_gen.id_empresa      = :emp AND ap_gen.eliminado = false
                   LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_cli.id_cuenta, ap_gen.id_cuenta)
                   WHERE d.id_factura_reembolso = :id AND d.es_reembolso = false AND di.codigo_impuesto = '2'
                   GROUP BY di.codigo_porcentaje, COALESCE(ap_cli.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idFacturaReembolso, ':id_cliente' => $idCliente]);
        while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
            $valorIva = round((float) $row['total_valor'], 2);
            if ($valorIva <= 0) continue;
            $idCuentaIva = !empty($row['id_cuenta']) ? (int) $row['id_cuenta'] : ($cuentaIvaHonorarios['id_cuenta'] ?? 0);
            $comercial[] = [
                'id_cuenta_contable' => $idCuentaIva,
                'cuenta_codigo'      => $row['cuenta_codigo'] ?? ($cuentaIvaHonorarios['cuenta_codigo'] ?? ''),
                'cuenta_nombre'      => $row['cuenta_nombre'] ?? ($cuentaIvaHonorarios['cuenta_nombre'] ?? ''),
                'debe'               => 0.0,
                'haber'              => $valorIva,
                'referencia_detalle' => 'IVA Ventas (honorarios)',
            ];
        }

        $totalDebe  = round(array_sum(array_column($comercial, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($comercial, 'haber')), 2);
        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No hay cuentas configuradas para la factura de reembolso o los montos son cero.");
        }

        return $this->aplicarAjusteRedondeo($comercial, $reglasReembolso, 'factura de reembolso');
    }

    /**
     * Arma el asiento contable de una retención recibida en ventas.
     *   DEBE : cuenta configurada por cada código de retención (retenciones_venta_debe),
     *          por el valor retenido de ese código.
     *   HABER: cuenta por cobrar (asientos_tipo.codigo = 'PORCOBRARFACTURAVENTA'),
     *          por el total retenido.
     *
     * Devuelve [] si no hay valores. Las líneas sin cuenta configurada se omiten
     * (el asiento quedará descuadrado y se avisará al usuario, igual que en facturas).
     */
    public function generarAsientoRetencionVenta(int $idEmpresa, int $idRetencion): array
    {
        $db = \App\core\Database::getConnection();

        // 1. DEBE: por código de retención → cuenta configurada + valor retenido
        $sqlDebe = "SELECT d.codigo_retencion,
                           SUM(d.valor_retenido) AS total,
                           ap.id_cuenta,
                           pc.codigo AS cuenta_codigo,
                           pc.nombre AS cuenta_nombre
                    FROM retencion_venta_detalle d
                    LEFT JOIN LATERAL (
                        SELECT rs.id FROM retenciones_sri rs
                        WHERE rs.codigo_ret = d.codigo_retencion
                        ORDER BY rs.id DESC LIMIT 1
                    ) rsx ON true
                    LEFT JOIN asientos_programados ap
                           ON ap.id_referencia = rsx.id
                          AND (ap.tipo_referencia = 'retenciones_venta_debe' OR ap.tipo_referencia = 'retenciones_venta')
                          AND ap.id_empresa = :emp
                          AND ap.eliminado = false
                    LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    WHERE d.id_retencion = :id
                    GROUP BY d.codigo_retencion, ap.id_cuenta, pc.codigo, pc.nombre";
        $st = $db->prepare($sqlDebe);
        $st->execute([':emp' => $idEmpresa, ':id' => $idRetencion]);

        $detalles      = [];
        $totalRetenido = 0.0;
        while ($l = $st->fetch(\PDO::FETCH_ASSOC)) {
            $valor = round((float) $l['total'], 2);
            if ($valor <= 0) continue;
            $totalRetenido += $valor;
            if (empty($l['id_cuenta'])) continue; // sin cuenta configurada para ese código
            $detalles[] = [
                'id_cuenta_contable' => (int) $l['id_cuenta'],
                'cuenta_codigo'      => $l['cuenta_codigo'],
                'cuenta_nombre'      => $l['cuenta_nombre'],
                'debe'               => $valor,
                'haber'              => 0.0,
                'referencia_detalle' => 'Retención ' . $l['codigo_retencion'],
            ];
        }
        $totalRetenido = round($totalRetenido, 2);

        if ($totalRetenido <= 0) {
            return [];
        }

        // 2. HABER: contrapartida en cuentas por cobrar
        $sqlHaber = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                     FROM asientos_programados ap
                     INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                     INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                     WHERE ap.id_empresa = :emp
                       AND at.codigo = 'PORCOBRARFACTURAVENTA'
                       AND ap.eliminado = false
                     LIMIT 1";
        $stH = $db->prepare($sqlHaber);
        $stH->execute([':emp' => $idEmpresa]);
        $haber = $stH->fetch(\PDO::FETCH_ASSOC);

        if ($haber && !empty($haber['id_cuenta'])) {
            $detalles[] = [
                'id_cuenta_contable' => (int) $haber['id_cuenta'],
                'cuenta_codigo'      => $haber['cuenta_codigo'],
                'cuenta_nombre'      => $haber['cuenta_nombre'],
                'debe'               => 0.0,
                'haber'              => $totalRetenido,
                'referencia_detalle' => 'Cuentas por cobrar (retención)',
            ];
        }

        return $detalles;
    }

    /**
     * Arma el asiento contable de una retención emitida en compras.
     *   HABER: cuenta configurada por cada código de retención (retenciones_compra_haber),
     *          por el valor retenido de ese código (retención por pagar al SRI).
     *   DEBE : cuenta por pagar (asientos_tipo.codigo = 'PORPAGARFACTURACOMPRA'),
     *          por el total retenido (reduce el pasivo con el proveedor).
     *
     * Devuelve [] si no hay valores. Las líneas sin cuenta configurada se omiten
     * (el asiento quedará descuadrado y se avisará al usuario, igual que en ventas).
     */
    public function generarAsientoRetencionCompra(int $idEmpresa, int $idRetencion): array
    {
        $db = \App\core\Database::getConnection();

        // 1. HABER: por código de retención → cuenta configurada + valor retenido
        $sqlHaber = "SELECT d.codigo_retencion,
                            SUM(d.valor_retenido) AS total,
                            ap.id_cuenta,
                            pc.codigo AS cuenta_codigo,
                            pc.nombre AS cuenta_nombre
                     FROM retencion_compra_detalle d
                     LEFT JOIN LATERAL (
                         SELECT rs.id FROM retenciones_sri rs
                         WHERE rs.codigo_ret = d.codigo_retencion
                         ORDER BY rs.id DESC LIMIT 1
                     ) rsx ON true
                     LEFT JOIN asientos_programados ap
                            ON ap.id_referencia = rsx.id
                           AND ap.tipo_referencia = 'retenciones_compra_haber'
                           AND ap.id_empresa = :emp
                           AND ap.eliminado = false
                     LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                     WHERE d.id_retencion = :id
                     GROUP BY d.codigo_retencion, ap.id_cuenta, pc.codigo, pc.nombre";
        $st = $db->prepare($sqlHaber);
        $st->execute([':emp' => $idEmpresa, ':id' => $idRetencion]);

        $detalles      = [];
        $totalRetenido = 0.0;
        while ($l = $st->fetch(\PDO::FETCH_ASSOC)) {
            $valor = round((float) $l['total'], 2);
            if ($valor <= 0) continue;
            $totalRetenido += $valor;
            if (empty($l['id_cuenta'])) continue; // sin cuenta configurada para ese código
            $detalles[] = [
                'id_cuenta_contable' => (int) $l['id_cuenta'],
                'cuenta_codigo'      => $l['cuenta_codigo'],
                'cuenta_nombre'      => $l['cuenta_nombre'],
                'debe'               => 0.0,
                'haber'              => $valor,
                'referencia_detalle' => 'Retención ' . $l['codigo_retencion'],
            ];
        }
        $totalRetenido = round($totalRetenido, 2);

        if ($totalRetenido <= 0) {
            return [];
        }

        // 2. DEBE: contrapartida en cuentas por pagar
        $sqlDebe = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                    FROM asientos_programados ap
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    WHERE ap.id_empresa = :emp
                      AND at.codigo = 'PORPAGARFACTURACOMPRA'
                      AND ap.eliminado = false
                    LIMIT 1";
        $stD = $db->prepare($sqlDebe);
        $stD->execute([':emp' => $idEmpresa]);
        $debe = $stD->fetch(\PDO::FETCH_ASSOC);

        if ($debe && !empty($debe['id_cuenta'])) {
            $detalles[] = [
                'id_cuenta_contable' => (int) $debe['id_cuenta'],
                'cuenta_codigo'      => $debe['cuenta_codigo'],
                'cuenta_nombre'      => $debe['cuenta_nombre'],
                'debe'               => $totalRetenido,
                'haber'              => 0.0,
                'referencia_detalle' => 'Cuentas por pagar (retención)',
            ];
        }

        return $detalles;
    }

    /**
     * Arma el asiento contable de un INGRESO usando solo la configuración contable propia
     * (Tipo de asiento → Ingresos/Egresos y Cobros/Pagos), sin depender de asientos tipo:
     *   DEBE : cuenta de cada forma de cobro (config Cobros/Pagos), por su monto.
     *   HABER: cuenta del concepto del ingreso (config Ingresos/Egresos), por el total cobrado.
     *
     * Devuelve [] si no hay valores. Las líneas sin cuenta configurada se omiten;
     * el asiento quedará descuadrado y no se generará (se avisa).
     */
    public function generarAsientoIngreso(int $idEmpresa, int $idIngreso, array $detallesConCuenta = []): array
    {
        $db = \App\core\Database::getConnection();

        $sqlCab = "SELECT i.id,
                          o.id_cuenta_contable AS concepto_id_cuenta,
                          o.nombre             AS concepto_nombre,
                          o.comportamiento     AS concepto_comportamiento
                   FROM ingresos_cabecera i
                   LEFT JOIN empresa_opciones_ingreso_egreso o ON o.id = i.id_ingreso_concepto
                   WHERE i.id = :id AND i.id_empresa = :emp AND i.eliminado = false";
        $stCab = $db->prepare($sqlCab);
        $stCab->execute([':id' => $idIngreso, ':emp' => $idEmpresa]);
        $ingreso = $stCab->fetch(\PDO::FETCH_ASSOC);
        if (!$ingreso) {
            return [];
        }

        // Si el concepto está atado a un módulo con contabilización propia (FACTURA_VENTA,
        // RECIBO_VENTA), la cuenta "oficial" de Configuración Contable manda sobre la que tenga
        // guardada aparte el concepto
        // (ese campo quedó de respaldo/legado — ver egresos-compras-cxp-contrapartida-faltante).
        // Anticipos (ANTICIPO_CLIENTE) no tienen equivalente y siguen usando su propia cuenta.
        //
        // Manda SIEMPRE que el comportamiento tenga cuenta oficial, incluso si esa cuenta aún no
        // está configurada (id_cuenta = 0): en ese caso la contrapartida se queda sin cuenta, el
        // asiento no se genera y el documento se reporta como pendiente. Antes se caía a la cuenta
        // legada del concepto, y bastaba con que esa columna tuviera un valor viejo —normalmente la
        // cuenta del banco, que la pantalla ya no deja editar— para producir un asiento espejado
        // (mismo banco en el Debe y en el Haber) que cuadraba y por tanto se guardaba sin avisar.
        // Caso real: GOLIFE (empresa 24), 14 asientos de ingreso contabilizados ANTES que las
        // facturas que cobraban, así que la cartera no resolvió y el fallback legado se activó.
        $conceptoIdCuenta = (int) ($ingreso['concepto_id_cuenta'] ?? 0);
        $oficialIngreso = $this->programadoRepo->getCuentaOficialPorComportamiento(
            $idEmpresa, (string) ($ingreso['concepto_comportamiento'] ?? '')
        );
        if ($oficialIngreso !== null) {
            $conceptoIdCuenta = $oficialIngreso['id_cuenta'];
        }

        // ── DEBE: formas de cobro (banco/caja) → config Cobros/Pagos ──
        [$detalles, $totalMovido] = $this->lineasFormas($idEmpresa, $idIngreso, 'ingreso');
        if ($totalMovido <= 0) {
            return [];
        }

        // ── HABER (cartera de ventas: FACTURA/RECIBO): cancelan la MISMA distribución de
        //    Cuenta por Cobrar que el documento acreditó (en su Debe) en su propio asiento al
        //    registrarse (puede estar repartida en varias cuentas por línea/Cliente/Producto —
        //    misma cascada que Compras), no la cuenta del concepto elegido en el ingreso.
        $restante = $totalMovido;
        [$lineasCartera, $totalCartera] = $this->contrapartidaCarteraVentas($db, $idEmpresa, $idIngreso);
        if ($totalCartera > 0) {
            foreach ($lineasCartera as $l) {
                $detalles[] = $l;
            }
            $restante = round($restante - $totalCartera, 2);
        }
        // Documentos sin asiento propio resoluble (o sin línea de Debe): su monto se queda en
        // $restante y cae al camino normal (cuenta del concepto).

        // ── HABER (resto): contrapartida repartida por la cuenta de cada línea de descripción.
        //    Por defecto la cuenta del concepto; si la línea trae otra, manda la de la línea.
        if ($restante > 0.0) {
            $contrapartida = $this->contrapartidaPorCuenta(
                $db, $idEmpresa, $idIngreso, 'ingreso',
                $conceptoIdCuenta,
                (string) ($ingreso['concepto_nombre'] ?? 'Ingreso'),
                $restante, $detallesConCuenta
            );
            foreach ($contrapartida as $linea) {
                $detalles[] = [
                    'id_cuenta_contable' => $linea['id_cuenta'],
                    'debe'               => 0.0,
                    'haber'              => round($linea['monto'], 2),
                    'referencia_detalle' => $linea['referencia'],
                ];
            }
        }

        return $detalles;
    }

    /**
     * Arma el asiento contable de un EGRESO usando solo la configuración contable propia
     * (Tipo de asiento → Ingresos/Egresos y Cobros/Pagos), sin depender de asientos tipo:
     *   HABER: cuenta de cada forma de pago (config Cobros/Pagos), por su monto.
     *   DEBE : cuenta del concepto del egreso (config Ingresos/Egresos), por el total pagado.
     */
    public function generarAsientoEgreso(int $idEmpresa, int $idEgreso, array $detallesConCuenta = []): array
    {
        $db = \App\core\Database::getConnection();

        $sqlCab = "SELECT e.id,
                          o.id_cuenta_contable AS concepto_id_cuenta,
                          o.nombre             AS concepto_nombre,
                          o.comportamiento     AS concepto_comportamiento
                   FROM egresos_cabecera e
                   LEFT JOIN empresa_opciones_ingreso_egreso o ON o.id = e.id_egreso_concepto
                   WHERE e.id = :id AND e.id_empresa = :emp AND e.eliminado = false";
        $stCab = $db->prepare($sqlCab);
        $stCab->execute([':id' => $idEgreso, ':emp' => $idEmpresa]);
        $egreso = $stCab->fetch(\PDO::FETCH_ASSOC);
        if (!$egreso) {
            return [];
        }

        // Si el concepto está atado a un módulo con contabilización propia (COMPRA,
        // LIQUIDACION), la cuenta "oficial" de Configuración Contable manda sobre la que tenga
        // guardada aparte el concepto
        // (ese campo quedó de respaldo/legado — ver egresos-compras-cxp-contrapartida-faltante).
        // Anticipos/préstamos no tienen equivalente y siguen usando su propia cuenta.
        //
        // Manda SIEMPRE que el comportamiento tenga cuenta oficial, incluso si esa cuenta aún no
        // está configurada (id_cuenta = 0): en ese caso la contrapartida se queda sin cuenta, el
        // asiento no se genera y el documento se reporta como pendiente. Antes se caía a la cuenta
        // legada del concepto, y bastaba con que esa columna tuviera un valor viejo —normalmente la
        // cuenta del banco, que la pantalla ya no deja editar— para producir un asiento espejado
        // (mismo banco en el Debe y en el Haber) que cuadraba y por tanto se guardaba sin avisar.
        // Caso real: GOLIFE (empresa 24), 14 asientos de ingreso contabilizados ANTES que las
        // facturas que cobraban, así que la cartera no resolvió y el fallback legado se activó.
        $conceptoIdCuenta = (int) ($egreso['concepto_id_cuenta'] ?? 0);
        $oficialEgreso = $this->programadoRepo->getCuentaOficialPorComportamiento(
            $idEmpresa, (string) ($egreso['concepto_comportamiento'] ?? '')
        );
        if ($oficialEgreso !== null) {
            $conceptoIdCuenta = $oficialEgreso['id_cuenta'];
        }

        // ── HABER: formas de pago (banco/caja) → config Cobros/Pagos ──
        [$detalles, $totalMovido] = $this->lineasFormas($idEmpresa, $idEgreso, 'egreso');
        if ($totalMovido <= 0) {
            return [];
        }

        // ── DEBE (pasivos de nómina ya provisionados): para estos tipo_documento
        //    puntuales NO se usa la cuenta genérica del concepto — cada uno cancela
        //    directamente su propio pasivo (ya provisionado mes a mes en el rol vía
        //    RolProvisionService). El resto del egreso (ROL/ANTICIPO/PRÉSTAMO/MANUAL)
        //    sigue su camino normal por el remanente ($restante).
        $restante = $totalMovido;
        foreach (self::CONTRAPARTIDA_ESPECIFICA_NOMINA as $tipoDocumento => $codigo) {
            $totalTipo = $this->sumaPorTipoDocumento($db, $idEgreso, $tipoDocumento);
            if ($totalTipo <= 0) continue;

            $idCtaTipo = $this->cuentaProgramadaPorCodigo($idEmpresa, 'nomina', $codigo);
            if ($idCtaTipo > 0) {
                $detalles[] = [
                    'id_cuenta_contable' => $idCtaTipo,
                    'debe'               => round($totalTipo, 2),
                    'haber'              => 0.0,
                    'referencia_detalle' => self::NOMBRE_CONTRAPARTIDA_NOMINA[$tipoDocumento] ?? $codigo,
                ];
                $restante = round($restante - $totalTipo, 2);
            }
            // Si la cuenta no está configurada, no se separa: ese monto se queda en
            // $restante y cae al camino normal (cuenta del concepto), igual que se
            // comportaba antes de esta integración.
        }

        // ── DEBE (rol MENSUAL, base devengado): la porción del egreso que paga un rol
        //    MENSUAL cancela "Sueldos por Pagar" (la misma cuenta que RolAsientoService
        //    acreditó al contabilizar el rol), no la cuenta genérica del concepto.
        $totalRolMensual = $this->sumaRolMensualPorEgreso($db, $idEgreso);
        if ($totalRolMensual > 0) {
            $idCtaSueldosPorPagar = $this->cuentaProgramadaPorCodigo($idEmpresa, 'nomina', 'SUELDOSPORPAGARNOMINA');
            if ($idCtaSueldosPorPagar > 0) {
                $detalles[] = [
                    'id_cuenta_contable' => $idCtaSueldosPorPagar,
                    'debe'               => $totalRolMensual,
                    'haber'              => 0.0,
                    'referencia_detalle' => 'Sueldos por Pagar (rol mensual)',
                ];
                $restante = round($restante - $totalRolMensual, 2);
            }
            // Si la cuenta no está configurada, se queda en $restante y cae a la cuenta
            // del concepto (igual que si la separación no aplicara).
        }

        // ── DEBE (rol QUINCENA/SEMANAL, anticipo puro): esa porción cancela
        //    "Anticipos y Descuentos" (la misma cuenta que el rol mensual acredita
        //    después vía el neteo), no la cuenta genérica del concepto — ver
        //    sumaRolNoMensualPorEgreso().
        $totalRolNoMensual = $this->sumaRolNoMensualPorEgreso($db, $idEgreso);
        if ($totalRolNoMensual > 0) {
            $idCtaAnticiposDescuentos = $this->cuentaProgramadaPorCodigo($idEmpresa, 'nomina', 'ANTICIPOSDESCUENTOSNOMINA');
            if ($idCtaAnticiposDescuentos > 0) {
                $detalles[] = [
                    'id_cuenta_contable' => $idCtaAnticiposDescuentos,
                    'debe'               => $totalRolNoMensual,
                    'haber'              => 0.0,
                    'referencia_detalle' => 'Anticipos y Descuentos (rol quincena/semana)',
                ];
                $restante = round($restante - $totalRolNoMensual, 2);
            }
            // Si la cuenta no está configurada, se queda en $restante y cae a la cuenta
            // del concepto (igual que si la separación no aplicara).
        }

        // ── DEBE (cartera de compras: COMPRA/LIQUIDACION): cancelan la MISMA distribución de
        //    Cuenta por Pagar que el documento acreditó en su propio asiento al registrarse
        //    (puede estar repartida en varias cuentas por línea/Producto/Categoría/Marca — ver
        //    contrapartidaCarteraCompras), no la cuenta del concepto elegido en el egreso.
        [$lineasCartera, $totalCartera] = $this->contrapartidaCarteraCompras($db, $idEmpresa, $idEgreso);
        if ($totalCartera > 0) {
            foreach ($lineasCartera as $l) {
                $detalles[] = $l;
            }
            $restante = round($restante - $totalCartera, 2);
        }
        // Documentos sin asiento propio resoluble (o sin línea de Haber): su monto se queda en
        // $restante y cae al camino normal (cuenta del concepto).

        // ── DEBE (resto): contrapartida repartida por la cuenta de cada línea de descripción.
        //    Por defecto la cuenta del concepto; si la línea trae otra, manda la de la línea.
        if ($restante > 0.0) {
            $contrapartida = $this->contrapartidaPorCuenta(
                $db, $idEmpresa, $idEgreso, 'egreso',
                $conceptoIdCuenta,
                (string) ($egreso['concepto_nombre'] ?? 'Egreso'),
                $restante, $detallesConCuenta
            );
            foreach ($contrapartida as $linea) {
                $detalles[] = [
                    'id_cuenta_contable' => $linea['id_cuenta'],
                    'debe'               => round($linea['monto'], 2),
                    'haber'              => 0.0,
                    'referencia_detalle' => $linea['referencia'],
                ];
            }
        }

        return $detalles;
    }

    /**
     * Arma el asiento de la LIQUIDACIÓN de la Declaración de IVA de un período (concepto
     * 'declaracion_iva'). Es un asiento de reclasificación/cierre: cancela lo acumulado en
     * IVA en ventas, crédito tributario de compras y retenciones de IVA, y deja el neto en
     * IVA por Pagar (si hay valor a pagar) y/o Crédito Tributario a Favor (si hay saldo a favor
     * del período, o si se está consumiendo el saldo a favor del período anterior).
     *
     * DEBE:  IVA en Ventas (neto) + Crédito Tributario a Favor (saldo a favor de ESTE período)
     * HABER: Crédito Tributario Compras (neto) + Retenciones de IVA + IVA por Pagar
     *        + Crédito Tributario a Favor (arrastre del período anterior que se consume)
     *
     * @param array $datos ['iva_ventas_neto','credito_compras_neto','retenciones',
     *                       'iva_a_pagar','saldo_favor','credito_anterior_aplicado']
     */
    public function generarAsientoDeclaracionIva(int $idEmpresa, int $idDeclaracion, array $datos): array
    {
        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'declaracion_iva');

        $ivaVentas   = round((float) ($datos['iva_ventas_neto'] ?? 0), 2);
        $creditoComp = round((float) ($datos['credito_compras_neto'] ?? 0), 2);
        $retenciones = round((float) ($datos['retenciones'] ?? 0), 2);
        $aPagar      = round((float) ($datos['iva_a_pagar'] ?? 0), 2);
        $saldoFavor  = round((float) ($datos['saldo_favor'] ?? 0), 2);
        $creditoAnt  = round((float) ($datos['credito_anterior_aplicado'] ?? 0), 2);

        $lineas = [
            ['codigo' => 'IVAVENTASDECLARACION',              'lado' => 'debe',  'monto' => $ivaVentas,   'ref' => 'IVA en Ventas'],
            ['codigo' => 'CREDITOTRIBUTARIOFAVORDECLARACION', 'lado' => 'debe',  'monto' => $saldoFavor,  'ref' => 'Crédito Tributario a Favor'],
            ['codigo' => 'IVACOMPRASDECLARACION',             'lado' => 'haber', 'monto' => $creditoComp, 'ref' => 'Crédito Tributario Compras'],
            ['codigo' => 'RETENCIONIVADECLARACION',           'lado' => 'haber', 'monto' => $retenciones, 'ref' => 'Retenciones de IVA Recibidas'],
            ['codigo' => 'IVAPORPAGARDECLARACION',            'lado' => 'haber', 'monto' => $aPagar,      'ref' => 'IVA por Pagar'],
            ['codigo' => 'CREDITOTRIBUTARIOFAVORDECLARACION', 'lado' => 'haber', 'monto' => $creditoAnt,  'ref' => 'Crédito Tributario a Favor Aplicado'],
        ];

        $detalles = [];
        $reglasSinCuenta = [];
        $huboMontos = false;
        foreach ($lineas as $l) {
            if ($l['monto'] <= 0.0) {
                continue;
            }
            $huboMontos = true;
            $idCuenta = $this->cuentaProgramadaPorCodigo($idEmpresa, 'declaracion_iva', $l['codigo']);
            if ($idCuenta <= 0) {
                $reglasSinCuenta[] = $l['ref'];
                continue;
            }
            $detalles[] = [
                'id_cuenta_contable' => $idCuenta,
                'debe'               => $l['lado'] === 'debe'  ? $l['monto'] : 0.0,
                'haber'              => $l['lado'] === 'haber' ? $l['monto'] : 0.0,
                'referencia_detalle' => $l['ref'],
            ];
        }

        // No había ningún monto en la declaración (caso legítimo de "nada que contabilizar").
        if (!$huboMontos) {
            return [];
        }

        // Había montos, pero NINGUNA de las cuentas requeridas está configurada: el ajuste por
        // redondeo no detectaría esto (Debe=Haber=0, sin descuadre), así que se avisa explícito.
        if (empty($detalles)) {
            throw new \Exception(
                'Falta configurar la(s) cuenta(s) contable(s) de: ' . implode(', ', array_unique($reglasSinCuenta)) .
                '. Configúrelas en Contabilidad → Configuración contable, concepto «Declaración de IVA».'
            );
        }

        return $this->aplicarAjusteRedondeo($detalles, $reglas, 'Declaración de IVA', $reglasSinCuenta);
    }

    /**
     * Asiento de la Declaración de Retenciones (Formulario 103): RECLASIFICA el pasivo por
     * retención de Impuesto a la Renta que ya se reconoció documento a documento (cada
     * retencion_compra_cabecera generó su propio asiento vía generarAsientoRetencionCompra,
     * acreditando la cuenta configurada por código SRI en 'retenciones_compra_haber') hacia
     * una única cuenta consolidada "Retenciones de Renta por Pagar (Declaración)", que es la
     * que luego se cancela con el egreso al SRI.
     *
     * A diferencia de generarAsientoDeclaracionIva, aquí ambos lados se calculan DIRECTO de
     * retencion_compra_detalle (no de valores ya agregados en la cabecera de la declaración),
     * igual patrón que generarAsientoRetencionCompra, para garantizar Debe=Haber por
     * construcción sin depender de que el snapshot de casilleros coincida centavo a centavo.
     *
     *   DEBE : cada cuenta de retención por código SRI usada en el período (la cierra).
     *   HABER: RETENCIONRENTAPORPAGARDECLARACION, por el total.
     *
     * @param int[] $idsGrupoRuc Empresas cuyas retenciones se suman al DEBE (el F103 se declara
     *              por RUC completo, ver DeclaracionRetencionesService). Vacío = solo $idEmpresa
     *              (comportamiento de siempre). El HABER y la resolución de cuentas por código
     *              SRI (asientos_programados) siguen SIEMPRE contra el plan de cuentas de
     *              $idEmpresa — es la única empresa donde el asiento puede contabilizarse.
     */
    public function generarAsientoDeclaracionRetenciones(int $idEmpresa, string $fechaDesde, string $fechaHasta, array $idsGrupoRuc = []): array
    {
        $db = \App\core\Database::getConnection();

        $idsGrupoRuc = $idsGrupoRuc ?: [$idEmpresa];
        $ph = implode(',', array_fill(0, count($idsGrupoRuc), '?'));

        $sqlDebe = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                           SUM(d.valor_retenido) AS total
                    FROM retencion_compra_detalle d
                    JOIN retencion_compra_cabecera c ON c.id = d.id_retencion
                    LEFT JOIN LATERAL (
                        SELECT rs.id FROM retenciones_sri rs
                        WHERE rs.codigo_ret = d.codigo_retencion
                        ORDER BY rs.id DESC LIMIT 1
                    ) rsx ON true
                    LEFT JOIN asientos_programados ap
                           ON ap.id_referencia = rsx.id
                          AND ap.tipo_referencia = 'retenciones_compra_haber'
                          AND ap.id_empresa = ?
                          AND ap.eliminado = false
                    LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    WHERE c.id_empresa IN ($ph) AND c.estado = 'autorizada' AND c.eliminado = false
                      AND c.fecha_emision BETWEEN ? AND ?
                      AND (d.codigo_impuesto IN ('1','RENTA'))
                    GROUP BY ap.id_cuenta, pc.codigo, pc.nombre";
        $st = $db->prepare($sqlDebe);
        $st->execute(array_merge([$idEmpresa], $idsGrupoRuc, [$fechaDesde, $fechaHasta]));

        $detalles = [];
        $reglasSinCuenta = [];
        $totalRetenido = 0.0;
        while ($l = $st->fetch(\PDO::FETCH_ASSOC)) {
            $valor = round((float) $l['total'], 2);
            if ($valor <= 0) continue;
            $totalRetenido += $valor;
            if (empty($l['id_cuenta'])) {
                $reglasSinCuenta[] = 'Retención por Pagar (código SRI sin cuenta configurada)';
                continue;
            }
            $detalles[] = [
                'id_cuenta_contable' => (int) $l['id_cuenta'],
                'debe'               => $valor,
                'haber'              => 0.0,
                'referencia_detalle' => 'Retenciones de Renta del período (' . ($l['cuenta_nombre'] ?? $l['cuenta_codigo']) . ')',
            ];
        }
        $totalRetenido = round($totalRetenido, 2);

        if ($totalRetenido <= 0.0) {
            return []; // caso legítimo: sin retenciones de renta en el período
        }
        if (empty($detalles)) {
            throw new Exception(
                'Falta configurar la(s) cuenta(s) contable(s) de retención por código SRI ' .
                '(Contabilidad → Configuración contable, concepto «Retenciones en Compra»).'
            );
        }

        $idCuentaConsolidada = $this->cuentaProgramadaPorCodigo($idEmpresa, 'declaracion_retenciones', 'RETENCIONRENTAPORPAGARDECLARACION');
        if ($idCuentaConsolidada <= 0) {
            throw new Exception(
                'Falta configurar la cuenta «Retenciones de Renta por Pagar (Declaración)» en ' .
                'Contabilidad → Configuración contable, concepto «Declaración de Retenciones».'
            );
        }
        $detalles[] = [
            'id_cuenta_contable' => $idCuentaConsolidada,
            'debe'               => 0.0,
            'haber'              => $totalRetenido,
            'referencia_detalle' => 'Retenciones de Renta por Pagar (Declaración)',
        ];

        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'declaracion_retenciones');
        return $this->aplicarAjusteRedondeo($detalles, $reglas, 'Declaración de Retenciones', $reglasSinCuenta);
    }

    /** Suma lo pagado (egresos_detalle.monto_pagado) de un egreso para un tipo_documento puntual. */
    private function sumaPorTipoDocumento(\PDO $db, int $idEgreso, string $tipoDocumento): float
    {
        $st = $db->prepare("SELECT COALESCE(SUM(monto_pagado), 0) FROM egresos_detalle
                             WHERE id_egreso = :id AND tipo_documento = :tipo AND eliminado = FALSE");
        $st->execute([':id' => $idEgreso, ':tipo' => $tipoDocumento]);
        return round((float) $st->fetchColumn(), 2);
    }

    /**
     * DEBE de la cartera de compras (COMPRA/LIQUIDACION) de un egreso: por cada documento
     * pagado, refleja la MISMA distribución de cuentas que ESE documento acreditó en su propio
     * asiento (modulo_origen 'compra'/'liquidacion_compra'). No asume una única cuenta global de
     * "Cuentas por Pagar": el Haber de una compra puede estar repartido en varias cuentas por
     * línea (cascada Producto → Categoría → Marca → General — p. ej. "Proveedores Mercadería"
     * vs "Proveedores Servicios"), así que el pago se prorratea en esa misma proporción.
     * Documentos sin asiento propio (o sin línea de Haber) se omiten: su monto queda fuera del
     * total devuelto y el llamador lo deja caer al camino normal (cuenta del concepto).
     *
     * @return array{0: array, 1: float} [líneas del Debe (fusionadas por cuenta), total resuelto]
     */
    /**
     * Filtra las líneas del asiento de un documento dejando solo las de su cuenta de cartera
     * (Cuenta por Cobrar / por Pagar), identificadas por las cuentas configuradas para ese slot
     * en CUALQUIER nivel de la cascada (General, Cliente, Producto, Categoría, Marca, Tipo de
     * producción). Las demás líneas del mismo lado —Costo de Ventas, Descuento en ventas— no son
     * cartera y no deben recibir parte del cobro/pago.
     *
     * Devuelve las líneas originales sin tocar si el slot no está configurado o si ninguna línea
     * coincide: en ese caso preferimos el comportamiento anterior (prorratear sobre todo el lado)
     * antes que dejar el documento sin contrapartida y el asiento descuadrado.
     */
    private function soloLineasDeCartera(\PDO $db, int $idEmpresa, array $lineas, string $codigoSlot): array
    {
        if ($codigoSlot === '' || empty($lineas)) {
            return $lineas;
        }
        $cuentas = $this->cuentasDelSlot($db, $idEmpresa, $codigoSlot);
        if (empty($cuentas)) {
            return $lineas;
        }
        $filtradas = array_values(array_filter(
            $lineas,
            static fn(array $l): bool => isset($cuentas[(int) $l['id_cuenta_contable']])
        ));

        return empty($filtradas) ? $lineas : $filtradas;
    }

    /**
     * Cuentas configuradas para un slot de cartera (código de asientos_tipo) en TODOS los niveles
     * de la cascada de esa empresa. No se filtra por tipo_referencia a propósito: el asiento del
     * documento pudo resolver su cartera por Cliente, Producto, Categoría, Marca o General, y aquí
     * hace falta el conjunto completo de cuentas posibles.
     *
     * @return array<int,bool> id_cuenta => true (mapa para lookup directo)
     */
    private function cuentasDelSlot(\PDO $db, int $idEmpresa, string $codigoSlot): array
    {
        $clave = $idEmpresa . ':' . $codigoSlot;
        if (isset($this->cacheCuentasSlot[$clave])) {
            return $this->cacheCuentasSlot[$clave];
        }

        try {
            $st = $db->prepare(
                "SELECT DISTINCT ap.id_cuenta
                   FROM asientos_programados ap
                   JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                  WHERE ap.id_empresa = :emp
                    AND at.codigo = :cod
                    AND ap.eliminado = false
                    AND ap.id_cuenta IS NOT NULL"
            );
            $st->execute([':emp' => $idEmpresa, ':cod' => $codigoSlot]);
            $cuentas = [];
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $idCuenta) {
                $cuentas[(int) $idCuenta] = true;
            }
        } catch (\Throwable $e) {
            $cuentas = []; // sin catálogo: el llamador conserva el comportamiento anterior
        }

        return $this->cacheCuentasSlot[$clave] = $cuentas;
    }

    private function contrapartidaCarteraCompras(\PDO $db, int $idEmpresa, int $idEgreso): array
    {
        $sql = "SELECT tipo_documento, id_referencia_documento, SUM(monto_pagado) AS total_pagado
                FROM egresos_detalle
                WHERE id_egreso = :id AND eliminado = FALSE
                  AND tipo_documento IN ('COMPRA','LIQUIDACION')
                  AND id_referencia_documento IS NOT NULL
                GROUP BY tipo_documento, id_referencia_documento";
        $st = $db->prepare($sql);
        $st->execute([':id' => $idEgreso]);
        $documentos = $st->fetchAll(\PDO::FETCH_ASSOC);

        $lineasPorCuenta = [];
        $totalResuelto = 0.0;

        foreach ($documentos as $doc) {
            $tipoDoc     = (string) $doc['tipo_documento'];
            $idDoc       = (int) $doc['id_referencia_documento'];
            $totalPagado = round((float) $doc['total_pagado'], 2);
            $moduloOrigen = self::MODULO_ORIGEN_CARTERA_COMPRAS[$tipoDoc] ?? null;
            if ($totalPagado <= 0 || $moduloOrigen === null) {
                continue;
            }

            $sqlHaber = "SELECT d.id_cuenta_contable, SUM(d.haber) AS monto
                         FROM asientos_contables_cabecera c
                         INNER JOIN asientos_contables_detalle d ON d.id_asiento = c.id
                         WHERE c.modulo_origen = :mod AND c.id_referencia_origen = :id_doc
                           AND c.id_empresa = :emp AND c.eliminado = false AND c.estado != 'anulado'
                           AND d.eliminado = false AND d.haber > 0
                         GROUP BY d.id_cuenta_contable";
            $stHaber = $db->prepare($sqlHaber);
            $stHaber->execute([':mod' => $moduloOrigen, ':id_doc' => $idDoc, ':emp' => $idEmpresa]);
            $haberLineas = $stHaber->fetchAll(\PDO::FETCH_ASSOC);

            // Espejo del filtro de ventas: solo las cuentas por pagar del documento, no todo el
            // Haber (que puede llevar descuento en compras u otras cuentas de resultado).
            $haberLineas = $this->soloLineasDeCartera(
                $db, $idEmpresa, $haberLineas, self::SLOT_CARTERA_COMPRAS[$tipoDoc] ?? ''
            );

            $totalHaberDoc = round((float) array_sum(array_column($haberLineas, 'monto')), 2);
            if (empty($haberLineas) || $totalHaberDoc <= 0) {
                continue; // documento sin asiento propio (o sin Haber): cae al camino normal
            }

            $referencia = self::NOMBRE_CONTRAPARTIDA_CARTERA_COMPRAS[$tipoDoc] ?? $tipoDoc;
            $acumuladoDoc = 0.0;
            $ultimaCuentaDoc = null;
            foreach ($haberLineas as $hl) {
                $idCuenta   = (int) $hl['id_cuenta_contable'];
                $proporcion = round((float) $hl['monto'], 2) / $totalHaberDoc;
                $monto      = round($totalPagado * $proporcion, 2);
                if (!isset($lineasPorCuenta[$idCuenta])) {
                    $lineasPorCuenta[$idCuenta] = ['id_cuenta_contable' => $idCuenta, 'debe' => 0.0, 'haber' => 0.0, 'referencia_detalle' => $referencia];
                }
                $lineasPorCuenta[$idCuenta]['debe'] = round($lineasPorCuenta[$idCuenta]['debe'] + $monto, 2);
                $acumuladoDoc = round($acumuladoDoc + $monto, 2);
                $ultimaCuentaDoc = $idCuenta;
            }
            // Conciliar el redondeo de ESTE documento contra su propio monto pagado (no el total
            // del egreso completo, para no mezclar el ajuste entre documentos distintos).
            $difDoc = round($totalPagado - $acumuladoDoc, 2);
            if (abs($difDoc) >= 0.01 && $ultimaCuentaDoc !== null) {
                $lineasPorCuenta[$ultimaCuentaDoc]['debe'] = round($lineasPorCuenta[$ultimaCuentaDoc]['debe'] + $difDoc, 2);
            }

            $totalResuelto = round($totalResuelto + $totalPagado, 2);
        }

        return [array_values($lineasPorCuenta), $totalResuelto];
    }

    /**
     * Espejo de contrapartidaCarteraCompras para la CARTERA de ventas (ingresos_detalle.
     * tipo_documento IN ('FACTURA','RECIBO')): por cada documento cobrado, refleja la MISMA
     * distribución de cuentas que ESE documento acreditó en su propio asiento (modulo_origen
     * 'factura_venta'/'recibo_venta'), esta vez leyendo el lado DEBE (donde vive la Cuenta por
     * Cobrar en una venta, en vez del Haber como en compras). ingresos_detalle no tiene columna
     * 'eliminado' (a diferencia de egresos_detalle), así que no se filtra por ella.
     *
     * @return array{0: array, 1: float} [líneas del Haber (fusionadas por cuenta), total resuelto]
     */
    private function contrapartidaCarteraVentas(\PDO $db, int $idEmpresa, int $idIngreso): array
    {
        $sql = "SELECT tipo_documento, id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                FROM ingresos_detalle
                WHERE id_ingreso = :id
                  AND tipo_documento IN ('FACTURA','RECIBO')
                  AND id_referencia_documento IS NOT NULL
                GROUP BY tipo_documento, id_referencia_documento";
        $st = $db->prepare($sql);
        $st->execute([':id' => $idIngreso]);
        $documentos = $st->fetchAll(\PDO::FETCH_ASSOC);

        $lineasPorCuenta = [];
        $totalResuelto = 0.0;

        foreach ($documentos as $doc) {
            $tipoDoc      = (string) $doc['tipo_documento'];
            $idDoc        = (int) $doc['id_referencia_documento'];
            $totalCobrado = round((float) $doc['total_cobrado'], 2);
            $moduloOrigen = self::MODULO_ORIGEN_CARTERA_VENTAS[$tipoDoc] ?? null;
            if ($totalCobrado <= 0 || $moduloOrigen === null) {
                continue;
            }

            $sqlDebe = "SELECT d.id_cuenta_contable, SUM(d.debe) AS monto
                        FROM asientos_contables_cabecera c
                        INNER JOIN asientos_contables_detalle d ON d.id_asiento = c.id
                        WHERE c.modulo_origen = :mod AND c.id_referencia_origen = :id_doc
                          AND c.id_empresa = :emp AND c.eliminado = false AND c.estado != 'anulado'
                          AND d.eliminado = false AND d.debe > 0
                        GROUP BY d.id_cuenta_contable";
            $stDebe = $db->prepare($sqlDebe);
            $stDebe->execute([':mod' => $moduloOrigen, ':id_doc' => $idDoc, ':emp' => $idEmpresa]);
            $debeLineas = $stDebe->fetchAll(\PDO::FETCH_ASSOC);

            // Quedarse SOLO con las cuentas de cartera del documento. Sin esto se prorrateaba
            // sobre todo el Debe —que incluye Costo de Ventas y Descuento en ventas— y el cobro
            // acreditaba cuentas de resultado dejando la cartera sin cancelar del todo.
            // Si ninguna línea coincide (empresa sin el slot configurado, asiento antiguo), se
            // mantiene el comportamiento anterior en vez de dejar el documento sin contrapartida.
            $debeLineas = $this->soloLineasDeCartera(
                $db, $idEmpresa, $debeLineas, self::SLOT_CARTERA_VENTAS[$tipoDoc] ?? ''
            );

            $totalDebeDoc = round((float) array_sum(array_column($debeLineas, 'monto')), 2);
            if (empty($debeLineas) || $totalDebeDoc <= 0) {
                continue; // documento sin asiento propio (o sin Debe): cae al camino normal
            }

            $referencia = self::NOMBRE_CONTRAPARTIDA_CARTERA_VENTAS[$tipoDoc] ?? $tipoDoc;
            $acumuladoDoc = 0.0;
            $ultimaCuentaDoc = null;
            foreach ($debeLineas as $dl) {
                $idCuenta   = (int) $dl['id_cuenta_contable'];
                $proporcion = round((float) $dl['monto'], 2) / $totalDebeDoc;
                $monto      = round($totalCobrado * $proporcion, 2);
                if (!isset($lineasPorCuenta[$idCuenta])) {
                    $lineasPorCuenta[$idCuenta] = ['id_cuenta_contable' => $idCuenta, 'debe' => 0.0, 'haber' => 0.0, 'referencia_detalle' => $referencia];
                }
                $lineasPorCuenta[$idCuenta]['haber'] = round($lineasPorCuenta[$idCuenta]['haber'] + $monto, 2);
                $acumuladoDoc = round($acumuladoDoc + $monto, 2);
                $ultimaCuentaDoc = $idCuenta;
            }
            // Conciliar el redondeo de ESTE documento contra su propio monto cobrado (no el total
            // del ingreso completo, para no mezclar el ajuste entre documentos distintos).
            $difDoc = round($totalCobrado - $acumuladoDoc, 2);
            if (abs($difDoc) >= 0.01 && $ultimaCuentaDoc !== null) {
                $lineasPorCuenta[$ultimaCuentaDoc]['haber'] = round($lineasPorCuenta[$ultimaCuentaDoc]['haber'] + $difDoc, 2);
            }

            $totalResuelto = round($totalResuelto + $totalCobrado, 2);
        }

        return [array_values($lineasPorCuenta), $totalResuelto];
    }

    /**
     * Suma lo pagado (egresos_detalle.monto_pagado) de un egreso que corresponde
     * puntualmente a un rol MENSUAL (tipo_documento='ROL' cuyo rol_detalle.id_rol
     * es de un rol_cabecera.tipo_rol='MENSUAL'). Quincena/Semanal no cuentan aquí:
     * cancelan "Anticipos y Descuentos" — ver sumaRolNoMensualPorEgreso().
     */
    private function sumaRolMensualPorEgreso(\PDO $db, int $idEgreso): float
    {
        $st = $db->prepare("SELECT COALESCE(SUM(ed.monto_pagado), 0)
                             FROM egresos_detalle ed
                             JOIN rol_detalle rd ON rd.id = ed.id_referencia_documento
                             JOIN rol_cabecera rc ON rc.id = rd.id_rol
                             WHERE ed.id_egreso = :id AND ed.tipo_documento = 'ROL'
                               AND rc.tipo_rol = 'MENSUAL' AND ed.eliminado = FALSE");
        $st->execute([':id' => $idEgreso]);
        return round((float) $st->fetchColumn(), 2);
    }

    /**
     * Suma lo pagado (egresos_detalle.monto_pagado) de un egreso que corresponde
     * puntualmente a un rol QUINCENA/SEMANAL (tipo_documento='ROL' cuyo
     * rol_detalle.id_rol es de un rol_cabecera.tipo_rol distinto de MENSUAL).
     * Ese rol nunca se contabiliza por sí solo (RolAsientoService lo rechaza: "solo
     * el rol mensual se contabiliza, las quincenas/semanas se netean en el
     * mensual") — es un anticipo puro. Al pagarlo, cancela la MISMA cuenta
     * "Anticipos y Descuentos" que el rol mensual acredita después vía el neteo
     * (RolAsientoService::contabilizar(), código ANTICIPOSDESCUENTOSNOMINA), en vez
     * de la cuenta genérica del concepto — así no queda un crédito sin contrapartida
     * ni se duplica el gasto si el concepto estaba configurado como cuenta de gasto.
     */
    private function sumaRolNoMensualPorEgreso(\PDO $db, int $idEgreso): float
    {
        $st = $db->prepare("SELECT COALESCE(SUM(ed.monto_pagado), 0)
                             FROM egresos_detalle ed
                             JOIN rol_detalle rd ON rd.id = ed.id_referencia_documento
                             JOIN rol_cabecera rc ON rc.id = rd.id_rol
                             WHERE ed.id_egreso = :id AND ed.tipo_documento = 'ROL'
                               AND rc.tipo_rol <> 'MENSUAL' AND ed.eliminado = FALSE");
        $st->execute([':id' => $idEgreso]);
        return round((float) $st->fetchColumn(), 2);
    }

    /** Cuenta configurada (asientos_programados) para un código de asientos_tipo puntual; 0 si no está configurada. */
    private function cuentaProgramadaPorCodigo(int $idEmpresa, string $tipoAsiento, string $codigo): int
    {
        foreach ($this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, $tipoAsiento) as $r) {
            if (($r['codigo'] ?? '') === $codigo) {
                return (int) ($r['id_cuenta'] ?? 0);
            }
        }
        return 0;
    }

    /**
     * Arma el asiento contable de un TRASPASO ENTRE FORMAS DE PAGO (Tesorería):
     *   DEBE : cuenta de la forma DESTINO, por el monto (entra dinero, como un ingreso).
     *   HABER: cuenta de la forma ORIGEN, por el monto (sale dinero, como un egreso).
     * Reutiliza el mismo mecanismo de resolución de cuenta que lineasFormas(): la cuenta de la
     * forma (empresa_formas_pago.id_cuenta_contable), con override opcional en
     * asientos_programados (tipo_referencia 'forma_pago' para el lado que pierde dinero,
     * 'forma_cobro' para el que lo recibe — mismo criterio que Ingresos/Egresos).
     * Devuelve [] si el traspaso no existe o si a alguna de las dos formas le falta cuenta
     * contable configurada (el asiento queda descuadrado a propósito y no se genera).
     */
    public function generarAsientoTraspaso(int $idEmpresa, int $idTraspaso): array
    {
        $db = \App\core\Database::getConnection();

        $sql = "SELECT t.monto,
                       fo.nombre AS origen_nombre,
                       COALESCE(apo.id_cuenta, fo.id_cuenta_contable) AS origen_cuenta,
                       fd.nombre AS destino_nombre,
                       COALESCE(apd.id_cuenta, fd.id_cuenta_contable) AS destino_cuenta
                FROM traspasos_cabecera t
                INNER JOIN empresa_formas_pago fo ON fo.id = t.id_forma_origen
                INNER JOIN empresa_formas_pago fd ON fd.id = t.id_forma_destino
                LEFT JOIN asientos_programados apo ON apo.id_referencia = fo.id
                                                   AND apo.tipo_referencia = 'forma_pago'
                                                   AND apo.id_empresa = :emp
                                                   AND apo.eliminado = false
                LEFT JOIN asientos_programados apd ON apd.id_referencia = fd.id
                                                   AND apd.tipo_referencia = 'forma_cobro'
                                                   AND apd.id_empresa = :emp
                                                   AND apd.eliminado = false
                WHERE t.id = :id AND t.id_empresa = :emp AND t.eliminado = false";
        $st = $db->prepare($sql);
        $st->execute([':id' => $idTraspaso, ':emp' => $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }

        $monto = round((float) $row['monto'], 2);
        if ($monto <= 0 || empty($row['origen_cuenta']) || empty($row['destino_cuenta'])) {
            return [];
        }

        return [
            [
                'id_cuenta_contable' => (int) $row['destino_cuenta'],
                'debe'               => $monto,
                'haber'              => 0.0,
                'referencia_detalle' => 'Traspaso a: ' . ($row['destino_nombre'] ?? ''),
            ],
            [
                'id_cuenta_contable' => (int) $row['origen_cuenta'],
                'debe'               => 0.0,
                'haber'              => $monto,
                'referencia_detalle' => 'Traspaso desde: ' . ($row['origen_nombre'] ?? ''),
            ],
        ];
    }

    /**
     * Construye la contrapartida (lado concepto) de un ingreso/egreso GENERAL repartida por la
     * cuenta contable elegida en cada línea de descripción. Para documentos sin líneas manuales
     * (egresos/ingresos atados a módulo) devuelve UNA sola línea con la cuenta del concepto por el
     * total, igual que antes (cero regresión).
     *
     * Fuente de la cuenta por línea, en orden de prioridad:
     *   1. $detallesModal: lo que envió el modal al guardar/actualizar (descripcion + id_cuenta_contable).
     *   2. El asiento ya existente del documento (regeneración sin modal): referencia_detalle → cuenta.
     *   3. La cuenta del concepto.
     *
     * Si alguna línea no logra resolver cuenta, NO se concilia el redondeo: el asiento queda
     * descuadrado a propósito para que no se genere (misma política que "forma sin cuenta").
     *
     * @param string $flujo 'ingreso' | 'egreso'
     * @return array<int,array{id_cuenta:int,monto:float,referencia:string}>
     */
    private function contrapartidaPorCuenta(
        \PDO $db, int $idEmpresa, int $idDocumento, string $flujo,
        int $conceptoCuenta, string $conceptoNombre, float $totalMovido, array $detallesModal
    ): array {
        $esEgreso   = ($flujo === 'egreso');
        $tablaDet   = $esEgreso ? 'egresos_detalle' : 'ingresos_detalle';
        $colDoc     = $esEgreso ? 'id_egreso' : 'id_ingreso';
        $colMonto   = $esEgreso ? 'monto_pagado' : 'monto_cobrado';
        $manualTipo = $esEgreso ? 'MANUAL' : 'OTRO';

        // 1. Líneas manuales del documento (descripción + monto). Definen líneas y montos.
        //    egresos_detalle tiene columna 'eliminado'; ingresos_detalle NO (mismo caso que
        //    ingresos_pagos en lineasFormas): solo se filtra por eliminado en egresos.
        $filtroElim = $esEgreso ? ' AND eliminado = FALSE' : '';
        $sql = "SELECT descripcion, {$colMonto} AS monto
                FROM {$tablaDet}
                WHERE {$colDoc} = :id{$filtroElim} AND tipo_documento = :tipo
                ORDER BY id ASC";
        $st = $db->prepare($sql);
        $st->execute([':id' => $idDocumento, ':tipo' => $manualTipo]);
        $manualRows = $st->fetchAll(\PDO::FETCH_ASSOC);

        // Sin líneas manuales (documento atado a módulo): una contrapartida por el total al concepto.
        if (empty($manualRows)) {
            if ($conceptoCuenta <= 0) return [];
            return [['id_cuenta' => $conceptoCuenta, 'monto' => round($totalMovido, 2), 'referencia' => $conceptoNombre]];
        }

        // 2. Mapa descripción → cuenta elegida (modal prioritario; si no, asiento existente).
        $mapaCuenta = [];
        if (!empty($detallesModal)) {
            foreach ($detallesModal as $d) {
                if (($d['tipo_documento'] ?? $manualTipo) !== $manualTipo) continue;
                $desc = trim((string) ($d['descripcion'] ?? ''));
                $cta  = (int) ($d['id_cuenta_contable'] ?? 0);
                if ($cta > 0) $mapaCuenta[$desc] = $cta;
            }
        } else {
            // Recuperar del asiento existente del documento (lado contrapartida).
            $ladoCol = $esEgreso ? 'd.debe' : 'd.haber';
            $sqlAs = "SELECT d.referencia_detalle, d.id_cuenta_contable
                      FROM asientos_contables_cabecera c
                      INNER JOIN asientos_contables_detalle d ON d.id_asiento = c.id
                      WHERE c.modulo_origen = :mod AND c.id_referencia_origen = :id
                        AND c.id_empresa = :emp AND c.eliminado = false AND c.estado != 'anulado'
                        AND d.eliminado = false AND {$ladoCol} > 0
                      ORDER BY c.id DESC, d.id ASC";
            $stAs = $db->prepare($sqlAs);
            $stAs->execute([':mod' => $flujo, ':id' => $idDocumento, ':emp' => $idEmpresa]);
            while ($r = $stAs->fetch(\PDO::FETCH_ASSOC)) {
                $desc = trim((string) ($r['referencia_detalle'] ?? ''));
                $cta  = (int) ($r['id_cuenta_contable'] ?? 0);
                if ($desc !== '' && $cta > 0 && !isset($mapaCuenta[$desc])) {
                    $mapaCuenta[$desc] = $cta;
                }
            }
        }

        // 3. Agrupar por cuenta resultante (cuenta de la línea ?: cuenta del concepto).
        $grupos = [];
        $faltaCuenta = false;
        foreach ($manualRows as $row) {
            $desc  = trim((string) ($row['descripcion'] ?? ''));
            $monto = round((float) ($row['monto'] ?? 0), 2);
            if ($monto <= 0) continue;
            $cta = $mapaCuenta[$desc] ?? $conceptoCuenta;
            if ($cta <= 0) { $faltaCuenta = true; continue; } // sin cuenta → descuadre intencional
            if (!isset($grupos[$cta])) {
                $grupos[$cta] = ['id_cuenta' => $cta, 'monto' => 0.0, 'referencia' => $desc !== '' ? $desc : $conceptoNombre];
            }
            $grupos[$cta]['monto'] = round($grupos[$cta]['monto'] + $monto, 2);
        }

        if (empty($grupos)) {
            // No se mapeó ninguna cuenta: fallback al concepto por el total (o nada si tampoco hay).
            if ($conceptoCuenta <= 0 || $faltaCuenta) return [];
            return [['id_cuenta' => $conceptoCuenta, 'monto' => round($totalMovido, 2), 'referencia' => $conceptoNombre]];
        }

        // 4. Conciliar centavos contra el total movido (la pata banco/caja es la fuente de verdad).
        //    Solo si TODAS las líneas resolvieron cuenta; si faltó alguna, dejamos el descuadre.
        if (!$faltaCuenta) {
            $sumaGrupos = round(array_sum(array_column($grupos, 'monto')), 2);
            $dif = round($totalMovido - $sumaGrupos, 2);
            if (abs($dif) >= 0.01) {
                $keyMax = null; $max = -1.0;
                foreach ($grupos as $k => $g) {
                    if ($g['monto'] > $max) { $max = $g['monto']; $keyMax = $k; }
                }
                if ($keyMax !== null) $grupos[$keyMax]['monto'] = round($grupos[$keyMax]['monto'] + $dif, 2);
            }
        }

        return array_values($grupos);
    }

    /**
     * Construye las líneas de la pata "banco/caja" (formas de cobro/pago) de un ingreso o egreso.
     * Para ingresos van al Debe; para egresos van al Haber. Devuelve [lineas, totalMovido].
     * El total contempla TODAS las formas (con o sin cuenta) para que, si falta alguna cuenta,
     * el asiento quede descuadrado y no se genere.
     *
     * @param string $flujo 'ingreso' | 'egreso'
     * @return array{0: array, 1: float}
     */
    private function lineasFormas(int $idEmpresa, int $idDocumento, string $flujo): array
    {
        $db = \App\core\Database::getConnection();

        if ($flujo === 'ingreso') {
            $tabla = 'ingresos_pagos';
            $colDoc = 'id_ingreso';
            $colForma = 'id_forma_cobro';
            $tipoRef = 'forma_cobro';
            $esDebe = true;
        } else {
            $tabla = 'egresos_pagos';
            $colDoc = 'id_egreso';
            $colForma = 'id_forma_pago';
            $tipoRef = 'forma_pago';
            $esDebe = false;
        }

        // Las tablas de pagos de egresos tienen columna 'eliminado'; las de ingresos no.
        // Un cheque anulado (estado_cheque) se preserva como historial pero deja de
        // contarse aquí: el asiento se recalcula más chico automáticamente, sin asiento
        // de reversión (ver EgresoService::anularCheque).
        $filtroElim = $flujo === 'egreso' ? " AND p.eliminado = FALSE AND COALESCE(p.estado_cheque, 'vigente') <> 'anulado'" : '';

        $sql = "SELECT p.{$colForma} AS id_forma, p.monto,
                       f.nombre AS forma_nombre,
                       COALESCE(ap.id_cuenta, f.id_cuenta_contable) AS id_cuenta,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM {$tabla} p
                INNER JOIN empresa_formas_pago f ON f.id = p.{$colForma}
                LEFT JOIN asientos_programados ap ON ap.id_referencia = f.id
                                                 AND ap.tipo_referencia = :tipo_ref
                                                 AND ap.id_empresa = :emp_ap
                                                 AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, f.id_cuenta_contable)
                WHERE p.{$colDoc} = :id{$filtroElim}
                ORDER BY p.id ASC";
        $st = $db->prepare($sql);
        $st->execute([':id' => $idDocumento, ':emp_ap' => $idEmpresa, ':tipo_ref' => $tipoRef]);

        $detalles = [];
        $total    = 0.0;
        while ($p = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float) $p['monto'], 2);
            if ($monto <= 0) {
                continue;
            }
            $total += $monto;
            if (empty($p['id_cuenta'])) {
                continue; // forma sin cuenta configurada → se omite (descuadra)
            }
            $detalles[] = [
                'id_cuenta_contable' => (int) $p['id_cuenta'],
                'cuenta_codigo'      => $p['cuenta_codigo'],
                'cuenta_nombre'      => $p['cuenta_nombre'],
                'debe'               => $esDebe ? $monto : 0.0,
                'haber'              => $esDebe ? 0.0 : $monto,
                'referencia_detalle' => ($esDebe ? 'Cobro: ' : 'Pago: ') . ($p['forma_nombre'] ?? ''),
            ];
        }

        return [$detalles, round($total, 2)];
    }
}
