<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class BodegaRepository extends BaseRepository
{
    /**
     * Whitelist + mapa del ORDER BY (OrdenListado). Las columnas de saldo salen del
     * subquery `s` (saldosPorBodegaSql) y la del establecimiento del JOIN `est`.
     */
    public const MAPA_ORDEN = [
        'nombre'          => 'b.nombre',
        'status'          => 'b.status',
        'establecimiento' => 'est.codigo',
        'productos'       => 'COALESCE(s.productos, 0)',
        'unidades'        => 'COALESCE(s.unidades, 0)',
        'valor_costo'     => 'COALESCE(s.valor_costo, 0)',
        'valor_venta'     => 'COALESCE(s.valor_venta, 0)',
    ];

    public function __construct()
    {
        parent::__construct('bodegas');
    }

    /**
     * Saldo de cada bodega de la empresa, a partir del kardex (no del caché
     * productos_bodegas.stock_actual, que puede desincronizarse):
     *  - productos:   productos con saldo distinto de cero.
     *  - unidades:    suma de las cantidades en existencia.
     *  - valor_costo: saldo × último costo del kardex (si no hay, el costo de la ficha),
     *                 el mismo criterio que la pestaña Existencias del Reporte de Inventarios.
     *  - valor_venta: saldo × precio de venta de la ficha (precio_base, sin IVA).
     * Es un listado operativo: sigue el ambiente SRI actual de la empresa, igual que
     * el stock que ven Facturas, Transferencias e Inventario (getStockActual()).
     * Último costo con MAX(ARRAY[fecha, id, costo])[3]: ver
     * ReporteInventarioRepository::baseExistencias().
     */
    private function saldosPorBodegaSql(string $filtroBodega = ''): string
    {
        return "SELECT x.id_bodega,
                       COUNT(*) AS productos,
                       SUM(x.stock) AS unidades,
                       SUM(x.stock * COALESCE(NULLIF(x.costo, 0), p.costo_producto, 0)) AS valor_costo,
                       SUM(x.stock * COALESCE(p.precio_base, 0)) AS valor_venta
                  FROM (
                        SELECT k.id_producto, k.id_bodega, SUM(k.cantidad) AS stock,
                               (MAX(ARRAY[EXTRACT(EPOCH FROM k.fecha_movimiento), k.id, k.costo_unitario]))[3] AS costo
                          FROM inventario_kardex k
                         WHERE k.id_empresa = :id_empresa AND k.eliminado = false
                           AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                           {$filtroBodega}
                         GROUP BY k.id_producto, k.id_bodega
                       ) x
                 INNER JOIN productos p ON p.id = x.id_producto AND p.id_empresa = :id_empresa
                 WHERE ROUND(x.stock, 4) <> 0
                 GROUP BY x.id_bodega";
    }

    /** JOIN del establecimiento (solo si la columna ya existe en la base). */
    private function joinEstablecimiento(): string
    {
        return $this->tieneColumnaEstablecimiento()
            ? "LEFT JOIN empresa_establecimiento est ON est.id = b.id_establecimiento AND est.id_empresa = b.id_empresa"
            : "LEFT JOIN (SELECT NULL::integer AS id, NULL::text AS codigo, NULL::text AS nombre) est ON false";
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null,
        array $ordenMulti = []
    ): array {
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'b.nombre', 'b.id DESC');

        $whereSql = $this->getBaseWhere($idEmpresa, 'b', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // El COUNT y las filas usan exactamente los mismos JOIN: los filtros de saldo
        // (con stock, rangos de unidades/valores) dependen del subquery `s`.
        $joins = $this->joinEstablecimiento() . "
                 LEFT JOIN (" . $this->saldosPorBodegaSql() . ") s ON s.id_bodega = b.id";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto([
                'b.nombre',
                'est.codigo',
                'est.nombre',
            ], $parsed['texto_libre'], $params, 'bod_b');
            if ($cond !== '') {
                $whereSql .= ' AND ' . $cond;
            }
        }

        // Estado: columna booleana (NULL = Activo, como lo pinta la tabla).
        if (isset($parsed['filtros']['estado'])) {
            $val = $parsed['filtros']['estado']['valor'];
            $val = mb_strtolower(trim(is_array($val) ? (string) ($val[0] ?? '') : (string) $val), 'UTF-8');
            $lit = in_array($val, ['activo', 'si', '1', 'true', 't'], true) ? true
                 : (in_array($val, ['inactivo', 'no', '0', 'false', 'f'], true) ? false : null);
            if ($lit !== null) {
                $esTrue = $lit !== (bool) $parsed['filtros']['estado']['neg'];
                $whereSql .= ' AND COALESCE(b.status, true) = ' . ($esTrue ? 'true' : 'false');
            }
            unset($parsed['filtros']['estado']);
        }

        $exactos = [
            'usuario'   => 'b.created_by',
            'con_stock' => "CASE WHEN COALESCE(s.productos, 0) > 0 THEN 'si' ELSE 'no' END",
        ];
        if ($this->tieneColumnaEstablecimiento()) {
            $exactos['id_establecimiento'] = 'b.id_establecimiento';
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto'    => [
                'nombre'          => 'b.nombre',
                'bodega'          => 'b.nombre',
                'establecimiento' => 'est.nombre',
            ],
            'exacto'   => $exactos,
            'fecha'    => [
                'registro'   => 'b.created_at',
                'created_at' => 'b.created_at',
            ],
            'numerico' => [
                'productos'   => 'COALESCE(s.productos, 0)',
                'unidades'    => 'COALESCE(s.unidades, 0)',
                'valor_costo' => 'COALESCE(s.valor_costo, 0)',
                'valor_venta' => 'COALESCE(s.valor_venta, 0)',
            ],
        ]);

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} b {$joins} {$whereSql}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;

        // Los totales (SUM ... OVER ()) se calculan sobre TODAS las bodegas filtradas,
        // antes del LIMIT: el pie de la tabla muestra el total general, no el de la página.
        $sqlRows = "SELECT b.*,
                           est.codigo AS establecimiento_codigo,
                           est.nombre AS establecimiento_nombre,
                           COALESCE(s.productos, 0)   AS productos,
                           COALESCE(s.unidades, 0)    AS unidades,
                           COALESCE(s.valor_costo, 0) AS valor_costo,
                           COALESCE(s.valor_venta, 0) AS valor_venta,
                           SUM(COALESCE(s.unidades, 0))    OVER () AS total_unidades,
                           SUM(COALESCE(s.valor_costo, 0)) OVER () AS total_valor_costo,
                           SUM(COALESCE(s.valor_venta, 0)) OVER () AS total_valor_venta
                    FROM {$this->table} b
                    {$joins}
                    {$whereSql}
                    {$orderBy}";

        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        $totales = [
            'unidades'    => (float) ($rows[0]['total_unidades'] ?? 0),
            'valor_costo' => (float) ($rows[0]['total_valor_costo'] ?? 0),
            'valor_venta' => (float) ($rows[0]['total_valor_venta'] ?? 0),
        ];
        foreach ($rows as &$r) {
            unset($r['total_unidades'], $r['total_valor_costo'], $r['total_valor_venta']);
        }
        unset($r);

        return [
            'total'   => $total,
            'rows'    => $rows,
            'totales' => $totales,
        ];
    }

    /** Saldo de una sola bodega (ficha del modal). */
    public function getSaldoBodega(int $idBodega, int $idEmpresa): array
    {
        $st = $this->db->prepare($this->saldosPorBodegaSql('AND k.id_bodega = :id_bodega'));
        $st->execute([':id_empresa' => $idEmpresa, ':id_bodega' => $idBodega]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'productos'   => (int) ($r['productos'] ?? 0),
            'unidades'    => (float) ($r['unidades'] ?? 0),
            'valor_costo' => (float) ($r['valor_costo'] ?? 0),
            'valor_venta' => (float) ($r['valor_venta'] ?? 0),
        ];
    }

    /** Usuarios que registraron bodegas en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConBodegas(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT u.id, u.nombre
               FROM bodegas b
               INNER JOIN usuarios u ON u.id = b.created_by
              WHERE b.id_empresa = :id_empresa AND b.eliminado = false
              ORDER BY u.nombre"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ¿La tabla ya tiene la columna id_establecimiento? La agrega la migración
     * de Transferencias de Inventario
     * (database/migrations/20260818_create_transferencias_inventario.sql). Se
     * consulta una sola vez por request (lectura barata, sin locks) para que
     * Bodegas siga funcionando en bases donde ese SQL todavía no se corrió.
     */
    private function tieneColumnaEstablecimiento(): bool
    {
        static $existe = null;
        if ($existe === null) {
            $st = $this->db->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_name = 'bodegas' AND column_name = 'id_establecimiento'"
            );
            $existe = (bool) $st->fetchColumn();
        }
        return $existe;
    }

    public function create(array $data): int
    {
        $conEst = $this->tieneColumnaEstablecimiento();
        $cols   = 'id_empresa, id_usuario, created_by, nombre, status, eliminado, created_at';
        $vals   = ':id_empresa, :id_usuario, :created_by, :nombre, :status, :eliminado, CURRENT_TIMESTAMP';
        if ($conEst) {
            $cols .= ', id_establecimiento';
            $vals .= ', :id_establecimiento';
        }

        $params = [
            ':id_empresa'   => $data['id_empresa'],
            ':id_usuario'   => $data['id_usuario'],
            ':created_by'   => $data['created_by'],
            ':nombre'       => $data['nombre'],
            ':status'       => $data['status'] ? 'true' : 'false',
            ':eliminado'    => 'false'
        ];
        if ($conEst) {
            $params[':id_establecimiento'] = !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null;
        }

        $st = $this->db->prepare("INSERT INTO {$this->table} ({$cols}) VALUES ({$vals})");
        $st->execute($params);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $conEst = $this->tieneColumnaEstablecimiento();
        $setEst = $conEst ? ', id_establecimiento = :id_establecimiento' : '';

        $sql = "UPDATE {$this->table} SET
                nombre = :nombre,
                status = :status,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                {$setEst}
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";

        $params = [
            ':nombre'     => $data['nombre'],
            ':status'     => $data['status'] ? 'true' : 'false',
            ':updated_by' => $data['updated_by'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ];
        if ($conEst) {
            $params[':id_establecimiento'] = !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null;
        }

        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT b.*,
                       u1.nombre AS creado_por_nombre,
                       u2.nombre AS actualizado_por_nombre
                FROM {$this->table} b
                LEFT JOIN usuarios u1 ON u1.id = b.created_by
                LEFT JOIN usuarios u2 ON u2.id = b.updated_by
                WHERE b.id = :id AND b.id_empresa = :id_empresa AND b.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function contarProductos(int $idBodega, int $idEmpresa): int
    {
        // Esta consulta atrapará excepciones en caso de que la tabla intermedia o inventario
        // aún no exista, retornando 0 para no quebrar el entorno hasta que se consolide el Kardex.
        try {
            $sql = "SELECT COUNT(DISTINCT id_producto) FROM productos_bodegas 
                    WHERE id_bodega = :id_bodega AND id_empresa = :id_empresa";
            $st = $this->db->prepare($sql);
            $st->execute([':id_bodega' => $idBodega, ':id_empresa' => $idEmpresa]);
            return (int) $st->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
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
     * Cuenta en cuántos registros de negocio se usa la bodega (para impedir eliminarla si tiene
     * datos). Revisa TODAS las tablas que referencian id_bodega (documentos, kardex, stock, etc.);
     * NO cuenta `usuarios_bodegas`, que es solo asignación de acceso. Devuelve [etiqueta => cantidad]
     * solo de las que tienen registros (vacío = se puede eliminar). Los nombres de tabla son fijos
     * del código, no entran del usuario.
     *
     * @return array<string,int>
     */
    public function usosBodega(int $id): array
    {
        $tablas = [
            'ventas_detalle'                    => 'facturas de venta',
            'inventario_kardex'                 => 'movimientos de inventario (kardex)',
            'productos_bodegas'                 => 'stock de productos',
            'recibos_venta_detalle'             => 'recibos de venta',
            'cambios_producto_cv_detalles'      => 'cambios de producto',
            'consignaciones_ventas_detalles'    => 'consignaciones',
            'consignaciones_facturas_detalles'  => 'facturación de consignación',
            'retornos_cv_detalles'              => 'retornos',
            'saldos_iniciales_consignaciones'   => 'saldos iniciales de consignación',
            'importaciones_detalle'             => 'importaciones',
            'inventario_cargas_detalle'         => 'cargas de inventario',
            'carwash_ordenes'                   => 'órdenes de lavado',
            'carwash_ordenes_detalle'           => 'detalle de órdenes de lavado',
        ];
        $usos = [];
        foreach ($tablas as $tabla => $label) {
            $st = $this->db->prepare("SELECT EXISTS (SELECT 1 FROM {$tabla} WHERE id_bodega = :id)");
            $st->execute([':id' => $id]);
            if ((bool) $st->fetchColumn()) {
                $stc = $this->db->prepare("SELECT COUNT(*) FROM {$tabla} WHERE id_bodega = :id");
                $stc->execute([':id' => $id]);
                $usos[$label] = (int) $stc->fetchColumn();
            }
        }
        return $usos;
    }

    /**
     * Obtiene todos los usuarios de la empresa y cruza con sus permisos de acceso a una bodega.
     */
    public function getUsuariosAcceso(int $idBodega, int $idEmpresa): array
    {
        // El acceso es por defecto: solo NO lo tiene quien esté marcado como
        // denegado explícitamente (modelo "lista de denegados").
        $sql = "SELECT u.id, u.nombre, u.mail,
                       (CASE WHEN COALESCE(ub.denegado, false) = true THEN false ELSE true END) AS tiene_acceso,
                       COALESCE(ub.es_default, false) AS es_default
                FROM usuarios u
                INNER JOIN empresa_asignada ea ON u.id = ea.id_usuario
                LEFT JOIN usuarios_bodegas ub ON u.id = ub.id_usuario AND ub.id_bodega = :id_bodega
                     AND ub.id_empresa = :id_empresa AND ub.eliminado = false
                WHERE ea.id_empresa = :id_empresa AND u.eliminado = false
                  AND COALESCE(u.nivel, 1) < 3
                ORDER BY u.nombre ASC";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_bodega'  => $idBodega,
            ':id_empresa' => $idEmpresa
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sincroniza los accesos de usuarios a una bodega específica.
     */
    public function saveUsuariosAcceso(int $idBodega, int $idEmpresa, array $accesos, int $idUsuarioLogueado): void
    {
        // Modelo "lista de denegados": el acceso a las bodegas es por defecto.
        // La UI envía SOLO los usuarios marcados (con acceso), así que hay que
        // registrar explícitamente como denegados a los usuarios de la empresa
        // que NO vinieron en la lista.
        $concedidos = [];
        $defaults   = [];
        foreach ($accesos as $acc) {
            if (empty($acc['id_usuario'])) continue;
            $uid = (int)$acc['id_usuario'];
            $concedidos[$uid] = true;
            $defaults[$uid]   = !empty($acc['es_default']);
        }

        // Universo de usuarios de la empresa (debe coincidir con getUsuariosAcceso:
        // el superadmin no se lista ni se gestiona aquí, siempre ve todas las bodegas).
        $stUsers = $this->db->prepare(
            "SELECT DISTINCT u.id
               FROM usuarios u
               INNER JOIN empresa_asignada ea ON u.id = ea.id_usuario
              WHERE ea.id_empresa = :id_e AND u.eliminado = false
                AND COALESCE(u.nivel, 1) < 3"
        );
        $stUsers->execute([':id_e' => $idEmpresa]);
        $todosUsuarios = $stUsers->fetchAll(PDO::FETCH_COLUMN);

        $sqlUpsert = "INSERT INTO usuarios_bodegas
                          (id_empresa, id_usuario, id_bodega, es_default, denegado, created_by, updated_by, eliminado)
                      VALUES (:id_e, :id_u, :id_b, :def, :den, :creBy, :creBy, false)
                      ON CONFLICT (id_empresa, id_usuario, id_bodega)
                      DO UPDATE SET
                        es_default = EXCLUDED.es_default,
                        denegado   = EXCLUDED.denegado,
                        eliminado  = false,
                        deleted_at = NULL,
                        deleted_by = NULL,
                        updated_by = EXCLUDED.updated_by,
                        updated_at = CURRENT_TIMESTAMP";

        $stUpsert = $this->db->prepare($sqlUpsert);
        foreach ($todosUsuarios as $uid) {
            $uid         = (int)$uid;
            $tieneAcceso = isset($concedidos[$uid]);
            $stUpsert->execute([
                ':id_e'  => $idEmpresa,
                ':id_u'  => $uid,
                ':id_b'  => $idBodega,
                ':def'   => ($tieneAcceso && !empty($defaults[$uid])) ? 'true' : 'false',
                ':den'   => $tieneAcceso ? 'false' : 'true',
                ':creBy' => $idUsuarioLogueado
            ]);
        }
    }

    /**
     * Limpia la marca de 'es_default' para un usuario en todas las bodegas de una empresa.
     * Útil antes de asignar un nuevo default.
     */
    public function clearDefaultForUser(int $idUsuario, int $idEmpresa): void
    {
        $sql = "UPDATE usuarios_bodegas SET es_default = false 
                WHERE id_usuario = :u AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':e' => $idEmpresa]);
    }

    /**
     * Retorna las bodegas permitidas para un usuario según su nivel y empresa.
     */
    /** Todas las bodegas activas de la empresa, sin mirar los accesos del usuario. */
    public function getBodegasEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT b.id, b.nombre
                                    FROM bodegas b
                                   WHERE b.id_empresa = :id_e AND b.status = true AND b.eliminado = false
                                   ORDER BY b.nombre ASC");
        $st->execute([':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBodegasPermitidas(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        if ($nivel >= 2) {
            // Admin y SuperAdmin ven todas
            $sql = "SELECT b.id, b.nombre, 
                           COALESCE(ub.es_default, false) as es_default
                    FROM bodegas b
                    LEFT JOIN usuarios_bodegas ub ON b.id = ub.id_bodega 
                         AND ub.id_usuario = :id_u AND ub.id_empresa = :id_e AND ub.eliminado = false
                    WHERE b.id_empresa = :id_e AND b.status = true AND b.eliminado = false
                    ORDER BY b.nombre ASC";
        } else {
            // Usuarios Nivel 1: por defecto ven TODAS las bodegas de la empresa.
            // Solo se excluyen aquellas con el acceso revocado explícitamente
            // (usuarios_bodegas.denegado = true). Sin registro = con acceso.
            $sql = "SELECT b.id, b.nombre,
                           COALESCE(ub.es_default, false) as es_default
                    FROM bodegas b
                    LEFT JOIN usuarios_bodegas ub ON b.id = ub.id_bodega
                         AND ub.id_usuario = :id_u AND ub.id_empresa = :id_e AND ub.eliminado = false
                    WHERE b.id_empresa = :id_e AND b.status = true AND b.eliminado = false
                      AND COALESCE(ub.denegado, false) = false
                    ORDER BY b.nombre ASC";
        }

        $st = $this->db->prepare($sql);
        $st->execute([':id_u' => $idUsuario, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ids de las bodegas con el acceso REVOCADO explícitamente para un usuario
     * (Bodegas → pestaña "Accesos": usuarios_bodegas.denegado = true).
     *
     * Es la cara negativa de getBodegasPermitidas() y expresa el modelo tal como está
     * en la base desde database/usuarios_bodegas_denegado.sql: sin registro = con
     * acceso. Por eso lo normal es que devuelva [] y quien filtra un listado por
     * bodega no tenga que añadir nada a su consulta (ni perder el plan que ya tenía);
     * solo los usuarios a los que se les quitó alguna bodega generan condición.
     * Niveles 2 y 3 (administrador y superadministrador) ven todas las bodegas.
     */
    public function getIdsBodegasDenegadas(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        if ($nivel >= 2) {
            return [];
        }

        $sql = "SELECT ub.id_bodega
                  FROM usuarios_bodegas ub
                 WHERE ub.id_usuario = :id_u AND ub.id_empresa = :id_e
                   AND ub.eliminado = false AND ub.denegado = true";
        $st = $this->db->prepare($sql);
        $st->execute([':id_u' => $idUsuario, ':id_e' => $idEmpresa]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
