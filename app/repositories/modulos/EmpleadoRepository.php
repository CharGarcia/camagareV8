<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class EmpleadoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('empleados');
    }

    /**
     * Obtiene el listado de empleados con filtros de búsqueda y paginación.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $whitelist = [
            'identificacion', 'nombres_apellidos', 'email', 'telefono', 'estado', 'id',
            'sexo', 'cargo', 'departamento', 'sueldo_base', 'valor_semanal', 'valor_quincena',
            'region', 'nombre_banco'
        ];
        if (!in_array($ordenCol, $whitelist)) {
            $ordenCol = 'nombres_apellidos';
        }

        $params = [':id_empresa' => $idEmpresa];
        $whereSql = $this->getBaseWhere($idEmpresa, 'e', $idUsuarioFiltro);
        
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (e.identificacion ILIKE :buscar OR e.nombres_apellidos ILIKE :buscar OR e.email ILIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }

        $orderExpr = match($ordenCol) {
            'nombre_banco' => 'b.nombre_banco',
            default        => "e.{$ordenCol}"
        };

        // Obtener el conteo total
        $stTotal = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} e {$whereSql}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        // Obtener las filas
        $offset = ($page - 1) * $perPage;
        $sqlRows = "SELECT e.*, b.nombre_banco
                    FROM {$this->table} e
                    LEFT JOIN bancos_ecuador b ON b.id = e.id_banco_ecuador
                    {$whereSql} 
                    ORDER BY {$orderExpr} {$ordenDir}";
        
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Crear un nuevo empleado.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, tipo_id, identificacion,
                    nombres_apellidos, direccion, email, telefono,
                    contacto_emergencia, fecha_nacimiento, sexo, estado,
                    id_banco_ecuador, tipo_cuenta, numero_cuenta,
                    fondos_reserva, aporta_iess, decimo_tercero, decimo_cuarto,
                    aporte_personal, aporte_patronal, sueldo_base, valor_semanal, valor_quincena,
                    region, cargo, lugar_trabajo, horario_trabajo,
                    departamento, codigo_sectorial_iess,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :tipo_id, :identificacion,
                    :nombres_apellidos, :direccion, :email, :telefono,
                    :contacto_emergencia, :fecha_nacimiento, :sexo, :estado,
                    :id_banco_ecuador, :tipo_cuenta, :numero_cuenta,
                    :fondos_reserva, :aporta_iess, :decimo_tercero, :decimo_cuarto,
                    :aporte_personal, :aporte_patronal, :sueldo_base, :valor_semanal, :valor_quincena,
                    :region, :cargo, :lugar_trabajo, :horario_trabajo,
                    :departamento, :codigo_sectorial_iess,
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'           => $data['id_empresa'],
            ':tipo_id'              => $data['tipo_id'],
            ':identificacion'       => $data['identificacion'],
            ':nombres_apellidos'    => mb_strtoupper($data['nombres_apellidos']),
            ':direccion'            => $data['direccion'] ?? null,
            ':email'                => $data['email'] ?? null,
            ':telefono'             => $data['telefono'] ?? null,
            ':contacto_emergencia'  => $data['contacto_emergencia'] ?? null,
            ':fecha_nacimiento'     => $data['fecha_nacimiento'] ?: null,
            ':sexo'                 => $data['sexo'] ?? 'M',
            ':estado'               => $data['estado'] ?? 'activo',
            ':id_banco_ecuador'     => $data['id_banco_ecuador'] ?: null,
            ':tipo_cuenta'          => $data['tipo_cuenta'] ?? null,
            ':numero_cuenta'        => $data['numero_cuenta'] ?? null,
            ':fondos_reserva'       => $data['fondos_reserva'] ?? 'no_se_paga',
            ':aporta_iess'          => (($data['aporta_iess'] ?? 'si') === 'si') ? 'true' : 'false',
            ':decimo_tercero'       => $data['decimo_tercero'] ?? 'acumula',
            ':decimo_cuarto'        => $data['decimo_cuarto'] ?? 'acumula',
            ':aporte_personal'      => floatval($data['aporte_personal'] ?? 0),
            ':aporte_patronal'      => floatval($data['aporte_patronal'] ?? 0),
            ':sueldo_base'          => floatval($data['sueldo_base'] ?? 0),
            ':valor_semanal'        => floatval($data['valor_semanal'] ?? 0),
            ':valor_quincena'       => floatval($data['valor_quincena'] ?? 0),
            ':region'               => $data['region'] ?? 'costa',
            ':cargo'                => $data['cargo'] ?? null,
            ':lugar_trabajo'        => $data['lugar_trabajo'] ?? null,
            ':horario_trabajo'      => $data['horario_trabajo'] ?? null,
            ':departamento'         => $data['departamento'] ?? null,
            ':codigo_sectorial_iess' => $data['codigo_sectorial_iess'] ?? null,
            ':id_u'                 => $data['id_usuario']
        ]);

        return $this->lastInsertId();
    }

    /**
     * Actualiza un empleado existente.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    tipo_id = :tipo_id,
                    identificacion = :identificacion,
                    nombres_apellidos = :nombres_apellidos,
                    direccion = :direccion,
                    email = :email,
                    telefono = :telefono,
                    contacto_emergencia = :contacto_emergencia,
                    fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo,
                    estado = :estado,
                    id_banco_ecuador = :id_banco_ecuador,
                    tipo_cuenta = :tipo_cuenta,
                    numero_cuenta = :numero_cuenta,
                    fondos_reserva = :fondos_reserva,
                    aporta_iess = :aporta_iess,
                    decimo_tercero = :decimo_tercero,
                    decimo_cuarto = :decimo_cuarto,
                    aporte_personal = :aporte_personal,
                    aporte_patronal = :aporte_patronal,
                    sueldo_base = :sueldo_base,
                    valor_semanal = :valor_semanal,
                    valor_quincena = :valor_quincena,
                    region = :region,
                    cargo = :cargo,
                    lugar_trabajo = :lugar_trabajo,
                    horario_trabajo = :horario_trabajo,
                    departamento = :departamento,
                    codigo_sectorial_iess = :codigo_sectorial_iess,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $st = $this->db->prepare($sql);
        return $st->execute([
            ':tipo_id'              => $data['tipo_id'],
            ':identificacion'       => $data['identificacion'],
            ':nombres_apellidos'    => mb_strtoupper($data['nombres_apellidos']),
            ':direccion'            => $data['direccion'] ?? null,
            ':email'                => $data['email'] ?? null,
            ':telefono'             => $data['telefono'] ?? null,
            ':contacto_emergencia'  => $data['contacto_emergencia'] ?? null,
            ':fecha_nacimiento'     => $data['fecha_nacimiento'] ?: null,
            ':sexo'                 => $data['sexo'] ?? 'M',
            ':estado'               => $data['estado'] ?? 'activo',
            ':id_banco_ecuador'     => $data['id_banco_ecuador'] ?: null,
            ':tipo_cuenta'          => $data['tipo_cuenta'] ?? null,
            ':numero_cuenta'        => $data['numero_cuenta'] ?? null,
            ':fondos_reserva'       => $data['fondos_reserva'] ?? 'no_se_paga',
            ':aporta_iess'          => (($data['aporta_iess'] ?? 'si') === 'si') ? 'true' : 'false',
            ':decimo_tercero'       => $data['decimo_tercero'] ?? 'acumula',
            ':decimo_cuarto'        => $data['decimo_cuarto'] ?? 'acumula',
            ':aporte_personal'      => floatval($data['aporte_personal'] ?? 0),
            ':aporte_patronal'      => floatval($data['aporte_patronal'] ?? 0),
            ':sueldo_base'          => floatval($data['sueldo_base'] ?? 0),
            ':valor_semanal'        => floatval($data['valor_semanal'] ?? 0),
            ':valor_quincena'       => floatval($data['valor_quincena'] ?? 0),
            ':region'               => $data['region'] ?? 'costa',
            ':cargo'                => $data['cargo'] ?? null,
            ':lugar_trabajo'        => $data['lugar_trabajo'] ?? null,
            ':horario_trabajo'      => $data['horario_trabajo'] ?? null,
            ':departamento'         => $data['departamento'] ?? null,
            ':codigo_sectorial_iess' => $data['codigo_sectorial_iess'] ?? null,
            ':updated_by'           => $data['id_usuario'],
            ':id'                   => $id,
            ':id_empresa'           => $idEmpresa
        ]);
    }

    /**
     * Eliminación lógica de un empleado.
     */
    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true,
                    deleted_by = :id_u,
                    deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id' => $id,
            ':id_empresa' => $idEmpresa,
            ':id_u' => $idUsuario
        ]);
    }

    /**
     * Verifica si existe otro empleado con la misma identificación en la misma empresa.
     */
    public function existsByIdentificacion(string $identificacion, int $idEmpresa, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE identificacion = :identificacion AND id_empresa = :id_empresa AND eliminado = false";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $st = $this->db->prepare($sql);
        $params = [':identificacion' => $identificacion, ':id_empresa' => $idEmpresa];
        if ($excludeId !== null) {
            $params[':exclude_id'] = $excludeId;
        }
        $st->execute($params);
        return $st->fetchColumn() !== false;
    }
    /**
     * Lista compacta de empleados (no eliminados) para poblar selects.
     */
    public function getActivosParaSelect(int $idEmpresa): array
    {
        $sql = "SELECT id, identificacion, nombres_apellidos
                FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY nombres_apellidos ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los periodos laborales de un empleado.
     */
    public function getPeriodos(int $idEmpleado, int $idEmpresa): array
    {
        $sql = "SELECT * FROM empleado_periodos WHERE id_empleado = :id_e AND id_empresa = :id_c AND eliminado = false ORDER BY fecha_ingreso DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_e' => $idEmpleado, ':id_c' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los rubros fijos de un empleado.
     */
    public function getRubrosFijos(int $idEmpleado, int $idEmpresa): array
    {
        $sql = "SELECT * FROM empleado_rubros_fijos WHERE id_empleado = :id_e AND id_empresa = :id_c AND eliminado = false ORDER BY tipo DESC, nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_e' => $idEmpleado, ':id_c' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sincroniza los periodos laborales.
     */
    public function syncPeriodos(int $idEmpleado, int $idEmpresa, array $periodos, int $idUsuario): void
    {
        // Eliminación lógica de anteriores
        $st = $this->db->prepare("UPDATE empleado_periodos SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP WHERE id_empleado = :e AND id_empresa = :c");
        $st->execute([':u' => $idUsuario, ':e' => $idEmpleado, ':c' => $idEmpresa]);

        if (empty($periodos)) return;

        $sql = "INSERT INTO empleado_periodos (id_empleado, id_empresa, fecha_ingreso, fecha_salida, motivo_salida, created_by, updated_by)
                VALUES (:e, :c, :fi, :fs, :m, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($periodos as $p) {
            if (empty($p['fecha_ingreso'])) continue;
            $st->execute([
                ':e' => $idEmpleado,
                ':c' => $idEmpresa,
                ':fi' => $p['fecha_ingreso'],
                ':fs' => !empty($p['fecha_salida']) ? $p['fecha_salida'] : null,
                ':m' => $p['motivo_salida'] ?? null,
                ':u' => $idUsuario
            ]);
        }
    }

    /**
     * Sincroniza los rubros fijos.
     */
    public function syncRubrosFijos(int $idEmpleado, int $idEmpresa, array $rubros, int $idUsuario): void
    {
        // Eliminación lógica de anteriores
        $st = $this->db->prepare("UPDATE empleado_rubros_fijos SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP WHERE id_empleado = :e AND id_empresa = :c");
        $st->execute([':u' => $idUsuario, ':e' => $idEmpleado, ':c' => $idEmpresa]);

        if (empty($rubros)) return;

        $sql = "INSERT INTO empleado_rubros_fijos (id_empleado, id_empresa, tipo, nombre, valor, aporta_iess, created_by, updated_by)
                VALUES (:e, :c, :t, :n, :v, :i, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($rubros as $r) {
            if (empty($r['nombre'])) continue;
            $st->execute([
                ':e' => $idEmpleado,
                ':c' => $idEmpresa,
                ':t' => $r['tipo'], // 'ingreso' o 'descuento'
                ':n' => mb_strtoupper($r['nombre']),
                ':v' => floatval($r['valor'] ?? 0),
                ':i' => (($r['aporta_iess'] ?? 'no') === 'si') ? 'true' : 'false',
                ':u' => $idUsuario
            ]);
        }
    }
}
