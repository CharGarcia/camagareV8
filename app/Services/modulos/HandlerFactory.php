<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Services\modulos\Handlers\BaseHandler;

/**
 * Registro central de módulos, acciones y sus handlers.
 *
 * Los handlers procesan TODOS los documentos pendientes en lotes internos
 * hasta vaciar la cola — no hay límite fijo por ejecución.
 * El parámetro 'lote_interno' solo controla cuántos registros se cargan en
 * memoria por vuelta (protección ante tablas muy grandes), no el total.
 */
class HandlerFactory
{
    // ── Acciones reutilizables para módulos de documentos electrónicos ────────
    private static function accionesDocumento(): array
    {
        return [
            'enviar_sri' => [
                'label'       => 'Enviar al SRI',
                'descripcion' => 'Autoriza y envía al SRI todos los documentos en estado borrador',
                'handler'     => Handlers\DocumentosHandler::class,
                'parametros'  => [],
            ],
            'enviar_correo' => [
                'label'       => 'Enviar correo',
                'descripcion' => 'Envía por email todos los documentos autorizados con correo pendiente',
                'handler'     => Handlers\DocumentosHandler::class,
                'parametros'  => [
                    ['key' => 'reintentar_fallidos', 'label' => 'Reintentar los que fallaron antes', 'tipo' => 'checkbox', 'default' => true,
                     'ayuda' => 'Si está activo, vuelve a intentar enviar los documentos cuyo correo falló en ejecuciones anteriores. Recomendado: activado.'],
                ],
            ],
        ];
    }

    private static array $catalogo = [];

    private static function getCatalogo(): array
    {
        if (!empty(self::$catalogo)) {
            return self::$catalogo;
        }

        $accionesDoc = self::accionesDocumento();

        self::$catalogo = [

            'facturas_venta' => [
                'label'   => 'Facturas de venta',
                'icono'   => 'fa-file-invoice',
                'acciones' => $accionesDoc,
            ],

            'retenciones_compras' => [
                'label'   => 'Retenciones en compras',
                'icono'   => 'fa-file-contract',
                'acciones' => $accionesDoc,
            ],

            'notas_credito' => [
                'label'   => 'Notas de crédito',
                'icono'   => 'fa-file-minus',
                'acciones' => $accionesDoc,
            ],

            'liquidaciones_compras' => [
                'label'   => 'Liquidaciones en compras',
                'icono'   => 'fa-file-alt',
                'acciones' => $accionesDoc,
            ],

            'guias_remision' => [
                'label'   => 'Guías de remisión',
                'icono'   => 'fa-truck',
                'acciones' => $accionesDoc,
            ],

            'whatsapp' => [
                'label'   => 'Avisos de WhatsApp',
                'icono'   => 'fa-whatsapp',
                'acciones' => [
                    'aviso_mensajes_no_leidos' => [
                        'label'       => 'Avisar mensajes no leídos',
                        'descripcion' => 'Envía un aviso (por WhatsApp) a los números configurados cuando hay chats con mensajes sin leer durante más del umbral definido en la configuración de WhatsApp.',
                        'handler'     => Handlers\WhatsappHandler::class,
                        'parametros'  => [],
                    ],
                ],
            ],

            'descargas_sri' => [
                'label'   => 'Descargas del SRI',
                'icono'   => 'fa-cloud-download-alt',
                'acciones' => [
                    'ejecutar_descarga' => [
                        'label'       => 'Sincronizar documentos recibidos',
                        'descripcion' => 'Ejecuta el robot en segundo plano respetando la configuración del módulo Descargas SRI.',
                        'handler'     => Handlers\DescargasSriHandler::class,
                        'parametros'  => [],
                    ],
                ],
            ],

            'cuentas_por_cobrar' => [
                'label'   => 'Cuentas por cobrar',
                'icono'   => 'fa-hand-holding-usd',
                'acciones' => [
                    'enviar_estado_correo' => [
                        'label'       => 'Enviar estado de cuenta (Correo)',
                        'descripcion' => 'Envía por correo a cada cliente con saldo pendiente su estado de cuenta, en el nivel de detalle elegido (total, por factura o por línea).',
                        'handler'     => Handlers\CuentasPorCobrarHandler::class,
                        'parametros'  => [
                            ['key' => 'nivel_detalle', 'label' => 'Nivel de detalle', 'tipo' => 'select', 'default' => 'por_factura',
                             'opciones' => [
                                 'total_vencido' => 'Total por cobrar vencido',
                                 'total_general' => 'Total por cobrar general',
                                 'por_factura'   => 'Detallado por cada factura',
                                 'por_linea'     => 'Detallado por cada línea de factura',
                             ],
                             'ayuda' => 'Define qué se incluye en el correo: solo el total, o el detalle por factura / por línea.'],
                            ['key' => 'solo_vencidas', 'label' => 'Considerar solo facturas vencidas', 'tipo' => 'checkbox', 'default' => true,
                             'ayuda' => 'Si está activo, solo se toman en cuenta las facturas ya vencidas (no se avisa por facturas dentro del plazo de crédito).'],
                            ['key' => 'dias_min_vencido', 'label' => 'Mínimo de días vencido', 'tipo' => 'number', 'default' => 0,
                             'ayuda' => 'Incluir solo facturas con al menos estos días de vencidas. 0 = sin mínimo.'],
                            ['key' => 'asunto', 'label' => 'Asunto del correo', 'tipo' => 'text', 'default' => 'Estado de cuenta - {empresa}',
                             'ayuda' => 'Etiquetas: {cliente} {empresa} {total_general} {total_vencido} {num_facturas}.'],
                            ['key' => 'cuerpo', 'label' => 'Mensaje (antes del detalle)', 'tipo' => 'textarea',
                             'default' => "Estimado/a {cliente}:\n\nLe compartimos el detalle de su estado de cuenta.\nTotal pendiente: {total_general}\nTotal vencido: {total_vencido}\n\nAgradecemos su pronto pago.\n{empresa}",
                             'ayuda' => 'Texto que va antes de la tabla de detalle. Etiquetas: {cliente} {empresa} {total_general} {total_vencido} {num_facturas}. El detalle se agrega automáticamente debajo según el nivel elegido.'],
                        ],
                    ],
                    'enviar_estado_whatsapp' => [
                        'label'       => 'Enviar estado de cuenta (WhatsApp)',
                        'descripcion' => 'Envía por WhatsApp a cada cliente con saldo pendiente, usando una plantilla aprobada por Meta.',
                        'handler'     => Handlers\CuentasPorCobrarHandler::class,
                        'parametros'  => [
                            ['key' => 'plantilla_whatsapp', 'label' => 'Plantilla de WhatsApp', 'tipo' => 'select_dinamico', 'fuente' => 'whatsapp_plantillas', 'default' => '',
                             'ayuda' => 'Plantilla aprobada por Meta.'],
                            ['key' => 'solo_vencidas', 'label' => 'Considerar solo facturas vencidas', 'tipo' => 'checkbox', 'default' => true,
                             'ayuda' => 'Si está activo, solo se toman en cuenta las facturas ya vencidas.'],
                            ['key' => 'dias_min_vencido', 'label' => 'Mínimo de días vencido', 'tipo' => 'number', 'default' => 0,
                             'ayuda' => 'Incluir solo facturas con al menos estos días de vencidas. 0 = sin mínimo.'],
                            ['key' => 'nivel_detalle_pdf', 'label' => 'Detalle del PDF adjunto', 'tipo' => 'select', 'default' => 'por_factura',
                             'opciones' => [
                                 'por_factura' => 'Detallado por cada factura',
                                 'por_linea'   => 'Detallado por cada línea de factura',
                             ],
                             'ayuda' => 'Solo aplica si la plantilla seleccionada lleva un documento (PDF) adjunto. Define el detalle del PDF generado.'],
                        ],
                    ],
                ],
            ],

            'suscripciones' => [
                'label'   => 'Suscripciones',
                'icono'   => 'fa-repeat',
                'acciones' => [
                    'generar_facturacion' => [
                        'label'       => 'Generar facturación',
                        'descripcion' => 'Genera las facturas (en borrador) de las suscripciones con períodos vencidos. El envío al SRI y por correo lo realizan las automatizaciones de Facturas de venta.',
                        'handler'     => Handlers\SuscripcionesHandler::class,
                        'parametros'  => [
                            ['key' => 'id_punto_emision', 'label' => 'Serie (punto de emisión)', 'tipo' => 'select_dinamico', 'fuente' => 'series', 'default' => '',
                             'ayuda' => 'Serie con la que se emitirán las facturas de las suscripciones.'],
                            ['key' => 'texto_item', 'label' => 'Texto adicional en cada ítem (opcional)', 'tipo' => 'text', 'default' => '',
                             'ayuda' => 'Se agrega en cada producto. Etiquetas: {mes} {MES} {mes_num} {anio} {mes_anio} {fecha} {anio_mes} {anio_mes_fin} {fecha_fin}. Período anterior: {mes_ant} {mes_anio_ant} {anio_mes_ant}. Ej: "Servicio {mes_anio}".'],
                            ['key' => 'info_concepto', 'label' => 'Concepto de info. adicional (opcional)', 'tipo' => 'text', 'default' => '',
                             'ayuda' => 'Nombre del campo en información adicional. Acepta las mismas etiquetas. Ej: "Período".'],
                            ['key' => 'info_detalle', 'label' => 'Detalle de info. adicional (opcional)', 'tipo' => 'text', 'default' => '',
                             'ayuda' => 'Valor de la info. adicional. Para rango de fechas: "{anio_mes} a {anio_mes_fin}" → "2026-06 a 2027-05". Para mensual: "{mes_anio}" → "junio 2026". Requiere también el concepto.'],
                            ['key' => 'por_periodicidad', 'label' => 'Personalización por periodicidad (opcional)', 'tipo' => 'grupo_periodicidad', 'default' => [],
                             'ayuda' => 'Sobrescribe texto ítem, concepto y detalle para una periodicidad específica. Lo que deje vacío usará el valor general de arriba.'],
                        ],
                    ],
                    'enviar_aviso_vencimiento' => [
                        'label'       => 'Enviar aviso de vencimiento (Correo)',
                        'descripcion' => 'Envía un correo a los clientes cuya suscripción vence en exactamente N días. Usa el correo configurado en la empresa.',
                        'handler'     => Handlers\SuscripcionesHandler::class,
                        'parametros'  => [
                            ['key' => 'dias_antes', 'label' => 'Avisar con anticipación (días)', 'tipo' => 'number', 'default' => 5,
                             'ayuda' => 'Se avisa cuando falten EXACTAMENTE estos días para el vencimiento (próximo cobro). Ej: 5 = se envía el correo el día en que falten 5 días. Para que no se pierdan avisos, ejecute esta automatización todos los días.'],
                            ['key' => 'asunto', 'label' => 'Asunto del correo', 'tipo' => 'text', 'default' => 'Su suscripción vence el {fecha_vencimiento}',
                             'ayuda' => 'Etiquetas disponibles: {cliente} {empresa} {fecha_vencimiento} {dias} {periodicidad}.'],
                            ['key' => 'cuerpo', 'label' => 'Cuerpo del correo', 'tipo' => 'textarea', 'default' => "Estimado/a {cliente}:\n\nLe recordamos que su suscripción {periodicidad} vence el {fecha_vencimiento} (faltan {dias} día(s)).\n\nGracias por su preferencia.\n{empresa}",
                             'ayuda' => 'Texto del mensaje. Mismas etiquetas: {cliente} {empresa} {fecha_vencimiento} {dias} {periodicidad}. Los saltos de línea se respetan en el correo.'],
                        ],
                    ],
                    'enviar_aviso_vencimiento_whatsapp' => [
                        'label'       => 'Enviar aviso de vencimiento (WhatsApp)',
                        'descripcion' => 'Envía un WhatsApp a los clientes cuya suscripción vence en exactamente N días, usando una plantilla aprobada por Meta. El mensaje lo define la plantilla; sus variables se rellenan automáticamente EN ESTE ORDEN: {{1}}=cliente, {{2}}=fecha de vencimiento, {{3}}=días, {{4}}=periodicidad, {{5}}=empresa.',
                        'handler'     => Handlers\SuscripcionesHandler::class,
                        'parametros'  => [
                            ['key' => 'dias_antes', 'label' => 'Avisar con anticipación (días)', 'tipo' => 'number', 'default' => 5,
                             'ayuda' => 'Se avisa cuando falten EXACTAMENTE estos días para el vencimiento. Ejecute la automatización todos los días para no perder avisos.'],
                            ['key' => 'plantilla_whatsapp', 'label' => 'Plantilla de WhatsApp', 'tipo' => 'select_dinamico', 'fuente' => 'whatsapp_plantillas', 'default' => '',
                             'ayuda' => 'Plantilla aprobada por Meta. El mensaje se define en la plantilla (módulo Plantillas WhatsApp). Las variables {{1}}..{{5}} se rellenan solas en el orden: cliente, fecha de vencimiento, días, periodicidad, empresa.'],
                        ],
                    ],
                ],
            ],

        ];

        return self::$catalogo;
    }

    // ── Mapa tabla por módulo ─────────────────────────────────────────────────
    public static array $tablasPorModulo = [
        'facturas_venta'        => ['tabla' => 'ventas_cabecera',           'tipo_doc' => '01'],
        'retenciones_compras'   => ['tabla' => 'retencion_compra_cabecera', 'tipo_doc' => '07'],
        'notas_credito'         => ['tabla' => 'notas_credito_cabecera',    'tipo_doc' => '04'],
        'liquidaciones_compras' => ['tabla' => 'liquidaciones_cabecera',    'tipo_doc' => '03'],
        'guias_remision'        => ['tabla' => 'guias_remision_cabecera',   'tipo_doc' => '06'],
    ];

    // ── API pública ───────────────────────────────────────────────────────────

    public static function crear(string $modulo, string $accion): BaseHandler
    {
        $config = self::getCatalogo()[$modulo]['acciones'][$accion] ?? null;
        if ($config === null) {
            throw new \RuntimeException("Acción '{$accion}' del módulo '{$modulo}' no está registrada.");
        }
        return new ($config['handler'])($modulo, $accion);
    }

    public static function getModulosDisponibles(): array
    {
        return array_map(
            fn($key, $m) => ['key' => $key, 'label' => $m['label'], 'icono' => $m['icono']],
            array_keys(self::getCatalogo()),
            self::getCatalogo()
        );
    }

    public static function getAccionesPorModulo(string $modulo): array
    {
        $acciones = self::getCatalogo()[$modulo]['acciones'] ?? [];
        return array_map(
            fn($key, $a) => ['key' => $key, 'label' => $a['label'], 'descripcion' => $a['descripcion']],
            array_keys($acciones),
            $acciones
        );
    }

    public static function getParametrosPorAccion(string $modulo, string $accion): array
    {
        return self::getCatalogo()[$modulo]['acciones'][$accion]['parametros'] ?? [];
    }

    public static function getConfigTabla(string $modulo): ?array
    {
        return self::$tablasPorModulo[$modulo] ?? null;
    }
}
