<?php

require_once 'mysql.php';
/**
 * Clase génerica de acceso a la base de datos
 */
class db
{
    /**
     * Transacttiones automáticas activadas si o no.
     * @var boolean
     */
    private static $auto_transactions;

    /**
     * Motor utilizado, MySQL o PostgreSQL
     * @var mysql| postgresql
     */
    private static $engine;

    /**
     * Última lista de tablas de la base de datos.
     * @var array|false 
     */
    private static $table_list;

    public function __construct()
    {
        if (!isset(self::$engine)) {
            //if (strtolower(TYPE) == 'mysql') {
            self::$engine = new mysql();
            self::$auto_transactions = TRUE;
            /*} else {
                //self::$engine = new postgresql();
            }*/
        }
    }

    /**
     * Devuelve el valor de auto_transacions, para saber si las transacciones
     * automáticas están activadas o no.
     * @return boolean
     */
    public function get_auto_transactions()
    {
        return self::$auto_transactions;
    }

    /**
     * Activa/desactiva las transacciones automáticas en la función exec()
     * @param boolean $value
     */
    public function set_auto_transactions($value)
    {
        self::$auto_transactions = $value;
    }

    /**
     * Devuelve el nº de transacciones con la base de datos.
     * @return integer
     */
    public function get_transactions()
    {
        return self::$engine->get_transactions();
    }

    /**
     * Conecta a la base de datos.
     * @return boolean
     */
    public function connect()
    {
        return self::$engine->connect();
    }

    /**
     * Desconecta de la base de datos.
     * @return boolean
     */
    public function close()
    {
        return self::$engine->close();
    }

    /**
     * Ejecuta una sentencia SQL de tipo select, y devuelve un array con los resultados,
     * o false en caso de fallo.
     * @param string $sql
     * @return array|false
     */
    public function select($sql)
    {
        return self::$engine->select($sql);
    }

    /**
     * Ejecuta una sentencia SQL de tipo select, pero con paginación,
     * y devuelve un array con los resultados o false en caso de fallo.
     * Limit es el número de elementos que quieres que devuelva.
     * Offset es el número de resultado desde el que quieres que empiece.
     * @param string $sql
     * @param integer $limit
     * @param integer $offset
     * @return array|false
     */
    public function select_limit($sql, $limit = 30, $offset = 0)
    {
        return self::$engine->select_limit($sql, $limit, $offset);
    }

    /**
     * Ejecuta sentencias SQL sobre la base de datos (inserts, updates o deletes).
     * Para hacer selects, mejor usar select() o selec_limit().
     * Por defecto se inicia una transacción, se ejecutan las consultas, y si todo
     * sale bien, se guarda, sino se deshace.
     * Se puede evitar este modo de transacción si se pone false
     * en el parametro transaction, o con la función set_auto_transactions(FALSE)
     * @param string $sql
     * @param boolean $transaction
     * @return boolean
     */
    public function exec($sql, $transaction = NULL)
    {
        /// usamos self::$auto_transactions como valor por defecto para la función
        if (is_null($transaction)) {
            $transaction = self::$auto_transactions;
        }

        return self::$engine->exec($sql, $transaction);
    }

    /**
     * Devuleve el último ID asignado al hacer un INSERT en la base de datos.
     * @return integer
     */
    public function lastval()
    {
        return self::$engine->lastval();
    }

    /**
     * Inicia una transacción SQL.
     * @return boolean
     */
    public function begin_transaction()
    {
        return self::$engine->begin_transaction();
    }

    /**
     * Guarda los cambios de una transacción SQL.
     * @return boolean
     */
    public function commit()
    {
        return self::$engine->commit();
    }

    /**
     * Deshace los cambios de una transacción SQL.
     * @return boolean
     */
    public function rollback()
    {
        return self::$engine->rollback();
    }

    /**
     * Escapa las comillas de la cadena de texto.
     * @param string $str
     * @return string
     */
    public function escape_string($str)
    {
        return self::$engine->escape_string($str);
    }

    /**
     * Devuelve el estilo de fecha del motor de base de datos.
     * @return string
     */
    public function date_style()
    {
        return self::$engine->date_style();
    }

    /**
     * Transforma una variable en una cadena de texto válida para ser
     * utilizada en una consulta SQL.
     * @param mixed $val
     * @return string
     * NOTA: esta funcion deberia ir el modelo, estara ubicada aqui temporalmente
     */
    /*     public function var2str($val)
    {
        if (is_null($val)) {
            return 'NULL';
        } else if (is_bool($val)) {
            if ($val) {
                return 'TRUE';
            } else {
                return 'FALSE';
            }
        } else if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})$/i', $val)) { /// es una fecha
            return "'" . Date($this->date_style(), strtotime($val)) . "'";
        } else if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})$/i', $val)) { /// es una fecha+hora
            return "'" . Date($this->date_style() . ' H:i:s', strtotime($val)) . "'";
        }

        return "'" . $this->escape_string($val) . "'";
    } */

    public function var2str($val)
    {
        if (is_null($val)) {
            return 'NULL';
        } else if (is_bool($val)) {
            return $val ? 'TRUE' : 'FALSE';
        }

        // Detectar formato YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val)) {
            return "'" . date($this->date_style(), strtotime($val)) . "'";
        }

        // Detectar formato YYYY-MM-DD HH:MM:SS
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $val)) {
            return "'" . date($this->date_style() . ' H:i:s', strtotime($val)) . "'";
        }

        // Detectar formato DD-MM-YYYY
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $val)) {
            return "'" . date($this->date_style(), strtotime($val)) . "'";
        }

        // Detectar formato DD-MM-YYYY HH:MM:SS
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4}) (\d{1,2}):(\d{1,2}):(\d{1,2})$/', $val)) {
            return "'" . date($this->date_style() . ' H:i:s', strtotime($val)) . "'";
        }

        return "'" . $this->escape_string($val) . "'";
    }



    /**
     * Devuelve un array con los nombres de las tablas de la base de datos.
     * @return array
     */
    public function list_tables()
    {
        if (self::$table_list === FALSE) {
            self::$table_list = self::$engine->list_tables();
        }

        return self::$table_list;
    }

    /**
     * .
     * @return 
     */
    public function field_count()
    {
        return self::$engine->field_count();
    }
}
