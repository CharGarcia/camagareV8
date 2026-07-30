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
            // Una sola entidad para las dos tablas: la plantilla trae la hoja
            // "Tipos_Medida" y la hoja "Unidades" en el mismo archivo.
            'unidades_medida' => [
                'nombre' => 'Unidades y tipos de medida',
                'global' => false,
                'col_numericas' => [],
                'multihoja' => true,
                'hojas' => [
                    'Tipos_Medida' => ['CODIGO_TIPO', 'NOMBRE_TIPO'],
                    'Unidades'     => ['CODIGO_TIPO', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD', 'ABREVIATURA', 'FACTOR_BASE', 'ES_BASE (SI / NO)'],
                ],
                'columnas' => ['CODIGO_TIPO', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD', 'ABREVIATURA', 'FACTOR_BASE', 'ES_BASE (SI / NO)'],
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

        // Entidades cuya plantilla trae varias hojas de datos relacionadas entre sí
        if (!empty($entidad['multihoja'])) {
            return $this->procesarMultihoja($spreadsheet, $entidadId, (int) $idEmpresa, $idUsuario);
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
        // El límite real de la columna es 50; se valida a 20 más abajo según el
        // tipo de identificación (validarIdentificacionCliente), pero se extrae
        // completa aquí para que ese mensaje muestre el valor real, no uno recortado.
        $identificacion = $this->sanitizarTexto(trim((string)($fila[1] ?? '')), 50);
        $nombre         = $this->sanitizarTexto(trim((string)($fila[2] ?? '')), 255);
        $direccion      = $this->sanitizarTexto(trim((string)($fila[3] ?? '')), 255);
        $emailRaw       = $this->sanitizarTexto(trim((string)($fila[4] ?? '')), 500);
        $telefono       = $this->campoTexto($fila, 5, 'TELEFONO', 20, $numeroFila);
        $plazo          = abs((int)($fila[6] ?? 0));
        $provinciaNombre = trim((string)($fila[7] ?? ''));
        $ciudadNombre    = trim((string)($fila[8] ?? ''));

        // Validar y normalizar emails
        $email = $this->validarYNormalizarEmails($emailRaw, $numeroFila);
        $email = $this->validarLongitud($email, 200, 'EMAIL', $numeroFila);

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
        // Solo validar entidades cuya plantilla incluye la hoja _Config
        if (!in_array($entidadId, ['productos', 'unidades_medida'], true)) {
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

    /**
     * Extrae y limpia un campo de texto de la fila y valida que no exceda el
     * largo real de la columna en la base de datos. En vez de truncar en
     * silencio (lo que después falla al guardar con el error crudo de
     * PostgreSQL "value too long for type character varying"), se avisa aquí
     * mismo qué campo está mal, cuántos caracteres trae y cuántos admite.
     */
    private function campoTexto(array $fila, int $indice, string $campo, int $maxLen, int $numeroFila): string
    {
        return $this->validarLongitud(
            trim((string)($fila[$indice] ?? '')),
            $maxLen,
            $campo,
            $numeroFila
        );
    }

    /**
     * Limpia caracteres de control/peligrosos y valida el largo máximo de un
     * valor ya extraído (útil cuando el valor se construye después de leer la
     * celda, como el email normalizado). Lanza un error legible por el
     * usuario en vez de truncar o dejar que falle la consulta SQL.
     */
    private function validarLongitud(string $valor, int $maxLen, string $campo, int $numeroFila): string
    {
        $valor = preg_replace('/[\x00-\x1F\x7F]/', '', $valor);
        $valor = trim((string)preg_replace("/['\";\\\\<>]/", '', $valor));

        $largo = mb_strlen($valor);
        if ($largo > $maxLen) {
            $muestra = $largo > 60 ? mb_substr($valor, 0, 60) . '…' : $valor;
            throw new Exception(
                "Fila {$numeroFila}: El campo {$campo} no se puede cargar porque tiene {$largo} caracteres " .
                "y el máximo permitido es {$maxLen}. Valor recibido: \"{$muestra}\". " .
                "Corrija el dato en el Excel para que {$campo} tenga máximo {$maxLen} caracteres."
            );
        }

        return $valor;
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
            in_array($tipoRaw, ['servicio', 'servicios', 'service', 'serv', '02', '2']) => '02',
            default => '01',
        };

        // Inventariable. Un servicio ('02') nunca maneja inventario,
        // igual que en el módulo de productos (ProductoService::crear).
        $inventariable = $tipoProduccion === '02'
            ? false
            : in_array($inventariableRaw, ['si', 'sí', 'yes', 's', '1', 'true']);

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
        // Nota: no usar empty() porque el código de la tarifa 0% es "0",
        // y empty("0") devuelve true, lo que provocaría rechazar una tarifa válida.
        if ($codigoIvaRaw === '') {
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
        $codigo = $this->campoTexto($fila, 0, 'CODIGO_RETENCION', 5, $numeroFila);
        $concepto = $this->campoTexto($fila, 1, 'CONCEPTO', 250, $numeroFila);
        $porcentaje = floatval($fila[2] ?? 0);
        $impuesto = $this->campoTexto($fila, 3, 'IMPUESTO', 50, $numeroFila);
        $codigoAts = $this->campoTexto($fila, 4, 'CODIGO_ATS', 5, $numeroFila);
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
        $placa = $this->campoTexto($fila, 0, 'PLACA', 20, $numeroFila);
        $marca = $this->campoTexto($fila, 1, 'MARCA', 100, $numeroFila);
        $modelo = $this->campoTexto($fila, 2, 'MODELO', 100, $numeroFila);
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
        // El límite real de la columna es 25; se valida a 20 más abajo según el
        // tipo de identificación (validarIdentificacionProveedor), pero se extrae
        // completa aquí para que ese mensaje muestre el valor real, no uno recortado.
        $identificacion  = $this->sanitizarTexto(trim((string)($fila[1]  ?? '')), 25);
        $razonSocial     = $this->campoTexto($fila, 2, 'RAZON_SOCIAL', 200, $numeroFila);
        $nombreComercial = $this->campoTexto($fila, 3, 'NOMBRE_COMERCIAL', 200, $numeroFila);
        $direccion       = $this->campoTexto($fila, 4, 'DIRECCION', 150, $numeroFila);
        $emailRaw        = $this->sanitizarTexto(trim((string)($fila[5]  ?? '')), 500);
        $telefono        = $this->campoTexto($fila, 6, 'TELEFONO', 50, $numeroFila);
        $plazo           = abs((int)($fila[7]  ?? 0));
        $provinciaNombre    = trim((string)($fila[8]  ?? ''));
        $ciudadNombre       = trim((string)($fila[9]  ?? ''));
        $tipoEmpresaNombre  = $this->sanitizarTexto(trim((string)($fila[10] ?? '')), 100);
        $bancoNombre        = $this->sanitizarTexto(trim((string)($fila[11] ?? '')), 150);
        $tipoCuentaRaw      = $this->sanitizarTexto(trim((string)($fila[12] ?? '')), 30);
        $numeroCuenta       = $this->campoTexto($fila, 13, 'NUMERO_CUENTA', 50, $numeroFila);
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
        $email = $this->validarLongitud($email, 100, 'EMAIL', $numeroFila);

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
        $tipoId = $this->campoTexto($fila, 0, 'TIPO_IDENTIFICACION', 20, $numeroFila);
        $identificacion = $this->campoTexto($fila, 1, 'IDENTIFICACION', 25, $numeroFila);
        $nombres = $this->campoTexto($fila, 2, 'NOMBRES_APELLIDOS', 255, $numeroFila);
        $email = $this->campoTexto($fila, 3, 'EMAIL', 100, $numeroFila);
        $telefono = $this->campoTexto($fila, 4, 'TELEFONO', 50, $numeroFila);
        $direccion = trim((string)($fila[5] ?? ''));
        $cargo = $this->campoTexto($fila, 6, 'CARGO', 100, $numeroFila);
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

    // ─────────────────────────────────────────────────────────────────
    // Unidades y tipos de medida (plantilla de dos hojas)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Despacha las entidades cuya plantilla trae varias hojas relacionadas.
     */
    private function procesarMultihoja(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        string $entidadId,
        int $idEmpresa,
        int $idUsuario
    ): int {
        if ($entidadId === 'unidades_medida') {
            return $this->procesarUnidadesYTipos($spreadsheet, $idEmpresa, $idUsuario);
        }

        throw new Exception("Lógica de importación multihoja no definida para {$entidadId}.");
    }

    /**
     * Importa la hoja "Tipos_Medida" y luego la hoja "Unidades" del mismo archivo,
     * en ese orden, para que una unidad pueda referirse a un tipo creado en la
     * misma carga. Ambas hojas son opcionales: se puede subir solo una.
     *
     * Los registros existentes se actualizan (upsert por código) en lugar de
     * abortar la importación. Todo ocurre dentro de una sola transacción.
     */
    private function procesarUnidadesYTipos(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        int $idEmpresa,
        int $idUsuario
    ): int {
        $filasTipos    = $this->leerHoja($spreadsheet, 'Tipos_Medida');
        $filasUnidades = $this->leerHoja($spreadsheet, 'Unidades');

        if (empty($filasTipos) && empty($filasUnidades)) {
            throw new Exception(
                "El archivo no tiene datos. Llene la hoja 'Tipos_Medida', la hoja 'Unidades' o ambas. " .
                "La hoja 'Catalogo_Sugerido' trae un catálogo listo para copiar y pegar."
            );
        }

        $this->db->beginTransaction();
        $procesados = 0;

        try {
            foreach ($filasTipos as $numeroFila => $fila) {
                $procesados += $this->guardarTipoMedida($fila, $numeroFila, $idEmpresa, $idUsuario);
            }

            foreach ($filasUnidades as $numeroFila => $fila) {
                $procesados += $this->guardarUnidadMedida($fila, $numeroFila, $idEmpresa, $idUsuario);
            }

            $this->db->commit();
            return $procesados;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Devuelve las filas con datos de una hoja, indexadas por su número real de
     * fila en Excel (la 1 es el encabezado, los datos empiezan en la 2), para que
     * los mensajes de error apunten a la fila que el usuario ve en pantalla.
     * Si la hoja no existe devuelve un arreglo vacío.
     *
     * @return array<int, array<int, mixed>>
     */
    private function leerHoja(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $nombreHoja): array
    {
        $hoja = $spreadsheet->getSheetByName($nombreHoja);
        if ($hoja === null) {
            return [];
        }

        $filas = $hoja->toArray();
        $resultado = [];

        for ($i = 1; $i < count($filas); $i++) {
            $fila = $filas[$i];
            $tieneDatos = false;
            foreach ($fila as $celda) {
                if (trim((string) $celda) !== '') {
                    $tieneDatos = true;
                    break;
                }
            }
            if ($tieneDatos) {
                $resultado[$i + 1] = $fila;
            }
        }

        return $resultado;
    }

    /**
     * Crea o actualiza un tipo de medida a partir de una fila de la hoja "Tipos_Medida".
     * Devuelve 1 si se guardó.
     */
    private function guardarTipoMedida(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $codigo = mb_strtoupper($this->sanitizarTexto(trim((string) ($fila[0] ?? '')), 50), 'UTF-8');
        $nombre = mb_strtoupper($this->sanitizarTexto(trim((string) ($fila[1] ?? '')), 100), 'UTF-8');

        if ($codigo === '' || $nombre === '') {
            throw new Exception(
                "Hoja 'Tipos_Medida', fila {$numeroFila}: CODIGO_TIPO y NOMBRE_TIPO son obligatorios."
            );
        }

        // Otro tipo con el mismo nombre y distinto código haría ambiguo el catálogo
        $stNombre = $this->db->prepare(
            "SELECT codigo FROM tipo_medida
              WHERE id_empresa = ? AND UPPER(TRIM(nombre)) = ? AND UPPER(TRIM(codigo)) <> ? AND eliminado = false
              LIMIT 1"
        );
        $stNombre->execute([$idEmpresa, $nombre, $codigo]);
        $codigoOtro = $stNombre->fetchColumn();
        if ($codigoOtro !== false) {
            throw new Exception(
                "Hoja 'Tipos_Medida', fila {$numeroFila}: ya existe un tipo de medida llamado '{$nombre}' " .
                "con el código '{$codigoOtro}'. Use ese mismo código o cambie el nombre."
            );
        }

        $stCheck = $this->db->prepare(
            "SELECT id FROM tipo_medida
              WHERE id_empresa = ? AND UPPER(TRIM(codigo)) = ? AND eliminado = false
              LIMIT 1"
        );
        $stCheck->execute([$idEmpresa, $codigo]);
        $idExistente = $stCheck->fetchColumn();

        if ($idExistente !== false) {
            $st = $this->db->prepare(
                "UPDATE tipo_medida
                    SET nombre = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE id = ? AND id_empresa = ?"
            );
            $st->execute([$nombre, $idUsuario, (int) $idExistente, $idEmpresa]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'importar_unidades_medida_excel',
                'tipo_medida',
                (int) $idExistente,
                null,
                ['origen' => 'excel', 'hoja' => 'Tipos_Medida', 'fila' => $numeroFila, 'accion' => 'actualizar', 'codigo' => $codigo, 'nombre' => $nombre]
            );

            return 1;
        }

        $st = $this->db->prepare(
            "INSERT INTO tipo_medida (
                id_empresa, id_usuario, codigo, nombre, status,
                created_by, updated_by, eliminado
             ) VALUES (?, ?, ?, ?, true, ?, ?, false) RETURNING id"
        );
        $st->execute([$idEmpresa, $idUsuario, $codigo, $nombre, $idUsuario, $idUsuario]);
        $id = (int) $st->fetchColumn();

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'importar_unidades_medida_excel',
            'tipo_medida',
            $id,
            null,
            ['origen' => 'excel', 'hoja' => 'Tipos_Medida', 'fila' => $numeroFila, 'accion' => 'crear', 'codigo' => $codigo, 'nombre' => $nombre]
        );

        return 1;
    }

    /**
     * Crea o actualiza una unidad de medida a partir de una fila de la hoja "Unidades".
     * Devuelve 1 si se guardó.
     */
    private function guardarUnidadMedida(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $codigoTipo  = mb_strtoupper($this->sanitizarTexto(trim((string) ($fila[0] ?? '')), 50), 'UTF-8');
        $codigo      = mb_strtoupper($this->sanitizarTexto(trim((string) ($fila[1] ?? '')), 50), 'UTF-8');
        $nombre      = mb_strtoupper($this->sanitizarTexto(trim((string) ($fila[2] ?? '')), 100), 'UTF-8');
        $abreviatura = $this->sanitizarTexto(trim((string) ($fila[3] ?? '')), 20);
        $factorRaw   = trim((string) ($fila[4] ?? ''));
        $esBase      = $this->interpretarSiNo($fila[5] ?? '');

        if ($codigoTipo === '' || $codigo === '' || $nombre === '') {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: CODIGO_TIPO, CODIGO_UNIDAD y NOMBRE_UNIDAD son obligatorios."
            );
        }

        if ($abreviatura === '') {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: ABREVIATURA es obligatoria (por ejemplo 'kg', 'u', 'm')."
            );
        }

        // Tipo al que pertenece la unidad
        $stTipo = $this->db->prepare(
            "SELECT id FROM tipo_medida
              WHERE id_empresa = ? AND UPPER(TRIM(codigo)) = ? AND eliminado = false
              LIMIT 1"
        );
        $stTipo->execute([$idEmpresa, $codigoTipo]);
        $idTipo = $stTipo->fetchColumn();

        if ($idTipo === false) {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: no existe un tipo de medida con código '{$codigoTipo}'. " .
                "Créelo en la hoja 'Tipos_Medida' de este mismo archivo o en Configuración → Unidades de Medida."
            );
        }
        $idTipo = (int) $idTipo;

        $factor = $this->interpretarFactor($factorRaw, $numeroFila);
        if ($esBase) {
            // La unidad base es la referencia del tipo: siempre vale 1
            $factor = 1.0;
        } elseif ($factor <= 0) {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: FACTOR_BASE debe ser mayor que cero. " .
                "Indique cuántas unidades base equivale 1 de esta unidad (por ejemplo, 1 lb = 0.453592 kg)."
            );
        }

        // El código de unidad debe ser único en toda la empresa: al importar
        // productos la unidad se resuelve solo por código, sin mirar el tipo.
        $stCheck = $this->db->prepare(
            "SELECT id, id_tipo FROM unidades_medida
              WHERE id_empresa = ? AND UPPER(TRIM(codigo)) = ? AND eliminado = false
              LIMIT 1"
        );
        $stCheck->execute([$idEmpresa, $codigo]);
        $existente = $stCheck->fetch(\PDO::FETCH_ASSOC);

        if ($existente && (int) $existente['id_tipo'] !== $idTipo) {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: el código '{$codigo}' ya está usado por otra unidad " .
                "de un tipo de medida distinto. Los códigos de unidad no pueden repetirse dentro de la empresa."
            );
        }

        // Solo puede haber una unidad base por tipo (índice único en la base de datos)
        if ($esBase) {
            $sqlBase = "SELECT codigo FROM unidades_medida
                         WHERE id_empresa = ? AND id_tipo = ? AND es_base = true AND eliminado = false";
            $params  = [$idEmpresa, $idTipo];
            if ($existente) {
                $sqlBase .= " AND id <> ?";
                $params[] = (int) $existente['id'];
            }
            $stBase = $this->db->prepare($sqlBase . " LIMIT 1");
            $stBase->execute($params);
            $codigoBase = $stBase->fetchColumn();

            if ($codigoBase !== false) {
                throw new Exception(
                    "Hoja 'Unidades', fila {$numeroFila}: el tipo '{$codigoTipo}' ya tiene a '{$codigoBase}' " .
                    "como unidad base. Solo puede haber una: ponga NO en ES_BASE e indique el factor respecto a ella."
                );
            }
        }

        // Otra unidad con el mismo nombre dentro del tipo (misma regla que el módulo)
        $sqlNombre = "SELECT codigo FROM unidades_medida
                       WHERE id_empresa = ? AND id_tipo = ? AND UPPER(TRIM(nombre)) = ? AND eliminado = false";
        $paramsNombre = [$idEmpresa, $idTipo, $nombre];
        if ($existente) {
            $sqlNombre .= " AND id <> ?";
            $paramsNombre[] = (int) $existente['id'];
        }
        $stNombre = $this->db->prepare($sqlNombre . " LIMIT 1");
        $stNombre->execute($paramsNombre);
        $codigoMismoNombre = $stNombre->fetchColumn();

        if ($codigoMismoNombre !== false) {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: ya existe la unidad '{$nombre}' con el código " .
                "'{$codigoMismoNombre}' en el tipo '{$codigoTipo}'."
            );
        }

        $datos = [
            'origen' => 'excel', 'hoja' => 'Unidades', 'fila' => $numeroFila,
            'codigo' => $codigo, 'nombre' => $nombre, 'abreviatura' => $abreviatura,
            'factor_base' => $factor, 'es_base' => $esBase,
        ];

        if ($existente) {
            $st = $this->db->prepare(
                "UPDATE unidades_medida
                    SET nombre = ?, abreviatura = ?, factor_base = ?, es_base = ?,
                        updated_by = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE id = ? AND id_empresa = ?"
            );
            $st->execute([
                $nombre,
                $abreviatura,
                number_format($factor, 6, '.', ''),
                \App\Helpers\Booleano::sql($esBase),
                $idUsuario,
                (int) $existente['id'],
                $idEmpresa,
            ]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'importar_unidades_medida_excel',
                'unidades_medida',
                (int) $existente['id'],
                null,
                $datos + ['accion' => 'actualizar']
            );

            return 1;
        }

        $st = $this->db->prepare(
            "INSERT INTO unidades_medida (
                id_empresa, id_tipo, codigo, nombre, abreviatura, factor_base,
                es_base, status, created_by, updated_by, eliminado
             ) VALUES (?, ?, ?, ?, ?, ?, ?, true, ?, ?, false) RETURNING id"
        );
        $st->execute([
            $idEmpresa,
            $idTipo,
            $codigo,
            $nombre,
            $abreviatura,
            number_format($factor, 6, '.', ''),
            \App\Helpers\Booleano::sql($esBase),
            $idUsuario,
            $idUsuario,
        ]);
        $id = (int) $st->fetchColumn();

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'importar_unidades_medida_excel',
            'unidades_medida',
            $id,
            null,
            $datos + ['accion' => 'crear']
        );

        return 1;
    }

    /**
     * Interpreta una celda SI / NO. Vacío se toma como NO.
     */
    private function interpretarSiNo($valor): bool
    {
        $texto = mb_strtolower(trim((string) $valor), 'UTF-8');

        return in_array($texto, ['si', 'sí', 's', 'x', '1', 'true', 'yes', 'v', 'verdadero'], true);
    }

    /**
     * Convierte el FACTOR_BASE de la plantilla a número. Acepta coma decimal
     * (0,453592) porque es lo que escribe Excel en configuración regional española.
     * Vacío equivale a 1.
     */
    private function interpretarFactor(string $valor, int $numeroFila): float
    {
        if ($valor === '') {
            return 1.0;
        }

        // "0,453592" → "0.453592" (solo cuando la coma es el separador decimal)
        if (strpos($valor, ',') !== false && strpos($valor, '.') === false) {
            $valor = str_replace(',', '.', $valor);
        } else {
            $valor = str_replace(',', '', $valor);
        }

        if (!is_numeric($valor)) {
            throw new Exception(
                "Hoja 'Unidades', fila {$numeroFila}: FACTOR_BASE ('{$valor}') no es un número válido."
            );
        }

        return (float) $valor;
    }

    private function insertarPlanCuenta(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $codigo = $this->campoTexto($fila, 0, 'CODIGO_CUENTA', 50, $numeroFila);
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
