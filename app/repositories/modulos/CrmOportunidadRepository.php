<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: oportunidades de venta (crm_oportunidades) y su historial de etapas
 * (crm_oportunidades_etapas_log). Diseño: docs/diseno/crm-redes-fase1.md §5.6.
 *
 * Registros propios (decisión 4): sin acceso total se ven las oportunidades
 * creadas por el usuario **o** asignadas a él (CrmContactoRepository::condicionPropios).
 */
class CrmOportunidadRepository extends BaseRepository
{
    protected string $table = 'crm_oportunidades';

    public const MAPA_ORDEN = [
        'numero'                => 'o.numero',
        'titulo'                => 'o.titulo',
        'contacto'              => 'c.nombre',
        'embudo'                => 'em.nombre',
        'etapa'                 => 'et.orden',
        'valor_estimado'        => 'o.valor_estimado',
        'probabilidad'          => 'et.probabilidad',
        'fecha_cierre_estimada' => 'o.fecha_cierre_estimada',
        'responsable'           => 'ur.nombre',
        'origen'                => 'og.nombre',
        'estado'                => 'o.estado',
        'created_at'            => 'o.created_at',
        'etapa_desde'           => 'o.etapa_desde',
    ];

    /** Tope de tarjetas por columna del Kanban (el resto se ve en la Lista). */
    public const MAX_TARJETAS_ETAPA = 200;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private const FROM = "FROM crm_oportunidades o
        JOIN crm_contactos c   ON c.id = o.id_contacto
        JOIN crm_etapas et     ON et.id = o.id_etapa
        JOIN crm_embudos em    ON em.id = o.id_embudo
        LEFT JOIN crm_origenes og ON og.id = o.id_origen
        LEFT JOIN usuarios ur     ON ur.id = o.id_responsable
        LEFT JOIN vendedores ve   ON ve.id = o.id_vendedor";

    private const COLUMNAS = "o.id, o.numero, o.titulo, o.id_contacto, o.id_embudo, o.id_etapa,
        o.id_responsable, o.id_vendedor, o.valor_estimado, o.fecha_cierre_estimada, o.id_origen,
        o.id_conversacion, o.id_proforma, o.id_cliente, o.estado, o.id_motivo_perdida,
        o.detalle_perdida, o.cerrada_at, o.etapa_desde, o.orden_kanban, o.created_at, o.created_by,
        c.nombre AS contacto, c.empresa_contacto, c.telefono AS contacto_telefono,
        c.instagram_usuario AS contacto_instagram,
        et.nombre AS etapa, et.probabilidad, et.tipo AS etapa_tipo, et.color AS etapa_color,
        em.nombre AS embudo, og.nombre AS origen, og.tipo_canal AS origen_canal,
        ur.nombre AS responsable, ve.nombre AS vendedor,
        GREATEST(0, CURRENT_DATE - o.etapa_desde::date) AS dias_en_etapa";

    /**
     * Arma el WHERE común de Lista, Kanban y totales a partir de los filtros de
     * pantalla + el buscador (FiltrosBusqueda). Devuelve [where, params].
     *
     * @param array $f id_embudo, estado (abierta|ganada|perdida|''), id_responsable,
     *                 id_origen, cierre_desde, cierre_hasta, buscar
     */
    private function armarWhere(int $idEmpresa, array $f, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'o')
                . CrmContactoRepository::condicionPropios('o', $params, $idUsuarioFiltro);

        if (!empty($f['id_embudo'])) {
            $where .= ' AND o.id_embudo = :f_embudo';
            $params[':f_embudo'] = (int) $f['id_embudo'];
        }
        if (in_array($f['estado'] ?? '', ['abierta', 'ganada', 'perdida'], true)) {
            $where .= ' AND o.estado = :f_estado';
            $params[':f_estado'] = $f['estado'];
        }
        if (!empty($f['id_responsable'])) {
            $where .= ' AND o.id_responsable = :f_resp';
            $params[':f_resp'] = (int) $f['id_responsable'];
        }
        if (!empty($f['id_origen'])) {
            $where .= ' AND o.id_origen = :f_origen';
            $params[':f_origen'] = (int) $f['id_origen'];
        }
        if (!empty($f['cierre_desde']) && $this->esFecha($f['cierre_desde'])) {
            $where .= ' AND o.fecha_cierre_estimada >= :f_cd';
            $params[':f_cd'] = $f['cierre_desde'];
        }
        if (!empty($f['cierre_hasta']) && $this->esFecha($f['cierre_hasta'])) {
            $where .= ' AND o.fecha_cierre_estimada <= :f_ch';
            $params[':f_ch'] = $f['cierre_hasta'];
        }

        $parsed = FiltrosBusqueda::parsear((string) ($f['buscar'] ?? ''));
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['o.titulo', 'c.nombre', 'c.empresa_contacto', 'c.telefono', 'c.instagram_usuario', 'CAST(o.numero AS text)'],
                $parsed['texto_libre'], $params, 'otxt'
            );
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => [
                'titulo' => 'o.titulo', 'contacto' => 'c.nombre', 'etapa' => 'et.nombre',
                'embudo' => 'em.nombre', 'origen' => 'og.nombre', 'responsable' => 'ur.nombre',
                'vendedor' => 've.nombre',
            ],
            'exacto'   => ['estado' => 'o.estado', 'numero' => 'o.numero'],
            'fecha'    => ['cierre' => 'o.fecha_cierre_estimada', 'creado' => 'o.created_at', 'cerrada' => 'o.cerrada_at'],
            'numerico' => ['valor' => 'o.valor_estimado', 'probabilidad' => 'et.probabilidad'],
        ]);

        return [$where, $params];
    }

    private function esFecha(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
    }

    /** Lista paginada (vista Lista y exportaciones; $perPage = 0 = todas). */
    public function getListado(int $idEmpresa, array $filtros, int $page, int $perPage, array $ordenMulti, ?int $idUsuarioFiltro): array
    {
        [$where, $params] = $this->armarWhere($idEmpresa, $filtros, $idUsuarioFiltro);

        $st = $this->db->prepare('SELECT COUNT(*) ' . self::FROM . " {$where}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = OrdenListado::clausula(OrdenListado::normalizar($ordenMulti), self::MAPA_ORDEN, 'o.created_at', 'o.id DESC');
        $limit = $perPage > 0 ? ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (max(1, $page) - 1) * $perPage : '';

        $st = $this->db->prepare('SELECT ' . self::COLUMNAS . ' ' . self::FROM . " {$where} {$orderBy}{$limit}");
        $st->execute($params);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Tarjetas del Kanban: oportunidades ABIERTAS del embudo, hasta
     * MAX_TARJETAS_ETAPA por etapa, con marca de actividad vencida.
     * Devuelve [id_etapa => ['tarjetas' => [...], 'cantidad' => n, 'valor' => x]].
     */
    public function getKanban(int $idEmpresa, int $idEmbudo, array $filtros, ?int $idUsuarioFiltro): array
    {
        $filtros['id_embudo'] = $idEmbudo;
        $filtros['estado']    = 'abierta';
        [$where, $params] = $this->armarWhere($idEmpresa, $filtros, $idUsuarioFiltro);
        $params[':max'] = self::MAX_TARJETAS_ETAPA;

        $sql = 'SELECT * FROM (
                    SELECT ' . self::COLUMNAS . ",
                           EXISTS (SELECT 1 FROM crm_actividades a
                                    WHERE a.id_oportunidad = o.id AND a.eliminado = false
                                      AND a.completada = false AND a.vence_at < NOW()) AS actividad_vencida,
                           ROW_NUMBER() OVER (PARTITION BY o.id_etapa ORDER BY o.orden_kanban, o.id) AS rn,
                           COUNT(*) OVER (PARTITION BY o.id_etapa) AS cantidad_etapa,
                           SUM(o.valor_estimado) OVER (PARTITION BY o.id_etapa) AS valor_etapa
                    " . self::FROM . " {$where}
                ) x WHERE x.rn <= :max ORDER BY x.id_etapa, x.rn";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $e = (int) $r['id_etapa'];
            if (!isset($out[$e])) {
                $out[$e] = ['tarjetas' => [], 'cantidad' => (int) $r['cantidad_etapa'], 'valor' => (float) $r['valor_etapa']];
            }
            unset($r['rn'], $r['cantidad_etapa'], $r['valor_etapa']);
            $out[$e]['tarjetas'][] = $r;
        }
        return $out;
    }

    /**
     * Totales de la tarjeta de control con los mismos filtros (sin el de estado):
     * abiertas, valor abierto, valor ponderado, ganadas/perdidas del mes en curso.
     */
    public function getResumen(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro): array
    {
        $filtros['estado'] = '';
        [$where, $params] = $this->armarWhere($idEmpresa, $filtros, $idUsuarioFiltro);
        $st = $this->db->prepare(
            "SELECT
                COUNT(*) FILTER (WHERE o.estado = 'abierta')                                   AS abiertas,
                COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'abierta'), 0)         AS valor_abierto,
                COALESCE(SUM(o.valor_estimado * et.probabilidad / 100.0)
                         FILTER (WHERE o.estado = 'abierta'), 0)                               AS valor_ponderado,
                COUNT(*) FILTER (WHERE o.estado = 'ganada'
                                   AND o.cerrada_at >= date_trunc('month', CURRENT_DATE))      AS ganadas_mes,
                COALESCE(SUM(o.valor_estimado) FILTER (WHERE o.estado = 'ganada'
                                   AND o.cerrada_at >= date_trunc('month', CURRENT_DATE)), 0)  AS valor_ganado_mes,
                COUNT(*) FILTER (WHERE o.estado = 'perdida'
                                   AND o.cerrada_at >= date_trunc('month', CURRENT_DATE))      AS perdidas_mes
             " . self::FROM . " {$where}"
        );
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'abiertas'         => (int) ($r['abiertas'] ?? 0),
            'valor_abierto'    => round((float) ($r['valor_abierto'] ?? 0), 2),
            'valor_ponderado'  => round((float) ($r['valor_ponderado'] ?? 0), 2),
            'ganadas_mes'      => (int) ($r['ganadas_mes'] ?? 0),
            'valor_ganado_mes' => round((float) ($r['valor_ganado_mes'] ?? 0), 2),
            'perdidas_mes'     => (int) ($r['perdidas_mes'] ?? 0),
        ];
    }

    /** Ficha completa (para el modal). */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ', o.descripcion, o.id_empresa, mp.nombre AS motivo_perdida,
                    cl.nombre AS cliente, o.id_responsable, o.updated_at,
                    c.id_cliente AS contacto_id_cliente, clc.nombre AS contacto_cliente,
                    clc.identificacion AS contacto_cliente_identificacion,
                    uc.nombre AS creado_por
             ' . self::FROM . '
             LEFT JOIN crm_motivos_perdida mp ON mp.id = o.id_motivo_perdida
             LEFT JOIN clientes cl ON cl.id = o.id_cliente AND cl.id_empresa = o.id_empresa
             LEFT JOIN clientes clc ON clc.id = c.id_cliente AND clc.id_empresa = o.id_empresa
             LEFT JOIN usuarios uc ON uc.id = o.created_by
             WHERE o.id = :id AND o.id_empresa = :e AND o.eliminado = false'
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Bloquea la fila de la oportunidad hasta el fin de la transacción (dos
     * usuarios arrastrando la misma tarjeta). Devuelve la fila cruda o null.
     */
    public function bloquear(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM crm_oportunidades WHERE id = :id AND id_empresa = :e AND eliminado = false FOR UPDATE'
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Siguiente número correlativo de la empresa. Toma un candado por empresa que
     * se libera con el COMMIT/ROLLBACK: llamar dentro de la transacción y antes del INSERT.
     */
    public function siguienteNumero(int $idEmpresa): int
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_oportunidad_num:' || CAST(:e AS text)))");
        $st->execute([':e' => $idEmpresa]);
        $st = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) + 1 FROM crm_oportunidades WHERE id_empresa = :e');
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /** Orden para una tarjeta nueva: al final de su columna. */
    public function ordenAlFinal(int $idEmpresa, int $idEtapa): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(orden_kanban), 0) + 1 FROM crm_oportunidades
              WHERE id_empresa = :e AND id_etapa = :t AND eliminado = false AND estado = 'abierta'"
        );
        $st->execute([':e' => $idEmpresa, ':t' => $idEtapa]);
        return (float) $st->fetchColumn();
    }

    public function insertar(array $d, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_oportunidades
                (id_empresa, numero, titulo, id_contacto, id_embudo, id_etapa, id_responsable, id_vendedor,
                 valor_estimado, fecha_cierre_estimada, id_origen, id_conversacion, descripcion,
                 estado, etapa_desde, orden_kanban, created_by, updated_by)
             VALUES
                (:e, :numero, :titulo, :contacto, :embudo, :etapa, :resp, :vend,
                 :valor, :cierre, :origen, :conv, :descr,
                 'abierta', NOW(), :orden, :u, :u2)
             RETURNING id"
        );
        $st->execute([
            ':e' => $idEmpresa, ':numero' => (int) $d['numero'],
            ':titulo' => $this->caparTexto('titulo', $d['titulo']),
            ':contacto' => (int) $d['id_contacto'], ':embudo' => (int) $d['id_embudo'], ':etapa' => (int) $d['id_etapa'],
            ':resp' => $d['id_responsable'] ?: null, ':vend' => $d['id_vendedor'] ?: null,
            ':valor' => $d['valor_estimado'], ':cierre' => $d['fecha_cierre_estimada'] ?: null,
            ':origen' => $d['id_origen'] ?: null, ':conv' => $d['id_conversacion'] ?? null,
            ':descr' => $d['descripcion'] ?? null, ':orden' => $d['orden_kanban'],
            ':u' => $idUsuario, ':u2' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Datos editables de la ficha. La etapa y el estado cambian por sus propias acciones. */
    public function actualizarDatos(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_oportunidades SET
                titulo = :titulo, id_contacto = :contacto, id_responsable = :resp, id_vendedor = :vend,
                valor_estimado = :valor, fecha_cierre_estimada = :cierre, id_origen = :origen,
                descripcion = :descr, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':titulo' => $this->caparTexto('titulo', $d['titulo']), ':contacto' => (int) $d['id_contacto'],
            ':resp' => $d['id_responsable'] ?: null, ':vend' => $d['id_vendedor'] ?: null,
            ':valor' => $d['valor_estimado'], ':cierre' => $d['fecha_cierre_estimada'] ?: null,
            ':origen' => $d['id_origen'] ?: null, ':descr' => $d['descripcion'] ?? null,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    /**
     * Cambia embudo/etapa/estado y los campos de cierre en una sola escritura.
     * `etapa_desde` se reinicia solo si la etapa cambió.
     */
    public function cambiarEtapa(int $id, int $idEmpresa, int $idEmbudo, int $idEtapa, string $estado, float $orden, array $cierre, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_oportunidades SET
                etapa_desde = CASE WHEN id_etapa <> :etapa_cmp THEN NOW() ELSE etapa_desde END,
                id_embudo = :embudo, id_etapa = :etapa, estado = :estado, orden_kanban = :orden,
                id_cliente = COALESCE(:cliente, id_cliente),
                id_motivo_perdida = :motivo, detalle_perdida = :detalle,
                cerrada_at = :cerrada,
                updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':etapa_cmp' => $idEtapa, ':embudo' => $idEmbudo, ':etapa' => $idEtapa, ':estado' => $estado,
            ':orden' => $orden, ':cliente' => $cierre['id_cliente'] ?? null,
            ':motivo' => $cierre['id_motivo_perdida'] ?? null, ':detalle' => $cierre['detalle_perdida'] ?? null,
            ':cerrada' => $estado === 'abierta' ? null : date('Y-m-d H:i:s'),
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function registrarCambioEtapa(int $idEmpresa, int $idOportunidad, ?int $etapaAnterior, int $etapaNueva, int $idUsuario): void
    {
        $st = $this->db->prepare(
            'INSERT INTO crm_oportunidades_etapas_log (id_empresa, id_oportunidad, id_etapa_anterior, id_etapa_nueva, id_usuario)
             VALUES (:e, :o, :a, :n, :u)'
        );
        $st->execute([':e' => $idEmpresa, ':o' => $idOportunidad, ':a' => $etapaAnterior, ':n' => $etapaNueva, ':u' => $idUsuario]);
    }

    /** Historial de etapas de la oportunidad (pestaña Historial). */
    public function getHistorialEtapas(int $id, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT l.fecha, ea.nombre AS etapa_anterior, en.nombre AS etapa_nueva, en.tipo, u.nombre AS usuario
               FROM crm_oportunidades_etapas_log l
               LEFT JOIN crm_etapas ea ON ea.id = l.id_etapa_anterior
               JOIN crm_etapas en ON en.id = l.id_etapa_nueva
               LEFT JOIN usuarios u ON u.id = l.id_usuario
              WHERE l.id_oportunidad = :id AND l.id_empresa = :e
              ORDER BY l.fecha DESC, l.id DESC"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Enlaza la proforma y toma su total como valor de la oportunidad (decisión 8). */
    public function vincularProforma(int $id, int $idProforma, float $total, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_oportunidades SET id_proforma = :p, valor_estimado = :v, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':p' => $idProforma, ':v' => round($total, 2), ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Al editar la proforma, la oportunidad abierta que la tiene enlazada sigue su total. */
    public function actualizarValorPorProforma(int $idProforma, float $total, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_oportunidades SET valor_estimado = :v, updated_at = NOW(), updated_by = :u
              WHERE id_proforma = :p AND id_empresa = :e AND eliminado = false AND estado = 'abierta'
                AND valor_estimado <> :v2"
        );
        $st->execute([':v' => round($total, 2), ':u' => $idUsuario, ':p' => $idProforma, ':e' => $idEmpresa, ':v2' => round($total, 2)]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_oportunidades SET eliminado = true, deleted_at = NOW(), deleted_by = :u,
                    updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Oportunidades de un contacto (ficha del contacto / panel de la bandeja). */
    public function getPorContacto(int $idContacto, int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa, ':ct' => $idContacto];
        $where = $this->getBaseWhere($idEmpresa, 'o') . ' AND o.id_contacto = :ct'
               . CrmContactoRepository::condicionPropios('o', $params, $idUsuarioFiltro);
        $st = $this->db->prepare('SELECT ' . self::COLUMNAS . ' ' . self::FROM . " {$where} ORDER BY o.created_at DESC, o.id DESC LIMIT 50");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
