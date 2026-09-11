<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Repository centralizado para la gestión de secuenciales de documentos electrónicos.
 * 
 * Responsabilidades:
 * - Obtener la configuración del secuencial inicial por punto de emisión y tipo de documento.
 * - Obtener los secuenciales ya utilizados en las tablas de documentos.
 * - Detectar huecos (gaps) en la numeración.
 */
class SecuencialRepository
{
    protected PDO $db;

    /**
     * Mapeo de tipo_documento → [tabla, columna_secuencial, columna_punto_emision]
     * Permite agregar nuevos tipos de documentos simplemente añadiendo una entrada.
     */
    private const DOCUMENT_MAP = [
        'Facturas de venta'                    => ['tabla' => 'ventas_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Recibos de venta'                     => ['tabla' => 'recibos_venta_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Nota de crédito'                      => ['tabla' => 'notas_credito_cabecera',  'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Nota de débito'                       => ['tabla' => 'nota_debito_cabecera',    'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Facturas de reembolso'                => ['tabla' => 'factura_reembolso_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Retenciones de compras'               => ['tabla' => 'retencion_compra_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Guía de remisión'                     => ['tabla' => 'guias_remision_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Liquidación de compras o servicios'   => ['tabla' => 'liquidaciones_cabecera',  'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Proformas'                            => ['tabla' => 'proformas_cabecera',      'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ingresos'                             => ['tabla' => 'ingresos_cabecera',       'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Egresos'                              => ['tabla' => 'egresos_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Traspasos'                            => ['tabla' => 'traspasos_cabecera',      'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Pedidos'                              => ['tabla' => 'pedidos_cabecera',        'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Órdenes de compra'                    => ['tabla' => 'ordenes_compra',           'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Consignaciones ventas'                => ['tabla' => 'consignaciones_ventas',   'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Retornos consignaciones ventas'       => ['tabla' => 'retornos_cv',             'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Facturacion consignaciones ventas'    => ['tabla' => 'consignaciones_facturas', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes car-wash'                     => ['tabla' => 'carwash_ordenes',         'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes de taller'                    => ['tabla' => 'taller_ordenes',          'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Ordenes servicio externo'             => ['tabla' => 'servicioexterno_ordenes', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Cambios de productos'                 => ['tabla' => 'cambios_producto_cv',    'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
        'Importaciones'                        => ['tabla' => 'importaciones_cabecera', 'col_sec' => 'secuencial', 'col_punto' => 'id_punto_emision'],
    ];

    /**
     * Tablas de comprobantes electrónicos → tipo_comprobante con el que quedan en
     * sri_envio_log. Solo estas pueden tener secuenciales "quemados" en el SRI.
     */
    private const TABLA_A_TIPO_SRI_LOG = [
        'ventas_cabecera'            => 'factura_venta',
        'notas_credito_cabecera'     => 'nota_credito',
        'nota_debito_cabecera'       => 'nota_debito',
        'factura_reembolso_cabecera' => 'factura_reembolso',
        'retencion_compra_cabecera'  => 'retencion_compra',
        'guias_remision_cabecera'    => 'guia_remision',
        'liquidaciones_cabecera'     => 'liquidacion_compra',
    ];

    /**
     * Acciones de sri_envio_log que prueban que el SRI retuvo la clave (y con ella el
     * secuencial) en ese ambiente. 'devuelta' y 'enviando' no cuentan: la primera es un
     * rechazo en recepción (el SRI no guarda nada) y la segunda se escribe antes de saber
     * el resultado. 'no_autorizado' sí cuenta: es conservador, pero un hueco de más no
     * cuesta nada frente a un "ERROR 45 SECUENCIAL REGISTRADO".
     */
    private const ACCIONES_SRI_RETIENE_SECUENCIAL = "'recibida','en_procesamiento','autorizado','autorizada','no_autorizado','no_autorizada'";

    /**
     * Tipos de documento que, ante el SRI, comparten el MISMO codDoc (Tabla 3) y
     * por tanto NO PUEDEN compartir el mismo punto de emisión: la numeración
     * estab-ptoEmi-secuencial debe ser única por (establecimiento, punto, codDoc),
     * y cada tipo de este listado lleva su propio contador independiente en
     * `empresa_secuencial` — si dos tipos de la misma familia comparten punto,
     * se generarían dos comprobantes distintos con el mismo número de documento.
     * Ej.: "Facturas de venta" y "Facturas de reembolso" son ambas codDoc=01
     * (el "reembolso" código 41 del ATS es solo un sub-campo del mismo XML de
     * Factura, ver XmlFacturaReembolsoService — no cambia el codDoc).
     */
    private const FAMILIAS_CODDOC = [
        '01' => ['Facturas de venta', 'Facturas de reembolso'],
    ];

    /**
     * Tipos de documento que solo pueden tener UN punto de emisión por empresa
     * (no varios, a diferencia del resto de tipos que sí pueden repetirse en
     * distintos puntos — p. ej. "Facturas de venta" en varias cajas). Hoy:
     * "Facturas de reembolso" — el sistema busca/crea un único punto dedicado
     * al dar de alta la empresa (ver
     * EmpresaInicializadorService::obtenerOCrearPuntoEmisionReembolso) y el
     * resto del flujo de Reembolso asume que existe solo uno.
     */
    private const TIPOS_PUNTO_UNICO = ['Facturas de reembolso'];

    /**
     * Tipos de documento que NO pueden numerar por fecha de emisión (Empresa →
     * Secuenciales, modo 'por_fecha'): son los que se envían al SRI, es decir los
     * que tienen su propio XmlXxxService y guardan clave_acceso. Su secuencial
     * forma parte de esa clave de acceso y la numeración por establecimiento +
     * punto + codDoc no admite reinicios: reiniciarla cada año o cada mes rompería
     * la serie declarada ante el SRI.
     *
     * El resto de tipos de DOCUMENT_MAP son documentos internos y SÍ pueden elegir
     * el modo. Se define por exclusión a propósito: un tipo nuevo que se agregue a
     * DOCUMENT_MAP nace pudiendo numerar por fecha, y solo hay que tocarlo aquí si
     * llega a ser electrónico.
     */
    private const TIPOS_SIN_MODO_PERIODO = [
        'Facturas de venta',
        'Facturas de reembolso',
        'Nota de crédito',
        'Nota de débito',
        'Guía de remisión',
        'Liquidación de compras o servicios',
        'Retenciones de compras',
    ];

    /** Modos de numeración admitidos en `empresa_secuencial.modo_numeracion`. */
    public const MODO_CONSECUTIVO = 'consecutivo';
    public const MODO_POR_FECHA   = 'por_fecha';

    /** Periodos de reinicio admitidos en `empresa_secuencial.periodo_reinicio`. */
    public const PERIODO_ANUAL   = 'anual';
    public const PERIODO_MENSUAL = 'mensual';

    /**
     * Largo objetivo del secuencial, el mismo formato canónico de siempre
     * (App\Helpers\SecuencialFormato::LONGITUD).
     *
     * En modo 'por_fecha' el número se arma CONCATENANDO el prefijo del periodo y el
     * correlativo, y el correlativo ocupa lo que sobre de estos 9 dígitos:
     *     anual   → AAAA   (4) + 5 dígitos →  202600017
     *     mensual → AAAAMM (6) + 3 dígitos →  202609001
     * Si un periodo agota esos dígitos, el correlativo NO invade el periodo siguiente:
     * el número simplemente crece (202609 + 1000 → 2026091000). Por eso se concatena en
     * vez de sumar — con aritmética, el documento 1000 de septiembre habría caído justo
     * encima de la numeración de octubre.
     */
    private const LONGITUD_SECUENCIAL = 9;

    /**
     * Techo del modo 'consecutivo' en los tipos que admiten numeración por fecha.
     *
     * POR QUÉ EXISTE: un punto que estuvo en modo 'por_fecha' deja números de 9
     * dígitos con prefijo de periodo (202600017). Si después se vuelve a
     * 'consecutivo', el generador vería ese 202600017 como el máximo usado y
     * seguiría desde 202600018 en vez de retomar el 18 que llevaba. Acotando la
     * búsqueda por debajo de este techo, los dos modos conviven sin pisarse y el
     * cambio de modo es reversible. Un consecutivo corrido nunca llega a los 100
     * millones de documentos en una misma serie.
     */
    private const TECHO_CONSECUTIVO = 99999999;

    /**
     * Agrupación por área de los tipos de documento soportados (solo para mostrar
     * en la ayuda de la pestaña Secuenciales). Los nombres deben coincidir EXACTO
     * con las claves de DOCUMENT_MAP.
     */
    private const DOCUMENT_AREAS = [
        'Ventas'         => ['Facturas de venta', 'Recibos de venta', 'Nota de crédito', 'Nota de débito', 'Facturas de reembolso', 'Proformas', 'Guía de remisión'],
        'Compras'        => ['Retenciones de compras', 'Liquidación de compras o servicios', 'Órdenes de compra', 'Importaciones'],
        'Tesorería'      => ['Ingresos', 'Egresos', 'Traspasos'],
        'Operativos'     => ['Pedidos', 'Cambios de productos', 'Ordenes car-wash', 'Ordenes de taller', 'Ordenes servicio externo'],
        'Consignaciones' => ['Consignaciones ventas', 'Retornos consignaciones ventas', 'Facturacion consignaciones ventas'],
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Tipos soportados agrupados por área para la tarjeta de ayuda.
     * Cualquier tipo de DOCUMENT_MAP no clasificado cae en "Otros".
     *
     * @return array<string, string[]>
     */
    public function getTiposDocumentoAgrupados(): array
    {
        $grupos = self::DOCUMENT_AREAS;
        $clasificados = array_merge(...array_values(self::DOCUMENT_AREAS));
        $otros = array_values(array_diff(array_keys(self::DOCUMENT_MAP), $clasificados));
        if (!empty($otros)) {
            $grupos['Otros'] = $otros;
        }
        return $grupos;
    }

    /**
     * Mapa tipoDocumento => [otros tipos en conflicto de codDoc], solo para los
     * tipos que SÍ tienen algún conflicto (para pintar la ayuda/validación en el
     * frontend de Empresa → Secuenciales sin duplicar la lista en la vista).
     */
    public function getMapaConflictosCodDoc(): array
    {
        $mapa = [];
        foreach (self::FAMILIAS_CODDOC as $familia) {
            foreach ($familia as $tipo) {
                $mapa[$tipo] = array_values(array_diff($familia, [$tipo]));
            }
        }
        return $mapa;
    }

    /**
     * ¿Este tipo de documento puede numerar por fecha de emisión (modo 'por_fecha')?
     * Falso para los electrónicos, ver TIPOS_SIN_MODO_PERIODO.
     */
    public function tipoPermiteModoPeriodo(string $tipoDocumento): bool
    {
        return isset(self::DOCUMENT_MAP[$tipoDocumento])
            && !in_array($tipoDocumento, self::TIPOS_SIN_MODO_PERIODO, true);
    }

    /**
     * Tipos de documento que sí ofrecen la opción de numerar por fecha, para que la
     * pestaña Empresa → Secuenciales sepa en qué filas pintar los selectores sin
     * duplicar la lista en la vista.
     *
     * @return string[]
     */
    public function getTiposConModoPeriodo(): array
    {
        return array_values(array_filter(
            array_keys(self::DOCUMENT_MAP),
            fn(string $t): bool => $this->tipoPermiteModoPeriodo($t)
        ));
    }

    /**
     * Prefijo de periodo (texto) que le corresponde a una fecha de emisión: el año con
     * 4 dígitos en modo anual (`2026`), y el año más el mes con 2 dígitos en mensual
     * (`202609`). Es lo que antecede al correlativo.
     *
     * Una fecha vacía o ilegible cae en la de hoy: es preferible numerar en el
     * periodo corriente a dejar al documento sin número.
     */
    public function getPrefijoPeriodo(?string $fecha, string $periodo): string
    {
        $ts = ($fecha !== null && trim($fecha) !== '') ? strtotime($fecha) : false;
        if ($ts === false) {
            $ts = time();
        }

        return $periodo === self::PERIODO_MENSUAL
            ? date('Ym', $ts)
            : date('Y', $ts);
    }

    /**
     * Cuántos dígitos le quedan al correlativo después del prefijo, para completar los 9
     * del formato canónico: 5 en anual, 3 en mensual. Es el ancho con el que se rellena
     * con ceros, no un tope: un correlativo más grande simplemente ocupa más dígitos.
     */
    public function getDigitosCorrelativo(string $prefijo): int
    {
        return max(1, self::LONGITUD_SECUENCIAL - strlen($prefijo));
    }

    /**
     * Arma el secuencial final concatenando el prefijo del periodo con el correlativo,
     * rellenado a la izquierda: ('202609', 1) → '202609001'; ('2026', 17) → '202600017'.
     *
     * Si el correlativo ya no cabe en los dígitos disponibles, se concatena tal cual y el
     * número crece (('202609', 1000) → '2026091000') en vez de desbordar sobre el periodo
     * siguiente. Por eso las columnas `secuencial` admiten más de 9 caracteres
     * (ver 20260909_secuencial_modo_periodo.sql).
     */
    public function componerSecuencialPeriodo(string $prefijo, int $correlativo): string
    {
        return $prefijo . str_pad((string) $correlativo, $this->getDigitosCorrelativo($prefijo), '0', STR_PAD_LEFT);
    }

    /** Techo de la numeración consecutiva en tipos que admiten modo por fecha (ver TECHO_CONSECUTIVO). */
    public function getTechoConsecutivo(): int
    {
        return self::TECHO_CONSECUTIVO;
    }

    /** Otros tipos de DOCUMENT_MAP que comparten codDoc SRI con $tipoDocumento (ver FAMILIAS_CODDOC). */
    public function tiposEnConflictoCodDoc(string $tipoDocumento): array
    {
        foreach (self::FAMILIAS_CODDOC as $familia) {
            if (in_array($tipoDocumento, $familia, true)) {
                return array_values(array_diff($familia, [$tipoDocumento]));
            }
        }
        return [];
    }

    /** Tipos de documento que solo pueden existir en un único punto de emisión por empresa (ver TIPOS_PUNTO_UNICO). */
    public function getTiposPuntoUnico(): array
    {
        return self::TIPOS_PUNTO_UNICO;
    }

    /**
     * Para un tipo de TIPOS_PUNTO_UNICO: el id del punto de emisión (de esta
     * empresa) que ya lo tiene configurado, excluyendo $idPuntoExcluir. Null si
     * el tipo no está en TIPOS_PUNTO_UNICO o si ningún otro punto lo tiene.
     * Usar antes de crear ese tipo en un punto nuevo: si devuelve un id, no
     * debe ofrecerse/crearse ahí, porque el resto del sistema asume un único
     * punto para ese tipo.
     */
    public function getPuntoConTipoUnico(string $tipoDocumento, int $idEmpresa, int $idPuntoExcluir = 0): ?int
    {
        if (!in_array($tipoDocumento, self::TIPOS_PUNTO_UNICO, true)) {
            return null;
        }

        // JOIN contra empresa_punto_emision (y no solo empresa_secuencial.eliminado):
        // un punto eliminado puede dejar secuenciales huérfanos si se borró antes de
        // que deletePuntoEmision() diera de baja también sus secuenciales (datos ya
        // en producción de antes de ese fix) — sin este JOIN, ese huérfano seguiría
        // bloqueando el tipo en cualquier punto nuevo aunque el original ya no exista.
        $sql = "SELECT es.id_punto_emision
                  FROM empresa_secuencial es
                  INNER JOIN empresa_punto_emision pe ON pe.id = es.id_punto_emision
                 WHERE es.id_empresa = :id_empresa AND es.tipo_documento = :tipo
                   AND es.eliminado = false AND pe.eliminado = false
                   AND es.id_punto_emision <> :id_punto_excluir
                 LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'        => $idEmpresa,
            ':tipo'              => $tipoDocumento,
            ':id_punto_excluir'  => $idPuntoExcluir,
        ]);
        $row = $st->fetchColumn();
        return $row !== false ? (int) $row : null;
    }

    /**
     * ¿El punto de emisión ya tiene configurado (activo) algún tipo de documento
     * que comparta codDoc SRI con $tipoDocumento? Si es así, devuelve el nombre
     * del tipo en conflicto; si no, null. Usar antes de crear una nueva
     * configuración de secuencial para no duplicar numeración ante el SRI.
     */
    public function getConflictoCodDoc(int $idPuntoEmision, string $tipoDocumento, int $idEmpresa): ?string
    {
        $conflictivos = $this->tiposEnConflictoCodDoc($tipoDocumento);
        if ($conflictivos === []) {
            return null;
        }

        $placeholders = [];
        $params = [':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa];
        foreach ($conflictivos as $i => $tipo) {
            $ph = ":t{$i}";
            $placeholders[] = $ph;
            $params[$ph] = $tipo;
        }

        $sql = "SELECT tipo_documento FROM empresa_secuencial
                WHERE id_punto_emision = :id_punto
                  AND id_empresa = :id_empresa
                  AND eliminado = false
                  AND tipo_documento IN (" . implode(',', $placeholders) . ")
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['tipo_documento'] : null;
    }

    /**
     * Bloqueo transaccional (se libera solo al COMMIT/ROLLBACK) por punto de emisión + tipo de
     * documento. DEBE llamarse antes de calcular el siguiente secuencial (obtenerSiguienteSecuencial),
     * dentro de la MISMA transacción que luego inserta la cabecera del documento — igual que
     * InventarioRepository::lockStock() (ver CLAUDE.md §8). Sin esto, dos documentos emitidos casi
     * al mismo tiempo pueden calcular el mismo "siguiente número" antes de que ninguno lo inserte.
     * No hace falta id_empresa: id_punto_emision ya es único por sí solo.
     */
    public function lockSecuencial(int $idPuntoEmision, string $tipoDocumento): void
    {
        $sql = "SELECT pg_advisory_xact_lock(hashtext('secuencial:' || :p || ':' || :t))";
        $st = $this->db->prepare($sql);
        $st->execute([':p' => $idPuntoEmision, ':t' => $tipoDocumento]);
    }

    /**
     * Serie por defecto de la empresa: establecimiento ACTIVO + punto de emisión ACTIVO de menor
     * código, EXCLUYENDO el punto dedicado a "Facturas de reembolso" (ver TIPOS_PUNTO_UNICO): ese
     * punto existe solo para esa familia de codDoc y no debe recibir documentos de otros tipos.
     *
     * Es la serie que se asigna a los documentos que NO traen serie propia — hoy, los migrados del
     * sistema anterior cuyo origen no guarda establecimiento/punto (ingresos, egresos, pedidos,
     * cambios de producto). Los documentos autorizados por el SRI SIEMPRE traen la suya y nunca
     * deben pasar por aquí.
     *
     * Los activos van primero en el ORDER BY (en vez de filtrarse con WHERE) para que una empresa
     * cuyos establecimientos/puntos estén marcados inactivos siga obteniendo una serie en lugar de
     * quedarse sin ninguna.
     *
     * @return array{id_establecimiento:int,establecimiento:string,id_punto_emision:int,punto_emision:string}|null
     */
    public function getSerieDefecto(int $idEmpresa): ?array
    {
        $sql = "SELECT e.id AS id_establecimiento,
                       LPAD(REGEXP_REPLACE(e.codigo, '[^0-9]', '', 'g'), 3, '0')       AS establecimiento,
                       p.id AS id_punto_emision,
                       LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') AS punto_emision
                  FROM empresa_punto_emision p
                  INNER JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE e.id_empresa = :id_empresa
                   AND e.eliminado = false
                   AND p.eliminado = false
                   AND NOT EXISTS (
                        SELECT 1 FROM empresa_secuencial s
                         WHERE s.id_punto_emision = p.id
                           AND s.eliminado = false
                           AND s.tipo_documento = :tipo_reembolso
                   )
                 ORDER BY CASE WHEN LOWER(COALESCE(e.estado, '')) = 'activo' THEN 0 ELSE 1 END,
                          CASE WHEN LOWER(COALESCE(p.estado, '')) = 'activo' THEN 0 ELSE 1 END,
                          LPAD(REGEXP_REPLACE(e.codigo, '[^0-9]', '', 'g'), 3, '0') ASC,
                          LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') ASC,
                          p.id ASC
                 LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa'     => $idEmpresa,
            ':tipo_reembolso' => 'Facturas de reembolso',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id_establecimiento' => (int) $row['id_establecimiento'],
            'establecimiento'    => (string) $row['establecimiento'],
            'id_punto_emision'   => (int) $row['id_punto_emision'],
            'punto_emision'      => (string) $row['punto_emision'],
        ];
    }

    /**
     * Serie de un punto de emisión concreto, validando que pertenezca a la empresa indicada.
     * Devuelve null si el punto no existe, está eliminado o es de otra empresa.
     *
     * Úsese al guardar un documento para derivar `establecimiento`/`punto_emision` del punto
     * REAL en vez de confiar en lo que mande el navegador: así la serie de texto nunca queda
     * desalineada de `id_punto_emision`, que es por donde se cuentan los números ya usados.
     *
     * La empresa se resuelve por el establecimiento (`empresa_establecimiento.id_empresa`), que
     * es el vínculo real de la jerarquía, no por la copia que lleva el propio punto.
     *
     * Las claves redundantes (`id`/`id_punto`, `establecimiento`/`cod_establecimiento`,
     * `punto`/`codigo_punto`) existen porque los módulos que consumen este dato fueron escritos
     * con nombres distintos; así todos comparten la consulta sin tocar sus vistas.
     *
     * @return array{id:int,id_punto:int,id_establecimiento:int,establecimiento:string,cod_establecimiento:string,punto:string,codigo_punto:string,nombre:string}|null
     */
    public function getPuntoEmisionSerie(int $idPuntoEmision, int $idEmpresa): ?array
    {
        $sql = "SELECT p.id,
                       p.id_establecimiento,
                       LPAD(REGEXP_REPLACE(e.codigo,        '[^0-9]', '', 'g'), 3, '0') AS establecimiento,
                       LPAD(REGEXP_REPLACE(p.codigo_punto,  '[^0-9]', '', 'g'), 3, '0') AS punto,
                       COALESCE(p.nombre, '') AS nombre
                  FROM empresa_punto_emision p
                  INNER JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id = :id_punto
                   AND e.id_empresa = :id_empresa
                   AND p.eliminado = false
                   AND e.eliminado = false
                 LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id'                  => (int) $row['id'],
            'id_punto'            => (int) $row['id'],
            'id_establecimiento'  => (int) $row['id_establecimiento'],
            'establecimiento'     => (string) $row['establecimiento'],
            'cod_establecimiento' => (string) $row['establecimiento'],
            'punto'               => (string) $row['punto'],
            'codigo_punto'        => (string) $row['punto'],
            'id_estab'            => (int) $row['id_establecimiento'],
            'estab'               => (string) $row['establecimiento'],
            'nombre'              => (string) $row['nombre'],
        ];
    }

    /**
     * Puntos de emisión de una empresa, con su serie ya normalizada. Fuente única para los
     * selectores de "Serie" de todos los módulos.
     *
     * Devuelve cada punto con claves redundantes a propósito (`id`/`id_punto`,
     * `establecimiento`/`cod_establecimiento`, `punto`/`codigo_punto`) porque los módulos que
     * consumen esta lista fueron escritos con nombres distintos; así todos leen la misma
     * consulta sin tener que tocar sus vistas y su JS.
     *
     * @param bool $soloActivos Excluye los puntos inhabilitados (estado <> 'activo').
     * @return array<int, array{id:int,id_punto:int,id_establecimiento:int,establecimiento:string,cod_establecimiento:string,punto:string,codigo_punto:string,nombre:string}>
     */
    public function getPuntosEmisionSerie(int $idEmpresa, bool $soloActivos = false): array
    {
        $filtroEstado = $soloActivos ? " AND LOWER(COALESCE(p.estado, '')) = 'activo'" : '';

        $sql = "SELECT p.id,
                       p.id_establecimiento,
                       LPAD(REGEXP_REPLACE(e.codigo,       '[^0-9]', '', 'g'), 3, '0') AS establecimiento,
                       LPAD(REGEXP_REPLACE(p.codigo_punto, '[^0-9]', '', 'g'), 3, '0') AS punto,
                       COALESCE(p.nombre, '') AS nombre
                  FROM empresa_punto_emision p
                  INNER JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE e.id_empresa = :id_empresa
                   AND p.eliminado = false
                   AND e.eliminado = false
                   {$filtroEstado}
                 ORDER BY establecimiento, punto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id'                  => (int) $row['id'],
                'id_punto'            => (int) $row['id'],
                'id_establecimiento'  => (int) $row['id_establecimiento'],
                'establecimiento'     => (string) $row['establecimiento'],
                'cod_establecimiento' => (string) $row['establecimiento'],
                'punto'               => (string) $row['punto'],
                'codigo_punto'        => (string) $row['punto'],
                'id_estab'            => (int) $row['id_establecimiento'],
                'estab'               => (string) $row['establecimiento'],
                'nombre'              => (string) $row['nombre'],
            ];
        }
        return $out;
    }

    /**
     * ¿La base ya tiene las columnas de modo de numeración (migración
     * 20260909_secuencial_modo_periodo.sql)?
     *
     * El código puede desplegarse antes de que se ejecute ese script: mientras eso
     * pase, todo se comporta como siempre (modo consecutivo) en vez de reventar con
     * "column does not exist". Se consulta una sola vez por petición.
     */
    public function soportaModoPeriodo(): bool
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $sql = "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = 'public'
                   AND table_name = 'empresa_secuencial'
                   AND column_name IN ('modo_numeracion', 'periodo_reinicio')";

        try {
            $cache = ((int) $this->db->query($sql)->fetchColumn()) === 2;
        } catch (\Throwable) {
            $cache = false;
        }

        return $cache;
    }

    /**
     * Obtiene la configuración del secuencial para un punto de emisión y tipo de documento:
     * el secuencial_inicial configurado y cómo debe numerarse (modo y periodo de reinicio).
     *
     * Un tipo sin fila configurada, o uno que no admite numeración por fecha (electrónicos,
     * ver TIPOS_SIN_MODO_PERIODO), siempre sale como 'consecutivo'.
     *
     * @return array{id:int|null,secuencial_inicial:int,modo_numeracion:string,periodo_reinicio:string|null}
     */
    public function getConfigSecuencial(int $idPuntoEmision, string $tipoDocumento): array
    {
        $colsModo = $this->soportaModoPeriodo()
            ? ", COALESCE(modo_numeracion, '" . self::MODO_CONSECUTIVO . "') AS modo_numeracion, periodo_reinicio"
            : ", '" . self::MODO_CONSECUTIVO . "' AS modo_numeracion, NULL AS periodo_reinicio";

        $sql = "SELECT id, COALESCE(secuencial_inicial, 1) AS secuencial_inicial {$colsModo}
                FROM empresa_secuencial
                WHERE id_punto_emision = :id_punto
                  AND tipo_documento = :tipo
                  AND eliminado = false
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo'     => $tipoDocumento,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'id'                 => null,
                'secuencial_inicial' => 1,
                'modo_numeracion'    => self::MODO_CONSECUTIVO,
                'periodo_reinicio'   => null,
            ];
        }

        // Un tipo electrónico configurado por fecha (dato viejo o tocado a mano en la
        // base) se ignora aquí: su numeración forma la clave de acceso del SRI.
        $modo = (string) ($row['modo_numeracion'] ?? self::MODO_CONSECUTIVO);
        if ($modo === self::MODO_POR_FECHA && !$this->tipoPermiteModoPeriodo($tipoDocumento)) {
            $modo = self::MODO_CONSECUTIVO;
        }

        $periodo = $row['periodo_reinicio'] ?? null;
        if ($modo !== self::MODO_POR_FECHA) {
            $periodo = null;
        } elseif ($periodo === null) {
            // Modo por fecha sin periodo (no debería pasar: hay un CHECK en la base).
            // Se asume anual antes que dejar al documento sin número.
            $periodo = self::PERIODO_ANUAL;
        }

        $row['modo_numeracion']  = $modo;
        $row['periodo_reinicio'] = $periodo;

        return $row;
    }

    /**
     * Obtiene el tipo_ambiente ('1' Pruebas, '2' Producción) basado en el punto de emisión.
     */
    private function getTipoAmbiente(int $idPuntoEmision): string
    {
        $sql = "SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) 
                FROM empresa_punto_emision pe
                JOIN empresa_establecimiento ee ON pe.id_establecimiento = ee.id
                JOIN empresas e ON ee.id_empresa = e.id
                WHERE pe.id = :id_punto";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_punto' => $idPuntoEmision]);
        return $stmt->fetchColumn() ?: '1';
    }

    /**
     * Obtiene TODOS los secuenciales ya utilizados para un punto de emisión y tipo de documento.
     * Solo consulta la tabla correspondiente al tipo de documento.
     * Retorna un array de enteros con los secuenciales usados, ordenados ASC.
     */
    public function getSecuencialesUsados(int $idPuntoEmision, string $tipoDocumento): array
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;

        if (!$map) {
            return [];
        }

        // Verificar que la tabla exista antes de consultar
        if (!$this->tableExists($map['tabla'])) {
            return [];
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);

        // Se ignoran los secuenciales que no sean enteros ("", "ABC-1", basura de cargas viejas):
        // un solo valor no numérico hacía fallar el CAST y dejaba al módulo sin poder emitir.
        $sql = "SELECT CAST(TRIM({$colSec}) AS BIGINT) AS sec_num
                FROM {$tabla}
                WHERE {$colPunto} = :id_punto 
                  AND tipo_ambiente = :tipo_ambiente
                  AND eliminado = false
                  AND {$colSec} IS NOT NULL
                  AND TRIM({$colSec}) ~ '^[0-9]+$'
                ORDER BY sec_num ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Resuelve EN SQL el siguiente secuencial disponible para un punto + tipo, a partir del
     * secuencial inicial configurado. Devuelve el primer hueco libre desde ese inicial y, si no
     * hay ninguno, el siguiente al máximo usado.
     *
     * Reglas (las mismas que documenta SecuencialService):
     *   - Si el propio inicial está libre, ese es el número — aunque ya existan documentos con
     *     números mayores. Ej.: inicial 5 y solo existe el 11 → devuelve 5.
     *   - Si el inicial está ocupado, se busca el primer número libre por encima de él.
     *     Ej.: inicial 1 y existen del 1 al 10 → devuelve 11.
     *   - Nunca devuelve un número menor al inicial.
     *
     * Por qué en SQL y no en PHP: la versión anterior traía TODOS los secuenciales del punto a
     * memoria y recorría uno a uno el rango [inicial .. máximo]. Con un punto que ya llegó al
     * 500.000 eso son medio millón de iteraciones en cada emisión. Aquí el motor resuelve el
     * hueco con dos búsquedas sobre el mismo conjunto.
     *
     * Los secuenciales que no sean enteros ("", "ABC-1", basura de una carga vieja) se IGNORAN
     * en vez de romper la consulta: antes, un solo valor no numérico hacía fallar el CAST y el
     * módulo entero se quedaba sin poder emitir.
     *
     * $techo acota por arriba el conjunto de números que se consideran "usados", y con él todo
     * el cálculo. Lo usan los dos modos de numeración (ver SecuencialService):
     *   - 'por_fecha': $secuencialInicial y $techo delimitan el periodo del documento, de forma
     *     que el correlativo de septiembre no vea los números de agosto ni los de otro año.
     *   - 'consecutivo' en tipos que admiten fecha: $techo = TECHO_CONSECUTIVO, para que los
     *     números con prefijo de periodo que dejó un paso por 'por_fecha' no arrastren la serie.
     * Sin $techo, el comportamiento es exactamente el de siempre.
     *
     * @return array{siguiente:int,max_usado:int,total_usados:int,es_gap:bool}
     */
    public function getSiguienteDisponible(int $idPuntoEmision, string $tipoDocumento, int $secuencialInicial, ?int $techo = null): array
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;
        $inicial = max(1, $secuencialInicial);
        $techo   = $techo ?? PHP_INT_MAX;

        if (!$map || !$this->tableExists($map['tabla'])) {
            return ['siguiente' => $inicial, 'max_usado' => 0, 'total_usados' => 0, 'es_gap' => false];
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];

        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);
        $params = [
            ':id_punto'      => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente,
            ':piso'          => $inicial,
            ':techo'         => $techo,
            ':ini_libre'     => $inicial,
            ':ini_valor'     => $inicial,
            ':ini_desde'     => $inicial,
        ];

        // Secuenciales "quemados" en el SRI: los de documentos (eliminados o no) cuya clave
        // de acceso el SRI ya recibió/autorizó en este ambiente. Un documento eliminado deja
        // de contar en `usados`, y su número volvía a salir como hueco libre; pero el SRI ya
        // lo tiene registrado con la clave vieja y rechaza el nuevo con "ERROR 45 SECUENCIAL
        // REGISTRADO" (o 43 "CLAVE ACCESO REGISTRADA"). Se cruza por (tipo, id) contra
        // sri_envio_log y no por clave_acceso: al editar la fecha de un borrador la clave
        // cambia, pero el id y el secuencial se conservan. Filtra por el ambiente del log,
        // no del documento: es en ese ambiente donde el número quedó ocupado.
        $unionQuemados = '';
        $tipoSriLog    = self::TABLA_A_TIPO_SRI_LOG[$tabla] ?? null;
        if ($tipoSriLog !== null && $this->tableExists('sri_envio_log')) {
            $acciones      = self::ACCIONES_SRI_RETIENE_SECUENCIAL;
            $unionQuemados = "
                    UNION
                    SELECT DISTINCT CAST(TRIM(d.{$colSec}) AS BIGINT) AS sec
                      FROM {$tabla} d
                     WHERE d.{$colPunto} = :id_punto_q
                       AND d.{$colSec} IS NOT NULL
                       AND TRIM(d.{$colSec}) ~ '^[0-9]+$'
                       AND CAST(TRIM(d.{$colSec}) AS BIGINT) BETWEEN :piso_q AND :techo_q
                       AND EXISTS (
                           SELECT 1 FROM sri_envio_log l
                            WHERE l.tipo_comprobante = :tipo_sri_log
                              AND l.id_comprobante   = d.id
                              AND l.tipo_ambiente    = :tipo_ambiente_q
                              AND l.accion IN ({$acciones})
                       )";
            $params += [
                ':id_punto_q'      => $idPuntoEmision,
                ':piso_q'          => $inicial,
                ':techo_q'         => $techo,
                ':tipo_sri_log'    => $tipoSriLog,
                ':tipo_ambiente_q' => $tipoAmbiente,
            ];
        }

        // Placeholders distintos para el mismo valor: PDO/pgsql no permite repetir uno nombrado.
        $sql = "WITH usados AS (
                    SELECT DISTINCT CAST(TRIM({$colSec}) AS BIGINT) AS sec
                      FROM {$tabla}
                     WHERE {$colPunto} = :id_punto
                       AND tipo_ambiente = :tipo_ambiente
                       AND eliminado = false
                       AND {$colSec} IS NOT NULL
                       AND TRIM({$colSec}) ~ '^[0-9]+$'
                       AND CAST(TRIM({$colSec}) AS BIGINT) BETWEEN :piso AND :techo
                    {$unionQuemados}
                )
                SELECT
                    (SELECT COALESCE(MAX(sec), 0) FROM usados) AS max_usado,
                    (SELECT COUNT(*)              FROM usados) AS total_usados,
                    CASE
                        WHEN NOT EXISTS (SELECT 1 FROM usados WHERE sec = :ini_libre) THEN :ini_valor
                        ELSE (
                            SELECT MIN(u.sec + 1) FROM usados u
                             WHERE u.sec >= :ini_desde
                               AND NOT EXISTS (SELECT 1 FROM usados v WHERE v.sec = u.sec + 1)
                        )
                    END AS siguiente";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $maxUsado  = (int) ($row['max_usado'] ?? 0);
        $total     = (int) ($row['total_usados'] ?? 0);
        $siguiente = (int) ($row['siguiente'] ?? 0);
        if ($siguiente < $inicial) {
            $siguiente = max($inicial, $maxUsado + 1);
        }

        return [
            'siguiente'    => $siguiente,
            'max_usado'    => $maxUsado,
            'total_usados' => $total,
            // Es "hueco" cuando el número cae dentro del rango ya emitido, no al final de la serie.
            'es_gap'       => $total > 0 && $siguiente < $maxUsado,
        ];
    }

    /**
     * Modo 'por_fecha': siguiente CORRELATIVO dentro del periodo, con las mismas reglas de
     * huecos que la numeración consecutiva pero mirando solo los documentos de ese periodo.
     *
     * Los documentos del periodo se reconocen por el PREFIJO del secuencial (los de
     * septiembre de 2026 empiezan en '202609'), y el correlativo es lo que va después. Se
     * compara como texto, no por rango numérico, justamente para que un periodo que agote
     * sus dígitos crezca hacia afuera en vez de solaparse con el periodo siguiente.
     *
     * Los secuenciales no numéricos, y los que no llegan a superar el largo del prefijo,
     * se ignoran igual que en getSiguienteDisponible().
     *
     * @return array{siguiente:int,max_usado:int,total_usados:int,es_gap:bool}
     */
    public function getSiguienteCorrelativoPeriodo(int $idPuntoEmision, string $tipoDocumento, string $prefijo, int $correlativoInicial): array
    {
        $map     = self::DOCUMENT_MAP[$tipoDocumento] ?? null;
        $inicial = max(1, $correlativoInicial);

        if (!$map || !$this->tableExists($map['tabla'])) {
            return ['siguiente' => $inicial, 'max_usado' => 0, 'total_usados' => 0, 'es_gap' => false];
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        // Enteros calculados aquí (el largo del prefijo), no datos de fuera: se interpolan.
        // No pueden ir como parámetro de PDO porque este los envía como TEXTO, y
        // `SUBSTRING(x FROM '7')` no corta por posición: PostgreSQL lo lee como la variante
        // de expresión regular y devuelve el patrón encontrado, no el resto de la cadena.
        $largo    = strlen($prefijo);
        $desdePos = $largo + 1;

        $sql = "WITH usados AS (
                    SELECT DISTINCT CAST(SUBSTRING(TRIM({$colSec}) FROM {$desdePos}) AS BIGINT) AS sec
                      FROM {$tabla}
                     WHERE {$colPunto} = :id_punto
                       AND tipo_ambiente = :tipo_ambiente
                       AND eliminado = false
                       AND {$colSec} IS NOT NULL
                       AND TRIM({$colSec}) ~ '^[0-9]+\$'
                       AND TRIM({$colSec}) LIKE :prefijo
                       AND LENGTH(TRIM({$colSec})) > {$largo}
                )
                SELECT
                    (SELECT COALESCE(MAX(sec), 0) FROM usados) AS max_usado,
                    (SELECT COUNT(*)              FROM usados) AS total_usados,
                    CASE
                        WHEN NOT EXISTS (SELECT 1 FROM usados WHERE sec = :ini_libre) THEN :ini_valor
                        ELSE (
                            SELECT MIN(u.sec + 1) FROM usados u
                             WHERE u.sec >= :ini_desde
                               AND NOT EXISTS (SELECT 1 FROM usados v WHERE v.sec = u.sec + 1)
                        )
                    END AS siguiente";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto'      => $idPuntoEmision,
            ':tipo_ambiente' => $this->getTipoAmbiente($idPuntoEmision),
            ':prefijo'       => $prefijo . '%',
            ':ini_libre'     => $inicial,
            ':ini_valor'     => $inicial,
            ':ini_desde'     => $inicial,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $maxUsado  = (int) ($row['max_usado'] ?? 0);
        $total     = (int) ($row['total_usados'] ?? 0);
        $siguiente = (int) ($row['siguiente'] ?? 0);
        if ($siguiente < $inicial) {
            $siguiente = max($inicial, $maxUsado + 1);
        }

        return [
            'siguiente'    => $siguiente,
            'max_usado'    => $maxUsado,
            'total_usados' => $total,
            'es_gap'       => $total > 0 && $siguiente < $maxUsado,
        ];
    }

    /**
     * Obtiene el número máximo de secuencial utilizado para un punto de emisión y tipo.
     */
    public function getMaxSecuencialUsado(int $idPuntoEmision, string $tipoDocumento): int
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;

        if (!$map || !$this->tableExists($map['tabla'])) {
            return 0;
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);

        $sql = "SELECT COALESCE(MAX(CAST(TRIM({$colSec}) AS BIGINT)), 0) AS max_sec
                FROM {$tabla}
                WHERE {$colPunto} = :id_punto 
                  AND tipo_ambiente = :tipo_ambiente
                  AND eliminado = false
                  AND {$colSec} IS NOT NULL
                  AND TRIM({$colSec}) ~ '^[0-9]+$'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica si un secuencial específico ya está en uso.
     */
    public function secuencialEnUso(int $idPuntoEmision, string $tipoDocumento, int $secuencial): bool
    {
        $map = self::DOCUMENT_MAP[$tipoDocumento] ?? null;

        if (!$map || !$this->tableExists($map['tabla'])) {
            return false;
        }

        $tabla    = $map['tabla'];
        $colSec   = $map['col_sec'];
        $colPunto = $map['col_punto'];
        $tipoAmbiente = $this->getTipoAmbiente($idPuntoEmision);

        $secStr = str_pad((string) $secuencial, 9, '0', STR_PAD_LEFT);

        $sql = "SELECT COUNT(*) 
                FROM {$tabla}
                WHERE {$colPunto} = :id_punto 
                  AND tipo_ambiente = :tipo_ambiente
                  AND ({$colSec} = :sec_num OR {$colSec} = :sec_str)
                  AND eliminado = false";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_punto' => $idPuntoEmision,
            ':tipo_ambiente' => $tipoAmbiente,
            ':sec_num'  => (string) $secuencial,
            ':sec_str'  => $secStr,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Obtiene todos los secuenciales configurados para un punto de emisión.
     */
    public function getAllConfigByPunto(int $idPuntoEmision): array
    {
        $colsModo = $this->soportaModoPeriodo()
            ? ", COALESCE(modo_numeracion, '" . self::MODO_CONSECUTIVO . "') AS modo_numeracion, periodo_reinicio"
            : ", '" . self::MODO_CONSECUTIVO . "' AS modo_numeracion, NULL AS periodo_reinicio";

        $sql = "SELECT id, tipo_documento, COALESCE(secuencial_inicial, 1) AS secuencial_inicial {$colsModo}
                FROM empresa_secuencial
                WHERE id_punto_emision = :id_punto
                  AND eliminado = false
                ORDER BY tipo_documento ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_punto' => $idPuntoEmision]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si una tabla existe en la base de datos.
     */
    private function tableExists(string $tableName): bool
    {
        static $cache = [];

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $sql = "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                      AND table_name = :table_name
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':table_name' => $tableName]);

        $cache[$tableName] = (bool) $stmt->fetchColumn();

        return $cache[$tableName];
    }

    /**
     * Retorna la lista de tipos de documentos soportados (los que tienen tabla mapeada).
     */
    public function getTiposDocumentoSoportados(): array
    {
        return array_keys(self::DOCUMENT_MAP);
    }

    /**
     * Verifica si un tipo de documento tiene tabla mapeada.
     */
    public function tipoDocumentoSoportado(string $tipoDocumento): bool
    {
        return isset(self::DOCUMENT_MAP[$tipoDocumento]);
    }
}
