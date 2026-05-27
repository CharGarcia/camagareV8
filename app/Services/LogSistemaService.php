<?php
declare(strict_types=1);

namespace App\Services;

use App\core\Database;
use PDO;

class LogSistemaService
{
    private PDO $db;

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
     * Compara dos arreglos de datos y retorna una lista de cambios legibles.
     */
    private function generarDetalleCambios(?array $antes, ?array $despues): array
    {
        if (!$antes && $despues) return [['campo' => 'Registro completo', 'antes' => null, 'despues' => 'Creación']];
        if (!$despues) return [['campo' => 'Registro', 'antes' => null, 'despues' => 'Eliminación']];

        $cambios = [];
        $omitir = ['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'eliminado', 'deleted_at', 'deleted_by', 'id_empresa', 'id_usuario'];

        foreach ($despues as $key => $valNuevo) {
            if (in_array($key, $omitir)) continue;

            $valAnterior = $antes[$key] ?? null;

            // Comparación segura para tipos simples y complejos (arreglos/JSON)
            if (is_array($valNuevo) || is_array($valAnterior)) {
                if (json_encode($valNuevo) === json_encode($valAnterior)) continue;
            } else {
                if ($valNuevo == $valAnterior) continue;
            }

            // Formatear booleanos
            if (is_bool($valNuevo)) {
                $valNuevo = $valNuevo ? 'Sí' : 'No';
                $valAnterior = $valAnterior ? 'Sí' : 'No';
            } else {
                $valNuevo = $this->resolverValor($key, $valNuevo);
                $valAnterior = $this->resolverValor($key, $valAnterior);
            }
            
            $cambios[] = [
                'campo'   => $this->formatearCampo($key),
                'antes'   => $valAnterior,
                'despues' => $valNuevo
            ];
        }

        return $cambios;
    }

    private function formatearCampo(string $key): string
    {
        $label = str_replace('id_', '', $key);
        $label = str_replace('_id', ' ID', $label);
        return ucfirst(str_replace('_', ' ', $label));
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

            try {
                $sql = "SELECT {$f} FROM {$t} WHERE {$k} = :val LIMIT 1";
                $st = $this->db->prepare($sql);
                $st->execute([':val' => $value]);
                $res = $st->fetchColumn();
                if ($res !== false && $res !== null) {
                    return (string) $res;
                }
            } catch (\Throwable $e) {}
        }

        return (string) $value;
    }
}
