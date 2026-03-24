<?php
/**
 * Modelo TarifaIva - CRUD de tarifas IVA
 * Tabla: tarifa_iva
 * Campos: id, codigo, tarifa, porcentaje_iva, status
 */

declare(strict_types=1);

namespace App\models;

class TarifaIva extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo', 'tarifa', 'porcentaje_iva', 'status'];

    /**
     * Lista todas las tarifas con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'porcentaje_iva', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'porcentaje_iva';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR tarifa LIKE '%{$b}%' OR porcentaje_iva LIKE '%{$b}%')";
        }
        $sql = "SELECT id, codigo, tarifa, porcentaje_iva, status FROM tarifa_iva{$where} ORDER BY {$col} {$dir}";
        return $this->query($sql);
    }

    /**
     * Crea una tarifa IVA
     */
    public function crear(string $codigo, string $tarifa, int $porcentajeIva, int $status): int
    {
        $codigo = $this->escape($codigo);
        $tarifa = $this->escape($tarifa);
        $porcentajeIva = (int) $porcentajeIva;
        $status = $status ? 1 : 0;
        $sql = "INSERT INTO tarifa_iva (codigo, tarifa, porcentaje_iva, status) VALUES ('{$codigo}', '{$tarifa}', {$porcentajeIva}, {$status})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza una tarifa IVA
     */
    public function actualizar(int $id, string $codigo, string $tarifa, int $porcentajeIva, int $status): bool
    {
        $id = (int) $id;
        $codigo = $this->escape($codigo);
        $tarifa = $this->escape($tarifa);
        $porcentajeIva = (int) $porcentajeIva;
        $status = $status ? 1 : 0;
        $sql = "UPDATE tarifa_iva SET codigo='{$codigo}', tarifa='{$tarifa}', porcentaje_iva={$porcentajeIva}, status={$status} WHERE id={$id}";
        return $this->execute($sql);
    }
}
