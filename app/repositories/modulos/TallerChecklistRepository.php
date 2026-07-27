<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Plantilla del checklist de recepción del taller.
 *
 * Es la lista de lo que se revisa al recibir cada vehículo: accesorios,
 * carrocería, documentos y niveles. Al crear una orden se copia a
 * taller_ordenes_checklist, de modo que cada orden conserva lo que se revisó
 * ese día aunque la plantilla cambie después.
 */
class TallerChecklistRepository extends BaseRepository
{
    /** Grupos en los que se organiza la revisión. */
    public const GRUPOS = [
        'accesorios' => 'Accesorios',
        'carroceria' => 'Carrocería',
        'documentos' => 'Documentos',
        'niveles'    => 'Niveles',
    ];

    public function __construct()
    {
        parent::__construct('taller_checklist_plantilla');
    }

    /** Listado paginado para la pantalla de administración. */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'c', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (c.item ILIKE :b OR c.grupo ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => ['item' => 'c.item'],
            'exacto' => ['grupo' => 'c.grupo', 'activo' => 'c.activo'],
        ]);

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM taller_checklist_plantilla c $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $colMap = [
            'item'   => 'c.item',
            'grupo'  => 'c.grupo',
            'orden'  => 'c.orden',
            'activo' => 'c.activo',
        ];
        $sort = $colMap[$ordenCol] ?? 'c.orden';
        $dir  = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $limit = '';
        if ($perPage > 0) {
            $limit = 'LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }

        $st = $this->db->prepare("SELECT c.* FROM taller_checklist_plantilla c $where ORDER BY $sort $dir, c.id ASC $limit");
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Plantilla en orden de revisión. Con $soloActivos = true devuelve lo que
     * se copia a una orden nueva; con false, todo el catálogo para administrar.
     */
    public function getPlantilla(int $idEmpresa, bool $soloActivos = true): array
    {
        $sql = "SELECT id, grupo, item, orden, activo
                FROM taller_checklist_plantilla
                WHERE id_empresa = :e AND eliminado = false"
             . ($soloActivos ? " AND activo = true" : "")
             . " ORDER BY orden ASC, id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        return $this->findById($id, $idEmpresa);
    }

    /** Evita repetir el mismo punto de revisión dentro de un grupo. */
    public function existeItem(int $idEmpresa, string $grupo, string $item, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM taller_checklist_plantilla
                WHERE id_empresa = :e AND grupo = :g AND UPPER(item) = UPPER(:i) AND eliminado = false";
        $params = [':e' => $idEmpresa, ':g' => $grupo, ':i' => $item];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /** Siguiente posición libre, dejando hueco para intercalar después. */
    public function siguienteOrden(int $idEmpresa): int
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(MAX(orden), 0) + 10 FROM taller_checklist_plantilla
             WHERE id_empresa = :e AND eliminado = false"
        );
        $st->execute([':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO taller_checklist_plantilla (id_empresa, grupo, item, orden, activo, created_by, updated_by)
                VALUES (:e, :grupo, :item, :orden, :activo, :u, :u) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'      => $d['id_empresa'],
            ':grupo'  => $d['grupo'] ?? 'accesorios',
            ':item'   => $d['item'],
            ':orden'  => (int) ($d['orden'] ?? 0),
            ':activo' => \App\Helpers\Booleano::sql($d['activo'] ?? true),
            ':u'      => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function update(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE taller_checklist_plantilla SET
                    grupo = :grupo, item = :item, orden = :orden, activo = :activo,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':grupo'  => $d['grupo'] ?? 'accesorios',
            ':item'   => $d['item'],
            ':orden'  => (int) ($d['orden'] ?? 0),
            ':activo' => \App\Helpers\Booleano::sql($d['activo'] ?? true),
            ':u'      => $d['id_usuario'],
            ':id'     => $id,
            ':e'      => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE taller_checklist_plantilla
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }
}
