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
                'label'   => 'WhatsApp',
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
                        'descripcion' => 'Envía un WhatsApp a los clientes cuya suscripción vence en exactamente N días, usando una plantilla aprobada por Meta. Requiere WhatsApp configurado y plantilla aprobada.',
                        'handler'     => Handlers\SuscripcionesHandler::class,
                        'parametros'  => [
                            ['key' => 'dias_antes', 'label' => 'Avisar con anticipación (días)', 'tipo' => 'number', 'default' => 5,
                             'ayuda' => 'Se avisa cuando falten EXACTAMENTE estos días para el vencimiento. Ejecute la automatización todos los días para no perder avisos.'],
                            ['key' => 'plantilla_whatsapp', 'label' => 'Plantilla de WhatsApp', 'tipo' => 'select_dinamico', 'fuente' => 'whatsapp_plantillas', 'default' => '',
                             'ayuda' => 'Plantilla aprobada por Meta con la que se enviará el aviso. Configúrelas en el módulo de WhatsApp.'],
                            ['key' => 'variables_whatsapp', 'label' => 'Valores de las variables de la plantilla', 'tipo' => 'text', 'default' => '',
                             'ayuda' => 'Valores para las variables {{1}} {{2}}... de la plantilla, EN ORDEN y separados por "|". Etiquetas: {cliente} {empresa} {fecha_vencimiento} {dias} {periodicidad}. Ej: "{cliente} | {fecha_vencimiento} | {dias}". Déjelo vacío si la plantilla no tiene variables.'],
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
