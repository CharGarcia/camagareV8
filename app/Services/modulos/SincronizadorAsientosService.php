<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;

class SincronizadorAsientosService
{
    private array $warnings = [];

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
            $db->exec("ALTER TABLE ingresos_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
            $db->exec("ALTER TABLE egresos_cabecera ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;");
        } catch (\Throwable $e) {
            // Ignorar errores si no tiene permisos o ya existen
        }

        // 1. Facturas de Venta
        $this->sincronizarModulo(
            $db,
            "SELECT id FROM ventas_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado IN ('autorizado', 'contabilizado')",
            [$idEmpresa],
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

        // 8. Verificación proactiva: conceptos y formas SIN cuenta contable configurada
        //    (avisa aunque todavía no existan documentos pendientes).
        $this->verificarConfiguracionCuentas($db, $idEmpresa);
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
}
