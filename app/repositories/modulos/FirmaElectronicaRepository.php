<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class FirmaElectronicaRepository extends BaseRepository
{
    protected string $table = 'firmas_electronicas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $whereSql = $this->getBaseWhere($idEmpresa, 'f', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (f.nombres ILIKE :b OR f.apellidos ILIKE :b OR f.numero_identificacion ILIKE :b OR f.correo ILIKE :b OR f.nombre_producto ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $cols = [
            'nombres'              => 'f.nombres',
            'apellidos'            => 'f.apellidos',
            'numero_identificacion'=> 'f.numero_identificacion',
            'estado'               => 'f.estado',
            'estado_pago'          => 'f.estado_pago',
            'created_at'           => 'f.created_at',
            'nombre_producto'      => 'f.nombre_producto',
            'telefono'             => 'f.telefono',
            'correo'               => 'f.correo',
            'fecha_caducidad'      => 'f.fecha_caducidad',
            'tipo_persona'         => 'f.tipo_persona',
        ];
        $col = $cols[$ordenCol] ?? 'f.created_at';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} f {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT f.id, f.tipo_persona, f.con_ruc,
                           f.tipo_identificacion, f.numero_identificacion, f.codigo_dactilar,
                           f.nombres, f.apellidos, f.fecha_nacimiento, f.sexo, f.nacionalidad,
                           f.telefono, f.correo, f.direccion,
                           f.cod_prov, f.cod_ciudad,
                           f.id_producto, f.nombre_producto,
                           f.ruc_empresa, f.nombre_empresa, f.cargo,
                           f.tipo_pago, f.estado, f.estado_pago, f.fecha_caducidad,
                           f.observaciones, f.created_at, f.updated_at,
                           f.facturacion_mismos_datos, f.facturacion_tipo_id, f.facturacion_num_id,
                           f.facturacion_nombres, f.facturacion_direccion, f.facturacion_correo,
                           f.facturacion_telefono, f.id_factura,
                           p.nombre AS provincia_nombre, c.nombre AS ciudad_nombre,
                           v.estado AS factura_estado, v.eliminado AS factura_eliminada,
                           v.establecimiento AS factura_establecimiento,
                           v.punto_emision   AS factura_punto_emision,
                           v.secuencial      AS factura_secuencial,
                           v.importe_total   AS factura_importe_total
                    FROM {$this->table} f
                    LEFT JOIN provincia       p ON p.codigo = f.cod_prov
                    LEFT JOIN ciudad          c ON c.codigo = f.cod_ciudad AND c.cod_prov = f.cod_prov
                    LEFT JOIN ventas_cabecera v ON v.id = f.id_factura
                    {$whereSql}
                    ORDER BY {$col} {$dir}";
        if ($perPage > 0) {
            $sqlRows .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT f.*,
                       p.nombre  AS provincia_nombre,
                       c.nombre  AS ciudad_nombre,
                       u_c.nombre AS creado_por_nombre,
                       u_u.nombre AS actualizado_por_nombre,
                       v.estado        AS factura_estado,
                       v.eliminado     AS factura_eliminada,
                       v.establecimiento AS factura_establecimiento,
                       v.punto_emision   AS factura_punto_emision,
                       v.secuencial      AS factura_secuencial,
                       v.importe_total   AS factura_importe_total,
                       v.fecha_emision   AS factura_fecha_emision
                FROM {$this->table} f
                LEFT JOIN provincia       p   ON p.codigo   = f.cod_prov
                LEFT JOIN ciudad          c   ON c.codigo   = f.cod_ciudad AND c.cod_prov = f.cod_prov
                LEFT JOIN usuarios        u_c ON u_c.id     = f.created_by
                LEFT JOIN usuarios        u_u ON u_u.id     = f.updated_by
                LEFT JOIN ventas_cabecera v   ON v.id       = f.id_factura
                WHERE f.id = :id AND f.id_empresa = :id_empresa AND f.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by,
                    id_producto, nombre_producto,
                    tipo_persona, con_ruc, ruc_empresa, nombre_empresa, cargo,
                    tipo_identificacion, numero_identificacion, codigo_dactilar,
                    nombres, apellidos, fecha_nacimiento,
                    telefono, correo, nacionalidad, sexo, direccion,
                    cod_prov, cod_ciudad,
                    tipo_pago, estado_pago, estado, fecha_caducidad, observaciones,
                    facturacion_mismos_datos, facturacion_tipo_id, facturacion_num_id,
                    facturacion_nombres, facturacion_direccion, facturacion_correo, facturacion_telefono,
                    eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by,
                    :id_producto, :nombre_producto,
                    :tipo_persona, :con_ruc, :ruc_empresa, :nombre_empresa, :cargo,
                    :tipo_identificacion, :numero_identificacion, :codigo_dactilar,
                    :nombres, :apellidos, :fecha_nacimiento,
                    :telefono, :correo, :nacionalidad, :sexo, :direccion,
                    :cod_prov, :cod_ciudad,
                    :tipo_pago, :estado_pago, :estado, :fecha_caducidad, :observaciones,
                    :facturacion_mismos_datos, :facturacion_tipo_id, :facturacion_num_id,
                    :facturacion_nombres, :facturacion_direccion, :facturacion_correo, :facturacion_telefono,
                    false, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute($this->mapParams($data));
        return $this->lastInsertId('firmas_electronicas_id_seq');
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_producto = :id_producto, nombre_producto = :nombre_producto,
                    tipo_persona = :tipo_persona, con_ruc = :con_ruc,
                    ruc_empresa = :ruc_empresa, nombre_empresa = :nombre_empresa, cargo = :cargo,
                    tipo_identificacion = :tipo_identificacion,
                    numero_identificacion = :numero_identificacion,
                    codigo_dactilar = :codigo_dactilar,
                    nombres = :nombres, apellidos = :apellidos,
                    fecha_nacimiento = :fecha_nacimiento,
                    telefono = :telefono, correo = :correo,
                    nacionalidad = :nacionalidad, sexo = :sexo, direccion = :direccion,
                    cod_prov = :cod_prov, cod_ciudad = :cod_ciudad,
                    tipo_pago = :tipo_pago, estado_pago = :estado_pago,
                    estado = :estado, fecha_caducidad = :fecha_caducidad,
                    observaciones = :observaciones,
                    facturacion_mismos_datos = :facturacion_mismos_datos,
                    facturacion_tipo_id = :facturacion_tipo_id,
                    facturacion_num_id = :facturacion_num_id,
                    facturacion_nombres = :facturacion_nombres,
                    facturacion_direccion = :facturacion_direccion,
                    facturacion_correo = :facturacion_correo,
                    facturacion_telefono = :facturacion_telefono,
                    updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = $this->mapParams($data);
        unset($params[':id_usuario'], $params[':created_by']);
        $params[':id']         = $id;
        $params[':id_empresa'] = $idEmpresa;
        $params[':updated_by'] = $data['updated_by'] ?? $data['id_usuario'];
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ── Adjuntos ──────────────────────────────────────────────

    public function getAdjuntos(int $idFirma, int $idEmpresa): array
    {
        $sql = "SELECT id, tipo, nombre_original, nombre_archivo, mime_type, tamano_bytes, created_at
                FROM firmas_electronicas_adjuntos
                WHERE id_firma = :id_firma AND id_empresa = :id_empresa AND eliminado = false
                ORDER BY created_at ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_firma' => $idFirma, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAdjunto(array $data): int
    {
        $sql = "INSERT INTO firmas_electronicas_adjuntos
                    (id_firma, id_empresa, tipo, nombre_original, nombre_archivo, ruta_relativa, mime_type, tamano_bytes, created_by)
                VALUES (:id_firma, :id_empresa, :tipo, :nombre_original, :nombre_archivo, :ruta_relativa, :mime_type, :tamano_bytes, :created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_firma'       => $data['id_firma'],
            ':id_empresa'     => $data['id_empresa'],
            ':tipo'           => $data['tipo'],
            ':nombre_original'=> $data['nombre_original'],
            ':nombre_archivo' => $data['nombre_archivo'],
            ':ruta_relativa'  => $data['ruta_relativa'],
            ':mime_type'      => $data['mime_type'],
            ':tamano_bytes'   => $data['tamano_bytes'],
            ':created_by'     => $data['created_by'],
        ]);
        return $this->lastInsertId('firmas_electronicas_adjuntos_id_seq');
    }

    public function getAdjuntoPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM firmas_electronicas_adjuntos WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteAdjunto(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE firmas_electronicas_adjuntos SET
                    eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ── Privado ───────────────────────────────────────────────

    private function mapParams(array $data): array
    {
        $fn = fn($val) => ($val === '' || $val === null) ? null : $val;
        return [
            ':id_empresa'           => $data['id_empresa'],
            ':id_usuario'           => $fn($data['id_usuario'] ?? null),
            ':created_by'           => $fn($data['id_usuario'] ?? null),
            ':id_producto'          => $fn($data['id_producto'] ?? null),
            ':nombre_producto'      => $fn($data['nombre_producto'] ?? null),
            ':tipo_persona'         => $data['tipo_persona'] ?? 'natural',
            ':con_ruc'              => ($data['con_ruc'] ?? false) ? 'true' : 'false',
            ':ruc_empresa'          => $fn($data['ruc_empresa'] ?? null),
            ':nombre_empresa'       => $fn($data['nombre_empresa'] ?? null),
            ':cargo'                => $fn($data['cargo'] ?? null),
            ':tipo_identificacion'  => $data['tipo_identificacion'],
            ':numero_identificacion'=> $data['numero_identificacion'],
            ':codigo_dactilar'      => $fn($data['codigo_dactilar'] ?? null),
            ':nombres'              => mb_strtoupper(trim($data['nombres']), 'UTF-8'),
            ':apellidos'            => mb_strtoupper(trim($data['apellidos']), 'UTF-8'),
            ':fecha_nacimiento'     => $fn($data['fecha_nacimiento'] ?? null),
            ':telefono'             => $fn($data['telefono'] ?? null),
            ':correo'               => $fn($data['correo'] ?? null),
            ':nacionalidad'         => $fn($data['nacionalidad'] ?? null),
            ':sexo'                 => $fn($data['sexo'] ?? null),
            ':direccion'            => $fn($data['direccion'] ?? null),
            ':cod_prov'             => $fn($data['cod_prov'] ?? null),
            ':cod_ciudad'           => $fn($data['cod_ciudad'] ?? null),
            ':tipo_pago'                 => $fn($data['tipo_pago'] ?? null),
            ':estado_pago'               => $data['estado_pago'] ?? 'pendiente',
            ':estado'                    => $data['estado'] ?? 'pendiente',
            ':fecha_caducidad'           => $fn($data['fecha_caducidad'] ?? null),
            ':observaciones'             => $fn($data['observaciones'] ?? null),
            ':facturacion_mismos_datos'  => ($data['facturacion_mismos_datos'] ?? true) ? 'true' : 'false',
            ':facturacion_tipo_id'       => $fn($data['facturacion_tipo_id'] ?? null),
            ':facturacion_num_id'        => $fn($data['facturacion_num_id'] ?? null),
            ':facturacion_nombres'       => $fn($data['facturacion_nombres'] ?? null),
            ':facturacion_direccion'     => $fn($data['facturacion_direccion'] ?? null),
            ':facturacion_correo'        => $fn($data['facturacion_correo'] ?? null),
            ':facturacion_telefono'      => $fn($data['facturacion_telefono'] ?? null),
        ];
    }
}
