<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\models\BaseModel;

class PlantillasPdfRepository extends BaseModel
{
    public function getListado(int $idEmpresa, string $buscar = '', string $tipo = '', int $page = 1, int $perPage = 20): array
    {
        $offset  = ($page - 1) * $perPage;
        $where   = "WHERE p.eliminado = false AND p.id_empresa = {$idEmpresa}";

        if ($tipo !== '') {
            $where .= " AND p.tipo_documento = '" . $this->escape($tipo) . "'";
        }
        if ($buscar !== '') {
            $b      = $this->escape($buscar);
            $where .= " AND (p.nombre ILIKE '%{$b}%' OR p.descripcion ILIKE '%{$b}%')";
        }

        $total = (int)($this->query("SELECT COUNT(*) AS t FROM plantillas_pdf p {$where}")[0]['t'] ?? 0);

        $rows = $this->query(
            "SELECT p.id, p.tipo_documento, p.nombre, p.descripcion, p.es_activa, p.estado,
                    p.created_at, p.updated_at,
                    u.nombre AS creado_por
             FROM plantillas_pdf p
             LEFT JOIN usuarios u ON u.id = p.created_by
             {$where}
             ORDER BY p.tipo_documento ASC, p.nombre ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id): ?array
    {
        $rows = $this->query(
            "SELECT * FROM plantillas_pdf WHERE id = {$id} AND eliminado = false"
        );
        return $rows[0] ?? null;
    }

    public function getActiva(int $idEmpresa, string $tipoDocumento): ?array
    {
        $tipo = $this->escape($tipoDocumento);
        $rows = $this->query(
            "SELECT * FROM plantillas_pdf
             WHERE id_empresa = {$idEmpresa}
               AND tipo_documento = '{$tipo}'
               AND es_activa = true
               AND eliminado = false
             LIMIT 1"
        );
        return $rows[0] ?? null;
    }

    public function crear(array $data): int
    {
        $idEmpresa     = (int)$data['id_empresa'];
        $tipo          = $this->escape($data['tipo_documento'] ?? 'factura_venta');
        $nombre        = $this->escape($data['nombre'] ?? '');
        $descripcion   = $this->escape($data['descripcion'] ?? '');
        $config        = $this->escape($data['configuracion'] ?? '{"pagina":{"formato":"A4","orientacion":"P","margenTop":10,"margenLeft":10,"margenRight":10},"elementos":[]}');
        $createdBy     = (int)($data['created_by'] ?? 0);

        $this->execute(
            "INSERT INTO plantillas_pdf
                (id_empresa, tipo_documento, nombre, descripcion, configuracion, es_activa, estado, created_by, updated_by, created_at, updated_at)
             VALUES
                ({$idEmpresa}, '{$tipo}', '{$nombre}', '{$descripcion}', '{$config}', false, 'borrador', {$createdBy}, {$createdBy}, NOW(), NOW())"
        );
        return (int)$this->lastInsertId('plantillas_pdf_id_seq');
    }

    public function actualizar(int $id, array $data): bool
    {
        $nombre      = $this->escape($data['nombre'] ?? '');
        $descripcion = $this->escape($data['descripcion'] ?? '');
        $tipo        = $this->escape($data['tipo_documento'] ?? 'factura_venta');
        $updatedBy   = (int)($data['updated_by'] ?? 0);

        return $this->execute(
            "UPDATE plantillas_pdf SET
                nombre = '{$nombre}', descripcion = '{$descripcion}', tipo_documento = '{$tipo}',
                updated_by = {$updatedBy}, updated_at = NOW()
             WHERE id = {$id} AND eliminado = false"
        );
    }

    public function guardarDiseno(int $id, string $configuracionJson, int $idUsuario): bool
    {
        $config = $this->escape($configuracionJson);
        return $this->execute(
            "UPDATE plantillas_pdf SET configuracion = '{$config}', updated_by = {$idUsuario}, updated_at = NOW()
             WHERE id = {$id} AND eliminado = false"
        );
    }

    public function activar(int $id, int $idEmpresa, string $tipoDocumento): bool
    {
        $tipo = $this->escape($tipoDocumento);
        $this->execute(
            "UPDATE plantillas_pdf SET es_activa = false
             WHERE id_empresa = {$idEmpresa} AND tipo_documento = '{$tipo}' AND eliminado = false"
        );
        return $this->execute(
            "UPDATE plantillas_pdf SET es_activa = true, estado = 'activo', updated_at = NOW()
             WHERE id = {$id} AND id_empresa = {$idEmpresa} AND eliminado = false"
        );
    }

    public function desactivar(int $id, int $idEmpresa): bool
    {
        return $this->execute(
            "UPDATE plantillas_pdf SET es_activa = false, estado = 'borrador', updated_at = NOW()
             WHERE id = {$id} AND id_empresa = {$idEmpresa} AND eliminado = false"
        );
    }

    public function eliminar(int $id, int $idUsuario): bool
    {
        return $this->execute(
            "UPDATE plantillas_pdf SET eliminado = true, deleted_at = NOW(), deleted_by = {$idUsuario}
             WHERE id = {$id} AND eliminado = false"
        );
    }
}
