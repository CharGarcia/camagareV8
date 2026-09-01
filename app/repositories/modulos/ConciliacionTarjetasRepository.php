<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos de Conciliación de Tarjetas.
 *
 * Qué formas de pago se liquidan en diferido NO se guarda en ninguna columna:
 * se resuelve por empresa_formas_pago.tipo, igual que ya hacen FlujoCajaRepository
 * (`tipo IN ('BANCO','EFECTIVO','TARJETA')`) y SaldosInicialesRepository. Para
 * sumar una pasarela nueva basta agregar su tipo al <select> de
 * views/modulos/formas_cobros_pagos/modal_forma_pago.php y a la constante de abajo.
 *
 * La unidad conciliable es la línea de ingresos_pagos (el cobro), no la transacción
 * de la pasarela: así entra todo cobro con tarjeta —Payphone, Nuvei, POS, datáfono
 * o ingreso digitado a mano— y las tablas de la pasarela solo aportan datos extra
 * (código de autorización) cuando existen.
 */
class ConciliacionTarjetasRepository extends BaseRepository
{
    /** Tipos de empresa_formas_pago cuyo dinero llega días después y neto de comisión. */
    public const TIPOS_LIQUIDACION_DIFERIDA = ['PAYPHONE', 'NUVEI', 'TARJETA'];

    public const COLUMNAS_ORDEN = [
        'numero', 'fecha_conciliacion', 'procesadora', 'destino',
        'total_bruto_estado', 'total_neto', 'neto_depositado', 'diferencia', 'estado',
    ];

    public function __construct()
    {
        parent::__construct('conciliacion_tarjetas_cabecera');
    }

    /** Ambiente actual de la empresa ('1' pruebas / '2' producción). */
    public function getTipoAmbiente(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e");
        $st->execute([':e' => $idEmpresa]);
        return (string) ($st->fetchColumn() ?: '1');
    }

    // ─── Catálogos ───────────────────────────────────────────────────────────

    /**
     * Formas de cobro que este módulo concilia: las de tipo diferido.
     * Devuelve también su cuenta contable y si esa cuenta es bancaria, porque de
     * eso depende si se puede generar el asiento (ver ConciliacionTarjetasService).
     */
    public function getProcesadoras(int $idEmpresa): array
    {
        $tipos = "'" . implode("','", self::TIPOS_LIQUIDACION_DIFERIDA) . "'";

        $sql = "SELECT fp.id, fp.nombre, fp.tipo, fp.modalidad_tarjeta, fp.activo,
                       fp.id_cuenta_contable, fp.id_banco,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       cfg.id                    AS id_config,
                       cfg.dias_liquidacion,
                       cfg.tolerancia_diferencia,
                       cfg.porcentaje_comision,
                       cfg.porcentaje_iva,
                       cfg.id_cuenta_comision,
                       cfg.id_cuenta_iva_comision,
                       cfg.id_cuenta_retencion_ir,
                       cfg.id_cuenta_retencion_iva
                  FROM empresa_formas_pago fp
                  LEFT JOIN plan_cuentas pc ON pc.id = fp.id_cuenta_contable
                  LEFT JOIN conciliacion_tarjetas_config cfg
                         ON cfg.id_forma_cobro = fp.id AND cfg.eliminado = FALSE
                 WHERE fp.id_empresa = :e
                   AND fp.eliminado  = FALSE
                   AND UPPER(fp.tipo) IN ({$tipos})
                 ORDER BY fp.activo DESC, fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Formas de cobro donde puede caer el depósito de la procesadora: el usuario
     * elige a cuál cruzar (normalmente un banco). Se excluyen las de tipo diferido
     * —el dinero no puede depositarse en la misma tarjeta— y los anticipos.
     */
    public function getFormasDestino(int $idEmpresa): array
    {
        $tipos = "'" . implode("','", self::TIPOS_LIQUIDACION_DIFERIDA) . "'";

        $sql = "SELECT fp.id, fp.nombre, fp.tipo, fp.numero_cuenta,
                       fp.id_cuenta_contable,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       b.nombre_banco
                  FROM empresa_formas_pago fp
                  LEFT JOIN plan_cuentas pc     ON pc.id = fp.id_cuenta_contable
                  LEFT JOIN bancos_ecuador b    ON b.id  = fp.id_banco
                 WHERE fp.id_empresa = :e
                   AND fp.eliminado  = FALSE
                   AND fp.activo     = TRUE
                   AND fp.tipo <> 'ANTICIPO'
                   AND UPPER(fp.tipo) NOT IN ({$tipos})
                   AND (fp.aplica_en = 'AMBAS' OR fp.aplica_en = 'INGRESO')
                 ORDER BY fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Configuración contable ──────────────────────────────────────────────

    public function getConfig(int $idEmpresa, int $idFormaCobro): ?array
    {
        // Incluye código y nombre de cada cuenta para que la pantalla de
        // configuración pueda mostrarlas sin una consulta extra por cuenta.
        $sql = "SELECT cfg.*,
                       pcc.codigo  AS comision_codigo,   pcc.nombre  AS comision_nombre,
                       pci.codigo  AS iva_codigo,        pci.nombre  AS iva_nombre,
                       pcr.codigo  AS ret_ir_codigo,     pcr.nombre  AS ret_ir_nombre,
                       pcv.codigo  AS ret_iva_codigo,    pcv.nombre  AS ret_iva_nombre
                  FROM conciliacion_tarjetas_config cfg
                  LEFT JOIN plan_cuentas pcc ON pcc.id = cfg.id_cuenta_comision
                  LEFT JOIN plan_cuentas pci ON pci.id = cfg.id_cuenta_iva_comision
                  LEFT JOIN plan_cuentas pcr ON pcr.id = cfg.id_cuenta_retencion_ir
                  LEFT JOIN plan_cuentas pcv ON pcv.id = cfg.id_cuenta_retencion_iva
                 WHERE cfg.id_empresa = :e AND cfg.id_forma_cobro = :f AND cfg.eliminado = FALSE
                 LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':f' => $idFormaCobro]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardarConfig(int $idEmpresa, int $idUsuario, array $d): int
    {
        $existente = $this->getConfig($idEmpresa, (int) $d['id_forma_cobro']);

        $params = [
            ':cta_com'  => $d['id_cuenta_comision']      ?: null,
            ':cta_iva'  => $d['id_cuenta_iva_comision']  ?: null,
            ':cta_rir'  => $d['id_cuenta_retencion_ir']  ?: null,
            ':cta_riva' => $d['id_cuenta_retencion_iva'] ?: null,
            ':pc'       => (float) ($d['porcentaje_comision'] ?? 0),
            ':pi'       => (float) ($d['porcentaje_iva'] ?? 0),
            ':dias'     => (int) ($d['dias_liquidacion'] ?? 2),
            ':tol'      => (float) ($d['tolerancia_diferencia'] ?? 0.05),
            ':u'        => $idUsuario,
        ];

        if ($existente) {
            $params[':id'] = (int) $existente['id'];
            $sql = "UPDATE conciliacion_tarjetas_config
                       SET id_cuenta_comision      = :cta_com,
                           id_cuenta_iva_comision  = :cta_iva,
                           id_cuenta_retencion_ir  = :cta_rir,
                           id_cuenta_retencion_iva = :cta_riva,
                           porcentaje_comision     = :pc,
                           porcentaje_iva          = :pi,
                           dias_liquidacion        = :dias,
                           tolerancia_diferencia   = :tol,
                           updated_at = CURRENT_TIMESTAMP,
                           updated_by = :u
                     WHERE id = :id";
            $this->db->prepare($sql)->execute($params);
            return (int) $existente['id'];
        }

        $params[':e'] = $idEmpresa;
        $params[':f'] = (int) $d['id_forma_cobro'];
        $sql = "INSERT INTO conciliacion_tarjetas_config
                    (id_empresa, id_forma_cobro, id_cuenta_comision, id_cuenta_iva_comision,
                     id_cuenta_retencion_ir, id_cuenta_retencion_iva, porcentaje_comision,
                     porcentaje_iva, dias_liquidacion, tolerancia_diferencia, created_by)
                VALUES (:e, :f, :cta_com, :cta_iva, :cta_rir, :cta_riva, :pc, :pi, :dias, :tol, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    // ─── Cobros con tarjeta pendientes de conciliar ──────────────────────────

    /**
     * Cobros con tarjeta que todavía no aparecen en ningún estado de cuenta.
     *
     * Un cobro deja esta lista solo cuando se cruza (existe una fila en
     * conciliacion_tarjetas_cruces sin eliminar), así que al anular una
     * conciliación sus cobros REAPARECEN aquí automáticamente — que es lo que se
     * quiere: son como cheques girados y no cobrados.
     *
     * `dias_transcurridos` alimenta el semáforo de atraso de la vista.
     *
     * @param int|null $idCabeceraActual Incluye también los cobros ya cruzados en
     *                                   ESA conciliación (para poder mostrarlos
     *                                   marcados mientras se edita el borrador).
     */
    public function getCobrosPendientes(
        int $idEmpresa,
        int $idFormaCobro,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        string $buscar = '',
        ?int $idUsuarioFiltro = null,
        ?int $idCabeceraActual = null
    ): array {
        $params = [
            ':e'   => $idEmpresa,
            ':f'   => $idFormaCobro,
            ':amb' => $this->getTipoAmbiente($idEmpresa),
        ];

        $where = "WHERE ic.id_empresa = :e
                    AND ic.eliminado  = FALSE
                    AND ic.estado    <> 'anulado'
                    AND ic.tipo_ambiente = :amb
                    AND ip.id_forma_cobro = :f
                    AND ip.monto > 0";

        // Solo los no cruzados (o los cruzados en la conciliación que se edita).
        if ($idCabeceraActual !== null) {
            $params[':cab'] = $idCabeceraActual;
            $where .= " AND NOT EXISTS (
                            SELECT 1 FROM conciliacion_tarjetas_cruces cr
                             WHERE cr.id_ingreso_pago = ip.id
                               AND cr.eliminado = FALSE
                               AND cr.id_cabecera <> :cab)";
        } else {
            $where .= " AND NOT EXISTS (
                            SELECT 1 FROM conciliacion_tarjetas_cruces cr
                             WHERE cr.id_ingreso_pago = ip.id
                               AND cr.eliminado = FALSE)";
        }

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $params[':desde'] = $fechaDesde;
            $where .= " AND ic.fecha_emision >= :desde";
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $params[':hasta'] = $fechaHasta;
            $where .= " AND ic.fecha_emision <= :hasta";
        }
        if ($idUsuarioFiltro !== null) {
            $params[':iuf'] = $idUsuarioFiltro;
            $where .= " AND ic.created_by = :iuf";
        }

        // Buscador: texto libre multi-palabra (§9) + filtros clave:valor.
        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['cl.nombre', 'cl.identificacion', 'ic.numero_ingreso', 'ip.referencia'],
                $parsed['texto_libre'],
                $params,
                'ctp'
            );
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['cliente' => 'cl.nombre', 'ingreso' => 'ic.numero_ingreso'],
            'exacto'   => ['identificacion' => 'cl.identificacion'],
            'fecha'    => ['fecha' => 'ic.fecha_emision'],
            'numerico' => ['monto' => 'ip.monto'],
        ]);

        $sql = "SELECT ip.id                AS id_ingreso_pago,
                       ic.id                AS id_ingreso,
                       ic.numero_ingreso,
                       ic.fecha_emision,
                       ip.monto,
                       ip.referencia,
                       cl.nombre            AS cliente_nombre,
                       cl.identificacion    AS cliente_identificacion,
                       (CURRENT_DATE - ic.fecha_emision) AS dias_transcurridos,
                       -- Documentos que cubrió el cobro (una factura, o varias)
                       (SELECT string_agg(idet.numero_documento, ', ' ORDER BY idet.id)
                          FROM ingresos_detalle idet
                         WHERE idet.id_ingreso = ic.id) AS documentos,
                       -- Código de autorización, si el cobro vino de una pasarela
                       COALESCE(
                           (SELECT pt.authorization_code FROM payphone_transacciones pt
                             WHERE pt.id_ingreso = ic.id AND pt.eliminado = FALSE
                             ORDER BY pt.id DESC LIMIT 1),
                           (SELECT nt.authorization_code FROM nuvei_transacciones nt
                             WHERE nt.id_ingreso = ic.id AND nt.eliminado = FALSE
                             ORDER BY nt.id DESC LIMIT 1)
                       ) AS autorizacion,
                       -- Cruce vigente en la conciliación que se está editando
                       (SELECT cr.id FROM conciliacion_tarjetas_cruces cr
                         WHERE cr.id_ingreso_pago = ip.id AND cr.eliminado = FALSE
                         LIMIT 1) AS id_cruce
                  FROM ingresos_pagos ip
                  INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                  LEFT  JOIN clientes cl          ON cl.id = ic.id_cliente
                  {$where}
                 ORDER BY ic.fecha_emision ASC, ip.id ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Resumen de lo pendiente por procesadora, para las tarjetas de indicadores:
     * cuántos cobros y cuánto dinero sigue sin aparecer en un estado de cuenta, y
     * cuál es el más antiguo.
     */
    public function getResumenPendientes(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $tipos = "'" . implode("','", self::TIPOS_LIQUIDACION_DIFERIDA) . "'";
        $params = [':e' => $idEmpresa, ':amb' => $this->getTipoAmbiente($idEmpresa)];

        $filtroUsuario = '';
        if ($idUsuarioFiltro !== null) {
            $params[':iuf'] = $idUsuarioFiltro;
            $filtroUsuario = " AND ic.created_by = :iuf";
        }

        $sql = "SELECT fp.id   AS id_forma_cobro,
                       fp.nombre,
                       fp.tipo,
                       COUNT(*)            AS cobros,
                       COALESCE(SUM(ip.monto), 0) AS monto,
                       MAX(CURRENT_DATE - ic.fecha_emision) AS dias_max
                  FROM ingresos_pagos ip
                  INNER JOIN ingresos_cabecera ic   ON ic.id = ip.id_ingreso
                  INNER JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro
                 WHERE ic.id_empresa = :e
                   AND ic.eliminado  = FALSE
                   AND ic.estado    <> 'anulado'
                   AND ic.tipo_ambiente = :amb
                   AND UPPER(fp.tipo) IN ({$tipos})
                   AND ip.monto > 0
                   AND NOT EXISTS (SELECT 1 FROM conciliacion_tarjetas_cruces cr
                                    WHERE cr.id_ingreso_pago = ip.id AND cr.eliminado = FALSE)
                   {$filtroUsuario}
                 GROUP BY fp.id, fp.nombre, fp.tipo
                 ORDER BY fp.nombre";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Perfiles de estado de cuenta ────────────────────────────────────────

    public function getPerfiles(int $idEmpresa, ?int $idFormaCobro = null): array
    {
        $params = [':e' => $idEmpresa];
        $filtro = '';
        if ($idFormaCobro !== null) {
            $params[':f'] = $idFormaCobro;
            $filtro = " AND (p.id_forma_cobro = :f OR p.id_forma_cobro IS NULL)";
        }

        $sql = "SELECT p.*, fp.nombre AS forma_nombre
                  FROM conciliacion_tarjetas_perfiles p
                  LEFT JOIN empresa_formas_pago fp ON fp.id = p.id_forma_cobro
                 WHERE p.id_empresa = :e AND p.eliminado = FALSE{$filtro}
                 ORDER BY p.nombre_perfil ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPerfil(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM conciliacion_tarjetas_perfiles
              WHERE id = :id AND id_empresa = :e AND eliminado = FALSE"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardarPerfil(int $idEmpresa, int $idUsuario, array $d): int
    {
        $params = [
            ':forma' => !empty($d['id_forma_cobro']) ? (int) $d['id_forma_cobro'] : null,
            ':nom'   => $d['nombre_perfil'],
            ':tipo'  => $d['tipo_archivo'],
            ':niv'   => $d['nivel'],
            ':fi'    => (int) ($d['fila_inicio'] ?? 0),
            ':ff'    => $d['formato_fecha'] ?? 'd/m/Y',
            ':sd'    => $d['separador_decimal'] ?? '.',
            ':map'   => json_encode($d['mapeo_columnas'] ?? [], JSON_UNESCAPED_UNICODE),
            ':act'   => !empty($d['activo']),
            ':u'     => $idUsuario,
        ];

        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            $params[':id'] = $id;
            $params[':e']  = $idEmpresa;
            $sql = "UPDATE conciliacion_tarjetas_perfiles
                       SET id_forma_cobro = :forma, nombre_perfil = :nom, tipo_archivo = :tipo,
                           nivel = :niv, fila_inicio = :fi, formato_fecha = :ff,
                           separador_decimal = :sd, mapeo_columnas = CAST(:map AS JSONB),
                           activo = :act, updated_at = CURRENT_TIMESTAMP, updated_by = :u
                     WHERE id = :id AND id_empresa = :e";
            $this->db->prepare($sql)->execute($params);
            return $id;
        }

        $params[':e'] = $idEmpresa;
        $sql = "INSERT INTO conciliacion_tarjetas_perfiles
                    (id_empresa, id_forma_cobro, nombre_perfil, tipo_archivo, nivel,
                     fila_inicio, formato_fecha, separador_decimal, mapeo_columnas, activo, created_by)
                VALUES (:e, :forma, :nom, :tipo, :niv, :fi, :ff, :sd, CAST(:map AS JSONB), :act, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function eliminarPerfil(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE conciliacion_tarjetas_perfiles
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ─── Cabecera de la conciliación ─────────────────────────────────────────

    /**
     * Siguiente número de conciliación. Toma el candado ANTES de leer el máximo,
     * dentro de la transacción del llamador (§8); se libera solo al COMMIT.
     */
    public function siguienteSecuencial(int $idEmpresa, string $tipoAmbiente): int
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('conciliacion_tarjetas:' || :e || ':' || :amb))")
                 ->execute([':e' => $idEmpresa, ':amb' => $tipoAmbiente]);

        // Incluye eliminadas y anuladas: un número no se reutiliza nunca.
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(secuencial), 0) + 1 FROM {$this->table}
              WHERE id_empresa = :e2 AND tipo_ambiente = :amb2"
        );
        $st->execute([':e2' => $idEmpresa, ':amb2' => $tipoAmbiente]);
        return (int) $st->fetchColumn();
    }

    public function crearCabecera(int $idEmpresa, int $idUsuario, array $d): int
    {
        $ambiente   = $this->getTipoAmbiente($idEmpresa);
        $secuencial = $this->siguienteSecuencial($idEmpresa, $ambiente);
        $numero     = 'CT-' . str_pad((string) $secuencial, 6, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO {$this->table}
                    (id_empresa, secuencial, numero, id_forma_cobro, id_forma_cobro_destino,
                     id_perfil, fecha_desde, fecha_hasta, fecha_conciliacion,
                     nombre_archivo, ruta_archivo, tipo_archivo, neto_depositado,
                     observaciones, tipo_ambiente, created_by)
                VALUES (:e, :sec, :num, :forma, :destino, :perfil, :desde, :hasta, :fecha,
                        :narch, :rarch, :tarch, :neto, :obs, :amb, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'       => $idEmpresa,
            ':sec'     => $secuencial,
            ':num'     => $numero,
            ':forma'   => (int) $d['id_forma_cobro'],
            ':destino' => !empty($d['id_forma_cobro_destino']) ? (int) $d['id_forma_cobro_destino'] : null,
            ':perfil'  => !empty($d['id_perfil']) ? (int) $d['id_perfil'] : null,
            ':desde'   => $d['fecha_desde'] ?: null,
            ':hasta'   => $d['fecha_hasta'] ?: null,
            ':fecha'   => $d['fecha_conciliacion'] ?: date('Y-m-d'),
            ':narch'   => $d['nombre_archivo'] ?? null,
            ':rarch'   => $d['ruta_archivo'] ?? null,
            ':tarch'   => $d['tipo_archivo'] ?? null,
            ':neto'    => (float) ($d['neto_depositado'] ?? 0),
            ':obs'     => $d['observaciones'] ?? null,
            ':amb'     => $ambiente,
            ':u'       => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarCabecera(int $id, int $idEmpresa, int $idUsuario, array $d): void
    {
        $sql = "UPDATE {$this->table}
                   SET id_forma_cobro_destino = :destino,
                       fecha_desde        = :desde,
                       fecha_hasta        = :hasta,
                       fecha_conciliacion = :fecha,
                       neto_depositado    = :neto,
                       observaciones      = :obs,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :u
                 WHERE id = :id AND id_empresa = :e AND eliminado = FALSE";
        $this->db->prepare($sql)->execute([
            ':destino' => !empty($d['id_forma_cobro_destino']) ? (int) $d['id_forma_cobro_destino'] : null,
            ':desde'   => $d['fecha_desde'] ?: null,
            ':hasta'   => $d['fecha_hasta'] ?: null,
            ':fecha'   => $d['fecha_conciliacion'] ?: date('Y-m-d'),
            ':neto'    => (float) ($d['neto_depositado'] ?? 0),
            ':obs'     => $d['observaciones'] ?? null,
            ':u'       => $idUsuario,
            ':id'      => $id,
            ':e'       => $idEmpresa,
        ]);
    }

    public function getCabecera(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT c.*,
                       fp.nombre  AS procesadora_nombre,
                       fp.tipo    AS procesadora_tipo,
                       fp.id_cuenta_contable AS procesadora_id_cuenta,
                       fp.id_banco           AS procesadora_id_banco,
                       pcp.codigo AS procesadora_cuenta_codigo,
                       pcp.nombre AS procesadora_cuenta_nombre,
                       fd.nombre  AS destino_nombre,
                       fd.id_cuenta_contable AS destino_id_cuenta,
                       pcd.codigo AS destino_cuenta_codigo,
                       pcd.nombre AS destino_cuenta_nombre,
                       pf.nombre_perfil
                  FROM {$this->table} c
                  LEFT JOIN empresa_formas_pago fp ON fp.id = c.id_forma_cobro
                  LEFT JOIN empresa_formas_pago fd ON fd.id = c.id_forma_cobro_destino
                  LEFT JOIN plan_cuentas pcp       ON pcp.id = fp.id_cuenta_contable
                  LEFT JOIN plan_cuentas pcd       ON pcd.id = fd.id_cuenta_contable
                  LEFT JOIN conciliacion_tarjetas_perfiles pf ON pf.id = c.id_perfil
                 WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Listado paginado de conciliaciones. */
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
            $ordenCol = 'numero';
        }
        $dir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        // getBaseWhere() nombra el placeholder :id_empresa (y :id_usuario_filtro).
        $params = [':id_empresa' => $idEmpresa, ':amb' => $this->getTipoAmbiente($idEmpresa)];
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro) . " AND c.tipo_ambiente = :amb";
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if (!empty($filtros['id_forma_cobro'])) {
            $params[':ff'] = (int) $filtros['id_forma_cobro'];
            $where .= " AND c.id_forma_cobro = :ff";
        }
        if (!empty($filtros['estado'])) {
            $params[':est'] = $filtros['estado'];
            $where .= " AND c.estado = :est";
        }
        if (!empty($filtros['fecha_desde'])) {
            $params[':fd'] = $filtros['fecha_desde'];
            $where .= " AND c.fecha_conciliacion >= :fd";
        }
        if (!empty($filtros['fecha_hasta'])) {
            $params[':fh'] = $filtros['fecha_hasta'];
            $where .= " AND c.fecha_conciliacion <= :fh";
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['c.numero', 'c.observaciones', 'fp.nombre', 'fd.nombre', 'c.nombre_archivo'],
                $parsed['texto_libre'],
                $params,
                'ctl'
            );
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['numero' => 'c.numero', 'procesadora' => 'fp.nombre', 'destino' => 'fd.nombre'],
            'exacto'   => ['estado' => 'c.estado'],
            'fecha'    => ['fecha' => 'c.fecha_conciliacion'],
            'numerico' => ['neto' => 'c.neto_depositado', 'diferencia' => 'c.diferencia'],
        ]);

        $from = "FROM {$this->table} c
                 LEFT JOIN empresa_formas_pago fp ON fp.id = c.id_forma_cobro
                 LEFT JOIN empresa_formas_pago fd ON fd.id = c.id_forma_cobro_destino";

        $stCount = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $orderExpr = match ($ordenCol) {
            'procesadora' => 'fp.nombre',
            'destino'     => 'fd.nombre',
            default       => "c.{$ordenCol}",
        };

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT c.*, fp.nombre AS procesadora_nombre, fp.tipo AS procesadora_tipo,
                       fd.nombre AS destino_nombre,
                       (SELECT COUNT(*) FROM conciliacion_tarjetas_cruces cr
                         WHERE cr.id_cabecera = c.id AND cr.eliminado = FALSE) AS cobros_cruzados
                {$from} {$where}
                ORDER BY {$orderExpr} {$dir}, c.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['data' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }

    /** Marca la conciliación como cerrada (o anulada) y guarda sus totales. */
    public function actualizarEstadoYTotales(int $id, int $idEmpresa, int $idUsuario, string $estado, array $tot, ?int $idAsiento, ?string $motivoSinAsiento): void
    {
        $sql = "UPDATE {$this->table}
                   SET estado = :est,
                       total_lineas        = :tl,
                       total_bruto_estado  = :tbe,
                       total_bruto_cruzado = :tbc,
                       total_comision      = :tc,
                       total_iva_comision  = :tiva,
                       total_retencion_ir  = :tir,
                       total_retencion_iva = :triva,
                       total_otros         = :toa,
                       total_neto          = :tn,
                       diferencia          = :dif,
                       id_asiento_contable    = :asi,
                       asiento_omitido_motivo = :mot,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :u
                 WHERE id = :id AND id_empresa = :e";
        $this->db->prepare($sql)->execute([
            ':est'   => $estado,
            ':tl'    => (int) ($tot['total_lineas'] ?? 0),
            ':tbe'   => (float) ($tot['total_bruto_estado'] ?? 0),
            ':tbc'   => (float) ($tot['total_bruto_cruzado'] ?? 0),
            ':tc'    => (float) ($tot['total_comision'] ?? 0),
            ':tiva'  => (float) ($tot['total_iva_comision'] ?? 0),
            ':tir'   => (float) ($tot['total_retencion_ir'] ?? 0),
            ':triva' => (float) ($tot['total_retencion_iva'] ?? 0),
            ':toa'   => (float) ($tot['total_otros'] ?? 0),
            ':tn'    => (float) ($tot['total_neto'] ?? 0),
            ':dif'   => (float) ($tot['diferencia'] ?? 0),
            ':asi'   => $idAsiento,
            ':mot'   => $motivoSinAsiento,
            ':u'     => $idUsuario,
            ':id'    => $id,
            ':e'     => $idEmpresa,
        ]);
    }

    public function eliminarCabecera(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE {$this->table}
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        // Los cruces se liberan para que sus cobros vuelvan a estar pendientes.
        $this->eliminarCrucesDeCabecera($id, $idUsuario);
    }

    // ─── Líneas del estado de cuenta ─────────────────────────────────────────

    /** Inserta en lote las líneas leídas del archivo. Devuelve cuántas insertó. */
    public function insertarLineas(int $idCabecera, int $idEmpresa, int $idUsuario, array $lineas, string $origen = 'archivo'): int
    {
        if (empty($lineas)) {
            return 0;
        }

        $sql = "INSERT INTO conciliacion_tarjetas_lineas
                    (id_empresa, id_cabecera, fecha_movimiento, tipo_linea, autorizacion,
                     referencia, descripcion, monto_bruto, comision, iva_comision,
                     retencion_ir, retencion_iva, otros_descuentos, monto_neto,
                     origen, linea_cruda, created_by)
                VALUES (:e, :cab, :fecha, :tipo, :aut, :ref, :desc, :bruto, :com, :iva,
                        :rir, :riva, :otros, :neto, :ori, :cruda, :u)";
        $st = $this->db->prepare($sql);

        $n = 0;
        foreach ($lineas as $l) {
            $st->execute([
                ':e'     => $idEmpresa,
                ':cab'   => $idCabecera,
                ':fecha' => $l['fecha'] ?: null,
                ':tipo'  => $l['tipo_linea'] ?? 'transaccion',
                ':aut'   => $l['autorizacion'] ?? null,
                ':ref'   => $l['referencia'] ?? null,
                ':desc'  => $l['descripcion'] ?? null,
                ':bruto' => (float) ($l['monto_bruto'] ?? 0),
                ':com'   => (float) ($l['comision'] ?? 0),
                ':iva'   => (float) ($l['iva_comision'] ?? 0),
                ':rir'   => (float) ($l['retencion_ir'] ?? 0),
                ':riva'  => (float) ($l['retencion_iva'] ?? 0),
                ':otros' => (float) ($l['otros_descuentos'] ?? 0),
                ':neto'  => (float) ($l['monto_neto'] ?? 0),
                ':ori'   => $origen,
                ':cruda' => $l['linea_cruda'] ?? null,
                ':u'     => $idUsuario,
            ]);
            $n++;
        }
        return $n;
    }

    /** Líneas del estado de cuenta con el detalle de lo que tienen cruzado. */
    public function getLineas(int $idCabecera, int $idEmpresa): array
    {
        $sql = "SELECT l.*,
                       (SELECT COUNT(*) FROM conciliacion_tarjetas_cruces cr
                         WHERE cr.id_linea = l.id AND cr.eliminado = FALSE) AS cruces,
                       (SELECT COALESCE(SUM(cr.monto_cruzado), 0) FROM conciliacion_tarjetas_cruces cr
                         WHERE cr.id_linea = l.id AND cr.eliminado = FALSE) AS monto_cruzado
                  FROM conciliacion_tarjetas_lineas l
                 WHERE l.id_cabecera = :cab AND l.id_empresa = :e AND l.eliminado = FALSE
                 ORDER BY l.fecha_movimiento ASC, l.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':cab' => $idCabecera, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLinea(int $id, int $idEmpresa): ?array
    {
        // Incluye el conteo de cruces vigentes: el Service decide con él si la línea
        // puede marcarse como "sin cobro" o si al descruzar vuelve a quedar pendiente.
        $st = $this->db->prepare(
            "SELECT l.*,
                    (SELECT COUNT(*) FROM conciliacion_tarjetas_cruces cr
                      WHERE cr.id_linea = l.id AND cr.eliminado = FALSE) AS cruces
               FROM conciliacion_tarjetas_lineas l
              WHERE l.id = :id AND l.id_empresa = :e AND l.eliminado = FALSE"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function actualizarEstadoLinea(int $idLinea, int $idEmpresa, int $idUsuario, string $estado): void
    {
        $this->db->prepare(
            "UPDATE conciliacion_tarjetas_lineas
                SET estado = :est, updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id AND id_empresa = :e"
        )->execute([':est' => $estado, ':u' => $idUsuario, ':id' => $idLinea, ':e' => $idEmpresa]);
    }

    /** Guarda los valores de una línea (comisión, retenciones…) editados a mano. */
    public function actualizarLinea(int $idLinea, int $idEmpresa, int $idUsuario, array $d): void
    {
        $sql = "UPDATE conciliacion_tarjetas_lineas
                   SET fecha_movimiento = :fecha,
                       autorizacion     = :aut,
                       referencia       = :ref,
                       descripcion      = :desc,
                       monto_bruto      = :bruto,
                       comision         = :com,
                       iva_comision     = :iva,
                       retencion_ir     = :rir,
                       retencion_iva    = :riva,
                       otros_descuentos = :otros,
                       monto_neto       = :neto,
                       observaciones    = :obs,
                       updated_at = CURRENT_TIMESTAMP,
                       updated_by = :u
                 WHERE id = :id AND id_empresa = :e AND eliminado = FALSE";
        $this->db->prepare($sql)->execute([
            ':fecha' => $d['fecha_movimiento'] ?: null,
            ':aut'   => $d['autorizacion'] ?? null,
            ':ref'   => $d['referencia'] ?? null,
            ':desc'  => $d['descripcion'] ?? null,
            ':bruto' => (float) ($d['monto_bruto'] ?? 0),
            ':com'   => (float) ($d['comision'] ?? 0),
            ':iva'   => (float) ($d['iva_comision'] ?? 0),
            ':rir'   => (float) ($d['retencion_ir'] ?? 0),
            ':riva'  => (float) ($d['retencion_iva'] ?? 0),
            ':otros' => (float) ($d['otros_descuentos'] ?? 0),
            ':neto'  => (float) ($d['monto_neto'] ?? 0),
            ':obs'   => $d['observaciones'] ?? null,
            ':u'     => $idUsuario,
            ':id'    => $idLinea,
            ':e'     => $idEmpresa,
        ]);
    }

    public function eliminarLinea(int $idLinea, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE conciliacion_tarjetas_lineas
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e"
        )->execute([':id' => $idLinea, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $this->db->prepare(
            "UPDATE conciliacion_tarjetas_cruces
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u2
              WHERE id_linea = :l AND eliminado = FALSE"
        )->execute([':u2' => $idUsuario, ':l' => $idLinea]);
    }

    /** Borra todas las líneas de una cabecera (al recargar el archivo). */
    public function eliminarLineasDeCabecera(int $idCabecera, int $idUsuario): void
    {
        $this->eliminarCrucesDeCabecera($idCabecera, $idUsuario);
        $this->db->prepare(
            "UPDATE conciliacion_tarjetas_lineas
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id_cabecera = :cab AND eliminado = FALSE"
        )->execute([':u' => $idUsuario, ':cab' => $idCabecera]);
    }

    // ─── Cruces ──────────────────────────────────────────────────────────────

    public function crearCruce(int $idEmpresa, int $idUsuario, array $d): int
    {
        $sql = "INSERT INTO conciliacion_tarjetas_cruces
                    (id_empresa, id_cabecera, id_linea, id_ingreso_pago, id_ingreso,
                     monto_cruzado, origen, score, criterio, created_by)
                VALUES (:e, :cab, :lin, :ip, :ing, :monto, :ori, :score, :crit, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $idEmpresa,
            ':cab'   => (int) $d['id_cabecera'],
            ':lin'   => (int) $d['id_linea'],
            ':ip'    => (int) $d['id_ingreso_pago'],
            ':ing'   => (int) $d['id_ingreso'],
            ':monto' => (float) ($d['monto_cruzado'] ?? 0),
            ':ori'   => $d['origen'] ?? 'manual',
            ':score' => isset($d['score']) ? (float) $d['score'] : null,
            ':crit'  => $d['criterio'] ?? null,
            ':u'     => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function eliminarCruce(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE conciliacion_tarjetas_cruces
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id = :id AND id_empresa = :e"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }

    public function eliminarCrucesDeCabecera(int $idCabecera, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE conciliacion_tarjetas_cruces
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
              WHERE id_cabecera = :cab AND eliminado = FALSE"
        )->execute([':u' => $idUsuario, ':cab' => $idCabecera]);
    }

    /** Cruces de una conciliación, con los datos del cobro para mostrarlos. */
    public function getCruces(int $idCabecera, int $idEmpresa): array
    {
        $sql = "SELECT cr.*,
                       ic.numero_ingreso, ic.fecha_emision,
                       cl.nombre AS cliente_nombre,
                       (SELECT string_agg(idet.numero_documento, ', ' ORDER BY idet.id)
                          FROM ingresos_detalle idet WHERE idet.id_ingreso = ic.id) AS documentos
                  FROM conciliacion_tarjetas_cruces cr
                  INNER JOIN ingresos_cabecera ic ON ic.id = cr.id_ingreso
                  LEFT  JOIN clientes cl          ON cl.id = ic.id_cliente
                 WHERE cr.id_cabecera = :cab AND cr.id_empresa = :e AND cr.eliminado = FALSE
                 ORDER BY cr.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':cab' => $idCabecera, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscador de cuentas del plan, para los selectores de la configuración
     * contable. Texto libre multi-palabra e insensible a tildes (§9).
     */
    public function buscarCuentasContables(int $idEmpresa, string $q): array
    {
        $params = [':e' => $idEmpresa];
        $where  = "WHERE id_empresa = :e AND eliminado = FALSE";

        $cond = FiltrosBusqueda::condicionTexto(['codigo', 'nombre'], $q, $params, 'cta');
        if ($cond !== '') {
            $where .= " AND {$cond}";
        }

        $st = $this->db->prepare(
            "SELECT id, codigo, nombre FROM plan_cuentas {$where} ORDER BY codigo ASC LIMIT 30"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Código y nombre de varias cuentas contables de una sola consulta, para armar
     * los detalles del asiento sin ir a la base una vez por línea.
     *
     * @param int[] $ids
     * @return array<int, array{codigo:string, nombre:string}>
     */
    public function getDatosCuentas(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }

        $marcas = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $marcas[] = ":c{$i}";
            $params[":c{$i}"] = $id;
        }

        $st = $this->db->prepare(
            "SELECT id, codigo, nombre FROM plan_cuentas WHERE id IN (" . implode(',', $marcas) . ")"
        );
        $st->execute($params);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[(int) $row['id']] = ['codigo' => (string) $row['codigo'], 'nombre' => (string) $row['nombre']];
        }
        return $mapa;
    }

    /**
     * Candado del recurso "cobros pendientes de esta procesadora" (§8): se toma
     * antes de leer los pendientes en cualquier flujo que después escriba cruces,
     * para que dos usuarios conciliando a la vez no crucen el mismo cobro.
     * Se libera solo con el COMMIT/ROLLBACK de la transacción en curso.
     */
    public function lockConciliacion(int $idEmpresa, int $idFormaCobro): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('conciliacion_tarjetas_cruce:' || :e || ':' || :f))")
                 ->execute([':e' => $idEmpresa, ':f' => $idFormaCobro]);
    }

    /** Un cobro ya está cruzado en otra conciliación vigente. */
    public function estaCruzado(int $idIngresoPago, ?int $exceptoCabecera = null): bool
    {
        $sql = "SELECT COUNT(*) FROM conciliacion_tarjetas_cruces
                 WHERE id_ingreso_pago = :ip AND eliminado = FALSE";
        $params = [':ip' => $idIngresoPago];
        if ($exceptoCabecera !== null) {
            $sql .= " AND id_cabecera <> :cab";
            $params[':cab'] = $exceptoCabecera;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }
}
