<?php
declare(strict_types=1);

namespace App\Services;

use App\core\Database;
use App\Helpers\AuditoriaCampos;
use PDO;

class LogSistemaService
{
    private PDO $db;

    /** Caché de resoluciones id → nombre dentro de la misma petición. */
    private array $cacheValores = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Registra una acción en la bitácora de auditoría.
     */
    public function registrar(
        int $idUsuario,
        ?int $idEmpresa,
        string $accion,
        string $tabla,
        ?int $idRegistro = null,
        ?array $antes = null,
        ?array $despues = null
    ): void {
        $sql = "INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip_usuario, user_agent) 
                VALUES (:id_u, :id_e, :acc, :tab, :id_r, :ant, :des, :ip, :ua)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_u' => $idUsuario,
            ':id_e' => $idEmpresa,
            ':acc'  => $accion,
            ':tab'  => $tabla,
            ':id_r' => $idRegistro,
            ':ant'  => $antes ? json_encode($antes) : null,
            ':des'  => $despues ? json_encode($despues) : null,
            ':ip'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':ua'   => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);

        // Invalidar la caché de contadores del navbar si la tabla es relevante.
        // Envuelto en try/catch: la invalidación de caché JAMÁS debe afectar la auditoría.
        try {
            \App\Services\ContadoresNavbarService::invalidarPorTabla($tabla, $idEmpresa);
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

    /**
     * Obtiene el historial de cambios de un registro específico.
     */
    public function getHistorial(string $tabla, int $idRegistro, ?int $idEmpresa = null): array
    {
        $where = "WHERE tabla_afectada = :tab AND id_registro = :id_r";
        $params = [':tab' => $tabla, ':id_r' => $idRegistro];

        if ($idEmpresa !== null) {
            $where .= " AND id_empresa = :id_e";
            $params[':id_e'] = $idEmpresa;
        }

        $sql = "SELECT l.*, u.nombre AS usuario_nombre
                FROM log_sistema l
                LEFT JOIN usuarios u ON u.id = l.id_usuario
                $where
                ORDER BY l.created_at DESC";
        
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $logs = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['created_at'] = date('d-m-Y H:i:s', strtotime($log['created_at']));
            $log['detalles'] = $this->generarDetalleCambios(
                $log['datos_anteriores'] ? json_decode($log['datos_anteriores'], true) : null,
                $log['datos_nuevos'] ? json_decode($log['datos_nuevos'], true) : null
            );
        }

        return $logs;
    }

    /**
     * Envoltura pública de generarDetalleCambios() para reutilizar el diff legible
     * desde el módulo de consulta de auditoría (LogSistemaConsultaService).
     */
    public function formatearCambios(?array $antes, ?array $despues): array
    {
        return $this->generarDetalleCambios($antes, $despues);
    }

    /**
     * Compara dos arreglos de datos y retorna una lista de cambios legibles.
     *
     * La comparación es semántica (App\Helpers\AuditoriaCampos::iguales): un mismo
     * valor guardado con distinta forma — booleano 't' de PostgreSQL frente a true
     * de PHP, JSON con las claves en otro orden, '12.00' frente a '12' — no se
     * reporta como cambio, porque el usuario no cambió nada.
     */
    private function generarDetalleCambios(?array $antes, ?array $despues): array
    {
        if (!$antes && $despues) return [['campo' => 'Registro', 'antes' => 'No existía', 'despues' => 'Creado']];
        if (!$despues) return [['campo' => 'Registro', 'antes' => 'Existía', 'despues' => 'Eliminado']];

        $cambios = [];
        $omitir = ['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'eliminado', 'deleted_at', 'deleted_by', 'id_empresa', 'id_usuario'];

        foreach ($despues as $key => $valNuevo) {
            if (in_array($key, $omitir)) continue;

            $valAnterior = $antes[$key] ?? null;

            if (AuditoriaCampos::iguales($valAnterior, $valNuevo)) continue;

            $antesTexto   = $this->presentarValor($key, $valAnterior);
            $despuesTexto = $this->presentarValor($key, $valNuevo);

            // Campos cuyo texto resume el contenido (listas, archivos): si el resumen
            // no cambia, decirlo, en vez de mostrar "1 registro → 1 registro".
            if ($antesTexto === $despuesTexto) {
                $despuesTexto .= ' (con cambios)';
            }

            $cambios[] = [
                'campo'   => AuditoriaCampos::etiqueta($key),
                'antes'   => $antesTexto,
                'despues' => $despuesTexto
            ];
        }

        return $cambios;
    }

    /**
     * Valor tal como debe verlo el usuario: primero la traducción del campo
     * (enumeraciones, booleanos con etiqueta propia, JSON de opciones) y, si no
     * aplica, la resolución del id contra su catálogo.
     */
    private function presentarValor(string $key, $valor): string
    {
        $legible = AuditoriaCampos::valorLegible($key, $valor);
        if ($legible !== null) {
            return $legible;
        }

        // Un guion no dice si el dato se borró o nunca existió.
        if ($valor === null || $valor === '') {
            return '(vacío)';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        // El mismo dato llega como arreglo o como texto JSON según de dónde venga el
        // log; se normaliza para que ambas formas se lean igual.
        if (is_string($valor) && preg_match('/^\s*[\[{]/', $valor)) {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                $valor = $decodificado;
            }
        }

        // Listas anexas (componentes, variantes…): el JSON completo no le sirve a nadie.
        if (is_array($valor) && in_array($key, ['componentes', 'variantes', 'inventarios', 'precios'], true)) {
            $n = count($valor);
            return $n === 0 ? 'Ninguno' : $n . ($n === 1 ? ' registro' : ' registros');
        }

        if (is_array($valor) && empty($valor)) {
            return '(vacío)';
        }

        return $this->resolverValor($key, $valor);
    }

    private function resolverValor(string $key, $value): string
    {
        if ($value === null || $value === '') return '-';
        if (is_array($value)) {
            if ($key === 'casilleros_sri') {
                $labels = [
                    'v_brutas' => 'Ventas Brutas',
                    'v_nc'     => 'NC Ventas',
                    'v_iva'    => 'IVA Ventas',
                    'c_brutas' => 'Compras Brutas',
                    'c_nc'     => 'NC Compras',
                    'c_iva'    => 'IVA Compras'
                ];
                $parts = [];
                foreach ($value as $k => $v) {
                    if ($v !== '') {
                        $label = $labels[$k] ?? $k;
                        $parts[] = "{$label}: {$v}";
                    }
                }
                return empty($parts) ? '-' : implode(' | ', $parts);
            }
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $map = [
            'id_forma_pago_sri' => ['table' => 'formas_pago_sri', 'field' => "codigo || ' - ' || nombre"],
            'id_vendedor'       => ['table' => 'vendedores', 'field' => 'nombre'],
            // Catálogos de producto: sin esto el historial mostraba "Medida: 12 → 13"
            'id_medida'         => ['table' => 'unidades_medida', 'field' => 'nombre'],
            'id_tipo_medida'    => ['table' => 'tipo_medida', 'field' => 'nombre'],
            'id_categoria'      => ['table' => 'categorias', 'field' => 'nombre'],
            'id_marca'          => ['table' => 'marcas', 'field' => 'nombre'],
            'tarifa_iva'        => ['table' => 'tarifa_iva', 'field' => 'tarifa'],
            'id_ice'            => ['table' => 'empresa_ice', 'field' => 'nombre_ice'],
            'id_bodega'         => ['table' => 'bodegas', 'field' => 'nombre'],
            'id_producto'       => ['table' => 'productos', 'field' => "codigo || ' - ' || nombre"],
            'id_cliente'        => ['table' => 'clientes', 'field' => 'nombre'],
            'id_proveedor'      => ['table' => 'proveedores', 'field' => 'razon_social'],
            'id_cuenta_cobrar'  => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_ingreso' => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_pagar'   => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_gasto'   => ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_cuenta_inventario'=> ['table' => 'plan_cuentas', 'field' => "codigo || ' - ' || nombre"],
            'id_banco'          => ['table' => 'bancos_ecuador', 'field' => 'nombre_banco'],
            'tipo_id'           => ['table' => 'identificadores_comprador_vendedor', 'field' => 'nombre', 'key' => 'codigo'],
            'tipo_id_proveedor' => ['table' => 'identificadores_comprador_vendedor', 'field' => 'nombre', 'key' => 'codigo'],
            'tipo_empresa'      => ['table' => 'tipo_empresa', 'field' => 'nombre'],
            'provincia'         => ['table' => 'provincias', 'field' => 'nombre', 'key' => 'codigo'],
            'ciudad'            => ['table' => 'ciudades', 'field' => 'nombre', 'key' => 'codigo'],
            'id_forma_cobro_predeterminada' => ['table' => 'formas_cobro', 'field' => 'nombre']
        ];

        if (isset($map[$key])) {
            $t = $map[$key]['table'];
            $f = $map[$key]['field'];
            $k = $map[$key]['key'] ?? 'id';

            $cacheKey = $t . '|' . $k . '|' . (string) $value;
            if (array_key_exists($cacheKey, $this->cacheValores)) {
                return $this->cacheValores[$cacheKey] ?? (string) $value;
            }

            try {
                $sql = "SELECT {$f} FROM {$t} WHERE {$k} = :val LIMIT 1";
                $st = $this->db->prepare($sql);
                $st->execute([':val' => $value]);
                $res = $st->fetchColumn();
                $this->cacheValores[$cacheKey] = ($res !== false && $res !== null) ? (string) $res : null;
                if ($this->cacheValores[$cacheKey] !== null) {
                    return $this->cacheValores[$cacheKey];
                }
            } catch (\Throwable $e) {
                $this->cacheValores[$cacheKey] = null;
            }
        }

        return (string) $value;
    }
}
