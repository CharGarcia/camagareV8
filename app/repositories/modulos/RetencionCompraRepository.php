<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class RetencionCompraRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('retencion_compra_cabecera');
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
            'fecha_emision', 'secuencial', 'proveedor_nombre', 'proveedor_ruc',
            'num_doc_sustento', 'periodo_fiscal', 'total_retenido', 'estado', 'created_at',
        ];
        if (!in_array($ordenCol, $colsPermitidas, true)) $ordenCol = 'fecha_emision';
        $ordenDir = $ordenDir === 'ASC' ? 'ASC' : 'DESC';

        $mapCols = [
            'proveedor_nombre' => 'p.razon_social',
            'proveedor_ruc'    => 'p.identificacion',
            'num_doc_sustento' => 'r.num_doc_sustento',
            'secuencial'       => 'r.secuencial',
            'fecha_emision'    => 'r.fecha_emision',
        ];
        $colFinal = $mapCols[$ordenCol] ?? "r.{$ordenCol}";

        $where  = 'r.id_empresa = :ie AND r.eliminado = false AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :ie)';
        $params = [':ie' => $idEmpresa];

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (
                r.secuencial ILIKE :b
                OR r.num_doc_sustento ILIKE :b
                OR p.razon_social ILIKE :b
                OR p.identificacion ILIKE :b
                OR r.periodo_fiscal ILIKE :b
            )";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => 'r.secuencial',
                'nro'            => 'r.secuencial',
                'doc_sustento'   => 'r.num_doc_sustento',
                'periodo'        => 'r.periodo_fiscal',
                'clave_acceso'   => 'r.clave_acceso',
                'usuario'        => 'u.nombre',
            ],
            'exacto'   => [ 'estado' => 'r.estado' ],
            'fecha'    => [ 'fecha' => 'r.fecha_emision', 'fecha_emision' => 'r.fecha_emision' ],
            'numerico' => [
                'monto' => '(r.total_renta + r.total_iva + r.total_isd)',
                'total' => '(r.total_renta + r.total_iva + r.total_isd)',
                'renta' => 'r.total_renta',
                'iva'   => 'r.total_iva',
                'isd'   => 'r.total_isd',
            ],
        ]);

        if ($idUsuarioFiltro !== null) {
            $where .= ' AND r.id_usuario = :iu';
            $params[':iu'] = $idUsuarioFiltro;
        }

        $baseJoin = "FROM retencion_compra_cabecera r
                     LEFT JOIN proveedores p ON p.id = r.id_proveedor
                     LEFT JOIN usuarios u    ON u.id  = r.id_usuario
                     WHERE {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$baseJoin}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $limit  = $perPage > 0 ? "LIMIT {$perPage} OFFSET {$offset}" : '';

        $sql = "SELECT
                    r.id, r.fecha_emision, r.establecimiento, r.punto_emision, r.secuencial,
                    r.clave_acceso, r.numero_autorizacion, r.fecha_autorizacion,
                    r.tipo_doc_sustento, r.num_doc_sustento, r.fecha_emision_doc_sustento,
                    r.periodo_fiscal, r.total_retenido, r.estado,
                    r.created_at, r.updated_at,
                    p.razon_social AS proveedor_nombre,
                    p.identificacion AS proveedor_ruc,
                    p.tipo_id_proveedor AS proveedor_tipo_id,
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
                    p.razon_social      AS proveedor_razon_social,
                    p.identificacion    AS proveedor_identificacion,
                    p.tipo_id_proveedor AS proveedor_tipo_id,
                    p.direccion         AS proveedor_direccion,
                    p.email             AS proveedor_email,
                    p.telefono          AS proveedor_telefono,
                    uc.nombre           AS creado_por_nombre,
                    uu.nombre           AS actualizado_por_nombre
                FROM retencion_compra_cabecera r
                LEFT JOIN proveedores p  ON p.id  = r.id_proveedor
                LEFT JOIN usuarios   uc  ON uc.id = r.created_by
                LEFT JOIN usuarios   uu  ON uu.id = r.updated_by
                WHERE r.id = :id AND r.id_empresa = :ie AND r.eliminado = false";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPorCompra(int $idCompra, int $idEmpresa, ?int $idLiquidacion = null): array
    {
        $where = "r.id_empresa = :ie AND r.eliminado = false";
        $params = [':ie' => $idEmpresa];

        if ($idLiquidacion !== null && $idLiquidacion > 0) {
            $where .= " AND r.id_liquidacion = :id";
            $params[':id'] = $idLiquidacion;
        } else {
            $where .= " AND r.id_compra = :id";
            $params[':id'] = $idCompra;
        }

        $sql = "SELECT r.id, r.establecimiento, r.punto_emision, r.secuencial, 
                       r.fecha_emision, r.total_retenido, r.estado,
                       p.razon_social AS proveedor_nombre
                FROM retencion_compra_cabecera r
                LEFT JOIN proveedores p ON p.id = r.id_proveedor
                WHERE $where
                ORDER BY r.fecha_emision DESC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Para SRI — sin filtro eliminado
    public function getPorIdSri(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT r.*,
                    p.razon_social      AS proveedor_razon_social,
                    p.identificacion    AS proveedor_identificacion,
                    p.tipo_id_proveedor AS proveedor_tipo_id,
                    p.direccion         AS proveedor_direccion,
                    e.nombre            AS empresa_razon_social,
                    e.ruc               AS empresa_ruc,
                    e.direccion         AS empresa_direccion,
                    e.nombre_comercial  AS empresa_nombre_comercial,
                    e.obligado_contabilidad AS empresa_obligado_contabilidad,
                    e.contribuyente_especial AS empresa_contribuyente_especial,
                    e.tipo_ambiente     AS empresa_tipo_ambiente,
                    ep.codigo_punto     AS punto_codigo,
                    ep.direccion_punto  AS punto_direccion,
                    ee.codigo           AS estab_codigo,
                    ee.direccion        AS estab_direccion
                FROM retencion_compra_cabecera r
                LEFT JOIN proveedores p           ON p.id  = r.id_proveedor
                LEFT JOIN empresas e              ON e.id  = r.id_empresa
                LEFT JOIN empresa_punto_emision ep ON ep.id = r.id_punto_emision
                LEFT JOIN empresa_establecimiento ee ON ee.id = r.id_establecimiento
                WHERE r.id = :id AND r.id_empresa = :ie";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':ie' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Detalle (líneas de retención) ────────────────────────────

    public function getDetalle(int $idRetencion): array
    {
        $sql = "SELECT d.*,
                    rs.concepto_ret AS sri_concepto,
                    rs.porcentaje_ret AS sri_porcentaje,
                    rs.impuesto_ret AS sri_tipo
                FROM retencion_compra_detalle d
                LEFT JOIN retenciones_sri rs ON rs.id = d.id_retencion_sri
                WHERE d.id_retencion = :ir
                ORDER BY d.codigo_impuesto, d.codigo_retencion";

        $st = $this->db->prepare($sql);
        $st->execute([':ir' => $idRetencion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Insertar cabecera ────────────────────────────────────────

    public function insertCabecera(array $d): int
    {
        $sql = "INSERT INTO retencion_compra_cabecera (
                    id_empresa, id_proveedor, id_usuario, id_establecimiento, id_punto_emision,
                    fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso,
                    tipo_ambiente, tipo_emision, periodo_fiscal,
                    tipo_doc_sustento, id_compra, id_liquidacion,
                    num_doc_sustento, fecha_emision_doc_sustento,
                    total_retenido, numero_autorizacion,
                    estado, detalle_xml,
                    created_by, updated_by
                ) VALUES (
                    :ie, :ip, :iu, :iest, :ipt,
                    :fe, :estab, :pto, :sec, :ca,
                    :ta, :tem, :pf,
                    :tds, :idc, :idl,
                    :nds, :feds,
                    :tr, :na,
                    :est, :dxml,
                    :cb, :ub
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':ie'   => $d['id_empresa'],
            ':ip'   => $d['id_proveedor'],
            ':iu'   => !empty($d['id_usuario']) ? $d['id_usuario'] : null,
            ':iest' => !empty($d['id_establecimiento']) ? $d['id_establecimiento'] : null,
            ':ipt'  => !empty($d['id_punto_emision']) ? $d['id_punto_emision'] : null,
            ':fe'   => $d['fecha_emision'],
            ':estab'=> $d['establecimiento'] ?? '001',
            ':pto'  => $d['punto_emision']   ?? '001',
            ':sec'  => $d['secuencial']      ?? null,
            ':ca'   => $d['clave_acceso']    ?? null,
            ':ta'   => $d['tipo_ambiente']   ?? '1',
            ':tem'  => $d['tipo_emision']    ?? '1',
            ':pf'   => $d['periodo_fiscal']  ?? null,
            ':tds'  => $d['tipo_doc_sustento'] ?? '01',
            ':idc'  => !empty($d['id_compra']) ? $d['id_compra'] : null,
            ':idl'  => !empty($d['id_liquidacion']) ? $d['id_liquidacion'] : null,
            ':nds'  => $d['num_doc_sustento']  ?? null,
            ':feds' => !empty($d['fecha_emision_doc_sustento']) ? $d['fecha_emision_doc_sustento'] : null,
            ':tr'   => $d['total_retenido']       ?? 0,
            ':na'   => $d['clave_acceso']         ?? null,
            ':est'  => $d['estado']    ?? 'borrador',
            ':dxml' => $d['detalle_xml'] ?? null,
            ':cb'   => !empty($d['id_usuario']) ? $d['id_usuario'] : null,
            ':ub'   => !empty($d['id_usuario']) ? $d['id_usuario'] : null,
        ]);

        return (int) $st->fetchColumn();
    }

    // ── Actualizar cabecera ──────────────────────────────────────

    public function updateCabecera(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE retencion_compra_cabecera SET
                    id_proveedor               = :ip,
                    id_establecimiento         = :iest,
                    id_punto_emision           = :ipt,
                    fecha_emision              = :fe,
                    establecimiento            = :estab,
                    punto_emision              = :pto,
                    secuencial                 = :sec,
                    clave_acceso               = :ca,
                    tipo_ambiente              = :ta,
                    periodo_fiscal             = :pf,
                    tipo_doc_sustento          = :tds,
                    id_compra                  = :idc,
                    id_liquidacion             = :idl,
                    num_doc_sustento           = :nds,
                    fecha_emision_doc_sustento = :feds,
                    total_retenido             = :tr,
                    numero_autorizacion        = :na,
                    estado                     = :est,
                    updated_by                 = :ub,
                    updated_at                 = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :ie AND eliminado = false";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':ip'   => $d['id_proveedor'],
            ':iest' => !empty($d['id_establecimiento']) ? $d['id_establecimiento'] : null,
            ':ipt'  => !empty($d['id_punto_emision'])   ? $d['id_punto_emision']   : null,
            ':fe'   => $d['fecha_emision'],
            ':estab'=> $d['establecimiento'] ?? '001',
            ':pto'  => $d['punto_emision']   ?? '001',
            ':sec'  => $d['secuencial']      ?? null,
            ':ca'   => $d['clave_acceso']    ?? null,
            ':ta'   => $d['tipo_ambiente']   ?? '1',
            ':pf'   => $d['periodo_fiscal']  ?? null,
            ':tds'  => $d['tipo_doc_sustento'] ?? '01',
            ':idc'  => !empty($d['id_compra']) ? $d['id_compra'] : null,
            ':idl'  => !empty($d['id_liquidacion']) ? $d['id_liquidacion'] : null,
            ':nds'  => $d['num_doc_sustento']  ?? null,
            ':feds' => !empty($d['fecha_emision_doc_sustento']) ? $d['fecha_emision_doc_sustento'] : null,
            ':tr'   => $d['total_retenido']       ?? 0,
            ':na'   => $d['clave_acceso']         ?? null,
            ':est'  => $d['estado'] ?? 'borrador',
            ':ub'   => $d['id_usuario'] ?? null,
            ':id'   => $id,
            ':ie'   => $idEmpresa,
        ]);

        return $st->rowCount() > 0;
    }

    // ── Insertar línea de detalle ────────────────────────────────

    public function insertDetalle(array $d): void
    {
        $sql = "INSERT INTO retencion_compra_detalle (
                    id_empresa, id_retencion,
                    codigo_impuesto, id_retencion_sri, codigo_retencion, concepto,
                    base_imponible, porcentaje_retener, valor_retenido,
                    cod_doc_sustento, num_doc_sustento, fecha_emision_doc_sustento
                ) VALUES (
                    :ie, :ir,
                    :ci, :irs, :cr, :con,
                    :bi, :pr, :vr,
                    :cds, :nds, :feds
                )";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':ie'   => $d['id_empresa'],
            ':ir'   => $d['id_retencion'],
            ':ci'   => $d['codigo_impuesto'],
            ':irs'  => !empty($d['id_retencion_sri']) ? $d['id_retencion_sri'] : null,
            ':cr'   => $d['codigo_retencion'],
            ':con'  => $d['concepto'] ?? null,
            ':bi'   => $d['base_imponible'],
            ':pr'   => $d['porcentaje_retener'],
            ':vr'   => $d['valor_retenido'] ?? 0,
            ':cds'  => $d['cod_doc_sustento'] ?? '01',
            ':nds'  => $d['num_doc_sustento'] ?? null,
            ':feds' => !empty($d['fecha_emision_doc_sustento']) ? $d['fecha_emision_doc_sustento'] : null,
        ]);
    }

    // ── Eliminar detalle ─────────────────────────────────────────

    public function deleteDetalle(int $idRetencion): void
    {
        $this->db->prepare("DELETE FROM retencion_compra_detalle WHERE id_retencion = :ir")
                 ->execute([':ir' => $idRetencion]);
    }

    // ── Eliminación lógica ───────────────────────────────────────

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE retencion_compra_cabecera
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
            ':ie' => $idEmpresa
        ]);
        return $st->rowCount() > 0;
    }

    // ── Actualizar estado SRI ────────────────────────────────────

    public function actualizarEstadoSri(int $id, array $d): void
    {
        $sets   = ['updated_at = CURRENT_TIMESTAMP'];
        $params = [':id' => $id];

        if (array_key_exists('estado', $d)) {
            $sets[]           = 'estado = :est';
            $params[':est']   = $d['estado'];
        }
        if (array_key_exists('numero_autorizacion', $d)) {
            $sets[]                       = 'numero_autorizacion = :na';
            $params[':na']                = $d['numero_autorizacion'];
        }
        if (array_key_exists('fecha_autorizacion', $d)) {
            $sets[]                        = 'fecha_autorizacion = :fa';
            $params[':fa']                 = $d['fecha_autorizacion'];
        }
        if (array_key_exists('xml_autorizado', $d)) {
            $sets[]                  = 'xml_autorizado = :xml';
            $params[':xml']          = $d['xml_autorizado'];
        }
        if (array_key_exists('mensajes_sri', $d)) {
            $sets[]                   = 'mensajes_sri = :msg';
            $params[':msg']           = $d['mensajes_sri'];
        }
        if (array_key_exists('clave_acceso', $d)) {
            $sets[]                   = 'clave_acceso = :ca';
            $params[':ca']            = $d['clave_acceso'];
        }

        $this->db->prepare("UPDATE retencion_compra_cabecera SET " . implode(', ', $sets) . " WHERE id = :id")
                 ->execute($params);
    }

    // ── Verificar unicidad de clave de acceso ────────────────────

    public function existeClaveAcceso(string $clave, ?int $excluirId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM retencion_compra_cabecera WHERE clave_acceso = :ca AND eliminado = false";
        $params = [':ca' => $clave];
        if ($excluirId !== null) {
            $sql          .= ' AND id <> :eid';
            $params[':eid'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    // ── Buscar compras disponibles para retener ──────────────────

    public function buscarComprasDisponibles(int $idEmpresa, string $buscar): array
    {
        $sql = "SELECT
                    c.id,
                    c.tipo_comprobante,
                    COALESCE(c.establecimiento_prov,'') || '-' || COALESCE(c.punto_emision_prov,'') || '-' || COALESCE(c.secuencial_prov,'') AS num_comprobante,
                    c.fecha_emision,
                    p.razon_social AS proveedor_nombre,
                    p.identificacion AS proveedor_ruc,
                    c.importe_total,
                    c.numero_autorizacion,
                    '01' AS tipo_doc_sri
                FROM compras_cabecera c
                LEFT JOIN proveedores p ON p.id = c.id_proveedor
                WHERE c.id_empresa = :ie
                  AND c.eliminado = false
                  AND c.estado NOT IN ('anulado')
                  AND (
                      p.razon_social ILIKE :b
                      OR p.identificacion ILIKE :b
                      OR c.secuencial_prov ILIKE :b
                      OR c.numero_autorizacion ILIKE :b
                  )
                ORDER BY c.fecha_emision DESC
                LIMIT 20";

        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':b' => '%' . $buscar . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Obtener retenciones_sri activas ──────────────────────────

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
            $sql            .= ' AND (codigo_ret ILIKE :b OR concepto_ret ILIKE :b)';
            $params[':b']    = '%' . $buscar . '%';
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

    // ── Verificar si ya existe una retención para un documento de sustento ──

    public function existeRetencionParaDocSustento(
        int $idEmpresa,
        string $tipoDocSustento,
        string $numDocSustento,
        ?int $excluirId = null
    ): bool {
        $sql    = "SELECT COUNT(*) FROM retencion_compra_cabecera
                   WHERE id_empresa = :ie AND tipo_doc_sustento = :tds AND num_doc_sustento = :nds
                     AND eliminado = false AND estado <> 'anulada'";
        $params = [':ie' => $idEmpresa, ':tds' => $tipoDocSustento, ':nds' => $numDocSustento];
        if ($excluirId !== null) {
            $sql            .= ' AND id <> :eid';
            $params[':eid']  = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    // ── Verificar si ya existe una retención para una compra ─────

    public function existeRetencionParaCompra(int $idCompra, int $idEmpresa, ?int $excluirId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM retencion_compra_cabecera
                   WHERE id_compra = :ic AND id_empresa = :ie AND eliminado = false AND estado <> 'anulada'";
        $params = [':ic' => $idCompra, ':ie' => $idEmpresa];
        if ($excluirId !== null) {
            $sql          .= ' AND id <> :eid';
            $params[':eid'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function getRetencionSriPorId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret FROM retenciones_sri WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    public function updateDetalleXml(int $id, string $xml): void
    {
        try {
            $this->db->exec("ALTER TABLE retencion_compra_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable) {}

        $st = $this->db->prepare(
            "UPDATE retencion_compra_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
