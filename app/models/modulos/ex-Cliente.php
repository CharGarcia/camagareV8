<?php
declare(strict_types=1);

namespace App\models\modulos;

use App\core\Database;
use PDO;

class Cliente
{
    private PDO $db;

    public const COLUMNAS_ORDEN = ['nombre', 'identificacion', 'email', 'telefono', 'nombre_vendedor', 'status'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getTodos(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT * FROM clientes WHERE id_empresa = :id_empresa AND eliminado = false ORDER BY nombre ASC");
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    public function getTodosParaListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        
        $where = "WHERE c.id_empresa = :id_empresa AND c.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= " AND (c.nombre ILIKE :buscar OR c.identificacion ILIKE :buscar OR c.email ILIKE :buscar OR c.telefono ILIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }

        // Obtener total
        $sqlCount = "SELECT COUNT(*) FROM clientes c $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Obtener filas con nombre del vendedor
        if ($total > 0) {
            $limitOffset = "";
            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $limitOffset = " LIMIT $perPage OFFSET $offset";
            }
            // Columnas de orden: si es nombre_vendedor, ordenar por v.nombre
            $orderExpr = $ordenCol === 'nombre_vendedor' ? "v.nombre" : "c.\"{$ordenCol}\"";
            $sql = "SELECT c.*, v.nombre AS nombre_vendedor,
                           icv.nombre AS nombre_tipo_id,
                           p.nombre AS nombre_provincia,
                           ciu.nombre AS nombre_ciudad
                    FROM clientes c
                    LEFT JOIN vendedores v ON v.id = c.id_vendedor
                    LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                    LEFT JOIN provincia p ON p.codigo = c.provincia
                    LEFT JOIN ciudad ciu ON ciu.codigo = c.ciudad AND ciu.cod_prov = c.provincia
                    $where
                    ORDER BY $orderExpr $ordenDir
                    $limitOffset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
        } else {
            $rows = [];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM clientes WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false");
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function existeIdentificacion(int $idEmpresa, string $tipoId, string $identificacion, ?int $idExcluir = null): bool
    {
        $sql = "SELECT COUNT(*) FROM clientes 
                WHERE id_empresa = :id_empresa 
                  AND tipo_id = :tipo_id 
                  AND identificacion = :identificacion 
                  AND eliminado = false";
        $params = [
            ':id_empresa' => $idEmpresa,
            ':tipo_id' => $tipoId,
            ':identificacion' => $identificacion
        ];

        if ($idExcluir !== null) {
            $sql .= " AND id <> :id_excluir";
            $params[':id_excluir'] = $idExcluir;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, provincia, ciudad, status, id_vendedor, id_cuenta_cobrar, id_cuenta_ingreso, eliminado)
                VALUES (:id_empresa, :id_usuario, :nombre, :tipo_id, :identificacion, :telefono, :email, :direccion, :plazo, :provincia, :ciudad, :status, :id_vendedor, :id_cuenta_cobrar, :id_cuenta_ingreso, false)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_usuario' => $data['id_usuario'],
            ':nombre' => $data['nombre'],
            ':tipo_id' => $data['tipo_id'],
            ':identificacion' => $data['identificacion'],
            ':telefono' => $data['telefono'],
            ':email' => $data['email'],
            ':direccion' => $data['direccion'],
            ':plazo' => $data['plazo'] ?? 0,
            ':provincia' => $data['provincia'],
            ':ciudad' => $data['ciudad'],
            ':status' => $data['status'] ?? 1,
            ':id_vendedor' => $data['id_vendedor'],
            ':id_cuenta_cobrar' => $data['id_cuenta_cobrar'],
            ':id_cuenta_ingreso' => $data['id_cuenta_ingreso']
        ]);
        return (int) $this->db->lastInsertId('clientes_id_seq');
    }

    public function actualizar(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE clientes SET 
                nombre = :nombre,
                tipo_id = :tipo_id,
                identificacion = :identificacion,
                telefono = :telefono,
                email = :email,
                direccion = :direccion,
                plazo = :plazo,
                provincia = :provincia,
                ciudad = :ciudad,
                status = :status,
                id_vendedor = :id_vendedor,
                id_cuenta_cobrar = :id_cuenta_cobrar,
                id_cuenta_ingreso = :id_cuenta_ingreso,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre' => $data['nombre'],
            ':tipo_id' => $data['tipo_id'],
            ':identificacion' => $data['identificacion'],
            ':telefono' => $data['telefono'],
            ':email' => $data['email'],
            ':direccion' => $data['direccion'],
            ':plazo' => $data['plazo'],
            ':provincia' => $data['provincia'],
            ':ciudad' => $data['ciudad'],
            ':status' => $data['status'],
            ':id_vendedor' => $data['id_vendedor'],
            ':id_cuenta_cobrar' => $data['id_cuenta_cobrar'],
            ':id_cuenta_ingreso' => $data['id_cuenta_ingreso'],
            ':id' => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }
    
    public function eliminar(int $id, int $idEmpresa): bool
    {
        $sql = "UPDATE clientes SET eliminado = true, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
    }
}
