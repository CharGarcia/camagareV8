<?php
/**
 * Modelo PlanCuentasModelo - Plan de cuentas modelo/plantilla
 * Tabla: plan_cuentas_modelo
 */

declare(strict_types=1);

namespace App\models;

class PlanCuentasModelo extends BaseModel
{
    public const COLUMNAS_ORDEN = ['codigo', 'nivel', 'nombre', 'codigo_sri', 'status'];

    public function getAll(string $ordenCol = 'codigo', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nivel LIKE '%{$b}%' OR nombre LIKE '%{$b}%' OR codigo_sri LIKE '%{$b}%' OR supercias_esf LIKE '%{$b}%' OR supercias_eri LIKE '%{$b}%')";
        }
        $sql = "SELECT id, codigo, nivel, nombre, codigo_sri, supercias_esf, supercias_eri, supercias_ecp_codigo, supercias_ecp_subcodigo, status
                FROM plan_cuentas_modelo{$where}
                ORDER BY {$col} {$dir}";
        try {
            return $this->query($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        $id = (int) $id;
        $rows = $this->query("SELECT id, codigo, nivel, nombre, codigo_sri, supercias_esf, supercias_eri, supercias_ecp_codigo, supercias_ecp_subcodigo, status FROM plan_cuentas_modelo WHERE id = {$id}");
        return $rows[0] ?? null;
    }

    public function crear(array $data): int
    {
        $codigo = $this->escape(trim($data['codigo'] ?? ''));
        $nivel = $this->escape(trim($data['nivel'] ?? '1'));
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $codigoSri = $this->escape(trim($data['codigo_sri'] ?? ''));
        $superciasEsf = $this->escape(trim($data['supercias_esf'] ?? ''));
        $superciasEri = $this->escape(trim($data['supercias_eri'] ?? ''));
        $superciasEcpCodigo = $this->escape(trim($data['supercias_ecp_codigo'] ?? ''));
        $superciasEcpSubcodigo = $this->escape(trim($data['supercias_ecp_subcodigo'] ?? ''));
        $status = !empty($data['status']) ? 1 : 0;

        $sql = "INSERT INTO plan_cuentas_modelo (codigo, nivel, nombre, codigo_sri, supercias_esf, supercias_eri, supercias_ecp_codigo, supercias_ecp_subcodigo, status)
                VALUES ('{$codigo}', '{$nivel}', '{$nombre}', '{$codigoSri}', '{$superciasEsf}', '{$superciasEri}', '{$superciasEcpCodigo}', '{$superciasEcpSubcodigo}', {$status})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    public function actualizar(int $id, array $data): bool
    {
        $id = (int) $id;
        $codigo = $this->escape(trim($data['codigo'] ?? ''));
        $nivel = $this->escape(trim($data['nivel'] ?? '1'));
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $codigoSri = $this->escape(trim($data['codigo_sri'] ?? ''));
        $superciasEsf = $this->escape(trim($data['supercias_esf'] ?? ''));
        $superciasEri = $this->escape(trim($data['supercias_eri'] ?? ''));
        $superciasEcpCodigo = $this->escape(trim($data['supercias_ecp_codigo'] ?? ''));
        $superciasEcpSubcodigo = $this->escape(trim($data['supercias_ecp_subcodigo'] ?? ''));
        $status = !empty($data['status']) ? 1 : 0;

        $sql = "UPDATE plan_cuentas_modelo SET
                codigo='{$codigo}', nivel='{$nivel}', nombre='{$nombre}', codigo_sri='{$codigoSri}',
                supercias_esf='{$superciasEsf}', supercias_eri='{$superciasEri}',
                supercias_ecp_codigo='{$superciasEcpCodigo}', supercias_ecp_subcodigo='{$superciasEcpSubcodigo}',
                status={$status}
                WHERE id={$id}";
        return $this->execute($sql);
    }

    /**
     * Devuelve el siguiente código para una cuenta hija del padre dado.
     * Busca el primer número faltante (hueco) o el consecutivo si no hay huecos.
     * Formato: Nivel 2 = 1 dígito (1-9), Niveles 3-4 = 2 dígitos (01-99), Nivel 5 = 3 dígitos (001-999).
     */
    public function getSiguienteCodigoHijo(string $codigoPadre): string
    {
        $padre = trim($codigoPadre);
        if ($padre === '') return '';
        $padreEsc = $this->escape($padre);
        $prefijo = $padre . '.';
        $segmentos = array_filter(explode('.', $padre));
        $nivelHijo = count($segmentos) + 1;

        $rows = $this->query("SELECT codigo FROM plan_cuentas_modelo WHERE codigo LIKE '{$padreEsc}.%'");
        $usados = [];
        foreach ($rows as $r) {
            $sufijo = substr($r['codigo'], strlen($prefijo));
            $num = (int) $sufijo;
            if ($num > 0) {
                $usados[$num] = true;
            }
        }

        $siguiente = 1;
        $maxPermitido = match ($nivelHijo) {
            2 => 9,
            3, 4 => 99,
            default => 999,
        };
        for ($i = 1; $i <= $maxPermitido + 1; $i++) {
            if (!isset($usados[$i])) {
                $siguiente = $i;
                break;
            }
        }

        $sufijoFormato = match ($nivelHijo) {
            2 => (string) $siguiente,
            5 => str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT),
            default => str_pad((string) $siguiente, 2, '0', STR_PAD_LEFT),
        };
        return $prefijo . $sufijoFormato;
    }

    /**
     * Devuelve todos los códigos de la tabla (para construir mapa padre-hijos sin N+1).
     */
    public function getTodosCodigos(): array
    {
        $rows = $this->query("SELECT codigo FROM plan_cuentas_modelo");
        return array_column($rows, 'codigo');
    }

    /**
     * Enriquece las filas con puede_eliminar, puede_crear_hijo, nivel_hijo y siguiente_codigo.
     * Usa 1 consulta extra (todos los codigos) en lugar de 2N consultas.
     */
    public function enriquecerFilasParaIndex(array $rows, array $todosCodigos): array
    {
        // Codigos que tienen al menos un hijo (cada hijo indica que su padre tiene hijos)
        $codigosConHijos = [];
        foreach ($todosCodigos as $codigo) {
            if (strpos($codigo, '.') !== false) {
                $padre = substr($codigo, 0, strrpos($codigo, '.'));
                $codigosConHijos[$padre] = true;
            }
        }

        // Construir mapa padre => [sufijos usados] desde TODA la tabla
        $padreToSufijos = [];
        foreach ($todosCodigos as $codigo) {
            if ($codigo === '' || strpos($codigo, '.') === false) continue;
            $pos = strrpos($codigo, '.');
            $padre = substr($codigo, 0, $pos);
            $sufijo = substr($codigo, $pos + 1);
            $num = (int) $sufijo;
            if ($num > 0) {
                $padreToSufijos[$padre][$num] = true;
            }
        }

        foreach ($rows as &$r) {
            $codigo = $r['codigo'] ?? '';
            $r['puede_eliminar'] = !isset($codigosConHijos[$codigo]);
            $nivel = (int) ($r['nivel'] ?? 0);
            if ($nivel < 5) {
                $r['puede_crear_hijo'] = true;
                $r['nivel_hijo'] = $nivel + 1;
                $r['siguiente_codigo'] = $this->computarSiguienteCodigoDesdeMapa($codigo, $padreToSufijos);
            } else {
                $r['puede_crear_hijo'] = false;
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Calcula el siguiente código hijo usando el mapa precomputado (sin consultas).
     */
    private function computarSiguienteCodigoDesdeMapa(string $codigoPadre, array $padreToSufijos): string
    {
        if ($codigoPadre === '') return '';
        $prefijo = $codigoPadre . '.';
        $segmentos = array_filter(explode('.', $codigoPadre));
        $nivelHijo = count($segmentos) + 1;
        $usados = $padreToSufijos[$codigoPadre] ?? [];
        $maxPermitido = match ($nivelHijo) {
            2 => 9,
            3, 4 => 99,
            default => 999,
        };
        $siguiente = 1;
        for ($i = 1; $i <= $maxPermitido + 1; $i++) {
            if (!isset($usados[$i])) {
                $siguiente = $i;
                break;
            }
        }
        $sufijoFormato = match ($nivelHijo) {
            2 => (string) $siguiente,
            5 => str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT),
            default => str_pad((string) $siguiente, 2, '0', STR_PAD_LEFT),
        };
        return $prefijo . $sufijoFormato;
    }

    /**
     * Indica si la cuenta puede eliminarse (no tiene hijas ni padre que la contenga como hija).
     * Una cuenta tiene hijas si existe otra con codigo que empiece con este codigo + '.'
     * Ej: 1.1.01.01 tiene hijas 1.1.01.01.001, 1.1.01.01.009
     */
    public function puedeEliminar(string $codigo): bool
    {
        if ($codigo === '') return false;
        $codigoEsc = $this->escape($codigo);
        $rows = $this->query("SELECT 1 FROM plan_cuentas_modelo WHERE codigo LIKE '{$codigoEsc}.%' LIMIT 1");
        return empty($rows);
    }

    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $cuenta = $this->getById($id);
        if (!$cuenta) return false;
        if (!$this->puedeEliminar($cuenta['codigo'] ?? '')) {
            return false;
        }
        return $this->execute("DELETE FROM plan_cuentas_modelo WHERE id = {$id}");
    }
}
