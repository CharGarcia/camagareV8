<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * Entregas de Consignaciones en Ventas: listado de consignaciones según su estado
 * de entrega. Por defecto muestra las PENDIENTES de entregar (estado 'Emitida'); con
 * el filtro `estado:` se ven también las ya entregadas, enriquecidas con la evidencia
 * (GPS + firma) registrada desde la app móvil (canal='movil') o al marcar "Entregada"
 * desde el sistema (canal='web'), que vive en consignaciones_ventas_entregas.
 */
class EntregasConsignacionesRepository extends BaseRepository
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ENTREGADA = 'entregada';
    public const ESTADO_TODAS     = 'todas';

    /** Días entre la emisión y la entrega (o hasta hoy si sigue pendiente). */
    private const EXPR_DIAS = "(COALESCE(e.capturado_en::date, CURRENT_DATE) - cv.fecha_emision)";

    /** Whitelist + mapa de columnas ordenables (ver OrdenListado). */
    public const MAPA_ORDEN = [
        'fecha_emision' => 'cv.fecha_emision',
        'secuencial'    => 'cv.secuencial',
        'cliente'       => 'c.nombre',
        'responsable'   => 'rt.nombre',
        'fecha_entrega' => 'cv.fecha_entrega',
        'estado'        => 'cv.estado',
        'dias'          => self::EXPR_DIAS,
        'capturado_en'  => 'e.capturado_en',
        'canal'         => 'e.canal',
    ];

    /**
     * Una fila por consignación. La evidencia de entrega se trae por LATERAL (la
     * confirmada por id_entrega_confirmada primero; si no, la más reciente válida),
     * así una consignación con varios intentos desde el celular no se duplica.
     */
    private const JOIN_BASE = "
        FROM consignaciones_ventas cv
        INNER JOIN clientes c ON c.id = cv.id_cliente
        LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
        LEFT JOIN LATERAL (
            SELECT ev.*
            FROM consignaciones_ventas_entregas ev
            WHERE ev.id_consignacion = cv.id AND ev.eliminado = false AND ev.estado <> 'anulada'
            ORDER BY (ev.id = cv.id_entrega_confirmada) DESC NULLS LAST, ev.capturado_en DESC, ev.id DESC
            LIMIT 1
        ) e ON true
        LEFT JOIN usuarios u ON u.id = e.created_by
    ";

    /**
     * Columnas de cada fila del listado (y de la cabecera que devuelve buscarEnDetalles(),
     * para abrir el mismo modal de detalle desde la pestaña Detalles del buscador).
     */
    private const COLUMNAS_LISTADO = "
        cv.id AS id_consignacion, cv.serie, cv.secuencial, cv.estado AS estado_consignacion,
        cv.fecha_emision, cv.fecha_entrega, cv.hora_entrega_desde, cv.hora_entrega_hasta,
        cv.total, cv.observaciones AS observaciones_consignacion,
        " . self::EXPR_DIAS . " AS dias_espera,
        c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
        c.direccion AS cliente_direccion,
        rt.nombre AS responsable_traslado_nombre,
        e.id, e.canal, e.estado, e.latitud, e.longitud, e.precision_m, e.firma_path,
        e.capturado_en, e.dispositivo_id, e.observaciones,
        u.nombre AS registrado_por
    ";

    public function __construct()
    {
        parent::__construct('consignaciones_ventas_entregas');
    }

    /**
     * Condición de alcance compartida: multiempresa + soft-delete + estados entregables
     * + filtro "solo mis responsables". Devuelve null si el usuario no puede ver nada.
     */
    private function condicionAlcance(int $idEmpresa, ?array $idsResponsables, array &$params): ?string
    {
        $params[':e'] = $idEmpresa;
        $cond = "cv.id_empresa = :e AND cv.eliminado = false AND cv.estado IN ('Emitida', 'Entregada', 'Facturada')";
        if ($idsResponsables !== null) {
            if (empty($idsResponsables)) {
                return null;
            }
            $marcadores = [];
            foreach (array_values($idsResponsables) as $i => $idResp) {
                $clave = ":r{$i}";
                $marcadores[] = $clave;
                $params[$clave] = $idResp;
            }
            $cond .= " AND cv.id_responsable_traslado IN (" . implode(',', $marcadores) . ")";
        }
        return $cond;
    }

    /** Responsables de traslado usados en las consignaciones entregables: filtro del modal de filtros. */
    public function getResponsablesUsados(int $idEmpresa, ?array $idsResponsables): array
    {
        $params = [];
        $cond = $this->condicionAlcance($idEmpresa, $idsResponsables, $params);
        if ($cond === null) {
            return [];
        }
        $sql = "SELECT DISTINCT rt.id, rt.nombre
                FROM consignaciones_ventas cv
                JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                WHERE $cond
                ORDER BY rt.nombre";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que registraron entregas (columna "Registrado por"): filtro del modal de filtros. */
    public function getUsuariosEntregas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM consignaciones_ventas_entregas e
                JOIN usuarios u ON u.id = e.created_by
                WHERE e.id_empresa = :e AND e.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las consignaciones (pestaña "Detalles" del modal de filtros):
     * cada producto consignado (código, nombre, lote, NUP, caducidad, bodega) y cada
     * evidencia de entrega registrada (canal, dispositivo, observación) que coincide con
     * el texto, con la consignación a la que pertenece. Mismo alcance que el listado
     * (empresa, no eliminadas, estados entregables y "solo mis responsables"), sin el
     * filtro de estado de entrega: busca en pendientes y entregadas.
     *
     * La CTE `base` trae las mismas columnas que una fila del listado, para abrir el
     * modal de detalle con los mismos datos.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?array $idsResponsables = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [];
        $cond = $this->condicionAlcance($idEmpresa, $idsResponsables, $params);
        if ($cond === null) {
            return [];
        }

        $condProd = FiltrosBusqueda::condicionTexto(
            ['p.codigo', 'p.nombre', 'p.codigo_barras', 'd.lote', 'd.nup', "TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')",
             'bo.nombre', 'd.cantidad::text'],
            $q, $params, 'pr'
        );
        $condEnt = FiltrosBusqueda::condicionTexto(
            ['ev.observaciones', 'ev.dispositivo_id', 'ue.nombre', "TO_CHAR(ev.capturado_en, 'DD-MM-YYYY HH24:MI:SS')"],
            $q, $params, 'en'
        );
        if ($condProd === '' || $condEnt === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT " . self::COLUMNAS_LISTADO . "
                    " . self::JOIN_BASE . "
                    WHERE $cond
                ),
                coincidencias AS (
                    SELECT 'PRODUCTO' AS det_origen, p.codigo AS det_tipo, p.nombre AS det_descripcion,
                           NULLIF(CONCAT_WS(' / ', NULLIF(d.lote, ''), NULLIF(d.nup, ''), TO_CHAR(d.fecha_caducidad, 'DD-MM-YYYY')), '') AS det_extra,
                           d.cantidad AS det_cantidad, d.id_consignacion AS det_id_cons
                    FROM consignaciones_ventas_detalles d
                    JOIN base b ON b.id_consignacion = d.id_consignacion
                    LEFT JOIN productos p ON p.id = d.id_producto
                    LEFT JOIN bodegas bo ON bo.id = d.id_bodega
                    WHERE d.eliminado = false AND $condProd
                    UNION ALL
                    SELECT 'ENTREGA' AS det_origen, ev.canal AS det_tipo, ev.observaciones AS det_descripcion,
                           TO_CHAR(ev.capturado_en, 'DD-MM-YYYY HH24:MI:SS') AS det_extra,
                           NULL::numeric AS det_cantidad, ev.id_consignacion AS det_id_cons
                    FROM consignaciones_ventas_entregas ev
                    JOIN base b ON b.id_consignacion = ev.id_consignacion
                    LEFT JOIN usuarios ue ON ue.id = ev.created_by
                    WHERE ev.eliminado = false AND $condEnt
                )
                SELECT x.det_origen, x.det_tipo, x.det_descripcion, x.det_extra, x.det_cantidad, b.*
                FROM coincidencias x
                JOIN base b ON b.id_consignacion = x.det_id_cons
                ORDER BY b.fecha_emision DESC, b.id_consignacion DESC, x.det_origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Responsable de traslado de la consignación dueña de una entrega (null si la
     * entrega no existe en la empresa o la consignación no tiene responsable).
     * Lo usa el guard de la firma: mismo criterio de visibilidad que el listado.
     */
    public function getResponsableDeEntrega(int $idEntrega, int $idEmpresa): ?int
    {
        $sql = "SELECT cv.id_responsable_traslado
                FROM consignaciones_ventas_entregas e
                INNER JOIN consignaciones_ventas cv ON cv.id = e.id_consignacion
                WHERE e.id = :id AND e.id_empresa = :e
                  AND e.eliminado = false AND cv.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idEntrega, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $row['id_responsable_traslado'] !== null ? (int) $row['id_responsable_traslado'] : null;
    }

    /** Normaliza el valor del filtro `estado:` del buscador; sin filtro = pendientes. */
    public static function normalizarEstado(?string $valor): string
    {
        $v = strtolower(trim((string) $valor));
        if (in_array($v, ['entregada', 'entregadas', 'entregado', 'entregados'], true)) {
            return self::ESTADO_ENTREGADA;
        }
        if (in_array($v, ['todas', 'todos', 'todo', 'all'], true)) {
            return self::ESTADO_TODAS;
        }
        return self::ESTADO_PENDIENTE;
    }

    /** Condición SQL del estado de entrega (sobre cv.estado). */
    private function condicionEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_ENTREGADA => "cv.estado IN ('Entregada', 'Facturada')",
            self::ESTADO_TODAS     => "cv.estado IN ('Emitida', 'Entregada', 'Facturada')",
            default                => "cv.estado = 'Emitida'",
        };
    }

    /**
     * Arma el WHERE + params compartido por getListado()/getResumen(), aplicando:
     * multiempresa + soft-delete, filtro "solo mis responsables" (usuario vinculado
     * a responsables de traslado en config/usuarios-sistema), buscador de texto libre
     * y sintaxis clave:valor (FiltrosBusqueda).
     *
     * El estado de entrega (`estado:` del buscador) NO se aplica aquí: se devuelve
     * resuelto en 'estado' para que el listado lo aplique y los KPIs no (los KPIs
     * describen pendientes y entregadas del mismo rango a la vez).
     *
     * $idsResponsables: null = ve todas las consignaciones; array (aunque vacío) =
     * solo las de esos responsables_traslado.
     */
    private function construirWhere(int $idEmpresa, string $buscar, ?array $idsResponsables): array
    {
        $params = [':e' => $idEmpresa];
        // Borrador y Anulada nunca entran: no son entregables ni entregadas.
        $where = "WHERE cv.id_empresa = :e AND cv.eliminado = false
                    AND cv.estado IN ('Emitida', 'Entregada', 'Facturada')";

        if ($idsResponsables !== null) {
            if (empty($idsResponsables)) {
                return ['where' => $where . " AND 1=0", 'params' => $params, 'estado' => self::ESTADO_PENDIENTE];
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
            // Texto libre (buscador FiltrosModal de la vista): las columnas del listado y lo
            // que identifica a la consignación aunque no sea columna. Decisión del usuario:
            // Estado y Canal (tipo) NO entran en el texto libre; se filtran desde el modal.
            // Firma y GPS son íconos sí/no: también van solo al modal.
            $condicion = FiltrosBusqueda::condicionTexto(
                [
                    "TO_CHAR(cv.fecha_emision, 'DD-MM-YYYY')",                   // Emisión
                    "CONCAT(cv.serie, '-', cv.secuencial)",                      // Consignación
                    'c.nombre',                                                  // Cliente
                    'c.identificacion',
                    'c.direccion',                                               // Dirección
                    'rt.nombre',                                                 // Responsable
                    "TO_CHAR(cv.fecha_entrega, 'DD-MM-YYYY')",                   // Entrega programada
                    '(' . self::EXPR_DIAS . ')::text',                           // Días
                    "TO_CHAR(e.capturado_en, 'DD-MM-YYYY HH24:MI:SS')",          // Fecha/hora entrega
                    'u.nombre',                                                  // Registrado por
                    'e.observaciones',                                           // Observaciones (de la entrega)
                    'cv.observaciones',                                          // Observaciones de la consignación
                    'cv.punto_llegada',
                    'e.dispositivo_id',
                    // Productos consignados (código, nombre, lote y NUP)
                    "(SELECT STRING_AGG(CONCAT_WS(' ', p.codigo, p.nombre, d.lote, d.nup), ' ')
                        FROM consignaciones_ventas_detalles d
                        LEFT JOIN productos p ON p.id = d.id_producto
                       WHERE d.id_consignacion = cv.id AND d.eliminado = false)",
                ],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        $estado = $this->extraerFiltroEstado($filtros);
        $this->aplicarFiltroProducto($where, $params, $filtros);

        FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'secuencial'     => 'cv.secuencial',
                // Nº completo "serie-secuencial" (contiene al secuencial: las búsquedas
                // viejas por secuencial siguen encontrando lo mismo).
                'numero'         => "CONCAT(cv.serie, '-', cv.secuencial)",
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'direccion'      => 'c.direccion',
                'responsable'    => 'rt.nombre',
                'realizo'        => 'u.nombre',
                'usuario'        => 'u.nombre',
                'dispositivo'    => 'e.dispositivo_id',
                'observaciones'  => 'e.observaciones',
                'obs'            => 'e.observaciones',
            ],
            'exacto' => [
                'canal'          => 'e.canal',
                'id_responsable' => 'cv.id_responsable_traslado',
                'id_usuario'     => 'e.created_by',
                // firma:si / firma:no y gps:si / gps:no (mismas reglas que los íconos de la tabla)
                'firma'          => "CASE WHEN e.id IS NOT NULL AND e.firma_path IS NOT NULL AND e.firma_path <> '' THEN 'si' ELSE 'no' END",
                'gps'            => "CASE WHEN e.id IS NOT NULL AND e.latitud IS NOT NULL AND e.longitud IS NOT NULL THEN 'si' ELSE 'no' END",
            ],
            'fecha' => [
                // Admiten valor parcial ("emision:2026" = todo el año, "emision:2026-08" =
                // todo el mes) vía FiltrosBusqueda::normalizarFecha(); los selects de Año/Mes
                // de la vista arman ese mismo token en vez de duplicar lógica de fechas aquí.
                'emision'    => 'cv.fecha_emision',
                'fecha'      => 'cv.fecha_emision',
                'programada' => 'cv.fecha_entrega',
                'entrega'    => 'e.capturado_en',
            ],
            'numerico' => [
                'dias'      => self::EXPR_DIAS,
                'precision' => 'e.precision_m',
            ],
        ]);

        return ['where' => $where, 'params' => $params, 'estado' => $estado];
    }

    /** Saca `estado:` del array de filtros (no es una columna directa) y lo devuelve normalizado. */
    private function extraerFiltroEstado(array &$filtros): string
    {
        if (!isset($filtros['estado'])) {
            return self::ESTADO_PENDIENTE;
        }
        $valor = $filtros['estado']['valor'];
        $valor = is_array($valor) ? ($valor[0] ?? '') : $valor;
        unset($filtros['estado']);
        return self::normalizarEstado(is_string($valor) ? $valor : null);
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

    /**
     * Listado paginado. $orden es la salida de OrdenListado::leer(); sin criterio válido
     * se ordena por fecha de emisión (con desempate por id para que la paginación sea estable).
     * Devuelve además el estado de entrega resuelto ('estado'), para que la vista lo refleje.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?array $idsResponsables): array
    {
        ['where' => $where, 'params' => $params, 'estado' => $estado] = $this->construirWhere($idEmpresa, $buscar, $idsResponsables);
        $where .= " AND " . $this->condicionEstado($estado);

        $sqlCount = "SELECT COUNT(*) " . self::JOIN_BASE . " $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $orderBy = OrdenListado::clausula($orden, self::MAPA_ORDEN, 'cv.fecha_emision', 'cv.id DESC');

        $sql = "SELECT " . self::COLUMNAS_LISTADO . "
                " . self::JOIN_BASE . "
                $where
                $orderBy
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows, 'estado' => $estado];
    }

    /**
     * KPIs del rango filtrado (sin aplicar el filtro de estado de entrega): pendientes,
     * entregadas (total y por canal), evidencia incompleta y tiempo promedio emisión→entrega.
     */
    public function getResumen(int $idEmpresa, string $buscar, ?array $idsResponsables): array
    {
        ['where' => $where, 'params' => $params] = $this->construirWhere($idEmpresa, $buscar, $idsResponsables);

        $sql = "SELECT
                    COUNT(*) FILTER (WHERE cv.estado = 'Emitida') AS pendientes,
                    COUNT(*) FILTER (WHERE cv.estado IN ('Entregada', 'Facturada')) AS total_entregas,
                    COUNT(*) FILTER (WHERE e.id IS NOT NULL AND e.canal = 'movil') AS total_movil,
                    COUNT(*) FILTER (WHERE e.id IS NOT NULL AND e.canal = 'web') AS total_web,
                    COUNT(*) FILTER (
                        WHERE cv.estado IN ('Entregada', 'Facturada')
                          AND (e.id IS NULL OR e.latitud IS NULL OR e.longitud IS NULL
                               OR (e.canal = 'movil' AND e.firma_path IS NULL))
                    ) AS incompletas,
                    AVG(EXTRACT(EPOCH FROM (e.capturado_en - (cv.fecha_emision)::timestamp)) / 3600.0)
                        FILTER (WHERE e.id IS NOT NULL AND cv.fecha_emision IS NOT NULL) AS horas_promedio
                " . self::JOIN_BASE . "
                $where";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'pendientes'     => (int) ($row['pendientes'] ?? 0),
            'total_entregas' => (int) ($row['total_entregas'] ?? 0),
            'total_movil'    => (int) ($row['total_movil'] ?? 0),
            'total_web'      => (int) ($row['total_web'] ?? 0),
            'incompletas'    => (int) ($row['incompletas'] ?? 0),
            'horas_promedio' => $row['horas_promedio'] !== null ? round((float) $row['horas_promedio'], 1) : null,
        ];
    }
}
