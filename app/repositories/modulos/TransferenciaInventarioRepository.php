<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Transferencias de inventario entre bodegas (y entre establecimientos del
 * mismo RUC). Acceso a datos puro: nada de lógica de negocio aquí — el
 * movimiento de stock lo orquesta TransferenciaInventarioService apoyándose en
 * InventarioRepository (lockStock / registrarMovimiento / actualizarStock).
 */
class TransferenciaInventarioRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['numero', 'fecha_transferencia', 'origen_nombre', 'destino_nombre', 'total_items', 'total_costo', 'estado'];

    public function __construct()
    {
        parent::__construct('transferencias_inventario_cabecera');
    }

    /** Ambiente actual de la empresa ('1' pruebas / '2' producción). */
    public function getTipoAmbiente(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return (string) ($st->fetchColumn() ?: '1');
    }

    // ────────────────────────────────────────────────────────────────
    // LISTADO
    // ────────────────────────────────────────────────────────────────

    /**
     * @param array $filtros ['desde' => 'Y-m-d', 'hasta' => 'Y-m-d', 'id_bodega' => int, 'estado' => string]
     */
    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null,
        array $filtros = []
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'fecha_transferencia';
        }
        $dir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = $this->getBaseWhere($idEmpresa, 't', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Igual que el kardex: cada ambiente ve solo sus documentos.
        $where .= " AND t.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)";
        $params[':id_empresa_amb'] = $idEmpresa;

        if (!empty($filtros['desde'])) {
            $where .= " AND t.fecha_transferencia >= :desde";
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where .= " AND t.fecha_transferencia <= :hasta";
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND (t.id_bodega_origen = :id_bod OR t.id_bodega_destino = :id_bod)";
            $params[':id_bod'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['estado'])) {
            $where .= " AND t.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        // Buscador estándar: texto libre multi-palabra + filtros clave:valor.
        $parsed = FiltrosBusqueda::parsear($buscar);
        if (($parsed['texto_libre'] ?? '') !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['t.numero', 'bo.nombre', 'bd.nombre', 't.observaciones', 't.responsable_envia', 't.responsable_recibe'],
                $parsed['texto_libre'],
                $params,
                'trf'
            );
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'] ?? [], [
            'texto'    => [
                'numero'      => 't.numero',
                'origen'      => 'bo.nombre',
                'destino'     => 'bd.nombre',
                'responsable' => 't.responsable_envia',
                'recibe'      => 't.responsable_recibe',
                'obs'         => 't.observaciones',
            ],
            'exacto'   => ['estado' => 't.estado'],
            'fecha'    => ['fecha' => 't.fecha_transferencia'],
            'numerico' => ['items' => 't.total_items', 'costo' => 't.total_costo'],
        ]);

        $from = "FROM {$this->table} t
                 INNER JOIN bodegas bo ON bo.id = t.id_bodega_origen
                 INNER JOIN bodegas bd ON bd.id = t.id_bodega_destino";

        $stCount = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $ordenSql = match ($ordenCol) {
            'origen_nombre'  => 'bo.nombre',
            'destino_nombre' => 'bd.nombre',
            default          => 't.' . $ordenCol,
        };

        $sql = "SELECT t.*,
                       bo.nombre AS origen_nombre,
                       bd.nombre AS destino_nombre,
                       eo.codigo AS establecimiento_origen_codigo,
                       ed.codigo AS establecimiento_destino_codigo,
                       u.nombre  AS usuario_nombre,
                       (SELECT COUNT(*) FROM transferencias_inventario_detalle d
                         WHERE d.id_transferencia = t.id AND d.eliminado = false) AS lineas
                {$from}
                LEFT JOIN empresa_establecimiento eo ON eo.id = t.id_establecimiento_origen
                LEFT JOIN empresa_establecimiento ed ON ed.id = t.id_establecimiento_destino
                LEFT JOIN usuarios u ON u.id = t.created_by
                {$where}
                ORDER BY {$ordenSql} {$dir}, t.id {$dir}";

        if ($perPage > 0) {
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** Totales del conjunto filtrado (tarjeta de control del listado). */
    public function getResumen(int $idEmpresa, ?int $idUsuarioFiltro = null, array $filtros = []): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 't', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= " AND t.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)";
        $params[':id_empresa_amb'] = $idEmpresa;

        if (!empty($filtros['desde'])) {
            $where .= " AND t.fecha_transferencia >= :desde";
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where .= " AND t.fecha_transferencia <= :hasta";
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND (t.id_bodega_origen = :id_bod OR t.id_bodega_destino = :id_bod)";
            $params[':id_bod'] = (int) $filtros['id_bodega'];
        }

        $sql = "SELECT COUNT(*) AS documentos,
                       COALESCE(SUM(CASE WHEN t.estado = 'registrada' THEN t.total_items ELSE 0 END), 0) AS unidades,
                       COALESCE(SUM(CASE WHEN t.estado = 'registrada' THEN t.total_costo ELSE 0 END), 0) AS costo,
                       COALESCE(SUM(CASE WHEN t.entre_establecimientos AND t.estado = 'registrada' THEN 1 ELSE 0 END), 0) AS interestablecimiento
                FROM {$this->table} t {$where}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['documentos' => 0, 'unidades' => 0, 'costo' => 0, 'interestablecimiento' => 0];
    }

    // ────────────────────────────────────────────────────────────────
    // FICHA
    // ────────────────────────────────────────────────────────────────

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT t.*,
                       bo.nombre AS origen_nombre,
                       bd.nombre AS destino_nombre,
                       eo.codigo AS establecimiento_origen_codigo, eo.nombre AS establecimiento_origen_nombre, eo.direccion AS establecimiento_origen_direccion,
                       ed.codigo AS establecimiento_destino_codigo, ed.nombre AS establecimiento_destino_nombre, ed.direccion AS establecimiento_destino_direccion,
                       u.nombre  AS usuario_nombre,
                       ua.nombre AS anulado_por_nombre
                FROM {$this->table} t
                INNER JOIN bodegas bo ON bo.id = t.id_bodega_origen
                INNER JOIN bodegas bd ON bd.id = t.id_bodega_destino
                LEFT JOIN empresa_establecimiento eo ON eo.id = t.id_establecimiento_origen
                LEFT JOIN empresa_establecimiento ed ON ed.id = t.id_establecimiento_destino
                LEFT JOIN usuarios u  ON u.id  = t.created_by
                LEFT JOIN usuarios ua ON ua.id = t.updated_by
                WHERE t.id = :id AND t.id_empresa = :e AND t.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDetalle(int $idTransferencia, int $idEmpresa): array
    {
        $sql = "SELECT d.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       um.nombre AS medida_nombre
                FROM transferencias_inventario_detalle d
                INNER JOIN productos p ON p.id = d.id_producto
                LEFT JOIN unidades_medida um ON um.id = d.id_medida
                WHERE d.id_transferencia = :id AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idTransferencia, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────
    // ESCRITURA
    // ────────────────────────────────────────────────────────────────

    /**
     * Siguiente número correlativo de la empresa. El candado se libera solo al
     * COMMIT/ROLLBACK, así que el llamador DEBE tener ya abierta la transacción
     * en la que insertará la cabecera (§8 de las reglas del sistema).
     */
    public function siguienteSecuencial(int $idEmpresa, string $tipoAmbiente): int
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('transferencia_inventario:' || :e || ':' || :amb))")
                 ->execute([':e' => $idEmpresa, ':amb' => $tipoAmbiente]);

        // Incluye las eliminadas y anuladas a propósito: un número de documento
        // no se reutiliza nunca, aunque su transferencia se haya dado de baja.
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(secuencial), 0) + 1
               FROM {$this->table}
              WHERE id_empresa = :e AND tipo_ambiente = :amb"
        );
        $st->execute([':e' => $idEmpresa, ':amb' => $tipoAmbiente]);
        return (int) $st->fetchColumn();
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, secuencial, numero, fecha_transferencia,
                    id_bodega_origen, id_bodega_destino,
                    id_establecimiento_origen, id_establecimiento_destino, entre_establecimientos,
                    responsable_envia, responsable_recibe, observaciones,
                    total_items, total_costo, estado, tipo_ambiente, created_by, updated_by
                ) VALUES (
                    :id_empresa, :sec, :numero, :fecha,
                    :bod_origen, :bod_destino,
                    :est_origen, :est_destino, :entre_est,
                    :resp_envia, :resp_recibe, :obs,
                    :total_items, :total_costo, :estado, :amb, :uid, :uid
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => (int) $data['id_empresa'],
            ':sec'         => (int) $data['secuencial'],
            ':numero'      => $data['numero'],
            ':fecha'       => $data['fecha_transferencia'],
            ':bod_origen'  => (int) $data['id_bodega_origen'],
            ':bod_destino' => (int) $data['id_bodega_destino'],
            ':est_origen'  => !empty($data['id_establecimiento_origen'])  ? (int) $data['id_establecimiento_origen']  : null,
            ':est_destino' => !empty($data['id_establecimiento_destino']) ? (int) $data['id_establecimiento_destino'] : null,
            ':entre_est'   => !empty($data['entre_establecimientos']) ? 'true' : 'false',
            ':resp_envia'  => $data['responsable_envia']  ?: null,
            ':resp_recibe' => $data['responsable_recibe'] ?: null,
            ':obs'         => $data['observaciones'] ?: null,
            ':total_items' => (float) ($data['total_items'] ?? 0),
            ':total_costo' => (float) ($data['total_costo'] ?? 0),
            ':estado'      => $data['estado'] ?? 'registrada',
            ':amb'         => $data['tipo_ambiente'],
            ':uid'         => (int) $data['id_usuario'],
        ]);

        return (int) $st->fetchColumn();
    }

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO transferencias_inventario_detalle (
                    id_empresa, id_transferencia, id_producto, id_medida,
                    cantidad, costo_unitario, costo_total,
                    numero_lote, fecha_caducidad, nup, observaciones,
                    id_kardex_salida, id_kardex_entrada, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_trf, :id_prod, :id_medida,
                    :cant, :costo_u, :costo_t,
                    :lote, :cad, :nup, :obs,
                    :k_salida, :k_entrada, :uid, :uid
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => (int) $data['id_empresa'],
            ':id_trf'     => (int) $data['id_transferencia'],
            ':id_prod'    => (int) $data['id_producto'],
            ':id_medida'  => !empty($data['id_medida']) ? (int) $data['id_medida'] : null,
            ':cant'       => (float) $data['cantidad'],
            ':costo_u'    => (float) ($data['costo_unitario'] ?? 0),
            ':costo_t'    => (float) ($data['costo_total'] ?? 0),
            ':lote'       => $data['numero_lote']     ?: null,
            ':cad'        => $data['fecha_caducidad'] ?: null,
            ':nup'        => $data['nup']             ?: null,
            ':obs'        => $data['observaciones']   ?: null,
            ':k_salida'   => !empty($data['id_kardex_salida'])  ? (int) $data['id_kardex_salida']  : null,
            ':k_entrada'  => !empty($data['id_kardex_entrada']) ? (int) $data['id_kardex_entrada'] : null,
            ':uid'        => (int) $data['id_usuario'],
        ]);

        return (int) $st->fetchColumn();
    }

    /** Totales del documento: se recalculan al terminar de procesar las líneas. */
    public function actualizarTotales(int $id, int $idEmpresa, float $totalItems, float $totalCosto): void
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
                SET total_items = :items, total_costo = :costo, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e"
        );
        $st->execute([':items' => $totalItems, ':costo' => $totalCosto, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                   SET estado = 'anulada', updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e AND eliminado = false AND estado = 'registrada'";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica del documento (§5): nunca se borra físicamente. */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table}
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);

        $this->db->prepare(
            "UPDATE transferencias_inventario_detalle
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid
              WHERE id_transferencia = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario]);

        return $st->rowCount() > 0;
    }

    /** Enlaza la guía de remisión emitida a partir de esta transferencia. */
    public function setGuiaRemision(int $id, int $idEmpresa, ?int $idGuia): void
    {
        $st = $this->db->prepare(
            "UPDATE {$this->table} SET id_guia_remision = :g, updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':g' => $idGuia, ':id' => $id, ':e' => $idEmpresa]);
    }

    // ────────────────────────────────────────────────────────────────
    // CATÁLOGOS
    // ────────────────────────────────────────────────────────────────

    /** Bodegas activas de la empresa con el establecimiento al que pertenecen. */
    public function getBodegasConEstablecimiento(int $idEmpresa): array
    {
        $sql = "SELECT b.id, b.nombre, b.id_establecimiento,
                       e.codigo AS establecimiento_codigo,
                       e.nombre AS establecimiento_nombre,
                       e.direccion AS establecimiento_direccion
                FROM bodegas b
                LEFT JOIN empresa_establecimiento e ON e.id = b.id_establecimiento AND e.eliminado = false
                WHERE b.id_empresa = :e AND b.eliminado = false AND b.status = true
                ORDER BY e.codigo ASC NULLS LAST, b.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Productos inventariables con stock en una bodega (para el buscador del
     * modal). Devuelve el stock real del kardex, no el caché.
     */
    public function buscarProductosConStock(int $idEmpresa, int $idBodega, string $texto, int $limite = 20): array
    {
        $params = [':e' => $idEmpresa, ':b' => $idBodega, ':e2' => $idEmpresa];
        $condTexto = FiltrosBusqueda::condicionTexto(['p.nombre', 'p.codigo'], $texto, $params, 'prod');
        $whereTexto = $condTexto !== '' ? " AND {$condTexto}" : '';

        $sql = "SELECT p.id, p.codigo, p.nombre, p.id_medida AS id_medida_base,
                       COALESCE(k.stock, 0) AS stock
                FROM productos p
                LEFT JOIN (
                    SELECT id_producto, ROUND(SUM(cantidad), 2) AS stock
                      FROM inventario_kardex
                     WHERE id_empresa = :e2 AND id_bodega = :b AND eliminado = false
                       AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)
                     GROUP BY id_producto
                ) k ON k.id_producto = p.id
                WHERE p.id_empresa = :e AND p.eliminado = false AND p.inventariable = true
                  {$whereTexto}
                ORDER BY (COALESCE(k.stock, 0) > 0) DESC, p.nombre ASC
                LIMIT " . (int) $limite;

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Costo unitario con el que sale la mercadería de la bodega origen: promedio
     * ponderado de TODOS los movimientos que sumaron stock ahí (entradas, ajustes
     * positivos y transferencias recibidas). No se usa getCostoPromedio() del
     * InventarioRepository porque ese solo mira tipo_movimiento = 'entrada', y una
     * bodega que se abasteció por transferencia quedaría con costo 0.
     * Si se indica lote, se calcula sobre ese lote. Cae a productos.costo_producto
     * cuando la bodega no tiene historial de costos.
     */
    public function getCostoOrigen(int $idProducto, int $idBodega, int $idEmpresa, ?string $lote = null): float
    {
        $params = [':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];
        $whereLote = '';
        if ($lote !== null && $lote !== '' && $lote !== 'sin_lote') {
            $whereLote = ' AND numero_lote = :lote';
            $params[':lote'] = $lote;
        }

        $sql = "SELECT CASE WHEN SUM(cantidad) > 0
                            THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6)
                            ELSE 0 END
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b
                  AND eliminado = false AND cantidad > 0
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                  {$whereLote}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $costo = (float) ($st->fetchColumn() ?: 0);

        if ($costo > 0) {
            return $costo;
        }

        // Sin historial de costos en la bodega: se usa el costo del producto.
        $st = $this->db->prepare("SELECT COALESCE(costo_producto, 0) FROM productos WHERE id = :p AND id_empresa = :e");
        $st->execute([':p' => $idProducto, ':e' => $idEmpresa]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    /** Saldo de una serie/NUP concreta en una bodega (debe ser > 0 para transferirla). */
    public function getStockSerie(int $idProducto, int $idBodega, int $idEmpresa, string $nup): float
    {
        $sql = "SELECT ROUND(COALESCE(SUM(cantidad), 0), 2)
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b
                  AND eliminado = false AND nup = :nup
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega, ':nup' => $nup]);
        return (float) $st->fetchColumn();
    }

    /** Series/NUP disponibles de un producto en una bodega (stock neto > 0). */
    public function getSeriesDisponibles(int $idProducto, int $idBodega, int $idEmpresa, ?string $lote = null): array
    {
        $params = [':e' => $idEmpresa, ':p' => $idProducto, ':b' => $idBodega];
        $whereLote = '';
        if ($lote !== null && $lote !== '' && $lote !== 'sin_lote') {
            $whereLote = " AND numero_lote = :lote";
            $params[':lote'] = $lote;
        }

        $sql = "SELECT nup, MAX(numero_lote) AS numero_lote, MAX(fecha_caducidad) AS fecha_caducidad
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p AND id_bodega = :b
                  AND eliminado = false AND nup IS NOT NULL AND nup <> ''
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e)
                  {$whereLote}
                GROUP BY nup
                HAVING ROUND(SUM(cantidad), 2) > 0
                ORDER BY nup ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
