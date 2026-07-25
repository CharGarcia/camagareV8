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
        'nombre_tipo_empresa', 'nombre_banco', 'nombre_provincia', 'nombre_ciudad'
    ];

    private static bool $geoMigrated = false;

    public function __construct()
    {
        parent::__construct('proveedores');
        $this->ensureColumnasOpcionales();
    }

    /**
     * Agrega las columnas de geolocalización y el límite inferior del rango de
     * auto pago si aún no existen (ver database/migrations/
     * 20260725_proveedores_monto_minimo_auto_pago.sql).
     * Seguro de ejecutar múltiples veces gracias a IF NOT EXISTS.
     */
    private function ensureColumnasOpcionales(): void
    {
        if (self::$geoMigrated) return;
        self::$geoMigrated = true;
        try {
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS latitud         DECIMAL(10,8) DEFAULT NULL");
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS longitud         DECIMAL(11,8) DEFAULT NULL");
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS geocodificado_en TIMESTAMP     DEFAULT NULL");
            $this->db->exec("ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS monto_minimo_auto_pago NUMERIC(14,2) DEFAULT NULL");
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
     * Resumen comercial del proveedor (solo lectura) para el modal:
     *
     *  - documentos_recibidos: compras (facturas, NC y ND) + liquidaciones de compra.
     *  - total_compras:        neto comprado = facturas + liquidaciones − NC + ND.
     *  - por_pagar:            saldo pendiente con el mismo criterio de Cuentas por
     *                          Pagar (documento − pagado − retenido − NC + ND), más
     *                          los saldos iniciales CXP pendientes.
     *
     * Se limita al ambiente activo de la empresa, igual que el resto de módulos
     * transaccionales. Si algo falla devuelve ceros sin romper el modal.
     */
    public function getEstadisticas(int $id, int $idEmpresa): array
    {
        $stats = [
            'documentos_recibidos' => 0,
            'total_compras'        => 0.00,
            'por_pagar'            => 0.00,
        ];

        $params = [':id' => $id, ':id_empresa' => $idEmpresa];

        try {
            // Ambiente activo de la empresa ('1' pruebas | '2' producción)
            $stA = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR) FROM empresas WHERE id = :id_empresa LIMIT 1");
            $stA->execute([':id_empresa' => $idEmpresa]);
            $amb = $stA->fetchColumn();
            $amb = $amb !== false && $amb !== null ? (string) $amb : null;

            $filtroAmbC = '';
            $filtroAmbL = '';
            if ($amb !== null) {
                $filtroAmbC = " AND CAST(c.tipo_ambiente AS VARCHAR) = :amb";
                $filtroAmbL = " AND CAST(l.tipo_ambiente AS VARCHAR) = :amb";
                $params[':amb'] = $amb;
            }

            $sql = "
                WITH pagado AS (
                    SELECT ed.tipo_documento,
                           ed.id_referencia_documento AS id_doc,
                           SUM(ed.monto_pagado)       AS total_pagado
                    FROM egresos_detalle ed
                    INNER JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento IN ('COMPRA','LIQUIDACION')
                      AND ec.estado   != 'anulado'
                      AND ec.eliminado = false
                      AND ed.eliminado = false
                    GROUP BY ed.tipo_documento, ed.id_referencia_documento
                ),
                nc_nd AS (
                    SELECT nc.documento_modificado,
                           SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                           SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
                    FROM compras_cabecera nc
                    WHERE nc.tipo_comprobante IN ('04','05')
                      AND nc.eliminado    = false
                      AND nc.id_empresa   = :id_empresa
                      AND nc.id_proveedor = :id
                    GROUP BY nc.documento_modificado
                ),
                ret AS (
                    SELECT r.id_compra, r.id_liquidacion, SUM(r.total_retenido) AS total_retenido
                    FROM retencion_compra_cabecera r
                    WHERE r.eliminado = false
                      AND UPPER(r.estado) NOT IN ('ANULADO','BORRADOR','PENDIENTE')
                    GROUP BY r.id_compra, r.id_liquidacion
                ),
                docs AS (
                    -- Facturas de compra
                    SELECT c.importe_total                                          AS total,
                           c.importe_total
                             - COALESCE(pg.total_pagado, 0)
                             - COALESCE(rt.total_retenido, 0)
                             - COALESCE(nn.total_nc, 0)
                             + COALESCE(nn.total_nd, 0)                             AS saldo
                    FROM compras_cabecera c
                    LEFT JOIN pagado pg ON pg.tipo_documento = 'COMPRA' AND pg.id_doc = c.id
                    LEFT JOIN nc_nd  nn ON nn.documento_modificado =
                              CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                    LEFT JOIN ret    rt ON rt.id_compra = c.id AND rt.id_liquidacion IS NULL
                    WHERE c.id_empresa       = :id_empresa
                      AND c.id_proveedor     = :id
                      AND c.eliminado        = false
                      AND c.tipo_comprobante = '01'
                      {$filtroAmbC}

                    UNION ALL

                    -- Liquidaciones de compra
                    SELECT l.importe_total                                          AS total,
                           l.importe_total
                             - COALESCE(pg.total_pagado, 0)
                             - COALESCE(rt.total_retenido, 0)                       AS saldo
                    FROM liquidaciones_cabecera l
                    LEFT JOIN pagado pg ON pg.tipo_documento = 'LIQUIDACION' AND pg.id_doc = l.id
                    LEFT JOIN ret    rt ON rt.id_liquidacion = l.id
                    WHERE l.id_empresa   = :id_empresa
                      AND l.id_proveedor = :id
                      AND l.eliminado    = false
                      AND LOWER(COALESCE(l.estado,'')) <> 'anulado'
                      {$filtroAmbL}
                )
                SELECT COALESCE(SUM(total), 0)                                   AS total_bruto,
                       COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0) AS saldo
                FROM docs";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            // Neto comprado: al bruto de facturas/liquidaciones se le restan las NC
            // y se le suman las ND del proveedor.
            $sqlNcNd = "SELECT
                            COALESCE(SUM(CASE WHEN c.tipo_comprobante = '04' THEN c.importe_total ELSE 0 END), 0) AS total_nc,
                            COALESCE(SUM(CASE WHEN c.tipo_comprobante = '05' THEN c.importe_total ELSE 0 END), 0) AS total_nd
                        FROM compras_cabecera c
                        WHERE c.id_empresa   = :id_empresa
                          AND c.id_proveedor = :id
                          AND c.eliminado    = false
                          AND c.tipo_comprobante IN ('04','05')
                          {$filtroAmbC}";
            $stN = $this->db->prepare($sqlNcNd);
            $stN->execute($params);
            $rowN = $stN->fetch(PDO::FETCH_ASSOC) ?: ['total_nc' => 0, 'total_nd' => 0];

            $stats['total_compras'] = (float) ($row['total_bruto'] ?? 0)
                                    - (float) ($rowN['total_nc'] ?? 0)
                                    + (float) ($rowN['total_nd'] ?? 0);
            $stats['por_pagar']     = (float) ($row['saldo'] ?? 0);

            // Cantidad de documentos recibidos (compras de cualquier tipo + liquidaciones)
            $sqlDocs = "SELECT
                            (SELECT COUNT(*) FROM compras_cabecera c
                              WHERE c.id_empresa = :id_empresa AND c.id_proveedor = :id
                                AND c.eliminado = false {$filtroAmbC})
                          + (SELECT COUNT(*) FROM liquidaciones_cabecera l
                              WHERE l.id_empresa = :id_empresa AND l.id_proveedor = :id
                                AND l.eliminado = false {$filtroAmbL}) AS docs";
            $stD = $this->db->prepare($sqlDocs);
            $stD->execute($params);
            $stats['documentos_recibidos'] = (int) $stD->fetchColumn();
        } catch (\Throwable $e) {
            // Alguna tabla transaccional aún no existe en esta instalación
        }

        // Saldos iniciales CXP pendientes (tabla independiente, sin ambiente)
        try {
            $sqlSi = "SELECT COALESCE(SUM(CASE WHEN saldo_pendiente > 0 THEN saldo_pendiente ELSE 0 END), 0)
                      FROM saldos_iniciales_cxp
                      WHERE id_empresa = :id_empresa AND id_proveedor = :id AND eliminado = false";
            $stSi = $this->db->prepare($sqlSi);
            $stSi->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
            $stats['por_pagar'] += (float) $stSi->fetchColumn();
        } catch (\Throwable $e) {
            // Módulo de saldos iniciales no instalado
        }

        return $stats;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, razon_social, nombre_comercial, tipo_id_proveedor,
                    identificacion, email, direccion, provincia, ciudad, telefono, tipo_empresa, plazo, unidad_tiempo, relacionado,
                    id_banco, tipo_cta, numero_cta,
                    status, eliminado, created_at, id_forma_pago_predeterminada,
                    monto_minimo_auto_pago, monto_maximo_auto_pago,
                    id_retencion_renta, id_retencion_iva, id_sustento_tributario,
                    tipo_operacion_bancaria_predeterminada, id_egreso_concepto_predeterminado,
                    latitud, longitud, geocodificado_en
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :razon_social, :nombre_comercial, :tipo_id_proveedor,
                    :identificacion, :email, :direccion, :provincia, :ciudad, :telefono, :tipo_empresa, :plazo, :unidad_tiempo, :relacionado,
                    :id_banco, :tipo_cta, :numero_cta,
                    :status, :eliminado, CURRENT_TIMESTAMP, :id_forma_pago_predeterminada,
                    :monto_minimo_auto_pago, :monto_maximo_auto_pago,
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
            ':monto_minimo_auto_pago'       => $data['monto_minimo_auto_pago'] ?? null,
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
                monto_minimo_auto_pago = :monto_minimo_auto_pago,
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
            ':monto_minimo_auto_pago'       => $data['monto_minimo_auto_pago'] ?? null,
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

    /**
     * Revisa todas las tablas que referencian al proveedor y devuelve los módulos
     * donde está siendo usado (solo registros NO eliminados).
     *
     * Respeta la separación de entornos: las tablas con tipo_ambiente solo cuentan
     * documentos del ambiente activo de la empresa; las que no lo tienen se revisan siempre.
     *
     * @return array<string,int> [etiqueta del módulo => cantidad de registros]
     */
    public function getUsosProveedor(int $id, int $idEmpresa): array
    {
        // Ambiente activo de la empresa ('1' pruebas | '2' producción)
        $stA = $this->db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ? LIMIT 1");
        $stA->execute([$idEmpresa]);
        $amb = $stA->fetchColumn();
        $amb = $amb !== false ? (string) $amb : null;

        $usos = [];

        // Documentos transaccionales con tipo_ambiente
        $conAmbiente = [
            'compras_cabecera'          => 'Compras',
            'egresos_cabecera'          => 'Egresos / pagos',
            'liquidaciones_cabecera'    => 'Liquidaciones de compra',
            'ordenes_compra'            => 'Órdenes de compra',
            'retencion_compra_cabecera' => 'Retenciones de compra',
        ];
        foreach ($conAmbiente as $tabla => $etiqueta) {
            $sql    = "SELECT COUNT(*) FROM {$tabla}
                       WHERE id_proveedor = :id AND id_empresa = :ide AND eliminado = false";
            $params = [':id' => $id, ':ide' => $idEmpresa];
            // Filtrar por ambiente activo solo si se conoce (la columna es varchar; comparar como texto)
            if ($amb !== null) {
                $sql .= " AND CAST(tipo_ambiente AS VARCHAR) = :amb";
                $params[':amb'] = $amb;
            }
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $usos[$etiqueta] = $n;
            }
        }

        // Catálogos sin tipo_ambiente: se revisan siempre
        $sinAmbiente = [
            'productos_homologacion' => 'Homologación de productos',
        ];
        foreach ($sinAmbiente as $tabla => $etiqueta) {
            $sql = "SELECT COUNT(*) FROM {$tabla}
                    WHERE id_proveedor = :id AND id_empresa = :ide AND eliminado = false";
            $st = $this->db->prepare($sql);
            $st->execute([':id' => $id, ':ide' => $idEmpresa]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $usos[$etiqueta] = $n;
            }
        }

        return $usos;
    }
}
