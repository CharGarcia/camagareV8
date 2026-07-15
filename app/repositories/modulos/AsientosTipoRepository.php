<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;

class AsientosTipoRepository
{
    public function __construct()
    {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("ALTER TABLE asientos_tipo ADD COLUMN IF NOT EXISTS debe_haber VARCHAR(10) DEFAULT 'debe' NOT NULL");
        } catch (\Throwable $e) {
            // Silently catch exceptions
        }
    }
    /**
     * Obtiene el listado de asientos tipo con paginación y filtros.
     */
    public function getListado(string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::getConnection();

        $sql = "SELECT id, tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, creado_por_nombre, updated_at
                FROM (
                    SELECT ast.*, u.nombre as creado_por_nombre
                    FROM asientos_tipo ast
                    LEFT JOIN usuarios u ON ast.created_by = u.id
                    WHERE ast.eliminado = false
                ) as q";
                
        $params = [];

        // El filtro va en la consulta EXTERNA (la subconsulta ya cerró su propio WHERE), por eso
        // debe empezar con WHERE y no con AND: con AND el SQL quedaba inválido y la búsqueda
        // reventaba con "error de sintaxis en o cerca de «AND»".
        if ($buscar !== '') {
            $sql .= " WHERE (codigo ILIKE :buscar OR referencia ILIKE :buscar OR detalle ILIKE :buscar OR tipo_asiento ILIKE :buscar)";
            $params[':buscar'] = "%$buscar%";
        }

        $sqlCount = "SELECT COUNT(*) FROM ($sql) as sub";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $ordenColPermitidas = ['tipo_asiento', 'referencia', 'codigo', 'id'];
        $ordenCol = in_array($ordenCol, $ordenColPermitidas, true) ? $ordenCol : 'id';
        $ordenDir = in_array(strtoupper($ordenDir), ['ASC', 'DESC'], true) ? strtoupper($ordenDir) : 'ASC';

        $sql .= " ORDER BY {$ordenCol} {$ordenDir}";
        
        if ($perPage > 0) {
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Obtiene un asiento tipo por ID.
     */
    public function getAsientoTipo(int $id): ?array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM asientos_tipo WHERE id = :id AND eliminado = false";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Verifica si un código de asiento tipo ya existe.
     */
    public function codigoExiste(string $codigo, int $excludeId = 0): bool
    {
        $pdo = Database::getConnection();
        $sql = "SELECT COUNT(*) FROM asientos_tipo WHERE codigo = :codigo AND eliminado = false";
        $params = [':codigo' => $codigo];

        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Guarda o actualiza un asiento tipo.
     */
    public function guardarAsientoTipo(array $data, int $idUsuario): int
    {
        $pdo = Database::getConnection();
        $id = (int)($data['id'] ?? 0);
        $isUpdate = $id > 0;

        if ($isUpdate) {
            $sql = "UPDATE asientos_tipo 
                    SET tipo_asiento = :tipo_asiento, referencia = :referencia, detalle = :detalle, 
                        codigo = :codigo, tipo_cuenta = :tipo_cuenta, debe_haber = :debe_haber, updated_by = :usuario, updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tipo_asiento' => $data['tipo_asiento'],
                ':referencia' => $data['referencia'],
                ':detalle' => $data['detalle'],
                ':codigo' => $data['codigo'],
                ':tipo_cuenta' => $data['tipo_cuenta'] ?: null,
                ':debe_haber' => $data['debe_haber'] ?: 'debe',
                ':usuario' => $idUsuario,
                ':id' => $id
            ]);
            return $id;
        } else {
            $sql = "INSERT INTO asientos_tipo 
                    (tipo_asiento, referencia, detalle, codigo, tipo_cuenta, debe_haber, created_by) 
                    VALUES (:tipo_asiento, :referencia, :detalle, :codigo, :tipo_cuenta, :debe_haber, :usuario) RETURNING id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tipo_asiento' => $data['tipo_asiento'],
                ':referencia' => $data['referencia'],
                ':detalle' => $data['detalle'],
                ':codigo' => $data['codigo'],
                ':tipo_cuenta' => $data['tipo_cuenta'] ?: null,
                ':debe_haber' => $data['debe_haber'] ?: 'debe',
                ':usuario' => $idUsuario
            ]);
            return (int)$stmt->fetchColumn();
        }
    }

    /**
     * Elimina lógicamente un asiento tipo.
     */
    public function eliminarAsientoTipo(int $id, int $idUsuario): void
    {
        $pdo = Database::getConnection();
        $sql = "UPDATE asientos_tipo 
                SET eliminado = true, deleted_by = :usuario, deleted_at = NOW() 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario, ':id' => $id]);
    }

    /**
     * Verifica si un asiento tipo ya ha sido configurado en la tabla de asientos_programados.
     */
    public function estaEnUso(int $idAsientoTipo): bool
    {
        $pdo = Database::getConnection();
        $sql = "SELECT COUNT(*) FROM asientos_programados 
                WHERE (id_asiento_tipo = :id OR (id_referencia = :id AND tipo_referencia = 'asientos tipo')) 
                AND eliminado = false";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idAsientoTipo]);
        return ((int)$stmt->fetchColumn()) > 0;
    }
}
