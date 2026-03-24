<?php
/**
 * Clase MySQL - driver para db usando mysqli
 * Usa config/parametros.xml
 */
class mysql
{
    private $con = null;
    private $transactions = 0;

    public function connect()
    {
        if ($this->con === null) {
            $xml = simplexml_load_file(dirname(__DIR__) . '/config/parametros.xml');
            $host = (string) $xml->host_db;
            $user = (string) $xml->user_db;
            $pass = (string) $xml->pass_db;
            $name = (string) $xml->db_name;
            $this->con = new mysqli($host, $user, $pass, $name);
            if (mysqli_connect_errno()) {
                return false;
            }
            mysqli_set_charset($this->con, 'utf8');
        }
        return true;
    }

    public function close()
    {
        if ($this->con) {
            $this->con->close();
            $this->con = null;
        }
        return true;
    }

    private function ensureConnection()
    {
        if ($this->con === null) {
            $this->connect();
        }
        return $this->con;
    }

    public function select($sql)
    {
        $con = $this->ensureConnection();
        $r = $con->query($sql);
        if ($r === false) {
            return false;
        }
        if ($r === true) {
            return [];
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $out[] = $row;
        }
        return $out;
    }

    public function select_limit($sql, $limit = 30, $offset = 0)
    {
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->select($sql);
    }

    public function exec($sql, $transaction = true)
    {
        $con = $this->ensureConnection();
        if ($transaction) {
            $this->begin_transaction();
        }
        $ok = $con->query($sql);
        if ($transaction) {
            if ($ok) {
                $this->commit();
            } else {
                $this->rollback();
            }
        }
        return (bool)$ok;
    }

    public function lastval()
    {
        $con = $this->ensureConnection();
        return (int)$con->insert_id;
    }

    public function begin_transaction()
    {
        $con = $this->ensureConnection();
        if ($this->transactions === 0) {
            $con->begin_transaction();
        }
        $this->transactions++;
        return true;
    }

    public function commit()
    {
        $this->transactions--;
        if ($this->transactions === 0 && $this->con) {
            $this->con->commit();
        }
        return true;
    }

    public function rollback()
    {
        if ($this->con) {
            $this->con->rollback();
        }
        $this->transactions = 0;
        return true;
    }

    public function escape_string($str)
    {
        $con = $this->ensureConnection();
        return $con->real_escape_string($str);
    }

    public function date_style()
    {
        return 'Y-m-d';
    }

    public function list_tables()
    {
        $r = $this->select("SHOW TABLES");
        if ($r === false) {
            return [];
        }
        $xml = simplexml_load_file(dirname(__DIR__) . '/config/parametros.xml');
        $key = 'Tables_in_' . (string) $xml->db_name;
        $out = [];
        foreach ($r as $row) {
            $out[] = $row[$key] ?? reset($row);
        }
        return $out;
    }

    public function field_count()
    {
        return 0;
    }

    public function get_transactions()
    {
        return $this->transactions;
    }
}
