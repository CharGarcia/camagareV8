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

        // 5. Armar la distribución de importes Debe/Haber según el módulo específico.
        //    Se pasa el método activo para que los builders puedan repartir por línea
        //    (producto/categoría/marca) la cuenta de ventas/gasto del documento.
        $documentData['__metodo__'] = $metodo;
        $asientoResult = match ($tipoAsiento) {
            'ventas_factura' => $this->armarDistribucionVentasFactura($reglasBase, $documentData),
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
     * Reparto POR LÍNEA de un monto (subtotal de ventas / gasto de compras) entre cuentas, según la
     * dimensión activa (producto/categoría/marca): suma las líneas del documento agrupadas por la
     * cuenta mapeada de su producto/categoría/marca para el asiento_tipo indicado. Las líneas sin
     * cuenta mapeada caen en $cuentaBase. Para métodos sin reparto (general/cliente/proveedor/iva)
     * o sin documento, devuelve una sola entrada con $cuentaBase y $montoTotal.
     *
     * @param string $soloInventariable ''=todas | 'si'=solo inventariables | 'no'=solo NO inventariables
     * @return array<int,array{id_cuenta:int,cuenta_codigo:string,cuenta_nombre:string,monto:float}>
     */
    private function repartirPorDimension(\PDO $db, int $idEmpresa, string $metodo, int $idDoc, string $detalleTabla, string $fkCol, int $idAsientoTipo, array $cuentaBase, float $montoTotal, string $soloInventariable = ''): array
    {
        $baseLinea = [
            'id_cuenta'     => (int)($cuentaBase['id_cuenta'] ?? 0),
            'cuenta_codigo' => $cuentaBase['cuenta_codigo'] ?? '',
            'cuenta_nombre' => $cuentaBase['cuenta_nombre'] ?? '',
            'monto'         => round($montoTotal, 2),
        ];

        if (!in_array($metodo, ['producto', 'categoria', 'marca'], true) || $idDoc <= 0 || $montoTotal <= 0 || empty($baseLinea['id_cuenta'])) {
            return [$baseLinea];
        }

        $dimExpr = match ($metodo) {
            'categoria' => 'p.id_categoria',
            'marca'     => 'p.id_marca',
            default     => 'd.id_producto',
        };

        $filtroInv = '';
        if ($soloInventariable === 'no') {
            $filtroInv = " AND NOT (p.inventariable = true AND COALESCE(p.tipo_produccion,'') <> '02')";
        } elseif ($soloInventariable === 'si') {
            $filtroInv = " AND (p.inventariable = true AND COALESCE(p.tipo_produccion,'') <> '02')";
        }

        $sql = "SELECT ap.id_cuenta AS dim_cuenta, pc.codigo AS dim_codigo, pc.nombre AS dim_nombre,
                       ROUND(SUM(d.precio_total_sin_impuesto)::numeric, 2) AS monto
                FROM {$detalleTabla} d
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN asientos_programados ap
                       ON ap.id_referencia   = {$dimExpr}
                      AND ap.tipo_referencia = :metodo
                      AND ap.id_asiento_tipo = :id_tipo
                      AND ap.id_empresa      = :emp
                      AND ap.eliminado       = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE d.{$fkCol} = :id_doc {$filtroInv}
                GROUP BY ap.id_cuenta, pc.codigo, pc.nombre";
        $st = $db->prepare($sql);
        $st->execute([
            ':metodo'  => $metodo,
            ':id_tipo' => $idAsientoTipo,
            ':emp'     => $idEmpresa,
            ':id_doc'  => $idDoc,
        ]);

        $mapa  = []; // id_cuenta => linea
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

        // Ajuste de redondeo: cuadrar la suma de partes con el monto total esperado.
        $dif = round($montoTotal - $total, 2);
        if (abs($dif) >= 0.01) {
            $keys = array_keys($mapa);
            $ult = end($keys);
            $mapa[$ult]['monto'] = round($mapa[$ult]['monto'] + $dif, 2);
        }

        return array_values($mapa);
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
        $metodo   = (string)($data['__metodo__'] ?? 'general');
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
        // El bloque comercial (por cobrar, ventas, IVA, ICE, propina, descuento) se agrega
        // directamente a $detalles. El bloque de costo (Costo de Ventas + Inventario) se
        // recolecta aparte en $costoLineas y solo se añade si está COMPLETO y CUADRADO
        // (ambas cuentas configuradas). Si falta una de las dos, se descarta el bloque de
        // costo para que el asiento comercial se genere igual y no descuadre.
        $costoLineas = [];

        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;

            $debe  = 0.00;
            $haber = 0.00;
            $valorMapeado = 0.00;
            $esLineaCosto = false;

            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');

            // Cuenta por cobrar → importe total (incluyendo impuestos)
            if (str_contains($codigo, 'PORCOBRAR') || str_contains($concepto, 'cobrar')) {
                $valorMapeado = $importeTotal;
            }
            // Subtotal / Ventas: neto si no hay desc, bruto si hay cuenta de desc
            elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valorMapeado = $tieneReglaDescuento ? ($subtotal + $descuento) : $subtotal;
                // Reparto por dimensión (producto/categoría/marca): cada línea a la cuenta de su
                // producto/categoría/marca; las no mapeadas, a la cuenta base de Ventas.
                if (in_array($metodo, ['producto', 'categoria', 'marca'], true) && !$tieneReglaDescuento && $idVenta > 0 && $valorMapeado > 0) {
                    $lado = (($r['debe_haber'] ?? 'haber') === 'debe') ? 'debe' : 'haber';
                    $partes = $this->repartirPorDimension(
                        $db, $idEmpresa, $metodo, $idVenta, 'ventas_detalle', 'id_venta', (int)$r['id_asiento_tipo'],
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
                            'referencia_detalle' => $refBase . ' · ' . ucfirst($metodo),
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
        $metodo    = (string)($data['__metodo__'] ?? 'general');
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

        // ── 3. IVA crédito tributario por tarifa (cuenta configurada en iva_compras_factura) ──
        $ivaRows = [];
        if ($idCompra > 0) {
            $sqlIva = "SELECT i.codigo_porcentaje,
                              SUM(i.valor)  AS total_valor,
                              ap.id_cuenta,
                              pc.codigo     AS cuenta_codigo,
                              pc.nombre     AS cuenta_nombre
                       FROM compras_detalle_impuestos i
                       JOIN compras_detalle d ON i.id_compra_detalle = d.id
                       JOIN asientos_programados ap
                            ON ap.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                           AND ap.tipo_referencia = 'iva_compras_factura'
                           AND ap.id_empresa      = :emp
                           AND ap.eliminado       = false
                       LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                       WHERE d.id_compra = :id AND i.codigo_impuesto = '2'
                       GROUP BY i.codigo_porcentaje, ap.id_cuenta, pc.codigo, pc.nombre";
            $stIva = $db->prepare($sqlIva);
            $stIva->execute([':emp' => $idEmpresa, ':id' => $idCompra]);
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
        // Reparto por dimensión (producto/categoría/marca) del gasto: las líneas NO inventariables
        // van a la cuenta de su producto/categoría/marca; las inventariables siguen en Inventario.
        $gastoLineas = null;
        if (in_array($metodo, ['producto', 'categoria', 'marca'], true) && $idCompra > 0 && $subGasto > 0) {
            foreach ($reglas as $rr) {
                if (empty($rr['id_cuenta'])) continue;
                $cod = strtoupper($rr['asiento_tipo_codigo'] ?? $rr['codigo'] ?? '');
                $con = strtolower($rr['asiento_tipo_referencia'] ?? $rr['concepto'] ?? $rr['referencia'] ?? '');
                if (str_contains($cod, 'SUBTOTAL') || str_contains($con, 'subtotal')) {
                    $gastoLineas = $this->repartirPorDimension(
                        $db, $idEmpresa, $metodo, $idCompra, 'compras_detalle', 'id_compra', (int)$rr['id_asiento_tipo'],
                        ['id_cuenta' => (int)$rr['id_cuenta'], 'cuenta_codigo' => $rr['cuenta_codigo'] ?? '', 'cuenta_nombre' => $rr['cuenta_nombre'] ?? ''],
                        $subGasto, 'no'
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

        // Reglas base (cuerpo del asiento). Se recuerda el índice de la línea de gasto (Subtotal)
        // e Inventario para absorber en ella una eventual diferencia de redondeo del documento.
        $idxGasto      = null;
        $idxInventario = null;
        foreach ($reglas as $r) {
            if (empty($r['id_cuenta'])) continue;

            $codigo   = strtoupper($r['asiento_tipo_codigo']     ?? $r['codigo']    ?? '');
            $concepto = strtolower($r['asiento_tipo_referencia'] ?? $r['concepto']  ?? $r['referencia'] ?? '');
            $ladoNatural = ($r['debe_haber'] ?? 'debe') === 'haber' ? 'haber' : 'debe';

            $valor = 0.0;
            $esSubtotal = false; $esInventario = false;
            if (str_contains($codigo, 'PORPAGAR') || str_contains($concepto, 'pagar')) {
                $valor = $importeTotal;
            } elseif (str_contains($codigo, 'INVENTARIO') || str_contains($concepto, 'inventario')) {
                $valor = $subInventario; $esInventario = true;
            } elseif (str_contains($codigo, 'SUBTOTAL') || str_contains($concepto, 'subtotal')) {
                $valor = $subGasto; $esSubtotal = true;
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
                    $idxGasto = count($detalles) - 1;
                    continue;
                }
            } elseif (str_contains($codigo, 'PROPINA') || str_contains($concepto, 'propina')) {
                $valor = $propina;
            }
            // DESCUENTO e ICE: el subtotal ya viene neto por línea; se omiten en v1.

            $antes = count($detalles);
            $push($r, $valor, $ladoNatural, $r['concepto'] ?? '');
            if (count($detalles) > $antes) {
                if ($esSubtotal)       $idxGasto      = count($detalles) - 1;
                elseif ($esInventario) $idxInventario = count($detalles) - 1;
            }
        }

        // ── Conciliación de redondeo ──
        // Los documentos del SRI suelen diferir en centavos entre importe_total y (subtotal + IVA)
        // por redondeo línea a línea. Si la diferencia es pequeña (≤ 0.05) se absorbe en la línea de
        // gasto (o inventario) como ajuste por redondeo. Si es mayor, se trata de una cuenta/impuesto
        // realmente faltante: NO se absorbe y el asiento queda descuadrado (se avisa).
        $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
        $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);
        $diff = round($totalDebe - $totalHaber, 2);
        $idxAjuste = $idxGasto ?? $idxInventario;
        if ($diff !== 0.0 && abs($diff) <= 0.05 && $idxAjuste !== null) {
            if ($detalles[$idxAjuste]['debe'] > 0) {
                $nuevo = round($detalles[$idxAjuste]['debe'] - $diff, 2);
                if ($nuevo > 0) $detalles[$idxAjuste]['debe'] = $nuevo;
            } else {
                $nuevo = round($detalles[$idxAjuste]['haber'] + $diff, 2);
                if ($nuevo > 0) $detalles[$idxAjuste]['haber'] = $nuevo;
            }
            $totalDebe  = round(array_sum(array_column($detalles, 'debe')),  2);
            $totalHaber = round(array_sum(array_column($detalles, 'haber')), 2);
        }

        // ── Validación de balance ──
        if ($totalDebe === 0.0 && $totalHaber === 0.0) {
            throw new \Exception("No se ha configurado ninguna cuenta para el asiento de adquisición o los montos son cero.");
        }
        if ($totalDebe !== $totalHaber) {
            throw new \Exception(
                "El asiento de adquisición no cuadra. Debe: $" . number_format($totalDebe, 2) .
                ", Haber: $" . number_format($totalHaber, 2) .
                ". Revise las cuentas de Inventario, Subtotal (gasto), IVA crédito y Cuentas por pagar."
            );
        }

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
        $stCab = $db->prepare("SELECT importe_total, total_sin_impuestos FROM liquidaciones_cabecera WHERE id = ?");
        $stCab->execute([$idLiquidacion]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
        $importeTotal = round((float)($cab['importe_total']      ?? 0), 2);
        $subtotal     = round((float)($cab['total_sin_impuestos'] ?? 0), 2);
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

        // 3. IVA crédito por tarifa (reutiliza la config de compras: iva_compras_factura)
        $ivaRows = [];
        $sqlIva = "SELECT i.codigo_porcentaje,
                          SUM(i.valor)  AS total_valor,
                          ap.id_cuenta,
                          pc.codigo     AS cuenta_codigo,
                          pc.nombre     AS cuenta_nombre
                   FROM liquidaciones_detalle_impuestos i
                   JOIN liquidaciones_detalle d ON i.id_detalle = d.id
                   JOIN asientos_programados ap
                        ON ap.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                       AND ap.tipo_referencia = 'iva_compras_factura'
                       AND ap.id_empresa      = :emp
                       AND ap.eliminado       = false
                   LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                   WHERE d.id_cabecera = :id AND i.codigo_impuesto = '2'
                   GROUP BY i.codigo_porcentaje, ap.id_cuenta, pc.codigo, pc.nombre";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idLiquidacion]);
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
        if ($totalDebe !== $totalHaber) {
            throw new \Exception(
                "El asiento no cuadra. Debe: $" . number_format($totalDebe, 2) .
                ", Haber: $" . number_format($totalHaber, 2) .
                ". Revise la configuración de cuentas."
            );
        }

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
            "SELECT importe_total, total_sin_impuestos, COALESCE(total_descuento,0) AS total_descuento
             FROM notas_credito_cabecera WHERE id = ?"
        );
        $stCab->execute([$idNotaCredito]);
        $cab = $stCab->fetch(\PDO::FETCH_ASSOC) ?: [];
        $importeTotal = round((float)($cab['importe_total']      ?? 0), 2);
        $subtotal     = round((float)($cab['total_sin_impuestos'] ?? 0), 2);
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

        // ── 3. IVA por tarifa: mismas cuentas que la factura (iva_ventas_factura), lado natural HABER ──
        $sqlIva = "SELECT i.codigo_porcentaje, SUM(i.valor) AS total_valor,
                          ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                   FROM notas_credito_detalle_impuestos i
                   JOIN notas_credito_detalle d ON i.id_nota_credito_detalle = d.id
                   JOIN asientos_programados ap
                        ON ap.id_referencia   = CAST(i.codigo_porcentaje AS INTEGER)
                       AND ap.tipo_referencia = 'iva_ventas_factura'
                       AND ap.id_empresa      = :emp AND ap.eliminado = false
                   LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                   WHERE d.id_nota_credito = :id AND i.codigo_impuesto = '2'
                   GROUP BY i.codigo_porcentaje, ap.id_cuenta, pc.codigo, pc.nombre";
        $stIva = $db->prepare($sqlIva);
        $stIva->execute([':emp' => $idEmpresa, ':id' => $idNotaCredito]);
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
        if ($totalDebe !== $totalHaber) {
            throw new \Exception(
                "El asiento de la nota de crédito no cuadra. Debe: $" . number_format($totalDebe, 2) .
                ", Haber: $" . number_format($totalHaber, 2) .
                ". Revise las cuentas de Ventas, IVA y Cuentas por cobrar de la factura de venta."
            );
        }

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
     * Arma el asiento contable de un INGRESO usando solo la configuración contable propia
     * (Tipo de asiento → Ingresos/Egresos y Cobros/Pagos), sin depender de asientos tipo:
     *   DEBE : cuenta de cada forma de cobro (config Cobros/Pagos), por su monto.
     *   HABER: cuenta del concepto del ingreso (config Ingresos/Egresos), por el total cobrado.
     *
     * Devuelve [] si no hay valores. Las líneas sin cuenta configurada se omiten;
     * el asiento quedará descuadrado y no se generará (se avisa).
     */
    public function generarAsientoIngreso(int $idEmpresa, int $idIngreso): array
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

        // ── HABER: cuenta del concepto del ingreso → config Ingresos/Egresos ──
        if (!empty($ingreso['concepto_id_cuenta'])) {
            $detalles[] = [
                'id_cuenta_contable' => (int) $ingreso['concepto_id_cuenta'],
                'debe'               => 0.0,
                'haber'              => $totalMovido,
                'referencia_detalle' => $ingreso['concepto_nombre'] ?? 'Ingreso',
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
    public function generarAsientoEgreso(int $idEmpresa, int $idEgreso): array
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

        // ── DEBE: cuenta del concepto del egreso → config Ingresos/Egresos ──
        if (!empty($egreso['concepto_id_cuenta'])) {
            $detalles[] = [
                'id_cuenta_contable' => (int) $egreso['concepto_id_cuenta'],
                'debe'               => $totalMovido,
                'haber'              => 0.0,
                'referencia_detalle' => $egreso['concepto_nombre'] ?? 'Egreso',
            ];
        }

        return $detalles;
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
