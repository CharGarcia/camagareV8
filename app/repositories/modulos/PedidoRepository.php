<?php

namespace App\Repositories\Modulos;

use App\core\Database;
use PDO;

class PedidoRepository {
    private $db;

    /** Caché por request: ¿existe ventas_detalle.id_pedido_detalle? (migración database/agregar_id_pedido_detalle_ventas.sql). */
    private ?bool $columnaVentasDetalleExiste = null;

    /** Caché por request: ¿existe pedidos_cabecera.id_proforma? (migración database/migrations/20260921_add_id_proforma_to_pedidos.sql). */
    private ?bool $columnaProformaExiste = null;

    /**
     * Orden lógico del estado para el ORDER BY (no alfabético).
     * Los estados reales son Pendiente / Procesado / Anulado (la migración mapea
     * status 1/2/3); "Facturado" existe como opción del buscador, por eso se
     * contempla aquí. Cualquier valor no listado cae al final (99).
     */
    private const ORDEN_ESTADO_SQL = "CASE UPPER(TRIM(COALESCE(p.estado, '')))
                                          WHEN 'PENDIENTE' THEN 1
                                          WHEN 'PROCESADO' THEN 2
                                          WHEN 'FACTURADO' THEN 3
                                          WHEN 'ANULADO'   THEN 4
                                          ELSE 99
                                      END";

    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL con la que se ordena. Es la whitelist del ORDER BY —lo único
     * que puede llegar al SQL sale de aquí— y también el mapa que necesita
     * `OrdenListado::clausula()` para encadenar varias columnas (Shift+clic).
     */
    public const MAPA_ORDEN = [
        // El número que se ve en pantalla no es una columna: se arma con las tres partes.
        'numero_pedido'          => "p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial",
        'establecimiento'        => 'p.establecimiento',
        'punto_emision'          => 'p.punto_emision',
        'secuencial'             => 'p.secuencial',
        'fecha_pedido'           => 'p.fecha_pedido',
        'cliente_nombre'         => 'c.nombre',
        'fecha_entrega'          => 'p.fecha_entrega',
        'rango_horario'          => 'p.hora_inicial_entrega',
        'responsable_entrega'    => 'rt.nombre',
        'observaciones'          => 'p.observaciones',
        'observaciones_internas' => 'p.observaciones_internas',
        // El estado NO se ordena alfabéticamente (ASC dejaría: Anulado, Facturado,
        // Pendiente, Procesado) sino por el orden lógico del flujo del pedido:
        // primero lo que falta atender, al final lo cerrado.
        // ASC = Pendiente → Procesado → Facturado → Anulado; DESC lo invierte.
        'estado'                 => self::ORDEN_ESTADO_SQL,
        'created_at'             => 'p.created_at',
    ];

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /** Ejecuta SQL con parámetros y devuelve las filas (lo que espera ListadoPaginado). */
    private function query(string $sql, array $params = []): array {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fecha/hora como texto, con expresiones INMUTABLES (to_char() y los casts a texto son
     * STABLE: dependen del DateStyle de la sesión y PostgreSQL no los admite en un índice).
     * Producen exactamente el mismo texto que antes: 'YYYY-MM-DD' y 'HH24:MI'.
     */
    private static function fechaTexto(string $col): string {
        return \App\Helpers\MotorBusqueda::fechaIso($col);
    }

    private static function horaTexto(string $col): string {
        return \App\Helpers\MotorBusqueda::horaHm($col);
    }

    /**
     * Texto propio del pedido que entra en la búsqueda libre: número, observaciones,
     * observaciones internas, fecha de emisión, fecha de entrega y rango horario.
     * `$a` es el prefijo del alias ('p.' en la consulta, '' en el CREATE INDEX): la MISMA
     * expresión alimenta la consulta y el índice, así que no se pueden desalinear.
     */
    private static function exprPedido(string $a = ''): string {
        // Número en formato canónico 000-000-000000000 (SecuencialFormato::sqlNumeroCompleto):
        // hay pedidos con el secuencial guardado sin los ceros a la izquierda ('16' en vez de
        // '000000016'), y así se encuentran escribiendo el número como se lee en el documento.
        return \App\Helpers\SecuencialFormato::sqlNumeroCompleto("{$a}establecimiento", "{$a}punto_emision", "{$a}secuencial")
             . " || ' ' || COALESCE({$a}observaciones, '')"
             . " || ' ' || COALESCE({$a}observaciones_internas, '')"
             . " || ' ' || " . self::fechaTexto("{$a}fecha_pedido")
             . " || ' ' || " . self::fechaTexto("{$a}fecha_entrega")
             . " || ' ' || " . self::horaTexto("{$a}hora_inicial_entrega")
             . " || ' - ' || " . self::horaTexto("{$a}hora_maxima_entrega");
    }

    /** Número de comprobante (establecimiento-punto-secuencial) de otra tabla. */
    private static function exprNumero(string $a = ''): string {
        return "COALESCE({$a}establecimiento, '') || '-' || COALESCE({$a}punto_emision, '') || '-' || COALESCE({$a}secuencial, '')";
    }

    /**
     * Fuentes del texto libre del listado (ver App\Helpers\MotorBusqueda). Cada una es un
     * conjunto que PostgreSQL resuelve UNA vez por palabra con su índice trigram, en lugar
     * de recalcularse por cada pedido de la empresa.
     *
     * Qué busca: lo que se VE en el listado — número de pedido, fechas de pedido y entrega,
     * rango horario, observaciones, observaciones internas, cliente (nombre) y responsable
     * de entrega.
     *
     * Qué NO busca, por decisión del usuario (17-09-2026), y dónde se busca en su lugar. El
     * criterio es el mismo de Facturas, Compras, notas, retenciones y consignaciones: si un
     * dato no está en una columna del listado, el pedido aparecía sin que se viera por qué:
     *   - Estado → modal de filtros (decisión anterior).
     *   - Identificación del cliente → filtro `ruc:` / `identificacion:`.
     *   - Usuario que registró → filtro del modal.
     *   - Productos pedidos y el número de las consignaciones o facturas que consumieron el
     *     pedido → pestaña "Detalles" del modal de filtros (buscarEnDetalles()), que busca las
     *     tres cosas y SÍ dice cuál coincidió.
     * Con eso quedan sin uso los índices idx_trgm_productos, idx_trgm_consignaciones_numero e
     * idx_trgm_ventas_numero. Ver database/20260917d_ajuste_busqueda_pedidos.sql.
     *
     * A propósito NO se filtra por empresa en las fuentes que cuelgan de otra tabla: el
     * listado ya está acotado a la empresa por fuera, y filtrar aquí cambiaría el resultado
     * si un dato quedó cruzado entre empresas.
     */
    private function fuentesBusqueda(): array {
        $fuentes = [
            // Datos propios del pedido
            [
                'sql'    => "p.id IN (SELECT bx.id FROM pedidos_cabecera bx WHERE bx.id_empresa = :id_empresa AND {cond})",
                'expr'   => self::exprPedido('bx.'),
                'indice' => ['tabla' => 'pedidos_cabecera', 'nombre' => 'idx_trgm_pedidos_cabecera', 'expr' => self::exprPedido()],
            ],
            // Cliente: SOLO el nombre, que es la columna del listado. El índice compartido
            // idx_trgm_clientes indexa "nombre + identificación" y un GIN trigram solo sirve
            // para la MISMA expresión, así que se usa el de nombre (el mismo que
            // Consignaciones de Venta).
            [
                'sql'    => "p.id_cliente IN (SELECT cx.id FROM clientes cx WHERE {cond})",
                'expr'   => "COALESCE(cx.nombre, '')",
                'indice' => ['tabla' => 'clientes', 'nombre' => 'idx_trgm_clientes_nombre', 'expr' => "COALESCE(nombre, '')"],
            ],
            // Responsable de entrega (columna del listado): tabla chica, sin índice.
            [
                'sql'  => "p.id_responsable_entrega IN (SELECT rx.id FROM responsables_traslado rx WHERE {cond})",
                'expr' => "COALESCE(rx.nombre, '')",
            ],
        ];

        return $fuentes;
    }

    /** SQL de los índices que necesita la búsqueda de este módulo (para database/*.sql). */
    public function sqlIndicesBusqueda(): array {
        return \App\Helpers\MotorBusqueda::sqlIndices($this->fuentesBusqueda());
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null,
        array $ordenMulti = []
    ): array {
        // $ordenMulti llega cuando el llamador usa OrdenListado (permite ordenar por
        // varias columnas); si viene vacío se arma desde $ordenCol/$ordenDir.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );

        $whereSql = "WHERE p.id_empresa = :id_empresa AND p.eliminado = false AND p.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $params   = [':id_empresa' => $idEmpresa];

        // Registros propios (§6): si el usuario no tiene "acceso total" en el
        // submódulo, solo ve lo que él mismo creó. $idUsuarioFiltro llega null
        // cuando el usuario SÍ tiene acceso total (ver todos).
        if ($idUsuarioFiltro !== null) {
            $whereSql .= " AND p.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Documentos que consumieron el pedido (Consignaciones de Venta y, si ya está la
        // migración de ventas_detalle.id_pedido_detalle, Facturas de Venta). Su número entra
        // en el texto libre —ahora como una fuente del motor de búsqueda, ver
        // fuentesBusqueda()— y aquí queda solo lo que necesita el filtro "documentos:si/no",
        // que se evalúa únicamente cuando el usuario lo usa.
        $existeDocs = "EXISTS (SELECT 1 FROM pedidos_detalle pde
                                 JOIN consignaciones_ventas_detalles cvd2 ON cvd2.id_pedido_detalle = pde.id AND cvd2.eliminado = false
                                 JOIN consignaciones_ventas cv2 ON cv2.id = cvd2.id_consignacion AND cv2.eliminado = false
                                WHERE pde.id_pedido = p.id)";
        if ($this->columnaVentasDetalleExiste()) {
            $existeDocs = "({$existeDocs} OR EXISTS (SELECT 1 FROM pedidos_detalle pdf2
                                 JOIN ventas_detalle vd2 ON vd2.id_pedido_detalle = pdf2.id
                                 JOIN ventas_cabecera v2 ON v2.id = vd2.id_venta AND v2.eliminado = false
                                WHERE pdf2.id_pedido = p.id))";
        }
        // Total del pedido: no es columna de la cabecera, sale de sus líneas.
        $sqlTotal = "(SELECT COALESCE(SUM(pdt.total), 0) FROM pedidos_detalle pdt WHERE pdt.id_pedido = p.id AND pdt.eliminado = false)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        // Texto libre: mismas columnas de siempre (ver fuentesBusqueda()), pero armadas como
        // conjuntos que PostgreSQL resuelve una sola vez con índices trigram en vez de
        // recalcular por cada pedido de la empresa. Decisión del usuario: la columna Estado
        // NO entra en el texto libre (se filtra solo desde el modal de filtros).
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\MotorBusqueda::condicion(
                $this->fuentesBusqueda(),
                $parsed['texto_libre'],
                $params,
                'b'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'cliente'       => 'c.nombre',
                'responsable'   => 'rt.nombre',
                'observaciones' => 'p.observaciones',
                'obs'           => 'p.observaciones',
                'obs_internas'  => 'p.observaciones_internas',
                'numero'        => "(p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial)",
                'ruc'           => 'c.identificacion',
                'identificacion' => 'c.identificacion',
            ],
            'exacto' => [
                'estado' => 'p.estado',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del buscador.
                'serie'  => "CONCAT(p.establecimiento,'-',p.punto_emision)",
                'id_responsable' => 'p.id_responsable_entrega',
                'id_usuario'     => 'p.created_by',
                // documentos:si → el pedido ya se consumió en alguna consignación o factura.
                'documentos'     => "CASE WHEN {$existeDocs} THEN 'si' ELSE 'no' END",
            ],
            'fecha' => [
                'fecha'         => 'p.fecha_pedido',
                'fecha_pedido'  => 'p.fecha_pedido',
                'fecha_entrega' => 'p.fecha_entrega',
            ],
            'numerico' => [
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'p.secuencial::numeric',
                'total'      => $sqlTotal,
                'monto'      => $sqlTotal,
            ],
        ]);

        // Una o varias columnas, siempre validadas contra MAPA_ORDEN, con p.id como
        // desempate para que las filas empatadas no bailen entre páginas.
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'p.created_at', 'p.id DESC');

        // UNA sola consulta (ver App\Helpers\ListadoPaginado): antes había un COUNT y un
        // SELECT por separado con el mismo WHERE, así que todo el trabajo del texto libre se
        // hacía DOS veces —medido con 20.000 pedidos: 8,3 s por búsqueda, la mitad en cada
        // consulta—. El JOIN a clientes es INNER y el ORDER BY puede usar c.nombre / rt.nombre,
        // así que ambos joins van también en la fase que filtra y cuenta.
        $joins = "JOIN clientes c ON p.id_cliente = c.id
                  LEFT JOIN responsables_traslado rt ON p.id_responsable_entrega = rt.id";

        $resultado = \App\Helpers\ListadoPaginado::consultar(
            fn(string $sql, array $prm): array => $this->query($sql, $prm),
            [
                'tabla'       => 'pedidos_cabecera',
                'alias'       => 'p',
                'joinsFiltro' => $joins,
                'joinsFinal'  => $joins,
                'where'       => $whereSql,
                'orderBy'     => $orderBy,
                'select'      => "p.*,
                                  (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido,
                                  c.nombre AS cliente_nombre,
                                  rt.nombre AS responsable_entrega",
                'perPage'     => $perPage,
                'offset'      => $perPage > 0 ? ($page - 1) * $perPage : 0,
                'conBusqueda' => trim($buscar) !== '',
            ],
            $params
        );

        return [
            'total' => $resultado['total'],
            'rows'  => $resultado['rows'],
        ];
    }

    /** Series REALMENTE usadas en pedidos guardados (para el filtro "Serie" del buscador). */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM pedidos_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Responsables de entrega usados en algún pedido de la empresa (select del modal de filtros). */
    public function getResponsablesConPedidos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT rt.id, rt.nombre
                FROM pedidos_cabecera p
                JOIN responsables_traslado rt ON rt.id = p.id_responsable_entrega
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY rt.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que han registrado algún pedido en la empresa (select "Usuario que registró"). */
    public function getUsuariosConPedidos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM pedidos_cabecera p
                JOIN usuarios u ON u.id = p.created_by
                WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de los pedidos (pestaña "Detalles" del modal de filtros):
     * devuelve cada producto pedido y cada documento que consumió el pedido
     * (consignación de venta / factura de venta) que coincide con el texto, junto con
     * el pedido al que pertenece. Mismo alcance que el listado (empresa, no
     * eliminados, ambiente y registros propios por created_by).
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "p.id_empresa = :id_empresa AND p.eliminado = false
                      AND p.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND p.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['pr.codigo', 'pr.nombre', 'd.cantidad::text', 'd.precio_unitario::text', 'd.total::text'],
            $q, $params, 'dt'
        );
        $condCons = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ["(cv.establecimiento || '-' || cv.punto_emision || '-' || cv.secuencial)", 'pr.codigo', 'pr.nombre', 'cvd.cantidad::text'],
            $q, $params, 'cs'
        );
        if ($condDet === '' || $condCons === '') {
            return [];
        }
        $ramaFactura = '';
        if ($this->columnaVentasDetalleExiste()) {
            $condFac = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ["(v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial)", 'pr.codigo', 'pr.nombre', 'vd.cantidad::text'],
                $q, $params, 'fc'
            );
            if ($condFac === '') {
                return [];
            }
            $ramaFactura = "
                    UNION ALL
                    SELECT 'FACTURA' AS origen,
                           (v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial) AS tipo,
                           CONCAT_WS(' ', NULLIF(pr.codigo,''), pr.nombre) AS descripcion,
                           vd.cantidad,
                           v.fecha_emision::text AS fecha_doc,
                           v.estado AS estado_doc,
                           b.id AS id_pedido, b.numero, b.fecha_pedido, b.estado, b.cliente
                    FROM pedidos_detalle d
                    JOIN base b ON b.id = d.id_pedido
                    JOIN ventas_detalle vd ON vd.id_pedido_detalle = d.id
                    JOIN ventas_cabecera v ON v.id = vd.id_venta AND v.eliminado = false
                    LEFT JOIN productos pr ON pr.id = d.id_producto
                    WHERE $condFac";
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT p.id, (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero,
                           p.fecha_pedido, p.estado, c.nombre AS cliente
                    FROM pedidos_cabecera p
                    JOIN clientes c ON c.id = p.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           pr.codigo AS tipo,
                           pr.nombre AS descripcion,
                           d.cantidad,
                           NULL AS fecha_doc,
                           NULL AS estado_doc,
                           b.id AS id_pedido, b.numero, b.fecha_pedido, b.estado, b.cliente
                    FROM pedidos_detalle d
                    JOIN base b ON b.id = d.id_pedido
                    LEFT JOIN productos pr ON pr.id = d.id_producto
                    WHERE d.eliminado = false AND $condDet
                    UNION ALL
                    SELECT 'CONSIGNACION' AS origen,
                           (cv.establecimiento || '-' || cv.punto_emision || '-' || cv.secuencial) AS tipo,
                           CONCAT_WS(' ', NULLIF(pr.codigo,''), pr.nombre) AS descripcion,
                           cvd.cantidad,
                           cv.fecha_emision::text AS fecha_doc,
                           cv.estado AS estado_doc,
                           b.id AS id_pedido, b.numero, b.fecha_pedido, b.estado, b.cliente
                    FROM pedidos_detalle d
                    JOIN base b ON b.id = d.id_pedido
                    JOIN consignaciones_ventas_detalles cvd ON cvd.id_pedido_detalle = d.id AND cvd.eliminado = false
                    JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion AND cv.eliminado = false
                    LEFT JOIN productos pr ON pr.id = d.id_producto
                    WHERE $condCons
                    $ramaFactura
                ) x
                ORDER BY x.fecha_pedido DESC, x.id_pedido DESC, x.origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id, $id_empresa) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                       c.email as cliente_email,
                       uc.nombre as creado_por_nombre, uu.nombre as modificado_por_nombre,
                       rt.nombre as responsable_entrega,
                       -- Pedidos no guarda vendedor propio: el asesor del pedido es el
                       -- que tiene asignado el cliente (clientes.id_vendedor), igual
                       -- criterio que usa Factura de Venta / Consignaciones.
                       v.nombre as vendedor_nombre,
                       (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido
                FROM pedidos_cabecera p
                JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN usuarios uc ON p.created_by = uc.id
                LEFT JOIN usuarios uu ON p.updated_by = uu.id
                LEFT JOIN responsables_traslado rt ON p.id_responsable_entrega = rt.id
                LEFT JOIN vendedores v ON v.id = c.id_vendedor
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'id_empresa' => $id_empresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDetalles($id_pedido, $id_empresa) {
        $sql = "SELECT d.*, p.nombre as producto_nombre, p.codigo as producto_codigo
                FROM pedidos_detalle d
                JOIN productos p ON d.id_producto = p.id
                WHERE d.id_pedido = :id_pedido 
                AND d.eliminado = false";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $id_pedido]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿Ya se desplegó la migración que agrega ventas_detalle.id_pedido_detalle? Degradación segura si no. */
    private function columnaVentasDetalleExiste(): bool {
        if ($this->columnaVentasDetalleExiste === null) {
            $sql = "SELECT EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name = 'ventas_detalle' AND column_name = 'id_pedido_detalle'
                    )";
            $this->columnaVentasDetalleExiste = (bool) $this->db->query($sql)->fetchColumn();
        }
        return $this->columnaVentasDetalleExiste;
    }

    /** ¿Ya se desplegó database/migrations/20260921_add_id_proforma_to_pedidos.sql? Degradación segura si no. */
    public function columnaProformaExiste(): bool {
        if ($this->columnaProformaExiste === null) {
            $sql = "SELECT EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name = 'pedidos_cabecera' AND column_name = 'id_proforma'
                    )";
            $this->columnaProformaExiste = (bool) $this->db->query($sql)->fetchColumn();
        }
        return $this->columnaProformaExiste;
    }

    /**
     * Pedidos (no eliminados) generados desde una proforma. Espejo de
     * FacturaVentaRepository::getPorProforma(): alimenta la pestaña "Pedidos" del
     * modal de proforma y el aviso de "ya existe un pedido" antes de crear otro.
     *
     * El total del pedido no es columna de la cabecera: sale de sus líneas.
     */
    public function getPorProforma(int $idProforma, int $idEmpresa): array {
        if (!$this->columnaProformaExiste()) {
            return [];   // migración aún no aplicada: no hay vínculo que consultar
        }
        $sql = "SELECT p.id, p.fecha_pedido, p.establecimiento, p.punto_emision, p.secuencial,
                       p.estado, p.fecha_entrega,
                       (SELECT COALESCE(SUM(d.total), 0) FROM pedidos_detalle d
                         WHERE d.id_pedido = p.id AND d.eliminado = false) AS importe_total
                FROM pedidos_cabecera p
                WHERE p.id_proforma = :id_proforma AND p.id_empresa = :id_empresa AND p.eliminado = false
                ORDER BY p.fecha_pedido DESC, p.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_proforma' => $idProforma, 'id_empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cantidad ya consumida (Consignación de Venta + Factura de Venta, no anuladas)
     * por cada línea de pedidos_detalle indicada. Solo devuelve entradas para las
     * líneas que sí tienen consumo (las demás se asumen en 0).
     *
     * @param int[] $idsDetalle IDs de pedidos_detalle a consultar.
     * @return array<int,float> [id_pedido_detalle => cantidad_consumida]
     */
    public function getCantidadConsumidaPorDetalle(array $idsDetalle): array {
        $idsDetalle = array_values(array_unique(array_map('intval', $idsDetalle)));
        if (empty($idsDetalle)) {
            return [];
        }
        $placeholders = implode(',', $idsDetalle);

        $sqlConsignacion = "
            SELECT cvd.id_pedido_detalle AS id, SUM(cvd.cantidad) AS cantidad
            FROM consignaciones_ventas_detalles cvd
            JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
            WHERE cv.eliminado = false AND cvd.eliminado = false
              AND cvd.id_pedido_detalle IN ({$placeholders})
            GROUP BY cvd.id_pedido_detalle
        ";

        $sqlFactura = $this->columnaVentasDetalleExiste() ? "
            UNION ALL
            SELECT vd.id_pedido_detalle AS id, SUM(vd.cantidad) AS cantidad
            FROM ventas_detalle vd
            JOIN ventas_cabecera v ON v.id = vd.id_venta
            WHERE v.eliminado = false AND v.estado <> 'anulado'
              AND vd.id_pedido_detalle IN ({$placeholders})
            GROUP BY vd.id_pedido_detalle
        " : '';

        $sql = "SELECT id, SUM(cantidad) AS cantidad FROM ({$sqlConsignacion} {$sqlFactura}) t GROUP BY id";
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('floatval', $rows);
    }

    /**
     * Historial de documentos que consumieron una línea del pedido (para el "ver
     * historial" al hacer clic en el ítem): Consignaciones de Venta y Facturas de
     * Venta que la referencian, con número, fecha y cantidad tomada.
     *
     * $idEmpresa es obligatorio y se valida contra el pedido dueño de la línea
     * (no contra el documento consumidor) para que un usuario no pueda leer el
     * historial de una línea de OTRA empresa adivinando/iterando el id.
     *
     * @return array<int,array{tipo:string,numero:string,fecha:?string,cantidad:float,estado:string}>
     */
    public function getHistorialConsumoDetalle(int $idDetalle, int $idEmpresa): array {
        $existeYPropia = $this->db->prepare(
            "SELECT 1 FROM pedidos_detalle d
             JOIN pedidos_cabecera p ON p.id = d.id_pedido
             WHERE d.id = :id AND p.id_empresa = :id_empresa"
        );
        $existeYPropia->execute([':id' => $idDetalle, ':id_empresa' => $idEmpresa]);
        if (!$existeYPropia->fetchColumn()) {
            return [];
        }

        $sqlConsignacion = "
            SELECT 'Consignación de Venta' AS tipo,
                   (cv.establecimiento || '-' || cv.punto_emision || '-' || cv.secuencial) AS numero,
                   cv.fecha_emision AS fecha,
                   cvd.cantidad AS cantidad,
                   cv.estado AS estado
            FROM consignaciones_ventas_detalles cvd
            JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
            WHERE cvd.id_pedido_detalle = :id AND cv.eliminado = false AND cvd.eliminado = false
        ";

        $sqlFactura = $this->columnaVentasDetalleExiste() ? "
            UNION ALL
            SELECT 'Factura de Venta' AS tipo,
                   (v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial) AS numero,
                   v.fecha_emision AS fecha,
                   vd.cantidad AS cantidad,
                   v.estado AS estado
            FROM ventas_detalle vd
            JOIN ventas_cabecera v ON v.id = vd.id_venta
            WHERE vd.id_pedido_detalle = :id2 AND v.eliminado = false
        " : '';

        $sql = "{$sqlConsignacion} {$sqlFactura} ORDER BY fecha DESC";
        $params = [':id' => $idDetalle];
        if ($this->columnaVentasDetalleExiste()) {
            $params[':id2'] = $idDetalle;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerUltimoNumero($id_empresa) {
        $sql = "SELECT MAX(CAST(numero_pedido AS INTEGER)) FROM pedidos_cabecera WHERE id_empresa = :id_empresa";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_empresa' => $id_empresa]);
        $ultimo = $stmt->fetchColumn();
        return $ultimo ? $ultimo + 1 : 1;
    }

    public function getTarifasIva(): array {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(int $idEmpresa): array {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true AND id_empresa = :id_empresa ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeSecuencial(int $idEmpresa, int $idPuntoEmision, string $secuencial, string $tipoAmbiente, ?int $excluirId = null): bool {
        $sql = "SELECT COUNT(*) FROM pedidos_cabecera
                WHERE id_empresa = :id_empresa AND id_punto_emision = :id_punto_emision
                  AND secuencial = :secuencial AND tipo_ambiente = :tipo_ambiente
                  AND eliminado = false";
        $params = [
            'id_empresa' => $idEmpresa,
            'id_punto_emision' => $idPuntoEmision,
            'secuencial' => $secuencial,
            'tipo_ambiente' => $tipoAmbiente,
        ];
        if ($excluirId !== null) {
            $sql .= " AND id <> :excluir_id";
            $params['excluir_id'] = $excluirId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getEmpresaConfig(int $idEmpresa): array {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->execute(['id' => $idEmpresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
