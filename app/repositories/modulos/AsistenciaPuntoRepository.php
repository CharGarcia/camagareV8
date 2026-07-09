<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Puntos de servicio (dueños del QR de ubicación).
 */
class AsistenciaPuntoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asistencia_puntos');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        $whitelist = ['nombre', 'direccion', 'estado', 'radio_m', 'id'];
        $ordenCol  = in_array($ordenCol, $whitelist, true) ? $ordenCol : 'nombre';
        $ordenDir  = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $params = [':id_empresa' => $idEmpresa];
        $where  = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (p.nombre ILIKE :b OR p.direccion ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['nombre' => 'p.nombre', 'direccion' => 'p.direccion'],
            'exacto'   => ['estado' => 'p.estado'],
            'numerico' => ['radio' => 'p.radio_m'],
        ]);

        $from = "FROM {$this->table} p {$where}";

        $stTotal = $this->db->prepare("SELECT COUNT(*) {$from}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $sql = "SELECT p.* {$from} ORDER BY p.{$ordenCol} {$ordenDir}";
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, nombre, direccion, latitud, longitud, radio_m,
                    exige_gps, qr_token, qr_rotativo, estado,
                    created_by, updated_by, created_at, updated_at, eliminado
                ) VALUES (
                    :id_empresa, :nombre, :direccion, :latitud, :longitud, :radio_m,
                    :exige_gps, :qr_token, :qr_rotativo, :estado,
                    :id_u, :id_u, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => $d['id_empresa'],
            ':nombre'      => $d['nombre'],
            ':direccion'   => $d['direccion'] ?? null,
            ':latitud'     => $d['latitud'] !== null && $d['latitud'] !== '' ? $d['latitud'] : null,
            ':longitud'    => $d['longitud'] !== null && $d['longitud'] !== '' ? $d['longitud'] : null,
            ':radio_m'     => (int) ($d['radio_m'] ?? 150),
            ':exige_gps'   => !empty($d['exige_gps']) ? 'true' : 'false',
            ':qr_token'    => $d['qr_token'],
            ':qr_rotativo' => !empty($d['qr_rotativo']) ? 'true' : 'false',
            ':estado'      => $d['estado'] ?? 'activo',
            ':id_u'        => $d['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $d): bool
    {
        $sql = "UPDATE {$this->table} SET
                    nombre = :nombre,
                    direccion = :direccion,
                    latitud = :latitud,
                    longitud = :longitud,
                    radio_m = :radio_m,
                    exige_gps = :exige_gps,
                    qr_rotativo = :qr_rotativo,
                    estado = :estado,
                    updated_by = :id_u,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'      => $d['nombre'],
            ':direccion'   => $d['direccion'] ?? null,
            ':latitud'     => $d['latitud'] !== null && $d['latitud'] !== '' ? $d['latitud'] : null,
            ':longitud'    => $d['longitud'] !== null && $d['longitud'] !== '' ? $d['longitud'] : null,
            ':radio_m'     => (int) ($d['radio_m'] ?? 150),
            ':exige_gps'   => !empty($d['exige_gps']) ? 'true' : 'false',
            ':qr_rotativo' => !empty($d['qr_rotativo']) ? 'true' : 'false',
            ':estado'      => $d['estado'] ?? 'activo',
            ':id_u'        => $d['id_usuario'],
            ':id'          => $id,
            ':id_empresa'  => $idEmpresa,
        ]);
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    /** Resuelve un punto por el token de su QR (global, no filtra por empresa). */
    public function getByQrToken(string $qrToken): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE qr_token = :t AND eliminado = false AND estado = 'activo' LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':t' => $qrToken]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** ¿Existe ya ese qr_token (para garantizar unicidad al generar)? */
    public function existeQrToken(string $qrToken): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE qr_token = :t LIMIT 1");
        $st->execute([':t' => $qrToken]);
        return (bool) $st->fetchColumn();
    }
}
