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

        // 2. Obtener el método de preferencia contable establecido por la empresa para este módulo
        $metodo = $this->programadoRepo->getMetodoPreferencia($idEmpresa, $tipoAsiento);

        // 3. Resolver cuentas específicas en base al método de preferencia activo
        $customAccounts = $this->resolverCuentasPorMetodo($idEmpresa, $tipoAsiento, $metodo, $documentData);

        // 4. Combinar la plantilla base con las cuentas específicas resueltas (Fallback automático)
        foreach ($reglasBase as &$r) {
            $idAsientoTipo = (int)$r['id_asiento_tipo'];
            if (isset($customAccounts[$idAsientoTipo])) {
                $r['id_cuenta'] = $customAccounts[$idAsientoTipo]['id_cuenta'];
                $r['cuenta_codigo'] = $customAccounts[$idAsientoTipo]['cuenta_codigo'];
                $r['cuenta_nombre'] = $customAccounts[$idAsientoTipo]['cuenta_nombre'];
            }
        }
        unset($r);

        // 5. Armar la distribución de importes Debe/Haber según el módulo específico
        $asientoResult = match ($tipoAsiento) {
            'ventas_factura' => $this->armarDistribucionVentasFactura($reglasBase, $documentData),
            // Se pueden agregar módulos adicionales aquí:
            // 'adquisiciones_compras' => $this->armarDistribucionCompras($reglasBase, $documentData),
            default => $this->armarDistribucionEstandar($reglasBase, $documentData),
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
     * Resuelve cuentas contables personalizadas basadas en el método de preferencia configurado.
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
            
            $st = $this->programadoRepo->query($sql, [
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

            // 3b. IVA por tasa con cuenta específica en asientos_programados
            $sqlIva = "SELECT i.codigo_porcentaje,
                              SUM(i.valor)    AS total_valor,
                              ap.id_cuenta    AS id_cuenta_contable,
                              pc.codigo       AS cuenta_codigo,
                              pc.nombre       AS cuenta_nombre
                       FROM ventas_detalle_impuestos i
                       JOIN ventas_detalle d ON i.id_venta_detalle = d.id
                       JOIN asientos_programados ap
                            ON ap.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                           AND ap.tipo_referencia = 'iva_ventas_factura'
                           AND ap.id_empresa      = :id_empresa
                           AND ap.eliminado       = false
                       LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                       WHERE d.id_venta = :id_venta AND i.codigo_impuesto = '2'
                       GROUP BY i.codigo_porcentaje, ap.id_cuenta, pc.codigo, pc.nombre";
            $stIva = $db->prepare($sqlIva);
            $stIva->execute([':id_empresa' => $idEmpresa, ':id_venta' => $idVenta]);

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
        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;

            $debe  = 0.00;
            $haber = 0.00;
            $valorMapeado = 0.00;

            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // Cuenta por cobrar → importe total (incluyendo impuestos)
            if (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valorMapeado = $importeTotal;
            }
            // Subtotal / Ventas: neto si no hay desc, bruto si hay cuenta de desc
            elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valorMapeado = $tieneReglaDescuento ? ($subtotal + $descuento) : $subtotal;
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
            // Costo de Ventas
            elseif (str_contains($codigo, 'COSTO') || str_contains($concepto, 'costo')) {
                $valorMapeado = $costoRealInventario;
            }
            // Inventario (contrapartida del costo)
            elseif (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valorMapeado = $costoRealInventario;
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

        // ── 6. Validación de balance ──
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);

        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No se ha configurado ninguna cuenta para este asiento o los montos son cero.");
        }
        if ($totalDebe !== $totalHaber) {
            throw new \Exception(
                "El asiento no cuadra. Debe: $" . number_format($totalDebe, 2) .
                ", Haber: $" . number_format($totalHaber, 2) .
                ". Revise la configuración de cuentas contables para ventas."
            );
        }

        return $detalles;
    }

    /**
     * Distribución genérica fallback en caso de no estar definido un comportamiento específico para el módulo.
     */
    private function armarDistribucionEstandar(array $reglas, array $data): array
    {
        $importeTotal = (float)($data['importe_total'] ?? $data['total'] ?? 0);
        $detalles = [];

        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;

            $debe = $r['debe_haber'] === 'debe' ? $importeTotal : 0.00;
            $haber = $r['debe_haber'] === 'haber' ? $importeTotal : 0.00;

            $detalles[] = [
                'id_cuenta_contable' => (int)$r['id_cuenta'],
                'cuenta_codigo' => $r['cuenta_codigo'],
                'cuenta_nombre' => $r['cuenta_nombre'],
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
                'referencia_detalle' => $r['asiento_tipo_referencia'] ?? $r['concepto'] ?? $r['referencia'] ?? '',
            ];
        }

        return $detalles;
    }
}
