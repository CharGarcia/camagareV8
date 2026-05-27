<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\modulos\PlantillasPdfRepository;

class PlantillasPdfService
{
    private PlantillasPdfRepository $repo;

    // Campos disponibles por tipo de documento
    private const CAMPOS = [
        'factura_venta' => [
            'Empresa'   => [
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
            ],
            'Factura'   => [
                '{numero_factura}'       => 'Número (001-001-000000001)',
                '{fecha_emision}'        => 'Fecha de Emisión',
                '{numero_autorizacion}'  => 'Número de Autorización',
                '{clave_acceso}'         => 'Clave de Acceso (49 dígitos)',
                '{fecha_autorizacion}'   => 'Fecha y Hora de Autorización',
                '{ambiente}'             => 'Ambiente (PRODUCCIÓN/PRUEBAS)',
                '{tipo_emision}'         => 'Tipo de Emisión',
                '{observaciones}'        => 'Observaciones',
            ],
            'Cliente'   => [
                '{cliente_nombre}'       => 'Razón Social / Nombre',
                '{cliente_ruc}'          => 'RUC / Cédula',
                '{cliente_direccion}'    => 'Dirección',
                '{cliente_email}'        => 'Correo',
                '{cliente_telefono}'     => 'Teléfono',
                '{guia_remision}'        => 'Guía de Remisión',
                '{plazo}'                => 'Plazo / Días Crédito',
            ],
            'Totales'   => [
                '{subtotal_0}'           => 'Subtotal IVA 0%',
                '{subtotal_iva}'         => 'Subtotal IVA X%',
                '{total_descuento}'      => 'Total Descuento',
                '{ice}'                  => 'ICE',
                '{iva}'                  => 'IVA',
                '{propina}'              => 'Propina',
                '{valor_total}'          => 'VALOR TOTAL',
            ],
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

    public function crear(array $data): int
    {
        $this->validar($data);
        return $this->repo->crear($data);
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
        $this->repo->activar($id, $idEmpresa, $plantilla['tipo_documento']);
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
            'factura_venta'       => 'Factura de Venta',
            'nota_credito'        => 'Nota de Crédito',
            'nota_debito'         => 'Nota de Débito',
            'liquidacion_compra'  => 'Liquidación de Compra',
            'guia_remision'       => 'Guía de Remisión',
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
