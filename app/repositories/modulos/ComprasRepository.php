<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\repositories\BaseRepository;

class ComprasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('compras_cabecera');
        // Valores recaudados por cuenta de terceros en planillas de servicios básicos
        // (bomberos, tasa de basura). Cuentas por Pagar y Egresos leen esta columna en
        // su SQL, así que debe existir aunque todavía no se haya corrido
        // database/migrations/20260827_compras_total_terceros.sql. Chequeada primero
        // (lectura barata) para no pagar el ALTER (lock exclusivo) en cada request una
        // vez migrada la columna.
        try {
            $existe = $this->db->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'compras_cabecera' AND column_name = 'total_terceros'")->fetchColumn();
            if ($existe === false) {
                $this->db->exec("ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS total_terceros NUMERIC(12,2) NOT NULL DEFAULT 0;");
            }
        } catch (\Throwable $e) {}
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTADO
    // ─────────────────────────────────────────────────────────────────────────

    /** Caché por instancia del tipo de ambiente de cada empresa consultada. */
    private array $tipoAmbienteCache = [];

    /**
     * Tipo de ambiente (1 = pruebas, 2 = producción) de la empresa, como el
     * VARCHAR(1) con el que se compara compras_cabecera.tipo_ambiente. Devuelve
     * NULL si la empresa no existe, para que el listado no muestre nada — igual
     * que hacía el sub-SELECT que este método reemplaza.
     */
    private function getTipoAmbienteEmpresa(int $idEmpresa): ?string
    {
        if (!array_key_exists($idEmpresa, $this->tipoAmbienteCache)) {
            $st = $this->db->prepare(
                "SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa"
            );
            $st->execute([':id_empresa' => $idEmpresa]);
            $valor = $st->fetchColumn();
            $this->tipoAmbienteCache[$idEmpresa] = $valor === false || $valor === null
                ? null
                : (string) $valor;
        }
        return $this->tipoAmbienteCache[$idEmpresa];
    }

    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL con la que se ordena. Es la whitelist del ORDER BY y el mapa que
     * necesita `OrdenListado::clausula()` para encadenar varias columnas.
     */
    public const MAPA_ORDEN = [
        'id'                  => 'c.id',
        'fecha_emision'       => 'c.fecha_emision',
        'fecha_registro'      => 'c.fecha_registro',
        'secuencial_prov'     => 'c.secuencial_prov',
        'importe_total'       => 'c.importe_total',
        'total_sin_impuestos' => 'c.total_sin_impuestos',
        'observaciones'       => 'c.observaciones',
        // Columnas que vienen de un JOIN: se prefija la tabla correcta.
        'tipo_comprobante'    => 'ca.comprobante',
        'proveedor_nombre'    => 'p.razon_social',
        'proveedor_ruc'       => 'p.identificacion',
        'usuario_nombre'      => 'u.nombre',
    ];

    /**
     * @param array $ordenMulti Criterios de orden [['col'=>…,'dir'=>…], …] cuando el
     *        llamador usa `OrdenListado`. Vacío = se ordena por $ordenCol/$ordenDir.
     */
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

        // El tipo de ambiente de la empresa se resuelve UNA vez en PHP en lugar
        // de con un sub-SELECT dentro del WHERE. Antes esa subconsulta viajaba
        // en las dos consultas del listado (COUNT y SELECT), obligaba a repetir
        // el placeholder :id_empresa y le escondía al planificador el valor
        // concreto de tipo_ambiente, que es justo una de las columnas del índice
        // idx_compras_listado. Si la empresa no existe el valor es NULL y la
        // comparación no calza con ninguna fila — mismo resultado que antes.
        $where = "WHERE c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = :tipo_ambiente";
        $params[':tipo_ambiente'] = $this->getTipoAmbienteEmpresa($idEmpresa);

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        // Abonos de la compra (pagos de Egresos + notas de crédito + retenciones), con
        // las MISMAS subconsultas que las columnas Saldo y Pago del listado (ver el
        // SELECT más abajo y el cálculo en la vista/controller). Los usan el texto
        // libre (columna Saldo), el filtro "pago:" y los numéricos "saldo:" y "retenido:".
        $sqlPagado    = "(SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false)";
        $sqlNc        = "(SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc WHERE nc.tipo_comprobante = '04' AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false)";
        $sqlRetenido  = "(SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada')";
        $sqlAbonos    = "($sqlPagado + $sqlNc + $sqlRetenido)";
        // Igual que la vista: saldo nunca negativo y las notas de crédito (04) no
        // tienen saldo por pagar.
        $saldo        = "(CASE WHEN c.tipo_comprobante = '04' THEN 0 ELSE GREATEST(0, c.importe_total - $sqlAbonos) END)";
        $ivaCalc      = '(c.importe_total - c.total_sin_impuestos - COALESCE(c.propina, 0) - COALESCE(c.total_ice, 0))';

        // Texto libre: el número del comprobante, el proveedor, la fecha, los importes,
        // el saldo, las observaciones y el documento modificado.
        //
        // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar:
        //   - Tipo, Sustento, Pago y Estado → modal de filtros (decisión anterior, igual
        //     que en Ingresos, Egresos y Facturas).
        //   - RUC / identificación del proveedor → filtro `ruc:` / `identificacion:`.
        //   - Número de autorización → filtro `autorizacion:` (17-09-2026). En los
        //     comprobantes electrónicos son 49 dígitos —fecha, RUC, serie, secuencial y
        //     un código numérico aleatorio de 8—, así que al escribir un número de
        //     factura caía dentro de la autorización de OTRAS compras por puro azar y el
        //     listado devolvía filas sin ninguna coincidencia visible. Medido con los
        //     datos locales (948 compras, 946 con autorización de 49 dígitos): el 5 % de
        //     las búsquedas por el propio N° de comprobante traía filas de más, 4,3 de
        //     media. Mismo caso que la clave de acceso en FacturaVentaRepository.
        //   - Usuario que registró → filtro `usuario:`.
        //   - Productos del detalle (código y descripción) → pestaña "Detalles" del modal
        //     de filtros (buscarEnDetalles()), que SÍ dice qué línea coincidió; desde el
        //     listado la compra aparecía sin que se viera el motivo. De paso se va la
        //     subconsulta STRING_AGG, que se evaluaba por cada compra de la empresa.
        if ($textoLibre !== '') {
            // Rendimiento: las columnas numéricas solo se evalúan si la palabra tiene
            // dígitos, y el SALDO (tres subconsultas por fila, lo más caro de todo) solo
            // si la palabra parece un monto con decimales. Buscar "garcia" ya no calcula
            // el saldo de cada compra de la empresa. Ver FiltrosBusqueda::condicionTexto.
            $digitos = \App\Helpers\FiltrosBusqueda::SI_DIGITOS;
            $decimal = \App\Helpers\FiltrosBusqueda::SI_DECIMAL;
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('c.establecimiento_prov', 'c.punto_emision_prov', 'c.secuencial_prov'), // N° Comprobante (canónico)
                    'c.secuencial_prov',
                    'p.razon_social',                                                               // Proveedor
                    // Fuera del listado, pero identifican la compra:
                    'c.observaciones',
                    'c.documento_modificado',
                    ['sql' => 'c.fecha_emision', 'si' => $digitos],                                 // Fecha
                    ['sql' => 'c.total_sin_impuestos', 'si' => $digitos],                           // Subtotal
                    ['sql' => $ivaCalc, 'si' => $digitos],                                          // IVA
                    ['sql' => 'c.importe_total', 'si' => $digitos],                                 // Total
                    ['sql' => "ROUND($saldo, 2)", 'si' => $decimal],                                // Saldo (caro)
                ],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        // ── Filtro especial: estado de pago (campo CALCULADO, no es columna) ──────
        // pago:pendiente | pago:abonada | pago:pagada (acepta lista). Misma regla que
        // el badge de la columna Pago: la NC (04) siempre cuenta como pagada.
        $pagoFiltro = $filtros['estado_pago'] ?? $filtros['pago'] ?? null;
        unset($filtros['estado_pago'], $filtros['pago']);
        if ($pagoFiltro !== null) {
            $valores = is_array($pagoFiltro['valor']) ? $pagoFiltro['valor'] : [$pagoFiltro['valor']];
            $conds = [];
            foreach ($valores as $val) {
                $v2 = strtolower(trim((string) $val));
                if (in_array($v2, ['pagada', 'pagado', 'pagadas'], true)) {
                    $conds[] = "(c.tipo_comprobante = '04' OR ROUND($saldo, 2) <= 0)";
                } elseif (in_array($v2, ['abonada', 'abonado', 'abonadas', 'parcial'], true)) {
                    $conds[] = "(c.tipo_comprobante <> '04' AND ROUND($saldo, 2) > 0 AND $sqlAbonos > 0)";
                } elseif (in_array($v2, ['pendiente', 'pendientes'], true)) {
                    $conds[] = "(c.tipo_comprobante <> '04' AND ROUND($saldo, 2) > 0 AND $sqlAbonos <= 0)";
                }
            }
            if ($conds) {
                $cond = '(' . implode(' OR ', $conds) . ')';
                if (!empty($pagoFiltro['neg'])) {
                    $cond = "NOT $cond";
                }
                $where .= " AND $cond";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)",
                'nro'            => "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)",
                'autorizacion'   => 'c.numero_autorizacion',
                'obs'            => 'c.observaciones',
                'observacion'    => 'c.observaciones',
                'usuario'        => 'u.nombre',
                'sustento'       => 'st.nombre',
                'documento_modificado' => 'c.documento_modificado',
            ],
            'exacto' => [
                'tipo_comprobante' => 'c.tipo_comprobante',
                'tipo'             => 'c.tipo_comprobante',
                // El estado dejó de ser un literal fijo con la aprobación de
                // compras: 'registrado', 'pendiente_aprobacion', 'rechazada'…
                'estado'           => 'c.estado',
                // Serie del PROVEEDOR (establecimiento_prov-punto_emision_prov): a
                // diferencia de los documentos que emite esta empresa, en Compras
                // la numeración es la del comprobante del proveedor.
                'serie'            => "CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov)",
                // Claves del modal de filtros (public/js/components/filtros_modal.js).
                'id_sustento'      => 'c.id_sustento_tributario',
                'id_usuario'       => 'c.created_by',
                'tipo_registro'    => 'c.tipo_registro',       // electronico / fisica / migrado
                'deducible'        => 'c.deducible',           // declaracion_iva / gasto_personal
                // asiento:si / asiento:no
                'asiento'          => "CASE WHEN c.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
                'orden_compra'     => "CASE WHEN c.id_orden_compra IS NULL THEN 'no' ELSE 'si' END",
                'retencion'        => "CASE WHEN EXISTS (SELECT 1 FROM retencion_compra_cabecera rx WHERE rx.id_compra = c.id AND rx.eliminado = false AND rx.estado != 'anulada') THEN 'si' ELSE 'no' END",
                // Booleano en BD; ::text lo hace robusto si viniera como texto migrado.
                'parte_relacionada' => "CASE WHEN COALESCE(c.parte_relacionada::text, '') IN ('true', 't', '1', 'si', 'SI', 'S') THEN 'si' ELSE 'no' END",
            ],
            'fecha' => [
                'fecha'          => 'c.fecha_emision',
                'fecha_emision'  => 'c.fecha_emision',
                'fecha_registro' => 'c.fecha_registro',
            ],
            'numerico' => [
                'monto'    => 'c.importe_total',
                'total'    => 'c.importe_total',
                'subtotal' => 'c.total_sin_impuestos',
                'iva'      => $ivaCalc,
                'descuento' => 'COALESCE(c.total_descuento, 0)',
                'saldo'    => $saldo,
                'retenido' => $sqlRetenido,
                // Comparación numérica exacta (no substring), igual que en los
                // demás módulos: "298" encuentra "000000298" sin escribir ceros.
                // A diferencia del secuencial propio (siempre numérico, generado
                // por el sistema), secuencial_prov lo escribe el usuario a mano
                // desde el comprobante del proveedor y a veces no es puro número
                // — el CASE evita que un valor no numérico rompa la consulta con
                // un error de cast; simplemente no calza con ningún filtro numérico.
                'secuencial' => "(CASE WHEN c.secuencial_prov ~ '^[0-9]+$' THEN c.secuencial_prov::numeric END)",
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND c.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Una o varias columnas (Shift+clic en el listado), siempre validadas contra
        // MAPA_ORDEN: lo único que puede llegar al ORDER BY sale de ahí.
        //
        // Desempate OBLIGATORIO por id: ninguna de las columnas ordenables es
        // única (345 de 515 compras de una misma empresa comparten
        // fecha_emision, que es el orden por defecto), y con LIMIT/OFFSET
        // PostgreSQL no garantiza un orden estable entre filas empatadas. Sin
        // este desempate la paginación repetía una fila en dos páginas y se
        // saltaba otra por completo: al recorrer las 26 páginas del listado
        // salían 515 filas pero solo 514 compras distintas. clausula() lo añade
        // al final y lo omite solo si ya se está ordenando por c.id.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula(
            $ordenMulti,
            self::MAPA_ORDEN,
            'c.fecha_emision',
            'c.id DESC'
        );

        // Columnas explícitas en lugar de "c.*" A PROPÓSITO: compras_cabecera
        // tiene la columna detalle_xml (TEXT con el XML del SRI, ~12 KB de
        // promedio y hasta 52 KB) y el listado la arrastraba en cada fila para
        // luego volcarla completa en el atributo data-row de cada <tr>. Eso
        // multiplicaba por ~8 el peso de cada página del listado (318 KB contra
        // 37 KB en 20 filas) sin que nadie la use: el modal recibe el XML por
        // getCompraAjax() y la descarga por descargarXml(), cada uno con su
        // propia consulta. Al agregar una columna nueva a la tabla, añadirla
        // aquí si el listado la necesita — pero NUNCA volver a traer detalle_xml.
        //
        // Rendimiento (2026-09-16): UNA sola consulta en dos fases.
        //  1) CTE `pagina`: aplica el WHERE (lo caro: texto libre, filtros calculados)
        //     UNA vez, ordena y corta la página; el total sale de COUNT(*) OVER (), que
        //     se calcula sobre todas las filas filtradas antes del LIMIT. Antes había un
        //     COUNT y un SELECT por separado que evaluaban el mismo WHERE dos veces.
        //  2) La consulta final une la página con la cabecera por PK y calcula las
        //     subconsultas de pagos / NC / retenciones SOLO para esas filas (20), no
        //     para todas las compras de la empresa.
        // `ca` va con LATERAL ... LIMIT 1: el catálogo comprobantes_autorizados repite
        // códigos (p. ej. 52) y el JOIN directo duplicaba la compra en el listado.
        $joinCatalogos = "INNER JOIN proveedores p          ON c.id_proveedor = p.id
                LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                LEFT  JOIN usuarios u             ON c.created_by = u.id
                LEFT  JOIN LATERAL (
                    SELECT cax.comprobante FROM comprobantes_autorizados cax
                    WHERE cax.codigo_comprobante = c.tipo_comprobante
                    ORDER BY cax.id LIMIT 1
                ) ca ON TRUE";
        $limite = $perPage > 0 ? " LIMIT $perPage OFFSET $offset" : '';
        $sql = "WITH pagina AS MATERIALIZED (
                    SELECT c.id, ROW_NUMBER() OVER ($orderBy) AS __rn, COUNT(*) OVER () AS __total
                    FROM compras_cabecera c
                    $joinCatalogos
                    $where
                    $orderBy
                    $limite
                )
                SELECT c.id, c.id_empresa, c.id_proveedor, c.id_establecimiento,
                       c.id_sustento_tributario, c.tipo_comprobante, c.tipo_id_proveedor,
                       c.parte_relacionada, c.establecimiento_prov, c.punto_emision_prov,
                       c.secuencial_prov, c.numero_autorizacion, c.fecha_emision,
                       c.fecha_registro, c.importe_total, c.observaciones,
                       c.created_at, c.updated_at, c.created_by, c.updated_by,
                       c.eliminado, c.deleted_at, c.deleted_by,
                       c.autorizacion_desde, c.autorizacion_hasta, c.fecha_caducidad,
                       c.tipo_registro, c.deducible, c.documento_modificado, c.motivo,
                       c.id_usuario, c.total_sin_impuestos, c.total_descuento, c.propina,
                       c.tipo_ambiente, c.id_asiento_contable, c.cod_doc_reembolso,
                       c.total_comprobantes_reembolso, c.total_base_imponible_reembolso,
                       c.total_impuesto_reembolso, c.id_orden_compra, c.estado,
                       c.token_aprobacion, c.aprobado_by, c.aprobado_at,
                       c.motivo_rechazo, c.total_terceros,
                       (c.importe_total - c.total_sin_impuestos - COALESCE(c.propina, 0) - COALESCE(c.total_ice, 0)) AS monto_iva,
                       p.razon_social      AS proveedor_nombre,
                       p.identificacion    AS proveedor_ruc,
                       st.nombre           AS sustento_nombre,
                       st.codigo           AS sustento_codigo,
                       u.nombre            AS usuario_nombre,
                       ca.comprobante      AS tipo_comprobante_nombre,
                       (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false) AS total_pagado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc WHERE nc.tipo_comprobante = '04' AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada') AS total_retencion,
                       pg.__total
                FROM pagina pg
                INNER JOIN compras_cabecera c ON c.id = pg.id
                $joinCatalogos
                ORDER BY pg.__rn";

        $rows = $this->query($sql, $params)->fetchAll();

        if ($rows) {
            $total = (int) $rows[0]['__total'];
        } elseif ($offset > 0) {
            // Página fuera de rango (p. ej. se borraron registros): sin filas no hay
            // __total, así que se cuenta aparte. Caso raro; la carga normal no pasa aquí.
            $total = (int) $this->query("SELECT COUNT(*) FROM compras_cabecera c $joinCatalogos $where", $params)->fetchColumn();
        } else {
            $total = 0;
        }
        foreach ($rows as &$r) {
            unset($r['__total']);
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Series del PROVEEDOR (establecimiento_prov-puntoEmision_prov) que
     * REALMENTE tienen al menos una compra guardada, para poblar el filtro
     * "Serie" del buscador.
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento_prov AS establecimiento, punto_emision_prov AS punto_emision
                FROM compras_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento_prov IS NOT NULL AND establecimiento_prov != ''
                ORDER BY establecimiento_prov, punto_emision_prov";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /** Sustentos tributarios REALMENTE usados en compras de la empresa (select "Sustento" del modal de filtros). */
    public function getSustentosUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT st.id, st.codigo, st.nombre
                FROM compras_cabecera c
                JOIN sustento_tributario st ON st.id = c.id_sustento_tributario
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                ORDER BY st.codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Usuarios que han registrado alguna compra en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConCompras(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM compras_cabecera c
                JOIN usuarios u ON u.id = c.created_by
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las compras (pestaña "Detalles" del modal de filtros):
     * devuelve cada producto comprado, forma de pago SRI, dato de información
     * adicional y comprobante de reembolso de terceros que coincide con el texto,
     * junto con la compra a la que pertenece. Mismo alcance que el listado (empresa,
     * no eliminadas, ambiente) y registros propios cuando no hay acceso total.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa, ':tipo_ambiente' => $this->getTipoAmbienteEmpresa($idEmpresa)];
        $whereBase = "c.id_empresa = :id_empresa AND c.eliminado = false AND c.tipo_ambiente = :tipo_ambiente";
        if ($idUsuario !== null) {
            $whereBase .= " AND c.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.codigo_principal', 'd.codigo_auxiliar', 'd.descripcion', 'd.cantidad::text', 'd.precio_unitario::text', 'd.precio_total_sin_impuesto::text'],
            $q, $params, 'dt'
        );
        $condPago = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['fp.nombre', 'cp.forma_pago', 'cp.total::text', 'cp.plazo::text', 'cp.unidad_tiempo'],
            $q, $params, 'pg'
        );
        $condAdic = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['a.nombre', 'a.valor'],
            $q, $params, 'ad'
        );
        $condReem = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['rt.razon_social_proveedor_reembolso', 'rt.identificacion_proveedor_reembolso',
             "CONCAT(rt.estab_doc_reembolso,'-',rt.pto_emi_doc_reembolso,'-',rt.secuencial_doc_reembolso)",
             'rt.numero_autorizacion_doc_reemb'],
            $q, $params, 'rb'
        );
        if ($condDet === '' || $condPago === '' || $condAdic === '' || $condReem === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT c.id, c.establecimiento_prov, c.punto_emision_prov, c.secuencial_prov,
                           c.fecha_emision, c.estado, p.razon_social AS proveedor
                    FROM compras_cabecera c
                    INNER JOIN proveedores p ON p.id = c.id_proveedor
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(d.codigo_principal, ''), d.codigo_auxiliar) AS tipo,
                           d.descripcion,
                           d.cantidad,
                           d.precio_total_sin_impuesto AS monto,
                           b.*
                    FROM compras_detalle d
                    JOIN base b ON b.id = d.id_compra
                    WHERE $condDet
                    UNION ALL
                    SELECT 'PAGO' AS origen,
                           COALESCE(fp.nombre, cp.forma_pago) AS tipo,
                           CASE WHEN COALESCE(cp.plazo, 0) > 0
                                THEN CONCAT_WS(' ', cp.plazo::text, NULLIF(cp.unidad_tiempo, ''))
                                ELSE 'Contado' END AS descripcion,
                           NULL AS cantidad,
                           cp.total AS monto,
                           b.*
                    FROM compras_pagos cp
                    JOIN base b ON b.id = cp.id_compra
                    LEFT JOIN formas_pago_sri fp ON fp.codigo = cp.forma_pago
                    WHERE $condPago
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           NULL AS monto,
                           b.*
                    FROM compras_adicional a
                    JOIN base b ON b.id = a.id_compra
                    WHERE $condAdic
                    UNION ALL
                    SELECT 'REEMBOLSO' AS origen,
                           CONCAT(rt.estab_doc_reembolso,'-',rt.pto_emi_doc_reembolso,'-',rt.secuencial_doc_reembolso) AS tipo,
                           NULLIF(CONCAT_WS(' · ', NULLIF(rt.razon_social_proveedor_reembolso, ''), NULLIF(rt.identificacion_proveedor_reembolso, '')), '') AS descripcion,
                           NULL AS cantidad,
                           (COALESCE(rt.base_imponible_total, 0) + COALESCE(rt.impuesto_total, 0)) AS monto,
                           b.*
                    FROM compras_reembolso_terceros rt
                    JOIN base b ON b.id = rt.id_compra
                    WHERE rt.eliminado = false AND $condReem
                ) x
                ORDER BY x.fecha_emision DESC, x.id DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tipos de comprobante que la empresa REALMENTE tiene registrados en Compras
     * (no el catálogo completo ni la lista acotada del modal de creación), para
     * poblar el filtro "Tipo comprobante" del buscador — mismo criterio que
     * getSeriesDistintas() para el filtro "Serie".
     */
    public function getTiposComprobanteUsados(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT c.tipo_comprobante AS codigo_comprobante,
                       COALESCE(ca.comprobante, c.tipo_comprobante) AS comprobante
                FROM compras_cabecera c
                LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                  AND c.tipo_comprobante IS NOT NULL AND c.tipo_comprobante != ''
                ORDER BY c.tipo_comprobante";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /**
     * Compras del rango de fechas para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     * No filtra por estado: una compra pendiente de aprobación ya está registrada
     * como documento recibido (lo que la aprobación detiene es pagarla, procesar
     * su inventario y asentarla), así que también debe salir en la descarga.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE c.id_empresa = :id_empresa AND c.eliminado = false
                   AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                   " . $this->condicionRangoDescargaMasiva('c.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params, 'fecha_emision', 'secuencial_prov');
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND c.created_by = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT c.id, c.establecimiento_prov AS establecimiento, c.punto_emision_prov AS punto_emision,
                       c.secuencial_prov AS secuencial, c.fecha_emision
                FROM compras_cabecera c
                $where
                ORDER BY c.fecha_emision ASC, c.id ASC";
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OBTENER POR ID
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Candado para el flujo "leer saldo → calcular → escribir" de un pago de compra
     * (CLAUDE.md §8). Llamar SIEMPRE antes de calcularSaldoPendiente() dentro de la
     * misma transacción del INSERT del egreso — se libera solo al COMMIT/ROLLBACK.
     */
    public function lockPago(int $idCompra, int $idEmpresa): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext(?))")
            ->execute(['pago_compra:' . $idEmpresa . ':' . $idCompra]);
    }

    /**
     * Saldo pendiente real de una compra, recalculado en el servidor (misma fórmula
     * que el listado: importe_total - pagado - notas_de_crédito - retenciones).
     * Llamar DESPUÉS de lockPago() dentro de la misma transacción — nunca confiar en
     * un saldo que mande el cliente, es la única forma de evitar que dos pagos
     * concurrentes contra la misma compra pasen ambos la validación de "no pagar de más".
     */
    public function calcularSaldoPendiente(int $idCompra, int $idEmpresa): float
    {
        $sql = "SELECT c.importe_total,
                       (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed
                          INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id
                        WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id
                          AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false) AS total_pagado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc
                        WHERE nc.tipo_comprobante = '04'
                          AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov)
                          AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r
                        WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada') AS total_retencion
                FROM compras_cabecera c
                WHERE c.id = ? AND c.id_empresa = ? AND c.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$idCompra, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }

        $saldo = (float) $row['importe_total'] - (float) $row['total_pagado'] - (float) $row['total_nc'] - (float) $row['total_retencion'];
        return max(0.0, $saldo);
    }

    public function getPorId(int $id, ?int $idEmpresa = null): ?array
    {
        $where = "WHERE c.id = ? AND c.eliminado = FALSE";
        $params = [$id];

        if ($idEmpresa !== null) {
            $where .= " AND c.id_empresa = ?";
            $params[] = $idEmpresa;
        }

        $sql = "SELECT c.*,
                       (c.importe_total - c.total_sin_impuestos - COALESCE(c.propina, 0) - COALESCE(c.total_ice, 0)) AS monto_iva,
                       p.razon_social          AS proveedor_nombre,
                       p.identificacion        AS proveedor_ruc,
                       p.direccion             AS proveedor_direccion,
                       p.email                 AS proveedor_email,
                       p.tipo_id_proveedor     AS proveedor_tipo_id,
                       COALESCE(icv.nombre,'') AS proveedor_nombre_tipo_id,
                       st.nombre               AS sustento_nombre,
                       st.codigo               AS sustento_codigo,
                       uc.nombre               AS creado_por_nombre,
                       uu.nombre               AS actualizado_por_nombre,
                       ca.comprobante          AS tipo_comprobante_nombre,
                       oc.numero_orden         AS orden_compra_numero,
                       oc.estado               AS orden_compra_estado,
                       oc.fecha_orden          AS orden_compra_fecha,
                       (SELECT COALESCE(SUM(ed.monto_pagado), 0) FROM egresos_detalle ed INNER JOIN egresos_cabecera ec ON ed.id_egreso = ec.id WHERE ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id AND ed.eliminado = false AND ec.estado != 'anulado' AND ec.eliminado = false) AS total_pagado,
                       (SELECT COALESCE(SUM(nc.importe_total), 0) FROM compras_cabecera nc WHERE nc.tipo_comprobante = '04' AND nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AND nc.id_proveedor = c.id_proveedor AND nc.id_empresa = c.id_empresa AND nc.eliminado = false) AS total_nc,
                       (SELECT COALESCE(SUM(r.total_retenido), 0) FROM retencion_compra_cabecera r WHERE r.id_compra = c.id AND r.eliminado = false AND r.estado != 'anulada') AS total_retencion
                FROM compras_cabecera c
                INNER JOIN proveedores p ON c.id_proveedor = p.id
                LEFT  JOIN identificador_comprador_vendedor icv ON icv.codigo = p.tipo_id_proveedor
                LEFT  JOIN sustento_tributario st ON c.id_sustento_tributario = st.id
                LEFT  JOIN usuarios uc ON c.created_by  = uc.id
                LEFT  JOIN usuarios uu ON c.updated_by  = uu.id
                LEFT  JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                LEFT  JOIN ordenes_compra oc ON oc.id = c.id_orden_compra AND oc.eliminado = false
                $where";
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    /** Vincula (o desvincula, pasando null) esta compra con una orden de compra. */
    public function vincularOrdenCompra(int $idCompra, int $idEmpresa, ?int $idOrdenCompra, int $idUsuario): void
    {
        $sql = "UPDATE compras_cabecera SET
                    id_orden_compra = :id_orden_compra,
                    updated_at      = NOW(),
                    updated_by      = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $this->query($sql, [
            ':id_orden_compra' => $idOrdenCompra,
            ':updated_by'      => $idUsuario,
            ':id'              => $idCompra,
            ':id_empresa'      => $idEmpresa,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETALLES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ¿La compra proviene de una migración? (tiene fila en migracion_mysql_map).
     * Las compras migradas no se pueden editar.
     */
    public function esMigrado(int $id, int $idEmpresa): bool
    {
        $row = $this->query(
            "SELECT 1 FROM migracion_mysql_map
              WHERE entidad = 'compras' AND id_destino = ? AND id_empresa = ?
              LIMIT 1",
            [$id, $idEmpresa]
        )->fetchColumn();
        return (bool) $row;
    }

    /**
     * Suma del importe de las notas de crédito (tipo 04) que modifican esta compra.
     * Vínculo: nc.documento_modificado = numero de la compra + mismo proveedor/empresa.
     */
    public function getTotalNotasCredito(int $idCompra, int $idEmpresa): float
    {
        $sql = "SELECT COALESCE(SUM(nc.importe_total), 0)
                  FROM compras_cabecera nc
                  JOIN compras_cabecera c
                       ON nc.documento_modificado = CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov)
                      AND nc.id_proveedor = c.id_proveedor
                      AND nc.id_empresa   = c.id_empresa
                 WHERE c.id = ? AND c.id_empresa = ?
                   AND nc.tipo_comprobante = '04' AND nc.eliminado = false";
        return (float) $this->query($sql, [$idCompra, $idEmpresa])->fetchColumn();
    }

    /**
     * Documentos relacionados de una compra:
     *  - Si la compra es una nota de crédito (tipo 04): devuelve la FACTURA que modifica.
     *  - Si es una factura/compra: devuelve sus NOTAS DE CRÉDITO (tipo 04).
     * Cada documento incluye sus detalles (líneas).
     */
    public function getDocumentosRelacionados(int $idCompra, int $idEmpresa): array
    {
        $cab = $this->getPorId($idCompra, $idEmpresa);
        if (!$cab) {
            return ['relacion' => 'ninguno', 'documentos' => []];
        }

        $numero = ($cab['establecimiento_prov'] ?? '') . '-' . ($cab['punto_emision_prov'] ?? '') . '-' . ($cab['secuencial_prov'] ?? '');
        $esNota = ((string)($cab['tipo_comprobante'] ?? '')) === '04';

        if ($esNota) {
            // Buscar la factura que esta nota de crédito modifica.
            $relacion = 'factura';
            $sql = "SELECT c.id,
                           CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
                           c.fecha_emision, c.importe_total, c.total_sin_impuestos, c.tipo_comprobante,
                           ca.comprobante AS tipo_comprobante_nombre
                      FROM compras_cabecera c
                      LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                     WHERE c.id_empresa = ? AND c.eliminado = false
                       AND c.id_proveedor = ?
                       AND CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) = ?
                       AND c.tipo_comprobante NOT IN ('04', '05')
                     ORDER BY c.id ASC";
            $params = [$idEmpresa, (int)($cab['id_proveedor'] ?? 0), (string)($cab['documento_modificado'] ?? '')];
        } else {
            // Buscar las notas de crédito que modifican esta compra.
            $relacion = 'nota_credito';
            $sql = "SELECT c.id,
                           CONCAT(c.establecimiento_prov, '-', c.punto_emision_prov, '-', c.secuencial_prov) AS numero,
                           c.fecha_emision, c.importe_total, c.total_sin_impuestos, c.tipo_comprobante,
                           ca.comprobante AS tipo_comprobante_nombre
                      FROM compras_cabecera c
                      LEFT JOIN comprobantes_autorizados ca ON ca.codigo_comprobante = c.tipo_comprobante
                     WHERE c.id_empresa = ? AND c.eliminado = false
                       AND c.id_proveedor = ?
                       AND c.tipo_comprobante = '04'
                       AND c.documento_modificado = ?
                     ORDER BY c.id ASC";
            $params = [$idEmpresa, (int)($cab['id_proveedor'] ?? 0), $numero];
        }

        $docs = $this->query($sql, $params)->fetchAll();
        foreach ($docs as &$doc) {
            $doc['detalles'] = $this->getDetalles((int)$doc['id']);
        }
        unset($doc);

        return ['relacion' => $relacion, 'documentos' => $docs];
    }

    public function getDetalles(int $idCompra): array
    {
        $sql = "SELECT d.*,
                       COALESCE(pr.nombre, ph_pr.nombre, d.descripcion) AS producto_nombre, 
                       COALESCE(pr.codigo, ph_pr.codigo) AS producto_codigo, 
                       COALESCE(pr.id_medida, ph_pr.id_medida) AS product_id_medida, 
                       COALESCE(um.id_tipo, ph_um.id_tipo) AS product_id_tipo_medida,
                       COALESCE(pr.id, ph_pr.id) AS id_producto_vinculado
                FROM compras_detalle d
                LEFT JOIN compras_cabecera c ON d.id_compra = c.id
                LEFT JOIN productos pr ON d.id_producto = pr.id
                LEFT JOIN unidades_medida um ON um.id = pr.id_medida
                LEFT JOIN productos_homologacion ph ON ph.id_proveedor = c.id_proveedor 
                                                     AND ph.id_empresa = c.id_empresa 
                                                     AND ph.codigo_proveedor = d.codigo_principal 
                                                     AND ph.eliminado = false
                LEFT JOIN productos ph_pr ON ph.id_producto = ph_pr.id
                LEFT JOIN unidades_medida ph_um ON ph_um.id = ph_pr.id_medida
                WHERE d.id_compra = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idCompra])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        return $this->query(
            "SELECT * FROM compras_detalle_impuestos WHERE id_compra_detalle = ?",
            [$idDetalle]
        )->fetchAll();
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
        $sql = "SELECT * FROM compras_detalle_impuestos WHERE id_compra_detalle IN ($ph)";

        $porDetalle = [];
        foreach ($this->query($sql, $ids)->fetchAll() as $imp) {
            $porDetalle[(int) $imp["id_compra_detalle"]][] = $imp;
        }
        return $porDetalle;
    }

    /**
     * Una sola línea de compra por su id, con datos de cabecera necesarios para
     * precargar un alta de Activo Fijo (proveedor, fecha de emisión).
     */
    public function getDetalleById(int $idDetalle, int $idEmpresa): ?array
    {
        $sql = "SELECT d.*, c.id_empresa, c.id_proveedor, c.fecha_emision,
                       p.razon_social AS proveedor_nombre
                FROM compras_detalle d
                INNER JOIN compras_cabecera c ON d.id_compra = c.id
                LEFT JOIN proveedores p ON c.id_proveedor = p.id
                WHERE d.id = ? AND c.id_empresa = ? AND c.eliminado = false";
        $row = $this->query($sql, [$idDetalle, $idEmpresa])->fetch();
        return $row ?: null;
    }

    /**
     * Vincula el asiento contable generado a la compra.
     */
    public function updateAsientoContable(int $idCompra, int $idAsiento): void
    {
        $this->query(
            "UPDATE compras_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento, $idCompra]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PAGOS
    // ─────────────────────────────────────────────────────────────────────────

    public function getPagos(int $idCompra): array
    {
        return $this->query(
            "SELECT cp.*, fp.nombre AS forma_pago_nombre
             FROM compras_pagos cp
             LEFT JOIN formas_pago_sri fp ON fp.codigo = cp.forma_pago
             WHERE cp.id_compra = ?",
            [$idCompra]
        )->fetchAll();
    }

    public function getInfoAdicional(int $idCompra): array
    {
        return $this->query(
            "SELECT * FROM compras_adicional WHERE id_compra = ?",
            [$idCompra]
        )->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FACTURA DE REEMBOLSO RECIBIDA (bloque <reembolsos> del XML, codDocReembolso=41)
    // ─────────────────────────────────────────────────────────────────────────

    public function getReembolsoTerceros(int $idCompra): array
    {
        return $this->query(
            "SELECT * FROM compras_reembolso_terceros WHERE id_compra = ? AND eliminado = false ORDER BY id ASC",
            [$idCompra]
        )->fetchAll();
    }

    public function getImpuestosReembolsoTercero(int $idCompraTercero): array
    {
        return $this->query(
            "SELECT * FROM compras_reembolso_terceros_impuestos WHERE id_compra_tercero = ?",
            [$idCompraTercero]
        )->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RETENCIONES
    // ─────────────────────────────────────────────────────────────────────────



    // Asiento contable: gestionado por módulo de contabilidad independiente

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS — CABECERA
    // ─────────────────────────────────────────────────────────────────────────

    public function insertCabecera(array $data): int
    {
        // tipo_ambiente NO se toma de $data: el formulario manual nunca lo envía y quedaría
        // en 1 por defecto, mientras el listado filtra contra el ambiente ACTUAL de la
        // empresa (getListado(), ~línea 43) — un desfase deja la compra invisible para
        // siempre (mismo bug ya visto en Kardex y Pedidos). Se toma en vivo de `empresas`,
        // igual que ya hace DocumentoAutomatedRegisterService para las cargas del SRI.
        $sql = "INSERT INTO compras_cabecera (
                    id_empresa, id_proveedor, id_establecimiento,
                    id_sustento_tributario, tipo_comprobante, tipo_id_proveedor,
                    parte_relacionada, establecimiento_prov, punto_emision_prov,
                    secuencial_prov, numero_autorizacion, fecha_emision, fecha_registro,
                    total_sin_impuestos, total_descuento, importe_total, total_ice, propina,
                    autorizacion_desde, autorizacion_hasta, fecha_caducidad,
                    tipo_registro, deducible, documento_modificado, motivo,
                    observaciones, estado, created_by, updated_by, id_usuario,
                    pago_loc_ext, cod_pais_pago, aplic_conv_dob_trib, pag_ext_suj_ret_nor_leg,
                    tipo_ambiente
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                ) RETURNING id";

        $params = [
            (int)   $data['id_empresa'],
            (int)   $data['id_proveedor'],
            !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            !empty($data['id_sustento_tributario']) ? (int)$data['id_sustento_tributario'] : null,
            $data['tipo_comprobante'] ?? '01',
            $data['tipo_id_proveedor'] ?? null,
            !empty($data['parte_relacionada']) ? 'true' : 'false',
            $data['establecimiento_prov'] ?? null,
            $data['punto_emision_prov'] ?? null,
            $data['secuencial_prov'] ?? null,
            $data['numero_autorizacion'] ?? null,
            $data['fecha_emision'],
            $data['fecha_registro'] ?? date('Y-m-d'),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            (float) ($data['total_ice'] ?? 0),
            (float) ($data['propina'] ?? 0),
            $data['autorizacion_desde'] ?? null,
            $data['autorizacion_hasta'] ?? null,
            !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
            $data['tipo_registro'] ?? 'fisica',
            $data['deducible'] ?? 'declaracion_iva',
            $data['documento_modificado'] ?? null,
            $data['motivo'] ?? null,
            $data['observaciones'] ?? null,
            // El DEFAULT de la columna es 'borrador', un estado que Compras nunca
            // usó: el estado real lo decide el Service (registrado, o pendiente
            // de aprobación si la empresa exige aprobar las compras).
            $data['estado'] ?? 'registrado',
            (int)   $data['id_usuario'], // created_by
            (int)   $data['id_usuario'], // updated_by
            (int)   $data['id_usuario'], // id_usuario
            ...array_values($this->paramsPagoExterior($data)),
            (int)   $data['id_empresa'], // → subconsulta tipo_ambiente
        ];

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE — CABECERA
    // ─────────────────────────────────────────────────────────────────────────

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE compras_cabecera SET
                    id_proveedor            = ?,
                    id_establecimiento      = ?,
                    id_sustento_tributario  = ?,
                    tipo_comprobante        = ?,
                    tipo_id_proveedor       = ?,
                    parte_relacionada       = ?,
                    establecimiento_prov    = ?,
                    punto_emision_prov      = ?,
                    secuencial_prov         = ?,
                    numero_autorizacion     = ?,
                    fecha_emision           = ?,
                    fecha_registro          = ?,
                    total_sin_impuestos     = ?,
                    total_descuento         = ?,
                    importe_total           = ?,
                    total_ice               = ?,
                    propina                 = ?,
                    autorizacion_desde      = ?,
                    autorizacion_hasta      = ?,
                    fecha_caducidad         = ?,
                    tipo_registro           = ?,
                    deducible               = ?,
                    documento_modificado    = ?,
                    motivo                  = ?,
                    observaciones           = ?,
                    pago_loc_ext            = ?,
                    cod_pais_pago           = ?,
                    aplic_conv_dob_trib     = ?,
                    pag_ext_suj_ret_nor_leg = ?,
                    updated_by              = ?,
                    updated_at              = NOW()
                WHERE id = ? AND id_empresa = ? AND eliminado = false";

        $params = [
            (int)   $data['id_proveedor'],
            !empty($data['id_establecimiento']) ? (int)$data['id_establecimiento'] : null,
            !empty($data['id_sustento_tributario']) ? (int)$data['id_sustento_tributario'] : null,
            $data['tipo_comprobante'] ?? '01',
            $data['tipo_id_proveedor'] ?? null,
            !empty($data['parte_relacionada']) ? 'true' : 'false',
            $data['establecimiento_prov'] ?? null,
            $data['punto_emision_prov'] ?? null,
            $data['secuencial_prov'] ?? null,
            $data['numero_autorizacion'] ?? null,
            $data['fecha_emision'],
            $data['fecha_registro'] ?? date('Y-m-d'),
            (float) ($data['total_sin_impuestos'] ?? 0),
            (float) ($data['total_descuento'] ?? 0),
            (float) ($data['importe_total'] ?? 0),
            (float) ($data['total_ice'] ?? 0),
            (float) ($data['propina'] ?? 0),
            $data['autorizacion_desde'] ?? null,
            $data['autorizacion_hasta'] ?? null,
            !empty($data['fecha_caducidad']) ? $data['fecha_caducidad'] : null,
            $data['tipo_registro'] ?? 'fisica',
            $data['deducible'] ?? 'declaracion_iva',
            $data['documento_modificado'] ?? null,
            $data['motivo'] ?? null,
            $data['observaciones'] ?? null,
            ...array_values($this->paramsPagoExterior($data)),
            (int)   $data['id_usuario'],
            $id,
            (int)   $data['id_empresa'],
        ];

        $this->query($sql, $params);
    }

    /**
     * Bloque <pagoExterior> del ATS, en el orden en que lo esperan el INSERT y
     * el UPDATE de la cabecera.
     *
     * Cuando el pago es local ('01') los otros tres campos se guardan en NULL:
     * el ATS los reporta como "NA" y dejarlos con un valor sobrante confundiría
     * al leerlos. Un `$data` sin estas claves (cargas automáticas desde el XML
     * del SRI, que nunca son pagos al exterior) cae en pago local.
     *
     * @return array{pago_loc_ext:string, cod_pais_pago:?string,
     *               aplic_conv_dob_trib:?string, pag_ext_suj_ret_nor_leg:?string}
     */
    private function paramsPagoExterior(array $data): array
    {
        $esExterior = ($data['pago_loc_ext'] ?? '01') === '02';

        return [
            'pago_loc_ext'            => $esExterior ? '02' : '01',
            'cod_pais_pago'           => $esExterior ? (($data['cod_pais_pago'] ?? '') ?: null) : null,
            'aplic_conv_dob_trib'     => $esExterior ? (($data['aplic_conv_dob_trib'] ?? '') ?: null) : null,
            'pag_ext_suj_ret_nor_leg' => $esExterior ? (($data['pag_ext_suj_ret_nor_leg'] ?? '') ?: null) : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS / DELETES — DETALLE
    // ─────────────────────────────────────────────────────────────────────────

    public function insertDetalle(array $data): int
    {
        $sql = "INSERT INTO compras_detalle (
                    id_compra, id_producto, codigo_principal, codigo_auxiliar,
                    descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                ) VALUES (?,?,?,?,?,?,?,?,?) RETURNING id";

        return (int) $this->query($sql, [
            (int)   $data['id_compra'],
            !empty($data['id_producto']) ? (int)$data['id_producto'] : null,
            $data['codigo_principal'] ?? '',
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'] ?? '',
            (float) ($data['cantidad'] ?? 1),
            (float) ($data['precio_unitario'] ?? 0),
            (float) ($data['descuento'] ?? 0),
            (float) ($data['precio_total_sin_impuesto'] ?? 0),
        ])->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO compras_detalle_impuestos (
                    id_compra_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?,?,?,?,?,?)";
        $this->query($sql, [
            (int)   $data['id_compra_detalle'],
            $data['codigo_impuesto'],
            $data['codigo_porcentaje'],
            (float) $data['tarifa'],
            (float) $data['base_imponible'],
            (float) $data['valor'],
        ]);
    }

    public function deleteDetalles(int $idCompra): void
    {
        // Primero eliminar impuestos (FK en cascada lo haría, pero lo hacemos explícito)
        $ids = $this->query(
            "SELECT id FROM compras_detalle WHERE id_compra = ?",
            [$idCompra]
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $this->query("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle IN ($ph)", $ids);
        }
        $this->query("DELETE FROM compras_detalle WHERE id_compra = ?", [$idCompra]);
    }

    /**
     * Actualiza una línea de detalle EN SU SITIO (mismo id), a diferencia de
     * deleteDetalles()+insertDetalle(). Necesario para no romper el vínculo con
     * inventario_kardex.referencia_id, que apunta a este id — ver sincronizarDetalles()
     * en ComprasService.
     */
    public function updateDetalle(array $data): void
    {
        $sql = "UPDATE compras_detalle SET
                    id_producto = ?, codigo_principal = ?, codigo_auxiliar = ?,
                    descripcion = ?, cantidad = ?, precio_unitario = ?, descuento = ?,
                    precio_total_sin_impuesto = ?
                WHERE id = ?";
        $this->query($sql, [
            !empty($data['id_producto']) ? (int)$data['id_producto'] : null,
            $data['codigo_principal'] ?? '',
            $data['codigo_auxiliar'] ?? null,
            $data['descripcion'] ?? '',
            (float) ($data['cantidad'] ?? 1),
            (float) ($data['precio_unitario'] ?? 0),
            (float) ($data['descuento'] ?? 0),
            (float) ($data['precio_total_sin_impuesto'] ?? 0),
            (int)   $data['id'],
        ]);
    }

    public function deleteImpuestosDeDetalle(int $idDetalle): void
    {
        $this->query("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle = ?", [$idDetalle]);
    }

    /** Elimina líneas puntuales (y sus impuestos) por id — para las que el usuario quitó al editar. */
    public function deleteDetallesPorId(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return;

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->query("DELETE FROM compras_detalle_impuestos WHERE id_compra_detalle IN ($ph)", $ids);
        $this->query("DELETE FROM compras_detalle WHERE id IN ($ph)", $ids);
    }

    /** [id_compra_detalle => cantidad ya enviada a inventario (viva)] para una compra. */
    public function getCantidadProcesadaPorDetalle(int $idCompra): array
    {
        $sql = "SELECT k.referencia_id AS id_detalle, COALESCE(SUM(k.cantidad), 0) AS cantidad
                FROM inventario_kardex k
                JOIN compras_detalle d ON d.id = k.referencia_id
                WHERE k.referencia_tipo = 'compra' AND k.eliminado = false AND d.id_compra = ?
                GROUP BY k.referencia_id";
        $rows = $this->query($sql, [$idCompra])->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id_detalle']] = (float) $r['cantidad'];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS / DELETES — PAGOS
    // ─────────────────────────────────────────────────────────────────────────

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO compras_pagos (id_compra, forma_pago, total, plazo, unidad_tiempo) VALUES (?,?,?,?,?)";
        $this->query($sql, [
            (int)   $data['id_compra'],
            (string)($data['forma_pago'] ?? '01'), // Mantener como string para SRI (ej: "01")
            (float) ($data['total'] ?? 0),
            (int)   ($data['plazo'] ?? 0),
            $data['unidad_tiempo'] ?? 'dias',
        ]);
    }

    public function deletePagos(int $idCompra): void
    {
        $this->query("DELETE FROM compras_pagos WHERE id_compra = ?", [$idCompra]);
    }

    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO compras_adicional (id_compra, nombre, valor) VALUES (?, ?, ?)";
        $this->query($sql, [
            (int)   $data['id_compra'],
            $data['nombre'],
            $data['valor'],
        ]);
    }

    public function deleteInfoAdicional(int $idCompra): void
    {
        $this->query("DELETE FROM compras_adicional WHERE id_compra = ?", [$idCompra]);
    }

    /**
     * Valores recaudados por cuenta de terceros (contribución bomberos, tasa de basura…)
     * declarados en la info adicional de las planillas de servicios básicos.
     * NO forman parte de importe_total —que es el valor declarado al SRI— pero sí del
     * saldo por pagar. El desglose vive en compras_adicional; aquí solo el total.
     */
    /**
     * Escribe SOLO las observaciones de la cabecera. Lo usa la carga desde el SRI
     * para dejar constancia de una inconsistencia del XML (p. ej. IVA de cabecera
     * distinto al del detalle) sin tocar el resto del documento.
     */
    public function updateObservaciones(int $idCompra, string $observaciones): void
    {
        $this->query(
            "UPDATE compras_cabecera SET observaciones = ? WHERE id = ?",
            [$observaciones, $idCompra]
        );
    }

    public function updateTotalTerceros(int $idCompra, float $total): void
    {
        $this->query(
            "UPDATE compras_cabecera SET total_terceros = ? WHERE id = ?",
            [round($total, 2), $idCompra]
        );
    }

    /**
     * Actualiza SOLO el Sustento Tributario de la cabecera (sin tocar el resto del
     * documento). Usado por ComprasService::actualizarSustentoTributario() para poder
     * corregir esta clasificación en compras migradas, que por lo demás son de solo
     * lectura (ver esMigrado()) — las migradas llegan sin este dato bien clasificado.
     */
    public function updateSustentoTributario(int $idCompra, int $idSustento, int $idUsuario): void
    {
        $this->query(
            "UPDATE compras_cabecera SET id_sustento_tributario = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
            [$idSustento, $idUsuario, $idCompra]
        );
    }

    /**
     * Total recaudado por cuenta de terceros de una compra (ver updateTotalTerceros).
     * Lo consulta el registro automático desde el SRI para pagar el valor real de la
     * planilla y no solo el importe declarado.
     */
    public function getTotalTerceros(int $idCompra): float
    {
        $row = $this->query(
            "SELECT COALESCE(total_terceros, 0) FROM compras_cabecera WHERE id = ?",
            [$idCompra]
        )->fetchColumn();

        return $row === false ? 0.0 : (float) $row;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS — FACTURA DE REEMBOLSO RECIBIDA
    // ─────────────────────────────────────────────────────────────────────────

    /** Totales agregados del bloque &lt;reembolsos&gt; (codDocReembolso=41), en la propia cabecera. */
    public function updateReembolsoTotales(int $idCompra, array $t): void
    {
        $sql = "UPDATE compras_cabecera
                   SET cod_doc_reembolso              = ?,
                       total_comprobantes_reembolso   = ?,
                       total_base_imponible_reembolso = ?,
                       total_impuesto_reembolso        = ?
                 WHERE id = ?";
        $this->query($sql, [
            (string) ($t['cod_doc_reembolso'] ?? '41'),
            (float)  ($t['total_comprobantes_reembolso'] ?? 0),
            (float)  ($t['total_base_imponible_reembolso'] ?? 0),
            (float)  ($t['total_impuesto_reembolso'] ?? 0),
            $idCompra,
        ]);
    }

    public function insertReembolsoTercero(array $data): int
    {
        $sql = "INSERT INTO compras_reembolso_terceros (
                    id_compra, tipo_identificacion_proveedor_reembolso, identificacion_proveedor_reembolso,
                    razon_social_proveedor_reembolso, cod_pais_pago_proveedor_reembolso, tipo_proveedor_reembolso,
                    cod_doc_reembolso, estab_doc_reembolso, pto_emi_doc_reembolso, secuencial_doc_reembolso,
                    fecha_emision_doc_reembolso, numero_autorizacion_doc_reemb, base_imponible_total, impuesto_total
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                RETURNING id";
        return (int) $this->query($sql, [
            (int)    $data['id_compra'],
            (string) $data['tipo_identificacion_proveedor_reembolso'],
            (string) $data['identificacion_proveedor_reembolso'],
            $data['razon_social_proveedor_reembolso'] ?? null,
            $data['cod_pais_pago_proveedor_reembolso'] ?? null,
            (string) ($data['tipo_proveedor_reembolso'] ?? '02'),
            (string) ($data['cod_doc_reembolso'] ?? '01'),
            $data['estab_doc_reembolso'] ?? null,
            $data['pto_emi_doc_reembolso'] ?? null,
            $data['secuencial_doc_reembolso'] ?? null,
            $data['fecha_emision_doc_reembolso'] ?? null,
            $data['numero_autorizacion_doc_reemb'] ?? null,
            (float)  ($data['base_imponible_total'] ?? 0),
            (float)  ($data['impuesto_total'] ?? 0),
        ])->fetchColumn();
    }

    public function insertImpuestoReembolsoTercero(array $data): void
    {
        $sql = "INSERT INTO compras_reembolso_terceros_impuestos
                    (id_compra_tercero, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
                VALUES (?,?,?,?,?,?)";
        $this->query($sql, [
            (int)   $data['id_compra_tercero'],
            (string)$data['codigo_impuesto'],
            (string)$data['codigo_porcentaje'],
            (float) ($data['tarifa'] ?? 0),
            (float) ($data['base_imponible'] ?? 0),
            (float) ($data['valor'] ?? 0),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSERTS / DELETES — RETENCIONES
    // ─────────────────────────────────────────────────────────────────────────





    // ─────────────────────────────────────────────────────────────────────────
    // ESTADO / ELIMINACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $this->query(
            "UPDATE compras_cabecera SET estado = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
            [$estado, $idUsuario, $id]
        );
    }

    // ─── Aprobación de compras (checkpoint 'aprobacion_compras') ───────────────

    /** Guarda el token con el que el aprobador entra desde el enlace del correo. */
    public function setTokenAprobacion(int $id, string $token): void
    {
        $this->query(
            "UPDATE compras_cabecera SET token_aprobacion = ?, updated_at = NOW() WHERE id = ?",
            [$token, $id]
        );
    }

    /**
     * Resuelve la compra a partir del token del correo. Es un flujo PÚBLICO (sin
     * sesión), así que valida aquí mismo que la empresa dueña siga activa: si no
     * lo está, el token se comporta como si no existiera (CLAUDE.md §6).
     */
    public function getPorTokenAprobacion(string $token): ?array
    {
        $rows = $this->query(
            "SELECT c.*,
                    p.razon_social   AS proveedor_nombre,
                    p.identificacion AS proveedor_ruc,
                    u.nombre         AS creado_por_nombre,
                    e.nombre         AS empresa_nombre
               FROM compras_cabecera c
               INNER JOIN empresas e     ON e.id = c.id_empresa
               INNER JOIN proveedores p  ON p.id = c.id_proveedor
               LEFT  JOIN usuarios u     ON u.id = c.created_by
              WHERE c.token_aprobacion = ?
                AND c.eliminado = false
                AND e.estado = '1'
                AND e.eliminado = false
              LIMIT 1",
            [$token]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $rows[0] ?? null;
    }

    /**
     * Cierra el flujo de aprobación. El token se limpia siempre: un enlace de
     * correo ya usado no debe volver a resolver a nada.
     *
     * Solo cambia una compra que SIGUE pendiente, en el mismo UPDATE: si dos
     * aprobadores deciden a la vez, uno solo la resuelve (y genera el pago
     * automático); el otro recibe false.
     *
     * @return bool false si la compra ya no estaba pendiente.
     */
    public function resolverAprobacion(int $id, string $estado, int $idUsuario, ?string $motivo = null): bool
    {
        return $this->query(
            "UPDATE compras_cabecera
                SET estado = ?, aprobado_by = ?, aprobado_at = NOW(),
                    motivo_rechazo = ?, token_aprobacion = NULL,
                    updated_by = ?, updated_at = NOW()
              WHERE id = ? AND estado = 'pendiente_aprobacion'",
            [$estado, $idUsuario, $motivo, $idUsuario, $id]
        )->rowCount() > 0;
    }

    /** Nombre y correo de los usuarios aprobadores (para notificar y mostrar). */
    public function getNombresUsuarios(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];

        $ph = implode(',', array_fill(0, count($ids), '?'));
        return $this->query(
            "SELECT id, nombre, mail FROM usuarios WHERE id IN ($ph) ORDER BY nombre ASC",
            $ids
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Cuántas compras esperan aprobación en la empresa (badge del listado). */
    public function contarPendientesAprobacion(int $idEmpresa): int
    {
        $rows = $this->query(
            "SELECT COUNT(*) AS n FROM compras_cabecera
              WHERE id_empresa = ? AND estado = 'pendiente_aprobacion' AND eliminado = false",
            [$idEmpresa]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return (int) ($rows[0]['n'] ?? 0);
    }

    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $this->query(
            "UPDATE compras_cabecera
             SET eliminado = true, deleted_at = NOW(), deleted_by = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?",
            [$idUsuario, $idUsuario, $id]
        );
    }

    public function getEgresosAsociados(int $idCompra, int $idEmpresa): array
    {
        $sql = "SELECT ec.id 
                FROM egresos_cabecera ec
                INNER JOIN egresos_detalle ed ON ec.id = ed.id_egreso
                WHERE ed.tipo_documento = 'COMPRA'
                  AND ed.id_referencia_documento = ?
                  AND ed.eliminado = FALSE
                  AND ec.id_empresa = ?
                  AND ec.eliminado = FALSE
                  AND ec.estado != 'anulado'";
        
        $st = $this->query($sql, [$idCompra, $idEmpresa]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    public function getEgresosVinculados(int $idCompra): array
    {
        $sql = "SELECT ed.monto_pagado, ec.id AS id_egreso, ec.numero_egreso, ec.fecha_emision, ec.estado, 
                       COALESCE(eoe.nombre, 'Sin Concepto') AS concepto_nombre,
                       (SELECT string_agg(fp.nombre, ', ') 
                        FROM egresos_pagos ep 
                        JOIN empresa_formas_pago fp ON ep.id_forma_pago = fp.id 
                        WHERE ep.id_egreso = ec.id AND ep.eliminado = FALSE) AS formas_pago
                FROM egresos_detalle ed
                JOIN egresos_cabecera ec ON ed.id_egreso = ec.id
                LEFT JOIN empresa_opciones_ingreso_egreso eoe ON ec.id_egreso_concepto = eoe.id
                WHERE ed.tipo_documento = 'COMPRA' 
                  AND ed.id_referencia_documento = ? 
                  AND ed.eliminado = FALSE 
                  AND ec.eliminado = FALSE
                ORDER BY ec.fecha_emision DESC";
        return $this->query($sql, [$idCompra])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function existeSecuencial(
        int $idEmpresa,
        int $idProveedor,
        string $estab,
        string $pto,
        string $sec,
        string $tipoComprobante,
        ?int $excluirId = null
    ): bool {
        $sql = "SELECT COUNT(*) FROM compras_cabecera
                WHERE id_empresa = ? AND id_proveedor = ?
                  AND establecimiento_prov = ? AND punto_emision_prov = ?
                  AND secuencial_prov = ? AND tipo_comprobante = ?
                  AND eliminado = FALSE";
        $params = [$idEmpresa, $idProveedor, $estab, $pto, $sec, $tipoComprobante];
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function existeNumeroAutorizacion(
        int $idEmpresa,
        string $numeroAutorizacion,
        ?int $excluirId = null
    ): bool {
        $sql = "SELECT COUNT(*) FROM compras_cabecera
                WHERE id_empresa = ? AND numero_autorizacion = ?
                  AND eliminado = FALSE";
        $params = [$idEmpresa, $numeroAutorizacion];
        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CATÁLOGOS
    // ─────────────────────────────────────────────────────────────────────────

    public function getFormasPago(): array
    {
        return $this->db->query(
            "SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query(
            "SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSustentosTributarios(): array
    {
        return $this->db->query(
            "SELECT * FROM sustento_tributario WHERE status = 1 ORDER BY codigo ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRetencionesDisponibles(string $tipoImpuesto = '', string $buscar = ''): array
    {
        $where = "WHERE status = 1";
        $params = [];
        if ($tipoImpuesto !== '') {
            $where .= " AND impuesto_ret = ?";
            $params[] = strtoupper($tipoImpuesto);
        }
        if ($buscar !== '') {
            $where .= " AND (codigo_ret ILIKE ? OR concepto_ret ILIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
        }
        // Intentar con ambos posibles nombres de PK
        try {
            return $this->query(
                "SELECT id AS id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret
                 FROM retenciones_sri $where ORDER BY codigo_ret ASC",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return $this->query(
                "SELECT id_ret AS id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret
                 FROM retenciones_sri $where ORDER BY codigo_ret ASC",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
}
