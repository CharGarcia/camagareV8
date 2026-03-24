<?php
/**
 * Modelo Provincia
 * Tabla provincia: codigo, nombre. Relación con ciudad por cod_prov.
 */

declare(strict_types=1);

namespace App\models;

class Provincia extends BaseModel
{
    public const COLUMNAS_ORDEN = ['codigo', 'nombre'];

    public function getTodas(): array
    {
        return $this->query("SELECT codigo, nombre FROM provincia ORDER BY nombre ASC");
    }

    /**
     * Busca código de provincia por nombre (insensible a mayúsculas).
     */
    public function getCodigoPorNombre(string $nombre): ?string
    {
        $nom = $this->escape(trim($nombre));
        if ($nom === '') return null;
        $rows = $this->query("SELECT codigo FROM provincia WHERE UPPER(TRIM(nombre)) = UPPER('{$nom}') LIMIT 1");
        if (!empty($rows)) return $rows[0]['codigo'];
        $rows = $this->query("SELECT codigo FROM provincia WHERE UPPER(nombre) LIKE UPPER('%{$nom}%') LIMIT 1");
        return $rows[0]['codigo'] ?? null;
    }

    public function getAll(string $ordenCol = 'nombre', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'nombre';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%')";
        }
        return $this->query("SELECT codigo, nombre FROM provincia{$where} ORDER BY {$col} {$dir}");
    }

    public function existeCodigo(string $codigo, ?string $excluirCodigo = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $sql = "SELECT 1 FROM provincia WHERE codigo = '{$cod}'";
        if ($excluirCodigo !== null && $excluirCodigo !== '') {
            $excl = $this->escape(trim($excluirCodigo));
            $sql .= " AND codigo != '{$excl}'";
        }
        return !empty($this->query($sql));
    }

    public function crear(string $codigo, string $nombre): void
    {
        $cod = $this->escape(trim($codigo));
        $nom = $this->escape(trim($nombre));
        if ($cod === '') {
            throw new \InvalidArgumentException('El código es obligatorio.');
        }
        if ($this->existeCodigo($codigo)) {
            throw new \InvalidArgumentException('Ya existe una provincia con ese código.');
        }
        $this->execute("INSERT INTO provincia (codigo, nombre) VALUES ('{$cod}', '{$nom}')");
    }

    public function actualizar(string $codigoActual, string $codigoNuevo, string $nombre): bool
    {
        $codAct = $this->escape(trim($codigoActual));
        $codNuevo = $this->escape(trim($codigoNuevo));
        $nom = $this->escape(trim($nombre));
        if ($codAct === '' || $codNuevo === '') {
            throw new \InvalidArgumentException('El código es obligatorio.');
        }
        if ($codNuevo !== $codAct && $this->existeCodigo($codigoNuevo, $codigoActual)) {
            throw new \InvalidArgumentException('Ya existe una provincia con ese código.');
        }
        if ($codNuevo !== $codAct) {
            $this->execute("UPDATE ciudad SET cod_prov = '{$codNuevo}' WHERE cod_prov = '{$codAct}'");
        }
        return $this->execute("UPDATE provincia SET codigo = '{$codNuevo}', nombre = '{$nom}' WHERE codigo = '{$codAct}'");
    }
}
