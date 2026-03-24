<?php
/**
 * Modelo FormaPagoSri - CRUD de formas de pago SRI
 * Tabla: formas_pago_sri
 * Campos: codigo, nombre (y id o id_forma_pago como PK)
 */

declare(strict_types=1);

namespace App\models;

class FormaPagoSri extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'status'];

    /**
     * Lista todas las formas de pago con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'codigo', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_forma_pago AS id, codigo, nombre, status FROM formas_pago_sri{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, codigo, nombre, status FROM formas_pago_sri{$where} ORDER BY {$col} {$dir}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->query($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /**
     * Verifica si ya existe una forma de pago con el código dado.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_forma_pago != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM formas_pago_sri WHERE codigo = '{$cod}'{$excluir}",
            "SELECT 1 FROM formas_pago_sri WHERE codigo = '{$cod}'{$excluirAlt}",
        ];
        foreach ($queries as $sql) {
            try {
                return !empty($this->query($sql));
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Elimina una forma de pago
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM formas_pago_sri WHERE id_forma_pago = {$id}",
            "DELETE FROM formas_pago_sri WHERE id = {$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Crea una forma de pago
     */
    public function crear(string $codigo, string $nombre, int $status): int
    {
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO formas_pago_sri (codigo, nombre, status) VALUES ('{$cod}', '{$nom}', {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza una forma de pago
     */
    public function actualizar(int $id, string $codigo, string $nombre, int $status): bool
    {
        $id = (int) $id;
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $st = $status ? 1 : 0;
        $queries = [
            "UPDATE formas_pago_sri SET codigo='{$cod}', nombre='{$nom}', status={$st} WHERE id_forma_pago={$id}",
            "UPDATE formas_pago_sri SET codigo='{$cod}', nombre='{$nom}', status={$st} WHERE id={$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}
