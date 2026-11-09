<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: contactos (leads o clientes en seguimiento). Tabla crm_contactos.
 *
 * Registros propios (decisión 4 del diseño): sin acceso total el usuario ve los
 * contactos que creó **o** que tiene asignados como responsable. Por eso no se
 * usa el filtro de usuario de getBaseWhere() (que solo mira created_by) sino
 * condicionPropios().
 */
class CrmContactoRepository extends BaseRepository
{
    protected string $table = 'crm_contactos';

    public const MAPA_ORDEN = [
        'nombre'           => 'c.nombre',
        'empresa_contacto' => 'c.empresa_contacto',
        'telefono'         => 'c.telefono',
        'correo'           => 'c.correo',
        'origen'           => 'og.nombre',
        'responsable'      => 'ur.nombre',
        'cliente'          => 'cl.nombre',
        'created_at'       => 'c.created_at',
    ];

    private const CAMPOS = [
        'nombre', 'empresa_contacto', 'cargo', 'telefono', 'correo', 'instagram_id',
        'instagram_usuario', 'linkedin_url', 'id_cliente', 'id_whatsapp_chat',
        'id_origen', 'id_responsable', 'notas',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /** `(c.created_by = :u OR c.id_responsable = :u)` para usuarios sin acceso total. */
    public static function condicionPropios(string $alias, array &$params, ?int $idUsuarioFiltro): string
    {
        if ($idUsuarioFiltro === null) {
            return '';
        }
        $params[':propio_c'] = $idUsuarioFiltro;
        $params[':propio_r'] = $idUsuarioFiltro;
        return " AND ({$alias}.created_by = :propio_c OR {$alias}.id_responsable = :propio_r)";
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $ordenMulti, ?int $idUsuarioFiltro = null): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c') . self::condicionPropios('c', $params, $idUsuarioFiltro);

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['c.nombre', 'c.empresa_contacto', 'c.telefono', 'c.correo', 'c.instagram_usuario'],
                $parsed['texto_libre'], $params, 'ctxt'
            );
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'nombre' => 'c.nombre', 'empresa' => 'c.empresa_contacto', 'correo' => 'c.correo',
                'telefono' => 'c.telefono', 'instagram' => 'c.instagram_usuario',
                'origen' => 'og.nombre', 'responsable' => 'ur.nombre', 'cliente' => 'cl.nombre',
            ],
            'fecha'  => ['creado' => 'c.created_at'],
        ]);

        $from = "FROM crm_contactos c
                 LEFT JOIN crm_origenes og ON og.id = c.id_origen
                 LEFT JOIN usuarios ur ON ur.id = c.id_responsable
                 LEFT JOIN clientes cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa";

        $st = $this->db->prepare("SELECT COUNT(*) {$from} {$where}");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = OrdenListado::clausula(OrdenListado::normalizar($ordenMulti), self::MAPA_ORDEN, 'c.nombre', 'c.id DESC');
        $limit = '';
        if ($perPage > 0) {
            $limit = ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (max(1, $page) - 1) * $perPage;
        }
        $st = $this->db->prepare(
            "SELECT c.id, c.nombre, c.empresa_contacto, c.cargo, c.telefono, c.correo,
                    c.instagram_usuario, c.linkedin_url, c.id_cliente, c.id_origen, c.id_responsable,
                    c.created_at, og.nombre AS origen, ur.nombre AS responsable, cl.nombre AS cliente,
                    (SELECT COUNT(*) FROM crm_oportunidades o
                      WHERE o.id_contacto = c.id AND o.eliminado = false AND o.estado = 'abierta') AS oportunidades_abiertas
             {$from} {$where} {$orderBy}{$limit}"
        );
        $st->execute($params);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** Ficha completa del contacto con nombres de origen, responsable y cliente. */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.*, og.nombre AS origen, ur.nombre AS responsable, cl.nombre AS cliente,
                    cl.identificacion AS cliente_identificacion
               FROM crm_contactos c
               LEFT JOIN crm_origenes og ON og.id = c.id_origen
               LEFT JOIN usuarios ur ON ur.id = c.id_responsable
               LEFT JOIN clientes cl ON cl.id = c.id_cliente AND cl.id_empresa = c.id_empresa
              WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Autocompletar del modal de oportunidad (respeta registros propios). */
    public function buscarParaSelect(int $idEmpresa, string $q, ?int $idUsuarioFiltro, ?int $id = null): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'c') . self::condicionPropios('c', $params, $idUsuarioFiltro);
        if ($id !== null) {
            $where .= ' AND c.id = :id';
            $params[':id'] = $id;
        } else {
            $cond = FiltrosBusqueda::condicionTexto(
                ['c.nombre', 'c.empresa_contacto', 'c.telefono', 'c.correo', 'c.instagram_usuario'], $q, $params, 'cq'
            );
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        $st = $this->db->prepare(
            "SELECT c.id, c.nombre, c.empresa_contacto, c.telefono, c.correo, c.instagram_usuario, c.id_cliente
               FROM crm_contactos c {$where} ORDER BY c.nombre, c.id LIMIT 20"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Contacto de una persona de Instagram (IGSID) en la empresa. */
    public function getPorInstagramId(int $idEmpresa, string $igsid): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM crm_contactos WHERE id_empresa = :e AND instagram_id = :i AND eliminado = false LIMIT 1'
        );
        $st->execute([':e' => $idEmpresa, ':i' => $igsid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Candado para «buscar o crear» el contacto de un IGSID (dos eventos simultáneos de la misma persona). */
    public function lockInstagram(int $idEmpresa, string $igsid): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('crm_contacto_ig:' || CAST(:e AS text) || ':' || :i))");
        $st->execute([':e' => $idEmpresa, ':i' => $igsid]);
    }

    /** $idUsuario null = lo creó el sistema (webhook). */
    public function insertar(array $d, int $idEmpresa, ?int $idUsuario): int
    {
        $cols = self::CAMPOS;
        $sql = 'INSERT INTO crm_contactos (id_empresa, ' . implode(', ', $cols) . ', created_by, updated_by)
                VALUES (:id_empresa, :' . implode(', :', $cols) . ', :created_by, :updated_by) RETURNING id';
        $st = $this->db->prepare($sql);
        $params = $this->parametros($d);
        $params[':id_empresa'] = $idEmpresa;
        $params[':created_by'] = $idUsuario;
        $params[':updated_by'] = $idUsuario;
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $sets = array_map(fn($c) => "{$c} = :{$c}", self::CAMPOS);
        $st = $this->db->prepare(
            'UPDATE crm_contactos SET ' . implode(', ', $sets) . ', updated_at = NOW(), updated_by = :updated_by
              WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false'
        );
        $params = $this->parametros($d);
        $params[':updated_by'] = $idUsuario;
        $params[':id'] = $id;
        $params[':id_empresa'] = $idEmpresa;
        $st->execute($params);
    }

    /** Enlaza el contacto a un cliente (al ganar). No pisa un enlace existente. */
    public function enlazarCliente(int $id, int $idCliente, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_contactos SET id_cliente = :c, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false AND id_cliente IS NULL"
        );
        $st->execute([':c' => $idCliente, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_contactos SET eliminado = true, deleted_at = NOW(), deleted_by = :u,
                    updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function contarOportunidades(int $id, int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM crm_oportunidades WHERE id_contacto = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /** Otro contacto vigente con el mismo correo o teléfono (aviso de posible duplicado). */
    public function buscarDuplicado(int $idEmpresa, ?string $correo, ?string $telefono, ?int $excluirId = null): ?array
    {
        if (($correo ?? '') === '' && ($telefono ?? '') === '') {
            return null;
        }
        $conds = [];
        $p = [':e' => $idEmpresa];
        if (($correo ?? '') !== '')   { $conds[] = 'lower(correo) = lower(:co)'; $p[':co'] = $correo; }
        if (($telefono ?? '') !== '') { $conds[] = 'telefono = :te'; $p[':te'] = $telefono; }
        $sql = 'SELECT id, nombre FROM crm_contactos WHERE id_empresa = :e AND eliminado = false AND ('
             . implode(' OR ', $conds) . ')';
        if ($excluirId !== null) { $sql .= ' AND id <> :x'; $p[':x'] = $excluirId; }
        $st = $this->db->prepare($sql . ' ORDER BY id LIMIT 1');
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function parametros(array $d): array
    {
        $p = [];
        foreach (self::CAMPOS as $c) {
            $v = $d[$c] ?? null;
            if (is_string($v)) {
                $v = trim($v);
                $v = $v === '' ? null : $this->caparTexto($c, $v);
            }
            $p[':' . $c] = $v;
        }
        return $p;
    }
}
