<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Repositorio de Cargas de Inventario (Documentos).
 * Documento con cabecera (inventario_cargas) + detalle (inventario_cargas_detalle)
 * que agrupa una carga masiva de movimientos (entrada/salida/ajuste) importada
 * desde Excel/CSV. Afecta el kardex solo al aprobarse.
 */
class CargaInventarioRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['numero', 'fecha', 'tipo_movimiento', 'estado', 'total_lineas', 'created_at'];

    public function __construct()
    {
        parent::__construct('inventario_cargas');
    }

    // ─── LISTADO ──────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'numero';
        }
        $dir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = "WHERE c.id_empresa = :e AND c.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND c.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (CAST(c.numero AS TEXT) ILIKE :b OR c.tipo_movimiento ILIKE :b OR c.estado ILIKE :b OR c.observacion ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'exacto'   => ['estado' => 'c.estado', 'tipo' => 'c.tipo_movimiento'],
            'numerico' => ['numero' => 'c.numero'],
            'fecha'    => ['fecha' => 'c.fecha'],
        ]);

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM inventario_cargas c $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limit = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limit  = "LIMIT $perPage OFFSET $offset";
        }

        $sql = "SELECT c.*,
                       u.nombre AS creado_por_nombre,
                       ua.nombre AS aprobado_por_nombre
                FROM inventario_cargas c
                LEFT JOIN usuarios u  ON u.id = c.created_by
                LEFT JOIN usuarios ua ON ua.id = c.aprobada_por
                $where
                ORDER BY c.$ordenCol $dir
                $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.*, u.nombre AS creado_por_nombre, ua.nombre AS aprobado_por_nombre
             FROM inventario_cargas c
             LEFT JOIN usuarios u  ON u.id = c.created_by
             LEFT JOIN usuarios ua ON ua.id = c.aprobada_por
             WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDetalle(int $idCarga, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT d.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    b.nombre AS bodega_nombre
             FROM inventario_cargas_detalle d
             LEFT JOIN productos p ON p.id = d.id_producto
             LEFT JOIN bodegas   b ON b.id = d.id_bodega
             WHERE d.id_carga = :id AND d.id_empresa = :e AND d.eliminado = false
             ORDER BY d.id ASC"
        );
        $st->execute([':id' => $idCarga, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── ESCRITURA ────────────────────────────────────────────────────────────

    public function siguienteNumero(int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT COALESCE(MAX(numero), 0) + 1 FROM inventario_cargas WHERE id_empresa = :e");
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function crearCabecera(array $d): int
    {
        $sql = "INSERT INTO inventario_cargas
                    (id_empresa, numero, fecha, tipo_movimiento, observacion, estado,
                     validada, errores_validacion, total_lineas, created_by, created_at, eliminado)
                VALUES
                    (:id_empresa, :numero, :fecha, :tipo, :obs, :estado,
                     :validada, :errores, :total, :cb, CURRENT_TIMESTAMP, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $d['id_empresa'],
            ':numero'     => $d['numero'],
            ':fecha'      => $d['fecha'],
            ':tipo'       => $d['tipo_movimiento'],
            ':obs'        => $d['observacion'] ?? null,
            ':estado'     => $d['estado'] ?? 'pendiente',
            ':validada'   => !empty($d['validada']) ? 'true' : 'false',
            ':errores'    => $d['errores_validacion'] ?? null,
            ':total'      => (int) ($d['total_lineas'] ?? 0),
            ':cb'         => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function crearDetalle(array $d): int
    {
        $sql = "INSERT INTO inventario_cargas_detalle
                    (id_carga, id_empresa, id_producto, id_bodega, cantidad, costo_unitario,
                     numero_lote, fecha_caducidad, nup, observacion, linea_valida, error_linea,
                     cod_producto_raw, cod_bodega_raw, created_by, created_at, eliminado)
                VALUES
                    (:id_carga, :id_empresa, :id_producto, :id_bodega, :cantidad, :costo,
                     :lote, :caducidad, :nup, :obs, :valida, :error, :cod_prod, :cod_bod, :cb, CURRENT_TIMESTAMP, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_carga'    => $d['id_carga'],
            ':id_empresa'  => $d['id_empresa'],
            ':id_producto' => $d['id_producto'] ?: null,
            ':id_bodega'   => $d['id_bodega'] ?: null,
            ':cantidad'    => $d['cantidad'] ?? 0,
            ':costo'       => $d['costo_unitario'] ?? 0,
            ':lote'        => $d['numero_lote'] ?? null,
            ':caducidad'   => !empty($d['fecha_caducidad']) ? $d['fecha_caducidad'] : null,
            ':nup'         => $d['nup'] ?? null,
            ':obs'         => $d['observacion'] ?? null,
            ':valida'      => !empty($d['linea_valida']) ? 'true' : 'false',
            ':error'       => $d['error_linea'] ?? null,
            ':cod_prod'    => $d['cod_producto_raw'] ?? null,
            ':cod_bod'     => $d['cod_bodega_raw'] ?? null,
            ':cb'          => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarEstado(int $id, int $idEmpresa, string $estado, ?int $aprobadaPor = null, ?string $motivoRechazo = null): bool
    {
        $sql = "UPDATE inventario_cargas
                SET estado = :estado,
                    aprobada_por   = :apr,
                    aprobada_at    = CASE WHEN :estado2 = 'aprobada' THEN CURRENT_TIMESTAMP ELSE aprobada_at END,
                    motivo_rechazo = :motivo,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':estado'  => $estado,
            ':estado2' => $estado,
            ':apr'     => $aprobadaPor,
            ':motivo'  => $motivoRechazo,
            ':id'      => $id,
            ':e'       => $idEmpresa,
        ]);
    }

    // ─── VALIDACIÓN (existencia) ──────────────────────────────────────────────

    public function productoExiste(int $idProducto, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM productos WHERE id = :id AND id_empresa = :e AND eliminado = false LIMIT 1");
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    public function bodegaExiste(int $idBodega, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM bodegas WHERE id = :id AND id_empresa = :e AND eliminado = false LIMIT 1");
        $st->execute([':id' => $idBodega, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /** ¿El código existe pero corresponde a un servicio ('02')? Permite dar un error preciso. */
    public function codigoEsServicio(string $codigo, int $idEmpresa): bool
    {
        $codigo = trim($codigo);
        if ($codigo === '') return false;
        $st = $this->db->prepare(
            "SELECT 1 FROM productos
             WHERE id_empresa = :e AND eliminado = false AND TRIM(codigo) = :c
               AND COALESCE(tipo_produccion, '01') = '02'
             LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $codigo]);
        return (bool) $st->fetchColumn();
    }

    /** Resuelve el id de un producto por su código principal, solo bienes (0 si no existe o es servicio). */
    public function getProductoIdPorCodigo(string $codigo, int $idEmpresa): int
    {
        $codigo = trim($codigo);
        if ($codigo === '') return 0;
        // Solo bienes ('01'); un servicio ('02') no puede cargarse a inventario.
        $st = $this->db->prepare(
            "SELECT id FROM productos
             WHERE id_empresa = :e AND eliminado = false AND TRIM(codigo) = :c
               AND COALESCE(tipo_produccion, '01') = '01'
             ORDER BY id LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $codigo]);
        return (int) $st->fetchColumn();
    }

    /** Resuelve el id de una bodega por su nombre (0 si no existe). */
    public function getBodegaIdPorNombre(string $nombre, int $idEmpresa): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') return 0;
        $st = $this->db->prepare("SELECT id FROM bodegas WHERE id_empresa = :e AND eliminado = false AND TRIM(nombre) ILIKE :n ORDER BY id LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':n' => $nombre]);
        return (int) $st->fetchColumn();
    }

    /** Usuarios por ids (id, nombre, mail) — para mostrar/notificar a los aprobadores. */
    public function getNombresUsuarios(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, nombre, mail FROM usuarios WHERE id IN ($ph)");
        $st->execute($ids);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setToken(int $id, string $token): void
    {
        $this->db->prepare("UPDATE inventario_cargas SET token_aprobacion = :t WHERE id = :id")->execute([':t' => $token, ':id' => $id]);
    }

    /** Carga por token (sin filtrar por empresa: la ruta pública no tiene sesión). */
    public function getByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') return null;
        $st = $this->db->prepare(
            "SELECT c.*, u.nombre AS creado_por_nombre
             FROM inventario_cargas c
             LEFT JOIN usuarios u ON u.id = c.created_by
             WHERE c.token_aprobacion = :t AND c.eliminado = false LIMIT 1"
        );
        $st->execute([':t' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function clearToken(int $id): void
    {
        $this->db->prepare("UPDATE inventario_cargas SET token_aprobacion = NULL WHERE id = :id")->execute([':id' => $id]);
    }

    /** Productos de la empresa para la hoja de referencia de la plantilla. */
    public function getProductosParaPlantilla(int $idEmpresa): array
    {
        // Solo bienes (tipo_produccion = '01'); los servicios ('02') no se cargan a inventario.
        // COALESCE: los registros antiguos sin el campo se tratan como bien, igual que en el resto del sistema.
        $st = $this->db->prepare(
            "SELECT codigo, nombre FROM productos
             WHERE id_empresa = :e AND eliminado = false
               AND TRIM(COALESCE(codigo,'')) <> ''
               AND COALESCE(tipo_produccion, '01') = '01'
             ORDER BY codigo ASC"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Bodegas de la empresa para la hoja de referencia de la plantilla. */
    public function getBodegasParaPlantilla(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT nombre FROM bodegas WHERE id_empresa = :e AND eliminado = false ORDER BY nombre ASC");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE inventario_cargas SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        return $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }
}
