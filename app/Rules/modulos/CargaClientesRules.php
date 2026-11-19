<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio de una fila de la carga masiva de clientes.
 *
 * No repite las reglas de la ficha: las delega en ClienteRules (la misma que
 * aplica ClienteService al guardar), y solo agrega lo propio del Excel: valores
 * que no se pudieron interpretar y largos máximos de cada columna (el UPDATE de
 * clientes no capa el texto, así que un teléfono de 25 caracteres reventaría).
 *
 * Devuelve la lista de errores en vez de lanzar, para informarlos todos por fila.
 */
class CargaClientesRules
{
    /** Largo máximo por columna del Excel (= columna de la tabla clientes). */
    private const LARGOS = [
        'NOMBRE'             => ['nombre', 300],
        'IDENTIFICACION'     => ['identificacion', 50],
        'TELEFONO'           => ['telefono', 20],
        'EMAIL'              => ['email', 200],
        'DIRECCION'          => ['direccion', 300],
        'OBSERVACIONES'      => ['observaciones', 1000],
        'OBSERVACION_VISITA' => ['observacion_visita', 255],
    ];

    private ClienteRules $clienteRules;

    public function __construct(?ClienteRules $clienteRules = null)
    {
        $this->clienteRules = $clienteRules ?? new ClienteRules();
    }

    /**
     * @param array $f      Datos ya normalizados (claves de ClienteService).
     * @param array $invalidos Columnas cuyo texto no se pudo interpretar: columna => texto.
     * @return string[]
     */
    public function validarCliente(array $f, array $invalidos = []): array
    {
        $errores = [];

        foreach ($invalidos as $columna => $mensaje) {
            $errores[] = $mensaje;
        }

        foreach (self::LARGOS as $columna => [$campo, $max]) {
            $valor = (string) ($f[$campo] ?? '');
            if (mb_strlen($valor) > $max) {
                $errores[] = $columna . ' no puede exceder ' . $max . ' caracteres (tiene ' . mb_strlen($valor) . ').';
            }
        }

        // Si ya hay un valor ilegible, la regla de la ficha diría algo confuso
        // sobre el mismo campo (p. ej. "tipo obligatorio"): se informa solo lo nuestro.
        if ($invalidos) {
            return $errores;
        }

        try {
            $this->clienteRules->validar($f);
        } catch (\InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $ident = (string) ($f['identificacion'] ?? '');
            // Excel suele comerse el cero inicial de cédulas y RUC escritos como número.
            if (preg_match('/^\d{9}$|^\d{12}$/', $ident)) {
                $mensaje .= ' ¿Excel le quitó el cero inicial? Escriba la identificación como texto.';
            }
            $errores[] = $mensaje;
        }

        return $errores;
    }
}
