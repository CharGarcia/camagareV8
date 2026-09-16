<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;
use PDO;

class ProformaRepository extends BaseRepository
{
    private array $colsCache = [];

    public function __construct()
    {
        parent::__construct('proformas_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Columnas reales de una tabla (para detectar campos opcionales aún no migrados). */
    private function columnasExistentes(string $tabla): array
    {
        if (!isset($this->colsCache[$tabla])) {
            $st = $this->db->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'"
            );
            $st->execute([$tabla]);
            $this->colsCache[$tabla] = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        return $this->colsCache[$tabla];
    }

    /** Series REALMENTE usadas en proformas guardadas (para el filtro "Serie" del buscador). */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM proformas_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /**
     * Estados (en minúsculas) que la empresa REALMENTE tiene en sus proformas, para
     * completar el select "Estado" del modal de filtros con los que no son del flujo
     * actual (p. ej. los migrados, como "emitida").
     */
    public function getEstadosUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT LOWER(estado) AS estado
                FROM proformas_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND estado IS NOT NULL AND estado != ''
                ORDER BY 1";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Vendedores con alguna proforma en la empresa (select "Vendedor" del modal de filtros). */
    public function getVendedoresConProformas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT ven.id, ven.nombre
                FROM proformas_cabecera p
                JOIN vendedores ven ON ven.id = p.id_vendedor
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY ven.nombre";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Usuarios que han registrado alguna proforma en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConProformas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM proformas_cabecera p
                JOIN usuarios u ON u.id = p.id_usuario
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY u.nombre";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las proformas (pestaña "Detalles" del modal de filtros):
     * devuelve cada producto cotizado o campo de información adicional que coincide con
     * el texto, junto con la proforma a la que pertenece. Mismo alcance que el listado
     * (empresa, no eliminadas, registros propios por id_usuario; el listado no filtra
     * por ambiente).
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "p.id_empresa = :id_empresa AND p.eliminado = false";
        if ($idUsuario !== null) {
            $whereBase .= " AND p.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.codigo_principal', 'd.codigo_auxiliar', 'd.descripcion', 'd.cantidad::text',
             'd.precio_unitario::text', 'd.precio_total_sin_impuesto::text'],
            $q, $params, 'dt'
        );
        $condAdic = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['a.nombre', 'a.valor'],
            $q, $params, 'ad'
        );
        if ($condDet === '' || $condAdic === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT p.id, CONCAT(p.establecimiento,'-',p.punto_emision,'-',p.secuencial) AS numero,
                           p.fecha_emision, p.estado, c.nombre AS cliente
                    FROM proformas_cabecera p
                    INNER JOIN clientes c ON c.id = p.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(d.codigo_principal,''), d.codigo_auxiliar) AS tipo,
                           d.descripcion,
                           d.cantidad,
                           d.precio_total_sin_impuesto AS monto,
                           b.id AS id_proforma, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM proformas_detalle d
                    JOIN base b ON b.id = d.id_proforma
                    WHERE $condDet
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           NULL AS monto,
                           b.id AS id_proforma, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM proformas_adicional a
                    JOIN base b ON b.id = a.id_proforma
                    WHERE $condAdic
                ) x
                ORDER BY x.fecha_emision DESC, x.id_proforma DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL con la que se ordena. Es la whitelist del ORDER BY y el mapa que
     * necesita `OrdenListado::clausula()` para encadenar varias columnas.
     */
    public const MAPA_ORDEN = [
        'id'              => 'p.id',
        'fecha_emision'   => 'p.fecha_emision',
        'secuencial'      => 'p.secuencial',
        'importe_total'   => 'p.importe_total',
        'estado'          => 'p.estado',
        'estado_correo'   => 'p.estado_correo',
        'observaciones'   => 'p.observaciones',
        // De un JOIN o compuestas: el número que se ve no es una columna.
        'cliente_nombre'  => 'c.nombre',
        'cliente_ruc'     => 'c.identificacion',
        'vendedor_nombre' => 'ven.nombre',
        'numero'          => "p.establecimiento||'-'||p.punto_emision||'-'||p.secuencial",
    ];

    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_emision',
        string $ordenDir = 'DESC',
        ?int $idUsuario = null,
        array $ordenMulti = []
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE p.id_empresa = :id_empresa AND p.eliminado = FALSE";

        if ($idUsuario !== null) {
            $where .= " AND p.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        // Texto libre: las columnas del listado y lo que identifica la proforma aunque no
        // sea columna (cada palabra en cualquier columna, sin importar tildes). Decisión
        // del usuario: las columnas Estado y Correo NO entran en el texto libre (se
        // filtran solo desde el modal de filtros).
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    "CONCAT(p.establecimiento,'-',p.punto_emision,'-',p.secuencial)", // Número
                    'p.secuencial',
                    'p.fecha_emision::text',                                          // Fecha
                    'c.nombre',                                                       // Cliente
                    'c.identificacion',                                               // RUC/CI
                    'ven.nombre',                                                     // Vendedor
                    'p.importe_total::text',                                          // Total
                    'p.observaciones',                                                // Observaciones
                    // Fuera del listado, pero identifican la proforma:
                    'u.nombre',                                                       // usuario que la registró
                    "(SELECT STRING_AGG(CONCAT_WS(' ', pd.codigo_principal, pd.codigo_auxiliar, pd.descripcion), ' ') FROM proformas_detalle pd WHERE pd.id_proforma = p.id)",
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'       => 'c.nombre',
                'ruc'           => 'c.identificacion',
                'numero'        => "CONCAT(p.establecimiento,'-',p.punto_emision,'-',p.secuencial)",
                'obs'           => 'p.observaciones',
                'observaciones' => 'p.observaciones',
                'vendedor'      => 'ven.nombre',
                'usuario'       => 'u.nombre',
            ],
            'exacto' => [
                // En minúsculas: los datos migrados traen el estado en mayúsculas (ANULADA).
                'estado'  => 'LOWER(p.estado)',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del modal de proforma.
                'serie'   => "CONCAT(p.establecimiento,'-',p.punto_emision)",
                'correo'        => "COALESCE(NULLIF(p.estado_correo,''),'pendiente')",
                'estado_correo' => "COALESCE(NULLIF(p.estado_correo,''),'pendiente')",
                'id_vendedor'   => 'p.id_vendedor',
                'id_usuario'    => 'p.id_usuario',
                // facturada:si / facturada:no → ya se convirtió en factura
                'facturada'     => "CASE WHEN p.id_factura_convertida IS NULL THEN 'no' ELSE 'si' END",
                // aprobacion_cliente:si / no → el cliente la aprobó desde el enlace del correo
                'aprobacion_cliente' => "CASE WHEN p.aprobacion_cliente_fecha IS NULL THEN 'no' ELSE 'si' END",
                // vigencia:vigente / vencida → fecha de emisión + días de vigencia contra hoy
                'vigencia'      => "CASE WHEN p.fecha_emision + COALESCE(p.dias_vigencia, 0) >= CURRENT_DATE THEN 'vigente' ELSE 'vencida' END",
            ],
            'fecha'   => [
                'fecha'   => 'p.fecha_emision',
                'fecha_emision'   => 'p.fecha_emision',
                'fecha_convertida' => 'p.fecha_convertida',
            ],
            'numerico' => [
                'total'   => 'p.importe_total',
                'monto'   => 'p.importe_total',
                'subtotal'  => 'p.total_sin_impuestos',
                'descuento' => 'p.total_descuento',
                'dias_vigencia' => 'COALESCE(p.dias_vigencia, 0)',
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'p.secuencial::numeric',
            ],
        ]);

        $joins = "INNER JOIN clientes c ON p.id_cliente = c.id
                  LEFT JOIN vendedores ven ON p.id_vendedor = ven.id
                  LEFT JOIN usuarios u ON p.id_usuario = u.id";

        $sqlCount = "SELECT COUNT(*) FROM proformas_cabecera p $joins $where";
        $total = $this->query($sqlCount, $params)->fetchColumn();

        // Una o varias columnas (Shift+clic), siempre validadas contra MAPA_ORDEN,
        // con p.id como desempate para que las filas empatadas no bailen entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'p.fecha_emision', 'p.id DESC');
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';


        $sql = "SELECT p.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.email          AS cliente_email,
                       ven.nombre       AS vendedor_nombre,
                       u.nombre         AS usuario_nombre
                FROM proformas_cabecera p $joins
                $where
                $orderBy
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll();
        return ['rows' => $rows, 'total' => (int) $total];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT p.*,
                       c.nombre              AS cliente_nombre,
                       c.identificacion      AS cliente_ruc,
                       c.direccion           AS cliente_direccion,
                       c.email               AS cliente_email,
                       c.tipo_id             AS cliente_tipo_id,
                       ven.nombre            AS vendedor_nombre,
                       u.nombre              AS usuario_nombre
                FROM proformas_cabecera p
                INNER JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN vendedores ven ON p.id_vendedor = ven.id
                LEFT JOIN usuarios u ON p.id_usuario = u.id
                WHERE p.id = ? AND p.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    /** Proforma por su token de aprobación pública (con datos del cliente). */
    public function getPorTokenAprobacion(string $token): ?array
    {
        $sql = "SELECT p.*,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.email          AS cliente_email
                FROM proformas_cabecera p
                INNER JOIN clientes c ON p.id_cliente = c.id
                INNER JOIN empresas emp ON emp.id = p.id_empresa
                WHERE p.aprobacion_token = ? AND p.eliminado = FALSE
                  AND emp.estado = '1' AND emp.eliminado = FALSE
                LIMIT 1";
        $row = $this->query($sql, [$token])->fetch();
        return $row ?: null;
    }

    /** Fija (o reutiliza) el token de aprobación pública de una proforma. */
    public function setTokenAprobacion(int $id, string $token): void
    {
        $this->query(
            "UPDATE proformas_cabecera SET aprobacion_token = ?, updated_at = NOW() WHERE id = ?",
            [$token, $id]
        );
    }

    /** Marca la proforma como "correo enviado" (para el listado general). */
    public function marcarCorreoEnviado(int $id): void
    {
        $this->query(
            "UPDATE proformas_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /** Registra la aprobación del cliente: estado = aprobada + comentario/IP/fecha. */
    public function registrarAprobacionCliente(int $id, string $comentario, string $ip): void
    {
        $this->query(
            "UPDATE proformas_cabecera
                SET estado = 'aprobada',
                    aprobacion_cliente_comentario = ?,
                    aprobacion_cliente_ip = ?,
                    aprobacion_cliente_fecha = NOW(),
                    updated_at = NOW()
              WHERE id = ?",
            [($comentario !== '' ? $comentario : null), $ip, $id]
        );
    }

    /**
     * ¿La serie ya tiene ese número? Compara por texto Y por valor numérico: el motor de
     * secuenciales razona con números (CAST a BIGINT) mientras esta tabla guarda texto, así
     * que un '16' heredado de una migración y el '000000016' que calcula el sistema son el
     * MISMO número en cadenas distintas — comparando solo texto, ese choque pasaba
     * desapercibido aquí (y también en el índice único uq_proformas_numero).
     */
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $mismoNumero = 'secuencial = ?';
        $params      = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial];
        if (preg_match('/^[0-9]+$/', trim($secuencial))) {
            // CASE (no AND) para fijar el orden de evaluación: sin él PostgreSQL puede
            // intentar el CAST sobre un secuencial no numérico y abortar la consulta.
            $mismoNumero .= " OR CASE WHEN TRIM(secuencial) ~ '^[0-9]+$'
                                      THEN CAST(TRIM(secuencial) AS BIGINT) = CAST(? AS BIGINT)
                                      ELSE FALSE END";
            $params[] = $secuencial;
        }

        $sql = "SELECT COUNT(*) FROM proformas_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND ({$mismoNumero}) AND eliminado = FALSE";
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function insertCabecera(array $data): int
    {
        // condiciones_html es opcional hasta desplegar el SQL (20260903_proformas_condiciones.sql).
        $conCondiciones = in_array('condiciones_html', $this->columnasExistentes('proformas_cabecera'), true);
        $sql = "INSERT INTO proformas_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    id_vendedor, fecha_emision, establecimiento, punto_emision, secuencial,
                    tipo_ambiente, dias_vigencia,
                    total_sin_impuestos, total_descuento, total_ice, importe_total, moneda,
                    estado, observaciones, created_by, updated_by" . ($conCondiciones ? ", condiciones_html" : "") . "
                ) VALUES (
                    ?,?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,?,?,  ?,?,?,?" . ($conCondiciones ? ",?" : "") . "
                ) RETURNING id";
        $params = [
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            (int) $data['id_usuario'],
            !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null,
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            $data['tipo_ambiente'] ?? '1',
            (int) ($data['dias_vigencia'] ?? 15),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['total_ice'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            $data['estado'] ?? 'borrador',
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ];
        if ($conCondiciones) {
            $params[] = ($data['condiciones_html'] ?? '') !== '' ? (string) $data['condiciones_html'] : null;
        }
        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $conCondiciones = in_array('condiciones_html', $this->columnasExistentes('proformas_cabecera'), true);
        $sql = "UPDATE proformas_cabecera SET
                    id_establecimiento    = ?,
                    id_punto_emision      = ?,
                    id_cliente            = ?,
                    id_vendedor           = ?,
                    fecha_emision         = ?,
                    establecimiento       = ?,
                    punto_emision         = ?,
                    secuencial            = ?,
                    dias_vigencia         = ?,
                    total_sin_impuestos   = ?,
                    total_descuento       = ?,
                    total_ice             = ?,
                    importe_total         = ?,
                    estado                = ?,
                    observaciones         = ?," . ($conCondiciones ? "
                    condiciones_html      = ?," : "") . "
                    updated_by            = ?,
                    updated_at            = NOW()
                WHERE id = ?";
        $params = [
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null,
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            (int) ($data['dias_vigencia'] ?? 15),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['total_ice'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            $data['estado'] ?? 'borrador',
            !empty($data['observaciones']) ? $data['observaciones'] : null,
        ];
        if ($conCondiciones) {
            $params[] = ($data['condiciones_html'] ?? '') !== '' ? (string) $data['condiciones_html'] : null;
        }
        $params[] = (int) $data['id_usuario'];
        $params[] = $id;
        $this->query($sql, $params);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE proformas_cabecera SET estado = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
        $this->query($sql, [$estado, $idUsuario, $id]);
    }

    public function marcarConvertida(int $id, int $idFactura, int $idUsuario): void
    {
        $sql = "UPDATE proformas_cabecera SET estado = 'convertida', id_factura_convertida = ?, fecha_convertida = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?";
        $this->query($sql, [$idFactura, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE proformas_cabecera
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->query($sql, [$idUsuario, $idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function getDetalles(int $idProforma): array
    {
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) AS producto_nombre, p.codigo AS producto_codigo,
                       p.imagen AS producto_imagen
                FROM proformas_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                WHERE d.id_proforma = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idProforma])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM proformas_detalle_impuestos WHERE id_proforma_detalle = ?";
        return $this->query($sql, [$idDetalle])->fetchAll();
    }

    /**
     * Impuestos de VARIAS líneas en UNA sola consulta, agrupados por línea.
     *
     * Evita el N+1 de llamar a getImpuestosDetalle() dentro del bucle de
     * detalles: con la base en un servidor remoto, un documento de 30 líneas
     * pagaba 30 viajes de red solo para esto.
     *
     * @param int[] $idsDetalle
     * @return array<int,array> id de la línea => sus impuestos
     */
    public function getImpuestosPorDetalles(array $idsDetalle): array
    {
        $ids = array_values(array_unique(array_filter(array_map("intval", $idsDetalle))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(",", array_fill(0, count($ids), "?"));
        $sql = "SELECT * FROM proformas_detalle_impuestos WHERE id_proforma_detalle IN ($ph)";

        $porDetalle = [];
        foreach ($this->query($sql, $ids)->fetchAll() as $imp) {
            $porDetalle[(int) $imp["id_proforma_detalle"]][] = $imp;
        }
        return $porDetalle;
    }

    public function getInfoAdicional(int $idProforma): array
    {
        $sql = "SELECT * FROM proformas_adicional WHERE id_proforma = ?";
        return $this->query($sql, [$idProforma])->fetchAll();
    }

    public function deleteDetalles(int $idProforma): void
    {
        // Primero borra impuestos de los detalles
        $this->query("DELETE FROM proformas_detalle_impuestos WHERE id_proforma_detalle IN (SELECT id FROM proformas_detalle WHERE id_proforma = ?)", [$idProforma]);
        $this->query("DELETE FROM proformas_detalle WHERE id_proforma = ?", [$idProforma]);
    }

    public function deleteInfoAdicional(int $idProforma): void
    {
        $this->query("DELETE FROM proformas_adicional WHERE id_proforma = ?", [$idProforma]);
    }

    public function insertDetalle(array $data): int
    {
        $cols = [
            'id_proforma', 'id_producto', 'id_unidad_medida',
            'codigo_principal', 'codigo_auxiliar', 'descripcion',
            'cantidad', 'precio_unitario', 'descuento', 'precio_total_sin_impuesto', 'id_tarifa_iva',
        ];
        $params = [
            (int) $data['id_proforma'],
            !empty($data['id_producto']) ? (int) $data['id_producto'] : null,
            !empty($data['id_unidad_medida']) ? (int) $data['id_unidad_medida'] : null,
            $data['codigo_principal'] ?? '',
            !empty($data['codigo_auxiliar']) ? $data['codigo_auxiliar'] : null,
            $data['descripcion'],
            $data['cantidad'],
            $data['precio_unitario'],
            $data['descuento'],
            $data['precio_total_sin_impuesto'],
            (int) ($data['id_tarifa_iva'] ?? 0),
        ];

        // Campo opcional (requiere la migración proformas_detalle_info_adicional.sql);
        // detectado en runtime para no romper instalaciones donde aún no se ha aplicado.
        if (in_array('info_adicional', $this->columnasExistentes('proformas_detalle'), true)) {
            $cols[]   = 'info_adicional';
            $params[] = !empty($data['adicional']) ? (string) $data['adicional'] : null;
        }

        $colSql = implode(', ', $cols);
        $valSql = implode(', ', array_fill(0, count($params), '?'));
        $sql    = "INSERT INTO proformas_detalle ({$colSql}) VALUES ({$valSql}) RETURNING id";

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO proformas_detalle_impuestos
                    (id_proforma_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
                VALUES (?,?,?,?,?,?)";
        $this->query($sql, [
            $data['id_proforma_detalle'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            $data['tarifa'],
            $data['base_imponible'],
            $data['valor'],
        ]);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO proformas_adicional (id_proforma, nombre, valor) VALUES (?,?,?)";
        $this->query($sql, [$data['id_proforma'], $data['nombre'], $data['valor']]);
    }
}
