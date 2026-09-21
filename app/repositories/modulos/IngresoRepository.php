<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AbonosVentaSql;
use App\repositories\BaseRepository;
use PDO;

class IngresoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ingresos_cabecera');
    }

    /** Series REALMENTE usadas en ingresos guardados (para el filtro "Serie" del buscador). */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM ingresos_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /**
     * Búsqueda libre DENTRO de los ingresos (pestaña "Detalles" del modal de filtros):
     * devuelve cada línea de documento cobrado y cada forma de cobro que coincide con
     * el texto, junto con el ingreso al que pertenece. Mismo alcance que el listado
     * (empresa, no eliminados, ambiente, registros propios si aplica).
     *
     * @return array<int, array{origen:string, tipo:?string, referencia:?string, descripcion:?string, monto:?string,
     *                          id_ingreso:int, numero_ingreso:?string, fecha_emision:?string, estado:?string, tercero:?string}>
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "i.id_empresa = :id_empresa AND i.eliminado = false
                      AND i.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND i.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.numero_documento', 'd.descripcion', 'd.tipo_documento', 'd.monto_documento::text', 'd.monto_cobrado::text', 'pc.codigo', 'pc.nombre'],
            $q, $params, 'dt'
        );
        $condPago = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['fp.nombre', 'p.referencia', 'p.numero_cheque', 'p.observaciones', 'p.tipo_operacion_bancaria', 'p.monto::text', 'p.fecha_cobro::text'],
            $q, $params, 'pg'
        );
        if ($condDet === '' || $condPago === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT i.id, i.numero_ingreso, i.fecha_emision, i.estado,
                           COALESCE(NULLIF(i.recibo_de, ''), c.nombre) AS tercero
                    FROM ingresos_cabecera i
                    LEFT JOIN clientes c ON c.id = i.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'DOCUMENTO' AS origen,
                           d.tipo_documento AS tipo,
                           d.numero_documento AS referencia,
                           d.descripcion,
                           d.monto_cobrado AS monto,
                           b.id AS id_ingreso, b.numero_ingreso, b.fecha_emision, b.estado, b.tercero
                    FROM ingresos_detalle d
                    JOIN base b ON b.id = d.id_ingreso
                    LEFT JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
                    WHERE $condDet
                    UNION ALL
                    SELECT 'PAGO' AS origen,
                           fp.nombre AS tipo,
                           NULLIF(CONCAT_WS(' ', p.tipo_operacion_bancaria, p.referencia, p.numero_cheque), '') AS referencia,
                           p.observaciones AS descripcion,
                           p.monto,
                           b.id AS id_ingreso, b.numero_ingreso, b.fecha_emision, b.estado, b.tercero
                    FROM ingresos_pagos p
                    JOIN base b ON b.id = p.id_ingreso
                    LEFT JOIN empresa_formas_pago fp ON fp.id = p.id_forma_cobro
                    WHERE $condPago
                ) x
                ORDER BY x.fecha_emision DESC, x.id_ingreso DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Usuarios que han registrado algún ingreso en la empresa (filtro "Usuario" del modal de filtros). */
    public function getUsuariosConIngresos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM ingresos_cabecera i
                JOIN usuarios u ON u.id = i.id_usuario
                WHERE i.id_empresa = :id_empresa AND i.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL con la que se ordena. Es la whitelist del ORDER BY y el mapa que
     * necesita `OrdenListado::clausula()` para encadenar varias columnas.
     */
    public const MAPA_ORDEN = [
        'id'             => 'i.id',
        'fecha_emision'  => 'i.fecha_emision',
        'numero_ingreso' => 'i.numero_ingreso',
        'tipo_ingreso'   => 'i.tipo_ingreso',
        'monto_total'    => 'i.monto_total',
        'estado'         => 'i.estado',
        'observaciones'  => 'i.observaciones',
        // De quién se recibe: el texto libre manda sobre el nombre del cliente, igual
        // que en la columna del listado (un ingreso puede no tener cliente asociado).
        'cliente_nombre' => "COALESCE(i.recibo_de, c.nombre, '—')",
        'recibo_de'      => "COALESCE(i.recibo_de, c.nombre, '—')",
    ];

    /**
     * @param array $ordenMulti Criterios de orden [['col'=>…,'dir'=>…], …] cuando el
     *        llamador usa `OrdenListado`. Vacío = se ordena por $ordenCol/$ordenDir.
     */
    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null, array $ordenMulti = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE i.id_empresa = :id_empresa AND i.eliminado = false AND i.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: SOLO lo que se VE en el listado — nº de ingreso (con su serie y
            // secuencial), "Recibo de" (el texto libre, el cliente o el concepto, que es lo
            // que muestra esa columna), observaciones, fecha y monto.
            //
            // Qué NO entra, por decisión del usuario, y dónde se busca en su lugar (mismo
            // criterio que el resto de módulos, 17-09-2026):
            //   - Tipo (de ingreso, de los documentos cobrados y nombre del concepto) y
            //     Estado → modal de filtros (decisión anterior).
            //   - Identificación del cliente → filtro `ruc:` / `identificacion:`.
            //   - Usuario que registró → filtro `usuario:`.
            //   - N° de los documentos cobrados → pestaña "Detalles" del modal de filtros
            //     (buscarEnDetalles()), que SÍ dice qué documento o qué pago coincidió; desde
            //     el listado el ingreso aparecía sin que se viera el motivo. De paso se va la
            //     subconsulta STRING_AGG, que corría por cada ingreso de la empresa.
            // Rendimiento: monto y fecha solo se comparan si la palabra tiene dígitos
            // (ver FiltrosBusqueda::condicionTexto). La subconsulta del detalle va al final.
            $digitos = \App\Helpers\FiltrosBusqueda::SI_DIGITOS;
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'i.numero_ingreso',                                   // Nº Ingreso (tal como se guardó)
                    \App\Helpers\SecuencialFormato::sqlNumeroCompleto('i.establecimiento', 'i.punto_emision', 'i.secuencial'), // y en formato canónico
                    'i.secuencial',
                    "CONCAT(i.establecimiento,'-',i.punto_emision)",      // Serie
                    // Columna "Recibo de": muestra el texto libre, o el cliente, o el concepto.
                    'i.recibo_de',
                    'c.nombre',
                    'rc.nombre',
                    'i.observaciones',                                    // Observaciones
                    ['sql' => 'i.fecha_emision', 'si' => $digitos],       // Fecha
                    ['sql' => 'i.monto_total', 'si' => $digitos],         // Monto
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (public/js/components/filtros_modal.js, en la
        // vista). Las claves se conservan aunque cambie la UI porque también viajan
        // en los enlaces de PDF/Excel.
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'recibo_de'      => 'i.recibo_de',
                'beneficiario'   => 'i.recibo_de',
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => 'i.numero_ingreso',
                'nro'            => 'i.numero_ingreso',
                'concepto'       => 'i.observaciones',
                'obs'            => 'i.observaciones',
                'observaciones'  => 'i.observaciones',
            ],
            'exacto'   => [
                'estado'      => 'i.estado',
                'tipo'        => 'i.tipo_ingreso',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del buscador.
                'serie'       => "CONCAT(i.establecimiento,'-',i.punto_emision)",
                'id_concepto' => 'i.id_ingreso_concepto',
                'usuario'     => 'i.id_usuario',
                // asiento:si / asiento:no
                'asiento'     => "CASE WHEN i.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
            ],
            'fecha'    => [
                'fecha'         => 'i.fecha_emision',
                'fecha_emision' => 'i.fecha_emision',
                'registro'      => 'i.created_at',
            ],
            'numerico' => [
                'monto' => 'i.monto_total',
                'total' => 'i.monto_total',
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'i.secuencial::numeric',
            ],
            // Lo que vive en las tablas hijas (formas de cobro y documentos cobrados):
            // el ingreso entra si ALGÚN pago/detalle cumple la condición.
            'existe'   => [
                'forma_cobro'    => ['tipo' => 'exacto', 'col' => 'p.id_forma_cobro',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})'],
                'tipo_operacion' => ['tipo' => 'exacto', 'col' => 'p.tipo_operacion_bancaria',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})'],
                'referencia'     => ['tipo' => 'texto',  'col' => "CONCAT(COALESCE(p.referencia,''),' ',COALESCE(p.numero_cheque,''))",
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})'],
                'fecha_cobro'    => ['tipo' => 'fecha',  'col' => 'p.fecha_cobro',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})'],
                'pago_monto'     => ['tipo' => 'numerico', 'col' => 'p.monto',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})'],
                'pago_obs'       => ['tipo' => 'texto',  'col' => 'p.observaciones',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_pagos p WHERE p.id_ingreso = i.id AND {cond})'],
                'documento'      => ['tipo' => 'texto',  'col' => 'd.numero_documento',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d WHERE d.id_ingreso = i.id AND {cond})'],
                'tipo_doc'       => ['tipo' => 'exacto', 'col' => 'd.tipo_documento',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d WHERE d.id_ingreso = i.id AND {cond})'],
                'det_descripcion'=> ['tipo' => 'texto',  'col' => 'd.descripcion',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d WHERE d.id_ingreso = i.id AND {cond})'],
                'det_cuenta'     => ['tipo' => 'texto',  'col' => "CONCAT(COALESCE(pc.codigo,''),' ',COALESCE(pc.nombre,''))",
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d LEFT JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable WHERE d.id_ingreso = i.id AND {cond})'],
                'det_monto_doc'  => ['tipo' => 'numerico', 'col' => 'd.monto_documento',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d WHERE d.id_ingreso = i.id AND {cond})'],
                'det_monto_cobrado' => ['tipo' => 'numerico', 'col' => 'd.monto_cobrado',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d WHERE d.id_ingreso = i.id AND {cond})'],
                'det_saldo'      => ['tipo' => 'numerico', 'col' => 'd.saldo_actual',
                                     'sql' => 'EXISTS (SELECT 1 FROM ingresos_detalle d WHERE d.id_ingreso = i.id AND {cond})'],
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND i.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Una o varias columnas (Shift+clic en el listado), siempre validadas contra
        // MAPA_ORDEN, con i.id como desempate para que las filas empatadas no bailen
        // entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula(
            $ordenMulti,
            self::MAPA_ORDEN,
            'i.fecha_emision',
            'i.id DESC'
        );

        // Rendimiento (2026-09-16): conteo + página en UNA consulta, y los tipos del
        // detalle (subconsulta por fila) calculados solo para las filas de la página.
        // Ver App\Helpers\ListadoPaginado.
        return \App\Helpers\ListadoPaginado::consultar(
            fn(string $sql, array $p) => $this->query($sql, $p)->fetchAll(PDO::FETCH_ASSOC),
            [
                'tabla'       => 'ingresos_cabecera',
                'alias'       => 'i',
                'joinsFiltro' => self::LISTADO_JOINS,
                'joinsFinal'  => self::LISTADO_JOINS,
                'where'       => $where,
                'orderBy'     => $orderBy,
                'perPage'     => $perPage,
                'conBusqueda' => trim($buscar) !== '',   // sin buscar: forma liviana (ids por índice + COUNT aparte)
                'offset'      => $offset,
                'select'      => self::LISTADO_SELECT,
            ],
            $params
        );
    }

    /**
     * Joins y columnas de cada fila del listado. Los comparten getListado() y
     * getFilaListado(): la fila del ingreso recién guardado debe salir igual que en el
     * listado, y escribirlas dos veces dejaría una de las dos desfasada.
     */
    private const LISTADO_JOINS = "LEFT JOIN clientes c  ON i.id_cliente        = c.id
                LEFT JOIN clientes rc ON i.id_recibo_cliente  = rc.id
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso eic ON i.id_ingreso_concepto = eic.id";

    private const LISTADO_SELECT = "i.*,
                       c.nombre AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       rc.nombre AS recibo_cliente_nombre,
                       u.nombre AS usuario_nombre,
                       eic.nombre AS concepto_nombre,
                       (SELECT STRING_AGG(t, ',') FROM (
                           SELECT DISTINCT idt.tipo_documento AS t
                           FROM ingresos_detalle idt
                           WHERE idt.id_ingreso = i.id
                           ORDER BY t
                       ) sub) AS tipos_detalle";

    /**
     * Una fila del listado (mismas columnas que getListado()) para el ingreso recién
     * guardado: la vista la usa para actualizar ese registro en el listado sin perder la
     * página, el orden ni los filtros. Mismo alcance que el listado (empresa, no eliminado,
     * ambiente y registros propios): null si el ingreso no se vería en él.
     */
    public function getFilaListado(int $id, int $idEmpresa, ?int $idUsuario = null): ?array
    {
        $params = [':id' => $id, ':id_empresa' => $idEmpresa];
        $where  = "WHERE i.id = :id AND i.id_empresa = :id_empresa AND i.eliminado = false
                     AND i.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $where .= " AND i.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $sql = "SELECT " . self::LISTADO_SELECT . "
                FROM ingresos_cabecera i
                " . self::LISTADO_JOINS . "
                $where";

        $fila = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Ingresos del rango de fechas o de números para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE i.id_empresa = :id_empresa AND i.eliminado = false
                   " . $this->condicionRangoDescargaMasiva('i.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params);
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND i.id_usuario = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT i.id, i.establecimiento, i.punto_emision, i.secuencial, i.fecha_emision, i.estado
                FROM ingresos_cabecera i
                $where
                ORDER BY i.fecha_emision ASC, i.id ASC";
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT i.*,
                       c.nombre AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.email AS cliente_email,
                       rc.nombre AS recibo_cliente_nombre,
                       rc.email AS recibo_cliente_email,
                       u.nombre AS usuario_nombre,
                       eic.nombre AS concepto_nombre,
                       est.nombre AS establecimiento_nombre,
                       pto.nombre AS punto_emision_nombre
                FROM ingresos_cabecera i
                LEFT JOIN clientes c  ON i.id_cliente        = c.id
                LEFT JOIN clientes rc ON i.id_recibo_cliente  = rc.id
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso eic ON i.id_ingreso_concepto = eic.id
                LEFT JOIN empresa_establecimiento est ON i.id_establecimiento = est.id
                LEFT JOIN empresa_punto_emision pto ON i.id_punto_emision = pto.id
                WHERE i.id = :id AND i.id_empresa = :id_empresa AND i.eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idIngreso): array
    {
        $sql = "SELECT d.*,
                       COALESCE(cv.id, cr.id, cs.id, cfr.id, s.id_cliente)                 AS id_cliente,
                       COALESCE(cv.nombre, cr.nombre, cs.nombre, cfr.nombre, s.nombre_cliente)  AS cliente_nombre,
                       COALESCE(v.fecha_emision, rv.fecha_emision, fr.fecha_emision, s.fecha_emision) AS fecha_documento,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM ingresos_detalle d
                LEFT JOIN ventas_cabecera v        ON d.id_referencia_documento = v.id  AND d.tipo_documento = 'FACTURA'
                LEFT JOIN clientes cv              ON v.id_cliente = cv.id
                LEFT JOIN recibos_venta_cabecera rv ON d.id_referencia_documento = rv.id AND d.tipo_documento = 'RECIBO'
                LEFT JOIN clientes cr              ON rv.id_cliente = cr.id
                LEFT JOIN factura_reembolso_cabecera fr ON d.id_referencia_documento = fr.id AND d.tipo_documento = 'FACTURA_REEMBOLSO'
                LEFT JOIN clientes cfr             ON fr.id_cliente = cfr.id
                LEFT JOIN saldos_iniciales_cxc s   ON d.id_referencia_documento = s.id  AND d.tipo_documento = 'SALDO_INICIAL'
                LEFT JOIN clientes cs              ON s.id_cliente = cs.id
                LEFT JOIN plan_cuentas pc          ON pc.id = d.id_cuenta_contable
                WHERE d.id_ingreso = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * IDs de saldos iniciales CXC (saldos_iniciales_cxc.id) cobrados por un ingreso,
     * para recalcular su saldo pendiente al crear/editar/anular/eliminar el ingreso.
     *
     * @return int[]
     */
    public function getSaldosInicialesReferenciados(int $idIngreso): array
    {
        $sql = "SELECT DISTINCT id_referencia_documento
                FROM ingresos_detalle
                WHERE id_ingreso = ?
                  AND tipo_documento = 'SALDO_INICIAL'
                  AND id_referencia_documento IS NOT NULL";
        return array_map('intval', $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_COLUMN));
    }

    public function getPagos(int $idIngreso): array
    {
        $sql = "SELECT ip.*, efc.nombre AS forma_cobro_nombre, efc.tipo AS forma_cobro_tipo,
                       be.nombre_banco AS banco_nombre
                FROM ingresos_pagos ip
                INNER JOIN empresa_formas_pago efc ON ip.id_forma_cobro = efc.id
                LEFT JOIN bancos_ecuador be ON be.id = efc.id_banco
                WHERE ip.id_ingreso = ?
                ORDER BY ip.id ASC";
        return $this->query($sql, [$idIngreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conceptos de ingreso activos, con la cuenta VIGENTE del concepto en Ingresos
     * (`cuenta_id` / `cuenta_codigo` / `cuenta_nombre`): manda el asiento programado de la
     * naturaleza ('opcion_ingreso') y la columna del módulo queda de respaldo. Es el mismo
     * COALESCE que usan el asiento (AsientoBuilderService::generarAsientoIngreso), Configuración
     * Contable y el módulo de Opciones; antes aquí se leía solo la columna, así que una cuenta
     * configurada únicamente en Configuración Contable no llegaba al modal. `id_cuenta_contable`
     * (de o.*) sigue siendo esa columna tal cual.
     *
     * LATERAL + LIMIT 1: asientos_programados no tiene índice único por referencia, y una regla
     * duplicada no debe duplicar el botón del concepto.
     */
    public function getConceptosIngreso(int $idEmpresa): array
    {
        $sql = "SELECT o.*,
                       COALESCE(ap.id_cuenta, o.id_cuenta_contable) AS cuenta_id,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN LATERAL (
                    SELECT ap.id_cuenta
                    FROM asientos_programados ap
                    WHERE ap.id_referencia = o.id
                      AND ap.tipo_referencia = 'opcion_ingreso'
                      AND ap.id_empresa = o.id_empresa
                      AND ap.eliminado = false
                    ORDER BY ap.id DESC
                    LIMIT 1
                ) ap ON true
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, o.id_cuenta_contable)
                WHERE o.id_empresa = :id_empresa
                  AND o.aplica_ingresos = TRUE
                  AND UPPER(o.estado) = 'ACTIVO'
                  AND o.eliminado = FALSE
                ORDER BY o.nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFacturasPendientes(int $idCliente, int $idEmpresa, ?int $excluirIngresoId = null): array
    {
        $excluirSql = "";
        $params = [':id_cliente' => $idCliente, ':id_empresa' => $idEmpresa];

        if ($excluirIngresoId !== null) {
            $excluirSql = " AND i.id <> :excluir ";
            $params[':excluir'] = $excluirIngresoId;
        }

        $excluirSqlSi = $excluirIngresoId !== null ? " AND i.id <> :excluir" : '';

        // Nota: Se calcula el saldo dinámicamente restando lo ya cobrado en ingresos activos
        // y las retenciones de venta del cliente. Incluye también los saldos iniciales CXC.
        $sql = "WITH cobrado AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) as total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                cobrado_rec AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) as total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'RECIBO'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                retenido_fact AS (
                    -- Regla compartida (AbonosVentaSql): enlace por id_venta o por
                    -- num_doc_sustento normalizado a 15 dígitos; si una retención
                    -- sustenta varias facturas, cada una recibe lo retenido en sus líneas.
                    " . AbonosVentaSql::cteRetenidoPorFactura(':id_empresa', "AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)") . "
                ),
                cobrado_si AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'SALDO_INICIAL'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSqlSi
                    GROUP BY id_referencia_documento
                ),
                retenido_si AS (
                    SELECT s.id AS id_saldo, SUM(rd.valor_retenido) AS total_retenido
                    FROM saldos_iniciales_cxc s
                    INNER JOIN retencion_venta_cabecera r
                        ON r.eliminado = FALSE AND r.id_empresa = s.id_empresa
                       AND r.id_venta IS NULL AND r.id_cliente = s.id_cliente
                    INNER JOIN retencion_venta_detalle rd
                        ON rd.id_retencion = r.id
                       AND rd.num_doc_sustento IS NOT NULL AND rd.num_doc_sustento <> ''
                       AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                           = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                    WHERE s.id_empresa = :id_empresa AND s.eliminado = FALSE
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = FALSE
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                    GROUP BY s.id
                ),
                nc_aplic AS (
                    -- Notas de crédito de venta, enlazadas a la factura por num_doc_modificado
                    -- (número normalizado; columnas num_norm y total_nc)
                    " . AbonosVentaSql::cteNotasPorFactura('notas_credito_cabecera', 'total_nc', ':id_empresa') . "
                ),
                nd_aplic AS (
                    -- Notas de débito de venta: SUMAN al saldo pendiente (al contrario de la NC).
                    " . AbonosVentaSql::cteNotasPorFactura('nota_debito_cabecera', 'total_nd', ':id_empresa') . "
                )
                SELECT * FROM (
                    SELECT 'FACTURA'::varchar AS tipo_documento,
                           v.id,
                           CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                           v.fecha_emision,
                           v.importe_total,
                           COALESCE(c.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rf.total_retenido, 0) AS monto_retenido,
                           (v.importe_total + COALESCE(ndf.total_nd, 0) - COALESCE(c.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) AS saldo_pendiente
                    FROM ventas_cabecera v
                    LEFT JOIN cobrado c        ON v.id = c.id_referencia_documento
                    LEFT JOIN retenido_fact rf ON v.id = rf.id_venta
                    LEFT JOIN nc_aplic ncf     ON ncf.num_norm = " . AbonosVentaSql::numFactura('v') . "
                    LEFT JOIN nd_aplic ndf     ON ndf.num_norm = " . AbonosVentaSql::numFactura('v') . "
                    WHERE v.id_cliente = :id_cliente
                      AND v.id_empresa = :id_empresa
                      AND v.estado <> 'anulado' -- Vigentes: incluye 'borrador' (aún sin autorizar por el SRI), que igual se puede cobrar
                      AND v.eliminado = FALSE
                      AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (v.importe_total + COALESCE(ndf.total_nd, 0) - COALESCE(c.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) > 0

                    UNION ALL

                    SELECT 'SALDO_INICIAL'::varchar AS tipo_documento,
                           s.id,
                           s.nro_documento AS numero_documento,
                           s.fecha_emision,
                           s.saldo_inicial AS importe_total,
                           COALESCE(csi.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rsi.total_retenido, 0)  AS monto_retenido,
                           (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) AS saldo_pendiente
                    FROM saldos_iniciales_cxc s
                    LEFT JOIN cobrado_si csi  ON s.id = csi.id_referencia_documento
                    LEFT JOIN retenido_si rsi ON s.id = rsi.id_saldo
                    WHERE s.id_cliente = :id_cliente
                      AND s.id_empresa = :id_empresa
                      AND s.eliminado = FALSE
                      AND (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) > 0

                    UNION ALL

                    SELECT 'RECIBO'::varchar AS tipo_documento,
                           r.id,
                           CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) AS numero_documento,
                           r.fecha_emision,
                           r.importe_total,
                           COALESCE(cr.total_cobrado, 0) AS monto_cobrado,
                           0 AS monto_retenido,
                           (r.importe_total - COALESCE(cr.total_cobrado, 0)) AS saldo_pendiente
                    FROM recibos_venta_cabecera r
                    LEFT JOIN cobrado_rec cr ON r.id = cr.id_referencia_documento
                    WHERE r.id_cliente = :id_cliente
                      AND r.id_empresa = :id_empresa
                      AND r.estado NOT IN ('anulado','facturado')
                      AND r.eliminado = FALSE
                      AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (r.importe_total - COALESCE(cr.total_cobrado, 0)) > 0
                ) docs
                ORDER BY fecha_emision ASC, id ASC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Clientes de la empresa que tienen al menos un documento de cuentas por cobrar
     * pendiente (factura autorizada, saldo inicial CXC o recibo de venta).
     *
     * Es el equivalente agrupado de getFacturasPendientes(): misma definición de saldo
     * pendiente (cobros aplicados, retenciones de venta, notas de crédito y débito, y el
     * filtro de tipo_ambiente de la empresa), pero resuelto en UNA sola consulta para toda
     * la empresa en vez de una consulta por cliente. Existe porque la Conciliación de Cobros
     * necesita la lista completa de clientes con cartera abierta al abrir el módulo y al
     * procesar un extracto; recorrer los clientes llamando a getFacturasPendientes() uno por
     * uno colgaba el request (N ejecuciones de una consulta con 7 CTE de agregación).
     *
     * Nota: a diferencia de getFacturasPendientes(), los CTE de cobros sí filtran por
     * id_empresa. Un ingreso solo puede cobrar documentos de su propia empresa, así que el
     * resultado es el mismo y evita agregar ingresos_detalle de todo el sistema.
     */
    public function getClientesConDocumentosPendientes(int $idEmpresa): array
    {
        $sql = "WITH cobrado AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.id_empresa = :id_empresa
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ),
                cobrado_rec AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'RECIBO'
                      AND i.id_empresa = :id_empresa
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ),
                cobrado_si AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'SALDO_INICIAL'
                      AND i.id_empresa = :id_empresa
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ),
                retenido_fact AS (
                    -- Regla compartida (AbonosVentaSql): enlace por id_venta o por
                    -- num_doc_sustento normalizado a 15 dígitos; si una retención
                    -- sustenta varias facturas, cada una recibe lo retenido en sus líneas.
                    " . AbonosVentaSql::cteRetenidoPorFactura(':id_empresa', "AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)") . "
                ),
                retenido_si AS (
                    SELECT s.id AS id_saldo, SUM(rd.valor_retenido) AS total_retenido
                    FROM saldos_iniciales_cxc s
                    INNER JOIN retencion_venta_cabecera r
                        ON r.eliminado = FALSE AND r.id_empresa = s.id_empresa
                       AND r.id_venta IS NULL AND r.id_cliente = s.id_cliente
                    INNER JOIN retencion_venta_detalle rd
                        ON rd.id_retencion = r.id
                       AND rd.num_doc_sustento IS NOT NULL AND rd.num_doc_sustento <> ''
                       AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                           = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                    WHERE s.id_empresa = :id_empresa AND s.eliminado = FALSE
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = FALSE
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                    GROUP BY s.id
                ),
                nc_aplic AS (
                    -- Notas de crédito de venta, enlazadas a la factura por num_doc_modificado
                    -- (número normalizado; columnas num_norm y total_nc)
                    " . AbonosVentaSql::cteNotasPorFactura('notas_credito_cabecera', 'total_nc', ':id_empresa') . "
                ),
                nd_aplic AS (
                    -- Notas de débito de venta: SUMAN al saldo pendiente (al contrario de la NC).
                    " . AbonosVentaSql::cteNotasPorFactura('nota_debito_cabecera', 'total_nd', ':id_empresa') . "
                ),
                clientes_pendientes AS (
                    SELECT v.id_cliente
                    FROM ventas_cabecera v
                    LEFT JOIN cobrado c        ON v.id = c.id_referencia_documento
                    LEFT JOIN retenido_fact rf ON v.id = rf.id_venta
                    LEFT JOIN nc_aplic ncf     ON ncf.num_norm = " . AbonosVentaSql::numFactura('v') . "
                    LEFT JOIN nd_aplic ndf     ON ndf.num_norm = " . AbonosVentaSql::numFactura('v') . "
                    WHERE v.id_empresa = :id_empresa
                      AND v.estado <> 'anulado' -- incluye 'borrador': una factura sin autorizar del SRI igual se cobra
                      AND v.eliminado = FALSE
                      AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (v.importe_total + COALESCE(ndf.total_nd, 0) - COALESCE(c.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) > 0

                    UNION

                    SELECT s.id_cliente
                    FROM saldos_iniciales_cxc s
                    LEFT JOIN cobrado_si csi  ON s.id = csi.id_referencia_documento
                    LEFT JOIN retenido_si rsi ON s.id = rsi.id_saldo
                    WHERE s.id_empresa = :id_empresa
                      AND s.eliminado = FALSE
                      AND (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) > 0

                    UNION

                    SELECT r.id_cliente
                    FROM recibos_venta_cabecera r
                    LEFT JOIN cobrado_rec cr ON r.id = cr.id_referencia_documento
                    WHERE r.id_empresa = :id_empresa
                      AND r.estado NOT IN ('anulado','facturado')
                      AND r.eliminado = FALSE
                      AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (r.importe_total - COALESCE(cr.total_cobrado, 0)) > 0
                )
                SELECT cli.id, cli.nombre, cli.identificacion
                FROM clientes cli
                WHERE cli.id_empresa = :id_empresa
                  AND cli.eliminado = FALSE
                  AND cli.status = 1
                  AND cli.id IN (SELECT id_cliente FROM clientes_pendientes WHERE id_cliente IS NOT NULL)
                ORDER BY cli.nombre ASC";

        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO ingresos_cabecera (
                    id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario,
                    fecha_emision, establecimiento, punto_emision, secuencial, numero_ingreso,
                    tipo_ingreso, id_ingreso_concepto, monto_total, observaciones, estado,
                    recibo_de, id_recibo_cliente, tipo_ambiente,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :id_empresa, :id_establecimiento, :id_punto_emision, :id_cliente, :id_usuario,
                    :fecha_emision, :establecimiento, :punto_emision, :secuencial, :numero_ingreso,
                    :tipo_ingreso, :id_ingreso_concepto, :monto_total, :observaciones, :estado,
                    :recibo_de, :id_recibo_cliente,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'           => (int) $data['id_empresa'],
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':id_cliente'           => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            ':id_usuario'           => (int) $data['id_usuario'],
            ':fecha_emision'        => $data['fecha_emision'],
            ':establecimiento'      => $data['establecimiento'] ?? null,
            ':punto_emision'        => $data['punto_emision'] ?? null,
            // Formato canónico (9 dígitos): el índice único uq_ingresos_secuencial_activo
            // compara TEXTO, así que '16' y '000000016' pasarían como valores distintos.
            // Se normaliza aquí, en el punto de escritura, porque los ingresos se crean
            // desde muchos flujos (cobro al facturar, suscripciones, conciliación…).
            ':secuencial'           => \App\Helpers\SecuencialFormato::normalizar($data['secuencial']),
            ':numero_ingreso'       => $data['numero_ingreso'],
            ':tipo_ingreso'         => $data['tipo_ingreso'],
            ':id_ingreso_concepto'  => !empty($data['id_ingreso_concepto']) ? (int) $data['id_ingreso_concepto'] : null,
            ':monto_total'          => (float) $data['monto_total'],
            ':observaciones'        => $data['observaciones'] ?? null,
            ':estado'               => $data['estado'] ?? 'registrado',
            ':recibo_de'            => !empty($data['recibo_de']) ? trim($data['recibo_de']) : null,
            ':id_recibo_cliente'    => !empty($data['id_recibo_cliente']) ? (int) $data['id_recibo_cliente'] : null,
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE ingresos_cabecera SET
                    id_establecimiento  = :id_establecimiento,
                    id_punto_emision    = :id_punto_emision,
                    id_cliente          = :id_cliente,
                    fecha_emision       = :fecha_emision,
                    establecimiento     = :establecimiento,
                    punto_emision       = :punto_emision,
                    secuencial          = :secuencial,
                    numero_ingreso      = :numero_ingreso,
                    tipo_ingreso        = :tipo_ingreso,
                    id_ingreso_concepto = :id_ingreso_concepto,
                    monto_total         = :monto_total,
                    observaciones       = :observaciones,
                    recibo_de           = :recibo_de,
                    id_recibo_cliente   = :id_recibo_cliente,
                    updated_by          = :id_usuario,
                    updated_at          = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'                   => $id,
            ':id_empresa'           => (int) $data['id_empresa'],
            ':id_establecimiento'   => !empty($data['id_establecimiento']) ? (int) $data['id_establecimiento'] : null,
            ':id_punto_emision'     => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':id_cliente'           => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            ':id_usuario'           => (int) $data['id_usuario'],
            ':fecha_emision'        => $data['fecha_emision'],
            ':establecimiento'      => $data['establecimiento'] ?? null,
            ':punto_emision'        => $data['punto_emision'] ?? null,
            // Formato canónico (9 dígitos): el índice único uq_ingresos_secuencial_activo
            // compara TEXTO, así que '16' y '000000016' pasarían como valores distintos.
            // Se normaliza aquí, en el punto de escritura, porque los ingresos se crean
            // desde muchos flujos (cobro al facturar, suscripciones, conciliación…).
            ':secuencial'           => \App\Helpers\SecuencialFormato::normalizar($data['secuencial']),
            ':numero_ingreso'       => $data['numero_ingreso'],
            ':tipo_ingreso'         => $data['tipo_ingreso'],
            ':id_ingreso_concepto'  => !empty($data['id_ingreso_concepto']) ? (int) $data['id_ingreso_concepto'] : null,
            ':monto_total'          => (float) $data['monto_total'],
            ':observaciones'        => $data['observaciones'] ?? null,
            ':recibo_de'            => !empty($data['recibo_de']) ? trim($data['recibo_de']) : null,
            ':id_recibo_cliente'    => !empty($data['id_recibo_cliente']) ? (int) $data['id_recibo_cliente'] : null,
        ]);
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO ingresos_detalle (
                    id_ingreso, tipo_documento, id_referencia_documento, numero_documento,
                    descripcion, monto_documento, saldo_anterior, monto_cobrado, saldo_actual, id_cuenta_contable
                ) VALUES (
                    :id_ingreso, :tipo_documento, :id_ref, :num_doc,
                    :desc, :monto_doc, :saldo_ant, :monto_cob, :saldo_act, :id_cuenta
                )";
        $this->query($sql, [
            ':id_ingreso'   => (int) $data['id_ingreso'],
            ':tipo_documento' => $data['tipo_documento'],
            ':id_ref'       => !empty($data['id_referencia_documento']) ? (int)$data['id_referencia_documento'] : null,
            ':num_doc'      => $data['numero_documento'] ?? null,
            ':desc'         => $data['descripcion'] ?? null,
            ':monto_doc'    => (float) ($data['monto_documento'] ?? 0),
            ':saldo_ant'    => (float) ($data['saldo_anterior'] ?? 0),
            ':monto_cob'    => (float) ($data['monto_cobrado'] ?? 0),
            ':saldo_act'    => (float) ($data['saldo_actual'] ?? 0),
            ':id_cuenta'    => !empty($data['id_cuenta_contable']) ? (int) $data['id_cuenta_contable'] : null,
        ]);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO ingresos_pagos (
                    id_ingreso, id_forma_cobro, monto, referencia, observaciones,
                    tipo_operacion_bancaria, numero_cheque, fecha_cobro
                ) VALUES (
                    :id_ingreso, :id_forma, :monto, :ref, :obs,
                    :tipo_op, :num_chq, :fec_cob
                )";
        $this->query($sql, [
            ':id_ingreso' => (int) $data['id_ingreso'],
            ':id_forma'   => (int) $data['id_forma_cobro'],
            ':monto'      => (float) $data['monto'],
            ':ref'        => $data['referencia'] ?? null,
            ':obs'        => $data['observaciones'] ?? null,
            ':tipo_op'    => $data['tipo_operacion_bancaria'] ?? null,
            ':num_chq'    => $data['numero_cheque'] ?? null,
            ':fec_cob'    => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null,
        ]);
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ingresos_cabecera
                SET estado = 'anulado', updated_at = CURRENT_TIMESTAMP, updated_by = :usr
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    /**
     * Devuelve los ingresos ACTIVOS (no anulados, no eliminados) que cobran la factura indicada,
     * incluyendo cuántos documentos distintos cobra cada ingreso (para detectar cobros multi-factura).
     *
     * @return array Filas con: id_ingreso, numero_ingreso, total_documentos
     */
    public function getIngresosActivosPorFactura(int $idVenta, int $idEmpresa): array
    {
        $sql = "SELECT i.id AS id_ingreso,
                       i.numero_ingreso,
                       (SELECT COUNT(DISTINCT (d2.tipo_documento, d2.id_referencia_documento))
                          FROM ingresos_detalle d2
                         WHERE d2.id_ingreso = i.id) AS total_documentos
                FROM ingresos_cabecera i
                INNER JOIN ingresos_detalle d
                        ON d.id_ingreso = i.id
                       AND d.tipo_documento = 'FACTURA'
                       AND d.id_referencia_documento = :id_venta
                WHERE i.id_empresa = :emp
                  AND i.eliminado = FALSE
                  AND i.estado != 'anulado'
                GROUP BY i.id, i.numero_ingreso";
        $st = $this->db->prepare($sql);
        $st->execute([':id_venta' => $idVenta, ':emp' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function eliminarLogico(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ingresos_cabecera 
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr 
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':emp' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function deleteDetalles(int $idIngreso): void
    {
        $this->query("DELETE FROM ingresos_detalle WHERE id_ingreso = ?", [$idIngreso]);
    }

    /** Enlaza (o desvincula con null) el asiento contable generado al ingreso. */
    public function updateAsientoContable(int $idIngreso, ?int $idAsiento): void
    {
        $this->query(
            "UPDATE ingresos_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento !== null && $idAsiento > 0 ? $idAsiento : null, $idIngreso]
        );
    }

    public function deletePagos(int $idIngreso): void
    {
        $this->query("DELETE FROM ingresos_pagos WHERE id_ingreso = ?", [$idIngreso]);
    }

    public function buscarDocumentosPendientes(int $idEmpresa, string $q = '', ?int $excluirIngresoId = null, string $tipo = 'FACTURA', ?string $fechaDesde = null, ?string $fechaHasta = null, ?int $soloId = null, ?string $soloTipoDocumento = null): array
    {
        // Según el concepto del ingreso: 'RECIBO' muestra solo recibos de venta;
        // 'FACTURA_REEMBOLSO' muestra solo facturas de reembolso; cualquier otro
        // ('FACTURA') muestra facturas de venta + saldos iniciales CXC.
        // Si se pide un documento puntual (getSaldoPendienteDocumento), el tipo
        // exacto manda sobre el agrupado por concepto ('FACTURA' agrupa también
        // SALDO_INICIAL, que aquí no aplica).
        $tiposPermitidos = $soloTipoDocumento !== null
            ? '{' . $soloTipoDocumento . '}'
            : match (strtoupper($tipo)) {
                'RECIBO'            => '{RECIBO}',
                'FACTURA_REEMBOLSO' => '{FACTURA_REEMBOLSO}',
                default             => '{FACTURA,SALDO_INICIAL}',
            };

        $params     = [':id_empresa' => $idEmpresa, ':tipos' => $tiposPermitidos];
        $excluirSql = '';
        $filtroBusq = '';
        $filtroBusqRec = '';
        $filtroBusqFr = '';

        // Filtro de fecha de emisión (opcional): se aplica una sola vez sobre el
        // envoltorio final, ya que todas las ramas del UNION alias su columna a
        // "fecha_emision".
        $filtroFecha = '';
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $filtroFecha .= " AND docs.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $fechaDesde;
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $filtroFecha .= " AND docs.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $fechaHasta;
        }

        // Filtro por un único documento (usado para recalcular su saldo real al
        // guardar un ingreso — ver getSaldoPendienteDocumento): acota a UN solo id
        // en vez de traer hasta 300 filas para buscar una.
        if ($soloId !== null) {
            $filtroFecha .= " AND docs.id = :solo_id";
            $params[':solo_id'] = $soloId;
        }

        if ($excluirIngresoId !== null) {
            $excluirSql = " AND i.id <> :excluir";
            $params[':excluir'] = $excluirIngresoId;
        }

        if ($q !== '') {
            $filtroBusq = " AND (
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :q
                OR c.nombre          ILIKE :q
                OR c.identificacion  ILIKE :q
            )";
            $filtroBusqRec = " AND (
                CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) ILIKE :q
                OR c.nombre          ILIKE :q
                OR c.identificacion  ILIKE :q
            )";
            $filtroBusqFr = " AND (
                CONCAT(fr.establecimiento,'-',fr.punto_emision,'-',fr.secuencial) ILIKE :q
                OR c.nombre          ILIKE :q
                OR c.identificacion  ILIKE :q
            )";
            $params[':q'] = '%' . $q . '%';
        }

        // Filtro de búsqueda para la rama de saldos iniciales CXC (usa el mismo :q)
        $filtroBusqCxc = '';
        if ($q !== '') {
            $filtroBusqCxc = " AND (
                s.nro_documento ILIKE :q
                OR COALESCE(c.nombre, s.nombre_cliente)         ILIKE :q
                OR COALESCE(c.identificacion, s.ruc_cliente)    ILIKE :q
            )";
        }

        // Exclusión del propio ingreso (al editar) también para los cobros de saldos iniciales
        $excluirSqlSi = $excluirIngresoId !== null ? " AND i.id <> :excluir" : '';

        $sql = "WITH cobrado AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                cobrado_rec AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'RECIBO'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                cobrado_fr AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'FACTURA_REEMBOLSO'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSql
                    GROUP BY id_referencia_documento
                ),
                retenido_fact AS (
                    -- Regla compartida (AbonosVentaSql): enlace por id_venta o por
                    -- num_doc_sustento normalizado a 15 dígitos; si una retención
                    -- sustenta varias facturas, cada una recibe lo retenido en sus líneas.
                    " . AbonosVentaSql::cteRetenidoPorFactura(':id_empresa', "AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)") . "
                ),
                cobrado_si AS (
                    SELECT id_referencia_documento, SUM(monto_cobrado) AS total_cobrado
                    FROM ingresos_detalle d
                    INNER JOIN ingresos_cabecera i ON d.id_ingreso = i.id
                    WHERE d.tipo_documento = 'SALDO_INICIAL'
                      AND i.estado != 'anulado'
                      AND i.eliminado = FALSE
                      $excluirSqlSi
                    GROUP BY id_referencia_documento
                ),
                retenido_si AS (
                    SELECT s.id AS id_saldo, SUM(rd.valor_retenido) AS total_retenido
                    FROM saldos_iniciales_cxc s
                    INNER JOIN retencion_venta_cabecera r
                        ON r.eliminado = FALSE AND r.id_empresa = s.id_empresa
                       AND r.id_venta IS NULL AND r.id_cliente = s.id_cliente
                    INNER JOIN retencion_venta_detalle rd
                        ON rd.id_retencion = r.id
                       AND rd.num_doc_sustento IS NOT NULL AND rd.num_doc_sustento <> ''
                       AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                           = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                    WHERE s.id_empresa = :id_empresa AND s.eliminado = FALSE
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa AND vc.eliminado = FALSE
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                    GROUP BY s.id
                ),
                nc_aplic AS (
                    -- Notas de crédito de venta, enlazadas a la factura por num_doc_modificado
                    -- (número normalizado; columnas num_norm y total_nc)
                    " . AbonosVentaSql::cteNotasPorFactura('notas_credito_cabecera', 'total_nc', ':id_empresa') . "
                ),
                nd_aplic AS (
                    -- Notas de débito de venta: SUMAN al saldo pendiente (al contrario de la NC).
                    " . AbonosVentaSql::cteNotasPorFactura('nota_debito_cabecera', 'total_nd', ':id_empresa') . "
                )
                SELECT * FROM (
                    -- Facturas de venta pendientes (saldo neto = total + notas de débito - cobros - retenciones - notas de crédito)
                    SELECT 'FACTURA'::varchar AS tipo_documento,
                           v.id,
                           CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_documento,
                           v.fecha_emision,
                           v.dias_credito,
                           v.importe_total,
                           COALESCE(cb.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rf.total_retenido, 0) AS monto_retenido,
                           (v.importe_total + COALESCE(ndf.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) AS saldo_pendiente,
                           c.id             AS id_cliente,
                           c.nombre         AS cliente_nombre,
                           c.identificacion AS cliente_ruc
                    FROM ventas_cabecera v
                    INNER JOIN clientes c ON v.id_cliente = c.id
                    LEFT  JOIN cobrado cb       ON v.id = cb.id_referencia_documento
                    LEFT  JOIN retenido_fact rf ON v.id = rf.id_venta
                    LEFT  JOIN nc_aplic ncf     ON ncf.num_norm = " . AbonosVentaSql::numFactura('v') . "
                    LEFT  JOIN nd_aplic ndf     ON ndf.num_norm = " . AbonosVentaSql::numFactura('v') . "
                    WHERE v.id_empresa = :id_empresa
                      AND v.estado <> 'anulado' -- incluye 'borrador': una factura sin autorizar del SRI igual se cobra
                      AND v.eliminado = FALSE
                      AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (v.importe_total + COALESCE(ndf.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rf.total_retenido, 0) - COALESCE(ncf.total_nc, 0)) > 0
                      $filtroBusq

                    UNION ALL

                    -- Saldos iniciales CXC pendientes (saldo = inicial - cobros - retención registrada)
                    SELECT 'SALDO_INICIAL'::varchar AS tipo_documento,
                           s.id,
                           s.nro_documento AS numero_documento,
                           s.fecha_emision,
                           CASE WHEN s.fecha_vencimiento IS NOT NULL
                                THEN (s.fecha_vencimiento - s.fecha_emision)::int
                                ELSE 0 END AS dias_credito,
                           s.saldo_inicial AS importe_total,
                           COALESCE(csi.total_cobrado, 0) AS monto_cobrado,
                           COALESCE(rsi.total_retenido, 0)  AS monto_retenido,
                           (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) AS saldo_pendiente,
                           c.id                                          AS id_cliente,
                           COALESCE(c.nombre, s.nombre_cliente)          AS cliente_nombre,
                           COALESCE(c.identificacion, s.ruc_cliente)     AS cliente_ruc
                    FROM saldos_iniciales_cxc s
                    LEFT JOIN clientes c      ON s.id_cliente = c.id
                    LEFT JOIN cobrado_si csi  ON s.id = csi.id_referencia_documento
                    LEFT JOIN retenido_si rsi ON s.id = rsi.id_saldo
                    WHERE s.id_empresa = :id_empresa
                      AND s.eliminado = FALSE
                      AND (s.saldo_inicial - COALESCE(csi.total_cobrado, 0) - COALESCE(rsi.total_retenido, 0)) > 0
                      $filtroBusqCxc

                    UNION ALL

                    -- Recibos de venta pendientes (saldo = total - cobros)
                    SELECT 'RECIBO'::varchar AS tipo_documento,
                           r.id,
                           CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial) AS numero_documento,
                           r.fecha_emision,
                           r.dias_credito,
                           r.importe_total,
                           COALESCE(cr.total_cobrado, 0) AS monto_cobrado,
                           0 AS monto_retenido,
                           (r.importe_total - COALESCE(cr.total_cobrado, 0)) AS saldo_pendiente,
                           c.id             AS id_cliente,
                           c.nombre         AS cliente_nombre,
                           c.identificacion AS cliente_ruc
                    FROM recibos_venta_cabecera r
                    INNER JOIN clientes c ON r.id_cliente = c.id
                    LEFT  JOIN cobrado_rec cr ON r.id = cr.id_referencia_documento
                    WHERE r.id_empresa = :id_empresa
                      AND r.estado NOT IN ('anulado','facturado')
                      AND r.eliminado = FALSE
                      AND r.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (r.importe_total - COALESCE(cr.total_cobrado, 0)) > 0
                      $filtroBusqRec

                    UNION ALL

                    -- Facturas de reembolso pendientes (saldo = total - cobros; sin NC/ND/retención propias)
                    SELECT 'FACTURA_REEMBOLSO'::varchar AS tipo_documento,
                           fr.id,
                           CONCAT(fr.establecimiento,'-',fr.punto_emision,'-',fr.secuencial) AS numero_documento,
                           fr.fecha_emision,
                           0 AS dias_credito,
                           fr.importe_total,
                           COALESCE(cfr.total_cobrado, 0) AS monto_cobrado,
                           0 AS monto_retenido,
                           (fr.importe_total - COALESCE(cfr.total_cobrado, 0)) AS saldo_pendiente,
                           c.id             AS id_cliente,
                           c.nombre         AS cliente_nombre,
                           c.identificacion AS cliente_ruc
                    FROM factura_reembolso_cabecera fr
                    INNER JOIN clientes c ON fr.id_cliente = c.id
                    LEFT  JOIN cobrado_fr cfr ON fr.id = cfr.id_referencia_documento
                    WHERE fr.id_empresa = :id_empresa
                      AND fr.estado = 'autorizado'
                      AND fr.eliminado = FALSE
                      AND fr.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (fr.importe_total - COALESCE(cfr.total_cobrado, 0)) > 0
                      $filtroBusqFr
                ) docs
                WHERE docs.tipo_documento = ANY(:tipos::text[])
                $filtroFecha
                ORDER BY cliente_nombre ASC, fecha_emision ASC, id ASC
                LIMIT 301";

        $rows    = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 300;
        return ['data' => array_slice($rows, 0, 300), 'has_more' => $hasMore];
    }

    /**
     * Bloqueo de concurrencia (CLAUDE.md §8) para el documento que un ingreso está
     * a punto de cobrar: se libera solo al COMMIT/ROLLBACK de la transacción en
     * curso. Llamar ANTES de leer el saldo con getSaldoPendienteDocumento(), y
     * mantener la transacción abierta hasta el INSERT del detalle.
     */
    public function lockDocumentoPago(string $tipoDocumento, int $idReferencia, int $idEmpresa): void
    {
        $this->query(
            "SELECT pg_advisory_xact_lock(hashtext('ingreso_doc:' || :id_empresa || ':' || :tipo || ':' || :id))",
            [':id_empresa' => $idEmpresa, ':tipo' => $tipoDocumento, ':id' => $idReferencia]
        );
    }

    /**
     * Saldo pendiente REAL de un documento específico, recalculado en el momento
     * (no confiar en el "saldo_anterior" que envía el navegador: pudo haberse
     * cobrado desde otro ingreso mientras el modal seguía abierto). Reutiliza el
     * mismo query que el buscador de documentos pendientes, acotado a un solo id.
     * Devuelve 0.0 si el documento ya no tiene saldo pendiente (o no existe).
     */
    public function getSaldoPendienteDocumento(string $tipoDocumento, int $idReferencia, int $idEmpresa, ?int $excluirIngresoId = null): float
    {
        $resultado = $this->buscarDocumentosPendientes(
            $idEmpresa, '', $excluirIngresoId, 'FACTURA', null, null, $idReferencia, $tipoDocumento
        );
        foreach ($resultado['data'] as $row) {
            if ($row['tipo_documento'] === $tipoDocumento && (int) $row['id'] === $idReferencia) {
                return (float) $row['saldo_pendiente'];
            }
        }
        return 0.0;
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM ingresos_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)";
        // Mismo formato canónico que usa insertCabecera: comparar '16' contra '000000016'
        // devolvía siempre "no existe" y la validación no servía de nada.
        $params = [$idEmpresa, $idEstablecimiento, $idPunto,
                   \App\Helpers\SecuencialFormato::normalizar($secuencial), $idEmpresa];

        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }

        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }
}
