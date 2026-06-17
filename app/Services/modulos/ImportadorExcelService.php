<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Services\LogSistemaService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PDO;
use Exception;

class ImportadorExcelService
{
    protected PDO $db;
    protected LogSistemaService $logService;

    public function __construct(PDO $db, LogSistemaService $logService)
    {
        $this->db = $db;
        $this->logService = $logService;
    }

    public function getEntidadesDisponibles(): array
    {
        return [
            // Operativas (Requieren Empresa)
            'clientes' => [
                'nombre' => 'Clientes',
                'global' => false,
                'col_numericas' => [6], // DIAS_PLAZO
                'columnas' => [
                    'TIPO_IDENTIFICACION (ver hoja Tipos_ID)',
                    'IDENTIFICACION',
                    'NOMBRE_RAZON_SOCIAL',
                    'DIRECCION',
                    'EMAIL',
                    'TELEFONO',
                    'DIAS_PLAZO',
                    'PROVINCIA (nombre exacto, opcional)',
                    'CIUDAD (nombre exacto, opcional - requiere PROVINCIA)',
                ]
            ],
            'productos' => [
                'nombre' => 'Productos',
                'global' => false,
                'col_numericas' => [5], // PRECIO_BASE_SIN_IVA
                'columnas' => [
                    'CODIGO_PRINCIPAL',
                    'CODIGO_AUXILIAR',
                    'CODIGO_BARRAS',
                    'NOMBRE',
                    'TIPO (Producto / Servicio)',
                    'PRECIO_BASE_SIN_IVA',
                    'CODIGO_IVA (ver hoja Tarifas_IVA)',
                    'INVENTARIABLE (Si / No)',
                    'APLICA_A (Compras / Ventas / Ambos)',
                    'CATEGORIA (nombre, se crea si no existe)',
                    'MARCA (nombre, se crea si no existe)',
                    'CODIGO_MEDIDA (ver hoja Unidades_Medida, solo aplica si TIPO=Producto)'
                ]
            ],
            'vehiculos' => [
                'nombre' => 'Vehículos',
                'global' => false,
                'col_numericas' => [3], // ANIO
                'columnas' => ['PLACA', 'MARCA', 'MODELO', 'ANIO']
            ],
            'proveedores' => [
                'nombre' => 'Proveedores',
                'global' => false,
                'col_numericas' => [7], // DIAS_PLAZO
                'columnas' => [
                    'TIPO_IDENTIFICACION (ver hoja Tipos_ID)',
                    'IDENTIFICACION',
                    'RAZON_SOCIAL',
                    'NOMBRE_COMERCIAL (opcional)',
                    'DIRECCION',
                    'EMAIL',
                    'TELEFONO',
                    'DIAS_PLAZO',
                    'PROVINCIA (nombre exacto, opcional)',
                    'CIUDAD (nombre exacto, opcional - requiere PROVINCIA)',
                    'TIPO_EMPRESA (nombre exacto, ver hoja Tipos_Empresa)',
                    'BANCO (nombre exacto, ver hoja Bancos)',
                    'TIPO_CUENTA (Ahorros / Corriente / Virtual / Otro)',
                    'NUMERO_CUENTA',
                    'SUSTENTO_TRIBUTARIO (código, ver hoja Sustento_Tributario, opcional)',
                ]
            ],
            'empleados' => [
                'nombre' => 'Empleados',
                'global' => false,
                'col_numericas' => [7], // SUELDO_BASE
                'columnas' => ['TIPO_IDENTIFICACION', 'IDENTIFICACION', 'NOMBRES_APELLIDOS', 'EMAIL', 'TELEFONO', 'DIRECCION', 'CARGO', 'SUELDO_BASE']
            ],
            'tipo_medida' => [
                'nombre' => 'Tipos de Medida',
                'global' => false,
                'col_numericas' => [],
                'columnas' => ['CODIGO', 'NOMBRE']
            ],
            'unidades_medida' => [
                'nombre' => 'Unidades de Medida',
                'global' => false,
                'col_numericas' => [],
                'columnas' => ['CODIGO_TIPO_MEDIDA', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD']
            ],
            'plan_cuentas' => [
                'nombre' => 'Plan de Cuentas',
                'global' => false,
                'col_numericas' => [3], // NIVEL
                'columnas' => ['CODIGO_CUENTA', 'NOMBRE_CUENTA', 'TIPO_CUENTA', 'NIVEL']
            ],

            // Globales (Sin Empresa)
            'retenciones_sri' => [
                'nombre' => 'Retenciones SRI',
                'global' => true,
                'col_numericas' => [2], // PORCENTAJE
                'columnas' => ['CODIGO_RETENCION', 'CONCEPTO', 'PORCENTAJE', 'IMPUESTO', 'CODIGO_ATS', 'DESDE', 'HASTA']
            ],
        ];
    }

    public function procesar(string $archivoTmp, string $entidadId, int $idEmpresa, string $tipoAmbiente, int $idUsuario): int
    {
        $entidades = $this->getEntidadesDisponibles();
        if (!isset($entidades[$entidadId])) {
            throw new Exception("Entidad no soportada: {$entidadId}");
        }

        $entidad = $entidades[$entidadId];
        $esGlobal = $entidad['global'] ?? false;

        // Cargar archivo
        $spreadsheet = IOFactory::load($archivoTmp);

        // Validar que el archivo corresponda al establecimiento destino (solo entidades operativas)
        if (!$esGlobal) {
            $this->validarEmpresaPlantilla($spreadsheet, $idEmpresa, $entidadId);
        }

        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray();

        if (count($filas) <= 1) {
            throw new Exception("El archivo está vacío o solo contiene los encabezados.");
        }

        $this->db->beginTransaction();
        $insertados = 0;

        try {
            for ($i = 1; $i < count($filas); $i++) {
                $fila = $filas[$i];
                if (empty(array_filter($fila))) {
                    continue; // Fila vacía
                }

                $numeroFila = $i + 1;
                
                // Mapear según entidad
                $idInsertado = $this->insertarFila($entidadId, $fila, $numeroFila, $esGlobal ? null : $idEmpresa, $esGlobal ? null : $tipoAmbiente, $idUsuario);
                
                if ($idInsertado > 0) {
                    $this->logService->registrar(
                        $idUsuario,
                        $esGlobal ? null : $idEmpresa,
                        "importar_{$entidadId}_excel",
                        $this->getTablaEntidad($entidadId),
                        $idInsertado,
                        null,
                        ['origen' => 'excel', 'fila' => $numeroFila]
                    );
                    $insertados++;
                }
            }

            $this->db->commit();
            return $insertados;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getTablaEntidad(string $entidadId): string
    {
        $mapa = [
            'clientes' => 'clientes',
            'productos' => 'productos',
            'vehiculos' => 'vehiculos',
            'proveedores' => 'proveedores',
            'empleados' => 'empleados',
            'tipo_medida' => 'tipo_medida',
            'unidades_medida' => 'unidades_medida',
            'plan_cuentas' => 'plan_cuentas',
            'retenciones_sri' => 'retenciones_sri',
        ];
        return $mapa[$entidadId] ?? 'desconocida';
    }

    private function insertarFila(string $entidadId, array $fila, int $numeroFila, ?int $idEmpresa, ?string $tipoAmbiente, int $idUsuario): int
    {
        switch ($entidadId) {
            case 'clientes':
                return $this->insertarCliente($fila, $numeroFila, $idEmpresa, $tipoAmbiente, $idUsuario);
            case 'productos':
                return $this->insertarProducto($fila, $numeroFila, $idEmpresa, $tipoAmbiente, $idUsuario);
            case 'vehiculos':
                return $this->insertarVehiculo($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'proveedores':
                return $this->insertarProveedor($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'empleados':
                return $this->insertarEmpleado($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'tipo_medida':
                return $this->insertarTipoMedida($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'unidades_medida':
                return $this->insertarUnidadMedida($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'plan_cuentas':
                return $this->insertarPlanCuenta($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'retenciones_sri':
                return $this->insertarRetencionSri($fila, $numeroFila, $idUsuario);
            default:
                throw new Exception("Lógica de inserción no definida para {$entidadId}");
        }
    }

    private function insertarCliente(array $fila, int $numeroFila, int $idEmpresa, string $tipoAmbiente, int $idUsuario): int
    {
        $tipoIdRaw      = $this->sanitizarTexto(trim((string)($fila[0] ?? '')), 50);
        $identificacion = $this->sanitizarTexto(trim((string)($fila[1] ?? '')), 20);
        $nombre         = $this->sanitizarTexto(trim((string)($fila[2] ?? '')), 255);
        $direccion      = $this->sanitizarTexto(trim((string)($fila[3] ?? '')), 255);
        $emailRaw       = $this->sanitizarTexto(trim((string)($fila[4] ?? '')), 500);
        $telefono       = $this->sanitizarTexto(trim((string)($fila[5] ?? '')), 30);
        $plazo          = abs((int)($fila[6] ?? 0));
        $provinciaNombre = trim((string)($fila[7] ?? ''));
        $ciudadNombre    = trim((string)($fila[8] ?? ''));

        // Validar y normalizar emails
        $email = $this->validarYNormalizarEmails($emailRaw, $numeroFila);

        if (empty($identificacion) || empty($nombre)) {
            throw new Exception("Fila {$numeroFila}: Identificación y Nombre son obligatorios.");
        }

        // Resolver código de tipo de identificación (solo 04,05,06,07,08 para clientes)
        $tipoId = $this->resolverTipoIdentificacion($tipoIdRaw, $numeroFila, ['04', '05', '06', '07', '08']);

        // Validar identificación según tipo
        $this->validarIdentificacionCliente($tipoId, $identificacion, $nombre, $idEmpresa, $numeroFila);

        // Resolver provincia y ciudad
        [$codProvincia, $codCiudad] = $this->resolverProvinciaYCiudad($provinciaNombre, $ciudadNombre, $numeroFila);

        // Verificar si ya existe → UPDATE, si no → INSERT
        $stCheck = $this->db->prepare(
            "SELECT id FROM clientes WHERE id_empresa = ? AND identificacion = ? AND eliminado = false LIMIT 1"
        );
        $stCheck->execute([$idEmpresa, $identificacion]);
        $idExistente = $stCheck->fetchColumn();

        if ($idExistente) {
            $sql = "UPDATE clientes SET
                        tipo_id = ?, nombre = ?, direccion = ?, email = ?, telefono = ?,
                        plazo = ?, provincia = ?, ciudad = ?,
                        updated_by = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND id_empresa = ?";
            $st = $this->db->prepare($sql);
            $st->execute([
                $tipoId, $nombre, $direccion, $email, $telefono,
                $plazo, $codProvincia, $codCiudad,
                $idUsuario, (int)$idExistente, $idEmpresa,
            ]);
            return (int)$idExistente;
        }

        $sql = "INSERT INTO clientes (
                    id_empresa, id_usuario, tipo_id, identificacion, nombre,
                    direccion, email, telefono, plazo,
                    provincia, ciudad,
                    status, eliminado, created_by, updated_by
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    1, false, ?, ?
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            $idEmpresa, $idUsuario, $tipoId, $identificacion, $nombre,
            $direccion, $email, $telefono, $plazo,
            $codProvincia, $codCiudad,
            $idUsuario, $idUsuario,
        ]);
        return (int)$st->fetchColumn();
    }

    /**
     * Resuelve el código de tipo de identificación (varchar 2) buscando por código exacto
     * o por nombre en la tabla identificador_comprador_vendedor.
     * Si viene vacío se permite (null). Si viene un valor no reconocido lanza excepción.
     */
    /**
     * Valida uno o varios correos separados por coma (con o sin espacio).
     * Retorna la cadena normalizada (emails separados por ", ") o cadena vacía si no se ingresó.
     * Lanza excepción si algún email tiene formato inválido.
     */
    private function validarYNormalizarEmails(string $emailRaw, int $numeroFila): string
    {
        if ($emailRaw === '') {
            return '';
        }

        // Separar por coma (con o sin espacio alrededor)
        $partes = array_filter(
            array_map('trim', explode(',', $emailRaw)),
            fn($e) => $e !== ''
        );

        $validos = [];
        foreach ($partes as $correo) {
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(
                    "Fila {$numeroFila}: El correo '{$correo}' no tiene un formato válido. " .
                    "Verifique que cada dirección sea correcta (pueden separarse por coma)."
                );
            }
            $validos[] = strtolower($correo);
        }

        return implode(', ', $validos);
    }

    /**
     * Valida la identificación y nombre del cliente según su tipo.
     * También valida unicidad de identificación en la empresa.
     */
    private function validarIdentificacionCliente(?string $tipoId, string $identificacion, string $nombre, int $idEmpresa, int $numeroFila): void
    {
        switch ($tipoId) {
            case '04': // RUC — 13 dígitos numéricos
                if (!preg_match('/^\d{13}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 04 (RUC) la identificación debe tener exactamente 13 dígitos numéricos. Valor recibido: '{$identificacion}'.");
                }
                break;

            case '05': // Cédula — 10 dígitos numéricos
                if (!preg_match('/^\d{10}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 05 (Cédula) la identificación debe tener exactamente 10 dígitos numéricos. Valor recibido: '{$identificacion}'.");
                }
                break;

            case '06': // Pasaporte — hasta 20 alfanuméricos
                if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 06 (Pasaporte) la identificación debe ser alfanumérica de hasta 20 caracteres. Valor recibido: '{$identificacion}'.");
                }
                break;

            case '07': // Consumidor Final — identificación fija y nombre exacto
                if ($identificacion !== '9999999999999') {
                    throw new Exception("Fila {$numeroFila}: Para tipo 07 (Consumidor Final) la identificación debe ser exactamente '9999999999999'.");
                }
                if (strtoupper(trim($nombre)) !== 'CONSUMIDOR FINAL') {
                    throw new Exception("Fila {$numeroFila}: Para tipo 07 el nombre debe ser exactamente 'CONSUMIDOR FINAL'. Valor recibido: '{$nombre}'.");
                }
                // Solo puede existir un Consumidor Final por empresa
                $stCf = $this->db->prepare(
                    "SELECT id FROM clientes WHERE id_empresa = ? AND tipo_id = '07' AND eliminado = false LIMIT 1"
                );
                $stCf->execute([$idEmpresa]);
                if ($stCf->fetchColumn()) {
                    throw new Exception("Fila {$numeroFila}: Ya existe un registro de CONSUMIDOR FINAL para esta empresa. Solo puede haber uno.");
                }
                return; // No verificar duplicado de identificación más abajo

            case '08': // Identificación del exterior — hasta 20 alfanuméricos
                if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 08 (Exterior) la identificación debe ser alfanumérica de hasta 20 caracteres. Valor recibido: '{$identificacion}'.");
                }
                break;
        }

    }

    /**
     * Resuelve el código de tipo de identificación buscando por código o nombre.
     * $codigosPermitidos restringe los códigos válidos para la entidad (ej: clientes solo 04-08).
     */
    private function resolverTipoIdentificacion(string $valorRaw, int $numeroFila, array $codigosPermitidos = []): ?string
    {
        if ($valorRaw === '') {
            return null;
        }

        // Construir cláusula de restricción si aplica
        $placeholders = implode(',', array_fill(0, count($codigosPermitidos), '?'));
        $clausulaPermitidos = $codigosPermitidos
            ? " AND codigo IN ({$placeholders})"
            : '';

        // Buscar por código exacto (ej: "04", "05")
        $st = $this->db->prepare(
            "SELECT codigo FROM identificador_comprador_vendedor
              WHERE UPPER(TRIM(codigo)) = UPPER(TRIM(?)) AND status = 1{$clausulaPermitidos} LIMIT 1"
        );
        $st->execute(array_merge([$valorRaw], $codigosPermitidos));
        $codigo = $st->fetchColumn();
        if ($codigo) return (string)$codigo;

        // Buscar por nombre exacto (ej: "RUC", "Cédula")
        $st2 = $this->db->prepare(
            "SELECT codigo FROM identificador_comprador_vendedor
              WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(?)) AND status = 1{$clausulaPermitidos} LIMIT 1"
        );
        $st2->execute(array_merge([$valorRaw], $codigosPermitidos));
        $codigo = $st2->fetchColumn();
        if ($codigo) return (string)$codigo;

        // Buscar por nombre parcial
        $st3 = $this->db->prepare(
            "SELECT codigo FROM identificador_comprador_vendedor
              WHERE UPPER(nombre) LIKE UPPER(?) AND status = 1{$clausulaPermitidos} ORDER BY codigo LIMIT 1"
        );
        $st3->execute(array_merge(['%' . $valorRaw . '%'], $codigosPermitidos));
        $codigo = $st3->fetchColumn();
        if ($codigo) return (string)$codigo;

        $permitidosTexto = $codigosPermitidos
            ? ' Los códigos permitidos para clientes son: ' . implode(', ', $codigosPermitidos) . '.'
            : '';

        throw new Exception(
            "Fila {$numeroFila}: El tipo de identificación '{$valorRaw}' no fue reconocido o no está permitido.{$permitidosTexto} " .
            "Consulte la hoja 'Tipos_ID' de la plantilla."
        );
    }

    /**
     * Resuelve códigos de provincia y ciudad a partir de sus nombres.
     * - Si ambos vacíos: retorna [null, null] (campo opcional).
     * - Si provincia no se reconoce: lanza excepción.
     * - Si ciudad no se reconoce dentro de la provincia: lanza excepción.
     * - Si solo se ingresa ciudad sin provincia: lanza excepción pidiendo la provincia.
     */
    private function resolverProvinciaYCiudad(string $provinciaNombre, string $ciudadNombre, int $numeroFila): array
    {
        $provinciaNombre = trim($provinciaNombre);
        $ciudadNombre    = trim($ciudadNombre);

        // Ambos vacíos → opcional, se permite
        if ($provinciaNombre === '' && $ciudadNombre === '') {
            return [null, null];
        }

        // Ciudad sin provincia → error
        if ($provinciaNombre === '' && $ciudadNombre !== '') {
            throw new Exception("Fila {$numeroFila}: Se ingresó CIUDAD ('{$ciudadNombre}') pero falta la PROVINCIA. Ambos campos son necesarios.");
        }

        // Buscar provincia por nombre exacto, luego parcial
        $stProv = $this->db->prepare(
            "SELECT codigo FROM provincia WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(?)) LIMIT 1"
        );
        $stProv->execute([$provinciaNombre]);
        $codProvincia = $stProv->fetchColumn();

        if (!$codProvincia) {
            // Intento con LIKE
            $stProv2 = $this->db->prepare(
                "SELECT codigo FROM provincia WHERE UPPER(nombre) LIKE UPPER(?) LIMIT 1"
            );
            $stProv2->execute(['%' . $provinciaNombre . '%']);
            $codProvincia = $stProv2->fetchColumn();
        }

        if (!$codProvincia) {
            throw new Exception("Fila {$numeroFila}: La provincia '{$provinciaNombre}' no fue encontrada en el sistema. Verifique el nombre exacto.");
        }

        // Solo provincia sin ciudad → se guarda la provincia, ciudad null
        if ($ciudadNombre === '') {
            return [(string)$codProvincia, null];
        }

        // Buscar ciudad dentro de la provincia
        $stCiud = $this->db->prepare(
            "SELECT codigo FROM ciudad WHERE cod_prov = ? AND UPPER(TRIM(nombre)) = UPPER(TRIM(?)) LIMIT 1"
        );
        $stCiud->execute([$codProvincia, $ciudadNombre]);
        $codCiudad = $stCiud->fetchColumn();

        if (!$codCiudad) {
            // Intento con LIKE
            $stCiud2 = $this->db->prepare(
                "SELECT codigo FROM ciudad WHERE cod_prov = ? AND UPPER(nombre) LIKE UPPER(?) LIMIT 1"
            );
            $stCiud2->execute([$codProvincia, '%' . $ciudadNombre . '%']);
            $codCiudad = $stCiud2->fetchColumn();
        }

        if (!$codCiudad) {
            throw new Exception("Fila {$numeroFila}: La ciudad '{$ciudadNombre}' no fue encontrada dentro de la provincia '{$provinciaNombre}'. Verifique el nombre exacto.");
        }

        return [(string)$codProvincia, (string)$codCiudad];
    }

    /**
     * Verifica que el archivo Excel haya sido generado para el mismo establecimiento destino.
     * Lee la hoja oculta _Config y compara el id_empresa embebido.
     * Solo aplica a plantillas operativas (productos, clientes, etc.).
     * Si la hoja _Config no existe se permite la importación (plantillas antiguas sin esta validación).
     */
    private function validarEmpresaPlantilla(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, int $idEmpresaDestino, string $entidadId): void
    {
        // Solo validar entidades que generan hoja _Config (actualmente: productos)
        if ($entidadId !== 'productos') {
            return;
        }

        try {
            $sheetConfig = $spreadsheet->getSheetByName('_Config');
        } catch (\Throwable $e) {
            $sheetConfig = null;
        }

        // Si no existe la hoja _Config (plantilla antigua), se permite continuar
        if ($sheetConfig === null) {
            return;
        }

        $idEmpresaArchivo  = trim((string)($sheetConfig->getCell([2, 1])->getValue() ?? ''));
        $labelEstablecimiento = trim((string)($sheetConfig->getCell([2, 2])->getValue() ?? 'desconocido'));

        if ($idEmpresaArchivo === '') {
            return; // Sin dato embebido, se permite
        }

        if ((int)$idEmpresaArchivo !== $idEmpresaDestino) {
            // Obtener nombre del establecimiento destino para el mensaje
            $stDest = $this->db->prepare(
                "SELECT establecimiento, COALESCE(NULLIF(nombre_comercial,''), nombre) AS nombre_emp
                   FROM empresas WHERE id = ? AND eliminado = false LIMIT 1"
            );
            $stDest->execute([$idEmpresaDestino]);
            $destRow = $stDest->fetch(\PDO::FETCH_ASSOC);
            $labelDestino = $destRow
                ? 'Est. ' . ($destRow['establecimiento'] ?? '001') . ' - ' . $destRow['nombre_emp']
                : 'ID ' . $idEmpresaDestino;

            throw new Exception(
                "Este archivo fue generado para el establecimiento: \"{$labelEstablecimiento}\". " .
                "No puede importarse en \"{$labelDestino}\". " .
                "Descargue la plantilla correcta para el establecimiento de destino."
            );
        }
    }

    private function sanitizarTexto(string $valor, int $maxLen = 255): string
    {
        // Eliminar caracteres de control y caracteres peligrosos para SQL/XSS
        $valor = preg_replace('/[\x00-\x1F\x7F]/', '', $valor);
        $valor = preg_replace("/['\";\\\\<>]/", '', $valor);
        return mb_substr(trim($valor), 0, $maxLen);
    }

    private function insertarProducto(array $fila, int $numeroFila, int $idEmpresa, string $tipoAmbiente, int $idUsuario): int
    {
        $codigoPrincipal = $this->sanitizarTexto(trim((string)($fila[0] ?? '')), 50);
        $codigoAuxiliar  = $this->sanitizarTexto(trim((string)($fila[1] ?? '')), 50);
        $codigoBarras    = $this->sanitizarTexto(trim((string)($fila[2] ?? '')), 50);
        $nombre          = $this->sanitizarTexto(trim((string)($fila[3] ?? '')), 255);
        $tipoRaw         = strtolower(trim((string)($fila[4] ?? 'producto')));
        $precio          = abs(floatval($fila[5] ?? 0));
        $codigoIvaRaw    = $this->sanitizarTexto(trim((string)($fila[6] ?? '')), 50);
        $inventariableRaw= strtolower(trim((string)($fila[7] ?? 'si')));
        $aplicaARaw      = strtolower(trim((string)($fila[8] ?? 'ambos')));
        $categoriaNombre  = $this->sanitizarTexto(trim((string)($fila[9]  ?? '')), 150);
        $marcaNombre      = $this->sanitizarTexto(trim((string)($fila[10] ?? '')), 150);
        $codigoMedidaRaw  = $this->sanitizarTexto(trim((string)($fila[11] ?? '')), 30);

        // Obligatorios
        if (empty($codigoPrincipal)) {
            throw new Exception("Fila {$numeroFila}: CODIGO_PRINCIPAL es obligatorio.");
        }
        if (empty($nombre)) {
            throw new Exception("Fila {$numeroFila}: NOMBRE es obligatorio.");
        }

        // Tipo producción: '01' = Bien/Producto, '02' = Servicio
        $tipoProduccion = match(true) {
            in_array($tipoRaw, ['servicio', 'service', '02']) => '02',
            default => '01',
        };

        // Inventariable
        $inventariable = in_array($inventariableRaw, ['si', 'sí', 'yes', 's', '1', 'true']);

        // Opciones aplica a
        $aplicaCompras = in_array($aplicaARaw, ['compras', 'compra', 'ambos', 'ambas', 'both', 'todos', 'all']);
        $aplicaVentas  = in_array($aplicaARaw, ['ventas', 'venta', 'ambos', 'ambas', 'both', 'todos', 'all']);
        if (!$aplicaCompras && !$aplicaVentas) {
            // Si no coincide ninguno conocido, se activan ambos por defecto
            $aplicaCompras = true;
            $aplicaVentas  = true;
        }
        $opciones = json_encode(['compra' => $aplicaCompras, 'venta' => $aplicaVentas]);

        // Buscar producto existente por código principal
        $stCheck = $this->db->prepare(
            "SELECT id FROM productos WHERE id_empresa = ? AND codigo = ? AND eliminado = false"
        );
        $stCheck->execute([$idEmpresa, $codigoPrincipal]);
        $idExistenteProducto = $stCheck->fetchColumn();

        // Validar unicidad del código de barras en OTRO producto distinto
        if (!empty($codigoBarras)) {
            $stBar = $this->db->prepare(
                "SELECT id FROM productos WHERE id_empresa = ? AND codigo_barras = ? AND eliminado = false AND codigo != ?"
            );
            $stBar->execute([$idEmpresa, $codigoBarras, $codigoPrincipal]);
            if ($stBar->fetchColumn()) {
                throw new Exception("Fila {$numeroFila}: El código de barras '{$codigoBarras}' ya está registrado en otro producto.");
            }
        }

        // Resolver tarifa IVA por su código único
        $idTarifaIva = $this->resolverTarifaIva($codigoIvaRaw, $numeroFila);

        // Resolver o crear categoría y marca
        $idCategoria = !empty($categoriaNombre)
            ? $this->resolverOCrearCatalogo('categorias', $categoriaNombre, $idEmpresa, $idUsuario)
            : null;
        $idMarca = !empty($marcaNombre)
            ? $this->resolverOCrearCatalogo('marcas', $marcaNombre, $idEmpresa, $idUsuario)
            : null;

        // Medida: solo aplica para Productos (tipo_produccion = '01'), no para Servicios
        $idMedida     = null;
        $idTipoMedida = null;
        if ($tipoProduccion === '01' && !empty($codigoMedidaRaw)) {
            [$idMedida, $idTipoMedida] = $this->resolverUnidadMedida($codigoMedidaRaw, $idEmpresa, $numeroFila);
        }

        if ($idExistenteProducto) {
            $sql = "UPDATE productos SET
                        updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP,
                        nombre = :nombre, codigo_auxiliar = :codigo_auxiliar, codigo_barras = :codigo_barras,
                        precio_base = :precio_base, tipo_produccion = :tipo_produccion, tarifa_iva = :tarifa_iva,
                        inventariable = :inventariable, opciones = :opciones,
                        id_categoria = :id_categoria, id_marca = :id_marca,
                        id_medida = :id_medida, id_tipo_medida = :id_tipo_medida
                    WHERE id = :id AND id_empresa = :id_empresa";
            $st = $this->db->prepare($sql);
            $st->execute([
                ':updated_by'      => $idUsuario,
                ':nombre'          => $nombre,
                ':codigo_auxiliar' => $codigoAuxiliar,
                ':codigo_barras'   => $codigoBarras,
                ':precio_base'     => $precio,
                ':tipo_produccion' => $tipoProduccion,
                ':tarifa_iva'      => $idTarifaIva,
                ':inventariable'   => $inventariable ? 'true' : 'false',
                ':opciones'        => $opciones,
                ':id_categoria'    => $idCategoria,
                ':id_marca'        => $idMarca,
                ':id_medida'       => $idMedida,
                ':id_tipo_medida'  => $idTipoMedida,
                ':id'              => (int)$idExistenteProducto,
                ':id_empresa'      => $idEmpresa,
            ]);
            return (int)$idExistenteProducto;
        }

        $sql = "INSERT INTO productos (
                    id_empresa, id_usuario, created_by, updated_by,
                    codigo, nombre, codigo_auxiliar, codigo_barras,
                    precio_base, tipo_produccion, tarifa_iva,
                    inventariable, opciones, id_categoria, id_marca,
                    id_medida, id_tipo_medida,
                    status, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :updated_by,
                    :codigo, :nombre, :codigo_auxiliar, :codigo_barras,
                    :precio_base, :tipo_produccion, :tarifa_iva,
                    :inventariable, :opciones, :id_categoria, :id_marca,
                    :id_medida, :id_tipo_medida,
                    1, false
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $idEmpresa,
            ':id_usuario'      => $idUsuario,
            ':created_by'      => $idUsuario,
            ':updated_by'      => $idUsuario,
            ':codigo'          => $codigoPrincipal,
            ':nombre'          => $nombre,
            ':codigo_auxiliar' => $codigoAuxiliar,
            ':codigo_barras'   => $codigoBarras,
            ':precio_base'     => $precio,
            ':tipo_produccion' => $tipoProduccion,
            ':tarifa_iva'      => $idTarifaIva,
            ':inventariable'   => $inventariable ? 'true' : 'false',
            ':opciones'        => $opciones,
            ':id_categoria'    => $idCategoria,
            ':id_marca'        => $idMarca,
            ':id_medida'       => $idMedida,
            ':id_tipo_medida'  => $idTipoMedida,
        ]);

        $id = $st->fetchColumn();
        if (!$id) {
            throw new Exception("Fila {$numeroFila}: No se pudo insertar el producto '{$nombre}'.");
        }
        return (int)$id;
    }

    private function resolverTarifaIva(string $codigoIvaRaw, int $numeroFila): int
    {
        if (empty($codigoIvaRaw)) {
            throw new Exception("Fila {$numeroFila}: CODIGO_IVA es obligatorio. Consulte los códigos disponibles en la hoja 'Tarifas_IVA' de la plantilla.");
        }

        // Buscar por el campo 'codigo' que es el valor único de la tarifa
        $st = $this->db->prepare(
            "SELECT id FROM tarifa_iva WHERE TRIM(codigo) = TRIM(?) AND status = 1 LIMIT 1"
        );
        $st->execute([$codigoIvaRaw]);
        $id = $st->fetchColumn();

        if (!$id) {
            throw new Exception("Fila {$numeroFila}: El código de IVA '{$codigoIvaRaw}' no existe o está inactivo. Consulte los códigos válidos en la hoja 'Tarifas_IVA' de la plantilla.");
        }

        return (int)$id;
    }

    private function insertarRetencionSri(array $fila, int $numeroFila, int $idUsuario): int
    {
        $codigo = trim((string)($fila[0] ?? ''));
        $concepto = trim((string)($fila[1] ?? ''));
        $porcentaje = floatval($fila[2] ?? 0);
        $impuesto = trim((string)($fila[3] ?? ''));
        $codigoAts = trim((string)($fila[4] ?? ''));
        $desde = trim((string)($fila[5] ?? ''));
        $hasta = trim((string)($fila[6] ?? ''));

        if (empty($codigo) || empty($concepto)) {
            throw new Exception("Fila {$numeroFila}: Código y Concepto son obligatorios.");
        }

        $sql = "INSERT INTO retenciones_sri (
                    codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, desde, hasta, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1) RETURNING id";
        $st = $this->db->prepare($sql);
        
        $desdeVal = !empty($desde) ? $desde : null;
        $hastaVal = !empty($hasta) ? $hasta : null;
        
        $st->execute([
            $codigo, $concepto, $porcentaje, $impuesto, $codigoAts, $desdeVal, $hastaVal
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Busca una unidad de medida por su código (dentro de la empresa).
     * Retorna [id_medida, id_tipo_medida].
     * NO crea la unidad si no existe; lanza excepción indicando que debe crearse primero en el módulo.
     */
    private function resolverUnidadMedida(string $codigoMedida, int $idEmpresa, int $numeroFila): array
    {
        $st = $this->db->prepare(
            "SELECT um.id, um.id_tipo
               FROM unidades_medida um
              WHERE um.id_empresa = ?
                AND UPPER(TRIM(um.codigo)) = UPPER(TRIM(?))
                AND um.eliminado = false
                AND um.status = true
              LIMIT 1"
        );
        $st->execute([$idEmpresa, $codigoMedida]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception(
                "Fila {$numeroFila}: La unidad de medida con código '{$codigoMedida}' no existe o está inactiva. " .
                "Debe crearla primero en el módulo Configuración → Unidades de Medida, " .
                "o consulte los códigos disponibles en la hoja 'Unidades_Medida' de la plantilla."
            );
        }

        return [(int)$row['id'], (int)$row['id_tipo']];
    }

    private function resolverOCrearCatalogo(string $tabla, string $nombre, int $idEmpresa, int $idUsuario): int
    {
        // Buscar existente (case-insensitive)
        $st = $this->db->prepare(
            "SELECT id FROM {$tabla} WHERE id_empresa = ? AND LOWER(nombre) = LOWER(?) AND eliminado = false LIMIT 1"
        );
        $st->execute([$idEmpresa, $nombre]);
        $id = $st->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        // Crear si no existe
        $ins = $this->db->prepare(
            "INSERT INTO {$tabla} (id_empresa, id_usuario, nombre, status, created_by, updated_by, eliminado)
             VALUES (?, ?, ?, 1, ?, ?, false) RETURNING id"
        );
        $ins->execute([$idEmpresa, $idUsuario, $nombre, $idUsuario, $idUsuario]);
        return (int)$ins->fetchColumn();
    }

    private function insertarVehiculo(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $placa = trim((string)($fila[0] ?? ''));
        $marca = trim((string)($fila[1] ?? ''));
        $modelo = trim((string)($fila[2] ?? ''));
        $anio = (int)($fila[3] ?? 0);

        if (empty($placa)) throw new Exception("Fila {$numeroFila}: La placa es obligatoria.");

        $stCheck = $this->db->prepare("SELECT id FROM vehiculos WHERE id_empresa = ? AND placa = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $placa]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: El vehículo con placa {$placa} ya existe.");

        $sql = "INSERT INTO vehiculos (id_empresa, id_usuario, placa, marca, modelo, anio, created_by, updated_by, eliminado, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, false, 1) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $idUsuario, $placa, $marca, $modelo, $anio, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarProveedor(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $tipoIdRaw       = $this->sanitizarTexto(trim((string)($fila[0]  ?? '')), 50);
        $identificacion  = $this->sanitizarTexto(trim((string)($fila[1]  ?? '')), 20);
        $razonSocial     = $this->sanitizarTexto(trim((string)($fila[2]  ?? '')), 255);
        $nombreComercial = $this->sanitizarTexto(trim((string)($fila[3]  ?? '')), 255);
        $direccion       = $this->sanitizarTexto(trim((string)($fila[4]  ?? '')), 255);
        $emailRaw        = $this->sanitizarTexto(trim((string)($fila[5]  ?? '')), 500);
        $telefono        = $this->sanitizarTexto(trim((string)($fila[6]  ?? '')), 30);
        $plazo           = abs((int)($fila[7]  ?? 0));
        $provinciaNombre    = trim((string)($fila[8]  ?? ''));
        $ciudadNombre       = trim((string)($fila[9]  ?? ''));
        $tipoEmpresaNombre  = $this->sanitizarTexto(trim((string)($fila[10] ?? '')), 100);
        $bancoNombre        = $this->sanitizarTexto(trim((string)($fila[11] ?? '')), 150);
        $tipoCuentaRaw      = $this->sanitizarTexto(trim((string)($fila[12] ?? '')), 30);
        $numeroCuenta       = $this->sanitizarTexto(trim((string)($fila[13] ?? '')), 50);
        $sustentoRaw        = $this->sanitizarTexto(trim((string)($fila[14] ?? '')), 20);

        if (empty($identificacion) || empty($razonSocial)) {
            throw new Exception("Fila {$numeroFila}: Identificación y Razón Social son obligatorios.");
        }

        // Tipo de identificación (04,05,06,08 — no aplica 07 para proveedores)
        $tipoId = $this->resolverTipoIdentificacion($tipoIdRaw, $numeroFila, ['04', '05', '06', '08']);

        // Validar identificación según tipo
        $this->validarIdentificacionProveedor($tipoId, $identificacion, $numeroFila);

        // Validar emails
        $email = $this->validarYNormalizarEmails($emailRaw, $numeroFila);

        // Buscar proveedor existente → UPDATE si existe, INSERT si no
        $stCheck = $this->db->prepare(
            "SELECT id FROM proveedores WHERE id_empresa = ? AND identificacion = ? AND eliminado = false LIMIT 1"
        );
        $stCheck->execute([$idEmpresa, $identificacion]);
        $idExistenteProveedor = $stCheck->fetchColumn();

        // Provincia y ciudad
        [$codProvincia, $codCiudad] = $this->resolverProvinciaYCiudad($provinciaNombre, $ciudadNombre, $numeroFila);

        // Tipo empresa (opcional)
        $idTipoEmpresa = !empty($tipoEmpresaNombre)
            ? $this->resolverTipoEmpresa($tipoEmpresaNombre, $numeroFila)
            : null;

        // Banco (opcional, pero si se llena debe existir)
        $idBanco = !empty($bancoNombre)
            ? $this->resolverBanco($bancoNombre, $numeroFila)
            : null;

        // Tipo de cuenta
        $tipoCuenta = null;
        if (!empty($tipoCuentaRaw)) {
            $tipoCuenta = match(strtolower($tipoCuentaRaw)) {
                'ahorros', 'ahorro'    => 1,
                'corriente'            => 2,
                'virtual'              => 3,
                'otro', 'otros'        => 4,
                default => throw new Exception(
                    "Fila {$numeroFila}: Tipo de cuenta '{$tipoCuentaRaw}' no válido. Use: Ahorros, Corriente, Virtual u Otro."
                ),
            };
        }

        // Sustento tributario (opcional)
        $idSustento = !empty($sustentoRaw)
            ? $this->resolverSustentoTributario($sustentoRaw, $numeroFila)
            : null;

        if ($idExistenteProveedor) {
            $sql = "UPDATE proveedores SET
                        updated_by = ?, updated_at = CURRENT_TIMESTAMP,
                        tipo_id_proveedor = ?, razon_social = ?, nombre_comercial = ?,
                        direccion = ?, email = ?, telefono = ?, plazo = ?,
                        provincia = ?, ciudad = ?, tipo_empresa = ?,
                        id_banco = ?, tipo_cta = ?, numero_cta = ?,
                        id_sustento_tributario = ?
                    WHERE id = ? AND id_empresa = ?";
            $st = $this->db->prepare($sql);
            $st->execute([
                $idUsuario,
                $tipoId, $razonSocial, $nombreComercial ?: null,
                $direccion ?: null, $email ?: null, $telefono ?: null, $plazo,
                $codProvincia, $codCiudad, $idTipoEmpresa,
                $idBanco, $tipoCuenta, $numeroCuenta ?: null,
                $idSustento,
                (int)$idExistenteProveedor, $idEmpresa,
            ]);
            return (int)$idExistenteProveedor;
        }

        $sql = "INSERT INTO proveedores (
                    id_empresa, id_usuario, created_by, updated_by,
                    tipo_id_proveedor, identificacion, razon_social, nombre_comercial,
                    direccion, email, telefono, plazo, unidad_tiempo,
                    provincia, ciudad, tipo_empresa,
                    id_banco, tipo_cta, numero_cta,
                    id_sustento_tributario,
                    relacionado, status, eliminado
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, 'DIAS',
                    ?, ?, ?,
                    ?, ?, ?,
                    ?,
                    false, true, false
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            $idEmpresa, $idUsuario, $idUsuario, $idUsuario,
            $tipoId, $identificacion, $razonSocial, $nombreComercial ?: null,
            $direccion ?: null, $email ?: null, $telefono ?: null, $plazo,
            $codProvincia, $codCiudad, $idTipoEmpresa,
            $idBanco, $tipoCuenta, $numeroCuenta ?: null,
            $idSustento,
        ]);

        $id = $st->fetchColumn();
        if (!$id) {
            throw new Exception("Fila {$numeroFila}: No se pudo insertar el proveedor '{$razonSocial}'.");
        }
        return (int)$id;
    }

    private function validarIdentificacionProveedor(?string $tipoId, string $identificacion, int $numeroFila): void
    {
        switch ($tipoId) {
            case '04':
                if (!preg_match('/^\d{13}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 04 (RUC) la identificación debe tener exactamente 13 dígitos numéricos. Valor: '{$identificacion}'.");
                }
                break;
            case '05':
                if (!preg_match('/^\d{10}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 05 (Cédula) la identificación debe tener exactamente 10 dígitos numéricos. Valor: '{$identificacion}'.");
                }
                break;
            case '06':
                if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 06 (Pasaporte) la identificación debe ser alfanumérica de hasta 20 caracteres. Valor: '{$identificacion}'.");
                }
                break;
            case '08':
                if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $identificacion)) {
                    throw new Exception("Fila {$numeroFila}: Para tipo 08 (Exterior) la identificación debe ser alfanumérica de hasta 20 caracteres. Valor: '{$identificacion}'.");
                }
                break;
        }
    }

    private function resolverTipoEmpresa(string $nombre, int $numeroFila): int
    {
        $st = $this->db->prepare(
            "SELECT id FROM tipo_empresa WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(?)) AND status = 1 LIMIT 1"
        );
        $st->execute([$nombre]);
        $id = $st->fetchColumn();
        if (!$id) {
            $st2 = $this->db->prepare(
                "SELECT id FROM tipo_empresa WHERE UPPER(nombre) LIKE UPPER(?) AND status = 1 ORDER BY id LIMIT 1"
            );
            $st2->execute(['%' . $nombre . '%']);
            $id = $st2->fetchColumn();
        }
        if (!$id) {
            throw new Exception("Fila {$numeroFila}: El tipo de empresa '{$nombre}' no existe. Consulte la hoja 'Tipos_Empresa' de la plantilla.");
        }
        return (int)$id;
    }

    private function resolverBanco(string $nombre, int $numeroFila): int
    {
        $st = $this->db->prepare(
            "SELECT id FROM bancos_ecuador WHERE UPPER(TRIM(nombre_banco)) = UPPER(TRIM(?)) AND status = 1 LIMIT 1"
        );
        $st->execute([$nombre]);
        $id = $st->fetchColumn();
        if (!$id) {
            $st2 = $this->db->prepare(
                "SELECT id FROM bancos_ecuador WHERE UPPER(nombre_banco) LIKE UPPER(?) AND status = 1 ORDER BY id LIMIT 1"
            );
            $st2->execute(['%' . $nombre . '%']);
            $id = $st2->fetchColumn();
        }
        if (!$id) {
            throw new Exception("Fila {$numeroFila}: El banco '{$nombre}' no existe. Consulte la hoja 'Bancos' de la plantilla.");
        }
        return (int)$id;
    }

    private function resolverSustentoTributario(string $codigoRaw, int $numeroFila): int
    {
        // Buscar por código exacto
        $st = $this->db->prepare(
            "SELECT id FROM sustento_tributario WHERE TRIM(codigo) = TRIM(?) AND status = 1 LIMIT 1"
        );
        $st->execute([$codigoRaw]);
        $id = $st->fetchColumn();
        if (!$id) {
            throw new Exception("Fila {$numeroFila}: El código de sustento tributario '{$codigoRaw}' no existe o está inactivo. Consulte la hoja 'Sustento_Tributario' de la plantilla.");
        }
        return (int)$id;
    }

    private function insertarEmpleado(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $tipoId = trim((string)($fila[0] ?? ''));
        $identificacion = trim((string)($fila[1] ?? ''));
        $nombres = trim((string)($fila[2] ?? ''));
        $email = trim((string)($fila[3] ?? ''));
        $telefono = trim((string)($fila[4] ?? ''));
        $direccion = trim((string)($fila[5] ?? ''));
        $cargo = trim((string)($fila[6] ?? ''));
        $sueldo = floatval($fila[7] ?? 0);

        if (empty($identificacion) || empty($nombres)) throw new Exception("Fila {$numeroFila}: Identificación y Nombres son obligatorios.");

        $stCheck = $this->db->prepare("SELECT id FROM empleados WHERE id_empresa = ? AND identificacion = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $identificacion]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: El empleado {$identificacion} ya existe.");

        $sql = "INSERT INTO empleados (id_empresa, tipo_id, identificacion, nombres_apellidos, email, telefono, direccion, cargo, sueldo_base, created_by, updated_by, eliminado, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, false, 'activo') RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $tipoId, $identificacion, $nombres, $email, $telefono, $direccion, $cargo, $sueldo, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarTipoMedida(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $codigo = trim((string)($fila[0] ?? ''));
        $nombre = trim((string)($fila[1] ?? ''));
        
        if (empty($codigo) || empty($nombre)) throw new Exception("Fila {$numeroFila}: Código y Nombre son obligatorios.");

        $stCheck = $this->db->prepare("SELECT id FROM tipo_medida WHERE id_empresa = ? AND codigo = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $codigo]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: El Tipo de Medida con código {$codigo} ya existe.");

        $sql = "INSERT INTO tipo_medida (id_empresa, id_usuario, codigo, nombre, status, created_by, updated_by, eliminado) VALUES (?, ?, ?, ?, 1, ?, ?, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $idUsuario, $codigo, $nombre, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarUnidadMedida(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $codigoTipo = trim((string)($fila[0] ?? ''));
        $codigo = trim((string)($fila[1] ?? ''));
        $nombre = trim((string)($fila[2] ?? ''));

        if (empty($codigoTipo) || empty($codigo) || empty($nombre)) throw new Exception("Fila {$numeroFila}: Código de Tipo, Código y Nombre son obligatorios.");

        $stTipo = $this->db->prepare("SELECT id FROM tipo_medida WHERE id_empresa = ? AND codigo = ? AND eliminado = false");
        $stTipo->execute([$idEmpresa, $codigoTipo]);
        $idTipo = $stTipo->fetchColumn();

        if (!$idTipo) throw new Exception("Fila {$numeroFila}: No se encontró el Tipo de Medida con código {$codigoTipo}.");

        $stCheck = $this->db->prepare("SELECT id FROM unidades_medida WHERE id_tipo = ? AND codigo = ? AND eliminado = false");
        $stCheck->execute([$idTipo, $codigo]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: La Unidad con código {$codigo} ya existe para este Tipo.");

        $sql = "INSERT INTO unidades_medida (id_tipo, codigo, nombre, status, created_by, updated_by, eliminado) VALUES (?, ?, ?, 1, ?, ?, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idTipo, $codigo, $nombre, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarPlanCuenta(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $codigo = trim((string)($fila[0] ?? ''));
        $nombre = trim((string)($fila[1] ?? ''));
        $tipo = trim((string)($fila[2] ?? ''));
        $nivel = (int)($fila[3] ?? 1);

        if (empty($codigo) || empty($nombre)) throw new Exception("Fila {$numeroFila}: Código y Nombre son obligatorios.");

        $stCheck = $this->db->prepare("SELECT id FROM plan_cuentas WHERE id_empresa = ? AND codigo = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $codigo]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: La cuenta con código {$codigo} ya existe.");

        $sql = "INSERT INTO plan_cuentas (id_empresa, id_usuario, codigo, nombre, tipo, nivel, status, created_by, updated_by, eliminado) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $idUsuario, $codigo, $nombre, $tipo, $nivel, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }
}
