<?php
/**
 * Modelo Empresa
 *
 * empresa_asignada: relaciona usuarios con empresas (id_usuario, id_empresa)
 * Define qué empresas puede ver/seleccionar cada usuario.
 */

declare(strict_types=1);

namespace App\models;

class Empresa extends BaseModel
{
    public const COLUMNAS_ORDEN = ['nombre', 'nombre_comercial', 'ruc', 'establecimiento', 'direccion', 'nombre_provincia', 'nombre_ciudad', 'estado'];

    public function getEmpresasAsignadas(int $idUsuario): array
    {
        $id = $this->escape((string) $idUsuario);
        $sql = "SELECT emp.id AS id_empresa, emp.ruc, emp.nombre_comercial
                FROM empresa_asignada emp_asi
                INNER JOIN empresas emp ON emp.id = emp_asi.id_empresa
                WHERE emp_asi.id_usuario = '{$id}' AND emp.estado = '1'";
        return $this->query($sql);
    }

    /**
     * Lista empresas para el módulo empresas del sistema.
     * SuperAdmin: todas. Admin: solo las que tiene asignadas.
     */
    public function getTodosParaListado(int $idActual, int $nivel, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre_comercial', string $ordenDir = 'ASC'): array
    {
        $offset = ($page - 1) * $perPage;
        $idActual = (int) $idActual;

        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_comercial';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $colMap = [
            'nombre_provincia' => 'p.nombre',
            'nombre_ciudad' => 'c.nombre',
        ];
        $col = $colMap[$ordenCol] ?? 'e.' . $ordenCol;

        if ($nivel >= 3) {
            $from = 'empresas e';
            $where = 'WHERE 1=1';
        } else {
            $from = 'empresa_asignada ea INNER JOIN empresas e ON e.id = ea.id_empresa';
            $where = "WHERE ea.id_usuario = {$idActual}";
        }
        $joinProv = 'LEFT JOIN provincia p ON p.codigo = e.cod_prov';
        $joinCiud = 'LEFT JOIN ciudad c ON c.codigo = e.cod_ciudad';

        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (e.nombre LIKE '%{$b}%' OR e.nombre_comercial LIKE '%{$b}%' OR e.ruc LIKE '%{$b}%' OR e.establecimiento LIKE '%{$b}%')";
        }

        $countSql = "SELECT COUNT(DISTINCT e.id) AS total FROM {$from} {$where}";
        $total = (int) ($this->query($countSql)[0]['total'] ?? 0);

        $sql = "SELECT DISTINCT e.id, e.nombre, e.nombre_comercial, e.ruc, e.establecimiento, e.direccion, e.telefono, e.mail,
                e.cod_prov, e.cod_ciudad, e.estado, e.valor_cobro, e.periodo_vigencia_desde, e.periodo_vigencia_hasta, e.estado_pago,
                p.nombre AS nombre_provincia, c.nombre AS nombre_ciudad
            FROM {$from} {$joinProv} {$joinCiud} {$where}
            ORDER BY {$col} {$dir}
            LIMIT {$offset}, {$perPage}";
        $rows = $this->query($sql);

        $empAsignada = new EmpresaAsignada();
        foreach ($rows as &$r) {
            $r['usuarios'] = $empAsignada->getUsuariosDeEmpresa((int) $r['id']);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT * FROM empresas WHERE id = {$id}");
        return $r[0] ?? null;
    }

    /**
     * Extrae establecimiento de RUC: últimos 3 dígitos (000-999).
     */
    private function extraerEstablecimientoDeRuc(string $ruc): string
    {
        $digitos = preg_replace('/\D/', '', trim($ruc));
        $ultimos3 = substr($digitos, -3);
        return str_pad($ultimos3 ?: '0', 3, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene un establecimiento disponible para el RUC: empieza con los 3 últimos dígitos,
     * si (ruc, 001) existe prueba 002, 003, etc. hasta encontrar uno libre.
     */
    private function obtenerEstablecimientoDisponible(string $ruc, ?int $excluirId = null): string
    {
        $base = (int) $this->extraerEstablecimientoDeRuc($ruc);
        for ($i = 0; $i < 1000; $i++) {
            $est = sprintf('%03d', ($base + $i) % 1000);
            if (!$this->existePorRucYEstablecimiento($ruc, $est, $excluirId)) {
                return $est;
            }
        }
        throw new \InvalidArgumentException('No hay establecimientos disponibles para este RUC (000-999 ocupados).');
    }

    /**
     * Normaliza establecimiento a 3 dígitos (000-999). Retorna null si no es válido.
     */
    private function normalizarEstablecimiento(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') return null;
        if (preg_match('/^\d{1,3}$/', $valor)) {
            $n = (int) $valor;
            if ($n >= 0 && $n <= 999) {
                return sprintf('%03d', $n);
            }
        }
        return null;
    }

    /**
     * Verifica si ya existe la combinación (RUC, establecimiento).
     */
    public function existePorRucYEstablecimiento(string $ruc, string $establecimiento, ?int $excluirId = null): bool
    {
        $rucEsc = $this->escape(trim($ruc));
        $est = $this->escape(trim($establecimiento));
        if ($rucEsc === '' || $est === '') return false;
        $sql = "SELECT 1 FROM empresas WHERE ruc = '{$rucEsc}' AND establecimiento = '{$est}'";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != ' . (int) $excluirId;
        }
        return !empty($this->query($sql));
    }

    /**
     * Crear empresa. Retorna id o lanza excepción.
     */
    public function crear(array $data): int
    {
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $nombreComercial = $this->escape(trim($data['nombre_comercial'] ?? ''));
        $ruc = $this->escape(trim($data['ruc'] ?? ''));
        $direccion = $this->escape(trim($data['direccion'] ?? ''));
        $telefono = $this->escape(trim($data['telefono'] ?? ''));
        $tipo = $this->escape(trim($data['tipo'] ?? '01'));
        $nomRepLegal = $this->escape(trim($data['nom_rep_legal'] ?? ''));
        $cedRepLegal = $this->escape(trim($data['ced_rep_legal'] ?? ''));
        $mail = $this->escape(trim($data['mail'] ?? ''));
        $codProv = $this->escape(trim($data['cod_prov'] ?? ''));
        $codCiudad = $this->escape(trim($data['cod_ciudad'] ?? ''));
        $nombreContador = $this->escape(trim($data['nombre_contador'] ?? ''));
        $rucContador = $this->escape(trim($data['ruc_contador'] ?? ''));
        $idUsuario = $this->escape(trim($data['id_usuario'] ?? '0'));

        if ($nombre === '' || $ruc === '') {
            throw new \InvalidArgumentException('Razón social y RUC son obligatorios.');
        }
        $establecimiento = trim($data['establecimiento'] ?? '');
        if ($establecimiento !== '') {
            $estNorm = $this->normalizarEstablecimiento($establecimiento);
            if ($estNorm === null) {
                throw new \InvalidArgumentException('Establecimiento debe ser de 3 dígitos (000 a 999).');
            }
            if ($this->existePorRucYEstablecimiento($ruc, $estNorm)) {
                throw new \InvalidArgumentException('Ya existe una empresa con el mismo RUC y establecimiento.');
            }
            $establecimiento = $estNorm;
        } else {
            $establecimiento = $this->obtenerEstablecimientoDisponible($ruc);
        }

        $valorCobro = isset($data['valor_cobro']) && $data['valor_cobro'] !== '' ? (float) $data['valor_cobro'] : null;
        $vigenciaDesde = !empty($data['periodo_vigencia_desde']) ? "'" . $this->escape($data['periodo_vigencia_desde']) . "'" : 'NULL';
        $vigenciaHasta = !empty($data['periodo_vigencia_hasta']) ? "'" . $this->escape($data['periodo_vigencia_hasta']) . "'" : 'NULL';
        $estadoPago = !empty($data['estado_pago']) ? "'" . $this->escape($data['estado_pago']) . "'" : "'pendiente'";
        $valCobroSql = $valorCobro !== null ? (string) $valorCobro : 'NULL';
        $estado = (trim($data['estado'] ?? '1') === '0') ? '0' : '1';

        $estEsc = $this->escape($establecimiento);
        $sql = "INSERT INTO empresas (nombre, nombre_comercial, ruc, establecimiento, direccion, telefono, tipo, nom_rep_legal, ced_rep_legal, mail, cod_prov, cod_ciudad, estado, fecha_agregado, id_usuario, nombre_contador, ruc_contador, valor_cobro, periodo_vigencia_desde, periodo_vigencia_hasta, estado_pago)
            VALUES ('{$nombre}', '{$nombreComercial}', '{$ruc}', '{$estEsc}', '{$direccion}', '{$telefono}', '{$tipo}', '{$nomRepLegal}', '{$cedRepLegal}', '{$mail}', '{$codProv}', '{$codCiudad}', '{$estado}', NOW(), '{$idUsuario}', '{$nombreContador}', '{$rucContador}', {$valCobroSql}, {$vigenciaDesde}, {$vigenciaHasta}, {$estadoPago})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualizar empresa.
     */
    public function actualizar(int $id, array $data): bool
    {
        $id = (int) $id;
        if ($id <= 0) return false;

        $establecimiento = trim($data['establecimiento'] ?? '');
        $ruc = trim($data['ruc'] ?? '');
        if ($establecimiento !== '' && $ruc !== '') {
            $estNorm = $this->normalizarEstablecimiento($establecimiento);
            if ($estNorm === null) {
                throw new \InvalidArgumentException('Establecimiento debe ser de 3 dígitos (000 a 999).');
            }
            if ($this->existePorRucYEstablecimiento($ruc, $estNorm, $id)) {
                throw new \InvalidArgumentException('Ya existe una empresa con el mismo RUC y establecimiento.');
            }
            $data['establecimiento'] = $estNorm;
        } elseif ($ruc !== '') {
            $data['establecimiento'] = $this->obtenerEstablecimientoDisponible($ruc, $id);
        }

        $sets = [];
        $campos = ['nombre', 'nombre_comercial', 'ruc', 'establecimiento', 'direccion', 'telefono', 'mail', 'nom_rep_legal', 'ced_rep_legal', 'cod_prov', 'cod_ciudad', 'nombre_contador', 'ruc_contador', 'estado', 'valor_cobro', 'periodo_vigencia_desde', 'periodo_vigencia_hasta', 'estado_pago'];
        foreach ($campos as $c) {
            if (array_key_exists($c, $data)) {
                if (in_array($c, ['valor_cobro'], true)) {
                    $v = $data[$c];
                    $sets[] = "{$c} = " . ($v === '' || $v === null ? 'NULL' : (float) $v);
                } elseif (in_array($c, ['periodo_vigencia_desde', 'periodo_vigencia_hasta'], true)) {
                    $v = trim($data[$c] ?? '');
                    $sets[] = "{$c} = " . ($v === '' ? 'NULL' : "'" . $this->escape($v) . "'");
                } else {
                    $sets[] = "{$c} = '" . $this->escape(trim((string) $data[$c])) . "'";
                }
            }
        }
        if (empty($sets)) return true;
        $sql = 'UPDATE empresas SET ' . implode(', ', $sets) . ' WHERE id = ' . $id;
        return $this->execute($sql);
    }
}
