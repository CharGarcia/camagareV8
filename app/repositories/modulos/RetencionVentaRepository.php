<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class RetencionVentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('retencion_venta_cabecera');
    }

    // ── Listado paginado ─────────────────────────────────────────

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro
    ): array {
        $colsPermitidas = [
            'fecha_emision', 'secuencial', 'cliente_nombre', 'cliente_ruc',
            'periodo_fiscal', 'total_renta', 'total_iva', 'total_isd', 'origen', 'created_at',
        ];
        if (!in_array($ordenCol, $colsPermitidas, true)) $ordenCol = 'fecha_emision';
        $ordenDir = $ordenDir === 'ASC' ? 'ASC' : 'DESC';

        $mapCols = [
            'cliente_nombre' => 'c.nombre',
            'cliente_ruc'    => 'c.identificacion',
            'secuencial'     => 'r.secuencial',
            'fecha_emision'  => 'r.fecha_emision',
        ];
        $colFinal = $mapCols[$ordenCol] ?? "r.{$ordenCol}";

        $where  = 'r.id_empresa = :ie AND r.eliminado = false AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :ie)';
        $params = [':ie' => $idEmpresa];

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $where .= " AND (
                r.secuencial ILIKE :b
                OR r.clave_acceso ILIKE :b
                OR c.nombre ILIKE :b
                OR c.identificacion ILIKE :b
                OR r.periodo_fiscal ILIKE :b
            )";
            $params[':b'] = '%' . $textoLibre . '%';
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => 'r.secuencial',
                'nro'            => 'r.secuencial',
                'periodo'        => 'r.periodo_fiscal',
                'clave_acceso'   => 'r.clave_acceso',
                'usuario'        => 'u.nombre',
            ],
            'exacto' => [
                'origen' => 'r.origen',
            ],
            'fecha' => [
                'fecha'         => 'r.fecha_emision',
                'fecha_emision' => 'r.fecha_emision',
            ],
            'numerico' => [
                'renta' => 'r.total_renta',
                'iva'   => 'r.total_iva',
                'isd'   => 'r.total_isd',
                'total' => '(r.total_renta + r.total_iva + r.total_isd)',
                'monto' => '(r.total_renta + r.total_iva + r.total_isd)',
            ],
        ]);

        if ($idUsuarioFiltro !== null) {
            $where .= ' AND r.created_by = :iu';
            $params[':iu'] = $idUsuarioFiltro;
        }

        $baseJoin = "FROM retencion_venta_cabecera r
                     LEFT JOIN clientes c  ON c.id = r.id_cliente
                     LEFT JOIN usuarios u  ON u.id = r.created_by
                     WHERE {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$baseJoin}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $limit  = $perPage > 0 ? "LIMIT {$perPage} OFFSET {$offset}" : '';

        $sql = "SELECT
                    r.id, r.fecha_emision, r.establecimiento, r.punto_emision, r.secuencial,
                    r.clave_acceso, r.periodo_fiscal,
                    r.total_renta, r.total_iva, r.total_isd,
                    (r.total_renta + r.total_iva + r.total_isd) AS total_retenido,
                    r.origen, r.created_at, r.updated_at,
                    c.nombre AS cliente_nombre,
                    c.identificacion AS cliente_ruc,
                    u.nombre AS usuario_nombre
                {$baseJoin}
                ORDER BY {$colFinal} {$ordenDir}
                {$limit}";

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    // ── Obtener por ID ───────────────────────────────────────────

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT
                    r.*,
                    (r.total_renta + r.total_iva + r.total_isd) AS total_retenido,
                    c.nombre      AS cliente_nombre,
                    c.identificacion    AS cliente_identificacion,
                    c.tipo_id AS cliente_tipo_id,
                    c.direccion         AS cliente_direccion,
                    c.email             AS cliente_email,
                    c.telefono          AS cliente_telefono,
                    uc.nombre           AS creado_por_nombre,
                    uu.nombre           AS actualizado_por_nombre
                FROM retencion_venta_cabecera r
                LEFT JOIN clientes  c   ON c.id  = r.id_cliente
                LEFT JOIN usuarios  uc  ON uc.id = r.created_by
                LEFT JOIN usuarios  uu  ON uu.id = r.updated_by
                WHERE r.id = :id AND r.id_empresa = :ie AND r.eliminado = false";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Detalle (líneas) ─────────────────────────────────────────

    public function getDetalle(int $idRetencion): array
    {
        $sql = "SELECT d.*,
                    rs.concepto_ret AS sri_concepto,
                    rs.porcentaje_ret AS sri_porcentaje,
                    rs.impuesto_ret AS sri_tipo
                FROM retencion_venta_detalle d
                LEFT JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                WHERE d.id_retencion = :ir
                ORDER BY d.id";

        $st = $this->db->prepare($sql);
        $st->execute([':ir' => $idRetencion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Insertar cabecera ────────────────────────────────────────

    public function insertCabecera(array $d): int
    {
        $sql = "INSERT INTO retencion_venta_cabecera (
                    id_empresa, id_cliente, id_venta,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    periodo_fiscal,
                    total_isd, total_iva, total_renta,
                    origen, detalle_xml,
                    created_by, updated_by
                ) VALUES (
                    :ie, :ic, :iv,
                    :fe, :estab, :pto, :sec, :ca,
                    :pf,
                    :tisd, :tiva, :trenta,
                    :orig, :dxml,
                    :cb, :ub
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':ie'    => $d['id_empresa'],
            ':ic'    => $d['id_cliente'],
            ':iv'    => !empty($d['id_venta'])   ? $d['id_venta']   : null,
            ':fe'    => $d['fecha_emision'],
            ':estab' => $d['establecimiento'] ?? '001',
            ':pto'   => $d['punto_emision']   ?? '001',
            ':sec'   => $d['secuencial'],
            ':ca'    => !empty($d['clave_acceso']) ? $d['clave_acceso'] : null,
            ':pf'    => $d['periodo_fiscal'],
            ':tisd'  => $d['total_isd']   ?? 0,
            ':tiva'  => $d['total_iva']   ?? 0,
            ':trenta'=> $d['total_renta'] ?? 0,
            ':orig'  => $d['origen']      ?? 'manual',
            ':dxml'  => $d['detalle_xml'] ?? null,
            ':cb'    => $d['id_usuario'],
            ':ub'    => $d['id_usuario'],
        ]);

        return (int) $st->fetchColumn();
    }

    // ── Actualizar cabecera ──────────────────────────────────────

    public function updateCabecera(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE retencion_venta_cabecera SET
                    id_cliente      = :ic,
                    id_venta        = :iv,
                    fecha_emision   = :fe,
                    establecimiento = :estab,
                    punto_emision   = :pto,
                    secuencial      = :sec,
                    clave_acceso    = :ca,
                    periodo_fiscal  = :pf,
                    total_isd       = :tisd,
                    total_iva       = :tiva,
                    total_renta     = :trenta,
                    updated_by      = :ub,
                    updated_at      = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :ie AND eliminado = false";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':ic'    => $d['id_cliente'],
            ':iv'    => !empty($d['id_venta']) ? $d['id_venta'] : null,
            ':fe'    => $d['fecha_emision'],
            ':estab' => $d['establecimiento'] ?? '001',
            ':pto'   => $d['punto_emision']   ?? '001',
            ':sec'   => $d['secuencial'],
            ':ca'    => !empty($d['clave_acceso']) ? $d['clave_acceso'] : null,
            ':pf'    => $d['periodo_fiscal'],
            ':tisd'  => $d['total_isd']   ?? 0,
            ':tiva'  => $d['total_iva']   ?? 0,
            ':trenta'=> $d['total_renta'] ?? 0,
            ':ub'    => $d['id_usuario'],
            ':id'    => $id,
            ':ie'    => $idEmpresa,
        ]);

        return $st->rowCount() > 0;
    }

    // ── Insertar línea de detalle ────────────────────────────────

    public function insertDetalle(array $d): void
    {
        $sql = "INSERT INTO retencion_venta_detalle (
                    id_retencion,
                    cod_doc_sustento, num_doc_sustento, fecha_emision_doc_sustento,
                    codigo_impuesto, codigo_retencion,
                    base_imponible, porcentaje_retencion, valor_retenido
                ) VALUES (
                    :ir,
                    :cds, :nds, :feds,
                    :ci, :cr,
                    :bi, :pr, :vr
                )";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':ir'   => $d['id_retencion'],
            ':cds'  => $d['cod_doc_sustento'],
            ':nds'  => $d['num_doc_sustento'] ?? null,
            ':feds' => $d['fecha_emision_doc_sustento'],
            ':ci'   => $d['codigo_impuesto'],
            ':cr'   => $d['codigo_retencion'],
            ':bi'   => $d['base_imponible'],
            ':pr'   => $d['porcentaje_retencion'],
            ':vr'   => $d['valor_retenido'] ?? 0,
        ]);
    }

    // ── Eliminar detalle ─────────────────────────────────────────

    public function deleteDetalle(int $idRetencion): void
    {
        $this->db->prepare("DELETE FROM retencion_venta_detalle WHERE id_retencion = :ir")
                 ->execute([':ir' => $idRetencion]);
    }

    // ── Eliminación lógica ───────────────────────────────────────

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE retencion_venta_cabecera
             SET eliminado = true,
                 deleted_at = CURRENT_TIMESTAMP,
                 deleted_by = :du,
                 updated_at = CURRENT_TIMESTAMP,
                 updated_by = :ub
             WHERE id = :id AND id_empresa = :ie AND eliminado = false"
        );
        $st->execute([
            ':du' => $idUsuario,
            ':ub' => $idUsuario,
            ':id' => $id,
            ':ie' => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    // ── Verificar si ya existe por clave de acceso ───────────────

    public function existeClaveAcceso(string $clave, int $idEmpresa, ?int $excluirId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM retencion_venta_cabecera WHERE clave_acceso = :ca AND id_empresa = :ie AND eliminado = false";
        $params = [':ca' => $clave, ':ie' => $idEmpresa];
        if ($excluirId !== null) {
            $sql           .= ' AND id <> :eid';
            $params[':eid'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    // ── Verificar duplicado por número (estab-pto-sec del cliente) ─

    public function existeNumero(
        int $idEmpresa,
        string $establecimiento,
        string $puntoEmision,
        string $secuencial,
        int $idCliente,
        ?int $excluirId = null
    ): bool {
        $sql    = "SELECT COUNT(*) FROM retencion_venta_cabecera
                   WHERE id_empresa = :ie AND establecimiento = :estab AND punto_emision = :pto
                     AND secuencial = :sec AND id_cliente = :ic AND eliminado = false";
        $params = [
            ':ie'    => $idEmpresa,
            ':estab' => $establecimiento,
            ':pto'   => $puntoEmision,
            ':sec'   => $secuencial,
            ':ic'    => $idCliente,
        ];
        if ($excluirId !== null) {
            $sql           .= ' AND id <> :eid';
            $params[':eid'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    // ── Catálogo retenciones SRI ─────────────────────────────────

    public function getRetencionesSri(?string $tipo = null, ?string $buscar = null, ?string $fecha = null): array
    {
        $sql    = "SELECT id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret
                   FROM retenciones_sri WHERE status = 1";
        $params = [];
        if ($tipo !== null) {
            $sql          .= ' AND impuesto_ret = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($buscar !== null) {
            $sql          .= ' AND (codigo_ret ILIKE :b OR concepto_ret ILIKE :b)';
            $params[':b']  = '%' . $buscar . '%';
        }
        if ($fecha !== null) {
            $sql .= ' AND (desde IS NULL OR desde <= :f) AND (hasta IS NULL OR hasta >= :f)';
            $params[':f'] = $fecha;
        }
        $sql .= ' ORDER BY impuesto_ret, codigo_ret::text';

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Buscar ventas disponibles ────────────────────────────────

    public function buscarVentasDisponibles(int $idEmpresa, string $buscar): array
    {
        $sql = "SELECT
                    v.id,
                    v.tipo_comprobante,
                    COALESCE(v.establecimiento,'') || '-' || COALESCE(v.punto_emision,'') || '-' || COALESCE(v.secuencial,'') AS num_comprobante,
                    v.fecha_emision,
                    c.nombre AS cliente_nombre,
                    c.identificacion AS cliente_ruc,
                    v.importe_total,
                    v.numero_autorizacion,
                    '01' AS tipo_doc_sri
                FROM ventas_cabecera v
                LEFT JOIN clientes c ON c.id = v.id_cliente
                WHERE v.id_empresa = :ie
                  AND v.eliminado = false
                  AND v.estado NOT IN ('anulado', 'anulada')
                  AND (
                      c.nombre ILIKE :b
                      OR c.identificacion ILIKE :b
                      OR v.secuencial ILIKE :b
                      OR v.numero_autorizacion ILIKE :b
                  )
                ORDER BY v.fecha_emision DESC
                LIMIT 20";

        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':b' => '%' . $buscar . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Retenciones de una venta ─────────────────────────────────

    public function getPorVenta(int $idVenta, int $idEmpresa): array
    {
        $sql = "SELECT r.id, r.establecimiento, r.punto_emision, r.secuencial,
                       r.fecha_emision, r.total_renta, r.total_iva, r.total_isd,
                       (r.total_renta + r.total_iva + r.total_isd) AS total_retenido,
                       r.origen,
                       c.nombre AS cliente_nombre
                FROM retencion_venta_cabecera r
                LEFT JOIN clientes c ON c.id = r.id_cliente
                WHERE r.id_venta = :id AND r.id_empresa = :ie AND r.eliminado = false
                ORDER BY r.fecha_emision DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta, ':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getComprobantesAutorizados(): array
    {
        return $this->db->query("SELECT codigo_comprobante, comprobante 
                                FROM comprobantes_autorizados 
                                WHERE status = 1
                                ORDER BY codigo_comprobante")->fetchAll(PDO::FETCH_ASSOC);
    }
}
