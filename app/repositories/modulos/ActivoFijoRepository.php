<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class ActivoFijoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('activos_fijos');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_adquisicion',
        string $ordenDir = 'DESC',
        ?int $idUsuario = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE a.id_empresa = :id_empresa AND a.eliminado = false";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (a.nombre ILIKE :buscar OR a.codigo ILIKE :buscar OR cat.nombre ILIKE :buscar
                          OR p.razon_social ILIKE :buscar OR a.proveedor_texto ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['nombre' => 'a.nombre', 'codigo' => 'a.codigo', 'categoria' => 'cat.nombre', 'proveedor' => 'p.razon_social'],
            'exacto'   => ['estado' => 'a.estado', 'origen' => 'a.origen'],
            'fecha'    => ['fecha' => 'a.fecha_adquisicion', 'fecha_adquisicion' => 'a.fecha_adquisicion'],
            'numerico' => ['valor' => 'a.valor_adquisicion', 'monto' => 'a.valor_adquisicion'],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND a.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuario;
        }

        $sqlCount = "SELECT COUNT(*)
                     FROM activos_fijos a
                     INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
                     LEFT  JOIN proveedores p ON a.id_proveedor = p.id
                     $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'codigo', 'nombre', 'fecha_adquisicion', 'valor_adquisicion', 'valor_en_libros', 'depreciacion_acumulada', 'estado', 'categoria_nombre'];
        if (!in_array($ordenCol, $allowedCols, true)) $ordenCol = 'fecha_adquisicion';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $ordenExpr = $ordenCol === 'categoria_nombre' ? 'cat.nombre' : "a.$ordenCol";

        $sql = "SELECT a.*,
                       cat.nombre AS categoria_nombre,
                       cat.porcentaje_depreciacion_anual AS categoria_porcentaje,
                       p.razon_social AS proveedor_nombre
                FROM activos_fijos a
                INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
                LEFT  JOIN proveedores p ON a.id_proveedor = p.id
                $where
                ORDER BY $ordenExpr $ordenDir
                " . ($perPage > 0 ? "LIMIT $perPage OFFSET $offset" : "");

        $rows = $this->query($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT a.*,
                       cat.nombre AS categoria_nombre,
                       cat.porcentaje_depreciacion_anual AS categoria_porcentaje,
                       p.razon_social AS proveedor_nombre
                FROM activos_fijos a
                INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
                LEFT  JOIN proveedores p ON a.id_proveedor = p.id
                WHERE a.id = :id AND a.id_empresa = :id_empresa AND a.eliminado = false";
        $row = $this->query($sql, [':id' => $id, ':id_empresa' => $idEmpresa])->fetch();
        return $row ?: null;
    }

    public function compraDetalleVinculado(int $idCompraDetalle): bool
    {
        return (bool) $this->query(
            "SELECT 1 FROM activos_fijos WHERE id_compra_detalle = ? AND eliminado = false LIMIT 1",
            [$idCompraDetalle]
        )->fetchColumn();
    }

    public function tieneDepreciacionesGeneradas(int $idActivo): bool
    {
        return (bool) $this->query(
            "SELECT 1 FROM activos_fijos_depreciaciones WHERE id_activo = ? AND eliminado = false LIMIT 1",
            [$idActivo]
        )->fetchColumn();
    }

    public function insert(array $data): int
    {
        $sql = "INSERT INTO activos_fijos (
                    id_empresa, id_categoria, codigo, nombre, descripcion,
                    origen, id_compra, id_compra_detalle, id_proveedor, proveedor_texto,
                    fecha_adquisicion, fecha_inicio_depreciacion,
                    valor_adquisicion, valor_residual, porcentaje_depreciacion_anual, valor_depreciable, meses_vida_util,
                    depreciacion_acumulada, valor_en_libros, estado,
                    id_cuenta_contrapartida_alta, observaciones, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id";

        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            (int) $data['id_categoria'],
            $data['codigo'] ?? null,
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['origen'],
            !empty($data['id_compra']) ? (int) $data['id_compra'] : null,
            !empty($data['id_compra_detalle']) ? (int) $data['id_compra_detalle'] : null,
            !empty($data['id_proveedor']) ? (int) $data['id_proveedor'] : null,
            $data['proveedor_texto'] ?? null,
            $data['fecha_adquisicion'],
            $data['fecha_inicio_depreciacion'],
            (float) $data['valor_adquisicion'],
            (float) $data['valor_residual'],
            (float) $data['porcentaje_depreciacion_anual'],
            (float) $data['valor_depreciable'],
            (int) $data['meses_vida_util'],
            (float) ($data['depreciacion_acumulada'] ?? 0),
            (float) $data['valor_en_libros'],
            $data['estado'] ?? 'activo',
            !empty($data['id_cuenta_contrapartida_alta']) ? (int) $data['id_cuenta_contrapartida_alta'] : null,
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    /**
     * Categoría, valor de adquisición y fecha quedan fijos desde la creación (afectan el
     * cálculo de depreciación); aquí solo se tocan campos descriptivos + valor_residual
     * (y su valor_depreciable derivado), usado cuando el activo AÚN no tiene depreciaciones.
     */
    public function update(int $id, array $data): void
    {
        $sql = "UPDATE activos_fijos SET
                    codigo = ?, nombre = ?, descripcion = ?,
                    valor_residual = ?, valor_depreciable = ?, id_cuenta_contrapartida_alta = ?, observaciones = ?,
                    updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";

        $this->query($sql, [
            $data['codigo'] ?? null,
            $data['nombre'],
            $data['descripcion'] ?? null,
            (float) $data['valor_residual'],
            (float) $data['valor_depreciable'],
            !empty($data['id_cuenta_contrapartida_alta']) ? (int) $data['id_cuenta_contrapartida_alta'] : null,
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            $id,
            (int) $data['id_empresa'],
        ]);
    }

    /**
     * Solo campos descriptivos (sin tocar montos ni fechas): usado cuando el activo
     * ya tiene depreciaciones generadas y esos valores quedan inmutables.
     */
    public function updateDescriptivo(int $id, array $data): void
    {
        $sql = "UPDATE activos_fijos SET
                    codigo = ?, nombre = ?, descripcion = ?, observaciones = ?,
                    updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";

        $this->query($sql, [
            $data['codigo'] ?? null,
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['observaciones'] ?? null,
            (int) $data['id_usuario'],
            $id,
            (int) $data['id_empresa'],
        ]);
    }

    public function softDelete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE activos_fijos SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND id_empresa = ?",
            [$idUsuario, $id, $idEmpresa]
        );
    }

    public function setAsientoAlta(int $id, int $idAsiento): void
    {
        $this->query("UPDATE activos_fijos SET id_asiento_alta = ? WHERE id = ?", [$idAsiento, $id]);
    }

    /**
     * Activos elegibles para depreciar en el período indicado:
     * activos, con % > 0, cuyo inicio de depreciación ya llegó, y que aún no
     * agotan su valor depreciable.
     */
    public function getActivosDepreciables(int $idEmpresa, int $anio, int $mes): array
    {
        $finPeriodo = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));

        $sql = "SELECT a.*, cat.nombre AS categoria_nombre,
                       cat.id_cuenta_gasto_depreciacion, cat.id_cuenta_depreciacion_acumulada
                FROM activos_fijos a
                INNER JOIN activos_fijos_categorias cat ON a.id_categoria = cat.id
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado = 'activo'
                  AND a.porcentaje_depreciacion_anual > 0
                  AND a.fecha_inicio_depreciacion <= :fin_periodo
                  AND a.depreciacion_acumulada < a.valor_depreciable
                ORDER BY cat.nombre ASC, a.nombre ASC";

        return $this->query($sql, [':id_empresa' => $idEmpresa, ':fin_periodo' => $finPeriodo])->fetchAll();
    }

    public function actualizarTrasDepreciacion(int $id, float $nuevoAcumulado, float $nuevoValorLibros, string $nuevoEstado): void
    {
        $this->query(
            "UPDATE activos_fijos SET depreciacion_acumulada = ?, valor_en_libros = ?, estado = ?, updated_at = NOW() WHERE id = ?",
            [$nuevoAcumulado, $nuevoValorLibros, $nuevoEstado, $id]
        );
    }

    public function getHistorialDepreciaciones(int $idActivo): array
    {
        return $this->query(
            "SELECT d.* FROM activos_fijos_depreciaciones d
             WHERE d.id_activo = ? AND d.eliminado = false
             ORDER BY d.periodo_anio ASC, d.periodo_mes ASC",
            [$idActivo]
        )->fetchAll();
    }
}
