<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

/**
 * Resumen de entregas de Consignaciones en Ventas: unifica la evidencia registrada
 * desde la app móvil (canal='movil') y la registrada manualmente desde el sistema
 * al marcar "Entregada" (canal='web') — ambas viven en consignaciones_ventas_entregas.
 */
class EntregasConsignacionesRepository extends BaseRepository
{
    private const JOIN_BASE = "
        FROM consignaciones_ventas_entregas e
        INNER JOIN consignaciones_ventas cv ON cv.id = e.id_consignacion
        INNER JOIN clientes c ON c.id = cv.id_cliente
        LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
        LEFT JOIN usuarios u ON u.id = e.created_by
    ";

    public function __construct()
    {
        parent::__construct('consignaciones_ventas_entregas');
    }

    /**
     * Arma el WHERE + params compartido por getListado()/getResumen(), aplicando:
     * multiempresa + soft-delete, filtro "solo mis responsables" (repartidor sin
     * acceso total), buscador de texto libre y sintaxis clave:valor (FiltrosBusqueda).
     *
     * $idsResponsables: null = ve todas las entregas; array (aunque vacío) = solo
     * las de esos responsables_traslado.
     */
    private function construirWhere(int $idEmpresa, string $buscar, ?array $idsResponsables): array
    {
        $params = [':e' => $idEmpresa];
        $where = "WHERE e.id_empresa = :e AND e.eliminado = false AND cv.eliminado = false";

        if ($idsResponsables !== null) {
            if (empty($idsResponsables)) {
                return ['where' => $where . " AND 1=0", 'params' => $params];
            }
            $marcadores = [];
            foreach (array_values($idsResponsables) as $i => $idResp) {
                $clave = ":r{$i}";
                $marcadores[] = $clave;
                $params[$clave] = $idResp;
            }
            $where .= " AND cv.id_responsable_traslado IN (" . implode(',', $marcadores) . ")";
        }

        $parsed     = FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $condicion = FiltrosBusqueda::condicionTexto(
                ['cv.secuencial', 'c.nombre', 'rt.nombre', 'u.nombre'],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        $this->aplicarFiltroProducto($where, $params, $filtros);

        FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'secuencial'     => 'cv.secuencial',
                'numero'         => 'cv.secuencial',
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'responsable'    => 'rt.nombre',
                'realizo'        => 'u.nombre',
                'usuario'        => 'u.nombre',
                'dispositivo'    => 'e.dispositivo_id',
                'observaciones'  => 'e.observaciones',
                'obs'            => 'e.observaciones',
            ],
            'exacto' => [
                'canal'  => 'e.canal',
                'estado' => 'e.estado',
            ],
            'fecha' => [
                // Admite valor parcial ("entrega:2026" = todo el año, "entrega:2026-08" = todo
                // el mes) vía FiltrosBusqueda::normalizarFecha(); los selects de Año/Mes de la
                // vista arman ese mismo token en vez de duplicar lógica de fechas aquí.
                'entrega' => 'e.capturado_en',
                'fecha'   => 'e.capturado_en',
            ],
            'numerico' => [
                'precision' => 'e.precision_m',
            ],
        ]);

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Filtro por producto contenido en la consignación (clave "producto:"). No cabe en
     * el mapa simple de FiltrosBusqueda (columna directa): requiere EXISTS contra el
     * detalle + join a productos, así que se resuelve aparte y se saca del array de
     * filtros antes de pasarlo a FiltrosBusqueda::aplicarFiltros().
     */
    private function aplicarFiltroProducto(string &$where, array &$params, array &$filtros): void
    {
        if (!isset($filtros['producto'])) {
            return;
        }
        $valor = $filtros['producto']['valor'];
        $valor = is_array($valor) ? ($valor[0] ?? '') : $valor;
        $neg   = $filtros['producto']['neg'];

        $where .= ($neg ? " AND NOT EXISTS" : " AND EXISTS") . " (
            SELECT 1 FROM consignaciones_ventas_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            WHERE d.id_consignacion = cv.id AND (d.eliminado = false OR d.eliminado IS NULL)
              AND (p.nombre ILIKE :f_producto OR p.codigo ILIKE :f_producto)
        )";
        $params[':f_producto'] = '%' . $valor . '%';
        unset($filtros['producto']);
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?array $idsResponsables): array
    {
        ['where' => $where, 'params' => $params] = $this->construirWhere($idEmpresa, $buscar, $idsResponsables);

        $sqlCount = "SELECT COUNT(*) " . self::JOIN_BASE . " $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $colMap = [
            'capturado_en' => 'e.capturado_en',
            'secuencial'   => 'cv.secuencial',
            'cliente'      => 'c.nombre',
            'responsable'  => 'rt.nombre',
            'canal'        => 'e.canal',
        ];
        $sort = $colMap[$ordenCol] ?? 'e.capturado_en';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT e.id, e.canal, e.estado, e.latitud, e.longitud, e.precision_m, e.firma_path,
                       e.capturado_en, e.dispositivo_id, e.observaciones,
                       cv.id AS id_consignacion, cv.serie, cv.secuencial, cv.estado AS estado_consignacion, cv.fecha_emision,
                       c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       rt.nombre AS responsable_traslado_nombre,
                       u.nombre AS registrado_por
                " . self::JOIN_BASE . "
                $where
                ORDER BY $sort $dir, e.id DESC
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    /** KPIs del rango filtrado: total por canal y evidencia incompleta. "Pendientes" se calcula aparte (getPendientes). */
    public function getResumen(int $idEmpresa, string $buscar, ?array $idsResponsables): array
    {
        ['where' => $where, 'params' => $params] = $this->construirWhere($idEmpresa, $buscar, $idsResponsables);

        $sql = "SELECT
                    COUNT(*) FILTER (WHERE e.estado != 'anulada') AS total_entregas,
                    COUNT(*) FILTER (WHERE e.estado != 'anulada' AND e.canal = 'movil') AS total_movil,
                    COUNT(*) FILTER (WHERE e.estado != 'anulada' AND e.canal = 'web') AS total_web,
                    COUNT(*) FILTER (
                        WHERE e.estado != 'anulada'
                          AND (e.latitud IS NULL OR e.longitud IS NULL OR (e.canal = 'movil' AND e.firma_path IS NULL))
                    ) AS incompletas,
                    AVG(EXTRACT(EPOCH FROM (e.capturado_en - (cv.fecha_emision)::timestamp)) / 3600.0)
                        FILTER (WHERE e.estado != 'anulada' AND cv.fecha_emision IS NOT NULL) AS horas_promedio
                " . self::JOIN_BASE . "
                $where";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_entregas' => (int) ($row['total_entregas'] ?? 0),
            'total_movil'    => (int) ($row['total_movil'] ?? 0),
            'total_web'      => (int) ($row['total_web'] ?? 0),
            'incompletas'    => (int) ($row['incompletas'] ?? 0),
            'horas_promedio' => $row['horas_promedio'] !== null ? round((float) $row['horas_promedio'], 1) : null,
        ];
    }

    /**
     * Consignaciones pendientes de entrega ('Emitida'), con el mismo filtro de
     * producto/responsable del listado (no aplica fecha/canal: aún no tienen entrega).
     */
    public function getPendientes(int $idEmpresa, string $buscar, ?array $idsResponsables): int
    {
        $params = [':e' => $idEmpresa];
        $where  = "WHERE cv.id_empresa = :e AND cv.eliminado = false AND cv.estado = 'Emitida'";

        if ($idsResponsables !== null) {
            if (empty($idsResponsables)) {
                return 0;
            }
            $marcadores = [];
            foreach (array_values($idsResponsables) as $i => $idResp) {
                $clave = ":r{$i}";
                $marcadores[] = $clave;
                $params[$clave] = $idResp;
            }
            $where .= " AND cv.id_responsable_traslado IN (" . implode(',', $marcadores) . ")";
        }

        $parsed  = FiltrosBusqueda::parsear($buscar);
        $filtros = $parsed['filtros'];
        if (isset($filtros['producto'])) {
            $valor = $filtros['producto']['valor'];
            $valor = is_array($valor) ? ($valor[0] ?? '') : $valor;
            $where .= " AND EXISTS (
                SELECT 1 FROM consignaciones_ventas_detalles d
                INNER JOIN productos p ON p.id = d.id_producto
                WHERE d.id_consignacion = cv.id AND (d.eliminado = false OR d.eliminado IS NULL)
                  AND (p.nombre ILIKE :f_producto OR p.codigo ILIKE :f_producto)
            )";
            $params[':f_producto'] = '%' . $valor . '%';
        }

        $sql = "SELECT COUNT(*) FROM consignaciones_ventas cv $where";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }
}
