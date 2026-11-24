<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\ConfigModulo;
use Exception;

/**
 * Reglas de la personalización de módulos (Configuración de Empresa). Solo se
 * aceptan módulos, claves y valores que existan en App\Helpers\ConfigModulo.
 */
class ConfigModuloRules
{
    public function validarModulo(string $modulo): void
    {
        if (ConfigModulo::definicion($modulo) === null) {
            throw new Exception('Este módulo no tiene opciones personalizables.');
        }
    }

    /**
     * Normaliza y valida el valor de una opción de la empresa según su tipo.
     * Lanza si la clave no existe o el valor no es válido.
     *
     * @return mixed Valor ya normalizado para guardar.
     */
    public function normalizarValor(string $modulo, string $clave, $valor)
    {
        $def = ConfigModulo::opcion($modulo, $clave);
        if ($def === null) {
            throw new Exception('La opción «' . $clave . '» no existe en este módulo.');
        }

        switch ($def['tipo']) {
            case 'bool':
                return in_array($valor, [true, 1, '1', 'true', 'on'], true);

            case 'select':
                $valor = (string) $valor;
                if (!array_key_exists($valor, $def['valores'] ?? [])) {
                    throw new Exception('Valor no válido para «' . $def['etiqueta'] . '».');
                }
                return $valor;

            case 'texto':
                $valor = trim((string) $valor);
                $max   = (int) ($def['max'] ?? 255);
                if (mb_strlen($valor) > $max) {
                    throw new Exception('«' . $def['etiqueta'] . '» admite hasta ' . $max . ' caracteres.');
                }
                return $valor;

            case 'columnas':
                // Lista de claves visibles: se descartan las desconocidas, las fijas
                // se agregan siempre y se devuelven en el orden del catálogo.
                $pedidas = array_map('strval', is_array($valor) ? $valor : []);
                $out = [];
                foreach ($def['columnas'] as $k => $c) {
                    if (!empty($c['fija']) || in_array($k, $pedidas, true)) {
                        $out[] = $k;
                    }
                }
                return $out;
        }

        throw new Exception('Tipo de opción no soportado.');
    }

    /** La opción debe admitir valores por cliente. */
    public function validarPorCliente(string $modulo, string $clave): void
    {
        $def = ConfigModulo::opcion($modulo, $clave);
        if ($def === null || empty($def['por_cliente'])) {
            throw new Exception('La opción «' . $clave . '» no se puede personalizar por cliente.');
        }
    }

    /**
     * Todos los clientes elegidos deben ser clientes vigentes de la empresa.
     *
     * @param int[] $pedidos
     * @param int[] $validos
     */
    public function validarClientes(array $pedidos, array $validos): void
    {
        $faltan = array_diff($pedidos, $validos);
        if ($faltan) {
            throw new Exception('Alguno de los clientes seleccionados no existe o fue eliminado. Recargue la página e inténtelo de nuevo.');
        }
    }
}
