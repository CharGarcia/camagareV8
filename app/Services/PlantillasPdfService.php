<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\modulos\PlantillasPdfRepository;

class PlantillasPdfService
{
    private PlantillasPdfRepository $repo;

    /** Bloque de campos de empresa (emisor), común a casi todos los documentos. */
    private const CAMPOS_EMPRESA = [
        '{empresa_nombre}'       => 'Nombre / Razón Social',
        '{empresa_comercial}'    => 'Nombre Comercial',
        '{empresa_ruc}'          => 'RUC',
        '{empresa_direccion}'    => 'Dirección Matriz',
        '{empresa_sucursal}'     => 'Dirección Sucursal',
        '{empresa_telefono}'     => 'Teléfono',
        '{empresa_correo}'       => 'Correo',
        '{empresa_contribuyente}'=> 'Contribuyente Especial',
        '{empresa_obligado}'     => 'Obligado Contabilidad',
        '{empresa_logo}'         => 'Logo (imagen)',
    ];

    /** Bloque de campos del cliente, común a los documentos que le facturan/entregan a uno. */
    private const CAMPOS_CLIENTE = [
        '{cliente_nombre}'       => 'Razón Social / Nombre',
        '{cliente_ruc}'          => 'RUC / Cédula',
        '{cliente_direccion}'    => 'Dirección',
        '{cliente_email}'        => 'Correo',
        '{cliente_telefono}'     => 'Teléfono',
    ];

    /** Bloque de totales SRI, común a los documentos tributarios con impuestos. */
    private const CAMPOS_TOTALES = [
        '{subtotal_0}'           => 'Subtotal IVA 0%',
        '{subtotal_iva}'         => 'Subtotal IVA X%',
        '{total_descuento}'      => 'Total Descuento',
        '{ice}'                  => 'ICE',
        '{iva}'                  => 'IVA',
        '{propina}'              => 'Propina',
        '{valor_total}'          => 'VALOR TOTAL',
    ];

    /** Bloque de datos de autorización SRI (número, clave de acceso, ambiente). */
    private const CAMPOS_AUTORIZACION_SRI = [
        '{numero_factura}'       => 'Número (001-001-000000001)',
        '{fecha_emision}'        => 'Fecha de Emisión',
        '{numero_autorizacion}'  => 'Número de Autorización',
        '{clave_acceso}'         => 'Clave de Acceso (49 dígitos)',
        '{fecha_autorizacion}'   => 'Fecha y Hora de Autorización',
        '{ambiente}'             => 'Ambiente (PRODUCCIÓN/PRUEBAS)',
        '{tipo_emision}'         => 'Tipo de Emisión',
        '{observaciones}'        => 'Observaciones',
    ];

    // Campos disponibles por tipo de documento
    private const CAMPOS = [
        'factura_venta' => [
            'Empresa'   => self::CAMPOS_EMPRESA,
            'Factura'   => self::CAMPOS_AUTORIZACION_SRI,
            'Cliente'   => self::CAMPOS_CLIENTE + [
                '{guia_remision}'        => 'Guía de Remisión',
                '{plazo}'                => 'Plazo / Días Crédito',
            ],
            'Totales'   => self::CAMPOS_TOTALES,
            'Tablas'    => [
                'tabla:detalles'         => 'Tabla de Ítems/Productos',
                'tabla:pagos'            => 'Tabla de Formas de Pago',
                'tabla:info_adicional'   => 'Tabla Información Adicional',
            ],
            'Especiales'=> [
                '{barcode}'              => 'Código de Barras (clave acceso)',
                '{texto_libre}'          => 'Texto fijo',
            ],
        ],
        'nota_credito' => [
            'Empresa'         => self::CAMPOS_EMPRESA,
            'Nota de Crédito' => self::CAMPOS_AUTORIZACION_SRI + [
                '{nc_motivo}'             => 'Motivo de la modificación',
                '{nc_num_doc_modificado}' => 'N.° del comprobante modificado',
                '{nc_fecha_doc_sustento}' => 'Fecha del comprobante modificado',
            ],
            'Cliente'         => self::CAMPOS_CLIENTE,
            'Totales'         => self::CAMPOS_TOTALES,
            'Tablas'          => [
                'tabla:detalles'       => 'Tabla de Ítems/Productos',
                'tabla:info_adicional' => 'Tabla Información Adicional',
            ],
            'Especiales'      => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'nota_debito' => [
            'Empresa'         => self::CAMPOS_EMPRESA,
            'Nota de Débito'  => self::CAMPOS_AUTORIZACION_SRI + [
                '{nd_num_doc_modificado}' => 'N.° del comprobante modificado',
                '{nd_fecha_doc_sustento}' => 'Fecha del comprobante modificado',
            ],
            'Cliente'         => self::CAMPOS_CLIENTE,
            'Totales'         => self::CAMPOS_TOTALES,
            'Tablas'          => [
                'tabla:motivos'        => 'Tabla de Motivos',
                'tabla:pagos'          => 'Tabla de Formas de Pago',
                'tabla:info_adicional' => 'Tabla Información Adicional',
            ],
            'Especiales'      => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'guia_remision' => [
            'Empresa'         => self::CAMPOS_EMPRESA,
            'Guía'            => [
                '{numero_factura}'                   => 'Número (001-001-000000001)',
                '{fecha_emision}'                     => 'Fecha de Emisión',
                '{numero_autorizacion}'               => 'Número de Autorización',
                '{clave_acceso}'                       => 'Clave de Acceso (49 dígitos)',
                '{fecha_autorizacion}'                 => 'Fecha y Hora de Autorización',
                '{ambiente}'                            => 'Ambiente (PRODUCCIÓN/PRUEBAS)',
                '{observaciones}'                       => 'Observaciones',
                '{gr_transportista_nombre}'            => 'Transportista (razón social)',
                '{gr_transportista_ruc}'                => 'Transportista (identificación)',
                '{gr_placa}'                             => 'Placa',
                '{gr_fecha_inicio_transporte}'         => 'Fecha inicio transporte',
                '{gr_fecha_fin_transporte}'            => 'Fecha fin transporte',
                '{gr_direccion_partida}'                => 'Dirección punto de partida',
                '{gr_direccion_destino}'                => 'Dirección punto de llegada',
                '{gr_motivo_traslado}'                  => 'Motivo del traslado',
                '{gr_ruta}'                              => 'Ruta',
                '{gr_doc_aduanero_unico}'              => 'Documento aduanero único',
                '{gr_num_doc_sustento}'                 => 'N.° documento de sustento',
                '{gr_cod_doc_sustento}'                 => 'Código documento de sustento',
                '{gr_num_autorizacion_doc_sustento}'   => 'Autorización documento de sustento',
                '{gr_fecha_doc_sustento}'               => 'Fecha documento de sustento',
            ],
            'Destinatario'    => self::CAMPOS_CLIENTE,
            'Tablas'          => ['tabla:detalles' => 'Tabla de Ítems/Productos'],
            'Especiales'      => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'liquidacion_compra' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Liquidación'=> self::CAMPOS_AUTORIZACION_SRI,
            'Proveedor'  => [
                '{proveedor_nombre}'    => 'Razón Social / Nombre',
                '{proveedor_ruc}'       => 'RUC / Identificación',
                '{proveedor_direccion}' => 'Dirección',
            ],
            'Totales'    => self::CAMPOS_TOTALES,
            'Tablas'     => [
                'tabla:detalles' => 'Tabla de Ítems/Productos',
                'tabla:pagos'    => 'Tabla de Formas de Pago',
            ],
            'Especiales' => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'compras' => [
            'Empresa'   => self::CAMPOS_EMPRESA,
            'Compra'    => [
                '{numero_factura}'              => 'Número (001-001-000000001)',
                '{fecha_emision}'                => 'Fecha de Emisión',
                '{compra_tipo_comprobante}'     => 'Tipo de comprobante (código SRI)',
                '{compra_numero_prov}'          => 'Número del proveedor (est-pto-sec)',
                '{compra_numero_autorizacion}'  => 'Número de Autorización',
                '{compra_fecha_autorizacion}'   => 'Fecha de Autorización',
                '{clave_acceso}'                  => 'Clave de Acceso (49 dígitos)',
                '{ambiente}'                       => 'Ambiente (PRODUCCIÓN/PRUEBAS)',
                '{observaciones}'                  => 'Observaciones',
            ],
            'Proveedor (emisor)' => [
                '{proveedor_nombre}'         => 'Razón Social / Nombre',
                '{proveedor_ruc}'            => 'RUC / Identificación',
                '{proveedor_nombre_tipo_id}' => 'Tipo de identificación',
                '{proveedor_direccion}'      => 'Dirección',
                '{proveedor_email}'          => 'Correo',
            ],
            'Totales'    => self::CAMPOS_TOTALES,
            'Tablas'     => ['tabla:detalles' => 'Tabla de Ítems/Productos'],
            'Especiales' => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'egreso' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Egreso'     => [
                '{cc_numero}'         => 'N.° de comprobante',
                '{fecha_emision}'      => 'Fecha de Emisión',
                '{cc_sujeto_nombre}'  => 'Pagado a (nombre)',
                '{cc_sujeto_ruc}'      => 'Pagado a (identificación)',
                '{cc_monto}'           => 'Monto',
                '{cc_monto_total}'     => 'Monto total',
                '{cc_monto_letras}'    => 'Monto en letras',
                '{cc_usuario_nombre}'  => 'Registrado por',
                '{cc_estado}'          => 'Estado',
                '{observaciones}'      => 'Observaciones / Por concepto de',
            ],
            'Tablas'     => [
                'tabla:detalles' => 'Tabla de conceptos/documentos pagados',
                'tabla:pagos'    => 'Tabla de Formas de Pago',
                'tabla:asiento'  => 'Tabla del Asiento Contable (Debe/Haber)',
            ],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'ingreso' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Ingreso'    => [
                '{cc_numero}'         => 'N.° de comprobante',
                '{fecha_emision}'      => 'Fecha de Emisión',
                '{cc_recibo_de}'       => 'Recibido de (nombre)',
                '{cc_monto}'           => 'Monto',
                '{cc_monto_total}'     => 'Monto total',
                '{cc_monto_letras}'    => 'Monto en letras',
                '{cc_usuario_nombre}'  => 'Registrado por',
                '{cc_estado}'          => 'Estado',
                '{observaciones}'      => 'Observaciones / Por concepto de',
            ],
            'Tablas'     => [
                'tabla:detalles' => 'Tabla de conceptos/documentos cobrados',
                'tabla:pagos'    => 'Tabla de Formas de Pago',
                'tabla:asiento'  => 'Tabla del Asiento Contable (Debe/Haber)',
            ],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'traspaso' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Traspaso'   => [
                '{cc_numero}'         => 'N.° de comprobante',
                '{fecha_emision}'      => 'Fecha de Emisión',
                '{cc_origen_nombre}'   => 'Cuenta origen',
                '{cc_destino_nombre}'  => 'Cuenta destino',
                '{cc_monto}'           => 'Monto',
                '{cc_monto_letras}'    => 'Monto en letras',
                '{cc_usuario_nombre}'  => 'Registrado por',
                '{observaciones}'      => 'Observaciones',
            ],
            'Tablas'     => ['tabla:asiento' => 'Tabla del Asiento Contable (Debe/Haber)'],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'proforma' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Proforma'   => [
                '{numero_factura}'    => 'Número (001-001-000000001)',
                '{fecha_emision}'      => 'Fecha de Emisión',
                '{pf_dias_vigencia}'  => 'Días de vigencia',
                '{pf_fecha_vigencia}' => 'Fecha límite de vigencia',
                '{observaciones}'      => 'Observaciones',
            ],
            'Cliente'    => self::CAMPOS_CLIENTE,
            'Totales'    => self::CAMPOS_TOTALES,
            'Tablas'     => ['tabla:detalles' => 'Tabla de Ítems/Productos'],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'retorno_cv' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Retorno'    => [
                '{cc_numero}'               => 'Número (serie-secuencial)',
                '{rt_fecha_retorno}'        => 'Fecha de retorno',
                '{rt_motivo}'               => 'Motivo',
                '{rt_usuario_nombre}'       => 'Realizado por',
                '{rt_responsable_traslado}' => 'Responsable de traslado',
                '{observaciones}'           => 'Observaciones',
            ],
            'Cliente'    => self::CAMPOS_CLIENTE,
            'Tablas'     => ['tabla:detalles' => 'Tabla de productos devueltos'],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'consignacion' => [
            'Empresa'       => self::CAMPOS_EMPRESA,
            'Consignación'  => [
                '{cc_numero}'               => 'Número (serie-secuencial)',
                '{fecha_emision}'            => 'Fecha de Emisión',
                '{cg_fecha_entrega}'        => 'Fecha de entrega',
                '{cg_vendedor_nombre}'      => 'Asesor / Vendedor',
                '{cg_responsable_traslado}' => 'Responsable de traslado',
                '{cg_punto_partida}'        => 'Punto de partida',
                '{cg_punto_llegada}'        => 'Punto de llegada',
                '{observaciones}'            => 'Observaciones',
            ],
            'Cliente'       => self::CAMPOS_CLIENTE,
            'Tablas'        => ['tabla:detalles' => 'Tabla de productos consignados'],
            'Especiales'    => ['{texto_libre}' => 'Texto fijo'],
        ],
        'cambio_producto_cv' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Cambio'     => [
                '{cc_numero}'              => 'Número (serie-secuencial)',
                '{cp_fecha_cambio}'        => 'Fecha del cambio',
                '{cp_usuario_nombre}'      => 'Realizado por',
                '{cp_motivo}'              => 'Motivo',
                '{cp_subtotal_devuelto}'   => 'Subtotal devuelto',
                '{cp_subtotal_entregado}'  => 'Subtotal entregado',
                '{cp_diferencia}'          => 'Diferencia',
                '{observaciones}'          => 'Observaciones',
            ],
            'Cliente'    => self::CAMPOS_CLIENTE,
            'Tablas'     => [
                'tabla:cambio_devuelto' => 'Tabla: productos que devuelve',
                'tabla:cambio_entrega'  => 'Tabla: productos que entrega a cambio',
            ],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'retencion_compra' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Retención'  => self::CAMPOS_AUTORIZACION_SRI + [
                '{ret_sujeto_nombre}'        => 'Sujeto retenido (razón social)',
                '{ret_sujeto_identificacion}'=> 'Sujeto retenido (identificación)',
                '{ret_periodo_fiscal}'       => 'Período fiscal',
                '{ret_tipo_doc_sustento}'    => 'Tipo de documento sustento',
                '{ret_num_doc_sustento}'     => 'N.° documento sustento',
                '{ret_fecha_doc_sustento}'   => 'Fecha documento sustento',
            ],
            'Tablas'     => ['tabla:retenciones' => 'Tabla: Detalle de Retenciones'],
            'Especiales' => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'retencion_venta' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Retención'  => self::CAMPOS_AUTORIZACION_SRI + [
                '{ret_sujeto_nombre}'         => 'Sujeto retenido (razón social)',
                '{ret_sujeto_identificacion}' => 'Sujeto retenido (identificación)',
                '{ret_periodo_fiscal}'        => 'Período fiscal',
            ],
            'Cliente'    => self::CAMPOS_CLIENTE,
            'Tablas'     => ['tabla:retenciones' => 'Tabla: Detalle de Retenciones'],
            'Especiales' => ['{barcode}' => 'Código de Barras (clave acceso)', '{texto_libre}' => 'Texto fijo'],
        ],
        'consignacion_factura' => [
            'Empresa'     => self::CAMPOS_EMPRESA,
            'Facturación' => [
                '{cc_numero}'           => 'Número (serie-secuencial)',
                '{fecha_emision}'        => 'Fecha de Emisión',
                '{cf_vendedor_nombre}'  => 'Vendedor',
                '{cf_factura_origen}'   => 'Factura relacionada',
                '{observaciones}'        => 'Observaciones',
            ],
            'Cliente'     => self::CAMPOS_CLIENTE,
            'Totales'     => self::CAMPOS_TOTALES,
            'Tablas'      => ['tabla:detalles' => 'Tabla de Ítems/Productos', 'tabla:info_adicional' => 'Tabla Información Adicional'],
            'Especiales'  => ['{texto_libre}' => 'Texto fijo'],
        ],
        'recibo_venta' => [
            'Empresa'    => self::CAMPOS_EMPRESA,
            'Recibo'     => [
                '{numero_factura}'   => 'Número (recibo, serie-secuencial)',
                '{fecha_emision}'     => 'Fecha de Emisión',
                '{rv_placa}'         => 'Placa (si aplica)',
                '{rv_monto_letras}'  => 'Monto en letras',
                '{rv_con_impuestos}' => 'Incluye impuestos (SI/NO)',
                '{guia_remision}'     => 'Guía de Remisión',
                '{observaciones}'     => 'Observaciones',
            ],
            'Cliente'    => self::CAMPOS_CLIENTE,
            'Totales'    => self::CAMPOS_TOTALES,
            'Tablas'     => ['tabla:detalles' => 'Tabla de Ítems/Productos', 'tabla:pagos' => 'Tabla de Formas de Pago'],
            'Especiales' => ['{texto_libre}' => 'Texto fijo'],
        ],
        'cheque' => [
            'Cheque'    => [
                '{beneficiario}'          => 'Beneficiario (Páguese a la orden de)',
                '{beneficiario_ident}'    => 'Identificación del beneficiario',
                '{monto_numero}'          => 'Monto en números (1,234.56)',
                '{monto_numero_protegido}'=> 'Monto números protegido (***1,234.56***)',
                '{monto_letras}'          => 'Monto en letras',
                '{fecha_cheque}'          => 'Fecha (dd/mm/aaaa)',
                '{fecha_iso}'             => 'Fecha (aaaa-mm-dd)',
                '{fecha_larga}'           => 'Fecha en texto (25 de julio de 2026)',
                '{ciudad}'                => 'Ciudad',
                '{ciudad_fecha}'          => 'Ciudad, fecha (QUITO, 2026-07-30)',
                '{dia}'                   => 'Día (dd)',
                '{mes}'                   => 'Mes (mm)',
                '{anio}'                  => 'Año (aaaa)',
                '{numero_cheque}'         => 'N.° de cheque',
                '{concepto}'              => 'Concepto / Observaciones',
                '{numero_egreso}'         => 'N.° de egreso',
            ],
            'Banco / Empresa' => [
                '{banco_nombre}'          => 'Banco',
                '{cuenta_numero}'         => 'N.° de cuenta',
                '{empresa_nombre}'        => 'Empresa (girador)',
                '{empresa_ruc}'           => 'RUC de la empresa',
            ],
            'Especiales'=> [
                '{texto_libre}'          => 'Texto fijo',
            ],
        ],
    ];

    // Columnas disponibles para cada tabla
    public const COLUMNAS_TABLA = [
        'tabla:detalles' => [
            '{codigo_principal}'          => 'Cód. Principal',
            '{codigo_auxiliar}'           => 'Cód. Auxiliar',
            '{cantidad}'                  => 'Cantidad',
            '{descripcion}'               => 'Descripción',
            '{detalle_adicional}'         => 'Det. Adicional',
            '{precio_unitario}'           => 'Precio Unitario',
            '{descuento}'                 => 'Descuento',
            '{precio_total}'              => 'Precio Total',
        ],
        'tabla:pagos' => [
            '{forma_pago}'                => 'Forma de Pago',
            '{valor_pago}'                => 'Valor',
            '{dias_credito}'              => 'Días Crédito',
            '{plazo_pago}'                => 'Plazo',
        ],
        'tabla:info_adicional' => [
            '{info_nombre}'               => 'Campo',
            '{info_valor}'                => 'Valor',
        ],
    ];

    public function __construct()
    {
        $this->repo = new PlantillasPdfRepository();
    }

    public function listar(int $idEmpresa, string $buscar = '', string $tipo = '', int $page = 1, int $perPage = 20): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $tipo, $page, $perPage);
    }

    /**
     * Crea una plantilla nueva. Si `$data['origen']` es 'original' (por defecto)
     * y el tipo de documento tiene una plantilla original registrada en
     * `PlantillasPdfSeedService`, la nueva plantilla nace de ese diseño; si no
     * hay una original para ese tipo, o el origen pedido es 'blanco', nace vacía
     * (el valor por defecto de `PlantillasPdfRepository::crear`).
     */
    public function crear(array $data): int
    {
        $this->validar($data);

        $origen = $data['origen'] ?? 'original';
        unset($data['origen']);
        if ($origen === 'original') {
            $seed = PlantillasPdfSeedService::getSeed($data['tipo_documento']);
            if ($seed !== null) {
                $data['configuracion'] = $seed;
            }
        }

        return $this->repo->crear($data);
    }

    /** Si el tipo de documento tiene una plantilla original para partir de ella. */
    public static function tieneSeedOriginal(string $tipoDocumento): bool
    {
        return PlantillasPdfSeedService::tieneSeed($tipoDocumento);
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $plantilla = $this->repo->getPorId($id);
        if (!$plantilla || (int)$plantilla['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        $this->validar($data);
        $this->repo->actualizar($id, $data);
    }

    public function guardarDiseno(int $id, int $idEmpresa, string $configuracionJson, int $idUsuario): void
    {
        $plantilla = $this->repo->getPorId($id);
        if (!$plantilla || (int)$plantilla['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        // Validar JSON
        $decoded = json_decode($configuracionJson, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('El diseño no es un JSON válido.');
        }
        $this->repo->guardarDiseno($id, $configuracionJson, $idUsuario);
    }

    public function activar(int $id, int $idEmpresa): void
    {
        $plantilla = $this->repo->getPorId($id);
        if (!$plantilla || (int)$plantilla['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        $idBanco = isset($plantilla['id_banco']) && $plantilla['id_banco'] !== null ? (int)$plantilla['id_banco'] : null;
        $this->repo->activar($id, $idEmpresa, $plantilla['tipo_documento'], $idBanco);
    }

    /**
     * Busca la plantilla de un tipo de documento para un banco específico; si no
     * existe ninguna, crea una nueva sembrada con $configuracionSeed y la activa
     * (queda como la plantilla vigente de ESE banco, sin afectar a otros bancos).
     * Devuelve el id de la plantilla lista para abrir en el diseñador.
     */
    public function obtenerOCrearParaBanco(
        int $idEmpresa,
        string $tipoDocumento,
        int $idBanco,
        string $nombrePlantilla,
        string $configuracionSeed,
        int $idUsuario
    ): int {
        $existente = $this->repo->getPorBanco($idEmpresa, $tipoDocumento, $idBanco);
        if ($existente) {
            if (empty($existente['es_activa'])) {
                $this->repo->activar((int)$existente['id'], $idEmpresa, $tipoDocumento, $idBanco);
            }
            return (int)$existente['id'];
        }

        $id = $this->repo->crear([
            'id_empresa'     => $idEmpresa,
            'tipo_documento' => $tipoDocumento,
            'nombre'         => $nombrePlantilla,
            'descripcion'    => 'Generada automáticamente al configurar la impresión desde Egresos.',
            'configuracion'  => $configuracionSeed,
            'id_banco'       => $idBanco,
            'created_by'     => $idUsuario,
        ]);
        $this->repo->activar($id, $idEmpresa, $tipoDocumento, $idBanco);
        return $id;
    }

    public function desactivar(int $id, int $idEmpresa): void
    {
        $plantilla = $this->repo->getPorId($id);
        if (!$plantilla || (int)$plantilla['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        $this->repo->desactivar($id, $idEmpresa);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $plantilla = $this->repo->getPorId($id);
        if (!$plantilla || (int)$plantilla['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        $this->repo->eliminar($id, $idUsuario);
    }

    public function getPorId(int $id, int $idEmpresa): array
    {
        $plantilla = $this->repo->getPorId($id);
        if (!$plantilla || (int)$plantilla['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Plantilla no encontrada.');
        }
        return $plantilla;
    }

    public function getCamposDisponibles(string $tipoDocumento): array
    {
        return self::CAMPOS[$tipoDocumento] ?? self::CAMPOS['factura_venta'];
    }

    public static function getTiposDocumento(): array
    {
        return [
            'factura_venta'         => 'Factura de Venta',
            'nota_credito'          => 'Nota de Crédito',
            'nota_debito'           => 'Nota de Débito',
            'liquidacion_compra'    => 'Liquidación de Compra',
            'guia_remision'         => 'Guía de Remisión',
            'compras'               => 'Compras (documento recibido)',
            'retencion_compra'      => 'Retención en Compras',
            'retencion_venta'       => 'Retención en Ventas',
            'recibo_venta'          => 'Recibo de Venta',
            'egreso'                => 'Egreso',
            'ingreso'               => 'Ingreso',
            'traspaso'              => 'Traspaso',
            'proforma'              => 'Proforma',
            'retorno_cv'            => 'Retorno de Consignación',
            'consignacion'          => 'Consignación en Ventas',
            'consignacion_factura'  => 'Facturación de Consignación',
            'cambio_producto_cv'    => 'Cambio de Productos',
            'cheque'                => 'Cheque',
        ];
    }

    private function validar(array $data): void
    {
        if (empty(trim($data['nombre'] ?? ''))) {
            throw new \InvalidArgumentException('El nombre de la plantilla es obligatorio.');
        }
        $tipos = array_keys(self::getTiposDocumento());
        if (!in_array($data['tipo_documento'] ?? '', $tipos, true)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }
    }
}
