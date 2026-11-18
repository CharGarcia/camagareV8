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
     *
     * $buscar sigue la sintaxis de FiltrosBusqueda: texto libre (por palabras, sin
     * tildes) sobre las columnas del listado + filtros `clave:valor` que arma el
     * modal de filtros de la vista (FiltrosModal). Las claves que no están en los
     * mapas de abajo se ignoran.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $whitelist = [
            'identificacion', 'nombres_apellidos', 'email', 'telefono', 'estado', 'id',
            'sexo', 'cargo', 'departamento', 'sueldo_base', 'valor_semanal', 'valor_quincena',
            'region', 'nombre_banco', 'fecha_nacimiento', 'direccion', 'tipo_cuenta',
            'numero_cuenta', 'tipo_id'
        ];
        if (!in_array($ordenCol, $whitelist)) {
            $ordenCol = 'nombres_apellidos';
        }

        $params = [':id_empresa' => $idEmpresa];
        $whereSql = $this->getBaseWhere($idEmpresa, 'e', $idUsuarioFiltro);
        
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre sobre las columnas visibles del listado. Tipo Id., Sexo,
            // Región y Estado no entran: se filtran desde el modal (igual que Proveedores).
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto([
                'e.identificacion',
                'e.nombres_apellidos',
                'e.email',
                'e.telefono',
                'e.direccion',
                'e.cargo',
                'e.departamento',
                'b.nombre_banco',
                'e.numero_cuenta',
            ], $parsed['texto_libre'], $params, 'emp_b');
            if ($cond !== '') {
                $whereSql .= ' AND ' . $cond;
            }
        }

        // Sí/No calculados: los NULL se leen como el valor por defecto de la ficha
        // (aporta IESS y participa en utilidades = Sí; discapacidad = No).
        $exacto = [
            'tipo'           => 'e.tipo_id',
            'estado'         => "COALESCE(e.estado, 'activo')",
            'sexo'           => 'e.sexo',
            'region'         => 'e.region',
            'cargo_sel'      => 'e.cargo',
            'depto_sel'      => 'e.departamento',
            'lugar_sel'      => 'e.lugar_trabajo',
            'id_banco'       => 'e.id_banco_ecuador',
            'tipo_cuenta'    => 'e.tipo_cuenta',
            'fondos'         => 'e.fondos_reserva',
            'decimo_tercero' => 'e.decimo_tercero',
            'decimo_cuarto'  => 'e.decimo_cuarto',
            'usuario'        => 'e.created_by',
            'con_email'      => "CASE WHEN NULLIF(TRIM(COALESCE(e.email, '')), '') IS NULL THEN 'no' ELSE 'si' END",
            'con_cuenta'     => "CASE WHEN NULLIF(TRIM(COALESCE(e.numero_cuenta, '')), '') IS NULL THEN 'no' ELSE 'si' END",
            'aporta_iess'    => "CASE WHEN COALESCE(e.aporta_iess, true) THEN 'si' ELSE 'no' END",
        ];
        // Columnas de database/migrations/20261008_empleados_utilidades_discapacidad.sql:
        // si el SQL aún no se aplicó, el filtro se ignora en vez de romper el listado.
        if ($this->columnaExiste($this->table, 'participa_utilidades')) {
            $exacto['utilidades'] = "CASE WHEN COALESCE(e.participa_utilidades, true) THEN 'si' ELSE 'no' END";
        }
        if ($this->columnaExiste($this->table, 'discapacidad')) {
            $exacto['discapacidad'] = "CASE WHEN COALESCE(e.discapacidad, false) THEN 'si' ELSE 'no' END";
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'         => 'e.nombres_apellidos',
                'empleado'       => 'e.nombres_apellidos',
                'identificacion' => 'e.identificacion',
                'cedula'         => 'e.identificacion',
                'email'          => 'e.email',
                'correo'         => 'e.email',
                'telefono'       => 'e.telefono',
                'direccion'      => 'e.direccion',
                'cargo'          => 'e.cargo',
                'departamento'   => 'e.departamento',
                'lugar'          => 'e.lugar_trabajo',
                'banco'          => 'b.nombre_banco',
                'cuenta'         => 'e.numero_cuenta',
            ],
            'exacto'   => $exacto,
            'fecha'    => [
                'nacimiento' => 'e.fecha_nacimiento',
                'registro'   => 'e.created_at',
                'created_at' => 'e.created_at',
            ],
            'numerico' => [
                'sueldo'   => 'e.sueldo_base',
                'semanal'  => 'e.valor_semanal',
                'quincena' => 'e.valor_quincena',
                'cargas'   => 'COALESCE(e.cargas_familiares, 0)',
            ],
        ]);

        $orderExpr = match($ordenCol) {
            'nombre_banco' => 'b.nombre_banco',
            default        => "e.{$ordenCol}"
        };

        // El JOIN de bancos participa del WHERE (texto libre y banco:): el COUNT lo
        // lleva igual que la consulta de filas para que ambos cuenten lo mismo.
        $from = "FROM {$this->table} e
                 LEFT JOIN bancos_ecuador b ON b.id = e.id_banco_ecuador";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from} {$whereSql}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sqlRows = "SELECT e.*, b.nombre_banco
                    {$from}
                    {$whereSql}
                    ORDER BY {$orderExpr} {$ordenDir}, e.id DESC";

        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Valores que la empresa usa en sus empleados, para los selects del modal de
     * filtros del listado (solo lo que existe, no catálogos completos).
     */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $p = [':id_empresa' => $idEmpresa];
        $leer = function (string $select, string $join, string $orden, string $extra = '') use ($p): array {
            $st = $this->db->prepare(
                "SELECT DISTINCT {$select} FROM {$this->table} e {$join}
                  WHERE e.id_empresa = :id_empresa AND e.eliminado = false {$extra}
                  ORDER BY {$orden}"
            );
            $st->execute($p);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };
        $texto = fn(string $col) => array_column(
            $leer("e.{$col} AS nombre", '', 'nombre', "AND NULLIF(TRIM(e.{$col}), '') IS NOT NULL"),
            'nombre'
        );

        return [
            'bancos'        => $leer('b.id, b.nombre_banco AS nombre', 'JOIN bancos_ecuador b ON b.id = e.id_banco_ecuador', 'nombre'),
            'cargos'        => $texto('cargo'),
            'departamentos' => $texto('departamento'),
            'lugares'       => $texto('lugar_trabajo'),
            'usuarios'      => $leer('u.id, u.nombre', 'JOIN usuarios u ON u.id = e.created_by', 'u.nombre'),
        ];
    }

    /**
     * Empleados ACTIVOS de la empresa (id, identificación y nombre), ordenados por
     * nombre. Lo usa la plantilla Excel de Novedades para venir ya prellenada con
     * el personal; no aplica el filtro de registros propios, igual que el buscador
     * de empleados del modal.
     */
    public function getActivosBasico(int $idEmpresa): array
    {
        $sql = "SELECT id, identificacion, nombres_apellidos
                  FROM {$this->table}
                 WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                 ORDER BY nombres_apellidos ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Columnas de la ficha que se escriben SOLO si el dato viene en $data y la
     * columna ya existe en la base (database/migrations/20261008_empleados_utilidades_discapacidad.sql):
     * así un alta o edición que no las trae (importador por Excel, llamadas parciales)
     * no las pisa con un valor por defecto, y el código no falla si se despliega antes
     * que el SQL.
     * @return array<string, mixed> columna => valor ya convertido para PDO
     */
    private function columnasOpcionales(array $data): array
    {
        $out = [];
        $siNo = fn($v) => in_array(strtolower((string) $v), ['si', 'sí', '1', 't', 'true'], true) ? 'true' : 'false';
        if (array_key_exists('participa_utilidades', $data) && $this->columnaExiste($this->table, 'participa_utilidades')) {
            $out['participa_utilidades'] = $siNo($data['participa_utilidades']);
        }
        if (array_key_exists('discapacidad', $data) && $this->columnaExiste($this->table, 'discapacidad')) {
            $out['discapacidad'] = $siNo($data['discapacidad']);
        }
        if (array_key_exists('porcentaje_discapacidad', $data) && $this->columnaExiste($this->table, 'porcentaje_discapacidad')) {
            $out['porcentaje_discapacidad'] = max(0, min(100, (int) $data['porcentaje_discapacidad']));
        }
        return $out;
    }

    /**
     * Crear un nuevo empleado.
     */
    public function create(array $data): int
    {
        $opcionales = $this->columnasOpcionales($data);
        $colsOpc = $opcionales ? ', ' . implode(', ', array_keys($opcionales)) : '';
        $valsOpc = $opcionales ? ', :' . implode(', :', array_keys($opcionales)) : '';
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, tipo_id, identificacion,
                    nombres_apellidos, direccion, email, telefono,
                    contacto_emergencia, cargas_familiares, fecha_nacimiento, sexo, estado,
                    id_banco_ecuador, tipo_cuenta, numero_cuenta,
                    fondos_reserva, aporta_iess, decimo_tercero, decimo_cuarto,
                    aporte_personal, aporte_patronal, sueldo_base, valor_semanal, valor_quincena,
                    region, cargo, lugar_trabajo, horario_trabajo,
                    departamento, codigo_sectorial_iess, atraso_modo, excluir_calculo_ir{$colsOpc},
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :tipo_id, :identificacion,
                    :nombres_apellidos, :direccion, :email, :telefono,
                    :contacto_emergencia, :cargas_familiares, :fecha_nacimiento, :sexo, :estado,
                    :id_banco_ecuador, :tipo_cuenta, :numero_cuenta,
                    :fondos_reserva, :aporta_iess, :decimo_tercero, :decimo_cuarto,
                    :aporte_personal, :aporte_patronal, :sueldo_base, :valor_semanal, :valor_quincena,
                    :region, :cargo, :lugar_trabajo, :horario_trabajo,
                    :departamento, :codigo_sectorial_iess, :atraso_modo, :excluir_calculo_ir{$valsOpc},
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";

        $st = $this->db->prepare($sql);
        $paramsOpc = [];
        foreach ($opcionales as $c => $v) $paramsOpc[':' . $c] = $v;
        $st->execute($paramsOpc + [
            ':id_empresa'           => $data['id_empresa'],
            ':tipo_id'              => $data['tipo_id'],
            ':identificacion'       => $data['identificacion'],
            ':nombres_apellidos'    => mb_strtoupper($data['nombres_apellidos']),
            ':direccion'            => $data['direccion'] ?? null,
            ':email'                => $data['email'] ?? null,
            ':telefono'             => $data['telefono'] ?? null,
            ':contacto_emergencia'  => $data['contacto_emergencia'] ?? null,
            ':cargas_familiares'    => max(0, (int) ($data['cargas_familiares'] ?? 0)),
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
            ':atraso_modo'          => !empty($data['atraso_modo']) ? $data['atraso_modo'] : null,
            ':excluir_calculo_ir'   => (($data['excluir_calculo_ir'] ?? 'no') === 'si') ? 'true' : 'false',
            ':id_u'                 => $data['id_usuario']
        ]);

        return $this->lastInsertId();
    }

    /**
     * Actualiza un empleado existente.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $opcionales = $this->columnasOpcionales($data);
        $setOpc = '';
        foreach (array_keys($opcionales) as $c) $setOpc .= "\n                    {$c} = :{$c},";
        $sql = "UPDATE {$this->table} SET
                    tipo_id = :tipo_id,
                    identificacion = :identificacion,
                    nombres_apellidos = :nombres_apellidos,
                    direccion = :direccion,
                    email = :email,
                    telefono = :telefono,
                    contacto_emergencia = :contacto_emergencia,
                    cargas_familiares = :cargas_familiares,
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
                    atraso_modo = :atraso_modo,
                    excluir_calculo_ir = :excluir_calculo_ir,{$setOpc}
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $st = $this->db->prepare($sql);
        $paramsOpc = [];
        foreach ($opcionales as $c => $v) $paramsOpc[':' . $c] = $v;
        return $st->execute($paramsOpc + [
            ':tipo_id'              => $data['tipo_id'],
            ':identificacion'       => $data['identificacion'],
            ':nombres_apellidos'    => mb_strtoupper($data['nombres_apellidos']),
            ':direccion'            => $data['direccion'] ?? null,
            ':email'                => $data['email'] ?? null,
            ':telefono'             => $data['telefono'] ?? null,
            ':contacto_emergencia'  => $data['contacto_emergencia'] ?? null,
            ':cargas_familiares'    => max(0, (int) ($data['cargas_familiares'] ?? 0)),
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
            ':atraso_modo'          => !empty($data['atraso_modo']) ? $data['atraso_modo'] : null,
            ':excluir_calculo_ir'   => (($data['excluir_calculo_ir'] ?? 'no') === 'si') ? 'true' : 'false',
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

    /** Asignaciones de horario/turno del empleado (turno + punto + vigencia). */
    public function getAsignacionesHorario(int $idEmpleado, int $idEmpresa): array
    {
        $sql = "SELECT eh.id, eh.id_horario, eh.id_punto, eh.vigente_desde, eh.vigente_hasta,
                       h.nombre AS horario_nombre, p.nombre AS punto_nombre
                FROM asistencia_empleado_horario eh
                JOIN asistencia_horarios h ON h.id = eh.id_horario
                LEFT JOIN asistencia_puntos p ON p.id = eh.id_punto
                WHERE eh.id_empleado = :e AND eh.id_empresa = :c AND eh.eliminado = false
                ORDER BY eh.vigente_desde DESC";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':e' => $idEmpleado, ':c' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return []; // módulo de asistencia no desplegado
        }
    }

    /** Sincroniza las asignaciones de horario del empleado (borra e inserta). */
    public function syncAsignacionesHorario(int $idEmpleado, int $idEmpresa, array $asignaciones, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE asistencia_empleado_horario SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP WHERE id_empleado = :e AND id_empresa = :c AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':e' => $idEmpleado, ':c' => $idEmpresa]);

        if (empty($asignaciones)) return;

        $sql = "INSERT INTO asistencia_empleado_horario (id_empresa, id_empleado, id_horario, id_punto, vigente_desde, vigente_hasta, created_by, updated_by)
                VALUES (:c, :e, :h, :p, :vd, :vh, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($asignaciones as $a) {
            $idHorario = (int) ($a['id_horario'] ?? 0);
            $desde     = trim((string) ($a['vigente_desde'] ?? ''));
            if ($idHorario <= 0 || $desde === '') continue; // fila incompleta
            $st->execute([
                ':c'  => $idEmpresa,
                ':e'  => $idEmpleado,
                ':h'  => $idHorario,
                ':p'  => !empty($a['id_punto']) ? (int) $a['id_punto'] : null,
                ':vd' => $desde,
                ':vh' => !empty($a['vigente_hasta']) ? $a['vigente_hasta'] : null,
                ':u'  => $idUsuario,
            ]);
        }
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
