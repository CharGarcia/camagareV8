<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;

class SincronizadorAsientosService
{
    private array $warnings = [];
    /** Notas informativas (no son errores): explican comportamientos intencionales. */
    private int $generados = 0;
    /**
     * Resumen corto de documentos con problema, agrupado por módulo: ['Facturas de Venta' => 20, ...].
     * Es lo que se muestra por defecto al usuario (ej. "Hay 20 asiento(s) por generar en Facturas de
     * Venta"); el detalle motivo-por-motivo (qué cuenta falta, qué documentos) se guarda aparte en
     * $detalle para que la UI lo pueda mostrar bajo un "Ver detalle" sin volver el mensaje largo.
     */
    private array $resumenPorModulo = [];
    /** Detalle motivo-por-motivo (mismo contenido que antes iba directo a $warnings). */
    private array $detalle = [];

    public function sincronizar(int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $this->prepararEsquema($db);

        $excMig = $this->construirExclusionMigracion($db);
        $trabajos = $this->construirTrabajos($idEmpresa, $excMig);

        foreach ($trabajos as $t) {
            $this->sincronizarModulo(
                $db,
                $t['sql'],
                $t['params'],
                $t['factory'],
                $t['nombre'],
                $t['dondeConfigurar'],
                $t['tablaVerif'],
                $t['colAsiento'],
                $t['colsDoc'] ?? []
            );
        }

        // Verificación proactiva: conceptos y formas SIN cuenta contable configurada
        // (avisa aunque todavía no existan documentos pendientes).
        $this->verificarConfiguracionCuentas($db, $idEmpresa);

        // Consignaciones con costo en Kardex que no se pueden contabilizar por falta
        // de la cuenta «Mercadería en Consignación» configurada.
        $this->verificarConsignacionesPendientes($db, $idEmpresa);

        // Facturas con costo en Kardex que no se puede contabilizar porque faltan las
        // cuentas de Costo de Ventas e Inventario.
        $this->verificarCosteoVentasPendiente($db, $idEmpresa);
    }

    /**
     * Total de "pasos" en los que se puede dividir sincronizar(): uno por cada trabajo (módulo)
     * más las 3 verificaciones fijas del final. Lo usa la UI para calcular el % de la barra de
     * progreso — debe coincidir exactamente con lo que recorre ejecutarPaso().
     */
    public function contarPasos(int $idEmpresa): int
    {
        $db = Database::getConnection();
        $excMig = $this->construirExclusionMigracion($db);
        return count($this->construirTrabajos($idEmpresa, $excMig)) + 3;
    }

    /**
     * Ejecuta UN solo paso de sincronizar() (0-indexado) y devuelve lo generado/avisado en ESE
     * paso — no acumula entre llamadas. Existe para que la UI pueda mostrar una barra de progreso
     * real y permitir cancelar entre pasos: cada llamada HTTP procesa un módulo (o una
     * verificación) y vuelve, en vez de bloquear hasta terminar todo de una vez como sincronizar().
     * El llamador (JS) es quien acumula generados/warnings/detalle de cada paso y arma el resumen
     * final — ver public/js/modulos/asientos_pendientes.js.
     */
    public function ejecutarPaso(int $idEmpresa, int $idUsuario, int $paso): array
    {
        $db = Database::getConnection();
        $this->prepararEsquema($db);
        $excMig = $this->construirExclusionMigracion($db);
        $trabajos = $this->construirTrabajos($idEmpresa, $excMig);
        $totalPasos = count($trabajos) + 3;

        $nombrePaso = null;
        if ($paso >= 0 && $paso < count($trabajos)) {
            $t = $trabajos[$paso];
            $nombrePaso = $t['nombre'];
            $this->sincronizarModulo(
                $db, $t['sql'], $t['params'], $t['factory'], $t['nombre'],
                $t['dondeConfigurar'], $t['tablaVerif'], $t['colAsiento'], $t['colsDoc'] ?? []
            );
        } elseif ($paso === count($trabajos)) {
            $nombrePaso = 'Configuración de cuentas (Ingresos/Egresos, Cobros/Pagos)';
            $this->verificarConfiguracionCuentas($db, $idEmpresa);
        } elseif ($paso === count($trabajos) + 1) {
            $nombrePaso = 'Consignaciones en Ventas pendientes';
            $this->verificarConsignacionesPendientes($db, $idEmpresa);
        } elseif ($paso === count($trabajos) + 2) {
            $nombrePaso = 'Costeo de Ventas pendiente';
            $this->verificarCosteoVentasPendiente($db, $idEmpresa);
        }

        return [
            'paso'             => $paso,
            'totalPasos'       => $totalPasos,
            'nombrePaso'       => $nombrePaso,
            'terminado'        => $paso >= $totalPasos - 1,
            'generados'        => $this->generados,
            'warnings'         => $this->warnings,
            'detalle'          => $this->detalle,
            'resumenPorModulo' => $this->resumenPorModulo,
        ];
    }

    /**
     * Cuenta cuántos documentos operativos están pendientes de generar su asiento contable,
     * SIN generarlos. Reutiliza exactamente las mismas consultas de detección que sincronizar()
     * (envolviéndolas en un COUNT), para que la cifra mostrada al usuario coincida con lo que
     * realmente se intentará generar. Los módulos cuya tabla/columna aún no exista (migración
     * pendiente) se omiten silenciosamente.
     */
    public function contarPendientes(int $idEmpresa): int
    {
        $db = Database::getConnection();
        $this->prepararEsquema($db);

        $excMig = $this->construirExclusionMigracion($db);
        $trabajos = $this->construirTrabajos($idEmpresa, $excMig);

        $total = 0;
        foreach ($trabajos as $t) {
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM (" . $t['sql'] . ") AS _pend");
                $st->execute($t['params']);
                $total += (int) $st->fetchColumn();
            } catch (\Throwable $e) {
                // Tabla o columna inexistente (migración pendiente): se omite sin romper.
            }
        }

        return $total;
    }

    /**
     * Asegura que la columna id_asiento_contable exista en todas las tablas operativas
     * antes de realizar cualquier consulta SELECT sobre ellas.
     */
    private function prepararEsquema(\PDO $db): void
    {
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
    }

    /**
     * Devuelve un closure que genera el fragmento SQL para excluir los documentos INSERTADOS
     * por la migración del sistema viejo (o '' si no aplica). Su contabilidad viene del histórico
     * migrado (modulo_origen='migracion'), así que NO deben generar asiento automático.
     * Se excluyen solo los que la migración creó (vinculado IS NOT TRUE); los 'vinculado'=true
     * son documentos NATIVOS que la migración solo enlazó por número SRI, así que deben seguir
     * generando su asiento normalmente.
     */
    private function construirExclusionMigracion(\PDO $db): callable
    {
        $tieneMapMig = false;
        try {
            $tieneMapMig = (bool) $db->query("SELECT to_regclass('public.migracion_mysql_map')")->fetchColumn();
        } catch (\Throwable $e) {
            $tieneMapMig = false;
        }

        // $entidad e $idExpr son literales del código (no entrada de usuario) → seguros de interpolar.
        return function (string $entidad, string $idExpr) use ($tieneMapMig): string {
            if (!$tieneMapMig) { return ''; }
            return " AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map mm WHERE mm.entidad = '{$entidad}' AND mm.id_destino = {$idExpr} AND mm.vinculado IS NOT TRUE) ";
        };
    }

    /**
     * Construye la lista de "trabajos" de sincronización: cada entrada describe la consulta que
     * detecta los documentos pendientes de un módulo y cómo generar su asiento. La usan tanto
     * sincronizar() (para generar) como contarPendientes() (para solo contar), garantizando que
     * ambos miren exactamente los mismos documentos.
     *
     * @return array<int, array{sql:string, params:array, factory:callable, nombre:string, dondeConfigurar:string, tablaVerif:?string, colAsiento:string}>
     */
    private function construirTrabajos(int $idEmpresa, callable $excMig): array
    {
        $trabajos = [];

        // 1. Facturas de Venta
        //    Se (re)generan tres grupos:
        //    (a) las que no tienen ningún asiento todavía,
        //    (b) las que YA tienen asiento pero ventas_costeo_seguimiento dice explícitamente
        //        que el bloque de costo sigue pendiente (requiere_costo=true, costo_generado=false).
        //        Esa tabla la escribe AsientoBuilderService con el resultado REAL de la cascada
        //        completa (General/Cliente/Producto/Categoría/Marca) cada vez que arma el asiento —
        //        a diferencia de la heurística anterior, que solo veía la cuenta General y quedaba
        //        ciega a cuentas configuradas por Categoría/Producto/Marca (ver
        //        database/ventas_costeo_seguimiento.sql), y
        //    (c) las que YA tienen asiento pero todavía NO tienen fila en ventas_costeo_seguimiento
        //        (documentos generados antes de que esta tabla existiera). Se reprocesan una vez
        //        para que AsientoBuilderService resuelva su costo real y quede registrado — mismo
        //        mecanismo de "cuenta pendientes → el usuario confirma → genera" que ya usa el resto
        //        del sistema (Compras, Liquidaciones, etc.), acotado a la empresa activa.
        $sqlFacturas = "SELECT v.id
                        FROM ventas_cabecera v
                        WHERE v.id_empresa = ?
                          AND v.eliminado = false
                          AND v.estado IN ('autorizado', 'contabilizado')
                          AND (
                                v.id_asiento_contable IS NULL
                             OR NOT EXISTS (
                                    SELECT 1 FROM ventas_costeo_seguimiento cs
                                    WHERE cs.id_empresa = v.id_empresa
                                      AND cs.tipo_documento = 'factura_venta'
                                      AND cs.id_documento = v.id
                                      AND cs.eliminado = false
                                )
                             OR EXISTS (
                                    SELECT 1 FROM ventas_costeo_seguimiento cs
                                    WHERE cs.id_empresa = v.id_empresa
                                      AND cs.tipo_documento = 'factura_venta'
                                      AND cs.id_documento = v.id
                                      AND cs.eliminado = false
                                      AND cs.requiere_costo = true
                                      AND cs.costo_generado = false
                                )
                          )" . $excMig('facturas', 'v.id');

        $trabajos[] = [
            'sql'    => $sqlFacturas,
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Facturas de Venta',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'ventas_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 1b. Recibos de Venta (espejo de la factura, reusa el concepto 'ventas_factura').
        //     Mismo criterio que la factura: solo se contabiliza el documento vigente ya emitido,
        //     nunca el borrador. Estados: borrador → emitido → facturado/anulado.
        //     Se excluyen además 'facturado' y 'anulado' porque su asiento se anula al facturar
        //     (ahí manda el de la factura, para no duplicar la venta) y al anular/eliminar.
        // Igual que Facturas: se suman las ramas (b) "costo pendiente según ventas_costeo_seguimiento"
        // y (c) "sin fila todavía" (histórico anterior a esta tabla) — antes los Recibos no tenían
        // ninguna de las dos.
        $sqlRecibos = "SELECT r.id
                       FROM recibos_venta_cabecera r
                       WHERE r.id_empresa = ? AND r.eliminado = false AND r.estado = 'emitido'
                         AND (
                               r.id_asiento_contable IS NULL
                            OR NOT EXISTS (
                                   SELECT 1 FROM ventas_costeo_seguimiento cs
                                   WHERE cs.id_empresa = r.id_empresa
                                     AND cs.tipo_documento = 'recibo_venta'
                                     AND cs.id_documento = r.id
                                     AND cs.eliminado = false
                               )
                            OR EXISTS (
                                   SELECT 1 FROM ventas_costeo_seguimiento cs
                                   WHERE cs.id_empresa = r.id_empresa
                                     AND cs.tipo_documento = 'recibo_venta'
                                     AND cs.id_documento = r.id
                                     AND cs.eliminado = false
                                     AND cs.requiere_costo = true
                                     AND cs.costo_generado = false
                               )
                         )" . $excMig('recibos', 'r.id');
        $trabajos[] = [
            'sql'    => $sqlRecibos,
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ReciboVentaService(
                    new \App\repositories\modulos\ReciboVentaRepository(),
                    new \App\Rules\modulos\ReciboVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Recibos de Venta',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'recibos_venta_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 2. Liquidaciones de Compra
        $trabajos[] = [
            'sql'    => "SELECT id FROM liquidaciones_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado IN ('autorizado', 'contabilizado')" . $excMig('liquidaciones', 'liquidaciones_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\LiquidacionCompraService(
                    new \App\repositories\modulos\LiquidacionCompraRepository(),
                    new \App\Rules\modulos\LiquidacionCompraRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Liquidaciones de Compra',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'liquidaciones_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 3. Compras (no tiene columna estado)
        $trabajos[] = [
            'sql'    => "SELECT id FROM compras_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL" . $excMig('compras', 'compras_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ComprasService();
            },
            'nombre' => 'Facturas de Compra',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'compras_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento_prov', 'punto_emision_prov', 'secuencial_prov'],
        ];

        // 4. Notas de Crédito — mismas ramas (b) y (c) que Facturas/Recibos.
        $sqlNotasCredito = "SELECT n.id
                            FROM notas_credito_cabecera n
                            WHERE n.id_empresa = ? AND n.eliminado = false
                              AND n.estado IN ('autorizado', 'contabilizado')
                              AND (
                                    n.id_asiento_contable IS NULL
                                 OR NOT EXISTS (
                                        SELECT 1 FROM ventas_costeo_seguimiento cs
                                        WHERE cs.id_empresa = n.id_empresa
                                          AND cs.tipo_documento = 'nota_credito_venta'
                                          AND cs.id_documento = n.id
                                          AND cs.eliminado = false
                                    )
                                 OR EXISTS (
                                        SELECT 1 FROM ventas_costeo_seguimiento cs
                                        WHERE cs.id_empresa = n.id_empresa
                                          AND cs.tipo_documento = 'nota_credito_venta'
                                          AND cs.id_documento = n.id
                                          AND cs.eliminado = false
                                          AND cs.requiere_costo = true
                                          AND cs.costo_generado = false
                                    )
                              )" . $excMig('notas_credito', 'n.id');
        $trabajos[] = [
            'sql'    => $sqlNotasCredito,
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\NotaCreditoService(
                    new \App\repositories\modulos\NotaCreditoRepository(),
                    new \App\Rules\modulos\NotaCreditoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Notas de Crédito',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'notas_credito_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 5. Retenciones en Ventas (no se autorizan en SRI: solo se filtra por asiento faltante)
        $trabajos[] = [
            'sql'    => "SELECT id FROM retencion_venta_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL" . $excMig('retenciones_venta', 'retencion_venta_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\RetencionVentaService(
                    new \App\repositories\modulos\RetencionVentaRepository(),
                    new \App\Rules\modulos\RetencionVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Retenciones en Ventas',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'retencion_venta_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 5b. Retenciones en Compras: mismo criterio que facturas/NC/liquidaciones, solo se
        //     contabiliza el documento vigente ya autorizado por el SRI, nunca el borrador.
        $trabajos[] = [
            'sql'    => "SELECT id FROM retencion_compra_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado = 'autorizada'" . $excMig('retenciones_compra', 'retencion_compra_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\RetencionCompraService(
                    new \App\repositories\modulos\RetencionCompraRepository(),
                    new \App\Rules\modulos\RetencionCompraRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Retenciones en Compras',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'retencion_compra_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 6. Ingresos (cobros): contrapartida del concepto + formas de cobro
        $trabajos[] = [
            'sql'    => "SELECT id FROM ingresos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado <> 'anulado'" . $excMig('ingresos', 'ingresos_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\IngresoService(
                    new \App\repositories\modulos\IngresoRepository(),
                    new \App\Rules\modulos\IngresoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Ingresos',
            'dondeConfigurar' => 'Configuración Contable (Ingresos/Egresos y Cobros/Pagos)',
            'tablaVerif' => 'ingresos_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['numero_ingreso'],
        ];

        // 7. Egresos (pagos): contrapartida del concepto + formas de pago. Los que pagan
        //    un rol MENSUAL SÍ generan su propio asiento (a partir de este cambio):
        //    cancelan la cuenta "Sueldos por Pagar" que RolAsientoService acreditó al
        //    contabilizar el rol (base devengado) — ver
        //    AsientoBuilderService::generarAsientoEgreso(). Ya no se duplica el gasto
        //    porque el rol ya no acredita Bancos directamente, solo el pasivo.
        $trabajos[] = [
            'sql'    => "SELECT id FROM egresos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado <> 'anulado'" . $excMig('egresos', 'egresos_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\EgresoService(
                    new \App\repositories\modulos\EgresoRepository(),
                    new \App\Rules\modulos\EgresoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Egresos',
            'dondeConfigurar' => 'Configuración Contable (Ingresos/Egresos y Cobros/Pagos)',
            'tablaVerif' => 'egresos_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['numero_egreso'],
        ];

        // 7b. Consignaciones en Ventas (reclasificación de inventario a costo).
        //     Se generan las que tengan las cuentas configuradas; el resto se avisa abajo.
        $trabajos[] = [
            'sql'    => "SELECT id FROM consignaciones_ventas WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado <> 'Anulada'" . $excMig('consignaciones', 'consignaciones_ventas.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ConsignacionVentaService(
                    new \App\repositories\modulos\ConsignacionVentaRepository(),
                    new \App\Rules\modulos\ConsignacionVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Consignaciones en Ventas',
            'dondeConfigurar' => 'Configuración Contable (Consignaciones en Ventas)',
            'tablaVerif' => 'consignaciones_ventas',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['serie', 'secuencial'],
        ];

        // 7c. Retornos de Consignaciones en Ventas (devolución del cliente: entrada de inventario).
        //     Solo los 'Emitida' tienen impacto contable (Borrador/Anulada no se contabilizan).
        $trabajos[] = [
            'sql'    => "SELECT id FROM retornos_cv WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado = 'Emitida'" . $excMig('retornos_cv', 'retornos_cv.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\RetornoCvService(
                    new \App\repositories\modulos\RetornoCvRepository(),
                    new \App\Rules\modulos\RetornoCvRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Retornos de Consignaciones',
            'dondeConfigurar' => 'Configuración Contable (Retornos de Consignaciones)',
            'tablaVerif' => 'retornos_cv',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['serie', 'secuencial'],
        ];

        // 7c-bis. Cambios de productos (devuelve/entrega): asiento a costo del inventario movido.
        //         Solo los 'Emitida' tienen impacto contable (Borrador/Anulada no se contabilizan).
        $trabajos[] = [
            'sql'    => "SELECT id FROM cambios_producto_cv WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado = 'Emitida'",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\CambioProductoCvService(
                    new \App\repositories\modulos\CambioProductoCvRepository(),
                    new \App\Rules\modulos\CambioProductoCvRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Cambios de Productos',
            'dondeConfigurar' => 'Configuración Contable (Cambios de productos)',
            'tablaVerif' => 'cambios_producto_cv',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['serie', 'secuencial'],
        ];

        // 7d. Facturación de Consignaciones (asiento INVERSO del reingreso de inventario).
        //     Solo las ya 'facturada' lo tienen; el enlace es id_asiento_reingreso (no id_asiento_contable).
        $trabajos[] = [
            'sql'    => "SELECT id FROM consignaciones_facturas WHERE id_empresa = ? AND eliminado = false AND id_asiento_reingreso IS NULL AND estado = 'facturada'",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ConsignacionFacturaService(
                    new \App\repositories\modulos\ConsignacionFacturaRepository(),
                    new \App\Rules\modulos\ConsignacionFacturaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Facturación de Consignaciones',
            'dondeConfigurar' => 'Configuración Contable (Consignaciones en Ventas)',
            'tablaVerif' => 'consignaciones_facturas',
            'colAsiento' => 'id_asiento_reingreso',
            'colsDoc' => ['numero_factura'],
        ];

        // 8. Roles de Pago (Nómina): solo el rol MENSUAL contabiliza (las quincenas/
        //    semanas se netean en el mensual, ver RolCalculoService). Base DEVENGADO:
        //    se contabiliza en cuanto está 'generado' (no espera a que se pague) — y
        //    se REGENERA (edita el mismo asiento, no lo duplica) cada vez que el rol
        //    se recalcula después de contabilizado y queda más nuevo que su asiento
        //    (rol_cabecera.updated_at > asiento.updated_at). 'anulado' queda fuera.
        $trabajos[] = [
            'sql'    => "SELECT rc2.id FROM rol_cabecera rc2
                         LEFT JOIN asientos_contables_cabecera ac2 ON ac2.id = rc2.id_asiento
                         WHERE rc2.id_empresa = ? AND rc2.eliminado = false
                           AND rc2.tipo_rol = 'MENSUAL' AND rc2.estado IN ('generado', 'pagado', 'contabilizado')
                           AND (rc2.id_asiento IS NULL OR ac2.updated_at < rc2.updated_at)",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\RolAsientoService(
                    new \App\repositories\modulos\RolPagoRepository(),
                    new \App\Services\LogSistemaService()
                );
            },
            'nombre' => 'Roles de Pago',
            'dondeConfigurar' => 'Asientos Programados (tipo «Nómina»)',
            'tablaVerif' => 'rol_cabecera',
            'colAsiento' => 'id_asiento',
            'colsDoc' => ['periodo_mes', 'periodo_anio'],
        ];

        // 9. Importaciones (nacionalizadas/cerradas): registra crédito de IVA/ISD e inventario
        //    nacionalizado. 'borrador'/'registrada' no generan asiento (no tocan inventario
        //    todavía), por eso se filtran aquí igual que ImportacionesService::procesarInventario().
        $trabajos[] = [
            'sql'    => "SELECT id FROM importaciones_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado IN ('nacionalizada', 'cerrada')" . $excMig('importaciones', 'importaciones_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ImportacionesService();
            },
            'nombre' => 'Importaciones',
            'dondeConfigurar' => 'Configuración Contable (tipo de asiento «Importaciones»)',
            'tablaVerif' => 'importaciones_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        return $trabajos;
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
     * Avisa, documento por documento y con el MOTIVO real, cuándo el costo de venta sigue
     * pendiente — leyendo ventas_costeo_seguimiento (escrita por AsientoBuilderService con el
     * resultado real de la cascada completa) en vez de reconstruir la heurística de cuentas.
     */
    private function verificarCosteoVentasPendiente(\PDO $db, int $idEmpresa): void
    {
        try {
            $sql = "SELECT tipo_documento, motivo_pendiente, COUNT(*) AS total
                    FROM ventas_costeo_seguimiento
                    WHERE id_empresa = ? AND eliminado = false
                      AND requiere_costo = true AND costo_generado = false
                    GROUP BY tipo_documento, motivo_pendiente
                    ORDER BY tipo_documento";
            $st = $db->prepare($sql);
            $st->execute([$idEmpresa]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) {
                return;
            }

            $nombres = [
                'factura_venta'      => 'factura(s) de venta',
                'recibo_venta'       => 'recibo(s) de venta',
                'nota_credito_venta' => 'nota(s) de crédito',
            ];
            $motivos = [
                'cuenta_no_configurada'         => 'falta configurar la cuenta de Costo de Ventas y/o Inventario (a nivel General, o por Cliente/Producto/Categoría/Marca)',
                'bloque_incompleto_descuadrado' => 'el bloque de costo quedó descuadrado (revise que la cuenta de Costo y la de Inventario resuelvan el mismo monto)',
            ];

            foreach ($rows as $row) {
                $tipo   = $nombres[$row['tipo_documento']] ?? $row['tipo_documento'];
                $motivo = $motivos[$row['motivo_pendiente']] ?? 'revise la configuración de cuentas';
                $this->warnings[] = "Hay {$row['total']} {$tipo} con costo de venta sin contabilizar: {$motivo}. "
                    . "Al corregirlo, se completará automáticamente la próxima vez que abra esta pantalla.";
            }
        } catch (\Throwable $e) {
            // Tabla inexistente (migración pendiente): omitir sin romper.
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

    /**
     * @param string|null $tablaVerif Tabla cabecera del módulo. Si se indica, tras intentar generar
     *                                se comprueba DOCUMENTO POR DOCUMENTO que realmente haya quedado
     *                                con asiento. Necesario porque varios services retornan en
     *                                silencio (sin excepción) cuando el asiento queda vacío, y sin
     *                                esto se contarían como generados.
     * @param string $colAsiento      Columna que enlaza el documento con su asiento. Casi todos usan
     *                                'id_asiento_contable'; Facturación CV usa 'id_asiento_reingreso'.
     * @param array  $colsDoc         Columna(s) de $tablaVerif que forman el número de documento visible
     *                                al usuario (ej. ['establecimiento','punto_emision','secuencial']),
     *                                para mostrar "001-001-000000123" en los avisos en vez del id interno.
     *                                Vacío = se muestra "#id" (no hay columna de número conocida).
     */
    private function sincronizarModulo(\PDO $db, string $sql, array $params, callable $serviceFactory, string $nombreModulo, string $dondeConfigurar = 'Asientos Programados', ?string $tablaVerif = null, string $colAsiento = 'id_asiento_contable', array $colsDoc = []): void
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

        // Se agrupan los fallos por MOTIVO real (mensaje de la excepción) para que el aviso diga
        // qué corregir. Antes se descartaba el mensaje y siempre se culpaba a las cuentas, lo que
        // ocultaba errores reales (p. ej. de BD) detrás de un "Configure las cuentas".
        $errores    = [];
        $intentados = [];

        foreach ($ids as $id) {
            try {
                $service->procesarAsientoContablePorSincronizacion((int)$id);
                $intentados[] = (int) $id;
            } catch (\Throwable $e) {
                $motivo = trim($e->getMessage());
                if ($motivo === '') {
                    $motivo = 'Error no especificado (' . get_class($e) . ')';
                }
                $errores[$motivo][] = (int) $id;
            }
        }

        // Comprobación DOCUMENTO POR DOCUMENTO: varios services retornan en silencio (sin excepción)
        // cuando el asiento queda vacío —p. ej. sin reglas/cuentas configuradas el builder devuelve []
        // y FacturaVentaService hace `if (empty($detalles)) return;`—. Sin esto, esos documentos se
        // contarían como generados y el usuario no vería ningún aviso pese a quedarse sin asiento.
        $sinAsiento = [];
        if ($tablaVerif !== null && !empty($intentados)) {
            try {
                $in  = implode(',', array_map('intval', $intentados));
                $stV = $db->query("SELECT id FROM {$tablaVerif} WHERE id IN ({$in}) AND {$colAsiento} IS NULL");
                $sinAsiento = array_map('intval', $stV->fetchAll(\PDO::FETCH_COLUMN));
            } catch (\Throwable $e) {
                // Sin tabla/columna para verificar: se omite la comprobación sin romper la carga.
            }
        }

        $this->generados += count($intentados) - count($sinAsiento);

        // Resuelve el número de documento (serie-secuencial) de todos los ids que van a aparecer
        // en algún aviso, para mostrar "001-001-000000123" en vez de un id interno sin significado.
        $idsParaNumero = $sinAsiento;
        foreach ($errores as $idsFallidos) {
            $idsParaNumero = array_merge($idsParaNumero, $idsFallidos);
        }
        $idsParaNumero = array_unique($idsParaNumero);
        $numeros = ($tablaVerif !== null && !empty($colsDoc))
            ? $this->resolverNumerosDocumento($db, $tablaVerif, $colsDoc, $idsParaNumero)
            : [];

        // El aviso corto ("Hay 20 en Facturas de Venta") va en $resumenPorModulo; el motivo real
        // (qué cuenta falta) y los documentos afectados quedan en $detalle, para un "Ver detalle"
        // en la UI — y también al log del servidor por si hace falta revisar fuera del navegador.
        $totalConProblema = 0;
        foreach ($errores as $motivo => $idsFallidos) {
            $n = count($idsFallidos);
            $totalConProblema += $n;
            $docs = $this->listarDocumentos($idsFallidos, $numeros);
            $this->detalle[] = "{$nombreModulo} — {$n} asiento(s): {$motivo} (documento(s): {$docs})";
            error_log("[SincronizadorAsientos] {$nombreModulo}: {$n} fallo(s) — {$motivo} — docs: {$docs} — ids: " . implode(',', $idsFallidos));
        }

        if (!empty($sinAsiento)) {
            $n = count($sinAsiento);
            $totalConProblema += $n;
            $docs = $this->listarDocumentos($sinAsiento, $numeros);
            $this->detalle[] = "{$nombreModulo} — {$n} documento(s) sin asiento (documento(s): {$docs})";
            error_log("[SincronizadorAsientos] {$nombreModulo}: {$n} documento(s) siguen sin asiento — docs: {$docs} — ids: " . implode(',', $sinAsiento));
        }

        if ($totalConProblema > 0) {
            $this->resumenPorModulo[$nombreModulo] = ($this->resumenPorModulo[$nombreModulo] ?? 0) + $totalConProblema;
        }
    }

    /**
     * Lee, para un lote de ids, el número de documento visible al usuario (serie-secuencial),
     * concatenando las columnas indicadas (ej. establecimiento-punto_emision-secuencial). Las
     * partes vacías se omiten; si ninguna columna tiene valor, el id se resuelve como "" (el
     * llamador cae a "#id"). Falla en silencio (devuelve []) si la tabla/columna no existe.
     */
    private function resolverNumerosDocumento(\PDO $db, string $tabla, array $cols, array $ids): array
    {
        if (empty($ids)) return [];
        // $tabla y $cols son literales del propio código (no entrada de usuario) → seguros de interpolar.
        $colsSql = implode(', ', $cols);
        $in = implode(',', array_map('intval', $ids));
        try {
            $st = $db->query("SELECT id, {$colsSql} FROM {$tabla} WHERE id IN ({$in})");
            $out = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $partes = [];
                foreach ($cols as $c) {
                    $v = trim((string)($row[$c] ?? ''));
                    if ($v !== '') $partes[] = $v;
                }
                $out[(int)$row['id']] = implode('-', $partes);
            }
            return $out;
        } catch (\Throwable $e) {
            // Columna inexistente (migración pendiente) u otro error: se omite sin romper el aviso.
            return [];
        }
    }

    /** Formatea una lista de ids para los avisos, mostrando el número de documento si se conoce
     *  (ej. "001-001-000000123, 001-001-000000456 y 9 más") y cayendo a "#id" si no. */
    private function listarDocumentos(array $ids, array $numeros, int $max = 5): string
    {
        $muestra = array_slice($ids, 0, $max);
        $etiquetas = array_map(function ($id) use ($numeros) {
            $num = trim((string)($numeros[$id] ?? ''));
            return $num !== '' ? $num : ('#' . $id);
        }, $muestra);
        $txt   = implode(', ', $etiquetas);
        $resto = count($ids) - count($muestra);
        return $resto > 0 ? $txt . ' y ' . $resto . ' más' : $txt;
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

    /** Resumen corto por módulo: ['Facturas de Venta' => 20, 'Facturas de Compra' => 3, ...]. */
    public function getResumenPorModulo(): array
    {
        return $this->resumenPorModulo;
    }

    /**
     * Detalle real de cada motivo (qué cuenta falta, qué documentos), uno por línea. La UI lo
     * muestra bajo un "Ver detalle" oculto por defecto, para no alargar el mensaje principal.
     */
    public function getDetalle(): array
    {
        return $this->detalle;
    }

    /**
     * Arma el mensaje corto de una sola línea que ve el usuario por defecto (ej. "Hay asiento(s) por
     * generar: 20 en Facturas de Venta, 3 en Facturas de Compra. Revise la configuración contable.").
     * El detalle motivo-por-motivo/documento-por-documento está en getDetalle() (y en el log del
     * servidor) para quien necesite profundizar (soporte, Auditoría Contable).
     */
    public function getResumenMensaje(): ?string
    {
        if (empty($this->resumenPorModulo)) {
            return null;
        }
        $partes = [];
        foreach ($this->resumenPorModulo as $modulo => $n) {
            $partes[] = "{$n} en {$modulo}";
        }
        return 'Hay asiento(s) por generar: ' . implode(', ', $partes) . '. Revise la configuración contable.';
    }
}
