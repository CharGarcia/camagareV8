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
        'id_cuenta_cobrar', 'id_cuenta_ingreso', 'status'
    ];

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

    /**
     * Obtiene el listado de clientes con filtros, paginación y joins.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
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
            $where .= " AND (c.nombre ILIKE :buscar OR c.identificacion ILIKE :buscar OR c.email ILIKE :buscar OR c.telefono ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
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
            'exacto'   => [ 'estado' => 'c.estado', 'tipo' => 'c.tipo_id' ],
            'numerico' => [ 'plazo'  => 'c.plazo' ],
        ]);

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
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
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
     * Inserta un nuevo cliente con campos de auditoría.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email,
                    direccion, plazo, provincia, ciudad, status, id_vendedor,
                    id_forma_pago_sri, id_forma_cobro_predeterminada, tipo_operacion_bancaria_predeterminada, monto_maximo_auto_cobro, id_ingreso_concepto_predeterminado,
                    latitud, longitud, geocodificado_en,
                    created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :nombre, :tipo_id, :identificacion, :telefono, :email,
                    :direccion, :plazo, :provincia, :ciudad, :status, :id_vendedor,
                    :id_forma_pago_sri, :id_forma_cobro_predeterminada, :tipo_operacion_bancaria_predeterminada, :monto_maximo_auto_cobro, :id_ingreso_concepto_predeterminado,
                    :latitud::numeric, :longitud::numeric, :geocodificado_en::timestamp,
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
            ':monto_maximo_auto_cobro' => $data['monto_maximo_auto_cobro'] ?? null,
            ':id_ingreso_concepto_predeterminado' => $data['id_ingreso_concepto_predeterminado'] ?? null,
            ':latitud'          => $data['latitud'] ?? null,
            ':longitud'         => $data['longitud'] ?? null,
            ':geocodificado_en' => (isset($data['latitud']) && $data['latitud'] !== null) ? date('Y-m-d H:i:s') : null,
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
                monto_maximo_auto_cobro = :monto_maximo_auto_cobro,
                id_ingreso_concepto_predeterminado = :id_ingreso_concepto_predeterminado,
                latitud = :latitud::numeric,
                longitud = :longitud::numeric,
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
            ':monto_maximo_auto_cobro'       => $data['monto_maximo_auto_cobro'] ?? null,
            ':id_ingreso_concepto_predeterminado' => $data['id_ingreso_concepto_predeterminado'] ?? null,
            ':latitud'          => $data['latitud'] ?? null,
            ':longitud'         => $data['longitud'] ?? null,
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
