<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;

class SincronizadorAsientosService
{
    private array $warnings = [];
    private int $generados = 0;

    public function sincronizar(int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();

        // Asegurar que la columna id_asiento_contable exista en todas las tablas operativas
        // antes de realizar cualquier consulta SELECT sobre ellas.
        try {
            $db->exec("ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE liquidaciones_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE notas_credito_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE nota_debito_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE retencion_venta_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE retencion_compra_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE ingresos_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE egresos_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE consignaciones_ventas ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
        } catch (\Throwable $e) {
            // Ignorar errores si no tiene permisos o ya existen
        }

        // 1. Facturas de Venta
        //    Se (re)generan dos grupos:
        //    (a) las que no tienen ningún asiento todavía, y
        //    (b) las que YA tienen asiento pero les falta el bloque de costo de ventas,
        //        siempre que: vendieron inventario con costo en el Kardex (costo_total > 0)
        //        y estén configuradas AMBAS cuentas (Costo de Ventas e Inventario). Si falta
        //        una de las dos no se reprocesa (evita reproceso en cada carga) y se avisa
        //        en verificarCosteoVentasPendiente(). El "juez" de si se necesita costeo es
        //        el Kardex de la factura, no la configuración de cuentas.
        $subCosto      = $this->sqlCuentaVentasPorPalabra('COSTO');
        $subInventario = $this->sqlCuentaVentasPorPalabra('INVENTARIO');

        $sqlFacturas = "SELECT v.id
                        FROM ventas_cabecera v
                        WHERE v.id_empresa = ?
                          AND v.eliminado = false
                          AND v.estado IN ('autorizado', 'contabilizado')
                          AND (
                                v.id_asiento_contable IS NULL
                             OR (
                                    v.id_asiento_contable IS NOT NULL
                                AND EXISTS (SELECT 1 FROM inventario_kardex k
                                            WHERE k.referencia_tipo = 'factura_venta'
                                              AND k.referencia_id   = v.id
                                              AND k.tipo_movimiento = 'salida'
                                              AND k.eliminado       = false
                                              AND k.costo_total      > 0)
                                AND EXISTS ($subCosto)
                                AND EXISTS ($subInventario)
                                AND NOT EXISTS (SELECT 1 FROM asientos_contables_detalle ad
                                                WHERE ad.id_asiento = v.id_asiento_contable
                                                  AND ad.id_cuenta_contable IN ($subCosto))
                                )
                          )";

        $this->sincronizarModulo(
            $db,
            $sqlFacturas,
            [$idEmpresa, $idEmpresa, $idEmpresa, $idEmpresa],
            function() {
                return new \App\Services\modulos\FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Facturas de Venta'
        );

        // 2. Liquidaciones de Compra
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM liquidaciones_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado IN ('autorizado', 'contabilizado')",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\LiquidacionCompraService(
                    new \App\repositories\modulos\LiquidacionCompraRepository(),
                    new \App\Rules\modulos\LiquidacionCompraRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Liquidaciones de Compra'
        );

        // 3. Compras (no tiene columna estado)
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM compras_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\ComprasService();
            },
            'Facturas de Compra'
        );

        // 4. Notas de Crédito
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM notas_credito_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado IN ('autorizado', 'contabilizado')",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\NotaCreditoService(
                    new \App\repositories\modulos\NotaCreditoRepository(),
                    new \App\Rules\modulos\NotaCreditoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Notas de Crédito'
        );

        // 5. Retenciones en Ventas (no se autorizan en SRI: solo se filtra por asiento faltante)
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM retencion_venta_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\RetencionVentaService(
                    new \App\repositories\modulos\RetencionVentaRepository(),
                    new \App\Rules\modulos\RetencionVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Retenciones en Ventas'
        );

        // 5b. Retenciones en Compras (emitidas al proveedor; solo se filtra por asiento faltante)
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM retencion_compra_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\RetencionCompraService(
                    new \App\repositories\modulos\RetencionCompraRepository(),
                    new \App\Rules\modulos\RetencionCompraRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Retenciones en Compras'
        );

        // 6. Ingresos (cobros): contrapartida del concepto + formas de cobro
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM ingresos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado <> 'anulado'",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\IngresoService(
                    new \App\repositories\modulos\IngresoRepository(),
                    new \App\Rules\modulos\IngresoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Ingresos',
            'Configuración Contable (Ingresos/Egresos y Cobros/Pagos)'
        );

        // 7. Egresos (pagos): contrapartida del concepto + formas de pago
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM egresos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado <> 'anulado'",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\EgresoService(
                    new \App\repositories\modulos\EgresoRepository(),
                    new \App\Rules\modulos\EgresoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Egresos',
            'Configuración Contable (Ingresos/Egresos y Cobros/Pagos)'
        );

        // 7b. Consignaciones en Ventas (reclasificación de inventario a costo).
        //     Se generan las que tengan las cuentas configuradas; el resto se avisa abajo.
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM consignaciones_ventas WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado <> 'Anulada'",
            [$idEmpresa],
            function() {
                return new \App\Services\modulos\ConsignacionVentaService(
                    new \App\repositories\modulos\ConsignacionVentaRepository(),
                    new \App\Rules\modulos\ConsignacionVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'Consignaciones en Ventas',
            'Configuración Contable (Consignaciones en Ventas)'
        );

        // 8. Verificación proactiva: conceptos y formas SIN cuenta contable configurada
        //    (avisa aunque todavía no existan documentos pendientes).
        $this->verificarConfiguracionCuentas($db, $idEmpresa);

        // 8b. Consignaciones con costo en Kardex que no se pueden contabilizar por falta
        //     de la cuenta «Mercadería en Consignación» configurada.
        $this->verificarConsignacionesPendientes($db, $idEmpresa);

        // 9. Verificación proactiva: facturas con costo en Kardex que no se puede contabilizar
        //    porque faltan las cuentas de Costo de Ventas e Inventario.
        $this->verificarCosteoVentasPendiente($db, $idEmpresa);
    }

    /**
     * Revisa la configuración contable de Ingresos/Egresos y Cobros/Pagos y genera un aviso
     * si hay conceptos (opciones) o formas activas sin cuenta contable asignada.
     */
    private function verificarConfiguracionCuentas(\PDO $db, int $idEmpresa): void
    {
        // Conceptos (opciones de Ingreso/Egreso) activos sin cuenta contable
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM empresa_opciones_ingreso_egreso
                                WHERE id_empresa = ? AND eliminado = false
                                  AND UPPER(estado) = 'ACTIVO' AND id_cuenta_contable IS NULL");
            $st->execute([$idEmpresa]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $this->warnings[] = "Hay {$n} concepto(s) de Ingresos/Egresos sin cuenta contable asignada. Configúrelos en Configuración Contable (tipo de asiento «Ingresos y Egresos»).";
            }
        } catch (\Throwable $e) {
            // Tabla inexistente (migración pendiente): omitir sin romper.
        }

        // Formas de Cobro/Pago activas sin cuenta contable
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM empresa_formas_pago
                                WHERE id_empresa = ? AND eliminado = false
                                  AND activo = true AND id_cuenta_contable IS NULL");
            $st->execute([$idEmpresa]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $this->warnings[] = "Hay {$n} forma(s) de Cobro/Pago sin cuenta contable asignada. Configúrelas en Configuración Contable (tipo de asiento «Cobros y Pagos»).";
            }
        } catch (\Throwable $e) {
            // Tabla inexistente (migración pendiente): omitir sin romper.
        }
    }

    /**
     * Subconsulta que devuelve los id_cuenta configurados para el tipo de asiento de Ventas
     * cuyo asiento_tipo (código o referencia) contiene la palabra clave dada (p. ej. 'COSTO'
     * o 'INVENTARIO'). Replica el cruce de AsientoProgramadoRepository::getReglasGeneralesPorConcepto.
     * Lleva un parámetro posicional (?) que debe enlazarse a id_empresa.
     * La palabra clave se sanitiza a solo letras (se interpola, no es entrada de usuario).
     */
    private function sqlCuentaVentasPorPalabra(string $palabra): string
    {
        $kw = strtoupper(preg_replace('/[^A-Za-z]/', '', $palabra));
        return "SELECT ap.id_cuenta
                FROM asientos_tipo at
                JOIN asientos_programados ap
                  ON ap.id_asiento_tipo = at.id
                 AND ap.id_empresa = ?
                 AND ap.id_referencia = at.id
                 AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
                 AND ap.eliminado = false
                WHERE at.tipo_asiento = 'ventas_factura' AND at.eliminado = false
                  AND ap.id_cuenta IS NOT NULL
                  AND (UPPER(COALESCE(at.codigo, '')) LIKE '%{$kw}%'
                       OR UPPER(COALESCE(at.referencia, '')) LIKE '%{$kw}%')";
    }

    /**
     * Avisa si hay facturas de venta que NECESITAN costeo (vendieron inventario con costo > 0)
     * pero cuyo costo no se puede contabilizar porque faltan las cuentas de Costo de Ventas
     * y/o Inventario en la configuración del tipo de asiento de Ventas. Esas facturas no se
     * reprocesan (se evita reproceso en cada carga); solo se cuentan para avisar al usuario.
     */
    private function verificarCosteoVentasPendiente(\PDO $db, int $idEmpresa): void
    {
        try {
            $subCosto      = $this->sqlCuentaVentasPorPalabra('COSTO');
            $subInventario = $this->sqlCuentaVentasPorPalabra('INVENTARIO');

            $sql = "SELECT COUNT(*)
                    FROM ventas_cabecera v
                    WHERE v.id_empresa = ?
                      AND v.eliminado = false
                      AND v.estado IN ('autorizado', 'contabilizado')
                      AND v.id_asiento_contable IS NOT NULL
                      AND EXISTS (SELECT 1 FROM inventario_kardex k
                                  WHERE k.referencia_tipo = 'factura_venta'
                                    AND k.referencia_id   = v.id
                                    AND k.tipo_movimiento = 'salida'
                                    AND k.eliminado       = false
                                    AND k.costo_total      > 0)
                      AND NOT (EXISTS ($subCosto) AND EXISTS ($subInventario))";
            $st = $db->prepare($sql);
            $st->execute([$idEmpresa, $idEmpresa, $idEmpresa]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $this->warnings[] = "Hay {$n} factura(s) de venta con productos cuyo costo de venta no se está contabilizando. "
                    . "Configure las cuentas de Costo de Ventas e Inventario en Configuración Contable (tipo de asiento de Ventas); "
                    . "al volver a abrir Estados Financieros, los asientos se completarán automáticamente.";
            }
        } catch (\Throwable $e) {
            // Tabla/columna inexistente (migración pendiente): omitir sin romper.
        }
    }

    /**
     * Subconsulta que devuelve los id_cuenta configurados para el concepto de Consignaciones
     * cuyo asiento_tipo (código o referencia) contiene la palabra clave dada (p. ej. 'CONSIGNACION').
     * Lleva un parámetro posicional (?) que debe enlazarse a id_empresa.
     */
    private function sqlCuentaConsignacionPorPalabra(string $palabra): string
    {
        $kw = strtoupper(preg_replace('/[^A-Za-z]/', '', $palabra));
        return "SELECT ap.id_cuenta
                FROM asientos_tipo at
                JOIN asientos_programados ap
                  ON ap.id_asiento_tipo = at.id
                 AND ap.id_empresa = ?
                 AND ap.id_referencia = at.id
                 AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
                 AND ap.eliminado = false
                WHERE at.tipo_asiento = 'consignacion_venta' AND at.eliminado = false
                  AND ap.id_cuenta IS NOT NULL
                  AND (UPPER(COALESCE(at.codigo, '')) LIKE '%{$kw}%'
                       OR UPPER(COALESCE(at.referencia, '')) LIKE '%{$kw}%')";
    }

    /**
     * Avisa si hay consignaciones en venta con costo en el Kardex (costo_total > 0) cuyo asiento
     * de reclasificación no se puede generar porque falta configurar la cuenta «Mercadería en
     * Consignación» en el tipo de asiento «Consignaciones en Ventas». No se reprocesan aquí:
     * solo se cuentan para avisar. Al configurar la cuenta se generarán automáticamente.
     */
    private function verificarConsignacionesPendientes(\PDO $db, int $idEmpresa): void
    {
        try {
            $subMercaderia = $this->sqlCuentaConsignacionPorPalabra('CONSIGNACION');

            $sql = "SELECT COUNT(*)
                    FROM consignaciones_ventas cv
                    WHERE cv.id_empresa = ?
                      AND cv.eliminado = false
                      AND cv.estado <> 'Anulada'
                      AND cv.id_asiento_contable IS NULL
                      AND EXISTS (SELECT 1 FROM inventario_kardex k
                                  WHERE k.referencia_tipo = 'CONSIGNACION_VENTA'
                                    AND k.referencia_id   = cv.id
                                    AND k.tipo_movimiento = 'salida'
                                    AND k.eliminado       = false
                                    AND k.costo_total      > 0)
                      AND NOT EXISTS ($subMercaderia)";
            $st = $db->prepare($sql);
            $st->execute([$idEmpresa, $idEmpresa]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $this->warnings[] = "Hay {$n} consignación(es) en venta sin asiento contable. "
                    . "Configure la cuenta «Mercadería en Consignación» (y su contrapartida de Inventario) en "
                    . "Configuración Contable (tipo de asiento «Consignaciones en Ventas»); al volver a abrir "
                    . "Estados Financieros, los asientos se generarán automáticamente.";
            }
        } catch (\Throwable $e) {
            // Tabla/columna inexistente (migración pendiente): omitir sin romper.
        }
    }

    private function sincronizarModulo(\PDO $db, string $sql, array $params, callable $serviceFactory, string $nombreModulo, string $dondeConfigurar = 'Asientos Programados'): void
    {
        try {
            $st = $db->prepare($sql);
            $st->execute($params);
            $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            // Tabla o columna inexistente (p. ej. migración pendiente en producción):
            // se omite el módulo sin romper la carga de Estados Financieros / Asientos.
            $this->warnings[] = "No se pudo verificar asientos pendientes en $nombreModulo (revise la migración de la base de datos).";
            return;
        }

        if (empty($ids)) {
            return;
        }

        $service = $serviceFactory();

        if (!method_exists($service, 'procesarAsientoContablePorSincronizacion')) {
            return;
        }

        $errorCount = 0;

        foreach ($ids as $id) {
            try {
                $service->procesarAsientoContablePorSincronizacion((int)$id);
                $this->generados++;
            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        if ($errorCount > 0) {
            $this->warnings[] = "Faltan $errorCount asientos contables por generar en $nombreModulo. Configure las cuentas correspondientes en $dondeConfigurar.";
        }
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /** Cantidad de asientos efectivamente generados en la última corrida de sincronizar(). */
    public function getGenerados(): int
    {
        return $this->generados;
    }
}
