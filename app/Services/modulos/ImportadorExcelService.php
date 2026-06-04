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
                'columnas' => ['TIPO_IDENTIFICACION', 'IDENTIFICACION', 'NOMBRE_RAZON_SOCIAL', 'DIRECCION', 'EMAIL', 'TELEFONO', 'DIAS_PLAZO']
            ],
            'productos' => [
                'nombre' => 'Productos',
                'global' => false,
                'columnas' => ['CODIGO_PRINCIPAL', 'NOMBRE', 'TIPO', 'PRECIO_UNITARIO', 'TIENE_IVA', 'INVENTARIABLE']
            ],
            'categorias' => [
                'nombre' => 'Categorías',
                'global' => false,
                'columnas' => ['NOMBRE']
            ],
            'marcas' => [
                'nombre' => 'Marcas',
                'global' => false,
                'columnas' => ['NOMBRE']
            ],
            'bodegas' => [
                'nombre' => 'Bodegas',
                'global' => false,
                'columnas' => ['NOMBRE']
            ],
            'vendedores' => [
                'nombre' => 'Vendedores',
                'global' => false,
                'columnas' => ['IDENTIFICACION', 'NOMBRE', 'CORREO', 'TELEFONO']
            ],
            'vehiculos' => [
                'nombre' => 'Vehículos',
                'global' => false,
                'columnas' => ['PLACA', 'MARCA', 'MODELO', 'ANIO']
            ],
            'proveedores' => [
                'nombre' => 'Proveedores',
                'global' => false,
                'columnas' => ['TIPO_IDENTIFICACION', 'IDENTIFICACION', 'RAZON_SOCIAL', 'DIRECCION', 'EMAIL', 'TELEFONO', 'DIAS_PLAZO']
            ],
            'empleados' => [
                'nombre' => 'Empleados',
                'global' => false,
                'columnas' => ['TIPO_IDENTIFICACION', 'IDENTIFICACION', 'NOMBRES_APELLIDOS', 'EMAIL', 'TELEFONO', 'DIRECCION', 'CARGO', 'SUELDO_BASE']
            ],
            'tipo_medida' => [
                'nombre' => 'Tipos de Medida',
                'global' => false,
                'columnas' => ['CODIGO', 'NOMBRE']
            ],
            'unidades_medida' => [
                'nombre' => 'Unidades de Medida',
                'global' => false,
                'columnas' => ['CODIGO_TIPO_MEDIDA', 'CODIGO_UNIDAD', 'NOMBRE_UNIDAD']
            ],
            'plan_cuentas' => [
                'nombre' => 'Plan de Cuentas',
                'global' => false,
                'columnas' => ['CODIGO_CUENTA', 'NOMBRE_CUENTA', 'TIPO_CUENTA', 'NIVEL']
            ],
            
            // Globales (Sin Empresa)
            'retenciones_sri' => [
                'nombre' => 'Retenciones SRI',
                'global' => true,
                'columnas' => ['CODIGO_RETENCION', 'CONCEPTO', 'PORCENTAJE', 'IMPUESTO', 'CODIGO_ATS', 'DESDE', 'HASTA']
            ],
            'plan_cuentas_modelo' => [
                'nombre' => 'Plan de Cuentas Modelo',
                'global' => true,
                'columnas' => ['CODIGO_CUENTA', 'NOMBRE_CUENTA', 'TIPO_CUENTA', 'NIVEL']
            ]
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
            'categorias' => 'categorias',
            'marcas' => 'marcas',
            'bodegas' => 'bodegas',
            'vendedores' => 'vendedores',
            'vehiculos' => 'vehiculos',
            'proveedores' => 'proveedores',
            'empleados' => 'empleados',
            'tipo_medida' => 'tipo_medida',
            'unidades_medida' => 'unidades_medida',
            'plan_cuentas' => 'plan_cuentas',
            'retenciones_sri' => 'retenciones_sri',
            'plan_cuentas_modelo' => 'plan_cuentas_modelo',
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
            case 'categorias':
                return $this->insertarCategoria($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'marcas':
                return $this->insertarMarca($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'bodegas':
                return $this->insertarBodega($fila, $numeroFila, $idEmpresa, $idUsuario);
            case 'vendedores':
                return $this->insertarVendedor($fila, $numeroFila, $idEmpresa, $idUsuario);
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
            case 'plan_cuentas_modelo':
                return $this->insertarPlanCuentasModelo($fila, $numeroFila, $idUsuario);
            default:
                throw new Exception("Lógica de inserción no definida para {$entidadId}");
        }
    }

    private function insertarCliente(array $fila, int $numeroFila, int $idEmpresa, string $tipoAmbiente, int $idUsuario): int
    {
        $tipoId = trim((string)($fila[0] ?? ''));
        $identificacion = trim((string)($fila[1] ?? ''));
        $nombre = trim((string)($fila[2] ?? ''));
        $direccion = trim((string)($fila[3] ?? ''));
        $email = trim((string)($fila[4] ?? ''));
        $telefono = trim((string)($fila[5] ?? ''));

        if (empty($identificacion) || empty($nombre)) {
            throw new Exception("Fila {$numeroFila}: Identificación y Nombre son obligatorios.");
        }

        // Verificar si existe
        $stCheck = $this->db->prepare("SELECT id FROM clientes WHERE id_empresa = ? AND identificacion = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $identificacion]);
        if ($stCheck->fetchColumn()) {
            throw new Exception("Fila {$numeroFila}: El cliente con identificación {$identificacion} ya existe en esta empresa.");
        }

        $sql = "INSERT INTO clientes (
                    id_empresa, tipo_id, identificacion, nombre, direccion, email, telefono,
                    tipo_ambiente, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            $idEmpresa, $tipoId, $identificacion, $nombre, $direccion, $email, $telefono,
            $tipoAmbiente, $idUsuario, $idUsuario
        ]);
        return (int) $st->fetchColumn();
    }

    private function insertarProducto(array $fila, int $numeroFila, int $idEmpresa, string $tipoAmbiente, int $idUsuario): int
    {
        $codigo = trim((string)($fila[0] ?? ''));
        $nombre = trim((string)($fila[1] ?? ''));
        $tipo = strtoupper(trim((string)($fila[2] ?? 'B')));
        $precio = floatval($fila[3] ?? 0);
        $tieneIva = strtoupper(trim((string)($fila[4] ?? 'N')));
        $inventariableStr = strtoupper(trim((string)($fila[5] ?? 'S')));
        $inventariable = ($inventariableStr === 'S' || $inventariableStr === 'SI' || $inventariableStr === '1');

        if (empty($codigo) || empty($nombre)) {
            throw new Exception("Fila {$numeroFila}: Código y Nombre son obligatorios.");
        }

        $stCheck = $this->db->prepare("SELECT id FROM productos WHERE id_empresa = ? AND codigo_principal = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $codigo]);
        if ($stCheck->fetchColumn()) {
            throw new Exception("Fila {$numeroFila}: El producto con código {$codigo} ya existe en esta empresa.");
        }

        $idTarifaIva = $tieneIva === 'S' ? 2 : 1; // 2 = 12%/15%, 1 = 0% (esto debería consultarse mejor en la tabla, pero se simplifica aquí)

        $sql = "INSERT INTO productos (
                    id_empresa, codigo_principal, nombre, tipo_producto, precio_unitario, id_tarifa_iva,
                    inventariable, tipo_ambiente, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            $idEmpresa, $codigo, $nombre, $tipo, $precio, $idTarifaIva,
            $inventariable ? 'true' : 'false', $tipoAmbiente, $idUsuario, $idUsuario
        ]);
        return (int) $st->fetchColumn();
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

    private function insertarPlanCuentasModelo(array $fila, int $numeroFila, int $idUsuario): int
    {
        $codigo = trim((string)($fila[0] ?? ''));
        $nombre = trim((string)($fila[1] ?? ''));
        $tipo = trim((string)($fila[2] ?? ''));
        $nivel = (int)($fila[3] ?? 1);
        $idPadre = !empty($fila[4]) ? (int)$fila[4] : null;

        if (empty($codigo) || empty($nombre)) {
            throw new Exception("Fila {$numeroFila}: Código y Nombre son obligatorios.");
        }

        $sql = "INSERT INTO plan_cuentas_modelo (
                    codigo, nombre, tipo, nivel, id_padre, activo
                ) VALUES (?, ?, ?, ?, ?, true) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            $codigo, $nombre, $tipo, $nivel, $idPadre
        ]);
        return (int) $st->fetchColumn();
    }

    private function insertarCategoria(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $nombre = trim((string)($fila[0] ?? ''));
        if (empty($nombre)) throw new Exception("Fila {$numeroFila}: Nombre es obligatorio.");

        $stCheck = $this->db->prepare("SELECT id FROM categorias WHERE id_empresa = ? AND nombre = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $nombre]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: La categoría '{$nombre}' ya existe.");

        $sql = "INSERT INTO categorias (id_empresa, nombre, status, created_by, updated_by, eliminado) VALUES (?, ?, 1, ?, ?, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $nombre, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarMarca(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $nombre = trim((string)($fila[0] ?? ''));
        if (empty($nombre)) throw new Exception("Fila {$numeroFila}: Nombre es obligatorio.");

        $stCheck = $this->db->prepare("SELECT id FROM marcas WHERE id_empresa = ? AND nombre = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $nombre]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: La marca '{$nombre}' ya existe.");

        $sql = "INSERT INTO marcas (id_empresa, nombre, status, created_by, updated_by, eliminado) VALUES (?, ?, 1, ?, ?, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $nombre, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarBodega(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $nombre = trim((string)($fila[0] ?? ''));
        if (empty($nombre)) throw new Exception("Fila {$numeroFila}: Nombre es obligatorio.");

        $stCheck = $this->db->prepare("SELECT id FROM bodegas WHERE id_empresa = ? AND nombre = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $nombre]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: La bodega '{$nombre}' ya existe.");

        $sql = "INSERT INTO bodegas (id_empresa, id_usuario, nombre, status, created_by, updated_by, eliminado) VALUES (?, ?, ?, true, ?, ?, false) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $idUsuario, $nombre, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
    }

    private function insertarVendedor(array $fila, int $numeroFila, int $idEmpresa, int $idUsuario): int
    {
        $identificacion = trim((string)($fila[0] ?? ''));
        $nombre = trim((string)($fila[1] ?? ''));
        $correo = trim((string)($fila[2] ?? ''));
        $telefono = trim((string)($fila[3] ?? ''));

        if (empty($identificacion) || empty($nombre)) throw new Exception("Fila {$numeroFila}: Identificación y Nombre son obligatorios.");

        // Validar si la tabla tiene id_empresa (a veces no lo tiene)
        $q = $this->db->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'vendedores' AND column_name = 'id_empresa'");
        $hasEmpresa = $q->fetchColumn();

        if ($hasEmpresa) {
            $stCheck = $this->db->prepare("SELECT id FROM vendedores WHERE id_empresa = ? AND identificacion = ? AND eliminado = false");
            $stCheck->execute([$idEmpresa, $identificacion]);
            if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: El vendedor con ident. {$identificacion} ya existe.");
            
            $sql = "INSERT INTO vendedores (id_empresa, identificacion, nombre, correo, telefono, created_by, updated_by, eliminado, status) VALUES (?, ?, ?, ?, ?, ?, ?, false, 1) RETURNING id";
            $st = $this->db->prepare($sql);
            $st->execute([$idEmpresa, $identificacion, $nombre, $correo, $telefono, $idUsuario, $idUsuario]);
        } else {
            $stCheck = $this->db->prepare("SELECT id FROM vendedores WHERE identificacion = ? AND eliminado = false");
            $stCheck->execute([$identificacion]);
            if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: El vendedor con ident. {$identificacion} ya existe.");
            
            $sql = "INSERT INTO vendedores (identificacion, nombre, correo, telefono, created_by, updated_by, eliminado, status) VALUES (?, ?, ?, ?, ?, ?, false, 1) RETURNING id";
            $st = $this->db->prepare($sql);
            $st->execute([$identificacion, $nombre, $correo, $telefono, $idUsuario, $idUsuario]);
        }
        return (int) $st->fetchColumn();
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
        $tipoId = trim((string)($fila[0] ?? ''));
        $identificacion = trim((string)($fila[1] ?? ''));
        $razonSocial = trim((string)($fila[2] ?? ''));
        $direccion = trim((string)($fila[3] ?? ''));
        $email = trim((string)($fila[4] ?? ''));
        $telefono = trim((string)($fila[5] ?? ''));
        $plazo = (int)($fila[6] ?? 0);

        if (empty($identificacion) || empty($razonSocial)) throw new Exception("Fila {$numeroFila}: Identificación y Razón Social son obligatorios.");

        $stCheck = $this->db->prepare("SELECT id FROM proveedores WHERE id_empresa = ? AND identificacion = ? AND eliminado = false");
        $stCheck->execute([$idEmpresa, $identificacion]);
        if ($stCheck->fetchColumn()) throw new Exception("Fila {$numeroFila}: El proveedor {$identificacion} ya existe.");

        $sql = "INSERT INTO proveedores (id_empresa, id_usuario, tipo_id, identificacion, razon_social, direccion, email, telefono, plazo, created_by, updated_by, eliminado, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, false, 1) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa, $idUsuario, $tipoId, $identificacion, $razonSocial, $direccion, $email, $telefono, $plazo, $idUsuario, $idUsuario]);
        return (int) $st->fetchColumn();
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
