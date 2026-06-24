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
