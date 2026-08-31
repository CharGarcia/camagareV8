<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ClienteRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'identificacion', 'nombre_tipo_id', 'nombre', 'email', 'telefono', 'direccion',
        'plazo', 'nombre_provincia', 'nombre_ciudad', 'nombre_vendedor',
        'id_cuenta_cobrar', 'id_cuenta_ingreso', 'status',
        'frecuencia_visita', 'orden_visita'
    ];

    /**
     * Columnas de la ficha guardadas como arrays de Postgres (smallint[]).
     * PDO no convierte arrays de PHP, así que entran serializadas con
     * arrayAPostgres() y salen decodificadas con hidratarVisita().
     */
    private const COLUMNAS_ARRAY = ['dias_visita', 'semanas_visita'];

    public function __construct()
    {
        parent::__construct('clientes');
        
        try {
            // Inyectar columna id_ingreso_concepto_predeterminado si no existe
            $check = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'clientes' AND column_name = 'id_ingreso_concepto_predeterminado'");
            if (!$check->fetch()) {
                $this->db->exec("ALTER TABLE clientes ADD COLUMN id_ingreso_concepto_predeterminado INT NULL");
            }
        } catch (\Throwable $e) {}
    }

    // ─── Días / frecuencia de visita (smallint[]) ───────────────────────────

    /**
     * Serializa un array de PHP al literal de array de Postgres ("{1,3,5}").
     * PDO/pgsql no tiene binding nativo de arrays: si se pasa el array de PHP
     * directo lanza "Array to string conversion" y guarda basura.
     */
    private function arrayAPostgres(?array $valores): ?string
    {
        if (empty($valores)) {
            return null;
        }
        $enteros = [];
        foreach ($valores as $v) {
            if (is_scalar($v) && ctype_digit(trim((string) $v))) {
                $enteros[] = (int) $v;
            }
        }
        return $enteros ? '{' . implode(',', $enteros) . '}' : null;
    }

    /** Decodifica el literal de array de Postgres ("{1,3,5}") a int[]. */
    private function postgresAArray($valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_array($valor)) {
            return array_values(array_map('intval', $valor));
        }
        $limpio = trim((string) $valor, '{}');
        if ($limpio === '') {
            return null;
        }
        $items = array_filter(array_map('trim', explode(',', $limpio)), fn($v) => $v !== '');
        return $items ? array_values(array_map('intval', $items)) : null;
    }

    /**
     * Deja las columnas de tipo array como arrays de PHP en la fila leída, para
     * que la vista y el JSON del modal reciban [1,3,5] y no la cadena "{1,3,5}".
     */
    private function hidratarVisita(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        foreach (self::COLUMNAS_ARRAY as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = $this->postgresAArray($row[$col]);
            }
        }
        return $row;
    }

    /** @param array<int,array> $rows */
    private function hidratarVisitaLote(array $rows): array
    {
        return array_map(fn(array $r) => $this->hidratarVisita($r), $rows);
    }

    /**
     * Filtros del buscador propios de la ruta de visita. Van aparte de
     * FiltrosBusqueda::aplicarFiltros() porque ese helper resuelve columnas
     * escalares (=, IN, ILIKE) y estas dos son arrays: la comparación correcta
     * es de pertenencia (`= ANY(...)`), no de igualdad.
     *
     * Soporta: dia_visita:martes | dia_visita:3 | dia_visita:lun,mie | -dia_visita:sabado
     *          semana_visita:1 | semana_visita:1,3
     */
    private function aplicarFiltrosVisita(string &$where, array &$params, array &$filtros): void
    {
        $mapa = [
            'dia_visita'    => ['col' => 'c.dias_visita',    'parser' => 'dia'],
            'dia'           => ['col' => 'c.dias_visita',    'parser' => 'dia'],
            'semana_visita' => ['col' => 'c.semanas_visita', 'parser' => 'semana'],
            'semana'        => ['col' => 'c.semanas_visita', 'parser' => 'semana'],
        ];

        foreach ($mapa as $clave => $cfg) {
            if (!isset($filtros[$clave])) {
                continue;
            }
            $filtro = $filtros[$clave];
            // Se consume aquí para que aplicarFiltros() no lo vuelva a procesar.
            unset($filtros[$clave]);

            $valores = is_array($filtro['valor']) ? $filtro['valor'] : [$filtro['valor']];
            $numeros = [];
            foreach ($valores as $v) {
                $n = $cfg['parser'] === 'dia'
                    ? \App\Helpers\DiasVisita::parsearDia((string) $v)
                    : (ctype_digit(trim((string) $v)) && (int) $v >= 1 && (int) $v <= 5 ? (int) $v : null);
                if ($n !== null) {
                    $numeros[$n] = $n;
                }
            }

            if (!$numeros) {
                continue; // Valor no reconocido: se ignora en silencio, como el resto del helper.
            }

            $ors = [];
            foreach (array_values($numeros) as $i => $n) {
                $ph = ":vis_{$clave}_{$i}";
                $ors[] = "{$ph}::smallint = ANY({$cfg['col']})";
                $params[$ph] = $n;
            }

            $cond = '(' . implode(' OR ', $ors) . ')';
            // Negado: además de no tener ese día, un cliente sin ruta definida
            // (columna NULL) también cumple "no lo visito el sábado".
            $where .= !empty($filtro['neg'])
                ? " AND (NOT {$cond} OR {$cfg['col']} IS NULL)"
                : " AND {$cond}";
        }
    }

    /**
     * Obtiene el listado de clientes con filtros, paginación y joins.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, bool $soloActivos = false): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        
        $where = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['c.nombre', 'c.identificacion', 'c.email', 'c.telefono'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        // La columna c.status es entera (1=activo, 0=inactivo) pero el buscador
        // envía 'activo'/'inactivo'. Traducir para no romper el bind a integer.
        $mapEstado = ['activo' => '1', 'inactivo' => '0'];
        foreach (['estado', 'status'] as $claveEstado) {
            if (!isset($parsed['filtros'][$claveEstado])) {
                continue;
            }
            $val = $parsed['filtros'][$claveEstado]['valor'];
            $parsed['filtros'][$claveEstado]['valor'] = is_array($val)
                ? array_map(fn($v) => $mapEstado[strtolower(trim((string)$v))] ?? $v, $val)
                : ($mapEstado[strtolower(trim((string)$val))] ?? $val);
        }

        // La frecuencia se guarda en mayúsculas ('SEMANAL'); el usuario escribe
        // 'semanal'. La columna es exacta, así que hay que normalizar antes.
        foreach (['frecuencia', 'frecuencia_visita'] as $claveFrec) {
            if (!isset($parsed['filtros'][$claveFrec])) {
                continue;
            }
            $val = $parsed['filtros'][$claveFrec]['valor'];
            $parsed['filtros'][$claveFrec]['valor'] = is_array($val)
                ? array_map(fn($v) => strtoupper(trim((string)$v)), $val)
                : strtoupper(trim((string)$val));
        }

        // Arrays (días/semanas de visita): se resuelven aparte y se quitan de
        // $parsed['filtros'] para que aplicarFiltros() no los trate como escalares.
        $this->aplicarFiltrosVisita($where, $params, $parsed['filtros']);

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'         => 'c.nombre',
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'email'          => 'c.email',
                'correo'         => 'c.email',
                'telefono'       => 'c.telefono',
                'direccion'      => 'c.direccion',
                'ciudad'         => 'ciu.nombre',
                'provincia'      => 'p.nombre',
                'vendedor'       => 'v.nombre',
            ],
            'exacto'   => [
                'estado'            => 'c.status',
                'status'            => 'c.status',
                'tipo'              => 'c.tipo_id',
                'frecuencia'        => 'c.frecuencia_visita',
                'frecuencia_visita' => 'c.frecuencia_visita',
            ],
            'numerico' => [ 'plazo' => 'c.plazo', 'orden_visita' => 'c.orden_visita' ],
        ]);

        // Selección de cliente para OPERACIONES: excluir inactivos (status = 0).
        // La lista/CRUD de clientes NO pasa este flag, así que ahí sí se ven.
        if ($soloActivos) {
            $where .= " AND c.status = 1";
        }

        $joins = "LEFT JOIN vendedores v ON v.id = c.id_vendedor
                  LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                  LEFT JOIN provincia p ON p.codigo = c.provincia
                  LEFT JOIN ciudad ciu ON ciu.codigo = c.ciudad AND ciu.cod_prov = c.provincia";

        // Obtener total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} c $joins $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $limitOffset = "";
            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $limitOffset = " LIMIT $perPage OFFSET $offset";
            }
            $orderExpr = match($ordenCol) {
                'nombre_vendedor'  => 'v.nombre',
                'nombre_tipo_id'   => 'icv.nombre',
                'nombre_provincia' => 'p.nombre',
                'nombre_ciudad'    => 'ciu.nombre',
                default            => "c.\"{$ordenCol}\""
            };
            $sql = "SELECT c.*, v.nombre AS nombre_vendedor,
                           icv.nombre AS nombre_tipo_id,
                           p.nombre AS nombre_provincia,
                           ciu.nombre AS nombre_ciudad
                    FROM {$this->table} c
                    $joins
                    $where
                    ORDER BY $orderExpr $ordenDir
                    $limitOffset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $this->hidratarVisitaLote($st->fetchAll(PDO::FETCH_ASSOC));
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Cliente por id, con los mismos joins/columnas que getListado() (nombre_vendedor,
     * nombre_tipo_id, etc.) — mismo shape que espera seleccionarCliente() en Factura de Venta.
     */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT c.*, v.nombre AS nombre_vendedor,
                       icv.nombre AS nombre_tipo_id,
                       p.nombre AS nombre_provincia,
                       ciu.nombre AS nombre_ciudad
                FROM {$this->table} c
                LEFT JOIN vendedores v ON v.id = c.id_vendedor
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                LEFT JOIN provincia p ON p.codigo = c.provincia
                LEFT JOIN ciudad ciu ON ciu.codigo = c.ciudad AND ciu.cod_prov = c.provincia
                WHERE c.id = :id AND c.id_empresa = :id_empresa AND c.eliminado = false";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hidratarVisita($row) : null;
    }

    /**
     * Ficha cruda por id (BaseRepository) con los arrays de visita ya
     * decodificados. Es el método que alimentan store()/update()/get() del
     * controlador, así que el JSON que recibe el modal trae dias_visita como
     * [1,3,5] y no como la cadena "{1,3,5}".
     */
    public function findById(int $id, int $idEmpresa): ?array
    {
        return $this->hidratarVisita(parent::findById($id, $idEmpresa));
    }

    /**
     * Verifica si una identificación ya existe en la empresa.
     */
    public function existeIdentificacion(int $idEmpresa, string $tipoId, string $identificacion, ?int $idExcluir = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND tipo_id = :tipo_id 
                  AND identificacion = :identificacion 
                  AND eliminado = false";
        $params = [
            ':id_empresa'    => $idEmpresa,
            ':tipo_id'       => $tipoId,
            ':identificacion' => $identificacion
        ];

        if ($idExcluir !== null) {
            $sql .= " AND id <> :id_exc";
            $params[':id_exc'] = $idExcluir;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    /**
     * Busca por identificación + empresa, INCLUYENDO eliminados (a propósito:
     * si el cliente se había borrado, se reactiva y se reutiliza en vez de
     * crear un duplicado) — mismo criterio que ya usa Factura Express QR.
     */
    public function findByIdentificacion(int $idEmpresa, string $identificacion): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE identificacion = :ident AND id_empresa = :empresa LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':ident' => $identificacion, ':empresa' => $idEmpresa]);
        $row = $this->hidratarVisita($st->fetch(PDO::FETCH_ASSOC) ?: null);
        return $row ?: null;
    }

    /**
     * Reactiva un cliente eliminado SIN tocar sus datos (a diferencia de
     * reactivarYActualizar). Usado por la replicación entre empresas: si el cliente
     * ya existía en la empresa destino pero estaba eliminado, se reactiva tal cual
     * estaba en vez de sobrescribirlo con los datos de la empresa origen.
     */
    public function reactivarSoloEliminado(int $id, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = false,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':id' => $id]);
    }

    /** Reactiva (si estaba eliminado) y refresca nombre/email/teléfono de un cliente existente. */
    public function reactivarYActualizar(int $id, array $data): void
    {
        $sql = "UPDATE {$this->table} SET
                    nombre = :nombre,
                    email = COALESCE(NULLIF(:email, ''), email),
                    telefono = COALESCE(NULLIF(:tel, ''), telefono),
                    eliminado = false,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':nombre' => $data['nombre'],
            ':email'  => $data['email'] ?? '',
            ':tel'    => $data['telefono'] ?? '',
            ':uid'    => $data['id_usuario'],
            ':id'     => $id,
        ]);
    }

    /**
     * Inserta un nuevo cliente con campos de auditoría.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email,
                    direccion, plazo, provincia, ciudad, status, id_vendedor,
                    id_forma_pago_sri, id_forma_cobro_predeterminada, tipo_operacion_bancaria_predeterminada,
                    monto_minimo_auto_cobro, monto_maximo_auto_cobro, id_ingreso_concepto_predeterminado,
                    latitud, longitud, geocodificado_en,
                    dias_visita, frecuencia_visita, semanas_visita, orden_visita,
                    hora_visita_desde, hora_visita_hasta, observacion_visita,
                    created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :nombre, :tipo_id, :identificacion, :telefono, :email,
                    :direccion, :plazo, :provincia, :ciudad, :status, :id_vendedor,
                    :id_forma_pago_sri, :id_forma_cobro_predeterminada, :tipo_operacion_bancaria_predeterminada,
                    :monto_minimo_auto_cobro, :monto_maximo_auto_cobro, :id_ingreso_concepto_predeterminado,
                    :latitud::numeric, :longitud::numeric, :geocodificado_en::timestamp,
                    :dias_visita::smallint[], :frecuencia_visita, :semanas_visita::smallint[], :orden_visita,
                    :hora_visita_desde::time, :hora_visita_hasta::time, :observacion_visita,
                    :id_u, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $data['id_empresa'],
            ':id_usuario'       => $data['id_usuario'],
            ':nombre'           => $data['nombre'],
            ':tipo_id'          => $data['tipo_id'],
            ':identificacion'   => $data['identificacion'],
            ':telefono'         => $data['telefono'],
            ':email'            => $data['email'],
            ':direccion'        => $data['direccion'],
            ':plazo'            => $data['plazo'] ?? 0,
            ':provincia'        => $data['provincia'],
            ':ciudad'           => $data['ciudad'],
            ':status'           => $data['status'] ?? 1,
            ':id_vendedor'      => $data['id_vendedor'],
            ':id_forma_pago_sri' => $data['id_forma_pago_sri'] ?? null,
            ':id_forma_cobro_predeterminada' => $data['id_forma_cobro_predeterminada'] ?? null,
            ':tipo_operacion_bancaria_predeterminada' => $data['tipo_operacion_bancaria_predeterminada'] ?? null,
            ':monto_minimo_auto_cobro' => $data['monto_minimo_auto_cobro'] ?? null,
            ':monto_maximo_auto_cobro' => $data['monto_maximo_auto_cobro'] ?? null,
            ':id_ingreso_concepto_predeterminado' => $data['id_ingreso_concepto_predeterminado'] ?? null,
            ':latitud'          => $data['latitud'] ?? null,
            ':longitud'         => $data['longitud'] ?? null,
            ':geocodificado_en' => (isset($data['latitud']) && $data['latitud'] !== null) ? date('Y-m-d H:i:s') : null,
            ':dias_visita'       => $this->arrayAPostgres($data['dias_visita'] ?? null),
            ':frecuencia_visita' => $data['frecuencia_visita'] ?? null,
            ':semanas_visita'    => $this->arrayAPostgres($data['semanas_visita'] ?? null),
            ':orden_visita'      => $data['orden_visita'] ?? null,
            ':hora_visita_desde' => $data['hora_visita_desde'] ?? null,
            ':hora_visita_hasta' => $data['hora_visita_hasta'] ?? null,
            ':observacion_visita' => $data['observacion_visita'] ?? null,
            ':id_u'             => $data['id_usuario']
        ]);
        return (int) $this->db->lastInsertId('clientes_id_seq');
    }

    /**
     * Actualiza un cliente con campos de auditoría.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $tieneCoordenadas = isset($data['latitud']) && $data['latitud'] !== null;

        // Si hay coordenadas nuevas actualizamos geocodificado_en, si no lo dejamos intacto
        $campoCodificado = $tieneCoordenadas
            ? "geocodificado_en = CURRENT_TIMESTAMP,"
            : "";

        $sql = "UPDATE {$this->table} SET
                nombre = :nombre,
                tipo_id = :tipo_id,
                identificacion = :identificacion,
                telefono = :telefono,
                email = :email,
                direccion = :direccion,
                plazo = :plazo,
                provincia = :provincia,
                ciudad = :ciudad,
                status = :status,
                id_vendedor = :id_vendedor,
                id_forma_pago_sri = :id_forma_pago_sri,
                id_forma_cobro_predeterminada = :id_forma_cobro_predeterminada,
                tipo_operacion_bancaria_predeterminada = :tipo_operacion_bancaria_predeterminada,
                monto_minimo_auto_cobro = :monto_minimo_auto_cobro,
                monto_maximo_auto_cobro = :monto_maximo_auto_cobro,
                id_ingreso_concepto_predeterminado = :id_ingreso_concepto_predeterminado,
                latitud = :latitud::numeric,
                longitud = :longitud::numeric,
                dias_visita = :dias_visita::smallint[],
                frecuencia_visita = :frecuencia_visita,
                semanas_visita = :semanas_visita::smallint[],
                orden_visita = :orden_visita,
                hora_visita_desde = :hora_visita_desde::time,
                hora_visita_hasta = :hora_visita_hasta::time,
                observacion_visita = :observacion_visita,
                {$campoCodificado}
                updated_by = :id_u,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'           => $data['nombre'],
            ':tipo_id'          => $data['tipo_id'],
            ':identificacion'   => $data['identificacion'],
            ':telefono'         => $data['telefono'],
            ':email'            => $data['email'],
            ':direccion'        => $data['direccion'],
            ':plazo'            => $data['plazo'] ?? 0,
            ':provincia'        => $data['provincia'],
            ':ciudad'           => $data['ciudad'],
            ':status'           => $data['status'] ?? 1,
            ':id_vendedor'      => $data['id_vendedor'],
            ':id_forma_pago_sri'             => $data['id_forma_pago_sri'] ?? null,
            ':id_forma_cobro_predeterminada' => $data['id_forma_cobro_predeterminada'] ?? null,
            ':tipo_operacion_bancaria_predeterminada' => $data['tipo_operacion_bancaria_predeterminada'] ?? null,
            ':monto_minimo_auto_cobro'       => $data['monto_minimo_auto_cobro'] ?? null,
            ':monto_maximo_auto_cobro'       => $data['monto_maximo_auto_cobro'] ?? null,
            ':id_ingreso_concepto_predeterminado' => $data['id_ingreso_concepto_predeterminado'] ?? null,
            ':latitud'          => $data['latitud'] ?? null,
            ':longitud'         => $data['longitud'] ?? null,
            ':dias_visita'       => $this->arrayAPostgres($data['dias_visita'] ?? null),
            ':frecuencia_visita' => $data['frecuencia_visita'] ?? null,
            ':semanas_visita'    => $this->arrayAPostgres($data['semanas_visita'] ?? null),
            ':orden_visita'      => $data['orden_visita'] ?? null,
            ':hora_visita_desde' => $data['hora_visita_desde'] ?? null,
            ':hora_visita_hasta' => $data['hora_visita_hasta'] ?? null,
            ':observacion_visita' => $data['observacion_visita'] ?? null,
            ':id_u'             => $data['id_usuario'],
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa,
        ]);
    }

    /**
     * Actualiza solo las coordenadas de un cliente.
     */
    public function updateCoordenadas(int $id, int $idEmpresa, float $lat, float $lng, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                latitud = :lat,
                longitud = :lng,
                geocodificado_en = CURRENT_TIMESTAMP,
                updated_by = :id_u,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':lat'        => $lat,
            ':lng'        => $lng,
            ':id_u'       => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }

    /**
     * Eliminación lógica con campos de auditoría.
     */
    /**
     * Revisa todas las tablas operativas que referencian al cliente y devuelve
     * los módulos donde está siendo usado (solo registros NO eliminados).
     *
     * @return array<string,int> [etiqueta del módulo => cantidad de registros]
     */
    public function getUsosCliente(int $id, int $idEmpresa): array
    {
        // tabla => [etiqueta, filtra_por_id_empresa]
        $tablas = [
            'ventas_cabecera'          => ['Facturas de venta',     true],
            'notas_credito_cabecera'   => ['Notas de crédito',      true],
            'retencion_venta_cabecera' => ['Retenciones de venta',  true],
            'guias_remision_cabecera'  => ['Guías de remisión',     true],
            'ingresos_cabecera'        => ['Ingresos / cobros',     true],
            'pedidos_cabecera'         => ['Pedidos',               true],
            'suscripciones'            => ['Suscripciones',         true],
            'proyectos'                => ['Proyectos',             true],
            'citas'                    => ['Citas',                 true],
            'tareas'                   => ['Tareas',                false],
        ];

        $usos = [];
        foreach ($tablas as $tabla => [$etiqueta, $conEmpresa]) {
            $sql    = "SELECT COUNT(*) FROM {$tabla} WHERE id_cliente = :id AND eliminado = false";
            $params = [':id' => $id];
            if ($conEmpresa) {
                $sql .= " AND id_empresa = :id_empresa";
                $params[':id_empresa'] = $idEmpresa;
            }
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $usos[$etiqueta] = $n;
            }
        }
        return $usos;
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_by = :id_u,
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id'         => $id, 
            ':id_empresa' => $idEmpresa,
            ':id_u'       => $idUsuario
        ]);
    }

    /**
     * Obtiene clientes que tienen coordenadas registradas (para el mapa).
     */
    public function getConCoordenadas(int $idEmpresa): array
    {
        $sql = "SELECT c.id, c.nombre, c.identificacion, c.telefono, c.email,
                       c.direccion, c.latitud, c.longitud, c.status,
                       p.nombre  AS nombre_provincia,
                       ciu.nombre AS nombre_ciudad,
                       v.nombre  AS nombre_vendedor
                FROM {$this->table} c
                LEFT JOIN provincia p   ON p.codigo = c.provincia
                LEFT JOIN ciudad ciu    ON ciu.codigo = c.ciudad AND ciu.cod_prov = c.provincia
                LEFT JOIN vendedores v  ON v.id = c.id_vendedor
                WHERE c.id_empresa = :id_empresa
                  AND c.eliminado  = false
                  AND c.latitud   IS NOT NULL
                  AND c.longitud  IS NOT NULL
                ORDER BY c.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta clientes sin coordenadas (para estadística en el mapa).
     */
    public function countSinCoordenadas(int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND (latitud IS NULL OR longitud IS NULL)";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /**
     * Obtiene estadísticas de ventas y documentos para un cliente.
     */
    public function getEstadisticas(int $idCliente, int $idEmpresa): array
    {
        $stats = [
            'facturas_emitidas' => 0,
            'total_ventas'      => 0.00,
            'total_nc'          => 0.00,
            'facturas_anuladas' => 0
        ];

        // 1. Facturas (Contar válidas y sumar importes)
        $sqlVentas = "SELECT 
                        COUNT(*) FILTER (WHERE estado NOT IN ('borrador', 'anulado')) as emitidas,
                        COALESCE(SUM(importe_total) FILTER (WHERE estado NOT IN ('borrador', 'anulado')), 0) as total,
                        COALESCE(SUM(total_sin_impuestos) FILTER (WHERE estado NOT IN ('borrador', 'anulado')), 0) as subtotal,
                        COUNT(*) FILTER (WHERE estado = 'anulado') as anuladas
                      FROM ventas_cabecera 
                      WHERE id_cliente = :id_cliente 
                        AND id_empresa = :id_empresa 
                        AND eliminado = false";
        
        $stVentas = $this->db->prepare($sqlVentas);
        $stVentas->execute([':id_cliente' => $idCliente, ':id_empresa' => $idEmpresa]);
        $resVentas = $stVentas->fetch(PDO::FETCH_ASSOC);

        if ($resVentas) {
            $stats['facturas_emitidas'] = (int) ($resVentas['emitidas'] ?? 0);
            $stats['total_ventas']      = (float) ($resVentas['total'] ?? 0);
            $stats['total_subtotal']    = (float) ($resVentas['subtotal'] ?? 0);
            $stats['facturas_anuladas'] = (int) ($resVentas['anuladas'] ?? 0);
        }

        // 2. Notas de Crédito
        $sqlNC = "SELECT 
                    COALESCE(SUM(importe_total), 0) as total_nc,
                    COALESCE(SUM(total_sin_impuestos), 0) as subtotal_nc
                  FROM notas_credito_cabecera 
                  WHERE id_cliente = :id_cliente 
                    AND id_empresa = :id_empresa 
                    AND estado NOT IN ('borrador', 'anulado')
                    AND eliminado = false";
        
        try {
            $stNC = $this->db->prepare($sqlNC);
            $stNC->execute([':id_cliente' => $idCliente, ':id_empresa' => $idEmpresa]);
            $resNC = $stNC->fetch(PDO::FETCH_ASSOC);
            $stats['total_nc'] = (float) ($resNC['total_nc'] ?? 0);
            $stats['total_nc_subtotal'] = (float) ($resNC['subtotal_nc'] ?? 0);
        } catch (\Throwable $e) {
            $stats['total_nc'] = 0.0;
            $stats['total_nc_subtotal'] = 0.0;
        }

        return $stats;
    }
}
