<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

class SuperciasEstructura
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function obtenerTodos(string $tipo = null): array
    {
        $sql = "SELECT * FROM supercias_estructuras WHERE eliminado = false";
        $params = [];
        if ($tipo) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        $sql .= " ORDER BY orden ASC, codigo ASC, subcodigo ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM supercias_estructuras WHERE id = :id AND eliminado = false");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Un casillero se identifica por tipo + código + subcódigo. En ECP el mismo código se
     * repite en varias filas (una por componente del patrimonio) y lo que cambia es el
     * subcódigo; por eso la unicidad NO es solo por código.
     */
    private function validarClaveUnica(array $datos, ?int $idExcluir = null): void
    {
        $sql = "SELECT id FROM supercias_estructuras
                WHERE tipo = :tipo AND codigo = :codigo AND COALESCE(subcodigo, '') = :subcodigo AND eliminado = false";
        $params = [
            ':tipo' => $datos['tipo'],
            ':codigo' => $datos['codigo'],
            ':subcodigo' => (string) ($datos['subcodigo'] ?? ''),
        ];
        if ($idExcluir !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $idExcluir;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $clave = $datos['codigo'] . ($params[':subcodigo'] !== '' ? ' / subcódigo ' . $params[':subcodigo'] : '');
            throw new \Exception("El casillero {$clave} ya existe en el tipo {$datos['tipo']}");
        }
    }

    public function actualizar(int $id, array $datos, int $usuarioId): bool
    {
        try {
            $this->db->beginTransaction();

            $this->validarClaveUnica($datos, $id);

            $orden = null;
            if (!empty($datos['codigo_anterior'])) {
                $stmt = $this->db->prepare("SELECT orden FROM supercias_estructuras WHERE tipo = :tipo AND codigo = :codigo LIMIT 1");
                $stmt->execute([':tipo' => $datos['tipo'], ':codigo' => $datos['codigo_anterior']]);
                $ordenAnterior = $stmt->fetchColumn();
                
                if ($ordenAnterior !== false) {
                    $orden = (int)$ordenAnterior + 1;
                    $stmtUpd = $this->db->prepare("UPDATE supercias_estructuras SET orden = orden + 1 WHERE tipo = :tipo AND orden >= :nuevoOrden");
                    $stmtUpd->execute([':tipo' => $datos['tipo'], ':nuevoOrden' => $orden]);
                }
            }

            // Si se dio un orden, actualizamos todo incluido el orden. Si no, dejamos el orden que ya tenía.
            if ($orden !== null) {
                $stmt = $this->db->prepare("
                    UPDATE supercias_estructuras 
                    SET tipo = :tipo, codigo = :codigo, subcodigo = :subcodigo, nombre = :nombre, formula = :formula, orden = :orden, updated_at = CURRENT_TIMESTAMP, updated_by = :usuarioId
                    WHERE id = :id AND eliminado = false
                ");
                $params = [
                    ':tipo' => $datos['tipo'],
                    ':codigo' => $datos['codigo'],
                    ':subcodigo' => $datos['subcodigo'] ?? '',
                    ':nombre' => $datos['nombre'],
                    ':formula' => $datos['formula'] === '' ? null : $datos['formula'],
                    ':orden' => $orden,
                    ':usuarioId' => $usuarioId,
                    ':id' => $id
                ];
            } else {
                $stmt = $this->db->prepare("
                    UPDATE supercias_estructuras 
                    SET tipo = :tipo, codigo = :codigo, subcodigo = :subcodigo, nombre = :nombre, formula = :formula, updated_at = CURRENT_TIMESTAMP, updated_by = :usuarioId
                    WHERE id = :id AND eliminado = false
                ");
                $params = [
                    ':tipo' => $datos['tipo'],
                    ':codigo' => $datos['codigo'],
                    ':subcodigo' => $datos['subcodigo'] ?? '',
                    ':nombre' => $datos['nombre'],
                    ':formula' => $datos['formula'] === '' ? null : $datos['formula'],
                    ':usuarioId' => $usuarioId,
                    ':id' => $id
                ];
            }
            
            $res = $stmt->execute($params);
            
            $this->db->commit();
            return $res;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    public function crear(array $datos, int $usuarioId): bool
    {
        try {
            $this->db->beginTransaction();

            $this->validarClaveUnica($datos);

            $orden = null;
            if (!empty($datos['codigo_anterior'])) {
                $stmt = $this->db->prepare("SELECT orden FROM supercias_estructuras WHERE tipo = :tipo AND codigo = :codigo LIMIT 1");
                $stmt->execute([':tipo' => $datos['tipo'], ':codigo' => $datos['codigo_anterior']]);
                $ordenAnterior = $stmt->fetchColumn();
                
                if ($ordenAnterior !== false) {
                    $orden = (int)$ordenAnterior + 1;
                    $stmtUpd = $this->db->prepare("UPDATE supercias_estructuras SET orden = orden + 1 WHERE tipo = :tipo AND orden >= :nuevoOrden");
                    $stmtUpd->execute([':tipo' => $datos['tipo'], ':nuevoOrden' => $orden]);
                }
            }
            
            if ($orden === null) {
                // Al final si no se encontró el anterior o no se envió
                $stmt = $this->db->prepare("SELECT MAX(orden) FROM supercias_estructuras WHERE tipo = :tipo");
                $stmt->execute([':tipo' => $datos['tipo']]);
                $maxOrden = $stmt->fetchColumn();
                $orden = $maxOrden !== false ? (int)$maxOrden + 1 : 1;
            }

            $stmt = $this->db->prepare("
                INSERT INTO supercias_estructuras (tipo, codigo, subcodigo, nombre, formula, orden, created_by)
                VALUES (:tipo, :codigo, :subcodigo, :nombre, :formula, :orden, :usuarioId)
            ");
            $res = $stmt->execute([
                ':tipo' => $datos['tipo'],
                ':codigo' => $datos['codigo'],
                ':subcodigo' => $datos['subcodigo'] ?? '',
                ':nombre' => $datos['nombre'],
                ':formula' => $datos['formula'] === '' ? null : $datos['formula'],
                ':orden' => $orden,
                ':usuarioId' => $usuarioId
            ]);
            
            $this->db->commit();
            return $res;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
