<?php
/**
 * Modelo SriCasilleroEtiqueta - CRUD para la estructura del formulario 104
 * Tabla: sri_casilleros_etiquetas
 * Campos: id (PK), casillero_bruto, casillero_neto, casillero_impuesto, seccion, descripcion, orden, indent, bold, tipo, formulas, eliminado
 */

declare(strict_types=1);

namespace App\models;

class SriCasilleroEtiqueta extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['casillero_bruto', 'seccion', 'descripcion', 'orden'];

    /**
     * Lista todos los registros con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'seccion', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'seccion';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = " WHERE eliminado = false ";
        
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (COALESCE(casillero_bruto,'') LIKE '%{$b}%' OR seccion LIKE '%{$b}%' OR descripcion LIKE '%{$b}%') ";
        }
        
        $sql = "SELECT * FROM sri_casilleros_etiquetas {$where} ORDER BY {$col} {$dir}, orden ASC";
        
        try {
            return $this->query($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Obtiene una etiqueta por ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM sri_casilleros_etiquetas WHERE id = {$id} AND eliminado = false";
        try {
            $rows = $this->query($sql);
            return $rows[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Crea un registro
     */
    public function crear(
        string $casilleroBruto,
        string $casilleroNeto,
        string $casilleroImpuesto,
        string $seccion,
        string $descripcion,
        int $orden,
        int $indent,
        bool $bold,
        string $tipo,
        string $formulaBruto,
        string $formulaNeto,
        string $formulaImpuesto,
        int $idUsuario
    ): void {
        $cBruto = $casilleroBruto !== '' ? "'".$this->escape($casilleroBruto)."'" : "NULL";
        $cNeto = $casilleroNeto !== '' ? "'".$this->escape($casilleroNeto)."'" : "NULL";
        $cImp = $casilleroImpuesto !== '' ? "'".$this->escape($casilleroImpuesto)."'" : "NULL";
        
        $fBruto = $formulaBruto !== '' ? "'".$this->escape($formulaBruto)."'" : "NULL";
        $fNeto = $formulaNeto !== '' ? "'".$this->escape($formulaNeto)."'" : "NULL";
        $fImp = $formulaImpuesto !== '' ? "'".$this->escape($formulaImpuesto)."'" : "NULL";

        $seccion = $this->escape($seccion);
        $descripcion = $this->escape($descripcion);
        $orden = (int) $orden;
        $indent = (int) $indent;
        $boldVal = $bold ? 'true' : 'false';
        $tipo = $this->escape($tipo);
        $idUsuario = (int) $idUsuario;

        $sql = "INSERT INTO sri_casilleros_etiquetas (
                    casillero_bruto, casillero_neto, casillero_impuesto,
                    seccion, descripcion, orden, indent, bold, tipo,
                    formula_bruto, formula_neto, formula_impuesto,
                    created_by, updated_by
                )
                VALUES (
                    {$cBruto}, {$cNeto}, {$cImp},
                    '{$seccion}', '{$descripcion}', {$orden}, {$indent}, {$boldVal}, '{$tipo}',
                    {$fBruto}, {$fNeto}, {$fImp},
                    {$idUsuario}, {$idUsuario}
                )";
        
        $this->execute($sql);
    }

    /**
     * Actualiza un registro
     */
    public function actualizar(
        int $id,
        string $casilleroBruto,
        string $casilleroNeto,
        string $casilleroImpuesto,
        string $seccion,
        string $descripcion,
        int $orden,
        int $indent,
        bool $bold,
        string $tipo,
        string $formulaBruto,
        string $formulaNeto,
        string $formulaImpuesto,
        int $idUsuario
    ): bool {
        $cBruto = $casilleroBruto !== '' ? "'".$this->escape($casilleroBruto)."'" : "NULL";
        $cNeto = $casilleroNeto !== '' ? "'".$this->escape($casilleroNeto)."'" : "NULL";
        $cImp = $casilleroImpuesto !== '' ? "'".$this->escape($casilleroImpuesto)."'" : "NULL";
        
        $fBruto = $formulaBruto !== '' ? "'".$this->escape($formulaBruto)."'" : "NULL";
        $fNeto = $formulaNeto !== '' ? "'".$this->escape($formulaNeto)."'" : "NULL";
        $fImp = $formulaImpuesto !== '' ? "'".$this->escape($formulaImpuesto)."'" : "NULL";

        $seccion = $this->escape($seccion);
        $descripcion = $this->escape($descripcion);
        $orden = (int) $orden;
        $indent = (int) $indent;
        $boldVal = $bold ? 'true' : 'false';
        $tipo = $this->escape($tipo);
        $idUsuario = (int) $idUsuario;

        $sql = "UPDATE sri_casilleros_etiquetas 
                SET casillero_bruto = {$cBruto}, casillero_neto = {$cNeto}, casillero_impuesto = {$cImp},
                    seccion = '{$seccion}', descripcion = '{$descripcion}', 
                    orden = {$orden}, indent = {$indent}, bold = {$boldVal}, tipo = '{$tipo}', 
                    formula_bruto = {$fBruto}, formula_neto = {$fNeto}, formula_impuesto = {$fImp},
                    updated_by = {$idUsuario}, updated_at = CURRENT_TIMESTAMP
                WHERE id = {$id}";
        
        try {
            return $this->execute($sql);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Elimina logicamente un casillero
     */
    public function eliminar(int $id, int $idUsuario): bool
    {
        $idUsuario = (int) $idUsuario;
        $sql = "UPDATE sri_casilleros_etiquetas 
                SET eliminado = true, deleted_by = {$idUsuario}, deleted_at = CURRENT_TIMESTAMP 
                WHERE id = {$id}";
        try {
            return $this->execute($sql);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
