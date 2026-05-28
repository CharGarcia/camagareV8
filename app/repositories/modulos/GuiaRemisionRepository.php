<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class GuiaRemisionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('guias_remision_cabecera');
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuario = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE g.id_empresa = :id_empresa AND g.eliminado = false AND g.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        if ($buscar !== '') {
            $where .= " AND (
                CONCAT(g.establecimiento,'-',g.punto_emision,'-',g.secuencial) ILIKE :b
                OR c.nombre ILIKE :b
                OR c.identificacion ILIKE :b
                OR t.nombre ILIKE :b
                OR g.placa ILIKE :b
                OR g.motivo_traslado ILIKE :b
            )";
            $params[':b'] = "%{$buscar}%";
        }

        if ($idUsuario !== null) {
            $where .= " AND g.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $allowedCols = ['id', 'fecha_emision', 'secuencial', 'estado', 'estado_correo',
                        'cliente_nombre', 'transportista_nombre', 'placa', 'motivo_traslado',
                        'fecha_inicio_transporte', 'usuario_nombre'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match($ordenCol) {
            'cliente_nombre'       => 'c.nombre',
            'transportista_nombre' => 't.nombre',
            'usuario_nombre'       => 'u.nombre',
            default                => "g.{$ordenCol}",
        };

        $sqlCount = "SELECT COUNT(*)
                     FROM guias_remision_cabecera g
                     INNER JOIN clientes      c ON g.id_cliente      = c.id
                     INNER JOIN transportistas t ON g.id_transportista = t.id
                     {$where}";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $sql = "SELECT g.*,
                       c.nombre        AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       t.nombre        AS transportista_nombre,
                       t.identificacion AS transportista_ruc,
                       u.nombre        AS usuario_nombre
                FROM guias_remision_cabecera g
                INNER JOIN clientes       c ON g.id_cliente       = c.id
                INNER JOIN transportistas t ON g.id_transportista = t.id
                LEFT  JOIN usuarios       u ON g.id_usuario       = u.id
                {$where}
                ORDER BY {$ordenExpr} {$ordenDir}
                LIMIT {$perPage} OFFSET {$offset}";

        return ['rows' => $this->query($sql, $params)->fetchAll(), 'total' => (int) $total];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT g.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.direccion      AS cliente_direccion,
                       c.email          AS cliente_email,
                       c.tipo_id        AS cliente_tipo_id,
                       t.nombre         AS transportista_nombre,
                       t.identificacion AS transportista_ruc,
                       t.tipo_id        AS transportista_tipo_id,
                       t.email          AS transportista_email,
                       u.nombre         AS usuario_nombre,
                       uc.nombre        AS creado_por_nombre,
                       uu.nombre        AS actualizado_por_nombre
                FROM guias_remision_cabecera g
                INNER JOIN clientes       c  ON g.id_cliente       = c.id
                INNER JOIN transportistas t  ON g.id_transportista = t.id
                LEFT  JOIN usuarios       u  ON g.id_usuario       = u.id
                LEFT  JOIN usuarios       uc ON g.created_by       = uc.id
                LEFT  JOIN usuarios       uu ON g.updated_by       = uu.id
                WHERE g.id = ? AND g.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    public function getDetalles(int $idGuia): array
    {
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) AS producto_nombre,
                       p.codigo AS producto_codigo
                FROM guias_remision_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                WHERE d.id_guia_remision = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idGuia])->fetchAll();
    }

    public function getInfoAdicional(int $idGuia): array
    {
        return $this->query("SELECT * FROM guias_remision_adicional WHERE id_guia_remision = ?", [$idGuia])->fetchAll();
    }

    public function insertarCabecera(array $data): int
    {
        $sql = "INSERT INTO guias_remision_cabecera
                    (id_empresa, id_establecimiento, id_punto_emision, id_cliente,
                     id_transportista, id_usuario,
                     fecha_emision, establecimiento, punto_emision, secuencial,
                     clave_acceso, placa,
                     fecha_inicio_transporte, fecha_fin_transporte,
                     direccion_partida, direccion_destino, motivo_traslado, ruta,
                     cod_doc_sustento, num_doc_sustento, num_autorizacion_doc_sustento, fecha_emision_doc_sustento,
                     doc_aduanero_unico, cod_establecimiento_destino,
                     tipo_ambiente, tipo_emision, estado, estado_correo, observaciones,
                     created_by, updated_by)
                VALUES
                    (:id_empresa, :id_establecimiento, :id_punto_emision, :id_cliente,
                     :id_transportista, :id_usuario,
                     :fecha_emision, :establecimiento, :punto_emision, :secuencial,
                     :clave_acceso, :placa,
                     :fecha_inicio_transporte, :fecha_fin_transporte,
                     :direccion_partida, :direccion_destino, :motivo_traslado, :ruta,
                     :cod_doc_sustento, :num_doc_sustento, :num_autorizacion_doc_sustento, :fecha_emision_doc_sustento,
                     :doc_aduanero_unico, :cod_establecimiento_destino,
                     :tipo_ambiente, :tipo_emision, :estado, :estado_correo, :observaciones,
                     :created_by, :updated_by)
                RETURNING id";

        return (int) $this->query($sql, [
            ':id_empresa'                    => $data['id_empresa'],
            ':id_establecimiento'            => $data['id_establecimiento'],
            ':id_punto_emision'              => $data['id_punto_emision'],
            ':id_cliente'                    => $data['id_cliente'],
            ':id_transportista'              => $data['id_transportista'],
            ':id_usuario'                    => $data['id_usuario'],
            ':fecha_emision'                 => $data['fecha_emision'],
            ':establecimiento'               => $data['establecimiento'],
            ':punto_emision'                 => $data['punto_emision'],
            ':secuencial'                    => $data['secuencial'],
            ':clave_acceso'                  => $data['clave_acceso'] ?? null,
            ':placa'                         => $data['placa'],
            ':fecha_inicio_transporte'       => $data['fecha_inicio_transporte'],
            ':fecha_fin_transporte'          => $data['fecha_fin_transporte'],
            ':direccion_partida'             => $data['direccion_partida'],
            ':direccion_destino'             => $data['direccion_destino'],
            ':motivo_traslado'               => $data['motivo_traslado'],
            ':ruta'                          => $data['ruta'] ?? null,
            ':cod_doc_sustento'              => $data['cod_doc_sustento'] ?? null,
            ':num_doc_sustento'              => $data['num_doc_sustento'] ?? null,
            ':num_autorizacion_doc_sustento' => $data['num_autorizacion_doc_sustento'] ?? null,
            ':fecha_emision_doc_sustento'    => $data['fecha_emision_doc_sustento'] ?? null,
            ':doc_aduanero_unico'            => $data['doc_aduanero_unico'] ?? null,
            ':cod_establecimiento_destino'   => $data['cod_establecimiento_destino'] ?? null,
            ':tipo_ambiente'                 => $data['tipo_ambiente'] ?? '1',
            ':tipo_emision'                  => $data['tipo_emision'] ?? '1',
            ':estado'                        => $data['estado'] ?? 'borrador',
            ':estado_correo'                 => $data['estado_correo'] ?? 'pendiente',
            ':observaciones'                 => $data['observaciones'] ?? null,
            ':created_by'                    => $data['id_usuario'],
            ':updated_by'                    => $data['id_usuario'],
        ])->fetchColumn();
    }

    public function actualizarCabecera(int $id, array $data): void
    {
        $sql = "UPDATE guias_remision_cabecera SET
                    id_cliente = :id_cliente, id_transportista = :id_transportista,
                    fecha_emision = :fecha_emision, establecimiento = :establecimiento,
                    punto_emision = :punto_emision, secuencial = :secuencial,
                    clave_acceso = :clave_acceso, placa = :placa,
                    fecha_inicio_transporte = :fecha_inicio_transporte,
                    fecha_fin_transporte = :fecha_fin_transporte,
                    direccion_partida = :direccion_partida, direccion_destino = :direccion_destino,
                    motivo_traslado = :motivo_traslado, ruta = :ruta,
                    cod_doc_sustento = :cod_doc_sustento, num_doc_sustento = :num_doc_sustento,
                    num_autorizacion_doc_sustento = :num_autorizacion_doc_sustento,
                    fecha_emision_doc_sustento = :fecha_emision_doc_sustento,
                    doc_aduanero_unico = :doc_aduanero_unico,
                    cod_establecimiento_destino = :cod_establecimiento_destino,
                    observaciones = :observaciones,
                    updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";

        $this->query($sql, [
            ':id_cliente'                    => $data['id_cliente'],
            ':id_transportista'              => $data['id_transportista'],
            ':fecha_emision'                 => $data['fecha_emision'],
            ':establecimiento'               => $data['establecimiento'],
            ':punto_emision'                 => $data['punto_emision'],
            ':secuencial'                    => $data['secuencial'],
            ':clave_acceso'                  => $data['clave_acceso'] ?? null,
            ':placa'                         => $data['placa'],
            ':fecha_inicio_transporte'       => $data['fecha_inicio_transporte'],
            ':fecha_fin_transporte'          => $data['fecha_fin_transporte'],
            ':direccion_partida'             => $data['direccion_partida'],
            ':direccion_destino'             => $data['direccion_destino'],
            ':motivo_traslado'               => $data['motivo_traslado'],
            ':ruta'                          => $data['ruta'] ?? null,
            ':cod_doc_sustento'              => $data['cod_doc_sustento'] ?? null,
            ':num_doc_sustento'              => $data['num_doc_sustento'] ?? null,
            ':num_autorizacion_doc_sustento' => $data['num_autorizacion_doc_sustento'] ?? null,
            ':fecha_emision_doc_sustento'    => $data['fecha_emision_doc_sustento'] ?? null,
            ':doc_aduanero_unico'            => $data['doc_aduanero_unico'] ?? null,
            ':cod_establecimiento_destino'   => $data['cod_establecimiento_destino'] ?? null,
            ':observaciones'                 => $data['observaciones'] ?? null,
            ':updated_by'                    => $data['id_usuario'],
            ':id'                            => $id,
            ':id_empresa'                    => $data['id_empresa'],
        ]);
    }

    public function insertarDetalle(int $idGuia, array $detalle): int
    {
        $sql = "INSERT INTO guias_remision_detalle
                    (id_guia_remision, id_producto, codigo_principal, codigo_auxiliar, descripcion, cantidad)
                VALUES (?, ?, ?, ?, ?, ?) RETURNING id";
        return (int) $this->query($sql, [
            $idGuia,
            $detalle['id_producto'] ?? null,
            $detalle['codigo_principal'] ?? null,
            $detalle['codigo_auxiliar']  ?? null,
            $detalle['descripcion'],
            (float) ($detalle['cantidad'] ?? 1),
        ])->fetchColumn();
    }

    public function eliminarDetalles(int $idGuia): void
    {
        $this->query("DELETE FROM guias_remision_detalle WHERE id_guia_remision = ?", [$idGuia]);
    }

    public function insertarAdicional(int $idGuia, string $nombre, string $valor): void
    {
        $this->query(
            "INSERT INTO guias_remision_adicional (id_guia_remision, nombre, valor) VALUES (?, ?, ?)",
            [$idGuia, $nombre, $valor]
        );
    }

    public function eliminarAdicionales(int $idGuia): void
    {
        $this->query("DELETE FROM guias_remision_adicional WHERE id_guia_remision = ?", [$idGuia]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $this->query(
            "UPDATE guias_remision_cabecera SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$estado, $idUsuario, $id]
        );
    }

    public function actualizarSri(int $id, string $estado, ?string $fechaAut, ?string $xmlAut, ?string $errores, int $idUsuario): void
    {
        $sql = "UPDATE guias_remision_cabecera
                SET estado = ?, fecha_autorizacion = ?, numero_autorizacion = ?,
                    updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $numAut = $xmlAut !== null ? (string)$id : null;
        $this->query($sql, [$estado, $fechaAut, $numAut, $idUsuario, $id]);
    }

    public function actualizarEstadoSriCompleto(
        int $id, string $estado, ?string $fechaAut, ?string $numAut, int $idUsuario
    ): void {
        $sql = "UPDATE guias_remision_cabecera
                SET estado = ?, fecha_autorizacion = ?, numero_autorizacion = ?,
                    updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $this->query($sql, [$estado, $fechaAut, $numAut, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE guias_remision_cabecera
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?,
                    updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM guias_remision_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE"
             . ($excluirId !== null ? " AND id <> ?" : "");
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];
        if ($excluirId !== null) $params[] = $excluirId;
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }
    public function getPorDocumentoSustento(string $numero, int $idEmpresa): array
    {
        $sql = "SELECT g.*, t.nombre AS transportista_nombre
                FROM guias_remision_cabecera g
                INNER JOIN transportistas t ON g.id_transportista = t.id
                WHERE g.num_doc_sustento = ? AND g.id_empresa = ? AND g.eliminado = FALSE
                ORDER BY g.fecha_emision DESC";
        return $this->query($sql, [$numero, $idEmpresa])->fetchAll();
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    public function updateDetalleXml(int $id, string $xml): void
    {
        try {
            $this->db->exec("ALTER TABLE guias_remision_cabecera ADD COLUMN IF NOT EXISTS detalle_xml TEXT;");
        } catch (\Throwable) {}

        $st = $this->db->prepare(
            "UPDATE guias_remision_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }
}
