<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsientoProgramadoRepository;
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

    private AsientoProgramadoRepository $programadoRepo;

    public function __construct()
    {
        $this->programadoRepo = new AsientoProgramadoRepository();
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
     * Reparto POR LÍNEA del subtotal de ventas en CASCADA: cada línea toma la cuenta de su producto;
     * si no, la de su categoría; si no, la de su marca; si ninguna, la cuenta base (General de Ventas).
     * Agrupa por la cuenta resultante y concilia el redondeo contra $montoTotal.
     *
     * @return array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>
     */
    private function repartirVentasCascada(\PDO $db, int $idEmpresa, int $idVenta, int $idAsientoTipo, array $cuentaBase, float $montoTotal): array
    {
        $baseLinea = [
            'id_cuenta'     => (int)($cuentaBase['id_cuenta'] ?? 0),
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($idVenta <= 0 || $montoTotal <= 0 || empty($baseLinea['id_cuenta'])) {
            return [$baseLinea];
        }

        // COALESCE(producto, categoría, marca) → la cuenta más específica configurada para cada línea.
        $sql = "SELECT COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM(d.precio_total_sin_impuesto)::numeric, 2) AS monto
                FROM ventas_detalle d
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
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta)
                WHERE d.id_venta = :id_doc
                GROUP BY COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_doc'   => $idVenta,
        ]);

        $mapa  = [];
        $total = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            $idCta = $tieneCta ? (int)$row['dim_cuenta'] : (int)$baseLinea['id_cuenta'];
            $cod   = $tieneCta ? ($row['dim_codigo'] ?? '') : $baseLinea['cuenta_codigo'];
            $nom   = $tieneCta ? ($row['dim_nombre'] ?? '') : $baseLinea['cuenta_nombre'];
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa)) {
            return [$baseLinea];
        }

        // Conciliación de redondeo contra el subtotal esperado.
        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            $keys = array_keys($mapa);
            $ult = end($keys);
            $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
        }

        return array_values($mapa);
    }

    /**
     * Igual que repartirVentasCascada() pero para RECIBOS DE VENTA, que guardan sus líneas en
     * recibos_venta_detalle (tabla separada de ventas_detalle, con su propia numeración de IDs).
     *
     * @return array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>
     */
    private function repartirRecibosCascada(\PDO $db, int $idEmpresa, int $idRecibo, int $idAsientoTipo, array $cuentaBase, float $montoTotal): array
    {
        $baseLinea = [
            'id_cuenta'     => (int)($cuentaBase['id_cuenta'] ?? 0),
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($idRecibo <= 0 || $montoTotal <= 0 || empty($baseLinea['id_cuenta'])) {
            return [$baseLinea];
        }

        $sql = "SELECT COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM(d.precio_total_sin_impuesto)::numeric, 2) AS monto
                FROM recibos_venta_detalle d
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
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta)
                WHERE d.id_recibo = :id_doc
                GROUP BY COALESCE(ap_p.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_doc'   => $idRecibo,
        ]);

        $mapa  = [];
        $total = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            $idCta = $tieneCta ? (int)$row['dim_cuenta'] : (int)$baseLinea['id_cuenta'];
            $cod   = $tieneCta ? ($row['dim_codigo'] ?? '') : $baseLinea['cuenta_codigo'];
            $nom   = $tieneCta ? ($row['dim_nombre'] ?? '') : $baseLinea['cuenta_nombre'];
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa)) {
            return [$baseLinea];
        }

        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            $keys = array_keys($mapa);
            $ult = end($keys);
            $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
        }

        return array_values($mapa);
    }

    /**
     * Reparto POR LÍNEA del gasto de COMPRAS por NOMBRE del ítem: cada línea NO inventariable toma la
     * cuenta de la regla 'item_compra' cuya `referencia_texto` coincide con su descripción; si no, la de
     * su categoría; si no, la de su marca; si ninguna, la cuenta base (General de gasto). Incluye los
     * ítems de texto libre (sin id_producto). Concilia el redondeo contra $montoTotal (subGasto).
     *
     * @return array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>
     */
    private function repartirComprasPorItem(\PDO $db, int $idEmpresa, int $idCompra, int $idAsientoTipo, array $cuentaBase, float $montoTotal): array
    {
        $baseLinea = [
            'id_cuenta'     => (int)($cuentaBase['id_cuenta'] ?? 0),
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];
        if ($idCompra <= 0 || $montoTotal <= 0 || empty($baseLinea['id_cuenta'])) {
            return [$baseLinea];
        }

        // Solo líneas de GASTO (no inventariables; incluye ítems de texto libre sin id_producto).
        $sql = "SELECT COALESCE(ap_i.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta) AS dim_cuenta,
                       pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM(d.precio_total_sin_impuesto)::numeric, 2) AS monto
                FROM compras_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
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
                  AND (d.id_producto IS NULL OR COALESCE(p.inventariable, false) <> true OR COALESCE(p.tipo_produccion, '') = '02')
                GROUP BY COALESCE(ap_i.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta), pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':id_tipo1' => $idAsientoTipo, ':emp1' => $idEmpresa,
            ':id_tipo2' => $idAsientoTipo, ':emp2' => $idEmpresa,
            ':id_tipo3' => $idAsientoTipo, ':emp3' => $idEmpresa,
            ':id_doc'   => $idCompra,
        ]);

        $mapa  = [];
        $total = 0.0;
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $monto = round((float)$row['monto'], 2);
            if ($monto == 0.0) continue;
            $tieneCta = !empty($row['dim_cuenta']);
            $idCta = $tieneCta ? (int)$row['dim_cuenta'] : (int)$baseLinea['id_cuenta'];
            $cod   = $tieneCta ? ($row['dim_codigo'] ?? '') : $baseLinea['cuenta_codigo'];
            $nom   = $tieneCta ? ($row['dim_nombre'] ?? '') : $baseLinea['cuenta_nombre'];
            if (!isset($mapa[$idCta])) {
                $mapa[$idCta] = ['id_cuenta' => $idCta, 'cuenta_codigo' => $cod, 'cuenta_nombre' => $nom, 'monto' => 0.0];
            }
            $mapa[$idCta]['monto'] = round($mapa[$idCta]['monto'] + $monto, 2);
            $total = round($total + $monto, 2);
        }

        if (empty($mapa)) {
            return [$baseLinea];
        }

        // Conciliación de redondeo contra el gasto esperado.
        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            $keys = array_keys($mapa);
            $ult = end($keys);
            $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
        }

        return array_values($mapa);
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
    private function aplicarAjusteRedondeo(array $detalles, array $reglas, string $etiqueta, array $reglasSinCuenta = []): array
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

        // Cuenta de ajuste por redondeo configurada para el concepto.
        $ajuste = null;
        foreach ($reglas as $r) {
            $cod = strtoupper($r['asiento_tipo_codigo'] ?? $r['codigo'] ?? '');
            if (str_contains($cod, 'REDONDEO') && !empty($r['id_cuenta'])) {
                $ajuste = $r;
                break;
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
        // Si hay una regla DESC/descuento activa Y hay descuento en la factura,
        // el SUBTOTAL (HABER) debe ser el importe bruto (neto + descuento) para que cuadre.
        $tieneReglaDescuento = false;
        if ($descuento > 0) {
            foreach ($reglas as $r) {
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

        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // La cuenta de Ajuste por redondeo no se mapea aquí (se aplica al final) NI se reporta
            // como faltante: su ausencia solo importa en descuadres de centavos, y para ese caso
            // aplicarAjusteRedondeo() ya emite su propio mensaje. Reportarla en un descuadre grande
            // desviaba el diagnóstico de la cuenta que realmente falta (p. ej. el IVA de una tarifa).
            if (str_contains($codigo, 'REDONDEO')) continue;

            if (empty($r['id_cuenta'])) {
                $reglasSinCuenta[] = $r['asiento_tipo_referencia'] ?? $r['concepto']
                                  ?? $r['asiento_tipo_codigo'] ?? $r['codigo'] ?? 'sin nombre';
                continue;
            }

            $debe  = 0.00;
            $haber = 0.00;
            $valorMapeado = 0.00;
            $esLineaCosto = false;
            $esTotalDocumento = false;

            // Cuenta por cobrar → importe total (incluyendo impuestos)
            if (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valorMapeado = $importeTotal;
                // Ancla del documento: esta línea DEBE valer el total de la factura. El resto
                // del Debe (costo de ventas, descuento) no forma parte de ese total, por eso
                // el cuadre contra la factura se mide aquí y no sobre el Debe total.
                $esTotalDocumento = true;
            }
            // Subtotal / Ventas: neto si no hay desc, bruto si hay cuenta de desc
            elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valorMapeado = $tieneReglaDescuento ? ($subtotal + $descuento) : $subtotal;
                // Reparto en cascada por línea (producto → categoría → marca → General): cada línea a la
                // cuenta más específica de su producto; las no mapeadas, a la cuenta base de Ventas.
                if ($repartePorLinea && !$tieneReglaDescuento && $idVenta > 0 && $valorMapeado > 0) {
                    $lado = (($r['debe_haber'] ?? 'haber') === 'debe') ? 'debe' : 'haber';
                    $partes = $this->repartirVentasCascada(
                        $db, $idEmpresa, $idVenta, (int)$r['id_asiento_tipo'],
                        ['id_cuenta' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'] ?? '', 'cuenta_nombre' => $r['cuenta_nombre'] ?? ''],
                        $valorMapeado
                    );
                    $refBase = $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? 'Ventas';
                    foreach ($partes as $pte) {
                        $detalles[] = [
                            'id_cuenta_contable' => $pte['id_cuenta'],
                            'cuenta_codigo'      => $pte['cuenta_codigo'],
                            'cuenta_nombre'      => $pte['cuenta_nombre'],
                            'debe'               => $lado === 'debe' ? round($pte['monto'], 2) : 0.0,
                            'haber'              => $lado === 'debe' ? 0.0 : round($pte['monto'], 2),
                            'referencia_detalle' => $refBase . ' · por línea',
                        ];
                    }
                    continue;
                }
            }
            // Descuento en ventas
            elseif (str_contains($codigo, 'DESC') || str_contains($concepto, 'descuento')) {
                $valorMapeado = $descuento;
            }
            // ICE
            elseif (str_contains($codigo, 'ICE') || str_contains($concepto, 'ice')) {
                $valorMapeado = $totalIce;
            }
            // Propina
            elseif (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                $valorMapeado = $propina;
            }
            // Costo de Ventas (bloque de costo)
            elseif (str_contains($codigo, 'COSTO') || str_contains($concepto, 'costo')) {
                $valorMapeado = $costoRealInventario;
                $esLineaCosto = true;
            }
            // Inventario (contrapartida del costo, bloque de costo)
            elseif (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valorMapeado = $costoRealInventario;
                $esLineaCosto = true;
            }
            // IVA general: recibe el IVA que no pudo mapearse a cuenta específica por tasa
            elseif (str_contains($codigo, 'IVA') || str_contains($concepto, 'iva')) {
                $valorMapeado = $ivaParaCuentaGeneral > 0 ? $ivaParaCuentaGeneral : 0.0;
            }

            if ($valorMapeado > 0) {
                if (($r['debe_haber'] ?? 'debe') === 'debe') {
                    $debe = $valorMapeado;
                } else {
                    $haber = $valorMapeado;
                }

                $linea = [
                    'id_cuenta_contable' => (int)$r['id_cuenta'],
                    'cuenta_codigo'      => $r['cuenta_codigo'],
                    'cuenta_nombre'      => $r['cuenta_nombre'],
                    'debe'               => round($debe, 2),
                    'haber'              => round($haber, 2),
                    'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '',
                    'es_total_documento' => $esTotalDocumento,
                ];

                if ($esLineaCosto) {
                    $costoLineas[] = $linea;
                } else {
                    $detalles[] = $linea;
                }
            }
        }

        // ── 5.1 Bloque de costo: solo se agrega si está COMPLETO y CUADRADO ──
        // Si están las dos cuentas (Costo de Ventas e Inventario) y suman igual en Debe/Haber,
        // se incorpora el bloque de costo. Si falta una (configuración incompleta), se descarta
        // y el asiento comercial se genera igual; se deja traza para diagnóstico.
        if (!empty($costoLineas)) {
            $debeCosto  = round(array_sum(array_column($costoLineas, 'debe')),  2);
            $haberCosto = round(array_sum(array_column($costoLineas, 'haber')), 2);
            if ($debeCosto > 0 && $debeCosto === $haberCosto) {
                $detalles = array_merge($detalles, $costoLineas);
            } else {
                error_log(
                    "[AsientoBuilder] Bloque de costo de ventas omitido por configuración incompleta " .
                    "(Debe: $debeCosto, Haber: $haberCosto). Configure ambas cuentas (Costo de Ventas e Inventario) " .
                    "para contabilizar el costo. El asiento comercial se generó igualmente."
                );
            }
        }

        // ── 6. Validación de balance ──
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);

        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }

        // Cuadre exacto vía la cuenta de Ajuste por redondeo (la cuenta por cobrar = total del
        // documento es la fuente de verdad; Base + IVA pueden diferir por ±centavos al redondear
        // por separado). Un descuadre > 3 centavos = error real de configuración → excepción.
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'ventas', $reglasSinCuenta);

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
        $tieneReglaDescuento = false;
        if ($descuento > 0) {
            foreach ($reglas as $r) {
                $c  = strtoupper($r['asiento_tipo_codigo']      ?? $r['codigo']     ?? '');
                $cv = strtolower($r['asiento_tipo_referencia']  ?? $r['concepto']   ?? $r['referencia'] ?? '');
                if (str_contains($c, 'DESC') || str_contains($cv, 'descuento')) {
                    $tieneReglaDescuento = true;
                    break;
                }
            }
        }

        // ── 5. Procesar reglas base (idéntico a armarDistribucionVentasFactura) ──
        $costoLineas = [];
        $reglasSinCuenta = $ivaTarifasSinCuenta;

        foreach ($reglas as $r) {
            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            if (str_contains($codigo, 'REDONDEO')) continue;

            if (empty($r['id_cuenta'])) {
                $reglasSinCuenta[] = $r['asiento_tipo_referencia'] ?? $r['concepto']
                                  ?? $r['asiento_tipo_codigo'] ?? $r['codigo'] ?? 'sin nombre';
                continue;
            }

            $debe  = 0.00;
            $haber = 0.00;
            $valorMapeado = 0.00;
            $esLineaCosto = false;
            $esTotalDocumento = false;

            if (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valorMapeado = $importeTotal;
                $esTotalDocumento = true;
            }
            elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valorMapeado = $tieneReglaDescuento ? ($subtotal + $descuento) : $subtotal;
                if ($repartePorLinea && !$tieneReglaDescuento && $idRecibo > 0 && $valorMapeado > 0) {
                    $lado = (($r['debe_haber'] ?? 'haber') === 'debe') ? 'debe' : 'haber';
                    $partes = $this->repartirRecibosCascada(
                        $db, $idEmpresa, $idRecibo, (int)$r['id_asiento_tipo'],
                        ['id_cuenta' => (int)$r['id_cuenta'], 'cuenta_codigo' => $r['cuenta_codigo'] ?? '', 'cuenta_nombre' => $r['cuenta_nombre'] ?? ''],
                        $valorMapeado
                    );
                    $refBase = $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? 'Ventas';
                    foreach ($partes as $pte) {
                        $detalles[] = [
                            'id_cuenta_contable' => $pte['id_cuenta'],
                            'cuenta_codigo'      => $pte['cuenta_codigo'],
                            'cuenta_nombre'      => $pte['cuenta_nombre'],
                            'debe'               => $lado === 'debe' ? round($pte['monto'], 2) : 0.0,
                            'haber'              => $lado === 'debe' ? 0.0 : round($pte['monto'], 2),
                            'referencia_detalle' => $refBase . ' · por línea',
                        ];
                    }
                    continue;
                }
            }
            elseif (str_contains($codigo, 'DESC') || str_contains($concepto, 'descuento')) {
                $valorMapeado = $descuento;
            }
            elseif (str_contains($codigo, 'ICE') || str_contains($concepto, 'ice')) {
                $valorMapeado = $totalIce;
            }
            elseif (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                $valorMapeado = $propina;
            }
            elseif (str_contains($codigo, 'COSTO') || str_contains($concepto, 'costo')) {
                $valorMapeado = $costoRealInventario;
                $esLineaCosto = true;
            }
            elseif (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valorMapeado = $costoRealInventario;
                $esLineaCosto = true;
            }
            elseif (str_contains($codigo, 'IVA') || str_contains($concepto, 'iva')) {
                $valorMapeado = $ivaParaCuentaGeneral > 0 ? $ivaParaCuentaGeneral : 0.0;
            }

            if ($valorMapeado > 0) {
                if (($r['debe_haber'] ?? 'debe') === 'debe') {
                    $debe = $valorMapeado;
                } else {
                    $haber = $valorMapeado;
                }

                $linea = [
                    'id_cuenta_contable' => (int)$r['id_cuenta'],
                    'cuenta_codigo'      => $r['cuenta_codigo'],
                    'cuenta_nombre'      => $r['cuenta_nombre'],
                    'debe'               => round($debe, 2),
                    'haber'              => round($haber, 2),
                    'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '',
                    'es_total_documento' => $esTotalDocumento,
                ];

                if ($esLineaCosto) {
                    $costoLineas[] = $linea;
                } else {
                    $detalles[] = $linea;
                }
            }
        }

        // ── 5.1 Bloque de costo: solo se agrega si está COMPLETO y CUADRADO ──
        if (!empty($costoLineas)) {
            $debeCosto  = round(array_sum(array_column($costoLineas, 'debe')),  2);
            $haberCosto = round(array_sum(array_column($costoLineas, 'haber')), 2);
            if ($debeCosto > 0 && $debeCosto === $haberCosto) {
                $detalles = array_merge($detalles, $costoLineas);
            } else {
                error_log(
                    "[AsientoBuilder] Bloque de costo de recibo de venta omitido por configuración incompleta " .
                    "(Debe: $debeCosto, Haber: $haberCosto). Configure ambas cuentas (Costo de Ventas e Inventario) " .
                    "para contabilizar el costo. El asiento comercial se generó igualmente."
                );
            }
        }

        // ── 6. Validación de balance ──
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);

        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }

        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'recibos de venta', $reglasSinCuenta);

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
                              pc.nombre     AS cuenta_nombre
                       FROM compras_detalle_impuestos i
                       JOIN compras_detalle d ON i.id_compra_detalle = d.id
                       LEFT JOIN productos p ON p.id = d.id_producto
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
                       GROUP BY i.codigo_porcentaje, COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
            $stIva = $db->prepare($sqlIva);
            $stIva->execute([':emp' => $idEmpresa, ':id' => $idCompra, ':id_proveedor' => $idProveedor]);
            while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
                $ivaRows[] = [
                    'id_cuenta'     => $row['id_cuenta'],
                    'cuenta_codigo' => $row['cuenta_codigo'],
                    'cuenta_nombre' => $row['cuenta_nombre'],
                    'valor'         => $row['total_valor'],
                ];
            }
        }

        // ── 4. Ensamblar el cuerpo (Inventario/Gasto/IVA/Por pagar) respetando la dirección ──
        // Reparto del GASTO por NOMBRE del ítem (item_compra → categoría → marca → General): cada línea
        // NO inventariable va a la cuenta de su ítem; las inventariables siguen en Inventario. Solo si
        // el proveedor no tiene reglas propias (si las tiene, manda el proveedor — Opción 2).
        $gastoLineas = null;
        if ($repartePorLinea && $idCompra > 0 && $subGasto > 0) {
            foreach ($reglas as $rr) {
                if (empty($rr['id_cuenta'])) continue;
                $cod = strtoupper($rr['asiento_tipo_codigo'] ?? $rr['codigo'] ?? '');
                $con = strtolower($rr['asiento_tipo_referencia'] ?? $rr['concepto'] ?? $rr['referencia'] ?? '');
                if (str_contains($cod, 'SUBTOTAL') || str_contains($con, 'subtotal')) {
                    $gastoLineas = $this->repartirComprasPorItem(
                        $db, $idEmpresa, $idCompra, (int)$rr['id_asiento_tipo'],
                        ['id_cuenta' => (int)$rr['id_cuenta'], 'cuenta_codigo' => $rr['cuenta_codigo'] ?? '', 'cuenta_nombre' => $rr['cuenta_nombre'] ?? ''],
                        $subGasto
                    );
                    break;
                }
            }
        }

        return $this->ensamblarAdquisicion($reglas, $importeTotal, $subInventario, $subGasto, $propina, $ivaRows, $reversa, $gastoLineas);
    }

    /**
     * Ensambla el cuerpo de un asiento de adquisición (compra o liquidación de compra) a partir
     * de los montos ya calculados, respetando la dirección (normal o reversa para notas de crédito).
     * Reglas base esperadas (asientos_tipo de 'adquisiciones_compras'): INVENTARIO (activo),
     * SUBTOTAL (gasto/costo), PORPAGAR (pasivo), PROPINA. El IVA crédito se pasa en $ivaRows
     * (lado natural = Debe). Reutilizado por compras y liquidaciones.
     *
     * @param array $ivaRows [['id_cuenta','cuenta_codigo','cuenta_nombre','valor'], ...]
     */
    private function ensamblarAdquisicion(array $reglas, float $importeTotal, float $subInventario, float $subGasto, float $propina, array $ivaRows, bool $reversa, ?array $gastoLineas = null): array
    {
        $detalles = [];

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
            if ($valorIva <= 0 || empty($iva['id_cuenta'])) continue;
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
            if (empty($r['id_cuenta'])) continue;

            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // La cuenta de Ajuste por redondeo no se mapea aquí: se aplica al final para cuadrar.
            if (str_contains($codigo, 'REDONDEO')) continue;

            $ladoNatural = ($r['debe_haber'] ?? 'debe') === 'haber' ? 'haber' : 'debe';

            $valor = 0.0;
            if (str_contains($codigo, 'PORPAGAR') || str_contains($concepto, 'pagar')) {
                $valor = $importeTotal;
            } elseif (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valor = $subInventario;
            } elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valor = $subGasto;
                if ($gastoLineas !== null) {
                    // Reparto por dimensión: una línea por cuenta en vez de un solo Subtotal.
                    foreach ($gastoLineas as $gl) {
                        $m = round((float)($gl['monto'] ?? 0), 2);
                        if ($m <= 0) continue;
                        $push(
                            ['id_cuenta' => (int)$gl['id_cuenta'], 'cuenta_codigo' => $gl['cuenta_codigo'] ?? '', 'cuenta_nombre' => $gl['cuenta_nombre'] ?? '', 'asiento_tipo_referencia' => ($r['concepto'] ?? 'Gasto')],
                            $m, $ladoNatural, ($r['concepto'] ?? 'Gasto')
                        );
                    }
                    continue;
                }
            } elseif (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                $valor = $propina;
            }
            // DESCUENTO e ICE: el subtotal ya viene neto por línea; se omiten en v1.

            $push($r, $valor, $ladoNatural, $r['concepto'] ?? '');
        }

        // ── Validación de balance + cuadre por cuenta de Ajuste por redondeo ──
        // Los documentos del SRI suelen diferir en centavos entre importe_total y (subtotal + IVA)
        // por redondeo línea a línea. Esa diferencia (≤ 3 centavos) se lleva a la cuenta de Ajuste
        // por redondeo; un descuadre mayor = cuenta/impuesto realmente faltante → excepción.
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);
        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No se ha configurado ninguna cuenta para el asiento de adquisición o los montos son cero.");
        }

        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'compras');

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
                          pc.nombre     AS cuenta_nombre
                   FROM liquidaciones_detalle_impuestos i
                   JOIN liquidaciones_detalle d ON i.id_detalle = d.id
                   LEFT JOIN productos p ON p.id = d.id_producto
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
                   GROUP BY i.codigo_porcentaje, COALESCE(ap_prov.id_cuenta, ap_item.id_cuenta, ap_c.id_cuenta, ap_m.id_cuenta, ap_gen.id_cuenta), pc.codigo, pc.nombre";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idLiquidacion, ':id_proveedor' => $idProveedor]);
        while ($row = $stIva->fetch(\PDO::FETCH_ASSOC)) {
            $ivaRows[] = [
                'id_cuenta'     => $row['id_cuenta'],
                'cuenta_codigo' => $row['cuenta_codigo'],
                'cuenta_nombre' => $row['cuenta_nombre'],
                'valor'         => $row['total_valor'],
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

        $lineas = [
            ['codigo' => 'INVENTARIOIMPORTACION',           'monto' => $costoTotalNacionalizado],
            ['codigo' => 'IVAIMPORTACION',                  'monto' => $totalIva],
            ['codigo' => 'ISDIMPORTACION',                  'monto' => $totalIsd],
            ['codigo' => 'OTROSGASTOSIMPORTACION',           'monto' => $totalOtros],
            ['codigo' => 'PORPAGARPROVEEDOREXTERIOR',        'monto' => $totalFacturaExterior],
            ['codigo' => 'PORPAGARTRIBUTOSADUANEROS',        'monto' => $totalManual],
            ['codigo' => 'RECLASIFICACIONGASTOIMPORTACION',  'monto' => $totalVinculado],
        ];

        $detalles = [];
        foreach ($lineas as $l) {
            if ($l['monto'] <= 0.0) continue;
            $regla = $porCodigo[$l['codigo']] ?? null;
            if (!$regla || empty($regla['id_cuenta'])) {
                throw new \Exception("No se ha configurado la cuenta contable para '{$l['codigo']}' del concepto Importaciones. Configúrela en /config/asientos-contables.");
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
     * método no se invoca). Debe = cuenta de Activo de la categoría (id_cuenta_activo);
     * Haber = contrapartida configurada en el propio activo (id_cuenta_contrapartida_alta)
     * o, en su defecto, la regla general del concepto 'activos_fijos_alta'
     * (código CONTRAPARTIDAALTAACTIVOFIJO), configurable en Configuración Contable.
     */
    public function generarAsientoAltaActivoFijo(int $idEmpresa, int $idActivo): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT a.valor_adquisicion, a.id_cuenta_contrapartida_alta, a.nombre,
                    cat.id_cuenta_activo,
                    pa.codigo AS cuenta_activo_codigo, pa.nombre AS cuenta_activo_nombre
             FROM activos_fijos a
             INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
             INNER JOIN plan_cuentas pa ON pa.id = cat.id_cuenta_activo
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
     * $idLote) por categoría, y arma una línea Debe (Gasto) + Haber (Depreciación
     * Acumulada) por categoría, leyendo las cuentas directo de
     * activos_fijos_categorias (no pasan por la cascada de asientos_programados).
     */
    public function generarAsientoDepreciacionLote(int $idEmpresa, int $idLote): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT cat.id AS id_categoria, cat.nombre AS categoria_nombre,
                    cat.id_cuenta_gasto_depreciacion, cat.id_cuenta_depreciacion_acumulada,
                    pg.codigo AS gasto_codigo, pg.nombre AS gasto_nombre,
                    pa.codigo AS acumulada_codigo, pa.nombre AS acumulada_nombre,
                    SUM(d.valor_depreciado) AS total
             FROM activos_fijos_depreciaciones d
             INNER JOIN activos_fijos a ON d.id_activo = a.id
             INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
             INNER JOIN plan_cuentas pg ON pg.id = cat.id_cuenta_gasto_depreciacion
             INNER JOIN plan_cuentas pa ON pa.id = cat.id_cuenta_depreciacion_acumulada
             WHERE d.id_lote = ? AND d.eliminado = false
             GROUP BY cat.id, cat.nombre, cat.id_cuenta_gasto_depreciacion, cat.id_cuenta_depreciacion_acumulada,
                      pg.codigo, pg.nombre, pa.codigo, pa.nombre
             ORDER BY cat.nombre ASC"
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
                'referencia_detalle' => 'Gasto depreciación - ' . $r['categoria_nombre'],
            ];
            $detalles[] = [
                'id_cuenta_contable' => (int) $r['id_cuenta_depreciacion_acumulada'],
                'cuenta_codigo'      => $r['acumulada_codigo'],
                'cuenta_nombre'      => $r['acumulada_nombre'],
                'debe'               => 0.0,
                'haber'              => $monto,
                'referencia_detalle' => 'Depreciación acumulada - ' . $r['categoria_nombre'],
            ];
        }

        if (empty($detalles)) return [];

        // Cuadra por construcción (cada categoría aporta el mismo monto a Debe y Haber);
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

        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;

            $debe  = 0.00;
            $haber = 0.00;
            $valorMapeado = 0.00;

            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // La cuenta de Ajuste por redondeo no se mapea aquí: se aplica al final para cuadrar.
            if (str_contains($codigo, 'REDONDEO')) continue;

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
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }

        // Cuadre exacto vía la cuenta de Ajuste por redondeo; descuadre > 3 centavos → excepción.
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'el documento');

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

        // ── 4. Reglas base de ventas_factura → por cobrar, subtotal, costo, inventario ──
        $reglas = $this->programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'ventas_factura');
        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;
            $codigo   = strtoupper($r['codigo']   ?? '');
            $concepto = strtolower($r['concepto'] ?? $r['referencia'] ?? '');
            $ladoNatural = ($r['debe_haber'] ?? 'debe') === 'haber' ? 'haber' : 'debe';

            $valor = 0.0; $esCosto = false;
            if (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valor = $importeTotal;
            } elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valor = $subtotal;
            } elseif (str_contains($codigo, 'COSTO') || str_contains($concepto, 'costo')) {
                $valor = $costo; $esCosto = true;
            } elseif (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valor = $costo; $esCosto = true;
            } else {
                continue; // ICE / propina / descuento / IVA-base no aplican a la NC
            }
            if ($valor <= 0) continue;

            $linea = [
                'id_cuenta_contable' => (int)$r['id_cuenta'],
                'cuenta_codigo'      => $r['cuenta_codigo'] ?? '',
                'cuenta_nombre'      => $r['cuenta_nombre'] ?? '',
                'debe'               => $ladoNatural === 'debe' ? round($valor, 2) : 0.0,
                'haber'              => $ladoNatural === 'debe' ? 0.0 : round($valor, 2),
                'referencia_detalle' => ($r['concepto'] ?? $r['referencia'] ?? '') . ' (NC)',
            ];
            if ($esCosto) { $costoLineas[] = $linea; } else { $comercial[] = $linea; }
        }

        // El bloque de costo solo entra si está COMPLETO y CUADRADO (ambas cuentas configuradas).
        $detallesNatural = $comercial;
        if (!empty($costoLineas)) {
            $dc = round(array_sum(array_column($costoLineas, 'debe')),  2);
            $hc = round(array_sum(array_column($costoLineas, 'haber')), 2);
            if ($dc > 0 && $dc === $hc) {
                $detallesNatural = array_merge($detallesNatural, $costoLineas);
            }
        }

        if (empty($detallesNatural)) {
            return [];
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
            throw new \Exception("No hay cuentas configuradas para la nota de crédito de venta o los montos son cero.");
        }

        // Cuadre exacto vía la cuenta de Ajuste por redondeo (reusa la config de ventas_factura).
        // Un descuadre > 3 centavos = error real de configuración → excepción.
        $detalles = $this->aplicarAjusteRedondeo($detalles, $reglas, 'ventas (nota de crédito)');

        return $detalles;
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
                          o.nombre             AS concepto_nombre
                   FROM ingresos_cabecera i
                   LEFT JOIN empresa_opciones_ingreso_egreso o ON o.id = i.id_ingreso_concepto
                   WHERE i.id = :id AND i.id_empresa = :emp AND i.eliminado = false";
        $stCab = $db->prepare($sqlCab);
        $stCab->execute([':id' => $idIngreso, ':emp' => $idEmpresa]);
        $ingreso = $stCab->fetch(\PDO::FETCH_ASSOC);
        if (!$ingreso) {
            return [];
        }

        // ── DEBE: formas de cobro (banco/caja) → config Cobros/Pagos ──
        [$detalles, $totalMovido] = $this->lineasFormas($idEmpresa, $idIngreso, 'ingreso');
        if ($totalMovido <= 0) {
            return [];
        }

        // ── HABER: contrapartida repartida por la cuenta de cada línea de descripción.
        //    Por defecto la cuenta del concepto; si la línea trae otra, manda la de la línea.
        $contrapartida = $this->contrapartidaPorCuenta(
            $db, $idEmpresa, $idIngreso, 'ingreso',
            (int) ($ingreso['concepto_id_cuenta'] ?? 0),
            (string) ($ingreso['concepto_nombre'] ?? 'Ingreso'),
            $totalMovido, $detallesConCuenta
        );
        foreach ($contrapartida as $linea) {
            $detalles[] = [
                'id_cuenta_contable' => $linea['id_cuenta'],
                'debe'               => 0.0,
                'haber'              => round($linea['monto'], 2),
                'referencia_detalle' => $linea['referencia'],
            ];
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
                          o.nombre             AS concepto_nombre
                   FROM egresos_cabecera e
                   LEFT JOIN empresa_opciones_ingreso_egreso o ON o.id = e.id_egreso_concepto
                   WHERE e.id = :id AND e.id_empresa = :emp AND e.eliminado = false";
        $stCab = $db->prepare($sqlCab);
        $stCab->execute([':id' => $idEgreso, ':emp' => $idEmpresa]);
        $egreso = $stCab->fetch(\PDO::FETCH_ASSOC);
        if (!$egreso) {
            return [];
        }

        // ── HABER: formas de pago (banco/caja) → config Cobros/Pagos ──
        [$detalles, $totalMovido] = $this->lineasFormas($idEmpresa, $idEgreso, 'egreso');
        if ($totalMovido <= 0) {
            return [];
        }

        // ── DEBE: contrapartida repartida por la cuenta de cada línea de descripción.
        //    Por defecto la cuenta del concepto; si la línea trae otra, manda la de la línea.
        $contrapartida = $this->contrapartidaPorCuenta(
            $db, $idEmpresa, $idEgreso, 'egreso',
            (int) ($egreso['concepto_id_cuenta'] ?? 0),
            (string) ($egreso['concepto_nombre'] ?? 'Egreso'),
            $totalMovido, $detallesConCuenta
        );
        foreach ($contrapartida as $linea) {
            $detalles[] = [
                'id_cuenta_contable' => $linea['id_cuenta'],
                'debe'               => round($linea['monto'], 2),
                'haber'              => 0.0,
                'referencia_detalle' => $linea['referencia'],
            ];
        }

        return $detalles;
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
        $filtroElim = $flujo === 'egreso' ? ' AND p.eliminado = FALSE' : '';

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
