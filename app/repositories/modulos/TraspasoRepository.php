<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class TraspasoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('traspasos_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE t.id_empresa = :id_empresa AND t.eliminado = false AND t.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (t.numero_traspaso ILIKE :buscar OR fo.nombre ILIKE :buscar OR fd.nombre ILIKE :buscar OR t.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'origen'  => 'fo.nombre',
                'destino' => 'fd.nombre',
                'numero'  => 't.numero_traspaso',
                'nro'     => 't.numero_traspaso',
                'obs'     => 't.observaciones',
            ],
            'exacto'   => [ 'estado' => 't.estado' ],
            'fecha'    => [ 'fecha' => 't.fecha_emision', 'fecha_emision' => 't.fecha_emision' ],
            'numerico' => [ 'monto' => 't.monto' ],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM traspasos_cabecera t
                     INNER JOIN empresa_formas_pago fo ON t.id_forma_origen = fo.id
                     INNER JOIN empresa_formas_pago fd ON t.id_forma_destino = fd.id
                     $where";
        $total = (int) $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'fecha_emision', 'numero_traspaso', 'monto', 'estado'];
        if (!in_array($ordenCol, $allowedCols)) {
            $ordenCol = 'fecha_emision';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT t.*,
                       fo.nombre AS origen_nombre,
                       fd.nombre AS destino_nombre,
                       u.nombre AS usuario_nombre
                FROM traspasos_cabecera t
                INNER JOIN empresa_formas_pago fo ON t.id_forma_origen = fo.id
                INNER JOIN empresa_formas_pago fd ON t.id_forma_destino = fd.id
                LEFT JOIN usuarios u ON t.created_by = u.id
                $where
                ORDER BY t.$ordenCol $ordenDir
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT t.*,
                       fo.nombre AS origen_nombre, fo.tipo AS origen_tipo,
                       fd.nombre AS destino_nombre, fd.tipo AS destino_tipo,
                       u.nombre AS usuario_nombre
                FROM traspasos_cabecera t
                INNER JOIN empresa_formas_pago fo ON t.id_forma_origen = fo.id
                INNER JOIN empresa_formas_pago fd ON t.id_forma_destino = fd.id
                LEFT JOIN usuarios u ON t.created_by = u.id
                WHERE t.id = :id AND t.id_empresa = :id_empresa AND t.eliminado = FALSE";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO traspasos_cabecera (
                    id_empresa, id_punto_emision, establecimiento, punto_emision, secuencial, numero_traspaso,
                    fecha_emision, id_forma_origen, id_forma_destino, monto, observaciones, estado, tipo_ambiente,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_punto, :est, :pto, :sec, :num,
                    :fecha, :forma_origen, :forma_destino, :monto, :obs, :estado,
                    (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa),
                    :usr, :usr
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => (int) $data['id_empresa'],
            ':id_punto'      => !empty($data['id_punto_emision']) ? (int) $data['id_punto_emision'] : null,
            ':est'           => $data['establecimiento'] ?? null,
            ':pto'           => $data['punto_emision'] ?? null,
            ':sec'           => $data['secuencial'] ?? null,
            ':num'           => $data['numero_traspaso'],
            ':fecha'         => $data['fecha_emision'],
            ':forma_origen'  => (int) $data['id_forma_origen'],
            ':forma_destino' => (int) $data['id_forma_destino'],
            ':monto'         => (float) $data['monto'],
            ':obs'           => $data['observaciones'] ?? null,
            ':estado'        => $data['estado'] ?? 'registrado',
            ':usr'           => (int) $data['usuario_id'],
        ]);

        return (int) $st->fetchColumn();
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE traspasos_cabecera SET estado = 'anulado', updated_by = :usr, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':emp' => $idEmpresa, ':usr' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    /** Enlaza (o desvincula con null) el asiento contable generado al traspaso. */
    public function updateAsientoContable(int $idTraspaso, ?int $idAsiento): void
    {
        $this->query(
            "UPDATE traspasos_cabecera SET id_asiento_contable = ? WHERE id = ?",
            [$idAsiento !== null && $idAsiento > 0 ? $idAsiento : null, $idTraspaso]
        );
    }

    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial): bool
    {
        $sql = "SELECT COUNT(*) FROM traspasos_cabecera
                WHERE id_empresa = ? AND establecimiento = (SELECT codigo FROM empresa_establecimiento WHERE id = ?)
                  AND punto_emision = (SELECT codigo_punto FROM empresa_punto_emision WHERE id = ?)
                  AND secuencial = ? AND eliminado = FALSE
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)";
        return (int) $this->query($sql, [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial, $idEmpresa])->fetchColumn() > 0;
    }
}
