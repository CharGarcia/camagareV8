<?php
/**
 * Modelo EmpresaDocumento - Documentos legales de empresas (contratos, etc.)
 */

declare(strict_types=1);

namespace App\models;

class EmpresaDocumento extends BaseModel
{
    public const TIPOS = ['contrato', 'ruc', 'licencia', 'poder', 'otro'];

    public function getPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query("SELECT * FROM empresa_documentos WHERE id_empresa = {$id} ORDER BY fecha_subida DESC");
    }

    public function getPorId(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT * FROM empresa_documentos WHERE id = {$id}");
        return $r[0] ?? null;
    }

    public function crear(int $idEmpresa, string $tipoDocumento, string $nombreArchivo, string $nombreOriginal, ?string $descripcion = null): int
    {
        $id = (int) $idEmpresa;
        $tipo = in_array($tipoDocumento, self::TIPOS, true) ? $tipoDocumento : 'otro';
        $tipo = $this->escape($tipo);
        $nomArch = $this->escape($nombreArchivo);
        $nomOrig = $this->escape($nombreOriginal);
        $desc = $descripcion !== null ? "'" . $this->escape($descripcion) . "'" : 'NULL';
        $this->execute("INSERT INTO empresa_documentos (id_empresa, tipo_documento, descripcion, nombre_archivo, nombre_original) VALUES ({$id}, '{$tipo}', {$desc}, '{$nomArch}', '{$nomOrig}')");
        return $this->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        if ($id <= 0) return false;
        return $this->execute("DELETE FROM empresa_documentos WHERE id = {$id}");
    }
}
