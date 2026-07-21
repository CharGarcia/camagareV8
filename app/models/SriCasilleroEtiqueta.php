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
            $where .= " AND (
                seccion ILIKE '%{$b}%'
                OR descripcion ILIKE '%{$b}%'
                OR COALESCE(casillero_bruto,'') ILIKE '%{$b}%'
                OR COALESCE(casillero_neto,'') ILIKE '%{$b}%'
                OR COALESCE(casillero_impuesto,'') ILIKE '%{$b}%'
                OR CAST(orden AS TEXT) ILIKE '%{$b}%'
            ) ";
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
        int $idUsuario,
        string $fuenteValor = 'documentos',
        bool $editable = false
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
        $editableVal = $editable ? 'true' : 'false';
        $tipo = $this->escape($tipo);
        $idUsuario = (int) $idUsuario;

        $fuenteValor = $this->escape($fuenteValor !== '' ? $fuenteValor : 'documentos');

        $sql = "INSERT INTO sri_casilleros_etiquetas (
                    casillero_bruto, casillero_neto, casillero_impuesto,
                    seccion, descripcion, orden, indent, bold, editable, tipo, fuente_valor,
                    formula_bruto, formula_neto, formula_impuesto,
                    created_by, updated_by
                )
                VALUES (
                    {$cBruto}, {$cNeto}, {$cImp},
                    '{$seccion}', '{$descripcion}', {$orden}, {$indent}, {$boldVal}, {$editableVal}, '{$tipo}', '{$fuenteValor}',
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
        int $idUsuario,
        string $fuenteValor = 'documentos',
        bool $editable = false
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
        $editableVal = $editable ? 'true' : 'false';
        $tipo = $this->escape($tipo);
        $idUsuario = (int) $idUsuario;

        $fuenteValor = $this->escape($fuenteValor !== '' ? $fuenteValor : 'documentos');

        $sql = "UPDATE sri_casilleros_etiquetas
                SET casillero_bruto = {$cBruto}, casillero_neto = {$cNeto}, casillero_impuesto = {$cImp},
                    seccion = '{$seccion}', descripcion = '{$descripcion}',
                    orden = {$orden}, indent = {$indent}, bold = {$boldVal}, editable = {$editableVal}, tipo = '{$tipo}',
                    fuente_valor = '{$fuenteValor}',
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

    /**
     * Obtiene los valores por defecto para un nuevo registro
     */
    public function getValoresPorDefecto(): array
    {
        $sql = "SELECT MAX(orden) as max_orden, 
                       (SELECT seccion FROM sri_casilleros_etiquetas WHERE eliminado = false ORDER BY id DESC LIMIT 1) as ultima_seccion
                FROM sri_casilleros_etiquetas WHERE eliminado = false";
        try {
            $rows = $this->query($sql);
            $maxOrden = (int) ($rows[0]['max_orden'] ?? 0);
            $ultimaSeccion = $rows[0]['ultima_seccion'] ?? '400';
            return [
                'siguiente_orden' => $maxOrden + 1,
                'ultima_seccion' => $ultimaSeccion
            ];
        } catch (\Throwable $e) {
            return [
                'siguiente_orden' => 1,
                'ultima_seccion' => '400'
            ];
        }
    }
}
