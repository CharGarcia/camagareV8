<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Plantillas "originales" del sistema, por tipo de documento: el punto de
 * partida cuando el usuario elige "Plantilla original del sistema" al crear
 * una nueva plantilla en el diseñador (en vez de partir en blanco). Son una
 * APROXIMACIÓN declarativa (JSON de elementos, mismo esquema que usa el
 * diseñador visual) del diseño que hoy dibuja cada *PdfService hardcodeado.
 *
 * No son transcripciones pixel-a-pixel: los *PdfService hardcodeados dibujan
 * con layout dinámico (alturas que dependen del contenido); estas semillas
 * usan posiciones fijas razonables que cubren los mismos campos y secciones,
 * en el mismo orden. El usuario las afina en el diseñador visual.
 *
 * Un tipo de documento sin semilla registrada (ej. nota_debito, que todavía
 * no tiene flujo de emisión/PDF propio) solo puede crearse en blanco.
 */
class PlantillasPdfSeedService
{
    public static function getSeed(string $tipoDocumento): ?string
    {
        $def = self::definiciones()[$tipoDocumento] ?? null;
        return $def !== null ? json_encode($def, JSON_UNESCAPED_UNICODE) : null;
    }

    public static function tieneSeed(string $tipoDocumento): bool
    {
        return self::getSeed($tipoDocumento) !== null;
    }

    /** Tipos de documento que sí tienen una plantilla original para partir de ella. */
    public static function tiposConSeed(): array
    {
        return array_keys(self::definiciones());
    }

    // ── Definiciones por tipo ──────────────────────────────────────────────────

    private static function definiciones(): array
    {
        return [
            'cheque' => [
                'pagina' => self::pagina('P', 'A4', 0, 0, 0, 0),
                'elementos' => [
                    self::campo('{beneficiario}', 20, 24, 80, 6, 'L', 10),
                    self::campo('{monto_numero_protegido}', 120, 24, 33, 6, 'L', 11, 'B'),
                    self::campo('{monto_letras}', 20, 30, 133, 10, 'L', 9),
                    self::campo('{ciudad_fecha}', 10, 43, 120, 6, 'L', 10),
                ],
            ],

            'factura_venta' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('FACTURA'),
                    self::clienteBlock(68),
                    [self::tabla('tabla:detalles', 10, 88, 190, 80)],
                    [self::tabla('tabla:pagos', 10, 172, 190, 16)],
                    self::totalesBlock(230),
                    [self::campo('{observaciones}', 10, 262, 190, 10, 'L', 7)]
                ),
            ],

            'nota_credito' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('NOTA DE CRÉDITO'),
                    [
                        self::texto('Motivo:', 10, 68, 24, 5, 'L', 8, 'B'),
                        self::campo('{nc_motivo}', 35, 68, 165, 8, 'L', 8),
                        self::texto('Comprobante que modifica:', 10, 78, 45, 5, 'L', 8, 'B'),
                        self::campo('{nc_num_doc_modificado}', 56, 78, 60, 5, 'L', 8),
                        self::texto('Fecha del comprobante:', 120, 78, 40, 5, 'L', 8, 'B'),
                        self::campo('{nc_fecha_doc_sustento}', 162, 78, 38, 5, 'L', 8),
                    ],
                    self::clienteBlock(88),
                    [self::tabla('tabla:detalles', 10, 112, 190, 60)],
                    self::totalesBlock(230),
                    [
                        self::texto('Observaciones:', 10, 265, 30, 5, 'L', 7, 'B'),
                        self::campo('{observaciones}', 10, 270, 190, 10, 'L', 7),
                    ]
                ),
            ],

            'guia_remision' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('GUÍA DE REMISIÓN'),
                    [
                        self::texto('Destinatario:', 10, 68, 26, 5, 'L', 8, 'B'),
                        self::campo('{cliente_nombre}', 36, 68, 100, 5, 'L', 8),
                        self::texto('Identificación:', 140, 68, 26, 5, 'L', 8, 'B'),
                        self::campo('{cliente_ruc}', 166, 68, 34, 5, 'L', 8),

                        self::texto('Transportista:', 10, 75, 26, 5, 'L', 8, 'B'),
                        self::campo('{gr_transportista_nombre}', 36, 75, 100, 5, 'L', 8),
                        self::texto('Identificación:', 140, 75, 26, 5, 'L', 8, 'B'),
                        self::campo('{gr_transportista_ruc}', 166, 75, 34, 5, 'L', 8),

                        self::texto('Placa:', 10, 82, 26, 5, 'L', 8, 'B'),
                        self::campo('{gr_placa}', 36, 82, 40, 5, 'L', 8),
                        self::texto('Motivo del traslado:', 80, 82, 34, 5, 'L', 8, 'B'),
                        self::campo('{gr_motivo_traslado}', 114, 82, 86, 5, 'L', 8),

                        self::texto('Punto de partida:', 10, 89, 30, 5, 'L', 8, 'B'),
                        self::campo('{gr_direccion_partida}', 40, 89, 160, 5, 'L', 8),
                        self::texto('Punto de llegada:', 10, 96, 30, 5, 'L', 8, 'B'),
                        self::campo('{gr_direccion_destino}', 40, 96, 160, 5, 'L', 8),

                        self::texto('Fecha inicio transporte:', 10, 103, 38, 5, 'L', 8, 'B'),
                        self::campo('{gr_fecha_inicio_transporte}', 48, 103, 40, 5, 'L', 8),
                        self::texto('Fecha fin transporte:', 100, 103, 34, 5, 'L', 8, 'B'),
                        self::campo('{gr_fecha_fin_transporte}', 134, 103, 40, 5, 'L', 8),
                    ],
                    [self::tabla('tabla:detalles', 10, 115, 190, 90)],
                    [self::campo('{observaciones}', 10, 260, 190, 10, 'L', 7)]
                ),
            ],

            'liquidacion_compra' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('LIQUIDACIÓN DE COMPRA'),
                    self::proveedorBlock(68),
                    [self::tabla('tabla:detalles', 10, 90, 190, 55)],
                    [self::tabla('tabla:pagos', 10, 150, 190, 20)],
                    self::totalesBlock(230),
                    [self::campo('{observaciones}', 10, 265, 190, 10, 'L', 7)]
                ),
            ],

            'compras' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('COMPRA'),
                    self::proveedorBlock(68),
                    [
                        self::texto('N.° proveedor:', 10, 84, 26, 5, 'L', 7, 'B'),
                        self::campo('{compra_numero_prov}', 36, 84, 60, 5, 'L', 7),
                        self::texto('Autorización:', 100, 84, 26, 5, 'L', 7, 'B'),
                        self::campo('{compra_numero_autorizacion}', 126, 84, 74, 5, 'L', 7),
                    ],
                    [self::tabla('tabla:detalles', 10, 94, 190, 55)],
                    [self::tabla('tabla:pagos', 10, 154, 190, 20)],
                    self::totalesBlock(230),
                    [self::campo('{observaciones}', 10, 265, 190, 10, 'L', 7)]
                ),
            ],

            'retencion_compra' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('COMPROBANTE DE RETENCIÓN'),
                    [
                        self::texto('DATOS DEL SUJETO RETENIDO', 10, 66, 190, 5, 'C', 8, 'B', '#f0f0f0'),
                        self::texto('Razón Social:', 10, 72, 30, 5, 'L', 7, 'B'),
                        self::campo('{ret_sujeto_nombre}', 40, 72, 90, 5, 'L', 7),
                        self::texto('RUC / Identif.:', 130, 72, 26, 5, 'L', 7, 'B'),
                        self::campo('{ret_sujeto_identificacion}', 156, 72, 44, 5, 'L', 7),
                        self::texto('Período Fiscal:', 10, 78, 30, 5, 'L', 7, 'B'),
                        self::campo('{ret_periodo_fiscal}', 40, 78, 40, 5, 'L', 7),
                        self::texto('Tipo sustento:', 84, 78, 26, 5, 'L', 7, 'B'),
                        self::campo('{ret_tipo_doc_sustento}', 110, 78, 40, 5, 'L', 7),
                        self::texto('N.° / Fecha sustento:', 10, 84, 36, 5, 'L', 7, 'B'),
                        self::campo('{ret_num_doc_sustento}', 46, 84, 50, 5, 'L', 7),
                        self::campo('{ret_fecha_doc_sustento}', 96, 84, 30, 5, 'L', 7),
                    ],
                    [self::tabla('tabla:retenciones', 10, 94, 190, 40)],
                    [self::campo('{observaciones}', 10, 250, 190, 10, 'L', 7)]
                ),
            ],

            'retencion_venta' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('COMPROBANTE DE RETENCIÓN'),
                    self::clienteBlock(68),
                    [
                        self::texto('Período Fiscal:', 10, 84, 30, 5, 'L', 7, 'B'),
                        self::campo('{ret_periodo_fiscal}', 40, 84, 40, 5, 'L', 7),
                    ],
                    [self::tabla('tabla:retenciones', 10, 94, 190, 40)],
                    [self::campo('{observaciones}', 10, 250, 190, 10, 'L', 7)]
                ),
            ],

            'proforma' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('PROFORMA'),
                    self::clienteBlock(68),
                    [
                        self::texto('Días de vigencia:', 10, 84, 32, 5, 'L', 8, 'B'),
                        self::campo('{pf_dias_vigencia}', 44, 84, 20, 5, 'L', 8),
                        self::texto('Válida hasta:', 70, 84, 26, 5, 'L', 8, 'B'),
                        self::campo('{pf_fecha_vigencia}', 98, 84, 40, 5, 'L', 8),
                    ],
                    [self::tabla('tabla:detalles', 10, 94, 190, 70)],
                    self::totalesBlock(225),
                    [
                        self::texto('Documento no tributario · Proforma sin validez de factura', 10, 255, 190, 5, 'C', 7),
                        self::campo('{observaciones}', 10, 262, 190, 10, 'L', 7),
                    ]
                ),
            ],

            'egreso' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('COMPROBANTE DE EGRESO'),
                    [
                        self::texto('Pagado a:', 12, 52, 24, 5, 'L', 8, 'B'),
                        self::campo('{cc_sujeto_nombre}', 36, 52, 90, 5, 'L', 8),
                        self::texto('Identificación:', 130, 52, 26, 5, 'L', 8, 'B'),
                        self::campo('{cc_sujeto_ruc}', 156, 52, 44, 5, 'L', 8),
                        self::texto('Por concepto de:', 12, 59, 30, 5, 'L', 8, 'B'),
                        self::campo('{observaciones}', 44, 59, 154, 8, 'L', 8),
                    ],
                    [self::tabla('tabla:detalles', 12, 72, 186, 40)],
                    [self::tabla('tabla:pagos', 12, 115, 186, 18)],
                    [
                        self::texto('Son:', 12, 138, 12, 5, 'L', 8, 'B'),
                        self::campo('{cc_monto_letras}', 26, 138, 172, 6, 'L', 8),
                        self::campo('{cc_monto_total}', 160, 145, 38, 6, 'R', 11, 'B'),
                    ],
                    [self::tabla('tabla:asiento', 12, 156, 186, 35)]
                ),
            ],

            'ingreso' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('COMPROBANTE DE INGRESO'),
                    [
                        self::texto('Recibido de:', 12, 52, 24, 5, 'L', 8, 'B'),
                        self::campo('{cc_recibo_de}', 36, 52, 130, 5, 'L', 8),
                        self::texto('Por concepto de:', 12, 59, 30, 5, 'L', 8, 'B'),
                        self::campo('{observaciones}', 44, 59, 154, 8, 'L', 8),
                    ],
                    [self::tabla('tabla:detalles', 12, 72, 186, 40)],
                    [self::tabla('tabla:pagos', 12, 115, 186, 18)],
                    [
                        self::texto('Son:', 12, 138, 12, 5, 'L', 8, 'B'),
                        self::campo('{cc_monto_letras}', 26, 138, 172, 6, 'L', 8),
                        self::campo('{cc_monto_total}', 160, 145, 38, 6, 'R', 11, 'B'),
                    ],
                    [self::tabla('tabla:asiento', 12, 156, 186, 35)]
                ),
            ],

            'traspaso' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('COMPROBANTE DE TRASPASO'),
                    [
                        self::texto('Cuenta origen:', 12, 52, 26, 5, 'L', 8, 'B'),
                        self::campo('{cc_origen_nombre}', 38, 52, 70, 5, 'L', 8),
                        self::texto('Cuenta destino:', 112, 52, 28, 5, 'L', 8, 'B'),
                        self::campo('{cc_destino_nombre}', 140, 52, 58, 5, 'L', 8),
                        self::texto('Monto:', 12, 59, 24, 5, 'L', 8, 'B'),
                        self::campo('{cc_monto}', 36, 59, 40, 5, 'L', 8, 'B'),
                        self::texto('Son:', 12, 66, 12, 5, 'L', 8, 'B'),
                        self::campo('{cc_monto_letras}', 26, 66, 172, 6, 'L', 8),
                        self::texto('Observaciones:', 12, 74, 30, 5, 'L', 8, 'B'),
                        self::campo('{observaciones}', 44, 74, 154, 8, 'L', 8),
                    ],
                    [self::tabla('tabla:asiento', 12, 90, 186, 40)]
                ),
            ],

            'retorno_cv' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('RETORNO DE CONSIGNACIÓN'),
                    self::clienteComprobanteBlock(52, [
                        ['Fecha retorno:', '{rt_fecha_retorno}'],
                        ['Realizado por:', '{rt_usuario_nombre}'],
                        ['Resp. traslado:', '{rt_responsable_traslado}'],
                    ]),
                    [self::tabla('tabla:detalles', 12, 82, 186, 60)],
                    [
                        self::texto('Motivo:', 12, 148, 24, 5, 'L', 8, 'B'),
                        self::campo('{rt_motivo}', 36, 148, 162, 6, 'L', 8),
                        self::texto('Observaciones:', 12, 156, 30, 5, 'L', 8, 'B'),
                        self::campo('{observaciones}', 44, 156, 154, 8, 'L', 8),
                    ],
                    self::firmasDobles(230, 'Realizado por', 'Recibido por')
                ),
            ],

            'consignacion' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('CONSIGNACIÓN EN VENTAS'),
                    self::clienteComprobanteBlock(52, [
                        ['Fecha entrega:', '{cg_fecha_entrega}'],
                        ['Asesor:', '{cg_vendedor_nombre}'],
                        ['Resp. traslado:', '{cg_responsable_traslado}'],
                        ['Punto partida / llegada:', '{cg_punto_partida}'],
                    ]),
                    [self::tabla('tabla:detalles', 12, 88, 186, 60)],
                    [
                        self::texto('Observaciones:', 12, 152, 30, 5, 'L', 8, 'B'),
                        self::campo('{observaciones}', 44, 152, 154, 8, 'L', 8),
                    ],
                    self::firmasTriples(230)
                ),
            ],

            'cambio_producto_cv' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('CAMBIO DE PRODUCTOS'),
                    self::clienteComprobanteBlock(52, [
                        ['Fecha cambio:', '{cp_fecha_cambio}'],
                        ['Realizado por:', '{cp_usuario_nombre}'],
                    ]),
                    [
                        self::texto('Productos que devuelve', 12, 78, 186, 5, 'L', 8, 'B'),
                        self::tabla('tabla:cambio_devuelto', 12, 84, 186, 30),
                        self::texto('Productos que entrega a cambio', 12, 118, 186, 5, 'L', 8, 'B'),
                        self::tabla('tabla:cambio_entrega', 12, 124, 186, 30),
                        self::texto('Total devuelto:', 130, 158, 30, 5, 'R', 8),
                        self::campo('{cp_subtotal_devuelto}', 162, 158, 36, 5, 'R', 8),
                        self::texto('Total entregado:', 130, 163, 30, 5, 'R', 8),
                        self::campo('{cp_subtotal_entregado}', 162, 163, 36, 5, 'R', 8),
                        self::texto('Diferencia:', 130, 168, 30, 5, 'R', 8, 'B'),
                        self::campo('{cp_diferencia}', 162, 168, 36, 5, 'R', 8, 'B'),
                        self::texto('Motivo:', 12, 176, 24, 5, 'L', 8, 'B'),
                        self::campo('{cp_motivo}', 36, 176, 162, 6, 'L', 8),
                    ],
                    self::firmasDobles(200, 'Realizado por', 'Recibido por')
                ),
            ],

            'consignacion_factura' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoComprobante('FACTURACIÓN DE CONSIGNACIÓN'),
                    self::clienteComprobanteBlock(52, [
                        ['Fecha:', '{fecha_emision}'],
                        ['Vendedor:', '{cf_vendedor_nombre}'],
                        ['Factura relacionada:', '{cf_factura_origen}'],
                    ]),
                    [self::tabla('tabla:detalles', 12, 82, 186, 60)],
                    self::totalesBlock(225),
                    [self::campo('{observaciones}', 12, 260, 186, 10, 'L', 7)]
                ),
            ],

            'recibo_venta' => [
                'pagina' => self::pagina(),
                'elementos' => array_merge(
                    self::encabezadoSri('RECIBO DE VENTA'),
                    self::clienteBlock(68),
                    [
                        self::texto('Placa:', 10, 84, 20, 5, 'L', 8, 'B'),
                        self::campo('{rv_placa}', 30, 84, 40, 5, 'L', 8),
                        self::texto('Guía de remisión:', 80, 84, 32, 5, 'L', 8, 'B'),
                        self::campo('{guia_remision}', 114, 84, 86, 5, 'L', 8),
                    ],
                    [self::tabla('tabla:detalles', 10, 94, 190, 55)],
                    [self::tabla('tabla:pagos', 10, 154, 190, 20)],
                    self::totalesBlock(225),
                    [
                        self::texto('Son:', 10, 255, 12, 5, 'L', 8, 'B'),
                        self::campo('{rv_monto_letras}', 24, 255, 176, 6, 'L', 8),
                        self::campo('{observaciones}', 10, 263, 190, 10, 'L', 7),
                    ]
                ),
            ],
        ];
    }

    // ── Bloques reutilizables ───────────────────────────────────────────────────

    private static function pagina(string $orientacion = 'P', string $formato = 'A4', float $mT = 10, float $mL = 10, float $mR = 10, float $mB = 15): array
    {
        return ['formato' => $formato, 'orientacion' => $orientacion, 'margenTop' => $mT, 'margenLeft' => $mL, 'margenRight' => $mR, 'margenBottom' => $mB];
    }

    /** Encabezado "documento tributario/sustento": empresa a la izquierda, caja de documento a la derecha (estilo factura/retención). */
    private static function encabezadoSri(string $titulo): array
    {
        return [
            self::campo('{empresa_logo}', 10, 8, 26, 16, 'L', 8),
            self::campo('{empresa_nombre}', 38, 8, 62, 8, 'L', 9, 'B'),
            self::campo('{empresa_direccion}', 38, 16, 62, 8, 'L', 7),
            self::campo('{empresa_telefono}', 38, 24, 62, 4, 'L', 7),
            self::campo('{empresa_correo}', 38, 28, 62, 4, 'L', 7),
            self::campo('{empresa_ruc}', 38, 33, 62, 4, 'L', 7, 'B'),

            self::texto($titulo, 100, 9, 100, 6, 'C', 11, 'B'),
            self::campo('{numero_factura}', 100, 16, 100, 6, 'C', 10, 'B'),
            self::texto('NÚMERO DE AUTORIZACIÓN', 100, 23, 100, 4, 'C', 7),
            self::campo('{clave_acceso}', 100, 27, 100, 8, 'C', 6),
            self::campo('{ambiente}', 100, 36, 48, 4, 'L', 7, 'B'),
            self::campo('{fecha_autorizacion}', 148, 36, 52, 4, 'R', 7),
            self::codigoBarras(100, 41, 100, 12),
        ];
    }

    /** Encabezado "comprobante interno": empresa a la izquierda, caja redondeada con título/número/estado a la derecha (estilo egreso/ingreso/consignación). */
    private static function encabezadoComprobante(string $titulo): array
    {
        return [
            self::campo('{empresa_logo}', 12, 10, 24, 16, 'L', 8),
            self::campo('{empresa_nombre}', 38, 10, 84, 6, 'L', 11, 'B'),
            self::campo('{empresa_ruc}', 38, 17, 84, 4, 'L', 8),
            self::campo('{empresa_direccion}', 38, 21, 84, 8, 'L', 7),
            self::rectangulo(122, 10, 76, 30, '#3c3c3c'),
            self::texto($titulo, 122, 12, 76, 5, 'C', 9.5, 'B'),
            self::texto('N.°', 122, 18, 76, 4, 'C', 8),
            self::campo('{cc_numero}', 122, 22, 76, 6, 'C', 11, 'B', '#b40000'),
            self::campo('{fecha_emision}', 122, 30, 76, 5, 'C', 8),
        ];
    }

    /** Caja gris de cliente (documentos tributarios): nombre, ruc, dirección, fecha. */
    private static function clienteBlock(float $y): array
    {
        return [
            self::rectangulo(10, $y, 190, 16, '#787878', '#f5f5f5'),
            self::texto('Cliente:', 12, $y + 2, 24, 5, 'L', 8, 'B'),
            self::campo('{cliente_nombre}', 36, $y + 2, 100, 5, 'L', 8),
            self::texto('Fecha:', 140, $y + 2, 20, 5, 'L', 8, 'B'),
            self::campo('{fecha_emision}', 160, $y + 2, 38, 5, 'L', 8),
            self::texto('Identificación:', 12, $y + 8, 26, 5, 'L', 8, 'B'),
            self::campo('{cliente_ruc}', 38, $y + 8, 60, 5, 'L', 8),
            self::texto('Dirección:', 100, $y + 8, 22, 5, 'L', 8, 'B'),
            self::campo('{cliente_direccion}', 122, $y + 8, 76, 5, 'L', 8),
        ];
    }

    /** Caja gris de proveedor (liquidación de compra / compras: emisor = proveedor). */
    private static function proveedorBlock(float $y): array
    {
        return [
            self::rectangulo(10, $y, 190, 16, '#787878', '#f5f5f5'),
            self::texto('Proveedor:', 12, $y + 2, 24, 5, 'L', 8, 'B'),
            self::campo('{proveedor_nombre}', 36, $y + 2, 100, 5, 'L', 8),
            self::texto('Fecha:', 140, $y + 2, 20, 5, 'L', 8, 'B'),
            self::campo('{fecha_emision}', 160, $y + 2, 38, 5, 'L', 8),
            self::texto('Identificación:', 12, $y + 8, 26, 5, 'L', 8, 'B'),
            self::campo('{proveedor_ruc}', 38, $y + 8, 60, 5, 'L', 8),
            self::texto('Dirección:', 100, $y + 8, 22, 5, 'L', 8, 'B'),
            self::campo('{proveedor_direccion}', 122, $y + 8, 76, 5, 'L', 8),
        ];
    }

    /** Caja gris de cliente para comprobantes internos, con pares label/campo extra a la derecha. */
    private static function clienteComprobanteBlock(float $y, array $extras): array
    {
        $h = 10 + count($extras) * 6;
        $els = [
            self::rectangulo(12, $y, 186, $h, '#787878', '#f5f5f5'),
            self::texto('Cliente:', 14, $y + 2, 24, 5, 'L', 8, 'B'),
            self::campo('{cliente_nombre}', 38, $y + 2, 60, 5, 'L', 8),
            self::texto('Identificación:', 14, $y + 8, 26, 5, 'L', 8, 'B'),
            self::campo('{cliente_ruc}', 40, $y + 8, 58, 5, 'L', 8),
        ];
        $yy = $y + 2;
        foreach ($extras as $i => [$label, $campo]) {
            $els[] = self::texto($label, 100, $yy + $i * 6, 40, 5, 'L', 8, 'B');
            $els[] = self::campo($campo, 142, $yy + $i * 6, 54, 5, 'L', 8);
        }
        return $els;
    }

    /** Bloque de totales SRI (subtotales, IVA, total), esquina inferior derecha. */
    private static function totalesBlock(float $y): array
    {
        return [
            self::texto('Subtotal 0%:', 130, $y, 34, 5, 'R', 8),
            self::campo('{subtotal_0}', 166, $y, 34, 5, 'R', 8),
            self::texto('Subtotal IVA:', 130, $y + 5, 34, 5, 'R', 8),
            self::campo('{subtotal_iva}', 166, $y + 5, 34, 5, 'R', 8),
            self::texto('IVA:', 130, $y + 10, 34, 5, 'R', 8),
            self::campo('{iva}', 166, $y + 10, 34, 5, 'R', 8),
            self::texto('TOTAL:', 130, $y + 16, 34, 6, 'R', 10, 'B'),
            self::campo('{valor_total}', 166, $y + 16, 34, 6, 'R', 10, 'B'),
        ];
    }

    /** Dos firmas lado a lado (líneas), cerca del pie de página. */
    private static function firmasDobles(float $y, string $izq, string $der): array
    {
        return [
            self::linea(30, $y, 90, '#000000'),
            self::linea(120, $y, 90, '#000000'),
            self::texto($izq, 30, $y + 1, 90, 4, 'C', 8, 'B'),
            self::texto($der, 120, $y + 1, 90, 4, 'C', 8, 'B'),
        ];
    }

    /** Tres firmas (consignación: entregado / traslado / recibí conforme). */
    private static function firmasTriples(float $y): array
    {
        return [
            self::linea(20, $y, 58, '#000000'),
            self::linea(84, $y, 58, '#000000'),
            self::linea(148, $y, 58, '#000000'),
            self::texto('Entregado por', 20, $y + 1, 58, 4, 'C', 7.5, 'B'),
            self::texto('Responsable de traslado', 84, $y + 1, 58, 4, 'C', 7.5, 'B'),
            self::texto('Recibí conforme', 148, $y + 1, 58, 4, 'C', 7.5, 'B'),
        ];
    }

    // ── Constructores de elementos individuales ────────────────────────────────

    private static function campo(string $campo, float $x, float $y, float $w, float $h, string $align = 'L', float $tam = 8, string $estilo = '', ?string $colorTexto = null): array
    {
        $el = ['tipo' => 'campo', 'campo' => $campo, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'alineacion' => $align, 'fuente' => 'helvetica', 'tamano' => $tam];
        if ($estilo !== '') $el['estilo'] = $estilo;
        if ($colorTexto !== null) $el['colorTexto'] = $colorTexto;
        return $el;
    }

    private static function texto(string $contenido, float $x, float $y, float $w, float $h, string $align = 'L', float $tam = 8, string $estilo = '', ?string $colorFondo = null): array
    {
        $el = ['tipo' => 'texto', 'contenido' => $contenido, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'alineacion' => $align, 'fuente' => 'helvetica', 'tamano' => $tam];
        if ($estilo !== '') $el['estilo'] = $estilo;
        if ($colorFondo !== null) $el['colorFondo'] = $colorFondo;
        return $el;
    }

    private static function tabla(string $campo, float $x, float $y, float $w, float $h): array
    {
        return ['tipo' => 'tabla', 'campo' => $campo, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    private static function rectangulo(float $x, float $y, float $w, float $h, string $colorBorde = '#000000', ?string $colorFondo = null): array
    {
        $el = ['tipo' => 'rectangulo', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'borde' => ['color' => $colorBorde, 'grosor' => 0.3, 'lados' => 'LTBR']];
        if ($colorFondo !== null) $el['colorFondo'] = $colorFondo;
        return $el;
    }

    private static function linea(float $x, float $y, float $w, string $color = '#000000'): array
    {
        return ['tipo' => 'linea', 'x' => $x, 'y' => $y, 'w' => $w, 'borde' => ['color' => $color, 'grosor' => 0.4]];
    }

    private static function codigoBarras(float $x, float $y, float $w, float $h): array
    {
        return ['tipo' => 'codigoBarras', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }
}
