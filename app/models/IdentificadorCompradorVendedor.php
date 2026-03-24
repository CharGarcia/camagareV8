<?php
/**
 * Modelo IdentificadorCompradorVendedor - CRUD de tipos de identificación comprador/vendedor
 * Tabla: identificador_comprador_vendedor
 * Campos: codigo, nombre, tipo (Comprador, Vendedor), status
 */

declare(strict_types=1);

namespace App\models;

class IdentificadorCompradorVendedor extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'tipo', 'status'];

    /** 1 = Comprador, 2 = Vendedor */
    public const TIPO_COMPRADOR = 1;
    public const TIPO_VENDEDOR = 2;

    /**
     * Lista todos los identificadores con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'codigo', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $tb = strtolower($buscar);
            $condTipo = '';
            if (str_contains($tb, 'comprador')) {
                $condTipo = " OR tipo = 1";
            }
            if (str_contains($tb, 'vendedor')) {
                $condTipo .= " OR tipo = 2";
            }
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%' OR CAST(tipo AS CHAR) LIKE '%{$b}%'{$condTipo})";
        }
        $queries = [
            "SELECT id_identificador_comprador_vendedor AS id, codigo, nombre, tipo, status FROM identificador_comprador_vendedor{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, codigo, nombre, tipo, status FROM identificador_comprador_vendedor{$where} ORDER BY {$col} {$dir}",
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
     * Verifica si ya existe un identificador con el código dado.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_identificador_comprador_vendedor != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM identificador_comprador_vendedor WHERE codigo = '{$cod}'{$excluir}",
            "SELECT 1 FROM identificador_comprador_vendedor WHERE codigo = '{$cod}'{$excluirAlt}",
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
     * Elimina un identificador
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM identificador_comprador_vendedor WHERE id_identificador_comprador_vendedor = {$id}",
            "DELETE FROM identificador_comprador_vendedor WHERE id = {$id}",
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
     * Normaliza tipo a 1 (Comprador) o 2 (Vendedor)
     */
    private function normalizarTipo($tipo): int
    {
        $t = is_numeric($tipo) ? (int) $tipo : 1;
        return $t === 2 ? 2 : 1;
    }

    /**
     * Crea un identificador
     */
    public function crear(string $codigo, string $nombre, int $tipo, int $status): int
    {
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $tip = $this->normalizarTipo($tipo);
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO identificador_comprador_vendedor (codigo, nombre, tipo, status) VALUES ('{$cod}', '{$nom}', {$tip}, {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un identificador
     */
    public function actualizar(int $id, string $codigo, string $nombre, int $tipo, int $status): bool
    {
        $id = (int) $id;
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $tip = $this->normalizarTipo($tipo);
        $st = $status ? 1 : 0;
        $queries = [
            "UPDATE identificador_comprador_vendedor SET codigo='{$cod}', nombre='{$nom}', tipo={$tip}, status={$st} WHERE id_identificador_comprador_vendedor={$id}",
            "UPDATE identificador_comprador_vendedor SET codigo='{$cod}', nombre='{$nom}', tipo={$tip}, status={$st} WHERE id={$id}",
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
