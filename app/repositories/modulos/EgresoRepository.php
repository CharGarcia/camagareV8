<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class EgresoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('egresos_cabecera');
        // Auto-Migración transparente para soporte de cheques
        try {
            $st = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'egresos_pagos' AND column_name = 'tipo_operacion_bancaria'");
            if (!$st->fetch()) {
                $this->db->exec("ALTER TABLE egresos_pagos ADD COLUMN tipo_operacion_bancaria VARCHAR(50) NULL");
                $this->db->exec("ALTER TABLE egresos_pagos ADD COLUMN numero_cheque VARCHAR(50) NULL");
                $this->db->exec("ALTER TABLE egresos_pagos ADD COLUMN fecha_cobro DATE NULL");
            }
        } catch (\Exception $e) {
            // Silenciar en caso de no poseer permisos DDL
        }
    }

    /** Series REALMENTE usadas en egresos guardados (para el filtro "Serie" del buscador). */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM egresos_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /** Tipos de egreso REALMENTE usados en la empresa (select "Tipo de egreso" del modal de filtros). */
    public function getTiposEgresoDistintos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT tipo_egreso
                FROM egresos_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND tipo_egreso IS NOT NULL AND tipo_egreso != ''
                ORDER BY tipo_egreso";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Usuarios que han registrado algún egreso en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConEgresos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM egresos_cabecera e
                JOIN usuarios u ON u.id = e.created_by
                WHERE e.id_empresa = :id_empresa AND e.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de los egresos (pestaña "Detalles" del modal de filtros):
     * devuelve cada documento pagado y cada forma de pago que coincide con el texto,
     * junto con el egreso al que pertenece. Mismo alcance que el listado (empresa, no
     * eliminados, ambiente) y registros propios cuando el usuario no tiene acceso total.
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "e.id_empresa = :id_empresa AND e.eliminado = false
                      AND e.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND e.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.numero_documento', 'd.descripcion', 'd.tipo_documento', 'd.monto_documento::text', 'd.monto_pagado::text', 'pc.codigo', 'pc.nombre'],
            $q, $params, 'dt'
        );
        // Sin estado_cheque: casi todos son "vigente" y buscarlo solo mete ruido (mismo
        // criterio que el listado: los estados se filtran, no se buscan).
        $condPago = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['fp.nombre', 'ep.referencia', 'ep.numero_cheque', 'ep.beneficiario_cheque', 'ep.tipo_operacion_bancaria',
             'ep.monto::text', 'ep.fecha_cobro::text'],
            $q, $params, 'pg'
        );
        if ($condDet === '' || $condPago === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT e.id, e.numero_egreso, e.fecha_emision, e.estado,
                           COALESCE(p.razon_social, emp.nombres_apellidos, e.beneficiario_nombre) AS beneficiario
                    FROM egresos_cabecera e
                    LEFT JOIN proveedores p ON p.id = e.id_proveedor
                    LEFT JOIN empleados emp ON emp.id = e.id_empleado
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'DOCUMENTO' AS origen,
                           d.tipo_documento AS tipo,
                           d.numero_documento AS referencia,
                           d.descripcion,
                           d.monto_pagado AS monto,
                           b.id AS id_egreso, b.numero_egreso, b.fecha_emision, b.estado, b.beneficiario
                    FROM egresos_detalle d
                    JOIN base b ON b.id = d.id_egreso
                    LEFT JOIN plan_cuentas pc ON pc.id = d.id_cuenta_contable
                    WHERE d.eliminado = FALSE AND $condDet
                    UNION ALL
                    SELECT 'PAGO' AS origen,
                           fp.nombre AS tipo,
                           NULLIF(CONCAT_WS(' ', NULLIF(ep.tipo_operacion_bancaria, ''), NULLIF(ep.referencia, ''), NULLIF(ep.numero_cheque, '')), '') AS referencia,
                           NULLIF(CONCAT_WS(' · ', NULLIF(ep.beneficiario_cheque, ''),
                                  CASE WHEN ep.estado_cheque IS NOT NULL AND ep.estado_cheque <> 'vigente' THEN 'Cheque ' || ep.estado_cheque END), '') AS descripcion,
                           ep.monto,
                           b.id AS id_egreso, b.numero_egreso, b.fecha_emision, b.estado, b.beneficiario
                    FROM egresos_pagos ep
                    JOIN base b ON b.id = ep.id_egreso
                    LEFT JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago
                    WHERE ep.eliminado = FALSE AND $condPago
                ) x
                ORDER BY x.fecha_emision DESC, x.id_egreso DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
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
        'id'            => 'e.id',
        'fecha_emision' => 'e.fecha_emision',
        'numero_egreso' => 'e.numero_egreso',
        'tipo_egreso'   => 'e.tipo_egreso',
        'monto_total'   => 'e.monto_total',
        'estado'        => 'e.estado',
        'observaciones' => 'e.observaciones',
        // A quién se le paga: puede ser proveedor, empleado o un beneficiario suelto,
        // igual que la columna del listado.
        'sujeto_nombre' => "COALESCE(p.razon_social, emp.nombres_apellidos, e.beneficiario_nombre, 'OTRO')",
    ];

    /**
     * @param array $ordenMulti Criterios de orden [['col'=>…,'dir'=>…], …] cuando el
     *        llamador usa `OrdenListado`. Vacío = se ordena por $ordenCol/$ordenDir.
     */
    /**
     * @param ?int $idUsuario Registros propios (CLAUDE.md §6): si el usuario NO tiene
     *        acceso total, el controller manda su id y solo se listan los egresos que él
     *        creó (`created_by`). null = toda la empresa. Va al final, después de
     *        $ordenMulti, para no mover la posición de los argumentos existentes.
     */
    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', array $ordenMulti = [], ?int $idUsuario = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE e.id_empresa = :id_empresa AND e.eliminado = false AND e.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: las columnas del listado y sus relacionadas (el buscador de
            // la vista no sugiere campos; lo que se escribe se busca en todo). Los
            // documentos pagados viven en el detalle: se agregan como una sola cadena
            // por egreso.
            // Decisión del usuario (igual que en Ingresos y Facturas): las columnas
            // Tipo y Estado NO entran en el texto libre; se filtran desde el modal.
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    // Rendimiento: monto y fecha solo se comparan si la palabra tiene
                    // dígitos (ver FiltrosBusqueda::condicionTexto).
                    'e.numero_egreso',                                    // Nº Egreso
                    'e.secuencial',
                    "CONCAT(e.establecimiento,'-',e.punto_emision)",      // Serie
                    'p.razon_social',                                     // Beneficiario
                    'emp.nombres_apellidos',
                    'e.beneficiario_nombre',
                    'p.identificacion',                                   // RUC / cédula del beneficiario
                    'emp.identificacion',
                    'e.observaciones',                                    // Observaciones
                    'u.nombre',                                           // Usuario que registró
                    ['sql' => 'e.fecha_emision', 'si' => \App\Helpers\FiltrosBusqueda::SI_DIGITOS], // Fecha
                    ['sql' => 'e.monto_total', 'si' => \App\Helpers\FiltrosBusqueda::SI_DIGITOS],   // Monto
                    "(SELECT STRING_AGG(d.numero_documento, ' ') FROM egresos_detalle d WHERE d.id_egreso = e.id AND d.eliminado = FALSE)",
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
        // vista). Se conservan las antiguas (proveedor, empleado, concepto…) porque
        // también viajan en los enlaces de PDF/Excel.
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'proveedor'     => 'p.razon_social',
                'empleado'      => 'emp.nombres_apellidos',
                // Beneficiario = lo que muestra la columna del listado.
                'beneficiario'  => "COALESCE(p.razon_social, emp.nombres_apellidos, e.beneficiario_nombre, '')",
                'ruc'           => "COALESCE(p.identificacion, emp.identificacion, '')",
                'identificacion' => "COALESCE(p.identificacion, emp.identificacion, '')",
                'numero'        => 'e.numero_egreso',
                'nro'           => 'e.numero_egreso',
                'concepto'      => 'e.observaciones',
                'obs'           => 'e.observaciones',
                'observaciones' => 'e.observaciones',
            ],
            'exacto'   => [
                'estado'       => 'e.estado',
                'tipo'         => 'e.tipo_egreso',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del buscador.
                'serie'        => "CONCAT(e.establecimiento,'-',e.punto_emision)",
                // sujeto:proveedor / empleado / otro
                'sujeto'       => "CASE WHEN e.id_proveedor IS NOT NULL THEN 'proveedor'
                                        WHEN e.id_empleado IS NOT NULL THEN 'empleado'
                                        ELSE 'otro' END",
                'id_concepto'  => 'e.id_egreso_concepto',
                'usuario'      => 'e.created_by',
                // asiento:si / asiento:no
                'asiento'      => "CASE WHEN e.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
            ],
            'fecha'    => [ 'fecha' => 'e.fecha_emision', 'fecha_emision' => 'e.fecha_emision' ],
            'numerico' => [
                'monto' => 'e.monto_total',
                'total' => 'e.monto_total',
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'e.secuencial::numeric',
            ],
        ]);

        // Registros propios: sin acceso total, solo los egresos que creó el usuario.
        // En egresos_cabecera el creador está en created_by (no hay id_usuario). Se
        // agrega al WHERE compartido, así aplica igual al COUNT y a la consulta.
        if ($idUsuario !== null) {
            $where .= " AND e.created_by = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // Una o varias columnas (Shift+clic en el listado), siempre validadas contra
        // MAPA_ORDEN, con e.id como desempate para que las filas empatadas no bailen
        // entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula(
            $ordenMulti,
            self::MAPA_ORDEN,
            'e.fecha_emision',
            'e.id DESC'
        );

        // Rendimiento (2026-09-16): conteo + página en UNA consulta, y los tipos del
        // detalle (subconsulta por fila) calculados solo para las filas de la página.
        // Ver App\Helpers\ListadoPaginado.
        $joins = "LEFT JOIN proveedores p ON e.id_proveedor = p.id
                LEFT JOIN empleados emp ON e.id_empleado = emp.id
                LEFT JOIN usuarios u ON e.created_by = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso ec ON e.id_egreso_concepto = ec.id";

        return \App\Helpers\ListadoPaginado::consultar(
            fn(string $sql, array $p) => $this->query($sql, $p)->fetchAll(PDO::FETCH_ASSOC),
            [
                'tabla'       => 'egresos_cabecera',
                'alias'       => 'e',
                'joinsFiltro' => $joins,
                'joinsFinal'  => $joins,
                'where'       => $where,
                'orderBy'     => $orderBy,
                'perPage'     => $perPage,
                'conBusqueda' => trim($buscar) !== '',   // sin buscar: forma liviana (ids por índice + COUNT aparte)
                'offset'      => $offset,
                'select'      => "e.*,
                       COALESCE(p.razon_social, emp.nombres_apellidos, e.beneficiario_nombre, 'N/A') AS sujeto_nombre,
                       COALESCE(p.identificacion, emp.identificacion, '') AS sujeto_ruc,
                       u.nombre AS usuario_nombre,
                       ec.nombre AS concepto_nombre,
                       (SELECT STRING_AGG(t, ',') FROM (
                           SELECT DISTINCT ed.tipo_documento AS t
                           FROM egresos_detalle ed
                           WHERE ed.id_egreso = e.id AND ed.eliminado = FALSE
                           ORDER BY t
                       ) sub) AS tipos_detalle",
            ],
            $params
        );
    }

    /**
     * Egresos del rango de fechas o de números para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE e.id_empresa = :id_empresa AND e.eliminado = false
                   " . $this->condicionRangoDescargaMasiva('e.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params);
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND e.created_by = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT e.id, e.establecimiento, e.punto_emision, e.secuencial, e.fecha_emision, e.estado
                FROM egresos_cabecera e
                $where
                ORDER BY e.fecha_emision ASC, e.id ASC";
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT e.*,
                       COALESCE(p.razon_social, emp.nombres_apellidos, e.beneficiario_nombre, 'N/A') AS sujeto_nombre,
                       COALESCE(p.identificacion, emp.identificacion, '') AS sujeto_ruc,
                       COALESCE(p.email, emp.email) AS sujeto_email,
                       u.nombre AS usuario_nombre,
                       ec.nombre AS concepto_nombre
                FROM egresos_cabecera e
                LEFT JOIN proveedores p ON e.id_proveedor = p.id
                LEFT JOIN empleados emp ON e.id_empleado = emp.id
                LEFT JOIN usuarios u ON e.created_by = u.id
                LEFT JOIN empresa_opciones_ingreso_egreso ec ON e.id_egreso_concepto = ec.id
                WHERE e.id = :id AND e.id_empresa = :id_empresa AND e.eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idEgreso): array
    {
        $sql = "SELECT ed.*, COALESCE(c.fecha_emision, l.fecha_emision) AS fecha_documento,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM egresos_detalle ed
                LEFT JOIN compras_cabecera c ON ed.tipo_documento = 'COMPRA' AND ed.id_referencia_documento = c.id
                LEFT JOIN liquidaciones_cabecera l ON ed.tipo_documento = 'LIQUIDACION' AND ed.id_referencia_documento = l.id
                LEFT JOIN plan_cuentas pc ON pc.id = ed.id_cuenta_contable
                WHERE ed.id_egreso = ? AND ed.eliminado = FALSE
                ORDER BY ed.id ASC";
        return $this->query($sql, [$idEgreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPagos(int $idEgreso): array
    {
        $sql = "SELECT ep.id, ep.id_egreso, ep.id_forma_pago, ep.monto, ep.referencia,
                       ep.tipo_operacion_bancaria, ep.numero_cheque, ep.fecha_cobro, ep.beneficiario_cheque,
                       ep.estado_cheque, ep.motivo_anulacion_cheque, ep.anulado_cheque_at,
                       efc.nombre AS forma_pago_nombre, efc.tipo AS forma_pago_tipo,
                       be.nombre_banco AS banco_nombre,
                       " . $this->sqlChequeConciliado('ep', 'efc') . " AS cheque_conciliado,
                       " . $this->sqlChequeFechaBanco('ep', 'efc') . " AS cheque_fecha_banco
                FROM egresos_pagos ep
                INNER JOIN empresa_formas_pago efc ON ep.id_forma_pago = efc.id
                LEFT JOIN bancos_ecuador be ON be.id = efc.id_banco
                WHERE ep.id_egreso = ? AND ep.eliminado = FALSE
                ORDER BY ep.id ASC";
        return $this->query($sql, [$idEgreso])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Subconsulta EXISTS: TRUE si el cheque del pago ya fue conciliado en Control
     * Bancario (tiene fecha_banco), es decir "reportado como cobrado".
     *
     * Dos anclajes posibles, según cómo Control Bancario haya podido anotar ese
     * movimiento (ver 20260824_control_bancario_sin_contabilidad.sql):
     *  1. Cuenta CON contabilidad: la anotación cuelga de la línea bancaria del
     *     asiento del egreso (misma cuenta contable).
     *  2. Cuenta SIN cuenta contable (empresa que no lleva contabilidad): no hay
     *     asiento, la anotación cuelga del propio pago (origen_tipo/origen_id).
     * Sin la segunda rama, un cheque ya cobrado de esas empresas se seguía viendo
     * como "en circulación" y el sistema dejaba editarle la fecha o anularlo.
     */
    private function sqlChequeConciliado(string $ep, string $fp): string
    {
        return "(EXISTS (
                    SELECT 1
                    FROM asientos_contables_cabecera acc
                    JOIN asientos_contables_detalle acd ON acd.id_asiento = acc.id AND acd.eliminado = FALSE
                    JOIN control_bancario_movimientos cbm ON cbm.id_asiento_detalle = acd.id AND cbm.eliminado = FALSE
                    WHERE acc.modulo_origen = 'egreso'
                      AND acc.id_referencia_origen = {$ep}.id_egreso
                      AND acd.id_cuenta_contable = {$fp}.id_cuenta_contable
                      AND cbm.fecha_banco IS NOT NULL
                ) OR EXISTS (
                    SELECT 1
                    FROM control_bancario_movimientos cbm2
                    WHERE cbm2.origen_tipo = 'egreso'
                      AND cbm2.origen_id = {$ep}.id
                      AND cbm2.eliminado = FALSE
                      AND cbm2.fecha_banco IS NOT NULL
                ))";
    }

    /**
     * Fecha en que el banco hizo efectivo el cheque (la Fecha Banco de Control Bancario),
     * por cualquiera de los dos anclajes de sqlChequeConciliado(). NULL si aún no se cobró.
     * Solo para mostrarla junto al estado del cheque en la fila del pago.
     */
    private function sqlChequeFechaBanco(string $ep, string $fp): string
    {
        return "(SELECT MIN(m.fecha_banco)
                 FROM control_bancario_movimientos m
                 WHERE m.eliminado = FALSE
                   AND m.fecha_banco IS NOT NULL
                   AND (
                         (m.origen_tipo = 'egreso' AND m.origen_id = {$ep}.id)
                         OR m.id_asiento_detalle IN (
                                SELECT acd2.id
                                FROM asientos_contables_cabecera acc2
                                JOIN asientos_contables_detalle acd2 ON acd2.id_asiento = acc2.id AND acd2.eliminado = FALSE
                                WHERE acc2.modulo_origen = 'egreso'
                                  AND acc2.id_referencia_origen = {$ep}.id_egreso
                                  AND acd2.id_cuenta_contable = {$fp}.id_cuenta_contable
                            )
                       ))";
    }

    /** Un pago-cheque con su estado de conciliación, para validar la edición de fecha. */
    public function getPagoChequeParaEdicion(int $idEmpresa, int $idPago): ?array
    {
        $sql = "SELECT ep.id, ep.id_egreso, ep.tipo_operacion_bancaria, ep.fecha_cobro,
                       ep.numero_cheque, ep.estado_cheque,
                       e.estado AS egreso_estado, e.fecha_emision,
                       " . $this->sqlChequeConciliado('ep', 'efc') . " AS cheque_conciliado
                FROM egresos_pagos ep
                INNER JOIN empresa_formas_pago efc ON ep.id_forma_pago = efc.id
                INNER JOIN egresos_cabecera e ON ep.id_egreso = e.id
                WHERE ep.id = ? AND e.id_empresa = ? AND ep.eliminado = FALSE
                LIMIT 1";
        $row = $this->query($sql, [$idPago, $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Actualiza solo la fecha de cobro de un pago-cheque. */
    public function actualizarFechaCobro(int $idPago, string $fecha): void
    {
        $this->query("UPDATE egresos_pagos SET fecha_cobro = :f WHERE id = :id",
            [':f' => $fecha, ':id' => $idPago]);
    }

    /**
     * Anula un cheque puntual dejando la fila intacta (no se borra ni se marca
     * `eliminado`): queda visible en el historial de pagos del egreso, y su
     * monto deja de contarse en el asiento contable (AsientoBuilderService)
     * y en Control Bancario / Impresión de Cheques.
     */
    public function anularCheque(int $idPago, string $motivo, int $idUsuario): void
    {
        $sql = "UPDATE egresos_pagos
                SET estado_cheque = 'anulado', motivo_anulacion_cheque = :motivo,
                    anulado_cheque_at = CURRENT_TIMESTAMP, anulado_cheque_by = :usr
                WHERE id = :id AND COALESCE(estado_cheque, 'vigente') = 'vigente'";
        $this->query($sql, [':motivo' => $motivo, ':usr' => $idUsuario, ':id' => $idPago]);
    }

    /** Actualiza el nombre a imprimir en el cheque (override; null = usa el beneficiario del egreso). */
    public function actualizarBeneficiarioCheque(int $idPago, ?string $nombre): void
    {
        $nombre = $nombre !== null ? mb_strtoupper(trim($nombre), 'UTF-8') : null;
        $this->query("UPDATE egresos_pagos SET beneficiario_cheque = :b WHERE id = :id",
            [':b' => ($nombre !== null && $nombre !== '') ? $nombre : null, ':id' => $idPago]);
    }

    public function getConceptosEgreso(int $idEmpresa): array
    {
        $sql = "SELECT o.*, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN plan_cuentas pc ON pc.id = o.id_cuenta_contable
                WHERE o.id_empresa = :id_empresa
                  AND o.aplica_egresos = TRUE
                  AND UPPER(o.estado) = 'ACTIVO'
                  AND o.eliminado = FALSE
                ORDER BY o.nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Documentos de nómina pendientes de un empleado: líneas de rol + anticipos/préstamos. */
    public function getDocumentosPendientesEmpleado(int $idEmpleado, int $idEmpresa): array
    {
        $sql = "WITH pagado_rol AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'ROL' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ),
                pagado_ant AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'ANTICIPO' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ),
                pagado_pre AS (
                    SELECT d.tipo_documento, d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'PRESTAMO9'
                      AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.tipo_documento, d.id_referencia_documento
                ),
                pagado_dc AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'DECIMO_CUARTO' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                ),
                pagado_dt AS (
                    SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                    FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE d.tipo_documento = 'DECIMO_TERCERO' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                    GROUP BY d.id_referencia_documento
                )
                SELECT 'ROL' AS tipo_doc_bd, rd.id,
                       (CASE rc.tipo_rol WHEN 'MENSUAL' THEN 'Rol Mensual' WHEN 'QUINCENA' THEN 'Quincena' WHEN 'SEMANAL' THEN 'Semanal' ELSE 'Rol' END)
                           || ' ' || rc.periodo_mes || '/' || rc.periodo_anio AS numero_documento,
                       rc.fecha_pago AS fecha_emision,
                       rd.neto AS monto_total,
                       COALESCE(p.total_pagado, 0) AS monto_pagado_previo,
                       (rd.neto - COALESCE(p.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM rol_detalle rd
                INNER JOIN rol_cabecera rc ON rc.id = rd.id_rol
                LEFT JOIN pagado_rol p ON p.id_referencia_documento = rd.id
                WHERE rd.id_empleado = :id_emp AND rd.id_empresa = :id_empresa
                  AND rc.eliminado = FALSE AND rc.estado IN ('generado','pagado','contabilizado')
                  AND (rd.neto - COALESCE(p.total_pagado, 0)) > 0.01
                UNION ALL
                SELECT 'ANTICIPO' AS tipo_doc_bd, n.id,
                       n.tipo_nombre || ' ' || n.periodo_mes || '/' || n.periodo_anio AS numero_documento,
                       n.fecha AS fecha_emision,
                       n.valor AS monto_total,
                       COALESCE(pa.total_pagado, 0) AS monto_pagado_previo,
                       (n.valor - COALESCE(pa.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM novedades n
                LEFT JOIN pagado_ant pa ON pa.id_referencia_documento = n.id
                WHERE n.id_empleado = :id_emp AND n.id_empresa = :id_empresa
                  AND n.eliminado = FALSE AND n.estado = 'activo' AND n.tipo_codigo = '3'
                  AND n.desembolsado_migrado = FALSE
                  AND (n.valor - COALESCE(pa.total_pagado, 0)) > 0.01
                UNION ALL
                -- Solo el préstamo tipo 9 (Préstamo Empresa) requiere desembolso por egreso:
                -- el 7 (Quirografario) y el 8 (Hipotecario) los desembolsa el IESS/banco
                -- directo al empleado, no la empresa, así que su cuota descuenta directo en
                -- el rol sin pasar por aquí (ver CatalogoNovedades::CODS_PRESTAMO).
                SELECT ('PRESTAMO' || n.tipo_codigo) AS tipo_doc_bd, n.id_empleado AS id,
                       MAX(n.tipo_nombre) || ' (desembolso)' AS numero_documento,
                       MIN(n.fecha) AS fecha_emision,
                       SUM(n.valor) AS monto_total,
                       COALESCE(pp.total_pagado, 0) AS monto_pagado_previo,
                       (SUM(n.valor) - COALESCE(pp.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM novedades n
                LEFT JOIN pagado_pre pp ON pp.tipo_documento = ('PRESTAMO' || n.tipo_codigo) AND pp.id_referencia_documento = n.id_empleado
                WHERE n.id_empleado = :id_emp AND n.id_empresa = :id_empresa
                  AND n.eliminado = FALSE AND n.estado = 'activo' AND n.tipo_codigo = '9'
                  AND n.desembolsado_migrado = FALSE
                GROUP BY n.id_empleado, n.tipo_codigo, pp.total_pagado
                HAVING (SUM(n.valor) - COALESCE(pp.total_pagado, 0)) > 0.01
                UNION ALL
                SELECT 'DECIMO_CUARTO' AS tipo_doc_bd, dcd.id,
                       'Décimo Cuarto ' || dcc.anio || ' (' ||
                           CASE WHEN dcc.region_grupo = 'sierra_amazonia' THEN 'Sierra/Amazonía' ELSE 'Costa/Insular' END
                       || ')' AS numero_documento,
                       dcc.fecha_emision,
                       dcd.valor AS monto_total,
                       COALESCE(pdc.total_pagado, 0) AS monto_pagado_previo,
                       (dcd.valor - COALESCE(pdc.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM decimo_cuarto_detalle dcd
                INNER JOIN decimo_cuarto_cabecera dcc ON dcc.id = dcd.id_cabecera
                LEFT JOIN pagado_dc pdc ON pdc.id_referencia_documento = dcd.id
                WHERE dcd.id_empleado = :id_emp AND dcd.id_empresa = :id_empresa
                  AND dcc.eliminado = FALSE AND dcd.mensualiza = FALSE AND dcd.valor > 0.01
                  AND (dcd.valor - COALESCE(pdc.total_pagado, 0)) > 0.01
                UNION ALL
                SELECT 'DECIMO_TERCERO' AS tipo_doc_bd, dtd.id,
                       'Décimo Tercero ' || dtc.anio AS numero_documento,
                       dtc.fecha_emision,
                       dtd.valor AS monto_total,
                       COALESCE(pdt.total_pagado, 0) AS monto_pagado_previo,
                       (dtd.valor - COALESCE(pdt.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM decimo_tercero_detalle dtd
                INNER JOIN decimo_tercero_cabecera dtc ON dtc.id = dtd.id_cabecera
                LEFT JOIN pagado_dt pdt ON pdt.id_referencia_documento = dtd.id
                WHERE dtd.id_empleado = :id_emp AND dtd.id_empresa = :id_empresa
                  AND dtc.eliminado = FALSE AND dtd.mensualiza = FALSE AND dtd.valor > 0.01
                  AND (dtd.valor - COALESCE(pdt.total_pagado, 0)) > 0.01
                ORDER BY numero_documento ASC";
        return $this->query($sql, [':id_emp' => $idEmpleado, ':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocumentosPendientesProveedor(int $idProveedor, int $idEmpresa): array
    {
        // Solo compras que se pueden pagar: ni pendientes de aprobación, ni rechazadas, ni anuladas.
        $compraPagable = \App\Helpers\TiposComprobanteCompra::sqlCompraPagable('c.estado');

        // Calcular acumulado pagado previamente en egresos registrados
        $sql = "WITH pagado AS (
                    SELECT d.tipo_documento, d.id_referencia_documento, SUM(d.monto_pagado) as total_pagado
                    FROM egresos_detalle d
                    INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                    WHERE e.estado != 'anulado' 
                      AND e.eliminado = FALSE
                      AND d.eliminado = FALSE
                    GROUP BY d.tipo_documento, d.id_referencia_documento
                ),
                nc_nd AS (
                    -- Notas de crédito ('04') y débito ('05') de compra, enlazadas a la factura por documento_modificado
                    SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
                           SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                           SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
                    FROM compras_cabecera nc
                    WHERE nc.tipo_comprobante IN ('04','05')
                      AND nc.eliminado = FALSE
                      AND nc.id_empresa = :id_empresa
                    GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
                )
                -- Unimos Compras (compras_cabecera) y Liquidaciones (liquidaciones_cabecera)
                SELECT 'COMPRA' as tipo_doc_bd, c.id,
                       CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero_documento,
                       c.fecha_emision,
                       -- + total_terceros: rubros que la planilla de luz/agua recauda para
                       -- terceros (bomberos, tasa de basura). Fuera del importe declarado al
                       -- SRI, pero dentro de lo que se le transfiere al proveedor.
                       (c.importe_total + COALESCE(c.total_terceros, 0)) AS monto_total,
                       COALESCE(p.total_pagado, 0) AS monto_pagado_previo,
                       (c.importe_total + COALESCE(c.total_terceros, 0) - COALESCE(p.total_pagado, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM compras_cabecera c
                LEFT JOIN pagado p ON c.id = p.id_referencia_documento AND p.tipo_documento = 'COMPRA'
                LEFT JOIN nc_nd nn ON nn.id_empresa = c.id_empresa
                                  AND nn.id_proveedor = c.id_proveedor
                                  AND nn.documento_modificado = CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov)
                WHERE c.id_proveedor = :id_prov
                  AND c.id_empresa = :id_empresa
                  AND c.eliminado = FALSE
                  AND COALESCE(c.tipo_comprobante, '01') NOT IN ('04','05')
                  AND {$compraPagable}
                  AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND (c.importe_total + COALESCE(c.total_terceros, 0) - COALESCE(p.total_pagado, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) > 0.01
                UNION ALL
                SELECT 'LIQUIDACION' as tipo_doc_bd, l.id,
                       CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                       l.fecha_emision,
                       l.importe_total AS monto_total,
                       COALESCE(p.total_pagado, 0) AS monto_pagado_previo,
                       (l.importe_total - COALESCE(p.total_pagado, 0)) AS saldo_pendiente,
                       0 AS dias_credito
                FROM liquidaciones_cabecera l
                LEFT JOIN pagado p ON l.id = p.id_referencia_documento AND p.tipo_documento = 'LIQUIDACION'
                WHERE l.id_proveedor = :id_prov
                  AND l.id_empresa = :id_empresa
                  AND l.eliminado = FALSE
                  AND l.estado = 'autorizado'
                  AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND (l.importe_total - COALESCE(p.total_pagado, 0)) > 0.01
                ORDER BY fecha_emision ASC";

        return $this->query($sql, [
            ':id_prov' => $idProveedor,
            ':id_empresa' => $idEmpresa
        ])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO egresos_cabecera (
                    id_empresa, id_punto_emision, establecimiento, punto_emision, secuencial, numero_egreso,
                    fecha_emision, tipo_egreso, tipo_sujeto, id_proveedor, id_empleado, id_egreso_concepto,
                    monto_total, observaciones, estado, tipo_ambiente, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_punto, :est, :pto, :sec, :num,
                    :fecha, :tipo_egreso, :tipo_sujeto, :id_prov, :id_emp, :id_conc,
                    :total, :obs, :estado,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :usr, :usr
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => (int) $data['id_empresa'],
            ':id_punto'     => !empty($data['id_punto_emision']) ? (int)$data['id_punto_emision'] : null,
            ':est'          => $data['establecimiento'] ?? null,
            ':pto'          => $data['punto_emision'] ?? null,
            // Formato canónico (9 dígitos): ver la nota de IngresoRepository::insertCabecera.
            ':sec'          => \App\Helpers\SecuencialFormato::normalizar($data['secuencial'] ?? null),
            ':num'          => $data['numero_egreso'],
            ':fecha'        => $data['fecha_emision'],
            ':tipo_egreso'  => $data['tipo_egreso'],
            ':tipo_sujeto'  => $data['tipo_sujeto'],
            ':id_prov'      => !empty($data['id_proveedor']) ? (int)$data['id_proveedor'] : null,
            ':id_emp'       => !empty($data['id_empleado']) ? (int)$data['id_empleado'] : null,
            ':id_conc'      => !empty($data['id_egreso_concepto']) ? (int)$data['id_egreso_concepto'] : null,
            ':total'        => (float) $data['monto_total'],
            ':obs'          => $data['observaciones'] ?? null,
            ':estado'       => $data['estado'] ?? 'registrado',
            ':usr'          => (int) $data['usuario_id']
        ]);

        return (int) $st->fetchColumn();
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO egresos_detalle (
                    id_egreso, tipo_documento, id_referencia_documento, numero_documento,
                    descripcion, monto_documento, saldo_anterior, monto_pagado, saldo_actual, id_cuenta_contable
                ) VALUES (
                    :id_egreso, :tipo_doc, :id_ref, :num_doc, :desc, :monto_doc, :saldo_ant, :monto_pag, :saldo_act, :id_cuenta
                )";
        $this->query($sql, [
            ':id_egreso'    => (int) $data['id_egreso'],
            ':tipo_doc'     => $data['tipo_documento'],
            ':id_ref'       => !empty($data['id_referencia_documento']) ? (int)$data['id_referencia_documento'] : null,
            ':num_doc'      => $data['numero_documento'] ?? null,
            ':desc'         => $data['descripcion'] ?? null,
            ':monto_doc'    => (float) ($data['monto_documento'] ?? 0),
            ':saldo_ant'    => (float) ($data['saldo_anterior'] ?? 0),
            ':monto_pag'    => (float) ($data['monto_pagado'] ?? 0),
            ':saldo_act'    => (float) ($data['saldo_actual'] ?? 0),
            ':id_cuenta'    => !empty($data['id_cuenta_contable']) ? (int) $data['id_cuenta_contable'] : null,
        ]);
    }

    public function insertPago(array $data): void
    {
        $benef = mb_strtoupper(trim((string) ($data['beneficiario_cheque'] ?? '')), 'UTF-8');
        $sql = "INSERT INTO egresos_pagos (
                    id_egreso, id_forma_pago, monto, referencia,
                    tipo_operacion_bancaria, numero_cheque, fecha_cobro, beneficiario_cheque
                ) VALUES (
                    :id_egreso, :id_forma, :monto, :ref,
                    :tipo_op, :num_chq, :fec_cob, :benef
                )";
        $this->query($sql, [
            ':id_egreso'  => (int) $data['id_egreso'],
            ':id_forma'   => (int) $data['id_forma_pago'],
            ':monto'      => (float) $data['monto'],
            ':ref'        => $data['referencia'] ?? null,
            ':tipo_op'    => $data['tipo_operacion_bancaria'] ?? null,
            ':num_chq'    => $data['numero_cheque'] ?? null,
            ':fec_cob'    => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null,
            ':benef'      => $benef !== '' ? $benef : null
        ]);
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE egresos_cabecera SET estado = 'anulado', updated_by = :usr, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':emp' => $idEmpresa, ':usr' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    /** Enlaza (o desvincula con null) el asiento contable generado al egreso. */
    public function updateAsientoContable(int $idEgreso, ?int $idAsiento): void
    {
        $this->query(
            "UPDATE egresos_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento !== null && $idAsiento > 0 ? $idAsiento : null, $idEgreso]
        );
    }
    
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial): bool
    {
        $sql = "SELECT COUNT(*) FROM egresos_cabecera
                WHERE id_empresa = ? AND establecimiento = (SELECT codigo FROM empresa_establecimiento WHERE id = ?)
                  AND punto_emision = (SELECT codigo_punto FROM empresa_punto_emision WHERE id = ?)
                  AND secuencial = ? AND eliminado = FALSE
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)";
        // Formato canónico, igual que insertCabecera (si no, la comparación de texto falla).
        return (int) $this->query($sql, [$idEmpresa, $idEstablecimiento, $idPunto,
            \App\Helpers\SecuencialFormato::normalizar($secuencial), $idEmpresa])->fetchColumn() > 0;
    }

    public function buscarDocumentosPendientesEgreso(int $idEmpresa, string $q = '', string $tipo = 'COMPRA', ?int $excluirEgresoId = null, ?string $fechaDesde = null, ?string $fechaHasta = null, ?int $soloId = null, ?string $soloTipoDocBd = null): array
    {
        $params     = [':id_empresa' => $idEmpresa];
        $excluirSql = '';

        if ($excluirEgresoId !== null) {
            $excluirSql = " AND e.id <> :excluir";
            $params[':excluir'] = $excluirEgresoId;
        }

        // Filtro de fecha de emisión (opcional): cada rama arma su propio snippet
        // porque la columna de origen tiene alias distinto (cb./l./u.fecha_emision).
        $filtroFecha = function (string $col) use ($fechaDesde, $fechaHasta, &$params): string {
            $sql = '';
            if ($fechaDesde !== null && $fechaDesde !== '') {
                $sql .= " AND {$col} >= :fecha_desde";
                $params[':fecha_desde'] = $fechaDesde;
            }
            if ($fechaHasta !== null && $fechaHasta !== '') {
                $sql .= " AND {$col} <= :fecha_hasta";
                $params[':fecha_hasta'] = $fechaHasta;
            }
            return $sql;
        };

        // Filtro por un único documento (usado para recalcular su saldo real al
        // guardar un egreso — ver getSaldoPendienteDocumento): acota la búsqueda a
        // UN solo id en vez de traer hasta 300 filas para buscar una.
        $filtroSoloId = function (string $col) use ($soloId, &$params): string {
            if ($soloId === null) {
                return '';
            }
            $params[':solo_id'] = $soloId;
            return " AND {$col} = :solo_id";
        };

        // Solo aplica dentro del envoltorio de la rama ROL (única con varios
        // tipo_doc_bd mezclados por UNION); en COMPRA/LIQUIDACION el tipo ya
        // queda fijo por la rama elegida y no hace falta filtrarlo aparte.
        $filtroSoloTipoDocBd = '';
        if ($soloTipoDocBd !== null) {
            $filtroSoloTipoDocBd = " AND u.tipo_doc_bd = :solo_tipo_doc_bd";
            $params[':solo_tipo_doc_bd'] = $soloTipoDocBd;
        }

        if ($tipo === 'COMPRA') {
            // El listado solo ofrece compras que se pueden pagar. Al recalcular el saldo de UN
            // documento (getSaldoPendienteDocumento, $soloId) no se filtra el estado: el guardado
            // lo valida aparte con un mensaje claro (EgresoService::validarSaldoDocumentos) y la
            // edición de un egreso que ya pagaba esa compra debe seguir encontrando su saldo.
            $filtroPagable = $soloId === null
                ? ' AND ' . \App\Helpers\TiposComprobanteCompra::sqlCompraPagable('cb.estado')
                : '';

            $filtroBusq = '';
            if ($q !== '') {
                $filtroBusq = " AND (
                    CONCAT(cb.establecimiento_prov,'-',cb.punto_emision_prov,'-',cb.secuencial_prov) ILIKE :q
                    OR prov.razon_social  ILIKE :q
                    OR prov.identificacion ILIKE :q
                )";
                $params[':q'] = '%' . $q . '%';
            }

            $sql = "WITH pagado AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d
                        INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'COMPRA'
                          AND e.estado != 'anulado'
                          AND e.eliminado = FALSE
                          AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    ),
                    nc_nd AS (
                        SELECT nc.id_empresa, nc.id_proveedor, nc.documento_modificado,
                               SUM(CASE WHEN nc.tipo_comprobante = '04' THEN nc.importe_total ELSE 0 END) AS total_nc,
                               SUM(CASE WHEN nc.tipo_comprobante = '05' THEN nc.importe_total ELSE 0 END) AS total_nd
                        FROM compras_cabecera nc
                        WHERE nc.tipo_comprobante IN ('04','05')
                          AND nc.eliminado = FALSE
                          AND nc.id_empresa = :id_empresa
                        GROUP BY nc.id_empresa, nc.id_proveedor, nc.documento_modificado
                    ),
                    retenido_compra AS (
                        -- Cubre dos vías de enlace: id_compra directo (flujo normal) y
                        -- num_doc_sustento por dígitos (retenciones migradas, sin id_compra).
                        SELECT tmp.id_compra, SUM(tmp.monto) AS total_retenido
                        FROM (
                            SELECT r.id_compra, r.total_retenido AS monto, r.id AS id_ret
                            FROM retencion_compra_cabecera r
                            WHERE r.id_empresa = :id_empresa
                              AND r.eliminado = FALSE
                              AND r.id_compra IS NOT NULL
                              AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')

                            UNION

                            SELECT c2.id AS id_compra, r.total_retenido AS monto, r.id AS id_ret
                            FROM retencion_compra_cabecera r
                            JOIN compras_cabecera c2
                                 ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
                                    = regexp_replace(CONCAT(c2.establecimiento_prov,'-',c2.punto_emision_prov,'-',c2.secuencial_prov), '[^0-9]', '', 'g')
                                AND c2.id_empresa = r.id_empresa
                                AND c2.eliminado  = FALSE
                            WHERE r.id_empresa = :id_empresa
                              AND r.eliminado = FALSE
                              AND r.id_compra IS NULL
                              AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
                              AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
                        ) tmp
                        GROUP BY tmp.id_compra
                    )
                    SELECT 'COMPRA' AS tipo_doc_bd,
                           cb.id,
                           CONCAT(cb.establecimiento_prov,'-',cb.punto_emision_prov,'-',cb.secuencial_prov) AS numero_documento,
                           cb.fecha_emision,
                           0 AS dias_credito,
                           (cb.importe_total + COALESCE(cb.total_terceros, 0)) AS monto_total,
                           COALESCE(p.total_pagado, 0) AS monto_cobrado,
                           COALESCE(rcp.total_retenido, 0) AS monto_retenido,
                           (cb.importe_total + COALESCE(cb.total_terceros, 0) - COALESCE(p.total_pagado, 0) - COALESCE(rcp.total_retenido, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) AS saldo_pendiente,
                           prov.id             AS proveedor_id,
                           prov.razon_social   AS proveedor_nombre,
                           prov.identificacion AS proveedor_ruc
                    FROM compras_cabecera cb
                    INNER JOIN proveedores prov ON cb.id_proveedor = prov.id
                    LEFT  JOIN pagado p ON cb.id = p.id_referencia_documento
                    LEFT  JOIN retenido_compra rcp ON cb.id = rcp.id_compra
                    LEFT  JOIN nc_nd nn ON nn.id_empresa = cb.id_empresa
                                       AND nn.id_proveedor = cb.id_proveedor
                                       AND nn.documento_modificado = CONCAT(cb.establecimiento_prov,'-',cb.punto_emision_prov,'-',cb.secuencial_prov)
                    WHERE cb.id_empresa = :id_empresa
                      AND cb.eliminado = FALSE
                      AND COALESCE(cb.tipo_comprobante, '01') NOT IN ('04','05')
                      $filtroPagable
                      AND cb.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (cb.importe_total + COALESCE(cb.total_terceros, 0) - COALESCE(p.total_pagado, 0) - COALESCE(rcp.total_retenido, 0) - COALESCE(nn.total_nc, 0) + COALESCE(nn.total_nd, 0)) > 0.01
                      $filtroBusq
                      {$filtroFecha('cb.fecha_emision')}
                      {$filtroSoloId('cb.id')}
                    ORDER BY prov.razon_social ASC, cb.fecha_emision ASC
                    LIMIT 301";
        } elseif ($tipo === 'ROL') {
            // NÓMINA: líneas de rol pendientes + anticipos/préstamos (novedades) pendientes,
            // por empleado, en una sola lista (cada fila con su tipo_doc_bd).
            $filtroRol = '';
            $filtroAnt = '';
            $filtroPre = '';
            $filtroDc  = '';
            $filtroDt  = '';
            if ($q !== '') {
                $filtroRol = " AND (emp.nombres_apellidos ILIKE :q OR emp.identificacion ILIKE :q OR rc.descripcion ILIKE :q)";
                $filtroAnt = " AND (emp.nombres_apellidos ILIKE :q OR emp.identificacion ILIKE :q OR n.tipo_nombre ILIKE :q)";
                $filtroPre = " AND (emp.nombres_apellidos ILIKE :q OR emp.identificacion ILIKE :q OR n.tipo_nombre ILIKE :q)";
                $filtroDc  = " AND (emp.nombres_apellidos ILIKE :q OR emp.identificacion ILIKE :q)";
                $filtroDt  = " AND (emp.nombres_apellidos ILIKE :q OR emp.identificacion ILIKE :q)";
                $params[':q'] = '%' . $q . '%';
            }
            $sql = "WITH pagado_rol AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'ROL' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    ),
                    pagado_ant AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'ANTICIPO' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    ),
                    pagado_pre AS (
                        SELECT d.tipo_documento, d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'PRESTAMO9'
                          AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.tipo_documento, d.id_referencia_documento
                    ),
                    pagado_dc AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'DECIMO_CUARTO' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    ),
                    pagado_dt AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'DECIMO_TERCERO' AND e.estado != 'anulado' AND e.eliminado = FALSE AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    )
                    SELECT * FROM (
                        SELECT 'ROL' AS tipo_doc_bd,
                               rd.id,
                               (CASE rc.tipo_rol WHEN 'MENSUAL' THEN 'Rol Mensual' WHEN 'QUINCENA' THEN 'Quincena' WHEN 'SEMANAL' THEN 'Semanal' ELSE 'Rol' END)
                                   || ' ' || rc.periodo_mes || '/' || rc.periodo_anio AS numero_documento,
                               rc.fecha_pago AS fecha_emision,
                               0 AS dias_credito,
                               rd.neto AS monto_total,
                               COALESCE(p.total_pagado, 0) AS monto_cobrado,
                               (rd.neto - COALESCE(p.total_pagado, 0)) AS saldo_pendiente,
                               emp.id                AS proveedor_id,
                               emp.nombres_apellidos AS proveedor_nombre,
                               emp.identificacion    AS proveedor_ruc
                        FROM rol_detalle rd
                        INNER JOIN rol_cabecera rc ON rc.id = rd.id_rol
                        INNER JOIN empleados emp ON emp.id = rd.id_empleado
                        LEFT  JOIN pagado_rol p ON rd.id = p.id_referencia_documento
                        WHERE rd.id_empresa = :id_empresa
                          AND rc.eliminado = FALSE
                          AND rc.estado IN ('generado','pagado','contabilizado')
                          AND rc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                          AND (rd.neto - COALESCE(p.total_pagado, 0)) > 0.01
                          $filtroRol
                        UNION ALL
                        SELECT 'ANTICIPO' AS tipo_doc_bd,
                               n.id,
                               n.tipo_nombre || ' ' || n.periodo_mes || '/' || n.periodo_anio AS numero_documento,
                               n.fecha AS fecha_emision,
                               0 AS dias_credito,
                               n.valor AS monto_total,
                               COALESCE(pa.total_pagado, 0) AS monto_cobrado,
                               (n.valor - COALESCE(pa.total_pagado, 0)) AS saldo_pendiente,
                               emp.id                AS proveedor_id,
                               emp.nombres_apellidos AS proveedor_nombre,
                               emp.identificacion    AS proveedor_ruc
                        FROM novedades n
                        INNER JOIN empleados emp ON emp.id = n.id_empleado
                        LEFT  JOIN pagado_ant pa ON n.id = pa.id_referencia_documento
                        WHERE n.id_empresa = :id_empresa
                          AND n.eliminado = FALSE AND n.estado = 'activo'
                          AND n.tipo_codigo = '3'
                          AND n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                          AND (n.valor - COALESCE(pa.total_pagado, 0)) > 0.01
                          $filtroAnt
                        UNION ALL
                        -- PRÉSTAMOS AGRUPADOS: una línea por empleado+tipo = desembolso (suma de cuotas).
                        -- Solo tipo 9 (Préstamo Empresa): el 7 (Quirografario) y el 8 (Hipotecario)
                        -- los desembolsa el IESS/banco directo, no la empresa (ver
                        -- CatalogoNovedades::CODS_PRESTAMO).
                        SELECT ('PRESTAMO' || n.tipo_codigo) AS tipo_doc_bd,
                               n.id_empleado AS id,
                               MAX(n.tipo_nombre) || ' (desembolso)' AS numero_documento,
                               MIN(n.fecha) AS fecha_emision,
                               0 AS dias_credito,
                               SUM(n.valor) AS monto_total,
                               COALESCE(pp.total_pagado, 0) AS monto_cobrado,
                               (SUM(n.valor) - COALESCE(pp.total_pagado, 0)) AS saldo_pendiente,
                               emp.id                AS proveedor_id,
                               emp.nombres_apellidos AS proveedor_nombre,
                               emp.identificacion    AS proveedor_ruc
                        FROM novedades n
                        INNER JOIN empleados emp ON emp.id = n.id_empleado
                        LEFT  JOIN pagado_pre pp ON pp.tipo_documento = ('PRESTAMO' || n.tipo_codigo) AND pp.id_referencia_documento = n.id_empleado
                        WHERE n.id_empresa = :id_empresa
                          AND n.eliminado = FALSE AND n.estado = 'activo'
                          AND n.tipo_codigo = '9'
                          AND n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                          $filtroPre
                        GROUP BY n.id_empleado, n.tipo_codigo, emp.id, emp.nombres_apellidos, emp.identificacion, pp.total_pagado
                        HAVING (SUM(n.valor) - COALESCE(pp.total_pagado, 0)) > 0.01
                        UNION ALL
                        SELECT 'DECIMO_CUARTO' AS tipo_doc_bd,
                               dcd.id,
                               'Décimo Cuarto ' || dcc.anio || ' (' ||
                                   CASE WHEN dcc.region_grupo = 'sierra_amazonia' THEN 'Sierra/Amazonía' ELSE 'Costa/Insular' END
                               || ')' AS numero_documento,
                               dcc.fecha_emision,
                               0 AS dias_credito,
                               dcd.valor AS monto_total,
                               COALESCE(pdc.total_pagado, 0) AS monto_cobrado,
                               (dcd.valor - COALESCE(pdc.total_pagado, 0)) AS saldo_pendiente,
                               emp.id                AS proveedor_id,
                               emp.nombres_apellidos AS proveedor_nombre,
                               emp.identificacion    AS proveedor_ruc
                        FROM decimo_cuarto_detalle dcd
                        INNER JOIN decimo_cuarto_cabecera dcc ON dcc.id = dcd.id_cabecera
                        INNER JOIN empleados emp ON emp.id = dcd.id_empleado
                        LEFT  JOIN pagado_dc pdc ON pdc.id_referencia_documento = dcd.id
                        WHERE dcc.id_empresa = :id_empresa
                          AND dcc.eliminado = FALSE AND dcd.mensualiza = FALSE AND dcd.valor > 0.01
                          AND (dcd.valor - COALESCE(pdc.total_pagado, 0)) > 0.01
                          $filtroDc
                        UNION ALL
                        SELECT 'DECIMO_TERCERO' AS tipo_doc_bd,
                               dtd.id,
                               'Décimo Tercero ' || dtc.anio AS numero_documento,
                               dtc.fecha_emision,
                               0 AS dias_credito,
                               dtd.valor AS monto_total,
                               COALESCE(pdt.total_pagado, 0) AS monto_cobrado,
                               (dtd.valor - COALESCE(pdt.total_pagado, 0)) AS saldo_pendiente,
                               emp.id                AS proveedor_id,
                               emp.nombres_apellidos AS proveedor_nombre,
                               emp.identificacion    AS proveedor_ruc
                        FROM decimo_tercero_detalle dtd
                        INNER JOIN decimo_tercero_cabecera dtc ON dtc.id = dtd.id_cabecera
                        INNER JOIN empleados emp ON emp.id = dtd.id_empleado
                        LEFT  JOIN pagado_dt pdt ON pdt.id_referencia_documento = dtd.id
                        WHERE dtc.id_empresa = :id_empresa
                          AND dtc.eliminado = FALSE AND dtd.mensualiza = FALSE AND dtd.valor > 0.01
                          AND (dtd.valor - COALESCE(pdt.total_pagado, 0)) > 0.01
                          $filtroDt
                    ) u
                    WHERE TRUE
                      {$filtroFecha('u.fecha_emision')}
                      {$filtroSoloId('u.id')}
                      $filtroSoloTipoDocBd
                    ORDER BY proveedor_nombre ASC, numero_documento ASC
                    LIMIT 301";
        } else {
            // LIQUIDACION
            $filtroBusq = '';
            if ($q !== '') {
                $filtroBusq = " AND (
                    CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) ILIKE :q
                    OR prov.razon_social  ILIKE :q
                    OR prov.identificacion ILIKE :q
                )";
                $params[':q'] = '%' . $q . '%';
            }

            $sql = "WITH pagado AS (
                        SELECT d.id_referencia_documento, SUM(d.monto_pagado) AS total_pagado
                        FROM egresos_detalle d
                        INNER JOIN egresos_cabecera e ON d.id_egreso = e.id
                        WHERE d.tipo_documento = 'LIQUIDACION'
                          AND e.estado != 'anulado'
                          AND e.eliminado = FALSE
                          AND d.eliminado = FALSE
                          $excluirSql
                        GROUP BY d.id_referencia_documento
                    ),
                    retenido_liq AS (
                        -- Cubre dos vías de enlace: id_liquidacion directo (flujo normal) y
                        -- num_doc_sustento por dígitos (retenciones migradas, sin id_liquidacion).
                        SELECT tmp.id_liquidacion, SUM(tmp.monto) AS total_retenido
                        FROM (
                            SELECT r.id_liquidacion, r.total_retenido AS monto, r.id AS id_ret
                            FROM retencion_compra_cabecera r
                            WHERE r.id_empresa = :id_empresa
                              AND r.eliminado = FALSE
                              AND r.id_liquidacion IS NOT NULL
                              AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')

                            UNION

                            SELECT l2.id AS id_liquidacion, r.total_retenido AS monto, r.id AS id_ret
                            FROM retencion_compra_cabecera r
                            JOIN liquidaciones_cabecera l2
                                 ON regexp_replace(r.num_doc_sustento, '[^0-9]', '', 'g')
                                    = regexp_replace(CONCAT(l2.establecimiento, '-', l2.punto_emision, '-', l2.secuencial), '[^0-9]', '', 'g')
                                AND l2.id_empresa = r.id_empresa
                                AND l2.eliminado  = FALSE
                            WHERE r.id_empresa = :id_empresa
                              AND r.eliminado = FALSE
                              AND r.id_liquidacion IS NULL
                              AND r.id_compra IS NULL
                              AND r.num_doc_sustento IS NOT NULL AND r.num_doc_sustento <> ''
                              AND UPPER(r.estado) NOT IN ('ANULADO','ANULADA','BORRADOR','PENDIENTE')
                        ) tmp
                        GROUP BY tmp.id_liquidacion
                    )
                    SELECT 'LIQUIDACION' AS tipo_doc_bd,
                           l.id,
                           CONCAT(l.establecimiento,'-',l.punto_emision,'-',l.secuencial) AS numero_documento,
                           l.fecha_emision,
                           0 AS dias_credito,
                           l.importe_total AS monto_total,
                           COALESCE(p.total_pagado, 0) AS monto_cobrado,
                           COALESCE(rl.total_retenido, 0) AS monto_retenido,
                           (l.importe_total - COALESCE(p.total_pagado, 0) - COALESCE(rl.total_retenido, 0)) AS saldo_pendiente,
                           prov.id             AS proveedor_id,
                           prov.razon_social   AS proveedor_nombre,
                           prov.identificacion AS proveedor_ruc
                    FROM liquidaciones_cabecera l
                    INNER JOIN proveedores prov ON l.id_proveedor = prov.id
                    LEFT  JOIN pagado p ON l.id = p.id_referencia_documento
                    LEFT  JOIN retenido_liq rl ON l.id = rl.id_liquidacion
                    WHERE l.id_empresa = :id_empresa
                      AND l.eliminado = FALSE
                      AND l.estado = 'autorizado'
                      AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                      AND (l.importe_total - COALESCE(p.total_pagado, 0) - COALESCE(rl.total_retenido, 0)) > 0.01
                      $filtroBusq
                      {$filtroFecha('l.fecha_emision')}
                      {$filtroSoloId('l.id')}
                    ORDER BY prov.razon_social ASC, l.fecha_emision ASC
                    LIMIT 301";
        }

        $rows    = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 300;
        return ['data' => array_slice($rows, 0, 300), 'has_more' => $hasMore];
    }

    /**
     * Bloqueo de concurrencia (CLAUDE.md §8) para el documento que un egreso está
     * a punto de pagar: se libera solo al COMMIT/ROLLBACK de la transacción en
     * curso. Llamar ANTES de leer el saldo con getSaldoPendienteDocumento(), y
     * mantener la transacción abierta hasta el INSERT del detalle.
     */
    public function lockDocumentoPago(string $tipoDocumento, int $idReferencia, int $idEmpresa): void
    {
        $this->query(
            "SELECT pg_advisory_xact_lock(hashtext('egreso_doc:' || :id_empresa || ':' || :tipo || ':' || :id))",
            [':id_empresa' => $idEmpresa, ':tipo' => $tipoDocumento, ':id' => $idReferencia]
        );
    }

    /**
     * Saldo pendiente REAL de un documento específico, recalculado en el momento
     * (no confiar en el "saldo_anterior" que envía el navegador: pudo haberse
     * pagado desde otro egreso mientras el modal seguía abierto). Reutiliza el
     * mismo query que el buscador de documentos pendientes, acotado a un solo id,
     * para no duplicar las fórmulas de retenciones/NC/ND por tipo de documento.
     * Devuelve 0.0 si el documento ya no tiene saldo pendiente (o no existe).
     */
    public function getSaldoPendienteDocumento(string $tipoDocumento, int $idReferencia, int $idEmpresa, ?int $excluirEgresoId = null): float
    {
        $categoria = match ($tipoDocumento) {
            'COMPRA'      => 'COMPRA',
            'LIQUIDACION' => 'LIQUIDACION',
            default       => 'ROL', // ROL, ANTICIPO, PRESTAMO7/8/9, DECIMO_CUARTO, DECIMO_TERCERO
        };
        $soloTipo = $categoria === 'ROL' ? $tipoDocumento : null;

        $resultado = $this->buscarDocumentosPendientesEgreso(
            $idEmpresa, '', $categoria, $excluirEgresoId, null, null, $idReferencia, $soloTipo
        );
        foreach ($resultado['data'] as $row) {
            if ($row['tipo_doc_bd'] === $tipoDocumento && (int) $row['id'] === $idReferencia) {
                return (float) $row['saldo_pendiente'];
            }
        }
        return 0.0;
    }

    /**
     * Estado de una compra de la empresa, para validar si se puede pagar
     * (EgresoRules::validarCompraPagable). null si no existe o fue eliminada.
     */
    public function getEstadoCompra(int $idCompra, int $idEmpresa): ?string
    {
        $row = $this->query(
            "SELECT COALESCE(estado, '') AS estado FROM compras_cabecera
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE",
            [':id' => $idCompra, ':id_empresa' => $idEmpresa]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['estado'] : null;
    }

    public function getUltimoNumeroCheque(int $idFormaPago): ?string
    {
        $sql = "SELECT numero_cheque FROM egresos_pagos
                WHERE id_forma_pago = :fp AND numero_cheque IS NOT NULL AND numero_cheque <> ''
                ORDER BY id DESC LIMIT 1";
        $res = $this->query($sql, [':fp' => $idFormaPago])->fetchColumn();
        return $res ?: null;
    }
}
