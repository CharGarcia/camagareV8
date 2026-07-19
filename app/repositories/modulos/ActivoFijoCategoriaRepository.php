<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class ActivoFijoCategoriaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('activos_fijos_categorias');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(int $idEmpresa, string $buscar = ''): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'c');
        $params = [':id_empresa' => $idEmpresa];

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND c.nombre ILIKE :buscar";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => ['nombre' => 'c.nombre'],
            'exacto' => ['estado' => 'c.estado'],
        ]);

        $sql = "SELECT c.*,
                       pa.codigo AS cuenta_activo_codigo, pa.nombre AS cuenta_activo_nombre,
                       pd.codigo AS cuenta_dep_acum_codigo, pd.nombre AS cuenta_dep_acum_nombre,
                       pg.codigo AS cuenta_gasto_codigo, pg.nombre AS cuenta_gasto_nombre,
                       (SELECT COUNT(*) FROM activos_fijos af WHERE af.id_categoria = c.id AND af.eliminado = false) AS cantidad_activos
                FROM activos_fijos_categorias c
                LEFT JOIN plan_cuentas pa ON pa.id = c.id_cuenta_activo
                LEFT JOIN plan_cuentas pd ON pd.id = c.id_cuenta_depreciacion_acumulada
                LEFT JOIN plan_cuentas pg ON pg.id = c.id_cuenta_gasto_depreciacion
                $where
                ORDER BY c.nombre ASC";

        return $this->query($sql, $params)->fetchAll();
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM activos_fijos_categorias WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $id, ':id_empresa' => $idEmpresa]
        )->fetch();
        return $row ?: null;
    }

    public function getActivasParaSelect(int $idEmpresa): array
    {
        return $this->query(
            "SELECT id, nombre, porcentaje_depreciacion_anual, id_cuenta_activo, id_cuenta_depreciacion_acumulada, id_cuenta_gasto_depreciacion
             FROM activos_fijos_categorias
             WHERE id_empresa = :id_empresa AND eliminado = false AND estado = true
             ORDER BY nombre ASC",
            [':id_empresa' => $idEmpresa]
        )->fetchAll();
    }

    public function tieneActivosVinculados(int $idCategoria): bool
    {
        $st = $this->query(
            "SELECT 1 FROM activos_fijos WHERE id_categoria = ? AND eliminado = false LIMIT 1",
            [$idCategoria]
        );
        return (bool) $st->fetchColumn();
    }

    public function nombreExiste(int $idEmpresa, string $nombre, ?int $idExcluir = null): bool
    {
        $sql = "SELECT 1 FROM activos_fijos_categorias WHERE id_empresa = ? AND eliminado = false AND LOWER(nombre) = LOWER(?)";
        $params = [$idEmpresa, $nombre];
        if ($idExcluir !== null) {
            $sql .= " AND id <> ?";
            $params[] = $idExcluir;
        }
        return (bool) $this->query($sql, $params)->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO activos_fijos_categorias (
                    id_empresa, nombre, porcentaje_depreciacion_anual,
                    id_cuenta_activo, id_cuenta_depreciacion_acumulada, id_cuenta_gasto_depreciacion,
                    estado, observaciones, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";

        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            $data['nombre'],
            (float) $data['porcentaje_depreciacion_anual'],
            (int) $data['id_cuenta_activo'],
            (int) $data['id_cuenta_depreciacion_acumulada'],
            (int) $data['id_cuenta_gasto_depreciacion'],
            !empty($data['estado']) ? 'true' : 'false',
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $sql = "UPDATE activos_fijos_categorias SET
                    nombre = ?, porcentaje_depreciacion_anual = ?,
                    id_cuenta_activo = ?, id_cuenta_depreciacion_acumulada = ?, id_cuenta_gasto_depreciacion = ?,
                    estado = ?, observaciones = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";

        $this->query($sql, [
            $data['nombre'],
            (float) $data['porcentaje_depreciacion_anual'],
            (int) $data['id_cuenta_activo'],
            (int) $data['id_cuenta_depreciacion_acumulada'],
            (int) $data['id_cuenta_gasto_depreciacion'],
            !empty($data['estado']) ? 'true' : 'false',
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            $id,
            (int) $data['id_empresa'],
        ]);
    }

    public function softDelete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE activos_fijos_categorias SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND id_empresa = ?",
            [$idUsuario, $id, $idEmpresa]
        );
    }
}
