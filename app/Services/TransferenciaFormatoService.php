<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\TransferenciaFormatoRepository;

/**
 * Lógica de negocio del catálogo global de Formatos de Transferencia
 * Bancaria (config/transferencia-formatos, nivel 3). Ver
 * app/Services/modulos/Transferencias/Formatters/TransferenciaFormatoConfigurable.php
 * para cómo se consumen `campos` al generar el archivo de un lote.
 */
class TransferenciaFormatoService
{
    public const TIPOS_ARCHIVO = ['xlsx', 'csv', 'txt_delimitado', 'txt_ancho_fijo'];

    /** Whitelist de datos disponibles para mapear en una columna del layout. */
    public const ORIGEN_DATO = [
        'tipo_beneficiario'       => 'Tipo de beneficiario (PROVEEDOR / EMPLEADO)',
        'identificacion'          => 'Identificación (cédula / RUC)',
        'nombre_beneficiario'     => 'Nombre del beneficiario',
        'codigo_banco'            => 'Código del banco del beneficiario',
        'nombre_banco_beneficiario' => 'Nombre del banco del beneficiario',
        'tipo_cuenta'             => 'Tipo de cuenta (ahorros / corriente / virtual / otro)',
        'numero_cuenta'           => 'Número de cuenta del beneficiario',
        'telefono'                => 'Teléfono del beneficiario',
        'monto'                   => 'Monto a transferir',
        'concepto'                => 'Concepto / referencia del pago',
        'numero_egreso'           => 'Número de egreso',
        'secuencial'              => 'Secuencial de la línea dentro del lote (1, 2, 3…)',
        'numero_lote'             => 'Número del lote',
        'fecha_pago'              => 'Fecha de pago del lote',
        'cuenta_empresa'          => 'Cuenta de origen (empresa)',
        'moneda'                  => 'Moneda (USD)',
        'texto_fijo'              => 'Texto fijo (definido en el propio campo)',
    ];

    private TransferenciaFormatoRepository $repo;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->repo = new TransferenciaFormatoRepository();
        $this->log = new LogSistemaService();
    }

    public function listar(string $buscar = ''): array
    {
        return $this->repo->getAll($buscar);
    }

    public function getPorId(int $id): array
    {
        $formato = $this->repo->getById($id);
        if (!$formato) {
            throw new \InvalidArgumentException('Formato no encontrado.');
        }
        return $formato;
    }

    public function crear(array $data, int $idUsuario): int
    {
        $data = $this->normalizarYValidar($data);
        $data['created_by'] = $idUsuario;

        $id = $this->repo->crear($data);
        $this->log->registrar($idUsuario, null, 'crear', 'transferencia_formatos', $id, null, $data);
        return $id;
    }

    public function actualizar(int $id, array $data, int $idUsuario): void
    {
        $anterior = $this->getPorId($id);
        if (!empty($anterior['clase_formatter'])) {
            // Fila "avanzada" (clase_formatter): solo se permite tocar nombre, banco y estado.
            $data = array_merge($anterior, [
                'nombre'   => trim((string) ($data['nombre'] ?? $anterior['nombre'])),
                'id_banco' => (int) ($data['id_banco'] ?? 0) ?: null,
                'estado'   => $data['estado'] ?? $anterior['estado'],
            ]);
        } else {
            $data = $this->normalizarYValidar($data);
        }
        $data['updated_by'] = $idUsuario;

        $this->repo->actualizar($id, $data);
        $this->log->registrar($idUsuario, null, 'actualizar', 'transferencia_formatos', $id, $anterior, $data);
    }

    public function cambiarEstado(int $id, string $estado, int $idUsuario): void
    {
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            throw new \InvalidArgumentException('Estado inválido.');
        }
        $anterior = $this->getPorId($id);
        $this->repo->cambiarEstado($id, $estado, $idUsuario);
        $this->log->registrar($idUsuario, null, 'cambiar_estado', 'transferencia_formatos', $id, ['estado' => $anterior['estado']], ['estado' => $estado]);
    }

    public function eliminar(int $id, int $idUsuario): void
    {
        $anterior = $this->getPorId($id);
        if ($this->repo->tieneLotesAsociados($id)) {
            throw new \InvalidArgumentException('No se puede eliminar: hay lotes de transferencia que usan este formato.');
        }
        $this->repo->eliminar($id, $idUsuario);
        $this->log->registrar($idUsuario, null, 'eliminar', 'transferencia_formatos', $id, $anterior, null);
    }

    private function normalizarYValidar(array $data): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del formato es obligatorio.');
        }

        $tipoArchivo = trim((string) ($data['tipo_archivo'] ?? ''));
        if (!in_array($tipoArchivo, self::TIPOS_ARCHIVO, true)) {
            throw new \InvalidArgumentException('Tipo de archivo inválido.');
        }

        $campos = $this->normalizarCampos($data['campos'] ?? [], $tipoArchivo);
        if (empty($campos)) {
            throw new \InvalidArgumentException('El formato debe tener al menos un campo.');
        }

        return [
            'id_banco'           => (int) ($data['id_banco'] ?? 0) ?: null,
            'nombre'             => $nombre,
            'descripcion'        => trim((string) ($data['descripcion'] ?? '')),
            'tipo_archivo'       => $tipoArchivo,
            'delimitador'        => in_array($tipoArchivo, ['csv', 'txt_delimitado'], true) ? (trim((string) ($data['delimitador'] ?? '')) ?: ',') : null,
            'incluye_encabezado' => !empty($data['incluye_encabezado']),
            'nombre_hoja'        => $tipoArchivo === 'xlsx' ? (trim((string) ($data['nombre_hoja'] ?? '')) ?: 'Transferencias') : null,
            'campos'             => $campos,
            'clase_formatter'    => null,
            'estado'             => in_array($data['estado'] ?? '', ['activo', 'inactivo'], true) ? $data['estado'] : 'activo',
        ];
    }

    private function normalizarCampos(array $campos, string $tipoArchivo): array
    {
        $out = [];
        $orden = 1;
        foreach ($campos as $c) {
            $origenDato = trim((string) ($c['origen_dato'] ?? ''));
            if (!isset(self::ORIGEN_DATO[$origenDato])) {
                continue;
            }
            $etiqueta = trim((string) ($c['etiqueta'] ?? ''));
            if ($etiqueta === '') {
                continue;
            }

            $longitudFija = isset($c['longitud_fija']) && $c['longitud_fija'] !== '' ? (int) $c['longitud_fija'] : null;
            if ($tipoArchivo === 'txt_ancho_fijo' && !$longitudFija) {
                throw new \InvalidArgumentException("El campo \"$etiqueta\" necesita una longitud fija (el tipo de archivo es ancho fijo).");
            }

            $out[] = [
                'orden'             => $orden++,
                'etiqueta'          => $etiqueta,
                'origen_dato'       => $origenDato,
                'valor_fijo'        => $origenDato === 'texto_fijo' ? trim((string) ($c['valor_fijo'] ?? '')) : null,
                'tipo_dato'         => in_array($c['tipo_dato'] ?? '', ['texto', 'numero', 'fecha'], true) ? $c['tipo_dato'] : 'texto',
                'formato_numero'    => in_array($c['formato_numero'] ?? '', ['decimal_punto', 'entero_centavos'], true) ? $c['formato_numero'] : null,
                'decimales'         => isset($c['decimales']) && $c['decimales'] !== '' ? max(0, (int) $c['decimales']) : 2,
                'longitud_fija'     => $longitudFija,
                'relleno_caracter'  => trim((string) ($c['relleno_caracter'] ?? '')) !== '' ? substr(trim((string) $c['relleno_caracter']), 0, 1) : null,
                'alineacion'        => in_array($c['alineacion'] ?? '', ['izquierda', 'derecha'], true) ? $c['alineacion'] : null,
                'mayusculas'        => !empty($c['mayusculas']),
                'quitar_tildes'     => !empty($c['quitar_tildes']),
                'solo_alfanumerico' => !empty($c['solo_alfanumerico']),
                'max_caracteres'    => isset($c['max_caracteres']) && $c['max_caracteres'] !== '' ? max(1, (int) $c['max_caracteres']) : null,
                'mapeo_valores'     => $this->normalizarMapeo($c['mapeo_valores'] ?? null),
            ];
        }
        return $out;
    }

    /** Acepta un array asociativo ya decodificado o el texto "clave=valor" por línea que arma la vista. */
    private function normalizarMapeo($mapeo): ?array
    {
        if (is_array($mapeo)) {
            return empty($mapeo) ? null : $mapeo;
        }
        if (!is_string($mapeo) || trim($mapeo) === '') {
            return null;
        }
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $mapeo) as $linea) {
            if (!str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
            if ($clave !== '') {
                $out[$clave] = $valor;
            }
        }
        return empty($out) ? null : $out;
    }
}
