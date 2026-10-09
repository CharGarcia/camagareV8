<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;

class SincronizadorAsientosService
{
    private array $warnings = [];
    /** Notas informativas (no son errores): explican comportamientos intencionales. */
    private array $info = [];
    private int $generados = 0;
    /**
     * Resumen corto de documentos con problema, agrupado por módulo: ['Facturas de Venta' => 20, ...].
     * Es lo que se muestra por defecto al usuario (ej. "Hay 20 asiento(s) por generar en Facturas de
     * Venta"); el detalle motivo-por-motivo (qué cuenta falta, qué documentos) se guarda aparte en
     * $detalle, que ya no se muestra al usuario: queda para soporte y para el log del servidor.
     */
    private array $resumenPorModulo = [];
    /** Detalle motivo-por-motivo (mismo contenido que antes iba directo a $warnings). */
    private array $detalle = [];
    /**
     * Lo que el usuario tiene que HACER, sin cifras: una línea por sección de Configuración
     * Contable (ej. "Falta configurar la Cuenta por Pagar en algunos proveedores"), sin repetir
     * aunque 70 documentos fallen por la misma causa. Es el mensaje principal del aviso; el
     * conteo por módulo y el detalle por documento ya no se muestran (solo log). Ver
     * registrarAccion().
     */
    private array $acciones = [];
    /**
     * Formas de cobro/pago sin cuenta que usan los documentos que fallaron, por flujo
     * ('cobro' | 'pago') => [nombre => inactiva]. Para que el aviso diga CUÁLES son (y cuáles están
     * inactivas) en vez del genérico «algunas formas…». Ver accionFormaSinCuenta().
     */
    private array $formasSinCuenta = ['cobro' => [], 'pago' => []];
    /** Igual que $formasSinCuenta para los conceptos de Ingresos y Egresos: flujo => [nombre => inactivo]. */
    private array $conceptosSinCuenta = ['ingreso' => [], 'egreso' => []];
    /** Línea de detalle del lote que se está registrando (ver registrarAccion/adjuntarDetalle). */
    private ?string $detalleActual = null;
    /**
     * Documentos sin asiento que NO se deben generar: sin valor que contabilizar, o que cobran/pagan
     * documentos de un módulo apagado en «Módulos que contabilizan». Se restan del total de
     * pendientes (getPendientes()) para que el aviso cuente solo lo que en realidad falta generar.
     */
    private int $noGenerables = 0;

    /**
     * Sección de Configuración Contable de la que sale el asiento de cada módulo (clave del
     * trabajo => tipo de asiento del selector de esa pantalla + nombre visible). 'entidad' marca
     * los módulos donde un cliente/proveedor puede tener cuentas propias que mandan sobre la
     * General (cascada "la entidad manda"): [tipo_referencia, tabla del documento, columna].
     * Liquidaciones de Compra no la lleva: su asiento no aplica reglas por proveedor.
     */
    private const AREAS = [
        'facturas_venta'        => ['tipo' => 'ventas_factura',            'nombre' => 'Ventas con Factura',          'entidad' => ['cliente', 'ventas_cabecera', 'id_cliente']],
        'recibos_venta'         => ['tipo' => 'recibos_venta',             'nombre' => 'Recibos de Venta',            'entidad' => ['cliente', 'recibos_venta_cabecera', 'id_cliente']],
        'notas_credito'         => ['tipo' => 'ventas_factura',            'nombre' => 'Ventas con Factura',          'entidad' => ['cliente', 'notas_credito_cabecera', 'id_cliente']],
        'notas_debito'          => ['tipo' => 'ventas_factura',            'nombre' => 'Ventas con Factura'],
        'compras'               => ['tipo' => 'adquisiciones_compras',     'nombre' => 'Adquisiciones de Compras',    'entidad' => ['proveedor', 'compras_cabecera', 'id_proveedor']],
        'liquidaciones_compra'  => ['tipo' => 'adquisiciones_compras',     'nombre' => 'Adquisiciones de Compras'],
        'importaciones'         => ['tipo' => 'adquisiciones_importacion', 'nombre' => 'Importaciones'],
        'factura_reembolso'     => ['tipo' => 'factura_reembolso',         'nombre' => 'Factura de Reembolso'],
        'retenciones_venta'     => ['tipo' => 'retenciones_venta',         'nombre' => 'Retenciones en Venta'],
        'retenciones_compra'    => ['tipo' => 'retenciones_compra',        'nombre' => 'Retenciones en Compra'],
        'ingresos'              => ['tipo' => 'ingresos_egresos',          'nombre' => 'Ingresos y Egresos'],
        'egresos'               => ['tipo' => 'ingresos_egresos',          'nombre' => 'Ingresos y Egresos'],
        'consignaciones'        => ['tipo' => 'consignacion_venta',        'nombre' => 'Consignaciones en Ventas'],
        'retornos_cv'           => ['tipo' => 'consignacion_venta',        'nombre' => 'Consignaciones en Ventas'],
        'cambios_producto_cv'   => ['tipo' => 'consignacion_venta',        'nombre' => 'Consignaciones en Ventas'],
        'facturacion_cv'        => ['tipo' => 'consignacion_venta',        'nombre' => 'Consignaciones en Ventas'],
        'roles_pago'            => ['tipo' => 'nomina',                    'nombre' => 'Nómina'],
        'conciliacion_tarjetas' => ['tipo' => 'cobros_pagos',              'nombre' => 'Cobros y Pagos (formas de cobro con tarjeta)', 'seccion' => 'cobros'],
        'traspasos'             => ['tipo' => 'cobros_pagos',              'nombre' => 'Cobros y Pagos'],
        'cobros_cheques'        => ['tipo' => 'cobros_pagos',              'nombre' => 'Cobros y Pagos'],
        'activos_fijos_alta'    => ['tipo' => 'activos_fijos_alta',        'nombre' => 'Activos Fijos - Alta'],
    ];

    /** Empresa de la corrida en curso: la usa registrarAccion() para consultar el interruptor. */
    private int $idEmpresa = 0;

    public function sincronizar(int $idEmpresa, int $idUsuario): void
    {
        $this->idEmpresa = $idEmpresa;
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
                $t['colsDoc'] ?? [],
                (string) ($t['clave'] ?? '')
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
        $this->idEmpresa = $idEmpresa;
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
                $t['dondeConfigurar'], $t['tablaVerif'], $t['colAsiento'], $t['colsDoc'] ?? [],
                (string) ($t['clave'] ?? '')
            );
        } elseif ($paso === count($trabajos)) {
            $nombrePaso = 'Configuración de cuentas (Ingresos/Egresos, Cobros/Pagos, Productos en ventas)';
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
            'pendientes'       => $this->getPendientes(),
            'acciones'         => array_values($this->acciones),
            'puedeConfigurar'  => $this->puedeConfigurar(),
            'info'             => $this->info,
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

        return $this->contarPendientesSinEsquema($db, $idEmpresa);
    }

    /**
     * contarPendientes() de varias empresas (los establecimientos de un RUC, vistos desde la
     * matriz en Estados Financieros) preparando el esquema una sola vez.
     *
     * @param int[] $idsEmpresa
     * @return array<int,int> id_empresa => documentos pendientes
     */
    public function contarPendientesEmpresas(array $idsEmpresa): array
    {
        $db = Database::getConnection();
        $this->prepararEsquema($db);

        $out = [];
        foreach ($idsEmpresa as $id) {
            $out[(int) $id] = $this->contarPendientesSinEsquema($db, (int) $id);
        }
        return $out;
    }

    private function contarPendientesSinEsquema(\PDO $db, int $idEmpresa): int
    {
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
        // Antes se lanzaban los 9 ALTER TABLE … ADD COLUMN IF NOT EXISTS SIEMPRE, en cada paso de
        // la generación (y al contar pendientes). Aunque la columna ya exista, el ALTER pide un
        // bloqueo exclusivo de la tabla y ESPERA sin límite a que termine cualquier transacción
        // abierta sobre ella (otro usuario guardando una compra, un reporte largo…); mientras
        // espera, además, todas las demás consultas a esa tabla quedan en fila detrás de él. Así
        // la generación se quedaba para siempre en "Preparando…" (reproducido el 23-09-2026 con
        // una transacción abierta sobre compras_cabecera). Ahora se mira el catálogo —que no
        // bloquea nada— y solo se altera la tabla a la que de verdad le falta la columna, con un
        // tope de espera.
        $tablas = [
            'compras_cabecera', 'liquidaciones_cabecera', 'notas_credito_cabecera',
            'nota_debito_cabecera', 'retencion_venta_cabecera', 'retencion_compra_cabecera',
            'ingresos_cabecera', 'egresos_cabecera', 'consignaciones_ventas',
        ];
        try {
            $st = $db->prepare(
                "SELECT c.relname
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = current_schema()
                 WHERE c.relname = ANY(string_to_array(:tablas, ','))
                   AND c.relkind = 'r'
                   AND NOT EXISTS (SELECT 1 FROM pg_attribute a
                                   WHERE a.attrelid = c.oid AND a.attname = 'id_asiento_contable'
                                     AND a.attnum > 0 AND NOT a.attisdropped)"
            );
            $st->execute([':tablas' => implode(',', $tablas)]);
            $faltantes = $st->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return;
        }
        if (!$faltantes) {
            return;
        }

        try {
            $db->exec("SET lock_timeout = '3s'");
            foreach ($faltantes as $tabla) {
                if (!in_array($tabla, $tablas, true)) {
                    continue;
                }
                try {
                    $db->exec("ALTER TABLE {$tabla} ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER");
                } catch (\Throwable $e) {
                    // Sin permisos o tabla ocupada: el SQL de detección de ese módulo fallará y se
                    // omitirá sin romper; la columna se agrega en el próximo intento.
                }
            }
        } finally {
            try { $db->exec("SET lock_timeout = 0"); } catch (\Throwable $e) {}
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
            $sql = '';
            if ($tieneMapMig) {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map mm WHERE mm.entidad = '{$entidad}' AND mm.id_destino = {$idExpr} AND mm.vinculado IS NOT TRUE) ";
            }
            // Segunda condición, independiente del mapa: el documento ya está ENLAZADO a un
            // asiento migrado vivo (documento.id_asiento_contable → modulo_origen = 'migracion').
            // El mapa no cubre a los documentos NATIVOS que la migración solo enlazó por número
            // (vinculado = true) ni a los que perdieron su fila del mapa; pero si su contabilidad
            // ya vino del histórico, tampoco deben entrar a las ramas que regeneran documentos con
            // asiento (costeo pendiente / sin fila de seguimiento en Facturas, Recibos y NC).
            // Mismo criterio que AsientoContableService::guardarAsiento(), que además corta a
            // cualquier otro camino de generación. Todos los $idExpr son "alias.id".
            $aliasDoc = preg_replace('/\.id$/', '', $idExpr);
            if ($aliasDoc !== null && $aliasDoc !== '' && $aliasDoc !== $idExpr) {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM asientos_contables_cabecera am WHERE am.id = {$aliasDoc}.id_asiento_contable AND am.modulo_origen = 'migracion' AND am.eliminado = false AND am.estado <> 'anulado') ";
            }
            return $sql;
        };
    }

    /**
     * Exclusión de los documentos DERIVADOS de una consignación migrada (retornos y facturaciones
     * de consignación).
     *
     * Existe porque `construirExclusionMigracion()` no alcanza para ellos: migracion_mysql_map solo
     * registra la entidad 'consignaciones' — nunca 'retornos_cv' ni 'consignaciones_facturas' —, así
     * que el documento derivado no aparece como migrado aunque su consignación madre sí lo esté.
     *
     * Y no es solo una cuestión de ruido: el retorno y el reingreso por facturación son el asiento
     * INVERSO de la consignación (Debe Inventario / Haber Mercadería en Consignación). Si la madre
     * está excluida por migrada y nunca hizo su asiento, generar el inverso acreditaría una cuenta de
     * activo que jamás se debitó, dejándola con saldo acreedor. Regla: si la consignación de origen
     * no se contabiliza, sus derivados tampoco.
     *
     * @return callable(string,string,string):string (tablaDetalle, columnaDocumento, expresiónId)
     */
    private function construirExclusionConsignacionMigrada(\PDO $db): callable
    {
        $tieneMapMig = false;
        try {
            $tieneMapMig = (bool) $db->query("SELECT to_regclass('public.migracion_mysql_map')")->fetchColumn();
        } catch (\Throwable $e) {
            $tieneMapMig = false;
        }

        // Los tres argumentos son literales del código (no entrada de usuario) → seguros de interpolar.
        return function (string $tablaDetalle, string $colDoc, string $idExpr) use ($tieneMapMig): string {
            if (!$tieneMapMig) { return ''; }
            return " AND NOT EXISTS (
                        SELECT 1
                          FROM {$tablaDetalle} dmig
                          JOIN migracion_mysql_map mmc
                            ON mmc.entidad     = 'consignaciones'
                           AND mmc.id_destino  = dmig.id_consignacion
                           AND mmc.vinculado IS NOT TRUE
                         WHERE dmig.{$colDoc} = {$idExpr}
                           AND dmig.eliminado = false
                     ) ";
        };
    }

    /**
     * Construye la lista de "trabajos" de sincronización: cada entrada describe la consulta que
     * detecta los documentos pendientes de un módulo y cómo generar su asiento. La usan tanto
     * sincronizar() (para generar) como contarPendientes() (para solo contar), garantizando que
     * ambos miren exactamente los mismos documentos.
     *
     * 'tipoCosteo' (solo Facturas, Recibos y Notas de Crédito) es el tipo_documento de ese módulo en
     * ventas_costeo_seguimiento, el mismo literal que usa su SQL. No lo lee la sincronización manual:
     * lo usa ContabilidadAutoService para no regenerar en cada pasada lo que sigue con el costo pendiente.
     *
     * @return array<int, array{sql:string, params:array, factory:callable, nombre:string, dondeConfigurar:string, tablaVerif:?string, colAsiento:string, tipoCosteo?:string}>
     */
    private function construirTrabajos(int $idEmpresa, callable $excMig): array
    {
        $trabajos = [];

        // Exclusión de los derivados de una consignación migrada (ver el método por qué el
        // $excMig normal no basta para ellos). Se arma aquí y no se recibe por parámetro para no
        // cambiar la firma en los cinco llamadores; la conexión es la misma instancia compartida.
        $excMigConsig = $this->construirExclusionConsignacionMigrada(Database::getConnection());

        // Herencia de la madre: Retornos y Facturación CV solo tienen asiento inverso si al menos
        // una de sus líneas viene de una consignación con asiento. Debe decir lo mismo que el
        // builder (misma condición), o el documento se vería pendiente y fallaría en cada pasada.
        $conMadreContab = static fn(string $tablaDetalle, string $colDoc, string $idExpr): string =>
            " AND EXISTS (SELECT 1 FROM {$tablaDetalle} dh
                           WHERE dh.{$colDoc} = {$idExpr} AND dh.eliminado = false
                             AND " . \App\repositories\modulos\ConsignacionVentaRepository::sqlContabilizada('dh.id_consignacion') . ") ";

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
            'tipoCosteo' => 'factura_venta',
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
            'tipoCosteo' => 'recibo_venta',
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
            'tipoCosteo' => 'nota_credito_venta',
        ];

        // 5. Retenciones en Ventas (no se autorizan en SRI: solo se filtra por asiento faltante)
        //    Una retención con valor retenido CERO no tiene nada que contabilizar: el builder devuelve
        //    un asiento vacío y nunca se le enlaza asiento, así que quedaba "pendiente" para siempre.
        //    Se considera con valor si lo tiene la cabecera (totales) o alguna línea del detalle.
        $trabajos[] = [
            'sql'    => "SELECT id FROM retencion_venta_cabecera
                         WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL
                           AND (
                                 ABS(COALESCE(total_iva, 0)) + ABS(COALESCE(total_renta, 0)) + ABS(COALESCE(total_isd, 0)) >= 0.01
                              OR EXISTS (
                                     SELECT 1 FROM retencion_venta_detalle rvd
                                     WHERE rvd.id_retencion = retencion_venta_cabecera.id
                                       AND ABS(COALESCE(rvd.valor_retenido, 0)) >= 0.01
                                 )
                           )" . $excMig('retenciones_venta', 'retencion_venta_cabecera.id'),
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
            // El $excMig por 'retornos_cv' se mantiene por si algún día el migrador registra esa
            // entidad, pero hoy no excluye nada: quien de verdad filtra los retornos migrados es
            // $excMigConsig, que mira la consignación de origen.
            'sql'    => "SELECT id FROM retornos_cv WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado = 'Emitida'"
                        . $excMig('retornos_cv', 'retornos_cv.id')
                        . $excMigConsig('retornos_cv_detalles', 'id_retorno', 'retornos_cv.id')
                        . $conMadreContab('retornos_cv_detalles', 'id_retorno', 'retornos_cv.id'),
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
        //         Los migrados tampoco: el sistema anterior no contabilizaba consignaciones ni cambios.
        $trabajos[] = [
            'sql'    => "SELECT id FROM cambios_producto_cv WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND estado = 'Emitida'"
                        . $excMig('cambios_producto', 'cambios_producto_cv.id'),
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

        // 7c-ter. Ajustes de inventario del módulo Inventario: una fila del kardex = un asiento.
        //         Solo los marcados (contabiliza_ajuste, desde el 08-10-2026) y con costo. Sin las
        //         columnas (SQL 20261008 pendiente) no se agrega el trabajo, para no mostrar un
        //         aviso de "revise la migración" en Estados Financieros.
        if ((new \App\repositories\modulos\InventarioRepository())->tieneColumnasAjusteContable()) {
            $trabajos[] = [
                'sql'    => "SELECT id FROM inventario_kardex
                             WHERE id_empresa = ? AND eliminado = false AND contabiliza_ajuste = true
                               AND id_asiento_contable IS NULL AND referencia_tipo = 'ajuste_manual'
                               AND ABS(COALESCE(costo_total, 0)) >= 0.005",
                'params' => [$idEmpresa],
                'factory' => function() {
                    return new \App\Services\modulos\AjusteInventarioAsientoService();
                },
                'clave'  => 'ajustes_inventario',
                'nombre' => 'Ajustes de Inventario',
                'dondeConfigurar' => 'Configuración Contable (Ajustes de Inventario)',
                'tablaVerif' => 'inventario_kardex',
                'colAsiento' => 'id_asiento_contable',
                'colsDoc' => [], // sin número propio: el aviso los muestra como "#id" del movimiento
            ];
        }

        // 7d. Facturación de Consignaciones (asiento INVERSO del reingreso de inventario).
        //     Solo las ya 'facturada' lo tienen; el enlace es id_asiento_reingreso (no id_asiento_contable).
        $trabajos[] = [
            // Igual que en Retornos: el reingreso es el asiento inverso de la consignación, así que
            // si la consignación de origen está excluida por migrada, su facturación también.
            // Los registros que genera un cambio de productos no tienen reingreso: su asiento es el del cambio.
            'sql'    => "SELECT id FROM consignaciones_facturas WHERE id_empresa = ? AND eliminado = false AND id_asiento_reingreso IS NULL AND estado = 'facturada'"
                        . \App\repositories\modulos\CambioProductoCvRepository::sqlNoEsRegistroDeCambio('consignaciones_facturas')
                        . $excMigConsig('consignaciones_facturas_detalles', 'id_consignacion_factura', 'consignaciones_facturas.id')
                        . $conMadreContab('consignaciones_facturas_detalles', 'id_consignacion_factura', 'consignaciones_facturas.id'),
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

        // 10. Conciliación de Tarjetas: el asiento del depósito (banco + comisión + retenciones
        //     contra la cuenta puente) se genera al cerrar; si en ese momento faltaba una cuenta
        //     o el período estaba cerrado, la conciliación queda cerrada sin asiento y se completa aquí.
        $trabajos[] = [
            'sql'    => "SELECT id FROM conciliacion_tarjetas_cabecera WHERE id_empresa = ? AND eliminado = false AND estado = 'cerrada' AND id_asiento_contable IS NULL",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ConciliacionTarjetasService(
                    new \App\repositories\modulos\ConciliacionTarjetasRepository(),
                    new \App\Rules\modulos\ConciliacionTarjetasRules(),
                    new \App\Services\modulos\ConciliacionTarjetasImportService(),
                    new \App\Services\modulos\ConciliacionTarjetasMatchService(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'conciliacion_tarjetas',
            'nombre' => 'Conciliación de Tarjetas',
            'dondeConfigurar' => 'Formas de Cobro (cuenta puente y banco) y la pestaña «Configuración» de la conciliación',
            'tablaVerif' => 'conciliacion_tarjetas_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['numero'],
        ];

        // 11. Notas de Débito de venta: el asiento se genera al autorizarse en el SRI.
        $trabajos[] = [
            'sql'    => "SELECT id FROM nota_debito_cabecera WHERE id_empresa = ? AND eliminado = false AND estado = 'autorizado' AND id_asiento_contable IS NULL",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\NotaDebitoService(
                    new \App\repositories\modulos\NotaDebitoRepository(),
                    new \App\Rules\modulos\NotaDebitoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'notas_debito',
            'nombre' => 'Notas de Débito',
            'dondeConfigurar' => 'Configuración Contable (Facturas de Venta)',
            'tablaVerif' => 'nota_debito_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 12. Facturas de Reembolso: asiento «cuenta puente» al autorizarse en el SRI.
        $trabajos[] = [
            'sql'    => "SELECT id FROM factura_reembolso_cabecera WHERE id_empresa = ? AND eliminado = false AND estado = 'autorizado' AND id_asiento_contable IS NULL",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\FacturaReembolsoService(
                    new \App\repositories\modulos\FacturaReembolsoRepository(),
                    new \App\Rules\modulos\FacturaReembolsoRules(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'factura_reembolso',
            'nombre' => 'Facturas de Reembolso',
            'dondeConfigurar' => 'Configuración Contable (Factura de Reembolso)',
            'tablaVerif' => 'factura_reembolso_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['establecimiento', 'punto_emision', 'secuencial'],
        ];

        // 13. Traspasos de fondos entre formas de cobro/pago.
        $trabajos[] = [
            'sql'    => "SELECT id FROM traspasos_cabecera WHERE id_empresa = ? AND eliminado = false AND id_asiento_contable IS NULL AND UPPER(TRIM(COALESCE(estado, ''))) <> 'ANULADO'",
            'params' => [$idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\TraspasoService(
                    new \App\repositories\modulos\TraspasoRepository(),
                    new \App\Rules\modulos\TraspasoRules(),
                    new \App\repositories\modulos\FormaPagoRepository(),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'traspasos',
            'nombre' => 'Traspasos',
            'dondeConfigurar' => 'Configuración Contable (Cobros y Pagos)',
            'tablaVerif' => 'traspasos_cabecera',
            'colAsiento' => 'id_asiento_contable',
            'colsDoc' => ['numero_traspaso'],
        ];

        // 13b. Cobro de cheques posfechados: los que ya tienen Fecha Banco en Control Bancario y
        //      cuyo ingreso/egreso usa la cuenta puente, sin asiento de cobro (p. ej. si al poner
        //      la fecha faltaba la cuenta de la forma). Ver ChequePosfechadoService.
        $trabajos[] = [
            'sql'    => \App\repositories\modulos\ChequePosfechadoRepository::sqlPendientesDeCobro(),
            'params' => [$idEmpresa, $idEmpresa],
            'factory' => function() {
                return new \App\Services\modulos\ChequePosfechadoService();
            },
            'clave'  => 'cobros_cheques',
            'nombre' => 'Cobro de cheques posfechados',
            'dondeConfigurar' => 'Configuración Contable (Cobros y Pagos)',
            'tablaVerif' => 'control_bancario_movimientos',
            'colAsiento' => 'id_asiento_cobro',
            'colsDoc' => ['numero_cheque'],
        ];

        // 14. Activos Fijos: asiento de ALTA de los activos manuales (los de una compra ya
        //     quedaron contabilizados por la compra). La depreciación no entra aquí: el lote
        //     mensual se contabiliza en la misma transacción o no se crea.
        $trabajos[] = [
            'sql'    => "SELECT id FROM activos_fijos WHERE id_empresa = ? AND eliminado = false AND origen = 'manual' AND id_asiento_alta IS NULL AND valor_adquisicion > 0",
            'params' => [$idEmpresa],
            'factory' => function() {
                $repo    = new \App\repositories\modulos\ActivoFijoRepository();
                $catRepo = new \App\repositories\modulos\ActivoFijoCategoriaRepository();
                return new \App\Services\modulos\ActivoFijoService(
                    $repo,
                    $catRepo,
                    new \App\repositories\modulos\ActivoFijoLoteRepository(),
                    new \App\repositories\modulos\ComprasRepository(),
                    new \App\Rules\modulos\ActivoFijoRules($repo, $catRepo),
                    new \App\Services\LogSistemaService()
                );
            },
            'clave'  => 'activos_fijos_alta',
            'nombre' => 'Activos Fijos (alta)',
            'dondeConfigurar' => 'el propio activo o Configuración Contable (Activos Fijos - Alta)',
            'tablaVerif' => 'activos_fijos',
            'colAsiento' => 'id_asiento_alta',
            'colsDoc' => ['codigo', 'nombre'],
        ];

        // Módulos que la empresa apagó en «Módulos que contabilizan»: no se generan ni se cuentan
        // como pendientes (el aviso de Estados Financieros / Balance no debe reclamarlos). Filtrar
        // aquí cubre de una vez sincronizar(), ejecutarPaso(), contarPasos(), contarPendientes()
        // y getTrabajoPorClave(), que comparten esta lista.
        $interruptor = ContabilidadInterruptorService::crear();
        return array_values(array_filter(
            $trabajos,
            static fn(array $t): bool => $interruptor->contabiliza($idEmpresa, (string) ($t['clave'] ?? ''))
        ));
    }

    /**
     * Revisa la configuración contable de Ingresos/Egresos y Cobros/Pagos y genera un aviso
     * si hay conceptos (opciones) o formas activas sin cuenta contable asignada.
     */
    private function verificarConfiguracionCuentas(\PDO $db, int $idEmpresa): void
    {
        $programadoRepo = new \App\repositories\modulos\AsientoProgramadoRepository();

        // Conceptos y formas de cobro/pago solo los usan Ingresos y Egresos: si la empresa apagó
        // ambos módulos, que les falte la cuenta no impide nada y no se avisa.
        $interruptor = ContabilidadInterruptorService::crear();
        $tesoreria   = $interruptor->contabiliza($idEmpresa, 'ingresos') || $interruptor->contabiliza($idEmpresa, 'egresos');

        // Conceptos (opciones de Ingreso/Egreso) sin cuenta contable que ya USAN ingresos/egresos
        // vigentes (OpcionIngresoEgresoRepository::getUsadosSinCuenta()): uno que nadie usa no deja
        // ningún asiento pendiente. Precisiones para no dar un aviso falso:
        //  1. La cuenta vive en DOS sitios y el resto del sistema la lee con
        //     COALESCE(asientos_programados.id_cuenta, o.id_cuenta_contable) — ver
        //     AsientoProgramadoRepository::getReglasOpcionesIngresoEgreso(). Mirar solo la columna
        //     del módulo daba por "sin configurar" toda regla creada por siembra del plan modelo o
        //     por la importación de configuración contable, que solo escribe en asientos_programados.
        //  2. Los conceptos con cuenta OFICIAL por comportamiento (COMPRA, LIQUIDACION,
        //     FACTURA_VENTA, RECIBO_VENTA, ROL) nunca tienen cuenta propia A PROPÓSITO: la toman
        //     de la configuración de su módulo vía getCuentaOficialPorComportamiento(). Configuración
        //     Contable ni siquiera los muestra, así que no se avisan.
        //  3. Activos → se configuran en Configuración Contable. Inactivos → ahí no aparecen: se
        //     manda al módulo Opciones de Ingreso/Egreso, que permite editarlos inactivos.
        try {
            $activos = $inactivos = [];
            if ($tesoreria) {
                foreach ((new \App\repositories\modulos\OpcionIngresoEgresoRepository())->getUsadosSinCuenta($idEmpresa) as $opcion) {
                    if ($programadoRepo->tieneCuentaOficialPorComportamiento((string) ($opcion['comportamiento'] ?? ''))) {
                        continue; // su cuenta se configura en el módulo, no aquí
                    }
                    if ($opcion['activo']) {
                        $activos[] = $opcion['nombre'];
                    } else {
                        $inactivos[] = $opcion['nombre'];
                    }
                }
            }
            $lista = function (array $nombres): string {
                $nombres = array_values(array_unique($nombres));
                $n = count($nombres);
                return implode(', ', array_slice($nombres, 0, 5)) . ($n > 5 ? ' y ' . ($n - 5) . ' más' : '');
            };
            if ($activos) {
                $this->warnings[] = 'Hay ' . count(array_unique($activos)) . ' concepto(s) de Ingresos/Egresos sin cuenta contable asignada que ya se usan en documentos ('
                    . $lista($activos) . '). Configúrelos en Configuración Contable (tipo de asiento «Ingresos y Egresos»).';
            }
            if ($inactivos) {
                $this->warnings[] = 'Hay ' . count(array_unique($inactivos)) . ' concepto(s) de Ingresos/Egresos INACTIVO(S) sin cuenta contable que ya se usan en documentos ('
                    . $lista($inactivos) . '). Por estar inactivos no aparecen en Configuración Contable: asígneles la cuenta en '
                    . 'Opciones de Ingreso/Egreso (editar el concepto → Cuenta contable).';
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

        // Formas de Cobro/Pago sin cuenta contable: solo las USADAS en documentos que se contabilizan
        // (ver FormaPagoRepository::getUsadasSinCuenta()); una que ningún documento usa no deja
        // ningún asiento pendiente. Activas → Configuración Contable. Inactivas → no aparecen ahí,
        // así que se manda al módulo Formas de Cobros y Pagos (permite editarlas inactivas).
        try {
            $formas = $tesoreria
                ? (new \App\repositories\modulos\FormaPagoRepository())->getUsadasSinCuenta($idEmpresa)
                : [];
            $lista = function (array $nombres): string {
                $n = count($nombres);
                return implode(', ', array_slice($nombres, 0, 5)) . ($n > 5 ? ' y ' . ($n - 5) . ' más' : '');
            };
            $activas   = array_values(array_unique(array_column(array_filter($formas, fn($f) => $f['activo']), 'nombre')));
            $inactivas = array_values(array_unique(array_column(array_filter($formas, fn($f) => !$f['activo']), 'nombre')));
            if ($activas) {
                $this->warnings[] = 'Hay ' . count($activas) . ' forma(s) de Cobro/Pago sin cuenta contable asignada ('
                    . $lista($activas) . '). Configúrelas en Configuración Contable (tipo de asiento «Cobros y Pagos»).';
            }
            if ($inactivas) {
                $this->warnings[] = 'Hay ' . count($inactivas) . ' forma(s) de Cobro/Pago INACTIVA(S) sin cuenta contable que ya se usan en documentos ('
                    . $lista($inactivas) . '). Por estar inactivas no aparecen en Configuración Contable: asígneles la cuenta en '
                    . 'Formas de Cobros y Pagos (editar la forma → Cuenta Contable).';
            }
        } catch (\Throwable $e) {
            // Tabla inexistente (migración pendiente): omitir sin romper.
        }

        // Productos/servicios sin la categoría (o marca) con la que se contabiliza el asiento: si un
        // tipo de asiento tiene reglas por categoría/marca, todo producto debe tenerla asignada —
        // lo que falta ahí es ASIGNARLA en Productos, no una cuenta (ver getProductosSinClasificacion()).
        // Después, los que SÍ la tienen pero su categoría/marca no tiene la cuenta y no hay General
        // (getProductosSinCuentaVentas()): esos sí bloquean sus facturas por falta de cuenta.
        try {
            $lista = function (array $items, int $total): string {
                $nombres = array_map(fn($p) => $p['nombre'], array_slice($items, 0, 5));
                return implode(', ', $nombres) . ($total > 5 ? ' y ' . ($total - 5) . ' más' : '');
            };
            $tiposProducto = [
                'ventas_factura'        => ['facturas_venta', 'Ventas con Factura'],
                'recibos_venta'         => ['recibos_venta', 'Recibos de Venta'],
                'adquisiciones_compras' => ['compras', 'Compras'],
            ];
            $faltaClasif = ['categoria' => false, 'marca' => false]; // en Ventas con Factura
            foreach ($tiposProducto as $tipo => [$clave, $nombreTipo]) {
                if (!$interruptor->contabiliza($idEmpresa, $clave)) continue;
                $sc = $programadoRepo->getProductosSinClasificacion($idEmpresa, $tipo, 100);
                foreach (['categoria' => ['categorías', 'categoría'], 'marca' => ['marcas', 'marca']] as $dim => [$plural, $singular]) {
                    $d = $sc[$dim] ?? null;
                    if (!$d || !$d['total']) continue;
                    if ($tipo === 'ventas_factura') $faltaClasif[$dim] = true;
                    $this->warnings[] = "{$nombreTipo} se contabiliza por {$plural}, pero hay {$d['total']} producto(s)/servicio(s) sin {$singular} asignada ("
                        . $lista($d['items'], $d['total']) . "). Asígnesela en Productos (editar el producto → "
                        . ucfirst($singular) . "): mientras no la tengan no usan las cuentas de su {$singular} "
                        . '(toman la cuenta General o, si no la hay, su documento no genera asiento).';
                }
            }
            if ($interruptor->contabiliza($idEmpresa, 'facturas_venta')) {
                // Los que ya salieron arriba por no tener categoría/marca no se repiten aquí.
                $productos = array_values(array_filter(
                    $programadoRepo->getProductosSinCuentaVentas($idEmpresa, 100),
                    fn($p) => !(($faltaClasif['categoria'] && $p['sin_categoria']) || ($faltaClasif['marca'] && $p['sin_marca']))
                ));
                if ($productos) {
                    $nombres = array_map(fn($p) => $p['nombre'] . ' → falta: ' . $p['faltan'], array_slice($productos, 0, 5));
                    $n = count($productos);
                    $this->warnings[] = "Hay {$n} producto(s)/servicio(s) cuya categoría, marca o tipo de producción no tiene la cuenta de Ventas con Factura "
                        . '(y no hay cuenta General): sus facturas no generarán asiento. ' . implode(' · ', $nombres)
                        . ($n > 5 ? ' · y ' . ($n - 5) . ' más' : '') . '. '
                        . 'Configure la cuenta en Configuración Contable (Ventas con Factura) en la regla de su categoría, marca o tipo de producción.';
                }
            }
        } catch (\Throwable $e) {
            // Catálogo sin migrar: omitir sin romper la sincronización.
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
            // Documentos de un módulo que la empresa no contabiliza: su costo pendiente no se avisa.
            $interruptor = ContabilidadInterruptorService::crear();
            $clavePorTipo = [
                'factura_venta'      => 'facturas_venta',
                'recibo_venta'       => 'recibos_venta',
                'nota_credito_venta' => 'notas_credito',
            ];
            $rows = array_filter(
                $rows,
                static fn(array $r): bool => !isset($clavePorTipo[$r['tipo_documento']])
                    || $interruptor->contabiliza($idEmpresa, $clavePorTipo[$r['tipo_documento']])
            );

            $motivos = [
                'cuenta_no_configurada'         =>'falta configurar la cuenta de Costo de Ventas y/o Inventario (a nivel General, o por Cliente/Producto/Categoría/Marca)',
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
        // La empresa decidió no contabilizar consignaciones: que no tengan asiento es lo esperado.
        if (!ContabilidadInterruptorService::crear()->contabiliza($idEmpresa, 'consignaciones')) {
            return;
        }
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
    private function sincronizarModulo(\PDO $db, string $sql, array $params, callable $serviceFactory, string $nombreModulo, string $dondeConfigurar = 'Asientos Programados', ?string $tablaVerif = null, string $colAsiento = 'id_asiento_contable', array $colsDoc = [], string $clave = ''): void
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
        // (qué cuenta falta) y los documentos afectados quedan en $detalle (ya no se muestra)
        // y queda en el log del servidor por si hace falta revisar fuera del navegador.
        $totalConProblema = 0;
        foreach ($errores as $motivo => $idsFallidos) {
            $n = count($idsFallidos);
            $totalConProblema += $n;
            $docs = $this->listarDocumentos($idsFallidos, $numeros);
            $this->detalle[] = "{$nombreModulo} — {$n} asiento(s): {$motivo} (documento(s): {$docs})";
            $this->registrarAccion($db, $clave, $nombreModulo, $motivo, $idsFallidos, $numeros);
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
                $this->registrarAccion($db, $clave, $nombreModulo, $motivo, $idsMotivo, $numeros);
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
            'ingresos_cabecera' => ['tabla' => 'ingresos_pagos', 'col' => 'id_ingreso', 'colForma' => 'id_forma_cobro', 'flujo' => 'cobro',  'doc' => 'El ingreso'],
            'egresos_cabecera'  => ['tabla' => 'egresos_pagos',  'col' => 'id_egreso',  'colForma' => 'id_forma_pago',  'flujo' => 'pago',   'doc' => 'El egreso'],
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
                           SUM(CASE WHEN NOT ({$condVigente}) THEN 1 ELSE 0 END) AS anulados,
                           -- Pagos vigentes cuya forma de cobro/pago ya no existe: lineasFormas()
                           -- los cruza con INNER JOIN y los descarta sin avisar.
                           SUM(CASE WHEN {$condVigente} AND p.monto > 0
                                     AND NOT EXISTS (SELECT 1 FROM empresa_formas_pago f WHERE f.id = p.{$cfg['colForma']})
                                    THEN 1 ELSE 0 END) AS sin_forma
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
            if ((int) ($f['sin_forma'] ?? 0) > 0) {
                $motivos[$id] = "{$cfg['doc']} tiene un {$cfg['flujo']} con una forma de {$cfg['flujo']} que ya no existe: "
                              . "edítelo y elija la forma de {$cfg['flujo']} correcta.";
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

    /** Notas informativas de la última corrida (no son errores ni pendientes). */
    public function getInfo(): array
    {
        return $this->info;
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
     * Detalle real de cada motivo (qué cuenta falta, qué documentos), uno por línea. La UI
     * ya no lo muestra (solo la lista de acciones); queda en el log del servidor para soporte.
     */
    public function getDetalle(): array
    {
        return $this->detalle;
    }

    /**
     * Acciones a realizar (ver $acciones): [{clave, texto, textoGenerico, tipo, seccion, dependeDe}]. `tipo` y
     * `seccion` arman el enlace a Configuración Contable (se pasan por sesión, la URL queda limpia); `tipo` vacío = sin
     * enlace. `dependeDe` es el módulo del que depende (ej. un egreso que paga compras sin asiento):
     * si ese módulo también quedó con pendientes, la acción sobra y la UI la convierte en nota.
     */
    public function getAcciones(): array
    {
        return array_values($this->acciones);
    }

    /**
     * Mensaje corto que ve el usuario: QUÉ configurar, sin cifras (ej. "Para
     * generar los asientos que faltan: Falta configurar la Cuenta por Pagar en algunos proveedores con
     * cuentas propias (Adquisiciones de Compras); Configure las cuentas contables de Nómina.").
     * El detalle por documento sigue en getDetalle() (y en el log del servidor) para soporte.
     * La UI con barra de progreso arma lo mismo desde getAcciones() — ver asientos_pendientes.js.
     */
    public function getResumenMensaje(): ?string
    {
        $lineas = [];
        foreach ($this->acciones as $a) {
            if ($a['dependeDe'] !== null && isset($this->resumenPorModulo[$a['dependeDe']])) {
                continue;
            }
            // Qué documentos / de quién / qué cuentas (hasta 3 lotes; el resto se cuenta).
            $det   = $a['detalles'] ?? [];
            $extra = '';
            if ($det) {
                $resto = count($det) - min(count($det), 3);
                $extra = ' (' . implode('; ', array_slice($det, 0, 3)) . ($resto > 0 ? "; y {$resto} grupo(s) más" : '') . ')';
            }
            $lineas[] = $a['texto'] . $extra;
        }
        $pendientes = $this->getPendientes();
        $cierre = $pendientes > 0 ? " Quedan pendientes {$pendientes} asiento(s) por generar." : '';
        if (!$lineas) {
            return $cierre !== '' ? ltrim($cierre) : null;
        }
        return 'Para generar los asientos que faltan: ' . implode('; ', $lineas) . '.' . $cierre;
    }

    /**
     * Asientos que quedaron por generar y que SÍ se deben generar: solo módulos encendidos (los
     * apagados ni se revisan, ver construirTrabajos()) y sin contar los documentos que no llevan
     * asiento ($noGenerables).
     */
    public function getPendientes(): int
    {
        return max(0, array_sum($this->resumenPorModulo) - $this->noGenerables);
    }

    /** ¿El usuario puede abrir Configuración Contable? Si no, el aviso no le muestra enlaces. */
    private function puedeConfigurar(): bool
    {
        try {
            return \App\Helpers\Permisos::puedeVer('modulos/configuracion-contable');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Traduce UN motivo de fallo (texto de la excepción o del diagnóstico) a la acción que el
     * usuario tiene que hacer, agrupada por sección de Configuración Contable. Un mismo motivo
     * puede traer varias causas concatenadas (AsientoBuilderService::verificarCuadre() une todas
     * las cuentas faltantes en una sola excepción), por eso cada regla se evalúa por separado y
     * solo se cae a la acción genérica del módulo si ninguna coincidió.
     */
    private function registrarAccion(\PDO $db, string $clave, string $nombreModulo, string $motivo, array $ids, array $numeros = []): void
    {
        // Detalle concreto de este lote (qué documentos, de quién y qué cuentas faltan): se adjunta
        // a cada acción que registre registrarAccionInterna() — ver agregarAccion()/fijarAccion().
        $this->detalleActual = $this->detalleDeLote($clave, $nombreModulo, $motivo, $ids, $numeros);
        try {
            $this->registrarAccionInterna($db, $clave, $nombreModulo, $motivo, $ids);
        } finally {
            $this->detalleActual = null;
        }
    }

    /**
     * Línea de detalle de un lote de documentos que no se pudieron contabilizar por el mismo motivo:
     * "Facturas de Compra 001-001-000000123, 001-001-000000456 · proveedores: ACME S.A., XYZ —
     * falta: «Subtotal factura de compras», «IVA compras tarifa 15%»". Es lo que permite al usuario
     * saber EXACTAMENTE qué configurar (el texto de la acción solo dice la sección).
     */
    private function detalleDeLote(string $clave, string $nombreModulo, string $motivo, array $ids, array $numeros): ?string
    {
        if (!$ids) {
            return null;
        }
        $linea = $nombreModulo . ' ' . $this->listarDocumentos($ids, $numeros);

        $area = self::AREAS[$clave] ?? null;
        if (!empty($area['entidad'])) {
            [$tipoRef, $tablaDoc, $colEntidad] = $area['entidad'];
            $nombres = array_values(array_unique(array_filter(
                (new \App\repositories\modulos\AsientoProgramadoRepository())
                    ->getNombresEntidadPorDocumento($tablaDoc, $colEntidad, $tipoRef, $ids)
            )));
            if ($nombres) {
                $resto = count($nombres) - min(count($nombres), 4);
                $linea .= ' · ' . ($tipoRef === 'proveedor' ? 'proveedor' : 'cliente') . (count($nombres) > 1 ? 'es' : '')
                        . ': ' . implode(', ', array_slice($nombres, 0, 4)) . ($resto > 0 ? " y {$resto} más" : '');
            }
        }

        $faltan = self::conceptosFaltantes($motivo);
        if ($faltan) {
            $linea .= ' — falta: ' . implode(', ', array_map(fn($c) => '«' . $c . '»', $faltan));
        }
        return $linea;
    }

    /**
     * Nombres de las cuentas/conceptos que el motivo de fallo dice que faltan: lo que va entre
     * «comillas» (AsientoBuilderService::registrarFaltante*) y las listas "Falta asignar la cuenta
     * contable de: A, B." (ensamblado de compras/ventas) y "Configure las cuentas de nómina en
     * Configuración Contable: A, B." (RolAsientoService).
     *
     * @return string[]
     */
    private static function conceptosFaltantes(string $motivo): array
    {
        $out = [];
        // La coletilla "Configúrela en Contabilidad → Configuración contable, concepto «compras»" dice
        // DÓNDE configurar, no qué falta: se quita para que «compras» no aparezca como cuenta.
        $motivo = (string) preg_replace('/Config[úu]rela en .*$/u', '', $motivo);
        if (preg_match_all('/«([^»]+)»/u', $motivo, $mm)) {
            foreach ($mm[1] as $c) {
                $out[] = trim($c);
            }
        }
        foreach (['/cuenta contable de:\s*(.+?)\.(?:\s|$)/u', '/cuentas de nómina en Configuración Contable:\s*(.+?)\.?$/u'] as $re) {
            if (preg_match($re, $motivo, $m1)) {
                foreach (explode(',', $m1[1]) as $c) {
                    $c = trim($c);
                    if ($c !== '') {
                        $out[] = $c;
                    }
                }
            }
        }
        return array_slice(array_values(array_unique($out)), 0, 8);
    }

    /** Adjunta a una acción la línea de detalle del lote en curso (sin repetir). */
    private function adjuntarDetalle(string $clave): void
    {
        if ($this->detalleActual === null || !isset($this->acciones[$clave])) {
            return;
        }
        $this->acciones[$clave]['detalles'] ??= [];
        if (!in_array($this->detalleActual, $this->acciones[$clave]['detalles'], true)) {
            $this->acciones[$clave]['detalles'][] = $this->detalleActual;
        }
    }

    private function registrarAccionInterna(\PDO $db, string $clave, string $nombreModulo, string $motivo, array $ids): void
    {
        $m    = mb_strtolower($motivo, 'UTF-8');
        $area = self::AREAS[$clave] ?? null;

        // Sin valor que contabilizar (egreso con todos los cheques anulados, formas en 0, etc.):
        // no hay nada que configurar, así que no es una acción sino una nota informativa.
        if (str_contains($m, 'que contabilizar')) {
            $nota = "Algunos documentos de {$nombreModulo} no tienen valor que contabilizar "
                  . '(sin formas de cobro/pago vigentes o en cero): no requieren configuración.';
            if (!in_array($nota, $this->info, true)) {
                $this->info[] = $nota;
            }
            $this->noGenerables += count($ids);
            return;
        }

        // Pago con una forma de pago que ya no existe: se corrige en el documento, no en
        // Configuración Contable (ver motivosSinAsiento()).
        if (str_contains($m, 'que ya no existe')) {
            $this->agregarAccion('', '', "Algunos {$nombreModulo} usan una forma de cobro/pago que ya no existe: edítelos y elija la forma correcta");
            return;
        }

        // El documento quedó sin asiento y ni el service ni el diagnóstico dijeron por qué (el
        // builder devolvió un asiento vacío sin lanzar excepción). No es un error del sistema:
        // falta un dato que el asiento necesita. Se dice eso, no "error inesperado".
        if (trim($m) === '') {
            $this->agregarAccion('', '', "Algunos {$nombreModulo} quedaron sin asiento porque les falta un dato que el asiento necesita "
                . '(revise que tengan valores y formas de cobro/pago); si todo está completo, comuníquese con soporte');
            return;
        }

        $coincidio = false;

        // Cobro/pago de documentos que todavía no tienen su propio asiento: se arregla al
        // configurar ESE módulo. Si ese módulo también quedó pendiente en esta corrida, la acción
        // sobra (dependeDe) y el aviso solo lo menciona como nota.
        //
        // Los módulos apagados en «Módulos que contabilizan» ya no llegan aquí (construirTrabajos()
        // los filtra), pero sí puede llegar un cobro/pago de un módulo encendido que depende de uno
        // apagado. No se pide configurar un módulo que la empresa decidió no contabilizar: queda
        // como nota informativa y el motivo exacto en el log del servidor.
        $dependencias = [
            'genere primero los de facturas de compra' => ['Facturas de Compra', 'adquisiciones_compras', 'la Cuenta por Pagar', 'Adquisiciones de Compras', ['compras', 'liquidaciones_compra']],
            'genere primero los de facturas de venta'  => ['Facturas de Venta', 'ventas_factura', 'la Cuenta por Cobrar', 'Ventas con Factura', ['facturas_venta']],
            'los recibos que cobra'                    => ['Recibos de Venta', 'recibos_venta', 'la Cuenta por Cobrar', 'Recibos de Venta', ['recibos_venta']],
        ];
        foreach ($dependencias as $patron => [$modDep, $tipoDep, $que, $nombreDep, $clavesDep]) {
            if (str_contains($m, $patron)) {
                $coincidio = true;
                if (!$this->algunoContabiliza($clavesDep)) {
                    $nota = "Algunos documentos de {$nombreModulo} cobran o pagan {$modDep}, un módulo que "
                          . 'esta empresa no contabiliza, por eso no se genera su asiento.';
                    if (!in_array($nota, $this->info, true)) {
                        $this->info[] = $nota;
                    }
                    $this->noGenerables += count($ids);
                    return; // no se generará mientras el módulo siga apagado: nada más que pedir
                }
                $this->agregarAccion(
                    $tipoDep, 'general', "Falta configurar {$que} en {$nombreDep}",
                    "Configure las cuentas contables de {$nombreDep}", $modDep
                );
            }
        }

        if (str_contains($m, 'la forma de cobro')) {
            $this->accionFormaSinCuenta('cobro', $motivo);
            $coincidio = true;
        }
        if (str_contains($m, 'la forma de pago')) {
            $this->accionFormaSinCuenta('pago', $motivo);
            $coincidio = true;
        }
        // Concepto (opción) de Ingresos/Egresos sin cuenta. Si está inactivo, el motivo manda a
        // «Opciones de Ingreso/Egreso» en vez de a Configuración Contable (ahí no aparece).
        if (str_contains($m, 'no tiene cuenta contable asignada')
            && (str_contains($m, 'ingresos y egresos') || str_contains($m, 'opciones de ingreso/egreso'))) {
            $this->accionConceptoSinCuenta($clave === 'egresos', $motivo);
            $coincidio = true;
        }
        // Contrapartida de una retención: la cartera no se configura en Retenciones sino en la
        // sección de Ventas / Compras (AsientoBuilderService::generarAsientoRetencionVenta/Compra).
        if (str_contains($m, 'contrapartida de la retención')) {
            if (str_contains($m, 'por cobrar')) {
                $this->agregarAccion('ventas_factura', 'general', 'Falta configurar la Cuenta por Cobrar en Ventas con Factura', 'Configure las cuentas contables de Ventas con Factura');
            } else {
                $this->agregarAccion('adquisiciones_compras', 'general', 'Falta configurar la Cuenta por Pagar en Adquisiciones de Compras', 'Configure las cuentas contables de Adquisiciones de Compras');
            }
            $coincidio = true;
        }
        // Código fuera del catálogo del SRI: no se le puede asignar cuenta (Configuración Contable
        // lo muestra marcado y sin campo). Se corrige en el documento, así que va sin enlace.
        if (str_contains($m, 'no existe en el catálogo de retenciones')) {
            $this->agregarAccion('', '', "Algunas {$nombreModulo} usan un código de retención que no existe en el catálogo del SRI: corrija el código en esas retenciones");
            $coincidio = true;
        }
        if (str_contains($m, 'el código de retención') && str_contains($m, 'no tiene cuenta contable')) {
            $esCompra = ($clave === 'retenciones_compra');
            $this->agregarAccion(
                $esCompra ? 'retenciones_compra' : 'retenciones_venta',
                'general',
                'Algunos códigos de retención no tienen cuenta contable (Retenciones en ' . ($esCompra ? 'Compra' : 'Venta') . ')'
            );
            $coincidio = true;
        }
        if ($clave !== 'roles_pago' && (str_contains($m, 'sueldos por pagar') || str_contains($m, 'cuentas de nómina'))) {
            $this->agregarAccion('nomina', 'general', 'Configure las cuentas contables de Nómina');
            $coincidio = true;
        }
        if ($coincidio) {
            return;
        }

        // Un error que no habla de cuentas ni de configuración (período cerrado, error de base de
        // datos…) no se disfraza de "configure las cuentas": se avisa sin enlace. El motivo exacto
        // queda en el log del servidor (error_log de sincronizarModulo) para soporte.
        if (str_contains($m, 'período') && str_contains($m, 'cerrado')) {
            $this->agregarAccion('', '', "Algunos documentos de {$nombreModulo} son de un período contable cerrado: reábralo si deben contabilizarse");
            return;
        }
        // Descuadre por encima del tope de redondeo con TODAS las cuentas asignadas
        // (AsientoBuilderService::aplicarAjusteRedondeo, rama sin $reglasSinCuenta): el importe
        // total del documento no es subtotal + IVA (ICE u otro impuesto que el asiento no
        // contempla, totales inconsistentes del comprobante). No es configuración: mandar al
        // usuario a "configurar cuentas en proveedores con cuentas propias" (que ya están) lo
        // hacía perseguir un problema inexistente. Se dice lo que es, sin enlace.
        if (str_contains($m, 'supera el máximo de ajuste')) {
            // En compras el comprobante electrónico no se puede corregir: la pestaña deja
            // registrar el asiento a mano (ComprasService::registrarAsientoManual).
            $comoSeResuelve = $clave === 'compras'
                ? 'Abra el documento → pestaña Asiento contable: si es electrónico, registre ahí el asiento a mano; si es físico, corrija sus totales'
                : 'Abra el documento → pestaña Asiento contable para ver el detalle exacto';
            $this->agregarAccion('', '', "Algunos asientos de {$nombreModulo} no cuadran aunque las cuentas estén configuradas: "
                . 'el importe total no es subtotal + IVA (ICE u otro impuesto, o totales del comprobante inconsistentes). '
                . $comoSeResuelve);
            return;
        }
        // Asiento sin ninguna línea con valor con las cuentas ya asignadas (ensamblarAdquisicion):
        // el documento no tiene detalle, o lo tiene en cero. Tampoco es configuración.
        if (str_contains($m, 'el detalle está vacío o en cero')) {
            $this->agregarAccion('', '', "Algunos documentos de {$nombreModulo} no tienen líneas con valor (detalle vacío o en cero): "
                . 'revise el documento; si está bien, comuníquese con soporte');
            return;
        }
        // "El asiento no está cuadrado" / "no cuadra" también es configuración: el builder omite la
        // línea cuya cuenta no encontró y el asiento queda cojo. Cae a la acción de la sección.
        if (!str_contains($m, 'cuenta') && !str_contains($m, 'configur') && !str_contains($m, 'cuadr')) {
            // Los mensajes de negocio (asiento migrado, documento sin datos, etc.) se entienden
            // solos: se muestran tal cual. Solo lo técnico (errores de base de datos, clases PHP)
            // se reemplaza por "comuníquese con soporte"; el texto real queda en el log.
            $esTecnico = preg_match('/sqlstate|pdoexception|exception|error de sintaxis|undefined|stack trace|\.php/i', $motivo)
                || mb_strlen($motivo) > 220;
            $this->agregarAccion('', '', $esTecnico
                ? "Algunos asientos de {$nombreModulo} no se pudieron generar por un error técnico: comuníquese con soporte"
                : "Algunos asientos de {$nombreModulo} no se pudieron generar: " . rtrim($motivo, ". "));
            return;
        }

        if ($area === null) {
            $this->agregarAccion('', '', "Revise la configuración contable de {$nombreModulo}");
            return;
        }

        // Solo en ventas/compras vale la pena nombrar la cartera: es la cuenta que el usuario
        // reconoce y la que más falta. En el resto el texto genérico de la sección basta.
        $concepto = null;
        if (in_array($area['tipo'], ['ventas_factura', 'recibos_venta', 'adquisiciones_compras'], true)) {
            if (str_contains($m, 'por pagar')) {
                $concepto = 'la Cuenta por Pagar';
            } elseif (str_contains($m, 'por cobrar')) {
                $concepto = 'la Cuenta por Cobrar';
            }
        }

        // Cascada "la entidad manda": si el cliente/proveedor del documento tiene cuentas propias,
        // el hueco está en SU configuración (o en la General que la complementa), no solo en la
        // General — se dice "en algunos proveedores" y el enlace abre esa sección.
        $conReglas = 0;
        if (!empty($area['entidad'])) {
            [$tipoRef, $tablaDoc, $colEntidad] = $area['entidad'];
            $conReglas = (new \App\repositories\modulos\AsientoProgramadoRepository())
                ->contarDocumentosConEntidadConReglas($tablaDoc, $colEntidad, $tipoRef, $area['tipo'], $ids);
            if ($conReglas > 0) {
                $plural = $tipoRef === 'proveedor' ? 'proveedores' : 'clientes';
                $this->agregarAccion(
                    $area['tipo'],
                    $tipoRef,
                    'Falta configurar ' . ($concepto ?? 'algunas cuentas contables') . " en algunos {$plural} con cuentas propias ({$area['nombre']})",
                    "Falta configurar algunas cuentas contables en algunos {$plural} con cuentas propias ({$area['nombre']})"
                );
            }
        }
        if ($conReglas < count($ids)) {
            $generico = "Configure las cuentas contables de {$area['nombre']}";
            $this->agregarAccion(
                $area['tipo'],
                $area['seccion'] ?? 'general',
                $concepto !== null ? "Falta configurar {$concepto} en {$area['nombre']}" : $generico,
                $generico
            );
        }
    }

    /** ¿Al menos uno de estos módulos genera asientos en la empresa de la corrida? */
    private function algunoContabiliza(array $claves): bool
    {
        if ($this->idEmpresa <= 0) {
            return true;
        }
        $interruptor = ContabilidadInterruptorService::crear();
        foreach ($claves as $c) {
            if ($interruptor->contabiliza($this->idEmpresa, $c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Acción «formas de cobro/pago sin cuenta» NOMBRANDO las formas, que toma del motivo
     * (AsientoBuilderService::lineasFormas(): «La forma de pago «X» (inactiva) no tiene cuenta…»;
     * Conciliación de Tarjetas usa comillas rectas). Son formas que un documento YA usa.
     */
    private function accionFormaSinCuenta(string $flujo, string $motivo): void
    {
        if (preg_match_all('/forma de ' . $flujo . '(?: destino)? [«"]([^»"]+)[»"]( \(inactiva\))?/iu', $motivo, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $this->formasSinCuenta[$flujo][trim($x[1])] = !empty($x[2]);
            }
        }
        $this->accionesSinCuenta(
            $this->formasSinCuenta[$flujo],
            ['la forma de ' . $flujo, 'las formas de ' . $flujo, 'inactiva', 'inactivas', 'la', 'las'],
            'cobros_pagos', $flujo === 'cobro' ? 'cobros' : 'pagos', 'Cobros y Pagos',
            'Formas de Cobros y Pagos (editar la forma → Cuenta Contable)',
            "Algunas formas de {$flujo} no tienen cuenta contable (Cobros y Pagos)"
        );
    }

    /**
     * Igual que accionFormaSinCuenta() para los conceptos (opciones) de Ingresos y Egresos, que
     * AsientoBuilderService::registrarFaltanteContrapartida() nombra: «El concepto «X» (inactivo)…».
     */
    private function accionConceptoSinCuenta(bool $esEgreso, string $motivo): void
    {
        $flujo = $esEgreso ? 'egreso' : 'ingreso';
        if (preg_match_all('/concepto [«"]([^»"]+)[»"]( \(inactivo\))? no tiene cuenta/iu', $motivo, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $this->conceptosSinCuenta[$flujo][trim($x[1])] = !empty($x[2]);
            }
        }
        $plural = $esEgreso ? 'egresos' : 'ingresos';
        $this->accionesSinCuenta(
            $this->conceptosSinCuenta[$flujo],
            ["el concepto de {$plural}", "los conceptos de {$plural}", 'inactivo', 'inactivos', 'lo', 'los'],
            'ingresos_egresos', $plural, 'Ingresos y Egresos',
            'Opciones de Ingreso/Egreso (editar el concepto → Cuenta contable)',
            "Algunos conceptos de {$plural} no tienen cuenta contable (Ingresos y Egresos)"
        );
    }

    /**
     * Arma las líneas del aviso para formas / conceptos sin cuenta, en DOS grupos:
     *  - Activos: enlace a su sección de Configuración Contable (tipo + sección), donde aparecen.
     *  - Inactivos: Configuración Contable solo lista los activos, así que no aparecerían ahí; la
     *    línea dice que se configuran en el módulo propio ($dondeInactivos) — sin enlace — y que
     *    siguen necesitando cuenta porque hay documentos que ya los usan.
     * Cada línea se reescribe al sumarse nombres de otros documentos (una por grupo y flujo).
     *
     * @param array<string,bool> $items  nombre => inactivo
     * @param string[] $txt  [singular, plural, «inactivo» singular, plural, pronombre singular, plural]
     */
    private function accionesSinCuenta(array $items, array $txt, string $tipo, string $seccion,
                                       string $nombreSeccion, string $dondeInactivos, string $generico): void
    {
        [$sing, $plur, $inacS, $inacP, $pronS, $pronP] = $txt;
        $lista = function (array $nombres, string $sufijo) use ($sing, $plur): string {
            $muestra = array_map(fn($n) => '«' . $n . '»', array_slice($nombres, 0, 5));
            $resto = count($nombres) - count($muestra);
            return ucfirst(count($nombres) === 1 ? $sing : $plur) . ' ' . implode(', ', $muestra)
                . ($resto > 0 ? " y {$resto} más" : '')
                . (count($nombres) === 1 ? ' no tiene' : ' no tienen') . ' cuenta contable' . $sufijo;
        };

        $activos   = array_keys(array_filter($items, fn($inactivo) => !$inactivo));
        $inactivos = array_keys(array_filter($items, fn($inactivo) => $inactivo));

        // Sin nombres en el motivo (texto antiguo o sin nombre): la línea genérica de siempre.
        if (!$items) {
            $this->fijarAccion($tipo . '|' . $seccion, $tipo, $seccion, $generico);
            return;
        }
        if ($activos) {
            $this->fijarAccion($tipo . '|' . $seccion, $tipo, $seccion, $lista($activos, " ({$nombreSeccion})"));
        }
        if ($inactivos) {
            $esUno = count($inactivos) === 1;
            $this->fijarAccion(
                'inactivos|' . $tipo . '|' . $seccion, '', '',
                $lista($inactivos, '') . ' y ' . ($esUno ? "está {$inacS}" : "están {$inacP}")
                . ': por eso no ' . ($esUno ? 'aparece' : 'aparecen') . ' en Configuración Contable. '
                . ($esUno ? 'Asígnele' : 'Asígneles') . " la cuenta en {$dondeInactivos}; "
                . 'aunque ' . ($esUno ? "esté {$inacS}" : "estén {$inacP}") . ', hay documentos que ya ' . ($esUno ? $pronS : $pronP) . ' usan'
            );
        }
    }

    /** Crea o reescribe una acción por su clave (líneas que se completan con cada documento). */
    private function fijarAccion(string $clave, string $tipo, string $seccion, string $texto): void
    {
        $this->acciones[$clave] = [
            'clave'         => $clave,
            'texto'         => $texto,
            'textoGenerico' => $texto,
            'tipo'          => $tipo,
            'seccion'       => $seccion,
            'dependeDe'     => null,
            'detalles'      => $this->acciones[$clave]['detalles'] ?? [], // se conservan al reescribir
        ];
        $this->adjuntarDetalle($clave);
    }

    /**
     * Agrega una acción: UNA línea por sección de Configuración Contable (tipo + sección). Si a la
     * misma sección le llegan faltas distintas (ej. la Cuenta por Pagar en unas compras y el IVA en
     * otras), la línea pasa a su texto genérico ("Configure las cuentas contables de …") en vez de
     * repetir la sección. Las acciones sin enlace (tipo vacío) se distinguen por su texto.
     */
    private function agregarAccion(string $tipo, string $seccion, string $texto, ?string $textoGenerico = null, ?string $dependeDe = null): void
    {
        $textoGenerico ??= $texto;
        // Sin enlace (tipo vacío) la línea se agrupa por su texto, ignorando los números: un mismo
        // motivo que cita "asiento #123" y "asiento #456" es una sola línea, no una por documento.
        $clave = $tipo !== '' ? $tipo . '|' . $seccion : '|' . preg_replace('/\d+/', '#', $texto);
        if (isset($this->acciones[$clave])) {
            $previa = &$this->acciones[$clave];
            if ($previa['texto'] !== $texto) {
                $previa['texto'] = $previa['textoGenerico'];
            }
            // Si la misma falta llega también de forma directa, ya no es solo una dependencia.
            if ($dependeDe === null) {
                $previa['dependeDe'] = null;
            }
            unset($previa);
            $this->adjuntarDetalle($clave);
            return;
        }
        $this->acciones[$clave] = [
            'clave'         => $clave,
            'texto'         => $texto,
            'textoGenerico' => $textoGenerico,
            'tipo'          => $tipo,
            'seccion'       => $seccion,
            'dependeDe'     => $dependeDe,
            'detalles'      => [], // qué documentos / de quién / qué cuentas (ver detalleDeLote)
        ];
        $this->adjuntarDetalle($clave);
    }
}
