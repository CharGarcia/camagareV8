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

            'suscripciones' => [
                'label'   => 'Suscripciones',
                'icono'   => 'fa-repeat',
                'acciones' => [
                    'generar_facturacion' => [
                        'label'       => 'Generar facturación',
                        'descripcion' => 'Genera las facturas (en borrador) de suscripciones con cobro vencido. El envío al SRI y por correo lo realizan las automatizaciones de Facturas de venta.',
                        'handler'     => Handlers\SuscripcionesHandler::class,
                        'parametros'  => [
                            ['key' => 'dias_anticipacion', 'label' => 'Generar con anticipación (días)', 'tipo' => 'number', 'default' => 0,
                             'ayuda' => 'Cuántos días ANTES de la fecha de cobro se genera la factura. Use 0 para generar el mismo día del vencimiento. Ejemplo: 3 generaría las facturas 3 días antes de su cobro.'],
                        ],
                    ],
                    'enviar_aviso_vencimiento' => [
                        'label'       => 'Enviar aviso de vencimiento',
                        'descripcion' => 'Notifica a los clientes cuya suscripción está próxima a vencer',
                        'handler'     => Handlers\SuscripcionesHandler::class,
                        'parametros'  => [
                            ['key' => 'dias_antes', 'label' => 'Avisar con anticipación (días)', 'tipo' => 'number', 'default' => 5,
                             'ayuda' => 'Cuántos días ANTES del vencimiento se envía el aviso al cliente. Ejemplo: 5 avisa al cliente 5 días antes de que venza su suscripción.'],
                            ['key' => 'canal', 'label' => '¿Por dónde se envía el aviso?', 'tipo' => 'select', 'default' => 'correo',
                             'opciones' => ['correo' => 'Correo electrónico', 'whatsapp' => 'WhatsApp', 'ambos' => 'Correo y WhatsApp'],
                             'ayuda' => 'Medio por el que el cliente recibirá la notificación. "Ambos" envía por correo y WhatsApp a la vez.'],
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
