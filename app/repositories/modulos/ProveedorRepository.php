<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ProveedorRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'razon_social', 'identificacion', 'nombre_tipo_id', 'email', 'telefono', 
        'nombre_comercial', 'direccion', 'plazo', 'relacionado', 'status', 
        'nombre_tipo_empresa'
    ];

    private static bool $geoMigrated = false;

    public function __construct()
    {
        parent::__construct('proveedores');
        $this->ensureGeoColumns();
    }

    /**
     * Agrega las columnas de geolocalización si aún no existen.
     * Seguro de ejecutar múltiples veces gracias a IF NOT EXISTS.
     */
    private function ensureGeoColumns(): void
    {
        if (self::$geoMigrated) return;
        self::$geoMigrated = true;
        try {
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS latitud         DECIMAL(10,8) DEFAULT NULL");
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS longitud         DECIMAL(11,8) DEFAULT NULL");
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS geocodificado_en TIMESTAMP     DEFAULT NULL");
        } catch (\Throwable) {
            // Las columnas ya existen o el motor no soporta IF NOT EXISTS — se ignora
        }
    }

    /**
     * Devuelve el listado paginado y con búsqueda para Proveedores.
     */
    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'razon_social';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $whereSql .= " AND (p.razon_social ILIKE :b OR p.identificacion ILIKE :b OR p.email ILIKE :b OR p.telefono ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'         => 'p.razon_social',
                'razon'          => 'p.razon_social',
                'proveedor'      => 'p.razon_social',
                'comercial'      => 'p.nombre_comercial',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'email'          => 'p.email',
                'correo'         => 'p.email',
                'telefono'       => 'p.telefono',
                'direccion'      => 'p.direccion',
                'ciudad'         => 'ciu.nombre',
                'provincia'      => 'prov.nombre',
                'tipo_empresa'   => 'te.nombre',
            ],
            'exacto'   => [
                'estado'      => 'p.estado',
                'tipo'        => 'p.tipo_id_proveedor',
                'relacionado' => 'p.relacionado',
            ],
            'numerico' => [ 'plazo' => 'p.plazo' ],
        ]);

        $countJoins = "LEFT JOIN provincia prov ON prov.codigo = p.provincia
                       LEFT JOIN ciudad ciu ON ciu.codigo = p.ciudad AND ciu.cod_prov = p.provincia
                       LEFT JOIN tipo_empresa te ON te.id = p.tipo_empresa";

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} p {$countJoins} {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $orderExpr = match($ordenCol) {
            'nombre_tipo_id' => 'icv.nombre',
            'nombre_banco'   => 'b.nombre_banco',
            'nombre_provincia' => 'prov.nombre',
            'nombre_ciudad'    => 'ciu.nombre',
            'nombre_tipo_empresa' => 'te.nombre',
            default            => "p.{$ordenCol}"
        };

        $sqlRows = "SELECT p.*, icv.nombre AS nombre_tipo_id,
                           b.nombre_banco AS nombre_banco,                            prov.nombre AS nombre_provincia,
                           ciu.nombre AS nombre_ciudad,
                           te.nombre AS nombre_tipo_empresa,
                           rs_renta.codigo_ret || ' - ' || rs_renta.concepto_ret || ' (' || rs_renta.porcentaje_ret || '%)' AS nombre_retencion_renta,
                           rs_iva.codigo_ret || ' - ' || rs_iva.concepto_ret || ' (' || rs_iva.porcentaje_ret || '%)' AS nombre_retencion_iva,
                           st.codigo || ' - ' || st.nombre AS nombre_sustento_tributario
                    FROM {$this->table} p
                    LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
                    LEFT JOIN bancos_ecuador b ON b.id = p.id_banco
                    LEFT JOIN provincia prov ON prov.codigo = p.provincia
                    LEFT JOIN ciudad ciu ON ciu.codigo = p.ciudad AND ciu.cod_prov = p.provincia
                    LEFT JOIN tipo_empresa te ON te.id = p.tipo_empresa
                    LEFT JOIN retenciones_sri rs_renta ON rs_renta.id = p.id_retencion_renta
                    LEFT JOIN retenciones_sri rs_iva ON rs_iva.id = p.id_retencion_iva
                    LEFT JOIN sustento_tributario st ON st.id = p.id_sustento_tributario
                    {$whereSql}
                    ORDER BY $orderExpr $dir";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    /**
     * Verifica si existe otra identificación igual en la misma empresa, y del mismo tipo
     */
    public function existeIdentificacion(int $idEmpresa, string $tipoId, string $identificacion, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND tipo_id_proveedor = :tipo_id 
                  AND identificacion = :identificacion 
                  AND eliminado = false";
        $params = [
            ':id_empresa'    => $idEmpresa,
            ':tipo_id'       => $tipoId,
            ':identificacion' => $identificacion
        ];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Obtiene el detalle de un proveedor incluyendo nombres de auditoría.
     */
    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} p
                LEFT JOIN usuarios u_crea ON u_crea.id = p.created_by
                LEFT JOIN usuarios u_act ON u_act.id = p.updated_by
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene estadísticas de compras del proveedor.
     * En caso de que la tabla 'compras' no exista, regresa 0.
     */
    public function getEstadisticas(int $id, int $idEmpresa): array
    {
        $stats = [
            'documentos_recibidos' => 0,
            'total_compras' => 0.00,
            'por_pagar' => 0.00
        ];
        try {
            // Adaptar los nombres reales de columnas cuando se cree la tabla compras
            $sql = "SELECT COUNT(*) as docs, COALESCE(SUM(total), 0) as total, COALESCE(SUM(saldo), 0) as saldo 
                    FROM compras 
                    WHERE id_proveedor = :id 
                      AND id_empresa = :id_empresa 
                      AND eliminado = false";
            $st = $this->db->prepare($sql);
            $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['documentos_recibidos'] = (int)$row['docs'];
                $stats['total_compras'] = (float)$row['total'];
                $stats['por_pagar'] = (float)$row['saldo'];
            }
        } catch (\Throwable $e) {
            // Tabla compras aún no migrada
        }
        return $stats;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, razon_social, nombre_comercial, tipo_id_proveedor,
                    identificacion, email, direccion, provincia, ciudad, telefono, tipo_empresa, plazo, unidad_tiempo, relacionado,
                    id_banco, tipo_cta, numero_cta,
                    status, eliminado, created_at, id_forma_pago_predeterminada, monto_maximo_auto_pago,
                    id_retencion_renta, id_retencion_iva, id_sustento_tributario,
                    tipo_operacion_bancaria_predeterminada, id_egreso_concepto_predeterminado,
                    latitud, longitud, geocodificado_en
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :razon_social, :nombre_comercial, :tipo_id_proveedor,
                    :identificacion, :email, :direccion, :provincia, :ciudad, :telefono, :tipo_empresa, :plazo, :unidad_tiempo, :relacionado,
                    :id_banco, :tipo_cta, :numero_cta,
                    :status, :eliminado, CURRENT_TIMESTAMP, :id_forma_pago_predeterminada, :monto_maximo_auto_pago,
                    :id_retencion_renta, :id_retencion_iva, :id_sustento_tributario,
                    :tipo_operacion_bancaria_predeterminada, :id_egreso_concepto_predeterminado,
                    :latitud::numeric, :longitud::numeric, :geocodificado_en::timestamp
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'         => $data['id_empresa'],
            ':id_usuario'         => $data['id_usuario'],
            ':created_by'         => $data['created_by'],
            ':razon_social'       => $data['razon_social'],
            ':nombre_comercial'   => $data['nombre_comercial'] ?? null,
            ':tipo_id_proveedor'  => $data['tipo_id_proveedor'],
            ':identificacion'     => $data['identificacion'],
            ':email'              => $data['email'] ?? null,
            ':direccion'          => $data['direccion'] ?? null,
            ':provincia'          => $data['provincia'] ?? null,
            ':ciudad'             => $data['ciudad'] ?? null,
            ':telefono'           => $data['telefono'] ?? null,
            ':tipo_empresa'       => $data['tipo_empresa'] ?? null,
            ':plazo'              => $data['plazo'] ?? 0,
            ':unidad_tiempo'      => $data['unidad_tiempo'] ?? 'DIAS',
            ':relacionado'        => !empty($data['relacionado']) ? 'true' : 'false',
            ':id_banco'           => !empty($data['id_banco']) ? $data['id_banco'] : null,
            ':tipo_cta'           => !empty($data['tipo_cta']) ? $data['tipo_cta'] : null,
            ':numero_cta'         => $data['numero_cta'] ?? null,
            ':status'             => !empty($data['status']) ? 'true' : 'false',
            ':eliminado'          => !empty($data['eliminado']) ? 'true' : 'false',
            ':id_forma_pago_predeterminada' => $data['id_forma_pago_predeterminada'] ?? null,
            ':monto_maximo_auto_pago'       => $data['monto_maximo_auto_pago'] ?? null,
            ':id_retencion_renta'           => !empty($data['id_retencion_renta']) ? $data['id_retencion_renta'] : null,
            ':id_retencion_iva'             => !empty($data['id_retencion_iva']) ? $data['id_retencion_iva'] : null,
            ':id_sustento_tributario'       => !empty($data['id_sustento_tributario']) ? $data['id_sustento_tributario'] : null,
            ':tipo_operacion_bancaria_predeterminada' => $data['tipo_operacion_bancaria_predeterminada'] ?? null,
            ':id_egreso_concepto_predeterminado' => !empty($data['id_egreso_concepto_predeterminado']) ? (int)$data['id_egreso_concepto_predeterminado'] : null,
            ':latitud'           => isset($data['latitud'])  && $data['latitud']  !== '' ? $data['latitud']  : null,
            ':longitud'          => isset($data['longitud']) && $data['longitud'] !== '' ? $data['longitud'] : null,
            ':geocodificado_en'  => (isset($data['latitud']) && $data['latitud'] !== null && $data['latitud'] !== '') ? date('Y-m-d H:i:s') : null,
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $tieneCoordenadas = isset($data['latitud']) && $data['latitud'] !== null && $data['latitud'] !== '';
        $camposGeo = $tieneCoordenadas
            ? "latitud = :latitud::numeric, longitud = :longitud::numeric, geocodificado_en = CURRENT_TIMESTAMP,"
            : "latitud = :latitud::numeric, longitud = :longitud::numeric,";

        $sql = "UPDATE {$this->table} SET
                razon_social = :razon_social,
                nombre_comercial = :nombre_comercial,
                tipo_id_proveedor = :tipo_id_proveedor,
                identificacion = :identificacion,
                email = :email,
                direccion = :direccion,
                provincia = :provincia,
                ciudad = :ciudad,
                telefono = :telefono,
                tipo_empresa = :tipo_empresa,
                plazo = :plazo,
                unidad_tiempo = :unidad_tiempo,
                relacionado = :relacionado,
                id_banco = :id_banco,
                tipo_cta = :tipo_cta,
                numero_cta = :numero_cta,
                id_forma_pago_predeterminada = :id_forma_pago_predeterminada,
                tipo_operacion_bancaria_predeterminada = :tipo_operacion_bancaria_predeterminada,
                monto_maximo_auto_pago = :monto_maximo_auto_pago,
                id_retencion_renta = :id_retencion_renta,
                id_retencion_iva = :id_retencion_iva,
                id_sustento_tributario = :id_sustento_tributario,
                id_egreso_concepto_predeterminado = :id_egreso_concepto_predeterminado,
                {$camposGeo}
                status = :status,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':razon_social'       => $data['razon_social'],
            ':nombre_comercial'   => $data['nombre_comercial'] ?? null,
            ':tipo_id_proveedor'  => $data['tipo_id_proveedor'],
            ':identificacion'     => $data['identificacion'],
            ':email'              => $data['email'] ?? null,
            ':direccion'          => $data['direccion'] ?? null,
            ':provincia'          => $data['provincia'] ?? null,
            ':ciudad'             => $data['ciudad'] ?? null,
            ':telefono'           => $data['telefono'] ?? null,
            ':tipo_empresa'       => $data['tipo_empresa'] ?? null,
            ':plazo'              => $data['plazo'] ?? 0,
            ':unidad_tiempo'      => $data['unidad_tiempo'] ?? 'DIAS',
            ':relacionado'        => !empty($data['relacionado']) ? 'true' : 'false',
            ':id_banco'           => !empty($data['id_banco']) ? $data['id_banco'] : null,
            ':tipo_cta'           => !empty($data['tipo_cta']) ? $data['tipo_cta'] : null,
            ':numero_cta'         => $data['numero_cta'] ?? null,
            ':id_forma_pago_predeterminada' => $data['id_forma_pago_predeterminada'] ?? null,
            ':tipo_operacion_bancaria_predeterminada' => $data['tipo_operacion_bancaria_predeterminada'] ?? null,
            ':monto_maximo_auto_pago'       => $data['monto_maximo_auto_pago'] ?? null,
            ':id_retencion_renta'           => !empty($data['id_retencion_renta']) ? $data['id_retencion_renta'] : null,
            ':id_retencion_iva'             => !empty($data['id_retencion_iva']) ? $data['id_retencion_iva'] : null,
            ':id_sustento_tributario'       => !empty($data['id_sustento_tributario']) ? $data['id_sustento_tributario'] : null,
            ':id_egreso_concepto_predeterminado' => !empty($data['id_egreso_concepto_predeterminado']) ? (int)$data['id_egreso_concepto_predeterminado'] : null,
            ':latitud'            => isset($data['latitud'])  && $data['latitud']  !== '' ? $data['latitud']  : null,
            ':longitud'           => isset($data['longitud']) && $data['longitud'] !== '' ? $data['longitud'] : null,
            ':status'             => !empty($data['status']) ? 'true' : 'false',
            ':updated_by'         => $data['updated_by'],
            ':id'                 => $id,
            ':id_empresa'         => $idEmpresa
        ]);
    }

    /**
     * Actualiza solo las coordenadas de un proveedor.
     */
    public function updateCoordenadas(int $id, int $idEmpresa, float $lat, float $lng, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                latitud = :lat::numeric,
                longitud = :lng::numeric,
                geocodificado_en = CURRENT_TIMESTAMP,
                updated_by = :uid,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':lat' => $lat, ':lng' => $lng, ':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /**
     * Devuelve proveedores con coordenadas geocodificadas (para el mapa).
     */
    public function getConCoordenadas(int $idEmpresa): array
    {
        $sql = "SELECT p.id, p.razon_social, p.nombre_comercial, p.identificacion,
                       p.email, p.telefono, p.direccion, p.status,
                       p.latitud, p.longitud, p.geocodificado_en,
                       prov.nombre AS nombre_provincia,
                       ciu.nombre  AS nombre_ciudad
                FROM {$this->table} p
                LEFT JOIN provincia prov ON prov.codigo = p.provincia
                LEFT JOIN ciudad ciu ON ciu.codigo = p.ciudad AND ciu.cod_prov = p.provincia
                WHERE p.id_empresa = :id_empresa
                  AND p.eliminado = false
                  AND p.latitud IS NOT NULL
                  AND p.longitud IS NOT NULL
                ORDER BY p.razon_social ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta proveedores sin coordenadas.
     */
    public function countSinCoordenadas(int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND (latitud IS NULL OR longitud IS NULL)";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /**
     * Eliminación lógica con campos de auditoría.
     */
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
     * Verifica si el proveedor tiene transacciones asociadas (compras o liquidaciones).
     */
    public function estaEnUso(int $id, int $idEmpresa): bool
    {
        // 1. Verificar en compras
        $sqlC = "SELECT 1 FROM compras_cabecera WHERE id_proveedor = :id AND id_empresa = :id_e AND eliminado = false LIMIT 1";
        $stC = $this->db->prepare($sqlC);
        $stC->execute([':id' => $id, ':id_e' => $idEmpresa]);
        if ($stC->fetch()) {
            return true;
        }

        // 2. Verificar en liquidaciones de compra
        $sqlL = "SELECT 1 FROM liquidaciones_cabecera WHERE id_proveedor = :id AND id_empresa = :id_e AND eliminado = false LIMIT 1";
        $stL = $this->db->prepare($sqlL);
        $stL->execute([':id' => $id, ':id_e' => $idEmpresa]);
        if ($stL->fetch()) {
            return true;
        }

        return false;
    }
}
