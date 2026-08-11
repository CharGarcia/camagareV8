<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo "Reasignar establecimiento": lista documentos por filtros y
 * reasigna su id_establecimiento (atribución interna a una sucursal propia). NO cambia
 * el número del documento (serie del proveedor/cliente). Solo toca el documento
 * seleccionado — sin cascada a pagos, contabilidad, retenciones de compra ni inventario.
 */
class ReasignarEstablecimientoRepository extends BaseRepository
{
    /** Config por tipo de documento reasignable. `c` = alias de la cabecera. */
    private const TIPOS = [
        'compras' => [
            'tabla'       => 'compras_cabecera',
            'join'        => 'LEFT JOIN proveedores pr ON pr.id = c.id_proveedor',
            'contraparte' => "COALESCE(NULLIF(pr.razon_social,''), pr.nombre_comercial, '')",
            'ident'       => "COALESCE(pr.identificacion,'')",
            'numero'      => "COALESCE(c.establecimiento_prov,'') || '-' || COALESCE(c.punto_emision_prov,'') || '-' || COALESCE(c.secuencial_prov,'')",
            'tipo_doc'    => 'c.tipo_comprobante',
            'total'       => 'COALESCE(c.importe_total,0)',
        ],
        'retenciones_venta' => [
            'tabla'       => 'retencion_venta_cabecera',
            'join'        => 'LEFT JOIN clientes cl ON cl.id = c.id_cliente',
            'contraparte' => "COALESCE(cl.nombre,'')",
            'ident'       => "COALESCE(cl.identificacion,'')",
            'numero'      => "COALESCE(c.establecimiento,'') || '-' || COALESCE(c.punto_emision,'') || '-' || COALESCE(c.secuencial,'')",
            'tipo_doc'    => "'07'",
            'total'       => "(COALESCE(c.total_isd,0)+COALESCE(c.total_iva,0)+COALESCE(c.total_renta,0))",
        ],
    ];

    public function __construct()
    {
        parent::__construct('compras_cabecera'); // tabla base nominal; el repo consulta ambas por tipo
    }

    public static function tiposValidos(): array
    {
        return array_keys(self::TIPOS);
    }

    /** Asegura la columna id_establecimiento en retencion_venta_cabecera + backfill a la matriz (idempotente). */
    public function asegurarColumnaRetVenta(): void
    {
        $this->db->exec("ALTER TABLE retencion_venta_cabecera ADD COLUMN IF NOT EXISTS id_establecimiento integer");
        $this->db->exec(
            "UPDATE retencion_venta_cabecera r SET id_establecimiento = (
                 SELECT ee.id FROM empresa_establecimiento ee
                  WHERE ee.id_empresa = r.id_empresa AND ee.eliminado = false
                  ORDER BY ee.codigo ASC LIMIT 1)
              WHERE r.id_establecimiento IS NULL"
        );
    }

    /** Establecimientos activos de la empresa (para los selectores origen/destino). */
    public function establecimientos(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, codigo, nombre FROM empresa_establecimiento
              WHERE id_empresa = :e AND eliminado = false AND estado = 'activo'
              ORDER BY codigo ASC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿El establecimiento pertenece a la empresa y está activo? */
    public function establecimientoValido(int $idEmpresa, int $idEstablecimiento): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM empresa_establecimiento WHERE id = :id AND id_empresa = :e AND eliminado = false AND estado = 'activo' LIMIT 1"
        );
        $st->execute([':id' => $idEstablecimiento, ':e' => $idEmpresa]);
        return $st->fetchColumn() !== false;
    }

    /**
     * Lista documentos del tipo dado según filtros. Devuelve ['rows'=>[], 'total'=>int].
     * $filtros: desde, hasta (fecha_emision), id_est_origen (int|0), buscar (texto proveedor/cliente).
     */
    public function listar(int $idEmpresa, string $tipo, array $filtros, ?int $idUsuarioFiltro, int $page = 1, int $perPage = 100): array
    {
        $cfg = self::TIPOS[$tipo];
        [$where, $params] = $this->armarWhere($idEmpresa, $tipo, $filtros, $idUsuarioFiltro);

        $sqlBase = "FROM {$cfg['tabla']} c {$cfg['join']}
                    LEFT JOIN empresa_establecimiento ee ON ee.id = c.id_establecimiento
                    {$where}";

        $total = (int) $this->qOne("SELECT COUNT(*) {$sqlBase}", $params);

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT c.id,
                       to_char(c.fecha_emision, 'DD-MM-YYYY') AS fecha,
                       {$cfg['numero']} AS numero,
                       {$cfg['tipo_doc']} AS tipo_doc,
                       {$cfg['contraparte']} AS contraparte,
                       {$cfg['ident']} AS ident,
                       {$cfg['total']} AS total,
                       c.id_establecimiento,
                       COALESCE(ee.codigo,'—') AS est_codigo,
                       COALESCE(ee.nombre,'(sin establecimiento)') AS est_nombre
                {$sqlBase}
                ORDER BY c.fecha_emision DESC, c.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Años y meses que SÍ tienen documentos del tipo, para la empresa (poblar los selectores).
     * Devuelve filas [anio => 'YYYY', mes => 'MM'] ordenadas año desc, mes asc.
     */
    public function periodos(int $idEmpresa, string $tipo, ?int $idUsuarioFiltro): array
    {
        $cfg = self::TIPOS[$tipo];
        [$where, $params] = $this->armarWhere($idEmpresa, $tipo, [], $idUsuarioFiltro);
        $sql = "SELECT DISTINCT to_char(c.fecha_emision,'YYYY') AS anio, to_char(c.fecha_emision,'MM') AS mes
                FROM {$cfg['tabla']} c
                {$where} AND c.fecha_emision IS NOT NULL
                ORDER BY anio DESC, mes ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Conteo por establecimiento actual (para la vista previa de lo seleccionado por filtro). */
    public function resumenPorEstablecimiento(int $idEmpresa, string $tipo, array $filtros, ?int $idUsuarioFiltro): array
    {
        $cfg = self::TIPOS[$tipo];
        [$where, $params] = $this->armarWhere($idEmpresa, $tipo, $filtros, $idUsuarioFiltro);
        $sql = "SELECT COALESCE(ee.codigo,'—') AS est_codigo, COUNT(*) AS n
                FROM {$cfg['tabla']} c {$cfg['join']}
                LEFT JOIN empresa_establecimiento ee ON ee.id = c.id_establecimiento
                {$where}
                GROUP BY ee.codigo ORDER BY ee.codigo";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** id_establecimiento actual de los documentos indicados (para auditoría antes/después). */
    public function establecimientoActualDe(int $idEmpresa, string $tipo, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) { return []; }
        $cfg = self::TIPOS[$tipo];
        $in  = implode(',', $ids);
        $st  = $this->db->prepare("SELECT id, id_establecimiento FROM {$cfg['tabla']} WHERE id_empresa = :e AND eliminado = false AND id IN ($in)");
        $st->execute([':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int) $r['id']] = $r['id_establecimiento'] === null ? null : (int) $r['id_establecimiento']; }
        return $out;
    }

    /**
     * Reasigna id_establecimiento de los documentos indicados. Devuelve el nº de filas afectadas.
     * Filtra por empresa + eliminado (y created_by si $idUsuarioFiltro). No renumera nada.
     */
    public function reasignar(int $idEmpresa, string $tipo, array $ids, int $idEstDestino, int $idUsuario, ?int $idUsuarioFiltro): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) { return 0; }
        $cfg = self::TIPOS[$tipo];
        $in  = implode(',', $ids);
        $sql = "UPDATE {$cfg['tabla']} SET id_establecimiento = :dest, updated_at = now(), updated_by = :u
                 WHERE id_empresa = :e AND eliminado = false AND id IN ($in)";
        $params = [':dest' => $idEstDestino, ':u' => $idUsuario, ':e' => $idEmpresa];
        if ($idUsuarioFiltro !== null) { $sql .= " AND created_by = :cbf"; $params[':cbf'] = $idUsuarioFiltro; }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /**
     * IDs de compras_detalle de esta compra que ya tienen movimiento de inventario asociado
     * (kardex vivo con referencia_tipo='compra'). Sirve para saber qué revertir antes de
     * reasignar (mismo criterio que ComprasController::eliminarAjax()).
     */
    public function detalleIdsConInventario(int $idCompra, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT d.id
               FROM compras_detalle d
               JOIN inventario_kardex k ON k.referencia_tipo = 'compra' AND k.referencia_id = d.id
              WHERE d.id_compra = :ic AND k.id_empresa = :ie AND k.eliminado = false"
        );
        $st->execute([':ic' => $idCompra, ':ie' => $idEmpresa]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    // ── helpers ──

    private function armarWhere(int $idEmpresa, string $tipo, array $filtros, ?int $idUsuarioFiltro): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) { $params[':id_usuario_filtro'] = $idUsuarioFiltro; }

        if (!empty($filtros['desde'])) { $where .= " AND c.fecha_emision >= :desde"; $params[':desde'] = $filtros['desde']; }
        if (!empty($filtros['hasta'])) { $where .= " AND c.fecha_emision <= :hasta"; $params[':hasta'] = $filtros['hasta'] . ' 23:59:59'; }
        if (!empty($filtros['id_est_origen'])) { $where .= " AND c.id_establecimiento = :esto"; $params[':esto'] = (int) $filtros['id_est_origen']; }
        if (isset($filtros['sin_establecimiento']) && $filtros['sin_establecimiento']) { $where .= " AND c.id_establecimiento IS NULL"; }

        if (!empty($filtros['buscar'])) {
            $cfg = self::TIPOS[$tipo];
            $where .= " AND ({$cfg['contraparte']} ILIKE :q OR {$cfg['ident']} ILIKE :q OR {$cfg['numero']} ILIKE :q)";
            $params[':q'] = '%' . trim((string) $filtros['buscar']) . '%';
        }
        return [$where, $params];
    }

    private function qOne(string $sql, array $params)
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }
}
