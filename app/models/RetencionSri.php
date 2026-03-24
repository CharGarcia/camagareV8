<?php
/**
 * Modelo RetencionSri - CRUD de retenciones SRI
 * Tabla: retenciones_sri
 * Campos: id_ret, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, status, desde, hasta
 */

declare(strict_types=1);

namespace App\models;

class RetencionSri extends BaseModel
{
    public const IMPUESTOS = ['RENTA', 'IVA', 'ISD'];

    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo_ret', 'concepto_ret', 'porcentaje_ret', 'impuesto_ret', 'cod_anexo_ret', 'status', 'desde', 'hasta'];

    /**
     * Lista todas las retenciones con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'codigo_ret', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo_ret';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo_ret LIKE '%{$b}%' OR concepto_ret LIKE '%{$b}%' OR impuesto_ret LIKE '%{$b}%' OR cod_anexo_ret LIKE '%{$b}%' OR CAST(porcentaje_ret AS CHAR) LIKE '%{$b}%' OR desde LIKE '%{$b}%' OR hasta LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_ret AS id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, status, desde, hasta FROM retenciones_sri{$where} ORDER BY {$col} {$dir}",
            "SELECT id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, status, desde, hasta FROM retenciones_sri{$where} ORDER BY {$col} {$dir}",
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
     * Obtiene una retención por ID
     */
    public function getById(int $id): ?array
    {
        $id = (int) $id;
        $queries = [
            "SELECT id_ret AS id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, status, desde, hasta FROM retenciones_sri WHERE id_ret = {$id}",
            "SELECT id, codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, status, desde, hasta FROM retenciones_sri WHERE id = {$id}",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                return $rows[0] ?? null;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Valida: no puede repetirse (codigo_ret, concepto_ret, porcentaje_ret)
     */
    public function existeCodigoConceptoPorcentaje(string $codigo, string $concepto, float $porcentaje, ?int $excluirId = null): bool
    {
        $codigo = $this->escape($codigo);
        $concepto = $this->escape($concepto);
        $porcentaje = (float) $porcentaje;
        $excluir = $excluirId !== null ? ' AND id_ret != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM retenciones_sri WHERE codigo_ret='{$codigo}' AND concepto_ret='{$concepto}' AND porcentaje_ret={$porcentaje}{$excluir}",
            "SELECT 1 FROM retenciones_sri WHERE codigo_ret='{$codigo}' AND concepto_ret='{$concepto}' AND porcentaje_ret={$porcentaje}{$excluirAlt}",
        ];
        foreach ($queries as $sql) {
            try {
                $r = $this->query($sql);
                return !empty($r);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Valida: no puede repetirse (concepto_ret, desde, hasta)
     */
    public function existeConceptoVigencia(string $concepto, string $desde, string $hasta, ?int $excluirId = null): bool
    {
        $concepto = $this->escape($concepto);
        $desde = $this->escape($desde);
        $hasta = $this->escape($hasta);
        $excluir = $excluirId !== null ? ' AND id_ret != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM retenciones_sri WHERE concepto_ret='{$concepto}' AND COALESCE(desde,'')='{$desde}' AND COALESCE(hasta,'')='{$hasta}'{$excluir}",
            "SELECT 1 FROM retenciones_sri WHERE concepto_ret='{$concepto}' AND COALESCE(desde,'')='{$desde}' AND COALESCE(hasta,'')='{$hasta}'{$excluirAlt}",
        ];
        foreach ($queries as $sql) {
            try {
                $r = $this->query($sql);
                return !empty($r);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Crea una retención
     */
    public function crear(
        string $codigoRet,
        string $conceptoRet,
        float $porcentajeRet,
        string $impuestoRet,
        string $codAnexoRet,
        int $status,
        string $desde,
        string $hasta
    ): int {
        $codigoRet = $this->escape($codigoRet);
        $conceptoRet = $this->escape($conceptoRet);
        $porcentajeRet = (float) $porcentajeRet;
        $impuestoRet = $this->escape($impuestoRet);
        $codAnexoRet = $this->escape($codAnexoRet);
        $status = $status ? 1 : 0;
        $desde = $this->escape($desde);
        $hasta = $this->escape($hasta);

        $sql = "INSERT INTO retenciones_sri (codigo_ret, concepto_ret, porcentaje_ret, impuesto_ret, cod_anexo_ret, status, desde, hasta) VALUES ('{$codigoRet}', '{$conceptoRet}', {$porcentajeRet}, '{$impuestoRet}', '{$codAnexoRet}', {$status}, '{$desde}', '{$hasta}')";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza una retención
     */
    public function actualizar(
        int $id,
        string $codigoRet,
        string $conceptoRet,
        float $porcentajeRet,
        string $impuestoRet,
        string $codAnexoRet,
        int $status,
        string $desde,
        string $hasta
    ): bool {
        $id = (int) $id;
        $codigoRet = $this->escape($codigoRet);
        $conceptoRet = $this->escape($conceptoRet);
        $porcentajeRet = (float) $porcentajeRet;
        $impuestoRet = $this->escape($impuestoRet);
        $codAnexoRet = $this->escape($codAnexoRet);
        $status = $status ? 1 : 0;
        $desde = $this->escape($desde);
        $hasta = $this->escape($hasta);

        $queries = [
            "UPDATE retenciones_sri SET codigo_ret='{$codigoRet}', concepto_ret='{$conceptoRet}', porcentaje_ret={$porcentajeRet}, impuesto_ret='{$impuestoRet}', cod_anexo_ret='{$codAnexoRet}', status={$status}, desde='{$desde}', hasta='{$hasta}' WHERE id_ret={$id}",
            "UPDATE retenciones_sri SET codigo_ret='{$codigoRet}', concepto_ret='{$conceptoRet}', porcentaje_ret={$porcentajeRet}, impuesto_ret='{$impuestoRet}', cod_anexo_ret='{$codAnexoRet}', status={$status}, desde='{$desde}', hasta='{$hasta}' WHERE id={$id}",
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
