<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

class NovedadRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('novedades');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['fecha', 'periodo_anio', 'periodo_mes', 'tipo_nombre', 'valor', 'estado', 'empleado', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'fecha';
        $ordenDir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'n', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $where .= ' AND e.eliminado = false';
        $where .= " AND n.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: las columnas del listado + lo que identifica la novedad.
            // Decisión del usuario: las columnas Tipo, Afecta a, Motivo (clasificaciones),
            // Estado y Pago NO entran en el texto libre; se filtran desde el modal.
            $condicion = FiltrosBusqueda::condicionTexto(
                [
                    'e.nombres_apellidos',                                        // Empleado
                    'e.identificacion',                                           // Identificación
                    "TO_CHAR(n.fecha, 'DD-MM-YYYY')",                             // Fecha (como se muestra)
                    'n.fecha::text',
                    self::exprPeriodoTexto('n'),                                  // Período ("Marzo 2026")
                    'n.valor::text',                                              // Valor
                    'n.observacion',
                    'u.nombre',                                                   // Usuario que registró
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (FiltrosModal en la vista). Las viejas se conservan:
        // viajan en los enlaces de PDF/Excel y en URLs guardadas.
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => [
                'tipo'           => 'n.tipo_nombre',
                'observacion'    => 'n.observacion',
                'empleado'       => 'e.nombres_apellidos',
                'identificacion' => 'e.identificacion',
            ],
            'exacto'   => [
                'estado'    => 'n.estado',
                'mes'       => 'n.periodo_mes',
                'anio'      => 'n.periodo_anio',
                'codigo'    => 'n.tipo_codigo',
                'aplica_en' => 'n.aplica_en',
                'motivo'    => 'n.motivo_codigo',
                'usuario'   => 'n.created_by',
                // origen:manual / origen:carga (importada desde Excel)
                'origen'    => "CASE WHEN n.id_carga IS NULL THEN 'manual' ELSE 'carga' END",
                // pago:pagada / pago:pendiente — mismo criterio que la columna Pago
                'pago'      => self::exprEstadoPago('n', $this->tieneDesembolsoManual()),
            ],
            'fecha'    => ['fecha' => 'n.fecha'],
            'numerico' => ['valor' => 'n.valor'],
        ]);

        $orderExpr = match ($ordenCol) {
            'empleado' => 'e.nombres_apellidos',
            default    => "n.{$ordenCol}",
        };

        $from = "FROM {$this->table} n JOIN empleados e ON e.id = n.id_empleado
                 LEFT JOIN usuarios u ON u.id = n.created_by {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT n.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion,
                       e.aporta_iess AS empleado_aporta_iess
                {$from}
                ORDER BY {$orderExpr} {$ordenDir}, n.id DESC";
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $this->anexarEstadoPago($rows, $idEmpresa), 'total' => $total];
    }

    /** Período de la novedad como se muestra en el listado ("Marzo 2026"), para el texto libre. */
    private static function exprPeriodoTexto(string $a): string
    {
        $casos = '';
        foreach (\App\models\CatalogoNovedades::MESES as $num => $nombre) {
            $casos .= ' WHEN ' . (int) $num . " THEN '" . str_replace("'", "''", $nombre) . "'";
        }
        return "CONCAT(CASE {$a}.periodo_mes{$casos} END, ' ', {$a}.periodo_anio)";
    }

    /**
     * 'pagada' / 'pendiente' en SQL, con el MISMO criterio que la columna Pago
     * (anexarEstadoPago): migrada como desembolsada o conciliada, o rubro de una línea
     * de rol cuyo neto quedó cubierto por egresos (ver idsNovedadesPagadas). Solo se
     * evalúa cuando se usa el filtro `pago:`.
     */
    private static function exprEstadoPago(string $a, bool $conDesembolsoManual = false): string
    {
        // La marca de desembolso solo cuenta como pagada en lo migrado (id_carga NULL y sin
        // desembolso_manual): en lo cargado por Excel, o marcado "Ya pagado" al registrar para
        // todos los empleados, solo dice que el anticipo/préstamo ya se entregó (ver esDesembolsoMigrado).
        $manual = $conDesembolsoManual ? " AND NOT COALESCE({$a}.desembolso_manual, false)" : '';
        return "CASE WHEN (COALESCE({$a}.desembolsado_migrado, false) AND {$a}.id_carga IS NULL{$manual}) OR COALESCE({$a}.conciliada_migrada, false)
                       OR EXISTS (
                            SELECT 1
                            FROM rol_detalle_rubro rr
                            JOIN rol_detalle rd  ON rd.id = rr.id_detalle
                            JOIN rol_cabecera rc ON rc.id = rd.id_rol
                            CROSS JOIN LATERAL (
                                SELECT COALESCE(SUM(ed.monto_pagado), 0) AS pagado
                                FROM egresos_detalle ed
                                JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                                WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                                  AND ec.eliminado = false AND ed.eliminado = false
                                  AND ed.id_referencia_documento = rd.id
                            ) pg
                            WHERE rr.id_novedad = {$a}.id
                              AND rd.id_empresa = {$a}.id_empresa AND rc.eliminado = false
                              AND pg.pagado > 0 AND ROUND(rd.neto - pg.pagado, 2) <= 0
                       )
                  THEN 'pagada' ELSE 'pendiente' END";
    }

    /** Años de período usados por la empresa (select "Año" del modal de filtros). */
    public function getAniosUsados(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT DISTINCT periodo_anio FROM {$this->table}
                                  WHERE id_empresa = :id_empresa AND eliminado = false AND periodo_anio IS NOT NULL
                                  ORDER BY periodo_anio DESC");
        $st->execute([':id_empresa' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Usuarios que registraron alguna novedad en la empresa (select "Usuario que registró"). */
    public function getUsuariosConNovedades(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT DISTINCT u.id, u.nombre
                                  FROM {$this->table} n
                                  JOIN usuarios u ON u.id = n.created_by
                                  WHERE n.id_empresa = :id_empresa AND n.eliminado = false
                                  ORDER BY u.nombre");
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Anexa a cada fila los flags derivados `pagada` y `bloqueada` (en lote, sin
     * N+1). Se usa en el listado y también al revisar las novedades de una carga
     * masiva antes de revertirla, para no duplicar el criterio de "ya usada".
     */
    public function anexarEstadoPago(array $rows, int $idEmpresa): array
    {
        if (empty($rows)) {
            return $rows;
        }

        // Estado de pago (derivado del rol): la novedad está pagada si aparece
        // como rubro en una línea de rol que fue pagada vía egreso.
        $pagadas = $this->idsNovedadesPagadas(array_column($rows, 'id'), $idEmpresa);
        // Anexar si la novedad está BLOQUEADA para editar/eliminar: mismo criterio que
        // NovedadService::bloquearSiRolPagado (rol del período ya pagado/contabilizado
        // o con algún pago registrado), calculado en lote para no hacer N+1 en el listado.
        $bloqueadas = $this->combosRolBloqueado($rows, $idEmpresa);
        // + mismo criterio que NovedadService::bloquearSiYaDesembolsada: Anticipo (3) ya
        // pagado por egreso, o cuota de Préstamo Empresa (9) cuyo empleado ya tiene
        // desembolsos registrados.
        $desembolsadas = $this->idsDesembolsadas($rows, $idEmpresa);
        foreach ($rows as &$r) {
            // Anticipo/Préstamo migrados marcados como desembolsados (Camino 1): se muestran como
            // "Pagada" (ya estaban saldados en el sistema anterior) aunque aún no exista el rol.
            // OJO: en pgsql el booleano llega como 't'/'f' y 'f' es truthy en PHP → comparar explícito.
            // Lo marcado como desembolsado por la carga Excel NO cuenta aquí: se edita, elimina y
            // revierte como cualquier otra novedad (no hay egreso que quede sin respaldo).
            $desembMig = self::esDesembolsoMigrado($r);
            // Conciliada por rol migrado (Pieza B): novedad de un período con rol/quincena migrado → "Pagada".
            $concMig = in_array($r['conciliada_migrada'] ?? null, ['t', true, 1, '1', 'true'], true);
            $r['pagada'] = isset($pagadas[(int) $r['id']]) || $desembMig || $concMig;
            $cod = (string) ($r['tipo_codigo'] ?? '');
            $porDesembolso = (\App\models\CatalogoNovedades::esPagoPorEgreso($cod) && (isset($desembolsadas['a' . (int) $r['id']]) || $desembMig))
                || ($cod === '9' && (isset($desembolsadas['p' . (int) $r['id_empleado']]) || $desembMig));
            $r['bloqueada'] = isset($bloqueadas[$this->claveCombo($r)]) || $porDesembolso;
        }
        unset($r);

        return $rows;
    }

    /** ¿Marca de desembolso puesta? (Anticipo/Préstamo Empresa ya entregado, sin egreso). */
    public static function tieneMarcaDesembolso(array $n): bool
    {
        return in_array($n['desembolsado_migrado'] ?? null, ['t', true, 1, '1', 'true'], true);
    }

    /**
     * ¿Desembolsado en el sistema anterior (migración)? La misma marca la pone la carga
     * Excel, que asume ya entregados los anticipos y préstamos empresa; esas novedades
     * llevan id_carga y no se bloquean ni se ven como pagadas por ella.
     */
    public static function esDesembolsoMigrado(array $n): bool
    {
        // "Ya pagado" marcado a mano al registrar para todos los empleados: tampoco es migrado.
        $manual = in_array($n['desembolso_manual'] ?? null, ['t', true, 1, '1', 'true'], true);
        return self::tieneMarcaDesembolso($n) && empty($n['id_carga']) && !$manual;
    }

    /** Clave [id_empleado|tipo_rol|anio|mes] para cruzar una novedad con su corrida de rol. */
    private function claveCombo(array $r): string
    {
        $tipoRol = match (strtolower((string) ($r['aplica_en'] ?? 'rol'))) {
            'semanal'  => 'SEMANAL',
            'quincena' => 'QUINCENA',
            default    => 'MENSUAL',
        };
        return ((int) $r['id_empleado']) . '|' . $tipoRol . '|' . ((int) $r['periodo_anio']) . '|' . ((int) $r['periodo_mes']);
    }

    /**
     * Combos [id_empleado|tipo_rol|anio|mes] cuyo rol ya está pagado/contabilizado o
     * tiene algún pago registrado por egreso: toda novedad de ese combo queda bloqueada
     * para editar/eliminar (mismo criterio que RolPagoRepository::existeRolPagadoPeriodo,
     * en lote para todos los empleados del listado en una sola consulta).
     */
    private function combosRolBloqueado(array $rows, int $idEmpresa): array
    {
        $idsEmp = array_values(array_unique(array_map(fn($r) => (int) $r['id_empleado'], $rows)));
        if (empty($idsEmp)) return [];
        $in = implode(',', $idsEmp);
        $sql = "SELECT DISTINCT d.id_empleado, c.tipo_rol, c.periodo_anio, c.periodo_mes
                FROM rol_detalle d
                JOIN rol_cabecera c ON c.id = d.id_rol
                WHERE d.id_empresa = :emp AND d.id_empleado IN ($in) AND c.eliminado = false
                  AND (
                    c.estado IN ('pagado','contabilizado')
                    OR EXISTS (
                        SELECT 1 FROM egresos_detalle ed
                        JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                        WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                          AND ec.eliminado = false AND ed.eliminado = false
                          AND ed.id_referencia_documento = d.id AND ed.monto_pagado > 0
                    )
                  )";
        $set = [];
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $clave = ((int) $r['id_empleado']) . '|' . $r['tipo_rol'] . '|' . ((int) $r['periodo_anio']) . '|' . ((int) $r['periodo_mes']);
                $set[$clave] = true;
            }
        } catch (\Throwable $e) {
            // Nómina/roles no desplegado en esta instalación: sin bloqueo derivado del rol.
        }
        return $set;
    }

    /**
     * Claves ['a{id_novedad}' => true] para Anticipos (3) ya pagados por egreso, y
     * ['p{id_empleado}' => true] para empleados con algún desembolso de Préstamo Empresa
     * (9) registrado. Una sola consulta por tipo para todo el listado (sin N+1).
     */
    private function idsDesembolsadas(array $rows, int $idEmpresa): array
    {
        $set = [];

        $idsAnticipo = array_values(array_unique(array_filter(array_map(
            fn($r) => \App\models\CatalogoNovedades::esPagoPorEgreso((string) ($r['tipo_codigo'] ?? '')) ? (int) $r['id'] : null,
            $rows
        ))));
        if (!empty($idsAnticipo)) {
            $in = implode(',', $idsAnticipo);
            $sql = "SELECT ed.id_referencia_documento AS id_novedad
                    FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento = 'ANTICIPO' AND ec.estado != 'anulado'
                      AND ec.eliminado = false AND ed.eliminado = false
                      AND ec.id_empresa = :emp AND ed.id_referencia_documento IN ($in)
                    GROUP BY ed.id_referencia_documento
                    HAVING SUM(ed.monto_pagado) > 0.001";
            try {
                $st = $this->db->prepare($sql);
                $st->execute([':emp' => $idEmpresa]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { $set['a' . (int) $id] = true; }
            } catch (\Throwable $e) {
                // egresos no disponible → sin bloqueo derivado del desembolso
            }
        }

        $idsEmpPrestamo9 = array_values(array_unique(array_filter(array_map(
            fn($r) => ((string) ($r['tipo_codigo'] ?? '')) === '9' ? (int) $r['id_empleado'] : null,
            $rows
        ))));
        if (!empty($idsEmpPrestamo9)) {
            $in = implode(',', $idsEmpPrestamo9);
            $sql = "SELECT DISTINCT ed.id_referencia_documento AS id_empleado
                    FROM egresos_detalle ed JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                    WHERE ed.tipo_documento = 'PRESTAMO9' AND ec.estado != 'anulado'
                      AND ec.eliminado = false AND ed.eliminado = false
                      AND ec.id_empresa = :emp AND ed.id_referencia_documento IN ($in)
                      AND ed.monto_pagado > 0.001";
            try {
                $st = $this->db->prepare($sql);
                $st->execute([':emp' => $idEmpresa]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idEmp) { $set['p' . (int) $idEmp] = true; }
            } catch (\Throwable $e) {
                // egresos no disponible → sin bloqueo derivado del desembolso
            }
        }

        return $set;
    }

    /**
     * Devuelve un mapa [id_novedad => true] con las novedades ya PAGADAS: aquellas que
     * figuran como rubro (rol_detalle_rubro.id_novedad) en una línea de rol cuyo neto
     * fue cubierto por egresos (pagado > 0 y saldo ≤ 0). Resiliente: si el módulo de
     * roles/egresos no está desplegado, devuelve [] y el listado sigue funcionando.
     */
    private function idsNovedadesPagadas(array $ids, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (empty($ids)) {
            return [];
        }
        $in = implode(',', $ids);
        $pagadoSub = "(SELECT COALESCE(SUM(ed.monto_pagado), 0)
                        FROM egresos_detalle ed
                        JOIN egresos_cabecera ec ON ec.id = ed.id_egreso
                       WHERE ed.tipo_documento = 'ROL' AND ec.estado != 'anulado'
                         AND ec.eliminado = false AND ed.eliminado = false
                         AND ed.id_referencia_documento = rd.id)";
        $sql = "SELECT DISTINCT rr.id_novedad
                FROM rol_detalle_rubro rr
                JOIN rol_detalle rd  ON rd.id = rr.id_detalle
                JOIN rol_cabecera rc ON rc.id = rd.id_rol
                WHERE rr.id_novedad IN ($in)
                  AND rd.id_empresa = :emp AND rc.eliminado = false
                  AND {$pagadoSub} > 0
                  AND ROUND(rd.neto - {$pagadoSub}, 2) <= 0";
        $set = [];
        try {
            $st = $this->db->prepare($sql);
            $st->execute([':emp' => $idEmpresa]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $set[(int) $id] = true;
            }
        } catch (\Throwable $e) {
            // Nómina/roles no desplegado en esta instalación: sin badge de pago.
        }
        return $set;
    }

    /** ¿Ya se aplicó database/novedades_aporta_iess.sql? Sin la columna, la marca no se guarda. */
    public function tieneMarcaIess(): bool
    {
        return $this->columnaExiste('novedades', 'aporta_iess');
    }

    /** ¿Ya existe novedades.desembolso_manual? (database/2026-11-18_novedades_desembolso_manual.sql) */
    public function tieneDesembolsoManual(): bool
    {
        return $this->columnaExiste('novedades', 'desembolso_manual');
    }

    /** ¿Ya existe novedades.id_rol_origen? (database/2026-11-27_roles_saldo_en_contra.sql) */
    public function tieneRolOrigen(): bool
    {
        return $this->columnaExiste('novedades', 'id_rol_origen');
    }

    /**
     * Descuentos automáticos por saldo en contra que generó un rol mensual (vigentes),
     * en cualquier período: el usuario puede haberlos movido de mes.
     */
    public function getPorRolOrigen(int $idRol, int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, id_empleado, valor, periodo_anio, periodo_mes, aplica_en, observacion
                                  FROM {$this->table}
                                  WHERE id_empresa = :e AND id_rol_origen = :r AND eliminado = false");
        $st->execute([':e' => $idEmpresa, ':r' => $idRol]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Actualiza el valor (y la observación) de un descuento automático por saldo en contra. */
    public function actualizarValorSaldoContra(int $id, int $idEmpresa, float $valor, string $observacion, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE {$this->table}
                                     SET valor = :v, observacion = :o, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                                   WHERE id = :id AND id_empresa = :e AND id_rol_origen IS NOT NULL AND eliminado = false");
        $st->execute([':v' => round($valor, 2), ':o' => $observacion, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Valor para la columna aporta_iess: 'true'/'false', o null si la novedad no lleva marca. */
    private static function paramMarcaIess(array $d)
    {
        $v = $d['aporta_iess'] ?? null;
        return $v === null ? null : ($v ? 'true' : 'false');
    }

    public function create(array $d): int
    {
        // La columna id_carga solo se escribe cuando la novedad viene de una carga
        // masiva; así el alta normal no depende de que el SQL de cargas esté aplicado.
        $idCarga    = isset($d['id_carga']) && (int) $d['id_carga'] > 0 ? (int) $d['id_carga'] : null;
        $colCarga   = $idCarga !== null ? ', id_carga' : '';
        $valCarga   = $idCarga !== null ? ', :id_carga' : '';
        // Descuento automático por saldo en contra (RolSaldoContraService): rol que lo generó.
        $idRolOrigen = isset($d['id_rol_origen']) && (int) $d['id_rol_origen'] > 0 ? (int) $d['id_rol_origen'] : null;
        if ($idRolOrigen !== null) {
            $colCarga .= ', id_rol_origen';
            $valCarga .= ', :id_rol_origen';
        }
        // Anticipo/Préstamo Empresa ya entregados fuera del sistema (la carga Excel lo asume):
        // el rol los descuenta sin pedir el egreso de desembolso.
        $desemb     = !empty($d['desembolsado_migrado']);
        $colDesemb  = $desemb ? ', desembolsado_migrado' : '';
        $valDesemb  = $desemb ? ', true' : '';
        // "Ya pagado" marcado a mano (registro para todos los empleados): no es migrado,
        // se edita y elimina como cualquier otra (ver esDesembolsoMigrado).
        if ($desemb && !empty($d['desembolso_manual']) && $this->tieneDesembolsoManual()) {
            $colDesemb .= ', desembolso_manual';
            $valDesemb .= ', true';
        }
        // Igual con la marca de IESS: solo si ya existe la columna.
        $conIess    = $this->tieneMarcaIess();
        $colIess    = $conIess ? ', aporta_iess' : '';
        $valIess    = $conIess ? ', :aporta_iess' : '';

        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_empleado, tipo_codigo, tipo_nombre, fecha,
                    periodo_mes, periodo_anio, valor, aplica_en, motivo_codigo, motivo_nombre,
                    observacion, estado, tipo_ambiente, created_by, updated_by, created_at, updated_at, eliminado{$colCarga}{$colIess}{$colDesemb}
                ) VALUES (
                    :id_empresa, :id_empleado, :tipo_codigo, :tipo_nombre, :fecha,
                    :periodo_mes, :periodo_anio, :valor, :aplica_en, :motivo_codigo, :motivo_nombre,
                    :observacion, :estado, (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false{$valCarga}{$valIess}{$valDesemb}
                )";
        $params = [
            ':id_empresa'    => $d['id_empresa'],
            ':id_empleado'   => $d['id_empleado'],
            ':tipo_codigo'   => $d['tipo_codigo'],
            ':tipo_nombre'   => $d['tipo_nombre'],
            ':fecha'         => $d['fecha'],
            ':periodo_mes'   => (int) $d['periodo_mes'],
            ':periodo_anio'  => (int) $d['periodo_anio'],
            ':valor'         => (float) ($d['valor'] ?? 0),
            ':aplica_en'     => $d['aplica_en'] ?? 'rol',
            ':motivo_codigo' => $d['motivo_codigo'] ?? null,
            ':motivo_nombre' => $d['motivo_nombre'] ?? null,
            ':observacion'   => $d['observacion'] ?? null,
            ':estado'        => $d['estado'] ?? 'activo',
            ':id_u'          => $d['id_usuario'],
        ];
        if ($idCarga !== null) {
            $params[':id_carga'] = $idCarga;
        }
        if ($idRolOrigen !== null) {
            $params[':id_rol_origen'] = $idRolOrigen;
        }
        if ($conIess) {
            $params[':aporta_iess'] = self::paramMarcaIess($d);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $d): bool
    {
        $conIess = $this->tieneMarcaIess();
        $setIess = $conIess ? 'aporta_iess = :aporta_iess,' : '';
        // Selector "Por pagar / Ya pagado" (NovedadService::aplicarMarcaPago): solo si viene el dato.
        $conPago   = array_key_exists('desembolsado_migrado', $d);
        $conManual = $conPago && $this->tieneDesembolsoManual();
        $setPago   = ($conPago ? 'desembolsado_migrado = :desemb,' : '') . ($conManual ? 'desembolso_manual = :desemb_manual,' : '');
        $sql = "UPDATE {$this->table} SET
                    id_empleado = :id_empleado,
                    tipo_codigo = :tipo_codigo,
                    tipo_nombre = :tipo_nombre,
                    fecha = :fecha,
                    periodo_mes = :periodo_mes,
                    periodo_anio = :periodo_anio,
                    valor = :valor,
                    aplica_en = :aplica_en,
                    motivo_codigo = :motivo_codigo,
                    motivo_nombre = :motivo_nombre,
                    observacion = :observacion,
                    estado = :estado,
                    {$setIess}
                    {$setPago}
                    updated_by = :id_u,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = [
            ':id_empleado'   => $d['id_empleado'],
            ':tipo_codigo'   => $d['tipo_codigo'],
            ':tipo_nombre'   => $d['tipo_nombre'],
            ':fecha'         => $d['fecha'],
            ':periodo_mes'   => (int) $d['periodo_mes'],
            ':periodo_anio'  => (int) $d['periodo_anio'],
            ':valor'         => (float) ($d['valor'] ?? 0),
            ':aplica_en'     => $d['aplica_en'] ?? 'rol',
            ':motivo_codigo' => $d['motivo_codigo'] ?? null,
            ':motivo_nombre' => $d['motivo_nombre'] ?? null,
            ':observacion'   => $d['observacion'] ?? null,
            ':estado'        => $d['estado'] ?? 'activo',
            ':id_u'          => $d['id_usuario'],
            ':id'            => $id,
            ':id_empresa'    => $idEmpresa,
        ];
        if ($conIess) {
            $params[':aporta_iess'] = self::paramMarcaIess($d);
        }
        // Booleanos como texto: un false de PHP llega a pgsql como '' y falla.
        if ($conPago) {
            $params[':desemb'] = !empty($d['desembolsado_migrado']) ? 'true' : 'false';
        }
        if ($conManual) {
            $params[':desemb_manual'] = !empty($d['desembolso_manual']) ? 'true' : 'false';
        }
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    /** Detalle con datos del empleado. */
    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT n.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion,
                       e.aporta_iess AS empleado_aporta_iess
                FROM {$this->table} n
                JOIN empleados e ON e.id = n.id_empleado
                WHERE n.id = :id AND n.id_empresa = :id_empresa AND n.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca una novedad generada previamente por un proceso automático (Control de
     * Asistencia), identificada por un marcador incrustado en la observación (p. ej.
     * "[ASISTENCIA-FALTA 2026-07]"). Evita duplicar y evita tocar novedades manuales.
     */
    public function getByMarcador(int $idEmpresa, int $idEmpleado, string $tipoCodigo, string $aplicaEn, int $mes, int $anio, string $marcador): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :emp AND id_empleado = :emple AND tipo_codigo = :tc
                  AND aplica_en = :ae AND periodo_mes = :m AND periodo_anio = :a
                  AND eliminado = false AND observacion ILIKE :mk
                ORDER BY id DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $idEmpresa, ':emple' => $idEmpleado, ':tc' => $tipoCodigo,
            ':ae' => $aplicaEn, ':m' => $mes, ':a' => $anio, ':mk' => '%' . $marcador . '%',
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Novedades vigentes de una carga masiva, con datos del empleado y los flags
     * `pagada`/`bloqueada` (mismo criterio del listado): es lo que se revisa antes
     * de permitir revertir la carga completa.
     */
    public function getPorCarga(int $idCarga, int $idEmpresa): array
    {
        return $this->getPorCargas([$idCarga], $idEmpresa);
    }

    /**
     * Igual que getPorCarga() pero para VARIAS cargas a la vez: una sola consulta
     * y una sola pasada de anexarEstadoPago() para todo el historial, en vez de
     * repetirlas por carga (el listado del modal muestra decenas).
     */
    public function getPorCargas(array $idsCarga, int $idEmpresa): array
    {
        $idsCarga = array_values(array_unique(array_filter(array_map('intval', $idsCarga))));
        if (empty($idsCarga) || !$this->columnaExiste('novedades', 'id_carga')) {
            return [];
        }
        $in = implode(',', $idsCarga);
        $sql = "SELECT n.*, e.nombres_apellidos AS empleado_nombre, e.identificacion AS empleado_identificacion
                  FROM {$this->table} n
                  JOIN empleados e ON e.id = n.id_empleado
                 WHERE n.id_carga IN ($in) AND n.id_empresa = :id_empresa AND n.eliminado = false
                 ORDER BY n.id";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $this->anexarEstadoPago($rows, $idEmpresa);
    }

    /**
     * Eliminación lógica en bloque de todas las novedades vigentes de una carga.
     * Devuelve cuántas filas se marcaron. El Service ya validó que ninguna esté
     * usada (rol pagado / desembolso registrado) y abre la transacción.
     */
    public function deleteLogicPorCarga(int $idCarga, int $idEmpresa, int $idUsuario): int
    {
        $sql = "UPDATE {$this->table}
                   SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                 WHERE id_carga = :id_carga AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_carga' => $idCarga, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
        return $st->rowCount();
    }
    /**
     * Novedades vigentes de esos empleados y años, en el ambiente actual de la
     * empresa (empleado, tipo, período, marca de IESS y valor). La importación
     * arma con ellas la clave de duplicado para rechazar lo que ya está registrado
     * antes de escribir nada. Una sola consulta para todo el archivo.
     */
    public function getParaDuplicados(int $idEmpresa, array $idsEmpleado, array $anios): array
    {
        $idsEmpleado = array_values(array_unique(array_filter(array_map('intval', $idsEmpleado))));
        $anios       = array_values(array_unique(array_filter(array_map('intval', $anios))));
        if (empty($idsEmpleado) || empty($anios)) {
            return [];
        }
        $inEmp  = implode(',', $idsEmpleado);
        $inAnio = implode(',', $anios);
        $colIess = $this->tieneMarcaIess() ? 'aporta_iess' : 'NULL AS aporta_iess';

        // desembolsado_migrado: en Anticipo/Préstamo Empresa, "ya entregado" y "por pagar"
        // no son la misma novedad (ver NovedadImportService::claveNovedad).
        $sql = "SELECT id_empleado, tipo_codigo, periodo_mes, periodo_anio, valor, {$colIess}, motivo_codigo,
                       COALESCE(desembolsado_migrado, false) AS desembolsado_migrado
                  FROM {$this->table}
                 WHERE id_empresa = :id_empresa AND eliminado = false
                   AND id_empleado IN ($inEmp) AND periodo_anio IN ($inAnio)
                   AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Novedades activas del mismo empleado, tipo y período (mes/año), del ambiente
     * actual de la empresa, sin contar `$excluirId` (la que se está editando). Para
     * avisar de un concepto repetido y para no aceptar una segunda Quincena del mes.
     */
    public function getMismoTipoPeriodo(int $idEmpresa, int $idEmpleado, string $tipo, int $anio, int $mes, ?int $excluirId = null): array
    {
        $sql = "SELECT id, tipo_nombre, valor, fecha, created_at, COALESCE(desembolsado_migrado, false) AS desembolsado_migrado
                  FROM {$this->table}
                 WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                   AND id_empleado = :emp AND tipo_codigo = :tipo
                   AND periodo_anio = :a AND periodo_mes = :m
                   AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $p = [':id_empresa' => $idEmpresa, ':emp' => $idEmpleado, ':tipo' => $tipo, ':a' => $anio, ':m' => $mes];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id <> :excluir';
            $p[':excluir'] = $excluirId;
        }
        $st = $this->db->prepare($sql . ' ORDER BY id');
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** [id_empleado => valor_quincena de la ficha] de los empleados que tienen uno mayor a 0. */
    public function getValorQuincenaFicha(int $idEmpresa, array $idsEmpleado): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsEmpleado))));
        if (empty($ids)) {
            return [];
        }
        $st = $this->db->prepare("SELECT id, valor_quincena FROM empleados
                                   WHERE id_empresa = :e AND eliminado = false AND COALESCE(valor_quincena, 0) > 0
                                     AND id IN (" . implode(',', $ids) . ")");
        $st->execute([':e' => $idEmpresa]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = (float) $r['valor_quincena'];
        }
        return $out;
    }

    /**
     * Empleados activos de la empresa para la cuadrícula "Todos los empleados" del modal:
     * id, identificación, nombre y si aporta al IESS (sin dato cuenta como que aporta).
     */
    public function getEmpleadosActivosParaNovedades(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, identificacion, nombres_apellidos, aporta_iess
                                    FROM empleados
                                   WHERE id_empresa = :e AND eliminado = false AND estado = 'activo'
                                   ORDER BY nombres_apellidos ASC, id ASC");
        $st->execute([':e' => $idEmpresa]);
        return array_map(fn($r) => [
            'id'                => (int) $r['id'],
            'identificacion'    => (string) $r['identificacion'],
            'nombres_apellidos' => (string) $r['nombres_apellidos'],
            'aporta_iess'       => $r['aporta_iess'] === null || in_array($r['aporta_iess'], [true, 't', 1, '1', 'true'], true),
        ], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** ¿El empleado aporta al IESS (pestaña Laboral de su ficha)? null si no existe en la empresa. */
    public function empleadoAportaIess(int $idEmpleado, int $idEmpresa): ?bool
    {
        $st = $this->db->prepare("SELECT aporta_iess FROM empleados WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idEmpleado, ':e' => $idEmpresa]);
        // fetch() y no fetchColumn(): un aporta_iess = false también devolvería false.
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $v = $row['aporta_iess'];
        // Sin dato cuenta como que aporta (igual que RolCalculoService). En pgsql el
        // booleano puede llegar como 't'/'f', y 'f' es truthy en PHP.
        return $v === null || in_array($v, [true, 't', 1, '1', 'true'], true);
    }

    /** Resuelve el id de un empleado por su identificación exacta (para importar). */
    public function getIdEmpleadoPorIdentificacion(int $idEmpresa, string $identificacion): ?int
    {
        $st = $this->db->prepare("SELECT id FROM empleados WHERE id_empresa = :e AND identificacion = :i AND eliminado = false LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':i' => trim($identificacion)]);
        $id = $st->fetchColumn();
        return $id !== false ? (int) $id : null;
    }
}
