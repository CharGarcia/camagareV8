<?php
/**
 * Modelo BancoEcuador - CRUD de bancos de Ecuador
 * Tabla: bancos_ecuador
 * Campos: id_bancos, codigo_banco, nombre_banco, spi, sci, status
 */

declare(strict_types=1);

namespace App\models;

class BancoEcuador extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo_banco', 'nombre_banco', 'spi', 'sci', 'status'];

    /**
     * Lista todos los bancos con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'nombre_banco', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'nombre_banco';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo_banco LIKE '%{$b}%' OR nombre_banco LIKE '%{$b}%' OR spi LIKE '%{$b}%' OR sci LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_bancos AS id, codigo_banco, nombre_banco, spi, sci, status FROM bancos_ecuador{$where} ORDER BY {$col} {$dir}",
            "SELECT id, codigo_banco, nombre_banco, spi, sci, status FROM bancos_ecuador{$where} ORDER BY {$col} {$dir}",
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
     * Crea un banco
     */
    public function crear(string $codigoBanco, string $nombreBanco, string $spi, string $sci, int $status): int
    {
        $codigoBanco = $this->escape($codigoBanco);
        $nombreBanco = $this->escape($nombreBanco);
        $spi = $this->escape($spi);
        $sci = $this->escape($sci);
        $status = $status ? 1 : 0;
        $sql = "INSERT INTO bancos_ecuador (codigo_banco, nombre_banco, spi, sci, status) VALUES ('{$codigoBanco}', '{$nombreBanco}', '{$spi}', '{$sci}', {$status})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un banco
     */
    public function actualizar(int $id, string $codigoBanco, string $nombreBanco, string $spi, string $sci, int $status): bool
    {
        $id = (int) $id;
        $codigoBanco = $this->escape($codigoBanco);
        $nombreBanco = $this->escape($nombreBanco);
        $spi = $this->escape($spi);
        $sci = $this->escape($sci);
        $status = $status ? 1 : 0;
        $queries = [
            "UPDATE bancos_ecuador SET codigo_banco='{$codigoBanco}', nombre_banco='{$nombreBanco}', spi='{$spi}', sci='{$sci}', status={$status} WHERE id_bancos={$id}",
            "UPDATE bancos_ecuador SET codigo_banco='{$codigoBanco}', nombre_banco='{$nombreBanco}', spi='{$spi}', sci='{$sci}', status={$status} WHERE id={$id}",
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
