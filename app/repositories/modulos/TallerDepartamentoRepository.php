<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Catálogo de departamentos del taller (diagnóstico, mecánica, enderezada,
 * pintura, armado, control de calidad…).
 *
 * Es configurable por empresa a propósito: cada taller arma su propio flujo.
 * Mismo criterio que estaciones_impresion del KDS. Cada departamento tiene su
 * pantalla de tablet en modulos/taller-estacion?id_departamento=N.
 */
class TallerDepartamentoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('taller_departamentos');
    }

    /** Listado paginado para la pantalla de administración del catálogo. */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $where  = $this->getBaseWhere($idEmpresa, 'd', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (d.nombre ILIKE :b OR d.codigo ILIKE :b OR d.descripcion ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => ['nombre' => 'd.nombre', 'codigo' => 'd.codigo'],
            'exacto' => ['activo' => 'd.activo'],
        ]);

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM taller_departamentos d $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $colMap = [
            'nombre' => 'd.nombre',
            'codigo' => 'd.codigo',
            'orden'  => 'd.orden',
            'activo' => 'd.activo',
        ];
        $sort = $colMap[$ordenCol] ?? 'd.orden';
        $dir  = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $limit = '';
        if ($perPage > 0) {
            $limit = 'LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
        }

        $st = $this->db->prepare("SELECT d.* FROM taller_departamentos d $where ORDER BY $sort $dir, d.id ASC $limit");
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** Departamentos activos, en orden de flujo (para selects, tablero y estaciones). */
    public function getActivos(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, codigo, color, icono, orden, es_diagnostico, es_control_calidad
                FROM taller_departamentos
                WHERE id_empresa = :e AND eliminado = false AND activo = true
                ORDER BY orden ASC, nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        return $this->findById($id, $idEmpresa);
    }

    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM taller_departamentos
                WHERE id_empresa = :e AND UPPER(nombre) = UPPER(:n) AND eliminado = false";
        $params = [':e' => $idEmpresa, ':n' => $nombre];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /** ¿El departamento tiene órdenes activas? Evita eliminar algo en uso. */
    public function tieneOrdenesActivas(int $id, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM taller_ordenes
                WHERE id_empresa = :e AND eliminado = false AND id_departamento_actual = :d
                  AND estado NOT IN ('facturada','anulada','entregada')
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':d' => $id]);
        return (bool) $st->fetchColumn();
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO taller_departamentos
                    (id_empresa, nombre, codigo, descripcion, color, icono, orden,
                     es_diagnostico, es_control_calidad, activo, created_by, updated_by)
                VALUES
                    (:e, :nombre, :codigo, :descripcion, :color, :icono, :orden,
                     :diag, :cc, :activo, :u, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'           => $d['id_empresa'],
            ':nombre'      => $d['nombre'],
            ':codigo'      => $d['codigo'] ?? null,
            ':descripcion' => $d['descripcion'] ?? null,
            ':color'       => $d['color'] ?? '#0d6efd',
            ':icono'       => $d['icono'] ?? 'bi-tools',
            ':orden'       => (int) ($d['orden'] ?? 0),
            ':diag'        => !empty($d['es_diagnostico']) ? 'true' : 'false',
            ':cc'          => !empty($d['es_control_calidad']) ? 'true' : 'false',
            ':activo'      => !empty($d['activo']) ? 'true' : 'false',
            ':u'           => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function update(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE taller_departamentos SET
                    nombre = :nombre, codigo = :codigo, descripcion = :descripcion,
                    color = :color, icono = :icono, orden = :orden,
                    es_diagnostico = :diag, es_control_calidad = :cc, activo = :activo,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':nombre'      => $d['nombre'],
            ':codigo'      => $d['codigo'] ?? null,
            ':descripcion' => $d['descripcion'] ?? null,
            ':color'       => $d['color'] ?? '#0d6efd',
            ':icono'       => $d['icono'] ?? 'bi-tools',
            ':orden'       => (int) ($d['orden'] ?? 0),
            ':diag'        => !empty($d['es_diagnostico']) ? 'true' : 'false',
            ':cc'          => !empty($d['es_control_calidad']) ? 'true' : 'false',
            ':activo'      => !empty($d['activo']) ? 'true' : 'false',
            ':u'           => $d['id_usuario'],
            ':id'          => $id,
            ':e'           => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE taller_departamentos
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_empresa = :e AND eliminado = false"
        )->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);
    }
}
