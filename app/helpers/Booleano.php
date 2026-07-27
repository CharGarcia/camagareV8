<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Lectura segura de columnas boolean de PostgreSQL.
 *
 * PDO devuelve los boolean de Postgres como las cadenas 't' / 'f', no como
 * true / false. Eso hace que `empty($fila['activo'])` sea incorrecto: empty('f')
 * es false, así que un registro inactivo se lee como activo. Es un error fácil
 * de cometer y difícil de ver, porque el código "parece" correcto.
 *
 * Use Booleano::es() para leer y Booleano::sql() para escribir.
 */
final class Booleano
{
    /** Valores que Postgres, PDO o un formulario pueden entregar como verdadero. */
    private const VERDADEROS = [true, 1, '1', 't', 'true', 'T', 'TRUE', 'on', 'yes', 'si', 'sí'];

    /** ¿El valor representa un verdadero? */
    public static function es($valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if ($valor === null || $valor === '') {
            return false;
        }
        if (is_string($valor)) {
            return in_array(strtolower(trim($valor)), ['1', 't', 'true', 'on', 'yes', 'si', 'sí'], true);
        }
        return in_array($valor, self::VERDADEROS, true);
    }

    /** Lo contrario de es(), para condiciones que se leen mejor en negativo. */
    public static function no($valor): bool
    {
        return !self::es($valor);
    }

    /**
     * Literal para pasar a PDO al insertar o actualizar un boolean.
     * El patrón del proyecto es enviar la cadena 'true' / 'false'.
     */
    public static function sql($valor): string
    {
        return self::es($valor) ? 'true' : 'false';
    }
}
