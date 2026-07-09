<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Etiquetas amigables para la auditoría: traduce nombres técnicos de tablas a
 * nombres de módulo legibles y normaliza las acciones.
 *
 * Seguridad: el nombre real de la tabla NUNCA se envía al cliente. En los
 * selects/listados/exportes se muestra la etiqueta; para filtrar se usa un
 * código opaco (codigo()) que el servidor vuelve a resolver contra la BD.
 */
class AuditoriaEtiquetas
{
    /** Mapa tabla → nombre de módulo (Español). */
    private const MAP = [
        'asientos_contables_cabecera'    => 'Asientos contables',
        'asientos_preferencia_empresa'   => 'Preferencias de contabilización',
        'asientos_programados'           => 'Asientos programados',
        'asientos_tipo'                  => 'Asientos tipo',
        'asistencia_puntos'              => 'Puntos de asistencia',
        'ats'                            => 'Anexo ATS',
        'auditoria_contable_corridas'    => 'Auditoría contable',
        'automatizaciones'               => 'Automatizaciones',
        'bodegas'                        => 'Bodegas',
        'carwash_ordenes'                => 'Órdenes de car wash',
        'cat_obligaciones'               => 'Obligaciones',
        'categorias'                     => 'Categorías',
        'centro_costos'                  => 'Centros de costo',
        'citas'                          => 'Citas',
        'citas_config_portal'            => 'Configuración de citas',
        'citas_horarios'                 => 'Horarios de citas',
        'citas_pagos'                    => 'Pagos de citas',
        'citas_recursos'                 => 'Recursos de citas',
        'citas_tipos'                    => 'Tipos de cita',
        'clientes'                       => 'Clientes',
        'compras_cabecera'               => 'Compras',
        'consignaciones_facturas'        => 'Facturación de consignaciones',
        'consignaciones_ventas'          => 'Consignaciones de venta',
        'egresos_cabecera'               => 'Egresos',
        'empleados'                      => 'Empleados',
        'empresa_opciones_ingreso_egreso' => 'Opciones de ingreso/egreso',
        'factura_express_plantillas'     => 'Plantillas de factura exprés',
        'factura_express_solicitudes'    => 'Solicitudes de factura exprés',
        'firmas_electronicas'            => 'Firmas electrónicas',
        'guias_remision_cabecera'        => 'Guías de remisión',
        'ingresos_cabecera'              => 'Ingresos',
        'inventario_cargas'              => 'Cargas de inventario',
        'inventario_kardex'              => 'Kardex de inventario',
        'liquidaciones_cabecera'         => 'Liquidaciones de compra',
        'marcas'                         => 'Marcas',
        'mesas'                          => 'Mesas',
        'notas_credito_cabecera'         => 'Notas de crédito',
        'novedades'                      => 'Novedades de nómina',
        'ordenes_compra'                 => 'Órdenes de compra',
        'periodos_contables'             => 'Períodos contables',
        'plan_cuentas'                   => 'Plan de cuentas',
        'productos'                      => 'Productos',
        'productos_homologacion'         => 'Homologación de productos',
        'proformas_cabecera'             => 'Proformas',
        'proveedores'                    => 'Proveedores',
        'proyectos'                      => 'Proyectos',
        'recibos_venta_cabecera'         => 'Recibos de venta',
        'responsables_traslado'          => 'Responsables de traslado',
        'retencion_compra_cabecera'      => 'Retenciones de compra',
        'retencion_venta_cabecera'       => 'Retenciones de venta',
        'retenciones_sri'                => 'Retenciones SRI',
        'retornos_cv'                    => 'Retornos de venta',
        'rol_cabecera'                   => 'Roles de pago',
        'saldos_iniciales_anticipos'     => 'Saldos iniciales (anticipos)',
        'saldos_iniciales_cxc'           => 'Saldos iniciales (CxC)',
        'sri_lotes'                      => 'Envíos en lote SRI',
        'suscripciones'                  => 'Suscripciones',
        'tareas'                         => 'Tareas',
        'tipo_medida'                    => 'Tipos de medida',
        'transportistas'                 => 'Transportistas',
        'unidades_medida'                => 'Unidades de medida',
        'vacaciones'                     => 'Vacaciones',
        'vehiculos'                      => 'Vehículos',
        'vendedores'                     => 'Vendedores',
        'ventas_cabecera'                => 'Facturas de venta',
        'videos_ayuda'                   => 'Videos de ayuda',
    ];

    /** Etiqueta de módulo para una tabla (nunca expone el nombre técnico literal). */
    public static function tabla(string $tabla): string
    {
        $t = trim($tabla);
        if ($t === '') {
            return '—';
        }
        if (isset(self::MAP[$t])) {
            return self::MAP[$t];
        }
        // Fallback para tablas no mapeadas: sin sufijos técnicos ni guiones bajos.
        $t = preg_replace('/_(cabecera|detalle|det|cab|encabezado)$/', '', $t) ?? $t;
        $t = trim(str_replace('_', ' ', $t));
        return $t === '' ? '—' : self::ucfirst($t);
    }

    /** Código opaco y estable para usar como valor de filtro en el cliente. */
    public static function codigo(string $tabla): string
    {
        return substr(sha1('tbl:' . trim($tabla)), 0, 12);
    }

    /** Normaliza la acción para mostrar (casing consistente, sin guiones bajos). */
    public static function accion(string $accion): string
    {
        $a = trim($accion);
        if ($a === '') {
            return '—';
        }
        $a = str_replace('_', ' ', mb_strtolower($a, 'UTF-8'));
        return self::ucfirst($a);
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }
}
