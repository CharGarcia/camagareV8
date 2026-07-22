<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CotizacionPublicidadRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('cotizacion_publicidad_cabecera');
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
        string $ordenCol = 'fecha_emision',
        string $ordenDir = 'DESC',
        ?int $idUsuario = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE q.id_empresa = :id_empresa AND q.eliminado = FALSE";

        if ($idUsuario !== null) {
            $where .= " AND q.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (
                c.nombre ILIKE :b
                OR c.identificacion ILIKE :b
                OR q.contacto ILIKE :b
                OR q.proyecto ILIKE :b
                OR q.observaciones ILIKE :b
                OR CAST(q.numero AS TEXT) ILIKE :b
            )";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'       => 'c.nombre',
                'ruc'           => 'c.identificacion',
                'proyecto'      => 'q.proyecto',
                'contacto'      => 'q.contacto',
                'obs'           => 'q.observaciones',
                'observaciones' => 'q.observaciones',
            ],
            'exacto' => [
                'estado' => 'q.estado',
            ],
            'fecha'   => [
                'fecha' => 'q.fecha_emision',
            ],
            'numerico' => [
                'total'       => 'q.importe_total',
                'comision'    => 'q.comision',
                'presupuesto' => 'q.presupuesto',
            ],
        ]);

        $joins = "INNER JOIN clientes c ON q.id_cliente = c.id
                  LEFT JOIN vendedores ven ON q.id_vendedor = ven.id
                  LEFT JOIN usuarios u ON q.id_usuario = u.id";

        $sqlCount = "SELECT COUNT(*) FROM cotizacion_publicidad_cabecera q $joins $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'numero', 'version', 'importe_total', 'estado', 'cliente_nombre', 'vendedor_nombre', 'proyecto', 'comision', 'presupuesto'];
        if (!in_array($ordenCol, $allowedCols)) $ordenCol = 'fecha_emision';
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $ordenExpr = match ($ordenCol) {
            'cliente_nombre'  => 'c.nombre',
            'vendedor_nombre' => 'ven.nombre',
            default           => "q.$ordenCol",
        };

        $sql = "SELECT q.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.email          AS cliente_email,
                       ven.nombre       AS vendedor_nombre,
                       u.nombre         AS usuario_nombre
                FROM cotizacion_publicidad_cabecera q $joins
                $where
                ORDER BY $ordenExpr $ordenDir, q.id DESC
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll();
        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT q.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.direccion      AS cliente_direccion,
                       c.email          AS cliente_email,
                       c.telefono       AS cliente_telefono,
                       ven.nombre       AS vendedor_nombre,
                       u.nombre         AS usuario_nombre
                FROM cotizacion_publicidad_cabecera q
                INNER JOIN clientes c ON q.id_cliente = c.id
                LEFT JOIN vendedores ven ON q.id_vendedor = ven.id
                LEFT JOIN usuarios u ON q.id_usuario = u.id
                WHERE q.id = ? AND q.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    /**
     * Siguiente número para una cotización NUEVA (version=1): MAX(numero)+1
     * filtrado por cliente + año de la fecha de emisión.
     */
    public function siguienteNumero(int $idEmpresa, int $idCliente, int $anio): int
    {
        $sql = "SELECT COALESCE(MAX(numero), 0) + 1
                FROM cotizacion_publicidad_cabecera
                WHERE id_empresa = ? AND id_cliente = ? AND anio = ? AND eliminado = FALSE";
        return (int) $this->query($sql, [$idEmpresa, $idCliente, $anio])->fetchColumn();
    }

    /**
     * Siguiente versión para un numero ya existente (clonar cotización): MAX(version)+1.
     */
    public function siguienteVersion(int $idEmpresa, int $idCliente, int $numero, int $anio): int
    {
        $sql = "SELECT COALESCE(MAX(version), 0) + 1
                FROM cotizacion_publicidad_cabecera
                WHERE id_empresa = ? AND id_cliente = ? AND numero = ? AND anio = ? AND eliminado = FALSE";
        return (int) $this->query($sql, [$idEmpresa, $idCliente, $numero, $anio])->fetchColumn();
    }

    public function existeNumeroVersion(int $idEmpresa, int $idCliente, int $numero, int $version, int $anio, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM cotizacion_publicidad_cabecera
                WHERE id_empresa = ? AND id_cliente = ? AND numero = ? AND version = ? AND anio = ? AND eliminado = FALSE";
        $params = [$idEmpresa, $idCliente, $numero, $version, $anio];
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO cotizacion_publicidad_cabecera (
                    id_empresa, id_cliente, id_vendedor, id_usuario, contacto,
                    fecha_emision, proyecto, numero, version, presupuesto,
                    id_tarifa_iva, comision, observaciones, estado,
                    total_sin_impuestos, total_comision, total_iva, importe_total, moneda,
                    created_by, updated_by
                ) VALUES (
                    ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,  ?,?,?,?,?,  ?,?
                ) RETURNING id";
        return (int) $this->query($sql, [
            (int) $data['id_empresa'],
            (int) $data['id_cliente'],
            !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null,
            (int) $data['id_usuario'],
            !empty($data['contacto']) ? $data['contacto'] : null,
            $data['fecha_emision'],
            !empty($data['proyecto']) ? $data['proyecto'] : null,
            (int) $data['numero'],
            (int) ($data['version'] ?? 1),
            (float) ($data['presupuesto'] ?? 0),
            (int) $data['id_tarifa_iva'],
            (float) ($data['comision'] ?? 0),
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            $data['estado'] ?? 'borrador',
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_comision'] ?? 0),
            (float) ($data['total_iva'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ])->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE cotizacion_publicidad_cabecera SET
                    id_cliente            = ?,
                    id_vendedor           = ?,
                    contacto              = ?,
                    fecha_emision         = ?,
                    proyecto              = ?,
                    presupuesto           = ?,
                    id_tarifa_iva         = ?,
                    comision              = ?,
                    observaciones         = ?,
                    total_sin_impuestos   = ?,
                    total_comision        = ?,
                    total_iva             = ?,
                    importe_total         = ?,
                    updated_by            = ?,
                    updated_at            = NOW()
                WHERE id = ?";
        $this->query($sql, [
            (int) $data['id_cliente'],
            !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null,
            !empty($data['contacto']) ? $data['contacto'] : null,
            $data['fecha_emision'],
            !empty($data['proyecto']) ? $data['proyecto'] : null,
            (float) ($data['presupuesto'] ?? 0),
            (int) $data['id_tarifa_iva'],
            (float) ($data['comision'] ?? 0),
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_comision'] ?? 0),
            (float) ($data['total_iva'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            (int) $data['id_usuario'],
            $id,
        ]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE cotizacion_publicidad_cabecera SET estado = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
        $this->query($sql, [$estado, $idUsuario, $id]);
    }

    public function marcarConvertida(int $id, int $idFactura, int $idUsuario): void
    {
        $sql = "UPDATE cotizacion_publicidad_cabecera
                SET estado = 'convertida', id_factura_convertida = ?, fecha_convertida = NOW(), updated_by = ?, updated_at = NOW()
                WHERE id = ?";
        $this->query($sql, [$idFactura, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE cotizacion_publicidad_cabecera
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->query($sql, [$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function getDetalles(int $idCotizacion): array
    {
        $sql = "SELECT d.*, cat.nombre AS categoria_nombre
                FROM cotizacion_publicidad_detalle d
                LEFT JOIN cotizacion_publicidad_categorias cat ON d.id_categoria = cat.id
                WHERE d.id_cotizacion = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idCotizacion])->fetchAll();
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO cotizacion_publicidad_detalle (
                    id_cotizacion, id_categoria, descripcion, precio_unitario,
                    ciudades, dias, cantidad, precio_total_sin_impuesto
                ) VALUES (?,?,?,?,?,?,?,?) RETURNING id";
        return (int) $this->query($sql, [
            (int) $data['id_cotizacion'],
            !empty($data['id_categoria']) ? (int) $data['id_categoria'] : null,
            $data['descripcion'],
            (float) $data['precio_unitario'],
            (int) ($data['ciudades'] ?? 1),
            (int) ($data['dias'] ?? 1),
            (float) $data['cantidad'],
            (float) $data['precio_total_sin_impuesto'],
        ])->fetchColumn();
    }

    /**
     * Actualiza una línea existente en sitio (conserva su id), para que los
     * costos ya guardados (cotizacion_publicidad_costos.id_detalle) sigan
     * siendo válidos al editar la cotización.
     */
    public function updateDetalle(int $id, array $data): void
    {
        $sql = "UPDATE cotizacion_publicidad_detalle SET
                    id_categoria              = ?,
                    descripcion               = ?,
                    precio_unitario           = ?,
                    ciudades                  = ?,
                    dias                      = ?,
                    cantidad                  = ?,
                    precio_total_sin_impuesto = ?
                WHERE id = ?";
        $this->query($sql, [
            !empty($data['id_categoria']) ? (int) $data['id_categoria'] : null,
            $data['descripcion'],
            (float) $data['precio_unitario'],
            (int) ($data['ciudades'] ?? 1),
            (int) ($data['dias'] ?? 1),
            (float) $data['cantidad'],
            (float) $data['precio_total_sin_impuesto'],
            $id,
        ]);
    }

    /**
     * Elimina líneas de detalle por id (usado cuando el usuario quita ítems al
     * editar). Debe llamarse DESPUÉS de deleteCostosDeDetalles() para esos
     * mismos ids: cotizacion_publicidad_costos.id_detalle referencia esta tabla.
     */
    public function deleteDetallesPorIds(array $ids): void
    {
        if (empty($ids)) return;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->query("DELETE FROM cotizacion_publicidad_detalle WHERE id IN ($ph)", array_values($ids));
    }

    public function deleteCostosDeDetalles(array $idsDetalle): void
    {
        if (empty($idsDetalle)) return;
        $ph = implode(',', array_fill(0, count($idsDetalle), '?'));
        $this->query("DELETE FROM cotizacion_publicidad_costos WHERE id_detalle IN ($ph)", array_values($idsDetalle));
    }

    // ─── Costos por proveedor (pestaña "Costos", N filas por línea cotizada) ─

    /**
     * Costos de todas las líneas de una cotización, agrupables por id_detalle
     * en el front (una línea cotizada puede tener costos de varios proveedores).
     */
    public function getCostosPorCotizacion(int $idCotizacion): array
    {
        $sql = "SELECT co.*, p.razon_social AS proveedor_nombre
                FROM cotizacion_publicidad_costos co
                INNER JOIN cotizacion_publicidad_detalle d ON co.id_detalle = d.id
                LEFT JOIN proveedores p ON co.id_proveedor = p.id
                WHERE d.id_cotizacion = ?
                ORDER BY co.id_detalle ASC, co.id ASC";
        return $this->query($sql, [$idCotizacion])->fetchAll();
    }

    public function deleteCostosPorCotizacion(int $idCotizacion): void
    {
        $this->query(
            "DELETE FROM cotizacion_publicidad_costos
             WHERE id_detalle IN (SELECT id FROM cotizacion_publicidad_detalle WHERE id_cotizacion = ?)",
            [$idCotizacion]
        );
    }

    public function insertCosto(array $data): int
    {
        $sql = "INSERT INTO cotizacion_publicidad_costos (
                    id_detalle, id_proveedor, id_compra, factura_proveedor, valor_costo, observacion_costo
                ) VALUES (?,?,?,?,?,?) RETURNING id";
        return (int) $this->query($sql, [
            (int) $data['id_detalle'],
            !empty($data['id_proveedor']) ? (int) $data['id_proveedor'] : null,
            !empty($data['id_compra']) ? (int) $data['id_compra'] : null,
            !empty($data['factura_proveedor']) ? $data['factura_proveedor'] : null,
            (float) ($data['valor_costo'] ?? 0),
            !empty($data['observacion_costo']) ? $data['observacion_costo'] : null,
        ])->fetchColumn();
    }

    /**
     * IDs de compras (facturas de proveedor) ya vinculadas a costos de esta
     * cotización, para no dejar elegir la misma factura dos veces.
     */
    public function getIdsCompraUsados(int $idCotizacion): array
    {
        $sql = "SELECT co.id_compra
                FROM cotizacion_publicidad_costos co
                INNER JOIN cotizacion_publicidad_detalle d ON co.id_detalle = d.id
                WHERE d.id_cotizacion = ? AND co.id_compra IS NOT NULL";
        return array_map('intval', $this->query($sql, [$idCotizacion])->fetchAll(PDO::FETCH_COLUMN));
    }

    public function getTarifaIva(int $id): ?array
    {
        $sql = "SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE id = ?";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    // ─── Catálogo de categorías del módulo ──────────────────────────────────

    public function getCategorias(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM cotizacion_publicidad_categorias
                WHERE id_empresa = ? AND eliminado = FALSE AND status = TRUE
                ORDER BY nombre ASC";
        return $this->query($sql, [$idEmpresa])->fetchAll();
    }

    public function insertCategoria(int $idEmpresa, string $nombre, int $idUsuario): int
    {
        $sql = "INSERT INTO cotizacion_publicidad_categorias (id_empresa, nombre, created_by, updated_by)
                VALUES (?,?,?,?) RETURNING id";
        return (int) $this->query($sql, [$idEmpresa, $nombre, $idUsuario, $idUsuario])->fetchColumn();
    }

    public function eliminarCategoria(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE cotizacion_publicidad_categorias
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->query($sql, [$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }
}
