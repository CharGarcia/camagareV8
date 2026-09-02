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
     * Código SRI (01, 16, 19, 20…) de una forma del catálogo, por su id.
     *
     * Lo necesitan los flujos que guardan un ID —la ficha del cliente
     * (`clientes.id_forma_pago_sri`) y la configuración del establecimiento
     * (`empresa_establecimiento.id_forma_pago_sri_def`)— pero emiten el
     * comprobante con el CÓDIGO. No filtra por `status`: si la forma quedó
     * inactiva después de haberse configurado, se usa igual antes que dejar el
     * comprobante sin forma de pago (mismo criterio que
     * CargaFacturasValidacionService::resolverFormaPago()).
     */
    public function getCodigoPorId(int $id): ?string
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $col = $this->columnaId();
        if ($col === null) {
            return null;
        }
        try {
            $rows = $this->query("SELECT codigo FROM formas_pago_sri WHERE {$col} = {$id}");
        } catch (\Throwable $e) {
            return null;
        }
        $codigo = trim((string) ($rows[0]['codigo'] ?? ''));
        return $codigo !== '' ? $codigo : null;
    }

    /**
     * Nombre real de la PK del catálogo ('id' o 'id_forma_pago', según de qué
     * versión venga la base). Se resuelve consultando el esquema, NO probando
     * un SELECT y capturando el error: en PostgreSQL una consulta fallida
     * aborta la transacción en curso (25P02) y todo lo que venga después del
     * catch revienta. getCodigoPorId() se invoca desde el cobro del POS, que
     * puede correr dentro de la transacción del llamador.
     */
    private function columnaId(): ?string
    {
        static $col = false;
        if ($col !== false) {
            return $col;
        }
        try {
            $rows = $this->query(
                "SELECT column_name FROM information_schema.columns
                  WHERE table_name = 'formas_pago_sri'
                    AND column_name IN ('id', 'id_forma_pago')
                  ORDER BY column_name = 'id' DESC
                  LIMIT 1"
            );
            $col = $rows[0]['column_name'] ?? null;
        } catch (\Throwable $e) {
            $col = null;
        }
        return $col;
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
