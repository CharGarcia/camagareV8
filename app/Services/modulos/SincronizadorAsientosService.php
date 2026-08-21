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
     * Trabajo (SQL de detección + service que genera) de UN módulo, identificado por su 'clave'.
     *
     * Lo usa ContabilidadAutoService para la generación automática y silenciosa que se dispara al
     * abrir un módulo: en vez de recorrer los 15 módulos como sincronizar(), procesa solo el que
     * el usuario está mirando. Comparte esta misma definición a propósito — el SQL que decide "a
     * este documento le falta el asiento" debe ser uno solo, o el aviso de la pantalla de Asientos
     * y lo que genera el automatismo dejarían de coincidir.
     *
     * $prepararEsquema viene en false a propósito. prepararEsquema() lanza nueve
     * ALTER TABLE ... ADD COLUMN IF NOT EXISTS sobre las tablas más transitadas del sistema
     * (ventas_cabecera, compras_cabecera, ingresos_cabecera…), y aunque no tengan nada que hacer
     * cada uno toma brevemente un lock exclusivo sobre la tabla. Eso es tolerable en la
     * sincronización manual, que alguien lanza de vez en cuando; repetirlo cada vez que un usuario
     * abre un módulo sería una fuente permanente de contención. Si a esta base todavía le falta la
     * columna, el SQL de detección falla y el llamador omite ese módulo sin romper nada — es la
     * migración pendiente lo que hay que aplicar, no el lock lo que hay que pagar.
     *
     * @return array|null null si esa clave no existe (mapa mal configurado).
     */
    public function getTrabajoPorClave(int $idEmpresa, string $clave, bool $prepararEsquema = false): ?array
    {
        $db = Database::getConnection();
        if ($prepararEsquema) {
            $this->prepararEsquema($db);
        }
        $excMig = $this->construirExclusionMigracion($db);

        foreach ($this->construirTrabajos($idEmpresa, $excMig) as $t) {
            if (($t['clave'] ?? null) === $clave) {
                return $t;
            }
        }
        return null;
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
            'clave'  => 'facturas_venta',
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
            'clave'  => 'recibos_venta',
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
            'clave'  => 'liquidaciones_compra',
            'nombre' => 'Liquidaciones de Compra',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'liquidaciones_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 3. Compras (no tiene columna estado)
        $trabajos[] = [
            // Estados que NO se contabilizan, y por qué este SQL tiene que conocerlos:
            //   'anulado'              — el módulo permite dejar la compra en ese estado (el
            //                            selector de la vista ofrece borrador/registrado/anulado)
            //                            y hasta ahora este SQL no lo miraba: se le generaba el
            //                            asiento igual.
            //   'pendiente_aprobacion' — mientras espera el checkpoint de aprobación la compra
            //   'rechazada'              existe como documento recibido pero no produce efectos.
            //                            ComprasService::procesarAsientoContablePorSincronizacion
            //                            ya los descarta, pero lo hace RETORNANDO EN SILENCIO: si
            //                            el SQL los sigue trayendo, la verificación posterior los
            //                            da por "documento que quedó sin asiento" y los anota como
            //                            fallo — un falso positivo que además los dejaría marcados
            //                            y sin reintentar cuando se aprueben. Filtrarlos aquí es lo
            //                            que mantiene alineadas la detección y la generación.
            // Se compara normalizado porque los documentos migrados guardan el estado en
            // mayúsculas. COALESCE a '' para no perder las compras con estado NULL, que son
            // registros válidos anteriores a la columna.
            'sql'    => "SELECT id FROM compras_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND UPPER(TRIM(COALESCE(estado, ''))) NOT IN ('ANULADO', 'PENDIENTE_APROBACION', 'RECHAZADA')" . $excMig('compras', 'compras_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ComprasService();
            },
            'clave'  => 'compras',
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
            'clave'  => 'notas_credito',
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
            'clave'  => 'retenciones_venta',
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
            'clave'  => 'retenciones_compra',
            'nombre' => 'Retenciones en Compras',
            'dondeConfigurar' => 'Asientos Programados',
            'tablaVerif' => 'retencion_compra_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 6. Ingresos (cobros): contrapartida del concepto + formas de cobro
        $trabajos[] = [
            'sql'    => "SELECT id FROM ingresos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND UPPER(TRIM(COALESCE(estado, ''))) <> 'ANULADO'" . $excMig('ingresos', 'ingresos_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\IngresoService(
                    new \App\repositories\modulos\IngresoRepository(),
                    new \App\Rules\modulos\IngresoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'ingresos',
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
            'sql'    => "SELECT id FROM egresos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND UPPER(TRIM(COALESCE(estado, ''))) <> 'ANULADO'" . $excMig('egresos', 'egresos_cabecera.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\EgresoService(
                    new \App\repositories\modulos\EgresoRepository(),
                    new \App\Rules\modulos\EgresoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'egresos',
            'nombre' => 'Egresos',
            'dondeConfigurar' => 'Configuración Contable (Ingresos/Egresos y Cobros/Pagos)',
            'tablaVerif' => 'egresos_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['numero_egreso'],
        ];

        // 7b. Consignaciones en Ventas (reclasificación de inventario a costo).
        //     Se generan las que tengan las cuentas configuradas; el resto se avisa abajo.
        $trabajos[] = [
            // Comparación normalizada (antes era `estado <> 'Anulada'`, sensible a mayúsculas):
            // una consignación guardada como 'ANULADA' o 'anulada' —caso típico de los
            // documentos migrados— se colaba y se le generaba el asiento.
            'sql'    => "SELECT id FROM consignaciones_ventas WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND UPPER(TRIM(COALESCE(estado, ''))) <> 'ANULADA'" . $excMig('consignaciones', 'consignaciones_ventas.id'),
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ConsignacionVentaService(
                    new \App\repositories\modulos\ConsignacionVentaRepository(),
                    new \App\Rules\modulos\ConsignacionVentaRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'consignaciones',
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
            'clave'  => 'retornos_cv',
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
            'clave'  => 'cambios_producto_cv',
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
            'clave'  => 'facturacion_cv',
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
            'clave'  => 'roles_pago',
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
            'clave'  => 'importaciones',
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
        $programadoRepo = new \App\repositories\modulos\AsientoProgramadoRepository();

        // Conceptos (opciones de Ingreso/Egreso) activos realmente sin cuenta contable.
        // Dos precisiones, ambas necesarias para no dar un aviso falso:
        //  1. La cuenta vive en DOS sitios y el resto del sistema la lee con
        //     COALESCE(asientos_programados.id_cuenta, o.id_cuenta_contable) — ver
        //     AsientoProgramadoRepository::getReglasOpcionesIngresoEgreso() y
        //     AsientoBuilderService::lineasFormas(). Mirar solo la columna del módulo daba por
        //     "sin configurar" toda regla creada por siembra del plan modelo o por la importación
        //     de configuración contable, que solo escribe en asientos_programados.
        //  2. Los conceptos con cuenta OFICIAL por comportamiento (COMPRA, LIQUIDACION,
        //     FACTURA_VENTA, RECIBO_VENTA, ROL) nunca tienen cuenta propia A PROPÓSITO: la toman
        //     de la configuración de su módulo (Adquisiciones/Ventas/Recibos/Nómina) vía
        //     getCuentaOficialPorComportamiento(). Configuración Contable ni siquiera los muestra
        //     y rechaza asignarles una cuenta aparte, así que avisarlos mandaba al usuario a una
        //     pantalla donde no aparecen — "ya está todo configurado" y el aviso seguía saliendo.
        try {
            $st = $db->prepare(
                "SELECT o.nombre, o.comportamiento
                   FROM empresa_opciones_ingreso_egreso o
                   LEFT JOIN asientos_programados ap
                          ON ap.id_referencia   = o.id
                         AND ap.tipo_referencia IN ('opcion_ingreso', 'opcion_egreso')
                         AND ap.id_empresa      = o.id_empresa
                         AND ap.eliminado       = false
                  WHERE o.id_empresa = ? AND o.eliminado = false
                    AND UPPER(o.estado) = 'ACTIVO'
                    AND COALESCE(ap.id_cuenta, o.id_cuenta_contable) IS NULL
                  ORDER BY o.nombre"
            );
            $st->execute([$idEmpresa]);
            $pendientes = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $opcion) {
                if ($programadoRepo->tieneCuentaOficialPorComportamiento((string) ($opcion['comportamiento'] ?? ''))) {
                    continue; // su cuenta se configura en el módulo, no aquí
                }
                $pendientes[] = (string) $opcion['nombre'];
            }
            if (!empty($pendientes)) {
                $n = count($pendientes);
                $this->warnings[] = "Hay {$n} concepto(s) de Ingresos/Egresos sin cuenta contable asignada ("
                    . implode(', ', array_slice($pendientes, 0, 5))
                    . ($n > 5 ? ' y ' . ($n - 5) . ' más' : '')
                    . '). Configúrelos en Configuración Contable (tipo de asiento «Ingresos y Egresos»).';
            }
        } catch (\Throwable $e) {
            // Tabla inexistente (migración pendiente): omitir sin romper.
        }

        // Reglas cuya cuenta contradice la naturaleza del concepto (p. ej. la cuenta de Ventas
        // puesta en "Cuenta por cobrar"). No basta con que la pantalla lo impida hoy: una regla
        // grabada antes de esa validación, o importada del sistema viejo, sigue viva — y aquí se
        // van a generar asientos en masa con ella. Vale más avisarlo ANTES de contabilizar miles
        // de documentos que descubrirlo revisando el balance (caso real: empresas 1 y 37, ver
        // database/diagnosticos/20260819_cxc_ventas_cuenta_incorrecta.sql).
        try {
            $st = $db->prepare(
                "SELECT at.referencia AS concepto, at.tipo_cuenta, ap.tipo_referencia,
                        pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                   FROM asientos_programados ap
                   JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                   JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
                  WHERE ap.id_empresa = ? AND ap.eliminado = false
                    AND COALESCE(TRIM(at.tipo_cuenta), '') <> ''
                  ORDER BY at.referencia"
            );
            $st->execute([$idEmpresa]);
            $incompatibles = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $regla) {
                if (\App\Services\modulos\AsientoProgramadoService::cuentaCompatible($regla['tipo_cuenta'], $regla['cuenta_codigo'])) {
                    continue;
                }
                $nivel = in_array($regla['tipo_referencia'], ['asientos tipo', ''], true) || str_contains((string) $regla['tipo_referencia'], '_')
                    ? 'General'
                    : ucfirst((string) $regla['tipo_referencia']);
                $incompatibles[] = sprintf(
                    '«%s» (%s) → %s %s, que es de tipo distinto a: %s',
                    $regla['concepto'], $nivel, $regla['cuenta_codigo'], $regla['cuenta_nombre'],
                    str_replace(',', ', ', (string) $regla['tipo_cuenta'])
                );
            }
            if (!empty($incompatibles)) {
                $this->warnings[] = 'Hay ' . count($incompatibles) . ' cuenta(s) configurada(s) con una naturaleza que no corresponde al concepto. '
                    . 'Los asientos que se generen las usarán tal cual: corríjalas en Configuración Contable antes de continuar. '
                    . implode(' · ', array_slice($incompatibles, 0, 5))
                    . (count($incompatibles) > 5 ? ' · y ' . (count($incompatibles) - 5) . ' más.' : '');
            }
        } catch (\Throwable $e) {
            // Catálogo o columnas aún sin migrar: omitir sin romper la sincronización.
        }

        // Formas de Cobro/Pago activas sin cuenta contable. Misma precisión que arriba: la cuenta
        // puede vivir solo en asientos_programados (tipo_referencia forma_cobro/forma_pago), que es
        // como la lee AsientoBuilderService::lineasFormas() — COALESCE(ap.id_cuenta, f.id_cuenta_contable).
        try {
            $st = $db->prepare(
                "SELECT f.nombre
                   FROM empresa_formas_pago f
                   LEFT JOIN asientos_programados ap
                          ON ap.id_referencia   = f.id
                         AND ap.tipo_referencia IN ('forma_cobro', 'forma_pago')
                         AND ap.id_empresa      = f.id_empresa
                         AND ap.eliminado       = false
                  WHERE f.id_empresa = ? AND f.eliminado = false AND f.activo = true
                    AND COALESCE(ap.id_cuenta, f.id_cuenta_contable) IS NULL
                  ORDER BY f.nombre"
            );
            $st->execute([$idEmpresa]);
            $formas = array_map('strval', $st->fetchAll(\PDO::FETCH_COLUMN));
            if (!empty($formas)) {
                $n = count($formas);
                $this->warnings[] = "Hay {$n} forma(s) de Cobro/Pago sin cuenta contable asignada ("
                    . implode(', ', array_slice($formas, 0, 5))
                    . ($n > 5 ? ' y ' . ($n - 5) . ' más' : '')
                    . '). Configúrelas en Configuración Contable (tipo de asiento «Cobros y Pagos»).';
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
            $totalConProblema += count($sinAsiento);
            // Agrupar por MOTIVO real cuando se puede diagnosticar. Un ingreso/egreso sin formas
            // de cobro/pago vigentes (caso típico: un egreso cuyos cheques se anularon todos, que
            // AsientoBuilderService::lineasFormas() ya no cuenta) devuelve un asiento vacío SIN
            // lanzar excepción: antes caía en el mensaje genérico y el usuario lo perseguía en
            // Configuración Contable, donde no había nada que corregir.
            $motivos   = $this->motivosSinAsiento($db, $tablaVerif, $sinAsiento);
            $porMotivo = [];
            foreach ($sinAsiento as $id) {
                $porMotivo[$motivos[$id] ?? ''][] = $id;
            }
            foreach ($porMotivo as $motivo => $idsMotivo) {
                $n    = count($idsMotivo);
                $docs = $this->listarDocumentos($idsMotivo, $numeros);
                $texto = $motivo !== ''
                    ? "{$nombreModulo} — {$n} documento(s) sin asiento: {$motivo} (documento(s): {$docs})"
                    : "{$nombreModulo} — {$n} documento(s) sin asiento (documento(s): {$docs})";
                $this->detalle[] = $texto;
                error_log("[SincronizadorAsientos] {$texto} — ids: " . implode(',', $idsMotivo));
            }
        }

        if ($totalConProblema > 0) {
            $this->resumenPorModulo[$nombreModulo] = ($this->resumenPorModulo[$nombreModulo] ?? 0) + $totalConProblema;
        }
    }

    /**
     * Explica, cuando se puede, POR QUÉ un ingreso/egreso quedó sin asiento aunque la
     * configuración contable esté completa: el builder devuelve un asiento vacío —sin lanzar
     * excepción— si el documento no tiene formas de cobro/pago vigentes con monto. El caso real
     * es el egreso al que se le anularon todos los cheques: el documento sigue "registrado" (no
     * anulado), pero ya no queda pago que contabilizar, así que reportarlo como un problema de
     * cuentas mandaba al usuario a corregir algo que estaba bien.
     *
     * @return array<int, string> id de documento => motivo. Los ids que no se pueden diagnosticar
     *                            no aparecen (el llamador cae al mensaje genérico).
     */
    private function motivosSinAsiento(\PDO $db, ?string $tablaVerif, array $ids): array
    {
        $mapa = [
            'ingresos_cabecera' => ['tabla' => 'ingresos_pagos', 'col' => 'id_ingreso', 'flujo' => 'cobro',  'doc' => 'El ingreso'],
            'egresos_cabecera'  => ['tabla' => 'egresos_pagos',  'col' => 'id_egreso',  'flujo' => 'pago',   'doc' => 'El egreso'],
        ];
        if ($tablaVerif === null || !isset($mapa[$tablaVerif]) || empty($ids)) {
            return [];
        }
        $cfg = $mapa[$tablaVerif];

        // Solo egresos_pagos tiene eliminado / estado_cheque (ver AsientoBuilderService::lineasFormas).
        $esEgreso   = $tablaVerif === 'egresos_cabecera';
        $filtroElim = $esEgreso ? " AND p.eliminado = FALSE" : '';
        $condVigente = $esEgreso ? "COALESCE(p.estado_cheque, 'vigente') <> 'anulado'" : 'TRUE';
        $in = implode(',', array_map('intval', $ids));

        try {
            $sql = "SELECT p.{$cfg['col']} AS id_doc,
                           COUNT(*) AS total,
                           COALESCE(SUM(CASE WHEN {$condVigente} AND p.monto > 0 THEN p.monto ELSE 0 END), 0) AS monto_vigente,
                           SUM(CASE WHEN NOT ({$condVigente}) THEN 1 ELSE 0 END) AS anulados
                      FROM {$cfg['tabla']} p
                     WHERE p.{$cfg['col']} IN ({$in}){$filtroElim}
                     GROUP BY p.{$cfg['col']}";
            $filas = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return []; // Tabla/columna inexistente (migración pendiente): sin diagnóstico.
        }

        $porDoc = [];
        foreach ($filas as $f) {
            $porDoc[(int) $f['id_doc']] = $f;
        }

        $motivos = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $f  = $porDoc[$id] ?? null;
            if ($f === null || (int) $f['total'] === 0) {
                $motivos[$id] = "{$cfg['doc']} no tiene formas de {$cfg['flujo']} registradas: no hay nada que contabilizar.";
                continue;
            }
            if (round((float) $f['monto_vigente'], 2) > 0) {
                continue; // sí hay monto vigente: el motivo es otro (cuentas, cascada, etc.)
            }
            $motivos[$id] = ((int) $f['anulados'] > 0)
                ? "{$cfg['doc']} no tiene ningún cheque vigente (todos anulados): no queda pago que contabilizar."
                : "Las formas de {$cfg['flujo']} de este documento suman 0.00: no hay valor que contabilizar.";
        }

        return $motivos;
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
