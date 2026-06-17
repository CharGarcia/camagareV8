<?php

declare(strict_types=1);

namespace App\Services;

class SuperciasEvaluatorService
{
    private \PDO $db;
    
    // Almacena las estructuras: ['ESF' => ['10101' => ['formula' => '...', 'valor' => 0]], ...]
    private array $estructuras = [];
    
    // Almacena los valores base sumados desde la contabilidad: ['ESF' => ['10101' => 12500.50], ...]
    private array $valoresBase = [];

    // Cache de evaluación para evitar recálculos (memoization)
    private array $cache = [];
    
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Carga y procesa todas las fórmulas para un año específico
     */
    public function evaluar(int $id_empresa, int $anio): array
    {
        $this->cargarEstructuras();
        $this->calcularValoresBase($id_empresa, $anio);
        
        // Asignar los valores base a la estructura
        foreach ($this->estructuras as $tipo => $casilleros) {
            foreach ($casilleros as $codigo => $datos) {
                // Si no tiene fórmula, su valor viene de la base de datos contable
                if (empty($datos['formula'])) {
                    $this->estructuras[$tipo][$codigo]['valor'] = $this->valoresBase[$tipo][$codigo] ?? 0.00;
                    $this->cache["{$tipo}:{$codigo}"] = $this->estructuras[$tipo][$codigo]['valor'];
                }
            }
        }
        
        // Evaluar casilleros con fórmula
        foreach ($this->estructuras as $tipo => $casilleros) {
            foreach ($casilleros as $codigo => $datos) {
                if (!empty($datos['formula'])) {
                    $this->estructuras[$tipo][$codigo]['valor'] = $this->evaluarFormula("{$tipo}:{$codigo}");
                }
            }
        }
        
        return $this->estructuras;
    }
    
    // Mapeo rápido de código -> tipo (ej: '10101' => 'ESF')
    private array $codigoATipo = [];

    private function cargarEstructuras(): void
    {
        $stmt = $this->db->query("SELECT * FROM supercias_estructuras WHERE eliminado = false ORDER BY orden ASC, codigo ASC, subcodigo ASC");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $tipo = $row['tipo'];
            $key = $row['codigo'];
            if ($tipo === 'ECP' && !empty($row['subcodigo'])) {
                $key .= '.' . $row['subcodigo'];
            }
            
            $this->estructuras[$tipo][$key] = [
                'formula' => trim((string)$row['formula']),
                'nombre' => $row['nombre'],
                'valor' => 0.00
            ];
            
            $this->codigoATipo[$key] = $tipo;
        }
    }
    
    private function calcularValoresBase(int $id_empresa, int $anio): void
    {
        $sql = "
            SELECT 
                p.codigo as cuenta_codigo,
                p.supercias_esf,
                p.supercias_eri,
                p.supercias_ecp_codigo,
                p.supercias_ecp_subcodigo,
                COALESCE(SUM(d.debe), 0) as total_debe,
                COALESCE(SUM(d.haber), 0) as total_haber
            FROM plan_cuentas p
            LEFT JOIN asientos_contables_detalle d ON p.id = d.id_cuenta_contable AND d.eliminado = false
            LEFT JOIN asientos_contables_cabecera c ON d.id_asiento = c.id AND c.eliminado = false AND c.id_empresa = :id_empresa
            WHERE p.id_empresa = :id_empresa AND p.eliminado = false
              AND EXTRACT(YEAR FROM c.fecha_asiento) = :anio
            GROUP BY p.codigo, p.supercias_esf, p.supercias_eri, p.supercias_ecp_codigo, p.supercias_ecp_subcodigo
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $id_empresa, ':anio' => $anio]);
        $saldos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->valoresBase = ['ESF' => [], 'ERI' => [], 'ECP' => [], 'EFE' => []];
        
        foreach ($saldos as $row) {
            $naturalezaDeudora = in_array(substr($row['cuenta_codigo'], 0, 1), ['1', '5', '6']);
            $saldo = $naturalezaDeudora 
                ? ($row['total_debe'] - $row['total_haber']) 
                : ($row['total_haber'] - $row['total_debe']);
            
            if (!empty($row['supercias_esf'])) {
                $cas = $row['supercias_esf'];
                if (!isset($this->valoresBase['ESF'][$cas])) $this->valoresBase['ESF'][$cas] = 0;
                $this->valoresBase['ESF'][$cas] += $saldo;
            }
            if (!empty($row['supercias_eri'])) {
                $cas = $row['supercias_eri'];
                if (!isset($this->valoresBase['ERI'][$cas])) $this->valoresBase['ERI'][$cas] = 0;
                $this->valoresBase['ERI'][$cas] += $saldo;
            }
            if (!empty($row['supercias_ecp_codigo'])) {
                $cas = $row['supercias_ecp_codigo'];
                if (!empty($row['supercias_ecp_subcodigo'])) {
                    $cas .= '.' . $row['supercias_ecp_subcodigo'];
                }
                if (!isset($this->valoresBase['ECP'][$cas])) $this->valoresBase['ECP'][$cas] = 0;
                $this->valoresBase['ECP'][$cas] += $saldo;
            }
        }
    }
    
    private function evaluarFormula(string $idNodo): float
    {
        if (isset($this->cache[$idNodo])) {
            return $this->cache[$idNodo];
        }
        
        list($tipo, $codigo) = explode(':', $idNodo);
        
        if (!isset($this->estructuras[$tipo][$codigo])) {
            $this->cache[$idNodo] = 0.00;
            return 0.00;
        }
        
        $formula = $this->estructuras[$tipo][$codigo]['formula'];
        
        if (empty($formula)) {
            $val = $this->estructuras[$tipo][$codigo]['valor'] ?? 0.00;
            $this->cache[$idNodo] = $val;
            return $val;
        }
        
        // Soportar ambas sintaxis: [TIPO:CODIGO] o solo CODIGO (ej: 10101)
        // Reemplazar primero los [TIPO:CODIGO] (Legacy o específico)
        $formula = preg_replace_callback('/\[([A-Z]{3}):([0-9\.]+)\]/', function($matches) {
            $depId = "{$matches[1]}:{$matches[2]}";
            return sprintf("%.4f", $this->evaluarFormula($depId));
        }, $formula);
        
        // Ahora buscar códigos puros (ej: 101, 10101, 301.1) y reemplazarlos si existen en el diccionario global
        $formula = preg_replace_callback('/\b(\d+(?:\.\d+)?)\b/', function($matches) {
            $num = $matches[1];
            // Si el número es un casillero registrado
            if (isset($this->codigoATipo[$num])) {
                $depTipo = $this->codigoATipo[$num];
                $depId = "{$depTipo}:{$num}";
                return sprintf("%.4f", $this->evaluarFormula($depId));
            }
            // Si no existe, es un número literal (constante)
            return $num;
        }, $formula);
        
        $resultado = $this->matematicaSegura($formula);
        
        $this->cache[$idNodo] = $resultado;
        return $resultado;
    }
    
    /**
     * Evaluador matemático muy básico y seguro que solo procesa suma y resta.
     * Si la fórmula original tiene paréntesis u otros operadores complejos,
     * se podría usar una librería de AST. Por ahora asume sumas y restas simples.
     */
    private function matematicaSegura(string $expression): float
    {
        // Limpiar espacios
        $expression = str_replace(' ', '', $expression);
        
        // Remover todo excepto números, puntos, signos + y -
        $clean = preg_replace('/[^0-9\.\+\-]/', '', $expression);
        if (empty($clean)) return 0.00;
        
        // Resolver usando eval de forma hiper-controlada (ya que solo permitimos [0-9.+-])
        // Para mayor seguridad en producción, se puede usar un tokenizer.
        try {
            $result = @eval("return {$clean};");
            return is_numeric($result) ? (float) $result : 0.00;
        } catch (\Throwable $e) {
            return 0.00;
        }
    }
}
