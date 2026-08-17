<?php

declare(strict_types=1);

namespace App\Services\MigracionMysql;

use App\core\Database;
use PDO;
use Throwable;

/**
 * Migración desde la BD MySQL del sistema anterior hacia el sistema nuevo (PostgreSQL).
 * Fase actual: ANÁLISIS/RESUMEN (solo lectura) — cuenta cuántos registros hay por
 * entidad para la empresa seleccionada, antes de migrar.
 * La transferencia real por entidad se implementa por fases (catálogos → documentos → cobros).
 */
class MigracionMysqlService
{
    /**
     * Entidades migrables: clave => [label, tabla, fecha (columna de fecha o null), tipo].
     * `tipo` (catalogo|documento) ajusta la estimación de tiempo. Todas filtran por ruc_empresa.
     */
    public const ENTIDADES = [
        // Prerequisito de Contabilidad y de la configuración contable: ambas apuntan a cuentas.
        'plan_cuentas'      => ['label' => 'Plan de cuentas',                  'tabla' => 'plan_cuentas',               'fecha' => null,             'tipo' => 'catalogo'],
        'clientes'          => ['label' => 'Clientes',                         'tabla' => 'clientes',                   'fecha' => 'fecha_agregado', 'tipo' => 'catalogo'],
        'productos'         => ['label' => 'Productos y servicios',            'tabla' => 'productos_servicios',        'fecha' => 'fecha_agregado', 'tipo' => 'catalogo'],
        'proveedores'       => ['label' => 'Proveedores',                      'tabla' => 'proveedores',                'fecha' => 'fecha_agregado', 'tipo' => 'catalogo'],
        'vendedores'        => ['label' => 'Vendedores',                       'tabla' => 'vendedores',                 'fecha' => 'fecha_registro', 'tipo' => 'catalogo'],
        'bodegas'           => ['label' => 'Bodegas',                          'tabla' => 'bodega',                     'fecha' => null,             'tipo' => 'catalogo'],
        // La tabla vieja de empleados filtra por id_empresa (id viejo), NO por ruc_empresa; se resuelve
        // vía empresas (LEFT(ruc,10)). Marcado con 'ruc_via_empresa' para el conteo/análisis.
        'empleados'         => ['label' => 'Empleados',                        'tabla' => 'empleados',                  'fecha' => null,             'tipo' => 'catalogo', 'ruc_via_empresa' => true],
        // Formas de cobro/pago (opciones: efectivo, caja chica…) y cuentas bancarias → empresa_formas_pago.
        // DEBEN ir antes de ingresos/egresos: sus pagos enlazan a estas formas.
        'formas_pago'       => ['label' => 'Formas de cobro/pago (efectivo, caja…)', 'tabla' => 'opciones_cobros_pagos', 'fecha' => null,             'tipo' => 'catalogo'],
        'cuentas_bancarias' => ['label' => 'Cuentas bancarias (formas de pago)', 'tabla' => 'cuentas_bancarias',        'fecha' => null,             'tipo' => 'catalogo'],
        'facturas'          => ['label' => 'Facturas de venta',                'tabla' => 'encabezado_factura',         'fecha' => 'fecha_factura',  'tipo' => 'documento'],
        'notas_credito'     => ['label' => 'Notas de crédito',                 'tabla' => 'encabezado_nc',              'fecha' => 'fecha_nc',       'tipo' => 'documento'],
        'retenciones_venta' => ['label' => 'Retenciones en venta',             'tabla' => 'encabezado_retencion_venta', 'fecha' => 'fecha_emision',  'tipo' => 'documento'],
        'retenciones_compra' => ['label' => 'Retenciones en compra',           'tabla' => 'encabezado_retencion',       'fecha' => 'fecha_emision',  'tipo' => 'documento'],
        'recibos'           => ['label' => 'Recibos de venta',                 'tabla' => 'encabezado_recibo',          'fecha' => 'fecha_recibo',   'tipo' => 'documento'],
        'liquidaciones'     => ['label' => 'Liquidaciones de compra',           'tabla' => 'encabezado_liquidacion',     'fecha' => 'fecha_liquidacion', 'tipo' => 'documento'],
        'guias'             => ['label' => 'Guías de remisión',                 'tabla' => 'encabezado_gr',              'fecha' => 'fecha_gr',       'tipo' => 'documento'],
        'compras'           => ['label' => 'Compras',                          'tabla' => 'encabezado_compra',          'fecha' => 'fecha_compra',   'tipo' => 'documento'],
        'ingresos'          => ['label' => 'Cobros (ingresos)',                 'tabla' => 'ingresos_egresos',           'fecha' => 'fecha_ing_egr',  'tipo' => 'documento', 'filtro' => "tipo_ing_egr = 'INGRESO'"],
        'egresos'           => ['label' => 'Pagos (egresos)',                   'tabla' => 'ingresos_egresos',           'fecha' => 'fecha_ing_egr',  'tipo' => 'documento', 'filtro' => "tipo_ing_egr = 'EGRESO'"],
        'contabilidad'      => ['label' => 'Contabilidad (asientos)',            'tabla' => 'encabezado_diario',          'fecha' => 'fecha_asiento',  'tipo' => 'documento'],
        'proformas'         => ['label' => 'Proformas (cotizaciones)',           'tabla' => 'encabezado_proforma',        'fecha' => 'fecha_proforma', 'tipo' => 'documento'],
        'pedidos'           => ['label' => 'Pedidos',                          'tabla' => 'encabezado_pedido',          'fecha' => 'datecreated',    'tipo' => 'documento'],
        'consignaciones'    => ['label' => 'Consignaciones de venta',            'tabla' => 'encabezado_consignacion',    'fecha' => 'fecha_consignacion', 'tipo' => 'documento', 'filtro' => "operacion = 'ENTRADA'"],
        'consignaciones_fact' => ['label' => 'Facturación de consignación',       'tabla' => 'encabezado_consignacion',    'fecha' => 'fecha_consignacion', 'tipo' => 'documento', 'filtro' => "operacion = 'FACTURA'"],
        'consignaciones_ret' => ['label' => 'Retornos de consignación',           'tabla' => 'encabezado_consignacion',    'fecha' => 'fecha_consignacion', 'tipo' => 'documento', 'filtro' => "operacion LIKE 'DEVOL%'"],
        'cambios_producto'  => ['label' => 'Cambios de productos',               'tabla' => 'cambio_productos_facturados', 'fecha' => 'fecha_cambio',  'tipo' => 'documento'],
        // Inventario va al FINAL: es el libro (kardex) que consolida los movimientos de todos los
        // documentos. Al migrarlo después de consignaciones/retornos, sus movimientos migrados se
        // ENLAZAN a esos documentos (referencia_tipo/id) desde la primera pasada. Ver migrarInventario().
        'inventario'        => ['label' => 'Inventario (kardex)',               'tabla' => 'inventarios',                'fecha' => 'fecha_registro', 'tipo' => 'catalogo'],
    ];

    /** Segundos estimados por registro según tipo (aprox., calibrado en pruebas). */
    private const SEG_POR_REG = ['catalogo' => 0.012, 'documento' => 0.04];

    /**
     * Resumen de cuántos registros hay por entidad para la empresa (por RUC base,
     * incluye todos los establecimientos del contribuyente). Solo lectura.
     *
     * @param string[] $entidades  claves a incluir; vacío = todas
     * @return array<string,array{label:string,tabla:string,total:?int,error:?string}>
     */
    public function analizar(string $rucEmpresa, array $entidades = []): array
    {
        $pdo  = LegacyMysqlConnection::get();
        $base = substr(preg_replace('/\D+/', '', $rucEmpresa), 0, 10);

        $out = [];
        foreach (self::ENTIDADES as $key => $def) {
            if (!empty($entidades) && !in_array($key, $entidades, true)) {
                continue;
            }
            $fecha = $def['fecha'] ?? null;
            $fila = ['label' => $def['label'], 'tabla' => $def['tabla'], 'total' => null, 'fecha_min' => null, 'fecha_max' => null, 'est_segundos' => 0, 'error' => null];
            try {
                $sel = "COUNT(*) AS n";
                if ($fecha) {
                    $sel .= ", MIN(CASE WHEN `$fecha` >= '2000-01-01' THEN `$fecha` END) AS fmin, MAX(`$fecha`) AS fmax";
                }
                $whereF = (!empty($def['ruc_via_empresa'])
                        ? "id_empresa IN (SELECT id FROM empresas WHERE LEFT(ruc, 10) = :b)"
                        : "LEFT(ruc_empresa, 10) = :b")
                    . (!empty($def['filtro']) ? " AND " . $def['filtro'] : "");
                $st = $pdo->prepare("SELECT $sel FROM `{$def['tabla']}` WHERE $whereF");
                $st->execute([':b' => $base]);
                $row = $st->fetch();
                $fila['total'] = (int) $row['n'];
                if ($fecha) {
                    $fila['fecha_min'] = self::fechaCorta($row['fmin'] ?? null);
                    $fila['fecha_max'] = self::fechaCorta($row['fmax'] ?? null);
                }
                $rate = self::SEG_POR_REG[$def['tipo'] ?? 'catalogo'] ?? 0.02;
                $fila['est_segundos'] = (int) ceil($fila['total'] * $rate);
            } catch (Throwable $e) {
                $fila['error'] = substr($e->getMessage(), 0, 140);
            }
            $out[$key] = $fila;
        }
        return $out;
    }

    /**
     * Migra una entidad de la empresa (idempotente vía migracion_mysql_map).
     * @return array contadores del proceso
     */
    public function migrar(string $entidad, int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $pg = Database::getConnection();
        // ANTES de cualquier reconciliación/salto por mapa: sana las entradas COLGADAS del mapa
        // (documentos que el mapa dice migrados pero cuya fila destino ya no existe, borrada por
        // fuera). Sin esto, la re-corrida los da por "ya migrados" y NUNCA los re-inserta → el
        // módulo queda vacío aunque el resumen diga que sí los trae. Cubre TODAS las entidades
        // (documentos y catálogos) porque el bug aplica a cualquier reconcile/yaMigradoDoc/$done.
        $this->purgarMapaColgado($pg, $idEmpresa, $entidad);
        // Y REVIVE los documentos que la migración insertó y quedaron ELIMINADOS (soft-delete): al
        // re-migrar deben reaparecer. Sin esto, la reconciliación refresca el ambiente pero deja
        // eliminado=true → el listado (filtra eliminado=false) no los muestra aunque diga "ya estaban".
        $revividos = $this->revivirMigrados($pg, $idEmpresa, $entidad);

        $res = $this->ejecutarMigracion($entidad, $idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
        if ($revividos > 0) { $res['revividos'] = $revividos; }
        return $res;
    }

    /** Despacha la migración de la entidad a su método específico. */
    private function ejecutarMigracion(string $entidad, int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        switch ($entidad) {
            case 'plan_cuentas':
                return $this->migrarPlanCuentasEntidad($idEmpresa, $ruc, $idUsuario);
            case 'clientes':
                return $this->migrarClientes($idEmpresa, $ruc, $idUsuario);
            case 'productos':
                return $this->migrarProductos($idEmpresa, $ruc, $idUsuario);
            case 'proveedores':
                return $this->migrarProveedores($idEmpresa, $ruc, $idUsuario);
            case 'vendedores':
                return $this->migrarVendedores($idEmpresa, $ruc, $idUsuario);
            case 'bodegas':
                return $this->migrarBodegas($idEmpresa, $ruc, $idUsuario);
            case 'empleados':
                return $this->migrarEmpleados($idEmpresa, $ruc, $idUsuario);
            case 'cuentas_bancarias':
                return $this->migrarCuentasBancarias($idEmpresa, $ruc, $idUsuario);
            case 'formas_pago':
                return $this->migrarFormasPago($idEmpresa, $ruc, $idUsuario);
            case 'facturas':
                return $this->migrarFacturas($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'compras':
                return $this->migrarCompras($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'notas_credito':
                return $this->migrarNotasCredito($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'retenciones_compra':
                return $this->migrarRetencionesCompra($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'retenciones_venta':
                return $this->migrarRetencionesVenta($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'recibos':
                return $this->migrarRecibos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'liquidaciones':
                return $this->migrarLiquidaciones($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'guias':
                return $this->migrarGuias($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'ingresos':
                return $this->migrarIngresos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'egresos':
                return $this->migrarEgresos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'inventario':
                return $this->migrarInventario($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'contabilidad':
                return $this->migrarContabilidad($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'proformas':
                return $this->migrarProformas($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'pedidos':
                return $this->migrarPedidos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'consignaciones':
                return $this->migrarConsignaciones($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'consignaciones_fact':
                return $this->migrarConsignacionesDerivado($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta, 'FACTURA');
            case 'consignaciones_ret':
                return $this->migrarConsignacionesDerivado($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta, 'DEVOLUCION');
            case 'cambios_producto':
                return $this->migrarCambiosProducto($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            default:
                return [
                    'entidad' => $entidad, 'total' => 0, 'migrados' => 0, 'vinculados' => 0,
                    'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0, 'no_implementado' => true,
                ];
        }
    }

    /**
     * Mapa de borrado por entidad de DOCUMENTO (no catálogos): cabecera destino + hijos y nietos.
     * 'nietos' = tablas de impuestos que cuelgan del detalle: [tabla, fk, tablaDetalle, fkDetalle].
     * 'hijos'  = tablas que cuelgan de la cabecera: [tabla, fk].
     * Se borran nietos → hijos → cabecera. Las FK cruzadas SET NULL (retenciones ↔ factura/compra)
     * se resuelven solas; las NO ACTION (activos fijos, importaciones, conciliación, transferencias,
     * entregas de consignación) que referencien un doc migrado harían fallar la transacción (rollback
     * limpio con mensaje). Contabilidad e inventario tienen su propio manejador.
     */
    private const REVERT_DOC = [
        'facturas' => ['cab' => 'ventas_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [['ventas_detalle_impuestos', 'id_venta_detalle', 'ventas_detalle', 'id_venta']],
            'hijos'  => [['ventas_detalle', 'id_venta'], ['ventas_pagos', 'id_venta'], ['ventas_adicional', 'id_venta']]],
        'notas_credito' => ['cab' => 'notas_credito_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [['notas_credito_detalle_impuestos', 'id_nota_credito_detalle', 'notas_credito_detalle', 'id_nota_credito']],
            'hijos'  => [['notas_credito_detalle', 'id_nota_credito'], ['notas_credito_adicional', 'id_nota_credito']]],
        'retenciones_venta' => ['cab' => 'retencion_venta_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['retencion_venta_detalle', 'id_retencion']]],
        'retenciones_compra' => ['cab' => 'retencion_compra_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['retencion_compra_detalle', 'id_retencion']]],
        'recibos' => ['cab' => 'recibos_venta_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [['recibos_venta_detalle_impuestos', 'id_recibo_detalle', 'recibos_venta_detalle', 'id_recibo']],
            'hijos'  => [['recibos_venta_detalle', 'id_recibo'], ['recibos_venta_pagos', 'id_recibo'], ['recibos_venta_adicional', 'id_recibo']]],
        'liquidaciones' => ['cab' => 'liquidaciones_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [['liquidaciones_detalle_impuestos', 'id_detalle', 'liquidaciones_detalle', 'id_cabecera']],
            'hijos'  => [['liquidaciones_detalle', 'id_cabecera'], ['liquidaciones_adicional', 'id_cabecera'], ['liquidaciones_pagos', 'id_cabecera']]],
        'guias' => ['cab' => 'guias_remision_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['guias_remision_detalle', 'id_guia_remision'], ['guias_remision_adicional', 'id_guia_remision']]],
        'compras' => ['cab' => 'compras_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [['compras_detalle_impuestos', 'id_compra_detalle', 'compras_detalle', 'id_compra']],
            'hijos'  => [['compras_detalle', 'id_compra'], ['compras_pagos', 'id_compra'], ['compras_adicional', 'id_compra']]],
        'ingresos' => ['cab' => 'ingresos_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['ingresos_detalle', 'id_ingreso'], ['ingresos_pagos', 'id_ingreso']]],
        'egresos' => ['cab' => 'egresos_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['egresos_detalle', 'id_egreso'], ['egresos_pagos', 'id_egreso']]],
        'proformas' => ['cab' => 'proformas_cabecera', 'fecha' => 'fecha_emision',
            'nietos' => [['proformas_detalle_impuestos', 'id_proforma_detalle', 'proformas_detalle', 'id_proforma']],
            'hijos'  => [['proformas_detalle', 'id_proforma'], ['proformas_adicional', 'id_proforma']]],
        'consignaciones' => ['cab' => 'consignaciones_ventas', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['consignaciones_ventas_detalles', 'id_consignacion']]],
        'consignaciones_fact' => ['cab' => 'consignaciones_facturas', 'fecha' => 'fecha_emision',
            'nietos' => [], 'hijos' => [['consignaciones_facturas_detalles', 'id_consignacion_factura']]],
        'consignaciones_ret' => ['cab' => 'retornos_cv', 'fecha' => 'fecha_retorno',
            'nietos' => [], 'hijos' => [['retornos_cv_detalles', 'id_retorno']]],
        'cambios_producto' => ['cab' => 'cambios_producto_cv', 'fecha' => 'fecha_cambio',
            'nietos' => [], 'hijos' => [['cambios_producto_cv_detalles', 'id_cambio']]],
        'pedidos' => ['cab' => 'pedidos_cabecera', 'fecha' => 'fecha_pedido',
            'nietos' => [], 'hijos' => [['pedidos_detalle', 'id_pedido']]],
    ];

    /** Cabecera + columna de fecha de las entidades con manejador especial (para acotar por rango). */
    private const REVERT_ESPECIAL = [
        'contabilidad' => ['cab' => 'asientos_contables_cabecera', 'fecha' => 'fecha_asiento'],
        'inventario'   => ['cab' => 'inventario_kardex',           'fecha' => 'fecha_movimiento'],
    ];

    /** Tabla destino (sistema nuevo) por entidad. Se usa para avisar de registros ya existentes. */
    private const DESTINO_TABLA = [
        'plan_cuentas' => 'plan_cuentas', 'clientes' => 'clientes', 'productos' => 'productos',
        'proveedores' => 'proveedores', 'vendedores' => 'vendedores', 'bodegas' => 'bodegas', 'empleados' => 'empleados',
        'cuentas_bancarias' => 'empresa_formas_pago', 'formas_pago' => 'empresa_formas_pago',
        'facturas' => 'ventas_cabecera', 'notas_credito' => 'notas_credito_cabecera',
        'retenciones_venta' => 'retencion_venta_cabecera', 'retenciones_compra' => 'retencion_compra_cabecera',
        'recibos' => 'recibos_venta_cabecera', 'liquidaciones' => 'liquidaciones_cabecera',
        'guias' => 'guias_remision_cabecera', 'compras' => 'compras_cabecera',
        'ingresos' => 'ingresos_cabecera', 'egresos' => 'egresos_cabecera',
        'inventario' => 'inventario_kardex', 'contabilidad' => 'asientos_contables_cabecera',
        'proformas' => 'proformas_cabecera', 'consignaciones' => 'consignaciones_ventas',
        'consignaciones_fact' => 'consignaciones_facturas', 'consignaciones_ret' => 'retornos_cv',
        'cambios_producto' => 'cambios_producto_cv', 'pedidos' => 'pedidos_cabecera',
    ];

    /**
     * Cuántos registros hay YA en el módulo destino que NO provienen de la migración (nativos:
     * capturados en el sistema nuevo o por otra vía). Sirve para AVISAR antes de migrar que esos
     * documentos podrían duplicarse. La migración deduplica por número/identificación (docExistente),
     * pero este aviso da visibilidad al usuario. Nativo = fila viva (eliminado=false) del módulo cuyo
     * id NO está en el mapa de migración de esa entidad; para contabilidad = asiento con
     * modulo_origen <> 'migracion' (los migrados y sus cierres son 'migracion').
     *
     * @param string[] $entidades
     * @return array<string,array{label:string,nativos:int}>
     */
    public function contarDestinoNoMigrado(array $entidades, int $idEmpresa, ?string $desde = null, ?string $hasta = null): array
    {
        $pg = Database::getConnection();
        [$desde, $hasta] = [self::fechaNz($desde), self::fechaNz($hasta)];
        $out = [];
        foreach ($entidades as $ent) {
            if (!isset(self::DESTINO_TABLA[$ent]) || !isset(self::ENTIDADES[$ent])) { continue; }
            $tabla = self::DESTINO_TABLA[$ent];
            $label = self::ENTIDADES[$ent]['label'] ?? $ent;
            // El rango de fechas solo aplica a documentos (tienen columna de fecha); los catálogos se
            // migran completos, así que su aviso ignora el rango.
            [, $fcol] = $this->cabFecha($ent);
            $castDate = ($fcol === 'fecha_movimiento');
            if ($ent === 'contabilidad') {
                [$cond, $params] = $this->condFecha('fecha_asiento', $desde, $hasta, false);
                $st = $pg->prepare("SELECT COUNT(*) FROM asientos_contables_cabecera WHERE id_empresa = ? AND eliminado = false AND modulo_origen <> 'migracion'" . $cond);
                $st->execute(array_merge([$idEmpresa], $params));
            } else {
                [$cond, $params] = $fcol ? $this->condFecha('t.' . $fcol, $desde, $hasta, $castDate) : ['', []];
                $st = $pg->prepare("SELECT COUNT(*) FROM $tabla t WHERE t.id_empresa = ? AND t.eliminado = false
                    AND NOT EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.id_empresa = t.id_empresa AND m.entidad = ? AND m.id_destino = t.id)" . $cond);
                $st->execute(array_merge([$idEmpresa, $ent], $params));
            }
            $n = (int) $st->fetchColumn();
            if ($n > 0) { $out[$ent] = ['label' => $label, 'nativos' => $n]; }
        }
        return $out;
    }

    /**
     * Guardarraíl: ¿el RUC de $idEmpresa ya tiene datos migrados bajo OTRA fila de `empresas`
     * (mismo RUC, distinto establecimiento)? El origen se filtra por los primeros 10 dígitos del
     * RUC (ver $base en analizar()), sin distinguir establecimiento — así que dos filas de
     * `empresas` con el mismo RUC traen exactamente el mismo histórico del sistema viejo. El
     * anti-reproceso (migracion_mysql_map, docExistente) está scoped por id_empresa, no por RUC,
     * así que migrar el mismo RUC dos veces bajo filas distintas duplica todo el histórico sin
     * que nada lo detecte. Se usa como aviso previo (UI), no bloquea el endpoint de migrar.
     *
     * @return array{id:int,establecimiento:string,nombre:string}|null Datos de la empresa hermana
     *         con migración ya existente, o null si no hay ninguna.
     */
    public function empresaHermanaConMigracion(int $idEmpresa, string $ruc): ?array
    {
        $pg   = Database::getConnection();
        $base = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        if ($base === '') { return null; }

        $st = $pg->prepare(
            "SELECT e.id, e.establecimiento, COALESCE(NULLIF(e.nombre_comercial,''), e.nombre) AS nombre
               FROM empresas e
              WHERE e.id <> :actual
                AND e.eliminado = false
                AND LEFT(regexp_replace(e.ruc, '[^0-9]', '', 'g'), 10) = :base
                AND EXISTS (SELECT 1 FROM migracion_mysql_map m WHERE m.id_empresa = e.id)
              ORDER BY e.establecimiento ASC
              LIMIT 1"
        );
        $st->execute([':actual' => $idEmpresa, ':base' => $base]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Catálogos: NO se eliminan con esta herramienta (se auto-corrigen al re-migrar por reconciliación). */
    private const ELIMINAR_VEDADAS = ['plan_cuentas', 'clientes', 'productos', 'proveedores', 'vendedores', 'bodegas', 'empleados', 'cuentas_bancarias', 'formas_pago'];

    /**
     * Cuántos registros ELIMINARÍA por entidad (para la confirmación previa). Solo cuenta lo que la
     * migración INSERTÓ (vinculado IS NOT TRUE); los vinculados son nativos y no se borran (aunque su
     * fila del mapa sí se limpia al eliminar). Para contabilidad cuenta los asientos 'migracion'
     * (incluye los cierres autogenerados, que no viven en el mapa).
     *
     * @param string[] $entidades
     * @return array<string,array{insertados:int,vinculados:int,label:string}>
     */
    public function contarMigrados(array $entidades, int $idEmpresa, ?string $desde = null, ?string $hasta = null): array
    {
        $pg = Database::getConnection();
        [$desde, $hasta] = [self::fechaNz($desde), self::fechaNz($hasta)];
        $rango = ($desde !== null || $hasta !== null);
        $out = [];
        foreach ($entidades as $ent) {
            if (in_array($ent, self::ELIMINAR_VEDADAS, true) || !isset(self::ENTIDADES[$ent])) { continue; }
            $label = self::ENTIDADES[$ent]['label'] ?? $ent;
            [$cab, $fcol] = $this->cabFecha($ent);

            if ($ent === 'contabilidad') {
                // Asientos migrados (histórico + cierres autogenerados que no viven en el mapa).
                [$cond, $params] = $this->condFecha($fcol, $desde, $hasta, false);
                $sql = "SELECT COUNT(*) FROM asientos_contables_cabecera WHERE id_empresa = ? AND modulo_origen = 'migracion' AND eliminado = false" . $cond;
                $st = $pg->prepare($sql);
                $st->execute(array_merge([$idEmpresa], $params));
                $ins = (int) $st->fetchColumn();
                $vin = 0;
            } elseif ($rango && $cab) {
                // Acotado por fecha: unir el mapa a la cabecera y contar por su fecha.
                [$cond, $params] = $this->condFecha('c.' . $fcol, $desde, $hasta, $fcol === 'fecha_movimiento');
                $st = $pg->prepare("SELECT COUNT(*) FILTER (WHERE m.vinculado IS NOT TRUE) AS ins, COUNT(*) FILTER (WHERE m.vinculado) AS vin
                    FROM migracion_mysql_map m JOIN $cab c ON c.id = m.id_destino
                    WHERE m.id_empresa = ? AND m.entidad = ?" . $cond);
                $st->execute(array_merge([$idEmpresa, $ent], $params));
                $r = $st->fetch(PDO::FETCH_ASSOC);
                $ins = (int) ($r['ins'] ?? 0);
                $vin = (int) ($r['vin'] ?? 0);
            } else {
                $q = $pg->prepare("SELECT COUNT(*) FILTER (WHERE vinculado IS NOT TRUE) AS ins, COUNT(*) FILTER (WHERE vinculado) AS vin FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
                $q->execute([$idEmpresa, $ent]);
                $r = $q->fetch(PDO::FETCH_ASSOC);
                $ins = (int) ($r['ins'] ?? 0);
                $vin = (int) ($r['vin'] ?? 0);
            }
            $out[$ent] = ['insertados' => $ins, 'vinculados' => $vin, 'label' => $label];
        }
        return $out;
    }

    /** Normaliza una fecha 'YYYY-MM-DD' de entrada: vacío → null. */
    private static function fechaNz(?string $f): ?string
    {
        $f = trim((string) $f);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : null;
    }

    /** [tabla cabecera, columna de fecha] de una entidad eliminable (documento o especial). */
    private function cabFecha(string $ent): array
    {
        if (isset(self::REVERT_DOC[$ent]))      { return [self::REVERT_DOC[$ent]['cab'], self::REVERT_DOC[$ent]['fecha']]; }
        if (isset(self::REVERT_ESPECIAL[$ent])) { return [self::REVERT_ESPECIAL[$ent]['cab'], self::REVERT_ESPECIAL[$ent]['fecha']]; }
        return [null, null];
    }

    /**
     * Arma "AND <col> >= ? AND <col> <= ?" con sus parámetros (posicionales). $castDate castea a ::date
     * (para columnas timestamp como fecha_movimiento). Devuelve ['', []] si no hay rango.
     * @return array{0:string,1:array<int,string>}
     */
    private function condFecha(string $col, ?string $desde, ?string $hasta, bool $castDate): array
    {
        $expr = $castDate ? "$col::date" : $col;
        $cond = ''; $params = [];
        if ($desde !== null) { $cond .= " AND $expr >= ?"; $params[] = $desde; }
        if ($hasta !== null) { $cond .= " AND $expr <= ?"; $params[] = $hasta; }
        return [$cond, $params];
    }

    /**
     * Resuelve los ids a borrar (insertados) y los ids cuyo mapa se limpia. Sin rango: [insertados, null]
     * (null = borrar TODO el mapa de la entidad). Con rango: [insertados en rango, todos en rango].
     * @return array{0:int[],1:?int[]}
     */
    private function idsEliminar(PDO $pg, int $idEmpresa, string $entidad, string $cab, string $fcol, ?string $desde, ?string $hasta): array
    {
        if ($desde === null && $hasta === null) {
            $q = $pg->prepare("SELECT id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ? AND vinculado IS NOT TRUE");
            $q->execute([$idEmpresa, $entidad]);
            return [array_values(array_filter(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)))), null];
        }
        [$cond, $params] = $this->condFecha('c.' . $fcol, $desde, $hasta, $fcol === 'fecha_movimiento');
        $base = "FROM migracion_mysql_map m JOIN $cab c ON c.id = m.id_destino WHERE m.id_empresa = ? AND m.entidad = ?" . $cond;
        $qi = $pg->prepare("SELECT m.id_destino $base AND m.vinculado IS NOT TRUE");
        $qi->execute(array_merge([$idEmpresa, $entidad], $params));
        $ins = array_values(array_filter(array_map('intval', $qi->fetchAll(PDO::FETCH_COLUMN))));
        $qa = $pg->prepare("SELECT m.id_destino $base");
        $qa->execute(array_merge([$idEmpresa, $entidad], $params));
        $all = array_values(array_filter(array_map('intval', $qa->fetchAll(PDO::FETCH_COLUMN))));
        return [$ins, $all];
    }

    /**
     * Elimina lo que la migración INSERTÓ para una entidad de documento/contabilidad/inventario, para
     * poder re-migrar y corregir. Borra hijos→cabecera SOLO de los registros insertados por la
     * migración (vinculado IS NOT TRUE); nunca toca registros nativos. Luego limpia TODO el mapa de la
     * entidad (los vinculados se vuelven a detectar por número al re-migrar). Transaccional.
     */
    public function eliminarMigrados(string $entidad, int $idEmpresa, int $idUsuario, ?string $desde = null, ?string $hasta = null): array
    {
        if (in_array($entidad, self::ELIMINAR_VEDADAS, true)) {
            throw new \RuntimeException('Los catálogos no se eliminan aquí: se auto-corrigen al re-migrar.');
        }
        [$desde, $hasta] = [self::fechaNz($desde), self::fechaNz($hasta)];
        $pg = Database::getConnection();
        if ($entidad === 'contabilidad') { return $this->eliminarContabilidadMigrada($idEmpresa, $idUsuario, $pg, $desde, $hasta); }
        if ($entidad === 'inventario')   { return $this->eliminarInventarioMigrado($idEmpresa, $pg, $desde, $hasta); }
        if (!isset(self::REVERT_DOC[$entidad])) {
            throw new \RuntimeException("Entidad no válida para eliminar: $entidad");
        }
        $map = self::REVERT_DOC[$entidad];
        $res = ['entidad' => $entidad, 'cabeceras' => 0, 'hijos' => 0, 'mapa' => 0, 'vinculados_intactos' => 0,
                'rango' => ($desde !== null || $hasta !== null)];

        // ids INSERTADOS a borrar (opcionalmente acotados por la fecha de la cabecera) e ids cuyo mapa
        // se limpia. Los vinculados (nativos) NO se borran; solo se limpia su fila del mapa.
        [$ids, $idsMapa] = $this->idsEliminar($pg, $idEmpresa, $entidad, $map['cab'], $map['fecha'], $desde, $hasta);
        $res['vinculados_intactos'] = (int) $pg->query("SELECT COUNT(*) FROM migracion_mysql_map WHERE id_empresa = " . (int) $idEmpresa . " AND entidad = " . $pg->quote($entidad) . " AND vinculado")->fetchColumn();

        $pg->beginTransaction();
        try {
            if ($ids) {
                $in = implode(',', $ids); // ya casteados a int → seguro para interpolar
                foreach ($map['nietos'] as [$tabla, $fk, $det, $detFk]) {
                    $pg->exec("DELETE FROM $tabla WHERE $fk IN (SELECT id FROM $det WHERE $detFk IN ($in))");
                }
                foreach ($map['hijos'] as [$tabla, $fk]) {
                    $res['hijos'] += (int) $pg->exec("DELETE FROM $tabla WHERE $fk IN ($in)");
                }
                $res['cabeceras'] = (int) $pg->exec("DELETE FROM {$map['cab']} WHERE id IN ($in)");
            }
            $res['mapa'] = $this->limpiarMapa($pg, $idEmpresa, $entidad, $idsMapa);
            $pg->commit();
        } catch (Throwable $e) {
            if ($pg->inTransaction()) { $pg->rollBack(); }
            throw new \RuntimeException("No se pudo eliminar '{$entidad}': " . $e->getMessage(), 0, $e);
        }
        return $res;
    }

    /**
     * Limpia el mapa de una entidad. $ids null = TODO el mapa de la entidad (borrado completo); si es
     * un arreglo = solo esas filas (borrado acotado por fecha; incluye vinculados en rango para que se
     * re-detecten al re-migrar). DEBE ir dentro de la misma transacción del borrado.
     */
    private function limpiarMapa(PDO $pg, int $idEmpresa, string $entidad, ?array $ids): int
    {
        if ($ids === null) {
            $qd = $pg->prepare("DELETE FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
            $qd->execute([$idEmpresa, $entidad]);
            return $qd->rowCount();
        }
        if (!$ids) { return 0; }
        return (int) $pg->exec("DELETE FROM migracion_mysql_map WHERE id_empresa = " . (int) $idEmpresa
            . " AND entidad = " . $pg->quote($entidad) . " AND id_destino IN (" . implode(',', $ids) . ")");
    }

    /**
     * Borra los asientos migrados (modulo_origen='migracion' → incluye el histórico y los cierres
     * autogenerados) + su detalle, desengancha id_asiento_contable de los documentos y limpia el mapa.
     * NO toca el plan de cuentas (es catálogo) ni los asientos nativos. Réplica de reset_contabilidad_migrada.sql.
     */
    private function eliminarContabilidadMigrada(int $idEmpresa, int $idUsuario, PDO $pg, ?string $desde = null, ?string $hasta = null): array
    {
        $res = ['entidad' => 'contabilidad', 'cabeceras' => 0, 'hijos' => 0, 'mapa' => 0, 'vinculados_intactos' => 0,
                'rango' => ($desde !== null || $hasta !== null)];
        // Tablas que guardan un id de asiento; se desenganchan por el id del asiento migrado.
        $docs = [
            ['compras_cabecera', 'id_asiento_contable'], ['ventas_cabecera', 'id_asiento_contable'],
            ['ingresos_cabecera', 'id_asiento_contable'], ['egresos_cabecera', 'id_asiento_contable'],
            ['retencion_venta_cabecera', 'id_asiento_contable'], ['retencion_compra_cabecera', 'id_asiento_contable'],
            ['notas_credito_cabecera', 'id_asiento_contable'], ['recibos_venta_cabecera', 'id_asiento_contable'],
            ['liquidaciones_cabecera', 'id_asiento_contable'], ['consignaciones_ventas', 'id_asiento_contable'],
            ['cambios_producto_cv', 'id_asiento_contable'], ['retornos_cv', 'id_asiento_contable'],
        ];
        // Asientos migrados (histórico + cierres autogenerados), opcionalmente acotados por fecha.
        [$cond, $params] = $this->condFecha('fecha_asiento', $desde, $hasta, false);
        $q = $pg->prepare("SELECT id FROM asientos_contables_cabecera WHERE id_empresa = ? AND modulo_origen = 'migracion'" . $cond);
        $q->execute(array_merge([$idEmpresa], $params));
        $ids = array_values(array_filter(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN))));

        $pg->beginTransaction();
        try {
            if ($ids) {
                $in = implode(',', $ids);
                foreach ($docs as [$tabla, $col]) {
                    // Solo tablas existentes con esa columna (defensivo).
                    $ok = $pg->query("SELECT to_regclass('public.$tabla')")->fetchColumn();
                    if (!$ok) { continue; }
                    $pg->exec("UPDATE $tabla SET $col = NULL WHERE $col IN ($in)");
                }
                $res['hijos'] = (int) $pg->exec("DELETE FROM asientos_contables_detalle WHERE id_asiento IN ($in)");
                $res['cabeceras'] = (int) $pg->exec("DELETE FROM asientos_contables_cabecera WHERE id IN ($in)");
            }
            // Sin rango: limpiar todo el mapa; con rango: solo el de los asientos borrados.
            $res['mapa'] = $this->limpiarMapa($pg, $idEmpresa, 'contabilidad', ($res['rango'] ? $ids : null));
            $pg->commit();
        } catch (Throwable $e) {
            if ($pg->inTransaction()) { $pg->rollBack(); }
            throw new \RuntimeException('No se pudo eliminar la contabilidad migrada: ' . $e->getMessage(), 0, $e);
        }
        return $res;
    }

    /**
     * Borra el kardex migrado (inventario_kardex insertado por la migración) y su mapa. AVISO: no
     * recalcula saldos corridos; re-migrar inventario los regenera con el saldo sembrado del stock.
     */
    private function eliminarInventarioMigrado(int $idEmpresa, PDO $pg, ?string $desde = null, ?string $hasta = null): array
    {
        $res = ['entidad' => 'inventario', 'cabeceras' => 0, 'hijos' => 0, 'mapa' => 0, 'vinculados_intactos' => 0,
                'rango' => ($desde !== null || $hasta !== null),
                'aviso' => 'Los saldos del kardex no se recalculan al borrar; vuelve a migrar Inventario para regenerarlos.'];
        [$ids, $idsMapa] = $this->idsEliminar($pg, $idEmpresa, 'inventario', 'inventario_kardex', 'fecha_movimiento', $desde, $hasta);

        $pg->beginTransaction();
        try {
            if ($ids) {
                $res['cabeceras'] = (int) $pg->exec("DELETE FROM inventario_kardex WHERE id IN (" . implode(',', $ids) . ")");
            }
            $res['mapa'] = $this->limpiarMapa($pg, $idEmpresa, 'inventario', $idsMapa);
            $pg->commit();
        } catch (Throwable $e) {
            if ($pg->inTransaction()) { $pg->rollBack(); }
            throw new \RuntimeException('No se pudo eliminar el inventario migrado: ' . $e->getMessage(), 0, $e);
        }
        return $res;
    }

    /** Migra los clientes del contribuyente (todos los establecimientos). */
    private function migrarClientes(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'clientes', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'cliente sin identificación (RUC/cédula)', 'errores' => 0];

        // Ya migrados (anti-reproceso)
        $done = [];
        $q = $pg->prepare("SELECT id_origen FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'clientes'");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $o) {
            $done[(string) $o] = true;
        }

        // Buscar SIN filtrar eliminado: la restricción unique_cliente_empresa cubre TODAS las filas
        // (incluidas las soft-deleted); si existe una borrada, hay que enlazar a ella, no insertar.
        $buscar = $pg->prepare("SELECT id FROM clientes WHERE id_empresa = :e AND identificacion = :ident LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, provincia, ciudad, status, created_by)
             VALUES (:e, :u, :nom, :tipo, :ident, :tel, :mail, :dir, :plazo, :prov, :ciu, :status, :cb) RETURNING id"
        );
        $insMap = $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, 'clientes', :o, :d, :cn, :vin, :cb)
             ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );

        $stmt = $mysql->query(
            "SELECT id, nombre, tipo_id, ruc, telefono, email, direccion, plazo, provincia, ciudad, status
               FROM clientes WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base)
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id'];
            if (isset($done[(string) $old])) {
                $res['ya_migrados']++;
                continue;
            }
            $ident = trim((string) $r['ruc']);
            if ($ident === '') {
                $res['omitidos']++;
                continue;
            }
            $nombre = trim((string) $r['nombre']);
            if ($nombre === '') {
                $nombre = $ident;
            }
            $tipo = trim((string) $r['tipo_id']);
            if ($tipo === '') {
                $tipo = self::inferirTipoId($ident);
            }

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $existente = $buscar->fetchColumn();
                if ($existente !== false) {
                    $idDest = (int) $existente;
                    $vin = true;
                    $res['vinculados']++;
                    if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $nombre; }
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':u' => $idUsuario, ':nom' => $nombre, ':tipo' => $tipo, ':ident' => $ident,
                        ':tel' => self::nz($r['telefono']), ':mail' => self::nz($r['email']), ':dir' => self::nz($r['direccion']),
                        ':plazo' => (int) ($r['plazo'] ?? 0), ':prov' => self::nz($r['provincia']), ':ciu' => self::nz($r['ciudad']),
                        ':status' => (int) ($r['status'] ?? 1), ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn();
                    $vin = false;
                    $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) {
                    $pg->rollBack();
                }
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra los productos/servicios del contribuyente. */
    /**
     * Mapa de unidad de medida vieja → nueva. El catálogo viejo (unidad_medida) es GLOBAL con ids
     * propios (p.ej. 17='Unidad'); el nuevo (unidades_medida) es POR EMPRESA con otros ids/códigos
     * (sembrado por CatalogoMedidas). Se casa por NOMBRE y luego ABREVIATURA (normalizados: mayúsculas,
     * sin plural final, sin prefijo "UN "). Todo lo que no case —incluido id 0 (sin unidad)— cae a UNIDAD.
     * Devuelve ['map' => [old_id => [id_medida_new, id_tipo_new]], 'default' => [id, tipo]|null].
     */
    private function mapaUnidades(int $idEmpresa, PDO $mysql, PDO $pg): array
    {
        // Normaliza: sin acentos, mayúsculas, espacios colapsados (para casar "Galones"↔"GALÓN").
        $norm = static function ($s): string {
            $s = strtr((string) $s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U','ñ'=>'N']);
            return strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
        };
        // Ordena es_base primero: si la empresa tiene unidades DUPLICADAS por nombre (catálogos viejos
        // con "UNIDAD" en dos tipos), se prefiere la base (la canónica) al indexar.
        $porNom = []; $porAbre = [];
        foreach ($pg->query("SELECT id, nombre, abreviatura, id_tipo FROM unidades_medida WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false ORDER BY es_base DESC, id ASC") as $u) {
            $par = [(int) $u['id'], (int) $u['id_tipo']];
            $n = $norm($u['nombre']); $a = $norm($u['abreviatura']);
            if ($n !== '' && !isset($porNom[$n]))  { $porNom[$n]  = $par; }
            if ($a !== '' && !isset($porAbre[$a])) { $porAbre[$a] = $par; }
        }
        // Default (id 0 / no casado) = la unidad UNIDAD de la empresa (misma que casa el id 17).
        $default = $porNom['UNIDAD'] ?? null;
        $viejo = [];
        foreach ($mysql->query("SELECT id_medida, nombre_medida, abre_medida FROM unidad_medida") as $v) {
            $viejo[(int) $v['id_medida']] = [$norm($v['nombre_medida']), $norm($v['abre_medida'])];
        }
        $map = [];
        foreach ($viejo as $oid => $par) {
            [$nom, $abre] = $par;
            // exacto por nombre → por abreviatura → singular (quita "S"/"ES": GALONES→GALON, METROS→METRO)
            $hit = $porNom[$nom] ?? $porAbre[$abre] ?? $porNom[preg_replace('/E?S$/', '', $nom)] ?? null;
            if (!$hit) { // sin prefijo "UN "/"UNA " (Un rollo → ROLLO)
                $nom2 = preg_replace('/^UN[A]? /', '', $nom);
                $hit = $porNom[$nom2] ?? $porNom[preg_replace('/E?S$/', '', $nom2)] ?? null;
            }
            $map[$oid] = $hit ?? $default;
        }
        return ['map' => $map, 'default' => $default];
    }

    private function migrarProductos(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'productos', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'producto sin código ni nombre', 'errores' => 0];

        $done = []; // id_origen => ['id' => id_destino, 'vin' => bool]
        $q = $pg->prepare("SELECT id_origen, id_destino, vinculado FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'productos'");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $done[(string) $o['id_origen']] = ['id' => (int) $o['id_destino'], 'vin' => (bool) $o['vinculado']];
        }

        $um = $this->mapaUnidades($idEmpresa, $mysql, $pg); // unidad de medida vieja → nueva (por nombre)

        // Dedup por (id_empresa, codigo) — no hay unique constraint, se controla aquí.
        $buscar = $pg->prepare("SELECT id FROM productos WHERE id_empresa = :e AND codigo = :cod LIMIT 1");
        // Al re-migrar: corrige el IVA y la UNIDAD de medida de los productos que insertó la migración
        // (corridas viejas guardaban el CÓDIGO SRI en tarifa_iva y no traían la unidad). COALESCE en IVA
        // para no borrarlo si esta corrida no lo resuelve.
        $updIva = $pg->prepare("UPDATE productos SET id_medida = ?, id_tipo_medida = ?, tarifa_iva = COALESCE(?, tarifa_iva), updated_at = now(), updated_by = ? WHERE id = ? AND id_empresa = ?");
        $ins = $pg->prepare(
            "INSERT INTO productos (id_empresa, codigo, nombre, codigo_auxiliar, codigo_barras, precio_base, tipo_produccion, tarifa_iva, id_medida, id_tipo_medida, status, inventariable, id_usuario, created_by)
             VALUES (:e, :cod, :nom, :aux, :barras, :precio, :tipo, :iva, :medida, :tipomedida, :status, :inv, :u, :cb) RETURNING id"
        );
        $insMap = $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, 'productos', :o, :d, :cn, :vin, :cb) ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );

        $stmt = $mysql->query(
            "SELECT id, codigo_producto, nombre_producto, codigo_auxiliar, precio_producto, tipo_produccion, tarifa_iva, status, id_unidad_medida
               FROM productos_servicios WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base)
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id'];
            // Unidad de medida: casa la vieja con la nueva de la empresa (id 0/no casado → UNIDAD).
            $oldUm = (int) $r['id_unidad_medida'];
            $par   = ($oldUm > 0 ? ($um['map'][$oldUm] ?? null) : null) ?? $um['default'];
            [$idMedida, $idTipoMed] = $par ?? [null, null];
            if (isset($done[(string) $old])) {
                // Ya migrado: reconciliar IVA y UNIDAD solo si lo INSERTÓ la migración (los 'vinculado' son
                // productos nativos del sistema nuevo y no se tocan).
                if (!$done[(string) $old]['vin']) {
                    $ivaOk = $this->ivaIdPorCodigo($pg, (string) $r['tarifa_iva']);
                    $updIva->execute([$idMedida, $idTipoMed, $ivaOk, $idUsuario, $done[(string) $old]['id'], $idEmpresa]);
                }
                $res['ya_migrados']++;
                continue;
            }
            $codigo = trim((string) $r['codigo_producto']);
            $nombre = trim((string) $r['nombre_producto']);
            if ($codigo === '' && $nombre === '') {
                $res['omitidos']++;
                continue;
            }
            if ($codigo === '') {
                $codigo = 'MIG-' . $old; // sin código: se genera uno estable
            }
            if ($nombre === '') {
                $nombre = $codigo;
            }
            $tipo = trim((string) $r['tipo_produccion']);
            if ($tipo === '') {
                $tipo = '01';
            }
            // IVA por CÓDIGO SRI → id de tarifa_iva (ver ivaIdPorCodigo: nunca por porcentaje).
            $iva = $this->ivaIdPorCodigo($pg, (string) $r['tarifa_iva']);

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':cod' => $codigo]);
                $existente = $buscar->fetchColumn();
                if ($existente !== false) {
                    $idDest = (int) $existente;
                    $vin = true;
                    $res['vinculados']++;
                    if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $codigo . ' · ' . $nombre; }
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':cod' => $codigo, ':nom' => $nombre,
                        ':aux' => trim((string) ($r['codigo_auxiliar'] ?? '')), ':barras' => '',
                        ':precio' => (float) ($r['precio_producto'] ?? 0), ':tipo' => $tipo, ':iva' => $iva,
                        ':medida' => $idMedida, ':tipomedida' => $idTipoMed,
                        ':status' => (int) ($r['status'] ?? 1), ':inv' => $tipo === '01' ? 't' : 'f',
                        ':u' => $idUsuario, ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn();
                    $vin = false;
                    $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($codigo, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = ['id' => $idDest, 'vin' => $vin];
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) {
                    $pg->rollBack();
                }
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra los proveedores del contribuyente. */
    private function migrarProveedores(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'proveedores', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'proveedor sin identificación (RUC/cédula)', 'errores' => 0];

        $done = []; // id_origen => ['id' => id_destino, 'vin' => bool]
        $q = $pg->prepare("SELECT id_origen, id_destino, vinculado FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'proveedores'");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $done[(string) $o['id_origen']] = ['id' => (int) $o['id_destino'], 'vin' => (bool) $o['vinculado']];
        }

        // Dedup por (id_empresa, identificacion) — sin unique constraint, se controla aquí.
        $buscar = $pg->prepare("SELECT id FROM proveedores WHERE id_empresa = :e AND identificacion = :ident LIMIT 1");
        // Al re-migrar: corrige "parte relacionada" de los proveedores que insertó la migración.
        $updRel = $pg->prepare("UPDATE proveedores SET relacionado = false, updated_at = now(), updated_by = ? WHERE id = ? AND id_empresa = ?");
        $ins = $pg->prepare(
            "INSERT INTO proveedores (id_empresa, id_usuario, razon_social, nombre_comercial, tipo_id_proveedor, identificacion, email, direccion, telefono, tipo_empresa, plazo, unidad_tiempo, relacionado, tipo_cta, numero_cta, created_by)
             VALUES (:e, :u, :rs, :nc, :tipo, :ident, :mail, :dir, :tel, :temp, :plazo, :ut, :rel, :tcta, :ncta, :cb) RETURNING id"
        );
        $insMap = $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, 'proveedores', :o, :d, :cn, :vin, :cb) ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );

        $stmt = $mysql->query(
            "SELECT id_proveedor, razon_social, nombre_comercial, tipo_id_proveedor, ruc_proveedor, mail_proveedor, dir_proveedor, telf_proveedor, tipo_empresa, plazo, unidad_tiempo, relacionado, tipo_cta, numero_cta
               FROM proveedores WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base)
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_proveedor'];
            if (isset($done[(string) $old])) {
                // Ya migrado: corregir "parte relacionada" a NO solo si lo INSERTÓ la migración
                // (los 'vinculado' son proveedores nativos del sistema nuevo y no se tocan).
                if (!$done[(string) $old]['vin']) {
                    $updRel->execute([$idUsuario, $done[(string) $old]['id'], $idEmpresa]);
                }
                $res['ya_migrados']++;
                continue;
            }
            $ident = trim((string) $r['ruc_proveedor']);
            if ($ident === '') {
                $res['omitidos']++;
                continue;
            }
            $rs = trim((string) $r['razon_social']);
            if ($rs === '') {
                $rs = trim((string) $r['nombre_comercial']) ?: $ident;
            }
            $tipo = trim((string) $r['tipo_id_proveedor']);
            if ($tipo === '') {
                $tipo = self::inferirTipoId($ident);
            }
            // "Parte relacionada" (concepto tributario SRI): el campo `relacionado` del sistema viejo
            // NO significa eso (48.683 proveedores traen '1'), así que se migra SIEMPRE como NO.
            $rel = 'f';
            $plazo = is_numeric($r['plazo']) ? (int) $r['plazo'] : null;

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $existente = $buscar->fetchColumn();
                if ($existente !== false) {
                    $idDest = (int) $existente;
                    $vin = true;
                    $res['vinculados']++;
                    if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $rs; }
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':u' => $idUsuario, ':rs' => $rs, ':nc' => self::nz($r['nombre_comercial']),
                        ':tipo' => $tipo, ':ident' => $ident, ':mail' => self::nz($r['mail_proveedor']),
                        ':dir' => self::nz($r['dir_proveedor']), ':tel' => self::nz($r['telf_proveedor']),
                        ':temp' => self::nz($r['tipo_empresa']), ':plazo' => $plazo, ':ut' => self::nz($r['unidad_tiempo']) ?? 'Días',
                        ':rel' => $rel, ':tcta' => self::nz($r['tipo_cta']), ':ncta' => self::nz($r['numero_cta']), ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn();
                    $vin = false;
                    $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = ['id' => $idDest, 'vin' => $vin];
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) {
                    $pg->rollBack();
                }
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra los vendedores del contribuyente. */
    private function migrarVendedores(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'vendedores', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done = $this->idsMigrados($pg, $idEmpresa, 'vendedores');
        $buscar = $pg->prepare("SELECT id FROM vendedores WHERE id_empresa = :e AND identificacion = :ident LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO vendedores (id_empresa, id_usuario, identificacion, nombre, correo, telefono, direccion, created_by)
             VALUES (:e, :u, :ident, :nom, :cor, :tel, :dir, :cb) RETURNING id"
        );
        $insMap = $this->stmtMap($pg,'vendedores');

        $stmt = $mysql->query("SELECT id_vendedor, numero_id, nombre, correo, telefono, direccion FROM vendedores WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_vendedor'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $ident = trim((string) $r['numero_id']) ?: ('V-' . $old);
            $nombre = trim((string) $r['nombre']) ?: $ident;
            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $ex = $buscar->fetchColumn();
                if ($ex !== false) { $idDest = (int) $ex; $vin = true; $res['vinculados']++; if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $nombre; } }
                else {
                    $ins->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':ident' => $ident, ':nom' => $nombre,
                        ':cor' => self::nz($r['correo']), ':tel' => self::nz($r['telefono']), ':dir' => self::nz($r['direccion']), ':cb' => $idUsuario]);
                    $idDest = (int) $ins->fetchColumn(); $vin = false; $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) $pg->rollBack();
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra las bodegas del contribuyente. */
    private function migrarBodegas(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'bodegas', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'bodega sin nombre', 'errores' => 0];

        $done = $this->idsMigrados($pg, $idEmpresa, 'bodegas');
        // Dedup INSENSIBLE a mayúsculas y espacios: así "Central" no crea un duplicado de "CENTRAL".
        // Prefiere la bodega viva (eliminado=false ordena primero) con el id más bajo.
        $buscar = $pg->prepare("SELECT id FROM bodegas WHERE id_empresa = :e AND UPPER(TRIM(nombre)) = UPPER(TRIM(:nom)) ORDER BY eliminado, id LIMIT 1");
        $ins = $pg->prepare("INSERT INTO bodegas (id_empresa, id_usuario, nombre, created_by) VALUES (:e, :u, :nom, :cb) RETURNING id");
        $insMap = $this->stmtMap($pg,'bodegas');

        $stmt = $mysql->query("SELECT id_bodega, nombre_bodega FROM bodega WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_bodega'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $nombre = trim((string) $r['nombre_bodega']);
            if ($nombre === '') { $res['omitidos']++; continue; }
            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':nom' => $nombre]);
                $ex = $buscar->fetchColumn();
                if ($ex !== false) { $idDest = (int) $ex; $vin = true; $res['vinculados']++; if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $nombre; } }
                else {
                    $ins->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':nom' => $nombre, ':cb' => $idUsuario]);
                    $idDest = (int) $ins->fetchColumn(); $vin = false; $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($nombre, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) $pg->rollBack();
                $res['errores']++;
            }
        }
        return $res;
    }

    /**
     * Migra los empleados del contribuyente. La tabla vieja `empleados` filtra por `id_empresa` (id
     * viejo), no por `ruc_empresa`: se resuelven todos los id de empresa del RUC base vía `empresas`.
     * Catálogo maestro (solo id_empresa, sin tipo_ambiente; ver regla del módulo Empleados).
     */
    private function migrarEmpleados(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'empleados', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'empleado sin identificación', 'errores' => 0];

        // id de empresa viejos que comparten el RUC base (todos los establecimientos).
        $empIds = [];
        $qe = $mysql->prepare("SELECT id FROM empresas WHERE LEFT(ruc, 10) = :b");
        $qe->execute([':b' => $base]);
        foreach ($qe->fetchAll(PDO::FETCH_COLUMN) as $eid) { $empIds[] = (int) $eid; }
        if (empty($empIds)) { return $res; }
        $inList = implode(',', array_map('intval', $empIds));

        // Mapa banco viejo (id_bancos) -> banco nuevo (bancos_ecuador.id) por codigo_banco (código SRI estable).
        $oldCod = [];
        foreach ($mysql->query("SELECT id_bancos, codigo_banco FROM bancos_ecuador") as $b) { $oldCod[(int) $b['id_bancos']] = (string) $b['codigo_banco']; }
        $newByCod = [];
        foreach ($pg->query("SELECT id, codigo_banco FROM bancos_ecuador") as $b) { $newByCod[(string) $b['codigo_banco']] = (int) $b['id']; }

        $done = $this->idsMigrados($pg, $idEmpresa, 'empleados');
        // Dedup por identificación dentro de la empresa (prefiere el vivo con id más bajo).
        $buscar = $pg->prepare("SELECT id FROM empleados WHERE id_empresa = :e AND identificacion = :ident ORDER BY eliminado, id LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO empleados (id_empresa, tipo_id, identificacion, nombres_apellidos, direccion, email, telefono,
                                    sexo, fecha_nacimiento, estado, id_banco_ecuador, tipo_cuenta, numero_cuenta, created_by)
             VALUES (:e, :tid, :ident, :nom, :dir, :cor, :tel, :sexo, :fnac, :est, :banco, :tcta, :ncta, :cb) RETURNING id"
        );
        $insMap = $this->stmtMap($pg, 'empleados');

        $stmt = $mysql->query("SELECT id, tipo_id, documento, nombres_apellidos, direccion, email, telefono, sexo, fecha_nacimiento, status, id_banco, tipo_cta, numero_cta FROM empleados WHERE id_empresa IN ($inList)");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $ident = trim((string) $r['documento']);
            if ($ident === '') { $res['omitidos']++; continue; }
            $nombre = trim((string) $r['nombres_apellidos']) ?: $ident;

            // Mapeos de códigos viejos -> valores del sistema nuevo.
            $tipoId = ((int) $r['tipo_id'] === 3) ? 'pasaporte' : 'cedula';          // legacy: casi todo 1 (cédula)
            $sexo   = ['1' => 'M', '2' => 'F'][(string) $r['sexo']] ?? null;
            $estado = ((int) $r['status'] === 1) ? 'activo' : 'inactivo';
            $tcta   = ['1' => 'ahorros', '2' => 'corriente'][(string) $r['tipo_cta']] ?? null;
            $oldB   = (int) $r['id_banco'];
            $banco  = ($oldB > 0 && isset($oldCod[$oldB], $newByCod[$oldCod[$oldB]])) ? $newByCod[$oldCod[$oldB]] : null;

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $ex = $buscar->fetchColumn();
                if ($ex !== false) {
                    $idDest = (int) $ex; $vin = true; $res['vinculados']++;
                    if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $nombre; }
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':tid' => $tipoId, ':ident' => $ident, ':nom' => $nombre,
                        ':dir' => self::nz($r['direccion']), ':cor' => self::nz($r['email']), ':tel' => self::nz($r['telefono']),
                        ':sexo' => $sexo, ':fnac' => self::fechaCorta($r['fecha_nacimiento']), ':est' => $estado,
                        ':banco' => $banco, ':tcta' => $tcta, ':ncta' => self::nz($r['numero_cta']), ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn(); $vin = false; $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Mapa de bancos viejo↔nuevo (cache por proceso). Devuelve [oldCod, newByCod, newNameById]:
     * oldCod = id_bancos viejo → codigo_banco; newByCod = codigo_banco → id nuevo; newNameById = id nuevo → nombre.
     * El catálogo bancos_ecuador viejo y nuevo comparten codigo_banco (código SRI estable).
     */
    private ?array $bancoMapsCache = null;
    private function bancoMaps(PDO $mysql, PDO $pg): array
    {
        if ($this->bancoMapsCache !== null) { return $this->bancoMapsCache; }
        $oldCod = [];
        foreach ($mysql->query("SELECT id_bancos, codigo_banco FROM bancos_ecuador") as $b) { $oldCod[(int) $b['id_bancos']] = (string) $b['codigo_banco']; }
        $newByCod = []; $newNameById = [];
        foreach ($pg->query("SELECT id, codigo_banco, nombre_banco FROM bancos_ecuador") as $b) {
            $newByCod[(string) $b['codigo_banco']] = (int) $b['id'];
            $newNameById[(int) $b['id']] = (string) $b['nombre_banco'];
        }
        return $this->bancoMapsCache = [$oldCod, $newByCod, $newNameById];
    }

    /**
     * id_tipo_cuenta viejo → tipo_cuenta del sistema nuevo (mayúsculas, como el módulo Formas de pago).
     * Solo se distinguen 1=AHORROS y 2=CORRIENTE; cualquier otro (3, y 4=tarjeta) va a AHORROS por defecto.
     */
    private static function tipoCuentaBanco(int $idTipo): string
    {
        return ['1' => 'AHORROS', '2' => 'CORRIENTE'][(string) $idTipo] ?? 'AHORROS';
    }

    /**
     * get-or-create de una FORMA DE PAGO bancaria (empresa_formas_pago tipo='BANCO', aplica_en='AMBAS')
     * a partir de una fila de cuentas_bancarias viejas. Dedup por numero_cuenta dentro de la empresa.
     * Inserta también el mapa (entidad 'cuentas_bancarias', old id_cuenta → forma nueva). Idempotente.
     * Devuelve ['id'=>int, 'nuevo'=>bool].
     */
    private function getOrCreateFormaBanco(int $idEmpresa, int $idUsuario, array $cta, PDO $mysql, PDO $pg, \PDOStatement $insMap): array
    {
        [$oldCod, $newByCod, $newNameById] = $this->bancoMaps($mysql, $pg);
        $oldCuenta = (int) $cta['id_cuenta'];
        $num = trim((string) $cta['numero_cuenta']);
        if ($num === '') { $num = 'CTA-' . $oldCuenta; }
        $oldB   = (int) $cta['id_banco'];
        $banco  = ($oldB > 0 && isset($oldCod[$oldB], $newByCod[$oldCod[$oldB]])) ? $newByCod[$oldCod[$oldB]] : null;
        $tcta   = self::tipoCuentaBanco((int) $cta['id_tipo_cuenta']);
        $nomBco = $banco !== null ? ($newNameById[$banco] ?? 'BANCO') : 'BANCO';
        $nombre = mb_substr($nomBco . ' - ' . $num, 0, 100);

        // Dedup por número de cuenta (prefiere la viva con id más bajo).
        $buscar = $pg->prepare("SELECT id FROM empresa_formas_pago WHERE id_empresa = :e AND tipo = 'BANCO' AND numero_cuenta = :n ORDER BY eliminado, id LIMIT 1");
        $buscar->execute([':e' => $idEmpresa, ':n' => $num]);
        $ex = $buscar->fetchColumn();
        if ($ex !== false) {
            $idForma = (int) $ex; $nuevo = false;
        } else {
            // aplica_en='AMBAS' (ingresos y egresos). id_banco puede ser null si el banco viejo no mapeó.
            $ins = $pg->prepare("INSERT INTO empresa_formas_pago (id_empresa, nombre, activo, tipo, aplica_en, id_banco, tipo_cuenta, numero_cuenta, created_by)
                                 VALUES (:e, :nom, true, 'BANCO', 'AMBAS', :banco, :tcta, :num, :cb) RETURNING id");
            $ins->execute([':e' => $idEmpresa, ':nom' => $nombre, ':banco' => $banco, ':tcta' => $tcta, ':num' => $num, ':cb' => $idUsuario]);
            $idForma = (int) $ins->fetchColumn(); $nuevo = true;
        }
        $insMap->execute([':e' => $idEmpresa, ':o' => $oldCuenta, ':d' => $idForma, ':cn' => substr($num, 0, 120), ':vin' => $nuevo ? 'f' : 't', ':cb' => $idUsuario]);
        return ['id' => $idForma, 'nuevo' => $nuevo];
    }

    /**
     * Resuelve la forma de cobro/pago de una línea de formas_pagos_ing_egr:
     *  - id_cuenta>0  → forma BANCARIA (cuenta migrada; get-or-create de respaldo si no está en el mapa).
     *  - id_cuenta=0  → opción por codigo_forma_pago (efectivo/caja…): mapa de formas_pago, luego el
     *    cache por nombre y, en último caso, la forma por defecto (Efectivo).
     * No cachea los resultados creados al vuelo (viven dentro de la transacción del ingreso/egreso; un
     * rollback los eliminaría). Los pre-migrados sí vienen en los mapas.
     */
    private function resolverFormaCobroPago(int $oldCuenta, string $cfp, int $idEmpresa, int $idUsuario, array $mapCuenta, array $mapFormaP, array $formaCache, int $formaDef, \PDOStatement $ctaStmt, \PDOStatement $insMapCta, PDO $mysql, PDO $pg): int
    {
        if ($oldCuenta > 0) {
            if (isset($mapCuenta[(string) $oldCuenta])) { return (int) $mapCuenta[(string) $oldCuenta]; }
            $ctaStmt->execute([':id' => $oldCuenta]);
            $cta = $ctaStmt->fetch(PDO::FETCH_ASSOC);
            if ($cta) { return $this->getOrCreateFormaBanco($idEmpresa, $idUsuario, $cta, $mysql, $pg, $insMapCta)['id']; }
            return $formaDef;
        }
        return $mapFormaP[$cfp] ?? $formaCache[$cfp] ?? $formaDef;
    }

    /**
     * Migra las cuentas bancarias del contribuyente a formas de cobro/pago (tipo BANCO, aplica AMBAS).
     * DEBE correrse ANTES de ingresos/egresos: sus pagos con id_cuenta>0 enlazan a estas formas.
     */
    private function migrarCuentasBancarias(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'cuentas_bancarias', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done   = $this->idsMigrados($pg, $idEmpresa, 'cuentas_bancarias');
        $insMap = $this->stmtMap($pg, 'cuentas_bancarias');

        $stmt = $mysql->query("SELECT id_cuenta, id_banco, id_tipo_cuenta, numero_cuenta FROM cuentas_bancarias WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_cuenta'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            try {
                $pg->beginTransaction();
                $out = $this->getOrCreateFormaBanco($idEmpresa, $idUsuario, $r, $mysql, $pg, $insMap);
                if ($out['nuevo']) { $res['migrados']++; }
                else { $res['vinculados']++; if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = trim((string) $r['numero_cuenta']); } }
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Migra las formas de cobro/pago (catálogo viejo opciones_cobros_pagos: efectivo, caja chica…) a
     * empresa_formas_pago (tipo EFECTIVO, aplica AMBAS) y registra el mapa (old opciones.id → forma nueva).
     * DEBE correrse ANTES de ingresos/egresos: sus pagos con id_cuenta=0 enlazan a estas formas por
     * codigo_forma_pago (= opciones.id). Dedup por nombre (reusa getOrCreateFormaPago).
     */
    private function migrarFormasPago(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'formas_pago', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'opción sin descripción', 'errores' => 0];

        $done   = $this->idsMigrados($pg, $idEmpresa, 'formas_pago');
        $insMap = $this->stmtMap($pg, 'formas_pago');
        $chk    = $pg->prepare("SELECT id FROM empresa_formas_pago WHERE id_empresa = :e AND nombre = :n AND eliminado = false LIMIT 1");

        $stmt = $mysql->query("SELECT id, descripcion FROM opciones_cobros_pagos WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $nombre = trim((string) $r['descripcion']);
            if ($nombre === '') { $res['omitidos']++; continue; }
            try {
                $pg->beginTransaction();
                $chk->execute([':e' => $idEmpresa, ':n' => $nombre]);
                $ya = $chk->fetchColumn();                       // ¿ya existía una forma con ese nombre?
                $idForma = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, $nombre, $pg);
                if ($ya !== false) { $res['vinculados']++; if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $nombre; } }
                else { $res['migrados']++; }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idForma, ':cn' => substr($nombre, 0, 120), ':vin' => $ya !== false ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** % de IVA por código SRI (para derivar impuestos del detalle). */
    private const IVA_PCT = ['0' => 0, '2' => 12, '3' => 14, '4' => 15, '5' => 5, '6' => 0, '7' => 0, '8' => 8, '10' => 13];

    /** get-or-create de un responsable de traslado desde el catálogo viejo (responsable_traslado). */
    private function getOrCreateResponsableTraslado(int $idEmpresa, int $idUsuario, int $oldRespId, PDO $mysql, PDO $pg, array &$cache): ?int
    {
        if ($oldRespId <= 0) { return null; }
        if (array_key_exists($oldRespId, $cache)) { return $cache[$oldRespId]; }
        $q = $mysql->prepare("SELECT nombre, correo FROM responsable_traslado WHERE id = :id LIMIT 1");
        $q->execute([':id' => $oldRespId]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return $cache[$oldRespId] = null; }
        $nombre = trim((string) $r['nombre']) !== '' ? trim((string) $r['nombre']) : ('Responsable ' . $oldRespId);
        $sel = $pg->prepare("SELECT id FROM responsables_traslado WHERE id_empresa = ? AND nombre = ? LIMIT 1");
        $sel->execute([$idEmpresa, $nombre]);
        $ex = $sel->fetchColumn();
        if ($ex !== false) { return $cache[$oldRespId] = (int) $ex; }
        $ins = $pg->prepare("INSERT INTO responsables_traslado (id_empresa, nombre, email, created_by) VALUES (?, ?, ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, $nombre, self::nz($r['correo']), $idUsuario]);
        return $cache[$oldRespId] = (int) $ins->fetchColumn();
    }

    /** Migra las consignaciones de venta base (operacion=ENTRADA) → consignaciones_ventas + detalles. */
    private function migrarConsignaciones(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'consignaciones', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done        = $this->idsMigrados($pg, $idEmpresa, 'consignaciones');
        $mapCliente  = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapVend     = $this->mapaDe($pg, $idEmpresa, 'vendedores');
        $mapProd     = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapBod      = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $prodPorCod  = $this->productosPorCodigo($pg, $idEmpresa);
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $insMap      = $this->stmtMap($pg,'consignaciones');
        $respCache   = [];
        // Reconcile (re-migrar): mapa old→[id,vinculado] para actualizar los ya migrados (solo los que
        // INSERTÓ la migración; los nativos vinculados NO se tocan) sin tener que "Eliminar migrados".
        $mapDest = [];
        $qmd = $pg->prepare("SELECT id_origen, id_destino, vinculado FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'consignaciones'");
        $qmd->execute([$idEmpresa]);
        foreach ($qmd->fetchAll(PDO::FETCH_ASSOC) as $o) { $mapDest[(string) $o['id_origen']] = ['id' => (int) $o['id_destino'], 'vin' => (bool) $o['vinculado']]; }
        $updEntrega = $pg->prepare("UPDATE consignaciones_ventas SET fecha_entrega = :fe, hora_entrega_desde = :hd, hora_entrega_hasta = :hh, updated_at = now(), updated_by = :u WHERE id = :id");
        // Reconcile de la caducidad por línea (re-migrar rellena fecha_caducidad sin "Eliminar migrados").
        // Match ROBUSTO: si el producto es único en la consignación, casa SOLO por (consignación, producto)
        // —independiente del lote, que a veces no coincide entre viejo y nuevo—; si el mismo producto aparece
        // varias veces (lotes distintos), se desempata por lote.
        $updDetCadProd = $pg->prepare("UPDATE consignaciones_ventas_detalles SET fecha_caducidad = :cad, updated_at = now() WHERE id_consignacion = :cons AND id_producto = :prod");
        $updDetCad     = $pg->prepare("UPDATE consignaciones_ventas_detalles SET fecha_caducidad = :cad, updated_at = now() WHERE id_consignacion = :cons AND id_producto = :prod AND COALESCE(lote,'') = COALESCE(:lote,'')");

        $detStmt = $mysql->prepare("SELECT id_producto, codigo_producto, nombre_producto, cant_consignacion, precio, descuento, id_bodega, lote, nup, vencimiento FROM detalle_consignacion WHERE codigo_unico = :cu AND LEFT(ruc_empresa, 10) = :base");
        $insCab  = $pg->prepare(
            "INSERT INTO consignaciones_ventas (id_empresa, fecha_emision, serie, secuencial, id_cliente, id_vendedor, id_responsable_traslado, punto_partida, punto_llegada, fecha_entrega, hora_entrega_desde, hora_entrega_hasta, observaciones, estado, subtotal, impuesto, total, establecimiento, punto_emision, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO consignaciones_ventas_detalles (id_consignacion, id_empresa, id_producto, cantidad, precio_unitario, subtotal, total, id_bodega, lote, nup, fecha_caducidad)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $sql = "SELECT id_consignacion, codigo_unico, fecha_consignacion, numero_consignacion, serie_sucursal, id_cli_pro, responsable, traslado_por, punto_partida, punto_llegada, observaciones, status, fecha_entrega, hora_entrega_desde, hora_entrega_hasta
                  FROM encabezado_consignacion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND operacion = 'ENTRADA'" . $this->clausulaFecha('fecha_consignacion', $desde, $hasta, $mysql) . " ORDER BY id_consignacion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_consignacion'];
            if (isset($mapDest[(string) $old])) { // ya migrada: reconciliar fecha/hora de entrega + caducidad del detalle (solo insertadas)
                if (!$mapDest[(string) $old]['vin']) {
                    $dest = $mapDest[(string) $old]['id'];
                    $hd = self::nz($ec['hora_entrega_desde']); if ($hd === '00:00:00') { $hd = null; }
                    $hh = self::nz($ec['hora_entrega_hasta']); if ($hh === '00:00:00') { $hh = null; }
                    try {
                        $updEntrega->execute([':fe' => self::fechaCorta($ec['fecha_entrega']), ':hd' => $hd, ':hh' => $hh, ':u' => $idUsuario, ':id' => $dest]);
                        // Caducidad: se agrupa el detalle viejo por producto resuelto. Producto único →
                        // match sin lote (robusto); repetido → por lote. (Sin crear productos.)
                        $detStmt->execute([':cu' => (string) $ec['codigo_unico'], ':base' => $base]);
                        $porProd = [];
                        foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
                            $idProdR = $mapProd[(string) (int) $d['id_producto']] ?? ($prodPorCod[(string) $d['codigo_producto']] ?? null);
                            if ($idProdR) { $porProd[$idProdR][] = $d; }
                        }
                        foreach ($porProd as $idProdR => $ls) {
                            if (count($ls) === 1) {
                                $updDetCadProd->execute([':cad' => self::caducidadODef($ls[0]['vencimiento'], $ec['fecha_consignacion']), ':cons' => $dest, ':prod' => $idProdR]);
                            } else {
                                foreach ($ls as $d) {
                                    $updDetCad->execute([':cad' => self::caducidadODef($d['vencimiento'], $ec['fecha_consignacion']), ':cons' => $dest, ':prod' => $idProdR, ':lote' => self::nz($d['lote'])]);
                                }
                            }
                        }
                    }
                    catch (Throwable $ex) { $res['errores']++; if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); } }
                }
                $res['ya_migrados']++; continue;
            }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cli_pro'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $partes = explode('-', trim((string) $ec['serie_sucursal']));
            $estab  = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto    = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec    = str_pad(preg_replace('/\D+/', '', (string) $ec['numero_consignacion']), 9, '0', STR_PAD_LEFT);

            $ye = $this->docExistente($pg, 'consignaciones_ventas', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }

            try {
                $pg->beginTransaction();
                $idVend = $mapVend[(string) (int) $ec['responsable']] ?? null;
                $idResp = $this->getOrCreateResponsableTraslado($idEmpresa, $idUsuario, (int) $ec['traslado_por'], $mysql, $pg, $respCache);

                $detStmt->execute([':cu' => (string) $ec['codigo_unico'], ':base' => $base]);
                $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);
                $sub = 0.0;
                foreach ($dets as $d) { $sub += (float) $d['cant_consignacion'] * (float) $d['precio']; }
                $est = ((int) $ec['status'] === 0) ? 'Anulada' : 'Emitida';

                $hd = self::nz($ec['hora_entrega_desde']); if ($hd === '00:00:00') { $hd = null; } // hora vacía → null (no medianoche)
                $hh = self::nz($ec['hora_entrega_hasta']); if ($hh === '00:00:00') { $hh = null; }
                $insCab->execute([$idEmpresa, substr((string) $ec['fecha_consignacion'], 0, 10), (string) $ec['serie_sucursal'], $sec, $idCliente, $idVend, $idResp, (string) ($ec['punto_partida'] ?? ''), (string) ($ec['punto_llegada'] ?? ''), self::fechaCorta($ec['fecha_entrega']), $hd, $hh, self::nz($ec['observaciones']), $est, round($sub, 2), round($sub, 2), $estab, $pto, $idUsuario]);
                $idCons = (int) $insCab->fetchColumn();

                foreach ($dets as $d) {
                    $idProd = $this->resolverOCrearProducto($prodPorCod, $mapProd, (int) $d['id_producto'], (string) $d['codigo_producto'], (string) $d['nombre_producto'], '0', $idEmpresa, $idUsuario, $pg);
                    $cant = (float) $d['cant_consignacion'];
                    $pu   = (float) $d['precio'];
                    $st   = round($cant * $pu, 2);
                    $idBod = ((int) $d['id_bodega'] > 0) ? ($mapBod[(string) (int) $d['id_bodega']] ?? null) : null;
                    // Caducidad por línea (regla del cero: si el vencimiento viene en cero/nulo, la fecha de la consignación).
                    $cadDet = self::caducidadODef($d['vencimiento'], $ec['fecha_consignacion']);
                    $insDet->execute([$idCons, $idEmpresa, $idProd, $cant, $pu, $st, $st, $idBod, self::nz($d['lote']), self::nz($d['nup']), $cadDet]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idCons, ':cn' => (string) $ec['numero_consignacion'], ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Migra los documentos derivados de una consignación: FACTURA → consignaciones_facturas,
     * DEVOLUCION → retornos_cv. Cada línea se enlaza a su ENTRADA por numero_orden_entrada + producto.
     * Requiere las consignaciones base migradas antes.
     */
    private function migrarConsignacionesDerivado(int $idEmpresa, string $ruc, int $idUsuario, int $limite, ?string $desde, ?string $hasta, string $modo): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $esFactura = ($modo === 'FACTURA');
        $entidad   = $esFactura ? 'consignaciones_fact' : 'consignaciones_ret';

        $res = ['entidad' => $entidad, 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'sin la consignación de origen migrada, o línea no casada', 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, $entidad);
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapVend    = $this->mapaDe($pg, $idEmpresa, 'vendedores');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapFactura = $esFactura ? $this->mapaDe($pg, $idEmpresa, 'facturas') : [];
        $prodPorCod = $this->productosPorCodigo($pg, $idEmpresa);
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $insMap     = $this->stmtMap($pg,$entidad);
        $respCache  = [];

        // numero_consignacion (ENTRADA) → id consignación nueva
        $mapCons = $this->mapaDe($pg, $idEmpresa, 'consignaciones');
        $mapEntrada = [];
        foreach ($mysql->query("SELECT id_consignacion, numero_consignacion FROM encabezado_consignacion WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base) . " AND operacion = 'ENTRADA'") as $e) {
            $nid = $mapCons[(string) (int) $e['id_consignacion']] ?? null;
            if ($nid) { $mapEntrada[(string) (int) $e['numero_consignacion']] = $nid; }
        }
        $lineLookup = $pg->prepare("SELECT id FROM consignaciones_ventas_detalles WHERE id_consignacion = ? AND id_producto = ? AND eliminado = false LIMIT 1");
        $lineCache = [];
        // Bodega del ítem (old id_bodega → nueva) + una por defecto; y el número de la factura vieja.
        $mapBod = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $bodDef = (int) $pg->query("SELECT id FROM bodegas WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false ORDER BY id LIMIT 1")->fetchColumn();
        // OJO: encabezado_consignacion.factura_venta es el SECUENCIAL de la factura (NO su id): se busca la
        // factura por RUC base + serie (= serie_sucursal) + secuencial = factura_venta. NO se filtra por
        // cliente: en datos viejos el id_cli_pro de la consignación a veces apunta a un cliente de OTRA
        // empresa (referencia corrupta), pero el secuencial sí identifica la factura correcta (fecha coincide).
        $oldFacNum = $esFactura ? $mysql->prepare("SELECT id_encabezado_factura, serie_factura, secuencial_factura, id_cliente FROM encabezado_factura WHERE LEFT(ruc_empresa, 10) = :b AND serie_factura = :serie AND CAST(secuencial_factura AS UNSIGNED) = :fv ORDER BY id_encabezado_factura LIMIT 1") : null;
        // Reconcile (re-migrar): actualiza los ya migrados (solo insertados, no vinculados) sin "Eliminar migrados".
        // La bodega del detalle se toma de la línea de ENTRADA (consignaciones_ventas_detalles) que ya la tiene.
        $mapDest = [];
        $qmd = $pg->prepare("SELECT id_origen, id_destino, vinculado FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
        $qmd->execute([$idEmpresa, $entidad]);
        foreach ($qmd->fetchAll(PDO::FETCH_ASSOC) as $o) { $mapDest[(string) $o['id_origen']] = ['id' => (int) $o['id_destino'], 'vin' => (bool) $o['vinculado']]; }
        if ($esFactura) {
            $updFacCab = $pg->prepare("UPDATE consignaciones_facturas SET numero_factura = :nf, id_factura = :idf, id_cliente = COALESCE(:cli, id_cliente), estado = 'facturada', updated_at = now(), updated_by = :u WHERE id = :id");
            $updDetBod = $pg->prepare("UPDATE consignaciones_facturas_detalles AS d SET id_bodega = e.id_bodega FROM consignaciones_ventas_detalles AS e WHERE e.id = d.id_consignacion_detalle AND d.id_consignacion_factura = :id AND d.id_bodega IS NULL");
        } else {
            $updFacCab = null;
            $updDetBod = $pg->prepare("UPDATE retornos_cv_detalles AS d SET id_bodega = e.id_bodega FROM consignaciones_ventas_detalles AS e WHERE e.id = d.id_consignacion_detalle AND d.id_retorno = :id AND d.id_bodega IS NULL");
        }

        $detStmt = $mysql->prepare("SELECT id_producto, codigo_producto, nombre_producto, cant_consignacion, precio, numero_orden_entrada, id_bodega, lote, nup FROM detalle_consignacion WHERE codigo_unico = :cu AND LEFT(ruc_empresa, 10) = :base");
        $opFilter = $esFactura ? "operacion = 'FACTURA'" : "operacion LIKE 'DEVOL%'";

        if ($esFactura) {
            $insCab = $pg->prepare("INSERT INTO consignaciones_facturas (id_empresa, id_consignacion, id_factura, numero_factura, fecha_emision, serie, secuencial, id_cliente, id_vendedor, subtotal, impuesto, total, estado, observaciones, establecimiento, punto_emision, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'facturada', ?, ?, ?, ?) RETURNING id");
            $insDet = $pg->prepare("INSERT INTO consignaciones_facturas_detalles (id_consignacion_factura, id_empresa, id_consignacion, id_consignacion_detalle, id_producto, cantidad, precio_unitario, subtotal, total, id_bodega, lote, nup) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $insCab = $pg->prepare("INSERT INTO retornos_cv (id_empresa, fecha_retorno, serie, secuencial, id_cliente, id_responsable_traslado, punto_partida, punto_llegada, observaciones, estado, subtotal, impuesto, total, establecimiento, punto_emision, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?) RETURNING id");
            $insDet = $pg->prepare("INSERT INTO retornos_cv_detalles (id_retorno, id_empresa, id_consignacion, id_consignacion_detalle, id_producto, cantidad, precio_unitario, subtotal, total, id_bodega, lote, nup) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        }

        $sql = "SELECT id_consignacion, codigo_unico, fecha_consignacion, numero_consignacion, serie_sucursal, id_cli_pro, responsable, traslado_por, punto_partida, punto_llegada, observaciones, status, factura_venta
                  FROM encabezado_consignacion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND " . $opFilter . $this->clausulaFecha('fecha_consignacion', $desde, $hasta, $mysql) . " ORDER BY id_consignacion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_consignacion'];
            if (isset($mapDest[(string) $old])) { // ya migrada: reconciliar número de factura + bodega del detalle (solo insertadas)
                if (!$mapDest[(string) $old]['vin']) {
                    $dest = $mapDest[(string) $old]['id'];
                    try {
                        $pg->beginTransaction();
                        if ($esFactura) {
                            $numFac = null; $idFac = null; $cliFac = null;
                            if ((int) $ec['factura_venta'] > 0) {
                                $oldFacNum->execute([':b' => $base, ':serie' => (string) $ec['serie_sucursal'], ':fv' => (int) $ec['factura_venta']]);
                                $ff = $oldFacNum->fetch(PDO::FETCH_ASSOC);
                                if ($ff) {
                                    $numFac = trim((string) $ff['serie_factura']) . '-' . str_pad(preg_replace('/\D+/', '', (string) $ff['secuencial_factura']), 9, '0', STR_PAD_LEFT);
                                    $idFac  = $mapFactura[(string) (int) $ff['id_encabezado_factura']] ?? null;
                                    $cliFac = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ff['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg) ?: null;
                                }
                            }
                            $updFacCab->execute([':nf' => $numFac, ':idf' => $idFac, ':cli' => $cliFac, ':u' => $idUsuario, ':id' => $dest]);
                        }
                        $updDetBod->execute([':id' => $dest]);
                        $pg->commit();
                    } catch (Throwable $ex) {
                        if ($pg->inTransaction()) { $pg->rollBack(); }
                        $res['errores']++; if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
                    }
                }
                $res['ya_migrados']++; continue;
            }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cli_pro'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            // Resolver todas las líneas contra su ENTRADA origen ANTES de insertar (id_consignacion_detalle es NOT NULL)
            $detStmt->execute([':cu' => (string) $ec['codigo_unico'], ':base' => $base]);
            $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);
            $lineas = [];
            $sub = 0.0;
            $incompleto = false;
            foreach ($dets as $d) {
                $numEnt  = (int) $d['numero_orden_entrada'];
                $idCons  = $mapEntrada[(string) $numEnt] ?? null;
                $idProd  = $this->resolverOCrearProducto($prodPorCod, $mapProd, (int) $d['id_producto'], (string) $d['codigo_producto'], (string) $d['nombre_producto'], '0', $idEmpresa, $idUsuario, $pg);
                if (!$idCons) { $incompleto = true; break; }
                $ckey = $idCons . '-' . $idProd;
                if (!array_key_exists($ckey, $lineCache)) {
                    $lineLookup->execute([$idCons, $idProd]);
                    $lineCache[$ckey] = ($lineLookup->fetchColumn() ?: null);
                }
                $idConsDet = $lineCache[$ckey];
                if (!$idConsDet) { $incompleto = true; break; }
                $cant = (float) $d['cant_consignacion'];
                $pu   = (float) $d['precio'];
                $st   = round($cant * $pu, 2);
                $sub += $st;
                $idBod = $mapBod[(string) (int) $d['id_bodega']] ?? ($bodDef ?: null); // bodega del ítem (mapeada), o la por defecto
                $lineas[] = [$idCons, $idConsDet, $idProd, $cant, $pu, $st, self::nz($d['lote']), self::nz($d['nup']), $idBod];
            }
            if ($incompleto || !$lineas) { $res['omitidos']++; continue; } // sin ENTRADA origen migrada / línea no casada

            $partes = explode('-', trim((string) $ec['serie_sucursal']));
            $estab  = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto    = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec    = str_pad(preg_replace('/\D+/', '', (string) $ec['numero_consignacion']), 9, '0', STR_PAD_LEFT);
            $fe     = substr((string) $ec['fecha_consignacion'], 0, 10);
            $idConsCab = $lineas[0][0];

            try {
                $pg->beginTransaction();
                if ($esFactura) {
                    $idVend = $mapVend[(string) (int) $ec['responsable']] ?? null;
                    // factura_venta = SECUENCIAL de la factura → número real + id real (por RUC+serie+secuencial).
                    $numFac = null; $idFactura = null;
                    if ((int) $ec['factura_venta'] > 0) {
                        $oldFacNum->execute([':b' => $base, ':serie' => (string) $ec['serie_sucursal'], ':fv' => (int) $ec['factura_venta']]);
                        $ff = $oldFacNum->fetch(PDO::FETCH_ASSOC);
                        if ($ff) {
                            $numFac    = trim((string) $ff['serie_factura']) . '-' . str_pad(preg_replace('/\D+/', '', (string) $ff['secuencial_factura']), 9, '0', STR_PAD_LEFT);
                            $idFactura = $mapFactura[(string) (int) $ff['id_encabezado_factura']] ?? null;
                            // El cliente correcto es el de la FACTURA (el id_cli_pro de la consignación a veces
                            // es cross-company). Solo se sobrescribe si el de la factura resuelve.
                            $cliFac = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ff['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
                            if ($cliFac) { $idCliente = $cliFac; }
                        }
                    }
                    $insCab->execute([$idEmpresa, $idConsCab, $idFactura, $numFac, $fe, (string) $ec['serie_sucursal'], $sec, $idCliente, $idVend, round($sub, 2), round($sub, 2), self::nz($ec['observaciones']), $estab, $pto, $idUsuario]);
                    $idParent = (int) $insCab->fetchColumn();
                    foreach ($lineas as $ln) {
                        $insDet->execute([$idParent, $idEmpresa, $ln[0], $ln[1], $ln[2], $ln[3], $ln[4], $ln[5], $ln[5], $ln[8], $ln[6], $ln[7]]);
                    }
                } else {
                    $idResp = $this->getOrCreateResponsableTraslado($idEmpresa, $idUsuario, (int) $ec['traslado_por'], $mysql, $pg, $respCache);
                    $est = ((int) $ec['status'] === 0) ? 'Anulada' : 'Emitida';
                    $insCab->execute([$idEmpresa, $fe, (string) $ec['serie_sucursal'], $sec, $idCliente, $idResp, (string) ($ec['punto_partida'] ?? ''), (string) ($ec['punto_llegada'] ?? ''), self::nz($ec['observaciones']), $est, round($sub, 2), round($sub, 2), $estab, $pto, $idUsuario]);
                    $idParent = (int) $insCab->fetchColumn();
                    foreach ($lineas as $ln) {
                        $insDet->execute([$idParent, $idEmpresa, $ln[0], $ln[1], $ln[2], $ln[3], $ln[4], $ln[5], $ln[5], $ln[8], $ln[6], $ln[7]]);
                    }
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idParent, ':cn' => (string) $ec['numero_consignacion'], ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }

        // Toda consignación de venta que YA fue facturada debe quedar 'Entregada' (el módulo exige que una
        // consignación esté Entregada para poder facturarla). Se mira el DETALLE de la facturación (una
        // factura puede cubrir VARIAS consignaciones — multi-consignación). No toca anuladas ni ya entregadas.
        if ($esFactura) {
            try {
                $st = $pg->prepare("UPDATE consignaciones_ventas cv SET estado = 'Entregada', updated_at = now(), updated_by = :u
                    WHERE cv.id_empresa = :e AND cv.eliminado = false AND cv.estado NOT IN ('Entregada', 'Anulada')
                      AND EXISTS (SELECT 1 FROM consignaciones_facturas_detalles cfd
                                  JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                                  WHERE cfd.id_consignacion = cv.id AND cf.id_empresa = :e AND cf.eliminado = false)");
                $st->execute([':u' => $idUsuario, ':e' => $idEmpresa]);
                $res['consignaciones_entregadas'] = $st->rowCount();
            } catch (Throwable $ex) {
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /** Migra los cambios de productos facturados (cambio_productos_facturados) → cambios_producto_cv (2 líneas: devuelto/entregado). */
    private function migrarCambiosProducto(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'cambios_producto', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done        = $this->idsMigrados($pg, $idEmpresa, 'cambios_producto');
        $mapCliente  = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd     = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapBod      = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $prodPorCod  = $this->productosPorCodigo($pg, $idEmpresa);
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $insMap      = $this->stmtMap($pg,'cambios_producto');
        $oldProd     = $mysql->prepare("SELECT codigo_producto, nombre_producto FROM productos_servicios WHERE id_producto = :id LIMIT 1");

        $insCab = $pg->prepare("INSERT INTO cambios_producto_cv (id_empresa, fecha_cambio, serie, secuencial, id_cliente, observaciones, estado, subtotal_devuelto, subtotal_entregado, diferencia, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Emitida', 0, 0, 0, ?) RETURNING id");
        $insDet = $pg->prepare("INSERT INTO cambios_producto_cv_detalles (id_cambio, id_empresa, tipo_linea, id_producto, cantidad, id_bodega, lote) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $sql = "SELECT id_cambio, fecha_cambio, id_cliente, id_producto_anterior, id_nuevo_producto, cant_cambiada, lote_anterior, nuevo_lote, id_bodega_anterior, id_nueva_bodega, observaciones
                  FROM cambio_productos_facturados WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_cambio', $desde, $hasta, $mysql) . " ORDER BY id_cambio";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        $resolverProd = function (int $oldId) use (&$prodPorCod, $mapProd, $oldProd, $idEmpresa, $idUsuario, $pg) {
            $oldProd->execute([':id' => $oldId]);
            $p = $oldProd->fetch(PDO::FETCH_ASSOC) ?: ['codigo_producto' => '', 'nombre_producto' => ''];
            return $this->resolverOCrearProducto($prodPorCod, $mapProd, $oldId, (string) $p['codigo_producto'], (string) $p['nombre_producto'], '0', $idEmpresa, $idUsuario, $pg);
        };

        while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $c['id_cambio'];
            if ($this->yaMigradoDoc($idEmpresa, 'cambios_producto', 'cambios_producto_cv', $old, $pg)) { $res['ya_migrados']++; continue; }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $c['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $sec = str_pad((string) $old, 9, '0', STR_PAD_LEFT);
            $cant = (float) $c['cant_cambiada'];

            try {
                $pg->beginTransaction();
                $prodAnt = $resolverProd((int) $c['id_producto_anterior']);
                $prodNew = $resolverProd((int) $c['id_nuevo_producto']);
                $insCab->execute([$idEmpresa, substr((string) $c['fecha_cambio'], 0, 10), '001-001', $sec, $idCliente, self::nz($c['observaciones']), $idUsuario]);
                $idCambio = (int) $insCab->fetchColumn();
                $insDet->execute([$idCambio, $idEmpresa, 'devolucion', $prodAnt, $cant, (((int) $c['id_bodega_anterior'] > 0) ? ($mapBod[(string) (int) $c['id_bodega_anterior']] ?? null) : null), self::nz($c['lote_anterior'])]);
                $insDet->execute([$idCambio, $idEmpresa, 'entrega', $prodNew, $cant, (((int) $c['id_nueva_bodega'] > 0) ? ($mapBod[(string) (int) $c['id_nueva_bodega']] ?? null) : null), self::nz($c['nuevo_lote'])]);
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idCambio, ':cn' => (string) $old, ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra proformas (cabecera + detalle + impuestos). El producto es opcional (no se auto-crea). */
    private function migrarProformas(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'proformas', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done        = $this->idsMigrados($pg, $idEmpresa, 'proformas');
        $mapCliente  = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd     = $this->mapaDe($pg, $idEmpresa, 'productos');
        $insMap      = $this->stmtMap($pg,'proformas');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);

        $insCab = $pg->prepare(
            "INSERT INTO proformas_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, total_sin_impuestos, total_descuento, importe_total, moneda, estado, observaciones, tipo_ambiente, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DOLAR', ?, ?, ?, ?) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO proformas_detalle (id_proforma, id_producto, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO proformas_detalle_impuestos (id_proforma_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (?, '2', ?, ?, ?, ?)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad, valor_unitario, subtotal, descuento, tarifa_iva, codigo_producto, nombre_producto FROM cuerpo_proforma WHERE codigo_unico = :cu AND LEFT(ruc_empresa, 10) = :base");

        $sql = "SELECT id_encabezado_proforma, fecha_proforma, serie_proforma, secuencial_proforma, id_cliente, total_proforma, estado_proforma, codigo_unico
                  FROM encabezado_proforma WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_proforma', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_proforma";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ep = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ep['id_encabezado_proforma'];
            if ($this->yaMigradoDoc($idEmpresa, 'proformas', 'proformas_cabecera', $old, $pg)) { $res['ya_migrados']++; continue; }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ep['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $partes = explode('-', trim((string) $ep['serie_proforma']));
            $estab  = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto    = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec    = str_pad(preg_replace('/\D+/', '', (string) $ep['secuencial_proforma']), 9, '0', STR_PAD_LEFT);

            $ye = $this->docExistente($pg, 'proformas_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);

                $cuerpoStmt->execute([':cu' => (string) $ep['codigo_unico'], ':base' => $base]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $totalSinImp = 0.0; $totalDesc = 0.0;
                foreach ($lineas as $l) {
                    $totalSinImp += (float) $l['subtotal'] - (float) $l['descuento'];
                    $totalDesc   += (float) $l['descuento'];
                }
                $est = (stripos((string) $ep['estado_proforma'], 'anul') !== false) ? 'ANULADA' : 'EMITIDA';

                $insCab->execute([$idEmpresa, $idEst, $idPto, $idCliente, $idUsuario, substr((string) $ep['fecha_proforma'], 0, 10), $estab, $pto, $sec, round($totalSinImp, 2), round($totalDesc, 2), (float) $ep['total_proforma'], $est, null, $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario]);
                $idProf = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                    $insDet->execute([$idProf, ($mapProd[(string) (int) $l['id_producto']] ?? null), (string) $l['codigo_producto'], ((string) $l['nombre_producto'] !== '' ? (string) $l['nombre_producto'] : 'ITEM'), (float) $l['cantidad'], (float) $l['valor_unitario'], (float) $l['descuento'], round($base_i, 2)]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['tarifa_iva']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([$idDet, $cod, $pct, round($base_i, 2), round($base_i * $pct / 100, 2)]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idProf, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Resuelve la cuenta contable de una línea vieja (id_cuenta) → id de plan_cuentas nuevo,
     * puenteando por código; si el código no existe en el plan nuevo, lo CREA. Null si no hay dato.
     *
     * $mysql (opcional): si la cuenta referenciada NO pertenece a la empresa (asientos manuales
     * viejos que apuntaban a cuentas de OTRA empresa, porque el id_cuenta era global), se busca su
     * código directamente en el plan viejo y se puentea por código a la empresa actual.
     */
    private function resolverOCrearCuenta(array &$mapCuenta, array &$cuentaPorCod, array $oldCuentas, int $oldId, int $idEmpresa, int $idUsuario, PDO $pg, ?PDO $mysql = null): ?int
    {
        if (isset($mapCuenta[$oldId])) { return $mapCuenta[$oldId]; }
        $info = $oldCuentas[$oldId] ?? null;
        // Cuenta de otra empresa (id_cuenta global): traer su código del plan viejo y puentear igual.
        if (!$info && $mysql) {
            static $ext = [];
            if (!array_key_exists($oldId, $ext)) {
                $q = $mysql->prepare("SELECT codigo_cuenta AS codigo, nombre_cuenta AS nombre, nivel_cuenta AS nivel FROM plan_cuentas WHERE id_cuenta = ? LIMIT 1");
                $q->execute([$oldId]);
                $ext[$oldId] = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $info = $ext[$oldId];
        }
        if (!$info) { return null; }
        $cod = trim((string) $info['codigo']);
        if ($cod === '') { return null; }
        $cod = self::codigoCasa($cod); // reformatea al plan nuevo (nivel 3 a 1 dígito)
        if (isset($cuentaPorCod[$cod])) { return $mapCuenta[$oldId] = $cuentaPorCod[$cod]; }
        $nivel = (int) ($info['nivel'] ?: (substr_count($cod, '.') + 1));
        try {
            $ins = $pg->prepare("INSERT INTO plan_cuentas (id_empresa, id_usuario, codigo, nivel, nombre, status, created_by) VALUES (?, ?, ?, ?, ?, 1, ?) RETURNING id");
            $ins->execute([$idEmpresa, $idUsuario, $cod, $nivel, ((string) $info['nombre'] !== '' ? (string) $info['nombre'] : $cod), $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM plan_cuentas WHERE id_empresa = ? AND codigo = ? LIMIT 1");
            $q->execute([$idEmpresa, $cod]);
            $id = (int) $q->fetchColumn();
            if (!$id) { throw $e; }
        }
        $cuentaPorCod[$cod] = $id;
        return $mapCuenta[$oldId] = $id;
    }

    /**
     * Ajusta un código de cuenta viejo al formato del plan NUEVO (de la casa): el nivel 3 usa 1
     * dígito, mientras el plan viejo lo trae con 2 (0X). Solo se reduce el 3er segmento cuando es 0X
     * (valor 1–9); si es >= 10 (no cabe en 1 dígito) se deja igual. Los demás segmentos no cambian.
     * Las cuentas de movimiento (las que usan los asientos) son todas de nivel 5, p. ej.
     * '3.1.01.01.001' -> '3.1.1.01.001'.
     */
    /** Igual que codigoCasa(), expuesto para reusar la MISMA conversión desde otros servicios. */
    public static function codigoCasaPublico(string $cod): string
    {
        return self::codigoCasa($cod);
    }

    private static function codigoCasa(string $cod): string
    {
        $s = explode('.', $cod);
        if (count($s) >= 3 && strlen($s[2]) === 2 && $s[2][0] === '0') {
            $s[2] = (string) (int) $s[2]; // '01'->'1', '09'->'9'
        }
        return implode('.', $s);
    }

    /**
     * Mapea el `tipo` del diario viejo (MAYÚSCULAS, p. ej. 'DIARIO', 'COMPRAS_SERVICIOS') al
     * vocabulario de `tipo_comprobante` del sistema nuevo (minúsculas: 'diario', 'compras', …),
     * para que el asiento migrado se reconozca/filtre igual que los nativos.
     */
    private static function tipoComprobanteCasa(?string $tipo): string
    {
        static $mapa = [
            'COMPRAS_SERVICIOS'   => 'compras',
            'VENTAS'              => 'ventas',
            'INGRESOS'            => 'ingresos',
            'EGRESOS'             => 'egresos',
            'RETENCIONES_COMPRAS' => 'retenciones_compras',
            'RETENCIONES_VENTAS'  => 'retenciones_ventas',
            'RECIBOS'             => 'ventas',
            'NC_VENTAS'           => 'ventas',
            'DIARIO'              => 'diario',
            'BALANCE_INICIAL'     => 'apertura',
            'ROL_PAGOS'           => 'nomina',
        ];
        $t = strtoupper(trim((string) $tipo));
        return $mapa[$t] ?? 'diario';
    }

    /**
     * Resuelve el documento nuevo al que pertenece un asiento del diario viejo, a partir de su
     * 'tipo' (COMPRAS_SERVICIOS, VENTAS, …) y su codigo_unico (prefijo de letras + id del documento
     * viejo, p. ej. 'COM375496'). Devuelve [tabla, idDocNuevo] o null si el tipo no lleva documento
     * (ROL_PAGOS/DIARIO/BALANCE_INICIAL) o el documento no está migrado. `$cache` acumula los mapas
     * por entidad para no recargarlos en cada llamada.
     *
     * @return array{0:string,1:int}|null
     */
    private function docDeDiario(PDO $pg, int $idEmpresa, string $tipo, string $codigoUnico, array &$cache): ?array
    {
        static $tipoDoc = [
            'COMPRAS_SERVICIOS'   => ['compras',            'compras_cabecera'],
            'VENTAS'              => ['facturas',           'ventas_cabecera'],
            'INGRESOS'            => ['ingresos',           'ingresos_cabecera'],
            'EGRESOS'             => ['egresos',            'egresos_cabecera'],
            'RETENCIONES_COMPRAS' => ['retenciones_compra', 'retencion_compra_cabecera'],
            'RETENCIONES_VENTAS'  => ['retenciones_venta',  'retencion_venta_cabecera'],
            'RECIBOS'             => ['recibos',            'recibos_venta_cabecera'],
            'NC_VENTAS'           => ['notas_credito',      'notas_credito_cabecera'],
        ];
        $tipo = strtoupper(trim($tipo));
        if (!isset($tipoDoc[$tipo])) { return null; }
        [$entidad, $tabla] = $tipoDoc[$tipo];
        $oldDoc = (int) preg_replace('/\D+/', '', $codigoUnico); // quita el prefijo de letras
        if ($oldDoc <= 0) { return null; }
        if (!isset($cache[$entidad])) { $cache[$entidad] = $this->mapaDe($pg, $idEmpresa, $entidad); }
        $idDocNuevo = $cache[$entidad][(string) $oldDoc] ?? null;
        return $idDocNuevo ? [$tabla, (int) $idDocNuevo] : null;
    }

    /**
     * Prepara/migra el PLAN DE CUENTAS viejo hacia la empresa nueva, con el formato de código de la
     * casa (codigoCasa). Reglas:
     *   - Si la cuenta (por su código de la casa) YA EXISTE en el plan nuevo: se reusa y se
     *     sobreescribe su NOMBRE con el del sistema viejo. No se duplica.
     *   - Si NO existe: se crea. Con `$crearSiempre=false` (uso interno desde Contabilidad) solo se
     *     crea si la empresa nueva no tenía plan, para no ensuciar uno ya armado; las faltantes se
     *     crean bajo demanda (resolverOCrearCuenta) cuando un asiento las referencia. Con
     *     `$crearSiempre=true` (entidad "Plan de cuentas", paso explícito del usuario) se crean todas.
     * Deja `$mapCuenta` (id_cuenta viejo → id nuevo) y `$cuentaPorCod` (código casa → id) listos.
     * Si se pasa `$res`, cuenta migradas/vinculadas/omitidas para el reporte del módulo.
     */
    private function migrarPlanCuentas(int $idEmpresa, int $idUsuario, PDO $pg, array $oldCuentas, array &$mapCuenta, array &$cuentaPorCod, bool $crearSiempre = false, ?array &$res = null): void
    {
        $nuevoVacio = ((int) $pg->query("SELECT COUNT(*) FROM plan_cuentas WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false")->fetchColumn()) === 0;
        $ins    = $pg->prepare("INSERT INTO plan_cuentas (id_empresa, id_usuario, codigo, nivel, nombre, status, created_by) VALUES (?, ?, ?, ?, ?, 1, ?) RETURNING id");
        $updNom = $pg->prepare("UPDATE plan_cuentas SET nombre = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        foreach ($oldCuentas as $oldId => $c) {
            if ($res !== null) { $res['total']++; }
            $cod = trim((string) $c['codigo']);
            if ($cod === '') { if ($res !== null) { $res['omitidos']++; } continue; }
            $house  = self::codigoCasa($cod);
            $nombre = ((string) $c['nombre'] !== '') ? (string) $c['nombre'] : $house;
            if (isset($cuentaPorCod[$house])) {
                // Ya existe en el nuevo: reusar y sobreescribir el nombre con el del viejo.
                $newId = (int) $cuentaPorCod[$house];
                $updNom->execute([$nombre, $idUsuario, $newId]);
                $mapCuenta[(int) $oldId] = $newId;
                if ($res !== null) {
                    $res['vinculados']++;
                    if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $house . ' · ' . $nombre; }
                }
            } elseif ($crearSiempre || $nuevoVacio) {
                $nivel = (int) ($c['nivel'] ?: (substr_count($house, '.') + 1));
                $ins->execute([$idEmpresa, $idUsuario, $house, $nivel, $nombre, $idUsuario]);
                $newId = (int) $ins->fetchColumn();
                $cuentaPorCod[$house]    = $newId;
                $mapCuenta[(int) $oldId] = $newId;
                if ($res !== null) { $res['migrados']++; }
            } elseif ($res !== null) {
                $res['omitidos']++;
            }
        }
    }

    /**
     * Entidad "Plan de cuentas": migra el plan viejo como PASO PROPIO (prerequisito para importar la
     * configuración contable, que apunta a cuentas). A diferencia del uso interno desde Contabilidad,
     * aquí SÍ se crean las cuentas faltantes aunque la empresa ya tenga plan.
     */
    private function migrarPlanCuentasEntidad(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'plan_cuentas', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'cuenta sin código en el plan viejo', 'errores' => 0];

        // Plan nuevo actual (código de la casa → id) y plan viejo de la empresa.
        $cuentaPorCod = [];
        $qn = $pg->prepare("SELECT codigo, id FROM plan_cuentas WHERE id_empresa = ? AND eliminado = false");
        $qn->execute([$idEmpresa]);
        foreach ($qn->fetchAll(PDO::FETCH_ASSOC) as $r) { $cuentaPorCod[(string) $r['codigo']] = (int) $r['id']; }

        $oldCuentas = [];
        foreach ($mysql->query("SELECT id_cuenta, codigo_cuenta, nombre_cuenta, nivel_cuenta FROM plan_cuentas WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base) . " ORDER BY codigo_cuenta") as $r) {
            $oldCuentas[(int) $r['id_cuenta']] = ['codigo' => $r['codigo_cuenta'], 'nombre' => $r['nombre_cuenta'], 'nivel' => $r['nivel_cuenta']];
        }

        $mapCuenta = [];
        try {
            $this->migrarPlanCuentas($idEmpresa, $idUsuario, $pg, $oldCuentas, $mapCuenta, $cuentaPorCod, true, $res);
        } catch (Throwable $ex) {
            $res['errores']++;
            if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
        }
        return $res;
    }

    /** Migra la contabilidad histórica (encabezado_diario + detalle_diario_contable) → asientos, tal cual (modulo_origen='migracion'). */
    private function migrarContabilidad(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'contabilidad', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'asiento roto en el diario viejo: sin detalle, o con líneas sin cuenta contable (id_cuenta 0)', 'errores' => 0];
        $done   = $this->idsMigrados($pg, $idEmpresa, 'contabilidad');
        $insMap = $this->stmtMap($pg,'contabilidad');

        // Plan de cuentas: código → id nuevo, y old id_cuenta → info (para puentear/crear)
        $cuentaPorCod = [];
        $qn = $pg->prepare("SELECT codigo, id FROM plan_cuentas WHERE id_empresa = ? AND eliminado = false");
        $qn->execute([$idEmpresa]);
        foreach ($qn->fetchAll(PDO::FETCH_ASSOC) as $r) { $cuentaPorCod[(string) $r['codigo']] = (int) $r['id']; }
        $oldCuentas = [];
        foreach ($mysql->query("SELECT id_cuenta, codigo_cuenta, nombre_cuenta, nivel_cuenta FROM plan_cuentas WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $r) {
            $oldCuentas[(int) $r['id_cuenta']] = ['codigo' => $r['codigo_cuenta'], 'nombre' => $r['nombre_cuenta'], 'nivel' => $r['nivel_cuenta']];
        }
        $mapCuenta = [];

        // Migrar/reconciliar el plan de cuentas viejo al formato de la casa ANTES de los asientos:
        // deja $mapCuenta y $cuentaPorCod listos (por código de la casa).
        $this->migrarPlanCuentas($idEmpresa, $idUsuario, $pg, $oldCuentas, $mapCuenta, $cuentaPorCod);

        // CRÍTICO: filtrar TAMBIÉN por ruc_empresa. El codigo_unico SÍ se repite entre empresas en la
        // base vieja (p.ej. 'FAC204315' existe en 2 contribuyentes), así que sin este filtro el detalle
        // de un asiento traería líneas de OTRA empresa y lo corrompería (líneas extra, descuadre, cuentas ajenas).
        $detStmt = $mysql->prepare("SELECT id_cuenta, debe, haber, detalle_item FROM detalle_diario_contable WHERE codigo_unico = :cu AND LEFT(ruc_empresa, 10) = :base");
        $insCab  = $pg->prepare("INSERT INTO asientos_contables_cabecera (id_empresa, fecha_asiento, tipo_comprobante, numero_comprobante, concepto, estado, modulo_origen, total_debe, total_haber, tipo_ambiente, created_by) VALUES (?, ?, ?, ?, ?, ?, 'migracion', ?, ?, ?, ?) RETURNING id");
        $insDet  = $pg->prepare("INSERT INTO asientos_contables_detalle (id_empresa, id_asiento, id_cuenta_contable, debe, haber, referencia_detalle, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // Mapa de la propia contabilidad (id_diario viejo → id asiento nuevo) y ajuste de ambiente,
        // para reconciliar / (re)enlazar en re-corridas sin re-insertar.
        $mapContab = $this->mapaDe($pg, $idEmpresa, 'contabilidad');
        $updAmb    = $pg->prepare("UPDATE asientos_contables_cabecera SET tipo_ambiente = ?, estado = ?, tipo_comprobante = ? WHERE id = ?");

        // Enlace documento ↔ asiento migrado. `docDeDiario()` resuelve el documento nuevo por el
        // 'tipo' del diario y el codigo_unico (prefijo+id viejo); aquí se setea
        // documento.id_asiento_contable + asiento.id_referencia_origen (1→1).
        $docMapCache  = [];
        $updDocStmts  = [];
        $updRefOrigen = $pg->prepare("UPDATE asientos_contables_cabecera SET id_referencia_origen = ? WHERE id = ? AND id_empresa = ?");
        $enlazar = function (array $e, int $idAsiento) use (&$docMapCache, &$updDocStmts, $idEmpresa, $pg, $updRefOrigen): void {
            $r = $this->docDeDiario($pg, $idEmpresa, (string) $e['tipo'], (string) $e['codigo_unico'], $docMapCache);
            if ($r === null) { return; }                                             // sin documento o documento no migrado
            [$tabla, $idDocNuevo] = $r;
            if (!isset($updDocStmts[$tabla])) { $updDocStmts[$tabla] = $pg->prepare("UPDATE $tabla SET id_asiento_contable = ? WHERE id = ? AND id_empresa = ?"); }
            $updDocStmts[$tabla]->execute([$idAsiento, $idDocNuevo, $idEmpresa]);
            $updRefOrigen->execute([$idDocNuevo, $idAsiento, $idEmpresa]);
        };

        // Los asientos ELIMINADOS en el sistema viejo se marcan con estado='Anulado' → NO se migran.
        $sql = "SELECT id_diario, codigo_unico, fecha_asiento, concepto_general, estado, tipo
                  FROM encabezado_diario WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND codigo_unico <> '' AND LOWER(TRIM(estado)) <> 'anulado'" . $this->clausulaFecha('fecha_asiento', $desde, $hasta, $mysql) . " ORDER BY id_diario";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($e = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $e['id_diario'];
            // Estado del asiento: los migrados se dan por posteados = 'contabilizado' (los anulados
            // ya se excluyeron arriba; se mantiene el mapeo por robustez).
            $est = (stripos((string) $e['estado'], 'anul') !== false) ? 'anulado' : 'contabilizado';
            $tcomp = self::tipoComprobanteCasa($e['tipo'] ?? null); // vocabulario del sistema nuevo
            // Ya migrado: reconciliar ambiente + estado + tipo y (re)enlazar el documento (re-corrida).
            if (isset($mapContab[(string) $old])) {
                $idAsientoExist = (int) $mapContab[(string) $old];
                $updAmb->execute([$this->ambienteEmpresa($pg, $idEmpresa), $est, $tcomp, $idAsientoExist]);
                $enlazar($e, $idAsientoExist);
                $res['ya_migrados']++;
                continue;
            }

            $detStmt->execute([':cu' => (string) $e['codigo_unico'], ':base' => $base]);
            $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$dets) { $res['omitidos']++; continue; } // encabezado sin detalle (huérfano)

            try {
                $pg->beginTransaction();
                $lineas = [];
                $td = 0.0;
                $th = 0.0;
                $sinCuenta = false;
                foreach ($dets as $d) {
                    $idc = $this->resolverOCrearCuenta($mapCuenta, $cuentaPorCod, $oldCuentas, (int) $d['id_cuenta'], $idEmpresa, $idUsuario, $pg, $mysql);
                    // Línea sin cuenta en el diario viejo (id_cuenta 0 o inexistente): el asiento está
                    // roto/incompleto en origen (a veces sin NINGUNA cuenta y descuadrado). No se puede
                    // migrar sin inventar datos → se OMITE entero (no es un error de la migración).
                    if (!$idc) { $sinCuenta = true; break; }
                    // referencia_detalle es varchar(500) (ver database/ensanchar_referencia_detalle.sql);
                    // truncado defensivo por si algún detalle superara ese límite.
                    $ref = self::nz($d['detalle_item']);
                    if ($ref !== null) { $ref = mb_substr((string) $ref, 0, 500); }
                    $lineas[] = [$idc, (float) $d['debe'], (float) $d['haber'], $ref];
                    $td += (float) $d['debe'];
                    $th += (float) $d['haber'];
                }
                if ($sinCuenta) {
                    $pg->rollBack();
                    $res['omitidos']++;
                    continue;
                }
                $insCab->execute([$idEmpresa, substr((string) $e['fecha_asiento'], 0, 10), $tcomp, (string) $e['codigo_unico'], (self::nz($e['concepto_general']) !== null ? (string) $e['concepto_general'] : (string) $e['codigo_unico']), $est, $td, $th, $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario]);
                $idAsiento = (int) $insCab->fetchColumn();
                foreach ($lineas as $ln) {
                    $insDet->execute([$idEmpresa, $idAsiento, $ln[0], $ln[1], $ln[2], $ln[3], $idUsuario]);
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idAsiento, ':cn' => (string) $e['codigo_unico'], ':vin' => 'f', ':cb' => $idUsuario]);
                $enlazar($e, $idAsiento); // enlaza el documento nuevo con este asiento (id_asiento_contable)
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        // Cierre del ejercicio POR AÑO: salda las cuentas PIVOT (clase 7 = RESUMEN RESULTADOS)
        // contra la cuenta de Utilidad/Pérdida configurada en 'cierre_ejercicio'.
        $this->cerrarEjercicioMigrado($idEmpresa, $idUsuario, $pg, $res);
        return $res;
    }

    /** Cuentas configuradas del tipo 'cierre_ejercicio': id de la cuenta de Utilidad y de Pérdida. */
    private function cuentasCierreConfig(int $idEmpresa, PDO $pg): array
    {
        $st = $pg->prepare(
            "SELECT at.codigo AS slot, ap.id_cuenta
               FROM asientos_tipo at
               JOIN asientos_programados ap
                 ON ap.id_asiento_tipo = at.id AND ap.id_empresa = ?
                AND ap.id_referencia = at.id
                AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
                AND ap.eliminado = false
              WHERE at.tipo_asiento = 'cierre_ejercicio' AND at.eliminado = false
                AND at.codigo IN ('UTILIDADEJERCICIOCIERRE', 'PERDIDAEJERCICIOCIERRE')"
        );
        $st->execute([$idEmpresa]);
        $r = ['utilidad' => null, 'perdida' => null];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $k = $x['slot'] === 'UTILIDADEJERCICIOCIERRE' ? 'utilidad' : 'perdida';
            $r[$k] = (int) $x['id_cuenta'];
        }
        return $r;
    }

    /**
     * Genera, por cada año fiscal, un asiento de cierre (fechado 31-dic) que salda las cuentas PIVOT
     * (clase 7, nivel 5) contra la cuenta de Utilidad (resultado positivo) o Pérdida (negativo)
     * configurada. IDEMPOTENTE: el saldo de clase 7 se calcula incluyendo cierres previos
     * (modulo_origen='migracion'), así al re-correr el neto es 0 y no duplica. El reset de
     * contabilidad borra estos cierres (son 'migracion') para regenerarlos.
     */
    private function cerrarEjercicioMigrado(int $idEmpresa, int $idUsuario, PDO $pg, array &$res): void
    {
        $ctas = $this->cuentasCierreConfig($idEmpresa, $pg);
        if (!$ctas['utilidad'] && !$ctas['perdida']) {
            $res['cierre_aviso'] = 'Cuentas de cierre (Utilidad/Pérdida) no configuradas en Configuración Contable: no se generó el cierre del ejercicio.';
            return;
        }

        // AÑOS QUE YA TRAEN SU CIERRE DEL SISTEMA VIEJO.
        // Si un asiento migrado (que no sea de este mismo generador) mueve la cuenta PIVOT,
        // significa que el sistema viejo YA cerró ese ejercicio: típicamente debita el
        // «Resumen de Resultados» y acredita el impuesto a la renta y la utilidad del
        // ejercicio en patrimonio. En esos años NO hay nada que cerrar: el saldo que queda
        // en la PIVOT es el contrapeso de las cuentas 4/5/6 —que siguen abiertas— y el
        // Balance ya lo usa en su cierre virtual (EstadosFinancierosService::getBalanceGeneral).
        // Generar un cierre encima interpretaba ese saldo deudor como pérdida e inventaba una
        // «Pérdida del ejercicio» inexistente, subvaluando el patrimonio en ese importe.
        $yaCerrados = [];
        $qc = $pg->prepare(
            "SELECT DISTINCT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio
               FROM asientos_contables_cabecera a
               JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
               JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
              WHERE a.id_empresa = ? AND a.eliminado = false
                AND a.modulo_origen = 'migracion' AND a.tipo_comprobante <> 'cierre'
                AND pc.id_empresa = ? AND pc.codigo LIKE '7%' AND pc.nivel = '5'"
        );
        $qc->execute([$idEmpresa, $idEmpresa]);
        foreach ($qc->fetchAll(PDO::FETCH_COLUMN) as $a) { $yaCerrados[(int) $a] = true; }

        // Saldo de clase 7 (nivel 5) por año y cuenta. Se aplican los MISMOS filtros que usa
        // el Balance (EstadosFinancierosRepository::getSaldos): solo asientos 'contabilizado',
        // del ambiente activo y sobre cuentas vivas del plan. Sin esto el cierre sumaba
        // asientos que el Balance no ve —anular desde el módulo de Asientos deja
        // estado='anulado' pero eliminado=false— y trasladaba a patrimonio un importe distinto.
        $q = $pg->prepare(
            "SELECT EXTRACT(YEAR FROM a.fecha_asiento)::int AS anio, d.id_cuenta_contable AS idc,
                    ROUND(SUM(d.haber) - SUM(d.debe), 2) AS saldo
               FROM asientos_contables_cabecera a
               JOIN asientos_contables_detalle d ON d.id_asiento = a.id AND d.eliminado = false
               JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
              WHERE a.id_empresa = ? AND a.eliminado = false
                AND a.estado = 'contabilizado'
                AND CAST(a.tipo_ambiente AS VARCHAR(1)) = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                AND pc.id_empresa = ? AND pc.eliminado = false
                AND pc.codigo LIKE '7%' AND pc.nivel = '5'
              GROUP BY anio, d.id_cuenta_contable"
        );
        $q->execute([$idEmpresa, $idEmpresa, $idEmpresa]);
        $porAnio = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $s = (float) $x['saldo'];
            if ($s != 0.0) { $porAnio[(int) $x['anio']][(int) $x['idc']] = $s; }
        }
        if (!$porAnio) { return; }

        // El ejercicio en curso no se cierra: todavía está vivo.
        $anioActual = (int) date('Y');

        $amb    = $this->ambienteEmpresa($pg, $idEmpresa);
        $insCab = $pg->prepare("INSERT INTO asientos_contables_cabecera (id_empresa, fecha_asiento, tipo_comprobante, numero_comprobante, concepto, estado, modulo_origen, total_debe, total_haber, tipo_ambiente, created_by) VALUES (?, ?, 'cierre', ?, ?, 'contabilizado', 'migracion', ?, ?, ?, ?) RETURNING id");
        $insDet = $pg->prepare("INSERT INTO asientos_contables_detalle (id_empresa, id_asiento, id_cuenta_contable, debe, haber, referencia_detalle, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

        ksort($porAnio);
        foreach ($porAnio as $anio => $cuentas) {
            if (isset($yaCerrados[$anio])) {
                $res['cierre_ya_migrado'] = ($res['cierre_ya_migrado'] ?? 0) + 1;
                continue;
            }
            if ($anio >= $anioActual) {
                $res['cierre_anio_en_curso'] = ($res['cierre_anio_en_curso'] ?? 0) + 1;
                continue;
            }
            $neto = round(array_sum($cuentas), 2);
            if ($neto == 0.0) { continue; }
            $ctaResultado = $neto > 0 ? $ctas['utilidad'] : $ctas['perdida'];
            if (!$ctaResultado) { $res['cierre_omitidos'] = ($res['cierre_omitidos'] ?? 0) + 1; continue; }

            try {
                $pg->beginTransaction();
                $lineas = []; $td = 0.0; $th = 0.0;
                foreach ($cuentas as $idc => $s) {          // reverso de cada cuenta clase-7
                    $debe = $s > 0 ? $s : 0.0;               // saldo crédito -> debitar para saldar
                    $haber = $s < 0 ? -$s : 0.0;             // saldo débito  -> acreditar
                    $lineas[] = [$idc, $debe, $haber]; $td += $debe; $th += $haber;
                }
                if ($neto > 0) { $lineas[] = [$ctaResultado, 0.0, $neto]; $th += $neto; }   // utilidad = crédito a patrimonio
                else           { $lineas[] = [$ctaResultado, -$neto, 0.0]; $td += -$neto; } // pérdida  = débito a patrimonio

                $insCab->execute([$idEmpresa, "$anio-12-31", "CIERRE-$anio", "Cierre del ejercicio $anio (migración)", round($td, 2), round($th, 2), $amb, $idUsuario]);
                $idAsiento = (int) $insCab->fetchColumn();
                foreach ($lineas as $ln) {
                    $insDet->execute([$idEmpresa, $idAsiento, $ln[0], round($ln[1], 2), round($ln[2], 2), "Cierre del ejercicio $anio", $idUsuario]);
                }
                $pg->commit();
                $res['cierres_generados'] = ($res['cierres_generados'] ?? 0) + 1;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
    }

    /**
     * Migra los pedidos (encabezado_pedido/detalle_pedido → pedidos_cabecera/pedidos_detalle).
     * El detalle viejo NO tiene precio (solo cantidad): el precio se toma del producto (precio_base) y el
     * IVA se deja en 0 (los pedidos no calculan impuesto en el sistema viejo). Requiere cliente.
     * tipo_ambiente se fija al de la empresa (el default '1' ocultaría los migrados en producción).
     */
    private function migrarPedidos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'pedidos', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'pedido sin cliente', 'errores' => 0];

        $done        = $this->idsMigrados($pg, $idEmpresa, 'pedidos');
        $insMap      = $this->stmtMap($pg, 'pedidos');
        $mapCliente  = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $mapProd     = $this->mapaDe($pg, $idEmpresa, 'productos');
        $prodPorCod  = $this->productosPorCodigo($pg, $idEmpresa);
        $amb         = $this->ambienteEmpresa($pg, $idEmpresa);

        // Precio por producto nuevo (precio_base): el detalle viejo no trae precio.
        $precioProd = [];
        foreach ($pg->query("SELECT id, COALESCE(precio_base, 0) pb FROM productos WHERE id_empresa = " . (int) $idEmpresa) as $p) { $precioProd[(int) $p['id']] = (float) $p['pb']; }

        // Sin establecimiento/punto en el viejo: se usan '001'/'001'; secuencial = numero_pedido.
        $buscar  = $pg->prepare("SELECT id FROM pedidos_cabecera WHERE id_empresa = :e AND establecimiento = '001' AND punto_emision = '001' AND secuencial = :sec ORDER BY eliminado, id LIMIT 1");
        $insCab  = $pg->prepare("INSERT INTO pedidos_cabecera (id_empresa, id_cliente, fecha_pedido, estado, observaciones, observaciones_internas, fecha_entrega, hora_inicial_entrega, hora_maxima_entrega, establecimiento, punto_emision, secuencial, tipo_ambiente, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '001', '001', ?, ?, ?) RETURNING id");
        $insDet  = $pg->prepare("INSERT INTO pedidos_detalle (id_pedido, id_producto, cantidad, precio_unitario, subtotal, iva, total) VALUES (?, ?, ?, ?, ?, 0, ?)");
        $detStmt = $mysql->prepare("SELECT id_producto, codigo_producto, producto, cantidad FROM detalle_pedido WHERE id_pedido = :id");
        // Reconcile (re-migrar): actualiza la cabecera (estado/entrega/observaciones) de los ya migrados
        // (solo los que insertó la migración, no los nativos) sin tener que "Eliminar migrados".
        $mapDest = [];
        $qmd = $pg->prepare("SELECT id_origen, id_destino, vinculado FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'pedidos'");
        $qmd->execute([$idEmpresa]);
        foreach ($qmd->fetchAll(PDO::FETCH_ASSOC) as $o) { $mapDest[(string) $o['id_origen']] = ['id' => (int) $o['id_destino'], 'vin' => (bool) $o['vinculado']]; }
        $updCab = $pg->prepare("UPDATE pedidos_cabecera SET estado = :est, fecha_entrega = :fent, hora_inicial_entrega = :hi, hora_maxima_entrega = :hm, observaciones = :obs, observaciones_internas = :obsi, updated_at = now(), updated_by = :u WHERE id = :id");

        $sql = "SELECT id, numero_pedido, id_cliente, datecreated, fecha_entrega, hora_entrega_desde, hora_entrega_hasta, observaciones_cliente, observaciones_interna, status
                  FROM encabezado_pedido WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('datecreated', $desde, $hasta, $mysql) . " ORDER BY id";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ep = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ep['id'];
            // status viejo → estado nuevo (confirmado por el usuario): 1=Pendiente, 2=Procesado, 3=Anulado.
            $est  = ['1' => 'Pendiente', '2' => 'Procesado', '3' => 'Anulado'][(string) $ep['status']] ?? 'Pendiente';
            if (isset($mapDest[(string) $old])) { // ya migrado: reconciliar estado + entrega (solo insertados)
                if (!$mapDest[(string) $old]['vin']) {
                    try { $updCab->execute([':est' => $est, ':fent' => self::fechaCorta($ep['fecha_entrega']), ':hi' => self::nz($ep['hora_entrega_desde']), ':hm' => self::nz($ep['hora_entrega_hasta']), ':obs' => self::nz($ep['observaciones_cliente']), ':obsi' => self::nz($ep['observaciones_interna']), ':u' => $idUsuario, ':id' => $mapDest[(string) $old]['id']]); }
                    catch (Throwable $ex) { $res['errores']++; if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); } }
                }
                $res['ya_migrados']++; continue;
            }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ep['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }
            $sec  = str_pad(preg_replace('/\D+/', '', (string) $ep['numero_pedido']), 9, '0', STR_PAD_LEFT);
            $fped = self::fechaCorta($ep['datecreated']);
            $fent = self::fechaCorta($ep['fecha_entrega']);

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':sec' => $sec]);
                $exId = $buscar->fetchColumn();
                if ($exId !== false) {
                    $idPed = (int) $exId; $vin = true; $res['vinculados']++;
                    if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = '001-001-' . $sec; }
                } else {
                    $insCab->execute([$idEmpresa, $idCliente, $fped, $est, self::nz($ep['observaciones_cliente']), self::nz($ep['observaciones_interna']), $fent, self::nz($ep['hora_entrega_desde']), self::nz($ep['hora_entrega_hasta']), $sec, $amb, $idUsuario]);
                    $idPed = (int) $insCab->fetchColumn(); $vin = false; $res['migrados']++;
                }

                if (!$vin) { // no tocar el detalle de un pedido nativo enlazado
                    $detStmt->execute([':id' => $old]);
                    foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
                        $idProd = $this->resolverOCrearProducto($prodPorCod, $mapProd, (int) $d['id_producto'], (string) $d['codigo_producto'], (string) $d['producto'], '0', $idEmpresa, $idUsuario, $pg);
                        if (!$idProd) { continue; }
                        $cant = (float) $d['cantidad'];
                        $pu   = $precioProd[$idProd] ?? 0.0;
                        $stl  = round($cant * $pu, 2);
                        $insDet->execute([$idPed, $idProd, $cant, $pu, $stl, $stl]);
                    }
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idPed, ':cn' => (string) $ep['numero_pedido'], ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra el kardex (tabla inventarios) → inventario_kardex, con saldo corrido por producto/bodega. */
    private function migrarInventario(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'inventario', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'movimientos en cero (no afectan el stock)', 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'inventario');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $prodPorCod = $this->productosPorCodigo($pg, $idEmpresa);
        $mapBod     = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $insMap     = $this->stmtMap($pg,'inventario');

        $bodDef = (int) $pg->query("SELECT id FROM bodegas WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false ORDER BY id LIMIT 1")->fetchColumn();
        if ($bodDef <= 0) {
            throw new \RuntimeException('La empresa no tiene bodegas activas; cree una antes de migrar inventario.');
        }

        // Convención canónica del sistema (nativa): inventario_kardex.cantidad va CON SIGNO (entrada +,
        // salida −). El stock corriente = SUM(cantidad) directo — igual que getStockActual() y el reporte de
        // movimientos, que clasifican entrada/salida por el SIGNO de cantidad, no por tipo_movimiento.
        $qStock = $pg->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM inventario_kardex WHERE id_empresa = ? AND id_producto = ? AND id_bodega = ? AND eliminado = false");
        // La unidad del kardex (id_medida) sale de la del producto (el listado usa k.id_medida sin fallback
        // al producto). Guard por si el esquema no tuviera la columna (el repo nativo también la verifica).
        $tieneMedida = (bool) $pg->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'inventario_kardex' AND column_name = 'id_medida'")->fetchColumn();
        $medStmt = $pg->prepare("SELECT id_medida FROM productos WHERE id = ? LIMIT 1");
        $medCache = [];
        // referencia_tipo/referencia_id ahora son parámetros: los movimientos de consignación/retorno
        // migrados se ENLAZAN a su documento (CONSIGNACION_VENTA/RETORNO_CV); el resto queda 'migracion'.
        $ins = $pg->prepare($tieneMedida
            ? "INSERT INTO inventario_kardex (id_empresa, id_producto, id_bodega, id_medida, tipo_movimiento, referencia_tipo, referencia_id, fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior, numero_lote, fecha_caducidad, observaciones, tipo_ambiente, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
            : "INSERT INTO inventario_kardex (id_empresa, id_producto, id_bodega, tipo_movimiento, referencia_tipo, referencia_id, fecha_movimiento, cantidad, costo_unitario, costo_total, stock_anterior, stock_posterior, numero_lote, fecha_caducidad, observaciones, tipo_ambiente, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
        // El kardex migrado por sí solo no alimenta productos_bodegas (el reporte de Existencias/
        // Valorización lee de ahí, no del kardex): sin este upsert el stock migrado queda invisible
        // en esas dos pestañas aunque el Kardex sí lo muestre.
        $upsertStock = $pg->prepare("INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_actual, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (id_producto, id_bodega)
                DO UPDATE SET stock_actual = EXCLUDED.stock_actual, updated_by = EXCLUDED.updated_by,
                              updated_at = CURRENT_TIMESTAMP, eliminado = false");
        $stock  = [];
        // Enlace de los movimientos de consignación/retorno al documento migrado. El texto viejo
        // `referencia` trae el número ("Consignación en venta N: 4" / "Retorno consignación en venta N: 4");
        // ese número = clave_natural en el mapa (numero_consignacion de la ENTRADA / del DEVOL). El stock
        // NO cambia (ya sale del propio kardex): solo se enlaza referencia_tipo/id para trazabilidad y para
        // que el módulo de consignaciones reconozca la salida/entrada como suya (reversos, reportes).
        $mapConsigNum = [];
        $qcn = $pg->prepare("SELECT clave_natural, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'consignaciones'");
        $qcn->execute([$idEmpresa]);
        foreach ($qcn->fetchAll(PDO::FETCH_ASSOC) as $m) { $mapConsigNum[(string) $m['clave_natural']] = (int) $m['id_destino']; }
        $mapRetNum = [];
        $qrn = $pg->prepare("SELECT clave_natural, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'consignaciones_ret'");
        $qrn->execute([$idEmpresa]);
        foreach ($qrn->fetchAll(PDO::FETCH_ASSOC) as $m) { $mapRetNum[(string) $m['clave_natural']] = (int) $m['id_destino']; }
        // Clasifica la referencia vieja → [referencia_tipo, referencia_id]; ['migracion', null] si no aplica
        // o si el documento aún no está migrado (se enlaza al re-migrar Inventario, vía el reconcile).
        $clasificarRef = static function ($texto) use ($mapConsigNum, $mapRetNum): array {
            $t = (string) $texto;
            if (preg_match('/^\s*Consignaci.n en venta N[^0-9]*([0-9]+)/iu', $t, $m)) {
                $id = $mapConsigNum[$m[1]] ?? null;
                if ($id) { return ['CONSIGNACION_VENTA', $id]; }
            } elseif (preg_match('/^\s*Retorno consignaci.n en venta N[^0-9]*([0-9]+)/iu', $t, $m)) {
                $id = $mapRetNum[$m[1]] ?? null;
                if ($id) { return ['RETORNO_CV', $id]; }
            }
            return ['migracion', null];
        };

        // Reconcile (re-migrar): en vez de saltar los ya migrados, actualiza su fecha_caducidad (y refresca
        // la unidad) y ENLAZA su referencia_tipo/id si corresponde. Así re-migrar CORRIGE la caducidad y el
        // enlace a consignación/retorno sin tener que "Eliminar migrados" primero (no degrada lo ya enlazado).
        $mapInv = $this->mapaDe($pg, $idEmpresa, 'inventario');
        // Reconcile actualiza también el SIGNO de cantidad (migraciones viejas la guardaban positiva en salidas):
        // re-migrar Inventario corrige el signo sin "Eliminar migrados". No toca stock_anterior/posterior (son
        // niveles absolutos, ya correctos) ni productos_bodegas.
        $updRec = $pg->prepare($tieneMedida
            ? "UPDATE inventario_kardex SET cantidad = :cant, fecha_caducidad = :cad, id_medida = COALESCE((SELECT id_medida FROM productos WHERE id = inventario_kardex.id_producto), id_medida), referencia_tipo = COALESCE(:rt, referencia_tipo), referencia_id = COALESCE(:rid, referencia_id), updated_at = now(), updated_by = :u WHERE id = :id"
            : "UPDATE inventario_kardex SET cantidad = :cant, fecha_caducidad = :cad, referencia_tipo = COALESCE(:rt, referencia_tipo), referencia_id = COALESCE(:rid, referencia_id), updated_at = now(), updated_by = :u WHERE id = :id");

        $sql = "SELECT id_inventario, id_producto, id_bodega, codigo_producto, nombre_producto, cantidad_entrada, cantidad_salida, costo_unitario, precio, operacion, fecha_registro, referencia, lote, fecha_vencimiento
                  FROM inventarios WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_registro', $desde, $hasta, $mysql) . " ORDER BY fecha_registro, id_inventario";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($iv = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $iv['id_inventario'];
            // Ya migrado: reconciliar el SIGNO de cantidad, la fecha_caducidad (regla del cero) y el enlace
            // consignación/retorno, sin recomputar el stock. Si no clasifica, :rt/:rid van null y COALESCE
            // conserva lo existente.
            if (isset($mapInv[(string) $old])) {
                $cadR = self::caducidadODef($iv['fecha_vencimiento'], $iv['fecha_registro']);
                [$rtR, $ridR] = $clasificarRef($iv['referencia']);
                $entR  = strtoupper(trim((string) $iv['operacion'])) === 'ENTRADA';
                $magR  = $entR ? (float) $iv['cantidad_entrada'] : (float) $iv['cantidad_salida'];
                $cantR = $entR ? $magR : -$magR;
                try { $updRec->execute([':cant' => $cantR, ':cad' => $cadR, ':rt' => ($rtR === 'migracion' ? null : $rtR), ':rid' => $ridR, ':u' => $idUsuario, ':id' => (int) $mapInv[(string) $old]]); }
                catch (Throwable $ex) { $res['errores']++; if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); } }
                $res['ya_migrados']++;
                continue;
            }

            $esEntrada = strtoupper(trim((string) $iv['operacion'])) === 'ENTRADA';
            $cant = $esEntrada ? (float) $iv['cantidad_entrada'] : (float) $iv['cantidad_salida'];
            if ($cant <= 0) { $res['omitidos']++; continue; } // movimiento en cero: ruido, no afecta stock

            try {
                $pg->beginTransaction();
                $idProd = $this->resolverOCrearProducto($prodPorCod, $mapProd, (int) $iv['id_producto'], (string) $iv['codigo_producto'], (string) $iv['nombre_producto'], '0', $idEmpresa, $idUsuario, $pg);
                $idBod  = $mapBod[(string) (int) $iv['id_bodega']] ?? $bodDef;
                $cu     = (float) ($iv['costo_unitario'] ?: $iv['precio']);

                $key = $idProd . '-' . $idBod;
                if (!isset($stock[$key])) {
                    $qStock->execute([$idEmpresa, $idProd, $idBod]);
                    $stock[$key] = (float) $qStock->fetchColumn();
                }
                $ant  = $stock[$key];
                $post = $esEntrada ? $ant + $cant : $ant - $cant;
                $stock[$key] = $post;

                if (!array_key_exists($idProd, $medCache)) { $medStmt->execute([$idProd]); $medCache[$idProd] = ($medStmt->fetchColumn() ?: null); }
                $idMed = $medCache[$idProd];
                $movParams = [$idEmpresa, $idProd, $idBod];
                if ($tieneMedida) { $movParams[] = $idMed; }
                // Fecha de caducidad: vencimiento, o la fecha del movimiento si viene en cero (ningún ítem sin caducidad).
                $cad = self::caducidadODef($iv['fecha_vencimiento'], $iv['fecha_registro']);
                // Enlace al documento de consignación/retorno (o 'migracion' + null si no aplica).
                [$refTipo, $refId] = $clasificarRef($iv['referencia']);
                // cantidad CON SIGNO (entrada +, salida −); costo_total siempre positivo (magnitud), como el nativo.
                $cantSigned = $esEntrada ? $cant : -$cant;
                array_push($movParams, $esEntrada ? 'entrada' : 'salida', $refTipo, $refId, substr((string) $iv['fecha_registro'], 0, 19), $cantSigned, $cu, $cant * $cu, $ant, $post, self::nz($iv['lote']), $cad, self::nz($iv['referencia']), $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario);
                $ins->execute($movParams);
                $kid = (int) $ins->fetchColumn();

                $upsertStock->execute([$idEmpresa, $idProd, $idBod, $post, $idUsuario, $idUsuario]);

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $kid, ':cn' => (string) $old, ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** get-or-create de una forma de cobro/pago por nombre. */
    private function getOrCreateFormaPago(int $idEmpresa, int $idUsuario, string $nombre, PDO $pg): int
    {
        $nombre = trim($nombre) !== '' ? trim($nombre) : 'Efectivo';
        $st = $pg->prepare("SELECT id FROM empresa_formas_pago WHERE id_empresa = ? AND nombre = ? LIMIT 1");
        $st->execute([$idEmpresa, $nombre]);
        $r = $st->fetchColumn();
        if ($r !== false) { return (int) $r; }
        $ins = $pg->prepare("INSERT INTO empresa_formas_pago (id_empresa, nombre, activo, tipo, aplica_en, created_by) VALUES (?, ?, true, 'EFECTIVO', 'AMBAS', ?) RETURNING id");
        $ins->execute([$idEmpresa, $nombre, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /**
     * Concepto genérico (get-or-create por empresa) para los ingresos/egresos migrados que NO
     * están atados a una factura/compra ("otros conceptos"). Con esto la cabecera queda como
     * ingreso/egreso "por concepto" (tipo OTRO/GENERAL) y su detalle es de texto libre, evitando
     * que la vista pinte un documento clickeable con id nulo. $tipo = 'ingreso' | 'egreso'.
     */
    private function getOrCreateConceptoMigracion(int $idEmpresa, int $idUsuario, string $tipo, PDO $pg): int
    {
        static $cache = [];
        $key = $idEmpresa . '|' . $tipo;
        if (isset($cache[$key])) { return $cache[$key]; }

        $nombre = $tipo === 'ingreso' ? 'Otros ingresos (migración)' : 'Otros egresos (migración)';
        $sel = $pg->prepare("SELECT id FROM empresa_opciones_ingreso_egreso WHERE id_empresa = :e AND nombre = :n AND eliminado = false LIMIT 1");
        $sel->execute([':e' => $idEmpresa, ':n' => $nombre]);
        $id = $sel->fetchColumn();
        if ($id !== false) { return $cache[$key] = (int) $id; }

        // PDO+pgsql: los boolean se pasan como literal 'true'/'false' (no false de PHP).
        $ins = $pg->prepare("INSERT INTO empresa_opciones_ingreso_egreso (id_empresa, nombre, aplica_ingresos, aplica_egresos, comportamiento, estado, created_by) VALUES (:e, :n, :ai, :ae, 'GENERAL', 'ACTIVO', :u) RETURNING id");
        $ins->execute([
            ':e'  => $idEmpresa,
            ':n'  => $nombre,
            ':ai' => $tipo === 'ingreso' ? 'true' : 'false',
            ':ae' => $tipo === 'egreso'  ? 'true' : 'false',
            ':u'  => $idUsuario,
        ]);
        return $cache[$key] = (int) $ins->fetchColumn();
    }

    /** Migra cobros (ingresos): cabecera + detalle (enlaza facturas por el mapa) + pagos (forma de cobro). */
    private function migrarIngresos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'ingresos', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'ingresos');
        $mapFactura = $this->mapaDe($pg, $idEmpresa, 'facturas');
        $insMap     = $this->stmtMap($pg,'ingresos');

        // Formas de cobro: pre-crear desde el catálogo viejo (fuera de transacción) + una por defecto
        $formaCache = [];
        foreach ($mysql->query("SELECT id, descripcion FROM opciones_cobros_pagos WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $o) {
            $formaCache[(string) $o['id']] = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, (string) $o['descripcion'], $pg);
        }
        $formaDef = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, 'Efectivo', $pg);

        $detStmt   = $mysql->prepare("SELECT valor_ing_egr, detalle_ing_egr, codigo_documento_cv FROM detalle_ingresos_egresos WHERE codigo_documento = :cd AND tipo_documento = 'INGRESO'");
        $formaStmt = $mysql->prepare("SELECT valor_forma_pago, codigo_forma_pago, id_cuenta, fecha_pago, cheque FROM formas_pagos_ing_egr WHERE codigo_documento = :cd AND tipo_documento = 'INGRESO'");
        // Forma de cobro BANCARIA: cuando el pago viejo trae id_cuenta>0 (codigo_forma_pago='0'), enlaza a la
        // cuenta bancaria migrada (empresa_formas_pago tipo BANCO). Preferir el mapa; get-or-create de respaldo.
        $mapCuenta  = $this->mapaDe($pg, $idEmpresa, 'cuentas_bancarias'); // old id_cuenta -> forma
        $mapFormaP  = $this->mapaDe($pg, $idEmpresa, 'formas_pago');       // old opciones.id -> forma
        $ctaStmt    = $mysql->prepare("SELECT id_cuenta, id_banco, id_tipo_cuenta, numero_cuenta FROM cuentas_bancarias WHERE id_cuenta = :id LIMIT 1");
        $insMapCta  = $this->stmtMap($pg, 'cuentas_bancarias');
        $mapCliente  = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldFacCli   = $mysql->prepare("SELECT id_cliente FROM encabezado_factura WHERE id_encabezado_factura = :id LIMIT 1");
        $mapIngreso  = $this->mapaDe($pg, $idEmpresa, 'ingresos'); // para reconciliar al re-correr
        $updCab      = $pg->prepare("UPDATE ingresos_cabecera SET fecha_emision = ?, tipo_ingreso = ?, id_ingreso_concepto = ?, id_cliente = ?, monto_total = ?, observaciones = ?, estado = ?, recibo_de = ?, id_recibo_cliente = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $delDet      = $pg->prepare("DELETE FROM ingresos_detalle WHERE id_ingreso = ?");
        $delPag      = $pg->prepare("DELETE FROM ingresos_pagos WHERE id_ingreso = ?");

        $insCab  = $pg->prepare("INSERT INTO ingresos_cabecera (id_empresa, id_usuario, fecha_emision, secuencial, numero_ingreso, tipo_ingreso, id_ingreso_concepto, id_cliente, monto_total, observaciones, estado, recibo_de, id_recibo_cliente, tipo_ambiente, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $insDet  = $pg->prepare("INSERT INTO ingresos_detalle (id_ingreso, tipo_documento, id_referencia_documento, numero_documento, descripcion, monto_documento, monto_cobrado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insPago = $pg->prepare("INSERT INTO ingresos_pagos (id_ingreso, id_forma_cobro, monto, fecha_cobro, numero_cheque) VALUES (?, ?, ?, ?, ?)");

        $sql = "SELECT id_ing_egr, codigo_documento, numero_ing_egr, valor_ing_egr, fecha_ing_egr, detalle_adicional, estado, nombre_ing_egr, id_cli_pro
                  FROM ingresos_egresos WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND tipo_ing_egr = 'INGRESO'" . $this->clausulaFecha('fecha_ing_egr', $desde, $hasta, $mysql) . " ORDER BY id_ing_egr";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ie = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ie['id_ing_egr'];
            $idIngExist = $mapIngreso[(string) $old] ?? null;
            $cod = (string) $ie['codigo_documento'];
            $sec = str_pad(preg_replace('/\D+/', '', (string) $ie['numero_ing_egr']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $detStmt->execute([':cd' => $cod]);
                $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);

                // Cliente: desde la factura vieja referenciada (funciona aunque la factura no se haya migrado)
                $idCliente = null;
                foreach ($dets as $d) {
                    $facOld = (int) $d['codigo_documento_cv'];
                    if ($facOld <= 0) { continue; }
                    $oldFacCli->execute([':id' => $facOld]);
                    $oldCli = (int) $oldFacCli->fetchColumn();
                    if ($oldCli > 0) {
                        $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, $oldCli, $idEmpresa, $idUsuario, $mysql, $pg);
                        if ($idCliente) { break; }
                    }
                }

                // "Recibido de": texto libre siempre presente en el viejo (nombre_ing_egr).
                // Es la fuente que el listado prioriza (COALESCE(recibo_de, cliente.nombre, '—')).
                $reciboDe = self::nz($ie['nombre_ing_egr']);
                // Fallback de cliente por id_cli_pro (referencia directa del ingreso viejo) cuando
                // el ingreso no apunta a una factura. El "recibo de" enlaza al mismo cliente resuelto.
                if (!$idCliente && (int) $ie['id_cli_pro'] > 0) {
                    $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ie['id_cli_pro'], $idEmpresa, $idUsuario, $mysql, $pg) ?: null;
                }
                $idReciboCli = $idCliente;

                // Clasificación: ¿es de "otros conceptos"? (ninguna línea referencia una factura vieja).
                // Los de concepto se guardan como ingreso tipo OTRO + concepto genérico, con detalle de
                // texto libre (tipo_documento='OTRO'), para que la vista NO pinte un documento clickeable
                // con id nulo. Los que sí referencian factura quedan como FACTURA_VENTA (como antes).
                $esConcepto = true;
                foreach ($dets as $d) { if ((int) $d['codigo_documento_cv'] > 0) { $esConcepto = false; break; } }
                if ($esConcepto) {
                    $tipoIngreso = 'OTRO';
                    $idConcepto  = $this->getOrCreateConceptoMigracion($idEmpresa, $idUsuario, 'ingreso', $pg);
                } else {
                    $tipoIngreso = 'FACTURA_VENTA';
                    $idConcepto  = null;
                }

                $fe  = substr((string) $ie['fecha_ing_egr'], 0, 10);
                $amb = $this->ambienteEmpresa($pg, $idEmpresa);
                // Igual que egresos: los ANULADOS del viejo se marcan 'anulado' para no contar como cobro.
                $estado = (stripos((string) $ie['estado'], 'anul') !== false) ? 'anulado' : 'registrado';
                if ($idIngExist) { // re-correr: reconciliar cabecera + reconstruir detalle/pagos
                    $idIng = (int) $idIngExist;
                    $updCab->execute([$fe, $tipoIngreso, $idConcepto, $idCliente, (float) $ie['valor_ing_egr'], self::nz($ie['detalle_adicional']), $estado, $reciboDe, $idReciboCli, $amb, $idUsuario, $idIng]);
                    $delDet->execute([$idIng]);
                    $delPag->execute([$idIng]);
                    $res['ya_migrados']++;
                } else {
                    $insCab->execute([$idEmpresa, $idUsuario, $fe, $sec, (string) $ie['numero_ing_egr'], $tipoIngreso, $idConcepto, $idCliente, (float) $ie['valor_ing_egr'], self::nz($ie['detalle_adicional']), $estado, $reciboDe, $idReciboCli, $amb, $idUsuario]);
                    $idIng = (int) $insCab->fetchColumn();
                    $res['migrados']++;
                }

                foreach ($dets as $d) {
                    $cv = (int) $d['codigo_documento_cv'];
                    if ($cv > 0) { // línea de documento: factura de venta
                        $tdoc   = 'FACTURA';
                        $idRef  = $mapFactura[(string) $cv] ?? null;
                        $numDoc = (preg_match('/(\d{1,3}-\d{1,3}-\d+)/', (string) $d['detalle_ing_egr'], $mnum) ? $mnum[1] : null);
                    } else { // línea de concepto: texto libre, sin documento clickeable
                        $tdoc   = 'OTRO';
                        $idRef  = null;
                        $numDoc = null;
                    }
                    $insDet->execute([$idIng, $tdoc, $idRef, $numDoc, self::nz($d['detalle_ing_egr']), (float) $d['valor_ing_egr'], (float) $d['valor_ing_egr']]);
                }

                $formaStmt->execute([':cd' => $cod]);
                foreach ($formaStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $idForma = $this->resolverFormaCobroPago((int) $f['id_cuenta'], (string) $f['codigo_forma_pago'], $idEmpresa, $idUsuario, $mapCuenta, $mapFormaP, $formaCache, $formaDef, $ctaStmt, $insMapCta, $mysql, $pg);
                    $insPago->execute([$idIng, $idForma, (float) $f['valor_forma_pago'], self::fechaCorta($f['fecha_pago']), ((int) $f['cheque']) ?: null]);
                }

                if (!$idIngExist) {
                    $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idIng, ':cn' => (string) $ie['numero_ing_egr'], ':vin' => 'f', ':cb' => $idUsuario]);
                }
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra pagos (egresos): cabecera + detalle (enlaza compras) + pagos (forma de pago). */
    private function migrarEgresos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'egresos', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done      = $this->idsMigrados($pg, $idEmpresa, 'egresos');
        $mapCompra = $this->mapaDe($pg, $idEmpresa, 'compras');
        $insMap    = $this->stmtMap($pg,'egresos');

        // codigo_documento (string, viejo) → id compra nueva
        $compraPorCod = [];
        foreach ($mysql->query("SELECT id_encabezado_compra, codigo_documento FROM encabezado_compra WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $c) {
            $nid = $mapCompra[(string) (int) $c['id_encabezado_compra']] ?? null;
            if ($nid) { $compraPorCod[(string) $c['codigo_documento']] = $nid; }
        }

        // Formas de pago (mismo catálogo que cobros)
        $formaCache = [];
        foreach ($mysql->query("SELECT id, descripcion FROM opciones_cobros_pagos WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $o) {
            $formaCache[(string) $o['id']] = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, (string) $o['descripcion'], $pg);
        }
        $formaDef = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, 'Efectivo', $pg);

        $detStmt   = $mysql->prepare("SELECT valor_ing_egr, detalle_ing_egr, codigo_documento_cv FROM detalle_ingresos_egresos WHERE codigo_documento = :cd AND tipo_documento = 'EGRESO'");
        $formaStmt = $mysql->prepare("SELECT valor_forma_pago, codigo_forma_pago, id_cuenta, fecha_pago, cheque FROM formas_pagos_ing_egr WHERE codigo_documento = :cd AND tipo_documento = 'EGRESO'");
        // Forma de pago BANCARIA por id_cuenta (ver ingresos): enlaza a la cuenta bancaria migrada.
        $mapCuenta = $this->mapaDe($pg, $idEmpresa, 'cuentas_bancarias');
        $mapFormaP = $this->mapaDe($pg, $idEmpresa, 'formas_pago');
        $ctaStmt   = $mysql->prepare("SELECT id_cuenta, id_banco, id_tipo_cuenta, numero_cuenta FROM cuentas_bancarias WHERE id_cuenta = :id LIMIT 1");
        $insMapCta = $this->stmtMap($pg, 'cuentas_bancarias');

        $mapProv     = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldCompProv = $mysql->prepare("SELECT id_proveedor FROM encabezado_compra WHERE codigo_documento = :cd LIMIT 1");
        $mapEgreso   = $this->mapaDe($pg, $idEmpresa, 'egresos'); // para reconciliar al re-correr
        $updCab      = $pg->prepare("UPDATE egresos_cabecera SET fecha_emision = ?, tipo_egreso = ?, id_egreso_concepto = ?, id_proveedor = ?, monto_total = ?, observaciones = ?, estado = ?, beneficiario_nombre = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $delDet      = $pg->prepare("DELETE FROM egresos_detalle WHERE id_egreso = ?");
        $delPag      = $pg->prepare("DELETE FROM egresos_pagos WHERE id_egreso = ?");

        $insCab  = $pg->prepare("INSERT INTO egresos_cabecera (id_empresa, fecha_emision, numero_egreso, secuencial, tipo_egreso, tipo_sujeto, id_proveedor, id_egreso_concepto, monto_total, observaciones, estado, beneficiario_nombre, tipo_ambiente, created_by) VALUES (?, ?, ?, ?, ?, 'PROVEEDOR', ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $insDet  = $pg->prepare("INSERT INTO egresos_detalle (id_egreso, tipo_documento, id_referencia_documento, numero_documento, descripcion, monto_documento, monto_pagado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insPago = $pg->prepare("INSERT INTO egresos_pagos (id_egreso, id_forma_pago, monto, fecha_cobro, numero_cheque) VALUES (?, ?, ?, ?, ?)");

        $sql = "SELECT id_ing_egr, codigo_documento, numero_ing_egr, valor_ing_egr, fecha_ing_egr, detalle_adicional, estado, nombre_ing_egr, id_cli_pro
                  FROM ingresos_egresos WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND tipo_ing_egr = 'EGRESO'" . $this->clausulaFecha('fecha_ing_egr', $desde, $hasta, $mysql) . " ORDER BY id_ing_egr";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ie = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ie['id_ing_egr'];
            $idEgrExist = $mapEgreso[(string) $old] ?? null;
            $cod = (string) $ie['codigo_documento'];
            $sec = str_pad(preg_replace('/\D+/', '', (string) $ie['numero_ing_egr']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $detStmt->execute([':cd' => $cod]);
                $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);

                // Proveedor: desde la compra vieja referenciada (funciona aunque la compra no se haya migrado)
                $idProv = null;
                foreach ($dets as $d) {
                    $cdv = (string) $d['codigo_documento_cv'];
                    if ($cdv === '') { continue; }
                    $oldCompProv->execute([':cd' => $cdv]);
                    $oldP = (int) $oldCompProv->fetchColumn();
                    if ($oldP > 0) {
                        $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, $oldP, $idEmpresa, $idUsuario, $mysql, $pg);
                        if ($idProv) { break; }
                    }
                }
                // Fallback: proveedor directo del egreso viejo (id_cli_pro apunta a proveedores en EGRESO)
                // cuando el egreso no viene de una compra (otros conceptos).
                if (!$idProv && (int) $ie['id_cli_pro'] > 0) {
                    $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ie['id_cli_pro'], $idEmpresa, $idUsuario, $mysql, $pg) ?: null;
                }
                // "Pagado a" de texto libre: siempre presente en el viejo (nombre_ing_egr). Se muestra en el
                // listado vía COALESCE(proveedor, empleado, beneficiario_nombre, 'N/A') cuando no hay proveedor.
                $benef = self::nz($ie['nombre_ing_egr']);

                // Clasificación: ¿es de "otros conceptos"? (ninguna línea referencia una compra vieja).
                // Los de concepto se guardan como egreso tipo GENERAL + concepto genérico, con detalle de
                // texto libre (tipo_documento='MANUAL'), para que la vista NO pinte un documento clickeable
                // con id nulo. Los que sí referencian compra quedan como COMPRA (como antes).
                $esConcepto = true;
                foreach ($dets as $d) {
                    $cdv = (string) $d['codigo_documento_cv'];
                    if ($cdv !== '' && $cdv !== '0') { $esConcepto = false; break; }
                }
                if ($esConcepto) {
                    $tipoEgreso = 'GENERAL';
                    $idConcepto = $this->getOrCreateConceptoMigracion($idEmpresa, $idUsuario, 'egreso', $pg);
                } else {
                    $tipoEgreso = 'COMPRA';
                    $idConcepto = null;
                }

                $fe  = substr((string) $ie['fecha_ing_egr'], 0, 10);
                $amb = $this->ambienteEmpresa($pg, $idEmpresa);
                // Estado del egreso: los ANULADOS del viejo se marcan 'anulado' para NO contar como
                // pago (el "total_pagado" de compras excluye estado='anulado'); el resto, 'registrado'.
                $estado = (stripos((string) $ie['estado'], 'anul') !== false) ? 'anulado' : 'registrado';
                if ($idEgrExist) { // re-correr: reconciliar cabecera + reconstruir detalle/pagos
                    $idEgr = (int) $idEgrExist;
                    $updCab->execute([$fe, $tipoEgreso, $idConcepto, $idProv, (float) $ie['valor_ing_egr'], self::nz($ie['detalle_adicional']), $estado, $benef, $amb, $idUsuario, $idEgr]);
                    $delDet->execute([$idEgr]);
                    $delPag->execute([$idEgr]);
                    $res['ya_migrados']++;
                } else {
                    $insCab->execute([$idEmpresa, $fe, (string) $ie['numero_ing_egr'], $sec, $tipoEgreso, $idProv, $idConcepto, (float) $ie['valor_ing_egr'], self::nz($ie['detalle_adicional']), $estado, $benef, $amb, $idUsuario]);
                    $idEgr = (int) $insCab->fetchColumn();
                    $res['migrados']++;
                }

                foreach ($dets as $d) {
                    $cdv = (string) $d['codigo_documento_cv'];
                    if ($cdv !== '' && $cdv !== '0') { // línea de documento: compra
                        $tdoc   = 'COMPRA';
                        $idRef  = $compraPorCod[$cdv] ?? null;
                        $numDoc = (preg_match('/(\d{1,3}-\d{1,3}-\d+)/', (string) $d['detalle_ing_egr'], $mnum) ? $mnum[1] : null);
                    } else { // línea de concepto: texto libre, sin documento clickeable
                        $tdoc   = 'MANUAL';
                        $idRef  = null;
                        $numDoc = null;
                    }
                    $insDet->execute([$idEgr, $tdoc, $idRef, $numDoc, self::nz($d['detalle_ing_egr']), (float) $d['valor_ing_egr'], (float) $d['valor_ing_egr']]);
                }

                $formaStmt->execute([':cd' => $cod]);
                foreach ($formaStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $idForma = $this->resolverFormaCobroPago((int) $f['id_cuenta'], (string) $f['codigo_forma_pago'], $idEmpresa, $idUsuario, $mapCuenta, $mapFormaP, $formaCache, $formaDef, $ctaStmt, $insMapCta, $mysql, $pg);
                    $insPago->execute([$idEgr, $idForma, (float) $f['valor_forma_pago'], self::fechaCorta($f['fecha_pago']), ((int) $f['cheque']) ?: null]);
                }

                if (!$idEgrExist) {
                    $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idEgr, ':cn' => (string) $ie['numero_ing_egr'], ':vin' => 'f', ':cb' => $idUsuario]);
                }
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra guías de remisión (cabecera + detalle, sin importes). */
    private function migrarGuias(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'guias', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'guias');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $insMap     = $this->stmtMap($pg,'guias');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO guias_remision_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_transportista, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, placa, fecha_inicio_transporte, fecha_fin_transporte, direccion_partida, direccion_destino, motivo_traslado, num_doc_sustento, tipo_ambiente, estado, created_by)
             VALUES (:e, :est, :pto, :cli, :tra, :u, :fe, :estc, :ptoc, :sec, :clave, :placa, :fini, :ffin, :dpart, :ddest, :mot, :nds, :amb, :estado, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO guias_remision_detalle (id_guia_remision, id_producto, codigo_principal, descripcion, cantidad)
             VALUES (:g, :prod, :cod, :desc, :cant)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad_gr, codigo_producto, nombre_producto FROM cuerpo_gr WHERE ruc_empresa = :r AND serie_gr = :s AND secuencial_gr = :sec");
        $transStmt  = $mysql->prepare("SELECT ruc, nombre, tipo_id FROM clientes WHERE id = :id LIMIT 1");

        $sql = "SELECT id_encabezado_gr, ruc_empresa, fecha_gr, fecha_salida, fecha_llegada, serie_gr, secuencial_gr, factura_aplica, origen, destino, id_transportista, id_cliente, placa, estado_sri, ambiente, aut_sri, motivo
                  FROM encabezado_gr WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_gr', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_gr";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_gr'];
            if ($this->yaMigradoDoc($idEmpresa, 'guias', 'guias_remision_cabecera', $old, $pg)) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_gr']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_gr']), 9, '0', STR_PAD_LEFT);
            $ye = $this->docExistente($pg, 'guias_remision_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }
            $fe    = substr((string) $ec['fecha_gr'], 0, 10);
            $fini  = self::fechaCorta($ec['fecha_salida']) ?: $fe;
            $ffin  = self::fechaCorta($ec['fecha_llegada']) ?: $fe;

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                // Transportista: get-or-create desde el cliente viejo
                $transStmt->execute([':id' => (int) $ec['id_transportista']]);
                $t = $transStmt->fetch(PDO::FETCH_ASSOC);
                $tIdent = $t ? trim((string) $t['ruc']) : '';
                if ($tIdent === '') { throw new \RuntimeException('transportista sin identificación'); }
                $idTra = $this->getOrCreateTransportista($idEmpresa, $idUsuario, $tIdent, (($t['nombre'] ?? '') ?: $tIdent), (trim((string) $t['tipo_id']) ?: '04'), (string) ($ec['placa'] ?? ''));
                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':cli' => $idCliente, ':tra' => $idTra, ':u' => $idUsuario,
                    ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec, ':clave' => self::claveAcceso($ec['aut_sri']),
                    ':placa' => (string) ($ec['placa'] ?? ''), ':fini' => $fini, ':ffin' => $ffin,
                    ':dpart' => (string) ($ec['origen'] ?? ''), ':ddest' => (string) ($ec['destino'] ?? ''),
                    ':mot' => (string) ($ec['motivo'] ?: 'Venta'), ':nds' => self::nz($ec['factura_aplica']),
                    ':amb' => $this->ambienteEmpresa($pg, $idEmpresa), ':estado' => $this->estadoFacturaSri((string) $ec['estado_sri']), ':cb' => $idUsuario,
                ]);
                $idGr = (int) $insCab->fetchColumn();

                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_gr']]);
                foreach ($cuerpoStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
                    $insDet->execute([
                        ':g' => $idGr, ':prod' => $mapProd[(string) $l['id_producto']] ?? null, ':cod' => (string) $l['codigo_producto'],
                        ':desc' => (string) ($l['nombre_producto'] ?: 'ITEM'), ':cant' => (float) $l['cantidad_gr'],
                    ]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idGr, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra liquidaciones de compra (cabecera + detalle + impuestos) resolviendo proveedor. */
    private function migrarLiquidaciones(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'liquidaciones', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done    = $this->idsMigrados($pg, $idEmpresa, 'liquidaciones');
        $mapProv = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap  = $this->stmtMap($pg,'liquidaciones');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldProvRuc   = $mysql->prepare("SELECT ruc_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO liquidaciones_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_proveedor, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, numero_autorizacion, total_sin_impuestos, total_descuento, importe_total, moneda, estado, id_sustento_tributario, tipo_ambiente, created_by)
             VALUES (:e, :est, :pto, :prov, :u, :fe, :estc, :ptoc, :sec, :clave, :aut, :tsi, :tdes, :tot, 'DOLAR', :estado, :sust, :amb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO liquidaciones_detalle (id_cabecera, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:c, :cod, :desc, :cant, :pu, :desc2, :baseCol) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO liquidaciones_detalle_impuestos (id_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT cantidad, valor_unitario, subtotal, tarifa_iva, descuento, codigo_producto, nombre_producto FROM cuerpo_liquidacion WHERE ruc_empresa = :r AND serie_liquidacion = :s AND secuencial_liquidacion = :sec");

        // SUSTENTO TRIBUTARIO: encabezado_liquidacion NO lo trae, pero el mismo doc está en
        // encabezado_compra (id_comprobante=3) con id_sustento. Se cruza por numero_documento
        // (solo dígitos = estab+pto+secuencial). Solo se acepta un id que exista en sustento_tributario.
        $sustValidos = [];
        foreach ($pg->query("SELECT id FROM sustento_tributario") as $s) { $sustValidos[(int) $s['id']] = true; }
        $sustPorNum = [];
        foreach ($mysql->query("SELECT numero_documento, id_sustento FROM encabezado_compra WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base) . " AND id_comprobante = 3") as $r) {
            $k = preg_replace('/\D/', '', (string) $r['numero_documento']);
            if ($k !== '') { $sustPorNum[$k] = (int) $r['id_sustento']; }
        }
        $sustentoDe = function (string $estab, string $pto, string $sec) use ($sustPorNum, $sustValidos): ?int {
            $id = $sustPorNum[$estab . $pto . $sec] ?? 0;
            return ($id > 0 && isset($sustValidos[$id])) ? $id : null;
        };

        // Info adicional (detalle_adicional_liquidacion) + formas de pago SRI (formas_pago_liquidacion),
        // ambas enlazan por serie+secuencial. Precarga en memoria (tablas pequeñas). Idempotente.
        $insAdic = $pg->prepare("INSERT INTO liquidaciones_adicional (id_cabecera, nombre, valor) VALUES (?, ?, ?)");
        $delAdic = $pg->prepare("DELETE FROM liquidaciones_adicional WHERE id_cabecera = ?");
        $insPago = $pg->prepare("INSERT INTO liquidaciones_pagos (id_cabecera, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)");
        $delPago = $pg->prepare("DELETE FROM liquidaciones_pagos WHERE id_cabecera = ?");
        $adicMap = null; $pagosMap = null;
        $precargar = function () use ($mysql, $base, &$adicMap, &$pagosMap): void {
            if ($adicMap !== null) { return; }
            $adicMap = []; $pagosMap = [];
            foreach ($mysql->query("SELECT serie_liquidacion, secuencial_liquidacion, adicional_concepto, adicional_descripcion FROM detalle_adicional_liquidacion WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base) . " ORDER BY id_detalle") as $r) {
                $adicMap[$r['serie_liquidacion'] . '|' . (int) $r['secuencial_liquidacion']][] = ['n' => $r['adicional_concepto'], 'd' => $r['adicional_descripcion']];
            }
            foreach ($mysql->query("SELECT serie_liquidacion, secuencial_liquidacion, id_forma_pago, valor_pago FROM formas_pago_liquidacion WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $r) {
                $pagosMap[$r['serie_liquidacion'] . '|' . (int) $r['secuencial_liquidacion']][] = ['fp' => $r['id_forma_pago'], 'val' => $r['valor_pago']];
            }
        };
        // Copia info adicional + formas de pago de una liquidación (delete+insert, idempotente).
        $migrarHijos = function (int $idLiq, array $ec) use ($precargar, &$adicMap, &$pagosMap, $insAdic, $delAdic, $insPago, $delPago): void {
            $precargar();
            $k = $ec['serie_liquidacion'] . '|' . (int) $ec['secuencial_liquidacion'];
            $delAdic->execute([$idLiq]);
            foreach ($adicMap[$k] ?? [] as $a) {
                $nom = trim((string) $a['n']);
                if ($nom === '') { continue; }
                $insAdic->execute([$idLiq, mb_substr($nom, 0, 300), mb_substr(trim((string) $a['d']), 0, 300)]);
            }
            $delPago->execute([$idLiq]);
            foreach ($pagosMap[$k] ?? [] as $f) {
                $fp = str_pad(trim((string) $f['fp']), 2, '0', STR_PAD_LEFT); // id_forma_pago = código SRI
                if ($fp === '' || $fp === '00') { continue; }
                $insPago->execute([$idLiq, $fp, (float) $f['val'], 0, 'dias']);
            }
        };

        // Reconciliación por mapa (reemplaza yaMigradoDoc): al re-correr completa sustento/estado/
        // ambiente + info adicional + formas de pago de las liquidaciones ya migradas.
        $mapLiq = $this->mapaDe($pg, $idEmpresa, 'liquidaciones');
        $updCab = $pg->prepare("UPDATE liquidaciones_cabecera SET id_proveedor = ?, fecha_emision = ?, estado = ?, id_sustento_tributario = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");

        $sql = "SELECT id_encabezado_liq, ruc_empresa, fecha_liquidacion, serie_liquidacion, secuencial_liquidacion, id_proveedor, estado_sri, total_liquidacion, ambiente, aut_sri
                  FROM encabezado_liquidacion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_liquidacion', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_liq";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_liq'];
            $idLiqExist = $mapLiq[(string) $old] ?? null;
            $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ec['id_proveedor'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idProv) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_liquidacion']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_liquidacion']), 9, '0', STR_PAD_LEFT);
            if (!$idLiqExist) {
                $ye = $this->docExistente($pg, 'liquidaciones_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
                if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }
            }
            $fe     = substr((string) $ec['fecha_liquidacion'], 0, 10);
            $estado = $this->estadoFacturaSri((string) $ec['estado_sri']);
            $sust   = $sustentoDe($estab, $pto, $sec);

            try {
                $pg->beginTransaction();
                if ($idLiqExist) { // re-correr: reconciliar cabecera + info adicional + pagos
                    $idLiq = (int) $idLiqExist;
                    $updCab->execute([$idProv, $fe, $estado, $sust, $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario, $idLiq]);
                    $res['ya_migrados']++;
                } else {
                    $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                    $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                    $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_liquidacion']]);
                    $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                    $tsi = 0.0; $tdes = 0.0;
                    foreach ($lineas as $l) { $tsi += (float) $l['subtotal'] - (float) $l['descuento']; $tdes += (float) $l['descuento']; }

                    $insCab->execute([
                        ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':prov' => $idProv, ':u' => $idUsuario,
                        ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec,
                        ':clave' => self::claveAcceso($ec['aut_sri']), ':aut' => self::numAutorizacion($ec['aut_sri']), ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2),
                        ':tot' => (float) $ec['total_liquidacion'], ':estado' => $estado, ':sust' => $sust,
                        ':amb' => $this->ambienteEmpresa($pg, $idEmpresa), ':cb' => $idUsuario,
                    ]);
                    $idLiq = (int) $insCab->fetchColumn();

                    foreach ($lineas as $l) {
                        $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                        $insDet->execute([
                            ':c' => $idLiq, ':cod' => (string) $l['codigo_producto'], ':desc' => (string) ($l['nombre_producto'] ?: 'ITEM'),
                            ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['valor_unitario'], ':desc2' => (float) $l['descuento'], ':baseCol' => round($base_i, 2),
                        ]);
                        $idDet = (int) $insDet->fetchColumn();
                        $cod = trim((string) $l['tarifa_iva']);
                        $pct = self::IVA_PCT[$cod] ?? 0;
                        $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                    }
                    $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idLiq, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                    $res['migrados']++;
                }

                $migrarHijos($idLiq, $ec); // info adicional + formas de pago SRI
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Migra facturas de venta (cabecera + detalle + impuestos) resolviendo cliente/producto/
     * bodega vía el mapa. Requiere clientes/productos migrados. $limite>0 = solo para pruebas.
     */
    private function migrarFacturas(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $repo  = new \App\repositories\modulos\FacturaVentaRepository();

        $res = ['entidad' => 'facturas', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done       = $this->idsMigrados($pg, $idEmpresa, 'facturas');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapBodega  = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $insMap     = $this->stmtMap($pg,'facturas');

        // Fallback: clientes existentes en el sistema nuevo por identificación (aunque no estén
        // en el mapa de migración; p.ej. creados por el importador XML). Prefiere el no eliminado.
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $cuerpoStmt = $mysql->prepare(
            "SELECT id_producto, cantidad_factura, valor_unitario_factura, subtotal_factura, descuento, tarifa_iva, codigo_producto, nombre_producto, id_bodega, lote, vencimiento
               FROM cuerpo_factura WHERE ruc_empresa = :r AND serie_factura = :s AND secuencial_factura = :sec"
        );
        $prodPorCod = $this->productosPorCodigo($pg, $idEmpresa);

        // Lotes de una factura ya migrada: reaplica numero_lote/fecha_caducidad a las líneas del detalle
        // emparejando por ORDEN (la migración inserta el detalle en el mismo orden del cuerpo viejo).
        // Se usa en la reconciliación (la re-corrida NO reconstruye el detalle de facturas).
        $loteStmt   = $mysql->prepare("SELECT lote, vencimiento FROM cuerpo_factura WHERE ruc_empresa = :r AND serie_factura = :s AND secuencial_factura = :sec ORDER BY id_cuerpo_factura");
        $detIdsStmt = $pg->prepare("SELECT id FROM ventas_detalle WHERE id_venta = ? ORDER BY id");
        $reaplicarLotes = function (int $idVenta, array $ef) use ($loteStmt, $detIdsStmt, $repo): void {
            $loteStmt->execute([':r' => $ef['ruc_empresa'], ':s' => $ef['serie_factura'], ':sec' => $ef['secuencial_factura']]);
            $lotes = $loteStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$lotes) { return; }
            $detIdsStmt->execute([$idVenta]);
            $ids = $detIdsStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $i => $idDet) {
                $l = $lotes[$i] ?? null;
                if ($l === null) { break; }
                $lote = trim((string) $l['lote']);
                // Caducidad a TODAS las líneas: vencimiento, o la fecha de la factura si viene en cero.
                // updateDetalleLoteCaducidad (no updateDetalleLoteNup): la tabla vieja cuerpo_factura
                // no tiene columna de NUP, así que esta reconciliación no debe tocar esa columna —
                // updateDetalleLoteNup la habría forzado a NULL y pisado un NUP cargado a mano.
                $repo->updateDetalleLoteCaducidad((int) $idDet, ($lote !== '' ? mb_substr($lote, 0, 100) : null), self::caducidadODef($l['vencimiento'], $ef['fecha_factura']));
            }
        };

        // Facturas ya migradas POR ESTA HERRAMIENTA (vinculado IS NOT TRUE): al re-correr se les
        // completan las formas de pago, la info adicional y los días de crédito. Las "vinculadas"
        // son documentos nativos del sistema nuevo y no se tocan.
        $mapFact = [];
        $qMap = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'facturas' AND vinculado IS NOT TRUE");
        $qMap->execute([$idEmpresa]);
        foreach ($qMap->fetchAll(PDO::FETCH_ASSOC) as $r) { $mapFact[(string) $r['id_origen']] = (int) $r['id_destino']; }

        // Formas de pago SRI (viejo formas_pago_ventas → nuevo ventas_pagos) e info adicional
        // (viejo detalle_adicional_factura → nuevo ventas_adicional).
        // RENDIMIENTO: la BD vieja está en OTRO servidor y detalle_adicional_factura NO tiene índice
        // (una consulta por factura = escaneo completo de ~1.1M filas + viaje de red). Por eso se
        // PRECARGAN ambas tablas del tenant en memoria UNA sola vez (1 escaneo, no N), agrupadas por
        // serie|secuencial. La clave se normaliza a (int) el secuencial (viejo lo guarda ora varchar
        // ora int). La carga es perezosa: no se toca la BD vieja si no hay facturas que procesar.
        $insPagoV = $pg->prepare("INSERT INTO ventas_pagos (id_venta, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)");
        $delPagoV = $pg->prepare("DELETE FROM ventas_pagos WHERE id_venta = ?");
        $insAdic  = $pg->prepare("INSERT INTO ventas_adicional (id_venta, nombre, valor) VALUES (?, ?, ?)");
        $delAdic  = $pg->prepare("DELETE FROM ventas_adicional WHERE id_venta = ?");

        // Vendedor de cada factura: viejo vendedores_ventas (id_venta = id_encabezado_factura →
        // id_vendedor) → nuevo ventas_cabecera.id_vendedor, mapeando el id viejo al nuevo con el
        // mapa de la entidad 'vendedores' (requiere haberlos migrado antes; si no, queda null).
        $mapVend = $this->mapaDe($pg, $idEmpresa, 'vendedores');

        $pagosMap = null; $adicMap = null; $vendMap = null; // se llenan en la 1ª llamada a $migrarHijos
        $precargar = function () use ($mysql, $base, &$pagosMap, &$adicMap, &$vendMap): void {
            if ($pagosMap !== null) { return; }
            $pagosMap = []; $adicMap = []; $vendMap = [];
            $q = $mysql->query("SELECT serie_factura, secuencial_factura, id_forma_pago, valor_pago FROM formas_pago_ventas WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
            foreach ($q as $r) {
                $k = $r['serie_factura'] . '|' . (int) $r['secuencial_factura'];
                $pagosMap[$k][] = ['fp' => $r['id_forma_pago'], 'val' => $r['valor_pago']];
            }
            $q = $mysql->query("SELECT serie_factura, secuencial_factura, adicional_concepto, adicional_descripcion FROM detalle_adicional_factura WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY id_detalle");
            foreach ($q as $r) {
                $k = $r['serie_factura'] . '|' . (int) $r['secuencial_factura'];
                $adicMap[$k][] = ['n' => $r['adicional_concepto'], 'd' => $r['adicional_descripcion']];
            }
            // Solo las ventas cuyo id_venta es una factura de este tenant (id_venta = id_encabezado_factura).
            $q = $mysql->query("SELECT vv.id_venta, vv.id_vendedor FROM vendedores_ventas vv JOIN encabezado_factura ef ON ef.id_encabezado_factura = vv.id_venta WHERE LEFT(ef.ruc_empresa, 10) = " . $mysql->quote($base));
            foreach ($q as $r) { $vendMap[(int) $r['id_venta']] = (int) $r['id_vendedor']; }
        };
        // Devuelve el id_vendedor NUEVO de una factura vieja (o null). Fuerza la precarga.
        $vendedorDe = function (int $oldFactura) use ($precargar, &$vendMap, $mapVend): ?int {
            $precargar();
            $ov = $vendMap[$oldFactura] ?? 0;
            return ($ov > 0 && isset($mapVend[(string) $ov])) ? (int) $mapVend[(string) $ov] : null;
        };

        // Copia pagos SRI + info adicional de una factura. Idempotente (borra antes de insertar):
        // re-correr la migración completa los datos sin duplicarlos.
        $migrarHijos = function (int $idVenta, array $ef, int $dias) use (&$pagosMap, &$adicMap, $precargar, $insPagoV, $delPagoV, $insAdic, $delAdic): void {
            $precargar();
            $k = $ef['serie_factura'] . '|' . (int) $ef['secuencial_factura'];

            $delPagoV->execute([$idVenta]);
            foreach ($pagosMap[$k] ?? [] as $f) {
                $fp = str_pad(trim((string) $f['fp']), 2, '0', STR_PAD_LEFT);
                if ($fp === '' || $fp === '00') { continue; }
                $insPagoV->execute([$idVenta, $fp, (float) $f['val'], $dias, 'dias']);
            }

            $delAdic->execute([$idVenta]);
            foreach ($adicMap[$k] ?? [] as $a) {
                $nom = trim((string) $a['n']);
                if ($nom === '') { continue; }
                $insAdic->execute([$idVenta, mb_substr($nom, 0, 300), mb_substr(trim((string) $a['d']), 0, 300)]);
            }
        };

        // Días de crédito: el sistema viejo no los guarda por factura, solo el plazo del cliente
        // (clientes.plazo, que sí se migra). Es la misma fuente que usa el sistema nuevo al facturar.
        $qPlazo   = $pg->prepare("SELECT COALESCE(plazo, 0) FROM clientes WHERE id = ?");
        $plazoCli = [];
        $diasDe = function (int $idCliente) use (&$plazoCli, $qPlazo): int {
            if (!array_key_exists($idCliente, $plazoCli)) {
                $qPlazo->execute([$idCliente]);
                $plazoCli[$idCliente] = (int) $qPlazo->fetchColumn();
            }
            return $plazoCli[$idCliente];
        };
        $updCabV = $pg->prepare("UPDATE ventas_cabecera SET dias_credito = ?, id_vendedor = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $qCliDe  = $pg->prepare("SELECT id_cliente FROM ventas_cabecera WHERE id = ?");

        $sql = "SELECT id_encabezado_factura, ruc_empresa, fecha_factura, serie_factura, secuencial_factura, id_cliente, observaciones_factura, estado_sri, total_factura, ambiente, aut_sri, propina
                  FROM encabezado_factura WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_factura', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_factura";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        $stmt = $mysql->query($sql);

        while ($ef = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ef['id_encabezado_factura'];

            // Ya migrada por la herramienta: reconciliar (formas de pago SRI, info adicional y días
            // de crédito). Así una re-corrida completa lo que corridas anteriores no trajeron.
            if (isset($mapFact[(string) $old])) {
                $idExist = $mapFact[(string) $old];
                try {
                    $pg->beginTransaction();
                    $qCliDe->execute([$idExist]);
                    $dias = $diasDe((int) $qCliDe->fetchColumn());
                    $updCabV->execute([$dias, $vendedorDe($old), $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario, $idExist]);
                    $migrarHijos($idExist, $ef, $dias);
                    $reaplicarLotes($idExist, $ef); // completa el lote en facturas ya migradas
                    $pg->commit();
                    $res['ya_migrados']++;
                } catch (Throwable $ex) {
                    if ($pg->inTransaction()) { $pg->rollBack(); }
                    $res['errores']++;
                    if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
                }
                continue;
            }
            if ($this->yaMigradoDoc($idEmpresa, 'facturas', 'ventas_cabecera', $old, $pg)) { $res['ya_migrados']++; continue; }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ef['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; } // cliente viejo sin identificación

            $serie = trim((string) $ef['serie_factura']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $secuencial = str_pad(preg_replace('/\D+/', '', (string) $ef['secuencial_factura']), 9, '0', STR_PAD_LEFT);

            $ye = $this->docExistente($pg, 'ventas_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $secuencial]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$secuencial", $idUsuario); continue; }

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);

                // Detalle: leer líneas y totales
                $cuerpoStmt->execute([':r' => $ef['ruc_empresa'], ':s' => $serie, ':sec' => $ef['secuencial_factura']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $totalSinImp = 0.0; $totalDesc = 0.0;
                foreach ($lineas as $l) {
                    $totalSinImp += (float) $l['subtotal_factura'] - (float) $l['descuento'];
                    $totalDesc   += (float) $l['descuento'];
                }

                $estado = $this->estadoFacturaSri((string) $ef['estado_sri']);
                $idVenta = $repo->insertCabecera([
                    'id_empresa' => $idEmpresa, 'id_establecimiento' => $idEst, 'id_punto_emision' => $idPto,
                    'id_cliente' => $idCliente, 'id_usuario' => $idUsuario,
                    'fecha_emision' => substr((string) $ef['fecha_factura'], 0, 10),
                    'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $secuencial,
                    'total_sin_impuestos' => round($totalSinImp, 2), 'total_descuento' => round($totalDesc, 2),
                    'importe_total' => (float) $ef['total_factura'], 'propina' => (float) $ef['propina'],
                    'moneda' => 'DOLAR', 'estado' => $estado,
                    'observaciones' => self::nz($ef['observaciones_factura']),
                    'clave_acceso' => self::claveAcceso($ef['aut_sri']),
                    'dias_credito' => $diasDe($idCliente),
                    'id_vendedor' => $vendedorDe($old),
                    'tipo_ambiente' => $this->ambienteEmpresa($pg, $idEmpresa),
                    'tipo_registro' => 'migrado',
                ]);

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal_factura'] - (float) $l['descuento'];
                    $idDet = $repo->insertDetalle([
                        'id_venta' => $idVenta,
                        'id_producto' => $this->resolverOCrearProducto($prodPorCod, $mapProd, (int) $l['id_producto'], (string) $l['codigo_producto'], (string) $l['nombre_producto'], trim((string) $l['tarifa_iva']), $idEmpresa, $idUsuario, $pg),
                        'id_bodega' => ((int) $l['id_bodega'] > 0) ? ($mapBodega[(string) $l['id_bodega']] ?? null) : null,
                        'codigo_principal' => (string) $l['codigo_producto'],
                        'descripcion' => (string) $l['nombre_producto'],
                        'cantidad' => (float) $l['cantidad_factura'],
                        'precio_unitario' => (float) $l['valor_unitario_factura'],
                        'descuento' => (float) $l['descuento'],
                        'precio_total_sin_impuesto' => round($base_i, 2),
                    ]);
                    $cod = trim((string) $l['tarifa_iva']); // el valor viejo puede traer espacios
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $repo->insertImpuesto([
                        'id_venta_detalle' => $idDet, 'codigo_impuesto' => '2', 'codigo_porcentaje' => $cod,
                        'tarifa' => $pct, 'base_imponible' => round($base_i, 2), 'valor' => round($base_i * $pct / 100, 2),
                    ]);
                    // Lote / vencimiento por línea (viejo cuerpo_factura.lote/vencimiento → ventas_detalle).
                    // Caducidad a TODAS las líneas: vencimiento, o la fecha de la factura si viene en cero.
                    $lote = trim((string) ($l['lote'] ?? ''));
                    $repo->updateDetalleLoteCaducidad((int) $idDet, ($lote !== '' ? mb_substr($lote, 0, 100) : null), self::caducidadODef($l['vencimiento'] ?? null, $ef['fecha_factura']));
                }

                $migrarHijos($idVenta, $ef, $diasDe($idCliente)); // formas de pago SRI + info adicional

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idVenta, ':cn' => "$estab-$pto-$secuencial", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    private static function xmlEsc($v): string
    {
        return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Normaliza un número de documento a "estab-punto-secuencial" (p.ej. '001001000001108' -> '001-001-000001108'). */
    private static function formatoNumDoc($v): ?string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        if (strlen($d) === 15) { return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6, 9); }
        return self::nz($v);
    }

    /**
     * Reconstruye el detalle_xml (sobre de autorización SRI + comprobante factura en CDATA) de una
     * compra YA migrada, leyendo sus datos del sistema nuevo. Así el PDF (que parsea detalle_xml) y la
     * descarga del XML funcionan para las compras migradas. Es una reconstrucción SIN firma.
     */
    private function generarXmlCompraGuardada(int $idCompra, PDO $pg): void
    {
        $q = $pg->prepare(
            "SELECT cc.establecimiento_prov, cc.punto_emision_prov, cc.secuencial_prov, cc.numero_autorizacion,
                    cc.fecha_emision, cc.tipo_ambiente, cc.importe_total, cc.total_sin_impuestos, cc.tipo_comprobante, cc.documento_modificado,
                    p.razon_social AS prov_razon, p.identificacion AS prov_ruc, COALESCE(p.direccion,'') AS prov_dir,
                    COALESCE(NULLIF(e.nombre_comercial,''), e.nombre) AS emp_razon, e.ruc AS emp_ruc, COALESCE(e.direccion,'') AS emp_dir
               FROM compras_cabecera cc
               JOIN proveedores p ON p.id = cc.id_proveedor
               JOIN empresas e ON e.id = cc.id_empresa
              WHERE cc.id = ? LIMIT 1"
        );
        $q->execute([$idCompra]);
        $c = $q->fetch(PDO::FETCH_ASSOC);
        if (!$c) { return; }

        $qd = $pg->prepare("SELECT id, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto FROM compras_detalle WHERE id_compra = ? ORDER BY id");
        $qd->execute([$idCompra]);
        $qi = $pg->prepare("SELECT codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor FROM compras_detalle_impuestos WHERE id_compra_detalle = ? LIMIT 1");
        $dets = [];
        foreach ($qd->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $qi->execute([(int) $d['id']]);
            $d['imp'] = $qi->fetch(PDO::FETCH_ASSOC) ?: [];
            $dets[] = $d;
        }
        $qp = $pg->prepare("SELECT forma_pago, total, plazo, unidad_tiempo FROM compras_pagos WHERE id_compra = ?");
        $qp->execute([$idCompra]);
        $pagos = $qp->fetchAll(PDO::FETCH_ASSOC);

        $xml = $this->construirDetalleXmlCompra($c, $dets, $pagos);
        $upd = $pg->prepare("UPDATE compras_cabecera SET detalle_xml = ? WHERE id = ?");
        $upd->execute([$xml, $idCompra]);
    }

    /** Arma el string del detalle_xml (sobre + comprobante factura) a partir de los datos ya migrados. */
    private function construirDetalleXmlCompra(array $c, array $dets, array $pagos): string
    {
        $amb    = ((string) $c['tipo_ambiente'] === '2') ? '2' : '1';
        $ambTxt = $amb === '2' ? 'PRODUCCION' : 'PRUEBAS';
        $cod    = trim((string) ($c['tipo_comprobante'] ?? '01')) ?: '01';
        $mapa   = ['01' => ['factura', 'infoFactura'], '03' => ['liquidacionCompra', 'infoLiquidacionCompra'], '04' => ['notaCredito', 'infoNotaCredito'], '05' => ['notaDebito', 'infoNotaDebito']];
        [$rootEl, $infoEl] = $mapa[$cod] ?? ['factura', 'infoFactura'];
        $fEmi   = ($c['fecha_emision'] ? date('d/m/Y', strtotime((string) $c['fecha_emision'])) : '');
        $fAut   = ($c['fecha_emision'] ? date('Y-m-d\TH:i:s', strtotime((string) $c['fecha_emision'])) . '-05:00' : '');
        $n2 = fn($v) => number_format((float) $v, 2, '.', '');
        $n6 = fn($v) => number_format((float) $v, 6, '.', '');

        $comp  = '<?xml version="1.0" encoding="UTF-8"?><' . $rootEl . ' id="comprobante" version="1.1.0">';
        $comp .= '<infoTributaria>';
        $comp .= '<ambiente>' . $amb . '</ambiente><tipoEmision>1</tipoEmision>';
        $comp .= '<razonSocial>' . self::xmlEsc($c['prov_razon']) . '</razonSocial>';
        $comp .= '<ruc>' . self::xmlEsc($c['prov_ruc']) . '</ruc>';
        $comp .= '<claveAcceso>' . self::xmlEsc($c['numero_autorizacion']) . '</claveAcceso>';
        $comp .= '<codDoc>' . $cod . '</codDoc>';
        $comp .= '<estab>' . self::xmlEsc($c['establecimiento_prov']) . '</estab>';
        $comp .= '<ptoEmi>' . self::xmlEsc($c['punto_emision_prov']) . '</ptoEmi>';
        $comp .= '<secuencial>' . self::xmlEsc($c['secuencial_prov']) . '</secuencial>';
        $comp .= '<dirMatriz>' . self::xmlEsc($c['prov_dir']) . '</dirMatriz>';
        $comp .= '</infoTributaria>';
        $comp .= '<' . $infoEl . '>';
        $comp .= '<fechaEmision>' . self::xmlEsc($fEmi) . '</fechaEmision>';
        $comp .= '<tipoIdentificacionComprador>04</tipoIdentificacionComprador>';
        $comp .= '<razonSocialComprador>' . self::xmlEsc($c['emp_razon']) . '</razonSocialComprador>';
        $comp .= '<identificacionComprador>' . self::xmlEsc($c['emp_ruc']) . '</identificacionComprador>';
        $comp .= '<direccionComprador>' . self::xmlEsc($c['emp_dir']) . '</direccionComprador>';
        // Documento modificado (solo NC '04' / ND '05')
        $docMod = trim((string) ($c['documento_modificado'] ?? ''));
        if (in_array($cod, ['04', '05'], true) && $docMod !== '') {
            $comp .= '<codDocModificado>01</codDocModificado>';
            $comp .= '<numDocModificado>' . self::xmlEsc($docMod) . '</numDocModificado>';
            $comp .= '<fechaEmisionDocSustento>' . self::xmlEsc($fEmi) . '</fechaEmisionDocSustento>';
        }
        $comp .= '<totalSinImpuestos>' . $n2($c['total_sin_impuestos']) . '</totalSinImpuestos>';
        $comp .= '<importeTotal>' . $n2($c['importe_total']) . '</importeTotal>';
        if ($pagos) {
            $comp .= '<pagos>';
            foreach ($pagos as $p) {
                $comp .= '<pago><formaPago>' . self::xmlEsc($p['forma_pago']) . '</formaPago><total>' . $n2($p['total']) . '</total>';
                $comp .= '<plazo>' . (int) $p['plazo'] . '</plazo><unidadTiempo>' . self::xmlEsc($p['unidad_tiempo'] ?: 'dias') . '</unidadTiempo></pago>';
            }
            $comp .= '</pagos>';
        }
        $comp .= '</' . $infoEl . '>';
        $comp .= '<detalles>';
        foreach ($dets as $d) {
            $imp = $d['imp'] ?? [];
            $comp .= '<detalle>';
            $comp .= '<codigoPrincipal>' . self::xmlEsc($d['codigo_principal']) . '</codigoPrincipal>';
            $comp .= '<descripcion>' . self::xmlEsc($d['descripcion']) . '</descripcion>';
            $comp .= '<cantidad>' . $n6($d['cantidad']) . '</cantidad>';
            $comp .= '<precioUnitario>' . $n6($d['precio_unitario']) . '</precioUnitario>';
            $comp .= '<descuento>' . $n2($d['descuento']) . '</descuento>';
            $comp .= '<precioTotalSinImpuesto>' . $n2($d['precio_total_sin_impuesto']) . '</precioTotalSinImpuesto>';
            $comp .= '<impuestos><impuesto>';
            $comp .= '<codigo>' . self::xmlEsc($imp['codigo_impuesto'] ?? '2') . '</codigo>';
            $comp .= '<codigoPorcentaje>' . self::xmlEsc($imp['codigo_porcentaje'] ?? '0') . '</codigoPorcentaje>';
            $comp .= '<tarifa>' . $n2($imp['tarifa'] ?? 0) . '</tarifa>';
            $comp .= '<baseImponible>' . $n2($imp['base_imponible'] ?? $d['precio_total_sin_impuesto']) . '</baseImponible>';
            $comp .= '<valor>' . $n2($imp['valor'] ?? 0) . '</valor>';
            $comp .= '</impuesto></impuestos>';
            $comp .= '</detalle>';
        }
        $comp .= '</detalles>';
        $comp .= '</' . $rootEl . '>';

        $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $x .= '<autorizaciones><autorizacion>';
        $x .= '<estado>AUTORIZADO</estado>';
        $x .= '<numeroAutorizacion>' . self::xmlEsc($c['numero_autorizacion']) . '</numeroAutorizacion>';
        $x .= '<fechaAutorizacion>' . self::xmlEsc($fAut) . '</fechaAutorizacion>';
        $x .= '<ambiente>' . $ambTxt . '</ambiente>';
        $x .= '<comprobante><![CDATA[' . $comp . ']]></comprobante>';
        $x .= '</autorizacion></autorizaciones>';
        return $x;
    }

    /**
     * Migra compras (cabecera + detalle + impuestos) resolviendo proveedor vía el mapa.
     * El cuerpo liga por codigo_documento. $limite>0 = solo pruebas.
     */
    private function migrarCompras(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'compras', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done      = $this->idsMigrados($pg, $idEmpresa, 'compras');
        $mapCompra = $this->mapaDe($pg, $idEmpresa, 'compras'); // id viejo → id compra nueva (para reconciliar en re-corrida)
        $mapProv   = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap    = $this->stmtMap($pg,'compras');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldProvRuc   = $mysql->prepare("SELECT ruc_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");

        // Ids válidos de sustento tributario en el sistema nuevo (para no romper la FK; viejo y nuevo comparten ids)
        $sustValidos = [];
        foreach ($pg->query("SELECT id FROM sustento_tributario") as $s) { $sustValidos[(int) $s['id']] = true; }
        // Claves ELECTRÓNICAS (49 díg.) ya usadas en la empresa → id de la compra dueña. El viejo a veces
        // repite la MISMA clave en varias compras (dato duplicado) y el índice único uq_compras_numaut_activo
        // (solo 49 díg.) lo rechaza (23505); la 2ª+ ocurrencia va con numero_autorizacion NULL. Se guarda el
        // id dueño para que al RECONCILIAR una compra no se anule su PROPIA clave. Las FÍSICAS (10 díg.) se
        // comparten entre documentos del talonario y NO se deduplican, así que no se cargan aquí.
        $authUsadas = [];
        foreach ($pg->query("SELECT id, numero_autorizacion FROM compras_cabecera WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false AND numero_autorizacion IS NOT NULL AND length(regexp_replace(numero_autorizacion, '[^0-9]', '', 'g')) = 49") as $a) {
            $authUsadas[(string) $a['numero_autorizacion']] = (int) $a['id'];
        }
        // id_comprobante viejo -> código SRI (01 factura, 03 liquidación, 04 NC, 05 ND...). NO todos son facturas.
        $mapComprobante = [];
        foreach ($mysql->query("SELECT id_comprobante, codigo_comprobante FROM comprobantes_autorizados") as $cc2) {
            $mapComprobante[(int) $cc2['id_comprobante']] = trim((string) $cc2['codigo_comprobante']);
        }

        // Establecimiento propio por defecto = MATRIZ (menor código). Las compras las emite el
        // proveedor (su serie va en *_prov); nuestra atribución de sucursal se inicia en la matriz y
        // se corrige luego con el módulo "Reasignar establecimiento". Los demás documentos migrados
        // ya fijan id_establecimiento; compras faltaba (quedaba NULL).
        $idEstMatriz = (int) $pg->query("SELECT id FROM empresa_establecimiento WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false ORDER BY codigo ASC LIMIT 1")->fetchColumn() ?: null;

        $insCab = $pg->prepare(
            "INSERT INTO compras_cabecera (id_empresa, id_proveedor, establecimiento_prov, punto_emision_prov, secuencial_prov, numero_autorizacion, fecha_emision, fecha_registro, importe_total, total_sin_impuestos, total_descuento, propina, observaciones, tipo_registro, tipo_comprobante, documento_modificado, id_sustento_tributario, autorizacion_desde, autorizacion_hasta, fecha_caducidad, deducible, tipo_ambiente, id_usuario, created_by, id_establecimiento)
             VALUES (:e, :prov, :est, :pto, :sec, :aut, :fe, :fr, :tot, :tsi, :tdes, :prop, :obs, :treg, :tcomp, :docmod, :sust, :ad, :ah, :fcad, :ded, :amb, :u, :cb, :idest) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO compras_detalle (id_compra, id_producto, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:c, NULL, :cod, :desc, :cant, :pu, :desc2, :base) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO compras_detalle_impuestos (id_compra_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT codigo_producto, detalle_producto, cantidad, precio, descuento, impuesto, det_impuesto, subtotal FROM cuerpo_compra WHERE codigo_documento = :cd");
        // (Re)construye el detalle + impuestos de una compra desde el cuerpo viejo. Se usa al
        // RECONCILIAR (re-corrida) para corregir corridas viejas que guardaron mal el IVA.
        $delDetImp = $pg->prepare("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle IN (SELECT id FROM compras_detalle WHERE id_compra = ?)");
        $delDet    = $pg->prepare("DELETE FROM compras_detalle WHERE id_compra = ?");
        // Al re-correr: actualiza el ambiente y los datos tributarios de una compra ya migrada, según la empresa actual
        $updCab = $pg->prepare("UPDATE compras_cabecera SET numero_autorizacion = :aut, tipo_ambiente = :amb, tipo_comprobante = :tcomp, documento_modificado = :docmod, id_sustento_tributario = :sust, autorizacion_desde = :ad, autorizacion_hasta = :ah, fecha_caducidad = :fcad, tipo_registro = :treg, deducible = :ded, id_establecimiento = COALESCE(id_establecimiento, :idest), updated_at = now(), updated_by = :u WHERE id = :id");
        // Formas de pago SRI de la compra: viejo formas_pago_compras → nuevo compras_pagos (enlaza por codigo_documento)
        $fpStmt  = $mysql->prepare("SELECT forma_pago, total_pago, plazo_pago, tiempo_pago FROM formas_pago_compras WHERE codigo_documento = :cd");
        $insPago = $pg->prepare("INSERT INTO compras_pagos (id_compra, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)");
        $delPago = $pg->prepare("DELETE FROM compras_pagos WHERE id_compra = ?");
        $migrarPagos = function (int $idCompra, string $codigoDoc) use ($fpStmt, $insPago, $delPago) {
            $delPago->execute([$idCompra]); // idempotente (re-correr no duplica)
            $fpStmt->execute([':cd' => $codigoDoc]);
            foreach ($fpStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $fp = trim((string) $f['forma_pago']);
                if ($fp === '') { continue; }
                $ut = (stripos((string) $f['tiempo_pago'], 'mes') !== false) ? 'meses' : 'dias';
                $insPago->execute([$idCompra, $fp, (float) $f['total_pago'], (is_numeric($f['plazo_pago']) ? (int) $f['plazo_pago'] : 0), $ut]);
            }
        };

        // Las liquidaciones de compra (id_comprobante = 3, código '03') NO se migran al
        // módulo de compras: tienen su propio módulo (migrarLiquidaciones → liquidaciones_cabecera).
        $sql = "SELECT id_encabezado_compra, codigo_documento, numero_documento, id_proveedor, aut_sri, fecha_compra, fecha_registro, total_compra, propina, id_sustento, `desde`, `hasta`, fecha_caducidad, tipo_comprobante, deducible_en, id_comprobante, factura_aplica_nc_nd
                  FROM encabezado_compra WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND id_comprobante <> 3" . $this->clausulaFecha('fecha_compra', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_compra";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_compra'];
            // Campos tributarios (para insertar o, en re-corrida, actualizar)
            $tc   = strtoupper(trim((string) $ec['tipo_comprobante']));
            $treg = (strpos($tc, 'ELEC') !== false) ? 'electronico' : 'fisica'; // ELECTRÓNICA / FÍSICA
            $sust = isset($sustValidos[(int) $ec['id_sustento']]) ? (int) $ec['id_sustento'] : null;
            $ad   = ((int) $ec['desde'] > 0) ? str_pad((string) (int) $ec['desde'], 9, '0', STR_PAD_LEFT) : null;
            $ah   = ((int) $ec['hasta'] > 0) ? str_pad((string) (int) $ec['hasta'], 9, '0', STR_PAD_LEFT) : null;
            $ded  = (trim((string) $ec['deducible_en']) === '05') ? 'gasto_personal' : 'declaracion_iva'; // 04=deducible IVA, 05=gasto personal
            $tcomp = $mapComprobante[(int) $ec['id_comprobante']] ?? '01'; // 01 factura, 03 liquidación, 04 NC, 05 ND...
            // Documento que modifica (solo NC '04' / ND '05'): viejo factura_aplica_nc_nd (ya viene con guiones)
            $docmod = in_array($tcomp, ['04', '05'], true) ? (trim((string) $ec['factura_aplica_nc_nd']) ?: null) : null;
            // Autorización SRI: física (10 díg.) se conserva tal cual; electrónica (49 díg.) es única por
            // empresa (índice) → se dedup contra otras compras (no contra sí misma).
            $aut = self::numAutorizacion($ec['aut_sri']);
            $autEsElectronica = ($aut !== null && strlen(preg_replace('/\D/', '', $aut)) === 49);
            // Ya migrada: reconciliar ambiente + datos tributarios con la configuración ACTUAL de la empresa (re-corrida)
            if (isset($mapCompra[(string) $old])) {
                $idExist = (int) $mapCompra[(string) $old];
                // dedup electrónica excluyendo la PROPIA compra (su clave ya está en $authUsadas apuntando a sí misma)
                $autRec = ($autEsElectronica && isset($authUsadas[$aut]) && $authUsadas[$aut] !== $idExist) ? null : $aut;
                try {
                    $pg->beginTransaction();
                    $updCab->execute([':aut' => $autRec, ':amb' => $this->ambienteEmpresa($pg, $idEmpresa), ':tcomp' => $tcomp, ':docmod' => $docmod, ':sust' => $sust, ':ad' => $ad, ':ah' => $ah, ':fcad' => self::fechaCorta($ec['fecha_caducidad']), ':treg' => $treg, ':ded' => $ded, ':idest' => $idEstMatriz, ':u' => $idUsuario, ':id' => $idExist]);
                    if ($autEsElectronica && $autRec !== null) { $authUsadas[$aut] = $idExist; }
                    // Rehacer detalle + impuestos desde el cuerpo viejo: corrige el IVA de corridas
                    // viejas que usaban `impuesto` (siempre '2'=12%) en vez de `det_impuesto`.
                    $delDetImp->execute([$idExist]);
                    $delDet->execute([$idExist]);
                    $cuerpoStmt->execute([':cd' => $ec['codigo_documento']]);
                    foreach ($cuerpoStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
                        $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                        $insDet->execute([
                            ':c' => $idExist, ':cod' => (string) $l['codigo_producto'],
                            ':desc' => (string) ($l['detalle_producto'] ?: $l['codigo_producto'] ?: 'ITEM'),
                            ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['precio'],
                            ':desc2' => (float) $l['descuento'], ':base' => round($base_i, 2),
                        ]);
                        $idDet = (int) $insDet->fetchColumn();
                        $cod = (trim((string) $l['impuesto']) === '2') ? trim((string) $l['det_impuesto']) : '0';
                        if (!isset(self::IVA_PCT[$cod])) { $cod = '0'; }
                        $pct = self::IVA_PCT[$cod];
                        $insImp->execute([':d' => $idDet, ':cp' => $cod, ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                    }
                    $migrarPagos($idExist, (string) $ec['codigo_documento']); // formas de pago SRI
                    $this->generarXmlCompraGuardada($idExist, $pg); // reconstruye detalle_xml (PDF/descarga XML)
                    $pg->commit();
                } catch (Throwable $ex) {
                    if ($pg->inTransaction()) { $pg->rollBack(); }
                    $res['errores']++;
                    if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
                }
                $res['ya_migrados']++;
                continue;
            }
            $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ec['id_proveedor'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idProv) { $res['omitidos']++; continue; } // proveedor no migrado

            $num = explode('-', trim((string) $ec['numero_documento']));
            $est = str_pad($num[0] ?? '', 3, '0', STR_PAD_LEFT);
            $pto = str_pad($num[1] ?? '', 3, '0', STR_PAD_LEFT);
            $sec = str_pad(preg_replace('/\D+/', '', $num[2] ?? ''), 9, '0', STR_PAD_LEFT);

            // Clave de identidad de la compra: proveedor + número (estab-punto-secuencial) + tipo de
            // comprobante + FECHA DE EMISIÓN. Sin tipo, una NC(04) hacía match con la factura(01) del mismo
            // número (numeraciones SRI independientes). Sin fecha, el viejo REUSA el mismo número entre años
            // (ej. factura 001-001-000000001 en 2021 y en 2024) y el 2º documento se colapsaba/perdía.
            // Verificado en la base vieja: 61 grupos factura/NC mismo número, y 29 grupos mismo número+tipo
            // con fechas distintas. Con esta clave, cada documento real se migra por separado.
            $fe = substr((string) $ec['fecha_compra'], 0, 10);
            $ye = $this->docExistente($pg, 'compras_cabecera', ['id_empresa' => $idEmpresa, 'id_proveedor' => $idProv, 'establecimiento_prov' => $est, 'punto_emision_prov' => $pto, 'secuencial_prov' => $sec, 'tipo_comprobante' => $tcomp, 'fecha_emision' => $fe]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$est-$pto-$sec", $idUsuario); continue; }

            try {
                $pg->beginTransaction();
                $cuerpoStmt->execute([':cd' => $ec['codigo_documento']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0;
                foreach ($lineas as $l) { $tsi += (float) $l['subtotal'] - (float) $l['descuento']; $tdes += (float) $l['descuento']; }

                // $aut / $autEsElectronica ya calculados arriba. Solo las electrónicas (49 díg.) se deduplican
                // (evita 23505); las físicas (10 díg., compartidas por talonario) se conservan siempre.
                $autIns = ($autEsElectronica && isset($authUsadas[$aut])) ? null : $aut;
                $insCab->execute([
                    ':e' => $idEmpresa, ':prov' => $idProv, ':est' => $est, ':pto' => $pto, ':sec' => $sec,
                    ':aut' => $autIns, ':fe' => $fe,
                    ':fr' => substr((string) $ec['fecha_registro'], 0, 19) ?: null, ':tot' => (float) $ec['total_compra'],
                    ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2), ':prop' => (float) $ec['propina'],
                    ':obs' => null, ':treg' => $treg, ':tcomp' => $tcomp, ':docmod' => $docmod, ':sust' => $sust, ':ad' => $ad, ':ah' => $ah,
                    ':fcad' => self::fechaCorta($ec['fecha_caducidad']), ':ded' => $ded,
                    ':amb' => $this->ambienteEmpresa($pg, $idEmpresa), ':u' => $idUsuario, ':cb' => $idUsuario, ':idest' => $idEstMatriz,
                ]);
                $idCompra = (int) $insCab->fetchColumn();
                if ($autEsElectronica && $autIns !== null) { $authUsadas[$aut] = $idCompra; }

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':c' => $idCompra, ':cod' => (string) $l['codigo_producto'], ':desc' => (string) ($l['detalle_producto'] ?: $l['codigo_producto'] ?: 'ITEM'),
                        ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['precio'], ':desc2' => (float) $l['descuento'], ':base' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    // OJO: en el viejo `impuesto` es el CÓDIGO DEL IMPUESTO SRI (2=IVA, 3=ICE), NO la
                    // tarifa; el codigoPorcentaje (0,2,3,4,5,6,7,8,10) vive en `det_impuesto`. Usar
                    // `impuesto` daba siempre '2' => 12%, cuando lo real suele ser '4' => 15%.
                    $cod = (trim((string) $l['impuesto']) === '2') ? trim((string) $l['det_impuesto']) : '0';
                    if (!isset(self::IVA_PCT[$cod])) { $cod = '0'; } // ICE u otro: sin IVA
                    $pct = self::IVA_PCT[$cod];
                    $insImp->execute([':d' => $idDet, ':cp' => $cod, ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $migrarPagos($idCompra, (string) $ec['codigo_documento']); // formas de pago SRI
                $this->generarXmlCompraGuardada($idCompra, $pg); // reconstruye detalle_xml (PDF/descarga XML)
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idCompra, ':cn' => "$est-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /** Migra notas de crédito (cabecera + detalle + impuestos). Mismo patrón que facturas. */
    private function migrarNotasCredito(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'notas_credito', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'notas_credito');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $insMap     = $this->stmtMap($pg,'notas_credito');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        // Info adicional: viejo detalle_adicional_nc → nuevo notas_credito_adicional. La NC destino
        // NO tiene columna de vendedor ni tabla de formas de pago (las NC no llevan <pagos>), así que
        // solo se completa la info adicional. RENDIMIENTO: detalle_adicional_nc no tiene índice útil
        // → se PRECARGA una vez el tenant en memoria (por serie|secuencial), carga perezosa.
        $insAdicNc = $pg->prepare("INSERT INTO notas_credito_adicional (id_nota_credito, nombre, valor) VALUES (?, ?, ?)");
        $delAdicNc = $pg->prepare("DELETE FROM notas_credito_adicional WHERE id_nota_credito = ?");
        $adicMapNc = null;
        $migrarAdicNc = function (int $idNc, array $ec) use ($mysql, $base, &$adicMapNc, $insAdicNc, $delAdicNc): void {
            if ($adicMapNc === null) {
                $adicMapNc = [];
                $q = $mysql->query("SELECT serie_nc, secuencial_nc, adicional_concepto, adicional_descripcion FROM detalle_adicional_nc WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY id_detalle");
                foreach ($q as $r) { $adicMapNc[$r['serie_nc'] . '|' . (int) $r['secuencial_nc']][] = ['n' => $r['adicional_concepto'], 'd' => $r['adicional_descripcion']]; }
            }
            $delAdicNc->execute([$idNc]); // idempotente
            foreach ($adicMapNc[$ec['serie_nc'] . '|' . (int) $ec['secuencial_nc']] ?? [] as $a) {
                $nom = trim((string) $a['n']);
                if ($nom === '') { continue; }
                $insAdicNc->execute([$idNc, mb_substr($nom, 0, 300), mb_substr(trim((string) $a['d']), 0, 300)]);
            }
        };
        // NC ya migradas por la herramienta (vinculado IS NOT TRUE): al re-correr se les completa la info adicional.
        $mapNc = [];
        $qMapNc = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'notas_credito' AND vinculado IS NOT TRUE");
        $qMapNc->execute([$idEmpresa]);
        foreach ($qMapNc->fetchAll(PDO::FETCH_ASSOC) as $r) { $mapNc[(string) $r['id_origen']] = (int) $r['id_destino']; }

        $insCab = $pg->prepare(
            "INSERT INTO notas_credito_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, cod_doc_modificado, num_doc_modificado, fecha_emision_docs_sustento, motivo, total_sin_impuestos, total_descuento, importe_total, estado, clave_acceso, tipo_ambiente, created_by)
             VALUES (:e, :est, :pto, :cli, :u, :fe, :estc, :ptoc, :sec, '01', :ndm, :fds, :mot, :tsi, :tdes, :tot, :estado, :clave, :amb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO notas_credito_detalle (id_nota_credito, id_producto, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:n, :prod, :cod, :desc, :cant, :pu, :desc2, :baseCol) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO notas_credito_detalle_impuestos (id_nota_credito_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad_nc, valor_unitario_nc, subtotal_nc, descuento, tarifa_iva, codigo_producto, nombre_producto FROM cuerpo_nc WHERE ruc_empresa = :r AND serie_nc = :s AND secuencial_nc = :sec");

        $sql = "SELECT id_encabezado_nc, ruc_empresa, fecha_nc, serie_nc, secuencial_nc, factura_modificada, id_cliente, estado_sri, total_nc, ambiente, aut_sri, motivo, fecha_factura
                  FROM encabezado_nc WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_nc', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_nc";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_nc'];

            // Ya migrada por la herramienta: reconciliar info adicional (completa re-corridas viejas).
            if (isset($mapNc[(string) $old])) {
                try {
                    $pg->beginTransaction();
                    $migrarAdicNc($mapNc[(string) $old], $ec);
                    $pg->commit();
                    $res['ya_migrados']++;
                } catch (Throwable $ex) {
                    if ($pg->inTransaction()) { $pg->rollBack(); }
                    $res['errores']++;
                    if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
                }
                continue;
            }
            if ($this->yaMigradoDoc($idEmpresa, 'notas_credito', 'notas_credito_cabecera', $old, $pg)) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_nc']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_nc']), 9, '0', STR_PAD_LEFT);
            $ye = $this->docExistente($pg, 'notas_credito_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }
            $fe    = substr((string) $ec['fecha_nc'], 0, 10);
            $fds   = substr((string) $ec['fecha_factura'], 0, 10);
            if ($fds === '' || strpos($fds, '0000') === 0) { $fds = $fe; }

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_nc']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0;
                foreach ($lineas as $l) { $tsi += (float) $l['subtotal_nc'] - (float) $l['descuento']; $tdes += (float) $l['descuento']; }

                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':cli' => $idCliente, ':u' => $idUsuario,
                    ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec, ':ndm' => (string) $ec['factura_modificada'],
                    ':fds' => $fds, ':mot' => (string) ($ec['motivo'] ?: 'Migración'), ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2),
                    ':tot' => (float) $ec['total_nc'], ':estado' => $this->estadoFacturaSri((string) $ec['estado_sri']),
                    ':clave' => self::claveAcceso($ec['aut_sri']), ':amb' => ((string) $ec['ambiente'] === '2') ? '2' : '1', ':cb' => $idUsuario,
                ]);
                $idNc = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal_nc'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':n' => $idNc, ':prod' => $mapProd[(string) $l['id_producto']] ?? null, ':cod' => (string) $l['codigo_producto'],
                        ':desc' => (string) $l['nombre_producto'], ':cant' => (float) $l['cantidad_nc'], ':pu' => (float) $l['valor_unitario_nc'],
                        ':desc2' => (float) $l['descuento'], ':baseCol' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['tarifa_iva']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $migrarAdicNc($idNc, $ec); // info adicional

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idNc, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /** Migra retenciones de compra (cabecera + detalle) resolviendo proveedor vía el mapa. */
    private function migrarRetencionesCompra(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'retenciones_compra', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done    = $this->idsMigrados($pg, $idEmpresa, 'retenciones_compra');
        $mapProv = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap  = $this->stmtMap($pg,'retenciones_compra');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldProvRuc   = $mysql->prepare("SELECT ruc_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO retencion_compra_cabecera (id_empresa, id_proveedor, id_usuario, id_establecimiento, id_punto_emision, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, numero_autorizacion, tipo_ambiente, tipo_doc_sustento, num_doc_sustento, fecha_emision_doc_sustento, total_retenido, periodo_fiscal, estado, created_by)
             VALUES (:e, :prov, :u, :est, :pto, :fe, :estc, :ptoc, :sec, :clave, :aut, :amb, :tds, :nds, :fds, :tot, :per, :estado, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO retencion_compra_detalle (id_empresa, id_retencion, codigo_impuesto, codigo_retencion, concepto, base_imponible, porcentaje_retener, valor_retenido)
             VALUES (:e, :r, :ci, :cr, :con, :bi, :pct, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT codigo_impuesto, impuesto, id_retencion, base_imponible, porcentaje_retencion, valor_retenido, nombre_retencion, ejercicio_fiscal FROM cuerpo_retencion WHERE ruc_empresa = :r AND serie_retencion = :s AND secuencial_retencion = :sec");
        // Reconciliación al re-correr: completa periodo_fiscal/estado y reconstruye el detalle de las ya migradas.
        $mapRetC = $this->mapaDe($pg, $idEmpresa, 'retenciones_compra');
        $updCab  = $pg->prepare("UPDATE retencion_compra_cabecera SET id_proveedor = ?, fecha_emision = ?, periodo_fiscal = ?, estado = ?, total_retenido = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $delDet  = $pg->prepare("DELETE FROM retencion_compra_detalle WHERE id_retencion = ?");

        $sql = "SELECT id_encabezado_retencion, ruc_empresa, id_proveedor, serie_retencion, secuencial_retencion, total_retencion, aut_sri, fecha_emision, fecha_documento, tipo_comprobante, numero_comprobante, ambiente, estado_sri
                  FROM encabezado_retencion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_emision', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_retencion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_retencion'];
            $idRetExist = $mapRetC[(string) $old] ?? null;
            $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ec['id_proveedor'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idProv) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_retencion']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_retencion']), 9, '0', STR_PAD_LEFT);
            if (!$idRetExist) {
                $ye = $this->docExistente($pg, 'retencion_compra_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
                if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }
            }
            $fe    = substr((string) $ec['fecha_emision'], 0, 10);
            $fds   = substr((string) $ec['fecha_documento'], 0, 10);
            if ($fds === '' || strpos($fds, '0000') === 0) { $fds = $fe; }
            // Periodo fiscal MM/YYYY desde la fecha (fallback al ejercicio_fiscal del cuerpo).
            $per = ($fe !== '' && strpos($fe, '0000') !== 0) ? (substr($fe, 5, 2) . '/' . substr($fe, 0, 4)) : '';
            // Estado: los migrados ya fueron emitidos → 'autorizada' (o 'anulada' si el viejo lo marca).
            $estado = (stripos((string) $ec['estado_sri'], 'anul') !== false) ? 'anulada' : 'autorizada';

            try {
                $pg->beginTransaction();
                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_retencion']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($per === '' && !empty($lineas[0]['ejercicio_fiscal'])) { $per = (string) $lineas[0]['ejercicio_fiscal']; }
                $per = $per ?: '01/1900';

                if ($idRetExist) { // re-correr: reconciliar cabecera + reconstruir detalle
                    $idRet = (int) $idRetExist;
                    $updCab->execute([$idProv, $fe, $per, $estado, (float) $ec['total_retencion'], $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario, $idRet]);
                    $delDet->execute([$idRet]);
                    $res['ya_migrados']++;
                } else {
                    $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                    $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                    $insCab->execute([
                        ':e' => $idEmpresa, ':prov' => $idProv, ':u' => $idUsuario, ':est' => $idEst, ':pto' => $idPto,
                        ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec, ':clave' => self::claveAcceso($ec['aut_sri']),
                        ':aut' => self::numAutorizacion($ec['aut_sri']), ':amb' => ((string) $ec['ambiente'] === '2') ? '2' : '1',
                        ':tds' => (string) ($ec['tipo_comprobante'] ?: '01'), ':nds' => self::nz($ec['numero_comprobante']),
                        ':fds' => $fds, ':tot' => (float) $ec['total_retencion'], ':per' => $per, ':estado' => $estado, ':cb' => $idUsuario,
                    ]);
                    $idRet = (int) $insCab->fetchColumn();
                    $res['migrados']++;
                }

                foreach ($lineas as $l) {
                    // codigo_impuesto = TIPO SRI (1 renta / 2 iva / 6 isd) desde el texto viejo `impuesto`;
                    // codigo_retencion = el código SRI específico del viejo `codigo_impuesto` (312, 304…).
                    $insDet->execute([
                        ':e' => $idEmpresa, ':r' => $idRet, ':ci' => self::tipoImpuestoRet($l['impuesto']),
                        ':cr' => trim((string) $l['codigo_impuesto']), ':con' => self::nz($l['nombre_retencion']),
                        ':bi' => (float) $l['base_imponible'], ':pct' => (float) $l['porcentaje_retencion'], ':val' => (float) $l['valor_retenido'],
                    ]);
                }

                if (!$idRetExist) { $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idRet, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]); }
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra retenciones de venta (cabecera + detalle) resolviendo cliente vía el mapa. */
    private function migrarRetencionesVenta(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'retenciones_venta', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'retenciones_venta');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $insMap     = $this->stmtMap($pg,'retenciones_venta');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO retencion_venta_cabecera (id_empresa, id_cliente, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, periodo_fiscal, total_isd, total_iva, total_renta, tipo_ambiente, created_by, updated_by)
             VALUES (:e, :cli, :fe, :est, :pto, :sec, :clave, :per, :isd, :iva, :renta, :amb, :cb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO retencion_venta_detalle (id_retencion, cod_doc_sustento, fecha_emision_doc_sustento, codigo_impuesto, codigo_retencion, base_imponible, porcentaje_retencion, valor_retenido, num_doc_sustento)
             VALUES (:r, :cds, :fds, :ci, :cr, :bi, :pct, :val, :nds)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT ejercicio_fiscal, base_imponible, codigo_impuesto, impuesto, porcentaje_retencion, valor_retenido, tipo_documento, numero_documento FROM cuerpo_retencion_venta WHERE codigo_unico = :cu AND LEFT(ruc_empresa, 10) = :base");
        $mapRet = $this->mapaDe($pg, $idEmpresa, 'retenciones_venta'); // para reconciliar al re-correr
        $updCab = $pg->prepare("UPDATE retencion_venta_cabecera SET id_cliente = ?, fecha_emision = ?, periodo_fiscal = ?, total_isd = ?, total_iva = ?, total_renta = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $delDet = $pg->prepare("DELETE FROM retencion_venta_detalle WHERE id_retencion = ?");

        $sql = "SELECT id_encabezado_retencion, ruc_empresa, id_cliente, serie_retencion, secuencial_retencion, aut_sri, fecha_emision, codigo_unico, numero_documento
                  FROM encabezado_retencion_venta WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_emision', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_retencion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_retencion'];
            $idRetExist = $mapRet[(string) $old] ?? null;
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) {
                // El id_cliente viejo es 0/roto (agente de retención no enlazado), pero la clave de acceso
                // (aut_sri, 49 díg.) trae el RUC del EMISOR = el cliente. Se recupera de ahí.
                $idCliente = $this->resolverClientePorClaveAcceso($ec['aut_sri'], $cliPorIdent, $mapCliente, $idEmpresa, $idUsuario, $mysql, $pg);
            }
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_retencion']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_retencion']), 9, '0', STR_PAD_LEFT);
            if (!$idRetExist) {
                $ye = $this->docExistente($pg, 'retencion_venta_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
                if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }
            }
            $fe    = substr((string) $ec['fecha_emision'], 0, 10);
            $per   = ($fe !== '' && strpos($fe, '0000') !== 0) ? (substr($fe, 5, 2) . '/' . substr($fe, 0, 4)) : '';

            try {
                $pg->beginTransaction();
                $cuerpoStmt->execute([':cu' => $ec['codigo_unico'], ':base' => $base]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tRenta = 0.0; $tIva = 0.0; $tIsd = 0.0;
                foreach ($lineas as $l) {
                    $tipo = trim((string) $l['impuesto']);
                    $v = (float) $l['valor_retenido'];
                    if ($tipo === '2') $tIva += $v; elseif ($tipo === '6') $tIsd += $v; else $tRenta += $v;
                }
                if ($per === '' && !empty($lineas[0]['ejercicio_fiscal'])) { $per = (string) $lineas[0]['ejercicio_fiscal']; }

                if ($idRetExist) { // re-correr: reconciliar cabecera + reconstruir detalle
                    $idRet = (int) $idRetExist;
                    $updCab->execute([$idCliente, $fe, ($per ?: '01/1900'), round($tIsd, 2), round($tIva, 2), round($tRenta, 2), $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario, $idRet]);
                    $delDet->execute([$idRet]);
                    $res['ya_migrados']++;
                } else {
                    $insCab->execute([
                        ':e' => $idEmpresa, ':cli' => $idCliente, ':fe' => $fe, ':est' => $estab, ':pto' => $pto, ':sec' => $sec,
                        ':clave' => self::claveAcceso($ec['aut_sri']), ':per' => ($per ?: '01/1900'), ':isd' => round($tIsd, 2),
                        ':iva' => round($tIva, 2), ':renta' => round($tRenta, 2), ':amb' => $this->ambienteEmpresa($pg, $idEmpresa), ':cb' => $idUsuario,
                    ]);
                    $idRet = (int) $insCab->fetchColumn();
                    $res['migrados']++;
                }

                foreach ($lineas as $l) {
                    $insDet->execute([
                        ':r' => $idRet, ':cds' => (string) ($l['tipo_documento'] ?: '01'), ':fds' => $fe,
                        ':ci' => trim((string) $l['impuesto']) ?: '1', ':cr' => trim((string) $l['codigo_impuesto']),
                        ':bi' => (float) $l['base_imponible'], ':pct' => (float) $l['porcentaje_retencion'], ':val' => (float) $l['valor_retenido'],
                        ':nds' => self::formatoNumDoc($l['numero_documento']),
                    ]);
                }

                if (!$idRetExist) { $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idRet, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]); }
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra recibos de venta (cabecera + detalle + impuestos). Patrón de facturas. */
    private function migrarRecibos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'recibos', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [], 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'recibos');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapBodega  = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $insMap     = $this->stmtMap($pg,'recibos');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        // Info adicional (viejo detalle_adicional_recibo, enlaza por id_encabezado_recibo → nuevo
        // recibos_venta_adicional) + vendedor (viejo vendedores_recibos: id_recibo → id_vendedor,
        // mapeado a nuevo vía la entidad 'vendedores') + días de crédito (del cliente, como facturas).
        // El viejo NO tiene formas de pago propias de recibos. RENDIMIENTO: detalle_adicional_recibo
        // (118K filas) no tiene índice útil → se PRECARGA el tenant en memoria una vez (perezoso).
        $mapVend   = $this->mapaDe($pg, $idEmpresa, 'vendedores');
        $insAdicR  = $pg->prepare("INSERT INTO recibos_venta_adicional (id_recibo, nombre, valor) VALUES (?, ?, ?)");
        $delAdicR  = $pg->prepare("DELETE FROM recibos_venta_adicional WHERE id_recibo = ?");
        $adicMapR = null; $vendMapR = null;
        $precargarR = function () use ($mysql, $base, &$adicMapR, &$vendMapR): void {
            if ($adicMapR !== null) { return; }
            $adicMapR = []; $vendMapR = [];
            // detalle_adicional_recibo se enlaza por id_encabezado_recibo, pero esa tabla no trae
            // ruc_empresa → se acota por JOIN al encabezado del tenant.
            $q = $mysql->query("SELECT da.id_encabezado_recibo, da.adicional_concepto, da.adicional_descripcion
                                  FROM detalle_adicional_recibo da JOIN encabezado_recibo er ON er.id_encabezado_recibo = da.id_encabezado_recibo
                                 WHERE LEFT(er.ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY da.id");
            foreach ($q as $r) { $adicMapR[(int) $r['id_encabezado_recibo']][] = ['n' => $r['adicional_concepto'], 'd' => $r['adicional_descripcion']]; }
            $q = $mysql->query("SELECT vr.id_recibo, vr.id_vendedor
                                  FROM vendedores_recibos vr JOIN encabezado_recibo er ON er.id_encabezado_recibo = vr.id_recibo
                                 WHERE LEFT(er.ruc_empresa, 10) = " . $mysql->quote($base));
            foreach ($q as $r) { $vendMapR[(int) $r['id_recibo']] = (int) $r['id_vendedor']; }
        };
        $vendedorRDe = function (int $oldRecibo) use ($precargarR, &$vendMapR, $mapVend): ?int {
            $precargarR();
            $ov = $vendMapR[$oldRecibo] ?? 0;
            return ($ov > 0 && isset($mapVend[(string) $ov])) ? (int) $mapVend[(string) $ov] : null;
        };
        $migrarAdicR = function (int $idRec, int $oldRecibo) use ($precargarR, &$adicMapR, $insAdicR, $delAdicR): void {
            $precargarR();
            $delAdicR->execute([$idRec]); // idempotente
            foreach ($adicMapR[$oldRecibo] ?? [] as $a) {
                $nom = trim((string) $a['n']);
                if ($nom === '') { continue; }
                $insAdicR->execute([$idRec, mb_substr($nom, 0, 300), mb_substr(trim((string) $a['d']), 0, 300)]);
            }
        };
        // Días de crédito del cliente (misma fuente que facturas).
        $qPlazoR = $pg->prepare("SELECT COALESCE(plazo, 0) FROM clientes WHERE id = ?");
        $plazoRCache = [];
        $diasRDe = function (int $idCliente) use (&$plazoRCache, $qPlazoR): int {
            if (!array_key_exists($idCliente, $plazoRCache)) {
                $qPlazoR->execute([$idCliente]);
                $plazoRCache[$idCliente] = (int) $qPlazoR->fetchColumn();
            }
            return $plazoRCache[$idCliente];
        };
        // Recibos ya migrados por la herramienta (vinculado IS NOT TRUE): reconciliar hijos + cabecera.
        $mapRec = [];
        $qMapRec = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'recibos' AND vinculado IS NOT TRUE");
        $qMapRec->execute([$idEmpresa]);
        foreach ($qMapRec->fetchAll(PDO::FETCH_ASSOC) as $r) { $mapRec[(string) $r['id_origen']] = (int) $r['id_destino']; }
        $updCabR = $pg->prepare("UPDATE recibos_venta_cabecera SET id_vendedor = ?, dias_credito = ?, tipo_ambiente = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $qCliRDe = $pg->prepare("SELECT id_cliente FROM recibos_venta_cabecera WHERE id = ?");

        $insCab = $pg->prepare(
            "INSERT INTO recibos_venta_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario, id_vendedor, dias_credito, fecha_emision, establecimiento, punto_emision, secuencial, recibo_numero, con_impuestos, total_sin_impuestos, total_descuento, importe_total, propina, moneda, tipo_ambiente, created_by)
             VALUES (:e, :est, :pto, :cli, :u, :vend, :dias, :fe, :estc, :ptoc, :sec, :num, :ci, :tsi, :tdes, :tot, :prop, 'DOLAR', :amb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO recibos_venta_detalle (id_recibo, id_producto, id_bodega, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:r, :prod, :bod, :cod, :desc, :cant, :pu, :desc2, :baseCol) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO recibos_venta_detalle_impuestos (id_recibo_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad, valor_unitario, subtotal, descuento, tarifa_iva, codigo_producto, nombre_producto, id_bodega FROM cuerpo_recibo WHERE id_encabezado_recibo = :id");

        $sql = "SELECT id_encabezado_recibo, fecha_recibo, serie_recibo, secuencial_recibo, id_cliente, total_recibo, propina
                  FROM encabezado_recibo WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_recibo', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_recibo";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_recibo'];

            // Ya migrado por la herramienta: reconciliar vendedor + días de crédito + info adicional.
            if (isset($mapRec[(string) $old])) {
                $idExist = $mapRec[(string) $old];
                try {
                    $pg->beginTransaction();
                    $qCliRDe->execute([$idExist]);
                    $updCabR->execute([$vendedorRDe($old), $diasRDe((int) $qCliRDe->fetchColumn()), $this->ambienteEmpresa($pg, $idEmpresa), $idUsuario, $idExist]);
                    $migrarAdicR($idExist, $old);
                    $pg->commit();
                    $res['ya_migrados']++;
                } catch (Throwable $ex) {
                    if ($pg->inTransaction()) { $pg->rollBack(); }
                    $res['errores']++;
                    if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
                }
                continue;
            }
            if ($this->yaMigradoDoc($idEmpresa, 'recibos', 'recibos_venta_cabecera', $old, $pg)) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_recibo']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_recibo']), 9, '0', STR_PAD_LEFT);

            $ye = $this->docExistente($pg, 'recibos_venta_cabecera', ['id_empresa' => $idEmpresa, 'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $sec]);
            if ($ye) { $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, $ye, "$estab-$pto-$sec", $idUsuario); continue; }

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                $cuerpoStmt->execute([':id' => $old]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0; $ivaTot = 0.0;
                foreach ($lineas as $l) {
                    $b = (float) $l['subtotal'] - (float) $l['descuento'];
                    $tsi += $b; $tdes += (float) $l['descuento'];
                    $ivaTot += $b * (self::IVA_PCT[trim((string) $l['tarifa_iva'])] ?? 0) / 100;
                }
                $conImp = $ivaTot > 0.005 ? 't' : 'f';

                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':cli' => $idCliente, ':u' => $idUsuario,
                    ':vend' => $vendedorRDe($old), ':dias' => $diasRDe($idCliente),
                    ':fe' => substr((string) $ec['fecha_recibo'], 0, 10), ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec,
                    ':num' => "$estab-$pto-$sec", ':ci' => $conImp, ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2),
                    ':tot' => (float) $ec['total_recibo'], ':prop' => (float) $ec['propina'], ':amb' => $this->ambienteEmpresa($pg, $idEmpresa), ':cb' => $idUsuario,
                ]);
                $idRec = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':r' => $idRec, ':prod' => $mapProd[(string) $l['id_producto']] ?? null,
                        ':bod' => ((int) $l['id_bodega'] > 0) ? ($mapBodega[(string) $l['id_bodega']] ?? null) : null,
                        ':cod' => (string) $l['codigo_producto'], ':desc' => (string) $l['nombre_producto'],
                        ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['valor_unitario'], ':desc2' => (float) $l['descuento'], ':baseCol' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['tarifa_iva']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $migrarAdicR($idRec, $old); // info adicional

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idRec, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Verifica en la base vieja qué facturas están anuladas (estado_sri) y ANULA en el
     * sistema nuevo las que existan (sin importar cómo entraron: XML, migración, manual).
     * Empareja por NÚMERO de factura (establecimiento-punto-secuencial), no por el mapa,
     * y usa el anular() oficial (reversa inventario/asiento/cobros si los hay). Idempotente.
     */
    public function verificarAnuladasFacturas(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['anuladas_en_viejo' => 0, 'anuladas_ahora' => 0, 'ya_anuladas' => 0, 'no_estan_en_nuevo' => 0, 'errores' => 0];

        $facturaService = new \App\Services\modulos\FacturaVentaService(
            new \App\repositories\modulos\FacturaVentaRepository(),
            new \App\Rules\modulos\FacturaVentaRules(),
            new \App\Services\LogSistemaService()
        );

        $chk = $pg->prepare("SELECT id, estado FROM ventas_cabecera WHERE id_empresa = :e AND establecimiento = :est AND punto_emision = :pto AND secuencial = :sec AND eliminado = false LIMIT 1");
        $st  = $mysql->query("SELECT serie_factura, secuencial_factura FROM encabezado_factura WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND UPPER(estado_sri) LIKE '%ANULAD%'");

        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $res['anuladas_en_viejo']++;
            $partes = explode('-', trim((string) $r['serie_factura']));
            $est = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec = str_pad(preg_replace('/\D+/', '', (string) $r['secuencial_factura']), 9, '0', STR_PAD_LEFT);

            $chk->execute([':e' => $idEmpresa, ':est' => $est, ':pto' => $pto, ':sec' => $sec]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $res['no_estan_en_nuevo']++; continue; }
            if ($row['estado'] === 'anulado') { $res['ya_anuladas']++; continue; }
            try {
                $facturaService->anular((int) $row['id'], $idEmpresa, $idUsuario, false); // sin candado SRI
                $res['anuladas_ahora']++;
            } catch (Throwable $e) {
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Cláusula SQL de filtro por rango de fechas (sobre la columna de fecha del documento). */
    private function clausulaFecha(string $col, ?string $desde, ?string $hasta, PDO $mysql): string
    {
        $c = '';
        if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $c .= " AND DATE(`$col`) >= " . $mysql->quote($desde);
        }
        if ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $c .= " AND DATE(`$col`) <= " . $mysql->quote($hasta);
        }
        return $c;
    }

    /** Mapa identificacion => id de los clientes del sistema nuevo (prefiere el no eliminado). */
    private function clientesPorIdentificacion(PDO $pg, int $idEmpresa): array
    {
        $m = [];
        $q = $pg->prepare("SELECT DISTINCT ON (identificacion) identificacion, id FROM clientes WHERE id_empresa = ? ORDER BY identificacion, eliminado, id");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['identificacion']] = (int) $r['id'];
        }
        return $m;
    }

    /** Mapa identificacion => id de los proveedores del sistema nuevo. */
    private function proveedoresPorIdentificacion(PDO $pg, int $idEmpresa): array
    {
        $m = [];
        $q = $pg->prepare("SELECT DISTINCT ON (identificacion) identificacion, id FROM proveedores WHERE id_empresa = ? ORDER BY identificacion, eliminado, id");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['identificacion']] = (int) $r['id'];
        }
        return $m;
    }

    /**
     * Resuelve el id nuevo de un cliente/proveedor: primero por el mapa de migración,
     * y si no está, por identificación contra los existentes en el sistema nuevo.
     */
    private function resolverEntidadPorId(array $mapMig, array $porIdent, \PDOStatement $oldRucStmt, int $oldId): ?int
    {
        if (isset($mapMig[(string) $oldId])) {
            return $mapMig[(string) $oldId];
        }
        $oldRucStmt->execute([':id' => $oldId]);
        $ruc = trim((string) $oldRucStmt->fetchColumn());
        return $ruc !== '' ? ($porIdent[$ruc] ?? null) : null;
    }

    /**
     * Resuelve el cliente de un documento: mapa → identificación → y si no existe, lo CREA
     * desde la base vieja (para que los documentos no fallen aunque no se migraron catálogos).
     * Actualiza $cliPorIdent por referencia. Devuelve id nuevo o null (si el viejo no tiene RUC).
     */
    private function resolverOCrearCliente(array &$cliPorIdent, array $mapMig, int $oldId, int $idEmpresa, int $idUsuario, PDO $mysql, PDO $pg): ?int
    {
        if (isset($mapMig[(string) $oldId])) {
            return $mapMig[(string) $oldId];
        }
        $st = $mysql->prepare("SELECT ruc, nombre, tipo_id, telefono, email, direccion, plazo FROM clientes WHERE id = :id LIMIT 1");
        $st->execute([':id' => $oldId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        $ident = $c ? trim((string) $c['ruc']) : '';
        if ($ident === '') {
            return null;
        }
        if (isset($cliPorIdent[$ident])) {
            return $cliPorIdent[$ident];
        }
        $tipo   = trim((string) $c['tipo_id']) ?: self::inferirTipoId($ident);
        $nombre = trim((string) $c['nombre']) ?: $ident;
        try {
            $ins = $pg->prepare("INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?) RETURNING id");
            $ins->execute([$idEmpresa, $idUsuario, $nombre, $tipo, $ident, self::nz($c['telefono']), self::nz($c['email']), self::nz($c['direccion']), (int) ($c['plazo'] ?? 0), $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM clientes WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
            $q->execute([$idEmpresa, $ident]);
            $id = (int) $q->fetchColumn();
            if (!$id) { return null; }
        }
        $cliPorIdent[$ident] = $id;
        return $id;
    }

    /** Igual que resolverOCrearCliente pero para proveedores. */
    private function resolverOCrearProveedor(array &$provPorIdent, array $mapMig, int $oldId, int $idEmpresa, int $idUsuario, PDO $mysql, PDO $pg): ?int
    {
        if (isset($mapMig[(string) $oldId])) {
            return $mapMig[(string) $oldId];
        }
        $st = $mysql->prepare("SELECT ruc_proveedor, razon_social, nombre_comercial, tipo_id_proveedor, mail_proveedor, dir_proveedor, telf_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");
        $st->execute([':id' => $oldId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        $ident = $c ? trim((string) $c['ruc_proveedor']) : '';
        if ($ident === '') {
            return null;
        }
        if (isset($provPorIdent[$ident])) {
            return $provPorIdent[$ident];
        }
        $tipo = trim((string) $c['tipo_id_proveedor']) ?: self::inferirTipoId($ident);
        $rs   = trim((string) $c['razon_social']) ?: (trim((string) $c['nombre_comercial']) ?: $ident);
        try {
            $ins = $pg->prepare("INSERT INTO proveedores (id_empresa, id_usuario, razon_social, nombre_comercial, tipo_id_proveedor, identificacion, email, direccion, telefono, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $ins->execute([$idEmpresa, $idUsuario, $rs, self::nz($c['nombre_comercial']), $tipo, $ident, self::nz($c['mail_proveedor']), self::nz($c['dir_proveedor']), self::nz($c['telf_proveedor']), $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM proveedores WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
            $q->execute([$idEmpresa, $ident]);
            $id = (int) $q->fetchColumn();
            if (!$id) { return null; }
        }
        $provPorIdent[$ident] = $id;
        return $id;
    }

    /**
     * RUC del emisor embebido en la clave de acceso SRI (49 díg.): posiciones 11–23 (offset 10, largo 13).
     * Estructura: 8 fecha + 2 tipoComprobante + 13 RUC + 1 ambiente + 6 serie + 9 secuencial + 8 código + 1 emisión + 1 verificador.
     */
    private static function rucDeClaveAcceso($autSri): ?string
    {
        $clave = preg_replace('/\D+/', '', (string) $autSri);
        if (strlen($clave) !== 49) { return null; }
        $ruc = substr($clave, 10, 13);
        return preg_match('/^\d{13}$/', $ruc) ? $ruc : null;
    }

    /**
     * Fallback para retenciones de VENTA cuyo id_cliente viejo es 0/roto: el emisor del comprobante
     * (el agente de retención = el cliente) está en la clave de acceso. Se recupera su RUC de ahí y se
     * resuelve/crea el cliente. Reusa resolverOCrearCliente cuando el RUC existe en `clientes` viejo
     * (para traer nombre/datos); si no, crea uno mínimo con la identificación de la clave.
     */
    private function resolverClientePorClaveAcceso($autSri, array &$cliPorIdent, array $mapCliente, int $idEmpresa, int $idUsuario, PDO $mysql, PDO $pg): ?int
    {
        $ruc = self::rucDeClaveAcceso($autSri);
        if ($ruc === null) { return null; }
        if (isset($cliPorIdent[$ruc])) { return $cliPorIdent[$ruc]; }

        // ¿Existe un cliente viejo con ese RUC? → resolver normal (trae nombre/datos + dedup + caché).
        $st = $mysql->prepare("SELECT id FROM clientes WHERE TRIM(ruc) = :r ORDER BY id LIMIT 1");
        $st->execute([':r' => $ruc]);
        $oldId = (int) $st->fetchColumn();
        if ($oldId > 0) {
            return $this->resolverOCrearCliente($cliPorIdent, $mapCliente, $oldId, $idEmpresa, $idUsuario, $mysql, $pg);
        }

        // Sin cliente viejo con ese RUC: crear uno mínimo (identificación desde la clave).
        try {
            $ins = $pg->prepare("INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, status, created_by) VALUES (?, ?, ?, ?, ?, 1, ?) RETURNING id");
            $ins->execute([$idEmpresa, $idUsuario, $ruc, self::inferirTipoId($ruc), $ruc, $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM clientes WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
            $q->execute([$idEmpresa, $ruc]);
            $id = (int) $q->fetchColumn();
            if (!$id) { return null; }
        }
        $cliPorIdent[$ruc] = $id;
        return $id;
    }

    /** Mapa codigo => id de los productos del sistema nuevo. */
    private function productosPorCodigo(PDO $pg, int $idEmpresa): array
    {
        $m = [];
        $q = $pg->prepare("SELECT DISTINCT ON (codigo) codigo, id FROM productos WHERE id_empresa = ? ORDER BY codigo, id");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['codigo']] = (int) $r['id'];
        }
        return $m;
    }

    /**
     * Resuelve el producto de una línea: mapa → código → y si no existe, lo CREA
     * (ventas_detalle.id_producto es NOT NULL). Devuelve siempre un id válido.
     */
    /**
     * id de `tarifa_iva` a partir del CÓDIGO SRI del IVA. Regla del sistema: SIEMPRE por código,
     * nunca por porcentaje (los códigos 0/6/7 comparten 0% y por % serían indistinguibles).
     * `productos.tarifa_iva` guarda ese **id**, NO el código (el id no coincide con el código: p. ej.
     * código '4'=15% es id 7, mientras el id 4 es el código '6'=0%). Respaldo: si el valor no es un
     * código válido pero sí un % inequívoco (dato sucio viejo, p. ej. '15'), se usa. Si no, 0%.
     * `tarifa_iva` es catálogo global → cache estático.
     */
    private function ivaIdPorCodigo(PDO $pg, string $valor): ?int
    {
        static $porCodigo = null, $porPct = null, $default = null;
        if ($porCodigo === null) {
            $porCodigo = []; $porPct = []; $cnt = [];
            foreach ($pg->query("SELECT id, codigo, porcentaje_iva FROM tarifa_iva") as $t) {
                $porCodigo[trim((string) $t['codigo'])] = (int) $t['id'];
                $p = (string) (int) $t['porcentaje_iva'];
                $cnt[$p] = ($cnt[$p] ?? 0) + 1;
                $porPct[$p] = (int) $t['id'];
            }
            foreach ($cnt as $p => $n) { if ($n > 1) { unset($porPct[$p]); } } // 0% ambiguo (0/6/7)
            $default = $porCodigo['0'] ?? null;
        }
        $v = trim($valor);
        if (isset($porCodigo[$v])) { return $porCodigo[$v]; }   // 1º: código SRI
        if ($v !== '' && isset($porPct[$v])) { return $porPct[$v]; } // 2º: % inequívoco (dato sucio)
        return $default;
    }

    private function resolverOCrearProducto(array &$prodPorCod, array $mapProd, int $oldId, string $codigo, string $nombre, string $ivaCode, int $idEmpresa, int $idUsuario, PDO $pg): int
    {
        if (isset($mapProd[(string) $oldId])) {
            return $mapProd[(string) $oldId];
        }
        $codigo = trim($codigo) !== '' ? trim($codigo) : ('MIG-' . $oldId);
        if (isset($prodPorCod[$codigo])) {
            return $prodPorCod[$codigo];
        }
        $iva = $this->ivaIdPorCodigo($pg, $ivaCode); // id de tarifa_iva por CÓDIGO SRI (no el código)
        try {
            $ins = $pg->prepare("INSERT INTO productos (id_empresa, codigo, nombre, codigo_auxiliar, codigo_barras, precio_base, tipo_produccion, tarifa_iva, status, inventariable, id_usuario, created_by) VALUES (?, ?, ?, '', '', 0, '01', ?, 1, false, ?, ?) RETURNING id");
            $ins->execute([$idEmpresa, $codigo, ($nombre !== '' ? $nombre : $codigo), $iva, $idUsuario, $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM productos WHERE id_empresa = ? AND codigo = ? LIMIT 1");
            $q->execute([$idEmpresa, $codigo]);
            $id = (int) $q->fetchColumn();
            if (!$id) { throw $e; }
        }
        $prodPorCod[$codigo] = $id;
        return $id;
    }

    /** tipo_ambiente vigente de la empresa ('1' pruebas / '2' producción). Cacheado. Los documentos
     *  migrados deben llevar el ambiente de la empresa, porque los listados filtran por él. */
    private function ambienteEmpresa(PDO $pg, int $idEmpresa): string
    {
        static $cache = [];
        if (isset($cache[$idEmpresa])) { return $cache[$idEmpresa]; }
        $st = $pg->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ? LIMIT 1");
        $st->execute([$idEmpresa]);
        $v = (string) $st->fetchColumn();
        return $cache[$idEmpresa] = ($v === '2' ? '2' : '1');
    }

    /**
     * ¿El documento ya está migrado (en el mapa)? Si sí, reconcilia su tipo_ambiente con la
     * configuración ACTUAL de la empresa (re-correr la migración = re-estampar el ambiente) y
     * devuelve true. Si no, false. Cachea el mapa por entidad y el statement por tabla.
     */
    private function yaMigradoDoc(int $idEmpresa, string $entidad, string $tabla, int $old, PDO $pg): bool
    {
        static $maps = [];
        $key = $idEmpresa . ':' . $entidad;
        if (!isset($maps[$key])) { $maps[$key] = $this->mapaDe($pg, $idEmpresa, $entidad); }
        if (!isset($maps[$key][(string) $old])) { return false; }
        static $upd = [];
        if (!isset($upd[$tabla])) { $upd[$tabla] = $pg->prepare("UPDATE $tabla SET tipo_ambiente = ? WHERE id = ?"); }
        $upd[$tabla]->execute([$this->ambienteEmpresa($pg, $idEmpresa), (int) $maps[$key][(string) $old]]);
        return true;
    }

    /**
     * ¿Ya existe el documento en el sistema nuevo por su clave natural? Devuelve su id o null.
     * Las columnas son fijas del código (no entran del usuario). Filtra eliminado = false.
     */
    private function docExistente(PDO $pg, string $tabla, array $cond): ?int
    {
        $wh = [];
        $params = [];
        foreach ($cond as $col => $val) {
            $wh[] = "$col = :$col";
            $params[":$col"] = $val;
        }
        $st = $pg->prepare("SELECT id FROM $tabla WHERE " . implode(' AND ', $wh) . " AND eliminado = false LIMIT 1");
        $st->execute($params);
        $r = $st->fetchColumn();
        return $r !== false ? (int) $r : null;
    }

    /**
     * Registra en el mapa un documento que YA EXISTÍA en el sistema nuevo (vinculado, no duplicado)
     * y actualiza los contadores del resultado. Devuelve true (para hacer `continue`).
     */
    private function marcarVinculado(array &$res, array &$done, PDO $pg, int $idEmpresa, int $old, int $idExistente, string $clave, int $idUsuario): bool
    {
        $insMap = $this->stmtMap($pg,(string) $res['entidad']);
        $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idExistente, ':cn' => substr($clave, 0, 120), ':vin' => 't', ':cb' => $idUsuario]);
        $done[(string) $old] = true;
        $res['vinculados'] = ($res['vinculados'] ?? 0) + 1;
        if (!isset($res['vinculados_muestra'])) { $res['vinculados_muestra'] = []; }
        if (count($res['vinculados_muestra']) < 8) { $res['vinculados_muestra'][] = $clave; }
        return true;
    }

    /** Mapa id_origen(string) => id_destino(int) de una entidad ya migrada. */
    private function mapaDe(PDO $pg, int $idEmpresa, string $entidad): array
    {
        $m = [];
        $q = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
        $q->execute([$idEmpresa, $entidad]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['id_origen']] = (int) $r['id_destino'];
        }
        return $m;
    }

    /**
     * Purga del mapa las entradas COLGADAS: aquellas cuyo documento destino ya NO existe físicamente
     * (fue borrado por fuera de esta herramienta, dejando el mapa apuntando a la nada). Sin esto, la
     * reconciliación de la re-corrida cree que el documento "ya está migrado" (lo cuenta en
     * ya_migrados) y NUNCA lo vuelve a insertar → el usuario ve "sí las trae" pero el módulo queda
     * vacío. Al purgar, esos documentos caen en la rama de inserción y se recrean. Devuelve cuántas
     * entradas colgadas se limpiaron. Debe llamarse ANTES de armar el mapa de reconciliación.
     */
    private function purgarMapaColgado(PDO $pg, int $idEmpresa, string $entidad): int
    {
        $tabla = self::DESTINO_TABLA[$entidad] ?? null;
        if ($tabla === null) { return 0; }
        $st = $pg->prepare("DELETE FROM migracion_mysql_map m
             WHERE m.id_empresa = ? AND m.entidad = ?
               AND NOT EXISTS (SELECT 1 FROM $tabla t WHERE t.id = m.id_destino)");
        $st->execute([$idEmpresa, $entidad]);
        return $st->rowCount();
    }

    /**
     * REVIVE (quita el soft-delete) los documentos que la migración MAPEÓ (insertados O vinculados) y
     * que quedaron eliminado=true. Al re-migrar, el histórico del sistema viejo debe estar presente y
     * visible; un documento que el mapa asocia a esa migración pero está borrado deja el módulo
     * incompleto (caso real: 39 facturas vinculadas + eliminadas que no se mostraban). Se incluyen los
     * VINCULADOS porque un vínculo a una fila borrada no cumple su función (representar el doc viejo):
     * reactivarlo la restaura sin duplicar. Solo toca filas en el mapa de ESTA entidad y solo las
     * eliminado=true. Devuelve cuántos revivió.
     */
    private function revivirMigrados(PDO $pg, int $idEmpresa, string $entidad): int
    {
        $tabla = self::DESTINO_TABLA[$entidad] ?? null;
        if ($tabla === null) { return 0; }
        $st = $pg->prepare("UPDATE $tabla t
                SET eliminado = false, deleted_at = NULL, deleted_by = NULL
              WHERE t.id_empresa = ? AND t.eliminado = true
                AND EXISTS (SELECT 1 FROM migracion_mysql_map m
                             WHERE m.id_empresa = t.id_empresa AND m.entidad = ?
                               AND m.id_destino = t.id)");
        $st->execute([$idEmpresa, $entidad]);
        return $st->rowCount();
    }

    private function estadoFacturaSri(string $e): string
    {
        $e = strtoupper(trim($e));
        if (strpos($e, 'ANULAD') !== false) return 'anulado';
        if ($e === 'AUTORIZADO') return 'autorizado';
        return 'autorizado'; // histórico: se asume emitido
    }

    /** Transportista (get-or-create) por (id_empresa, identificacion). */
    private function getOrCreateTransportista(int $idEmpresa, int $idUsuario, string $ident, string $nombre, string $tipoId, string $placa): int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM transportistas WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
        $st->execute([$idEmpresa, $ident]);
        $r = $st->fetchColumn();
        if ($r !== false) {
            return (int) $r;
        }
        $ins = $db->prepare("INSERT INTO transportistas (id_empresa, id_usuario, tipo_id, identificacion, nombre, placa, created_by) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, $idUsuario, $tipoId, $ident, $nombre, $placa, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Establecimiento (get-or-create). Réplica de DocumentoAutomatedRegisterService. */
    private function getEstablecimientoId(int $idEmpresa, string $cod, int $idUsuario): int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM empresa_establecimiento WHERE id_empresa = ? AND codigo = ? LIMIT 1");
        $st->execute([$idEmpresa, $cod]);
        $r = $st->fetchColumn();
        if ($r !== false) return (int) $r;
        $ins = $db->prepare("INSERT INTO empresa_establecimiento (id_empresa, nombre, codigo, direccion, tipo, logo_ruta, leyenda_pdf_titulo, leyenda_pdf_mensaje, created_by, updated_by) VALUES (?, ?, ?, '', 'otro', '', '', '', ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, "Establecimiento $cod", $cod, $idUsuario, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Punto de emisión (get-or-create). */
    private function getPuntoEmisionId(int $idEmpresa, string $estab, string $pto, int $idUsuario): int
    {
        $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM empresa_punto_emision WHERE id_establecimiento = ? AND codigo_punto = ? LIMIT 1");
        $st->execute([$idEst, $pto]);
        $r = $st->fetchColumn();
        if ($r !== false) return (int) $r;
        $ins = $db->prepare("INSERT INTO empresa_punto_emision (id_empresa, id_establecimiento, nombre, codigo_punto, logo_ruta, estado, created_by, updated_by) VALUES (?, ?, ?, ?, '', 'activo', ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, $idEst, "Punto $pto", $pto, $idUsuario, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Ids ya migrados de una entidad (anti-reproceso). */
    private function idsMigrados(PDO $pg, int $idEmpresa, string $entidad): array
    {
        $done = [];
        $q = $pg->prepare("SELECT id_origen FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
        $q->execute([$idEmpresa, $entidad]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $o) {
            $done[(string) $o] = true;
        }
        return $done;
    }

    /** Prepared statement de inserción en el mapa (idempotente). */
    private function stmtMap(PDO $pg, string $entidad): \PDOStatement
    {
        return $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, " . $pg->quote($entidad) . ", :o, :d, :cn, :vin, :cb) ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );
    }

    /** Fecha a 'Y-m-d' o null (descarta ceros / vacíos). */
    private static function fechaCorta($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '0000') === 0) {
            return null;
        }
        return substr($v, 0, 10);
    }

    /** Cadena vacía -> null (para columnas nullable). */
    private static function nz($v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    /**
     * Tipo de impuesto SRI de una línea de retención de compra: el viejo `cuerpo_retencion.impuesto`
     * trae el tipo en TEXTO (RENTA/IVA/ISD). El módulo nuevo clasifica renta/iva/isd por el
     * codigo_impuesto = '1' (Renta) / '2' (IVA) / '6' (ISD). Si ya viniera numérico, se respeta.
     */
    private static function tipoImpuestoRet($v): string
    {
        $s = strtoupper(trim((string) $v));
        if ($s === 'IVA') { return '2'; }
        if ($s === 'ISD') { return '6'; }
        if ($s === 'RENTA') { return '1'; }
        return in_array($s, ['1', '2', '6'], true) ? $s : '1';
    }

    /**
     * Normaliza la CLAVE DE ACCESO SRI: solo es válida si tiene exactamente 49 dígitos. Cualquier otra
     * cosa (vacío, o el placeholder '0' que el sistema viejo usa en docs sin autorización) → NULL, para
     * no chocar con los índices únicos parciales sobre clave_acceso (que aplican WHERE clave_acceso IS
     * NOT NULL): varias retenciones con clave '0' reventaban con 23505.
     */
    private static function claveAcceso($v): ?string
    {
        $s = preg_replace('/\D/', '', (string) $v);
        return (strlen((string) $s) === 49) ? $s : null;
    }

    /**
     * Normaliza el NÚMERO DE AUTORIZACIÓN SRI: válido = al menos 10 dígitos (autorización antigua de 10
     * o clave de 49). Cualquier cosa más corta (vacío, o el placeholder '0' del viejo en docs sin
     * autorización) → NULL, para no chocar con el índice único parcial `uq_compras_numaut_activo`
     * (id_empresa, numero_autorizacion) WHERE numero_autorizacion IS NOT NULL AND <> '' — varias
     * compras con numero_autorizacion='0' reventaban con 23505.
     */
    private static function numAutorizacion($v): ?string
    {
        $s = trim((string) $v);
        return (strlen(preg_replace('/\D/', '', $s)) >= 10) ? $s : null;
    }

    /**
     * fecha_caducidad de un ítem: la del vencimiento; si viene en CERO/basura (nula, 0000, epoch
     * anterior a 2000) usa la FECHA DEL ÍTEM (fallback) — factura para líneas de factura, fecha del
     * movimiento para el kardex. Decisión del usuario: ningún ítem queda sin caducidad.
     */
    private static function caducidadODef($vencimiento, $fechaItem): ?string
    {
        $cad = self::fechaCorta($vencimiento);
        if ($cad === null || $cad < '2000-01-01') { $cad = self::fechaCorta($fechaItem); }
        return $cad;
    }

    /** Infiere el tipo de identificación SRI a partir del número. */
    private static function inferirTipoId(string $ident): string
    {
        $d = preg_replace('/\D+/', '', $ident);
        if (strlen($d) === 13) return '04'; // RUC
        if (strlen($d) === 10) return '05'; // Cédula
        return '06';                         // Pasaporte / otro
    }

    // ─────────────────────────────────────────────────────────────────
    // MIGRACIÓN DE LAS EMPRESAS (registro de las empresas activas del sistema
    // anterior en el nuevo). Se consolida por RUC base (10 díg.): una empresa
    // por contribuyente + N establecimientos. NO se envía ningún correo: solo
    // se marca `notificacion_pendiente`. La invitación del usuario nuevo y los
    // documentos legales se envían cuando el superadmin ACTUALIZA la empresa
    // (ver EmpresasSistemaController::update()).
    // ─────────────────────────────────────────────────────────────────

    /** Asegura la columna `notificacion_pendiente` en `empresas` (idempotente). */
    public static function asegurarColumnaNotificacion(PDO $pg): void
    {
        $pg->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS notificacion_pendiente boolean NOT NULL DEFAULT false");
    }

    /**
     * Lista las empresas del sistema anterior en estado activo (estado='1')
     * cuyo RUC base (10 díg.) todavía NO existe en el sistema nuevo. Agrupa por
     * RUC base: una fila = un contribuyente con todos sus establecimientos.
     * La matriz es el establecimiento más bajo (no siempre 001).
     *
     * @return array<int,array{base:string,ruc:string,nombre:string,razon:string,mail:string,tiene_mail:bool,ests:string,n_est:int}>
     */
    public function listarEmpresasParaMigrar(): array
    {
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        // Bases (RUC 10 díg.) ya presentes en el sistema nuevo → se excluyen.
        $existentes = [];
        foreach ($pg->query("SELECT DISTINCT LEFT(ruc,10) AS b FROM empresas WHERE eliminado = false") as $r) {
            $existentes[(string) $r['b']] = true;
        }

        // Activas del viejo. Se agrupan en PHP para elegir la MATRIZ = est. más bajo.
        $rows = $mysql->query(
            "SELECT id, nombre, nombre_comercial, ruc, mail
               FROM empresas
              WHERE estado = '1'
              ORDER BY ruc"
        )->fetchAll(PDO::FETCH_ASSOC);

        $porBase = [];
        foreach ($rows as $r) {
            $porBase[substr((string) $r['ruc'], 0, 10)][] = $r;
        }

        $out = [];
        foreach ($porBase as $base => $filas) {
            if (isset($existentes[$base])) { continue; } // ya migrada
            $matriz = $filas[0]; // ordenadas por ruc ASC ⇒ establecimiento más bajo
            $ests   = array_map(static fn($f) => substr((string) $f['ruc'], -3), $filas);
            $mail   = trim((string) ($matriz['mail'] ?? ''));
            $nombre = trim((string) ($matriz['nombre_comercial'] ?? '')) ?: trim((string) $matriz['nombre']);
            $out[]  = [
                'base'       => (string) $base,
                'ruc'        => (string) $matriz['ruc'],
                'nombre'     => $nombre,
                'razon'      => trim((string) $matriz['nombre']),
                'mail'       => $mail,
                'tiene_mail' => $mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false,
                'ests'       => implode(', ', $ests),
                'n_est'      => count($filas),
            ];
        }

        usort($out, static fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
        return $out;
    }

    /**
     * Registra en el sistema nuevo las empresas del viejo indicadas por su RUC
     * base. Por cada base: crea la empresa (matriz = est. más bajo), la
     * inicializa, agrega sus establecimientos adicionales, crea el usuario
     * administrador (nivel 2, SIN enviar correo) y la marca `notificacion_pendiente`.
     * Idempotente: si la base ya existe en el nuevo, se omite.
     *
     * @param string[] $basesSeleccionadas  RUC base (10 díg.)
     * @return array{migradas:int,omitidas:int,detalle:array<int,array<string,mixed>>}
     */
    public function migrarEmpresas(array $basesSeleccionadas, int $idUsuario): array
    {
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        self::asegurarColumnaNotificacion($pg);

        $bases = array_values(array_unique(array_filter(
            array_map(static fn($b) => preg_replace('/\D+/', '', (string) $b), $basesSeleccionadas),
            static fn($b) => strlen((string) $b) === 10
        )));

        $res = ['migradas' => 0, 'omitidas' => 0, 'detalle' => []];
        if (!$bases) { return $res; }

        $modelEmpresa  = new \App\models\Empresa();
        $modelUsuario  = new \App\models\Usuario();
        $asignada      = new \App\models\EmpresaAsignada();
        $inicializador = new \App\Services\EmpresaInicializadorService();

        foreach ($bases as $base) {
            $det = ['base' => $base, 'ok' => false, 'nombre' => '', 'establecimientos' => 0, 'usuario' => '', 'msg' => ''];
            try {
                // Idempotencia / doble envío: ¿ya existe la base en el nuevo?
                $chk = $pg->prepare("SELECT 1 FROM empresas WHERE LEFT(ruc,10) = :b AND eliminado = false LIMIT 1");
                $chk->execute([':b' => $base]);
                if ($chk->fetchColumn() !== false) {
                    $res['omitidas']++; $det['msg'] = 'Ya existe en el sistema nuevo.';
                    $res['detalle'][] = $det; continue;
                }

                // Establecimientos activos del contribuyente en el viejo.
                $st = $mysql->prepare(
                    "SELECT id, nombre, nombre_comercial, ruc, direccion, telefono, tipo,
                            nom_rep_legal, ced_rep_legal, mail, cod_prov, cod_ciudad,
                            nombre_contador, ruc_contador
                       FROM empresas
                      WHERE LEFT(ruc,10) = :b AND estado = '1'
                      ORDER BY ruc"
                );
                $st->execute([':b' => $base]);
                $filas = $st->fetchAll(PDO::FETCH_ASSOC);
                if (!$filas) {
                    $res['omitidas']++; $det['msg'] = 'Sin establecimientos activos en el sistema anterior.';
                    $res['detalle'][] = $det; continue;
                }

                $matriz    = $filas[0];
                $estMatriz = substr((string) $matriz['ruc'], -3);
                $det['nombre'] = trim((string) ($matriz['nombre_comercial'] ?: $matriz['nombre']));

                // 1) Crear la empresa (matriz). Empresa::crear() ya inserta el
                //    establecimiento matriz y la bodega Central por defecto.
                $idEmpresa = $modelEmpresa->crear([
                    'nombre'           => (string) $matriz['nombre'],
                    'nombre_comercial' => (string) $matriz['nombre_comercial'],
                    'ruc'              => (string) $matriz['ruc'],
                    'establecimiento'  => $estMatriz,
                    'direccion'        => (string) $matriz['direccion'],
                    'telefono'         => (string) $matriz['telefono'],
                    'tipo'             => (string) ($matriz['tipo'] ?: '1'),
                    'nom_rep_legal'    => (string) $matriz['nom_rep_legal'],
                    'ced_rep_legal'    => (string) $matriz['ced_rep_legal'],
                    'mail'             => (string) $matriz['mail'],
                    'cod_prov'         => (string) $matriz['cod_prov'],
                    'cod_ciudad'       => (string) $matriz['cod_ciudad'],
                    'nombre_contador'  => (string) $matriz['nombre_contador'],
                    'ruc_contador'     => (string) $matriz['ruc_contador'],
                    'estado'           => '1',
                    'estado_pago'      => 'pendiente',
                    'max_usuarios'     => 3,
                    'id_usuario'       => (string) $idUsuario,
                ]);

                // Ambiente producción + marca de notificación pendiente (correo al actualizar).
                $up = $pg->prepare("UPDATE empresas SET tipo_ambiente = 2, notificacion_pendiente = true WHERE id = :id");
                $up->execute([':id' => $idEmpresa]);

                // Asignar al superadmin que migra (paridad con la creación manual).
                $asignada->asignar($idEmpresa, $idUsuario, $idUsuario);

                // 2) Inicializar (punto de emisión, unidades, formas de pago, secuenciales, config facturación).
                $inicializador->inicializar($idEmpresa, $idUsuario);

                // 3) Establecimientos adicionales (los demás; la matriz ya la creó Empresa::crear()).
                $nEst = 1;
                foreach ($filas as $f) {
                    $cod = substr((string) $f['ruc'], -3);
                    if ($cod === $estMatriz) { continue; }
                    $ex = $pg->prepare("SELECT 1 FROM empresa_establecimiento WHERE id_empresa = :e AND codigo = :c AND eliminado = false LIMIT 1");
                    $ex->execute([':e' => $idEmpresa, ':c' => $cod]);
                    if ($ex->fetchColumn() !== false) { continue; }
                    $nom = trim((string) ($f['nombre_comercial'] ?: $f['nombre'])) ?: ('Establecimiento ' . $cod);
                    $ins = $pg->prepare(
                        "INSERT INTO empresa_establecimiento (id_empresa, codigo, nombre, direccion, tipo, estado, created_by, updated_by, created_at, eliminado)
                         VALUES (:e, :c, :n, :d, 'Sucursal', 'activo', :u, :u, NOW(), false)"
                    );
                    $ins->execute([':e' => $idEmpresa, ':c' => $cod, ':n' => $nom, ':d' => (string) $f['direccion'], ':u' => $idUsuario]);
                    $nEst++;
                }
                $det['establecimientos'] = $nEst;

                // 4) Usuario administrador (nivel 2) SIN enviar correo.
                $correo = trim((string) $matriz['mail']);
                if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    if ($modelUsuario->existePorCorreo($correo)) {
                        $det['usuario'] = 'omitido (correo ya registrado)';
                    } else {
                        $nombreU = trim((string) $matriz['nom_rep_legal']);
                        if ($nombreU === '') { $nombreU = trim((string) ($matriz['nombre_comercial'] ?: $matriz['nombre'])) ?: 'Administrador'; }
                        $u = $modelUsuario->crearPorCorreo($nombreU, $correo, $idUsuario, 2);
                        $asignada->asignar($idEmpresa, (int) $u['id'], $idUsuario);
                        $det['usuario'] = 'creado';
                    }
                } else {
                    $det['usuario'] = 'sin correo válido';
                }

                // 5) Registro en el mapa (trazabilidad; no participa de "Eliminar migrados").
                $map = $pg->prepare(
                    "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by, created_at)
                     VALUES (:e, 'empresa_registro', :orig, :dest, :cn, false, :u, NOW())
                     ON CONFLICT DO NOTHING"
                );
                $map->execute([':e' => $idEmpresa, ':orig' => (int) $matriz['id'], ':dest' => $idEmpresa, ':cn' => $base, ':u' => $idUsuario]);

                $det['ok'] = true; $det['id_destino'] = $idEmpresa; $det['msg'] = 'Migrada.';
                $res['migradas']++;
            } catch (Throwable $e) {
                $det['msg'] = 'Error: ' . $e->getMessage();
            }
            $res['detalle'][] = $det;
        }

        return $res;
    }

    // ─────────────────────────────────────────────────────────────────
    // MIGRACIÓN DE USUARIOS (usuarios activos del sistema anterior → usuarios
    // NIVEL 1 en el nuevo). Se dedupe por correo, se omiten los ya registrados,
    // se crean con token de registro (contraseñas viejas son MD5, incompatibles;
    // NO se envía correo) y se asignan a las empresas nuevas que correspondan por
    // RUC (vía empresa_asignada del viejo), si ya están migradas.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Usuarios activos (estado=1) del viejo, con correo, deduplicados por correo y
     * EXCLUYENDO los que ya existen en el nuevo. Incluye las empresas donde estaba
     * (para mostrar y para la asignación por RUC).
     *
     * @return array<int,array{mail:string,nombre:string,cedula:string,telefono:string,n_empresas:int,empresas:string,empresas_en_nuevo:int}>
     */
    public function listarUsuariosParaMigrar(): array
    {
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        // Correos ya en el nuevo → se excluyen (no repetir correo).
        $existentes = [];
        foreach ($pg->query("SELECT LOWER(TRIM(mail)) AS m FROM usuarios WHERE mail IS NOT NULL AND TRIM(mail) <> '' AND eliminado = false") as $r) {
            $existentes[(string) $r['m']] = true;
        }

        // Usuarios activos del viejo con correo.
        $rows = $mysql->query("SELECT id, nombre, cedula, mail, telefono FROM usuarios WHERE estado = 1 AND TRIM(COALESCE(mail,'')) <> '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        // Empresas viejas: id → base RUC + nombre.
        $oldEmp = [];
        foreach ($mysql->query("SELECT id, LEFT(ruc,10) AS base, COALESCE(NULLIF(nombre_comercial,''), nombre) AS nom FROM empresas") as $r) {
            $oldEmp[(int) $r['id']] = ['base' => (string) $r['base'], 'nom' => (string) $r['nom']];
        }
        // empresa_asignada viejo: id_usuario → [id_empresa].
        $asigByUser = [];
        foreach ($mysql->query("SELECT id_usuario, id_empresa FROM empresa_asignada") as $r) {
            $asigByUser[(int) $r['id_usuario']][] = (int) $r['id_empresa'];
        }
        // Bases presentes en el nuevo (para marcar "se asignará").
        $newBases = [];
        foreach ($pg->query("SELECT DISTINCT LEFT(ruc,10) AS b FROM empresas WHERE eliminado = false") as $r) { $newBases[(string) $r['b']] = true; }

        $porMail = [];
        foreach ($rows as $r) {
            $mail = strtolower(trim((string) $r['mail']));
            if (isset($existentes[$mail])) { continue; } // ya registrado en el nuevo
            if (!isset($porMail[$mail])) {
                $porMail[$mail] = ['mail' => trim((string) $r['mail']), 'nombre' => trim((string) $r['nombre']), 'cedula' => trim((string) $r['cedula']), 'telefono' => trim((string) $r['telefono']), 'basesSet' => []];
            }
            foreach ($asigByUser[(int) $r['id']] ?? [] as $oldEmpId) {
                if (isset($oldEmp[$oldEmpId])) { $porMail[$mail]['basesSet'][$oldEmp[$oldEmpId]['base']] = $oldEmp[$oldEmpId]['nom']; }
            }
        }

        $out = [];
        foreach ($porMail as $u) {
            $enNuevo = 0; $nombres = [];
            foreach ($u['basesSet'] as $b => $nom) { $nombres[] = $nom; if (isset($newBases[$b])) { $enNuevo++; } }
            $out[] = [
                'mail'              => $u['mail'],
                'nombre'            => $u['nombre'],
                'cedula'            => $u['cedula'],
                'telefono'          => $u['telefono'],
                'n_empresas'        => count($u['basesSet']),
                'empresas'          => implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? '…' : ''),
                'empresas_en_nuevo' => $enNuevo,
            ];
        }
        usort($out, static fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
        return $out;
    }

    /**
     * Crea en el nuevo los usuarios indicados por correo, como NIVEL 1, con token de
     * registro (sin enviar correo), y los asigna a las empresas nuevas que correspondan
     * por RUC. Idempotente: omite los correos que ya existen en el nuevo.
     *
     * @param string[] $mailsSeleccionados
     * @return array{migrados:int,omitidos:int,asignaciones:int,detalle:array<int,array<string,mixed>>}
     */
    public function migrarUsuarios(array $mailsSeleccionados, int $idUsuario): array
    {
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $mails = array_values(array_unique(array_filter(array_map(static fn($m) => strtolower(trim((string) $m)), $mailsSeleccionados), static fn($m) => $m !== '')));
        $res = ['migrados' => 0, 'omitidos' => 0, 'asignaciones' => 0, 'detalle' => []];
        if (!$mails) { return $res; }

        $asignada = new \App\models\EmpresaAsignada();

        // Correos y cédulas ya usados (unicidad).
        $mailUsados = []; $cedUsadas = [];
        foreach ($pg->query("SELECT LOWER(TRIM(mail)) AS m, cedula FROM usuarios WHERE eliminado = false") as $r) {
            if ($r['m'] !== null) { $mailUsados[(string) $r['m']] = true; }
            if ($r['cedula'] !== null) { $cedUsadas[(string) $r['cedula']] = true; }
        }
        // Empresas nuevas por base RUC + empresas viejas id → base.
        $newEmpByBase = [];
        foreach ($pg->query("SELECT id, LEFT(ruc,10) AS b FROM empresas WHERE eliminado = false") as $r) { $newEmpByBase[(string) $r['b']][] = (int) $r['id']; }
        $oldEmpBase = [];
        foreach ($mysql->query("SELECT id, LEFT(ruc,10) AS b FROM empresas") as $r) { $oldEmpBase[(int) $r['id']] = (string) $r['b']; }

        $selUser = $mysql->prepare("SELECT id, nombre, cedula, telefono FROM usuarios WHERE estado = 1 AND LOWER(TRIM(mail)) = :m ORDER BY id LIMIT 1");
        $selAsig = $mysql->prepare("SELECT DISTINCT ea.id_empresa FROM empresa_asignada ea JOIN usuarios u ON u.id = ea.id_usuario WHERE LOWER(TRIM(u.mail)) = :m AND u.estado = 1");
        $insUser = $pg->prepare("INSERT INTO usuarios (nombre, cedula, password, nivel, estado, mail, token, telefono) VALUES (:n, :c, :p, 1, 1, :m, :t, :tel) RETURNING id");
        $insUA   = $pg->prepare("INSERT INTO usuario_asignado (id_usuario, id_adm) SELECT :u, :a WHERE NOT EXISTS (SELECT 1 FROM usuario_asignado WHERE id_usuario = :u AND id_adm = :a)");

        foreach ($mails as $mail) {
            $det = ['mail' => $mail, 'ok' => false, 'nombre' => '', 'asignadas' => 0, 'msg' => ''];
            try {
                if (isset($mailUsados[$mail])) { $res['omitidos']++; $det['msg'] = 'Ya registrado en el sistema nuevo.'; $res['detalle'][] = $det; continue; }
                $selUser->execute([':m' => $mail]);
                $u = $selUser->fetch(PDO::FETCH_ASSOC);
                if (!$u) { $res['omitidos']++; $det['msg'] = 'No se encontró usuario activo con ese correo.'; $res['detalle'][] = $det; continue; }

                $det['nombre'] = trim((string) $u['nombre']);
                $ced = trim((string) $u['cedula']);
                if ($ced === '' || isset($cedUsadas[$ced])) { $ced = substr(md5($mail), 0, 15); } // cédula única obligatoria
                $token  = bin2hex(random_bytes(16));
                $hash   = password_hash($token, PASSWORD_DEFAULT) ?: md5($token);
                $nombre = trim((string) $u['nombre']) !== '' ? trim((string) $u['nombre']) : $mail;
                $tel    = trim((string) $u['telefono']); // NOT NULL → '' si vacío

                $pg->beginTransaction();
                $insUser->execute([':n' => $nombre, ':c' => $ced, ':p' => $hash, ':m' => $mail, ':t' => $token, ':tel' => $tel]);
                $idNuevo = (int) $insUser->fetchColumn();
                $insUA->execute([':u' => $idNuevo, ':a' => $idUsuario]);

                // Asignar a las empresas nuevas por RUC (empresas viejas donde estaba, activas o no).
                $selAsig->execute([':m' => $mail]);
                $bases = [];
                foreach ($selAsig->fetchAll(PDO::FETCH_COLUMN) as $oe) { $b = $oldEmpBase[(int) $oe] ?? null; if ($b !== null) { $bases[$b] = true; } }
                foreach (array_keys($bases) as $b) {
                    foreach ($newEmpByBase[$b] ?? [] as $ne) { $asignada->asignar($ne, $idNuevo, $idUsuario); $det['asignadas']++; $res['asignaciones']++; }
                }
                $pg->commit();

                $mailUsados[$mail] = true; $cedUsadas[$ced] = true;
                $det['ok'] = true; $det['msg'] = 'Creado (nivel 1).'; $res['migrados']++;
            } catch (Throwable $e) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $det['msg'] = 'Error: ' . substr($e->getMessage(), 0, 150);
            }
            $res['detalle'][] = $det;
        }

        return $res;
    }
}
