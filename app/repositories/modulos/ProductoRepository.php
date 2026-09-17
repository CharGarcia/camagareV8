<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ProductoRepository extends BaseRepository
{
    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL con la que se ordena. Es la whitelist del ORDER BY y el mapa que
     * necesita `OrdenListado::clausula()` para encadenar varias columnas.
     */
    public const MAPA_ORDEN = [
        'codigo'            => 'p.codigo',
        'nombre'            => 'p.nombre',
        'precio_base'       => 'p.precio_base',
        'status'            => 'p.status',
        'tipo_produccion'   => 'p.tipo_produccion',
        'codigo_auxiliar'   => 'p.codigo_auxiliar',
        'codigo_barras'     => 'p.codigo_barras',
        'inventariable'     => 'p.inventariable',
        'stock_minimo'      => 'p.stock_minimo',
        'stock_maximo'      => 'p.stock_maximo',
        'valor_ice'         => 'p.valor_ice',
        'ubicacion'         => 'p.ubicacion',
        // Columnas que vienen de un JOIN: se prefija la tabla correcta.
        'nombre_categoria'  => 'cat.nombre',
        'nombre_marca'      => 'mar.nombre',
        'nombre_medida'     => 'um.nombre',
        'nombre_tarifa_iva' => 'ti.tarifa',
        // Calculadas: no son columnas, se recalculan igual que en el SELECT.
        'valor_iva'         => '((p.precio_base + COALESCE(p.valor_ice, 0)) * (COALESCE(ti.porcentaje_iva, 0) / 100))',
        'pvp'               => '((p.precio_base + COALESCE(p.valor_ice, 0)) * (1 + COALESCE(ti.porcentaje_iva, 0) / 100))',
        'saldo_actual'      => '(SELECT COALESCE(SUM(k.cantidad), 0) FROM inventario_kardex k WHERE k.id_producto = p.id AND k.id_empresa = p.id_empresa AND k.eliminado = false)',
    ];

    public function __construct()
    {
        parent::__construct('productos');
    }

    /**
     * Texto del producto que entra en el buscador de los documentos (facturas, POS, compras,
     * comandas…): nombre y los tres códigos. `$a` es el prefijo del alias ('px.' en la
     * consulta, '' en el CREATE INDEX): la MISMA expresión alimenta la consulta y el índice.
     *
     * OJO: es distinta de la de `idx_trgm_productos` (solo código y nombre), que usan los
     * listados que buscan "el producto dentro del documento" (Pedidos, Consignaciones). Son
     * dos índices a propósito: cada módulo busca en las columnas que su usuario decidió.
     */
    private static function exprProducto(string $a = ''): string
    {
        return "COALESCE({$a}codigo, '') || ' ' || COALESCE({$a}nombre, '')"
             . " || ' ' || COALESCE({$a}codigo_auxiliar, '')"
             . " || ' ' || COALESCE({$a}codigo_barras, '')";
    }

    /**
     * Columnas numéricas del listado, tal como se ven en pantalla. Dos de ellas (Val. IVA y
     * PVP) dependen del porcentaje de la tarifa, que vive en otra tabla, así que estas se
     * comparan por fila y solo cuando la palabra puede ser un número — una palabra de puras
     * letras nunca podría coincidir con un importe.
     */
    private const SI_PUEDE_SER_NUMERO = '/^[.,\-]+$|\d/';

    private static function exprNumerica(): string
    {
        return "CONCAT_WS(' ',
                    ROUND(p.precio_base, 2),
                    NULLIF(ROUND((p.precio_base + COALESCE(p.valor_ice, 0)) * (COALESCE(ti.porcentaje_iva, 0) / 100), 2), 0),
                    NULLIF(ROUND(COALESCE(p.valor_ice, 0), 2), 0),
                    ROUND((p.precio_base + COALESCE(p.valor_ice, 0)) * (1 + COALESCE(ti.porcentaje_iva, 0) / 100), 2),
                    NULLIF(ROUND(p.stock_minimo, 2), 0),
                    NULLIF(ROUND(p.stock_maximo, 2), 0))";
    }

    /** Fuentes del buscador de producto de los documentos (ver App\Helpers\MotorBusqueda). */
    private function fuentesBusqueda(): array
    {
        return [[
            'sql'    => "p.id IN (SELECT px.id FROM productos px WHERE px.id_empresa = :id_empresa AND {cond})",
            'expr'   => self::exprProducto('px.'),
            'indice' => ['tabla' => 'productos', 'nombre' => 'idx_trgm_productos_codigos', 'expr' => self::exprProducto()],
        ]];
    }

    /**
     * Fuentes del texto libre del LISTADO del módulo (búsqueda amplia): las columnas
     * visibles y lo que identifica al producto. Busca exactamente lo mismo que antes —
     * código, código auxiliar, código de barras, descripción, categoría, marca, medida,
     * ubicación, precio base, Val. IVA, ICE, PVP, mínimo, máximo, nombre del ICE, usuario
     * que registró, variantes y códigos de proveedor—, pero lo que vive en otra tabla se
     * resuelve como conjunto (una vez por palabra) en vez de recalcularse por cada producto
     * del catálogo. Tipo, Tipo IVA, Inv., Estado y Saldo siguen fuera (solo por el modal).
     */
    private function fuentesBusquedaAmplia(): array
    {
        return [
            // Código, descripción y los otros dos códigos: mismo índice que el buscador de
            // los documentos.
            [
                'sql'    => "p.id IN (SELECT px.id FROM productos px WHERE px.id_empresa = :id_empresa AND {cond})",
                'expr'   => self::exprProducto('px.'),
                'indice' => ['tabla' => 'productos', 'nombre' => 'idx_trgm_productos_codigos', 'expr' => self::exprProducto()],
            ],
            // Ubicación y nombre del ICE: columnas cortas que casi nadie busca; se comparan
            // por fila para no cargar otro índice a la tabla de productos.
            ['expr' => "CONCAT_WS(' ', p.ubicacion, p.nombre_ice)"],
            // Importes y stocks mínimos/máximos, como se ven en el listado.
            ['expr' => self::exprNumerica(), 'crudo' => true, 'si' => self::SI_PUEDE_SER_NUMERO],
            // Catálogos: tablas chicas, sin índice.
            ['sql' => "p.id_categoria IN (SELECT cx.id FROM categorias cx WHERE {cond})", 'expr' => "COALESCE(cx.nombre, '')"],
            ['sql' => "p.id_marca IN (SELECT mx.id FROM marcas mx WHERE {cond})", 'expr' => "COALESCE(mx.nombre, '')"],
            ['sql' => "p.id_medida IN (SELECT dx.id FROM unidades_medida dx WHERE {cond})", 'expr' => "COALESCE(dx.nombre, '')"],
            ['sql' => "p.created_by IN (SELECT gx.id FROM usuarios gx WHERE {cond})", 'expr' => "COALESCE(gx.nombre, '')"],
            // Variantes (nombre y valor) y códigos con que lo factura cada proveedor.
            [
                'sql'    => "p.id IN (SELECT pv.id_producto FROM productos_variantes pv WHERE pv.eliminado = false AND {cond})",
                'expr'   => "COALESCE(pv.nombre, '') || ' ' || COALESCE(pv.valor, '')",
                'indice' => ['tabla' => 'productos_variantes', 'nombre' => 'idx_trgm_productos_variantes', 'expr' => "COALESCE(nombre, '') || ' ' || COALESCE(valor, '')"],
            ],
            [
                'sql'    => "p.id IN (SELECT ph.id_producto FROM productos_homologacion ph WHERE ph.id_empresa = :id_empresa AND ph.eliminado = false AND {cond})",
                'expr'   => "COALESCE(ph.codigo_proveedor, '')",
                'indice' => ['tabla' => 'productos_homologacion', 'nombre' => 'idx_trgm_productos_homologacion', 'expr' => "COALESCE(codigo_proveedor, '')"],
            ],
        ];
    }

    /** SQL de los índices que necesita la búsqueda de este módulo (para database/*.sql). */
    public function sqlIndicesBusqueda(): array
    {
        return \App\Helpers\MotorBusqueda::sqlIndices(
            array_merge($this->fuentesBusqueda(), $this->fuentesBusquedaAmplia())
        );
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null,
        ?string $soloOpcion = null,
        bool $soloActivos = false,
        array $ordenMulti = [],
        // true solo desde el listado del módulo Productos (y sus exportaciones): el texto
        // libre busca en todas las columnas del listado. Los buscadores de producto de
        // facturas, POS, compras, comandas… llaman sin este flag y siguen buscando solo
        // por nombre y códigos, para no llenarlos de ruido (una marca, un precio…) ni
        // pagar las subconsultas de variantes y homologaciones en cada tecla.
        bool $busquedaAmplia = false
    ): array {
        // Una o varias columnas (Shift+clic en el listado), siempre validadas contra
        // MAPA_ORDEN, con p.id como desempate para que las filas empatadas no bailen
        // entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula(
            $ordenMulti,
            self::MAPA_ORDEN,
            'p.nombre',
            'p.id DESC'
        );

        $whereSql = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($soloActivos) {
            $whereSql .= " AND p.status = 1";
        }

        if ($soloOpcion !== null) {
            $whereSql .= " AND (p.opciones->>'" . $soloOpcion . "')::boolean = true";
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '' && $busquedaAmplia) {
            // Listado del módulo Productos (y sus exportaciones): ver fuentesBusquedaAmplia().
            $condicion = \App\Helpers\MotorBusqueda::condicion(
                $this->fuentesBusquedaAmplia(),
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        } elseif ($parsed['texto_libre'] !== '') {
            // Buscador de producto de facturas, POS, compras, comandas… (el que más se usa
            // del sistema: 3.280 búsquedas en 15 horas según producción). Mismas columnas de
            // siempre —nombre y los tres códigos— pero como un conjunto que PostgreSQL
            // resuelve UNA vez con su índice trigram, en vez de quitarle las tildes al texto
            // de cada producto del catálogo en cada tecla. Ver App\Helpers\MotorBusqueda.
            $condicion = \App\Helpers\MotorBusqueda::condicion(
                $this->fuentesBusqueda(),
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }

        // El buscador envía etiquetas amigables ('activo'/'inactivo', 'bien'/'servicio')
        // pero p.status es entero (1/0) y p.tipo_produccion guarda códigos ('01'/'02').
        // Traducir antes de aplicarFiltros() para no comparar contra valores que nunca
        // van a coincidir (o, en el caso de status, romper el bind a entero).
        // Mismo patrón que ClienteRepository::getListado().
        $mapEstado = ['activo' => '1', 'inactivo' => '0'];
        $mapTipo   = ['bien' => '01', 'servicio' => '02'];
        $traducciones = ['estado' => $mapEstado, 'status' => $mapEstado, 'tipo' => $mapTipo];
        foreach ($traducciones as $claveFiltro => $mapa) {
            if (!isset($parsed['filtros'][$claveFiltro])) continue;
            $val = $parsed['filtros'][$claveFiltro]['valor'];
            $parsed['filtros'][$claveFiltro]['valor'] = is_array($val)
                ? array_map(fn($v) => $mapa[strtolower(trim((string)$v))] ?? $v, $val)
                : ($mapa[strtolower(trim((string)$val))] ?? $val);
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'        => 'p.nombre',
                'codigo'        => 'p.codigo',
                'codigo_aux'    => 'p.codigo_auxiliar',
                'codigo_barras' => 'p.codigo_barras',
                'barras'        => 'p.codigo_barras',
                'categoria'     => 'cat.nombre',
                'marca'         => 'mar.nombre',
                'medida'        => 'um.nombre',
                'ubicacion'     => 'p.ubicacion',
            ],
            'exacto'   => [
                // Igual que la columna Estado del listado: solo status = 1 es "Activo";
                // cualquier otro valor (0, o el 2 que traen productos migrados) se ve y se
                // filtra como Inactivo. Antes estado:inactivo comparaba = 0 y dejaba fuera
                // a los de status 2. `status` sigue comparando el valor crudo.
                'estado'       => "CASE WHEN COALESCE(p.status, 1) = 1 THEN '1' ELSE '0' END",
                'status'       => 'p.status',
                'tipo'         => 'p.tipo_produccion',
                // NULL se ve como "No" en la columna Inv.; con la columna cruda,
                // inventariable:false los dejaba fuera.
                'inventariable' => 'COALESCE(p.inventariable, false)',
                // Selects del modal de filtros (catálogos por id).
                'id'           => 'p.id',
                'id_categoria' => 'p.id_categoria',
                'id_marca'     => 'p.id_marca',
                'id_medida'    => 'p.id_medida',
                'id_tarifa_iva'=> 'p.tarifa_iva',
                'usuario'      => 'p.created_by',
                // Sí/No calculados.
                'con_ice'      => "CASE WHEN COALESCE(p.valor_ice, 0) > 0 OR p.id_ice IS NOT NULL THEN 'si' ELSE 'no' END",
                'para_venta'   => "CASE WHEN COALESCE((p.opciones->>'venta')::boolean, false) THEN 'si' ELSE 'no' END",
                'para_compra'  => "CASE WHEN COALESCE((p.opciones->>'compra')::boolean, false) THEN 'si' ELSE 'no' END",
                'kit'          => "CASE WHEN EXISTS (SELECT 1 FROM productos_componentes pcm WHERE pcm.id_producto_padre = p.id AND pcm.eliminado = false) THEN 'si' ELSE 'no' END",
                'con_precios'  => "CASE WHEN EXISTS (SELECT 1 FROM productos_precios ppr WHERE ppr.id_empresa = p.id_empresa AND ppr.eliminado = false AND ppr.id_producto = p.id) THEN 'si' ELSE 'no' END",
                // Bajo el mínimo: inventariable, con stock mínimo definido y saldo del kardex por debajo.
                'bajo_minimo'  => "CASE WHEN COALESCE(p.inventariable, false) AND COALESCE(p.stock_minimo, 0) > 0
                                        AND (SELECT COALESCE(SUM(k.cantidad), 0) FROM inventario_kardex k WHERE k.id_producto = p.id AND k.id_empresa = p.id_empresa AND k.eliminado = false) < p.stock_minimo
                                   THEN 'si' ELSE 'no' END",
            ],
            'fecha'    => [
                'registro'   => 'p.created_at',
                'created_at' => 'p.created_at',
            ],
            'numerico' => [
                'precio'    => 'p.precio_base',
                'pvp'       => '((p.precio_base + COALESCE(p.valor_ice, 0)) * (1 + COALESCE(ti.porcentaje_iva, 0) / 100))',
                // p.stock no existe: el saldo real se calcula en vivo desde el Kardex
                // (misma subquery correlacionada que la columna saldo_actual del SELECT).
                'stock'     => '(SELECT COALESCE(SUM(k.cantidad), 0) FROM inventario_kardex k WHERE k.id_producto = p.id AND k.id_empresa = p.id_empresa AND k.eliminado = false)',
                'stock_min' => 'p.stock_minimo',
                'stock_max' => 'p.stock_maximo',
            ],
        ]);

        // Mismos JOIN en el COUNT que usan el texto libre y los filtros (ti: Val. IVA/PVP;
        // ureg: usuario que registró, solo en la búsqueda amplia).
        $countJoins = "LEFT JOIN categorias cat ON cat.id = p.id_categoria
                       LEFT JOIN marcas mar ON mar.id = p.id_marca
                       LEFT JOIN unidades_medida um ON um.id = p.id_medida
                       LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva";
        $joinUsuario = $busquedaAmplia ? "LEFT JOIN usuarios ureg ON ureg.id = p.created_by" : '';
        $countJoins .= "\n                       {$joinUsuario}";

        // Conteo + página en UNA consulta (App\Helpers\ListadoPaginado): antes el COUNT y el
        // SELECT repetían el mismo WHERE, así que el texto libre se evaluaba dos veces. En el
        // buscador de producto de los documentos eso se paga en CADA tecla.
        $select = "p.*,
                           cat.nombre AS nombre_categoria,
                           mar.nombre AS nombre_marca,
                           ti.tarifa AS nombre_tarifa_iva,
                           ti.porcentaje_iva AS porcentaje_iva_final,
                           ti.codigo AS codigo_iva_final,
                           ti.status AS status_iva_final,
                           um.nombre AS nombre_medida,
                           um.abreviatura AS abreviatura_medida,
                           ((p.precio_base + COALESCE(p.valor_ice, 0)) * (COALESCE(ti.porcentaje_iva, 0)::numeric / 100)) AS valor_iva,
                           ((p.precio_base + COALESCE(p.valor_ice, 0)) * (1 + COALESCE(ti.porcentaje_iva, 0)::numeric / 100)) AS pvp,
                           (SELECT COALESCE(SUM(k.cantidad), 0)
                              FROM inventario_kardex k
                             WHERE k.id_producto = p.id
                               AND k.id_empresa = p.id_empresa
                               AND k.eliminado = false) AS saldo_actual";

        return \App\Helpers\ListadoPaginado::consultar(
            function (string $sql, array $prm): array {
                $st = $this->db->prepare($sql);
                $st->execute($prm);
                return $st->fetchAll(PDO::FETCH_ASSOC);
            },
            [
                'tabla'       => $this->table,
                'alias'       => 'p',
                'joinsFiltro' => $countJoins,
                'joinsFinal'  => $countJoins,
                'where'       => $whereSql,
                'orderBy'     => $orderBy,
                'select'      => $select,
                'perPage'     => $perPage,
                'offset'      => $perPage > 0 ? ($page - 1) * $perPage : 0,
                'conBusqueda' => trim($buscar) !== '',
            ],
            $params
        );
    }

    /**
     * Opciones de los selects del modal de filtros del listado: solo los valores que
     * la empresa realmente usa en sus productos (no eliminados).
     *
     * @return array{categorias: array, marcas: array, medidas: array, tarifas_iva: array, usuarios: array}
     */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $p = [':id_empresa' => $idEmpresa];
        $leer = function (string $select, string $join, string $orden) use ($p): array {
            $st = $this->db->prepare("SELECT DISTINCT {$select}
                                      FROM productos p {$join}
                                      WHERE p.id_empresa = :id_empresa AND p.eliminado = false
                                      ORDER BY {$orden}");
            $st->execute($p);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };

        return [
            'categorias'  => $leer('c.id, c.nombre', 'JOIN categorias c ON c.id = p.id_categoria', 'c.nombre'),
            'marcas'      => $leer('m.id, m.nombre', 'JOIN marcas m ON m.id = p.id_marca', 'm.nombre'),
            'medidas'     => $leer('um.id, um.nombre', 'JOIN unidades_medida um ON um.id = p.id_medida', 'um.nombre'),
            'tarifas_iva' => $leer('ti.id, ti.tarifa AS nombre', 'JOIN tarifa_iva ti ON ti.id = p.tarifa_iva', 'nombre'),
            'usuarios'    => $leer('u.id, u.nombre', 'JOIN usuarios u ON u.id = p.created_by', 'u.nombre'),
        ];
    }

    /**
     * Búsqueda libre DENTRO de los productos (pestaña "Detalles" del modal de filtros):
     * cada variante, componente (kit), precio adicional y código de proveedor
     * (homologación) que coincide con el texto, junto con el producto al que pertenece.
     * Mismo alcance que el listado: empresa, no eliminados y registros propios
     * (created_by) si el usuario no tiene acceso total.
     *
     * @return array<int, array{origen:string, detalle:?string, valor:?string, monto:?string,
     *                          id_producto:int, codigo:?string, nombre:?string, status:?int, created_at:?string}>
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
            $whereBase .= " AND p.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condVar  = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['pv.nombre', 'pv.valor', 'pv.precio_adicional::text'], $q, $params, 'dv');
        $condComp = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['h.codigo', 'h.nombre', 'pc.cantidad::text', 'um.nombre'], $q, $params, 'dc');
        $condPre  = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['pp.nombre_precio', 'pp.precio::text'], $q, $params, 'dp');
        $condHom  = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['ph.codigo_proveedor', 'pr.razon_social', 'pr.identificacion'], $q, $params, 'dh');
        if ($condVar === '' || $condComp === '' || $condPre === '' || $condHom === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT p.id, p.codigo, p.nombre, p.status, p.created_at
                    FROM productos p
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'VARIANTE' AS origen, pv.nombre AS detalle, pv.valor AS valor, pv.precio_adicional AS monto,
                           b.id AS id_producto, b.codigo, b.nombre, b.status, b.created_at
                    FROM productos_variantes pv
                    JOIN base b ON b.id = pv.id_producto
                    WHERE pv.eliminado = false AND $condVar
                    UNION ALL
                    SELECT 'COMPONENTE' AS origen, CONCAT_WS(' - ', h.codigo, h.nombre) AS detalle,
                           CONCAT_WS(' ', TRIM(TO_CHAR(pc.cantidad, 'FM999999990.####')), um.nombre) AS valor, NULL AS monto,
                           b.id AS id_producto, b.codigo, b.nombre, b.status, b.created_at
                    FROM productos_componentes pc
                    JOIN base b ON b.id = pc.id_producto_padre
                    JOIN productos h ON h.id = pc.id_producto_hijo
                    LEFT JOIN unidades_medida um ON um.id = pc.id_medida
                    WHERE pc.eliminado = false AND $condComp
                    UNION ALL
                    SELECT 'PRECIO' AS origen, pp.nombre_precio AS detalle,
                           CONCAT_WS(' a ', TO_CHAR(pp.valido_desde, 'DD-MM-YYYY'), TO_CHAR(pp.valido_hasta, 'DD-MM-YYYY')) AS valor,
                           pp.precio AS monto,
                           b.id AS id_producto, b.codigo, b.nombre, b.status, b.created_at
                    FROM productos_precios pp
                    JOIN base b ON b.id = pp.id_producto
                    WHERE pp.id_empresa = :id_empresa AND pp.eliminado = false AND $condPre
                    UNION ALL
                    SELECT 'HOMOLOGACION' AS origen, pr.razon_social AS detalle, ph.codigo_proveedor AS valor, NULL AS monto,
                           b.id AS id_producto, b.codigo, b.nombre, b.status, b.created_at
                    FROM productos_homologacion ph
                    JOIN base b ON b.id = ph.id_producto
                    LEFT JOIN proveedores pr ON pr.id = ph.id_proveedor
                    WHERE ph.id_empresa = :id_empresa AND ph.eliminado = false AND $condHom
                ) x
                ORDER BY x.nombre ASC, x.id_producto DESC, x.origen
                LIMIT $limit";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Producto por id, con los mismos joins/columnas calculadas que getListado()
     * (porcentaje_iva_final, codigo_iva_final, etc.) — mismo shape que espera
     * seleccionarProductoEnFila() en Factura de Venta. Solo productos vendibles y
     * activos (mismos filtros que getListado($soloOpcion='venta', $soloActivos=true)),
     * para no facturar algo que ya no se puede vender aunque el id exista.
     */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.*,
                       cat.nombre AS nombre_categoria,
                       mar.nombre AS nombre_marca,
                       ti.tarifa AS nombre_tarifa_iva,
                       ti.porcentaje_iva AS porcentaje_iva_final,
                       ti.codigo AS codigo_iva_final,
                       ti.status AS status_iva_final,
                       um.nombre AS nombre_medida,
                       um.abreviatura AS abreviatura_medida
                FROM {$this->table} p
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                LEFT JOIN unidades_medida um ON um.id = p.id_medida
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false
                  AND p.status = 1 AND (p.opciones->>'venta')::boolean = true";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeCodigo(int $idEmpresa, string $codigo, ?int $excluirId = null): bool
    {
        // lower(): concuerda con el índice único productos_codigo_unico_idx, de modo
        // que 'P001' y 'p001' se consideran el mismo código.
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo) AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa, ':codigo' => trim($codigo)];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Busca por código + empresa, INCLUYENDO eliminados (para que la replicación
     * entre empresas reactive en vez de duplicar). Case-insensitive, mismo criterio
     * que existeCodigo().
     */
    public function findByCodigo(int $idEmpresa, string $codigo): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo)
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':codigo' => trim($codigo)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Reactiva un producto eliminado SIN tocar sus datos (a diferencia de un
     * update completo). Usado por la replicación entre empresas: si el producto
     * ya existía en la empresa destino pero estaba eliminado, se reactiva tal
     * cual estaba en vez de sobrescribirlo con los datos de la empresa origen.
     */
    public function reactivarSoloEliminado(int $id, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table} SET
                    eliminado = false,
                    updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':id' => $id]);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, codigo, nombre,
                    codigo_auxiliar, codigo_barras, precio_base, tipo_produccion,
                    tarifa_iva, id_medida, id_tipo_medida, status, valor_ice, codigo_ice,
                    nombre_ice, inventariable, id_categoria, id_marca, imagen, costo_producto,
                    eliminado, created_at, stock_minimo, stock_maximo, id_ice, opciones, ubicacion,
                    excluir_recargo_servicio, precio_editable_comanda
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :codigo, :nombre,
                    :codigo_auxiliar, :codigo_barras, :precio_base, :tipo_produccion,
                    :tarifa_iva, :id_medida, :id_tipo_medida, :status, :valor_ice, :codigo_ice,
                    :nombre_ice, :inventariable, :id_categoria, :id_marca, :imagen, :costo_producto,
                    :eliminado, CURRENT_TIMESTAMP, :stock_minimo, :stock_maximo, :id_ice, :opciones, :ubicacion,
                    :excluir_recargo_servicio, :precio_editable_comanda
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'             => $data['id_empresa'],
            ':id_usuario'             => $data['id_usuario'],
            ':created_by'             => $data['id_usuario'],
            ':codigo'                 => $data['codigo'],
            ':nombre'                 => $data['nombre'],
            ':codigo_auxiliar'        => $data['codigo_auxiliar'],
            ':codigo_barras'          => $data['codigo_barras'],
            ':precio_base'            => $data['precio_base'],
            ':tipo_produccion'        => $data['tipo_produccion'],
            ':tarifa_iva'             => $data['tarifa_iva'],
            ':id_medida'              => $data['id_medida'],
            ':id_tipo_medida'         => $data['id_tipo_medida'],
            ':status'                 => $data['status'] ? 1 : 0,
            ':valor_ice'              => $data['valor_ice'],
            ':codigo_ice'             => $data['codigo_ice'],
            ':nombre_ice'             => $data['nombre_ice'],
            ':inventariable'          => $data['inventariable'] ? 'true' : 'false',
            ':id_categoria'           => $data['id_categoria'],
            ':id_marca'               => $data['id_marca'],
            ':imagen'                 => $data['imagen'],
            ':costo_producto'         => $data['costo_producto'] ?? 0,
            ':eliminado'              => 'false',
            ':stock_minimo'           => $data['stock_minimo'] ?? 0,
            ':stock_maximo'           => $data['stock_maximo'] ?? 0,
            ':id_ice'                 => !empty($data['id_ice']) ? (int)$data['id_ice'] : null,
            ':opciones'               => $data['opciones'] ?? '{"compra":true,"venta":true}',
            ':ubicacion'              => !empty($data['ubicacion']) ? $data['ubicacion'] : null,
            ':excluir_recargo_servicio' => !empty($data['excluir_recargo_servicio']) ? 'true' : 'false',
            ':precio_editable_comanda'  => !empty($data['precio_editable_comanda']) ? 'true' : 'false',
        ]);
        return (int) $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                codigo = :codigo,
                nombre = :nombre,
                codigo_auxiliar = :codigo_auxiliar,
                codigo_barras = :codigo_barras,
                precio_base = :precio_base,
                tipo_produccion = :tipo_produccion,
                tarifa_iva = :tarifa_iva,
                id_medida = :id_medida,
                id_tipo_medida = :id_tipo_medida,
                status = :status,
                valor_ice = :valor_ice,
                codigo_ice = :codigo_ice,
                nombre_ice = :nombre_ice,
                inventariable = :inventariable,
                id_categoria = :id_categoria,
                id_marca = :id_marca,
                imagen = :imagen,
                costo_producto = :costo_producto,
                stock_minimo = :stock_minimo,
                stock_maximo = :stock_maximo,
                id_ice = :id_ice,
                opciones = :opciones,
                ubicacion = :ubicacion,
                excluir_recargo_servicio = :excluir_recargo_servicio,
                precio_editable_comanda = :precio_editable_comanda,
                id_usuario = :id_usuario,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'                 => $data['codigo'],
            ':nombre'                 => $data['nombre'],
            ':codigo_auxiliar'        => $data['codigo_auxiliar'],
            ':codigo_barras'          => $data['codigo_barras'],
            ':precio_base'            => $data['precio_base'],
            ':tipo_produccion'        => $data['tipo_produccion'],
            ':tarifa_iva'             => $data['tarifa_iva'],
            ':id_medida'              => $data['id_medida'],
            ':id_tipo_medida'         => $data['id_tipo_medida'],
            ':status'                 => $data['status'] ? 1 : 0,
            ':valor_ice'              => $data['valor_ice'],
            ':codigo_ice'             => $data['codigo_ice'],
            ':nombre_ice'             => $data['nombre_ice'],
            ':inventariable'          => $data['inventariable'] ? 'true' : 'false',
            ':id_categoria'           => $data['id_categoria'],
            ':id_marca'               => $data['id_marca'],
            ':imagen'                 => $data['imagen'],
            ':costo_producto'         => $data['costo_producto'] ?? 0,
            ':stock_minimo'           => $data['stock_minimo'] ?? 0,
            ':stock_maximo'           => $data['stock_maximo'] ?? 0,
            ':id_ice'                 => !empty($data['id_ice']) ? (int)$data['id_ice'] : null,
            ':opciones'               => $data['opciones'] ?? '{"compra":true,"venta":true}',
            ':ubicacion'              => !empty($data['ubicacion']) ? $data['ubicacion'] : null,
            ':excluir_recargo_servicio' => !empty($data['excluir_recargo_servicio']) ? 'true' : 'false',
            ':precio_editable_comanda'  => !empty($data['precio_editable_comanda']) ? 'true' : 'false',
            ':id_usuario'             => $data['id_usuario'],
            ':updated_by'             => $data['id_usuario'],
            ':id'                     => $id,
            ':id_empresa'             => $idEmpresa
        ]);
    }

    /** Cambia solo la categoría del producto (edición en línea desde reportes). */
    public function actualizarCategoria(int $id, int $idEmpresa, ?int $idCategoria, int $userId): void
    {
        $sql = "UPDATE {$this->table}
                SET id_categoria = :id_categoria, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_categoria' => $idCategoria, ':id' => $id, ':id_empresa' => $idEmpresa, ':uid' => $userId,
        ]);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.*,
                       cat.nombre AS nombre_categoria,
                       mar.nombre AS nombre_marca,
                       um.nombre AS nombre_medida,
                       ti.porcentaje_iva,
                       ti.tarifa AS nombre_tarifa_iva,
                       u1.nombre AS creado_por_nombre,
                       u2.nombre AS actualizado_por_nombre
                FROM {$this->table} p
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                LEFT JOIN unidades_medida um ON um.id = p.id_medida
                LEFT JOIN tipo_medida tm ON tm.id = p.id_tipo_medida
                LEFT JOIN usuarios u1 ON u1.id = p.created_by
                LEFT JOIN usuarios u2 ON u2.id = p.updated_by
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        $row['inventarios']    = $this->getInventarios($id, $idEmpresa);
        $row['precios']        = $this->getPrecios($id, $idEmpresa);
        $row['componentes']    = $this->getComponentes($id, $idEmpresa);
        $row['variantes']      = $this->getVariantes($id, $idEmpresa);
        $row['homologaciones'] = $this->getHomologaciones($id, $idEmpresa);
        
        // Stock general calculado
        $row['stock_actual_general'] = $this->getInventarioGeneral($id, $idEmpresa);

        return $row;
    }

    public function getHomologaciones(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT ph.*, 
                       pr.razon_social AS nombre_proveedor, 
                       pr.identificacion AS id_proveedor_ruc,
                       (SELECT d.descripcion 
                        FROM compras_detalle d 
                        JOIN compras_cabecera c ON d.id_compra = c.id 
                        WHERE c.id_proveedor = ph.id_proveedor 
                          AND d.codigo_principal = ph.codigo_proveedor 
                          AND c.id_empresa = ph.id_empresa
                          AND c.eliminado = false
                        ORDER BY c.fecha_emision DESC LIMIT 1) AS descripcion_homologada
                FROM productos_homologacion ph
                JOIN proveedores pr ON pr.id = ph.id_proveedor
                WHERE ph.id_producto = :id_p AND ph.id_empresa = :id_e AND ph.eliminado = false
                ORDER BY pr.razon_social ASC";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getNombre(int $id): string
    {
        $sql = "SELECT nombre FROM {$this->table} WHERE id = ? LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([$id]);
        return (string) ($st->fetchColumn() ?: 'Producto #' . $id);
    }

    public function isInventariable(int $id, int $idEmpresa): bool
    {
        $info = $this->getInfoControlInventario($id, $idEmpresa);
        return $info['inventariable'] && $info['tipo_produccion'] !== '02';
    }

    /**
     * Lo único que un movimiento de inventario necesita del producto: si es inventariable, su
     * tipo de producción y la unidad de medida. getDetalleCompleto() arma la ficha entera
     * (inventario por bodega sumando el kardex, precios, variantes, homologaciones) y se usaba
     * por cada línea de cada factura, recibo y nota de crédito.
     */
    public function getDatosMovimientoInventario(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT id, inventariable, tipo_produccion, id_medida
                FROM {$this->table}
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getInfoControlInventario(int $id, int $idEmpresa): array
    {
        $sql = "SELECT inventariable, tipo_produccion FROM {$this->table} WHERE id = ? AND id_empresa = ? AND eliminado = false LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return ['inventariable' => false, 'tipo_produccion' => ''];
        }

        $inv = $row['inventariable'];
        return [
            'inventariable'   => ($inv === true || $inv === 'true' || $inv == 1 || $inv === 't'),
            'tipo_produccion' => (string) $row['tipo_produccion']
        ];
    }

    public function getComponentes(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT pc.*, p.nombre AS nombre_componente, p.codigo AS codigo_componente, um.nombre AS nombre_medida
                FROM productos_componentes pc
                JOIN productos p ON p.id = pc.id_producto_hijo
                LEFT JOIN unidades_medida um ON um.id = pc.id_medida
                WHERE pc.id_producto_padre = :id_p AND pc.id_empresa = :id_e AND pc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVariantes(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT * FROM productos_variantes
                WHERE id_producto = :id_p AND id_empresa = :id_e AND eliminado = false
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Variantes de VARIOS productos en una sola consulta. Ver el porqué en
     * getPreciosPorProductos().
     *
     * @param int[] $idProductos
     * @return array<int,array> id_producto => variantes
     */
    public function getVariantesPorProductos(array $idProductos, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idProductos))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM productos_variantes
                WHERE id_empresa = ? AND eliminado = false AND id_producto IN ($ph)
                ORDER BY id_producto, nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idEmpresa], $ids));

        $porProducto = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porProducto[(int) $fila['id_producto']][] = $fila;
        }
        return $porProducto;
    }

    public function getInventarios(int $idProducto, int $idEmpresa): array
    {
        // Obtenemos stock actual sumando el Kardex en tiempo real
        $sql = "SELECT pb.id_bodega,
                       b.nombre AS nombre_bodega,
                       pb.stock_minimo,
                       pb.stock_maximo,
                       (SELECT COALESCE(SUM(cantidad), 0)
                        FROM inventario_kardex
                        WHERE id_producto = :id_producto
                          AND id_bodega = pb.id_bodega
                          AND id_empresa = :id_empresa
                          AND eliminado = false) AS stock_actual,
                       (SELECT CASE WHEN SUM(cantidad) > 0
                            THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6)
                            ELSE 0 END
                        FROM inventario_kardex
                        WHERE id_producto = :id_producto
                          AND id_bodega = pb.id_bodega
                          AND id_empresa = :id_empresa
                          AND tipo_movimiento = 'entrada' AND eliminado = false) AS costo_promedio
                FROM productos_bodegas pb
                JOIN bodegas b ON b.id = pb.id_bodega
                WHERE pb.id_producto = :id_producto
                  AND pb.id_empresa = :id_empresa
                  AND pb.eliminado = false
                  AND b.eliminado = false";

        $st = $this->db->prepare($sql);
        $st->execute([':id_producto' => $idProducto, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Costo promedio ponderado del producto a partir de las entradas del Kardex,
     * consolidando todas las bodegas. Null si no hay entradas registradas.
     */
    public function calcularCostoPromedioKardex(int $idProducto, int $idEmpresa): ?float
    {
        $sql = "SELECT CASE WHEN SUM(cantidad) > 0
                    THEN ROUND(SUM(costo_total)::numeric / SUM(cantidad)::numeric, 6)
                    ELSE NULL END AS costo_promedio
                FROM inventario_kardex
                WHERE id_empresa = :e AND id_producto = :p
                  AND tipo_movimiento = 'entrada' AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':p' => $idProducto]);
        $valor = $st->fetchColumn();
        return ($valor === false || $valor === null) ? null : (float) $valor;
    }

    /** Actualiza únicamente el costo del producto (usado por "Actualizar desde Kardex"). */
    public function actualizarCosto(int $id, int $idEmpresa, float $costo, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table}
                SET costo_producto = :costo, updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':costo' => $costo, ':uid' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }

    /** IDs + costo actual de los productos inventariables de la empresa (para la actualización masiva). */
    public function getInventariablesConCosto(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $whereSql = $this->getBaseWhere($idEmpresa, '', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT id, nombre, costo_producto FROM {$this->table}
                {$whereSql} AND inventariable = true";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Servicios (no inventariables) activos, con su tarifa de IVA. Lo usa el
     * buscador de Empresa → Facturación para elegir el producto con el que se
     * emite la propina voluntaria de las comandas: tiene que ser un servicio,
     * porque una propina no mueve stock.
     *
     * Va con búsqueda y tope de filas a propósito: un catálogo puede tener miles
     * de ítems y volcarlos todos en la pantalla no sirve para elegir uno.
     */
    public function getServicios(int $idEmpresa, string $buscar = '', int $limite = 15): array
    {
        $where = "p.id_empresa = :id_empresa AND p.eliminado = false
                  AND p.inventariable = false AND p.status = 1";
        $params = [':id_empresa' => $idEmpresa];
        $buscar = trim($buscar);
        if ($buscar !== '') {
            $where .= " AND (p.nombre ILIKE :b OR p.codigo ILIKE :b2)";
            $params[':b']  = '%' . $buscar . '%';
            $params[':b2'] = '%' . $buscar . '%';
        }
        $limite = max(1, min(50, $limite));
        $sql = "SELECT p.id, p.codigo, p.nombre, ti.porcentaje_iva
                FROM {$this->table} p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE {$where}
                ORDER BY p.nombre ASC
                LIMIT {$limite}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cambia SOLO la foto de un producto. Lo usa el módulo Menú: la foto del
     * plato y la del producto vinculado son la misma, así que al cambiarla desde
     * la carta se actualiza también en el catálogo. Es un update acotado a
     * propósito — no toca precio, stock ni nada más del producto.
     */
    public function actualizarImagen(int $id, int $idEmpresa, ?string $imagen, int $idUsuario): void
    {
        $sql = "UPDATE {$this->table}
                   SET imagen = :img, updated_at = CURRENT_TIMESTAMP, updated_by = :u
                 WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':img' => ($imagen !== null && $imagen !== '') ? $imagen : null,
            ':u'   => $idUsuario,
            ':id'  => $id,
            ':e'   => $idEmpresa,
        ]);
    }

    /** Un servicio por id, con el mismo shape que getServicios() — para mostrar el ya elegido en el buscador. */
    public function getServicioPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.id, p.codigo, p.nombre, ti.porcentaje_iva
                FROM {$this->table} p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPrecios(int $idProducto, int $idEmpresa): array
    {
        $sql = "SELECT id, nombre_precio, precio, valido_desde, valido_hasta, estado
                FROM productos_precios
                WHERE id_producto = :id_producto AND id_empresa = :id_empresa AND eliminado = false
                ORDER BY nombre_precio";
        $st = $this->db->prepare($sql);
        $st->execute([':id_producto' => $idProducto, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Precios de VARIOS productos en una sola consulta.
     *
     * Para abrir un documento con muchas líneas: llamar a getPrecios() dentro
     * del bucle multiplica los viajes a la base por el número de líneas.
     *
     * @param int[] $idProductos
     * @return array<int,array> id_producto => precios
     */
    public function getPreciosPorProductos(array $idProductos, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idProductos))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_producto, id, nombre_precio, precio, valido_desde, valido_hasta, estado
                FROM productos_precios
                WHERE id_empresa = ? AND eliminado = false AND id_producto IN ($ph)
                ORDER BY id_producto, nombre_precio";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idEmpresa], $ids));

        $porProducto = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $idProducto = (int) $fila['id_producto'];
            unset($fila['id_producto']); // mismas claves que getPrecios()
            $porProducto[$idProducto][] = $fila;
        }
        return $porProducto;
    }


    public function syncInventarios(int $idProducto, int $idEmpresa, array $inventarios, int $userId): void
    {
        $sqlDel = "UPDATE productos_bodegas SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_bodegas (id_empresa, id_producto, id_bodega, stock_minimo, stock_maximo, stock_actual, created_by, updated_by)
                   VALUES (:id_e, :id_p, :id_b, :s_min, :s_max, :s_act, :uid, :uid)
                   ON CONFLICT (id_producto, id_bodega) 
                   DO UPDATE SET eliminado = false, stock_minimo = EXCLUDED.stock_minimo, stock_maximo = EXCLUDED.stock_maximo, updated_by = EXCLUDED.updated_by, updated_at = CURRENT_TIMESTAMP";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($inventarios as $inv) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':id_b'  => $inv['id_bodega'],
                ':s_min' => $inv['stock_minimo'] ?? 0,
                ':s_max' => $inv['stock_maximo'] ?? 0,
                ':s_act' => $inv['stock_actual'] ?? 0,
                ':uid'   => $userId
            ]);
        }
    }
    
    /** Recalcula el stock_actual denormalizado para un producto/bodega desde el Kardex */
    public function recalcularStockCache(int $idProducto, int $idBodega, int $idEmpresa): void
    {
        $sql = "UPDATE productos_bodegas 
                SET stock_actual = (
                    SELECT COALESCE(SUM(cantidad), 0) 
                    FROM inventario_kardex 
                    WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e AND eliminado = false
                ),
                updated_at = CURRENT_TIMESTAMP
                WHERE id_producto = :p AND id_bodega = :b AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idProducto, ':b' => $idBodega, ':e' => $idEmpresa]);
    }

    public function syncPrecios(int $idProducto, int $idEmpresa, array $precios, int $userId): void
    {
        $sqlDel = "UPDATE productos_precios SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_precios (id_empresa, id_producto, nombre_precio, precio, valido_desde, valido_hasta, estado, created_by, updated_by)
                   VALUES (:id_e, :id_p, :nom, :pre, :des, :has, :est, :uid, :uid)";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($precios as $p) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':nom'   => $p['nombre_precio'],
                ':pre'   => $p['precio'],
                ':des'   => !empty($p['valido_desde']) ? $p['valido_desde'] : null,
                ':has'   => !empty($p['valido_hasta']) ? $p['valido_hasta'] : null,
                ':est'   => isset($p['estado']) ? ($p['estado'] ? 'true' : 'false') : 'true',
                ':uid'   => $userId
            ]);
        }
    }

    public function syncComponentes(int $idProducto, int $idEmpresa, array $componentes, int $userId): void
    {
        $sqlDel = "UPDATE productos_componentes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto_padre = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_componentes (id_empresa, id_producto_padre, id_producto_hijo, cantidad, id_medida, created_by, updated_by)
                   VALUES (:id_e, :id_p, :id_h, :can, :id_m, :uid, :uid)";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($componentes as $c) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':id_h'  => $c['id_producto_hijo'],
                ':can'   => $c['cantidad'],
                ':id_m'  => !empty($c['id_medida']) ? $c['id_medida'] : null,
                ':uid'   => $userId
            ]);
        }
    }

    public function syncVariantes(int $idProducto, int $idEmpresa, array $variantes, int $userId): void
    {
        $sqlDel = "UPDATE productos_variantes SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid 
                   WHERE id_producto = :id_p AND id_empresa = :id_e";
        $stDel = $this->db->prepare($sqlDel);
        $stDel->execute([':uid' => $userId, ':id_p' => $idProducto, ':id_e' => $idEmpresa]);

        $sqlIns = "INSERT INTO productos_variantes (id_empresa, id_producto, nombre, valor, precio_adicional, created_by, updated_by)
                   VALUES (:id_e, :id_p, :nom, :val, :pad, :uid, :uid)";
        $stIns = $this->db->prepare($sqlIns);

        foreach ($variantes as $v) {
            $stIns->execute([
                ':id_e'  => $idEmpresa,
                ':id_p'  => $idProducto,
                ':nom'   => $v['nombre'],
                ':val'   => $v['valor'],
                ':pad'   => !empty($v['precio_adicional']) ? $v['precio_adicional'] : 0,
                ':uid'   => $userId
            ]);
        }
    }
    public function softDelete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_at = CURRENT_TIMESTAMP, 
                deleted_by = :uid,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :uid
                WHERE id = :id AND id_empresa = :id_e AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_e' => $idEmpresa]);
    }
    /**
     * Calcula el stock total del producto sumando todos los movimientos de Kardex
     * (Fuente de verdad absoluta)
     */
    public function getInventarioGeneral(int $idProducto, int $idEmpresa): float
    {
        $sql = "SELECT COALESCE(SUM(cantidad), 0) as total
                FROM inventario_kardex 
                WHERE id_producto = :id_p AND id_empresa = :id_e AND eliminado = false";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id_p' => $idProducto, ':id_e' => $idEmpresa]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    /**
     * Verifica si el producto ha sido usado en facturas de venta o en movimientos de inventario.
     * Cuando es true, no se permite modificar código, nombre ni tipo_produccion.
     */
    public function estaUsadoEnDocumentos(int $id, int $idEmpresa): bool
    {
        $sqlV = "SELECT 1 FROM ventas_detalle WHERE id_producto = :id LIMIT 1";
        $stV  = $this->db->prepare($sqlV);
        $stV->execute([':id' => $id]);
        if ($stV->fetchColumn()) return true;

        $sqlK = "SELECT 1 FROM inventario_kardex
                 WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false LIMIT 1";
        $stK  = $this->db->prepare($sqlK);
        $stK->execute([':id' => $id, ':ide' => $idEmpresa]);
        return (bool) $stK->fetchColumn();
    }

    /**
     * Devuelve un listado de los módulos donde el producto está siendo utilizado,
     * filtrando por el ambiente activo de la empresa (tipo_ambiente).
     *
     * Regla de entornos separados:
     *  - Si la empresa está en PRUEBAS (1): solo revisa documentos de pruebas.
     *  - Si la empresa está en PRODUCCIÓN (2): solo revisa documentos de producción.
     * Así, un producto usado únicamente en pruebas puede eliminarse cuando la empresa
     * está en producción (y viceversa).
     *
     * Excepciones sin tipo_ambiente (suscripciones, órdenes, pedidos, componentes):
     * se verifican siempre porque son catálogos que aplican a ambos entornos.
     */
    public function obtenerUsos(int $id, int $idEmpresa, ?string $tipoAmbiente = null): array
    {
        $usos = [];
        $amb  = $tipoAmbiente; // '1' pruebas | '2' producción | null = todos

        // Documentos transaccionales con tipo_ambiente.
        // 'amb_col' = expresión de la columna tipo_ambiente; el filtro de ambiente
        // se agrega dinámicamente (con un único parámetro) para evitar errores de
        // tipo en PostgreSQL al reutilizar el placeholder.
        $checksAmbiente = [
            [
                'sql'    => "SELECT COUNT(*) FROM ventas_detalle vd
                             JOIN ventas_cabecera vc ON vc.id = vd.id_venta
                                AND vc.id_empresa = :ide AND vc.eliminado = false
                             WHERE vd.id_producto = :id",
                'amb_col' => 'vc.tipo_ambiente',
                'nombre' => 'Facturas de venta',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM compras_detalle cd
                             JOIN compras_cabecera cc ON cc.id = cd.id_compra
                                AND cc.id_empresa = :ide AND cc.eliminado = false
                             WHERE cd.id_producto = :id",
                'amb_col' => 'cc.tipo_ambiente',
                'nombre' => 'Compras',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM notas_credito_detalle ncd
                             JOIN notas_credito_cabecera ncc ON ncc.id = ncd.id_nota_credito
                                AND ncc.id_empresa = :ide AND ncc.eliminado = false
                             WHERE ncd.id_producto = :id",
                'amb_col' => 'ncc.tipo_ambiente',
                'nombre' => 'Notas de crédito',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM inventario_kardex
                             WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false",
                'amb_col' => 'tipo_ambiente',
                'nombre' => 'Movimientos de inventario',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM guias_remision_detalle grd
                             JOIN guias_remision_cabecera grc ON grc.id = grd.id_guia_remision
                                AND grc.id_empresa = :ide AND grc.eliminado = false
                             WHERE grd.id_producto = :id",
                'amb_col' => 'grc.tipo_ambiente',
                'nombre' => 'Guías de remisión',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM liquidaciones_detalle ld
                             JOIN liquidaciones_cabecera lc ON lc.id = ld.id_cabecera
                                AND lc.id_empresa = :ide AND lc.eliminado = false
                             WHERE ld.id_producto = :id",
                'amb_col' => 'lc.tipo_ambiente',
                'nombre' => 'Liquidaciones de compra',
            ],
        ];

        // Catálogos sin tipo_ambiente: se verifican siempre (aplican a ambos entornos)
        $checksSinAmbiente = [
            [
                'sql'    => "SELECT COUNT(*) FROM suscripciones_detalle sd
                             WHERE sd.id_producto = :id AND sd.id_empresa = :ide AND sd.eliminado = false",
                'nombre' => 'Suscripciones',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM ordenes_compra_detalle od
                             WHERE od.id_producto = :id AND od.id_empresa = :ide",
                'nombre' => 'Órdenes de compra',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM pedidos_detalle pd
                             JOIN pedidos_cabecera pc ON pc.id = pd.id_pedido
                                AND pc.id_empresa = :ide AND pc.eliminado = false
                             WHERE pd.id_producto = :id",
                'nombre' => 'Pedidos',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM productos_componentes
                             WHERE id_componente = :id AND id_empresa = :ide AND eliminado = false",
                'nombre' => 'Componente de otros productos',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM factura_express_items
                             WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false",
                'nombre' => 'Plantillas Factura Express',
            ],
            [
                'sql'    => "SELECT COUNT(*) FROM firmas_electronicas
                             WHERE id_producto = :id AND id_empresa = :ide AND eliminado = false",
                'nombre' => 'Firmas electrónicas',
            ],
        ];

        foreach ($checksAmbiente as $check) {
            try {
                $sql    = $check['sql'];
                $params = [':id' => $id, ':ide' => $idEmpresa];
                // Filtrar por ambiente activo solo si se conoce (columnas varchar; comparar como texto)
                if ($amb !== null && !empty($check['amb_col'])) {
                    $sql .= " AND CAST({$check['amb_col']} AS VARCHAR) = :amb";
                    $params[':amb'] = $amb;
                }
                $st = $this->db->prepare($sql);
                $st->execute($params);
                if ((int)$st->fetchColumn() > 0) {
                    $usos[] = $check['nombre'];
                }
            } catch (\Throwable) {}
        }

        foreach ($checksSinAmbiente as $check) {
            try {
                $st = $this->db->prepare($check['sql']);
                $st->execute([':id' => $id, ':ide' => $idEmpresa]);
                if ((int)$st->fetchColumn() > 0) {
                    $usos[] = $check['nombre'];
                }
            } catch (\Throwable) {}
        }

        return $usos;
    }

    /**
     * Retorna el tipo_ambiente activo de la empresa ('1' pruebas | '2' producción).
     */
    public function getTipoAmbienteEmpresa(int $idEmpresa): ?string
    {
        $st = $this->db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ? AND eliminado = false LIMIT 1");
        $st->execute([$idEmpresa]);
        $val = $st->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    /**
     * Retorna los IDs del tipo medida "Unidad" (código "0") y su unidad "Unidad" para la empresa.
     */
    public function getMedidaDefaultUnidad(int $idEmpresa): ?array
    {
        $sqlTipo = "SELECT id FROM tipo_medida
                    WHERE id_empresa = :e AND eliminado = false AND status = true
                      AND (LOWER(nombre) = 'unidad' OR codigo = '0')
                    ORDER BY (CASE WHEN codigo = '0' THEN 0 ELSE 1 END) ASC
                    LIMIT 1";
        $stTipo = $this->db->prepare($sqlTipo);
        $stTipo->execute([':e' => $idEmpresa]);
        $idTipo = $stTipo->fetchColumn();
        if (!$idTipo) return null;

        $sqlMed = "SELECT id FROM unidades_medida
                   WHERE id_tipo = :t AND id_empresa = :e AND eliminado = false AND status = true
                   ORDER BY (CASE WHEN LOWER(nombre) = 'unidad' THEN 0 ELSE 1 END) ASC
                   LIMIT 1";
        $stMed = $this->db->prepare($sqlMed);
        $stMed->execute([':t' => $idTipo, ':e' => $idEmpresa]);
        $idMedida = $stMed->fetchColumn();
        if (!$idMedida) return null;

        return ['id_tipo_medida' => (int)$idTipo, 'id_medida' => (int)$idMedida];
    }

    public function getSiguienteCodigo(int $idEmpresa, string $tipo): string
    {
        $prefijo = ($tipo === '01') ? 'P' : 'S';

        // Saltar códigos ya ocupados (autogenerados o escritos a mano).
        $stExiste = $this->db->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo)
               AND eliminado = false
             LIMIT 1"
        );

        // 1) Continuar la numeración real de la empresa: si ya existen códigos
        //    puramente numéricos para este tipo (p. ej. 202401…202417, aunque no
        //    sigan el formato P00X/S00X), se toma el mayor y se sugiere el
        //    siguiente, conservando el mismo largo (relleno de ceros). Se usa el
        //    MÁXIMO y no "el último creado" porque un producto reciente con un
        //    código manual suelto (p. ej. '001') no debe desviar la secuencia.
        //    Se limita a 9 dígitos (además de excluir del cast a bigint) para
        //    no confundir un código de barras (EAN-13/UPC-A, 12-13 dígitos)
        //    escrito por error en 'codigo' con la numeración real del producto.
        $stMax = $this->db->prepare(
            "SELECT codigo FROM {$this->table}
             WHERE id_empresa = :id_empresa AND tipo_produccion = :tipo
               AND eliminado = false AND codigo ~ '^[0-9]{1,9}$'
             ORDER BY codigo::bigint DESC
             LIMIT 1"
        );
        $stMax->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipo]);
        $maxCodigo = (string) ($stMax->fetchColumn() ?: '');

        if ($maxCodigo !== '') {
            $longitud = strlen($maxCodigo);
            $numero = (int) $maxCodigo;
            do {
                $numero++;
                $codigo = str_pad((string) $numero, $longitud, '0', STR_PAD_LEFT);
                $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
            } while ($stExiste->fetchColumn() && $numero < 999999999999999);

            return $codigo;
        }

        // 2) Prefijo genérico + número (p. ej. 'VTA.0001', 'VTA.0002'… o el propio
        //    'P001', 'S001'…, que es solo un caso particular de este mismo patrón).
        //    Se separa cada código en "prefijo" (parte no numérica) + "sufijo"
        //    (dígitos finales, máx. 9) y se agrupa por prefijo. Gana el prefijo
        //    más usado (no el del último creado, por la misma razón del punto 1:
        //    un código manual suelto no debe desviar la convención real de la
        //    empresa); empate se resuelve por el sufijo más alto encontrado.
        $stTodos = $this->db->prepare(
            "SELECT codigo FROM {$this->table}
             WHERE id_empresa = :id_empresa AND tipo_produccion = :tipo
               AND eliminado = false"
        );
        $stTodos->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipo]);

        $grupos = [];
        foreach ($stTodos->fetchAll(PDO::FETCH_COLUMN) as $codigoExistente) {
            $codigoExistente = (string) $codigoExistente;
            if (!preg_match('/^(.*?)([0-9]{1,9})$/', $codigoExistente, $m) || $m[1] === '') {
                continue; // sin prefijo (numérico puro) ya se resolvió en el punto 1
            }
            $pref = $m[1];
            $valor = (int) $m[2];
            if (!isset($grupos[$pref])) {
                $grupos[$pref] = ['count' => 0, 'max' => -1, 'codigoMax' => ''];
            }
            $grupos[$pref]['count']++;
            if ($valor > $grupos[$pref]['max']) {
                $grupos[$pref]['max'] = $valor;
                $grupos[$pref]['codigoMax'] = $codigoExistente;
            }
        }

        if (!empty($grupos)) {
            uasort($grupos, fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: $b['max'] <=> $a['max']);
            $prefGanador = array_key_first($grupos);
            $g = $grupos[$prefGanador];

            $sufMax = substr($g['codigoMax'], strlen($prefGanador));
            $longitud = strlen($sufMax);
            $numero = (int) $sufMax;

            do {
                $numero++;
                $codigo = $prefGanador . str_pad((string) $numero, $longitud, '0', STR_PAD_LEFT);
                $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
            } while ($stExiste->fetchColumn() && $numero < 999999999);

            return $codigo;
        }

        // 3) Catálogo vacío para este tipo: no hay ninguna convención previa que
        //    seguir. Se arranca desde cero con el formato autogenerado (P001, S001…).
        $numero = 0;
        do {
            $numero++;
            $codigo = $prefijo . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
        } while ($stExiste->fetchColumn() && $numero < 999999999);

        return $codigo;
    }

    /**
     * Prefijos de código ya en uso para un tipo de producción, con el conteo de
     * productos que los usan (para que el usuario elija de cuál familia quiere
     * el siguiente consecutivo, en vez de que el sistema adivine "el ganador").
     * Mismo criterio de extracción de prefijo que getSiguienteCodigo() (texto no
     * numérico al inicio + hasta 9 dígitos finales).
     *
     * @return array<int,array{prefijo:string,cantidad:int,ejemplo:string}>
     */
    public function getPrefijosCodigo(int $idEmpresa, string $tipo): array
    {
        $sql = "SELECT codigo FROM {$this->table}
                WHERE id_empresa = :id_empresa AND tipo_produccion = :tipo
                  AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipo]);

        $grupos = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $codigoExistente) {
            $codigoExistente = (string) $codigoExistente;
            if (!preg_match('/^(.*?)([0-9]{1,9})$/', $codigoExistente, $m) || $m[1] === '') {
                continue; // código numérico puro: no tiene un prefijo que agrupar
            }
            $pref = $m[1];
            if (!isset($grupos[$pref])) {
                $grupos[$pref] = ['count' => 0, 'ejemplo' => $codigoExistente];
            }
            $grupos[$pref]['count']++;
        }

        uasort($grupos, fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        $resultado = [];
        foreach ($grupos as $prefijo => $g) {
            $resultado[] = ['prefijo' => $prefijo, 'cantidad' => $g['count'], 'ejemplo' => $g['ejemplo']];
        }
        return $resultado;
    }

    /**
     * Siguiente código consecutivo para un prefijo específico, elegido por el
     * usuario (p. ej. 'CM' -> 'CM002'). A diferencia de getSiguienteCodigo(),
     * no adivina el prefijo "ganador": el usuario ya lo indicó.
     */
    public function getSiguienteCodigoPorPrefijo(int $idEmpresa, string $tipo, string $prefijo): string
    {
        $prefijo = trim($prefijo);
        if ($prefijo === '') {
            throw new \InvalidArgumentException('Prefijo vacío.');
        }

        $stTodos = $this->db->prepare(
            "SELECT codigo FROM {$this->table}
             WHERE id_empresa = :id_empresa AND tipo_produccion = :tipo
               AND eliminado = false AND codigo ILIKE :prefijo_like"
        );
        $stTodos->execute([
            ':id_empresa'    => $idEmpresa,
            ':tipo'          => $tipo,
            ':prefijo_like'  => str_replace(['%', '_'], ['\\%', '\\_'], $prefijo) . '%',
        ]);

        $max = -1;
        $longitud = 3;
        foreach ($stTodos->fetchAll(PDO::FETCH_COLUMN) as $codigoExistente) {
            $codigoExistente = (string) $codigoExistente;
            if (stripos($codigoExistente, $prefijo) !== 0) continue;
            $resto = substr($codigoExistente, strlen($prefijo));
            if (!preg_match('/^[0-9]{1,9}$/', $resto)) continue;
            $valor = (int) $resto;
            if ($valor > $max) {
                $max = $valor;
                $longitud = strlen($resto);
            }
        }

        $stExiste = $this->db->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE id_empresa = :id_empresa AND lower(codigo) = lower(:codigo)
               AND eliminado = false
             LIMIT 1"
        );

        $numero = max($max, 0);
        do {
            $numero++;
            $codigo = $prefijo . str_pad((string) $numero, $longitud, '0', STR_PAD_LEFT);
            $stExiste->execute([':id_empresa' => $idEmpresa, ':codigo' => $codigo]);
        } while ($stExiste->fetchColumn() && $numero < 999999999);

        return $codigo;
    }

    public function searchSimple(int $idEmpresa, string $q, int $limit = 10, string $tipo = '', int $exclude = 0, bool $soloActivos = false): array
    {
        $db = \App\core\Database::getConnection();
        $params = [$idEmpresa, "%$q%", "%$q%"];
        $whereSql = "";

        if ($tipo !== '') {
            $whereSql .= " AND p.tipo_produccion = ? ";
            $params[] = $tipo;
        }

        if ($soloActivos) {
            $whereSql .= " AND p.status = 1 ";
        }

        if ($exclude > 0) {
            $whereSql .= " AND p.id != ? ";
            $params[] = $exclude;
        }

        $params[] = $limit;

        $st = $db->prepare("SELECT p.id, p.codigo, p.nombre, p.id_medida, p.id_tipo_medida, um.nombre AS nombre_medida
                            FROM productos p
                            LEFT JOIN unidades_medida um ON um.id = p.id_medida
                            WHERE p.id_empresa = ? AND (p.codigo ILIKE ? OR p.nombre ILIKE ?) AND p.eliminado = false 
                            {$whereSql}
                            ORDER BY p.nombre ASC LIMIT ?");
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Ubicaciones distintas ya usadas por productos de la empresa (para el autocompletado del campo). */
    public function getUbicacionesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT ubicacion FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND ubicacion IS NOT NULL AND ubicacion != ''
                ORDER BY ubicacion ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    public function softDeleteHomologacion(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE productos_homologacion SET 
                eliminado = true, 
                deleted_at = CURRENT_TIMESTAMP, 
                deleted_by = :uid,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :uid
                WHERE id = :id AND id_empresa = :id_e AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([':uid' => $idUsuario, ':id' => $id, ':id_e' => $idEmpresa]);
    }
}


