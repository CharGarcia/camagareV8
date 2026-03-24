<?php
/**
 * Modelo Ciudad
 * Tabla ciudad: codigo, nombre, cod_prov. Relación: ciudad.cod_prov = provincia.codigo
 */

declare(strict_types=1);

namespace App\models;

class Ciudad extends BaseModel
{
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'cod_prov'];

    public function getPorProvincia(string $codProv): array
    {
        $cod = $this->escape(str_pad(trim($codProv), 2, '0', STR_PAD_LEFT));
        return $this->query("SELECT codigo, nombre, cod_prov FROM ciudad WHERE cod_prov = '{$cod}' ORDER BY nombre ASC");
    }

    /**
     * Busca código de ciudad por nombre dentro de una provincia.
     */
    public function getCodigoPorNombreYProvincia(string $nombre, string $codProv): ?string
    {
        $nom = $this->escape(trim($nombre));
        $cp = $this->escape(str_pad(trim($codProv), 2, '0', STR_PAD_LEFT));
        if ($nom === '' || $cp === '') return null;
        $rows = $this->query("SELECT codigo FROM ciudad WHERE cod_prov = '{$cp}' AND UPPER(TRIM(nombre)) = UPPER('{$nom}') LIMIT 1");
        if (!empty($rows)) return $rows[0]['codigo'];
        $rows = $this->query("SELECT codigo FROM ciudad WHERE cod_prov = '{$cp}' AND UPPER(nombre) LIKE UPPER('%{$nom}%') LIMIT 1");
        return $rows[0]['codigo'] ?? null;
    }

    public function getAll(string $ordenCol = 'nombre', string $ordenDir = 'ASC', string $buscar = '', ?string $codProv = null): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? 'c.' . $ordenCol : 'c.nombre';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = 'WHERE 1=1';
        if ($codProv !== null && $codProv !== '') {
            $cp = $this->escape(str_pad(trim($codProv), 2, '0', STR_PAD_LEFT));
            $where .= " AND c.cod_prov = '{$cp}'";
        }
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (c.codigo LIKE '%{$b}%' OR c.nombre LIKE '%{$b}%' OR c.cod_prov LIKE '%{$b}%')";
        }
        $sql = "SELECT c.codigo, c.nombre, c.cod_prov, p.nombre AS nombre_provincia
            FROM ciudad c
            LEFT JOIN provincia p ON p.codigo = c.cod_prov
            {$where}
            ORDER BY {$col} {$dir}";
        return $this->query($sql);
    }

    public function existeCodigo(string $codigo, string $codProv, ?string $excluirCodigo = null, ?string $excluirCodProv = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $cp = $this->escape(str_pad(trim($codProv), 2, '0', STR_PAD_LEFT));
        $sql = "SELECT 1 FROM ciudad WHERE codigo = '{$cod}' AND cod_prov = '{$cp}'";
        if ($excluirCodigo !== null && $excluirCodigo !== '' && $excluirCodProv !== null) {
            $excl = $this->escape(trim($excluirCodigo));
            $exclCp = $this->escape(str_pad(trim($excluirCodProv), 2, '0', STR_PAD_LEFT));
            $sql .= " AND NOT (codigo = '{$excl}' AND cod_prov = '{$exclCp}')";
        }
        return !empty($this->query($sql));
    }

    public function crear(string $codigo, string $nombre, string $codProv): void
    {
        $cod = $this->escape(trim($codigo));
        $nom = $this->escape(trim($nombre));
        $cp = $this->escape(str_pad(trim($codProv), 2, '0', STR_PAD_LEFT));
        if ($cod === '' || $cp === '') {
            throw new \InvalidArgumentException('Código y provincia son obligatorios.');
        }
        if ($this->existeCodigo($codigo, $codProv)) {
            throw new \InvalidArgumentException('Ya existe una ciudad con ese código en esa provincia.');
        }
        $this->execute("INSERT INTO ciudad (codigo, nombre, cod_prov) VALUES ('{$cod}', '{$nom}', '{$cp}')");
    }

    public function actualizar(string $codigoActual, string $codProvActual, string $codigoNuevo, string $nombre, string $codProvNuevo): bool
    {
        $codAct = $this->escape(trim($codigoActual));
        $cpAct = $this->escape(str_pad(trim($codProvActual), 2, '0', STR_PAD_LEFT));
        $codNuevo = $this->escape(trim($codigoNuevo));
        $nom = $this->escape(trim($nombre));
        $cpNuevo = $this->escape(str_pad(trim($codProvNuevo), 2, '0', STR_PAD_LEFT));
        if ($codAct === '' || $cpAct === '' || $codNuevo === '' || $cpNuevo === '') {
            throw new \InvalidArgumentException('Código y provincia son obligatorios.');
        }
        $mismoRegistro = ($codNuevo === $codAct && $cpNuevo === $cpAct);
        if (!$mismoRegistro && $this->existeCodigo($codigoNuevo, $codProvNuevo, $codigoActual, $codProvActual)) {
            throw new \InvalidArgumentException('Ya existe una ciudad con ese código en esa provincia.');
        }
        return $this->execute("UPDATE ciudad SET codigo = '{$codNuevo}', nombre = '{$nom}', cod_prov = '{$cpNuevo}' WHERE codigo = '{$codAct}' AND cod_prov = '{$cpAct}'");
    }
}
